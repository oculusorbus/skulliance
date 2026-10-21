<?php
/**
 * dropship-audio-harness.php -- which sound a round plays.
 *
 * Run: php dropship-audio-harness.php
 *
 * displayRound() carried thirty-four if-branches matching caption text
 * against sound filenames, with every reskin's own words in there beside
 * Drop Ship's -- Vibrator, Whip, Paddle, Ball Gag. All of those are words
 * the wording table already holds, so the branches were the wording table
 * written a second time in a file no admin page could reach.
 *
 * THE FILENAME IS THE WORD PLUS THE FRAME NUMBER, which is the convention
 * the art already uses. demo1 is the activation and demo2 the explosion,
 * so Drop Ship asks for demolition1.mp3 and demolition2.mp3, and a theme
 * calling the thing a Paddle asks for paddle1.mp3 and paddle2.mp3.
 *
 * AND THE KEY IS THE FRAME, NOT THE CAPTION, because machine-gun2 and
 * machine-gun3 share the caption "Machine Gun" and rocket-launcher2 and 3
 * share theirs. Reading the words cannot tell you which picture is up.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($cond, $msg) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $msg\n"; } }

require_once __DIR__ . '/harness-strip.php';   // harness_probe(): cleans up on a fatal too
require_once __DIR__ . '/dropship/dropship-themes.php';
require_once __DIR__ . '/dropship/dropship-art.php';
require_once __DIR__ . '/dropship/dropship-audio.php';

class ARes { public $num_rows; private $r; private $i = 0;
	public function __construct($r) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return $this->i < count($this->r) ? $this->r[$this->i++] : null; } }
class AConn { public $themes; public $text;
	public function __construct($themes = array(), $text = array()) {
		$this->themes = $themes; $this->text = $text; }
	public function query($sql) {
		if (strpos($sql, 'SHOW TABLES') !== false) return new ARes(array(array('t' => 'themes')));
		if (strpos($sql, 'FROM themes WHERE project_id') !== false) {
			preg_match('/project_id = (\d+)/', $sql, $m); $p = (int)$m[1];
			return new ARes(isset($this->themes[$p]) ? array($this->themes[$p]) : array());
		}
		if (strpos($sql, 'FROM themes') !== false) return new ARes(array_values($this->themes));
		if (strpos($sql, 'FROM theme_text') !== false) {
			preg_match('/theme_id = (\d+)/', $sql, $m); $t = (int)$m[1];
			return new ARes(isset($this->text[$t]) ? $this->text[$t] : array());
		}
		return new ARes(array());
	} }
function arow($find, $rep) {
	return array('match_type' => 'exact', 'find' => $find, 'replacement' => $rep); }

$ev  = dropshipAudioEvents();
$fbk = dropshipAudioFallbacks();

echo "\nthe key is the frame, and a frame is only itself\n";
/*
 * NO ORDER TO GET WRONG. The caption chain had to be kept in a sequence
 * it never declared, because "Smoke Bomb" is inside "Pull Smoke Bomb
 * Pin". An exact key cannot shadow another one -- but it CAN be
 * duplicated, which would make one of the two rows unreachable.
 */
$arts = array();
foreach ($ev as $e) {
	ok(!isset($arts[$e['art']]), "two rows claim the frame {$e['art']}, so one can never fire");
	$arts[$e['art']] = $e;
	ok($e['art'] !== '' && strpos($e['art'], '..') === false, "a bad art path: {$e['art']}");
	ok($e['family'] !== '', "the frame {$e['art']} belongs to no thing, so it can never be themed");
}
/* THE PAIRS THAT CAPTIONS COULD NOT SEPARATE, named so the loop above
   cannot quietly stop finding anything. */
foreach (array(array('weapons/machine-gun2', 'weapons/machine-gun3'),
               array('weapons/rocket-launcher2', 'weapons/rocket-launcher3'),
               array('weapons/demo1', 'weapons/demo2')) as $pair) {
	ok(isset($arts[$pair[0]], $arts[$pair[1]]),
	   "{$pair[0]} and {$pair[1]} are not both frames with their own row");
	if (isset($arts[$pair[0]], $arts[$pair[1]])) {
		ok($arts[$pair[0]]['n'] !== $arts[$pair[1]]['n'],
		   "{$pair[0]} and {$pair[1]} carry the same frame number, so they ask for one file");
	}
}
/* The catch-alls are prefixes and must not collide with an exact frame
   they are supposed to lose to. */
foreach ($fbk as $f) {
	ok(substr($f['art'], -1) === '/', "the catch-all {$f['art']} is not a folder prefix");
}

echo "\nevery frame the run shows has a slot\n";
/*
 * SCRAPED FROM THE GAME. Each caption is read off the markup that emits
 * the frame, so a frame added later fails here rather than going
 * quietly silent -- which is how Night Vision Goggles, Radio,
 * Reinforcements, Jet Pack and Drop Box came to have no sound at all.
 */
