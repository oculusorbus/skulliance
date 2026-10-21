<?php
/*
 * dhcarena-bench-harness.php — CLI only. The Fighter cooldown, with no database.
 *
 * WHY THIS EXISTS. A Fighter that won and SURVIVED came back available
 * immediately, while one that fell was correctly unavailable for hours. Two
 * separate causes, both invisible to php -l and to every other harness:
 *
 *   1. dhca_crew() read a MySQL-written benched_until with PHP's strtotime()
 *      and compared it against PHP's time(). The host's MySQL clock runs
 *      behind php.ini's UTC, so a 4-hour bench came back already expired and
 *      an 8-to-16-hour one did not.
 *   2. dhca_bench()'s missing-column fallback ran on EVERY bench -- `if ($ok)`
 *      tested a variable nothing assigned -- so wins and losses were
 *      incremented twice per battle.
 *
 * Both are tested by driving the REAL functions against a $conn stand-in that
 * records what was asked and answers with rows of our choosing. The point of
 * the stand-in is that the rows can DISAGREE with PHP's clock: that is the only
 * way to prove which clock decided.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/* A warning is a failure here. The $ok bug emitted "Undefined variable $ok" on
   every battle that ended, and dhc_json() swallowed it -- so nothing anybody
   could see said the fallback was firing. */
$GLOBALS['bh_warn'] = array();
set_error_handler(function($no, $str, $file, $line) {
	$GLOBALS['bh_warn'][] = "$str ($file:$line)";
	return true;
});

require __DIR__ . '/dhcarena-lib.php';

$fail = 0;
function bh_ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok   $what\n"; return true; }
	$fail++;  echo "  FAIL $what\n";  return false;
}

/* ---------- the $conn stand-in ---------------------------------------------- */

class BhResult {
	private $rows; public $num_rows;
	public function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	public function fetch_assoc() { return array_shift($this->rows); }
}
class BhConn {
	public $log = array();        // every query, in order
	public $crew = array();       // rows the crew SELECT answers with
	public $fail_first = false;   // make the INSERT naming `ko` fail
	public $insert_id = 0;
	public function real_escape_string($s) { return addslashes($s); }
	public function query($sql) {
		$this->log[] = $sql;
		if (strpos($sql, 'FROM dhc_fighters f') !== false) return new BhResult($this->crew);
		if (strpos($sql, 'INSERT INTO dhc_arena_fighters') !== false)
			return !($this->fail_first && strpos($sql, ',ko,') !== false);
		return false;             // every aggregate: tolerated, all Unproven
	}
}

/* One crew row, with the two numbers set independently. $until is the stored
   DATETIME string and $left is what MySQL says is left of it -- a real database
   would never disagree with itself, and the disagreement is the instrument. */
function bh_row($id, $until, $left, $ko) {
	return array('id'=>$id, 'serial'=>$id, 'name'=>'F'.$id, 'traits'=>'{}',
	             'rarity_score'=>100, 'benched_until'=>$until, 'ko'=>$ko,
	             'wins'=>0, 'losses'=>0, 'season_wins'=>0, 'season_losses'=>0,
	             'bench_left'=>$left);
}

/* ---------- 1. the clock that wrote it is the clock that reads it ------------ */

echo "the bench is measured by MySQL, not by PHP\n";

$c = new BhConn();
/* THE REPORTED BUG, exactly. benched_until reads as four hours in the PAST
   against PHP's clock -- which is what the host's MySQL hands back for a
   4-hour bench written seconds ago -- while MySQL itself still says 4 hours
   are left. The old code called this Fighter available. */
$c->crew = array(bh_row(1, date('Y-m-d H:i:s', time() - 4 * 3600), 14400, 0));
$crew = dhca_crew($c, 7);
bh_ok(count($crew) === 1, 'the crew came back');
bh_ok(isset($crew[0]['available']) && $crew[0]['available'] === false,
   'a survivor whose stored time looks past to PHP is still RECOVERING');
bh_ok(isset($crew[0]['bench_left']) && $crew[0]['bench_left'] === 14400,
   'and the countdown is the one MySQL measured, not PHP arithmetic');
bh_ok(empty($crew[0]['fell']), 'ko 0 reads as recovering, not resurrecting');

/* AND THE OTHER DIRECTION, so this is not a one-sided clamp: a stored time in
   PHP's FUTURE with nothing left on MySQL's clock is available. Fail this and
   the fix is just "always benched". */
$c = new BhConn();
$c->crew = array(bh_row(2, date('Y-m-d H:i:s', time() + 4 * 3600), 0, 1));
$crew = dhca_crew($c, 7);
bh_ok(isset($crew[0]['available']) && $crew[0]['available'] === true,
   'an expired bench whose stored time looks future to PHP is available');
bh_ok($crew[0]['bench_left'] === 0, 'and nothing is left on its countdown');

/* A Fighter that has never fought has no row at all: the LEFT JOIN gives NULLs,
   and COALESCE is what keeps that from reading as "benched forever". */
