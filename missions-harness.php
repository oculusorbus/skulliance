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
	'ladder'      => array(),            // every (project_id, level, id) in level order
	'inflight'    => array(),            // missions rows still out
	'm_items'     => array(),            // mission_id => array(consumable_id => name)
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
		if (strpos($flat, 'SELECT project_id, level, id FROM quests') !== false) {
			$r = array();
			foreach ($WORLD['ladder'] as $row) $r[] = $row;
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
		if (strpos($flat, 'FROM missions_consumables mc') !== false) {
			$r = array();
			foreach ($WORLD['m_items'] as $mid => $its)
				foreach ($its as $cid => $name)
					$r[] = array('mission_id' => $mid, 'consumable_id' => $cid, 'name' => $name);
			return new MRes($r);
		}
		if (strpos($flat, 'SELECT m.id AS mission_id, m.quest_id, m.created_date') !== false)
			return new MRes($WORLD['inflight']);
		/* The drawer's NEIGHBOUR lookup: two LIMIT 1 subselects in a UNION,
		   nearest level either side.
		
		   This INTERPRETS the SQL rather than restating what it ought to
		   mean -- it reads the WHERE, the ORDER BY direction and the LIMIT
		   off each subselect. A stub that re-derives the intent cannot
		   fail: the first version of this one answered "nearest either
		   side" from the fixture directly, and flipping the real query's
		   ORDER BY to ASC/DESC -- which strands both arrows on a ladder
		   with a gap -- still passed. */
		if (strpos($flat, 'SELECT id, level FROM quests WHERE') !== false) {
			$r = array();
			foreach (explode('UNION ALL', $flat) as $sub) {
				$rows = $WORLD['ladder'];
				if (preg_match("/project_id = '(\d+)'/", $sub, $mp)) {
					$p = (int)$mp[1];
					$rows = array_filter($rows, function ($x) use ($p) {
						return (int)$x['project_id'] === $p; });
				}
				if (preg_match('/level ([<>]) (\d+)/', $sub, $ml)) {
					$op = $ml[1]; $lv = (int)$ml[2];
					$rows = array_filter($rows, function ($x) use ($op, $lv) {
						return $op === '<' ? (int)$x['level'] < $lv : (int)$x['level'] > $lv; });
				}
				$rows = array_values($rows);
				$desc = (bool)preg_match('/ORDER BY level DESC/', $sub);
				usort($rows, function ($a, $b) use ($desc) {
					return $desc ? (int)$b['level'] - (int)$a['level'] : (int)$a['level'] - (int)$b['level']; });
				if (preg_match('/LIMIT (\d+)/', $sub, $mn)) $rows = array_slice($rows, 0, (int)$mn[1]);
				foreach ($rows as $x) $r[] = array('id' => $x['id'], 'level' => $x['level']);
			}
			return new MRes($r);
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

/*
 * NOT EVEN FOR USER 1. The admin may OPEN a locked rung to check how it is
 * configured -- mission_is_admin() gates that -- but launching one is the
 * accident that jumps a cleared level past every rung underneath, which is
 * the most likely cause of the "unlocked but never run" rows on this very
 * account. The old page allowed it: getMissions() rendered the submit form
 * for locked missions when the discord id matched, and startMission() never
 * re-checked.
 */
$SESS_WAS = $_SESSION;
$_SESSION = array('userData' => array('user_id' => 1, 'discord_id' => '772831523899965440'));
$WROTE = array(); $conn = new MConn();
ok(mission_is_admin(), 'user 1 is the admin');
$r = mission_launch($conn, 78, array(10), array());
ok(empty($r['ok']), 'and a locked mission is refused for the admin too');
ok(!wrote('mission'), 'nothing written for the admin either');
$_SESSION = $SESS_WAS;

reset_world(null, array(10 => 30), array(), 40);   // cost 100, balance 40
$r = mission_launch($conn, 77, array(10), array());
ok(empty($r['ok']), 'refused when you cannot afford it');
ok(!wrote('mission'), 'nothing written when broke');
ok(strpos($r['message'], '60') !== false, 'the refusal says how much short you are');

reset_world(null, array(), array());
$r = mission_launch($conn, 77, array(), array());
ok(empty($r['ok']), 'refused with no squad and no success item');
ok(!wrote('mission'), 'nothing written for an empty load-out');

/*
 * AN ITEM-ONLY LOAD-OUT IS LEGAL, BUT ONLY WITH THE ROSTER HOME.
 *
 * Start Max Maxi launches with no NFTs at all -- that is how twenty
 * missions go out on one roster -- but renderMaxMaxiMissionsButton()
 * refuses to appear unless maxMaxiAvailableNfts() > 0. Holding NFTs back
 * is what buys the right to spend an item; send everything and you are
 * locked out until they come home. Without this the launcher would let
 * anyone fire missions off nothing but item stock forever.
 */
reset_world(null, array(10 => 30), array(1 => 1));   // one NFT home, not sent
$r = mission_launch($conn, 77, array(), array(1));
ok(!empty($r['ok']), 'a 100% item sends a mission with no crew while some roster is home');
ok($r['success'] == 100, 'and it is a 100% mission');
ok(!wrote('nft'), 'and it really does send nobody -- the NFTs stay available');

reset_world(null, array(), array(1 => 1));           // whole roster deployed
$r = mission_launch($conn, 77, array(), array(1));
ok(empty($r['ok']), 'the same item is refused once the whole roster is out');
ok(!wrote('mission'), 'and nothing is written');
ok(!wrote('amount'), 'the item is not consumed by a refused launch');
echo "  refusal reads: " . ($r['message'] ?? '?') . "\n";

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

echo "\nrungs slotted in after the player passed them\n";

/*
 * THE QUESTION THIS ANSWERS: if I never ran level 31, how is level 33
 * open? Because level 31 did not exist when they climbed past. quests.id
 * is auto-increment, so a rung whose id is higher than a HIGHER-level
 * rung in the same project was inserted into the middle afterwards.
 */
mission_levels_forget();
$WORLD['levels']  = array(9 => 32);
$WORLD['balance'] = array(9 => 99999);
$WORLD['idle']    = array(9 => 6);
$WORLD['ladder']  = array(
	array('project_id' => 9, 'level' => 31, 'id' => 900),   // added last
	array('project_id' => 9, 'level' => 32, 'id' => 400),
	array('project_id' => 9, 'level' => 33, 'id' => 401),
);
$WORLD['unattempted'] = array(qrow(900, 31, 9, 100, 'Queen of Hearts'),
                              qrow(401, 33, 9, 100, 'Cybernetic Research'));
$f = mission_frontier($conn);
$by = array(); foreach ($f as $r) $by[$r['quest_id']] = $r;
ok(count($f) === 2, 'both unlocked-and-unrun rungs are listed');
ok(!empty($by[900]['added_later']), 'a rung with a higher id than the levels above it was added later');
ok(empty($by[401]['added_later']), 'the genuine frontier rung was not');
ok(!empty($by[401]['frontier']) && empty($by[900]['frontier']), 'and only 33 is the frontier');

/* A ladder built in order must never be flagged. */
mission_levels_forget();
$WORLD['ladder'] = array(
	array('project_id' => 9, 'level' => 1, 'id' => 10),
	array('project_id' => 9, 'level' => 2, 'id' => 11),
	array('project_id' => 9, 'level' => 3, 'id' => 12),
);
$WORLD['levels'] = array(9 => 1);
$WORLD['unattempted'] = array(qrow(11, 2, 9, 100, 'In order'));
$f = mission_frontier($conn);
ok(count($f) === 1 && empty($f[0]['added_later']), 'a ladder authored in order flags nothing');

echo "\nmission descriptions keep their markup, and only their markup\n";

/*
 * An allow-list, so the interesting cases are the ones it must REFUSE.
 * A quest description is authored through the database rather than a
 * form, but it is still rendered into innerHTML on a page where the
 * player is about to spend points, so "it is trusted content" is not a
 * reason to hand it a script tag.
 */
function rt($in) { return mission_rich_text($in); }

ok(rt('Plain prose, nothing to do.') === 'Plain prose, nothing to do.', 'plain text is untouched');
ok(rt('one<br>two') === 'one<br>two', '<br> survives -- descriptions use it');
ok(rt('a <b>bold</b> and <em>emphasis</em>') === 'a <b>bold</b> and <em>emphasis</em>',
   'the bare formatting tags survive');

$link = rt('see <a href="https://xrp.cafe/collection/bootlegs">the drop</a> now');
ok(strpos($link, '<a href="https://xrp.cafe/collection/bootlegs"') !== false, 'a real link is rendered');
ok(strpos($link, 'target="_blank"') !== false, 'and opens away from the page');
ok(strpos($link, 'rel="noopener noreferrer"') !== false, 'with the opener severed');
ok(strpos($link, '&lt;a') === false, 'no escaped tag left showing in the prose');

$js = rt('<a href="javascript:alert(1)">tap</a>');
ok(strpos($js, '<a') === false, 'a javascript: href is not a link');
ok(strpos($js, 'tap') !== false, 'but its text is kept');
ok(strpos($js, '</a>') === false, 'and no stray closing tag is left behind');
ok(strpos($js, 'alert') === false || strpos($js, 'href') === false, 'the payload never reaches an attribute');

$data = rt('<a href="data:text/html;base64,PHNjcmlwdD4=">x</a>');
ok(strpos($data, '<a') === false, 'a data: href is not a link either');

$scr = rt('hi <script>alert(1)</script>');
ok(strpos($scr, '<script') === false, 'a script tag is escaped, not run');
ok(strpos($scr, '&lt;script&gt;') !== false, 'it shows as text');

$img = rt('<img src=x onerror="alert(1)">');
ok(strpos($img, '<img') === false, 'an img tag is not on the list');

$attr = rt('<b onclick="alert(1)">bold</b>');
ok(strpos($attr, '<b onclick') === false, 'an allowed tag carrying an attribute is NOT restored');
ok(strpos($attr, '&lt;b onclick') !== false, 'it stays escaped, which is the safe direction');

/* The reason the href is captured lazily to its MATCHING closing entity
   rather than "up to the next &": a query string is full of ampersands,
   and stopping at the first one silently truncated the URL. */
$q = rt('<a href="https://xrp.cafe/c?id=7&amp;sort=new">list</a>');
ok(strpos($q, 'id=7&amp;sort=new') !== false, 'a query string survives intact');

/* Mixed: one link that stands and one that is dropped. The closers have to
   balance against what actually survived. */
$mix = rt('<a href="https://a.com">good</a> and <a href="javascript:x">bad</a>');
ok(substr_count($mix, '<a href=') === 1, 'only the good link is rendered');
ok(substr_count($mix, '</a>') === 1, 'and exactly one closing tag is left');
ok(strpos($mix, 'bad') !== false, 'the dropped one keeps its text');

ok(rt('Rate is 5 &amp; rising') === 'Rate is 5 &amp; rising', 'an existing entity is not double-encoded');
ok(rt('') === '' && rt(null) === '', 'empty and null are handled');

echo "\nart paths\n";
ok(mission_art_slug("Widow's Walk") === 'widows-walk', "the apostrophe is dropped, as getInventory() does");
ok(mission_art_slug('Enter the Galacticverse') === 'enter-the-galacticverse', 'spaces become hyphens');

/* ---------- the page shell: the sticky nav --------------------------------
 *
 * Two rules, both of which look like styling and are not.
 *
 * THE INSET IS PADDING, NOT `top`. body carries
 * padding-top:env(safe-area-inset-top) and that padding SCROLLS AWAY, so a
 * bar pinned at top:env(inset) leaves a transparent band above it that page
 * content rides up through -- into the clock and the battery. Measured both
 * ways in Chrome against a stand-in 59px inset: with the old rule the element
 * painted 20px down the screen was a mission card, with this one it is the
 * bar. The inset has to be the bar's own padding so its background owns that
 * band at every scroll position.
 *
 * THE CONTAINING BLOCK. .ms-nav lives inside .main, a flex child with auto
 * height (measured 3753px against a 1323px .container), which is the only
 * reason this page's sticky holds all the way down. realms.php put its nav
 * as a direct child of .container -- height:100%, one viewport -- and it
 * unpinned one screenful down. If this nav is ever moved out of .main, that
 * is the failure to expect.
 */
/* ---------- the headline follows the page --------------------------------
 *
 * "4 ready to collect" stayed on the page after all four were collected,
 * and the In-the-field figure read 200 while the nav badge beside it
 * already said 196. Only the nav badge and the Ready chip were ever being
 * repainted; the h2 and the other three figures were first-paint PHP that
 * nothing touched again. The DATA was already there -- both endpoints have
 * been returning `overview` all along and the callbacks threw it away.
 *
 * The logic itself is exercised by missions-head-harness.js, which runs the
 * real paintOverview() against a DOM stub. This side just holds the
 * contract the two of them depend on: the hooks in the markup, and the
 * endpoints actually sending an overview.
 */
echo "\nthe headline and figures repaint\n";
$msrc = file_get_contents(__DIR__ . '/missions.php');
ok(strpos($msrc, 'id="ms-title"') !== false,
   'the headline has no id, so nothing can rewrite it after a collect');
/* AND IT COUNTS THE ACCOUNT, NOT THE PAGE. The field list is capped at
   MS_FIELD_CAP with the rest behind Show all, so counting the rendered
   rows announced "24 in the field" to someone with 196 out -- while the
   figure beside it and the nav badge both showed the real number. */
ok(strpos($msrc, "ms_n(count(\$ms_active)) . ' in the field'") === false,
   'the headline is counting the RENDERED rows again, which is the capped '
 . 'list, not how many missions are actually out');
ok(strpos($msrc, "ms_n(\$ms_total) . ' in the field'") !== false,
   'the headline is not using $ms_total (mission_active_total), so it cannot '
 . 'be the real number of missions in the field');
/* Anchored to the MARKUP. A bare strpos passes on the querySelector in
   paintOverview() itself, which is how removing the real hook read as
   fine. */
foreach (array('active', 'ready', 'completed', 'levels') as $fig) {
	ok(preg_match('/<div class="ms-fig[^"]*" data-fig="' . $fig . '"/', $msrc) === 1,
	   "the $fig figure lost its data-fig hook and will go stale after a collect");
}
/* Matched on the call shape, which appears nowhere in a comment in this
   file -- realms-harness.php's no_comments() does not live here. */
ok(substr_count($msrc, 'paintField(j.html, j.ready, j.overview)') === 2
   && strpos($msrc, 'paintField(j.field, j.ready, j.overview)') !== false,
   'a paintField() caller is dropping the overview again, so the figures '
 . 'beside the headline will disagree with the nav badge');
foreach (array('ajax/mission-data.php', 'ajax/mission-launch.php') as $ep) {
	ok(strpos(file_get_contents(__DIR__ . '/' . $ep), 'mission_overview($conn)') !== false,
	   "$ep no longer returns an overview, so the header cannot be repainted");
}
ok(is_file(__DIR__ . '/missions-head-harness.js'),
   'missions-head-harness.js is gone; the repaint rules are unchecked');

/* The empty-roster notice is rendered INTO .ms-d-crew, which is a
   62px-track grid, so without a span it wraps to a word or two per line. */
ok(preg_match('/\.ms-d-crew \.ms-d-desc \{[^}]*grid-column:\s*1 \/ -1/', $msrc) === 1,
   'the "everything is out on a mission" notice is not spanning the crew '
 . 'grid, so it renders as a 62px-wide sliver of text');
/* US spelling, like the rest of the platform's buttons. */
ok(strpos($msrc, '>Maximise<') === false,
   'the load-out tool button is back to the British spelling');

echo "\nthe sticky section nav\n";
$msrc = file_get_contents(__DIR__ . '/missions.php');
ok(preg_match('/\.ms-nav\s*\{.*?position:\s*sticky;\s*top:\s*env\(safe-area-inset-top/s', $msrc) === 1,
   'the sticky nav does not pin at the safe-area inset, so its links sit in '
 . 'the strip the status bar owns');
/*
 * AND IT PAYS THE INSET ONLY WHILE STUCK, NEVER AS HEIGHT.
 *
 * Paying it as padding covered the status-bar strip and cost a permanent
 * inset-tall gap above the bar at rest -- ~59px of nothing between the
 * figures and the tabs on an iPhone. The bar pins at the inset again, and
 * a ::before paints the strip only when .stuck is on. It is absolutely
 * positioned, so it has no layout box and the bar's height cannot change
 * between the two states -- which is what stops the page jumping as it
 * sticks. Measured: gap above the nav 0px at rest, height 39 in both
 * states, and a card visible in the strip with the band off but not with
 * it on.
 */
ok(preg_match('/\.ms-nav\s*\{.*?padding:\s*calc\(\s*[0-9]+px\s*\+\s*env\(safe-area-inset-top/s', $msrc) === 0,
   'the nav is paying the safe-area inset as padding again, which is a '
 . 'permanent inset-tall gap above it at rest');
ok(preg_match('/\.ms-nav::before\s*\{[^}]*height:\s*env\(safe-area-inset-top/s', $msrc) === 1,
   'nothing paints the status-bar strip above the stuck nav, so page content '
 . 'scrolls through it into the clock and the battery');
ok(preg_match('/\.ms-nav::before\s*\{[^}]*position:\s*absolute/s', $msrc) === 1,
   'the band has a layout box, so the nav changes height when it sticks and '
 . 'the page jumps');
ok(preg_match('/\.ms-nav::before\s*\{[^}]*display:\s*none/s', $msrc) === 1
   && preg_match('/\.ms-nav\.stuck::before\s*\{[^}]*display:\s*block/', $msrc) === 1,
   'the band is painted even when the nav is not stuck, where it covers the '
 . 'page content sitting above the nav');
ok(strpos($msrc, "classList.toggle('stuck'") !== false
   && strpos($msrc, 'function stickTop()') !== false,
   'nothing marks the nav as stuck, so the band never appears');
ok(preg_match('/scroll-margin-top:\s*calc\(env\(safe-area-inset-top/', $msrc) === 1,
   'the sections lost their scroll-margin-top, so a jump lands the heading '
 . 'behind the bar');
/* .main is what makes the pin hold; the nav has to stay inside it. */
$navAt  = strpos($msrc, '<nav class="ms-nav"');
$mainAt = strrpos(substr($msrc, 0, $navAt === false ? 0 : $navAt), '<div class="main">');
ok($navAt !== false && $mainAt !== false,
   'the nav is no longer rendered inside <div class="main"> -- that is the '
 . 'auto-height box its position:sticky depends on, and outside it the pin '
 . 'dies one viewport down the way realms.php did');

/* ---------- stepping through the ladder from inside the drawer ----------- *
 *
 * The drawer grew prev/next arrows, which means the answer to "is the next
 * rung mine to see" is now asked in two places: by the arrow, and by
 * ajax/mission-data.php when the arrow is clicked. If those two disagree
 * the arrow is live and opens an error.
 *
 * So the lock decision is made HERE, once, as `next_open`, using the same
 * rule `locked` uses one screen up -- and the checks below are about that
 * agreement, not about the arrow's appearance.
 */
echo "\nstepping through the ladder\n";

function lo_world($level, $done, $ladder) {
	global $WORLD, $conn;
	$WORLD['levels']  = array(9 => $done);
	$WORLD['balance'] = array(9 => 500);
	$WORLD['ladder']  = $ladder;
	$WORLD['eligible'] = array();
	$WORLD['quest']   = array('id' => 77, 'title' => 'Mid Ladder', 'description' => 'go',
		'cost' => 100, 'reward' => 300, 'duration' => 2, 'extension' => 'png',
		'level' => $level, 'project_id' => 9, 'currency' => 'STAR',
		'project_name' => 'Test Project');
	$conn = new MConn();
	mission_levels_forget();
	return mission_loadout($conn, 77);
}
$LAD = array(
	array('project_id' => 9, 'level' => 1, 'id' => 10),
	array('project_id' => 9, 'level' => 2, 'id' => 11),
	array('project_id' => 9, 'level' => 3, 'id' => 12),
	array('project_id' => 9, 'level' => 4, 'id' => 13),
);

/* Standing on 3 with 2 cleared: 3 is open, 4 is not. */
$lo = lo_world(3, 2, $LAD);
ok($lo['prev_id'] === 11, 'the rung below is not offered');
ok($lo['next_id'] === 13, 'a LOCKED next rung still comes back, so the arrow can be disabled rather than vanish');
ok($lo['next_locked'] === true, 'the rung above a cleared+1 level is not reported locked');
ok($lo['next_open'] === false, 'a staker is being offered a rung the endpoint will refuse');

/* The admin exemption, which is the whole reason next_open exists apart
   from next_locked: ajax/mission-data.php serves a locked loadout to user
   1, so the arrow has to be live for user 1 and nobody else. */
$SESS_WAS = $_SESSION;
$_SESSION = array('userData' => array('user_id' => 1, 'discord_id' => '772831523899965440'));
$lo = lo_world(3, 2, $LAD);
ok(mission_is_admin(), 'user 1 is the admin');
ok($lo['next_locked'] === true, 'the rung is still reported locked for the admin -- it IS locked');
ok($lo['next_open'] === true, 'the admin cannot step onto a locked rung the endpoint would serve them');
$_SESSION = $SESS_WAS;

/* Either end of the ladder. */
$lo = lo_world(1, 0, $LAD);
ok($lo['prev_id'] === null, 'the first rung reports something below it');
ok($lo['next_id'] === 11, 'and still offers the one above');

$lo = lo_world(4, 9, $LAD);
ok($lo['next_id'] === null, 'the last rung reports something above it');
ok($lo['next_locked'] === false, 'a missing next rung is being reported as locked');
ok($lo['next_open'] === false, 'and the arrow would be live with nowhere to go');

/* NEAREST level, not level+-1. The ladder is ORDER BY level ASC, so a
   project whose numbering skips -- which an interrupted reorder leaves
   behind -- must still step from card to card the way it looks. */
$GAP = array(
	array('project_id' => 9, 'level' => 2, 'id' => 20),
	array('project_id' => 9, 'level' => 5, 'id' => 21),
	array('project_id' => 9, 'level' => 9, 'id' => 22),
);
$lo = lo_world(5, 9, $GAP);
ok($lo['prev_id'] === 20, 'a gap in the numbering strands the back arrow');
ok($lo['next_id'] === 22, 'a gap in the numbering strands the forward arrow');

/* Another project's rungs are not this ladder. The other project's rows
   come FIRST and sit at the same levels, so an unscoped lookup returns
   THEM on the tie -- with this ladder's rows first, dropping the
   project_id from the query changed nothing and the check was decorative. */
$lo = lo_world(3, 2, array_merge(array(
	array('project_id' => 4, 'level' => 2, 'id' => 80),
	array('project_id' => 4, 'level' => 4, 'id' => 81)), $LAD));
ok($lo['prev_id'] === 11 && $lo['next_id'] === 13, 'the arrows walk into another project\'s ladder');

/*
 * AND THE CLIENT MUST NOT RE-DERIVE IT. The arrow reads next_open and
 * nothing else; the moment the page starts comparing levels to cleared
 * counts for itself, it can disagree with the endpoint -- which is the
 * one failure mode this design exists to prevent.
 */
$mp = file_get_contents(__DIR__ . '/missions.php');
ok(strpos($mp, 'LO.next_open') !== false, 'the drawer no longer reads next_open');
ok(strpos($mp, 'next_locked') === false,
   'missions.php mentions next_locked -- the lock decision has leaked into the client');

/*
 * Stepping replaces the drawer's body while it is open, so it has to do
 * what closing does. Two things bite otherwise, and both were live bugs
 * in the shape of this one: a detached <video> keeps playing (see
 * msCloseDrawer), and consumables picked for one mission stay picked.
 */
/* The PREAMBLE only -- everything msOpenDrawer does before it asks the
   server. Sliced to the fetch rather than to msCloseDrawer, because
   msStopMedia's own declaration sits between the two and a slice that
   reaches it finds the name whether or not anything calls it. */
$step = substr($mp, strpos($mp, 'window.msOpenDrawer = function'));
$step = substr($step, 0, strpos($step, "fetch('ajax/mission-data.php?what=loadout"));
ok(strpos($step, 'msStopMedia()') !== false,
   'opening a mission over an open one leaves the old video playing');
ok(strpos($step, 'items = {}') !== false && strpos($step, 'shedByItem = {}') !== false,
   "stepping carries the previous mission's consumables into the next launch");

/* ---------- Fast Forward is a shorter wait, and the drawer says so ------- *
 *
 * It is NOT stored as a shorter duration. mission_active() and
 * completeMission() both pull created_date BACK by ceil(duration / 2) and
 * then count the full duration forward from there, so the wait left over
 * is duration - ceil(duration / 2) -- which is ZERO on a one-day mission.
 *
 * This section is the AUTHORITY for that number. The drawer now prints it
 * before the item is spent, and missions-fastforward-harness.js drives the
 * browser's copy of the arithmetic against the same table, because the two
 * being written in different languages is exactly how they drift.
 */
echo "\nfast forward shortens the wait\n";

/* Shared with the JS harness, which reads it out of this file. */
$FF_TABLE = array(1 => 0, 2 => 1, 3 => 1, 4 => 2, 5 => 2, 6 => 3, 7 => 3, 8 => 4);

function ff_wait($conn, $duration, $with_item) {
	global $WORLD;
	$WORLD['inflight'] = array(array(
		'mission_id' => 500, 'quest_id' => 77, 'created_date' => date('Y-m-d H:i:s'),
		'status' => '0', 'title' => 'Timed', 'cost' => 0, 'reward' => 300,
		'duration' => $duration, 'extension' => 'png', 'level' => 3,
		'project_name' => 'Test Project', 'currency' => 'STAR',
		'total_nfts' => 1, 'squad_rate' => 30));
	$WORLD['m_items'] = $with_item
		? array(500 => array(MISSION_ITEM_FAST_FORWARD => 'Fast Forward'))
		: array();
	$a = mission_active($conn);
	if (!$a) return null;
	/* Back to whole days the same way the lib counts them forward. */
	return (int)round(($a[0]['due'] - time()) / 86400);
}

foreach ($FF_TABLE as $d => $expect) {
	ok(ff_wait($conn, $d, false) === $d, "a $d-day mission with no item does not wait $d days");
	ok(ff_wait($conn, $d, true) === $expect,
	   "a $d-day mission with Fast Forward waits " . ff_wait($conn, $d, true) . ", not $expect days");
}
/* The one that is easy to get wrong in either direction: half of one day
   is not one day and it is not half a day, it is nothing left to wait. */
ok(ff_wait($conn, 1, true) === 0, 'Fast Forward on a one-day mission does not land it immediately');
$WORLD['inflight'] = array(); $WORLD['m_items'] = array();

/* ---------- mission art: one pairing rule, five call sites --------------- *
 *
 * quests.extension is the format a mission was AUTHORED in, which for an
 * mp4 is not the picture: an mp4 ships two files, the video and a .gif
 * beside it, and every surface wanting an <img> wants the gif.
 *
 * That ternary used to be written out at each call site. Four had it and
 * two did not, and both were live defects -- mission_active() SELECTed
 * q.extension and then hardcoded '.png', breaking the field-list image for
 * 266 of 455 missions, and the share payload handed Discord a .mp4 as an
 * embed image. The rule now lives in mission_art_ext(), and what this
 * section really guards is that nobody restates it inline again.
 */
echo "\nmission art\n";
$ml = file_get_contents(__DIR__ . '/missions-lib.php');

ok(mission_art_ext('mp4') === 'gif', 'an mp4 mission no longer shows its paired .gif as the still');
foreach (array('png', 'jpg', 'gif') as $e) {
	ok(mission_art_ext($e) === $e, "a $e mission's still is no longer its own file");
}
/* NOT mapped on purpose: nothing here can read a mov, and pointing at a
   .gif that was never produced turns a visible break into a silent one. */
ok(mission_art_ext('mov') === 'mov', 'mov is being quietly remapped to a file nobody generated');

/* The filename IS the title, so the slug has to stay filesystem-safe. */
ok(mission_art_slug("Ohh Meed's Bar") === 'ohh-meeds-bar', 'the art slug no longer strips apostrophes and spaces');
ok(mission_art_slug('Enter The Galacticverse') === 'enter-the-galacticverse', 'the art slug is no longer lowercased');

/* No art path may restate the pairing or assume a format. Both of the
   fixed bugs are exactly this shape, so the guard is textual -- and it
   counts rather than greps, because the rule legitimately appears once:
   inside mission_art_ext() itself. */
$body = preg_replace('!/\*.*?\*/!s', '', $ml);
ok(substr_count($body, "'mp4') ? 'gif'") === 1,
   'the mp4->gif rule is written in ' . substr_count($body, "'mp4') ? 'gif'")
 . ' places; it belongs in mission_art_ext() and nowhere else');
ok(preg_match("~images/missions/[^\n]*\\.png'~", $body) !== 1,
   "an art path hardcodes .png again -- that is the mission_active() bug, and it is invisible for png missions");
/* Every still-image path must reach the helper. Four call sites build one
   (browse, picker, loadout, active) and the share payload is the fifth. */
/* MINUS THE DEFINITION. Counting raw occurrences includes
   `function mission_art_ext(` itself, so five call sites plus the
   declaration is six -- and a `>= 5` check passed with a call site
   deleted, which is exactly the share-card bug coming back. */
$uses = substr_count($body, 'mission_art_ext(') - substr_count($body, 'function mission_art_ext(');
ok($uses >= 5, "only $uses call site(s) use mission_art_ext(); there are five art paths and one has stopped");

echo "\nmission art busts the cache\n";

/*
 * The server sends mission art with max-age=604800. Re-uploading a file
 * under the same name therefore changes nothing for anyone who has seen
 * the old one, for SEVEN DAYS -- which is indistinguishable from the
 * upload having failed, and is exactly how it presented when Buffy Bot's
 * frame timing was fixed and the browser kept the broken copy.
 */
$probe_dir = __DIR__ . '/images/missions';
@mkdir($probe_dir, 0755, true);
$probe = $probe_dir . '/_harness_probe.png';
file_put_contents($probe, 'x');
$u = mission_art_url('_harness_probe', 'png');
ok(strpos($u, 'images/missions/_harness_probe.png') === 0, "the path changed shape: $u");
ok(preg_match('/\?v=\d+$/', $u) === 1,
   "a re-uploaded image stays cached for a week: no ?v= on $u");
/* The buster must track the FILE, not the clock, or every request is a
   cache miss and the max-age is wasted. */
$again = mission_art_url('_harness_probe', 'png');
ok($u === $again, 'the buster changes between calls, so the image is never cached at all');
touch($probe, time() + 60);
clearstatcache(true, $probe);
ok(mission_art_url('_harness_probe', 'png') !== $u,
   'the buster did not change when the file did, which is the whole point');
@unlink($probe);

/* A missing file must not produce "?v=" with nothing after it. */
$missing = mission_art_url('_harness_absent', 'png');
ok(strpos($missing, '?') === false, "a missing file produced a malformed url: $missing");

/*
 * AND IT IS BUILT IN ONE PLACE. This path was spelled out at seven call
 * sites; six of them would have kept serving stale art while one did
 * not, which is worse than all seven being wrong.
 */
$body = preg_replace('!/\*.*?\*/!s', '', file_get_contents(__DIR__ . '/missions-lib.php'));
ok(substr_count($body, "'images/missions/'") === 1,
   'the art path is spelled out at ' . substr_count($body, "'images/missions/'")
 . ' places again; it belongs in mission_art_url() and nowhere else');
ok(substr_count($body, 'mission_art_url(') >= 7,
   'only ' . substr_count($body, 'mission_art_url(') . ' uses of mission_art_url(); there are '
 . 'seven art paths and one has stopped going through it');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all missions checks passed\n";
