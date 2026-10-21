<?php
/**
 * dropship-audio-migrate-harness.php -- the one-off audio migration.
 *
 * Run: php dropship-audio-migrate-harness.php
 *
 * dropship/audio-migrate.php copies real files on a live server. It only
 * ever copies and it refuses to overwrite, so the damage it can do is
 * bounded -- but writing the WRONG source onto a name is still somebody
 * hearing a paddle where a flamethrower should be, and it is tedious to
 * undo across four themes. This drives the part that decides.
 *
 * THE PLAN IS BUILT FROM THE LIVE WORDING, which is the whole reason the
 * script exists rather than a list: rename a thing in the theme admin
 * and the name it asks for changes with it.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($cond, $msg) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $msg\n"; } }

require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/dropship/dropship-themes.php';
require_once __DIR__ . '/dropship/dropship-art.php';
require_once __DIR__ . '/dropship/dropship-audio.php';

/* The script bootstraps a database and then acts; lift the planner out
   rather than running any of that. */
$src = file_get_contents(__DIR__ . '/dropship/audio-migrate.php');
/* The signature carries defaults now, so match it loosely -- the close
   paren inside array() defeats anything tighter. */
preg_match('/function dsamPlan\(.*?\n\}/s', $src, $m);
ok(!empty($m), 'dsamPlan() could not be lifted, so nothing below proves anything');
if (empty($m)) { echo "\n1 check(s) FAILED\n"; exit(1); }
eval($m[0]);
preg_match('/\$CHAIN = array\((.*?)\n\);/s', $src, $cm);
preg_match('/\$OPEN = array\((.*?)\);/s', $src, $om);
preg_match('/\$GUARDED = array\((.*?)\n\);/s', $src, $gm);
preg_match('/\$ROUNDS = array\((.*?)\n\);/s', $src, $rm);
ok(!empty($gm) && !empty($rm), 'the guard list or the round map could not be lifted');
eval('$GUARDED = array(' . $gm[1] . "\n);");
eval('$ROUNDS = array(' . $rm[1] . "\n);");
ok(!empty($cm) && !empty($om), 'the chain or the opening map could not be lifted');
eval('$CHAIN = array(' . $cm[1] . "\n);");
eval('$OPEN = array(' . $om[1] . ');');

class MRes { public $num_rows; private $r; private $i = 0;
	public function __construct($r) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return $this->i < count($this->r) ? $this->r[$this->i++] : null; } }
class MConn { public $themes; public $text;
	public function __construct($t, $x) { $this->themes = $t; $this->text = $x; }
	public function query($sql) {
		if (strpos($sql, 'SHOW TABLES') !== false) return new MRes(array(array('t' => 'themes')));
		if (strpos($sql, 'FROM themes WHERE project_id') !== false) {
			preg_match('/project_id = (\d+)/', $sql, $m); $p = (int)$m[1];
			return new MRes(isset($this->themes[$p]) ? array($this->themes[$p]) : array());
		}
		if (strpos($sql, 'FROM themes') !== false) return new MRes(array_values($this->themes));
		if (strpos($sql, 'FROM theme_text') !== false) {
			preg_match('/theme_id = (\d+)/', $sql, $m); $t = (int)$m[1];
			return new MRes(isset($this->text[$t]) ? $this->text[$t] : array());
		}
		return new MRes(array());
	} }
function mrow($f, $r) { return array('match_type' => 'exact', 'find' => $f, 'replacement' => $r); }

$conn = new MConn(
	array(1 => array('id' => 10, 'project_id' => 1, 'slug' => 'drop-ship',     'name' => 'Drop Ship'),
	      4 => array('id' => 40, 'project_id' => 4, 'slug' => 'oculus-lounge', 'name' => 'Oculus Lounge')),
	array(40 => array(mrow('Demolition', 'Paddle'), mrow('Explosion', 'Spank'),
	                  mrow('Melee', 'Vibrator'), mrow('Smoke Bomb', 'Whip'),
	                  mrow('Pull Smoke Bomb Pin', 'Pull Out Whip'), mrow('Radar', 'Ball Gag'),
	                  /* Round 0 is a frame now, so its name comes from its
	                     caption the way every other one does. */
	                  mrow('Drop Ship Initiating', 'Oculus Lounge Entrance'))));
$plan = dsamPlan($conn, $CHAIN, $OPEN, $GUARDED, $ROUNDS);
ok(count($plan) > 20, 'the plan is only ' . count($plan) . ' files; something did not resolve');

echo "\nDrop Ship's own files go onto its own words\n";
foreach (array('demolition1.mp3' => 'demo.mp3',
               'demolition2.mp3' => 'explosioncontra.mp3',
               'smoke-bomb1.mp3' => 'grenadepin.mp3',
               'smoke-bomb2.mp3' => 'grenadecontra.mp3',
               'machine-gun2.mp3' => 'gunfirecontra.mp3',
               'machine-gun3.mp3' => 'gunfirecontra.mp3',
               'survived.mp3' => 'enemyguncontra.mp3',
               'drop-ship-initiating.mp3' => 'alarm.mp3') as $t => $s) {
	ok(isset($plan[$t]), "$t is not in the plan at all");
	if (isset($plan[$t])) ok($plan[$t]['src'] === $s,
		"$t should come from $s, plan says " . $plan[$t]['src']);
}
/* THE TWO FRAMES OF ONE WEAPON MUST NOT SHARE A TARGET, which is the
   thing the numbering exists for. */
