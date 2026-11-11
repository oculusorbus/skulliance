<?php
/*
 * dhc-tether-harness.php — nobody is left holding half a weapon.
 *
 * The detection and the repair are driven against a stand-in connection,
 * so the SQL and the award calls can be inspected without a database. The
 * bug this pins was reported from the live site: a weapon that attaches to
 * the character and then cannot be saved, with the missing half absent
 * from the picker because the picker only offers what a player owns.
 */
require_once __DIR__ . '/dhcfighters-config.php';
require_once __DIR__ . '/dhcfighters-lib.php';

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

/** Answers one orphan row per pair, and records every query. */
class TetherSpy {
	public $sql = array();
	public $rows;
	public function __construct($rows = array()) { $this->rows = $rows; }
	public function query($q) {
		$this->sql[] = $q;
		$out = array_shift($this->rows);
		return $out === null ? false : new TetherRes($out);
	}
	public function real_escape_string($v) { return str_replace("'", "\\'", $v); }
}
class TetherRes {
	private $r; private $i = 0;
	public function __construct($r) { $this->r = $r; }
	public function fetch_assoc() { return isset($this->r[$this->i]) ? $this->r[$this->i++] : null; }
}

echo "\nevery tether is keyed both ways and folds to one pair\n";
$seen = array();
foreach (DHCF_TETHERED as $a => $b) {
	ok(isset(DHCF_TETHERED[$b]) && DHCF_TETHERED[$b] === $a,
	   "$a names $b as its partner but $b does not name $a, so one direction is orphaned");
	$seen[$a < $b ? "$a|$b" : "$b|$a"] = 1;
}
ok(count($seen) * 2 === count(DHCF_TETHERED),
   'the tether table is not an even set of mutual pairs');
/* Both halves must have a rarity row or the backfill cannot award one. */
$rar = dhcf_rarity(); $catOf = array();
foreach ($rar as $cat => $rows) foreach ($rows as $slug => $_) $catOf[$slug] = $cat;
foreach (DHCF_TETHERED as $a => $b) {
	ok(isset($catOf[$a]), "$a is tethered but has no rarity row, so it can never be awarded");
	ok(isset($catOf[$a]) && isset($catOf[$b]) && $catOf[$a] === $catOf[$b],
	   "$a and $b are in different categories, so the pair cannot be awarded together");
}
/* THE REPORTED PAIR, by name: the spike blaster and its strap. */
ok(isset(DHCF_TETHERED['dh-spike-blaster']) && DHCF_TETHERED['dh-spike-blaster'] === 'dh-spike-blaster-1',
   'the spike blaster is no longer tethered to its strap');

echo "\nthe orphan query counts, and does not merely look\n";
$spy = new TetherSpy();
dhcf_tether_orphans($spy);
ok(count($spy->sql) === count($seen),
   'the orphan scan ran ' . count($spy->sql) . ' queries for ' . count($seen) . ' pairs');
$all = implode("\n", $spy->sql);
/*
 * COUNTS, NOT PRESENCE. Two guns and one strap is owed a second strap, or
 * the player can build one Fighter and not the other. A query that asked
 * "do they hold any" would report that player as fine.
 */
ok(strpos($all, 'SUM(d.slug') !== false,
   'the scan tests for presence rather than counting copies, so two guns and one strap reads as whole');
ok(strpos($all, 'GROUP BY d.user_id') !== false, 'the scan does not group by player');
ok(strpos($all, 'LEFT JOIN users') !== false,
   'the scan does not fetch usernames, so the page can only show numeric ids');
/* It must read, never write. */
foreach (array('INSERT', 'UPDATE', 'DELETE') as $w) {
	ok(stripos($all, $w) === false, "the orphan scan issues a $w; it is supposed to only read");
}

echo "\nthe short half is the one that gets handed over\n";
/* a = copies of x, b = copies of y. Three guns and one strap is owed two
   straps -- and the direction has been wrong in a previous repair. */
