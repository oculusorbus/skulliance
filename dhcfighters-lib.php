<?php
/**
 * DHC FIGHTERS -- core library
 *
 * The "game" is: earn traits by playing everything else on the platform,
 * assemble them into Fighters, save them, and compete on how rare your best
 * one is. This file owns the rules that decide what drops, what it is worth,
 * and what a Fighter is called.
 *
 * Include AFTER db.php (needs $conn) and skulliance.php (needs the session).
 * Pure logic and queries only -- no output, so AJAX endpoints and pages can
 * both use it.
 */

require_once __DIR__ . '/dhcfighters-config.php';

/* ------------------------------------------------------------------ *
 * RARITY AND SCORING
 * ------------------------------------------------------------------ */

/** The rarity table, keyed category => slug => [tier, worn, rate]. */
function dhcf_rarity() {
	static $r = null;
	if ($r === null) {
		$r = is_file(__DIR__ . '/dhcrarity.php') ? (require __DIR__ . '/dhcrarity.php') : array();
	}
	return $r;
}

function dhcf_trait_info($category, $slug) {
	$r = dhcf_rarity();
	return isset($r[$category][$slug]) ? $r[$category][$slug] : null;
}

/**
 * POINTS FOR ONE TRAIT.
 *
 * Logarithmic, deliberately. A linear 1/rate would let a single 0.14% mythic
 * outweigh nine other traits combined, so every Fighter would be "one jackpot
 * plus filler". On a log curve the rarest trait is worth about 2.5x the most
 * common one instead of 300x, which means filling a slot always helps and
 * chasing rarity still pays -- both axes matter.
 *
 *   0.14% mythic   -> 285      2.80% uncommon -> 155
 *   1.00% mythic   -> 200      4.89% common   -> 131
 *   1.29% legendary-> 189      7.33% common   -> 113
 *   1.70% epic     -> 177
 */
function dhcf_trait_points($rate) {
	$rate = (float)$rate;
	if ($rate <= 0) $rate = 0.01;           // never divide by zero on missing data
	return (int)round(100 * log10(100 / $rate));
}

/**
 * A Fighter's rarity score: the sum of its filled slots.
 *
 * Recomputable from the traits alone, which is the point -- rarity is a VIEW,
 * not a stored fact. When the maths changes, rescore; nothing in the ledger
 * moves. The stored column is a cache for leaderboard sorting.
 */
function dhcf_score($traits) {
	$total = 0;
	foreach ($traits as $slot => $slug) {
		if ($slug === '' || $slug === null) continue;
		$cat  = dhcf_slot_category($slot);
		$info = dhcf_trait_info($cat, $slug);
		if ($info) $total += dhcf_trait_points($info[2]);
	}
	return $total;
}

/**
 * Canonical fingerprint of a trait set.
 *
 * Sorted by slot before hashing, so the same pieces always produce the same
 * hash no matter what order they were picked in -- two players who assembled
 * an identical Fighter by different routes must be recognisable as having
 * built the same thing.
 */
function dhcf_traits_hash($traits) {
	$pairs = array();
	foreach ($traits as $slot => $slug) {
		if ($slug === '' || $slug === null) continue;
		$pairs[] = $slot . ':' . $slug;
	}
	sort($pairs);
	return sha1(implode('|', $pairs));
}

/**
 * Is this player the first to have built this configuration?
 *
 * Earliest live Fighter with the hash wins it, and keeps it -- a later copy
 * never takes it away. Disassembled Fighters do not hold a claim: if you took
 * it apart, the configuration is available to be discovered again.
 *
 * $exclude_id skips the row being rescored or edited, so a Fighter is never
 * compared against itself.
 */
function dhcf_is_first_build($conn, $user_id, $hash, $exclude_id = 0) {
	$sql = sprintf("SELECT user_id FROM dhc_fighters
	                WHERE traits_hash = '%s' AND disassembled_at IS NULL%s
	                ORDER BY created_at ASC, id ASC LIMIT 1",
		$conn->real_escape_string($hash),
		$exclude_id ? ' AND id <> ' . (int)$exclude_id : '');
	$res = $conn->query($sql);
	if (!$res || !$res->num_rows) return true;            // nobody holds it
	return (int)$res->fetch_assoc()['user_id'] === (int)$user_id;
}

