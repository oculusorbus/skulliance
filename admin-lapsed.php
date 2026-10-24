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
/* For the gift column. Pulls in dhcfighters-lib.php, which is fine on an
   admin page nobody loads in a loop. */
require_once __DIR__ . '/lib/return-gift.php';

/*
 * TWO VIEWS OFF ONE PAGE. "Who should I write to" and "did writing to
 * them work" are the same campaign seen from either end, and splitting
 * them across two admin tabs would mean checking two places to answer
 * one question.
 */
$al_view = (isset($_GET['view']) && $_GET['view'] === 'back') ? 'back' : 'lapsed';

/*
 * WHO HAS COME BACK. The welcome-back package is the receipt: it is
 * granted once per lapse on a sign-in after a month away, so a
 * 'returngift' row IS a returning member, timestamped. Nothing else
 * had to be recorded to measure this.
 */
$al_back = array();
$q = @$conn->query("SELECT user_id, COUNT(*) AS traits, MIN(awarded_at) AS back_at
                      FROM dhc_trait_drops WHERE source = 'returngift'
                     GROUP BY user_id ORDER BY back_at DESC");
if ($q) while ($x = $q->fetch_assoc()) $al_back[(int)$x['user_id']] = $x;

$al_bu = $al_built = array();
if ($al_back) {
	$bids = implode(',', array_map('intval', array_keys($al_back)));
	$q = @$conn->query("SELECT id, username, discord_id, avatar, last_login
	                      FROM users WHERE id IN ($bids)");
	if ($q) while ($x = $q->fetch_assoc()) $al_bu[(int)$x['id']] = $x;
	/*
	 * DID THEY BUILD ANYTHING WITH IT. This is the measure that matters:
	 * the package exists so somebody can assemble a Fighter the moment
	 * they arrive, so a Fighter dated after the package is the gift
	 * doing its job. Signing in and leaving again is not a win.
	 *
	 * Counted against each member's OWN return date in one query rather
	 * than one per member.
	 */
	$q = @$conn->query(
		"SELECT f.user_id, COUNT(*) AS n
		   FROM dhc_fighters f
		   JOIN (SELECT user_id, MIN(awarded_at) AS back_at FROM dhc_trait_drops
		          WHERE source = 'returngift' GROUP BY user_id) g ON g.user_id = f.user_id
		  WHERE f.created_at >= g.back_at
		  GROUP BY f.user_id");
	if ($q) while ($x = $q->fetch_assoc()) $al_built[(int)$x['user_id']] = (int)$x['n'];
}

/* Headline numbers, counted here so the strip reads the same on both views. */
$al_n_back = count($al_back);
$al_n_week = $al_n_built = $al_n_again = 0;
foreach ($al_back as $uid => $b) {
	$t = strtotime($b['back_at']);
	if ($t && $t > time() - 7 * 86400) $al_n_week++;
	if (!empty($al_built[$uid]))       $al_n_built++;
	/* last_login is written at most once a day, so "came back on a later
	   day" is the honest reading of a login newer than the package. */
	if (isset($al_bu[$uid]) && $t && strtotime($al_bu[$uid]['last_login']) > $t + 86400) $al_n_again++;
}

/* Both in the query string so a view can be linked or bookmarked. */
$al_days = isset($_GET['days']) ? (int)$_GET['days'] : 90;
if (!in_array($al_days, array(30, 90, 180, 365), true)) $al_days = 90;
/*
 * FOUR WAYS TO WORK THE LIST, because "who should I message" has more
 * than one right answer.
 *
 *   warmest  least time away -- still remembers their password
 *   coldest  longest away -- deciding who is never coming back
 *   active   did the most while they were here. Somebody with two
 *            thousand missions behind them has a reason to look at
 *            what has been built since; somebody who played twice
 *            does not.
 *   quiet    barely engaged, for working out what put them off
 */
$al_sorts = array('warmest', 'coldest', 'active', 'quiet');
$al_sort  = (isset($_GET['sort']) && in_array($_GET['sort'], $al_sorts, true))
          ? $_GET['sort'] : 'warmest';

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

/*
 * THE ACTIVITY SORTS HAPPEN HERE, NOT IN SQL, because the number being
 * sorted on does not exist in the database -- it is the weighted total
 * across fifteen sources, assembled a few lines up. Four hundred rows
 * in PHP is nothing; a sort key that disagrees with the column beside
 * it would be worse than slow.
 *
 * The recency sorts stay in SQL, where last_login already lives.
 */
if ($al_sort === 'active' || $al_sort === 'quiet') {
	$al_dir = ($al_sort === 'active') ? -1 : 1;
	uasort($al_rows, function ($a, $b) use ($al_did, $al_dir) {
		$ta = isset($al_did[(int)$a['id']]) ? (int)$al_did[(int)$a['id']]['total'] : 0;
		$tb = isset($al_did[(int)$b['id']]) ? (int)$al_did[(int)$b['id']]['total'] : 0;
		if ($ta === $tb) {
			/* Ties broken by recency, so the second axis is still useful. */
			return strcmp((string)$b['last_login'], (string)$a['last_login']);
		}
		return ($ta < $tb ? -1 : 1) * $al_dir;
	});
}

include 'header.php';
admin_chrome('lapsed');
?>
<?php include 'admin-css.php'; ?>
<style>
/* The scoreboard. Four numbers, not a chart: the question is "is this
   working", and a sparkline over two data points would be decoration. */
.lap-score { display:flex; gap:28px; flex-wrap:wrap; margin:0 0 16px; }
.lap-score div { display:flex; flex-direction:column; }
.lap-score b { font-size:1.5rem; color:#e8eaed; font-variant-numeric:tabular-nums; line-height:1.1; }
.lap-score b.lap-good { color:#00c8a0; }
.lap-score span { font-size:.68rem; letter-spacing:.08em; text-transform:uppercase; color:#5f7d90; margin-top:3px; }
.lap-views { margin:0 0 16px; }
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
/* The gift thumbnail. The art is transparent PNG over nothing, so it
   needs its own ground or it reads as a hole in the row. flexbox.css
   centres and teals every bare <a>, which would drag the picture off
   its cell -- block display pins it back. */
.lap-gift { width:60px; }
.lap-gift a { display:block; width:52px; line-height:0; }
/* No frame of its own either: the art already fills the square, so a
   background only shows as a rim when the aspect is not exactly 1:1. */
td.lap-gift img { width:52px; height:52px; object-fit:cover; display:block;
                  border:0; background:none; border-radius:3px; }
.lap-warm { color:#d69e2e; font-weight:bold; }

/* ---- the package modal ---------------------------------------- *
   Its own element rather than a link to the PNG, because the job is
   not "look at a picture" -- it is read the traits, copy the link,
   write the DM. All three in one place.                            */
/* NEUTRALISED EXPLICITLY, NOT LEFT TO THE DEFAULTS. A <button> carries
   a background, a border and padding from the UA stylesheet before any
   of this platform's five stylesheets get a say, and the owner saw a
   bright teal frame around every thumbnail that none of them reproduce
   locally. So every property that can draw a box is set to nothing
   here, and the selector is specific enough to outrank a bare
   `button` rule from anywhere. */
td.lap-gift button.lap-giftbtn {
  display:block; padding:0; margin:0; border:0; outline:0;
  background:none; background-color:transparent; background-image:none;
  box-shadow:none; border-radius:0; -webkit-appearance:none; appearance:none;
  cursor:zoom-in; line-height:0; font:inherit; color:inherit; width:auto;
}
td.lap-gift button.lap-giftbtn:focus-visible { outline:2px solid #00c8a0; outline-offset:2px; }
.lap-mw { position:fixed; inset:0; z-index:9000; display:flex; align-items:center;
  justify-content:center; padding:20px; background:rgba(3,10,16,.86); }
.lap-mw[hidden] { display:none !important; }
.lap-mb { background:#0b1b26; border:1px solid rgba(255,255,255,.14); border-radius:8px;
  max-width:720px; width:100%; max-height:92vh; overflow:auto; padding:18px; }
.lap-mh { display:flex; align-items:baseline; justify-content:space-between; gap:12px; margin:0 0 12px; }
.lap-mh h3 { margin:0; font-size:1rem; color:#e8eaed; }
.lap-mx { background:none; border:0; color:#8fa8b8; font-size:1.4rem; line-height:1; cursor:pointer; padding:0 4px; }
.lap-mg { display:flex; gap:18px; flex-wrap:wrap; }
.lap-mg img { width:300px; max-width:100%; border-radius:6px; background:#0d2030; display:block; }
.lap-mi { flex:1; min-width:230px; }
.lap-mt { font-size:.78rem; color:#8fa8b8; line-height:1.7; margin:0 0 14px; white-space:pre-line; }
.lap-ml { font-size:.68rem; letter-spacing:.09em; text-transform:uppercase; color:#5f7d90;
  display:block; margin:0 0 5px; }
.lap-mu { width:100%; font-size:.72rem; padding:7px 8px; color:#e8eaed; background:#06151e;
  border:1px solid rgba(255,255,255,.14); border-radius:4px; font-family:monospace; }
.lap-mc { margin-top:9px; padding:8px 15px; font-size:.74rem; letter-spacing:.06em;
  text-transform:uppercase; color:#06222b; background:#00c8a0; border:0; border-radius:4px; cursor:pointer; }
@media (max-width:560px) { .lap-mg img { width:100%; } }
</style>

<?php /* THE SCOREBOARD, on both views, because the reason to work the
         lapsed list is sitting in these four numbers. "Built a Fighter"
         is the one that means the campaign worked: the package exists so
         somebody can assemble one on arrival, and a sign-in that leads
         nowhere is not a win. */ ?>
<div class="lap-score">
	<?php /* THE LOGIN IS THE WIN. Somebody signing in after a personal
	         invite is the campaign working, whether or not they go on to
	         build anything, so this is the number that gets the colour. */ ?>
	<div><b class="<?php echo $al_n_back ? 'lap-good' : ''; ?>"><?php
		echo number_format($al_n_back); ?></b><span>came back</span></div>
	<div><b><?php echo number_format($al_n_week); ?></b><span>in the last 7 days</span></div>
	<div><b><?php echo number_format($al_n_built); ?></b><span>built a Fighter</span></div>
	<div><b><?php echo number_format($al_n_again); ?></b><span>came back again</span></div>
</div>

<div class="lap-range lap-views">
	<a href="admin-lapsed.php?days=<?php echo $al_days; ?>&amp;sort=<?php echo $al_sort; ?>"
	   class="<?php echo $al_view === 'lapsed' ? 'on' : ''; ?>">Still away</a>
	<a href="admin-lapsed.php?view=back" class="<?php
	   echo $al_view === 'back' ? 'on' : ''; ?>">Came back<?php
	   echo $al_n_back ? ' (' . number_format($al_n_back) . ')' : ''; ?></a>
</div>

<?php if ($al_view === 'back'): ?>
<p class="lap-sum">
	Everyone who signed in after a month away and collected a package.
	<b><?php echo number_format($al_n_built); ?></b> of
	<b><?php echo number_format($al_n_back); ?></b> have built a Fighter since.
</p>
<?php if (!$al_back): ?>
<p style="color:#8fa8b8">Nobody has come back yet. The first return will appear here.</p>
<?php else: ?>
<table class="lap-t">
	<thead><tr>
		<th>Member</th><th>Came back</th><th>Got</th><th>Built since</th><th>Since then</th>
	</tr></thead>
	<tbody>
	<?php foreach ($al_back as $uid => $b):
		$bu   = isset($al_bu[$uid]) ? $al_bu[$uid] : null;
		$bt   = strtotime($b['back_at']);
		$days = $bt ? (int)floor((time() - $bt) / 86400) : 0;
		$seen = ($bu && $bt && strtotime($bu['last_login']) > $bt + 86400);
	?>
	<tr>
		<td>
			<div class="lap-who">
				<?php if ($bu): ?>
				<img loading="lazy" alt=""
				     src="<?php echo htmlspecialchars(avatar_url($bu['discord_id'], $bu['avatar'], 64), ENT_QUOTES); ?>"
				     onerror="this.src='icons/skull.png'">
				<span title="<?php echo htmlspecialchars($bu['discord_id'], ENT_QUOTES); ?>"><?php
					echo htmlspecialchars($bu['username'], ENT_QUOTES); ?></span>
				<?php else: ?><span style="color:#5f7d90">user <?php echo (int)$uid; ?></span><?php endif; ?>
			</div>
		</td>
		<td class="lap-gone">
			<b><?php echo $days === 0 ? 'today' : number_format($days) . ' days ago'; ?></b>
			<span><?php echo htmlspecialchars(date('j M Y', $bt)); ?></span>
		</td>
		<td class="lap-gone"><b><?php echo (int)$b['traits']; ?></b> traits</td>
		<td class="lap-has"><?php
			echo !empty($al_built[$uid])
				? '<b>' . (int)$al_built[$uid] . '</b> Fighter' . ($al_built[$uid] > 1 ? 's' : '')
				: '<span class="none">not yet</span>';
		?></td>
		<td class="lap-has"><?php
			echo $seen ? 'signed in again' : '<span class="none">not since</span>';
		?></td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>

<?php else: ?>

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
		<?php /* WARMEST IS THE DEFAULT, because that is the order you would
		         work a list you intend to message. The other three are
		         different questions asked of the same people. */
		foreach (array('warmest' => 'Warmest', 'coldest' => 'Coldest',
		               'active'  => 'Most active', 'quiet' => 'Least active') as $k => $label): ?>
		<a href="admin-lapsed.php?days=<?php echo $al_days; ?>&amp;sort=<?php echo $k; ?>"
		   class="<?php echo $al_sort === $k ? 'on' : ''; ?>"><?php echo $label; ?></a>
		<?php endforeach; ?>
	</div>
</div>

<?php if (!$al_rows): ?>
<p style="color:#8fa8b8">Nobody has been away that long.</p>
<?php else: ?>
<table class="lap-t">
	<thead><tr>
		<th>Member</th><th>Last seen</th><th>Played</th><th>What they played</th>
		<th>Still waiting</th><th>Waiting for them</th>
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
		<?php /* THE NUMBER THE ACTIVE SORTS ORDER BY. Without it on screen
		         those two orderings look arbitrary. */ ?>
		<td class="lap-gone"><b><?php echo $did ? number_format((int)$did['total']) : '0'; ?></b></td>
		<td class="lap-did"><?php
			echo $bits ? htmlspecialchars(implode(' · ', $bits), ENT_QUOTES)
			           : '<span style="color:#4f7488">never played anything</span>';
		?></td>
		<td class="lap-has"><?php
			echo $has ? implode(' · ', $has) : '<span class="none">nothing</span>';
		?></td>
		<?php /*
		 * THE PACKAGE THEY WOULD WALK INTO, rendered as the Fighter it
		 * makes rather than three trait names, because the picture is
		 * the thing that goes in the DM.
		 *
		 * LAZY AND VIA AN ENDPOINT, not dhcf_render_fighter() inline.
		 * Compositing a stack of thousand-pixel layers per row would
		 * make a four-hundred-row list take minutes to open; this way
		 * only the rows actually scrolled to are ever built, once each.
		 *
		 * THE LINK IS THE PUBLIC URL AND THE IMG IS THE ADMIN ONE. The
		 * endpoint is behind the admin gate, so pasting its address
		 * into a DM would show the recipient "Not found." Loading the
		 * img is what causes the render to exist; the href is where it
		 * then lives, which is the address to paste. Right-click the
		 * picture, Copy Image Address, and the redirect means you get
		 * the public one either way.
		 */ ?>
		<?php
			/* Derived once per row: return_gift_url() calls the same
			   derivation internally, so asking twice doubles the hashing
			   for nothing. */
			$gift_t  = return_gift_traits($u['discord_id']);
			$gift_u  = return_gift_url($u['discord_id']);
			$gift_ep = 'return-gift-card.php?d=' . rawurlencode($u['discord_id']);
			$gift_l  = array();
			foreach ($gift_t as $gc => $gs) {
				$gift_l[] = ucfirst($gc) . ': ' . dhcf_trait_name($gc, $gs);
			}
		?>
		<td class="lap-gift">
			<?php /* A BUTTON, NOT A LINK. It opens the modal rather than
			         navigating, and the picture it shows is loaded from the
			         endpoint -- which is what causes the render to exist, so
			         the public URL beside it is live by the time anyone can
			         copy it. */ ?>
			<button type="button" class="lap-giftbtn"
			        data-img="<?php echo htmlspecialchars($gift_ep, ENT_QUOTES); ?>"
			        data-url="<?php echo htmlspecialchars($gift_u, ENT_QUOTES); ?>"
			        data-who="<?php echo htmlspecialchars($u['username'], ENT_QUOTES); ?>"
			        data-traits="<?php echo htmlspecialchars(implode("\n", $gift_l), ENT_QUOTES); ?>"
			        title="See the package and copy the link">
				<img loading="lazy" alt="welcome-back package for <?php
				     echo htmlspecialchars($u['username'], ENT_QUOTES); ?>"
				     src="<?php echo htmlspecialchars($gift_ep, ENT_QUOTES); ?>">
			</button>
		</td>
	</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>
<?php endif; /* $al_view */ ?>
</div></div>

<?php /*
 * THE PACKAGE MODAL. One element reused by every row rather than one
 * per row -- four hundred hidden copies of the same markup is a lot of
 * DOM for a thing you open one at a time.
 *
 * The <img> is pointed at the ENDPOINT, not at the public render: the
 * endpoint is what causes the picture to exist, so by the time it has
 * drawn, the dhcrenders/ URL in the box beside it is live and safe to
 * paste. Pointing it straight at the public file would show a broken
 * image for any row whose thumbnail had not been scrolled into view.
 */ ?>
<div class="lap-mw" id="lapModal" hidden>
	<div class="lap-mb" role="dialog" aria-modal="true" aria-labelledby="lapModalWho">
		<div class="lap-mh">
			<h3 id="lapModalWho"></h3>
			<button type="button" class="lap-mx" id="lapModalX" aria-label="Close">&times;</button>
		</div>
		<div class="lap-mg">
			<img id="lapModalImg" alt="">
			<div class="lap-mi">
				<p class="lap-mt" id="lapModalTraits"></p>
				<label class="lap-ml" for="lapModalUrl">Image link for the invite</label>
				<input class="lap-mu" id="lapModalUrl" type="text" readonly>
				<button type="button" class="lap-mc" id="lapModalCopy">Copy link</button>
			</div>
		</div>
	</div>
</div>
<script>
(function () {
	var wrap = document.getElementById('lapModal');
	if (!wrap) return;
	var img    = document.getElementById('lapModalImg'),
	    who    = document.getElementById('lapModalWho'),
	    traits = document.getElementById('lapModalTraits'),
	    url    = document.getElementById('lapModalUrl'),
	    copy   = document.getElementById('lapModalCopy'),
	    last   = null;

	function open(btn) {
		last = btn;
		who.textContent    = btn.getAttribute('data-who') || 'Welcome-back package';
		traits.textContent = btn.getAttribute('data-traits') || '';
		url.value          = btn.getAttribute('data-url') || '';
		img.alt            = 'Welcome-back package for ' + who.textContent;
		/* Set last: assigning src starts the fetch, and on a row whose
		   thumbnail is already cached this completes before the rest of
		   the dialog would otherwise be filled in. */
		img.src            = btn.getAttribute('data-img') || '';
		copy.textContent   = 'Copy link';
		wrap.hidden = false;
		copy.focus();
	}
	function close() {
		wrap.hidden = true;
		/* Stop the fetch and release the picture; reopening re-reads it
		   from cache. */
		img.removeAttribute('src');
		if (last) { last.focus(); last = null; }
	}

	document.addEventListener('click', function (e) {
		var btn = e.target.closest ? e.target.closest('.lap-giftbtn') : null;
		if (btn) { e.preventDefault(); open(btn); return; }
		/* The backdrop is the wrapper itself; a click inside the panel
		   must not close it. */
		if (e.target === wrap || e.target.id === 'lapModalX') close();
	});
	document.addEventListener('keydown', function (e) {
		if (e.key === 'Escape' && !wrap.hidden) close();
	});

	copy.addEventListener('click', function () {
		url.select();
		url.setSelectionRange(0, url.value.length);   // iOS needs the range
		function done() { copy.textContent = 'Copied'; }
		/* execCommand is deprecated but is the only thing that works
		   without a secure context or a permission prompt, so it is the
		   fallback rather than the first choice. */
		if (navigator.clipboard && navigator.clipboard.writeText) {
			navigator.clipboard.writeText(url.value).then(done, function () {
				try { document.execCommand('copy'); done(); } catch (err) {}
			});
		} else {
			try { document.execCommand('copy'); done(); } catch (err) {}
		}
	});
})();
</script>
