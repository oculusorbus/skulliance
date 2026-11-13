<?php
/*
 * polish-monstrocity.php — Monstrocity's half of the gradient layer.
 *
 * WHY THIS PAGE NEEDED ITS OWN, twice over. Monstrocity does not include
 * header.php at all -- it is a standalone game page, like skullswap.php --
 * so dist/polish.css is never linked and the --msp- tokens do not exist
 * here. And every surface it draws is declared in an INLINE <style>
 * block, which is later in the document than any <link>, so the platform
 * file could not outrank it even if it were loaded. So this declares the
 * tokens as well as consuming them, and is included immediately after
 * that block, the way missions-polish.php and polish-dhc.php are.
 *
 * THE TOKEN VALUES ARE COPIES, AND THE HARNESS PINS THEM. There is no
 * way to share them without linking a stylesheet whose panel and button
 * selectors (.content, .button) would reach into this page uninvited.
 * polish-harness.php asserts these five declarations match dist/polish.css
 * character for character, so the copy cannot drift into a second,
 * slightly different platform look.
 *
 * !important, WHICH THE OTHER TWO LAYERS DO NOT USE. Monstrocity's skin
 * block sets every one of these surfaces with !important already, so a
 * layer that did not would lose to it no matter where it sat in the
 * document. The rule it comes with: nothing here is !important that is
 * not shadowing an !important of the page's own, and no rule reaches
 * further than the one it shadows.
 *
 * IT OVERRIDES, IT DOES NOT REPLACE. Every rule keeps the colour the
 * page already chose and adds layers ON TOP, so the five gameplay tile
 * colours, the teal primary and the green health bar all still mean what
 * they meant. Nothing here invents a colour.
 *
 * HOW TO ROLL IT BACK. Delete the one include line. ?polish=0 turns it
 * off without a deploy, the same flag the other layers read.
 */
if (isset($_GET['polish']) && $_GET['polish'] === '0') return;
if (defined('MONSTROCITY_POLISH')) return;
define('MONSTROCITY_POLISH', 1);
?>
<style>
:root {
  --msp-lift: linear-gradient(180deg, rgba(255,255,255,.055) 0, rgba(255,255,255,0) 86px), linear-gradient(0deg, rgba(0,0,0,.15) 0, rgba(0,0,0,0) 52px);
  --msp-lift-soft: linear-gradient(180deg, rgba(255,255,255,.05) 0, rgba(255,255,255,0) 72px), linear-gradient(0deg, rgba(0,0,0,.06) 0, rgba(0,0,0,0) 40px);
  --msp-fill: linear-gradient(180deg, rgba(255,255,255,.26) 0, rgba(255,255,255,.05) 46%, rgba(0,0,0,.16) 100%);
  --msp-fill-in: linear-gradient(0deg, rgba(255,255,255,.2) 0, rgba(255,255,255,.03) 46%, rgba(0,0,0,.2) 100%);
  --msp-ghost: linear-gradient(180deg, rgba(255,255,255,.07) 0, rgba(255,255,255,0) 60%);
}

/* ---- Panels -------------------------------------------------------- */
/*
 * The tray the whole game sits on, and the three cards on it. The skin
 * block already calls these "cards on a tray, not pits in the floor" and
 * gives them a teal hairline and a drop shadow -- the lift is the half
 * that was missing, and it is what the hairline has to end against.
 */