/** Score with the originality bonus applied, and the parts that made it. */
function dhcf_score_with_bonus($conn, $user_id, $traits, $exclude_id = 0) {
	$base  = dhcf_score($traits);
	$hash  = dhcf_traits_hash($traits);
	$first = dhcf_is_first_build($conn, $user_id, $hash, $exclude_id);
	$bonus = $first ? (int)round($base * DHCF_ORIGINALITY_BONUS) : 0;
	return array('hash' => $hash, 'base' => $base, 'first' => $first,
	             'bonus' => $bonus, 'total' => $base + $bonus);
}

/* ------------------------------------------------------------------ *
 * NAMING
 * ------------------------------------------------------------------ */

/**
 * Next serial number, continuing the collection's own numbering.
 *
 * The minted collection runs DHC2F001..DHC2F420 (226 actually minted, so the
 * range has gaps, and the unminted numbers inside it are NOT reused -- they
 * belong to the collection if it ever completes). Assemblies therefore start
 * after the top of the range, at DHCF_SERIAL_START.
 */
/**
 * The lowest number not currently in use, from DHCF_SERIAL_START up.
 *
 * Was MAX(serial)+1, which retired a number permanently the moment its Fighter
 * was disassembled -- 422 and 423 were burned that way inside the first week,
 * by one player disassembling and reassembling before editing existed. Now
 * that a Fighter can be edited in place, disassembly means deleting a
 * character outright, and a character that no longer exists has no claim on a
 * number.
 *
 * Disassembly releases the serial (see dhcf_delete_fighter), so the gap it
 * leaves is the next number handed out. Numbering stays dense and the
 * collection's count means what it looks like it means.
 *
 * Reading every serial rather than asking SQL for the first gap: the set is a
 * few hundred at most, and the loop is obvious where the SQL for it is not.
 */
function dhcf_next_serial($conn) {
	$start = DHCF_SERIAL_START;
	$used  = array();
	$res = $conn->query("SELECT serial FROM dhc_fighters WHERE serial IS NOT NULL");
	if ($res) while ($row = $res->fetch_assoc()) $used[(int)$row['serial']] = true;
	$n = $start;
	while (isset($used[$n])) $n++;
	return $n;
}

/** The default name for a serial, e.g. 'DHC2F421'. */
function dhcf_default_name($serial) {
	return DHCF_SERIAL_PREFIX . str_pad((string)$serial, DHCF_SERIAL_PAD, '0', STR_PAD_LEFT);
}

/** What a Fighter is actually called -- the override if set, else the serial. */
function dhcf_display_name($row) {
	$n = isset($row['name']) ? trim((string)$row['name']) : '';
	if ($n !== '') return $n;
	// A disassembled Fighter has released its serial, so there is no number to
	// fall back to. Nothing displays those rows, but a bare 'DHC2F000' leaking
	// into a log or an error is worse than saying what it is.
	if (!isset($row['serial']) || $row['serial'] === null) return 'a disassembled Fighter';
	return dhcf_default_name((int)$row['serial']);
}

/* ------------------------------------------------------------------ *
 * AWARDING A TRAIT
 * ------------------------------------------------------------------ */

/**
 * Draw one trait from a category, weighted by tier.
 *
 * Two stages, because the two things are tuned separately: the TIER comes from
 * the table the trigger earned (topping a board rolls on a better one than a
 * routine run), and only then is a trait picked from within that tier. Picking
 * straight from the flat per-trait rates would make placement barely matter,
 * since common traits dominate the pool by count.
 *
 * $category  one of the trait directories, or 'wildcard' for any category
 * $tiers     tier => weight, e.g. DHCF_TIERS['placement_1']
 */
function dhcf_draw($category, $tiers) {
	$r = dhcf_rarity();

	// wildcard: pool every category together
	$pool = array();
	if ($category === 'wildcard') {
		foreach ($r as $cat => $traits) {
			foreach ($traits as $slug => $info) $pool[] = array($cat, $slug, $info);
		}
	} elseif (isset($r[$category])) {
		foreach ($r[$category] as $slug => $info) $pool[] = array($category, $slug, $info);
	}

	// Suspended traits leave the DRAW, never ownership. Filtered here rather
	// than at each call site so no source can hand one out -- including the
	// wildcard, which pools every category and would route straight past a
	// per-category guard.
	if (DHCF_SUSPENDED) {
		$pool = array_values(array_filter($pool, function ($p) {
			return !in_array($p[1], DHCF_SUSPENDED, true);
		}));
	}
	if (!$pool) return null;

	// bucket by tier, then weight the buckets that actually have traits in them
	$byTier = array();
	foreach ($pool as $p) $byTier[$p[2][0]][] = $p;

	$total = 0; $avail = array();
	foreach ($tiers as $tier => $w) {
		if (!empty($byTier[$tier]) && $w > 0) { $avail[$tier] = $w; $total += $w; }
	}
	if ($total <= 0) return null;

	$roll = mt_rand(1, 100000) / 100000 * $total;
	$pickedTier = null;
	foreach ($avail as $tier => $w) { $roll -= $w; if ($roll <= 0) { $pickedTier = $tier; break; } }
	if ($pickedTier === null) $pickedTier = array_key_last($avail);

	$bucket = $byTier[$pickedTier];
	return $bucket[mt_rand(0, count($bucket) - 1)];   // [category, slug, info]
}

