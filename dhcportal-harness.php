<?php
/*
 * dhcportal-harness.php — CLI only. No database.
 *
 * THE BOARD RULES ARE PURE, so most of this EXECUTES them rather than
 * grepping for them. dhcp_fit(), dhcp_rows() and dhcp_table_name() take
 * plain arrays and return plain arrays, which is why they were written
 * that way: a regex can tell you a rule is present, only a call can tell
 * you it is right.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/lib/dhcportal-lib.php';

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }
function piece($tier, $id = 0) {
	return array('drop_id' => $id ?: rand(1, 1e6), 'category' => 'head',
	             'slug' => 's' . ($id ?: rand(1, 1e6)), 'tier' => $tier,
	             'size' => dhcp_size($tier));
}
function board($tiers) { $o = array(); $i = 1; foreach ($tiers as $t) $o[] = piece($t, $i++); return $o; }

echo "every piece divides a row\n";
/*
 * THE PROPERTY THE WHOLE DESIGN RESTS ON. Four commons make a row, two
 * uncommons make a row, an epic IS a row. If any size stops dividing
 * DHCP_COLS the board stops being readable as rows and a piece can
 * straddle two of them filling neither -- which an earlier draft did,
 * by drawing epic as a 2x2 block, and then spent a page solving the
 * packing problem it had just invented.
 */
foreach (dhcp_sizes() as $tier => $size) {
	ok($size > 0, $tier . ' takes no space');
	ok($size % DHCP_COLS === 0 || DHCP_COLS % $size === 0,
	   $tier . ' is ' . $size . ' slots, which neither fills a row nor divides one — it can straddle');
	ok($size <= DHCP_SLOTS,
	   $tier . ' is bigger than the whole board, so the eject rule can never make room for it');
}
ok(dhcp_sizes()['mythic'] === DHCP_SLOTS,
   'a mythic no longer fills the board exactly, which is the owner\'s anchor for the whole scale');
ok(dhcp_sizes()['legendary'] * 2 === DHCP_SLOTS,
   'two legendaries no longer fill the board, which is the other half of the anchor');

echo "\nrows are tiers, and one row is the minimum\n";
ok(count(dhcp_tables()) === DHCP_ROWS,
   'there are ' . count(dhcp_tables()) . ' tables against ' . DHCP_ROWS . ' rows; they are meant to be one to one');
foreach (dhcp_tables() as $t) {
	ok(isset(DHCF_TIERS[$t]), 'the board rolls on "' . $t . '", which is not a table in DHCF_TIERS');
}
ok(dhcp_table_name(0) === null && dhcp_table_name(DHCP_COLS - 1) === null,
   'a part-filled first row can fire, so the minimum and the tier disagree');
ok(dhcp_table_name(DHCP_COLS) === 'run', 'one full row does not roll on the first table');
ok(dhcp_table_name(DHCP_SLOTS) === 'placement_1',
   'a full board does not reach the best table — that is the jackpot the whole feature hangs on');
/* Rows climb one at a time and never past the top. */
for ($r = 1; $r <= DHCP_ROWS; $r++) {
	ok(dhcp_rows($r * DHCP_COLS) === $r, $r . ' full rows does not read as ' . $r . ' rows');
	ok(dhcp_table_name($r * DHCP_COLS) === dhcp_tables()[$r - 1],
	   'row ' . $r . ' rolls on the wrong table');
}
ok(dhcp_rows(DHCP_SLOTS * 3) === DHCP_ROWS, 'an overfull board reports more rows than it has');
/* A partial row counts for nothing, which is what makes the board readable. */
/* A PART ROW COUNTS FOR NOTHING, which is what makes the board readable
   as its own meter. Checked across every partial rather than one case,
   because rounding the wrong way only shows up at some of them. */
for ($n = 1; $n < DHCP_COLS; $n++) {
	ok(dhcp_rows(DHCP_COLS + $n) === 1,
	   DHCP_COLS + $n . ' slots reads as ' . dhcp_rows(DHCP_COLS + $n) . ' rows, not 1 — a part row is being counted');
	ok(dhcp_table_name($n) === null,
	   $n . ' slots can fire, but that is less than a row');
}

