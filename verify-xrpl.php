<?php
/**
 * verify-xrpl.php — the XRP Ledger half of NFT verification.
 *
 * Sibling of verifyNFTs() in verify.php, not a replacement. It does the same
 * job against a different ledger and hands its results to the SAME
 * processNFT(), whose signature was already chain-agnostic: it takes a policy,
 * an asset id, a name, an image and an address, and none of those have to be
 * Cardano-shaped. See multichain.md §5.
 *
 * SEPARATE FILE, AND SEPARATE CRON PASS. The two chains verify independently so
 * one being unreachable cannot cost holders on the other their staking for a
 * night (multichain.md §5b). That isolation is only real if the reset step is
 * chain-scoped too -- removeUsers() zeroes nfts.user_id for everyone about to
 * be re-verified, and a Cardano pass that zeroed XRPL rows would never restore
 * them. Silent, total, and not obvious until somebody complains.
 *
 * EVERY EXTERNAL CALL IS INJECTABLE. $fetch is a callable so the whole file can
 * be exercised with no network and no database -- which is how the Arena engine
 * was built and the reason its rules could be tested at all. See
 * verify-xrpl-harness.php.
 *
 * Nothing here talks to Xaman. Xaman is sign-in only, twice per wallet link;
 * the nightly pass reads the ledger directly from a public cluster and is free
 * at any volume this platform will reach (multichain.md §4e).
 */

if (!defined('XRPL_CHAIN_ID')) define('XRPL_CHAIN_ID', 2);
/* account_nfts pages at 20-400, default 100. A collector with more than one
   page is silently truncated if the marker loop is skipped -- they just appear
   to own fewer NFTs, with no error anywhere. Ask for the maximum and still
   follow the marker. */
if (!defined('XRPL_PAGE_SIZE'))  define('XRPL_PAGE_SIZE', 400);
/* A guard against a node that keeps handing back the same marker. 40 pages at
   400 is 16,000 NFTs for one account, far past anything real. */
if (!defined('XRPL_MAX_PAGES'))  define('XRPL_MAX_PAGES', 40);
if (!defined('XRPL_HTTP_TIMEOUT')) define('XRPL_HTTP_TIMEOUT', 20);

/* ---------- pure helpers: no network, no database ------------------------- */

/**
 * A collection on XRPL is issuer + taxon, not one hash. Joined with a colon and
 * stored in collections.policy as an opaque string, so getPolicies() and
 * getCollectionIDs() keep working without learning what a taxon is.
 */
function xrpl_collection_key($issuer, $taxon) {
	return trim((string)$issuer) . ':' . (string)(int)$taxon;
}

/** XRPL stores URIs hex-encoded. Returns '' for anything that is not clean hex. */
function xrpl_hex_to_str($hex) {
	$hex = trim((string)$hex);
	if ($hex === '' || strlen($hex) % 2 !== 0 || !ctype_xdigit($hex)) return '';
	$out = @hex2bin($hex);
	return is_string($out) ? $out : '';
}

/**
 * processNFT() does substr($image, 7) to strip a leading "ipfs://" before
 * storing, so whatever we hand it has to carry a seven-character scheme or the
 * first seven characters of the CID are eaten. That is a contract with an
 * existing function, not a preference -- hence normalising to ipfs:// here and
 * leaving http(s) alone, which processNFT's other branch handles.
 */
function xrpl_normalise_image($uri) {
	$uri = trim((string)$uri);
	if ($uri === '') return '';
	if (strpos($uri, 'ipfs://') === 0) {
		// some collections write ipfs://ipfs/<cid>
		$rest = substr($uri, 7);
		if (strpos($rest, 'ipfs/') === 0) $rest = substr($rest, 5);
		return 'ipfs://' . $rest;
	}
	if (preg_match('#^https?://[^/]*/ipfs/(.+)$#i', $uri, $m)) return 'ipfs://' . $m[1];
	// a bare CID, which is common enough to be worth catching
	if (preg_match('#^(Qm[1-9A-HJ-NP-Za-km-z]{44}|b[a-z2-7]{58})#', $uri)) return 'ipfs://' . $uri;
	return $uri;
}

/** An ipfs:// URI as something HTTP can fetch. */
function xrpl_gateway_url($uri, $gateway) {
	$uri = trim((string)$uri);
	if (strpos($uri, 'ipfs://') === 0) return rtrim($gateway, '/') . '/' . substr($uri, 7);
	return $uri;
}

/**
 * One account_nfts page into the fields processNFT() wants.
 *
 * Returns array(list, marker). `marker` is null when the ledger says there are
 * no more pages -- the caller must keep going until it is.
 */
