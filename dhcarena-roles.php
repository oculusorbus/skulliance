<?php
/**
 * dhcarena-roles.php — what an individual trait DOES, read from what it is.
 *
 * THE SLOT IS TOO BLUNT. Mapping a whole slot to a stat means a steel viking
 * helmet and a VR targeting mask do the same thing because they hang off the
 * same hook, which is plainly wrong and flattens a 197-piece collection into
 * eight numbers. Headgear is the clearest case: 13 of its 32 pieces are
 * literally helmets and 10 are optics, and those are opposite jobs.
 *
 * So the ROLE is inferred per trait from its own label, and the slot only
 * decides what a role is worth there. A laser cannon strapped to your shoulder
 * adds damage; a vision cam in the same slot adds precision. Two Fighters
 * wearing the same slots can want completely different things.
 *
 * WHY LABELS. The collection's slugs are descriptive because the artist named
 * them that way -- threat-seeker-antenna, cyber-knight-helmet,
 * deteriorated-mk100-blaster-arms. That is a real signal and it is free.
 * Anything the keywords cannot place falls to ROLE_PLAIN, which is deliberately
 * unexciting rather than a guess, and DHCA_ROLE_OVERRIDE is where a human
 * disagreement gets recorded permanently.
 *
 * Pure: no database, no session, no engine. dhcarena-harness.php and the
 * review dump both load it on its own.
 */

define('DHCA_ROLE_OPTIC',  'optic');    // sees: targeting, sensors, detection
define('DHCA_ROLE_ARMOUR', 'armour');   // takes a hit: helmets, plating, shields
define('DHCA_ROLE_WEAPON', 'weapon');   // deals one: blasters, blades, cannons
define('DHCA_ROLE_ENERGY', 'energy');   // charges: fire, lava, electricity
define('DHCA_ROLE_BEAST',  'beast');    // fights alongside: creatures, drones
define('DHCA_ROLE_PLAIN',  'plain');    // placed nothing -- earns a little of nothing

/**
 * Keyword => role, tried IN ORDER, first match wins.
 *
 * Order matters and is the whole design. "blaster-arms" is a weapon before it
 * is a limb; "cyber-knight-helmet" is armour before "cyber" means anything;
 * "detecting-mask" is an optic before "mask" is generic. So the specific and
 * the load-bearing come first, and the generic words sit at the bottom.
 */