$spy2 = new TetherSpy(array(array(
	array('user_id' => 7, 'username' => 'someone', 'a' => '3', 'b' => '1'),
	array('user_id' => 8, 'username' => 'other',   'a' => '0', 'b' => '2'),
)));
$got = dhcf_tether_orphans($spy2);
ok(count($got) === 2, 'the scan returned ' . count($got) . ' orphans, not 2');
if (count($got) === 2) {
	list($x, $y) = explode('|', array_keys($seen)[0]);
	ok($got[0]['owed'] === 2, 'three of one half and one of the other is owed '
	   . $got[0]['owed'] . ', not 2');
	ok($got[0]['slug'] !== $got[0]['partner'], 'a player is reported as owing themselves');
	ok($got[1]['owed'] === 2, 'none of one half and two of the other is owed '
	   . $got[1]['owed'] . ', not 2');
	/* The one they hold NONE of is the one they are short of. */
	ok($got[1]['slug'] === $x && $got[1]['partner'] === $y,
	   'the scan hands over the half the player already holds instead of the one they lack');
	ok($got[0]['slug'] === $y && $got[0]['partner'] === $x,
	   'the direction is inverted for a player short of the second half');
}
/* Equal holdings are not an orphan. */
$spy3 = new TetherSpy(array(array(array('user_id' => 9, 'username' => 'ok', 'a' => '2', 'b' => '2'))));
ok(count(dhcf_tether_orphans($spy3)) === 0,
   'a player holding two of each half is reported as owed something');

echo "\nthe repair is silent, and shaped like the draw\n";
$lib = '';
foreach (token_get_all((string) file_get_contents(__DIR__ . '/dhcfighters-lib.php')) as $t) {
	if (is_array($t)) { if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) continue; $lib .= $t[1]; }
	else $lib .= $t;
}
preg_match('~function dhcf_tether_repair\(\$conn, \$orphans\) \{(.*?)\n\}~s', $lib, $m_rp);
$rp = count($m_rp) === 2 ? $m_rp[1] : '';
ok($rp !== '', 'dhcf_tether_repair() could not be lifted');
/*
 * ANNOUNCING OFF. One Discord post per piece, for art the player has
 * effectively been holding all along, is noise -- and a backfill of a
 * hundred pieces would bury every real drop that week.
 */
ok(strpos($rp, 'false)') !== false && strpos($rp, 'DHCF_PAIRED_MARK') !== false,
   'the repair announces, or no longer marks the row as a paired half');
/* Through dhcf_commit_drop(), so the row is identical to the draw's. */
ok(strpos($rp, 'dhcf_commit_drop(') !== false,
   'the repair writes its own ledger row instead of going through dhcf_commit_drop()');
ok(strpos($rp, "'backfill'") !== false,
   'the repair no longer records itself as a backfill, so these rows are indistinguishable from drops');

echo "\nthe CLI and the page ask the same question\n";
$cli = (string) file_get_contents(__DIR__ . '/dhc-trait-migrate.php');
$adm = (string) file_get_contents(__DIR__ . '/admin-tethers.php');
ok(strpos($cli, 'dhcf_tether_orphans(') !== false && strpos($cli, 'dhcf_tether_repair(') !== false,
   'the CLI has its own copy of the scan or the repair again, so the two can disagree');
ok(strpos($adm, 'dhcf_tether_orphans(') !== false && strpos($adm, 'dhcf_tether_repair(') !== false,
   'the admin page does not use the shared functions');
/* A side effect on GET fires on every open, including a prefetch. */
ok(strpos($adm, "\$_SERVER['REQUEST_METHOD'] === 'POST'") !== false,
   'the repair runs on GET, so opening the page hands out traits');
ok(strpos($adm, 'header(\'Location: admin-tethers.php?done=') !== false,
   'the repair does not redirect after POST, so a refresh runs it twice');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
