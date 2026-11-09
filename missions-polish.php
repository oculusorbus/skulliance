<?php
/*
 * missions-polish.php — the missions page's half of the gradient layer.
 *
 * WHY THIS PAGE NEEDS ITS OWN. dist/polish.css carries the tokens and
 * every platform surface, and header.php links it. It cannot reach this
 * page's panels: missions.php's stylesheet is INLINE and therefore later
 * in the document than any <link>, so it outranks that file on document
 * order. These rules sit immediately after it, which is the only place
 * they can win from without resorting to !important.
 *
 * So this file is only the .ms- rules. The tokens it uses are declared
 * in dist/polish.css, with `none` fallbacks here so that deleting that
 * file's <link> and leaving this one in place degrades to flat panels
 * rather than to transparent ones.
 *
 * HOW TO ROLL IT BACK. Delete the one include line in missions.php.
 * ?polish=0 turns it off without one, the same flag header.php reads.
 *
 * THE WELLS STAY FLAT ON PURPOSE. .ms-mcard-art, .ms-quest-art,
 * .ms-d-art, .ms-d-nft and the search field are #07111d holes cut INTO a
 * panel. A top highlight on a recess is the one thing that makes a
 * gradient pass look cheap, because it lights the hole the same way it
 * lights the surface and the depth goes flat.
 */
if (isset($_GET['polish']) && $_GET['polish'] === '0') return;
if (defined('MS_POLISH')) return;
define('MS_POLISH', 1);
?>
<style>
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
.ms-d-bar      { background: var(--msp-lift, none), #0a1929; }
/* Two classes, so it needs two of its own or the rule above outranks it. */
.ms-news.quiet { background: var(--msp-lift, none), #0a1929; }

/* ---- Panels that carry a state tint -------------------------------- */
/* Each keeps the exact rgba it had; only the softer lift goes on top. */
.ms-news       { background: var(--msp-lift-soft, none), rgba(245,166,35,.06); }
.ms-daily.go   { background: var(--msp-lift-soft, none), rgba(0,200,160,.06); }
.ms-claimbar   { background: var(--msp-lift-soft, none), rgba(0,200,160,.09); }
.ms-proj.sel   { background: var(--msp-lift-soft, none), rgba(0,200,160,.07); }
.ms-day.done   { background: var(--msp-lift-soft, none), rgba(0,200,160,.08); }
/* Nested inside .ms-news, which already carries a lift of its own. */
.ms-new        { background: var(--msp-lift-soft, none), #0a1929; }

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
  background: var(--msp-fill, none), #00c8a0;
  border: 1px solid #2ee3bf;
}
.ms-daily .button:hover, .ms-deploy-buttons .button:hover,
.ms-claimbar .button:hover, .ms-d-launch .button:hover {
  background: var(--msp-fill, none), #00a882;
  border-color: #1cd5b0;
}
.ms-daily .button:active, .ms-deploy-buttons .button:active,
.ms-claimbar .button:active, .ms-d-launch .button:active {
  background: var(--msp-fill-in, none), #00a882;
}
/* Disabled and working already drop to opacity, which carries the sheen
   down with them -- but the lit border does not read as "off", so it goes. */
.ms-daily .button:disabled, .ms-deploy-buttons .button:disabled {
  border-color: rgba(46,227,191,.35);
}
.ms-collect {
  background: var(--msp-fill, none), #00c8a0;
  border: 1px solid #2ee3bf;
}
.ms-collect:hover { background: var(--msp-fill, none), #00a882; }
.ms-collect:active { background: var(--msp-fill-in, none), #00a882; }

/* ---- The progress bars --------------------------------------------- */
/*
 * THE SHEEN RUNS ACROSS A BAR, NEVER ALONG IT.
 *
 * The obvious thing to do to a progress bar is ramp the colour from one
 * end to the other, and it is wrong here: a bar whose fill changes along
 * its length reads as though the value at the far end means something
 * different from the value at the near end. These bars are time
 * remaining and crew readiness. A 180deg sheen says "filled solid",
 * which is what they actually mean, and it is the same ramp the buttons
 * use because a filled bar and a button are the same object -- a lit
 * solid on a dark page.
 *
 * THE TRACKS STAY FLAT, for the same reason the art wells do: an empty
 * bar is a groove, and lighting a groove from above flattens it.
 */
/* The daily reward countdown bar is NOT here: .w3-grey-rewards is a
   platform class and dist/polish.css lights it everywhere it appears,
   including inside the claim reveal. */
/* The drawer's success meter, 6px. Both states keep their own colour. */
.ms-d-meter i      { background: var(--msp-fill, none), #00c8a0; }
.ms-d-meter.sure i { background: var(--msp-fill, none), #b47fff; }
/* The mission card's time bar, 3px: about one lit pixel and one shaded
   one, which reads as a bevelled hairline rather than a flat slab. */
.ms-mcard-bar i                 { background: var(--msp-fill, none), #00c8a0; }
.ms-mcard.success .ms-mcard-bar i { background: var(--msp-fill, none), rgba(140,90,255,.75); }
.ms-mcard.failure .ms-mcard-bar i { background: var(--msp-fill, none), rgba(255,92,92,.6); }
/*
 * .ms-proj-bar IS DELIBERATELY LEFT ALONE. It is 2px, so the ramp would
 * be one light pixel and one dark one with none of the fill left in
 * between -- and that fill is #ffcc44 when a project is partly ready and
 * #00c8a0 when it is full. Washing out the only thing that distinguishes
 * them to gain a bevel nobody can see is a bad trade.
 */

/* ---- The ghost buttons --------------------------------------------- */
/* These already had borders. They were transparent, which on a panel that
   now has a gradient of its own made them read as holes rather than
   controls, so they get a faint fill of their own. */
.ms-daily-lb, .ms-more-btn, .ms-d-tool {
  background: var(--msp-ghost, none), rgba(0,200,160,.05);
}
.ms-daily-lb:hover, .ms-more-btn:hover, .ms-d-tool:hover {
  background: var(--msp-ghost, none), rgba(0,200,160,.14);
}
.ms-retreat { background: var(--msp-ghost, none), rgba(122,158,176,.05); }
.ms-retreat:hover { background: var(--msp-ghost, none), rgba(122,158,176,.14); }
</style>
