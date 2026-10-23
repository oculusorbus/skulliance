<?php
/**
 * dropship-armory-harness.php — no shop for a game you cannot enter.
 *
 * Run: php dropship-armory-harness.php
 *
 * Filthy Mermaid wants a Shorty Verse NFT; Oculus Lounge wants a Disco
 * Solaris NFT AND VIP. The Armory ignored both and rendered for
 * everybody, so a refused player was shown a shop full of goods for a
 * game they cannot open, priced in a currency they cannot earn.
 *
 * The real function is lifted and RUN against a stub connection, because
 * the failure here is silent in both directions: showing a shop to
 * somebody refused, or hiding it from somebody who paid.
 */
require_once __DIR__ . '/harness-strip.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return true; }
	$fail++; echo "  FAIL  $msg\n"; return false;
}

/* Lifted by source: dropship/db.php connects at file scope. */
$src = file_get_contents(__DIR__ . '/dropship/db.php');
function lift($src, $sig) {
	$at = strpos($src, $sig);
	if ($at === false) return null;
	$d = 0;
	for ($j = strpos($src, '{', $at); $j < strlen($src); $j++) {
		if ($src[$j] === '{') $d++;
		elseif ($src[$j] === '}') { $d--; if (!$d) return substr($src, $at, $j - $at + 1); }
	}
	return null;
}
foreach (array('function dropshipSoldierCount(', 'function dropshipArmoryVisible(') as $sig) {
	$fn = lift($src, $sig);
	if (!ok($fn !== null, rtrim($sig, '(') . '() is gone from dropship/db.php')) exit(1);
	eval($fn);
}

/** Answers one number to the soldier count, which is all it is asked. */
class ArmConn {
	public $n;
	function __construct($n) { $this->n = $n; }
	function query($sql) { return new ArmRes($this->n); }
	function real_escape_string($s) { return addslashes((string)$s); }
}
class ArmRes {
	public $num_rows = 1; private $n;
	function __construct($n) { $this->n = $n; }
	function fetch_assoc() { return array('total' => $this->n); }
}
$_SESSION['userData'] = array('dropship_user_id' => 7, 'dropship_project_id' => 3);

echo "\nthe open themes always have a shop\n";
/* Drop Ship, Dread City and DHC2 Fighters refuse nobody, and Drop Ship
   hands out four free soldiers, so a zero count there is not a refusal. */
foreach (array(1, 2, 5) as $pid) {
	if (!ok(dropshipArmoryVisible(new ArmConn(0), $pid, 'false'),
	        "project $pid hides its Armory, but that theme refuses nobody")) break;
}

echo "\nFilthy Mermaid wants a Shorty Verse NFT\n";
ok(dropshipArmoryVisible(new ArmConn(0), 3, 'false') === false,
   'a player holding nothing is shown the Filthy Mermaid shop');
ok(dropshipArmoryVisible(new ArmConn(1), 3, 'false') === true,
   'one NFT is not enough to see the Filthy Mermaid shop');
/* VIP is irrelevant here -- it is the Lounge's door, not this one. */
ok(dropshipArmoryVisible(new ArmConn(0), 3, 'true') === false,
   'VIP alone opens the Filthy Mermaid shop, which is the wrong gate');

echo "\nOculus Lounge wants the NFT AND the VIP\n";
ok(dropshipArmoryVisible(new ArmConn(0), 4, 'false') === false, 'neither, and the shop still shows');
ok(dropshipArmoryVisible(new ArmConn(5), 4, 'false') === false,
   'holdings without VIP open the Lounge shop; the Lounge needs both');
ok(dropshipArmoryVisible(new ArmConn(0), 4, 'true') === false,
   'VIP without holdings opens the Lounge shop; the Lounge needs both');
ok(dropshipArmoryVisible(new ArmConn(2), 4, 'true') === true,
   'a player with both is still refused the Lounge shop');

echo "\nthe paid door counts as VIP\n";
/*
 * $vip is 'true' for the Discord role AND for a pass bought with
 * DISCOIN -- dropship.php folds the session flag in precisely so
 * somebody who has just paid is not refused until they log out. This
 * function must not re-decide that question.
 */
$dsrc = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
$fnsrc = lift($dsrc, 'function dropshipArmoryVisible(');
ok(strpos($fnsrc, 'roles') === false && strpos($fnsrc, 'DISCOIN') === false,
   'the Armory gate re-derives VIP instead of trusting $vip, so the paid pass can disagree with it');

echo "\nand the page actually uses it\n";
$dash = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash, 'dropshipArmoryVisible($conn') !== false,
   'dashboard.php never calls the gate, so the Armory renders for everybody');
/* Wrapped round the whole column, not just the Inventory tab: the
   Weapons, Armor and Equipment tabs are the shop. */
$g = strpos($dash, 'dropshipArmoryVisible($conn');
$a = strpos($dash, '<a name="armory"');
$h = strpos($dash, 'evaluateText("Armory")');
ok($g !== false && $a !== false && $g < $a,
   'the gate opens after the Armory anchor, so the anchor renders regardless');
ok($g !== false && $h !== false && $g < $h, 'the Armory heading is outside the gate');
$e = strpos($dash, '<?php endif; ?>', $h);
$w = strpos($dash, 'loadItems($conn, "Equipment"');
ok($e !== false && $w !== false && $w < $e,
   'the shop tabs fall outside the gate, so the goods still render');

echo "\n" . ($fail ? "$fail FAILED\n" : "all passed\n");
exit($fail ? 1 : 0);
