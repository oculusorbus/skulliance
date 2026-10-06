<?php
/**
 * verify-polygon.php — Polygon (EVM) ownership, for Danketsu.
 *
 * Included by verify.php. Defines functions and nothing else: no output,
 * no side effects at include time, no database work until it is called.
 *
 * WHY THIS ONE IS SHAPED DIFFERENTLY FROM THE OTHER THREE.
 *
 * Cardano, XRPL and Solana all answer the question "what does this wallet
 * hold". ERC-721 has no such call. The optional interface that would
 * provide one (ERC721Enumerable, 0x780e9d63) is not implemented by
 * Danketsu -- measured, not assumed; polygon-probe.php prints it -- and
 * the alternatives are both bad: scanning Transfer logs back to the
 * deploy block is far outside what a public node will serve, and an
 * indexer API means a paid key as a hard dependency.
 *
 * So the question is inverted. Read the COLLECTION's owner map -- one
 * Multicall3 call per POLYGON_CHUNK tokens, each one asking ownerOf --
 * and intersect it against the wallets this platform knows about.
 *
 * Measured on the live server: 4,444 tokens in 18 calls, 165-200ms each.
 * That is fast enough to run at connect time as well as nightly, which is
 * why there is no owner-map cache here. If a node starts rate-limiting,
 * a cache is the fix, and it goes in poly_owner_map() alone.
 *
 * The property this buys, which the per-wallet chains do not have: one
 * read answers for every user at once, and a row that should not exist
 * cannot survive a pass.
 *
 * WHAT IT NEVER DOES: sign, send a transaction, hold a key, or spend gas.
 * Every call is eth_call at `latest`. A wallet is linked by reporting its
 * address, exactly as on the other chains -- see createAddress().
 */

require_once __DIR__ . '/lib/ipfs-gateways.php';

if (!defined('POLYGON_CHAIN_ID')) define('POLYGON_CHAIN_ID', 4);

/* Multicall3. Same address on every chain it is deployed to, Polygon
   included -- it is deployed deterministically. */
define('POLYGON_MULTICALL', '0xca11bde05977b3631167028862be2a173976ca11');

/*
 * 250 ownerOf calls is 56KB of calldata and 40KB back, which LiteSpeed and
 * the public nodes both handle without complaint. Raising it trades round
 * trips for the risk of a node's own request-size limit, which is not
 * advertised and shows up as a bare 413 or a dropped connection.
 */
define('POLYGON_CHUNK', 250);
define('POLYGON_HTTP_TIMEOUT', 20);
define('POLYGON_FETCH_CONCURRENCY', 8);

/**
 * The nodes, best-first. blockchains.api_base goes in front of these at
 * the call site, for the reason that column exists: a node going bad is a
 * config change, not a deploy.
 *
 * polygon-rpc.com is deliberately absent. It now answers every request
 * with "API key disabled, reason: tenant disabled" and a -32051, which
 * looks like a node problem and is an account problem.
 */
function poly_nodes() {
	return array(
		'https://polygon-bor-rpc.publicnode.com',
		'https://polygon.drpc.org',
		'https://1rpc.io/matic',
	);
}

/**
 * THE SELECTORS, WRITTEN OUT.
 *
 * A selector is the first four bytes of keccak256(signature), and PHP
 * cannot compute that: hash('sha3-256') is FIPS-202, a different padding
 * from Ethereum's keccak256, and there is no keccak in core. Rather than
 * vendor a hasher for four constants, they are written out -- and every
 * one was proved against the live contract before being committed, which
 * is a stronger check than re-deriving them.
 *
 * The consequence to remember: EIP-55 checksum validation also needs
 * keccak256, so poly_valid_address() checks SHAPE only. A mistyped
 * address therefore reads as "holds nothing" rather than being rejected.
 * Visible, not silent, and the alternative is 200 lines of hasher to
 * catch a typo nobody types -- addresses arrive from MetaMask.
 */
function poly_sel($name) {
	$s = array(
		'ownerOf'     => '6352211e',   // ownerOf(uint256)
		'tokenURI'    => 'c87b56dd',   // tokenURI(uint256)
		'totalSupply' => '18160ddd',   // totalSupply()
		'aggregate3'  => '82ad56cb',   // aggregate3((address,bool,bytes)[])
	);
	return isset($s[$name]) ? $s[$name] : '';
}

/* ---------- addresses ------------------------------------------------------ */

/**
 * SHAPE ONLY, AND ALWAYS LOWER CASE.
 *
 * ownerOf returns a word with the address in the low 20 bytes, lower
 * case. MetaMask reports EIP-55 mixed case. If both sides are not folded
 * to one case, no wallet ever matches any token and the pass reports a
 * clean zero -- the single most likely day-one bug on this chain, and a
 * silent one, because nothing errors.
 */
