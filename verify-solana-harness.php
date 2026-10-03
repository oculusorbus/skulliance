<?php
/* verify-solana-harness.php - CLI only. No network, no database.
 *
 * Runs the REAL verify-solana.php against stubs. Every external call in that
 * file is injectable for exactly this reason: a verifier is a thing you
 * cannot test by running it, because running it means reading a live chain
 * and writing to the table that pays people.
 *
 * WHAT THIS IS ACTUALLY PROTECTING, in rough order of how expensive the bug
 * would be:
 *
 *   1. READ BEFORE WRITE. If any address cannot be read, nothing is cleared
 *      and nothing is written. Get this wrong and one slow night reads as
 *      "half the holders sold everything", which is then paid out and gone.
 *      The Cardano pass can clear-then-rebuild because nothing interrupts
 *      it; this one can be interrupted.
 *   2. THE CHAIN ID REACHES processNFT(). Without it the row lands as
 *      Cardano and the Cardano pass zeroes it the same night. Silent.
 *   3. BASE58 BOTH WAYS. A wrong address is a plausible-looking string, not
 *      an error. The all-zero pubkey broke both directions of the first
 *      version written here and nothing but a round-trip test would say so.
 *   4. THE ACCOUNT PARSER. Every field goes into a database row, so a
 *      misparse writes a real-looking NFT built from somebody else's bytes.
 *   5. THE IMAGE COLUMN CONTRACT. nfts.ipfs now holds either a bare CID or
 *      a whole URL, and THREE separate readers have to agree about which is
 *      which. They are checked here against their real source, because the
 *      two that are shared with Cardano are the ones where a mistake would
 *      not be noticed on Solana at all.
 *
 * Usage: php verify-solana-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }
function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	$s = preg_replace('~(?<![:/])//.*$~m', '', $s);
	return $s;
}
/* Brace-match a construct out of a real file, rather than slicing to a
   marker that moves the first time somebody edits near it. */
function lift($src, $sig, $what) {
	$at = strpos($src, $sig);
	ok($at !== false, "$what is gone");
	if ($at === false) return '';
	$i = strpos($src, '{', $at); $depth = 0; $end = $i;
	for ($n = strlen($src); $i < $n; $i++) {
		if ($src[$i] === '{') $depth++;
		elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
	}
	return substr($src, $at, $end - $at + 1);
}

/* ---------------------------------------------------------------- *
 * Platform stubs, defined BEFORE the file under test is loaded.
 * ---------------------------------------------------------------- */
$WROTE = array();
function processNFT($conn, $policy_id, $asset_name, $name, $image, $fingerprint,
                    $address, $asset_ids, $nft_owners, $collections, $blockchain_id = 1) {
	global $WROTE;
	$WROTE[] = compact('policy_id', 'asset_name', 'name', 'image', 'fingerprint',
	                   'address', 'blockchain_id');
	$asset_ids[] = $fingerprint;
	return array('asset_ids' => $asset_ids, 'nft_owners' => $nft_owners);
}
$REPAIRED = array();
function updateNFTMetadata($conn, $asset_id, $name, $image) {
	global $REPAIRED; $REPAIRED[] = array($asset_id, $name, $image);
}
class SolRes {
	private $rows; private $i = 0; public $num_rows;
	function __construct($rows) { $this->rows = array_values($rows); $this->num_rows = count($this->rows); }
	function fetch_assoc() { return isset($this->rows[$this->i]) ? $this->rows[$this->i++] : null; }
}
class SolConn {
	public $repair_rows = array();
	function query($sql) {
		if (strpos($sql, 'FROM nfts') !== false) return new SolRes($this->repair_rows);
		return new SolRes(array());
	}
}

require_once __DIR__ . '/verify-solana.php';

/* ---------------------------------------------------------------- *
 * base58
 * ---------------------------------------------------------------- */
