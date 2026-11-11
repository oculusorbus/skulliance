<?php
/*
 * lib/dhcportal-lib.php — the DHC Portal's rules, with no page attached.
 *
 * THE WHOLE MECHANIC IS ONE SENTENCE: a 4x4 board, traits take up space,
 * rows filled is the tier. See dhcportal-design.md for why it is shaped
 * this way and what was rejected getting there.
 *
 * EVERYTHING HERE IS PURE EXCEPT THE THREE FUNCTIONS THAT SAY SO. The
 * board maths, the tier lookup and the eject rule take plain arrays and
 * return plain arrays, so the harness can run the whole rule set without
 * a database. The endpoint is where traits actually move.
 */

require_once __DIR__ . '/../dhcfighters-config.php';
require_once __DIR__ . '/../dhcfighters-lib.php';
require_once __DIR__ . '/return-gift.php';

/*
 * THE PIECES. Every one is row-aligned, which is the property the whole
 * design rests on: four commons make a row, two uncommons make a row, an
 * epic IS a row. Nothing can leave a notch, so there is no packing
 * problem and no auto-fill.
 *
 * An earlier draft had epic as a 2x2 block and spent a page solving the
 * problem that created. Four slots is a row, so a four-slot piece is a
 * row. If these numbers are ever edited, they must keep dividing
 * DHCP_COLS exactly or the board stops being readable as rows.
 */
define('DHCP_COLS', 4);
define('DHCP_ROWS', 4);
define('DHCP_SLOTS', DHCP_COLS * DHCP_ROWS);   /* 16 */

function dhcp_sizes() {
	return array(
		'common'    => 1,   /* 1x1 */
		'uncommon'  => 2,   /* 2x1 */
		'epic'      => 4,   /* 4x1, one row  */
		'legendary' => 8,   /* 4x2, two rows */
		'mythic'    => 16,  /* 4x4, the board */
	);
}

/** How many slots one trait takes. Unknown tiers take a slot, never zero. */
function dhcp_size($tier) {
	$s = dhcp_sizes();
	return isset($s[$tier]) ? $s[$tier] : 1;
}

/*
 * ROWS ARE TIERS, one to one against the four tables DHCF_TIERS already
 * has. Nothing new is invented and nothing can drift: if a fifth table
 * were ever added, this returns the first four and the harness says so.
 */
function dhcp_tables() {
	return array('run', 'placement_10', 'placement_3', 'placement_1');
}

/** Rows filled by a given number of slots. Partial rows do not count. */
function dhcp_rows($slots) {
	$r = (int) floor(((int)$slots) / DHCP_COLS);
	return $r > DHCP_ROWS ? DHCP_ROWS : $r;
}

/**
 * The table a board of this many slots rolls on, or null below one row.
 *
 * ONE FULL ROW IS THE MINIMUM TO FIRE. That is the same rule as the
 * tier rather than a second one: you cannot roll on a row you have not
 * filled, and row zero is not a table.
 */
function dhcp_table_name($slots) {
	$rows = dhcp_rows($slots);
	if ($rows < 1) return null;
	$t = dhcp_tables();
	return $t[$rows - 1];
}

/** The odds themselves, for the roll and for showing the player. */
function dhcp_table($slots) {
	$name = dhcp_table_name($slots);
	return $name === null ? null : DHCF_TIERS[$name];
}

/**
 * What a hopper weighs.
 *
 * @param array $hopper list of array('category'=>..,'slug'=>..,'tier'=>..)
 */
function dhcp_slots($hopper) {
	$n = 0;
	foreach ($hopper as $h) $n += dhcp_size(isset($h['tier']) ? $h['tier'] : '');
	return $n;
}

/**
 * THE EJECT RULE, which is physical rather than arithmetic.
 *
 * Fifteen slots used and the player drops in a legendary that needs
 * two rows. Without this that is eleven slots of fuel thrown away --
 * most of another run -- with nothing to warn them.
 *
 * CHEAPEST FIRST, deliberately: it preserves the dense piece the player
 * just chose and returns the ones most reusable next time. Ejecting the
 * legendary instead would be the system overruling their last action.
 *
 * Nothing is worth more than the whole board (a mythic is exactly
 * DHCP_SLOTS) so there is always room to make, which is why this cannot
 * fail and has no edge case for an oversized piece.
 *
 * @return array array($kept, $returned) in the order they were given
 */
