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
	/* TWO PARTS, not three: the opening used to be a key of its own and
	   is round 0's frame now, so it travels in 'frames' like the rest. */
	ok(isset($pay['frames'], $pay['any']) && !isset($pay['open']),
	   'the payload shape changed: ' . implode(', ', array_keys($pay)));
	ok(isset($pay['frames']['weapons/demo1']), 'the payload does not key frames by art path');
	ok(!isset($pay['frames']['weapons/demo2']),
	   'a frame with no sound was sent to the browser anyway');
	foreach ($pay['frames'] as $k => $r) {
		ok($r['f'] !== '' && strpos($r['f'], 'sounds/') === 0, "a bad row for $k: " . json_encode($r));
		ok(strpos($r['f'], '?v=') !== false, "the sound for $k carries no cache buster");
	}
	foreach ($pay['any'] as $r) ok(!empty($r['p']), 'a catch-all with no prefix');

	/* WHO ELSE IS LISTENING -- the warning the shared folder needs. */
	/* EXCLUDED BY ART, not by family: two frames of one thing are two
	   files, and excluding by family would hide the other frame from its
	   own warning. */
	$claims = dropshipAudioClaims($c, 'demolition1', 4, 'weapons/demo1');
	ok(in_array('Drop Ship: Demolition', $claims, true),
	   'replacing a shared sound reports nobody else: ' . json_encode($claims));
	/* The claim reads as the theme and the word it uses, which is what a
	   person needs to recognise what they are about to change. */
	ok(dropshipAudioClaims($c, 'paddle1', 4, 'weapons/demo1') === array(),
	   'the warning names the theme doing the replacing, so it nags about itself: '
	 . json_encode(dropshipAudioClaims($c, 'paddle1', 4, 'weapons/demo1')));
	/* AND THE OTHER FRAME OF THE SAME THING IS NOT EXCLUDED WITH IT.
	   Excluding by family rather than by frame would have hidden demo1
	   from demo2's warning, which is the one most easily confused. */
	ok(in_array('Oculus Lounge: Paddle',
	            dropshipAudioClaims($c, 'paddle1', 4, 'weapons/demo2'), true),
	   'excluding one frame hides the other frame of the same thing from its own warning: '
	 . json_encode(dropshipAudioClaims($c, 'paddle1', 4, 'weapons/demo2')));

	harness_probe_clean();
}

echo "\nthe opening is round 0, not a slot of its own\n";
/*
 * Asked why the opening was not associated with a slide. It should have
 * been: it plays on round 0 and round 0 IS a slide -- reset.gif, "the
 * ship arriving", with a caption like every other frame. Modelling it
 * separately gave it no picture to sit under and forced a
 * <slug>-opening.mp3 name invented to dodge a collision with the round
 * 1 frame, whose word for Filthy Mermaid is "Filthy Mermaid".
 */
ok(!function_exists('dropshipAudioOpenSlot'),
   'the opening is still a slot of its own, so it has no frame to sit under');
$fmc = new AConn(
	array(33 => array('id' => 330, 'project_id' => 33, 'slug' => 'filthy-mermaid', 'name' => 'Filthy Mermaid')),
	array(330 => array(arow('Drop Ship', 'Filthy Mermaid'),
	                   arow('Drop Ship Initiating', 'Filthy Mermaid Entrance'))));
$r0 = dropshipAudioSlotByArt($fmc, 33, 'reset/reset');
ok($r0 !== null, 'round 0 has no sound row at all');
ok($r0 && $r0['stem'] === 'filthy-mermaid-entrance',
   'round 0 does not take its own caption: ' . ($r0 ? $r0['stem'] : 'nothing'));
/* AND IT NO LONGER COLLIDES, because it is named by its own caption
   rather than by the theme. */
$r1 = dropshipAudioSlotByArt($fmc, 33, 'drop/drop');
ok($r1 && $r1['stem'] === 'filthy-mermaid', 'round 1 stopped asking for the theme word');
ok($r0 && $r1 && $r0['stem'] !== $r1['stem'],
   'rounds 0 and 1 ask for the same file, so two sounds fight over one name');
/* EACH THEME KEEPS THE OPENING IT ALREADY HAS. Every other row falls
   back only for Drop Ship, because the old name is Drop Ship's; these
   three were per-project from the start, so crowd is Filthy Mermaid's
   own and falling back to it borrows nothing. */
foreach (array(1 => 'alarm', 3 => 'crowd', 4 => 'flyingcar') as $pid => $want) {
	$pc = new AConn(array($pid => array('id' => $pid * 10, 'project_id' => $pid,
	                                    'slug' => 'theme-' . $pid, 'name' => 'T' . $pid)), array());
	$sl = dropshipAudioSlotByArt($pc, $pid, 'reset/reset');
	ok($sl && $sl['legacy'] === $want,
	   "project $pid falls back to " . ($sl ? $sl['legacy'] : '?') . ", not its own $want");
}
/* AND IT ACTUALLY PLAYS IT. The legacy being recorded is not the same
   as the run reaching for it: every other row refuses to fall back
   unless the theme is Drop Ship, and without the own-file exception
   Filthy Mermaid would go quiet on round 0 the day this shipped. */
$snd3 = __DIR__ . '/dropship/sounds';
if (is_dir($snd3) || @mkdir($snd3, 0777, true)) {
	harness_probe($snd3 . '/crowd.mp3');
	$pc3 = new AConn(array(3 => array('id' => 31, 'project_id' => 3,
	                                  'slug' => 'fm-probe', 'name' => 'FM')), array());
	$s3 = dropshipAudioSlotByArt($pc3, 3, 'reset/reset');
	ok($s3 && $s3['plays'] === 'sounds/crowd.mp3',
	   "a theme's own opening is treated as Drop Ship's and refused: "
	 . ($s3 ? var_export($s3['plays'], true) : '?'));
	ok($s3 && !empty($s3['legacy_mine']), 'the slot does not mark the old file as the theme\'s own');
	harness_probe_clean();
}

/* DREAD CITY NEVER HAD AN OPENING and must not acquire one: it is not
   in the per-project list, and the row carries no shared old name for
   it to fall back to instead. */
$dc = new AConn(array(2 => array('id' => 20, 'project_id' => 2, 'slug' => 'dread-city',
                                 'name' => 'Dread City')), array());
$dcs = dropshipAudioSlotByArt($dc, 2, 'reset/reset');
ok($dcs && $dcs['legacy'] === '',
   'Dread City picked up an opening it never had: ' . ($dcs ? $dcs['legacy'] : '?'));
ok($dcs && $dcs['plays'] === '', 'Dread City plays something on round 0 that it did not before');

/* And nothing else in the table has a per-project file: that exception
   exists for one row and must not spread. */
$byproj = 0;
foreach (dropshipAudioEvents() as $e) if (!empty($e['legacy_by'])) $byproj++;
ok($byproj === 1, "$byproj rows carry a per-project fallback; only round 0 should");

echo "\nthe page says what the run does\n";
$adm = (string)@file_get_contents(__DIR__ . '/dropship/admin-art.php');
ok(strpos($adm, "\$as['plays']") !== false,
   'the art page works out what is playing by its own rule instead of reading the run\'s answer');
ok(strpos($adm, 'dropshipAudioPlanRows($conn, $pid, $art_plan)') !== false,
   'the art page does not list the sound slots at all');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