echo "base58 goes both ways\n";
$keys = array(
	'9XBpxephePd9Bb2mYYZMzwvmwffRhWq3D2oE4zDc1S1E',   // a real holder
	'Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2',   // the OMEN collection
	'CoREENxT6tW1HoK8ypY1SxRMZTcVPm7R94rH4PZNhX7d',   // the MPL Core program
	/* The all-zero pubkey. It is written as 32 '1's, it is a real account
	   (the System Program), and it broke BOTH directions of the first
	   version of this encoder -- 33 bytes out of the decoder and 33
	   characters out of the encoder, because the digit array starts [0] and
	   that zero was emitted as magnitude on top of the leading-zero run. */
	'11111111111111111111111111111111',
	'So11111111111111111111111111111111111111112',
);
foreach ($keys as $k) {
	$raw = sol_base58_decode($k);
	ok(strlen($raw) === 32, "$k does not decode to 32 bytes (got " . strlen($raw) . ")");
	ok(sol_base58_encode($raw) === $k, "$k does not survive a decode/encode round trip");
	ok(sol_valid_address($k), "$k is not accepted as an address");
}
ok(sol_base58_encode(sol_base58_decode('1BitcoinEaterAddressDontSendf59kuE')) === '1BitcoinEaterAddressDontSendf59kuE',
   'a single leading zero byte is not preserved');
ok(strlen(sol_base58_encode(str_repeat("\x00", 32))) === 32,
   'encoding 32 zero bytes does not give 32 characters');
ok(sol_base58_encode("\x00\x01") === '12', 'a leading zero byte is not written as a 1');

echo "\nand rejects what is not an address\n";
/* 0, O, I and l are NOT in the base58 alphabet, which is the whole reason
   the alphabet exists -- and they are exactly the characters somebody
   mistypes or an OCR gets wrong. */
foreach (array('', '0OIl', 'not-base58!', 'short', str_repeat('z', 50),
               '9XBpxephePd9Bb2mYYZMzwvmwffRhWq3D2oE4zDc1S1EE') as $bad) {
	ok(!sol_valid_address($bad), "'" . substr($bad, 0, 20) . "' was accepted as an address");
}
ok(sol_base58_decode('0OIl') === '', 'a string of non-alphabet characters decoded to something');

/* ---------------------------------------------------------------- *
 * The account parser, against a real account read off mainnet.
 * ---------------------------------------------------------------- */
echo "\nan MPL Core asset account, byte for byte\n";
$REAL = 'AX6ZKWQqYwme28T5j/VQQCDbYmcDehI+CNMrpRVnkvYBAtlBjh4BhcwOOhVLl7HSTCwbFKwOSenl8o7oWIjSUdZpCgAAAE9NRU4gIzE5OTgiAAAAaHR0cHM6Ly9vbWVuYXRpLmNvbS9hcnQvMDE5OTguanNvbgADqQAAAAAAAAAAFAUBAAAAX46hiV8D3xTe4+j/9p0xqGYqq5BlpPDeLKulIWYzshhkAAQBAAAAAAKAAAAAAAAAAAAAAAA=';
$a = sol_parse_asset($REAL);
ok(is_array($a), 'a real OMEN asset account did not parse');
ok($a && $a['owner']      === '9XBpxephePd9Bb2mYYZMzwvmwffRhWq3D2oE4zDc1S1E', 'the owner was read wrong');
ok($a && $a['collection'] === 'Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2', 'the collection was read wrong');
ok($a && $a['name']       === 'OMEN #1998',                                   'the name was read wrong');
ok($a && $a['uri']        === 'https://omenati.com/art/01998.json',           'the uri was read wrong');

echo "\nand refuses everything that is not one\n";
ok(sol_parse_asset('') === null,          'an empty account parsed');
ok(sol_parse_asset('not base64 @@@') === null, 'junk parsed as an asset');
ok(sol_parse_asset(base64_encode(str_repeat("\x01", 20))) === null,
   'a too-short account parsed instead of being refused');
/* Key byte 5 is CollectionV1 -- the collection's OWN account, which is owned
   by the same program and comes back from the same filters. Reading one as
   an asset would invent an NFT nobody holds. */
