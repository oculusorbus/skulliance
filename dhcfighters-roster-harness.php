<?php
/* dhcfighters-roster-harness.php - CLI only. No database.
 *
 * Sorting the roster, and the one-column ladder that freed the space for the
 * trait-drop list to sit beside it.
 *
 * THE SORT IS ONLY HONEST IF "DEADLIEST" MEANS WHAT THE ARENA MEANS. POW and
 * HP come from dhca_build_fighter() -- the same function the Arena's Crew
 * picker and the battle engine use. A second opinion computed here would let
 * this page call a Fighter deadly that the Arena then disagrees with, and the
 * player only finds out after losing with it. So the derivation is EXECUTED
 * here, not pattern-matched.
 *
 * The rest is drift: an <option> whose key has no comparator sorts nothing
 * and says nothing, and a comparator reading a data attribute the card does
 * not emit silently sorts everything to zero -- both look like "the sort is
 * broken" and neither throws.
 *
 * Geometry (three columns at 1400, stacked at 400, the ladder filling the
 * roster's height) was measured in headless Chrome against the real
 * stylesheet rather than asserted here; a layout test needs a browser.
 *
 * Usage: php dhcfighters-roster-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }
function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	$s = preg_replace('~(?<![:/])//.*$~m', '', $s);
	return $s;
}

$src   = file_get_contents(__DIR__ . '/dhcfighters.php');
$clean = no_comments($src);

/* ---------------------------------------------------------------- *
 * Deadliest and Toughest are the Arena's own numbers.
 * ---------------------------------------------------------------- */
echo "POW and HP come from the Arena's own engine\n";
/*
 * BOTH, and in this order. dhcarena-engine.php pulls in dhcarena-roles.php
 * and nothing else -- it takes the rarity table as an ARGUMENT rather than
 * reaching for it, which is what keeps it isolated from the DB and session
 * layers. So dhcf_rarity() has to come from dhcfighters-lib.php separately.
 * (dhcfighters.php itself already requires that at the top of the page; this
 * only bites a harness loading the engine on its own, which is how the
 * dependency got noticed at all.)
 *
 * Neither file touches a database: dhcfighters-lib.php's only require is
 * dhcfighters-config.php, which is the trait tables.
 */
require_once __DIR__ . '/dhcfighters-lib.php';
require_once __DIR__ . '/dhcarena-engine.php';
ok(function_exists('dhca_build_fighter'), 'dhca_build_fighter() is gone');
ok(function_exists('dhcf_rarity'), 'dhcf_rarity() is gone');

$rarity = dhcf_rarity();
/* A real minimum-legal Fighter: background, torso and head and nothing else.
   DHCF_REQUIRED allows it, and every optional slot arrives missing -- which
   is the shape that used to emit undefined-key warnings straight into the
   JSON on the Arena's own endpoint. */
$bare = dhca_build_fighter(array('background' => '', 'torso' => '', 'head' => ''), '', 'h1', $rarity);
ok(is_array($bare), 'a minimum-legal Fighter no longer builds');
ok(isset($bare['power']) && isset($bare['maxHp']), 'the built Fighter has no power/maxHp for the roster to sort on');
ok((int)$bare['power'] > 0 && (int)$bare['maxHp'] > 0,
   'a bare Fighter derives 0 POW/HP, so every empty-slot Fighter would tie at the bottom');

/* The page must USE it rather than reimplement it. */
ok(strpos($clean, 'dhca_build_fighter(') !== false,
   'dhcfighters.php no longer calls dhca_build_fighter(); POW/HP are now a second opinion the Arena does not share');
ok(strpos($clean, "require_once __DIR__ . '/dhcarena-engine.php'") !== false,
   'dhcfighters.php calls into the arena engine without requiring it');
foreach (array('pow' => 'power', 'hp' => 'maxHp') as $field => $from) {
	ok(preg_match('/\$dhcf_roster\[\$i\]\[\'' . $field . '\'\]\s*=\s*\(int\)\$built\[\'' . $from . '\'\]/', $clean) === 1,
	   "the roster's \"$field\" no longer comes from the built Fighter's \"$from\"");
}

/* ---------------------------------------------------------------- *
 * Every option sorts, and every sort reads something that is there.
 * ---------------------------------------------------------------- */
echo "\nevery sort option has a comparator and a fact to read\n";
$sel_at = strpos($src, '<select id="dhcfSort">');
ok($sel_at !== false, 'the #dhcfSort control is gone');
$sel = substr($src, $sel_at, strpos($src, '</select>', $sel_at) - $sel_at);
preg_match_all('/<option value="([a-z]+)"/', $sel, $m);
$options = $m[1];
ok(count($options) >= 5, 'only ' . count($options) . ' sort options; the point was more than one axis');

