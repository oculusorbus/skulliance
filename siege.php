<?php
/**
 * siege.php -- the week against the boss crew, as a map.
 *
 * The ladder answers "where do I stand". This answers "how is the week
 * going, and who is carrying it" -- the question a SHARED bar invites and
 * the one a per-player board cannot answer. The crew sits in the middle as
 * the week's waves; everyone who sent a Crew at it is ringed around, sized
 * by the damage they put in, with the Fighters they fielded drawn as real
 * portraits.
 *
 * IT IS THE BOSSES' RIVALRIES, deliberately -- same ring, same portraits,
 * same ink-exact canvas, because a second visual language for the same kind
 * of object would be a worse map rather than a distinct one. The packing and
 * the portrait live in dhc-crew-kit.js, shared with that page. What differs
 * is only what sits in the middle, which is the whole point of each.
 *
 * PUBLIC, like Rivalries and the Collection and for the same reason: it is a
 * picture of a community event, every name and avatar on it is already on
 * the ladder on dhcbosses.php, and a page only its participants can open is
 * a page nobody sees. Nothing private is on it -- no Crew that has not
 * fought yet, no holdings, no addresses.
 *
 * Merge the cookie, never assign -- see skulliance.php's own note.
 */
include 'db.php';

if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$sg_ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($sg_ck)) {
		$_SESSION = array_merge((array)$_SESSION, $sg_ck);
	} else {
		setcookie('SessionCookie', '', time() - 3600);
	}
	unset($sg_ck);
}
$sg_guest = empty($_SESSION['logged_in']);
if (!$sg_guest) { include 'verify.php'; include 'skulliance.php'; }

require_once __DIR__ . '/dhcfighters-lib.php';   // dhcf_layers(), the art stack
require_once __DIR__ . '/dhcbosses-lib.php';     // dhcb_siege() and friends
require_once __DIR__ . '/dhc-type.php';          // one typeface for every DHC surface

/*
 * THE WINDOW IS WHOLE WEEKS, NOT DAYS, and the buttons say so.
 *
 * Rivalries offers 7/30/90 days because a feud is a rolling thing. A boss
 * crew is not: the week turns at 4pm Thursday and a crew belongs to exactly
 * one of them, so a rolling window would straddle the boundary and draw
 * damage against a crew that no longer exists. Every window here is a whole
 * number of weeks and the labels match the clock the game actually runs on.
 */
$sg_weeks = isset($_GET['weeks']) ? (int)$_GET['weeks'] : 1;
if (!in_array($sg_weeks, array(1, 4, 13), true)) $sg_weeks = 1;

/* Where the trait art lives, the same probe the Collection uses. */
$sg_base = '';
foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
	if (is_dir(__DIR__ . '/' . $c . '/1000')) { $sg_base = $c; break; }
}

$sg_user = $sg_guest ? 0 : (int)(isset($_SESSION['userData']['user_id'])
                                 ? $_SESSION['userData']['user_id'] : 0);

$sg   = dhcb_siege($conn, $sg_weeks);
dhcb_siege_art($sg, __DIR__ . '/' . $sg_base, $sg_base);
$sglay = dhcb_siege_layout($sg);
$sg_n  = count($sglay['nodes']);

$sg_span = $sg_weeks === 1 ? 'this week'
         : ($sg_weeks . ' weeks');
$sg_title = 'Siege - DHC Bosses | Skulliance';
$sg_desc  = $sg_n
	? number_format((int)$sglay['dealt']) . ' damage from ' . number_format($sg_n)
	  . ' players against the DHC boss crew, ' . $sg_span . ', drawn as a map.'
	: 'A map of everyone attacking the DHC boss crew.';
$sg_canonical = 'https://www.skulliance.io/staking/siege.php';

$GLOBALS['page_title'] = $sg_title;
$GLOBALS['page_head']  = '
<meta name="description" content="' . htmlspecialchars($sg_desc) . '">
<link rel="canonical" href="' . $sg_canonical . '">
<meta property="og:type" content="website">
<meta property="og:site_name" content="Skulliance">
<meta property="og:url" content="' . $sg_canonical . '">
<meta property="og:title" content="' . htmlspecialchars($sg_title) . '">
<meta property="og:description" content="' . htmlspecialchars($sg_desc) . '">
';

include 'header.php';
?>

<?php dhc_type_styles(); ?>
<style>
/* EVERY TOKEN THIS PAGE USES IS DECLARED HERE, for the reason rivalries.php
   records at length: --bone is not declared globally, and a var() with no
   fallback referring to a token the component does not own paints nothing
   at all and says nothing about it. */
