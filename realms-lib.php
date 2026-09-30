<?php
/**
 * realms-lib.php -- the locations panel, as data.
 *
 * ADDITIVE, exactly like missions-lib.php. db.php's echoing renderers are
 * left alone -- getRealmLocationUpgrade() still returns its block of HTML and
 * still has the side effect everything depends on -- and this sits beside
 * them returning values, so the panel can be rendered once and reverted in a
 * line if it goes wrong.
 *
 * WHY IT EXISTS AT ALL. realms.php and ajax/get-locations.php each carried
 * their own near-verbatim copy of the panel: the same nine-slot inventory
 * strip, the same category wrappers, the same row markup, the same three-line
 * price ladder. One of them had a Guide button and the other did not, which
 * is how you find out a copy has drifted. There is one partial now
 * (realms-locations.php) and both include it.
 */

require_once __DIR__ . '/db.php';

/** The seven equippable items, in the order the strip shows them. */
function realm_con_names() {
	return array(1 => '100% Success', 2 => '75% Success', 3 => '50% Success',
	             4 => '25% Success',  5 => 'Fast Forward', 6 => 'Double Rewards',
	             7 => 'Random Reward');
}

/** Icon filename for one item, matching what is on disk. */
function realm_con_icon($name) {
	return strtolower(str_replace('%', '', str_replace(' ', '-', $name))) . '.png';
}

/**
 * Upgrades in flight, as data rather than a block of HTML.
 *
 * MUST BE CALLED AFTER getRealmLocationsUpgrades(), never instead of it: that
 * function is what actually COMPLETES a due upgrade -- it applies the level,
 * deletes the row, burns the Fast Forward and posts to Discord. Reading the
 * table without calling it first would show a finished upgrade as still
 * running, forever.
 */
