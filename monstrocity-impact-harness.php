<?php
/* monstrocity-impact-harness.php — CLI only. Static. No database.
 *
 *     php monstrocity-impact-harness.php
 *
 * THE IMPACT LAYER, AND THE PROMISE THAT IT MOVES NOTHING.
 *
 * These effects shipped once inside a battlefield rework that also
 * rebuilt the layout, and the whole thing had to be reverted. They came
 * back on their own, on one condition: nothing here reflows. The narrow
 * layout is tuned against hard-coded offsets -- the card is
 * min-height:552px at top:-225px and #game-over-container sits at
 * top:317px against it -- so a rule that changes a card's height moves
 * furniture three rules away.
 *
 * The centrepiece is the selector allowlist. "No layout properties" is
 * not checkable in general, but "this block styles these things and
 * nothing else" is, and it makes adding a selector a decision somebody
 * has to make on purpose.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$src = (string) file_get_contents(__DIR__ . '/monstrocity.php');
$a   = strpos($src, 'THE IMPACT LAYER. Isolated block');
ok($a !== false, 'the impact block is gone, or lost its marker');
$blk = $a === false ? '' : substr($src, $a, strpos($src, '</style>', $a) - $a);
/* FROM THE <style> TAG, not from the marker: the marker sits in an HTML
   comment above it, and parsing from there read the whole comment as a
   selector. */
$sty = $blk === '' ? false : strpos($blk, '<style>');
ok($sty !== false, 'the impact block has no stylesheet');
$blk = $sty === false ? '' : substr($blk, $sty + 7);
/* Declarations only: the block's prose names the selectors and offsets
   these checks search for, so a raw search reads the comments. */
$code = preg_replace('~/\*.*?\*/~s', '', $blk);
/* And the same for the page, for the checks that scan all of it. */
$srcCode = preg_replace('~/\*.*?\*/~s', '', $src);

echo "it styles these things and nothing else\n";
/* Every selector in the block, normalised. A new one fails here on
   purpose -- that is the whole guard. */
$want = array(
	'.dmg-float', '.dmg-float.heal', '.dmg-float.big',
	'.character img.hit-flash, .character video.hit-flash',
	'.character.struck',
);
$got = array();
foreach (preg_split('~\}~', $code) as $chunk) {
	if (strpos($chunk, '{') === false) continue;
	$sel = trim(preg_replace('~\s+~', ' ', substr($chunk, 0, strpos($chunk, '{'))));
	/* keyframe stops (0%, 100%, 25%) and the at-rules that hold them */
	if ($sel === '' || $sel[0] === '@' || preg_match('~^[\d%,\s.]+$~', $sel)) continue;
	$got[] = $sel;
}
$extra = array_values(array_diff(array_unique($got), $want));
ok(count($extra) === 0,
   'the impact block styles something new: ' . implode(' / ', $extra)
   . ' -- if it is in flow it can move the layout, which is what got this reverted');
foreach ($want as $w)
	ok(in_array($w, $got, true), "the rule for $w is gone");

echo "\nand none of it can reflow\n";
/* Out of flow, so top/left/font-size on it are free. */
ok(preg_match('~\.dmg-float\s*\{[^}]*position:\s*absolute~s', $code) === 1,
   'the damage number is not absolute, so it pushes the card around');
ok(preg_match('~\.dmg-float\s*\{[^}]*pointer-events:\s*none~s', $code) === 1,
   'the damage number swallows taps on the card underneath it');
/* The two card-level effects may only animate transform and filter.
   A keyframe that touched width, margin or font-size would reflow. */
foreach (array('cardStruck' => 'transform', 'hitFlash' => 'filter') as $kf => $prop) {
	ok(preg_match('~@keyframes ' . $kf . '\s*\{(.*?)\n\}~s', $code, $m) === 1,
	   "@keyframes $kf is gone");
	$props = array();
	if (isset($m[1])) { preg_match_all('~([a-z-]+)\s*:~', $m[1], $pm); $props = array_unique($pm[1]); }
	ok($props === array($prop),
	   "@keyframes $kf animates " . implode(', ', $props) . " -- only $prop cannot reflow");
}
/* .character gets no box properties here. Its height is what the narrow
   layout's fixed offsets are measured against. */
