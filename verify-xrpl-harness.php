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
                    $address, $asset_ids, $nft_owners, $collections) {
	global $WROTE;
	$WROTE[] = compact('policy_id', 'asset_name', 'name', 'image', 'fingerprint', 'address');
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
function page($ids, $marker = null, $issuer = 'rISS', $taxon = 1) {
	$nfts = array();
	foreach ($ids as $i) {
		$nfts[] = array('NFTokenID' => str_pad((string)$i, 64, '0', STR_PAD_LEFT),
			'Issuer' => $issuer, 'NFTokenTaxon' => $taxon, 'nft_serial' => $i,
			'URI' => bin2hex('ipfs://meta' . $i));
	}
	$r = array('account_nfts' => $nfts);
	if ($marker !== null) $r['marker'] = $marker;
	return json_encode(array('result' => $r));
}

$calls = 0;
$paged = function($url, $post) use (&$calls) {
	if ($post === null) return json_encode(array('name' => 'Meta', 'image' => 'ipfs://img'));
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
	if ($post === null) return json_encode(array('name' => 'Maxi One', 'image' => 'ipfs://'.'QmPIC'));
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