function xrpl_parse_page($json) {
	$out = array('list' => array(), 'marker' => null);
	if (!is_array($json)) return $out;
	// JSON-RPC wraps the answer in `result`; a websocket reply does not.
	$res = isset($json['result']) && is_array($json['result']) ? $json['result'] : $json;
	if (isset($res['marker']) && $res['marker'] !== '') $out['marker'] = $res['marker'];
	if (!isset($res['account_nfts']) || !is_array($res['account_nfts'])) return $out;

	foreach ($res['account_nfts'] as $n) {
		if (!is_array($n) || empty($n['NFTokenID'])) continue;
		$issuer = isset($n['Issuer']) ? $n['Issuer'] : '';
		$taxon  = isset($n['NFTokenTaxon']) ? (int)$n['NFTokenTaxon'] : 0;
		$out['list'][] = array(
			'id'      => (string)$n['NFTokenID'],
			'policy'  => xrpl_collection_key($issuer, $taxon),
			'issuer'  => $issuer,
			'taxon'   => $taxon,
			'serial'  => isset($n['nft_serial']) ? (int)$n['nft_serial'] : 0,
			'uri'     => isset($n['URI']) ? xrpl_hex_to_str($n['URI']) : '',
		);
	}
	return $out;
}

/**
 * Name and image for one NFToken, from the JSON its URI points at.
 *
 * ALWAYS RETURNS A USABLE NAME. processNFT() skips an NFT with no name, so a
 * flaky IPFS gateway would otherwise mean somebody's asset silently fails to
 * stake. A synthesised name is worse than the real one and far better than
 * nothing, and the row can be corrected later; being unable to stake cannot.
 *
 * This runs ONCE PER NFT EVER, not nightly: processNFT() only reaches
 * createNFT() for an asset it has not seen, so afterwards the row exists and
 * only ownership changes.
 */
function xrpl_resolve_metadata($nft, $gateway, $fetch) {
	$fallback = array(
		'name'  => 'XRPL #' . $nft['serial'],
		'image' => '',
	);
	if (empty($nft['uri'])) return $fallback;

	$url = xrpl_gateway_url(xrpl_normalise_image($nft['uri']), $gateway);
	if ($url === '' || !preg_match('#^https?://#i', $url)) return $fallback;

	$body = call_user_func($fetch, $url, null);
	if (!is_string($body) || $body === '') return $fallback;

	$meta = json_decode($body, true);
	if (!is_array($meta)) {
		/* Not JSON. Plenty of collections point the URI straight at the image,
		   in which case the URI itself is the picture and there is no name. */
		return array('name' => $fallback['name'], 'image' => xrpl_storable_image($nft['uri']));
	}
	$name = '';
	foreach (array('name', 'title') as $k) {
		if (!empty($meta[$k]) && is_string($meta[$k])) { $name = $meta[$k]; break; }
	}
	$img = '';
	foreach (array('image', 'image_url', 'imageUrl', 'animation_url') as $k) {
		if (!empty($meta[$k]) && is_string($meta[$k])) { $img = $meta[$k]; break; }
	}
	return array(
		'name'  => $name !== '' ? $name : $fallback['name'],
		'image' => xrpl_storable_image($img),
	);
}

/**
 * IPFS OR NOTHING.
 *
 * nfts.ipfs must hold a BARE CID, because the image cache builds its URL as
 * gateway . value (_fetchRace in lib/image-cache-lib.php). processNFT() gets
 * there by chopping exactly seven characters off the front, which is right for
 * "ipfs://" and wrong for everything else -- "https://host/a.png" would be
 * stored as "/host/a.png" and then fetched as "https://ipfs.io/ipfs//host/a.png".
 *
 * So an image that is not IPFS is dropped rather than mangled. The NFT still
 * stakes; it just has no cached picture, which is recoverable. A poisoned
 * nfts.ipfs is not: it looks like data, it fails silently in a nightly worker,
 * and nothing points at why.
 *
 * The alternative was loosening processNFT()'s substr(7) for both chains. Not
 * worth it for a case Maxingo's collections will not hit -- and if a future
 * collection does host off IPFS, THIS is the one function to change.
 */
function xrpl_storable_image($img) {
	if ($img === '' || $img === null) return '';
	$norm = xrpl_normalise_image($img);
	if (strpos($norm, 'ipfs://') !== 0) {
		error_log('verify-xrpl: dropping non-IPFS image ' . substr((string)$img, 0, 120));
		return '';
	}
	/* The cache skips anything under 46 characters as a malformed CID, so a
	   truncated one would be silently dropped there instead of here. Catch it
	   where the log line can say which NFT it came from. */
	if (strlen(substr($norm, 7)) < 46) {
		error_log('verify-xrpl: dropping short CID ' . substr($norm, 0, 80));
		return '';
	}
	return $norm;
}

