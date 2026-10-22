<?php
/**
 * WHAT A LOADOUT IS ACTUALLY WORTH.
 *
 * Drop Ship decides everything before the run: you pick a squad, buy items,
 * press Run, and what plays afterwards is a replay of something already
 * resolved. That makes the loadout screen the ONLY place a decision happens
 * -- and until now it was the one place with no numbers on it. The Skull
 * Paper has always said a full squad "has the best probability within the
 * game" without ever saying what that probability is.
 *
 * THE NUMBER COMES FROM THE RUN'S OWN MATH, NOT AN ESTIMATE. dropship.php
 * resolves each round with:
 *
 *     $probability = rand(0, 99);
 *     if ($probability < $threshold) { survived } else { death branch }
 *
 * so the threshold IS the odds, and this file owns the one copy of how that
 * threshold is built. dropship.php calls dropshipThreshold() for the run and
 * the dashboard calls it to show the figure. They cannot drift, which is the
 * whole point: a predicted number that does not match the roll is worse than
 * no number at all.
 */
require_once __DIR__ . '/dropship-grunts.php';

/**
 * The survival threshold for a loadout. Mirrors nothing -- it IS the rule.
 *
 * Flags are the four purchased suits and the four squad armour tiers, each
 * true/false. Returns the raw threshold; the run compares rand(0,99) against
 * it directly.
 *
 * TWO THINGS THAT LOOK LIKE BUGS AND ARE NOT:
 *
 * 1. A BARE SQUAD (no armour at all) sits at 79, which is BETTER than Heavy
 *    armour's 74. That is the Skulliance promo: the no-armour branch also
 *    hands out extralife, demolition and melee for free, and those side
 *    effects stay in dropship.php because they are not part of the odds.
 *    Anyone "fixing" the ordering here breaks the promo.
 *
 * 2. THE SUITS OVERRIDE ARMOUR, they do not stack with it. The chain is
 *    if/else-if, so a Mech Suit is 88.9 whatever the squad wears. There is a
 *    commented-out additive version in dropship.php; it is disabled, and this
 *    reproduces the behaviour that actually ships.
 *
 * The lower tiers pay a CREDIT on top of the best tier worn, which is what
 * makes a mixed squad worth building: Heavy alone is 74, Heavy with Medium,
 * Light and Base is 80.
 */
function dropshipThreshold($f) {
	$g = function ($k) use ($f) { return !empty($f[$k]); };

	$heavy_threshold  = 74;
	$medium_threshold = 72;
	$light_threshold  = 65.66;
	$base_threshold   = 50;
	$medium_credit    = 3;
	$light_credit     = 2;
	$base_credit      = 1;

	if ($g('mech_suit'))        return 88.9;
	if ($g('exo_suit'))         return 87.5;
	if ($g('quantum_stealth'))  return 85.7;
	if ($g('ballistic_shield')) return 83.3;

	if ($g('heavy')) {
		$t = $heavy_threshold;
		if ($g('medium')) $t += $medium_credit;
		if ($g('light'))  $t += $light_credit;
		if ($g('base'))   $t += $base_credit;
		return $t;
	}
	if ($g('medium')) {
		$t = $medium_threshold;
		if ($g('light')) $t += $light_credit;
		if ($g('base'))  $t += $base_credit;
		return $t;
	}
	if ($g('light')) {
		$t = $light_threshold;
		if ($g('base')) $t += $base_credit;
		return $t;
	}
	if ($g('base')) return $base_threshold;

	/* No armour: the promo default. dropship.php grants the free gear. */
	return 79;
}

/**
 * The threshold as a whole-number percentage, which is what a player reads.
 *
 * NOT round(). The run rolls rand(0,99) and survives on STRICTLY LESS THAN
 * the threshold, so a threshold of 65.66 survives on 0-65, which is 66 of the
 * 100 outcomes -- ceil, not round, and for a whole number it is itself.
 * round() would print 66 here too but 88 for 88.9, understating a Mech Suit
 * by a point.
 */
function dropshipSurvivalPct($threshold) {
	$pct = (int)ceil($threshold);
	if ($pct < 0)   $pct = 0;
	if ($pct > 100) $pct = 100;
	return $pct;
}

