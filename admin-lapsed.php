<?php
/**
 * admin-lapsed.php — who has stopped coming, warmest first.
 *
 * THE ORDER IS THE POINT. Somebody who lapsed six weeks ago still
 * remembers their password, their realm and what they were in the
 * middle of; somebody gone two years is a cold start. So the default
 * is LEAST inactive first, because that is the order you would work
 * down a list you intend to actually message.
 *
 * AND WHAT THEY WOULD BE COMING BACK TO. A list of names and dates
 * tells you who to contact and nothing about what to say. The columns
 * here are the reason to write: what they used to play, the NFTs still
 * sitting in their account, the CARBON they never spent, whether their
 * realm is still standing. "Your realm is still there" is a message; a
 * name and a date is a mail merge.
 *
 * READ ONLY. Nothing here writes, nothing is marked contacted, nothing
 * is emailed. It is a list to work from.
 *
 * last_login IS TRUSTWORTHY -- skulliance.php writes it at most once a
 * day on any signed-in page load, so it means last seen rather than
 * last Discord handshake.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/avatar-lib.php';
require_once __DIR__ . '/activity-sources-lib.php';

/* Both in the query string so a view can be linked or bookmarked. */
$al_days = isset($_GET['days']) ? (int)$_GET['days'] : 90;
if (!in_array($al_days, array(30, 90, 180, 365), true)) $al_days = 90;
$al_sort = (isset($_GET['sort']) && $_GET['sort'] === 'coldest') ? 'coldest' : 'warmest';

/*
 * ONE PASS EACH, not one query per player. Four hundred members and a
 * dozen things worth knowing about each is a page that takes a minute
 * if you ask per row.
 */
$al_rows = array();
$al_sql = "SELECT id, username, discord_id, avatar, last_login, date_created
             FROM users
            WHERE discord_id IS NOT NULL AND discord_id <> ''
              AND last_login IS NOT NULL
              AND last_login < NOW() - INTERVAL " . (int)$al_days . " DAY
            ORDER BY last_login " . ($al_sort === 'coldest' ? 'ASC' : 'DESC');
$r = @$conn->query($al_sql);
if ($r) while ($row = $r->fetch_assoc()) $al_rows[(int)$row['id']] = $row;

/* What is still sitting in the account. */
$al_nfts = $al_carbon = $al_realm = array();
if ($al_rows) {
	$ids = implode(',', array_map('intval', array_keys($al_rows)));
	$q = @$conn->query("SELECT user_id, COUNT(*) AS n FROM nfts WHERE user_id IN ($ids) GROUP BY user_id");
	if ($q) while ($x = $q->fetch_assoc()) $al_nfts[(int)$x['user_id']] = (int)$x['n'];
	/* Project 15 is CARBON, the platform-wide currency the games pay. */
	$q = @$conn->query("SELECT user_id, balance FROM balances WHERE project_id = 15 AND user_id IN ($ids)");
	if ($q) while ($x = $q->fetch_assoc()) $al_carbon[(int)$x['user_id']] = (float)$x['balance'];
	$q = @$conn->query("SELECT DISTINCT user_id FROM realms WHERE user_id IN ($ids)");
	if ($q) while ($x = $q->fetch_assoc()) $al_realm[(int)$x['user_id']] = true;
}

/*
 * WHAT THEY USED TO DO, all time rather than recent -- by definition
 * these people have done nothing recently, so a windowed view would
 * report zero for everybody and tell you nothing about who they were.
 */
$al_did = array();
foreach (activity_totals($conn, 36500) as $a) {
	$al_did[(int)$a['user_id']] = $a;
}
$al_labels = activity_labels();