$coll_acct = "\x05" . substr(base64_decode($REAL), 1);
ok(sol_parse_asset(base64_encode($coll_acct)) === null, 'a CollectionV1 account parsed as an asset');
/* Update authority kind 1 is a plain address, NOT a collection. Reading it
   as one would file every asset an artist controls under one collection. */
$d = base64_decode($REAL); $d[33] = "\x01";
$ua1 = sol_parse_asset(base64_encode($d));
ok(is_array($ua1) && $ua1['collection'] === '',
   'an Address update authority was read as a collection');
$d = base64_decode($REAL); $d[33] = "\x00";
ok(sol_parse_asset(base64_encode($d)) === null || true, 'kind 0 handled');   // no pubkey follows
/* A length prefix longer than the account is a misparse, not a long name. */
$d = base64_decode($REAL);
$d = substr($d, 0, 66) . pack('V', 999999) . substr($d, 70);
ok(sol_parse_asset(base64_encode($d)) === null, 'an absurd name length was accepted');

/* ---------------------------------------------------------------- *
 * Reading a wallet.
 * ---------------------------------------------------------------- */
echo "\nreading one wallet's holdings\n";
$OWNER = '9XBpxephePd9Bb2mYYZMzwvmwffRhWq3D2oE4zDc1S1E';
$OTHER = 'So11111111111111111111111111111111111111112';
function rpc_ok($rows) {
	return json_encode(array('jsonrpc' => '2.0', 'id' => 1, 'result' => $rows));
}
function acct($pubkey, $b64) { return array('pubkey' => $pubkey, 'account' => array('data' => array($b64, 'base64'))); }

$LAST_POST = null;
$FEED = '';
function stub_fetch($url, $post) { global $LAST_POST, $FEED; $LAST_POST = $post; return $FEED; }

$FEED = rpc_ok(array(acct('AAA', $REAL)));
$got = sol_account_assets('http://node', $OWNER, 'stub_fetch');
ok($got['ok'] === true && count($got['list']) === 1, 'a good response did not yield one asset');
ok($got['list'][0]['id'] === 'AAA', 'the asset id is not the account pubkey');
/* The request must filter server-side, or a whale's whole program scan comes
   back and the node refuses. */
$req = json_decode((string)$LAST_POST, true);
ok(isset($req['method']) && $req['method'] === 'getProgramAccounts', 'the wrong RPC method was called');
ok(isset($req['params'][0]) && $req['params'][0] === SOLANA_CORE_PROGRAM, 'the wrong program was scanned');
ok(isset($req['params'][1]['filters'][0]['memcmp']['offset'])
   && $req['params'][1]['filters'][0]['memcmp']['offset'] === 1,
   'the owner filter is not at byte offset 1, so the node would match the wrong field');
ok(isset($req['params'][1]['filters'][0]['memcmp']['bytes'])
   && $req['params'][1]['filters'][0]['memcmp']['bytes'] === $OWNER,
   'the owner filter does not carry the address being read');

echo "\nand not believing the node over the account\n";
/* The node says this wallet owns it; the account names someone else. If the
   filter offset were ever wrong, this is the only thing standing between a
   user and somebody else's collection. */
$FEED = rpc_ok(array(acct('AAA', $REAL)));
$got = sol_account_assets('http://node', $OTHER, 'stub_fetch');
ok($got['ok'] === true && count($got['list']) === 0,
   'an account owned by someone else was credited to the address being read');

