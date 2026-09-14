<?php
/**
 * DHC ASSEMBLER -- the shared trait-layering engine.
 *
 * Included by BOTH dhcsandbox.php (public proof of concept, every trait
 * available, no session) and dhcfighters.php (the game, restricted to traits
 * the player actually owns). Extracted so the layering rules -- draw order,
 * the weapon partition, the arms exclusions, the couplings, the assembly
 * offsets -- exist once. Two copies would drift, and every one of those rules
 * was expensive to find.
 *
 * Set before including:
 *   $dhca_mode   'sandbox' (default) or 'fighters'
 *   $dhca_owned  null for everything, or category => slug => ['copies'=>n,'free'=>n]
 *                to restrict the picker to a player's holdings
 *
 * Emits its own <!--
  Typefaces travel with the assembler, not with one page's <head>. They used to
  live only in dhcsandbox.php, so dhcfighters.php fell back to Impact for every
  heading and button -- condensed and heavy, and unreadable at 11px uppercase
  with letter-spacing. Anything that includes the assembler now gets the faces
  its CSS asks for.
-->
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Archivo+Black&family=JetBrains+Mono:wght@400;600&display=swap">
<style>, markup and <script>. The CSS sits in the body for the
 * fighters page, where header.php has already closed <head> -- valid, and
 * cheaper than teaching the platform header about a second stylesheet.
 */
require_once __DIR__ . '/dhcfighters-config.php';   // the layering rules live here

if (!isset($dhca_mode))  $dhca_mode  = 'sandbox';
if (!isset($dhca_owned)) $dhca_owned = null;

// Where the art landed. Checked in order so a different upload path needs one
// edit here, not a hunt through the file.
$dhc_base = '';
foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
	if (is_dir(__DIR__ . '/' . $c . '/1000')) { $dhc_base = $c; break; }
}

// slot key => [label, directory under <base>/<size>/, optional]
$dhc_slots = array(
	'background' => array('Background',      'background', false),
	'weaponBack' => array('Weapon (behind)', 'weapon',     true),
	'torso'      => array('Torso',           'torso',      false),
	// WEAPON BEFORE ARMS. A weapon is gripped, so the hand and forearm belong in
	// front of it -- drawing arms first put the grip over the knuckles. Reported
	// from the sandbox; the guessed order in LAYER-MANIFEST.json had it backwards.
	'weapon'     => array('Weapon',          'weapon',     true),
	'arms'       => array('Arms',            'arms',       true),
	// EFFECTS SIT JUST UNDER THE HEAD. They were behind the whole character for a
	// while, which kept them off the face but also buried them behind the body.
	// Here they cross the Fighter and stop short of the face, which is what an
	// attack burst or a comic cover is supposed to do.
	//
	// Effects 2 draws over Effects 1 -- the pair is a bottom/top sandwich, not two
	// interchangeable slots, so which one a trait goes in decides what covers what.
	'effects1'   => array('Effects 1',       'effects',    true),
	'effects2'   => array('Effects 2',       'effects',    true),
	'head'       => array('Head',            'head',       false),
	'headgear'   => array('Headgear',        'headgear',   true),
	'companion'  => array('Companion',       'companion',  true),
);

// WHICH WEAPONS BELONG IN WHICH SLOT.
//
// A PARTITION, not a preference: each weapon belongs to exactly one slot. The
// seven below are drawn in front of the body; every other weapon is drawn behind
// it. Neither list offers the other's art, so a weapon can only ever be placed
// where it actually works.
//
// It also resolves the three two-part weapons -- one half of each pair is in
// front, the other necessarily behind:
//
//   DH Spike Blaster 1  front   <->  DH Spike Blaster   back
//   Lil Fren            front   <->  Lil Fren 1         back
//   Mega Taser Cannon 1 front   <->  Mega Taser Cannon  back
//   Scythe Sash         front   <->  Scythe             back
//   Skull Krusher Sash  front   <->  Skull Krusher      back
//
// The two sashes were separated out of their weapons by hand: the scythe and
// the skull krusher are now the weapon alone, behind the body, with the sash
// that carried them a front piece in its own right. The axe lost its torso
// chains in the same pass -- another weapon already supplies those.
$dhc_weapon_front = array(
	'annihilation-belt', 'dh-raider-equipment', 'dh-spike-blaster-1',
	'electric-morning-star', 'lil-fren', 'mega-taser-cannon-1', 'plastic-blaster',
	'scythe-sash', 'skull-krusher-sash',
);

// THE COMIC COVERS ARE EFFECTS 2 ONLY.
//
// A cover is a full-frame treatment over the whole Fighter, so nothing else
// should ever be drawn on top of one -- and two covers at once is meaningless.
// Effects 2 is the upper of the two effects slots and holds exactly one trait,
// so confining the covers to it makes both guarantees structural: there is no
// slot left that could draw above a cover, and no way to select a second one.
//
// Enforced by leaving them out of the Effects 1 list entirely rather than by a
// rule in the renderer. A trait that is never offered cannot be combined wrongly.
$dhc_comic_covers = array(
	'dhc2-comic-cover-1', 'dhc2-comic-cover-2', 'dhc2-comic-cover-3',
);

// Display names come from trait-index.json when it is uploaded alongside the
// art; otherwise they are derived from the slug, so the page still reads well
// with nothing but the images present.
$dhc_index = array();
if ($dhc_base !== '' && is_file(__DIR__ . '/' . $dhc_base . '/trait-index.json')) {
	$dhc_index = json_decode(file_get_contents(__DIR__ . '/' . $dhc_base . '/trait-index.json'), true) ?: array();
}
function dhc_title($slug) {
	$s = str_replace('-', ' ', $slug);
	$s = preg_replace_callback('/\b([a-z])/', function ($m) { return strtoupper($m[1]); }, $s);
	return preg_replace('/\b(Dh|Xlon|Mk|Ue|Zx|Vr|Dhc)\b/', '\\1', $s);
}

// Rarity lives in the repo rather than in the art folder, so it is versioned
// with the code and needs no upload. Absent, the page simply shows no tiers.
$dhc_rarity = is_file(__DIR__ . '/dhcrarity.php') ? (require __DIR__ . '/dhcrarity.php') : array();

$dhc_traits = array();
foreach ($dhc_slots as $key => $s) {
	$dir = $s[1];
	$out = array();
	if ($dhc_base !== '') {
		foreach ((array)glob(__DIR__ . '/' . $dhc_base . '/250/' . $dir . '/*.png') as $f) {
			$slug = basename($f, '.png');
			// Filtered at the source: each weapon appears in one slot only.
			if ($key === 'weapon'     && !in_array($slug, $dhc_weapon_front, true)) continue;
			if ($key === 'weaponBack' &&  in_array($slug, $dhc_weapon_front, true)) continue;
			// Covers are offered in the upper effects slot only, so nothing can
			// draw over one and a second cover cannot be selected at all.
			if ($key === 'effects1'   &&  in_array($slug, $dhc_comic_covers, true)) continue;
			$name = isset($dhc_index[$dir][$slug]['name']) ? $dhc_index[$dir][$slug]['name'] : dhc_title($slug);
			// Rarity is keyed by art directory, so the two weapon slots and the two
			// effects slots share one table -- a trait's tier does not depend on
			// which slot it happens to be offered in.
			$r = isset($dhc_rarity[$dir][$slug]) ? $dhc_rarity[$dir][$slug] : null;
			$out[] = array(
				'slug' => $slug,
				'name' => $name,
				'tier' => $r ? $r[0] : '',
				'worn' => $r ? $r[1] : 0,     // how many of the 226 fighters wore it
				'rate' => $r ? $r[2] : 0,
			);
		}
	}
	$dhc_traits[$key] = $out;
}
/*
 * ARMLESS TORSO VARIANTS, optional and per-torso.
 *
 * Every torso is drawn WITH arms, so an Augmented Arms trait overlays a limb that
 * is already there and the original shows around it. There is no way to separate
 * them automatically -- the arms are as thick as the body, so neither a lateral
 * mask nor a morphological opening isolates them, and the union of the arm traits
 * swallows 94% of the chest. It needs hand-editing against the layered source.
 *
 * So: if web/<size>/torso-noarms/<slug>.png exists, it is used INSTEAD of the
 * normal torso whenever an arms trait is selected. Checked per torso rather than
 * all-or-nothing, so the set can be filled in one at a time and each one starts
 * working the moment it lands. Nothing breaks while they are missing.
 */
