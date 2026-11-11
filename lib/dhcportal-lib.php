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

/*
 * ===== TETHERED PAIRS ====================================================
 *
 * Seven weapons on the platform are two rows that mean one thing: a
 * weapon and the sash or strap it hangs from. DHCF_TETHERED maps each
 * half to the other, and the assembler REFUSES TO SAVE a Fighter
 * holding one without the other -- dhcf_shortfall() reports it missing.
 *
 * So letting a player jettison half a pair quietly bricks the half they
 * keep: it cannot be built with, cannot be completed, and is not even
 * obviously broken until they try. The Portal treats a pair as ONE
 * PIECE throughout.
 *
 * ONE PIECE COSTS ONE TRAIT'S SLOTS, not two. One draw produced both
 * rows -- that is why dhcf_award() marks the second with
 * DHCF_PAIRED_MARK and excludes it from the daily cap, on exactly this
 * reasoning: "one draw that happens to be two pieces must not cost two
 * of the three traits a day a source is allowed to give." A pair is one
 * trait's worth of value and takes one trait's worth of board.
 *
 * AND THEY TRAVEL TOGETHER. Both rows go to the SAME recipient, or the
 * cascade would hand two different lapsed players half a weapon each
 * and recreate the bug it was meant to prevent.
 */

/** Both halves of a pair, or just the one slug when it stands alone. */
function dhcp_pair_slugs($slug) {
	$other = dhcf_tethered_partner($slug);
	return $other === '' ? array($slug) : array($slug, $other);
}

/**
 * WHERE EVERY PIECE SITS ON THE BOARD. Pure; the client's layout() is this.
 *
 * LARGEST FIRST, AND THAT IS LOAD-BEARING. The note above says every piece
 * is row-aligned so nothing can leave a notch, and that is only true if
 * the sizes arrive in non-increasing order. A player clicks in whatever
 * order they like: two commons and then a legendary put the legendary at
 * column 2, four columns wide, hanging two columns off the board.
 *
 * Reported from the live site, and worse than reported -- enumerated here
 * rather than reasoned about, 422 of the 628 boards that fit in sixteen
 * slots straddled. Sorted largest first, none do: the sizes are 1, 2, 4,
 * 8 and 16, so a four-wide piece always starts on a multiple of four, a
 * two-wide always on an even slot, and a one fits anywhere.
 *
 * PURELY VISUAL. Slots, rows and the table are sums over the same set in
 * any order, and the eject rule is dhcp_fit()'s. This exists so the
 * harness can execute the rule the client draws with, rather than read it.
 *
 * @param array $pieces list of array('tier'=>..)
 * @return array list of array('tier','at','w','h')
 */
function dhcp_layout($pieces) {
	$seq = array_values($pieces);
	usort($seq, function ($a, $b) {
		return dhcp_size(isset($b['tier']) ? $b['tier'] : '')
		     - dhcp_size(isset($a['tier']) ? $a['tier'] : '');
	});
	$out = array(); $at = 0;
	foreach ($seq as $p) {
		$s = dhcp_size(isset($p['tier']) ? $p['tier'] : '');
		$out[] = array(
			'tier' => isset($p['tier']) ? $p['tier'] : '',
			'at'   => $at,
			'w'    => min($s, DHCP_COLS),
			'h'    => (int) ceil($s / DHCP_COLS),
		);
		$at += $s;
	}
	return $out;
}

/**
 * Does this board fit inside the grid, every piece whole?
 *
 * @return bool
 */
function dhcp_layout_fits($pieces) {
	foreach (dhcp_layout($pieces) as $L) {
		if (!dhcp_cell_fits($L['at'], $L['w'], $L['h'])) return false;
	}
	return true;
}

