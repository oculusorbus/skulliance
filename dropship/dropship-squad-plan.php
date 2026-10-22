<?php
/**
 * THE MOST COMPLETE SQUADS A ROSTER CAN FIELD, AND PROVABLY THE MOST.
 *
 * A player holding forty soldiers cannot see what is in them. The odds pane
 * prices the squad they HAVE built; nothing tells them the arrangement that
 * gets the most complete squads out of the collection, and it is not the
 * arrangement anybody reaches for by hand.
 *
 * WHAT COMPLETE MEANS, which is the owner's rule and not the arithmetic's:
 * all four armour tiers AND all three items in one squad.
 *
 *   armour   Heavy + Medium + Light + Base   74+3+2+1 = 80, the best roll
 *   items    Medkit + Melee + Demolition     one of each fires
 *
 * A DUPLICATE ITEM IS DEAD WEIGHT. Only one of each item fires in a squad,
 * so a second Medkit does nothing here AND is a Medkit the next squad
 * cannot have. The fourth soldier is worth most carrying None.
 *
 * WHY THIS IS NOT A COUNTING PROBLEM. On armour alone the answer is
 * min(Heavy, Medium, Light, Base). Requiring the three items INSIDE each
 * squad makes tiers and items interact: a squad holds one soldier per tier,
 * so the three items must come from three DIFFERENT tiers. A roster with
 * ten of every tier builds no second squad if every Medkit it owns is
 * Heavy. That is the case a player cannot eyeball.
 *
 * WHY IT IS NOT A SEARCH EITHER, which is the part worth knowing:
 *
 *   1. Soldiers are interchangeable inside a (tier, gear) bucket, so a
 *      roster of any size is 16 numbers.
 *   2. For k squads, let y[item][tier] be how many squads take that item
 *      from that tier. Every squad needs each item once, so each item's
 *      row sums to k; a tier fills one slot per squad, so each tier's
 *      column is at most k; and y cannot exceed what is held. That is a
 *      feasibility question on a 3x4 table -- a max-flow on nine nodes.
 *   3. Given such a y, the squads ALWAYS exist. The item-tier table is a
 *      bipartite multigraph whose item degrees are exactly k and whose
 *      tier degrees are at most k, so by Konig's edge-colouring theorem it
 *      splits into k matchings -- and a matching is one item per tier, all
 *      distinct, which is a squad. Nothing can be stranded by a bad choice
 *      earlier, which is exactly what a greedy planner risks.
 *
 * So: take the largest k that is feasible. There is no arrangement with
 * k+1 because the flow says the counts cannot support it, and the leftovers
 * are leftovers rather than a squad somebody failed to spot.
 *
 * PURE. No database, no session. dropship-squad-plan-harness.php checks it
 * against exhaustive search on thousands of random rosters.
 */

/** The three items a complete squad carries between its four members. */
function dropshipPlanItems() { return array('Medkit', 'Melee', 'Demolition'); }

/**
 * 16 buckets and 4 tier totals.
 *
 * Assumes dropshipGruntLoadout() has ALREADY been applied by the caller, so
 * the loaner kits and the pinned NFT overrides are what gets planned around.
 * Planning on the stored columns hands a Grunt a squad it cannot field.
 */
function dropshipPlanCounts($roster) {
	$n = array(); $total = array();
	foreach (dropshipArmorTiers() as $t) {
		$total[$t] = 0;
		$n[$t] = array();
		foreach (array_merge(dropshipPlanItems(), array('None')) as $g) $n[$t][$g] = 0;
	}
	foreach ($roster as $r) {
		$t = isset($r['armor']) ? (string)$r['armor'] : '';
		if (!isset($n[$t])) continue;
		$g = isset($r['gear']) ? (string)$r['gear'] : 'None';
		if (!isset($n[$t][$g])) $g = 'None';      /* unknown gear is no gear */
		$n[$t][$g]++;
		$total[$t]++;
	}
	return array($n, $total);
}

/**
 * Can k complete squads be built? Returns y[item][tier], or null.
 *
 * Max-flow on source -> item (k) -> tier (held) -> sink (k). Saturating the
 * source means every squad found all three of its items on distinct tiers.
 * Nine nodes, so the implementation can be the obvious one.
 */
