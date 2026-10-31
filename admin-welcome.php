<?php
/**
 * admin-welcome.php — who arrived lately and where they stalled.
 *
 * THE MANUAL VERSION OF THIS IS SCROLLING THE WELCOME CHANNEL. That is a
 * list which only goes one way, with no record of who has already been
 * written to and no way to tell at a glance whether somebody is still in
 * the server at all. What it costs most is the people further up, who get
 * missed because the scroll gets tiring before they do.
 *
 * THREE GROUPS, THREE QUESTIONS -- the owner's own split, arrived at by
 * doing this by hand:
 *
 *   Gone       welcomed and no longer here. Ask why they left.
 *   Visitor    here, never past the rules gate. Ask if the questions
 *              were the problem.
 *   Candidate  through the gate and no further. Ask whether the wallet
 *              or staking is where they stuck.
 *
 * Anyone holding more than Candidate got where they were going and is
 * not on the list.
 *
 * READ ONLY against Discord. The only thing this page writes is the
 * owner's own note of who he has messaged.
 *
 * SEE ALSO admin-lapsed.php, which is the same shape of tool pointed at
 * the other end of the funnel -- people who used to be here. This one is
 * people who never really started.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/avatar-lib.php';
require_once __DIR__ . '/lib/welcome-channel.php';
require_once __DIR__ . '/lib/outreach-log.php';
require_once __DIR__ . '/discord-rules-lib.php';   // $bot_token, $role_visitor, $role_candidate

if (!defined('OUTREACH_LIST_WELCOME')) define('OUTREACH_LIST_WELCOME', 'welcome');

/* Refreshing the roster is a POST so a reload cannot repeat it, and the
   result is carried in the session rather than the query string -- the
   same shape admin-lapsed.php uses for the same button. */
$aw_msg = '';
if (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['aw_msg'])) {
	$aw_msg = $_SESSION['aw_msg'];
	unset($_SESSION['aw_msg']);
}
/*
 * NAMED aw_refresh, NOT refresh, AND THAT IS NOT FUSSINESS.
 *
 * skulliance.php is included by every signed-in page and reads $_POST at
 * include time: `if(isset($_POST['refresh']))` is its WALLET refresh,
 * which calls verifyNFTs() from verify.php -- a file this page does not
 * include. Posting a field called `refresh` from here reached that branch
 * and took the page down with
 *
 *   Fatal error: Call to undefined function verifyNFTs()
 *
 * before a line of this file ran. Any form field on any admin page shares
 * a namespace with that one, so prefix them.
 */
if (isset($_SERVER['REQUEST_METHOD']) && $_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['aw_refresh'])) {
	$r = guild_members_refresh($bot_token);
	$_SESSION['aw_msg'] = !empty($r['ok'])
		? 'Roster refreshed: ' . number_format((int)$r['count']) . ' members.'
		: 'Could not refresh the roster. ' . (string)$r['error'];
	header('Location: admin-welcome.php');
	exit;
}

/*
 * ONE MEMBER, INTERROGATED. Paste a Discord id and the page says what it
 * sees -- in the roster or not, what roles it has for them, which group
 * that puts them in. Added because "nich holds Candidate and is not in
 * the Candidate list" took three rounds to pin down, and every round was
 * me asking for something off the server that the page could have said
 * itself.
 */
$aw_look = isset($_GET['id']) ? preg_replace('/[^0-9]/', '', (string)$_GET['id']) : '';

$aw_last  = guild_members_last();
$aw_cache = guild_members_load();
$aw_known = !empty($aw_cache['ids']);
$aw_roles_known = isset($aw_cache['roles']) && is_array($aw_cache['roles']);
$aw_arr   = welcome_arrivals($bot_token);
$aw_log   = outreach_load(OUTREACH_LIST_WELCOME);

/*
 * THE PLATFORM'S OWN ROW FOR THEM, WHERE THERE IS ONE. A Discord id is
 * enough to write to somebody, but the avatar and the platform username
 * are what make a list of three hundred recognisable -- and whether they
 * have an account at all is itself part of the answer for a Candidate
 * who never staked.
 */