echo "\nthe eject rule returns the cheapest, and always makes room\n";
/*
 * Fifteen commons and a legendary that needs two rows. Without this that
 * is eleven slots of fuel thrown away -- most of another run -- with
 * nothing to warn the player.
 */
$full = board(array_fill(0, 15, 'common'));
list($kept, $back) = dhcp_fit($full, piece('legendary', 99));
ok(dhcp_slots($kept) === DHCP_SLOTS,
   'the board came out at ' . dhcp_slots($kept) . ' slots, not exactly full');
ok(count($back) === 7, 'returned ' . count($back) . ' pieces, expected 7');
$lastIsIncoming = $kept[count($kept) - 1]['drop_id'] === 99;
ok($lastIsIncoming, 'the piece the player just chose was ejected instead of kept');

/*
 * A MIXED BOARD, because a uniform one cannot tell cheapest-first from
 * dearest-first. The first version of this test used fifteen commons,
 * where the two rules are indistinguishable and reversing the comparison
 * passed. Commons and one epic: the commons must go and the epic stay.
 */
$mixed = board(array('epic', 'common', 'common', 'common', 'common',
                     'common', 'common', 'common', 'common', 'common',
                     'common', 'common', 'common'));   /* 4 + 12 = 16, full */
list($kmix, $bmix) = dhcp_fit($mixed, piece('epic', 88));
ok(dhcp_slots($kmix) === DHCP_SLOTS, 'the mixed board did not come out full');
foreach ($bmix as $b) {
	ok($b['tier'] === 'common',
	   'ejected a ' . $b['tier'] . ' while commons were still on the board — cheapest-first is reversed');
}
$keptTiers = array();
foreach ($kmix as $k) $keptTiers[] = $k['tier'];
ok(count(array_keys($keptTiers, 'epic')) === 2,
   'both epics should survive a cheapest-first eject; kept ' . implode(',', $keptTiers));

/* A mythic onto a full board of commons: everything goes, nothing breaks. */
list($kept2, $back2) = dhcp_fit(board(array_fill(0, 16, 'common')), piece('mythic', 77));
ok(dhcp_slots($kept2) === DHCP_SLOTS && count($kept2) === 1 && $kept2[0]['drop_id'] === 77,
   'a mythic onto a full board did not clear it; got ' . count($kept2) . ' pieces, '
 . dhcp_slots($kept2) . ' slots');
ok(count($back2) === 16, 'returned ' . count($back2) . ' commons, expected all 16');

/* Nothing is worth more than the board, so there is no case where the
   rule cannot make room. Asserted by exhausting every tier. */
foreach (array_keys(dhcp_sizes()) as $tier) {
	list($k3, ) = dhcp_fit(board(array_fill(0, 16, 'common')), piece($tier, 5));
	ok(dhcp_slots($k3) <= DHCP_SLOTS,
	   'adding a ' . $tier . ' to a full board left ' . dhcp_slots($k3) . ' slots, over the max');
}
/* Room to spare means nothing is returned. */
list($k4, $b4) = dhcp_fit(board(array('common')), piece('common', 2));
ok(count($b4) === 0 && dhcp_slots($k4) === 2, 'the rule ejected from a board with room to spare');

echo "\nnothing you fed can come back\n";
/*
 * The one hard rule of the feature, and the only thing standing between
 * a player and the single most enraging outcome it could produce.
 */
$lib = (string) file_get_contents(__DIR__ . '/lib/dhcportal-lib.php');
ok(preg_match('~function dhcp_roll.*?\$fed\[\(string\)\$h\[\x27slug\x27\]\] = true;.*?if \(!isset\(\$fed\[\$d\[1\]\]\)\) return \$d;~s', $lib) === 1,
   'dhcp_roll does not exclude what was fed, so a player can be handed back the trait they just threw away');