/**
 * The round a loadout typically dies on.
 *
 * Rounds 0, 1 and 2 are the drop itself and are never rolled for -- the first
 * roll happens at counter 3. After that each round is the same independent
 * roll, so the number of rounds survived is geometric and its mean is
 * p/(1-p). An extra life (a Medkit, carried or bought) buys a second run at
 * that same distribution.
 *
 * DELIBERATELY EXCLUDES the one-shot escapes -- Drop Box, Radio, Jet Pack,
 * and the melee/demolition chains -- which skip the counter forward on a
 * failed roll. They only ever push the number UP, so this reads as a floor
 * rather than a promise, which is the right way round for a figure a player
 * spends money against.
 */
function dropshipTypicalRound($pct, $extra_lives = 0) {
	$p = $pct / 100;
	if ($p >= 1) return null;                 /* never reachable; no honest number */
	$runs = 1 + max(0, (int)$extra_lives);
	return (int)round(3 + $runs * ($p / (1 - $p)));
}

/**
 * The loadout a player is currently holding.
 *
 * Reads the squad the same way setSquad() does -- active, not deceased --
 * but WITHOUT touching the globals setSquadAttributes() writes. The dashboard
 * renders after dropship.php has already run, and quietly re-setting those
 * globals mid-request is the kind of thing that changes a run instead of
 * describing one.
 */
