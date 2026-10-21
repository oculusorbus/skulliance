<?php
/**
 * SAVED SQUADS.
 *
 * A player assembles four soldiers, saves the grouping, and fields it again
 * next game in one press instead of rebuilding it from the barracks. See
 * dropship-squads-schema.md for the two tables and for why a member is keyed
 * on soldiers.id.
 *
 * TWO HALVES, DELIBERATELY SPLIT. Everything that decides anything --
 * dropshipSquadMemberState() and dropshipSquadReadiness() -- takes plain
 * arrays and returns plain arrays, so the rules can be driven by a harness
 * with no database at all. Everything that touches the database is a thin
 * wrapper around them. The run loop in dropship.php is the cautionary tale:
 * sixty lines of nested ifs that could only be understood by executing them,
 * and reading it produced a wrong answer twice.
 *
 * TOLERANT OF THE TABLES NOT EXISTING, like dropshipThemesTable(). This
 * platform is updated by pulling the code and running the migration by hand,
 * so there is always a window where the code is ahead of the database. Until
 * the CREATE runs, every function here reports "no squads" and the barracks
 * renders exactly as it did before.
 */

/** Do the squad tables exist? Per connection, like dropshipThemesTable(). */
function dropshipSquadsTable($conn) {
	static $have = array();
	if (!is_object($conn)) return false;
	$k = spl_object_id($conn);
	if (!array_key_exists($k, $have)) {
		$r = @$conn->query("SHOW TABLES LIKE 'squads'");
		$have[$k] = (bool)($r && $r->num_rows);
	}
	return $have[$k];
}

/**
 * What one member IS, from the soldier row the squad points at.
 *
 * $row is the soldiers row or null when it has gone entirely. $me is the
 * player's dropship user id. Returns one of the five states in the schema
 * doc. Nothing here is stored: every one of these is a fact about the
 * soldiers table that changes without anybody touching a squad.
 */
function dropshipSquadMemberState($row, $me) {
	if (!is_array($row) || !isset($row['id'])) return 'gone';
	/* OWNERSHIP FIRST. A sold soldier can be deceased and deployed on
	   somebody else's field, and none of that is the player's business --
	   "not yours" is the whole answer and the only honest one. */
	if ((int)$row['user_id'] !== (int)$me)   return 'sold';
	if (!empty($row['deceased']))            return 'fallen';
	if (!empty($row['active']))              return 'fielded';
	return 'ready';
}

/**
 * Can this squad be fielded, and if not, why not in the player's words.
 *
 * REFUSES A PARTIAL SQUAD, which is the owner's call and the right one: the
 * whole value of a Super Soldier Squad is that all four armour tiers are
 * present, and quietly fielding three of them would hand somebody 74 while
 * the name on the row still said 80.
 *
 * THE REASON MATTERS MORE THAN THE REFUSAL. 'fallen' is not damage, it is
 * Tuesday: killSquad() kills the field after every run and nothing revives
 * anybody until the next game opens, so a squad reads as fallen for most of
 * the week it was used in. Saying "missing" there would send somebody
 * looking for a bug. Each state gets its own sentence.
 *
 * $states is the list from dropshipSquadMemberState(). $room is how many more
 * soldiers the field can take, which the four-soldier ceiling decides.
 */
function dropshipSquadReadiness($states, $room) {
	$n = array('ready' => 0, 'fielded' => 0, 'fallen' => 0, 'sold' => 0, 'gone' => 0);
	foreach ($states as $s) if (isset($n[$s])) $n[$s]++;
	$total = count($states);

	if ($total === 0) {
		return array('can' => false, 'why' => 'This squad has nobody left in it.',
		             'counts' => $n, 'fix' => 'delete');
	}
	/* Nothing left that is yours: the squad is a name and a memory. Offer the
	   one action that still makes sense rather than a greyed-out Deploy. */
	if ($n['sold'] + $n['gone'] === $total) {
		return array('can' => false, 'counts' => $n, 'fix' => 'delete',
		             'why' => $total === 1
		                 ? 'You no longer own this soldier.'
		                 : 'You no longer own any of these soldiers.');
	}
	if ($n['sold'] + $n['gone'] > 0) {
		return array('can' => false, 'counts' => $n, 'fix' => 'edit',
		             'why' => dropshipSquadPlural($n['sold'] + $n['gone'], 'member')
		                 . ' of this squad ' . (($n['sold'] + $n['gone']) === 1 ? 'is' : 'are')
		                 . ' no longer yours. Disperse it to rebuild.');
	}
	if ($n['fallen'] > 0) {
		return array('can' => false, 'counts' => $n, 'fix' => 'wait',
		             'why' => dropshipSquadPlural($n['fallen'], 'member')
		                 . ' already fought this game. They are back when the next one opens.');
	}
	/* Already standing on the field is not a reason to refuse -- it is the
	   squad being where you wanted it. */
	if ($n['ready'] === 0 && $n['fielded'] === $total) {
		return array('can' => false, 'counts' => $n, 'fix' => 'none',
		             'why' => 'This squad is already deployed.');
	}
	if ($n['ready'] > (int)$room) {
		return array('can' => false, 'counts' => $n, 'fix' => 'withdraw',
		             'why' => 'There is only room for ' . (int)$room . ' more on the field. '
		                 . 'Withdraw somebody first.');
	}
	return array('can' => true, 'why' => '', 'counts' => $n, 'fix' => 'none');
}

