<?php
/**
 * dropship-share-harness.php — a shared run must not claim what it did not do.
 *
 * Run: php dropship-share-harness.php
 *
 * THE POST IS A PUBLIC CLAIM ABOUT OTHER PEOPLE. "Passed fomz into 2nd" is
 * either true or it is a lie with somebody's name attached, so the facts are
 * driven over real boards rather than described: the actual
 * dropshipShareFacts() and dropshipShareText(), over standings this file
 * builds, with the awkward cases written out one at a time.
 *
 * THE RULE, from the owner: name whoever this run PASSED, and if it passed
 * nobody, name the leader being chased. Nobody is ever named as having
 * beaten the sharer.
 */
require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/dropship/dropship-share.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return true; }
	$fail++; echo "  FAIL  $msg\n"; return false;
}
/** A board, highest first. ME is user 1. */
function board() {
	return array(
		array('user_id' => 9, 'username' => 'fomz',     'score' => 51),
		array('user_id' => 3, 'username' => 'Scorba',   'score' => 40),
		array('user_id' => 1, 'username' => 'me',       'score' => 34),
		array('user_id' => 7, 'username' => 'kryptman', 'score' => 22),
		array('user_id' => 5, 'username' => 'maxi',     'score' => 12),
	);
}

echo "\nwhere you stand\n";
$f = dropshipShareFacts(board(), 1, null);
ok($f['rank'] === 3,  'the rank is ' . $f['rank'] . ', not 3rd');
ok($f['field'] === 5, 'the field is ' . $f['field'] . ', not 5');
ok($f['score'] === 34, 'the score read back as ' . $f['score']);
ok($f['leader'] === 'fomz' && $f['behind'] === 17,
   'the leader and the gap are wrong: ' . $f['leader'] . ' / ' . $f['behind']);

echo "\nentering the board passes nobody\n";
/*
 * THE ONE CLAIM THAT IS EASY TO CATCH AND IMPOSSIBLE TO WALK BACK. A first
 * run lands above two players, and saying it "passed" them is a lie -- they
 * were never ahead of anything. prev = null is the whole test.
 */
ok($f['passed'] === '' && $f['passed_n'] === 0,
   'a first run claims to have passed ' . $f['passed_n'] . ' player(s)');
ok(strpos(dropshipShareText($f, 'Dread City'), 'Passed') === false,
   'the post for a first run says Passed');
ok(strpos(dropshipShareText($f, 'Dread City'), 'behind fomz') !== false,
   'a run with nobody passed does not name the leader it is chasing');

echo "\nand a run that passed somebody names them\n";
$f = dropshipShareFacts(board(), 1, 22);   // was level with kryptman, now above
ok($f['passed'] === 'kryptman' && $f['passed_n'] === 1,
   'expected to have passed kryptman alone, got ' . $f['passed_n'] . ' (' . $f['passed'] . ')');
$f2 = dropshipShareFacts(board(), 1, 10);  // was below both
ok($f2['passed_n'] === 2 && $f2['passed'] === 'kryptman',
   'the highest player passed is not the one named: ' . $f2['passed'] . ' of ' . $f2['passed_n']);
ok(strpos(dropshipShareText($f2, 'Drop Ship'), 'Passed kryptman and 1 more into 3rd of 5.') !== false,
   'the overtake sentence changed: ' . dropshipShareText($f2, 'Drop Ship'));

echo "\na run that went nowhere claims nothing\n";
/* Scores are the LATEST, not the best -- logScore() deletes and reinserts --
   so a worse run than last time is normal and must not boast. */
$f = dropshipShareFacts(board(), 1, 34);
ok($f['passed_n'] === 0, 'an unchanged score claims an overtake');
ok($f['personal_best'] === false, 'an unchanged score reads as a personal best');
$f = dropshipShareFacts(board(), 1, 48);
ok($f['passed_n'] === 0, 'a WORSE run than last week claims to have passed people');
ok($f['personal_best'] === false, 'a worse run reads as a personal best');
ok(strpos(dropshipShareText($f, 'Drop Ship'), 'personal best') === false,
   'the post for a worse run still says personal best');

echo "\nnobody is named as having beaten you\n";
/*
 * Only two names can ever reach the text: somebody this run went past, and
 * the leader. Scorba is directly above and is neither, so a post that
 * mentions them is naming a player who beat the sharer.
 */
