<?php
/**
 * verify-solana.php — the Solana half of NFT verification.
 *
 * Third sibling of verifyNFTs() in verify.php, after verify-xrpl.php, and
 * built to the same shape on purpose: read the chain, hand the results to the
 * SAME processNFT(), write nothing until every address has been read. If you
 * are reading this before that file, read that one first — the reasoning
 * behind the ordering, the injectable fetcher and the chain-scoped clear is
 * written out there and only summarised here.
 *
 * WHAT IS DIFFERENT ABOUT SOLANA, AND IT IS ALL IN OUR FAVOUR:
 *
 *   1. ONE CALL PER WALLET, NO PAGINATION. getProgramAccounts with a memcmp on
 *      the owner field returns every asset a wallet holds in one response.
 *      There is no marker loop to forget, which was XRPL's silent-truncation
 *      trap (multichain.md §5).
 *   2. THE NAME IS ON-CHAIN. An MPL Core asset account carries its own name,
 *      so an NFT never has to be called "Solana #12345" because a gateway had
 *      a bad minute. Only the image needs the metadata document.
 *   3. NO CREDENTIALS. The public cluster answers unauthenticated, like
 *      XRPL's and unlike Blockfrost.
 *
 * METAPLEX CORE, NOT SPL TOKEN. This reads the MPL Core program only. A Core
 * asset is a SINGLE account holding owner, collection, name and uri — not the
 * mint + token account + metadata PDA triple of the older Token Metadata
 * standard. That is why ownership is a byte range at a fixed offset rather
 * than a token-account scan, and it is why this file is short.
 *
 * Collections on other Solana standards (Token Metadata, compressed NFTs)
 * would each need their own reader and are NOT handled. That is deliberate
 * rather than an oversight: the collection this was built for is Core, a
 * reader that half-supports a standard is worse than one that does not claim
 * to, and sol_account_assets() returns only what it can actually prove.
 * Compressed NFTs in particular are not accounts at all — they live in a
 * Merkle tree and cannot be read this way at any price.
 *
 * EVERY EXTERNAL CALL IS INJECTABLE. $fetch is a callable so the whole file
 * runs with no network and no database. See verify-solana-harness.php.
 */

require_once __DIR__ . '/lib/ipfs-gateways.php';

if (!defined('SOLANA_CHAIN_ID')) define('SOLANA_CHAIN_ID', 3);

/* The MPL Core program. Every asset account this file reads is owned by it.
   Hard-coded rather than a chain setting: it is the definition of the
   standard being read, not configuration — a different value would not be a
   different endpoint, it would be a different parser. */
if (!defined('SOLANA_CORE_PROGRAM'))
	define('SOLANA_CORE_PROGRAM', 'CoREENxT6tW1HoK8ypY1SxRMZTcVPm7R94rH4PZNhX7d');

/* Byte offsets into an MPL Core AssetV1 account:
     0        key (u8), 1 = AssetV1, 5 = CollectionV1
     1..32    owner pubkey
     33       update authority kind: 0 None, 1 Address, 2 Collection
     34..65   that authority's pubkey, when the kind is 1 or 2
     then     name (u32 length + bytes), uri (u32 length + bytes)
   Only the owner offset is used as a server-side filter; everything else is
   re-read and re-checked locally, so a filter match is never trusted on its
   own. */
if (!defined('SOLANA_OWNER_OFFSET')) define('SOLANA_OWNER_OFFSET', 1);

if (!defined('SOLANA_HTTP_TIMEOUT'))      define('SOLANA_HTTP_TIMEOUT', 20);
if (!defined('SOLANA_FETCH_CONCURRENCY')) define('SOLANA_FETCH_CONCURRENCY', 12);

/* A wallet holding more Core assets than this is not read at all, rather than
   read partially. getProgramAccounts has no paging, so a response that large
   is the point at which the public cluster starts refusing or truncating —
   and a truncated holdings list reads as "they sold most of it" to the pass
   that pays people. Far past any real collector; the whole OMEN collection is
   7,209 accounts and that is every holder put together. */
if (!defined('SOLANA_MAX_ASSETS')) define('SOLANA_MAX_ASSETS', 20000);

/**
 * IPFS gateways, for a Solana collection that stores metadata on IPFS.
 *
 * The collection this was built for does NOT — it serves plain https — but
 * plenty of Solana collections do, and a reader that only handled one would
 * fail the next artist silently. Same list and same reasoning as
 * verify-xrpl.php: one gateway 429s under load and the failure looks like
 * "this collection has no metadata".
 */
