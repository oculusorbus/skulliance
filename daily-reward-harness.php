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


echo "\nthe ladder in the code is the ladder in the docs\n";
/*
 * THE LADDER LIVES IN TWO PLACES THAT CANNOT SEE EACH OTHER: the array in
 * daily-streak-lib.php and the bullet list players read on
 * skullpaper/staking-daily-rewards.md. One of them is going to be edited
 * alone, and the one players read is the one that will be wrong.
 */
require_once __DIR__ . '/daily-streak-lib.php';
$dr_tiers = getRewardTiers();
$dr_doc   = (string) @file_get_contents(__DIR__ . '/skullpaper/staking-daily-rewards.md');
ok($dr_doc !== '', 'the daily rewards page is gone; this check is now blind');
preg_match_all('~^\* Day (\d): \+([\d,]+) RANDOM~m', $dr_doc, $dr_m, PREG_SET_ORDER);
ok(count($dr_m) === 7, 'the docs no longer list seven days (' . count($dr_m) . ' found)');
$dr_drift = array();
foreach ($dr_m as $r) {
	$d = (int) $r[1];
	$v = (int) str_replace(',', '', $r[2]);
	if (!isset($dr_tiers[$d]) || (int) $dr_tiers[$d] !== $v) {
		$dr_drift[] = "day $d: code " . (isset($dr_tiers[$d]) ? $dr_tiers[$d] : 'missing') . ", docs $v";
	}
}
ok(count($dr_drift) === 0, 'the code and the docs disagree -- ' . implode('; ', $dr_drift));
$dr_week = array_sum($dr_tiers);
ok(strpos($dr_doc, number_format($dr_week)) !== false,
   'the docs do not quote the real weekly maximum of ' . number_format($dr_week));
$dr_rising = true;
for ($dr_i = 2; $dr_i <= 7; $dr_i++) if ($dr_tiers[$dr_i] <= $dr_tiers[$dr_i-1]) $dr_rising = false;
ok($dr_rising, 'the ladder does not rise every day, so there is no reason to keep a streak');

echo "\na finished streak is found by a derived amount, never a literal\n";
/*
 * FOUR QUERIES INFERRED A COMPLETED STREAK FROM `amount = 30` -- tier 7 of
 * the old ladder. Raising the ladder to 3000 would have left all four
 * counting nothing new while still reporting the old totals, which looks
 * exactly like it still works. They have to read dailyStreakAmountSql().
 */
$dr_sql  = dailyStreakAmountSql();
$dr_vals = array_map('intval', dailyStreakAmounts());
/* THE LIST, NOT THE STRING. Checking the SQL with strpos() for '30' passes
   on "amount IN (3000)" -- the original value can be dropped entirely and
   the substring is still there inside the new one. */
ok(in_array((int) $dr_tiers[7], $dr_vals, true),
   'the streak marker does not include the current day-seven payout, so no new streak counts');
ok(in_array(30, $dr_vals, true),
   'the streak marker dropped the original 30, so every streak ever completed stopped counting');
ok(strpos($dr_sql, 'IN (') !== false, 'the marker is not a list, so it can only ever match one ladder');
foreach (array('db.php', 'activity-sources-lib.php') as $dr_f) {
	$dr_src = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/' . $dr_f));
	ok(preg_match("~bonus\s*=\s*'?1'?\s*(AND|and)\s*amount\s*=~", $dr_src) !== 1,
	   "$dr_f still matches a completed streak against a literal amount");
}

echo "\nthe mine rate has one home, and the docs quote it\n";
$dr_db = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/db.php'));
ok(strpos($dr_db, 'function getMineRate(') !== false, 'the mine rate has no single definition');
ok(preg_match('~mine_level\s*\*\s*\d~', $dr_db) !== 1,
   'db.php still multiplies the mine level inline instead of calling getMineRate()');
$dr_mine = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/ajax/get-mine.php'));
ok(preg_match('~\$lv\s*\*\s*\d~', $dr_mine) !== 1,
   'ajax/get-mine.php keeps its own copy of the mine rate');
ok(strpos($dr_mine, 'getMineRate(') !== false, 'the level table does not read the real rate');
/*
 * AND THE CALIBRATION HOLDS. A perfect week buys this many Realms levels
 * at the any-points rate; a Level 10 Mine shatters into this many core
 * points a week. They are meant to be equal -- if either side moves and
 * this fails, that is the conversation, not a number to paper over.
 */
$dr_mult      = 2;                          /* $points_multiplier, skulliance.php */
$dr_week_buys = $dr_week / $dr_mult;
/* PARSED OUT OF db.php, not called: getMineRate() lives beside a live
   database connection this harness deliberately does not open. Reading
   the real source is the stronger check anyway. */
preg_match('~function getMineRate\([^)]*\)\s*\{[^}]*\*\s*(\d+)~s', $dr_db, $dr_rm);
ok(!empty($dr_rm), 'getMineRate() no longer multiplies by a literal rate this can read');
$dr_rate      = empty($dr_rm) ? 0 : (int) $dr_rm[1];
$dr_mine_wk   = $dr_rate * 10 * 7 / 100 * 6;   /* L10 nightly x7 / 100 per DIAMOND x 6 core points */
ok(abs($dr_week_buys - $dr_mine_wk) < 1,
   'a perfect week buys ' . number_format($dr_week_buys) . ' levels but a Level 10 Mine yields '
   . number_format($dr_mine_wk) . '; the ladder and the mine are out of calibration');