/* The comparator table, as written. */
$ord_at = strpos($clean, 'var ORDERS = {');
ok($ord_at !== false, 'the ORDERS comparator table is gone');
$orders_src = substr($clean, $ord_at, strpos($clean, "\n  };", $ord_at) - $ord_at);
preg_match_all('/^\s{4}([a-z]+):\s*function/m', $orders_src, $m2);
$orders = $m2[1];

foreach ($options as $o) {
	ok(in_array($o, $orders, true),
	   "the dropdown offers \"$o\" and ORDERS has no comparator for it, so picking it sorts by nothing");
}
foreach ($orders as $o) {
	ok(in_array($o, $options, true), "ORDERS defines \"$o\" and nothing in the dropdown can pick it");
}
printf("  %d options, %d comparators\n", count($options), count($orders));

/* Every data-* the comparators read must be emitted on the card. A missing
   one is not an error in JS -- parseFloat(null) is NaN, the guard turns it
   into 0, and the whole grid ties. */
preg_match_all("/rnum\(\s*[ab]\s*,\s*'([a-z]+)'\s*\)/", $orders_src, $m3);
$needed = array_unique($m3[1]);
if (preg_match("/rname\(/", $orders_src)) $needed[] = 'name';
$card_at = strpos($src, '<div class="dhcf-card"');
$card    = substr($src, $card_at, 900);
foreach ($needed as $attr) {
	ok(strpos($card, 'data-' . $attr . '="') !== false,
	   "a comparator reads data-$attr and the card does not emit it; every Fighter would tie at 0");
}
printf("  reads: %s\n", implode(', ', $needed));

/* Sorting must return to page one. Sorting and staying on page 3 shows the
   player the same cards they were already looking at. */
/* Slice the function out and look inside it, rather than trying to match
   across its body: rapply() contains an inner `function (c) { ... }`, so a
   [^}] run stops at that callback's brace and never reaches the line being
   checked. The first version of this check failed on correct code. */
$rap_at = strpos($clean, 'function rapply(');
ok($rap_at !== false, 'rapply() is gone; the sort does nothing');
$rapply = substr($clean, $rap_at, strpos($clean, "\n  }", $rap_at) - $rap_at);
ok(preg_match('/rpage\s*=\s*0;/', $rapply) === 1,
   'sorting no longer resets the pager, so a re-sort lands mid-list');
ok(strpos($clean, 'rgrid.appendChild(c)') !== false,
   'the sort no longer moves the cards, so the grid order never changes on screen');
/* Stored preference must never be able to break the page. */
ok(preg_match('/try \{ localStorage\.setItem\(SORT_KEY/', $clean) === 1
   && preg_match('/try \{ saved = localStorage\.getItem\(SORT_KEY\); \} catch/', $clean) === 1,
   'localStorage is touched without a try/catch; it throws in a private window and takes the roster with it');
ok(preg_match('/if \(saved && ORDERS\[saved\]\)/', $clean) === 1,
   'a stored sort key is applied without checking it is still a real one');

/* ---------------------------------------------------------------- *
 * One ladder, and the freed column.
 * ---------------------------------------------------------------- */
echo "\none ladder panel, monthly first, games beside it\n";
$panels_at  = strpos($src, '<div class="dhcf-panels">');
$panels_end = strpos($src, 'function dhcf_board_html');
$panels     = substr($src, $panels_at, $panels_end - $panels_at);
ok(substr_count($panels, 'dhcf_board_html($dhcf_lb_month)') === 1
   && substr_count($panels, 'dhcf_board_html($dhcf_lb_ath)') === 1,
   'the two ladders are no longer rendered exactly once each');
ok(strpos($panels, 'dhcf_board_html($dhcf_lb_month)') < strpos($panels, 'dhcf_board_html($dhcf_lb_ath)'),
   'all-time is rendered before this month; the short, live list goes first');
/* TWO in the grid now -- trait drops and the ladder. The roster went up into
   the picker column. Note the pattern excludes the wrapper's own
   class="dhcf-panels", which carries the same prefix and once made this
   count one too many against correct markup. */
$panel_count = preg_match_all('/class="dhcf-panel[" ]/', $panels);
ok($panel_count === 2,
   'expected two panels in the grid (trait drops, ladder), found ' . $panel_count);
ok(strpos($panels, 'Your Fighters') === false,
   'the roster is back in the grid; it belongs in the picker column, which is otherwise empty below the thumbnails');

