<?php
/*
 * dropship-squad-autosync-harness.php -- squads maintain themselves, and
 * only ownership moves them.
 *
 * The engine is invisible: a player never saves, names or disperses a
 * squad, they just open the game and pick one. That only works if the
 * rebuild trigger is exactly right. Rebuild too eagerly and a squad
 * reshuffles under somebody between two runs; too rarely and a newly
 * bought NFT never joins one.
 *
 * THE TRIGGER IS OWNERSHIP AND NOTHING ELSE. Deploying, withdrawing,
 * dying and resurrecting must all leave the arrangement alone -- none of
 * them changes what can be built. Buying and selling must rebuild it.
 *
 * The database is faked in memory here: the real one needs credentials and
 * a live host, and what is being tested is the decision, not mysqli.
 */
require_once __DIR__ . '/dropship/dropship-odds.php';
require_once __DIR__ . '/dropship/dropship-grunts.php';
require_once __DIR__ . '/dropship/dropship-squad-plan.php';

/* dropship-squads.php expects a handful of helpers from db.php. Only the
   ones the sync path touches are needed. */
if (!function_exists('dropshipWord')) { function dropshipWord($w) { return $w; } }
if (!function_exists('evaluateText')) { function evaluateText($t) { return $t; } }
require_once __DIR__ . '/dropship/dropship-squads.php';

$fail = 0;
function ok($c, $m) { global $fail; if ($c) { echo "  ok    $m\n"; } else { echo "  FAIL  $m\n"; $fail++; } }

class FakeRes {
	public $rows; public $num_rows;
	function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	function fetch_assoc() { return array_shift($this->rows); }
}
class FakeDb {
	public $soldiers = array();          /* id => row (with active, deceased) */
	public $squads   = array();          /* sid => true */
	public $members  = array();          /* sid => array of soldier ids */
	public $insert_id = 0;
	public $log = array();
	private $next = 1;

	function query($sql) {
		$this->log[] = preg_replace('/\s+/', ' ', trim($sql));
		if (strpos($sql, 'SHOW TABLES') !== false) return new FakeRes(array(array('x' => 'squads')));
		if (strpos($sql, 'FROM soldiers') !== false) {
			$out = array();
			foreach ($this->soldiers as $s) {
				$out[] = array('id' => $s['id'], 'asset_name' => $s['asset_name'],
				               'name' => $s['asset_name'], 'rank' => 'NFT',
				               'armor' => $s['armor'], 'gear' => $s['gear']);
			}
			return new FakeRes($out);
		}
		/* Checked before the SELECT below, and matched on the DELETE's own
		   text: "DELETE m FROM squad_members m JOIN ..." contains the
		   substring the membership SELECT was being recognised by, so the
		   wipe was answered with an empty result set and the old
		   assignments survived every rebuild. The fake was wrong, not the
		   engine, which is its own reminder to make the fake fail too. */
		if (strpos($sql, 'DELETE m FROM squad_members') !== false) { $this->members = array(); return true; }
		if (strpos($sql, 'SELECT m.soldier_id') !== false) {
			$out = array();
			foreach ($this->members as $sid => $ids) {
				foreach ($ids as $id) $out[] = array('soldier_id' => $id, 'id' => $sid);
			}
			return new FakeRes($out);
		}
		if (strpos($sql, 'DELETE FROM squads') !== false)          { $this->squads  = array(); return true; }
		if (strpos($sql, 'INSERT INTO squads') !== false) {
			$sid = $this->next++;
			$this->squads[$sid] = true; $this->members[$sid] = array();
			$this->insert_id = $sid;
			return true;
		}
		if (preg_match('/INSERT INTO squad_members .* VALUES (.*)$/', $sql, $m)) {
			preg_match_all('/\((\d+),(\d+),(\d+)\)/', $m[1], $all, PREG_SET_ORDER);
			foreach ($all as $t) $this->members[(int)$t[1]][] = (int)$t[2];
			return true;
		}
		return new FakeRes(array());
	}
	function assigned() {
		$out = array();
		foreach ($this->members as $ids) foreach ($ids as $i) $out[] = (int)$i;
		sort($out); return $out;
	}
	function owned() { $o = array_keys($this->soldiers); sort($o); return $o; }
}

$_SESSION['userData'] = array('dropship_user_id' => 1, 'dropship_project_id' => 1);
$TIERS = dropshipArmorTiers();
$GEARS = array_merge(dropshipPlanItems(), array('None'));

