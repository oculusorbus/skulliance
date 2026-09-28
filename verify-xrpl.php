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
 * Verify ONE user's XRPL wallets, right now.
 *
 * A wallet connect verifies immediately on the Cardano side -- link, clear
 * that user, re-verify -- and it has to here too, or somebody links Xaman,
 * sees nothing, and concludes it did not work. "It will appear tomorrow" is
 * not a thing to tell somebody who just proved they own something.
 *
 * Same read-then-write order as the nightly pass and for the same reason: if
 * the ledger cannot be read, this user's existing rows are left exactly as
 * they were rather than cleared and half-rebuilt.
 *
 * Returns array(ok, staked, message).
 */
function xrpl_verify_user($conn, $user_id, $budget = 45) {
	try {
		$user_id = (int)$user_id;
		if ($user_id <= 0) return array('ok' => false, 'staked' => 0, 'message' => '');

		$collections = getCollectionIDs($conn, XRPL_CHAIN_ID);
		if (!$collections)
			return array('ok' => true, 'staked' => 0,
				'message' => 'No XRPL collections are registered yet.');

		/* This user's XRPL addresses only. getAddresses() is Cardano-shaped and
		   unscoped, so it is not reused here. */
		$addresses = array();
		$res = $conn->query("SELECT stake_address FROM wallets
		                     WHERE user_id = $user_id AND blockchain_id = " . XRPL_CHAIN_ID);
		if ($res) while ($r = $res->fetch_assoc()) $addresses[] = $r['stake_address'];
		if (!$addresses) return array('ok' => true, 'staked' => 0, 'message' => '');

		$out = verifyNFTsXRPL($conn, $addresses, $collections,
			getNFTAssetIDs($conn, XRPL_CHAIN_ID), array(), array(
				'api_base' => getChainSetting($conn, XRPL_CHAIN_ID, 'api_base', 'https://xrplcluster.com'),
				'gateway'  => getChainSetting($conn, XRPL_CHAIN_ID, 'ipfs_gateway', 'https://ipfs.io/ipfs/'),
				/* Short: somebody is watching a spinner. If the ledger is slow the
				   nightly pass will pick it up, which is a far better outcome than
				   a request that hangs. */
				'deadline' => time() + (int)$budget,
				'clear'    => function() use ($conn, $user_id) {
					removeUser($conn, $user_id, XRPL_CHAIN_ID);
				},
			));

		if (!$out['ok'])
			return array('ok' => false, 'staked' => 0,
				'message' => 'Could not read the ledger just now — your NFTs will be '
				           . 'picked up tonight.');

		return array('ok' => true, 'staked' => $out['wrote'], 'message' => '');
	} catch (Throwable $e) {
		error_log('xrpl_verify_user: ' . $e->getMessage());
		return array('ok' => false, 'staked' => 0,
			'message' => 'Wallet linked. Your NFTs will be counted tonight.');
	}
}

/**
 * The XRPL phase, as the nightly job runs it. One function so the scheduled
 * path and the manual path cannot drift.
 *
 * Returns a one-line report, and NEVER throws: this runs inside the job that
 * pays everybody, so an XRPL problem must cost XRPL holders a night and cost
 * nobody else anything.
 *
 * $budget bounds it for the same reason. A hanging node must not hold up the
 * payout, and because the pass reads before it writes, running out of time
 * aborts cleanly with yesterday's rows intact.
 */
function xrpl_nightly($conn, $budget = 600) {
	try {
		if (!function_exists('getCollectionIDs') || !function_exists('getAllAddresses'))
			return 'xrpl: skipped (platform functions unavailable)';

		$collections = getCollectionIDs($conn, XRPL_CHAIN_ID);
		$addresses   = getAllAddresses($conn, XRPL_CHAIN_ID);
		if (!$collections || !$addresses)
			return sprintf('xrpl: nothing to do (%d addresses, %d collections)',
				count($addresses), count($collections));

		$res = verifyNFTsXRPL($conn, $addresses, $collections,
			getNFTAssetIDs($conn, XRPL_CHAIN_ID), array(), array(
				'api_base' => getChainSetting($conn, XRPL_CHAIN_ID, 'api_base', 'https://xrplcluster.com'),
				'gateway'  => getChainSetting($conn, XRPL_CHAIN_ID, 'ipfs_gateway', 'https://ipfs.io/ipfs/'),
				'deadline' => time() + (int)$budget,
				'clear'    => function() use ($conn) { removeUsers($conn, XRPL_CHAIN_ID); },
			));

		if (!$res['ok']) {
			$msg = 'xrpl: SKIPPED WRITE — could not read ' . implode(', ', $res['failed'])
			     . '. Yesterday\'s rows stand.';
			error_log($msg);
			return $msg;
		}
		return sprintf('xrpl: %d addresses read, %d NFTs staked across %d collections',
			$res['read'], $res['wrote'], count($collections));
	} catch (Throwable $e) {
		/* Never let this reach the payout step. */
		$msg = 'xrpl: FAILED — ' . $e->getMessage();
		error_log($msg);
		return $msg;
	}
}


/**
 * One XRPL verification pass, READ FIRST AND WRITE SECOND.
 *
 * THE ORDER IS THE SAFETY. The Cardano pattern clears ownership up front and
 * trusts the verifier to put it all back, which is fine when nothing can
 * interrupt it. Here it cannot be: if a node is slow, or one address in fifty
 * is unreadable, a cleared-then-partially-rebuilt table is not a smaller
 * truth, it is a wrong one -- and the payout step reads it.
 *
 * So the whole ledger is read before a single row is touched. If ANY address
 * fails, nothing is cleared and nothing is written: yesterday's rows stand,
 * which are correct for everybody who has not traded since. A missed night is
 * recoverable. A night where half the holders read as having sold everything
 * is paid out and gone.
 *
 * $clear is the caller's chain-scoped removeUsers(), injected so this function
 * owns the ordering rather than hoping the caller gets it right -- the clear
 * has to happen between the two phases, and that is not a thing to leave to a
 * comment.
 *
 * Returns array(asset_ids, nft_owners, ok, failed, read, wrote).
 */
function verifyNFTsXRPL($conn, $addresses, $collections, $asset_ids,
                        $nft_owners = array(), $opt = array()) {
	$api     = isset($opt['api_base']) ? $opt['api_base'] : 'https://xrplcluster.com';
	$gateway = isset($opt['gateway'])  ? $opt['gateway']  : 'https://ipfs.io/ipfs/';
	$fetch   = isset($opt['fetch'])    ? $opt['fetch']    : 'xrpl_http';
	$clear   = isset($opt['clear'])    ? $opt['clear']    : null;
	$deadline = isset($opt['deadline']) ? (int)$opt['deadline'] : 0;

	/* ---- phase one: read everything, write nothing ---- */
	$held = array(); $failed = array(); $read = 0;
	foreach ($addresses as $address) {
		$address = trim((string)$address);
		if ($address === '') continue;

		/* A budget, because this can run inside the job that pays people and
		   must not be able to delay it indefinitely. Running out of time is a
		   failure like any other: it aborts the write rather than writing
		   what it managed. */
		if ($deadline && time() > $deadline) {
			$failed[] = $address . ' (out of time)';
			continue;
		}

		$got = xrpl_account_nfts($api, $address, $fetch);
		if (!$got['ok']) { $failed[] = $address; continue; }
		$held[$address] = $got['list'];
		$read++;
	}

	/* ---- the gate ---- */
	if ($failed) {
		return array('asset_ids' => $asset_ids, 'nft_owners' => $nft_owners,
			'ok' => false, 'failed' => $failed, 'read' => $read, 'wrote' => 0);
	}

	/* ---- phase two: now it is safe to clear and rebuild ---- */
	if ($clear !== null) call_user_func($clear);

	$wrote = 0;
	foreach ($held as $address => $list) {
		foreach ($list as $nft) {
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
			$wrote++;
		}
	}

	return array('asset_ids' => $asset_ids, 'nft_owners' => $nft_owners,
		'ok' => true, 'failed' => array(), 'read' => $read, 'wrote' => $wrote);
}
