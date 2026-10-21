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

echo "\nthe Set button finds the row it was pressed on\n";
/*
 * REPORTED: picked melee for Dread City's Machete, pressed Set, nothing
 * happened. The resolver compared $e['term'] against the posted value
 * and events have no 'term' key -- they have 'family' -- so it matched
 * nothing, every Set resolved to null and the handler bailed on "that
 * is not a sound this theme asks for".
 *
 * DRIVEN, not grepped. The handler itself needs a request and a
 * database, but the lookup it depends on is a function now and this
 * calls it with exactly what the form posts. The old version was
 * covered by source assertions alone, which is precisely why a dead
 * array key sat there unnoticed.
 */
$sconn = new AConn(
	array(102 => array('id' => 1020, 'project_id' => 102, 'slug' => 'dread-city', 'name' => 'Dread City')),
	array(1020 => array(arow('Tactical Katana', 'Machete'), arow('Flamethrower', 'Torch'))));
$mach = dropshipAudioSlotByArt($sconn, 102, 'weapons/tactical-katana');
ok($mach !== null, 'the katana row does not resolve, so pressing Set on it does nothing');
ok($mach && $mach['stem'] === 'machete',
   'the katana row resolves to ' . ($mach ? $mach['stem'] : 'nothing') . ', not machete');
/* EVERY ROW THE PAGE RENDERS MUST RESOLVE, with exactly the value the
   form puts in the field. */
foreach (dropshipAudioSlots($sconn, 102) as $sl) {
	$posted = $sl['art'] !== '' ? $sl['art'] : 'Opening';
	$back = dropshipAudioSlotByArt($sconn, 102, $posted);
	ok($back !== null, "the row for '$posted' does not resolve, so its Set button does nothing");
	if ($back) ok($back['stem'] === $sl['stem'],
		"'$posted' resolves to {$back['stem']} but the row shows {$sl['stem']}");
}
$op = dropshipAudioSlotByArt($sconn, 102, 'Opening');
ok($op !== null && $op['stem'] === 'dread-city-opening', 'the opening row does not resolve');
ok(dropshipAudioSlotByArt($sconn, 102, 'weapons/not-a-frame') === null,
   'a frame this theme does not have still resolves, so a posted value reaches the disk');

/* FOUR FRAMES OF ONE THING ARE FOUR ROWS, and the family cannot tell
   them apart -- which is the other half of why the key is the art. */
$f = array();
foreach (array(1,2,3,4) as $n) {
	$sl = dropshipAudioSlotByArt($sconn, 102, 'weapons/flamethrower' . $n);
	ok($sl !== null, "flamethrower frame $n does not resolve");
	if ($sl) $f[$sl['stem']] = true;
}
ok(count($f) === 4, 'the four flamethrower frames resolve to ' . count($f) . ' files, not four');
ok(isset($f['torch3']), 'the third flamethrower frame is not torch3: ' . implode(', ', array_keys($f)));

/* AND THE FORM POSTS THAT KEY. The lookup being right is half of it. */
$adm_k = (string)@file_get_contents(__DIR__ . '/dropship/admin-art.php');
ok(strpos($adm_k, 'name="audio_art"') !== false,
   'the form does not post the frame, so the lookup has nothing to find');
/* ON THE audio_art INPUT ITSELF. The confirm field uses the same
   expression, so looking for the expression anywhere in the file passes
   with the posted value swapped back to the family. */
ok(preg_match('/name="audio_art" value="<\?php echo \$h\(\$as\[\x27art\x27\]/', $adm_k) === 1,
   'the posted value is not the art path the resolver expects');
ok(strpos($adm_k, 'dropshipAudioSlotByArt($conn, $pid, (string)$_POST[\'audio_art\'])') !== false,
   'the handler does not resolve the posted frame');