/**
 * Is a piece of this size, starting at this slot, inside the grid?
 *
 * SEPARATE FROM THE LOOP ABOVE SO IT CAN BE TESTED AT ALL. With the sort
 * in dhcp_layout() doing its job nothing ever straddles, so these two
 * conditions are unreachable through dhcp_layout_fits() -- deleting
 * either one changed no outcome and no assertion, which is a guard that
 * cannot be shown to work. Reached directly it can be handed the
 * straddles the sort exists to prevent.
 */
function dhcp_cell_fits($at, $w, $h) {
	$at = (int) $at;
	$col = $at % DHCP_COLS;
	$row = (int) floor($at / DHCP_COLS);
	if ($col + (int) $w > DHCP_COLS) return false;   /* off the right edge */
	if ($row + (int) $h > DHCP_ROWS) return false;   /* off the top */
	return true;
}

/**
 * Collapse a flat list of owned copies into pieces, pairing the tethered.
 *
 * A piece carries drop_ids (plural) because a pair is two rows, and
 * every path that consumes a piece has to take all of them.
 *
 * UNMATCHED HALVES STAY VISIBLE. A player can hold one half already --
 * the Bio Weapon shipped without its strap for a while and
 * dhc-trait-migrate.php only granted it to existing holders. Hiding a
 * lone half would leave a trait they can neither use nor get rid of.
 *
 * @param array $rows list of array('drop_id','category','slug','tier')
 */
function dhcp_pieces($rows) {
	$byslug = array();
	foreach ($rows as $r) $byslug[$r['slug']][] = $r;

	$out = array(); $used = array();
	foreach ($rows as $r) {
		if (isset($used[$r['drop_id']])) continue;
		$other = dhcf_tethered_partner($r['slug']);
		$mate = null;
		if ($other !== '' && !empty($byslug[$other])) {
			foreach ($byslug[$other] as $cand) {
				if (!isset($used[$cand['drop_id']])) { $mate = $cand; break; }
			}
		}
		$used[$r['drop_id']] = true;
		$ids = array((int) $r['drop_id']);
		$slugs = array($r['slug']);
		if ($mate) { $used[$mate['drop_id']] = true; $ids[] = (int) $mate['drop_id'];
		             $slugs[] = $mate['slug']; }
		$out[] = array(
			'drop_id'  => (int) $r['drop_id'],   /* the handle the client uses */
			'drop_ids' => $ids,
			'category' => $r['category'],
			'slug'     => $r['slug'],
			'slugs'    => $slugs,
			'tier'     => $r['tier'],
			'size'     => dhcp_size($r['tier']),
			'paired'   => $mate ? 1 : 0,
		);
	}
	return $out;
}

/**
 * THE TRAY: every free copy the player holds, one entry per copy,
 * cheapest first. Pure -- rows in, pieces out.
 *
 * NOTE WHAT THIS FUNCTION CANNOT SEE: the board. That is the fix for a
 * real bug and the reason this is a function at all.
 *
 * The page used to build the tray inline and skip anything currently
 * staged, so the array handed to the client held only the UNstaged
 * copies. Taking a piece off the board then had nothing to put it back
 * into -- the client removed it from the board, looked for it in the
 * tray, and it had never been sent. Only a page load restored it.
 *
 * The client already owns that subtraction (spare() hides what is on
 * the board), so doing it here too was both redundant and lossy. It was
 * also wrong about quantity: the skip happened BEFORE the free-copy
 * tally, so a staged copy did not consume its own quota and a player
 * with two free copies of a trait could have one on the board and still
 * see two in the tray.
 *
 * Keeping the board out of the signature is what stops either from
 * coming back.
 *
 * ONE ENTRY PER COPY rather than a stack with a count, because the board
 * holds specific drop_ids and the player is choosing which copy to
 * surrender. A pile of "x3" would have to pick one for them.
 *
 * Cheapest first because that is the order a clear-out happens in: the
 * commons are what the Portal is for, and the rares should take a
 * deliberate scroll to reach rather than sitting under the cursor.
 *
 * @param array $rows   id/category/slug, ordered by id
 * @param array $free   dhcf_available() shape: [cat][slug]['free']
 * @param array $rarity dhcf_rarity() shape: [cat][slug][0] = tier
 */
