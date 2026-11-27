<?php
/**
 * gauntlets-engine.php -- the Gauntlet fight, resolved.
 *
 * PURE. No database, no session, no output, no globals. It takes a fight
 * state and one action and returns the new state plus a TIMELINE of what
 * happened for the client to animate. Same arrangement as
 * dhcarena-engine.php, and for the same reason: the client sends "attack
 * with weapon 7", this decides everything, and the client decides nothing.
 *
 * WHY THE GAME CHANGED. The old encounter was
 *
 *     effective_wc = min(100, base_wc + consumables + weapon_level + armor_level)
 *
 * followed by one roll. Everything a player brought collapsed into a single
 * integer and a coin flip, which is why a shotgun and a breastplate were
 * mathematically the same item and why the game reads as luck. A single
 * flip also has maximum variance: with three wins needed, a 70% matchup
 * sweeps only 34% of the time. See gauntlets-redesign.md.
 *
 * A multi-round fight averages many small rolls instead of one big one, so
 * variance collapses and what the player brought actually shows.
 *
 * SEEDED AND REPLAYABLE. Every roll comes from the fight's own seed, so a
 * resolution can be replayed exactly -- which is what makes a result
 * auditable and a bug reproducible.
 *
 * EVERY TUNING NUMBER IS IN ONE BLOCK BELOW. They are first guesses made to
 * be moved, not balance. Nothing outside that block should carry a number
 * that decides how the game feels.
 */

/* ===================== TUNING =========================================
 * Move these. Nothing here is sacred and nothing else should hold a
 * number that changes the feel of a fight.
 * ===================================================================== */

/* A squad member's health, and the only pool that persists across a run. */
if (!defined('GAUNT_HP_BASE'))        define('GAUNT_HP_BASE',        120);

/*
 * THE MATCHUP SETS THE OPPONENT'S HEALTH, not yours.
 *
 * gauntletCalculateWinChance() still returns 70 / 50 / 30 from the
 * rock-paper-scissors between effective projects. Rather than throwing
 * that away or turning it into YOUR health -- which collides with a pool
 * that carries across encounters, and makes a fight decided before it
 * starts -- it scales the opponent you are given. A favourable matchup is
 * a smaller enemy, which reads instantly and leaves your own pool alone.
 *
 * THREE PERCENT, AND THAT IS NOT A TYPO. In an attrition fight between
 * otherwise equal fighters, a health edge compounds brutally: measured
 * over 600 fights per setting, 0.85/1.20 produced 98% / 50% / 0%, which
 * is a verdict handed down before the first swing. 0.97/1.03 produces
 * 65% / 50% / 31% -- the same spirit as the 70/50/30 the coin flip used
 * to pay, except now a player can out-fight it.
 *
 * ANYONE WIDENING THESE SHOULD RE-MEASURE RATHER THAN REASON. "Three
 * percent is nothing, make it twenty" is the intuition, and it is wrong
 * by about fifty points of win rate.
 */
if (!defined('GAUNT_FOE_HP_GOOD'))    define('GAUNT_FOE_HP_GOOD',    0.97);
if (!defined('GAUNT_FOE_HP_EVEN'))    define('GAUNT_FOE_HP_EVEN',    1.00);
if (!defined('GAUNT_FOE_HP_BAD'))     define('GAUNT_FOE_HP_BAD',     1.03);

/* Bare hands. Always available, deliberately weak -- a player with no
   items must still be able to play, just slowly. */
if (!defined('GAUNT_BARE_MIN'))       define('GAUNT_BARE_MIN',       7);
if (!defined('GAUNT_BARE_MAX'))       define('GAUNT_BARE_MAX',       11);

/*
 * A weapon's bite: FLAT + level * PER_LEVEL, give or take SPREAD. Weapon
 * levels run 1..10 (melee through artillery), so this is 8 at the bottom
 * and 17 at the top against a 120hp opponent, with bare hands averaging 9.
 *
 * DELIBERATELY COMPRESSED. The first pass had the top weapon at four
 * times bare, which simulated out as a level-4 opponent beating an
 * empty-handed player 100% of the time in five turns -- and fights of
 * four turns have no room for a decision in them. A weapon is a BURST
 * over few swings, not a different league.
 */
if (!defined('GAUNT_WPN_FLAT'))       define('GAUNT_WPN_FLAT',       10);
if (!defined('GAUNT_WPN_PER_LEVEL'))  define('GAUNT_WPN_PER_LEVEL',  1);
if (!defined('GAUNT_WPN_SPREAD'))     define('GAUNT_WPN_SPREAD',     3);

