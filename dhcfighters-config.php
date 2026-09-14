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

/** TEMPORARY, retires per torso as armless variants land. See the assembler. */
define('DHCF_ARMS_BEHIND_TORSO', array('perforator-arm-replacement'));

/** Vertical nudge in pixels of the 1000px master, positive = down. */
define('DHCF_NUDGE', array('skull-krusher' => 23, 'skull-krusher-sash' => 13, 'axe' => 13));

/** Weapons drawn against the torso's own arms; cannot coexist with Arms. */
define('DHCF_ARMS_EXCLUSIVE', array('plastic-blaster', 'dh-raider-equipment', 'electric-morning-star'));

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
	'cryptcrawl'     => array('label' => 'Crypt Crawl', 'url' => 'cryptcrawlgame.php',     'category' => 'weapon',     'trigger' => 'win',
	                          'base' => 'placement_3'),
	'cryptconquest'  => array('label' => 'Crypt Conquest', 'url' => 'cryptconquestgame.php',  'category' => 'headgear',   'trigger' => 'win',
	                          'base' => 'placement_10'),
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
	// NOT A GAME, deliberately in this list anyway: it is a drop source, and
	// everything that reads DHCF_GAMES -- the claim endpoint, the notifier, the
	// "where traits drop" table -- should see it without special-casing.
	// Aimed at the members who claim dailies and run missions but never open a
	// game; a wildcard is the right lure because it can be any trait at all.
	'dailystreak'    => array('label' => 'Daily Reward Streak', 'url' => 'launchpad.php', 'category' => 'wildcard',
	                          'trigger' => 'complete a 7-day streak', 'base' => 'placement_3'),
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
	'cryptcrawl'    => 0,      // win only
	'cryptconquest' => 0,      // win only
	'gauntlets'     => 0,      // win only
	'guardians'     => 10,     // waves held; board shows 12-81
	'monstrocity'   => 28,     // campaign completion
	'bosses'        => 0,      // the defeat itself qualifies; nothing to grade
	'dailystreak'   => 7,      // day 7 of 7, nothing earlier
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
