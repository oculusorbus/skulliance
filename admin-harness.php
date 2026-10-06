<?php
/* admin-harness.php — CLI only. No database, no network.
 *
 * admin-lib.php holds an AUTHORISATION boundary and the only enforcement
 * of the mission economy, so this runs the real file rather than a
 * description of it. Same reasoning as store-edit-harness.php: a test that
 * re-implements the rule it checks passes on the day the rule changes,
 * which is the day it needed to fail.
 *
 * THE TWO PAIRS THAT MUST NOT DRIFT, and the reason this file exists at
 * all: the panel computes a mission's art filename and its still-image
 * extension, and the GAME computes them again in missions-lib.php. If
 * those ever disagree, the panel previews one filename and the player's
 * browser asks for another -- a bug with no error anywhere. Both pairs are
 * asserted equal here against the real missions-lib.php.
 *
 * Usage: php admin-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

require_once __DIR__ . '/admin-lib.php';

/* missions-lib.php includes nothing, but it does call db.php helpers at
   runtime; only the two pure naming functions are lifted. */
$ml = file_get_contents(__DIR__ . '/missions-lib.php');
function lift($src, $sig) {
	$at = strpos($src, $sig); if ($at === false) return '';
	$i = strpos($src, '{', $at); $d = 0;
	for ($j = $i; $j < strlen($src); $j++) {
		if ($src[$j] === '{') $d++;
		elseif ($src[$j] === '}') { $d--; if (!$d) return substr($src, $at, $j - $at + 1); }
	}
	return '';
}
eval(lift($ml, 'function mission_art_slug('));
eval(lift($ml, 'function mission_art_ext('));

echo "the panel and the game agree on names\n";
foreach (array("Ohh Meed's Bar", 'Enter The Galacticverse', 'Checkmate', 'Arrows of Fate') as $t) {
	ok(admin_mission_slug($t) === mission_art_slug($t),
	   "the panel would preview a different filename than the game asks for, on \"$t\"");
}
foreach (array('png','jpg','gif','mp4','mov') as $x) {
	ok(admin_art_ext($x) === mission_art_ext($x),
	   "the panel and the game disagree about the still image for a .$x mission");
}

echo "\nwho may use the panel\n";
$_SESSION = array();
ok(adminRights()['ok'] === false, 'a logged-out visitor is admitted');
$_SESSION['userData'] = array('user_id' => 2);
ok(adminRights()['ok'] === false, 'an ordinary member is admitted to a panel that creates projects');
$_SESSION['userData'] = array('user_id' => '1');
ok(adminRights()['ok'] === true, 'user 1 is locked out of their own panel');
ok(adminIsSuper() === true, 'adminIsSuper() disagrees with adminRights()');

echo "\na title that cannot be a filename is refused\n";
ok(admin_slug_problem(admin_mission_slug('Temple Gates')) === '', 'a perfectly ordinary title was refused');
ok(admin_slug_problem(admin_mission_slug("Ohh Meed's Bar")) === '', 'an apostrophe was refused; the slug strips those');
foreach (array('Deep: The Descent', 'Rock & Roll', 'Up/Down', 'Café Noir', 'What?') as $t) {
	ok(admin_slug_problem(admin_mission_slug($t)) !== '',
	   "\"$t\" was accepted -- that character goes straight into the art filename");
}
ok(admin_slug_problem('') !== '', 'an empty title was accepted');

echo "\nthe next mission continues the ladder the project already uses\n";
/* Flat 100s (14 live projects) -- level 11 costs 1100. */
$flat = array(200,300,400,500,600,700,800,900,1000);
ok(admin_next_cost($flat, 11) === 1100, 'a flat-100 project did not continue at 100 x level, got ' . admin_next_cost($flat, 11));
/* The wide spread (25 live projects) -- after 1500 comes 2000, not 1600. */
$wide = array(300,500,700,1000,1500);
ok(admin_next_cost($wide, 7) === 2000, 'a wide-spread project dropped off its own ladder, got ' . admin_next_cost($wide, 7));
/* A SHORTER wide project, where "next rung" and "continue by the last
   step" give DIFFERENT answers -- 1000 against 900. The five-rung fixture
   above cannot tell those apart, so on its own it passes with the ladder
   lookup deleted entirely. */
$wide4 = array(300, 500, 700);
ok(admin_next_cost($wide4, 5) === 1000,
   'a four-mission wide project did not take the next rung of the master ladder (1000), got ' . admin_next_cost($wide4, 5));
/* A brand-new project starts on flat 100s. */
ok(admin_next_cost(array(), 2) === 200, 'a new project did not start at 200');
/* Past the master sequence it must never go DOWN. */
$full = admin_wide_ladder();
$next = admin_next_cost($full, 11);
ok($next > end($full), "past the wide ladder the next cost was $next, which is not above " . end($full)
                     . ' -- a mission cheaper than the one below it');
ok($next % 100 === 0, 'the continued step is not a multiple of 100, so the duration stops being whole days');

