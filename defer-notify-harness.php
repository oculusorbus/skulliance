<?php
/*
 * defer-notify-harness.php — Discord posts do not happen on the player's time.
 *
 * THE BUG THIS PINS: every discordmsg() was a blocking curl (CURLOPT_TIMEOUT
 * 8 on an embed, 20 on an upload) inside the request that triggered it, so
 * the player waited on Discord before the game loop, the modal or the
 * animation could continue. In the Arena the pause itself leaked that
 * something notable had just happened.
 *
 * Nothing here posts anything. skl_discord_send() is never called: the
 * checks below execute the pure decision, and read the split structurally
 * where executing it would mean a webhook.
 */
define('SKL_WEBHOOKS_NO_ALIAS', 1);
require_once __DIR__ . '/webhooks.php';

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$src = (string) file_get_contents(__DIR__ . '/webhooks.php');
ok($src !== '', 'webhooks.php could not be read, so every structural check below passes on nothing');

/* Comment-stripped, because four checks in this project have matched the
   prose explaining a fix rather than the fix. */
$code = '';
foreach (token_get_all($src) as $t) {
	if (is_array($t)) { if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) continue; $code .= $t[1]; }
	else $code .= $t;
}
ok(strlen($code) < strlen($src), 'comment stripping did nothing, so these checks can match explanations');

echo "\nthe decision to defer is executable\n";
/*
 * EVERY COMBINATION, RUN RATHER THAN READ. Three reasons to post
 * immediately and each has to hold on its own: CLI has no browser to
 * release and a cron that exits would post nothing; SKL_DISCORD_SYNC is
 * how a harness asserts a post was made rather than parked; and inside
 * the flush, because alertAdmin() calls back in from a failing send.
 */
foreach (array('cli', 'litespeed', 'fpm-fcgi', 'apache2handler') as $sapi) {
	foreach (array(false, true) as $sync) {
		foreach (array(false, true) as $flushing) {
			$want = ($sapi !== 'cli' && !$sync && !$flushing);
			ok(skl_discord_defers($sapi, $sync, $flushing) === $want,
			   "skl_discord_defers($sapi, " . var_export($sync, true) . ", "
			   . var_export($flushing, true) . ") is not " . var_export($want, true));
		}
	}
}
/* The web default is the whole point: a normal request must defer. */
ok(skl_discord_defers('litespeed', false, false) === true,
   'a plain web request posts inline again, which is the bug');

echo "\nthe player's request carries no curl\n";
/* The split: skl_discordmsg() builds and hands off, skl_discord_send()
   is the only thing that talks to Discord. */
$bodies = array();
$toks = token_get_all($src); $cur = null; $depth = 0;
foreach ($toks as $i => $x) {
	if (is_array($x) && $x[0] === T_FUNCTION) {
		for ($j = $i + 1; $j < count($toks); $j++) {
			if (is_array($toks[$j]) && $toks[$j][0] === T_STRING) { $cur = $toks[$j][1]; break; }
		}
	}
	if ($cur === null) continue;
	/* COMMENTS SKIPPED HERE TOO, not just in $code. The first version of
	   this built the bodies from raw tokens and then searched them, so
	   the check below matched the COMMENT in skl_discord_send() naming
	   $GLOBALS['skl_ops_alert'] and reported the fix as un-applied. That
	   is the fifth time in this project a check has read prose as code. */
	if (!is_array($x) || ($x[0] !== T_COMMENT && $x[0] !== T_DOC_COMMENT)) {
		$bodies[$cur] = (isset($bodies[$cur]) ? $bodies[$cur] : '') . (is_array($x) ? $x[1] : $x);
	}
	if ($x === '{') $depth++;
	if ($x === '}') { $depth--; if ($depth === 0) $cur = null; }
}
foreach (array('skl_discordmsg', 'skl_discord_send', 'skl_discord_dispatch',
               'skl_discord_flush', 'skl_discord_release', 'skl_discord_defers') as $fn) {
	ok(isset($bodies[$fn]) && function_exists($fn), "$fn is gone");
}
ok(strpos($bodies['skl_discord_send'], 'MIRROR TO THE ACTIVITY FEED') === false,
   'the extracted bodies still carry their comments, so every check below can match an explanation');
ok(strpos($bodies['skl_discordmsg'], 'curl_init') === false,
   'skl_discordmsg() posts inline again, so the player waits on Discord');
ok(strpos($bodies['skl_discordmsg'], 'skl_discord_dispatch(') !== false,
   'skl_discordmsg() no longer hands the post off, so nothing is queued');
ok(strpos($bodies['skl_discord_send'], 'curl_init') !== false,
   'skl_discord_send() does not post, so nothing reaches Discord at all');

echo "\nthe job carries everything the send needs\n";
/*
 * THE REAL RISK OF THIS CHANGE. The send used to read fifteen variables
 * out of its enclosing scope; now it reads them off a job array built one
 * request earlier. A field the dispatcher forgets is not a parse error,
 * it is a silently empty embed in production -- so compare the two sets
 * rather than trusting that they match.
 */
