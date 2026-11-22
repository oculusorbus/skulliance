<?php
/* monstrocity-battlefield-harness.php — CLI only. Static. No database.
 *
 *     php monstrocity-battlefield-harness.php
 *
 * THE BATTLEFIELD WAS FOUR NUDGES, NOT A LAYOUT. .character, .log and
 * .legend each carried `position:relative; top:-225px` -- four separate
 * shoves that happened to line up at one window size. Nothing was aligned
 * to anything, which is why the columns drifted and why no amount of
 * spacing tuning held it together. That is the thing most likely to come
 * back, because the shove LOOKS right in a diff and only reveals itself
 * at a different width.
 *
 * The rest is the HUD a match-3 battler needs and did not have: the HP
 * figure on the bar rather than four rows down a stats table, a damage
 * trail that shows how far the bar just moved, and a number over the
 * portrait so a 12 and a 90 are different events.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$src = (string) file_get_contents(__DIR__ . '/monstrocity.php');
$css = '';
if (preg_match_all('~<style>(.*?)</style>~s', $src, $m)) $css = implode("\n", $m[1]);

echo "the battlefield is laid out, not shoved\n";
/* The ORIGINAL rules still carry top:-225px; what matters is that the
   later block neutralises every one of them. Counted, because three
   selectors used it and overriding two of three is the bug. */
ok(preg_match('~\.character\s*\{[^}]*top:\s*auto\s*!important~s', $css) === 1,
   '.character no longer cancels its -225px shove');
ok(preg_match('~\.log,\s*\.legend\s*\{[^}]*top:\s*auto\s*!important~s', $css) === 1,
   '.log and .legend no longer cancel their -225px shove');
ok(strpos($css, 'display: flex !important') !== false
   && preg_match('~\.battlefield\s*\{[^}]*display:\s*flex~s', $css) === 1,
   'the battlefield is not laid out as a flex row');
/* A grid was tried first and could not contain the board, whose width is
   set by JS from the viewport -- it overlapped both cards. */
ok(preg_match('~\.battlefield\s*\{[^}]*grid-template-columns~s', $css) !== 1,
   'the battlefield is back on a grid, which cannot size the JS-sized board');
/* THE CONTAINING BLOCK IS LOAD BEARING. position:static was tried and it
   broke the damage numbers: .dmg-float is absolute, and with no positioned
   ancestor it escaped the card and floated over the board. */
ok(preg_match('~\.character\s*\{[^}]*position:\s*relative\s*!important~s', $css) === 1,
   '.character is not a positioned ancestor, so the damage numbers escape it');
ok(strpos($src, 'class="m3-under"') !== false,
   'the log and legend are no longer wrapped in a row of their own');

echo "\nthe health bar carries the figure and the damage\n";
foreach (array('1', '2') as $n) {
	ok(strpos($src, 'id="p' . $n . '-trail"') !== false, "player $n has no damage trail");
	ok(strpos($src, 'id="p' . $n . '-hpnum"') !== false, "player $n has no figure on the bar");
}
ok(strpos($src, "getElementById(side + '-hpnum')") !== false,
   'updateHealth() does not write the figure onto the bar');
ok(strpos($src, "getElementById(side + '-trail')") !== false,
   'updateHealth() does not drive the trail, so the bar shows no damage');
/* A HEAL MUST JUMP, NOT LAG. A trail easing upward behind a rising fill
   shows a red strip for damage that never happened. */
/* THE WHOLE CONDITION, not a tail of it: `false && percentage > prev`
   still contains "percentage > prev", so a disabled branch passed. */
ok(strpos($src, 'if (!isNaN(prev) && percentage > prev) {') !== false
   && preg_match('~percentage > prev\) \{[\s\S]{0,160}transition = .none.~', $src) === 1,
   'a heal eases the trail upward, which paints damage that did not happen');
/* The stats table still has Health; it is now the duplicate. */
ok(strpos($css, '.character tr.hp-row { display: none; }') !== false
   && strpos($src, 'class="hp-row"') !== false,
   'the Health row is still shown twice, on the bar and in the table');

echo "\na hit looks like a hit\n";
ok(strpos($src, 'floatDamage(') !== false && strpos($src, 'flashHit(') !== false,
   'a hit is still only a sideways shift');
ok(strpos($src, "this.flashHit(defender, totalDamage)") !== false
   && strpos($src, "this.floatDamage(defender, totalDamage)") !== false,
   'animateRecoil() does not call them, so neither ever fires');
/* Removed on animationend, not on a timer that has to agree with the CSS:
   two durations drifting apart leaves these stacking up on the card. */
ok(strpos($src, "addEventListener('animationend', () => el.remove())") !== false,
   'the damage numbers are removed on a timer, which will drift from the CSS');
/* The portrait is measured rather than guessed, and a broken image does
   not count as a portrait. */
ok(strpos($src, 'face.offsetHeight > 40') !== false,
   'the number is placed without checking the portrait actually loaded');

echo "\nthe dead transition stays dead\n";
/* `transition: transform 0.1slinear, filter 0.5sease` -- missing spaces,
   so the whole declaration was invalid and filter never transitioned. */
ok(strpos($css, '0.1slinear') === false && strpos($css, '0.5sease') === false,
   'the malformed transition is back; the declaration is invalid and filter never animates');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all battlefield checks passed\n");
exit($fail ? 1 : 0);