function sol_gateways() { return ipfs_gateways(); }

/* ---------- base58 --------------------------------------------------------- */

/*
 * PURE PHP, NO bcmath AND NO gmp.
 *
 * verify-xrpl.php's base58 uses bcmath, which is present on this server
 * today. This one does not depend on that still being true after a PHP
 * version change in cPanel, because the failure mode is ugly: an undefined
 * bcadd() is a fatal inside the nightly job that pays everybody. Long
 * division over a byte array is a dozen lines and cannot be switched off.
 *
 * Note the alphabet is the BITCOIN one. XRPL uses a different ordering for
 * the same 58 characters, so the two encoders are not interchangeable even
 * though they look it — swapping them produces plausible, wrong addresses.
 */
function sol_b58_alphabet() { return '123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz'; }

function sol_base58_encode($bytes) {
	if (!is_string($bytes) || $bytes === '') return '';
	$ab = sol_b58_alphabet();
	$digits = array(0);
	for ($i = 0; $i < strlen($bytes); $i++) {
		$carry = ord($bytes[$i]);
		for ($j = 0; $j < count($digits); $j++) {
			$carry += $digits[$j] << 8;
			$digits[$j] = $carry % 58;
			$carry = (int)($carry / 58);
		}
		while ($carry > 0) { $digits[] = $carry % 58; $carry = (int)($carry / 58); }
	}
	/* Strip the digit array's own leading zeros before emitting. It starts
	   life as [0], so an all-zero input would otherwise emit one '1' of
	   magnitude on top of one per zero byte -- 33 characters for a 32-byte
	   key. Symmetric with the same correction in the decoder. */
	$top = count($digits) - 1;
	while ($top > 0 && $digits[$top] === 0) $top--;
	$mag = '';
	if (!($top === 0 && $digits[0] === 0))
		for ($i = $top; $i >= 0; $i--) $mag .= $ab[$digits[$i]];

	/* Leading zero bytes are not magnitude, so they survive as '1's. */
	$out = '';
	for ($i = 0; $i < strlen($bytes) && $bytes[$i] === "\x00"; $i++) $out .= $ab[0];
	return $out . $mag;
}

/** Returns raw bytes, or '' if the string is not valid base58. */
function sol_base58_decode($s) {
	if (!is_string($s) || $s === '') return '';
	$ab = sol_b58_alphabet();
	$map = array();
	for ($i = 0; $i < 58; $i++) $map[$ab[$i]] = $i;

	$bytes = array(0);
	for ($i = 0; $i < strlen($s); $i++) {
		if (!isset($map[$s[$i]])) return '';          // not base58 at all
		$carry = $map[$s[$i]];
		for ($j = 0; $j < count($bytes); $j++) {
			$carry += $bytes[$j] * 58;
			$bytes[$j] = $carry & 0xff;
			$carry >>= 8;
		}
		while ($carry > 0) { $bytes[] = $carry & 0xff; $carry >>= 8; }
	}
	/* The magnitude, most significant byte first, with its own leading zeros
	   stripped -- the digit array always starts life as [0] and a value that
	   is entirely zeros would otherwise contribute a byte that is not there.
	   That is not a hypothetical: the all-zero pubkey is the System Program,
	   written '1' x 32, and the first version of this returned 33 bytes for
	   it and so called it an invalid address. */
	$mag = '';
	for ($i = count($bytes) - 1; $i >= 0; $i--) $mag .= chr($bytes[$i]);
	$mag = ltrim($mag, "\x00");

	/* Leading zero BYTES are not magnitude; base58 writes one '1' each, and
	   they are restored here rather than inferred from the length. */
	$zeros = 0;
	for ($i = 0; $i < strlen($s) && $s[$i] === $ab[0]; $i++) $zeros++;

	return str_repeat("\x00", $zeros) . $mag;
}

/**
 * Is this a Solana address?
 *
 * A pubkey is 32 bytes, which is 32 to 44 base58 characters. Checked by
 * DECODING rather than by a length regex: "0OIl" look like base58 and are
 * not, and a 44-character string of legal characters can still decode to 33
 * bytes. The cheap check passes things the chain will reject, and the place
 * that notices is the nightly job.
 */
function sol_valid_address($s) {
	$s = trim((string)$s);
	if ($s === '' || strlen($s) < 32 || strlen($s) > 44) return false;
	$raw = sol_base58_decode($s);
	return strlen($raw) === 32;
}

