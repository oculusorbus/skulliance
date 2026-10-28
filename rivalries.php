<?php
/**
 * rivalries.php -- who is fighting whom in the Arena, as a map.
 *
 * The ladder answers "where do I stand". This answers "who is actually going
 * at it, and with what". One cluster per player active in the window: their
 * avatar sized by how much they have fought, their Crew drawn around it as
 * real Fighter portraits, and a chord to everyone they have traded battles
 * with.
 *
 * IT HAS ITS OWN PAGE BECAUSE IT NEEDS THE WHOLE WIDTH. This first shipped as
 * a panel under the Arena ladder and did not survive contact with real data:
 * ten players and 322 battles in a column about 450px wide is a map you scroll
 * past rather than read. A graph wants room in both directions at once.
 *
 * PUBLIC, like the Collection and for the same reason -- it is a picture of
 * what the Arena has been doing, usernames and avatars are already on every
 * leaderboard, and a page only the people in it can open is a page nobody
 * sees. Nothing here is private: no Crew composition for a battle that has not
 * happened, no holdings, no addresses.
 *
 * Merge the cookie, never assign -- see skulliance.php's own note.
 */
include 'db.php';

if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$riv_ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($riv_ck)) {
		$_SESSION = array_merge((array)$_SESSION, $riv_ck);
	} else {
		setcookie('SessionCookie', '', time() - 3600);
	}
	unset($riv_ck);
}
$riv_guest = empty($_SESSION['logged_in']);
if (!$riv_guest) { include 'verify.php'; include 'skulliance.php'; }

require_once __DIR__ . '/dhcfighters-lib.php';   // dhcf_layers(), the art stack
require_once __DIR__ . '/dhcarena-engine.php';
require_once __DIR__ . '/dhcarena-lib.php';      // dhca_rivalries() and friends
require_once __DIR__ . '/dhc-type.php';          // one typeface for every DHC surface

/*
 * How many Fighters are drawn around a player. DEFINED HERE AND SENT TO THE
 * RENDERER, rather than written out in both languages: the page says the
 * number in its own copy and rivalries.js lays out exactly that many, and two
 * independent 12s would disagree the first time one of them was tuned.
 * rivalries.js keeps a fallback for the same reason ARM_SPLIT does, and
 * rivalries-harness.js fails if the fallback and this drift apart.
 */
if (!defined('RIV_CREW_SHOWN')) define('RIV_CREW_SHOWN', 12);

$riv_user = $riv_guest ? 0 : (int)(isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 0);

/* ?days= so a quiet month can be widened without a deploy. Bounded: a year is
   not "current events" and the point of the page is what is happening now. */
$riv_days = isset($_GET['days']) ? (int)$_GET['days'] : DHCA_RIVALRY_DAYS;
if ($riv_days < 7)   $riv_days = 7;
if ($riv_days > 365) $riv_days = 365;

/* Where the trait art lives, the same probe the Collection uses. */
$riv_base = '';
foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
	if (is_dir(__DIR__ . '/' . $c . '/1000')) { $riv_base = $c; break; }
}

$riv    = dhca_rivalries($conn, $riv_days);
dhca_rivalry_art($riv, __DIR__ . '/' . $riv_base, $riv_base);
$rivlay = dhca_rivalry_layout($riv);
$riv_n  = count($rivlay['nodes']);

/* How many clusters are still waiting on the participation ledger. */
$riv_withf = 0;
foreach ($rivlay['nodes'] as $rn) if ($rn['fighters']) $riv_withf++;

$riv_title = 'Rivalries - DHC Arena | Skulliance';
$riv_desc  = $riv_n
	? number_format((int)$rivlay['battles']) . ' Arena battles between ' . number_format($riv_n)
	  . ' players in the last ' . (int)$riv_days . ' days, drawn as a map of who is hunting whom.'
	: 'A map of who is fighting whom in the DHC Arena.';
$riv_canonical = 'https://www.skulliance.io/staking/rivalries.php';

$GLOBALS['page_title'] = $riv_title;
$GLOBALS['page_head']  = '
<meta name="description" content="' . htmlspecialchars($riv_desc) . '">
<link rel="canonical" href="' . $riv_canonical . '">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="' . $riv_canonical . '">
<meta property="og:title" content="' . htmlspecialchars($riv_title) . '">
<meta property="og:description" content="' . htmlspecialchars($riv_desc) . '">
';