$db = new FakeDb();
$nid = 1;
$add = function ($armor, $gear) use (&$db, &$nid) {
	$db->soldiers[$nid] = array('id' => $nid, 'asset_name' => 'NFT' . $nid,
	                            'armor' => $armor, 'gear' => $gear,
	                            'active' => 0, 'deceased' => 0);
	return $nid++;
};
foreach (array(array('Heavy','Medkit'), array('Medium','Melee'), array('Light','Demolition'),
               array('Base','None'), array('Heavy','Melee'), array('Medium','None')) as $s) $add($s[0], $s[1]);

echo "the first visit builds everything\n";
$r = dropshipSquadsAutoSync($db, 1, 1);
ok($r['changed'] === true, 'a player with no squads is left with none');
ok($db->assigned() === $db->owned(), 'not every soldier ended up in exactly one squad');
ok($r['complete'] >= 1, 'the complete squad was not found: ' . $r['complete']);

echo "\nand the next visit does nothing at all\n";
$before = count($db->log);
$r = dropshipSquadsAutoSync($db, 1, 1);
ok($r['changed'] === false, 'it rebuilt squads that were already correct');
$wrote = 0;
foreach (array_slice($db->log, $before) as $q) {
	if (preg_match('/^(INSERT|DELETE|UPDATE)/i', $q)) $wrote++;
}
ok($wrote === 0, "an in-sync visit still wrote to the database ($wrote statements)");

echo "\ndeploying is not an ownership change\n";
/* The roster query must not look at active or deceased, or fielding a
   squad would reshuffle the rest between runs. */
$sel = '';
foreach ($db->log as $q) if (strpos($q, 'FROM soldiers') !== false) { $sel = $q; break; }
ok(stripos($sel, 'active') === false,   'the roster query filters on active, so deploying reshuffles squads');
ok(stripos($sel, 'deceased') === false, 'the roster query filters on deceased, so dying reshuffles squads');
foreach ($db->soldiers as $id => $s) { $db->soldiers[$id]['active'] = 1; }
$r = dropshipSquadsAutoSync($db, 1, 1);
ok($r['changed'] === false, 'fielding the whole roster rebuilt the squads');
foreach ($db->soldiers as $id => $s) { $db->soldiers[$id]['deceased'] = 1; }
$r = dropshipSquadsAutoSync($db, 1, 1);
ok($r['changed'] === false, 'a wipe rebuilt the squads');

echo "\nbuying and selling are\n";
$new = $add('Base', 'Medkit');
$r = dropshipSquadsAutoSync($db, 1, 1);
ok($r['changed'] === true, 'a newly bought NFT did not trigger a rebuild');
ok(in_array($new, $db->assigned(), true), 'and it was not put in a squad');
ok($db->assigned() === $db->owned(), 'the rebuild lost or duplicated somebody');

unset($db->soldiers[2]);
$r = dropshipSquadsAutoSync($db, 1, 1);
ok($r['changed'] === true, 'selling an NFT did not trigger a rebuild');
ok($db->assigned() === $db->owned(), 'a sold soldier is still sitting in a squad');

echo "\nit survives a random life\n";
mt_srand(31337);
$bad = null;
for ($step = 0; $step < 400 && $bad === null; $step++) {
	$roll = mt_rand(0, 3);
	if ($roll === 0) { $add($TIERS[mt_rand(0, 3)], $GEARS[mt_rand(0, 3)]); }
	elseif ($roll === 1 && count($db->soldiers) > 1) {
		$ids = array_keys($db->soldiers); unset($db->soldiers[$ids[mt_rand(0, count($ids) - 1)]]);
	} elseif ($roll === 2) {
		foreach ($db->soldiers as $id => $s) $db->soldiers[$id]['active'] = mt_rand(0, 1);
	} else {
		foreach ($db->soldiers as $id => $s) $db->soldiers[$id]['deceased'] = mt_rand(0, 1);
	}
	dropshipSquadsAutoSync($db, 1, 1);
	if ($db->assigned() !== $db->owned()) { $bad = "step $step: assignment drifted from ownership"; break; }
	$seen = array();
	foreach ($db->members as $sid => $ids) {
		if (count($ids) > 4) { $bad = "step $step: squad $sid has " . count($ids) . ' members'; break 2; }
		foreach ($ids as $i) {
			if (isset($seen[$i])) { $bad = "step $step: soldier $i is in two squads"; break 3; }
			$seen[$i] = true;
		}
	}
}
ok($bad === null, 'the engine drifted: ' . $bad);

echo $fail ? "\n$fail check(s) FAILED\n" : "\nall good\n";