/* ---------- the network ---------------------------------------------------- */

/** The default fetcher. Replaced wholesale by the harness. */
function xrpl_http($url, $post) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, XRPL_HTTP_TIMEOUT);
	if ($post !== null) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	}
	$body = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	/* No curl_close(): it has had no effect since PHP 8.0 and is deprecated
	   from 8.5, and the notice would print AHEAD of this endpoint's JSON and
	   break the client's parse. The handle is freed when it goes out of
	   scope. Same trap dhc-json.php already documents. */
	if ($body === false || $code >= 400) return '';
	return $body;
}

/**
 * Every NFToken held by one account, following the marker to the last page.
 *
 * Returns array(list, ok). `ok` is false if any page failed, and the caller
 * MUST NOT treat a partial list as the truth -- a half-read account looks
 * exactly like an account that sold half its NFTs, and acting on it would
 * unstake assets the player still owns.
 */
function xrpl_account_nfts($api_base, $account, $fetch) {
	$all = array(); $marker = null; $pages = 0;
	do {
		$req = array(
			'method' => 'account_nfts',
			'params' => array(array(
				'account'      => $account,
				'limit'        => XRPL_PAGE_SIZE,
				'ledger_index' => 'validated',
			)),
		);
		if ($marker !== null) $req['params'][0]['marker'] = $marker;

		$body = call_user_func($fetch, $api_base, json_encode($req));
		if (!is_string($body) || $body === '') return array('list' => $all, 'ok' => false);

		$json = json_decode($body, true);
		if (!is_array($json)) return array('list' => $all, 'ok' => false);
		$res = isset($json['result']) && is_array($json['result']) ? $json['result'] : $json;
		// the ledger reports its own failures in a field, not an HTTP code
		if (isset($res['status']) && $res['status'] === 'error') {
			/* actNotFound is not a failure: it is an account that has never been
			   funded, which owns nothing and should read as empty rather than
			   as a broken run. */
			$err = isset($res['error']) ? $res['error'] : '';
			return array('list' => $all, 'ok' => ($err === 'actNotFound'));
		}

		$page   = xrpl_parse_page($json);
		$all    = array_merge($all, $page['list']);
		$marker = $page['marker'];
	} while ($marker !== null && ++$pages < XRPL_MAX_PAGES);

	return array('list' => $all, 'ok' => true);
}

/* ---------- the pass ------------------------------------------------------- */

/**
 * One XRPL verification pass. Mirrors verifyNFTs()'s contract so the cron can
 * call either the same way.
 *
 * $collections maps collections.policy -> collections.id, exactly as
 * getCollectionIDs() returns it. An NFToken whose issuer:taxon is not in there
 * is somebody's unrelated art and is skipped -- we verify what we stake, not
 * everything a wallet happens to hold.
 *
 * Returns array(asset_ids, nft_owners, ok, failed). `ok` false means at least
 * one address could not be read and the caller must not treat absence as loss.
 */
function verifyNFTsXRPL($conn, $addresses, $collections, $asset_ids,
                        $nft_owners = array(), $opt = array()) {
	$api     = isset($opt['api_base']) ? $opt['api_base'] : 'https://xrplcluster.com';
	$gateway = isset($opt['gateway'])  ? $opt['gateway']  : 'https://ipfs.io/ipfs/';
	$fetch   = isset($opt['fetch'])    ? $opt['fetch']    : 'xrpl_http';
	$failed  = array();

	foreach ($addresses as $address) {
		$address = trim((string)$address);
		if ($address === '') continue;

		$got = xrpl_account_nfts($api, $address, $fetch);
		if (!$got['ok']) {
			/* Skip the WHOLE address rather than process what did arrive. A
			   partial read is indistinguishable from a sale. */
			$failed[] = $address;
			continue;
		}

		foreach ($got['list'] as $nft) {
			if (!isset($collections[$nft['policy']])) continue;   // not ours

			$meta = xrpl_resolve_metadata($nft, $gateway, $fetch);
			$payload = processNFT(
				$conn,
				$nft['policy'],        // policy_id   -> collections lookup
				$nft['id'],            // asset_name
				$meta['name'],         // name        -- never empty, see above
				$meta['image'],        // image       -> ipfs:// , stripped by processNFT
				$nft['id'],            // fingerprint -> nfts.asset_id
				$address,
				$asset_ids, $nft_owners, $collections
			);
			if (is_array($payload)) {
				$asset_ids  = $payload['asset_ids'];
				$nft_owners = $payload['nft_owners'];
			}
		}
	}

	return array(
		'asset_ids'  => $asset_ids,
		'nft_owners' => $nft_owners,
		'ok'         => empty($failed),
		'failed'     => $failed,
	);
}
