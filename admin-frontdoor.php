<?php
/**
 * admin-frontdoor.php — why somebody cannot get in.
 *
 * WHAT THIS IS FOR. "The application didn't respond in time", pressed on the
 * rules button, is reported by members who are sitting outside the server and
 * cannot be asked to run anything. It has two causes with nothing in common:
 *
 *   THE CLICK NEVER REACHED US. A button belongs to the application that
 *     posted it. An old rules message from BotGhost still in the channel
 *     carries BotGhost's buttons, and BotGhost is the thing that kept dying.
 *     Pressing it times out forever and nothing arrives here to find. The
 *     same symptom, from the same silence, if the Interactions Endpoint URL
 *     is not saved in the Developer Portal.
 *
 *   THE CLICK REACHED US AND WE WERE LATE. Host queueing, a cold worker, or
 *     a PHP startup warning printed in front of the JSON -- which is what it
 *     was on 2026-10-30, and which fires per WORKER, so one click in several
 *     fails and the rest look perfect.
 *
 * THE DIAGNOSIS THAT WAS MISSING IS "DID THE CLICK ARRIVE". Until now the
 * endpoint logged only when it was slow, so a quiet log meant either a
 * healthy gate or a gate Discord was never calling, and there was no way to
 * tell. It now writes a line per interaction, and that log is the third
 * panel here: ask the member to press the button, reload this page. A line
 * means the problem is ours. No line means the click went somewhere else.
 *
 * WHY A PAGE AND NOT A SCRIPT. discord-interactions-doctor.php already
 * checks the credentials and the crypto, and it is CLI only -- which is no
 * use at one in the morning on a phone, in the conversation where somebody
 * is telling you they cannot get in. Everything here is readable from a
 * browser, and the ini values are read over HTTP, which is the only reading
 * that counts: the shell's php is a different build from the web's.
 *
 * READ ONLY. It posts nothing, edits nothing, deletes nothing. A stale gate
 * message is removed in Discord in one tap, and a diagnostic that deletes
 * things is a diagnostic nobody dares run.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/discord-rules-lib.php';         // $bot_token, $rules_channel_id, SK_BTN_START
require_once __DIR__ . '/lib/frontdoor-lib.php';
require_once __DIR__ . '/lib/discord-interaction-log.php';

$fd_ini = frontdoor_ini();

/*
 * THE SELF-PROBE IS BEHIND A BUTTON, not run on load.
 *
 * It fetches this host from this host three times, and a page that does that
 * every time it is opened is a page that can be made to hammer the server by
 * reloading it. It is also the only check here that costs anything.
 */
$fd_probe = array();
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['fd_probe'])) {
	/* Built from what the request says about itself, so it probes the host
	   somebody is actually reading this on rather than a hardcoded one --
	   skulliance.io and www.skulliance.io are different origins here. */
	$fd_host = isset($_SERVER['HTTP_HOST']) ? preg_replace('/[^A-Za-z0-9.:-]/', '', $_SERVER['HTTP_HOST']) : '';
	$fd_url  = 'https://' . $fd_host . rtrim(dirname($_SERVER['PHP_SELF']), '/')
	         . '/discord-interactions.php';
	$fd_probe = frontdoor_probe($fd_url, 3);
}

/* Who Discord thinks we are, and what is sitting in the rules channel. Two
   calls, both cheap; the channel read is one page of fifty. */
$fd_me   = frontdoor_me($bot_token);
$fd_msgs = frontdoor_gate_messages($bot_token, isset($rules_channel_id) ? $rules_channel_id : '');
$fd_live = 0;
foreach ($fd_msgs['messages'] as $m) {
	list($st,) = frontdoor_classify($m, $fd_me['id'], SK_BTN_START);
	if ($st === 'live') $fd_live++;
}

$fd_log = sk_ilog_read(40);
/* An id that appears twice is ONE interaction Discord re-sent, not two
   clicks -- and that is the difference between "we were slow" and "our
   reply was refused". See frontdoor_retry_counts(). */