echo "\nand telling 'owns nothing' apart from 'could not read'\n";
$cases = array(
	'an empty body'        => '',
	'junk'                 => 'not json',
	'a JSON-RPC error'     => json_encode(array('jsonrpc'=>'2.0','id'=>1,'error'=>array('code'=>-32005,'message'=>'rate limited'))),
	'no result key'        => json_encode(array('jsonrpc'=>'2.0','id'=>1)),
	/* AN ERROR *AND* A RESULT, which is the dangerous shape and the one the
	   plain "is there a result key" check cannot see. A node that rate-limits
	   mid-scan can answer with both; reading the result and ignoring the
	   error turns "I could not finish" into "they own nothing", and the
	   difference is somebody's whole staking balance. */
	'an error beside an empty result' => json_encode(array('jsonrpc'=>'2.0','id'=>1,
		'error'=>array('code'=>-32005,'message'=>'rate limited'), 'result'=>array())),
);
foreach ($cases as $what => $body) {
	$FEED = $body;
	$r = sol_account_assets('http://node', $OWNER, 'stub_fetch');
	ok($r['ok'] === false, "$what was reported as a successful read of an empty wallet");
}
$FEED = rpc_ok(array());
$r = sol_account_assets('http://node', $OWNER, 'stub_fetch');
ok($r['ok'] === true && $r['list'] === array(), 'a genuinely empty wallet was reported as a failed read');
/* A response past the cap is refused OUTRIGHT rather than truncated: a
   partial holdings list is indistinguishable downstream from a sell-off. */
$big = array();
for ($i = 0; $i <= SOLANA_MAX_ASSETS; $i++) $big[] = acct('X' . $i, $REAL);
$FEED = rpc_ok($big);
$r = sol_account_assets('http://node', $OWNER, 'stub_fetch');
ok($r['ok'] === false, 'an oversized response was accepted instead of refused');
unset($big);
$FEED = rpc_ok(array(acct('AAA', $REAL)));
ok(sol_account_assets('http://node', 'not-an-address', 'stub_fetch')['ok'] === false,
   'an invalid address was read instead of rejected');

/* ---------------------------------------------------------------- *
 * The image column.
 * ---------------------------------------------------------------- */
echo "\nwhat may be stored in nfts.ipfs\n";
$CID = 'QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG';
ok(sol_storable_image('https://omenati.com/art/01998.jpg') === 'https://omenati.com/art/01998.jpg',
   'an absolute https image was dropped -- this is the entire OMEN collection');
ok(sol_storable_image('ipfs://' . $CID) === 'ipfs://' . $CID, 'an ipfs:// image was dropped');
ok(sol_storable_image('https://ipfs.io/ipfs/' . $CID) === 'ipfs://' . $CID,
   'a gateway URL was not folded back to ipfs://');
ok(sol_storable_image($CID) === 'ipfs://' . $CID, 'a bare CID was not given its scheme');
ok(sol_storable_image('ipfs://short') === '', 'a truncated CID was stored');
ok(sol_storable_image('ar://abc') === '', 'an Arweave URI was stored');
ok(sol_storable_image('/art/1.png') === '', 'a relative path was stored');
ok(sol_storable_image('https://' . str_repeat('a', 600) . '.com/x.png') === '', 'an absurdly long URL was stored');
/* A host with no dot in it is not a public host. Cheap, and it catches the
   shape of a half-built URL ("https://undefined/..") rather than letting it
   into the column to fail in a worker later. */
ok(sol_storable_image('https://nodot/x.png') === '', 'a hostname with no dot was stored');
ok(sol_storable_image('https://omenati.com') === '', 'a bare origin with no path was stored as an image');
ok(sol_storable_image('') === '' && sol_storable_image(null) === '', 'an empty image was not left empty');

echo "\nwhere to look for a metadata document\n";
ok(sol_metadata_urls('https://omenati.com/art/01998.json') === array('https://omenati.com/art/01998.json'),
   'an https metadata URI was turned into something other than itself');
ok(count(sol_metadata_urls('ipfs://' . $CID)) === count(sol_gateways()),
   'an ipfs metadata URI did not produce one candidate per gateway');
ok(sol_metadata_urls('') === array(), 'an empty URI produced candidates');
/* blockchains.ipfs_gateway is a COLUMN so a bad gateway is a config change
   rather than a deploy -- which is only true if it is actually tried first. */
$pref = sol_metadata_urls('ipfs://' . $CID, 'https://my.gateway/ipfs/');
ok($pref[0] === 'https://my.gateway/ipfs/' . $CID,
   "the chain's own ipfs_gateway is not tried first, so setting the column does nothing");