/* BOUNDED, AND IT GIVES UP. Rejection sampling with no ceiling is an
   infinite loop the day somebody suspends enough traits; the caller
   refuses the jettison rather than being handed a fed trait. Checked
   structurally rather than by matching a `return null` that happens to
   sit next to a closing brace, which is what the first version of this
   did and it failed on a trailing comment. */
$rollFn = preg_match('~function dhcp_roll\(.*?\n\}~s', $lib, $rm) ? $rm[0] : '';
ok($rollFn !== '', 'dhcp_roll is gone');
ok(preg_match('~for \(\$i = 0; \$i < \$attempts; \$i\+\+\)~', $rollFn) === 1,
   'dhcp_roll is not bounded, so an exhausted pool spins for ever');
ok(substr_count($rollFn, 'return null;') >= 1 && preg_match('~return null;[^;]*\n\}~s', $rollFn) === 1,
   'dhcp_roll has no give-up path, so an exhausted pool returns a fed trait');
ok(strpos($lib, 'function dhcp_pool_remains') !== false,
   'there is no pre-flight, so a board with nothing left to win fires anyway');

echo "\nthe jettison cannot half-happen\n";
$end = (string) file_get_contents(__DIR__ . '/ajax/dhcportal-jettison.php');
/*
 * THE ONLY FAILURE THAT CANNOT BE APOLOGISED AWAY is traits gone and
 * nothing back. The roll is taken BEFORE anything is destroyed and the
 * whole move runs in a transaction.
 */
ok(strpos($end, '$conn->begin_transaction()') !== false, 'the jettison is not transactional');
ok(substr_count($end, '$conn->rollback()') >= 2, 'the jettison has fewer rollback paths than failure paths');
$posRoll = strpos($end, 'dhcp_roll(');
$posDel  = strpos($end, 'DELETE FROM dhc_trait_drops');
ok($posRoll !== false && $posDel !== false && $posRoll < $posDel,
   'the traits are destroyed before the roll is taken, so a failed roll costs the player their board');
/*
 * THE DOUBLE-SPEND GUARD. The delete names the owner and the affected
 * count must equal the board, so two tabs firing at once cannot take the
 * same row and a row that moved since the read is simply not deleted.
 */
ok(preg_match('~DELETE FROM dhc_trait_drops\s*\n?\s*WHERE user_id = \x27\$uid\x27 AND id IN~', $end) === 1,
   'the delete does not name the owner, so it can take a row that is no longer theirs');
ok(strpos($end, 'affected_rows !== count($ids)') !== false,
   'the delete does not check how many rows it actually took, which is the whole double-spend guard');
/* The gifts go out AFTER the commit: a failed gift must not roll back a
   jettison that succeeded. */
$posCommit = strpos($end, '$conn->commit()');
$posGift   = strpos($end, "'portalgift'");
ok($posCommit !== false && $posGift !== false && $posCommit < $posGift,
   'the redistribution runs inside the transaction, so one unreachable recipient un-does a finished jettison');

echo "\nthe traits always land on somebody\n";
/*
 * NOTHING INTO THE ETHER. The cascade widens until it finds anybody, and
 * the last rung is every active player.
 */
$rungs = dhcp_lapse_rungs();
ok($rungs[0] === (int) RETURN_GIFT_DAYS,
   'the cascade does not start at the platform\'s own definition of lapsed (' . RETURN_GIFT_DAYS . ' days)');
ok(end($rungs) === 0, 'the cascade does not end at "anybody", so a quiet week drops traits on the floor');
$sorted = $rungs; rsort($sorted);
ok($rungs === $sorted, 'the cascade does not widen monotonically, so it can skip a population');
ok(strpos($lib, 'user_id <> \'" . $ex . "\'') !== false,
   'the cascade does not exclude the submitter, so the last rung can hand somebody their own trait back');

echo "\nthe board is session state, held by copy\n";
/*
 * NO TABLE. The board is a draft of something about to be destroyed;
 * giving it durable storage bought nothing anybody asked for and cost a
 * migration and a second place for state to go stale.
 */
