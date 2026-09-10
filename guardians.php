<?php
/*
 * REALM GUARDIANS -- tower defense built on the player's own realm.
 *
 * Answers one question: is keeping locations stocked under a wave clock fun?
 * All seven locations are live now, and the baseline comes from the player's
 * REAL realm. See realm-guardians.md for the full design.
 *
 * ---------------------------------------------------------------------------
 * IT CANNOT DAMAGE REALMS OR RAIDS. THAT IS STRUCTURAL, NOT A PROMISE.
 * ---------------------------------------------------------------------------
 * Every query below is a SELECT. There is no INSERT, UPDATE, DELETE, REPLACE,
 * TRUNCATE, ALTER or DROP anywhere in this file, and no migration.
 *
 * It takes a SNAPSHOT and plays with copies. A guardian dying in a siege is a
 * number decrementing in a JS object -- it does not touch `soldiers`, does not
 * set `dead`, does not consume a weapon from `gear`, and does not change a
 * location level. Play it a hundred times and your realm is exactly as you left
 * it. That guarantee is the whole reason this can ship next to raids.
 *
 * Nothing UNSCOPED links here any more: it is in the nav, tiled on the
 * Launchpad, and it has a monthly leaderboard. Persistence, scoring and the
 * Discord post live in guardians-lib.php -- including the migrations, which
 * are NOT in this file. See that file's header for the trust boundary a
 * client-simulated game creates the moment CARBON is on the board.
 *
 * ---------------------------------------------------------------------------
 * WHY THE SIMULATION IS DETERMINISTIC
 * ---------------------------------------------------------------------------
 * Fixed timestep, seeded PRNG, no Math.random, actions recorded with their tick.
 * The real version has to accept a result on a board that pays CARBON, and the
 * only honest way is replaying the player's inputs server-side rather than
 * trusting a reported score. Building it any other way now means throwing it
 * away later -- the Skull Racer ghost-trace lesson, applied before it bites.
 */
include 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';
// Persistence only. The realm snapshot below stays SELECT-only; every write
// this game makes is to its own two tables and happens in guardians-lib.php,
// reached through ajax/guardians-save.php.
include_once 'guardians-lib.php';

$rg_me = intval($_SESSION['userData']['user_id'] ?? 0);

// A siege already in progress, if there is one. Never scored -- it only puts
// the player back where they were.
$rg_saved = function_exists('guardiansLoadRun') ? guardiansLoadRun($conn, $rg_me) : null;

/*
 * `soldiers.location` IS A STATE, NOT A `locations.id`.
 *
 * This cost a bug worth recording. Reading the Tower's row out of
 * realms_locations gives a locations.id, and matching soldiers against it looks
 * entirely reasonable -- but the column means something else, so the garrison
 * came back wrong (reported as 20 against 10 actually stationed).
 *
 * The real values, from db.php:
 *   1  reserve / barracks   -- createSoldier() inserts with location 1
 *   2  Tower garrison       -- deploy sets 2 (db.php:10238), the raid defence
 *                              garrison reads 2 (db.php:10730)
 *   3  out on a raid        -- commit sets 3 (db.php:10667), and it is reset to
 *                              1 when the raid resolves (db.php:7922)
 *
 * `soldiers.raid_id` is NOT the signal for "raiding" either -- raid membership
 * lives in raids_soldiers, and location 3 is what the platform itself checks.
 */
define('RG_LOC_RESERVE', 1);
define('RG_LOC_TOWER',   2);
define('RG_LOC_RAID',    3);

/* ---------------------------------------------------------------------------
 * THE SNAPSHOT. Read-only, and the only reason this file touches the database.
 * --------------------------------------------------------------------------- */
$rg_levels = array('tower'=>0,'barracks'=>0,'armory'=>0,'crypt'=>0,'portal'=>0,'factory'=>0,'mine'=>0);
$rg_army = 0; $rg_armed = 0; $rg_cache = 0; $rg_wlevel = 1; $rg_realm_name = '';
$rg_has_realm = false;
$rg_units = array();   // the player's own soldiers, as NFT art
$rg_acache = 0;        // unissued armor pieces
// Each guardian's ACTUAL kit: {w: weapon level, a: armour level}, 0 for neither.
$rg_kit_tower = array(); $rg_kit_raid = array(); $rg_kit_reserve = array();
// The unissued cache, expanded by quantity into a pool of levels.
$rg_wpool = array(); $rg_apool = array();
// The weapon and armour catalogues, so a forged tier becomes a real item.
$rg_wcat = array(); $rg_acat = array();
$rg_crypt = 0;         // enlisted NFTs currently dead -- they start in the Crypt
$rg_theme = 0;         // the realm's theme, used as the page backdrop
$rg_garrison = 0; $rg_g_armed = 0; $rg_g_armored = 0;   // already on the wall
$rg_raiders  = 0; $rg_r_armed = 0; $rg_r_armored = 0;   // already in the field
$rg_alevel = 1;        // best armor level, decides how much a breach is absorbed

