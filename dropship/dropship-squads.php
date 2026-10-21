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

/**
 * Heaviest armour first, which is also the order the threshold chain reads.
 *
 * dropshipThreshold() takes the best tier worn as the floor and adds the
 * lower tiers as credits, so Heavy, Medium, Light, Base is not an arbitrary
 * reading order -- it is the order the squad's own number is built in, and a
 * player scanning a row sees the tier that is carrying it first. A member
 * with no armour the chain recognises sorts last rather than anywhere.
 */
function dropshipSquadArmorRank($armor) {
	$order = array('Heavy' => 0, 'Medium' => 1, 'Light' => 2, 'Base' => 3);
	$a = (string)$armor;
	return isset($order[$a]) ? $order[$a] : 4;
}

/**
 * Put a squad's members in that order.
 *
 * SORTED FOR DISPLAY, not stored. squad_members.slot keeps the order they
 * were saved in and is left alone: armour changes when a soldier is swapped,
 * and a stored order would then disagree with the card it is drawn on. slot
 * breaks a tie so two Heavies do not swap places between page loads.
 */
function dropshipSquadOrderMembers(&$members) {
	$i = 0;
	foreach ($members as $k => $m) { $members[$k]['_at'] = $i++; }
	usort($members, function ($a, $b) {
		$ra = dropshipSquadArmorRank(isset($a['row']['armor']) ? $a['row']['armor'] : '');
		$rb = dropshipSquadArmorRank(isset($b['row']['armor']) ? $b['row']['armor'] : '');
		if ($ra !== $rb) return $ra - $rb;
		return $a['_at'] - $b['_at'];
	});
}

/** "1 member" / "3 members", because "1 members" is how a page looks unfinished. */
function dropshipSquadPlural($n, $word) {
	$n = (int)$n;
	return $n . ' ' . $word . ($n === 1 ? '' : 's');
}

/**
 * The armour and gear a squad is carrying, in the shape dropshipThreshold()
 * reads.
 *
 * NO SUITS. A Mech Suit is a purchased item on the session, not a property of
 * these four soldiers, and the suits OVERRIDE armour in the threshold chain --
 * fold one in and every squad you own reports the same number, which makes
 * "order by most powerful" meaningless at exactly the moment a player has
 * spent money. So this is the squad's own armour, which is the thing that
 * differs between squads and the thing a player is choosing between.
 */
function dropshipSquadFlags($members) {
	$f = array('mech_suit' => false, 'exo_suit' => false, 'quantum_stealth' => false,
	           'ballistic_shield' => false, 'heavy' => false, 'medium' => false,
	           'light' => false, 'base' => false, 'extra_lives' => 0, 'roster' => array());
	foreach ($members as $m) {
		$row = isset($m['row']) ? $m['row'] : null;
		if (!is_array($row)) continue;
		switch (isset($row['armor']) ? $row['armor'] : '') {
			case 'Heavy':  $f['heavy']  = true; break;
			case 'Medium': $f['medium'] = true; break;
			case 'Light':  $f['light']  = true; break;
			case 'Base':   $f['base']   = true; break;
		}
		if (isset($row['gear']) && $row['gear'] === 'Medkit') $f['extra_lives']++;
		$f['roster'][] = array('armor' => (string)($row['armor'] ?? ''),
		                       'gear'  => (string)($row['gear']  ?? ''));
	}
	return $f;
}

/**
 * What a squad IS, instead of what somebody called it.
 *
 * NAMES WERE THE FIRST VERSION AND WERE THE WRONG ANSWER. A squad already has
 * an identity -- its armour spread and what that is worth per round -- and
 * asking a player to type one is a step between them and the thing they came
 * to do. Derived, never stored, for the usual reason: a stored label is a
 * second copy of a fact and drifts the moment a soldier is swapped out.
 *
 * THE SAME dropshipThreshold() THE RUN ROLLS AGAINST, so the figure on the
 * row and the figure the dice are compared to cannot disagree. 'pct' is the
 * squad's ARMOUR though, not the player's whole loadout -- see
 * dropshipSquadFlags() -- so the wording says "from armor" and never implies
 * it is the number for the next run.
 */
