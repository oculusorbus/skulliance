<?php
/* verify-polygon-harness.php — CLI only. Exercises verify-polygon.php with
 * no database and no network.
 *
 * THE FIXTURES ARE REAL BYTES. The aggregate3 request and response below
 * were captured from polygon-bor-rpc.publicnode.com against the live
 * Danketsu contract, asking ownerOf for tokens 1, 2 and 4445 -- the last
 * of which does not exist and therefore REVERTS, which is the case the
 * whole allowFailure design exists for and the one a hand-written fixture
 * would never think to include.
 *
 * The golden request matters as much as the response: it was produced by
 * a SEPARATE implementation (the Python in polygon-probe.php's development)
 * and accepted by a real node. Asserting PHP's encoder reproduces it
 * byte for byte is the only check here that is not the encoder agreeing
 * with itself.
 *
 * Usage: php verify-polygon-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

define('POLYGON_CHAIN_ID', 4);
require __DIR__ . '/verify-polygon.php';

$CONTRACT = '0xEe79a3e8Aef1109a6EE82bf399cE9e1BD43Cf5c4';          // EIP-55 mixed case, as MetaMask reports it
$OWNER1   = '0x98569abd448950932e0396de3074a03b89de2319';
$OWNER2   = '0x682ae3484fe1ad40a575df4cbff019e0b7624e3b';

/*
 * THE GOLDEN REQUEST CHANGED ONCE, and the reason is worth keeping.
 * The first capture came from a Python encoder whose `ljust(64)` was a
 * no-op on a 72-character string, so it sent dynamic bytes UNPADDED --
 * 36 bytes where the ABI says pad to a 32-byte multiple. The node
 * accepted it anyway. PHP pads properly, the node accepts that too, and
 * both return byte-identical results; this golden is the spec-correct
 * one, re-captured from the node. A fixture being accepted is not the
 * same as it being right.
 */
$REQ_1_2_4445 = '0x82ad56cb'
 . '0000000000000000000000000000000000000000000000000000000000000020'
 . '0000000000000000000000000000000000000000000000000000000000000003'
 . '0000000000000000000000000000000000000000000000000000000000000060'
 . '0000000000000000000000000000000000000000000000000000000000000120'
 . '00000000000000000000000000000000000000000000000000000000000001e0'
 . '000000000000000000000000ee79a3e8aef1109a6ee82bf399ce9e1bd43cf5c4'
 . '0000000000000000000000000000000000000000000000000000000000000001'
 . '0000000000000000000000000000000000000000000000000000000000000060'
 . '0000000000000000000000000000000000000000000000000000000000000024'
 . '6352211e00000000000000000000000000000000000000000000000000000000'
 . '0000000100000000000000000000000000000000000000000000000000000000'
 . '000000000000000000000000ee79a3e8aef1109a6ee82bf399ce9e1bd43cf5c4'
 . '0000000000000000000000000000000000000000000000000000000000000001'
 . '0000000000000000000000000000000000000000000000000000000000000060'
 . '0000000000000000000000000000000000000000000000000000000000000024'
 . '6352211e00000000000000000000000000000000000000000000000000000000'
 . '0000000200000000000000000000000000000000000000000000000000000000'
 . '000000000000000000000000ee79a3e8aef1109a6ee82bf399ce9e1bd43cf5c4'
 . '0000000000000000000000000000000000000000000000000000000000000001'
 . '0000000000000000000000000000000000000000000000000000000000000060'
 . '0000000000000000000000000000000000000000000000000000000000000024'
 . '6352211e00000000000000000000000000000000000000000000000000000000'
 . '0000115d00000000000000000000000000000000000000000000000000000000';

