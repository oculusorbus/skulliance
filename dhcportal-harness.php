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

/*
 * EVERY SOURCE FILE READ IN ONE PLACE, AT THE TOP.
 *
 * These used to be defined next to the first check that wanted them,
 * which meant a check added above that point got null and PHP's
 * strpos(null, ...) is merely deprecated, not fatal -- so the assertion
 * quietly passed on nothing. That has now happened three times in this
 * file. Reading them all up front makes the order impossible to get
 * wrong.
 */
$lib     = (string) file_get_contents(__DIR__ . '/lib/dhcportal-lib.php');
$page    = (string) file_get_contents(__DIR__ . '/dhcportal.php');
$ui      = (string) file_get_contents(__DIR__ . '/dhcportal-ui.php');
$rev     = (string) file_get_contents(__DIR__ . '/dhcportal-reveal.php');
$end     = (string) file_get_contents(__DIR__ . '/ajax/dhcportal-jettison.php');
$end0    = $end;
$dropsrc = (string) file_get_contents(__DIR__ . '/dhc-dropmodal.php');
$fighters = (string) file_get_contents(__DIR__ . '/dhcfighters-lib.php');
$flexcss = (string) file_get_contents(__DIR__ . '/dist/flexbox.css');

/* And a guard on the guards: an empty read means every strpos below is
   searching nothing and passing for it. */
foreach (array('lib' => $lib, 'page' => $page, 'ui' => $ui, 'rev' => $rev,
               'endpoint' => $end, 'dropmodal' => $dropsrc, 'fighters-lib' => $fighters, 'flexbox.css' => $flexcss) as $n => $src) {
	ok($src !== '', "could not read $n, so every source check below is searching an empty string");
}

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
ok(preg_match('~function dhcp_roll.*?if \(!isset\(\$fed\[\$d\[1\]\]\)\) return \$d;~s', $lib) === 1,
   'dhcp_roll does not exclude what was fed, so a player can be handed back the trait they just threw away');
/*
 * AND BOTH HALVES OF A PAIR. Excluding only the slug a piece is handled
 * by would let the Portal return the strap of the weapon it has just
 * swallowed -- which is not the same trait, but is just as galling.
 */
ok(substr_count($lib, "isset(\$h['slugs']) ? \$h['slugs'] : array(\$h['slug'])") >= 2,
   'the roll and the pre-flight do not exclude both halves of a tethered pair');
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

echo "\na tethered pair is one piece\n";
/*
 * Seven weapons on the platform are two rows that mean one thing: a
 * weapon and the sash or strap it hangs from. The ASSEMBLER REFUSES TO
 * SAVE a Fighter holding one without the other, so letting a player
 * jettison half quietly bricks the half they keep -- unusable,
 * uncompletable, and not obviously broken until they try.
 */
ok(count(DHCF_TETHERED) > 0, 'there are no tethered pairs, so this whole section is checking nothing');
foreach (DHCF_TETHERED as $a => $b) {
	ok(dhcf_tethered_partner($a) === $b, $a . ' does not point back at ' . $b);
	ok(dhcf_tethered_partner($b) === $a, 'the tether is one-way: ' . $b . ' does not point at ' . $a);
}
/* Collapsing: two rows in, ONE piece out, carrying both ids. */
$pair = array_keys(DHCF_TETHERED)[0];
$mate = DHCF_TETHERED[$pair];
$rows = array(
	array('drop_id' => 11, 'category' => 'weapon', 'slug' => $pair, 'tier' => 'epic'),
	array('drop_id' => 12, 'category' => 'weapon', 'slug' => $mate, 'tier' => 'epic'),
);
$made = dhcp_pieces($rows);
ok(count($made) === 1, 'a tethered pair came out as ' . count($made) . ' pieces, not one');
ok(count($made[0]['drop_ids']) === 2, 'the piece does not carry both rows, so one would be left behind');
ok((int) $made[0]['paired'] === 1, 'the piece is not marked as a pair');
/*
 * ONE PIECE COSTS ONE TRAIT'S SLOTS, not two. One draw produced both
 * rows -- which is why dhcf_award() marks the second with
 * DHCF_PAIRED_MARK and excludes it from the daily cap.
 */
ok(dhcp_slots($made) === dhcp_size('epic'),
   'a pair takes ' . dhcp_slots($made) . ' slots; it should cost one trait\'s worth, '
 . dhcp_size('epic'));
/* And that is because dhcp_slots() reads the TIER, never how many rows
   a piece happens to carry. A pair is two rows and one trait. */