include 'header.php';
admin_chrome('lapsed');
?>
<?php include 'admin-css.php'; ?>
<style>
.lap-sum { font-size:.8rem; color:#8fa8b8; margin:0 0 14px; }
.lap-sum b { color:#00c8a0; }
.lap-tools { display:flex; gap:16px; flex-wrap:wrap; margin:0 0 16px; align-items:center; }
.lap-range { display:flex; gap:0; }
.lap-range a { padding:7px 14px; font-size:.76rem; letter-spacing:.07em; text-transform:uppercase;
  color:#8fa8b8; border:1px solid rgba(255,255,255,.12); text-decoration:none; }
.lap-range a + a { border-left:0; }
.lap-range a.on { color:#06222b; background:#00c8a0; border-color:#00c8a0; }
.lap-t { width:100%; border-collapse:collapse; font-size:.82rem; }
.lap-t th { text-align:left; font-size:.68rem; letter-spacing:.09em; text-transform:uppercase;
  color:#5f7d90; padding:0 10px 7px; font-weight:normal; }
.lap-t td { padding:9px 10px; border-top:1px solid rgba(255,255,255,.06); vertical-align:top; }
.lap-who { display:flex; align-items:center; gap:8px; }
.lap-who img { width:26px; height:26px; border-radius:50%; object-fit:cover; background:#0d2030; }
.lap-gone { font-variant-numeric:tabular-nums; white-space:nowrap; }
.lap-gone b { color:#e8eaed; }
.lap-gone span { display:block; font-size:.7rem; color:#5f7d90; }
.lap-did { font-size:.72rem; color:#5f7d90; max-width:320px; }
.lap-has { font-size:.74rem; white-space:nowrap; }
.lap-has b { color:#00c8a0; font-variant-numeric:tabular-nums; }
.lap-has .none { color:#4f7488; }
.lap-warm { color:#d69e2e; font-weight:bold; }
</style>

<p class="lap-sum">
	<b><?php echo number_format(count($al_rows)); ?></b> members have not signed in for
	<?php echo (int)$al_days; ?> days or more.
	<?php
	$al_withstuff = 0;
	foreach ($al_rows as $uid => $u) {
		if (!empty($al_nfts[$uid]) || !empty($al_realm[$uid]) || !empty($al_carbon[$uid])) $al_withstuff++;
	}
	?>
	<b><?php echo number_format($al_withstuff); ?></b> of them still have something waiting.
</p>

<div class="lap-tools">
	<div class="lap-range">
		<?php foreach (array(30, 90, 180, 365) as $d): ?>
		<a href="admin-lapsed.php?days=<?php echo $d; ?>&amp;sort=<?php echo $al_sort; ?>"
		   class="<?php echo $d === $al_days ? 'on' : ''; ?>"><?php echo $d; ?>+ days</a>
		<?php endforeach; ?>
	</div>
	<div class="lap-range">
		<?php /* WARMEST FIRST IS THE DEFAULT, because that is the order you
		         would work the list in. Coldest is there for the opposite
		         job: deciding who is never coming back. */ ?>
		<a href="admin-lapsed.php?days=<?php echo $al_days; ?>&amp;sort=warmest"
		   class="<?php echo $al_sort === 'warmest' ? 'on' : ''; ?>">Warmest first</a>
		<a href="admin-lapsed.php?days=<?php echo $al_days; ?>&amp;sort=coldest"
		   class="<?php echo $al_sort === 'coldest' ? 'on' : ''; ?>">Coldest first</a>
	</div>
</div>

<?php if (!$al_rows): ?>
<p style="color:#8fa8b8">Nobody has been away that long.</p>
<?php else: ?>
<table class="lap-t">
	<thead><tr>
		<th>Member</th><th>Last seen</th><th>What they played</th>
		<th>Still waiting</th>
	</tr></thead>
	<tbody>
	<?php foreach ($al_rows as $uid => $u):
		$gone = (int)floor((time() - strtotime($u['last_login'])) / 86400);
		$did  = isset($al_did[$uid]) ? $al_did[$uid] : null;
		$bits = array();
		if ($did) {
			$by = $did['by']; arsort($by);
			foreach ($by as $k => $n) {
				if ((int)$n <= 0) continue;
				$bits[] = (isset($al_labels[$k]) ? $al_labels[$k] : $k) . ' ' . number_format($n);
				if (count($bits) === 4) break;
			}
		}
		$has = array();
		if (!empty($al_nfts[$uid]))   $has[] = '<b>' . number_format($al_nfts[$uid]) . '</b> NFTs';
		if (!empty($al_realm[$uid]))  $has[] = 'a realm';
		if (!empty($al_carbon[$uid])) $has[] = '<b>' . number_format($al_carbon[$uid]) . '</b> CARBON';
	?>
	<tr>
		<td>
			<div class="lap-who">
				<img loading="lazy" alt=""
				     src="<?php echo htmlspecialchars(avatar_url($u['discord_id'], $u['avatar'], 64), ENT_QUOTES); ?>"
				     onerror="this.src='icons/skull.png'">
				<?php /* THE ID IS ON HOVER, NOT IN A COLUMN. A column of raw
				         snowflakes is noise -- you find people by name in
				         Discord. But a display name can change and the id
				         cannot, so it stays reachable for the one case where
				         the name no longer finds them. */ ?>
				<span title="<?php echo htmlspecialchars($u['discord_id'], ENT_QUOTES); ?>"><?php
					echo htmlspecialchars($u['username'], ENT_QUOTES); ?></span>
			</div>
		</td>
		<td class="lap-gone">
			<b class="<?php echo $gone < 180 ? 'lap-warm' : ''; ?>"><?php echo number_format($gone); ?> days</b>
			<span><?php echo htmlspecialchars(date('j M Y', strtotime($u['last_login']))); ?></span>
		</td>
		<td class="lap-did"><?php
			echo $bits ? htmlspecialchars(implode(' · ', $bits), ENT_QUOTES)
			           : '<span style="color:#4f7488">never played anything</span>';
		?></td>
		<td class="lap-has"><?php
			echo $has ? implode(' · ', $has) : '<span class="none">nothing</span>';
		?></td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>
</div></div>
