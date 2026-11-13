<?php
/**
 * game-exit-harness.php — the way out of a standalone game page.
 *
 * Run: php game-exit-harness.php
 *
 * The component is RENDERED and its output inspected, not grepped. The two
 * things most worth holding -- that the CSS is emitted once however many
 * buttons there are, and that the colour is re-stated so a page-level bare
 * `a` rule cannot win -- are both properties of the output.
 */
require_once __DIR__ . '/lib/game-exit.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}
function render($opts = array()) {
	ob_start(); game_exit_button($opts); return ob_get_clean();
}

echo "\nthe first call brings its styles with it\n";
$first = render();
ok(strpos($first, '<style>') !== false, 'the component renders no CSS, so it is an unstyled link');
ok(strpos($first, 'class="skl-exit"') !== false, 'the control has no class to style');

echo "\nand later calls do not repeat them\n";
/* Two <style> blocks is not a visual bug, it is a signal the guard is gone --
   and the guard is what lets a page drop in a second button safely. */
$second = render(array('label' => 'Quit'));
ok(strpos($second, '<style>') === false,
   'the CSS is emitted again on every call, so two buttons ship it twice');
ok(strpos($second, 'class="skl-exit"') !== false, 'a later call renders no button at all');

echo "\nit is the platform's button, not a browser pill\n";
/*
 * .button in dist/flexbox.css is the brand: solid #00c8a0, #07111d text,
 * 0.5rem radius, and a HARD offset shadow that halves on :active while the
 * button translates into it. The old control was a translucent pill with a
 * 999px radius and a backdrop blur, which is none of those things.
 */
/* THE TEAL IS NOW THE LAYER UNDER THE SHEEN, not the whole background, so
   this reads the last layer rather than the literal declaration it used to
   pin. Same for hover: the colour the page chose stays the colour. */
ok(preg_match('~background:\s*var\(--msp-fill,[^;]*\),\s*#00c8a0\s*;~', $first) === 1,
   'the button is not the platform teal, or the teal is no longer the layer under the sheen');
ok(preg_match('~background:\s*var\(--msp-fill,[^;]*\),\s*#00e6b8\s*;~', $first) === 1,
   "hover is not the page's own brighter teal");
ok(preg_match('~background:\s*var\(--msp-fill-in,[^;]*\),\s*#00e6b8\s*;~', $first) === 1,
   'the press is no longer the inverted ramp, so it does not read as a button being pushed');
ok(strpos($first, 'border: 1px solid #2ee3bf') !== false,
   'the lit edge is gone, so the sheen ends on nothing and reads as a painted stripe');
/*
 * THE SHEEN LITERALS ARE COPIES OF THE PLATFORM TOKENS, and this is what
 * stops them drifting. They have to BE literals: neither page that renders
 * this control links dist/polish.css -- monstrocity.php includes no header
 * and match3rpg.php uses site-header.php -- so var(--msp-fill) alone would
 * compute to nothing and the button would be TRANSPARENT, not flat. The
 * token is still read first, so a page that does declare it (Monstrocity,
 * through polish-monstrocity.php) shares the one value.
 */
$polCssRaw = preg_replace('~/\*.*?\*/~s', '', (string) @file_get_contents(__DIR__ . '/dist/polish.css'));
foreach (array('--msp-fill', '--msp-fill-in') as $t) {
	$want = preg_match('~' . preg_quote($t, '~') . ':\s*([^;]+);~', $polCssRaw, $m)
	      ? preg_replace('~\s+~', ' ', trim($m[1])) : '';
	/* A BALANCED SCAN, NOT A REGEX. The fallback is a pair of
	   linear-gradient(rgba(...), ...) calls, so [^)]* stopped at the first
	   inner paren and reported every value as drifted -- on a clean tree. */
	$got = '';
	$at  = strpos($first, 'var(' . $t . ',');
	if ($at !== false) {
		$i = $at + strlen('var(' . $t . ','); $depth = 1; $start = $i;
		for (; $i < strlen($first); $i++) {
			if ($first[$i] === '(') $depth++;
			elseif ($first[$i] === ')') { $depth--; if ($depth === 0) break; }
		}
		$got = preg_replace('~\s+~', ' ', trim(substr($first, $start, $i - $start)));
	}
	ok($want !== '', "$t cannot be read from dist/polish.css");
	ok($want === $got, "the $t fallback has drifted from the platform's value");
}
ok(strpos($first, 'border-radius: 0.5rem') !== false,
   'the radius is not the platform 0.5rem');
ok(strpos($first, 'border-radius: 999px') === false, 'it is still a rounded pill');
ok(strpos($first, 'backdrop-filter') === false, 'it still uses the blurred-glass treatment');
/*
 * THE HARD SLAB IS GONE, AND THAT IS THE ASSERTION NOW.
 *
 * It was `box-shadow: 0 4px #007a62`, copied from .button in
 * dist/flexbox.css -- an antiquated style neither page that renders this
 * control actually uses. Monstrocity's buttons have no shadow at rest and
 * lift into a soft teal glow; match3rpg's CTAs carry a soft glow and lift.
 * The slab made this the one chunky 3D key on a page of flat lifting ones.
 * Reading the stylesheet instead of the page is how it got there.
 */
