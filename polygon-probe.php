<?php
/**
 * polygon-probe.php — can THIS server read Danketsu's ownership off Polygon?
 *
 *   php polygon-probe.php                                   (the CLI answer)
 *   php polygon-probe.php sweep                             (+ the whole collection)
 *   https://skulliance.io/staking/polygon-probe.php         (the one that matters)
 *   https://skulliance.io/staking/polygon-probe.php?sweep=1
 *
 * RUN IT BOTH WAYS, AND BELIEVE THE SECOND. The shell php is a different
 * build from the one Apache runs, and this platform has paid for that
 * lesson three times now -- ext/sodium, mysqlnd, and ffmpeg.
 *
 * WHAT IT DECIDES. Polygon ownership cannot be read the way the other
 * three chains are read. Cardano, XRPL and Solana all answer "what does
 * this wallet hold"; ERC-721 has no such call, and Danketsu does not
 * implement the optional interface that would fake it (verified below:
 * supportsInterface(0x780e9d63) is false). So the plan is to invert it --
 * read the COLLECTION's owner map with one Multicall3 call per 250 tokens
 * and intersect that against linked wallets.
 *
 * That plan rests on three things this server has never been asked to do:
 *   1. reach a public Polygon JSON-RPC node at all;
 *   2. POST a ~112KB request body (250 batched calls) and get 40KB back;
 *   3. decode ABI output in PHP.
 * If any of those fails from the web SAPI the design changes, so it is
 * proved here before a line of verify-polygon.php gets written.
 *
 * READ-ONLY. Every call is eth_call at `latest`. It signs nothing, sends
 * no transaction, holds no key, and writes nothing to the database.
 */

$cli = (PHP_SAPI === 'cli');
if (!$cli) {
	/* Over the web this reports server configuration, so it is admin
	   only -- and 404, not 403, like the rest of the panel. */
	include __DIR__ . '/db.php';
	include __DIR__ . '/skulliance.php';
	require_once __DIR__ . '/admin-lib.php';
	if (!adminIsSuper()) { http_response_code(404); header('Content-Type: text/plain'); echo "Not found.\n"; exit; }
	header('Content-Type: text/plain; charset=utf-8');
}
$sweep = $cli ? in_array('sweep', $argv, true) : !empty($_GET['sweep']);

function say($s = '') { echo $s . "\n"; if (PHP_SAPI !== 'cli') @flush(); }
function ms($t) { return number_format((microtime(true) - $t) * 1000, 0) . 'ms'; }

/* The collection, and the contract the user gave. Lower case throughout:
   ownerOf returns lower case and MetaMask reports EIP-55 mixed case, so
   one of the two has to be normalised and it may as well be both. */
define('PP_CONTRACT',  '0xee79a3e8aef1109a6ee82bf399ce9e1bd43cf5c4');   // Danketsu
define('PP_PROJECT_ID', 10);
/* Multicall3, same address on every chain it is deployed to. */
define('PP_MULTICALL', '0xca11bde05977b3631167028862be2a173976ca11');
define('PP_CHUNK', 250);

/*
 * THE SELECTORS, WRITTEN OUT.
 *
 * A selector is the first four bytes of keccak256(signature) -- and PHP
 * CANNOT COMPUTE THAT. hash('sha3-256') is the FIPS-202 function, which
 * is a different padding from Ethereum's keccak256 and gives a different
 * digest; there is no keccak in core. Rather than vendor a hasher for
 * seven constants, they are written out here. Every one of these was
 * proved against the live contract before being committed, which is a
 * stronger check than re-deriving them would be.
 *
 * The consequence to remember: EIP-55 checksum validation ALSO needs
 * keccak256, so addresses are validated by shape and lower-cased, not
 * checksummed. A mistyped address then reads as "holds nothing" rather
 * than being rejected -- visible, not silent.
 */
$PP_SEL = array(
	'name'              => '06fdde03',   // name()
	'symbol'            => '95d89b41',   // symbol()
	'totalSupply'       => '18160ddd',   // totalSupply()
	'supportsInterface' => '01ffc9a7',   // supportsInterface(bytes4)
	'ownerOf'           => '6352211e',   // ownerOf(uint256)
	'tokenURI'          => 'c87b56dd',   // tokenURI(uint256)
	'aggregate3'        => '82ad56cb',   // aggregate3((address,bool,bytes)[])
);

