<?php
/**
 * gauntlets-fight-lib.php -- the Gauntlet fight, persisted.
 *
 * The layer between gauntlets.php and gauntlets-engine.php. The engine is
 * pure and knows nothing about the database; this knows about both and
 * decides nothing about combat.
 *
 * SERVER AUTHORITATIVE. The client posts "I attack with weapon 7". This
 * checks the player owns weapon 7, that the run has a swing of it left,
 * and only then asks the engine what happens. A forged action is rejected
 * rather than resolved, which is the whole reason the turn is a round trip
 * instead of a local simulation.
 *
 * WHERE THE STATE LIVES, and why each piece is where it is:
 *
 *   gauntlets_nfts.hp        -- a squad member's health. On the HAND row
 *                               because it has to survive between
 *                               encounters: a beaten NFT stays beaten for
 *                               the rest of the run. Dies with the run,
 *                               like everything else here.
 *   gauntlets.gear_state     -- swings spent and armour worn, for the RUN.
 *                               Weapons are rationed per run, not per
 *                               fight; that is what stops "use the best
 *                               one every turn", which would make the
 *                               choice no choice.
 *   gauntlets_encounters.fight -- the live fight. Dies when it resolves.
 *
 * GEAR IS DURABLE, CONSUMABLES ARE SPENT. Weapons and armour wear down
 * across a run and come back afterwards; heals are genuinely consumed. If
 * gear were destroyed permanently players would hoard it and never use
 * it -- the 99-unused-elixirs problem -- and the game would be back to
 * having no decisions in it.
 */

require_once __DIR__ . '/gauntlets-engine.php';

/* The three columns this needs. Named rather than discovered so the page
   can say exactly what is missing instead of fataling -- same approach as
   guardians-lib.php. */
function gaunt_schema_missing($conn) {
	/* A LIST OF PAIRS, not a map: two of these are on the same table and
	   a map would silently keep only the last. */
	$want = array(
		array('gauntlets_nfts',       'hp'),
		array('gauntlets',            'gear_state'),
		array('gauntlets',            'chest'),
		array('gauntlets_encounters', 'fight'),
	);
	$missing = array();
	foreach ($want as $pair) {
		list($tbl, $col) = $pair;
		$r = @$conn->query("SHOW COLUMNS FROM `$tbl` LIKE '" . $conn->real_escape_string($col) . "'");
		if (!$r || !$r->num_rows) $missing[] = "$tbl.$col";
	}
	return $missing;
}

/**
 * THE MIGRATION, printed by the page rather than guessed at:
 *
 *   ALTER TABLE gauntlets_nfts       ADD COLUMN hp INT NOT NULL DEFAULT 0;
 *   ALTER TABLE gauntlets            ADD COLUMN gear_state TEXT NULL;
 *   ALTER TABLE gauntlets_encounters ADD COLUMN fight MEDIUMTEXT NULL;
 */
function gaunt_schema_sql() {
	return "ALTER TABLE gauntlets_nfts       ADD COLUMN hp INT NOT NULL DEFAULT 0;\n"
	     . "ALTER TABLE gauntlets            ADD COLUMN gear_state TEXT NULL;\n"
	     . "ALTER TABLE gauntlets            ADD COLUMN chest TINYINT NOT NULL DEFAULT 0;\n"
	     . "ALTER TABLE gauntlets_encounters ADD COLUMN fight MEDIUMTEXT NULL;";
}

/* ---------------------------------------------------------------- gear */

/**
 * The hyphenated slug a weapon's icon and sound are both keyed by.
 *
 * DUPLICATED FROM cryptcrawlWeaponSfxName(), on purpose. Both games read
 * the SAME `weapons` table through getAllWeapons(), so the names are
 * already shared -- but including another game's RENDER file to borrow a
 * lookup would couple two pages that have nothing else in common.
 * gauntlets-fight-harness.php asserts this map matches Crypt Crawl's
 * entry for entry, so the copy cannot drift.
 */
