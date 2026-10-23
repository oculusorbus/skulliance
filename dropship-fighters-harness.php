<?php
/**
 * dropship-fighters-harness.php — assembled Fighters survive the NFT sync.
 *
 * Run: php dropship-fighters-harness.php
 *
 * THE BUG THIS EXISTS FOR. updateSoldiers() detaches the player's whole
 * roster before re-attaching the list it was handed, which is how a sold
 * NFT lets go of its soldier. DHC2 Fighters has TWO sources, so the second
 * call wiped the first one's work: the Fighters synced, created rows, were
 * attached, and were detached again by the Koios pass. Four NFTs, no
 * Fighters, every query succeeding, nothing in the log.
 *
 * So the attach is checked by RUNNING IT -- the real function, lifted out
 * of db.php, against a stub connection that records the SQL. A check that
 * only grepped for dropshipClaimedSoldiers() would pass on a version that
 * called it and threw the answer away.
 */
require_once __DIR__ . '/harness-strip.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

/*
 * LIFTED, not included: dropship/db.php opens a database connection at
 * file scope. The two functions are pulled out by source text and eval'd,
 * so what runs below is the shipping code and not a paraphrase of it.
 */
$db_src = file_get_contents(__DIR__ . '/dropship/db.php');
function lift($src, $sig) {
	$at = strpos($src, $sig);
	if ($at === false) return null;
	$i = strpos($src, '{', $at);
	$depth = 0; $n = strlen($src);
	for ($j = $i; $j < $n; $j++) {
		if ($src[$j] === '{') $depth++;
		elseif ($src[$j] === '}') { $depth--; if ($depth === 0) return substr($src, $at, $j - $at + 1); }
	}
	return null;
}
$f_claim = lift($db_src, 'function dropshipClaimedSoldiers(');
$f_upd   = lift($db_src, 'function updateSoldiers(');
ok($f_claim !== null, 'dropshipClaimedSoldiers() is gone from db.php');
ok($f_upd   !== null, 'updateSoldiers() is gone from db.php');
if ($f_claim === null || $f_upd === null) { echo "\ncannot run without both\n"; exit(1); }
eval($f_claim);
eval($f_upd);

/** Records every statement and answers TRUE, like a successful query. */
class FhConn {
	public $sql = array();
	public $error = '';
	public function query($s) { $this->sql[] = preg_replace('/\s+/', ' ', trim($s)); return true; }
	public function real_escape_string($s) { return addslashes((string)$s); }
}
$_SESSION['userData'] = array('dropship_user_id' => 7, 'dropship_project_id' => 3);

echo "\nthe accumulator unions, which is what makes order irrelevant\n";
/*
 * Run FIRST and on its own names, because the static is per process and
 * cannot be reset -- the roster test below asserts presence, not equality,
 * so these three are free to stay in it. Union is commutative, which IS
 * the order-independence property; asserting it here is cheaper and more
 * honest than running the dashboard's two passes twice in both orders.
 */
$u = dropshipClaimedSoldiers(array('AAA1', 'AAA2'));
sort($u);
ok($u === array('AAA1', 'AAA2'), 'the first call does not report what it was given');
$u = dropshipClaimedSoldiers(array('AAA3'));
sort($u);
ok($u === array('AAA1', 'AAA2', 'AAA3'),
   'a second call replaced the list instead of adding to it, which is the bug');
$u = dropshipClaimedSoldiers(array('AAA3', '', '  '));
sort($u);
ok($u === array('AAA1', 'AAA2', 'AAA3'),
   'a repeat or a blank name got in -- a blank would widen the IN () to match nothing useful');
ok(dropshipClaimedSoldiers() === $u, 'reading without adding changed the set');