/*
 * AND THEY MUST BE SIBLINGS, which counting cannot tell you.
 *
 * A misplaced </div> once left .dhcf-ladder nested INSIDE .dhcf-games. The
 * count above was still 2, the page still had both panels, PHP still linted
 * clean -- and the grid had one child, so the two rendered full width,
 * stacked, which is precisely the layout this work existed to replace. It
 * shipped. So walk the divs and check the parent, rather than trusting a
 * substring tally.
 */
$depth  = 0;
$parent = array();
$seen   = array();
preg_match_all('/<div\b[^>]*>|<\/div>/', preg_replace('/<\?php.*?\?>/s', '', $panels), $tags, PREG_PATTERN_ORDER);
foreach ($tags[0] as $tag) {
	if ($tag === '</div>') { array_pop($parent); $depth--; continue; }
	if (preg_match('/class="([^"]*)"/', $tag, $cm)) {
		$cls = $cm[1];
		if ($cls !== 'dhcf-panels' && strpos($cls, 'dhcf-panel') === 0) {
			$seen[$cls] = end($parent) === false ? 'ROOT' : end($parent);
		}
		$parent[] = $cls;
	} else {
		$parent[] = '';
	}
	$depth++;
}
foreach (array('dhcf-panel dhcf-games', 'dhcf-panel dhcf-ladder') as $cls) {
	ok(isset($seen[$cls]), "the \"$cls\" panel is gone");
	ok(isset($seen[$cls]) && $seen[$cls] === 'dhcf-panels',
	   "\"$cls\" is nested inside \"" . ($seen[$cls] ?? '?') . "\" instead of being a child of the grid, "
	 . 'so it renders full width instead of as a column');
}
ok($depth === -1,
   'the panels markup does not balance (' . $depth . '); a stray or missing </div> changes what is nested in what');
ok(strpos($panels, 'dhcf-games') !== false,
   'the trait-drop list is outside the panels grid again, so it is back below the fold on a desktop');
/* ORDER: roster, trait drops, ladder. The drop list sits beside the roster
   because the two are read together -- what you have, and where the next
   piece comes from -- and the ladder goes last because it is the only one
   of the three that is about other people. */
ok(strpos($panels, 'dhcf-games') < strpos($panels, 'dhcf-ladder'),
   'the ladder column comes before the trait-drop column');

/* The flexbox detail that is easy to lose and silently wrong: a flex child's
   default min-height is auto, so without min-height:0 the all-time table
   refuses to shrink and overflows the panel instead of scrolling in it. */
ok(preg_match('/\.dhcf-ladder \.body\{[^}]*min-height:0/', $clean) === 1,
   'the ladder body lost min-height:0; the all-time list will overflow the panel instead of filling it');
ok(preg_match('/\.dhcf-ladder \.lb-ath\{[^}]*min-height:0/', $clean) === 1,
   'the all-time section lost min-height:0');
ok(preg_match('/\.dhcf-ladder \.lb-ath\{[^}]*flex:1/', $clean) === 1,
   'the all-time section no longer grows, so the column ends in dead space');

/* ---------------------------------------------------------------- *
 * The roster lives in the picker's aside.
 * ---------------------------------------------------------------- */
echo "\nthe roster fills the picker column\n";
ok(preg_match('/ob_start\(\);.*?Your Fighters.*?\$dhca_aside = ob_get_clean\(\);/s', $clean) === 1,
   'the roster is no longer buffered into $dhca_aside, so nothing reaches the picker');
ok(strpos($clean, '$dhca_aside = ob_get_clean();') < strpos($clean, "include __DIR__ . '/dhc-assembler.php'"),
   '$dhca_aside is built AFTER the assembler is included, so it arrives too late to render');
ok(strpos($clean, 'class="dhcf-panel dhcf-aside"') !== false,
   'the roster panel lost its .dhcf-aside class, which is what the picker styles it by');

/*
 * THE ROSTER DRAWS BEFORE THE ASSEMBLER RUNS, so it cannot read anything the
 * assembler defines. $dhc_base was exactly that: set at the top of
 * dhc-assembler.php, read by the card loop, and the buffer runs first -- so
 * every card shipped with an empty src and "Undefined variable $dhc_base"
 * printed into its alt text. dhcf_art_dir() lives in dhcfighters-config.php
 * now, which both sides already require, and both call it.
 */
require_once __DIR__ . '/dhcfighters-config.php';
ok(function_exists('dhcf_art_dir'), 'dhcf_art_dir() is gone from dhcfighters-config.php');
ok(dhcf_art_dir() !== '', 'dhcf_art_dir() resolves to nothing; every Fighter image would 404');
ok(strpos($clean, '$dhc_base = dhcf_art_dir();') !== false,
   'dhcfighters.php no longer resolves the art base itself, so the buffered roster reads the assembler\'s copy before it exists');
