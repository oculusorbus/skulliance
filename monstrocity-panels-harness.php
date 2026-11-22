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

echo "\nand nothing on the page is rounded\n";
/*
 * REQUESTED: no border-radius anywhere in the game. This scans the WHOLE
 * file, not just the stylesheets, because one of the 38 was inline in a
 * JS template literal that builds a boss card -- a CSS-block-only check
 * would have missed it and left one rounded health fill on the page.
 *
 * It also catches the long-hand corners (border-top-left-radius and
 * friends), which are a different property name and would sail past a
 * search for "border-radius".
 *
 * OUT OF SCOPE ON PURPOSE: dhc-dropmodal.php keeps its corners. It is a
 * platform modal shared by about twenty pages and is not Monstrocity's
 * UI; squaring it here would square it on all of them.
 */
foreach (array('monstrocity.php', 'polish-monstrocity.php') as $f) {
	$t = (string) file_get_contents(__DIR__ . '/' . $f);
	/* Comments may discuss radii -- the removal is explained in several. */
	$t = preg_replace('~/\*.*?\*/~s', '', $t);
	preg_match_all('~border(?:-[a-z]+)*-radius\s*:\s*([^;"\']+)~i', $t, $m);
	$round = array();
	foreach ($m[1] as $v) if (preg_match('~[1-9]~', $v)) $round[] = trim($v);
	ok(count($round) === 0,
	   "$f still rounds something: " . implode(' | ', array_unique($round)));
}
/* The exit button comes from a SHARED component whose stylesheet is
   emitted after every one of this page's, so the page cannot override
   it -- it has to ask for the square corner by option. */
ok(preg_match("~game_exit_button\(array\((?:[^)]|\)[^)])*'radius'\s*=>\s*0~s", $src) === 1,
   'the exit button is not asked for a square corner, so it is the one rounded thing on the page');

echo "\nthe log and the legend collapse\n";
ok(preg_match('~<details class="log" open>\s*<summary>Battle Log</summary>~', $src) === 1,
   'the battle log is not an open-by-default <details> with a summary');
ok(preg_match('~<details class="legend">\s*<summary>Legend</summary>~', $src) === 1,
   'the legend is not a closed-by-default <details> with a summary');
ok(strpos($src, '<details class="legend" open') === false,
   'the legend opens by default, which is the opposite of what it is for');
/*
 * THE SCROLLER IS THE LIST, NOT THE PANEL. On a <details> the panel is
 * the summary too, so overflow out there scrolls the heading away. It
 * also makes log()'s `battleLog.scrollTop = 0` do something: entries
 * are PREPENDED, so that line has always meant "show the newest", and
 * it was a no-op while the overflow lived on .log.
 */
ok(preg_match('~#battle-log\s*\{[^}]*overflow-y:\s*auto~s', $code) === 1,
   'the battle log list is not the scroller, so the summary scrolls away with the entries');
$lg = rule($code, '.log');
ok($lg !== null && preg_match('~overflow-y~', $lg) !== 1,
   '.log scrolls again, which carries its own summary out of view');
/* The colour needs !important here for the same reason everything else
   on this page does. */
ok(preg_match('~\.log > summary,\s*\n\s*\.legend > summary\s*\{[^}]*color:[^;]*!important~s', $code) === 1,
   'the summaries do not force their colour, so .game-container * softens them to off-white');
ok(preg_match('~\.log > summary\s*\{[^}]*cursor|\.log > summary,\s*\n\s*\.legend > summary\s*\{[^}]*cursor:\s*pointer~s', $code) === 1,
   'the summaries do not look clickable');
ok(preg_match('~\.log > summary,\s*\n\s*\.legend > summary\s*\{[^}]*[;{\s]user-select:\s*none~s', $code) === 1,
   'a tap on the summary text can start a selection instead of toggling it');

echo "\nand the tray hugs what is left\n";
$gc = rule($code, '.game-container');
ok($gc !== null, 'the .game-container rule is gone');
ok(preg_match('~height:\s*auto~', (string) $gc) === 1,
   'the tray does not hug: height is not auto, so closing both panels reveals nothing');
ok(preg_match('~(^|;|\s)height:\s*100%~', (string) $gc) !== 1,
   'the tray is back to filling its parent whatever is in it');
ok(preg_match('~max-height:\s*2050px~', (string) $gc) !== 1,
   'the 2050px cap is back, which clips a tall legend instead of hugging it');