$fd_retry = frontdoor_retry_counts($fd_log);

/*
 * THE LAYER BELOW PHP. $fd_log can only hold requests that reached PHP; the
 * web server's own log holds the ones it refused first, and those are
 * invisible from every other angle -- including the self-probe, which comes
 * from this host and is not the request Discord makes.
 */
$fd_host = isset($_SERVER['HTTP_HOST'])
	? preg_replace('/[^A-Za-z0-9.-]/', '', explode(':', $_SERVER['HTTP_HOST'])[0]) : '';
$fd_acc  = frontdoor_access_hits(frontdoor_access_paths($fd_host), 'discord-interactions.php');
/* Discord's own calls, which is the comparison that matters: the server saw
   this many, PHP recorded that many, and the difference never ran. */
$fd_acc_discord = 0;
foreach ($fd_acc['hits'] as $h) if (frontdoor_is_discord($h['ua'])) $fd_acc_discord++;

include 'header.php';
admin_chrome('frontdoor');
?>
<?php include 'admin-css.php'; ?>
<?php include 'admin-copy.php';   /* click an id to copy it */ ?>
<style>
.fd-why { font-size:.82rem; color:#8fa8b8; margin:0 0 16px; max-width:none; }
.fd-sect { font-size:.72rem; letter-spacing:.1em; text-transform:uppercase; color:#5f7d90;
  margin:26px 0 10px; border-bottom:1px solid rgba(255,255,255,.1); padding-bottom:6px; }
.fd-sect:first-of-type { margin-top:6px; }
/* One verdict shape for all three panels: a state word, then the sentence
   that says what to do about it. Colour carries the state, the words carry
   the instruction -- neither on its own is enough on a phone at 1am. */
.fd-row { display:flex; gap:10px; align-items:baseline; flex-wrap:wrap; padding:9px 0;
  border-bottom:1px solid rgba(255,255,255,.06); font-size:.82rem; }
.fd-k { flex:0 0 150px; color:#5f7d90; font-size:.72rem; letter-spacing:.08em;
  text-transform:uppercase; }
.fd-v { color:#e8eaed; font-variant-numeric:tabular-nums; }
.fd-say { color:#8fa8b8; flex:1 1 260px; min-width:0; }
.fd-good { color:#00c8a0; } .fd-bad { color:#ff6b6b; } .fd-warn { color:#f5a623; }
.fd-tag { font-size:.66rem; letter-spacing:.09em; text-transform:uppercase;
  border:1px solid currentColor; border-radius:3px; padding:2px 7px; white-space:nowrap; }
.fd-btn { background:#123049; color:#9fd8c8; border:1px solid rgba(255,255,255,.14);
  border-radius:6px; padding:6px 14px; font-size:.8rem; cursor:pointer; }
.fd-btn:hover { background:#17405f; }
.fd-t { width:100%; border-collapse:collapse; font-size:.8rem; }
.fd-t th { text-align:left; font-size:.68rem; letter-spacing:.08em; text-transform:uppercase;
  color:#5f7d90; border-bottom:1px solid rgba(255,255,255,.12); padding:0 9px 7px; font-weight:600; }
.fd-t td { padding:8px 9px; border-bottom:1px solid rgba(255,255,255,.06); vertical-align:top;
  font-variant-numeric:tabular-nums; }
.fd-t td.fd-wrap { font-variant-numeric:normal; word-break:break-word; }
.fd-mono { font-family:ui-monospace,Menlo,monospace; font-size:.76rem; color:#9fc0d0; }
.fd-none { color:#5f7d90; font-style:italic; font-size:.82rem; }
/* The table scrolls rather than the page: four of these columns are numbers
   nobody needs to read sideways. */
.fd-scroll { overflow-x:auto; }
@media (max-width:620px) { .fd-k { flex-basis:100%; } }
</style>

<p class="fd-why">Two different things make a member see <b>"the application didn't respond
in time"</b>: the click never reached this server, or it reached it late. The panels below
answer which, in that order. The last one is the decisive test &mdash; ask them to press the
button, then reload this page. A line means it is ours to fix. No line means the click went
somewhere else.</p>

<?php /* ---------------- 1. the rules channel ---------------- */ ?>
<p class="fd-sect">The rules channel &mdash; who owns the buttons</p>
<?php if ($fd_msgs['error']): ?>
	<p class="fd-row"><span class="fd-tag fd-bad">cannot read</span>
		<span class="fd-say"><?php echo htmlspecialchars($fd_msgs['error'], ENT_QUOTES); ?></span></p>
<?php elseif (!$fd_msgs['messages']): ?>
	<p class="fd-none">No message in that channel carries a button at all, which means there is
	nothing for a newcomer to press. Run <span class="fd-mono">discord-rules-post.php</span>.</p>
<?php else: ?>
	<?php if (!$fd_me['ok']): ?>
	<p class="fd-row"><span class="fd-tag fd-warn">unverified</span>
		<span class="fd-say">Discord would not say which bot we are
		(<?php echo htmlspecialchars($fd_me['error'], ENT_QUOTES); ?>), so ownership below is
		a guess rather than a fact.</span></p>
	<?php endif; ?>
	<div class="fd-scroll"><table class="fd-t">
	<thead><tr><th>Posted</th><th>By</th><th>Button</th><th>Message id</th><th>Verdict</th></tr></thead>
	<tbody>
	<?php foreach ($fd_msgs['messages'] as $m):
		list($state, $say) = frontdoor_classify($m, $fd_me['id'], SK_BTN_START);
		$cls = $state === 'live' ? 'fd-good' : ($state === 'foreign' ? 'fd-bad' : 'fd-warn');
		/* Two live gates both work -- the endpoint does not care which message
		   a click came from -- but a newcomer scrolling the channel can only
		   see one of them, and the other one looks like the dead one. */
		if ($state === 'live' && $fd_live > 1) {
			$say .= ' There is more than one of these; they all work, but keep one.';
		} ?>
	<tr>
		<td><?php echo $m['at'] ? date('j M Y', $m['at']) : '?'; ?></td>
		<td class="fd-wrap"><?php echo htmlspecialchars($m['author'] !== '' ? $m['author'] : '(unknown)', ENT_QUOTES); ?>
			<?php if (!$m['is_bot']): ?><span class="fd-none">person</span><?php endif; ?></td>
		<td class="fd-wrap fd-mono"><?php
			$bits = array();
			foreach ($m['buttons'] as $b) {
				$bits[] = htmlspecialchars(($b['label'] !== '' ? $b['label'] . ' ' : '')
				        . '[' . $b['custom_id'] . ']', ENT_QUOTES);
			}
			echo implode('<br>', $bits); ?></td>
		<td class="fd-mono adm-copy" data-copy="<?php echo htmlspecialchars($m['id'], ENT_QUOTES); ?>"><?php
			echo htmlspecialchars($m['id'], ENT_QUOTES); ?></td>
		<td class="fd-wrap"><span class="fd-tag <?php echo $cls; ?>"><?php echo $state; ?></span>
			<span class="fd-say"><?php echo htmlspecialchars($say, ENT_QUOTES); ?></span></td>
	</tr>
	<?php endforeach; ?>
	</tbody></table></div>
	<?php if ($fd_live === 0 && $fd_me['ok']): ?>
	<p class="fd-row"><span class="fd-tag fd-bad">no live gate</span>
		<span class="fd-say">Nothing in that channel carries our rules button, so every click in
		there goes to another application and times out. This is the whole bug, not a symptom
		of one.</span></p>
	<?php endif; ?>
<?php endif; ?>

<?php /* ---------------- 2. this PHP, over HTTP ---------------- */ ?>
<p class="fd-sect">The web PHP &mdash; read over HTTP, which is the only way it counts</p>
<?php
/*
 * GLOBAL ini VALUES, not the effective ones: db.php calls ini_set() and
 * date_default_timezone_set() on this very page, so the effective values
 * describe what this page did to itself. A startup warning comes out of the
 * php.ini value, before any script runs.
 *
 * date.timezone IS PER PHP VERSION in the CloudLinux selector, so a version
 * switch empties it again -- which is exactly how the gate broke.
 */
$fd_tz = (string)$fd_ini['date.timezone'];
$fd_ob = (string)$fd_ini['output_buffering'];
$fd_checks = array(
	array('PHP', $fd_ini['version'] . ' (' . $fd_ini['sapi'] . ')', 'ok',
	      'Re-check the row below after any version change: the timezone setting does not '
	    . 'follow you across one.'),
	array('date.timezone', $fd_tz === '' ? '(empty)' : $fd_tz,
	      $fd_tz === '' ? 'bad' : ($fd_tz === date_default_timezone_get() ? 'ok' : 'warn'),
	      $fd_tz === ''
	        ? 'EMPTY. Every cold worker prints "PHP Startup: Invalid date.timezone value" in '
	        . 'front of the response, which is not JSON, and Discord reports that as not '
	        . 'responding in time. Set it in cPanel: Select PHP Version, Options, date.timezone.'
	        : ($fd_tz === date_default_timezone_get()
	            ? 'Matches what db.php sets, so no startup warning and no clock disagreement.'
	            : 'Set, so no startup warning -- but it disagrees with db.php\'s '
	            . date_default_timezone_get() . ', so stored times and PHP times differ.')),
	array('output_buffering', $fd_ob === '' ? '(off)' : $fd_ob,
	      ($fd_ob === '' || $fd_ob === '0' || strtolower($fd_ob) === 'off') ? 'warn' : 'ok',
	      ($fd_ob === '' || $fd_ob === '0' || strtolower($fd_ob) === 'off')
	        ? 'Off. The endpoint catches a startup warning by taking over the output buffer, '
	        . 'and with buffering off there is no buffer to take over -- the warning is '
	        . 'already gone down the wire. The timezone row above is then the only defence.'
	        : 'On, so a startup warning lands in a buffer the endpoint can discard.'),
	/*
	 * ABSENT IS NOW A REGRESSION, NOT THE NORMAL STATE.
	 *
	 * This row used to say "expected on this host", because no PHP build
	 * here appeared to have ext/sodium -- a survey that ran each alt-php
	 * binary and so measured what was ENABLED rather than what could be
	 * switched on. It was ticked on in the PHP Selector on 2026-10-31. So
	 * from here, absent means something turned it off, and the likeliest
	 * something is a PHP version change: Selector extensions reset on one,
	 * exactly like the timezone row above.
	 */
	array('ext/sodium', $fd_ini['sodium'] ? 'present' : 'absent', $fd_ini['sodium'] ? 'ok' : 'warn',
	      $fd_ini['sodium']
	        ? 'Native Ed25519, so signature checks are microseconds.'
	        : 'It was enabled in the PHP Selector on 31 Oct 2026, so absent means something '
	        . 'turned it off -- most likely a PHP version change, which resets the extension '
	        . 'list the same way it resets the timezone. Nothing breaks: lib/sodium_compat '
	        . 'does it in pure PHP at about 21ms. Re-tick it when convenient.'),
);
foreach ($fd_checks as $c):
	$cls = $c[2] === 'ok' ? 'fd-good' : ($c[2] === 'bad' ? 'fd-bad' : 'fd-warn'); ?>
<div class="fd-row">
	<span class="fd-k"><?php echo htmlspecialchars($c[0], ENT_QUOTES); ?></span>
	<span class="fd-v <?php echo $cls; ?>"><?php echo htmlspecialchars($c[1], ENT_QUOTES); ?></span>
	<span class="fd-say"><?php echo htmlspecialchars($c[3], ENT_QUOTES); ?></span>
</div>
<?php endforeach; ?>

<?php /* ---------------- 3. the live probe ---------------- */ ?>
<p class="fd-sect">Hit the endpoint as a stranger</p>
<p class="fd-why">Three unsigned requests. A clean <b>401</b> with nothing in front of the body
is what Discord needs; anything printed ahead of it is the bug that looks like a timeout. It
fires per worker, not per request, so one of three failing is the signature of it.</p>
<form method="post" action="admin-frontdoor.php" style="margin:0 0 12px">
	<button class="fd-btn" type="submit" name="fd_probe" value="1">Probe the endpoint</button>
</form>
<?php if ($fd_probe): ?>
<div class="fd-scroll"><table class="fd-t">
<thead><tr><th>#</th><th>Status</th><th>Time</th><th>Body starts</th><th></th></tr></thead>
<tbody>
<?php foreach ($fd_probe as $i => $p): ?>
<tr>
	<td><?php echo $i + 1; ?></td>
	<td class="<?php echo $p['status'] === 401 ? 'fd-good' : 'fd-bad'; ?>"><?php
		echo $p['status'] ?: 'no answer'; ?></td>
	<td><?php echo $p['ms']; ?>ms</td>
	<td class="fd-wrap fd-mono"><?php echo htmlspecialchars($p['head'] !== '' ? $p['head']
		: ($p['error'] !== '' ? $p['error'] : '(empty)'), ENT_QUOTES); ?></td>
	<td><span class="fd-tag <?php echo $p['clean'] ? 'fd-good' : 'fd-bad'; ?>"><?php
		echo $p['clean'] ? 'clean' : 'corrupted'; ?></span></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php /* ---------------- 4. the log ---------------- */ ?>
<p class="fd-sect">Interactions that reached this server</p>
<p class="fd-why"><b>lag</b> is how long the request took to get here, measured from Discord's
own clock &mdash; the interaction id carries the millisecond it was created, so no clock of ours
is involved. <b>work</b> is the part that is ours. Together they run against Discord's 3000ms,
and whichever half is eating it decides who can fix it. A row tagged <b>sent N&times;</b> is one
interaction Discord delivered more than once &mdash; not N clicks, but one press whose reply
Discord would not accept, which is a different bug from being slow and looks the same in a list
of times.</p>
<?php if (!$fd_log): ?>
<p class="fd-none">Nothing logged yet. Either nobody has pressed the button since this was
deployed, or Discord is not calling this endpoint at all &mdash; which is what a dead button in
the channel above, or an unsaved Interactions Endpoint URL, looks like from here.</p>
<?php else: ?>
<div class="fd-scroll"><table class="fd-t">
<thead><tr><th>When</th><th>Outcome</th><th>Button</th><th>Member</th><th>Lag</th><th>Work</th><th>Note</th></tr></thead>
<tbody>
<?php foreach ($fd_log as $r):
	$lag  = isset($r['lag'])  ? (int)$r['lag']  : null;
	$work = isset($r['work']) ? (int)$r['work'] : null;
	$total = (int)$lag + (int)$work;
	$bad  = ($total >= 3000) || (isset($r['stray'])) || $r['outcome'] === 'fatal'
	        || $r['outcome'] === 'rejected'; ?>
<tr>
	<td class="fd-wrap"><?php echo htmlspecialchars(substr($r['at'], 5, 11), ENT_QUOTES); ?></td>
	<td class="fd-wrap <?php echo $bad ? 'fd-bad' : 'fd-good'; ?>"><?php
		echo htmlspecialchars(isset($r['outcome']) ? $r['outcome'] : '?', ENT_QUOTES); ?></td>
	<td class="fd-wrap fd-mono"><?php echo htmlspecialchars(isset($r['custom']) ? $r['custom'] : '', ENT_QUOTES); ?></td>
	<td class="fd-mono adm-copy" data-copy="<?php echo htmlspecialchars(isset($r['user']) ? $r['user'] : '', ENT_QUOTES); ?>"><?php
		echo htmlspecialchars(isset($r['user']) ? $r['user'] : '', ENT_QUOTES); ?></td>
	<td class="<?php echo ($lag !== null && $lag > 1500) ? 'fd-bad' : ''; ?>"><?php
		echo $lag === null ? '?' : $lag . 'ms'; ?></td>
	<td class="<?php echo ($work !== null && $work > 1200) ? 'fd-bad' : ''; ?>"><?php
		echo $work === null ? '?' : $work . 'ms'; ?></td>
	<td class="fd-wrap"><?php
		$note = array();
		if (!empty($r['iid']) && !empty($fd_retry[$r['iid']]) && $fd_retry[$r['iid']] > 1) {
			echo '<span class="fd-tag fd-bad">sent ' . (int)$fd_retry[$r['iid']] . '&times;</span> ';
			$note[] = 'Discord re-sent this same interaction, so it did not accept our reply.';
		}
		if (!empty($r['stray'])) $note[] = 'output discarded: ' . $r['stray'];
		if (!empty($r['note']))  $note[] = $r['note'];
		echo htmlspecialchars(implode(' · ', $note), ENT_QUOTES); ?></td>
</tr>
<?php endforeach; ?>
</tbody></table></div>
<?php endif; ?>

<?php /* ---------------- 5. what the web server saw ---------------- */ ?>
<p class="fd-sect">Requests the web server saw &mdash; including the ones PHP never did</p>
<p class="fd-why">The log above can only hold interactions that <em>reached PHP</em>. If the
server refuses one first &mdash; a ModSecurity rule, a CloudLinux resource limit &mdash; PHP
never runs, nothing is logged, and it looks exactly like Discord never calling. This panel is
the layer below. <b>A 403 or a 503 here with nothing in the log above is the answer.</b></p>
<?php if (!$fd_acc['ok']): ?>
	<p class="fd-row"><span class="fd-tag fd-warn">no log</span>
		<span class="fd-say"><?php echo htmlspecialchars($fd_acc['error'], ENT_QUOTES); ?></span></p>
<?php elseif (!$fd_acc['hits']): ?>
	<p class="fd-none">The access log is readable and carries no request to this endpoint at all
	in its recent tail. If somebody pressed the button in that window, Discord is not reaching
	this server &mdash; which points at the Interactions Endpoint URL, not at anything here.</p>
<?php else: ?>
	<p class="fd-row">
		<span class="fd-k">From Discord</span>
		<span class="fd-v"><?php echo number_format($fd_acc_discord); ?></span>
		<span class="fd-say">of <?php echo number_format(count($fd_acc['hits'])); ?> recent
		requests to this endpoint. PHP recorded <?php echo number_format(count($fd_log)); ?>
		interaction<?php echo count($fd_log) === 1 ? '' : 's'; ?>; a Discord request that is
		here and not there never ran.</span>
	</p>
	<div class="fd-scroll"><table class="fd-t">
	<thead><tr><th>When</th><th>Status</th><th>From</th><th>Agent</th><th>Means</th></tr></thead>
	<tbody>
	<?php foreach ($fd_acc['hits'] as $h):
		list($st, $say) = frontdoor_status_say($h['status']);
		$cls = $st === 'ok' ? 'fd-good' : ($st === 'bad' ? 'fd-bad' : 'fd-warn'); ?>
	<tr>
		<td class="fd-wrap"><?php echo $h['at'] ? date('j M H:i:s', $h['at']) : '?'; ?></td>
		<td class="<?php echo $cls; ?>"><?php echo (int)$h['status']; ?></td>
		<td class="fd-mono"><?php echo htmlspecialchars($h['ip'], ENT_QUOTES); ?></td>
		<td class="fd-wrap"><?php echo frontdoor_is_discord($h['ua'])
			? '<span class="fd-tag fd-good">discord</span>'
			: '<span class="fd-none">' . htmlspecialchars(substr($h['ua'], 0, 40), ENT_QUOTES) . '</span>'; ?></td>
		<td class="fd-wrap fd-say"><?php echo htmlspecialchars($say, ENT_QUOTES); ?></td>
	</tr>
	<?php endforeach; ?>
	</tbody></table></div>
<?php endif; ?>
