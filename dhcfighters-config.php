<?php
/**
 * DHC FIGHTERS -- tunables
 *
 * Everything a designer would want to change without reading logic lives here:
 * which game pays which category, how good a drop is for a given achievement,
 * and how Fighters are numbered.
 */

/* ------------------------------------------------------------------ *
 * NUMBERING
 *
 * The minted collection is DHC2F001..DHC2F420 -- 226 actually minted, so the
 * range is full of holes. Those holes are NOT reused: they belong to the
 * collection if it ever completes, and reusing them would create a saved
 * assembly whose number later collides with a real mint.
 *
 * Assemblies continue the collection's own naming, starting after the top of
 * the minted range. They are NOT NFTs and cannot be minted -- the restriction
 * is carried by the interface wording rather than by the serial, which keeps
 * the numbering unbroken.
 * ------------------------------------------------------------------ */
define('DHCF_SERIAL_PREFIX', 'DHC2F');
define('DHCF_SERIAL_START',  421);          // first number after the collection's range
define('DHCF_SERIAL_PAD',    3);            // DHC2F421, DHC2F1000 when it gets there

/**
 * TEMPORARILY NOT DROPPING.
 *
 * Every torso is drawn with arms, so an Arms trait overlays limbs that are
 * already there. Most arms sit close enough to cover what is beneath; these
 * six do not, and read as two sets of arms until the hand-made armless torso
 * variants exist.
 *
 * Suspended from the DRAW only -- not removed. A player already holding one
 * keeps it, can still place it and still scores for it: taking back an awarded
 * trait would be far worse than an imperfect render. They simply stop being
 * handed out.
 *
 * EMPTIED 2026-09-15. All 25 armless torsos are hand-made and live, and each
 * of the six was checked against them before being let go: the four that
 * replace both limbs cover 78-100% of what is beneath, Head Chopper replaces
 * one and routes through the hybrid torso, and the Perforator covers neither
 * because it is drawn behind as an accent. See dhcf_armless_mode().
 *
 * Kept as the mechanism, not deleted. Add a slug here to pull a trait from the
 * drop pool without taking it away from anyone who already holds one.
 */
/**
 * Maxingo's project id. His own missions pay his own trait art, which is the
 * neatest fit on the platform -- and unlike the games, missions are something
 * the mission-and-dailies players already do.
 */
define('DHCF_MAXINGO_PROJECT', 9);

define('DHCF_SUSPENDED', array());

/**
 * ORIGINALITY BONUS -- what the first staker to build a configuration earns.
 *
 * A bonus for discovery, never a penalty for duplication. If a copied Fighter
 * lost points, the original builder would be marked down by somebody else's
 * action, which is the same unfairness that made blocking duplicate builds the
 * wrong answer: it punishes draw order rather than effort.
 *
 * 15% is enough to matter against scores in the hundreds without letting a
 * plain-but-unique build beat a genuinely rare one.
 */
define('DHCF_ORIGINALITY_BONUS', 0.15);

/** Bumped when a layering or exclusion rule changes; stamped on each save. */
define('DHCF_RULES_VERSION', '1');

/* ------------------------------------------------------------------ *
 * SLOTS
 *
 * Ten render slots, eight trait categories. Both weapon slots draw from the
 * same category, as do both effects slots -- owning a trait lets you place it
 * in either, subject to the front/back partition the assembler enforces.
 * ------------------------------------------------------------------ */
function dhcf_slots() {
	return array('background', 'weaponBack', 'torso', 'weapon', 'arms',
	             'effects1', 'effects2', 'head', 'headgear', 'companion');
}

function dhcf_slot_category($slot) {
	switch ($slot) {
		case 'weapon': case 'weaponBack': return 'weapon';
		case 'effects1': case 'effects2': return 'effects';
		default: return $slot;
	}
}

/*
 * A FIGHTER NEEDS A BODY. Background, torso and head are mandatory; everything
 * else is decoration. This is also why those three categories only drop from
 * games that cannot be lost -- gating a required part behind a win could leave
 * a player permanently unable to save anything.
 */
define('DHCF_REQUIRED', array('background', 'torso', 'head'));

/** Required slots the layout is missing, by category name. Empty means valid. */
function dhcf_missing_required($traits) {
	$missing = array();
	foreach (DHCF_REQUIRED as $slot) {
		if (empty($traits[$slot])) $missing[] = $slot;
	}
	return $missing;
}

function dhcf_categories() {
	return array('background', 'torso', 'head', 'headgear', 'effects', 'arms', 'weapon', 'companion');
}

