<?php
/* monstrocity-healthbar-harness.php — CLI only. Static. No database.
 *
 *     php monstrocity-healthbar-harness.php
 *
 * THE BAR, AND ONLY THE BAR.
 *
 * A wider battlefield rework shipped and had to be reverted: it moved the
 * layout, and the layout is a tuned system -- .character, .log and .legend
 * carry `top:-225px`, the board carries its own, the turn indicator is an
 * absolute pill because the board paints over its flow position, and
 * #game-over-container is placed at a fixed 317px against all of it. The
 * health bar was the one piece worth keeping, so it came back on its own.
 *
 * MOST OF WHAT THIS CHECKS IS WHAT THE BLOCK MUST NOT DO. The failure was
 * not a bad bar, it was a good bar arriving with layout attached.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$src = (string) file_get_contents(__DIR__ . '/monstrocity.php');

/* The isolated block, by its own marker -- not the whole page, or these
   checks would read the original CSS and pass on everything. */
$a = strpos($src, 'THE HEALTH BAR. Isolated block');
ok($a !== false, 'the health bar block is gone, or lost its marker');
$blk = $a === false ? '' : substr($src, $a, strpos($src, '</style>', $a) - $a);

echo "it is a bar, not a layout\n";
/* EVERY ONE OF THESE IS A THING THE REVERTED VERSION DID. */
foreach (array('.battlefield', '.m3-under', '.turn-indicator', '.dmg-float',
               '.log', '.legend', 'flex-direction', 'grid-template-columns') as $banned) {
	ok(strpos($blk, $banned) === false,
	   "the health bar block contains $banned -- it is reaching outside the bar again");
}
/* .character may not be touched at all: its position and height are what
   the narrow layout's fixed offsets are measured against. */
ok(preg_match('~\.character\s*[,{]~', $blk) !== 1,
   'the block styles .character, whose geometry the narrow layout is tuned against');

echo "\nand it changes no geometry\n";
/* 20px is the original height. A taller bar makes a taller card, and
   #game-over-container is positioned at a hard-coded 317px below it. */
ok(preg_match('~\.health-bar\s*\{[^}]*height\s*:~s', $blk) !== 1,
   'the block sets a height on .health-bar, which moves every fixed offset below it');
/* The duplicate Health row stays. Hiding it is tidier and also changes
   the card height, which is the same mistake in the other direction. */
ok(strpos($blk, 'hp-row') === false && strpos($src, 'class="hp-row"') === false,
   'the Health table row is being hidden, which shortens the card');
/* The children need a containing block, and that is the only positioning
   this is allowed to do. */
ok(preg_match('~\.health-bar\s*\{[^}]*position:\s*relative~s', $blk) === 1,
   '.health-bar is not a containing block, so the fill and the figure escape it');
/* background-IMAGE only: the skin block's background-color still shows
   through and keeps its own value. */
ok(preg_match('~\.health-bar\s*\{[^}]*background-image:~s', $blk) === 1
   && preg_match('~\.health-bar\s*\{[^}]*background-color:~s', $blk) !== 1,
   'the block sets a background-color and overwrites the skin block\'s own');
/* And .health keeps the skin's teal glow, so no box-shadow here. */
ok(preg_match('~\.health\s*\{[^}]*box-shadow:~s', $blk) !== 1,
   '.health sets box-shadow, which drops the skin block\'s glow');

echo "\nthe bar shows the figure and the damage\n";
foreach (array('1', '2') as $n) {
	ok(strpos($src, 'id="p' . $n . '-trail"') !== false, "player $n has no damage trail");
	ok(strpos($src, 'id="p' . $n . '-hpnum"') !== false, "player $n has no figure on the bar");
}
ok(strpos($src, "getElementById(side + '-hpnum')") !== false,
   'updateHealth() does not write the figure onto the bar');
ok(strpos($src, "getElementById(side + '-trail')") !== false,
   'updateHealth() does not drive the trail, so the bar shows no damage');