function dhca_role_rules() {
	return array(
		// --- sees ------------------------------------------------------------
		'detect'     => DHCA_ROLE_OPTIC,   'detection' => DHCA_ROLE_OPTIC,
		'vision'     => DHCA_ROLE_OPTIC,   'seeker'    => DHCA_ROLE_OPTIC,
		'vigilance'  => DHCA_ROLE_OPTIC,   'antenna'   => DHCA_ROLE_OPTIC,
		'goggle'     => DHCA_ROLE_OPTIC,   'vr-'       => DHCA_ROLE_OPTIC,
		'scanner'    => DHCA_ROLE_OPTIC,   'radar'      => DHCA_ROLE_OPTIC,
		'-cam'       => DHCA_ROLE_OPTIC,
		'implant'    => DHCA_ROLE_OPTIC,   'neural'    => DHCA_ROLE_OPTIC,
		'circuit'    => DHCA_ROLE_OPTIC,   'scuba'     => DHCA_ROLE_OPTIC,
		'mask'       => DHCA_ROLE_OPTIC,
		// --- takes a hit -------------------------------------------------------
		'helmet'     => DHCA_ROLE_ARMOUR,  'armor'     => DHCA_ROLE_ARMOUR,
		'armour'     => DHCA_ROLE_ARMOUR,  'deflekt'   => DHCA_ROLE_ARMOUR,
		'protector'  => DHCA_ROLE_ARMOUR,  'shield'    => DHCA_ROLE_ARMOUR,
		'plate'      => DHCA_ROLE_ARMOUR,  'guard'     => DHCA_ROLE_ARMOUR,
		'headgear'   => DHCA_ROLE_ARMOUR,
		// --- deals one ---------------------------------------------------------
		'blaster'    => DHCA_ROLE_WEAPON,  'cannon'    => DHCA_ROLE_WEAPON,
		'turret'     => DHCA_ROLE_WEAPON,  'chopper'   => DHCA_ROLE_WEAPON,
		'crusher'    => DHCA_ROLE_WEAPON,  'stabber'   => DHCA_ROLE_WEAPON,
		'perforator' => DHCA_ROLE_WEAPON,  'destroyer' => DHCA_ROLE_WEAPON,
		'destruction'=> DHCA_ROLE_WEAPON,  'saber'     => DHCA_ROLE_WEAPON,
		'laser'      => DHCA_ROLE_WEAPON,  'attack'    => DHCA_ROLE_WEAPON,
		// --- charges -----------------------------------------------------------
		'flame'      => DHCA_ROLE_ENERGY,  'fire'      => DHCA_ROLE_ENERGY,
		'lava'       => DHCA_ROLE_ENERGY,  'spark'     => DHCA_ROLE_ENERGY,
		'electric'   => DHCA_ROLE_ENERGY,  'thunder'   => DHCA_ROLE_ENERGY,
		'inferno'    => DHCA_ROLE_ENERGY,  'voodoo'    => DHCA_ROLE_ENERGY,
		'sorceror'   => DHCA_ROLE_ENERGY,  'sorcerer'  => DHCA_ROLE_ENERGY,
		// 'demon-' with the hyphen, NOT 'demon': love-demonstration was being
		// classified as occult energy because the word demonstration contains it.
		'demon-'     => DHCA_ROLE_ENERGY,  'smoke'     => DHCA_ROLE_ENERGY,
		// --- fights alongside ---------------------------------------------------
		'isopunk'    => DHCA_ROLE_BEAST,   'insekt'    => DHCA_ROLE_BEAST,
		'arachn'     => DHCA_ROLE_BEAST,   'predator'  => DHCA_ROLE_BEAST,
		'wasp'       => DHCA_ROLE_BEAST,   'superbug'  => DHCA_ROLE_BEAST,
		'infested'   => DHCA_ROLE_BEAST,   'symbio'    => DHCA_ROLE_BEAST,
		'skull'      => DHCA_ROLE_BEAST,   'alien'     => DHCA_ROLE_BEAST,
		'pharaoh'    => DHCA_ROLE_BEAST,   'phantom'   => DHCA_ROLE_BEAST,
		// --- generic limbs, last, so a blaster-arm is never merely an arm --------
		'limb'       => DHCA_ROLE_WEAPON,  'arms'      => DHCA_ROLE_WEAPON,
		'-arm'       => DHCA_ROLE_WEAPON,
	);
}

/** Where the keywords get it wrong. Reviewed by hand; this wins over inference. */
function dhca_role_overrides() {
	return array(
		// "It's just a cool helmet, doesn't deal damage." The word destroyer is
		// in the name of the piece, not in what it does -- headgear you wear is
		// armour however frightening it is called.
		'dh-destroyer' => DHCA_ROLE_ARMOUR,
	);
}

/**
 * What this specific trait does.
 *
 * The slot is passed because one rule genuinely depends on it: a BEAST is
 * something fighting ALONGSIDE you, and a piece you WEAR is not, whatever it is
 * called. An alien skull worn on your head is a helmet -- the same reading that
 * makes dh-destroyer armour rather than a weapon. So in a worn slot the creature
 * roles fall back to what that slot is: headgear and head armour you, a torso
 * armours you. Only the companion, and an arm that is genuinely alive
 * (infested, symbio), can actually be a beast.
 */
function dhca_trait_role($slug, $slot = '') {
	$slug = strtolower((string)$slug);
	if ($slug === '') return DHCA_ROLE_PLAIN;

	$role = null;
	$over = dhca_role_overrides();
	if (isset($over[$slug])) $role = $over[$slug];
	if ($role === null) {
		foreach (dhca_role_rules() as $needle => $r) {
			if (strpos($slug, $needle) !== false) { $role = $r; break; }
		}
	}
	if ($role === null) $role = DHCA_ROLE_PLAIN;

	$worn = array('head' => 1, 'headgear' => 1, 'torso' => 1);
	if ($role === DHCA_ROLE_BEAST && $slot !== '' && isset($worn[$slot])) {
		return DHCA_ROLE_ARMOUR;
	}
	return $role;
}

