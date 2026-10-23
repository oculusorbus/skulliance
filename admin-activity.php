<?php
/**
 * admin-activity.php -- who is actually doing things, at a glance.
 *
 * TWO READINGS OF THE SAME DATA. A ranked list of bars answers "who is
 * carrying the platform" in one scan, and a wall of cards underneath
 * answers "what is going on" -- the same split the daily digest makes
 * between a headline number and the tiles behind it.
 *
 * THE DEFINITION OF ACTIVITY IS NOT HERE. It is in activity-sources-lib.php,
 * shared with the public Activity leaderboard, because this page is the
 * FOURTH thing that wanted it -- the other three are db.php and the two
 * analytics endpoints, and skullpaper/MAINTENANCE.md already records how
 * they drifted. Weights and queries are read, never restated.
 *
 * THE WINDOW IS IN DAYS, which the leaderboard's periods are not: it
 * offers all-time, calendar month and calendar week, and "this month" on
 * the 1st is a different question from "the last 30 days". Rivalries
 * already uses rolling 7/30/90 and this matches it.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/avatar-lib.php';
require_once __DIR__ . '/activity-sources-lib.php';

/* The window, in the query string so a view can be linked or bookmarked.
   Anything else falls back to 30 rather than being trusted into a date. */
$ac_days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
if (!in_array($ac_days, array(7, 30, 90), true)) $ac_days = 30;

$ac_rows   = activity_totals($conn, $ac_days);
$ac_labels = activity_labels();
$ac_max    = 0;
foreach ($ac_rows as $r) if ($r['total'] > $ac_max) $ac_max = $r['total'];

$ac_people  = count($ac_rows);
$ac_actions = 0;
$ac_points  = 0;
foreach ($ac_rows as $r) {
	$ac_points += $r['total'];
	foreach ($r['by'] as $n) $ac_actions += $n;
}

