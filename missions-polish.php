<?php
/*
 * missions-polish.php — TEMPORARY. A gradient-and-border pass over the
 * missions page, built to be thrown away.
 *
 * WHAT IT IS. Every panel on missions is a flat #0a1929 with a hairline
 * teal border, and every button is a flat fill. This adds a light lift
 * at the top edge and a settle at the bottom of each panel, and gives
 * the buttons a sheen and an edge. Nothing else: no spacing, no type, no
 * colour decisions.
 *
 * HOW TO ROLL IT BACK. Delete the one include line in missions.php. That
 * is the whole revert, and it is why every rule lives here rather than
 * being merged into the page's own stylesheet.
 *
 * HOW TO COMPARE WITHOUT DEPLOYING ANYTHING. missions.php?polish=0
 * renders none of this, so the two states are one tap apart on a phone.
 *
 * WHY IT IS AN OVERRIDE LAYER AND NOT AN EDIT. The page's stylesheet is
 * inline and 680 lines long. Editing twenty background declarations in
 * place and then unpicking them later is exactly the change that leaves
 * three of them behind. This block is included immediately after that
 * stylesheet, so it wins every tie on document order and each rule here
 * matches the specificity of the one it is shadowing.
 *
 * THE LIFT IS TWO FIXED-DISTANCE LAYERS, NOT A PERCENTAGE RAMP.
 * A 0%-to-100% gradient looks right on a 60px figure and like a wash on
 * the 600px drawer. Pinning the highlight to the top 86px and the shade
 * to the bottom 64px gives every panel the same edge regardless of how
 * tall it is -- and because the panel's own colour stays the last layer,
 * the translucent state panels (the claim bar, the news band) keep their
 * tint instead of being painted over.
 */
if (isset($_GET['polish']) && $_GET['polish'] === '0') return;
if (defined('MS_POLISH')) return;
define('MS_POLISH', 1);
?>
<style>
:root {
  /* Panels. Last layer stays the panel's own colour, so this composites
     over an opaque fill and a tint alike. */
  --msp-lift: linear-gradient(180deg, rgba(255,255,255,.055) 0, rgba(255,255,255,0) 86px),
              linear-gradient(0deg, rgba(0,0,0,.15) 0, rgba(0,0,0,0) 52px);
  /*
   * A SECOND, SOFTER LIFT FOR THE TINTED PANELS.
   *
   * The first pass used one lift everywhere, and the news band came out
   * muddy: a black bottom layer over an amber tint is brown, and the band
   * lost the colour that is the entire reason it is a different panel. A
   * tint carries its own weight, so it needs the top edge and almost none
   * of the shade. The nested cards use it too -- they sit on a panel that
   * already has a shade, and two stacked reads as a dirty mark.
   */
  --msp-lift-soft: linear-gradient(180deg, rgba(255,255,255,.05) 0, rgba(255,255,255,0) 72px),
                   linear-gradient(0deg, rgba(0,0,0,.06) 0, rgba(0,0,0,0) 40px);
  /*
   * And the ghost buttons get their own, far quieter ramp. On the first
   * pass they took the primary sheen and "Retreat" ended up reading as
   * loud as "Collect" -- a destructive action that outshouts the one
   * beside it is worse than the flat outline it replaced.
   */
  --msp-ghost: linear-gradient(180deg, rgba(255,255,255,.07) 0, rgba(255,255,255,0) 60%);
  /* Buttons are short, so these are percentages: a fixed distance would
     cover a 38px button entirely. */
  --msp-btn: linear-gradient(180deg, rgba(255,255,255,.26) 0,
              rgba(255,255,255,.05) 46%, rgba(0,0,0,.16) 100%);
  /* Pressed: the same ramp upside down, which is the whole trick. */
  --msp-btn-in: linear-gradient(0deg, rgba(255,255,255,.2) 0,
                 rgba(255,255,255,.03) 46%, rgba(0,0,0,.2) 100%);
}

