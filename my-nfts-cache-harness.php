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

echo "\n-- A TIMEOUT STOPS THE RUN. It must never advance it. --\n";
/*
 * THIS ONE TOOK THE SITE DOWN, so it is the assertion that matters most in
 * this file.
 *
 * record() moves to the next NFT. Calling it from ontimeout meant the
 * browser abandoned a request THE SERVER WAS STILL WORKING ON and
 * immediately started another: thirteen timeouts left thirteen requests
 * running at once, PHP-FPM ran out of workers, and the site answered 503.
 * The loop was serial only while the server kept up.
 *
 * Abandoning a request says nothing about whether it finished, so the only
 * safe reading is that work is still in flight -- halt, and say so.
 */
ok(strpos($run, 'xhr.ontimeout') !== false, 'a timeout is handled');
ok(preg_match('/ontimeout\s*=\s*function[^}]*halt\(/s', $run),
   'and it HALTS');
ok(!preg_match('/ontimeout\s*=\s*function[^}]*record\(/s', $run),
   '...it does not record-and-continue, which is what piled requests up');
ok(preg_match('/onerror\s*=\s*function[^}]*halt\(/s', $run),
   'a request that never arrived halts too');
ok(strpos($run, 'function halt(') !== false, 'halt exists');
ok(preg_match('/function halt\([^)]*\)\s*\{[^}]*stopped = true;/s', $run),
   'and it sets the stop flag rather than calling step()');
ok(!preg_match('/function halt\([^)]*\)\s*\{[^}]*step\(\);/s', $run),
   '...never step()');
ok(strpos($run, 'if (stopped) { finish(); return; }') !== false,
   'and step() refuses to run once stopped');

echo "\n-- the client is the LAST thing to give up --\n";
/* If the browser can time out before the server finishes, abandoning is
   possible at all. It must not be, in normal operation. */
preg_match('/xhr\.timeout = (\d+);/', $run, $ct);
$client_ms = $ct ? (int)$ct[1] : 0;
$ep2 = file_get_contents(__DIR__ . '/ajax/cache-nft-image.php');
preg_match('/set_time_limit\((\d+)\)/', $ep2, $sl);
$server_s = $sl ? (int)$sl[1] : 0;
preg_match('/^\s*(\d+)\s*\/\/ max_fetch_seconds/m', $ep2, $mf);
$fetch_s = $mf ? (int)$mf[1] : 0;
ok($client_ms > 0 && $server_s > 0, "both caps are readable (client {$client_ms}ms, server {$server_s}s)");
ok($client_ms > $server_s * 1000,
   "the browser waits longer than the server's cap ({$client_ms}ms > {$server_s}000ms)");
ok($fetch_s > 0 && $fetch_s < $server_s,
   "and the fetch budget sits inside it ({$fetch_s}s < {$server_s}s)");

echo "\n-- the server keeps a budget it can actually enforce --\n";
/* set_time_limit does not count time outside PHP, so neither curl nor
   Imagick was ever bounded by it. */
$icl = harness_strip_comments(file_get_contents(__DIR__ . '/lib/image-cache-lib.php'));
ok(strpos($icl, '$_fetch_started = microtime(true);') !== false,
   'the library times its own fetch');
ok(strpos($icl, 'microtime(true) - $_fetch_started) > $budget') !== false,
   'and checks the budget BEFORE starting Imagick, which set_time_limit cannot bound');
$ii = strpos($icl, '> $budget');
$ij = strpos($icl, 'new Imagick()', $ii);
ok($ii !== false && $ij !== false && $ii < $ij, 'the check comes first');
ok(strpos(substr($icl, $ii, $ij - $ii), 'file_put_contents') !== false,
   'and it still writes the bytes rather than giving up -- an unresized copy beats none');

echo "\n-- and it can always be called off --\n";
ok(strpos($src, 'id="mn-cache-stop"') !== false, 'there is a stop button');
ok(strpos($run, 'stopBtn.hidden = false;') !== false, 'shown while a run is going');
ok(strpos($run, 'stopped = true; stopBtn.hidden = true;') !== false, 'and it stops the run');
ok(strpos($run, 'streak >= 3') !== false,
   'three consecutive failures also stop it, rather than grinding through two dozen');

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

echo "\n-- the NFT panel --\n";
/*
 * A card used to go straight to a block explorer. The panel takes the click,
 * but the anchor and its href must survive: middle-click, "open in new tab"
 * and a visitor with no JavaScript all still have to land somewhere.
 */
ok(strpos($src, 'data-nft-open=') !== false, 'cards are marked as openable');
/* Read the whole opening tag rather than matching attribute values: the
   values are PHP echoes containing their own double quotes, so [^"]* stops
   inside one and the pattern never matches whatever the markup says. */