/**
 * Award a trait and write it to the ledger. Returns the awarded row, or null.
 *
 * $source        game key, see DHCF_GAMES
 * $source_detail free text for the audit trail ('won', 'wave 41', boss name)
 * $tierTable     which tier weights to roll on
 */
function dhcf_award($conn, $user_id, $category, $source, $source_detail = '', $tierTable = null) {
	$user_id = (int)$user_id;
	if ($user_id <= 0) return null;
	if ($tierTable === null) $tierTable = DHCF_TIERS['run'];

	/*
	 * THE DAILY CAP LIVES HERE, not only in the claim endpoint.
	 *
	 * It used to be enforced solely in ajax/dhc-claim-drop.php, which the games
	 * go through -- but missions and the reward streak call this function
	 * directly from db.php and were bounded by nothing at all. A staker with
	 * MAXI could clear ten missions across every level in a day and take ten
	 * traits, while a player topping a leaderboard was held to three.
	 *
	 * Enforcing it at the single point where a trait is actually created means
	 * no source can be added later that quietly skips it. The endpoint keeps
	 * its own check so it can return a message the modal can explain; this is
	 * the guarantee underneath it.
	 */
	$cap = dhcf_cap($source);
	if ($cap > 0) {
		$sql = sprintf("SELECT COUNT(*) AS c FROM dhc_trait_drops
		                WHERE user_id = %d AND source = '%s' AND awarded_at >= CURDATE()",
			$user_id, $conn->real_escape_string($source));
		$res = $conn->query($sql);
		// FAIL CLOSED. Written as `if ($res && ... >= $cap) return null` this
		// awards anyway when the count cannot be read -- a failed query would
		// silently lift the cap for as long as it kept failing, which is the
		// opposite of what a limit is for. A missed trait is recoverable; an
		// uncapped one is already in the ledger.
		if (!$res) {
			error_log('dhcf_award: cap check failed for ' . $source . ' -- ' . $conn->error);
			return null;
		}
		$row = $res->fetch_assoc();
		if (!$row || (int)$row['c'] >= $cap) return null;
	}

	$drawn = dhcf_draw($category, $tierTable);
	if (!$drawn) return null;
	list($cat, $slug, $info) = $drawn;

	$sql = sprintf(
		"INSERT INTO dhc_trait_drops (user_id, category, slug, tier, drop_rate, source, source_detail, awarded_at)
		 VALUES (%d, '%s', '%s', '%s', %.3f, '%s', '%s', NOW())",
		$user_id,
		$conn->real_escape_string($cat),
		$conn->real_escape_string($slug),
		$conn->real_escape_string($info[0]),
		(float)$info[2],
		$conn->real_escape_string($source),
		$conn->real_escape_string(substr($source_detail, 0, 120))
	);
	if (!$conn->query($sql)) {
		error_log('dhcf_award: ' . $conn->error);
		return null;
	}

	$drop = array(
		'category' => $cat,
		'slug'     => $slug,
		'tier'     => $info[0],
		'worn'     => $info[1],
		'rate'     => $info[2],
		'points'   => dhcf_trait_points($info[2]),
		'name'     => dhcf_trait_name($cat, $slug),
		'is_new'   => dhcf_count_owned($conn, $user_id, $slug) <= 1,
	);

	/*
	 * HELD FOR REVEAL. A trait is awarded by this function, not by the modal --
	 * so a player who clicks away from a win screen still gets it, but may
	 * never SEE it. The reveal is most of the point, so the drop is parked in
	 * the session and shown on whatever page they land on next. The browser
	 * clears it once it has actually been displayed.
	 */
	if (session_status() === PHP_SESSION_ACTIVE) {
		if (!isset($_SESSION['dhcf_unseen']) || !is_array($_SESSION['dhcf_unseen'])) {
			$_SESSION['dhcf_unseen'] = array();
		}
		// Bounded: a reveal queue is a nicety, not a ledger. The ledger is the
		// database, and it already has every one of these.
		if (count($_SESSION['dhcf_unseen']) < 5) $_SESSION['dhcf_unseen'][] = $drop;
	}

	// Announced from here rather than from the endpoint, so every award posts
	// no matter which caller made it. The ledger row is already written; the
	// notifier swallows its own failures and never reaches back into this.
	if (is_file(__DIR__ . '/dhcfighters-notify.php')) {
		// Buffered around the REQUIRE as well as the call: loading the notifier
		// pulls in webhooks.php, which pulls in its credentials file, and any
		// warning from that chain would land in the middle of an AJAX endpoint's
		// JSON. display_errors is on platform-wide, so this is not theoretical.
		ob_start();
		require_once __DIR__ . '/dhcfighters-notify.php';
		dhcf_notify_drop($conn, $user_id, $drop, $source);
		ob_end_clean();
	}

	return $drop;
}

