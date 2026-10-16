<?php
/*
 * IPFS: which gateway, and how a CID is stored.
 *
 * Two bugs, both silent, both found from one report of Happy Hoppers art
 * failing in the Monstrocity theme while working in My NFTs.
 *
 * Usage: php monstrocity-ipfs-harness.php   (add live=1 to hit the gateways)
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }
require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/ipfs-gateways.php';

/* ---- 1. a bare CID must survive being stored ---------------------------- */
echo "how an image field becomes a CID\n";
/*
 * THE BUG: verify.php did substr($image, 7) unconditionally, on the
 * assumption everything reaching it was an ipfs:// URI. CIP-25 permits a
 * bare CID and older collections use one, so those lost their first seven
 * characters and became a CID no gateway can resolve -- the skull
 * placeholder, forever, with nothing anywhere saying why.
 */
$vs = file_get_contents(__DIR__ . '/verify.php');
$vn = harness_strip_comments($vs);
ok(strpos($vn, 'substr($image, 7, strlen($image))') === false,
   'verify.php still strips seven characters off every image value, which destroys a bare CID');
ok(strpos($vn, "stripos((string)\$image, 'ipfs://') === 0") !== false,
   'verify.php does not check for the ipfs:// prefix before stripping it');

/* The rule itself, driven over every shape the column has ever held. */
function ipfs_norm($image) {
	if (str_contains((string)$image, 'data:image/svg+xml;base64')) return $image;
	if (preg_match('~^https?://~i', (string)$image))               return $image;
	if (stripos((string)$image, 'ipfs://') === 0)                  return substr($image, 7);
	return (string)$image;
}
$CID = 'QmQtVpbUTXB29tNsdzaTrtEpn51NxFn6d68izsTUthbieW';   // a real Happy Hoppers poster
$cases = array(
	'ipfs://' . $CID => $CID,                       // the common shape, unchanged
	'IPFS://' . $CID => $CID,                       // case must not matter
	$CID             => $CID,                       // THE BUG: a bare CID, left alone
	'bafybeigdyrzt5sfp7udm7hu76uh7y26nf3efuylqabf3oclgtqy55fbzdi'
	                 => 'bafybeigdyrzt5sfp7udm7hu76uh7y26nf3efuylqabf3oclgtqy55fbzdi',
	'https://omenati.com/art/01998.jpg' => 'https://omenati.com/art/01998.jpg',
);
foreach ($cases as $in => $want) {
	$got = ipfs_norm($in);
	ok($got === $want, 'image "' . substr($in, 0, 24) . '..." stored as "' . substr($got, 0, 24) . '..."');
}
/* And the mangling specifically cannot come back. */
ok(ipfs_norm($CID) !== substr($CID, 7),
   'a bare CID is still being chopped, which is the bug that produced the skull placeholder');

/* ---- 2. nothing may hardcode a gateway that refuses this server --------- */
echo "\ngateways\n";
$dead = array('ipfs.io', 'dweb.link');
$gw = ipfs_gateways();
ok($gw[0] === 'https://ipfs.filebase.io/ipfs/',
   'the measured-best gateway is no longer first in lib/ipfs-gateways.php');
foreach ($dead as $d)
	ok(strpos($gw[0], $d) === false, 'the first gateway tried is ' . $d . ', which answers 429 here');

$game = file_get_contents(__DIR__ . '/monstrocity.php');
/* Comments stripped for the require check: "// require_once ..." still
   contains the string, and a plain strpos was happy with it. */
$gameNo = harness_strip_comments($game);
ok(substr_count($game, "'https://ipfs.io/ipfs/'") === 0,
   'monstrocity.php hardcodes ipfs.io, which refuses this server');
ok(strpos($game, 'MONSTROCITY_GATEWAYS') !== false,
   'the game does not use the platform gateway list');
ok(strpos($gameNo, "require_once __DIR__ . '/lib/ipfs-gateways.php'") !== false,
   'the page echoes ipfs_gateways() without requiring the file that defines it -- it is only '
 . 'required inside a db.php function, so this would be a fatal');
