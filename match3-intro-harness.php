<?php
/*
 * match3-intro-harness.php — CLI only. Static. No database.
 *
 * REPORTED: new players do not know a tile slides the whole row or
 * column, play it as a neighbour swap, score badly, and some quit. It
 * was documented nowhere, and skullswap.php's own help actively taught
 * the misconception ("drag a tile to swap it with a neighbor").
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$lib   = (string) file_get_contents(__DIR__ . '/lib/match3-intro.php');
$view  = (string) file_get_contents(__DIR__ . '/lib/match3-intro-view.php');
$swap  = (string) file_get_contents(__DIR__ . '/skullswap.php');
$monst = (string) file_get_contents(__DIR__ . '/monstrocity.php');
$adm   = (string) file_get_contents(__DIR__ . '/admin-match3-intro.php');
$tabs  = (string) file_get_contents(__DIR__ . '/admin-lib.php');

echo "every board that slides says so, and only before a first run\n";
/*
 * THE COVERAGE CHECK THAT SHOULD HAVE BEEN WRITTEN FIRST.
 *
 * The first version of this asserted that only skullswap.php and
 * monstrocity.php contained `dragDirection`, and passed -- while
 * dhcarena.php had the identical mechanic under a different variable
 * name (drag.axis) and no intro at all. dhcbosses.php is `require
 * dhcarena.php`, so DHC Bosses was uncovered too, and Boss Battles is
 * monstrocity.php#boss. Reported by the owner, not by this file.
 *
 * An assertion keyed on ONE spelling of an implementation detail is not
 * a coverage check, it is a search. So: every player-facing page that
 * drags along an axis must mount the intro, whatever it calls its own
 * variables -- and a new spelling arriving is the one thing this still
 * cannot see, which is why the signature list is named and short rather
 * than clever.
 */
$m3_sig = '~dragDirection|drag\.axis|dragAxis~';
/* Not player-facing: a throwaway feel prototype, a test page, and this
   file, which names the signatures in order to look for them. */
$m3_skip = array('dhcarena-prototype.php', 'monstrocity-test.php', basename(__FILE__));
$m3_slide = array();
foreach (glob(__DIR__ . '/*.php') as $m3f) {
	$b = basename($m3f);
	if (in_array($b, $m3_skip, true)) continue;
	if (preg_match($m3_sig, (string) file_get_contents($m3f))) $m3_slide[] = $b;
}
sort($m3_slide);
ok($m3_slide === array('dhcarena.php', 'monstrocity.php', 'skullswap.php'),
   'the set of sliding boards changed: ' . implode(', ', $m3_slide)
 . ' -- a new one needs the intro, and a page that lost the mechanic must stop showing it');
foreach ($m3_slide as $b) {
	$src = (string) file_get_contents(__DIR__ . '/' . $b);
	ok(strpos($src, "require_once __DIR__ . '/lib/match3-intro.php'") !== false,
	   $b . ' slides but does not include the intro, so its new players meet nothing');
	ok(preg_match('~match3_intro\(array\(~', $src) === 1,
	   $b . ' includes the intro but never calls it');
	ok(preg_match('~match3_intro_marks\(\x27[a-z]+\x27\)~', $src) === 1,
	   $b . ' does not ask for a declared set of marks, so its gate is its own');
}
/* And the page that only borrows the Arena has to keep borrowing it,
   or DHC Bosses silently loses the intro the Arena mounts. */
$m3_bos = (string) file_get_contents(__DIR__ . '/dhcbosses.php');
ok(preg_match('~require __DIR__ \. \x27/dhcarena\.php\x27~', $m3_bos) === 1,
   'dhcbosses.php no longer runs dhcarena.php, so it needs an intro of its own');

/*
 * EACH GAME'S RECORD IS ITS OWN, AND DECLARED IN ONE PLACE.
 *
 * Skull Swap and Monstrocity write a row in `scores`; the Arena writes
 * a battle, DHC Bosses a run. match3_intro_marks() holds all of them so
 * three pages and this file cannot disagree about what "has played"
 * means. No flag, no cookie, no session key: the row the game already
 * writes IS the record, which is why nothing is written on dismiss and
 * there is nothing to go stale.
 */
foreach (array('skullswap' => 'scores', 'monstrocity' => 'scores',
               'dhcarena' => 'dhc_arena_battles') as $k => $table) {
	ok(preg_match('~\x27' . $k . '\x27\s*=> array\(array\(\x27from\x27 => \x27' . $table . '~', $lib) === 1,
	   'match3_intro_marks(\'' . $k . '\') does not read ' . $table);
}
/* The Arena's two modes share a board, so either one counts for both. */
ok(preg_match('~\x27dhcarena\x27.*?dhc_arena_battles.*?dhc_boss_runs~s', $lib) === 1,
   'the Arena gate does not count a Boss run, so a Boss player meets the intro again in the Arena');