include 'header.php';
?>

<?php dhc_type_styles(); ?>
<style>
.riv-wrap{padding:14px;max-width:100%;margin:0 auto;overflow-x:clip;
  color:var(--bone,#e8eaed);font:14px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  /* EVERY TOKEN THIS PAGE USES IS DECLARED HERE. --bone was not, and nothing
     declares it globally -- .arena-wrap declares its own. So `background:
     var(--bone)` was an invalid value and the legend's line swatches simply
     did not paint: the key said "runs both ways" and "one-sided" next to
     nothing at all. A var() with no fallback referring to a token the
     component does not own is silent, which is what makes it worth a test. */
  --ink:#07111d;--panel:#0a1929;--line:#1b3346;--dim:#7a9eb0;--ochre:#00c8a0;
  --blood:#e0466b;--bone:#e8eaed;--panel2:#0d1e2e}
.riv-wrap h1{font-family:"Archivo Black",Impact,sans-serif;font-weight:400;margin:0;font-size:22px}
/*
 * ONE HEADER ROW. The title, the blurb and the window buttons were three
 * stacked blocks, and the blurb was capped at 80ch -- a sensible measure for
 * a column of prose and the wrong one here, because it wrapped to five lines
 * down the left while two thirds of a wide screen sat empty beside it. Five
 * lines of chrome is five lines the map does not get.
 *
 * Side by side they take one line on a wide screen and still stack on a
 * narrow one, since the row wraps and the blurb has a flex-basis it will not
 * squeeze below.
 */
.riv-top{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px 24px;margin:0 0 10px}
.riv-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px 14px;flex:0 0 auto}
.riv-head .sub{font-size:12px;opacity:.65;white-space:nowrap}
.riv-intro{font-size:11.5px;opacity:.7;margin:0;line-height:1.6;flex:1 1 420px;min-width:0}
.riv-intro b{opacity:1;color:var(--bone)}
.riv-bar{display:flex;flex-wrap:wrap;gap:6px;margin:0;align-items:center;flex:0 0 auto}
.riv-bar .lbl{font-size:9px;letter-spacing:.14em;text-transform:uppercase;opacity:.5;margin-right:2px}
.riv-bar a{font-size:10px;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;
  color:var(--dim);border:1px solid var(--line);padding:4px 9px;border-radius:3px}
.riv-bar a:hover{border-color:var(--ochre);color:var(--ochre)}
.riv-bar a.on{border-color:var(--ochre);color:var(--ink);background:var(--ochre)}
/*
 * THE MAP FITS THE SCREEN, ON BOTH AXES, AND STAYS CENTRED.
 *
 * Three attempts got here. First it kept its natural size inside a box capped
 * at 82vh and scrolled: a sideways scrollbar, the map pinned to the left of a
 * wide screen, and only ever a window onto the graph. Then it was told to
 * fill the column -- width:100% -- which fixed the pinning and the sideways
 * scroll, but on a large display the column is nearly two thousand pixels, so
 * the map grew to match and ran off the bottom instead.
 *
 * A graph wants to be seen whole. So it is bounded on BOTH axes and allowed
 * to find its own size between them: max-width keeps it inside the column,
 * max-height keeps it inside the window, width/height:auto let the viewBox
 * scale it down to satisfy whichever bites first, and the aspect ratio is
 * preserved either way. It never scales UP past its natural size, because
 * enlarging a 1500px drawing to fill a 2000px column only makes the type
 * coarse.
 *
 * The max-height here is only the PRE-SCRIPT value, so the box is never
 * briefly taller than the window before rivalries.js runs. Its subtraction is
 * a guess at the page above and below, and a guess is wrong twice -- too
 * small and the map overflows, too large and it leaves a dead band under the
 * legend, which is what 330 did. The script measures the real leftover and
 * overrides this; see fit() there. The max() floor stops a short window from
 * squeezing the map into a stamp.
 *
 * Centred by the flex container rather than by margin:auto, which only
 * centres a block whose width is resolved -- and this one's is deliberately
 * not.
 */
.riv-map{display:flex;justify-content:center;align-items:flex-start;
  overflow:auto;background:#050c14;border:1px solid var(--line);border-radius:4px;
  padding:8px;-webkit-overflow-scrolling:touch}
.riv-map svg{display:block;width:auto;height:auto;
  max-width:100%;max-height:max(420px, calc(100vh - 330px))}
.riv-shot{display:flex;flex-wrap:wrap;align-items:center;gap:8px;margin:10px 0 0}
.riv-shot button{font-size:10px;letter-spacing:.08em;text-transform:uppercase;cursor:pointer;
  font-family:inherit;padding:6px 12px;border-radius:3px;border:1px solid var(--line);
  background:transparent;color:var(--bone)}
.riv-shot button:hover{border-color:var(--ochre);color:var(--ochre)}
.riv-shot button[hidden]{display:none}
#riv-shot-note{font-size:10px;color:var(--dim);letter-spacing:.04em}
.riv-key{display:flex;flex-wrap:wrap;gap:6px 16px;margin:10px 0 0;
  font-size:9.5px;letter-spacing:.06em;color:var(--dim)}
.riv-key span{display:flex;align-items:center;gap:6px}
.riv-key i{display:block;font-style:normal;flex:0 0 auto}
.riv-key .k-solid{width:20px;height:3px;background:var(--bone)}
.riv-key .k-dash{width:20px;height:0;border-top:3px dashed var(--bone);opacity:.7}
.riv-key .k-agg{width:10px;height:10px;border-radius:50%;border:2px solid var(--blood)}
.riv-key .k-tgt{width:10px;height:10px;border-radius:50%;border:2px solid var(--ochre)}
.riv-key .k-sat{width:11px;height:11px;border-radius:50%;background:#0a1929;border:1.5px solid var(--dim)}
.riv-note{font-size:10.5px;color:var(--dim);margin:10px 0 0;line-height:1.6;max-width:80ch;
  border-left:2px solid var(--line);padding-left:9px}
.riv-empty{font-size:12px;color:var(--dim);border:1px solid var(--line);border-radius:4px;
  padding:26px;text-align:left;background:var(--panel)}
/* ---- THE SAME FOUR ANIMATIONS THE REALM MAP USES -------------------------
   Lifted from dist/map.css with their timings and ranges unchanged, because
   the point is that the two maps feel like one platform rather than two
   things that happen to both move. What they attach to differs -- a raid
   line there is a grudge line here, a realm marker is a player -- but a line
   breathes at the same rate on both, and your own avatar pulses slower and
   deeper than anybody else's on both.

     riv-pulse  1.4s alternate, .4 to .85   lines at rest
     riv-march  0.7s linear, dashoffset -18 the lines you are hovering
     riv-glow   1.6s, .5 to 1               a player who leans one way
     riv-own    2s,   .35 to 1              you
*/
@keyframes riv-pulse { from { opacity:.4 } to { opacity:.85 } }
@keyframes riv-march { to { stroke-dashoffset:-18 } }
@keyframes riv-glow  { 0%,100% { opacity:.5 } 50% { opacity:1 } }
@keyframes riv-own   { 0%,100% { opacity:.35 } 50% { opacity:1 } }
.riv-map line.riv-line{animation:riv-pulse 1.4s ease-in-out infinite alternate}
/* MARCHING NEEDS SOMETHING TO MARCH. A mutual grudge is drawn solid, so the
   dashes come with the hover state -- the legend describes the map at rest,
   and at rest the solid/dashed distinction is untouched. */
.riv-map line.riv-march{animation:riv-march .7s linear infinite;
  stroke-dasharray:6 3}
.riv-ring-lean{animation:riv-glow 1.6s ease-in-out infinite}
.riv-ring-me{animation:riv-own 2s ease-in-out infinite}

/* Focus state lives as a class on the container, so "nothing is focused" is
   one fact rather than something assembled from every child -- and a touch
   device, which never fires mouseleave, cannot leave the map stuck dimmed. */
.riv-cluster,.riv-map line{transition:opacity .12s ease}
.riv-map.riv-focused line{opacity:.1}
.riv-map.riv-focused line.riv-on{opacity:1}
.riv-cluster.riv-dim{opacity:.22}
.riv-cluster,.riv-sat{cursor:pointer}
/* Everything stops for a viewer who asked for less motion -- the transitions
   AND the four animations, or the map carries on breathing at them. */
@media (prefers-reduced-motion:reduce){
  .riv-cluster,.riv-map line{transition:none}
  .riv-map line.riv-line,.riv-map line.riv-march,
  .riv-ring-lean,.riv-ring-me{animation:none}
}

/* ---- the player panel ----------------------------------------------------
   Deliberately NOT a second Fighter panel. This one answers "who is this
   person and who do they fight"; a Fighter is the Collection's panel, opened
   through its own file. */
/* z-index AND SAFE-AREA PADDING COPIED FROM THE FIGHTER PANEL, because they
   are the same kind of thing and were not behaving like it: at 70 this sat
   UNDER the navbar (200) and the burger button floated over the sheet. 9997
   is deliberately one below the Fighter panel's 9998, so a Fighter opened
   from the roster below layers on top of the player it belongs to. */
.riv-veil{position:fixed;inset:0;z-index:9997;background:rgba(3,8,14,.86);
  display:flex;align-items:flex-start;justify-content:center;overflow:auto;
  padding:calc(env(safe-area-inset-top,0px) + 4vh) 16px calc(env(safe-area-inset-bottom,0px) + 20px)}
.riv-veil[hidden]{display:none!important}
.riv-sheet{position:relative;width:min(960px,100%);background:var(--panel);
  border:1px solid var(--line);border-radius:6px;padding:20px 22px 22px}
.riv-x{position:absolute;top:8px;right:10px;background:none;border:0;color:var(--dim);
  font-size:24px;line-height:1;cursor:pointer;padding:4px 8px}
.riv-x:hover{color:var(--bone)}
.riv-ph{display:flex;align-items:center;gap:14px;margin:0 0 16px}
.riv-ph img{width:64px;height:64px;border-radius:50%;flex:0 0 auto;background:#050c14}
.riv-ph h2{margin:0;font-family:"Archivo Black",Impact,sans-serif;font-weight:400;font-size:19px}
.riv-ph .lean{font-size:9px;letter-spacing:.14em;text-transform:uppercase;
  border:1px solid currentColor;border-radius:3px;padding:2px 7px;margin-left:8px;vertical-align:2px}
.riv-ph .lean.a{color:var(--blood)} .riv-ph .lean.d{color:var(--ochre)}
.riv-ph a{color:var(--ochre);font-size:11px;text-decoration:none;text-align:left}
.riv-ph a:hover{text-decoration:underline}
.riv-stats{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 18px}
.riv-stats div{border:1px solid var(--line);padding:7px 12px;min-width:84px;border-radius:3px}
.riv-stats b{display:block;font-size:17px;font-variant-numeric:tabular-nums;color:var(--ochre)}
.riv-stats span{font-size:8.5px;letter-spacing:.1em;text-transform:uppercase;opacity:.55}
.riv-sect{font-size:9.5px;letter-spacing:.14em;text-transform:uppercase;opacity:.5;
  margin:0 0 8px;padding-top:4px}
/* THE TABLE SCROLLS IN ITS OWN BOX. Five columns do not fit a phone, and
   without this the right-hand ones were simply cut off the edge of the
   sheet -- the record column, which is the one worth reading. A table is one
   of the few things allowed to scroll sideways; the page still must not. */
.riv-vswrap{overflow-x:auto;-webkit-overflow-scrolling:touch;margin:0 0 18px}
.riv-vs{width:100%;border-collapse:collapse;font-size:11.5px;margin:0;min-width:430px}
.riv-vs th{text-align:left;font-size:8.5px;letter-spacing:.1em;text-transform:uppercase;
  opacity:.45;font-weight:400;padding:0 8px 6px 0}
.riv-vs td{padding:5px 8px 5px 0;border-top:1px solid var(--line);vertical-align:middle}
/*
 * ALIGNMENT BELONGS TO THE COLUMN, so the rule names both cell types.
 *
 * The markup already marked the numeric headers .r and only td.r was
 * right-aligned, so every heading sat hard left above a column of
 * right-aligned figures -- BATTLES floating over nothing while its numbers
 * lined up four columns away. The header was carrying the right
 * information and the stylesheet was only listening to half of it.
 */
.riv-vs td.r, .riv-vs th.r{text-align:right}
.riv-vs td.r{font-variant-numeric:tabular-nums}
.riv-vs .who{display:flex;align-items:center;gap:8px}
.riv-vs .who img{width:26px;height:26px;border-radius:50%;background:#050c14}
.riv-vs .won{color:var(--ochre)} .riv-vs .lost{color:var(--blood)}
/* The full roster, including the Fighters the map had no room to draw. */
.riv-crew{display:grid;grid-template-columns:repeat(auto-fill,minmax(104px,1fr));gap:10px}
/* --panel2, the platform's raised-surface colour, not the map's ground.
   #050c14 is the deep backdrop the graph is drawn on; borrowed for a tile
   sitting on a panel it reads as a black hole punched in the sheet. */
.riv-fig{background:var(--panel2);border:1px solid var(--line);border-radius:4px;padding:8px;
  cursor:pointer;text-align:center;font:inherit;color:inherit}
.riv-fig:hover{border-color:var(--ochre)}
/* DISPLAY:BLOCK, because it is a <span>. An inline box ignores width,
   height and aspect-ratio, so this had no height at all and the layers
   inside -- absolutely positioned to fill it -- had nothing to fill. The
   tiles rendered as name and stats with an invisible gap above. .nm and .st
   below got their display:block because they are obviously text; the one
   that is actually a box did not. */
/*
 * SQUARE, like the Collection's own card. The map draws a Fighter clipped
 * to a disc because it is a marker orbiting an avatar and has to read at
 * thirty pixels; this is the panel you open to look at one properly, and a
 * circle crops the corners off art that was composed as a square.
 * dhcgallery.php frames it exactly this way -- aspect-ratio:1, no radius.
 */
.riv-fig .art{display:block;position:relative;width:100%;aspect-ratio:1;margin:0 0 6px;
  overflow:hidden;background:var(--ink)}
.riv-fig .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.riv-fig .nm{display:block;font-size:10px;line-height:1.3;word-break:break-word}
.riv-fig .st{display:block;font-size:9px;opacity:.6;margin-top:2px;font-variant-numeric:tabular-nums}
.riv-empty-note{font-size:11px;opacity:.6;margin:0}
@media (max-width:620px){
  .riv-sheet{padding:16px 14px 18px}
  .riv-ph img{width:48px;height:48px}
}
</style>

<div class="riv-wrap">
  <?php $dhcnav_at = 'rivalries'; $dhcnav_guest = $riv_guest; include 'dhc-nav.php'; ?>
  <?php /* Title, blurb and window buttons share ONE row -- see .riv-top. */ ?>
  <div class="riv-top">
    <div class="riv-head">
      <h1>Rivalries</h1>
      <span class="sub">DHC Arena &middot; last <?php echo (int)$riv_days; ?> days</span>
    </div>
  <?php if (!$riv_n): ?>
  </div>
    <div class="riv-empty">
      No settled battles in the last <?php echo (int)$riv_days; ?> days.
      <a href="dhcarena.php">Fight someone</a> and the map draws itself.
    </div>
  <?php else: ?>
    <p class="riv-intro">
      <b><?php echo number_format((int)$rivlay['battles']); ?></b>
      settled battle<?php echo $rivlay['battles'] === 1 ? '' : 's'; ?> between
      <b><?php echo number_format($riv_n); ?></b>
      player<?php echo $riv_n === 1 ? '' : 's'; ?>. Bigger avatars have fought more,
      and the Fighters beside them are the Crew they field, each sized by how
      often. Hover anyone to pick out their rivalries, or a Fighter for its
      record. Up to <?php echo (int)RIV_CREW_SHOWN; ?> Fighters per player.
    </p>

    <div class="riv-bar">
      <span class="lbl">Window</span>
      <?php foreach (array(7 => '7 days', 30 => '30 days', 90 => '90 days') as $d => $lbl): ?>
        <a class="<?php echo $riv_days === $d ? 'on' : ''; ?>"
           href="rivalries.php<?php echo $d === DHCA_RIVALRY_DAYS ? '' : '?days=' . $d; ?>"><?php
           echo $lbl; ?></a>
      <?php endforeach; ?>
    </div>
  </div>

    <div id="riv-map" class="riv-map"></div>

    <?php /* A picture of the map, without resorting to a screenshot. Both
             buttons are hidden until the script has the map, because a
             button that cannot work yet is worse than one that is not
             there; Copy hides itself for good where the clipboard will not
             take an image, which today is Firefox. */ ?>
    <div class="riv-shot">
      <button type="button" id="riv-download">Download image</button>
      <button type="button" id="riv-copy">Copy image</button>
      <span id="riv-shot-note"></span>
    </div>

    <p class="riv-key">
      <span><i class="k-solid"></i> runs both ways</span>
      <span><i class="k-dash"></i> one-sided</span>
      <span><i class="k-agg"></i> mostly attacking</span>
      <span><i class="k-tgt"></i> mostly attacked</span>
      <span><i class="k-sat"></i> a Fighter, sized by battles fought</span>
    </p>

    <?php
      /*
       * THE PORTRAITS COME FROM A YOUNGER TABLE THAN THE LINES.
       * dhc_arena_battles has recorded attacker and defender since the Arena
       * opened, so the clusters and the grudges between them are months deep.
       * dhc_arena_battle_fighters is new, so which Fighters were in a Crew is
       * only known for battles logged since it landed. A player with battles
       * and no Crew drawn is NORMAL, and saying so costs less than letting it
       * read as a bug.
       */
      if ($riv_withf < $riv_n):
    ?>
      <p class="riv-note">
        <?php echo number_format($riv_n - $riv_withf); ?> of
        <?php echo number_format($riv_n); ?> players show no Fighters yet. Which
        Fighters were in a Crew has only been recorded since the participation
        ledger landed, so the portraits fill in as new battles are fought. The
        avatars and the lines are complete.
      </p>
    <?php endif; ?>
  <?php endif; ?>

  <?php /* The player panel. Built by rivalries.js from the data already on
           the page -- every number in it is in window.RIVALRY_DATA, so there
           is nothing to fetch. The Fighter panel below is a different thing
           entirely: that one is the Collection's, included whole. */ ?>
  <div id="riv-veil" class="riv-veil" role="dialog" aria-modal="true" aria-labelledby="riv-pname" hidden>
    <div class="riv-sheet">
      <button type="button" class="riv-x" id="riv-close" aria-label="Close">&times;</button>
      <div id="riv-body"></div>
    </div>
  </div>
</div>

<?php if ($riv_n): ?>
<?php
  /*
   * THE FIGHTER PANEL, INCLUDED NOT REBUILT. This is the same
   * dhc-fighter-modal.php the Collection and the roster open, and clicking a
   * Fighter here fetches the same ajax/dhc-fighter.php payload they do. A
   * second panel saying nearly the same things is how two surfaces come to
   * disagree about what a Fighter is -- the proven ranks live in that file
   * and nothing here needs to know about them.
   *
   * $dhcm_deeplink stays false: dhcgallery.php owns the ?fighter= URL, and
   * two pages rewriting the address for the same panel would fight.
   */
  $dhcm_user = $riv_user; $dhcm_base = $riv_base; $dhcm_deeplink = false;
  include __DIR__ . '/dhc-fighter-modal.php';
?>
<?php
  /*
   * map-kit.js BEFORE rivalries.js, and both after the markup: rivalries.js
   * runs on parse and looks up #riv-map straight away, so a tag above the div
   * finds nothing and returns silently. The realm map shares the same kit --
   * see realms.php, which loads it in the same order.
   *
   * JSON_HEX_* so a username carrying a quote or an angle bracket cannot break
   * out of the script element.
   */
?>
<script>
window.RIVALRY_DATA = <?php
  echo json_encode($rivlay, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS
                          | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES);
?>;
window.RIVALRY_ARM_SPLIT = <?php echo (int)DHCF_ONE_ARM_SPLIT; ?>;
window.RIVALRY_CREW_MAX  = <?php echo (int)RIV_CREW_SHOWN; ?>;
/* Who is looking, so their own avatar can pulse the way the realm map
   pulses yours. 0 for a guest, which pulses nothing. */
window.RIVALRY_ME        = <?php echo (int)$riv_user; ?>;
</script>
<?php /* js/save-image.js is NOT loaded here: header.php already carries
         it for every page, and a second tag would fetch the same file
         twice on the one page that happens to need it most. */ ?>
<script src="map-kit.js?var=<?php echo rand(0,999); ?>"></script>
<?php /* dhc-crew-kit.js: the Crew packing, the portrait and the trait-layer
         geometry, shared with the Siege map. After map-kit.js, which owns
         svgEl() and the text measurer, and before the map that uses it. */ ?>
<script src="dhc-crew-kit.js?var=<?php echo rand(0,999); ?>"></script>
<?php /* dhc-map-export.js: the download/copy buttons, shared with the Siege
         map. Needs js/save-image.js, which the page already loads. */ ?>
<script src="dhc-map-export.js?var=<?php echo rand(0,999); ?>"></script>
<script src="rivalries.js?var=<?php echo rand(0,999); ?>"></script>
<?php endif; ?>
<?php
$conn->close();
?>
</html>