.game-container { background: var(--msp-lift), #0d2230 !important; }
.character,
.log,
.legend       { background: var(--msp-lift), #1a3142 !important; }

/* The select-screen cards. Same colour, same treatment, so a card does
   not change character between the lobby and the battle. */
.character-option,
.boss-option        { background: var(--msp-lift), #1a3142 !important; }
.boss-option:hover  { background: var(--msp-lift), #23485c !important; }
/* A disabled card is deliberately transparent; leave it that way. */
.boss-option.disabled:hover { background: transparent !important; }

/* The one real dialog panel on the page. */
.progress-modal-content { background: var(--msp-lift), #121212 !important; }

/*
 * WHAT STAYS FLAT, AND WHY.
 *
 * #game-board is the ground the tiles sit IN, not a card -- the same
 * call polish-dhc.php makes for .arena-wrap .boardwrap and the platform
 * layer makes for every #080f18 well. Lighting a recess from above is
 * the single thing that makes a gradient pass look cheap, and here the
 * tiles already carry their own emboss, so a second highlight behind
 * them would read as a smear across the top row.
 *
 * .boss-project-filter is a select: a field, not a surface.
 *
 * The three select-screen containers are #07111d, the PAGE colour used
 * as a full-bleed modal ground. A ground is not a panel.
 *
 * .health-bar is the groove the health fill runs in. The rails stay flat
 * for the same reason, and the fill below is where the light goes.
 */

/* ---- Controls ------------------------------------------------------ */
/*
 * The sheen is a layer OVER the fill, so hover and active keep the exact
 * colours the skin block chose and only the ramp changes. The border is
 * what the lit top edge ends against; without one it is a painted stripe.
 * The page sets `border: none` on these, so border-box comes with it --
 * a button whose UA default is content-box would otherwise grow 2px in
 * each direction and shunt the row it sits in.
 */
button,
#leaderboard,
#select-boss-button,
#progress-resume            { background: var(--msp-fill), #00c8a0 !important;
                              border: 1px solid #2ee3bf !important;
                              box-sizing: border-box !important; }
button:hover,
#leaderboard:hover,
#select-boss-button:hover,
#progress-resume:hover      { background: var(--msp-fill), #00e6b8 !important;
                              border-color: #4af0d2 !important; }
button:active,
#leaderboard:active,
#select-boss-button:active,
#progress-resume:active     { background: var(--msp-fill-in), #00e6b8 !important; }

/* The quiet pair. A ghost button that shouts is worse than a flat one,
   so these get the soft ramp and keep their own near-transparent fill. */
#theme-close-button         { background: var(--msp-ghost), rgba(255,255,255,0.08) !important; }
#theme-close-button:hover   { background: var(--msp-ghost), rgba(255,70,70,0.18) !important; }
#progress-start-fresh       { background: var(--msp-ghost), rgba(255,255,255,0.08) !important; }
#progress-start-fresh:hover { background: var(--msp-ghost), rgba(255,255,255,0.14) !important; }

/* ---- The health bars ----------------------------------------------- */
/*
 * THE SHEEN RUNS ACROSS A BAR, NEVER ALONG IT -- the platform's rule,
 * and it matters more here than anywhere: a fill that ramps end to end
 * would say the health at the far end means something different from the
 * health at the near end. A 180deg sheen says "filled solid", which is
 * exactly what a health bar means, and it is the same ramp the buttons
 * use because a filled bar and a button are the same object.
 *
 * .health is coloured by state elsewhere (it goes amber and red as it
 * drains); background-image composites over background-color, so this
 * one rule lights every state without restating any of them.
 */
.health { background-image: var(--msp-fill) !important; }

/* ---- The legend ---------------------------------------------------- */
/*
 * The legend swatches are 20px miniatures of the five tile types, and
 * the tiles got their gradient and emboss in afe55149 while these did
 * not -- so the key stopped looking like the thing it is a key to. This
 * is the tile's own treatment at the tile's own values, scaled down: the
 * emboss loses the 2px bottom line, which at 20px is a third of the
 * swatch rather than an edge.
 *
 * Same trick as the tiles: the five colours are set by class and
 * background-image composites over background-color, so one rule lights
 * all five and none has to be named here.
 */
.legend-tile {
  background-image: linear-gradient(180deg,
      rgba(255,255,255,.20) 0,
      rgba(255,255,255,.04) 45%,
      rgba(0,0,0,.14) 55%,
      rgba(0,0,0,.34) 100%);
  box-shadow: inset 0 1px 0 rgba(255,255,255,.26),
              inset 0 -1px 0 rgba(0,0,0,.38),
              0px 2px 5px black;
}
</style>