ok(strpos($lib, 'dhc_portal_board') === false,
   'the board still wants a table; it is session state now and a stray reference will fatal on a fresh install');
ok(!is_file(__DIR__ . '/dhcportal-schema.md'),
   'the schema doc is still there describing a table that does not exist');
ok(strpos($lib, "\$_SESSION['dhcp_board']") !== false,
   'the board is not in the session, so it is nowhere at all');
/*
 * KEYED BY USER. One browser can sign out and in as somebody else
 * without a reload, and a board inherited across that would point at
 * traits the new account does not own.
 */
/* strpos, not a regex: the first version of these two drowned in
   backslashes and reported correct code as broken. */
ok(strpos($lib, "['uid'] ?? 0) !== " . '$uid') !== false,
   'the session board is not keyed to the account, so it survives a sign-out into somebody else\'s session');
/*
 * STILL drop_ids, NOT category+slug: a player can own three copies and
 * the jettison must destroy exactly the ones staged.
 */
ok(strpos($lib, 'dhcf_available($conn, $uid)') !== false,
   'the board does not self-heal against Fighters, so a trait on a Fighter can still be jettisoned');
ok(strpos($lib, 'SELECT id, category, slug FROM dhc_trait_drops') !== false
   && strpos($lib, "WHERE user_id = '" . '$uid' . "' AND id IN") !== false,
   'the board does not resolve its ids against what the player owns, so a forged id reaches the jettison');

echo "\nboth ends announce, and neither is capped\n";
/*
 * Two sources, because they are two different events: one is "you
 * gambled and this came back", the other is "somebody threw this away
 * and the Portal found you". Cap 0 on both -- the player's side is
 * limited by the board, and the gift side is involuntary, so a cap
 * there would silently drop traits the cascade exists to place.
 */
foreach (array('portal', 'portalgift') as $src) {
	ok(dhcf_game($src) !== null, 'there is no DHCF_GAMES entry for ' . $src . ', so a drop from it cannot be labelled');
	ok(dhcf_cap($src) === 0, $src . ' is capped at ' . dhcf_cap($src) . ', which silently drops awards');
}
ok(dhcf_game('portal')['label'] !== dhcf_game('portalgift')['label'],
   'both ends announce with the same words, so a gift reads as a win');

echo "\nthe page and the UI agree with the library\n";
$page = (string) file_get_contents(__DIR__ . '/dhcportal.php');
$ui   = (string) file_get_contents(__DIR__ . '/dhcportal-ui.php');
/* The sizes reach the client from the library rather than being typed
   twice -- two copies of this table is two different games. */
ok(strpos($page, 'json_encode(dhcp_sizes())') !== false,
   'the page hardcodes the piece sizes instead of sending the library\'s');
ok(strpos($page, 'json_encode($dhcp_tables)') !== false,
   'the page hardcodes the tables instead of sending the library\'s');
ok(preg_match('~cols: <\?php echo DHCP_COLS~', $page) === 1,
   'the page hardcodes the board width');
/* The reveal composes its own art path, because the payload has none. */
$rev = (string) file_get_contents(__DIR__ . '/dhcportal-reveal.php');
ok(strpos($rev, "won.image") === false,
   'the reveal reads won.image, which dhcf_drop_payload has never returned — every win shows an empty frame');
ok(strpos($rev, "'/250/' + won.category") !== false,
   'the reveal does not compose the trait art path the way every other drop surface does');

/*
 * THE BOARD PULLS THE HI-RES SOURCE FOR ANYTHING WIDER THAN A CELL.
 *
 * The board column is a 1fr between two fixed ones, so on a wide screen
 * a cell is around 175px and a piece spanning the row is near 700px. A
 * 250px source upscaled that far is visibly soft -- and the pieces that
 * span the row are exactly the ones the player paid most to put there.
 *
 * BOTH HALVES ARE CHECKED. 1000 for everything would be bandwidth spent
 * on pixels nobody can see: a tray chip is 54px and a full board of
 * commons is sixteen cells at ~175px.
 */