/*
 * SWINGS PER RUN, NOT PER FIGHT, AND THE BIG ONES ARE SCARCE.
 *
 * This is the rule that stops "use the best weapon every turn", which
 * would make the choice no choice at all. Spending artillery on encounter
 * one means not having it on encounter three.
 */
if (!defined('GAUNT_WPN_USES_BASE'))  define('GAUNT_WPN_USES_BASE',  7);
if (!defined('GAUNT_WPN_USES_MIN'))   define('GAUNT_WPN_USES_MIN',   2);

/*
 * ARMOUR IS A POOL THAT SOAKS, NOT A PERCENTAGE.
 *
 * A pool depletes visibly, which is the point -- the player can see it
 * running out and decide whether to spend a turn on another piece.
 * Wearing it costs the turn it goes on.
 */
if (!defined('GAUNT_ARM_POOL_PER_LEVEL')) define('GAUNT_ARM_POOL_PER_LEVEL', 14);
/*
 * A SHARE OF EACH HIT, NOT A FLAT CAP, so something always lands.
 *
 * The first version soaked up to level x 2 from every hit, which at
 * level 5 is 10 -- more than a punch. Traced: bare hands against heavy
 * armour dealt literally zero, forever, and the fight could not progress.
 * A player whose attacks do nothing at all has no game, whatever the
 * numbers say.
 */
if (!defined('GAUNT_ARM_SOAK_SHARE'))     define('GAUNT_ARM_SOAK_SHARE',     0.6);

/* Heals restore this share of MAX health -- "heal by", not "restore to",
   or a 25% potion is useless above a quarter health. Keyed by the
   consumable's own percentage. */
if (!defined('GAUNT_HEAL_FRACTION'))  define('GAUNT_HEAL_FRACTION',  0.01);  /* x pct */

/* The opponent heals when it drops below this, if its template allows. */
if (!defined('GAUNT_FOE_HEAL_AT'))    define('GAUNT_FOE_HEAL_AT',    0.40);
if (!defined('GAUNT_FOE_HEAL_PCT'))   define('GAUNT_FOE_HEAL_PCT',   40);

/* A fight that cannot end is a bug, not a stalemate. Two bare-handed
   fighters at ~9 a turn against 120hp need about 27 turns, so this is
   generous and only ever catches a loop. */
if (!defined('GAUNT_MAX_TURNS'))      define('GAUNT_MAX_TURNS',      60);

/*
 * How far above the player's own best gear an opponent may be armed.
 *
 * ZERO: you never face better than you can field. Measured at a margin of
 * one, a player holding literally nothing won 4% against a capped
 * opponent -- and at 4% per encounter they cannot bank the single win
 * that pays the smallest chest, which is the closed loop the chest exists
 * to break. At zero that same player faces their own equal and the fight
 * is even. Opponents get harder as the player does, which is the only
 * difficulty curve that needs no notion of who counts as new.
 */
if (!defined('GAUNT_FOE_CAP_MARGIN')) define('GAUNT_FOE_CAP_MARGIN', 0);

/* ===================== PRNG ===========================================
 * The fight carries its own seed so a resolution can be replayed exactly.
 * Same generator as dhca_rand() in dhcarena-engine.php -- duplicated
 * rather than shared because that file is the Arena's and pulling it in
 * here would couple two games that have nothing else in common.
 * ===================================================================== */

function gaunt_rand(&$f) {
	$f['seed'] = ($f['seed'] + 0x6D2B79F5) & 0xFFFFFFFF;
	$t = $f['seed'];
	$t = (gaunt_imul($t ^ ($t >> 15), $t | 1)) & 0xFFFFFFFF;
	$t ^= ($t + gaunt_imul($t ^ ($t >> 7), $t | 61)) & 0xFFFFFFFF;
	return (($t ^ ($t >> 14)) & 0xFFFFFFFF) / 4294967296;
}
function gaunt_imul($a, $b) {
	$a &= 0xFFFFFFFF; $b &= 0xFFFFFFFF;
	$ah = ($a >> 16) & 0xFFFF; $al = $a & 0xFFFF;
	return ((($al * $b) + ((($ah * $b) & 0xFFFF) << 16)) & 0xFFFFFFFF);
}
/** Inclusive integer in [$lo,$hi]. */
function gaunt_rint(&$f, $lo, $hi) {
	if ($hi <= $lo) return (int)$lo;
	return (int)$lo + (int)floor(gaunt_rand($f) * (($hi - $lo) + 1));
}