function dropshipOddsFlags($conn) {
	$f = array('mech_suit' => false, 'exo_suit' => false, 'quantum_stealth' => false,
	           'ballistic_shield' => false, 'heavy' => false, 'medium' => false,
	           'light' => false, 'base' => false, 'extra_lives' => 0,
	           /* The roster itself, not just the flags. The flags answer "is
	              there any Heavy armour", which is all the ROLL needs; the
	              build guide has to know that two soldiers are wearing it. */
	           'roster' => array());

	/* Purchased items live on the session, exactly as the run reads them. */
	foreach (array('mech_suit', 'exo_suit', 'quantum_stealth', 'ballistic_shield') as $k) {
		if (isset($_SESSION['userData'][$k]) && $_SESSION['userData'][$k] === 'true') $f[$k] = true;
	}
	/* Weapons too, so the shop can say which one would actually fire. */
	foreach (array_keys(dropshipWeaponDelays()) as $k) {
		if (isset($_SESSION['userData'][$k]) && $_SESSION['userData'][$k] === 'true') $f[$k] = true;
	}
	/*
	 * ONE OF EACH ITEM FIRES IN A SQUAD, SO EXTRA LIVES CAP AT ONE.
	 *
	 * This counted one per Medkit -- carried, purchased and promo -- and the
	 * run was changed to spend them all. That was the wrong half to change.
	 * The game has always fired one of each item per squad; a second Medkit
	 * is a duplicate that does nothing, which is exactly why a fourth
	 * soldier carrying None is worth more than one carrying a spare Medkit.
	 * Counting them made a stacked squad strictly better and quietly
	 * rebalanced the run. The readout was over-counting; the run was right.
	 */
	if (isset($_SESSION['userData']['medkit']) && $_SESSION['userData']['medkit'] === 'true') {
		$f['extra_lives'] = 1;
	}
	/* And the armour flags the run also accepts from the session. */
	foreach (array('heavy' => 'heavy_armor', 'medium' => 'medium_armor',
	               'light' => 'light_armor', 'base' => 'base_armor') as $k => $sk) {
		if (isset($_SESSION['userData'][$sk]) && $_SESSION['userData'][$sk] === 'true') $f[$k] = true;
	}

	if (!isset($_SESSION['userData']['dropship_user_id'],
	           $_SESSION['userData']['dropship_project_id'])) {
		return $f;
	}
	$uid = (int)$_SESSION['userData']['dropship_user_id'];
	$pid = (int)$_SESSION['userData']['dropship_project_id'];
	$res = $conn->query("SELECT asset_name, rank, armor, gear FROM soldiers
	                      WHERE user_id = $uid AND active = 1 AND project_id = $pid
	                        AND deceased = 0");
	if (!$res) return $f;
	while ($row = $res->fetch_assoc()) {
		/* The same loaner kit the run will field, or this readout would quote
		   odds for a squad that does not exist. */
		$row = dropshipGruntLoadout($row);
		switch ($row['armor']) {
			case 'Heavy':  $f['heavy']  = true; break;
			case 'Medium': $f['medium'] = true; break;
			case 'Light':  $f['light']  = true; break;
			case 'Base':   $f['base']   = true; break;
		}
		/* Capped, not counted: see the note above. A roster of four Medkits
		   is one extra life, the same as a roster of one. */
		if ($row['gear'] === 'Medkit') $f['extra_lives'] = 1;
		$f['roster'][] = array('armor' => (string)$row['armor'],
		                       'gear'  => (string)$row['gear']);
	}

	/*
	 * THE PROMO LIFE COUNTS, because the run really spends it.
	 *
	 * dropship.php hands a loadout with no armour and no suit a free Medkit,
	 * Melee and Demolition -- the same branch that puts a bare squad at 79.
	 * This readout counted only carried and purchased Medkits, so it told a
	 * bare squad it had no extra life while the run gave it one, and the
	 * typical round it quoted was a round and a half short.
	 */
	if (!$f['mech_suit'] && !$f['exo_suit'] && !$f['quantum_stealth']
	    && !$f['ballistic_shield'] && !$f['heavy'] && !$f['medium']
	    && !$f['light'] && !$f['base']) {
		$f['extra_lives'] = 1;
	}
	return $f;
}

/** The four armour tiers, best first. Order is the threshold chain's own. */
function dropshipArmorTiers() { return array('Heavy', 'Medium', 'Light', 'Base'); }
/** The three gear types the run actually reads. */
function dropshipGearTypes() { return array('Melee', 'Demolition', 'Medkit'); }

/**
 * HOW TO BUILD A SUPER SOLDIER SQUAD, one step at a time.
 *
 * Four slots, four armour tiers, three gear types. The roll only reads
 * ARMOUR -- the best tier worn sets the floor and every lower tier adds its
 * credit on top, which is why a mixed squad beats four of the same thing and
 * why the complete set is 74+3+2+1 = 80. Gear does not touch the roll at
 * all; it buys an extra life and the melee and demolition chains.
 *
 * WHY THIS PANE AND NOT THE SHOP: a player staring at a half-built squad
 * needs to know what to put in the next slot, not which suit to buy. Items
 * are a different decision and they now carry their own numbers on the shop
 * listing, where somebody is already deciding to spend.
 *
 * Returns array(headline, detail) of already-escaped strings, or null when
 * there is nothing useful to say.
 */
/**
 * WHAT TO GO AND BUY, in the collection's own language.
 *
 * "A Base soldier carrying a Medkit" is only checkable by buying one; a
 * marketplace filter is not. Each collection translates that gap into
 * something a listing can be searched for.
 *
 * ROUTED ON THE POLICY, NOT A PROJECT ID, so any theme on a collection
 * gets its advice and none can inherit another's -- which is what makes
 * one Shorty Verse lookup serve both Dread City and Filthy Mermaid. A
 * collection with no mapping says nothing rather than guessing.
 *
 * Shared by both branches of the advice: the one that names a single
 * missing soldier, and the one that explains why there is no single
 * soldier to name.
 */
function dropshipShopHint($conn, $gap) {
	if (!is_array($gap) || !isset($gap['armor'], $gap['gear'])) return '';
	if (!function_exists('getProjectPolicyId')) return '';
	$pol  = (string)@getProjectPolicyId($conn);
	$hint = null;
	$routes = array(
		'dropshipDiscosolarisPolicy' => 'discosolarisTraitHints',
		'dropshipShortyversePolicy'  => 'shortyverseTraitHints',
		'dropshipOwnPolicy'          => 'dropshipOwnTraitHints',
		'dropshipDhc2Policy'         => 'dhc2TraitHints',
	);
	foreach ($routes as $policy_fn => $hint_fn) {
		if (!function_exists($policy_fn) || !function_exists($hint_fn)) continue;
		if ($pol !== $policy_fn()) continue;
		$hint = $hint_fn($gap['armor'], $gap['gear']);
		break;
	}
	return ($hint !== null && $hint !== '') ? 'Look for ' . $hint . '.' : '';
}

function dropshipSquadAdvice($f, $conn = null) {
	/*
	 * THE ADVICE IS ABOUT THE COLLECTION NOW, NOT THE SQUAD ON THE FIELD.
	 *
	 * This used to read "Swap a Sweat Pants soldier for Smoking Jacket ->
	 * 80%", and before that it walked somebody through filling four slots
	 * one at a time. Both are answers to a question nobody is asked any
	 * more: dropshipSquadsAutoSync() arranges the whole roster, so there is
	 * no slot to fill and nothing to swap. Telling a player to improve a
	 * squad the game built for them is worse than saying nothing.
	 *
	 * WHAT IS STILL ACTIONABLE IS WHAT THEY OWN. More NFTs means more
	 * complete squads -- and the planner already knows exactly which one
	 * would do it, because dropshipSquadPlan() tests every kind of soldier
	 * against the arrangement and reports the one that buys another squad.
	 * "A Base soldier carrying a Medkit" is a thing somebody can go and
	 * look for; "buy more NFTs" is not.
	 *
	 * NEEDS THE WHOLE ROSTER, not $f['roster'], which is only what is
	 * deployed. Without a connection there is nothing useful to say, so it
	 * says nothing rather than falling back to advice about the field.
	 */
	if (!is_object($conn) || !function_exists('dropshipSquadsRoster')
	    || !function_exists('dropshipSquadPlan')) {
		return null;
	}
	$me  = (int)($_SESSION['userData']['dropship_user_id'] ?? 0);
	$pid = (int)($_SESSION['userData']['dropship_project_id'] ?? 0);
	if ($me <= 0 || $pid <= 0) return null;

	$roster = dropshipSquadsRoster($conn, $me, $pid);
	if (!$roster) return null;

	$plan  = dropshipSquadPlan($roster);
	$n     = (int)$plan['count'];
	$super = dropshipWord('Super Soldier') . ' Squad';

	$headline = $n > 0
		? $n . ' ' . $super . ($n === 1 ? '' : 's')
		: 'No ' . $super . ' yet';

	$need = $plan['need'];
	if ($need !== null) {
		/* A free fourth slot is any soldier of that tier, so naming a gear
		   it does not need would send somebody hunting for the wrong one. */
		$carry = ($need['gear'] !== 'None')
			? ' carrying <b>' . dropshipWord($need['gear']) . '</b>'
			: '';
		$detail = 'One more <b>' . dropshipWord($need['armor']) . '</b> '
		        . dropshipWord('soldier') . $carry . ' would make it '
		        . ($n + 1) . '.';
		$detail .= ' ' . dropshipShopHint($conn, $need);
		$detail .= ' Squads are built for you from everything you own.';
	} elseif ($n > 0) {
		/*
		 * ALREADY MAXED, SO SAY WHAT IS ACTUALLY MISSING.
		 *
		 * $need is null when no SINGLE soldier would add a squad, which is
		 * the normal state for anybody with a real collection -- another
		 * squad wants one of every tier. "More soldiers would build more"
		 * is true and useless; the leftovers say exactly which tiers have
		 * nobody spare, and those are the ones to go and buy.
		 */
		$spare = array();
		foreach (dropshipArmorTiers() as $t) $spare[$t] = 0;
		foreach ($plan['leftover'] as $lr) {
			$t = (string)$lr['armor'];
			if (isset($spare[$t])) $spare[$t]++;
		}
		$short = array();
		foreach ($spare as $t => $c) if ($c === 0) $short[] = $t;

		$detail = 'Every ' . $super . ' your collection can make is already built. ';
		if ($short) {
			$detail .= 'Another needs ' . dropshipJoinWords(dropshipWords($short))
			        . ' armor, and you have none spare.';
			/* The same shopping advice as above, for the first gap. */
			$gap = array('armor' => $short[0], 'gear' => 'None');
			$held = array();
			foreach ($plan['leftover'] as $lr) $held[(string)$lr['gear']] = true;
			foreach (dropshipPlanItems() as $it) {
				if (!isset($held[$it])) { $gap['gear'] = $it; break; }
			}
			$detail .= ' ' . dropshipShopHint($conn, $gap);
		} else {
			$detail .= 'More ' . dropshipWord('soldier') . 's would build more.';
		}
	} else {
		$detail = 'A ' . $super . ' needs all four armor tiers and all three items. '
		        . 'More ' . dropshipWord('soldier') . 's would get you there.';
	}
	return array($headline, $detail);
}

/** "Heavy, Medium and Light" -- a list a person would read aloud. */
/**
 * A game word, in this theme's language.
 *
 * THE BUILD GUIDE WAS THE ONE PLACE STILL SPEAKING DROP SHIP. Everything
 * else on the page runs its nouns through evaluateText() -- the soldier
 * cards, the shop, the round captions -- so Filthy Mermaid read "Next: a
 * soldier in Base armor" underneath a roster of Perverts in Boxer Briefs.
 *
 * Guarded because this file is also loaded by harnesses that have no
 * database and therefore no evaluateText(); untranslated is the right
 * answer there, and it is what the function already returns for a theme
 * with no override.
 */
function dropshipWord($term) {
	return function_exists('evaluateText') ? evaluateText($term) : $term;
}

/** Each of a list of game words, translated. */
function dropshipWords($words) {
	$out = array();
	foreach ((array)$words as $w) $out[] = dropshipWord($w);
	return $out;
}

function dropshipJoinWords($words) {
	$words = array_values($words);
	$n = count($words);
	if ($n === 0) return '';
	if ($n === 1) return $words[0];
	$last = array_pop($words);
	return implode(', ', $words) . ' and ' . $last;
}

/**
 * The readout itself. Returns markup; prints nothing.
 *
 * Says what the NEXT tier would be worth as well as what this one is, because
 * "84%" on its own is a fact and "84%, a Mech Suit would make it 89%" is a
 * decision -- and the decision is the only thing this screen is for.
 */
function dropshipOddsPanel($conn) {
	$f    = dropshipOddsFlags($conn);
	$pct  = dropshipSurvivalPct(dropshipThreshold($f));
	$life = (int)$f['extra_lives'];
	$typ  = dropshipTypicalRound($pct, $life);

	/*
	 * NO ITEMS IN HERE. This pane used to finish with "a Mech Suit would make
	 * it 89%", which is a spending decision put in front of somebody still
	 * assembling a foundation; those numbers sit on the shop listing now,
	 * where a player is already choosing what to buy.
	 *
	 * WHAT IS HERE IS THIS SQUAD'S OWN GAP, spelled out rather than folded
	 * away. The barracks cards collapse theirs because a list of fourteen
	 * wants scanning; the one squad actually on the field is the one being
	 * decided about, so it reads straight.
	 *
	 * NOT THE COLLECTION-WIDE ADVICE IT USED TO CARRY. That answered "what
	 * should I buy next" on a pane that prices the four soldiers in front
	 * of you, and it did not change when you swapped squads.
	 */
	$advice = function_exists('dropshipGapAdvice')
		? dropshipGapAdvice($conn, isset($f['roster']) ? $f['roster'] : array()) : null;

	$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES); };

	/* Styles ride along with the panel, once. Drop Ship builds its CSS from
	   input.css through a toolchain this does not need to join for one
	   readout, and the class names are scoped so nothing else can catch them. */
	static $css_done = false;
	$out = '';
	if (!$css_done) {
		$css_done = true;
		/*
		 * TEXT-ALIGN IS RE-STATED ON EVERY LINE, and it is not redundant.
		 * dist/flexbox.css centres text globally, and a flex child stretched
		 * to full width inherits that -- so the first row sat left (flex puts
		 * it at flex-start) while every line under it centred, which is
		 * exactly how this first shipped.
		 *
		 * #00c8a0 is the platform accent the Squad heading above already
		 * uses. The first version was lime, which belonged to nothing.
		 */
		$out .= '<style>'
		  . '.ds-odds{display:flex;flex-wrap:wrap;align-items:baseline;gap:3px 10px;'
		  . 'margin:0 0 14px;padding:11px 14px;border:1px solid rgba(255,255,255,.12);'
		  . 'border-radius:4px;background:rgba(0,0,0,.28);line-height:1.5;text-align:left}'
		  . '.ds-odds>span{text-align:left}'
		  . '.ds-odds-pct{font-size:1.55rem;font-weight:bold;color:#00c8a0;letter-spacing:.01em}'
		  . '.ds-odds-lbl{font-size:.82rem;color:#e8eaed;opacity:.8}'
		  . '.ds-odds-typ{flex:1 1 100%;font-size:.86rem;color:#e8eaed}'
		  . '.ds-odds-typ b{color:#00c8a0}'
		  . '.ds-odds-step{flex:1 1 100%;font-size:.9rem;color:#e8eaed;margin-top:5px;'
		  . 'padding-top:7px;border-top:1px solid rgba(255,255,255,.1)}'
		  . '.ds-odds-step b{color:#00c8a0}'
		  . '.ds-odds-why{flex:1 1 100%;font-size:.8rem;color:#e8eaed;opacity:.7}'
		  . '@media(max-width:480px){.ds-odds-pct{font-size:1.3rem}}'
		  . '</style>';
	}
	$out .= '<div class="ds-odds">';
	$out .= '<span class="ds-odds-pct">' . $h($pct) . '%</span>';
	$out .= '<span class="ds-odds-lbl">to survive each round</span>';
	if ($typ !== null) {
		$out .= '<span class="ds-odds-typ">typically reaches round <b>' . $h($typ) . '</b>';
		if ($life > 0) {
			$out .= ', with ' . $h($life) . ' extra ' . ($life === 1 ? 'life' : 'lives');
		}
		$out .= '</span>';
	}
	/*
	 * THE ADVICE IS NOT HERE ANY MORE. This pane prices the squad ON THE
	 * FIELD; the advice is about the whole collection and does not change
	 * when you swap squads, so it reads as part of the wrong thing.
	 *
	 * It lives in the barracks panel now, expandable, where it is visible
	 * whether or not anything is deployed. Keeping a copy here meant the
	 * barracks one had to be suppressed while a squad was out -- which is
	 * most of the time, so the expandable version was effectively hidden.
	 */
	if ($advice !== null) {
		/* Already escaped by dropshipGapAdvice(), which emits only its own
		   <b> around values from fixed lists. */
		$out .= '<span class="ds-odds-step">' . $advice[0] . '</span>';
		$out .= '<span class="ds-odds-why">' . $advice[1] . '</span>';
	}
	$out .= '</div>';
	return $out;
}