echo "\nthe derived fields\n";
ok(admin_mission_duration(1500) === 15, 'duration is not cost / 100');
ok(admin_mission_reward(1000, 5, 0) === 1500, 'reward is not cost x (1 + level/10)');
ok(admin_mission_reward(1000, 5, 0) % 10 === 0, 'a reward came out off a multiple of ten');
/* The invariant the whole economy rests on. */
for ($L = 2; $L <= 12; $L++) {
	$c = 100 * $L; $r = admin_mission_reward($c, $L, 0);
	ok(abs(admin_mission_per_day($c, $r, admin_mission_duration($c)) - 10 * $L) < 1e-9,
	   "net per day at level $L is not 10 x level, which is the number the player is shown");
}
/* And with the cap on, the rate stops climbing but the cost does not. */
ok(admin_mission_reward(3000, 30, 10) === 6000, 'the multiplier cap is not being applied');
ok(admin_mission_per_day(3000, admin_mission_reward(3000, 30, 10), 30) === 100.0,
   'a capped mission does not settle at 100 a day');

echo "\nwhat the editor pre-fills\n";
$d = admin_mission_defaults($flat, 11, 0);
ok($d['cost'] === 1100 && $d['duration'] === 11 && $d['reward'] === 2310,
   'the defaults for the next mission are not self-consistent: ' . json_encode($d));
$d1 = admin_mission_defaults(array(), 1);
ok($d1['cost'] === 0 && $d1['reward'] === 10 && $d1['duration'] === 1,
   'level 1 is not defaulting to the free intro every project opens with');

echo "\nsaving a mission\n";
$good = array('title' => 'Temple Gates', 'level' => 5, 'cost' => 500, 'reward' => 750, 'duration' => 5);
ok(admin_validate_mission($good, array(), 0) === array(),
   'a correct mission was refused: ' . implode(' / ', admin_validate_mission($good, array(), 0)));
$bad = $good; $bad['duration'] = 7;
ok(count(admin_validate_mission($bad, array(), 0)) === 1, 'a duration that disagrees with the cost was accepted -- that is The Worm');
$bad = $good; $bad['reward'] = 600;
ok(count(admin_validate_mission($bad, array(), 0)) === 1, 'a hand-typed reward was accepted -- that is Trash Collection');
$bad = $good; $bad['cost'] = 550;
ok(admin_validate_mission($bad, array(), 0) !== array(), 'a cost that is not a multiple of 100 was accepted');
ok(admin_validate_mission($good, array(5), 0) !== array(), 'a duplicate level was accepted');
$l1 = array('title' => 'Intro', 'level' => 1, 'cost' => 200, 'reward' => 240, 'duration' => 2);
ok(admin_validate_mission($l1, array(), 0) !== array(), 'a level 1 that is not the free intro was accepted');

echo "\nand the ladder as a whole\n";
$ladder = array(1 => array('cost'=>0,'reward'=>10,'duration'=>1),
                2 => array('cost'=>200,'reward'=>240,'duration'=>2),
                3 => array('cost'=>300,'reward'=>390,'duration'=>3));
ok(admin_validate_ladder($ladder, 0) === array(),
   'a clean ladder was refused: ' . implode(' / ', admin_validate_ladder($ladder, 0)));
$gap = $ladder; unset($gap[2]);
ok(admin_validate_ladder($gap, 0) !== array(), 'a gap in the ladder was accepted; level 3 would be unreachable');
$no1 = $ladder; unset($no1[1]);
ok(admin_validate_ladder($no1, 0) !== array(), 'a project with no level 1 was accepted');

echo "\ncurrency is an icon filename\n";
ok(admin_currency_icon('DREAD') === 'icons/dread.png', 'the currency icon path does not match what the pages render');
ok(admin_currency_icon('$SCRIP') === 'icons/scrip.png', 'a leading $ is not stripped the way skulliance.php strips it');
ok(admin_currency_problem('DREAD', array('SCRIP','CARBON')) === '', 'an unused currency was refused');
/* Case-insensitive, because skullswap.php groups the board on LOWER(currency). */
ok(admin_currency_problem('dread', array('DREAD')) !== '',
   'a currency differing only in case was allowed -- both projects would share one icon and one Skull Swap tile');
ok(admin_currency_problem('A', array()) !== '', 'a one-character currency was accepted');
ok(admin_currency_problem('MY COIN', array()) !== '', 'a currency with a space was accepted; it becomes a filename');

echo "\nmission art arrives in pairs\n";
ok(admin_art_missing('png', array('png')) === array(), 'a complete png mission was told it was missing a file');
ok(admin_art_missing('mp4', array('mp4')) !== array(),
   'an mp4 with no paired .gif was accepted -- every <img> surface would show a broken tile');
ok(admin_art_missing('mp4', array('mp4','gif')) === array(), 'a complete mp4 pair was refused');
/* THE OTHER HALF OF THE PAIR. Testing only the missing gif passes even
   with the video requirement deleted, because the gif alone still trips
   it -- so the missing .mp4 has to be asked about separately. */