include 'header.php';
admin_chrome('activity');
?>
<style>
/* Scoped to .aca so nothing here can reach the rest of the admin. */
.aca-tools { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin:0 0 16px; }
.aca-range { display:flex; gap:0; }
.aca-range a { padding:7px 14px; font-size:.76rem; letter-spacing:.07em; text-transform:uppercase;
  color:#8fa8b8; border:1px solid rgba(255,255,255,.12); text-decoration:none; }
.aca-range a + a { border-left:0; }
.aca-range a.on { color:#06222b; background:#00c8a0; border-color:#00c8a0; }
.aca-range a:hover:not(.on) { color:#e8eaed; border-color:#00c8a0; }
.aca-sum { font-size:.78rem; color:#8fa8b8; }
.aca-sum b { color:#00c8a0; }

/* ---- the ranked bars ---- */
.aca-rows { display:flex; flex-direction:column; gap:2px; margin:0 0 30px; }
.aca-row { display:grid; grid-template-columns:28px 34px minmax(0,1fr) auto; align-items:center;
  gap:10px; padding:7px 10px; background:#07111d; border:1px solid rgba(255,255,255,.05); }
.aca-row:hover { border-color:rgba(0,200,160,.35); }
.aca-rank { font-size:.72rem; color:#4f7488; text-align:right; font-variant-numeric:tabular-nums; }
.aca-face { width:34px; height:34px; border-radius:50%; object-fit:cover; display:block;
  background:#0d2030; }
.aca-who { min-width:0; }
.aca-name { display:block; font-size:.82rem; color:#e8eaed; overflow:hidden;
  text-overflow:ellipsis; white-space:nowrap; }
/* THE BAR IS THE POINT OF THE ROW, so it gets the width and the name sits
   above it rather than beside it in a fixed column that truncates. */
.aca-bar { display:block; height:7px; margin-top:5px; background:rgba(255,255,255,.06); }
.aca-bar i { display:block; height:100%; background:#00c8a0; }
.aca-pts { font-size:.82rem; color:#00c8a0; font-variant-numeric:tabular-nums; white-space:nowrap; }
.aca-pts em { display:block; font-style:normal; font-size:.68rem; color:#4f7488; text-align:right; }

/* ---- the wall ---- */
.aca-wall { display:grid; grid-template-columns:repeat(auto-fill,minmax(210px,1fr)); gap:10px; }
.aca-card { background:#07111d; border:1px solid rgba(255,255,255,.06); padding:12px; }
.aca-card-head { display:flex; align-items:center; gap:9px; margin-bottom:9px; }
.aca-card-head img { width:30px; height:30px; border-radius:50%; object-fit:cover; background:#0d2030; }
.aca-card-head b { font-size:.8rem; color:#e8eaed; overflow:hidden; text-overflow:ellipsis;
  white-space:nowrap; min-width:0; }
.aca-card-head span { margin-left:auto; font-size:.76rem; color:#00c8a0;
  font-variant-numeric:tabular-nums; }
.aca-bits { display:flex; flex-direction:column; gap:3px; }
.aca-bit { display:grid; grid-template-columns:minmax(0,1fr) auto; gap:8px; font-size:.7rem;
  color:#8fa8b8; }
.aca-bit em { font-style:normal; color:#5f7d90; font-variant-numeric:tabular-nums; }
.aca-none { color:#8fa8b8; font-size:.82rem; }
@media (max-width:620px) {
  .aca-row { grid-template-columns:22px 28px minmax(0,1fr) auto; gap:8px; }
  .aca-face { width:28px; height:28px; }
}
</style>

<div class="aca">
	<div class="aca-tools">
		<?php /* Rolling windows, not calendar periods -- see the file header. */ ?>
		<div class="aca-range">
			<?php foreach (array(7, 30, 90) as $d): ?>
			<a href="admin-activity.php?days=<?php echo $d; ?>"
			   class="<?php echo $d === $ac_days ? 'on' : ''; ?>"><?php echo $d; ?> days</a>
			<?php endforeach; ?>
		</div>
		<span class="aca-sum"><b><?php echo number_format($ac_people); ?></b> active ·
			<b><?php echo number_format($ac_actions); ?></b> actions ·
			<b><?php echo number_format($ac_points); ?></b> weighted</span>
	</div>

<?php if (!$ac_rows): ?>
	<p class="aca-none">No activity recorded in the last <?php echo $ac_days; ?> days.</p>
<?php else: ?>
	<div class="aca-rows">
		<?php foreach ($ac_rows as $i => $r):
			/* Against the leader, so the top bar is always full and the rest
			   are read relative to it -- an absolute scale would make every
			   bar a sliver the week somebody has a big run. */
			$pct = $ac_max > 0 ? max(1, round(($r['total'] / $ac_max) * 100)) : 0; ?>
		<div class="aca-row">
			<span class="aca-rank"><?php echo $i + 1; ?></span>
			<img class="aca-face" loading="lazy" alt=""
			     src="<?php echo htmlspecialchars(avatar_url($r['discord_id'], $r['avatar'], 64), ENT_QUOTES); ?>"
			     onerror="this.src='icons/skull.png'">
			<span class="aca-who">
				<span class="aca-name"><?php echo htmlspecialchars($r['username'], ENT_QUOTES); ?></span>
				<span class="aca-bar"><i style="width:<?php echo $pct; ?>%"></i></span>
			</span>
			<span class="aca-pts"><?php echo number_format($r['total']); ?><em>weighted</em></span>
		</div>
		<?php endforeach; ?>
	</div>

	<?php /* THE WALL. Same people, same order, but what they actually did --
	         the ranked list answers who, this answers what. Only the sources
	         a player has touched are listed, so a card is as short as that
	         player's week and the wall stays scannable. */ ?>
	<div class="aca-wall">
		<?php foreach ($ac_rows as $r): ?>
		<div class="aca-card">
			<div class="aca-card-head">
				<img loading="lazy" alt=""
				     src="<?php echo htmlspecialchars(avatar_url($r['discord_id'], $r['avatar'], 64), ENT_QUOTES); ?>"
				     onerror="this.src='icons/skull.png'">
				<b><?php echo htmlspecialchars($r['username'], ENT_QUOTES); ?></b>
				<span><?php echo number_format($r['total']); ?></span>
			</div>
			<div class="aca-bits">
				<?php
				$bits = $r['by'];
				arsort($bits);
				$shown = 0;
				foreach ($bits as $k => $n) {
					if ($n <= 0) continue;
					$shown++;
					echo '<span class="aca-bit"><span>'
					   . htmlspecialchars(isset($ac_labels[$k]) ? $ac_labels[$k] : $k, ENT_QUOTES)
					   . '</span><em>' . number_format($n) . '</em></span>';
				}
				if (!$shown) echo '<span class="aca-bit"><span>Nothing in this window</span></span>';
				?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
</div>

<?php /* admin_chrome() opens the .adm wrapper but does not carry its
         styling, so without this the page renders as unstyled text against
         the left edge -- the same note admin-collage.php ends on. */ ?>
<?php include 'admin-css.php'; ?>