/* ---------- the account ---------------------------------------------------- */

/**
 * Decode one MPL Core AssetV1 account.
 *
 * Returns array(owner, collection, name, uri) or null for anything that is
 * not an asset — a CollectionV1 account, a plugin account, a truncated read.
 * NULL RATHER THAN A GUESS: every field here ends up in a database row, and
 * a misparse would write a real-looking NFT with somebody else's bytes in it.
 *
 * `collection` is '' when the asset is not in one (update authority None or
 * a plain Address). Those are skipped by the caller rather than treated as a
 * collection of their own.
 */
function sol_parse_asset($b64) {
	if (!is_string($b64) || $b64 === '') return null;
	$d = base64_decode($b64, true);
	if ($d === false || strlen($d) < 70) return null;
	if (ord($d[0]) !== 1) return null;                 // not AssetV1

	$p = 1;
	$owner = sol_base58_encode(substr($d, $p, 32)); $p += 32;
	$kind  = ord($d[$p]); $p++;
	$coll  = '';
	if ($kind === 1 || $kind === 2) {
		if (strlen($d) < $p + 32) return null;
		/* Only kind 2 is a collection. Kind 1 is a plain update authority and
		   must NOT be read as one, or every asset an artist controls would
		   look like one collection. */
		if ($kind === 2) $coll = sol_base58_encode(substr($d, $p, 32));
		$p += 32;
	}

	$str = function ($d, &$p) {
		if (strlen($d) < $p + 4) return null;
		$len = unpack('V', substr($d, $p, 4))[1]; $p += 4;
		/* A length longer than the account is a misparse, not a long name. */
		if ($len > 2048 || strlen($d) < $p + $len) return null;
		$s = substr($d, $p, $len); $p += $len;
		return $s;
	};
	$name = $str($d, $p); if ($name === null) return null;
	$uri  = $str($d, $p); if ($uri  === null) return null;

	return array(
		'owner'      => $owner,
		'collection' => $coll,
		'name'       => trim($name),
		'uri'        => trim($uri),
	);
}

/**
 * Every MPL Core asset one wallet holds.
 *
 * ONE REQUEST, NO PAGING. The memcmp filter is applied by the node, so the
 * response is already this wallet's assets rather than the whole program's.
 *
 * Returns array(list, ok). `ok` false means the chain could not be read and
 * the caller must NOT write — an empty list and a failed read look identical
 * downstream otherwise, and one of them means "sold everything".
 */
function sol_account_assets($api_base, $address, $fetch) {
	if (!sol_valid_address($address)) return array('list' => array(), 'ok' => false);

	$req = array(
		'jsonrpc' => '2.0', 'id' => 1, 'method' => 'getProgramAccounts',
		'params'  => array(SOLANA_CORE_PROGRAM, array(
			'encoding'   => 'base64',
			'commitment' => 'confirmed',
			'filters'    => array(array('memcmp' => array(
				'offset' => SOLANA_OWNER_OFFSET, 'bytes' => trim($address),
			))),
		)),
	);

	$body = call_user_func($fetch, $api_base, json_encode($req));
	if (!is_string($body) || $body === '') return array('list' => array(), 'ok' => false);

	$json = json_decode($body, true);
	if (!is_array($json)) return array('list' => array(), 'ok' => false);
	/* JSON-RPC reports its failures in a field, not an HTTP code — a 200 with
	   an `error` key is the normal way to be rate-limited here. */
	if (isset($json['error'])) return array('list' => array(), 'ok' => false);
	if (!isset($json['result']) || !is_array($json['result']))
		return array('list' => array(), 'ok' => false);
	if (count($json['result']) > SOLANA_MAX_ASSETS)
		return array('list' => array(), 'ok' => false);

	$out = array();
	foreach ($json['result'] as $row) {
		if (!isset($row['pubkey']) || !isset($row['account']['data'][0])) continue;
		$a = sol_parse_asset($row['account']['data'][0]);
		if ($a === null || $a['collection'] === '') continue;
		/* The node said this wallet owns it; the account says who owns it.
		   Believe the account. A filter offset off by one would otherwise
		   hand somebody else's holdings to this user. */
		if ($a['owner'] !== trim($address)) continue;
		$a['id'] = $row['pubkey'];
		$out[] = $a;
	}
	return array('list' => $out, 'ok' => true);
}

/* ---------- metadata ------------------------------------------------------- */

