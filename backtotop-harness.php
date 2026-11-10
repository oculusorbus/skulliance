<?php
/* backtotop-harness.php — CLI only. Static. No database.
 *
 * REPORTED: on mobile the arrow stalls halfway up the screen and only
 * drops back to the corner when you scroll the other way.
 *
 * THE CAUSE WAS A FILTER ON A FIXED ELEMENT, and that is the single
 * thing this guards. A filtered position:fixed element cannot be kept as
 * a plain composited layer pinned to the viewport -- the filtered region
 * has to be re-rasterised, and mobile browsers do not do that on every
 * frame of a momentum scroll. It was the only such element on the
 * platform; the check below makes sure it stays that way for all of them.
 */
$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$css = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/dist/flexbox.css'));
$tabs = (string) file_get_contents(__DIR__ . '/admin-lib.php');
$js  = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/skulliance.js'));

/* Rules, by brace counting: a regex bounded with [^}]* walks straight out
   of a nested @media block into whatever follows. */
function btt_rules($s) {
	$out = array(); $i = 0; $n = strlen($s);
	while ($i < $n) {
		$j = strpos($s, '{', $i);
		if ($j === false) break;
		$parts = explode('}', substr($s, $i, $j - $i));
		$sel   = trim(array_pop($parts));
		$d = 0; $k = $j;
		for (; $k < $n; $k++) {
			if ($s[$k] === '{') $d++;
			elseif ($s[$k] === '}') { $d--; if (!$d) break; }
		}
		$body = substr($s, $j + 1, $k - $j - 1);
		if (strpos($sel, '@') === 0) $out = array_merge($out, btt_rules($body));
		else $out[] = array($sel, $body);
		$i = $k + 1;
	}
	return $out;
}
$rules = btt_rules($css);
ok(count($rules) > 200, 'the stylesheet could not be parsed, so this check is blind');

echo "no fixed element carries a filter\n";
$bad = array();
foreach ($rules as $r) {
	if (preg_match('~position:\s*fixed~', $r[1])
	 && preg_match('~(^|[;{\s])(-webkit-)?filter:\s*(?!none)~', $r[1])) $bad[] = trim($r[0]);
}
ok(count($bad) === 0,
   'these are position:fixed AND filtered, which is what made the arrow lag behind a '
 . 'momentum scroll: ' . implode(', ', $bad));

echo "\nthe arrow is composited, not repainted\n";
$btt = '';
foreach ($rules as $r) if (trim($r[0]) === '#back-to-top-button') { $btt = $r[1]; break; }
ok($btt !== '', 'the #back-to-top-button rule is gone');
ok(preg_match('~box-shadow:~', $btt) === 1,
   'the arrow lost its shadow entirely; box-shadow is the cheap way to keep it');
ok(preg_match('~transform:\s*translateZ\(0\)~', $btt) === 1,
   'the arrow is not promoted to its own layer, so it is repainted as the page moves');
/* display:none <-> block forces a layout on every scroll event. */
ok(preg_match('~(^|[;{\s])display:\s*none~', $btt) !== 1,
   'the arrow is hidden with display again, which forces a layout on every scroll event');
ok(preg_match('~visibility:\s*hidden~', $btt) === 1 && preg_match('~opacity:\s*0~', $btt) === 1,
   'the arrow is not hidden with visibility and opacity');
/* Anchored on the brace: strpos() for '.show' also matches '.shown', so
   renaming the class passed. */
ok(preg_match('~#back-to-top-button\.show\s*\{~', $css) === 1,
   'there is no shown state, so the arrow can never appear');
ok(preg_match('~classList\.toggle\("show"~', $js) === 1,
   'the stylesheet and the script disagree about the class name');
/* It sat under the home indicator, and on top of the quick-menu bar. */
ok(preg_match('~bottom:\s*calc\([^)]*safe-area-inset-bottom~', $btt) === 1,
   'the arrow ignores the bottom safe area, so it sits under the home indicator');
ok(preg_match('~display-mode:\s*standalone~', $css) === 1
   && preg_match('~display-mode:\s*standalone[^@]*#back-to-top-button~s', $css) === 1,
   'the arrow does not move for the quick-menu bar, so in the installed app it sits on top of it');

echo "\nthe scroll handler cannot be clobbered, and does no layout\n";
ok(strpos($js, 'window.onscroll') === false,
   'window.onscroll is back; it is a single slot, so the next script to want one silently replaces this');
/* [\s\S]*? and not [^;]*: the callback body contains a semicolon, so the
   first version could never reach the options object. */
ok(preg_match('~addEventListener\("scroll",[\s\S]*?\{ passive: true \}\)~', $js) === 1,
   'the scroll listener is not passive, so it runs before the browser may scroll');
ok(strpos($js, 'requestAnimationFrame(apply)') !== false,
   'the handler is not frame-throttled, so it runs work on every scroll event');
ok(preg_match('~btn\.classList\.toggle\("show"~', $js) === 1,
   'the handler no longer toggles the class the stylesheet is built around');
ok(preg_match('~style\.display\s*=~', substr($js, strpos($js, 'back-to-top-button'), 900)) !== 1,
   'the handler sets display again');
