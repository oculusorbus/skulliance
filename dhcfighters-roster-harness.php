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

$src     = file_get_contents(__DIR__ . '/dhcfighters.php');
$clean   = no_comments($src);
$asm_pre = no_comments(file_get_contents(__DIR__ . '/dhc-assembler.php'));

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
echo "\nthe intro folds on a phone without losing the rights notice\n";
/*
 * Six lines of prose above the thing people came to use is the whole first
 * screenful on a narrow screen. But the second half of it is the rights
 * notice -- what you assemble is not an NFT, earning a trait is not
 * ownership -- so it folds rather than disappearing.
 * Measured at load: 245px -> 20px at 390 and 700, unchanged 74px at 1100.
 */
ok(preg_match('/<details class="dhcf-note" id="dhcfNote" open>/', $clean) === 1,
   'the intro is no longer a <details open>; with JS off a phone would get a notice it cannot open');
ok(strpos($clean, '<summary>') !== false, 'the fold has no summary, so there is nothing to tap');
/* The sentences that must survive whatever happens to the layout. */
foreach (array('not an NFT', 'no ownership of the artwork', 'Maxingo') as $must) {
	ok(strpos($src, $must) !== false,
	   "the intro no longer says \"$must\"; that is the rights notice, not decoration");
}
ok(preg_match('/\.dhcf-note > summary\{display:none\}/', $clean) === 1,
   'the summary shows on desktop, where this should render exactly as the paragraph it replaced');
ok(preg_match('/@media \(max-width:700px\)\{[\s\S]{0,600}?\.dhcf-note > summary\{display:block/', $clean) === 1,
   'the summary never appears on a phone, so the fold cannot be opened');
ok(strpos($clean, "matchMedia('(max-width:700px)')") !== false,
   'the fold no longer decides from the viewport');
ok(strpos($clean, "n.addEventListener('toggle'") !== false,
   'a reader who opens the notice has it shut again on the next resize');

echo "\nthe mobile shortcut to the trait-drop list\n";
/*
 * "What can I still pull today" is asked several times a day, and on a phone
 * dhcfighters.php stacks: the list is below the assembler, the canvas, the
 * draw order and twenty roster cards. The shortcut is an ANCHOR into that
 * page from the DHC strip, mobile only -- on a desktop the list is already a
 * column of the page and a link to it would be clutter.
 * Measured at 360/400: five chips, two rows, 83px, nothing clipped, no
 * sideways scroll. At 1000: four chips, one row.
 */
$nav = no_comments(file_get_contents(__DIR__ . '/dhc-nav.php'));
$n_at  = strpos($nav, '$dhcnav_items = array(');
ok($n_at !== false, '$dhcnav_items is gone from dhc-nav.php');
$items_src = substr($nav, $n_at, strpos($nav, "\n);", $n_at) - $n_at);
preg_match_all("/'([a-z]+)'\s*=> array\(([^)]*)\)/", $items_src, $im, PREG_SET_ORDER);
ok(count($im) >= 5, 'expected at least five DHC strip entries, found ' . count($im));
/* list() destructures five fields now. A four-field row is an undefined
   offset on every DHC page at once, not just the one that added it. */
foreach ($im as $row) {
	$fields = count(explode(',', $row[2]));
	ok($fields === 5, "the \"{$row[1]}\" strip entry has $fields fields, not 5; list() will warn on every DHC page");
}
ok(strpos($items_src, "'dhcfighters.php#drops'") !== false,
   'the Games shortcut no longer points at the drop list anchor');
/*
 * AND ON THE PAGE IT POINTS INTO IT MUST BE JUST THE HASH. "page.php#frag"
 * only jumps in place when everything before the "#" matches the current URL
 * exactly -- it does not while editing (?edit=12) -- and any mismatch makes
 * it a navigation that reloads the assembler, the trait index, the roster
 * art and two leaderboards to reach a list already further down the same
 * document. In the installed PWA that reload was reported as never
 * finishing. Rendered both ways: on 'fighters' the href is "#drops", on
 * 'arena' it is "dhcfighters.php#drops".
 */
ok(strpos($nav, '$dhcnav_items[$dhcnav_at][0] === substr($href, 0, $hash)') !== false,
   'the strip no longer collapses a fragment aimed at the current page, so it reloads instead of jumping');
ok(preg_match("/'status'\s*=> array\('dhcfighters\.php#drops', 'Games', 'review', 1, 1\)/", $items_src) === 1,
   'the Games entry changed shape: it must be gated and mobile-only, labelled noun/verb like its neighbours');
ok(preg_match('/\.dhcnav a\.only-mobile\{display:none\}/', $nav) === 1,
   'the shortcut is no longer hidden on desktop, where the list it jumps to is already on screen');