/* ============================ THE ARENA ITSELF ==============================
   Which background you fight on is the defender's front-rank Fighter's, and
   that is deliberate. WHAT IT DOES used to be dhca_hash($slug) % 5 -- stable
   for a given background, and otherwise arbitrary. So a screen of fire could
   be captioned "Dense Cover, Shield gives 50% more", which is the same mistake
   as a helmet deciding how often you land a critical hit.

   The label decides now, exactly as it does for traits. Fire and explosions
   make a fight hotter; nebulae and wormholes lighten it; traps, webs and
   interiors give cover; circuits and signal attacks scramble aim; an empty
   colour field leaves nowhere to hide.

   AND THE NAME IS THE BACKGROUND'S OWN. Five invented labels could never match
   42 pieces of art, so the arena is announced by the name of the thing you are
   looking at -- Hellscape, Wormhole Passage, Advanced Circuit -- and only the
   EFFECT is inferred. A caption cannot contradict the picture if it is the
   picture's name.
   ========================================================================== */
function dhca_terrain_rules() {
	return array(
		// hotter
		'flame' => 'dmg', 'fire' => 'dmg', 'hellscape' => 'dmg', 'explosion' => 'dmg',
		'thunder' => 'dmg', 'sun' => 'dmg', 'quasar' => 'dmg', 'bright-red' => 'dmg',
		// lighter: space, and the long fall
		'nebula' => 'surge', 'wormhole' => 'surge', 'abyss' => 'surge',
		'exoplanet' => 'surge', 'spacecraft' => 'surge', 'black' => 'surge',
		// cover: something to actually get BEHIND. Kept deliberately small --
		// guard battles run 28 turns against 16 to 18 everywhere else, so the
		// arena that slows the game down should be the rare one, not the
		// commonest. Webs, traps, bubbles and smoke qualify; a building does
		// not, it just means the fight is indoors.
		'trap' => 'guard', 'web' => 'guard', 'bubble' => 'guard', 'smoke' => 'guard',
		// enclosed, not covered: nowhere to back away to, so everything lands
		'complex' => 'dmg', 'building' => 'dmg', 'interior' => 'dmg',
		'facility' => 'dmg', 'entrance' => 'dmg',
		// scrambled: signal, noise, interference
		'circuit' => 'crit', 'data' => 'crit', 'ddos' => 'crit', 'optical' => 'crit',
		'psi' => 'crit', 'tunnel' => 'crit', 'attack' => 'crit', 'digital' => 'crit',
		// exposed: an empty field, nothing to use
		'landscape' => 'frail', 'light-' => 'frail', 'white' => 'frail', 'red' => 'frail',
	);
}

/** What fighting on this background does. */
function dhca_terrain_for($slug) {
	$slug = strtolower((string)$slug);
	foreach (dhca_terrain_rules() as $needle => $id) {
		if (strpos($slug, $needle) !== false) return $id;
	}
	return 'frail';   // an unrecognised field is a bare one
}

/** The arena's name: a written one where the slug does not carry itself. */
function dhca_terrain_name($slug) {
	$slug = strtolower((string)$slug);
	$named = dhca_terrain_names();
	if (isset($named[$slug])) return $named[$slug];
	$s = preg_replace('/[-_]+/', ' ', $slug);
	$s = preg_replace('/\b(dh|dhc2|xlon s|xlons s|xlons|xlon)\b\s*/', '', $s);
	$s = preg_replace('/\s+\d+$/', '', $s);          // trailing variant numbers
	$s = trim(preg_replace('/\s+/', ' ', $s));
	if ($s === '') $s = 'Unknown Ground';
	return ucwords($s);
}

/**
 * Arenas that need a name rather than a tidied slug.
 *
 * Some backgrounds already read as a place -- Hellscape, Wormhole Passage,
 * Aracnyd Web, Digital Abyss -- and those are left alone, because the art's own
 * name is always the safest caption. The rest are descriptions of a picture
 * ("Light Yellow", "Complex", "Xlon S Optical Attack") and announcing a battle
 * in Light Yellow is not atmosphere, it is a filename.
 *
 * Every name below is built from what the piece actually shows, and is checked
 * against the effect it carries: a Whiteout leaves you exposed and that arena
 * costs health; the Void is deep space and that one builds Charge; the Firewall
 * is digital flame and that one burns.
 */