echo "\nnothing is overwritten without a warning first\n";
/*
 * The question was exactly this: does picking an existing sound copy it
 * and leave the original alone, does an upload land on the right name,
 * and does anything that destroys a file say so first.
 *
 * COPY, NOT MOVE, for the picker. An icon with a new name is RENAMED,
 * because the old name is then asked for by nobody; a sound picked from
 * the list is still being asked for under its own name by whoever led
 * you to it, so it has to be copied.
 */
$adm_w = (string)@file_get_contents(__DIR__ . '/dropship/admin-art.php');
$a_at  = strpos($adm_w, "isset(\$_POST['audio_art'])");
ok($a_at !== false, 'the audio handler is gone; nothing below proves anything');
$a_blk = $a_at === false ? '' : substr($adm_w, $a_at, 3000);

ok(strpos($a_blk, '@copy($a_dir . $a_use, $a_dest)') !== false,
   'picking an existing sound moves it, which takes it away from whatever else asks for it');
ok(strpos($a_blk, 'rename(') === false && strpos($a_blk, 'unlink(') === false,
   'the audio handler can move or delete a file; it must only ever write to the derived name');
/* THE DESTINATION IS THE DERIVED NAME, never anything off the form --
   an upload keeps nothing of whatever the file was called. */
ok(strpos($a_blk, "\$a_dest = \$a_dir . \$a_slot['stem'] . '.mp3';") !== false,
   'the destination is not the name the wording asks for');
ok(strpos($a_blk, "dropshipAudioSlotByArt(\$conn, \$pid, (string)\$_POST['audio_art'])") !== false,
   'the slot is taken off the form without being resolved, so a posted name reaches the disk');
ok(strpos($a_blk, "basename((string)\$_POST['audio_use'])") !== false,
   'the picked filename is not reduced to a basename, so a path could escape the folder');
ok(strpos($a_blk, "in_array(\$a_use, dropshipAudioFiles(), true)") !== false,
   'the picked file is not checked against the folder, so any readable path could be copied in');

/*
 * ANY OVERWRITE WARNS. The first version asked only when some OTHER
 * theme also wanted the file, so replacing a sound nothing else used
 * happened silently -- and a file gone by accident is a file gone.
 */
ok(strpos($a_blk, '$a_go = (!$a_overwrite || !empty($_POST[\'confirm\']));') !== false,
   'an overwrite can still go through unwarned when no other theme shares the file');
ok(strpos($a_blk, '$a_overwrite = is_file($a_dest);') !== false,
   'nothing checks whether the destination already exists');
ok(strpos($a_blk, 'already exists and will be ') !== false,
   'the refusal does not say that a file is about to be replaced');
ok(strpos($a_blk, 'which also changes it for ') !== false,
   'the refusal does not name who else loses the sound they had');
/* AND A NEW NAME IS NOT A WARNING. Creating a file destroys nothing;
   asking there would train somebody to click through. */
ok(strpos($a_blk, '!$a_overwrite ||') !== false,
   'writing a name that does not exist yet also asks for confirmation');

/*
 * THE CONFIRM IS ARMED BY A REFUSAL, not by having posted. Keyed off
 * the post alone it stayed armed after a SUCCESSFUL write, so the next
 * thing done to that row would have overwritten without asking.
 */
ok(strpos($adm_w, "\$msg['warn'] = \$a_slot['art'];") !== false,
   'the refusal does not record WHICH row it refused');
ok(strpos($adm_w, "!empty(\$msg['warn']) && \$msg['warn'] === (\$as['art']") !== false,
   'the confirm is armed by having posted rather than by having been refused');
ok(strpos($adm_w, "\$_POST['audio_term']") === false,
   'the old family-keyed field is still there, so the lookup has two masters');

echo "\nthe page says what the run does\n";
$adm = (string)@file_get_contents(__DIR__ . '/dropship/admin-art.php');
ok(strpos($adm, "\$as['plays']") !== false,
   'the art page works out what is playing by its own rule instead of reading the run\'s answer');
ok(strpos($adm, 'dropshipAudioSlots($conn, $pid)') !== false,
   'the art page does not list the sound slots at all');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