/* ---------- the smallest ABI encoder that does this job ------------------- */

function pp_word($hex) { return str_pad(ltrim((string)$hex, '0') === '' ? '0' : ltrim((string)$hex, '0'), 64, '0', STR_PAD_LEFT); }
function pp_uint($n)   { return pp_word(dechex((int)$n)); }
function pp_addr($a)   { return pp_word(strtolower(substr((string)$a, 0, 2) === '0x' ? substr($a, 2) : $a)); }

/*
 * aggregate3 takes (address target, bool allowFailure, bytes callData)[].
 * A dynamic array of structs that each contain dynamic bytes, which is
 * the fiddliest shape in the ABI: the head is one offset per struct,
 * measured from the START OF THE ARRAY BODY, not from the start of the
 * call. Getting that base wrong is the classic mistake and the node
 * answers with a bare revert, so it is worth stating.
 *
 * allowFailure is TRUE on every call on purpose: ownerOf REVERTS for a
 * burned token, and Danketsu has one (4,443 of 4,444 ids have an owner).
 * With allowFailure false the single bad id fails the whole batch of 250.
 */
function pp_aggregate3($target, $calls) {
	global $PP_SEL;
	$n = count($calls); $bodies = array();
	foreach ($calls as $cd) {
		$len = strlen($cd) / 2;
		$bodies[] = pp_addr($target) . pp_uint(1) . pp_uint(96) . pp_uint($len)
		          . str_pad($cd, (int)(ceil(strlen($cd) / 64) * 64), '0', STR_PAD_RIGHT);
	}
	$heads = ''; $cursor = $n * 32;
	foreach ($bodies as $b) { $heads .= pp_uint($cursor); $cursor += strlen($b) / 2; }
	return '0x' . $PP_SEL['aggregate3'] . pp_uint(32) . pp_uint($n) . $heads . implode('', $bodies);
}

/* Unpack aggregate3's (bool success, bytes returnData)[] back out. */
function pp_decode_aggregate3($hex) {
	$h = (substr($hex, 0, 2) === '0x') ? substr($hex, 2) : $hex;
	if (strlen($h) < 128) return null;
	$n = hexdec(substr($h, 64, 64)); $out = array();
	for ($k = 0; $k < $n; $k++) {
		$o  = hexdec(substr($h, 128 + $k * 64, 64)) * 2 + 128;
		$ok = (int)hexdec(substr($h, $o, 64));
		$ro = hexdec(substr($h, $o + 64, 64)) * 2 + $o;
		$ln = hexdec(substr($h, $ro, 64));
		$out[] = array('ok' => $ok, 'data' => substr($h, $ro + 64, $ln * 2));
	}
	return $out;
}

/* An ABI string: offset, length, bytes. */
function pp_decode_string($hex) {
	$h = (substr($hex, 0, 2) === '0x') ? substr($hex, 2) : $hex;
	if (strlen($h) < 128) return null;
	$n = hexdec(substr($h, 64, 64));
	return pack('H*', substr($h, 128, $n * 2));
}
function pp_decode_addr($hex) {
	$h = (substr($hex, 0, 2) === '0x') ? substr($hex, 2) : $hex;
	return (strlen($h) < 64) ? null : '0x' . substr($h, 24, 40);
}

/* ---------- the node ------------------------------------------------------- */

$PP_RPCS = array(
	'https://polygon-bor-rpc.publicnode.com',
	'https://1rpc.io/matic',
	'https://polygon.drpc.org',
	/* polygon-rpc.com is left out deliberately: it now answers
	   "API key disabled, tenant disabled" with a -32051. */
);

function pp_rpc($url, $method, $params, &$err = null) {
	$body = json_encode(array('jsonrpc' => '2.0', 'id' => 1, 'method' => $method, 'params' => $params));
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_POST           => true,
		CURLOPT_POSTFIELDS     => $body,
		CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
		CURLOPT_RETURNTRANSFER => true,
		CURLOPT_TIMEOUT        => 45,
		CURLOPT_CONNECTTIMEOUT => 10,
		/* publicnode 403s a request with no User-Agent, which looked like
		   a block on this server's IP until it was sent one. */
		CURLOPT_USERAGENT      => 'Skulliance/1.0 (+https://skulliance.io)',
	));
	$raw  = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$cerr = curl_error($ch);
	if ($raw === false) { $err = 'curl: ' . $cerr; return null; }
	if ($code !== 200)  { $err = 'HTTP ' . $code . ': ' . substr((string)$raw, 0, 160); return null; }
	$j = json_decode($raw, true);
	if (!is_array($j))            { $err = 'not JSON: ' . substr((string)$raw, 0, 160); return null; }
	if (isset($j['error']))       { $err = 'rpc: ' . json_encode($j['error']); return null; }
	if (!isset($j['result']))     { $err = 'no result'; return null; }
	return $j['result'];
}
function pp_call($url, $to, $data, &$err = null) {
	return pp_rpc($url, 'eth_call', array(array('to' => $to, 'data' => $data), 'latest'), $err);
}

