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
	           'light' => false, 'base' => false, 'extra_lives' => 0);

	/* Purchased items live on the session, exactly as the run reads them. */
	foreach (array('mech_suit', 'exo_suit', 'quantum_stealth', 'ballistic_shield') as $k) {
		if (isset($_SESSION['userData'][$k]) && $_SESSION['userData'][$k] === 'true') $f[$k] = true;
	}
	if (isset($_SESSION['userData']['medkit']) && $_SESSION['userData']['medkit'] === 'true') {
		$f['extra_lives']++;
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
	$res = $conn->query("SELECT armor, gear FROM soldiers
	                      WHERE user_id = $uid AND active = 1 AND project_id = $pid
	                        AND deceased = 0");
	if (!$res) return $f;
	while ($row = $res->fetch_assoc()) {
		switch ($row['armor']) {
			case 'Heavy':  $f['heavy']  = true; break;
			case 'Medium': $f['medium'] = true; break;
			case 'Light':  $f['light']  = true; break;
			case 'Base':   $f['base']   = true; break;
		}
		if ($row['gear'] === 'Medkit') $f['extra_lives']++;
	}
	return $f;
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

	/* The best thing not already carried, and what it would be worth. */
	$next = null;
	foreach (array('mech_suit' => 'a Mech Suit', 'exo_suit' => 'an Exo Suit',
	               'quantum_stealth' => 'Quantum Stealth', 'ballistic_shield' => 'a Ballistic Shield')
	         as $k => $label) {
		if (!empty($f[$k])) break;                    /* already carrying this or better */
		$try = $f; $try[$k] = true;
		$trypct = dropshipSurvivalPct(dropshipThreshold($try));
		if ($trypct > $pct) { $next = array($label, $trypct, dropshipTypicalRound($trypct, $life)); break; }
	}

	$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES); };

	/* Styles ride along with the panel, once. Drop Ship builds its CSS from
	   input.css through a toolchain this does not need to join for one
	   readout, and the class names are scoped so nothing else can catch them. */
	static $css_done = false;
	$out = '';
	if (!$css_done) {
		$css_done = true;
		$out .= '<style>'
		  . '.ds-odds{display:flex;flex-wrap:wrap;align-items:baseline;gap:4px 10px;'
		  . 'margin:0 0 14px;padding:10px 14px;border:1px solid rgba(255,255,255,.14);'
		  . 'border-radius:4px;background:rgba(0,0,0,.28);line-height:1.5}'
		  . '.ds-odds-pct{font-size:1.6rem;font-weight:bold;color:#7CFC00}'
		  . '.ds-odds-lbl{font-size:.82rem;opacity:.85}'
		  . '.ds-odds-typ{flex:1 1 100%;font-size:.86rem}'
		  . '.ds-odds-next{flex:1 1 100%;font-size:.82rem;color:#ffd47a}'
		  . '.ds-odds-note{flex:1 1 100%;font-size:.72rem;opacity:.6}'
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
	if ($next !== null) {
		$out .= '<span class="ds-odds-next">' . $h($next[0]) . ' would make it '
		      . $h($next[1]) . '%';
		if ($next[2] !== null) $out .= ' and round ' . $h($next[2]);
		$out .= '</span>';
	}
	/* Honest about what the figure leaves out, in the place it is read. */
	$out .= '<span class="ds-odds-note">Drop Box, Radio and Jet Pack are not counted '
	      . '&mdash; they only push it further.</span>';
	$out .= '</div>';
	return $out;
}