ok(preg_match('~\$points_multiplier\s*=\s*' . $dr_mult . '\s*;~',
   (string) file_get_contents(__DIR__ . '/skulliance.php')) === 1,
   'the any-points multiplier is no longer ' . $dr_mult . ', so the calibration above is stale');


echo "\nevery day pays a trait, and the day picks the table\n";
/*
 * It used to be day seven only, off placement_3, so six days of showing
 * up bought nothing. A streak is a habit and a habit wants paying as it
 * forms -- but the ladder that does that now lives in three places that
 * cannot see each other: the bands in dhcfighters-config.php, the call
 * in db.php, and the list players read on staking-daily-rewards.md.
 */
require_once __DIR__ . '/dhcfighters-config.php';
$dr_db2 = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/db.php'));

/* THE FLOOR. At 7 the award is unreachable on every other day whatever
   the bands say. */
ok(dhcf_floor('dailystreak') <= 1,
   'the dailystreak floor is ' . dhcf_floor('dailystreak') . ', so only that day can ever pay');
/* THE CALL. A flat table here would ignore the ladder entirely. */
ok(preg_match("~dhcf_table_for\('dailystreak',\s*\(int\)\\\$current_streak\)~", $dr_db2) === 1,
   'the claim passes a fixed table instead of the day, so every day pays the same odds');
ok(preg_match("~if \(\\\$current_streak >= 1 &&~", $dr_db2) === 1,
   'the claim still gates the award on one particular day');

/* THE LADDER ITSELF: it must rise, and it must top out at the best table
   there is on day seven. */
$dr_prev = -1; $dr_names = array();
for ($dr_d = 1; $dr_d <= 7; $dr_d++) {
	$dr_t = dhcf_table_for('dailystreak', $dr_d);
	$dr_n = array_search($dr_t, DHCF_TIERS, true);
	$dr_names[$dr_d] = $dr_n;
	$dr_r = dhcf_table_rank($dr_n);
	ok($dr_r >= $dr_prev, "day $dr_d drops to a worse table than day " . ($dr_d - 1));
	$dr_prev = $dr_r;
}
ok($dr_names[7] === 'placement_1',
   'day seven pays ' . $dr_names[7] . ', not the best table on the platform');
ok(dhcf_table_rank($dr_names[7]) > dhcf_table_rank($dr_names[1]),
   'day seven is worth no more than day one, so the streak buys nothing');
/* placement_1 is the ceiling: nothing may rank above it, or the "best
   possible" claim on day seven stops being true. */
$dr_top = 0;
foreach (array_keys(DHCF_TIERS) as $dr_k) $dr_top = max($dr_top, dhcf_table_rank($dr_k));
ok(dhcf_table_rank('placement_1') === $dr_top,
   'placement_1 is no longer the best table, so day seven is not the best possible');

/* ONE PER DAY, and it has to be the cap rather than the call site: the
   cap is what a second route to the same award would still hit. */
ok((int) dhcf_cap('dailystreak') === 1,
   'the dailystreak cap is ' . dhcf_cap('dailystreak') . ', so a claim could pay more than one trait a day');
/* INSIDE dhcf_award()'s cap check: dhcf_drops_today() further down the
   file runs the same CURDATE() clause, so a file-wide search passed with
   the cap's own window removed. */
$dr_lib  = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/dhcfighters-lib.php'));
$dr_awd  = substr($dr_lib, strpos($dr_lib, 'function dhcf_award('));
$dr_awd  = substr($dr_awd, 0, strpos($dr_awd, '$drawn = dhcf_draw('));
ok(strpos($dr_awd, 'awarded_at >= CURDATE()') !== false,
   'the cap no longer counts per day, so "one per day" is not enforced anywhere');

/* THE DOCS. The bullet list is what players read, and it is the copy
   that will be left behind. */
$dr_md = (string) @file_get_contents(__DIR__ . '/skullpaper/staking-daily-rewards.md');
ok(strpos($dr_md, 'A trait every day') !== false, 'the docs do not mention the daily trait at all');
$dr_want = array('run' => 'ordinary drop table', 'placement_10' => 'top-ten table',
                 'placement_3' => 'top-three table', 'placement_1' => 'first-place table');
foreach (array_unique($dr_names) as $dr_n) {
	ok(strpos($dr_md, $dr_want[$dr_n]) !== false,
	   "the ladder uses $dr_n but the docs never name the " . $dr_want[$dr_n]);
}
/* And nothing the ladder does NOT use may be advertised. */
foreach ($dr_want as $dr_n => $dr_label) {
	if (in_array($dr_n, $dr_names, true)) continue;
	ok(strpos($dr_md, $dr_label) === false,
	   "the docs promise the $dr_label but no day actually draws from it");
}

/* The modal cannot say "full streak" on day two. */
$dr_modal = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/missions-claim-modal.php'));
ok(preg_match('~day >= 7~', $dr_modal) === 1,
   'the claim modal announces the trait the same way on every day');
ok(preg_match("~>Full streak\.~", $dr_modal) !== 1,
   'the modal hardcodes "Full streak" in its markup, so it says it on day one');

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall good\n";
/* EXIT CODE, not just a line of text. Without this the harness always
   returned 0, so every runner that checked the status -- including the
   mutation driver that is supposed to prove these assertions can fail --
   read a red run as green. */
exit($fail ? 1 : 0);