ok(admin_art_missing('mp4', array('gif')) !== array(),
   'an mp4 mission with only its still was accepted -- there would be no video to play');
ok(admin_art_missing('mov', array('mov')) !== array(),
   'mov was accepted; nothing on this host can read one and the game has no branch for it');
ok(!isset(admin_art_kinds()['mov']), 'mov is listed as a renderable format');
/* A REFUSAL THAT NAMES THE FIX. QuickTime is the one people actually
   try, because it is what a phone and a screen recorder produce, and
   "that is not an MP4" leaves them to work the rest out. */
$lib_raw = file_get_contents(__DIR__ . '/admin-lib.php');
/* Matched with \s* because the map is column-aligned -- a literal
   "'image/webp' =>" misses it by two spaces, which is the third time an
   assertion in this file has been wrong about whitespace rather than
   the code being wrong. */
foreach (array('video/quicktime', 'image/webp', 'video/webm', 'image/svg+xml') as $m) {
	ok(preg_match("/'" . preg_quote($m, '/') . "'\s*=>/", $lib_raw) === 1,
	   "an upload of $m gets the generic \"not a PNG, JPG, GIF or MP4\" message instead of being told what to do");
}
ok(stripos($lib_raw, 'H.264') !== false,
   'nothing tells the operator which codec is safe, which is the actual fix');

echo "\nhow big an image may be\n";
/* THE ONE THAT TOOK THE SITE DOWN, 2026-10-05. A 5000x5000 mission
   upload against a flat 256MB Imagick limit: ImageMagick does not fail
   past its limit, it spills the pixel cache to disk and grinds, so the
   request never returned and every navigation behind it fell through
   the service worker's 20s timeout onto offline.html. */
$b5 = admin_image_budget(5000, 5000);
ok($b5['ok'] === true, 'a 5000x5000 upload is refused; it is large but legitimate and should be downscaled, not rejected');
/* 8 bytes a pixel is the Q16 pixel cache ALONE; a resize needs a working
   copy on top. Asserting only the cache size passes a flat 256MB limit,
   which is the exact configuration that hung -- so the bar is 12. */
ok($b5['mem'] >= 25000000 * 12,
   'a 25-megapixel image is given ' . round($b5['mem'] / 1048576) . 'MB. Its Q16 pixel cache alone is ~200MB '
 . 'and a resize needs a working copy, so ImageMagick would spill to disk and the request would hang '
 . 'rather than fail');

/* Small images must not be handed enormous limits either. */
$b1 = admin_image_budget(800, 600);
ok($b1['ok'] === true && $b1['mem'] <= 128 * 1048576,
   'a small image is given ' . round($b1['mem'] / 1048576) . 'MB, far more than it can use');

/* And something genuinely absurd is refused FROM THE HEADER, instantly,
   rather than accepted and ground through swap. */
foreach (array(array(6000, 6000), array(10000, 10000), array(20000, 2000)) as $d) {
	$b = admin_image_budget($d[0], $d[1]);
	ok($b['ok'] === false, "{$d[0]}x{$d[1]} was accepted; past the budget it cannot finish in a request");
	ok(strpos($b['why'], 'megapixel') !== false && preg_match('/\d/', $b['why']) === 1 || strlen($b['why']) > 40,
	   "{$d[0]}x{$d[1]} is refused without telling the operator the limit or what to do");
}
ok(admin_image_budget(0, 0)['ok'] === false, 'a file with no dimensions was accepted');
ok(admin_image_budget(-5, 100)['ok'] === false, 'a negative dimension was accepted');
/* The ceiling must bound even an in-budget image. */
ok(admin_image_budget(5500, 5450)['mem'] <= ADMIN_IMAGE_MAX_MEM * 1048576,
   'the memory grant ignores its own ceiling');

/* And the decision has to happen BEFORE the decode, or it saves nothing. */
$lib_b = preg_replace('!/\*.*?\*/!s', '', file_get_contents(__DIR__ . '/admin-lib.php'));
$wi    = strpos($lib_b, 'function admin_write_image');
$body  = substr($lib_b, $wi, 1800);
ok(strpos($body, 'getimagesize') !== false,
   'admin_write_image() does not read the header first, so an oversized image is only discovered after decoding it');
ok(strpos($body, 'admin_image_budget') < strpos($body, 'readImage'),
   'the budget is checked after readImage(), which is after the allocation that causes the hang');
ok(strpos($body, 'RESOURCETYPE_MEMORY, $budget') !== false,
   'the Imagick memory limit is a fixed number again rather than sized to the image');

echo "\nediting a mission's art: rename, replace, and leave no debt\n";
/* The plan is pure, so every shape of edit is exercised here with no
   filesystem -- which is the only honest way to test something that
   deletes somebody's artwork. */