$RES_1_2_4445 = '0x'
 . '0000000000000000000000000000000000000000000000000000000000000020'
 . '0000000000000000000000000000000000000000000000000000000000000003'
 . '0000000000000000000000000000000000000000000000000000000000000060'
 . '00000000000000000000000000000000000000000000000000000000000000e0'
 . '0000000000000000000000000000000000000000000000000000000000000160'
 . '0000000000000000000000000000000000000000000000000000000000000001'
 . '0000000000000000000000000000000000000000000000000000000000000040'
 . '0000000000000000000000000000000000000000000000000000000000000020'
 . '00000000000000000000000098569abd448950932e0396de3074a03b89de2319'
 . '0000000000000000000000000000000000000000000000000000000000000001'
 . '0000000000000000000000000000000000000000000000000000000000000040'
 . '0000000000000000000000000000000000000000000000000000000000000020'
 . '000000000000000000000000682ae3484fe1ad40a575df4cbff019e0b7624e3b'
 . '0000000000000000000000000000000000000000000000000000000000000000'
 . '0000000000000000000000000000000000000000000000000000000000000040'
 . '0000000000000000000000000000000000000000000000000000000000000004'
 . 'df2d9b4200000000000000000000000000000000000000000000000000000000';

echo "the encoder produces what a real node accepted\n";

$calls = array();
foreach (array(1, 2, 4445) as $i) $calls[] = poly_sel('ownerOf') . poly_uint($i);
$built = poly_aggregate3($CONTRACT, $calls);
ok($built === $REQ_1_2_4445, 'the aggregate3 encoding no longer matches the bytes a node accepted');
/* The two mistakes that produce a bare revert with no message, called out
   so a failure above says WHICH. */
ok(substr($built, 2, 8) === '82ad56cb', 'the aggregate3 selector is wrong');
/* Second struct at 0x120: 96 for the three head words, plus one 192-byte
   struct body (address, bool, offset, length, 64 bytes of padded calldata).
   Measured from the array body, NOT from the start of the call -- getting
   that base wrong is a bare revert with no message. */
ok(strpos($built, '0000000000000000000000000000000000000000000000000000000000000120') !== false,
   'the struct offsets are not measured from the start of the ARRAY BODY');
/* Contract case: the encoder must fold it, or every call targets nothing. */
ok(strpos($built, 'ee79a3e8aef1109a6ee82bf399ce9e1bd43cf5c4') !== false
   && strpos($built, 'Ee79a3e8Aef1109a6EE82bf399cE9e1BD43Cf5c4') === false,
   'a mixed-case contract address reached the calldata unfolded');

echo "\nand decodes what it sent back, reverts included\n";

$rows = poly_decode_aggregate3($RES_1_2_4445);
ok(is_array($rows) && count($rows) === 3, 'the real response no longer decodes to three results');
ok($rows[0]['ok'] === 1 && poly_decode_addr($rows[0]['data']) === $OWNER1, 'token 1 owner is wrong');
ok($rows[1]['ok'] === 1 && poly_decode_addr($rows[1]['data']) === $OWNER2, 'token 2 owner is wrong');
/* THE ONE THAT MATTERS. Token 4445 does not exist, so ownerOf reverts.
   allowFailure is what keeps that from failing the other 249 in a real
   chunk, and a revert must read as "no owner", never as an address. */
ok($rows[2]['ok'] === 0, 'a reverting token is being read as a success');
/* Four bytes, not a padded word: the decoder must honour the declared
   length. df2d9b42 is ERC721NonexistentToken. */
ok($rows[2]['data'] === 'df2d9b42', 'the revert payload is not being preserved verbatim');

/* Garbage must come back null rather than half-decoded. A node error page
   reaching the decoder is a real case. */
/* A short word must decode to nothing rather than to a plausible-looking
   address built out of whatever bytes were there. poly_owner_map() then
   refuses to store it -- that guard is defensive and the fake node cannot
   produce the case, so this is where it is tested. */
ok(poly_decode_addr('0x' . str_repeat('0', 62)) === '', 'a truncated word decodes to an address');
ok(poly_decode_addr('') === '', 'empty data decodes to an address');
ok(poly_decode_string('0x') === '', 'a short string decodes to something');