ok(preg_match('~function dhcp_slots.*?dhcp_size\(isset\(\$h\[\x27tier\x27\]\)~s', $lib) === 1,
   'dhcp_slots does not size by tier, so a two-row pair can be charged twice');
/* An unmatched half stays visible: a player can hold one already, and
   hiding it would leave a trait they can neither use nor get rid of. */
$lone = dhcp_pieces(array(array('drop_id' => 9, 'category' => 'weapon', 'slug' => $pair, 'tier' => 'epic')));
ok(count($lone) === 1 && count($lone[0]['drop_ids']) === 1 && (int) $lone[0]['paired'] === 0,
   'a lone half is hidden or mis-paired, so it can never be cleared out');
/* Three copies, two of which pair: nothing may be used twice. */
$three = dhcp_pieces(array(
	array('drop_id' => 1, 'category' => 'weapon', 'slug' => $pair, 'tier' => 'epic'),
	array('drop_id' => 2, 'category' => 'weapon', 'slug' => $pair, 'tier' => 'epic'),
	array('drop_id' => 3, 'category' => 'weapon', 'slug' => $mate, 'tier' => 'epic'),
));
$ids = array();
foreach ($three as $pc) foreach ($pc['drop_ids'] as $i) $ids[] = $i;
sort($ids);
ok($ids === array(1, 2, 3), 'a row was dropped or paired twice: ' . implode(',', $ids));
ok(count($three) === 2, 'three rows with one pair came out as ' . count($three) . ' pieces, not two');
/* And they travel together out the other side. */
ok(preg_match('~\$to = dhcp_pick_recipient.*?foreach \(\$slugs as~s', $end0) === 1,
   'the recipient is picked per slug rather than per piece, so a pair is split between two players');
ok(strpos($end0, 'DHCF_PAIRED_MARK') !== false,
   'the second half of a gifted pair is not marked, so it counts as a drop of its own');

echo "\nthe jettison cannot half-happen\n";
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

echo "\nthe rarity colours are the platform's, not invented\n";
/*
 * THE FIRST VERSION PICKED THESE BY EYE off the launchpad's section
 * accents and came out with epic, legendary and mythic rotated. The
 * owner spotted it from memory: legendary is the orange one, mythic is
 * the pink one.
 *
 * CHECKED AGAINST dhc-dropmodal.php RATHER THAN AGAINST A LITERAL. Two
 * surfaces already carried this palette and agreed with each other by
 * luck; pinning the Portal to a copy of the numbers would make three
 * copies. Reading the drop modal's own TIER map means they cannot
 * drift apart without this failing.
 */
$mine = dhcf_tier_colours();
ok(count($mine) === 5, 'the palette does not cover all five tiers');
foreach ($mine as $tier => $hex) {
	$found = preg_match('~' . $tier . ':\s*\{color:\x27(#[0-9a-f]{6})\x27~i', $dropsrc, $dm)
	       ? strtolower($dm[1]) : '';
	ok($found !== '', 'dhc-dropmodal.php has no colour for ' . $tier . ' to check against');
	ok($found === strtolower($hex),
	   $tier . ' is ' . $hex . ' here and ' . $found . ' in the drop modal — the same trait '
	 . 'would be two colours on two screens');
}
/* The Portal must consume that function rather than keep its own list.
   Read here rather than relying on $ui and $rev, which are loaded forty
   lines further down -- the first version of this checked two variables
   that did not exist yet and failed on correct code. */
foreach (array('dhcportal-ui.php', 'dhcportal-reveal.php') as $pf) {
	ok(strpos((string) file_get_contents(__DIR__ . '/' . $pf), 'dhcf_tier_colours()') !== false,
	   $pf . ' hardcodes the rarity palette instead of reading the one source');
}

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

echo "\nthe player is paid before anyone else is told\n";
/*
 * THE TEN SECOND WAIT. Everything below the commit -- up to sixteen
 * recipient cascades and twenty-three dhcf_commit_drop() calls, each
 * posting to Discord over the network -- used to run BEFORE the JSON
 * was written. The reveal could not begin until every lapsed player on
 * the platform had been pinged.
 */
/*
 * ORDERING IS READ FROM CODE, NOT FROM PROSE. The comment above the
 * gift loop explains the cascade by name, so a plain strpos found
 * dhcp_pick_recipient() in the explanation -- before the answer -- and
 * reported the bug it was written to catch. That mistake has now been
 * made four times in this project, so: strip the comments first.
 */
