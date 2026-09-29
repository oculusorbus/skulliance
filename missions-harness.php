<?php
/* missions-harness.php — CLI only. Exercises missions-lib.php with no
   database and no network.
 *
 * mission_launch() is the reason this exists. It is the path that spends a
 * staker's points and commits their NFTs, and it replaced a path that
 * believed the browser: ajax/process-mission-nft.php wrote whatever nft_id
 * the client named into $_SESSION and startMission() inserted it unchecked.
 * The new one intersects the request against what the database says is
 * eligible, and that intersection is what these checks are about.
 *
 * missions-lib.php includes nothing, which is what makes this possible --
 * the handful of db.php helpers it calls are stubbed below and every write
 * is recorded rather than performed.
 *
 * Usage: php missions-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* ---------- the world ----------------------------------------------------- */
$WORLD = array(
	'levels'      => array(9 => 2),      // project 9: cleared up to level 2, so 3 is open
	'balance'     => array(9 => 500),
	'eligible'    => array(),            // nft_id => rate, filled per case
	'amounts'     => array(),            // consumable_id => amount
	'quest'       => array(),
	'idle'        => array(),            // project_id => idle nft count
	'unattempted' => array(),            // quest rows the user has never launched
);
$WROTE = array();   // every insert/update, in order

/* ---------- db.php stubs -------------------------------------------------- */
function getMissionLevels($conn)    { global $WORLD; return $WORLD['levels']; }
function getCurrentAmounts($conn)   {
	global $WORLD; $out = array();
	$names = array(1 => '100% Success', 2 => '75% Success', 3 => '50% Success',
	               4 => '25% Success', 5 => 'Fast Forward', 6 => 'Double Rewards');
	foreach ($WORLD['amounts'] as $id => $amt)
		$out[$id] = array('name' => isset($names[$id]) ? $names[$id] : 'Item', 'amount' => $amt);
	return $out;
}
function getIPFS($ipfs, $cid, $pid)         { return 'img'; }
function getMissionConsumables($conn, $mid) { return array(); }
function updateBalance($conn, $u, $p, $d)   { global $WROTE; $WROTE[] = array('balance', $u, $p, $d); }
function logDebit($conn, $u, $a, $c, $p, $z, $m) { global $WROTE; $WROTE[] = array('debit', $u, $c, $p, $m); }
function updateAmount($conn, $u, $cid, $d)  { global $WROTE; $WROTE[] = array('amount', $u, $cid, $d); }
function discordmsg() { global $WROTE; $WROTE[] = array('discord'); }