$ai = strpos($src, '<a class="nft-art"');
$aj = strpos($src, 'rel="noopener"', $ai);
$tag = ($ai !== false && $aj !== false) ? substr($src, $ai, $aj - $ai) : '';
ok($tag !== '', 'the card anchor is there to read');
ok(strpos($tag, 'data-nft-open=') !== false, 'it is marked as openable');
ok(strpos($tag, 'href="') !== false, 'and still carries a real href to the explorer');
ok(strpos($tag, 'nftExplorerUrl(') !== false, 'built by the chain-aware helper');
ok(strpos($src, 'nftExplorerUrl(') !== false, 'which is the chain-aware one, not a hardcoded pool.pm');
$pi = strpos($src, "document.addEventListener('click'");
$pj = strpos($src, '})();', $pi);
$panel = substr($src, $pi, $pj - $pi);
ok($panel !== '', 'the panel handler is there to read');
ok(strpos($panel, 'e.metaKey || e.ctrlKey || e.shiftKey || e.altKey') !== false,
   'a modified click is left alone, so open-in-new-tab still works');
ok(strpos($panel, 'e.button !== 0') !== false, 'and so is a middle click');
ok(strpos($panel, 'e.preventDefault()') !== false, 'a plain click opens the panel instead');
ok(strpos($panel, 'closest(') !== false, 'bound once by delegation, not per card');

echo "\n-- it asks the endpoint, which decides who may look --\n";
ok(strpos($panel, 'ajax/nft-dossier.php?nft_id=') !== false, 'the panel fetches the dossier');
$dos = file_get_contents(__DIR__ . '/ajax/nft-dossier.php');
$lib = file_get_contents(__DIR__ . '/lib/nft-dossier.php');
ok(strpos($dos, "\$_SESSION['userData']['user_id']") !== false, 'the endpoint knows who is asking');
/*
 * NOT "the word visibility appears" -- that stayed true with the gate
 * commented out. What matters is that the not-mine branch actually REFUSES:
 * a comparison against the public setting, and an exit behind it.
 */
$bi = strpos($dos, 'if (!$mine) {');
$bj = strpos($dos, "\n}", $bi);
$gate = ($bi !== false && $bj !== false) ? substr($dos, $bi, $bj - $bi) : '';
ok($gate !== '', 'the not-mine branch is there to read');
ok(preg_match('/\$vis\s*!=\s*2|\$vis\s*!==\s*2/', $gate),
   "it compares the owner's visibility against the public setting");
ok(strpos($gate, 'exit;') !== false, 'and exits rather than falling through');
ok(preg_match('/\$vis[^;]*\)\s*\{[^}]*exit;/s', $gate),
   'with the exit INSIDE the check, not merely nearby');
ok(strpos($gate, 'private') !== false, 'and says why');
ok(strpos($dos, 'SELECT visibility FROM users') !== false, 'read from the owner, not the caller');
ok(strpos($lib, 'visibility') === false,
   'while the library does NOT -- one place decides, so the two cannot disagree');
ok(strpos($lib, 'INSERT') === false && strpos($lib, 'UPDATE') === false
   && strpos($lib, 'DELETE') === false, 'and the library only reads');
/* Every surface the user asked to see. */
foreach (array('missions_nfts' => 'missions', 'gauntlets_nfts' => 'gauntlets',
               'diamond_skulls' => 'diamond skulls', 'soldiers' => 'realms') as $tbl => $what) {
	ok(strpos($lib, $tbl) !== false, "  it reads $what ($tbl)");
}
ok(strpos($lib, 'function _nftd_rows') !== false, 'through one guarded reader');
ok(strpos($lib, 'if (!$res) return array();') !== false,
   'which survives a table this deployment does not have');

echo "\n-- and it is the big view of the artwork --\n";
ok(strpos($src, '.nftd-art img{max-width:100%;max-height:58vh') !== false,
   'the art leads, bounded by the viewport so a tall piece does not push the rest off');
ok(strpos($panel, 'd.image') === false && strpos($src, 'esc(d.image)') !== false,
   'drawn from the resolved url the grid uses');
ok(strpos($src, "this.src=\\'/staking/icons/skull.png\\'") !== false,
   'with the same skull fallback as everywhere else');

echo "\n-- every file that calls a shared helper also loads it --\n";
/*
 * THIS ONE SHIPPED, AND IT BROKE THE NIGHTLY.
 *
 * lib/image-cache-lib.php has always called ipfs_gateways() and never
 * required the file that defines it. It survived because db.php requires
 * that file INSIDE ensureNFTImageCached(), so anything which happened to
 * call that first had the function by luck -- and the nightly pass
 * (image-cache.php) calls neither, so every NFT with an IPFS CID died on an
 * undefined function. The pass was not "missing some"; it was failing on
 * all of them bar the self-hosted-URL rows.
 *
 * A dependency satisfied by whoever happens to include you first is not
 * satisfied. The rule: a file that CALLS it must REQUIRE it.
 */