function dhca_terrain_names() {
	return array(
		// Already a place, so left alone -- the art's own name is always the
		// safest caption: hellscape, digital-abyss, wormhole-passage,
		// dh-aracnyd-web, data-tunnel, quasar-tf-39-sun.
		//
		// EVERY NAME BELOW KEEPS A WORD FROM THE ORIGINAL. These are Maxingo's
		// pieces and a player may well recognise one by name; renaming Light
		// Yellow to something unrecognisable would trade a filename for a
		// disguise. So the slug's own word stays and the rest does the work of
		// making it somewhere you could stand.
		'advanced-circuit-1'               => 'Advanced Circuitry',
		'advanced-circuit-2'               => 'Deep Circuitry',
		'black'                            => 'The Black',
		'bright-red'                       => 'The Red Glare',
		'dark-explosion'                   => 'The Dark Blast',
		'dh-killer-bubbles'                => 'Killer Bubble Field',
		'dh-landscape'                     => 'The Open Landscape',
		'dh-nebula-1'                      => 'The Nebula',
		'dh-nebula-02'                     => 'The Nebula',
		'dh-nebula-3'                      => 'The Nebula',
		'dh-thunder'                       => 'Thunderhead',
		'digital-flames-1'                 => 'Digital Firewall',
		'digital-flames-2'                 => 'Digital Firewall',
		'digital-trap-01'                  => 'Digital Snare',
		'digital-trap-2'                   => 'Digital Snare',
		'digital-trap-03'                  => 'Digital Snare',
		'digital-trap-04'                  => 'Digital Snare',
		'eon-raider-spacecraft-window'     => 'Eon Raider Viewport',
		'exoplanet-landscape'              => 'Exoplanet Surface',
		'experimentation-facility-entrance'=> 'The Facility Gate',
		'light-blue'                       => 'Pale Blue Expanse',
		'light-green'                      => 'Pale Green Expanse',
		'light-purple'                     => 'Pale Violet Expanse',
		'light-yellow'                     => 'Pale Amber Expanse',
		'red'                              => 'The Red Expanse',
		'white'                            => 'Whiteout',
		'xlon-ddos-attack'                 => 'DDoS Flood',
		'xlon-s-2yk-attack-01'             => '2YK Breach',
		'xlon-s-2yk-attack-02'             => '2YK Breach',
		'xlon-s-building-trap'             => 'The Rigged Building',
		'xlon-s-digital-smoke-attack'      => 'Digital Smokescreen',
		'xlon-s-disintegrating-bubbles'    => 'Disintegration Field',
		'xlon-s-optical-attack'            => 'Optical Glare',
		'xlon-s-psi-attack'                => 'Psi Storm',
		'xlons-building-interior'          => 'Xlon Interior',
		'xlons-s-complex'                  => 'The Xlon Complex',
		'quasar-tf-39-sun'                 => 'Quasar TF-39',
	);
}

/**
 * One line of atmosphere per arena.
 *
 * Written to do two jobs at once: say what the place feels like, and hint at
 * what it is about to do to the fight. The Whiteout line is about exposure and
 * that arena costs health; the Snare line is about putting your back to
 * something and that one strengthens Shield. A player who never reads the
 * effect underneath should still come away with the right expectation.
 *
 * Written in the collection's OWN vocabulary wherever it fits -- cyborgs,
 * raiders, insekts, specters, cops, Xlon, arachno silk, plague-detecting masks.
 * Generic sci-fi would read as stock text bolted onto Maxingo's art; the words
 * the pieces are already named with make the arena part of the same world.
 *
 * Short on purpose. This sits between a name and a rule on a panel that holds
 * for three seconds, and a paragraph would turn an entrance into a loading
 * screen.
 */
