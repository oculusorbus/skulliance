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
 * ON BRAND MEANS THE PLATFORM'S OWN BUTTON. .button in dist/flexbox.css is
 * solid #00c8a0 on #07111d text, 0.5rem radius, and a HARD offset shadow
 * (0 6px #007a62) that shortens on :active while the button translates down,
 * so it presses like a physical key. The old control was a translucent dark
 * pill -- 999px radius, hairline teal border, backdrop blur -- which shares
 * none of that and reads as a browser affordance dropped on top of the game.
 * This is the platform button scaled to a floating control: same colours,
 * same radius, same press, smaller.
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
  background: #00c8a0;
  color: #07111d;            /* re-stated: flexbox.css colours bare `a` */
  text-align: center;        /* re-stated for the same reason */
  border: 0;
  border-radius: 0.5rem;
  text-decoration: none;
  font-family: Arial, Helvetica, sans-serif;
  font-size: 0.88rem;
  font-weight: bold;
  line-height: 1;
  letter-spacing: 0.02em;
  box-shadow: 0 4px #007a62;
  transition: background 0.15s;
}
.skl-exit:hover,
.skl-exit:focus-visible {
  background: #00a882;
  color: #07111d;
  text-decoration: none;
}
.skl-exit:focus-visible { outline: 2px solid #07111d; outline-offset: 2px; }
/* The platform button's press: the shadow halves and the button moves into
   it, so the travel is real rather than a colour change. */
.skl-exit:active {
  background: #00a882;
  box-shadow: 0 2px #007a62;
  transform: translateY(2px);
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
  .skl-exit:active { transform: none; }
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