preg_match_all("~\\\$J\\['([a-z_0-9]+)'\\]~", $bodies['skl_discord_send'], $m_read);
preg_match('~skl_discord_dispatch\(array\((.*?)\n *\)\);~s', $bodies['skl_discordmsg'], $m_arr);
preg_match_all("~'([a-z_0-9]+)' *=>~", count($m_arr) === 2 ? $m_arr[1] : '', $m_sent);
$read = array_unique($m_read[1]); sort($read);
$sent = array_unique($m_sent[1]); sort($sent);
ok(count($read) > 10, 'the send reads almost nothing off the job, so this comparison is vacuous');
ok($read === $sent, 'the send reads fields the dispatcher does not supply: '
   . implode(', ', array_diff($read, $sent))
   . ' / supplied but unused: ' . implode(', ', array_diff($sent, $read)));

echo "\nrequest-scoped facts are captured before the request ends\n";
/*
 * THE ACTOR. $actor_id null means "work it out from the session", and the
 * session is closed before a queued job runs -- so the lookup must happen
 * at queue time, not at send time.
 */
ok(strpos($bodies['skl_discordmsg'], 'activity_actor()') !== false,
   'the actor is no longer resolved while the request is up');
ok(strpos($bodies['skl_discord_send'], 'activity_actor()') === false,
   'the send resolves the actor itself, against a session that is already closed');
/*
 * THE OPS FLAG. $skl_alerting is a static that resets between calls and
 * $skl_ops_alert is a global long cleared by flush time. Both said "this
 * post is itself an operational alert", and the feed must not carry one:
 * it would put internal failure detail on a page every member reads.
 */
ok(strpos($bodies['skl_discord_send'], "\$GLOBALS['skl_ops_alert']") === false,
   'the send reads skl_ops_alert at flush time, when it is long cleared, so alerts mirror to the public feed');
ok(strpos($bodies['skl_discord_send'], "empty(\$J['ops'])") !== false,
   'the send no longer honours the captured ops flag');
ok(strpos($bodies['skl_discordmsg'], "'ops'") !== false,
   'the dispatcher does not capture the ops flag');

echo "\nthe browser is let go before anything is posted\n";
$rel = $bodies['skl_discord_release'];
ok(strpos($rel, 'litespeed_finish_request') !== false,
   'the release does not use litespeed_finish_request, which is the one that works on this SAPI');
ok(strpos($rel, 'ignore_user_abort(true)') !== false,
   'nothing sets ignore_user_abort, so the work dies with the connection it just closed');
/*
 * ORDER. The session must be closed BEFORE the release, or the player's
 * next request queues on the session lock and the stall has only moved
 * somewhere harder to see.
 */
$p_sess = strpos($rel, 'session_write_close');
$p_rel  = strpos($rel, 'litespeed_finish_request');
ok($p_sess !== false && $p_rel !== false && $p_sess < $p_rel,
   'the session is closed after the release, so the next request still queues behind this one');
/* And the flush releases before it posts, not after. */
$p_r2 = strpos($bodies['skl_discord_flush'], 'skl_discord_release()');
$p_s2 = strpos($bodies['skl_discord_flush'], 'skl_discord_send(');
ok($p_r2 !== false && $p_s2 !== false && $p_r2 < $p_s2,
   'the flush posts before releasing the browser, which is the bug with extra steps');
/* Idempotent: dhcp_answer() and anything like it may have released first. */
/* The EARLY RETURN specifically. Checking the flag name alone passed
   with the guard deleted and only the assignment left behind. */
ok(preg_match('~if \(!empty\(\$GLOBALS\[.skl_discord_released.\]\)\) return;~', $rel) === 1,
   'the release is not guarded, so a second call re-closes a request already closed');
ok(strpos($rel, "\$GLOBALS['skl_discord_released'] = true") !== false,
   'the release never records that it ran, so the guard above can never fire');

echo "\nthe queue cannot strand a job\n";
/* alertAdmin() posts from inside a failing send, so the drain has to see
   a job appended while it is running. A foreach over a snapshot would not. */
ok(strpos($bodies['skl_discord_flush'], 'array_shift') !== false
   && strpos($bodies['skl_discord_flush'], 'while') !== false,
   'the drain iterates a snapshot, so a post raised by a failing post is left in the queue');
ok(strpos($bodies['skl_discord_flush'], 'skl_discord_flushing') !== false,
   'nothing marks the flush as running, so alertAdmin() queues into a drain that has moved past it');
ok(strpos($bodies['skl_discord_flush'], 'catch') !== false,
   'one throwing post abandons every post behind it in the queue');
ok(strpos($bodies['skl_discord_dispatch'], 'register_shutdown_function') !== false,
   'nothing ever drains the queue, so no notification is sent at all');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