$ds_src = file_get_contents(__DIR__ . '/dropship/dropship.php');
preg_match_all('/evaluateText\("([^"]+)"\)\."<\/h3>";\s*\n\s*\$dropshipMarkup\.="<img src=.\"\.\$prefix\.\"images\/([a-z0-9\/-]+)\./',
	$ds_src, $fm, PREG_SET_ORDER);
ok(count($fm) >= 30, 'only ' . count($fm) . ' captioned frames found; the scrape is broken');
foreach ($fm as $f) {
	list(, $caption, $art) = $f;
	ok(isset($arts[$art]), "the frame images/$art is captioned \"$caption\" and has no sound row");
	if (isset($arts[$art])) {
		ok($arts[$art]['caption'] === $caption,
		   "images/$art is captioned \"$caption\" but the row says \"{$arts[$art]['caption']}\"");
	}
}
/* AND EVERY ROW IS A FRAME THE MANIFEST HAS, or it asks for art nobody
   ships. */
$man = dropshipArtManifest('gif');
$stems = array();
foreach ($man as $rel) $stems[preg_replace('/\.[a-z0-9]+$/', '', substr($rel, 7))] = true;
foreach ($ev as $e) {
	ok(isset($stems[$e['art']]), "the row for {$e['art']} names art that is not in the manifest");
}

echo "\nthe filename is the word plus the frame number\n";
$c = new AConn(
	array(1 => array('id' => 10, 'project_id' => 1, 'slug' => 'drop-ship',     'name' => 'Drop Ship'),
	      4 => array('id' => 40, 'project_id' => 4, 'slug' => 'oculus-lounge', 'name' => 'Oculus Lounge')),
	array(40 => array(arow('Demolition', 'Paddle'), arow('Melee', 'Vibrator'),
	                  arow('Flamethrower', 'Candle'), arow('Radar', 'Ball Gag'))));
$by = function ($conn, $pid) {
	$o = array(); foreach (dropshipAudioSlots($conn, $pid) as $s) $o[$s['art']] = $s; return $o; };
$ol = $by($c, 4);
$ds = $by($c, 1);
ok($ds['weapons/demo1']['rel'] === 'sounds/demolition1.mp3',
   'the activation frame does not take the frame number: ' . $ds['weapons/demo1']['rel']);
ok($ds['weapons/demo2']['rel'] === 'sounds/demolition2.mp3',
   'the explosion frame does not take the frame number: ' . $ds['weapons/demo2']['rel']);
ok($ol['weapons/demo1']['rel'] === 'sounds/paddle1.mp3',
   "a renamed thing does not carry its own word into the filename: " . $ol['weapons/demo1']['rel']);
ok($ol['weapons/demo2']['rel'] === 'sounds/paddle2.mp3',
   'the second frame of a renamed thing is wrong: ' . $ol['weapons/demo2']['rel']);
ok($ol['weapons/flamethrower4']['rel'] === 'sounds/candle4.mp3',
   'the fourth flamethrower frame is wrong: ' . $ol['weapons/flamethrower4']['rel']);
/* A SINGLE-FRAME THING TAKES NO NUMBER, exactly as melee.gif does. */
ok($ds['weapons/melee']['rel'] === 'sounds/melee.mp3',
   'a single-frame thing was numbered: ' . $ds['weapons/melee']['rel']);
ok($ol['weapons/melee']['rel'] === 'sounds/vibrator.mp3',
   'a renamed single-frame thing is wrong: ' . $ol['weapons/melee']['rel']);
ok($ol['equipment/radar']['rel'] === 'sounds/ball-gag.mp3',
   'a two-word name is not slugged the way an icon is: ' . $ol['equipment/radar']['rel']);

echo "\nthe delay belongs to the frame, not the theme\n";
ok($ds['weapons/smoke-bomb1']['delay'] === 600 && $ol['weapons/smoke-bomb1']['delay'] === 600,
   'the delay changed between themes');
foreach (array('weapons/tactical-katana' => 1000, 'equipment/airstrike2' => 2000,
               'weapons/sniper-rifle' => 1000) as $a => $d) {
	ok($ds[$a]['delay'] === $d, "$a should wait {$d}ms, waits " . var_export($ds[$a]['delay'], true));
}

echo "\nno theme is ever handed Drop Ship's audio\n";
/*
 * Reported twice. First "why are you playing drop ship sounds on filthy
 * mermaid", looking at Demolition -> Paddle playing demo.mp3. Then the
 * gunshot that plays on EVERY surviving round turning up on themes that
 * want nothing of the sort -- the same leak, in the place it is loudest.
 *
 * So the rule is flat: a theme plays its own file or nothing. Drop Ship
 * alone falls back to the old sound-named files, because those ARE its
 * own under a name the convention has not reached yet.
 */
