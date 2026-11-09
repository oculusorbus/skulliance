<?php
/* missions-chest-harness.php — CLI only. Static. No database.
 *
 * THE CHEST IS DECORATION OVER A CONTROL THAT ALREADY WORKED.
 *
 * Claiming the daily reward is #claimRewardButton calling dailyReward().
 * That button is what is focusable, what a screen reader announces, and
 * what skulliance.js knows about. The chest forwards a click to it. The
 * failure worth testing is the one where that stops being true: a chest
 * that claims on its own, or that replaces the button, strands anybody
 * who cannot click a picture of a box.
 *
 * AND THE HOOK IS OPTIONAL BY CONSTRUCTION. dailyReward() must check for
 * SK_CHEST_OPEN before calling it, exactly as it does for DHC_SHOW_DROP,
 * or every page that claims without this partial throws instead.
 */
$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$chest = (string) file_get_contents(__DIR__ . '/missions-chest.php');
$daily = (string) file_get_contents(__DIR__ . '/missions-daily.php');
$js    = (string) file_get_contents(__DIR__ . '/skulliance.js');
$strip = function ($s) {
	$s = preg_replace('~/\*.*?\*/~s', '', $s);
	return preg_replace('~^\s*//.*$~m', '', $s);
};
$chestCode = $strip($chest);
$jsCode    = $strip($js);

echo "the button still does the claiming\n";
ok(strpos($chestCode, 'dailyReward(') === false,
   'the chest calls dailyReward() itself, so there are two claim paths');
ok(strpos($chestCode, "getElementById('claimRewardButton')") !== false,
   'the chest does not find the real button, so it cannot forward to it');
ok(strpos($chestCode, 'btn.click()') !== false,
   'the chest does not forward its click to the button');
ok(strpos($chestCode, 'if (btn.disabled) return;') !== false,
   'the chest forwards a click to a disabled button, which is the second-claim guard');
ok(strpos($chestCode, 'aria-hidden="true"') !== false,
   'the chest is not aria-hidden, so a screen reader meets a second nameless control');
ok(strpos($daily, 'claimRewardButton') !== false,
   'the claim button has gone from the strip, leaving only the picture');

echo "\nthe hook is optional, like the one beside it\n";
ok(strpos($jsCode, 'if (window.SK_CHEST_OPEN) SK_CHEST_OPEN();') !== false,
   'dailyReward() calls the chest hook without checking it exists');
$at   = strpos($jsCode, 'SK_CHEST_OPEN');
$rew  = strpos($jsCode, "getElementById('reward').style.opacity");
ok($at !== false && $rew !== false && $at < $rew,
   'the chest opens after the reward line is written, so the art lags the numbers');
/* The already-claimed reply returns null and must not fire a burst. */
$nullBranch = substr($jsCode, strpos($jsCode, 'if(obj == null)'));
$nullBranch = substr($nullBranch, 0, strpos($nullBranch, '}else{'));
ok(strpos($nullBranch, 'SK_CHEST_OPEN') === false,
   'an already-claimed reply still bursts the chest');
ok(strpos($chestCode, 'window.SK_CHEST_OPEN = function') !== false,
   'the partial never defines the hook it is called through');
/* INSIDE THE HOOK, not anywhere in the file: the same test appears in the
   click binding, so a plain search passes with the guard deleted. */
$hook = substr($chestCode, strpos($chestCode, 'window.SK_CHEST_OPEN = function'));
$hook = substr($hook, 0, strpos($hook, '};'));
ok(strpos($hook, "el.classList.contains('is-open')") !== false,
   'the hook does not guard against opening twice');
ok(strpos($hook, "add('is-open'") !== false, 'the hook never opens the chest');

echo "\nthree layers, because the light has to move on its own\n";
foreach (array('closed.webp', 'open.webp', 'glow.webp') as $f) {
	ok(strpos($chest, $f) !== false, "the chest does not reference $f");
}
ok(substr_count($chestCode, 'ms-chest-l') >= 4,
   'the three art layers are not all present');
/* The RESTING state specifically. A bare `.ms-chest-glow {` also matches
   the tail of `.ms-chest.is-open .ms-chest-glow {`, so dropping the start
   scale and keeping the end one passed. */