$endCode = '';
foreach (token_get_all($end) as $t) {
	if (is_array($t)) {
		if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) continue;
		$endCode .= $t[1];
	} else {
		$endCode .= $t;
	}
}
ok(strpos($endCode, 'ORDERING IS READ FROM CODE') === false
   && strlen($endCode) < strlen($end),
   'comment stripping did nothing, so these ordering checks can match explanations again');

$ans_at   = strpos($endCode, 'dhcp_answer(array(');
$gift_at  = strpos($endCode, "'portalgift'");
$pick_at  = strpos($endCode, 'dhcp_pick_recipient(');
ok($ans_at !== false, 'the endpoint no longer answers the player separately, so it answers last again');
/* UNCONDITIONALLY. The position checks below are satisfied by a call
   that sits in the right place and never runs, which is what a
   disabled answer looks like. */
ok($ans_at !== false && preg_match('~(?:^|\n)dhcp_answer\(array\(~', $endCode) === 1,
   'the answer is nested inside a branch, so it is not guaranteed to run before the redistribution');
ok($ans_at !== false && $gift_at !== false && $ans_at < $gift_at,
   'the gift loop runs before the player is answered, so the reveal waits on every Discord post');
ok($ans_at !== false && $pick_at !== false && $ans_at < $pick_at,
   'the recipient cascade runs before the player is answered, so the reveal waits on it too');

/* AND THE CONNECTION IS ACTUALLY RELEASED. Writing the body early
   achieves nothing on its own: without a declared length the browser
   holds the socket open waiting for EOF and the player waits exactly as
   long as before. */
ok(strpos($endCode, "header('Content-Length: ' . strlen(\$body))") !== false,
   'the early answer declares no length, so the browser waits for EOF and nothing is gained');
ok(strpos($endCode, 'ignore_user_abort(true)') !== false,
   'the redistribution depends on a connection that is being closed, with no ignore_user_abort');
ok(strpos($endCode, 'fastcgi_finish_request') !== false
   && strpos($endCode, 'litespeed_finish_request') !== false,
   'only one SAPI is handled, so the handoff silently does nothing under the others');

/* The session lock is the other thing that would hold the player up:
   the reveal reloads the page, and that reload queues behind this
   request until the session is written. */
ok(strpos($endCode, 'session_write_close()') !== false,
   'the session stays locked through the redistribution, so the post-reveal page reload blocks on it');

/* Nothing may try to answer twice: dhcp_out() echoes and exits, and
   after a Content-Length has been declared a second body is garbage. */
$tail = $ans_at !== false ? substr($endCode, $ans_at + 10) : '';
ok(strpos($tail, 'dhcp_out(') === false,
   'something still calls dhcp_out() after the answer has been sent, which writes past the declared length');

echo "\nthe pieces travel from the board to the Portal\n";
/*
 * THE REPORT: "it displays over the grid instead of the portal, the
 * traits don't get sent into the portal, there's just the animation
 * effect over the grid and the items in the grid disappear."
 *
 * Three separate faults behind one description, so three checks.
 */

/* ONE: the vortex opened at the middle of the VIEWPORT, which on a
   three-column page is the middle of the board. It has to find the
   Portal element. */
ok(strpos($rev, "querySelector('.dhcp-vortex')") !== false,
   'the reveal does not look up the Portal element, so it plays wherever the viewport centre happens to be');
ok(preg_match('~var cx = AX, cy = AY;~', $rev) === 1,
   'the vortex is drawn at the viewport centre again rather than at the Portal');
ok(preg_match('~var cx = r\.width / 2, cy = r\.height / 2;~', $rev) !== 1,
   'the old viewport-centre vortex is back');

/* TWO: the veil was .93 opaque from the first frame, so the board was
   gone before anything could be seen leaving it. It starts light and
   only closes once the board is empty. */
preg_match('~#dhcpVeil \{[^}]*?background-color: rgba\([^)]*?,\s*([\d.]+)\)~s', $rev, $m_v0);
preg_match('~#dhcpVeil\.deep \{[^}]*?background-color: rgba\([^)]*?,\s*([\d.]+)\)~s', $rev, $m_v1);
$veil0 = count($m_v0) === 2 ? (float) $m_v0[1] : 1.0;
$veil1 = count($m_v1) === 2 ? (float) $m_v1[1] : 0.0;
ok($veil0 <= 0.45,
   'the veil opens at ' . $veil0 . ' opaque, so the board is hidden before the pieces can be seen leaving it');