/** How many copies of a slug a player holds (duplicates are separate rows). */
function dhcf_count_owned($conn, $user_id, $slug) {
	$sql = sprintf("SELECT COUNT(*) AS c FROM dhc_trait_drops WHERE user_id = %d AND slug = '%s'",
		(int)$user_id, $conn->real_escape_string($slug));
	$res = $conn->query($sql);
	if ($res && $row = $res->fetch_assoc()) return (int)$row['c'];
	return 0;
}

/* ------------------------------------------------------------------ *
 * INVENTORY AND FIGHTERS
 * ------------------------------------------------------------------ */

/**
 * TRAITS ARE CONSUMABLE.
 *
 * Owning a trait is not the same as having one free. Committing an Axe to a
 * Fighter spends it; building a second Fighter with an Axe needs a second Axe.
 * Disassembling or swapping a trait out returns it to the pool.
 *
 * That makes duplicates the point rather than dead weight -- a second copy of
 * a common is what lets you keep the first Fighter and still build another --
 * so the collection never stops being worth drawing from.
 *
 * Availability is DERIVED, never stored: owned (ledger rows) minus committed
 * (traits inside saved Fighters). Nothing can drift out of sync because there
 * is only one number, computed from two immutable-ish sources.
 */

/** Copies awarded, category => slug => count. */
function dhcf_owned($conn, $user_id) {
	$out = array();
	$sql = sprintf("SELECT category, slug, COUNT(*) AS copies, MIN(awarded_at) AS first_at
	                FROM dhc_trait_drops WHERE user_id = %d GROUP BY category, slug", (int)$user_id);
	$res = $conn->query($sql);
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$out[$row['category']][$row['slug']] = array(
				'copies' => (int)$row['copies'],
				'first'  => $row['first_at'],
			);
		}
	}
	return $out;
}

/**
 * Copies currently locked inside saved Fighters, slug => count.
 *
 * $ignore_id skips one Fighter, which is how editing works: the build being
 * changed must not count itself as competition for its own traits.
 */
function dhcf_committed($conn, $user_id, $ignore_id = 0) {
	$counts = array();
	// disassembled_at IS NULL: a disassembled Fighter holds nothing. Its row
	// survives so its history cannot be rebuilt as if new, but its traits are
	// free again and must not read as committed.
	$sql = sprintf("SELECT id, traits FROM dhc_fighters
	                WHERE user_id = %d AND disassembled_at IS NULL%s",
		(int)$user_id, $ignore_id ? ' AND id <> ' . (int)$ignore_id : '');
	$res = $conn->query($sql);
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$t = json_decode($row['traits'], true) ?: array();
			// counted per occurrence: the same effect in both effects slots
			// legitimately spends two copies
			foreach ($t as $slot => $slug) {
				if ($slug === '' || $slug === null) continue;
				$counts[$slug] = (isset($counts[$slug]) ? $counts[$slug] : 0) + 1;
			}
		}
	}
	return $counts;
}