function plan_is($got, $want_rename, $want_delete, $want_missing, $label) {
	$r = array_map(function($x){ return $x[0] . '>' . $x[1]; }, $got['rename']); sort($r);
	$d = $got['delete']; sort($d);
	$m = $got['missing']; sort($m);
	sort($want_rename); sort($want_delete); sort($want_missing);
	ok($r === $want_rename, "$label: renames were [" . implode(' ', $r) . '], expected [' . implode(' ', $want_rename) . ']');
	ok($d === $want_delete, "$label: deletes were [" . implode(' ', $d) . '], expected [' . implode(' ', $want_delete) . ']');
	ok($m === $want_missing, "$label: missing was [" . implode(' ', $m) . '], expected [' . implode(' ', $want_missing) . ']');
}

/* THE HEADLINE CASE: a retitle with nothing uploaded. The file follows
   the title; the operator should not have to find the art again. */
plan_is(admin_art_plan('old-name', 'new-name', 'png', array('png'), array(), array()),
        array('old-name.png>new-name.png'), array(), array(), 'retitle, png, no upload');

/* An mp4 retitle moves BOTH halves of the pair. */
plan_is(admin_art_plan('old-name', 'new-name', 'mp4', array('mp4', 'gif'), array(), array()),
        array('old-name.gif>new-name.gif', 'old-name.mp4>new-name.mp4'), array(), array(),
        'retitle, mp4 pair, no upload');

/* A retitle where the old art is incomplete must still refuse, not
   half-rename and save a row pointing at a missing video. */
plan_is(admin_art_plan('old-name', 'new-name', 'mp4', array('gif'), array(), array()),
        array('old-name.gif>new-name.gif'), array(), array('mp4'), 'retitle, mp4 missing its video');

/* REPLACING THE ART WITHOUT RETITLING: the upload wins, nothing moves,
   nothing is orphaned. */
plan_is(admin_art_plan('same', 'same', 'png', array('png'), array('png'), array('png')),
        array(), array(), array(), 'same title, new png over the old one');

/* CHANGING FORMAT is where the debt came from. png -> mp4 leaves a
   stale .png behind unless it is cleaned up. */
plan_is(admin_art_plan('same', 'same', 'mp4', array('png'), array('png'), array('mp4', 'gif')),
        array(), array('same.png'), array(), 'same title, png becomes an mp4 pair');

/* ...and the reverse: mp4 -> png must take BOTH old files with it. */
plan_is(admin_art_plan('same', 'same', 'png', array('mp4', 'gif'), array('mp4', 'gif'), array('png')),
        array(), array('same.gif', 'same.mp4'), array(), 'same title, mp4 pair becomes a png');

/* A retitle AND a format change at once: the old pair goes, the new
   file stands alone under the new name. */
plan_is(admin_art_plan('old-name', 'new-name', 'png', array('mp4', 'gif'), array(), array('png')),
        array(), array('old-name.gif', 'old-name.mp4'), array(), 'retitle and reformat together');

/* A retitle where an upload also arrives: the upload wins and the old
   file is cleaned up rather than renamed on top of it. */
plan_is(admin_art_plan('old-name', 'new-name', 'png', array('png'), array(), array('png')),
        array(), array('old-name.png'), array(), 'retitle with a replacement uploaded');

/* A new mission has no old slug and nothing to clean. */
plan_is(admin_art_plan('', 'brand-new', 'png', array(), array(), array('png')),
        array(), array(), array(), 'a brand new mission');
plan_is(admin_art_plan('', 'brand-new', 'mp4', array(), array(), array('mp4')),
        array(), array(), array('gif'), 'a brand new mp4 with no still');

/* THE ONE THAT WOULD LOSE ART: a rename's destination must never also
   be queued for deletion. */
$p = admin_art_plan('old-name', 'new-name', 'gif', array('gif', 'png'), array(), array());
$dests = array(); foreach ($p['rename'] as $r) $dests[] = $r[1];
foreach ($dests as $dst) ok(!in_array($dst, $p['delete'], true),
   "a file is renamed to $dst and then deleted, which loses the art");
ok(in_array('old-name.png', $p['delete'], true), 'the leftover old .png was not cleaned up');

/* An unknown format is refused before anything is planned. */
$bad = admin_art_plan('a', 'b', 'mov', array('mov'), array(), array());
ok($bad['missing'] === array('mov') && $bad['rename'] === array() && $bad['delete'] === array(),
   'an unsupported format produced a rename or a delete instead of a refusal');

echo "\nreordering a ladder\n";
/* Aeoniumsky's shape: a flat-100 project, level 1 free. */
function rowset($levels_costs) {
	$r = array(); $id = 100;
	foreach ($levels_costs as $lv => $cost) {
		$r[$id++] = array('level' => $lv, 'cost' => $cost,
		                  'reward' => $cost ? (int)(($cost * (10 + $lv)) / 10) : 10,
		                  'duration' => $cost ? (int)($cost / 100) : 1);
	}
	return $r;
}
$rows = rowset(array(1 => 0, 2 => 200, 3 => 300, 4 => 400));   // ids 100..103
$ids  = array_keys($rows);