/** Human label for a trait, from the art index when present. */
function dhcf_trait_name($category, $slug) {
	static $idx = null;
	if ($idx === null) {
		$idx = array();
		foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
			$f = __DIR__ . '/' . $c . '/trait-index.json';
			if (is_file($f)) { $idx = json_decode(file_get_contents($f), true) ?: array(); break; }
		}
	}
	if (isset($idx[$category][$slug]['name'])) return $idx[$category][$slug]['name'];
	return ucwords(str_replace('-', ' ', $slug));
}

/* ------------------------------------------------------------------ *
 * LAYERING RULES -- the single source.
 *
 * The assembler emits these into its JS and the server-side compositor reads
 * them directly, so the picture Discord shows is drawn by the same rules as
 * the picture the player assembled. Two copies of this would drift, and every
 * one of these exceptions was expensive to find.
 * ------------------------------------------------------------------ */

/** Companions that belong against the body, drawn below Arms rather than last. */
define('DHCF_COMPANION_UNDER', array('dh-vision-shoulder-cam', 'code-sea-predator'));

/** Effects that read as environment: dropped to just behind the torso. */
define('DHCF_EFFECTS_BEHIND_TORSO', array('xlon-s-black-fire-attack'));

/**
 * Arms drawn BEHIND the torso: accents flanking the body, not replacements for
 * its arms. Permanent -- the Perforator's cutoffs are not clean enough to sit
 * over an armless torso, and it reads better as an accent anyway. Members are
 * exempt from the armless-torso swap; see dhc-assembler.php.
 */
define('DHCF_ARMS_BEHIND_TORSO', array('perforator-arm-replacement'));

/**
 * SINGLE-SIDED ARMS -- arms that replace one limb, not both.
 *
 * Head Chopper only covers the image-LEFT arm. Swapping in the armless torso
 * for it therefore deletes the other arm and leaves a stump, but drawing it
 * over the normal torso does not work either: measured against the armless
 * variants it covers only 80-88% of the arm beneath it, so the original shows
 * around the edge.
 *
 * So the torso is drawn as TWO layers: the armless variant, then the NORMAL
 * torso clipped to the half this trait does not cover, which puts that one arm
 * back. No new art -- the body is pixel-identical in both files, so the join is
 * invisible provided it falls between the arms rather than through one.
 *
 * The value is which half of the normal torso to keep.
 */
define('DHCF_ONE_ARM', array('head-chopper' => 'right'));

/**
 * Where that join sits, as a percentage of width. The centre: across all 25
 * torsos the left arm always ends by x=299 and the right never starts before
 * x=723 (of 1000), so 50% clears both by ~200px. Any value in that window
 * works; there is nothing to tune per torso.
 */
define('DHCF_ONE_ARM_SPLIT', 50);

/**
 * Arms that sit cleanly OVER a normal torso, so the armless variant is not
 * used at all. Infested Robo Limb is one: it replaces a single limb and covers
 * what is under it well enough that the torso's own arms can stay.
 *
 * The difference from DHCF_ONE_ARM is what happens to the OTHER arm. Head
 * Chopper needs the armless torso because its own silhouette does not hide the
 * limb beneath, so that limb is deleted and the far one restored; these keep
 * both of the torso's arms and simply cover one.
 */
define('DHCF_ARMS_OVER_TORSO', array('infested-robo-limb'));

/**
 * HOW AN ARMS TRAIT TREATS THE TORSO BENEATH IT. One answer for every renderer
 * -- the canvas, the Discord render and the card grids all have to agree, and
 * they were each growing their own copy of these conditions.
 *
 *   'full'    swap in the armless torso: the arm replaces both limbs
 *   'hybrid'  armless torso, plus the normal torso's other half put back
 *   'none'    leave the torso alone; it keeps its own arms
 */
function dhcf_armless_mode($arms) {
	if (empty($arms))                                        return 'none';
	if (in_array($arms, DHCF_ARMS_BEHIND_TORSO, true))       return 'none';
	if (in_array($arms, DHCF_ARMS_OVER_TORSO, true))         return 'none';
	if (isset(DHCF_ONE_ARM[$arms]))                          return 'hybrid';
	return 'full';
}

/** Vertical nudge in pixels of the 1000px master, positive = down. */
define('DHCF_NUDGE', array('skull-krusher' => 23, 'skull-krusher-sash' => 13, 'axe' => 13));