/** What the player can still place: category => slug => free copies. */
function dhcf_available($conn, $user_id, $ignore_id = 0) {
	$owned     = dhcf_owned($conn, $user_id);
	$committed = dhcf_committed($conn, $user_id, $ignore_id);
	$out = array();
	foreach ($owned as $cat => $traits) {
		foreach ($traits as $slug => $info) {
			$free = $info['copies'] - (isset($committed[$slug]) ? $committed[$slug] : 0);
			$out[$cat][$slug] = array(
				'copies' => $info['copies'],
				'free'   => $free > 0 ? $free : 0,
				'first'  => $info['first'],
			);
		}
	}
	return $out;
}

/** Which traits in a layout the player cannot currently afford. Empty = fine. */
function dhcf_shortfall($available, $traits) {
	$need = array();
	foreach ($traits as $slot => $slug) {
		if ($slug === '' || $slug === null) continue;
		$need[$slug] = (isset($need[$slug]) ? $need[$slug] : 0) + 1;
	}
	$short = array();
	foreach ($need as $slug => $n) {
		$free = 0;
		foreach ($available as $cat => $traits2) {
			if (isset($traits2[$slug])) { $free = $traits2[$slug]['free']; break; }
		}
		if ($free < $n) $short[$slug] = array('need' => $n, 'free' => $free);
	}
	return $short;
}

/** Saved fighters for a player, newest first. */
function dhcf_fighters($conn, $user_id) {
	$out = array();
	$sql = sprintf("SELECT * FROM dhc_fighters
	                WHERE user_id = %d AND disassembled_at IS NULL
	                ORDER BY created_at DESC", (int)$user_id);
	$res = $conn->query($sql);
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$row['traits']  = json_decode($row['traits'], true) ?: array();
			$row['display'] = dhcf_display_name($row);
			$out[] = $row;
		}
	}
	return $out;
}

function dhcf_clean_traits($traits) {
	$clean = array();
	foreach (dhcf_slots() as $slot) {
		if (!empty($traits[$slot])) $clean[$slot] = (string)$traits[$slot];
	}
	return $clean;
}

/**
 * Save a new Fighter, spending its traits. Returns [ok, message, row].
 *
 * Wrapped in a transaction because availability is derived from the very table
 * being written: two saves racing each other would both read the same free
 * copies and both commit them. The UNIQUE key on serial covers the numbering
 * race separately -- a collision just retries with the next number.
 */
function dhcf_save_fighter($conn, $user_id, $traits, $name = '') {
	$user_id = (int)$user_id;
	if ($user_id <= 0) return array(false, 'Not signed in.', null);

	$clean = dhcf_clean_traits($traits);
	if (!$clean) return array(false, 'Nothing to save.', null);

	// Enforced here, not only in the browser: the page is a suggestion.
	$missing = dhcf_missing_required($clean);
	if ($missing) {
		return array(false, 'A Fighter needs a ' . implode(', ', $missing) . '.', null);
	}

	$name  = trim(mb_substr((string)$name, 0, 48));
	$json  = json_encode($clean);

	$conn->begin_transaction();
	try {
		$short = dhcf_shortfall(dhcf_available($conn, $user_id), $clean);
		if ($short) {
			$conn->rollback();
			$names = array();
			foreach ($short as $slug => $s) $names[] = dhcf_trait_name(dhcf_category_of($slug), $slug);
			return array(false, 'You have already used: ' . implode(', ', $names), null);
		}

		for ($try = 0; $try < 5; $try++) {
			$serial = dhcf_next_serial($conn);
			$newest = dhcf_newest_trait_at($conn, $user_id, $clean);
			// Inside the transaction: two players saving the same configuration at
			// once must not both be told they were first.
			$sc     = dhcf_score_with_bonus($conn, $user_id, $clean);
			$score  = $sc['total'];
			$sql = sprintf(
				"INSERT INTO dhc_fighters (user_id, serial, name, traits, traits_hash, rarity_score, rules_version, newest_trait_at, created_at, updated_at)
				 VALUES (%d, %d, %s, '%s', '%s', %d, '%s', %s, NOW(), NOW())",
				$user_id, $serial,
				$name === '' ? 'NULL' : "'" . $conn->real_escape_string($name) . "'",
				$conn->real_escape_string($json),
				$conn->real_escape_string($sc['hash']), $score,
				$conn->real_escape_string(DHCF_RULES_VERSION),
				$newest === null ? 'NOW()' : "'" . $conn->real_escape_string($newest) . "'"
			);
			if ($conn->query($sql)) {
				$id = $conn->insert_id;
				$conn->commit();
				$saved = array(
					'id'      => $id,
					'serial'  => $serial,
					'name'    => $name,
					'display' => $name !== '' ? $name : dhcf_default_name($serial),
					'score'   => $score,
					'base'    => $sc['base'],
					'bonus'   => $sc['bonus'],
					'first'   => $sc['first'],
					'traits'  => $clean,
				);
				// After the commit, never before: an announcement for a save
				// that then rolled back would be a lie, and the render is slow
				// enough to be worth keeping outside the transaction.
				if (is_file(__DIR__ . '/dhcfighters-notify.php')) {
					ob_start();
					require_once __DIR__ . '/dhcfighters-notify.php';
					dhcf_notify_fighter($conn, $user_id, $saved);
					ob_end_clean();
				}
				return array(true, 'Saved.', $saved);
			}
			if ($conn->errno !== 1062) break;   // 1062 = serial taken, retry
		}
		$conn->rollback();
		error_log('dhcf_save_fighter: ' . $conn->error);
		return array(false, 'Could not save.', null);
	} catch (Exception $e) {
		$conn->rollback();
		error_log('dhcf_save_fighter: ' . $e->getMessage());
		return array(false, 'Could not save.', null);
	}
}