ok(preg_match('/@media \(max-width:700px\)\{[\s\S]{0,600}?\.dhcnav a\.only-mobile\{display:flex\}/', $nav) === 1,
   'the shortcut never becomes visible on a phone, which is the only place it exists for');
ok(preg_match('/@media \(max-width:700px\)\{[\s\S]{0,600}?\.dhcnav\{flex-wrap:wrap;overflow-x:visible\}/', $nav) === 1,
   'the strip scrolls sideways again on a phone; five chips do not fit and a scroller hides the overflow behind an untold gesture');

/* The anchor has to land somewhere. */
ok(strpos($clean, 'class="dhcf-panel dhcf-games" id="drops"') !== false,
   'the trait-drop panel lost id="drops", so the shortcut scrolls nowhere');
ok(preg_match('/\.dhcf-games\{[^}]*scroll-margin-top/', $clean) === 1,
   'the drop panel has no scroll margin, so the anchor lands it flush against the top edge');

echo "\nnothing scrolls inside anything on a phone\n";
/*
 * Desktop keeps its inner scrollers -- the picker is a real column beside a
 * canvas and the roster is capped at half of it, which is wanted. Stacked on
 * a phone neither has a column height to fill, so the same declarations only
 * produce short windows onto long lists: scroll the page to the traits, then
 * scroll again inside them, with Fighters past the second row clipped away
 * entirely. Measured after the fix at 400 and 900: zero inner scrollers,
 * zero clipped elements. At 1400: grid auto, aside 50%, ladder auto.
 */
ok(preg_match('/@media \(max-width:900px\)\{[^}]*\.picker\{position:static;width:auto;max-height:none\}/', $asm_pre) === 1,
   'the picker is capped again on mobile, which is what turns its trait list into an inner scroller');
ok(preg_match('/@media \(max-width:900px\)\{[\s\S]{0,600}?\.picker > \.dhcf-aside\{max-height:none;margin-top:0/', $asm_pre) === 1,
   'the roster keeps its 50% cap on mobile, so Fighters past the second row are clipped away');
/* Desktop must NOT have been collateral. */
ok(preg_match('/\.picker > \.dhcf-aside\{flex:0 1 auto;max-height:50%/', $asm_pre) === 1,
   'the roster lost its desktop cap; it will push the picker past the shell it is positioned inside');
ok(preg_match('/\.grid\{flex:1;overflow:auto;padding:10px/', $asm_pre) === 1,
   'the trait grid lost its desktop scroller');

/*
 * ORDER, NOT JUST PRESENCE. The picker section redeclares .grid with its own
 * overflow:auto further down the file, so a mobile override written with the
 * other mobile rules higher up loses at equal specificity -- which it did:
 * the computed overflow-y at 400px was still "auto" after the first attempt.
 * The override has to come after the declaration it is overriding.
 */
$grid_decl   = strpos($asm_pre, '.grid{flex:1;overflow:auto;padding:10px');
$grid_mobile = strpos($asm_pre, '.grid{flex:none;overflow:visible');
ok($grid_mobile !== false, 'the trait grid never drops its scroller on mobile');
ok($grid_mobile !== false && $grid_decl !== false && $grid_mobile > $grid_decl,
   'the mobile .grid rule sits BEFORE the .grid declaration it overrides, so it loses the cascade');

ok(preg_match('/@media \(max-width:900px\)\{[\s\S]{0,400}?\.dhcf-ladder \.lb-ath\{overflow:visible/', $clean) === 1,
   'the ladder drops its inner scroller at the wrong breakpoint; it must match the 900px where .shell and .dhcf-panels stack');

echo "\nthe columns below line up with the assembler's own split\n";
/* The panels used to divide 50/50 while the stage/picker seam directly above
   them sat at --stage, so two vertical rules ran down the page a couple of
   hundred pixels apart. Measured after the fix: the picker's left border and
   this panel's right border both occupy [653,654) at 1200/1400/1800/2000. */
ok(preg_match('/\.dhcf-panels\{[^}]*grid-template-columns:minmax\(0,calc\(var\(--stage\) \+ 1px\)\)/', $clean) === 1,
   'the panels grid no longer tracks --stage (+1px for .shell\'s own border), so its split drifts from the assembler\'s');
/* NO COLUMN GAP. The edge that must line up is the RIGHT panel's LEFT edge
   against the picker's left edge -- same column of the page, read as one
   line. A gutter makes that impossible: it puts the right panel 14px past
   wherever the left one ends, so only one of the two edges can meet the
   picker's border. An earlier pass aligned the LEFT panel's right edge and
   it still read as crooked, because the misaligned edge was the one being
   looked at. .shell has always solved this with gap:0 and a shared border;
   this mirrors it. Measured: both rules at [653,654) at 1200/1400/2000. */
