<?php
/*
 * polish-dhc.php — the DHC pages' half of the gradient layer.
 *
 * WHY THESE PAGES NEEDED THEIR OWN. Same reason missions.php did: every
 * DHC surface is declared in an INLINE <style> block, which is later in
 * the document than any <link>, so dist/polish.css can never outrank it.
 * This is included immediately after each of those blocks.
 *
 * AND ONE OF THEM WAS NOT A SURFACE AT ALL. .dhcf-panel had a border and
 * no background, so it was a rectangle drawn on the page rather than a
 * panel sitting on it. There was nothing for a gradient to light. It
 * gets the platform's panel colour here, which is the colour its own
 * token already resolves to.
 *
 * THE TOKENS LINE UP EXACTLY, which is why this is short: the DHC pages
 * call #07111d --ink, #0a1929 --panel, #0d1e2e --panel2 and #1b3346
 * --line. Those are the platform's page, panel, field and hairline. So
 * every rule below reads the token and the platform rule it mirrors
 * stays true -- including that --panel2 is a FIELD and stays flat.
 *
 * HOW TO ROLL IT BACK. Delete the include lines. ?polish=0 turns it off
 * without one, the same flag header.php and missions-polish.php read.
 */
if (isset($_GET['polish']) && $_GET['polish'] === '0') return;
if (defined('DHC_POLISH')) return;
define('DHC_POLISH', 1);
?>
<style>
/* ---- Panels -------------------------------------------------------- */
/* Each keeps its own token, so a page that retunes --panel (the Arena's
   battle board does) is retuned here too rather than pinned to a hex. */
.dhcg-card,
.dhcf-card,
.dhcf-stat,
.arena-wrap .a-card,
.arena-wrap .a-panel,
.arena-wrap .a-stat,
.arena-wrap .a-foe,
.arena-wrap .panel,
.arena-wrap .fc-box,
.arena-wrap .logbox,
.arena-wrap .a-feed { background: var(--msp-lift, none), var(--panel, #0a1929); }

/* IT HAD A BORDER AND NO FILL. A bordered rectangle with the page
   showing through is not a panel, and there was nothing here for the
   platform layer to light even if it could have reached it. */
.dhcf-panel { background: var(--msp-lift, none), var(--panel, #0a1929); }

/*
 * THE ART WELLS AND THE BOARD STAY FLAT. .dhcg-art, .dhcf-card .art and
 * .arena-wrap .boardwrap are --panel2, which is the platform's field
 * colour: holes cut INTO a panel rather than surfaces on one. Lighting a
 * recess from above is what makes a gradient pass look cheap.
 */

/* ---- Controls ------------------------------------------------------ */
/* Outline buttons on a panel that now has a gradient read as holes
   unless they carry a faint fill of their own. */
.dhcf-pager button,
.arena-wrap .a-pager button,
.arena-wrap .tok { background: var(--msp-ghost, none), var(--panel, #0a1929); }
.dhcf-pager button:hover,
.arena-wrap .a-pager button:hover { background: var(--msp-ghost, none), rgba(0,200,160,0.12); }
/* The Arena's own primary. It is the platform's teal, so it gets the
   platform's sheen and edge. */
.arena-wrap .btn { background: var(--msp-fill, none), #00c8a0; border: 1px solid #2ee3bf; }
.arena-wrap .btn:hover { background: var(--msp-fill, none), #00a882; }
.arena-wrap .btn:active { background: var(--msp-fill-in, none), #00a882; }
</style>
