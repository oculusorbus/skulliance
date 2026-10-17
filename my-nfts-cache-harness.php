<?php
/**
 * my-nfts-cache-harness.php -- the "save these artworks" button on My NFTs.
 *
 * WHAT THIS IS REALLY GUARDING. ajax/cache-nft-image.php already had a
 * caller: every image that failed to load called it automatically, and on a
 * page where many failed at once those parallel requests saturated PHP-FPM
 * and locked the site. That path was cut back to showing a skull
 * (healNFT in skulliance.js still only does that).
 *
 * The button calls the same endpoint again, and is safe only because of the
 * shape: bounded by the page, strictly one request in flight, and started by
 * a person. Every one of those is checked below, because losing any of them
 * re-creates an outage rather than a bug.
 */
$fails = 0;
function ok($c, $w) { global $fails; if (!$c) { $fails++; echo "FAIL  $w\n"; } else echo "ok    $w\n"; }

$src = file_get_contents(__DIR__ . '/my-nfts.php');
/*
 * A SECOND COPY WITH THE COMMENTS GONE, for the checks that count call
 * sites. This file explains itself at length and names both getIPFS() and
 * cacheNFTImage() in prose -- counting those as calls said the page did
 * things it does not do, and would have gone on saying so after the real
 * calls were removed. harness-strip.php is the repo's stripper; the naive
 * regex every harness used to inline eats `accept="image/*"` and takes
 * kilobytes of live code with it.
 */
require_once __DIR__ . '/harness-strip.php';
$code = harness_strip_comments($src);

echo "-- the page knows which NFTs have no local copy --\n";
ok(strpos($src, '$mn_uncached = array();') !== false, 'it keeps a list of them');
ok(preg_match('/foreach \(\$mn_rows as \$r\) \{/', $src), 'built in a pass over the rows');
ok(strpos($src, "strpos(\$url, '/staking/images/nfts/') === 0") !== false,
   'and decides by what getIPFS() returned, not a second glob');
ok(substr_count($code, 'getIPFS(') === 1,
   'getIPFS is CALLED once per row (' . substr_count($code, 'getIPFS(') . ' call sites)');
ok(strpos($src, "strpos((string)\$r[\"ipfs\"], 'data:image') !== false") !== false,
   'an inline data URI is not counted -- there is no file to fetch');

echo "\n-- the button only exists when there is something to do --\n";
ok(preg_match('/if \(\$mn_uncached\):/', $src), 'the button is inside a guard on that list');
ok(substr_count($src, 'if ($mn_uncached):') === 2,
   'and so are the modal and its script (' . substr_count($src, 'if ($mn_uncached):') . ' guards)');
ok(strpos($src, 'count($mn_uncached)') !== false, 'the count is shown on it');

echo "\n-- it reuses the endpoint rather than caching inline --\n";
ok(strpos($src, "ajax/cache-nft-image.php") !== false, 'it posts to the shared endpoint');
ok(strpos($code, 'cacheNFTImage(') === false,
   'and does NOT call the cache library itself, which would bypass the rate limit');
$ep = file_get_contents(__DIR__ . '/ajax/cache-nft-image.php');
ok(strpos($ep, 'rl_max') !== false, 'the endpoint still rate-limits');
ok(strpos($ep, 'SELECT n.ipfs') !== false, 'and still reads the CID from the database, not the client');

echo "\n-- STRICTLY SERIAL, which is the whole reason this is safe --\n";
/*
 * The runner is a function that calls itself only from a completion handler.
 * What must never appear is a loop that fires them all: that is precisely
 * what took the site down before.
 */
$i = strpos($src, "btn.addEventListener('click'");
$j = strpos($src, '})();', $i);
$run = substr($src, $i, $j - $i);
ok($run !== '', 'the click handler is there to read');
ok(substr_count($run, 'xhr.send(') === 1, 'exactly one send() in the whole runner');
ok(substr_count($run, 'new XMLHttpRequest()') === 1, 'and one request object');
ok(!preg_match('/(for|while)\s*\([^)]*\)\s*\{[^}]*xhr/s', $run),
   'no loop wraps the request');
ok(!preg_match('/ids\.(forEach|map)\s*\(/', $run),
   'the id list is never iterated to fire requests');
ok(strpos($run, 'function step()') !== false, 'it steps instead');
ok(preg_match('/done\+\+;.*?step\(\);/s', $run),
   'and the next step only happens after one finishes');
ok(strpos($run, 'Promise.all') === false, 'nothing is run in parallel');
ok(strpos($run, 'btn.disabled = true') !== false, 'the button disables while it runs');
ok(strpos($run, 'btn.disabled = false') !== false, '...and comes back afterwards');

echo "\n-- every outcome the endpoint can return is reported --\n";
/* The endpoint's own statuses, from lib/image-cache-lib.php's contract. */
foreach (array('cached', 'exists', 'skipped', 'error') as $st) {
	ok(strpos($run, "'" . $st . "'") !== false, "  '$st' is handled");
}
ok(strpos($run, 'd.message') !== false, "a failure's reason is shown, not just that it failed");
ok(strpos($run, 'xhr.ontimeout') !== false, 'a timeout is reported');
ok(strpos($run, 'xhr.onerror') !== false, 'so is a request that never arrived');
ok(strpos($run, 'catch (e) {}') !== false || strpos($run, 'try { d = JSON.parse') !== false,
   'and a reply that is not JSON does not throw');
ok(strpos($run, 'img.src = url') !== false, 'a saved image is swapped in without a reload');

echo "\n-- the results modal is its own, not the announcement one --\n";
ok(strpos($src, 'id="mn-cache-veil"') !== false, 'it has its own veil');
ok(strpos($run, 'myModal') === false, 'and never touches #myModal');
ok(strpos($src, 'renderVisibility') !== false, 'the visibility prompt is still there, untouched');
$veilCss = '';
if (preg_match('/\.mn-cache-veil\{([^}]*)\}/', $src, $vm)) $veilCss = $vm[1];
preg_match('/z-index:\s*(\d+)/', $veilCss, $zm);
ok($zm && (int)$zm[1] > 200, 'it clears the navbar (z-index ' . ($zm ? $zm[1] : '?') . ')');

echo "\n" . ($fails ? "$fails FAILED\n" : "all passed\n");
exit($fails ? 1 : 0);