foreach (array(null, 0, 10, 22, 34, 48) as $prev) {
	$t = dropshipShareText(dropshipShareFacts(board(), 1, $prev), 'Drop Ship');
	if (!ok(strpos($t, 'Scorba') === false,
	        'prev=' . var_export($prev, true) . ' names a player who is ahead: ' . $t)) break;
}

echo "\nout in front\n";
$lead = array(array('user_id' => 1, 'username' => 'me', 'score' => 61),
              array('user_id' => 9, 'username' => 'fomz', 'score' => 51));
$f = dropshipShareFacts($lead, 1, 40);
ok($f['first'] === true && $f['clear'] === 10, 'leading by ' . $f['clear'] . ', expected 10');
ok(strpos(dropshipShareText($f, 'Dread City'), 'Passed fomz into 1st of 2.') !== false,
   'taking the lead does not say who was overtaken: ' . dropshipShareText($f, 'Dread City'));
$alone = array(array('user_id' => 1, 'username' => 'me', 'score' => 61));
$f = dropshipShareFacts($alone, 1, null);
$t = dropshipShareText($f, 'Drop Ship');
ok(strpos($t, 'Survived to round 61 in Drop Ship. Top of the board.') === 0,
   'the only player on the board gets: ' . $t);
ok(strpos($t, 'clear of second') === false, 'a one-player board boasts about a gap to nobody');

echo "\nnot on the board at all\n";
$f = dropshipShareFacts(board(), 999, null);
ok($f['rank'] === 0, 'a player with no row has a rank');
$t = dropshipShareText($f, 'Drop Ship');
ok(strpos($t, 'round 0') === false && strpos($t, '0th') === false,
   'a player with no row gets a post full of zeroes: ' . $t);
ok(strpos($t, 'Drop Ship') !== false, 'a player with no row is not even told which game: ' . $t);
ok(dropshipShareFacts(array(), 1, 5)['rank'] === 0, 'an empty board produces a rank');

echo "\nordinals\n";
foreach (array(1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', 11 => '11th',
               12 => '12th', 13 => '13th', 21 => '21st', 22 => '22nd', 101 => '101st') as $n => $want) {
	if (!ok(dropshipOrdinal($n) === $want,
	        "$n reads as " . dropshipOrdinal($n) . ', not ' . $want)) break;
}

echo "\nthe theme is named, because five games share this engine\n";
ok(strpos(dropshipShareText(dropshipShareFacts(board(), 1, null), 'Oculus Lounge'), 'Oculus Lounge') !== false,
   'the post does not say which game it was');
/* Themes rename the word too. */
ok(strpos(dropshipShareText(dropshipShareFacts(board(), 1, null), 'Oculus Lounge', 'Floor'), 'floor 34') !== false,
   "a theme's own word for a round is ignored");

echo "\nthe post sells the game, not just the score\n";
/*
 * THE FIRST VERSION WAS A REPORT CARD. "Survived to round 28 in DHC2
 * Fighters. Top of the board, 13 clear of second." is true and tells a
 * stranger scrolling past nothing: not what the game is, not that it
 * costs nothing, not why they would click. A share is free reach and
 * that spent it on a number. Every post now carries a pitch and an
 * invitation as well as the result.
 */
$shapes = array(
	'leading'  => array(array(array('user_id' => 1, 'username' => 'me', 'score' => 28),
	                          array('user_id' => 9, 'username' => 'fomz', 'score' => 15)), 1, 20),
	'overtook' => array(board(), 1, 22),
	'chasing'  => array(board(), 1, null),
	'worse'    => array(board(), 1, 48),
);
foreach ($shapes as $name => $c) {
	$t = dropshipShareText(dropshipShareFacts($c[0], $c[1], $c[2]), 'Dread City');
	if (!ok(stripos($t, 'free') !== false, "the $name post never says the game is free: $t")) break;
	if (!ok(substr_count($t, "\n\n") >= 2, "the $name post is not three parts: $t")) break;
	/* The result leads, because it is why they pressed the button and
	   because a trim cuts from the end. */
	if (!ok(stripos($t, 'Survived') === 0 || stripos($t, 'Personal best') === 0,
	        "the $name post does not lead with the result: $t")) break;
	if (!ok(mb_strlen($t) <= dropshipShareLimit(),
	        "the $name post is " . mb_strlen($t) . ' chars, over the ' . dropshipShareLimit() . ' budget')) break;
}