class MRes {
	public $num_rows; private $rows;
	function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	function fetch_assoc() { return array_shift($this->rows); }
}
class MConn {
	public $insert_id = 0; public $error = ''; public $tx = 0;
	private $next_id = 7000;
	function begin_transaction() { $this->tx++; return true; }
	function commit()   { global $WROTE; $WROTE[] = array('commit');   return true; }
	function rollback() { global $WROTE; $WROTE[] = array('rollback'); return true; }
	function query($sql) {
		global $WORLD, $WROTE;
		$flat = preg_replace('/\s+/', ' ', $sql);
		if (strpos($flat, 'FROM quests q INNER JOIN projects p') !== false
		    && strpos($flat, 'NOT IN (SELECT quest_id FROM missions') === false)
			return new MRes($WORLD['quest'] ? array($WORLD['quest']) : array());
		/* ORDER MATTERS, and it bit: the single-row lookup below also matches
		   'FROM balances', so the per-project list has to be tested first or
		   every frontier row comes back with a balance of zero. */
		if (strpos($flat, 'SELECT project_id, balance FROM balances') !== false) {
			$r = array();
			foreach ($WORLD['balance'] as $pid => $b) $r[] = array('project_id' => $pid, 'balance' => $b);
			return new MRes($r);
		}
		if (strpos($flat, 'FROM balances') !== false) {
			preg_match("/project_id = '(\d+)'/", $flat, $m);
			$p = isset($m[1]) ? (int)$m[1] : 0;
			return new MRes(isset($WORLD['balance'][$p]) ? array(array('balance' => $WORLD['balance'][$p])) : array());
		}
		if (strpos($flat, 'SELECT c.project_id, COUNT(*) AS n FROM nfts n') !== false) {
			$r = array();
			foreach ($WORLD['idle'] as $pid => $n) $r[] = array('project_id' => $pid, 'n' => $n);
			return new MRes($r);
		}
		if (strpos($flat, 'FROM nfts n INNER JOIN collections c') !== false) {
			$r = array();
			foreach ($WORLD['eligible'] as $id => $rate) $r[] = array('id' => $id, 'rate' => $rate);
			return new MRes($r);
		}
		if (strpos($flat, 'FROM quests q INNER JOIN projects p') !== false
		    && strpos($flat, 'NOT IN (SELECT quest_id FROM missions') !== false) {
			$r = array();
			foreach ($WORLD['unattempted'] as $q) $r[] = $q;
			return new MRes($r);
		}
		if (strpos($flat, 'INSERT INTO missions (') === 0) {
			$WROTE[] = array('mission'); $this->insert_id = ++$this->next_id; return true;
		}
		if (strpos($flat, 'INSERT INTO missions_nfts') === 0) {
			preg_match_all("/'(\d+)'/", $flat, $m);
			$WROTE[] = array('nft', (int)$m[1][1]); return true;
		}
		if (strpos($flat, 'INSERT INTO missions_consumables') === 0) {
			preg_match_all("/'(\d+)'/", $flat, $m);
			$WROTE[] = array('item', (int)$m[1][1]); return true;
		}
		if (strpos($flat, 'FROM consumables WHERE id') !== false)
			return new MRes(array(array('name' => 'Fast Forward')));
		return new MRes(array());
	}
}

require __DIR__ . '/missions-lib.php';

$_SESSION = array('userData' => array('user_id' => 44, 'discord_id' => '1111', 'username' => 'tester'));
$conn = new MConn();

function reset_world($quest = null, $eligible = array(), $amounts = array(), $balance = 500) {
	global $WORLD, $WROTE, $conn;
	$WORLD['quest']    = $quest ?: array('id' => 77, 'title' => "Widow's Walk", 'cost' => 100,
		'reward' => 300, 'duration' => 2, 'extension' => 'png', 'level' => 3,
		'project_id' => 9, 'currency' => 'STAR');
	$WORLD['eligible'] = $eligible;
	$WORLD['amounts']  = $amounts;
	$WORLD['balance']  = array(9 => $balance);
	$WROTE = array();
	$conn = new MConn();
	mission_levels_forget();
}
function wrote($kind) { global $WROTE; $o = array(); foreach ($WROTE as $w) if ($w[0] === $kind) $o[] = $w; return $o; }

echo "the squad is intersected, not believed\n";

/* The whole point. The request names five NFTs; only two of them are in the
   eligible set the database returned. */
reset_world(null, array(10 => 30, 11 => 25));
$r = mission_launch($conn, 77, array(10, 11, 12, 999, 10), array());
ok(!empty($r['ok']), 'launches with the valid ones');
ok(count(wrote('nft')) === 2, 'exactly the two eligible NFTs were written');
$ids = array(); foreach (wrote('nft') as $w) $ids[] = $w[1];
sort($ids);
ok($ids === array(10, 11), "someone else's NFT never reaches missions_nfts");
ok($r['dropped'] === 2, 'the request is told how many it named that did not count');
ok($r['success'] == 55, 'success is the sum of the REAL rates, not claimed ones');

/* A duplicate id in the request must not be written or counted twice. */
ok(count(wrote('nft')) === 2, 'a repeated nft_id is written once');

echo "\nitems you do not own\n";