/* ---------- report --------------------------------------------------------- */

say('SAPI                : ' . PHP_SAPI . ($cli ? '   <- not the one that matters' : '   <- this is the one that matters'));
say('PHP                 : ' . PHP_VERSION);
say('curl                : ' . (function_exists('curl_init')
	? 'yes (' . (string)@curl_version()['version'] . ')' : 'NOT AVAILABLE -- nothing below can work'));
say('contract            : ' . PP_CONTRACT . '  (Danketsu, project ' . PP_PROJECT_ID . ')');
say();

say('-- reaching a Polygon node ------------------------------------------');
$RPC = null;
foreach ($PP_RPCS as $url) {
	$t = microtime(true); $err = null;
	$r = pp_rpc($url, 'eth_chainId', array(), $err);
	if ($r !== null && hexdec($r) === 137) { say(sprintf('  %-42s ok    %s  chainId 137', $url, ms($t))); if (!$RPC) $RPC = $url; }
	else                                    say(sprintf('  %-42s FAIL  %s', $url, (string)$err));
}
if (!$RPC) { say(); say('No Polygon node is reachable from this server. Nothing else can be measured.'); exit(1); }
say('  using               : ' . $RPC);
say();

say('-- what the contract is --------------------------------------------');
$t = microtime(true);
$nm = pp_call($RPC, PP_CONTRACT, '0x' . $PP_SEL['name']);
$sy = pp_call($RPC, PP_CONTRACT, '0x' . $PP_SEL['symbol']);
$ts = pp_call($RPC, PP_CONTRACT, '0x' . $PP_SEL['totalSupply']);
say('  name()              : ' . var_export(pp_decode_string((string)$nm), true));
say('  symbol()            : ' . var_export(pp_decode_string((string)$sy), true));
$supply = ($ts === null) ? 0 : (int)hexdec($ts);
say('  totalSupply()       : ' . number_format($supply));
foreach (array('ERC721' => '80ac58cd', 'ERC721Metadata' => '5b5e139f',
               'ERC721Enumerable' => '780e9d63', 'ERC1155' => 'd9b67a26') as $label => $iface) {
	$r = pp_call($RPC, PP_CONTRACT, '0x' . $PP_SEL['supportsInterface'] . str_pad($iface, 64, '0', STR_PAD_RIGHT));
	$yes = ($r !== null && (int)hexdec($r) === 1);
	say(sprintf('  supports %-18s: %s%s', $label, $yes ? 'yes' : 'no',
		($label === 'ERC721Enumerable' && !$yes) ? '   <- why the owner map is read per TOKEN, not per wallet' : ''));
}
say('  (' . ms($t) . ' for six eth_calls)');
say();

say('-- one Multicall3 batch of ' . PP_CHUNK . ' ownerOf calls -------------------');
$ids = range(1, PP_CHUNK);
$calls = array();
foreach ($ids as $i) $calls[] = $PP_SEL['ownerOf'] . pp_uint($i);
$data = pp_aggregate3(PP_CONTRACT, $calls);
say('  request body        : ' . number_format(strlen($data) / 2) . ' bytes of calldata');
$t = microtime(true); $err = null;
$res = pp_call($RPC, PP_MULTICALL, $data, $err);
if ($res === null) {
	say('  FAILED              : ' . (string)$err);
	say();
	say('  This is the call the whole design rests on. If it fails only over the web,');
	say('  suspect a request-size or outbound-POST limit rather than the node.');
	exit(1);
}
say('  response            : ' . number_format(strlen($res) / 2) . ' bytes   ' . ms($t));
$rows = pp_decode_aggregate3($res);
$owners = array(); $reverted = 0;
foreach ($rows as $k => $row) {
	if (!$row['ok']) { $reverted++; continue; }
	$owners[$ids[$k]] = pp_decode_addr($row['data']);
}
say('  decoded             : ' . count($rows) . ' results, ' . count($owners) . ' owned, ' . $reverted . ' reverted');
say('  token 1 owner       : ' . (isset($owners[1]) ? $owners[1] : '(none)'));
say('  token ' . PP_CHUNK . ' owner     : ' . (isset($owners[PP_CHUNK]) ? $owners[PP_CHUNK] : '(none)'));
say();