ok(poly_decode_aggregate3('0x') === null, 'empty data decodes to something');
ok(poly_decode_aggregate3('0x' . str_repeat('ff', 64)) === null,
   'a nonsense length is trusted instead of refused');

echo "\naddresses are folded, both ways, always\n";

ok(poly_norm_address($CONTRACT) === strtolower($CONTRACT), 'an EIP-55 address is not lower-cased');
ok(poly_norm_address('  ' . $OWNER1 . '  ') === $OWNER1, 'whitespace is not trimmed');
ok(poly_norm_address('0x123') === '', 'a short address is accepted');
ok(poly_norm_address('0xZZ79a3e8aef1109a6ee82bf399ce9e1bd43cf5c4') === '', 'a non-hex address is accepted');
ok(poly_valid_address($CONTRACT), 'a mixed-case address fails validation');

echo "\nasset ids are global, and fit\n";

$aid = poly_asset_id($CONTRACT, 4444);
ok($aid === strtolower($CONTRACT) . ':4444', 'the asset id shape changed');
ok(strlen($aid) <= 47, 'the asset id is ' . strlen($aid) . ' chars; poly_check_schema() promises 47');
ok(poly_asset_id($CONTRACT, 1) !== poly_asset_id('0x1111111111111111111111111111111111111111', 1),
   'two contracts token 1 collide -- asset_id is not global');

echo "\nthe picture: a gateway URL is not a website\n";

$DANK = 'https://bafybeihz5555nb3vdcteuczpsdoqizesglvqp3nvubtsqw6cyqa6h4la6i.ipfs.nftstorage.link/1.png?ext=png';
$g = poly_gateway_cid($DANK);
ok(is_array($g) && $g['cid'] === 'bafybeihz5555nb3vdcteuczpsdoqizesglvqp3nvubtsqw6cyqa6h4la6i',
   'the CID is not lifted out of the subdomain form');
ok(is_array($g) && $g['path'] === '1.png?ext=png', 'the path after the CID is lost');
ok(poly_gateway_cid('https://ipfs.filebase.io/ipfs/bafybeihz5555nb3vdcteuczpsdoqizesglvqp3nvubtsqw6cyqa6h4la6i/1.png')
   !== null, 'the path form of a gateway URL is not recognised');

/*
 * THE LINE THIS DRAWS, and getting it wrong breaks the other chain.
 * A gateway URL names content that exists independently of the host, so
 * the CID is durable and the host is an accident -- fold it. A project's
 * OWN site does not, so it must be stored whole. Solana's OMEN is exactly
 * that case and it is live.
 */
ok(poly_gateway_cid('https://omenati.com/art/01998.jpg') === null,
   "a project's own domain is being treated as a gateway");
ok(poly_storable_image('https://omenati.com/art/01998.jpg') === 'https://omenati.com/art/01998.jpg',
   "OMEN's absolute URL is no longer stored whole -- that breaks Solana");

ok(poly_storable_image($DANK) === 'bafybeihz5555nb3vdcteuczpsdoqizesglvqp3nvubtsqw6cyqa6h4la6i/1.png?ext=png',
   'the Danketsu image is not stored as a CID the cache can race for');
ok(poly_storable_image('ipfs://bafybeihz5555nb3vdcteuczpsdoqizesglvqp3nvubtsqw6cyqa6h4la6i')
   === 'bafybeihz5555nb3vdcteuczpsdoqizesglvqp3nvubtsqw6cyqa6h4la6i', 'a plain ipfs:// uri is mangled');
ok(poly_storable_image('ipfs://ipfs/bafybeihz5555nb3vdcteuczpsdoqizesglvqp3nvubtsqw6cyqa6h4la6i')
   === 'bafybeihz5555nb3vdcteuczpsdoqizesglvqp3nvubtsqw6cyqa6h4la6i', 'the doubled ipfs/ prefix survives');
