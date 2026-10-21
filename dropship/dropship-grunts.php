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
	$sql = $names ? " OR (rank = 'Grunt' AND asset_name IN (" . implode(',', $names) . "))" : '';

	/*
	 * AND THE SAME FOR AN OVERRIDDEN NFT, BOTH WAYS.
	 *
	 * This is the bug this function was written for, wearing a second hat. A
	 * pinned soldier's STORED armor is whatever the sync derived, so filtering
	 * by Heavy hid the one whose card says Heavy -- and filtering by Base
	 * showed it, because Base is what is still in the column. Adding it to the
	 * right filter without removing it from the wrong one would have fixed
	 * half of that and looked finished.
	 */
	/*
	 * ONLY FOR A VALUE THESE TABLES ACTUALLY USE. An unrecognised filter has
	 * to produce no fragment at all -- it always has -- and without this the
	 * exclusion half fired for any string at all, since every pinned soldier
	 * "is not Nonesuch". Harmless in SQL and wrong as a contract: a function
	 * that answers a question nobody asked is one a later reader trusts.
	 */
	$known = false;
	foreach (array(dropshipGruntKits(), dropshipNftLoadouts()) as $table) {
		foreach ($table as $kit) if (isset($kit[$field]) && $kit[$field] === $value) $known = true;
	}
	$add = array(); $drop = array();
	if ($known) {
		foreach (dropshipNftLoadouts() as $asset => $kit) {
			if (!isset($kit[$field])) continue;
			if ($kit[$field] === $value) $add[]  = "'" . $asset . "'";
			else                         $drop[] = "'" . $asset . "'";
		}
	}
	if ($add)  $sql .= " OR asset_name IN (" . implode(',', $add) . ")";
	/*
	 * THE EXCLUSION GOES FIRST, AND IT RELIES ON AND BINDING TIGHTER THAN OR.
	 * The caller wraps this as `AND (armor = 'X' <fragment>)`, so the result
	 * reads
	 *
	 *   (armor = 'X' AND asset_name NOT IN (pinned elsewhere))
	 *   OR (rank = 'Grunt' AND asset_name IN (grunts))
	 *   OR asset_name IN (pinned here)
	 *
	 * which is what is wanted: the NOT IN narrows the STORED-column test
	 * only, and the two OR terms add back the rows whose real kit is a
	 * derivation. Written without that precedence in mind the NOT IN would
	 * swallow the OR terms as well and the filter would return nothing.
	 * dropship-grunts-harness.php pins the caller's shape for that reason.
	 */
	if ($drop) $sql  = " AND asset_name NOT IN (" . implode(',', $drop) . ")" . $sql;
	return $sql;
}

/**
 * LOADOUT OVERRIDES FOR NAMED NFTs.
 *
 * Drop Ship reads two things off a soldier -- `armor` and `gear` -- and for a
 * collection whose metadata it cannot map, both come from
 * dropshipStatsFromAssetName(), which is a derivation from the asset name
 * rather than a fact about the art. On Digital Hell Citizens 2: Fighters that
 * produced Light/Melee, Base/None, Base/None and Medium/Demolition: two
 * duplicate Base tiers, no Heavy and no Medkit, so the holder could not build
 * a Super Soldier Squad out of the collection at all.
 *
 * WHY THE OVERRIDE IS PINNED FOR ALL FOUR and not only the one that changes:
 * the NFT sync UPDATEs armor, gear and level on every wallet refresh from
 * that same derivation, so a soldier left unpinned can drift the next time
 * the mapping is touched and quietly break the squad again. Pinning the whole
 * set makes the squad a stated intention rather than a coincidence.
 *
 * KEYED ON THE ASSET NAME ALONE, no project gate. These names exist in
 * exactly one collection, so a project id would add a second thing to keep
 * right -- and it is the thing most easily got wrong, since a theme id and a
 * project id are different numbers that both look like small integers.
 *
 * ARMOUR AND GEAR ONLY. Level and rank are the collection's business, and
 * dropshipGruntLoadout() has always left them alone for the same reason --
 * level feeds the rank tiers and anything else that reads it. A card will
 * therefore show the overridden armour beside the original level.
 */
function dropshipNftLoadouts() {
	return array(
		/* The Super Soldier Squad for Digital Hell Citizens 2: Fighters.
		   Heavy + Medium + Light + Base is 74+3+2+1 = 80, and Melee +
		   Demolition + Medkit is all three gear types. */
		'DHC2F163' => array('armor' => 'Light',  'gear' => 'Melee'),       // unchanged, pinned
		'DHC2F028' => array('armor' => 'Medium', 'gear' => 'Demolition'),  // unchanged, pinned
		'DHC2F088' => array('armor' => 'Heavy',  'gear' => 'Medkit'),      // was Base / None
		'DHC2F133' => array('armor' => 'Base',   'gear' => 'None'),        // unchanged, pinned
	);
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

	/*
	 * A NAMED NFT OVERRIDE BEATS EVERYTHING, and is checked before the Grunt
	 * gate below rather than after it: these are real NFTs with a real rank,
	 * so the `rank !== 'Grunt'` return would have skipped them entirely.
	 *
	 * Matched on asset_name, falling back to the display name. The sync
	 * writes the on-chain ascii name into asset_name and the metadata name
	 * into name, and for this collection they are the same string -- the
	 * fallback costs nothing and removes a guess from something that is
	 * otherwise silent when it misses.
	 */
	$over = dropshipNftLoadouts();
	foreach (array('asset_name', 'name') as $k) {
		if (empty($row[$k]) || !isset($over[(string)$row[$k]])) continue;
		$kit = $over[(string)$row[$k]];
		$row['armor'] = $kit['armor'];
		$row['gear']  = $kit['gear'];
		return $row;                  /* level and rank deliberately untouched */
	}

	if (isset($row['rank']) && $row['rank'] !== 'Grunt') return $row;

	$kits = dropshipGruntKits();
	$key  = (string)$row['asset_name'];
	if (!isset($kits[$key])) return $row;

	$row['armor'] = $kits[$key]['armor'];
	$row['gear']  = $kits[$key]['gear'];
	return $row;                      /* level and rank deliberately untouched */
}
