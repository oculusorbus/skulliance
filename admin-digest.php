<?php
/**
 * admin-digest.php — one day of Skulliance as a single poster.
 *
 * Reads a day out of the activity table and hands it to a canvas: the art
 * that actually appeared that day, the players who turned up, and a count
 * of each kind of thing. Download it, post it, write the words yourself.
 *
 * WHY IT CAN EXIST AT ALL. The collage needs art from missions, raids,
 * realms, Gauntlets, the Arena, Fighters, auctions and raffles, plus every
 * player and a tally per feature. Assembled from the feature tables that is
 * eight joins across eight unrelated schemas, several of which never kept
 * the picture -- only the ids it was built from. The activity table already
 * holds all of it, because it is written from the announcement itself. So
 * this is three queries, and a feature that starts announcing tomorrow is
 * in the poster tomorrow with nothing added here.
 *
 * DRAWN IN THE BROWSER, like admin-flyers.php, and for its reasons plus one
 * more:
 *   - there is no TTF in this repo (see dhc-card.php), and a poster whose
 *     whole job is shouting out players by name cannot be text-free;
 *   - Imagick composing forty pieces of art, some of them 5000x5000, is the
 *     memory profile that already spilled to disk once in admin-lib.php;
 *   - nothing is written to disk, so there is no POST and no cleanup.
 *
 * SEEDED BY THE DATE. The arrangement is random but deterministic, so the
 * same day always draws the same poster and a different day never draws the
 * same one. Re-roll changes the seed by hand when a layout falls badly.
 *
 * Nothing is stored. No POST, no writes -- which is why admin-harness.php's
 * write-path checks do not apply here, same as the flyer builder.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php
require_once __DIR__ . '/activity-lib.php';

/* Default to today in the SERVER's clock, which is the clock created_at is
   written with -- picking "today" from the browser would silently ask for
   tomorrow for anyone east of the server. */
$dg_day = isset($_GET['day']) ? (string) $_GET['day'] : date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dg_day)) $dg_day = date('Y-m-d');

$DIGEST = activity_digest($conn, $dg_day);

/* Which days have anything at all, so the picker can say so rather than
   leaving someone to hunt for a day the feed was running. */
$dg_days = array();
$r = @$conn->query("SELECT DATE(created_at) AS d, COUNT(*) AS n FROM activity
	GROUP BY DATE(created_at) ORDER BY d DESC LIMIT 60");
if ($r) while ($row = $r->fetch_assoc()) $dg_days[(string) $row['d']] = (int) $row['n'];

include 'header.php';   // GLOBAL scope: header.php reads $name and $avatar_url
admin_chrome('digest');
?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Saira+Semi+Condensed:wght@700;800&family=Rajdhani:wght@600;700&display=block" rel="stylesheet">
<style>
.dg-wrap { display: flex; gap: 22px; flex-wrap: wrap; align-items: flex-start; }
.dg-side { flex: 0 0 300px; max-width: 100%; }
.dg-main { flex: 1 1 520px; min-width: 0; }
.dg-canvas-box { background: #050c14; border: 1px solid rgba(0,200,160,0.15); border-radius: 4px;
  padding: 10px; overflow: auto; }
#dg-canvas { width: 100%; height: auto; display: block; }
.dg-row { display: flex; gap: 8px; flex-wrap: wrap; margin: 14px 0; }
.dg-stat { font-size: .78rem; color: #9fb4c4; line-height: 1.7; }
.dg-stat b { color: #00c8a0; }
.dg-miss { font-size: .74rem; color: #c8954a; line-height: 1.6; margin-top: 10px; }
.dg-empty { color: #9fb4c4; font-size: .86rem; line-height: 1.6; }
</style>

<p class="adm-note">One day of the platform as a single image, built from what Skull Bot announced.
  Pick a day, choose a shape, download the PNG. Nothing is saved.</p>

<div class="dg-wrap">
  <div class="dg-side">
    <div class="adm-card">
      <h3>Day</h3>
      <div class="adm-form">
        <label>Date
          <input type="date" id="dg-day" value="<?php echo htmlspecialchars($dg_day, ENT_QUOTES); ?>"
                 max="<?php echo date('Y-m-d'); ?>"></label>
        <?php if ($dg_days): ?>
        <label>Days with activity
          <select id="dg-jump">
            <?php foreach ($dg_days as $d => $n): ?>
            <option value="<?php echo htmlspecialchars($d, ENT_QUOTES); ?>" <?php echo $d === $dg_day ? 'selected' : ''; ?>>
              <?php echo htmlspecialchars($d) . ' — ' . (int)$n . ' event' . ($n === 1 ? '' : 's'); ?>
            </option>
            <?php endforeach; ?>
          </select></label>
        <?php endif; ?>
      </div>
    </div>

    <div class="adm-card">
      <h3>Shape</h3>
      <div class="adm-form">
        <label>Format
          <select id="dg-format">
            <?php /* 16:9 is never cropped in an X timeline. 4:5 is the tallest
                     thing X will show uncropped and takes the most vertical
                     room, which is what a feast wants. */ ?>
            <option value="wide">Wide — 1600 &times; 900 (16:9)</option>
            <option value="tall">Tall — 1200 &times; 1500 (4:5)</option>
            <option value="square">Square — 1200 &times; 1200</option>
          </select></label>
        <label>Headline
          <input type="text" id="dg-headline" maxlength="42" placeholder="(optional) overrides the date line"></label>
      </div>
      <div class="dg-row">
        <button type="button" class="adm-btn" id="dg-reroll">Re-roll layout</button>
        <button type="button" class="adm-btn" id="dg-save">Download PNG</button>
      </div>
      <p class="dg-stat" id="dg-report"></p>
      <p class="dg-miss" id="dg-miss" hidden></p>
    </div>
  </div>

  <div class="dg-main">
    <?php if (!$DIGEST['total']): ?>
      <div class="adm-card"><p class="dg-empty">
        <b>Nothing was announced on <?php echo htmlspecialchars($dg_day); ?>.</b><br>
        The activity table starts from the day it was created, and only records what
        Skull Bot actually posted — so an empty day here means a quiet day, or a day
        before the feed existed. Pick another from the list.
      </p></div>
    <?php else: ?>
      <div class="dg-canvas-box"><canvas id="dg-canvas" width="1600" height="900"></canvas></div>
    <?php endif; ?>
  </div>
</div>

<script>
/* The day's material, straight from activity_digest(). Every src in here has
   already been made canvas-safe server-side -- see activity_digest_src(). */
window.DIGEST = <?php echo json_encode($DIGEST, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
</script>
<script src="js/digest-builder.js?v=<?php echo @filemtime(__DIR__ . '/js/digest-builder.js') ?: 1; ?>"></script>
<script>
(function () {
  var day = document.getElementById('dg-day'), jump = document.getElementById('dg-jump');
  function go(d) { if (d) location.href = 'admin-digest.php?day=' + encodeURIComponent(d); }
  if (day)  day.addEventListener('change', function () { go(this.value); });
  if (jump) jump.addEventListener('change', function () { go(this.value); });
})();
</script>