/**
 * The most recent award date among the traits a layout uses.
 *
 * This is what the monthly board ranks on, NOT when the Fighter was saved.
 * Saving keys the wrong thing: disassemble in September, rebuild the identical
 * Fighter on the 1st, and a created_at window hands it the new month for no
 * new play. Trait recency cannot be laundered that way -- to place in a month
 * you must have earned something in it.
 *
 * MAX over the awards of each slug, so holding several copies dates the
 * Fighter by the newest one.
 */
function dhcf_newest_trait_at($conn, $user_id, $traits) {
	$slugs = array();
	foreach ($traits as $slot => $slug) {
		if ($slug === '' || $slug === null) continue;
		$slugs[] = "'" . $conn->real_escape_string($slug) . "'";
	}
	if (!$slugs) return null;
	$sql = sprintf("SELECT MAX(awarded_at) AS m FROM dhc_trait_drops
	                WHERE user_id = %d AND slug IN (%s)", (int)$user_id, implode(',', $slugs));
	$res = $conn->query($sql);
	if ($res && $row = $res->fetch_assoc()) return $row['m'];
	return null;
}

/** Which category a slug belongs to, by searching the rarity table. */
function dhcf_category_of($slug) {
	foreach (dhcf_rarity() as $cat => $traits) if (isset($traits[$slug])) return $cat;
	return '';
}

/** Change a Fighter's traits, releasing the old set and spending the new. */
function dhcf_update_fighter($conn, $user_id, $fighter_id, $traits) {
	$user_id = (int)$user_id; $fighter_id = (int)$fighter_id;
	$clean = dhcf_clean_traits($traits);
	if (!$clean) return array(false, 'Nothing to save.', null);
	$missing = dhcf_missing_required($clean);
	if ($missing) {
		return array(false, 'A Fighter needs a ' . implode(', ', $missing) . '.', null);
	}

	$conn->begin_transaction();
	try {
		// ignore this fighter's own commitments -- it is being replaced, so the
		// traits it currently holds are available to it
		$short = dhcf_shortfall(dhcf_available($conn, $user_id, $fighter_id), $clean);
		if ($short) {
			$conn->rollback();
			$names = array();
			foreach ($short as $slug => $s) $names[] = dhcf_trait_name(dhcf_category_of($slug), $slug);
			return array(false, 'You have already used: ' . implode(', ', $names), null);
		}
		$newest = dhcf_newest_trait_at($conn, $user_id, $clean);
		$sc     = dhcf_score_with_bonus($conn, $user_id, $clean, $fighter_id);
		$sql = sprintf("UPDATE dhc_fighters SET traits = '%s', traits_hash = '%s', rarity_score = %d,
		                rules_version = '%s', newest_trait_at = %s, updated_at = NOW()
		                WHERE id = %d AND user_id = %d",
			$conn->real_escape_string(json_encode($clean)),
			$conn->real_escape_string($sc['hash']), $sc['total'],
			$conn->real_escape_string(DHCF_RULES_VERSION),
			$newest === null ? 'NOW()' : "'" . $conn->real_escape_string($newest) . "'",
			$fighter_id, $user_id);
		if (!$conn->query($sql)) { $conn->rollback(); return array(false, 'Could not save.', null); }
		$conn->commit();
		return array(true, 'Saved.', array('id' => $fighter_id, 'score' => $sc['total'], 'traits' => $clean));
	} catch (Exception $e) {
		$conn->rollback();
		return array(false, 'Could not save.', null);
	}
}

/**
 * Disassemble: delete the Fighter and return every trait it held.
 *
 * The traits come back automatically because availability is derived -- with
 * the row gone, nothing counts those copies as committed. No inventory write,
 * so no way for the two to disagree. The serial goes back into the pool for
 * the same reason: nothing holds it any more.
 *
 * A REAL delete. It used to mark the row instead, on the grounds that keeping
 * it stopped a Fighter being disassembled and rebuilt as though newly made --
 * but that protection never lived here. The monthly board ranks on
 * newest_trait_at, which dhcf_newest_trait_at() reads from dhc_trait_drops,
 * the append-only ledger of when each trait was AWARDED. Rebuilding the same
 * Fighter reproduces the same timestamp, and the ledger is never deleted. The
 * originality check ignores disassembled rows too. So the row was carrying
 * history and nothing else, and the table reads better holding only Fighters
 * that exist.
 *
 * dhc_trait_drops still records everything that was ever earned, so what a
 * player holds is always reconstructible even though what they built is not.
 */
function dhcf_delete_fighter($conn, $user_id, $fighter_id) {
	$sql = sprintf("DELETE FROM dhc_fighters WHERE id = %d AND user_id = %d",
		(int)$fighter_id, (int)$user_id);
	return $conn->query($sql) && $conn->affected_rows > 0;
}

/** Rename, or clear the override by passing ''. */
function dhcf_rename_fighter($conn, $user_id, $fighter_id, $name) {
	$name = trim(mb_substr((string)$name, 0, 48));
	$sql = sprintf("UPDATE dhc_fighters SET name = %s, updated_at = NOW() WHERE id = %d AND user_id = %d",
		$name === '' ? 'NULL' : "'" . $conn->real_escape_string($name) . "'",
		(int)$fighter_id, (int)$user_id);
	return $conn->query($sql);
}

/**
 * Drops awarded today, source => count. One query rather than ten.
 *
 * CURDATE() is server midnight, which is the same boundary the claim endpoint
 * counts against -- so what the interface promises and what the server allows
 * cannot drift apart.
 */
function dhcf_drops_today($conn, $user_id) {
	$out = array();
	$sql = sprintf("SELECT source, COUNT(*) AS c FROM dhc_trait_drops
	                WHERE user_id = %d AND awarded_at >= CURDATE() GROUP BY source", (int)$user_id);
	$res = $conn->query($sql);
	if ($res) while ($row = $res->fetch_assoc()) $out[$row['source']] = (int)$row['c'];
	return $out;
}

/* ------------------------------------------------------------------ *
 * LEADERBOARD
 * ------------------------------------------------------------------ */

/**
 * Top fighters. $period 'ath' or 'monthly'.
 *
 * Ranked on the single best Fighter a player has, with total fighters saved as
 * the tie-break -- so the board rewards one exceptional build, and volume only
 * separates players who already match on quality.
 */
function dhcf_leaderboard($conn, $period = 'ath', $limit = 25) {
	/*
	 * MONTHLY = a Fighter BUILT this month that carries a trait EARNED this
	 * month. Both, not either, because each condition alone has a hole and
	 * they are different holes:
	 *
	 *   created_at alone     -- disassemble September's winner on the 1st,
	 *                           rebuild it identically, get a fresh date and
	 *                           the new month for no new play.
	 *   newest_trait_at alone-- keep September's winner, slot in any trait
	 *                           earned in October, and it is back on the board
	 *                           without being a new character at all.
	 *
	 * Requiring both closes each with the other. Re-entering with last month's
	 * champion now costs a disassembly AND a fresh drop -- the same price as
	 * building a new character, which is the point: the monthly board resets so
	 * newer players get a shot, and last month's winner should not re-enter on
	 * an edit.
	 *
	 * Editing an old Fighter still improves it for ALL-TIME. That board has no
	 * window and never needed one.
	 *
	 * Disassembled Fighters are gone from the table entirely, so no period has
	 * to exclude them any more.
	 */
	$where = ($period === 'monthly')
		? "WHERE f.disassembled_at IS NULL
		     AND f.created_at      >= DATE_FORMAT(NOW(), '%Y-%m-01')
		     AND f.newest_trait_at >= DATE_FORMAT(NOW(), '%Y-%m-01')"
		: "WHERE f.disassembled_at IS NULL";
	$sql = "SELECT f.user_id,
	               MAX(f.rarity_score) AS best_score,
	               COUNT(*)            AS fighters
	        FROM dhc_fighters f
	        $where
	        GROUP BY f.user_id
	        ORDER BY best_score DESC, fighters DESC
	        LIMIT " . (int)$limit;
	$rows = array();
	$res = $conn->query($sql);
	if ($res) while ($row = $res->fetch_assoc()) $rows[] = $row;
	return $rows;
}

/**
 * Recompute every stored score. For after the rarity maths changes.
 *
 * Includes disassembled Fighters on purpose: their scores are unused today,
 * but leaving a stale number on a row that history can still be read from
 * would make the record disagree with the rules that produced it.
 */
/**
 * THE IMAGE LAYERS FOR A FIGHTER, in draw order -- one answer for every still
 * renderer (the gallery grid, the roster cards, anything else that draws a
 * saved Fighter from PHP).
 *
 * The live canvas has its own copy in JS because it redraws on every click,
 * and the Discord render composites with GD rather than <img> tags; all three
 * ask dhcf_armless_mode() the same question, so they agree.
 *
 * Each entry: cat, slug, nudge (percent, for translateY) and clip ('' | 'left'
 * | 'right'), where clip marks the single-sided-arm restore layer -- the normal
 * torso showing through on the side the arm does not cover.
 *
 * $webroot is the filesystem path to the art root (the folder holding 1000/),
 * needed only to see whether a torso has an armless variant. Pass '' to skip
 * that test and always use the plain torso.
 */
function dhcf_layers($traits, $webroot = '') {
	$arms = isset($traits['arms']) ? $traits['arms'] : '';
	$mode = dhcf_armless_mode($arms);
	$out  = array();

	foreach (dhcf_layer_order($traits) as $slot) {
		if (empty($traits[$slot])) continue;
		$slug  = $traits[$slot];
		$cat   = dhcf_slot_category($slot);
		$nudge = isset(DHCF_NUDGE[$slug]) ? (float)DHCF_NUDGE[$slug] / 10 : 0;

		$armless = ($slot === 'torso' && $mode !== 'none' && $webroot !== ''
		            && is_file($webroot . '/1000/torso-noarms/' . $slug . '.png'));

		$out[] = array('cat' => $armless ? 'torso-noarms' : $cat,
		               'slug' => $slug, 'nudge' => $nudge, 'clip' => '');

		// put back the arm a single-sided trait does not cover
		if ($armless && $mode === 'hybrid') {
			$out[] = array('cat' => 'torso', 'slug' => $slug, 'nudge' => $nudge,
			               'clip' => DHCF_ONE_ARM[$arms]);
		}
	}
	return $out;
}

/** CSS for a layer's clip, '' when it is not clipped. */
function dhcf_layer_clip_css($clip) {
	if ($clip === 'right') return 'clip-path:inset(0 0 0 ' . DHCF_ONE_ARM_SPLIT . '%)';
	if ($clip === 'left')  return 'clip-path:inset(0 ' . (100 - DHCF_ONE_ARM_SPLIT) . '% 0 0)';
	return '';
}

function dhcf_rescore_all($conn) {
	$n = 0;
	// Hashes first, so the originality pass below compares against a table
	// where every row's fingerprint is already canonical -- rescoring against
	// half-written hashes would hand the bonus to the wrong builder.
	$res = $conn->query("SELECT id, traits FROM dhc_fighters");
	$rows = array();
	if ($res) while ($row = $res->fetch_assoc()) $rows[] = $row;
	foreach ($rows as $row) {
		$t = json_decode($row['traits'], true) ?: array();
		$conn->query(sprintf("UPDATE dhc_fighters SET traits_hash = '%s' WHERE id = %d",
			$conn->real_escape_string(dhcf_traits_hash($t)), (int)$row['id']));
	}
	$res = $conn->query("SELECT id, user_id, traits FROM dhc_fighters");
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$t  = json_decode($row['traits'], true) ?: array();
			$sc = dhcf_score_with_bonus($conn, (int)$row['user_id'], $t, (int)$row['id']);
			$conn->query(sprintf("UPDATE dhc_fighters SET rarity_score = %d WHERE id = %d",
				$sc['total'], (int)$row['id']));
			$n++;
		}
	}
	return $n;
}