/*
 * THE COMPENSATION HAS TO MATCH THE SHOVE. .legend is the last child and
 * carries top:-225px; a relative offset does not change flow, so without
 * an equal negative bottom margin the tray ends 225px below where the
 * legend visibly ends -- which IS the gap that stops it hugging. Read
 * both numbers out and compare them, so moving one moves the other.
 */
$le = rule($code, '.legend');
ok($le !== null, 'the .legend rule is gone');
preg_match('~(^|[;\s])top:\s*-?(\d+)px~', (string) $le, $t);
preg_match('~margin-bottom:\s*-(\d+)px~', (string) $le, $mb);
ok(isset($t[2]) && isset($mb[1]) && $t[2] === $mb[1],
   'the legend\'s negative bottom margin no longer matches its top offset, so the tray stops hugging');
/*
 * NARROW IS DELIBERATELY NOT HUGGING. min-height:100vh makes the tray
 * the full-screen backdrop on a phone so no themed body background
 * shows around the game. That is a decision, not an oversight, and
 * "fixing" it to match desktop would undo it.
 */
ok(preg_match('~@media[^{]*max-width:\s*1025px.*?\.game-container\s*\{[^}]*min-height:\s*100vh~s', $code) === 1,
   'the narrow tray lost min-height:100vh, so the themed background now shows around the game on a phone');

echo "\nand the duplicated Health row is gone\n";
ok(strpos($src, 'id="p1-hp"') === false && strpos($src, 'id="p2-hp"') === false,
   'the Health row is back in the stats table, saying the same number the bar already shows');
/*
 * AND NOTHING STILL WRITES TO IT. updateHealth() runs in the attack path
 * AHEAD of sounds.match.play(), so a TypeError on a null element there
 * would take the match sound out with it.
 */
ok(preg_match('~\bp[12]Hp\b~', $src) !== 1,
   'something still writes to the removed Health cell, which throws in the attack path');

echo "\nthe narrow board is 330, and the tray starts at the top\n";
/* REQUESTED: 330x330 on mobile, up from 250. */
ok(preg_match('~@media[^{]*max-width:\s*1025px.*?#game-board\s*\{[^}]*width:\s*min\(330px, 100%\)~s', $code) === 1,
   'the narrow board is not min(330px, 100%), so it is the wrong size or it can overflow the screen');
/*
 * 100% AND NOT 100vw. vw counts the scrollbar, so calc(100vw - 20px) is
 * about 15px wider than the space actually available on a narrow desktop
 * window and the board hangs off the edge. Measured that way round.
 */
ok(preg_match('~@media[^{]*max-width:\s*1025px.*?#game-board\s*\{[^}]*100vw~s', $code) !== 1,
   'the narrow board sizes off 100vw, which includes the scrollbar and overflows');
/* A percentage HEIGHT cannot resolve against an auto-height parent, so
   square comes from aspect-ratio rather than a second min(). */
ok(preg_match('~@media[^{]*max-width:\s*1025px.*?#game-board\s*\{[^}]*aspect-ratio:\s*1~s', $code) === 1,
   'the narrow board has no aspect-ratio, so it is not square once it has to shrink');
/*
 * AND THE WIN/LOSE OVERLAY FOLLOWED IT. The board's top edge is fixed
 * and it grew 250 -> 330, so its centre moved down 40px; the overlay is
 * absolutely placed and does not move on its own. Measured: the modal
 * sat 40px high of the board until margin-top went 41 -> 81.
 */
ok(preg_match('~@media[^{]*max-width:\s*1025px.*?#game-over-container\s*\{[^}]*margin-top:\s*81px~s', $code) === 1,
   'the narrow win/lose overlay is not nudged 81px, so it no longer sits where it did over the board');
/*
 * THE TRAY STARTS AT THE TOP. body is display:flex with
 * align-items:center and min-height:100vh. That never showed while the
 * tray was height:100%, but a hugging tray got centred -- and when it
 * was TALLER than the window, centring put its top at -483px, off the
 * screen. align-self on the tray, not align-items on the body, because
 * the select containers are body children too and are meant to centre.
 */
ok(preg_match('~(^|[;\s])align-self:\s*flex-start~', (string) $gc) === 1,
   'the tray is not top-aligned, so it floats in the middle once the panels are closed');
ok(preg_match('~(^|\n)\s*body\s*\{[^}]*align-items:\s*center~s', $code) === 1,
   "body no longer centres its children -- the select containers rely on that");

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all panel checks passed\n");
exit($fail ? 1 : 0);