/* A poisoned nfts.ipfs is unrecoverable in practice, so anything that is
   neither a CID nor an absolute URL is dropped rather than stored. */
ok(poly_storable_image('ar://abcdef') === '', 'an arweave uri is being stored');
ok(poly_storable_image('/images/thing.png') === '', 'a relative path is being stored');
ok(poly_storable_image('ipfs://tooshort') === '', 'a truncated CID is being stored');
ok(poly_storable_image('') === '' && poly_storable_image(null) === '', 'empty input is not empty output');

/* ---------- the pass ------------------------------------------------------ */

$WROTE = array();
function processNFT($conn, $policy, $asset_name, $name, $image, $fingerprint,
                    $address, $asset_ids, $nft_owners, $collections, $chain = 1) {
	global $WROTE;
	$WROTE[] = array('policy' => $policy, 'asset_name' => $asset_name, 'name' => $name,
	                 'image' => $image, 'fingerprint' => $fingerprint,
	                 'address' => $address, 'chain' => $chain);
	$asset_ids[] = $fingerprint;
	return array('asset_ids' => $asset_ids, 'nft_owners' => $nft_owners);
}
$REPAIRED = array();
function updateNFTMetadata($conn, $aid, $name, $image) {
	global $REPAIRED; $REPAIRED[] = array($aid, $name, $image);
}

class PRes {
	public $num_rows; private $rows;
	function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	function fetch_assoc() { return array_shift($this->rows); }
}
class PConn {
	public $blank = array();      // asset_ids with no image, for the repair path
	function query($sql) {
		if (strpos($sql, 'SHOW COLUMNS') !== false)
			return new PRes(array(array('Field' => 'asset_id',   'Type' => 'varchar(64)'),
			                      array('Field' => 'asset_name', 'Type' => 'varchar(64)')));
		if (strpos($sql, "ipfs = ''") !== false) {
			$r = array();
			foreach ($this->blank as $a) $r[] = array('asset_id' => $a);
			return new PRes($r);
		}
		return new PRes(array());
	}
}

/*
 * A FAKE CHAIN. $CHAIN is contract => (token_id => owner); the fetcher
 * below answers eth_chainId, totalSupply, aggregate3(ownerOf) and
 * aggregate3(tokenURI) out of it, encoding its replies with the same
 * codec under test -- which is fine for the PASS-level checks, because
 * the codec itself is pinned against real node bytes above.
 */
$CHAIN = array(); $URIS = array(); $DOCS = array(); $BREAK_CHUNK = false; $CHAIN_ID = 137;

