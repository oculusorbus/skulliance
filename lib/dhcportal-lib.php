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
 * The board persists. Sixteen pieces is enough arrangement that losing it
 * to a closed tab would sting, and a player who stacks a board on a phone
 * and gets interrupted should find it waiting.
 *
 * IT HOLDS drop_id, NOT category+slug, and that is the whole double-spend
 * defence. A player can own three copies of the same trait; staging two
 * of them has to be unambiguous, and the delete at jettison has to remove
 * exactly the rows that were staged rather than "two of these". Pointing
 * at dhc_trait_drops.id makes every piece on the board a specific copy
 * that either still exists and is still free, or does not.
 *
 * STAGED IS NOT COMMITTED. A trait on the board stays available to the
 * assembler, because a draft that quietly locks your traits is worse than
 * one that goes stale. So the board SELF-HEALS: dhcp_board() drops any
 * piece that has since been spent on a Fighter, sold or otherwise left,
 * and tells the page how many went. The endpoint then re-verifies from
 * scratch before anything moves, because a page can be stale by the time
 * a button is pressed and the staging area is not the authority.
 */

/** CREATE TABLE, for the record. Run once; see dhcportal-schema.md. */
function dhcp_schema() {
	return "CREATE TABLE IF NOT EXISTS dhc_portal_board (
	  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
	  user_id INT UNSIGNED NOT NULL,
	  drop_id INT UNSIGNED NOT NULL,
	  added_at DATETIME NOT NULL,
	  PRIMARY KEY (id),
	  UNIQUE KEY uniq_drop (drop_id),
	  KEY idx_user (user_id)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4";
}

/**
 * The board as it stands, self-healed.
 *
 * UNIQUE ON drop_id is the first half of the double-spend defence: the
 * same copy cannot be staged twice, by this player or any other, and the
 * database refuses rather than the code remembering to.
 *
 * @return array array('pieces' => [...], 'slots' => n, 'dropped' => n)
 */
function dhcp_board($conn, $user_id) {
	$uid  = (int) $user_id;
	$out  = array('pieces' => array(), 'slots' => 0, 'dropped' => 0);
	if ($uid <= 0) return $out;

	$r = @$conn->query(
		"SELECT b.drop_id, d.category, d.slug
		   FROM dhc_portal_board b
		   INNER JOIN dhc_trait_drops d ON d.id = b.drop_id
		  WHERE b.user_id = '$uid' AND d.user_id = '$uid'
		  ORDER BY b.id ASC");
	if (!$r) return $out;

	$rows = array();
	while ($x = $r->fetch_assoc()) $rows[] = $x;
	if (!$rows) {
		/* Nothing joined. Either the board is empty or every piece has
		   left the account; clear any orphans so the next read is cheap. */
		@$conn->query("DELETE FROM dhc_portal_board WHERE user_id = '$uid'");
		return $out;
	}

	/*
	 * SELF-HEAL AGAINST FIGHTERS. dhcf_available() already knows how many
	 * copies of each trait are free, so the board is checked against it
	 * rather than against a second idea of what "spent" means. More
	 * staged copies than free copies means the difference went into a
	 * Fighter since, and the newest staged ones go.
	 */
	$free = dhcf_available($conn, $uid);
	$seen = array();
	$keep = array(); $drop = array();
	foreach ($rows as $x) {
		$cat = $x['category']; $slug = $x['slug'];
		$have = isset($free[$cat][$slug]) ? (int) $free[$cat][$slug]['free'] : 0;
		$k = $cat . '|' . $slug;
		$seen[$k] = isset($seen[$k]) ? $seen[$k] + 1 : 1;
		if ($seen[$k] <= $have) $keep[] = $x; else $drop[] = (int) $x['drop_id'];
	}
	if ($drop) {
		@$conn->query("DELETE FROM dhc_portal_board WHERE user_id = '$uid'
		                AND drop_id IN (" . implode(',', array_map('intval', $drop)) . ")");
		$out['dropped'] = count($drop);
	}

	$rarity = dhcf_rarity();
	foreach ($keep as $x) {
		$cat = $x['category']; $slug = $x['slug'];
		$tier = isset($rarity[$cat][$slug][0]) ? $rarity[$cat][$slug][0] : 'common';
		$out['pieces'][] = array(
			'drop_id'  => (int) $x['drop_id'],
			'category' => $cat,
			'slug'     => $slug,
			'tier'     => $tier,
			'size'     => dhcp_size($tier),
		);
	}
	$out['slots'] = dhcp_slots($out['pieces']);
	return $out;
}

/**
 * Put one trait on the board.
 *
 * VERIFIES OWNERSHIP AND FREEDOM HERE TOO, not only at the jettison. A
 * staging call is a write and every write checks for itself -- the page
 * that sent it may be minutes stale.
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

	/* THE EJECT RULE, applied before anything is written. */
	list($kept, $returned) = dhcp_fit($board['pieces'], $incoming);
	$back = array();
	foreach ($returned as $t) $back[] = (int) $t['drop_id'];
	if ($back) {
		@$conn->query("DELETE FROM dhc_portal_board WHERE user_id = '$uid'
		                AND drop_id IN (" . implode(',', $back) . ")");
	}
	@$conn->query("INSERT INTO dhc_portal_board (user_id, drop_id, added_at)
	               VALUES ('$uid', '$did', NOW())");
	return array(true, '', $back);
}

/** Take one piece off the board. */
function dhcp_unstage($conn, $user_id, $drop_id) {
	$uid = (int) $user_id; $did = (int) $drop_id;
	if ($uid <= 0 || $did <= 0) return false;
	return (bool) @$conn->query("DELETE FROM dhc_portal_board
	                              WHERE user_id = '$uid' AND drop_id = '$did'");
}

/** Clear it. */
function dhcp_clear($conn, $user_id) {
	$uid = (int) $user_id;
	if ($uid <= 0) return false;
	return (bool) @$conn->query("DELETE FROM dhc_portal_board WHERE user_id = '$uid'");
}