ok(strpos($first, '#007a62') === false,
   'the hard offset slab is back; neither page that renders this button has one');
/* THE PRESS IS THE SIGNATURE, and it is now the lift collapsing: hover
   raises the button into a glow, active settles it back. Both halves, or it
   is just a colour change. */
ok(strpos($first, 'translateY(-1px)') !== false
   && strpos($first, 'box-shadow: 0 4px 14px rgba(0, 200, 160, 0.35)') !== false,
   'hover does not lift into the glow the page gives its own buttons');
ok(preg_match('~\.skl-exit:active\s*\{[^}]*transform:\s*translateY\(0\)~s', $first) === 1,
   'the button does not settle on press, so the lift never collapses');
/* An ambient shadow at rest, because unlike those buttons this one floats
   over the board rather than sitting on a panel. */
ok(strpos($first, 'box-shadow: 0 4px 14px rgba(0, 0, 0, 0.4)') !== false,
   'the floating control has no ambient shadow, so it reads as pasted onto the board');

echo "\ncolour and alignment are re-stated\n";
/*
 * THE TRAP THIS EXISTS FOR. dist/flexbox.css styles bare `a` globally with
 * its own colour and text-align:center, and monstrocity.php sets
 * `a { color: white }` in its own head. A component that inherits either one
 * renders differently on the two pages that use it -- which is the whole
 * reason it was made shared.
 */
ok(preg_match('/\.skl-exit\s*\{[^}]*color:\s*#07111d/s', $first) === 1,
   'the button does not state its own text colour, so a page-level `a` rule decides it');
ok(preg_match('/\.skl-exit\s*\{[^}]*text-align:\s*center/s', $first) === 1,
   'the button does not state its own text-align');
/* And the hover must restate it too, or hovering hands the colour back. */
ok(preg_match('/\.skl-exit:hover[^{]*\{[^}]*color:\s*#07111d/s', $first) === 1,
   'hover does not restate the colour, so the text changes colour under the cursor');

echo "\nthe page decides how far down it sits\n";
/* site-header.php is sticky and ~51px tall; at the top the button covered
   its skull mark instead of losing to it. */
ok(strpos(render(), '--skl-exit-top:10px') !== false, 'the default offset is not 10px');
ok(strpos(render(array('top' => 62)), '--skl-exit-top:62px') !== false,
   'the offset cannot be set per page');
/* AND THE CSS HAS TO READ IT. Asserting only that the variable is set on the
   element passes while the rule hardcodes a value and ignores it -- which is
   a real mutation that got through the first version of this check, and
   would have put match3rpg's button back over the sticky nav. */
ok(strpos($first, 'var(--skl-exit-top') !== false,
   'the CSS hardcodes the offset instead of reading the per-page variable');
ok(strpos($first, 'env(safe-area-inset-top') !== false,
   'the offset ignores the phone inset, so it sits under the status bar');

echo "\nuntrusted text cannot break out\n";
$evil = render(array('href' => '"><script>x()</script>', 'label' => '<b>no</b>'));
ok(strpos($evil, '<script>') === false, 'the href is not escaped');
ok(strpos($evil, '<b>no</b>') === false, 'the label is not escaped');

echo "\nit is reachable without a mouse\n";
ok(strpos($first, ':focus-visible') !== false, 'there is no keyboard focus state');
ok(strpos($first, 'aria-label=') !== false, 'the control has no accessible name');
/* The arrow is decoration; a screen reader announcing "left arrow Exit" is
   noise when the aria-label already says where it goes. */
ok(strpos($first, 'aria-hidden="true"') !== false, 'the arrow glyph is read out as content');
ok(strpos($first, 'prefers-reduced-motion') !== false,
   'the press animation ignores a reduced-motion preference');

echo "\nboth game pages use it, and keep no copy\n";
foreach (array('monstrocity.php' => 10, 'match3rpg.php' => 62) as $p => $top) {
	$src = file_get_contents(__DIR__ . '/' . $p);
	ok(strpos($src, "require_once __DIR__ . '/lib/game-exit.php'") !== false,
	   "$p does not include the exit component");
	ok(strpos($src, 'game_exit_button(') !== false, "$p does not render the exit button");
	/* THE OLD CSS IS GONE, not just unused. A stale #monstrocity-exit rule is
	   the next person's twenty minutes. */
	ok(strpos($src, '#monstrocity-exit') === false && strpos($src, '#m3-exit') === false,
	   "$p still carries its old exit-button CSS");
	ok(strpos($src, 'mx-arrow') === false && strpos($src, 'mx-label') === false,
	   "$p still references the old button's inner spans");
	if ($top !== 10)
		ok(strpos($src, "'top'   => " . $top) !== false,
		   "$p lost its $top px offset and will cover the sticky nav");
}

echo "\nnothing forces the old light text onto it\n";
/*
 * monstrocity.php listed #monstrocity-exit under "secondary buttons" with
 * `color:#e8eaed !important`. On a solid teal button that is unreadable, and
 * !important beats anything the component says.
 */
$mono = file_get_contents(__DIR__ . '/monstrocity.php');
ok(preg_match('/\.skl-exit[^{]*\{[^}]*color:[^;]*!important/s', $mono) !== 1,
   'monstrocity.php forces a colour onto the shared button with !important');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