/* ---- Panels on the flat panel colour ------------------------------- */
.ms-fig,
.ms-step,
.ms-primer-more,
.ms-daily,
.ms-deploy,
.ms-notice,
.ms-empty,
.ms-mcard,
.ms-proj,
.ms-quest,
.ms-drawer-box,
.ms-d-foot,
.ms-d-bar      { background: var(--msp-lift), #0a1929; }
/* Two classes, so it needs two of its own or the rule above outranks it. */
.ms-news.quiet { background: var(--msp-lift), #0a1929; }

/* ---- Panels that carry a state tint -------------------------------- */
/* Each keeps the exact rgba it had; only the softer lift goes on top. */
.ms-news       { background: var(--msp-lift-soft), rgba(245,166,35,.06); }
.ms-daily.go   { background: var(--msp-lift-soft), rgba(0,200,160,.06); }
.ms-claimbar   { background: var(--msp-lift-soft), rgba(0,200,160,.09); }
.ms-proj.sel   { background: var(--msp-lift-soft), rgba(0,200,160,.07); }
.ms-day.done   { background: var(--msp-lift-soft), rgba(0,200,160,.08); }
/* Nested inside .ms-news, which already carries a lift of its own. */
.ms-new        { background: var(--msp-lift-soft), #0a1929; }

/*
 * THE WELLS STAY FLAT ON PURPOSE. .ms-mcard-art, .ms-quest-art, .ms-d-art,
 * .ms-d-nft and the search field are #07111d holes cut INTO a panel. A
 * top highlight on a recess is the one thing that makes a gradient pass
 * look cheap, because it lights the hole the same way it lights the
 * surface and the depth goes flat.
 */

/* ---- The primary buttons ------------------------------------------- */
/*
 * The sheen is a layer OVER the fill, so hover and active keep the exact
 * colours flexbox.css already defines (#00c8a0 / #00a882) and only the
 * ramp changes. The border is what the gradient needs to sit in: without
 * one the lit top edge has nothing to end against.
 */
.ms-daily .button, .ms-deploy-buttons .button,
.ms-claimbar .button, .ms-d-launch .button {
  background: var(--msp-btn), #00c8a0;
  border: 1px solid #2ee3bf;
}
.ms-daily .button:hover, .ms-deploy-buttons .button:hover,
.ms-claimbar .button:hover, .ms-d-launch .button:hover {
  background: var(--msp-btn), #00a882;
  border-color: #1cd5b0;
}
.ms-daily .button:active, .ms-deploy-buttons .button:active,
.ms-claimbar .button:active, .ms-d-launch .button:active {
  background: var(--msp-btn-in), #00a882;
}
/* Disabled and working already drop to opacity, which carries the sheen
   down with them -- but the lit border does not read as "off", so it goes. */
.ms-daily .button:disabled, .ms-deploy-buttons .button:disabled {
  border-color: rgba(46,227,191,.35);
}
.ms-collect {
  background: var(--msp-btn), #00c8a0;
  border: 1px solid #2ee3bf;
}
.ms-collect:hover { background: var(--msp-btn), #00a882; }
.ms-collect:active { background: var(--msp-btn-in), #00a882; }

/* ---- The ghost buttons --------------------------------------------- */
/* These already had borders. They were transparent, which on a panel that
   now has a gradient of its own made them read as holes rather than
   controls, so they get a faint fill of their own. */
.ms-daily-lb, .ms-more-btn, .ms-d-tool {
  background: var(--msp-ghost), rgba(0,200,160,.05);
}
.ms-daily-lb:hover, .ms-more-btn:hover, .ms-d-tool:hover {
  background: var(--msp-ghost), rgba(0,200,160,.14);
}
.ms-retreat { background: var(--msp-ghost), rgba(122,158,176,.05); }
.ms-retreat:hover { background: var(--msp-ghost), rgba(122,158,176,.14); }
</style>