ok(strpos($ui, "var px = (onBoard && size(p) > 1) ? 1000 : 250;") !== false,
   'the board does not pick its art size by how big the piece will be drawn');
ok(strpos($ui, 'art(p, true)') !== false,
   'the board does not ask for the board-sized art, so a mythic spanning the grid is upscaled 250px art');
ok(preg_match('~drawTray[\s\S]*?art\(p\)~', $ui) === 1,
   'the tray asks for board-sized art, which is 1000px pulled for a 54px chip');
/* Both sizes have to exist on disk or the choice is academic. */
foreach (array(250, 1000) as $px) {
	ok(is_dir(__DIR__ . '/' . dhcf_art_dir() . '/' . $px),
	   'there is no ' . $px . 'px art directory, so that branch 404s');
}
/* Row one is the bottom row: a board fills upward. */
ok(strpos($ui, 'flex-direction: column-reverse') !== false,
   'the board fills downward, so the tier climbs the wrong way');

echo "\nthree columns, and the art is never a dependency\n";
/*
 * PORTAL, BOARD, SPARES -- with the picker on the right where a trait
 * picker lives everywhere else on the platform. Named grid areas, so the
 * phone can restack without the markup caring.
 */
ok(strpos($ui, 'grid-template-areas: "portal board pick"') !== false,
   'the three columns are not in the owner\'s order: Portal, board, spares');
/*
 * NO <section> ON THIS PAGE.
 *
 * flexbox.css:1735 starts every <section> on the platform at opacity:0
 * for a scroll reveal that adds .active. All three columns were
 * <section>, so they rendered, measured 1960px wide, and were
 * completely invisible -- reported as "nothing is displayed on the page
 * at all", which is exactly what it was. Asserted because <section> is
 * the obvious tag for a page of three panels and nothing about the
 * markup would warn the next person.
 */
/* THE MARKUP, NOT THE COMMENTARY. The note above that rule in the page
   names <section> in order to explain why it is not used, so a plain
   search over the source matches my own prose and reports the thing it
   is warning about. token_get_all: only inline HTML is looked at. */
$pageHtml = '';
foreach (token_get_all($page) as $t) {
	if (is_array($t) && $t[0] === T_INLINE_HTML) $pageHtml .= $t[1];
}
ok(stripos($pageHtml, '<section') === false,
   'the page uses <section>, which flexbox.css starts at opacity 0 — it will render invisible');
/* And the rule it is dodging is real and still there; if it ever goes,
   this guard is why the page stopped depending on it. */
ok(preg_match('~\nsection \{\s*\n\topacity: 0;~', (string) file_get_contents(__DIR__ . '/dist/flexbox.css')) === 1,
   'flexbox.css no longer hides <section>, so this guard is documenting a trap that no longer exists');
/*
 * AND THE GRID HAS TO SAY HOW BIG IT WANTS TO BE. This page sits in the
 * platform's full-width column, 1960px on a wide monitor, and a 1fr
 * middle column there gave a 4x4 board with 314px cells.
 */
ok(preg_match('~\.dhcp-rig \{[^}]*max-width: \d+px~s', $ui) === 1,
   'the rig is uncapped, so the board grows to whatever the 2000px column gives it');
ok(substr_count($ui, 'grid-template-areas') >= 3,
   'there is only one layout, so the three columns are still three columns on a phone');
/* BOARD FIRST on a phone: it is what the page is for, and the spares
   list is long enough to push everything else off the screen. */
ok(strpos($ui, 'grid-template-areas: "board" "portal" "pick"') !== false,
   'the phone stack does not lead with the board');

/*
 * THE ART DEPLOYS BY FTP, OUTSIDE THIS REPO. Until it lands, both panels
 * have to be exactly what was there before -- a colour, no broken frame,
 * nothing moved. The colour is declared separately from the image for
 * that reason, never in a `background:` shorthand that would take the
 * colour down with a missing file.
 */
