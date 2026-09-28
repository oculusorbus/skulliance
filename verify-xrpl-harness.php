<?php
/* verify-xrpl-harness.php — CLI only. Exercises the whole XRPL verifier with
   no network and no database, which is the entire reason every external call
   in verify-xrpl.php goes through an injectable $fetch.

   The Cardano verifier has never had one of these, and the bugs it has grown
   over the years -- transient Koios 504s taking down a whole nightly run, an
   extra offset pass bolted on for one wallet with more than 1,000 UTXOs -- are
   all things a harness would have caught at the desk instead of at 3am.

   Usage: php verify-xrpl-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/* processNFT() lives in verify.php, which pulls in the platform. The harness
   stubs it and records what it was asked to write, because what this is
   testing is what the XRPL side HANDS to it. */
$WROTE = array();
function processNFT($conn, $policy_id, $asset_name, $name, $image, $fingerprint,
                    $address, $asset_ids, $nft_owners, $collections, $blockchain_id = 1) {
	global $WROTE;
	/* $blockchain_id is captured because PHP SILENTLY DISCARDS extra arguments
	   to a user-defined function. The old stub took ten parameters, so when
	   the caller passed a chain id as an eleventh it vanished and every test
	   here still passed -- which is exactly how XRPL rows were written as
	   Cardano with a green harness. */
	$WROTE[] = compact('policy_id', 'asset_name', 'name', 'image', 'fingerprint',
	                   'address', 'blockchain_id');
	$asset_ids[] = $fingerprint;
	$nft_owners[] = '1-' . $fingerprint;
	return array('asset_ids' => $asset_ids, 'nft_owners' => $nft_owners);
}
require __DIR__ . '/verify-xrpl.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* ---------- 1. the pure mappings ------------------------------------------ */
ok(xrpl_collection_key('rABC', 7) === 'rABC:7', 'issuer+taxon makes a collection key');
ok(xrpl_collection_key('rABC', '007') === 'rABC:7', 'a taxon is normalised to an int');
ok(xrpl_hex_to_str('697066733A2F2F616263') === 'ipfs://abc', 'a hex URI decodes');
ok(xrpl_hex_to_str('nothex') === '', 'a non-hex URI is rejected, not mangled');
ok(xrpl_hex_to_str('ABC') === '', 'odd-length hex is rejected');
ok(xrpl_hex_to_str('') === '', 'an empty URI is empty');
echo "mappings: ok\n";

/* processNFT() does substr($image, 7) to strip "ipfs://", so anything handed
   to it MUST carry a seven-character scheme or the CID loses its first seven
   characters -- silently, producing a broken image URL nobody notices. */
$cid = 'QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG';
ok(xrpl_normalise_image('ipfs://'.$cid) === 'ipfs://'.$cid, 'ipfs:// is left alone');
ok(xrpl_normalise_image('ipfs://ipfs/'.$cid) === 'ipfs://'.$cid, 'ipfs://ipfs/ is collapsed');
ok(xrpl_normalise_image('https://ipfs.io/ipfs/'.$cid) === 'ipfs://'.$cid, 'a gateway URL is folded back');
ok(xrpl_normalise_image($cid) === 'ipfs://'.$cid, 'a bare CID gains a scheme');
ok(substr(xrpl_normalise_image($cid), 7) === $cid, 'and survives processNFT stripping 7 chars');
ok(xrpl_normalise_image('https://example.com/a.png') === 'https://example.com/a.png', 'http is untouched');
echo "image normalisation: ok (the substr(7) contract holds)\n";

/* ---------- 2. paging: the silent-truncation trap -------------------------- */
/* A CIDv0 is exactly 46 characters and the image cache skips anything shorter,
   so fixtures use real-length ones. Short fakes pass through code that would
   reject them in production, which is a test that proves nothing. */