echo "\nand the pitch is dropped last, not first\n";
/*
 * shareOnXUrl() cuts from the END, so an over-long post would lose the
 * call to action and then the pitch -- the exact half the rewrite exists
 * to add. The text is assembled against the real budget instead, so what
 * gets dropped is chosen rather than whatever happened to be last.
 */
$big = array();
for ($i = 1; $i <= 40; $i++) $big[] = array('user_id' => 100 + $i, 'username' => 'player_number_' . $i, 'score' => 500 - $i);
$big[] = array('user_id' => 1, 'username' => 'me', 'score' => 455);
usort($big, function ($a, $b) { return $b['score'] - $a['score']; });
$bf = dropshipShareFacts($big, 1, 400);

/* THE LONGEST REAL THEME NAME. Filthy Mermaid is 14 characters and the
   worst standing is a personal best on a 41-player board -- if the pitch
   cannot survive that, it never ships, and the whole rewrite is theatre. */
$t = dropshipShareText($bf, 'Filthy Mermaid', 'Round', 'filthy-mermaid');
ok(mb_strlen($t) <= dropshipShareLimit(),
   'the worst REAL case is ' . mb_strlen($t) . ' chars, over the ' . dropshipShareLimit() . ' budget');
ok(stripos($t, 'free to play') !== false,
   'the worst real case drops the pitch, so most posts would ship without one: ' . $t);
ok(substr_count($t, "\n\n") === 2, 'the worst real case is not three parts: ' . $t);

/* AND AN ABSURD ONE, to pin the ORDER things are given up in. */
$t = dropshipShareText($bf, 'Neo Miami Nightlife Extravaganza Deluxe Edition Theme Name');
ok(mb_strlen($t) <= dropshipShareLimit(),
   'the absurd case is ' . mb_strlen($t) . ' chars, over the ' . dropshipShareLimit() . ' budget');
ok(strpos($t, 'round 455') !== false, 'the absurd case lost the score itself: ' . $t);
ok(strpos($t, 'Join ') !== false,
   'the absurd case dropped the call to action before the pitch, which is backwards: ' . $t);

echo "\nthe account is mentioned once, inside a sentence\n";
/*
 * The call names @skulliance, so shareOnXUrl() must stop appending its
 * own tail -- two mentions is 13 wasted characters out of a very tight
 * 280 and reads as a handle dangling under the post.
 */
$t = dropshipShareText(dropshipShareFacts(board(), 1, 22), 'Dread City', 'Round', 'dread-city');
ok(substr_count($t, '@skulliance') === 1, 'the body mentions @skulliance ' . substr_count($t, '@skulliance') . ' times');
$u = shareOnXUrl($t, dropshipSharePage('dread-city'));
parse_str(parse_url($u, PHP_URL_QUERY), $q);
ok(substr_count($q['text'], '@skulliance') === 1,
   'the posted text mentions @skulliance ' . substr_count($q['text'], '@skulliance') . ' times');
ok(mb_strlen($q['text']) + 24 <= 280,
   'the posted length is ' . (mb_strlen($q['text']) + 24) . ', over 280');
/* And a caller that does NOT name it still gets the tail. */
parse_str(parse_url(shareOnXUrl('A result with no handle in it.', 'x.php'), PHP_URL_QUERY), $q2);
ok(strpos($q2['text'], '@skulliance') !== false,
   'suppressing the tail broke every other game, which relies on it');

echo "\nOculus Lounge tags its own account, and nothing tags a person\n";
/*
 * REAL ACCOUNTS. A wrong handle tags a stranger from a player's own
 * timeline, so these come from the owner's own x_tag registry and are
 * shape-checked. @ohh_meed and @MMAXI404 are in that registry too and are
 * PEOPLE -- a run share fires every time anybody finishes a game, and
 * that volume is not pointed at someone's mentions unasked.
 */
ok(dropshipThemeHandle('oculus-lounge') === '@oculuslounge',
   'Oculus Lounge no longer tags its own account');