function penc_aggregate3_result($datas) {
	$n = count($datas); $bodies = array();
	foreach ($datas as $d) {
		$ok = ($d === null) ? 0 : 1;
		$payload = ($d === null) ? '' : $d;
		$len = strlen($payload) / 2;
		$bodies[] = poly_uint($ok) . poly_uint(64) . poly_uint($len)
		          . str_pad($payload, (int)(ceil(max(1, strlen($payload)) / 64) * 64), '0', STR_PAD_RIGHT);
	}
	$heads = ''; $cursor = $n * 32;
	foreach ($bodies as $b) { $heads .= poly_uint($cursor); $cursor += strlen($b) / 2; }
	return '0x' . poly_uint(32) . poly_uint($n) . $heads . implode('', $bodies);
}
function penc_string($s) {
	$h = bin2hex($s);
	return poly_uint(32) . poly_uint(strlen($s)) . str_pad($h, (int)(ceil(strlen($h) / 64) * 64), '0', STR_PAD_RIGHT);
}
function pfake($url, $post) {
	global $CHAIN, $URIS, $DOCS, $BREAK_CHUNK, $CHAIN_ID;
	if ($post === null) {                                     // a metadata GET
		return isset($DOCS[$url]) ? $DOCS[$url] : '';
	}
	$j = json_decode($post, true);
	$m = isset($j['method']) ? $j['method'] : '';
	if ($m === 'eth_chainId') return json_encode(array('result' => '0x' . dechex($CHAIN_ID)));
	if ($m !== 'eth_call') return '';
	$to   = strtolower($j['params'][0]['to']);
	$data = $j['params'][0]['data'];
	$sel  = substr($data, 2, 8);

	if ($sel === poly_sel('totalSupply')) {
		$c = poly_norm_address($to);
		return json_encode(array('result' => '0x' . poly_uint(isset($CHAIN[$c]) ? max(array_keys($CHAIN[$c])) : 0)));
	}
	if ($sel !== poly_sel('aggregate3')) return '';
	if ($BREAK_CHUNK) { $BREAK_CHUNK = false; return ''; }     // one chunk fails

	/*
	 * Pull target + inner calls back out.
	 *
	 * THE BASE, which this got wrong first time and which is the same
	 * mistake poly_aggregate3()'s comment warns about: after the selector
	 * (8 hex) comes the offset word (64) and then the array LENGTH (64),
	 * so the element-head region starts at 8+128 -- and each element
	 * offset is measured from THERE, not from the length word. Being 64
	 * characters out decodes the target as 0x...0100 and every token
	 * reads as unowned, with nothing erroring.
	 */
	$h = poly_strip($data); $n = hexdec(substr($h, 8 + 64, 64));
	$target = ''; $inner = array();
	for ($k = 0; $k < $n; $k++) {
		$o = hexdec(substr($h, 8 + 128 + $k * 64, 64)) * 2 + 8 + 128;
		$target = poly_norm_address('0x' . substr($h, $o + 24, 40));
		$len = hexdec(substr($h, $o + 192, 64));
		$inner[] = substr($h, $o + 256, $len * 2);
	}
	$out = array();
	foreach ($inner as $cd) {
		$isel = substr($cd, 0, 8); $arg = (int)hexdec(substr($cd, 8, 64));
		if ($isel === poly_sel('ownerOf')) {
			$out[] = isset($CHAIN[$target][$arg]) ? poly_word(substr(poly_norm_address($CHAIN[$target][$arg]), 2)) : null;
		} else if ($isel === poly_sel('tokenURI')) {
			$out[] = isset($URIS[$target][$arg]) ? penc_string($URIS[$target][$arg]) : null;
		} else $out[] = null;
	}
	return json_encode(array('result' => penc_aggregate3_result($out)));
}

$C1 = '0xee79a3e8aef1109a6ee82bf399ce9e1bd43cf5c4';
$C2 = '0x1111111111111111111111111111111111111111';
$A  = '0xaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa';
$B  = '0xbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb';

function run_pass($conn, $addresses, $collections, $asset_ids = array(), $opt = array()) {
	global $WROTE, $REPAIRED;
	$WROTE = array(); $REPAIRED = array();
	return verifyNFTsPolygon($conn, $addresses, $collections, $asset_ids, array(),
		array_merge(array('fetch' => 'pfake', 'node' => 'http://node.test'), $opt));
}

echo "\na burned token is absent, not owned by nobody\n";

/*
 * Token ids are NOT dense. This collection has 4,443 owners across ids
 * 1..4,444, so one id in the middle reverts, and totalSupply is the
 * highest id rather than the count. A revert must leave the map with no
 * entry at all: recording it as an owner of '' inflates the read count
 * and puts a sentinel where an address belongs, one typo away from
 * matching a wallet row that is also ''.
 */
$CHAIN = array($C1 => array(1 => $A, 3 => $A));          // 2 is burned
$map = poly_owner_map('http://node.test', $C1, 'pfake');
ok($map['ok'], 'a collection with a burned token read as a failure');
ok($map['supply'] === 3, 'totalSupply came back as ' . $map['supply']);
ok(count($map['owners']) === 2, 'the map holds ' . count($map['owners']) . ' owners, not 2');
ok(!array_key_exists(2, $map['owners']), 'the burned token is in the owner map');
ok(isset($map['owners'][3]) && $map['owners'][3] === $A, 'the token after the gap was lost');