function dhcp_fit($hopper, $incoming) {
	$kept = $hopper;
	$kept[] = $incoming;
	$returned = array();
	while (dhcp_slots($kept) > DHCP_SLOTS) {
		/* The cheapest piece that is NOT the one just added. Scanning for
		   the minimum rather than assuming an order, because the board is
		   assembled in whatever order the player clicks. */
		$worst = null; $worstSize = null;
		foreach ($kept as $i => $h) {
			if ($i === count($kept) - 1) continue;      /* never the incoming piece */
			$sz = dhcp_size(isset($h['tier']) ? $h['tier'] : '');
			if ($worstSize === null || $sz < $worstSize) { $worst = $i; $worstSize = $sz; }
		}
		if ($worst === null) break;                      /* only the incoming piece left */
		$returned[] = $kept[$worst];
		array_splice($kept, $worst, 1);
	}
	return array(array_values($kept), $returned);
}

/**
 * Roll a trait for a board, excluding everything the board holds.
 *
 * NOTHING YOU FED COMES BACK. The one hard rule of the feature, and the
 * only thing standing between a player and the single most enraging
 * outcome this could produce.
 *
 * REJECTION SAMPLING, not a filtered pool. dhcf_draw() owns the weights,
 * the suspended list and the wildcard pooling, and reimplementing any of
 * that here would be a second copy to drift. Rejecting a draw and asking
 * again preserves the conditional distribution exactly, and with sixteen
 * slugs excluded from a pool of hundreds it almost never loops twice.
 *
 * @return array|null array($category, $slug, $info)
 */
function dhcp_roll($hopper, $tiers, $attempts = 60) {
	$fed = array();
	foreach ($hopper as $h) $fed[(string)$h['slug']] = true;
	for ($i = 0; $i < $attempts; $i++) {
		$d = dhcf_draw('wildcard', $tiers);
		if (!$d) return null;
		if (!isset($fed[$d[1]])) return $d;
	}
	return null;        /* caller refuses rather than handing back a fed trait */
}

/**
 * Is there anything left to win at all?
 *
 * CHECKED BEFORE ANYTHING MOVES. Refusing a jettison after the traits
 * are gone is the worst version of this feature, so the pre-flight runs
 * at the staging area and again in the endpoint.
 */
function dhcp_pool_remains($hopper) {
	$fed = array();
	foreach ($hopper as $h) $fed[(string)$h['slug']] = true;
	foreach (dhcf_rarity() as $cat => $traits) {
		foreach ($traits as $slug => $info) {
			if (DHCF_SUSPENDED && in_array($slug, DHCF_SUSPENDED, true)) continue;
			if (!isset($fed[$slug])) return true;
		}
	}
	return false;
}

/*
 * ===== WHO THE TRAITS GO TO ==============================================
 *
 * Everything swallowed goes back out to other players at FULL RARITY. No
 * split, no degradation, no holding the good ones back -- the owner's
 * call and the right one: a rare landing on somebody who fell off is
 * exactly the kind of thing that wins them back, and if they return they
 * need the traits to catch up.
 */

/**
 * The cascade: longest-lapsed first, widening until somebody qualifies.
 *
 * NOTHING IS EVER DESTROYED FOR WANT OF A RECIPIENT. The last rung is
 * every active player, so a trait cannot fall into the ether however
 * quiet the platform is.
 *
 * THE SUBMITTER IS EXCLUDED AT EVERY RUNG, INCLUDING THE LAST. Without
 * that, the fall-through to active players can hand somebody their own
 * jettisoned trait, which breaks the one hard rule of the feature from
 * the other direction.
 *
 * RECIPIENTS MUST ALREADY HOLD A TRAIT. A Fighter trait landing on an
 * account that has never touched the game is noise, not a welcome back.
 */
function dhcp_lapse_rungs() {
	/* Days of silence. Starts at the platform's own definition of lapsed
	   (RETURN_GIFT_DAYS) rather than inventing a second one, then widens.
	   0 is "anybody active", the floor that makes this total. */
	return array((int) RETURN_GIFT_DAYS, 21, 14, 7, 3, 1, 0);
}

/**
 * Pick a recipient. Reads; writes nothing.
 *
 * @return int user_id, or 0 if the platform has nobody at all
 */