ok($veil1 >= 0.85,
   'the veil never closes over the page, so the result lands on a lit board');
/* IN THE BEAT, not just somewhere in the file. There is a second
   copy of this on the reduced-motion path, which opens straight to the
   result and has no travel to watch -- so a plain search for the call
   passed with the animated path's darkening deleted. */
preg_match('~at\(GIVE, function \(\) \{(.*?)\}\);~s', $rev, $m_beat);
ok(count($m_beat) === 2 && strpos($m_beat[1], "veil.classList.add('deep')") !== false,
   'the veil never darkens on the held beat, so the result lands on a lit board');
ok(substr_count($rev, "veil.classList.add('deep')") >= 2,
   'the reduced-motion path no longer darkens the veil, so it opens the result over a tint');

/* THREE: particles crawled inward at a flat 1.6-3.0px a frame. From
   400px out that is 150+ frames inside a 90-frame take, so most never
   arrived -- the speed has to come from the distance. */
ok(strpos($rev, 'vr: -(rad - 6) /') !== false,
   'the inward speed is a constant again, so distant particles never reach the Portal inside the take');
ok(strpos($rev, 'vr: -(1.6 + Math.random() * 1.4)') === false,
   'the old flat inward speed is back');

/* And what crosses the gap is the trait, taken from the <img> already
   in the board piece -- recognisable, and no second request for art the
   page has already decoded. */
ok(strpos($rev, "node.querySelector('img')") !== false,
   'the reveal no longer carries the trait art across, so only abstract sparks travel');
ok(strpos($rev, 'img.complete && img.naturalWidth') !== false,
   'the travelling sprite does not check the image decoded, so drawImage can throw on a half-loaded trait');

/*
 * AND THE SLOT EMPTIES AS THE PIECE LEAVES IT. The veil is a tint, so
 * the board is visible throughout -- which means a DOM piece left in
 * place while its canvas copy flies off is simply two of everything.
 * It has to go on the same born beat the sprite starts on.
 */
ok(preg_match('~at\(Math\.round\(born \* 1000\), function \(\) \{\s*'
            . "node\.style\.visibility = 'hidden';~s", $rev) === 1,
   'the board pieces are not hidden as they depart, so each trait is drawn twice: in its slot and in flight');
/* display would reflow the pieces that are left; they are positioned
   off a cell grid and must not move while the board empties. */
ok(strpos($rev, "node.style.display = 'none'") === false,
   'a departing piece is removed from layout, which shifts the pieces still waiting to go');

/* The handover must be pixel-identical: the piece's <img> carries
   object-fit: contain, so the painted art is letterboxed inside the
   button box and drawing the sprite at the button's rect stretches it. */
ok(strpos($rev, 'Math.min(sw / img.naturalWidth, sh / img.naturalHeight)') !== false,
   'the sprite is drawn at the button box rather than the contained art, so the handover jumps');

echo "\nthe Portal's win is revealed once, not twice\n";
/*
 * dhcf_commit_drop() parks a win in $_SESSION['dhcf_unseen'], and
 * dhc-dropmodal.php -- included from header.php on EVERY page -- flushes
 * that queue. The Portal runs its own vortex reveal and then reloads the
 * page behind it, so the generic trait modal opened on the same trait
 * the player had just watched arrive.
 */
$commit_fn = new ReflectionFunction('dhcf_commit_drop');
$commit_params = array();
foreach ($commit_fn->getParameters() as $cp) $commit_params[$cp->getName()] = $cp;
ok(isset($commit_params['reveal']),
   'dhcf_commit_drop() has no $reveal flag, so a caller with its own win screen cannot opt out of the generic one');
ok(isset($commit_params['reveal']) && $commit_params['reveal']->isOptional()
   && $commit_params['reveal']->getDefaultValue() === true,
   '$reveal does not default to true, so every game that wants the generic modal silently lost it');
/* $announce is its twin and must stay independent: opting out of the
   reveal must not quietly stop Discord hearing about the win. */
ok(isset($commit_params['announce']) && $commit_params['announce']->isOptional()
   && $commit_params['announce']->getDefaultValue() === true,
   '$announce is gone or no longer defaults true, so the Portal win stopped being announced');

/* The queue push is actually gated on it, rather than the flag existing
   and doing nothing. */