foreach (array('operator' => '#071320', 'vortex' => '#0a0410') as $img => $colour) {
	ok(strpos($ui, 'url(images/portal/' . $img . '.webp)') !== false,
	   'the ' . $img . ' art is not referenced, so the page is still all CSS');
	ok(strpos($ui, 'background-color: ' . $colour) !== false,
	   'the ' . $img . ' panel has no fallback colour, so a missing upload is a hole');
}
/*
 * WHOLE PICTURES, NOT CROPS. Both sources are 1024x1536, so both panels
 * take that ratio and `contain` -- which makes cropping impossible
 * rather than merely unlikely. The first pass cropped the vortex to a
 * square and threw away the machinery and the flames, and cropped the
 * operator to a band that lost most of the figure.
 */
foreach (array('.dhcp-boardwrap', '.dhcp-vortex') as $sel) {
	$rule = preg_match('~' . preg_quote($sel, '~') . ' \{[^}]*\}~s', $ui, $rm) ? $rm[0] : '';
	ok($rule !== '', $sel . ' has no rule');
	ok(strpos($rule, 'aspect-ratio: 1024 / 1536') !== false,
	   $sel . ' is not the shape of the art it carries, so the art gets cropped to fit');
	ok(strpos($rule, 'background-size: contain') !== false,
	   $sel . ' uses cover, which crops; the whole picture is meant to show');
}
/*
 * AND NO SECOND ARROW. The vortex art carries its own green plate and
 * down-arrow; the page was stacking a CSS copy directly above the real
 * one. Two arrows saying the same thing, one redundant by construction.
 */
ok(strpos($ui, '.dhcp-sign') === false && strpos($page, 'dhcp-sign') === false,
   'the drawn deposit sign is back, duplicating the one already in the art');
/* The readout lives outside the art panel: inside, it was unreadable
   over the operator, and the panel has a fixed ratio now. */
ok(preg_match('~</div>\s*<\?php /\* OUTSIDE THE ART PANEL~s', $page) === 1,
   'the readout is back inside the art panel, where it is illegible and stretches the frame');
ok(preg_match('~background:\s*url\(images/portal~', $ui) !== 1,
   'the art is in a background shorthand, which takes the fallback colour down with it');
/* Sixteen tiles have to read against it. */
ok(strpos($ui, '.dhcp-boardwrap::before') !== false,
   'the board art has no scrim, so the pieces sit on a busy picture at full strength');

echo "\nthe spares browse like the assembler\n";
/*
 * CATEGORY TABS AND A RARITY FILTER. The first build dumped all eight
 * categories into one grid, which is unusable past a few dozen pieces --
 * and a third way to browse the same traits would be worse than either.
 */
/* AND 'all' IS THE DEFAULT. The Portal opens on the whole pile laid out,
   because seeing the scale of the junk is half of what makes a clear-out
   worth starting. The tabs narrow it; they are not a gate. */
ok(strpos($ui, "var cat = 'all', rar = null;") !== false,
   'the picker does not open on the whole pile, so the quantity is hidden behind a tab');
ok(strpos($ui, 'function drawTabs') !== false, 'there are no category tabs');
ok(preg_match('~cats = Object\.keys\(CATNAME\)\.filter~', $ui) === 1,
   'every category gets a tab whether the player holds any or not, so most lead nowhere');
/* The counts have to fall as pieces move onto the board, or they lie. */
ok(preg_match('~function spare\(\).*?return D\.tray\.filter~s', $ui) === 1,
   'the tray is not filtered against the board, so a staged piece shows in both places');
ok(preg_match('~drawTabs\(list\)~', $ui) === 1,
   'the tabs are not rebuilt from the live list, so their counts go stale');
/*
 * "Show me my commons" is the whole clear-out, so the rarity filter is
 * not optional decoration here.
 */
ok(strpos($ui, "rar = (rar === t) ? null : t") !== false,
   'the rarity filter cannot be switched off once set');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