/* ===================== SHAPE ==========================================
 * How many swings a weapon of this level gets for a whole run.
 * ===================================================================== */
function gaunt_weapon_uses($level) {
	$level = max(1, (int)$level);
	return max(GAUNT_WPN_USES_MIN, GAUNT_WPN_USES_BASE - $level);
}

/**
 * CAP THE OPPONENT'S TEMPLATE AGAINST WHAT THE PLAYER CAN FIELD.
 *
 * The kit comes from the opposing PLAYER's inventory, which is what makes
 * a stocked rival a harder fight. Uncapped, that is also the whole
 * difficulty curve: simulated, an empty-handed player against a level-8
 * weapon with armour and a heal wins 0% of the time, and so does a
 * level-6 player. Matchmaking luck would decide whether a run was
 * playable at all.
 *
 * So the rule is simply: YOU NEVER FACE BETTER THAN YOU CAN FIELD, PLUS A
 * MARGIN. It is explainable in one sentence, it scales with the player
 * instead of with the calendar, and it needs no separate notion of who
 * counts as new.
 */
function gaunt_cap_kit($kit, $best_weapon, $best_armor, $player_has_heal) {
	$m = GAUNT_FOE_CAP_MARGIN;
	return array(
		'weapon_level' => min((int)($kit['weapon_level'] ?? 0), max(0, (int)$best_weapon) + $m),
		'armor_level'  => min((int)($kit['armor_level']  ?? 0), max(0, (int)$best_armor)  + $m),
		/* The free +30% health a heal is worth is the single biggest item
		   in the kit, so it is gated on the player holding one at all
		   rather than on a level. */
		'can_heal'     => !empty($kit['can_heal']) && (bool)$player_has_heal,
	);
}

/** The opponent's health for this fight, from the 70/50/30 matchup. */
function gaunt_foe_hp($win_chance) {
	$wc = (int)$win_chance;
	if ($wc >= 70) $mult = GAUNT_FOE_HP_GOOD;
	elseif ($wc <= 30) $mult = GAUNT_FOE_HP_BAD;
	else $mult = GAUNT_FOE_HP_EVEN;
	return (int)round(GAUNT_HP_BASE * $mult);
}

/**
 * Open a fight.
 *
 * $player  ['hp' => carried-over health, 'max' => its maximum]
 * $kit     what the OPPONENT is allowed to do, derived from that player's
 *          inventory -- ['weapon_level'=>int, 'armor_level'=>int,
 *          'can_heal'=>bool]. Nothing of theirs is ever consumed; it is a
 *          template for allowed actions, so a stocked rival is a harder
 *          fight and a dormant account is an easy one.
 */
function gaunt_begin($player, $win_chance, $kit, $seed) {
	$foeHp = gaunt_foe_hp($win_chance);
	return array(
		'seed'  => (int)$seed & 0xFFFFFFFF,
		'turn'  => 0,
		'wc'    => (int)$win_chance,
		'you'   => array(
			'hp'  => max(1, (int)$player['hp']),
			'max' => max(1, (int)$player['max']),
			'arm' => 0,          /* soak pool remaining */
			'armLevel' => 0,
		),
		'foe'   => array(
			'hp'  => $foeHp,
			'max' => $foeHp,
			'arm' => 0,
			'armLevel' => 0,
			'kit' => array(
				'weapon_level' => max(0, (int)($kit['weapon_level'] ?? 0)),
				'armor_level'  => max(0, (int)($kit['armor_level']  ?? 0)),
				'can_heal'     => !empty($kit['can_heal']),
				'healed'       => 0,
				/* ONCE. Without this the opponent re-applied armour
				   every time its pool emptied, which is unlimited
				   mitigation -- traced as a mirror match the player
				   lost 100% of the time. The player's armour is
				   limited by what they actually hold. */
				'armored'      => 0,
				/*
				 * THE OPPONENT'S SWINGS ARE SCARCE TOO, from the same
				 * formula as the player's. Without this it swings its
				 * best weapon every single turn for free while the
				 * player is rationing theirs, which made a level-4
				 * template beat a bare-handed player 100% of the time
				 * in five turns. Measured, not guessed -- the balance
				 * report is what found it.
				 */
				'swings'       => $kit['weapon_level'] ? gaunt_weapon_uses($kit['weapon_level']) : 0,
			),
		),
		'log'   => array(),
		'over'  => false,
		'win'   => null,
	);
}