function dropshipPlanFeasible($n, $total, $k) {
	if ($k <= 0) return array();
	$items = dropshipPlanItems();
	$tiers = dropshipArmorTiers();
	/* A tier with fewer than k soldiers cannot put one in every squad, and
	   no amount of item juggling fixes that. */
	foreach ($tiers as $t) if ($total[$t] < $k) return null;

	$nodes = array_merge(array('@src'), $items, $tiers, array('@snk'));
	$cap = array();
	foreach ($nodes as $a) foreach ($nodes as $b) $cap[$a][$b] = 0;
	foreach ($items as $i) {
		$cap['@src'][$i] = $k;
		foreach ($tiers as $t) $cap[$i][$t] = $n[$t][$i];
	}
	foreach ($tiers as $t) $cap[$t]['@snk'] = $k;

	$flow = 0;
	while (true) {
		/* BFS for any augmenting path. */
		$prev = array('@src' => '@src');
		$queue = array('@src');
		while ($queue) {
			$u = array_shift($queue);
			if ($u === '@snk') break;
			foreach ($nodes as $v) {
				if (isset($prev[$v]) || $cap[$u][$v] <= 0) continue;
				$prev[$v] = $u;
				$queue[] = $v;
			}
		}
		if (!isset($prev['@snk'])) break;
		/* Smallest capacity along it. */
		$push = PHP_INT_MAX;
		for ($v = '@snk'; $v !== '@src'; $v = $prev[$v]) $push = min($push, $cap[$prev[$v]][$v]);
		for ($v = '@snk'; $v !== '@src'; $v = $prev[$v]) {
			$cap[$prev[$v]][$v] -= $push;
			$cap[$v][$prev[$v]] += $push;
		}
		$flow += $push;
	}
	if ($flow < $k * count($items)) return null;

	/* Read y back off the residual: what was pushed item -> tier. */
	$y = array();
	foreach ($items as $i) {
		foreach ($tiers as $t) $y[$i][$t] = $n[$t][$i] - $cap[$i][$t];
	}
	return $y;
}

/**
 * The largest number of complete squads this roster supports.
 *
 * BINARY SEARCH, because feasibility is monotone: any solution for k
 * squads is a solution for k-1 with one squad put back, so there is a
 * single boundary to find rather than a run of answers to walk. A whale
 * holding a thousand soldiers has a ceiling around 250, which was 250
 * flow solves counting down and is 8 bisecting -- and this runs on a page
 * load, not a cron.
 *
 * The ceiling is min(tier totals): every squad spends one soldier from
 * every tier, so no arrangement can beat the scarcest tier's count.
 */
function dropshipPlanMaxSquads($n, $total) {
	$lo = 0;                                      /* known feasible */
	$hi = min($total) + 1;                        /* known infeasible */
	while ($hi - $lo > 1) {
		$mid = intdiv($lo + $hi, 2);
		if (dropshipPlanFeasible($n, $total, $mid) !== null) $lo = $mid;
		else                                                 $hi = $mid;
	}
	return $lo;
}

/**
 * A fingerprint of what the player holds.
 *
 * THE FASTEST PLAN IS THE ONE NOT COMPUTED. Squads are rebuilt when the
 * holdings change and at no other time, so the common page load is a hash
 * compare rather than a solve. Only the facts the plan depends on go in --
 * who, in what armour, carrying what -- so a rename or a level up does not
 * invalidate anything, and sorting first means the row order the database
 * happens to return cannot either.
 */
function dropshipPlanFingerprint($roster) {
	$parts = array();
	foreach ($roster as $r) {
		$parts[] = (string)$r['id'] . ':' . (string)$r['armor'] . ':' . (string)$r['gear'];
	}
	sort($parts);
	return md5(implode('|', $parts));
}

/**
 * Split y into k squads: one item per tier, all tiers distinct.
 *
 * NOT ANY MATCHING WILL DO, which is the trap this fell into first. Taking
 * a valid squad out can leave a remainder that cannot be split at all:
 *
 *   Medkit     Medium 1, Base 1          two squads, and the only Melee
 *   Melee      Heavy 2                   pair is on Heavy, so both squads
 *   Demolition Light 1, Base 1           take Melee from Heavy
 *
 * Pull out (Melee Heavy, Medkit Medium, Demolition Light) and the rest is
 * Medkit Base and Demolition Base -- both on Base, which one squad cannot
 * hold. Pull out (Melee Heavy, Medkit Base, Demolition Light) instead and
 * the remainder is a squad. Same count, and only one of the two orders
 * works, so backtracking inside a squad is not enough.
 *
 * THE RULE THAT MAKES IT SAFE comes out of the edge-colouring proof. With r
 * squads left, every item has exactly r edges and no tier has more than r.
 * A tier sitting AT r must be used by this squad, or its r edges have to
 * fit into r-1 squads and one is stranded. At most three tiers can be at r
 * at once -- the degrees sum to 3r, so four tiers at r would need 4r -- and
 * a squad covers three, so such a matching always exists.
 *
 * Twenty-four injective maps of three items onto four tiers, so they are
 * enumerated rather than reasoned about.
 */