/** ipfs://CID, /ipfs/CID and a bare CID all fold to ipfs://CID. */
function sol_normalise_image($uri) {
	$uri = trim((string)$uri);
	if ($uri === '') return '';
	if (strpos($uri, 'ipfs://') === 0) {
		$rest = substr($uri, 7);
		if (strpos($rest, 'ipfs/') === 0) $rest = substr($rest, 5);
		return 'ipfs://' . $rest;
	}
	if (preg_match('~^https?://[^/]+/ipfs/(.+)$~i', $uri, $m)) return 'ipfs://' . $m[1];
	if (preg_match('~^(Qm[1-9A-HJ-NP-Za-km-z]{44}|b[a-z2-7]{58})~', $uri)) return 'ipfs://' . $uri;
	return $uri;
}

/**
 * WHAT MAY BE STORED IN nfts.ipfs.
 *
 * This is where Solana diverges from XRPL, and it is the one change that
 * reaches the Cardano code as well.
 *
 * xrpl_storable_image() drops anything that is not IPFS, because nfts.ipfs
 * had to hold a BARE CID: the image cache built its URL as gateway . value
 * and processNFT() got there by chopping seven characters off the front. An
 * https URL came out as "/host/a.png" and was then fetched from
 * "https://ipfs.io/ipfs//host/a.png". Its comment says that if a collection
 * ever hosts off IPFS, that is the function to change.
 *
 * This is that collection: OMEN serves its art from https://omenati.com. So
 * the column now holds EITHER a bare CID (every row ever written until now)
 * OR a whole absolute http(s) URL, and the three places that read it —
 * processNFT(), getIPFS() and lib/image-cache-lib.php — each learned to tell
 * them apart. Nothing else reads it.
 *
 * Still dropped: anything that is neither. A relative path, an Arweave ar://
 * or a data: URI that is not the SVG case processNFT() already handles would
 * all poison the column, and a poisoned nfts.ipfs is unrecoverable in
 * practice — it looks like data, it fails inside a nightly worker, and
 * nothing points at why.
 */
function sol_storable_image($img) {
	if ($img === '' || $img === null) return '';
	$norm = sol_normalise_image($img);

	if (strpos($norm, 'ipfs://') === 0) {
		/* The cache skips anything under 46 characters as a malformed CID, so
		   a truncated one would be dropped there instead of here, further
		   from the cause. */
		if (strlen(substr($norm, 7)) < 46) {
			error_log('verify-solana: dropping short CID ' . substr($norm, 0, 80));
			return '';
		}
		return $norm;
	}
	if (preg_match('~^https?://[^\s/]+\.[^\s/]+/~i', $norm) && strlen($norm) <= 500) {
		return $norm;
	}
	error_log('verify-solana: dropping unusable image ' . substr((string)$img, 0, 120));
	return '';
}

/**
 * The URLs worth trying for one asset's metadata document, in order.
 *
 * An https URI is itself and nothing else — racing gateways for it would be
 * nonsense. An ipfs:// URI becomes one candidate per gateway.
 */
function sol_metadata_urls($uri, $preferred = '') {
	$uri = sol_normalise_image($uri);
	if ($uri === '') return array();
	if (strpos($uri, 'ipfs://') === 0) {
		$cid = substr($uri, 7);
		/* The chain's own blockchains.ipfs_gateway goes FIRST, then the
		   built-in list as fallback. That is the entire reason the setting is
		   a column rather than a constant: a gateway going bad is a config
		   change, not a deploy. De-duplicated, so naming one that is already
		   in the list costs a wasted round rather than two. */
		$list = sol_gateways();
		$preferred = trim((string)$preferred);
		if ($preferred !== '') array_unshift($list, $preferred);
		$out = array();
		foreach (array_unique($list) as $g) $out[] = $g . $cid;
		return $out;
	}
	if (preg_match('~^https?://~i', $uri)) return array($uri);
	return array();
}

/**
 * Pull the image out of a metadata document.
 *
 * The NAME IS NOT TAKEN FROM HERE. It is already on the asset account, read
 * from the chain itself, and the chain cannot 429. XRPL had to synthesise
 * "XRPL #<serial>" when a gateway failed, and then needed a whole repair
 * path to undo those rows later; this cannot produce a wrong name at all.
 */