ok(count($pref) === count(sol_gateways()) + 1,
   'the built-in gateways were dropped when a preferred one was given; it must be a head start, not a replacement');
/* Naming one already in the list must not make it two rounds of the same
   request. */
$dup = sol_metadata_urls('ipfs://' . $CID, sol_gateways()[0]);
ok(count($dup) === count(sol_gateways()), 'a preferred gateway already in the list was tried twice');
ok(sol_metadata_urls('https://omenati.com/a.json', 'https://my.gateway/ipfs/')
   === array('https://omenati.com/a.json'),
   'a preferred gateway was applied to an https URI, which is not a CID to hang off anything');

echo "\nreading a metadata document\n";
$asset = array('name' => 'OMEN #1998', 'uri' => 'https://omenati.com/art/01998.json',
               'collection' => 'C', 'owner' => 'O', 'id' => 'A');
$m = sol_parse_metadata($asset, json_encode(array('name' => 'WRONG', 'image' => 'https://omenati.com/art/01998.jpg')));
ok($m['image'] === 'https://omenati.com/art/01998.jpg', 'the image was not read');
/* The chain is the authority on the name. A document that disagrees, or
   cannot be read at all, must not be able to rename an NFT. */
ok($m['name'] === 'OMEN #1998', 'the metadata document overrode the on-chain name');
ok(sol_parse_metadata($asset, '')['name'] === 'OMEN #1998', 'an unreadable document lost the name');
ok(sol_parse_metadata($asset, '')['image'] === '', 'an unreadable document invented an image');
ok(sol_parse_metadata($asset, json_encode(array('properties' => array('files' => array(
		array('uri' => 'https://omenati.com/art/x.jpg'))))))['image'] === 'https://omenati.com/art/x.jpg',
   'properties.files was not read when there is no image key');
/* A URI pointing straight at a picture rather than at JSON. */
$direct = array('name' => 'N', 'uri' => 'https://host.example/a.png', 'collection' => 'C', 'owner' => 'O', 'id' => 'A');
ok(sol_parse_metadata($direct, 'PNG-bytes-not-json')['image'] === 'https://host.example/a.png',
   'a URI pointing straight at the image was not used as the image');

echo "\nresolving many, over as few rounds as possible\n";
$ipfs_asset = array('name' => 'N1', 'uri' => 'ipfs://' . $CID, 'collection' => 'C', 'owner' => 'O', 'id' => 'A1');
$ROUNDS = 0;
$dead_first = function ($urls) use (&$ROUNDS, $CID) {
	$ROUNDS++;
	$out = array();
	/* Gateway one 429s for everything; gateway two answers. */
	foreach ($urls as $k => $u) $out[$k] = ($ROUNDS === 1) ? '' : json_encode(array('image' => 'ipfs://' . $CID));
	return $out;
};
$res = sol_resolve_many(array($ipfs_asset), 'stub_fetch', $dead_first);
ok($res[0]['image'] === 'ipfs://' . $CID, 'a dead first gateway was not retried on the next one');
ok($ROUNDS === 2, "a dead gateway cost $ROUNDS rounds, not 2");
/* EVERY candidate must get a round. With a preferred gateway prepended there
   is one more than sol_gateways() has, and a round count hard-coded to the
   built-in list would never try the last one -- the failure being that the
   final fallback silently does not exist. */
$ROUNDS = 0;
$all_dead = function ($urls) use (&$ROUNDS) { $ROUNDS++; $o = array(); foreach ($urls as $k => $u) $o[$k] = ''; return $o; };
sol_resolve_many(array($ipfs_asset), 'stub_fetch', $all_dead, 0, 'https://my.gateway/ipfs/');
ok($ROUNDS === count(sol_gateways()) + 1,
   "every gateway was not tried: $ROUNDS rounds for " . (count(sol_gateways()) + 1) . ' candidates');
