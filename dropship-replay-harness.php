<?php
/**
 * dropship-replay-harness.php -- the instant replay, stored and stepped.
 *
 * Run: php dropship-replay-harness.php
 *
 * A replay is HTML written to the database at the end of a run and read
 * back verbatim later, then walked round by round in the browser. Two
 * things can go wrong quietly: the markup can be mangled on the way
 * through SQL, and the walk can pick up the wrong element.
 */

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}
function rsrc($rel) { return (string)@file_get_contents(__DIR__ . '/' . $rel); }
function rcode($rel) {
	return preg_replace(array('#/\*.*?\*/#s', '#^\s*//.*$#m'), '', rsrc($rel));
}

echo "\nthe markup survives the round trip through the database\n";
/*
 * AN HTML ESCAPER IS NOT AN SQL ESCAPER. The insert interpolated
 * htmlspecialchars($replay) straight into the statement, and the markup is
 * full of single quotes -- <div class='round' id='0'>. The only reason
 * every replay did not truncate at the first one is that PHP 8.1 changed
 * htmlspecialchars()'s DEFAULT to include ENT_QUOTES; on 8.0 it is
 * ENT_COMPAT, which leaves ' alone. This server picks its PHP version per
 * domain from a dropdown.
 */
$round = "<div class='round' id='0'><h3>Round 1 - Mask &amp; Hoodie</h3>"
       . "<img src='filthy-mermaid/images/live/beach/1.gif?var=123'/></div>";
$enc = htmlspecialchars($round, ENT_QUOTES);
ok(strpos($enc, "'") === false,
   'a single quote survives encoding, so the SQL string ends inside the markup');
ok(htmlspecialchars_decode($enc, ENT_QUOTES) === $round,
   'the encode/decode pair is not lossless');
/* MISMATCHED FLAGS ARE THE OTHER HALF: encode with ENT_QUOTES and decode
   with ENT_COMPAT and every quote stays a literal &#039;, which is a
   replay full of broken attributes rather than a missing one. */
ok(htmlspecialchars_decode($enc, ENT_COMPAT) !== $round,
   'ENT_COMPAT decoding is lossless, so this check is no longer proving anything');
$db = rcode('dropship/db.php');
ok(strpos($db, 'real_escape_string(htmlspecialchars($replay, ENT_QUOTES))') !== false,
   'the replay is not SQL-escaped; it relies on an html escaper and a default flag');
ok(strpos($db, 'htmlspecialchars_decode($row["replay"], ENT_QUOTES)') !== false,
   'the decode does not pin its flags, so it can stop matching the encode');
/* Both halves must name the same flag, or the pair drifts apart. */
ok(substr_count($db, 'ENT_QUOTES') >= 2, 'only one side of the pair pins its flags');

echo "\na round is found by position, never by a bare numeric id\n";
/*
 * Every round div carries id='0', '1', '2'... and those ids are NOT unique
 * on the dashboard: the Armory, the soldier cards and the results tables
 * all render elements whose id is a small database integer, and all of
 * them appear BEFORE #hidden-results. getElementById returns the first
 * match, so a round lookup could land on an item card -- which has no
 * <h3> and no <img>, throwing partway through the step and leaving the
 * caption and the picture frozen. Which ids collide depends on how many
 * items and soldiers a theme renders, which is why it varied by theme.
 */
$js = rcode('dropship/dropship.js');
ok(strpos($js, 'document.getElementById(currentRound') === false,
   'rounds are still looked up by bare numeric id, which collides with item and soldier ids');
ok(strpos($js, "box.querySelectorAll('.round')[n]") !== false,
   'the lookup is not scoped to the rounds container');
ok(strpos($js, "document.getElementById('hidden-results')") !== false,
   'the lookup does not start from #hidden-results');
/* ...and it must degrade rather than throw, or the fix is the same crash
   wearing a different hat. */
ok(strpos($js, 'return h3 ? h3.innerHTML : \'\';') !== false,
   'an unreadable round still dereferences null for its caption');
ok(strpos($js, 'return img ? img.src : \'\';') !== false,
   'an unreadable round still dereferences null for its image');
ok(strpos($js, 'var prev = roundEl(currentRound-1);') !== false,
   'hiding the previous round can still throw on a missing element');
ok(strpos($js, 'return roundCaption(currentRound).includes(terms);') !== false,
   'the audio match still reaches into the element itself and can throw');
/* One painter, not the same two lines in both branches. */
ok(substr_count($js, 'function showRound(') === 1, 'there is no single round painter');
ok(substr_count($js, 'showRound(currentRound)') === 2,
   'only one of the two branches paints through it');

echo "\na javascript fix actually reaches the browser\n";
/*
 * THIS IS WHY A FIX CAME AND WENT. dashboard.php asked for
 * "dropship.js?var=" . rand(0, 999) and the server sends
 * Cache-Control: max-age=604800 -- a thousand urls, each cached for a
 * WEEK. Every load rolled a die and landed either on a fresh copy or on
 * one from before the last deploy, so the same code appeared to work,
 * then not work, with nothing having changed. Every other page asked
 * with no buster at all and served a week-stale copy outright.
 *
 * Observed, not theorised: the live dashboard was running dropship.js
 * ?var=591 with none of the new functions in it, while the server was
 * serving the new file correctly.
 */
require_once __DIR__ . '/dropship/dropship-themes.php';
$url = dropshipAsset('dropship.js');
ok(strpos($url, '?v=') !== false, 'the asset url carries no cache buster at all');
ok(preg_match('/\?v=\d+$/', $url) === 1, "the buster is not a file timestamp: $url");
/* THE SAME FILE MUST GIVE THE SAME URL, or every page view is a fresh
   download and the cache header may as well not exist. */
ok(dropshipAsset('dropship.js') === $url, 'the url changes between calls, so nothing ever caches');
/* A missing file must not emit a bare "?v=" and have THAT cached. */
ok(dropshipAsset('definitely-not-here.js') === 'definitely-not-here.js',
   'a missing asset emits an empty buster, which then caches forever');
/* Nothing may ask for it by a random number again, and nothing may ask
   for it bare. */
foreach (glob(__DIR__ . '/dropship/*.php') as $page) {
	$psrc = rsrc('dropship/' . basename($page));
	if (strpos($psrc, 'dropship.js') === false) continue;
	/* A bare rand() AS the buster. header.php uses filemtime() with rand()
	   only as a fallback, and its comment records that the collision was
	   LIVE-TESTED for the stylesheet -- the same bug, found and fixed
	   there, and left in place for the javascript. */
	ok(preg_match('/\?var=<\?php echo rand\(/', $psrc) !== 1,
	   basename($page) . ' cache-busts with a random number, which collides and serves stale js');
	ok(strpos($psrc, 'src="dropship.js"') === false,
	   basename($page) . ' asks for dropship.js with no buster, so it serves a week-stale copy');
}

echo "\nthe rounds really are emitted in order, so position IS the round\n";
/*
 * The whole fix rests on this: if the run ever emitted them out of order,
 * or numbered them from something other than a straight counter, indexing
 * by position would quietly show the wrong round.
 */
$run = rsrc('dropship/dropship.php');
preg_match_all("/<div class='round' id='\"\.\\\$(\w+)\.\"'/", $run, $m);
ok(count($m[1]) > 0, 'no round divs found in the run; this check is proving nothing');
ok(count(array_unique($m[1])) === 1 && $m[1][0] === 'counter',
   'a round id comes from something other than $counter: ' . implode(', ', array_unique($m[1])));

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
