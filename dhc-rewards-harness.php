<?php
/* dhc-rewards-harness.php — CLI only. The DHC monthly payouts, with no
 * database and no Discord.
 *
 * This is a MONEY path: it credits CARBON to real balances and there is no
 * rewarded flag on either table to stop a second run. It runs once a month,
 * unattended, at a moment nobody is watching. So the things worth pinning
 * down are the window and the arithmetic, not the markup.
 *
 * The functions are read out of db.php rather than reimplemented -- a test
 * that restates the split it is checking passes on the day the split
 * changes.
 *
 * Usage: php dhc-rewards-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

function extract_fn($src, $name) {
	$at = strpos($src, "function $name(");
	if ($at === false) return '';
	$open = strpos($src, '{', $at); $depth = 0; $i = $open; $n = strlen($src);
	for (; $i < $n; $i++) {
		if ($src[$i] === '{') $depth++;
		elseif ($src[$i] === '}') { $depth--; if ($depth === 0) { $i++; break; } }
	}
	return substr($src, $at, $i - $at);
}

$PAID = array(); $SQL = array(); $DISCORD = array();

function updateBalance($conn, $u, $p, $amt) { global $PAID; $PAID[] = array('bal', $u, $p, $amt); }
function logCredit($conn, $u, $amt, $p)     { global $PAID; $PAID[] = array('credit', $u, $amt, $p); }
function discordmsg($t, $d = '', $i = '', $u = '') { global $DISCORD; $DISCORD[] = array($t, $d); }
function renderLeaderboardList($rows) { global $ROWS_OUT; $ROWS_OUT = $rows; }
function fireworks() {}

class DRes {
	public $num_rows; private $rows;
	function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	function fetch_assoc() { return array_shift($this->rows); }
}
class DConn {
	public function real_escape_string($s) { return addslashes($s); }
	function query($sql) {
		global $SQL, $PLAYERS;
		$SQL[] = preg_replace('/\s+/', ' ', $sql);
		return new DRes($PLAYERS);
	}
}

$src = file_get_contents(__DIR__ . '/db.php');
eval(extract_fn($src, 'checkDHCFightersLeaderboard'));
ok(function_exists('checkDHCFightersLeaderboard'), 'the real function was read out of db.php');

/* Three players, clearly separated so ranks cannot be ambiguous. */
$PLAYERS = array(
	array('user_id'=>11,'username'=>'ace','discord_id'=>'1','avatar'=>'a','visibility'=>2,
	      'best_score'=>'900','fighters'=>'6','total_score'=>'4000'),
	array('user_id'=>22,'username'=>'bee','discord_id'=>'2','avatar'=>'b','visibility'=>2,
	      'best_score'=>'700','fighters'=>'4','total_score'=>'2000'),
	array('user_id'=>33,'username'=>'cee','discord_id'=>'3','avatar'=>'c','visibility'=>2,
	      'best_score'=>'500','fighters'=>'1','total_score'=>'500'),
);

echo "the window\n";

$SQL = array(); $PAID = array(); $DISCORD = array();
checkDHCFightersLeaderboard(new DConn(), false, false);       // all-time display
$q = $SQL[0];
ok(strpos($q, 'DATE_FORMAT') === false, 'all-time has no date window at all');
ok(!$PAID, 'and pays nothing');

$SQL = array(); $PAID = array();
checkDHCFightersLeaderboard(new DConn(), true, false);        // this month, display
$q = $SQL[0];
ok(strpos($q, "created_at >= DATE_FORMAT(NOW(), '%Y-%m-01')") !== false, 'monthly starts at the 1st of THIS month');
ok(strpos($q, 'INTERVAL 1 MONTH') === false, 'and does not reach back a month');
ok(!$PAID, 'the display board pays nothing');

$SQL = array(); $PAID = array();
checkDHCFightersLeaderboard(new DConn(), false, true);        // the payout
$q = $SQL[0];
ok(strpos($q, "created_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')") !== false,
   'a reward run starts at the 1st of LAST month');