echo "\ntwo sources, one roster\n";
$conn = new FhConn();
/* The Fighter sync runs first, exactly as dashboard.php orders it. */
updateSoldiers($conn, implode("', '", array('DHCFA11', 'DHCFA12')));
/* Then the Koios pass, with only the minted names. */
updateSoldiers($conn, implode("', '", array('DHC2F001', 'DHC2F002')));
$last = end($conn->sql);
ok(strpos($last, "'DHCFA11'") !== false && strpos($last, "'DHCFA12'") !== false,
   'the Koios pass detached the Fighters again -- the original bug is back');
ok(strpos($last, "'DHC2F001'") !== false && strpos($last, "'DHC2F002'") !== false,
   'the minted NFTs are missing from the final attach');

echo "\nthe clear is still there, because that is the detach\n";
$conn3 = new FhConn();
updateSoldiers($conn3, "DHC2F009");
$first = $conn3->sql[0];
ok(strpos($first, 'SET user_id = 0') !== false,
   'the roster is no longer cleared first, so a sold NFT keeps its soldier forever');
ok(strpos($first, "rank != 'GRUNT'") !== false,
   'the clear stopped sparing GRUNTs, which have no NFT to re-attach them');

echo "\nnames are escaped on the way into the IN ()\n";
/* The old line interpolated raw. Nothing on chain carries a quote, but the
   list now round-trips through an explode() and this is the cheap half. */
$conn4 = new FhConn();
updateSoldiers($conn4, "O'Brien");
$att = end($conn4->sql);
ok(strpos($att, "O\\'Brien") !== false,
   'an asset name with a quote in it goes into the SQL unescaped');

echo "\ndisassembled Fighters are not fielded\n";
/*
 * Taking a Fighter apart returns its traits to be built with again, so the
 * build is gone -- dhcf_fighters() and the Arena both filter on it. Without
 * this, a player disassembles one, spends the traits on a second, and
 * fields both.
 */
$fsrc = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-fighters.php'));
ok(preg_match('/FROM dhc_fighters f.*?disassembled_at IS NULL/s', $fsrc) === 1,
   'the Fighter sync reads disassembled Fighters as if they still existed');
ok(strpos($fsrc, 'f.invalid = 0') !== false,
   'the invalid filter is gone from the Fighter sync');

echo "\nthe sync hands its names to the shared accumulator\n";
/* Via updateSoldiers(), which is the one path that registers them. A sync
   that wrote the rows and never called it would leave them unowned. */
ok(strpos($fsrc, 'updateSoldiers($conn, implode') !== false,
   'the Fighter sync no longer runs an ownership pass at all');

echo "\nFighters are an addition, not a replacement\n";
$dash = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(preg_match('/\$ds_fighters\s*=\s*\(\$ds_source\s*===\s*\x27fighters\x27\)/', $dash) === 1,
   'the fighters flag is gone from dashboard.php');
ok(preg_match('/if\(\$ds_source\s*===\s*\x27skulliance\x27\)/', $dash) === 1,
   "the NFT chain no longer starts at 'skulliance', so 'fighters' may have become exclusive again");
/* The fighters block must not be an arm of the NFT chain -- that is the
   shape that turned the real collection off. */
$at_f = strpos($dash, '$ds_fighters = ');
$at_s = strpos($dash, "if(\$ds_source === 'skulliance')");
ok($at_f !== false && $at_s !== false && $at_f < $at_s,
   'the Fighter pull no longer runs before the NFT chain');

echo "\nthe probe can see Fighters\n";
/* The page said "both are checked below" and checked the two NFT paths
   only, which is why this took a round trip to diagnose. */
$probe = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-nft-probe.php'));
ok(strpos($probe, 'FROM dhc_fighters f') !== false,
   'the probe still cannot tell you whether you have any Fighters');
ok(strpos($probe, 'FIGHTERS: ...synced into soldiers') !== false
   && strpos($probe, 'FIGHTERS: ...and attached to you') !== false,
   'the probe reports Fighters built but not whether they synced and attached');

echo "\n" . ($fail ? "$fail FAILED\n" : "all passed\n");
exit($fail ? 1 : 0);
