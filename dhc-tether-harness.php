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
/*
 * EVERY COPY, NOT THE FREE ONES. The invariant is "holds as many straps
 * as weapons", and a worn gun has a worn strap so those cancel out of the
 * difference anyway.
 *
 * Counting only FREE copies would miss the case that matters most:
 * dhcf_shortfall() checks that each trait sent is owned and free but does
 * NOT enforce tethering -- both halves being sent is a client-side rule --
 * so a legacy or crafted save could have put a lone gun on a Fighter. That
 * player has 0 free guns and 0 free straps and reads as whole, while being
 * owed two. Counting totals reports them.
 *
 * It is also what makes the repair idempotent: once the counts balance, a
 * second run finds nothing whatever is equipped.
 */
ok(strpos($all, 'dhc_trait_drops') !== false,
   'the scan no longer counts the ledger, which is the only place every copy is recorded');
ok(stripos($all, 'dhc_fighters') === false && stripos($all, 'free') === false,
   'the scan has started filtering on what is free, so a lone half worn on a Fighter reads as whole');
$lib_pre = (string) file_get_contents(__DIR__ . '/dhcfighters-lib.php');
preg_match('~function dhcf_tether_orphans\(\$conn\) \{(.*?)\n\}~s', $lib_pre, $m_or);
ok(count($m_or) === 2 && strpos($m_or[1], 'dhcf_available(') === false,
   'the orphan scan consults dhcf_available(), so it counts free copies rather than every copy');

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

echo "\nevery path that grants a trait grants both halves\n";
/*
 * THE BUG THIS PINS, found in the ledger: four Lil Fren straps against
 * three Lil Frens, which no sequence of ordinary drops can produce.
 *
 * The partner award lived in dhcf_award(), so it only ran on a DRAW --
 * and three paths grant traits without drawing. The Portal's win, the
 * Portal's gifts and the welcome-back package all call
 * dhcf_commit_drop() directly, and every one of them could hand out half
 * a weapon that the assembler then refuses to build with.
 */
$lib_src = (string) file_get_contents(__DIR__ . '/dhcfighters-lib.php');
$lib_code = '';
foreach (token_get_all($lib_src) as $t) {
	if (is_array($t)) { if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) continue; $lib_code .= $t[1]; }
	else $lib_code .= $t;
}
$fn_award  = new ReflectionFunction('dhcf_award');
$fn_commit = new ReflectionFunction('dhcf_commit_drop');
$src_lines = explode("\n", $lib_src);
$body_of = function ($fn) use ($src_lines) {
	return implode("\n", array_slice($src_lines, $fn->getStartLine() - 1,
		$fn->getEndLine() - $fn->getStartLine() + 1));
};
$award_body  = $body_of($fn_award);
$commit_body = $body_of($fn_commit);

/*
 * DRIVEN, NOT READ. The first version of this searched the function for
 * dhcf_tethered_partner( and passed with the whole branch disabled --
 * the call was still there, just unreachable. Counting the INSERTs a
 * grant actually produces cannot be fooled that way.
 */
class TetherIns {
	public $q = array(); public $insert_id = 1; public $error = '';
	public function query($s) {
		$this->q[] = $s;
		if (stripos($s, 'INSERT') === 0) $this->insert_id++;
		return new TetherInsRes();
	}
	public function real_escape_string($v) { return $v; }
}
class TetherInsRes {
	private $i = 0; public $num_rows = 1;
	public function fetch_assoc() {
		return $this->i++ ? null : array('c' => 0, 'worn' => 0, 'username' => 'x');
	}
}
function tether_inserts($slug) {
	$c = new TetherIns();
	$info = dhcf_trait_info('weapon', $slug);
	if (!$info) return array();
	dhcf_commit_drop($c, 5, 'weapon', $slug, $info, 'portal', 'test', false, false);
	$out = array();
	foreach ($c->q as $q) {
		if (stripos($q, 'INSERT INTO dhc_trait_drops') !== 0) continue;
		if (preg_match("~,\s*'([a-z0-9-]+)',\s*'[a-z]+',~i", $q, $m)) $out[] = $m[1];
	}
	return $out;
}
$t_pair = tether_inserts('lil-fren');
ok(count($t_pair) === 2,
   'granting a tethered half wrote ' . count($t_pair) . ' ledger rows, not 2 -- '
 . 'every caller that does not draw can hand out half a weapon');
ok(in_array('lil-fren', $t_pair, true) && in_array('lil-fren-1', $t_pair, true),
   'granting lil-fren did not write both halves: ' . implode(', ', $t_pair));

/* AND EXACTLY TWO. Without the mark the partner pairs back to the trait
   that pulled it in, and that is an infinite recursion, not a duplicate. */
$t_solo = tether_inserts('dh-raider-equipment');
ok(count($t_solo) === 1,
   'granting an UNtethered trait wrote ' . count($t_solo) . ' rows, not 1');

ok(substr_count($award_body, 'dhcf_tethered_partner(') === 0,
   'dhcf_award() grants the partner as well, so a drawn pair is awarded twice');

/* THE MARK IS THE RECURSION GUARD, and without it this recurses forever:
   the partner's partner is the trait that pulled it in. */
ok(strpos($commit_body, 'DHCF_PAIRED_MARK) !== 0') !== false,
   'nothing stops a paired row pairing again, so a tethered award recurses');
ok(strpos($commit_body, 'DHCF_PAIRED_MARK . $slug') !== false,
   'the partner row is not marked, so it counts as a drop of its own AND pairs again');

/* Every direct caller is covered BY that, so none of them may carry its
   own copy -- two implementations is how the halves drift apart again. */
foreach (array('ajax/dhcportal-jettison.php', 'lib/return-gift.php') as $cf) {
	$csrc = (string) file_get_contents(__DIR__ . '/' . $cf);
	ok(strpos($csrc, 'dhcf_tethered_partner(') === false,
	   $cf . ' grants the tethered partner itself, so the partner is awarded twice');
}

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