if ($sweep) {
	say('-- the whole collection --------------------------------------------');
	$all = array(); $fail = 0; $calls_made = 0; $t = microtime(true);
	for ($start = 1; $start <= $supply; $start += PP_CHUNK) {
		$ids = range($start, min($start + PP_CHUNK - 1, $supply));
		$cd = array();
		foreach ($ids as $i) $cd[] = $PP_SEL['ownerOf'] . pp_uint($i);
		$err = null;
		$res = pp_call($RPC, PP_MULTICALL, pp_aggregate3(PP_CONTRACT, $cd), $err);
		$calls_made++;
		if ($res === null) { $fail++; say('  chunk at ' . $start . ' FAILED: ' . (string)$err); continue; }
		foreach ((array)pp_decode_aggregate3($res) as $k => $row)
			if ($row['ok']) $all[$ids[$k]] = pp_decode_addr($row['data']);
	}
	$el = microtime(true) - $t;
	$holders = array_count_values($all);
	arsort($holders);
	say('  rpc calls           : ' . $calls_made . ' (' . PP_CHUNK . ' tokens each)' . ($fail ? ", $fail FAILED" : ''));
	say('  wall clock          : ' . number_format($el, 1) . 's');
	say('  tokens with owner   : ' . number_format(count($all)) . ' / ' . number_format($supply));
	say('  distinct holders    : ' . number_format(count($holders)));
	$top = array_slice($holders, 0, 3, true);
	foreach ($top as $addr => $cnt) say('    ' . $addr . '  x' . $cnt);
	say();
	/* WHY THIS SHAPE IS WORTH IT: one sweep answers for every user at
	   once, and a row that should not exist cannot survive it. The cost
	   scales with COLLECTION size, not holder count -- fine nightly,
	   too slow to run while somebody watches a connect spinner, so the
	   map wants caching with the nightly refreshing it. */
	say('  -> at this speed a nightly sweep is affordable; a connect-time one is not.');
	say();
}

say('-- metadata and the picture ----------------------------------------');
$u = pp_call($RPC, PP_CONTRACT, '0x' . $PP_SEL['tokenURI'] . pp_uint(1));
$turi = ($u === null) ? '' : (string)pp_decode_string($u);
say('  tokenURI(1)         : ' . $turi);

/*
 * THE TRAP THIS SECTION EXISTS FOR.
 *
 * Danketsu's metadata does not point at ipfs:// for the picture. The
 * `image` field is an absolute https URL on a GATEWAY -- and that
 * gateway (nftstorage.link) answers 302 then 429. The same CID through a
 * working gateway returns the png.
 *
 * getIPFS() and _ipfs_is_url() currently treat an absolute URL as opaque
 * and fetch it as-is, which is exactly right for Omen and exactly wrong
 * here. What is needed is to recognise a gateway-SHAPED url, lift the CID
 * out of it, and re-resolve through our own list. pp_gateway_cid() below
 * is the sketch of that.
 */
function pp_gateway_cid($url) {
	$u = (string)$url;
	/* https://<cid>.ipfs.<host>/<path> */
	if (preg_match('~^https?://([a-z0-9]{46,})\.ipfs\.[^/]+/?(.*)$~i', $u, $m))
		return array('cid' => strtolower($m[1]), 'path' => $m[2]);
	/* https://<host>/ipfs/<cid>/<path> */
	if (preg_match('~^https?://[^/]+/ipfs/([A-Za-z0-9]{46,})/?(.*)$~', $u, $m))
		return array('cid' => $m[1], 'path' => $m[2]);
	return null;
}