function gaunt_weapon_slug($name) {
	return strtolower(str_replace(array('%', ' '), array('', '-'), (string)$name));
}
function gaunt_weapon_sfx($name) {
	static $map = array(
		'melee'           => 'melee',
		'tactical-katana' => 'tacticalkatana',
		'pistol'          => 'pistol',
		'grenade'         => 'grenade',
		'sniper-rifle'    => 'sniperrifle',
		'machine-gun'     => 'machinegun',
		'demolition'      => 'demolition',
		'flamethrower'    => 'flamethrower',
		'rocket-launcher' => 'rocketlauncher',
		'artillery'       => 'artillery',
	);
	$slug = gaunt_weapon_slug($name);
	/* No entry means no weapon-specific layer, never a fatal -- a weapon
	   added to the table tomorrow still swings, it just sounds generic. */
	return isset($map[$slug]) ? $map[$slug] : null;
}

/** Heal percentage for a consumable, or 0 if it is not a heal. */
function gaunt_heal_pct($consumable_id) {
	switch ((int)$consumable_id) {
		case GAUNTLET_C_100: return 100;
		case GAUNTLET_C_75:  return 75;
		case GAUNTLET_C_50:  return 50;
		case GAUNTLET_C_25:  return 25;
	}
	return 0;
}

/* --------------------------------------------------------------- state */

function gaunt_gear_state($conn, $run_id) {
	$rid = (int)$run_id;
	$r = @$conn->query("SELECT gear_state FROM gauntlets WHERE id=$rid LIMIT 1");
	if (!$r || !$r->num_rows) return array('wpn' => array(), 'arm' => array());
	$j = json_decode((string)$r->fetch_assoc()['gear_state'], true);
	if (!is_array($j)) $j = array();
	if (!isset($j['wpn']) || !is_array($j['wpn'])) $j['wpn'] = array();
	if (!isset($j['arm']) || !is_array($j['arm'])) $j['arm'] = array();
	/* Salvage taken off slain opponents. RUN-SCOPED: it lives here, dies
	   with the run, and never touches the player's real inventory. */
	if (!isset($j['loot']) || !is_array($j['loot'])) $j['loot'] = array();
	return $j;
}
function gaunt_gear_state_save($conn, $run_id, $state) {
	$rid = (int)$run_id;
	$js  = $conn->real_escape_string(json_encode($state));
	@$conn->query("UPDATE gauntlets SET gear_state='$js' WHERE id=$rid");
}

function gaunt_fight_get($conn, $enc_id) {
	$e = (int)$enc_id;
	$r = @$conn->query("SELECT fight FROM gauntlets_encounters WHERE id=$e LIMIT 1");
	if (!$r || !$r->num_rows) return null;
	$j = json_decode((string)$r->fetch_assoc()['fight'], true);
	return is_array($j) ? $j : null;
}
function gaunt_fight_save($conn, $enc_id, $fight) {
	$e  = (int)$enc_id;
	$js = $conn->real_escape_string(json_encode($fight));
	@$conn->query("UPDATE gauntlets_encounters SET fight='$js' WHERE id=$e");
}

/**
 * A squad member's carried health.
 *
 * ZERO IS AMBIGUOUS AND THAT WAS A BUG. The column defaults to 0, so a
 * freshly dealt card and a corpse look identical -- and the first
 * version resolved that by reading 0 as full health, which RESURRECTED
 * anything that died. A member killed in encounter one came back whole
 * for encounter two, which quietly undoes the entire reason health
 * carries across a run.
 *
 * Resolved by asking whether the card has ever fought in this run. It
 * has not, so 0 means unseeded; it has, so 0 means down. One extra
 * query, only in the ambiguous case, and only until gauntletStartRun()
 * has seeded every new run at full.
 */