require_once __DIR__ . '/harness-strip.php';
$shared = array(
	'ipfs_gateways'  => 'lib/ipfs-gateways.php',
);
foreach ($shared as $fn => $home) {
	$callers = array();
	foreach (glob(__DIR__ . '/*.php') as $f) $callers[] = $f;
	foreach (glob(__DIR__ . '/lib/*.php') as $f) $callers[] = $f;
	foreach (glob(__DIR__ . '/ajax/*.php') as $f) $callers[] = $f;
	$checked = 0;
	foreach ($callers as $f) {
		$name = str_replace(__DIR__ . '/', '', $f);
		if (substr($name, -12) === '-harness.php') continue;   // harnesses assert about it
		if ($name === $home) continue;                          // the definition itself
		$code = harness_strip_comments(file_get_contents($f));
		if (strpos($code, $fn . '()') === false) continue;       // does not call it
		$checked++;
		ok(preg_match('~require(_once)?\s*.{0,40}' . preg_quote(basename($home), '~') . '~', $code) === 1,
		   "  $name calls $fn() and requires " . basename($home));
	}
	ok($checked > 0, "found $checked callers of $fn() to check");
}

echo "\n-- drilling into one collection --\n";
$db = harness_strip_comments(file_get_contents(__DIR__ . '/db.php'));

/* The filter has to reach the QUERY. Narrowing in PHP after the fact would
   page wrongly: the pager counts rows the query returned. */
ok(strpos($db, '$collection_id = 0) {') !== false
   || strpos($db, '$collection_id = 0){') !== false,
   'getNFTsData takes a collection, last and optional');
ok(strpos($db, '$collection_id = 0){') !== false
   || strpos($db, '$collection_id = 0)') !== false,
   'and so does countNFTs, or the pager offers pages that are not there');
ok(substr_count($db, 'nfts.collection_id = " . $coll') === 2,
   'both queries narrow on it (' . substr_count($db, 'nfts.collection_id = " . $coll') . ')');
/* WHERE "" . " AND x" is a syntax error, not a filter. */
ok(substr_count($db, '$lead = ($user_filter !== ""') === 2,
   'and neither can emit a WHERE that opens with AND');
ok(strpos($src, 'countNFTs($conn, $filterby, "", $mn_coll)') !== false,
   'the page counts with the same filter it lists with');
ok(strpos($src, 'getNFTsData($conn, $filterby, "", $mn_page, $mn_per, $mn_coll)') !== false,
   '...and lists with it');

echo "\n-- the control only appears when it has something to do --\n";
ok(strpos($src, 'if (count($mn_colls) > 1):') !== false,
   'hidden unless the project holds more than one of this staker\'s collections');
ok(strpos($src, '$mn_proj  = ($filterby !== "" && $filterby !== "None"') !== false,
   'and the list is only built once a project is chosen');
ok(strpos($src, 'WHERE n.user_id = $uid AND c.project_id = $mn_proj') !== false,
   'built from what the staker ACTUALLY HOLDS, not every collection in the project');
ok(strpos($src, 'if ($mn_coll > 0 && !isset($mn_colls[$mn_coll])) $mn_coll = 0;') !== false,
   'an id they hold nothing in is dropped rather than obeyed');
ok(strpos($src, "COUNT(*) AS n") !== false, 'each option carries how many they hold');

echo "\n-- it survives paging and does not strand the other filters --\n";
ok(strpos($src, "\$cq = \$mn_coll > 0 ? '&collection=' . \$mn_coll : '';") !== false,
   'the collection rides along in the pager');
ok(substr_count($src, '$fb . $cq') === 2, 'on both Prev and Next');
ok(strpos($src, 'name="filterby" value="<?php echo htmlspecialchars($filterby); ?>"') !== false,
   'and the project survives changing the collection');
ok(strpos($src, 'method="get"') !== false, 'GET, so a drilled-down view is a link');
/* Changing the project must NOT keep the old collection -- it belongs to a
   project it is no longer in. The shared form posts only filterby, which is
   what makes that true, so it is worth pinning. */
$sk = file_get_contents(__DIR__ . '/skulliance.php');
$fi = strpos($sk, 'function filterNFTs(');
$fj = strpos($sk, "\n}", $fi);
$ff = substr($sk, $fi, $fj - $fi);
ok(strpos($ff, 'name="collection"') === false,
   'the project form carries no collection, so switching project clears it');
/* Not "the variable is mentioned" -- that stayed true with the parameter
   dropped and a local set to empty. The options have to actually emit
   `selected` from a comparison against it. */
ok(strpos($ff, "\$method='post', \$selected=\"\"") !== false,
   'filterNFTs takes the current selection, last and optional');
ok(substr_count($ff, "(string)\$selected === (string)\$id") === 2,
   'both project groups compare against it (' . substr_count($ff, "(string)\$selected === (string)\$id") . ')');
ok(substr_count($ff, "' selected'") === 2, "and emit ' selected' when it matches");
ok(strpos($ff, '($selected === "" || $selected === "None") ? " selected" : ""') !== false,
   'and "All" is selected when nothing is filtered');
ok(strpos($src, 'filterNFTs("my-nfts", "", "get", $filterby)') !== false,
   'which this page passes');

echo "\n" . ($fails ? "$fails FAILED\n" : "all passed\n");
exit($fails ? 1 : 0);