ok(strpos($lib, 'INSERT') === false && strpos($lib, 'UPDATE') === false,
   'the gate writes, so dismissing the intro now records something that can go stale');
ok(strpos($view, 'localStorage') === false && strpos($view, 'document.cookie') === false,
   'the overlay remembers itself in the browser, so it is a second record of the same fact');
/* A table or column name reaching a query unfiltered is how a list of
   marks becomes an injection point the day somebody builds one from
   input. */
ok(preg_match("~preg_replace\('~\[\^a-z0-9_\]~i', '', \(string\)~", $lib) === 1
   || preg_match('~preg_replace\(.\~\[\^a-z0-9_\]\~i., .., \(string\)~', $lib) === 1,
   'the table and column names are not filtered before they are interpolated');
/*
 * A FAILED READ MUST NOT NAG A VETERAN. The default is PLAYED, and a
 * query that cannot be read moves it nowhere.
 */
ok(preg_match('~\$seen\[\$key\] = true;~', $lib) === 1
   && preg_match('~if \(!\$r\) continue;~', $lib) === 1,
   'a failed read does not default to played, so a database hiccup shows the intro to everybody');
ok(preg_match('~if \(\$uid <= 0\) return true;~', $lib) === 1,
   'a logged-out visitor gets the overlay, which is in front of a game they cannot play anyway');

echo "\nthe demo shows the real rule, not a simplification\n";
/*
 * slideTiles() ROTATES THE SPAN you drag across: the grabbed tile lands
 * where you let go and everything between steps one place back. The
 * demo has to do exactly that, or it teaches a rule the board does not
 * have.
 *
 * AND IT HAS TO TRAVEL MORE THAN ONE CELL. At one cell a slide and a
 * neighbour swap are indistinguishable, so a one-cell demo would
 * quietly confirm what the player already believed.
 */
/*
 * TWO MOVES, ONE PER AXIS, BOTH EDGE TO EDGE.
 *
 * One move along one axis leaves the other to be guessed at, and
 * players not guessing is the whole problem. And the DISTANCE is the
 * lesson: the first version travelled two cells, which the owner called
 * correctly -- a short hop still reads as a swap with extra steps,
 * where a tile crossing the whole board cannot be mistaken for one.
 */
$m3_moves = array();
if (preg_match_all('~\{ axis: \x27(row|col)\x27, line: (\d+), from: (\d+), to: (LAST|\d+)~', $view, $mm, PREG_SET_ORDER)) {
	foreach ($mm as $one) $m3_moves[] = $one;
}
ok(count($m3_moves) === 2, 'the demo declares ' . count($m3_moves) . ' move(s), not one per axis');
$m3_axes = array();
foreach ($m3_moves as $one) $m3_axes[] = $one[1];
sort($m3_axes);
ok($m3_axes === array('col', 'row'),
   'the demo does not show both axes (' . implode(', ', $m3_axes) . '), so one of them is left to be guessed');
$m3_last = preg_match('~var LAST = (\d+);~', $view, $lm) ? (int) $lm[1] : -1;
ok($m3_last === 4, 'LAST is not the far edge of a five-wide board, so "edge to edge" is not what it means');
foreach ($m3_moves as $one) {
	ok((int) $one[3] === 0 && $one[4] === 'LAST',
	   'the ' . $one[1] . ' move runs ' . $one[3] . ' to ' . $one[4]
	 . ' rather than edge to edge, and a short slide still reads as a swap');
}
/* The tiles it passes must step back, which is the half of the rule a
   player cannot see from the result alone. */
ok(preg_match('~for \(var i = mv\.from \+ 1; i <= mv\.to; i\+\+\) put\(tileOf\(mv, i\), cellOf\(mv, i - 1\)\);~', $view) === 1,
   'the demo moves the grabbed tile without shifting what it passed, which is not what the board does');
/*
 * AND THE BOARD IS SOLVED FOR EXACTLY THOSE TWO MOVES. Searched under
 * three conditions: no three-in-a-row to begin with, row 1 dragged
 * x0->x4 lands exactly one match, column 1 dragged y0->y4 lands exactly
 * one. Exactly one each, because a second accidental match would fire
 * where the demo never highlights and read as the board misbehaving.
 *
 * Re-solved here rather than trusted, so editing a tile for looks fails
 * instead of quietly turning the demonstration into a lie.
 */