$c = new BhConn();
$c->crew = array(bh_row(3, null, 0, null));
$crew = dhca_crew($c, 7);
bh_ok($crew[0]['available'] === true, 'a Fighter that has never fought is available');

/* The remainder is ASKED. Driving the function cannot distinguish "MySQL
   measured it" from "the stub happened to agree", so the query itself is
   checked -- and checked for the comparison, not for the word NOW(), which
   appears in it either way. */
$c = new BhConn();
$c->crew = array(bh_row(4, null, 0, null));
dhca_crew($c, 7);
$sel = $c->log[0];
bh_ok(strpos($sel, 'TIMESTAMPDIFF(SECOND, NOW(), a.benched_until)') !== false,
   'the SELECT asks MySQL how many seconds are left');
bh_ok(strpos($sel, 'AS bench_left') !== false, 'and names it bench_left');

/* No clock arithmetic left in the function. strpos on the whole file would pass
   on any other function's strtotime, so the body is cut out first -- and the
   COMMENTS are cut out of that, because the comment above dhca_crew() names
   both strtotime() and time() while explaining why neither is called. A check
   that its own explanation satisfies is not a check. */
$lib  = file_get_contents(__DIR__ . '/dhcarena-lib.php');
$from = strpos($lib, 'function dhca_crew($conn, $user_id) {');
$body = substr($lib, $from, strpos($lib, "\nfunction dhca_available", $from) - $from);
$code = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '', $body);
bh_ok(strpos($code, 'strtotime') === false, 'dhca_crew() no longer parses a date in PHP');
bh_ok(strpos($code, 'time()') === false,   'and no longer reads PHP\'s clock');
bh_ok(strpos($body, 'strtotime') !== false,
   'the comment explaining the two clocks is still there to find');

/* The missing-column retry still strips ko and still keeps the countdown --
   stripping the wrong substring would take bench_left with it and leave every
   Fighter available for good. */
bh_ok(strpos(str_replace('a.ko, ', '', $sel), 'a.ko') === false,
   'the ko retry really does remove the column');
bh_ok(strpos(str_replace('a.ko, ', '', $sel), 'AS bench_left') !== false,
   'and the retry keeps the countdown');

/* ---------- 2. the fallback insert only runs when the first one failed ------- */

echo "\nthe bench is written once\n";

$c = new BhConn();
dhca_bench($c, 7, 1, DHCA_BENCH_BASE_H, true);
$ins = array_values(array_filter($c->log, function($q){
	return strpos($q, 'INSERT INTO dhc_arena_fighters') !== false; }));
bh_ok(count($ins) === 1, 'a bench that succeeded writes ONE row, not two');
bh_ok(count($ins) && strpos($ins[0], ',ko,') !== false, 'and it carries the ko flag');
/* The double write was invisible except in the counters, so check the thing it
   corrupted: wins must be incremented exactly once per battle. */
bh_ok(substr_count(implode("\n", $ins), 'wins=wins+1') === 1,
   'wins is incremented once, not twice');

$c = new BhConn();
$c->fail_first = true;
dhca_bench($c, 7, 1, DHCA_BENCH_BASE_H, true);
$ins = array_values(array_filter($c->log, function($q){
	return strpos($q, 'INSERT INTO dhc_arena_fighters') !== false; }));
bh_ok(count($ins) === 2, 'a database with no ko column still gets the bench written');
bh_ok(count($ins) > 1 && strpos($ins[1], ',ko,') === false,
   'and the second attempt leaves the column out');
bh_ok(count($ins) > 1 && strpos($ins[1], 'benched_until=VALUES(benched_until)') !== false,
   'the fallback still ends the battle: it sets benched_until');

/* A loss still benches for longer than a survival, which is the rule the whole
   label depends on. Driven, not restated: the two intervals are read back out
   of the SQL that was actually built. */
function bh_minutes($sql) {
	return preg_match('/INTERVAL (\d+) MINUTE/', $sql, $m) ? (int)$m[1] : -1;
}
$c = new BhConn(); dhca_bench($c, 7, 1, DHCA_BENCH_BASE_H, true);
$surv = bh_minutes($c->log[0]);
$c = new BhConn(); dhca_bench($c, 7, 1, DHCA_BENCH_BASE_H + DHCA_BENCH_LOSS_MIN, false);
$fallen = bh_minutes($c->log[0]);
bh_ok($surv === DHCA_BENCH_BASE_H * 60, 'a survivor is benched for the flat base');
bh_ok($fallen > $surv, 'a Fighter that fell is benched for longer');
bh_ok(strpos($c->log[0], ",1,") !== false, 'and is stored with ko set');

/* ---------- 3. nothing emitted a warning ------------------------------------ */

echo "\nquiet\n";
bh_ok(empty($GLOBALS['bh_warn']), 'no notice or warning from any of the above');
foreach ($GLOBALS['bh_warn'] as $w) echo "       $w\n";

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all cooldown checks passed\n");
exit($fail ? 1 : 0);
