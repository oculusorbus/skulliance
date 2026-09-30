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
/* The ceiling comes out of db.php rather than being repeated here, or the
   harness would keep passing after somebody moved it. */
preg_match("/define\('REALM_UPGRADE_CEILING',\s*(\d+)\)/", $db, $cm);
ok(!empty($cm[1]), 'REALM_UPGRADE_CEILING is not defined in db.php');
define('REALM_UPGRADE_CEILING', (int)$cm[1]);
printf("ceiling: %d\n\n", REALM_UPGRADE_CEILING);

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

/* The ladder up to the ceiling. */
foreach (array(0 => 1, 1 => 2, 8 => 9, 9 => 10) as $lvl => $want) {
	$GLOBALS['LEVEL'] = $lvl;
	$d = realmUpgradeQuote($conn, 4);
	ok($d['ok'], "level $lvl should be upgradeable: " . $d['why']);
	ok($d['duration'] === $want,
	   "level $lvl should quote Lv$want, quoted Lv" . $d['duration']);
	ok($d['cost'] === $want * 100, "level $lvl priced at " . $d['cost']);
}

/*
 * AT AND ABOVE THE CEILING, NOTHING IS SOLD.
 *
 * The page used to offer "Maintain Lv10" here. At exactly 10 that cost 1,000
 * currency and ten days of lockout to change nothing. Above 10 -- where
 * raids push a location and where a power player deliberately sits -- the
 * completion SET the level back to 10. There is no state up here where
 * buying an upgrade helps, so the server refuses to price one.
 */
echo "\n  at the ceiling\n";
foreach (array(10, 14, 21, 31) as $lvl) {
	$GLOBALS['LEVEL'] = $lvl;
	$d = realmUpgradeQuote($conn, 4);
	printf("    level %-3d -> %s\n", $lvl, $d['ok'] ? 'SOLD (wrong)' : $d['why']);
	ok(!$d['ok'], "level $lvl was offered an upgrade that can only cost the player");
	ok(!empty($d['at_ceiling']), "level $lvl is not flagged as at the ceiling, so the "
	                           . 'panel cannot explain why there is no button');
	ok($d['cost'] === 0, "level $lvl was still given a price of " . $d['cost']);
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
/* Who is allowed to decide a price: the two endpoints that spend, and the
   panel's data layer. realms.php and ajax/get-locations.php used to ask
   directly; they include realms-lib.php now, which is the only renderer that
   does. */
foreach (array('ajax/upgrade-realm-location.php', 'ajax/points-option.php',
               'realms-lib.php') as $f) {
	$src = file_get_contents(__DIR__ . '/' . $f);
	ok(strpos($src, 'realmUpgradeQuote(') !== false, "$f no longer asks for a server quote");
}
/* And nobody at all works the price out for themselves. Each of these once
   carried its own copy of the three-line ladder. */
foreach (array('ajax/upgrade-realm-location.php', 'ajax/points-option.php',
               'ajax/get-locations.php', 'realms.php', 'realms-lib.php',
               'realms-locations.php') as $f) {
	$src = file_get_contents(__DIR__ . '/' . $f);
	ok(!preg_match('/\$cost\s*=\s*\$duration\s*\*\s*100/', $src),
	   "$f prices the upgrade itself again instead of asking realmUpgradeQuote()");
	ok(!preg_match('/\$levels\[\$location_id\]\s*>\s*10/', $src),
	   "$f has its own copy of the level ladder again");
}
/* The renderers must go through the partial, not rebuild the panel. */
foreach (array('realms.php', 'ajax/get-locations.php') as $f) {
	$src = file_get_contents(__DIR__ . '/' . $f);
	ok(strpos($src, "realms-locations.php") !== false,
	   "$f no longer includes the shared panel partial -- the two copies are back");
}
$ep = file_get_contents(__DIR__ . '/ajax/upgrade-realm-location.php');
foreach (array('cost', 'duration', 'realm_id') as $p) {
	ok(strpos($ep, "\$_GET['$p']") === false,
	   "the upgrade endpoint reads \$_GET['$p'] again -- that is the hole");
}

/* ---------- 5. completing an upgrade never lowers a level -------------------- */
/*
 * upgradeRealmLocationLevel() had two clauses that cancelled out. The first
 * -- "sync upgrade to current level to avoid penalizing owner" -- protected a
 * location above the ceiling; the second -- "safety precaution in case
 * someone manages to level up a location past 10" -- immediately capped it
 * back and undid the protection. A level 21 Portal finishing a Maintain came
 * out at 10, and the Realms leaderboard ranks on SUM(level).
 *
 * The ceiling rule above means nothing at 10+ can be BOUGHT any more, but
 * this is the write itself and other paths reach it, so it is pinned here.
 */
echo "\nan upgrade can never lower a level\n";
$lvlBody = extract_fn($db, 'upgradeRealmLocationLevel');
ok($lvlBody !== '', 'upgradeRealmLocationLevel() not found');
$lvlBody = str_replace('getRealmLocationLevel($conn, $realm_id, $location_id)',
                       '$GLOBALS["LVL"]', $lvlBody);
$lvlBody = preg_replace('/\$sql = "UPDATE.*?\n/s', '$GLOBALS["WROTE"] = $duration;' . "\n",
                        $lvlBody, 1);
$lvlBody = preg_replace('/if \(\$conn->query\(\$sql\).*?\}\s*\}/s', '}', $lvlBody);
eval($lvlBody);

foreach (array(array(3, 4, 4), array(9, 10, 10), array(10, 10, 10),
               array(14, 10, 14), array(21, 10, 21), array(31, 10, 31),
               array(21, 21, 21), array(5, 99, 10)) as $c) {
	list($have, $bought, $want) = $c;
	$GLOBALS['LVL'] = $have; $GLOBALS['WROTE'] = null;
	upgradeRealmLocationLevel(null, 1, 1, $bought);
	printf("  at %-3d completing a Lv%-3d upgrade -> %s\n", $have, $bought, $GLOBALS['WROTE']);
	ok($GLOBALS['WROTE'] === $want,
	   "level $have finishing a Lv$bought upgrade should end at $want, ended at "
	 . var_export($GLOBALS['WROTE'], true));
	ok($GLOBALS['WROTE'] >= $have,
	   "level $have was LOWERED to " . $GLOBALS['WROTE'] . ' by completing an upgrade');
}
ok(REALM_UPGRADE_CEILING === 10, 'the ceiling moved; the expectations above assume 10');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all realm upgrade checks passed\n");
exit($fail ? 1 : 0);
