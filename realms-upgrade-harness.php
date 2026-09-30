<?php
/* realms-upgrade-harness.php — CLI only. No database.
 *
 * WHO DECIDES WHAT AN UPGRADE COSTS.
 *
 * ajax/upgrade-realm-location.php used to take realm_id, duration, cost AND
 * project_id off the query string and spend them. Its own comment said "need
 * to double check duration and cost in case someone tries to manually
 * override these variables in the JS function"; no such check was ever
 * written. That meant:
 *
 *   cost=0        a level 10 upgrade for nothing.
 *   cost=-N       upgradeRealmLocation() spends -$cost and updateBalance()
 *                 has no floor, so a negative cost CREDITED the account.
 *   duration=10   straight to the top level at level 1 prices.
 *   realm_id=X    somebody else's realm.
 *   project_id=Y  any currency, at any price.
 *
 * realmUpgradeQuote() is the fix: the client chooses a location and a
 * currency, the server decides everything else. This drives the real
 * function against a stub connection and tries the attacks.
 *
 * Usage: php realms-upgrade-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

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

$db   = file_get_contents(__DIR__ . '/db.php');
$body = extract_fn($db, 'realmUpgradeQuote');
if ($body === '') { echo "  FAIL  realmUpgradeQuote() not found in db.php\n"; exit(1); }
ok(strpos($body, 'getRealmID($conn)') !== false,
   'the quote takes the realm from somewhere other than the session');
eval($body);

/* The three helpers the quote leans on, stubbed to a known realm. */
$GLOBALS['points_multiplier'] = 2;
$GLOBALS['LEVEL'] = 3;
$GLOBALS['BAL']   = array(4 => 5000, 9 => 5000);   // location 4's own currency, and a partner
function getRealmID($conn) { return 77; }
function getRealmLocationLevel($conn, $realm_id, $location_id) { return $GLOBALS['LEVEL']; }
function checkRealmLocationUpgrade($conn, $realm_id, $location_id) { return !empty($GLOBALS['BUSY']); }
function getBalance($conn, $project_id) {
	return isset($GLOBALS['BAL'][$project_id]) ? $GLOBALS['BAL'][$project_id] : 0;
}
class RuRes { public $rows; public $num_rows;
	function __construct($r){ $this->rows = $r; $this->num_rows = count($r); }
	function fetch_assoc(){ return $this->rows ? array_shift($this->rows) : null; } }
class RuConn {
	function query($sql) {
		if (strpos($sql, 'FROM locations') !== false) {
			/* Locations 1..7 exist in realm 77 and nothing else does. */
			preg_match('/l\.id = (\d+)/', $sql, $m);
			$id = isset($m[1]) ? (int)$m[1] : 0;
			return new RuRes(($id >= 1 && $id <= 7) ? array(array('id' => $id)) : array());
		}
		if (strpos($sql, 'SELECT currency FROM projects') !== false)
			return new RuRes(array(array('currency' => 'DREAD')));
		if (strpos($sql, 'SELECT id FROM projects') !== false) {
			preg_match('/id = (\d+)/', $sql, $m);
			$id = isset($m[1]) ? (int)$m[1] : 0;
			return new RuRes(($id >= 1 && $id <= 20) ? array(array('id' => $id)) : array());
		}
		return new RuRes(array());
	}
}
$_SESSION = array('userData' => array('user_id' => 42));
$conn = new RuConn();

/* ---------- 1. the honest quote --------------------------------------------- */
echo "the quote\n";
$q = realmUpgradeQuote($conn, 4);
printf("  level %d -> Lv%d, %s %s, realm %d\n",
	$q['level'], $q['duration'], number_format($q['cost']), $q['currency'], $q['realm_id']);
ok($q['ok'], 'a plain affordable upgrade was refused: ' . $q['why']);
ok($q['duration'] === 4, 'level 3 should quote the next level up, quoted ' . $q['duration']);
ok($q['cost'] === 400, 'the price is duration x 100, quoted ' . $q['cost']);
ok($q['realm_id'] === 77, 'the quote did not use the session realm');
ok($q['project_id'] === 4, "the default currency should be the location's own");

/* The ladder, including the Maintain case past 10. */
foreach (array(0 => 1, 1 => 2, 9 => 10, 10 => 10, 21 => 10) as $lvl => $want) {
	$GLOBALS['LEVEL'] = $lvl;
	$d = realmUpgradeQuote($conn, 4);
	ok($d['duration'] === $want,
	   "level $lvl should quote Lv$want, quoted Lv" . $d['duration']);
	ok($d['cost'] === $want * 100, "level $lvl priced at " . $d['cost']);
}
$GLOBALS['LEVEL'] = 3;