/* THE PROPERTY THAT MAKES A DRAG SAFE: the rungs belong to the project,
   so the same costs exist before and after -- only the mapping moves. */
$rev = array_reverse($ids);
$out = admin_reorder_plan($rows, $rev, 0);
ok($out['errors'] === array(), 'a straight reversal was refused: ' . implode(' / ', $out['errors']));
$before = array(); foreach ($rows as $r) $before[] = (int)$r['cost']; sort($before);
$after  = array(); foreach ($out['plan'] as $p) $after[] = (int)$p['cost']; sort($after);
ok($before === $after,
   'reordering changed the set of costs on the ladder -- it must only change which mission sits on which rung');
$spend_before = array_sum($before); $spend_after = array_sum($after);
ok($spend_before === $spend_after, 'the project pays a different total after a reorder');

/* Level 1 is the free intro WHEREVER it lands. */
$first = $out['plan'][$rev[0]];
ok($first['level'] === 1 && $first['cost'] === 0 && $first['reward'] === 10 && $first['duration'] === 1,
   'the mission dragged to the top did not become the free intro');
/* ...and the old opener picks up rung 2 rather than staying free. */
$last = $out['plan'][$ids[0]];
ok($last['cost'] > 0, 'the old level-1 mission stayed free after being moved down');

/* Every row is contiguous, derived, and clean. */
$levels = array(); foreach ($out['plan'] as $p) $levels[] = $p['level'];
sort($levels);
ok($levels === range(1, count($rows)), 'the new levels are not contiguous from 1');
foreach ($out['plan'] as $id => $p) {
	if ($p['level'] === 1) continue;
	ok($p['reward'] === admin_mission_reward($p['cost'], $p['level'], 0), "row $id: reward is not derived from its new level");
	ok($p['duration'] === admin_mission_duration($p['cost']), "row $id: duration is not cost / 100");
	ok($p['reward'] % 10 === 0, "row $id: reward is off a multiple of ten");
}

/* A WIDE-SPREAD project keeps its own rungs rather than being flattened. */
$wide = rowset(array(1 => 0, 2 => 300, 3 => 500, 4 => 700, 5 => 1000, 6 => 1500));
$wout = admin_reorder_plan($wide, array_reverse(array_keys($wide)), 0);
$wcosts = array(); foreach ($wout['plan'] as $p) $wcosts[] = (int)$p['cost']; sort($wcosts);
ok($wcosts === array(0, 300, 500, 700, 1000, 1500),
   'a wide-spread project was flattened onto 100-steps by a reorder: ' . implode(',', $wcosts));

echo "\nand anything that is not a permutation is refused outright\n";
foreach (array(
	'a missing mission'   => array_slice($ids, 1),
	'a duplicate'         => array($ids[0], $ids[0], $ids[1], $ids[2]),
	'a stranger'          => array($ids[0], $ids[1], $ids[2], 999999),
	'nothing at all'      => array(),
) as $what => $bad) {
	$r = admin_reorder_plan($rows, $bad, 0);
	ok($r['errors'] !== array(), "$what was accepted as an order");
	ok($r['plan'] === array(), "$what produced a plan anyway -- a partial write is worse than a refusal");
}

echo "\nand up/down move exactly one place\n";
$up = admin_reorder_move($rows, $ids[2], 'up');
ok($up === array($ids[0], $ids[2], $ids[1], $ids[3]), 'up did not swap with the row above: ' . implode(',', $up));
$dn = admin_reorder_move($rows, $ids[0], 'down');
ok($dn === array($ids[1], $ids[0], $ids[2], $ids[3]), 'down did not swap with the row below');
ok(admin_reorder_move($rows, $ids[0], 'up') === $ids, 'up at the top of the ladder changed the order');
ok(admin_reorder_move($rows, $ids[3], 'down') === $ids, 'down at the bottom changed the order');
ok(admin_reorder_move($rows, 999999, 'up') === $ids, 'moving a mission that is not here changed the order');
/* Both paths end in the planner, so a move is always a valid permutation. */
ok(admin_reorder_plan($rows, admin_reorder_move($rows, $ids[2], 'up'), 0)['errors'] === array(),
   'the order produced by a move is not accepted by the planner');

/* ---------- the three pages ---------------------------------------- *
 *
 * They include db.php and emit a page, so these are textual -- but each
 * guards something that would be invisible if it broke.
 */
echo "\nthe panel pages\n";
$PAGES = array('admin-projects.php', 'admin-collections.php', 'admin-missions.php', 'admin-blockchains.php');
$src = array();
foreach ($PAGES as $f) {
	ok(is_file(__DIR__ . '/' . $f), "$f is missing");
	$src[$f] = is_file(__DIR__ . '/' . $f) ? preg_replace('!/\*.*?\*/!s', '', file_get_contents(__DIR__ . '/' . $f)) : '';
}