function dropshipPlanColour($y, $k) {
	$items = dropshipPlanItems();
	$tiers = dropshipArmorTiers();
	$out = array();

	for ($r = $k; $r >= 1; $r--) {
		/* Tiers that must be in this squad: the ones with as many edges
		   left as there are squads left. */
		$must = array();
		foreach ($tiers as $t) {
			$deg = 0;
			foreach ($items as $i) $deg += $y[$i][$t];
			if ($deg >= $r) $must[$t] = true;
		}

		$chosen = null;
		foreach ($tiers as $a) {
			foreach ($tiers as $b) {
				if ($b === $a) continue;
				foreach ($tiers as $c) {
					if ($c === $a || $c === $b) continue;
					$map = array($items[0] => $a, $items[1] => $b, $items[2] => $c);
					$ok = true;
					foreach ($map as $i => $t) if ($y[$i][$t] <= 0) { $ok = false; break; }
					if (!$ok) continue;
					foreach ($must as $t => $_) {
						if ($t !== $a && $t !== $b && $t !== $c) { $ok = false; break; }
					}
					if ($ok) { $chosen = $map; break 3; }
				}
			}
		}
		/* Cannot happen for a y the flow produced; returning null rather
		   than a short squad means the caller finds out instead of a player
		   being handed three soldiers and a gap. */
		if ($chosen === null) return null;

		foreach ($chosen as $i => $t) $y[$i][$t]--;
		$out[] = $chosen;
	}
	return $out;
}

/**
 * The plan: the squads themselves, what is left, and what is stopping one more.
 *
 * THE LIMIT IS THE HALF A PLAYER CAN ACT ON. "One Base soldier short of a
 * third squad" is advice; a rearranged list of forty soldiers is a chore.
 * It is measured, not guessed: the blocker is whatever single addition
 * would make k+1 feasible.
 */
function dropshipSquadPlan($roster) {
	list($n, $total) = dropshipPlanCounts($roster);
	$items = dropshipPlanItems();
	$tiers = dropshipArmorTiers();

	$k = dropshipPlanMaxSquads($n, $total);
	$y = $k > 0 ? dropshipPlanFeasible($n, $total, $k) : array();
	$shapes = $k > 0 ? dropshipPlanColour($y, $k) : array();

	/* Hand out real soldiers. Pools are per (tier, gear) because that is the
	   only thing that distinguishes one soldier from another here. */
	$pool = array();
	foreach ($tiers as $t) foreach (array_merge($items, array('None')) as $g) $pool[$t][$g] = array();
	foreach ($roster as $r) {
		$t = (string)$r['armor'];
		if (!isset($pool[$t])) continue;
		$g = (string)$r['gear'];
		if (!isset($pool[$t][$g])) $g = 'None';
		$pool[$t][$g][] = $r;
	}

	/*
	 * ITEM SLOTS FIRST, ACROSS EVERY SQUAD, AND ONLY THEN THE FREE ONES.
	 *
	 * Filling each squad completely before starting the next one produced
	 * squads with a hole in them: a free slot would take the last Base
	 * Medkit because None had run out, and the next squad's plan was
	 * counting on that exact soldier for its Medkit. The plan was right and
	 * the handing-out was wrong.
	 *
	 * Two passes fixes it outright. Every item slot the flow allocated is
	 * claimed before any free slot is filled, so a free slot can only ever
	 * spend a soldier nothing else needs. There are always enough left:
	 * tier t owes k soldiers in total and holds at least k.
	 */
	$squads = array(); $free_tier = array();
	foreach ($shapes as $si => $shape) {
		$squad = array(); $filled = array();
		foreach ($shape as $item => $tier) {
			$squad[] = array_shift($pool[$tier][$item]);
			$filled[$tier] = true;
		}
		foreach ($tiers as $t) if (!isset($filled[$t])) $free_tier[$si] = $t;
		$squads[$si] = $squad;
	}
	/* The free slot. None first: a duplicate item does nothing in this
	   squad and is one the next squad might have needed. */
	foreach ($squads as $si => $squad) {
		if (!isset($free_tier[$si])) continue;
		$t = $free_tier[$si];
		foreach (array_merge(array('None'), $items) as $g) {
			if (!empty($pool[$t][$g])) { $squads[$si][] = array_shift($pool[$t][$g]); break; }
		}
	}

	$left = array();
	foreach ($pool as $gears) foreach ($gears as $rows) foreach ($rows as $r) $left[] = $r;

	/*
	 * WHAT WOULD BUY ONE MORE. Asked by adding a single hypothetical soldier
	 * of each kind and re-running the feasibility test, so the answer is the
	 * real one rather than a reading of the counts.
	 */
	$need = null;
	foreach ($tiers as $t) {
		foreach (array_merge($items, array('None')) as $g) {
			$n2 = $n; $t2 = $total;
			$n2[$t][$g]++; $t2[$t]++;
			if (dropshipPlanFeasible($n2, $t2, $k + 1) !== null) {
				$need = array('armor' => $t, 'gear' => $g);
				break 2;
			}
		}
	}
	return array('squads' => $squads, 'leftover' => $left, 'count' => $k, 'need' => $need);
}

