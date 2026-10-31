<?php
/**
 * activity.php -- who is actually doing things, at a glance.
 *
 * WAS AN ADMIN PAGE, and is not one any more: nothing on it is private.
 * Usernames, avatars and how much somebody has played are already on the
 * leaderboards -- profile visibility gates HOLDINGS, not identity (see
 * dhcgallery.php's header) -- so the only thing admin-only status bought
 * was hiding it from the people it is about.
 *
 * THE FEED IT REPLACED IS feed.php. That page is the running commentary
 * of what Skull Bot announces; this one ranks the people behind it. They
 * answer different questions and now sit next to each other in the nav.
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
 * TWO TABS OVER ONE QUERY. Players is the default and is the page as it
 * was; Games asks the same data the other way round -- which games the
 * platform turns up for -- and answers it with the avatars of everybody
 * who played, because a bar whose length is a count of people reads worse
 * than the people themselves. The pivot is activity_games() in the lib and
 * costs nothing: no second query, no second definition of a play.
 *
 * THE WINDOW IS IN DAYS, which the leaderboard's periods are not: it
 * offers all-time, calendar month and calendar week, and "this month" on
 * the 1st is a different question from "the last 30 days". Rivalries
 * already uses rolling 7/30/90 and this matches it.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/avatar-lib.php';
require_once __DIR__ . '/activity-sources-lib.php';

/* The window, in the query string so a view can be linked or bookmarked.
   Anything else falls back to 30 rather than being trusted into a date. */
$ac_days = isset($_GET['days']) ? (int)$_GET['days'] : 30;
if (!in_array($ac_days, array(7, 30, 90), true)) $ac_days = 30;

/* Which reading. Same data either way, so this only picks a renderer --
   and it is in the query string for the same reason the window is. */
/* is_string, not a cast: ?view[]=games casts to the string "Array" and
   prints a PHP warning at the top of the page on the way. */
$ac_view = isset($_GET['view']) && is_string($_GET['view']) ? $_GET['view'] : 'players';
if (!in_array($ac_view, array('players', 'games'), true)) $ac_view = 'players';

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

/* Pivoted only when it is going to be rendered. */
$ac_games = $ac_view === 'games' ? activity_games($ac_rows) : array();

include 'header.php';
?>
<div class="row">
	<div class="aca">
		<h2>Activity</h2>
<style>
/*
 * FULL WIDTH, EXPLICITLY.
 *
 * .row is display:flex, so a single child sizes to its content rather
 * than filling the line -- the page rendered as a narrow column down the
 * left with two thirds of the screen empty. feed.php solves the same
 * problem the same way on #ac-wrap, which is the page this one sits
 * beside in the nav.
 *
 * The wrapper also carries its own padding and card look rather than
 * borrowing .content's, because .content is a card baseline meant for
 * panels inside a layout and this IS the layout.
 */
.aca { width: 100%; padding: 20px; }
.aca h2 { margin: 0 0 16px; }