ok(preg_match('~\.character\.struck\s*\{[^}]*(width|height|margin|padding|top|font|line-height)[a-z-]*\s*:~s', $code) !== 1,
   'the struck rule sets a box property on the card, which moves the layout');
/* And the block may not reach the pieces the reverted version moved. */
foreach (array('.battlefield', '.m3-under', '.turn-indicator', '.log',
               'flex-direction', 'grid-template') as $banned)
	ok(strpos($code, $banned) === false,
	   "the impact block contains $banned -- it is reaching outside the effects again");

echo "\nthe flash keeps the portrait's shadow\n";
/*
 * filter is ONE property: a keyframe that ends at `none` drops the
 * base rule's drop-shadow for the length of the flash and snaps it
 * back, which reads as the card flickering rather than being hit.
 */
ok(substr_count($code, 'drop-shadow(2px 5px 10px #000)') === 2,
   'the hit flash does not restate the portrait drop-shadow in both frames, so the shadow blinks');
ok(preg_match('~@keyframes hitFlash\s*\{[^}]*filter:\s*none~s', $code) !== 1,
   'the flash animates filter to none, dropping the drop-shadow with it');

echo "\nthe effects are actually fired\n";
ok(preg_match('~glow-recoil., 200\);\s*(/\*.*?\*/\s*)?this\.flashHit\(defender, totalDamage\);~s', $src) === 1,
   'the flash is not fired from the recoil, so a hit still looks like a nudge');
ok(strpos($src, 'this.floatDamage(defender, totalDamage);') !== false,
   'the damage number is never shown');
/* Re-adding a class already on the element does NOT restart a CSS
   animation, so without the forced reflow the second hit does nothing. */
ok(substr_count($src, 'void img.offsetWidth;') === 1
   && substr_count($src, 'void card.offsetWidth;') === 1,
   'the animation restart reflow is missing, so only the first hit animates');
/* On animationend, not a timer: two durations drift apart and the
   numbers stack up on the card for the rest of the match. */
ok(preg_match("~addEventListener\('animationend', \(\) => el\.remove\(\)\)~", $src) === 1,
   'the damage number is not removed on animationend, so they accumulate');
ok(strpos($src, "el.className = 'dmg-float' + (kind === 'heal' ? ' heal' : '') + (n >= 25 ? ' big' : '')") !== false,
   'the number no longer distinguishes a heal or a big hit');
/* Measured from the real change, because the restore is clamped at
   maxHealth: a 10-point Heal at 4 below full is a +4 on screen. */
ok(strpos($src, 'const healthBefore = player.health;') !== false
   && strpos($src, "this.floatDamage(player, player.health - healthBefore, 'heal');") !== false,
   'the heal number is not measured from the actual change, so it can over-report');
/* The portrait is measured rather than guessed, and only when it is
   really there -- a broken image still reports the placeholder height. */
ok(strpos($src, 'face.offsetHeight > 40') !== false,
   'the number is placed without checking the portrait loaded, so it lands beside the name');

echo "\nand the dead transition stays fixed\n";
/*
 * `transition: transform 0.1slinear, filter 0.5sease` -- no space
 * between time and easing makes the WHOLE declaration invalid, so
 * neither property transitioned and the hit glows snapped on and off.
 */
ok(preg_match('~\d(s|ms)(linear|ease|cubic)~', $srcCode) !== 1,
   'a transition time is glued to its easing again, which voids the whole declaration');
ok(strpos($src, 'transition: transform 0.1s linear, filter 0.5s ease;') !== false,
   'the portrait transition is gone, so the hit glows snap instead of fading');

echo "\na reduced-motion preference is honoured\n";
ok(strpos($blk, 'prefers-reduced-motion') !== false,
   'the shake and the flying number ignore a reduced-motion preference');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all impact checks passed\n");
exit($fail ? 1 : 0);
