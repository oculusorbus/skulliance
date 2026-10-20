<?php
/**
 * THE DROP SHIP WORDS A THEME USUALLY HAS TO ANSWER FOR.
 *
 * Reworking a theme used to mean guessing which words mattered, or reading
 * the game until you found them all. This is that list, so the admin can
 * present it as a form to fill in rather than a blank box to speculate into.
 *
 * DERIVED FROM WHAT THE EXISTING THEMES ACTUALLY OVERRODE, not from a trawl
 * of the source: the union of every replacement the if/else chain in db.php
 * held for Dread City, Filthy Mermaid and Oculus Lounge. 'themes' records how
 * many of those three bothered with a term, which is a decent proxy for how
 * load-bearing it is -- a 3 is something every reskin had to rename.
 *
 * LOWERCASE-HYPHEN TERMS ARE ICON FILENAMES. Renaming 'medkit' changes which
 * file the page asks for, so a theme that overrides one owes an icon by that
 * name. The admin flags them for exactly that reason.
 *
 * NOT A LIMIT. A theme can override anything; this is the guide, and the
 * admin keeps free-form rows underneath it.
 */
function dropshipThemeVocabulary() {
	return array(
		'Identity' => array(
			array('find' => 'Drop Ship', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'drop-ship', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'dropship', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Drop Ship Initiating', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Drop Ship Landed', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'SCRIP', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'Grunt', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Soldiers', 'type' => 'exact', 'themes' => 1, 'icon' => false),
			array('find' => 'Registered Soldiers', 'type' => 'exact', 'themes' => 1, 'icon' => false),
			array('find' => 'Super Soldier', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'supersoldier', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Pilot', 'type' => 'exact', 'themes' => 2, 'icon' => false),
		),
		'Places' => array(
			array('find' => 'Barracks', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'Armory', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Beach Secured', 'type' => 'exact', 'themes' => 1, 'icon' => false),
			array('find' => 'Hill Secured', 'type' => 'exact', 'themes' => 1, 'icon' => false),
		),
		'Armor' => array(
			array('find' => 'Heavy', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'Heavy Armor', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'heavy-armor', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Medium', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'Medium Armor', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'medium-armor', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Light', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'Light Armor', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'light-armor', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Base', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'Base Armor', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'base-armor', 'type' => 'exact', 'themes' => 3, 'icon' => true),
		),
		'Gear' => array(
			array('find' => 'Medkit', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'medkit', 'type' => 'exact', 'themes' => 2, 'icon' => true),
			array('find' => 'Demolition', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'demolition', 'type' => 'exact', 'themes' => 2, 'icon' => true),
			array('find' => 'Melee', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'melee', 'type' => 'exact', 'themes' => 2, 'icon' => true),
		),
		'Suits' => array(
			array('find' => 'Mech Suit', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'mech-suit', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Exo Suit', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'exo-suit', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Quantum Stealth', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'quantum-stealth', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Ballistic Shield', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'ballistic-shield', 'type' => 'exact', 'themes' => 2, 'icon' => true),
		),
		'Equipment' => array(
			array('find' => 'Drop Box', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'drop-box', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Jet Pack', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'jet-pack', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Radar', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'radar', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Radio', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'radio', 'type' => 'exact', 'themes' => 2, 'icon' => true),
			array('find' => 'Night Vision Goggles', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'night-vision-goggles', 'type' => 'exact', 'themes' => 3, 'icon' => true),
			array('find' => 'Reinforcements', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Airstrike', 'type' => 'exact', 'themes' => 2, 'icon' => false),
		),
		'Weapons' => array(
			array('find' => 'Rocket Launcher', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'rocket-launcher', 'type' => 'exact', 'themes' => 2, 'icon' => true),
			array('find' => 'Flamethrower', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'flamethrower', 'type' => 'exact', 'themes' => 2, 'icon' => true),
			array('find' => 'Machine Gun', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'machine-gun', 'type' => 'exact', 'themes' => 2, 'icon' => true),
			array('find' => 'Grenade', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'grenade', 'type' => 'exact', 'themes' => 2, 'icon' => true),
			array('find' => 'Smoke Bomb', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'smoke-bomb', 'type' => 'exact', 'themes' => 2, 'icon' => true),
			array('find' => 'Sniper Rifle', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'sniper-rifle', 'type' => 'exact', 'themes' => 2, 'icon' => true),
			array('find' => 'Tactical Katana', 'type' => 'exact', 'themes' => 3, 'icon' => false),
			array('find' => 'tactical-katana', 'type' => 'exact', 'themes' => 3, 'icon' => true),
		),
		'Action lines' => array(
			array('find' => 'Explosion', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Flamethrower Fire', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Flamethrower Flame', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Flamethrower Ignition', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Flamethrower Spray', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Jet Pack Fly', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Jet Pack Takeoff', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Load Machine Gun', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Load Rocket Launcher', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Pull Grenade Pin', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Pull Smoke Bomb Pin', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Rocket Launcher Explosion', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Rocket Launcher Gunfire', 'type' => 'exact', 'themes' => 2, 'icon' => false),
			array('find' => 'Throw Grenade', 'type' => 'exact', 'themes' => 2, 'icon' => false),
		),
		'Descriptions' => array(
			array('find' => 'beach', 'type' => 'description', 'themes' => 3, 'icon' => false),
			array('find' => 'bunker', 'type' => 'description', 'themes' => 3, 'icon' => false),
			array('find' => 'hill', 'type' => 'description', 'themes' => 3, 'icon' => false),
			array('find' => 'Ballistic Shield', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Base Armor', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Climbed the Hill', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Demolition', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Drop Box', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Entered the Bunker', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Exo Suit', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Flamethrower', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Grenade', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Heavy Armor', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Jet Pack', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Light Armor', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Machine Gun', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Mech Suit', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Medium Armor', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Medkit', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Melee', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Night Vision Goggles', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Quantum Stealth', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Radar', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Radio', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Rocket Launcher', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Smoke Bomb', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Sniper Rifle', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Stormed the Beach', 'type' => 'description', 'themes' => 2, 'icon' => false),
			array('find' => 'Tactical Katana', 'type' => 'description', 'themes' => 2, 'icon' => false),
		),
	);
}