/* A page restored mid-scroll gets no scroll event. */
$blk = substr($js, strpos($js, 'back-to-top-button'));
$blk = substr($blk, 0, strpos($blk, '})();'));
ok(substr_count($blk, 'apply();') >= 1, 'the state is never applied on load, so a restored page shows no arrow');
/* The inline onclick in header.php needs this name at global scope. */
ok(preg_match('~^function topFunction\(\)~m', $js) === 1,
   'topFunction() is no longer global, so the inline onclick in header.php throws');
ok(strpos((string) file_get_contents(__DIR__ . '/header.php'), 'onclick="topFunction()"') !== false,
   'the button no longer calls topFunction()');

echo "\nthe pages that hide it still can\n";
/* Three pages hide it outright and realms pushes it behind the map. All
   four use declarations that outrank the base rule; if the base rule ever
   starts using !important they stop working. */
ok(strpos($btt, '!important') === false,
   'the base rule uses !important, which breaks the pages that hide the arrow');
foreach (array('skullracer.php', 'guardians.php', 'obscura.php') as $f) {
	ok(strpos((string) file_get_contents(__DIR__ . '/' . $f), '#back-to-top-button') !== false,
	   "$f no longer hides the arrow");
}


echo "\nthe arrow is pinned to the viewport you can actually see\n";
/*
 * REPORTED TWICE. The drop-shadow was real and is gone -- at 390px wide,
 * 48 frames of continuous scroll hold the gap at exactly 10px -- but a
 * desktop browser has one viewport and a phone has two.
 *
 * position:fixed resolves against the LAYOUT viewport, which mobile
 * browsers size to the window with the URL bar HIDDEN. While the bar is
 * showing, or sliding, what you can see is shorter than that, so
 * `bottom: 10px` is measured from an edge somewhere under the bar and
 * the button hovers above the corner. Scrolling down hides the bar and
 * it settles; scrolling up brings it back and it lifts. visualViewport
 * is the only thing that reports the viewport being looked at.
 */
ok(strpos($js, 'window.visualViewport') !== false,
   'nothing reads visualViewport, so the arrow is still pinned to an edge the phone cannot show');
ok(preg_match('~--btt-lift~', $js) === 1, 'the gap between the two viewports is never published');
ok(preg_match('~--btt-lift~', $css) === 1, 'the stylesheet never consumes the gap');
/* [\s\S]*? and not [^)]*: the offset already contains env(...), whose
   closing paren ended the window before it could reach the var. This is
   the third time a negated-character window has cost me an assertion in
   this file; the lesson is to bound by what comes after, not by what
   must not appear in between. */
ok(preg_match('~bottom:\s*calc\([\s\S]*?var\(--btt-lift, 0px\)~', $btt) === 1,
   "the arrow's offset does not include the viewport gap");
/* Both events: resize is the bar appearing or going, scroll is pinch-zoom
   moving the visual viewport inside the layout one. */
foreach (array('resize', 'scroll') as $ev) {
	ok(preg_match('~vv\.addEventListener\("' . $ev . '", pinSoon\)~', $js) === 1,
	   "the arrow does not re-pin on visualViewport $ev");
}
ok(preg_match('~requestAnimationFrame\(pin\)~', $js) === 1,
   'the pin is not frame-throttled, and visualViewport scroll fires continuously');
/* The fallback has to be zero, not a guess: a browser without the API
   must behave exactly as it did before. */
ok(preg_match('~var\(--btt-lift, 0px\)~', $css) === 1,
   'the fallback for a browser with no visualViewport is not zero');
ok(preg_match('~if \(!vv\) return;~', $js) === 1,
   'a browser without visualViewport throws instead of keeping the old behaviour');
/* It must never push the arrow DOWN, off the screen. */
ok(preg_match('~if \(!\(lift > 0\)\) lift = 0;~', $js) === 1,
   'a negative gap is not clamped, so the arrow can be pushed below the screen');
/* The installed-app offset has to carry it too, or the arrow is right
   everywhere except in the PWA. */
ok(preg_match('~display-mode:\s*standalone[^@]*--btt-lift~s', $css) === 1,
   'the quick-menu offset ignores the viewport gap, so the arrow is wrong in the installed app');

echo "\nand there is a way to see it from the phone\n";
/*
 * The owner is usually on a phone and cannot run anything, and this is a
 * bug that only exists there -- so the measurement has to be a page.
 */
$vp = (string) @file_get_contents(__DIR__ . '/admin-viewport.php');
ok($vp !== '', 'the viewport diagnostic is gone');
ok(strpos($vp, 'admin_require()') !== false, 'the diagnostic is not behind admin_require()');
/* THE MECHANISM, not the word. Both of these appear in the page's own
   prose and markup, so a search for either passed with the code that
   does the work renamed. */
ok(preg_match('~visualViewport\.addEventListener\(~', $vp) === 1,
   'the diagnostic never re-reads when the viewport changes, which is the only moment it is about');
ok(preg_match('~Math\.abs\(ag - 10\) > Math\.abs\(worst - 10\)~', $vp) === 1,
   'the diagnostic shows only the current frame, so a stall you scrolled past leaves no trace');
ok(preg_match('~back-to-top-button~', $vp) === 1,
   'the diagnostic measures the viewport but not the arrow it is about');
ok(strpos($tabs, "'viewport' => array('admin-viewport.php'") !== false,
   'there is no admin tab, so the diagnostic cannot be reached from a phone');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
