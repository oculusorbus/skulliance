<?php
/**
 * dropship-skulliance-db-harness.php — one door to the Skulliance database.
 *
 * Run: php dropship-skulliance-db-harness.php
 *
 * Both credentials files use the same four variable names, and Drop Ship's
 * are what built $conn. Reading Skulliance's anywhere but inside a function
 * overwrites them, and the next thing to build a connection points at the
 * wrong database. Nothing in PHP enforces that; this does.
 */
require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/dropship/dropship-skulliance-db.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

/* Comments stripped everywhere below: dropship-skulliance-db.php quotes the
   old pattern in its own docblock to explain it, and a check that matched
   its own explanation would be worthless. */
$files = array();
foreach (glob(__DIR__ . '/dropship/*.php') as $f) {
	$files[basename($f)] = harness_strip_comments(file_get_contents($f));
}

echo "\nthe Skulliance credentials are read in exactly one place\n";
$readers = array();
foreach ($files as $name => $src) {
	/* '../credentials' is Skulliance's. Drop Ship's own is 'credentials/...'
	   with no parent hop, and db.php is supposed to read that one. */
	if (strpos($src, "'/../credentials/db_credentials.php'") !== false
	    || strpos($src, '"/../credentials/db_credentials.php"') !== false) {
		$readers[] = $name;
	}
}
ok($readers === array('dropship-skulliance-db.php'),
   'Skulliance credentials are read in ' . count($readers) . ' places: ' . implode(', ', $readers));
/* Drop Ship's own stays exactly where it is. */
ok(strpos($files['db.php'], "include 'credentials/db_credentials.php';") !== false,
   "db.php stopped reading Drop Ship's own credentials");

echo "\nand only ever inside a function\n";
/*
 * THE WHOLE POINT. At file scope the include overwrites $servername,
 * $username, $password and $dbname globally -- the same four names that
 * built $conn. Inside a function they are locals and die on return.
 */
$src = $files['dropship-skulliance-db.php'];
$fn_at  = strpos($src, 'function dropshipSkullianceDb()');
$inc_at = strpos($src, 'include $cred;');
ok($fn_at !== false && $inc_at !== false && $inc_at > $fn_at,
   'the credentials include sits outside the function, where it overwrites the globals');

echo "\nnothing else opens a second connection\n";
/* A new mysqli() anywhere else means somebody found the credentials another
   way, which is the same bug wearing a different hat. */
$builders = array();
foreach ($files as $name => $s2) {
	if (preg_match_all('/new mysqli\s*\(/', $s2, $m)) {
		$builders[$name] = count($m[0]);
	}
}
ok(isset($builders['db.php']) && $builders['db.php'] === 1,
   "db.php builds " . ($builders['db.php'] ?? 0) . " connections; it should build only Drop Ship's own");
ok(isset($builders['dropship-skulliance-db.php']) && $builders['dropship-skulliance-db.php'] === 1,
   'the helper does not build the connection it exists to build');
unset($builders['db.php'], $builders['dropship-skulliance-db.php']);
ok(empty($builders), 'other files still build their own connection: ' . implode(', ', array_keys($builders)));

echo "\nevery crossing goes through the helper\n";
/* dropshipSyncFromSkulliance was dropshipSyncOculusLounge. It is the
   generic "read holdings from the already-synced nfts table" pipeline that
   dropship_themes.trait_source selects, so naming it after the one theme
   that first used it was the thing stopping a second theme choosing it. */
foreach (array('dropshipConnectedStakeAddresses', 'dropshipOculusLoungeLocalImages',
               'dropshipSyncFromSkulliance') as $fn) {
	$at = strpos($files['db.php'], 'function ' . $fn);
	ok($at !== false, "$fn is gone");
	if ($at === false) continue;
	$body = substr($files['db.php'], $at, 1800);
	ok(strpos($body, 'dropshipSkullianceDb()') !== false,
	   "$fn opens its own Skulliance connection instead of using the helper");
}
ok(strpos($files['dropship-traitdrop.php'], 'dropshipSkullianceDb()') !== false,
   'the trait award opens its own Skulliance connection');
ok(strpos($files['db.php'], "require_once __DIR__ . '/dropship-skulliance-db.php'") !== false,
   'db.php does not load the helper it calls');

echo "\nit fails quiet, because every caller is an enhancement\n";
/*
 * Driven for real. A checkout has no credentials file, which is the same
 * thing a misconfigured server does, so this exercises the failure branch
 * rather than describing it. A throw here would take down a Drop Ship page
 * over a cross-database lookup that is always optional.
 */
$threw = false;
$out = '';
try {
	ob_start();
	$c = dropshipSkullianceDb();
	$out = ob_get_clean();
} catch (Throwable $e) { $threw = true; if (ob_get_level()) ob_end_clean(); }
ok(!$threw, 'the helper threw instead of returning null when it could not connect');
ok($c === null, 'the helper returned something other than null when it could not connect');
ok($out === '', 'the helper printed into the page: ' . substr($out, 0, 80));
/* And the four names must not have escaped into global scope. */
foreach (array('servername', 'username', 'password', 'dbname') as $leak) {
	ok(!array_key_exists($leak, $GLOBALS),
	   "\$$leak leaked into global scope, which is the bug this file prevents");
}

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