/*
 * WHO SWINGS FIRST IS ROLLED, and that is not a detail.
 *
 * With the player always acting first, two identical bare-handed
 * fighters came out 73/27 to the player -- measured. A turn-order bias
 * that large swamps the matchup, which is only meant to be worth about
 * 0.85 to 1.20 on the opponent's health, and it would make every other
 * number in this file a lie. Rolled from the fight's own seed, so it is
 * still replayable.
 */
function gaunt_init_first(&$f) {
	if (isset($f['first'])) return;
	$f['first'] = (gaunt_rand($f) < 0.5) ? 'foe' : 'you';
	if ($f['first'] === 'foe') {
		gaunt_say($f, 'foe', 'init', 'moves first', array('sfx' => ''));
		gaunt_foe_turn($f);
		gaunt_check_over($f);
	}
}

/* ===================== RESOLUTION ===================================== */

/** Push one timeline entry. The client animates from these and nothing else. */
function gaunt_say(&$f, $who, $kind, $text, $extra = array()) {
	$e = array('who' => $who, 'kind' => $kind, 'text' => $text);
	foreach ($extra as $k => $v) $e[$k] = $v;
	$f['log'][] = $e;
}

/**
 * Land damage on a side, armour first.
 *
 * ARMOUR SOAKS A SHARE OF EACH HIT AND LOSES THAT MUCH POOL, so it wears
 * out faster against heavy weapons -- which is the behaviour people
 * expect and the reason it is a pool rather than a percentage. A share
 * rather than a flat cap, so a weak attack is blunted but never nullified.
 */
function gaunt_damage(&$f, $side, $amount) {
	$amount = max(0, (int)$amount);
	$soaked = 0;
	if ($f[$side]['arm'] > 0 && $amount > 0) {
		$cap    = (int)floor($amount * GAUNT_ARM_SOAK_SHARE);
		$soaked = max(0, min($f[$side]['arm'], $cap));
		$f[$side]['arm']    -= $soaked;
		$amount             -= $soaked;
		if ($f[$side]['arm'] <= 0) $f[$side]['armLevel'] = 0;
	}
	$f[$side]['hp'] = max(0, $f[$side]['hp'] - $amount);
	return array('dealt' => $amount, 'soaked' => $soaked);
}

function gaunt_check_over(&$f) {
	if ($f['you']['hp'] <= 0) { $f['over'] = true; $f['win'] = 'foe'; }
	elseif ($f['foe']['hp'] <= 0) { $f['over'] = true; $f['win'] = 'you'; }
	elseif ($f['turn'] >= GAUNT_MAX_TURNS) {
		/* Whoever is further ahead on remaining share takes it. A draw is
		   not a state the run can hold, and a fight that never ends is a
		   bug rather than a stalemate. */
		$f['over'] = true;
		$yr = $f['you']['hp'] / max(1, $f['you']['max']);
		$fr = $f['foe']['hp'] / max(1, $f['foe']['max']);
		$f['win'] = ($yr >= $fr) ? 'you' : 'foe';
	}
	return $f['over'];
}

/**
 * One exchange: the player's action, then the opponent's reply if it is
 * still standing. Returns the entries added this turn.
 *
 * $act  ['type' => 'bare'|'weapon'|'armor'|'heal',
 *        'level' => weapon/armour level, 'pct' => heal percentage,
 *        'name' => what to call it, 'sfx' => the cue to play]
 */
