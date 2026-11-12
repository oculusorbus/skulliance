<?php
/*
 * admin-infiltration.php -- submit and manage targets.
 *
 * A target is a post of ours worth replying to. There is no cadence: three
 * in a day or none for a fortnight, whichever suits. Nothing expires, so a
 * target stays infiltratable forever and only closes when it is retired
 * here.
 *
 * WHAT THIS PAGE IS REALLY FOR, beyond adding targets: the pending count.
 * Verification rests on one undocumented endpoint, and the day it changes,
 * submissions start parking instead of verifying. That number rising is how
 * we find out -- from this page rather than from Discord.
 *
 * Reads on GET. Every change is a POST, because a side effect on GET fires
 * on every prefetch.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/dhcfighters-config.php';
require_once __DIR__ . '/dhcfighters-lib.php';
require_once __DIR__ . '/lib/infiltration-lib.php';
admin_require();

$ai_missing = infil_tables_missing($conn);
$ai_msgs    = array('ok' => array(), 'err' => array(), 'warn' => array());

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

	if (isset($_POST['install'])) {
		list($made, $errs) = infil_install($conn);
		foreach ($errs as $e) $ai_msgs['err'][] = htmlspecialchars($e);
		$ai_missing = infil_tables_missing($conn);
		$_SESSION['ai_ok'] = $ai_missing ? 'Some tables could not be created.'
		                                 : 'Tables are in place.';

	} else if (isset($_POST['add'])) {
		$r = infil_target_add($conn, (string) $_POST['url'], (string) $_POST['label'],
		                      (int) $_POST['early'], (int) $_POST['main']);
		$_SESSION[$r['id'] > 0 ? 'ai_ok' : 'ai_err'] =
			$r['id'] > 0 ? 'Target added.' : $r['why'];

	} else if (isset($_POST['retire'])) {
		infil_target_retire($conn, (int) $_POST['target'], (int) $_POST['retire'] === 1);
		$_SESSION['ai_ok'] = ((int) $_POST['retire'] === 1) ? 'Target retired.' : 'Target reopened.';

	} else if (isset($_POST['windows'])) {
		infil_target_windows($conn, (int) $_POST['target'], (int) $_POST['early'], (int) $_POST['main']);
		$_SESSION['ai_ok'] = 'Windows updated.';
	}
	header('Location: admin-infiltration.php');
	exit;
}
if (isset($_SESSION['ai_ok']))  { $ai_msgs['ok'][]  = htmlspecialchars($_SESSION['ai_ok']);  unset($_SESSION['ai_ok']); }
if (isset($_SESSION['ai_err'])) { $ai_msgs['err'][] = htmlspecialchars($_SESSION['ai_err']); unset($_SESSION['ai_err']); }

$ai_ready = count($ai_missing) === 0;
if ($ai_ready) {
	$ai_settled = infil_settle_due($conn);
	if ($ai_settled['targets'] > 0) {
		$ai_msgs['warn'][] = 'Closed ' . (int) $ai_settled['targets'] . ' target'
		  . ($ai_settled['targets'] === 1 ? '' : 's') . ', costing a rung to '
		  . (int) $ai_settled['docked'] . ' member'
		  . ($ai_settled['docked'] === 1 ? '' : 's') . ' who did not reply.';
	}
}

$ai_targets = $ai_ready ? infil_targets($conn) : array();
$ai_pending = $ai_ready ? infil_pending_count($conn) : 0;
$ai_recent  = $ai_ready ? infil_recent($conn, 40) : array();
$ai_hook    = function_exists('getInfiltrationsWebhook');

include 'header.php';
admin_chrome('infiltration');
?>
<?php include 'admin-css.php'; ?>
<style>
.adm-heat { display:inline-block; width:46px; height:7px; background:rgba(255,255,255,.1);
  vertical-align:middle; }
.adm-heat i { display:block; height:100%; background:#00c8a0; }
.adm-quote { color:#8aa0b4; font-size:.82rem; max-width:360px; }
</style>
<div class="row"><div class="adm">
  <h3>Infiltration</h3>
  <small>
    Members reply to our posts on X in their own words. A verified reply pays a trait, and
    how promptly they replied moves them up a ten rung ladder that decides how good it is.
    Nothing here is public: not in the Skull Paper, not on a marketing page, and the posts
    themselves stay ordinary posts.
  </small>

  <?php admin_flash($ai_msgs); ?>

  <?php if (!$ai_ready): ?>
    <div class="adm-card">
      <h4>Not installed</h4>
      <p>These tables do not exist yet: <code><?php echo htmlspecialchars(implode(', ', $ai_missing)); ?></code></p>
      <form method="post"><button type="submit" name="install" value="1">Create them</button></form>
      <p class="adm-note"><small>Creates only what is missing. Safe to run twice.</small></p>
    </div>
  <?php else: ?>

  <?php /* ---- health ---------------------------------------------------- */ ?>
  <div class="adm-card">
    <h4>Health</h4>
    <p class="adm-note">
      Waiting on X:
      <b style="color:<?php echo $ai_pending > 0 ? '#e8b24a' : '#00c8a0'; ?>"><?php echo (int) $ai_pending; ?></b>
      <?php if ($ai_pending > 0): ?>
        <br><span class="warn">A submission parks when X does not answer. One or two is
        ordinary. A number that keeps climbing means the syndication endpoint has changed,
        and <a href="admin-xprobe.php">the probe</a> will say how.</span>
      <?php endif; ?>
    </p>
    <p class="adm-note">
      Discord channel:
      <?php if ($ai_hook): ?>
        <b class="ok-dot">connected</b>
      <?php else: ?>
        <b class="bad-dot">no webhook</b> &mdash; add
        <code>getInfiltrationsWebhook()</code> to
        <code>credentials/webhooks_credentials.php</code>. Until then infiltrations are paid
        in silence; nothing breaks.
      <?php endif; ?>
    </p>
  </div>

  <?php /* ---- add a target ------------------------------------------------ */ ?>
  <div class="adm-card">
    <h4>Add a target</h4>
    <small>Paste one of our own posts. It is checked before it is saved: it has to exist,
      be ours, and not itself be a reply &mdash; a target that is secretly a reply would make
      every infiltration of it a nested reply, and every one of them would be refused.</small>
    <form method="post" class="adm-form">
      <label class="adm-wide">Post link
        <input type="text" name="url" class="adm-wide" required autocomplete="off"
               placeholder="https://x.com/skulliance/status/...">
      </label>
      <label class="adm-wide">Label <small style="text-transform:none">(what you will recognise it by)</small>
        <input type="text" name="label" class="adm-wide" autocomplete="off" placeholder="Portal announcement">
      </label>
      <div class="adm-grid">
        <label>First window (minutes)
          <input type="number" name="early" value="<?php echo (int) INFIL_EARLY_MINS; ?>" min="1">
          <small>Worth +2. One hour is where a reply actually buys reach.</small>
        </label>
        <label>Main window (minutes)
          <input type="number" name="main" value="<?php echo (int) INFIL_MAIN_MINS; ?>" min="1">
          <small>Worth +1. A day is long enough that anyone who checks in daily qualifies.</small>
        </label>
      </div>
      <button type="submit" name="add" value="1">Add target</button>
    </form>
  </div>

  <?php /* ---- targets ----------------------------------------------------- */ ?>
  <h4>Targets</h4>
  <?php if (!$ai_targets): ?>
    <p class="adm-note">None yet.</p>
  <?php endif; ?>
  <?php foreach ($ai_targets as $t):
    $posted = strtotime($t['posted_at']);
    $heat   = infil_heat($posted);
    $url    = 'https://x.com/skulliance/status/' . $t['status_id'];
  ?>
  <div class="adm-card">
    <div style="display:flex;flex-wrap:wrap;gap:14px;align-items:baseline;justify-content:space-between">
      <div>
        <b><?php echo htmlspecialchars($t['label'] !== '' ? $t['label'] : 'Target ' . $t['id']); ?></b>
        <?php if ((int) $t['retired']): ?><span class="bad-dot"> &middot; retired</span><?php endif; ?>
        <br><small><?php echo htmlspecialchars($t['posted_at']); ?>
          &middot; <a href="<?php echo htmlspecialchars($url); ?>" target="_blank" rel="noopener">open on X</a></small>
      </div>
      <div style="text-align:right">
        <b><?php echo (int) $t['verified']; ?></b> infiltrated
        <?php if ((int) $t['submissions'] > (int) $t['verified']): ?>
          <br><small class="warn"><?php echo (int) $t['submissions'] - (int) $t['verified']; ?> not verified</small>
        <?php endif; ?>
        <br><small>reach left <span class="adm-heat"><i style="width:<?php
          echo (int) round($heat * 100); ?>%"></i></span></small>
      </div>
    </div>
    <form method="post" class="adm-pick" style="margin-top:12px">
      <input type="hidden" name="target" value="<?php echo (int) $t['id']; ?>">
      <label style="flex-direction:row;align-items:center;gap:6px">first
        <input type="number" name="early" value="<?php echo (int) $t['early_mins']; ?>" min="1" style="width:84px"></label>
      <label style="flex-direction:row;align-items:center;gap:6px">main
        <input type="number" name="main" value="<?php echo (int) $t['main_mins']; ?>" min="1" style="width:84px"></label>
      <button type="submit" name="windows" value="1">Save windows</button>
    </form>
    <form method="post" style="margin-top:6px">
      <input type="hidden" name="target" value="<?php echo (int) $t['id']; ?>">
      <input type="hidden" name="retire" value="<?php echo (int) $t['retired'] ? 0 : 1; ?>">
      <button type="submit" name="retire" value="1" style="background:transparent;border:1px solid rgba(0,200,160,.2);color:#5a7888;font-weight:normal">
        <?php echo (int) $t['retired'] ? 'Reopen' : 'Retire'; ?>
      </button>
    </form>
  </div>
  <?php endforeach; ?>

  <?php /* ---- what came in ------------------------------------------------ */ ?>
  <h4>Recent submissions</h4>
  <small>The channel is where you read these as they land, and where you react to the ones
    you have answered. This is the record.</small>
  <div class="adm-tablewrap">
  <table class="adm-table">
    <tr><th>Member</th><th>@</th><th>Said</th><th>Won</th><th>When</th><th>State</th></tr>
    <?php foreach ($ai_recent as $s): ?>
    <tr>
      <td><?php echo htmlspecialchars($s['username']); ?></td>
      <td><?php if ($s['x_handle'] !== ''): ?><a href="<?php
          echo htmlspecialchars(infil_comment_url($s['x_handle'], $s['status_id'])); ?>"
          target="_blank" rel="noopener">@<?php echo htmlspecialchars($s['x_handle']); ?></a><?php endif; ?></td>
      <td class="adm-quote"><?php echo htmlspecialchars((string) $s['own_text']); ?></td>
      <td><?php echo htmlspecialchars((string) $s['trait_name']); ?>
        <?php if ($s['trait_tier'] !== ''): ?><small>(<?php echo htmlspecialchars($s['trait_tier']); ?>)</small><?php endif; ?></td>
      <td><small><?php echo htmlspecialchars((string) $s['speed']); ?></small></td>
      <td><?php
        if ($s['state'] === 'verified')      echo '<span class="ok-dot">verified</span>';
        else if ($s['state'] === 'pending')  echo '<span class="warn">waiting on X</span>';
        else                                 echo '<span class="bad-dot">refused</span>';
        if ((string) $s['why'] !== '') echo '<br><small>' . htmlspecialchars($s['why']) . '</small>';
      ?></td>
    </tr>
    <?php endforeach; ?>
  </table>
  </div>

  <?php endif; ?>
</div></div>
</html>