foreach ($dead as $d)
	ok(strpos($game, "'" . $d . "'") !== false,
	   $d . ' is not in the dead-gateway filter, so a theme could still pin itself to it');

/* ---- 3. live, only when asked ------------------------------------------- */
if (in_array('live=1', $argv, true)) {
	echo "\nlive gateway check (a real Happy Hoppers poster)\n";
	foreach (array($gw[0], 'https://ipfs.io/ipfs/') as $g) {
		/* A RANGED GET, NOT A HEAD. filebase does not answer HEAD -- it came
		   back 0 while the same CID served 200 to a GET, which would have
		   read as the good gateway being down. One byte is enough. */
		$ch = curl_init($g . $CID);
		curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER=>1, CURLOPT_TIMEOUT=>25,
		                             CURLOPT_RANGE=>'0-0'));
		curl_exec($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		printf("  %-32s HTTP %d\n", $g, $code);
		if (strpos($g, 'ipfs.io') === false)
			ok($code === 200 || $code === 206,
			   'the first-choice gateway did not serve a known-good CID (HTTP ' . $code . ')');
	}
}

/* ---- 4. the game resolves like My NFTs does ----------------------------- */
echo "\nhow the game resolves an NFT image\n";
/*
 * My NFTs is reliable because getIPFS() prefers a LOCAL CACHED FILE and the
 * client never picks a gateway. The game built a URL client-side from a CID
 * and a per-theme prefix, so it inherited whatever gateway the theme row
 * happened to name -- ipfs.io, for all 49.
 *
 * ajax/get-nft-assets.php already returns localUrl alongside the CID. The
 * game now builds the whole chain: cached copy, then each measured gateway,
 * then the skull, and walks it on error instead of giving up after one try.
 */
ok(strpos($gameNo, 'function monstrocityImageChain(') !== false,
   'the game does not build a fallback chain for NFT images');
ok(strpos($gameNo, 'config.localUrl') !== false,
   'the game ignores the locally cached copy the endpoint already hands it');
/* The cached copy must come FIRST -- it is the only candidate that does not
   involve a gateway at all. */
$chAt = strpos($gameNo, 'function monstrocityImageChain(');
$chEnd = strpos($gameNo, "\n    }", $chAt);
$chBody = substr($gameNo, $chAt, $chEnd - $chAt);
ok(strpos($chBody, 'config.localUrl') < strpos($chBody, 'MONSTROCITY_GATEWAYS'),
   'the chain tries a gateway before the locally cached file');
ok(strpos($chBody, "out.push('icons/skull.png')") !== false,
   'the chain does not end at the placeholder, so a dead CID draws nothing');

/* It must WALK, not stop at one fallback. */
ok(strpos($gameNo, 'function monstrocityNextSrc(') !== false,
   'there is no walker, so one failed gateway is the end of it');
ok(strpos($gameNo, 'onerror="monstrocityNextSrc(this)"') !== false,
   'the rendered images do not call the walker on error');
/*
 * AND IT MUST BE AN INLINE HANDLER. These elements are built as template
 * strings with onerror attributes; an inline onerror pre-empts a document
 * listener entirely, which is the trap that stopped the avatar repair
 * firing on the leaderboards. A listener here would never run.
 */
ok(strpos($gameNo, "addEventListener('error'") === false,
   'the game added a document-level error listener; the inline onerror on these images '
 . 'pre-empts it and it would never fire');
/* No render path may still jump straight to a single fallback. */
ok(preg_match('/onerror="this\.src=\x27\$\{character\.fallbackUrl\}\x27"/', $gameNo) !== 1,
   'a character image still replaces itself with one fallback instead of walking the chain');

/* The per-theme prefix is no longer consulted anywhere. */
ok(preg_match('/ipfsPrefix:\s*/', $gameNo) !== 1,
   'the game still reads a per-theme ipfsPrefix, which pinned 49 themes to a dead gateway');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall ipfs checks passed\n");
exit($fail ? 1 : 0);