function gaunt_act(&$f, $act) {
	if (!empty($f['over'])) return array();
	$from = count($f['log']);
	gaunt_init_first($f);
	if (!empty($f['over'])) return array_slice($f['log'], $from);
	$f['turn']++;
	$type = (string)($act['type'] ?? 'bare');
	$name = (string)($act['name'] ?? '');

	if ($type === 'armor') {
		$lv = max(1, (int)($act['level'] ?? 1));
		$f['you']['arm']      = $lv * GAUNT_ARM_POOL_PER_LEVEL;
		$f['you']['armLevel'] = $lv;
		gaunt_say($f, 'you', 'armor', ($name !== '' ? $name : 'Armour') . ' on',
			array('sfx' => 'equip', 'pool' => $f['you']['arm']));

	} elseif ($type === 'heal') {
		$pct  = max(1, (int)($act['pct'] ?? 25));
		$gain = (int)round($f['you']['max'] * $pct * GAUNT_HEAL_FRACTION);
		$before = $f['you']['hp'];
		$f['you']['hp'] = min($f['you']['max'], $f['you']['hp'] + $gain);
		gaunt_say($f, 'you', 'heal', '+' . ($f['you']['hp'] - $before),
			array('sfx' => 'heal', 'amount' => $f['you']['hp'] - $before));

	} else {
		if ($type === 'weapon') {
			$lv  = max(1, (int)($act['level'] ?? 1));
			$mid = GAUNT_WPN_FLAT + $lv * GAUNT_WPN_PER_LEVEL;
			$dmg = gaunt_rint($f, $mid - GAUNT_WPN_SPREAD, $mid + GAUNT_WPN_SPREAD);
			$sfx = (string)($act['sfx'] ?? 'melee');
		} else {
			$dmg = gaunt_rint($f, GAUNT_BARE_MIN, GAUNT_BARE_MAX);
			$sfx = 'fist';
			$name = 'Bare hands';
		}
		$r = gaunt_damage($f, 'foe', $dmg);
		gaunt_say($f, 'you', 'hit', '-' . $r['dealt'],
			array('sfx' => $sfx, 'amount' => $r['dealt'], 'soaked' => $r['soaked'],
			      'name' => $name, 'big' => ($r['dealt'] >= 25)));
	}

	if (gaunt_check_over($f)) return array_slice($f['log'], $from);

	gaunt_foe_turn($f);
	gaunt_check_over($f);
	return array_slice($f['log'], $from);
}

/**
 * The opponent's reply, chosen from its template.
 *
 * Deliberately simple and deliberately not stupid: heal when badly hurt,
 * put armour on once, otherwise swing. It does not need to be clever --
 * the interest is in the player's resource decisions, and an opponent
 * that outplays you with hidden information is not fun, it is unfair.
 */
function gaunt_foe_turn(&$f) {
	$kit = $f['foe']['kit'];

	/* ARMOUR FIRST, as any sensible player would. Leaving it until after
	   the heal check meant the opponent spent the opening turns naked and
	   lost a mirror match 99 times in 100 -- measured. */
	if ($kit['armor_level'] > 0 && $f['foe']['armLevel'] === 0 && $kit['armored'] < 1) {
		$lv = $kit['armor_level'];
		$f['foe']['kit']['armored'] = 1;
		$f['foe']['arm']      = $lv * GAUNT_ARM_POOL_PER_LEVEL;
		$f['foe']['armLevel'] = $lv;
		gaunt_say($f, 'foe', 'armor', 'Armour on',
			array('sfx' => 'equip', 'pool' => $f['foe']['arm']));
		return;
	}

	if ($kit['can_heal'] && $kit['healed'] < 1
	    && $f['foe']['hp'] < $f['foe']['max'] * GAUNT_FOE_HEAL_AT) {
		$gain = (int)round($f['foe']['max'] * GAUNT_FOE_HEAL_PCT * GAUNT_HEAL_FRACTION);
		$before = $f['foe']['hp'];
		$f['foe']['hp'] = min($f['foe']['max'], $f['foe']['hp'] + $gain);
		$f['foe']['kit']['healed'] = 1;
		gaunt_say($f, 'foe', 'heal', '+' . ($f['foe']['hp'] - $before),
			array('sfx' => 'heal', 'amount' => $f['foe']['hp'] - $before));
		return;
	}

	if ($kit['weapon_level'] > 0 && $f['foe']['kit']['swings'] > 0) {
		$lv  = $kit['weapon_level'];
		$f['foe']['kit']['swings']--;
		$mid = GAUNT_WPN_FLAT + $lv * GAUNT_WPN_PER_LEVEL;
		$dmg = gaunt_rint($f, $mid - GAUNT_WPN_SPREAD, $mid + GAUNT_WPN_SPREAD);
		$sfx = 'melee';
	} else {
		$dmg = gaunt_rint($f, GAUNT_BARE_MIN, GAUNT_BARE_MAX);
		$sfx = 'fist';
	}
	$r = gaunt_damage($f, 'you', $dmg);
	gaunt_say($f, 'foe', 'hit', '-' . $r['dealt'],
		array('sfx' => $sfx, 'amount' => $r['dealt'], 'soaked' => $r['soaked'],
		      'big' => ($r['dealt'] >= 25)));
}