ok(preg_match('~if \(\$reveal\s*\n\s*&&\s*session_status\(\) === PHP_SESSION_ACTIVE~', $fighters) === 1,
   '$reveal exists but does not gate the dhcf_unseen push, so it changes nothing');

/* And the Portal passes it. Both trailing arguments, since $reveal sits
   after $announce and cannot be reached without it. */
ok(preg_match("~dhcf_commit_drop\\(\\\$conn, \\\$uid,[^;]*'portal', \\\$detail, false, false\\)~", $end) === 1,
   'the Portal no longer opts out of BOTH the generic reveal and the immediate announcement');
/*
 * THE ANNOUNCEMENT IS DEFERRED, NOT DROPPED. One jettison posts up to
 * two dozen Discord messages, and the player's own win going first
 * buries it under every gift that follows -- so it is skipped at the
 * commit and posted at the very end, where they will actually find it.
 * Asserting it is still sent matters more than asserting it is skipped:
 * passing false to $announce and then forgetting to post would be a
 * silent loss.
 */
ok(strpos($endCode, "dhcf_notify_drop(\$conn, \$uid, \$drop, 'portal')") !== false,
   'the Portal win is never announced at all now, having been skipped at the commit');
$end_notify = strpos($endCode, 'dhcf_notify_drop');
$end_gifts  = strpos($endCode, "'portalgift'");
ok($end_notify !== false && $end_gifts !== false && $end_notify > $end_gifts,
   'the player\'s own announcement fires before the gift loop again, so it is buried under the gifts');
/* The gifts going OUT must keep it: those land on other players, who
   have no Portal reveal of their own and whose modal is the whole
   woo-back. */
ok(preg_match("~'portalgift', \\\$detail\\)~", $end) === 1,
   'the outgoing gifts changed shape, so the recipients may have lost their reveal');

echo "\nthe tray is the whole pile, so a piece can come back\n";
/*
 * THE BUG: taking a piece off the board did not restore it to the
 * selector, only a page load did. The page built the tray inline and
 * skipped whatever was staged, so the array sent to the client never
 * held the staged copies -- the client removed a piece from the board,
 * looked for it in the tray, and it was not there to find.
 *
 * The guard is structural: dhcp_tray_pieces() takes rows, free counts
 * and rarity, and nothing else. It CANNOT subtract the board.
 */
$tray_fn = new ReflectionFunction('dhcp_tray_pieces');
ok($tray_fn->getNumberOfParameters() === 3,
   'dhcp_tray_pieces() takes a fourth argument, which is where the board crept back in');
$tray_params = array();
foreach ($tray_fn->getParameters() as $tp) $tray_params[] = $tp->getName();
ok(!in_array('board', $tray_params, true) && !in_array('staged', $tray_params, true),
   'dhcp_tray_pieces() can see the board again, so it can pre-subtract and lose pieces: '
   . implode(', ', $tray_params));

/* And the page hands it every row, rather than filtering first. */
ok(strpos($page, 'dhcp_tray_pieces(') !== false,
   'the page builds the tray inline again instead of through the pure builder');
ok(strpos($page, '$staged[') === false,
   'the page is filtering staged traits out of the tray again, which is the bug itself');

/* THE QUANTITY HALF, which the old inline version also got wrong: it
   skipped staged rows BEFORE counting against the free total, so a
   staged copy did not consume its own quota. Three copies held, two
   free: the tray must show two whatever is staged, never three. */
$fx_free   = array('head' => array('visor' => array('free' => 2)));
$fx_rarity = array('head' => array('visor' => array('common')));
$fx_rows   = array(
	array('id' => 11, 'category' => 'head', 'slug' => 'visor'),
	array('id' => 12, 'category' => 'head', 'slug' => 'visor'),
	array('id' => 13, 'category' => 'head', 'slug' => 'visor'),
);
$fx_tray = dhcp_tray_pieces($fx_rows, $fx_free, $fx_rarity);
ok(count($fx_tray) === 2,
   'three copies with two free produced ' . count($fx_tray) . ' tray entries, not 2');

/* THE RESTORE ITSELF. Whatever the board holds, the piece is still in
   the pile the client is given -- that is what it comes back into. */
$fx_ids = array();
foreach ($fx_tray as $fp) $fx_ids[] = $fp['drop_id'];
ok(in_array(11, $fx_ids, true),
   'the first copy is missing from the tray, so taking it off the board has nowhere to put it');

/* Cheapest first: the clear-out order, and the rares out of reach of a
   stray click. */