function poly_valid_address($s) {
	return (bool)preg_match('/^0x[0-9a-fA-F]{40}$/', trim((string)$s));
}
function poly_norm_address($s) {
	$s = trim((string)$s);
	return poly_valid_address($s) ? strtolower($s) : '';
}

/**
 * nfts.asset_id for a Polygon token.
 *
 * Contract plus token id, because the token id alone is unique only
 * within its contract and asset_id is global. 47 characters at the
 * collection's size; poly_check_schema() verifies the column can hold it
 * rather than trusting that.
 */
function poly_asset_id($contract, $token_id) {
	return poly_norm_address($contract) . ':' . (string)(int)$token_id;
}

/* ---------- the smallest ABI codec that does this job ---------------------- */

function poly_word($hex) {
	$h = ltrim((string)$hex, '0');
	return str_pad(($h === '') ? '0' : $h, 64, '0', STR_PAD_LEFT);
}
function poly_uint($n)  { return poly_word(dechex((int)$n)); }
function poly_addr($a)  { return poly_word(substr(poly_norm_address($a), 2)); }

/**
 * aggregate3 takes (address target, bool allowFailure, bytes callData)[].
 *
 * A dynamic array of structs each containing dynamic bytes, which is the
 * fiddliest shape the ABI has: the head is one offset per struct, measured
 * from the START OF THE ARRAY BODY rather than from the start of the call.
 * Getting that base wrong produces a bare revert with no message, so it is
 * worth stating rather than discovering twice.
 *
 * allowFailure is TRUE on every call, deliberately. ownerOf REVERTS for a
 * burned token and this collection has one -- 4,443 of 4,444 ids have an
 * owner. With allowFailure false that single id fails the whole batch of
 * 250 and the chunk looks like a network problem.
 */
function poly_aggregate3($target, $calls) {
	$n = count($calls);
	$bodies = array();
	foreach ($calls as $cd) {
		$len = strlen($cd) / 2;
		$bodies[] = poly_addr($target) . poly_uint(1) . poly_uint(96) . poly_uint($len)
		          . str_pad($cd, (int)(ceil(strlen($cd) / 64) * 64), '0', STR_PAD_RIGHT);
	}
	$heads = ''; $cursor = $n * 32;
	foreach ($bodies as $b) { $heads .= poly_uint($cursor); $cursor += strlen($b) / 2; }
	return '0x' . poly_sel('aggregate3') . poly_uint(32) . poly_uint($n) . $heads . implode('', $bodies);
}

/** aggregate3's (bool success, bytes returnData)[], back out. Null if the
    response is not that shape -- a node error page, say. */
function poly_decode_aggregate3($hex) {
	$h = poly_strip($hex);
	if (strlen($h) < 128) return null;
	$n = hexdec(substr($h, 64, 64));
	if ($n < 0 || $n > 100000) return null;
	$out = array();
	for ($k = 0; $k < $n; $k++) {
		if (strlen($h) < 192 + $k * 64) return null;
		$o = hexdec(substr($h, 128 + $k * 64, 64)) * 2 + 128;
		if ($o + 128 > strlen($h)) return null;
		$ok = (int)hexdec(substr($h, $o, 64));
		$ro = hexdec(substr($h, $o + 64, 64)) * 2 + $o;
		if ($ro + 64 > strlen($h)) return null;
		$ln = hexdec(substr($h, $ro, 64));
		$out[] = array('ok' => $ok, 'data' => substr($h, $ro + 64, $ln * 2));
	}
	return $out;
}
function poly_strip($hex) {
	$h = (string)$hex;
	return (substr($h, 0, 2) === '0x') ? substr($h, 2) : $h;
}
function poly_decode_addr($hex) {
	$h = poly_strip($hex);
	return (strlen($h) < 64) ? '' : poly_norm_address('0x' . substr($h, 24, 40));
}
function poly_decode_string($hex) {
	$h = poly_strip($hex);
	if (strlen($h) < 128) return '';
	$n = hexdec(substr($h, 64, 64));
	if ($n <= 0 || 128 + $n * 2 > strlen($h)) return '';
	return pack('H*', substr($h, 128, $n * 2));
}

/* ---------- talking to a node ---------------------------------------------- */

/** The default fetcher. Replaced wholesale by the harness. */
function poly_http($url, $post) {
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_FOLLOWLOCATION => true,
		CURLOPT_TIMEOUT        => POLYGON_HTTP_TIMEOUT,
		CURLOPT_CONNECTTIMEOUT => 8,
		/* publicnode answers 403 to a request with no User-Agent, which
		   reads as the server's IP being blocked. It is not. */
		CURLOPT_USERAGENT      => 'Skulliance/1.0 (+https://skulliance.io)',
	));
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
	return (string)$body;
}