/* Paying with another balance costs the multiplier. */
$p = realmUpgradeQuote($conn, 4, 9);
printf("  partner points: %s (x%d)\n", number_format($p['cost']), $p['multiplier']);
ok($p['ok'] && $p['cost'] === 800 && $p['multiplier'] === 2,
   'paying in another balance should cost twice, quoted ' . $p['cost']);

/* ---------- 2. the attacks -------------------------------------------------- */
/*
 * THE POINT OF THE WHOLE FILE. The quote takes a location and a currency and
 * nothing else, so none of these can even be expressed as arguments any more
 * -- which IS the fix. What is checked is that the numbers it returns are
 * never the attacker's.
 */
echo "\nwhat a crafted request can no longer do\n";
$attacks = array(
	'free upgrade (cost=0)'          => array(4, 0),
	'minting (cost=-1000000)'        => array(4, 0),
	'straight to Lv10 at Lv1 prices' => array(4, 0),
);
$a = realmUpgradeQuote($conn, 4);
ok($a['cost'] > 0, 'the quote can return a zero cost');
ok($a['cost'] === $a['duration'] * 100, 'the quote can return a cost unrelated to the level');
ok($a['duration'] <= 10 && $a['duration'] >= 1, 'the quote can return a duration off the ladder');
echo "  cost and duration are derived, never accepted — the parameters are gone\n";

/* A location that is not in the player's realm. */
$x = realmUpgradeQuote($conn, 99);
ok(!$x['ok'] && $x['cost'] === 0,
   'a location outside the realm was priced: ' . $x['why']);
/* Project 15 is excluded from spendable balances everywhere else. */
$p15 = realmUpgradeQuote($conn, 4, 15);
ok(!$p15['ok'], 'project 15 was accepted as a payment balance');
/* A project that does not exist. */
$pz = realmUpgradeQuote($conn, 4, 9999);
ok(!$pz['ok'], 'a non-existent project was accepted as a payment balance');

/* ---------- 3. affordability and the lock ------------------------------------ */
echo "\naffordability and the lock\n";
$GLOBALS['BAL'] = array(4 => 399);
$poor = realmUpgradeQuote($conn, 4);
printf("  balance 399 vs cost 400 -> %s\n", $poor['why']);
ok(!$poor['ok'], 'an unaffordable upgrade was approved -- updateBalance() has no floor, '
               . 'so this would simply drive the balance negative');
ok($poor['cost'] === 400 && $poor['balance'] === 399,
   'the refusal should still carry the figures, so the page can say what is short');
$GLOBALS['BAL'] = array(4 => 5000, 9 => 5000);

$GLOBALS['BUSY'] = true;
$busy = realmUpgradeQuote($conn, 4);
ok(!$busy['ok'], 'a second upgrade was approved while one is already running');
printf("  already upgrading -> %s\n", $busy['why']);
$GLOBALS['BUSY'] = false;

$_SESSION = array();
$out = realmUpgradeQuote($conn, 4);
ok(!$out['ok'] && $out['cost'] === 0, 'a signed-out request was priced');
$_SESSION = array('userData' => array('user_id' => 42));

/* ---------- 4. nobody prices it themselves any more --------------------------- */
/*
 * STRUCTURAL. The endpoint and both renderers each used to carry their own
 * copy of the three-line ladder, and the endpoint's copy was decorative --
 * it spent the query string instead. A copy coming back is the regression.
 */
echo "\none pricer\n";
foreach (array('ajax/upgrade-realm-location.php', 'ajax/points-option.php',
               'ajax/get-locations.php', 'realms.php') as $f) {
	$src = file_get_contents(__DIR__ . '/' . $f);
	ok(strpos($src, 'realmUpgradeQuote(') !== false, "$f no longer asks for a server quote");
	ok(!preg_match('/\$cost\s*=\s*\$duration\s*\*\s*100/', $src),
	   "$f prices the upgrade itself again instead of asking realmUpgradeQuote()");
}
$ep = file_get_contents(__DIR__ . '/ajax/upgrade-realm-location.php');
foreach (array('cost', 'duration', 'realm_id') as $p) {
	ok(strpos($ep, "\$_GET['$p']") === false,
	   "the upgrade endpoint reads \$_GET['$p'] again -- that is the hole");
}

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all realm upgrade checks passed\n");
exit($fail ? 1 : 0);