/**
 * What one shop item would do to the odds, as a line for its listing.
 *
 * WHY THE SHOP AND NOT JUST THE SQUAD PANEL: the squad panel answers "how
 * good am I", which is a status. The question a player has with their finger
 * over a Buy button is "how much better would THIS make me", which is a
 * decision, and the two are not the same number. An item that costs real
 * scrip should say what the scrip buys.
 *
 * MEASURED AGAINST THE LOADOUT THEY ARE ACTUALLY HOLDING, so a Ballistic
 * Shield reads as a big jump to a bare squad and as nothing at all to someone
 * already in a Mech Suit -- which is the truth, because the suits override
 * each other rather than stacking.
 *
 * SILENT WHERE IT CANNOT MEASURE. The item name is matched against the same
 * session key the run uses, and anything not recognised returns null rather
 * than a guess: weapons, Radar and Night Vision Goggles decorate a round that
 * was already survived and change no odds this file can see. A made-up
 * number on a price tag is worse than no number.
 *
 * $name is the raw items.name; the run turns it into a session key with the
 * same strtolower/underscore rule, so they cannot drift apart.
 */
function dropshipItemOddsNote($name, $f) {
	$key = strtolower(str_replace(' ', '_', (string)$name));

	$now  = dropshipSurvivalPct(dropshipThreshold($f));
	$life = isset($f['extra_lives']) ? (int)$f['extra_lives'] : 0;

	/* The four suits set the per-round roll outright. */
	if (in_array($key, array('mech_suit', 'exo_suit', 'quantum_stealth', 'ballistic_shield'), true)) {
		if (!empty($f[$key])) return 'Carried &middot; ' . $now . '% per round';
		$try = $f; $try[$key] = true;
		$then = dropshipSurvivalPct(dropshipThreshold($try));
		if ($then > $now) return 'Survival ' . $now . '% &rarr; <b>' . $then . '%</b> per round';
		/* NO DOWNGRADE BRANCH, because there is no downgrade to report:
		   adding a suit can only move you UP the override chain or leave you
		   where you are. Checked over all 256 loadouts x 4 suits, zero cases
		   lower the odds. A Ballistic Shield bought over a Mech Suit is "no
		   change", which is the honest thing to tell a shopper and is what
		   stops them buying it. */
		return 'No change &middot; already at ' . $now . '%';
	}

	/* A Medkit does not change the roll, it buys a second run at it. */
	if ($key === 'medkit') {
		$a = dropshipTypicalRound($now, $life);
		$b = dropshipTypicalRound($now, $life + 1);
		if ($a === null || $b === null) return null;
		return 'An extra life &middot; typical round ' . $a . ' &rarr; <b>' . $b . '</b>';
	}

	/* One-shot escapes on a failed roll. They skip the counter forward rather
	   than changing the odds, so they are named and explicitly excluded. */
	if (in_array($key, array('drop_box', 'radio', 'jet_pack'), true)) {
		return 'One escape from a failed round &middot; not counted in the odds';
	}

	/*
	 * WEAPONS ADD ROUNDS, they do not change the roll. On a failed round the
	 * melee chain can fire, and weapondelay() pushes the counter forward by
	 * a fixed amount per weapon -- and the counter at death IS the score.
	 *
	 * WHICH WEAPON FIRES IS NOT THE BEST ONE YOU OWN. setWeapons() is a
	 * chain of non-exclusive ifs that each overwrite the choice, so the LAST
	 * one checked wins, and Tactical Katana (+1) is checked last. Owning a
	 * Rocket Launcher and a Katana fires the Katana. That is reported rather
	 * than smoothed over: a shop that tells someone a Rocket Launcher is
	 * worth +5 while the run gives them +1 is lying to them.
	 */
	$delays = dropshipWeaponDelays();
	if (isset($delays[$key])) {
		$active = dropshipActiveWeapon($f);
		$n = $delays[$key];
		$plus = '+' . $n . ' round' . ($n === 1 ? '' : 's') . ' when the melee chain fires';
		if ($active === null || $active === $key) return $plus;
		$an = $delays[$active];
		if ($an >= $n) return $plus . ' &middot; your ' . dropshipWeaponLabel($active) . ' fires instead';
		return $plus . ' &middot; <b>but your ' . dropshipWeaponLabel($active) . ' (+' . $an
		     . ') fires instead</b>';
	}

	return null;
}

