<?php
/**
 * THE DROP SHIP WORDS A THEME HAS TO ANSWER FOR, grouped by the THING they
 * name rather than listed one string at a time.
 *
 * WHY FAMILIES. The game asks for the same thing in up to four ways: the
 * soldier card shows the bare tier ("Heavy"), the shop sells the item
 * ("Heavy Armor"), a results line mentions it mid-sentence (a description
 * match), and the markup asks for icons/heavy-armor.png. They always take
 * the same answer -- verified against all three existing themes, 46 of 46
 * exact/description pairs byte-identical -- so asking four times was
 * make-work, and four boxes that must agree are four boxes that can drift.
 * One box fills the family.
 *
 * WHY THERE IS NO "DESCRIPTIONS" GROUP. Exact and description are match
 * TYPES, not categories: every description term is the same thing as a term
 * already named above, bar the places and the three action lines, which
 * belong with the places and the action lines. A group that collects terms
 * by how they are matched tells you nothing about what you are renaming.
 *
 * THE ICON NAME IS DERIVED, NOT TYPED. Icons are filenames, and slugifying
 * the theme's own word gets it right 51 times out of 64 in the existing
 * themes. The 13 exceptions are all deliberate shortenings ('Sexy Nurse' ->
 * nurse, 'Loot Crate' -> crate, 'Birthday Suit' -> cake), so the derived
 * name is the default and the admin keeps an override.
 *
 * DERIVED BY RUNNING THE OLD evaluateText() CHAIN, not by reading it, and
 * every family is checked for internal agreement. Two findings came out of
 * that check rather than out of review: 'dropship' is Super Soldier's icon,
 * not Drop Ship's (both themes send it to 'vip', with 'supersoldier'), and
 * the armor icon is 'heavy-armor', never 'heavy'. 'themes' counts how many
 * of the three bothered with a family, a usable proxy for how load-bearing
 * it is.
 *
 * NOT A LIMIT. The admin keeps free-form rows underneath this.
 */
