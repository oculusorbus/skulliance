<?php
/**
 * dhc-type.php -- one typeface for every DHC surface.
 *
 * THE PROBLEM THIS SOLVES. Each DHC page declared its own fonts against its
 * own wrapper, so a surface that was not one of those wrappers got the
 * platform's Arial instead, and the ones that were got slightly different
 * answers. Moving between the tabs changed the body text AND the headings:
 *
 *   - The Fighter detail panel is `position:fixed` and sits OUTSIDE
 *     .dhcg-wrap on the Collection but INSIDE .dhcf-wrap on the assembler.
 *     The same panel therefore rendered in the DHC monospace on one page and
 *     in the site's Arial on the other.
 *   - dhc-assembler.php declared `h1,h2,h3,.btn,.tab` UNSCOPED, so every
 *     page that includes it had its headings changed document-wide -- which
 *     is also why that panel's <h2> looked right on the assembler. A rule
 *     leaking was propping up a rule missing.
 *   - The Arena titles were mono while the Collection's were Archivo Black.
 *   - dhc-nav.php, the strip on all four pages, declares nothing at all and
 *     simply inherited whatever it happened to be sitting in.
 *
 * NOTE WHAT IS NOT HAPPENING HERE: neither family is actually loaded. There
 * is no @font-face and no Google Fonts link anywhere in the repo, so
 * "JetBrains Mono" resolves to ui-monospace/Menlo and "Archivo Black" to
 * Impact. That is fine and deliberate-looking, but it does mean the fix is
 * about reaching every element with the same STACK, not about shipping
 * fonts. Adding real webfonts is a separate decision with its own costs.
 *
 * SIZES ARE LEFT ALONE. The Arena runs at 13px against 14px elsewhere, and
 * its board is laid out around that; changing it to match is a layout
 * change rather than a typographic one, and 13 vs 14 of the same family is
 * not what makes a tab change feel jarring.
 *
 * ONE SELECTOR LIST, here, rather than a class added to six wrappers: fewer
 * files to touch and the list itself is the inventory. dhc-share-harness.js
 * asserts every DHC wrapper is in it, so a new surface cannot quietly miss.
 */
/*
 * A FUNCTION, NOT OUTPUT ON INCLUDE. Every DHC page requires its libraries
 * at the top of the file, before header.php has emitted anything -- a
 * partial that echoed a <style> on include would print in front of the
 * document and, on any page that sets a header afterwards, break it. So the
 * include is inert and the caller decides where the markup lands.
 *
 * Emits once however many times it is called: the assembler, the Collection
 * and the Arena all call it, and the assembler is itself included by two
 * pages.
 */
function dhc_type_styles() {
	if (defined('DHC_TYPE_RENDERED')) return;
	define('DHC_TYPE_RENDERED', 1);
?>
<style>
/* Every DHC surface, including the ones that are not inside a page wrapper:
   the detail panel is fixed-position and the nav strip travels. */
.dhcf-wrap, .dhcg-wrap, .arena-wrap, .shell, .dhcnav, #dhcg-veil {
  --dhc-body: "JetBrains Mono", ui-monospace, Menlo, monospace;
  --dhc-display: "Archivo Black", Impact, sans-serif;
  font-family: var(--dhc-body);
  -webkit-font-smoothing: antialiased;
}
/* Headings, buttons and inputs. Controls are named explicitly because form
   elements do NOT inherit font by default -- that is why several of these
   pages carry a `font:inherit` on every button they declare, and why one
   that forgets gets the browser's own UI font in the middle of the panel. */
.dhcf-wrap h1, .dhcf-wrap h2, .dhcf-wrap h3,
.dhcg-wrap h1, .dhcg-wrap h2, .dhcg-wrap h3,
.arena-wrap h1, .arena-wrap h2, .arena-wrap h3,
.shell h1, .shell h2, .shell h3,
#dhcg-veil h1, #dhcg-veil h2, #dhcg-veil h3 {
  font-family: var(--dhc-display);
  font-weight: 400;
}
.dhcf-wrap button, .dhcf-wrap input, .dhcf-wrap select, .dhcf-wrap textarea,
.dhcg-wrap button, .dhcg-wrap input, .dhcg-wrap select, .dhcg-wrap textarea,
.arena-wrap button, .arena-wrap input, .arena-wrap select, .arena-wrap textarea,
.shell button, .shell input, .shell select, .shell textarea,
.dhcnav button, .dhcnav input, .dhcnav select,
#dhcg-veil button, #dhcg-veil input, #dhcg-veil select {
  font-family: inherit;
}
</style>
<?php
}
?>