function gaunt_nft_hp($conn, $run_id, $nft_id) {
	$rid = (int)$run_id; $nid = (int)$nft_id;
	$r = @$conn->query("SELECT hp FROM gauntlets_nfts WHERE run_id=$rid AND nft_id=$nid LIMIT 1");
	if (!$r || !$r->num_rows) return GAUNT_HP_BASE;
	$hp = (int)$r->fetch_assoc()['hp'];
	if ($hp > 0) return $hp;
	$f = @$conn->query("SELECT id FROM gauntlets_encounters
	                     WHERE run_id=$rid AND player_nft_id=$nid LIMIT 1");
	return ($f && $f->num_rows) ? 0 : GAUNT_HP_BASE;
}

function gaunt_nft_hp_save($conn, $run_id, $nft_id, $hp) {
	$rid = (int)$run_id; $nid = (int)$nft_id; $h = max(0, (int)$hp);
	@$conn->query("UPDATE gauntlets_nfts SET hp=$h WHERE run_id=$rid AND nft_id=$nid");
}

/* ----------------------------------------------------------- the kit */

/** The best level of a gear type the player actually holds. */
function gaunt_best_level($conn, $user_id, $type) {
	$uid = (int)$user_id;
	$tbl = ($type === 'armor') ? 'armor' : 'weapons';
	$r = @$conn->query("SELECT MAX(t.level) AS lv FROM gear g
	                    INNER JOIN `$tbl` t ON t.id = g.item_id
	                    WHERE g.user_id=$uid AND g.type='" . ($type === 'armor' ? 'armor' : 'weapon') . "'
	                      AND g.quantity > 0");
	if (!$r || !$r->num_rows) return 0;
	return (int)$r->fetch_assoc()['lv'];
}
function gaunt_has_heal($conn, $user_id) {
	foreach (array(GAUNTLET_C_100, GAUNTLET_C_75, GAUNTLET_C_50, GAUNTLET_C_25) as $cid)
		if (getCurrentAmount($conn, (int)$user_id, $cid) > 0) return true;
	return false;
}

/**
 * What the OPPONENT is allowed to do, from the opposing player's
 * inventory -- a template for allowed actions, never consuming anything
 * of theirs. Capped against what this player can field; see
 * gaunt_cap_kit() for why that cap is the whole difficulty curve.
 */
function gaunt_kit_for($conn, $opponent_user_id, $player_user_id) {
	$raw = array('weapon_level' => 0, 'armor_level' => 0, 'can_heal' => false);
	if ((int)$opponent_user_id > 0) {
		$raw['weapon_level'] = gaunt_best_level($conn, $opponent_user_id, 'weapon');
		$raw['armor_level']  = gaunt_best_level($conn, $opponent_user_id, 'armor');
		$raw['can_heal']     = gaunt_has_heal($conn, $opponent_user_id);
	}
	return gaunt_cap_kit($raw,
		gaunt_best_level($conn, $player_user_id, 'weapon'),
		gaunt_best_level($conn, $player_user_id, 'armor'),
		gaunt_has_heal($conn, $player_user_id));
}

/* -------------------------------------------------------- the loadout */

/**
 * Every action the player may take this turn, with what is left of it.
 * The UI renders exactly this, and gaunt_fight_turn() validates against
 * it -- one list, so what is shown and what is allowed cannot disagree.
 */
function gaunt_loadout($conn, $user_id, $run_id, $fight = null) {
	$uid = (int)$user_id;
	$gs  = gaunt_gear_state($conn, $run_id);
	$out = array(
		'bare'    => array('type' => 'bare', 'name' => 'Bare hands', 'sfx' => 'fist', 'icon' => 'fist'),
		'weapons' => array(),
		'armor'   => array(),
		'heals'   => array(),
		'stakes'  => array(),
	);
	foreach (getGearInventory($conn, $uid) as $g) {
		if ((int)$g['quantity'] < 1) continue;
		$id = (int)$g['item_id'];
		if ($g['type'] === 'weapon') {
			$lv    = (int)$g['weapon_level'];
			$spent = (int)($gs['wpn'][$id] ?? 0);
			$qty   = (int)$g['quantity'];
			$left  = max(0, gaunt_weapon_uses($lv, $qty) - $spent);
			$out['weapons'][] = array(
				'type' => 'weapon', 'id' => $id, 'name' => $g['weapon_name'],
				'level' => $lv, 'left' => $left, 'qty' => $qty,
				'slug' => gaunt_weapon_slug($g['weapon_name']),
				'sfx'  => gaunt_weapon_sfx($g['weapon_name']),
			);
		} elseif ($g['type'] === 'armor') {
			$lv    = (int)$g['armor_level'];
			$qty   = (int)$g['quantity'];
			$spent = (int)($gs['arm'][$id] ?? 0);
			$left  = max(0, gaunt_weapon_uses($lv, $qty) - $spent);
			$out['armor'][] = array(
				'type' => 'armor', 'id' => $id, 'name' => $g['armor_name'],
				'level' => $lv, 'left' => $left, 'qty' => $qty, 'sfx' => 'equip',
			);
		}
	}
	/*
	 * SALVAGE IS OFFERED ALONGSIDE OWNED GEAR, under a NEGATIVE id so the
	 * two can never be confused: a positive id is something the player
	 * owns and whose wear is tracked against their inventory, a negative
	 * one is loot that exists only for this run.
	 */
	foreach ($gs['loot'] as $i => $lt) {
		$left = max(0, (int)($lt['left'] ?? 0));
		$id   = -($i + 1);
		if ($lt['kind'] === 'weapon') {
			$out['weapons'][] = array('type' => 'weapon', 'id' => $id, 'loot' => true,
				'name' => 'Salvaged weapon', 'level' => (int)$lt['level'], 'left' => $left,
				'slug' => 'fist', 'sfx' => 'melee');
		} elseif ($lt['kind'] === 'armor') {
			$out['armor'][] = array('type' => 'armor', 'id' => $id, 'loot' => true,
				'name' => 'Salvaged armour', 'level' => (int)$lt['level'], 'left' => $left,
				'sfx' => 'equip');
		} elseif ($lt['kind'] === 'heal' && $left > 0) {
			$out['heals'][] = array('type' => 'heal', 'id' => $id, 'loot' => true,
				'pct' => (int)$lt['pct'], 'have' => $left, 'sfx' => 'heal');
		}
	}

	/*
	 * THE TWO REWARD ITEMS. They change the PAYOUT, not the fight, and
	 * they are the only economic decision in the game -- turning them
	 * into combat effects would leave every item a combat button.
	 * Offered mid-fight on purpose: committing one while you can see how
	 * it is going is a read, where the old loadout screen made it a
	 * blind bet.
	 */
	$staked = isset($fight['staked']) && is_array($fight['staked']) ? $fight['staked'] : array();
	foreach (array(GAUNTLET_C_DOUBLE => 'Double rewards',
	               GAUNTLET_C_RANDOM => 'Random currency') as $cid => $label) {
		$n = getCurrentAmount($conn, $uid, $cid);
		if ($n < 1 || in_array($cid, $staked, true)) continue;
		$out['stakes'][] = array('type' => 'stake', 'id' => $cid, 'name' => $label,
			'have' => (int)$n, 'sfx' => 'equip');
	}

	foreach (array(GAUNTLET_C_100, GAUNTLET_C_75, GAUNTLET_C_50, GAUNTLET_C_25) as $cid) {
		$n = getCurrentAmount($conn, $uid, $cid);
		if ($n < 1) continue;
		$out['heals'][] = array('type' => 'heal', 'id' => $cid,
			'pct' => gaunt_heal_pct($cid), 'have' => (int)$n, 'sfx' => 'heal');
	}
	return $out;
}

/* ------------------------------------------------------------- a turn */

/** Open the fight for a pending encounter, if it has not been opened. */
function gaunt_fight_start($conn, $user_id, $enc) {
	$enc_id = (int)$enc['id'];
	$have   = gaunt_fight_get($conn, $enc_id);
	if ($have) return $have;

	$run_id = (int)$enc['run_id'];
	$peff_r = @$conn->query("SELECT effective_project_id FROM gauntlets_nfts
	                          WHERE run_id=$run_id AND nft_id=" . (int)$enc['player_nft_id'] . " LIMIT 1");
	$peff   = ($peff_r && $peff_r->num_rows) ? (int)$peff_r->fetch_assoc()['effective_project_id'] : 0;
	$wc     = gauntletCalculateWinChance($peff, (int)$enc['opponent_effective_project_id']);

	$hp = gaunt_nft_hp($conn, $run_id, (int)$enc['player_nft_id']);
	$f  = gaunt_begin(array('hp' => $hp, 'max' => GAUNT_HP_BASE), $wc,
		gaunt_kit_for($conn, (int)$enc['opponent_user_id'], (int)$user_id),
		/* Seeded from the encounter so a replay is exact and a reload
		   cannot re-roll a fight into a better one. */
		crc32('gauntlet:' . $enc_id));
	gaunt_fight_save($conn, $enc_id, $f);
	return $f;
}

/**
 * Play one turn. Returns ['ok'=>bool, 'why'=>string, 'timeline'=>[], 'fight'=>[]].
 *
 * EVERY ACTION IS CHECKED AGAINST WHAT THE PLAYER ACTUALLY HAS before the
 * engine sees it. The client is a renderer.
 */
function gaunt_fight_turn($conn, $user_id, $enc_id, $kind, $item_id = 0) {
	$uid = (int)$user_id; $e = (int)$enc_id; $item_id = (int)$item_id;

	$r = @$conn->query("SELECT ge.*, g.user_id AS owner FROM gauntlets_encounters ge
	                    INNER JOIN gauntlets g ON g.id = ge.run_id
	                    WHERE ge.id=$e AND ge.outcome='pending' LIMIT 1");
	if (!$r || !$r->num_rows) return array('ok' => false, 'why' => 'no such fight');
	$enc = $r->fetch_assoc();
	if ((int)$enc['owner'] !== $uid) return array('ok' => false, 'why' => 'not your fight');

	$fight = gaunt_fight_get($conn, $e);
	if (!$fight) $fight = gaunt_fight_start($conn, $uid, $enc);
	if (!empty($fight['over'])) return array('ok' => false, 'why' => 'this fight is over');

	$run_id = (int)$enc['run_id'];
	$load   = gaunt_loadout($conn, $uid, $run_id, $fight);
	$gs     = gaunt_gear_state($conn, $run_id);
	$act    = null;

	if ($kind === 'weapon') {
		foreach ($load['weapons'] as $w) if ($w['id'] === $item_id && $w['left'] > 0) $act = $w;
		if (!$act) return array('ok' => false, 'why' => 'no swings left with that weapon');
		if ($item_id < 0) $gs['loot'][-$item_id - 1]['left']--;
		else $gs['wpn'][$item_id] = (int)($gs['wpn'][$item_id] ?? 0) + 1;

	} elseif ($kind === 'armor') {
		foreach ($load['armor'] as $a) if ($a['id'] === $item_id && $a['left'] > 0) $act = $a;
		if (!$act) return array('ok' => false, 'why' => 'that armour is spent');
		if ($item_id < 0) $gs['loot'][-$item_id - 1]['left']--;
		else $gs['arm'][$item_id] = (int)($gs['arm'][$item_id] ?? 0) + 1;

	} elseif ($kind === 'heal') {
		foreach ($load['heals'] as $h) if ($h['id'] === $item_id) $act = $h;
		if (!$act) return array('ok' => false, 'why' => 'you have none of those');
		if ($item_id < 0) {
			/* Salvage, so nothing leaves the inventory. */
			$gs['loot'][-$item_id - 1]['left']--;
		} else {
			/* Genuinely consumed, unlike gear. */
			updateAmount($conn, $uid, $item_id, -1);
		}

	} elseif ($kind === 'stake') {
		foreach ($load['stakes'] as $k) if ($k['id'] === $item_id) $act = $k;
		if (!$act) return array('ok' => false, 'why' => 'you have none of those, or it is already staked');

	} else {
		$act = $load['bare'];
	}

	$timeline = gaunt_act($fight, $act);
	gaunt_fight_save($conn, $e, $fight);
	if ($kind === 'weapon' || $kind === 'armor' || $item_id < 0)
		gaunt_gear_state_save($conn, $run_id, $gs);

	/* The carried pool is written every turn, not just at the end: a
	   player who closes the tab mid-fight should come back to the damage
	   they actually took. */
	gaunt_nft_hp_save($conn, $run_id, (int)$enc['player_nft_id'], (int)$fight['you']['hp']);

	/*
	 * SPOILS, when the fight is won. Taken from what the opponent
	 * actually played, usable immediately, and gone when the run ends --
	 * relief that keeps a bad run alive, not income. Project points for
	 * the slaying are paid separately and are untouched.
	 */
	if (!empty($fight['over']) && ($fight['win'] ?? '') === 'you') {
		$drop = gaunt_roll_drop($fight);
		if ($drop) {
			$gs['loot'][] = array('kind' => $drop['kind'],
				'level' => (int)($drop['level'] ?? 0), 'pct' => (int)($drop['pct'] ?? 0),
				'left'  => (int)$drop['uses']);
			gaunt_gear_state_save($conn, $run_id, $gs);
			$timeline[] = array('who' => 'you', 'kind' => 'loot', 'sfx' => 'equip',
				'text' => 'Salvaged their ' . ($drop['kind'] === 'heal' ? 'medkit'
				          : ($drop['kind'] === 'armor' ? 'armour' : 'weapon')));
		}
	}

	return array('ok' => true, 'why' => '', 'timeline' => $timeline, 'fight' => $fight);
}

/**
 * Close a finished fight: record the result and pay it out.
 *
 * Delegates to gauntletResolveEncounter() with the outcome FORCED, so
 * every reward rule -- the currency, the transaction, the doubling --
 * stays in the one place it already lived. No consumables or gear are
 * passed: those were spent turn by turn during the fight, not staked
 * up front the way the old loadout screen did it.
 */
function gauntletResolveFight($conn, $user_id, $enc_id) {
	$f = gaunt_fight_get($conn, (int)$enc_id);
	if (!$f || empty($f['over'])) return false;
	$won = (($f['win'] ?? '') === 'you');
	/* The reward items committed during the fight. Passed rather than
	   spent here: gauntletResolveEncounter() checks the player still
	   holds each one, deducts it and applies its rule, exactly as it did
	   when they were staked up front on the old loadout screen. */
	$staked = isset($f['staked']) && is_array($f['staked']) ? $f['staked'] : array();
	return gauntletResolveEncounter($conn, (int)$user_id, (int)$enc_id,
		$staked, 0, 0, $won ? 'win' : 'loss');
}

/**
 * The backdrop for an encounter: the Monstrocity theme art for the
 * OPPONENT's project, so the fight is staged in their world.
 *
 * MATCHED THREE WAYS, MOST PRECISE FIRST, and the first version got
 * this wrong by matching only on project NAME. monstrocity-schema.md is
 * explicit: `project_id` is the real link and is "NULLABLE and mostly
 * NULL", while `project_name` is the string that "stands on its own"
 * for the many themes whose project is not on Skulliance at all. So the
 * themes that DO correspond to a real project are exactly the ones a
 * name match is least likely to find.
 *
 *   1. the collection -- monstrocity_theme_collections is the join a
 *      theme uses when it covers specific collections, and the
 *      opponent's NFT belongs to one;
 *   2. the project id -- the canonical link when the theme has one;
 *   3. the project name -- the legacy fallback, kept last.
 *
 * Gated on the theme's own `background` flag either way, because that
 * is what says the image is actually there. No flag, no backdrop,
 * rather than a 404 behind the fight.
 */
function gaunt_theme_bg($conn, $project_name, $project_id = 0, $collection_id = 0) {
	$key = (int)$collection_id . ':' . (int)$project_id . ':' . $project_name;
	static $memo = array();
	if (isset($memo[$key])) return $memo[$key];

	$slug = '';
	$cid  = (int)$collection_id;
	$pid  = (int)$project_id;

	if ($cid > 0) {
		$r = @$conn->query("SELECT t.slug FROM monstrocity_theme_collections tc
		                    INNER JOIN monstrocity_themes t ON t.id = tc.theme_id
		                    WHERE tc.collection_id = $cid AND t.active = 1
		                      AND t.background = 1 LIMIT 1");
		if ($r && $r->num_rows) $slug = (string)$r->fetch_assoc()['slug'];
	}
	if ($slug === '' && $pid > 0) {
		$r = @$conn->query("SELECT slug FROM monstrocity_themes
		                    WHERE project_id = $pid AND active = 1 AND background = 1 LIMIT 1");
		if ($r && $r->num_rows) $slug = (string)$r->fetch_assoc()['slug'];
	}
	if ($slug === '' && trim((string)$project_name) !== '') {
		$esc = $conn->real_escape_string(trim((string)$project_name));
		$r = @$conn->query("SELECT slug FROM monstrocity_themes
		                    WHERE project_name = '$esc' AND active = 1 AND background = 1 LIMIT 1");
		if ($r && $r->num_rows) $slug = (string)$r->fetch_assoc()['slug'];
	}

	$memo[$key] = $slug === '' ? ''
		: '/staking/images/monstrocity/' . rawurlencode($slug) . '/monstrocity.png';
	return $memo[$key];
}

/* ---------------------------------------------------------- the chest */

/** How much salvage this run took off opponents. */
function gaunt_run_haul($conn, $run_id) {
	$gs = gaunt_gear_state($conn, $run_id);
	return count($gs['loot']);
}

/** Wins banked in a run. */
function gaunt_run_wins($conn, $run_id) {
	$rid = (int)$run_id;
	$r = @$conn->query("SELECT COUNT(*) AS c FROM gauntlets_encounters
	                     WHERE run_id=$rid AND outcome='win' AND resolved_date IS NOT NULL");
	return ($r && $r->num_rows) ? (int)$r->fetch_assoc()['c'] : 0;
}

/** Is there a chest waiting on this run? */
function gaunt_chest_pending($conn, $run_id) {
	$rid = (int)$run_id;
	$r = @$conn->query("SELECT chest FROM gauntlets WHERE id=$rid LIMIT 1");
	if (!$r || !$r->num_rows) return false;
	if ((int)$r->fetch_assoc()['chest'] !== 0) return false;
	return gaunt_run_wins($conn, $rid) > 0;
}

/**
 * Open it. Grants real consumables -- the ONE thing that leaves a run.
 *
 * NOT `gauntlets.reward`. That column is the batch payout's own flag
 * (db.php: "UPDATE gauntlets SET reward = 1 WHERE reward = 0"), so
 * flipping it here would make the cron skip paying the run out. The
 * chest gets its own.
 *
 * Returns consumable_id => quantity granted, or array() if there was
 * nothing to open.
 */
function gaunt_chest_claim($conn, $user_id, $run_id) {
	$uid = (int)$user_id; $rid = (int)$run_id;
	$r = @$conn->query("SELECT id FROM gauntlets WHERE id=$rid AND user_id=$uid LIMIT 1");
	if (!$r || !$r->num_rows) return array();
	if (!gaunt_chest_pending($conn, $rid)) return array();

	$got = gaunt_chest_contents(gaunt_run_wins($conn, $rid), gaunt_run_haul($conn, $rid),
		crc32('gauntlet-chest:' . $rid));

	/* CLAIMED FIRST, and only then granted. The other order lets a
	   double submit pay twice; this one can at worst pay nothing, which
	   is recoverable by hand and is not someone minting consumables. */
	$upd = @$conn->query("UPDATE gauntlets SET chest=1 WHERE id=$rid AND chest=0");
	if (!$upd || $conn->affected_rows < 1) return array();

	foreach ($got as $cid => $qty) updateAmount($conn, $uid, (int)$cid, (int)$qty);
	return $got;
}

/**
 * Annotate hand cards with the health they are carrying.
 *
 * WITHOUT THIS, FAST FORWARD IS A BLIND SWAP. Health persists across a
 * run, so "should I swap?" is entirely a question about the
 * replacement's condition -- offering the choice without showing it is
 * offering nothing. A card already at zero is marked down: it is out
 * for the run, and swapping to a corpse would end the fight instantly.
 */
function gaunt_hand_hp($conn, $run_id, $cards) {
	$out = array();
	foreach ($cards as $c) {
		$hp = gaunt_nft_hp($conn, $run_id, (int)$c['id']);
		$c['hp']   = (int)$hp;
		$c['max']  = GAUNT_HP_BASE;
		$c['down'] = ((int)$hp <= 0);
		$out[] = $c;
	}
	return $out;
}
