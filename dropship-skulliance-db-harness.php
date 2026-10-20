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
/* dropshipLocalNftImages was dropshipOculusLoungeLocalImages. It maps a
   collection's asset names to Skulliance's locally cached art, and it had
   the Disco Solaris policy written into it -- so every other theme reading
   its NFTs from Skulliance got soldiers with no pictures. */
foreach (array('dropshipConnectedStakeAddresses', 'dropshipLocalNftImages',
               'dropshipSyncFromSkulliance') as $fn) {
	$at = strpos($files['db.php'], 'function ' . $fn);
	ok($at !== false, "$fn is gone");
	if ($at === false) continue;
	$body = substr($files['db.php'], $at, 1800);
	ok(strpos($body, 'dropshipSkullianceDb()') !== false,
	   "$fn opens its own Skulliance connection instead of using the helper");
}
/*
 * THE COLLECTION COMES FROM THE PROJECT, in every one of these. Each was
 * written for Oculus Lounge and carried the Disco Solaris policy as a
 * literal, so a second theme reading its NFTs out of Skulliance got the
 * wrong collection or none at all. Filthy Mermaid loaded its soldiers and
 * showed no pictures for exactly this reason -- the art was cached on
 * Skulliance the whole time (project 2, collection 9) and nothing looked
 * for it.
 */
ok(strpos($files['db.php'], "function dropshipLocalNftImages(\$asset_names, \$policy = '')") !== false,
   'the image lookup does not take a policy, so it can only serve one collection');
ok(strpos($files['db.php'], "'d0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779'") === false
   || substr_count($files['db.php'], "'d0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779'") === 1,
   'the Disco Solaris policy is still written into more than one place in db.php');
/* ...and it must refuse to guess when it has none, rather than fall back to
   somebody else's collection. */
ok(strpos($files['db.php'], "if (\$policy === '') return \$images;") !== false,
   'the image lookup with no policy falls back to a hardcoded collection');
/* Both render paths ask for the project's own art, and for every theme
   except Drop Ship, whose images are genuinely local to it. */
ok(substr_count($files['db.php'], "dropshipLocalNftImages(array_column(\$rows, 'asset_name'),") === 2,
   'only one of the two render paths looks up local art');
ok(substr_count($files['db.php'], "getProjectPolicyId(\$conn));") === 2,
   'a render path looks up art without the project policy');
ok(strpos($files['db.php'], "if (\$_SESSION['userData']['dropship_project_id'] == 4) {") === false,
   'local art is still gated on project 4, so no other theme can ever show a picture');
/* The bulk pre-cache has to be pointable at a new collection too, or the
   art a new theme needs is never fetched in the first place. */
$cache_src = harness_strip_comments(file_get_contents(__DIR__ . '/cache-oculuslounge-art.php'));
ok(strpos($cache_src, "preg_match('/^[0-9a-f]{56}\$/i', \$a)") !== false,
   'the art pre-cache cannot be pointed at another collection');
ok(strpos($cache_src, '$policy = $conn->real_escape_string($policy_in);') !== false,
   'the pre-cache policy is not the one taken from the command line');

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