/** Weapons drawn against the torso's own arms; cannot coexist with Arms. */
define('DHCF_ARMS_EXCLUSIVE', array('plastic-blaster', 'dh-raider-equipment', 'electric-morning-star'));

/*
 * HEADGEAR A GIVEN HEAD CANNOT WEAR.
 *
 * Keyed by head slug, listing the headgear that will not sit on it. Beheaded
 * Cyborg is the case that created this: there is no skull under the headgear to
 * carry these pieces, so they read as floating rather than worn. Not every piece
 * fails -- most of the 32 still work -- so this is an explicit list per head
 * rather than a blanket "headless heads wear nothing" rule.
 *
 * Enforced in three places, which is why it lives here rather than in any one of
 * them: dhcf_layers() for every PHP renderer, the canvas's own paint() in
 * dhc-assembler.php, and blockedReason() in the picker so the pairing cannot be
 * made in the first place. A Fighter saved BEFORE a rule lands still holds the
 * pairing -- the ledger is the record and its traits are not at risk -- so the
 * renderers have to drop the layer rather than assume it can never occur.
 */
define('DHCF_HEADGEAR_EXCLUDED_BY_HEAD', array(
	'beheaded-cyborg' => array(
		'merged',
		'steel-viking-helmet',
		'666-demon-headgear',
		'dh-fire-goggles',
		'killer-phantom-mask',
		'ww2-helmet',
		'arachno-neural-implant',
		'code-prisoner-helmet',
		'dm-mask',
		'golden-cyber-plague-detecting-mask',
		'mk200-cyber-plague-vr-mask',
		'trojan-detection-mask',
		'xlon-implant',
		'malware-detecting-mask',
	),
));

/** Whether $headgear is refused by $head. Empty either side is never blocked. */
function dhcf_headgear_blocked($head, $headgear) {
	if (empty($head) || empty($headgear)) return false;
	$excluded = DHCF_HEADGEAR_EXCLUDED_BY_HEAD;
	return isset($excluded[$head]) && in_array($headgear, $excluded[$head], true);
}

/**
 * Draw order for a saved layout, exceptions applied. Mirrors layerOrder() in
 * the assembler's JS -- same inputs, same output, minus the drag handling the
 * server has no use for.
 */
function dhcf_layer_order($traits) {
	$order = dhcf_slots();

	if (!empty($traits['companion']) && in_array($traits['companion'], DHCF_COMPANION_UNDER, true)) {
		$order = dhcf_move_before($order, 'companion', 'arms');
	}
	if (!empty($traits['arms']) && in_array($traits['arms'], DHCF_ARMS_BEHIND_TORSO, true)) {
		$order = dhcf_move_before($order, 'arms', 'torso');
	}
	foreach (array('effects1', 'effects2') as $k) {
		if (!empty($traits[$k]) && in_array($traits[$k], DHCF_EFFECTS_BEHIND_TORSO, true)) {
			$order = dhcf_move_before($order, $k, 'torso');
		}
	}
	return $order;
}

/** Move $what immediately before $before, preserving everything else. */
function dhcf_move_before($order, $what, $before) {
	$out = array();
	foreach ($order as $k) { if ($k !== $what) $out[] = $k; }
	$at = array_search($before, $out, true);
	if ($at === false) return $order;
	array_splice($out, $at, 0, array($what));
	return $out;
}

/* ------------------------------------------------------------------ *
 * WHICH GAME PAYS WHICH CATEGORY
 *
 * The organising rule: a MANDATORY trait may only come from a game that cannot
 * be lost. Torso, Background and Head are needed by every Fighter, so gating
 * them behind a win would let a player grind for months and still be unable to
 * finish a character -- and losing is the norm here (Crypt Conquest's number
 * two sits at 7 wins against 34 losses).
 *
 *   score games  -> the three mandatory categories
 *   winnable     -> optional categories, paid on the win
 *   gated        -> the wildcard only, so being locked out costs a bonus and
 *                   never a slot anyone needs
 * ------------------------------------------------------------------ */
