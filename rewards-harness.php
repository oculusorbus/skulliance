<?php
/* rewards-harness.php — CLI only. The payout runner, with no database.
 *
 * rewards.php is what pays every board on the platform. It just went from
 * fifteen independent `if` blocks to one job table plus a combined runner,
 * and the thing that matters is that NOTHING changed about what each flag
 * does -- a refactor of a money path is only safe if it is provably a
 * refactor.
 *
 * It also has to be honest about the hazard consolidation creates: these
 * payouts are not idempotent, so a half-finished combined run must never
 * invite a plain re-run.
 *
 * Usage: php rewards-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$CALLS = array();
function rec() { global $CALLS; $CALLS[] = func_get_args(); }

/* Every board the runner can reach, recorded rather than run. */
function checkSkullSwapsLeaderboard($c,$m,$r)    { rec('swaps',$m,$r); }
function checkBossBattlesLeaderboard($c,$m,$r)   { rec('bosses',$m,$r); }
function checkGauntletsLeaderboard($c,$m,$r)     { rec('gauntlets',$m,$r); }
function checkCryptCrawlLeaderboard($c,$m,$r)    { rec('cryptcrawl',$m,$r); }
function checkSkullRacerLeaderboard($c,$m,$r,$b) { rec('skullracer:'.$b,$m,$r); }
function resetSkullRacerRuns($c)                 { rec('reset:skullracer'); }
function checkObscuraLeaderboard($c,$m,$r)       { rec('obscura',$m,$r); }
function resetObscuraRuns($c)                    { rec('reset:obscura'); }
function checkMissionsLeaderboard($c,$m,$r)      { rec('missions',$m,$r); }
function checkStreaksLeaderboard($c,$m,$r)       { rec('streaks',$m,$r); }
function checkRaidsLeaderboard($c,$m,$r)         { rec('raids',$m,$r); }
function checkFactionsLeaderboard($c,$m,$r)      { rec('factions',$m,$r); }
function checkMonstrocityLeaderboard($c,$m,$r)   { rec('monstrocity',$m,$r); }
function checkGuardiansLeaderboard($c,$m,$r)     { rec('guardians',$m,$r); }
function checkCryptConquestLeaderboard($c,$m,$r) { rec('cryptconquest',$m,$r); }
function checkDHCFightersLeaderboard($c,$m,$r)   { rec('dhcfighters',$m,$r); }
function checkDHCArenaLeaderboard($c,$m,$r)      { rec('dhcarena',$m,$r); }

/* Pull the job table and the runner out of rewards.php, so this tests the
   shipped file rather than a description of it. */
$src = file_get_contents(__DIR__ . '/rewards.php');
$from = strpos($src, '$reward_jobs = array(');
$to   = strpos($src, "/* Individual flags");
eval(substr($src, $from, $to - $from));
ok(isset($reward_jobs) && count($reward_jobs) === 15, 'fifteen jobs defined');

function names($cadence) { global $reward_jobs;
	$o = array(); foreach ($reward_jobs as $n => $d) if ($d[0] === $cadence) $o[] = $n; return $o; }
function fire($name) { global $reward_jobs, $CALLS; $CALLS = array(); $reward_jobs[$name][1](null);
	$o = array(); foreach ($CALLS as $c) $o[] = $c[0]; return $o; }

echo "cadences\n";
$w = names('weekly'); $m = names('monthly');
ok($w === array('swaps','bosses','gauntlets','cryptcrawl','skullracer','obscura'),
   'the six weekly boards, and only those');
ok($m === array('missions','streaks','raids','factions','monstrocity','guardians',
                'cryptconquest','dhcfighters','dhcarena'),
   'the nine monthly boards, and only those');
ok(!array_intersect($w, $m), 'no board is in both cadences');
ok(count($w) + count($m) === 15, 'and none is in neither');

echo "\nevery job pays, none displays\n";
foreach ($reward_jobs as $n => $d) {
	global $CALLS; $CALLS = array(); $d[1](null);
	foreach ($CALLS as $c) {
		if (strpos($c[0], 'reset:') === 0) continue;
		ok(isset($c[2]) && $c[2] === true,  $n . ': called in reward mode');
		ok(isset($c[1]) && $c[1] === false, $n . ': monthly flag false, so the reward window decides the period');
	}
}

echo "\nthe two jobs whose order is load-bearing\n";
ok(fire('skullracer') === array('skullracer:race','skullracer:lap','reset:skullracer'),
   'skull racer: both boards paid BEFORE the reset closes the week');
ok(fire('obscura') === array('obscura','reset:obscura'),
   'obscura: paid before the reset flips reward=1');
ok(fire('guardians') === array('guardians'),
   'guardians resets itself, so the job must not call a second reset');

echo "\nthe combined runner\n";
/* run_reward_jobs() came along with the slice above -- it sits between the
   job table and the individual-flag loop -- so it is already defined. */
ok(function_exists('run_reward_jobs'), 'the runner was read out of rewards.php too');

$CALLS = array();
ob_start(); $r = run_reward_jobs(null, $reward_jobs, 'weekly'); ob_end_clean();
ok($r['done'] === $w, 'weekly runs exactly the weekly boards, in order');
ok(!$r['failed'], 'and nothing fails');

$CALLS = array();
ob_start(); $r = run_reward_jobs(null, $reward_jobs, 'monthly'); ob_end_clean();
ok($r['done'] === $m, 'monthly runs exactly the monthly boards, in order');

echo "\nresuming a half-finished run\n";
ob_start(); $r = run_reward_jobs(null, $reward_jobs, 'monthly', array('missions','raids')); ob_end_clean();
ok($r['skipped'] === array('missions','raids'), 'skip= leaves the named boards alone');
ok(!in_array('missions', $r['done'], true) && !in_array('raids', $r['done'], true),
   'and they really are not paid');
ok(count($r['done']) === 7, 'the other seven still are');

echo "\none broken board does not strand the rest\n";
$boom = $reward_jobs;
$boom['raids'] = array('monthly', function ($c) { throw new RuntimeException('table gone'); });
$CALLS = array();
ob_start(); $r = run_reward_jobs(null, $boom, 'monthly'); $out = ob_get_clean();
ok($r['failed'] === array('raids'), 'the failure is reported');
ok(count($r['done']) === 8, 'and every other board still paid');
ok(in_array('dhcarena', $r['done'], true), 'including the ones AFTER the failure');
ok(strpos($out, 'Do NOT re-run the whole cadence') !== false,
   'the output warns against a blind re-run -- these payouts are not idempotent');
ok(strpos($out, 'php rewards.php raids=true') !== false,
   'and names the exact command that retries only what failed');

echo "\nthe individual flags still work\n";
ok(strpos($src, 'foreach ($reward_jobs as $rj_name => $rj_def)') !== false,
   'single-board flags dispatch through the same table');
foreach (array('missions','obscura','skullracer','dhcarena','dhcfighters','cryptconquest') as $flag)
	ok(isset($reward_jobs[$flag]), 'rewards.php?' . $flag . ' still resolves to a job');
$probe = array(); parse_str('monthly=true', $probe);
ok(isset($probe['monthly']), 'monthly=true on the command line sets the key isset() looks for');

echo "\nthe non-payout endpoints are untouched\n";
ok(strpos($src, "isset(\$_GET['leaderboardsnapshot'])") !== false, 'the snapshot refresh survives');
ok(strpos($src, "isset(\$_GET['rewards'])") !== false, 'the daily balance run survives');
ok(strpos($src, "'rewards' =>") === false, 'and neither is in the weekly or monthly set');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all rewards runner checks passed\n";
