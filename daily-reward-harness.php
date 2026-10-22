<?php
/*
 * daily-reward-harness.php -- the daily reward can be claimed once a day.
 *
 * WHY THIS EXISTS. The claim was repeatable without limit for five hours
 * every night and nothing noticed for as long as the two clocks have been
 * apart. php.ini set PHP to UTC; MySQL stamps transactions.date_created
 * with the host clock, which is Central. Between 19:00 Central and midnight
 * PHP was already on tomorrow's date, so a claim stamped "21st" was read as
 * yesterday's and the button came back -- and because the test compared
 * DATE against DATE, every fresh claim landed on a date PHP had already
 * moved past. Refresh, claim, refresh, claim.
 *
 * The functions are LIFTED OUT OF db.php and executed against a stub
 * connection, because db.php wants credentials and a live database. What
 * is pinned here is the thing that was actually wrong: the day boundary is
 * MySQL's, compared against a value MySQL wrote, with no PHP date in it.
 */
$fail = 0;
function ok($c, $m) { global $fail; if ($c) { echo "  ok    $m\n"; } else { echo "  FAIL  $m\n"; $fail++; } }

$db = file_get_contents(__DIR__ . '/db.php');

/* ---- the zone is pinned in the repository, not left to php.ini -------- */
echo "the platform's clock\n";
ok(strpos($db, "date_default_timezone_set('America/Chicago');") !== false,
   'db.php no longer pins the timezone, so a PHP version switch can move the day boundary again');
ok(strpos($db, 'date_default_timezone_set') < strpos($db, "include 'credentials/db_credentials.php'"),
   'the zone is set after something has already run, so early date() calls use php.ini');

/* ---- lift the two functions and drive them ---------------------------- */
$lift = function ($name) use ($db) {
	$i = strpos($db, 'function ' . $name . '($conn)');
	if ($i === false) return null;
	$j = strpos($db, "\n}", $i);
	return substr($db, $i, $j - $i + 2);
};
$src = '';
foreach (array('getDailyRewardEligibility', 'getMaxDateCreated') as $fn) {
	$one = $lift($fn);
	ok($one !== null, "$fn is gone from db.php, so this harness is testing nothing");
	$src .= $one . "\n";
}
eval($src);

class DRH_Result {
	public $r;
	function __construct($r) { $this->r = $r; }
	function fetch_assoc() { return $this->r; }
}
class DRH_Conn {
	public $last = '';
	public $ret;
	function query($s) { $this->last = $s; return $this->ret; }
}
$c = new DRH_Conn();

echo "\nonce a day, and the clock cannot move it\n";
$_SESSION['userData']['user_id'] = 42;

$c->ret = new DRH_Result(array('last_claim' => null, 'claimed_today' => null));
ok(getDailyRewardEligibility($c) === true, 'a player who has never claimed is refused their first reward');

/*
 * THE EXPLOIT, as a test. This is the row the live database returns to a
 * player who claimed at 19:15 Central: MySQL wrote today's date. The old
 * code called that yesterday's and said yes, over and over.
 */
$c->ret = new DRH_Result(array('last_claim' => '2026-10-21 19:15:00', 'claimed_today' => '1'));
ok(getDailyRewardEligibility($c) === false, 'a player who already claimed TODAY can claim again');
/* And it stays refused however many times it is asked -- the symptom was
   a refresh loop, not a single extra claim. */
$again = true;
for ($i = 0; $i < 5; $i++) if (getDailyRewardEligibility($c) !== false) $again = false;
ok($again, 'repeated requests eventually slip through, which is the refresh loop that was reported');

$c->ret = new DRH_Result(array('last_claim' => '2026-10-20 19:15:00', 'claimed_today' => '0'));
ok(getDailyRewardEligibility($c) === true, 'a player who claimed yesterday is not eligible today');

echo "\nfailing safe\n";
$c->ret = false;
ok(getDailyRewardEligibility($c) === false, 'a failed query reads as never-claimed, which pays out on any database error');

$c->ret = new DRH_Result(array('last_claim' => '2026-10-21 19:15:00', 'claimed_today' => '1'));
unset($_SESSION['userData']['user_id']);
ok(getDailyRewardEligibility($c) === false, 'a request with no session user is handed a reward');
ok(getMaxDateCreated($c) === null, 'the max query runs for a missing user, so it can match user 0');

echo "\nthe query itself\n";
$_SESSION['userData']['user_id'] = 42;
$c->ret = new DRH_Result(array('last_claim' => null, 'claimed_today' => null));
getDailyRewardEligibility($c);
$sql = preg_replace('/\s+/', ' ', trim($c->last));
ok(strpos($sql, 'CURDATE()') !== false,
   'the day boundary is computed in PHP again, so the two clocks can disagree');
ok(stripos($sql, 'GROUP BY') === false,
   'GROUP BY is back; grouping on the primary key makes MAX() a no-op over one row each');
ok(strpos($sql, "bonus = '1'") !== false,
   'the query is no longer scoped to bonus rows, so any transaction counts as a claim');
ok(preg_match('/user_id = \d+ /', $sql) === 1,
   'the user id is not interpolated as a bare integer: ' . $sql);

/* A poisoned session must not reach the statement. */
$_SESSION['userData']['user_id'] = '42 OR 1=1 -- ';
getDailyRewardEligibility($c);
ok(strpos($c->last, 'OR 1=1') === false, 'a tampered session user_id reaches the query intact');

echo "\nthe max helper\n";
$_SESSION['userData']['user_id'] = 42;
$c->ret = new DRH_Result(array('date_created' => null));
ok(getMaxDateCreated($c) === null, 'MAX() over no rows does not read as null');
$c->ret = new DRH_Result(array('date_created' => '2026-10-21 19:15:00'));
ok(getMaxDateCreated($c) === '2026-10-21 19:15:00', 'the stamp is not returned as written');

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall good\n";