/*
 * MEASURED 2026-10-05, probing Danketsu's own CID. filebase was the ONLY
 * one of these that served it: ipfs.io, dweb.link and w3s.link have all
 * switched to a service-worker-only gateway and answer 429 or an HTML
 * notice instead of the file, nftstorage.link (which is where Danketsu's
 * metadata POINTS) answers 302 then 429, and 4everland/storry/ipfs.cyou
 * answer 301/403/nothing. filebase also rate-limits under rapid repeats,
 * so this list is ordered best-first and a miss is not a verdict.
 *
 * The platform's own lists -- lib/image-cache-lib.php:172 and db.php:3471
 * -- still lead with ipfs.io, so every first-warm of a new image pays a
 * dead gateway's timeout before it gets anywhere.
 */
$GATES = array('https://ipfs.filebase.io/ipfs/', 'https://gateway.pinata.cloud/ipfs/',
               'https://ipfs.io/ipfs/', 'https://dweb.link/ipfs/', 'https://w3s.link/ipfs/');

function pp_fetch($url, &$code = null, &$ctype = null, $head = false) {
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 3,
		CURLOPT_TIMEOUT => 30, CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_NOBODY => (bool)$head,
		CURLOPT_USERAGENT => 'Skulliance/1.0 (+https://skulliance.io)',
	));
	$b = curl_exec($ch);
	$code  = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$ctype = (string)curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
	return ($b === false) ? '' : (string)$b;
}

$meta = null;
if (strpos($turi, 'ipfs://') === 0) {
	$rest = substr($turi, 7);
	say('  resolving           : ipfs://' . $rest);
	foreach ($GATES as $g) {
		$code = 0; $ct = '';
		$body = pp_fetch($g . $rest, $code, $ct);
		$j = json_decode($body, true);
		$good = ($code === 200 && is_array($j));
		say(sprintf('    %-34s %s', $g, $good ? 'ok' : ('no (HTTP ' . $code . ', ' . substr($ct, 0, 24) . ')')));
		if ($good && !$meta) $meta = $j;
	}
}
if (is_array($meta)) {
	say('  name                : ' . (isset($meta['name']) ? $meta['name'] : '(none)'));
	$img = isset($meta['image']) ? (string)$meta['image'] : '';
	say('  image               : ' . $img);
	$code = 0; $ct = '';
	pp_fetch($img, $code, $ct, true);
	say('  image as published  : HTTP ' . $code . ' ' . $ct . ($code === 200 ? '' : '   <- cannot be fetched as-is'));
	$cid = pp_gateway_cid($img);
	if ($cid) {
		say('  CID lifted out      : ' . $cid['cid'] . '  path ' . var_export($cid['path'], true));
		foreach ($GATES as $g) {
			$code = 0; $ct = '';
			pp_fetch($g . $cid['cid'] . '/' . $cid['path'], $code, $ct, true);
			say(sprintf('    %-34s HTTP %d %s', $g, $code, substr($ct, 0, 24)));
		}
	} else {
		say('  CID lifted out      : (not a gateway-shaped URL -- fetch it as-is)');
	}
}
say();

say('-- can the schema hold it ------------------------------------------');
/*
 * A Polygon NFT needs a globally unique asset_id, and the only thing that
 * is unique is contract + token id: '0xee79...c5c4:4444' is 47 characters.
 * Checked rather than assumed for the reason sol_check_schema() exists --
 * MySQL outside strict mode TRUNCATES instead of erroring, the row looks
 * fine, the picture renders, and the next pass compares a full id against
 * the truncated one, never matches, and inserts the collection again.
 */
if (!$cli && isset($conn) && $conn) {
	$need = array('asset_id' => 47, 'asset_name' => 32);
	$res = @$conn->query("SHOW COLUMNS FROM nfts");
	if (!$res) { say('  SHOW COLUMNS failed.'); }
	else while ($row = $res->fetch_assoc()) {
		$col = isset($row['Field']) ? $row['Field'] : '';
		if (!isset($need[$col])) continue;
		$type = isset($row['Type']) ? $row['Type'] : '';
		$w = preg_match('/^\s*(?:var)?char\s*\(\s*(\d+)\s*\)/i', $type, $m) ? (int)$m[1] : 0;
		say(sprintf('  nfts.%-14s : %-12s needs %d  %s', $col, $type, $need[$col],
			($w === 0 ? 'ok (not a fixed char column)' : ($w >= $need[$col] ? 'ok' : 'TOO SHORT'))));
	}
} else {
	say('  (run this over the web -- the CLI has no database handle here)');
}
say();
say('done. Nothing was written.');