ok($plan['demolition1.mp3']['src'] !== $plan['demolition2.mp3']['src'],
   'both demolition frames take the same sound, so the numbering bought nothing');

echo "\na renamed thing takes its own word, and the sound it has today\n";
foreach (array('paddle1.mp3' => 'spankme.mp3',
               'paddle2.mp3' => 'spank.mp3',
               'whip1.mp3' => 'spankme.mp3',
               'whip2.mp3' => 'spank.mp3',
               'vibrator.mp3' => 'vibrator.mp3',
               'ball-gag.mp3' => 'ballgag.mp3',
               'oculus-lounge-entrance.mp3' => 'flyingcar.mp3') as $t => $s) {
	if ($t === 'vibrator.mp3') {  /* already correctly named: nothing to do */
		ok(!isset($plan[$t]), 'a file already on the right name is scheduled for a pointless copy');
		continue;
	}
	ok(isset($plan[$t]), "$t is not in the plan at all");
	if (isset($plan[$t])) ok($plan[$t]['src'] === $s,
		"$t should come from $s, plan says " . $plan[$t]['src']);
}
/* AND NEVER ONTO ITSELF. */
foreach ($plan as $t => $w) ok($w['src'] !== $t, "$t is scheduled to be copied onto itself");

echo "\nthe plan says who wants each file\n";
ok(!empty($plan['demolition1.mp3']['why']), 'a row does not say which theme wants it');
$why = array_keys($plan['demolition1.mp3']['why']);
ok(strpos(implode(' ', $why), 'Drop Ship') !== false,
   'the reason does not name the theme: ' . implode(', ', $why));

echo "\nno theme is handed Drop Ship's audio by the migration\n";
/*
 * THE BUG THE DRY RUN CAUGHT, and the reason the live output was worth
 * reading before --go. A dozen branches were wrapped in
 * `project_id != 3 && != 4`, so Filthy Mermaid and Oculus Lounge were
 * SILENT on the flamethrower, the machine gun, the rocket launcher, the
 * sniper and the air strike. The first planner read "the chain says
 * nothing" as "use Drop Ship's old file", which would have copied
 * flamethrower1.mp3 onto candle1.mp3 and handed those themes the exact
 * audio the whole rework exists to keep away from them.
 */
$guard_conn = new MConn(
	array(1 => array('id' => 10, 'project_id' => 1, 'slug' => 'drop-ship',     'name' => 'Drop Ship'),
	      3 => array('id' => 30, 'project_id' => 3, 'slug' => 'filthy-mermaid','name' => 'Filthy Mermaid')),
	array(30 => array(mrow('Flamethrower', 'Candle'), mrow('Machine Gun', 'Money Gun'),
	                  mrow('Rocket Launcher', 'Double Dildo'), mrow('Drop Ship', 'Filthy Mermaid'),
	                  mrow('Drop Ship Landed', 'Filthy Mermaid Doorway'))));
$gp = dsamPlan($guard_conn, $CHAIN, $OPEN, $GUARDED, $ROUNDS);
/*
 * double-dildo* IS NOT IN THIS LIST, deliberately. The old chain tested
 * "Dildo" before it ever reached the guarded rocket launcher branches,
 * so those five frames really do play melee.mp3 today -- an accident of
 * branch order, but a true one. The script copies it and prints a note
 * counting any sound about to cover three or more frames, which is the
 * honest way to hand that decision over.
 */
foreach (array('candle1.mp3', 'candle4.mp3', 'money-gun2.mp3') as $t) {
	ok(isset($gp[$t]), "$t fell out of the plan entirely; this check is proving nothing");
	if (isset($gp[$t])) {
		ok(!empty($gp[$t]['skip']),
		   "$t is scheduled from " . $gp[$t]['src'] . ", which is Drop Ship's sound for a frame "
		 . 'this theme was silent on');
	}
}
/* AND DROP SHIP ITSELF STILL GETS ITS OWN, by the same rule. */
foreach (array('flamethrower1.mp3' => 'flamethrower1.mp3') as $t => $_) {
	/* Drop Ship's flamethrower is already correctly named, so there is
	   nothing to copy -- which is itself the right answer. */
	ok(!isset($gp[$t]), 'a file already on the right name is scheduled for a pointless copy');
}
ok(isset($gp['machine-gun2.mp3']) && empty($gp['machine-gun2.mp3']['skip'])
   && $gp['machine-gun2.mp3']['src'] === 'gunfirecontra.mp3',
   "Drop Ship's own machine gun was skipped along with the themes that were silent");

/* ROUNDS 1 AND 2 were keyed on the round NUMBER, not the caption, so
   they are not in the chain: Filthy Mermaid had door and stairs, and
   handing it dropshipcontra is the same leak. */