function fakecid($n) { return 'Qm' . substr(str_repeat(md5((string)$n), 2), 0, 44); }
function page($ids, $marker = null, $issuer = 'rISS', $taxon = 1) {
	$nfts = array();
	foreach ($ids as $i) {
		$nfts[] = array('NFTokenID' => str_pad((string)$i, 64, '0', STR_PAD_LEFT),
			'Issuer' => $issuer, 'NFTokenTaxon' => $taxon, 'nft_serial' => $i,
			'URI' => bin2hex('ipfs://' . fakecid($i)));
	}
	$r = array('account_nfts' => $nfts);
	if ($marker !== null) $r['marker'] = $marker;
	return json_encode(array('result' => $r));
}

$calls = 0;
$paged = function($url, $post) use (&$calls) {
	if ($post === null) return json_encode(array('name' => 'Meta', 'image' => 'ipfs://'.fakecid(0)));
	$calls++;
	$req = json_decode($post, true);
	$m = isset($req['params'][0]['marker']) ? $req['params'][0]['marker'] : null;
	if ($m === null)  return page(array(1,2), 'm1');
	if ($m === 'm1')  return page(array(3,4), 'm2');
	return page(array(5));                       // no marker: last page
};
$got = xrpl_account_nfts('http://node', 'rHOLDER', $paged);
ok($got['ok'], 'a paged account reads ok');
ok(count($got['list']) === 5, 'every page is followed -- got '.count($got['list']).' of 5');
ok($calls === 3, 'it took exactly 3 requests, not 1 ('.$calls.')');
printf("paging: %d NFTs over %d pages (a skipped marker loop would have returned 2)\n",
	count($got['list']), $calls);

/* ---------- 3. failure must not read as a sale ----------------------------- */
$halfDead = function($url, $post) {
	if ($post === null) return '';
	$req = json_decode($post, true);
	// first page fine, second page dies
	return isset($req['params'][0]['marker']) ? '' : page(array(1,2), 'm1');
};
$got2 = xrpl_account_nfts('http://node', 'rHOLDER', $halfDead);
ok($got2['ok'] === false, 'a failed page marks the whole read as failed');
ok(count($got2['list']) === 2, 'the partial list is returned but flagged');

$dead = function($url, $post) { return ''; };
$res = verifyNFTsXRPL(null, array('rA', 'rB'), array('rISS:1' => 9), array(),
	array(), array('fetch' => $dead));
ok($res['ok'] === false, 'a pass with unreachable addresses is not ok');
ok(count($res['failed']) === 2, 'both addresses are reported failed');
ok(empty($WROTE), 'and NOTHING was written -- a half-read account must not look like a sale');

/* READ BEFORE WRITE, AND DO NOT CLEAR ON A FAILED READ. The payout step reads
   this table. A cleared-then-partially-rebuilt set is not a smaller truth, it
   is a wrong one, and it is wrong in money. */
$WROTE = array(); $cleared = 0;
$oneBad = function($url, $post) use (&$calls) {
	static $n = 0;
	if ($post === null) return json_encode(array('name' => 'X', 'image' => ''));
	$req = json_decode($post, true);
	// the second address is unreadable; the first is fine
	return $req['params'][0]['account'] === 'rGOOD' ? page(array(1)) : '';
};
$res9 = verifyNFTsXRPL(null, array('rGOOD', 'rBAD'), array('rISS:1' => 9), array(),
	array(), array('fetch' => $oneBad, 'clear' => function() use (&$cleared) { $cleared++; }));
ok($res9['ok'] === false, 'one unreadable address fails the whole pass');
ok($cleared === 0, 'and the table was NEVER CLEARED -- yesterday\'s rows stand');
ok(empty($WROTE), 'and the readable address was not written either');
ok($res9['read'] === 1, 'the report still says how much was read ('.$res9['read'].')');