/** The most complete squads a roster supports, as one call. */
function dropshipPlanMax($roster) {
	list($n, $total) = dropshipPlanCounts($roster);
	return dropshipPlanMaxSquads($n, $total);
}

/** Is this group of four already a complete squad? */
function dropshipPlanIsComplete($members) {
	if (count($members) !== 4) return false;
	$t = array(); $g = array();
	foreach ($members as $m) {
		if (!is_array($m)) return false;
		$t[(string)$m['armor']] = true;
		$g[(string)$m['gear']]  = true;
	}
	if (count($t) !== 4) return false;
	foreach (dropshipPlanItems() as $i) if (!isset($g[$i])) return false;
	return true;
}

/**
 * WHAT TO CHANGE, for a player who already has squads.
 *
 * The planner above answers "what is the best arrangement". That is not
 * the question somebody with three saved squads is asking. They want to
 * know what to TOUCH, and the answer has to be as small as possible --
 * breaking up a squad somebody assembled deliberately, to end up with the
 * same number of squads, is a worse outcome than leaving it alone.
 *
 * SO A COMPLETE SQUAD IS KEPT UNLESS KEEPING IT COSTS A SQUAD. That test
 * is exact rather than assumed: hold the squad aside, ask the planner for
 * the maximum over everything else, and keep it only if the total still
 * reaches the overall best. A complete squad CAN be in the way -- it may
 * be holding the one Base Medkit that two other squads needed -- and this
 * is how that case is found rather than guessed at.
 *
 * Incomplete squads are always dispersed: they are not worth anything as
 * squads, and their soldiers are what the new ones get built from.
 *
 * $current is squad_id => array of member rows, each row carrying at least
 * id, armor and gear, already passed through dropshipGruntLoadout().
 */
function dropshipSquadPlanDiff($roster, $current) {
	$best = dropshipPlanMax($roster);

	/* Index the roster by id so squads can be subtracted from it. */
	$by_id = array();
	foreach ($roster as $r) $by_id[(string)$r['id']] = $r;

	$complete = array(); $broken = array();
	foreach ($current as $sid => $members) {
		if (dropshipPlanIsComplete($members)) $complete[$sid] = $members;
		else                                  $broken[]       = $sid;
	}

	/*
	 * Try to keep each complete squad, strongest claim first. Order matters
	 * only when two complete squads cannot both be kept; taking them in a
	 * stable order keeps the recommendation the same from one page load to
	 * the next, which matters more here than which one wins.
	 */
	ksort($complete);
	$pool   = $by_id;
	$keep   = array();
	$target = $best;
	foreach ($complete as $sid => $members) {
		$without = $pool;
		foreach ($members as $m) unset($without[(string)$m['id']]);
		/*
		 * ONE FEASIBILITY TEST, NOT A FULL MAXIMUM.
		 *
		 * Keeping this squad is worth it when the rest still supports
		 * target-1, and the rest can never support MORE than that: it is a
		 * subset of a roster whose best is target, and this squad adds one
		 * on top. So the question is a yes or no at a known k, and asking
		 * dropshipPlanMax() for the number instead cost a binary search per
		 * saved squad -- 39ms for a holder with 250 of them.
		 */
		list($n2, $t2) = dropshipPlanCounts(array_values($without));
		$fits = ($target - 1) === 0
		        ? true
		        : dropshipPlanFeasible($n2, $t2, $target - 1) !== null;
		if ($fits) {
			$keep[] = $sid;
			$pool   = $without;
			$target--;
		}
	}

	$disperse = array_merge($broken, array_values(array_diff(array_keys($complete), $keep)));
	sort($disperse);

	$plan = dropshipSquadPlan(array_values($pool));
	return array(
		'now'      => count($complete),   /* complete squads they have today */
		'best'     => $best,              /* complete squads they could have */
		'gain'     => $best - count($complete),
		'keep'     => $keep,              /* squad ids to leave alone */
		'disperse' => $disperse,          /* squad ids to break up */
		'build'    => $plan['squads'],    /* the new squads, as member rows */
		'leftover' => $plan['leftover'],
		'need'     => $plan['need'],
	);
}