function realm_location_upgrades_data($conn, $realm_id) {
	$out = array();
	$res = $conn->query("SELECT location_id, duration, level, created_date
	                     FROM upgrades WHERE realm_id = " . (int)$realm_id);
	if (!$res) return $out;
	while ($row = $res->fetch_assoc()) {
		$target   = ((int)$row['level'] > 0) ? (int)$row['level'] : (int)$row['duration'];
		$deadline = strtotime('+' . (int)$row['duration'] . ' day', strtotime($row['created_date']));
		if ($deadline <= time()) continue;          // due; the call above will have cleared it
		$out[(int)$row['location_id']] = array(
			'target'   => $target,
			'days'     => (int)$row['duration'],
			'deadline' => $deadline,
		);
	}
	return $out;
}

/**
 * What the equipped items actually do at one location.
 *
 * The success figures are the same table getLocationSuccessRateBoost() uses
 * to decide a raid, so the number on the card is the number that fights. The
 * panel used to print four separate name tags -- "+10% Success", "Fast
 * Forward", "Shield", "Random Reward" -- immediately above seven slots that
 * showed the identical state with ticks on them. One of the two had to go,
 * and the slots are the control, so the tags became this: what the loadout
 * DOES, which the slots cannot say.
 */
function realm_location_effects($equipped) {
	$boost_map = array(1 => 4, 2 => 3, 3 => 2, 4 => 1);
	$success = 0;
	foreach ($boost_map as $cid => $pts) if (isset($equipped[$cid])) $success += $pts;
	$success = min(10, $success);
	$notes = array();
	if (isset($equipped[5])) $notes[] = 'half-time upgrades';
	/*
	 * CONSUMABLE 6 IS CALLED "Double Rewards" AND ON A LOCATION IT IS A
	 * SHIELD. startRaid() reads it through hasDoubleRewardsShield(): when the
	 * location is hit, the shield is spent instead, the location keeps its
	 * level and every other item on it survives. The old panel labelled it
	 * "Shield", which was right about the behaviour and looked like a
	 * mislabel; this says what it does.
	 */
	if (isset($equipped[6])) $notes[] = 'shields one raid hit';
	/* 7 is annotated by the caller -- see realm_location_panel(). On its own
	   a location cannot know whether the rest of its side is covered. */
	if (isset($equipped[7])) $notes[] = 'random reward';
	return array('success' => $success, 'notes' => $notes);
}

/**
 * RANDOM REWARD DOES NOTHING UNTIL A WHOLE SIDE HAS IT.
 *
 * hasRandomReward() returns false unless EVERY location of that type carries
 * one -- offense is 1,2,4,6 and defense 3,5,7. The old panel put a flat
 * "Random Reward" tag on any single location that had one, which reads as an
 * active effect and is not: one on its own does nothing at all. Exactly the
 * kind of stat that made this panel untrustworthy.
 *
 * Takes the rows by reference and rewrites the note in place. Separate from
 * realm_location_panel() so it can be tested without a database -- a
 * location cannot answer this about itself, so it has to live somewhere that
 * sees the whole set.
 */
function realm_annotate_random_reward(&$rows) {
	$sides = array('offense' => array(1, 2, 4, 6), 'defense' => array(3, 5, 7));
	$has = array();
	foreach ($rows as $row) $has[(int)$row['id']] = !empty($row['equipped'][7]);

	$armed = array();
	foreach ($sides as $side => $ids) {
		$armed[$side] = true;
		foreach ($ids as $lid) {
			if (empty($has[$lid])) { $armed[$side] = false; break; }
		}
	}
	foreach ($rows as $i => $row) {
		if (empty($row['equipped'][7])) continue;
		$side = in_array((int)$row['id'], $sides['defense'], true) ? 'defense' : 'offense';
		foreach ($rows[$i]['effects']['notes'] as $n => $note) {
			if ($note !== 'random reward') continue;
			$rows[$i]['effects']['notes'][$n] = $armed[$side]
				? 'random reward armed'
				: 'random reward (needs every ' . $side . ' location)';
		}
	}
}

/** Offense and defense success, averaged the way a raid reads them. */
function realm_raid_boosts($conn, $realm_id) {
	return array(
		'offense' => (int)getLocationSuccessRateBoost($conn, $realm_id, 'offense'),
		'defense' => (int)getLocationSuccessRateBoost($conn, $realm_id, 'defense'),
	);
}

/**
 * Everything the panel draws, in one call.
 *
 * @return array|null  null when the player has no active realm
 */
function realm_location_panel($conn) {
	if (empty($_SESSION['userData']['user_id'])) return null;

	/* FIRST, and for its side effect: this completes anything that is due. */
	getRealmLocationsUpgrades($conn);

	$realm_id = (int)getRealmID($conn);
	if ($realm_id <= 0) return null;

	$locations   = getLocationInfo($conn);
	$levels      = getRealmLocationLevels($conn);
	$equipped_by = getRealmLocationConsumables($conn, $realm_id);
	$amounts     = getCurrentAmounts($conn);
	$running     = realm_location_upgrades_data($conn, $realm_id);
	$cons        = realm_con_names();

	$inventory = array();
	foreach ($cons as $cid => $cname) {
		$inventory[$cid] = isset($amounts[$cid]) ? (int)$amounts[$cid]['amount'] : 0;
	}

	$rows = array();
	foreach ($locations as $location_id => $location) {
		$location_id = (int)$location_id;
		$equipped = isset($equipped_by[$location_id]) ? $equipped_by[$location_id] : array();
		$row = array(
			'id'          => $location_id,
			'name'        => $location['name'],
			'type'        => $location['type'],
			'description' => $location['description'],
			'level'       => isset($levels[$location_id]) ? (int)$levels[$location_id] : 0,
			'equipped'    => $equipped,
			'effects'     => realm_location_effects($equipped),
			'running'     => isset($running[$location_id]) ? $running[$location_id] : null,
			'quote'       => null,
		);
		/* Only price a location that is idle. The quote refuses a running one
		   anyway, but asking costs queries the panel does not need. */
		if (!$row['running']) $row['quote'] = realmUpgradeQuote($conn, $location_id);
		$rows[] = $row;
	}

	realm_annotate_random_reward($rows);

	return array(
		'realm_id'   => $realm_id,
		'consumables'=> $cons,
		'inventory'  => $inventory,
		'rows'       => $rows,
		'boosts'     => realm_raid_boosts($conn, $realm_id),
		'ceiling'    => REALM_UPGRADE_CEILING,
	);
}
