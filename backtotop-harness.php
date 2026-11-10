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

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