function sol_parse_metadata($asset, $body) {
	$out = array('name' => $asset['name'], 'image' => '');
	if (!is_string($body) || $body === '') return $out;
	$meta = json_decode($body, true);
	if (!is_array($meta)) {
		/* Not JSON: some collections point the URI straight at the picture. */
		$out['image'] = sol_storable_image($asset['uri']);
		return $out;
	}
	$img = '';
	foreach (array('image', 'image_url', 'imageUrl', 'animation_url', 'animation') as $k) {
		if (!empty($meta[$k]) && is_string($meta[$k])) { $img = $meta[$k]; break; }
	}
	/* Metaplex also allows the real file under properties.files[]. Read it
	   only as a fallback: `image` is the canonical slot and files[0] is
	   occasionally a video next to a jpeg thumbnail. */
	if ($img === '' && !empty($meta['properties']['files'][0]['uri'])
	    && is_string($meta['properties']['files'][0]['uri']))
		$img = $meta['properties']['files'][0]['uri'];

	$out['image'] = sol_storable_image($img);
	return $out;
}

/* ---------- the network ---------------------------------------------------- */

/** The default fetcher. Replaced wholesale by the harness. */
function sol_http($url, $post) {
	$ch = curl_init($url);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_TIMEOUT, SOLANA_HTTP_TIMEOUT);
	if ($post !== null) {
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
	}
	$body = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	/* No curl_close(): no effect since PHP 8.0, deprecated from 8.5, and
	   display_errors is ON here. Same trap dhc-json.php documents. */
	if ($body === false || $code >= 400) return '';
	return $body;
}

/** Many GETs at once. Same reasoning as xrpl_http_many(): the wall clock has
    to be the slowest fetch, not the sum of them. */
function sol_http_many($urls, $timeout = null) {
	$out = array();
	if (!$urls) return $out;
	if ($timeout === null) $timeout = SOLANA_HTTP_TIMEOUT;

	$mh = curl_multi_init();
	foreach (array_chunk($urls, SOLANA_FETCH_CONCURRENCY, true) as $chunk) {
		$handles = array();
		foreach ($chunk as $key => $url) {
			$ch = curl_init($url);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
			curl_multi_add_handle($mh, $ch);
			$handles[$key] = $ch;
		}
		$running = null;
		do {
			curl_multi_exec($mh, $running);
			if ($running) curl_multi_select($mh, 1.0);
		} while ($running > 0);
		foreach ($handles as $key => $ch) {
			$body = curl_multi_getcontent($ch);
			$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
			$out[$key] = (!is_string($body) || $body === '' || $code >= 400) ? '' : $body;
			curl_multi_remove_handle($mh, $ch);
		}
	}
	curl_multi_close($mh);
	return $out;
}

/** Honour an injected fetcher; only the real one gets the parallel path. */
function sol_fetch_many($urls, $fetch, $fetch_many = null) {
	if (!$urls) return array();
	if ($fetch_many !== null)  return call_user_func($fetch_many, $urls);
	if ($fetch === 'sol_http') return sol_http_many($urls);
	$out = array();
	foreach ($urls as $k => $u) $out[$k] = call_user_func($fetch, $u, null);
	return $out;
}

/**
 * Metadata for a whole list of assets, one round per candidate URL.
 *
 * Round one asks each asset's first candidate; round two asks the second for
 * whatever round one could not answer. For an https collection there is only
 * ever one round. Keyed by position so the caller can zip it back.
 */
function sol_resolve_many($assets, $fetch, $fetch_many = null, $deadline = 0, $gateway = '') {
	$out = array(); $cands = array(); $most = 0;
	foreach ($assets as $i => $a) {
		$out[$i] = array('name' => $a['name'], 'image' => '');
		$c = sol_metadata_urls($a['uri'], $gateway);
		if ($c) { $cands[$i] = $c; $most = max($most, count($c)); }
	}
	if (!$cands) return $out;

	/* As many rounds as the longest candidate list, not a fixed count: with a
	   preferred gateway prepended there is one more than sol_gateways() has,
	   and hard-coding that length would silently never try the last one. */
	for ($r = 0; $r < max(1, $most); $r++) {
		if ($deadline && time() > $deadline) break;
		$batch = array();
		foreach ($cands as $i => $list) if (isset($list[$r])) $batch[$i] = $list[$r];
		if (!$batch) break;

		$bodies = sol_fetch_many($batch, $fetch, $fetch_many);
		foreach ($bodies as $i => $body) {
			if (!is_string($body) || $body === '') continue;
			$out[$i] = sol_parse_metadata($assets[$i], $body);
			unset($cands[$i]);                 // answered; no further rounds
		}
		if (!$cands) break;
	}
	return $out;
}