ok(strpos($q, "created_at < DATE_FORMAT(NOW(), '%Y-%m-01')") !== false,
   'and STOPS at the 1st of this one -- both ends, or it pays for this month too');
ok(strpos($q, "newest_trait_at >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')") !== false,
   'the trait date is windowed the same way');
ok(strpos($q, "newest_trait_at < DATE_FORMAT(NOW(), '%Y-%m-01')") !== false, 'at both ends too');
ok(strpos($q, 'disassembled_at IS NULL') !== false, 'disassembled Fighters are excluded, as on the board');

echo "\nthe split\n";

$bal = array_values(array_filter($PAID, function ($p) { return $p[0] === 'bal'; }));
ok(count($bal) === 3, 'every ranked player is paid once');
ok($bal[0][3] == 100000, 'rank 1 takes the whole 100,000 pool');
ok($bal[1][3] == 50000,  'rank 2 takes half');
ok($bal[2][3] == 33333,  'rank 3 takes a third');
ok($bal[0][1] == 11 && $bal[1][1] == 22 && $bal[2][1] == 33, 'paid in rank order, to the right users');
ok($bal[0][2] == 15, 'paid in CARBON (project 15)');

$cred = array_values(array_filter($PAID, function ($p) { return $p[0] === 'credit'; }));
ok(count($cred) === 3, 'and each payment is logged');
foreach ($cred as $i => $c) ok($c[2] == $bal[$i][3], 'the logged amount matches the credited one, rank ' . ($i + 1));

echo "\nties\n";
/* Two players level on both sort keys share a rank -- and therefore share
   the rank's share, rather than one of them silently taking rank 2's. */
$PLAYERS = array(
	array('user_id'=>11,'username'=>'ace','discord_id'=>'1','avatar'=>'a','visibility'=>2,
	      'best_score'=>'900','fighters'=>'6','total_score'=>'4000'),
	array('user_id'=>22,'username'=>'bee','discord_id'=>'2','avatar'=>'b','visibility'=>2,
	      'best_score'=>'900','fighters'=>'6','total_score'=>'4000'),
);
$PAID = array(); $DISCORD = array();
checkDHCFightersLeaderboard(new DConn(), false, true);
$bal = array_values(array_filter($PAID, function ($p) { return $p[0] === 'bal'; }));
ok(count($bal) === 2, 'both tied players are paid');
ok($bal[0][3] == $bal[1][3], 'and paid the same');
ok($bal[0][3] == 100000, 'at the higher rank, not the lower');

echo "\nthe announcement\n";
ok(count($DISCORD) === 1, 'exactly one Discord post per run');
ok(strpos($DISCORD[0][0], 'DHC Fighters Results') !== false, 'titled as a results post');
ok(strpos($DISCORD[0][0], date('F', strtotime('first day of last month'))) !== false,
   'and names the month that just closed, not this one');

$PAID = array(); $DISCORD = array();
checkDHCFightersLeaderboard(new DConn(), true, false);
ok(!$DISCORD, 'the display board announces nothing');

echo "\nnobody played\n";
$PLAYERS = array(); $PAID = array(); $DISCORD = array();
ob_start(); checkDHCFightersLeaderboard(new DConn(), false, true); ob_end_clean();
ok(!$PAID, 'an empty month pays nobody');
ok(!$DISCORD, 'and posts nothing');

echo "\nthe endpoint\n";
$rw = file_get_contents(__DIR__ . '/rewards.php');
ok(strpos($rw, "isset(\$_GET['dhcfighters'])") !== false, 'rewards.php answers ?dhcfighters');
ok(strpos($rw, 'checkDHCFightersLeaderboard($conn, false, true)') !== false, 'and calls it in reward mode');
ok(strpos($rw, "isset(\$_GET['dhcarena'])") !== false, 'the arena entry is still there');
/* The cron passes dhcfighters=true on the command line; rewards.php turns
   argv into $_GET with parse_str, and isset() is what it checks. */
$probe = array(); parse_str('dhcfighters=true', $probe);
ok(isset($probe['dhcfighters']), 'dhcfighters=true on the command line sets the key isset() looks for');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all DHC reward checks passed\n";