function dhcp_pick_recipient($conn, $exclude_user_id) {
	$ex = (int) $exclude_user_id;
	foreach (dhcp_lapse_rungs() as $days) {
		/*
		 * "Holds a trait, is not the submitter, and has been quiet for at
		 * least this long." Quiet is measured by their most recent trait,
		 * which is the only activity signal dhc_trait_drops can see --
		 * and the one that matters here, since the gift is a trait.
		 */
		$having = $days > 0
			? "HAVING MAX(awarded_at) <= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY)"
			: "";
		$sql = "SELECT user_id FROM dhc_trait_drops
		         WHERE user_id <> '" . $ex . "'
		         GROUP BY user_id " . $having . "
		         ORDER BY RAND() LIMIT 1";
		$r = @$conn->query($sql);
		if ($r && ($row = $r->fetch_assoc())) return (int) $row['user_id'];
	}
	return 0;
}

/*
 * ===== THE STAGED BOARD ==================================================
 *
 * IN THE SESSION, NOT A TABLE.
 *
 * The first build gave this its own table with a UNIQUE key and a
 * schema doc. That was over-engineering a scratchpad: the board is a
 * draft of something about to be destroyed, and persisting it properly
 * bought durability nobody asked for at the cost of a migration, a
 * cleanup story and a second place for state to go stale.
 *
 * So it is a list of drop_ids in $_SESSION. It survives navigation and
 * reloads, and it is lost on logout or a long enough idle gap. That is
 * the right trade for a pile you assembled two minutes ago and are
 * about to throw away.
 *
 * WHAT THE TABLE WAS ACTUALLY DEFENDING IS STILL DEFENDED. Its UNIQUE
 * key stopped the same copy being staged twice, and a session does that
 * better: one array, one owner, no second tab racing it. And the real
 * double-spend guard was never here -- it is in the endpoint, where the
 * delete names the owner and checks its own affected-row count against
 * the board. That is untouched.
 *
 * STILL ONLY drop_ids, NOT category+slug. A player can own three copies
 * of a trait; staging two has to be unambiguous and the jettison has to
 * destroy exactly the ones staged rather than "two of these".
 */

/** The raw list. Always a list of ints, never trusted to be valid. */
function dhcp_raw($user_id) {
	$uid = (int) $user_id;
	if ($uid <= 0) return array();
	/* KEYED BY USER. One browser can sign out and in as somebody else
	   without a reload, and a board inherited across that would be
	   pointing at traits the new account does not own. */
	if (!isset($_SESSION['dhcp_board']) || !is_array($_SESSION['dhcp_board'])) return array();
	if ((int) ($_SESSION['dhcp_board']['uid'] ?? 0) !== $uid) return array();
	$ids = isset($_SESSION['dhcp_board']['ids']) ? $_SESSION['dhcp_board']['ids'] : array();
	return is_array($ids) ? array_values(array_map('intval', $ids)) : array();
}

function dhcp_put($user_id, $ids) {
	$_SESSION['dhcp_board'] = array('uid' => (int) $user_id,
	                                'ids' => array_values(array_map('intval', $ids)));
}

/**
 * The board as it stands, self-healed.
 *
 * SELF-HEALING IS NOT OPTIONAL HERE. Staged is deliberately NOT
 * committed -- a draft that silently locks your traits is worse than one
 * that goes stale -- so a piece can be spent on a Fighter while it sits
 * on the board. This checks what is staged against dhcf_available(),
 * which already knows how many copies of each trait are free, rather
 * than inventing a second idea of what "spent" means.
 *
 * @return array array('pieces' => [...], 'slots' => n, 'dropped' => n)
 */