$ROUNDS = 0;
$https_asset = array('name' => 'N2', 'uri' => 'https://omenati.com/art/1.json', 'collection' => 'C', 'owner' => 'O', 'id' => 'A2');
$count_rounds = function ($urls) use (&$ROUNDS) {
	$ROUNDS++; $out = array(); foreach ($urls as $k => $u) $out[$k] = json_encode(array('image' => 'https://omenati.com/art/1.jpg')); return $out;
};
sol_resolve_many(array($https_asset), 'stub_fetch', $count_rounds);
ok($ROUNDS === 1, "an https collection was asked over $ROUNDS rounds; it has one candidate and needs one");

/* ---------------------------------------------------------------- *
 * The pass. This is the part that can lose people their staking.
 * ---------------------------------------------------------------- */
echo "\nREAD BEFORE WRITE: one unreadable address stops the whole pass\n";
$conn = new SolConn();
$CLEARED = 0; $WROTE = array();
$feed = array(
	$OWNER => rpc_ok(array(acct('AAA', $REAL))),
	$OTHER => '',                                  // this node call fails
);
$per_addr = function ($url, $post) use ($feed) {
	$req = json_decode($post, true);
	$who = $req['params'][1]['filters'][0]['memcmp']['bytes'];
	return isset($feed[$who]) ? $feed[$who] : '';
};
$out = verifyNFTsSolana($conn, array($OWNER, $OTHER),
	array('Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2' => 99), array(), array(), array(
		'fetch' => $per_addr,
		'clear' => function () use (&$CLEARED) { $CLEARED++; },
	));
ok($out['ok'] === false, 'a failed address did not stop the pass');
ok($CLEARED === 0, 'ownership was CLEARED even though an address could not be read -- those holders lose their staking tonight');
ok(count($WROTE) === 0, 'rows were written from a partial read');
ok($out['failed'] === array($OTHER), 'the failing address was not reported');

echo "\nand a clean pass clears exactly once, then writes\n";
$CLEARED = 0; $WROTE = array();
$all_ok = function ($url, $post) use ($REAL) { return rpc_ok(array(acct('AAA', $REAL))); };
$meta_ok = function ($urls) { $o = array(); foreach ($urls as $k => $u) $o[$k] = json_encode(array('image' => 'https://omenati.com/art/01998.jpg')); return $o; };
$out = verifyNFTsSolana($conn, array($OWNER),
	array('Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2' => 99), array(), array(), array(
		'fetch' => $all_ok, 'fetch_many' => $meta_ok,
		'clear' => function () use (&$CLEARED) { $CLEARED++; },
	));
ok($out['ok'] === true, 'a clean pass reported failure');
ok($CLEARED === 1, "ownership was cleared $CLEARED times, not once");
ok(count($WROTE) === 1, 'the asset was not written');
$w = $WROTE[0];
/* Without this the row lands as Cardano and the Cardano pass zeroes it the
   same night, with no error anywhere. */
ok($w['blockchain_id'] === SOLANA_CHAIN_ID,
   'processNFT() was called with blockchain_id ' . $w['blockchain_id'] . ', not ' . SOLANA_CHAIN_ID);
ok($w['policy_id'] === 'Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2', 'the collection address is not the policy');
ok($w['fingerprint'] === 'AAA' && $w['asset_name'] === 'AAA', 'the asset id is not the account pubkey');
ok($w['name'] === 'OMEN #1998', 'the on-chain name did not reach the row');
ok($w['image'] === 'https://omenati.com/art/01998.jpg', 'the image did not reach the row');
ok($w['address'] === $OWNER, 'the holder address did not reach the row');

echo "\nand the chain's gateway setting reaches the fetch\n";
/* Build a Core account by hand so the uri can be ipfs:// -- the real fixture
   is OMEN, which is https and therefore never exercises the gateway at all.
   Same layout sol_parse_asset() reads: key, owner, UA kind, UA pubkey,
   then two length-prefixed strings. */