echo "\nonly what this platform's wallets hold\n";

$CHAIN = array($C1 => array(1 => $A, 2 => $B, 3 => '0xcccccccccccccccccccccccccccccccccccccccc'));
$URIS  = array($C1 => array(1 => 'ipfs://cid1/1.json', 2 => 'ipfs://cid1/2.json'));
$DOCS  = array();
foreach (ipfs_gateways() as $g) {
	$DOCS[$g . 'cid1/1.json'] = json_encode(array('name' => 'Danketsu #1', 'image' => 'ipfs://' . str_repeat('a', 59) . '/1.png'));
	$DOCS[$g . 'cid1/2.json'] = json_encode(array('name' => 'Danketsu #2', 'image' => 'ipfs://' . str_repeat('b', 59) . '/2.png'));
}
$conn = new PConn();
$r = run_pass($conn, array($A, $B), array($C1 => 7));
ok($r['ok'], 'the pass failed on a clean chain');
ok($r['read'] === 3, 'the owner map read ' . $r['read'] . ' tokens, not the 3 on chain');
ok($r['wrote'] === 2, 'wrote ' . $r['wrote'] . ' rows; only two of the three tokens are ours');
$by = array(); foreach ($WROTE as $w) $by[$w['fingerprint']] = $w;
ok(isset($by[$C1 . ':1']) && $by[$C1 . ':1']['address'] === $A, 'token 1 did not land on its owner');
ok(isset($by[$C1 . ':2']) && $by[$C1 . ':2']['address'] === $B, 'token 2 did not land on its owner');
ok(!isset($by[$C1 . ':3']), "a token owned by a stranger was written");
ok($by[$C1 . ':1']['chain'] === POLYGON_CHAIN_ID,
   'the row is not tagged Polygon -- the Cardano pass will zero it tonight');
ok($by[$C1 . ':1']['name'] === 'Danketsu #1', 'the name did not come from the metadata');
ok($by[$C1 . ':1']['image'] === str_repeat('a', 59) . '/1.png', 'the image was not stored as a CID');

echo "\na mixed-case wallet still matches\n";

/* The day-one bug: ownerOf answers lower case, MetaMask reports EIP-55. */
$r = run_pass($conn, array(strtoupper(substr($A, 2)) === '' ? $A : '0xAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA'), array($C1 => 7));
ok($r['wrote'] === 1, 'an EIP-55 wallet address matched ' . $r['wrote'] . ' tokens instead of 1');

echo "\nthe gate: a half-read collection writes nothing\n";

$BREAK_CHUNK = true;
$r = run_pass($conn, array($A, $B), array($C1 => 7));
ok(!$r['ok'], 'a failed chunk reported success');
ok($r['wrote'] === 0, 'a failed chunk still wrote ' . $r['wrote'] . ' rows');
ok($r['failed'], 'a failed chunk did not say which');
$cleared = 0;
$BREAK_CHUNK = true;
$r = run_pass($conn, array($A, $B), array($C1 => 7), array(),
              array('clear' => function() use (&$cleared) { $cleared++; }));
ok($cleared === 0, 'ownership was CLEARED before the read succeeded -- every holder reads as having sold');

/* And the opposite: a clean read must clear exactly once, before writing. */
$cleared = 0;
$r = run_pass($conn, array($A, $B), array($C1 => 7), array(),
              array('clear' => function() use (&$cleared) { $cleared++; }));
ok($r['ok'] && $cleared === 1, 'a clean pass cleared ' . $cleared . ' times, expected once');

echo "\na node on the wrong chain is not a node\n";

$CHAIN_ID = 80002;                                   // Amoy testnet
$r = verifyNFTsPolygon($conn, array($A), array($C1 => 7), array(), array(),
	array('fetch' => 'pfake'));                      // no 'node', so it must pick one
