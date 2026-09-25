<?php
/**
 * dhcarena-prototype.php — THROWAWAY feel prototype for DHC Arena.
 *
 * REBUILT as a puzzle-battler after the first prototype played as a menu.
 * Playtest verdict on v1, verbatim: "found myself getting bored and
 * unengaged... selecting the best option out of 4 each time and hoping for the
 * best roll." Correct. v1 had no execution layer — all the skill lived in the
 * build, and nothing you did during a battle was done WELL or badly.
 *
 * WHAT MAKES THIS NOT MONSTROCITY. Monstrocity is already a shared-board,
 * alternating-turn match-3, so that shape cannot be the difference. Two rules
 * make Arena its own game, and both come straight out of dhcarena.md:
 *
 *   1. EACH OF YOUR THREE FIGHTERS OWNS A GEM COLOUR. Matching that colour is
 *      how that Fighter acts. The board therefore means something different
 *      depending on who is in your Crew — your team composition literally
 *      rewrites what a good move is. Monstrocity's five tile types mean the
 *      same thing in every battle.
 *   2. MATCH SIZE IS REACH. Three hits their front rank, four reaches their
 *      mid, five or more reaches their back. §3bb's formation becomes the
 *      thing the puzzle is about: their healer is hiding at the back and a
 *      3-match cannot touch it.
 *
 * Together those make the trait economy the game. Your weapon decides what
 * your colour DOES; your Crew decides which colours you have at all.
 *
 * Still deliberately not the real build: no db.php, no skulliance.php, no
 * session, no login, nothing saved. Rules are JS; they port to
 * dhcarena-engine.php once the feel is right. Real traits from dhcrarity.php,
 * real art from the dhc/web tree.
 */
$dhc_rarity = is_file(__DIR__ . '/dhcrarity.php') ? (require __DIR__ . '/dhcrarity.php') : array();
if (!$dhc_rarity) { http_response_code(500); exit('dhcrarity.php missing — nothing to build Fighters from.'); }

