<?php
/**
 * The way out of a standalone game page.
 *
 * WHY IT EXISTS AT ALL: the game pages do not include header.php, so there is
 * no burger menu. In PWA standalone mode there is no browser back button
 * either, so without this control a player is simply trapped in the game.
 *
 * WHY IT IS SHARED: it was written twice, in monstrocity.php and
 * match3rpg.php, the second one's comment saying it mirrors the first "so the
 * interaction feels consistent across the two standalone pages". Two copies
 * is how that consistency stops being true.
 *
 * ON BRAND MEANS THE BUTTONS ON THE PAGE IT SITS ON, which is not the same
 * thing as .button in dist/flexbox.css -- and reading the stylesheet instead
 * of the page is how this went wrong twice.
 *
 * It started as a translucent dark pill -- 999px radius, hairline border,
 * backdrop blur -- which read as a browser affordance dropped on the game,
 * so it was rebuilt as flexbox.css's .button: solid teal, 0.5rem radius, and
 * that stylesheet's HARD OFFSET SHADOW (0 6px #007a62) shortening on :active
 * while the button translated into it. REPORTED, and correctly: that slab is
 * an antiquated style left over from the original platform CSS, and NEITHER
 * page that renders this control has it. Monstrocity's own buttons have no
 * shadow at rest and lift 1px into a soft teal glow on hover;
 * match3rpg.php's CTAs carry a soft glow and lift 2px. The slab made this the
 * one chunky 3D key in a page of flat lifting ones.
 *
 * So the press is now the page's press, and the fill carries the sheen and
 * the lit #2ee3bf edge that dist/polish.css gave every other button. At rest
 * it keeps a soft ambient shadow -- 0 4px 14px rgba(0,0,0,0.4), the value
 * Monstrocity's own panels use -- because unlike those buttons this one
 * floats over the board and needs to sit above it rather than on it.
 *
 * COLOUR AND text-align ARE RE-STATED even though they look redundant.
 * dist/flexbox.css styles bare `a` globally with its own colour and
 * text-align:center; match3rpg.php pulls that in through site-header.php,
 * monstrocity.php does not, and a component that renders differently on two
 * pages is the exact thing this file exists to prevent.
 */

/**
 * $opts: href, label, aria, top (px below the top inset -- 62 on a page with
 * the sticky site-header, which is ~51px tall, so the button loses to the
 * nav instead of covering its skull mark).
 */
function game_exit_button($opts = array()) {
	static $css_done = false;

	$href  = isset($opts['href'])  ? $opts['href']  : 'launchpad.php';
	$label = isset($opts['label']) ? $opts['label'] : 'Exit';
	$top   = isset($opts['top'])   ? (int)$opts['top'] : 10;
	$aria  = isset($opts['aria'])  ? $opts['aria']
	       : 'Leave the game and return to the Skulliance Launchpad';

	if (!$css_done) {
		$css_done = true;
		?>
<style>
.skl-exit {
  position: fixed;
  /* The inset keeps it clear of a phone's status bar / notch; --skl-exit-top
     is the page's own offset on top of that. */
  top: calc(env(safe-area-inset-top, 0px) + var(--skl-exit-top, 10px));
  left: calc(env(safe-area-inset-left, 0px) + 10px);
  z-index: 99990;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  padding: 9px 15px;
  min-height: 38px;          /* a comfortable tap target */
  box-sizing: border-box;
  /*
   * THE SHEEN IS A LAYER OVER THE FILL, so the colour stays the one the
   * platform chose and only the ramp is added -- the same move
   * dist/polish.css makes on .button.
   *
   * THE LITERAL IS THE FALLBACK, NOT THE OTHER WAY ROUND. Neither page
   * that renders this control links dist/polish.css: monstrocity.php
   * includes no header at all and match3rpg.php uses site-header.php.
   * So the token resolves only where a page-level layer declared it, and
   * the literal carries the rest. game-exit-harness.php asserts the two
   * literals below still match the platform's tokens character for
   * character, so the copy cannot drift.
   */
  background: var(--msp-fill, linear-gradient(180deg, rgba(255,255,255,.26) 0, rgba(255,255,255,.05) 46%, rgba(0,0,0,.16) 100%)), #00c8a0;
  color: #07111d;            /* re-stated: flexbox.css colours bare `a` */
  text-align: center;        /* re-stated for the same reason */
  /* The lit top edge needs something to end against; without a border it
     is a painted stripe. box-sizing is already border-box above, so the
     button does not grow by the 2px. */
  border: 1px solid #2ee3bf;
  border-radius: 0.5rem;
  text-decoration: none;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 0.88rem;
  font-weight: bold;
  line-height: 1;
  letter-spacing: 0.02em;
  /* Ambient, not a slab: this floats over the board and has to sit above it.
     The value is the one monstrocity.php gives .character/.log/.legend. */
  box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4);
  transition: background 0.15s ease, transform 0.15s ease, box-shadow 0.15s ease;
}
/* Monstrocity's own buttons go BRIGHTER on hover, not darker, and lift into
   a teal glow. Matching the page, not the platform stylesheet. */
.skl-exit:hover,
.skl-exit:focus-visible {
  background: var(--msp-fill, linear-gradient(180deg, rgba(255,255,255,.26) 0, rgba(255,255,255,.05) 46%, rgba(0,0,0,.16) 100%)), #00e6b8;
  border-color: #4af0d2;
  color: #07111d;
  text-decoration: none;
  transform: translateY(-1px);
  box-shadow: 0 4px 14px rgba(0, 200, 160, 0.35);
}
.skl-exit:focus-visible { outline: 2px solid #07111d; outline-offset: 2px; }
/* THE PRESS IS THE LIFT COLLAPSING, not a key going into a slab: the button
   settles back to the page and the glow goes with it, which is what every
   other button on these two pages does. */
.skl-exit:active {
  /* The same ramp upside down, which is the whole trick. */
  background: var(--msp-fill-in, linear-gradient(0deg, rgba(255,255,255,.2) 0, rgba(255,255,255,.03) 46%, rgba(0,0,0,.2) 100%)), #00e6b8;
  transform: translateY(0);
  box-shadow: 0 2px 6px rgba(0, 0, 0, 0.4);
}
.skl-exit .skl-exit-arrow { font-size: 1rem; line-height: 1; }
/* On a phone the label goes and the arrow carries it, so the button stops
   competing with the game for the top-left corner. */
@media (max-width: 480px) {
  .skl-exit { padding: 9px 11px; gap: 0; }
  .skl-exit .skl-exit-label { display: none; }
}
@media (prefers-reduced-motion: reduce) {
  .skl-exit { transition: none; }
  .skl-exit:hover, .skl-exit:focus-visible, .skl-exit:active { transform: none; }
}
</style>
		<?php
	}
	?>
<a class="skl-exit" style="--skl-exit-top:<?php echo $top; ?>px"
   href="<?php echo htmlspecialchars($href); ?>"
   aria-label="<?php echo htmlspecialchars($aria); ?>"><span class="skl-exit-arrow" aria-hidden="true">&larr;</span><span class="skl-exit-label"><?php echo htmlspecialchars($label); ?></span></a>
	<?php
}
