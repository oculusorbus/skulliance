<?php
/**
 * admin-dhcbosses.php — every Crew that went in, and how the day is going.
 *
 * READ ONLY. Nothing here spawns, settles, resets or pays. The day is closed
 * by rewards.php?dhcbosses-day=1 on a cron, and a settle button on an admin
 * page would be a second way to do the one thing that must happen exactly
 * once -- the payout is idempotent, but "exactly once" is easier to reason
 * about when there is only one caller.
 *
 * THE REPORT IS THE POINT. A shared health bar is only legible if you can
 * see who moved it: which Crews went in, what each one dealt, which Fighters
 * fell, and whether the people doing the damage are the same three every
 * day. That last question is the one that decides whether the feature is
 * working, and no leaderboard answers it.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/avatar-lib.php';
require_once __DIR__ . '/dhcbosses-lib.php';

/*
 * WHICH WEEK TO LOOK AT. In the query string so a view can be linked.
 *
 * This asked for CURDATE() and every `day` column in the schema holds the
 * THURSDAY the week began -- so for six days out of seven the page looked
 * up a date nothing is keyed on and reported "no crew has stood up" while
 * the crew was standing perfectly well under the Thursday.
 *
 * The parameter is still called `day` because the column is, and renaming a
 * column is not worth a migration for a word -- see dhcbosses-schema.md.
 */
$ab_today = dhcb_week($conn);
$ab_day   = isset($_GET['day']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['day'])
          ? $_GET['day'] : $ab_today;

$ab_state = dhcb_state($conn, $ab_day);
$ab_prog  = $ab_state ? dhcb_progress($ab_state) : null;
$ab_runs  = dhcb_runs_report($conn, $ab_day, 300);
$ab_part  = dhcb_participants($conn, $ab_day);
$ab_table = $ab_prog ? dhcb_tier_table($ab_prog) : 'run';
$ab_labels = array('run'=>'standard', 'placement_10'=>'improved',
                   'placement_3'=>'rare', 'placement_1'=>'best');

/* Has the day been settled? The payout rows are the only honest answer --
   a flag could be wrong, a row either exists or it does not. */
