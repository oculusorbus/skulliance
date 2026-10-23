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
		/*
		 * ZERO GETS ITS OWN SENTENCE. The arithmetic version read "There is
		 * only room for 0 more on the field", which is the shape of a string
		 * built by a loop rather than written by a person -- the number is
		 * the whole point of the sentence and zero is the one value that
		 * makes it nonsense. "Full" is the word somebody would actually use.
		 */
		if ((int)$room <= 0) {
			return array('can' => false, 'counts' => $n, 'fix' => 'withdraw',
			             'why' => 'Your squad is already full. Withdraw somebody first.');
		}
		return array('can' => false, 'counts' => $n, 'fix' => 'withdraw',
		             'why' => 'Your squad has room for only ' . (int)$room . ' more, and this '
		                 . 'needs ' . dropshipSquadPlural($n['ready'], 'slot')
		                 . '. Withdraw somebody first.');
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

/**
 * The order the FIELD shows what is standing on it.
 *
 * TWO GROUPS, and the owner's rule: what you added yourself comes first, in
 * the order you added it, and anything that arrived as a saved squad follows
 * in armour order. Adding soldiers one at a time is a sequence the player
 * performed and watching it resort itself is disorienting; a squad arrived
 * all at once and has no order of its own, so it takes the one its own card
 * uses -- Heavy, Medium, Light, Base.
 *
 * WHICH GROUP IS DERIVED, NOT STORED. A soldier in a saved squad has no
 * individual Deploy button, so if it is on the field it got there through
 * Deploy squad. There is nothing to record and nothing that can disagree.
 *
 * $order is soldier_id => when it was deployed, and $inSquad is the
 * membership map. Both come from the caller so this stays drivable.
 */