/* And the stylesheet keeps the override, in case one comes back. */
$css_src = file_get_contents(__DIR__ . '/admin-css.php');
ok(preg_match('/\.adm section\s*\{[^}]*opacity:\s*1/', $css_src) === 1,
   'admin-css.php no longer forces .adm section back to opacity 1; a stray <section> would be invisible again');

$lib_src = preg_replace('!/\*.*?\*/!s', '', file_get_contents(__DIR__ . '/admin-lib.php'));
ok(strpos($lib_src, "include __DIR__ . '/header.php'") === false,
   'admin-lib.php includes header.php from inside a function again -- that runs it in the function scope, '
 . 'with none of the globals it reads');

foreach ($PAGES as $f) {
	$b = $src[$f];
	/* Drawn by admin_chrome(), re-checked before any write. A page that
	   gates once and then trusts itself is one early return from an
	   unguarded write. */
	ok(strpos($b, 'admin_require()') !== false, "$f does not call admin_require(), so its rights check is its own");
	ok(strpos($b, 'admin_chrome(') !== false, "$f does not open the admin layout");
	/* HEADER AT GLOBAL SCOPE. `include` inside a function body runs the
	   included file in THAT function's scope, and header.php reads $name
	   and $avatar_url which skulliance.php sets globally -- so including
	   it from inside admin_chrome() handed it an empty scope and rendered
	   the logged-out branch. The page must include it itself. */
	ok(preg_match("/^include 'header\\.php';/m", $b) === 1,
	   "$f does not include header.php at global scope");
	ok(strpos($b, 'adminIsSuper()') !== false, "$f never re-checks rights inside its write block");
	ok(strpos($b, "REQUEST_METHOD'] === 'POST'") !== false, "$f has no POST gate");
	/* THE LAYOUT BUG THAT MADE THE FIRST VERSION LOOK BROKEN. header.php
	   leaves a .container OPEN; opening a second one, or using the
	   .column class that does not exist in dist/flexbox.css, leaves the
	   content with no box. */
	ok(strpos($b, 'class="container"') === false,
	   "$f opens its own .container -- header.php already left one open and nesting them is what broke the layout");
	/* THE ONE THAT MADE THE WHOLE PANEL INVISIBLE. dist/flexbox.css has a
	   BARE ELEMENT rule -- `section { opacity: 0 }`, revealed by adding
	   .active from a scroll observer in skulliance.js. Admin pages do not
	   load that script, so a <section> here renders at full size with
	   nothing painted inside it: in the DOM, correctly laid out, and
	   completely invisible. No error, nothing in the console, and every
	   control in it unclickable because there is nothing to see. */
	ok(strpos($b, '<section') === false,
	   "$f uses a <section>, which dist/flexbox.css hides with opacity:0 until skulliance.js adds .active -- "
	 . 'these pages do not load it, so the content renders invisible');
	ok(strpos($b, 'class="column"') === false,
	   "$f uses .column, which does not exist in dist/flexbox.css (only .col1of2 and .col1of3 do)");
	/* A failed write must say so. mysqli_report is OFF platform-wide, so
	   query() returns false rather than throwing, and a silent redirect
	   back to the form looks exactly like success. */
	ok(strpos($b, '$conn->error') !== false, "$f redirects without ever checking whether the write succeeded");
}

/* THE WHOLE POINT of the missions page: the form displays reward and
   duration and must not SEND them, or a hand-edited field walks past the
   validator and recreates Trash Collection by hand. */
$mi = $src['admin-missions.php'];
ok(strpos($mi, "_POST['reward']") === false,
   'admin-missions.php reads reward from the POST; it has to be derived or the form can be edited');
ok(strpos($mi, "_POST['duration']") === false, 'admin-missions.php reads duration from the POST');
ok(preg_match('/\$duration\s*=\s*admin_mission_duration\(/', $mi) === 0,
   'the save handler calls admin_mission_duration() directly again');
/* DERIVED, but no longer by calling the raw helper here: both the form
   and the save path go through admin_mission_derive(), because calling
   the helpers directly is precisely what made level 1 unsaveable. The
   assertion is the property -- derived, not posted -- not the spelling. */
ok(preg_match('/\$d\s*=\s*admin_mission_derive\(/', $mi) === 1,
   'the mission reward and duration are not derived through admin_mission_derive()');
ok(preg_match('/\$reward\s*=\s*admin_mission_reward\(/', $mi) === 0,
   'the save handler calls admin_mission_reward() directly again -- that is the level-1 bug, '
 . 'where the form showed 10/1 and the POST computed 0/0');

ok(strpos($mi, 'admin_validate_mission(') !== false, 'the mission write does not call the validator');
/* EDITING ART: rename, replace, no debt. The order is the safety, so
   the harness pins the order and not just the calls. */