/* Fed in cheapest-first ALREADY, so a sort that merely preserves input
   order -- or reverses it -- cannot pass by luck. The check is that the
   mythic ends last however it arrives, so it runs both ways round. */
$fx_sfree = array('head' => array('crown' => array('free' => 1), 'cap' => array('free' => 1)));
$fx_srar  = array('head' => array('crown' => array('mythic'), 'cap' => array('common')));
foreach (array(array('cap', 'crown'), array('crown', 'cap')) as $fx_order) {
	$fx_sort = dhcp_tray_pieces(
		array(array('id' => 1, 'category' => 'head', 'slug' => $fx_order[0]),
		      array('id' => 2, 'category' => 'head', 'slug' => $fx_order[1])),
		$fx_sfree, $fx_srar);
	ok(count($fx_sort) === 2 && $fx_sort[0]['tier'] === 'common'
	   && $fx_sort[1]['tier'] === 'mythic',
	   'the tray does not lead with the cheapest when the rows arrive '
	   . $fx_order[0] . '-first, so the rares sit under the cursor');
}

/* The client takes the board out itself, and must do it by every id a
   piece carries: a pair reaches the tray ordered by id and the board in
   staged order, so their primary ids can differ. */
ok(strpos($ui, 'function spare()') !== false && strpos($ui, 'ids(p).forEach') !== false,
   'spare() compares primary drop_ids again, so a staged pair stays sitting in the tray');

echo "\nthe page and the UI agree with the library\n";
/* The sizes reach the client from the library rather than being typed
   twice -- two copies of this table is two different games. */
ok(strpos($page, 'json_encode(dhcp_sizes())') !== false,
   'the page hardcodes the piece sizes instead of sending the library\'s');
ok(strpos($page, 'json_encode($dhcp_tables)') !== false,
   'the page hardcodes the tables instead of sending the library\'s');
ok(preg_match('~cols: <\?php echo DHCP_COLS~', $page) === 1,
   'the page hardcodes the board width');
/* The reveal composes its own art path, because the payload has none. */
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
ok(preg_match('~\nsection \{\s*\n\topacity: 0;~', $flexcss) === 1,
   'flexbox.css no longer hides <section>, so this guard is documenting a trap that no longer exists');
/*
 * AND THE GRID HAS TO SAY HOW BIG IT WANTS TO BE -- within a window, at
 * both ends. Both ends have been wrong on this page: a bare 1fr middle
 * column in the platform's 1960px content column gave a 4x4 board with
 * 314px cells, and the 1500px cap that fixed that left a few hundred
 * pixels of dead page either side of the rig.
 *
 * So rather than assert a cap exists -- which the old check did, and
 * which both of those broken layouts satisfied -- read the cap and the
 * track ratios out of the CSS and work out the board the pair of them
 * actually produce.
 */
preg_match('~\.dhcp-rig \{[^}]*?max-width: (\d+)px~s', $ui, $m_cap);
preg_match('~\.dhcp-rig \{[^}]*?gap: (\d+)px~s', $ui, $m_gap);
preg_match('~\.dhcp-rig \{[^}]*?grid-template-columns: minmax\(0,([\d.]+)fr\) '
         . 'minmax\(0,([\d.]+)fr\) minmax\(0,([\d.]+)fr\)~s', $ui, $m_fr);
ok(count($m_cap) === 2 && count($m_gap) === 2 && count($m_fr) === 4,
   'the rig is not a capped three-ratio grid any more, so the geometry below is not measuring the page');

$rig_cap  = count($m_cap) === 2 ? (int) $m_cap[1] : 0;
$rig_gap  = count($m_gap) === 2 ? (int) $m_gap[1] : 0;
$fr_total = count($m_fr) === 4 ? ((float) $m_fr[1] + (float) $m_fr[2] + (float) $m_fr[3]) : 0.0;
$fr_board = count($m_fr) === 4 ? (float) $m_fr[2] : 0.0;

/* THE GUTTERS. The content column is the platform's 2000px .container,
   so anything the cap leaves under that is dead page either side. 1500
   left 500px of it, which is what got reported. */
ok($rig_cap >= 1900, 'the rig cap leaves ' . (2000 - $rig_cap)
   . 'px of unused page either side of the three columns');

/* THE BOARD ITSELF, from the cap and the ratios: wide enough that a
   cell is a readable tile, and not so wide that its 2:3 panel is taller
   than a screen. 1fr gave 1256px here (314px cells); 1500px/400-1fr-340
   gave 720. */