/** What a squad's armour is worth a round, from the tiers actually in it. */
function dropshipPlanSquadThreshold($members) {
	$f = array('heavy' => false, 'medium' => false, 'light' => false, 'base' => false);
	$map = array('Heavy' => 'heavy', 'Medium' => 'medium', 'Light' => 'light', 'Base' => 'base');
	foreach ($members as $m) {
		if (is_array($m) && isset($map[(string)$m['armor']])) $f[$map[(string)$m['armor']]] = true;
	}
	return dropshipThreshold($f);
}

/**
 * EVERY SOLDIER IN A SQUAD, BEST FIRST, NOTHING LEFT OVER.
 *
 * The planner above stops once it has wrung every COMPLETE squad out of a
 * roster, and hands back the remainder as a pile. A player does not want a
 * pile. They want the rest of their collection arranged into the best
 * squads it can still make, so that whichever one they field is the best
 * thing available at that moment.
 *
 * THREE PHASES, AND THE FIRST TWO ARE EXACT.
 *
 *   1. Complete squads -- four tiers and all three items. Maximised by the
 *      flow above, and proven maximal.
 *   2. Four-tier squads out of what is left. Armour alone is worth 80, and
 *      a squad with one of every tier is the best thing a remainder can
 *      still produce. Taking one soldier from each tier while all four
 *      have someone left yields min(tier counts) of them, which is the
 *      most there can be: every such squad consumes one from each tier.
 *   3. The tail. Fewer than four tiers have anyone left, so the thresholds
 *      drop and the remaining choices are between squads worth 79 and 78
 *      and so on. Best-first by tier value, which is greedy -- and the
 *      spread it is choosing between is a point or two of survival on
 *      squads a player is unlikely to field while a complete one exists.
 *
 * ITEMS STILL MATTER IN PHASES 2 AND 3 even though they do not move the
 * roll: one of each fires, so a soldier who adds a NEW item to the squad
 * is preferred over one carrying a duplicate or nothing.
 */
function dropshipSquadPlanAll($roster) {
	$plan  = dropshipSquadPlan($roster);
	$items = dropshipPlanItems();
	$tiers = dropshipArmorTiers();

	$out = array();
	foreach ($plan['squads'] as $sq) {
		$out[] = array('members' => $sq, 'complete' => true,
		               'threshold' => dropshipPlanSquadThreshold($sq));
	}

	/* Everything the complete squads did not use, bucketed by tier. */
	$left = array();
	foreach ($tiers as $t) $left[$t] = array();
	foreach ($plan['leftover'] as $r) {
		$t = (string)$r['armor'];
		if (isset($left[$t])) $left[$t][] = $r;
	}

	$remaining = function () use (&$left) {
		$n = 0;
		foreach ($left as $rows) $n += count($rows);
		return $n;
	};

	while ($remaining() > 0) {
		$squad = array(); $have = array();
		/* One from each tier that still has somebody, best tier first. */
		foreach ($tiers as $t) {
			if (count($squad) >= 4 || empty($left[$t])) continue;
			$pick = 0;
			foreach ($left[$t] as $i => $r) {
				$g = (string)$r['gear'];
				/* A soldier adding an item the squad lacks beats one who
				   duplicates an item already in it or carries none. */
				if (in_array($g, $items, true) && !isset($have[$g])) { $pick = $i; break; }
			}
			$chosen = $left[$t][$pick];
			array_splice($left[$t], $pick, 1);
			$squad[] = $chosen;
			$have[(string)$chosen['gear']] = true;
		}
		/* Fewer than four tiers had anyone: fill the slots from the deepest
		   pools so the squads that follow stay as varied as possible. */
		while (count($squad) < 4 && $remaining() > 0) {
			$deepest = null;
			foreach ($tiers as $t) {
				if (empty($left[$t])) continue;
				if ($deepest === null || count($left[$t]) > count($left[$deepest])) $deepest = $t;
			}
			if ($deepest === null) break;
			$squad[] = array_shift($left[$deepest]);
		}
		$out[] = array('members' => $squad,
		               'complete' => dropshipPlanIsComplete($squad),
		               'threshold' => dropshipPlanSquadThreshold($squad));
	}
	return $out;
}