.sg-wrap{padding:14px;max-width:100%;margin:0 auto;overflow-x:clip;
  color:var(--bone,#e8eaed);font:14px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  --ink:#07111d;--panel:#0a1929;--line:#1b3346;--dim:#7a9eb0;--ochre:#00c8a0;
  --blood:#e0466b;--amber:#f5a623;--bone:#e8eaed;--panel2:#0d1e2e}
.sg-wrap h1{font-family:"Archivo Black",Impact,sans-serif;font-weight:400;margin:0;font-size:22px}
.sg-top{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px 24px;margin:0 0 10px}
.sg-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:8px 14px;flex:0 0 auto}
.sg-head .sub{font-size:12px;opacity:.65;white-space:nowrap}
.sg-intro{font-size:11.5px;opacity:.7;margin:0;line-height:1.6;flex:1 1 420px;min-width:0}
.sg-intro b{opacity:1;color:var(--bone)}
.sg-bar{display:flex;flex-wrap:wrap;gap:6px;margin:0;align-items:center;flex:0 0 auto}
.sg-bar .lbl{font-size:9px;letter-spacing:.14em;text-transform:uppercase;opacity:.5;margin-right:2px}
.sg-bar a{font-size:10px;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;
  color:var(--dim);border:1px solid var(--line);padding:4px 9px;border-radius:3px}
.sg-bar a:hover{border-color:var(--ochre);color:var(--ochre)}
.sg-bar a.on{border-color:var(--ochre);color:var(--ink);background:var(--ochre)}
/* BOUNDED ON BOTH AXES AND LEFT TO FIND ITS OWN SIZE BETWEEN THEM -- the
   shape rivalries.php arrived at after three attempts: max-width keeps it in
   the column, max-height keeps it in the window, width/height auto let the
   viewBox scale to satisfy whichever bites first, and it never scales UP
   past its natural size because enlarging a drawing only coarsens the type. */
.sg-map{display:flex;justify-content:center;align-items:flex-start;
  overflow:auto;background:#050c14;border:1px solid var(--line);border-radius:4px;
  padding:8px;-webkit-overflow-scrolling:touch}
.sg-map svg{display:block;width:auto;height:auto;
  max-width:100%;max-height:max(420px, calc(100vh - 330px))}
.sg-key{display:flex;flex-wrap:wrap;gap:6px 16px;margin:10px 0 0;
  font-size:9.5px;letter-spacing:.06em;color:var(--dim)}
.sg-key i{display:inline-block;vertical-align:middle;margin-right:5px}
.sg-key .k-live{width:14px;height:0;border-top:2px solid var(--blood)}
.sg-key .k-done{width:14px;height:0;border-top:1px dashed rgba(122,158,176,.6)}
.sg-key .k-kill{width:8px;height:8px;border-radius:50%;background:var(--amber)}
.sg-key .k-spoke{width:14px;height:0;border-top:2px solid rgba(0,200,160,.6)}
.sg-key .k-sat{width:9px;height:9px;border-radius:50%;
  background:#0a1929;border:1.5px solid rgba(0,200,160,.6)}
.sg-key .k-gone{width:9px;height:9px;border-radius:50%;
  background:#0a1929;border:1.5px dashed rgba(122,158,176,.7)}
.sg-note{font-size:10.5px;color:var(--dim);margin:10px 0 0;line-height:1.6;max-width:none}
.sg-empty{padding:28px 16px;text-align:center;color:var(--dim);font-size:12.5px;
  background:#050c14;border:1px solid var(--line);border-radius:4px}
.sg-stats{display:flex;flex-wrap:wrap;gap:4px 18px;margin:0 0 10px;font-size:11px;color:var(--dim)}
.sg-stats b{color:var(--bone);font-weight:600}

/* ---- hover: isolate one attacker ----------------------------------------
   Every line runs to the same centre, so "who is connected" is not a
   question here the way it is on the Arena map -- what a hover answers is
   "which of these is mine", which on a busy week is the only way to pick
   one attacker out of twenty. The march runs INWARD, the direction the
   damage went. */
.sg-cluster,.sg-map line{transition:opacity .12s ease}
.sg-dim{opacity:.18}
@keyframes sg-march{to{stroke-dashoffset:-16}}
.sg-march{stroke-dasharray:6 10;animation:sg-march .7s linear infinite}
@media (prefers-reduced-motion:reduce){
  /* The dash still marks the line; it simply does not travel. */
  .sg-march{animation:none}
}
.sg-cluster{cursor:pointer}
.sg-sat{cursor:pointer}

/* ---- the player panel ---------------------------------------------------- */
.sg-veil{position:fixed;inset:0;z-index:9996;background:rgba(3,8,14,.88);
  display:flex;align-items:flex-start;justify-content:center;overflow:auto;
  padding:calc(env(safe-area-inset-top,0px) + 5vh) 16px calc(env(safe-area-inset-bottom,0px) + 20px)}
.sg-veil[hidden]{display:none!important}
.sg-sheet{position:relative;width:min(620px,100%);background:var(--panel);
  border:1px solid var(--line);border-radius:5px;padding:18px 20px 22px}
.sg-x{position:absolute;top:6px;right:9px;background:none;border:0;color:var(--dim);
  font-size:25px;line-height:1;cursor:pointer;padding:4px 8px}
.sg-x:hover{color:var(--bone)}
.sg-phead{display:flex;align-items:center;gap:12px;margin:0 0 14px}
.sg-phead .av{width:46px;height:46px;border-radius:50%;object-fit:cover;flex:0 0 46px}
.sg-phead h2{margin:0;font-size:16px;font-family:"Archivo Black",Impact,sans-serif;font-weight:400}
.sg-phead .sub{margin:2px 0 0;font-size:11px;color:var(--dim)}
.sg-pstats{display:grid;grid-template-columns:auto 1fr;gap:4px 14px;margin:0 0 14px;font-size:11.5px}
.sg-pstats dt{color:var(--dim)}
.sg-pstats dd{margin:0;color:var(--bone)}
.sg-psect{font-size:9px;letter-spacing:.14em;text-transform:uppercase;color:var(--dim);margin:0 0 8px}
.sg-pcrew{display:grid;grid-template-columns:repeat(auto-fill,minmax(84px,1fr));gap:8px}
.sg-pcrew button,.sg-pcrew span.gone{display:flex;flex-direction:column;align-items:center;gap:3px;
  background:#07111d;border:1px solid var(--line);border-radius:3px;padding:7px 5px;
  color:var(--bone);font:inherit;font-size:10px;text-align:center}
.sg-pcrew button{cursor:pointer}
.sg-pcrew button:hover{border-color:var(--ochre)}
/* A disassembled Fighter is not clickable and must not look it. */
.sg-pcrew span.gone{opacity:.55;border-style:dashed}
.sg-pcrew b{font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%}
.sg-pcrew i{font-style:normal;color:var(--dim);font-size:9px}
/* SQUARE, the way the Collection frames a Fighter. The map clips to a disc
   because a marker has to read at twenty pixels; a panel opened to look at
   one properly should not crop its corners. */
.sg-pcrew .art{position:relative;display:block;width:100%;aspect-ratio:1;background:#050c14;
  border-radius:2px;overflow:hidden}
.sg-pcrew .art img{position:absolute;left:0;width:100%;height:100%;object-fit:contain}
.sg-pcrew .art.empty{background:repeating-linear-gradient(45deg,#07111d,#07111d 4px,#0a1929 4px,#0a1929 8px)}
</style>

<div class="row" id="row1">
 <div class="main">
  <div class="sg-wrap">
  <?php $dhcnav_at = 'siege'; include 'dhc-nav.php'; ?>

  <div class="sg-top">
    <div class="sg-head">
      <h1>Siege</h1>
      <span class="sub"><?php echo $sg_weeks === 1 ? 'this week' : 'last ' . $sg_weeks . ' weeks'; ?></span>
    </div>
    <p class="sg-intro">The boss crew in the middle, everyone attacking it around the outside.
       <b>Rings are the week's waves</b>, each sized by the health it was actually given - wave 1
       is the big one because it stands for most of the week, and the later ones shrink exactly
       as fast as they get cheaper to drop. A player's <b>line reaches as far in as the waves
       they fought</b>, so its length is when they turned up. Amber means they landed a last blow.</p>
    <p class="sg-bar">
      <span class="lbl">Window</span>
      <?php foreach (array(1 => 'This week', 4 => '4 weeks', 13 => '13 weeks') as $w => $lab): ?>
      <a href="siege.php?weeks=<?php echo $w; ?>"<?php
         echo $sg_weeks === $w ? ' class="on"' : ''; ?>><?php echo $lab; ?></a>
      <?php endforeach; ?>
    </p>
  </div>

  <?php if (!$sg_n): ?>
    <div class="sg-empty">
      Nobody has sent a Crew at the bosses <?php echo $sg_weeks === 1
        ? 'this week yet. The map fills in as the first Crews go out.'
        : 'in this window.'; ?>
    </div>
  <?php else: ?>
    <p class="sg-stats">
      <span><b><?php echo number_format((int)$sglay['dealt']); ?></b> damage</span>
      <span><b><?php echo number_format($sg_n); ?></b> player<?php echo $sg_n === 1 ? '' : 's'; ?></span>
      <span><b><?php echo number_format((int)$sglay['runs']); ?></b> Crews sent</span>
      <span><b><?php echo number_format((int)$sglay['downed']); ?></b> boss crew<?php
        echo (int)$sglay['downed'] === 1 ? '' : 's'; ?> down</span>
    </p>

    <div class="sg-map" id="sg-map"></div>

    <p class="sg-key">
      <span><i class="k-live"></i> the crew still standing</span>
      <span><i class="k-done"></i> a wave already down</span>
      <span><i class="k-kill"></i> who landed the last blow</span>
      <span><i class="k-spoke"></i> a player, thickness by damage</span>
      <span><i class="k-sat"></i> a Fighter, sized by damage dealt</span>
      <span><i class="k-gone"></i> a Fighter since disassembled</span>
    </p>
    <p class="sg-note">Hover a player to isolate their line. Click one to open their
       week; click a Fighter to open it in the Collection's panel.</p>

    <?php
      /*
       * SAID OUT LOUD, because the two numbers genuinely disagree and the
       * difference reads as a bug otherwise. The ladder ranks on damage per
       * Fighter fielded, deliberately, so that roster size does not decide
       * it. A map has to size on something a circle can carry, and
       * contribution to a SHARED bar really is raw damage -- so the biggest
       * circle here is not always the ladder's first row. Both figures are
       * on every label.
       */
    ?>
    <p class="sg-note">
      Circles are sized on <b>total damage</b>, which is what went into the shared bar.
      The ladder on <a href="dhcbosses.php">DHC Bosses</a> ranks on <b>damage per Fighter</b>
      instead, so that a large roster does not win by volume - which means the biggest circle
      here is not always the top of that board. Every label carries both.
      <?php if ($sg_weeks > 1): ?>
      Over more than one week the middle shows how deep each week got rather than one
      week's waves, because wave numbers restart every Thursday.
      <?php endif; ?>
    </p>
  <?php endif; ?>

  <?php /* The player panel. Built by siege.js from the data already on the
           page -- every number in it is in window.SIEGE_DATA, so there is
           nothing to fetch. The Fighter panel below is a different thing
           entirely: that one is the Collection's, included whole. */ ?>
  <div id="sg-veil" class="sg-veil" role="dialog" aria-modal="true" aria-labelledby="sg-pname" hidden>
    <div class="sg-sheet">
      <button type="button" class="sg-x" id="sg-close" aria-label="Close">&times;</button>
      <div id="sg-body"></div>
    </div>
  </div>
  </div>
 </div>
</div>

<?php if ($sg_n): ?>
<?php
  /*
   * THE FIGHTER PANEL, INCLUDED NOT REBUILT. The same dhc-fighter-modal.php
   * the Collection, the roster and the Rivalries map open, fed by the same
   * ajax/dhc-fighter.php payload keyed on serial. A second panel saying
   * nearly the same things is how two surfaces come to disagree about what
   * a Fighter is -- the proven ranks live in that file and nothing here
   * needs to know about them.
   *
   * $dhcm_deeplink stays false: dhcgallery.php owns the ?fighter= URL, and
   * two pages rewriting the address for the same panel would fight.
   */
  $dhcm_user = $sg_user; $dhcm_base = $sg_base; $dhcm_deeplink = false;
  include __DIR__ . '/dhc-fighter-modal.php';
?>
<script>
  window.SIEGE_DATA = <?php echo json_encode($sglay, JSON_UNESCAPED_SLASHES); ?>;
  /* DHCF_ONE_ARM_SPLIT, the same constant the Collection and the assembler
     use: where a two-arm master is cut to make a one-sided arm. */
  window.SIEGE_ARM_SPLIT = <?php echo defined('DHCF_ONE_ARM_SPLIT')
      ? (int)DHCF_ONE_ARM_SPLIT : 50; ?>;
  window.SIEGE_ME = <?php echo (int)$sg_user; ?>;
</script>
<?php /* map-kit.js owns svgEl() and the text measurer; dhc-crew-kit.js owns
         the Crew packing and the portrait, shared with the Rivalries map.
         Both before siege.js, and all three after the markup it writes into. */ ?>
<script src="map-kit.js?var=<?php echo rand(0,999); ?>"></script>
<script src="dhc-crew-kit.js?var=<?php echo rand(0,999); ?>"></script>
<script src="siege.js?var=<?php echo rand(0,999); ?>"></script>
<?php endif; ?>
<?php $conn->close(); ?>