$m3_rows = array();
if (preg_match_all("~array\('([cqr])', '([cqr])', '([cqr])', '([cqr])', '([cqr])'\),~", $view, $rm, PREG_SET_ORDER)) {
	foreach ($rm as $r) $m3_rows[] = array_slice($r, 1);
}
ok(count($m3_rows) === 5, 'the demo board is not five rows of five');
if (count($m3_rows) === 5) {
	$runs = function ($b) {
		$out = array();
		for ($y = 0; $y < 5; $y++) for ($x = 0; $x < 3; $x++)
			if ($b[$y][$x] === $b[$y][$x+1] && $b[$y][$x] === $b[$y][$x+2]) $out[] = "h$x,$y";
		for ($x = 0; $x < 5; $x++) for ($y = 0; $y < 3; $y++)
			if ($b[$y][$x] === $b[$y+1][$x] && $b[$y][$x] === $b[$y+2][$x]) $out[] = "v$x,$y";
		return $out;
	};
	$rot = function ($b, $axis, $line) {
		$v = array();
		for ($i = 0; $i < 5; $i++) $v[] = $axis === 'row' ? $b[$line][$i] : $b[$i][$line];
		$first = array_shift($v); $v[] = $first;          /* 0 -> 4 */
		for ($i = 0; $i < 5; $i++) {
			if ($axis === 'row') $b[$line][$i] = $v[$i]; else $b[$i][$line] = $v[$i];
		}
		return $b;
	};
	ok(count($runs($m3_rows)) === 0,
	   'the demo board already contains a match, so it opens on a board the game would have cleared');
	$m3_r = $runs($rot($m3_rows, 'row', 1));
	ok($m3_r === array('h2,1'),
	   'the row move lands ' . (count($m3_r) ? implode(' + ', $m3_r) : 'nothing')
	 . ' instead of exactly one match at x2..x4');
	$m3_c = $runs($rot($m3_rows, 'col', 1));
	ok($m3_c === array('v1,2'),
	   'the column move lands ' . (count($m3_c) ? implode(' + ', $m3_c) : 'nothing')
	 . ' instead of exactly one match at y2..y4');
}
/* No images: they deploy by FTP outside this repo, and a teaching
   overlay that 404s teaches nothing. */
/* The headline names BOTH axes. Removing an assumption is the entire
   job, so a title that mentions one of them is the bug in the headline. */
ok(stripos($view, 'id="m3i-title">') !== false
   && preg_match('~id="m3i-title">[^<]*\brow or column\b~i', $view) === 1,
   'the title names one axis, which leaves the other to be assumed -- the thing this exists to stop');
ok(strpos($view, '<img') === false && strpos($view, 'url(') === false,
   'the overlay loads an image, which deploys separately and can leave it blank');
/* Reduced motion keeps the board and drops the loop. */
ok(preg_match('~prefers-reduced-motion: reduce~', $view) === 1
   && strpos($view, 'if (calm)') !== false,
   'the demo loops forever under reduced motion, which is the preference it is meant to honour');

echo "\nthe admin can see it, and the games say the same thing\n";
ok(strpos($tabs, "'match3-intro' => array('admin-match3-intro.php'") !== false,
   'there is no admin tab, so the overlay cannot be reached once you have played');
/* The preview renders the REAL partial, so what is reviewed is what
   ships -- the same rule admin-chest.php follows. */
ok(strpos($adm, "require_once __DIR__ . '/lib/match3-intro.php'") !== false
   && strpos($adm, "'force' => true") !== false,
   'the preview reimplements the overlay instead of forcing the real one');
ok(strpos($adm, 'INSERT') === false && strpos($adm, 'UPDATE') === false,
   'the preview writes something');
/*
 * AND THE GAME'S OWN HELP MUST NOT CONTRADICT IT. skullswap.php told
 * players to "swap it with a neighbor" in two places, which is the
 * misconception being fixed, printed by the game itself.
 */
ok(stripos($swap, 'swap it with a neighbor') === false
   && stripos($swap, 'swap it with a neighbour') === false,
   'the game still tells players to swap with a neighbour, which is the belief this exists to correct');
/* ALL FIVE PAGES. Three engines, five pages a player can land on, and
   a rule documented on some of them is a rule somebody reads around. */
foreach (array('games-skull-swap.md', 'games-monstrocity.md', 'games-dhc-arena.md',
               'games-dhc-bosses.md', 'games-boss-battles.md') as $doc) {
	$d = (string) file_get_contents(__DIR__ . '/skullpaper/' . $doc);
	ok(stripos($d, 'slides the whole row') !== false,
	   $doc . ' does not document the slide, which is where it was missing in the first place');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