/* the happy path still clears, in the right order */
$WROTE = array(); $cleared = 0; $orderOk = true;
$good = function($url, $post) {
	if ($post === null) return json_encode(array('name' => 'X', 'image' => ''));
	return page(array(1, 2), null, 'rISS', 1);
};
$res10 = verifyNFTsXRPL(null, array('rGOOD'), array('rISS:1' => 9), array(),
	array(), array('fetch' => $good, 'clear' => function() use (&$cleared, &$WROTE, &$orderOk) {
		if (!empty($WROTE)) $orderOk = false;   // clearing AFTER writing would be catastrophic
		$cleared++;
	}));
ok($res10['ok'] === true, 'a clean pass succeeds');
ok($cleared === 1, 'it cleared exactly once');
ok($orderOk, 'and it cleared BEFORE writing, not after');
ok(count($WROTE) === 2, 'both NFTs were written ('.count($WROTE).')');

/* the budget is a failure, not a partial write */
$WROTE = array(); $cleared = 0;
$slow = function($url, $post) {
	if ($post === null) return json_encode(array('name' => 'X', 'image' => ''));
	return page(array(1));
};
$res11 = verifyNFTsXRPL(null, array('rA'), array('rISS:1' => 9), array(),
	array(), array('fetch' => $slow, 'deadline' => time() - 1,
	                'clear' => function() use (&$cleared) { $cleared++; }));
ok($res11['ok'] === false, 'an exhausted time budget fails the pass');
ok($cleared === 0, 'and still does not clear');
echo "atomicity: ok (read all, then clear, then write — or do none of it)\n";
echo "failure isolation: ok (partial reads are refused, not processed)\n";

/* actNotFound is an unfunded account: empty, not broken. */
$absent = function($url, $post) {
	return json_encode(array('result' => array('status' => 'error', 'error' => 'actNotFound')));
};
$got3 = xrpl_account_nfts('http://node', 'rNEW', $absent);
ok($got3['ok'] === true && count($got3['list']) === 0, 'an unfunded account reads as empty, not failed');

/* ---------- 4. only our collections, and what gets written ----------------- */
$WROTE = array();
$mixed = function($url, $post) {
	if ($post === null) return json_encode(array('name' => 'Maxi One', 'image' => 'ipfs://'.fakecid(1)));
	$nfts = array(
		array('NFTokenID' => str_pad('A', 64, '0'), 'Issuer' => 'rMAXI', 'NFTokenTaxon' => 1,
		      'nft_serial' => 11, 'URI' => bin2hex('ipfs://meta')),
		array('NFTokenID' => str_pad('B', 64, '0'), 'Issuer' => 'rOTHER', 'NFTokenTaxon' => 3,
		      'nft_serial' => 12, 'URI' => bin2hex('ipfs://meta')),
	);
	return json_encode(array('result' => array('account_nfts' => $nfts)));
};
$res2 = verifyNFTsXRPL(null, array('rHOLDER'), array('rMAXI:1' => 42), array(),
	array(), array('fetch' => $mixed));
ok($res2['ok'], 'the mixed pass succeeded');
ok(count($WROTE) === 1, 'only OUR collection was written ('.count($WROTE).' rows)');
if ($WROTE) {
	$w = $WROTE[0];
	ok($w['policy_id'] === 'rMAXI:1', 'policy is issuer:taxon');
	ok($w['fingerprint'] === str_pad('A', 64, '0'), 'fingerprint is the NFTokenID');
	ok($w['name'] === 'Maxi One', 'the name came from the metadata');
	ok(strpos($w['image'], 'ipfs://') === 0, 'the image carries the scheme processNFT strips');
	printf("write: policy=%s name=%s image=%s\n", $w['policy_id'], $w['name'], $w['image']);
}

/* ---------- 4b. nfts.ipfs must be a bare CID, or the cache is poisoned ----- */
/* lib/image-cache-lib.php builds its fetch as gateway . value, and processNFT()
   gets the value by chopping exactly 7 characters. Anything that is not
   ipfs://<cid> must be dropped here rather than stored mangled. */
