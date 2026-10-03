<?php
/* dropship-topstrip-harness.php - CLI only. No database, no network.
 *
 * Drop Ship's burger icon is position:fixed in the top-right corner, so it
 * takes no space in flow. `.container { padding-top: 60px }` pushes the page's
 * own content down to clear it, and `.container::before` paints that 60px gap
 * -- without it the gap shows body's wallpaper where the rest of the site
 * shows dark chrome.
 *
 * THE STRIP WAS position:fixed, AND THAT IS THE BUG THIS FILE EXISTS FOR. A
 * positioned box with z-index:0 paints above EVERY non-positioned in-flow
 * element on the page (CSS 2.1 Appendix E: in-flow block backgrounds and
 * inline content are painted in steps 4 and 7, positioned z-index:auto/0
 * descendants in step 8). So the moment the page scrolled, the strip stopped
 * being a backdrop for an empty gap and became an opaque 60px bar riding the
 * top of the viewport, slicing the soldier cards in half. Reported on both
 * Drop Ship and Oculus Lounge, which is one stylesheet and therefore one bug.
 *
 * Measured in headless Chrome at 393x852, scrolled 600px, against this exact
 * stylesheet: with position:fixed the artwork was painted over from y=0 to
 * y=60 and only began below it; with position:absolute it starts at y=0 and
 * the strip still paints the gap at scroll 0.
 *
 * The gap is at the top of the DOCUMENT, not the top of the viewport, so
 * absolute is not a workaround -- it is where this strip always belonged.
 *
 * Usage: php dropship-topstrip-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$css = file_get_contents(__DIR__ . '/dropship/dist/flexbox.css');
ok($css !== false && $css !== '', 'dropship/dist/flexbox.css is missing');

/* The mobile rules only -- the burger, the clearance padding and the strip
   are all under max-width:700px, and desktop has none of the three. There is
   more than ONE such block in this stylesheet (the nav collapse has its own),
   so every one of them is collected and brace-matched; taking the first match
   silently slices the wrong block and every check below then "fails" because
   it is reading the dropdown rules. */
$mobile = '';
$from = 0;
while (($at = strpos($css, '@media screen and (max-width: 700px)', $from)) !== false) {
	$i = strpos($css, '{', $at); $depth = 0; $end = $i;
	for ($n = strlen($css); $i < $n; $i++) {
		if ($css[$i] === '{') $depth++;
		elseif ($css[$i] === '}') { $depth--; if ($depth === 0) { $end = $i; break; } }
	}
	$mobile .= substr($css, $at, $end - $at + 1) . "\n";
	$from = $end + 1;
}
ok($mobile !== '', 'the max-width:700px media query is gone from flexbox.css');
if ($mobile === '') { echo "\nFAILED\n"; exit(1); }
/* Comments out before anything is parsed: this stylesheet carries long
   explanatory comments directly above the rules being read, and they end up
   inside the "selector" half of a naive rule split -- which turns a one-line
   failure message into four paragraphs of prose. */
$mobile = preg_replace('!/\*.*?\*/!s', '', $mobile);

/* The strip's own declarations. */
ok(preg_match('/\.container::before\s*\{([^}]*)\}/', $mobile, $m) === 1,
   'the clearance strip (.container::before) is gone from the mobile block, so the 60px gap shows the wallpaper again');
$strip = isset($m[1]) ? $m[1] : '';

echo "the clearance strip cannot ride the viewport\n";
ok(preg_match('/position\s*:\s*absolute/', $strip) === 1,
   'the clearance strip is not position:absolute');
ok(preg_match('/position\s*:\s*fixed/', $strip) !== 1,
   'the clearance strip is position:fixed again -- it will paint over the cards the moment the page scrolls, which is the exact bug reported on Drop Ship and Oculus Lounge');
ok(preg_match('/top\s*:\s*0/', $strip) === 1,
   'the clearance strip no longer sits at the top of the document');

echo "\nand it still covers exactly the gap the burger needs\n";
ok(preg_match('/background-color\s*:\s*#[0-9a-f]{6}/i', $strip) === 1,
   'the clearance strip paints no colour, so the gap shows body\'s wallpaper -- which is why the strip was added');
ok(preg_match('/width\s*:\s*100%/', $strip) === 1, 'the clearance strip is no longer full width');

preg_match('/height\s*:\s*(\d+)px/', $strip, $sh);
ok(preg_match('/\.container\s*\{([^}]*)\}/', $mobile, $cm) === 1,
   '.container lost its rule in the mobile block, so nothing clears the fixed burger icon');
preg_match('/padding-top\s*:\s*(\d+)px/', isset($cm[1]) ? $cm[1] : '', $cp);
$strip_h = isset($sh[1]) ? (int)$sh[1] : -1;
$pad_t   = isset($cp[1]) ? (int)$cp[1] : -2;
printf("  strip %dpx, clearance padding %dpx\n", $strip_h, $pad_t);
ok($strip_h > 0, 'the clearance strip has no height');
ok($strip_h === $pad_t,
   "the strip ($strip_h px) and the clearance padding ($pad_t px) disagree; the difference renders as a band of wallpaper under the burger");

echo "\nand the chrome it backs still sits on top of it\n";
preg_match('/z-index\s*:\s*(-?\d+)/', $strip, $sz);
$strip_z = isset($sz[1]) ? (int)$sz[1] : 0;
/* A selector can own several blocks here (.navbar has both its own rule and
   a share of ".row, .navbar"), so every block naming it is read and the
   z-index taken from whichever one sets it -- matching only the first block
   reads the one that happens to carry flex-direction and nothing else. */
$zindex_of = function ($sel) use ($mobile) {
	preg_match_all('/([^{}]+)\{([^}]*)\}/', $mobile, $all, PREG_SET_ORDER);
	$z = null;
	foreach ($all as $r) {
		if (strpos($r[1], $sel) === false) continue;
		if (preg_match('/z-index\s*:\s*(-?\d+)/', $r[2], $zz)) $z = (int)$zz[1];
	}
	return $z;
};
foreach (array('#burger-menu', '.navbar') as $sel) {
	$z = $zindex_of($sel);
	ok($z !== null && $z > $strip_z,
	   "$sel does not outrank the clearance strip (z-index " . var_export($z, true) . " vs $strip_z), so the strip can paint over the thing it exists to back");
}

/* Nothing ELSE in the mobile block may be a top-anchored opaque fixed bar
   that in-flow content cannot paint over -- the same shape of mistake. */
echo "\nand nothing else in the mobile block is a top-anchored fixed bar\n";
preg_match_all('/([^{}]+)\{([^}]*)\}/', $mobile, $rules, PREG_SET_ORDER);
foreach ($rules as $r) {
	$sel = trim(preg_replace('/\s+/', ' ', $r[1]));
	$dec = $r[2];
	if (strpos($sel, '@media') !== false || $sel === '') continue;
	if (preg_match('/position\s*:\s*fixed/', $dec) !== 1) continue;
	if (preg_match('/top\s*:\s*0\s*;/', $dec) !== 1) continue;
	if (preg_match('/background(-color)?\s*:/', $dec) !== 1) continue;
	preg_match('/z-index\s*:\s*(-?\d+)/', $dec, $zz);
	$z = isset($zz[1]) ? (int)$zz[1] : 0;
	ok($z < 0, "\"$sel\" is a fixed, opaque, top-anchored bar at z-index $z; in-flow page content paints below that and will be covered while scrolling");
}

echo "\n" . ($fail ? "FAILED ($fail)\n" : "top strip: ok\n");
exit($fail ? 1 : 0);