/* A heal must JUMP. A trail easing upward behind a rising fill paints a
   red strip for damage that never happened. Anchored on the whole
   condition: a tail of it survives the branch being disabled. */
ok(strpos($src, 'if (!isNaN(prev) && percentage > prev) {') !== false
   && preg_match('~percentage > prev\) \{[\s\S]{0,160}transition = .none.~', $src) === 1,
   'a heal eases the trail upward, which paints damage that did not happen');
/* AND IT HAS TO LAG. With no transition the trail snaps to the new width
   in the same frame as the fill and no red is ever visible -- the bar
   still passes every other check here and shows no damage at all. The
   delay is what makes the fill move first and the trail follow it. */
ok(preg_match('~\.health-trail\s*\{[^}]*transition:\s*width\s+[.\d]+s[^;]*\s[.\d]+s\s*;~s', $blk) === 1,
   'the trail does not ease behind the fill, so no damage is ever visible');
/* The trail sits BEHIND the fill, or there is nothing to see. */
ok(preg_match('~\.health-trail\s*\{[^}]*z-index:\s*1~s', $blk) === 1
   && preg_match('~\.health\s*\{[^}]*z-index:\s*2~s', $blk) === 1
   && preg_match('~\.hp-num\s*\{[^}]*z-index:\s*3~s', $blk) === 1,
   'the three layers are not stacked trail / fill / figure');

echo "\nand the figure stays readable at every fill colour\n";
/*
 * REPORTED: white on the green read badly, and on the yellow it was
 * worse. The fill runs #00c8a0 -> #ffc800 -> #ff9632 -> #ff5c5c, where
 * white measures 2.2 / 1.6 / 2.2 / 3.0 : 1 -- the yellow is effectively
 * invisible -- while the rail behind it is #525F65, where near-black
 * would be just as unreadable. So there are two copies of the figure,
 * each clipped to the stretch it reads against.
 */
foreach (array('1', '2') as $n) {
	ok(preg_match('~id="p' . $n . '-hpnum"><span class="hp-ink hp-ink-on"></span>'
	              . '<span class="hp-ink hp-ink-off"></span>~', $src) === 1,
	   "player $n does not have both ink layers, so one fill range is unreadable");
}
/* Dark over the fill, white over the rail -- and NOT the other way
   round, which is the bug this replaced. */
ok(preg_match('~\.hp-ink-on\s*\{[^}]*color:\s*#0[0-9a-f]{5}~s', $blk) === 1,
   'the ink over the fill is not dark, so it is unreadable on the yellow again');
ok(preg_match('~\.hp-ink-off\s*\{[^}]*color:\s*#fff~s', $blk) === 1,
   'the ink over the empty rail is not white');
/* The two clips must be COMPLEMENTARY: one hides what the other shows.
   If both measure from the same side the figure doubles or vanishes. */
ok(preg_match('~\.hp-ink-on\s*\{[^}]*clip-path:\s*inset\(0 calc\(100% - var\(--hp-pct~s', $blk) === 1,
   'the dark copy is not clipped to the fill');
ok(preg_match('~\.hp-ink-off\s*\{[^}]*clip-path:\s*inset\(0 0 0 var\(--hp-pct~s', $blk) === 1,
   'the white copy is not clipped to the empty rail');
/* The wrapper must not set a colour of its own: both inks would inherit
   it and one of them would be wrong on every frame. */
ok(preg_match('~\.hp-num\s*\{[^}]*color:~s', $blk) !== 1,
   '.hp-num sets a colour, which overrides one of the two inks');
/* And the clips need the width, or they both sit at their 100% default
   and the bar shows dark text on a dark rail. */
ok(strpos($src, "setProperty('--hp-pct'") !== false,
   'the fill width is never published, so the clips never move');
ok(strpos($src, "querySelectorAll('.hp-ink')") !== false,
   'only one copy of the figure is written, so the other stays empty');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all health bar checks passed\n");
exit($fail ? 1 : 0);
