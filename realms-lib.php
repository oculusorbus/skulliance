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

/**
 * Realms whose owner has been seen in the last $days, best first.
 *
 * FOR THE ONBOARDING PAGE, which until now argued for Realms entirely in
 * the abstract. A list of people who are actually playing, with their
 * faces on it, is the one piece of evidence a pitch cannot fake.
 *
 * last_login IS THE RIGHT CLOCK. skulliance.php writes it at most once a
 * day on any signed-in page load, so it means "last seen" rather than
 * "last Discord handshake" -- the same reasoning admin-lapsed.php runs
 * on, in the other direction.
 *
 * Levels are summed over locations 1-7 only, matching the realms
 * leaderboard exactly, so a realm cannot be shown here at one level and
 * on the leaderboard at another.
 */
function realm_showcase($conn, $limit = 6, $days = 30) {
	$limit = max(1, min(24, (int) $limit));
	$days  = max(1, min(365, (int) $days));
	$sql = "SELECT realms.id AS realm_id, realms.name AS realm_name, realms.theme_id,
	               users.username, users.avatar, users.discord_id,
	               projects.name AS faction_name, projects.currency AS faction_currency,
	               COALESCE(SUM(realms_locations.level), 0) AS total_levels,
	               (SELECT COUNT(*) FROM raids
	                 WHERE raids.offense_id = realms.id AND raids.outcome = '1') AS raids_won
	        FROM realms
	        INNER JOIN users ON users.id = realms.user_id
	        INNER JOIN projects ON projects.id = realms.project_id
	        LEFT JOIN realms_locations ON realms_locations.realm_id = realms.id
	             AND realms_locations.location_id IN (1,2,3,4,5,6,7)
	        WHERE realms.active = '1'
	          AND users.last_login >= (NOW() - INTERVAL " . $days . " DAY)
	        GROUP BY realms.id
	        ORDER BY total_levels DESC, realms.name ASC
	        LIMIT " . $limit;
	$result = $conn->query($sql);
	if (!$result || $result->num_rows == 0) return array();
	$rows = array();
	while ($row = $result->fetch_assoc()) {
		$rows[] = array(
			'realm'     => $row['realm_name'],
			'theme_id'  => (int) $row['theme_id'],
			'username'  => $row['username'],
			'avatar'    => avatar_url($row['discord_id'], $row['avatar']),
			'faction'   => $row['faction_name'],
			'currency'  => $row['faction_currency'],
			'levels'    => (int) $row['total_levels'],
			'raids_won' => (int) $row['raids_won'],
		);
	}
	return $rows;
}

/**
 * The starter pack, read off the code that actually grants it.
 *
 * createRealm() loops getProjects($conn, 'core') and credits 1000 of
 * each. The onboarding page used to print its own hand-typed list of
 * seven currencies beside it, which is a promise maintained in two
 * places -- add a core project and the page quietly under-sells, drop
 * one and it lies.
 */
function realm_starter_pack($conn) {
	$out = array();
	foreach (getProjects($conn, 'core') as $project) {
		$out[] = array('currency' => $project['currency'],
		               'name'     => $project['name'],
		               'amount'   => REALM_STARTER_EACH);
	}
	return $out;
}

/**
 * realm_dossier() — everything the map's card needs about one realm.
 *
 * WHY THIS EXISTS. The card used to carry what the map already had in
 * hand: an avatar, a name, an average level, and a count of raids. A
 * reader on the map is doing the one thing that data cannot answer --
 * "the owner put it best: they're exploring the map and evaluating the
 * raids that are happening" -- and a count of two raids tells you
 * nothing about whether the realm is worth attacking or who is already
 * on it.
 *
 * SO IT IS THE ATTACK SCREEN'S OWN DATA. getRealmLocationsWithShields()
 * is what realms.php's raid preview filters into its defense and offense
 * columns, shields and all, so the card shows the same levels from the
 * same function rather than a second opinion assembled here.
 *
 * READ ONLY, AND SAFE TO SHOW A STRANGER. Every field is something the
 * platform already publishes: profile.php serves any username and avatar
 * without a login, and a realm's levels are what an attacker sees before
 * committing. Nothing here is the viewer's own position -- no success
 * chance, no "your offense", because on a public map there is no viewer
 * to compute it against.
 *
 * ONE ARRAY, NO ECHO, so the harness can read it without a page.
 */