function dhcp_board($conn, $user_id) {
	$uid = (int) $user_id;
	$out = array('pieces' => array(), 'slots' => 0, 'dropped' => 0);
	if ($uid <= 0) return $out;
	$ids = dhcp_raw($uid);
	if (!$ids) return $out;

	/* Resolve against what they actually own, in the staged order. */
	$rows = array();
	$r = @$conn->query("SELECT id, category, slug FROM dhc_trait_drops
	                     WHERE user_id = '$uid' AND id IN (" . implode(',', $ids) . ")");
	if ($r) while ($x = $r->fetch_assoc()) $rows[(int) $x['id']] = $x;

	$free = dhcf_available($conn, $uid);
	$rarity = dhcf_rarity();
	$seen = array();
	$keep = array();
	$dropped = 0;
	foreach ($ids as $did) {
		if (!isset($rows[$did])) { $dropped++; continue; }   /* sold, gone, never theirs */
		$cat = $rows[$did]['category']; $slug = $rows[$did]['slug'];
		$have = isset($free[$cat][$slug]) ? (int) $free[$cat][$slug]['free'] : 0;
		$k = $cat . '|' . $slug;
		$seen[$k] = isset($seen[$k]) ? $seen[$k] + 1 : 1;
		if ($seen[$k] > $have) { $dropped++; continue; }     /* went onto a Fighter */
		$tier = isset($rarity[$cat][$slug][0]) ? $rarity[$cat][$slug][0] : 'common';
		$keep[] = array('drop_id' => $did, 'category' => $cat, 'slug' => $slug,
		                'tier' => $tier, 'size' => dhcp_size($tier));
	}
	if ($dropped > 0) {
		$ok = array();
		foreach ($keep as $p) $ok[] = $p['drop_id'];
		dhcp_put($uid, $ok);
	}
	$out['pieces']  = $keep;
	$out['slots']   = dhcp_slots($keep);
	$out['dropped'] = $dropped;
	return $out;
}

/**
 * Put one trait on the board.
 *
 * VERIFIES OWNERSHIP AND FREEDOM HERE TOO, not only at the jettison. A
 * staging call is a state change and every one checks for itself: the
 * page that sent it may be minutes stale.
 *
 * @return array array(bool ok, string why, array returned_drop_ids)
 */
function dhcp_stage($conn, $user_id, $drop_id) {
	$uid = (int) $user_id; $did = (int) $drop_id;
	if ($uid <= 0 || $did <= 0) return array(false, 'Nothing to add.', array());

	$r = @$conn->query("SELECT category, slug FROM dhc_trait_drops
	                     WHERE id = '$did' AND user_id = '$uid' LIMIT 1");
	if (!$r || !($row = $r->fetch_assoc())) return array(false, 'That trait is not yours.', array());

	$board = dhcp_board($conn, $uid);
	foreach ($board['pieces'] as $p) {
		if ($p['drop_id'] === $did) return array(false, 'That one is already on the board.', array());
	}

	/* Free copies minus what this board already holds of the same trait. */
	$free = dhcf_available($conn, $uid);
	$have = isset($free[$row['category']][$row['slug']])
	      ? (int) $free[$row['category']][$row['slug']]['free'] : 0;
	$staged = 0;
	foreach ($board['pieces'] as $p) {
		if ($p['category'] === $row['category'] && $p['slug'] === $row['slug']) $staged++;
	}
	if ($staged >= $have) return array(false, 'That trait is on a Fighter.', array());

	$rarity = dhcf_rarity();
	$tier = isset($rarity[$row['category']][$row['slug']][0])
	      ? $rarity[$row['category']][$row['slug']][0] : 'common';
	$incoming = array('drop_id' => $did, 'category' => $row['category'],
	                  'slug' => $row['slug'], 'tier' => $tier, 'size' => dhcp_size($tier));

	/* THE EJECT RULE, applied before anything is stored. */
	list($kept, $returned) = dhcp_fit($board['pieces'], $incoming);
	$ids = array();
	foreach ($kept as $p) $ids[] = $p['drop_id'];
	dhcp_put($uid, $ids);

	$back = array();
	foreach ($returned as $t) $back[] = (int) $t['drop_id'];
	return array(true, '', $back);
}

/** Take one piece off the board. */
function dhcp_unstage($conn, $user_id, $drop_id) {
	$uid = (int) $user_id; $did = (int) $drop_id;
	if ($uid <= 0 || $did <= 0) return false;
	$ids = dhcp_raw($uid);
	$out = array();
	$found = false;
	foreach ($ids as $i) { if ($i === $did) { $found = true; continue; } $out[] = $i; }
	dhcp_put($uid, $out);
	return $found;
}

/** Clear it. */
function dhcp_clear($conn, $user_id) {
	$uid = (int) $user_id;
	if ($uid <= 0) return false;
	dhcp_put($uid, array());
	return true;
}