$t = dropshipShareText(dropshipShareFacts(board(), 1, 22), 'Oculus Lounge', 'Round', 'oculus-lounge');
ok(strpos($t, 'play @oculuslounge.') !== false, 'the Oculus Lounge call does not tag it: ' . $t);
foreach (array('drop-ship', 'dread-city', 'filthy-mermaid', 'dhc2-fighters') as $sl) {
	if (!ok(dropshipThemeHandle($sl) === '', "$sl tags an account nobody asked for: " . dropshipThemeHandle($sl))) break;
	$t = dropshipShareText(dropshipShareFacts(board(), 1, 22), 'A Theme', 'Round', $sl);
	if (!ok(strpos($t, '@ohh_meed') === false && strpos($t, '@MMAXI404') === false,
	        "$sl tags a person on every single run: $t")) break;
}
ok(dropshipThemeHandle('../evil') === '' && dropshipThemeHandle('') === '',
   'an unknown slug produces a handle');

echo "\nthe link is public, and carries no run id\n";
/*
 * BOTH HALVES MATTER. A link behind the login serves X's crawler a redirect
 * to error.php and the post unfurls as a bare URL -- that is why there was
 * no Drop Ship share before this. And ?run=<result_id> would let anyone
 * read any player's score by changing a number.
 */
$page = dropshipSharePage('dhc2-fighters');
ok($page === 'dropshipgame.php?theme=dhc2-fighters', 'the share target moved: ' . $page);
ok(strpos($page, 'run=') === false, 'the share URL carries a run id, which is enumerable');
ok(strpos($page, 'dashboard.php') === false, 'the share points behind the login gate');
ok(dropshipSharePage('../../etc') === 'dropshipgame.php?theme=drop-ship',
   'a bad slug is passed through instead of falling back');
ok(dropshipSharePage('') === 'dropshipgame.php?theme=drop-ship', 'an empty slug is not defaulted');

echo "\nthe post fits in a post\n";
/* X counts any URL as 23 characters and the handle is appended after the
   body, so the budget is not 280. shareOnXUrl() owns that; this checks the
   worst realistic case survives it with the interesting half intact. */
$long = dropshipShareText(dropshipShareFacts(board(), 1, 10), 'A Theme With A Very Long Name Indeed');
$url  = shareOnXUrl($long, dropshipSharePage('dhc2-fighters'));
parse_str(parse_url($url, PHP_URL_QUERY), $q);
ok(mb_strlen($q['text']) <= 280 - 24, 'the body is ' . mb_strlen($q['text']) . ' chars, over budget');
ok(strpos($q['text'], '@skulliance') !== false, 'the post lost the @skulliance tail');
ok(strpos($q['text'], 'Passed kryptman') !== false,
   'a trim ate the overtake, which is the reason to post at all');

echo "\nthe public page and the card exist and stay public\n";
foreach (array('dropshipgame.php', 'dropship-card.php') as $f_) {
	ok(is_file(__DIR__ . '/' . $f_), "$f_ is missing");
	$c = harness_strip_comments(file_get_contents(__DIR__ . '/' . $f_));
	/* skulliance.php is the login gate. Either file including it serves X a
	   redirect, which is the exact failure this feature exists to fix. */
	ok(strpos($c, "skulliance.php") === false, "$f_ includes the login gate");
	ok(strpos($c, "dropship/db.php") === false, "$f_ includes Drop Ship's db.php, which IS the gate");
}
$card = harness_strip_comments(file_get_contents(__DIR__ . '/dropship-card.php'));
ok(preg_match('/\^\[a-z0-9-\]\{1,48\}\$/', $card) === 1,
   'the theme slug is no longer validated before becoming a path');
ok(strpos($card, 'imagedestroy') === false,
   'imagedestroy() is back; it is deprecated in 8.5 and prints in front of the JPEG header');
$page_src = harness_strip_comments(file_get_contents(__DIR__ . '/dropshipgame.php'));
ok(strpos($page_src, 'summary_large_image') !== false, 'the page no longer declares a large card');
ok(strpos($page_src, 'dropship-card.php?theme=') !== false, 'og:image no longer points at the card');
ok(strpos($page_src, 'href="dropship/dashboard.php') !== false,
   'the play link is absolute, which drops a host-only session');

echo "\n" . ($fail ? "$fail FAILED\n" : "all passed\n");
exit($fail ? 1 : 0);