function dropshipFieldOrder($rows, $order, $inSquad) {
	$i = 0;
	foreach ($rows as $k => $r) $rows[$k]['_seq'] = $i++;
	usort($rows, function ($a, $b) use ($order, $inSquad) {
		$ia = (int)$a['soldier_id']; $ib = (int)$b['soldier_id'];
		$sa = isset($inSquad[$ia]) ? 1 : 0;
		$sb = isset($inSquad[$ib]) ? 1 : 0;
		if ($sa !== $sb) return $sa - $sb;                 /* yours first */
		if ($sa === 1) {                                    /* squad: armour */
			$ra = dropshipSquadArmorRank(isset($a['armor']) ? $a['armor'] : '');
			$rb = dropshipSquadArmorRank(isset($b['armor']) ? $b['armor'] : '');
			if ($ra !== $rb) return $ra - $rb;
			return $a['_seq'] - $b['_seq'];
		}
		/* Yours, in the order you added them. A soldier deployed before this
		   column existed has no time at all and sorts to the front rather
		   than to a random place -- it was there first, which is true. */
		$ta = isset($order[$ia]) && $order[$ia] !== null ? (string)$order[$ia] : '';
		$tb = isset($order[$ib]) && $order[$ib] !== null ? (string)$order[$ib] : '';
		if ($ta !== $tb) return strcmp($ta, $tb);
		return $a['_seq'] - $b['_seq'];
	});
	foreach ($rows as $k => $r) unset($rows[$k]['_seq']);
	return $rows;
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
	/* s.ipfs IS NOT OPTIONAL, and its absence was invisible for months.
	   dropshipSquadFace() falls through to it only when the local image
	   cache misses, and for every NFT theme the cache hits -- so the
	   undefined key was never reached until assembled Fighters, which are
	   not NFTs and have no cached copy. display_errors is on here, so the
	   notice printed INSIDE the src attribute. */
	$res = @$conn->query("SELECT m.squad_id, m.soldier_id, m.slot,
	                             s.id, s.user_id, s.name, s.asset_name, s.rank,
	                             s.armor, s.gear, s.level, s.active, s.deceased,
	                             s.ipfs
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
		           . ' in your squad ' . (count($clash) === 1 ? 'is' : 'are')
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

	/*
	 * PICKING A DIFFERENT SQUAD SWAPS, IT DOES NOT REFUSE.
	 *
	 * Deploying while something is already fielded used to answer "Your
	 * squad is already full. Withdraw somebody first." -- an error telling
	 * the player to do the obvious thing themselves. Nobody presses Deploy
	 * squad on a second squad meaning "but only if the field happens to be
	 * empty"; they mean field this one instead.
	 *
	 * ONLY WHEN ROOM IS THE BLOCKER. dropshipSquadReadiness() already tags
	 * that case 'fix' => 'withdraw', so every other refusal stands: a squad
	 * whose members fought this game, or died, or were sold, still says so
	 * rather than clearing the field for a deployment that cannot happen.
	 *
	 * The field is cleared and the squad RE-READ, because readiness is
	 * computed against the room available at the time it was loaded -- the
	 * stale copy still believes the field is full.
	 */
	$swapped = 0;
	if (empty($squad['ready']['can']) && ($squad['ready']['fix'] ?? '') === 'withdraw') {
		$mine = array();
		foreach ($squad['members'] as $m) $mine[(int)$m['soldier_id']] = true;
		$holding = false;
		$res = @$conn->query("SELECT id FROM soldiers
		                       WHERE user_id = $me AND project_id = $pid AND active = 1");
		if ($res) while ($r = $res->fetch_assoc()) if (!isset($mine[(int)$r['id']])) $holding = true;
		/* Nothing to gain from clearing a field made of THIS squad already:
		   that is not a swap, and withdrawing would undo what the player
		   can see is already done. */
		if ($holding) {
			list($ok_w, ) = dropshipWithdrawAll($conn);
			if ($ok_w) {
				$swapped = 1;
				$squad = null;
				foreach (dropshipSquadsAll($conn, $me, $pid) as $sq) if ($sq['id'] === $sid) $squad = $sq;
				if (!$squad) return array(false, 'That is not your squad.');
			}
		}
	}
	if (empty($squad['ready']['can'])) {
		/* 'none' is the no-op case -- the squad is already on the field.
		   Nothing went wrong, nothing changed, and the card under the
		   button already says it, so there is no banner to raise. */
		$quiet = ($squad['ready']['fix'] ?? '') === 'none';
		return array(false, $quiet ? '' : $squad['ready']['why']);
	}

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
	/* STAMPED TOO, and all with the same NOW(): they arrived together, so
	   they tie, and dropshipFieldOrder() falls through to armour order for
	   them -- which is the order their own squad card shows. The retry is
	   the same bargain deploySoldier() makes: before the ALTER has run,
	   losing the ordering beats losing the deployment. */
	if (@$conn->query("UPDATE soldiers SET active = 1, active_at = NOW()
	                    WHERE id IN ($list) AND user_id = $me AND project_id = $pid AND deceased = 0") !== true) {
		@$conn->query("UPDATE soldiers SET active = 1
		                WHERE id IN ($list) AND user_id = $me AND project_id = $pid AND deceased = 0");
	}
	return array(true, $squad['power']['label'] . ($swapped ? ' swapped in.' : ' deployed.'));
}

/**
 * Take everybody off the field at once.
 *
 * FOR BUILDING IN BATCHES, which is how squads actually get made: set one up,
 * save it, clear it, set up the next. Doing that four soldiers at a time
 * through four individual Withdraws is sixteen presses to save four squads,
 * and the point of the feature was to remove presses.
 *
 * It lives here rather than beside deploySoldier() because it belongs to the
 * squad-building flow and because the writes in this file are all scoped to
 * the signed-in player in the statement itself -- which is exactly the guard
 * deploySoldier() was missing until this week.
 *
 * NOT A DISPERSE. Nothing about saved squads changes; this only empties the
 * deployed squad. A player clearing it to build the next one must not lose
 * the one they just saved.
 *
 * VOCABULARY. The game has always called the deployed set "your squad" --
 * deploySoldier() says "Your squad is full." -- so every message here says
 * that too, and a stored grouping is a SAVED squad. "The field" was my word
 * and nobody else's, and it put a second name on something already named.
 */
function dropshipWithdrawAll($conn) {
	$me  = (int)($_SESSION['userData']['dropship_user_id'] ?? 0);
	$pid = (int)($_SESSION['userData']['dropship_project_id'] ?? 0);
	if ($me <= 0 || $pid <= 0) return array(false, 'Not signed in.');
	/* active_at cleared with it, so the next soldier added starts the order
	   afresh instead of slotting in behind a timestamp from last time. The
	   retry is for the window before the ALTER has run. */
	if (@$conn->query("UPDATE soldiers SET active = 0, active_at = NULL
	                    WHERE user_id = $me AND project_id = $pid AND active = 1") !== true) {
		@$conn->query("UPDATE soldiers SET active = 0
		                WHERE user_id = $me AND project_id = $pid AND active = 1");
	}
	$n = (int)$conn->affected_rows;
	if ($n <= 0) return array(false, 'Your squad is already empty.');
	return array(true, dropshipSquadPlural($n, 'soldier') . ' withdrawn.');
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
/**
 * WHAT THIS SQUAD IS SHORT OF, and what would fix it.
 *
 * The collection-wide advice answered "what should I buy next", which is
 * one sentence for a barracks of fourteen squads and says nothing about
 * the one a player is looking at. A squad either is a Super Soldier Squad
 * or is missing something specific, and that is the question being asked
 * while scrolling the list.
 *
 * MISSING MEANS MISSING FROM THIS SQUAD, not from the collection: four
 * armour tiers between four soldiers, and all three items between them.
 * The odds only read armour -- see dropshipThreshold() -- so a squad short
 * only an item still rolls the same, and the wording says so rather than
 * implying a lost percentage.
 *
 * Returns array(summary, body) of already-escaped markup, or null when the
 * squad is complete and there is nothing to say.
 */
function dropshipSquadGapAdvice($conn, $members) {
	$roster = array();
	foreach ($members as $m) {
		$row = isset($m['row']) ? $m['row'] : null;
		if (!is_array($row)) continue;
		$roster[] = array('armor' => (string)($row['armor'] ?? ''),
		                  'gear'  => (string)($row['gear']  ?? ''));
	}
	return dropshipGapAdvice($conn, $roster);
}

/**
 * The same question asked of a plain roster, which is what the odds pane
 * holds for the squad currently on the field.
 */
function dropshipGapAdvice($conn, $roster) {
	$tiers = function_exists('dropshipArmorTiers')
		? dropshipArmorTiers() : array('Heavy', 'Medium', 'Light', 'Base');
	$items = function_exists('dropshipPlanItems')
		? dropshipPlanItems() : array('Medkit', 'Melee', 'Demolition');

	if (!$roster) return null;
	$has_t = array(); $has_i = array();
	foreach ($roster as $row) {
		$has_t[(string)($row['armor'] ?? '')] = true;
		$has_i[(string)($row['gear']  ?? '')] = true;
	}
	$miss_t = array(); foreach ($tiers as $t) if (empty($has_t[$t])) $miss_t[] = $t;
	$miss_i = array(); foreach ($items as $i) if (empty($has_i[$i])) $miss_i[] = $i;
	if (!$miss_t && !$miss_i) return null;

	$w  = function ($v) { return function_exists('dropshipWord') ? dropshipWord($v) : $v; };
	/*
	 * TWO JOINERS, BECAUSE A NEGATIVE TAKES "OR".
	 *
	 * "this squad has no Medkit and Melee" reads as though it is short of
	 * the pair and might hold one of them; the sentence means it holds
	 * neither. "Missing Medkit and Melee" is the positive form and keeps
	 * "and". English, not a tidy-up: one of these is wrong out loud.
	 */
	$join = function ($a, $word) use ($w) {
		$a = array_map($w, $a);
		if (count($a) <= 1) return (string)($a[0] ?? '');
		$last = array_pop($a);
		return implode(', ', $a) . ' ' . $word . ' ' . $last;
	};
	$jw  = function ($a) use ($join) { return $join($a, 'and'); };
	$jor = function ($a) use ($join) { return $join($a, 'or'); };

	/*
	 * ONE LIST, JOINED ONCE. This built two phrases that each already
	 * contained "and" and then joined THOSE with "and", so a squad short a
	 * tier and two items read "Missing Base armor and Medkit and Melee".
	 * The armour tiers stay grouped under one "armor" -- "Light and Base
	 * armor" rather than "Light armor, Base armor" -- and then every piece
	 * goes into a single list.
	 */
	$bits = array();
	if ($miss_t) $bits[] = '<b>' . $jw($miss_t) . '</b> armor';
	foreach ($miss_i as $i) $bits[] = '<b>' . $w($i) . '</b>';
	$last = array_pop($bits);
	$summary = 'Missing ' . ($bits ? implode(', ', $bits) . ' and ' : '') . $last;

	$body = '';
	/* ARMOUR IS THE ONLY HALF THAT MOVES THE ODDS. Saying a squad is
	   "missing" an item without that caveat reads as a lost percentage. */
	if ($miss_t) {
		$body .= 'Armor sets the odds, so another tier here would raise this squad. ';
	} else {
		$body .= 'The odds are already the best this squad can roll -- armor is all they read. ';
	}
	if ($miss_i) {
		$body .= 'One of each item fires per squad, so this squad has no '
		       . $jor($miss_i) . '. ';
	}

	/*
	 * EVERY NFT THE SQUAD NEEDS, NOT THE FIRST ONE.
	 *
	 * This named a single purchase however many gaps there were, so a
	 * squad short a tier AND two items was told to buy one Medkit soldier
	 * -- which closes two of the three gaps and leaves the third
	 * unmentioned. Advice that stops halfway reads as generic, because the
	 * one thing it did not do was account for the squad in front of it.
	 *
	 * ONE NFT CARRIES ONE ITEM AND WEARS ONE TIER, so a purchase can close
	 * at most one of each: the number needed is the larger of the two
	 * shortfalls, and the pairs are built by taking a missing tier and a
	 * missing item together wherever both remain.
	 *
	 * THERE IS NO FIFTH SLOT. Once all four tiers are present, a purchase
	 * is a same-tier replacement for whoever is carrying nothing useful --
	 * a None, or a duplicate of an item already covered -- so the odds hold
	 * and the item lands. Those are the tiers asked for when the tiers
	 * themselves are fine.
	 *
	 * IT NEVER TELLS THE PLAYER TO SWAP ANYBODY. Nobody can: squads are
	 * generated and there is no manual editing left. They buy, and
	 * dropshipSquadsAutoSync() rebuilds around it on the next load.
	 */
	$wasted = array();
	$covered = array();
	foreach ($roster as $row) {
		$g = (string)($row['gear'] ?? '');
		if (in_array($g, $items, true) && !isset($covered[$g])) { $covered[$g] = true; continue; }
		$wasted[] = (string)($row['armor'] ?? '');
	}

	$want = array();
	$n = max(count($miss_t), count($miss_i));
	for ($k = 0; $k < $n; $k++) {
		if (isset($miss_t[$k])) {
			$armor = $miss_t[$k];
		} else {
			/* No tier to fix, so replace a wasted slot in its own tier.
			   Falls back to the squad's own first tier rather than
			   inventing one if every member is already pulling weight. */
			$wi = $k - count($miss_t);
			$armor = isset($wasted[$wi]) ? $wasted[$wi]
			       : (isset($roster[0]['armor']) ? (string)$roster[0]['armor'] : $tiers[0]);
		}
		$want[] = array('armor' => $armor, 'gear' => isset($miss_i[$k]) ? $miss_i[$k] : 'None');
	}

	if ($want && function_exists('dropshipShopList')) {
		/* Same sentence shape as the collection advice above it -- see
		   dropshipShopList(), which both callers share so the two tiers
		   cannot phrase the same thing two ways. */
		$body .= dropshipShopList($conn, $want);
	}

	$need_names = array();
	foreach ($miss_t as $t) $need_names[] = $t . ' Armor';
	foreach ($miss_i as $i) $need_names[] = $i;
	$buy = array();
	foreach ($need_names as $nm) $buy[] = '<b>' . $w($nm) . '</b>';
	if ($buy) {
		$last = array_pop($buy);
		$body .= ' Or buy ' . ($buy ? implode(', ', $buy) . ' and ' : '') . $last
		       . ' in the ' . $w('Armory') . ', which lasts one run.';
	}

	/*
	 * AND WHETHER THEY CAN ACTUALLY AFFORD IT.
	 *
	 * Naming a shelf of items to somebody with eleven SCRIP is the same
	 * failure as naming one NFT for three gaps: true, and not about them.
	 * The balance and the prices are both already here -- checkBalance()
	 * and items.cost -- so the sentence can say which of the two answers
	 * applies: go and buy it, or go and earn it.
	 *
	 * EARNING IS A DEEPER RUN, not a vaguer "play more". logBalance() adds
	 * the run's SCORE to the balance, so the thing that raises it is
	 * getting further, which is the thing the rest of this advice is
	 * about. The loop closes.
	 *
	 * THE QUERY USES CANONICAL NAMES. items.name stores 'Heavy Armor'
	 * whatever the theme calls it -- the renaming happens in
	 * evaluateText() on the way to the screen -- so the lookup must not
	 * use the themed words, and the sentence must.
	 */
	if ($need_names && is_object($conn) && function_exists('checkBalance')) {
		$esc = array();
		foreach ($need_names as $nm) $esc[] = "'" . $conn->real_escape_string($nm) . "'";
		$res = @$conn->query('SELECT name, cost FROM items WHERE name IN (' . implode(',', $esc) . ')');
		$cost = array();
		if ($res) while ($r = $res->fetch_assoc()) $cost[(string)$r['name']] = (float)$r['cost'];
		if ($cost) {
			$bal   = (float)str_replace(',', '', (string)checkBalance($conn));
			$total = 0; $cheapest = null;
			foreach ($need_names as $nm) {
				if (!isset($cost[$nm])) continue;
				$total += $cost[$nm];
				if ($cheapest === null || $cost[$nm] < $cost[$cheapest]) $cheapest = $nm;
			}
			/*
			 * CUMULATIVE, NOT ITEM BY ITEM. This tested each price against
			 * the whole balance on its own, so 200 SCRIP was "enough for
			 * Base Armor and Medkit" when those are 100 and 150 -- each
			 * affordable alone, 250 together. Telling somebody they can
			 * afford a basket they cannot is worse than saying nothing.
			 *
			 * Cheapest first, because that is the most things the balance
			 * can actually cover.
			 */
			$order = array();
			foreach ($need_names as $nm) if (isset($cost[$nm])) $order[$nm] = $cost[$nm];
			asort($order);
			$afford = array(); $spent = 0;
			foreach ($order as $nm => $c) {
				if ($spent + $c > $bal) break;
				$spent += $c;
				$afford[] = $nm;
			}
			$scrip = $w('SCRIP');
			$n_bal = '<b>' . number_format($bal) . '</b> ' . $scrip;
			if ($total > 0 && $bal >= $total) {
				$body .= ' You have ' . $n_bal . ', enough for all of it.';
			} elseif ($afford) {
				$names = array();
				foreach ($afford as $nm) $names[] = '<b>' . $w($nm) . '</b>';
				$lastn = array_pop($names);
				$body .= ' You have ' . $n_bal . ', enough for '
				       . ($names ? implode(', ', $names) . ' and ' : '') . $lastn . '.';
			} elseif ($cheapest !== null) {
				$body .= ' You have ' . $n_bal . ' and the cheapest of those is <b>'
				       . number_format($cost[$cheapest]) . '</b>. A deeper run earns more: '
				       . 'your score is added to your balance.';
			}
		}
	}

	return array($summary, trim($body));
}

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
/* .msg HAD NO RULES ANYWHERE -- not here, not in flexbox.css -- so it
   rendered as bare text inheriting the global centring, which is why a
   one-line result read as a stray caption floating over the panel. It is
   the result of something the player just pressed, so it belongs with the
   panel and reads left like everything else in it. */
.msg{border:1px solid var(--ds-line,#1b3346);border-radius:var(--ds-r,3px);
  background:var(--ds-ink,#07111d);padding:8px 12px;margin:0 0 12px;
  font-size:12px;text-align:left;color:#9fb6c4}
.msg.good{border-color:rgba(0,200,160,.45);color:#00c8a0}
.msg.bad{border-color:rgba(232,160,176,.45);color:#e8a0b0}
.dssq{margin:0 0 14px}
/* The collection advice, shown here only when nothing is fielded -- the
   odds pane carries it the rest of the time and two copies on one screen
   is worse than none. Same ink as a squad card so it reads as part of the
   panel rather than a notice bolted above it. */
.dssq-advice{flex:1 1 100%;border:1px solid var(--ds-line,#1b3346);
  border-radius:var(--ds-r,3px);background:var(--ds-ink,#07111d);margin:6px 0 0}
/* COLLAPSED BY DEFAULT, because the headline is the glanceable half and
   the shopping advice is two lines nobody needs on every load. <details>
   rather than a script: it works with the page's JS off, it is keyboard
   and screen-reader navigable for free, and this panel already had one
   control too many. */
.dssq-advice summary{list-style:none;cursor:pointer;padding:7px 10px;
  font-size:11px;font-weight:normal;color:#7a9eb0;display:flex;align-items:center;gap:8px;
  text-align:left}
.dssq-advice summary::-webkit-details-marker{display:none}
/* The caret is drawn rather than a glyph, so it cannot fall back to a box
   on a device without the character. */
.dssq-advice summary::after{content:'';width:6px;height:6px;margin-left:auto;
  border-right:1.5px solid #7a9eb0;border-bottom:1.5px solid #7a9eb0;
  transform:rotate(45deg);transition:transform .15s}
.dssq-advice[open] summary::after{transform:rotate(-135deg)}
.dssq-advice summary:hover{color:#e8eaed}
/* TEXT-ALIGN RESTATED, NOT INHERITED. flexbox.css centres text at several
   levels and this block sits inside .dssq-head, which is a centred flex
   row -- so the paragraph came out centre-aligned, which reads as a
   banner rather than a note. The same trap the squad captions hit. */
.dssq-advice .body{padding:0 10px 9px;font-size:11px;color:#7a9eb0;line-height:1.45;
  text-align:left}
/* THE COLLECTION-WIDE ONE SITS ABOVE THE LIST, not inside a card, so it
   carries the weight the per-card notes deliberately do not: it is the
   thing a player reads before deciding anything. */
.dssq-advice.top{margin:0 0 12px}
.dssq-advice.top summary{padding:10px 12px;font-size:13px;font-weight:bold;color:#00c8a0}
.dssq-advice.top summary:hover{color:#e8eaed}
.dssq-advice.top .body{padding:0 12px 11px;text-align:left}
@media (prefers-reduced-motion:reduce){.dssq-advice summary::after{transition:none}}
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
/*
 * FOUR COLUMNS ACROSS THE PANEL, which is the opposite of what this said.
 *
 * It was a fixed 92px per member, argued for on the grounds that full-width
 * faces would be "a second barracks, not the compact row this is meant to
 * be". That was true while the barracks existed. It does not now: the loose
 * grid is gone, every soldier lives in a squad, and this panel is the only
 * place a player ever sees the art they own. Shrinking it to a thumbnail
 * because something else used to show it properly is the wrong trade.
 *
 * CONTAIN, NOT COVER. At 92px a crop was unnoticeable; at a quarter of the
 * panel it takes the top off a portrait. The same call the game picker
 * already made -- the box is the tile's own ink, so the letterboxed edge
 * reads as the card rather than as a grey slab.
 *
 * TWO COLUMNS ON A PHONE. Four across a 360px screen is 80px a face, which
 * is smaller than what this replaced.
 */
.dssq-men{display:grid;grid-template-columns:repeat(4,minmax(0,1fr));gap:10px;padding:10px 12px}
@media (max-width:560px){.dssq-men{grid-template-columns:repeat(2,minmax(0,1fr))}}
.dssq-man{text-align:left;font-size:11px;min-width:0}
.dssq-man img{display:block;width:100%;aspect-ratio:1/1;object-fit:contain;
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
<?php
/*
 * TWO TIERS OF ADVICE, AND THEY ANSWER DIFFERENT QUESTIONS.
 *
 * This one is the collection: how do I get MORE squads. It names the one
 * soldier that would add another, or what is standing in the way when no
 * single soldier would. Each card below answers the other question --
 * what is wrong with THIS squad -- and the two are deliberately not the
 * same sentence: fixing a squad's missing Medkit does not add a squad,
 * and buying the soldier that adds one does not fix anybody's gap.
 *
 * SHOWN WHETHER OR NOT ANYTHING IS DEPLOYED. It was gated on an empty
 * field while the odds pane carried a copy; the odds pane now answers for
 * the squad ON the field instead, so there is nothing to collide with and
 * no reason to hide the one piece of advice a player reads before
 * deciding anything.
 */
$ds_adv = function_exists('dropshipSquadAdvice')
	? dropshipSquadAdvice(array('roster' => array()), $conn) : null;
if ($ds_adv !== null): ?>
	<?php /* Already escaped by dropshipSquadAdvice(). */ ?>
	<details class="dssq-advice top">
		<summary><?php echo $ds_adv[0]; ?></summary>
		<div class="body"><?php echo $ds_adv[1]; ?></div>
	</details>
<?php endif; ?>
<div class="dssq">
<?php foreach ($squads as $sq):
	$can = !empty($sq['ready']['can']);
	/*
	 * A FULL FIELD IS NOT A REASON TO HIDE THE BUTTON ANY MORE.
	 *
	 * Deploying a second squad swaps it in rather than refusing, so
	 * withholding the control is the one thing that makes the swap
	 * unreachable: once anything is fielded every OTHER squad loses its
	 * button and the player is back to clearing the field by hand, which
	 * is exactly what the swap removed.
	 *
	 * ONLY FOR ROOM. dropshipSquadReadiness() tags that case
	 * 'fix' => 'withdraw'; a squad whose members fought, died or were sold
	 * still has no button, because pressing it could not work.
	 */
	$swaps = !$can && ($sq['ready']['fix'] ?? '') === 'withdraw';
	$offer = $can || $swaps; ?>
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
			<?php /* LANDS ON THE SQUAD, NOT THE BARRACKS. This posted to
			         #barracks like every other form here, and the barracks
			         anchor sits BELOW the squad panel in the document -- so
			         deploying scrolled the player straight past the thing
			         they had just done. On a phone that is the whole screen:
			         you press Deploy squad and nothing appears to happen,
			         because the squad you fielded and the odds for it are
			         above the fold you were returned to.

			         #squad is the heading of the panel that only exists once
			         something is deployed, so it is always there on the way
			         back from this particular form. Withdraw all keeps
			         #barracks: that panel is gone by the time it lands. */ ?>
			<?php if ($offer): ?>
				<form action="dashboard.php#squad" method="post">
					<input type="hidden" name="squad_deploy" value="<?php echo (int)$sq['id']; ?>">
					<?php /* SAY WHICH IT IS. "Deploy squad" on a squad that
					         will first clear the field reads as if it adds
					         to what is already out there, and the field
					         holds four. */ ?>
					<input class="small-button<?php echo $swaps ? ' secondary' : ''; ?>"
					       type="submit" value="<?php echo $swaps ? 'Swap in' : 'Deploy squad'; ?>">
				</form>
			<?php endif; ?>
			<?php /* NO DISPERSE. Squads are generated from what the player
			         owns, so taking one apart would last until the next time
			         the roster changed and the engine put it back. The
			         soldiers in it are not trapped: Withdraw all clears the
			         field, and anyone not in a complete squad is in one of
			         the lower-tier squads below it. */ ?>
			<?php /* THE REASON HAS TO AGREE WITH THE BUTTON. "Your squad is
			         already full. Withdraw somebody first." sat next to a
			         control that does exactly that for you, which reads as
			         the game arguing with itself. A squad that can swap says
			         what swapping costs instead. */ ?>
			<?php
			/*
			 * WHAT IS WRONG WITH THIS SQUAD, one click away.
			 *
			 * Per squad rather than per collection: "buy a Base soldier"
			 * is one sentence for a barracks of fourteen and says nothing
			 * about the card being looked at. Collapsed, because a
			 * complete squad has nothing to say and an incomplete one is
			 * still only worth reading when you reach it.
			 */
			$gap = function_exists('dropshipSquadGapAdvice')
				? dropshipSquadGapAdvice($conn, $sq['members']) : null;
			?>
			<?php if ($swaps): ?>
				<span class="dssq-why">Ready. Swapping in withdraws your current squad.</span>
			<?php elseif (!$can && $sq['ready']['why'] !== ''): ?>
				<span class="dssq-why"><?php echo $h($sq['ready']['why']); ?></span>
			<?php elseif ($can): ?>
				<span class="dssq-why">Ready.</span>
			<?php endif; ?>
			<?php if ($gap !== null): ?>
				<details class="dssq-advice">
					<summary><?php echo $gap[0]; ?></summary>
					<div class="body"><?php echo $gap[1]; ?></div>
				</details>
			<?php endif; ?>
		</div>
		<div class="dssq-men">
		<?php foreach ($sq['members'] as $m):
			$row   = $m['row'];
			$state = $m['state'];
			$label = array('ready' => 'Ready', 'fielded' => 'Deployed',
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
					title="This soldier is in more than one saved squad, so only one of them can be deployed at a time."
					>Also in another squad</span><?php endif; ?>
				<?php /* NO REMOVE EITHER. A sold or vanished member used to
				         need clearing out by hand; the engine now notices the
				         same thing -- the owned set and the assigned set stop
				         agreeing -- and rebuilds every squad on the next load.
				         By the time a player could press this, it has already
				         happened. */ ?>
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
	/* No jpg.store fallback: that host is gone and answers 403. An
	   unavailable helper means no picture, which the card handles.
	   GUARDED ON THE KEY, not just the function: this runs inside an
	   src="" attribute with display_errors on, so a row that arrives
	   without the column renders its own warning as the picture. The
	   caller's SELECT is the fix; this is the thing that makes the next
	   one a missing image instead of a page full of file paths. */
	if (!function_exists('dropshipSoldierImage') || !isset($row['ipfs'])) return '';
	return dropshipSoldierImage($row['ipfs']);
}

/**
 * Every soldier the player owns in this game, as the planner needs them.
 *
 * ACTIVE AND DECEASED ARE DELIBERATELY IGNORED. A squad is a grouping, not
 * a deployment: fielding four soldiers does not change what the player
 * owns, and a fallen soldier is coming back. Reading either here would
 * reshuffle somebody's squads every time they pressed Deploy.
 *
 * dropshipGruntLoadout() is applied on the way out so the plan is built
 * from the kit the run will actually field -- the four Grunts' loaner gear
 * and the pinned NFT overrides -- rather than from the stored columns,
 * which for a Grunt are still None/None.
 */
function dropshipSquadsRoster($conn, $me, $pid) {
	$me = (int)$me; $pid = (int)$pid;
	if ($me <= 0 || $pid <= 0) return array();
	$res = @$conn->query("SELECT id, asset_name, name, rank, armor, gear
	                        FROM soldiers WHERE user_id = $me AND project_id = $pid");
	if (!$res) return array();
	$out = array();
	while ($r = $res->fetch_assoc()) {
		$r = dropshipGruntLoadout($r);
		$r['id'] = (int)$r['id'];
		$out[] = $r;
	}
	return $out;
}

/**
 * KEEP THE PLAYER'S SQUADS CORRECT WITHOUT THEM EVER MANAGING THEM.
 *
 * Squads are generated, not saved. The player opens the game and their
 * collection is already arranged into the best squads it can make; they
 * pick one and deploy it. There is nothing to assemble, nothing to name,
 * nothing to disperse.
 *
 * REBUILT ON OWNERSHIP, AND ON NOTHING ELSE. Buying or selling an NFT
 * changes what can be built; deploying, dying, resurrecting and
 * withdrawing do not. Rebuilding on any of those would pull a squad apart
 * underneath somebody mid-session for no gain.
 *
 * THE TEST IS THE MEMBERSHIP ITSELF, which is why this needs no new column
 * and no stored hash to go stale. The arrangement is current exactly when
 * every owned soldier sits in exactly one squad: a newly bought soldier is
 * in none, and a sold one leaves a squad a member short. Both show up as
 * the owned set and the assigned set disagreeing, and nothing else does.
 *
 * REBUILDING DOES NOT WITHDRAW ANYONE. soldiers.active is untouched here,
 * so a squad regrouped while its members are in the field stays in the
 * field; only the grouping changes.
 */
function dropshipSquadsAutoSync($conn, $user_id = null, $project_id = null) {
	if (!dropshipSquadsTable($conn)) return array('changed' => false, 'reason' => 'no table');
	$me  = (int)($user_id    !== null ? $user_id    : ($_SESSION['userData']['dropship_user_id'] ?? 0));
	$pid = (int)($project_id !== null ? $project_id : ($_SESSION['userData']['dropship_project_id'] ?? 0));
	if ($me <= 0 || $pid <= 0) return array('changed' => false, 'reason' => 'no player');

	$roster = dropshipSquadsRoster($conn, $me, $pid);
	$owned  = array();
	foreach ($roster as $r) $owned[(int)$r['id']] = true;

	$assigned = dropshipSquadMembership($conn, $me, $pid);
	$a_ids = array_map('intval', array_keys($assigned));
	$o_ids = array_keys($owned);
	sort($a_ids); sort($o_ids);
	if ($a_ids === $o_ids) {
		/*
		 * THE SAME SOLDIERS DOES NOT MEAN THE SAME SQUADS.
		 *
		 * Membership answers "has anything been bought or sold", which is
		 * the common case and the cheap one. It cannot see a soldier whose
		 * ARMOUR OR GEAR CHANGED while they stayed in the collection --
		 * and that is exactly what a trait remapping does. Every id would
		 * still match, so the engine would sit still while every squad on
		 * the platform was built from stats that no longer exist.
		 *
		 * So ask the other question: do they still have as many complete
		 * squads as their collection can make? A player's arrangement can
		 * never beat the maximum, so fewer means the arrangement has gone
		 * stale and is worth rebuilding -- whatever moved the stats. It
		 * self-heals rather than depending on somebody remembering to
		 * force a rebuild after a mapping change.
		 *
		 * AND IT STAYS QUIET WHEN THE COUNT HOLDS. A remap that happens
		 * not to cost anybody a squad rebuilds nobody, which is right: the
		 * arrangement is still optimal and churn with no gain is the thing
		 * this engine exists to avoid.
		 */
		$cur = array();
		$res = @$conn->query("SELECT m.squad_id, m.soldier_id
		                        FROM squad_members m
		                        INNER JOIN squads q ON q.id = m.squad_id
		                       WHERE q.user_id = $me AND q.project_id = $pid");
		if ($res) {
			$by_id = array();
			foreach ($roster as $r) $by_id[(int)$r['id']] = $r;
			while ($row = $res->fetch_assoc()) {
				$sid = (int)$row['squad_id']; $sol = (int)$row['soldier_id'];
				if (isset($by_id[$sol])) $cur[$sid][] = $by_id[$sol];
			}
		}
		$have = 0;
		foreach ($cur as $members) if (dropshipPlanIsComplete($members)) $have++;
		$best = dropshipPlanMax($roster);
		if ($have >= $best) {
			return array('changed' => false, 'reason' => 'in sync',
			             'owned' => count($o_ids), 'complete' => $have);
		}
		/* Falls through to the rebuild below. */
	}

	/* Out of date. Everything goes and is rebuilt, because the best
	   arrangement of a changed roster is not the old one with a patch. */
	@$conn->query("DELETE m FROM squad_members m
	                 JOIN squads s ON m.squad_id = s.id
	                WHERE s.user_id = $me AND s.project_id = $pid");
	@$conn->query("DELETE FROM squads WHERE user_id = $me AND project_id = $pid");

	$built = 0; $complete = 0;
	foreach (dropshipSquadPlanAll($roster) as $squad) {
		$members = $squad['members'];
		if (!$members) continue;
		/* Heaviest armour first, the order the panel already shows. */
		dropshipSquadOrderMembers($members);
		@$conn->query(sprintf("INSERT INTO squads (user_id, project_id, created_at)
		                       VALUES (%d, %d, NOW())", $me, $pid));
		$sid = (int)$conn->insert_id;
		if ($sid <= 0) continue;
		$rows = array();
		foreach ($members as $i => $m) $rows[] = sprintf('(%d,%d,%d)', $sid, (int)$m['id'], $i);
		@$conn->query('INSERT INTO squad_members (squad_id, soldier_id, slot) VALUES '
		              . implode(',', $rows));
		$built++;
		if (!empty($squad['complete'])) $complete++;
	}
	return array('changed' => true, 'reason' => 'holdings or stats changed',
	             'owned' => count($o_ids), 'squads' => $built, 'complete' => $complete);
}