function dhca_terrain_lines() {
	return array(
		'advanced-circuit-1'               => 'Every trace is a sightline. The cyborgs built this board to be read.',
		'advanced-circuit-2'               => 'Deep in the traces. Everything is exposed down here, including them.',
		'black'                            => 'No floor, no ceiling, nothing to earth the charge you are building.',
		'bright-red'                       => 'Red light over everything. Blows land heavier in it.',
		'dark-explosion'                   => 'You arrive in the half-second after the charge went off.',
		'data-tunnel'                      => 'Packets scream past at head height. Read the gaps and strike through them.',
		'dh-aracnyd-web'                   => 'Arachno silk, thick enough to lean on and thick enough to hide behind.',
		'dh-killer-bubbles'                => 'They drift and they burst, and they take the worst of what is thrown.',
		'dh-landscape'                     => 'Flat to the horizon. Nothing between you and the raiders.',
		'dh-nebula-1'                      => 'Gas thick enough to taste, and it holds a charge.',
		'dh-nebula-02'                     => 'Gas thick enough to taste, and it holds a charge.',
		'dh-nebula-3'                      => 'Gas thick enough to taste, and it holds a charge.',
		'dh-thunder'                       => 'Every strike lands with the storm behind it.',
		'digital-abyss'                    => 'It goes down further than the eye follows. Nothing here to earth a charge.',
		'digital-flames-1'                 => 'Code burning hotter than whatever it was written to protect.',
		'digital-flames-2'                 => 'Code burning hotter than whatever it was written to protect.',
		'digital-trap-01'                  => 'The floor is rigged. Put your back to it and let it work.',
		'digital-trap-2'                   => 'The floor is rigged. Put your back to it and let it work.',
		'digital-trap-03'                  => 'The floor is rigged. Put your back to it and let it work.',
		'digital-trap-04'                  => 'The floor is rigged. Put your back to it and let it work.',
		'eon-raider-spacecraft-window'     => "Fighting on the raider's glass, the long dark on the other side.",
		'exoplanet-landscape'              => 'Low gravity. Everything you throw carries further than it should.',
		'experimentation-facility-entrance'=> 'Past these doors there is nowhere to back away to.',
		'hellscape'                        => 'It was already burning when the citizens got here.',
		'light-blue'                       => 'An empty field of light. No cover, no corners, nothing to use.',
		'light-green'                      => 'Nothing to stand behind. The insekts see you coming.',
		'light-purple'                     => 'Open ground in every direction. Everyone is easier to reach.',
		'light-yellow'                     => 'Bright, bare, and completely exposed.',
		'quasar-tf-39-sun'                 => 'A dying star, close enough to feel. Everything hits harder for it.',
		'red'                              => 'Red to the edges. Nowhere to put your back.',
		'white'                            => 'No edges and no shadow. Nowhere for anyone to hide.',
		'wormhole-passage'                 => 'The passage pulls at everything. Charge builds faster in it.',
		'xlon-ddos-attack'                 => 'Xlon traffic hammers past in waves. Find the gap, put something through it.',
		'xlon-s-2yk-attack-01'             => 'A gap in the system nobody ever patched. Aim for it and it opens.',
		'xlon-s-2yk-attack-02'             => 'A gap in the system nobody ever patched. Aim for it and it opens.',
		'xlon-s-building-trap'             => 'Rigged floor to roof, and every charge is between you and them.',
		'xlon-s-digital-smoke-attack'      => 'You cannot see much. Neither can the cops.',
		'xlon-s-disintegrating-bubbles'    => 'Everything comes apart slowly here. Long enough to get behind something.',
		'xlon-s-optical-attack'            => "The glare finds every seam in a cyborg's plate.",
		'xlon-s-psi-attack'                => 'Something in the air is reading the fight before it happens.',
		'xlons-building-interior'          => 'Close walls. Nothing lands softly inside an Xlon building.',
		'xlons-s-complex'                  => 'Corridors and blind corners, and no room to give ground.',
	);
}

/** The line for this arena, or one that fits its effect if it has none yet. */
function dhca_terrain_line($slug) {
	$lines = dhca_terrain_lines();
	$slug  = strtolower((string)$slug);
	if (isset($lines[$slug])) return $lines[$slug];
	// A background added after this table was written still gets something
	// true, because the effect is known even when the place is not.
	$byEffect = array(
		'dmg'   => 'Something about this place makes every blow land harder.',
		'surge' => 'The air holds a charge, and it is building.',
		'guard' => 'There is cover here, for anyone quick enough to use it.',
		'crit'  => 'The noise cuts both ways. Aim well and it will tell.',
		'frail' => 'Open ground. Nothing to hide behind and nowhere to rest.',
	);
	$t = dhca_terrain_for($slug);
	return isset($byEffect[$t]) ? $byEffect[$t] : '';
}