$snd = __DIR__ . '/dropship/sounds';
$made = is_dir($snd) || @mkdir($snd, 0777, true);
ok($made, 'could not make a sounds folder to probe, so the checks below prove nothing');
if ($made) {
	/* harness_probe(), not a local unlink list: a fatal partway through
	   skipped the cleanup once and the leftover went into a commit,
	   which then broke `git pull` on the live server. */
	$put = function ($n) use ($snd) { harness_probe($snd . '/' . $n . '.mp3'); };
	/* Only the old Drop Ship names exist. */
	$put('demo'); $put('enemyguncontra');
	$ds = $by($c, 1); $ol = $by($c, 4);
	ok($ds['weapons/demo1']['plays'] === 'sounds/demo.mp3',
	   "Drop Ship went quiet before its files were renamed: " . $ds['weapons/demo1']['plays']);
	ok($ol['weapons/demo1']['plays'] === '',
	   "another theme was handed Drop Ship's sound: " . $ol['weapons/demo1']['plays']);
	/* THE GUNSHOT, which is the one that plays on every single round. */
	$svds = null; $svol = null;
	foreach (dropshipAudioSlots($c, 1) as $s) if ($s['art'] === 'live/') $svds = $s;
	foreach (dropshipAudioSlots($c, 4) as $s) if ($s['art'] === 'live/') $svol = $s;
	ok($svds && $svds['plays'] === 'sounds/enemyguncontra.mp3',
	   'Drop Ship lost the sound on a surviving round');
	ok($svol && $svol['plays'] === '',
	   "another theme gets a gunshot on every advance: " . ($svol ? $svol['plays'] : '?'));

	/* ITS OWN FILE WINS, and that is how a theme gets its audio back. */
	$put('paddle1');
	$ol = $by($c, 4);
	ok($ol['weapons/demo1']['plays'] === 'sounds/paddle1.mp3',
	   "a theme's own file is not played: " . $ol['weapons/demo1']['plays']);

	/* THE PAYLOAD the browser gets: exact frames, prefix catch-alls, and
	   nothing with an empty file. */
	$pay = dropshipAudioPayload($c, 4);
	ok(isset($pay['frames'], $pay['any'], $pay['open']), 'the payload lost one of its three parts');
	ok(isset($pay['frames']['weapons/demo1']), 'the payload does not key frames by art path');
	ok(!isset($pay['frames']['weapons/demo2']),
	   'a frame with no sound was sent to the browser anyway');
	foreach ($pay['frames'] as $k => $r) {
		ok($r['f'] !== '' && strpos($r['f'], 'sounds/') === 0, "a bad row for $k: " . json_encode($r));
		ok(strpos($r['f'], '?v=') !== false, "the sound for $k carries no cache buster");
	}
	foreach ($pay['any'] as $r) ok(!empty($r['p']), 'a catch-all with no prefix');

	/* WHO ELSE IS LISTENING -- the warning the shared folder needs. */
	$claims = dropshipAudioClaims($c, 'demolition1', 4, 'Demolition');
	ok(in_array('Drop Ship: Demolition', $claims, true),
	   'replacing a shared sound reports nobody else: ' . json_encode($claims));
	ok(!in_array('Oculus Lounge: Paddle', dropshipAudioClaims($c, 'paddle1', 4, 'Demolition'), true),
	   'the warning names the theme doing the replacing, so it nags about itself');

	harness_probe_clean();
}

echo "\nthe opening cannot collide with a frame\n";
/*
 * It is the one slot with no frame to name it, so it is named after the
 * theme -- and Filthy Mermaid's word for the round 1 frame is "Filthy
 * Mermaid", which means a bare slug would have put two different sounds
 * on filthy-mermaid.mp3.
 */
$fmc = new AConn(
	array(33 => array('id' => 330, 'project_id' => 33, 'slug' => 'filthy-mermaid', 'name' => 'Filthy Mermaid')),
	array(330 => array(arow('Drop Ship', 'Filthy Mermaid'))));
$open = dropshipAudioOpenSlot($fmc, 33);
$fstems = array();
foreach (dropshipAudioSlots($fmc, 33) as $sl) if ($sl['stem'] !== '') $fstems[$sl['stem']] = $sl['term'];
ok($open['stem'] !== '', 'the opening has no filename at all');
ok(!isset($fstems[$open['stem']]),
   'the opening asks for the same file as a frame, so two sounds fight over one name');
ok(isset($fstems['filthy-mermaid']),
   'the round 1 frame no longer asks for the theme word, so this check has gone stale');
/* ITS OLD FILE IS ITS OWN, not Drop Ship's: crowd.mp3 was only ever
   Filthy Mermaid's, so falling back to it borrows nothing. */
ok($open['legacy'] === '' || $open['legacy'] !== 'alarm',
   "a theme's opening falls back to Drop Ship's");

echo "\nthe page says what the run does\n";
$adm = (string)@file_get_contents(__DIR__ . '/dropship/admin-art.php');
ok(strpos($adm, "\$as['plays']") !== false,
   'the art page works out what is playing by its own rule instead of reading the run\'s answer');
ok(strpos($adm, 'dropshipAudioSlots($conn, $pid)') !== false,
   'the art page does not list the sound slots at all');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