function dropshipThemeVocabulary() {
	return array(
		'Identity' => array(
			/* NO ICON. The old chain carried a 'drop-ship' entry, but nothing has
			   ever rendered icons/drop-ship.png -- every other use of that string
			   is the art FOLDER slug. Project 1 had been asking for a 404 since
			   before any of this, and claiming it here would put a permanently
			   missing slot on the art page and demand three files nobody uses. */
			array('label' => 'Drop Ship', 'exact' => array('Drop Ship'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			/* The title card shown the moment you press Play, before the
			   first round. It has no caption on screen -- it is a picture
			   and a sound and nothing else -- but it needs a word here so
			   a theme can name its own file for it, the same as any other
			   thing. */
			array('label' => 'Drop Ship Intro', 'exact' => array('Drop Ship Intro'), 'desc' => array(), 'icons' => array(), 'themes' => 0),
			array('label' => 'Drop Ship Initiating', 'exact' => array('Drop Ship Initiating'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Drop Ship Landed', 'exact' => array('Drop Ship Landed'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'SCRIP', 'exact' => array('SCRIP'), 'desc' => array(), 'icons' => array(), 'themes' => 3),
			array('label' => 'Grunt', 'exact' => array('Grunt'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Soldiers', 'exact' => array('Soldiers'), 'desc' => array(), 'icons' => array(), 'themes' => 1),
			/* ONE member of the squad, lowercase because it is only ever
			   used mid-sentence -- "Next: a soldier in Heavy armor" in the
			   build guide. 'Troops' below is the plural the dashboard
			   shows; this is the singular that reads in a sentence, and
			   no existing theme had one because nothing asked for it. */
			array('label' => 'soldier', 'exact' => array('soldier'), 'desc' => array(), 'icons' => array(), 'themes' => 0),
			/* The word for one member of the squad. It had no dictionary rows
			   because the dashboard used to read it off a character_label
			   column instead; that column is gone and this is the word. */
			array('label' => 'Troops', 'exact' => array('Troops'), 'desc' => array(), 'icons' => array(), 'themes' => 3),
			array('label' => 'Registered Soldiers', 'exact' => array('Registered Soldiers'), 'desc' => array(), 'icons' => array(), 'themes' => 1),
			array('label' => 'Super Soldier', 'exact' => array('Super Soldier'), 'desc' => array(), 'icons' => array('dropship', 'supersoldier'), 'themes' => 2),
			array('label' => 'Pilot', 'exact' => array('Pilot'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
		),
		'Locations' => array(
			array('label' => 'Barracks', 'exact' => array('Barracks'), 'desc' => array(), 'icons' => array(), 'themes' => 3),
			array('label' => 'Armory', 'exact' => array('Armory'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'beach', 'exact' => array(), 'desc' => array('beach'), 'icons' => array(), 'themes' => 3),
			array('label' => 'hill', 'exact' => array(), 'desc' => array('hill'), 'icons' => array(), 'themes' => 3),
			array('label' => 'bunker', 'exact' => array(), 'desc' => array('bunker'), 'icons' => array(), 'themes' => 3),
		),
		'Armor' => array(
			array('label' => 'Heavy', 'exact' => array('Heavy', 'Heavy Armor'), 'desc' => array('Heavy Armor'), 'icons' => array('heavy-armor'), 'themes' => 3),
			array('label' => 'Medium', 'exact' => array('Medium', 'Medium Armor'), 'desc' => array('Medium Armor'), 'icons' => array('medium-armor'), 'themes' => 3),
			array('label' => 'Light', 'exact' => array('Light', 'Light Armor'), 'desc' => array('Light Armor'), 'icons' => array('light-armor'), 'themes' => 3),
			array('label' => 'Base', 'exact' => array('Base', 'Base Armor'), 'desc' => array('Base Armor'), 'icons' => array('base-armor'), 'themes' => 3),
			array('label' => 'Mech Suit', 'exact' => array('Mech Suit'), 'desc' => array('Mech Suit'), 'icons' => array('mech-suit'), 'themes' => 3),
			array('label' => 'Exo Suit', 'exact' => array('Exo Suit'), 'desc' => array('Exo Suit'), 'icons' => array('exo-suit'), 'themes' => 3),
			array('label' => 'Quantum Stealth', 'exact' => array('Quantum Stealth'), 'desc' => array('Quantum Stealth'), 'icons' => array('quantum-stealth'), 'themes' => 3),
			array('label' => 'Ballistic Shield', 'exact' => array('Ballistic Shield'), 'desc' => array('Ballistic Shield'), 'icons' => array('ballistic-shield'), 'themes' => 2),
		),
		'Weapons' => array(
			array('label' => 'Rocket Launcher', 'exact' => array('Rocket Launcher'), 'desc' => array('Rocket Launcher'), 'icons' => array('rocket-launcher'), 'themes' => 2),
			array('label' => 'Flamethrower', 'exact' => array('Flamethrower'), 'desc' => array('Flamethrower'), 'icons' => array('flamethrower'), 'themes' => 2),
			array('label' => 'Machine Gun', 'exact' => array('Machine Gun'), 'desc' => array('Machine Gun'), 'icons' => array('machine-gun'), 'themes' => 2),
			array('label' => 'Grenade', 'exact' => array('Grenade'), 'desc' => array('Grenade'), 'icons' => array('grenade'), 'themes' => 2),
			array('label' => 'Smoke Bomb', 'exact' => array('Smoke Bomb'), 'desc' => array('Smoke Bomb'), 'icons' => array('smoke-bomb'), 'themes' => 2),
			array('label' => 'Sniper Rifle', 'exact' => array('Sniper Rifle'), 'desc' => array('Sniper Rifle'), 'icons' => array('sniper-rifle'), 'themes' => 2),
			array('label' => 'Tactical Katana', 'exact' => array('Tactical Katana'), 'desc' => array('Tactical Katana'), 'icons' => array('tactical-katana'), 'themes' => 3),
			array('label' => 'Melee', 'exact' => array('Melee'), 'desc' => array('Melee'), 'icons' => array('melee'), 'themes' => 2),
			array('label' => 'Demolition', 'exact' => array('Demolition'), 'desc' => array('Demolition'), 'icons' => array('demolition'), 'themes' => 2),
		),
		'Items' => array(
			array('label' => 'Medkit', 'exact' => array('Medkit'), 'desc' => array('Medkit'), 'icons' => array('medkit'), 'themes' => 2),
			array('label' => 'Drop Box', 'exact' => array('Drop Box'), 'desc' => array('Drop Box'), 'icons' => array('drop-box'), 'themes' => 3),
			array('label' => 'Jet Pack', 'exact' => array('Jet Pack'), 'desc' => array('Jet Pack'), 'icons' => array('jet-pack'), 'themes' => 3),
			array('label' => 'Radar', 'exact' => array('Radar'), 'desc' => array('Radar'), 'icons' => array('radar'), 'themes' => 3),
			array('label' => 'Radio', 'exact' => array('Radio'), 'desc' => array('Radio'), 'icons' => array('radio'), 'themes' => 3),
			array('label' => 'Night Vision Goggles', 'exact' => array('Night Vision Goggles'), 'desc' => array('Night Vision Goggles'), 'icons' => array('night-vision-goggles'), 'themes' => 3),
			array('label' => 'Reinforcements', 'exact' => array('Reinforcements'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Airstrike', 'exact' => array('Airstrike'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
		),
		'Actions' => array(
			array('label' => 'Stormed the Beach', 'exact' => array(), 'desc' => array('Stormed the Beach'), 'icons' => array(), 'themes' => 2),
			array('label' => 'Climbed the Hill', 'exact' => array(), 'desc' => array('Climbed the Hill'), 'icons' => array(), 'themes' => 2),
			array('label' => 'Entered the Bunker', 'exact' => array(), 'desc' => array('Entered the Bunker'), 'icons' => array(), 'themes' => 2),
			array('label' => 'Beach Secured', 'exact' => array('Beach Secured'), 'desc' => array(), 'icons' => array(), 'themes' => 1),
			array('label' => 'Hill Secured', 'exact' => array('Hill Secured'), 'desc' => array(), 'icons' => array(), 'themes' => 1),
			array('label' => 'Explosion', 'exact' => array('Explosion'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Throw Grenade', 'exact' => array('Throw Grenade'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Pull Grenade Pin', 'exact' => array('Pull Grenade Pin'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Pull Smoke Bomb Pin', 'exact' => array('Pull Smoke Bomb Pin'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Load Machine Gun', 'exact' => array('Load Machine Gun'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Load Rocket Launcher', 'exact' => array('Load Rocket Launcher'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Rocket Launcher Explosion', 'exact' => array('Rocket Launcher Explosion'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Rocket Launcher Gunfire', 'exact' => array('Rocket Launcher Gunfire'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Flamethrower Ignition', 'exact' => array('Flamethrower Ignition'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Flamethrower Fire', 'exact' => array('Flamethrower Fire'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Flamethrower Flame', 'exact' => array('Flamethrower Flame'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Flamethrower Spray', 'exact' => array('Flamethrower Spray'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Jet Pack Takeoff', 'exact' => array('Jet Pack Takeoff'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
			array('label' => 'Jet Pack Fly', 'exact' => array('Jet Pack Fly'), 'desc' => array(), 'icons' => array(), 'themes' => 2),
		),
	);
}

/**
 * TURN WHAT THE WORDING FORM POSTS INTO theme_text ROWS.
 *
 * The form shows one box per family; the database wants one row per word
 * per match type, plus one per icon filename. This is where the one becomes
 * the many, and it is a plain function of the post so it can be tested
 * without a browser or a database.
 *
 * BLANK MEANS THE DROP SHIP WORD, and nothing is stored. evaluateText()
 * already falls through to the original when it finds no row, so a row
 * replacing a word with itself would be a no-op kept only to record that
 * somebody had looked at it -- bookkeeping, paid for with a column on every
 * row of the form. Only overrides override.
 *
 * THE WORDS COME FROM THE VOCABULARY, NEVER FROM THE POST. The form carries
 * the label; the list it expands to is read from here, so a mangled or
 * forged post cannot write a word this form does not own.
 */
function dropshipThemeVocabExpand($post) {
	$legit = array();
	foreach (dropshipThemeVocabulary() as $rows) {
		foreach ($rows as $r) $legit[$r['label']] = $r;
	}
	$labels = (array)(isset($post['fam_label']) ? $post['fam_label'] : array());
	$says   = (array)(isset($post['fam_say'])   ? $post['fam_say']   : array());
	$out = array();
	foreach ($labels as $i => $label) {
		$label = trim((string)$label);
		if (!isset($legit[$label])) continue;
		$fam = $legit[$label];
		$say = trim((string)(isset($says[$i]) ? $says[$i] : ''));
		if ($say === '') continue;              /* keeps the Drop Ship word */
		foreach ($fam['exact'] as $w) {
			$out[] = array('type' => 'exact', 'find' => $w, 'replacement' => $say);
		}
		foreach ($fam['desc'] as $w) {
			$out[] = array('type' => 'description', 'find' => $w, 'replacement' => $say);
		}
		/*
		 * NO ICON ROWS. The icon filename is the word made into a filename,
		 * and dropshipThemeText() derives it on the way out. Writing it here
		 * too would be the same fact in two rows, free to disagree: rename
		 * Medkit and the stored icon row would still say 'nurse' until
		 * somebody edited it as well. The FILE is managed on the art screen,
		 * next to the art it belongs with; the name is never typed.
		 */
	}
	return $out;
}