$GLOBALS['DHCF_GAMES'] = array(
	// 'base'  the tier table a qualifying result rolls on before any bonus.
	//         Win-gated games start higher: on the live boards a Crypt Crawl
	//         week is typically 0-1 wins against 2 losses, so a win is already
	//         an achievement and should not roll the same table as a routine run.
	// 'bands' value => better table, for games where doing MORE should pay more.
	//         Without these a drop is binary, and publishing the floor teaches
	//         players to stop at it -- hold 10 waves, die, restart, which turns
	//         a 30-minute game into a 5-minute farm.
	// 'lower' set when a smaller value is better (race times).
	'skullswap'      => array('label' => 'Skull Swap', 'url' => 'skullswap.php',      'category' => 'torso',      'trigger' => 'score threshold',
	                          'base' => 'run',          'bands' => array(9000 => 'placement_10', 12000 => 'placement_3', 15000 => 'placement_1')),
	'skullracer'     => array('label' => 'Skull Racer', 'url' => 'skullracergame.php',     'category' => 'head',       'trigger' => 'finish the race',
	                          'base' => 'run',          'lower' => true,
	                          'bands' => array(400 => 'placement_10', 345 => 'placement_3', 330 => 'placement_1')),
	'obscura'        => array('label' => 'Obscura', 'url' => 'obscura.php',         'category' => 'background', 'trigger' => 'finish a run of 10+ solves',
	                          'base' => 'run',          'bands' => array(15 => 'placement_10', 25 => 'placement_3', 40 => 'placement_1')),
	// DEPTH, NOT THE WIN. Both card games used to pay only on a full clear, and
	// on the live boards that is roughly 0-1 wins against 2 losses a week -- so
	// Weapon and Headgear were shut to most players however much they played,
	// which is the one thing the category map is supposed to prevent.
	//
	// The qualifying depth is the last stretch of each game rather than a flat
	// number: 12 of Crawl's 15, 10 of Conquest's 12. Conquest is the shorter
	// game, so the same count would have been a much softer bar there -- these
	// are both four fifths of the way in, which is the thing being asked for.
	//
	// The bands still pay a full clear exactly what the win paid before, so
	// qualifying adds a roll and takes nothing off the win. Depth is also what
	// both leaderboards already rank on, so this grades what the games measure.
	'cryptcrawl'     => array('label' => 'Crypt Crawl', 'url' => 'cryptcrawlgame.php',     'category' => 'weapon',     'trigger' => 'clear 12 of the 15 crypts',
	                          'base' => 'run',          'bands' => array(13 => 'placement_10', 15 => 'placement_3')),
	'cryptconquest'  => array('label' => 'Crypt Conquest', 'url' => 'cryptconquestgame.php',  'category' => 'headgear',   'trigger' => 'defeat 10 of the 12 court cards',
	                          'base' => 'run',          'bands' => array(12 => 'placement_10')),
	'gauntlets'      => array('label' => 'Gauntlets', 'url' => 'gauntlets.php',       'category' => 'effects',    'trigger' => 'sweep the gauntlet',
	                          'base' => 'placement_10'),
	'guardians'      => array('label' => 'Realm Guardians', 'url' => 'guardiansgame.php', 'category' => 'companion',  'trigger' => 'waves held',
	                          'base' => 'run',          'bands' => array(20 => 'placement_10', 40 => 'placement_3', 70 => 'placement_1')),
	'monstrocity'    => array('label' => 'Monstrocity', 'url' => 'match3rpg.php',     'category' => 'arms',       'trigger' => 'complete all 28 levels',
	                          'base' => 'placement_1'),
	// No bands and no floor: killing a boss IS the achievement, and there is no
	// "how well" to grade it on. The bands here used to read damage contributed,
	// from when the trigger was a damage milestone -- against the match-3 score
	// actually passed now, they graded nothing and the old floor of 100 silently
	// refused low-scoring kills.
	'bosses'         => array('label' => 'Boss Battles', 'url' => 'monstrocity.php#boss',    'category' => 'wildcard',   'trigger' => 'every boss defeat', 'gated' => true,
	                          'base' => 'placement_10'),
	// Every successful Maxingo mission pays, at any level -- most stakers have
	// already unlocked all ten, so paying only first clears would shut them out.
	// The level decides the odds, since the deeper ones are harder to reach.
	// A wildcard, because a mission is not tied to one part of a Fighter.
	//
	// The farm is stopped by the daily cap, which dhcf_award() now enforces for
	// every source: a 100% success consumable makes completion certain and
	// returns the NFTs immediately, so without it MAXI would buy an unbounded
	// supply of best-table traits. Three a day however many missions are run.
	'maxingo'        => array('label' => 'Maxingo Missions', 'url' => 'missions.php',
	                          'category' => 'wildcard', 'trigger' => 'complete a mission',
	                          'base' => 'run',
	                          'bands' => array(4 => 'placement_10', 7 => 'placement_3', 9 => 'placement_1')),

	// REALMS RAIDS -- the first source that is not a solo game: the trait comes
	// out of a contest with another staker rather than a board. BOTH SIDES of a
	// decided raid pay, and they share ONE key deliberately, so the per-source
	// cap means three a day from Realms in total -- not three attacking plus
	// three defending.
	//
	// Gated: raiding needs a Realm, a trained army and a Portal, so a staker
	// without one is shut out completely. That is precisely the case the
	// wildcard exists for -- being locked out costs a bonus, never a slot.
	//
	// 'base' is the ROUTINE table, not a win-gated one. A raid win is nothing
	// like a Crypt Crawl win: evenly matched realms sit near 50%, and the
	// attacker chooses the target, so the floor here is a coin flip you picked.
	//
	// The bands read HOW FAR UP THE WINNER PUNCHED -- the loser's rating minus
	// their own -- which asks both sides the same question: how much stronger
	// was the realm you beat. It mirrors the loot rule (a weaker attacker takes
	// up to 9%, a stronger one is capped at 3%) and it stops "farm the softest
	// eligible realm" from being the optimal way to collect traits.
	//
	// Attackers reach the top band and defenders essentially cannot: startRaid()
	// refuses a target more than 3 defense levels below you, so a defender's gap
	// caps at +3 outside a revenge raid. That asymmetry is intended. Punching up
	// five levels is a deliberate long-odds choice; nobody chooses to be raided.
	'raids'          => array('label' => 'Realm Raids', 'url' => 'raids.php',
	                          'category' => 'wildcard', 'trigger' => 'win a raid, or repel one',
	                          'gated' => true, 'base' => 'run',
	                          'bands' => array(1 => 'placement_10', 3 => 'placement_3', 5 => 'placement_1')),

	// NOT A GAME, deliberately in this list anyway: it is a drop source, and
	// everything that reads DHCF_GAMES -- the claim endpoint, the notifier, the
	// "where traits drop" table -- should see it without special-casing.
	// Aimed at the members who claim dailies and run missions but never open a
	// game; a wildcard is the right lure because it can be any trait at all.
	// 'limit_note' replaces the "N of N left today" line where a daily count
	// does not describe the real limit. The streak pays once per seven days by
	// definition, so quoting a daily cap implies three are available today.
	'dailystreak'    => array('label' => 'Daily Reward Streak', 'url' => 'launchpad.php', 'category' => 'wildcard',
	                          'trigger' => 'complete a 7-day streak', 'base' => 'placement_3',
	                          'cap' => 1, 'limit_note' => 'once per 7-day streak'),
);

