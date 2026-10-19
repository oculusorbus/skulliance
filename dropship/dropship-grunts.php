<?php
/**
 * THE FOUR GRUNTS, AND WHAT THEY ACTUALLY CARRY.
 *
 * Every Drop Ship player is given four free soldiers -- Blood Bag, Delay,
 * Hagar and Sektr -- stored with armor 'None', gear 'None' and level 0. The
 * run used to read that as "no armour at all", which falls to the bottom of
 * the threshold chain in dropship-odds.php: the promo branch, worth 79 plus
 * free Medkit, Melee and Demolition.
 *
 * THE RESULT WAS BACKWARDS. One grunt scored 79, a squad of four grunts also
 * scored 79, and a real NFT soldier in Heavy armour scored 74. Sending a
 * single grunt was the strongest opening in the game, four grunts was no
 * better than one, and buying armour made you worse. New players deploy all
 * four because four slots and four soldiers obviously go together, and the
 * game quietly told them they were wrong.
 *
 * So the grunts carry a LOANER KIT instead, assigned per soldier, and the
 * four of them together make exactly one Super Soldier Squad:
 *
 *   Hagar      Heavy   Melee        the one who wades in
 *   Delay      Medium  Demolition   a delay is a demolition fuse
 *   Blood Bag  Light   Medkit       the medic, and the obvious one
 *   Sektr      Base    None         the odd man out, and deliberately so
 *
 * Heavy carries the squad at 74, and Medium, Light and Base each add their
 * credit on top -- 74 + 3 + 2 + 1 = 80 -- which is the Super Soldier Squad
 * the Skull Paper has always described. Deploy one grunt and you get only
 * that grunt's tier, so Sektr alone is 50 and the full four is 80. The
 * intuitive move is now also the right one.
 *
 * SEKTR CARRIES NOTHING, which is not an oversight. There are three gear
 * types and four grunts, and duplicating one would hand a two-grunt squad a
 * second Medkit the game has no way to use. The gap is the cost of the
 * fourth armour tier.
 *
 * THE LOAN IS NOT A PROMOTION: level stays 0 and rank stays Grunt, so none of
 * this touches the rank tiers or anything that reads level. Only armor and
 * gear are rewritten, and only for these four.
 *
 * DROP SHIP ONLY. addDefaultSoldiers() is already gated on project_id 1, so
 * Oculus Lounge has no grunts and nothing here can reach it.
 */

/**
 * The loaner kit, keyed on asset_name because that is what the insert sets
 * and it never changes. The display name carries a " #00" suffix and goes
 * through evaluateText() on the way to the screen, so it is not a key.
 */
function dropshipGruntKits() {
	return array(
		'GRUNT003' => array('armor' => 'Heavy',  'gear' => 'Melee'),       // Hagar
		'GRUNT002' => array('armor' => 'Medium', 'gear' => 'Demolition'),  // Delay
		'GRUNT001' => array('armor' => 'Light',  'gear' => 'Medkit'),      // Blood Bag
		'GRUNT004' => array('armor' => 'Base',   'gear' => 'None'),        // Sektr
	);
}

/**
 * A SQL fragment matching the grunts whose loaner kit has this armor or gear.
 *
 * The Barracks filter runs in SQL against the stored columns, which for a
 * grunt are still None/None -- so filtering by Heavy hid Hagar even though
 * his card reads Heavy. Built from the same kit table the cards are drawn
 * from, so the filter cannot disagree with what it is filtering.
 *
 * Returns '' when no grunt qualifies, which keeps the caller's clause
 * unchanged rather than bolting on an empty IN ().
 */
function dropshipGruntFilterSql($field, $value) {
	if ($field !== 'armor' && $field !== 'gear') return '';
	$names = array();
	foreach (dropshipGruntKits() as $asset => $kit) {
		if ($kit[$field] === $value) $names[] = "'" . $asset . "'";
	}
	/* Kit values and asset names are literals in this file, never user input,
	   so there is nothing here to escape -- but the keys are constrained to
	   the known set anyway so a future edit cannot widen that. */
	return $names ? " OR (rank = 'Grunt' AND asset_name IN (" . implode(',', $names) . "))" : '';
}

/**
 * One soldier row with the loaner kit applied, if it is a grunt.
 *
 * Takes and returns a row rather than writing anywhere, so the SAME call
 * works for the run, the barracks listing and the odds readout. Every one of
 * those has to agree or the screen promises a squad the run does not field.
 *
 * Guarded on rank as well as asset_name: an NFT that somehow arrived with a
 * colliding asset_name must not be handed free armour.
 */
function dropshipGruntLoadout($row) {
	if (!is_array($row) || empty($row['asset_name'])) return $row;
	if (isset($row['rank']) && $row['rank'] !== 'Grunt') return $row;

	$kits = dropshipGruntKits();
	$key  = (string)$row['asset_name'];
	if (!isset($kits[$key])) return $row;

	$row['armor'] = $kits[$key]['armor'];
	$row['gear']  = $kits[$key]['gear'];
	return $row;                      /* level and rank deliberately untouched */
}