if ($rg_me > 0) {
	$rr = $conn->query("SELECT id, name, theme_id FROM realms WHERE user_id = $rg_me AND active = 1 LIMIT 1");
	if ($rr && $rr->num_rows > 0) {
		$rrow = $rr->fetch_assoc();
		$rg_realm_id = intval($rrow['id']);
		$rg_realm_name = (string)$rrow['name'];
		$rg_has_realm = true;
		$rg_theme = intval($rrow['theme_id'] ?? 0);

		// Levels by NAME, not by hardcoded location id -- ids are data, and a
		// reordered locations table should not silently rewire the game.
		$lr = $conn->query("SELECT l.name, rl.level FROM realms_locations rl
		                    INNER JOIN locations l ON l.id = rl.location_id
		                    WHERE rl.realm_id = $rg_realm_id");
		if ($lr) while ($l = $lr->fetch_assoc()) {
			$k = strtolower(trim($l['name']));
			if (array_key_exists($k, $rg_levels)) $rg_levels[$k] = intval($l['level']);
		}

		// The army: enlisted NFTs still alive. Same conditions the realm itself
		// uses everywhere -- dead IS NULL AND active = 1.
		$sr = $conn->query("SELECT COUNT(*) AS cnt FROM soldiers
		                    WHERE realm_id = $rg_realm_id AND dead IS NULL AND active = 1");
		if ($sr && $sr->num_rows) $rg_army = intval($sr->fetch_assoc()['cnt']);

		$ar = $conn->query("SELECT COUNT(*) AS cnt FROM soldiers
		                    WHERE realm_id = $rg_realm_id AND dead IS NULL AND active = 1
		                    AND weapon_id > 0");
		if ($ar && $ar->num_rows) $rg_armed = intval($ar->fetch_assoc()['cnt']);

		/*
		 * THE DEAD COUNT TOO -- they start in the Crypt.
		 *
		 * The roster query above filters on `dead IS NULL`, so a realm with 100
		 * enlisted NFTs but seven in the crypt reported 93 and the player was
		 * right to notice the shortfall. Those seven are not gone; they are
		 * exactly what the Crypt is for. They now seed the in-game Crypt, so the
		 * Raise button has something to work with from the first wave.
		 *
		 * Soldiers away on raids need no special handling: they are alive and
		 * active, so the roster query already counts them. Nothing here filters
		 * on raid_id or location, deliberately -- a guardian on a raid is still
		 * one of yours.
		 */
		$cr = $conn->query("SELECT COUNT(*) AS cnt FROM soldiers
		                    WHERE realm_id = $rg_realm_id AND dead IS NOT NULL AND active = 1");
		if ($cr && $cr->num_rows) $rg_crypt = intval($cr->fetch_assoc()['cnt']);

		/*
		 * THE GARRISON IS ALREADY ON THE WALL.
		 *
		 * Soldiers stationed at the realm's Tower were being counted as reserve,
		 * so a siege opened with an empty wall and the player had to deploy
		 * troops that were, in their realm, already standing there. They now
		 * start in the garrison.
		 *
		 * Their gear comes with them: a soldier's weapon_id and armor_id are
		 * their OWN equipment, distinct from the unissued cache in `gear`, so
		 * seeding them armed and armoured costs the cache nothing.
		 */
		/*
		 * EVERY SOLDIER'S ACTUAL KIT, not a headcount and a single MAX level.
		 *
		 * The first pass counted "how many carry something" and then applied the
		 * best level in the CACHE to all of them, so a level-1 pistol and a
		 * level-10 launcher were worth exactly the same and the cache's best
		 * item flattered every soldier who had anything at all.
		 *
		 * Each row is one guardian's real equipment: w = their weapon's level,
		 * a = their armour's level, 0 for neither. Damage and survival are
		 * computed per guardian from these, so a well-kitted army genuinely
		 * outfights a nominally-armed one.
		 *
		 * LEFT JOINs because weapon_id/armor_id are 0 for the unequipped, and an
		 * INNER JOIN would silently drop exactly the soldiers being counted.
		 */
		$rg_kit_sql = "SELECT soldiers.location AS loc,
		                      COALESCE(w.level,0) AS wl, COALESCE(w.name,'') AS wn,
		                      COALESCE(a.level,0) AS al, COALESCE(a.name,'') AS an
		               FROM soldiers
		               LEFT JOIN weapons w ON w.id = soldiers.weapon_id
		               LEFT JOIN armor   a ON a.id = soldiers.armor_id
		               WHERE soldiers.realm_id = $rg_realm_id
		                 AND soldiers.dead IS NULL AND soldiers.active = 1
		               ORDER BY COALESCE(w.level,0) DESC, COALESCE(a.level,0) DESC
		               LIMIT 400";
		$kr = $conn->query($rg_kit_sql);
		if ($kr) while ($k2 = $kr->fetch_assoc()) {
			// The NAME travels with the level. It is what picks both the icon a
			// guardian wears and the sound their weapon makes -- without it every
			// guardian wore the cache's best weapon and fired a random noise.
			$kit = array('w' => intval($k2['wl']), 'wn' => (string)$k2['wn'],
			             'a' => intval($k2['al']), 'an' => (string)$k2['an']);
			$loc = intval($k2['loc']);
			if     ($loc === RG_LOC_TOWER) $rg_kit_tower[]   = $kit;
			elseif ($loc === RG_LOC_RAID)  $rg_kit_raid[]    = $kit;
			else                           $rg_kit_reserve[] = $kit;
		}

		/*
		 * BREAK UP THE GEAR GRADIENT.
		 *
		 * The ORDER BY above exists for one reason: if a realm has more than 400
		 * soldiers, LIMIT has to keep the best rather than an arbitrary 400. But
		 * it also hands each bucket over sorted by weapon level, with armour as
		 * the tiebreaker -- so any batch read off the FRONT is the top weapon
		 * tier to a man (identically armed) while their armour, the secondary
		 * key, still varies. It reads as a bug in the weapon lookup and is
		 * really the sort order showing through.
		 *
		 * Two buckets are read as batches and both need this: the raid line,
		 * which is drawn across the field all at once, and the reserve, which a
		 * Strike shifts a handful off the front of. The Tower bucket is left
		 * alone deliberately -- it wants the best soldiers first, because they
		 * man the wall up to the cap.
		 *
		 * Stride through the sorted list instead of reading it front to back.
		 * Deterministic -- no shuffle(), no rand() -- so the snapshot a replay
		 * reconstructs is byte-identical to this one.
		 */
		$rg_destride = function ($rows) {
			if (count($rows) <= 2) return $rows;
			$stride = max(2, (int)round(sqrt(count($rows))));
			$mixed = array();
			for ($off = 0; $off < $stride; $off++) {
				for ($i = $off; $i < count($rows); $i += $stride) $mixed[] = $rows[$i];
			}
			return $mixed;
		};
		$rg_kit_raid    = $rg_destride($rg_kit_raid);
		$rg_kit_reserve = $rg_destride($rg_kit_reserve);

		/*
		 * SOLDIERS ON RAIDS ARE ALREADY IN THE FIELD.
		 *
		 * They are out attacking somebody right now, which is exactly what a
		 * sortie is -- so they open the siege having already stepped through the
		 * Portal rather than waiting in the barracks. raid_id is the explicit
		 * signal; `location IN(2,3)` in db.php conflates deployment states and
		 * would be a guess.
		 */
		$rr3 = $conn->query("SELECT COUNT(*) AS cnt,
		                            COALESCE(SUM(CASE WHEN weapon_id > 0 THEN 1 ELSE 0 END),0) AS armed,
		                            COALESCE(SUM(CASE WHEN armor_id  > 0 THEN 1 ELSE 0 END),0) AS armored
		                     FROM soldiers
		                     WHERE realm_id = $rg_realm_id AND location = " . RG_LOC_RAID . "
		                       AND dead IS NULL AND active = 1");
		if ($rr3 && $rr3->num_rows) {
			$r3 = $rr3->fetch_assoc();
			$rg_raiders   = intval($r3['cnt']);
			$rg_r_armed   = intval($r3['armed']);
			$rg_r_armored = intval($r3['armored']);
		}

		{
			$tr = $conn->query("SELECT COUNT(*) AS cnt,
			                           COALESCE(SUM(CASE WHEN weapon_id > 0 THEN 1 ELSE 0 END),0) AS armed,
			                           COALESCE(SUM(CASE WHEN armor_id  > 0 THEN 1 ELSE 0 END),0) AS armored
			                    FROM soldiers
			                    WHERE realm_id = $rg_realm_id AND location = " . RG_LOC_TOWER . "
			                      AND dead IS NULL AND active = 1");
			if ($tr && $tr->num_rows) {
				$t = $tr->fetch_assoc();
				$rg_garrison  = intval($t['cnt']);
				$rg_g_armed   = intval($t['armed']);
				$rg_g_armored = intval($t['armored']);
			}
		}

		/*
		 * THE GUARDIANS THEMSELVES. Soldiers are enlisted NFTs, so the units
		 * riding out of the Portal wear their real artwork -- your skulls going
		 * out against their faces. Symmetry with the avatar horde, and it costs
		 * nothing: the art is already cached on this server.
		 *
		 * LOCAL CACHE ONLY, the same rule Obscura settled on: getIPFS() falls
		 * back to a public gateway that is slow and often fails outright, and a
		 * unit that never renders is worse than a plain marker. Files under 1KB
		 * are treated as truncated. No art simply means plain markers.
		 */
		$ur = $conn->query("SELECT nfts.ipfs, nfts.name, nfts.collection_id, collections.project_id
		                    FROM soldiers
		                    INNER JOIN nfts ON nfts.id = soldiers.nft_id
		                    INNER JOIN collections ON collections.id = nfts.collection_id
		                    WHERE soldiers.realm_id = $rg_realm_id
		                      AND soldiers.dead IS NULL AND soldiers.active = 1
		                    LIMIT 40");
		if ($ur) while ($u = $ur->fetch_assoc()) {
			$ipfs = (string)$u['ipfs'];
			if ($ipfs === '' || strpos($ipfs, 'data:image/svg+xml;base64') === 0) continue;
			$pid = intval($u['project_id']); $cid = intval($u['collection_id']);
			$hit = glob(__DIR__ . '/images/nfts/' . $pid . '/' . $cid . '/' . md5($ipfs) . '.*');
			if (empty($hit) || @filesize($hit[0]) < 1024) continue;
			$rg_units[] = array(
				'name' => (string)($u['name'] ?? ''),
				'img'  => '/staking/images/nfts/' . $pid . '/' . $cid . '/' . md5($ipfs) . '.'
				          . pathinfo($hit[0], PATHINFO_EXTENSION),
			);
		}
	}

	// The weapon cache is per USER, not per realm -- gear is inventory.
	$gr = $conn->query("SELECT COALESCE(SUM(g.quantity),0) AS qty,
	                           COALESCE(MAX(w.level),1) AS lvl
	                    FROM gear g
	                    INNER JOIN weapons w ON w.id = g.item_id
	                    WHERE g.user_id = $rg_me AND g.type = 'weapon' AND g.quantity > 0");
	if ($gr && $gr->num_rows) {
		$g = $gr->fetch_assoc();
		$rg_cache  = intval($g['qty']);
		$rg_wlevel = max(1, intval($g['lvl']));
	}

	/*
	 * The best weapon in the cache, worn by armed guardians on the field.
	 *
	 * Icon path is built exactly as cryptcrawl-render.php:379 builds it --
	 * icons/<lowercase name, spaces to dashes>.png -- which is also what the
	 * Armory modal in Realms uses. All ten verified 200 before shipping; note
	 * the dashes matter ("Machine Gun" is machine-gun.png, and machinegun.png
	 * is a 404).
	 */
	/*
	 * THE CACHE IS A POOL OF VARIED GEAR, not a quantity and a best level.
	 *
	 * Unissued gear has its own spread of levels, and issuing it should hand out
	 * what is actually in there -- a mix -- rather than pretending every piece
	 * is the best one owned. Expanded by quantity so a stack of five level-2
	 * weapons really is five draws at level 2, and capped so a large inventory
	 * cannot bloat the page.
	 */
	$pr = $conn->query("SELECT g.type, COALESCE(w.level, a.level, 1) AS lvl,
	                           COALESCE(w.name, a.name, '') AS nm, g.quantity
	                    FROM gear g
	                    LEFT JOIN weapons w ON g.type = 'weapon' AND w.id = g.item_id
	                    LEFT JOIN armor   a ON g.type = 'armor'  AND a.id = g.item_id
	                    WHERE g.user_id = $rg_me AND g.quantity > 0
	                      AND g.type IN ('weapon','armor')");
	if ($pr) while ($pw = $pr->fetch_assoc()) {
		$piece = array('lvl' => max(1, intval($pw['lvl'])), 'name' => (string)$pw['nm']);
		$qty = min(200, intval($pw['quantity']));
		for ($i = 0; $i < $qty; $i++) {
			if ($pw['type'] === 'weapon') { if (count($rg_wpool) < 300) $rg_wpool[] = $piece; }
			else                          { if (count($rg_apool) < 300) $rg_apool[] = $piece; }
		}
	}
	// Best first, so issuing draws the good stuff before the dregs -- which is
	// what a quartermaster would do and keeps the early waves feeling equipped.
	$rg_bylvl = function ($x, $y) { return $y['lvl'] - $x['lvl']; };
	usort($rg_wpool, $rg_bylvl);
	usort($rg_apool, $rg_bylvl);

	/*
	 * ARMOR. Soldiers carry armor_id as well as weapon_id, and gear holds the
	 * unissued pieces -- so protection is already part of the realm and was
	 * simply missing here. Weapons decide how hard a guardian hits; armor
	 * decides whether they walk away from a breach.
	 */
	$rr2 = $conn->query("SELECT COALESCE(SUM(g.quantity),0) AS qty,
	                            COALESCE(MAX(a.level),1) AS lvl
	                     FROM gear g
	                     INNER JOIN armor a ON a.id = g.item_id
	                     WHERE g.user_id = $rg_me AND g.type = 'armor' AND g.quantity > 0");
	if ($rr2 && $rr2->num_rows) {
		$r2 = $rr2->fetch_assoc();
		$rg_acache = intval($r2['qty']);
		$rg_alevel = max(1, intval($r2['lvl']));
	}
}

/*
 * The catalogues, so gear FORGED in-game gets a real item rather than a bare
 * tier number. A rolled tier looks up the weapon or armour of that level, so
 * the icon it wears and the sound it makes are the platform's own.
 *
 * OUTSIDE the realm gate: this is the platform's reference data, not anything
 * about one player. It used to be read only when the player had a realm, which
 * meant a conscript's catPick() found nothing and their forge could never
 * produce a single item -- the Armory bar filled forever and handed back null.
 */
$cwr = $conn->query("SELECT name, level FROM weapons ORDER BY level ASC");
if ($cwr) while ($cw = $cwr->fetch_assoc()) {
	$rg_wcat[] = array('name' => (string)$cw['name'], 'lvl' => intval($cw['level']));
}
$car = $conn->query("SELECT name, level FROM armor ORDER BY level ASC");
if ($car) while ($ca = $car->fetch_assoc()) {
	$rg_acat[] = array('name' => (string)$ca['name'], 'lvl' => intval($ca['level']));
}

/*
 * THE CONSCRIPT FLOOR. No realm means no enlisted soldiers and nothing to
 * deploy -- not a weak position, an empty one. Without this the entry-level
 * siege is unplayable rather than merely hard, and the game recruits nobody.
 *
 * This is also what the "from scratch" toggle switches a realm-holder to, so
 * it is built as a standalone baseline rather than as a mutation of $rg_*.
 *
 * The starting cache is REAL pieces off the bottom of the catalogue, not a
 * bare count. It used to set $rg_cache = 2 while leaving the pool empty, so
 * the blurb promised two weapons the quartermaster did not have and the HUD
 * showed zero -- the numbers disagreed with the game.
 */
$rg_floor_lvls = array('tower'=>1,'barracks'=>1,'armory'=>1,'crypt'=>1,'portal'=>1,'factory'=>1,'mine'=>1);
$rg_floor_pool = function ($cat, $n) {
	$out = array();
	if (empty($cat)) return $out;
	$first = $cat[0];   // catalogue is ordered by level ASC, so this is tier 1
	for ($i = 0; $i < $n; $i++) $out[] = array('lvl' => intval($first['lvl']), 'name' => (string)$first['name']);
	return $out;
};
$rg_scratch = array(
	'levels' => $rg_floor_lvls,
	'army'   => 4,  'armed'  => 1,
	'cache'  => 2,  'acache' => 1,
	'wlevel' => 1,  'alevel' => 1,
	'start'  => 1,  'crypt'  => 0,
	'wpool'  => $rg_floor_pool($rg_wcat, 2),
	'apool'  => $rg_floor_pool($rg_acat, 1),
	// No realm means no enlisted NFTs: nobody on the wall, nobody raiding, and
	// an empty reserve. The Barracks is the only source of guardians.
	'kitTower' => array(), 'kitRaid' => array(), 'kitReserve' => array(),
	'garrison' => 0, 'garmed' => 0, 'garmored' => 0,
	'raiders'  => 0, 'ramed'  => 0, 'rarmored' => 0,
);

if (!$rg_has_realm) {
	$rg_levels = $rg_floor_lvls;
	$rg_army   = $rg_scratch['army'];
	$rg_armed  = $rg_scratch['armed'];
	$rg_cache  = $rg_scratch['cache'];
	$rg_acache = $rg_scratch['acache'];
	$rg_wpool  = $rg_scratch['wpool'];
	$rg_apool  = $rg_scratch['apool'];
}

/*
 * THE FACTORY ROLLS REAL CONSUMABLES, using the platform's OWN odds.
 *
 * getFactoryOdds() lives in db.php:10499 and is what the Realms factory modal
 * shows players, so this CALLS it rather than copying the table -- one source,
 * and a balance change there reaches the game for free.
 */
$rg_fodds = function_exists('getFactoryOdds')
	? getFactoryOdds(max(1, $rg_levels['factory']))
	: array(1 => 100);
$rg_con_names = array(
	1 => 'Random Reward', 2 => '25% Success', 3 => 'Fast Forward', 4 => '50% Success',
	5 => '75% Success',   6 => 'Double Rewards', 7 => '100% Success',
);
/*
 * WHAT THE BUTTON SAYS, AND WHAT THE ITEM IS.
 *
 * Realms names these consumables for what they do THERE -- "Double Rewards" is
 * a rewards multiplier on a location. In a siege, with the horde on the wall,
 * that name tells you nothing about whether to press it. So each button leads
 * with what it does HERE and carries the Realms name underneath, which keeps
 * the two learnable as the same object without making the button cryptic at
 * the exact moment it matters.
 *
 * Order is crisis-first and FIXED -- the same item is always in the same place,
 * so it can be found by position once learned rather than read every time.
 */
$rg_con_ui = array(
	6 => array('Wall Shield',  'Absorbs one breach, whole'),
	7 => array('Volley +100%', '+8 wall, +100% for 10s'),
	5 => array('Volley +75%',  '+8 wall, +75% for 7.5s'),
	4 => array('Volley +50%',  '+8 wall, +50% for 5s'),
	2 => array('Volley +25%',  '+8 wall, +25% for 2.5s'),
	3 => array('Rush Lines',   'Every line completes now'),
	1 => array('Free Level',   'One random location +1'),
);
/*
 * Two fixed rows, not one grid that wraps wherever the width happens to put it.
 * The four Volleys are ONE item at four strengths -- they are read as a set and
 * compared against each other, so they belong on a line together. The other
 * three do genuinely different things and get a line of three.
 */
$rg_con_rows = array(
	'volleys' => array(7, 5, 4, 2),
	'rest'    => array(6, 3, 1),
);
$rg_build_items = function ($odds) use ($rg_con_names, $rg_con_ui) {
	$out = array();
	foreach ($odds as $cid => $pct) {
		if (!isset($rg_con_names[$cid])) continue;
		$out[] = array(
			'id'   => intval($cid),
			'pct'  => intval($pct),
			'name' => $rg_con_names[$cid],
			'ui'   => $rg_con_ui[$cid][0],
			'blurb'=> $rg_con_ui[$cid][1],
			// Same icon construction the factory modal uses (ajax/get-factory.php:38).
			'icon' => 'icons/' . strtolower(str_replace(array('%', ' '), array('', '-'), $rg_con_names[$cid])) . '.png',
		);
	}
	return $out;
};
$rg_items = $rg_build_items($rg_fodds);
// The scratch baseline runs a level-1 Factory, so it must roll the level-1
// odds rather than inherit the player's table.
$rg_scratch['items'] = $rg_build_items(
	function_exists('getFactoryOdds') ? getFactoryOdds(1) : array(1 => 100)
);

/*
 * A realm is fast-forward: investment sets where on the ladder you begin.
 *
 * Location levels ALONE were not enough. Weapon level multiplies every armed
 * guardian's damage (see towerDamage), and army size decides how many you can
 * field at once -- so a player with a modest realm and a good cache was landing
 * far below their real strength and steamrolling the early waves. All three now
 * feed the score, with weapons weighted heavily because their effect is
 * multiplicative rather than additive.
 */
$rg_total = array_sum($rg_levels);
/*
 * Power counts the READY army, not the crypt.
 *
 * The dead are real guardians and they do prepopulate the in-game Crypt -- but
 * they sit behind a CARBON gate, so they are a resource you may spend into
 * rather than strength you start holding. Counting them would raise the starting
 * wave for a player whose army is largely in the ground, which is backwards.
 * Deliberate, and it also leaves the balance the last playtest approved intact.
 */
$rg_power = $rg_total + $rg_army + ($rg_wlevel * 4);
$rg_start_wave = $rg_has_realm ? max(1, intval(floor($rg_power / 5))) : 1;

/*
 * MUSIC. Discovered, not hardcoded.
 *
 * The tracks live in audio/tracks/ alongside Crypt Crawl's, and they ship by
 * FTP rather than through the repo -- so the exact filename (spaces? dashes?
 * capitalisation?) is not knowable from here, and guessing it wrong fails
 * silently, which is the worst way for this to break.
 *
 * This runs ON the server where the files are, so it just looks. Names are
 * normalised to letters only before matching, which makes it indifferent to
 * "Stand Your Ground.mp3", "stand-your-ground.mp3" or "Stand_Your_Ground.mp3",
 * and it keeps working if the files are renamed later.
 *
 * Anything not matched is simply absent -- no player renders, nothing breaks.
 */
$rg_tracks = array();
$rg_want = array(
	'standyourground'      => 'Stand Your Ground',
	'guardiansoftherealm'  => 'Guardians of the Realm',
);
$rg_named = array();
foreach ((array)glob(__DIR__ . '/audio/tracks/*.[mM][pP]3') as $rg_f) {
	$rg_base = pathinfo($rg_f, PATHINFO_FILENAME);
	$rg_key  = strtolower(preg_replace('/[^a-zA-Z]/', '', $rg_base));
	// rawurlencode the FILENAME only -- directory separators must survive,
	// spaces in the name must not.
	$rg_url  = 'audio/tracks/' . rawurlencode($rg_base . '.' . pathinfo($rg_f, PATHINFO_EXTENSION));

	/*
	 * SUBSTRING, not equality. An exact match required the file to reduce to
	 * precisely "standyourground", so anything decorated -- "RG - Stand Your
	 * Ground", "Stand Your Ground (final)", a version number -- silently
	 * matched nothing, which is exactly how this failed the first time.
	 */
	$rg_hit = '';
	foreach ($rg_want as $rg_k => $rg_label) {
		if (strpos($rg_key, $rg_k) !== false) { $rg_hit = $rg_label; break; }
	}
	if ($rg_hit !== '') {
		$rg_named[] = array('name' => $rg_hit, 'url' => $rg_url);
		continue;
	}

	/*
	 * Anything else in the folder that isn't Crypt Crawl's score. Belt and
	 * braces: if the two titles were saved under names nothing here predicts,
	 * they still turn up in the picker rather than leaving an empty control.
	 * Better a track labelled by its filename than no player at all.
	 */
	if (strpos($rg_key, 'cryptcrawl') === false) {
		$rg_tracks[] = array('name' => $rg_base, 'url' => $rg_url);
	}
}
// The two we were asked for lead the list; discoveries follow.
$rg_tracks = array_merge($rg_named, $rg_tracks);

// The horde wears real member avatars. Public everywhere already (podiums,
// profiles), so this exposes nothing new -- and being overrun by names from
// your own Discord is a story. The player is excluded from their own horde.
$rg_horde = array();
$hr = $conn->query("SELECT username, discord_id, avatar FROM users
                    WHERE discord_id != '' AND avatar != '' AND id != $rg_me
                    ORDER BY RAND() LIMIT 80");
if ($hr) while ($h = $hr->fetch_assoc()) {
	$rg_horde[] = array(
		'name' => $h['username'],
		// Carried so the defeat post can name the staker whose avatar actually
		// broke the wall. Digits only by construction here, and re-filtered
		// server-side before it is ever put in a Discord message -- a mention
		// string built from client input is an injection vector otherwise.
		'did'  => preg_replace('/[^0-9]/', '', (string)$h['discord_id']),
		'img'  => 'https://cdn.discordapp.com/avatars/' . $h['discord_id'] . '/' . $h['avatar'] . '.png',
	);
}
?>

<?php
/*
 * The theme spans the ROW -- the same width as the main menu header -- rather
 * than the game column inside it. On the game panel it was only as wide as the
 * board (820px) and read as a card floating on the page; across the row it
 * reads as the backdrop the whole page is standing in.
 *
 * Art on a ::before so it can never paint over content, gradient baked into the
 * same value so the wash travels with the image, and everything real layered
 * above it. Same construction Crypt Crawl uses (.cc-theme-bg,
 * cryptcrawl.php:188), just anchored to the wider container.
 */
$rg_theme_img = $rg_theme > 0
	? "linear-gradient(180deg, rgba(7,17,26,.72), rgba(7,17,26,.90)), url('/staking/images/themes/" . $rg_theme . ".jpg')"
	: '';
?>
<div class="row<?php echo $rg_theme_img !== '' ? ' rg-themed' : ''; ?>" id="row1"<?php
	if ($rg_theme_img !== '') echo ' style="--rg-theme-img:' . htmlspecialchars($rg_theme_img) . '"';
?>>
  <!--
    max-width:none is the load-bearing part. Removing this page's own 820px cap
    was not enough: dist/flexbox.css:2388 caps EVERY .col1of3 at 900px
    platform-wide, so the board stayed boxed. That rule is shared by every page
    on the site and must not be edited, so it is overridden inline here and
    nowhere else.

    The navbar carries no max-width of its own, so full width IS the header's
    width -- which is what makes the board and the theme behind it line up.
  -->
  <div class="col1of3" style="margin:0 auto;flex:1 1 100%;max-width:none;">

	<h2 class="rg-intro">Realm Guardians</h2>
	<div class="rg-blurb rg-intro">
		<?php if ($rg_has_realm): ?>
			<?php
			/*
			 * The full roster, not just the ready half. A realm with 100 enlisted
			 * NFTs and seven in the crypt used to read "93 guardians", which looks
			 * like the game losing track of them. The dead are shown, and they
			 * start in the Crypt rather than being omitted.
			 */
			$rg_roster = $rg_army + $rg_crypt;
			// Where they actually are, since they no longer all start in one pile.
			$rg_where = array();
			if ($rg_garrison > 0) $rg_where[] = $rg_garrison . ' on the Tower';
			if ($rg_raiders  > 0) $rg_where[] = $rg_raiders . ' out raiding';
			$rg_ready = max(0, $rg_army - $rg_garrison - $rg_raiders);
			if ($rg_ready > 0)    $rg_where[] = $rg_ready . ' in reserve';
			if ($rg_crypt > 0)    $rg_where[] = $rg_crypt . ' in the Crypt';
			?>
			<span id="rg-blurb-realm">Defending <strong><?php echo htmlspecialchars($rg_realm_name); ?></strong> &mdash;
			<?php echo $rg_roster; ?> guardians<?php
				if (!empty($rg_where)) echo ' (' . implode(', ', $rg_where) . ')';
			?>,
			<?php echo $rg_cache; ?> weapons and <?php echo $rg_acache; ?> armour in the cache,
			<?php echo $rg_total; ?> total location levels.
			Power <?php echo $rg_power; ?> starts you at wave <?php echo $rg_start_wave; ?>.</span>
			<!-- Swapped in by the "start from scratch" toggle. The blurb has to
			     follow the baseline or it describes a realm this run is not using. -->
			<span id="rg-blurb-scratch" hidden>Holding the wall with <strong>conscripts</strong> &mdash;
			no enlisted guardians, every location at level 1, 2 weapons and 1 armour in the
			cache. Starting at wave 1, the same place a player with no realm starts.</span>
		<?php else: ?>
			You have no realm, so you hold the wall with conscripts. Build a realm and you
			start further up the same ladder.
		<?php endif; ?>
		<br><em>Nothing here is saved and nothing is spent. Your realm is untouched no matter how this goes.</em>
	</div>

	<div id="rg-game">

		<div class="rg-hud">
			<span>Wave <strong id="rg-wave">0</strong></span>
			<span>Wall <strong id="rg-hp">100</strong></span>
			<span>CARBON <strong id="rg-carbon">0</strong></span>
			<span id="rg-status">Press Begin</span>
			<!-- A run can last an hour and nothing is saved. Being interrupted must
			     not cost that, so the siege can be put down and picked up. -->
			<button type="button" id="rg-pause" title="Pause the siege" aria-pressed="false" hidden>&#9208;&#65039;</button>
			<!-- Withdraw from this siege and go back to the pre-run board, where the
			     baseline can be changed before starting again. Confirmed, because it
			     throws away up to an hour. -->
			<button type="button" id="rg-retreat" title="Sound the retreat and set up a new siege" hidden>&#8635;</button>
			<button type="button" id="rg-sound" title="Mute effects" aria-pressed="true">&#128266;</button>
			<?php if (!empty($rg_tracks)): ?>
				<!-- Music gets its own switch and its own volume: the point of
				     having it here is judging it AGAINST the gunfire, which is
				     impossible if one control kills both. -->
				<button type="button" id="rg-music" title="Mute music" aria-pressed="true">&#127925;</button>
				<select id="rg-track" title="Track">
					<?php foreach ($rg_tracks as $i => $t): ?>
						<option value="<?php echo $i; ?>"><?php echo htmlspecialchars($t['name']); ?></option>
					<?php endforeach; ?>
				</select>
				<input type="range" id="rg-vol" min="0" max="100" value="70" title="Music volume">
			<?php endif; ?>
		</div>

		<!--
			The field reads as a place now, not an abstract track. The Tower
			stands ON the wall at the left, which is where its fire comes from,
			and the Portal sits out in the field where guardians actually step
			through it -- sorties spawn at exactly that mark rather than at an
			arbitrary offset, so "ride out through the Portal" is something you
			watch rather than something the log claims.
		-->
		<div id="rg-field">
			<div id="rg-wall"></div>
			<!-- Shields stack and never expire, and the wall glows the same for one
			     as for five -- so the count is the only way to know what is banked. -->
			<span id="rg-wall-shields" hidden></span>
			<img id="rg-tower-icon" src="icons/locations/tower.png" alt="Tower"
			     title="The Tower. Its garrison fires from here." onerror="this.style.display='none'">
			<img id="rg-portal-icon" src="icons/locations/portal.png" alt="Portal"
			     title="The Portal. Striking guardians step through here." onerror="this.style.display='none'">
			<div id="rg-sortie"></div>
			<div id="rg-enemies"></div>
			<!-- Tower fire. Purely decorative, desktop only, and empty on mobile
			     and for anyone who asked for reduced motion. -->
			<div id="rg-tracers"></div>
		</div>

		<div id="rg-locations">
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/tower.png" alt="" onerror="this.style.display='none'">Tower <span class="rg-lvl" id="rg-lvl-tower">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-garrison">0</strong>/<span id="rg-garrison-cap">4</span> garrison &middot; <span id="rg-armed">0</span> armed &middot; <span id="rg-armored">0</span> armoured</div>
				<button type="button" class="rg-act" data-act="deploy" title="Send every spare guardian from the Barracks to the Tower now, arming and armouring them from the cache">Deploy</button>
				<button type="button" class="rg-act rg-up" data-act="up-tower">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/barracks.png" alt="" onerror="this.style.display='none'">Barracks <span class="rg-lvl" id="rg-lvl-barracks">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-reserve">0</strong> in reserve</div>
				<div class="rg-bar" title="Time until the Barracks trains the next guardian"><i id="rg-bar-barracks"></i></div>
					<div class="rg-cap" id="rg-cap-barracks">training next guardian</div>
				<button type="button" class="rg-act rg-up" data-act="up-barracks">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/armory.png" alt="" onerror="this.style.display='none'">Armory <span class="rg-lvl" id="rg-lvl-armory">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-weapons">0</strong> weapons &middot; <span id="rg-armor">0</span> armour</div>
				<div class="rg-bar" title="Time until the Armory forges the next weapon"><i id="rg-bar-armory"></i></div>
					<div class="rg-cap" id="rg-cap-armory">forging next weapon</div>
					<div class="rg-bar" title="Time until the Armory forges the next piece of armour"><i id="rg-bar-forge"></i></div>
					<div class="rg-cap" id="rg-cap-forge">forging next armour</div>
				<button type="button" class="rg-act rg-up" data-act="up-armory">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/crypt.png" alt="" onerror="this.style.display='none'">Crypt <span class="rg-lvl" id="rg-lvl-crypt">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-dead">0</strong> dead</div>
				<div class="rg-bar" title="Time until the Crypt can perform the rite again"><i id="rg-bar-crypt"></i></div>
					<div class="rg-cap" id="rg-cap-crypt">preparing the rite</div>
				<button type="button" class="rg-act" data-act="raise" title="Empty the Crypt — every guardian in it comes back at once. Free; it only needs time, and a higher Crypt needs less of it. Waiting longer means more of them return in one rite.">Raise</button>
				<button type="button" class="rg-act rg-up" data-act="up-crypt">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/portal.png" alt="" onerror="this.style.display='none'">Portal <span class="rg-lvl" id="rg-lvl-portal">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-sortied">0</strong> in the field</div>
				<div class="rg-bar" title="Portal cooldown -- Strike is ready when full"><i id="rg-bar-portal"></i></div>
					<div class="rg-cap" id="rg-cap-portal">strike ready when full</div>
				<button type="button" class="rg-act" data-act="sortie" title="Send guardians out through the Portal to meet the horde in the open, before it reaches your wall. They fight with no Tower behind them.">Strike</button>
				<button type="button" class="rg-act rg-up" data-act="up-portal">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/factory.png" alt="" onerror="this.style.display='none'">Factory <span class="rg-lvl" id="rg-lvl-factory">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-items">0</strong> items</div>
				<div class="rg-bar" title="Time until the Factory builds the next item"><i id="rg-bar-factory"></i></div>
					<div class="rg-cap" id="rg-cap-factory">building next item</div>
				<button type="button" class="rg-act rg-up" data-act="up-factory">Upgrade</button>
			</div>
		</div>

		<!--
			THE FACTORY SHELF.

			Items used to be a QUEUE spent by one "Fortify" button: you got
			whatever the Factory happened to build next, which made the one
			action in the game you could not choose. Playtest: "I find myself
			with the hordes all up on my wall and I'm mashing items hoping for a
			miracle." Hoping is the giveaway -- that is a slot machine, not a
			decision, and everything else in this game is a decision.

			TWO ROWS, and deliberately not one auto-flowing grid. The four
			Volleys are the same item at four strengths, so they read as a set
			and belong on a line of their own; the other three do genuinely
			different things and spread across a line of three. An auto-fit grid
			put four on one row and three on the next by accident of width,
			which looked the same at 720px and fell apart everywhere else.
		-->
		<div id="rg-shelf">
			<div class="rg-shelf-head">Factory items &mdash; <span id="rg-shelf-count">0</span> held</div>
			<?php foreach ($rg_con_rows as $rg_rowname => $rg_rowids): ?>
				<div class="rg-shelf-grid rg-shelf-<?php echo $rg_rowname; ?>">
					<?php foreach ($rg_rowids as $rg_cid): $rg_cu = $rg_con_ui[$rg_cid]; ?>
						<button type="button" class="rg-item" data-act="item-<?php echo intval($rg_cid); ?>" disabled
						        title="<?php echo htmlspecialchars($rg_con_names[$rg_cid] . ' — ' . $rg_cu[1]); ?>">
							<img src="icons/<?php echo strtolower(str_replace(array('%', ' '), array('', '-'), $rg_con_names[$rg_cid])); ?>.png"
							     alt="" onerror="this.style.display='none'">
							<span class="rg-item-name"><?php echo htmlspecialchars($rg_cu[0]); ?>
								<b data-count="<?php echo intval($rg_cid); ?>">(0)</b></span>
							<span class="rg-item-blurb"><?php echo htmlspecialchars($rg_cu[1]); ?></span>
							<?php if ($rg_rowname === 'volleys'): ?>
							<!-- Volleys are the only items with a DURATION, and any of them
							     overwrites whatever is already running -- so all four share
							     one countdown showing when the current boost expires. -->
							<i class="rg-item-boost"></i>
							<?php endif; ?>
						</button>
					<?php endforeach; ?>
				</div>
			<?php endforeach; ?>
		</div>

		<!--
			THE MINE, and the button that starts a run, in ONE panel.

			Begin had a cell of its own for a while. It is pressed twice a run --
			once at the start, once after a loss -- so a whole panel of its own
			was space bought at the wrong price, especially on a phone where
			every row costs a scroll. The Mine is the natural host: it is the
			shortest card, it is the one nothing is decided on mid-siege, and it
			is already the bottom of the board.
		-->
		<div id="rg-mine-row" class="rg-loc">
			<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/mine.png" alt="" onerror="this.style.display='none'">Mine <span class="rg-lvl" id="rg-lvl-mine">1</span></div>
			<div class="rg-mine-body">
				<div class="rg-mine-stats">
					<div class="rg-loc-stat">CARBON &middot; <span id="rg-mine-rate">+0/s</span></div>
					<div class="rg-bar" title="CARBON banked toward the Mine's next level. Full means you can afford it — and it falls back when you spend on another location."><i id="rg-bar-mine"></i></div>
						<div class="rg-cap" id="rg-cap-mine">CARBON to the next Mine level</div>
					<button type="button" class="rg-act rg-up" data-act="up-mine">Upgrade</button>
				</div>
				<div class="rg-mine-start">
					<!-- Only rendered when there is actually a siege to go back to, so
					     the common case is still one unambiguous button. -->
					<?php if ($rg_saved): ?>
					<button type="button" id="rg-resume">Resume &mdash; wave <?php echo intval($rg_saved['wave']); ?></button>
					<?php endif; ?>
					<button type="button" id="rg-begin"><?php echo $rg_saved ? 'Start over' : 'Begin the Siege'; ?></button>
					<?php if ($rg_has_realm): ?>
					<!-- Only shown to someone who HAS a realm to switch off. Without one
					     the game is already the scratch baseline, and a toggle that does
					     nothing is worse than no toggle. -->
					<label id="rg-scratch-wrap" title="Ignore your realm and hold the wall with conscripts: every location at level 1, no enlisted guardians, a level-1 cache. Your realm is untouched either way.">
						<input type="checkbox" id="rg-scratch"> Start from scratch
					</label>
					<?php endif; ?>
				</div>
			</div>
		</div>

		<!--
			WHAT THE PLAYER IS FOR.

			Playtest: "I'm trying to understand what my role is and what the
			strategy is. Otherwise I'm just button mashing the second things
			activate." That is a fair description of a game that never says what
			it wants. The locations run themselves; the player is an allocator,
			and the tension is one finite pool of CARBON against four uses.
			Saying so out loud costs nothing and is the difference between
			mashing and playing.
		-->
		<details id="rg-help">
			<summary>What am I actually deciding?</summary>
			<div>
				<p><strong>The locations run themselves.</strong> The Barracks trains guardians,
				the Armory forges weapons, the Factory builds items, the Mine pays CARBON, and
				the Tower reinforces itself from the Barracks and fires on its own. Every
				progress bar is one of those timers filling. You never have to click to keep
				the wall manned.</p>
				<p><strong>Your job is choosing where the effort goes.</strong> Three of the
				four actions are free &mdash; what they cost is time, bodies, or a thing you
				only have one of:</p>
				<ul>
					<li><strong>Upgrade</strong> (costs CARBON) &mdash; permanently improve a rate
					or a cap for the rest of this run. This is the only thing CARBON buys, and it
					compounds, so an early level is worth several late ones.</li>
					<li><strong>Raise</strong> (free, costs time) &mdash; empties the Crypt:
					<em>every</em> guardian in it comes back at once. The Crypt holds your dead
					indefinitely, so none are ever lost, and a higher Crypt shortens the wait
					between rites. Because one rite returns everyone, raising the instant it is
					ready spends the whole cycle on whoever happens to be dead &mdash; holding on
					returns everyone who falls in the meantime for the same wait. The judgement
					is whether your wall lives long enough to be worth banking.</li>
					<li><strong>Strike</strong> (free, costs guardians and gear) &mdash; kill
					attackers in the open before they reach the wall. Trades bodies for wall
					damage you never take.</li>
					<li><strong>Factory items</strong> (free, but each is spent for good)
					&mdash; the shelf under the board. Which one you pick is the decision;
					see below.</li>
				</ul>
				<p><strong>The strategy:</strong> spend CARBON early, while a level still has a
				whole run to pay you back, and lean on the free actions once waves outpace
				production. Holding CARBON does nothing &mdash; unspent CARBON is a wave you
				didn't survive.</p>

				<p><strong>The seven items, and when they are worth spending.</strong> The
				Factory builds them on its own and holds a limited number, so a shelf full of
				items you are saving is a Factory that has stopped producing.</p>
				<ul>
					<li><strong>Wall Shield</strong> &mdash; the next attacker to reach your wall
					is thrown back and does <em>no</em> damage at all. Stacks, and never expires:
					three of them is three breaches cancelled. <em>The strongest thing you can
					hold when the wall is about to be hit</em>, and worth most against the big
					attackers, who hit for 12 where the rest hit for 5.</li>
					<li><strong>Rush Lines</strong> &mdash; every production line completes at
					once: a recruit, a weapon, a piece of armour, another item, CARBON, and the
					Portal refilled. <em>The Portal is the point</em> &mdash; it means you can
					Strike immediately. Best used the moment you want guardians out in the field
					and the cooldown says no.</li>
					<li><strong>Volley +100% / +75% / +50% / +25%</strong> &mdash; each patches
					8 onto the wall and makes the Tower hit that much harder. The duration
					scales with the magnitude &mdash; the number on the tin is the number of
					tenths of a second, so +100% runs for 10s and +25% for 2.5s. Two things
					worth knowing: the patch is capped at a full wall, so spending one at 100
					wastes it &mdash; and the Tower fires one shot at one attacker every 0.6s,
					so once your guardians already kill an attacker per shot, hitting harder
					kills no faster. <em>Late in a run these are mostly the +8.</em></li>
					<li><strong>Free Level</strong> &mdash; one random location gains a level for
					nothing. It is the only item that compounds, and the only one with no effect
					on the fight in front of you. <em>Spend these early</em>, while a level still
					has a whole run to pay you back &mdash; and spend them to clear shelf space
					rather than sitting on them.</li>
				</ul>
			</div>
		</details>

		<div id="rg-log"></div>
	</div>

	<!--
		THE LAST STAND, and it lives OUTSIDE #rg-game on purpose.

		It is a full-screen overlay, and `#rg-game > *` gives every child
		max-width:720px with auto margins to centre the board. That rule applied
		to the overlay itself, and an element's own max-width clamps it even when
		it is position:fixed -- so the "full screen" backdrop was a 720px column
		floating in the middle of the page. Measured before the fix: x=641,
		width=720 in a 2017px viewport.
	-->
	<div id="rg-nuke" hidden role="dialog" aria-modal="true" aria-labelledby="rg-nuke-title">
		<div class="rg-nuke-card">
			<div class="rg-nuke-emoji" aria-hidden="true">&#9762;&#65039;</div>
			<h3 id="rg-nuke-title">Nuke Detonated</h3>
			<p>The wall was breached and your realm has detonated a nuclear bomb,
			sweeping <strong><span id="rg-nuke-count">0</span></strong> from the field.</p>
			<p class="rg-nuke-sub">Your realm is safe for now, but whatever is beyond the
			edge of the blast is still coming.</p>
			<button type="button" id="rg-nuke-ok">Hold the line</button>
		</div>
	</div>

	<!--
		THE SEND-OFF.

		Every realm falls -- that is the design, not a failure state -- so the
		end of a run is the one moment the game gets to say what was achieved
		rather than what went wrong. It led with the Begin button reappearing,
		which reads as "again?" when the honest reading is "you held 37 waves".

		WAVES HELD is the headline, not the wave reached, for the same reason
		the leaderboard ranks on it: the wave you started on was handed to you
		by your realm, and what you did is the distance past it.
	-->
	<div id="rg-defeat" hidden role="dialog" aria-modal="true" aria-labelledby="rg-defeat-title">
		<div class="rg-nuke-card">
			<div class="rg-nuke-emoji" aria-hidden="true">&#127993;</div>
			<h3 id="rg-defeat-title">The realm has fallen</h3>
			<p class="rg-defeat-hero"><strong id="rg-defeat-held">0</strong>
				<span>waves held</span></p>
			<p>You began at wave <strong id="rg-defeat-start">1</strong> and the wall
			came down at wave <strong id="rg-defeat-wave">1</strong>.</p>
			<p class="rg-nuke-sub"><span id="rg-defeat-lost">0</span> guardians lost
			over <span id="rg-defeat-time">0m</span>.
			<span id="rg-defeat-scored"></span></p>
			<!-- Straight to the board this run just went on, not the hub: the
			     question in the player's head at this exact moment is "where did
			     that put me", and one more click to find out is one too many.
			     ?filterby= is read from GET (skulliance.php:848), so a plain
			     link works and needs no form. -->
			<a class="rg-defeat-board" href="leaderboards.php?filterby=monthly-guardians">See the monthly board</a>
			<button type="button" id="rg-defeat-ok">Again</button>
		</div>
	</div>

	<!-- Retreat confirmation. Outside #rg-game for the same reason the nuke
	     modal is: the board's centring rule would clamp a full-screen overlay
	     to 720px. -->
	<div id="rg-confirm" hidden role="dialog" aria-modal="true" aria-labelledby="rg-confirm-title">
		<div class="rg-nuke-card">
			<div class="rg-nuke-emoji" aria-hidden="true">&#9888;&#65039;</div>
			<h3 id="rg-confirm-title">Sound the retreat?</h3>
			<p>You withdraw, the siege ends, and nothing is scored. You will be able to
			set up a new one &mdash; from your realm, or from scratch.</p>
			<p class="rg-nuke-sub">The siege is held until you choose.</p>
			<button type="button" id="rg-confirm-yes">Retreat</button>
			<button type="button" id="rg-confirm-no">Keep fighting</button>
		</div>
	</div>

  </div>
</div>

<style>
/* The themed band, on the ROW so it spans the same width as the main menu
   header rather than only the board. Art on a ::before so it can never paint
   over the content, inset slightly so it fills the rounded corners without
   exposing an edge, overflow clipped. Everything real sits above it. */
/* Full screen height whatever the board's height is -- a short run left the art
   as a shallow band with dead page under it. dvh first for mobile, where the
   browser chrome makes vh refer to the tallest state and overshoot. */
#row1.rg-themed {
  position:relative; overflow:hidden; border-radius:14px; padding:18px 0;
  min-height:100vh;
  min-height:100dvh;
  align-content:flex-start;   /* content stays at the top; the art fills down */
}
#row1.rg-themed::before {
  content:''; position:absolute; inset:-3%;
  background-image:var(--rg-theme-img);
  background-size:cover; background-position:center;
  z-index:0;
}
#row1.rg-themed > * { position:relative; z-index:1; }
/* The panels need to stay legible on top of artwork. */
#row1.rg-themed .rg-loc { background:rgba(13,30,48,.93); }
#row1.rg-themed #rg-field { background:rgba(10,25,41,.93); }
/* The intro is small grey text and the themes are busy artwork, so it needs its
   own ground to sit on rather than relying on the wash alone. Only when themed;
   over the flat background it would be a box around nothing. */
#row1.rg-themed h2.rg-intro,
#row1.rg-themed .rg-blurb {
  background:rgba(7,17,29,.78);
  border-radius:10px;
  padding:10px 14px;
  backdrop-filter:blur(2px);
}
/* Block, not inline-block: shrink-wrapping made the title a small left-hugging
   box while everything below it sat in the 720px column. */
#row1.rg-themed h2.rg-intro { margin-bottom:10px; }
#row1.rg-themed .rg-blurb { margin-top:0; }
.rg-blurb { font-size:.82rem; color:rgba(255,255,255,.5); margin:-6px 0 16px; line-height:1.5; }
.rg-blurb strong { color:#00c8a0; }
/* NOWRAP, and a fixed height. The status message changes length constantly
   ("Wave 41 incoming" / "Wave held -- regroup"), and with wrapping enabled that
   pushed the volume slider onto a second row, which shifted the whole board down
   mid-fight. The message now truncates instead of reflowing, and the bar's
   height is reserved so nothing below it can move. */
.rg-hud {
  display:flex; gap:14px; font-size:.78rem; color:rgba(255,255,255,.55);
  margin-bottom:10px; flex-wrap:nowrap; align-items:center;
  min-height:28px; overflow:hidden;
}
.rg-hud strong { color:#00c8a0; font-size:1rem; }
/* Fixed-size items keep their place; only the message flexes and clips. */
.rg-hud > span:not(#rg-status), .rg-hud > button, .rg-hud > select, .rg-hud > input { flex:0 0 auto; }
#rg-status {
  flex:1 1 auto; min-width:0; text-align:right; color:#ffcc44;
  white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
#rg-sound, #rg-music, #rg-pause, #rg-retreat { background:none; border:0; font-size:1rem; cursor:pointer; padding:0 2px; line-height:1; }
#rg-pause[hidden], #rg-retreat[hidden] { display:none; }
/* A <select> sizes itself to its WIDEST option, and "Guardians of the Realm" is
   wide. Capped, or it dictates the width of the whole bar. */
#rg-track { background:#0d1e30; color:rgba(255,255,255,.75); border:1px solid rgba(255,255,255,.15);
            border-radius:5px; font-size:.72rem; padding:3px 5px; max-width:130px; }
#rg-vol { width:70px; accent-color:#00c8a0; vertical-align:middle; }
/* On a phone the HUD is already tight; the volume slider is the first thing
   that can go, since the mute button covers the urgent case. */
/* The HUD cannot wrap any more (that was shifting the board), so on a phone it
   must FIT instead. The track picker is the widest item -- a <select> sizes to
   its longest option -- and the volume slider is the least urgent, so both go;
   the mute buttons cover everything that matters. Without this the bar forced
   the page wider than the screen. */
@media (max-width:560px) {
  #rg-vol, #rg-track { display:none; }
  .rg-hud { gap:9px; font-size:.72rem; }
  .rg-hud strong { font-size:.88rem; }
}

/* ---- FIELD SCALE ----------------------------------------------------------
   The field is the one part of the board worth looking AT -- it carries the
   NFT art, the avatars and the gear badges, and at 26px none of that reads.
   Doubled for a browser, and left exactly as it was on a phone, where the
   screen is the constraint rather than the pixels.
   Kept as variables rather than a dozen duplicated rules so the mobile block
   overrides ONE set of numbers instead of re-stating every selector. */
#rg-game { --rg-u:52px; --rg-tough:68px; --rg-badge:24px; --rg-off:-12px;
           --rg-barh:4px; --rg-tower:52px; --rg-portal:60px;
           --rg-tracer-w:24px; --rg-tracer-h:4px; --rg-field-h:160px; }
#rg-field { position:relative; height:var(--rg-field-h); background:#0a1929; border:1px solid rgba(255,255,255,.08); border-radius:8px; overflow:hidden; margin-bottom:12px; }
#rg-wall.rg-fortified { background:linear-gradient(180deg,#ffcc44,#c79a1e) !important; box-shadow:0 0 14px rgba(255,204,68,.8); }
#rg-wall { position:absolute; left:0; top:0; bottom:0; width:10px; background:linear-gradient(180deg,#00c8a0,#007a61); }
#rg-enemies, #rg-sortie { position:absolute; inset:0; }
/* The Tower sits on the wall; the Portal stands out in the field at PORTAL_X.
   Both are markers, not obstacles -- pointer-events off so they never eat a tap
   meant for a foe, and behind the units so guardians walk in front of them. */
#rg-tower-icon, #rg-portal-icon { position:absolute; pointer-events:none; opacity:.9; z-index:0; }
/* Vertically centred, level with the Portal -- the two are the same kind of
   thing (a place on the field), so they should sit on the same line rather than
   one hugging the floor. */
#rg-tower-icon  { left:14px; top:50%; transform:translateY(-50%); width:var(--rg-tower); height:var(--rg-tower); object-fit:contain; }
#rg-portal-icon { left:25%; top:50%; transform:translate(-50%,-50%); width:var(--rg-portal); height:var(--rg-portal);
                  object-fit:contain; opacity:.55; filter:drop-shadow(0 0 6px rgba(0,200,160,.7)); }
#rg-sortie, #rg-enemies { z-index:1; }
/* ---- Tower fire ----------------------------------------------------------
   A tracer leaves the Tower whenever an attacker crosses the Portal, so the
   garrison visibly does something instead of the wall just losing height.
   Decorative: the layer takes no pointer events, holds nothing the sim reads,
   and is never populated at all on phones (see TRACERS in the script). Animated
   on transform/opacity only -- both composited, so no layout runs per shot. */
#rg-tracers { position:absolute; inset:0; pointer-events:none; z-index:2; }
.rg-tracer { position:absolute; top:50%; margin-top:calc(var(--rg-tracer-h) / -2); width:var(--rg-tracer-w); height:var(--rg-tracer-h); border-radius:1px;
             background:linear-gradient(90deg, rgba(255,214,102,0), #ffd666);
             animation:rg-tracer .26s linear forwards; will-change:transform, opacity; }
@keyframes rg-tracer {
  from { transform:translateX(0);              opacity:1; }
  to   { transform:translateX(var(--rg-dx,0)); opacity:0; }
}
/* The muzzle end, so the shot reads as coming FROM the Tower. */
#rg-tower-icon.rg-firing { filter:drop-shadow(0 0 6px rgba(255,214,102,.95)); }
.rg-foe { position:absolute; top:50%; transform:translateY(-50%); box-sizing:border-box; width:var(--rg-u); height:var(--rg-u); border-radius:50%; background:#c0392b; border:2px solid #c0392b; transition:left .1s linear; }
.rg-foe img { width:100%; height:100%; border-radius:50%; display:block; object-fit:cover; }
.rg-foe.rg-tough { width:var(--rg-tough); height:var(--rg-tough); border-color:#c39bd3; box-shadow:0 0 8px rgba(195,155,211,.6); }
.rg-foe i { position:absolute; left:0; bottom:var(--rg-off); height:var(--rg-barh); background:#ff6b6b; }
/* Sortied guardians sit above the line so they read as yours, not theirs. */
/* Your guardians: their own NFT art, ringed in the platform green so they read
   as yours at a glance against the red horde. */
/* Identical footprint to a foe -- border-box so the border sits INSIDE, or a
   2px border silently makes one 4px bigger than the other.
   THE ART IS THE POINT. These are 26px circles carrying somebody's NFT, and the
   status ring is only worth anything if you can still tell whose face it is
   round. Everything decorative is therefore drawn at the EDGE or OUTSIDE it:
   a 1px border rather than 2px, the kit ring as an outset shadow instead of a
   second inner ring, and the badges pushed off the circle so they clip a
   corner rather than sitting on the face. Four heavy rings on 26px left about
   twenty usable pixels of artwork. */
.rg-unit { position:absolute; top:16%; box-sizing:border-box; width:var(--rg-u); height:var(--rg-u); border-radius:50%; background:#0a1929; border:1px solid rgba(0,200,160,.9); box-shadow:0 0 5px rgba(0,200,160,.45); transition:left .1s linear; }
.rg-unit img { width:100%; height:100%; border-radius:50%; display:block; object-fit:cover; }
.rg-unit.rg-armed { border-color:rgba(255,204,68,.95); box-shadow:0 0 6px rgba(255,204,68,.55); }
/* The weapon they carry, badged clear of the face. */
.rg-unit b { position:absolute; right:var(--rg-off); bottom:var(--rg-off); width:var(--rg-badge); height:var(--rg-badge); background:#07111d; border-radius:50%; display:block; padding:1px; }
/* Armour on the other shoulder, so a guardian can visibly carry both. */
.rg-unit u { position:absolute; left:var(--rg-off); bottom:var(--rg-off); width:var(--rg-badge); height:var(--rg-badge); background:#07111d; border-radius:50%; display:block; padding:1px; }
.rg-unit b img, .rg-unit u img { width:100%; height:100%; object-fit:contain; border-radius:0; }
/* Life bar, mirroring the horde's -- theirs red, yours the platform green, so
   the two lines of bars read as two sides rather than one crowd. */
.rg-unit i { position:absolute; left:0; bottom:var(--rg-off); height:var(--rg-barh); background:#00c8a0; }
/* Armoured guardians get a steel halo. Drawn OUTSIDE the circle, so it reads
   as "fully kitted" next to the gold border without stealing another pixel of
   the artwork. */
.rg-unit.rg-prot { box-shadow:0 0 0 1px rgba(190,200,215,.85), 0 0 7px rgba(190,200,215,.45); }
.rg-unit.rg-armed.rg-prot { box-shadow:0 0 0 1px rgba(190,200,215,.85), 0 0 6px rgba(255,204,68,.55); }

/*
 * THREE ACROSS, AND TIGHT. The field wants the full width -- more room to watch
 * the horde come -- but the controls do not: spreading seven panels across a
 * wide monitor turns every decision into a mouse journey. Capped and centred so
 * the six locations stay a compact 3x2 block under a wide field, with the Mine
 * as a full-width row beneath them.
 */
/*
 * ONLY THE FIELD GOES FULL WIDTH. Everything else -- HUD included -- stays a
 * tight centred column. The strip is the one thing that gains from the room,
 * because it is the approach the horde crosses; spreading the controls, HUD,
 * log and help across a wide monitor just turns every decision into a mouse
 * journey.
 */
#rg-game > *, .rg-intro { max-width:720px; margin-left:auto; margin-right:auto; }
#rg-field { max-width:none !important; }

#rg-locations { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
.rg-loc { background:#0d1e30; border:1px solid rgba(255,255,255,.1); border-radius:8px; padding:9px 10px; }
.rg-loc.rg-wide { grid-column:1 / -1; }
/* The Mine panel carries Begin. Stats on the left, the start control on the
   right, so the panel is one row deep instead of two stacked blocks -- the
   whole reason Begin lives here rather than in a cell of its own. */
#rg-mine-row .rg-mine-body { display:flex; align-items:center; gap:14px; }
#rg-mine-row .rg-mine-stats { flex:1 1 auto; min-width:0; }
#rg-mine-row .rg-mine-stats .rg-act { margin-bottom:0; }
#rg-mine-row .rg-mine-start { flex:0 0 auto; display:flex; flex-direction:column;
                              align-items:center; gap:3px; }
#rg-mine-row .rg-mine-start #rg-begin { margin:0; }
#rg-mine-row .rg-mine-start #rg-scratch-wrap { margin:0; }
.rg-loc-name { font-size:.72rem; text-transform:uppercase; letter-spacing:.06em; color:rgba(255,255,255,.5); display:flex; align-items:center; gap:6px; }
/* The realm's own location art (icons/locations/<name>.png -- the same files
   realms.php:106 uses, all verified 200). Hidden rather than broken if one is
   ever missing, since assets ship by FTP and a 404 must not leave a torn icon. */
.rg-icon { width:20px; height:20px; object-fit:contain; opacity:.85; flex:0 0 auto; }
.rg-lvl { color:#ffcc44; }
.rg-loc-stat { font-size:.8rem; margin:3px 0 7px; }
.rg-loc-stat strong { color:#fff; font-size:1rem; }
.rg-bar { height:3px; background:rgba(255,255,255,.08); border-radius:2px; overflow:hidden; margin-bottom:7px; }
.rg-bar i { display:block; height:100%; width:0; background:#00c8a0; }
/* A HELD line: full, and going nowhere until something makes room. Amber, not
   green, so "waiting on me" is distinguishable at a glance from "working" --
   a full green bar and a stalled green bar looked identical. */
.rg-bar i.rg-bar-full { background:#ffcc44; }
.rg-act { background:#00c8a0; color:#04121d; font-weight:bold; border:0; border-radius:5px; padding:7px 9px; font-size:.74rem; cursor:pointer; margin:0 3px 3px 0; }
.rg-act.rg-up { background:rgba(255,255,255,.12); color:rgba(255,255,255,.75); }
.rg-act:disabled { opacity:.32; cursor:default; }
/* The Factory button renames itself to whatever item is next, and those names
   are different lengths ("Double Rewards" against "100% Success"). Pinned to
   the widest so the card does not resize under the cursor every time the
   Factory finishes something -- the same reason the HUD readouts are pinned. */
.rg-act[data-act="fortify"] { min-width:118px; text-align:center; }
/* Bar captions. A progress bar with no label is a mystery, and there are five
   of them on this board. */
.rg-cap { font-size:.6rem; color:rgba(255,255,255,.35); margin:-4px 0 6px; letter-spacing:.02em; }
#rg-help { margin-top:14px; font-size:.8rem; color:rgba(255,255,255,.6); }
#rg-help summary { cursor:pointer; color:#00c8a0; font-weight:bold; font-size:.82rem; }
#rg-help div { padding:8px 0 0; line-height:1.6; }
#rg-help ul { margin:6px 0; padding-left:18px; }
#rg-help li { margin-bottom:5px; }
#rg-help strong { color:rgba(255,255,255,.85); }
#rg-log { margin-top:12px; font-size:.78rem; color:rgba(255,255,255,.45); min-height:3.2em; line-height:1.5; }
#rg-log b { color:#ff6b6b; }
/* On the Mine row now rather than in a banner under the log, but CENTRED on
   its own line instead of sitting inline beside Upgrade -- the button that
   starts the game is not one of the Mine's controls and should not read as
   one. Its own block, so it keeps the centre of the wide row whatever the
   Upgrade button next to it is doing. */
#rg-begin { display:block; margin:8px auto 2px; background:#00c8a0; color:#04121d; font-weight:bold; border:0; border-radius:6px; padding:9px 22px; font-size:.84rem; cursor:pointer; }
/* Under Begin and centred with it -- it is a choice ABOUT the run you are
   about to start, so it belongs next to the button that starts it. */
#rg-scratch-wrap { display:block; text-align:center; font-size:.72rem; color:rgba(255,255,255,.5); cursor:pointer; user-select:none; }
/* REQUIRED, not decoration: the id selector above outranks the browser's own
   [hidden] rule, so without this the element stays on screen with hidden set --
   which is the exact bug this pairing exists to fix. Same reason #rg-begin and
   #rg-pause each carry one. */
#rg-scratch-wrap[hidden] { display:none; }
#rg-scratch-wrap input { vertical-align:middle; margin-right:4px; cursor:pointer; }
#rg-scratch-wrap:has(input:disabled) { opacity:.35; cursor:default; }
#rg-begin[hidden] { display:none; }
/* Resume leads when there is a siege to go back to, so it gets the green and
   Begin drops to the quiet treatment -- the common case for someone returning
   to this page is that they meant to carry on, not start again.
   [hidden] guard for the same reason every other one here has it: the id
   selector sets display and outranks the browser's own [hidden]. */
#rg-resume { display:block; margin:8px auto 2px; background:#00c8a0; color:#04121d;
             font-weight:bold; border:0; border-radius:6px; padding:9px 22px;
             font-size:.84rem; cursor:pointer; }
#rg-resume[hidden] { display:none; }
#rg-resume + #rg-begin { background:rgba(255,255,255,.12); color:rgba(255,255,255,.75);
                         padding:6px 14px; font-size:.74rem; }

/* ---- THE FACTORY SHELF ----------------------------------------------------
   Seven buttons, always in the same order, whether or not you hold any. A menu
   that hides its empty slots cannot be learned by position, and being able to
   reach for the right item without reading is the entire point of the change.
   Auto-fit rather than a fixed column count, so it is a comfortable grid on a
   monitor and two columns on a phone without a second breakpoint. */
/* `margin:0 auto 12px`, and the auto is LOAD-BEARING. `#rg-game > *` centres
   every child with margin-left/right:auto, and a `margin` SHORTHAND here resets
   both to 0 -- which pinned the whole shelf to the left edge of the page while
   everything else stayed in the centred column. Verified in the live page:
   shelf left 21px against the locations grid's 641px, both 720px wide. */
#rg-shelf { margin:0 auto 12px; }
#rg-shelf[hidden] { display:none; }
.rg-shelf-head { font-size:.7rem; text-transform:uppercase; letter-spacing:.06em;
                 color:rgba(255,255,255,.45); margin:0 0 6px; }
/* Fixed counts, not auto-fit: the row split is a MEANING (four strengths of one
   item, then three different ones), so it must not depend on the width. */
.rg-shelf-grid { display:grid; gap:6px; }
.rg-shelf-grid + .rg-shelf-grid { margin-top:6px; }
.rg-shelf-volleys { grid-template-columns:repeat(4, 1fr); }
.rg-shelf-rest    { grid-template-columns:repeat(3, 1fr); }
.rg-item { display:grid; grid-template-columns:22px 1fr; grid-template-rows:auto auto;
           gap:1px 8px; align-items:center; text-align:left;
           background:#0d1e30; border:1px solid rgba(255,255,255,.1); border-radius:7px;
           padding:7px 9px; cursor:pointer; color:inherit; font:inherit; }
.rg-item img { grid-row:1 / span 2; width:22px; height:22px; object-fit:contain; }
.rg-item-name { font-size:.76rem; font-weight:bold; color:#fff; line-height:1.2; }
.rg-item-name b { color:#00c8a0; font-weight:bold; }
.rg-item-blurb { font-size:.66rem; color:rgba(255,255,255,.45); line-height:1.25; }
/* Holding one lights it up: at a glance the shelf shows what is available
   without reading a single count. */
.rg-item.rg-item-have { border-color:rgba(0,200,160,.55); background:#0e2637; }
/* Dimmed by COLOUR, not by opacity on the whole button. The shelf sits over the
   realm's theme art, and fading the button faded its background too -- the
   artwork came through the card and the text stopped being readable. It is a
   reference you read before you need it, so an unheld item still has to be
   legible. */
.rg-item:disabled { cursor:default; background:rgba(13,30,48,.85); border-color:rgba(255,255,255,.08); }
.rg-item:disabled .rg-item-name  { color:rgba(255,255,255,.55); }
.rg-item:disabled .rg-item-blurb { color:rgba(255,255,255,.32); }
.rg-item:disabled .rg-item-name b { color:rgba(255,255,255,.45); }
.rg-item:disabled img { opacity:.4; }
/* The volley countdown. Drains along the bottom of all four, because any of
   them overwrites whatever is running -- see paintShelf(). */
.rg-item { position:relative; overflow:hidden; }
.rg-item-boost { position:absolute; left:0; bottom:0; height:3px; width:0;
                 background:#ffcc44; transition:width .1s linear; }
/* Pressing THIS one would replace a stronger boost with a weaker one and reset
   the clock. Marked rather than disabled -- overwriting is sometimes right,
   just never when it is a downgrade. */
.rg-item.rg-item-worse { border-color:rgba(255,204,68,.45); }
.rg-item.rg-item-worse .rg-item-name { color:rgba(255,204,68,.75); }

/* The free level applies ITSELF, so the card that gained it says so -- the log
   named it, but the log keeps three lines and this fires mid-fight. */
@keyframes rg-boon { 0% { box-shadow:0 0 0 0 rgba(255,204,68,.85); }
                     100% { box-shadow:0 0 0 14px rgba(255,204,68,0); } }
.rg-loc.rg-boon { border-color:rgba(255,204,68,.9); animation:rg-boon 1s ease-out 2; }
@media (prefers-reduced-motion: reduce) { .rg-loc.rg-boon { animation:none; } }

/* Shields banked, on the wall itself. They stack and never expire, and the wall
   glows identically for one or five, so the number is the only real signal. */
#rg-wall-shields { position:absolute; left:14px; top:6px; z-index:2; font-size:.7rem;
                   font-weight:bold; color:#04121d; background:#00c8a0;
                   border-radius:9px; padding:1px 7px; pointer-events:none;
                   white-space:nowrap; }
#rg-wall-shields[hidden] { display:none; }

/* ---- THE LAST STAND -------------------------------------------------------
   Fixed rather than absolute: it announces something that just happened to the
   whole board, and on a phone the board is taller than the screen -- anchoring
   it to the page would put the announcement wherever you happened to be
   scrolled. [hidden] guard is REQUIRED, because the rule below sets display and
   an id selector outranks the browser's own [hidden]. */
#rg-nuke, #rg-confirm, #rg-defeat { position:fixed; inset:0; z-index:50; display:flex; align-items:center;
           justify-content:center; padding:20px; background:rgba(4,10,18,.82);
           /* Belt and braces. The markup now sits OUTSIDE #rg-game so the
              board's centring rule cannot reach it, but an element's own
              max-width clamps it even when position:fixed -- which is exactly
              how this shipped as a 720px column in the middle of the page.
              Stated here so a future container rule cannot quietly redo it. */
           max-width:none; margin:0; }
#rg-nuke[hidden], #rg-confirm[hidden], #rg-defeat[hidden] { display:none; }
.rg-nuke-card { max-width:340px; text-align:center; background:#12263a;
                border:1px solid rgba(255,204,68,.55); border-radius:12px;
                padding:20px 22px; box-shadow:0 0 40px rgba(255,204,68,.28); }
.rg-nuke-emoji { font-size:2.6rem; line-height:1; margin-bottom:8px; }
/* text-align is stated on the children, not just inherited from the card: the
   platform stylesheet aligns headings left, which beats inheritance and left
   the title hanging off to one side of a centred card. Measured: card computed
   `center`, the h3 inside it computed `left`. */
.rg-nuke-card h3 { margin:0 0 8px; font-size:1rem; color:#ffcc44; letter-spacing:.04em;
                   text-transform:uppercase; text-align:center; }
.rg-nuke-card p { margin:0 0 8px; font-size:.82rem; color:rgba(255,255,255,.8); line-height:1.5;
                  text-align:center; }
.rg-nuke-card p.rg-nuke-sub { font-size:.74rem; color:rgba(255,255,255,.45); margin-bottom:14px; }
.rg-nuke-card strong { color:#fff; }
#rg-nuke-ok, #rg-confirm-yes, #rg-defeat-ok { background:#ffcc44; color:#1a1200; font-weight:bold; border:0;
              border-radius:6px; padding:9px 20px; font-size:.82rem; cursor:pointer; }
/* Cancel is the quiet one and gets focus, so the safe choice is both the
   default and the less eye-catching -- the loud button should not be the
   one that throws an hour away. */
#rg-confirm-no { background:rgba(255,255,255,.14); color:rgba(255,255,255,.85);
                 border:0; border-radius:6px; padding:9px 16px; font-size:.82rem;
                 cursor:pointer; margin-left:8px; }
#rg-retreat { font-size:1.05rem; }


/* The send-off wears the horde's colour, not the Tower's -- this is their
   moment. The card is otherwise the one every modal here uses. */
#rg-defeat .rg-nuke-card { border-color:rgba(192,57,43,.6); box-shadow:0 0 40px rgba(192,57,43,.3); }
#rg-defeat h3 { color:#e8705f; }
/* The number IS the message, so it gets the room. */
.rg-defeat-hero { margin:2px 0 12px !important; line-height:1; }
.rg-defeat-hero strong { display:block; font-size:2.8rem; color:#fff; letter-spacing:-.02em; }
.rg-defeat-hero span { display:block; font-size:.68rem; text-transform:uppercase;
                       letter-spacing:.18em; color:rgba(255,255,255,.45); margin-top:4px; }
/* Its own line above the button, and quieter than it: leaving the page is the
   secondary action here, playing again is the primary one. */
.rg-defeat-board { display:block; margin:0 0 12px; font-size:.76rem; color:#00c8a0;
                   text-decoration:underline; }

/* ---- PAUSED ---------------------------------------------------------------
   Unmistakable at a glance. Someone coming back to their phone after twenty
   minutes needs to see instantly that the game is held and nothing was lost --
   a small icon change in the HUD is not enough to carry that. The field dims
   and the horde stops mid-approach, so the state reads as "held", not "over". */
#rg-game.rg-paused #rg-field { filter:grayscale(.7) brightness(.55); }
#rg-game.rg-paused #rg-field::after {
  content:'PAUSED'; position:absolute; inset:0; display:flex;
  align-items:center; justify-content:center;
  font-size:.9rem; font-weight:bold; letter-spacing:.28em;
  color:rgba(255,255,255,.92); text-shadow:0 2px 6px rgba(0,0,0,.9);
  pointer-events:none; z-index:3;
}
/* Only while held: a steady pulse on the resume control, so the way back into
   the game is the thing that draws the eye. */
#rg-game.rg-paused #rg-pause { animation:rg-pulse 1.4s ease-in-out infinite; }
@keyframes rg-pulse { 0%,100% { opacity:1; } 50% { opacity:.45; } }
@media (prefers-reduced-motion: reduce) {
  #rg-game.rg-paused #rg-pause { animation:none; }
}

/* ---- DOUBLE-TAP ZOOM ------------------------------------------------------
   This game is tapped FAST -- deploy, raise, strike, fortify, upgrade -- and a
   phone reads two quick taps near the same spot as "zoom in", so a decent burst
   of tapping left the board magnified and half off-screen. It is native
   behaviour, but it is not immovable: touch-action switches it off.

   `manipulation` and not `none`: none would also kill scrolling, and this page
   scrolls past the board to the log. manipulation disables double-tap zoom and
   keeps panning AND pinch-zoom, so nobody loses the ability to magnify the
   field deliberately -- it only stops the accidental version.

   NOT user-scalable=no in the viewport meta, which is the other common answer:
   header.php is shared by every page on the platform, so that would disable
   pinch zoom everywhere, and iOS Safari has ignored it since 10 anyway.

   Set on the container: touch-action on an ancestor restricts its descendants,
   so this covers every control inside the board. The controls repeat it because
   they are the elements actually being hammered. */
#rg-game { touch-action:manipulation; }
#rg-game button, #rg-game label, #rg-game input { touch-action:manipulation; }
/* A fast repeat tap on a control can also start a text selection or a callout
   on iOS. Controls only -- the log stays selectable. */
#rg-game button, #rg-scratch-wrap { -webkit-user-select:none; user-select:none; }

/* The lessons Obscura paid for: fits a phone, nothing pinned over the board. */
@media (max-width:760px) { #rg-locations { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:560px) {
  /* The shelf sits between the field and the controls, so on a phone its
     one-line descriptions are seven lines pushing the game off the screen. The
     names and counts stay; the descriptions go, because "What am I actually
     deciding?" carries the full explanation and the tooltip carries the short
     one. */
  .rg-item-blurb { display:none; }
  .rg-shelf-grid { grid-template-columns:repeat(auto-fit, minmax(112px, 1fr)); gap:4px; }
  .rg-item { padding:6px 7px; grid-template-columns:18px 1fr; gap:0 6px; }
  .rg-item img { grid-row:1; width:18px; height:18px; }
  .rg-item-name { font-size:.7rem; }

  /* The BLURB goes, not the title. Most people reaching this from the nav are
     on a phone, and a page that opens with no heading at all reads as broken;
     the title alone is one compact line. */
  .rg-blurb.rg-intro { display:none; }
  h2.rg-intro { font-size:1.05rem; margin:0 0 8px; }
  /* ---- TIGHT. Seven location cards, a two-row item shelf and a HUD do not
     fit a phone at desktop spacing, and this game is played by REACTING -- a
     control you have to scroll to find is a control you do not use. Everything
     below is space bought back: the gaps between sections, the padding inside
     the cards, the bar captions, and the item descriptions.
     The captions ("training next guardian") go because the bar directly above
     each one already says it, and there are five of them. ---- */
  /* The field goes back to its phone size: one set of numbers, not a
     re-statement of every selector above. */
  #rg-game { --rg-u:26px; --rg-tough:34px; --rg-badge:12px; --rg-off:-6px;
             --rg-barh:2px; --rg-tower:26px; --rg-portal:30px;
             --rg-tracer-w:12px; --rg-tracer-h:2px; --rg-field-h:56px; }
  #rg-field { margin-bottom:6px; }
  #rg-locations { gap:4px; }
  .rg-loc { padding:5px 6px; border-radius:6px; }
  .rg-loc-name { font-size:.62rem; gap:4px; }
  .rg-loc-name .rg-icon { width:12px; height:12px; }
  .rg-loc-stat { font-size:.7rem; margin:2px 0 4px; }
  .rg-loc-stat strong { font-size:.88rem; }
  .rg-cap { display:none; }
  .rg-bar { margin-bottom:4px; }
  .rg-act { padding:6px 7px; font-size:.7rem; margin:0 2px 2px 0; }
  /* No space between sections -- the board reads as one block on a phone. */
  #rg-shelf { margin:6px auto 6px; }
  .rg-shelf-head { font-size:.6rem; margin-bottom:3px; }
  .rg-shelf-grid { gap:3px; }
  .rg-shelf-grid + .rg-shelf-grid { margin-top:3px; }
  #rg-begin { padding:7px 14px; font-size:.76rem; }
  #rg-scratch-wrap { font-size:.62rem; }
  /* Stays side by side on a phone too -- stacking it is the extra row this
     whole arrangement exists to avoid. */
  #rg-mine-row { margin-top:4px; }
  #rg-mine-row .rg-mine-body { gap:8px; }
  #rg-help { font-size:.78rem; margin-top:2px; }
  #rg-log { font-size:.68rem; }
  body::after { content:none !important; display:none !important; }
  #quick-menu { display:none !important; }
  #back-to-top-button { display:none !important; }
  #rg-game { padding-bottom:calc(env(safe-area-inset-bottom, 0px) + 12px); }
  /* Belt and braces: nothing in here may make the page scroll sideways. */
  #rg-game, #rg-locations, #rg-field { max-width:100%; }
  #rg-game { overflow-x:hidden; }
}
</style>

<script>
(function () {
  var game = document.getElementById('rg-game');
  if (!game) return;

  /* The snapshot, handed over from PHP. COPIES -- nothing written back. */
  var SNAPSHOT = {
    levels: <?php echo json_encode($rg_levels); ?>,
    army:   <?php echo intval($rg_army); ?>,
    armed:  <?php echo intval($rg_armed); ?>,
    cache:  <?php echo intval($rg_cache); ?>,
    wlevel: <?php echo intval($rg_wlevel); ?>,
    start:  <?php echo intval($rg_start_wave); ?>,
    acache: <?php echo intval($rg_acache); ?>,
    kitTower:  <?php echo json_encode($rg_kit_tower); ?>,
    kitRaid:   <?php echo json_encode($rg_kit_raid); ?>,
    kitReserve:<?php echo json_encode($rg_kit_reserve); ?>,
    wpool:  <?php echo json_encode($rg_wpool); ?>,
    items:  <?php echo json_encode($rg_items); ?>,
    apool:  <?php echo json_encode($rg_apool); ?>,
    wcat:   <?php echo json_encode($rg_wcat); ?>,
    acat:   <?php echo json_encode($rg_acat); ?>,
    crypt:  <?php echo intval($rg_crypt); ?>,
    garrison:<?php echo intval($rg_garrison); ?>,
    garmed: <?php echo intval($rg_g_armed); ?>,
    garmored:<?php echo intval($rg_g_armored); ?>,
    raiders:<?php echo intval($rg_raiders); ?>,
    ramed:  <?php echo intval($rg_r_armed); ?>,
    rarmored:<?php echo intval($rg_r_armored); ?>,
    alevel: <?php echo intval($rg_alevel); ?>
  };

  /*
   * FROM SCRATCH. The same conscript floor a player with no realm gets, offered
   * to everyone as a toggle -- an established realm is a big head start, and
   * there is no way to feel the early game once you have one.
   *
   * It is a whole baseline rather than a flag, so nothing downstream has to know
   * which mode it is in: REALM points at one object or the other and reset()
   * reads it exactly the same way. The catalogues are shared because they are
   * the platform's reference data, not the player's.
   */
  /*
   * A siege already in progress, handed over from the server. Convenience
   * only: it is never scored, so a tampered snapshot buys nothing but a nicer
   * place to resume from. Null when there is nothing to go back to.
   */
  var SAVED = <?php echo $rg_saved ? $rg_saved['state'] : 'null'; ?>;

  var SCRATCH = <?php echo json_encode($rg_scratch); ?>;
  SCRATCH.wcat = SNAPSHOT.wcat;
  SCRATCH.acat = SNAPSHOT.acat;

  var HAS_REALM = <?php echo $rg_has_realm ? 'true' : 'false'; ?>;
  var scratchOn = false;
  // Whichever baseline is live. Reassigned by the toggle; read everywhere else.
  var REALM = SNAPSHOT;
  // CANDIDATES, not the horde. A Discord avatar url 404s whenever someone has
  // changed their picture since we cached the hash, and the fallback turned the
  // field into a wall of identical skulls. Only verified faces get used.
  var CANDIDATES = <?php echo json_encode($rg_horde); ?>;
  var VERIFIED = [], HORDE = [];
  var UNITS = <?php echo json_encode($rg_units); ?>;   // your soldiers, as NFT art

  /* Deterministic core: seeded PRNG, fixed timestep, no Math.random. */
  var SEED = 20260909;
  function mulberry32(a) {
    return function () {
      a |= 0; a = a + 0x6D2B79F5 | 0;
      var t = Math.imul(a ^ a >>> 15, 1 | a);
      t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
      return ((t ^ t >>> 14) >>> 0) / 4294967296;
    };
  }
  var rand = mulberry32(SEED);
  // Where the Portal stands on the field, in percent. The icon is positioned at
  // the same figure in CSS, so guardians step out of it rather than near it.
  /*
   * REALMS' OWN GEAR DROP TABLE, keyed off Armory level.
   *
   * Copied from ajax/get-armory.php:151-163, which is what the Armory modal
   * shows players -- so a drop in here behaves like a drop out there rather
   * than like a number this game invented. Levels 9+ share the top curve.
   *
   * It also gives the Armory upgrade a real payoff: raising it mid-siege shifts
   * the whole distribution up, so later gear is genuinely better rather than
   * merely more frequent.
   */
  var TIER_ODDS = {
    1: {1:55, 2:30, 3:15},
    2: {1:55, 2:30, 3:15},
    3: {1:35, 2:30, 3:20, 4:10, 5:5},
    4: {1:35, 2:30, 3:20, 4:10, 5:5},
    5: {1:15, 2:18, 3:20, 4:18, 5:15, 6:8, 7:5, 8:1},
    6: {1:15, 2:18, 3:20, 4:18, 5:15, 6:8, 7:5, 8:1},
    7: {1:8, 2:10, 3:14, 4:16, 5:18, 6:16, 7:10, 8:5, 9:2, 10:1},
    8: {1:8, 2:10, 3:14, 4:16, 5:18, 6:16, 7:10, 8:5, 9:2, 10:1},
    9: {1:5, 2:8, 3:12, 4:15, 5:20, 6:18, 7:12, 8:6, 9:3, 10:1}
  };
  // Seeded, so a forged item is part of the reproducible run like everything
  // else -- a server replaying the inputs must roll the same gear.
  function rollTier() {
    var lvl = Math.max(1, L('armory'));
    var odds = TIER_ODDS[lvl >= 9 ? 9 : lvl] || {1:100};
    var total = 0, t;
    for (t in odds) total += odds[t];
    var pick = rand() * total, acc = 0;
    for (t in odds) { acc += odds[t]; if (pick <= acc) return parseInt(t, 10); }
    return 1;
  }

  var PORTAL_X = 25;
  var TICK = 100;
  var actionLog = [];

  var S = {};
  function reset() {
    S = {
      running:false, over:false, paused:false, nuked:false, tick:0, wave:REALM.start - 1,
      hp:100, maxhp:100, carbon:0,
      // The reserve is what is LEFT: everyone alive who is not already on the
      // wall or out on a raid. Without this the same guardian would be counted
      // twice and the army would appear to grow at the whistle.
      // reserve/garrison hold GUARDIANS ({w,a}), not counts -- see equip().
      reserve:[],
      weapons:REALM.cache, armor:REALM.acache, dead:REALM.crypt,
      garrison:[], items:[], shield:0, boost:0, boostFor:0, boostMax:0, sortied:[], emerging:[],
      wpool:REALM.wpool.slice(), apool:REALM.apool.slice(),
      lvl:JSON.parse(JSON.stringify(REALM.levels)),
      prod:{ barracks:0, armory:0, forge:0, factory:0, mine:0, portal:0, reinforce:0, crypt:0 },
      bought:{ tower:0, barracks:0, armory:0, crypt:0, portal:0, factory:0, mine:0 },
      foes:[], nextAttack:0, betweenWaves:0, fireIdx:0
    };

    /*
     * OPEN WHERE THE REALM ACTUALLY STANDS -- and do it in reset(), so it shows
     * on page load rather than only once Begin is pressed. The board should
     * describe your realm the moment you arrive.
     *
     * Tower soldiers (location 2) man the wall. They are already standing there,
     * so starting with an empty wall asked the player to deploy them twice.
     * Anything over the garrison cap falls back to the reserve rather than
     * vanishing.
     *
     * Soldiers on raids (location 3) are ALREADY OUT, so they stand in the field
     * beyond the Portal rather than queueing to emerge -- they left before you
     * got here. Spread outward from the Portal so a group reads as a group.
     *
     * Both keep their own kit: soldiers.weapon_id / armor_id is that soldier's
     * equipment, distinct from the unissued cache in `gear`, so seating them
     * armed and armoured costs the cache nothing.
     */
    // Each guardian arrives with the kit they actually carry in the realm.
    var cap = garrisonCap();
    for (var g = 0; g < REALM.kitTower.length; g++) {
      var kt = { w: REALM.kitTower[g].w, wn: REALM.kitTower[g].wn,
                 a: REALM.kitTower[g].a, an: REALM.kitTower[g].an };
      if (S.garrison.length < cap) S.garrison.push(kt); else S.reserve.push(kt);
    }
    for (var v = 0; v < REALM.kitReserve.length; v++) {
      S.reserve.push({ w: REALM.kitReserve[v].w, wn: REALM.kitReserve[v].wn,
                       a: REALM.kitReserve[v].a, an: REALM.kitReserve[v].an });
    }
    /*
     * Spread the raid line across the ground it actually has. A fixed 3% step
     * clamped to 96 piled every raider past the twenty-fourth onto the same
     * pixel, so a big raid party showed as a short line and a clump at the
     * edge. Step down as the party grows instead: 3% apart when there is room,
     * tighter when there is not, never stacked.
     */
    var rn = REALM.kitRaid.length, rspan = 96 - (PORTAL_X + 2);
    var rstep = rn > 1 ? Math.min(3, rspan / (rn - 1)) : 0;
    for (var r = 0; r < rn; r++) {
      var kr = REALM.kitRaid[r];
      S.sortied.push({
        pos:   PORTAL_X + 2 + r * rstep,
        w: kr.w, wn: kr.wn, a: kr.a, an: kr.an,
        hp:    unitHp(kr), max: unitHp(kr),
        slot:  unitSeq++
      });
    }
  }

  /* ---- Kit, and what it is worth ---------------------------------------
   * A guardian's weapon LEVEL decides how hard they hit and their armour LEVEL
   * how much they can absorb -- not a yes/no flag with the cache's best level
   * applied to everyone, which made a level-1 pistol worth a level-10 launcher.
   * --------------------------------------------------------------------- */
  /* ---- Factory consumables ---------------------------------------------
   * The Factory rolls the SAME seven items Realms uses, at the odds
   * getFactoryOdds() gives for its level, and each does here what it does
   * there rather than being a generic "fortify":
   *
   *   Double Rewards  a damage shield -- absorbs the next hit, consumed doing it
   *   Random Reward   a free level to a random location
   *   Fast Forward    halves the wait: every production timer completes now
   *   N% Success      effectiveness for a spell, at the magnitude it names
   *
   * Rolled with the seeded PRNG so a run stays reproducible.
   * -------------------------------------------------------------------- */
  function rollItem() {
    if (!REALM.items.length) return null;
    var total = 0, i;
    for (i = 0; i < REALM.items.length; i++) total += REALM.items[i].pct;
    if (total <= 0) return REALM.items[0];
    var pick = rand() * total, acc = 0;
    for (i = 0; i < REALM.items.length; i++) {
      acc += REALM.items[i].pct;
      if (pick <= acc) return REALM.items[i];
    }
    return REALM.items[REALM.items.length - 1];
  }
  /*
   * There is no help MAP here any more. Every item's name and one-line
   * description live in $rg_con_ui in the PHP above, which is what renders the
   * shelf buttons -- so the text a player reads and the text this file carries
   * cannot drift apart, because there is only one of them.
   */
  var UPGRADABLE = ['tower','barracks','armory','crypt','portal','factory','mine'];
  function useItem(it) {
    if (!it) return;
    if (it.id === 6) {                       // Double Rewards -- damage shield
      S.shield++;
      log('A shield goes up over the wall.');
    } else if (it.id === 1) {                // Random Reward -- free level
      var k = UPGRADABLE[Math.floor(rand() * UPGRADABLE.length)];
      S.lvl[k]++;
      log('Random Reward: ' + k + ' rises to ' + S.lvl[k] + ' for nothing.');
      /*
       * The level applies ITSELF -- it is not a free press of an Upgrade
       * button -- and the log said which one, but the log keeps three lines and
       * this fires mid-fight, so it was routinely missed. The card that gained
       * it flashes, which is where the player is already looking.
       */
      var lvlEl = document.getElementById('rg-lvl-' + k);
      var card  = lvlEl && lvlEl.closest ? lvlEl.closest('.rg-loc') : null;
      if (card) {
        card.classList.remove('rg-boon');
        void card.offsetWidth;            // restart the animation if it re-fires
        card.classList.add('rg-boon');
        setTimeout(function () { card.classList.remove('rg-boon'); }, 2000);
      }
    } else if (it.id === 3) {                // Fast Forward -- halve the wait
      S.prod.barracks = barracksRate();
      S.prod.armory   = armoryRate();
      S.prod.forge    = forgeRate();
      S.prod.factory  = factoryRate();
      S.prod.mine     = mineRate();
      S.prod.portal   = portalRate();
      S.prod.crypt    = cryptRate();   // a resurrection readied too
      log('Fast Forward: every line finishes at once.');
    } else {                                 // 25/50/75/100% Success
      var pct = { 2:0.25, 4:0.50, 5:0.75, 7:1.00 }[it.id] || 0.25;
      S.boost = pct;
      /*
       * The duration scales with the magnitude: seconds are the percentage
       * over ten, so +25% runs 2.5s and +100% runs 10s. They were a flat six
       * seconds each, which made the four nearly interchangeable -- the only
       * difference was a number that, as measured earlier, often converts to no
       * extra kills at all once a volley already one-shots.
       *
       * Scaling the time as well makes the rare ones genuinely rarer in effect
       * rather than just in name, and it is the ratio a player can hold in
       * their head: the number on the tin is the number of tenths of a second.
       */
      S.boostFor = Math.round(pct * 100);
      S.boostMax = S.boostFor;   // what the countdown on the shelf divides by
      S.hp = Math.min(S.maxhp, S.hp + 8);
      log(it.name + ': the guns bite ' + Math.round(pct * 100) + '% harder.');
    }
  }

  /*
   * A gear NAME decides two things, and the two use different spellings:
   *
   *   icon   icons/<lowercase, spaces to DASHES>.png   "machine-gun.png"
   *   sound  audio/sounds/<lowercase, spaces REMOVED>.mp3  "machinegun.mp3"
   *
   * Both were verified against the live server. machinegun.png is a 404 and
   * machine-gun.mp3 is a 404 -- they are genuinely different conventions, so
   * deriving one from the other with a single rule silently produces nothing.
   */
  function gearIcon(name) {
    if (!name) return '';
    return 'icons/' + String(name).toLowerCase().replace(/%/g, '').replace(/\s+/g, '-') + '.png';
  }
  /*
   * The SAME whitelist cryptcrawlWeaponSfxName() keeps (cryptcrawl-render.php:21).
   * Deriving the basename works for every weapon that has a sound today, but a
   * weapon added to the table tomorrow would derive a name with no file behind
   * it and fire a 404 on every shot. Crypt Crawl answers a miss with null and
   * skips the weapon layer; here a miss falls back to the unarmed bank, so a
   * guardian holding something unrecognised still makes a sound.
   */
  var WEAPON_SFX = {
    'melee': 'melee', 'tactical-katana': 'tacticalkatana', 'pistol': 'pistol',
    'grenade': 'grenade', 'sniper-rifle': 'sniperrifle', 'machine-gun': 'machinegun',
    'demolition': 'demolition', 'flamethrower': 'flamethrower',
    'rocket-launcher': 'rocketlauncher', 'artillery': 'artillery'
  };
  function gearSfx(name) {
    if (!name) return '';
    var slug = String(name).toLowerCase().replace(/%/g, '').replace(/\s+/g, '-');
    return WEAPON_SFX[slug] || '';
  }
  // Forged gear becomes a real item of that tier, so it wears and sounds like
  // something rather than being an anonymous level.
  function catPick(cat, tier) {
    if (!cat || !cat.length) return null;
    var best = cat[0];
    for (var i = 0; i < cat.length; i++) {
      if (Math.abs(cat[i].lvl - tier) < Math.abs(best.lvl - tier)) best = cat[i];
    }
    return { lvl: best.lvl, name: best.name };
  }

  function soldierDamage(s) { return s.w > 0 ? 2 + s.w : 1; }
  function unitHp(s)        { return (s.w > 0 ? 6 : 4) + (s.a > 0 ? 2 + s.a : 0); }
  function armedCount()     { var n = 0; for (var i = 0; i < S.garrison.length; i++) if (S.garrison[i].w > 0) n++; return n; }
  function armoredCount()   { var n = 0; for (var i = 0; i < S.garrison.length; i++) if (S.garrison[i].a > 0) n++; return n; }
  // Issue from the cache to anyone short of kit. Best first: a quartermaster
  // hands out the good stuff, and it keeps early waves feeling equipped.
  /*
   * WHAT A SOLDIER IS HANDED FOLLOWS THE ARMORY'S OWN DROP TABLE.
   *
   * This used to shift() the front of the pool, which is kept best-first -- so
   * a Strike of five drew the five best pieces, and consecutive entries in a
   * sorted list are usually the SAME item. Every guardian rode out identically
   * armed, which read as "default gear" and made the Armory level invisible
   * once you owned one good weapon.
   *
   * The quartermaster now rolls a tier on TIER_ODDS for the current Armory
   * level -- the same table the Armory modal shows players and the same one
   * the forge rolls on -- and issues the nearest piece actually in the cache.
   * So the spread of gear going out the Portal is the spread the Armory
   * advertises, a higher Armory really does put better kit on more soldiers,
   * and a cache holding a mix issues that mix instead of its top five.
   *
   * Seeded, like every other roll, so a replay issues the same gear.
   */
  function drawFor(pool) {
    if (!pool.length) return null;
    var tier = rollTier(), bi = 0;
    for (var i = 1; i < pool.length; i++) {
      if (Math.abs(pool[i].lvl - tier) < Math.abs(pool[bi].lvl - tier)) bi = i;
    }
    return pool.splice(bi, 1)[0];
  }
  function equip(s) {
    if (s.w === 0) { var pw = drawFor(S.wpool); if (pw) { s.w = pw.lvl; s.wn = pw.name; } }
    if (s.a === 0) { var pa = drawFor(S.apool); if (pa) { s.a = pa.lvl; s.an = pa.name; } }
    return s;
  }
  /*
   * Trade a guardian up, and only up. Takes the best piece in the cache that
   * beats what they carry; the worn one is left behind rather than returned,
   * because a cache that never shrinks is a cache the Armory can never forge
   * into. Returns true when something was actually issued.
   */
  function upgradeFrom(unit, lvlKey, nameKey, pool) {
    if (!unit || !pool.length) return false;
    var bi = -1;
    for (var i = 0; i < pool.length; i++) {
      if (pool[i].lvl > unit[lvlKey] && (bi < 0 || pool[i].lvl > pool[bi].lvl)) bi = i;
    }
    if (bi < 0) return false;
    var piece = pool.splice(bi, 1)[0];
    unit[lvlKey] = piece.lvl;
    unit[nameKey] = piece.name;
    return true;
  }
  function poolIn(pool, piece, cap) {
    if (!piece || pool.length >= cap) return;
    pool.push(piece);
    pool.sort(function (x, y) { return y.lvl - x.lvl; });   // best first
  }

  /* Every level is a RATE or a CAP -- never one power number. That is the whole
     point of mapping realm locations onto a siege. */
  function L(k) { return Math.max(1, S.lvl[k] || 1); }
  function garrisonCap()  { return 3 + L('tower'); }
  function reserveCap(l)  { return 6 + (l === undefined ? L('barracks') : l) * 3; }
  /*
   * Caps take an explicit level so the HUD can ask "would the NEXT upgrade
   * actually create room?" -- see paintBar. Telling a player to upgrade when
   * upgrading cannot help is worse than saying nothing.
   */
  function weaponCap(l)   { return 4 + (l === undefined ? L('armory') : l) * 3; }
  function itemCap(l)     { return 1 + Math.ceil((l === undefined ? L('factory') : l) / 2); }
  function barracksRate() { return Math.max(12, 62 - L('barracks') * 5); }
  function armoryRate()   { return Math.max(16, 72 - L('armory') * 5); }
  // Armour comes slower than weapons and is capped lower: it is the resource
  // that turns a breach into a scratch, so it should never be abundant.
  function forgeRate()    { return Math.max(34, 150 - L('armory') * 9); }
  function armorCap(l)    { return 2 + Math.ceil((l === undefined ? L('armory') : l) / 2); }
  function factoryRate()  { return Math.max(60, 240 - L('factory') * 16); }
  function mineRate()     { return Math.max(6, 26 - L('mine') * 2); }
  function portalRate()   { return Math.max(40, 170 - L('portal') * 12); }
  // How fast the Tower refills itself from the Barracks. Faster with Barracks
  // level, so investing there is felt as resilience rather than a bigger number.
  function reinforceRate()  { return Math.max(4, 20 - L('barracks') * 1.5); }
  function sortieSize()   { return Math.max(1, Math.ceil(L('portal') / 2)); }
  /*
   * THE CRYPT COSTS TIME, NOT CARBON.
   *
   * Raising used to be bought with CARBON, priced down by Crypt level. It reads
   * better as a production line like every other location: the Crypt works up
   * to a rite, the bar shows how far along it is, and the Crypt LEVEL is what
   * shortens the wait. Nothing is bought; the rite is either ready or it is not.
   *
   * ONE RITE EMPTIES THE WHOLE CRYPT, which is what makes the wait a decision
   * rather than a queue. Raising the moment it is ready spends the cycle on
   * whoever happens to be dead; holding on returns everyone who falls in the
   * meantime for the same wait. So the question is whether the wall survives
   * long enough to be worth banking -- and the cost of being wrong is real,
   * because a cycle spent on one body is a cycle not spent on ten.
   *
   * The rate is longer than it was when the rite returned a single guardian
   * (was max(25, 180 - level*10)), because the payload is now the whole Crypt.
   *
   * The Crypt holds unlimited dead, so this line can never be blocked by
   * capacity -- only by having nobody left to bring back.
   */
  function cryptRate()    { return Math.max(50, 320 - L('crypt') * 15); }
  /*
   * Quadratic, not linear. At 18*level a wave's kills paid for two or three
   * upgrades, so defense compounded faster than the ladder climbed and the run
   * became unloseable by wave four. Now each level costs meaningfully more than
   * the last, so upgrading is a choice against raising the dead rather than
   * something you do with spare change.
   */
  /*
   * Priced on what you have bought THIS RUN, not on the absolute level.
   *
   * Keying off absolute level meant a realm-fed player started every location
   * at level 10+, so an upgrade cost over a thousand from wave one -- while
   * whichever locations their realm had neglected stayed cheap. Reported
   * exactly that way: "upgrade buttons rarely appear and it's usually only
   * crypt or factory", ending a run on 1781 unspent CARBON.
   *
   * Now the first upgrade of a run is affordable whatever your realm looks
   * like, and each one after costs more. Spending is a live decision again
   * instead of a lottery.
   */
  function upgradeCost(k) { return 45 + 55 * (S.bought[k] || 0); }
  // Weapon LEVEL matters, not just count -- a better cache hits harder.
  function towerDamage()  {
    // Summed per guardian from their OWN weapon level.
    var d = 0;
    for (var i = 0; i < S.garrison.length; i++) d += soldierDamage(S.garrison[i]);
    // Success-family consumables read as combat effectiveness here, at the
    // magnitude their name promises (25/50/75/100%).
    if (S.boostFor > 0) d = Math.round(d * (1 + S.boost));
    return d;
  }

  var el = {};
  ['wave','hp','carbon','reserve','weapons','dead','garrison','garrison-cap','armed','status',
   'sortied','items','mine-rate','armor','armored',
   'lvl-tower','lvl-barracks','lvl-armory','lvl-crypt','lvl-portal','lvl-factory','lvl-mine',
   'bar-barracks','bar-armory','bar-forge','bar-factory','bar-mine','bar-portal','bar-crypt',
   'cap-barracks','cap-armory','cap-forge','cap-factory','cap-mine','cap-portal','cap-crypt']
    .forEach(function (k) { el[k] = document.getElementById('rg-' + k); });
  var foesEl = document.getElementById('rg-enemies');
  var sortieEl = document.getElementById('rg-sortie');
  var logEl = document.getElementById('rg-log');
  var beginBtn = document.getElementById('rg-begin');

  /* ---- Tower fire ------------------------------------------------------
   * DESKTOP ONLY, on purpose. A late wave puts dozens of attackers across the
   * Portal within a second or two, and that is exactly the moment a phone is
   * already animating a screen full of avatars -- adding a burst of elements
   * there costs frames on the device least able to spare them. The whole
   * feature is therefore switched off below the mobile breakpoint rather than
   * merely made cheaper, and off again for anyone who asked for reduced
   * motion. Nothing downstream depends on it: tracers live outside S, so a
   * run plays and replays identically whether or not a single one is drawn.
   */
  var tracersEl = document.getElementById('rg-tracers');
  var fieldEl   = document.getElementById('rg-field');
  var towerEl   = document.getElementById('rg-tower-icon');
  var TRACERS = (function () {
    if (!tracersEl || !fieldEl || !window.matchMedia) return false;
    if (window.matchMedia('(max-width:560px)').matches) return false;
    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return false;
    // A coarse pointer is a touch device however wide the viewport claims to be.
    if (window.matchMedia('(pointer: coarse)').matches) return false;
    return true;
  })();
  /*
   * Where the shot leaves the Tower. Measured off the icon rather than hard
   * coded: it was 40px because the icon was 26px wide at left:14px, and the
   * icon is twice that on a browser now -- a constant would have tracers
   * starting in the middle of the tower.
   */
  var liveTracers = 0, flashTimer = 0;
  function muzzleX() {
    if (!towerEl || !fieldEl) return 40;
    return towerEl.getBoundingClientRect().right - fieldEl.getBoundingClientRect().left;
  }
  function tracer(posPct) {
    // Capped: past a certain density they stop reading as individual shots and
    // only cost paint, so a heavy wave draws a burst, not one per attacker.
    if (!TRACERS || liveTracers >= 10) return;
    var w = fieldEl.clientWidth;
    if (!w) return;
    var mx = muzzleX();
    var dx = (w * posPct / 100) - mx;
    if (dx <= 0) return;              // already past the muzzle; nothing to draw
    var t = document.createElement('i');
    t.className = 'rg-tracer';
    t.style.left = mx + 'px';
    t.style.setProperty('--rg-dx', dx + 'px');
    liveTracers++;
    t.addEventListener('animationend', function () {
      if (t.parentNode) t.parentNode.removeChild(t);
      liveTracers--;
    });
    tracersEl.appendChild(t);
    if (towerEl) {
      towerEl.classList.add('rg-firing');
      clearTimeout(flashTimer);
      flashTimer = setTimeout(function () { towerEl.classList.remove('rg-firing'); }, 90);
    }
  }
  // A finished run leaves nothing hanging in the air.
  function tracersClear() {
    if (!tracersEl) return;
    while (tracersEl.firstChild) tracersEl.removeChild(tracersEl.firstChild);
    liveTracers = 0;
    if (towerEl) towerEl.classList.remove('rg-firing');
  }

  /* ---- The cacophony. Crypt Crawl's weapon sounds, reused from audio/sounds/.
     Each guardian fires THEIR OWN weapon -- a wall of pistols sounds different
     from a wall of launchers -- and the unarmed swing fists, so an empty Armory
     is AUDIBLE before the counter is read. Cosmetic: never touches the sim. ---- */
  var UNARMED_SFX = ['fist','melee','tacticalkatana'];
  var sfxOn = true, sfxPool = {}, sfxCursor = 0;
  function sfxLoad(name) {
    if (sfxPool[name]) return sfxPool[name];
    var pool = [];
    for (var i = 0; i < 3; i++) {
      var a = new Audio('audio/sounds/' + name + '.mp3');
      a.preload = 'auto'; a.volume = 0.10; pool.push(a);
    }
    sfxPool[name] = { list: pool, i: 0 };
    return sfxPool[name];
  }
  function sfxPlay(name, vol) {
    if (!sfxOn) return;
    var p = sfxLoad(name), a = p.list[p.i];
    p.i = (p.i + 1) % p.list.length;
    try { a.currentTime = 0; a.volume = vol === undefined ? 0.10 : vol; a.play().catch(function () {}); } catch (e) {}
  }
  /*
   * THE SOUND IS THE GUARDIAN'S OWN WEAPON, and only fires when they do.
   *
   * It used to draw at random from a bank of every weapon in the game, so the
   * wall made noises for guns nobody was carrying. Now each shot is the actual
   * weapon of the guardian taking it -- a Machine Gun sounds like a machine gun
   * and a bare-handed guardian sounds like fists -- which also means the
   * cacophony tells you what your army is actually holding.
   *
   * Shooters rotate through the garrison so the volley is not always the same
   * two guardians, and the count is still capped: enough to read as a firefight
   * without being unlistenable.
   */
  function sfxVolley() {
    if (!sfxOn || !S.garrison.length) return;
    var shots = Math.min(2, Math.max(1, Math.ceil(S.garrison.length / 3)));
    for (var i = 0; i < shots; i++) {
      var g = S.garrison[(S.fireIdx + i) % S.garrison.length];
      var name = g.w > 0 ? gearSfx(g.wn) : UNARMED_SFX[(sfxCursor++) % UNARMED_SFX.length];
      if (!name) name = UNARMED_SFX[0];
      (function (n, d) { setTimeout(function () { sfxPlay(n, 0.10); }, d); })(name, i * 70);
    }
    S.fireIdx = (S.fireIdx + shots) % S.garrison.length;
  }

  /* ---- Music. Separate channel from the weapon effects, deliberately: the
     whole reason it is here is to hear one against the other. Autoplay policy
     is satisfied because nothing starts before the Begin button. ---- */
  var TRACKS = <?php echo json_encode($rg_tracks); ?>;
  var music = null, musicOn = true, trackIdx = 0, musicVol = 0.70;

  function musicLoad(i) {
    if (!TRACKS.length) return;
    var was = music && !music.paused;
    // Pause the outgoing track but do NOT blank its src: clearing src fires an
    // 'error' on the old element, and its handler used to null the shared
    // `music` reference -- which by then pointed at the NEW track. Switching
    // tracks silently killed playback. The handler below now checks identity,
    // so a late event from a discarded element cannot touch the current one.
    if (music) music.pause();
    trackIdx = i % TRACKS.length;
    var a = new Audio(TRACKS[trackIdx].url);
    // NOT loop: one track on repeat meant only ever hearing one of the two.
    // Playing to the end and advancing turns them into a playlist.
    a.loop = false;
    a.addEventListener('ended', function () { if (music === a) { musicLoad(trackIdx + 1); if (music) music.play().catch(function () {}); } });
    a.volume = musicVol;
    // A missing or unplayable track must not take the game with it.
    a.addEventListener('error', function () { if (music === a) music = null; });
    music = a;
    // Keep the picker showing what is actually playing -- the playlist advances
    // on its own, so the dropdown was lying about the current track.
    if (trackSel) trackSel.value = String(trackIdx);
    if (was && musicOn) music.play().catch(function () {});
  }
  function musicStart() {
    if (!TRACKS.length || !musicOn) return;
    if (!music) musicLoad(trackIdx);
    if (music) music.play().catch(function () {});
  }
  function musicStop() { if (music) music.pause(); }

  /*
   * Pre-flight the avatars. Each candidate is loaded once at page open; only
   * the ones that actually decode make it into VERIFIED. Doing it here rather
   * than server-side keeps the page free of eighty HEAD requests, and it warms
   * the browser cache as a side effect, so verified faces appear instantly.
   *
   * The horde is frozen from VERIFIED when a siege begins, so faces cannot
   * reshuffle mid-run as stragglers resolve. If nothing verifies, foes render
   * as plain markers -- an anonymous attacker beats a row of identical skulls.
   */
  CANDIDATES.forEach(function (c) {
    var im = new Image();
    im.onload  = function () { if (im.naturalWidth > 0) VERIFIED.push(c); };
    im.onerror = function () {};   // silently dropped
    im.src = c.img;
  });

  var foeSeq = 0, unitSeq = 0;
  // One element per foe/unit, kept for its lifetime -- see render().
  var foeNodes = {}, unitNodes = {};
  function foeIdentity(f) {
    if (!HORDE.length) return { name:'A raider', img:'' };   // plain marker, never a skull
    return HORDE[f.id % HORDE.length];
  }
  function escAttr(s) {
    return String(s).replace(/&/g, '&amp;').replace(/"/g, '&quot;')
                    .replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function log(msg, bad) {
    logEl.innerHTML = (bad ? '<b>' + msg + '</b>' : msg) + '<br>' +
      logEl.innerHTML.split('<br>').slice(0, 2).join('<br>');
  }

  /*
   * TUNING NOTE, from the first real playtest.
   *
   * The complaint was "I upgrade a little and the wave disappears -- I'm not
   * inclined to deploy more." The cause was not that the defense was too
   * strong. It was SPACING: foes were spawned 7-13 apart, so a 25-strong wave
   * strung out over 340 units of approach and arrived roughly one every two and
   * a half seconds. A single-target volley kills one in well under a second, so
   * the Tower never once fell behind. No mass, no leaks, nothing to react to.
   *
   * They now arrive as a COLUMN. Spacing is a third of what it was, so the wave
   * reaches the wall faster than one gun can chew through it, and the pressure
   * comes from being outnumbered rather than from any single attacker being
   * tough. That is where the frenetic part of a tower defense actually lives.
   */
  function buildWave(n) {
    var q = [], count = 4 + Math.floor(n * 1.75);
    for (var i = 0; i < count; i++) {
      var tough = n >= 3 && rand() < 0.16 + n * 0.015;
      var hp = (tough ? 24 : 10) + n * 4;
      q.push({ id:foeSeq++, hp:hp, max:hp,
               speed:(tough ? 0.24 : 0.38) + n * 0.006,
               // Tight. This one number is the difference between a siege and
               // a queue.
               pos:100 + i * (2.2 + rand() * 1.8), tough:tough });
    }
    return q;
  }

  function startWave() {
    S.wave++;
    S.foes = buildWave(S.wave);
    el.status.textContent = 'Wave ' + S.wave + ' incoming';
    log('Wave ' + S.wave + ' approaches &mdash; ' + S.foes.length + ' of them.');
    /*
     * Crypt Crawl's last-stand cue, announcing the wave. It is the one moment
     * in a run that deserves a sound of its own: the wall has held, the field
     * is briefly empty, and the next lot are coming. Louder than the weapon
     * chatter (0.20 against 0.09-0.16) because it fires roughly once a minute
     * rather than several times a second, and it has to carry over a wall
     * already firing.
     *
     * Cosmetic, like every other cue -- sfxPlay is a no-op when effects are
     * muted, and nothing here touches the simulation.
     */
    sfxPlay('laststand', 0.20);
  }

  function step() {
    /*
     * PAUSED. Gated here rather than by clearing the interval, so there is
     * exactly one timer for the life of a run and no way to start a second by
     * resuming twice. A no-op every 100ms costs nothing next to the running
     * game, and a hidden tab is throttled by the browser anyway.
     *
     * S.tick does not advance while paused, so the action log -- which is
     * [tick, action] pairs -- replays identically whether the player paused or
     * not. Pause is invisible to a server verifying the run.
     */
    if (S.paused) return;
    S.tick++;
    // Snapshot on a tick count rather than a wall clock, so a paused or
    // backgrounded siege is not writing rows while nothing is happening.
    if (S.tick % SAVE_EVERY === 0) saveRun();
    if (S.boostFor > 0) S.boostFor--;

    /*
     * PRODUCTION, AND THE BARS THAT REPORT IT.
     *
     * A bar completes when something ARRIVES, and not before. Every one of
     * these lines used to reset its timer whether or not it had produced
     * anything, so a full cache still showed a bar sweeping to 100% over and
     * over -- animation standing in for activity, and the one place a player
     * looks to answer "is this location doing anything for me?" was answering
     * yes while the answer was no.
     *
     * Blocked lines now HOLD at full instead of cycling. That is honest twice
     * over: the bar is not lying about output, and a stalled bar is a visible
     * signal that a cap is the thing limiting you -- which is exactly when the
     * Upgrade button underneath it is worth pressing. Holding at the threshold
     * also means production resumes the instant room appears, with no partial
     * timer lost.
     *
     * `produce()` takes the line's timer, its rate, and a function that returns
     * true when it actually delivered. One rule, six lines, no chance of a
     * seventh being added that quietly cycles on empty.
     */
    function produce(key, rate, deliver) {
      if (S.prod[key] < rate) S.prod[key]++;
      if (S.prod[key] >= rate) {
        if (deliver()) S.prod[key] = 0;
        else S.prod[key] = rate;   // held: full, and waiting on space
      }
    }

    // A raw recruit: no kit until the Armory can issue some.
    produce('barracks', barracksRate(), function () {
      if (S.reserve.length >= reserveCap()) return false;
      S.reserve.push({ w:0, wn:'', a:0, an:'' });
      return true;
    });
    /*
     * The Armory forges BOTH. It was producing weapons only, so armour was
     * whatever the cache started with and then gone for good -- reported as
     * "the armory doesn't seem to be generating armor, only weapons".
     * Armour comes slower than weapons, which is what keeps it a resource worth
     * spending carefully rather than a permanent second health bar.
     */
    // Forged gear rolls its tier from the realm's own drop table.
    produce('armory', armoryRate(), function () {
      if (S.wpool.length >= weaponCap()) return false;
      poolIn(S.wpool, catPick(REALM.wcat, rollTier()), weaponCap());
      return true;
    });
    produce('forge', forgeRate(), function () {
      if (S.apool.length >= armorCap()) return false;
      poolIn(S.apool, catPick(REALM.acat, rollTier()), armorCap());
      return true;
    });
    produce('factory', factoryRate(), function () {
      if (S.items.length >= itemCap()) return false;
      var ni = rollItem();
      if (!ni) return false;
      S.items.push(ni);
      return true;
    });
    // The Mine has no cap, so its bar was always honest and stays a plain timer.
    produce('mine', mineRate(), function () { S.carbon += L('mine'); return true; });
    // The Portal is a COOLDOWN, not a production line: full means Strike is
    // ready, and it already held there rather than cycling.
    if (S.prod.portal < portalRate()) S.prod.portal++;
    // The Crypt is the same shape: it prepares a guardian and waits to be
    // asked. It fills whether or not anyone is dead, so a loss late in a run
    // is not also a wait -- the Crypt has been standing ready.
    if (S.prod.crypt < cryptRate()) S.prod.crypt++;

    /*
     * THE BARRACKS FEEDS THE TOWER BY ITSELF.
     *
     * Playtest: "I can't click fast enough to knock them back or stop them
     * destroying my wall." That was a design fault, not a difficulty one. Every
     * replacement defender needed its own click, so at high waves the game was
     * bounded by clicking speed rather than by judgement -- and no tower defense
     * is fun when it is a clicking exercise.
     *
     * The user's own spec said this from the start: "The tower keeps deploying a
     * garrison from the barracks." Reinforcement is automatic now, paced by
     * Barracks level, and the player's clicks go where decisions actually live:
     * sorties, fortifies, resurrections and upgrades.
     *
     * It also makes the balance model honest -- it always assumed a full
     * garrison, which by hand was unachievable.
     */
    S.prod.reinforce++;
    if (S.prod.reinforce >= reinforceRate()) {
      S.prod.reinforce = 0;
      if (S.reserve.length && S.garrison.length < garrisonCap()) {
        S.garrison.push(equip(S.reserve.shift()));
      }
      /*
       * THE QUARTERMASTER. The cache's missing consumer.
       *
       * Armour DEGRADES on every breach (S.garrison[best].a--) and nothing ever
       * replaced it, so a wall wore down to bare while the cache watched. On a
       * realm that arrives fully equipped this was the whole problem: equip()
       * only fills EMPTY slots, every soldier already had kit, and so a cache of
       * 159 weapons and 114 armour had literally nobody to go to -- the Armory
       * sat capped-out and idle for the entire run, and issuing gear could never
       * drain it because there were more pieces than there were soldiers.
       *
       * So the wall is re-kitted from the cache as it wears: worn armour is
       * replaced, and a guardian carrying worse than the cache holds trades up.
       * One piece per pass, on the reinforcement tick, so it is a steady draw
       * rather than a lump -- and it only ever fires when the cache genuinely
       * holds something better than what is being worn.
       */
      var worst = -1, worstA = Infinity;
      for (var g2 = 0; g2 < S.garrison.length; g2++) {
        if (S.garrison[g2].a < worstA) { worstA = S.garrison[g2].a; worst = g2; }
      }
      if (worst >= 0) upgradeFrom(S.garrison[worst], 'a', 'an', S.apool);
      var worstW = -1, worstWl = Infinity;
      for (var g3 = 0; g3 < S.garrison.length; g3++) {
        if (S.garrison[g3].w < worstWl) { worstWl = S.garrison[g3].w; worstW = g3; }
      }
      if (worstW >= 0) upgradeFrom(S.garrison[worstW], 'w', 'wn', S.wpool);
    }

    // The Tower fires on the closest foe still short of the wall.
    S.nextAttack--;
    if (S.nextAttack <= 0 && S.foes.length && S.garrison.length) {
      S.nextAttack = 6;
      var target = S.foes[0];
      for (var i = 1; i < S.foes.length; i++) if (S.foes[i].pos < target.pos) target = S.foes[i];
      target.hp -= towerDamage();
      sfxVolley();
      if (target.hp <= 0) { kill(target); }
    }

    /*
     * One guardian steps through the Portal at a time. Four ticks apart is
     * enough for the one ahead to have started moving, so a sortie arrives as a
     * file of individuals rather than a single stacked marker.
     */
    if (S.emerging.length) {
      S.portalOut = (S.portalOut || 0) + 1;
      if (S.portalOut >= 4) {
        S.portalOut = 0;
        S.sortied.push(S.emerging.shift());
      }
    }

    // Sortied guardians meet the horde in the open -- no tower behind them.
    for (var s = S.sortied.length - 1; s >= 0; s--) {
      var u = S.sortied[s];
      var near = null, bestd = 999;
      for (var k = 0; k < S.foes.length; k++) {
        var d = Math.abs(S.foes[k].pos - u.pos);
        if (d < bestd) { bestd = d; near = S.foes[k]; }
      }
      if (!near) { u.pos = Math.max(u.pos - 0.4, 2); continue; }
      if (bestd < 4) {
        near.hp -= soldierDamage(u);
        // A sortied guardian's own weapon, heard only when it connects.
        if (bestd < 4) sfxPlay(u.w > 0 ? (gearSfx(u.wn) || UNARMED_SFX[0]) : UNARMED_SFX[0], 0.09);
        u.hp -= near.tough ? 2 : 1;
        if (near.hp <= 0) kill(near);
        if (u.hp <= 0) {
          S.sortied.splice(s, 1); S.dead++;
          log('A guardian falls in the open.', true);
          sfxPlay('death', 0.14);
        }
      } else {
        u.pos += (near.pos > u.pos) ? 0.5 : -0.5;
      }
    }

    // Advance, and resolve anything reaching the wall.
    for (var j = S.foes.length - 1; j >= 0; j--) {
      var f = S.foes[j];
      var wasBeyondPortal = f.pos > PORTAL_X;
      f.pos -= f.speed;
      // Crossing the Portal is the moment the Tower has a clear shot. Cosmetic
      // only, and deliberately edge-triggered: one tracer per attacker per run,
      // not one per frame while it is inside the line.
      if (wasBeyondPortal && f.pos <= PORTAL_X) tracer(f.pos);
      if (f.pos <= 0) {
        S.foes.splice(j, 1);
        S.hp -= f.tough ? 12 : 5;
        /*
         * ARMOR IS WHAT A GUARDIAN WALKS AWAY IN.
         *
         * A breach used to kill a defender outright. Now, if anyone on the wall
         * is armoured, the armour takes it instead -- the piece is destroyed,
         * the guardian lives, and the Crypt stays empty. Weapons decide how hard
         * you hit; armour decides whether you survive being hit, which is
         * exactly the split Realms already makes between weapon_id and armor_id.
         *
         * Better armour absorbs more of the wall damage too, so a good cache is
         * felt twice.
         */
        /*
         * Double Rewards behaves here exactly as it does on a realm location:
         * a damage shield that absorbs the hit and is consumed doing it.
         */
        if (S.shield > 0) {
          S.shield--;
          S.hp += (f.tough ? 12 : 5);   // fully absorbed
          log('The shield takes it. ' + escAttr(foeIdentity(f).name) + ' is thrown back.');
          sfxPlay('melee', 0.14);
        } else {
          // The best-armoured guardian takes it, and their armour degrades a
          // level rather than the guardian dying. Higher tiers absorb more.
          var best = -1, bestA = 0;
          for (var q = 0; q < S.garrison.length; q++) {
            if (S.garrison[q].a > bestA) { bestA = S.garrison[q].a; best = q; }
          }
          if (best >= 0) {
            S.garrison[best].a--;
            S.hp += Math.min(f.tough ? 12 : 5, 1 + bestA);   // better armour, more absorbed
            log(escAttr(foeIdentity(f).name) + ' breaks against the armour.');
            sfxPlay('melee', 0.14);
          } else if (S.garrison.length) {
            S.garrison.shift();
            S.dead++;
            log(escAttr(foeIdentity(f).name) + ' breaches the wall. A guardian falls.', true);
            sfxPlay('death', 0.16);
          }
        }
        /*
         * THE LAST STAND. It fires when the wall FALLS, in place of the run
         * ending -- once, and only once.
         *
         * This is the whole point of it: a straggler touching the wall is not a
         * last stand, it is a scratch. The moment worth spending a nuke on is
         * the moment you were about to lose, so it stands exactly where end()
         * would otherwise be called and takes its place.
         *
         * Anything still off screen is untouched, so it buys you the wall back
         * and a clear field, not the wave -- the horde beyond the edge is still
         * walking, and the second time the wall falls the run is over.
         *
         * Deterministic, which matters because it changes the simulation rather
         * than just decorating it: no rand(), and the trigger is a wall-health
         * check, so a server replaying the same inputs fires it on the same tick.
         */
        if (S.hp <= 0) {
          // Who actually broke it. The horde is other stakers, so this is a
          // real person and the defeat post names them.
          var who = foeIdentity(f);
          S.breacher = who && who.name ? who.name : '';
          S.breacherId = who && who.did ? who.did : '';
          if (S.nuked) return end();
          S.nuked = true;
          lastStand();
          return;
        }
      }
    }

    if (!S.foes.length) {
      // The respite shrinks as the siege wears on. A fixed gap meant a strong
      // realm always had time to fully restock, which is the other half of why
      // it stopped being a fight.
      if (S.betweenWaves <= 0) {
        S.betweenWaves = Math.max(14, 45 - S.wave);
        // A LITERAL dash, not an entity: this is textContent, which does not
        // decode HTML -- "&mdash;" was being displayed verbatim in the HUD.
        // The log() line below can keep its entity, because log writes innerHTML.
        el.status.textContent = 'Wave held — regroup';
      }
      S.betweenWaves--;
      if (S.betweenWaves <= 0) startWave();
    }
    render();
  }

  function kill(f) {
    var i = S.foes.indexOf(f);
    if (i >= 0) S.foes.splice(i, 1);
    S.carbon += f.tough ? 4 : 1;   // tighter than it was; the economy was flooding
    sfxPlay('kill', 0.09);
  }

  function render() {
    // Fortify is otherwise invisible -- the wall glows and the HUD says so while
    // it is up, or the player has no way to know the item did anything.
    document.getElementById('rg-wall').className = (S.boostFor > 0 || S.shield > 0) ? 'rg-fortified' : '';
    el.wave.textContent = S.wave;
    el.hp.textContent = Math.max(0, S.hp);
    el.carbon.textContent = S.carbon;
    el.reserve.textContent = S.reserve.length;
    el.weapons.textContent = S.wpool.length;
    el.armor.textContent = S.apool.length;
    el.dead.textContent = S.dead;
    el.garrison.textContent = S.garrison.length;
    el.armed.textContent = armedCount();
    el.armored.textContent = armoredCount();
    el.items.textContent = S.items.length;
    // Counts those still stepping through, or the number dips as they queue.
    el.sortied.textContent = S.sortied.length + S.emerging.length;
    el['garrison-cap'].textContent = garrisonCap();
    el['mine-rate'].textContent = '+' + L('mine') + ' per ' + (mineRate() / 10).toFixed(1) + 's';
    ['tower','barracks','armory','crypt','portal','factory','mine'].forEach(function (k) {
      el['lvl-' + k].textContent = S.lvl[k];
    });
    /*
     * A stalled bar says WHY. A bar sitting at full is only useful if the
     * caption underneath turns into the reason -- otherwise it reads as a
     * frozen game rather than a full cache, and the fix (the Upgrade button
     * directly below it) is not obvious.
     */
    function paintBar(key, cur, rate, blocked, idleText, blockedText) {
      el['bar-' + key].style.width = Math.round(Math.min(1, cur / rate) * 100) + '%';
      el['bar-' + key].className = blocked ? 'rg-bar-full' : '';
      var cap = el['cap-' + key];
      if (cap) cap.textContent = blocked ? blockedText : idleText;
    }
    /*
     * "Upgrade for room" ONLY when an upgrade would actually make room.
     *
     * A realm is a head start, and a big one arrives holding far more gear than
     * the in-game caps: 159 weapons and 114 armour against caps of 76 and 14.
     * Clearing those by upgrading would take 28 and 199 Armory levels. The bar
     * was right that nothing was being forged, and the caption was sending the
     * player to spend CARBON on the one thing that could not fix it.
     *
     * What DOES draw the cache down is issuing gear -- Deploy and Strike equip
     * guardians out of the pool -- so an over-full cache says that instead.
     * Held is still held either way; only the advice changes.
     */
    function heldText(len, capNow, capNext, what, drain) {
      return capNext > len
        ? what + ' full — upgrade for room'
        : what + ' full — ' + drain;
    }
    /*
     * A third state, and for a well-supplied realm it is the true one:
     * the cache is full of gear WORSE than what the wall is already wearing.
     *
     * Measured on the realm that reported this: 159 weapons topping out at
     * level 6 against guardians carrying 5 to 10, and every one of 93 living
     * soldiers already equipped. No amount of issuing drains that, because
     * there are more pieces than there are soldiers and none of them is an
     * upgrade. Saying "issue them" there sends the player after a fix that does
     * not exist -- the same mistake as "upgrade for room", one layer down.
     *
     * Armour still drains, because armour WEARS: a breach degrades it a level,
     * and once a guardian drops below what the cache holds the quartermaster
     * re-kits them. So the honest caption depends on whether the cache holds
     * anything better than what is actually being worn right now.
     */
    function gearHeld(what, len, capNext, pool, key) {
      if (capNext > len) return what + ' full — upgrade for room';
      var worn = Infinity;
      for (var i = 0; i < S.garrison.length; i++) {
        if (S.garrison[i][key] < worn) worn = S.garrison[i][key];
      }
      var best = 0;
      for (var j = 0; j < pool.length; j++) if (pool[j].lvl > best) best = pool[j].lvl;
      if (S.garrison.length && best > worn) return what + ' full — re-kitting the wall from it';
      return what + ' surplus — nothing in it beats what your guardians carry';
    }
    var aLvl = L('armory'), bLvl = L('barracks'), fLvl = L('factory');
    paintBar('barracks', S.prod.barracks, barracksRate(),
      S.reserve.length >= reserveCap(), 'training next guardian',
      heldText(S.reserve.length, reserveCap(), reserveCap(bLvl + 1), 'barracks', 'deploy some to the Tower'));
    paintBar('armory', S.prod.armory, armoryRate(),
      S.wpool.length >= weaponCap(), 'forging next weapon',
      gearHeld('weapon cache', S.wpool.length, weaponCap(aLvl + 1), S.wpool, 'w'));
    paintBar('forge', S.prod.forge, forgeRate(),
      S.apool.length >= armorCap(), 'forging next armour',
      gearHeld('armour cache', S.apool.length, armorCap(aLvl + 1), S.apool, 'a'));
    paintBar('factory', S.prod.factory, factoryRate(),
      S.items.length >= itemCap(), 'building next item',
      heldText(S.items.length, itemCap(), itemCap(fLvl + 1), 'shelf', 'spend one to restart it'));
    /*
     * THE MINE'S BAR MEASURES THE NEXT UPGRADE, NOT THE NEXT COIN.
     *
     * It used to show S.prod.mine against mineRate(), which at a developed Mine
     * is a payout every 0.6s -- a bar that sweeps twice a second and tells you
     * nothing you can act on. It was the clearest case of animation standing in
     * for information: CARBON was visibly arriving, but "am I close to
     * affording anything?" was the actual question and nothing answered it.
     *
     * Now it fills toward what the Mine's own next level costs, so full means
     * "you can buy this" -- and it moves DOWN when you spend on another
     * location, which is honest: that really did set the Mine back.
     *
     * The payout rate has not changed and is still stated in the line above the
     * bar ("CARBON · +19 per 0.6s"), so nothing was lost by taking it off here.
     */
    var mineCost = upgradeCost('mine');
    paintBar('mine', Math.min(S.carbon, mineCost), mineCost, false,
      S.carbon >= mineCost
        ? 'ready to upgrade the Mine'
        : (mineCost - S.carbon) + ' CARBON to the next Mine level',
      '');
    paintBar('portal', S.prod.portal, portalRate(), false, 'strike ready when full', '');
    /*
     * The Crypt holds unlimited dead, so it is never blocked by capacity. The
     * one thing that stops it is having nobody to bring back -- which is good
     * news, and the caption says it that way rather than as a fault.
     */
    paintBar('crypt', S.prod.crypt, cryptRate(), S.prod.crypt >= cryptRate() && S.dead === 0,
      S.prod.crypt >= cryptRate()
        ? 'rite ready — raises all ' + S.dead
        : (S.dead > 0 ? 'preparing the rite — ' + S.dead + ' waiting' : 'preparing the rite'),
      'rite ready — no one to raise');

    /*
     * REUSE THE NODES. Do not rebuild innerHTML.
     *
     * This ran ten times a second and threw away every <img> each time. The
     * browser re-decoded them on every frame, and in the gap before each one
     * painted you saw the div's own red background -- which reads exactly as
     * "placeholder icons, always flashing red". The avatars were fine; the DOM
     * was being destroyed under them.
     *
     * Now each foe owns one element for its whole life: created once, moved by
     * updating `left`, removed when it dies. No re-decode, no flicker, and far
     * less work per tick.
     */
    var seen = {};
    for (var i = 0; i < S.foes.length; i++) {
      var f = S.foes[i];
      if (f.pos > 100) continue;
      seen[f.id] = 1;
      var node = foeNodes[f.id];
      if (!node) {
        var who = foeIdentity(f);
        node = document.createElement('div');
        node.className = 'rg-foe' + (f.tough ? ' rg-tough' : '');
        node.title = who.name;
        if (who.img) {
          var fimg = document.createElement('img');
          fimg.alt = '';
          // Pre-verified, so this should never fire -- but hide rather than
          // show a placeholder if it somehow does.
          fimg.onerror = function () { this.style.display = 'none'; };
          fimg.src = who.img;
          node.appendChild(fimg);
        }
        node.appendChild(document.createElement('i'));
        foeNodes[f.id] = node;
        foesEl.appendChild(node);
      }
      node.style.left = f.pos + '%';
      // Percent of the circle, not pixels: the circle's size is a CSS variable
      // that doubles on a browser, and a hard-coded 20px would not follow it.
      node.lastChild.style.width = Math.max(0, Math.round(f.hp / f.max * 100)) + '%';
    }
    for (var id in foeNodes) {
      if (!seen[id]) { foesEl.removeChild(foeNodes[id]); delete foeNodes[id]; }
    }

    // Your guardians wear their own NFT art, with the weapon they carry badged
    // on top -- so the field reads as your skulls against their faces, and an
    // armed guardian is visibly armed.
    // Same node reuse as the horde: rebuilding these every tick re-decoded the NFT
    // art and made your own guardians strobe too.
    var useen = {};
    for (var u = 0; u < S.sortied.length; u++) {
      var un2 = S.sortied[u];
      useen[un2.slot] = 1;
      var unode = unitNodes[un2.slot];
      if (!unode) {
        var art2 = UNITS.length ? UNITS[un2.slot % UNITS.length] : null;
        unode = document.createElement('div');
        unode.className = 'rg-unit' + (un2.w > 0 ? ' rg-armed' : '') + (un2.a > 0 ? ' rg-prot' : '');
        if (art2) {
          unode.title = art2.name;
          var uimg = document.createElement('img');
          uimg.alt = '';
          uimg.onerror = function () { this.style.display = 'none'; };
          uimg.src = art2.img;
          unode.appendChild(uimg);
        }
        // THEIR weapon and THEIR armour -- not the cache's best applied to
        // everyone, which is why the initial raiders were wearing gear they do
        // not carry.
        /*
         * `this.parentNode`, NOT the badge variable. These are declared with
         * var inside the render loop, so every handler closed over the SAME
         * binding -- one icon failing to load hid whichever guardian's badge
         * happened to be created last, not its own.
         */
        var wsrc = un2.w > 0 ? gearIcon(un2.wn) : '';
        if (wsrc) {
          var wb = document.createElement('b');
          var wi = document.createElement('img');
          wi.alt = ''; wi.title = un2.wn;
          wi.onerror = function () { if (this.parentNode) this.parentNode.style.display = 'none'; };
          wi.src = wsrc;
          wb.appendChild(wi); unode.appendChild(wb);
        }
        var asrc = un2.a > 0 ? gearIcon(un2.an) : '';
        if (asrc) {
          var ab = document.createElement('u');
          var ai = document.createElement('img');
          ai.alt = ''; ai.title = un2.an;
          ai.onerror = function () { if (this.parentNode) this.parentNode.style.display = 'none'; };
          ai.src = asrc;
          ab.appendChild(ai); unode.appendChild(ab);
        }
        /*
         * A life bar, the same as the horde's. Guardians in the open already
         * took damage and died -- nothing showed it, so a Strike looked like
         * units vanishing at random. It is also where armour becomes legible:
         * unitHp() is (weapon ? 6 : 4) + (armour ? 2 + level : 0), so a
         * well-armoured guardian visibly outlasts a bare one.
         *
         * Held on the node rather than found with lastChild, because the badges
         * above are conditional -- lastChild is the weapon, the armour or the
         * bar depending on what this guardian happens to be carrying.
         */
        var ubar = document.createElement('i');
        unode.appendChild(ubar);
        unode._bar = ubar;
        unitNodes[un2.slot] = unode;
        sortieEl.appendChild(unode);
      }
      unode.style.left = un2.pos + '%';
      if (unode._bar) {
        unode._bar.style.width = Math.max(0, Math.round(un2.hp / (un2.max || un2.hp || 1) * 100)) + '%';
      }
    }
    for (var uid in unitNodes) {
      if (!useen[uid]) { sortieEl.removeChild(unitNodes[uid]); delete unitNodes[uid]; }
    }


    paintPause();
    paintRetreat();
    paintShelf();

    // Locked mid-siege: swapping baselines would rebuild the state under the
    // wave already walking at you.
    if (scratchBox) {
      /*
       * The toggle lives and dies WITH the Begin button. It is a choice about
       * the run you are about to start, so once a siege is under way it is not
       * merely unusable, it is irrelevant -- and hiding the button while
       * leaving its checkbox behind left an orphaned control floating in the
       * Mine panel. Mirroring beginBtn.hidden rather than re-deriving the
       * condition means the two cannot drift apart again, including when Begin
       * comes back as "Hold again" after a loss.
       */
      if (scratchWrap) scratchWrap.hidden = beginBtn.hidden;
      scratchBox.disabled = S.running;   // belt and braces if it is ever shown
    }

    document.querySelectorAll('.rg-act').forEach(function (b) {
      var a = b.dataset.act;
      if (a === 'deploy')       b.disabled = !(S.reserve.length && S.garrison.length < garrisonCap());
      else if (a === 'raise')   b.disabled = !(S.dead > 0 && S.prod.crypt >= cryptRate());
      else if (a === 'sortie')  b.disabled = !(S.reserve.length && S.prod.portal >= portalRate() && S.running);
      // No 'fortify' branch any more -- the items moved to their own shelf,
      // where each has a button of its own and paintShelf() maintains it.
      else {
        var k = a.slice(3);
        b.disabled = S.carbon < upgradeCost(k);
        b.textContent = 'Upgrade (' + upgradeCost(k) + ')';
      }
      // Greyed as well as inert while paused: act() already refuses, but a
      // button that looks live and does nothing reads as a broken game.
      if (S.paused) b.disabled = true;
    });
    if (S.paused) el.status.textContent = 'Paused — the horde waits';
  }

  function act(a) {
    /*
     * Nothing may be spent while paused. Pause is for putting the game DOWN,
     * not for an untimed planning window -- deciding under pressure is most of
     * the game, and a pause you can act inside would remove it. It also keeps
     * the action log honest: every entry lands on a tick the clock actually ran.
     */
    if (!S.running || S.over || S.paused) return;
    actionLog.push([S.tick, a]);   // what a server would replay
    /*
     * Deploy and Raise fill in ONE click rather than one guardian per click.
     * Clicking twelve times to refill a tower is not a decision, it is a
     * dexterity tax -- and it was what made the game unwinnable by hand at
     * higher waves. The choice worth making is "reinforce now or spend the
     * CARBON on an upgrade", and that survives batching intact.
     */
    if (a === 'deploy' && S.reserve.length && S.garrison.length < garrisonCap()) {
      var room = garrisonCap() - S.garrison.length, sent = 0;
      while (room-- > 0 && S.reserve.length) {
        S.garrison.push(equip(S.reserve.shift())); sent++;
      }
      log(sent + ' to the Tower.');
    } else if (a === 'raise' && S.dead > 0 && S.prod.crypt >= cryptRate()) {
      /*
       * The whole Crypt, in one rite, free. Deliberately NOT capped to the
       * reserve: reserveCap governs how many the Barracks will STOCKPILE, and
       * turning your own dead away at the door because the barracks is busy
       * would be a bewildering way to lose a run. The Barracks simply pauses
       * until the reserve falls back under its cap, which is self-correcting.
       */
      var raised = S.dead;
      S.prod.crypt = 0;
      // They come back without their kit -- it stayed where they fell.
      while (S.dead > 0) { S.dead--; S.reserve.push({ w:0, wn:'', a:0, an:'' }); }
      log('The Crypt gives ' + raised + ' back.' + (raised > 1 ? ' The wall fills again.' : ''));
    // Called STRIKE in the UI. The internal action id, the state and the CSS
    // ids stay 'sortie' -- renaming those would touch the field markup, the
    // stylesheet and the replay action log for a wording change, and the log
    // is the thing a server has to keep reading. One name, two spellings.
    } else if (a === 'sortie' && S.reserve.length && S.prod.portal >= portalRate()) {
      // Meet them in the open: they die before reaching the wall, but your
      // guardians fight with no tower behind them. The whole risk/reward beat.
      S.prod.portal = 0;
      /*
       * They QUEUE at the Portal rather than appearing as a stack.
       *
       * Spawning the whole sortie at PORTAL_X put every guardian on the same
       * pixel, so a group of five read as one. They now step through one at a
       * time (see the portal release in step()), and because each moves off
       * toward the nearest foe as soon as it lands, the group spreads itself
       * out -- which is what the old arbitrary offsets were faking.
       */
      var n = Math.min(sortieSize(), S.reserve.length);
      for (var i = 0; i < n; i++) {
        // They take their OWN kit, topped up from the cache if short. A sortie
        // has no tower behind it, so armour is felt most sharply here.
        var u = equip(S.reserve.shift());
        // slot picks which enlisted NFT this guardian is, and stays fixed for
        // its life so the face on the field doesn't change between renders.
        S.emerging.push({ pos:PORTAL_X, w:u.w, wn:u.wn, a:u.a, an:u.an, hp:unitHp(u), max:unitHp(u), slot:unitSeq++ });
      }
      log(n + ' guardian' + (n > 1 ? 's ride' : ' rides') + ' out through the Portal.');
    } else if (a.indexOf('item-') === 0) {
      /*
       * Spend THAT item, not whatever is on top. The inventory stays a single
       * array so the Factory's total cap keeps its meaning -- a shelf full of
       * Free Levels really does block the next shield -- but the player picks
       * which one leaves it.
       */
      var want = parseInt(a.slice(5), 10);
      for (var ii = 0; ii < S.items.length; ii++) {
        if (S.items[ii].id === want) { useItem(S.items.splice(ii, 1)[0]); break; }
      }
    } else if (a.indexOf('up-') === 0) {
      var k = a.slice(3);
      if (S.carbon >= upgradeCost(k)) { S.carbon -= upgradeCost(k); S.lvl[k]++; S.bought[k]++; log(k + ' raised to ' + S.lvl[k] + '.'); }
    }
    render();
  }

  function end() {
    S.over = true; S.running = false;
    S.paused = false;        // or the next run's board opens wearing the wash
    clearInterval(S.timer);
    musicStop();
    tracersClear();
    paintPause();
    /*
     * The only moment a score is written. The wave is claimed by the client and
     * the elapsed time is measured by the server against the started_at it
     * stamped at Begin, so neither can be forged without the other -- see
     * guardians-lib.php. Recording also clears the saved run, so a fallen siege
     * cannot be resumed.
     */
    SAVED = null;
    el.status.textContent = 'The wall is breached';
    log('The realm falls at wave ' + S.wave + '. Guardians lost: ' + S.dead + '.', true);
    beginBtn.textContent = 'Hold again';
    beginBtn.hidden = false;
    showDefeat();
    /*
     * The only moment a score is written. The wave is claimed by the client and
     * the elapsed time is measured by the server against the started_at it
     * stamped at Begin, so neither can be forged without the other -- see
     * guardians-lib.php. Recording also clears the saved run, so a fallen siege
     * cannot be resumed.
     *
     * The modal is up BEFORE this resolves, so a slow round trip never delays
     * the send-off; whether it counted is filled in when the answer arrives.
     */
    post('defeat', { wave: S.wave, lost: S.dead,
                     breacher: S.breacher || '', breacher_id: S.breacherId || '' }).then(function (res) {
      var el2 = document.getElementById('rg-defeat-scored');
      if (!el2) return;
      if (res && res.scored) el2.textContent = 'Recorded on this month’s board.';
      else if (res)          el2.textContent = 'Not recorded — this run had no server-side start.';
      else                   el2.textContent = '';
    });
  }

  /*
   * WAVES HELD is the headline, not the wave reached. The wave you started on
   * was handed to you by your realm; the distance past it is the part you did,
   * which is also what the board ranks on -- the two should not tell different
   * stories about the same run.
   */
  function showDefeat() {
    var d = document.getElementById('rg-defeat');
    if (!d) return;
    var held = Math.max(0, S.wave - REALM.start);
    function put(id, v) { var e = document.getElementById(id); if (e) e.textContent = v; }
    put('rg-defeat-held',  held);
    put('rg-defeat-start', REALM.start);
    put('rg-defeat-wave',  S.wave);
    put('rg-defeat-lost',  S.dead);
    // S.tick does not advance while paused, so this is time actually played
    // rather than time the tab was open.
    var secs = Math.round(S.tick / 10), mins = Math.floor(secs / 60);
    put('rg-defeat-time', mins > 0 ? mins + 'm ' + (secs % 60) + 's' : secs + 's');
    put('rg-defeat-scored', '');
    d.hidden = false;
    var ok = document.getElementById('rg-defeat-ok');
    if (ok) ok.focus();
  }

  document.addEventListener('click', function (e) {
    // .rg-item carries data-act too, so the shelf goes through the same path
    // and lands in the same replay log as every other action.
    var b = e.target.closest('.rg-act, .rg-item');
    if (b && !b.disabled) act(b.dataset.act);
  });

  var soundBtn = document.getElementById('rg-sound');
  /*
   * Effects OFF by default on a phone.
   *
   * Reported: they slow the game down on mobile. Decoding and mixing several
   * overlapping clips per volley is real work on a handset, and a siege that
   * stutters is worse than a silent one. Music is untouched -- it is a single
   * stream and costs almost nothing.
   *
   * An explicit choice always wins: this only applies when nothing is stored, so
   * turning them on once on a phone sticks.
   */
  try {
    var stored = localStorage.getItem('rg-sound');
    if (stored === 'off') sfxOn = false;
    else if (stored === null && window.matchMedia &&
             window.matchMedia('(max-width:560px)').matches) sfxOn = false;
  } catch (e) {}
  function paintSound() {
    soundBtn.innerHTML = sfxOn ? '&#128266;' : '&#128263;';
    soundBtn.title = sfxOn ? 'Mute' : 'Unmute';
    soundBtn.setAttribute('aria-pressed', sfxOn ? 'true' : 'false');
  }
  soundBtn.addEventListener('click', function () {
    sfxOn = !sfxOn;
    try { localStorage.setItem('rg-sound', sfxOn ? 'on' : 'off'); } catch (e) {}
    paintSound();
  });
  paintSound();

  /* ---- PAUSE ------------------------------------------------------------
   * A run reaches an hour and NOTHING is saved. Losing that to a phone call is
   * the worst failure this game has, and it is not a failure of play -- there
   * is nothing to learn from it, only an hour gone.
   *
   * Auto-pauses when the tab is hidden, which is the interruption that actually
   * happens: a call, an app switch, a locked screen. That case has to be handled
   * for a second reason too -- a backgrounded tab has its timers throttled hard,
   * so without this the game does not keep running so much as lurch, and the
   * player returns to a wall that fell while the page was frozen.
   *
   * Resuming is always manual. Coming back to a siege already in progress, with
   * a wave part-way across the field, is exactly the ambush being avoided.
   */
  /* ---- The Factory shelf -------------------------------------------------
   * Counts are DERIVED from the inventory each paint rather than kept as a
   * second tally that could drift out of step with it. Seven buttons is a
   * cheap loop and there is then exactly one source of truth for what you hold.
   *
   * Every button is always present, including the ones you have none of: the
   * shelf is how the items are learned, and a menu that rearranges itself
   * cannot be learned by position.
   */
  // Mirrors useItem()'s own table -- the shelf has to know a Volley's magnitude
  // to say whether pressing it would improve on what is already running.
  var VOLLEY_PCT = { 2:0.25, 4:0.50, 5:0.75, 7:1.00 };
  var shieldEl = document.getElementById('rg-wall-shields');
  var shelfEl = document.getElementById('rg-shelf');
  var shelfCountEl = document.getElementById('rg-shelf-count');
  var itemBtns = shelfEl ? shelfEl.querySelectorAll('.rg-item') : [];
  function paintShelf() {
    if (!shelfEl) return;
    // Always on screen, including before the first siege and including the
    // items you hold none of. It is the only place the seven are explained, and
    // a player who has just arrived should be able to read what they are before
    // needing one -- which was the whole complaint that produced this shelf.
    var held = {};
    for (var i = 0; i < S.items.length; i++) held[S.items[i].id] = (held[S.items[i].id] || 0) + 1;
    if (shelfCountEl) shelfCountEl.textContent = S.items.length + ' / ' + itemCap();
    for (var b = 0; b < itemBtns.length; b++) {
      var btn = itemBtns[b];
      var id = parseInt(btn.dataset.act.slice(5), 10);
      var n = held[id] || 0;
      var badge = btn.querySelector('b[data-count]');
      if (badge) badge.textContent = '(' + n + ')';
      btn.disabled = n === 0 || !S.running || S.over || S.paused;
      btn.classList.toggle('rg-item-have', n > 0);
      /*
       * The countdown, and why it exists: volleys do NOT stack. A second one
       * overwrites both the magnitude and the timer, so firing one while
       * another is running throws the remainder away. The bar drains across all
       * four, because any of them would do it.
       *
       * A press that would DOWNGRADE the running boost is marked separately --
       * +25% over a live +100% is the one that actually costs you something,
       * where +100% over a live +25% is an upgrade and perfectly fine.
       */
      var bar = btn.querySelector('.rg-item-boost');
      if (bar) {
        var live = S.boostFor > 0 && S.boostMax > 0;
        bar.style.width = live ? Math.round(S.boostFor / S.boostMax * 100) + '%' : '0';
        btn.classList.toggle('rg-item-worse', !!live && (VOLLEY_PCT[id] || 0) <= S.boost);
      }
    }
    // Shields are invisible otherwise -- the wall glows whether one is up or
    // five are. They stack and never expire, so the count is the whole point.
    if (shieldEl) {
      shieldEl.textContent = S.shield > 0 ? '\u26E8 ' + S.shield : '';
      shieldEl.hidden = !(S.shield > 0);
    }
  }

  var pauseBtn = document.getElementById('rg-pause');
  function paintPause() {
    if (!pauseBtn) return;
    pauseBtn.hidden = !S.running || S.over;
    pauseBtn.innerHTML = S.paused ? '&#9654;&#65039;' : '&#9208;&#65039;';
    pauseBtn.title = S.paused ? 'Resume the siege' : 'Pause the siege';
    pauseBtn.setAttribute('aria-pressed', S.paused ? 'true' : 'false');
    game.classList.toggle('rg-paused', !!S.paused);
  }
  function setPaused(p, quiet) {
    if (!S.running || S.over || S.paused === p) return;
    S.paused = p;
    if (p) { musicStop(); if (!quiet) log('Siege paused. The horde waits.'); }
    else   { if (musicOn) musicStart(); if (!quiet) log('Siege resumed.'); }
    paintPause();
    render();
  }

  /* ---- THE LAST STAND ---------------------------------------------------
   * One nuke, the first time anything reaches the wall.
   *
   * The siege is held while the modal is up, and quietly -- a player reading
   * why their wall just detonated should not be losing ground for it, and the
   * ordinary "Siege paused" line would be noise on top of the announcement.
   * Dismissing resumes.
   */
  /*
   * Looked up and wired HERE, in one place. Assigning these earlier and
   * declaring them here would have `var nukeEl = null` run AFTER the
   * assignment and quietly wipe it -- var initialisers execute where they are
   * written, however far the declaration hoists.
   */
  var nukeEl = document.getElementById('rg-nuke');
  var nukeOk = document.getElementById('rg-nuke-ok');
  if (nukeOk) {
    nukeOk.addEventListener('click', function () {
      nukeEl.hidden = true;
      setPaused(false, true);
    });
  }
  /*
   * A QUARTER OF THE WALL BACK. The sweep alone would not be a reprieve: the
   * wall is at zero by the time this runs, so the very next attacker to arrive
   * would end the run anyway and the nuke would have bought a few seconds.
   * A quarter is enough to fight on and nowhere near enough to be safe --
   * and the horde that was off screen is still coming.
   */
  var LAST_STAND_HP = 25;
  function lastStand() {
    // Only what is ON the field. The horde still off screen keeps walking, so
    // this buys the wall back and a clear field, not the wave.
    var caught = 0;
    for (var i = S.foes.length - 1; i >= 0; i--) {
      if (S.foes[i].pos <= 100) { S.foes.splice(i, 1); caught++; }
    }
    S.hp = LAST_STAND_HP;
    // No CARBON: this is a desperation weapon, not a payday.
    sfxPlay('demolition', 0.34);
    log('THE WALL FALLS &mdash; and the last stand detonates. ' + caught +
        ' swept from the field, ' + LAST_STAND_HP + ' wall shored up.', true);
    if (nukeEl) {
      var n = document.getElementById('rg-nuke-count');
      if (n) n.textContent = caught;
      nukeEl.hidden = false;
      setPaused(true, true);
      if (nukeOk) nukeOk.focus();
    }
    render();
  }
  if (pauseBtn) pauseBtn.addEventListener('click', function () { setPaused(!S.paused); });

  /* ---- RETREAT ----------------------------------------------------------
   * Persistence created the need for this: before it, closing the tab was how
   * you abandoned a siege. Now a run follows you back, so there has to be a
   * deliberate way out of one.
   *
   * It does NOT start a new siege directly -- it puts the board back to its
   * pre-run state, which is where the "start from scratch" toggle lives. That
   * is one extra click and it makes "restart from scratch" fall out of the
   * existing controls rather than needing a second variant of Begin.
   *
   * Two clicks, because it throws away up to an hour, and the arm expires so a
   * stray first click cannot sit waiting to be completed by an unrelated one.
   * Not scored: abandoning is not defeat, and paying out for it would make
   * quitting a losing siege the correct play.
   */
  var retreatBtn = document.getElementById('rg-retreat');
  var confirmEl  = document.getElementById('rg-confirm');
  var confirmYes = document.getElementById('rg-confirm-yes');
  var confirmNo  = document.getElementById('rg-confirm-no');
  function paintRetreat() {
    if (retreatBtn) retreatBtn.hidden = !S.running || S.over;
  }
  /*
   * A real dialog, not a two-click arm. This throws away up to an hour, and a
   * quiet second click on an icon is not a confirmation anyone would remember
   * making. The siege is held while it is up -- quietly, and only if it was not
   * already paused, so cancelling puts the player back exactly as they were
   * rather than silently un-pausing a siege they had paused themselves.
   */
  var confirmPaused = false;
  function askRetreat() {
    if (!confirmEl || !S.running || S.over) return;
    confirmPaused = !S.paused;
    if (confirmPaused) setPaused(true, true);
    confirmEl.hidden = false;
    if (confirmNo) confirmNo.focus();
  }
  function closeRetreat(resume) {
    if (confirmEl) confirmEl.hidden = true;
    if (resume && confirmPaused) setPaused(false, true);
    confirmPaused = false;
  }
  // Dismissing the send-off just closes it: Begin is already back on the board
  // underneath, so this does not need to start anything itself.
  var defeatOk = document.getElementById('rg-defeat-ok');
  if (defeatOk) {
    defeatOk.addEventListener('click', function () {
      var d = document.getElementById('rg-defeat');
      if (d) d.hidden = true;
      if (beginBtn) beginBtn.focus();
    });
  }

  if (retreatBtn) retreatBtn.addEventListener('click', askRetreat);
  if (confirmNo) confirmNo.addEventListener('click', function () { closeRetreat(true); });
  if (confirmYes) {
    confirmYes.addEventListener('click', function () {
      closeRetreat(false);
      clearInterval(S.timer);
      post('abandon', {});
      SAVED = null;
      musicStop();
      tracersClear();
      if (nukeEl) nukeEl.hidden = true;
      // Back to the pre-run board, which is where the "start from scratch"
      // toggle lives -- so restarting from scratch falls out of the controls
      // that already exist rather than needing a second variant of Begin.
      applyBaseline();
      beginBtn.hidden = false;
      beginBtn.textContent = 'Begin the Siege';
      if (resumeBtn) resumeBtn.hidden = true;
      paintPause();
      paintRetreat();
      log('The retreat is sounded. Nothing was scored.');
    });
  }
  document.addEventListener('visibilitychange', function () {
    if (document.hidden) setPaused(true);
  });

  /*
   * THE SCRATCH TOGGLE. Swaps which baseline REALM points at and rebuilds the
   * board, so the choice is visible before you commit to it -- the roster, the
   * levels and the starting wave all change under you as you tick it.
   *
   * Locked while a siege is running: switching baselines mid-run would rebuild
   * the state under the wave that is already walking at you. Remembered, like
   * the sound choice, because someone who wants the hard version usually wants
   * it again next time.
   */
  var scratchBox = document.getElementById('rg-scratch');
  var scratchWrap = document.getElementById('rg-scratch-wrap');
  var blurbRealm = document.getElementById('rg-blurb-realm');
  var blurbScratch = document.getElementById('rg-blurb-scratch');
  function applyBaseline() {
    REALM = (scratchOn && HAS_REALM) ? SCRATCH : SNAPSHOT;
    if (blurbRealm)   blurbRealm.hidden   = scratchOn;
    if (blurbScratch) blurbScratch.hidden = !scratchOn;
    reset();
    render();
  }
  if (scratchBox) {
    try { scratchOn = localStorage.getItem('rg-scratch') === 'on'; } catch (e) {}
    scratchBox.checked = scratchOn;
    scratchBox.addEventListener('change', function () {
      if (S.running) { scratchBox.checked = scratchOn; return; }
      scratchOn = scratchBox.checked;
      try { localStorage.setItem('rg-scratch', scratchOn ? 'on' : 'off'); } catch (e) {}
      applyBaseline();
    });
  }

  var musicBtn = document.getElementById('rg-music');
  var trackSel = document.getElementById('rg-track');
  var volSlider = document.getElementById('rg-vol');
  if (musicBtn) {
    try {
      if (localStorage.getItem('rg-music') === 'off') musicOn = false;
      var sv = parseInt(localStorage.getItem('rg-musicvol'), 10);
      if (!isNaN(sv)) { musicVol = Math.max(0, Math.min(1, sv / 100)); volSlider.value = sv; }
    } catch (e) {}
    function paintMusic() {
      musicBtn.innerHTML = musicOn ? '&#127925;' : '&#128263;';
      musicBtn.title = musicOn ? 'Mute music' : 'Unmute music';
      musicBtn.setAttribute('aria-pressed', musicOn ? 'true' : 'false');
    }
    musicBtn.addEventListener('click', function () {
      musicOn = !musicOn;
      try { localStorage.setItem('rg-music', musicOn ? 'on' : 'off'); } catch (e) {}
      if (musicOn) { if (S.running) musicStart(); } else { musicStop(); }
      paintMusic();
    });
    trackSel.addEventListener('change', function () { musicLoad(parseInt(this.value, 10) || 0); });
    volSlider.addEventListener('input', function () {
      musicVol = Math.max(0, Math.min(1, parseInt(this.value, 10) / 100));
      if (music) music.volume = musicVol;
      try { localStorage.setItem('rg-musicvol', String(this.value)); } catch (e) {}
    });
    paintMusic();
  }

  /* ---- PERSISTENCE ------------------------------------------------------
   * A siege runs half an hour and the long ones an hour. Losing that to a
   * closed tab is the same failure the pause button exists to prevent, one
   * step further out, so the run is snapshotted and can be picked up again.
   *
   * The server stores what it is told and never scores a snapshot -- the trust
   * boundary and the wall-clock bound on submitted scores are written out in
   * guardians-lib.php. Nothing here is load-bearing for the leaderboard.
   *
   * Every call is fire-and-forget with a swallowed rejection: a save that fails
   * must never interrupt a siege, and a player with a flaky connection should
   * lose the resume, not the game.
   */
  var SAVE_EVERY = 100;                 // ticks -- 10s, cheap next to a run
  function post(action, extra) {
    var body = 'action=' + encodeURIComponent(action);
    for (var k in extra) body += '&' + k + '=' + encodeURIComponent(extra[k]);
    return fetch('ajax/guardians-save.php', {
      method: 'POST', credentials: 'same-origin',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body
    }).then(function (r) { return r.json(); }).catch(function () { return null; });
  }
  /*
   * S.timer is an interval id, not state -- serialising it would restore a
   * number that means nothing in a new page and, worse, read as a live timer.
   * Everything else in S is plain data by construction.
   */
  function snapshot() {
    var out = {};
    for (var k in S) if (k !== 'timer') out[k] = S[k];
    return JSON.stringify({ s: out, seed: SEED, scratch: scratchOn, unitSeq: unitSeq });
  }
  function saveRun() {
    if (!S.running || S.over) return;
    post('save', { state: snapshot(), wave: S.wave });
  }
  // The two moments a run is most likely to be abandoned: the tab going away,
  // and the player pausing to deal with something else.
  window.addEventListener('pagehide', saveRun);
  document.addEventListener('visibilitychange', function () { if (document.hidden) saveRun(); });

  function beginSiege(resumeState) {
    rand = mulberry32(SEED);
    actionLog = [];
    HORDE = VERIFIED.slice();   // frozen for the run: no reshuffling faces
    reset();

    if (resumeState) {
      /*
       * Restore over a freshly reset S rather than replacing it, so a snapshot
       * written by an older build cannot leave a key missing that this build
       * expects -- anything the save does not carry keeps the value reset()
       * just gave it.
       */
      for (var k in resumeState.s) S[k] = resumeState.s[k];
      if (typeof resumeState.unitSeq === 'number') unitSeq = resumeState.unitSeq;
      S.timer = 0;
    }

    S.running = true;
    S.paused = false;        // a fresh siege never opens paused
    S.over = false;
    logEl.innerHTML = '';
    tracersClear();          // no shots left over from the run that just fell
    beginBtn.hidden = true;
    if (resumeBtn) resumeBtn.hidden = true;
    paintPause();            // reveals the pause control, hidden until now
    if (resumeState) {
      log('The siege resumes at wave ' + S.wave + '.');
    } else {
      post('begin', { start_wave: REALM.start, scratch: scratchOn ? 1 : 0 });
      startWave();
    }
    musicStart();
    S.timer = setInterval(step, TICK);
  }

  beginBtn.addEventListener('click', function () {
    // Pressing Begin on a board with a saved run abandons it. Not scored:
    // abandoning is not defeat, and paying for it would make quitting a losing
    // siege the correct play.
    if (SAVED) { post('abandon', {}); SAVED = null; }
    beginSiege(null);
  });

  var resumeBtn = document.getElementById('rg-resume');
  if (resumeBtn) {
    resumeBtn.addEventListener('click', function () {
      var st = SAVED;
      SAVED = null;
      resumeBtn.hidden = true;
      // The saved run keeps its own scratch flag: resuming must rebuild the
      // baseline it was PLAYED on, not whatever the toggle happens to say now.
      if (st && typeof st.scratch === 'boolean' && st.scratch !== scratchOn) {
        scratchOn = st.scratch;
        if (scratchBox) scratchBox.checked = scratchOn;
        REALM = (scratchOn && HAS_REALM) ? SCRATCH : SNAPSHOT;
      }
      beginSiege(st);
    });
  }

  // applyBaseline, not a bare reset: a remembered "from scratch" choice has to
  // be in force on the first paint, not only after the box is touched again.
  applyBaseline();
})();
</script>

</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>