function dhcf_game($key) {
	return isset($GLOBALS['DHCF_GAMES'][$key]) ? $GLOBALS['DHCF_GAMES'][$key] : null;
}

/* ------------------------------------------------------------------ *
 * MINIMUM SCORE TO QUALIFY
 *
 * A run has to earn its roll, or quitting out repeatedly becomes the fastest
 * way to farm. Set well below what the live leaderboards currently show, so a
 * genuine attempt always qualifies -- these are floors, not targets.
 *
 * Derived from the top few players on each board, which is a biased sample;
 * if any floor ever excludes a real attempt it is too high.
 * ------------------------------------------------------------------ */
$GLOBALS['DHCF_FLOORS'] = array(
	'skullswap'     => 5000,   // board shows 11,870-14,400
	'skullracer'    => 0,      // finishing at all qualifies
	'obscura'       => 10,     // solves in a completed run; board shows streaks of 11-33
	'cryptcrawl'    => 12,     // crypts cleared, of 15; a win is 15
	'cryptconquest' => 10,     // court cards defeated, of 12; a win is 12
	'gauntlets'     => 0,      // win only
	'guardians'     => 10,     // waves held; board shows 12-81
	'monstrocity'   => 28,     // campaign completion
	'bosses'        => 0,      // the defeat itself qualifies; nothing to grade
	'dailystreak'   => 7,      // day 7 of 7, nothing earlier
	'maxingo'       => 1,      // any completed Maxingo mission; level sets the odds
	'raids'         => 0,      // the decided raid itself qualifies, won or repelled
);

function dhcf_floor($game) {
	return isset($GLOBALS['DHCF_FLOORS'][$game]) ? $GLOBALS['DHCF_FLOORS'][$game] : 0;
}