reset_world(null, array(10 => 30), array(5 => 1));       // holds Fast Forward only
$r = mission_launch($conn, 77, array(10), array(1, 5));  // asks for a +100% it lacks
ok(!empty($r['ok']), 'launches');
$items = array(); foreach (wrote('item') as $w) $items[] = $w[1];
ok($items === array(5), 'only the item actually held is attached');
ok($r['success'] == 30, 'the unowned +100% does not reach the success rate');
ok(count(wrote('amount')) === 1, 'exactly one item is decremented');

reset_world(null, array(10 => 30), array(1 => 2));
$r = mission_launch($conn, 77, array(10), array(1, 1, 1));
ok(count(wrote('item')) === 1, 'the same item cannot be applied three times');
ok($r['success'] == 100, 'success caps at 100');

echo "\nthe rules that were only ever enforced by the page\n";

/* Level 4 with 2 cleared: locked. The old page hid the card; nothing
   stopped a crafted request. */
reset_world(array('id' => 78, 'title' => 'Deep Six', 'cost' => 0, 'reward' => 10, 'duration' => 1,
	'extension' => 'png', 'level' => 5, 'project_id' => 9, 'currency' => 'STAR'), array(10 => 30));
$r = mission_launch($conn, 78, array(10), array());
ok(empty($r['ok']), 'a locked mission is refused server-side');
ok(!wrote('mission'), 'and nothing is written');

reset_world(null, array(10 => 30), array(), 40);   // cost 100, balance 40
$r = mission_launch($conn, 77, array(10), array());
ok(empty($r['ok']), 'refused when you cannot afford it');
ok(!wrote('mission'), 'nothing written when broke');
ok(strpos($r['message'], '60') !== false, 'the refusal says how much short you are');

reset_world(null, array(), array());
$r = mission_launch($conn, 77, array(), array());
ok(empty($r['ok']), 'refused with no squad and no success item');
ok(!wrote('mission'), 'nothing written for an empty load-out');

/* But a success item alone IS a mission -- that is how Max Maxi farms. */
reset_world(null, array(), array(1 => 1));
$r = mission_launch($conn, 77, array(), array(1));
ok(!empty($r['ok']), 'a 100% item with no NFTs still launches');
ok($r['success'] == 100, 'and it is a 100% mission');

echo "\nthe money path\n";

reset_world(null, array(10 => 30), array());
$r = mission_launch($conn, 77, array(10), array());
$b = wrote('balance'); $d = wrote('debit');
ok(count($b) === 1 && $b[0][3] == -100, 'the cost is deducted once, as a negative');
ok(count($d) === 1 && $d[0][2] == 100,  'and logged as a debit of the same size');
ok(count(wrote('mission')) === 1, 'exactly one mission row');
ok(count(wrote('discord')) === 1, 'exactly one Discord announcement');

reset_world(array('id' => 79, 'title' => 'Free Run', 'cost' => 0, 'reward' => 10, 'duration' => 1,
	'extension' => 'png', 'level' => 1, 'project_id' => 9, 'currency' => 'STAR'), array(10 => 30));
$r = mission_launch($conn, 79, array(10), array());
ok(!empty($r['ok']), 'a free mission launches');
ok(!wrote('balance') && !wrote('debit'), 'a zero cost writes no balance change at all');

echo "\nrollback\n";
/* Nothing should be left behind if the insert itself fails. */
class MConnFail extends MConn {
	function query($sql) {
		if (strpos(preg_replace('/\s+/', ' ', $sql), 'INSERT INTO missions (') === 0) return false;
		return parent::query($sql);
	}
}
reset_world(null, array(10 => 30));
$bad = new MConnFail();
$r = mission_launch($bad, 77, array(10), array());
ok(empty($r['ok']), 'a failed insert is reported as a failure');
ok(!wrote('balance'), 'and no points were taken');
ok(count(wrote('rollback')) === 1, 'the transaction is rolled back');

echo "\nnewly unlocked, and still not taken\n";

