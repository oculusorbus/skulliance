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

/* ---------- the three pages ---------------------------------------- *
 *
 * They include db.php and emit a page, so these are textual -- but each
 * guards something that would be invisible if it broke.
 */
echo "\nthe panel pages\n";
$PAGES = array('admin-projects.php', 'admin-collections.php', 'admin-missions.php');
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
ok(preg_match('/\$reward\s*=\s*admin_mission_reward\(/', $mi) === 1, 'the mission reward is not derived');
ok(preg_match('/\$duration\s*=\s*admin_mission_duration\(/', $mi) === 1, 'the mission duration is not derived');
ok(strpos($mi, 'admin_validate_mission(') !== false, 'the mission write does not call the validator');
ok(strpos($mi, 'admin_art_missing(') !== false, 'the mission write does not check its art arrived');

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

/* The old tabbed page stays as a redirect rather than a 404. */
$old = file_get_contents(__DIR__ . '/admin.php');
ok(strpos($old, 'admin-projects.php') !== false && stripos($old, 'Location:') !== false,
   'admin.php no longer redirects, so an old bookmark 404s');

/* And the nav offers all three. */
$hd = file_get_contents(__DIR__ . '/header.php');
foreach ($PAGES as $f) ok(strpos($hd, $f) !== false, "the nav has no link to $f");
ok(preg_match('/user_id\'\]\s*===?\s*1/', $hd) === 1, 'the Admin nav is not gated to user 1');

echo "\n" . ($fail ? "FAILED ($fail)\n" : "all admin-lib checks passed\n");
exit($fail ? 1 : 0);
