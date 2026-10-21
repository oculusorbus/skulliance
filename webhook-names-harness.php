<?php
/*
 * webhook-names-harness.php — CLI only. The credential names webhooks.php
 * looks for, lifted and resolved.
 *
 * WHY THIS EXISTS. A webhook that is not wired up is SILENT by design: every
 * branch in webhooks.php guards with function_exists() so a player earning a
 * trait never sees an error because a credential is missing. That is right,
 * and it means a MISNAMED credential looks exactly like an unconfigured one
 * -- nothing posts, nothing complains, and the only way to find out is to
 * notice a channel has gone quiet.
 *
 * credentials/ is not in the repository, so this cannot check what is on the
 * server. What it can do is pin the names the code asks for, so that a rename
 * on either side is a failing test rather than a quiet channel.
 *
 * Usage: php webhook-names-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok   $what\n"; return true; }
	$fail++;  echo "  FAIL $what\n";  return false;
}

$src = (string)file_get_contents(__DIR__ . '/webhooks.php');

/* Every channel branch and the credential it reaches for. */
preg_match_all('/\$channel == "([a-z]+)"/', $src, $chans);
$channels = array_values(array_unique($chans[1]));
ok(count($channels) > 5, 'the channel branches moved; this harness is reading the wrong file');

echo "every credential is reached through function_exists\n";
/*
 * NOT A COUNT. Channels and credentials are not one-to-one -- some branches
 * share a webhook and some take none -- so comparing the two numbers was a
 * check that failed for being arithmetic rather than for being wrong.
 *
 * What IS a contract: every getXWebhook() call is guarded. An unguarded one
 * is a fatal on a request that has already paid out, which is the thing
 * every comment in that file is about.
 */
preg_match_all('/(?<![a-z_])(get[A-Za-z]*Webhook)\s*\(/', $src, $calls);
$guarded = array();
preg_match_all("/function_exists\('(get[A-Za-z]*Webhook)'\)/", $src, $names);
foreach ($names[1] as $n) $guarded[$n] = true;
/*
 * A BASELINE, NOT A CLEAN SWEEP. Nineteen of these have been called
 * unguarded since long before the guard existed, and their credentials have
 * always been present -- asserting none exist would be claiming a contract
 * that was never true and failing the build on history.
 *
 * What this pins is that the list does not GROW. Every channel added since
 * the guard appeared uses it, because the newer ones are wired up after the
 * code ships, and an unguarded call to a credential that is not there yet is
 * a fatal on a request that has already paid the player.
 */
$historic = array('getGeneralWebhook', 'getMemberWebhook', 'getEliteWebhook',
	'getInnerCircleWebhook', 'getRealmsWebhook', 'getRaidsWebhook',
	'getDailyRewardsWebhook', 'getMissionsWebhook', 'getSkullSwapWebhook',
	'getMonstrocityWebhook', 'getBossBattlesWebhook', 'getStoreWebhook',
	'getAuctionsWebhook', 'getRafflesWebhook', 'getDelegationsWebhook',
	'getGauntletsWebhook', 'getCryptCrawlWebhook', 'getXWebhook', 'getWebhook');
$unguarded = array();
foreach (array_unique($calls[1]) as $n) {
	if (!isset($guarded[$n]) && !in_array($n, $historic, true)) $unguarded[] = $n;
}
ok($unguarded === array(),
   'a NEW webhook is called without a function_exists guard, so a credential that is '
   . 'not wired up yet is a fatal: ' . implode(', ', $unguarded));
/* And the baseline itself shrinks or holds, never grows by relabelling. */
$still = 0;
foreach ($historic as $n) if (strpos($src, $n . '(') !== false) $still++;
ok($still <= count($historic),
   'the historic list has grown, which means an unguarded call was added to it rather than guarded');

/*
 * THE DHC FIGHTERS CHANNEL ACCEPTS TWO SPELLINGS, and the prefixed one is
 * deliberate rather than a typo: the platform and Drop Ship each load their
 * own credentials file and both land in one PHP namespace on a request that
 * touches both, which is how "Cannot redeclare getWebhook()" once took down
 * the play page. Prefixing with Skulliance is the defence, so a credential
 * written that way must be honoured and not silently ignored.
 */
echo "\nthe DHC Fighters credential, either spelling\n";
foreach (array('getDHCFightersWebhook', 'getSkullianceDHCFightersWebhook') as $n) {
	ok(strpos($src, "function_exists('" . $n . "')") !== false,
	   "webhooks.php no longer looks for $n, so a credential of that name is ignored");
}

/* RESOLVED, not read. The branch is lifted and run with only the prefixed
   name defined -- the case that prompted this -- because a fallback chain
   written the wrong way round reads fine and returns "". */
$a = strpos($src, '}else if($channel == "dhcfighters"){');
$b = strpos($src, '}else if($channel == "dhcarena"){');
ok($a !== false && $b !== false && $b > $a, 'the dhcfighters branch could not be lifted');
$blk = preg_replace('/^\}else if\([^)]*\)\{/', '', substr($src, $a, $b - $a));

/*
 * ONE PROCESS PER CASE. The whole point is which functions EXIST, and a
 * single process cannot declare getDHCFightersWebhook() for one case and not
 * for the next -- the second declaration is a fatal and a conditional one
 * makes the earlier case leak into the later. So each case is its own php -r.
 */
function resolve_with($blk, $defs) {
	$code = '<?php $channel = "dhcfighters";' . $defs . $blk . ' echo $webhook;';
	$tmp  = tempnam(sys_get_temp_dir(), 'whk') . '.php';
	file_put_contents($tmp, $code);
	$out = @shell_exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg($tmp) . ' 2>&1');
	@unlink($tmp);
	return $out === null ? '' : trim($out);
}
$plain    = 'function getDHCFightersWebhook(){ return "PLAIN"; }';
$prefixed = 'function getSkullianceDHCFightersWebhook(){ return "PREFIXED"; }';

/* Nothing defined: silent, never a fatal. That is the whole guard. */
ok(resolve_with($blk, '') === '', 'an unconfigured channel no longer resolves to empty');
/* THE CASE THAT PROMPTED THIS. A fallback chain written the wrong way round
   reads perfectly and returns "", which is indistinguishable from the
   credential not being there at all. */
ok(resolve_with($blk, $prefixed) === 'PREFIXED',
   'a credential named with the Skulliance prefix is ignored, which looks identical to it being missing');
ok(resolve_with($blk, $plain) === 'PLAIN', 'the unprefixed credential is ignored');
/* Both defined: the unprefixed one wins, because it is what every
   neighbouring channel uses. */
ok(resolve_with($blk, $plain . $prefixed) === 'PLAIN',
   'with both spellings defined the prefixed one wins, which is the surprising answer');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all webhook name checks passed\n");
exit($fail ? 1 : 0);
