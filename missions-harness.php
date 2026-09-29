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
	'levels'    => array(9 => 2),        // project 9: cleared up to level 2, so 3 is open
	'balance'   => array(9 => 500),
	'eligible'  => array(),              // nft_id => rate, filled per case
	'amounts'   => array(),              // consumable_id => amount
	'quest'     => array(),
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
		if (strpos($flat, 'FROM quests q INNER JOIN projects p') !== false)
			return new MRes($WORLD['quest'] ? array($WORLD['quest']) : array());
		if (strpos($flat, 'FROM balances') !== false) {
			preg_match("/project_id = '(\d+)'/", $flat, $m);
			$p = isset($m[1]) ? (int)$m[1] : 0;
			return new MRes(isset($WORLD['balance'][$p]) ? array(array('balance' => $WORLD['balance'][$p])) : array());
		}
		if (strpos($flat, 'FROM nfts n INNER JOIN collections c') !== false) {
			$r = array();
			foreach ($WORLD['eligible'] as $id => $rate) $r[] = array('id' => $id, 'rate' => $rate);
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

echo "\nart paths\n";
ok(mission_art_slug("Widow's Walk") === 'widows-walk', "the apostrophe is dropped, as getInventory() does");
ok(mission_art_slug('Enter the Galacticverse') === 'enter-the-galacticverse', 'spaces become hyphens');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all missions-lib checks passed\n";
