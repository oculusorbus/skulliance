<?php
/* monstrocity-panels-harness.php — CLI only. Static. No database.
 *
 *     php monstrocity-panels-harness.php
 *
 * THE CARD AND THE BOARD ARE NOT PANELS ANY MORE.
 *
 * Both were a box inside padding inside a border inside a radius, and
 * the chrome was holding space the content could use. Removing it is
 * only safe because the arithmetic was done in both axes, and the
 * arithmetic is what this pins -- the narrow layout is tuned against
 * hard-coded offsets (#game-over-container at top:317px, the board and
 * the cards at top:-225px), so a card whose OUTER box changes size
 * drags the log and the legend with it.
 *
 * Measured outcome this is protecting: nothing moves at 390, 800,
 * 1024, 1026, 1240 or 1600px; the card's content goes 265x552 ->
 * 295x584 and the board's inner area 314 -> 340 (222 -> 250 narrow).
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$src  = (string) file_get_contents(__DIR__ . '/monstrocity.php');
$pol  = (string) file_get_contents(__DIR__ . '/polish-monstrocity.php');
$code = preg_replace('~/\*.*?\*/~s', '', $src);   /* the prose names every
                                                     property below */
function rule($css, $sel) {
	return preg_match('~(^|\n)\s*' . preg_quote($sel, '~') . '\s*\{([^}]*)\}~', $css, $m)
	     ? $m[2] : null;
}

echo "the card carries no chrome, and the width makes up for it\n";
$ch = rule($code, '.character');
ok($ch !== null, 'the base .character rule is gone');
ok(preg_match('~width:\s*295px~', (string) $ch) === 1,
   'the card is not 295px wide, so the content lost the space the padding gave back');
foreach (array('padding', 'border', 'border-radius', 'background-color') as $gone)
	ok(preg_match('~(^|;|\s)' . $gone . '\s*:~', (string) $ch) !== 1,
	   "the base .character rule has its $gone back, which puts the chrome back and moves the column");
/*
 * 584 IS THE WHOLE POINT. min-height is content-box like the width, so
 * the old card stood 552 + 30 padding + 2 border = 584 in flow. Leaving
 * it at 552 with the padding gone made the column 32px shorter and
 * .log and .legend -- which follow .battlefield and carry their own
 * top:-225px -- BOTH RODE UP BY EXACTLY THAT. Measured, not reasoned.
 */
ok(preg_match('~min-height:\s*584px~', (string) $ch) === 1,
   'the card min-height is not 584, so its flow box changed and the log and legend move');

echo "\nand the narrow card keeps its old footprint too\n";
/* The narrow rule sizes itself with its own padding ON TOP of the base
   border that is now gone, so it needed a pixel back in each axis or
   the board rose 2px and the log and legend 4px. */
ok(preg_match('~@media[^{]*max-width:\s*1025px.*?\.character\s*\{[^}]*padding:\s*6px 11px~s', $code) === 1,
   'the narrow card is not padded 6px 11px, so removing the base border shrank it and the board moved up');

echo "\nthe board hands its padding to the tiles\n";
$gb = rule($code, '#game-board');
ok($gb !== null, 'the base #game-board rule is gone');
ok(preg_match('~(^|;|\s)padding\s*:~', (string) $gb) !== 1,
   'the board has its padding back, so the tiles lose that space again');
ok(preg_match('~(^|;|\s)background\s*:~', (string) $gb) !== 1,
   'the board has its fill back');
/* THE REASON REMOVING THE PADDING IS FREE. With border-box the padding
   came OUT of the 342px, so dropping it grows the tiles and the board's
   footprint does not move. Without it, the board would GROW by 2vh. */
ok(preg_match('~box-sizing:\s*border-box~', (string) $gb) === 1,
   'the board is no longer border-box, so its 342px is now the content and the board grows');

echo "\nneither is painted back by a later layer\n";
/* A regex, not strpos: the layer still lights .character-option. */
ok(preg_match('~(^|\n)\s*\.character\s*[,{]~', $pol) !== 1,
   'the polish layer lights .character, which paints the panel back on');
ok(preg_match('~(^|\n)\s*#game-board\s*[,{]~', $pol) !== 1,
   'the polish layer lights #game-board');
/* The skin block's own panel rule. .log and .legend ARE still panels. */
ok(preg_match('~(^|\n)\s*\.log,\s*\n\s*\.legend\s*\{[^}]*background-color:\s*#1a3142~s', $code) === 1,
   'the skin block no longer gives .log and .legend their panel fill');
foreach (array('.character', '#game-board') as $bare)
	ok(preg_match('~(^|\n)\s*' . preg_quote($bare, '~') . ',\s*\n\s*\.log,~', $code) !== 1,
	   "$bare is back in the skin block's panel list");

echo "\nand no selector list was left dangling\n";
/*
 * THE BUG THIS EXISTS FOR, and it was mine. #game-board was the LAST
 * selector in `.character, .log, .legend, #game-board { ... }`.
 * Deleting it and its block left `.character, .log, .legend,` running
 * straight into the NEXT rule, so the heading rule below became
 * `.character, .log, .legend, .log h3, .legend h3, .character h2` and
 * put uppercase 0.85rem heading type on the whole log and legend.
 * Nothing errored. The legend quietly lost 121px and only measuring
 * found it.
 */
ok(preg_match('~(^|\n)\s*\.log h3,\s*\n\s*\.legend h3,\s*\n\s*\.character h2\s*\{~', $code) === 1,
   'the heading rule has picked up extra selectors, or lost its own -- a selector list above it is dangling');
/* The general form: a comma, then a comment or blank line, then a new
   rule. That is always a list that lost its last selector. */
ok(preg_match('~,\s*\n\s*(/\*|\n)~', preg_replace('~/\*.*?\*/~s', '/**/', $src)) !== 1
   || preg_match('~,\s*\n\s*/\*\*/\s*\n\s*[.#@a-zA-Z][^{\n]*\{~', preg_replace('~/\*.*?\*/~s', '/**/', $src)) !== 1,
   'a selector list ends in a comma with only a comment after it, which swallows the next rule');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all panel checks passed\n");
exit($fail ? 1 : 0);