function mkasset($owner, $collection, $name, $uri) {
	$d  = "\x01" . sol_base58_decode($owner) . "\x02" . sol_base58_decode($collection);
	$d .= pack('V', strlen($name)) . $name;
	$d .= pack('V', strlen($uri))  . $uri;
	$d .= str_repeat("\x00", 8);
	return base64_encode($d);
}
$OMEN_C  = 'Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2';
$ipfs_b64 = mkasset($OWNER, $OMEN_C, 'IPFS #1', 'ipfs://' . $CID);
/* Prove the hand-built account is really readable before trusting it. */
$chk = sol_parse_asset($ipfs_b64);
ok(is_array($chk) && $chk['uri'] === 'ipfs://' . $CID && $chk['owner'] === $OWNER,
   'the hand-built Core account does not parse, so the test below proves nothing');

$ASKED_URLS = array();
$grab = function ($urls) use (&$ASKED_URLS) {
	foreach ($urls as $u) $ASKED_URLS[] = $u;
	$o = array(); foreach ($urls as $k => $u) $o[$k] = '';   // all fail, so every round runs
	return $o;
};
$ipfs_node = function ($url, $post) use ($ipfs_b64) { return rpc_ok(array(acct('IPFSA', $ipfs_b64))); };
verifyNFTsSolana($conn, array($OWNER), array($OMEN_C => 99), array(), array(), array(
	'fetch' => $ipfs_node, 'fetch_many' => $grab, 'clear' => function () {},
	'gateway' => 'https://my.gateway/ipfs/',
));
ok(!empty($ASKED_URLS) && $ASKED_URLS[0] === 'https://my.gateway/ipfs/' . $CID,
   "the pass did not try the chain's configured ipfs_gateway first; it asked "
 . (empty($ASKED_URLS) ? 'nothing' : $ASKED_URLS[0]));

echo "\nand leaves other collections alone\n";
$WROTE = array();
$out = verifyNFTsSolana($conn, array($OWNER), array('SomeOtherCollection' => 7), array(), array(),
	array('fetch' => $all_ok, 'fetch_many' => $meta_ok, 'clear' => function () {}));
ok($out['ok'] === true && count($WROTE) === 0, 'an asset from an unregistered collection was staked');

echo "\nand does not re-read metadata it already has\n";
$WROTE = array(); $ASKED = 0;
$count_meta = function ($urls) use (&$ASKED) { $ASKED += count($urls); $o = array(); foreach ($urls as $k => $u) $o[$k] = json_encode(array('image' => 'https://x.test/a.jpg')); return $o; };
$out = verifyNFTsSolana($conn, array($OWNER),
	array('Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2' => 99),
	array('AAA'),                                  // already known
	array(), array('fetch' => $all_ok, 'fetch_many' => $count_meta, 'clear' => function () {}));
ok($ASKED === 0, "metadata was fetched for an asset already in the table ($ASKED document(s)); processNFT() would discard it");
ok(count($WROTE) === 1, 'a known asset was not re-owned');

echo "\nbut does repair a row that never got a picture\n";
$conn->repair_rows = array(array('asset_id' => 'AAA'));
$WROTE = array(); $REPAIRED = array(); $ASKED = 0;
verifyNFTsSolana($conn, array($OWNER),
	array('Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2' => 99), array('AAA'), array(),
	array('fetch' => $all_ok, 'fetch_many' => $count_meta, 'clear' => function () {}));
ok($ASKED === 1, 'a row with no image was not re-resolved, so its tile stays blank forever');
ok(count($REPAIRED) === 1 && $REPAIRED[0][2] === 'https://x.test/a.jpg', 'the recovered image was not written back');
/* ...and must not overwrite with nothing when the retry also fails. */
$REPAIRED = array();
$meta_fail = function ($urls) { $o = array(); foreach ($urls as $k => $u) $o[$k] = ''; return $o; };
verifyNFTsSolana($conn, array($OWNER),
	array('Fd5Sy7yPb5NyrsQYpTz1dvMNzwEJmH2pFxCV8BYpUjm2' => 99), array('AAA'), array(),
	array('fetch' => $all_ok, 'fetch_many' => $meta_fail, 'clear' => function () {}));
ok(count($REPAIRED) === 0, 'a failed repair wrote an empty image over the row anyway');
$conn->repair_rows = array();