$realCid = 'QmYwAPJzv5CZsnA625s3Xf2nemtYgPpHdWEz79ojWnPbdG';
ok(xrpl_storable_image('ipfs://'.$realCid) === 'ipfs://'.$realCid, 'an ipfs CID is stored');
ok(xrpl_storable_image('https://ipfs.io/ipfs/'.$realCid) === 'ipfs://'.$realCid,
   'a gateway URL is folded to ipfs://, not stored as a URL');
ok(xrpl_storable_image('https://example.com/a.png') === '',
   'a non-IPFS http image is DROPPED, not mangled into /example.com/a.png');
ok(xrpl_storable_image('https://arweave.net/abc123') === '', 'arweave is dropped too');
ok(xrpl_storable_image('ipfs://short') === '', 'a truncated CID is dropped');
ok(xrpl_storable_image('') === '', 'no image stays no image');
/* the contract that makes it all work */
ok(substr(xrpl_storable_image('ipfs://'.$realCid), 7) === $realCid,
   'after processNFT strips 7 chars the result is a BARE CID the cache can use');
ok(strlen(substr(xrpl_storable_image('ipfs://'.$realCid), 7)) >= 46,
   'and it clears the cache\'s 46-character malformed-CID guard');
echo "image storage: ipfs-or-nothing (the cache prepends a gateway, so a URL would poison it)\n";

$WROTE = array();
$httpImg = function($url, $post) {
	if ($post === null) return json_encode(array('name' => 'Hosted', 'image' => 'https://example.com/a.png'));
	return page(array(9), null, 'rMAXI', 1);
};
verifyNFTsXRPL(null, array('rHOLDER'), array('rMAXI:1' => 42), array(),
	array(), array('fetch' => $httpImg));
ok(count($WROTE) === 1, 'an NFT with a non-IPFS image is still staked');
ok($WROTE[0]['image'] === '', 'but its image is empty rather than mangled');
ok($WROTE[0]['name'] === 'Hosted', 'and it keeps its real name');

/* ---------- 5. a broken gateway must not stop somebody staking ------------- */
$WROTE = array();
$noGateway = function($url, $post) {
	if ($post === null) return '';          // IPFS is down
	return page(array(7), null, 'rMAXI', 1);
};
verifyNFTsXRPL(null, array('rHOLDER'), array('rMAXI:1' => 42), array(),
	array(), array('fetch' => $noGateway));
ok(count($WROTE) === 1, 'the NFT is still written when metadata cannot be fetched');
ok(!empty($WROTE[0]['name']), 'and it has a name, or processNFT would skip it entirely');

/* THE CHAIN ID MUST REACH THE INSERT. createNFT() defaults it to 1, so an
   XRPL row that does not carry a 2 claims to be Cardano -- and the Cardano
   pass then zeroes it on its next run and cannot put it back. */
$chains = array();
foreach ($WROTE as $w) $chains[(string)$w['blockchain_id']] = 1;
ok(count($chains) === 1 && isset($chains[(string)XRPL_CHAIN_ID]),
   'every row is handed processNFT with blockchain_id ' . XRPL_CHAIN_ID
   . ' (got: ' . implode(',', array_keys($chains)) . ')');
printf("degraded metadata: still staked, named %s\n", json_encode($WROTE[0]['name']));

/* Metadata that is an image rather than JSON. */
$WROTE = array();
$imgOnly = function($url, $post) {
	if ($post === null) return "\x89PNG\r\n\x1a\n binary, not json";
	return page(array(8), null, 'rMAXI', 1);
};
verifyNFTsXRPL(null, array('rHOLDER'), array('rMAXI:1' => 42), array(),
	array(), array('fetch' => $imgOnly));
ok(count($WROTE) === 1 && strpos($WROTE[0]['image'], 'ipfs://') === 0,
   'a URI pointing straight at an image is used as the image');

echo "\n".($fail ? "FAILED: $fail check(s)\n" : "all XRPL verifier checks passed\n");
exit($fail ? 1 : 0);