function dhcp_tray_pieces($rows, $free, $rarity) {
	$flat = array(); $seen = array();
	foreach ($rows as $x) {
		$cat = $x['category']; $slug = $x['slug'];
		$have = isset($free[$cat][$slug]['free']) ? (int) $free[$cat][$slug]['free'] : 0;
		$k = $cat . '|' . $slug;
		$seen[$k] = isset($seen[$k]) ? $seen[$k] + 1 : 1;
		/* Copies beyond the free count are wearing a Fighter. */
		if ($seen[$k] > $have) continue;
		$tier = isset($rarity[$cat][$slug][0]) ? $rarity[$cat][$slug][0] : 'common';
		$flat[] = array('drop_id' => (int) $x['id'], 'category' => $cat,
		                'slug' => $slug, 'tier' => $tier);
	}
	/* PAIRS ARE ONE CHIP. A tethered weapon and its strap are one object
	   and the assembler refuses to build with half of one, so the tray
	   must not offer them separately either. */
	$out = dhcp_pieces($flat);
	$order = array('common' => 0, 'uncommon' => 1, 'epic' => 2, 'legendary' => 3, 'mythic' => 4);
	usort($out, function ($a, $b) use ($order) {
		$d = $order[$a['tier']] - $order[$b['tier']];
		return $d !== 0 ? $d : strcmp($a['slug'], $b['slug']);
	});
	return $out;
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
	/* BOTH HALVES of anything fed, or the Portal can hand back the strap
	   of the weapon it has just swallowed. */
	$fed = array();
	foreach ($hopper as $h) {
		foreach (isset($h['slugs']) ? $h['slugs'] : array($h['slug']) as $sl) $fed[(string)$sl] = true;
	}
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
	foreach ($hopper as $h) {
		foreach (isset($h['slugs']) ? $h['slugs'] : array($h['slug']) as $sl) $fed[(string)$sl] = true;
	}
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
		 * "Holds a trait, is not the submitter, and has not been HERE for
		 * at least this long."
		 *
		 * QUIET IS users.last_login, AND THAT IS A CORRECTION. It used to
		 * be MAX(awarded_at) over this player's own trait drops, which is
		 * not an activity signal at all -- it is "when did we last give
		 * them something". Three things went wrong with it, and the first
		 * is the one that got reported:
		 *
		 *   OUR OWN GIFT MARKED THEM ACTIVE. The instant a lapsed player
		 *   received a piece their MAX(awarded_at) became NOW(), so they
		 *   dropped out of EVERY rung at once. Each lapsed player could
		 *   absorb exactly one trait, the pool drained one-each, and a big
		 *   jettison fell through to the last rung -- showering active
		 *   players, which is how the owner came to be handed traits from
		 *   somebody else's clear-out.
		 *
		 *   AND IT PERSISTED. Everyone gifted today looked active for the
		 *   next RETURN_GIFT_DAYS, so the NEXT jettison had a smaller
		 *   lapsed pool and leaned harder on active players still. The
		 *   feature eroded itself the more it was used.
		 *
		 *   THE INVERSE TOO: somebody who opens the site daily but has not
		 *   EARNED a trait in a month read as lapsed and got a woo-back
		 *   they never needed.
		 *
		 * last_login is the signal the welcome-back feature already uses
		 * for this exact question. Despite the name it is not an auth
		 * event: skulliance.php updates it for any signed-in page load,
		 * once a day, so a persisted session that pops in to look at a
		 * realm counts as being here. Day-level accuracy is ample for a
		 * cascade whose tightest rung is one day.
		 *
		 * NULL IS MAXIMALLY LAPSED. A trait-holder who has never loaded a
		 * signed-in page has not been here, which is the whole question.
		 */
		$where = $days > 0
			? " AND (u.last_login IS NULL
			         OR u.last_login <= DATE_SUB(NOW(), INTERVAL " . (int)$days . " DAY))"
			: "";
		$sql = "SELECT d.user_id FROM dhc_trait_drops d
		         INNER JOIN users u ON u.id = d.user_id
		         WHERE d.user_id <> '" . $ex . "'" . $where . "
		         GROUP BY d.user_id
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
		                'tier' => $tier);
	}
	/* PAIRS COLLAPSE HERE, after the self-heal: a half whose partner has
	   gone onto a Fighter should pair with nothing, not with a row that
	   is no longer free. */
	$pieces = dhcp_pieces($keep);
	if ($dropped > 0) {
		$ok = array();
		foreach ($pieces as $p) foreach ($p['drop_ids'] as $i) $ok[] = $i;
		dhcp_put($uid, $ok);
	}
	$out['pieces']  = $pieces;
	$out['slots']   = dhcp_slots($pieces);
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

	/*
	 * THE PARTNER COMES WITH IT. A tethered weapon and its strap are one
	 * object: the assembler refuses to build with half a pair, so
	 * letting one go alone bricks the other.
	 */
	$ids_in = array($did);
	$slugs  = array($row['slug']);
	$other  = dhcf_tethered_partner($row['slug']);
	if ($other !== '') {
		$staged_ids = dhcp_raw($uid);
		$q = @$conn->query("SELECT id FROM dhc_trait_drops
		                     WHERE user_id = '$uid' AND slug = '" . $conn->real_escape_string($other) . "'
		                     ORDER BY id ASC");
		if ($q) while ($x = $q->fetch_assoc()) {
			$cand = (int) $x['id'];
			if (in_array($cand, $staged_ids, true)) continue;
			$ids_in[] = $cand; $slugs[] = $other; break;
		}
	}

	$incoming = array('drop_id' => $did, 'drop_ids' => $ids_in,
	                  'category' => $row['category'], 'slug' => $row['slug'],
	                  'slugs' => $slugs, 'tier' => $tier, 'size' => dhcp_size($tier),
	                  'paired' => count($ids_in) > 1 ? 1 : 0);

	/* THE EJECT RULE, applied before anything is stored. */
	list($kept, $returned) = dhcp_fit($board['pieces'], $incoming);
	$ids = array();
	foreach ($kept as $p) {
		foreach (isset($p['drop_ids']) ? $p['drop_ids'] : array($p['drop_id']) as $i) $ids[] = $i;
	}
	dhcp_put($uid, $ids);

	$back = array();
	foreach ($returned as $t) {
		foreach (isset($t['drop_ids']) ? $t['drop_ids'] : array($t['drop_id']) as $i) $back[] = (int) $i;
	}
	return array(true, '', $back);
}

/** Take one piece off the board. */
function dhcp_unstage($conn, $user_id, $drop_id) {
	$uid = (int) $user_id; $did = (int) $drop_id;
	if ($uid <= 0 || $did <= 0) return false;
	/* THE WHOLE PIECE, both halves of a pair. Taking one off and leaving
	   the strap behind would put half a weapon on the board, which is
	   the bug this all exists to avoid, from the other direction. */
	$board = dhcp_board($conn, $uid);
	$drop = array();
	foreach ($board['pieces'] as $p) {
		if (in_array($did, $p['drop_ids'], true)) { $drop = $p['drop_ids']; break; }
	}
	if (!$drop) return false;
	$out = array();
	foreach (dhcp_raw($uid) as $i) if (!in_array($i, $drop, true)) $out[] = $i;
	dhcp_put($uid, $out);
	return true;
}

/** Clear it. */
function dhcp_clear($conn, $user_id) {
	$uid = (int) $user_id;
	if ($uid <= 0) return false;
	dhcp_put($uid, array());
	return true;
}