$ART = 'dhc/web';
$excl = array();
if (is_file(__DIR__ . '/dhcfighters-config.php')) {
	require_once __DIR__ . '/dhcfighters-config.php';
	if (defined('DHCF_HEADGEAR_EXCLUDED_BY_HEAD')) $excl = DHCF_HEADGEAR_EXCLUDED_BY_HEAD;
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>DHC Arena — Prototype</title>
<style>
:root{--ink:#0d0f13;--panel:#151922;--panel2:#1d2230;--line:#2b3345;--bone:#e8e6e1;
  --dim:#8b93a7;--teal:#00c8a0;--ochre:#f5a623;--blood:#e0466b;--shield:#5aa9ff;
  --g0:#e0466b;--g1:#f5a623;--g2:#8b7bd8;--g3:#5aa9ff;--g4:#00c8a0;}
*{box-sizing:border-box}
body{margin:0;background:var(--ink);color:var(--bone);
  font:13px/1.5 "JetBrains Mono",ui-monospace,Menlo,monospace;
  padding:env(safe-area-inset-top,0) 0 env(safe-area-inset-bottom,0);
  -webkit-user-select:none;user-select:none}
.wrap{max-width:1500px;margin:0 auto;padding:12px;overflow-x:clip}
/* Nothing may scroll the page sideways. A phone has no spare width and a
   horizontal scrollbar makes a board feel broken to drag on. */
html,body{max-width:100%;overflow-x:hidden}
h1{font-size:15px;letter-spacing:.14em;text-transform:uppercase;margin:0 0 2px}
.sub{color:var(--dim);font-size:10.5px;margin:0 0 10px}
button{font:inherit;cursor:pointer;border-radius:3px}
.btn{background:var(--panel2);color:var(--bone);border:1px solid var(--line);padding:6px 12px}
.btn:hover{border-color:var(--ochre);color:var(--ochre)}
.btn.go{background:var(--blood);border-color:var(--blood);color:#fff}
.top{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:10px}
#howto{display:none;padding:6px 11px}
.turnflag{font-size:10px;letter-spacing:.12em;text-transform:uppercase;padding:3px 9px;
  border-radius:999px;border:1px solid var(--line);color:var(--dim)}
.turnflag.you{border-color:var(--teal);color:var(--teal)}
.turnflag.foe{border-color:var(--blood);color:var(--blood)}

.arena{position:relative;display:grid;grid-template-columns:minmax(0,270px) minmax(0,1fr) minmax(0,270px);
  gap:14px;align-items:start}
/* The board must not simply eat the extra width -- a 1000px square does not fit
   a laptop viewport. Cap it against viewport HEIGHT and centre it, and the
   width freed up goes to the Fighters, which is the point. */
/* The cap belongs to the whole column, not just the board. Applied only to the
   boardwrap it left the reach line and the legend stretching the full column
   width, so they ran wider than the thing they describe. */
.boardcol > *{max-width:min(74vh,760px);margin-left:auto;margin-right:auto}
.teamcol{display:grid;gap:6px}
.coltag{font-size:9px;letter-spacing:.16em;text-transform:uppercase;margin-bottom:5px;
  padding-bottom:4px;border-bottom:1px solid var(--line);
  display:flex;align-items:center;justify-content:space-between;gap:8px}
/* CHARGE IS A TEAM METER. Every living Fighter gains it together, so showing it
   three times in three token corners was repetition -- and once the text was
   shortened to "⚡ 7" it stopped saying what it was counting towards, which is
   the only thing you actually want to know. One bar per Crew, beside the name. */
.chg{display:flex;align-items:center;gap:5px;letter-spacing:0;text-transform:none;
  font-size:9px;color:var(--dim);white-space:nowrap}
.chg i{display:block;width:46px;height:5px;border-radius:3px;background:#0b0d11;
  overflow:hidden;flex:none}
.chg i b{display:block;height:100%;width:0;background:var(--g4);
  transition:width .35s cubic-bezier(.2,.7,.3,1)}
.chg.full{color:var(--g4);animation:chgPulse 1s ease-in-out infinite}
.chg.full i b{box-shadow:0 0 8px var(--g4)}
@keyframes chgPulse{0%,100%{opacity:1}50%{opacity:.55}}
.coltag.you{color:var(--teal)} .coltag.foe{color:var(--blood)}
.boardcol{min-width:0}
.legend.mobonly,.reach.mobonly{display:none}
@media (max-width:1000px){
  /* Stack, and put the enemies above the board where they read as the opposition */
  .arena{grid-template-columns:1fr}
  .teamcol{grid-template-columns:repeat(3,minmax(0,1fr))}
  .teamwrap.foes{order:-1}
  .teamwrap.mine{order:1}
  /* Enemies, board and your Crew have to fit one screen together, so the
     legend goes below all three rather than wedging between the board and
     your own Fighters. */
  .legend.deskonly,.reach.deskonly{display:none}
  .legend.mobonly{display:flex;margin-top:8px}
  .reach.mobonly{display:flex;margin-top:10px}
  /* PACKED TIGHT. Every gap between your Crew, the board and theirs is space
     the board could be using, and on a phone the three have to share one
     screen. The title hides with the intro rather than being deleted -- both
     come back together behind the ? button, so nothing is lost, it is just not
     paying rent on a 750px screen. */
  .wrap{padding:8px}
  .arena{gap:5px}
  .arenabg{inset:0}          /* no bleed: it was pushing 6px past the viewport */
  .boardwrap{padding:5px}
  .coltag{font-size:8px;margin-bottom:2px;padding-bottom:2px}
  .top{margin-bottom:6px;gap:6px}
  .teamcol{gap:4px}
  /* Squat tokens. Three-up at phone width the art would be square at ~128px,
     and two rows of that plus the board came to about 794px -- over the fold on
     most phones even after the legend moved. 96px brings the core play area
     near 730px, which fits. The whole figure still shows; it is letterboxed
     into a shorter frame rather than cropped. */
  .teamcol .tok .art{aspect-ratio:auto;height:96px}
  /* Three tokens shoulder to shoulder with a 4px gap: an 8% pop-out overlaps
     its neighbours. Smaller on a phone, full size in the desktop columns. */
  .tok.act{animation:actSmall .34s}
  .teamcol .tok{padding:4px}
  .teamcol .tok .nm{font-size:9px}
  .teamcol .tok .kitn{font-size:7.5px}
  .coltag{margin-bottom:3px;padding-bottom:3px}
  /* Title and intro are one unit behind the ? -- a wall of rules plus a
     heading costs most of a screen on a phone, and neither is needed while
     you are playing. One tap brings both back. */
  h1,.sub{display:none}
  body.showintro h1{display:block}
  body.showintro .sub{display:block}
  #howto{display:inline-block}
}

/* ---- teams ---- */
.tok{background:var(--panel2);border:1px solid var(--line);border-radius:3px;padding:5px;
  position:relative;transition:transform .16s,opacity .3s,border-color .15s}
.tok{border-left:3px solid var(--gem)}
.tok .rk{font-size:7.5px;letter-spacing:.12em;text-transform:uppercase;color:var(--dim);
  display:flex;justify-content:space-between;gap:4px}
.tok .mygem{display:inline-flex;align-items:center;justify-content:center;
  width:19px;height:19px;border-radius:50%;background:var(--gem);font-size:11px;
  box-shadow:inset 0 -2px 4px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.12);flex:none}
.tok .art{position:relative;width:100%;aspect-ratio:1;overflow:hidden;border-radius:2px;
  background:repeating-conic-gradient(#191419 0% 25%,#201b20 0% 50%) 50%/9px 9px;margin:2px 0}
.tok .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
/* PER-LAYER MOTION. Only possible because a Fighter is still separate images at
   render time -- the same separation the layering rules needed.
   Kept SMALL on purpose. Each layer is a full 250px frame that is mostly
   transparent, so it rotates about the frame's centre rather than the weapon's
   grip, and .art clips at the edge. A few degrees reads as a swing; twenty
   would read as the picture coming apart.
   Transform only, no filters: transforms are composited on the GPU, so this
   costs nothing per frame, which matters after the audio lesson. */
.tok .art img[data-l="weapon"]{transform-origin:50% 65%}
.tok .art img.swing{animation:swing .34s ease-out}
@keyframes swing{0%{transform:rotate(0) translateX(0)}
  30%{transform:rotate(-7deg) translateX(-2%)}
  55%{transform:rotate(4deg) translateX(1%)}
  100%{transform:rotate(0) translateX(0)}}
.tok .art img.bob{animation:bob .42s ease-in-out}
@keyframes bob{0%{transform:translateY(0)}40%{transform:translateY(-5%)}100%{transform:translateY(0)}}
.tok .art img.jolt{animation:jolt .30s ease-out}
@keyframes jolt{0%{transform:translateX(0)}35%{transform:translateX(3%) rotate(2deg)}
  100%{transform:translateX(0)}}
/* the Fighter's own background fills the frame and sits well back */
/* NO TREATMENT. The background is drawn as it is, same as the assembler and
   the gallery draw it. It had a brightness filter, a saturate filter and a
   dark scrim stacked on it to keep the figure readable, and the result was
   art that looked broken rather than art that sat back. If a busy background
   ever does swallow a figure, that is a reason to say so about that trait --
   not to dim all 42 of them. */
.tok .art img.bg{object-fit:cover}
.tok .nm{font-size:9.5px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.tok .kitn{font-size:8px;color:var(--dim);white-space:nowrap;overflow:hidden;text-overflow:ellipsis}
.hpwrap{position:relative;height:6px;background:#0b0d11;border-radius:2px;overflow:hidden;margin-top:3px}
.hp{position:absolute;inset:0;background:var(--teal);transform-origin:left;
  transition:transform .4s cubic-bezier(.2,.7,.3,1)}
.hp.low{background:var(--ochre)}.hp.crit{background:var(--blood)}
.sh{position:absolute;top:0;left:0;height:100%;background:var(--shield);opacity:.8;transition:width .4s}
/* Fixed height and no wrapping. This line gains "sh 24" and "charge 7/10" the
   moment Shield or Charge match, and on a phone-width token that wrapped to a
   second line -- so the token grew, the row shoved, and the Crew jolted. On
   desktop there was room, so it never wrapped and nothing moved: the same
   report from both ends. */
.hpn{font-size:8px;color:var(--dim);font-variant-numeric:tabular-nums;display:flex;
  justify-content:space-between;gap:4px;white-space:nowrap;overflow:hidden;
  height:12px;line-height:12px}
.hpn span{overflow:hidden;text-overflow:ellipsis}
/* The layers now carry the fade themselves, so the token only desaturates.
   Stacking the old opacity:.3 on top of layers that already end near .2 left
   the art at about 6% -- a fallen Fighter should read as a wreck you can still
   name, not an empty box. The text stays legible for the same reason: whose
   Fighter died, and on what HP, is information. */
.tok.ko{opacity:.8;filter:grayscale(1);transition:opacity .5s,filter .5s}
.tok.ko .nm{text-decoration:line-through}
/* While a Fighter is coming apart it must not already be greyed out -- the
   whole point is watching it happen. Three classes beats two, so this wins
   over .tok.ko until the sequence finishes. */
.tok.ko.dying{opacity:1;filter:none}
.tok.dying{animation:deathShake .55s}
@keyframes deathShake{0%{transform:translateX(0)}
  12%{transform:translateX(-4%) rotate(-1.5deg)}
  30%{transform:translateX(3.5%) rotate(1.2deg)}
  52%{transform:translateX(-2.5%) rotate(-.8deg)}
  74%{transform:translateX(1.5%)}100%{transform:translateX(0)}}
/* TRAIT BY TRAIT. Each layer falls out of the composite on its own, staggered
   from the top of the stack down, so a Fighter comes apart in the order it was
   assembled rather than simply fading. Two variants so pieces do not all spin
   the same way -- alternating by index reads as debris, one direction reads as
   a single object rotating.
   Ends at .22 rather than 0 and stays there (forwards): the fallen Fighter
   should be a scattered wreck you can still recognise, not an empty frame. */
.tok .art img.dis{animation:disA .62s cubic-bezier(.3,0,.7,1) forwards}
.tok .art img.dis.alt{animation-name:disB}
@keyframes disA{0%{transform:none;opacity:1}
  16%{transform:translateY(-4%) scale(1.04);opacity:1}
  100%{transform:translateY(28%) rotate(9deg) scale(.84);opacity:.22}}
@keyframes disB{0%{transform:none;opacity:1}
  16%{transform:translateY(-5%) scale(1.05);opacity:1}
  100%{transform:translateY(24%) rotate(-11deg) scale(.86);opacity:.22}}
.tok.hit{animation:hit .3s}
@keyframes hit{0%{transform:translateX(0)}30%{transform:translateX(-3%)}
  60%{transform:translateX(2.4%)}100%{transform:translateX(0)}}
.tok.act{animation:act .34s}
@keyframes act{0%{transform:scale(1)}40%{transform:scale(1.08)}100%{transform:scale(1)}}
@keyframes actSmall{0%{transform:scale(1)}40%{transform:scale(1.035)}100%{transform:scale(1)}}
/* z-index matters here and was missing. .flash is the token's FIRST child with
   no z-index, and .tok .art is position:relative and comes after it -- so with
   both at auto they paint in DOM order and the ART COVERED THE FLASH. Damage
   has been flashing behind the character all along, which is most of why a hit
   did not read. Above the art now, below the damage number. */
.flash{position:absolute;inset:0;opacity:0;pointer-events:none;border-radius:3px;z-index:4;
  background:var(--blood)}
.flash.on{animation:fl .30s}
@keyframes fl{0%{opacity:.50}100%{opacity:0}}
/* A crit is white and lingers -- it should look different from a normal hit,
   not merely bigger. */
.flash.big{background:#fff}
.flash.big.on{animation:flBig .40s}
@keyframes flBig{0%{opacity:.80}35%{opacity:.35}100%{opacity:0}}
/* Healing and shielding get their own colours, so Drain reads as taking
   something rather than only as damage. */
.flash.heal{background:var(--teal)}
.flash.shield{background:var(--shield)}
.tok.hit.big{animation:hitBig .40s}
@keyframes hitBig{0%{transform:translateX(0)}20%{transform:translateX(-5%)}
  45%{transform:translateX(4%)}70%{transform:translateX(-2%)}100%{transform:translateX(0)}}
.pop{position:absolute;left:50%;top:22%;transform:translateX(-50%);font-size:14px;font-weight:700;
  pointer-events:none;opacity:0;text-shadow:0 2px 6px #000;z-index:5;white-space:nowrap}
.pop.on{animation:pp .9s}.pop.heal{color:var(--teal)}.pop.big{font-size:19px;color:var(--ochre)}
@keyframes pp{0%{opacity:0;transform:translate(-50%,6px)}18%{opacity:1}100%{opacity:0;transform:translate(-50%,-26px)}}

/* In the side-column layout three stacked tokens would stand far taller than
   the board, so the art gets a fixed height there and letterboxes inside it --
   object-fit:contain already centres it. */
@media (min-width:1001px){
  /* THE WHOLE FIGHTER, not a bust crop. The crop showed the head and torso
     larger, but it cut the legs, the weapon and half of any companion off --
     and the art is the reason to care about a Fighter at all.
     210px is the size that fits: three tokens then stand about 890px against
     the board column's ~820, so the columns finish near level instead of
     running far past it. The square art letterboxes into the 270px width with
     small bars either side, over the checkerboard, which reads as a frame. */
  .teamcol .tok .art{aspect-ratio:auto;height:210px}
  .teamcol .tok .art img{object-fit:contain}
  /* Enemies face the player. The art is all drawn facing one way, so the right
     column mirrors and the two Crews look at each other across the board
     instead of everyone staring the same direction. Only in the side-column
     layout -- stacked on a phone they are above you, not opposite you, and a
     mirrored row there just looks like different art. */
  /* On the container, not the images. A transform on the img would be
     overwritten the moment a layer animates, flipping enemies back mid-swing.
     Mirroring the frame instead leaves every layer's own transform free. */
  .teamcol.foes .tok .art{transform:scaleX(-1)}
  .teamcol .tok{padding:8px}
  .teamcol .tok .nm{font-size:12px}
  .teamcol .tok .kitn{font-size:10px}
  .teamcol .tok .rk{font-size:9px}
  .teamcol .tok .hpn{font-size:9.5px;height:14px;line-height:14px}
  .teamcol .tok .hpwrap{height:8px}
  .teamcol{gap:10px}
}

/* ---- board ---- */
.boardwrap.foeturn{border-color:rgba(224,70,107,.55);box-shadow:0 0 0 1px rgba(224,70,107,.25)}
.boardwrap{position:relative;border:1px solid var(--line);border-radius:4px;
  transition:border-color .2s,box-shadow .2s;
  background:rgba(21,25,34,.80);
  padding:8px;overflow:hidden}
/* Was inset in the board at 13% opacity behind an opaque grid, which meant the
   terrain -- a real mechanic, drawn from a real trait -- was invisible. It now
   backs the entire arena, with a scrim so nothing over it loses contrast. */
.arenabg{position:absolute;inset:-14px;z-index:0;border-radius:6px;overflow:hidden;
  /* The negative inset is a deliberate bleed so the backdrop reaches past the
     columns, but it is also 14px of width the page does not have on a phone --
     see the mobile block, where it is pulled back to the arena's own edges. */
  background-size:cover;background-position:center;opacity:.30;
  filter:saturate(.75) contrast(.95);pointer-events:none}
.arenabg:after{content:'';position:absolute;inset:0;
  background:radial-gradient(120% 90% at 50% 40%,rgba(13,15,19,.35),rgba(13,15,19,.88))}
.arena > .teamwrap, .arena > .boardcol{position:relative;z-index:1}
.grid{position:relative;z-index:2;display:grid;gap:3px;touch-action:none}
.cell{position:relative;aspect-ratio:1;border-radius:4px;display:flex;align-items:center;
  justify-content:center;cursor:pointer;background:#10131a;border:1px solid transparent;
  transition:transform .12s,border-color .12s}
.cell:hover{border-color:var(--line)}
.cell.sel{border-color:var(--bone);transform:scale(.9)}
.cell .g{width:72%;height:72%;border-radius:50%;background:var(--gc);
  display:flex;align-items:center;justify-content:center;
  box-shadow:inset 0 -3px 6px rgba(0,0,0,.45), 0 0 0 1px rgba(255,255,255,.08);
  transition:transform .18s,opacity .18s}
.cell.sq .g{border-radius:4px}
.cell.di .g{border-radius:3px;transform:rotate(45deg) scale(.82)}
/* Flat shoulders, straight flanks, tapering to a point at the bottom -- the
   same silhouette as the emoji sitting on it. */
.cell.shield .g{border-radius:3px 3px 0 0;
  clip-path:polygon(0% 0%,100% 0%,100% 48%,86% 78%,50% 100%,14% 78%,0% 48%)}
/* the point steals height from the bottom, so the emoji rides a little high */
.cell.shield .g .em{transform:translateY(-8%)}
.cell.hex .g{clip-path:polygon(25% 5%,75% 5%,100% 50%,75% 95%,25% 95%,0 50%)}
.cell .em{font-size:clamp(11px,2.4vw,19px);line-height:1;filter:drop-shadow(0 1px 2px rgba(0,0,0,.6));
  pointer-events:none;
  /* A BOX, not a bare inline span. .cross sizes itself as a percentage, and a
     percentage of an inline span with no dimensions is zero -- the drawn cross
     was rendering at no size at all, which is why bombs went from hard to see
     to invisible. */
  display:flex;align-items:center;justify-content:center;width:100%;height:100%}
/* the rotated diamond must not rotate its emoji with it */
.cell.di .g .em{transform:rotate(-45deg)}
/* A bomb keeps its colour -- you detonate it by matching that colour -- but it
   has to be unmistakable on a busy board, so it pulses and wears a ring. */
.cell.bomb .g{box-shadow:inset 0 -3px 6px rgba(0,0,0,.45),0 0 0 2px var(--bone),0 0 12px var(--gc);
  animation:bmb 1.5s ease-in-out infinite}
.cell.bomb2 .g{box-shadow:inset 0 -3px 6px rgba(0,0,0,.45),0 0 0 2px var(--ochre),0 0 18px var(--ochre);
  animation:bmb .9s ease-in-out infinite}
@keyframes bmb{0%,100%{transform:scale(1)}50%{transform:scale(1.08)}}
/* The cross bomb's blast shape, drawn on the gem: a bar across and a bar down. */
.cross{position:relative;display:block;width:76%;height:76%}
.cross:before,.cross:after{content:'';position:absolute;background:#fff;border-radius:1px;
  box-shadow:0 0 4px rgba(0,0,0,.55)}
.cross:before{left:0;right:0;top:calc(50% - 2px);height:4px}
.cross:after{top:0;bottom:0;left:calc(50% - 2px);width:4px}
/* Detonation: everything the blast takes lights up before it goes. */
.cell.blast .g{animation:blastPop .42s ease-out}
.cell.blast2 .g{animation:blastPop2 .5s ease-out}
@keyframes blastPop{0%{transform:scale(1);filter:brightness(1)}
  35%{transform:scale(1.35);filter:brightness(3.2)}
  100%{transform:scale(.2);filter:brightness(1);opacity:0}}
@keyframes blastPop2{0%{transform:scale(1) rotate(0);filter:brightness(1)}
  30%{transform:scale(1.5) rotate(8deg);filter:brightness(4)}
  100%{transform:scale(.15) rotate(-6deg);opacity:0}}
.boardwrap.shake{animation:bshake .38s}
@keyframes bshake{0%,100%{transform:translate(0,0)}
  20%{transform:translate(-5px,3px)}45%{transform:translate(4px,-3px)}
  70%{transform:translate(-3px,-2px)}}
.cell.di.bomb .g{animation:none}
.cell.clear .g{transform:scale(0);opacity:0}
.cell.drop{animation:drp .22s}
.cell.settle{animation:stl .16s}
@keyframes stl{0%{transform:scale(1.06)}100%{transform:scale(1)}}
@keyframes drp{0%{transform:translateY(-16px);opacity:.4}100%{transform:translateY(0);opacity:1}}
/* Sits over the settled board rather than in a side panel, because the end of
   a battle should land where you were looking. Fades in only once the last
   cascade has come to rest. */
/* MUST COME FIRST AND MUST EXIST. `hidden` gets display:none from the browser's
   own stylesheet, which is the weakest source there is -- the .endcard rule
   below sets display:flex and silently beat it, so this overlay sat over the
   board from page load, covering every gem and swallowing every click. The page
   looked dead and a refresh could not help, because it was never alive.
   The attribute selector outranks the bare class, so it wins. */
.endcard[hidden]{display:none}
.endcard{position:absolute;inset:0;z-index:8;display:flex;flex-direction:column;
  align-items:center;justify-content:center;gap:9px;text-align:center;
  background:rgba(13,15,19,.86);backdrop-filter:blur(3px);animation:ecIn .45s ease-out}
@keyframes ecIn{0%{opacity:0}100%{opacity:1}}
.ec-title{font-size:30px;letter-spacing:.14em;text-transform:uppercase;font-weight:700}
.endcard.win .ec-title{color:var(--teal);text-shadow:0 0 26px rgba(0,200,160,.45)}
.endcard.lose .ec-title{color:var(--blood);text-shadow:0 0 26px rgba(224,70,107,.4)}
.ec-sub{font-size:12px;color:var(--bone);opacity:.85;max-width:74%}
.ec-stats{display:flex;gap:16px;flex-wrap:wrap;justify-content:center;
  font-size:10px;color:var(--dim);letter-spacing:.06em;text-transform:uppercase}
.ec-stats b{display:block;font-size:17px;color:var(--bone);letter-spacing:0;
  font-variant-numeric:tabular-nums}
.legend{display:flex;flex-wrap:wrap;gap:6px;margin-top:8px}
.lchip{display:inline-flex;align-items:center;gap:6px;font-size:11px;color:var(--bone);
  padding:4px 9px 4px 4px;border-radius:999px;background:#10131a;
  border:1px solid var(--line);border-left:3px solid var(--lc);white-space:nowrap;
  max-width:100%;overflow:hidden;text-overflow:ellipsis}
.lchip b{display:inline-flex;align-items:center;justify-content:center;width:21px;height:21px;
  border-radius:50%;background:var(--lc);font-size:12px;font-weight:400;
  box-shadow:inset 0 -2px 4px rgba(0,0,0,.45),0 0 0 1px rgba(255,255,255,.12)}
.lchip.gone{opacity:.32;text-decoration:line-through}
.reach{font-size:10px;color:var(--dim);margin-top:7px;display:flex;flex-wrap:wrap;gap:4px 14px;
  align-items:baseline}
.reach b{color:var(--ochre)}
.reach .terr{color:var(--teal);margin-left:auto}
.reach b{color:var(--ochre)}

/* ---- side ---- */
.panel{border:1px solid var(--line);border-radius:4px;background:var(--panel);padding:9px;margin-bottom:10px}
.panel h2{margin:0 0 6px;font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim)}
.logbox{border:1px solid var(--line);border-radius:4px;background:var(--panel);
  margin-top:10px;padding:8px 10px}
.logbox summary{font-size:9px;letter-spacing:.16em;text-transform:uppercase;color:var(--dim);
  cursor:pointer;list-style:none}
.logbox summary::-webkit-details-marker{display:none}
.logbox summary:before{content:'▸ ';}
.logbox[open] summary:before{content:'▾ ';}
#log{height:150px;overflow:auto;font-size:10px;line-height:1.55;margin-top:6px}
#log div{padding:1px 0;border-bottom:1px solid rgba(255,255,255,.04)}
.log-you{color:var(--teal)}.log-foe{color:var(--blood)}.log-sys{color:var(--dim)}
.log-big{color:var(--ochre)}
.combo{position:absolute;left:50%;top:38%;transform:translateX(-50%);z-index:9;
  font-size:26px;font-weight:700;color:var(--ochre);text-shadow:0 3px 14px #000;opacity:0;pointer-events:none}
.combo.on{animation:cb 1s}
@keyframes cb{0%{opacity:0;transform:translate(-50%,10px) scale(.8)}
  20%{opacity:1;transform:translate(-50%,0) scale(1.1)}
  70%{opacity:1}100%{opacity:0;transform:translate(-50%,-14px)}}
.over{text-align:center;padding:14px}.over h2{font-size:15px;color:var(--ochre);margin:0 0 4px}
</style>
</head>
<body>
<div class="wrap">
  <h1>DHC Arena <span style="color:var(--ochre)">prototype v2 — puzzle battler</span></h1>
  <p class="sub"><b>Drag a gem along its row or column, any distance</b> — the gems it passes shift back one.
     <b>There are always five gems.</b> Three of them are <b>ranks</b> — red front, amber mid, violet back —
     and matching one makes that rank's Fighter act, for whichever side matched it. The gem on the board
     shows <i>your</i> weapon in that rank; their token shows what the same gem does for <i>them</i>.
     The other two are 🛡️ Shield and ⚡ Charge. A <b>4-match leaves a ✛ bomb</b> (clears its row and
     column) and a <b>5-match leaves a 💣</b> (clears the board) — an <b>L, T or plus counts too</b>,
     since that is five gems as well — they sit there keeping their colour, and
     <b>either side can set one off</b> by matching it, so a bomb you leave lying around can be turned on you.
     Match size is reach:
     <b>3</b> hits their front, <b>4</b> reaches mid, <b>5+</b> reaches back. Match 4+ and you go again.
     A slide with no match costs nothing.</p>
  <div class="top">
    <button class="btn go" id="reroll">New battle</button>
    <button class="btn" id="howto" title="How to play">?</button>
    <button class="btn" id="mute" title="Mute sound">🔊</button>
    <span class="turnflag" id="flag">—</span>
    <span class="sub" style="margin:0" id="round"></span>
  </div>

  <!-- Wide: your Crew down the left, the board in the middle, theirs down the
       right, so both teams hug the board. Narrow: they stack, enemies on top.
       The legend sits under the board and the log under that -- it is a
       reference for a curious player, not something read mid-turn. -->
  <div class="arena">
    <!-- The defender's front-rank background IS the arena (see §3 terrain), so
         it belongs behind the whole fight rather than hidden behind the board. -->
    <div class="arenabg" id="terrain"></div>
    <div class="teamwrap mine"><div class="coltag you"><span>Your Crew</span>
        <span class="chg" id="chgMine"></span></div>
      <div class="teamcol mine" id="myTeam"></div></div>
    <div class="boardcol">
      <div class="boardwrap">
        <div class="combo" id="combo"></div>
        <div class="grid" id="grid"></div>
        <div class="endcard" id="endcard" hidden>
          <div class="ec-title" id="ecTitle"></div>
          <div class="ec-sub" id="ecSub"></div>
          <div class="ec-stats" id="ecStats"></div>
          <button class="btn go" id="ecAgain">Battle again</button>
        </div>
      </div>
      <div class="reach deskonly" id="reach"></div>
      <!-- Two slots, one shown per breakpoint. CSS order cannot move the legend
           past .teamwrap.mine because they have different parents, so the
           mobile copy lives outside the arena where it can sit last. -->
      <div class="legend deskonly" id="legend"></div>
    </div>
    <div class="teamwrap foes"><div class="coltag foe"><span>Enemy Crew</span>
        <span class="chg" id="chgFoes"></span></div>
      <div class="teamcol foes" id="foeTeam"></div></div>
  </div>
  <!-- On a phone the board is followed straight by your Crew; every line of
       reference text moves below both. -->
  <div class="reach mobonly" id="reachM"></div>
  <div class="legend mobonly" id="legendM"></div>
  <div class="panel" id="resultPanel" style="display:none;margin-top:10px"></div>
  <details class="logbox" open>
    <summary>Battle log</summary>
    <div id="log"></div>
  </details>
</div>
<script>
/* =====================================================================
   DHC ARENA — PROTOTYPE v2, PUZZLE BATTLER (JavaScript, throwaway)
   Ported to dhcarena-engine.php once the feel is settled.
   ===================================================================== */
var RARITY = <?php echo json_encode($dhc_rarity); ?>;
var ART    = <?php echo json_encode($ART); ?>;
var HG_EXCL= <?php echo json_encode($excl); ?>;

var N = 7;                    // board is N x N
var GEMS = 5;                 // 0,1,2 = your three Fighters. 3 = guard. 4 = surge.
/* Shape reinforces meaning where meaning is fixed. Gems 0-2 belong to whichever
   Fighters you brought, so their shapes are just distinguishers -- circle,
   square, diamond. Gem 3 is always Shield and gem 4 is always Charge, so those
   two get silhouettes that say so. */
var SHAPE = ['','sq','di','shield','hex'];

var TIER_MULT = {common:1.00, uncommon:1.08, epic:1.16, legendary:1.24, mythic:1.32};
function hash(s){var h=2166136261;for(var i=0;i<s.length;i++){h^=s.charCodeAt(i);h=Math.imul(h,16777619);}return h>>>0;}
function pick(a,n){return a[n%a.length];}
function cap(s){return s.charAt(0).toUpperCase()+s.slice(1);}
function slugsOf(c){return Object.keys(RARITY[c]||{});}
function tierOf(c,s){var r=(RARITY[c]||{})[s];return r?r[0]:'common';}
function artUrl(d,s,z){return ART+'/'+z+'/'+d+'/'+s+'.png';}

/* Weapon kits. What YOUR gem colour does when it matches -- which is the
   trait economy reaching into the puzzle. dhcarena.md §3. */
/* Every kit carries an emoji and a name that says what the match DOES, because
   colour alone made the board unreadable -- you cannot play a puzzle whose
   pieces need a legend lookup each turn. The emoji is the same on the gem, on
   the Fighter's token and in the legend, so the three are one glance apart.

   Names are plain verbs on purpose. "Heavy Swing" tells you nothing at a
   glance; "Smash" does. */
var KITS = [
  {id:'heavy',  emoji:'🔨', name:'Smash',  dmg:1.55, note:'one big hit'},
  {id:'cleave', emoji:'🪓', name:'Cleave', dmg:0.80, cleave:true, note:'hits whole rank'},
  /* A true drain, not a heal: healF() is fed the damage ACTUALLY dealt, so it
     takes health off the target and puts 45% of it back on the attacker --
     and hitting through a shield heals less, because less got through. The
     old note said "heals itself", which read like a medkit. */
  {id:'drain',  emoji:'🩸', name:'Drain',  dmg:1.05, drain:.45,   note:'hits/steals health'},
  {id:'sunder', emoji:'⛏️', name:'Break',  dmg:0.95, sunder:true, note:'smashes shields'},
  /* PLAIN WORDS. "crits often" and "chips everyone" are both gamer shorthand,
     and a legend exists so nobody has to already know the vocabulary. A crit
     here is a 1.6x hit and Snipe adds 28 points of chance to it; Volley's 0.62
     multiplier against every living enemy is a weak hit to all of them. Say
     that instead. */
  {id:'precise',emoji:'🎯', name:'Snipe',  dmg:1.15, crit:.28,    note:'often hits harder'},
  {id:'volley', emoji:'🏹', name:'Volley', dmg:0.62, all:true,    note:'weak hit to all'},
  {id:'brutal', emoji:'🗡️', name:'Bleed',  dmg:1.30, bleed:true,  note:'hurts for 3 rounds'},
  {id:'quick',  emoji:'⚔️', name:'Double', dmg:0.85, echo:true,   note:'strikes twice'}
];
/* The two shared gems. Named for the effect, not the mechanic. */
var SHARED = {
  3:{emoji:'🛡️', name:'Shield', note:'shields team'},
  4:{emoji:'⚡', name:'Charge',  note:'erupts at 10'}
};
/** What a gem shows and means, for a given side. */
function gemInfo(side,g){
  if(SHARED[g]) return SHARED[g];
  var f=fighterForGem(side,g);
  return f ? {emoji:f.kit.emoji, name:f.kit.name, note:f.kit.note, fighter:f} : {emoji:'·',name:'—',note:''};
}
/* These still said "guard gems" and "surge gems" -- the names those two were
   called before they became Shield and Charge, so the terrain was describing
   pieces that are not on the board any more. Plain words here too. */
var TERRAIN = [
  {id:'crit',  name:'Fractured Signal', note:'everyone lands big hits more often'},
  {id:'dmg',   name:'Overclocked',      note:'everyone deals 10% more damage'},
  {id:'guard', name:'Dense Cover',      note:'🛡️ Shield gives 50% more'},
  {id:'surge', name:'Low Gravity',      note:'⚡ Charge builds twice as fast'},
  {id:'frail', name:'Corrosive Haze',   note:'everyone has 8% less health'}
];

function buildFighter(t,name){
  var m=function(c,s){return TIER_MULT[tierOf(c,s)]||1;};
  var v=function(s,sp){return 1+((hash(s)%1000)/1000-.5)*sp;};
  var V=.6;   // §4: variance between traits must be wider than the gap between tiers
  /* 150 gave 8.4-move battles with one in eight ending in three moves or less;
     380 gave 18.3 and no blowouts at all. 320 sits between, and the harness
     player plays optimally where a human will not, so live battles run longer
     than the number here. */
  var hp=Math.round(320*m('torso',t.torso)*v(t.torso,V));
  return {uid:'f'+(++UID), name:name, traits:t,
    kit:pick(KITS,hash(t.weapon)),
    maxHp:hp, hp:hp, shield:0, bleed:0,
    /* Tuned by simulating 140 full battles per setting rather than guessed.
       At power 15 a battle ran 17.6 of your moves -- a real tug of war but
       long enough to risk the tedium the v1 playtest already found. At 25 it
       collapsed to 9.6, too quick for the lead to change hands. 20 lands
       around 13, which is long enough to swing and short enough to stay
       sharp. Revisit against a 6-battle daily allowance. */
    power:Math.round(20*m('weapon',t.weapon)*v(t.weapon,V)),
    critC:0.06*m('headgear',t.headgear)*v(t.headgear,V),
    surge:0, rank:0, side:null, ko:false};
}
var UID=0;
function mulberry(s){return function(){s|=0;s=s+0x6D2B79F5|0;var t=Math.imul(s^s>>>15,1|s);
  t=t+Math.imul(t^t>>>7,61|t)^t;return((t^t>>>14)>>>0)/4294967296;};}
function randomTraits(rnd){
  var t={};['torso','head','headgear','arms','weapon','background','companion','effects'].forEach(function(c){
    var l=slugsOf(c);t[c]=l[Math.floor(rnd()*l.length)];});
  var b=HG_EXCL[t.head]||[],g=0;
  while(b.indexOf(t.headgear)!==-1&&g++<40){var h=slugsOf('headgear');t.headgear=h[Math.floor(rnd()*h.length)];}
  return t;
}
var FIRST=['Bone','Ash','Grim','Null','Vex','Rust','Pale','Iron','Hex','Dread','Cinder','Wraith'];
var LAST=['Harvester','Revenant','Conductor','Sentinel','Warden','Prowler','Herald','Butcher','Cipher','Widow'];
/* Distinct first names per battle: the log and the legend both refer to a
   Fighter by its first name, and a battle with two Ashes in it is unreadable. */
function fname(rnd,used){
  var g=0,n;
  do{ n=FIRST[Math.floor(rnd()*FIRST.length)]; }while(used&&used[n]&&g++<60);
  if(used)used[n]=1;
  return n+' '+LAST[Math.floor(rnd()*LAST.length)];
}

/* ---------------- sound ----------------
   All of it from the two match-3 games on this platform, Skull Swap and
   Monstrocity (/staking/sounds, every file verified 200 before it was
   referenced). Players already know what a match sounds like here, and a board
   that sounds like the ones they play needs no learning at all. */
/* [file, volume] -- no caps, played whole, the way the source games play them.
   An earlier pass trimmed every sample with a fade, which was solving the wrong
   problem. Skull Swap's playSound() is three lines:

       sound.currentTime = 0;
       sound.play();

   ONE shared Audio per sound, restarted on each trigger, so a sample can never
   overlap ITSELF -- a rapid second match cuts the first short naturally and the
   2.1s shatter never piles up. This version was cloning on every trigger, which
   stacked copies and turned a cascade to mush; the caps were papering over a
   playback model the original games never had. */
var SFX = {
  pick:   ['sounds/select.ogg',                 .40],
  bad:    ['sounds/badmove.ogg',                .45],
  clear:  ['sounds/gem_shatters.ogg',           .42],
  land:   ['sounds/hyperspace_gem_land_1.ogg',  .22],
  chain:  ['sounds/speedmatch1.ogg',            .55],
  great:  ['sounds/voice_excellent.ogg',        .60],
  shield: ['sounds/hyperspace_gem_land_2.ogg',  .45],
  erupt:  ['sounds/badgeawarded.ogg',           .60],
  ko:     ['sounds/skullcoinlose.ogg',          .55],
  armX:   ['sounds/powergem_created.ogg',       .70],
  armB:   ['sounds/hypercube_create.ogg',       .80],
  boom:   ['sounds/bomb_explode.ogg',           .75],
  start:  ['sounds/voice_go.ogg',               .55],
  win:    ['sounds/voice_levelcomplete.ogg',    .75],
  lose:   ['sounds/voice_gameover.ogg',         .75]
};
var sfxOn=true;
try{ sfxOn = localStorage.getItem('dhcarena_sfx') !== '0'; }catch(e){}

/* WEB AUDIO, NOT <audio> ELEMENTS.
 *
 * Two reports, one cause: sounds cutting each other off, and tile animation
 * going slow and laggy on mobile whenever sound is on.
 *
 * An HTMLAudioElement is a heavyweight object. Every play() does work on the
 * main thread -- the same thread running the board animation -- and mobile
 * browsers cap how many can sound at once, so a new one starting can stop one
 * already playing. Both symptoms fall straight out of that, and no amount of
 * throttling or trimming fixes either, because the cost is in the mechanism.
 *
 * Web Audio decodes each file ONCE into memory at startup. Playing is then a
 * BufferSourceNode -- a throwaway object with no decode, no fetch and no main
 * thread work -- and any number can sound together and mix properly instead of
 * competing. It is the difference between opening a file and reading from RAM.
 *
 * Falls back to the old element-based path if AudioContext is missing or a
 * file will not decode (Safari and Ogg have a history), so sound degrades
 * rather than disappearing. */
var actx=null, sfxBuf={}, sfxEl={}, sfxVoices=0;
var SFX_MAX_VOICES=10;        // a ceiling, never reached in normal play

function sfxInit(){
  if(actx) return;
  var AC=window.AudioContext||window.webkitAudioContext;
  if(!AC) return;                       // no Web Audio: fallback path handles it
  try{ actx=new AC(); }catch(e){ return; }
  // Decode everything in the background, once. ~600KB total, after the first
  // gesture, so it never sits in front of the first frame.
  Object.keys(SFX).forEach(function(k){
    try{
      fetch(SFX[k][0]).then(function(r){ return r.arrayBuffer(); })
        .then(function(b){
          return new Promise(function(res,rej){ actx.decodeAudioData(b,res,rej); });
        })
        .then(function(buf){ sfxBuf[k]=buf; })
        .catch(function(){});           // leaves this one on the fallback
    }catch(e){}
  });
}

function sfx(name){
  if(!sfxOn) return;
  var def=SFX[name]; if(!def) return;

  if(actx && sfxBuf[name]){
    if(sfxVoices>=SFX_MAX_VOICES) return;
    try{
      if(actx.state==='suspended') actx.resume();
      var src=actx.createBufferSource(); src.buffer=sfxBuf[name];
      var g=actx.createGain(); g.gain.value=def[1];
      src.connect(g); g.connect(actx.destination);
      sfxVoices++;
      src.onended=function(){ sfxVoices--; };
      src.start(0);
      return;
    }catch(e){ sfxVoices=Math.max(0,sfxVoices-1); }
  }

  /* Fallback: one element per sound, restarted -- skullswap.php's model. */
  try{
    var a=sfxEl[name];
    if(!a){ a=sfxEl[name]=new Audio(def[0]); a.volume=def[1]; }
    a.currentTime=0;
    var pr=a.play();
    if(pr && pr.catch) pr.catch(function(){});
  }catch(e){}
}

/* ---------------- state ---------------- */
var S=null, sel=null, busy=false;

function newBattle(){
  var rnd=mulberry(Date.now()&0x7fffffff);
  /* Your three get DISTINCT kits, so no two of your gems wear the same emoji.
     An ambiguous icon is worse than no icon -- the whole point is that a glance
     resolves it. Their side may repeat; their gems are not on your board. */
  var mine=[], usedKits={}, usedNames={}, guard=0;
  while(mine.length<3 && guard++<300){
    var f=buildFighter(randomTraits(rnd),fname(rnd,usedNames));
    if(usedKits[f.kit.id]) continue;
    usedKits[f.kit.id]=1; mine.push(f);
  }
  while(mine.length<3) mine.push(buildFighter(randomTraits(rnd),fname(rnd,usedNames)));
  var foeNames={};
  var foes=[0,1,2].map(function(){return buildFighter(randomTraits(rnd),fname(rnd,foeNames));});
  mine.forEach(function(f,i){f.rank=i;f.side='mine';});
  foes.forEach(function(f,i){f.rank=i;f.side='foes';});
  S={mine:mine,foes:foes,board:[],bomb:null,turn:'mine',round:1,over:null,
     settling:false, stats:{bombs:0,blasts:0,best:1},
     terrain:pick(TERRAIN,hash(foes[0].traits.background)),terrainBg:foes[0].traits.background};
  if(S.terrain.id==='frail') S.mine.concat(S.foes).forEach(function(f){f.maxHp=Math.round(f.maxHp*.92);f.hp=f.maxHp;});
  makeBoard();
  document.getElementById('log').innerHTML='';
  document.getElementById('resultPanel').style.display='none';
  document.getElementById('endcard').hidden=true;
  buildTeams();          // once; renderTeams() only patches from here on
  logLine('sys','Terrain — '+S.terrain.name+': '+S.terrain.note+'. Set by their front rank.');
  renderAll();
}
function team(s){return s==='mine'?S.mine:S.foes;}
/** Both sides, for the passes that do not care whose Fighter it is. */
function everyone(){ return S.mine.concat(S.foes); }
function alive(s){return team(s).filter(function(f){return !f.ko;});}
function fighterForGem(side,g){ return team(side).filter(function(f){return f.rank===g;})[0]; }

/* ---------------- board ---------------- */
/* BOMBS -- same rules as Skull Swap, which is the reference players have.
   A 4-match leaves a ✛ that wipes its row AND column. A 5-or-more leaves a 💣
   that wipes the whole board. Both sit on the board as ordinary gems keeping
   their colour, and you set one off by matching that colour. Explosions that
   catch another bomb chain-detonate it.

   THE ARENA TWIST, and the reason this is worth having: whoever DETONATES a
   bomb gets its payload. A bomb you built and did not spend is a loaded gun
   lying on a shared table -- leave it and they can match its colour and turn it
   on you. It is the first mechanic here where doing something good is also
   taking on risk.

   Stored as a parallel layer rather than extra gem values, so matching, the
   slide and the AI's search all keep working on plain colours. */
var BOMB_CROSS=1, BOMB_BOARD=2;
var BLAST_CAP=4, BLAST_SCALE=0.45;   // see the blast block in cascade()
/* Pacing knobs, all found by simulation rather than taste -- see the tuning
   note above buildFighter(). Charge in particular was gaining +len per match,
   so it reached ten in about three matches and a single move could set off
   three eruptions. It is meant to be the thing you build toward across a
   battle, not a side effect of playing one. */
var SURGE_GAIN=1;        // per Charge match, flat -- NOT the match length
var EXTRA_TURN_MIN=5;    // a 4 still leaves a bomb; that is reward enough
function makeBoard(){
  do{
    S.board=[];
    for(var i=0;i<N*N;i++) S.board.push(Math.floor(Math.random()*GEMS));
  } while(findMatches().length || !hasMove());
  /* Only seed the bomb layer if there isn't one. makeBoard() also runs on a
     mid-battle reshuffle, and zeroing here wiped every live bomb without a
     word -- you made one, the board reshuffled, and it was simply gone. A bomb
     keeps its cell and takes whatever colour lands there. */
  if(!S.bomb){ S.bomb=[]; for(var k=0;k<N*N;k++) S.bomb.push(0); }
}
function rowColCells(i){
  var r=Math.floor(i/N), c=i%N, out=[], k;
  for(k=0;k<N;k++){ out.push(idx(r,k)); if(k!==r) out.push(idx(k,c)); }
  return out;
}
function allCells(){ var o=[],k; for(k=0;k<N*N;k++) o.push(k); return o; }
function idx(r,c){return r*N+c;}
function inb(r,c){return r>=0&&r<N&&c>=0&&c<N;}
function findMatches(b){
  b=b||S.board; var raw=[],r,c,i;
  for(r=0;r<N;r++){ c=0; while(c<N){ var run=1;
      while(c+run<N && b[idx(r,c+run)]===b[idx(r,c)] && b[idx(r,c)]!==-1) run++;
      if(run>=3){var g=[];for(i=0;i<run;i++)g.push(idx(r,c+i));raw.push({cells:g,type:b[idx(r,c)]});}
      c+=run; } }
  for(c=0;c<N;c++){ r=0; while(r<N){ var run2=1;
      while(r+run2<N && b[idx(r+run2,c)]===b[idx(r,c)] && b[idx(r,c)]!==-1) run2++;
      if(run2>=3){var g2=[];for(i=0;i<run2;i++)g2.push(idx(r+i,c));raw.push({cells:g2,type:b[idx(r,c)]});}
      r+=run2; } }

  /* AN L, T OR PLUS IS ONE MATCH, NOT TWO.
     The scan above walks rows and columns separately, so a cross of five gems
     came back as two runs of three -- neither of them four, so it left no bomb
     and paid two small hits instead of one big one. Five gems is five gems
     whatever shape you made it in.
     Runs that share a cell are flooded together here; sharing a cell means
     sharing a colour, so no type check is needed beyond the obvious one. */
  var used=[], out=[], j;
  for(i=0;i<raw.length;i++) used.push(false);
  for(i=0;i<raw.length;i++){
    if(used[i]) continue;
    used[i]=true;
    var cells={}, type=raw[i].type, stack=[raw[i]], grew=true;
    while(stack.length){
      var g3=stack.pop();
      for(j=0;j<g3.cells.length;j++) cells[g3.cells[j]]=1;
      for(j=0;j<raw.length;j++){
        if(used[j] || raw[j].type!==type) continue;
        var touches=false;
        for(var k=0;k<raw[j].cells.length;k++) if(cells[raw[j].cells[k]]){ touches=true; break; }
        if(touches){ used[j]=true; stack.push(raw[j]); }
      }
    }
    var list=Object.keys(cells).map(Number);
    out.push({cells:list, type:type, len:list.length});
  }
  return out;
}
/* THE MOVE IS A SLIDE, NOT A SWAP.
   Matched to Monstrocity and Skull Swap, which is the feel players already
   have: pick a gem and drag it any distance along its row or column, and every
   gem between shifts one step back toward where it came from. Read out of
   monstrocity.php slideTiles() rather than approximated -- same rotation, same
   free revert when a move produces no match.

   It also makes the board far richer than adjacent swapping. On a 7x7 every
   gem has twelve destinations instead of four neighbours, so a move is a real
   search rather than a scan, and reaching for a 5-match is often possible
   somewhere if you can see it. */
function slid(b,a,z){
  if(a===z) return null;
  var ra=Math.floor(a/N), ca=a%N, rz=Math.floor(z/N), cz=z%N, x, y;
  if(ra!==rz && ca!==cz) return null;      // must share a row or a column
  var n=b.slice(), t=b[a];
  if(ra===rz){
    if(ca<cz){ for(x=ca;x<cz;x++) n[idx(ra,x)]=b[idx(ra,x+1)]; }
    else     { for(x=ca;x>cz;x--) n[idx(ra,x)]=b[idx(ra,x-1)]; }
    n[idx(ra,cz)]=t;
  }else{
    if(ra<rz){ for(y=ra;y<rz;y++) n[idx(y,ca)]=b[idx(y+1,ca)]; }
    else     { for(y=ra;y>rz;y--) n[idx(y,ca)]=b[idx(y-1,ca)]; }
    n[idx(rz,ca)]=t;
  }
  return n;
}
/** The same rotation applied to the bomb layer, so a bomb travels with its gem. */
function slideBombs(a,z){
  var ra=Math.floor(a/N), ca=a%N, rz=Math.floor(z/N), cz=z%N, x, y;
  var b=S.bomb.slice(), t=b[a];
  if(ra===rz){
    if(ca<cz){ for(x=ca;x<cz;x++) S.bomb[idx(ra,x)]=b[idx(ra,x+1)]; }
    else     { for(x=ca;x>cz;x--) S.bomb[idx(ra,x)]=b[idx(ra,x-1)]; }
    S.bomb[idx(ra,cz)]=t;
  }else{
    if(ra<rz){ for(y=ra;y<rz;y++) S.bomb[idx(y,ca)]=b[idx(y+1,ca)]; }
    else     { for(y=ra;y>rz;y--) S.bomb[idx(y,ca)]=b[idx(y-1,ca)]; }
    S.bomb[idx(rz,ca)]=t;
  }
}
/** Every legal slide from every cell. The move space the AI and hasMove share. */
function eachSlide(fn){
  for(var r=0;r<N;r++) for(var c=0;c<N;c++){
    var a=idx(r,c), k;
    for(k=0;k<N;k++){ if(k!==c) fn(a, idx(r,k)); }
    for(k=0;k<N;k++){ if(k!==r) fn(a, idx(k,c)); }
  }
}
function hasMove(){
  var found=false;
  eachSlide(function(a,z){
    if(found) return;
    var nb=slid(S.board,a,z);
    if(nb && findMatches(nb).length) found=true;
  });
  return found;
}
function collapse(){
  // The bomb layer falls with its gem. Collapsing the colours alone would leave
  // bombs hanging in mid-air attached to whatever dropped into their cell.
  for(var c=0;c<N;c++){
    var col=[], bmb=[];
    for(var r=N-1;r>=0;r--){
      var v=S.board[idx(r,c)];
      if(v!==-1){ col.push(v); bmb.push(S.bomb[idx(r,c)]); }
    }
    for(var r2=N-1,k=0;r2>=0;r2--,k++){
      var at=idx(r2,c);
      S.board[at] = k<col.length ? col[k] : Math.floor(Math.random()*GEMS);
      S.bomb[at]  = k<col.length ? bmb[k] : 0;
    }
  }
}

/* ---------------- combat ---------------- */
function reachFor(len){ return len>=5?2:len===4?1:0; }
function targetsAt(side,depth){
  var foes=alive(side); if(!foes.length) return [];
  var byRank=foes.slice().sort(function(a,b){return a.rank-b.rank;});
  // reach is a ceiling: you can always hit anything shallower
  var elig=byRank.filter(function(f){return f.rank<=depth;});
  return elig.length?elig:[byRank[0]];
}
function hurt(t,amt,tag){
  if(t.shield>0){var a=Math.min(t.shield,amt);t.shield-=a;amt-=a;if(a>0)pop(t,'-'+a+' shield','heal');}
  if(amt<=0)return 0;
  t.hp=Math.max(0,t.hp-amt); pop(t,'-'+amt,tag); shake(t,tag==='big');
  layerAnim(t,['head','headgear'],'jolt');   // head and helmet together, or the
                                             // skull slides out from under it
  if(t.hp===0){
    t.ko=true; sfx('ko'); killAnim(t);
    logLine(t.side==='mine'?'foe':'you','☠ '+t.name+' falls.');
  }
  return amt;
}
function healF(f,a){var b=f.hp;f.hp=Math.min(f.maxHp,f.hp+a);
  if(f.hp>b){ pop(f,'+'+(f.hp-b),'heal'); flashTok(f,'heal'); }}

function resolveGroup(side,grp,chain,scale){
  var foeSide=side==='mine'?'foes':'mine';
  var mult=(1+(chain-1)*0.35)*(scale===undefined?1:scale);                        // cascades hit harder
  if(S.terrain.id==='dmg')mult*=1.10;
  if(grp.type===3){                                  // GUARD — shield your team
    var amt=Math.round((6+grp.len*4)*mult*(S.terrain.id==='guard'?1.5:1));
    alive(side).forEach(function(f){ f.shield+=amt; flashTok(f,'shield'); });
    sfx('shield');
    logLine(side==='mine'?'you':'foe','🛡️ Shield x'+grp.len+' — +'+amt+' to the whole team.');
    return;
  }
  if(grp.type===4){                                  // SURGE — charge, then erupt
    var add=SURGE_GAIN*(S.terrain.id==='surge'?2:1);
    var living=alive(side);
    if(!living.length) return;          // whole team down mid-cascade
    living.forEach(function(f){f.surge=Math.min(10,f.surge+add);});
    logLine(side==='mine'?'you':'foe','⚡ Charge x'+grp.len+' — now '+living[0].surge+'/10.');
    alive(side).forEach(function(f){
      if(f.surge>=10){ f.surge=0;
        var ts=alive(foeSide);
        ts.forEach(function(t){hurt(t,Math.round(f.power*0.9*mult),'big');});
        act(f); pop(f,'SURGE!','big'); sfx('erupt');
        logLine(side==='mine'?'you':'foe','⚡ '+f.name+' ERUPTS — hits everything.');
      }});
    return;
  }
  var f=fighterForGem(side,grp.type);
  if(!f||f.ko){ logLine('sys','Matched '+gemInfo(side,grp.type).emoji+' — that Fighter is down, the match is wasted.'); return; }
  var depth=reachFor(grp.len);
  var ts=targetsAt(foeSide,depth);
  if(!ts.length)return;
  act(f);
  var k=f.kit;
  var hitList = k.all ? alive(foeSide) : (k.cleave ? ts : [ts[ts.length-1]]);
  var times = k.echo?2:1;
  for(var n=0;n<times;n++){
    hitList.forEach(function(t){
      if(t.ko)return;
      var crit=Math.random()<(f.critC+(k.crit||0)+(S.terrain.id==='crit'?.12:0));
      var base=f.power*k.dmg*mult*(crit?1.6:1)*(1+(grp.len-3)*0.30);
      if(k.sunder&&t.shield>0){t.shield=Math.max(0,t.shield-Math.round(base*0.5));}
      var dealt=hurt(t,Math.max(1,Math.round(base)),crit?'big':'');
      if(k.drain)healF(f,Math.round(dealt*k.drain));
      if(k.bleed&&!t.ko)t.bleed=3;
    });
  }
  logLine(side==='mine'?'you':'foe',
    f.name+' — '+k.name+' x'+grp.len+(chain>1?' (chain '+chain+')':'')+
    ' → '+['front','mid','back'][depth]+' rank');
}
function tickBleeds(side){
  alive(side).forEach(function(f){
    if(f.bleed>0){f.bleed--;var d=Math.round(f.maxHp*0.04);hurt(f,d,'');
      logLine('sys',f.name+' bleeds for '+d+'.');}});
}

/* ---------------- turn resolution ---------------- */
function applySlide(a,z,side,done){
  busy=true;
  var nb=slid(S.board,a,z);
  if(!nb){ busy=false; return; }
  slideBombs(a,z);          // bombs travel with their gems
  S.board=nb;
  S.lastTo=z;               // a new bomb forms under the gem you actually moved
  renderBoard(false,true);
  setTimeout(function(){ cascade(side,1,done); },150);
}
function cascade(side,chain,done){
  var ms=findMatches();
  if(!ms.length){
    if(S.settling){
      // board is at rest and nothing is owed -- set off whatever is left, then
      // show the result. busy stays true until the fireworks finish.
      renderBoard(); renderTeams();
      finalHurrah(function(){ busy=false; showEnd(); });
      return;
    }
    // the move is over: everything armed during it goes live for both sides
    var armed=0;
    for(var q=0;q<S.bomb.length;q++) if(S.bomb[q]<0){ S.bomb[q]=-S.bomb[q]; armed++; }
    if(armed) renderBoard();
    if(!hasMove()){ makeBoard(); logLine('sys','No moves left — board reshuffled.'); renderBoard(); }
    busy=false; done&&done(chain-1); return;
  }
  if(chain>S.stats.best) S.stats.best=chain;
  sfx('clear');
  if(chain>1){ sfx('chain'); var c=document.getElementById('combo'); c.textContent='CHAIN x'+chain;
    c.classList.remove('on'); void c.offsetWidth; c.classList.add('on'); }
  var cleared={};
  ms.forEach(function(g){ g.cells.forEach(function(i){cleared[i]=1;}); });

  /* DETONATE anything caught in the clear, chaining through bombs the blast
     reaches. Queue rather than recursion so a chain cannot blow the stack. */
  var extra={}, boom=0, boomKind=0, blasts=[];
  var queue=Object.keys(cleared).filter(function(i){return S.bomb[i]>0;});   // >0: live, not armed-this-move
  while(queue.length){
    var at=+queue.shift(), kind=S.bomb[at];
    if(!kind) continue;
    var colour=S.board[at];
    S.bomb[at]=0; boom++; boomKind=Math.max(boomKind,kind); S.stats.blasts++;
    var hit=(kind===BOMB_BOARD?allCells():rowColCells(at));
    var own=0;
    hit.forEach(function(j){
      if(S.bomb[j]>0) queue.push(j);
      if(S.board[j]===colour) own++;
      if(!cleared[j]){ cleared[j]=1; extra[j]=1; }
    });
    blasts.push({colour:colour,kind:kind,own:own,size:hit.length});
  }

  /* Guarded because a throw in here is unrecoverable, not cosmetic: the
     exception escapes cascade(), so busy stays true and done() is never called,
     and the board sits disabled with the turn never handed back. That is
     exactly what a dangling GEMNAME reference did -- and only when a gem
     belonging to a knocked-out Fighter was matched, so it survived every test
     until a Fighter actually went down mid-battle. One bad group should cost
     one group's effect, never the game. */
  ms.forEach(function(g){
    if(S.settling) return;          // decided already; this is animation now
    try { resolveGroup(side,g,chain); }
    catch(err){ logLine('sys','(effect error — skipped)');
                if(window.console) console.error('resolveGroup', err); }
  });

  /* Blast payload. Everything the explosion took, grouped by colour and paid to
     WHOEVER SET IT OFF -- which is the whole risk. Length is capped at 6 so a
     board bomb is a huge turn rather than an instant win: 49 gems resolving at
     full scale would simply end the battle on the spot. */
  if(boom && S.settling){ boom=0; blasts=[]; }   // blasts stop paying once decided
  if(boom){
    /* A BOMB PAYS ITS OWN COLOUR, NOT ALL FIVE.
       The first version resolved every colour the blast touched, so one board
       bomb was five big matches at once -- battles averaged four moves and some
       ended on the first. Scaling the damage down barely helped, because the
       problem was the breadth, not the size.
       Paying only the bomb's own colour makes it that RANK's big strike: the
       Fighter whose gem you blew up swings hard, reaching their back rank
       because the blast counts as a huge match. Same drama, one axis to tune,
       and it reads far better in the log. */
    logLine(side==='mine'?'you':'foe',
      (boomKind===BOMB_BOARD?'💣 BOARD BOMB':'✛ BOMB')+(boom>1?' ×'+boom+' (chain)':'')
      + ' — ' + Object.keys(extra).length + ' gems caught by '
      + (side==='mine'?'you':'them') + '.');
    blasts.forEach(function(bl){
      try { resolveGroup(side,
              {type:bl.colour,
               len:Math.min(BLAST_CAP,Math.max(3,bl.own)),
               cells:[]},
              chain, bl.kind===BOMB_BOARD?BLAST_SCALE*1.6:BLAST_SCALE); }
      catch(err){ if(window.console) console.error('blast', err); }
    });
    var cb=document.getElementById('combo');
    cb.textContent = boomKind===BOMB_BOARD?'💣 BOARD BOMB':'✛ BOMB';
    cb.classList.remove('on'); void cb.offsetWidth; cb.classList.add('on');
  }

  /* CREATE from this turn's own matches. The bomb cell survives the clear --
     that is what leaves it sitting on the board for either side to take. */
  ms.forEach(function(g){
    if(g.len<4 || S.settling) return;
    if(g.len>=5) sfx('great');
    var at=(S.lastTo!==undefined && g.cells.indexOf(S.lastTo)!==-1)
             ? S.lastTo : g.cells[Math.floor(g.cells.length/2)];
    var big=(g.len>=5);
    /* ARMED, BUT NOT LIVE UNTIL THE MOVE ENDS. Stored negative so this cascade
       cannot set it off.
       Without this a bomb was routinely created and detonated inside the same
       move: the cascade collapses and refills immediately, a fresh match forms
       over the new bomb, and it goes off before anybody saw it. That is why
       bombs appeared never to be created while explosions kept happening --
       they were being made and spent in the same breath, and the risk of
       leaving one lying around for the opponent never existed.
       Negative survives collapse() untouched, which index-tracking would not:
       gems fall, so a cell number means nothing a moment later. */
    S.bomb[at]=-(big?BOMB_BOARD:BOMB_CROSS);
    S.stats.bombs++;
    delete cleared[at];
    sfx(big?'armB':'armX');
    logLine(side==='mine'?'you':'foe',
      (big?'💣 BOARD BOMB':'✛ Bomb')+' armed — match its colour to set it off. Either side can.');
    /* Announced on the board, not just in the log. A bomb being CREATED looks
       like nothing happening -- the match resolves as usual and one gem quietly
       changes -- so it says so. */
    var cb=document.getElementById('combo');
    if(cb){ cb.textContent=(big?'💣 BOARD BOMB ARMED':'✛ BOMB ARMED');
      cb.classList.remove('on'); void cb.offsetWidth; cb.classList.add('on'); }
  });
  Object.keys(cleared).forEach(function(i){
    var el=cellEl(i); if(!el) return;
    // cells taken by a blast get the explosion, the rest just clear
    el.classList.add(extra[i] ? (boomKind===BOMB_BOARD?'blast2':'blast') : 'clear');
  });
  if(boom){
    sfx('boom');
    var bw=document.querySelector('.boardwrap');
    if(bw){ bw.classList.remove('shake'); void bw.offsetWidth; bw.classList.add('shake'); }
  }
  renderTeams();
  /* THE BOARD FINISHES FALLING EVEN AFTER THE BATTLE IS DECIDED.
     Returning here left the last clear half-applied -- gems scaled to nothing,
     holes never collapsed, the board frozen mid-explosion looking broken. The
     cascade now keeps running purely as animation: S.settling stops every
     effect, so nothing further is resolved against a team that has already
     lost, but the gems fall, the chains play out and the board comes to rest
     before the result is shown. */
  if(checkOver() && !S.settling){
    S.settling=true;
    logLine('big', S.over==='win' ? '— their Crew is down —' : '— your Crew is down —');
  }
  // hold longer when something exploded, so the blast is seen rather than
  // skipped past on the way to the collapse
  var hold = boom ? 460 : 190;
  setTimeout(function(){
    Object.keys(cleared).forEach(function(i){S.board[i]=-1;});
    collapse(); renderBoard(true); sfx('land');
    setTimeout(function(){ cascade(side,chain+1,done); },170);
  },hold);
}
function endTurn(best){
  if(S.over)return;
  var extra = best>=1 && S.lastLen>=EXTRA_TURN_MIN;
  if(extra){ logLine('big','Match of '+S.lastLen+' — you go again.'); renderAll(); return; }
  tickBleeds(S.turn==='mine'?'foes':'mine');
  if(checkOver())return;
  S.turn = S.turn==='mine'?'foes':'mine';
  if(S.turn==='mine')S.round++;
  renderAll();
  if(S.turn==='foes') setTimeout(aiMove,300);
}
function checkOver(){
  if(!alive('foes').length){S.over='win';renderAll();return true;}
  if(!alive('mine').length){S.over='lose';renderAll();return true;}
  return false;
}

/* ---------------- the AI ----------------
   Plays the same board by the same rules. dhcarena.md §3b: if a simple
   priority AI cannot play an ability well, the ability is not finished. */
function scoreMove(a,b,side){
  var nb=slid(S.board,a,b); if(!nb) return -1;
  var ms=findMatches(nb);
  if(!ms.length)return -1;
  var sc=0;
  ms.forEach(function(g){
    var len=g.len;
    if(g.type===3) sc += len*4;
    else if(g.type===4) sc += len*5;
    else {
      var f=fighterForGem(side,g.type);
      if(!f||f.ko){ sc -= 2; return; }
      sc += len*10 + f.power*0.4;
      var depth=reachFor(len);
      var ts=targetsAt(side==='mine'?'foes':'mine',depth);
      // finishing something is worth a lot
      ts.forEach(function(t){ if(f.power*f.kit.dmg*(1+(len-3)*.3) >= t.hp) sc+=28; });
      if(len>=EXTRA_TURN_MIN) sc+=14;   // extra turn
    }
    /* IT HAS TO WANT YOUR BOMBS. Without this the AI never touches one, the
       board silts up with live bombs, and the risk the mechanic exists to
       create never actually lands on the player. A bomb it can reach is the
       best move on the board and it should take it. */
    g.cells.forEach(function(i){
      if(S.bomb[i]===BOMB_BOARD) sc += 120;
      else if(S.bomb[i]===BOMB_CROSS) sc += 45;
    });
    if(len>=5) sc += 30;        // leaves a board bomb
    else if(len===4) sc += 12;  // leaves a cross bomb
  });
  return sc;
}
/* THE AI IS DELIBERATELY NOT OPTIMAL.
   Slides give 545 legal moves on a typical board against 78 for adjacent
   swapping, and about 32 of them reach a 5-match. A machine that evaluates all
   545 and always takes the best one is not a worthy opponent, it is a wall --
   it would out-see a human every single turn, and the skill the slide mechanic
   exists to reward would never pay.

   So it picks from its shortlist rather than the top of it. Roughly half the
   time it plays its best move; otherwise it takes one of the next few. That
   reads as an opponent who missed something, which is what a human opponent
   does, and it leaves room for a player who spots the 5-match to win because
   they spotted it.

   This is a prototype knob. The real AI (dhcarena.md §3b) defends someone's
   Crew for real stakes and its strength is a design decision, not a
   convenience -- §14 of the locked calls says one fixed difficulty. */
function bestMove(side){
  var cand=[];
  eachSlide(function(a,z){
    var sc=scoreMove(a,z,side);
    if(sc>0) cand.push({a:a,b:z,s:sc});
  });
  if(!cand.length) return null;
  cand.sort(function(x,y){return y.s-x.s;});
  if(Math.random()<0.5) return cand[0];
  return cand[Math.min(cand.length-1, 1+Math.floor(Math.random()*4))];
}
/* WATCH THE OPPONENT MOVE.
   The board used to jump straight to its new state on the enemy's turn, so
   there was nothing to see and no way to tell an enemy move from a cascade --
   both just appeared. Monstrocity animates its AI's slide with the same
   transition the player gets, and that is what makes it read as somebody doing
   something rather than the board changing by itself.
   Reuses the geometry from previewDrag(): the chosen gem travels, the gems it
   passes shift back one, then the move commits. */
function animateSlide(a,z,cb){
  var lead=cellEl(a);
  var ra=Math.floor(a/N), ca=a%N, rz=Math.floor(z/N), cz=z%N;
  var row=(ra===rz), steps=row?(cz-ca):(rz-ra);
  if(!lead || !steps){ cb(); return; }
  var sz=cellSize(), dir=steps>0?1:-1, off=steps*sz;
  lead.classList.add('sel');                 // a beat on the gem it picked
  setTimeout(function(){
    lead.style.transition='transform .26s ease'; lead.style.zIndex='6';
    lead.style.transform = row ? 'translateX('+off+'px) scale(1.06)'
                               : 'translateY('+off+'px) scale(1.06)';
    for(var k=1;k<=Math.abs(steps);k++){
      var e=cellEl(row ? idx(ra,ca+dir*k) : idx(ra+dir*k,ca));
      if(!e) continue;
      e.style.transition='transform .26s ease';
      e.style.transform = row ? 'translateX('+(-dir*sz)+'px)'
                              : 'translateY('+(-dir*sz)+'px)';
    }
    setTimeout(function(){ lead.classList.remove('sel'); clearOffsets(); cb(); }, 290);
  }, 240);
}

function aiMove(){
  if(S.over)return;
  var mv=bestMove('foes');
  if(!mv){ makeBoard(); renderBoard(); mv=bestMove('foes'); if(!mv){endTurn(0);return;} }
  var ms=findMatches(slid(S.board,mv.a,mv.b));
  S.lastLen=ms.reduce(function(p,g){return Math.max(p,g.len);},0);
  busy=true;                                  // locked while they move
  animateSlide(mv.a,mv.b,function(){
  applySlide(mv.a,mv.b,'foes',function(chains){
    if(S.over)return;
    if(S.lastLen>=EXTRA_TURN_MIN){ logLine('foe','They matched '+S.lastLen+' — they go again.');
      renderAll(); setTimeout(aiMove,340); return; }
    tickBleeds('mine'); if(checkOver())return;
    S.turn='mine'; S.round++; renderAll();
  });
  });
}

/* ---------------- rendering ---------------- */
function cellEl(i){return document.querySelector('[data-i="'+i+'"]');}
function elFor(f){return document.querySelector('[data-id="'+f.uid+'"]');}
/** kind: '' a hit, 'big' a crit, 'heal' or 'shield' for the good ones. */
function flashTok(f,kind){
  var e=elFor(f); if(!e) return;
  var fl=e.querySelector('.flash'); if(!fl) return;
  fl.className='flash'+(kind?' '+kind:'');
  void fl.offsetWidth; fl.classList.add('on');
}
/* Top of the stack downward -- companion first, torso last -- so the Fighter
   comes apart in the reverse of the order it was built. The background is left
   alone: it is the ground the pieces fall against, not part of the body. */
var DEATH_ORDER=['companion','headgear','head','effects','arms','weapon','torso'];
function killAnim(f){
  var e=elFor(f); if(!e) return;
  e.classList.add('dying');
  flashTok(f,'big');
  var step=80, n=0;
  DEATH_ORDER.forEach(function(slot){
    var img=e.querySelector('.art img[data-l="'+slot+'"]'); if(!img) return;
    img.style.animationDelay=(n*step)+'ms';
    img.className='dis'+(n%2?' alt':'');   // alternate the spin: debris, not a rotation
    n++;
  });
  setTimeout(function(){ e.classList.remove('dying'); }, n*step+640);
}

function shake(f,big){
  var e=elFor(f); if(!e) return;
  e.classList.remove('hit','big'); void e.offsetWidth;
  e.classList.add('hit'); if(big) e.classList.add('big');
  flashTok(f,big?'big':'');
}
function act(f){
  var e=elFor(f); if(!e) return;
  e.classList.remove('act'); void e.offsetWidth; e.classList.add('act');
  layerAnim(f,'weapon','swing');      // the weapon that did it actually swings
  layerAnim(f,'companion','bob');     // and the companion reacts alongside it
}
/** Animate one or more trait layers of a Fighter, together and in step.
 *
 *  TAKES A LIST because some layers are rigidly attached to each other in the
 *  art and cannot move apart: headgear sits ON the head, so jolting the head
 *  alone slid the skull out from under its own helmet. They are one object as
 *  far as motion is concerned, even though the layering rules keep them
 *  separate for drawing.
 *
 *  Slots with no trait are skipped, which is most of them most of the time. */
function layerAnim(f,slots,cls){
  var e=elFor(f); if(!e) return;
  if(typeof slots==='string') slots=[slots];
  slots.forEach(function(slot){
    var img=e.querySelector('.art img[data-l="'+slot+'"]'); if(!img) return;
    img.classList.remove(cls); void img.offsetWidth; img.classList.add(cls);
  });
}
function pop(f,txt,kind){var e=elFor(f);if(!e)return;var p=document.createElement('div');
  p.className='pop on '+(kind||'');p.textContent=txt;e.appendChild(p);setTimeout(function(){p.remove();},950);}

function tokHtml(f,showGem){
  var pct=f.hp/f.maxHp, cls=pct<=.25?'crit':pct<=.55?'low':'';
  var t=f.traits;
  /* The background goes in, at full strength. It is one of the three MANDATORY
     slots and the largest pool on the platform at 42 traits -- leaving it out
     meant every token showed an incomplete Fighter against a transparency
     checkerboard, and a trait the player chose was invisible. Drawn exactly as
     the assembler and the gallery draw it: no filter, no scrim. */
  var layers=(t.background
      ? '<img class="bg" loading="lazy" alt="" src="'+artUrl('background',t.background,250)+'" onerror="this.remove()">'
      : '')
    + ['torso','weapon','arms','effects','head','headgear','companion']
    .filter(function(k){return t[k];})
    .map(function(k){return '<img data-l="'+k+'" loading="lazy" alt="" src="'
        +artUrl(k,t[k],250)+'" onerror="this.remove()">';}).join('');
  return '<div class="tok'+(showGem?' mine':' foe')+(f.ko?' ko':'')+'" data-id="'+f.uid+'"'
    + ' style="--gem:var(--g'+f.rank+')">'
    + '<div class="flash"></div>'
    /* BOTH sides wear their gem. The colour is the RANK -- red is front, amber
       is mid, violet is back -- and it drives whichever side matched it. So the
       same gem shows your weapon on the board and theirs on their token, which
       is the information that was missing: leave violet matches lying around
       and their back-rank Fighter is the one who gets to use them. */
    + '<div class="rk"><span>'+['front','mid','back'][f.rank]+'</span>'
    +   '<span class="mygem" title="'+(showGem?'your':'their')+' '
    +     ['front','mid','back'][f.rank]+' — '+f.kit.name+', '+f.kit.note+'">'
    +     f.kit.emoji+'</span></div>'
    + '<div class="art">'+layers+'</div>'
    + '<div class="nm">'+f.name+'</div>'
    + '<div class="kitn" title="'+f.kit.name+' — '+f.kit.note+'">'
    +   (showGem?f.kit.emoji+' ':'')+f.kit.note+'</div>'
    + '<div class="hpwrap"><div class="hp '+cls+'" style="transform:scaleX('+pct+')"></div>'
    +   '<div class="sh" style="width:'+Math.min(100,(f.shield/f.maxHp)*100)+'%"></div></div>'
    + '<div class="hpn"><span>'+f.hp+'/'+f.maxHp+'</span><span>'
    +   (f.shield>0?'🛡 '+f.shield+' ':'')
    +   (f.bleed>0?'🗡':'')+'</span></div>'
    + '</div>';
}
/* BUILD ONCE PER BATTLE. This used to rewrite both teams' innerHTML on every
   render, which throws away every <img> and builds new ones -- so all fourteen
   art layers were re-created on every move. On a phone that is a visible flash
   of the whole Crew each time you touch the board, and it was quietly killing
   the hit and lunge animations too, since the element being animated was
   replaced mid-animation. */
function buildTeams(){
  var byRank=function(a,b){return a.rank-b.rank;};
  document.getElementById('foeTeam').innerHTML =
    S.foes.slice().sort(byRank).map(function(f){return tokHtml(f,false);}).join('');
  document.getElementById('myTeam').innerHTML =
    S.mine.slice().sort(byRank).map(function(f){return tokHtml(f,true);}).join('');
}
/* Patch only what moves: health, shield, surge, bleed, knocked-out. The art,
   the name, the rank and the gem never change during a battle. */
function renderTeams(){
  everyone().forEach(function(f){
    var e=elFor(f); if(!e) return;
    if(f.ko) e.classList.add('ko'); else e.classList.remove('ko');
    var pct=f.hp/f.maxHp;
    var bar=e.querySelector('.hp');
    if(bar){
      bar.style.transform='scaleX('+pct+')';
      bar.className='hp'+(pct<=.25?' crit':pct<=.55?' low':'');
    }
    var sh=e.querySelector('.sh');
    if(sh) sh.style.width=Math.min(100,(f.shield/f.maxHp)*100)+'%';
    var n=e.querySelector('.hpn');
    if(n) n.innerHTML='<span>'+f.hp+'/'+f.maxHp+'</span><span>'
      + (f.shield>0?'🛡 '+f.shield+' ':'')
      + (f.bleed>0?'🗡':'')+'</span>';
  });
}
function renderBoard(dropAnim,settle){
  var g=document.getElementById('grid');
  g.style.gridTemplateColumns='repeat('+N+',1fr)';
  var h='';
  for(var i=0;i<N*N;i++){
    var v=S.board[i];
    var gi=gemInfo('mine',v), bm=S.bomb?Math.abs(S.bomb[i]):0;
    /* No emoji reads as "row and column". ✳️ is a thin glyph that disappears on
       a coloured gem -- 4-match bombs were being made and going unnoticed. The
       cross is drawn instead, two bars across the gem, which is literally the
       shape of the blast. 💣 is kept for the board bomb: it is chunky, it is
       unambiguous, and it does not need to describe a direction. */
    var face = bm===BOMB_BOARD ? '💣'
             : bm===BOMB_CROSS ? '<i class="cross"></i>'
             : gi.emoji;
    var tip  = bm===BOMB_BOARD ? 'Board bomb — clears everything. Match its colour to set it off.'
             : bm===BOMB_CROSS ? 'Bomb — clears its row and column. Match its colour to set it off.'
             : gi.name+' — '+gi.note;
    h+='<div class="cell '+SHAPE[v]+(bm?' bomb'+(bm===BOMB_BOARD?' bomb2':''):'')
      +(dropAnim?' drop':'')+(settle?' settle':'')+'" data-i="'+i+'"'
      +' style="--gc:var(--g'+v+')" title="'+tip+'">'
      +'<div class="g"><span class="em">'+face+'</span></div></div>';
  }
  g.innerHTML=h;
}
function renderAll(){
  renderTeams(); renderBoard();
  document.getElementById('terrain').style.backgroundImage='url("'+artUrl('background',S.terrainBg,1000)+'")';
  /* The description is the point. An earlier version read "Cleave — Bone",
     which pairs an action with a Fighter's name and never says what it does,
     so the legend answered the wrong question. Effect first, always. */
  /* ONE STRIP, NOT FIVE BANDS. The row version stranded the description on the
     left, threw the kit name at the right edge and orphaned the Fighter's name
     on a second line -- five of those cost ~290px under the board for five
     short phrases. The kit name is gone entirely: it was never the answer to
     "what does this do", which is the only question a legend is for. The
     Fighter's name is gone too, because its token already wears the gem. */
  function chip(colour, emoji, does, dead){
    return '<span class="lchip'+(dead?' gone':'')+'" style="--lc:'+colour+'">'
      + '<b>'+emoji+'</b>'+does+'</span>';
  }
  var lg=[0,1,2].map(function(i){
    var f=fighterForGem('mine',i);
    return f ? chip('var(--g'+i+')', f.kit.emoji, f.kit.note, f.ko) : '';
  }).join('')
  + chip('var(--g3)','🛡️','shields your team')
  + chip('var(--g4)','⚡','erupts at 10');
  document.getElementById('legend').innerHTML = lg;
  document.getElementById('legendM').innerHTML = lg;   // mobile slot, see markup
  // Everything else about reach is in the header; this is the at-a-glance
  // reminder plus the one thing that changes per battle, the terrain.
  var reachHtml=
      '<b>3</b> front · <b>4</b> mid · <b>5+</b> back · cascades multiply'
    + '<span class="terr">'+S.terrain.name+' — '+S.terrain.note+'</span>';
  document.getElementById('reach').innerHTML  = reachHtml;
  document.getElementById('reachM').innerHTML = reachHtml;

  ['mine','foes'].forEach(function(sd){
    var live=alive(sd);
    var v=live.length?Math.max.apply(null,live.map(function(f){return f.surge;})):0;
    var el=document.getElementById(sd==='mine'?'chgMine':'chgFoes');
    if(!el) return;
    el.className='chg'+(v>=10?' full':'');
    el.innerHTML='⚡ <i><b style="width:'+(v/10*100)+'%"></b></i>'+v+'/10';
    el.title = v>=10 ? 'Charged — the next Charge match erupts' : 'Charge: '+v+' of 10';
  });
  var bw=document.querySelector('.boardwrap');
  if(bw){ if(!S.over && S.turn==='foes') bw.classList.add('foeturn');
          else bw.classList.remove('foeturn'); }
  var fl=document.getElementById('flag');
  fl.className='turnflag '+(S.over?'':(S.turn==='mine'?'you':'foe'));
  fl.textContent=S.over?(S.over==='win'?'victory':'defeat'):(S.turn==='mine'?'your move':'their move');
  document.getElementById('round').textContent=S.over?'':'Round '+S.round;
  // the result lives on the end card over the board now, not in a side panel
}
/* LAST HURRAH. Every bomb still sitting on the board goes off once the battle
   is decided and the gems have come to rest. Pure spectacle -- S.settling has
   already switched every effect off, so nothing is resolved against a team that
   has lost. It is the payoff for a board that ends up littered with bombs
   nobody dared spend, and it gives the end of a battle a beat of its own
   instead of the board simply stopping. */
function finalHurrah(cb){
  var live=[], i;
  for(i=0;i<S.bomb.length;i++) if(S.bomb[i]) live.push(i);
  if(!live.length){ cb(); return; }

  var cleared={}, kind=0, n=0, queue=live.slice();
  while(queue.length){
    var at=queue.shift(), k=Math.abs(S.bomb[at]||0);
    if(!k) continue;
    S.bomb[at]=0; kind=Math.max(kind,k); n++;
    (k===BOMB_BOARD?allCells():rowColCells(at)).forEach(function(j){
      if(S.bomb[j]) queue.push(j);
      cleared[j]=1;
    });
  }
  sfx('boom');
  logLine('big','💥 Last hurrah — '+n+' bomb'+(n!==1?'s':'')+' still on the board go off.');
  var cb2=document.getElementById('combo');
  if(cb2){ cb2.textContent='💥 LAST HURRAH'; cb2.classList.remove('on');
           void cb2.offsetWidth; cb2.classList.add('on'); }
  Object.keys(cleared).forEach(function(j){
    var el=cellEl(j); if(el) el.classList.add(kind===BOMB_BOARD?'blast2':'blast');
  });
  var bw=document.querySelector('.boardwrap');
  if(bw){ bw.classList.remove('shake'); void bw.offsetWidth; bw.classList.add('shake'); }
  setTimeout(function(){
    Object.keys(cleared).forEach(function(j){ S.board[j]=-1; });
    collapse(); renderBoard(true);
    setTimeout(cb, 300);
  }, 560);
}

function showEnd(){
  var won=S.over==='win';
  var card=document.getElementById('endcard');
  var standing=alive(won?'mine':'foes');
  card.className='endcard '+(won?'win':'lose');
  document.getElementById('ecTitle').textContent = won?'Victory':'Defeat';
  document.getElementById('ecSub').textContent = won
    ? (standing.length===3
        ? 'Their Crew is down and yours did not lose a Fighter.'
        : standing.length+' of your Fighters still standing — '
          + standing.map(function(f){return f.name.split(' ')[0];}).join(' and ') + '.')
    : 'Your Crew is down. In the real game these Fighters would now be benched, '
      + 'and you would send the rest.';
  document.getElementById('ecStats').innerHTML =
      '<span><b>'+S.round+'</b>rounds</span>'
    + '<span><b>'+S.stats.bombs+'</b>bombs armed</span>'
    + '<span><b>'+S.stats.blasts+'</b>detonated</span>'
    + '<span><b>x'+S.stats.best+'</b>best chain</span>';
  card.hidden=false;
  sfx(won?'win':'lose');
  logLine('big', won?'VICTORY — their Crew is down.':'DEFEAT — your Crew is down.');
}

function logLine(kind,text){
  var l=document.getElementById('log');var d=document.createElement('div');
  d.className='log-'+kind;d.textContent=text;l.appendChild(d);l.scrollTop=l.scrollHeight;
}

/* ---------------- input: drag to slide ----------------
   Live preview while dragging, because that IS the feel: the gem follows your
   finger along one axis and the gems it displaces shift the other way, so you
   can see the move before you commit it. Pointer events so mouse and touch are
   one code path. A slide that produces no match reverts for free and does not
   cost the turn, matching Monstrocity. */
var drag=null;
function cellSize(){
  var c=document.querySelector('.cell');
  if(!c) return 40;
  var r=c.getBoundingClientRect();
  var g=parseFloat(getComputedStyle(document.getElementById('grid')).gap)||3;
  return r.width+g;
}
function clearOffsets(){
  document.querySelectorAll('.cell').forEach(function(e){
    e.style.transition=''; e.style.transform=''; e.style.zIndex='';
  });
}
function previewDrag(){
  if(!drag||!drag.axis) return;
  var sz=drag.size, a=drag.from, ra=Math.floor(a/N), ca=a%N;
  var raw = drag.axis==='row' ? drag.dx : drag.dy;
  var maxBack = (drag.axis==='row' ? ca : ra) * sz;
  var maxFwd  = ((N-1) - (drag.axis==='row' ? ca : ra)) * sz;
  var off = Math.max(-maxBack, Math.min(maxFwd, raw));
  var steps = Math.round(off/sz);
  drag.to = drag.axis==='row' ? idx(ra, ca+steps) : idx(ra+steps, ca);

  clearOffsets();
  var lead = cellEl(a);
  if(lead){ lead.style.zIndex='6';
    lead.style.transform = drag.axis==='row' ? 'translateX('+off+'px) scale(1.06)'
                                             : 'translateY('+off+'px) scale(1.06)'; }
  // everything between shifts one cell the other way, which is what a slide is
  if(steps!==0){
    var dir = steps>0?1:-1;
    for(var k=1;k<=Math.abs(steps);k++){
      var i = drag.axis==='row' ? idx(ra, ca+dir*k) : idx(ra+dir*k, ca);
      var e = cellEl(i); if(!e) continue;
      e.style.transition='transform .08s';
      e.style.transform = drag.axis==='row' ? 'translateX('+(-dir*sz)+'px)'
                                            : 'translateY('+(-dir*sz)+'px)';
    }
  }
}
function endDrag(commit){
  if(!drag) return;
  var from=drag.from, to=drag.to;
  clearOffsets(); drag=null;
  if(!commit || to===undefined || to===from) return;
  var nb=slid(S.board,from,to);
  if(!nb) return;
  var ms=findMatches(nb);
  if(!ms.length){ sfx('bad'); logLine('sys','No match on that slide — free, try another.'); return; }
  S.lastLen=ms.reduce(function(p,g){return Math.max(p,g.len);},0);
  applySlide(from,to,'mine',function(){ endTurn(1); });
}
var gridEl=document.getElementById('grid');
gridEl.addEventListener('pointerdown',function(e){
  if(busy||S.over||S.turn!=='mine')return;
  var c=e.target.closest('.cell'); if(!c)return;
  e.preventDefault();
  gridEl.setPointerCapture&&gridEl.setPointerCapture(e.pointerId);
  drag={from:+c.getAttribute('data-i'),x0:e.clientX,y0:e.clientY,dx:0,dy:0,
        axis:null,size:cellSize(),to:undefined};
  sfx('pick');
  c.classList.add('sel');
});
gridEl.addEventListener('pointermove',function(e){
  if(!drag)return;
  drag.dx=e.clientX-drag.x0; drag.dy=e.clientY-drag.y0;
  if(!drag.axis){
    if(Math.abs(drag.dx)>6&&Math.abs(drag.dx)>Math.abs(drag.dy)) drag.axis='row';
    else if(Math.abs(drag.dy)>6&&Math.abs(drag.dy)>Math.abs(drag.dx)) drag.axis='col';
  }
  if(drag.axis) previewDrag();
});
function release(e){ if(!drag)return;
  document.querySelectorAll('.cell.sel').forEach(function(x){x.classList.remove('sel');});
  endDrag(true); }
gridEl.addEventListener('pointerup',release);
gridEl.addEventListener('pointercancel',function(){ if(drag){clearOffsets();drag=null;} });
window.addEventListener('pointerup',function(e){ if(drag) release(e); });

document.getElementById('howto').onclick=function(){
  // title and intro together -- see the mobile block in the stylesheet
  document.body.classList.toggle('showintro');
};
var muteBtn=document.getElementById('mute');
function paintMute(){ muteBtn.textContent = sfxOn?'🔊':'🔇';
  muteBtn.title = sfxOn?'Mute sound':'Unmute sound'; }
muteBtn.onclick=function(){
  sfxOn=!sfxOn;
  try{ localStorage.setItem('dhcarena_sfx', sfxOn?'1':'0'); }catch(e){}
  paintMute(); if(sfxOn){ sfxInit(); sfx('pick'); }
};
paintMute();

/* Browsers refuse audio until the page has been interacted with, so the opening
   call plays on the first gesture rather than at load, where it would be
   swallowed. Once only -- it greets a battle, it is not a click sound. */
var greeted=false;
function greet(){ if(greeted) return; greeted=true; sfxInit(); sfx('start'); }
document.addEventListener('pointerdown', greet, {once:true});

document.getElementById('reroll').onclick=function(){ greeted=true; sfxInit(); newBattle(); sfx('start'); };
document.getElementById('ecAgain').onclick=function(){ newBattle(); sfx('start'); };
newBattle();
</script>
</body>
</html>
