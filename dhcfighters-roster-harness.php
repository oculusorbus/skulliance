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
/* class="dhcf-panel" and class="dhcf-panel dhcf-games" -- but NOT the
   wrapper's own class="dhcf-panels", which contains the same prefix and
   made this count 4 against correct markup. */
$panel_count = preg_match_all('/class="dhcf-panel[" ]/', $panels);
ok($panel_count === 3,
   'expected exactly three panels in the grid (roster, ladder, trait drops), found ' . $panel_count);
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

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
