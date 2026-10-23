<?php
/**
 * admin-nudges.php — who the daily nudge is talking to, and what it says.
 *
 * WHY THIS EXISTS. The owner cannot see this feature. It only speaks to
 * accounts touching four or fewer of the platform's fifteen things, and
 * his own touches all fifteen -- so the one person who needs to judge
 * whether the suggestions are any good is the one account guaranteed
 * never to receive one. Shipping it without this meant "trust me".
 *
 * It also answers the question the feature is FOR: who is running one
 * loop, and what would we say to them. That is the targeting list for
 * the campaign, not just a debug view.
 *
 * READ ONLY, AND IT MUST STAY THAT WAY. Nothing here logs a 'shown' row.
 * If it did, opening this page would consume every targeted player's next
 * nudge and reset their three-day gap -- the admin looking at the list
 * would silence the whole list.
 *
 * THE NUMBERS ARE REAL, which is the point: the breadth, the suggestion
 * and the reason are produced by the same functions the Launchpad calls,
 * not re-derived here. A preview that agrees with itself and not with the
 * page proves nothing.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/avatar-lib.php';
require_once __DIR__ . '/lib/nudge-lib.php';

/* The window is in the query string so a view can be linked. */
$an_days = isset($_GET['days']) ? (int)$_GET['days'] : NUDGE_WINDOW_DAYS;
if ($an_days < 7 || $an_days > 365) $an_days = NUDGE_WINDOW_DAYS;

/*
 * ONE PASS OVER THE FIFTEEN SOURCES FOR EVERYBODY, not fifteen queries
 * per player. activity_totals() already does exactly this for the
 * Activity page; reusing it keeps one definition of a play and makes
 * this page one round of queries however many members there are.
 */
$an_rows  = activity_totals($conn, $an_days);
$an_table = nudge_table($conn);

/* Realms and Fighters in one query each, for the same reason. */
$an_realms = array();
$r = @$conn->query("SELECT DISTINCT user_id FROM realms");
if ($r) while ($row = $r->fetch_assoc()) $an_realms[(int)$row['user_id']] = true;

/*
 * Spare traits are the strongest suggestion we can make, so the preview
 * has to show them -- but dhcf_available() is a per-player call and
 * running it for every member would be hundreds of queries. Only the
 * players who actually qualify are asked, which is a handful.
 */
$an_list = array();
foreach ($an_rows as $row) {
	$uid = (int)$row['user_id'];
	$fp  = array();
	foreach ($row['by'] as $k => $n) if ((int)$n > 0) $fp[$k] = (int)$n;
	$breadth = nudge_breadth($fp);
	$narrow  = ($breadth <= NUDGE_NARROW_MAX);

	$facts = array(
		'has_realm'   => isset($an_realms[$uid]),
		'fighters'    => isset($fp['fighter']) ? $fp['fighter'] : 0,
		'free_traits' => 0,
	);
	$pick = null;
	if ($narrow) {
		if (function_exists('dhcf_available')) {
			foreach (dhcf_available($conn, $uid) as $cat => $traits) {
				foreach ($traits as $t) $facts['free_traits'] += (int)$t['free'];
			}
		}
		$pick = nudge_pick(nudge_candidates($fp, $facts), nudge_history($conn, $uid));
	}
	$state = $an_table ? nudge_state($conn, $uid) : array('snoozed'=>false,'resting'=>false,'recent'=>false,'shown'=>0,'clicked'=>0);
	$an_list[] = array(
		'row' => $row, 'fp' => $fp, 'breadth' => $breadth, 'narrow' => $narrow,
		'facts' => $facts, 'pick' => $pick, 'state' => $state,
	);
}
/* Narrowest first: that is the targeting order. */
usort($an_list, function ($a, $b) {
	if ($a['narrow'] !== $b['narrow']) return $a['narrow'] ? -1 : 1;
	if ($a['breadth'] !== $b['breadth']) return $a['breadth'] - $b['breadth'];
	return $b['row']['total'] - $a['row']['total'];
});

$an_targets = 0; $an_shown = 0; $an_clicked = 0;
foreach ($an_list as $i) {
	if ($i['narrow']) $an_targets++;
	$an_shown   += (int)$i['state']['shown'];
	$an_clicked += (int)$i['state']['clicked'];
}

include 'header.php';
admin_chrome('nudges');
?>
<?php /* The .adm wrapper admin_chrome() just opened has no styling of its
         own -- every admin page loads it from here. Caught by
         digest-harness.php, which sweeps for exactly this. */ ?>