ok(preg_match('~(^|\s)\.ms-chest-glow\s*\{[^}]*transform:\s*scale\(\.?7~m', $chest) === 1,
   'the glow has no starting scale, so the burst is a fade rather than an ignition');
/* In the MAIN stylesheet, not the reduced-motion block: that one sets
   scale(1) too, so an unscoped search passes with the real transition
   deleted. */
$mainCss = substr($chest, 0, strpos($chest, '@media (prefers-reduced-motion'));
ok(preg_match('~\.is-open \.ms-chest-glow\s*\{[^}]*transform:\s*scale\(1\)~s', $mainCss) === 1,
   'the glow never scales up to rest, so it stays shrunken when open');
/*
 * Only transform and opacity animate: anything else is a layout thrash on
 * a page that already does a lot.
 *
 * The block is extracted by counting braces. A regex bounded with [^@]*
 * runs straight past the closing brace into whatever rule follows, which
 * is how the first version of this reported `left: 50%` on .ms-chest-flash
 * as a keyframe property.
 */
$bad = array();
$pos = 0;
while (($k = strpos($chest, '@keyframes', $pos)) !== false) {
	$open = strpos($chest, '{', $k);
	$d = 0; $end = $open;
	for ($i = $open; $i < strlen($chest); $i++) {
		if ($chest[$i] === '{') $d++;
		elseif ($chest[$i] === '}') { $d--; if (!$d) { $end = $i; break; } }
	}
	$body = substr($chest, $open, $end - $open);
	$name = trim(substr($chest, $k + 10, $open - $k - 10));
	if (preg_match('~(^|[;{\s])(width|height|top|left|right|bottom|margin|padding)\s*:~', $body)) {
		$bad[] = $name;
	}
	$pos = $end + 1;
}
ok(count($bad) === 0,
   'these keyframes animate layout properties instead of transform/opacity: ' . implode(', ', $bad));
ok($pos > 0, 'no @keyframes were found at all, so the extraction is reading the wrong file');

echo "\nthe burst leaves along the mouth, which is not horizontal\n";
/*
 * The chest is drawn in three-quarter view, so its front rim rises to
 * the right. Six sparks sharing one origin and fanning sideways read as
 * a horizontal line crossing a tilted object -- and the origin they
 * shared, 56% down, was the middle of the skull rather than the
 * opening, so they came out of the front panel.
 */
preg_match_all('~\.ms-chest-spark\.s(\d)\s*\{[^}]*left:\s*([\d.]+)%[^}]*top:\s*([\d.]+)%~', $chest, $sp, PREG_SET_ORDER);
ok(count($sp) >= 6, 'the sparks do not each have their own origin (' . count($sp) . ' found)');
$lefts = array_map(function ($m) { return (float) $m[2]; }, $sp);
$tops  = array_map(function ($m) { return (float) $m[3]; }, $sp);
ok(count(array_unique($tops)) === count($tops),
   'two sparks start at the same height, so they are not spread along the rim');
$descending = true;
for ($i = 1; $i < count($sp); $i++) if ($tops[$i] >= $tops[$i-1]) $descending = false;
ok($descending,
   'the spark origins do not rise to the right, so the burst ignores the angle of the chest');
ok(min($lefts) > 20 && max($lefts) < 80,
   'the sparks start outside the opening (' . min($lefts) . '% to ' . max($lefts) . '%)');
/* Spread ALONG the rim, not stacked on one point: distinct, ordered,
   and covering a real span of it. */
ok(count(array_unique($lefts)) === count($lefts),
   'two sparks share a horizontal origin, so they are not spread along the rim');
$ordered = true;
for ($i = 1; $i < count($lefts); $i++) if ($lefts[$i] <= $lefts[$i-1]) $ordered = false;
ok($ordered, 'the spark origins are not ordered across the rim');
ok(max($lefts) - min($lefts) > 25,
   'the sparks only cover ' . (max($lefts) - min($lefts)) . '% of the opening, so they read as one point');
/* 56% was the skull. The mouth sits around 45-50%. */
ok(max($tops) < 52 && min($tops) > 40,
   'the sparks start off the opening, at ' . min($tops) . '% to ' . max($tops) . '% down');
ok(preg_match('~\.ms-chest-flash\s*\{[^}]*top:\s*4[0-9](\.\d+)?%~s', $chest) === 1,
   'the flash is not centred on the mouth of the chest');