/* ------------------------------------------------------------------ *
 * HOW GOOD A DROP IS
 *
 * Placement drives QUALITY, not quantity. If topping a board multiplied both
 * the rate and the tier, strong players would compound away from everyone
 * else; this way the prize is real but the gap stays bounded.
 *
 * Every table keeps a non-zero mythic chance. A 0% would quietly tell most of
 * the base that the good traits are not for them, and the lottery feeling is
 * the whole engine.
 * ------------------------------------------------------------------ */
define('DHCF_TIERS', array(
	'run'         => array('common' => 55, 'uncommon' => 28, 'epic' => 13, 'legendary' => 3.5, 'mythic' => 0.5),
	'placement_10'=> array('common' => 38, 'uncommon' => 30, 'epic' => 21, 'legendary' => 9,   'mythic' => 2),
	'placement_3' => array('common' => 25, 'uncommon' => 30, 'epic' => 27, 'legendary' => 15,  'mythic' => 3),
	'placement_1' => array('common' => 15, 'uncommon' => 25, 'epic' => 30, 'legendary' => 22,  'mythic' => 8),
));

/** Rank the tables so "whichever is better" is a comparison, not a guess. */
function dhcf_table_rank($name) {
	$order = array('run' => 0, 'placement_10' => 1, 'placement_3' => 2, 'placement_1' => 3);
	return isset($order[$name]) ? $order[$name] : 0;
}

/** Table name earned by a leaderboard position (1-based), or 'run'. */
function dhcf_placement_table($placement = null) {
	if ($placement === null || $placement < 1) return 'run';
	if ($placement == 1)  return 'placement_1';
	if ($placement <= 3)  return 'placement_3';
	if ($placement <= 10) return 'placement_10';
	return 'run';
}

/**
 * Which tier table a result has earned: the BEST of the game's base table,
 * what its performance bands award, and what its leaderboard placement awards.
 *
 * Best-of rather than additive, so a player is never worse off for having done
 * well on two axes at once, and the ceiling stays the first-place table.
 */
function dhcf_table_for($key, $value = 0, $placement = null) {
	$g    = dhcf_game($key);
	$name = ($g && !empty($g['base'])) ? $g['base'] : 'run';

	if ($g && !empty($g['bands'])) {
		$lower = !empty($g['lower']);
		foreach ($g['bands'] as $threshold => $table) {
			$hit = $lower ? ($value > 0 && $value <= $threshold) : ($value >= $threshold);
			if ($hit && dhcf_table_rank($table) > dhcf_table_rank($name)) $name = $table;
		}
	}

	$p = dhcf_placement_table($placement);
	if (dhcf_table_rank($p) > dhcf_table_rank($name)) $name = $p;

	return DHCF_TIERS[$name];
}

/** The band a value has reached, for telling the player what they rolled on. */
function dhcf_band_label($key, $value = 0, $placement = null) {
	$g = dhcf_game($key);
	$name = ($g && !empty($g['base'])) ? $g['base'] : 'run';
	if ($g && !empty($g['bands'])) {
		$lower = !empty($g['lower']);
		foreach ($g['bands'] as $threshold => $table) {
			$hit = $lower ? ($value > 0 && $value <= $threshold) : ($value >= $threshold);
			if ($hit && dhcf_table_rank($table) > dhcf_table_rank($name)) $name = $table;
		}
	}
	$p = dhcf_placement_table($placement);
	if (dhcf_table_rank($p) > dhcf_table_rank($name)) $name = $p;
	$labels = array('run' => 'standard odds', 'placement_10' => 'improved odds',
	                'placement_3' => 'strong odds', 'placement_1' => 'best odds');
	return isset($labels[$name]) ? $labels[$name] : 'standard odds';
}

/* ------------------------------------------------------------------ *
 * DAILY CEILING
 *
 * An anti-abuse ceiling, not a balancing dial. At current volumes -- three or
 * four players per board logging a handful of runs a week -- no honest player
 * will ever reach it.
 * ------------------------------------------------------------------ */
define('DHCF_DAILY_CAP', 3);

/**
 * Daily cap for one source. Override per game by adding 'cap' to its
 * DHCF_GAMES entry; everything else falls back to the constant.
 *
 * The cap only binds on the short games -- a Guardians siege runs half an
 * hour, a Monstrocity campaign completes about once a month, and the daily
 * streak pays once a week by definition. It is an anti-abuse ceiling on the
 * few sources that could be ground, not a balancing dial for the rest.
 */
function dhcf_cap($key) {
	$g = dhcf_game($key);
	return ($g && isset($g['cap'])) ? (int)$g['cap'] : DHCF_DAILY_CAP;
}
