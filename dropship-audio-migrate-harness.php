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
preg_match('/function dsamPlan\(\$conn, \$CHAIN, \$OPEN\) \{.*?\n\}/s', $src, $m);
ok(!empty($m), 'dsamPlan() could not be lifted, so nothing below proves anything');
if (empty($m)) { echo "\n1 check(s) FAILED\n"; exit(1); }
eval($m[0]);
preg_match('/\$CHAIN = array\((.*?)\n\);/s', $src, $cm);
preg_match('/\$OPEN = array\((.*?)\);/s', $src, $om);
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
	                  mrow('Pull Smoke Bomb Pin', 'Pull Out Whip'), mrow('Radar', 'Ball Gag'))));
$plan = dsamPlan($conn, $CHAIN, $OPEN);
ok(count($plan) > 20, 'the plan is only ' . count($plan) . ' files; something did not resolve');

echo "\nDrop Ship's own files go onto its own words\n";
foreach (array('demolition1.mp3' => 'demo.mp3',
               'demolition2.mp3' => 'explosioncontra.mp3',
               'smoke-bomb1.mp3' => 'grenadepin.mp3',
               'smoke-bomb2.mp3' => 'grenadecontra.mp3',
               'machine-gun2.mp3' => 'gunfirecontra.mp3',
               'machine-gun3.mp3' => 'gunfirecontra.mp3',
               'survived.mp3' => 'enemyguncontra.mp3',
               'drop-ship-opening.mp3' => 'alarm.mp3') as $t => $s) {
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
               'oculus-lounge-opening.mp3' => 'flyingcar.mp3') as $t => $s) {
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

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
