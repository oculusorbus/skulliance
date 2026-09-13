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
	'skullswap'      => array('label' => 'Skull Swap',      'category' => 'torso',      'trigger' => 'score threshold'),
	'skullracer'     => array('label' => 'Skull Racer',     'category' => 'head',       'trigger' => 'finish the race'),
	'obscura'        => array('label' => 'Obscura',         'category' => 'background', 'trigger' => 'finish a run of 10+ solves'),
	'cryptcrawl'     => array('label' => 'Crypt Crawl',     'category' => 'weapon',     'trigger' => 'win'),
	'cryptconquest'  => array('label' => 'Crypt Conquest',  'category' => 'headgear',   'trigger' => 'win'),
	'gauntlets'      => array('label' => 'Gauntlets',       'category' => 'effects',    'trigger' => 'win'),
	'guardians'      => array('label' => 'Realm Guardians', 'category' => 'companion',  'trigger' => 'waves held'),
	'monstrocity'    => array('label' => 'Monstrocity',     'category' => 'arms',       'trigger' => 'complete all 28 levels'),
	'bosses'         => array('label' => 'Boss Battles',    'category' => 'wildcard',   'trigger' => 'every boss defeat', 'gated' => true),
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
	'bosses'        => 100,    // damage contributed to the defeated boss
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

/** Tier table for a leaderboard position (1-based); null for a routine run. */
function dhcf_tier_table($placement = null) {
	if ($placement === null || $placement < 1) return DHCF_TIERS['run'];
	if ($placement == 1)  return DHCF_TIERS['placement_1'];
	if ($placement <= 3)  return DHCF_TIERS['placement_3'];
	if ($placement <= 10) return DHCF_TIERS['placement_10'];
	return DHCF_TIERS['run'];
}

/* ------------------------------------------------------------------ *
 * DAILY CEILING
 *
 * An anti-abuse ceiling, not a balancing dial. At current volumes -- three or
 * four players per board logging a handful of runs a week -- no honest player
 * will ever reach it.
 * ------------------------------------------------------------------ */
define('DHCF_DAILY_CAP', 3);