function qrow($id, $level, $pid, $cost, $title) {
	return array('id' => $id, 'title' => $title, 'level' => $level, 'cost' => $cost,
		'reward' => $cost * 2 + 10, 'duration' => $level, 'extension' => 'png',
		'project_id' => $pid, 'project_name' => 'P' . $pid, 'currency' => 'STAR');
}

/* Cleared level 2 on project 9. Level 3 is open and never launched: that is
   the rung the holder keeps missing. Level 4 is still locked. */
mission_levels_forget(); $WORLD['levels'] = array(9 => 2);
$WORLD['balance']     = array(9 => 5000);
$WORLD['idle']        = array(9 => 6);
$WORLD['unattempted'] = array(qrow(31, 3, 9, 300, 'Walker'), qrow(32, 4, 9, 500, 'Clicker'));
$f = mission_frontier($conn);
ok(count($f) === 1, 'only the unlocked rung is listed');
ok($f && $f[0]['quest_id'] === 31, 'and it is the one that just opened');
ok($f && !empty($f[0]['frontier']), 'flagged as the frontier rung');
ok($f && !empty($f[0]['affordable']) && !empty($f[0]['has_squad']), 'says it can be launched now');

/* THE DAY-ONE RULE. A brand new staker has cleared nothing, so level 1 on
   every project is "open and never launched" -- forty rows of noise that
   would bury the one rung this list exists to surface. */
mission_levels_forget(); $WORLD['levels'] = array();
$WORLD['unattempted'] = array(qrow(1, 1, 9, 0, 'First Steps'), qrow(2, 1, 12, 0, 'Also First'));
ok(mission_frontier($conn) === array(), 'a staker who has cleared nothing sees no "new" rungs');

/* Cleared on project 9 only: project 12's level 1 still is not news. */
mission_levels_forget(); $WORLD['levels'] = array(9 => 1);
$WORLD['balance']     = array(9 => 5000, 12 => 5000);
$WORLD['idle']        = array(9 => 6, 12 => 6);
$WORLD['unattempted'] = array(qrow(20, 2, 9, 100, 'Second Rung'), qrow(2, 1, 12, 0, 'Untouched Project'));
$f = mission_frontier($conn);
ok(count($f) === 1 && $f[0]['quest_id'] === 20, 'the rule is per project, not global');

/* A rung you already ran and FAILED is not new -- you saw it. The query
   excludes any quest with a missions row, which the stub models by simply
   not returning it. What is checked here is that a cleared-but-lower rung
   that WAS never launched still shows, because it is a real missed one. */
mission_levels_forget(); $WORLD['levels'] = array(9 => 4);
$WORLD['balance']     = array(9 => 5000);
$WORLD['idle']        = array(9 => 6);
$WORLD['unattempted'] = array(qrow(21, 2, 9, 100, 'Skipped'), qrow(22, 5, 9, 900, 'Frontier'));
$f = mission_frontier($conn);
ok(count($f) === 2, 'a skipped lower rung counts too');
ok($f[0]['level'] === 5, 'the deepest launchable one is first');
ok(!empty($f[0]['frontier']) && empty($f[1]['frontier']), 'only the top rung is the frontier');

/* Ready-to-go outranks deep-but-blocked: the launchable one is the one
   about to be lost to the next Start All Free. */
$WORLD['balance']     = array(9 => 150);    // cannot afford the 900
$WORLD['unattempted'] = array(qrow(21, 2, 9, 100, 'Affordable'), qrow(22, 5, 9, 900, 'Too dear'));
$f = mission_frontier($conn);
ok($f[0]['quest_id'] === 21, 'a rung you can launch now sorts above a deeper one you cannot');
ok($f[1]['shortfall'] == 750, 'and the blocked one says how much short you are');

echo "\nart paths\n";
ok(mission_art_slug("Widow's Walk") === 'widows-walk', "the apostrophe is dropped, as getInventory() does");
ok(mission_art_slug('Enter the Galacticverse') === 'enter-the-galacticverse', 'spaces become hyphens');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all missions-lib checks passed\n";