ok(strpos($mi, 'admin_art_plan(') !== false, 'the mission write does not plan its file changes');
ok(strpos($mi, 'admin_art_apply_renames(') !== false, 'a retitle no longer moves the art, so it asks for a re-upload');
ok(strpos($mi, 'admin_art_apply_deletes(') !== false, 'nothing cleans up, so images/missions/ gains an orphan per rename');
ok(strpos($mi, '$Q_before') !== false, 'the save does not read the row before editing it, so it cannot know the old filename');
/* DELETES AFTER THE WRITE. Before it, an edit that fails validation
   takes the artwork with it. */
$i_write  = strpos($mi, '$wrote =');
$i_delete = strpos($mi, 'admin_art_apply_deletes(');
$i_rename = strpos($mi, 'admin_art_apply_renames(');
ok($i_rename !== false && $i_write !== false && $i_rename < $i_write,
   'the renames happen after the row is written, so a failed rename leaves a row pointing at nothing');
ok($i_delete !== false && $i_write !== false && $i_delete > $i_write,
   'files are deleted BEFORE the row is written -- a save that fails validation would take the art with it');
/* And a failed write puts the renames back. */
ok(preg_match('/if \(!\$wrote\)\s*\{[^}]*admin_art_apply_renames/s', $mi) === 1,
   'a refused write leaves the files renamed for a title that was never saved');

/* REORDERING. Both routes must end in the planner, and the save must be
   scoped to the project -- a stray id in the posted order would
   otherwise renumber somebody else's ladder. */
ok(strpos($mi, 'admin_reorder_plan(') !== false, 'the reorder write does not go through the planner');
ok(strpos($mi, 'admin_reorder_move(') !== false, 'the up/down buttons do not go through the shared move helper');
ok(preg_match('/UPDATE quests SET level[^;]*AND project_id/s', $mi) === 1,
   'the reorder UPDATE is not scoped to the project; a stray id could renumber another ladder');
/* The mission-save block must not also run on a reorder post, or a drag
   would try to save a mission with no title. */
ok(strpos($mi, "!== 'reorder'") !== false,
   'the mission save does not stand down for a reorder post');
/* The up/down buttons are plain submits, so the ladder is reorderable
   with the script absent -- the lesson from the picker. */
ok(preg_match('/name="up_<\?php/', $mi) === 1 && preg_match('/name="down_<\?php/', $mi) === 1,
   'the up/down buttons are gone, leaving drag as the only way to reorder');
ok(strpos($mi, 'type="submit" name="up_') !== false,
   'the up button is not a real submit, so it needs JS to do anything');

$pr = $src['admin-projects.php'];
ok(strpos($pr, 'admin_currency_problem(') !== false, 'the project write does not check the currency is free');
/* A NEW project defaults its discord_id to the admin, because a blank one
   means nobody can edit that project's store listings. An EXISTING blank
   must be left alone -- substituting there reassigns the listings. */
ok(defined('ADMIN_DEFAULT_DISCORD') && ADMIN_DEFAULT_DISCORD !== '',
   'ADMIN_DEFAULT_DISCORD is not set, so a new project gets an unowned store');
ok(preg_match('/\$P \? \$P\[.discord_id.\] : ADMIN_DEFAULT_DISCORD/', $pr) === 1,
   'the Discord id is not defaulted for new projects only -- a ?? on the column would also overwrite '
 . 'an existing blank, which reassigns who can edit that project\'s store listings');
/* adm_accept_upload() lives in admin-lib.php now, shared by both
   uploading pages, so the type check is asserted there. */
$lib = file_get_contents(__DIR__ . '/admin-lib.php');
ok(strpos($lib, 'finfo_file') !== false,
   'uploads trust the browser-supplied type; this writes into a directory the server serves');
ok(strpos($lib, 'move_uploaded_file') !== false, 'uploads are not moved with move_uploaded_file()');
ok(strpos($pr, 'adm_accept_upload(') !== false, 'the project page never accepts an icon upload');

$co = $src['admin-collections.php'];
/* Greping for the NAME is not enough: putting `false &&` in front of the
   whole condition leaves the string in the file and the check dead. Tie
   it to the chain branch it guards. */
ok(preg_match('/\$chain === 3 &&[^;]*sol_base58_decode/s', $co) === 1,
   'the Solana branch no longer decodes the address -- base58 has no checksum, so a truncated one is still legal-looking');
ok(preg_match('/FROM collections WHERE policy/', $co) === 1,
   'the collections page does not check the on-chain id is unused; two rows would claim the same NFTs');

/* ---------- the marketplace template ------------------------------- *
 *
 * The MARKETPLACE is per chain and now lives in blockchains; the
 * IDENTIFIER is per collection and stays on collections. Four live XRPL
 * collections have four different slugs, so the slug can never become a
 * chain setting -- these pin the split in both directions.
 */
echo "\nthe marketplace link\n";
$db = preg_replace('!/\*.*?\*/!s', '', file_get_contents(__DIR__ . '/db.php'));
ok(strpos($db, "'marketplace_url' => 1") !== false,
   "getChainSetting() does not whitelist marketplace_url, so the column reads as its default and is silently ignored");