ok(!$r['ok'], 'a testnet node was accepted; every holder would read as owning nothing');
$CHAIN_ID = 137;

echo "\ntwo contracts, same token id\n";

/* Both collections have a token 1. If metadata is keyed by token id
   instead of by queue position, one of them gets the other's artwork. */
$CHAIN = array($C1 => array(1 => $A), $C2 => array(1 => $A));
$URIS  = array($C1 => array(1 => 'ipfs://cid1/1.json'), $C2 => array(1 => 'ipfs://cid2/1.json'));
foreach (ipfs_gateways() as $g) {
	$DOCS[$g . 'cid1/1.json'] = json_encode(array('name' => 'Danketsu #1', 'image' => 'ipfs://' . str_repeat('a', 59) . '/1.png'));
	$DOCS[$g . 'cid2/1.json'] = json_encode(array('name' => 'Other #1',    'image' => 'ipfs://' . str_repeat('c', 59) . '/1.png'));
}
$r = run_pass($conn, array($A), array($C1 => 7, $C2 => 8));
ok($r['wrote'] === 2, 'expected both tokens, wrote ' . $r['wrote']);
$by = array(); foreach ($WROTE as $w) $by[$w['fingerprint']] = $w;
ok(isset($by[$C1 . ':1']) && $by[$C1 . ':1']['name'] === 'Danketsu #1',
   'contract 1 token 1 got the wrong metadata');
ok(isset($by[$C2 . ':1']) && $by[$C2 . ':1']['name'] === 'Other #1',
   "contract 2 token 1 got the other contract's metadata");

echo "\nmetadata that cannot be read is not a failure\n";

$DOCS = array();                                      // every gateway dark
$CHAIN = array($C1 => array(1 => $A));
$URIS  = array($C1 => array(1 => 'ipfs://cid1/1.json'));
$r = run_pass($conn, array($A), array($C1 => 7));
ok($r['ok'], 'one unreadable document aborted the whole pass');
ok($r['wrote'] === 1, 'the row was not written without its picture');
ok($WROTE[0]['image'] === '', 'an unreadable document produced a non-empty image');
/* The fallback is numbered by TOKEN id, not by position in the batch --
   an earlier version built it from poly_resolve_many()'s key, which is
   the queue index, so token 4,312 came out as "#0". */
ok($WROTE[0]['name'] === 'Polygon #1',
   'the fallback name is "' . $WROTE[0]['name'] . '", not the token id');

echo "\nand the empty image is the repair marker\n";

$conn->blank = array($C1 . ':1');
foreach (ipfs_gateways() as $g)
	$DOCS[$g . 'cid1/1.json'] = json_encode(array('name' => 'Danketsu #1', 'image' => 'ipfs://' . str_repeat('a', 59) . '/1.png'));
$r = run_pass($conn, array($A), array($C1 => 7), array());
ok($r['repaired'] === 1, 'a row with no picture was not repaired when the document came back');
ok($REPAIRED && $REPAIRED[0][0] === $C1 . ':1', 'the repair targeted the wrong asset');
$conn->blank = array();

echo "\nwhere a Polygon collection, token and wallet link to\n";

/* Lifted out of db.php and run without it, the way verify-solana-harness
   does -- these are the three links the Collections page, the gallery and
   the wallet list build, and none of them has a Polygon branch by
   default. */