ok(preg_match('/\.dhcf-panels\{[^}]*column-gap:0/', $clean) === 1,
   'the panels row has a column gap again, so the ladder cannot sit on the picker\'s edge');
ok(preg_match('/\.dhcf-panels > \.dhcf-games\{border-right:0\}/', $clean) === 1,
   'the trait-drop panel kept its right border, so the seam is two touching rules where the shell has one');
ok(preg_match('/@media \(max-width:900px\)\{[^}]*\.dhcf-panels\{grid-template-columns:1fr/s', $clean) === 1,
   'the panels no longer collapse at the same 900px breakpoint as .shell');
ok(preg_match('/@media \(max-width:900px\)\{[\s\S]{0,400}?\.dhcf-panels > \.dhcf-games\{border-right:1px/', $clean) === 1,
   'stacked, the trait-drop panel never gets its right border back, so it renders as an open box');
ok(preg_match('/\.dhcf-wrap\{--stage:/', $asm_pre) === 1,
   '--stage is no longer declared on .dhcf-wrap, so the host page cannot line up with the shell');

echo "\nthe disassemble control fits the card\n";
/* "Disassemble" was the longest label in a three-control row and the cards
   in the picker column are ~96px: it rendered as "DISA". A clipped word is
   worse than none -- it reads as a layout fault and says nothing. Measured
   after: Edit 23px + Rename 51px + trash 20px in a 111px row, nothing
   clipped or overflowing on any of 26 cards. */
ok(strpos($clean, '>Disassemble</button>') === false,
   'the word Disassemble is back on the button; it does not fit the card');
/* NOT [^>]* here: the title attribute in between contains a <?php ?> tag,
   whose own ">" ends that run before it ever reaches aria-label. The first
   version of this check failed against correct markup for that reason. */
ok(preg_match('/class="dhcf-scrap"[\s\S]{0,400}?aria-label="Disassemble/', $clean) === 1,
   'the disassemble button has no accessible name, so it is an unlabelled icon to a screen reader');
ok(preg_match('/class="dhcf-scrap"[^>]*title="Disassemble/', $clean) === 1,
   'the disassemble button has no title, so nothing explains the icon on hover');
ok(preg_match('/\.dhcf-card \.acts \.dhcf-scrap\{[^}]*flex:0 0 auto/', $clean) === 1,
   'the trash button takes a 1fr share again, which is what squeezed the word buttons');
ok(strpos($clean, 'stroke:currentColor') !== false,
   'the icon no longer inherits currentColor, so it will not follow the hover state');
/* The confirm still has to spell out what it costs -- the icon carries less
   warning than the word did. */
ok(strpos($clean, "confirm('Disassemble ") !== false,
   'the confirmation prompt is gone; an icon with no confirm is a one-click permanent delete');

echo "\nthe save bar leads the assembler\n";
/* It used to sit under the shell, which on a wide screen put the one control
   the page exists to reach beneath the draw-order list on one side and
   twenty roster cards on the other. */
$save_at = strpos($clean, '<div class="dhcf-save');
$asm_at  = strpos($clean, "include __DIR__ . '/dhc-assembler.php'");
ok($save_at !== false, 'the save bar is gone');
ok($save_at < $asm_at, 'the save bar is back below the assembler, where it gets lost');
/* And there must still be exactly ONE of it -- lifting markup from one place
   to another is how a page ends up rendering the bar twice with a handler
   bound to whichever id the browser saw first.
   The BAR is what gets counted, not the button: id="dhcfSave" legitimately
   appears twice in the source, once in each arm of the $dhcf_editing
   if/else, and only one of those is ever rendered. Counting the button
   failed against correct markup. */
ok(substr_count($clean, '<div class="dhcf-save') === 1,
   'the save bar is rendered more than once');
ok(substr_count($clean, 'id="dhcfSay"') === 1, 'there is more than one #dhcfSay status line');
$bar_block = substr($clean, $save_at, strpos($clean, '<?php endif; ?>', $save_at) - $save_at);
ok(substr_count($bar_block, 'id="dhcfSave"') === 2
   && strpos($bar_block, '<?php else: ?>') !== false,
   'the save button is no longer one-per-branch of the editing conditional');

/* Clicking a Fighter must not throw you at the top of the page. */
ok(strpos($clean, "block: 'center'") === false,
   "the card click centres the canvas again; at 66vh tall that scrolls almost to the top of the page");
ok(preg_match("/scrollIntoView\(\{ behavior: 'smooth', block: 'start' \}\)/", $clean) === 1,
   'the card click no longer scrolls the assembler to the top of the viewport');
ok(strpos($clean, '.shell{scroll-margin-top') !== false,
   'the assembler has no scroll margin, so it lands flush against the viewport edge');

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