/** Many GETs at once -- the wall clock should be the slowest fetch, not
    the sum. Same shape as sol_http_many(). */
function poly_http_many($urls, $timeout = null) {
	$out = array();
	if (!$urls) return $out;
	if ($timeout === null) $timeout = POLYGON_HTTP_TIMEOUT;
	$mh = curl_multi_init();
	foreach (array_chunk($urls, POLYGON_FETCH_CONCURRENCY, true) as $chunk) {
		$handles = array();
		foreach ($chunk as $key => $url) {
			$ch = curl_init($url);
			curl_setopt_array($ch, array(
				CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
				CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 8,
				CURLOPT_USERAGENT => 'Skulliance/1.0 (+https://skulliance.io)',
			));
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
function poly_fetch_many($urls, $fetch, $fetch_many = null) {
	if (!$urls) return array();
	if ($fetch_many !== null)   return call_user_func($fetch_many, $urls);
	if ($fetch === 'poly_http') return poly_http_many($urls);
	$out = array();
	foreach ($urls as $k => $u) $out[$k] = call_user_func($fetch, $u, null);
	return $out;
}

/** One JSON-RPC call. Returns null on anything that is not a result. */
function poly_rpc($node, $method, $params, $fetch = 'poly_http') {
	$body = json_encode(array('jsonrpc' => '2.0', 'id' => 1,
	                          'method' => $method, 'params' => $params));
	$raw = call_user_func($fetch, $node, $body);
	if (!is_string($raw) || $raw === '') return null;
	$j = json_decode($raw, true);
	if (!is_array($j) || isset($j['error']) || !isset($j['result'])) return null;
	return $j['result'];
}
function poly_call($node, $to, $data, $fetch = 'poly_http') {
	return poly_rpc($node, 'eth_call', array(array('to' => $to, 'data' => $data), 'latest'), $fetch);
}

/**
 * The first node that answers with Polygon's chain id.
 *
 * Checked rather than assumed, because an RPC URL that has been repointed
 * at a testnet answers every call perfectly and reports that nobody owns
 * anything -- which this pass would then write.
 */
function poly_pick_node($preferred = '', $fetch = 'poly_http') {
	$list = poly_nodes();
	$preferred = trim((string)$preferred);
	if ($preferred !== '') array_unshift($list, $preferred);
	foreach (array_unique($list) as $node) {
		$id = poly_rpc($node, 'eth_chainId', array(), $fetch);
		if ($id !== null && hexdec(poly_strip($id)) === 137) return $node;
	}
	return '';
}

/* ---------- the owner map -------------------------------------------------- */

/**
 * Every token's owner, for one contract.
 *
 * Returns array(ok, owners, supply, failed). `owners` is token_id =>
 * address, lower case, missing for any id that reverted (burned).
 *
 * ok IS FALSE IF ANY CHUNK FAILED, and the caller must treat that as a
 * total failure. A partial owner map is not a smaller truth, it is a
 * wrong one: every holder in the missing range reads as having sold
 * everything, and the payout step believes it.
 */
function poly_owner_map($node, $contract, $fetch = 'poly_http', $deadline = 0, $supply = 0) {
	$contract = poly_norm_address($contract);
	if ($contract === '') return array('ok' => false, 'owners' => array(), 'supply' => 0,
	                                   'failed' => array('bad contract address'));
	if ($supply <= 0) {
		$ts = poly_call($node, $contract, '0x' . poly_sel('totalSupply'), $fetch);
		$supply = ($ts === null) ? 0 : (int)hexdec(poly_strip($ts));
	}
	if ($supply <= 0) return array('ok' => false, 'owners' => array(), 'supply' => 0,
	                               'failed' => array('totalSupply unreadable'));

	$owners = array(); $failed = array();
	for ($start = 1; $start <= $supply; $start += POLYGON_CHUNK) {
		if ($deadline && time() > $deadline) { $failed[] = "chunk at $start (out of time)"; break; }
		$ids = range($start, min($start + POLYGON_CHUNK - 1, $supply));
		$calls = array();
		foreach ($ids as $i) $calls[] = poly_sel('ownerOf') . poly_uint($i);
		$res = poly_call($node, POLYGON_MULTICALL, poly_aggregate3($contract, $calls), $fetch);
		$rows = ($res === null) ? null : poly_decode_aggregate3($res);
		if ($rows === null || count($rows) !== count($ids)) { $failed[] = "chunk at $start"; continue; }
		foreach ($rows as $k => $row) {
			if (!$row['ok']) continue;                       // burned
			$a = poly_decode_addr($row['data']);
			if ($a !== '') $owners[$ids[$k]] = $a;
		}
	}
	return array('ok' => !$failed, 'owners' => $owners, 'supply' => $supply, 'failed' => $failed);
}

/** tokenURI for a set of ids, batched the same way. Missing entries are
    tokens whose URI could not be read; the caller treats those as
    metadata-less rather than as a failure, because an unreadable URI on
    ONE token must not cost the other 4,443 their night. */
function poly_token_uris($node, $contract, $ids, $fetch = 'poly_http', $deadline = 0) {
	$out = array();
	$ids = array_values(array_unique(array_map('intval', $ids)));
	foreach (array_chunk($ids, POLYGON_CHUNK) as $chunk) {
		if ($deadline && time() > $deadline) break;
		$calls = array();
		foreach ($chunk as $i) $calls[] = poly_sel('tokenURI') . poly_uint($i);
		$res  = poly_call($node, POLYGON_MULTICALL, poly_aggregate3($contract, $calls), $fetch);
		$rows = ($res === null) ? null : poly_decode_aggregate3($res);
		if ($rows === null || count($rows) !== count($chunk)) continue;
		foreach ($rows as $k => $row) {
			if (!$row['ok']) continue;
			$u = poly_decode_string($row['data']);
			if ($u !== '') $out[$chunk[$k]] = $u;
		}
	}
	return $out;
}

/* ---------- metadata and the picture --------------------------------------- */

/**
 * LIFT THE CID BACK OUT OF A GATEWAY URL.
 *
 * This is the one piece of this chain's handling that is not generic, and
 * it exists because of what Danketsu's metadata actually contains. Its
 * `image` is not ipfs:// -- it is
 *
 *   https://bafybeihz55...la6i.ipfs.nftstorage.link/1.png?ext=png
 *
 * an absolute URL pointing at a specific public gateway, and that gateway
 * answers 302 then 429. Stored as-is it would be fetched as-is forever
 * and never resolve, because getIPFS() and lib/image-cache-lib.php both
 * treat an absolute URL as opaque and give it the single empty "gateway"
 * -- which is exactly right for Solana's OMEN, hosted on its project's
 * own domain, and exactly wrong here.
 *
 * The difference is whether the URL is somebody's SITE or somebody's
 * GATEWAY. A gateway URL names content that exists independently of it,
 * so the CID is the durable part and the host is the accident. Folding it
 * back to ipfs://CID/path lets the cache race every gateway for it, and
 * the one that currently works is reached without this file knowing which
 * one that is.
 *
 * Returns array(cid, path) or null. Both URL shapes are handled: the
 * subdomain form above, and the path form https://host/ipfs/<cid>/...
 */
function poly_gateway_cid($url) {
	$u = trim((string)$url);
	/* A CIDv1 base32 is 'b' + 58 lowercase base32 chars; a CIDv0 is 'Qm'
	   + 44 base58. Matching loosely on length would catch a hostname. */
	if (preg_match('~^https?://(b[a-z2-7]{58}|Qm[1-9A-HJ-NP-Za-km-z]{44})\.ipfs\.[^/]+/?(.*)$~i', $u, $m))
		return array('cid' => $m[1], 'path' => $m[2]);
	if (preg_match('~^https?://[^/]+/ipfs/(b[a-z2-7]{58}|Qm[1-9A-HJ-NP-Za-km-z]{44})/?(.*)$~i', $u, $m))
		return array('cid' => $m[1], 'path' => $m[2]);
	return null;
}

/** ipfs://CID, /ipfs/CID, a gateway URL and a bare CID all fold to
    ipfs://CID. Anything else is returned untouched. */
function poly_normalise_image($uri) {
	$uri = trim((string)$uri);
	if ($uri === '') return '';
	if (strpos($uri, 'ipfs://') === 0) {
		$rest = substr($uri, 7);
		if (strpos($rest, 'ipfs/') === 0) $rest = substr($rest, 5);
		return 'ipfs://' . $rest;
	}
	$g = poly_gateway_cid($uri);
	if ($g !== null) return 'ipfs://' . $g['cid'] . ($g['path'] !== '' ? '/' . $g['path'] : '');
	if (preg_match('~^(Qm[1-9A-HJ-NP-Za-km-z]{44}|b[a-z2-7]{58})~', $uri)) return 'ipfs://' . $uri;
	return $uri;
}

/**
 * WHAT MAY BE STORED IN nfts.ipfs.
 *
 * The column holds either a bare CID (optionally with a path) or a whole
 * absolute http(s) URL -- see sol_storable_image() for how it came to
 * hold both. Everything else is dropped rather than stored, because a
 * poisoned nfts.ipfs is unrecoverable in practice: it looks like data, it
 * fails inside a nightly worker, and nothing points at why.
 */
function poly_storable_image($img) {
	if ($img === '' || $img === null) return '';
	$norm = poly_normalise_image($img);
	if (strpos($norm, 'ipfs://') === 0) {
		$rest = substr($norm, 7);
		/* The cache treats anything under 46 characters as a malformed CID
		   and skips it, so a truncated one would be dropped there instead
		   of here, further from the cause. */
		$cid = explode('/', $rest);
		if (strlen($cid[0]) < 46) {
			error_log('verify-polygon: dropping short CID ' . substr($norm, 0, 80));
			return '';
		}
		/*
		 * THE 'ipfs://' STAYS ON. processNFT() does substr($image, 7) to
		 * strip it before writing nfts.ipfs, so what this function returns
		 * is pre-strip, not post-strip. Returning the bare CID here --
		 * which is what the column ends up holding, and so looks right --
		 * gets seven more characters taken off it and stores
		 * 'hz5555nb...' silently. sol_storable_image() has the same
		 * contract for the same reason.
		 */
		return 'ipfs://' . $rest;
	}
	if (preg_match('~^https?://~i', $norm)) return $norm;
	if (strpos($norm, 'data:image/svg+xml;base64') === 0) return $norm;
	return '';
}

/** The URLs worth trying for one token's metadata document, in order. */
function poly_metadata_urls($uri, $preferred = '') {
	$uri = poly_normalise_image($uri);
	if ($uri === '') return array();
	if (strpos($uri, 'ipfs://') === 0) {
		$rest = substr($uri, 7);
		$list = ipfs_gateways();
		$preferred = trim((string)$preferred);
		if ($preferred !== '') array_unshift($list, $preferred);
		$out = array();
		foreach (array_unique($list) as $g) $out[] = $g . $rest;
		return $out;
	}
	if (preg_match('~^https?://~i', $uri)) return array($uri);
	return array();
}

/**
 * Name and image out of one metadata document.
 *
 * NO FALLBACK NAME IS INVENTED HERE. An earlier version built one from
 * the array key it was handed -- and poly_resolve_many() keys by QUEUE
 * POSITION, not token id, so every unreadable document produced a name
 * numbered after its place in the batch. Returning an empty name makes
 * the caller, which is the only thing holding the real token id, supply
 * it. See poly_fallback_name().
 */
function poly_parse_metadata($body) {
	$none = array('name' => '', 'image' => '');
	if (!is_string($body) || $body === '') return $none;
	$j = json_decode($body, true);
	if (!is_array($j)) return $none;
	$name = isset($j['name']) ? trim((string)$j['name']) : '';
	$img = '';
	foreach (array('image', 'image_url', 'imageUrl') as $k)
		if (isset($j[$k]) && trim((string)$j[$k]) !== '') { $img = trim((string)$j[$k]); break; }
	return array('name' => $name, 'image' => poly_storable_image($img));
}

/** What a token is called when its document could not be read. Generic,
    not the collection's name: this file serves whatever Polygon
    collections exist, and XRPL's synthetic "XRPL #<serial>" is the
    precedent. */
function poly_fallback_name($token_id) { return 'Polygon #' . (int)$token_id; }

/**
 * Resolve many tokens' metadata, racing gateways per document.
 *
 * $uris is token_id => tokenURI. Returns token_id => array(name, image).
 * A document that cannot be read yields the fallback name and an empty
 * image, which is the marker the repair pass looks for -- NOT a failure
 * that aborts the write. One unreadable JSON must not cost every other
 * holder their night.
 */
function poly_resolve_many($uris, $fetch = 'poly_http', $fetch_many = null,
                           $deadline = 0, $gateway = '') {
	$out = array();
	if (!$uris) return $out;

	/* Round one: the first candidate for every token, all at once. Rounds
	   after that retry only what is still missing, against the next
	   gateway along -- so a collection whose first gateway is healthy
	   costs exactly one round. */
	$cands = array();
	foreach ($uris as $id => $uri) $cands[$id] = poly_metadata_urls($uri, $gateway);

	$max = 0;
	foreach ($cands as $list) $max = max($max, count($list));
	$pending = array_keys($uris);

	for ($round = 0; $round < $max && $pending; $round++) {
		if ($deadline && time() > $deadline) break;
		$batch = array();
		foreach ($pending as $id) if (isset($cands[$id][$round])) $batch[$id] = $cands[$id][$round];
		if (!$batch) continue;
		$bodies = poly_fetch_many($batch, $fetch, $fetch_many);
		$still  = array();
		foreach ($pending as $id) {
			$body = isset($bodies[$id]) ? $bodies[$id] : '';
			$meta = poly_parse_metadata($body);
			if ($meta['image'] !== '') { $out[$id] = $meta; continue; }
			$still[] = $id;
		}
		$pending = $still;
	}
	/* Whatever never resolved is simply absent. The caller fills it in,
	   because only the caller knows which token each key was. */
	return $out;
}

/* ---------- the pass ------------------------------------------------------- */

/**
 * CAN THIS SCHEMA HOLD A POLYGON ASSET ID?
 *
 * '0xee79...c5c4:4444' is 47 characters. nfts.asset_id is varchar(64), so
 * this passes today -- it is checked anyway for the reason
 * sol_check_schema() exists: MySQL outside strict mode TRUNCATES instead
 * of erroring, the row looks fine, the picture renders, and the next pass
 * compares a full id against the truncated one, never matches, and
 * inserts the whole collection again.
 *
 * SHOW COLUMNS, not information_schema -- this database's cPanel MySQL
 * user cannot read information_schema at all, so a guard written against
 * it silently passes on the one server it exists to protect.
 */
function poly_check_schema($conn) {
	$need = array('asset_id' => 47, 'asset_name' => 32);
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
	return 'Polygon writing is blocked: ' . implode('; ', $bad)
	     . '. An asset id is contract + token id and MySQL would truncate it'
	     . ' silently. See multichain.md.';
}

/**
 * Verify ONE user's Polygon wallets, right now.
 *
 * A connect verifies immediately on all three existing chains and has to
 * here too: somebody links MetaMask, sees nothing, and concludes it did
 * not work. "It will appear tomorrow" is not a thing to tell somebody who
 * just proved they own something.
 *
 * Affordable because the owner map is ~3.2s on this server. If that ever
 * stops being true, cache the map in poly_owner_map() -- not here.
 *
 * Returns array(ok, staked, message).
 */
function poly_verify_user($conn, $user_id, $budget = 45) {
	try {
		$user_id = (int)$user_id;
		if ($user_id <= 0) return array('ok' => false, 'staked' => 0, 'message' => '');

		$schema = poly_check_schema($conn);
		if ($schema !== '') {
			error_log($schema);
			return array('ok' => false, 'staked' => 0,
				'message' => 'Wallet linked. Staking is not switched on for this '
				           . 'chain yet - nothing to do on your end.');
		}

		$collections = getCollectionIDs($conn, POLYGON_CHAIN_ID);
		if (!$collections)
			return array('ok' => true, 'staked' => 0,
				'message' => 'No Polygon collections are registered yet.');

		/* This user's Polygon addresses only. getAddresses() is
		   Cardano-shaped and unscoped, so it is not reused here. */
		$addresses = array();
		$res = $conn->query("SELECT stake_address FROM wallets
		                     WHERE user_id = $user_id AND blockchain_id = " . POLYGON_CHAIN_ID);
		if ($res) while ($r = $res->fetch_assoc()) $addresses[] = $r['stake_address'];
		if (!$addresses) return array('ok' => true, 'staked' => 0, 'message' => '');

		$out = verifyNFTsPolygon($conn, $addresses, $collections,
			getNFTAssetIDs($conn, POLYGON_CHAIN_ID), array(), array(
				'api_base' => getChainSetting($conn, POLYGON_CHAIN_ID, 'api_base', ''),
				'gateway'  => getChainSetting($conn, POLYGON_CHAIN_ID, 'ipfs_gateway', ''),
				'deadline' => time() + (int)$budget,
				/* SCOPED TO THIS USER. The owner map covers everybody, but
				   clearing everybody's rows on one person's connect would
				   hand the whole platform's Polygon ownership to whatever
				   this single pass manages to rebuild. */
				'only'     => $addresses,
				'clear'    => function() use ($conn, $user_id) {
					removeUser($conn, $user_id, POLYGON_CHAIN_ID);
				},
			));

		if (!$out['ok'])
			return array('ok' => false, 'staked' => 0,
				'message' => 'Could not read Polygon just now - your NFTs will be '
				           . 'picked up tonight.');

		return array('ok' => true, 'staked' => $out['wrote'], 'message' => '');
	} catch (Throwable $e) {
		error_log('poly_verify_user: ' . $e->getMessage());
		return array('ok' => false, 'staked' => 0,
			'message' => 'Wallet linked. Your NFTs will be counted tonight.');
	}
}

/**
 * The Polygon phase, as the nightly job runs it. One function so the
 * scheduled path and the manual path cannot drift.
 *
 * NEVER THROWS. This runs inside the job that pays everybody, so a
 * Polygon problem must cost Polygon holders a night and cost nobody else
 * anything.
 */
function poly_nightly($conn, $budget = 600) {
	try {
		if (!function_exists('getCollectionIDs') || !function_exists('getAllAddresses'))
			return 'polygon: skipped (platform functions unavailable)';

		$schema = poly_check_schema($conn);
		if ($schema !== '') return 'polygon: ' . $schema;

		$collections = getCollectionIDs($conn, POLYGON_CHAIN_ID);
		$addresses   = getAllAddresses($conn, POLYGON_CHAIN_ID);
		if (!$collections || !$addresses)
			return sprintf('polygon: nothing to do (%d addresses, %d collections)',
				count($addresses), count($collections));

		$res = verifyNFTsPolygon($conn, $addresses, $collections,
			getNFTAssetIDs($conn, POLYGON_CHAIN_ID), array(), array(
				'api_base' => getChainSetting($conn, POLYGON_CHAIN_ID, 'api_base', ''),
				'gateway'  => getChainSetting($conn, POLYGON_CHAIN_ID, 'ipfs_gateway', ''),
				'deadline' => time() + (int)$budget,
				'clear'    => function() use ($conn) { removeUsers($conn, POLYGON_CHAIN_ID); },
			));

		if (!$res['ok']) {
			$msg = 'polygon: SKIPPED WRITE - could not read ' . implode(', ', $res['failed'])
			     . '. Yesterday\'s rows stand.';
			error_log($msg);
			return $msg;
		}
		return sprintf('polygon: %d tokens mapped, %d NFTs staked across %d collections',
			$res['read'], $res['wrote'], count($collections));
	} catch (Throwable $e) {
		$msg = 'polygon: FAILED - ' . $e->getMessage();
		error_log($msg);
		return $msg;
	}
}

/**
 * One Polygon verification pass, READ FIRST AND WRITE SECOND.
 *
 * THE ORDER IS THE SAFETY, for the reason verify-xrpl.php spells out: the
 * Cardano pattern clears ownership up front and trusts the verifier to
 * put it all back, which is fine only when nothing can interrupt it. Here
 * it can. A cleared-then-partially-rebuilt table is not a smaller truth,
 * it is a wrong one, and the payout step reads it. So if the owner map is
 * incomplete FOR ANY REASON, nothing is cleared and nothing is written:
 * yesterday's rows stand, which are correct for everybody who has not
 * traded since. A missed night is recoverable; a night where half the
 * holders read as having sold everything is paid and gone.
 *
 * This chain gets that guarantee more cheaply than the others. There is
 * one read, not one per address, so "did every address succeed" collapses
 * to "did every chunk succeed" -- and a chunk is 250 tokens, not one
 * person's holdings.
 *
 * $clear is the caller's chain-scoped removeUser(s), injected so this
 * function owns the ordering rather than hoping the caller gets it right.
 * $opt['only'] restricts the write to those addresses, for the
 * single-user connect path.
 *
 * Returns array(asset_ids, nft_owners, ok, failed, read, wrote, resolved, repaired).
 */
function verifyNFTsPolygon($conn, $addresses, $collections, $asset_ids,
                           $nft_owners = array(), $opt = array()) {
	$fetch    = isset($opt['fetch'])    ? $opt['fetch']    : 'poly_http';
	$clear    = isset($opt['clear'])    ? $opt['clear']    : null;
	$deadline = isset($opt['deadline']) ? (int)$opt['deadline'] : 0;
	$gateway  = isset($opt['gateway'])  ? $opt['gateway']  : '';

	/* Which addresses this pass may write for, folded to lower case --
	   see poly_norm_address() for why that fold is not optional. */
	$mine = array();
	$src  = isset($opt['only']) ? $opt['only'] : $addresses;
	foreach ((array)$src as $a) {
		$n = poly_norm_address($a);
		if ($n !== '') $mine[$n] = 1;
	}

	$empty = array('asset_ids' => $asset_ids, 'nft_owners' => $nft_owners,
		'ok' => false, 'failed' => array(), 'read' => 0, 'wrote' => 0,
		'resolved' => 0, 'repaired' => 0);

	$node = isset($opt['node']) ? $opt['node']
	      : poly_pick_node(isset($opt['api_base']) ? $opt['api_base'] : '', $fetch);
	if ($node === '') { $empty['failed'] = array('no Polygon node answered on chain 137'); return $empty; }

	/* ---- phase one: read everything, write nothing ---- */
	$held = array(); $failed = array(); $read = 0;
	foreach ($collections as $contract => $collection_id) {
		if (poly_norm_address($contract) === '') { $failed[] = "collection $collection_id: '$contract' is not an address"; continue; }
		$map = poly_owner_map($node, $contract, $fetch, $deadline);
		if (!$map['ok']) {
			foreach ($map['failed'] as $f) $failed[] = $contract . ': ' . $f;
			continue;
		}
		$read += count($map['owners']);
		foreach ($map['owners'] as $token_id => $owner) {
			if (!isset($mine[$owner])) continue;            // nobody here owns it
			$held[] = array('contract' => poly_norm_address($contract),
			                'token_id' => (int)$token_id, 'owner' => $owner);
		}
	}

	/* ---- the gate ---- */
	if ($failed) {
		$empty['failed'] = $failed; $empty['read'] = $read;
		return $empty;
	}

	/* ---- phase two: now it is safe to clear and rebuild ---- */
	if ($clear !== null) call_user_func($clear);

	/*
	 * METADATA IS FOR NEW NFTs ONLY. processNFT() uses the name and image
	 * only when it reaches createNFT(); for an asset it already knows it
	 * calls updateNFT(), which sets user_id and touches nothing else.
	 */
	$known = array_flip($asset_ids);

	/*
	 * EXCEPT ROWS THAT HAVE NO PICTURE. processNFT() writes name and image
	 * exactly once, so a document that could not be read on the night an
	 * NFT first appeared leaves an empty ipfs forever and the image cache
	 * cannot help -- there is nothing stored to fetch. An empty image is
	 * the marker, as on Solana. Bounded and cheap to be wrong about.
	 */
	$repair = array();
	if (is_object($conn)) {
		$r = @$conn->query("SELECT asset_id FROM nfts
		                     WHERE blockchain_id = " . POLYGON_CHAIN_ID . "
		                       AND (ipfs IS NULL OR ipfs = '')
		                     LIMIT 500");
		while ($r && $row = $r->fetch_assoc()) $repair[$row['asset_id']] = 1;
	}

	$queue = array();
	foreach ($held as $h) {
		$aid = poly_asset_id($h['contract'], $h['token_id']);
		$queue[] = array('h' => $h, 'asset_id' => $aid,
		                 'need' => !isset($known[$aid]) || isset($repair[$aid]),
		                 'fix'  => isset($repair[$aid]));
	}

	/* One tokenURI batch per contract, for the ids that need one. */
	$want_by_contract = array();
	foreach ($queue as $i => $q)
		if ($q['need']) $want_by_contract[$q['h']['contract']][$i] = $q['h']['token_id'];

	$metas = array();
	foreach ($want_by_contract as $contract => $by_index) {
		$uris = poly_token_uris($node, $contract, array_values($by_index), $fetch, $deadline);
		$per  = array();
		foreach ($by_index as $i => $tid) if (isset($uris[$tid])) $per[$i] = $uris[$tid];
		/* Keyed by QUEUE INDEX, not token id: two contracts can both have a
		   token 1, and merging on token id would give one of them the
		   other's artwork. */
		$resolved = poly_resolve_many($per, $fetch,
			isset($opt['fetch_many']) ? $opt['fetch_many'] : null, $deadline, $gateway);
		foreach ($by_index as $i => $tid)
			$metas[$i] = isset($resolved[$i]) ? $resolved[$i] : array('name' => '', 'image' => '');
	}

	$wrote = 0; $repaired = 0;
	foreach ($queue as $i => $q) {
		$h    = $q['h'];
		$meta = isset($metas[$i]) ? $metas[$i] : array('name' => '', 'image' => '');
		/* The real token id lives here and nowhere else in this loop. */
		if ($meta['name'] === '') $meta['name'] = poly_fallback_name($h['token_id']);

		if ($q['fix'] && is_object($conn) && $meta['image'] !== ''
		    && function_exists('updateNFTMetadata')) {
			updateNFTMetadata($conn, $q['asset_id'], $meta['name'], $meta['image']);
			$repaired++;
		}

		$payload = processNFT(
			$conn,
			$h['contract'],      // policy_id   -> collections lookup, the contract
			(string)$h['token_id'],  // asset_name
			$meta['name'],       // name
			$meta['image'],      // image       -> CID or absolute URL, see poly_storable_image()
			$q['asset_id'],      // fingerprint -> nfts.asset_id, contract:tokenId
			$h['owner'],
			$asset_ids, $nft_owners, $collections,
			/* Without this the row lands as Cardano and the Cardano pass
			   zeroes it the same night. See processNFT()'s header. */
			POLYGON_CHAIN_ID
		);
		if (is_array($payload)) {
			$asset_ids  = $payload['asset_ids'];
			$nft_owners = $payload['nft_owners'];
		}
		$wrote++;
	}

	return array('asset_ids' => $asset_ids, 'nft_owners' => $nft_owners,
		'ok' => true, 'failed' => array(), 'read' => $read, 'wrote' => $wrote,
		'resolved' => count($metas), 'repaired' => $repaired);
}
