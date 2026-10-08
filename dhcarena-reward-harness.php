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
/* dhca_reward_bands() probes dhcf_table_for(), so the real reward config
   has to be loaded -- lifting the function without it would test the
   probe against nothing. dhcfighters-config.php is pure definitions. */
require_once __DIR__ . '/dhcfighters-config.php';
foreach (array('dhca_already_rewarded', 'dhca_paid_today', 'dhca_payout_outcome',
               'dhca_gap_steps', 'dhca_reward_bands') as $fn) {
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

/* ---------- 0. the flag follows the trait ----------------------------------- */
/*
 * THE BUG THIS EXISTS FOR. dhca_settle() used to set $rewarded = 1 and THEN
 * call dhca_pay(), storing 1 whether or not dhcf_award() actually handed
 * anything over. A win that hit the three-a-day cap therefore paid nothing
 * and ALSO burned the pairing's one payout for the day -- the player lost the
 * trait and the chance to win it off that rival later, and the rival list
 * then marked them as already beaten. Reported by a player who lost several
 * battles, finally won one, and got nothing.
 *
 * rewarded is what dhca_already_rewarded() and dhca_paid_today() both read,
 * so it has to mean "a trait came out of this" and nothing looser.
 */
echo "the rewarded flag\n";
$cases = array(
	//  already paid, got a drop   => flag, why
	array(false, true,  1, ''),
	array(false, false, 0, 'cap'),
	array(true,  false, 0, 'opponent'),
	array(true,  true,  0, 'opponent'),   // cannot happen; must not pay twice if it does
);
foreach ($cases as $c) {
	list($paidAlready, $gotDrop, $wantFlag, $wantWhy) = $c;
	list($flag, $why) = dhca_payout_outcome($paidAlready, $gotDrop);
	printf("  already=%-5s drop=%-5s -> rewarded=%d %s\n",
		var_export($paidAlready, true), var_export($gotDrop, true), $flag,
		$why === '' ? '' : "($why)");
	ok($flag === $wantFlag && $why === $wantWhy,
	   'already=' . var_export($paidAlready, true) . ' drop=' . var_export($gotDrop, true)
	 . " should give ($wantFlag, '$wantWhy'), gave ($flag, '$why')");
}
ok(dhca_payout_outcome(false, false)[0] === 0,
   'a win that awarded nothing must NOT mark the pairing as paid -- that costs the '
 . 'player the trait and the rematch that could still have earned it');

/* And the call site has to use it. The rule being right in a function nothing
   calls is how this looked before. */
ok(strpos($src, 'dhca_payout_outcome(') !== false
   && substr_count($src, 'dhca_payout_outcome(') >= 2,
   'dhca_settle() does not call dhca_payout_outcome() -- the rule above is not the '
 . 'one the game runs');
ok(!preg_match('/\$rewarded = 1;\s*\n\s*dhca_pay\(/', $src),
   'dhca_settle() sets the rewarded flag before dhca_pay() again, which is the bug');

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

echo "\nwhat the rival list promises is what the win pays\n";

/*
 * Punching up has always paid better -- dhca_pay() bands the trait roll
 * on the Crews' best rarity scores -- and nothing on the opponent select
 * said so, so the rational play was to farm the weakest Crew. The fix is
 * to print the odds on each rival.
 *
 * WHICH CREATES THE ONLY RISK WORTH TESTING: a screen that quotes odds
 * the payout does not honour is worse than no screen. A player picks the
 * hard fight for a mythic chance that never existed, loses the bench
 * time, and has no way to tell they were misled.
 *
 * dhca_reward_bands() is built by PROBING dhcf_table_for(), the function
 * dhca_pay() calls, rather than restating its thresholds. These checks
 * are that the probe stayed faithful.
 */
$bands = dhca_reward_bands();
ok(count($bands) >= 2, 'the reward ladder collapsed to one band; punching up would read the same as farming down');

/* Every step from 0 to 12 must land on the band the payout would use. */
$drift = array();
for ($g = 0; $g <= 12; $g++) {
	$real = dhcf_table_for('arena', $g);
	$show = $bands[0];
	foreach ($bands as $b) if ($g >= $b['from']) $show = $b;
	if ((float)$real['mythic'] !== $show['mythic'] || (float)$real['legendary'] !== $show['legendary'])
		$drift[] = "gap $g: pays {$real['mythic']}% mythic, list says {$show['mythic']}%";
}
ok(!$drift, 'the rival list quotes odds the payout does not honour: ' . implode('; ', $drift));

/* The ladder must actually RISE, or the whole point is lost. */
$prev = -1; $rising = true;
foreach ($bands as $b) { if ($b['mythic'] < $prev) $rising = false; $prev = $b['mythic']; }
ok($rising, 'the reward ladder does not increase with the gap, so punching up pays no better');
ok($bands[count($bands)-1]['mythic'] > $bands[0]['mythic'] * 2,
   'the best band is not meaningfully better than a level fight ('
 . $bands[0]['mythic'] . '% vs ' . $bands[count($bands)-1]['mythic'] . '%), so nothing is being encouraged');
ok($bands[count($bands)-1]['best'] === true && $bands[0]['best'] === false,
   'the top band is not flagged, so the list cannot highlight the best fight available');

/* ONE PIECE OF ARITHMETIC, not two. The page subtracts the same way the
   payout does, or the quoted band is for a gap nobody has. */
ok(dhca_gap_steps(3100, 4310) === 12, 'dhca_gap_steps() is not (theirs - mine) / 100');
ok(dhca_gap_steps(4310, 3100) === -12, 'a downward gap does not go negative, so farming reads as punching up');
ok(dhca_gap_steps(3100, 3149) === 0, 'a 49-point gap rounds up to a band the player has not earned');
$lib = file_get_contents(__DIR__ . '/dhcarena-lib.php');
ok(preg_match('/return dhca_gap_steps\(\$mine, \$foes\);/', $lib) === 1,
   'dhca_rating_gap() does the subtraction itself again, so the payout and the list can drift');

/* And the page has to actually render it, from server data. */
$pg = file_get_contents(__DIR__ . '/dhcarena.php');
ok(strpos($pg, 'json_encode(dhca_reward_bands())') !== false,
   'the page no longer emits the server-authored bands, so the client is inventing percentages');
ok(preg_match('/paintPicked\(\);\s*\n\s*paintOdds\(\);/', $pg) === 1,
   'paintOdds() is not called from paintPicker(), so the odds go stale the moment the Crew changes');
ok(strpos($pg, "data-best=\"<?php echo (int)\$o['best']") !== false,
   'the rival card no longer carries its best score, so the gap cannot be computed');
ok(strpos($pg, "if (!picked.length) { slot.textContent = ''") !== false,
   'odds are shown before a Crew is picked, which quotes a gap measured against nothing');

echo "\na guest never gets an HTML redirect from an AJAX endpoint\n";

/*
 * REPORTED: "Lost contact with the Arena - that move was not played",
 * repeatedly, on practice in Chrome as a guest.
 *
 * That string is the fetch FAILING, not the server refusing -- a refusal
 * arrives as ok:false with a message. The route there was three steps:
 *   1. startPractice() caught a draw error and set practice = null,
 *      leaving a board on screen that looked playable.
 *   2. sendMove()'s ternary then fell through to the RANKED endpoint
 *      with battle_id 0.
 *   3. ajax/dhcarena-action.php includes skulliance.php, which 302s an
 *      unidentified visitor to error.php. fetch follows it, gets HTML,
 *      and JSON.parse reports a network-shaped error for a login state.
 *
 * Each step is guarded separately below, because any one of them alone
 * turns a cosmetic bug into a dead board.
 */
$act = file_get_contents(__DIR__ . '/ajax/dhcarena-action.php');
$iGuest = strpos($act, "if (empty(\$_SESSION['userData']['user_id']))");
$iSkul  = strpos($act, "include '../skulliance.php'");
ok($iGuest !== false, 'the guest check before skulliance.php is gone; a logged-out POST 302s to HTML');
ok($iSkul !== false && $iGuest !== false && $iGuest < $iSkul,
   'the guest check runs AFTER skulliance.php, which has already redirected by then');

/* The PWA trap: that check must not fire for a player whose session
   lives only in the SessionCookie, or it logs out every iOS user
   instead of every guest. */
ok(strpos($act, "array_merge((array)\$_SESSION, \$ck)") !== false,
   'the guest check does not restore from SessionCookie first, so every PWA player reads as a guest');
$iMerge = strpos($act, 'SessionCookie');
ok($iMerge !== false && $iMerge < $iGuest,
   'the session restore runs after the guest check, which makes the restore pointless');
ok(strpos($act, '$_SESSION = $ck') === false,
   'the restore replaces $_SESSION instead of merging; that wipes other pages state');

/* And the client must not route a practice move to the ranked endpoint
   in the first place. */
$pg = file_get_contents(__DIR__ . '/dhcarena.php');
ok(preg_match('/if \(!live && !practice && !\(battleId > 0\)\)/', $pg) === 1,
   'sendMove() will post a battle with no id and no spec to the ranked endpoint again');
ok(preg_match('/catch \(e\) \{\s*\n(?:.*\n)*?\s*try \{ leaveBattle\(\); \}/', $pg) === 1,
   'a practice battle that cannot be drawn is left on screen instead of closed');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all arena reward checks passed\n");
exit($fail ? 1 : 0);