$board_w = $fr_total > 0 ? ((($rig_cap - 2 * $rig_gap) * $fr_board) / $fr_total) : 0;
$cell_w  = ($board_w - 2 * 16 - 3 * 6) / DHCP_COLS;   /* padding 16, three 6px gaps */
$panel_h = $board_w * 1536 / 1024;                     /* the art's own ratio */
ok($cell_w >= 120 && $cell_w <= 220,
   'the board cell computes to ' . round($cell_w) . 'px, outside the 120-220px a trait tile reads at');
ok($panel_h <= 1250,
   'the board panel computes to ' . round($panel_h) . 'px tall, past what a screen shows at once');

/*
 * THE BLOCK RIDES AT THE TOP OF THE PANEL, and the scrim is spent
 * there. Centred, the grid sat across the middle of the figure and the
 * scrim had to be heavy over most of the picture to keep sixteen tiles
 * legible, which is what muddied the art. The two go together: if the
 * block is ever centred again the scrim has to come back, so assert
 * both or neither.
 */
preg_match('~\.dhcp-boardwrap \{[^}]*?justify-content: ([a-z-]+)~s', $ui, $m_jc);
ok(count($m_jc) === 2 && $m_jc[1] === 'flex-start',
   'the board block is not top-aligned in its panel, so the scrim has to cover the middle of the art');

/* Every stop below the halfway mark is near clear, so the lower art is
   seen at full strength. */
preg_match('~\.dhcp-boardwrap::before \{.*?linear-gradient\(180deg,(.*?)\);~s', $ui, $m_sc);
$scrim_low = 0.0;
if (count($m_sc) === 2 && preg_match_all('~rgba\([^)]*?,\s*([\d.]+)\)\s+(\d+)%~',
    $m_sc[1], $stops, PREG_SET_ORDER)) {
	foreach ($stops as $st) {
		if ((int) $st[2] >= 50) $scrim_low = max($scrim_low, (float) $st[1]);
	}
}
ok(count($m_sc) === 2 && $scrim_low <= 0.2,
   'the scrim is still ' . $scrim_low . ' opaque over the bottom half of the art, which is what muddied it');

/*
 * THE COLUMNS END TOGETHER. With align-items: start each column stopped
 * wherever its contents ran out -- the spares panel finished about
 * 500px up the page with empty column under it -- and the tray scrolled
 * 372 traits inside a fixed 420px box while that space sat there.
 */
/* NOT stretch. A grid row sizes to its items' content height, and the
   tray's content is 372 traits -- about 4600px at seven across. Letting
   the columns share a height made the tray set the page height, and the
   portal column ended up halfway down the page. */
preg_match('~\.dhcp-rig \{[^}]*?align-items: ([a-z]+)~s', $ui, $m_ai);
ok(count($m_ai) === 2 && $m_ai[1] !== 'stretch',
   'the rig columns stretch, so the tray\'s full trait list sets the height of the whole page');

/* The tray is the one list with no natural end, so the panel holding it
   is the one panel that has to be told how tall to be -- from the
   window, which is neither its contents nor its siblings. */
/* ALL the .dhcp-pick blocks, not the first one: the selector appears
   three times -- the grid-area line, the panel itself, and the phone
   override -- and matching the first found grid-area and reported the
   cap missing while it was sitting in the second. */
preg_match_all('~\.dhcp-pick \{([^}]*)\}~s', $ui, $m_pk);
$pick = count($m_pk[1]) ? implode(' ', $m_pk[1]) : '';
ok(count($m_pk[1]) >= 2, 'the spares panel rules moved, so the checks below are reading the wrong block');
ok($pick !== '' && preg_match('~max-height: calc\(100dvh~', $pick) === 1,
   'the spares panel has no viewport height cap, so 372 traits stretch the page instead of scrolling');
ok($pick !== '' && preg_match('~max-height: calc\(100vh~', $pick) === 1,
   'the spares panel caps in dvh with no vh fallback, so it has no height where dvh is unknown');

preg_match('~\.dhcp-tray \{([^}]*)\}~s', $ui, $m_tr);
$tray = count($m_tr) === 2 ? $m_tr[1] : '';
ok($tray !== '' && strpos($tray, 'flex: 1') !== false,
   'the tray does not flex, so it cannot take the height the stretched column gives it');
ok($tray !== '' && preg_match('~max-height: \d+px~', $tray) !== 1,
   'the tray is back inside a fixed-height box, which is what left the column empty below it');