function realm_dossier($conn, $realm_id) {
	$realm_id = intval($realm_id);
	if ($realm_id <= 0) return null;

	$sql = "SELECT realms.id, realms.name AS realm_name, realms.theme_id, realms.active,
	               users.username, users.discord_id, users.avatar,
	               projects.name AS faction_name, projects.currency AS faction_currency
	        FROM realms
	        INNER JOIN users ON users.id = realms.user_id
	        INNER JOIN projects ON projects.id = realms.project_id
	        WHERE realms.id = " . $realm_id . " LIMIT 1";
	$res = $conn->query($sql);
	if (!$res || $res->num_rows === 0) return null;
	$r = $res->fetch_assoc();

	/* The attack screen's own list, so the two cannot disagree. */
	$locs = function_exists('getRealmLocationsWithShields')
		? getRealmLocationsWithShields($conn, $realm_id) : array();
	$defense = array(); $offense = array(); $portal = null;
	foreach ($locs as $l) {
		$row = array(
			'name'       => $l['name'],
			'level'      => (int) $l['level'],
			'shield'     => !empty($l['has_shield']),
			'boost'      => (int) $l['success_boost'],
			'id'         => (int) $l['location_id'],
		);
		/* The portal is location 1 and is neither column: realms.php
		   pulls it out of the offense list for the same reason. */
		if ((int) $l['location_id'] === 1) { $portal = $row; continue; }
		if ($l['location_type'] === 'defense') $defense[] = $row; else $offense[] = $row;
	}
	$avg = function ($rows) {
		if (!count($rows)) return 0;
		$s = 0; foreach ($rows as $x) $s += $x['level'];
		return (int) round($s / count($rows));
	};

	/* Soldiers, counted rather than listed: the card says how many can
	   march, not which NFTs they are. */
	$ready = 0; $training = 0;
	if (function_exists('getBarracksSoldiers')) {
		$now = time();
		foreach (getBarracksSoldiers($conn, $realm_id) as $s) {
			if (!empty($s['dead'])) continue;
			if (!empty($s['ready_at']) && strtotime($s['ready_at']) > $now) $training++;
			else $ready++;
		}
	}

	/*
	 * THE RAIDS, NAMED. outcome 0 is a raid still running, 1 is the
	 * attacker through, 2 is the defender holding -- the same reading
	 * db.php settles them by.
	 */
	$rs = $conn->query(
		"SELECT r.id, r.offense_id, r.defense_id, r.outcome, r.created_date,
		        orl.name AS off_realm, ou.username AS off_user,
		        drl.name AS def_realm, du.username AS def_user
		 FROM raids r
		 INNER JOIN realms orl ON orl.id = r.offense_id
		 INNER JOIN users ou ON ou.id = orl.user_id
		 INNER JOIN realms drl ON drl.id = r.defense_id
		 INNER JOIN users du ON du.id = drl.user_id
		 WHERE r.offense_id = " . $realm_id . " OR r.defense_id = " . $realm_id . "
		 ORDER BY r.created_date DESC LIMIT 40");
	$active = array(); $recent = array();
	$won = 0; $lost = 0; $held = 0; $fell = 0;
	if ($rs) {
		while ($q = $rs->fetch_assoc()) {
			$attacking = ((int) $q['offense_id'] === $realm_id);
			$row = array(
				'raid_id'    => (int) $q['id'],
				'attacking'  => $attacking,
				'other'      => $attacking ? $q['def_realm'] : $q['off_realm'],
				'other_user' => $attacking ? $q['def_user']  : $q['off_user'],
				'other_id'   => $attacking ? (int) $q['defense_id'] : (int) $q['offense_id'],
				'when'       => $q['created_date'],
			);
			if ((int) $q['outcome'] === 0) { $active[] = $row; continue; }
			$success = ((int) $q['outcome'] === 1);
			$row['result'] = $attacking
				? ($success ? 'won' : 'lost')
				: ($success ? 'fell' : 'held');
			if ($row['result'] === 'won')  $won++;
			if ($row['result'] === 'lost') $lost++;
			if ($row['result'] === 'held') $held++;
			if ($row['result'] === 'fell') $fell++;
			if (count($recent) < 6) $recent[] = $row;
		}
	}

	return array(
		'realm_id'  => $realm_id,
		'realm'     => $r['realm_name'],
		'owner'     => $r['username'],
		'avatar'    => function_exists('avatar_url')
			? avatar_url($r['discord_id'], $r['avatar']) : '',
		'theme_id'  => (int) $r['theme_id'],
		'faction'   => $r['faction_name'],
		'currency'  => strtolower((string) $r['faction_currency']),
		'active'    => (int) $r['active'] === 1,
		'defense'   => $defense,
		'offense'   => $offense,
		'portal'    => $portal,
		'def_avg'   => $avg($defense),
		'off_avg'   => $avg($offense),
		'soldiers'  => array('ready' => $ready, 'training' => $training),
		'raids'     => array('active' => $active, 'recent' => $recent),
		'record'    => array('won' => $won, 'lost' => $lost, 'held' => $held, 'fell' => $fell),
	);
}