/* ---------- the pass ------------------------------------------------------- */

/**
 * CAN THIS SCHEMA HOLD A SOLANA ASSET ID?
 *
 * A Core asset id is a base58 pubkey, up to 44 characters. nfts.asset_id was
 * sized for a 44-character Cardano fingerprint and then widened to 64 for
 * XRPL, so this passes today — but it is checked anyway, for the reason the
 * XRPL version exists: MySQL outside strict mode TRUNCATES instead of
 * erroring, the row looks fine, the picture renders, and the link goes to an
 * asset that does not exist. Worse, the next pass compares a full id against
 * the truncated one, never matches, and inserts the whole collection again.
 *
 * SHOW COLUMNS, not information_schema — this database's cPanel MySQL user
 * cannot read information_schema at all, so a guard written against it
 * silently passes on the one server it exists to protect.
 */
function sol_check_schema($conn) {
	$need = array('asset_id' => 44, 'asset_name' => 44);
	$bad  = array();
	$res = @$conn->query("SHOW COLUMNS FROM nfts");
	if (!$res) return '';
	while ($row = $res->fetch_assoc()) {
		$col = isset($row['Field']) ? $row['Field'] : '';
		if (!isset($need[$col])) continue;
		if (!preg_match('/^\s*(?:var)?char\s*\(\s*(\d+)\s*\)/i',
		                isset($row['Type']) ? $row['Type'] : '', $m)) continue;   // TEXT: plenty
		if ((int)$m[1] < $need[$col])
			$bad[] = sprintf("nfts.%s holds %d chars, needs %d", $col, (int)$m[1], $need[$col]);
	}
	if (!$bad) return '';
	return 'Solana writing is blocked: ' . implode('; ', $bad)
	     . '. A Core asset id is up to 44 base58 characters and MySQL would'
	     . ' truncate it silently. See solana-schema.md.';
}

/**
 * Verify ONE user's Solana wallets, right now.
 *
 * A connect verifies immediately on both existing chains, and it has to here
 * too: somebody links Solflare, sees nothing, and concludes it did not work.
 * "It will appear tomorrow" is not a thing to tell somebody who just proved
 * they own something.
 *
 * Returns array(ok, staked, message).
 */