/* An explicit min-height is load-bearing: a flex item defaults to
   min-height auto, which floors at content height and kills overflow-y. */
ok($tray !== '' && preg_match('~min-height: \d+px~', $tray) === 1,
   'the tray has no explicit min-height, so its auto floor cancels overflow-y and it grows forever');

/*
 * A PLACED TRAIT GETS A PLATE, AN EMPTY SLOT DOES NOT. Both halves
 * matter and they have been wrong in both directions: every cell filled
 * at .5 read as a slab over the figure, and no fill anywhere left a
 * placed trait, which is a mostly-dark transparent PNG, invisible
 * against the lit art. So the fill belongs under the piece only, and it
 * has to be heavier than the empty cell's wash but short of solid.
 */
/* Both alphas read here, not borrowed from a block further down the
   file -- this check is a comparison between the two, so it has to own
   both sides of it or it breaks when either block moves. */
preg_match('~\.dhcp-piece \{([^}]*)\}~s', $ui, $m_pblk);
preg_match_all('~rgba\([^)]*?,\s*([\d.]+)\)~', count($m_pblk) === 2 ? $m_pblk[1] : '', $m_pa);
$piece_max = count($m_pa[1]) ? max(array_map('floatval', $m_pa[1])) : 0.0;

preg_match('~\.dhcp-cell \{[^}]*?background: rgba\([^)]*?,\s*([\d.]+)\)~s', $ui, $m_cell0);
$cell_fill = count($m_cell0) === 2 ? (float) $m_cell0[1] : 0.0;

ok($piece_max > 0 && $cell_fill > 0,
   'the piece or cell fill could not be read, so the comparison below is not measuring anything');

ok($piece_max > $cell_fill,
   'a placed trait has no more backing than an empty slot, so the part itself is lost against the art');
ok($piece_max < 1.0,
   'the plate behind a placed trait is solid, which punches a hole in the picture it sits on');

/*
 * THE READOUT HAS TO BE READABLE. It is the only line on the page that
 * says what you get for what you have loaded, and it was .82rem of
 * --dhcp-dim floating on the page ground. Three things make it legible
 * and all three are easy to undo by hand later.
 */
preg_match('~\.dhcp-readout \{([^}]*)\}~s', $ui, $m_ro);
$ro = count($m_ro) === 2 ? $m_ro[1] : '';
preg_match('~font-size: ([\d.]+)rem~', $ro, $m_rs);
ok(count($m_rs) === 2 && (float) $m_rs[1] >= 0.9,
   'the readout is set at ' . (count($m_rs) === 2 ? $m_rs[1] : '?')
   . 'rem, too small for the most important line on the page');
ok($ro !== '' && strpos($ro, 'var(--dhcp-dim)') === false,
   'the readout is painted in --dhcp-dim, the quietest colour on the page');
ok($ro !== '' && strpos($ro, 'background-color') !== false,
   'the readout has no surface, so it floats on the page ground with nothing holding it');

/* The odds phrase is FILLED, not merely coloured: coloured text on a
   dark ground is still text, and this has to beat the sentence it sits
   in. The old rule set a colour and nothing else. */
preg_match('~\.dhcp-readout \.odds \{([^}]*)\}~s', $ui, $m_od);
$od = count($m_od) === 2 ? $m_od[1] : '';
ok($od !== '' && preg_match('~background: var\(--dhcp-[a-z]+\)~', $od) === 1,
   'the odds phrase is coloured text again rather than a filled pill, so it does not carry');

/* AND THE CHIPS HAVE ROOM. At 54px the grid fitted nine across a 618px
   column and the trait art came out smaller than the count badge. */
preg_match('~\.dhcp-tray \{[^}]*?minmax\((\d+)px~s', $ui, $m_ch);
ok(count($m_ch) === 2 && (int) $m_ch[1] >= 68,
   'the spares chips are back to ' . (count($m_ch) === 2 ? $m_ch[1] : '?')
   . 'px, which packs the tray too tight to read a trait');

/* And an empty cell is an outline, not a filled panel: sixteen fills at
   .5 stacked into a dark slab laid over the figure. */
preg_match('~\.dhcp-cell \{[^}]*?background: rgba\([^)]*?,\s*([\d.]+)\)~s', $ui, $m_cf);
ok(count($m_cf) === 2 && (float) $m_cf[1] <= 0.25,
   'the empty-cell fill is back up to ' . (count($m_cf) === 2 ? $m_cf[1] : '?')
   . ', so sixteen of them read as a slab over the art');
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
