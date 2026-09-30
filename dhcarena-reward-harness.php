<?php
/* dhcarena-reward-harness.php — CLI only. No database.
 *
 * WHY A WIN SOMETIMES PAYS NOTHING, and whether the page said so.
 *
 * Reported as "I won an arena fight and didn't get a trait -- is it because I
 * fought the same opponent twice, or is it a bug?" It was the first: one
 * rewarded battle per opponent per day. The rule was correct and completely
 * invisible, which is indistinguishable from a bug.
 *
 * TWO FUNCTIONS ASK THE SAME QUESTION and they must never disagree:
 *
 *   dhca_already_rewarded()  per battle, decides whether to pay.
 *   dhca_paid_today()        once for the whole rival list, decides whether to
 *                            mark a rival "beaten today · no trait".
 *
 * If one drifts from the other the player is shown an unmarked rival and then
 * paid nothing for beating them -- the original complaint, with a marker
 * that lies on top of it. So this drives both over the same stubbed battle
 * table and requires the same answer for every defender.
 *
 * THEY FAIL IN OPPOSITE DIRECTIONS ON PURPOSE, which is the one case where
 * they are allowed to differ, and it is asserted rather than assumed: a
 * failed read must never hand out a second trait (so already_rewarded
 * returns TRUE) and must never paint a marker it cannot justify (so
 * paid_today returns NOTHING).
 *
 * Usage: php dhcarena-reward-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* The two functions, read out of the shipped file. dhcarena-lib.php expects a
   session and a live $conn at include time, so the pair is brace-extracted
   rather than the file being loaded. */
function extract_fn($src, $name) {
	$at = strpos($src, 'function ' . $name . '(');
	if ($at === false) return '';
	$open = strpos($src, '{', $at);
	$d = 0;
	for ($i = $open, $n = strlen($src); $i < $n; $i++) {
		if ($src[$i] === '{') $d++;
		elseif ($src[$i] === '}') { $d--; if ($d === 0) return substr($src, $at, $i - $at + 1); }
	}
	return '';
}
$src = file_get_contents(__DIR__ . '/dhcarena-lib.php');
foreach (array('dhca_already_rewarded', 'dhca_paid_today') as $fn) {
	$body = extract_fn($src, $fn);
	if ($body === '') { echo "  FAIL  could not find $fn() in dhcarena-lib.php\n"; exit(1); }
	eval($body);
}

/* A battle table. Rows are [attacker, defender, today?, rewarded?]. */
class BattleRes {
	public $rows;
	function __construct($r) { $this->rows = $r; }
	function fetch_assoc() { return $this->rows ? array_shift($this->rows) : null; }
}
class BattleConn {
	public $rows; public $broken = false;
	function __construct($rows) { $this->rows = $rows; }
	function query($sql) {
		if ($this->broken) return false;
		preg_match('/attacker_id = (\d+)/', $sql, $a);
		$att = isset($a[1]) ? (int)$a[1] : 0;
		$wantToday    = strpos($sql, 'CURDATE()') !== false;
		$wantRewarded = strpos($sql, 'rewarded = 1') !== false;
		$hit = array();
		foreach ($this->rows as $r) {
			list($ra, $rd, $rtoday, $rrew) = $r;
			if ($ra !== $att) continue;
			if ($wantToday && !$rtoday) continue;
			if ($wantRewarded && !$rrew) continue;
			$hit[] = $rd;
		}
		if (preg_match('/defender_id = (\d+)/', $sql, $d)) {
			$hit = array_values(array_filter($hit, function ($x) use ($d) {
				return $x === (int)$d[1]; }));
		}
		if (strpos($sql, 'COUNT(*)') !== false) {
			return new BattleRes(array(array('c' => count($hit))));
		}
		$out = array();
		foreach (array_unique($hit) as $x) $out[] = array('defender_id' => $x);
		return new BattleRes($out);
	}
}

/* ---------- 1. the two agree ------------------------------------------------ */
echo "agreement\n";
$ME = 7;
$rows = array(
	array(7, 11, true,  true),    // beaten today AND paid  -> no second trait
	array(7, 11, true,  false),   // and a rematch that paid nothing
	array(7, 12, true,  false),   // fought today, never paid (a loss)
	array(7, 13, false, true),    // paid, but YESTERDAY -> pays again today
	array(7, 14, true,  true),
	array(9, 15, true,  true),    // somebody else's battle
);
$conn = new BattleConn($rows);
$paid = dhca_paid_today($conn, $ME);
printf("  marked as already paid today: %s\n",
	$paid ? implode(', ', array_keys($paid)) : '(none)');

$expect = array(11 => true, 12 => false, 13 => false, 14 => true, 15 => false, 99 => false);
foreach ($expect as $def => $want) {
	$one  = dhca_already_rewarded($conn, $ME, $def);
	$many = isset($paid[$def]);
	ok($one === $want, "rival $def: dhca_already_rewarded said " . var_export($one, true)
	                 . ', expected ' . var_export($want, true));
	ok($one === $many, "rival $def: the per-battle rule and the rival-list marker DISAGREE "
	                 . '-- a player would be shown one thing and paid another');
}
ok(!isset($paid[13]), "yesterday's payout must not block today's");
ok(!isset($paid[15]), "another player's battles leaked into this player's markers");

/* ---------- 2. the deliberate asymmetry on failure -------------------------- */
echo "\nwhen the query fails\n";
$conn->broken = true;
$one  = dhca_already_rewarded($conn, $ME, 11);
$many = dhca_paid_today($conn, $ME);
printf("  already_rewarded -> %s | paid_today -> %d marker(s)\n",
	var_export($one, true), count($many));
ok($one === true,
   'a failed read must FAIL CLOSED and refuse the payout -- returning false '
 . 'hands out an extra trait for as long as the query keeps failing');
ok($many === array(),
   'a failed read must paint NO markers rather than guess at them');

/* ---------- 3. the page explains itself ------------------------------------- */
/*
 * Source-level, and narrow on purpose: what is being checked is that all
 * THREE server states are handled, not how the sentences are worded. The bug
 * was a missing branch -- 'cap' leaves rewarded === true, so the old code's
 * single `rewarded === false` test could not see it and said nothing at all.
 */
echo "\nthe end screen\n";
$page = file_get_contents(__DIR__ . '/dhcarena.php');
$act  = file_get_contents(__DIR__ . '/ajax/dhcarena-action.php');
ok(strpos($act, "\$out['nodrop']") !== false,
   'ajax/dhcarena-action.php no longer sends nodrop, so the page cannot tell '
 . 'the two no-trait rules apart');
foreach (array('opponent', 'cap') as $why) {
	ok(strpos($page, "res.nodrop === '$why'") !== false,
	   "dhcarena.php has no branch for nodrop '$why' -- that win goes unexplained");
}
ok(!preg_match("/rewarded === false\) *sub \+= ' No trait this time: the daily cap/", $page),
   'the end screen is blaming the daily cap for a repeat-opponent win again');
ok(strpos($page, 'dhca_paid_today') !== false,
   'dhcarena.php stopped asking which rivals already paid, so the list is unmarked again');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all arena reward checks passed\n");
exit($fail ? 1 : 0);