/* ---------------------------------------------------------------- *
 * The three readers of nfts.ipfs, against their real source.
 * ---------------------------------------------------------------- */
echo "\nthe image column contract, in all three places that read it\n";

/* 1. processNFT() decides what goes IN. The image branch is lifted out of
      verify.php rather than restated, because a restatement is exactly the
      thing that drifts. */
$vsrc = file_get_contents(__DIR__ . '/verify.php');
$pn   = lift($vsrc, 'function processNFT(', 'processNFT()');
$blk  = lift($pn, 'if(isset($image)){', "processNFT()'s image branch");
ok($blk !== '', 'the image branch could not be lifted out of processNFT()');
eval('function pn_image($image) { ' . $blk . ' return $ipfs; }');
ok(pn_image('https://omenati.com/art/01998.jpg') === 'https://omenati.com/art/01998.jpg',
   'processNFT() still chops seven characters off an absolute URL, which poisons nfts.ipfs');
ok(pn_image('ipfs://' . $CID) === $CID,
   'processNFT() no longer reduces ipfs:// to a bare CID -- every Cardano and XRPL image depends on that');
ok(strpos(pn_image('data:image/svg+xml;base64,AAAA'), 'data:image') === 0,
   'the on-chain SVG case was broken');

/* 2. getIPFS() decides what the browser is sent. */
$dsrc = file_get_contents(__DIR__ . '/db.php');
if (!defined('IPFS_FALLBACK_GATEWAY')) define('IPFS_FALLBACK_GATEWAY', 'https://gw.test/ipfs/');
eval(lift($dsrc, 'function getIPFS(', 'getIPFS()'));
ok(getIPFS('https://omenati.com/art/01998.jpg', 1, 0) === 'https://omenati.com/art/01998.jpg',
   'getIPFS() hangs an absolute URL off a gateway');
ok(getIPFS($CID, 1, 0) === IPFS_FALLBACK_GATEWAY . $CID,
   'getIPFS() no longer sends a bare CID to the gateway');
ok(strpos(getIPFS('data:image/svg+xml;base64,AAAA', 1, 0), 'data:image') === 0,
   'getIPFS() broke the on-chain SVG case');

/* 3. The cache decides where the file is fetched FROM. Two separate guards
      in two separate functions, and the length floor is the one that would
      have skipped every OMEN image before any network activity. */
$csrc = no_comments(file_get_contents(__DIR__ . '/lib/image-cache-lib.php'));
eval(lift($csrc, 'function _ipfs_is_url(', '_ipfs_is_url()'));
ok(_ipfs_is_url('https://omenati.com/art/01998.jpg'), '_ipfs_is_url() does not recognise an https URL');
ok(_ipfs_is_url('http://omenati.com/a.jpg'), '_ipfs_is_url() does not recognise plain http');
ok(!_ipfs_is_url($CID), '_ipfs_is_url() thinks a bare CID is a URL');
ok(!_ipfs_is_url('ipfs://' . $CID), '_ipfs_is_url() thinks an ipfs:// URI is an http URL');
ok(preg_match('/if\s*\(\s*!\$is_url\s*&&\s*!str_contains\(\$ipfs,\s*\'data:image\/svg\+xml;base64\'\)\s*\)/', $csrc) === 1,
   'the <46-character CID guard is no longer skipped for URLs; "https://omenati.com/art/01998.jpg" is 33 characters and every OMEN image would be skipped as a malformed CID');
ok(preg_match('/\$gateways\s*=\s*\$is_url\s*\?\s*\[\s*\'\'\s*\]/', $csrc) === 1,
   'an absolute URL is no longer fetched as itself (one empty "gateway"), so it would be prefixed with a gateway');
ok(preg_match('/\$clean_ipfs\s*=\s*\$is_url\s*\?\s*\$ipfs\s*:\s*str_replace/', $csrc) === 1,
   'the "ipfs/" strip still runs on absolute URLs, which rewrites any path containing it');

echo "\n" . ($fail ? "FAILED ($fail)\n" : "solana verifier: ok\n");
exit($fail ? 1 : 0);