function plift($src, $sig, $what) {
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
$dsrc = file_get_contents(__DIR__ . '/db.php');
if (!defined('XRPL_CHAIN_ID'))   define('XRPL_CHAIN_ID', 2);
if (!defined('SOLANA_CHAIN_ID')) define('SOLANA_CHAIN_ID', 3);
eval(plift($dsrc, 'function collectionMarketUrl(', 'collectionMarketUrl()'));
eval(plift($dsrc, 'function accountExplorerUrl(',  'accountExplorerUrl()'));
eval(plift($dsrc, 'function nftExplorerUrl(',      'nftExplorerUrl()'));

$CC = '0xee79a3e8aef1109a6ee82bf399ce9e1bd43cf5c4';

/* WITH a slug: OpenSea, which addresses a collection by slug and nothing
   else. Danketsu's is danketsu-nft, verified against the live page --
   "danketsu" is not it, and returns OpenSea's generic landing title. */
ok(collectionMarketUrl($CC, POLYGON_CHAIN_ID, 'danketsu-nft')
   === 'https://opensea.io/collection/danketsu-nft', 'the slug no longer reaches OpenSea');
/* WITHOUT one: NOT OpenSea. Handing it a contract address produces a dead
   page -- the same trap wayup set on Cardano. Rarible addresses by
   contract and works, so it is the nearest honest thing. */
$noslug = collectionMarketUrl($CC, POLYGON_CHAIN_ID);
ok(strpos($noslug, 'opensea.io') === false,
   'a contract address is being handed to OpenSea as if it were a slug: ' . $noslug);
ok($noslug === 'https://rarible.com/collection/polygon/' . $CC . '/items',
   'the no-slug fallback is ' . var_export($noslug, true));
ok(collectionMarketUrl('not-an-address', POLYGON_CHAIN_ID) === '',
   'a junk policy produces a link rather than nothing');

/* A Cardano policy must not pick up Polygon's behaviour, and vice versa. */
ok(collectionMarketUrl(str_repeat('a', 56), 1, 'some-slug')
   === 'https://www.wayup.io/collection/' . str_repeat('a', 56),
   'adding Polygon to $slug_chains let a slug through on Cardano');

/* The token link. asset_id is contract:tokenId and OpenSea wants the two
   as separate path segments, which a single-%s template cannot express --
   so it is built, and must not fall through to pool.pm. */
$fake_conn = new PConn();
$tok = nftExplorerUrl($fake_conn, $CC . ':4312', POLYGON_CHAIN_ID);
ok($tok === 'https://opensea.io/assets/matic/' . $CC . '/4312',
   'the token link is ' . var_export($tok, true));
ok(strpos($tok, 'pool.pm') === false, 'a Polygon token links to a Cardano explorer');
ok(nftExplorerUrl($fake_conn, 'no-colon-here', POLYGON_CHAIN_ID) === '',
   'a malformed asset id produces a link rather than nothing');

ok(accountExplorerUrl('0xAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA', POLYGON_CHAIN_ID)
   === 'https://polygonscan.com/address/0xAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA',
   'a Polygon wallet does not link to polygonscan');
ok(strpos(accountExplorerUrl('0xAAAA', POLYGON_CHAIN_ID), 'pool.pm') === false,
   'a Polygon wallet links to pool.pm, which renders a "not found" and reads as a lost wallet');

echo "\nthere is one gateway list, not four\n";

/*
 * There were four, in db.php, lib/image-cache-lib.php, verify-solana.php and
 * here, and they had all drifted apart -- different hosts, different orders,
 * and none of them carrying the only gateway that still answers this server.
 * A racing caller hides that completely, so the drift has to be caught by a
 * test rather than by symptoms.
 */
foreach (array('db.php', 'lib/image-cache-lib.php', 'verify-solana.php', 'verify-polygon.php') as $f) {
	$src = file_get_contents(__DIR__ . '/' . $f);
	$src = preg_replace('!/\*.*?\*/!s', '', $src);          // the measurements live in comments
	ok(substr_count($src, "'https://ipfs.io/ipfs/'") === 0,
	   "$f has its own hardcoded gateway list again; there is one, in lib/ipfs-gateways.php");
}
ok(count(ipfs_gateways()) >= 2, 'the shared gateway list has no fallback left');
ok(in_array('https://ipfs.filebase.io/ipfs/', ipfs_gateways(), true),
   'the only gateway measured as serving Danketsu art is gone from the list');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "polygon verifier: ok\n";