<?php include 'admin-css.php'; ?>
<style>
.ndg-sum { font-size:.8rem; color:#8fa8b8; margin:0 0 14px; }
.ndg-sum b { color:#00c8a0; }
.ndg-warn { font-size:.8rem; color:#d69e2e; margin:0 0 14px; }
.ndg-range { display:flex; gap:0; margin:0 0 16px; }
.ndg-range a { padding:7px 14px; font-size:.76rem; letter-spacing:.07em; text-transform:uppercase;
  color:#8fa8b8; border:1px solid rgba(255,255,255,.12); text-decoration:none; }
.ndg-range a + a { border-left:0; }
.ndg-range a.on { color:#06222b; background:#00c8a0; border-color:#00c8a0; }
.ndg-t { width:100%; border-collapse:collapse; font-size:.82rem; }
.ndg-t th { text-align:left; font-size:.68rem; letter-spacing:.09em; text-transform:uppercase;
  color:#5f7d90; padding:0 10px 7px; font-weight:normal; }
.ndg-t td { padding:9px 10px; border-top:1px solid rgba(255,255,255,.06); vertical-align:top; }
.ndg-t tr.off td { opacity:.42; }
.ndg-who { display:flex; align-items:center; gap:8px; }
.ndg-who img { width:26px; height:26px; border-radius:50%; object-fit:cover; background:#0d2030; }
.ndg-b { font-variant-numeric:tabular-nums; }
.ndg-b.hit { color:#d69e2e; font-weight:bold; }
.ndg-does { font-size:.72rem; color:#5f7d90; }
.ndg-pick b { color:#e8eaed; }
.ndg-pick span { display:block; font-size:.72rem; color:#7a9eb0; margin-top:2px; }
.ndg-none { color:#4f7488; }
.ndg-tag { display:inline-block; font-size:.64rem; letter-spacing:.06em; text-transform:uppercase;
  padding:2px 6px; border:1px solid rgba(255,255,255,.16); color:#8fa8b8; }
</style>

<?php if (!$an_table): ?>
<p class="ndg-warn"><strong>The <code>nudges</code> table does not exist, so the feature is switched
off.</strong> Everything below is what it <em>would</em> say.</p>
<?php endif; ?>

<p class="ndg-sum">
	<b><?php echo number_format($an_targets); ?></b> of <b><?php echo number_format(count($an_list)); ?></b>
	active players touch <?php echo (int)NUDGE_NARROW_MAX; ?> or fewer of the
	<?php echo count(activity_labels()); ?> things here and would be nudged ·
	<b><?php echo number_format($an_shown); ?></b> shown and
	<b><?php echo number_format($an_clicked); ?></b> clicked in the last
	<?php echo (int)NUDGE_FATIGUE_DAYS; ?> days ·
	one every <?php echo (int)NUDGE_GAP_DAYS; ?> days
</p>

<div class="ndg-range">
	<?php foreach (array(30, 60, 90, 180) as $d): ?>
	<a href="admin-nudges.php?days=<?php echo $d; ?>"
	   class="<?php echo $d === $an_days ? 'on' : ''; ?>"><?php echo $d; ?> days</a>
	<?php endforeach; ?>
</div>

<table class="ndg-t">
	<thead><tr>
		<th>Player</th><th>Breadth</th><th>What they do</th><th>What we would say</th><th>State</th>
	</tr></thead>
	<tbody>
	<?php
	$labels = activity_labels();
	foreach ($an_list as $i):
		$row = $i['row'];
		$does = array();
		arsort($i['fp']);
		foreach ($i['fp'] as $k => $n) $does[] = (isset($labels[$k]) ? $labels[$k] : $k) . ' ' . number_format($n);
	?>
	<tr class="<?php echo $i['narrow'] ? '' : 'off'; ?>">
		<td>
			<div class="ndg-who">
				<img loading="lazy" alt=""
				     src="<?php echo htmlspecialchars(avatar_url($row['discord_id'], $row['avatar'], 64), ENT_QUOTES); ?>"
				     onerror="this.src='icons/skull.png'">
				<span><?php echo htmlspecialchars($row['username'], ENT_QUOTES); ?></span>
			</div>
		</td>
		<td class="ndg-b <?php echo $i['narrow'] ? 'hit' : ''; ?>"><?php echo (int)$i['breadth']; ?></td>
		<td class="ndg-does"><?php echo htmlspecialchars(implode(' · ', array_slice($does, 0, 6)), ENT_QUOTES); ?></td>
		<td class="ndg-pick">
			<?php if (!$i['narrow']): ?>
				<span class="ndg-none">Broad already, left alone</span>
			<?php elseif ($i['pick']): ?>
				<b><?php echo htmlspecialchars($i['pick']['title'], ENT_QUOTES); ?></b>
				<span><?php echo htmlspecialchars($i['pick']['why'], ENT_QUOTES); ?></span>
			<?php else: ?>
				<span class="ndg-none">Nothing left to suggest</span>
			<?php endif; ?>
		</td>
		<td>
			<?php
			$s = $i['state'];
			if ($s['snoozed'])      echo '<span class="ndg-tag">snoozed</span>';
			elseif ($s['resting'])  echo '<span class="ndg-tag">resting</span>';
			elseif ($s['recent'])   echo '<span class="ndg-tag">recent</span>';
			elseif ($i['narrow'])   echo '<span class="ndg-tag">due</span>';
			if ($s['shown'])
				echo ' <span class="ndg-does">' . (int)$s['shown'] . ' shown, ' . (int)$s['clicked'] . ' clicked</span>';
			?>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>
</div></div>