$dhc_noarms = array();
if ($dhc_base !== '') {
	foreach ((array)glob(__DIR__ . '/' . $dhc_base . '/1000/torso-noarms/*.png') as $f) {
		$dhc_noarms[] = basename($f, '.png');
	}
}

$dhc_total = 0;
foreach (array('background','torso','arms','head','headgear','weapon','companion','effects1') as $k) {
	$dhc_total += count($dhc_traits[$k]);
}
/*
 * OWNERSHIP FILTER. In fighters mode the picker shows only what the player
 * holds, with the copies they have free. Done here rather than in JS so an
 * unowned trait is never sent to the browser at all -- the grid cannot offer
 * what it was never given.
 */
if ($dhca_owned !== null) {
	foreach ($dhc_traits as $slotKey => $list) {
		$dir  = $dhc_slots[$slotKey][1];
		$keep = array();
		foreach ($list as $t) {
			if (empty($dhca_owned[$dir][$t['slug']])) continue;
			$own = $dhca_owned[$dir][$t['slug']];
			$t['copies'] = (int)$own['copies'];
			$t['free']   = (int)$own['free'];
			$keep[] = $t;
		}
		$dhc_traits[$slotKey] = $keep;
	}
}
?>

<style>
<?php if ($dhca_mode === 'sandbox'): ?>
/* THE SANDBOX KEEPS ITS OWN SKIN. It is a public, DHC-branded tool handed to
   an artist outside the platform, so it wears the collection's colours -- hot
   ash and ochre -- rather than Skulliance navy. */
:root{
  --ink:#100d0d; --panel:#191414; --panel2:#221b1b; --line:#312727;
  --bone:#ece7dc; --dim:#9b9086; --blood:#d0463a; --ochre:#e0a13c; --teal:#4fa39c;
}
<?php else: ?>
/* EMBEDDED: SKULLIANCE PALETTE, sampled from the platform's own stylesheet
   rather than approximated -- #07111d ground, #00c8a0 accent (its most-used
   colour by a distance), #7a9eb0 muted text. The brown-and-ochre set the
   sandbox uses reads as a foreign object bolted onto a navy interface.
   --ochre keeps its name because the assembler's CSS refers to it throughout;
   only the value changes, so there is one place to edit rather than fifty. */
:root{
  --ink:#07111d; --panel:#0a1929; --panel2:#0d1e2e; --line:#1b3346;
  --bone:#e8eaed; --dim:#7a9eb0; --blood:#00c8a0; --ochre:#00c8a0; --teal:#00a882;
}
<?php endif; ?>
*{box-sizing:border-box}
<?php if ($dhca_mode === 'sandbox'): ?>
/* STANDALONE ONLY. The sandbox owns its whole page, so it takes the viewport
   and lays the shell out against it. Embedded in the platform these same rules
   force a viewport-tall block in the middle of a normal page -- which is where
   the dead gap on DHC Fighters came from. */
html,body{margin:0;height:100%}
body{
  background:var(--ink); color:var(--bone);
  font:14px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  -webkit-font-smoothing:antialiased;
}
<?php else: ?>
/* EMBEDDED. Typography only -- no page-level background, height or margin, so
   the assembler sits in the host page's flow like any other block. */
.shell,.dhcf-wrap{color:var(--bone);
  font:14px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  -webkit-font-smoothing:antialiased}
<?php endif; ?>
h1,h2,h3,.btn,.tab{font-family:"Archivo Black",Impact,sans-serif;font-weight:400}
.dhcf-wrap h1,.dhcf-wrap h2,.dhcf-wrap button{font-family:"Archivo Black",Impact,sans-serif;font-weight:400}
a{color:var(--ochre)}

.top{display:flex;align-items:baseline;gap:16px;flex-wrap:wrap;
  padding:14px 20px;border-bottom:1px solid var(--line);background:var(--panel)}
.top h1{font-size:17px;margin:0;letter-spacing:.02em}
.top .sub{font-size:11px;color:var(--dim)}
.top .spacer{flex:1}
.badge{font-size:10px;letter-spacing:.14em;text-transform:uppercase;color:var(--ink);
  background:var(--ochre);padding:3px 8px;border-radius:2px}

.shell{display:grid;grid-template-columns:minmax(0,1fr) 380px;gap:0;<?php
  // Only the standalone page has a viewport to fill. Embedded, the shell is
  // sized by what is in it, and the picker scrolls inside its own column.
  echo $dhca_mode === 'sandbox' ? 'height:calc(100% - 52px)' : 'height:auto;max-height:none'; ?>}
@media (max-width:900px){.shell{grid-template-columns:1fr;height:auto}}
<?php if ($dhca_mode !== 'sandbox'): ?>
/* Embedded, THE STAGE SETS THE HEIGHT and the picker is taken out of row
   sizing altogether by being absolutely positioned.
 *
 * Every in-flow arrangement fails one way or the other: size the row to
 * content and 42 thumbnails stretch it to two and a half screens; give it a
 * fixed height and the canvas is trapped in a scroller. Out of flow, the
 * picker contributes nothing to the row, so the shell is exactly as tall as
 * the canvas plus its buttons and draw order -- nothing to scroll past, no
 * dead space -- and the picker fills that height with its grid scrolling
 * inside.
 */
.shell{border:1px solid var(--line);border-radius:3px;position:relative;align-items:stretch}
.stage{overflow:visible;min-height:0}
.picker{position:absolute;top:0;right:0;bottom:0;width:380px;
  display:flex;flex-direction:column;min-height:0}
.grid{flex:1;min-height:0;overflow:auto}
@media (max-width:900px){
  /* Single column: back into flow, and cap the picker rather than trapping
     the canvas in a short scroller on a phone. */
  .picker{position:static;width:auto;max-height:min(78vh,720px)}
}
<?php endif; ?>

/* ---- stage ---- */
.stage{display:flex;flex-direction:column;align-items:center;justify-content:flex-start;
  padding:22px;gap:14px;min-width:0;overflow:auto}
.frame{position:relative;width:min(66vh,100%);aspect-ratio:1;background:var(--panel2);
  border:1px solid var(--line);box-shadow:0 18px 60px rgba(0,0,0,.55)}
.frame img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain;
  image-rendering:auto;pointer-events:none}