/**
 * Every weapon's round bonus, IN THE ORDER setWeapons() CHECKS THEM.
 *
 * The order is load-bearing, not cosmetic: that function overwrites its
 * choice on each match, so the last entry here that a player owns is the one
 * that actually fires. Keep this list in setWeapons()'s order or the shop
 * starts naming the wrong weapon.
 */
function dropshipWeaponDelays() {
	return array(
		'rocket_launcher' => 5,
		'flamethrower'    => 4,
		'machine_gun'     => 3,
		'smoke_bomb'      => 2,
		'grenade'         => 2,
		'sniper_rifle'    => 1,
		'tactical_katana' => 1,
	);
}

/** Human label for a weapon key. */
function dropshipWeaponLabel($key) {
	return ucwords(str_replace('_', ' ', $key));
}

/**
 * The weapon that would actually fire, or null if none is owned.
 *
 * LAST OWNED WINS, mirroring setWeapons(). Reads the session the same way
 * the run does.
 */
function dropshipActiveWeapon($f = null) {
	$active = null;
	foreach (dropshipWeaponDelays() as $key => $delay) {
		$owned = isset($f[$key]) ? !empty($f[$key])
		       : (isset($_SESSION['userData'][$key]) && $_SESSION['userData'][$key] === 'true');
		if ($owned) $active = $key;
	}
	return $active;
}