$aw_users = array();
if (!empty($aw_arr['people'])) {
	$ids = array();
	foreach (array_keys($aw_arr['people']) as $d) {
		$d = preg_replace('/[^0-9]/', '', (string)$d);
		if ($d !== '') $ids[] = "'" . $conn->real_escape_string($d) . "'";
	}
	if ($ids) {
		$q = @$conn->query("SELECT id, username, discord_id, avatar, last_login
		                      FROM users WHERE discord_id IN (" . implode(',', $ids) . ")");
		if ($q) while ($x = $q->fetch_assoc()) $aw_users[(string)$x['discord_id']] = $x;
	}
}

/*
 * Sorted into the three buckets, newest arrival first within each.
 *
 * AND COUNTED AS THEY FALL THROUGH, because the three totals do NOT add
 * up to the number of arrivals and that looked like a bug when it was
 * not. Somebody in the server whose roles we cannot read belongs to no
 * group, so while the roster has no roles the ONLY bucket that can fill
 * is Left -- which reads as "everybody left" and is really "we cannot
 * see anybody's roles". The page says which, below, rather than leaving
 * it to be inferred from three zeroes.
 */
$aw_rows = array('gone' => array(), 'visitor' => array(), 'candidate' => array());
$aw_skip = array('noroles' => 0, 'settled' => 0);
foreach ($aw_arr['people'] as $did => $p) {
	$in    = guild_member_in($aw_cache, $did);
	$roles = guild_member_roles($aw_cache, $did);
	$g     = welcome_group($in, $roles, (string)$role_visitor, (string)$role_candidate);
	if ($g === '' || !isset($aw_rows[$g])) {
		/* Told apart because they mean opposite things: one is a gap in
		   what we know, the other is somebody who is getting on fine. */
		if ($in && $roles === null) $aw_skip['noroles']++;
		elseif ($in)                $aw_skip['settled']++;
		continue;
	}
	$aw_rows[$g][$did] = $p;
}
$aw_found = count($aw_arr['people']);
foreach ($aw_rows as $k => $v) {
	uasort($aw_rows[$k], function ($a, $b) { return (int)$b['at'] <=> (int)$a['at']; });
}

$aw_view = isset($_GET['view']) ? (string)$_GET['view'] : 'visitor';
if (!isset($aw_rows[$aw_view])) $aw_view = 'visitor';

/* How many of each are still to do, which is the number worth showing:
   a count that does not come down as you work is not a progress bar. */
$aw_todo = array();
foreach ($aw_rows as $k => $v) {
	$n = 0;
	foreach (array_keys($v) as $did) if (!outreach_done($aw_log, $did)) $n++;
	$aw_todo[$k] = $n;
}

$AW_LABEL = array('gone' => 'Left', 'visitor' => 'Stuck at the rules',
                  'candidate' => 'Never staked');
$AW_WHY = array(
	'gone'      => 'Welcomed, and no longer in the server.',
	'visitor'   => 'In the server, never got past the rules questions.',
	'candidate' => 'Through the rules, never connected a wallet or staked.',
);

include 'header.php';
admin_chrome('welcome');
?>
<?php include 'admin-css.php'; ?>
<?php include 'admin-copy.php';   /* click a name to copy it */ ?>
<style>
.aw-score { display:flex; gap:28px; flex-wrap:wrap; margin:0 0 16px; }
.aw-score div { display:flex; flex-direction:column; }
.aw-score b { font-size:1.5rem; color:#e8eaed; font-variant-numeric:tabular-nums; line-height:1.1; }
.aw-score span { font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; color:#5f7d90; margin-top:3px; }
.aw-views { margin:0 0 10px; display:flex; gap:8px; flex-wrap:wrap; }
.aw-views a { font-size:.78rem; padding:5px 12px; border-radius:999px; text-decoration:none;
  background:#0e1c2c; color:#9fd8c8; border:1px solid rgba(255,255,255,.12); }
.aw-views a.on { background:#123049; color:#e8eaed; border-color:#00c8a0; }
.aw-why { font-size:.8rem; color:#8fa8b8; margin:0 0 14px; }
.aw-warn { font-size:.8rem; color:#f5a623; background:rgba(245,166,35,.08);
  border:1px solid rgba(245,166,35,.3); border-radius:6px; padding:10px 12px; margin:0 0 14px; }
.aw-ok { color:#00c8a0; border-color:rgba(0,200,160,.3); background:rgba(0,200,160,.08); }
.aw-t { width:100%; border-collapse:collapse; font-size:.82rem; }
.aw-t th { text-align:left; font-size:.68rem; letter-spacing:.08em; text-transform:uppercase;
  color:#5f7d90; border-bottom:1px solid rgba(255,255,255,.12); padding:0 10px 7px; font-weight:600; }
.aw-t td { padding:9px 10px; border-bottom:1px solid rgba(255,255,255,.06); vertical-align:middle; }
.aw-who { display:flex; align-items:center; gap:9px; }
.aw-who img { width:26px; height:26px; border-radius:50%; flex:none; }
.aw-dim { color:#5f7d90; }
/* An account made days before it turned up. Amber, not red: it is a
   reason to look, not a verdict -- real people make accounts to join
   things. */
.aw-fresh { color:#f5a623; }
.aw-none { color:#5f7d90; font-style:italic; }
td.aw-act { text-align:right; white-space:nowrap; }
.aw-btn { background:#123049; color:#9fd8c8; border:1px solid rgba(255,255,255,.14);
  border-radius:6px; padding:4px 12px; font-size:.78rem; cursor:pointer; }
.aw-btn:hover { background:#17405f; }
.aw-btn:focus-visible { outline:2px solid #00c8a0; outline-offset:2px; }
.aw-btn.on { background:transparent; color:#5f7d90; border-style:dashed; }
.aw-refresh { background:none; border:1px solid rgba(255,255,255,.14); color:#9fd8c8;
  border-radius:6px; padding:5px 12px; font-size:.78rem; cursor:pointer; }

/* THE DIALOG'S OWN STYLING, not borrowed from admin-lapsed.php.
   Those .lap-m* rules live in that page's inline <style>, not in
   admin-css.php, so reusing the class names here produced a dialog with
   no styling at all -- the backdrop had no position, so removing
   [hidden] revealed an unstyled block at the foot of the page and the
   button looked dead. Self-contained, with its own prefix. */
.aw-mw { position:fixed; inset:0; background:rgba(4,10,16,.82); z-index:9000;
  display:flex; align-items:center; justify-content:center; padding:20px; }
.aw-mw[hidden] { display:none; }
.aw-mp { background:#0b1b26; border:1px solid rgba(255,255,255,.14); border-radius:10px;
  padding:16px; width:min(560px,100%); max-height:90vh; overflow:auto; }
.aw-mh { display:flex; align-items:baseline; justify-content:space-between; gap:12px; margin:0 0 12px; }
.aw-mh h3 { margin:0; font-size:1rem; color:#e8eaed; }
.aw-mx { background:none; border:0; color:#8fa8b8; font-size:1.4rem; line-height:1;
  cursor:pointer; padding:0 2px; }
.aw-mname { background:#123049; color:#9fd8c8; border:1px solid rgba(255,255,255,.14);
  border-radius:6px; padding:3px 10px; font-size:.72rem; cursor:pointer; margin-left:auto; }
.aw-offscreen { position:absolute; left:-9999px; width:1px; height:1px; }
.aw-mi { display:flex; flex-direction:column; gap:7px; }
.aw-ml { font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; color:#5f7d90; }
.aw-mu { width:100%; background:#07131c; border:1px solid rgba(255,255,255,.12);
  border-radius:6px; color:#e8eaed; font:inherit; font-size:.82rem; padding:9px 10px;
  box-sizing:border-box; }
.aw-mmsg { resize:vertical; line-height:1.5; }
.aw-mc { background:#00c8a0; color:#04141c; border:0; border-radius:6px; padding:7px 14px;
  font-size:.8rem; font-weight:600; cursor:pointer; }
.aw-mc2 { background:#123049; color:#9fd8c8; }
.aw-mdone { background:#f5a623; color:#2a1a00; }
.aw-mnav { display:flex; align-items:center; gap:8px; margin-top:14px; }
.aw-mnav span { font-size:.72rem; color:#5f7d90; margin:0 auto; }
.aw-mc:disabled, .aw-mnav button:disabled { opacity:.4; cursor:default; }
</style>

<h2 style="margin:0 0 4px">Newcomers</h2>
<p class="aw-why">
	Read from the welcome channel, crossed against the server roster.
	<?php if (!empty($aw_cache['at'])): ?>
	Roster <?php echo guild_members_stale($aw_cache) ? '<b style="color:#f5a623">is stale</b>, from'
	                                                 : 'from'; ?>
	<?php echo htmlspecialchars(date('j M, H:i', (int)$aw_cache['at'])); ?>,
	<?php /* THE SIZE, because a roster that fetched one page of a large
	         server looks identical to a healthy one until you notice that
	         everybody reads as having left. */ ?>
	<b><?php echo number_format(count((array)$aw_cache['ids'])); ?></b> members.
	<?php endif; ?>
	Read <b><?php echo number_format($aw_found); ?></b> arrivals from the channel.</p>
	<p class="aw-why"><?php
		/* WHY THE THREE NUMBERS DO NOT ADD UP, said out loud. */
		if ($aw_skip['noroles']) {
			echo '<b style="color:#f5a623">' . number_format($aw_skip['noroles']) . '</b> of them '
			   . 'are in the server but the roster cannot say what roles they hold, so they are '
			   . 'in none of the groups below. That is what the warning above is about. ';
		}
		if ($aw_skip['settled']) {
			echo number_format($aw_skip['settled']) . ' got further than Candidate and need '
			   . 'nothing. ';
		}
	?>
	<form method="post" style="display:inline">
		<button type="submit" name="aw_refresh" value="1" class="aw-refresh">Refresh roster</button>
	</form>
</p>

<?php /* THE LAST ATTEMPT, WHETHER OR NOT IT WAS THIS PAGE LOAD. A flash
         message is lost on the next reload, which is how three refreshes
         can fail for the same reason without the reason ever being read. */ ?>
<?php if (!empty($aw_last['at']) && empty($aw_last['ok'])): ?>
<p class="aw-warn"><b>Last refresh failed</b>
	(<?php echo htmlspecialchars(date('j M, H:i', (int)$aw_last['at'])); ?>):
	<?php echo htmlspecialchars((string)$aw_last['error']); ?></p>
<?php elseif (!empty($aw_last['at'])): ?>
<p class="aw-why">Last refresh succeeded
	<?php echo htmlspecialchars(date('j M, H:i', (int)$aw_last['at'])); ?>,
	<?php echo number_format((int)$aw_last['count']); ?> members.</p>
<?php endif; ?>

<?php /* ---- interrogate one member ---- */ ?>
<form method="get" style="margin:0 0 14px">
	<label class="aw-ml" for="awLook">Check one Discord id</label><br>
	<input type="text" name="id" id="awLook" inputmode="numeric" placeholder="1009474801317974016"
	       value="<?php echo htmlspecialchars($aw_look, ENT_QUOTES); ?>"
	       style="background:#07131c;border:1px solid rgba(255,255,255,.12);border-radius:6px;
	              color:#e8eaed;font:inherit;font-size:.82rem;padding:7px 10px;width:260px">
	<button type="submit" class="aw-refresh">Check</button>
</form>
<?php if ($aw_look !== ''):
	$lk_in    = guild_member_in($aw_cache, $aw_look);
	$lk_roles = guild_member_roles($aw_cache, $aw_look);
	$lk_g     = welcome_group($lk_in, $lk_roles, (string)$role_visitor, (string)$role_candidate);
	$lk_seen  = isset($aw_arr['people'][$aw_look]);
?>
<p class="aw-warn">
	<b><?php echo htmlspecialchars($aw_look); ?></b><br>
	In the welcome channel: <b><?php echo $lk_seen ? 'yes' : 'no'; ?></b><br>
	In the roster cache: <b><?php echo $lk_in ? 'yes' : 'no'; ?></b><br>
	Roles the cache holds: <b><?php
		echo $lk_roles === null ? 'UNKNOWN (cache has no roles)'
		   : ($lk_roles ? htmlspecialchars(implode(', ', $lk_roles)) : 'none'); ?></b><br>
	Configured Visitor id: <b><?php echo htmlspecialchars((string)$role_visitor ?: 'NOT SET'); ?></b>,
	Candidate id: <b><?php echo htmlspecialchars((string)$role_candidate ?: 'NOT SET'); ?></b><br>
	Group: <b><?php echo $lk_g === '' ? 'none -- not shown on this page' : htmlspecialchars($lk_g); ?></b>
</p>
<?php endif; ?>

<?php if ($aw_msg !== ''): ?>
<p class="aw-warn <?php echo strpos($aw_msg, 'Could not') === 0 ? '' : 'aw-ok'; ?>"><?php
	echo htmlspecialchars($aw_msg); ?></p>
<?php endif; ?>

<?php /* EVERY REASON THE LIST COULD BE EMPTY, SAID OUT LOUD. An empty
         page that does not explain itself is indistinguishable from a
         quiet week, and all three of these are one-line fixes nobody
         would guess at. */ ?>
<?php if (!$aw_arr['ok']): ?>
<p class="aw-warn">Could not read the welcome channel. <?php echo htmlspecialchars($aw_arr['error']); ?></p>
<?php endif; ?>
<?php if (!$aw_known): ?>
<p class="aw-warn">The server roster has never been fetched, so nobody can be placed in a
	group. Press <b>Refresh roster</b>. If that fails, the bot needs the
	<b>Server Members Intent</b> switched on in the Developer Portal.</p>
<?php elseif (!$aw_roles_known): ?>
<p class="aw-warn">The cached roster predates role capture, so it knows who is here but not
	what they hold. Press <b>Refresh roster</b> to fill in the Visitor and Candidate groups.</p>
<?php endif; ?>
<?php if (empty($role_visitor) || empty($role_candidate)): ?>
<p class="aw-warn">The Visitor or Candidate role id is not set in
	<code>credentials/webhooks_credentials.php</code>, so those two groups cannot be told apart.</p>
<?php endif; ?>

<div class="aw-score">
	<?php foreach ($AW_LABEL as $k => $label): ?>
	<div><b><?php echo number_format(count($aw_rows[$k])); ?></b><span><?php
		echo htmlspecialchars($label); ?></span></div>
	<?php endforeach; ?>
	<div><b><?php echo number_format(array_sum($aw_todo)); ?></b><span>still to write to</span></div>
	<?php
	/* Counted across every group, not just the visible one: the question
	   "how many of these are throwaways" is about the whole intake. */
	$aw_fresh = 0;
	foreach ($aw_rows as $grp) foreach ($grp as $d2 => $p2) {
		$a2 = discord_age_at($d2, (int)$p2['at']);
		if ($a2 !== null && $a2 < WELCOME_FRESH_DAYS) $aw_fresh++;
	}
	?>
	<div><b class="<?php echo $aw_fresh ? 'aw-fresh' : ''; ?>"><?php
		echo number_format($aw_fresh); ?></b><span>brand-new accounts</span></div>
</div>

<div class="aw-views">
	<?php foreach ($AW_LABEL as $k => $label): ?>
	<a href="admin-welcome.php?view=<?php echo urlencode($k); ?>"
	   class="<?php echo $k === $aw_view ? 'on' : ''; ?>"><?php
		echo htmlspecialchars($label); ?>
		<span class="aw-dim">(<?php echo (int)$aw_todo[$k]; ?>)</span></a>
	<?php endforeach; ?>
</div>
<p class="aw-why"><?php echo htmlspecialchars($AW_WHY[$aw_view]); ?></p>

<?php if (!$aw_rows[$aw_view]): ?>
<p class="aw-none">Nobody in this group.</p>
<?php else: ?>
<table class="aw-t">
	<thead><tr><th>Member</th><th>Welcomed</th><th>Discord account</th><th>Platform</th><th></th></tr></thead>
	<tbody>
	<?php foreach ($aw_rows[$aw_view] as $did => $p):
		$u    = isset($aw_users[$did]) ? $aw_users[$did] : null;
		$name = $p['name'] !== '' ? $p['name']
		      : ($u ? (string)$u['username'] : guild_member_name($aw_cache, $did));
		if ($name === '') $name = 'user ' . $did;
		$done = outreach_done($aw_log, $did);
	?>
	<tr>
		<td>
			<div class="aw-who">
				<img loading="lazy" alt=""
				     src="<?php echo htmlspecialchars(avatar_url($did, $u ? $u['avatar'] : '', 64), ENT_QUOTES); ?>"
				     onerror="this.src='icons/skull.png'">
				<span class="adm-copy" tabindex="0" role="button"
				      data-copy="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>"
				      title="<?php echo htmlspecialchars($did, ENT_QUOTES); ?> — click to copy"><?php
					echo htmlspecialchars($name); ?></span>
			</div>
		</td>
		<td class="aw-dim"><?php
			echo $p['at'] ? htmlspecialchars(date('j M Y', (int)$p['at'])) : '&mdash;'; ?></td>
		<td class="aw-dim"><?php
			/*
			 * HOW OLD THE ACCOUNT WAS WHEN THEY ARRIVED. The closest thing
			 * available to "how many servers do they share with me", which
			 * a bot cannot see at all -- and a better signal regardless: a
			 * spam account is days old on arrival, a real one is usually
			 * years old whether or not you share anything else.
			 *
			 * Marked, never hidden. Plenty of real people make an account
			 * to join something.
			 */
			$age = discord_age_at($did, (int)$p['at']);
			$made = discord_created_at($did);
			if ($age === null) { echo '<span class="aw-none">&mdash;</span>'; }
			elseif ($age < WELCOME_FRESH_DAYS) {
				echo '<b class="aw-fresh">' . ($age <= 1 ? 'new that day' : $age . ' days old')
				   . '</b><br><span class="aw-none">made '
				   . htmlspecialchars(date('j M Y', $made)) . '</span>';
			} else {
				$yrs = $age / 365;
				echo $yrs >= 1 ? number_format($yrs, 1) . ' yrs' : $age . ' days';
				echo '<br><span class="aw-none">made ' . htmlspecialchars(date('M Y', $made)) . '</span>';
			}
		?></td>
		<td class="aw-dim"><?php
			/* WHETHER THEY EVER SIGNED IN AT ALL, which for a Candidate who
			   never staked is most of the answer: no account means they
			   never reached the site, and the wallet is not the problem. */
			echo $u ? 'yes' : '<span class="aw-none">never signed in</span>'; ?></td>
		<td class="aw-act">
			<button type="button" class="aw-btn<?php echo $done ? ' on' : ''; ?>"
			        data-did="<?php echo htmlspecialchars($did, ENT_QUOTES); ?>"
			        data-who="<?php echo htmlspecialchars($name, ENT_QUOTES); ?>"
			        data-sent="<?php echo $done ? '1' : ''; ?>"
			        data-msg="<?php echo htmlspecialchars(welcome_text($aw_view, $name), ENT_QUOTES); ?>"><?php
				echo $done ? 'written' : 'write'; ?></button>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

<div class="aw-mw" id="awModal" hidden>
	<div class="aw-mp" role="dialog" aria-modal="true" aria-labelledby="awWho">
		<div class="aw-mh">
			<h3 id="awWho"></h3>
			<button type="button" class="aw-mname" id="awCopyName">Copy name</button>
			<input class="aw-offscreen" id="awName" type="text" readonly aria-hidden="true" tabindex="-1">
			<button type="button" class="aw-mx" id="awX" aria-label="Close">&times;</button>
		</div>
		<div class="aw-mi">
			<label class="aw-ml" for="awMsg">The message</label>
			<textarea class="aw-mu aw-mmsg" id="awMsg" rows="8" readonly></textarea>
			<button type="button" class="aw-mc" id="awCopyMsg">Copy message</button>
		</div>
		<div class="aw-mnav">
			<button type="button" class="aw-mc aw-mc2" id="awPrev">&larr; Prev</button>
			<span id="awPos"></span>
			<button type="button" class="aw-mc aw-mdone" id="awDone">Mark written &amp; next</button>
			<button type="button" class="aw-mc aw-mc2" id="awNext">Next &rarr;</button>
		</div>
	</div>
</div>

<script>
(function () {
	var wrap = document.getElementById('awModal');
	if (!wrap) return;
	var who  = document.getElementById('awWho'),
	    msg  = document.getElementById('awMsg'),
	    cpM  = document.getElementById('awCopyMsg'),
	    nm   = document.getElementById('awName'),
	    cpN  = document.getElementById('awCopyName'),
	    prev = document.getElementById('awPrev'),
	    next = document.getElementById('awNext'),
	    pos  = document.getElementById('awPos'),
	    done = document.getElementById('awDone'),
	    all  = Array.prototype.slice.call(document.querySelectorAll('.aw-btn')),
	    at   = -1, last = null;

	/* execCommand is deprecated and is the only thing that works without a
	   secure context or a permission prompt, so it is the fallback. */
	function wire(field, btn, label) {
		btn.addEventListener('click', function () {
			field.select();
			field.setSelectionRange(0, field.value.length);   // iOS needs the range
			function ok() {
				btn.textContent = 'Copied';
				setTimeout(function () { btn.textContent = label; }, 1600);
			}
			if (navigator.clipboard && navigator.clipboard.writeText) {
				navigator.clipboard.writeText(field.value).then(ok, function () {
					try { document.execCommand('copy'); ok(); } catch (e) {}
				});
			} else {
				try { document.execCommand('copy'); ok(); } catch (e) {}
			}
		});
	}
	wire(msg, cpM, 'Copy message');
	wire(nm,  cpN, 'Copy name');

	function open(btn) {
		last = btn;
		at = all.indexOf(btn);
		pos.textContent = (at >= 0) ? (at + 1) + ' of ' + all.length : '';
		prev.disabled = (at <= 0);
		next.disabled = (at < 0 || at >= all.length - 1);
		var sent = btn.getAttribute('data-sent') === '1';
		done.setAttribute('data-on', sent ? '1' : '');
		done.textContent = sent ? 'Written (undo)' : 'Mark written & next';
		done.disabled = false;
		who.textContent = btn.getAttribute('data-who') || '';
		nm.value        = btn.getAttribute('data-who') || '';
		msg.value       = btn.getAttribute('data-msg') || '';
		cpM.textContent = 'Copy message';
		cpN.textContent = 'Copy name';
		wrap.hidden = false;
		cpM.focus();
	}
	function close() {
		wrap.hidden = true; at = -1;
		if (last) { last.focus(); last = null; }
	}
	function step(by) {
		if (at < 0) return;
		var n = at + by;
		if (n < 0 || n >= all.length) return;
		if (all[n].scrollIntoView) all[n].scrollIntoView({ block: 'center' });
		open(all[n]);
	}
	document.addEventListener('click', function (e) {
		var b = e.target.closest ? e.target.closest('.aw-btn') : null;
		if (b) { e.preventDefault(); open(b); return; }
		if (e.target === wrap || e.target.id === 'awX') close();
	});
	prev.addEventListener('click', function () { step(-1); });
	next.addEventListener('click', function () { step(1); });
	document.addEventListener('keydown', function (e) {
		if (wrap.hidden) return;
		if (e.key === 'Escape')     { close(); return; }
		if (e.key === 'ArrowLeft')  { e.preventDefault(); step(-1); }
		if (e.key === 'ArrowRight') { e.preventDefault(); step(1); }
	});

	/* MARK, THEN MOVE, and only once the server has confirmed -- a row
	   shown as done before the write lands quietly drops somebody off a
	   list whose whole job is not losing anybody. */
	done.addEventListener('click', function () {
		if (at < 0) return;
		var btn = all[at],
		    on  = done.getAttribute('data-on') !== '1',
		    body = 'discord_id=' + encodeURIComponent(btn.getAttribute('data-did'))
		         + '&list=' + encodeURIComponent('<?php echo OUTREACH_LIST_WELCOME; ?>')
		         + (on ? '' : '&off=1');
		done.disabled = true;
		done.textContent = on ? 'Marking...' : 'Undoing...';
		fetch('ajax/admin-outreach.php', {
			method: 'POST', credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body
		}).then(function (r) { return r.json(); }).then(function (j) {
			if (!j || !j.ok) throw new Error((j && j.why) || 'failed');
			btn.setAttribute('data-sent', on ? '1' : '');
			btn.textContent = on ? 'written' : 'write';
			btn.classList.toggle('on', on);
			if (on && at < all.length - 1) step(1); else open(btn);
		}).catch(function () {
			done.disabled = false;
			done.textContent = 'Could not save';
			setTimeout(function () {
				done.textContent = done.getAttribute('data-on') === '1'
					? 'Written (undo)' : 'Mark written & next';
			}, 2000);
		});
	});
})();
</script>