function dropshipSquadPower($members) {
	$f     = dropshipSquadFlags($members);
	$tiers = 0;
	foreach (array('heavy', 'medium', 'light', 'base') as $t) if ($f[$t]) $tiers++;
	$gear  = array();
	foreach ($f['roster'] as $r) if ($r['gear'] !== '' && $r['gear'] !== 'None') $gear[$r['gear']] = true;

	$pct   = function_exists('dropshipSurvivalPct') && function_exists('dropshipThreshold')
	       ? (int)dropshipSurvivalPct(dropshipThreshold($f)) : 0;
	/* A SUPER SOLDIER SQUAD is all four armour tiers AND all three gear
	   types -- the same test dropshipSquadAdvice() and loadAchievements()
	   apply, not a second opinion about what "super" means. */
	$super = ($tiers === 4 && count($gear) === 3);

	if ($super)              $label = dropshipSquadWord('Super Soldier') . ' Squad';
	elseif ($tiers === 4)    $label = 'Every armor tier';
	elseif ($tiers === 0)    $label = 'No armor';
	else                     $label = dropshipSquadPlural($tiers, 'armor tier');

	return array('pct' => $pct, 'super' => $super, 'tiers' => $tiers,
	             'gear' => count($gear), 'label' => $label, 'flags' => $f);
}

/** evaluateText() when it is loaded, the plain word when it is not. */
function dropshipSquadWord($term) {
	return function_exists('dropshipWord') ? dropshipWord($term)
	     : (function_exists('evaluateText') ? evaluateText($term) : $term);
}

/**
 * Strongest first.
 *
 * Armour decides the roll, so it decides the order. Gear breaks a tie because
 * two squads at 80 are not equal if one of them carries a Medkit, and the
 * member count breaks that, so the ordering is total and a list does not
 * reshuffle itself between page loads for no visible reason.
 */