ok(strpos($clean, '$dhc_base = dhcf_art_dir();') < strpos($clean, 'ob_start();'),
   'the art base is resolved AFTER the roster is buffered, which is the same bug in a different order');

$asm = no_comments(file_get_contents(__DIR__ . '/dhc-assembler.php'));
ok(strpos($asm, '$dhc_base = dhcf_art_dir();') !== false,
   'the assembler went back to detecting the art path itself; there must be one answer, not two');

/*
 * AND THE NAME MUST NOT COLLIDE. dhcfighters-notify.php has had its own
 * dhcf_art_base() for a long time, returning the ABSOLUTE https:// prefix
 * Discord needs rather than the relative directory. Adding a second
 * dhcf_art_base() in config was a fatal "cannot redeclare" the moment both
 * loaded -- which dhcfighters-lib.php does on every single trait award, so
 * it broke the drop path outright. php -l cannot see it; only loading both
 * can, so that is what this does.
 */
$notify = no_comments(file_get_contents(__DIR__ . '/dhcfighters-notify.php'));
ok(preg_match_all('/function\s+dhcf_art_dir\s*\(/', $notify) === 0,
   'dhcfighters-notify.php declares dhcf_art_dir() too; one of them will fatal');
ok(preg_match_all('/function\s+dhcf_art_base\s*\(/', no_comments(file_get_contents(__DIR__ . '/dhcfighters-config.php'))) === 0,
   'dhcfighters-config.php declares dhcf_art_base(), which notify already owns -- a fatal on every trait award');
/* Drive it: the award path loads config (via the lib) and then notify. */
$probe = escapeshellarg('require ' . escapeshellarg(__DIR__ . '/dhcfighters-config.php')
       . '; require ' . escapeshellarg(__DIR__ . '/dhcfighters-notify.php')
       . '; echo "OK:" . dhcf_art_dir() . "|" . dhcf_art_base();');
$res = shell_exec(PHP_BINARY . ' -r ' . $probe . ' 2>/dev/null');
ok(strpos((string)$res, 'OK:') !== false,
   'loading dhcfighters-config.php and dhcfighters-notify.php together fatals; that is the trait-award path');
ok(strpos((string)$res, 'OK:|') === false, 'the art directory resolves to nothing when both are loaded');
$pick_at = strpos($asm, '<div class="picker">');
ok($pick_at !== false, 'the picker markup moved');
$picker = substr($asm, $pick_at, strpos($asm, '</div>', strpos($asm, 'dhca_aside', $pick_at)) - $pick_at);
ok(strpos($picker, 'dhca_aside') !== false,
   'the assembler no longer renders $dhca_aside inside .picker');
ok(strpos($picker, 'id="grid"') < strpos($picker, 'dhca_aside'),
   'the aside renders above the trait grid; it fills the space BELOW it');
ok(preg_match('/\.picker > \.dhcf-aside\{[^}]*max-height:50%/', $asm) === 1,
   'the aside is uncapped; a tall roster will push the picker past the shell it is positioned inside');
ok(preg_match('/\.picker > \.dhcf-aside\{[^}]*min-height:0/', $asm) === 1,
   'the aside lost min-height:0 and will refuse to shrink');
/* PINNED. .grid is flex:1 and absorbs the slack, which puts the aside at the
   foot of the column today -- by side effect, not by instruction. The roster
   is the same whatever tab is open, so its top edge must not move when you
   click from a 42-thumbnail slot to a 6-thumbnail one. Verified in headless
   Chrome at 60, 6 and 0 thumbnails: top stayed at 590px, flush with the
   picker's bottom, in all three. */
ok(preg_match('/\.picker > \.dhcf-aside\{[^}]*margin-top:auto/', $asm) === 1,
   'the aside is no longer pinned to the bottom of the picker; it will move as the trait grid changes size');

/* The assembler has to stay includable by a page with no roster at all --
   dhcsandbox.php is public and has no session, no database and no Fighters. */
ok(strpos($asm, 'if (!empty($dhca_aside))') !== false,
   'the aside is rendered without an empty check; the sandbox would emit a stray undefined variable');
$sandbox = file_get_contents(__DIR__ . '/dhcsandbox.php');
ok(strpos($sandbox, 'dhca_aside') === false,
   'dhcsandbox.php now sets $dhca_aside; the sandbox is public and owns no roster');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