function sol_verify_user($conn, $user_id, $budget = 45) {
	try {
		$user_id = (int)$user_id;
		if ($user_id <= 0) return array('ok' => false, 'staked' => 0, 'message' => '');

		$schema = sol_check_schema($conn);
		if ($schema !== '') {
			error_log($schema);
			return array('ok' => false, 'staked' => 0,
				'message' => 'Wallet linked. Staking is not switched on for this '
				           . 'chain yet — nothing to do on your end.');
		}

		$collections = getCollectionIDs($conn, SOLANA_CHAIN_ID);
		if (!$collections)
			return array('ok' => true, 'staked' => 0,
				'message' => 'No Solana collections are registered yet.');

		/* This user's Solana addresses only. getAddresses() is Cardano-shaped
		   and unscoped, so it is not reused here. */
		$addresses = array();
		$res = $conn->query("SELECT stake_address FROM wallets
		                     WHERE user_id = $user_id AND blockchain_id = " . SOLANA_CHAIN_ID);
		if ($res) while ($r = $res->fetch_assoc()) $addresses[] = $r['stake_address'];
		if (!$addresses) return array('ok' => true, 'staked' => 0, 'message' => '');

		$out = verifyNFTsSolana($conn, $addresses, $collections,
			getNFTAssetIDs($conn, SOLANA_CHAIN_ID), array(), array(
				'api_base' => getChainSetting($conn, SOLANA_CHAIN_ID, 'api_base',
				                              'https://api.mainnet-beta.solana.com'),
				'gateway'  => getChainSetting($conn, SOLANA_CHAIN_ID, 'ipfs_gateway', ''),
				/* Short: somebody is watching a spinner. If the cluster is slow
				   the nightly pass picks it up, which beats a hung request. */
				'deadline' => time() + (int)$budget,
				'clear'    => function() use ($conn, $user_id) {
					removeUser($conn, $user_id, SOLANA_CHAIN_ID);
				},
			));

		if (!$out['ok'])
			return array('ok' => false, 'staked' => 0,
				'message' => 'Could not read Solana just now — your NFTs will be '
				           . 'picked up tonight.');

		return array('ok' => true, 'staked' => $out['wrote'], 'message' => '');
	} catch (Throwable $e) {
		error_log('sol_verify_user: ' . $e->getMessage());
		return array('ok' => false, 'staked' => 0,
			'message' => 'Wallet linked. Your NFTs will be counted tonight.');
	}
}

/**
 * The Solana phase, as the nightly job runs it. One function so the scheduled
 * path and the manual path cannot drift.
 *
 * NEVER THROWS. This runs inside the job that pays everybody, so a Solana
 * problem must cost Solana holders a night and cost nobody else anything.
 */
function sol_nightly($conn, $budget = 600) {
	try {
		if (!function_exists('getCollectionIDs') || !function_exists('getAllAddresses'))
			return 'solana: skipped (platform functions unavailable)';

		$schema = sol_check_schema($conn);
		if ($schema !== '') return 'solana: ' . $schema;

		$collections = getCollectionIDs($conn, SOLANA_CHAIN_ID);
		$addresses   = getAllAddresses($conn, SOLANA_CHAIN_ID);
		if (!$collections || !$addresses)
			return sprintf('solana: nothing to do (%d addresses, %d collections)',
				count($addresses), count($collections));

		$res = verifyNFTsSolana($conn, $addresses, $collections,
			getNFTAssetIDs($conn, SOLANA_CHAIN_ID), array(), array(
				'api_base' => getChainSetting($conn, SOLANA_CHAIN_ID, 'api_base',
				                              'https://api.mainnet-beta.solana.com'),
				'gateway'  => getChainSetting($conn, SOLANA_CHAIN_ID, 'ipfs_gateway', ''),
				'deadline' => time() + (int)$budget,
				'clear'    => function() use ($conn) { removeUsers($conn, SOLANA_CHAIN_ID); },
			));

		if (!$res['ok']) {
			$msg = 'solana: SKIPPED WRITE — could not read ' . implode(', ', $res['failed'])
			     . '. Yesterday\'s rows stand.';
			error_log($msg);
			return $msg;
		}
		return sprintf('solana: %d addresses read, %d NFTs staked across %d collections',
			$res['read'], $res['wrote'], count($collections));
	} catch (Throwable $e) {
		$msg = 'solana: FAILED — ' . $e->getMessage();
		error_log($msg);
		return $msg;
	}
}

/**
 * One Solana verification pass, READ FIRST AND WRITE SECOND.
 *
 * THE ORDER IS THE SAFETY, for the reason verify-xrpl.php spells out: the
 * Cardano pattern clears ownership up front and trusts the verifier to put it
 * all back, which is fine only when nothing can interrupt it. Here it can. A
 * cleared-then-partially-rebuilt table is not a smaller truth, it is a wrong
 * one, and the payout step reads it. So if ANY address fails, nothing is
 * cleared and nothing is written: yesterday's rows stand, which are correct
 * for everybody who has not traded since. A missed night is recoverable; a
 * night where half the holders read as having sold everything is paid and
 * gone.
 *
 * $clear is the caller's chain-scoped removeUsers(), injected so this function
 * owns the ordering rather than hoping the caller gets it right.
 *
 * Returns array(asset_ids, nft_owners, ok, failed, read, wrote, resolved, repaired).
 */
function verifyNFTsSolana($conn, $addresses, $collections, $asset_ids,
                          $nft_owners = array(), $opt = array()) {
	$api      = isset($opt['api_base']) ? $opt['api_base'] : 'https://api.mainnet-beta.solana.com';
	/* Only used by a collection whose metadata is on IPFS. OMEN's is not;
	   a later one's might be. */
	$fetch    = isset($opt['fetch'])    ? $opt['fetch']    : 'sol_http';
	$clear    = isset($opt['clear'])    ? $opt['clear']    : null;
	$deadline = isset($opt['deadline']) ? (int)$opt['deadline'] : 0;

	/* ---- phase one: read everything, write nothing ---- */
	$held = array(); $failed = array(); $read = 0;
	foreach ($addresses as $address) {
		$address = trim((string)$address);
		if ($address === '') continue;

		/* Running out of time is a failure like any other: it aborts the
		   write rather than writing what it managed. */
		if ($deadline && time() > $deadline) { $failed[] = $address . ' (out of time)'; continue; }

		$got = sol_account_assets($api, $address, $fetch);
		if (!$got['ok']) { $failed[] = $address; continue; }
		$held[$address] = $got['list'];
		$read++;
	}

	/* ---- the gate ---- */
	if ($failed) {
		return array('asset_ids' => $asset_ids, 'nft_owners' => $nft_owners,
			'ok' => false, 'failed' => $failed, 'read' => $read, 'wrote' => 0,
			'resolved' => 0, 'repaired' => 0);
	}

	/* ---- phase two: now it is safe to clear and rebuild ---- */
	if ($clear !== null) call_user_func($clear);

	/*
	 * METADATA IS FOR NEW NFTs ONLY. processNFT() uses the name and image
	 * only when it reaches createNFT(); for an asset it already knows it
	 * calls updateNFT(), which sets user_id and touches nothing else. XRPL
	 * was resolving every NFT every night to produce values it then threw
	 * away, and at 3,400 NFTs that was the difference between a pass that
	 * finished inside its budget and one that did not.
	 */
	$known = array_flip($asset_ids);

	/*
	 * EXCEPT ROWS THAT HAVE NO PICTURE.
	 *
	 * processNFT() writes name and image exactly once, so a document that
	 * could not be read on the night an NFT first appeared leaves a row with
	 * an empty ipfs forever, and the image cache cannot help because there is
	 * nothing stored to fetch. XRPL recognises those rows by its synthetic
	 * "XRPL #<serial>" name; here the name always comes from the chain and is
	 * always right, so the empty image IS the marker.
	 *
	 * The cost of being wrong is bounded and small: a collection whose pieces
	 * genuinely have no artwork re-reads up to 500 documents a night and
	 * writes nothing. The cost of not doing it is a permanently blank tile.
	 */
	$repair = array();
	if (is_object($conn)) {
		$r = @$conn->query("SELECT asset_id FROM nfts
		                     WHERE blockchain_id = " . SOLANA_CHAIN_ID . "
		                       AND (ipfs IS NULL OR ipfs = '')
		                     LIMIT 500");
		while ($r && $row = $r->fetch_assoc()) $repair[$row['asset_id']] = 1;
	}

	$queue = array();
	foreach ($held as $address => $list) {
		foreach ($list as $a) {
			if (!isset($collections[$a['collection']])) continue;   // not ours
			$queue[] = array(
				'asset'   => $a,
				'address' => $address,
				'need'    => !isset($known[$a['id']]) || isset($repair[$a['id']]),
				'fix'     => isset($repair[$a['id']]),
			);
		}
	}

	$wanted = array();
	foreach ($queue as $i => $q) if ($q['need']) $wanted[$i] = $q['asset'];
	$metas = $wanted
		? sol_resolve_many($wanted, $fetch,
			isset($opt['fetch_many']) ? $opt['fetch_many'] : null, $deadline,
			isset($opt['gateway']) ? $opt['gateway'] : '')
		: array();

	$wrote = 0; $repaired = 0;
	foreach ($queue as $i => $q) {
		$a = $q['asset']; $address = $q['address'];
		$meta = isset($metas[$i]) ? $metas[$i] : array('name' => $a['name'], 'image' => '');

		/* A repair: the row exists, so processNFT() below only touches
		   ownership. Write the recovered image here, and only if this attempt
		   actually got one — otherwise a second bad night overwrites nothing
		   with nothing and burns the write. */
		if ($q['fix'] && is_object($conn) && $meta['image'] !== ''
		    && function_exists('updateNFTMetadata')) {
			updateNFTMetadata($conn, $a['id'], $meta['name'], $meta['image']);
			$repaired++;
		}

		$payload = processNFT(
			$conn,
			$a['collection'],   // policy_id   -> collections lookup
			$a['id'],           // asset_name
			$meta['name'],      // name        -- from the chain, never empty
			$meta['image'],     // image       -> ipfs:// or https://, see sol_storable_image()
			$a['id'],           // fingerprint -> nfts.asset_id
			$address,
			$asset_ids, $nft_owners, $collections,
			/* Without this the row lands as Cardano and the Cardano pass
			   zeroes it the same night. See processNFT()'s header. */
			SOLANA_CHAIN_ID
		);
		if (is_array($payload)) {
			$asset_ids  = $payload['asset_ids'];
			$nft_owners = $payload['nft_owners'];
		}
		$wrote++;
	}

	return array('asset_ids' => $asset_ids, 'nft_owners' => $nft_owners,
		'ok' => true, 'failed' => array(), 'read' => $read, 'wrote' => $wrote,
		'resolved' => count($wanted), 'repaired' => $repaired);
}
