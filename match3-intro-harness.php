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

echo "it reaches both games, and only before a first run\n";
/*
 * BOTH, OR IT IS HALF A FIX. The slide is the same mechanic in both --
 * they are the only two files in the repo carrying dragDirection -- and
 * a player who meets it in one and not the other learns it twice or not
 * at all.
 */
foreach (array('skullswap.php' => array($swap, 0, 'Skull Swap'),
               'monstrocity.php' => array($monst, 36, 'Monstrocity')) as $file => $g) {
	list($src, $pid, $name) = $g;
	ok(strpos($src, "require_once __DIR__ . '/lib/match3-intro.php'") !== false,
	   $file . ' does not include the intro, so its new players meet nothing');
	ok(preg_match('~match3_intro\(array\(\x27game\x27 => \x27' . preg_quote($name, '~')
	   . '\x27, \x27project\x27 => ' . $pid . '\)\)~', $src) === 1,
	   $file . ' does not ask for its own game name and project id, so the gate reads the wrong game');
	/* The mechanic is what the intro is ABOUT, so a game that stops
	   having it should stop showing it -- and a third game that grows
	   one should be noticed here rather than by a player. */
	ok(strpos($src, 'dragDirection') !== false,
	   $file . ' no longer has the slide mechanic, so the intro is teaching a rule it does not have');
}
/* And nothing else does, which is what makes "both" the whole set. */
$m3_others = array();
foreach (glob(__DIR__ . '/*.php') as $m3f) {
	$b = basename($m3f);
	/* And not this file, which names the mechanic in order to check for
	   it -- the same prose-matching-itself trap as searching a stylesheet
	   for a property its own comment explains. */
	if (in_array($b, array('skullswap.php', 'monstrocity.php', 'monstrocity-test.php',
	                       basename(__FILE__)), true)) continue;
	if (strpos((string) file_get_contents($m3f), 'dragDirection') !== false) $m3_others[] = $b;
}
ok(count($m3_others) === 0,
   'these also have the slide and no intro: ' . implode(', ', $m3_others));

/*
 * THE GATE IS THE SCORE, AND NOTHING ELSE.
 *
 * No flag, no cookie, no session key: a row in `scores` for that
 * project is the record that the player has played. That is why
 * nothing is written on dismiss, and why there is nothing to reset.
 */
/* /s: the query is wrapped across two lines, and without it the dot
   stops at the newline and reports a correct gate as a missing one. */
ok(preg_match('~FROM scores WHERE user_id = .*? AND project_id = ~s', $lib) === 1,
   'the gate does not read scores, so it is keeping a second record of who has played');
ok(strpos($lib, 'INSERT') === false && strpos($lib, 'UPDATE') === false,
   'the gate writes, so dismissing the intro now records something that can go stale');
ok(strpos($view, 'localStorage') === false && strpos($view, 'document.cookie') === false,
   'the overlay remembers itself in the browser, so it is a second record of the same fact');
/*
 * A FAILED READ MUST NOT NAG A VETERAN. The default is PLAYED: missing
 * the intro once costs nothing, putting a dialog over a returning
 * player's board costs more than it ever saves.
 */
ok(preg_match('~\$seen\[\$key\] = true;\s*\n\s*\$r = @\$conn->query~', $lib) === 1,
   'a failed read does not default to played, so a database hiccup shows the intro to everybody');
ok(preg_match('~if \(\$uid <= 0\) return true;~', $lib) === 1,
   'a logged-out visitor gets the overlay, which is in front of a game they cannot score in anyway');

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
ok(preg_match('~var ROW = (\d+), FROM = (\d+), TO = (\d+);~', $view, $m) === 1,
   'the demo has no declared move, so what it teaches cannot be checked');
if (!empty($m)) {
	ok((int)$m[3] - (int)$m[2] >= 2,
	   'the demo drags ' . ((int)$m[3] - (int)$m[2]) . ' cell(s); at one cell a slide and a '
	 . 'neighbour swap look identical, so it teaches nothing');
	/* The tiles it passes must shift back, which is the half of the
	   rule a player cannot see from the result alone. */
	ok(preg_match('~for \(var x = FROM \+ 1; x <= TO; x\+\+\) place\(tiles\[x \+ \x27,\x27 \+ ROW\], x - 1, ROW\);~', $view) === 1,
	   'the demo moves the grabbed tile without shifting what it passed, which is not what the board does');
}
/*
 * AND THE MOVE HAS TO LAND A MATCH THE OTHER MODEL CANNOT REACH. The
 * row is c r q c c: dragging the circle from 0 to 2 leaves r q c c c,
 * three circles in a line. Swapping neighbours from x=0 never gets
 * there, which is the entire argument the demo is making.
 */
ok(preg_match("~array\('c', 'r', 'q', 'c', 'c'\)~", $view) === 1,
   'the demonstration row changed; the move may no longer land a match, which makes the demo a lie');
/* No images: they deploy by FTP outside this repo, and a teaching
   overlay that 404s teaches nothing. */
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
foreach (array('games-skull-swap.md', 'games-monstrocity.md') as $doc) {
	$d = (string) file_get_contents(__DIR__ . '/skullpaper/' . $doc);
	ok(stripos($d, 'slides the whole row') !== false,
	   $doc . ' does not document the slide, which is where it was missing in the first place');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