/* Scoped to .aca so nothing here can reach the rest of the page. */
.aca-tools { display:flex; align-items:center; gap:14px; flex-wrap:wrap; margin:0 0 16px; }
.aca-range { display:flex; gap:0; }
.aca-range a { padding:7px 14px; font-size:.76rem; letter-spacing:.07em; text-transform:uppercase;
  color:#8fa8b8; border:1px solid rgba(255,255,255,.12); text-decoration:none; }
.aca-range a + a { border-left:0; }
.aca-range a.on { color:#06222b; background:#00c8a0; border-color:#00c8a0; }
.aca-range a:hover:not(.on) { color:#e8eaed; border-color:#00c8a0; }
.aca-sum { font-size:.78rem; color:#8fa8b8; }
.aca-sum b { color:#00c8a0; }

/* ---- the two tabs ----
   Deliberately NOT the same control as the window: the window filters what
   you are looking at and the tabs change what the page is, so one is a
   segmented button and the other an underlined tab. Giving them the same
   look made the row read as five choices of one thing. */
.aca-tabs { display:flex; gap:22px; margin:0 0 14px; border-bottom:1px solid rgba(255,255,255,.08); }
.aca-tabs a { padding:0 0 9px; font-size:.9rem; color:#8fa8b8; text-decoration:none;
  border-bottom:2px solid transparent; margin-bottom:-1px; }
.aca-tabs a.on { color:#e8eaed; border-bottom-color:#00c8a0; }
.aca-tabs a:hover:not(.on) { color:#e8eaed; }

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

/* ---- the games view ----
 * THE AVATARS ARE THE BAR. They wrap, they are all the same size, and
 * there is no track behind them: the length of the row IS the number of
 * people, which is what the rows are sorted by, so the ordering and the
 * picture cannot disagree. A bar would have had to be scaled against the
 * biggest game and would then have said nothing a reader could count.
 *
 * Nothing is truncated either -- a game fifty people played is meant to
 * take fifty avatars' worth of room. That is the measurement.
 */
.aca-games { display:flex; flex-direction:column; gap:14px; }
.aca-game { background:#07111d; border:1px solid rgba(255,255,255,.06); padding:13px 14px 14px; }
.aca-game-head { display:flex; align-items:baseline; gap:10px; flex-wrap:wrap; margin:0 0 11px; }
.aca-game-head b { font-size:.92rem; color:#e8eaed; }
.aca-game-n { font-size:.76rem; color:#00c8a0; font-variant-numeric:tabular-nums; }
.aca-game-a { font-size:.72rem; color:#4f7488; font-variant-numeric:tabular-nums;
  margin-left:auto; font-style:normal; }
.aca-faces { display:flex; flex-wrap:wrap; gap:5px; }
/* The wrapper carries the title so a hover names the player and their
   count; the image itself would lose it to the onerror swap. */
.aca-f { display:block; line-height:0; }
.aca-f img { width:32px; height:32px; border-radius:50%; object-fit:cover; display:block;
  background:#0d2030; border:1px solid rgba(255,255,255,.07); }
.aca-f:hover img { border-color:#00c8a0; }

@media (max-width:620px) {
  .aca-row { grid-template-columns:22px 28px minmax(0,1fr) auto; gap:8px; }
  .aca-face { width:28px; height:28px; }
  .aca-f img { width:28px; height:28px; }
  .aca-tabs { gap:16px; }
}
</style>

	<?php /* Each control carries the other one's state, so switching tabs
	         keeps your window and switching windows keeps your tab. */ ?>
	<div class="aca-tabs">
		<a href="activity.php?days=<?php echo $ac_days; ?>&amp;view=players"
		   class="<?php echo $ac_view === 'players' ? 'on' : ''; ?>">Players</a>
		<a href="activity.php?days=<?php echo $ac_days; ?>&amp;view=games"
		   class="<?php echo $ac_view === 'games' ? 'on' : ''; ?>">Games</a>
	</div>

	<div class="aca-tools">
		<?php /* Rolling windows, not calendar periods -- see the file header. */ ?>
		<div class="aca-range">
			<?php foreach (array(7, 30, 90) as $d): ?>
			<a href="activity.php?days=<?php echo $d; ?>&amp;view=<?php echo $ac_view; ?>"
			   class="<?php echo $d === $ac_days ? 'on' : ''; ?>"><?php echo $d; ?> days</a>
			<?php endforeach; ?>
		</div>
		<span class="aca-sum"><?php if ($ac_view === 'games'): ?><b><?php
			echo number_format(count($ac_games)); ?></b> games ·
			<?php endif; ?><b><?php echo number_format($ac_people); ?></b> active ·
			<b><?php echo number_format($ac_actions); ?></b> actions ·
			<b><?php echo number_format($ac_points); ?></b> weighted</span>
	</div>

<?php if (!$ac_rows): ?>
	<p class="aca-none">No activity recorded in the last <?php echo $ac_days; ?> days.</p>

<?php elseif ($ac_view === 'games'): ?>
	<?php /* MOST PLAYED MEANS MOST PLAYERS -- activity_games() says why.
	         The count is printed as well as drawn, because an avatar row is
	         something you read at a glance and not something you count. */ ?>
	<div class="aca-games">
		<?php foreach ($ac_games as $g): $n = count($g['players']); ?>
		<div class="aca-game">
			<div class="aca-game-head">
				<b><?php echo htmlspecialchars(isset($ac_labels[$g['key']])
					? $ac_labels[$g['key']] : $g['key'], ENT_QUOTES); ?></b>
				<span class="aca-game-n"><?php echo number_format($n); ?>
					player<?php echo $n === 1 ? '' : 's'; ?></span>
				<em class="aca-game-a"><?php echo number_format($g['actions']); ?></em>
			</div>
			<div class="aca-faces">
				<?php foreach ($g['players'] as $p): ?>
				<span class="aca-f" title="<?php echo htmlspecialchars($p['username']
					. ' · ' . number_format($p['cnt']), ENT_QUOTES); ?>">
					<img loading="lazy" alt="<?php echo htmlspecialchars($p['username'], ENT_QUOTES); ?>"
					     src="<?php echo htmlspecialchars(avatar_url($p['discord_id'], $p['avatar'], 64), ENT_QUOTES); ?>"
					     onerror="this.src='icons/skull.png'">
				</span>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</div>

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
</div>