/** "1 member" / "3 members", because "1 members" is how a page looks unfinished. */
function dropshipSquadPlural($n, $word) {
	$n = (int)$n;
	return $n . ' ' . $word . ($n === 1 ? '' : 's');
}

/* ---------- the database side ---------------------------------------------- */

/**
 * Every squad this player has in this game, members resolved and labelled.
 *
 * READ FROM squads FIRST AND THE SOLDIERS SECOND. An INNER JOIN would drop a
 * squad whose members are all sold, which is the one squad that most needs to
 * be on screen -- see the schema doc.
 */
function dropshipSquadsAll($conn, $user_id = null, $project_id = null) {
	if (!dropshipSquadsTable($conn)) return array();
	$me  = (int)($user_id    !== null ? $user_id    : ($_SESSION['userData']['dropship_user_id'] ?? 0));
	$pid = (int)($project_id !== null ? $project_id : ($_SESSION['userData']['dropship_project_id'] ?? 0));
	if ($me <= 0 || $pid <= 0) return array();

	$res = @$conn->query("SELECT id, name FROM squads
	                       WHERE user_id = $me AND project_id = $pid
	                       ORDER BY created_at ASC, id ASC");
	if (!$res) return array();
	$squads = array();
	while ($r = $res->fetch_assoc()) {
		$squads[(int)$r['id']] = array('id' => (int)$r['id'], 'name' => (string)$r['name'],
		                               'members' => array());
	}
	if (!$squads) return array();

	$ids = implode(',', array_map('intval', array_keys($squads)));
	/* LEFT JOIN, so a member whose soldier row has gone still comes back as a
	   row with nulls and is labelled 'gone' rather than silently dropped. */
	$res = @$conn->query("SELECT m.squad_id, m.soldier_id, m.slot,
	                             s.id, s.user_id, s.name, s.asset_name, s.rank,
	                             s.armor, s.gear, s.level, s.active, s.deceased
	                        FROM squad_members m
	                        LEFT JOIN soldiers s ON s.id = m.soldier_id
	                       WHERE m.squad_id IN ($ids)
	                       ORDER BY m.slot ASC, m.soldier_id ASC");
	if ($res) {
		while ($r = $res->fetch_assoc()) {
			$sid = (int)$r['squad_id'];
			if (!isset($squads[$sid])) continue;
			$row = isset($r['id']) && $r['id'] !== null ? $r : null;
			if ($row !== null && function_exists('dropshipGruntLoadout')) {
				$row = dropshipGruntLoadout($row);
			}
			$squads[$sid]['members'][] = array(
				'soldier_id' => (int)$r['soldier_id'],
				'state'      => dropshipSquadMemberState($row, $me),
				'row'        => $row,
			);
		}
	}
	foreach ($squads as $sid => $sq) {
		$states = array();
		foreach ($sq['members'] as $m) $states[] = $m['state'];
		$squads[$sid]['ready'] = dropshipSquadReadiness($states, dropshipSquadRoom($conn));
	}
	return array_values($squads);
}

/** How many more soldiers the field will take. The ceiling is four. */
function dropshipSquadRoom($conn) {
	$n = function_exists('checkSquadCount') ? (int)checkSquadCount($conn) : 0;
	return max(0, 4 - $n);
}

/**
 * Which saved squad holds a soldier, or 0.
 *
 * This is what stops a squad member being deployed on its own. Read for the
 * whole barracks in ONE query and handed to the card renderer, because the
 * alternative is a lookup per card and a barracks runs to dozens.
 */
function dropshipSquadMembership($conn, $user_id = null, $project_id = null) {
	if (!dropshipSquadsTable($conn)) return array();
	$me  = (int)($user_id    !== null ? $user_id    : ($_SESSION['userData']['dropship_user_id'] ?? 0));
	$pid = (int)($project_id !== null ? $project_id : ($_SESSION['userData']['dropship_project_id'] ?? 0));
	if ($me <= 0 || $pid <= 0) return array();
	$res = @$conn->query("SELECT m.soldier_id, q.id, q.name
	                        FROM squad_members m
	                        INNER JOIN squads q ON q.id = m.squad_id
	                       WHERE q.user_id = $me AND q.project_id = $pid");
	$out = array();
	if ($res) while ($r = $res->fetch_assoc()) {
		$out[(int)$r['soldier_id']] = array('id' => (int)$r['id'], 'name' => (string)$r['name']);
	}
	return $out;
}

/* ---------- the four things a player can do -------------------------------- */

/**
 * Save the soldiers currently on the field as a named squad.
 *
 * FROM THE FIELD, not from a list the browser sends. The client never names
 * which soldiers go in: the squad is whoever is standing on the field right
 * now, read from the database. deploySoldier() shipped with a comment saying
 * it trusts a posted soldier_id, and a saved squad taking ids from a form
 * would be the same hole with a nicer name on it.
 *
 * Returns array(ok, message).
 */
function dropshipSquadSave($conn, $name) {
	if (!dropshipSquadsTable($conn)) return array(false, 'Saved squads are not set up yet.');
	$me  = (int)($_SESSION['userData']['dropship_user_id'] ?? 0);
	$pid = (int)($_SESSION['userData']['dropship_project_id'] ?? 0);
	if ($me <= 0 || $pid <= 0) return array(false, 'Not signed in.');

	/* Trimmed, length-capped and never empty. The column is VARCHAR(40) and
	   a name longer than that would be silently truncated by MySQL, which is
	   a squad called something the player did not choose. */
	$name = trim(preg_replace('/\s+/', ' ', (string)$name));
	if ($name === '') $name = 'Squad';
	if (function_exists('mb_substr')) $name = mb_substr($name, 0, 40);
	else                              $name = substr($name, 0, 40);

	$res = @$conn->query("SELECT id FROM soldiers
	                       WHERE user_id = $me AND project_id = $pid
	                         AND active = 1 AND deceased = 0
	                       ORDER BY name ASC");
	$ids = array();
	if ($res) while ($r = $res->fetch_assoc()) $ids[] = (int)$r['id'];
	if (!$ids) return array(false, 'Deploy a squad first, then save it.');

	/* ALREADY SAVED? Saving the same four twice gives a player two rows that
	   look identical and behave identically, and no way to tell which is
	   which. Compared as a set, because slot order is a display detail. */
	foreach (dropshipSquadsAll($conn, $me, $pid) as $sq) {
		$have = array();
		foreach ($sq['members'] as $m) $have[] = (int)$m['soldier_id'];
		sort($have);
		$want = $ids; sort($want);
		if ($have === $want) return array(false, 'That squad is already saved as "' . $sq['name'] . '".');
	}

	@$conn->query(sprintf("INSERT INTO squads (user_id, project_id, name, created_at)
	                       VALUES (%d, %d, '%s', NOW())",
	                      $me, $pid, $conn->real_escape_string($name)));
	$sid = (int)$conn->insert_id;
	if ($sid <= 0) return array(false, 'Could not save that squad.');

	$rows = array();
	foreach ($ids as $i => $id) $rows[] = sprintf('(%d,%d,%d)', $sid, $id, $i);
	@$conn->query('INSERT INTO squad_members (squad_id, soldier_id, slot) VALUES '
	              . implode(',', $rows));
	return array(true, 'Saved as "' . $name . '".');
}

/**
 * Field every member of a saved squad.
 *
 * REFUSES RATHER THAN FIELDING PART OF IT -- see dropshipSquadReadiness().
 * The readiness check runs again HERE and not only in the markup: the button
 * is drawn from the same state, but a page can be a minute old and a soldier
 * can have died in another tab, so the decision is made at the moment of the
 * write and never inferred from the fact that somebody pressed a button.
 */
function dropshipSquadDeploy($conn, $squad_id) {
	if (!dropshipSquadsTable($conn)) return array(false, 'Saved squads are not set up yet.');
	$me  = (int)($_SESSION['userData']['dropship_user_id'] ?? 0);
	$pid = (int)($_SESSION['userData']['dropship_project_id'] ?? 0);
	$sid = (int)$squad_id;

	$squad = null;
	foreach (dropshipSquadsAll($conn, $me, $pid) as $sq) if ($sq['id'] === $sid) $squad = $sq;
	/* Not found means not yours: dropshipSquadsAll() only ever returns squads
	   belonging to this player and this project, so a guessed id falls out
	   here rather than being checked for separately and forgotten. */
	if (!$squad) return array(false, 'That is not your squad.');
	if (empty($squad['ready']['can'])) return array(false, $squad['ready']['why']);

	$ids = array();
	foreach ($squad['members'] as $m) {
		if ($m['state'] === 'ready') $ids[] = (int)$m['soldier_id'];
	}
	if (!$ids) return array(false, $squad['ready']['why'] ?: 'Nothing to deploy.');
	/* One statement, and scoped to this player's own rows -- the same guard
	   deploySoldier() is missing.
	   The id list is built FIRST so the SQL is one unbroken string rather
	   than two literals with a concat in the middle. That is readability and
	   it is also testable: a check that the write carries `user_id = $me`
	   cannot read a statement that is stitched together at runtime. */
	$list = implode(',', array_map('intval', $ids));
	@$conn->query("UPDATE soldiers SET active = 1
	                WHERE id IN ($list) AND user_id = $me AND project_id = $pid AND deceased = 0");
	return array(true, '"' . $squad['name'] . '" deployed.');
}

/**
 * Break the grouping up. The soldiers are untouched; only the saved squad goes.
 *
 * NOT A WITHDRAWAL. Dispersing is how a player gets their soldiers back into
 * the loose pool so they can be rebuilt into something else, and it says
 * nothing about whether they are standing on the field -- pulling them off
 * the field as a side effect would make "recalibrate my build" cost a
 * deployment somebody may have wanted to keep.
 */
function dropshipSquadDisperse($conn, $squad_id) {
	if (!dropshipSquadsTable($conn)) return array(false, 'Saved squads are not set up yet.');
	$me  = (int)($_SESSION['userData']['dropship_user_id'] ?? 0);
	$pid = (int)($_SESSION['userData']['dropship_project_id'] ?? 0);
	$sid = (int)$squad_id;
	if ($sid <= 0) return array(false, 'That is not your squad.');

	/* OWNERSHIP IN THE DELETE ITSELF, not in a SELECT before it. A check and
	   then a write is two statements a guessed id can slip between; this one
	   cannot delete a row that is not yours however it is called. */
	@$conn->query("DELETE FROM squads WHERE id = $sid AND user_id = $me AND project_id = $pid");
	if (!$conn->affected_rows) return array(false, 'That is not your squad.');
	@$conn->query("DELETE FROM squad_members WHERE squad_id = $sid");
	return array(true, 'Squad dispersed. Its soldiers are back in the barracks.');
}

/**
 * Drop one member, for a squad that lost a soldier and is otherwise fine.
 *
 * The alternative is dispersing and rebuilding three cards by hand because
 * one was sold, which is the friction this whole feature exists to remove.
 */
function dropshipSquadDrop($conn, $squad_id, $soldier_id) {
	if (!dropshipSquadsTable($conn)) return array(false, 'Saved squads are not set up yet.');
	$me  = (int)($_SESSION['userData']['dropship_user_id'] ?? 0);
	$pid = (int)($_SESSION['userData']['dropship_project_id'] ?? 0);
	$sid = (int)$squad_id; $mid = (int)$soldier_id;
	$res = @$conn->query("SELECT id FROM squads
	                       WHERE id = $sid AND user_id = $me AND project_id = $pid LIMIT 1");
	if (!$res || !$res->num_rows) return array(false, 'That is not your squad.');
	@$conn->query("DELETE FROM squad_members WHERE squad_id = $sid AND soldier_id = $mid");
	/* A squad with nobody left is not a squad. Leaving the empty husk would
	   put a row on the screen whose only honest action is Delete, so it goes
	   now rather than asking the player to tidy up after the game did. */
	$left = @$conn->query("SELECT COUNT(*) AS n FROM squad_members WHERE squad_id = $sid");
	if ($left && ($r = $left->fetch_assoc()) && (int)$r['n'] === 0) {
		@$conn->query("DELETE FROM squads WHERE id = $sid AND user_id = $me");
		return array(true, 'That was the last member, so the squad has gone too.');
	}
	return array(true, 'Removed from the squad.');
}