/* The rise is in px, so it must track the rendered size or it is half a
   chest on mobile and a third of one on the preview. */
ok(strpos($chest, 'var(--mcrise)') !== false,
   'the spark rise is hardcoded, so it does not scale with the chest');
ok(preg_match('~\.ms-chest\s*\{[^}]*--mcrise~s', $chest) === 1,
   '--mcrise has no default, so the sparks do not travel at all');
ok(preg_match('~max-width:\s*760px[^}]*--mcrise~s', $chest) === 1,
   'the mobile size does not scale the spark rise');
ok(strpos((string) file_get_contents(__DIR__ . '/admin-chest.php'), '--mcrise') !== false,
   'the preview renders the chest larger without scaling the spark rise');

echo "\nreduced motion keeps the feedback and drops the movement\n";
ok(strpos($chest, 'prefers-reduced-motion') !== false,
   'the chest ignores prefers-reduced-motion');
$rm = substr($chest, strpos($chest, 'prefers-reduced-motion'));
$rm = substr($rm, 0, strpos($rm, '</style>'));
ok(strpos($rm, 'animation: none') !== false, 'reduced motion does not stop the animations');
ok(strpos($rm, '.ms-chest-spark { display: none') !== false
   || strpos($rm, '.ms-chest-flash, .ms-chest-spark { display: none') !== false,
   'reduced motion still fires the sparks');
ok(preg_match('~prefers-reduced-motion.*\.ms-chest\.is-open \.ms-chest-glow~s', $chest) === 1,
   'reduced motion also removes the glow, so a claim gives no visible feedback at all');

echo "\nit is included once, and in both states\n";
ok(substr_count($daily, "include __DIR__ . '/missions-chest.php'") === 1,
   'the chest partial is included more than once, or not at all');
$inc = strpos($daily, 'missions-chest.php');
$br  = strpos($daily, "if (\$ms_daily['eligible']): ?>", strpos($daily, 'ms-daily-right'));
ok($inc !== false && $br !== false && $inc < $br,
   'the chest is inside the eligible branch, so a claimed day shows no chest');
ok(strpos($chestCode, "defined('MS_CHEST_CSS')") !== false,
   'the stylesheet is not guarded, so a second include duplicates it');
ok(strpos($chestCode, 'images/chest/') !== false && strpos($chestCode, 'https://') === false,
   'the art is referenced absolutely; cookies here are host-only, so paths stay relative');

echo "\nthe admin preview shows the real thing and claims nothing\n";
/*
 * The animation fires once a day for a player with a claimable day, so
 * without a preview nobody can look at it twice. The two things that
 * matter about that preview: it must include the REAL partial, because
 * a second copy of the markup drifts from what players see, and it must
 * not be able to claim.
 */
$adm = (string) file_get_contents(__DIR__ . '/admin-chest.php');
$admCode = $strip($adm);
ok(strpos($admCode, "include __DIR__ . '/missions-chest.php'") !== false,
   'the preview does not include the real partial, so it can drift from what ships');
ok(strpos($admCode, 'claimRewardButton') === false,
   'the preview carries a claim button, so looking at the animation can spend a reward');
ok(strpos($admCode, 'dailyReward') === false,
   'the preview can reach dailyReward(), so it can claim');
ok(strpos($admCode, 'admin_require()') !== false,
   'the preview is not behind admin_require()');
$req = strpos($admCode, 'admin_require()');
$hdr = strpos($admCode, "include 'header.php'");
ok($req !== false && $hdr !== false && $req < $hdr,
   'admin_require() runs after header.php, which is after output has begun');
ok(strpos($admCode, 'SK_CHEST_OPEN()') !== false,
   'the preview never calls the hook, so the button does nothing');
ok(strpos($admCode, "classList.remove('is-open', 'is-bursting', 'is-settled')") !== false,
   'the preview cannot reset, so it can only be watched once per reload');
/* The art arrives by FTP, so a fresh deploy has markup and no pictures. */
ok(strpos($admCode, "addEventListener('error'") !== false,
   'the preview shows broken frames rather than saying which files are missing');
$tabs = (string) file_get_contents(__DIR__ . '/admin-lib.php');
ok(strpos($tabs, "'chest' => array('admin-chest.php'") !== false,
   'there is no admin tab, so the preview cannot be reached');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
