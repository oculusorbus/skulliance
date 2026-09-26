<?php
/**
 * dhcarena.php — DHC Arena.
 *
 * The real thing. dhcarena-prototype.php proved the game; this is it wired to
 * the platform: real Fighters, a real Crew, a real opponent, a server that owns
 * every rule, and traits that actually land in the ledger.
 *
 * WHAT THE CLIENT DOES AND DOES NOT DO. It draws the board and animates what it
 * is told. It does NOT decide anything: a move is sent to
 * ajax/dhcarena-action.php as "slide A to B", the engine resolves the whole
 * exchange including the defending Crew's replies, and the reply carries both
 * the new state and an fx TIMELINE to play back. Nothing the browser says can
 * change a number.
 *
 * Design: dhcarena.md. Rules: dhcarena-engine.php. Economy: dhcarena-lib.php.
 * Tables: dhcarena-schema.md, run once by hand.
 */
include_once 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
require_once __DIR__ . '/dhcarena-lib.php';
include 'header.php';

$user_id   = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
$crew      = $user_id ? dhca_crew($conn, $user_id) : array();
$available = dhca_available($crew);
$block     = $user_id ? dhca_entry_block($conn, $user_id) : 'Sign in to enter the Arena.';
$spent     = $user_id ? dhca_battles_today($conn, $user_id) : 0;
$foesList  = $user_id ? dhca_opponents($conn, $user_id) : array();
$ladder    = dhca_ladder($conn, null, 10);

/*
 * WHAT EACH FIGHTER WILL ACTUALLY BE, derived here so the picker can sort and
 * filter on it. Same function the battle uses, so a card cannot promise
 * something the Arena then disagrees with. Rarity score stays on the card too,
 * but it is no longer the only thing a Crew can be chosen on -- which it was,
 * and which is the one number §4 refuses to let decide a fight.
 */
$rarity   = dhcf_rarity();
$kitCount = array();
foreach ($crew as $i => $f) {
	$built = dhca_build_fighter(is_array($f['traits']) ? $f['traits'] : array(),
	                            '', 'p'.$f['id'], $rarity);
	$crew[$i]['hp']   = (int)$built['maxHp'];
	$crew[$i]['pow']  = (int)$built['power'];
	$crew[$i]['crit'] = round($built['critC'] * 100);
	$crew[$i]['kit']  = $built['kit'];
	$kitCount[$built['kit']['id']] = (isset($kitCount[$built['kit']['id']]) ? $kitCount[$built['kit']['id']] : 0) + 1;
}
$allKits = array();
foreach (dhca_kits() as $k) if (!empty($kitCount[$k['id']])) $allKits[] = $k;

/* Art root, resolved the same way the assembler resolves it. */
$ART = '';
foreach (array('dhc/web','web','dhc','traits') as $c) {
	if (is_dir(__DIR__ . '/' . $c . '/1000')) { $ART = $c; break; }
}
?>
<div class="arena-wrap">
<style>
/* ================== TWO SKINS, AND THE SEAM BETWEEN THEM ====================
   THE SELECTION SCREEN IS A PLATFORM PAGE. It sits in the platform header, next
   to platform navigation, and it should look like everything else on
   Skulliance -- same ground, same accent, same typeface. It had been wearing
   the prototype's own dark monospace skin, which was written for a standalone
   page with nothing above it, and next to the real header it read as a foreign
   object bolted onto a navy interface.

   THE BATTLE IS A GAME WORLD and keeps its own. That is the same call
   dhc-assembler.php makes about the sandbox, and for the same reason: a board
   with five gem colours, damage reds and shield blues cannot be recoloured to
   a UI palette without losing what the colours are for.

   So the palette below is Skulliance, sampled from the platform's own
   stylesheet exactly as the assembler samples it -- #07111d ground, #00c8a0
   accent, #7a9eb0 muted -- and #arenaBattle further down overrides the whole
   set with the game's.
   ========================================================================== */
.arena-wrap{--ink:#07111d;--panel:#0a1929;--panel2:#0d1e2e;--line:#1b3346;
  --bone:#e8eaed;--dim:#7a9eb0;--teal:#00a882;--ochre:#00c8a0;--blood:#00c8a0;
  --shield:#5aa9ff;--warn:#d0463a;}
.arena-wrap,.arena-wrap *{box-sizing:border-box}
/* No background and no font of its own: the page ground and Arial come from
   the platform, which is the whole point. */
.arena-wrap{margin:0}
/* The board's own world. Everything the game draws lives in here. */
.arena-wrap #arenaBattle{--ink:#0d0f13;--panel:#151922;--panel2:#1d2230;--line:#2b3345;
  --bone:#e8e6e1;--dim:#8b93a7;--teal:#00c8a0;--ochre:#f5a623;--blood:#e0466b;
  --shield:#5aa9ff;--warn:#e0466b;
  --g0:#e0466b;--g1:#f5a623;--g2:#8b7bd8;--g3:#5aa9ff;--g4:#00c8a0;
  background:var(--ink);color:var(--bone);border:1px solid var(--line);border-radius:4px;
  padding:10px;font:13px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  -webkit-user-select:none;user-select:none}
/* NO MAX-WIDTH OF ITS OWN. The platform's .container already caps at 2000px and
   centres, so capping again here just made the Arena 500px narrower than the
   header sitting above it. The prototype was a standalone page and needed its
   own cap; this is not, and dhcfighters.php learned the same lesson already --
   see the note on .dhcf-wrap. The board is still capped separately, against
   viewport HEIGHT, which is a different question and still the right one. */
.arena-wrap{max-width:100%;margin:0 auto;padding:12px;overflow-x:clip}
/* Nothing may scroll the page sideways. A phone has no spare width and a
   horizontal scrollbar makes a board feel broken to drag on. */
.arena-wrap{max-width:100%;overflow-x:clip}
.arena-wrap h1{font-size:15px;letter-spacing:.14em;text-transform:uppercase;margin:0 0 2px}
.arena-wrap .sub{color:var(--dim);font-size:10.5px;margin:0 0 10px}
.arena-wrap button{font:inherit;cursor:pointer;border-radius:3px}
.arena-wrap .btn{background:var(--panel2);color:var(--bone);border:1px solid var(--line);padding:6px 12px}
.arena-wrap .btn:hover{border-color:var(--ochre);color:var(--ochre)}
.arena-wrap .btn.go{background:var(--blood);border-color:var(--blood);color:#fff}
.arena-wrap .top{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
.arena-wrap #howto{display:none;padding:6px 11px}
.arena-wrap .turnflag{font-size:10px;letter-spacing:.12em;text-transform:uppercase;padding:3px 9px;
  border-radius:999px;border:1px solid var(--line);color:var(--dim)}
.arena-wrap .turnflag.you{border-color:var(--teal);color:var(--teal)}
.arena-wrap .turnflag.foe{border-color:var(--blood);color:var(--blood)}
/* The Fighter columns SCALE rather than sitting at a fixed width. A flat 320px
   looked right on a 2000px screen and quietly stole the board's width on a
   1440px laptop, where the board is limited by what is left over rather than by
   height -- so widening the sides made the board smaller on exactly the screens
   that had least to spare. Scaling by viewport keeps the height cap in charge
   on every normal desktop and still lets the Fighters grow on a big monitor. */
.arena-wrap .arena{position:relative;display:grid;
  grid-template-columns:minmax(0,clamp(230px,19vw,340px)) minmax(0,1fr)
                        minmax(0,clamp(230px,19vw,340px));
  gap:16px;align-items:start}
/* The board must not simply eat the extra width -- a 1000px square does not fit
   a laptop viewport -- so it is capped against viewport HEIGHT and centred, and
   the width that frees up goes to the Fighters, which is the point.
   The cap belongs to the whole column, not just the board: applied only to the
   boardwrap it left the reach line and the legend stretching the full column
   width, so they ran wider than the thing they describe.

   THE SECOND NUMBER IS A CEILING, NOT THE ANSWER. It was 760px, which on any
   screen taller than about 1030px is SMALLER than the height cap -- so the
   board stopped growing at 760 and left the rest of the screen empty, which is
   not what a height cap is for.

   The target is not the viewport, it is the COLUMN BESIDE IT. Three Fighters at
   210px of art each, plus their names, kits and health bars, come to about
   940px, and the grid row is as tall as its tallest column -- so a 760px board
   sat in a 940px row with a couple of hundred pixels of nothing beside it.
   82vh lands the board column within a few pixels of both the Fighters beside
   it and the bottom of a 1069px window, which is what makes the three columns
   read as one thing and keeps the battle log in view.

   The pixel ceiling only catches what the height cap cannot: a short, very wide
   window, where 84vh of WIDTH would run past the Fighters. */
.arena-wrap .boardcol > *{max-width:min(82vh,1180px);margin-left:auto;margin-right:auto}
.arena-wrap .teamcol{display:grid;gap:6px}
.arena-wrap .coltag{font-size:9px;letter-spacing:.16em;text-transform:uppercase;margin-bottom:5px;
  padding-bottom:4px;border-bottom:1px solid var(--line);
  display:flex;align-items:center;justify-content:space-between;gap:8px}
/* CHARGE IS A TEAM METER. Every living Fighter gains it together, so showing it
   three times in three token corners was repetition -- and once the text was
   shortened to "⚡ 7" it stopped saying what it was counting towards, which is
   the only thing you actually want to know. One bar per Crew, beside the name. */
.arena-wrap .chg{display:flex;align-items:center;gap:5px;letter-spacing:0;text-transform:none;
  font-size:9px;color:var(--dim);white-space:nowrap}
.arena-wrap .chg i{display:block;width:46px;height:5px;border-radius:3px;background:#0b0d11;
  overflow:hidden;flex:none}
.arena-wrap .chg i b{display:block;height:100%;width:0;background:var(--g4);
  transition:width .35s cubic-bezier(.2,.7,.3,1)}
.arena-wrap .chg.full{color:var(--g4);animation:chgPulse 1s ease-in-out infinite}
.arena-wrap .chg.full i b{box-shadow:0 0 8px var(--g4)}
@keyframes chgPulse{0%,100%{opacity:1}50%{opacity:.55}}
.arena-wrap .coltag.you{color:var(--teal)}
.arena-wrap .coltag.foe{color:var(--blood)}
.arena-wrap .boardcol{min-width:0}
.arena-wrap .legend.mobonly, .arena-wrap .reach.mobonly{display:none}
@media (max-width:1000px){/* Stack, and put the enemies above the board where they read as the opposition */
  .arena-wrap .arena{grid-template-columns:1fr}.arena-wrap .teamcol{grid-template-columns:repeat(3,minmax(0,1fr))}.arena-wrap .teamwrap.foes{order:-1}.arena-wrap .teamwrap.mine{order:1}/* Enemies, board and your Crew have to fit one screen together, so the
     legend goes below all three rather than wedging between the board and
     your own Fighters. */
  .arena-wrap .legend.deskonly, .arena-wrap .reach.deskonly{display:none}.arena-wrap .legend.mobonly{display:flex;margin-top:8px}.arena-wrap .reach.mobonly{display:flex;margin-top:10px}/* PACKED TIGHT. Every gap between your Crew, the board and theirs is space
     the board could be using, and on a phone the three have to share one
     screen. The title hides with the intro rather than being deleted -- both
     come back together behind the ? button, so nothing is lost, it is just not
     paying rent on a 750px screen. */
  .arena-wrap{padding:8px}.arena-wrap .arena{gap:5px}.arena-wrap .arenabg{inset:0}/* no bleed: it was pushing 6px past the viewport */
  .arena-wrap .boardwrap{padding:5px}.arena-wrap .coltag{font-size:8px;margin-bottom:2px;padding-bottom:2px}.arena-wrap .top{margin-bottom:6px;gap:6px}.arena-wrap .teamcol{gap:4px}/* Squat tokens. Three-up at phone width the art would be square at ~128px, and two rows of that plus the board came to about 794px -- over the fold on
     most phones even after the legend moved. 96px brings the core play area
     near 730px, which fits. The whole figure still shows; it is letterboxed
     into a shorter frame rather than cropped. */
  .arena-wrap .teamcol .tok .art{aspect-ratio:auto;height:96px}/* Three tokens shoulder to shoulder with a 4px gap: an 8% pop-out overlaps
     its neighbours. Smaller on a phone, full size in the desktop columns. */
  .arena-wrap .tok.act{animation:actSmall .34s}.arena-wrap .teamcol .tok{padding:4px}.arena-wrap .teamcol .tok .nm{font-size:9px}.arena-wrap .teamcol .tok .kitn{font-size:7.5px}.arena-wrap .coltag{margin-bottom:3px;padding-bottom:3px}/* Title and intro are one unit behind the ? -- a wall of rules plus a
     heading costs most of a screen on a phone, and neither is needed while
     you are playing. One tap brings both back. */
  .arena-wrap.playing h1, .arena-wrap.playing .a-sub, .arena-wrap.playing .a-stats{display:none}
  .arena-wrap.playing.showintro h1, .arena-wrap.playing.showintro .a-sub{display:block}
  .arena-wrap #howto{display:inline-block}
}
/* ---- teams ---- */
.arena-wrap .tok{background:var(--panel2);border:1px solid var(--line);border-radius:3px;padding:5px;
  position:relative;transition:transform .16s,opacity .3s,border-color .15s}
.arena-wrap .tok{border-left:3px solid var(--gem)}
.arena-wrap .tok .rk{font-size:7.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);
  display:flex;justify-content:space-between;gap:4px}
.arena-wrap .tok .mygem{display:inline-flex;align-items:center;justify-content:center;
  width:19px;height:19px;border-radius:50%;background:var(--gem);font-size:11px;
  box-shadow:inset 0 -2px 4px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.12);flex:none}