ok(isset($gp['filthy-mermaid.mp3']) && $gp['filthy-mermaid.mp3']['src'] === 'door.mp3',
   'the round 1 sound for Filthy Mermaid is '
 . (isset($gp['filthy-mermaid.mp3']) ? $gp['filthy-mermaid.mp3']['src'] : 'absent') . ', not door.mp3');
ok(isset($gp['filthy-mermaid-doorway.mp3'])
   && $gp['filthy-mermaid-doorway.mp3']['src'] === 'stairs.mp3',
   'the round 2 sound for Filthy Mermaid is not stairs.mp3');
ok(isset($gp['drop-ship.mp3']) && $gp['drop-ship.mp3']['src'] === 'dropshipcontra.mp3',
   "Drop Ship's own round 1 sound is wrong");
/* The accident is kept, and the script says so rather than hiding it. */
ok(isset($gp['double-dildo4.mp3']) && empty($gp['double-dildo4.mp3']['skip']),
   'the rocket launcher frames that really do play melee.mp3 today were dropped');
ok(strpos($src, 'WORTH A LOOK') !== false && strpos($src, 'count($ts) >= 3') !== false,
   'nothing counts a sound about to cover several frames, so it passes unnoticed');

/*
 * A SHARED NAME NEEDS ONE AGREED ANSWER. Every theme calls a surviving
 * round "Survived", so all four ask for survived.mp3 -- and two of them
 * were silent there. Copying anything into that name gives the gunshot
 * to all four, which is the complaint that started this.
 */
$all = new MConn(
	array(1 => array('id' => 10, 'project_id' => 1, 'slug' => 'drop-ship',      'name' => 'Drop Ship'),
	      3 => array('id' => 30, 'project_id' => 3, 'slug' => 'filthy-mermaid', 'name' => 'Filthy Mermaid')),
	array(30 => array()));
$ap = dsamPlan($all, $CHAIN, $OPEN, $GUARDED, $ROUNDS);
ok(isset($ap['survived.mp3']), 'the catch-all fell out of the plan; this check proves nothing');
ok(!empty($ap['survived.mp3']['skip']),
   'survived.mp3 is scheduled, which gives every theme the gunshot on every round');
/*
 * AND A SHARED NAME WHERE THE THEMES HAD DIFFERENT SOUNDS. A
 * half-translated theme does this: both call the Demolition a Paddle,
 * so both ask for paddle2.mp3, but one renamed the second frame's
 * caption to "Spank" and the other left it as "Explosion". One of them
 * would get the other's audio, and which one would depend on the order
 * the themes came out of the database.
 */
/* FRESH PROJECT IDS: dropshipThemeConfig() and the dictionaries both
   cache per id for the request, so reusing 3 and 4 here would answer
   with the stubs an earlier section set. */
$split = new MConn(
	array(103 => array('id' => 1030, 'project_id' => 103, 'slug' => 'half-a',  'name' => 'Half A'),
	      104 => array('id' => 1040, 'project_id' => 104, 'slug' => 'half-b',  'name' => 'Half B')),
	array(1030 => array(mrow('Demolition', 'Paddle'), mrow('Explosion', 'Spank')),
	      1040 => array(mrow('Demolition', 'Paddle'))));
$sp = dsamPlan($split, $CHAIN, $OPEN, $GUARDED, $ROUNDS);
ok(isset($sp['paddle2.mp3']), 'the clashing target fell out of the plan; this proves nothing');
ok(!empty($sp['paddle2.mp3']['skip']),
   'two themes wanting different sounds at one filename is resolved by whichever came last');
ok(isset($sp['paddle1.mp3']) && empty($sp['paddle1.mp3']['skip']),
   'the frame they DO agree on was skipped too');

/* But a shared name every theme DID have stays. */
ok(isset($ap['extra-life.mp3']) && empty($ap['extra-life.mp3']['skip']),
   'a shared file that every theme already played was skipped too');

echo "\nthe script itself cannot destroy anything\n";
$body = harness_strip_comments($src);
ok(strpos($body, 'rename(') === false && strpos($body, 'unlink(') === false,
   'the migration script can move or delete a file; it must only ever copy');
ok(strpos($body, 'if (is_file($dir . $target)) {') !== false,
   'the script does not check the target before writing, so it can overwrite');
ok(strpos($body, "in_array('--go', \$argv, true)") !== false,
   'the script has no dry run, so reading it is the only way to know what it will do');
ok(strpos($body, '@chmod($dir . $target, 0644)') !== false,
   'a copied file keeps the source mode, and an unreadable one is a silent 404');
ok(strpos($body, "PHP_SAPI !== 'cli'") !== false,
   'the migration script is reachable over the web');
/* The planner marks a row to skip; the loop that writes has to honour
   it, and that loop needs a database so it is read rather than run. */
ok(strpos($body, "if (!empty(\$w['skip'])) {") !== false,
   'the writing loop ignores the skip the planner set, so it copies anyway');
ok(strpos($body, 'count($ts) >= 3') !== false,
   'nothing counts a sound about to cover several frames, so it passes unnoticed');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
