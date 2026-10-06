<?php
/* verify-entrypoint-harness.php — CLI only. No database, no network.
 *
 * verify.php's job blocks were gated on isset($_GET['verify']) and
 * nothing else: no auth, no CLI check, ending in platform-wide payouts.
 * verify.php is included by ten other pages, so `store.php?verify=1` ran
 * the whole nightly job for anyone who typed it.
 *
 * Two gates now, and the split matters. The ENTRY-POINT check ships
 * enabled because it cannot break a cron that already points at
 * verify.php. The TOKEN is opt-in because turning it on requires editing
 * the crontab, and a half-applied change there means nobody gets paid.
 *
 * Usage: php verify-entrypoint-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$src = file_get_contents(__DIR__ . '/verify.php');

echo "every job block is behind both gates\n";

/* Four blocks: solana, polygon, xrpl, and the main nightly pass. Counted
   rather than spot-checked, because the one that gets forgotten when a
   fifth chain is added is the one that matters. */
$gated   = substr_count($src, '$verify_entry && verify_job_allowed() && isset($_GET[\'verify\'])');
$ungated = preg_match_all('/(?<!&& )isset\(\$_GET\[\'verify\'\]\)\s*(?:&&|\))/', $src);
ok($gated === 4, "only $gated of the 4 job blocks are gated");
ok($ungated === 0, "$ungated block(s) still test \$_GET['verify'] without the entry-point gate");

/* The payout block is the one that must never be reachable sideways. */
$main = strpos($src, "if(\$verify_entry && verify_job_allowed() && isset(\$_GET['verify'])){\n\tset_time_limit(0);");
ok($main !== false, 'the main nightly block (the one that pays out) is not gated');

echo "\nthe entry-point check\n";

/* The real expression, lifted rather than restated. */
preg_match('/\$verify_self  = .*?\$verify_entry = [^;]+;/s', $src, $m);
ok(!empty($m[0]), 'the entry-point expression is gone from verify.php');
$expr = $m[0];

function entry_for($script, $self, $expr) {
	$_SERVER['SCRIPT_FILENAME'] = $script;
	/* __FILE__ inside the lifted code would be THIS file, so the real
	   file's path is injected as $self and the lifted code rewritten to
	   use it -- the logic under test is the comparison, not __FILE__. */
	$code = str_replace('@realpath(__FILE__)', '@realpath($self)', $expr);
	$verify_self = $verify_asked = $verify_entry = null;
	eval($code);
	return $verify_entry;
}
$VERIFY = __DIR__ . '/verify.php';
ok(entry_for($VERIFY, $VERIFY, $expr) === true,
   'running verify.php directly is refused -- the cron and every CLI pass would stop');
ok(entry_for(__DIR__ . '/store.php', $VERIFY, $expr) === false,
   'store.php?verify=1 still runs the nightly job, payouts included');
ok(entry_for(__DIR__ . '/realms.php', $VERIFY, $expr) === false, 'realms.php?verify=1 still runs it');

/* THE false === false TRAP. realpath() returns false for anything that is
   not a real file, and comparing the raw results hands a pass to any
   context where SCRIPT_FILENAME is not one. */
ok(entry_for('', $VERIFY, $expr) === false,
   'an empty SCRIPT_FILENAME passes the gate -- realpath(false) === realpath(false)');
ok(entry_for('/no/such/file.php', $VERIFY, $expr) === false,
   'a SCRIPT_FILENAME that does not exist passes the gate');
/* THE ACTUAL TRAP, which needs BOTH sides to fail to resolve. That is
   `php -r`, where __FILE__ is the string "Command line code" and
   SCRIPT_FILENAME is empty -- realpath() returns false for both, and
   false === false is true. Substituting a real path for __FILE__, as the
   cases above do, makes it unreproducible: the first version of this
   harness did exactly that and scored the mutation as caught when it was
   not. */
ok(entry_for('', '/not/a/real/file.php', $expr) === false,
   'with NEITHER path resolving the gate passes -- realpath(false) === realpath(false)');
ok(entry_for('/no/such/thing.php', '/not/a/real/file.php', $expr) === false,
   'two different non-existent paths compare equal');

/* A symlinked or relatively-expressed path must still match: /tmp is a
   symlink to /private/tmp on this machine and a string compare fails. */
ok(entry_for(__DIR__ . '/./verify.php', $VERIFY, $expr) === true,
   'a path with a ./ in it no longer resolves to the same file');

echo "\nthe token, which is off until it is configured\n";

preg_match('/function verify_job_allowed\(\) \{.*?\n\}/s', $src, $m2);
ok(!empty($m2[0]), 'verify_job_allowed() is gone');
eval($m2[0]);

/* Undefined: behaviour is exactly as before, so this ships without
   touching a running cron. That is the point, not an oversight. */
ok(verify_job_allowed() === true, 'with no token configured the job is refused -- that breaks the cron');

if (!defined('VERIFY_JOB_TOKEN')) define('VERIFY_JOB_TOKEN', 'sekrit-value');
/* CLI is past any gate this could impose -- a shell on the box already
   has verify.php and a php binary. */
ok(verify_job_allowed() === true, 'CLI is refused once a token exists');

/* Constant time. A timing difference cannot be measured reliably from a
   test, so this asserts the CALL -- the thing that would be lost in a
   refactor is hash_equals(), not the behaviour it protects. A token that
   triggers platform-wide payouts is not one to leave a byte-at-a-time
   oracle on. */
ok(strpos($src, 'hash_equals(') !== false,
   'the job token is compared with === rather than hash_equals()');
ok(preg_match('/return\s*\(string\)VERIFY_JOB_TOKEN\s*===/', $src) === 0,
   'the job token is compared with a plain === somewhere');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "verify entry point: ok\n";