.arena-wrap .tok .art{position:relative;width:100%;aspect-ratio:1;overflow:hidden;border-radius:2px;
  background:repeating-conic-gradient(#191419 0% 25%,#201b20 0% 50%) 50%/9px 9px;margin:2px 0}
.arena-wrap .tok .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
/* PER-LAYER MOTION. Only possible because a Fighter is still separate images at
   render time -- the same separation the layering rules needed.
   Kept SMALL on purpose. Each layer is a full 250px frame that is mostly
   transparent, so it rotates about the frame's centre rather than the weapon's
   grip, and .art clips at the edge. A few degrees reads as a swing; twenty
   would read as the picture coming apart.
   Transform only, no filters: transforms are composited on the GPU, so this
   costs nothing per frame, which matters after the audio lesson. */
.arena-wrap .tok .art img[data-l="weapon"]{transform-origin:50% 65%}
.arena-wrap .tok .art img.swing{animation:swing .34s ease-out}
@keyframes swing{0%{transform:rotate(0) translateX(0)}30%{transform:rotate(-7deg) translateX(-2%)}55%{transform:rotate(4deg) translateX(1%)}100%{transform:rotate(0) translateX(0)}}
.arena-wrap .tok .art img.bob{animation:bob .42s ease-in-out}
@keyframes bob{0%{transform:translateY(0)}40%{transform:translateY(-5%)}100%{transform:translateY(0)}}
.arena-wrap .tok .art img.jolt{animation:jolt .30s ease-out}
@keyframes jolt{0%{transform:translateX(0)}35%{transform:translateX(3%) rotate(2deg)}100%{transform:translateX(0)}}
/* the Fighter's own background fills the frame and sits well back */
/* NO TREATMENT. The background is drawn as it is, same as the assembler and
   the gallery draw it. It had a brightness filter, a saturate filter and a
   dark scrim stacked on it to keep the figure readable, and the result was
   art that looked broken rather than art that sat back. If a busy background
   ever does swallow a figure, that is a reason to say so about that trait --
   not to dim all 42 of them. */
.arena-wrap .tok .art img.bg{object-fit:cover}
.arena-wrap .tok .nm{font-size:9.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .tok .kitn{font-size:8px;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .hpwrap{position:relative;height:6px;background:#0b0d11;border-radius:2px;overflow:hidden;margin-top:3px}
.arena-wrap .hp{position:absolute;inset:0;background:var(--teal);transform-origin:left;
  transition:transform .4s cubic-bezier(.2,.7,.3,1)}
.arena-wrap .hp.low{background:var(--ochre)}
.arena-wrap .hp.crit{background:var(--blood)}
.arena-wrap .sh{position:absolute;top:0;left:0;height:100%;background:var(--shield);opacity:.8;transition:width .4s}
/* Fixed height and no wrapping. This line gains "sh 24" and "charge 7/10" the
   moment Shield or Charge match, and on a phone-width token that wrapped to a
   second line -- so the token grew, the row shoved, and the Crew jolted. On
   desktop there was room, so it never wrapped and nothing moved: the same
   report from both ends. */
.arena-wrap .hpn{font-size:8px;color:var(--dim);font-variant-numeric:tabular-nums;display:flex;
  justify-content:space-between;gap:4px;white-space:nowrap;overflow:hidden;
  height:12px;line-height:12px}
.arena-wrap .hpn span{overflow:hidden;text-overflow:ellipsis}
/* The layers now carry the fade themselves, so the token only desaturates.
   Stacking the old opacity:.3 on top of layers that already end near .2 left
   the art at about 6% -- a fallen Fighter should read as a wreck you can still
   name, not an empty box. The text stays legible for the same reason: whose
   Fighter died, and on what HP, is information. */
.arena-wrap .tok.ko{opacity:.8;filter:grayscale(1);transition:opacity .5s,filter .5s}
.arena-wrap .tok.ko .nm{text-decoration:line-through}
/* While a Fighter is coming apart it must not already be greyed out -- the
   whole point is watching it happen. Three classes beats two, so this wins
   over .tok.ko until the sequence finishes. */
.arena-wrap .tok.ko.dying{opacity:1;filter:none}
.arena-wrap .tok.dying{animation:deathShake .55s}
@keyframes deathShake{0%{transform:translateX(0)}12%{transform:translateX(-4%) rotate(-1.5deg)}30%{transform:translateX(3.5%) rotate(1.2deg)}52%{transform:translateX(-2.5%) rotate(-.8deg)}74%{transform:translateX(1.5%)}100%{transform:translateX(0)}}
/* TRAIT BY TRAIT. Each layer falls out of the composite on its own, staggered
   from the top of the stack down, so a Fighter comes apart in the order it was
   assembled rather than simply fading. Two variants so pieces do not all spin
   the same way -- alternating by index reads as debris, one direction reads as
   a single object rotating.
   Ends at .22 rather than 0 and stays there (forwards): the fallen Fighter
   should be a scattered wreck you can still recognise, not an empty frame. */
.arena-wrap .tok .art img.dis{animation:disA .62s cubic-bezier(.3,0,.7,1) forwards}
.arena-wrap .tok .art img.dis.alt{animation-name:disB}
@keyframes disA{0%{transform:none;opacity:1}16%{transform:translateY(-4%) scale(1.04);opacity:1}100%{transform:translateY(28%) rotate(9deg) scale(.84);opacity:.22}}
@keyframes disB{0%{transform:none;opacity:1}16%{transform:translateY(-5%) scale(1.05);opacity:1}100%{transform:translateY(24%) rotate(-11deg) scale(.86);opacity:.22}}
.arena-wrap .tok.hit{animation:hit .3s}
@keyframes hit{0%{transform:translateX(0)}30%{transform:translateX(-3%)}60%{transform:translateX(2.4%)}100%{transform:translateX(0)}}
.arena-wrap .tok.act{animation:act .34s}
@keyframes act{0%{transform:scale(1)}40%{transform:scale(1.08)}100%{transform:scale(1)}}
@keyframes actSmall{0%{transform:scale(1)}40%{transform:scale(1.035)}100%{transform:scale(1)}}
/* z-index matters here and was missing. .flash is the token's FIRST child with
   no z-index, and .tok .art is position:relative and comes after it -- so with
   both at auto they paint in DOM order and the ART COVERED THE FLASH. Damage
   has been flashing behind the character all along, which is most of why a hit
   did not read. Above the art now, below the damage number. */
.arena-wrap .flash{position:absolute;inset:0;opacity:0;pointer-events:none;border-radius:3px;z-index:4;
  background:var(--blood)}
.arena-wrap .flash.on{animation:fl .30s}
@keyframes fl{0%{opacity:.50}100%{opacity:0}}
/* A crit is white and lingers -- it should look different from a normal hit, not merely bigger. */
.arena-wrap .flash.big{background:#fff}
.arena-wrap .flash.big.on{animation:flBig .40s}
@keyframes flBig{0%{opacity:.80}35%{opacity:.35}100%{opacity:0}}
/* Healing and shielding get their own colours, so Drain reads as taking
   something rather than only as damage. */
.arena-wrap .flash.heal{background:var(--teal)}
.arena-wrap .flash.shield{background:var(--shield)}
/* AT RISK. A Fighter whose bar has gone red pulses, because the bar is 8px of a
   270px token and on a phone it is 8px of a three-across row -- the one piece
   of information you most need mid-move was the smallest thing on screen.
   Same threshold as .hp.crit, deliberately: the glow and the red bar must never
   disagree about who is about to die.

   ITS OWN ELEMENT, not an animation on .tok. The token already animates on
   .act, .hit and .hit.big, and `animation` is one property -- a pulse declared
   there would be cancelled by the next hit and would cancel the lunge in turn.
   A separate layer pulses continuously underneath all of that. Under .flash
   (z-index 4) so an incoming hit still reads over the warning. */
.arena-wrap .danger{position:absolute;inset:0;opacity:0;pointer-events:none;border-radius:3px;
  z-index:3;box-shadow:inset 0 0 0 2px var(--blood),inset 0 0 14px rgba(224,70,107,.55)}
.arena-wrap .tok.crit .danger{animation:danger 1.15s ease-in-out infinite}
@keyframes danger{0%,100%{opacity:.30}50%{opacity:1}}
/* The bar breathes with it, so the two read as one signal rather than two. */
.arena-wrap .tok.crit .hp.crit{animation:dangerBar 1.15s ease-in-out infinite}
@keyframes dangerBar{0%,100%{opacity:1}50%{opacity:.55}}
/* A Fighter who is already down is not at risk of anything. */
.arena-wrap .tok.ko .danger{animation:none;opacity:0}
.arena-wrap .tok.hit.big{animation:hitBig .40s}
@keyframes hitBig{0%{transform:translateX(0)}20%{transform:translateX(-5%)}45%{transform:translateX(4%)}70%{transform:translateX(-2%)}100%{transform:translateX(0)}}
.arena-wrap .pop{position:absolute;left:50%;top:22%;transform:translateX(-50%);font-size:14px;font-weight:700;
  pointer-events:none;opacity:0;text-shadow:0 2px 6px #000;z-index:5;white-space:nowrap}
.arena-wrap .pop.on{animation:pp .9s}
.arena-wrap .pop.heal{color:var(--teal)}
.arena-wrap .pop.big{font-size:19px;color:var(--ochre)}
@keyframes pp{0%{opacity:0;transform:translate(-50%,6px)}18%{opacity:1}100%{opacity:0;transform:translate(-50%,-26px)}}
/* In the side-column layout three stacked tokens would stand far taller than
   the board, so the art gets a fixed height there and letterboxes inside it --
   object-fit:contain already centres it. */
/* NOT ".arena-wrap @media". A prefixed at-rule is invalid and the browser
   throws the WHOLE block away -- which is what happened here: every desktop
   refinement below, the full-figure art, the taller frame, the larger type and
   the enemy mirroring, was dead from the moment the prototype's stylesheet was
   scoped to .arena-wrap. The scoping pass moved a prefix past a comment and
   landed it in front of the @media. The rules inside are already scoped
   individually, which is why they read correctly and still did nothing. */
@media (min-width:1001px){/* THE WHOLE FIGHTER, not a bust crop. The crop showed the head and torso
     larger, but it cut the legs, the weapon and half of any companion off --
     and the art is the reason to care about a Fighter at all.
     210px is the size that fits: three tokens then stand about 890px against
     the board column's ~820, so the columns finish near level instead of
     running far past it. The square art letterboxes into the 270px width with
     small bars either side, over the checkerboard, which reads as a frame. */
  /* Scaled to the viewport, not fixed. At a flat 210px a Fighter column comes
     to about 940px whatever screen it is on, so on a 1366x768 laptop the Crew
     alone was taller than the window and the whole battle had to be scrolled
     through. Tied to height it tracks the board, which is capped the same way,
     and the two columns stay roughly level instead of one dictating the row. */
  .arena-wrap .teamcol .tok .art{aspect-ratio:auto;height:clamp(120px,19vh,230px)}.arena-wrap .teamcol .tok .art img{object-fit:contain}/* Enemies face the player. The art is all drawn facing one way, so the right
     column mirrors and the two Crews look at each other across the board
     instead of everyone staring the same direction. Only in the side-column
     layout -- stacked on a phone they are above you, not opposite you, and a
     mirrored row there just looks like different art. */
  /* On the container, not the images. A transform on the img would be
     overwritten the moment a layer animates, flipping enemies back mid-swing.
     Mirroring the frame instead leaves every layer's own transform free. */
  .arena-wrap .teamcol.foes .tok .art{transform:scaleX(-1)}.arena-wrap .teamcol .tok{padding:8px}.arena-wrap .teamcol .tok .nm{font-size:12px}.arena-wrap .teamcol .tok .kitn{font-size:10px}.arena-wrap .teamcol .tok .rk{font-size:9px}.arena-wrap .teamcol .tok .hpn{font-size:9.5px;height:14px;line-height:14px}.arena-wrap .teamcol .tok .hpwrap{height:8px}.arena-wrap .teamcol{gap:10px}
}
/* ---- board ---- */
.arena-wrap .boardwrap.foeturn{border-color:rgba(224,70,107,.55);box-shadow:0 0 0 1px rgba(224,70,107,.25)}
.arena-wrap .boardwrap{position:relative;border:1px solid var(--line);border-radius:4px;
  transition:border-color .2s,box-shadow .2s;
  background:rgba(21,25,34,.80);
  padding:8px;overflow:hidden}
/* Was inset in the board at 13% opacity behind an opaque grid, which meant the
   terrain -- a real mechanic, drawn from a real trait -- was invisible. It now
   backs the entire arena, with a scrim so nothing over it loses contrast. */
.arena-wrap .arenabg{position:absolute;inset:-14px;z-index:0;border-radius:6px;overflow:hidden;
  /* The negative inset is a deliberate bleed so the backdrop reaches past the
     columns, but it is also 14px of width the page does not have on a phone --
     see the mobile block, where it is pulled back to the arena's own edges. */
  background-size:cover;background-position:center;opacity:.30;
  filter:saturate(.75) contrast(.95);pointer-events:none}
.arena-wrap .arenabg:after{content:'';position:absolute;inset:0;
  background:radial-gradient(120% 90% at 50% 40%,rgba(13,15,19,.35),rgba(13,15,19,.88))}
.arena-wrap .arena > .teamwrap, .arena-wrap .arena > .boardcol{position:relative;z-index:1}
.arena-wrap .grid{position:relative;z-index:2;display:grid;gap:3px;touch-action:none}
.arena-wrap .cell{position:relative;aspect-ratio:1;border-radius:4px;display:flex;align-items:center;
  justify-content:center;cursor:pointer;background:#10131a;border:1px solid transparent;
  transition:transform .12s,border-color .12s}
.arena-wrap .cell:hover{border-color:var(--line)}
.arena-wrap .cell.sel{border-color:var(--bone);transform:scale(.9)}
.arena-wrap .cell .g{width:72%;height:72%;border-radius:50%;background:var(--gc);
  display:flex;align-items:center;justify-content:center;
  box-shadow:inset 0 -3px 6px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.08);
  transition:transform .18s,opacity .18s}
.arena-wrap .cell.sq .g{border-radius:4px}
.arena-wrap .cell.di .g{border-radius:3px;transform:rotate(45deg) scale(.82)}
/* Flat shoulders, straight flanks, tapering to a point at the bottom -- the
   same silhouette as the emoji sitting on it. */
.arena-wrap .cell.shield .g{border-radius:3px 3px 0 0;
  clip-path:polygon(0% 0%,100% 0%,100% 48%,86% 78%,50% 100%,14% 78%,0% 48%)}
/* the point steals height from the bottom, so the emoji rides a little high */
.arena-wrap .cell.shield .g .em{transform:translateY(-8%)}
.arena-wrap .cell.hex .g{clip-path:polygon(25% 5%,75% 5%,100% 50%,75% 95%,25% 95%,0 50%)}
.arena-wrap .cell .em{font-size:clamp(11px,2.4vw,19px);line-height:1;filter:drop-shadow(0 1px 2px rgba(0,0,0,.6));
  pointer-events:none;
  /* A BOX, not a bare inline span. .cross sizes itself as a percentage, and a
     percentage of an inline span with no dimensions is zero -- the drawn cross
     was rendering at no size at all, which is why bombs went from hard to see
     to invisible. */
  display:flex;align-items:center;justify-content:center;width:100%;height:100%}
/* the rotated diamond must not rotate its emoji with it */
.arena-wrap .cell.di .g .em{transform:rotate(-45deg)}
/* A bomb keeps its colour -- you detonate it by matching that colour -- but it
   has to be unmistakable on a busy board, so it pulses and wears a ring. */
.arena-wrap .cell.bomb .g{box-shadow:inset 0 -3px 6px rgba(0,0,0,.45),0 0 0 2px var(--bone),0 0 12px var(--gc);
  animation:bmb 1.5s ease-in-out infinite}
.arena-wrap .cell.bomb2 .g{box-shadow:inset 0 -3px 6px rgba(0,0,0,.45),0 0 0 2px var(--ochre),0 0 18px var(--ochre);
  animation:bmb .9s ease-in-out infinite}
@keyframes bmb{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
/* The cross bomb's blast shape, drawn on the gem: a bar across and a bar down. */
.arena-wrap .cross{position:relative;display:block;width:76%;height:76%}
.arena-wrap .cross:before, .arena-wrap .cross:after{content:'';position:absolute;background:#fff;border-radius:1px;
  box-shadow:0 0 4px rgba(0,0,0,.55)}
.arena-wrap .cross:before{left:0;right:0;top:calc(50% - 2px);height:4px}
.arena-wrap .cross:after{top:0;bottom:0;left:calc(50% - 2px);width:4px}
/* Detonation: everything the blast takes lights up before it goes. */
.arena-wrap .cell.blast .g{animation:blastPop .42s ease-out}
.arena-wrap .cell.blast2 .g{animation:blastPop2 .5s ease-out}
@keyframes blastPop{0%{transform:scale(1);filter:brightness(1)}35%{transform:scale(1.35);filter:brightness(3.2)}100%{transform:scale(.2);filter:brightness(1);opacity:0}}
@keyframes blastPop2{0%{transform:scale(1) rotate(0);filter:brightness(1)}30%{transform:scale(1.5) rotate(8deg);filter:brightness(4)}100%{transform:scale(.15) rotate(-6deg);opacity:0}}
.arena-wrap .boardwrap.shake{animation:bshake .38s}
@keyframes bshake{0%,100%{transform:translate(0,0)}20%{transform:translate(-5px,3px)}45%{transform:translate(4px,-3px)}70%{transform:translate(-3px,-2px)}}
.arena-wrap .cell.di.bomb .g{animation:none}
.arena-wrap .cell.clear .g{transform:scale(0);opacity:0}
.arena-wrap .cell.drop{animation:drp .22s}
.arena-wrap .cell.settle{animation:stl .16s}
@keyframes stl{0%{transform:scale(1.06)}100%{transform:scale(1)}}
@keyframes drp{0%{transform:translateY(-16px);opacity:.4}100%{transform:translateY(0);opacity:1}}
/* ============================ THE ENTRANCE ==================================
   A battle used to appear all at once, fully drawn, which gave the biggest
   moment in the game no moment at all. It arrives in three beats now: the
   arena and what it does to the fight, then the board falling in, then the two
   Crews walking on.

   IT IS SKIPPABLE AND IT IS SHORT. Six battles a day means seeing this six
   times a day, so the whole sequence is under three seconds and a tap anywhere
   ends it immediately. It also never plays on RESUME -- picking a battle back
   up mid-move is not an entrance, and replaying a moment that already happened
   is the exact mistake the drop-reveal queue made.
   ========================================================================== */
.arena-wrap .a-intro{position:absolute;inset:-14px;z-index:30;border-radius:6px;overflow:hidden;
  display:flex;flex-direction:column;align-items:center;justify-content:center;
  gap:7px;text-align:center;padding:18px;cursor:pointer;
  background-size:cover;background-position:center}
.arena-wrap .a-intro:after{content:'';position:absolute;inset:0;
  background:radial-gradient(120% 90% at 50% 45%,rgba(13,15,19,.55),rgba(13,15,19,.95))}
.arena-wrap .a-intro > *{position:relative;z-index:1}
.arena-wrap .a-intro.go{animation:introIn .45s ease-out}
.arena-wrap .a-intro.out{animation:introOut .40s ease-in forwards;pointer-events:none}
@keyframes introIn{0%{opacity:0}100%{opacity:1}}
@keyframes introOut{0%{opacity:1}100%{opacity:0}}
.arena-wrap .ti-k{font-size:9px;letter-spacing:.28em;text-transform:uppercase;color:var(--dim);
  animation:tiUp .5s .05s both}
.arena-wrap .ti-n{font-size:clamp(19px,4.4vw,34px);letter-spacing:.06em;text-transform:uppercase;
  color:var(--bone);text-shadow:0 3px 18px #000;animation:tiName .7s .12s both}
.arena-wrap .ti-e{font-size:11.5px;color:var(--ochre);max-width:34ch;animation:tiUp .5s .38s both}
.arena-wrap .ti-v{margin-top:6px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;
  color:var(--dim);animation:tiUp .5s .55s both}
.arena-wrap .ti-v b{color:var(--bone);font-weight:400}
.arena-wrap .ti-s{position:absolute;bottom:9px;right:12px;z-index:1;font-size:8.5px;
  letter-spacing:.14em;text-transform:uppercase;color:var(--dim);opacity:.7}
@keyframes tiUp{0%{opacity:0;transform:translateY(7px)}100%{opacity:1;transform:translateY(0)}}
@keyframes tiName{0%{opacity:0;transform:scale(1.14);letter-spacing:.22em}
  100%{opacity:1;transform:scale(1);letter-spacing:.06em}}
/* Beat two and three. The gems fall in on a per-cell delay set in JS, and the
   Crews walk on from their own side of the board.

   THE BOARD IS HIDDEN, NOT MERELY ANIMATED, until its beat arrives. The panel
   fades in over 450ms, and a board that is only animating is a board you can
   watch through the fade -- which is exactly backwards: the first thing you saw
   was the finished picture, and the cinematic then covered it up. Hiding the
   grid outright means there is nothing behind the panel to see. */
.arena-wrap .cine .grid{visibility:hidden}
.arena-wrap .cine.board .grid{visibility:visible}
.arena-wrap .cine.board .cell{animation:gemIn .34s both}
@keyframes gemIn{0%{opacity:0;transform:translateY(-26px) scale(.55)}
  60%{opacity:1;transform:translateY(2px) scale(1.04)}100%{transform:translateY(0) scale(1)}}
.arena-wrap .cine .teamwrap{opacity:0}
.arena-wrap .cine.crew .teamwrap.foes{animation:crewIn .5s both}
.arena-wrap .cine.crew .teamwrap.mine{animation:crewIn .5s .12s both}
.arena-wrap .cine.crew .teamwrap.foes .tok{animation:tokIn .42s both}
.arena-wrap .cine.crew .teamwrap.mine .tok{animation:tokIn .42s both}
@keyframes crewIn{0%{opacity:0}100%{opacity:1}}
@keyframes tokIn{0%{opacity:0;transform:translateY(14px) scale(.94)}
  100%{opacity:1;transform:translateY(0) scale(1)}}

/* =========================== THE FIGHTER CARD ===============================
   Tap any Fighter, yours or theirs, and it opens.

   It exists because the game's central rule is invisible. A Fighter's weapon
   decides HOW it fights, its torso how long it lasts and its headgear how often
   it lands big -- and arms, effects and companion do nothing at all except look
   like something. Nobody can deduce that from a board, and a player who does
   not know it is choosing a Crew on rarity score, which is the one number the
   Arena deliberately refuses to let decide a fight.
   ========================================================================== */
.arena-wrap .fcard{position:fixed;inset:0;z-index:60;display:flex;align-items:center;
  justify-content:center;padding:16px;background:rgba(5,7,10,.78)}
.arena-wrap .fcard[hidden]{display:none}
.arena-wrap .fc-box{background:var(--panel);border:1px solid var(--line);border-radius:5px;
  width:min(560px,100%);max-height:calc(100vh - 32px);overflow:auto;position:relative;
  box-shadow:0 18px 60px rgba(0,0,0,.6)}
.arena-wrap .fc-x{position:absolute;top:6px;right:8px;z-index:2;background:none;border:0;
  color:var(--dim);font-size:20px;line-height:1;padding:4px 8px;cursor:pointer}
.arena-wrap .fc-x:hover{color:var(--ochre)}
.arena-wrap .fc-top{display:flex;gap:12px;padding:12px;border-bottom:1px solid var(--line)}
.arena-wrap .fc-art{position:relative;width:118px;flex:none;aspect-ratio:1;border-radius:3px;
  overflow:hidden;background:var(--panel2)}
.arena-wrap .fc-art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.arena-wrap .fc-art img.bg{object-fit:cover}
.arena-wrap .fc-id{min-width:0;flex:1}
.arena-wrap .fc-id h3{margin:0 0 3px;font-size:16px;letter-spacing:.01em}
.arena-wrap .fc-who{font-size:10px;letter-spacing:.12em;text-transform:uppercase;opacity:.6}
.arena-wrap .fc-gem{display:inline-flex;align-items:center;gap:6px;margin-top:7px;
  padding:3px 9px;border-radius:999px;border:1px solid var(--gemc);font-size:11px}
.arena-wrap .fc-gem b{display:inline-flex;align-items:center;justify-content:center;
  width:19px;height:19px;border-radius:50%;background:var(--gemc);font-size:11px}
.arena-wrap .fc-kit{margin-top:8px;font-size:12px}
.arena-wrap .fc-kit b{color:var(--ochre);font-weight:400}
.arena-wrap .fc-body{padding:11px 12px}
.arena-wrap .fc-h{font-size:9px;letter-spacing:.16em;text-transform:uppercase;opacity:.55;
  margin:0 0 6px}
.arena-wrap .fc-rows{display:grid;gap:5px;margin:0 0 12px}
.arena-wrap .fc-row{display:grid;grid-template-columns:78px 62px 1fr;gap:9px;align-items:baseline;
  font-size:11.5px}
.arena-wrap .fc-row .k{opacity:.6;font-size:10px;letter-spacing:.1em;text-transform:uppercase}
.arena-wrap .fc-row .v{font-variant-numeric:tabular-nums;color:var(--ochre)}
.arena-wrap .fc-row .src{opacity:.7;min-width:0;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .fc-row .src em{font-style:normal;opacity:.55;font-size:10px}
.arena-wrap .fc-cos{font-size:11px;opacity:.55;line-height:1.7}
.arena-wrap .fc-cos b{opacity:.85;font-weight:400}
.arena-wrap .fc-note{margin:10px 0 0;font-size:11px;opacity:.6;line-height:1.6;
  border-top:1px solid var(--line);padding-top:9px}
.arena-wrap .tok{cursor:pointer}
@media (max-width:520px){
  .arena-wrap .fc-top{flex-direction:column;align-items:center;text-align:center}
  .arena-wrap .fc-art{width:150px}
  .arena-wrap .fc-row{grid-template-columns:70px 56px 1fr;gap:7px;font-size:11px}
}

/* Sits over the settled board rather than in a side panel, because the end of
   a battle should land where you were looking. Fades in only once the last
   cascade has come to rest. */
/* MUST COME FIRST AND MUST EXIST. `hidden` gets display:none from the browser's
   own stylesheet, which is the weakest source there is -- the .endcard rule
   below sets display:flex and silently beat it, so this overlay sat over the
   board from page load, covering every gem and swallowing every click. The page
   looked dead and a refresh could not help, because it was never alive.
   The attribute selector outranks the bare class, so it wins. */
.arena-wrap .endcard[hidden]{display:none}
.arena-wrap .endcard{position:absolute;inset:0;z-index:8;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:9px;text-align:center;
  background:rgba(13,15,19,.86);backdrop-filter:blur(3px);animation:ecIn .45s ease-out}
@keyframes ecIn{0%{opacity:0}100%{opacity:1}}
.arena-wrap .ec-title{font-size:clamp(20px,5vw,30px);letter-spacing:.14em;
  text-transform:uppercase;font-weight:700}
/* The card is the board's size now rather than the page's, so on a phone it has
   a couple of hundred pixels less to work with and the text has to give. */
.arena-wrap .endcard{padding:10px}
@media (max-width:1000px){
  .arena-wrap .ec-sub{font-size:11px;max-width:92%}
  .arena-wrap .ec-stats{gap:10px;font-size:9px}
  .arena-wrap .ec-stats b{font-size:14px}
  .arena-wrap .endcard{gap:7px}
}
.arena-wrap .endcard.win .ec-title{color:var(--teal);text-shadow:0 0 26px rgba(0,200,160,.45)}
.arena-wrap .endcard.lose .ec-title{color:var(--blood);text-shadow:0 0 26px rgba(224,70,107,.4)}
.arena-wrap .ec-sub{font-size:12px;color:var(--bone);opacity:.85;max-width:74%}
.arena-wrap .ec-stats{display:flex;gap:16px;flex-wrap:wrap;justify-content:center;
  font-size:10px;color:var(--dim);letter-spacing:.06em;text-transform:uppercase}
.arena-wrap .ec-stats b{display:block;font-size:17px;color:var(--bone);letter-spacing:0;
  font-variant-numeric:tabular-nums}
.arena-wrap .legend{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.arena-wrap .lchip{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:var(--bone);
  padding:4px 9px 4px 4px;border-radius:999px;background:#10131a;
  border:1px solid var(--line);border-left:3px solid var(--lc);white-space:nowrap;
  max-width:100%;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .lchip b{display:inline-flex;align-items:center;justify-content:center;width:21px;height:21px;
  border-radius:50%;background:var(--lc);font-size:12px;font-weight:400;
  box-shadow:inset 0 -2px 4px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.12)}
.arena-wrap .lchip.gone{opacity:.32;text-decoration:line-through}
.arena-wrap .reach{font-size:10px;color:var(--dim);margin-top:7px;display:flex;flex-wrap:wrap;gap:4px 14px;
  align-items:baseline}
.arena-wrap .reach b{color:var(--ochre)}
.arena-wrap .reach .terr{color:var(--teal);margin-left:auto}
.arena-wrap .reach b{color:var(--ochre)}
/* ---- side ---- */
.arena-wrap .panel{border:1px solid var(--line);border-radius:4px;background:var(--panel);padding:9px;margin-bottom:10px}
.arena-wrap .panel h2{margin:0 0 6px;font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim)}
.arena-wrap .logbox{border:1px solid var(--line);border-radius:4px;background:var(--panel);
  margin-top:10px;padding:8px 10px}
.arena-wrap .logbox summary{font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim);
  cursor:pointer;list-style:none}
.arena-wrap .logbox summary::-webkit-details-marker{display:none}
.arena-wrap .logbox summary:before{content:'▸ ';}
.arena-wrap .logbox[open] summary:before{content:'▾ ';}
.arena-wrap #log{height:150px;overflow:auto;font-size:10px;line-height:1.55;margin-top:6px}
.arena-wrap #log div{padding:1px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.arena-wrap .log-you{color:var(--teal)}
.arena-wrap .log-foe{color:var(--blood)}
.arena-wrap .log-sys{color:var(--dim)}
.arena-wrap .log-big{color:var(--ochre)}
.arena-wrap .combo{position:absolute;left:50%;top:38%;transform:translateX(-50%);z-index:9;
  font-size:26px;font-weight:700;color:var(--ochre);text-shadow:0 3px 14px #000;opacity:0;pointer-events:none}
.arena-wrap .combo.on{animation:cb 1s}
@keyframes cb{0%{opacity:0;transform:translate(-50%,10px) scale(.8)}20%{opacity:1;transform:translate(-50%,0) scale(1.1)}70%{opacity:1}100%{opacity:0;transform:translate(-50%,-14px)}}
.arena-wrap .ec-acts{display:flex;gap:8px;justify-content:center;flex-wrap:wrap}
.arena-wrap .ec-acts .btn[hidden]{display:none}
.arena-wrap .top .btn[hidden]{display:none}
.arena-wrap .over{text-align:center;padding:14px}
.arena-wrap .over h2{font-size:15px;color:var(--ochre);margin:0 0 4px}
/* ---- Arena shell ------------------------------------------------------------
   Written against dhcfighters.php's idioms rather than invented again: the same
   masthead, the same stat tiles, the same bordered panel with an uppercase
   caption bar and a padded body. Two sibling pages in the same feature should
   not each have their own idea of what a panel is.
   -------------------------------------------------------------------------- */
.arena-wrap{max-width:100%;margin:0 auto;padding:14px;overflow-x:clip;text-align:left}
/* One row across the full width -- blurb takes what it needs, counts sit hard
   right -- the way the Fighters masthead does it. */
.arena-wrap .a-masthead{display:flex;flex-wrap:wrap;align-items:center;
  justify-content:space-between;gap:18px 32px;margin:0 0 16px}
.arena-wrap .a-intro-txt{flex:1 1 420px;min-width:0}
.arena-wrap .a-head{display:flex;align-items:baseline;gap:12px;flex-wrap:wrap;margin:0 0 4px}
.arena-wrap .a-head h1{font-size:22px;letter-spacing:.02em;margin:0}
.arena-wrap .a-sub{font-size:11.5px;opacity:.65;line-height:1.6;margin:0}
.arena-wrap .a-sub b{opacity:.95}
.arena-wrap .a-sub a{color:var(--ochre)}
/* flex:0 1 auto + min-width:0 so the tiles can shrink and wrap instead of
   pushing the page sideways on a phone -- the same trap .dhcf-stats documents. */
.arena-wrap .a-stats{display:flex;gap:8px;flex-wrap:wrap;margin:0;flex:0 1 auto;min-width:0}
.arena-wrap .a-stat{border:1px solid var(--line);border-radius:3px;padding:7px 12px;
  min-width:0;flex:0 1 auto}
.arena-wrap .a-stat b{display:block;font-size:17px;font-variant-numeric:tabular-nums;white-space:nowrap}
.arena-wrap .a-stat span{font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;
  opacity:.6;white-space:nowrap}
@media (max-width:520px){
  .arena-wrap .a-stats{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));width:100%}
  .arena-wrap .a-stat{padding:6px 8px}
  .arena-wrap .a-stat b{font-size:15px}
}
.arena-wrap .a-block{border:1px solid var(--ochre);background:rgba(0,200,160,.07);
  border-radius:3px;padding:9px 12px;font-size:12px;margin-bottom:14px}
.arena-wrap .a-block a{color:var(--ochre)}
.arena-wrap .a-panels{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));
  gap:14px;align-items:start}
.arena-wrap .a-panel{border:1px solid var(--line);border-radius:3px;overflow:hidden}
.arena-wrap .a-panel h2{margin:0;padding:9px 12px;font-size:10px;letter-spacing:.16em;
  text-transform:uppercase;opacity:.65;border-bottom:1px solid var(--line);
  display:flex;flex-wrap:wrap;justify-content:space-between;gap:2px 10px}
.arena-wrap .a-panel h2 span{opacity:.75;letter-spacing:.08em;white-space:nowrap}
.arena-wrap .a-panel .body{padding:10px 12px}
.arena-wrap .a-tools{display:flex;flex-wrap:wrap;align-items:center;gap:5px;margin:0 0 9px;
  font-size:11px}
.arena-wrap .a-tools .lbl{font-size:9px;letter-spacing:.14em;text-transform:uppercase;opacity:.5}
.arena-wrap .a-tools .sep{width:1px;height:14px;background:var(--line);margin:0 4px}
.arena-wrap .a-tools button{font:inherit;font-size:11px;background:none;color:inherit;
  border:1px solid var(--line);border-radius:999px;padding:3px 9px;cursor:pointer;opacity:.75}
.arena-wrap .a-tools button:hover{border-color:var(--ochre);opacity:1}
.arena-wrap .a-tools button.on{border-color:var(--ochre);color:var(--ochre);
  background:rgba(0,200,160,.08);opacity:1}
.arena-wrap .a-card .st{display:flex;align-items:center;gap:6px;font-size:9.5px;opacity:.75;
  font-variant-numeric:tabular-nums;margin-top:2px}
.arena-wrap .a-card .st em{font-style:normal;margin-left:auto}
.arena-wrap .a-pick{display:grid;grid-template-columns:repeat(auto-fill,minmax(112px,1fr));gap:7px}
.arena-wrap .a-card{border:1px solid var(--line);border-radius:3px;padding:5px;
  cursor:pointer;position:relative;text-align:left}
.arena-wrap .a-card:hover{background:rgba(0,200,160,.05);border-color:var(--ochre)}
.arena-wrap .a-card.sel{border-color:var(--ochre);box-shadow:inset 0 0 0 1px var(--ochre);
  background:rgba(0,200,160,.08)}
.arena-wrap .a-card.out{opacity:.4;cursor:not-allowed}
.arena-wrap .a-card .art{position:relative;width:100%;aspect-ratio:1;overflow:hidden;border-radius:2px;
  background:var(--panel2)}
.arena-wrap .a-card .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.arena-wrap .a-card .art img.bg{object-fit:cover}
.arena-wrap .a-card .nm{font-size:9.5px;margin-top:3px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .a-card .sc{font-size:9px;opacity:.6;font-variant-numeric:tabular-nums}
.arena-wrap .a-card .wl{font-size:8.5px;opacity:.5}
.arena-wrap .a-card .pk{position:absolute;top:4px;left:4px;z-index:2;font-size:8px;
  letter-spacing:.1em;text-transform:uppercase;padding:2px 5px;border-radius:2px;
  background:var(--ink);border:1px solid var(--ochre);color:var(--ochre)}
/* READABLE, which the first version was not: 9px of --blood (#e0466b) on a
   dark scrim over busy artwork is red-on-dark at the smallest size on the page,
   and red is the one hue that loses most contrast against a near-black ground.
   The word is bone, the clock is ochre -- and ochre is the right colour for it
   anyway, because a recovering Fighter is a wait, not an error. */
.arena-wrap .a-card .recover{position:absolute;inset:0;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:3px;border-radius:3px;text-align:center;
  padding:4px;background:rgba(8,10,14,.85);
  font-size:9.5px;letter-spacing:.11em;text-transform:uppercase;color:var(--bone);
  text-shadow:0 1px 3px #000}
.arena-wrap .a-card .recover b{font-weight:400;font-size:12px;letter-spacing:0;
  text-transform:none;color:var(--ochre);font-variant-numeric:tabular-nums}
/* A Fighter being brought back reads differently from one catching its breath.
   The word is longer, so it gets room to wrap rather than being squeezed. */
.arena-wrap .a-card .recover.fell{letter-spacing:.06em}
.arena-wrap .a-card .recover.fell b{color:var(--warn)}
.arena-wrap .a-card.out:hover{background:none;border-color:var(--line)}
/* ---- paging. A staker with two hundred Fighters should not be handed two
   hundred cards, and the cards are the expensive part: eight <img> layers each.
   Off-page cards are display:none rather than removed, which is what makes the
   picks survive a page change -- and a browser does not fetch images inside a
   display:none subtree, so the pages you are not looking at cost nothing. */
.arena-wrap .a-card.off{display:none}
.arena-wrap .a-pager{display:flex;align-items:center;justify-content:center;gap:10px;
  margin-top:10px;font-size:11px;opacity:.7}
.arena-wrap .a-pager button{background:var(--panel2);color:var(--bone);border:1px solid var(--line);
  border-radius:3px;padding:3px 10px;font:inherit;cursor:pointer}
.arena-wrap .a-pager button:disabled{opacity:.35;cursor:default}
.arena-wrap .a-pager button:not(:disabled):hover{border-color:var(--ochre);color:var(--ochre)}
/* THE PICKS STAY ON SCREEN. Choosing three Fighters and then paging away from
   them left the formation invisible at the moment you most need it -- the
   order IS the formation. */
.arena-wrap .a-picked{display:flex;flex-wrap:wrap;gap:5px;margin:0 0 9px;min-height:23px;
  align-items:center;font-size:11px;opacity:.85}
.arena-wrap .a-picked .chip{display:inline-flex;align-items:center;gap:5px;padding:3px 8px;
  border:1px solid var(--ochre);border-radius:999px;color:var(--bone);background:rgba(0,200,160,.08);
  cursor:pointer;max-width:180px}
.arena-wrap .a-picked .chip b{font-weight:400;color:var(--ochre);font-size:8.5px;
  letter-spacing:.1em;text-transform:uppercase;flex:none}
.arena-wrap .a-picked .chip s{text-decoration:none;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.arena-wrap .a-picked .chip i{font-style:normal;opacity:.55;flex:none}
/* Removing is the one destructive affordance on this screen, and --blood is
   the platform's own accent here, so it needs a colour of its own to read as
   "this takes something away". */
.arena-wrap .a-picked .chip:hover{border-color:var(--warn);background:rgba(208,70,58,.10)}
.arena-wrap .a-picked .chip:hover i{color:var(--warn);opacity:1}
.arena-wrap .a-foes{display:flex;flex-direction:column;gap:5px;max-height:330px;overflow:auto}
.arena-wrap .a-foe{display:flex;align-items:center;gap:10px;padding:7px 9px;border:1px solid var(--line);
  border-radius:3px;cursor:pointer}
.arena-wrap .a-foe:hover{border-color:var(--ochre);background:rgba(0,200,160,.05)}
.arena-wrap .a-foe.sel{border-color:var(--ochre);box-shadow:inset 0 0 0 1px var(--ochre);
  background:rgba(0,200,160,.08)}
.arena-wrap .a-foe img{width:28px;height:28px;border-radius:50%;flex:none;background:var(--panel2)}
.arena-wrap .a-foe .n{flex:1;min-width:0;font-size:12.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.arena-wrap .a-foe .m{font-size:10px;opacity:.6;white-space:nowrap}
.arena-wrap .a-go{margin-top:12px;display:flex;gap:10px;align-items:center;flex-wrap:wrap}
/* The platform's buttons, not the game's. .btn inside #arenaBattle keeps the
   monospace board styling; out here it should look like every other control on
   Skulliance. */
.arena-wrap .a-go .btn{font:inherit;border-radius:3px;padding:7px 14px;
  background:var(--panel2);color:var(--bone);border:1px solid var(--line);cursor:pointer}
.arena-wrap .a-go .btn.go{background:rgba(0,200,160,.12);border-color:var(--ochre);color:var(--ochre)}
.arena-wrap .a-go .btn.go:hover:not(:disabled){background:rgba(0,200,160,.2)}
.arena-wrap .a-go .btn:disabled{opacity:.4;cursor:default}
.arena-wrap .a-lad{width:100%;border-collapse:collapse;font-size:12px}
.arena-wrap .a-lad th{text-align:left;font-size:9px;letter-spacing:.12em;text-transform:uppercase;
  opacity:.6;font-weight:400;padding:5px 7px;border-bottom:1px solid var(--line)}
.arena-wrap .a-lad td{padding:6px 7px;border-bottom:1px solid var(--line)}
.arena-wrap .a-lad tr:last-child td{border-bottom:0}
.arena-wrap .a-lad tbody tr:hover{background:rgba(0,200,160,.05)}
.arena-wrap .a-lad .r{opacity:.65;font-variant-numeric:tabular-nums}
.arena-wrap .a-lad .me{color:var(--ochre)}
.arena-wrap .a-lad .me .r{opacity:1}
.arena-wrap #arenaBattle{display:none;margin-top:12px}
.arena-wrap #arenaBattle.on{display:block}
.arena-wrap .a-busy{opacity:.55;pointer-events:none}
</style>

  <!-- One masthead row: the blurb takes what it needs and the counts sit hard
       right, the same shape DHC Fighters uses. Two sibling pages in one feature
       should not introduce themselves differently. -->
  <div class="a-masthead">
    <div class="a-intro-txt">
      <div class="a-head"><h1>DHC Arena</h1></div>
      <p class="a-sub">Three of your Fighters against three of theirs, on a shared board.
         Each of your Crew owns a gem — matching it makes them act, and <b>match size is
         reach</b>. Win and a trait lands in your collection.</p>
    </div>
    <div class="a-stats">
      <div class="a-stat"><b><?php echo count($crew); ?></b><span>Fighters</span></div>
      <div class="a-stat"><b><?php echo count($available); ?></b><span>Standing</span></div>
      <div class="a-stat"><b><?php echo max(0, DHCA_DAILY_BATTLES - $spent); ?>/<?php echo DHCA_DAILY_BATTLES; ?></b><span>Battles left</span></div>
      <div class="a-stat"><b><?php echo count($foesList); ?></b><span>Rivals</span></div>
    </div>
  </div>

<?php if ($block): ?>
  <div class="a-block"><?php echo htmlspecialchars($block); ?>
    <?php if (count($crew) < DHCA_CREW_SIZE): ?>
      Build them in <a href="dhcfighters.php">DHC Fighters</a>.
    <?php endif; ?>
  </div>
<?php endif; ?>

  <div class="a-panels" id="arenaSetup">
    <div class="a-panel">
      <h2>Your Crew — pick <?php echo DHCA_CREW_SIZE; ?>, in order</h2>
      <div class="body">
      <p class="a-sub" style="margin:0 0 9px">The order you pick sets the formation:
         first is <b>front</b>, then <b>mid</b>, then <b>back</b>. A match of 3 only
         reaches their front rank, 4 reaches mid, 5 or more reaches the back — so put
         the Fighter you most want protected last.</p>
      <?php if (!$crew): ?>
        <p class="a-sub" style="margin:0">No Fighters yet.</p>
      <?php else: ?>
      <?php /* Sorting and filtering happen in the browser, not through a
               reload: a reload would throw away the Fighters already picked,
               and picking is the whole reason this list exists. */ ?>
      <div class="a-tools">
        <span class="lbl">Sort</span>
        <button type="button" class="on" data-sort="might">Deadliest</button>
        <button type="button" data-sort="hp">Toughest</button>
        <button type="button" data-sort="pow">Hardest hitting</button>
        <button type="button" data-sort="score">Rarest</button>
        <?php if (count($allKits) > 1): ?>
          <span class="sep"></span>
          <span class="lbl">Fights like</span>
          <button type="button" class="on" data-kit="">Any</button>
          <?php foreach ($allKits as $k): ?>
            <button type="button" data-kit="<?php echo htmlspecialchars($k['id']); ?>"
              title="<?php echo htmlspecialchars($k['note']); ?>"><?php
              echo $k['emoji'].' '.htmlspecialchars($k['name']).' '.$kitCount[$k['id']]; ?></button>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
      <div class="a-picked" id="aPicked"></div>
      <div class="a-pick">
      <?php foreach ($crew as $f):
        $t = $f['traits']; $ok = $f['available']; ?>
        <div class="a-card<?php echo $ok?'':' out'; ?>" data-fid="<?php echo (int)$f['id']; ?>"
             data-ok="<?php echo $ok?1:0; ?>" data-hp="<?php echo (int)$f['hp']; ?>"
             data-pow="<?php echo (int)$f['pow']; ?>" data-kit="<?php echo htmlspecialchars($f['kit']['id']); ?>"
             data-score="<?php echo (int)$f['rarity_score']; ?>"
             title="<?php echo htmlspecialchars($f['display'].' — '.$f['kit']['name'].', '.$f['kit']['note']
                     .' · '.$f['hp'].' health, '.$f['pow'].' power, '.$f['crit'].'% crit'); ?>">
          <div class="art">
            <?php if (!empty($t['background'])): ?>
              <img class="bg" loading="lazy" alt="" src="<?php echo $ART; ?>/250/background/<?php echo htmlspecialchars($t['background']); ?>.png" onerror="this.remove()">
            <?php endif; ?>
            <?php foreach (array('torso','weapon','arms','effects','head','headgear','companion') as $k):
              if (empty($t[$k])) continue; ?>
              <img loading="lazy" alt="" src="<?php echo $ART; ?>/250/<?php echo $k; ?>/<?php echo htmlspecialchars($t[$k]); ?>.png" onerror="this.remove()">
            <?php endforeach; ?>
            <?php if (!$ok): ?>
              <?php /* Two different things are happening and they deserve two
                       different words: a Fighter that fell is being brought
                       back, one that merely fought is catching its breath. */ ?>
              <div class="recover<?php echo !empty($f['fell']) ? ' fell' : ''; ?>"><?php
                echo !empty($f['fell']) ? 'resurrecting' : 'recovering'; ?><b><?php
                echo dhca_hms($f['bench_left']); ?></b></div>
            <?php endif; ?>
          </div>
          <div class="nm"><?php echo htmlspecialchars($f['display']); ?></div>
          <div class="st"><span>♥ <?php echo (int)$f['hp']; ?></span><span>⚔ <?php echo (int)$f['pow']; ?></span>
            <em><?php echo $f['kit']['emoji']; ?></em></div>
          <div class="wl"><?php echo number_format((int)$f['rarity_score']); ?> pts ·
            <?php echo (int)$f['wins']; ?>W/<?php echo (int)$f['losses']; ?>L</div>
        </div>
      <?php endforeach; ?>
      </div>
      <div class="a-pager" id="aPager" style="display:none">
        <button type="button" id="aPrev">&lsaquo; Prev</button>
        <span id="aPageLbl"></span>
        <button type="button" id="aNext">Next &rsaquo;</button>
      </div>
      <?php endif; ?>
      </div>
    </div>

    <div class="a-panel">
      <h2>Choose a rival</h2>
      <div class="body">
      <?php if (!$foesList): ?>
        <p class="a-sub" style="margin:0">Nobody else has a Crew yet. Check back once more stakers have built three Fighters.</p>
      <?php else: ?>
      <div class="a-foes">
      <?php foreach ($foesList as $o):
        $av = ($o['discord_id'] && $o['avatar'])
            ? 'https://cdn.discordapp.com/avatars/'.$o['discord_id'].'/'.$o['avatar'].'.png' : ''; ?>
        <div class="a-foe" data-uid="<?php echo (int)$o['user_id']; ?>">
          <img src="<?php echo htmlspecialchars($av); ?>" alt="" onerror="this.style.visibility='hidden'">
          <span class="n"><?php echo htmlspecialchars($o['username']); ?></span>
          <span class="m"><?php echo (int)$o['fighters']; ?> Fighters · best <?php echo number_format((int)$o['best']); ?></span>
        </div>
      <?php endforeach; ?>
      </div>
      <?php endif; ?>
      <div class="a-go">
        <button class="btn go" id="aStart" <?php echo $block?'disabled':''; ?>>Enter the Arena</button>
        <?php /* Never disabled, whatever the allowance says -- that is the point
                 of it. A player out of battles, short of a Crew, or waiting on
                 three Fighters to come back can still play. */ ?>
        <button class="btn" id="aPractice">Practice</button>
        <span class="a-sub" style="margin:0" id="aMsg"></span>
      </div>
      <p class="a-sub" style="margin:8px 0 0">Practice pits two random Crews against
         each other with the real rules and nothing at stake — no allowance, no
         recovery, no traits, no ladder. Play as many as you like.</p>
      </div>
    </div>

    <div class="a-panel">
      <h2>Ladder <span><?php echo htmlspecialchars(dhca_season()); ?></span></h2>
      <div class="body">
      <?php if (!$ladder): ?>
        <p class="a-sub" style="margin:0">No battles fought this season yet. Be first.</p>
      <?php else: ?>
      <table class="a-lad">
      <thead><tr><th>#</th><th>Staker</th><th>W</th><th>L</th><th>Best chain</th></tr></thead>
      <tbody>
      <?php foreach ($ladder as $i => $l): ?>
        <tr<?php echo ((int)$l['user_id'] === $user_id) ? ' class="me"' : ''; ?>>
          <td class="r"><?php echo $i+1; ?></td>
          <td><?php echo htmlspecialchars($l['username']); ?></td>
          <td class="r"><?php echo (int)$l['wins']; ?></td>
          <td class="r"><?php echo (int)$l['losses']; ?></td>
          <td class="r">x<?php echo (int)$l['best_chain']; ?></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table>
      <?php endif; ?>
      </div>
    </div>
  </div>

  <!-- the board, hidden until a battle starts -->
  <div id="arenaBattle">
    <div class="top">
      <button class="btn" id="aLeave">Leave</button>
      <?php /* Practice only -- a ranked opponent is chosen, not dealt. */ ?>
      <button class="btn" id="aReroll" hidden title="Deal two new Crews">New battle</button>
      <button class="btn" id="mute" title="Mute sound">🔊</button>
      <!-- Phone only. The title and the blurb cost most of a screen while you
           are playing and neither is needed then, so they hide together and
           come back together. Nothing is deleted. -->
      <button class="btn" id="howto" title="Show the title and how it works">?</button>
      <span class="turnflag" id="flag">—</span>
      <span class="sub" style="margin:0" id="round"></span>
    </div>
    <div class="arena">
      <div class="arenabg" id="terrain"></div>
      <div class="teamwrap mine"><div class="coltag you"><span>Your Crew</span>
        <span class="chg" id="chgMine"></span></div>
        <div class="teamcol mine" id="myTeam"></div></div>
      <div class="boardcol">
        <div class="boardwrap">
          <div class="combo" id="combo"></div>
          <div class="grid" id="grid"></div>
          <?php /* INSIDE the board, not beside it. The card is
                   position:absolute;inset:0, and as a sibling of .arena it had
                   no positioned ancestor at all -- so it anchored to the top of
                   the DOCUMENT and covered the first screenful of it. On a
                   desktop the battle happens to be about one screen tall so it
                   looked deliberate; on a phone the layout stacks, and the
                   first screenful is the enemy Crew and the board, which is
                   exactly what you want to look at when a battle ends.
                   .boardwrap is already position:relative for the combo banner,
                   so the card now covers the board and nothing else. */ ?>
          <div class="endcard" id="endcard" hidden>
            <div class="ec-title" id="ecTitle"></div>
            <div class="ec-sub" id="ecSub"></div>
            <div class="ec-stats" id="ecStats"></div>
            <?php /* NO SHARE BUTTON YET, deliberately. dhcarena.php is behind
                     skulliance.php, so the link in a post is a login prompt for
                     everyone who is not already a member and X cannot scrape a card
                     off it either -- the share would cost reach rather than earn it.
                     It comes back pointed at the public page, once there is one. */ ?>
            <div class="ec-acts">
              <button class="btn go" id="ecAgain">Back to the Arena</button>
              <?php /* Practice only. A ranked battle's "Back to the Arena" already
                       leaves, but in practice that button starts the next battle --
                       which left the player with no way out of practice at all except
                       the Leave button up in the toolbar, above the card they are
                       looking at and easy to miss. */ ?>
              <button class="btn" id="ecLeave" hidden>Leave practice</button>
            </div>
          </div>
        </div><!-- /.boardwrap -->
        <div class="reach deskonly" id="reach"></div>
        <div class="legend deskonly" id="legend"></div>
      </div>
      <div class="teamwrap foes"><div class="coltag foe"><span>Enemy Crew</span>
        <span class="chg" id="chgFoes"></span></div>
        <div class="teamcol foes" id="foeTeam"></div></div>
    </div>
    <div class="reach mobonly" id="reachM"></div>
    <div class="legend mobonly" id="legendM"></div>
    </div>
    <details class="logbox" open><summary>Battle log</summary><div id="log"></div></details>
    <div class="fcard" id="fcard" hidden>
      <div class="fc-box" id="fcBox"></div>
    </div>
  </div>

</div>

<script>
/* ============================================================================
   DHC Arena — the client.

   It draws and it animates. It decides NOTHING. A move goes to
   ajax/dhcarena-action.php as "slide A to B"; the engine resolves the whole
   exchange, the defending Crew's replies included, and answers with the new
   state plus an fx TIMELINE — an ordered list of what happened. Everything
   below is a player for that timeline.

   The one rule duplicated here is "does this slide make a match", and only to
   REFUSE a move locally: a slide that matches nothing is free in this game, and
   making the player wait on a round trip to be told so would ruin the feel. The
   server checks it again and is the only opinion that counts.
   ============================================================================ */
(function(){
var ART = <?php echo json_encode($ART); ?>;
var N = 7, BOMB_CROSS = 1, BOMB_BOARD = 2;
var SHAPE = ['','sq','di','shield','hex'];
var SHARED = {3:{emoji:'🛡️',name:'Shield',note:'shields crew'},
              4:{emoji:'⚡',name:'Charge',note:'erupts at 10'}};
var CREW_SIZE = <?php echo DHCA_CREW_SIZE; ?>;
/* Why the player cannot enter, if they cannot. The server decides this again on
   every start -- this only keeps the button honest. */
var BLOCKED = <?php echo json_encode($block); ?>;

var S = null, battleId = 0, busy = false, drag = null, sfxOn = true;
/* PRACTICE. The same board, the same engine, nothing at stake -- and no row
   anywhere: the battle lives in this spec, which is posted back with each move
   and rebuilt server-side. See dhcarena-practice.php. */
var PRACTICE_URL = 'ajax/dhcarena-practice.php';
var practice = null;      // the spec while a practice battle is running, else null
try { sfxOn = localStorage.getItem('dhcarena_sfx') !== '0'; } catch (e) {}

var $ = function(id){ return document.getElementById(id); };
var setup = $('arenaSetup'), battle = $('arenaBattle'), gridEl = $('grid');

/* ---------------------------------------------------------------- sound ----
   One voice per sound, restarted rather than layered — Skull Swap's model.
   Stacking a fresh voice per event cost 33 overlapping sounds on a single move
   and stalled tile animation on a phone. */
var SFX = {
  pick:['sounds/select.ogg',.40], bad:['sounds/badmove.ogg',.45],
  clear:['sounds/gem_shatters.ogg',.42], land:['sounds/hyperspace_gem_land_1.ogg',.22],
  chain:['sounds/speedmatch1.ogg',.55], great:['sounds/voice_excellent.ogg',.60],
  shield:['sounds/hyperspace_gem_land_2.ogg',.45], erupt:['sounds/badgeawarded.ogg',.60],
  ko:['sounds/skullcoinlose.ogg',.55], armX:['sounds/powergem_created.ogg',.70],
  armB:['sounds/hypercube_create.ogg',.80], boom:['sounds/bomb_explode.ogg',.75],
  start:['sounds/voice_go.ogg',.55], win:['sounds/voice_levelcomplete.ogg',.75],
  lose:['sounds/voice_gameover.ogg',.75]
};
var actx = null, sfxBuf = {}, sfxEl = {}, sfxCur = {};
function sfxInit(){
  if (actx) return;
  var AC = window.AudioContext || window.webkitAudioContext; if (!AC) return;
  try { actx = new AC(); } catch (e) { return; }
  Object.keys(SFX).forEach(function(k){
    try {
      fetch(SFX[k][0]).then(function(r){ return r.arrayBuffer(); })
        .then(function(buf){ return new Promise(function(res, rej){ actx.decodeAudioData(buf, res, rej); }); })
        .then(function(b){ sfxBuf[k] = b; }).catch(function(){});
    } catch (e) {}
  });
}
function sfx(name){
  if (!sfxOn) return;
  var def = SFX[name]; if (!def) return;
  if (actx && sfxBuf[name]) {
    try {
      if (actx.state === 'suspended') actx.resume();
      var prev = sfxCur[name];
      if (prev) { try { prev.gain.gain.setTargetAtTime(0, actx.currentTime, .005);
                        prev.src.stop(actx.currentTime + .015); } catch (e) {} }
      var src = actx.createBufferSource(); src.buffer = sfxBuf[name];
      var g = actx.createGain(); g.gain.value = def[1];
      src.connect(g); g.connect(actx.destination);
      sfxCur[name] = {src:src, gain:g};
      src.onended = function(){ if (sfxCur[name] && sfxCur[name].src === src) sfxCur[name] = null; };
      src.start(0); return;
    } catch (e) {}
  }
  try {
    var a = sfxEl[name]; if (!a) { a = sfxEl[name] = new Audio(def[0]); a.volume = def[1]; }
    a.currentTime = 0; var pr = a.play(); if (pr && pr.catch) pr.catch(function(){});
  } catch (e) {}
}

/* ------------------------------------------------------------- geometry ----
   Read out of the engine so the preview matches what the server will do. */
function idx(r,c){ return r*N + c; }
function slid(b,a,z){
  if (a === z) return null;
  var ra = Math.floor(a/N), ca = a%N, rz = Math.floor(z/N), cz = z%N, x, y;
  if (ra !== rz && ca !== cz) return null;
  var n = b.slice(), t = b[a];
  if (ra === rz) {
    if (ca < cz) { for (x=ca; x<cz; x++) n[idx(ra,x)] = b[idx(ra,x+1)]; }
    else         { for (x=ca; x>cz; x--) n[idx(ra,x)] = b[idx(ra,x-1)]; }
    n[idx(ra,cz)] = t;
  } else {
    if (ra < rz) { for (y=ra; y<rz; y++) n[idx(y,ca)] = b[idx(y+1,ca)]; }
    else         { for (y=ra; y>rz; y--) n[idx(y,ca)] = b[idx(y-1,ca)]; }
    n[idx(rz,ca)] = t;
  }
  return n;
}
function findMatches(b){
  var raw = [], r, c, i;
  for (r=0; r<N; r++) { c=0; while (c<N) { var run=1;
    while (c+run<N && b[idx(r,c+run)] === b[idx(r,c)] && b[idx(r,c)] !== -1) run++;
    if (run>=3) { var g=[]; for (i=0;i<run;i++) g.push(idx(r,c+i)); raw.push({cells:g,type:b[idx(r,c)]}); }
    c += run; } }
  for (c=0; c<N; c++) { r=0; while (r<N) { var run2=1;
    while (r+run2<N && b[idx(r+run2,c)] === b[idx(r,c)] && b[idx(r,c)] !== -1) run2++;
    if (run2>=3) { var g2=[]; for (i=0;i<run2;i++) g2.push(idx(r+i,c)); raw.push({cells:g2,type:b[idx(r,c)]}); }
    r += run2; } }
  return raw;   // enough to answer "is this a move"; shape merging is the server's job
}

/* --------------------------------------------------------------- lookups ---
   fx carries an index into a side's array, which is also that Fighter's rank —
   the engine builds both teams front, mid, back. uid is what the DOM knows. */
function fighterAt(side, i){ return S && S[side] ? S[side][i] : null; }
function elFor(f){ return f ? document.querySelector('[data-id="'+f.uid+'"]') : null; }
function cellEl(i){ return gridEl.querySelector('[data-i="'+i+'"]'); }
function gemInfo(side, g){
  if (SHARED[g]) return SHARED[g];
  var list = S[side] || [];
  for (var i=0; i<list.length; i++) if (list[i].rank === g) return list[i].kit;
  return {emoji:'·', name:'—', note:''};
}
function artUrl(slot, name, size){ return ART+'/'+size+'/'+slot+'/'+name+'.png'; }
function logLine(kind, text){
  var l = $('log'); if (!l) return;
  var d = document.createElement('div'); d.className = 'log-'+kind; d.textContent = text;
  l.appendChild(d); l.scrollTop = l.scrollHeight;
}
/**
 * The one banner over the board, and the reason it takes a priority.
 *
 * A single wave announces itself several times in the SAME TICK. The engine
 * emits, in order: the wave (a chain number), then a multi-match, then every
 * hit, then a detonation, then any bomb armed -- and each of those handlers
 * returns a zero delay, because they are one simultaneous event. With a plain
 * last-writer-wins banner the final call was the only one ever seen, and the
 * final call is `arm`.
 *
 * So the biggest moment in the game was the one most reliably hidden: a MEGA
 * MULTI-MATCH is nine or more gems across two or more matches, which nearly
 * always contains a run of four, which arms a bomb, whose banner replaced it
 * in the same frame. Players saw "BOMB ARMED" and concluded multi-matches were
 * not implemented.
 *
 * Ranking them fixes it without holding the action back: a louder event still
 * interrupts immediately, a quieter one within the hold window is dropped
 * rather than queued, and anything arriving after the window shows normally.
 * Queueing was the other option and it is worse -- the banners would trail the
 * board they are describing.
 */
var BANNER_HOLD = 620, bannerAt = 0, bannerPri = 0;
function banner(text, pri){
  var c = $('combo'); if (!c) return;
  pri = pri || 1;
  var now = Date.now();
  if (now - bannerAt < BANNER_HOLD && pri <= bannerPri) return;
  bannerAt = now; bannerPri = pri;
  c.textContent = text; c.classList.remove('on'); void c.offsetWidth; c.classList.add('on');
}

/* ------------------------------------------------------------ token art ----
   Built ONCE per battle. Rewriting innerHTML per move re-creates all fourteen
   <img> layers, which flashes the whole Crew on a phone and kills any animation
   mid-flight by replacing the element running it. */
function tokHtml(f, mine){
  var t = f.traits || {};
  var layers = (t.background
      ? '<img class="bg" loading="lazy" alt="" src="'+artUrl('background',t.background,250)+'" onerror="this.remove()">' : '')
    + ['torso','weapon','arms','effects','head','headgear','companion']
      .filter(function(k){ return t[k]; })
      .map(function(k){ return '<img data-l="'+k+'" loading="lazy" alt="" src="'+artUrl(k,t[k],250)+'" onerror="this.remove()">'; })
      .join('');
  var rank = ['front','mid','back'][f.rank];
  return '<div class="tok'+(mine?' mine':' foe')+(f.ko?' ko':'')+'" data-id="'+f.uid+'"'
    + ' style="--gem:var(--g'+f.rank+')">'
    + '<div class="flash"></div>'
    + '<div class="danger"></div>'
    + '<div class="rk"><span>'+rank+'</span>'
    +   '<span class="mygem" title="'+(mine?'your':'their')+' '+rank+' — '+f.kit.name+', '+f.kit.note+'">'
    +   f.kit.emoji+'</span></div>'
    + '<div class="art">'+layers+'</div>'
    + '<div class="nm">'+f.name+'</div>'
    + '<div class="kitn" title="'+f.kit.name+' — '+f.kit.note+'">'+(mine?f.kit.emoji+' ':'')+f.kit.note+'</div>'
    + '<div class="hpwrap"><div class="hp"></div><div class="sh"></div></div>'
    + '<div class="hpn"><span></span><span></span></div>'
    + '</div>';
}
function buildTeams(){
  var byRank = function(a,b){ return a.rank - b.rank; };
  $('foeTeam').innerHTML = S.foes.slice().sort(byRank).map(function(f){ return tokHtml(f,false); }).join('');
  $('myTeam').innerHTML  = S.mine.slice().sort(byRank).map(function(f){ return tokHtml(f,true);  }).join('');
}
/* Patch only what moves. Art, name, rank and gem never change mid-battle. */
function paintTeams(){
  ['mine','foes'].forEach(function(sd){
    S[sd].forEach(function(f){
      var e = elFor(f); if (!e) return;
      if (f.ko) e.classList.add('ko'); else e.classList.remove('ko');
      var pct = f.hp / f.maxHp, bar = e.querySelector('.hp');
      if (bar) { bar.style.transform = 'scaleX('+pct+')';
                 bar.className = 'hp'+(pct<=.25?' crit':pct<=.55?' low':''); }
      // same threshold the bar uses, so the pulse and the red never disagree
      e.classList.toggle('crit', !f.ko && pct <= .25);
      var sh = e.querySelector('.sh');
      if (sh) sh.style.width = Math.min(100, (f.shield/f.maxHp)*100)+'%';
      var n = e.querySelector('.hpn');
      if (n) n.innerHTML = '<span>'+f.hp+'/'+f.maxHp+'</span><span>'
        + (f.shield>0 ? '🛡 '+f.shield+' ' : '') + (f.bleed>0 ? '🗡' : '') + '</span>';
    });
    var live = S[sd].filter(function(f){ return !f.ko; });
    var v = live.length ? Math.max.apply(null, live.map(function(f){ return f.surge; })) : 0;
    var el = $(sd === 'mine' ? 'chgMine' : 'chgFoes'); if (!el) return;
    el.className = 'chg'+(v>=10?' full':'');
    el.innerHTML = '⚡ <i><b style="width:'+(v/10*100)+'%"></b></i>'+v+'/10';
    el.title = v>=10 ? 'Charged — the next Charge match erupts' : 'Charge: '+v+' of 10';
  });
}
function paintBoard(dropAnim, settle){
  gridEl.style.gridTemplateColumns = 'repeat('+N+',1fr)';
  var h = '', i;
  for (i=0; i<N*N; i++) {
    var v = S.board[i], bm = S.bomb ? Math.abs(S.bomb[i]||0) : 0, gi = gemInfo('mine', v);
    var face = bm === BOMB_BOARD ? '💣' : bm === BOMB_CROSS ? '<i class="cross"></i>' : gi.emoji;
    var tip  = bm === BOMB_BOARD ? 'Board bomb — clears everything. Match its colour to set it off.'
             : bm === BOMB_CROSS ? 'Bomb — clears its row and column. Match its colour to set it off.'
             : gi.name+' — '+gi.note;
    h += '<div class="cell '+SHAPE[v]+(bm?' bomb'+(bm===BOMB_BOARD?' bomb2':''):'')
       + (dropAnim?' drop':'')+(settle?' settle':'')+'" data-i="'+i+'"'
       + ' style="--gc:var(--g'+v+')" title="'+tip+'">'
       + '<div class="g"><span class="em">'+face+'</span></div></div>';
  }
  gridEl.innerHTML = h;
}
function paintChrome(){
  var tb = $('terrain');
  if (tb && S.terrainBg) tb.style.backgroundImage = 'url("'+artUrl('background', S.terrainBg, 1000)+'")';
  /* Effect first, always: the legend answers "what does this gem do", and it is
     the only place that answer lives once the board is full. */
  function chip(colour, emoji, does, dead){
    return '<span class="lchip'+(dead?' gone':'')+'" style="--lc:'+colour+'"><b>'+emoji+'</b>'+does+'</span>';
  }
  var lg = [0,1,2].map(function(i){
    var f = null;
    S.mine.forEach(function(x){ if (x.rank === i) f = x; });
    return f ? chip('var(--g'+i+')', f.kit.emoji, f.kit.note, f.ko) : '';
  }).join('')
  + chip('var(--g3)','🛡️','shields crew')
  + chip('var(--g4)','⚡','erupts at 10');
  $('legend').innerHTML = lg; $('legendM').innerHTML = lg;
  var reach = '<b>3</b> front · <b>4</b> mid · <b>5+</b> back · cascades multiply'
            + (S.terrainName ? '<span class="terr">'+S.terrainName+'</span>' : '');
  $('reach').innerHTML = reach; $('reachM').innerHTML = reach;
  var bw = document.querySelector('.boardwrap');
  if (bw) { if (!S.over && S.turn === 'foes') bw.classList.add('foeturn'); else bw.classList.remove('foeturn'); }
  var fl = $('flag');
  fl.className = 'turnflag ' + (S.over ? '' : (S.turn === 'mine' ? 'you' : 'foe'));
  fl.textContent = S.over ? (S.over === 'mine' ? 'victory' : 'defeat')
                          : (S.turn === 'mine' ? 'your move' : 'their move');
  $('round').textContent = S.over ? '' : 'Round '+S.round;
}

/* -------------------------------------------------------- token effects ----
   Straight out of the prototype: the same motion, driven by the server's
   timeline instead of by a local rules pass. */
function flashTok(f, kind){
  var e = elFor(f); if (!e) return;
  var fl = e.querySelector('.flash'); if (!fl) return;
  fl.className = 'flash'+(kind?' '+kind:''); void fl.offsetWidth; fl.classList.add('on');
}
/* Top of the stack downward — companion first, torso last — so a Fighter comes
   apart in the reverse of the order it was built. The background stays: it is
   the ground the pieces fall against, not part of the body. */
var DEATH_ORDER = ['companion','headgear','head','effects','arms','weapon','torso'];
function killAnim(f){
  var e = elFor(f); if (!e) return;
  e.classList.add('dying'); flashTok(f,'big');
  var step = 80, n = 0;
  DEATH_ORDER.forEach(function(slot){
    var img = e.querySelector('.art img[data-l="'+slot+'"]'); if (!img) return;
    img.style.animationDelay = (n*step)+'ms';
    img.className = 'dis'+(n%2?' alt':'');     // alternate the spin: debris, not a rotation
    n++;
  });
  setTimeout(function(){ e.classList.remove('dying'); }, n*step + 640);
}
function shakeTok(f, big){
  var e = elFor(f); if (!e) return;
  e.classList.remove('hit','big'); void e.offsetWidth;
  e.classList.add('hit'); if (big) e.classList.add('big');
  flashTok(f, big?'big':'');
}
/** Animate trait layers together and in step. TAKES A LIST because some layers
 *  are rigidly attached in the art — headgear sits ON the head, so jolting the
 *  head alone slides the skull out from under its own helmet. */
function layerAnim(f, slots, cls){
  var e = elFor(f); if (!e) return;
  if (typeof slots === 'string') slots = [slots];
  slots.forEach(function(slot){
    var img = e.querySelector('.art img[data-l="'+slot+'"]'); if (!img) return;
    img.classList.remove(cls); void img.offsetWidth; img.classList.add(cls);
  });
}
function actTok(f){
  var e = elFor(f); if (!e) return;
  e.classList.remove('act'); void e.offsetWidth; e.classList.add('act');
  layerAnim(f,'weapon','swing');       // the weapon that did it actually swings
  layerAnim(f,'companion','bob');      // and the companion reacts alongside it
}
function pop(f, txt, kind){
  var e = elFor(f); if (!e) return;
  var p = document.createElement('div');
  p.className = 'pop on '+(kind||''); p.textContent = txt;
  e.appendChild(p); setTimeout(function(){ p.remove(); }, 950);
}
function shakeBoard(){
  var bw = document.querySelector('.boardwrap'); if (!bw) return;
  bw.classList.remove('shake'); void bw.offsetWidth; bw.classList.add('shake');
}

/* ================================ THE TIMELINE PLAYER =======================
   fx is what the server did, in order. Each event is handled and returns how
   long to wait before the next one. Nothing here reads a rule or a number that
   did not come down the wire.

   Every handler is guarded. An exception escaping this loop is not cosmetic: it
   would leave busy true and the board dead with the turn never handed back,
   which is exactly what one dangling reference did to the prototype.
   ============================================================================ */
function playTimeline(fx, state, done){
  var i = 0, hold = 190;      // how long the current clear sits before it falls

  function step(){
    if (i >= fx.length) { finish(); return; }
    var e = fx[i++], wait = 0;
    try { wait = handle(e); } catch (err) {
      logLine('sys','(effect skipped)');
      if (window.console) console.error('fx', e && e.k, err);
    }
    setTimeout(step, wait || 0);
  }

  function handle(e){
    switch (e.k) {

    case 'slide':
      /* A defending move gets a beat of its own before it happens. Without it
         their slide lands inside the tail of your own cascade and reads as more
         of your turn — the single most confusing thing in the prototype. */
      if (e.side === 'foes') {
        S.turn = 'foes'; paintChrome();
        var c = cellEl(e.a); if (c) c.classList.add('sel');
        setTimeout(function(){ doSlide(e); }, 420);
        return 640;
      }
      doSlide(e);
      return 150;

    case 'wave':
      /* A wave is a new beat, so the hold starts over. Without this the window
         spans waves: one multi-match on the first link of a cascade outranked
         every CHAIN announcement that followed it, and chains stopped appearing
         at all -- a fix for one hidden announcement that hid a different one.
         Priority is for resolving a collision INSIDE a wave; across waves the
         newer event simply wins. */
      bannerAt = 0;
      sfx('clear');
      // a deep chain is worth more than a shallow one, and outranks a bomb
      if (e.chain > 1) { sfx('chain'); banner('CHAIN x'+e.chain, e.chain >= 3 ? 4 : 3); }
      (e.cells||[]).forEach(function(j){ var el = cellEl(j); if (el) el.classList.add('clear'); });
      if (e.len >= 5) sfx('great');
      hold = 190;
      return 0;

    case 'multi':
      banner(e.mega ? '✦✦ MEGA MULTI-MATCH' : '✦ MULTI-MATCH', e.mega ? 6 : 5);
      sfx('chain');
      return 0;

    case 'act':    actTok(fighterAt(e.side, e.i)); return 0;

    case 'hit': {
      var t = fighterAt(e.side, e.i);
      pop(t, '-'+e.v, e.crit ? 'big' : '');
      shakeTok(t, !!e.crit);
      layerAnim(t, ['head','headgear'], 'jolt');   // together, or the skull slides out
      return 0;
    }
    case 'shielded': pop(fighterAt(e.side, e.i), '-'+e.v+' shield', 'heal'); return 0;
    case 'heal':     pop(fighterAt(e.side, e.i), '+'+e.v, 'heal');
                     flashTok(fighterAt(e.side, e.i), 'heal'); return 0;
    case 'shield':   flashTok(fighterAt(e.side, e.i), 'shield');
                     if (e.i === 0) sfx('shield'); return 0;
    case 'erupt': {
      var f = fighterAt(e.side, e.i);
      actTok(f); pop(f, 'SURGE!', 'big'); sfx('erupt');
      return 0;
    }
    case 'ko':
      sfx('ko'); killAnim(fighterAt(e.side, e.i));
      return 0;

    case 'boom':
      (e.cells||[]).forEach(function(j){
        var el = cellEl(j); if (el) el.classList.add(e.kind === BOMB_BOARD ? 'blast2' : 'blast');
      });
      /* A CHAIN SAYS SO. Half of all detonations set off more than one bomb,
         and the damage climbs steeply with each -- five of them hit twelve
         times as hard as one. Announcing every detonation as "BOMB" hid the
         difference between a tidy clear and the thing you had been hoarding
         for. It outranks almost everything when it is big, because it IS. */
      var n = e.n || 1;
      banner(n > 1 ? '💥 ' + n + ' BOMBS CHAIN'
                   : (e.kind === BOMB_BOARD ? '💣 BOARD BOMB' : '✛ BOMB'),
             n >= 3 ? 8 : (n > 1 ? 6 : (e.kind === BOMB_BOARD ? 5 : 3)));
      sfx('boom'); shakeBoard();
      hold = 460;         // the blast is held longer, or it is skipped past
      return 0;

    case 'arm':
      sfx(e.big ? 'armB' : 'armX');
      // armed, not detonated: a promise rather than an event, so it yields to
      // anything that actually happened this wave
      banner(e.up ? (e.big ? '💣 BOMB UPGRADED' : '✛ BOMB UPGRADED')
                  : (e.big ? '💣 BOARD BOMB ARMED' : '✛ BOMB ARMED'), e.big ? 3 : 2);
      return 0;

    case 'drop': {
      /* The authoritative board after this link of the cascade. The client has
         no collapse of its own — it is handed the result. */
      var h = hold; hold = 190;
      setTimeout(function(){
        S.board = e.board.slice(); S.bomb = e.bomb.slice();
        paintBoard(true); paintTeams(); sfx('land');
      }, h);
      return h + 170;
    }

    case 'reshuffle':
      logLine('sys','No moves left — board reshuffled.');
      banner('RESHUFFLE', 4);
      return 260;

    case 'again':
      banner('AGAIN', 6);
      return 320;

    case 'hurrah':
      /* Every bomb left on the board goes off once the battle is decided. Pure
         spectacle — the result is already settled, nothing is resolved by it. */
      banner('💥 LAST HURRAH', 9);
      (e.cells||[]).forEach(function(j){
        var el = cellEl(j); if (el) el.classList.add(e.kind === BOMB_BOARD ? 'blast2' : 'blast');
      });
      sfx('boom'); shakeBoard();
      return 700;

    default: return 0;
    }
  }

  function doSlide(e){
    var nb = slid(S.board, e.a, e.z);
    if (nb) { S.bomb = slid(S.bomb, e.a, e.z) || S.bomb; S.board = nb; }
    /* The settle pulse is for a board that has just moved on screen, which is
       true of a defending slide and no longer true of your own -- the preview
       has been showing the result since you let go, so pulsing it here is a
       double-take on a move you already made. */
    paintBoard(false, e.side === 'foes');
  }

  function finish(){
    // whatever the animation did to the local copy, the server's word is final
    S = state;
    paintBoard(); paintTeams(); paintChrome();
    (S.log||[]).forEach(function(line){ logLine(lineKind(line), line); });
    done && done();
  }

  step();
}
function lineKind(line){
  if (line.indexOf('☠') === 0 || line.indexOf('—') === 0) return 'big';
  if (line.indexOf('bleeds') !== -1 || line.indexOf('reshuffled') !== -1) return 'sys';
  return 'you';
}

/* --------------------------------------------------------------- moving ---- */
/**
 * One request. `fail` is not optional decoration: without it every failure ended
 * up in #log, which lives INSIDE the hidden battle view, so a start that went
 * wrong printed its explanation somewhere the player could not see and left the
 * button disabled with no way back but a refresh.
 *
 * A throw inside `cb` is treated as a failure too. It lands in the same promise
 * chain as a network error, and swallowing it silently is what turns a small
 * rendering bug into "the Arena stalls".
 */
function post(body, cb, fail, url){
  var fd = new FormData();
  Object.keys(body).forEach(function(k){
    if (Array.isArray(body[k])) body[k].forEach(function(v){ fd.append(k+'[]', v); });
    else fd.append(k, body[k]);
  });
  var done = false;
  // A fetch that never settles would leave the page waiting for ever. Twenty
  // seconds is far beyond a normal turn and still short enough to act on.
  var timer = setTimeout(function(){
    if (done) return;
    done = true;
    oops(new Error('timed out'));
  }, 20000);
  function oops(err){
    if (window.console) console.error('arena', body['do'], err);
    if (fail) fail(err); else logLine('sys','Lost contact with the Arena — your move was not played.');
    busy = false;
  }
  fetch(url || 'ajax/dhcarena-action.php', {method:'POST', body:fd, credentials:'same-origin'})
    .then(function(r){
      if (!r.ok) throw new Error('HTTP ' + r.status);
      return r.text();
    })
    .then(function(txt){
      // .json() would throw a parse error that says nothing about WHAT came
      // back. A PHP warning printed ahead of the payload is the likeliest
      // cause, and the first hundred characters of it name the file and line.
      var res;
      try { res = JSON.parse(txt); }
      catch (e) { throw new Error('bad reply: ' + txt.slice(0, 160)); }
      if (done) return;
      done = true; clearTimeout(timer);
      cb(res);
    })
    .catch(function(err){
      if (done) return;
      done = true; clearTimeout(timer);
      oops(err);
    });
}
function sendMove(a, z){
  busy = true;
  var body = practice
    ? {do:'move', spec:JSON.stringify(practice), a:a, z:z}
    : {do:'move', battle_id:battleId, a:a, z:z};
  post(body, function(res){
    if (res && res.ok && res.spec) practice = res.spec;
    if (!res || !res.ok) {
      busy = false;
      logLine('sys', (res && res.message) || 'That move was refused.');
      if (res && res.state) S = res.state;
      // Repaint either way: the board is still holding the dropped gem where
      // snapPreview() left it, and a refused move must not be left looking
      // like it was made.
      paintBoard(); paintTeams(); paintChrome();
      sfx('bad');
      return;
    }
    try {
      playTimeline(res.state.fx || [], res.state, function(){
        busy = false;
        if (res.over) showEnd(res);
      });
    } catch (e) {
      // The exchange happened on the server whatever the browser managed to
      // draw, so take the state and let play continue rather than freezing.
      busy = false;
      S = res.state; paintBoard(); paintTeams(); paintChrome();
      logLine('sys','(the animation was skipped)');
      if (window.console) console.error('arena timeline', e);
      if (res.over) showEnd(res);
    }
  }, function(){
    // Same reason, for a request that never came back at all: the preview is
    // standing in for a board state the server never confirmed, so put the
    // real one back rather than leaving a slide that did not happen on screen.
    paintBoard();
    logLine('sys','Lost contact with the Arena — that move was not played.');
  }, practice ? PRACTICE_URL : null);
}

/* ------------------------------------------------------------ the end ------ */
function showEnd(res){
  var won = res.over === 'mine';
  var card = $('endcard');
  var standing = S[won ? 'mine' : 'foes'].filter(function(f){ return !f.ko; });
  card.className = 'endcard '+(won?'win':'lose');
  $('ecTitle').textContent = won ? 'Victory' : 'Defeat';
  var sub;
  if (won) {
    sub = standing.length === 3
      ? 'Their Crew is down and yours did not lose a Fighter.'
      : standing.length+' of your Fighters still standing — '
        + standing.map(function(f){ return f.name.split(' ')[0]; }).join(' and ') + '.';
    /* Practice pays nothing and says so, rather than staying quiet and letting
       a player wonder where their trait went. */
    if (practice)                   sub += ' Practice — nothing was at stake.';
    else if (res.drop)              sub += ' A trait dropped — check your collection.';
    else if (res.rewarded === false) sub += ' No trait this time: the daily cap is spent.';
  } else {
    sub = practice
      ? 'Your Crew is down. Practice — nothing was at stake, so go again.'
      : 'Your Crew is down. The Fighters you sent are resurrecting — they will be back.';
  }
  $('ecSub').textContent = sub;
  $('ecStats').innerHTML =
      '<span><b>'+S.round+'</b>rounds</span>'
    + '<span><b>'+S.stats.bombs+'</b>bombs armed</span>'
    + '<span><b>'+S.stats.blasts+'</b>detonated</span>'
    + '<span><b>x'+S.stats.best+'</b>best chain</span>';
  $('ecAgain').textContent = practice ? 'Practice again' : 'Back to the Arena';
  $('ecLeave').hidden = !practice;
  card.hidden = false;
  sfx(won ? 'win' : 'lose');
  logLine('big', won ? 'VICTORY — their Crew is down.' : 'DEFEAT — your Crew is down.');
}


/* ------------------------------------------------------- input: dragging ---
   Live preview while dragging, because that IS the feel: the gem follows your
   finger along one axis and the gems it displaces shift the other way, so you
   see the move before committing it. Pointer events so mouse and touch are one
   path. A slide that matches nothing reverts for free and costs no turn. */
function cellSize(){
  var c = gridEl.querySelector('.cell'); if (!c) return 40;
  var r = c.getBoundingClientRect();
  var g = parseFloat(getComputedStyle(gridEl).gap) || 3;
  return r.width + g;
}
function clearOffsets(){
  gridEl.querySelectorAll('.cell').forEach(function(e){
    e.style.transition = ''; e.style.transform = ''; e.style.zIndex = '';
  });
}
function previewDrag(){
  if (!drag || !drag.axis) return;
  var sz = drag.size, a = drag.from, ra = Math.floor(a/N), ca = a%N;
  var raw = drag.axis === 'row' ? drag.dx : drag.dy;
  var maxBack = (drag.axis === 'row' ? ca : ra) * sz;
  var maxFwd  = ((N-1) - (drag.axis === 'row' ? ca : ra)) * sz;
  var off = Math.max(-maxBack, Math.min(maxFwd, raw));
  var steps = Math.round(off/sz);
  drag.to = drag.axis === 'row' ? idx(ra, ca+steps) : idx(ra+steps, ca);

  clearOffsets();
  var lead = cellEl(a);
  if (lead) { lead.style.zIndex = '6';
    lead.style.transform = drag.axis === 'row' ? 'translateX('+off+'px) scale(1.06)'
                                               : 'translateY('+off+'px) scale(1.06)'; }
  if (steps !== 0) {
    var dir = steps > 0 ? 1 : -1;
    for (var k=1; k<=Math.abs(steps); k++) {
      var i = drag.axis === 'row' ? idx(ra, ca+dir*k) : idx(ra+dir*k, ca);
      var e = cellEl(i); if (!e) continue;
      e.style.transition = 'transform .08s';
      e.style.transform = drag.axis === 'row' ? 'translateX('+(-dir*sz)+'px)'
                                              : 'translateY('+(-dir*sz)+'px)';
    }
  }
}
/**
 * A COMMITTED SLIDE DOES NOT SPRING BACK.
 *
 * clearOffsets() used to run before this even knew whether the move was being
 * committed, so every accepted slide played out as: gem snaps home, pause,
 * board jumps to the match. On a phone that pause is the network round trip
 * and it is long enough to read as a bug -- it looks like the move was
 * rejected and then reconsidered.
 *
 * It only looks that way because of WHERE the rules live now. In the prototype
 * the board updated in the same frame as the release, so wiping the drag
 * preview and redrawing were one step. Here the authoritative board is a
 * request away, and the preview is the only thing standing in for it until the
 * answer lands -- so it has to stay standing.
 *
 * The gem is snapped to the cell it was dropped on and LEFT there. When the
 * reply arrives the board is repainted into the arrangement it is already
 * showing, so there is nothing to see. A move that makes no match still
 * springs back, which is correct: that one really was refused.
 */
function snapPreview(from, to, axis, sz){
  var ra = Math.floor(from / N), ca = from % N;
  var steps = (axis === 'row') ? ((to % N) - ca) : (Math.floor(to / N) - ra);
  if (!steps) return;
  var dir = steps > 0 ? 1 : -1, off = steps * sz, k, i, e;
  var lead = cellEl(from);
  if (lead) {
    lead.style.transition = 'transform .09s ease-out';
    lead.style.zIndex = '6';
    // the 1.06 lift goes: the gem is landing now, not being carried
    lead.style.transform = (axis === 'row') ? 'translateX(' + off + 'px)'
                                            : 'translateY(' + off + 'px)';
  }
  for (k = 1; k <= Math.abs(steps); k++) {
    i = (axis === 'row') ? idx(ra, ca + dir * k) : idx(ra + dir * k, ca);
    e = cellEl(i); if (!e) continue;
    e.style.transition = 'transform .09s ease-out';
    e.style.transform = (axis === 'row') ? 'translateX(' + (-dir * sz) + 'px)'
                                         : 'translateY(' + (-dir * sz) + 'px)';
  }
}
function endDrag(commit){
  if (!drag) return;
  var from = drag.from, to = drag.to, axis = drag.axis, sz = drag.size;
  drag = null;
  if (!commit || to === undefined || to === from) { clearOffsets(); return; }
  var nb = slid(S.board, from, to);
  if (!nb) { clearOffsets(); return; }
  if (!findMatches(nb).length) {
    clearOffsets();          // a free revert, and it SHOULD look like one
    sfx('bad'); logLine('sys','No match on that slide — free, try another.');
    return;
  }
  snapPreview(from, to, axis, sz);
  sendMove(from, to);
}
gridEl.addEventListener('pointerdown', function(e){
  if (busy || !S || S.over || S.turn !== 'mine') return;
  var c = e.target.closest('.cell'); if (!c) return;
  e.preventDefault();
  if (gridEl.setPointerCapture) gridEl.setPointerCapture(e.pointerId);
  drag = {from:+c.getAttribute('data-i'), x0:e.clientX, y0:e.clientY, dx:0, dy:0,
          axis:null, size:cellSize(), to:undefined};
  sfx('pick'); c.classList.add('sel');
});
gridEl.addEventListener('pointermove', function(e){
  if (!drag) return;
  drag.dx = e.clientX - drag.x0; drag.dy = e.clientY - drag.y0;
  if (!drag.axis) {
    if (Math.abs(drag.dx) > 6 && Math.abs(drag.dx) > Math.abs(drag.dy)) drag.axis = 'row';
    else if (Math.abs(drag.dy) > 6 && Math.abs(drag.dy) > Math.abs(drag.dx)) drag.axis = 'col';
  }
  if (drag.axis) previewDrag();
});
function release(){
  if (!drag) return;
  gridEl.querySelectorAll('.cell.sel').forEach(function(x){ x.classList.remove('sel'); });
  endDrag(true);
}
gridEl.addEventListener('pointerup', release);
gridEl.addEventListener('pointercancel', function(){ if (drag) { clearOffsets(); drag = null; } });
window.addEventListener('pointerup', function(){ if (drag) release(); });

/* ------------------------------------------------------------- the shell --- */
var picked = [], rival = 0;

/* ---------------------------------------------------------------- paging ----
   Cards are hidden, never removed: a pick made on page 1 has to survive a walk
   to page 4, and rebuilding the grid would throw away the selected elements
   along with eight <img> layers apiece. */
var PER_PAGE = 12, page = 0, sortBy = 'might', kitFilter = '';
var allCards = [].slice.call(document.querySelectorAll('.a-card'));
var viewCards = allCards.slice();      // what the filter and sort left, in order
var pickGrid = document.querySelector('.a-pick');

function cardNum(c, k){ return +(c.getAttribute('data-'+k) || 0); }
/**
 * Apply the sort and the filter, then re-lay the grid.
 *
 * The cards are MOVED, never rebuilt. Rebuilding would drop the selection, and
 * selection is the entire point of this list -- a player who sorts by Toughest
 * to reconsider their back rank must not lose the two they had already chosen.
 * It also keeps the eight art layers per card out of the browser's way.
 */
function applyView(){
  viewCards = allCards.filter(function(c){
    return !kitFilter || c.getAttribute('data-kit') === kitFilter;
  });
  viewCards.sort(function(a, b){
    if (sortBy === 'might') return (cardNum(b,'hp')*cardNum(b,'pow')) - (cardNum(a,'hp')*cardNum(a,'pow'));
    return cardNum(b, sortBy) - cardNum(a, sortBy);
  });
  // a card the filter removed is hidden, not detached, so its pick survives
  allCards.forEach(function(c){ c.classList.add('off'); });
  viewCards.forEach(function(c){ pickGrid.appendChild(c); });
  page = 0;
  paintPage();
}
function pageCount(){ return Math.max(1, Math.ceil(viewCards.length / PER_PAGE)); }
function paintPage(){
  var n = pageCount();
  if (page >= n) page = n - 1;
  if (page < 0) page = 0;
  allCards.forEach(function(c){ c.classList.add('off'); });
  viewCards.forEach(function(c, i){
    if (Math.floor(i / PER_PAGE) === page) c.classList.remove('off');
  });
  var pg = $('aPager');
  if (pg) {
    pg.style.display = n > 1 ? 'flex' : 'none';
    $('aPageLbl').textContent = 'Page ' + (page+1) + ' of ' + n + ' · ' + viewCards.length
      + (viewCards.length === allCards.length ? ' Fighters' : ' of ' + allCards.length);
    $('aPrev').disabled = (page === 0);
    $('aNext').disabled = (page === n - 1);
  }
}
document.querySelectorAll('.a-tools button[data-sort]').forEach(function(btn){
  btn.addEventListener('click', function(){
    sortBy = btn.getAttribute('data-sort');
    document.querySelectorAll('.a-tools button[data-sort]').forEach(function(b){
      b.classList.toggle('on', b === btn);
    });
    applyView();
  });
});
document.querySelectorAll('.a-tools button[data-kit]').forEach(function(btn){
  btn.addEventListener('click', function(){
    kitFilter = btn.getAttribute('data-kit');
    document.querySelectorAll('.a-tools button[data-kit]').forEach(function(b){
      b.classList.toggle('on', b === btn);
    });
    applyView();
  });
});
/** The picks, as chips, always on screen. Click one to drop it. */
function paintPicked(){
  var box = $('aPicked'); if (!box) return;
  if (!picked.length) {
    box.innerHTML = '<span>Nobody picked yet — the order you pick is the formation.</span>';
    return;
  }
  var byId = {};
  allCards.forEach(function(c){ byId[+c.getAttribute('data-fid')] = c; });
  box.innerHTML = picked.map(function(id, i){
    var c = byId[id], nm = c ? c.querySelector('.nm').textContent : ('#'+id);
    return '<span class="chip" data-drop="'+id+'" title="Click to remove">'
         + '<b>'+['front','mid','back'][i]+'</b><s>'+nm+'</s><i>&times;</i></span>';
  }).join('');
  box.querySelectorAll('.chip').forEach(function(ch){
    ch.addEventListener('click', function(){
      var at = picked.indexOf(+ch.getAttribute('data-drop'));
      if (at !== -1) { picked.splice(at, 1); paintPicker(); }
    });
  });
}

function paintPicker(){
  allCards.forEach(function(c){
    var at = picked.indexOf(+c.getAttribute('data-fid'));
    c.classList.toggle('sel', at !== -1);
    /* The badge is the whole reason the picker is ordered. Without it the
       formation is decided by click order and never says so, which is a real
       decision taken away from the player by silence. */
    var b = c.querySelector('.pk');
    if (at === -1) { if (b) b.remove(); }
    else {
      if (!b) { b = document.createElement('div'); b.className = 'pk'; c.appendChild(b); }
      b.textContent = ['front','mid','back'][at] || (at+1);
    }
  });
  paintPicked();
  var go = $('aStart');
  if (go) go.disabled = !!BLOCKED || !(picked.length === CREW_SIZE && rival > 0);
  var msg = $('aMsg');
  if (!msg) return;
  if (BLOCKED)                      msg.textContent = '';
  else if (picked.length !== CREW_SIZE) msg.textContent = 'Pick '+(CREW_SIZE - picked.length)+' more.';
  else                              msg.textContent = rival ? '' : 'Now pick a rival.';
}
allCards.forEach(function(c){
  c.addEventListener('click', function(){
    if (c.getAttribute('data-ok') !== '1') return;      // still recovering
    var id = +c.getAttribute('data-fid'), at = picked.indexOf(id);
    if (at !== -1) picked.splice(at,1);
    else if (picked.length < CREW_SIZE) picked.push(id);
    paintPicker();
  });
});
if ($('aPrev')) $('aPrev').addEventListener('click', function(){ page--; paintPage(); });
if ($('aNext')) $('aNext').addEventListener('click', function(){ page++; paintPage(); });

document.querySelectorAll('.a-foe').forEach(function(r){
  r.addEventListener('click', function(){
    document.querySelectorAll('.a-foe').forEach(function(x){ x.classList.remove('sel'); });
    r.classList.add('sel'); rival = +r.getAttribute('data-uid');
    paintPicker();
  });
});
var wrap = document.querySelector('.arena-wrap');
/* --------------------------------------------------------- Fighter card ----
   What the board cannot say. Three traits decide a Fighter and five do not,
   and there is nowhere else in the game that tells you which is which.
   ---------------------------------------------------------------------- */
var SLOT_LABEL = {background:'Background', torso:'Torso', head:'Head', headgear:'Headgear',
                  arms:'Arms', weapon:'Weapon', effects:'Effects', effects1:'Effects',
                  effects2:'Effects 2', companion:'Companion', weaponBack:'Weapon (behind)'};
function prettySlug(sl){
  return String(sl||'').replace(/-/g,' ').replace(/\b\w/g, function(c){ return c.toUpperCase(); });
}
function traitOf(f, cat){
  var t = f.traits || {};
  if (t[cat]) return t[cat];
  // saved Fighters store effects1/effects2; a practice Crew stores effects
  if (cat === 'effects') return t.effects1 || t.effects2 || '';
  return '';
}
function tierOf(f, cat){
  var ti = f.tiers || {};
  return ti[cat] || (cat === 'effects' ? (ti.effects1 || ti.effects2 || '') : '');
}
function srcCell(f, cat){
  var sl = traitOf(f, cat);
  if (!sl) return '<span class="src">—</span>';
  var tier = tierOf(f, cat);
  return '<span class="src">' + prettySlug(sl)
       + (tier ? ' <em>' + tier + '</em>' : '') + '</span>';
}
/* Which worn pieces gave a Fighter this, named. A stat with no source says so
   rather than showing an empty cell -- "no optics" is the actionable version of
   a 6% crit chance. */
var ROLE_WORD = {optic:'optics', armour:'armour', weapon:'weapon',
                 energy:'energy', beast:'companion', plain:'no effect'};
function roleSrc(f, role, none){
  var r = f.roles || {}, hits = [];
  Object.keys(r).forEach(function(slot){
    if (r[slot] === role && traitOf(f, slot)) hits.push(prettySlug(traitOf(f, slot)));
  });
  if (!hits.length) return '<span class="src">' + (none || '—') + '</span>';
  return '<span class="src">' + hits.join(', ') + '</span>';
}
/* THE WHOLE POINT OF THE CARD. A trait does what it IS, not what slot it hangs
   on -- a blaster arm adds damage and a protector arm adds armour, from the same
   hook. Nothing else in the game says so, so this lists every piece and names
   the job it is doing. */
function pieceRows(f){
  var r = f.roles || {}, out = '';
  ['head','headgear','arms','effects','effects1','effects2','companion'].forEach(function(slot){
    var sl = traitOf(f, slot); if (!sl || !r[slot]) return;
    out += '<div class="fc-row"><span class="k">' + (SLOT_LABEL[slot] || slot) + '</span>'
         + '<span class="v" style="font-size:10px">' + (ROLE_WORD[r[slot]] || r[slot]) + '</span>'
         + srcCell(f, slot) + '</div>';
  });
  return out || '<div class="fc-row"><span class="src">Nothing but a torso, a head and a weapon.</span></div>';
}
function openCard(f, mine){
  var box = $('fcBox'); if (!box) return;
  var t = f.traits || {};
  var rank = ['front','mid','back'][f.rank];
  var layers = (t.background
      ? '<img class="bg" alt="" src="'+artUrl('background',t.background,250)+'" onerror="this.remove()">' : '')
    + ['torso','weapon','arms','effects','effects1','effects2','head','headgear','companion']
      .filter(function(k){ return t[k]; })
      .map(function(k){ return '<img alt="" src="'+artUrl(k.replace(/[12]$/,''),t[k],250)+'" onerror="this.remove()">'; })
      .join('');

  var pct = Math.round(f.hp / f.maxHp * 100);
  box.innerHTML =
      '<button class="fc-x" id="fcX" title="Close">&times;</button>'
    + '<div class="fc-top">'
    +   '<div class="fc-art">'+layers+'</div>'
    +   '<div class="fc-id">'
    +     '<h3>'+f.name+'</h3>'
    +     '<div class="fc-who">'+(mine?'your':'enemy')+' '+rank+' rank</div>'
    +     '<div class="fc-gem" style="--gemc:var(--g'+f.rank+')"><b>'+f.kit.emoji+'</b>'
    +       'matching this gem makes them act</div>'
    +     '<div class="fc-kit"><b>'+f.kit.name+'</b> — '+f.kit.note+'</div>'
    +   '</div>'
    + '</div>'
    + '<div class="fc-body">'
    +   '<p class="fc-h">What decides this Fighter</p>'
    +   '<div class="fc-rows">'
    +     row('Health', f.hp+' / '+f.maxHp+' <i style="opacity:.5">('+pct+'%)</i>', srcCell(f,'torso'))
    +     row('Power',  f.power, srcCell(f,'weapon'))
    +     row('Style',  f.kit.name, srcCell(f,'weapon'))
    +     row('Crit',   Math.round((f.crit||0)*100)+'%', roleSrc(f,'optic','no optics'))
    +     (f.resist > 0 ? row('Armour', Math.round(f.resist*100)+'% less',
                             roleSrc(f,'armour','')) : '')
    +     (f.assist > 0 ? row('Assist', Math.round(f.assist*100)+'% extra hit',
                             roleSrc(f,'beast','')) : '')
    +     (f.charge > 1 ? row('Charge', '&times;'+f.charge.toFixed(2)+' rate',
                             roleSrc(f,'energy','')) : '')
    +   '</div>'
    +   '<p class="fc-h">Every piece, and what it does</p>'
    +   '<div class="fc-rows">' + pieceRows(f) + '</div>'
    +   '<p class="fc-h">Right now</p>'
    +   '<div class="fc-rows">'
    +     row('Shield', f.shield || 0, '<span class="src">absorbs damage before health</span>')
    +     row('Charge', (f.surge||0)+' / 10', '<span class="src">erupts on the Crew\u2019s next '+SHARED[4].emoji+'</span>')
    +     row('Damage', f.dealt || 0, '<span class="src">dealt so far this battle</span>')
    +     (f.bleed > 0 ? row('Bleeding', f.bleed+' rounds', '<span class="src">loses health at the end of each</span>') : '')
    +   '</div>'
    +   '<p class="fc-note">Every piece does something, and what it does comes from'
    +     ' what it <b>is</b> rather than the slot it sits in — a blaster arm adds damage,'
    +     ' a protector arm adds armour. Reach is match size, not this Fighter: <b>3</b>'
    +     ' touches their front rank, <b>4</b> reaches mid, <b>5 or more</b> reaches the back.'
    +     ' The background matters for one Fighter only — the defender\u2019s front rank,'
    +     ' whose background is the arena.</p>'
    + '</div>';
  function row(k, v, src){
    return '<div class="fc-row"><span class="k">'+k+'</span><span class="v">'+v+'</span>'+src+'</div>';
  }
  $('fcard').hidden = false;
  var x = $('fcX'); if (x) x.addEventListener('click', closeCard);
}
function closeCard(){ var c = $('fcard'); if (c) c.hidden = true; }
var fcardEl = $('fcard');
if (fcardEl) {
  fcardEl.addEventListener('click', function(e){
    if (e.target === fcardEl) closeCard();       // the backdrop, not the card
  });
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape' && !fcardEl.hidden) closeCard();
  });
}
/* Delegated, because the tokens are rebuilt at the start of every battle. */
['myTeam','foeTeam'].forEach(function(id){
  var host = $(id); if (!host) return;
  host.addEventListener('click', function(e){
    var tok = e.target.closest('.tok'); if (!tok || !S) return;
    var uid = tok.getAttribute('data-id');
    var side = id === 'myTeam' ? 'mine' : 'foes', f = null;
    S[side].forEach(function(x){ if (x.uid === uid) f = x; });
    if (f) openCard(f, side === 'mine');
  });
});

/* ------------------------------------------------------------- entrance ----
   Three beats: the arena and what it does, the board falling in, the two Crews
   walking on. Under three seconds, skippable with a tap, and it never runs on
   resume -- see the note in the stylesheet.

   EVERY STEP IS OPTIONAL. The battle is already loaded and playable before this
   starts; the sequence only holds input for its own duration. If anything in
   here throws, finish() still runs and the board is live. A cinematic must
   never be able to cost somebody their turn. */
var cineTimers = [], cineDone = null;
/**
 * End the sequence.
 *
 * @param keepCover  leave the board hidden instead of revealing it. Used when
 *   the reason we are stopping is that ANOTHER battle is on its way: dealing a
 *   new practice battle, or going again from the end card, both stop the
 *   current sequence and then wait on a request. Revealing the old board for
 *   the length of that round trip is a flash of exactly the thing the entrance
 *   exists to build up to -- and it reads as the board appearing before the
 *   cinematic, which is what it looked like the first time this went wrong.
 */
function cineStop(keepCover){
  cineTimers.forEach(function(t){ clearTimeout(t); });
  cineTimers = [];
  var el = document.querySelector('.a-intro');
  if (el) el.remove();
  var ar = document.querySelector('.arena');
  if (ar) {
    ar.classList.remove('board', 'crew');
    if (keepCover) ar.classList.add('cine'); else ar.classList.remove('cine');
  }
  gridEl.querySelectorAll('.cell').forEach(function(c){ c.style.animationDelay = ''; });
  if (cineDone) { var d = cineDone; cineDone = null; d(); }
}
function cineAt(ms, fn){ cineTimers.push(setTimeout(fn, ms)); }

function playEntrance(done){
  var ar = document.querySelector('.arena');
  if (!ar) { done(); return; }
  cineDone = done;
  ar.classList.add('cine');          // already set by openBattle(); harmless twice
  busy = true;                       // no moves until the Crews are on

  var terr = S.terrainBg ? artUrl('background', S.terrainBg, 1000) : '';
  var intro = document.createElement('div');
  intro.className = 'a-intro go';
  if (terr) intro.style.backgroundImage = 'url("' + terr + '")';
  intro.innerHTML =
      '<div class="ti-k">The arena</div>'
    + '<div class="ti-n">' + (S.terrainName || 'Unknown Ground') + '</div>'
    + '<div class="ti-e">' + (S.terrainNote || '') + '</div>'
    + '<div class="ti-v">' + (S.mine[0] ? '<b>Your Crew</b>' : '') + ' versus <b>'
    +   (S.foes[0] ? S.foes[0].name.split(' ')[0] + "'s Crew" : 'the defenders') + '</b></div>'
    + '<div class="ti-s">tap to skip</div>';
  intro.addEventListener('click', cineStop);
  ar.appendChild(intro);

  // beat two: the board falls in, a diagonal sweep rather than all at once.
  // The delays are set BEFORE the grid is revealed, so `both` holds every cell
  // at its opening frame until its own turn comes round.
  cineAt(1450, function(){
    intro.classList.add('out');
    gridEl.querySelectorAll('.cell').forEach(function(c, i){
      var r = Math.floor(i / N), col = i % N;
      c.style.animationDelay = ((r + col) * 26) + 'ms';
    });
    ar.classList.add('board');
    sfx('land');
  });
  // beat three: the Crews walk on
  cineAt(2050, function(){
    if (intro.parentNode) intro.remove();
    ar.classList.add('crew');
    var toks = document.querySelectorAll('.teamcol .tok');
    toks.forEach(function(t, i){ t.style.animationDelay = ((i % 3) * 90) + 'ms'; });
    sfx('start');
  });
  cineAt(2750, cineStop);
}

/**
 * @param res    the start/resume reply
 * @param fresh  true for a battle that begins now. A resumed battle is drawn
 *               immediately: it is not an entrance, and replaying the fanfare
 *               for a fight already in progress is the drop-reveal mistake.
 */
function openBattle(res, fresh){
  battleId = res.battle_id; S = res.state;
  setup.style.display = 'none';
  battle.classList.add('on');
  wrap.classList.add('playing');
  $('endcard').hidden = true;
  $('log').innerHTML = '';
  /* BEFORE anything is drawn, not after. playEntrance() used to add this at the
     end of openBattle(), which left a window -- one synchronous task today, but
     one await away from being a visible frame of the finished board -- in which
     the board existed on screen with nothing over it. Setting it first means
     the grid is never in a visible state at any point before its beat. */
  var arEl = document.querySelector('.arena');
  if (arEl && fresh && !res.state.over) arEl.classList.add('cine');
  $('aReroll').hidden = !practice;
  buildTeams(); paintBoard(); paintTeams(); paintChrome();
  (S.log||[]).forEach(function(l){ logLine('sys', l); });
  sfxInit();
  battle.scrollIntoView({behavior:'smooth', block:'start'});
  if (!fresh || S.over) { sfx('start'); return; }
  try { playEntrance(function(){ busy = false; }); }
  catch (e) {
    // the battle is already drawn and live; the sequence was the optional part
    busy = false; sfx('start');
    if (window.console) console.error('arena entrance', e);
  }
}
/**
 * Draw two fresh Crews and open the battle. Every route into practice goes
 * through here -- the Practice button on the setup screen, New battle in the
 * toolbar, and Practice again on the end card -- so all three behave the same
 * and there is one place where "start a practice battle" is defined.
 *
 * @param onFail  what to do if it does not come back; the setup screen can say
 *                so in its message line, the in-battle button cannot.
 */
function startPractice(onFail){
  if (busy) return;
  // cineStop() runs the pending done-callback, which clears busy -- so claim it
  // afterwards or this claims nothing. keepCover: the board stays hidden until
  // the battle we are fetching is ready to show it.
  cineStop(true);
  busy = true;
  $('endcard').hidden = true;
  post({do:'new'}, function(res){
    busy = false;
    if (!res || !res.ok) { cineStop(); onFail && onFail('Could not start practice.'); return; }
    practice = res.spec;
    battleId = 0;
    try { openBattle(res, true); }
    catch (e) {
      practice = null;
      if (window.console) console.error('arena practice', e);
      onFail && onFail('Could not draw the battle.');
    }
  }, function(){
    busy = false;
    cineStop();          // uncover whatever is there; nothing new is coming
    onFail && onFail('The Arena did not answer.');
  }, PRACTICE_URL);
}

var practiceBtn = $('aPractice');
if (practiceBtn) practiceBtn.addEventListener('click', function(){
  if (busy || battle.classList.contains('on')) return;
  practiceBtn.disabled = true;
  $('aMsg').textContent = 'Drawing two Crews…';
  startPractice(function(msg){ $('aMsg').textContent = msg; });
  // re-enabled either way: openBattle() hides the setup screen on success
  setTimeout(function(){ practiceBtn.disabled = false; }, 400);
});

/* NEW BATTLE, mid-practice. Practice Crews are dealt at random, so the first
   thing a player wants is often a different hand -- a matchup they fancy, or a
   kit they have never seen. Making them finish or leave to get one is the
   difference between a sandbox and a queue.
   Practice only: a ranked opponent is chosen, not dealt. */
var rerollBtn = $('aReroll');
if (rerollBtn) rerollBtn.addEventListener('click', function(){
  if (!practice || busy) return;
  rerollBtn.disabled = true;
  startPractice(function(){ logLine('sys','Could not draw a new battle.'); });
  setTimeout(function(){ rerollBtn.disabled = false; }, 400);
});

var startBtn = $('aStart');
function startFailed(msg){
  busy = false;
  paintPicker();                 // re-enables the button if a Crew is still picked
  startBtn.disabled = false;
  $('aMsg').textContent = msg;
  /*
   * THE BATTLE MAY EXIST ANYWAY. dhca_start() inserts the row and saves the
   * board before the reply is written, so a request that fails on the way back
   * -- or a reply this page cannot render -- still leaves a real battle on the
   * server. That is exactly the state where every further click was refused
   * with "you are in the middle of a battle" and only a refresh recovered,
   * because a refresh is the one thing that runs resume.
   *
   * So run resume here instead of making the player discover that.
   */
  post({do:'resume'}, function(res){
    if (!res || !res.ok) return;
    $('aMsg').textContent = '';
    // A battle nobody has moved in yet is a battle that is only just starting,
    // whatever went wrong on the way here, so it still gets its entrance.
    openBattle(res, !res.state.moves);
    logLine('sys','Recovered a battle that had already started.');
  }, function(){});
}
if (startBtn) startBtn.addEventListener('click', function(){
  if (busy) return;
  if (battle.classList.contains('on')) return;   // already in one
  busy = true; startBtn.disabled = true; practice = null;
  $('aMsg').textContent = 'Entering…';
  post({do:'start', defender:rival, fighters:picked}, function(res){
    if (!res || !res.ok) { startFailed((res && res.message) || 'Could not start.'); return; }
    busy = false; startBtn.disabled = false;
    $('aMsg').textContent = '';
    /* A throw in here used to vanish into the promise chain, leaving the setup
       screen up, the battle live on the server and no explanation anywhere. */
    try { openBattle(res, true); }
    catch (e) { startFailed('Could not draw the battle. Reopening it…'); }
  }, function(err){
    startFailed('The Arena did not answer. Checking whether the battle started…');
  });
});
/* Leaving does not abandon anything: a ranked battle is on the server and
   resume picks it up exactly where it was, and a practice battle was never
   worth keeping. */
function leaveBattle(){
  cineStop(); practice = null;
  $('endcard').hidden = true;
  // hidden by #arenaBattle going away regardless, but leaving it set would be a
  // stale flag waiting to be believed
  $('aReroll').hidden = true;
  battle.classList.remove('on'); wrap.classList.remove('playing'); setup.style.display = '';
  setup.scrollIntoView({behavior:'smooth', block:'start'});
}
$('ecLeave').addEventListener('click', leaveBattle);
$('aLeave').addEventListener('click', leaveBattle);
$('ecAgain').addEventListener('click', function(){
  // Practice costs nothing, so going again should cost nothing either -- not a
  // page load and not a trip through the Crew picker.
  if (practice) startPractice(function(){ location.reload(); });
  else location.reload();
});

$('howto').addEventListener('click', function(){ wrap.classList.toggle('showintro'); });

var muteBtn = $('mute');
function paintMute(){
  muteBtn.textContent = sfxOn ? '🔊' : '🔇';
  muteBtn.title = sfxOn ? 'Mute sound' : 'Unmute sound';
}
muteBtn.addEventListener('click', function(){
  sfxOn = !sfxOn;
  try { localStorage.setItem('dhcarena_sfx', sfxOn ? '1' : '0'); } catch (e) {}
  paintMute(); if (sfxOn) { sfxInit(); sfx('pick'); }
});
paintMute();
applyView();
paintPicker();

/* A battle left open in another tab, or on a phone that went to sleep, is still
   the one you owe a move to — the server will refuse a new one until it ends. */
post({do:'resume'}, function(res){
  if (!res || !res.ok) return;
  // The player can press Enter before this lands; whoever opened a battle first
  // owns the screen.
  if (battle.classList.contains('on') || practice) return;
  try {
    openBattle(res, !res.state.moves);
    logLine('sys', res.state.moves ? 'Picked up where you left off.'
                                   : 'A battle was waiting for you.');
  } catch (e) {
    $('aMsg').textContent = 'A battle is in progress but could not be drawn. Try reloading.';
    if (window.console) console.error('arena resume', e);
  }
}, function(){ /* nothing in progress is the normal case; stay quiet */ });
})();
</script>

<?php
/*
 * dhc-dropmodal.php is already pulled in by header.php platform-wide, so a win
 * that drops a trait reveals it here without this page doing anything.
 */
$conn->close();
?>
</html>