function dropshipSquadSort(&$squads) {
	usort($squads, function ($a, $b) {
		$pa = $a['power']; $pb = $b['power'];
		if ($pa['pct']   !== $pb['pct'])   return $pb['pct']   - $pa['pct'];
		if ($pa['super'] !== $pb['super']) return $pb['super'] ? 1 : -1;
		if ($pa['gear']  !== $pb['gear'])  return $pb['gear']  - $pa['gear'];
		if (count($a['members']) !== count($b['members'])) {
			return count($b['members']) - count($a['members']);
		}
		return $a['id'] - $b['id'];
	});
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

	/* NO NAME COLUMN. A squad is identified by what it IS -- see
	   dropshipSquadPower() -- so there is nothing stored to select. The
	   ordering here is only a stable starting point; dropshipSquadSort()
	   puts them in strength order once the members are known, which cannot
	   be done in SQL because the armour lives on the soldiers. */
	$res = @$conn->query("SELECT id FROM squads
	                       WHERE user_id = $me AND project_id = $pid
	                       ORDER BY created_at ASC, id ASC");
	if (!$res) return array();
	$squads = array();
	while ($r = $res->fetch_assoc()) {
		$squads[(int)$r['id']] = array('id' => (int)$r['id'], 'members' => array());
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
		dropshipSquadOrderMembers($squads[$sid]['members']);
		$sq = $squads[$sid];
		$states = array();
		foreach ($sq['members'] as $m) $states[] = $m['state'];
		$squads[$sid]['ready'] = dropshipSquadReadiness($states, dropshipSquadRoom($conn));
		$squads[$sid]['power'] = dropshipSquadPower($sq['members']);
	}
	$out = array_values($squads);
	dropshipSquadSort($out);
	return $out;
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
	$res = @$conn->query("SELECT m.soldier_id, q.id
	                        FROM squad_members m
	                        INNER JOIN squads q ON q.id = m.squad_id
	                       WHERE q.user_id = $me AND q.project_id = $pid");
	/*
	 * A LIST PER SOLDIER, not one squad. A soldier can be in more than one
	 * saved squad -- withdraw one member of a deployed squad, put somebody
	 * else in their place and save, and three soldiers are now in two squads.
	 * That is allowed (see dropshipSquadShared()), but the map was
	 * $out[$id] = $squad_id, so the second squad silently overwrote the
	 * first and anything asking "which squad is this soldier in" got one
	 * arbitrary answer. Only array_keys() is used today, which is why
	 * nothing broke; it would have broken the first time somebody asked.
	 */
	$out = array();
	if ($res) while ($r = $res->fetch_assoc()) {
		$out[(int)$r['soldier_id']][] = (int)$r['id'];
	}
	return $out;
}

/**
 * Which soldiers sit in more than one of these squads.
 *
 * NOTHING HERE CAN CREATE ONE ANY MORE -- dropshipSquadSave() refuses a
 * soldier that is already spoken for, which is the owner's rule. This stays
 * as the net under it, for two reasons worth writing down.
 *
 * The first is history: a squad saved before that rule existed, or a row put
 * in by hand, is overlap this code did not make and still has to explain.
 * The second is that an unexplained overlap is the worst failure this screen
 * has -- two squads that each refuse to deploy while the other is out, with
 * no visible cause -- so the panel marks it rather than letting somebody
 * discover it from a refusal.
 *
 * dropship-squads-walk-harness.php asserts BOTH halves: that no order of
 * operations produces an overlap, and that one would be reported if it did.
 *
 * Takes the resolved squads and returns soldier_id => how many squads hold
 * it, counting only those held more than once.
 */
function dropshipSquadShared($squads) {
	$n = array();
	foreach ($squads as $sq) {
		foreach ($sq['members'] as $m) {
			$id = (int)$m['soldier_id'];
			$n[$id] = isset($n[$id]) ? $n[$id] + 1 : 1;
		}
	}
	foreach ($n as $id => $c) if ($c < 2) unset($n[$id]);
	return $n;
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
function dropshipSquadSave($conn) {
	if (!dropshipSquadsTable($conn)) return array(false, 'Saved squads are not set up yet.');
	$me  = (int)($_SESSION['userData']['dropship_user_id'] ?? 0);
	$pid = (int)($_SESSION['userData']['dropship_project_id'] ?? 0);
	if ($me <= 0 || $pid <= 0) return array(false, 'Not signed in.');

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
		if ($have === $want) return array(false, 'You have already saved this squad.');
	}

	/*
	 * A SOLDIER BELONGS TO ONE SQUAD. The owner's rule, and it closes the
	 * hole this feature actually had: withdraw one member of a deployed
	 * squad, field somebody else in the gap, save, and three soldiers were
	 * quietly in two squads at once. Neither squad could then be fielded
	 * while the other was, and nothing on either row said why.
	 *
	 * REFUSED BY NAME, not just refused. The way out is to disperse the
	 * squad those soldiers are already in, and a player cannot do that
	 * unless they are told which soldiers and therefore which squad.
	 *
	 * AFTER THE DUPLICATE TEST, and the order is load-bearing. Saving the
	 * exact same four is also a clash, and "you have already saved this" is
	 * the more useful of the two answers there.
	 *
	 * The first version tried to express that as `count($clash) !== count($ids)`
	 * -- fire only when SOME of the field is spoken for -- and the walk
	 * harness found the hole in eight steps: save {D,E}, withdraw D, save
	 * again. The field is {E}, every one of its members is spoken for, so the
	 * guard stood down, and {E} is not equal to {D,E} so the duplicate test
	 * let it through. E ended up in two squads. A subset is not a duplicate,
	 * and "all of them clash" is not "this is the same squad".
	 */
	$spoken = dropshipSquadMembership($conn, $me, $pid);
	$clash  = array();
	foreach ($ids as $id) if (isset($spoken[$id])) $clash[] = $id;
	if ($clash) {
		$names = array();
		$res = @$conn->query("SELECT name FROM soldiers WHERE id IN ("
		                     . implode(',', array_map('intval', $clash)) . ")");
		if ($res) while ($r = $res->fetch_assoc()) $names[] = (string)$r['name'];
		return array(false, dropshipSquadPlural(count($clash), 'soldier')
		           . ' on the field ' . (count($clash) === 1 ? 'is' : 'are')
		           . ' already in a saved squad'
		           . ($names ? ' (' . implode(', ', $names) . ')' : '')
		           . '. Disperse that squad first, or withdraw them.');
	}

	@$conn->query(sprintf("INSERT INTO squads (user_id, project_id, created_at)
	                       VALUES (%d, %d, NOW())", $me, $pid));
	$sid = (int)$conn->insert_id;
	if ($sid <= 0) return array(false, 'Could not save that squad.');

	$rows = array();
	foreach ($ids as $i => $id) $rows[] = sprintf('(%d,%d,%d)', $sid, $id, $i);
	@$conn->query('INSERT INTO squad_members (squad_id, soldier_id, slot) VALUES '
	              . implode(',', $rows));

	return array(true, 'Squad saved.');
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
	return array(true, $squad['power']['label'] . ' deployed.');
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

/* ---------- the screen ------------------------------------------------------ */

/**
 * The saved squads, above the loose barracks.
 *
 * MARKUP BESIDE THE RULES, the way dropshipOddsPanel() keeps its own. The
 * states this draws are read from one place and rendered in one place; the
 * version where the page worked out for itself what a member was is how the
 * screen and the roll come to disagree.
 *
 * COMPACT ROWS, NOT A SECOND GRID OF CARDS. The big card exists to help you
 * CHOOSE a soldier, which is what the barracks below is for. A saved squad is
 * a thing you already chose, so what it owes the player is a name, who is in
 * it, whether it can go, and one button.
 */
function dropshipSquadsPanel($conn) {
	if (!dropshipSquadsTable($conn)) return '';
	$squads = dropshipSquadsAll($conn);
	if (!$squads) return '';
	/* THE NET, not an expected state. dropshipSquadSave() refuses a soldier
	   that is already in a squad, so nothing this code does can produce one
	   -- but a row from before that rule, or one put in by hand, would
	   otherwise be two squads that each refuse while the other is out, with
	   nothing on either saying why. */
	$shared = dropshipSquadShared($squads);
	$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES); };

	/* The theme's own word for a soldier, so a reskin does not say "soldier"
	   in the middle of its own vocabulary. */
	$t = function ($v) { return function_exists('evaluateText') ? evaluateText($v) : $v; };

	ob_start();
	?>
<style>
/* Unscoped ids, like the game picker: these rules must not name an ancestor
   the element might not have. */
.dssq{margin:0 0 14px}
.dssq-one{border:1px solid var(--ds-line,#1b3346);border-radius:var(--ds-r,3px);
  background:#0a1929;margin-bottom:10px;overflow:hidden}
.dssq-one.ready{border-color:#00c8a0}
.dssq-head{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:9px 12px;
  border-bottom:1px solid var(--ds-line,#1b3346)}
.dssq-head b{font-size:13px;text-align:left}
.dssq-one.super .dssq-head b{color:#00c8a0}
.dssq-pct{flex:1 1 auto;text-align:left;font-size:12px;font-variant-numeric:tabular-nums;
  color:#e8eaed}
.dssq-pct em{font-style:normal;font-size:10px;letter-spacing:.06em;text-transform:uppercase;
  color:#7a9eb0;margin-left:3px}
.dssq-head form{display:inline}
.dssq-why{font-size:11px;color:#7a9eb0;flex:1 1 100%;text-align:left}
.dssq-one.ready .dssq-why{color:#00c8a0}
/* FIXED-WIDTH MEMBERS, NOT A FOUR-COLUMN GRID. A grid of 1fr columns makes
   each face a quarter of the page -- about 370px tall on a desktop -- which
   is a second barracks, not the compact row this is meant to be. A squad
   member needs to be recognised, not studied. Four of these plus the gaps
   come to ~400px, so the row still holds together on a phone. */
.dssq-men{display:flex;flex-wrap:wrap;gap:10px;padding:10px 12px}
.dssq-man{flex:0 0 92px;width:92px;text-align:left;font-size:11px;min-width:0}
.dssq-man img{display:block;width:100%;aspect-ratio:1/1;object-fit:cover;
  border-radius:var(--ds-r,3px);background:#07111d;max-width:100%}
.dssq-man .nm{display:block;font-weight:bold;margin-top:4px;overflow:hidden;
  text-overflow:ellipsis;white-space:nowrap}
.dssq-man .st{display:block;color:#7a9eb0;text-transform:uppercase;letter-spacing:.06em;
  font-size:9.5px;margin-top:1px}
.dssq-man.sold .st,.dssq-man.gone .st{color:#e8a0b0}
.dssq-man.fallen .st{color:#f5a623}
.dssq-man.ready .st{color:#00c8a0}
.dssq-man.sold img,.dssq-man.gone img,.dssq-man.fallen img{filter:grayscale(1);opacity:.45}
.dssq-man .sh{display:block;font-size:9px;line-height:1.25;margin-top:2px;color:#f5a623}
.dssq-man form{display:block;margin-top:4px}
.dssq-man .small-button{width:100%;min-height:0;padding:4px 6px;font-size:10px}
</style>
<div class="dssq">
<?php foreach ($squads as $sq):
	$can = !empty($sq['ready']['can']); ?>
	<div class="dssq-one<?php echo $can ? ' ready' : ''; ?><?php
	     echo !empty($sq['power']['super']) ? ' super' : ''; ?>">
		<div class="dssq-head">
			<?php /* WHAT IT IS, not what it was called. The percentage is the
			         squad's armour through the same dropshipThreshold() the run
			         rolls against, so the two cannot drift; it is labelled
			         "from armor" because a purchased suit overrides armour in
			         that chain and this figure deliberately leaves suits out. */ ?>
			<b><?php echo $h($sq['power']['label']); ?></b>
			<span class="dssq-pct"><?php echo (int)$sq['power']['pct']; ?>%
				<em>from armor</em></span>
			<?php if ($can): ?>
				<form action="dashboard.php#barracks" method="post">
					<input type="hidden" name="squad_deploy" value="<?php echo (int)$sq['id']; ?>">
					<input class="small-button" type="submit" value="Deploy squad">
				</form>
			<?php endif; ?>
			<?php /* Disperse always, including on a squad nothing else can be
			         done with -- it is the way out, so it is never the thing
			         that is missing. */ ?>
			<form action="dashboard.php#barracks" method="post">
				<input type="hidden" name="squad_disperse" value="<?php echo (int)$sq['id']; ?>">
				<input class="small-button secondary" type="submit" value="Disperse">
			</form>
			<?php if (!$can && $sq['ready']['why'] !== ''): ?>
				<span class="dssq-why"><?php echo $h($sq['ready']['why']); ?></span>
			<?php elseif ($can): ?>
				<span class="dssq-why">Ready.</span>
			<?php endif; ?>
		</div>
		<div class="dssq-men">
		<?php foreach ($sq['members'] as $m):
			$row   = $m['row'];
			$state = $m['state'];
			$label = array('ready' => 'Ready', 'fielded' => 'On the field',
			               'fallen' => 'Fought this game', 'sold' => 'Not yours',
			               'gone' => 'Gone');
			$name  = $row ? (string)$row['name'] : 'Unknown soldier';
			?>
			<?php $isShared = isset($shared[(int)$m['soldier_id']]); ?>
			<span class="dssq-man <?php echo $h($state); ?><?php echo $isShared ? ' shared' : ''; ?>">
				<?php if ($row && !empty($row['asset_name'])): ?>
					<img loading="lazy" alt=""
					     src="<?php echo $h(dropshipSquadFace($conn, $row)); ?>"
					     onerror="this.style.visibility='hidden'">
				<?php endif; ?>
				<span class="nm" title="<?php echo $h($name); ?>"><?php echo $h($name); ?></span>
				<span class="st"><?php echo $h($label[$state] ?? $state); ?></span>
				<?php if ($isShared): ?><span class="sh"
					title="This soldier is in more than one squad, so only one of those squads can be on the field at a time."
					>Also in another squad</span><?php endif; ?>
				<?php /* A member you cannot field is the only one worth
				         removing on its own -- offering Remove on a healthy
				         soldier invites somebody to dismantle a squad one
				         card at a time when Disperse is right there. */ ?>
				<?php if ($state === 'sold' || $state === 'gone'): ?>
					<form action="dashboard.php#barracks" method="post">
						<input type="hidden" name="squad_id" value="<?php echo (int)$sq['id']; ?>">
						<input type="hidden" name="squad_drop" value="<?php echo (int)$m['soldier_id']; ?>">
						<input class="small-button secondary" type="submit" value="Remove">
					</form>
				<?php endif; ?>
			</span>
		<?php endforeach; ?>
		</div>
	</div>
<?php endforeach; ?>
</div>
	<?php
	return ob_get_clean();
}

/**
 * The picture for one squad member.
 *
 * The same three-way the barracks card uses: Drop Ship's own art is local,
 * every other theme is Skulliance's cached copy, and anything not cached
 * falls back to the IPFS optimiser. Looked up per member rather than in one
 * batch because a squad is four rows, not a roster.
 */
function dropshipSquadFace($conn, $row) {
	$pid = (int)($_SESSION['userData']['dropship_project_id'] ?? 0);
	if ($pid === 1) return 'images/nfts/' . $row['asset_name'] . '.jpg';
	if (function_exists('dropshipLocalNftImages') && function_exists('getProjectPolicyId')) {
		$local = dropshipLocalNftImages(array($row['asset_name']), getProjectPolicyId($conn));
		if (!empty($local[$row['asset_name']])) return $local[$row['asset_name']];
	}
	return 'https://image-optimizer.jpgstoreapis.com/' . $row['ipfs'];
}