.frame .empty{position:absolute;inset:0;display:flex;align-items:center;justify-content:center;
  color:var(--dim);font-size:12px;text-align:center;padding:30px;line-height:1.7}
.bar{display:flex;gap:8px;flex-wrap:wrap;justify-content:center}
.btn{background:var(--panel2);color:var(--bone);border:1px solid var(--line);
  padding:9px 15px;font-size:12px;cursor:pointer;border-radius:2px;letter-spacing:.03em}
.btn:hover{border-color:var(--ochre);color:var(--ochre)}
.btn:focus-visible{outline:2px solid var(--ochre);outline-offset:2px}
.btn.primary{background:var(--blood);border-color:var(--blood);color:var(--ink)}
.btn.primary:hover{filter:brightness(1.12)}

/* ---- stack readout ---- */
.stack{width:min(66vh,100%);border:1px solid var(--line);background:var(--panel)}
.stack h3{margin:0;padding:9px 12px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;
  color:var(--dim);border-bottom:1px solid var(--line)}
.stack ol{margin:0;padding:6px 0;list-style:none;font-size:11.5px}
.stack li{display:flex;gap:10px;padding:3px 12px;color:var(--dim)}
.stack li b{color:var(--bone);font-weight:400;min-width:112px}
.stack li.off{opacity:.34}
.stack li .n{color:var(--teal)}
.stack li[draggable]{cursor:grab;user-select:none}
.stack li[draggable]:hover{background:var(--panel2)}
.stack li.dragging{opacity:.4;cursor:grabbing}
.stack li.over{box-shadow:inset 0 2px 0 var(--ochre)}
.stack li .grip{color:var(--line);letter-spacing:-2px}
.stack li[draggable]:hover .grip{color:var(--ochre)}
/* visibility toggle: pushed to the right edge of its row */
.stack li{align-items:center}
.stack li span:nth-of-type(3){flex:1;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.stack li .eye{background:none;border:0;color:var(--line);font:inherit;font-size:12px;
  line-height:1;padding:2px 3px;cursor:pointer;border-radius:2px;flex:none}
.stack li .eye:hover{color:var(--ochre)}
.stack li .eye:focus-visible{outline:1px solid var(--ochre);outline-offset:1px}
.stack li .eye[aria-pressed="true"]{color:var(--blood)}
/* Hidden reads differently from empty: struck through, not just faded, so a
   hidden layer is never mistaken for a slot with nothing in it. */
.stack li.hid b,.stack li.hid span:nth-of-type(3){text-decoration:line-through;opacity:.5}
.stack h3{display:flex;justify-content:space-between;align-items:center;gap:10px}
.stack h3 button{background:none;border:1px solid var(--line);color:var(--dim);
  font:inherit;font-size:9px;letter-spacing:.1em;padding:3px 7px;cursor:pointer;border-radius:2px}
.stack h3 button:hover{border-color:var(--ochre);color:var(--ochre)}
.stack h3 .custom{color:var(--ochre)}

/* ---- picker ---- */
.picker{border-left:1px solid var(--line);background:var(--panel);display:flex;
  flex-direction:column;min-height:0}
@media (max-width:900px){.picker{border-left:0;border-top:1px solid var(--line)}}
.tabs{display:flex;flex-wrap:wrap;gap:1px;padding:10px;border-bottom:1px solid var(--line)}
.tab{background:var(--panel2);border:1px solid transparent;color:var(--dim);font-size:10.5px;
  padding:6px 9px;cursor:pointer;letter-spacing:.04em;border-radius:2px}
.tab:hover{color:var(--bone)}
.tab[aria-selected="true"]{background:var(--blood);color:#fff}
.tab .dot{color:var(--ochre)}
/* grid-auto-rows:max-content is load-bearing, not tidying. Without it the
   implicit rows were sized shorter than the cells' own content -- 61px against a
   112px thumbnail -- so the name underneath fell outside the cell and
   overflow:hidden clipped it away entirely. Every category had names; none of
   them were visible. */
.grid{flex:1;overflow:auto;padding:10px;display:grid;
  grid-template-columns:repeat(auto-fill,minmax(88px,1fr));gap:8px;
  align-content:start;grid-auto-rows:max-content}
.cell{background:var(--panel2);border:1px solid var(--line);cursor:pointer;padding:0;
  display:flex;flex-direction:column;border-radius:2px;overflow:hidden}
.cell:hover{border-color:var(--ochre)}
.cell:focus-visible{outline:2px solid var(--ochre);outline-offset:1px}
.cell[aria-pressed="true"]{border-color:var(--blood);box-shadow:inset 0 0 0 1px var(--blood)}
.cell img{width:100%;aspect-ratio:1;object-fit:contain;background:
  repeating-conic-gradient(#1b1616 0% 25%,#221b1b 0% 50%) 50%/12px 12px}
.cell span{font-size:9.5px;line-height:1.3;padding:5px 6px;color:var(--dim);
  overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.cell[aria-pressed="true"] span{color:var(--bone)}
.cell.none img{background:var(--panel2);position:relative}
.cell.none{border-style:dashed}
.cell.none[aria-pressed="true"]{border-style:solid}
/* A trait the current build rules out. Dimmed and unclickable rather than
   hidden -- it still has to be findable, or its absence reads as a bug. */
/* ---- rarity ----
   One hue per tier, used for the cell badge, the filter pills and the swatch
   in the legend, so a colour always means the same thing wherever it appears. */
<?php if ($dhca_mode === 'sandbox'): ?>
.t-common{--tier:#8b8178}
.t-uncommon{--tier:#4f9d84}
.t-epic{--tier:#7d6bb0}
.t-legendary{--tier:#c8913c}
.t-mythic{--tier:#c2445c}
<?php else: ?>
/* Tier ramp re-pitched for a navy ground. Common borrows the platform's own
   muted blue-grey so it recedes; uncommon is the brand mint; epic, legendary
   and mythic climb violet, amber and magenta. Five hues that stay separable
   at badge size and none of which fight the interface. */
.t-common{--tier:#7a9eb0}
.t-uncommon{--tier:#00c8a0}
.t-epic{--tier:#8b7bd8}
.t-legendary{--tier:#f5a623}
.t-mythic{--tier:#ff4f8b}
<?php endif; ?>
.cell .rar{display:flex;align-items:center;gap:4px;padding:0 6px 5px;font-size:8.5px;
  line-height:1.2;color:var(--tier,var(--dim));letter-spacing:.04em;
  text-transform:uppercase;white-space:nowrap;overflow:hidden}
.cell .rar i{width:5px;height:5px;border-radius:50%;background:var(--tier,var(--line));
  flex:none;font-style:normal}
.cell .rar b{font-weight:400;color:var(--dim);margin-left:auto;letter-spacing:0;
  text-transform:none;font-variant-numeric:tabular-nums}
/* filter row */
.rarbar{display:flex;flex-wrap:wrap;gap:5px;padding:8px 10px 0}
.rarbar button{background:none;border:1px solid var(--line);color:var(--dim);font:inherit;
  font-size:9px;letter-spacing:.09em;text-transform:uppercase;padding:3px 8px;cursor:pointer;
  border-radius:999px;display:flex;align-items:center;gap:5px}
.rarbar button:hover{border-color:var(--tier,var(--ochre));color:var(--tier,var(--ochre))}
.rarbar button[aria-pressed="true"]{border-color:var(--tier,var(--ochre));
  color:var(--tier,var(--bone));background:rgba(255,255,255,.04)}
.rarbar button i{width:5px;height:5px;border-radius:50%;background:var(--tier,var(--dim));
  flex:none;font-style:normal}
.rarbar button .c{color:var(--dim);font-size:8.5px;letter-spacing:0}
.rarbar .sort{margin-left:auto;border-style:dashed}
.rarbar .sort:hover{border-color:var(--ochre);color:var(--ochre)}
.cell.blocked{opacity:.32;cursor:not-allowed;filter:grayscale(1)}
.cell.blocked:hover{border-color:var(--line)}
.cell.blocked img{background:var(--panel2)}
.conflict{margin:0 0 8px;padding:7px 9px;font-size:10.5px;line-height:1.5;
  border:1px solid var(--blood);background:rgba(208,70,58,.09);color:var(--bone);
  border-radius:2px;grid-column:1/-1}
.warn{margin:22px;padding:18px;border:1px solid var(--blood);background:rgba(208,70,58,.09);
  font-size:12.5px;line-height:1.7;border-radius:2px}
.warn code{color:var(--ochre)}
</style>
<div class="shell">
  <div class="stage">
    <div class="frame" id="frame">
      <div class="empty" id="empty">Pick a background and a torso to begin &mdash;<br>or hit Randomise.</div>
    </div>
    <div class="bar">
      <button class="btn primary" id="rand">Randomise</button>
      <button class="btn" id="randFull">Randomise (everything)</button>
      <button class="btn" id="clear">Clear</button>
      <button class="btn" id="share">Copy link to this build</button>
    </div>
    <div class="stack">
      <h3><span id="stackTitle">Draw order &mdash; back to front</span>
          <button type="button" id="resetOrder" hidden>Reset order</button></h3>
      <ol id="stackList"></ol>
    </div>
  </div>

  <div class="picker">
    <div class="tabs" id="tabs" role="tablist"></div>
    <div class="rarbar" id="rarbar"></div>
    <div class="grid" id="grid" role="tabpanel"></div>
  </div>
</div>
<script>
(function () {
  var BASE   = <?php echo json_encode($dhc_base); ?>;
  var SLOTS  = <?php echo json_encode(array_map(function ($k, $s) {
      return array('key'=>$k,'label'=>$s[0],'dir'=>$s[1],'optional'=>$s[2]);
  }, array_keys($dhc_slots), $dhc_slots)); ?>;
  var TRAITS = <?php echo json_encode($dhc_traits); ?>;
  var NOARMS = <?php echo json_encode($dhc_noarms); ?>;   // torsos with a hand-made armless variant

  /*
   * PER-TRAIT VERTICAL NUDGE, in pixels of the 1000px master, positive = down.
   *
   * A couple of weapons sit too high to meet the body. Tuned by eye by Oculus
   * Orbus over several passes, and against more than one torso -- the numbers
   * grew once torsos other than the usual one were checked, so they are not
   * "what looks right on a C-4589 Mechanic" but what clears the awkward ones
   * too.
   *
   * Applied at ASSEMBLY time rather than baked into the art, so the source files
   * stay as Maxingo drew them and the number is visible and adjustable here. It
   * is a percentage of the rendered height rather than a pixel count, so it
   * holds at whatever size the canvas happens to be -- a hardcoded 10px would
   * drift the moment the canvas was not exactly 1000 wide.
   *
   * Thumbnails are left alone; this is about how pieces meet in a build.
   */
  // The sash is tuned separately from its weapon. They are halves of one piece
  // but they meet the body at different points -- the sash lies on the chest,
  // the weapon hangs behind -- so the later correction that dropped the weapons
  // another 10px does not apply to it.
  var NUDGE = <?php echo json_encode(DHCF_NUDGE); ?>;

  /*
   * COMPANIONS NORMALLY DRAW LAST -- a pet or drone floats in front of the
   * Fighter. These are the exceptions, which belong against the body rather
   * than in front of it: the DH Vision Shoulder Cam mounts ON the shoulder, and
   * Code Sea Predator wraps the body. Drawn over, either one looks stuck to the
   * outside of the character, so the arms and headgear have to sit above them.
   *
   * Handled by reordering the draw sequence rather than adding an eleventh slot
   * for two traits. layerOrder() is the one definition of what draws when, and
   * both the canvas and the draw-order readout use it, so the readout can
   * never disagree with what you are looking at.
   */
  var COMPANION_UNDER = <?php echo json_encode(DHCF_COMPANION_UNDER); ?>;

  /* Exceptions, reported from testing: these weapons are drawn against the
     torso's own arms -- held in them, or posed to rest on them. An Arms trait
     replaces those arms with a different pose, so the weapon ends up floating
     with nothing supporting it. They are mutually exclusive with Arms. This is
     a rule about the art, not about draw order, so no amount of reordering
     fixes it and it is enforced on selection instead.

     Enforced both ways. Whichever slot is filled first blocks the other, and
     the way out is always the None tile, so no build can become unescapable. */
  var ARMS_EXCLUSIVE = <?php echo json_encode(DHCF_ARMS_EXCLUSIVE); ?>;

  function armsExclusive(slug) { return ARMS_EXCLUSIVE.indexOf(slug) !== -1; }

  /*
   * COUPLED WEAPONS. Three relationships, and they are NOT the same one:
   *
   *   Axe      -> Electric Morning Star   one-way. The axe always brings the
   *                                       morning star, but the morning star is
   *                                       a weapon in its own right and stands
   *                                       alone perfectly well.
   *   Scythe  <-> Scythe Sash             tethered. Two halves of one weapon;
   *   Krusher <-> Skull Krusher Sash      neither reads on its own, and a sash
   *                                       with nothing hanging from it is not a
   *                                       trait, so picking either brings both
   *                                       and dropping either drops both.
   *
   * `mutual` is the whole difference: it decides both whether the partner is
   * pulled in when you pick the second piece, and whether removing one removes
   * the other. For the one-way link the morning star is only cleared if the axe
   * put it there -- if you chose it yourself first, it is yours to keep.
   */
  var COUPLE = [
    { back: 'axe',           front: 'electric-morning-star', mutual: false },
    { back: 'scythe',        front: 'scythe-sash',           mutual: true  },
    { back: 'skull-krusher', front: 'skull-krusher-sash',    mutual: true  },
  ];
  var coupledIn = {};   // slot => true when a coupling, not the user, filled it

  function coupleFor(key, slug) {
    for (var i = 0; i < COUPLE.length; i++) {
      var c = COUPLE[i];
      if (key === 'weaponBack' && slug === c.back)  return c;
      if (key === 'weapon'     && slug === c.front) return c;
    }
    return null;
  }

  /* Pull in the partner when one half is chosen. */
  function applyCouple(key, slug) {
    var c = coupleFor(key, slug);
    if (!c) return;
    if (key === 'weaponBack') {
      // The axe stands on its own when arms rule its partner out, rather than
      // the arms ruling out the axe. The coupling is what the axe prefers, not
      // a condition of wearing it.
      if (!c.mutual && sel.arms && armsExclusive(c.front)) return;
      // Already wearing it by choice? Then it stays yours, and removing the axe
      // later leaves it be. Only a partner the coupling actually put there is
      // the coupling's to take away.
      if (sel.weapon !== c.front) { sel.weapon = c.front; coupledIn.weapon = true; }
    } else if (c.mutual) {
      sel.weaponBack = c.back; coupledIn.weaponBack = true;
    }
  }

  /* Drop the partner when a half is cleared or replaced. `was` is the outgoing
     slug, which is what decides whether a coupling was in force at all. */
  function releaseCouple(key, was) {
    var c = coupleFor(key, was);
    if (!c) return;
    if (key === 'weaponBack' && sel.weapon === c.front) {
      // tethered halves always go together; the morning star only if the axe brought it
      if (c.mutual || coupledIn.weapon) { delete sel.weapon; delete coupledIn.weapon; }
    } else if (key === 'weapon' && c.mutual && sel.weaponBack === c.back) {
      delete sel.weaponBack; delete coupledIn.weaponBack;
    }
  }

  /* One place both the grid and the None tile go through, so a selection can
     never be made without its coupling being considered. */
  function choose(key, slug) {
    var was = sel[key];
    if (was === slug) return;
    if (was) releaseCouple(key, was);
    if (slug) { sel[key] = slug; delete coupledIn[key]; applyCouple(key, slug); }
    else delete sel[key];
  }

  /*
   * TEMPORARY -- remove once the armless torsos are in.
   *
   * Perforator Arm Replacement reads better drawn BEHIND the torso than over
   * it, because the torso's own arms are still there underneath and drawing
   * the replacement on top leaves two sets of arms. Behind the torso is not
   * correct either, it just hides the seam better while we wait.
   *
   * So it retires itself. The moment a torso has a hand-made armless variant,
   * that variant is the real fix and this exception stops applying FOR THAT
   * TORSO -- the arms go back to drawing in their proper place over an armless
   * body. Nothing needs deleting as the armless set fills in; drop the last
   * one in and the workaround is simply never reached again.
   */
  var ARMS_BEHIND_TORSO = <?php echo json_encode(DHCF_ARMS_BEHIND_TORSO); ?>;


  /*
   * EFFECTS THAT BELONG BEHIND THE BODY.
   *
   * Effects normally draw just under the head, crossing the Fighter. These are
   * the ones that read as something the character is standing in rather than
   * something happening in front of them, so they drop to just behind the
   * torso -- still over the background and the rear weapon, but under the body.
   *
   * Per effects slot, so the trait behaves the same in Effects 1 or Effects 2,
   * and an ordinary effect in the other slot stays up under the head. With one
   * in both slots their relative order is preserved.
   */
  var EFFECTS_BEHIND_TORSO = <?php echo json_encode(DHCF_EFFECTS_BEHIND_TORSO); ?>;

  function effectBehindTorso(key) {
    return !!sel[key] && EFFECTS_BEHIND_TORSO.indexOf(sel[key]) !== -1;
  }

  function armsBehindTorso() {
    if (!sel.arms || ARMS_BEHIND_TORSO.indexOf(sel.arms) === -1) return false;
    if (sel.torso && NOARMS.indexOf(sel.torso) !== -1) return false;   // real fix available
    return true;
  }

  function traitBySlug(key, slug) {
    var l = TRAITS[key] || [];
    for (var i = 0; i < l.length; i++) if (l[i].slug === slug) return l[i];
    return null;
  }

  /* Why this trait cannot be picked right now, or null if it can. One function
     so the greyed-out cells, the tooltip and the banner can never disagree. */
  function blockedReason(key, slug) {
    /*
     * NO FREE COPIES. Traits are consumable: a copy committed to a saved
     * Fighter is spent until that Fighter is disassembled. The picker used to
     * offer anything the player OWNED, so a trait already wearing on a saved
     * Fighter still looked available -- it could be placed, and only the save
     * would refuse it, with a message about traits "already used" for a build
     * the player had just assembled.
     *
     * Copies placed in OTHER slots of the build in progress count too: the
     * same effect in both effects slots legitimately spends two.
     */
    var t = traitBySlug(key, slug);
    if (t && typeof t.free === 'number') {
      var elsewhere = 0;
      SLOTS.forEach(function (s) { if (s.key !== key && sel[s.key] === slug) elsewhere++; });
      if (t.free - elsewhere <= 0) {
        if (t.free <= 0) {
          return t.copies > 1
            ? 'All ' + t.copies + ' of your copies are in saved Fighters. Disassemble one to free a copy.'
            : 'Your only copy is in a saved Fighter. Disassemble it to free this trait.';
        }
        return 'Your ' + (t.copies > 1 ? t.copies + ' copies are' : 'only copy is')
             + ' already placed on this Fighter.';
      }
    }

    if (key === 'weapon' && armsExclusive(slug) && sel.arms)
      return nameOf('weapon', slug) + ' is drawn against the torso’s own arms, so it cannot be '
           + 'combined with an Arms trait. Set Arms to None first.';
    if (key === 'arms' && sel.weapon && armsExclusive(sel.weapon))
      return 'Arms traits repose the torso’s arms, which are what support '
           + nameOf('weapon', sel.weapon) + '. Set Weapon to None first.';
    return null;
  }

  function nameOf(key, slug) {
    var l = TRAITS[key] || [];
    for (var i = 0; i < l.length; i++) if (l[i].slug === slug) return l[i].name;
    return slug;
  }

  /* Last line of defence for selections that did not come from a click --
     Randomise and a hand-edited or older shared link. The weapon yields,
     because Arms is the slot with more to look at. */
  function dropConflicts() {
    // The weapon yields to the arms; anything that brought it stays. An axe with
    // no morning star is a fine Fighter, so only the blocked half is dropped.
    if (sel.arms && sel.weapon && armsExclusive(sel.weapon)) {
      delete sel.weapon; delete coupledIn.weapon;
    }
    // A sash is never a trait on its own, and neither half of a tethered pair
    // survives without the other -- a hand-edited link could have arrived that way.
    COUPLE.forEach(function (c) {
      if (!c.mutual) return;
      if (sel.weapon === c.front && sel.weaponBack !== c.back) sel.weaponBack = c.back;
      if (sel.weaponBack === c.back && sel.weapon !== c.front) sel.weapon = c.front;
    });
  }

  var customOrder = null;   // array of slot keys once the user has dragged

  function layerOrder() {
    if (customOrder) {
      // Dragged order wins outright, including over the shoulder-cam rule. The
      // point of dragging is to test arrangements the rules do not produce, so
      // silently re-applying an exception on top would defeat it.
      var byKey = {};
      SLOTS.forEach(function (s) { byKey[s.key] = s; });
      var out = [];
      customOrder.forEach(function (k) { if (byKey[k]) out.push(byKey[k]); });
      SLOTS.forEach(function (s) { if (out.indexOf(s) === -1) out.push(s); });
      return out;
    }
    var order = SLOTS.slice();
    if (sel.companion && COMPANION_UNDER.indexOf(sel.companion) !== -1) {
      var ci = order.map(function (s) { return s.key; }).indexOf('companion');
      var ai = order.map(function (s) { return s.key; }).indexOf('arms');
      if (ci > -1 && ai > -1) order.splice(ai, 0, order.splice(ci, 1)[0]);
    }
    if (armsBehindTorso()) {
      var k = order.map(function (s) { return s.key; });
      var a = k.indexOf('arms'), t = k.indexOf('torso');
      // a > t, so pulling arms out does not shift the torso index
      if (a > -1 && t > -1 && a > t) order.splice(t, 0, order.splice(a, 1)[0]);
    }
    ['effects1', 'effects2'].forEach(function (key) {
      if (!effectBehindTorso(key)) return;
      var k = order.map(function (s) { return s.key; });
      var e = k.indexOf(key), t = k.indexOf('torso');
      // e > t, so pulling the effect out does not shift the torso index
      if (e > -1 && t > -1 && e > t) order.splice(t, 0, order.splice(e, 1)[0]);
    });
    return order;
  }

  var sel = {}, hidden = {}, active = SLOTS[0].key, dragKey = null;
  var frame = document.getElementById('frame');
  var empty = document.getElementById('empty');
  var tabsEl = document.getElementById('tabs');
  var gridEl = document.getElementById('grid');
  var stackEl = document.getElementById('stackList');

  function url(dir, slug, size) { return BASE + '/' + size + '/' + dir + '/' + slug + '.png'; }
  function slotByKey(k) { for (var i=0;i<SLOTS.length;i++) if (SLOTS[i].key===k) return SLOTS[i]; }

  /* ---- render the composite. One <img> per slot, kept in DOM order so the
         browser paints them back-to-front exactly as SLOTS is declared. ---- */
  function paint() {
    SLOTS.forEach(function (s) {
      var id = 'L-' + s.key, el = document.getElementById(id);
      // A hidden layer keeps its selection but is not drawn at all -- removing
      // the <img> rather than setting opacity, so nothing can be half-visible.
      if (!sel[s.key] || hidden[s.key]) { if (el) el.remove(); return; }
      if (!el) {
        el = document.createElement('img');
        el.id = id; el.alt = '';
        frame.appendChild(el);
      }
      // Swap in the armless torso when arms are on and a variant exists for it.
      var dir = s.dir;
      // Hidden arms must bring the torso's own arms back, or hiding the arms
      // layer would leave an armless torso and nothing to explain it.
      if (s.key === 'torso' && sel.arms && !hidden.arms && NOARMS.indexOf(sel[s.key]) !== -1)
        dir = 'torso-noarms';
      var want = url(dir, sel[s.key], 1000);
      if (el.getAttribute('src') !== want) el.setAttribute('src', want);
      var n = NUDGE[sel[s.key]] || 0;
      el.style.transform = n ? 'translateY(' + (n / 10) + '%)' : '';
    });
    // keep DOM order == layer order, regardless of the order things were picked
    layerOrder().forEach(function (s) {
      var el = document.getElementById('L-' + s.key);
      if (el) frame.appendChild(el);
    });
    // "Nothing picked" and "everything hidden" both leave a blank canvas, and
    // they are not the same problem -- say which one you are looking at.
    var picked = Object.keys(sel).length;
    var drawn  = Object.keys(sel).filter(function (k) { return !hidden[k]; }).length;
    empty.style.display = drawn ? 'none' : 'flex';
    empty.textContent = !picked ? 'Pick traits to build a Fighter'
                                : 'Every layer is hidden — use the ● toggles to bring them back';
    paintStack();
    writeHash();
  }

  function paintStack() {
    stackEl.innerHTML = '';
    layerOrder().forEach(function (s, i) {
      var li = document.createElement('li');
      var chosen = sel[s.key];
      if (!chosen) li.className = 'off';
      var name = '—';
      if (chosen) {
        var list = TRAITS[s.key] || [];
        for (var j=0;j<list.length;j++) if (list[j].slug===chosen) name = list[j].name;
      }
      var tag = '';
      if (s.key === 'companion' && chosen && COMPANION_UNDER.indexOf(chosen) !== -1)
        tag = ' <em style="color:var(--teal);font-style:normal">behind arms</em>';
      if ((s.key === 'effects1' || s.key === 'effects2') && chosen && effectBehindTorso(s.key))
        tag = ' <em style="color:var(--teal);font-style:normal">behind torso</em>';
      if (s.key === 'arms' && chosen && armsBehindTorso())
        tag = ' <em style="color:var(--ochre);font-style:normal">behind torso &mdash; temporary</em>';
      if (s.key === 'torso' && chosen && sel.arms)
        tag = NOARMS.indexOf(chosen) !== -1
            ? ' <em style="color:var(--teal);font-style:normal">armless</em>'
            : ' <em style="color:var(--ochre);font-style:normal">arms underneath</em>';
      if (hidden[s.key]) li.classList.add('hid');
      li.innerHTML = '<span class="grip">&#8942;&#8942;</span><span class="n">' + (i+1) +
                     '</span><b>' + s.label + '</b><span>' + name + tag + '</span>';
      /*
       * Per-layer visibility. Distinct from None: None empties the slot, this
       * keeps the trait selected and simply stops drawing it, so you can look
       * at what a layer is covering and put it straight back. That is the whole
       * question with the arms-over-torso and weapon-over-body cases -- how much
       * of the thing underneath is actually hidden -- and toggling was the only
       * way to answer it without losing the build you were testing.
       */
      var eye = document.createElement('button');
      eye.type = 'button'; eye.className = 'eye';
      eye.setAttribute('aria-pressed', hidden[s.key] ? 'true' : 'false');
      eye.title = (hidden[s.key] ? 'Show ' : 'Hide ') + s.label;
      eye.innerHTML = hidden[s.key] ? '&#9676;' : '&#9679;';
      eye.addEventListener('click', function (e) {
        e.stopPropagation();
        if (hidden[s.key]) delete hidden[s.key]; else hidden[s.key] = true;
        paint();
      });
      // Dragging must start from the row, never from the button underneath it.
      eye.draggable = false;
      eye.addEventListener('dragstart', function (e) { e.preventDefault(); e.stopPropagation(); });
      li.appendChild(eye);
      /*
       * DRAGGABLE, because finding the exceptions is the job. The rules we have
       * were all discovered by looking at a wrong composite -- weapons over arms,
       * effects over faces, the shoulder cam floating -- so the fastest way to
       * find the next one is to let a person rearrange the stack directly and
       * watch the canvas update.
       */
      li.draggable = true;
      li.dataset.key = s.key;
      li.tabIndex = 0;
      li.title = 'Drag to reorder, or focus and press Alt + up/down';
      li.addEventListener('dragstart', function (e) {
        dragKey = s.key; li.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
        try { e.dataTransfer.setData('text/plain', s.key); } catch (err) {}
      });
      li.addEventListener('dragend', function () {
        dragKey = null; li.classList.remove('dragging');
        [].forEach.call(stackEl.children, function (c) { c.classList.remove('over'); });
      });
      li.addEventListener('dragover', function (e) {
        if (!dragKey || dragKey === s.key) return;
        e.preventDefault(); e.dataTransfer.dropEffect = 'move';
        li.classList.add('over');
      });
      li.addEventListener('dragleave', function () { li.classList.remove('over'); });
      li.addEventListener('drop', function (e) {
        e.preventDefault(); li.classList.remove('over');
        if (dragKey && dragKey !== s.key) moveLayer(dragKey, s.key);
      });
      li.addEventListener('keydown', function (e) {
        if (!e.altKey || (e.key !== 'ArrowUp' && e.key !== 'ArrowDown')) return;
        e.preventDefault();
        var keys = layerOrder().map(function (x) { return x.key; });
        var at = keys.indexOf(s.key), to = at + (e.key === 'ArrowUp' ? -1 : 1);
        if (to < 0 || to >= keys.length) return;
        moveLayer(s.key, keys[to]);
        var el = stackEl.querySelector('[data-key="' + s.key + '"]');
        if (el) el.focus();
      });
      stackEl.appendChild(li);
    });
    var custom = !!customOrder;
    document.getElementById('resetOrder').hidden = !custom;
    document.getElementById('stackTitle').innerHTML = custom
      ? 'Draw order &mdash; <span class="custom">rearranged</span>'
      : 'Draw order &mdash; back to front';
  }

  /* Move `key` to where `target` currently sits, then repaint from the new order. */
  function moveLayer(key, target) {
    var keys = layerOrder().map(function (s) { return s.key; });
    var from = keys.indexOf(key), to = keys.indexOf(target);
    if (from < 0 || to < 0) return;
    keys.splice(to, 0, keys.splice(from, 1)[0]);
    customOrder = keys;
    paint();
  }

  /* ---- picker ---- */
  /* Clears first: this is called again on every selection to repaint the
     dot markers, and an append-only version stacked a fresh set of ten tabs
     on each click. Idempotent here rather than relying on callers to reach
     for a separate refresh helper -- there is now only one function to call. */
  function buildTabs() {
    tabsEl.innerHTML = '';
    SLOTS.forEach(function (s) {
      var b = document.createElement('button');
      b.className = 'tab'; b.type = 'button'; b.setAttribute('role','tab');
      b.dataset.key = s.key;
      b.innerHTML = s.label + (sel[s.key] ? ' <span class="dot">&bull;</span>' : '');
      b.setAttribute('aria-selected', s.key === active ? 'true' : 'false');
      b.addEventListener('click', function () { active = s.key; buildTabs(); buildGrid(); });
      tabsEl.appendChild(b);
    });
  }

  /* ---- rarity filter, applied on top of whichever category tab is open ---- */
  var TIERS = ['common', 'uncommon', 'epic', 'legendary', 'mythic'];
  var rarityFilter = null;
  var rarbarEl = document.getElementById('rarbar');

  /* Rarest first by default -- the interesting traits are the ones you rarely
     see, and alphabetical buried them. TIERS is ordered commonest-first, so its
     index reversed gives the rank; rate breaks ties inside a tier, name breaks
     ties inside that. Untiered traits sort last either way, so a missing rarity
     table degrades to alphabetical rather than to something arbitrary. */
  var sortRarest = true;

  function sortByRarity(list) {
    return list.slice().sort(function (a, b) {
      var ai = TIERS.indexOf(a.tier), bi = TIERS.indexOf(b.tier);
      if (ai < 0 || bi < 0) return ai === bi ? a.name.localeCompare(b.name) : (ai < 0 ? 1 : -1);
      if (ai !== bi)           return sortRarest ? bi - ai : ai - bi;
      if (a.rate !== b.rate)   return sortRarest ? a.rate - b.rate : b.rate - a.rate;
      return a.name.localeCompare(b.name);
    });
  }

  function buildRarBar() {
    rarbarEl.innerHTML = '';
    var list = TRAITS[active] || [];
    if (!list.length || !list[0].tier) return;    // no rarity data loaded
    var all = document.createElement('button');
    all.type = 'button'; all.textContent = 'All';
    all.setAttribute('aria-pressed', rarityFilter ? 'false' : 'true');
    all.addEventListener('click', function () { rarityFilter = null; buildGrid(); });
    rarbarEl.appendChild(all);
    TIERS.forEach(function (tier) {
      // Count within THIS category, so the number tells you what the filter will
      // actually show rather than how many exist collection-wide.
      var n = list.filter(function (t) { return t.tier === tier; }).length;
      if (!n) return;
      var b = document.createElement('button');
      b.type = 'button'; b.className = 't-' + tier;
      b.innerHTML = '<i></i>' + tier + ' <span class="c">' + n + '</span>';
      b.setAttribute('aria-pressed', rarityFilter === tier ? 'true' : 'false');
      b.addEventListener('click', function () {
        rarityFilter = (rarityFilter === tier) ? null : tier;
        buildGrid();
      });
      rarbarEl.appendChild(b);
    });
    var sort = document.createElement('button');
    sort.type = 'button'; sort.className = 'sort';
    sort.innerHTML = sortRarest ? 'Rarest first &#9650;' : 'Commonest first &#9660;';
    sort.title = 'Sort by ' + (sortRarest ? 'commonest' : 'rarest') + ' first';
    sort.addEventListener('click', function () { sortRarest = !sortRarest; buildGrid(); });
    rarbarEl.appendChild(sort);
  }

  function buildGrid() {
    var s = slotByKey(active), list = TRAITS[active] || [];
    buildRarBar();          // safe: the bar's handlers call back into buildGrid, not vice versa
    gridEl.innerHTML = '';
    // Every category gets None, including Background, Torso and Head. This is a
    // sandbox for inspecting layers -- being able to drop the torso and see what
    // sits behind it is the point, so nothing is mandatory.
    var none = document.createElement('button');
    none.className = 'cell none'; none.type = 'button'; none.title = 'Remove from canvas';
    none.setAttribute('aria-pressed', !sel[active] ? 'true' : 'false');
    none.innerHTML = '<img alt=""><span>None</span>';
    none.addEventListener('click', function () { choose(active, null); buildTabs(); paint(); buildGrid(); });
    gridEl.appendChild(none);
    // If anything in this category is ruled out, say so once at the top rather
    // than leaving the reader to hover a greyed tile to find out why.
    var banner = null;
    var shown = 0;
    sortByRarity(list).forEach(function (t) {
      if (rarityFilter && t.tier !== rarityFilter) return;   // secondary filter
      shown++;
      var why = blockedReason(active, t.slug);
      var b = document.createElement('button');
      b.className = 'cell' + (why ? ' blocked' : '') + (t.tier ? ' t-' + t.tier : '');
      b.type = 'button';
      b.title = why || (t.name + (t.tier ? ' — ' + t.tier + ', ' + t.rate + '% drop'
              + (t.worn ? ' (' + t.worn + ' of the 226 original fighters)'
                        : ' (in no original fighter)') : ''));
      if (why) { b.disabled = true; if (!banner) banner = why; }
      b.setAttribute('aria-pressed', sel[active] === t.slug ? 'true' : 'false');
      var i = document.createElement('img');
      i.loading = 'lazy'; i.alt = t.name; i.src = url(s.dir, t.slug, 250);
      var sp = document.createElement('span'); sp.textContent = t.name;
      b.appendChild(i); b.appendChild(sp);
      if (t.tier) {
        var r = document.createElement('span');
        r.className = 'rar';
        // In fighters mode the copy count matters more than the drop rate --
        // it is what decides whether you can build a second Fighter with it.
        var right = (typeof t.free === 'number')
          ? (t.free + (t.copies > 1 ? '/' + t.copies : '') + ' free')
          : t.rate + '%';
        r.innerHTML = '<i></i>' + t.tier + '<b>' + right + '</b>';
        b.appendChild(r);
      }
      b.addEventListener('click', function () {
        choose(active, t.slug); buildTabs(); paint(); buildGrid();
      });
      gridEl.appendChild(b);
    });
    if (rarityFilter && !shown) {
      var none2 = document.createElement('p');
      none2.className = 'conflict';
      none2.textContent = 'No ' + rarityFilter + ' traits in ' + s.label + '.';
      gridEl.appendChild(none2);
    }
    if (banner) {
      var n = document.createElement('p');
      n.className = 'conflict'; n.textContent = banner;
      gridEl.insertBefore(n, gridEl.firstChild);
    }
  }

  /* ---- shuffle ---- */
  /* Weighted by drop rate, so Randomise actually demonstrates the rarity curve
     instead of showing a mythic as often as a common. Falls back to a flat pick
     when no rarity data is loaded. */
  function pick(k) {
    var l = TRAITS[k] || [];
    if (!l.length) return null;
    var total = 0, i;
    for (i = 0; i < l.length; i++) total += (l[i].rate || 0);
    if (total <= 0) return l[Math.floor(Math.random()*l.length)].slug;
    var r = Math.random() * total;
    for (i = 0; i < l.length; i++) { r -= (l[i].rate || 0); if (r <= 0) return l[i].slug; }
    return l[l.length-1].slug;
  }
  /*
   * HOW OFTEN A SLOT IS ACTUALLY FILLED, measured across the 226 minted
   * Fighters rather than guessed. The guessed numbers this replaces had every
   * optional slot at 45%, which dressed each shuffle in one of everything --
   * real Fighters are much sparer than that. Weapons in particular appear in
   * only a quarter of them.
   *
   * Background, Torso and Head are always filled and are not listed. On chain
   * Torso and Head are 97.8% and Background 66.8%, but all three are the body
   * of the character here, and a shuffle that hands you a headless torso on no
   * background is not a useful starting point.
   */
  var FILL = { arms: 0.296, headgear: 0.447, companion: 0.177, effects1: 0.522,
  // Effects 2 is CONDITIONAL on Effects 1, so this is 13.3/52.2 rather than the
  // 13.3% it works out to overall. Using the flat figure here would multiply by
  // the Effects 1 roll and land at 7%.
               effects2: 0.255 };
  var WEAPON_FILL = 0.248;

  /* The other half of a two-part weapon, or null. Derived from the suffix the
     halves are named with -- "-1" for the three Maxingo delivered split, "-sash"
     for the scythe and skull krusher -- so a new split weapon following either
     convention pairs up without code changes. */
  function weaponPartner(slug, intoKey) {
    var tries = [];
    ['-1', '-sash'].forEach(function (suf) {
      tries.push(slug.slice(-suf.length) === suf ? slug.slice(0, -suf.length) : slug + suf);
    });
    var list = TRAITS[intoKey] || [];
    for (var i = 0; i < list.length; i++)
      if (tries.indexOf(list[i].slug) !== -1) return list[i].slug;
    return null;
  }

  function randomise(all) {
    sel = {};
    hidden = {};   // a fresh shuffle starts fully visible
    SLOTS.forEach(function (s) {
      if (s.key === 'weapon' || s.key === 'weaponBack') return;   // handled together below
      if (!s.optional) { var v = pick(s.key); if (v) sel[s.key] = v; return; }
      // Effects 2 never appears without Effects 1 in the collection -- not once
      // in 226 -- so it is a second effect on top of a first, not a slot of its own.
      if (s.key === 'effects2' && !all && !sel.effects1) return;
      if (all || Math.random() < (FILL[s.key] || 0.3)) { var x = pick(s.key); if (x) sel[s.key] = x; }
    });
    /*
     * ONE weapon, from both slots' pools together. Rolling each slot separately
     * gave a Fighter a front weapon and an unrelated back weapon at the same
     * time, and skipping the back slot entirely -- the old behaviour -- meant
     * the seven back-only weapons never turned up at all.
     */
    if (all || Math.random() < WEAPON_FILL) {
      var pool = ['weapon', 'weaponBack'][Math.random() < 0.5 ? 0 : 1];
      // Arms repose the torso's arms, so the weapons that rely on them are out
      // of the running once arms are on rather than picked and then discarded.
      var cands = (TRAITS[pool] || []).filter(function (t) {
        return !(sel.arms && armsExclusive(t.slug));
      });
      if (cands.length) {
        var total = 0, i;
        for (i = 0; i < cands.length; i++) total += (cands[i].rate || 1);
        var r = Math.random() * total, chosen = cands[cands.length - 1].slug;
        for (i = 0; i < cands.length; i++) { r -= (cands[i].rate || 1); if (r <= 0) { chosen = cands[i].slug; break; } }
        sel[pool] = chosen;
        // Couplings first -- a sash must never be rolled on its own, and the axe
        // brings the morning star unless arms have ruled it out.
        applyCouple(pool, chosen);
        var otherKey = pool === 'weapon' ? 'weaponBack' : 'weapon';
        if (!sel[otherKey]) {
          var mate = weaponPartner(chosen, otherKey);
          if (mate) sel[otherKey] = mate;   // the "-1" halves Maxingo delivered split
        }
      }
    }
    dropConflicts();
    buildTabs(); paint(); buildGrid();
  }

  /* ---- share a build in the url, so a combination can be sent to someone ---- */
  function writeHash() {
    var parts = [];
    SLOTS.forEach(function (s) { if (sel[s.key]) parts.push(s.key + '=' + sel[s.key]); });
    // A rearranged stack travels in the link too, so a finding can be sent as a
    // url rather than described in prose.
    if (customOrder) parts.push('order=' + customOrder.join(','));
    var hid = Object.keys(hidden).filter(function (k) { return hidden[k]; });
    if (hid.length) parts.push('hide=' + hid.join(','));
    history.replaceState(null, '', parts.length ? '#' + parts.join('&') : location.pathname);
  }
  function readHash() {
    var h = location.hash.replace(/^#/, '');
    if (!h) return false;
    var got = false;
    h.split('&').forEach(function (p) {
      var kv = p.split('=');
      if (kv[0] === 'order' && kv[1]) { customOrder = kv[1].split(','); got = true; return; }
      if (kv[0] === 'hide' && kv[1]) {
        kv[1].split(',').forEach(function (k) { if (slotByKey(k)) hidden[k] = true; });
        got = true; return;
      }
      var s = slotByKey(kv[0]);
      if (!s || !kv[1]) return;
      var list = TRAITS[kv[0]] || [];
      for (var i=0;i<list.length;i++) if (list[i].slug === kv[1]) { sel[kv[0]] = kv[1]; got = true; }
    });
    dropConflicts();   // links shared before the rule existed still open cleanly
    return got;
  }

  document.getElementById('rand').addEventListener('click', function () { randomise(false); });
  document.getElementById('randFull').addEventListener('click', function () { randomise(true); });
  document.getElementById('resetOrder').addEventListener('click', function () {
    customOrder = null; paint();
  });
  document.getElementById('clear').addEventListener('click', function () {
    sel = {}; hidden = {}; buildTabs(); paint(); buildGrid();
  });
  document.getElementById('share').addEventListener('click', function () {
    var btn = this, was = btn.textContent;
    writeHash();
    var done = function () { btn.textContent = 'Link copied'; setTimeout(function(){ btn.textContent = was; }, 1600); };
    if (navigator.clipboard) navigator.clipboard.writeText(location.href).then(done, done); else done();
  });

  buildTabs();
  if (!readHash()) randomise(false); else { buildTabs(); paint(); }

  /* The one thing the outside world can ask for: what is currently on the
     canvas. Returned as a copy so a caller cannot mutate the live selection,
     and read from `sel` rather than the DOM so a save stores exactly what was
     drawn. */
  window.DHC_SELECTION = function () {
    var out = {};
    SLOTS.forEach(function (s) { if (sel[s.key]) out[s.key] = sel[s.key]; });
    return out;
  };
  buildGrid();
})();
</script>