ok(preg_match("/function collectionMarketUrl\(.*\\\$conn = null\)/", $db) === 1,
   'collectionMarketUrl() no longer accepts $conn, so it cannot consult the chain row');
ok(preg_match('/getChainSetting\(\$conn, \$bid, .marketplace_url./', $db) === 1,
   'collectionMarketUrl() never reads marketplace_url from the chain');
/* The template comes out of a database column, so a stray % in one must
   not reach sprintf -- same trap nftExplorerUrl() already documents. */
ok(strpos($db, 'str_replace(\'%s\', rawurlencode($id), $tpl)') !== false,
   'the marketplace template is interpolated with sprintf; a stray % in the column is a ValueError, '
 . 'which is a broken page rather than a broken link');
/* A template without its placeholder points every collection at one page. */
$bc = preg_replace('!/\*.*?\*/!s', '', file_get_contents(__DIR__ . '/admin-blockchains.php'));
ok(strpos($bc, "strpos(\$v, '%s') === false") !== false,
   'admin-blockchains.php accepts a template with no %s, which links every collection to the same page');
/* Asserted against the VISIBLE prose, not a comment -- $bc has comments
   stripped, and the point is that an admin reading the page is told
   where the slug lives, not that the source mentions it. */
$bc_flat = preg_replace('/\s+/', ' ', $bc);   // the prose wraps across lines in the source
ok(stripos($bc_flat, 'marketplace slug lives on the collection') !== false,
   'admin-blockchains.php does not tell the reader where a collection slug actually lives, '
 . 'which is exactly the confusion that prompted this split');
/* And the slug stays on the collection. */
$co2 = preg_replace('!/\*.*?\*/!s', '', file_get_contents(__DIR__ . '/admin-collections.php'));
ok(strpos($co2, 'name="marketplace_slug"') !== false,
   'the collection form lost its marketplace_slug field; four live XRPL collections have four different slugs');

/* The old tabbed page stays as a redirect rather than a 404. */
$old = file_get_contents(__DIR__ . '/admin.php');
ok(strpos($old, 'admin-projects.php') !== false && stripos($old, 'Location:') !== false,
   'admin.php no longer redirects, so an old bookmark 404s');

/* And the nav offers all three. */
$hd = file_get_contents(__DIR__ . '/header.php');
foreach ($PAGES as $f) ok(strpos($hd, $f) !== false, "the nav has no link to $f");
ok(preg_match('/user_id\'\]\s*===?\s*1/', $hd) === 1, 'the Admin nav is not gated to user 1');

echo "\nlevel 1 can actually be saved\n";

/*
 * THE BUG: the save handler derived reward and duration by calling
 * admin_mission_reward() and admin_mission_duration() directly, while
 * the FORM displayed admin_mission_defaults(), which special-cases the
 * free intro. At level 1 the first pair gives 0 and 0, the second gives
 * 10 and 1, and admin_validate_mission() then refused the save with
 * "Level 1 is the free intro" -- a true message about values the user
 * never typed. Level 1 was unsaveable on every project.
 *
 * The old assertion here REQUIRED the handler to call the raw helpers,
 * so the harness was enforcing the bug's shape. It checked the spelling
 * of the derivation rather than the property, which is that reward and
 * duration are derived and not posted.
 */
$d1x = admin_mission_derive(0, 1);
ok($d1x['cost'] === 0 && $d1x['reward'] === 10 && $d1x['duration'] === 1,
   'level 1 does not derive the free intro: ' . json_encode($d1x));
$dx = admin_mission_derive(900, 1);
ok($dx['cost'] === 0 && $dx['reward'] === 10 && $dx['duration'] === 1,
   'a cost posted on level 1 reaches the table: ' . json_encode($dx));

/* THE ROUND TRIP, which is the point: whatever derive() produces must
   satisfy the validator, at every level. */
foreach (array(1, 2, 3, 7, 14, 20, 46) as $lv) {
	$cost = ($lv <= 1) ? 0 : 100 * $lv;
	$dd   = admin_mission_derive($cost, $lv, 0);
	$ee   = admin_validate_mission(array('title' => 'Probe ' . $lv, 'level' => $lv,
		'cost' => $dd['cost'], 'reward' => $dd['reward'], 'duration' => $dd['duration']), array(), 0);
	ok(!$ee, "level $lv derives values its own validator rejects: " . implode(' | ', $ee));
}

/* The form shows defaults(); the handler calls derive(). At level 1 they
   must agree or the panel displays something it will not write. */
$defx = admin_mission_defaults(array(), 1);
ok($defx['cost'] === $d1x['cost'] && $defx['reward'] === $d1x['reward']
   && $defx['duration'] === $d1x['duration'],
   'what the form displays for level 1 is not what the save path writes');

echo "\n" . ($fail ? "FAILED ($fail)\n" : "all admin-lib checks passed\n");
exit($fail ? 1 : 0);