$ab_paid = 0;
$q = @$conn->query("SELECT COUNT(*) AS n FROM dhc_boss_payouts
                     WHERE day = '" . $conn->real_escape_string($ab_day) . "'");
if ($q && ($x = $q->fetch_assoc())) $ab_paid = (int)$x['n'];

/* The last fourteen WEEKS, for the picker and for the one chart that
   matters: is participation climbing or is it the same people. Grouped on
   `day`, which holds the Thursday -- so a row is a week, not a day. */
$ab_hist = array();
$q = @$conn->query("SELECT day, COUNT(DISTINCT user_id) AS players, COUNT(*) AS runs,
                           SUM(dealt) AS dealt
                      FROM dhc_boss_runs WHERE outcome <> 0
                     GROUP BY day ORDER BY day DESC LIMIT 14");
if ($q) while ($x = $q->fetch_assoc()) $ab_hist[] = $x;

include 'header.php';
admin_chrome('dhcbosses');
?>
<?php include 'admin-css.php'; ?>
<style>
.ab-crew { display:flex; gap:12px; flex-wrap:wrap; margin:0 0 16px; }
.ab-b { flex:1 1 200px; min-width:0; }
.ab-b img { width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:6px;
  background:#0d2030; display:block; transform:translateZ(0); }
.ab-b.dead img { filter:grayscale(100%); opacity:.45; }
.ab-bn { font-size:.72rem; letter-spacing:.07em; text-transform:uppercase; color:#8fa8b8; margin:6px 0 4px; }
.ab-bar { height:7px; border-radius:4px; background:rgba(255,255,255,.10); overflow:hidden; }
.ab-bar i { display:block; height:100%; background:#c2566a; border-radius:4px; }
.ab-b.dead .ab-bar i { background:rgba(255,255,255,.18); }
.ab-hp { font-size:.7rem; color:#5f7d90; margin-top:4px; font-variant-numeric:tabular-nums; }
.ab-sum { font-size:.82rem; color:#8fa8b8; margin:0 0 14px; }
.ab-sum b { color:#00c8a0; }
.ab-warn { color:#d69e2e; }
.ab-t { width:100%; border-collapse:collapse; font-size:.82rem; }
.ab-t th { text-align:left; font-size:.68rem; letter-spacing:.09em; text-transform:uppercase;
  color:#5f7d90; padding:0 10px 7px; font-weight:normal; }
.ab-t td { padding:8px 10px; border-top:1px solid rgba(255,255,255,.06); vertical-align:top; }
.ab-n { font-variant-numeric:tabular-nums; white-space:nowrap; }
.ab-f { font-size:.72rem; color:#5f7d90; }
.ab-f s { text-decoration:none; color:#c2566a; }
.ab-days { display:flex; gap:0; flex-wrap:wrap; margin:0 0 16px; }
.ab-days a { padding:6px 11px; font-size:.72rem; color:#8fa8b8;
  border:1px solid rgba(255,255,255,.12); text-decoration:none; }
.ab-days a + a { border-left:0; }
.ab-days a.on { color:#06222b; background:#00c8a0; border-color:#00c8a0; }
/* The colourway reference. Folded by default and that is load-bearing: a
   browser does not fetch images inside a display:none subtree, so fifteen
   500px renders cost nothing until somebody actually opens it. */
.ab-hues { margin:26px 0 0; border-top:1px solid rgba(255,255,255,.08); padding-top:14px; }
.ab-hues > summary { cursor:pointer; list-style:none; display:inline-flex; align-items:center;
  gap:8px; font-size:.78rem; letter-spacing:.09em; text-transform:uppercase; color:#8fa8b8; }
.ab-hues > summary::-webkit-details-marker { display:none; }
.ab-hues > summary:before { content:'+'; font-size:1rem; line-height:1; width:12px;
  text-align:center; opacity:.8; }
.ab-hues[open] > summary:before { content:'\2212'; }
.ab-hues > summary:hover { color:#00c8a0; }
.ab-hw { display:flex; flex-wrap:wrap; align-items:baseline; gap:4px 16px; margin:18px 0 8px;
  padding-bottom:6px; border-bottom:1px solid rgba(255,255,255,.07); }
.ab-hw h4 { margin:0; font-size:.8rem; color:#e8eaed; font-weight:600; }
.ab-hw.now h4:after { content:' · standing now'; color:#00c8a0; font-weight:normal;
  font-size:.72rem; letter-spacing:.06em; }
.ab-hw span { font-size:.7rem; color:#5f7d90; font-variant-numeric:tabular-nums; }
.ab-hw span b { color:#8fa8b8; font-weight:600; }
.ab-hr { display:grid; grid-template-columns:repeat(3,1fr); gap:10px; }
.ab-hr figure { margin:0; }
/* The source is square and width:100% + height:auto keeps it square, so no
   aspect-ratio box is needed -- the width/height attributes on the tag
   already reserve the space before the render arrives. */
.ab-hr img { width:100%; height:auto; display:block; border-radius:6px;
  background:#0d2030; }
.ab-hr figcaption { font-size:.66rem; letter-spacing:.07em; text-transform:uppercase;
  color:#5f7d90; text-align:center; margin-top:4px; }
.ab-note { font-size:.76rem; color:#8fa8b8; line-height:1.6; margin:16px 0 0;
  border-left:2px solid rgba(255,255,255,.14); padding-left:12px; }
.ab-note b { color:#e8eaed; }
.ab-note.flag { border-left-color:#d69e2e; }
@media (max-width:620px){ .ab-hr { grid-template-columns:repeat(3,minmax(84px,1fr)); } }
</style>

<?php if (!$ab_state): ?>
  <p class="ab-sum ab-warn">No crew has stood up for the week of
     <?php echo htmlspecialchars($ab_day); ?>.
     <?php if ($ab_day === $ab_today): ?>
       That is this week, so either the tables in dhcbosses-schema.md have not been created
       yet or nobody has opened the Bosses page since the week turned — the crew is stood up
       lazily, on the first read.
     <?php else: ?>
       Nothing was keyed to that Thursday.
     <?php endif; ?></p>
<?php else: ?>

<div class="ab-crew">
  <?php foreach ($ab_state as $b):
    $pct = $b['max_health'] > 0 ? max(0, min(100, round(100 * $b['health'] / $b['max_health']))) : 0; ?>
  <div class="ab-b<?php echo $b['dead'] ? ' dead' : ''; ?>">
    <img loading="lazy" alt="" src="<?php echo htmlspecialchars($b['art']); ?>" onerror="this.remove()">
    <div class="ab-bn"><?php echo htmlspecialchars($b['name']); ?></div>
    <div class="ab-bar"><i style="width:<?php echo $pct; ?>%"></i></div>
    <div class="ab-hp"><?php echo $b['dead'] ? 'down'
      : number_format($b['health']) . ' / ' . number_format($b['max_health']); ?></div>
  </div>
  <?php endforeach; ?>
</div>

<p class="ab-sum">
  <?php /* THE WAVE, because the crew gets back up inside the week now and
           "40% down" means nothing without knowing which crew. */ ?>
  <b>Wave <?php echo (int)$ab_prog['wave']; ?></b><?php
    if ($ab_prog['downed'] > 0): ?> · <b><?php echo (int)$ab_prog['downed']; ?></b>
    crew<?php echo $ab_prog['downed'] === 1 ? '' : 's'; ?> dropped<?php endif; ?> ·
  <b><?php echo round(100 * $ab_prog['pct']); ?>%</b> down ·
  <?php echo number_format($ab_prog['dealt']); ?> of <?php echo number_format($ab_prog['max']); ?> damage ·
  <b><?php echo count($ab_part); ?></b> players ·
  <b><?php echo count($ab_runs); ?></b> Crews sent ·
  rolling on the <b><?php echo $ab_labels[$ab_table]; ?></b> table
  <?php if ($ab_paid > 0): ?>
    · <b><?php echo (int)$ab_paid; ?></b> paid
  <?php elseif ($ab_day < $ab_today): ?>
    · <span class="ab-warn">not settled yet — run rewards.php?dhcbosses-week=1</span>
  <?php endif; ?>
</p>
<?php endif; ?>

<?php if ($ab_hist): ?>
<div class="ab-days">
  <?php foreach ($ab_hist as $h): ?>
  <a href="admin-dhcbosses.php?day=<?php echo htmlspecialchars($h['day']); ?>"
     class="<?php echo $h['day'] === $ab_day ? 'on' : ''; ?>"><?php
     echo htmlspecialchars(date('j M', strtotime($h['day']))); ?>
     <span style="opacity:.6">(<?php echo (int)$h['players']; ?>p)</span></a>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<?php if (!$ab_runs): ?>
  <p class="ab-sum">Nobody has sent a Crew on this day.</p>
<?php else: ?>
<table class="ab-t">
  <thead><tr>
    <th>Player</th><th>Crew</th><th>Dealt</th><th>Rounds</th><th>Result</th><th>When</th>
  </tr></thead>
  <tbody>
  <?php foreach ($ab_runs as $r): ?>
  <tr>
    <td><?php echo htmlspecialchars($r['username'] !== null ? $r['username'] : ('user ' . (int)$r['user_id'])); ?></td>
    <td class="ab-f"><?php
      if (empty($r['fighters'])) { echo '<i>not recorded</i>'; }
      else {
        $bits = array();
        foreach ($r['fighters'] as $f) {
          $n = htmlspecialchars($f['display']);
          $bits[] = !empty($f['fell']) ? '<s>' . $n . '</s>' : $n;
        }
        echo implode(' · ', $bits);
      } ?></td>
    <td class="ab-n"><b><?php echo number_format((int)$r['dealt']); ?></b></td>
    <td class="ab-n"><?php echo (int)$r['rounds']; ?></td>
    <td class="ab-n"><?php
      if ((int)$r['outcome'] === 0)      echo '<span class="ab-warn">running</span>';
      elseif (!empty($r['killed']))      echo '<b style="color:#00c8a0">kill</b>';
      elseif ((int)$r['outcome'] === 1)  echo 'cleared';
      else                               echo 'wiped';
    ?></td>
    <td class="ab-n ab-f"><?php echo htmlspecialchars(date('H:i', strtotime($r['started_at']))); ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php if ($ab_hist): ?>
<?php /* FOURTEEN WEEKS, not fourteen days. The query groups on `day` and
         `day` holds the Thursday the week began, so every row here is a
         week -- it said "fortnight" from the daily draft and would have had
         the owner reading a quarter as two weeks. */ ?>
<h3 style="margin:26px 0 8px;font-size:.9rem;color:#8fa8b8">The last fourteen weeks</h3>
<table class="ab-t">
  <thead><tr><th>Week of</th><th>Players</th><th>Crews</th><th>Damage</th></tr></thead>
  <tbody>
  <?php foreach ($ab_hist as $h): ?>
  <tr>
    <td><?php echo htmlspecialchars($h['day']); ?></td>
    <td class="ab-n"><?php echo (int)$h['players']; ?></td>
    <td class="ab-n"><?php echo (int)$h['runs']; ?></td>
    <td class="ab-n"><?php echo number_format((int)$h['dealt']); ?></td>
  </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php endif; ?>

<?php
/*
 * THE COLOURWAY REFERENCE.
 *
 * WHY IT IS HERE AND NOT IN A DOC. The crew gets back up in a new hue every
 * time it falls, and the only way to know what wave 4 looks like used to be
 * to wait for a week strong enough to produce one. This is the whole series
 * on demand, from the SAME endpoint the game, Discord and the X card use --
 * so it is not a mock-up of the colourways, it IS them, and a broken render
 * shows up here before a player finds it in a post.
 *
 * EVERY NUMBER IS CALLED, NOT TYPED. The hue, the power multiplier, the
 * traits a round banks and the table it rolls on all come from the functions
 * that decide them, so this page cannot drift from the game the way a
 * written reference would.
 *
 * FOLDED, AND THAT IS LOAD-BEARING. Fifteen 500px renders is several
 * megabytes; a browser fetches none of them while the <details> is shut,
 * because the subtree is display:none. The report above it stays the fast
 * thing this page is for.
 */
/*
 * HOW LONG THE SERIES IS, ASKED RATHER THAN TYPED. A literal 5 here is one
 * edit to dhcb_wave_hue() away from a reference page that shows four of six
 * colourways and a footnote that is wrong about where it wraps. Walk the
 * function until it returns to where it started.
 */
$ab_hue_waves = 1;
for ($w = 2; $w <= 64; $w++) {
	if (dhcb_wave_hue($w) === dhcb_wave_hue(1)) { $ab_hue_waves = $w - 1; break; }
	$ab_hue_waves = $w;
}
$ab_now_wave  = $ab_prog ? max(1, (int)$ab_prog['wave']) : 1;
?>
<details class="ab-hues">
  <summary>Wave colourways &mdash; all <?php echo $ab_hue_waves; ?>, as the renderer draws them</summary>
  <?php for ($w = 1; $w <= $ab_hue_waves; $w++):
    $tbl = dhcb_tier_table(array('downed' => $w - 1, 'pct' => 0.0, 'cleared' => false));
    $tr  = dhcb_round_traits($w); ?>
  <div class="ab-hw<?php echo $w === $ab_now_wave ? ' now' : ''; ?>">
    <h4>Wave <?php echo $w; ?></h4>
    <span>hue <b><?php echo (int)dhcb_wave_hue($w); ?>&deg;</b></span>
    <span>power <b>&times;<?php echo number_format(dhcb_wave_pow($w), 3); ?></b></span>
    <span>banks <b><?php echo $tr; ?></b> trait<?php echo $tr === 1 ? '' : 's'; ?> a round</span>
    <span>rolls on <b><?php echo htmlspecialchars($ab_labels[$tbl]); ?></b></span>
    <span>mythic <b><?php echo DHCF_TIERS[$tbl]['mythic']; ?>%</b></span>
  </div>
  <div class="ab-hr">
    <?php foreach (dhcb_bosses() as $bz): ?>
    <figure>
      <?php /* Relative, like every link on this platform: the login cookie
               is host-only, so an absolute one drops the session for anyone
               on the other hostname. */ ?>
      <img loading="lazy" width="500" height="500" alt=""
           src="dhc-boss-art.php?boss=<?php echo urlencode($bz['key']); ?>&amp;wave=<?php echo $w; ?>"
           onerror="this.closest('figure').style.opacity=.3">
      <figcaption><?php echo htmlspecialchars($bz['name']); ?></figcaption>
    </figure>
    <?php endforeach; ?>
  </div>
  <?php endfor; ?>

  <p class="ab-note">
    Hue only &mdash; even fifths of <b>72&deg;</b>, wave 1 ungraded. No tint and no overlay: a
    luminance-preserving rotation, the same matrix the browser's own <code>hue-rotate()</code>
    applies, so the art keeps its weight instead of reading as a filter laid over it.
    Rendered by <b>dhc-boss-art.php</b> and cached on the server against the source file's
    mtime, so replacing a boss picture retires every rotated copy of it.
  </p>
  <p class="ab-note flag">
    <b>The series wraps at <?php echo $ab_hue_waves; ?>.</b> Wave <?php echo $ab_hue_waves + 1; ?>
    is pixel-identical to wave 1 &mdash; the renders hash the same &mdash; but its power
    multiplier is <b>&times;<?php echo number_format(dhcb_wave_pow($ab_hue_waves + 1), 3); ?></b>
    against wave 1's &times;1.000. A crew that looks like the one the week opened on hits more
    than twice as hard, and nothing on the art says so.
  </p>
</details>

<?php /* admin_chrome() opens two divs and every admin page closes them the
         same way; there is no closer function. */ ?>
</div></div>
