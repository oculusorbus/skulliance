<?php
/*
 * REALM GUARDIANS -- prototype.
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
 * Nothing links here: no nav, no hub card, no leaderboard, no cron, no rewards.
 *
 * ---------------------------------------------------------------------------
 * WHY THE SIMULATION IS DETERMINISTIC WHEN NOTHING IS SUBMITTED YET
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

$rg_me = intval($_SESSION['userData']['user_id'] ?? 0);

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
$rg_wicon = '';        // the best weapon in the cache, worn by armed guardians
$rg_aicon = '';        // the best armor in the cache, worn by protected guardians
$rg_acache = 0;        // unissued armor pieces
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
	$wr = $conn->query("SELECT w.name FROM gear g
	                    INNER JOIN weapons w ON w.id = g.item_id
	                    WHERE g.user_id = $rg_me AND g.type = 'weapon' AND g.quantity > 0
	                    ORDER BY w.level DESC LIMIT 1");
	if ($wr && $wr->num_rows > 0) {
		$wn = (string)$wr->fetch_assoc()['name'];
		if ($wn !== '') $rg_wicon = 'icons/' . strtolower(str_replace(array('%', ' '), array('', '-'), $wn)) . '.png';
	}

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
	$ar2 = $conn->query("SELECT a.name FROM gear g
	                     INNER JOIN armor a ON a.id = g.item_id
	                     WHERE g.user_id = $rg_me AND g.type = 'armor' AND g.quantity > 0
	                     ORDER BY a.level DESC LIMIT 1");
	if ($ar2 && $ar2->num_rows > 0) {
		$an = (string)$ar2->fetch_assoc()['name'];
		if ($an !== '') $rg_aicon = 'icons/' . strtolower(str_replace(array('%', ' '), array('', '-'), $an)) . '.png';
	}
}

/*
 * THE CONSCRIPT FLOOR. No realm means no enlisted soldiers and nothing to
 * deploy -- not a weak position, an empty one. Without this the entry-level
 * siege is unplayable rather than merely hard, and the game recruits nobody.
 */
if (!$rg_has_realm) {
	$rg_levels = array('tower'=>1,'barracks'=>1,'armory'=>1,'crypt'=>1,'portal'=>1,'factory'=>1,'mine'=>1);
	$rg_army   = 4;
	$rg_armed  = 1;
	$rg_cache  = 2;
	$rg_acache = 1;
}

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
		'img'  => 'https://cdn.discordapp.com/avatars/' . $h['discord_id'] . '/' . $h['avatar'] . '.png',
	);
}
?>

<?php if ($rg_theme > 0): ?>
	<!--
		The realm's own theme as the backdrop. A fixed layer BEHIND the content
		rather than a background on <body>: body already carries the platform's
		styling and a PWA strip on ::after, and painting over it risks the rest
		of the page. This can only ever sit behind things.

		The dark wash is not decoration -- the themes are busy artwork and the
		HUD is small text over it. Same approach .podium-section.has-theme takes
		in leaderboards.php.
	-->
	<div id="rg-bg" style="background-image:url('images/themes/<?php echo $rg_theme; ?>.jpg')"></div>
<?php endif; ?>

<div class="row" id="row1">
  <div class="col1of3" style="max-width:820px;margin:0 auto;flex:1 1 100%;">

	<h2 class="rg-intro">Realm Guardians <span class="rg-tag">prototype</span></h2>
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
			if ($rg_garrison > 0) $rg_where[] = $rg_garrison . ' on the wall';
			if ($rg_raiders  > 0) $rg_where[] = $rg_raiders . ' out raiding';
			$rg_ready = max(0, $rg_army - $rg_garrison - $rg_raiders);
			if ($rg_ready > 0)    $rg_where[] = $rg_ready . ' in reserve';
			if ($rg_crypt > 0)    $rg_where[] = $rg_crypt . ' in the Crypt';
			?>
			Defending <strong><?php echo htmlspecialchars($rg_realm_name); ?></strong> &mdash;
			<?php echo $rg_roster; ?> guardians<?php
				if (!empty($rg_where)) echo ' (' . implode(', ', $rg_where) . ')';
			?>,
			<?php echo $rg_cache; ?> weapons and <?php echo $rg_acache; ?> armour in the cache,
			<?php echo $rg_total; ?> total location levels.
			Power <?php echo $rg_power; ?> starts you at wave <?php echo $rg_start_wave; ?>.
		<?php else: ?>
			You have no realm, so you hold the wall with conscripts. Build a realm and you
			start further up the same ladder.
		<?php endif; ?>
		<br><em>Nothing here is saved and nothing is spent. Your realm is untouched no matter how this goes.</em>
	</div>

	<div id="rg-game">

		<div class="rg-hud">
			<!-- The prototype marker lives HERE, not only in the heading, because
			     the heading is hidden under 560px for space -- and now that this
			     is in the nav, most of the people seeing it will be on a phone
			     and should know what they are playing. -->
			<span class="rg-tag rg-tag-hud">prototype</span>
			<span>Wave <strong id="rg-wave">0</strong></span>
			<span>Wall <strong id="rg-hp">100</strong></span>
			<span>CARBON <strong id="rg-carbon">0</strong></span>
			<span id="rg-status">Press Begin</span>
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
			<img id="rg-tower-icon" src="icons/locations/tower.png" alt="Tower"
			     title="The Tower. Its garrison fires from here." onerror="this.style.display='none'">
			<img id="rg-portal-icon" src="icons/locations/portal.png" alt="Portal"
			     title="The Portal. Sortied guardians step through here." onerror="this.style.display='none'">
			<div id="rg-sortie"></div>
			<div id="rg-enemies"></div>
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
					<div class="rg-cap">training next guardian</div>
				<button type="button" class="rg-act rg-up" data-act="up-barracks">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/armory.png" alt="" onerror="this.style.display='none'">Armory <span class="rg-lvl" id="rg-lvl-armory">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-weapons">0</strong> weapons &middot; <span id="rg-armor">0</span> armour</div>
				<div class="rg-bar" title="Time until the Armory forges the next weapon"><i id="rg-bar-armory"></i></div>
					<div class="rg-cap">forging next weapon</div>
					<div class="rg-bar" title="Time until the Armory forges the next piece of armour"><i id="rg-bar-forge"></i></div>
					<div class="rg-cap">forging next armour</div>
				<button type="button" class="rg-act rg-up" data-act="up-armory">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/crypt.png" alt="" onerror="this.style.display='none'">Crypt <span class="rg-lvl" id="rg-lvl-crypt">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-dead">0</strong> dead</div>
				<button type="button" class="rg-act" data-act="raise" title="Spend CARBON to bring your dead back to the Barracks as fresh guardians">Raise</button>
				<button type="button" class="rg-act rg-up" data-act="up-crypt">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/portal.png" alt="" onerror="this.style.display='none'">Portal <span class="rg-lvl" id="rg-lvl-portal">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-sortied">0</strong> in the field</div>
				<div class="rg-bar" title="Portal cooldown -- Sortie is ready when full"><i id="rg-bar-portal"></i></div>
					<div class="rg-cap">sortie ready when full</div>
				<button type="button" class="rg-act" data-act="sortie" title="Send guardians out through the Portal to meet the horde in the open, before it reaches your wall. They fight with no Tower behind them.">Sortie</button>
				<button type="button" class="rg-act rg-up" data-act="up-portal">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/factory.png" alt="" onerror="this.style.display='none'">Factory <span class="rg-lvl" id="rg-lvl-factory">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-items">0</strong> items</div>
				<div class="rg-bar" title="Time until the Factory builds the next item"><i id="rg-bar-factory"></i></div>
					<div class="rg-cap">building next item</div>
				<button type="button" class="rg-act" data-act="fortify" title="Spend a Factory item: repairs the wall and makes the Tower hit 50% harder for six seconds">Fortify</button>
				<button type="button" class="rg-act rg-up" data-act="up-factory">Upgrade</button>
			</div>
			<div class="rg-loc rg-wide">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/mine.png" alt="" onerror="this.style.display='none'">Mine <span class="rg-lvl" id="rg-lvl-mine">1</span></div>
				<div class="rg-loc-stat">CARBON flowing &middot; <span id="rg-mine-rate">+0/s</span></div>
				<div class="rg-bar" title="Time until the Mine yields more CARBON"><i id="rg-bar-mine"></i></div>
					<div class="rg-cap">next CARBON payout</div>
				<button type="button" class="rg-act rg-up" data-act="up-mine">Upgrade</button>
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
				<p><strong>Your job is spending.</strong> CARBON is the one thing in short
				supply, and it has four competing uses:</p>
				<ul>
					<li><strong>Raise</strong> &mdash; buy your dead back as guardians. Cheapest
					with a high Crypt. Best when the Barracks can't replace losses fast enough.</li>
					<li><strong>Upgrade</strong> &mdash; permanently improve a rate or cap for the
					rest of this run. Compounds, so early upgrades are worth more than late ones.</li>
					<li><strong>Sortie</strong> (free, but costs guardians and gear) &mdash; kill
					attackers in the open before they reach the wall. Trades bodies for wall
					damage you never take.</li>
					<li><strong>Fortify</strong> (costs a Factory item) &mdash; repairs the wall
					<em>and</em> makes the Tower hit 50% harder for six seconds. Save it for the
					moment a wave is about to break through.</li>
				</ul>
				<p><strong>The strategy:</strong> spend early on upgrades while they still have
				time to compound, then switch to raising and fortifying once waves outpace
				production. Holding CARBON does nothing &mdash; unspent CARBON is a wave you
				didn't survive.</p>
			</div>
		</details>

		<div id="rg-log"></div>
		<button type="button" id="rg-begin">Begin the siege</button>
	</div>

  </div>
</div>

<style>
#rg-bg {
  position:fixed; inset:0; z-index:-1;
  background-size:cover; background-position:center;
  /* fixed so the field doesn't parallax while the page scrolls */
  background-attachment:fixed;
}
#rg-bg::after { content:''; position:absolute; inset:0; background:rgba(7,17,29,.86); }
/* The panels need to stay legible on top of artwork, so they get a little more
   opacity than they had over flat background. */
#rg-game .rg-loc { background:rgba(13,30,48,.92); }
#rg-field { background:rgba(10,25,41,.92) !important; }
.rg-blurb { font-size:.82rem; color:rgba(255,255,255,.5); margin:-6px 0 16px; line-height:1.5; }
.rg-blurb strong { color:#00c8a0; }
.rg-tag { font-size:.6rem; text-transform:uppercase; letter-spacing:.12em; color:#ffcc44; border:1px solid rgba(255,204,68,.4); border-radius:10px; padding:2px 8px; vertical-align:middle; }
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
.rg-tag-hud { font-size:.55rem; padding:2px 6px; }
#rg-sound, #rg-music { background:none; border:0; font-size:1rem; cursor:pointer; padding:0 2px; line-height:1; }
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

#rg-field { position:relative; height:80px; background:#0a1929; border:1px solid rgba(255,255,255,.08); border-radius:8px; overflow:hidden; margin-bottom:12px; }
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
#rg-tower-icon  { left:14px; top:50%; transform:translateY(-50%); width:26px; height:26px; object-fit:contain; }
#rg-portal-icon { left:25%; top:50%; transform:translate(-50%,-50%); width:30px; height:30px;
                  object-fit:contain; opacity:.55; filter:drop-shadow(0 0 6px rgba(0,200,160,.7)); }
#rg-sortie, #rg-enemies { z-index:1; }
.rg-foe { position:absolute; top:50%; transform:translateY(-50%); box-sizing:border-box; width:26px; height:26px; border-radius:50%; background:#c0392b; border:2px solid #c0392b; transition:left .1s linear; }
.rg-foe img { width:100%; height:100%; border-radius:50%; display:block; object-fit:cover; }
.rg-foe.rg-tough { width:34px; height:34px; border-color:#c39bd3; box-shadow:0 0 8px rgba(195,155,211,.6); }
.rg-foe i { position:absolute; left:0; bottom:-6px; height:2px; background:#ff6b6b; }
/* Sortied guardians sit above the line so they read as yours, not theirs. */
/* Your guardians: their own NFT art, ringed in the platform green so they read
   as yours at a glance against the red horde. */
/* Identical footprint to a foe -- border-box so the border sits INSIDE, or a
   2px border silently makes one 4px bigger than the other. */
.rg-unit { position:absolute; top:16%; box-sizing:border-box; width:26px; height:26px; border-radius:50%; background:#0a1929; border:2px solid #00c8a0; box-shadow:0 0 6px rgba(0,200,160,.5); transition:left .1s linear; }
.rg-unit img { width:100%; height:100%; border-radius:50%; display:block; object-fit:cover; }
.rg-unit.rg-armed { border-color:#ffcc44; box-shadow:0 0 8px rgba(255,204,68,.6); }
/* The weapon they carry, badged on the shoulder. */
.rg-unit b { position:absolute; right:-5px; bottom:-5px; width:14px; height:14px; background:#07111d; border-radius:50%; display:block; padding:1px; }
/* Armour on the other shoulder, so a guardian can visibly carry both. */
.rg-unit u { position:absolute; left:-5px; bottom:-5px; width:14px; height:14px; background:#07111d; border-radius:50%; display:block; padding:1px; }
.rg-unit b img, .rg-unit u img { width:100%; height:100%; object-fit:contain; border-radius:0; }
/* Armoured guardians get a steel ring; armed ones gold. Both shows as gold with
   a steel inner edge, which reads as "fully kitted" at a glance. */
.rg-unit.rg-prot { box-shadow:0 0 0 2px rgba(190,200,215,.8), 0 0 8px rgba(190,200,215,.5); }

#rg-locations { display:grid; grid-template-columns:repeat(3,minmax(0,1fr)); gap:8px; }
.rg-loc { background:#0d1e30; border:1px solid rgba(255,255,255,.1); border-radius:8px; padding:9px 10px; }
.rg-loc.rg-wide { grid-column:1 / -1; }
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
.rg-act { background:#00c8a0; color:#04121d; font-weight:bold; border:0; border-radius:5px; padding:7px 9px; font-size:.74rem; cursor:pointer; margin:0 3px 3px 0; }
.rg-act.rg-up { background:rgba(255,255,255,.12); color:rgba(255,255,255,.75); }
.rg-act:disabled { opacity:.32; cursor:default; }
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
#rg-begin { display:block; margin:14px auto 0; background:#00c8a0; color:#04121d; font-weight:bold; border:0; border-radius:6px; padding:11px 26px; font-size:.9rem; cursor:pointer; }
#rg-begin[hidden] { display:none; }

/* The lessons Obscura paid for: fits a phone, nothing pinned over the board. */
@media (max-width:760px) { #rg-locations { grid-template-columns:repeat(2,minmax(0,1fr)); } }
@media (max-width:560px) {
  .rg-intro { display:none; }
  #rg-field { height:64px; }
  #rg-locations { gap:6px; }
  .rg-loc { padding:7px 8px; }
  .rg-loc-stat { font-size:.74rem; }
  .rg-act { padding:8px 8px; font-size:.72rem; }
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
  var REALM = {
    levels: <?php echo json_encode($rg_levels); ?>,
    army:   <?php echo intval($rg_army); ?>,
    armed:  <?php echo intval($rg_armed); ?>,
    cache:  <?php echo intval($rg_cache); ?>,
    wlevel: <?php echo intval($rg_wlevel); ?>,
    start:  <?php echo intval($rg_start_wave); ?>,
    acache: <?php echo intval($rg_acache); ?>,
    crypt:  <?php echo intval($rg_crypt); ?>,
    garrison:<?php echo intval($rg_garrison); ?>,
    garmed: <?php echo intval($rg_g_armed); ?>,
    garmored:<?php echo intval($rg_g_armored); ?>,
    raiders:<?php echo intval($rg_raiders); ?>,
    ramed:  <?php echo intval($rg_r_armed); ?>,
    rarmored:<?php echo intval($rg_r_armored); ?>,
    alevel: <?php echo intval($rg_alevel); ?>
  };
  // CANDIDATES, not the horde. A Discord avatar url 404s whenever someone has
  // changed their picture since we cached the hash, and the fallback turned the
  // field into a wall of identical skulls. Only verified faces get used.
  var CANDIDATES = <?php echo json_encode($rg_horde); ?>;
  var VERIFIED = [], HORDE = [];
  var UNITS = <?php echo json_encode($rg_units); ?>;   // your soldiers, as NFT art
  var WICON = <?php echo json_encode($rg_wicon); ?>;   // best weapon in the cache
  var AICON = <?php echo json_encode($rg_aicon); ?>;   // best armor in the cache

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
  var PORTAL_X = 25;
  var TICK = 100;
  var actionLog = [];

  var S = {};
  function reset() {
    S = {
      running:false, over:false, tick:0, wave:REALM.start - 1,
      hp:100, maxhp:100, carbon:0,
      // The reserve is what is LEFT: everyone alive who is not already on the
      // wall or out on a raid. Without this the same guardian would be counted
      // twice and the army would appear to grow at the whistle.
      reserve:Math.max(0, REALM.army - REALM.garrison - REALM.raiders),
      weapons:REALM.cache, armor:REALM.acache, dead:REALM.crypt,
      garrison:0, armed:0, armored:0, items:0, sortied:[], emerging:[],
      lvl:JSON.parse(JSON.stringify(REALM.levels)),
      prod:{ barracks:0, armory:0, forge:0, factory:0, mine:0, portal:0, reinforce:0 },
      bought:{ tower:0, barracks:0, armory:0, crypt:0, portal:0, factory:0, mine:0 },
      foes:[], nextAttack:0, betweenWaves:0, fortifyFor:0
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
    var seat = Math.min(REALM.garrison, garrisonCap());
    S.garrison = seat;
    S.armed    = Math.min(REALM.garmed, seat);
    S.armored  = Math.min(REALM.garmored, seat);
    S.reserve += Math.max(0, REALM.garrison - seat);

    for (var r = 0; r < REALM.raiders; r++) {
      S.sortied.push({
        pos:   Math.min(96, PORTAL_X + 2 + r * 3),
        hp:    (r < REALM.ramed ? 6 : 4) + (r < REALM.rarmored ? 2 + REALM.alevel : 0),
        armed: r < REALM.ramed,
        prot:  r < REALM.rarmored,
        slot:  unitSeq++
      });
    }
  }

  /* Every level is a RATE or a CAP -- never one power number. That is the whole
     point of mapping realm locations onto a siege. */
  function L(k) { return Math.max(1, S.lvl[k] || 1); }
  function garrisonCap()  { return 3 + L('tower'); }
  function reserveCap()   { return 6 + L('barracks') * 3; }
  function weaponCap()    { return 4 + L('armory') * 3; }
  function itemCap()      { return 1 + Math.ceil(L('factory') / 2); }
  function barracksRate() { return Math.max(12, 62 - L('barracks') * 5); }
  function armoryRate()   { return Math.max(16, 72 - L('armory') * 5); }
  // Armour comes slower than weapons and is capped lower: it is the resource
  // that turns a breach into a scratch, so it should never be abundant.
  function forgeRate()    { return Math.max(34, 150 - L('armory') * 9); }
  function armorCap()     { return 2 + Math.ceil(L('armory') / 2); }
  function factoryRate()  { return Math.max(60, 240 - L('factory') * 16); }
  function mineRate()     { return Math.max(6, 26 - L('mine') * 2); }
  function portalRate()   { return Math.max(40, 170 - L('portal') * 12); }
  // How fast the Tower refills itself from the Barracks. Faster with Barracks
  // level, so investing there is felt as resilience rather than a bigger number.
  function reinforceRate()  { return Math.max(4, 20 - L('barracks') * 1.5); }
  function sortieSize()   { return Math.max(1, Math.ceil(L('portal') / 2)); }
  function raiseCost()    { return Math.max(3, 12 - L('crypt') * 2); }
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
    var d = S.armed * (2 + REALM.wlevel) + (S.garrison - S.armed) * 1;
    return S.fortifyFor > 0 ? Math.round(d * 1.5) : d;
  }

  var el = {};
  ['wave','hp','carbon','reserve','weapons','dead','garrison','garrison-cap','armed','status',
   'sortied','items','mine-rate','armor','armored',
   'lvl-tower','lvl-barracks','lvl-armory','lvl-crypt','lvl-portal','lvl-factory','lvl-mine',
   'bar-barracks','bar-armory','bar-forge','bar-factory','bar-mine','bar-portal']
    .forEach(function (k) { el[k] = document.getElementById('rg-' + k); });
  var foesEl = document.getElementById('rg-enemies');
  var sortieEl = document.getElementById('rg-sortie');
  var logEl = document.getElementById('rg-log');
  var beginBtn = document.getElementById('rg-begin');

  /* ---- The cacophony. Crypt Crawl's weapon sounds, reused from audio/sounds/.
     Armed defenders fire guns, unarmed swing fists, so an empty Armory is
     AUDIBLE before the counter is read. Cosmetic: never touches the sim. ---- */
  var ARMED_SFX   = ['machinegun','pistol','sniperrifle','rocketlauncher','artillery','grenade','flamethrower','demolition'];
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
  function sfxVolley() {
    if (!sfxOn) return;
    var shots = Math.min(2, Math.max(1, Math.ceil(S.garrison / 3)));
    for (var i = 0; i < shots; i++) {
      var bank = (i < S.armed) ? ARMED_SFX : UNARMED_SFX;
      var name = bank[(sfxCursor++) % bank.length];
      (function (n, d) { setTimeout(function () { sfxPlay(n, 0.10); }, d); })(name, i * 70);
    }
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
  }

  function step() {
    S.tick++;
    if (S.fortifyFor > 0) S.fortifyFor--;

    // Production. Every location earns its keep on a timer.
    S.prod.barracks++;
    if (S.prod.barracks >= barracksRate()) { S.prod.barracks = 0; if (S.reserve < reserveCap()) S.reserve++; }
    /*
     * The Armory forges BOTH. It was producing weapons only, so armour was
     * whatever the cache started with and then gone for good -- reported as
     * "the armory doesn't seem to be generating armor, only weapons".
     * Armour comes slower than weapons, which is what keeps it a resource worth
     * spending carefully rather than a permanent second health bar.
     */
    S.prod.armory++;
    if (S.prod.armory >= armoryRate()) { S.prod.armory = 0; if (S.weapons < weaponCap()) S.weapons++; }
    S.prod.forge++;
    if (S.prod.forge >= forgeRate()) { S.prod.forge = 0; if (S.armor < armorCap()) S.armor++; }
    S.prod.factory++;
    if (S.prod.factory >= factoryRate()) { S.prod.factory = 0; if (S.items < itemCap()) S.items++; }
    S.prod.mine++;
    if (S.prod.mine >= mineRate()) { S.prod.mine = 0; S.carbon += L('mine'); }
    if (S.prod.portal < portalRate()) S.prod.portal++;

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
      if (S.reserve > 0 && S.garrison < garrisonCap()) {
        S.reserve--; S.garrison++;
        if (S.weapons > 0) { S.weapons--; S.armed++; }
        if (S.armor > 0)   { S.armor--;   S.armored++; }
      }
    }

    // The Tower fires on the closest foe still short of the wall.
    S.nextAttack--;
    if (S.nextAttack <= 0 && S.foes.length && S.garrison > 0) {
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
        near.hp -= u.armed ? (2 + REALM.wlevel) : 1;
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
      f.pos -= f.speed;
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
        if (S.armored > 0) {
          S.armored--;
          S.hp += Math.min(f.tough ? 12 : 5, 1 + REALM.alevel);   // partly absorbed
          log(escAttr(foeIdentity(f).name) + ' breaks against the armour.');
          sfxPlay('melee', 0.14);
        } else if (S.garrison > 0) {
          S.garrison--; if (S.armed > 0) S.armed--;
          S.dead++;
          log(escAttr(foeIdentity(f).name) + ' breaches the wall. A guardian falls.', true);
          sfxPlay('death', 0.16);
        }
        if (S.hp <= 0) return end();
      }
    }

    if (!S.foes.length) {
      // The respite shrinks as the siege wears on. A fixed gap meant a strong
      // realm always had time to fully restock, which is the other half of why
      // it stopped being a fight.
      if (S.betweenWaves <= 0) {
        S.betweenWaves = Math.max(14, 45 - S.wave);
        el.status.textContent = 'Wave held &mdash; regroup';
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
    document.getElementById('rg-wall').className = S.fortifyFor > 0 ? 'rg-fortified' : '';
    el.wave.textContent = S.wave;
    el.hp.textContent = Math.max(0, S.hp);
    el.carbon.textContent = S.carbon;
    el.reserve.textContent = S.reserve;
    el.weapons.textContent = S.weapons;
    el.armor.textContent = S.armor;
    el.dead.textContent = S.dead;
    el.garrison.textContent = S.garrison;
    el.armed.textContent = S.armed;
    el.armored.textContent = S.armored;
    el.items.textContent = S.items;
    // Counts those still stepping through, or the number dips as they queue.
    el.sortied.textContent = S.sortied.length + S.emerging.length;
    el['garrison-cap'].textContent = garrisonCap();
    el['mine-rate'].textContent = '+' + L('mine') + ' per ' + (mineRate() / 10).toFixed(1) + 's';
    ['tower','barracks','armory','crypt','portal','factory','mine'].forEach(function (k) {
      el['lvl-' + k].textContent = S.lvl[k];
    });
    el['bar-barracks'].style.width = Math.round(S.prod.barracks / barracksRate() * 100) + '%';
    el['bar-armory'].style.width   = Math.round(S.prod.armory / armoryRate() * 100) + '%';
    el['bar-forge'].style.width    = Math.round(S.prod.forge / forgeRate() * 100) + '%';
    el['bar-factory'].style.width  = Math.round(S.prod.factory / factoryRate() * 100) + '%';
    el['bar-mine'].style.width     = Math.round(S.prod.mine / mineRate() * 100) + '%';
    el['bar-portal'].style.width   = Math.round(S.prod.portal / portalRate() * 100) + '%';

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
      node.lastChild.style.width = Math.max(0, Math.round(f.hp / f.max * 20)) + 'px';
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
        unode.className = 'rg-unit' + (un2.armed ? ' rg-armed' : '') + (un2.prot ? ' rg-prot' : '');
        if (art2) {
          unode.title = art2.name;
          var uimg = document.createElement('img');
          uimg.alt = '';
          uimg.onerror = function () { this.style.display = 'none'; };
          uimg.src = art2.img;
          unode.appendChild(uimg);
        }
        if (un2.armed && WICON) {
          var wb = document.createElement('b');
          var wi = document.createElement('img');
          wi.alt = ''; wi.onerror = function () { wb.style.display = 'none'; }; wi.src = WICON;
          wb.appendChild(wi); unode.appendChild(wb);
        }
        if (un2.prot && AICON) {
          var ab = document.createElement('u');
          var ai = document.createElement('img');
          ai.alt = ''; ai.onerror = function () { ab.style.display = 'none'; }; ai.src = AICON;
          ab.appendChild(ai); unode.appendChild(ab);
        }
        unitNodes[un2.slot] = unode;
        sortieEl.appendChild(unode);
      }
      unode.style.left = un2.pos + '%';
    }
    for (var uid in unitNodes) {
      if (!useen[uid]) { sortieEl.removeChild(unitNodes[uid]); delete unitNodes[uid]; }
    }


    document.querySelectorAll('.rg-act').forEach(function (b) {
      var a = b.dataset.act;
      if (a === 'deploy')       b.disabled = !(S.reserve > 0 && S.garrison < garrisonCap());
      else if (a === 'raise')   b.disabled = !(S.dead > 0 && S.carbon >= raiseCost());
      else if (a === 'sortie')  b.disabled = !(S.reserve > 0 && S.prod.portal >= portalRate() && S.running);
      else if (a === 'fortify') b.disabled = !(S.items > 0);
      else {
        var k = a.slice(3);
        b.disabled = S.carbon < upgradeCost(k);
        b.textContent = 'Upgrade (' + upgradeCost(k) + ')';
      }
    });
  }

  function act(a) {
    if (!S.running || S.over) return;
    actionLog.push([S.tick, a]);   // what a server would replay
    /*
     * Deploy and Raise fill in ONE click rather than one guardian per click.
     * Clicking twelve times to refill a tower is not a decision, it is a
     * dexterity tax -- and it was what made the game unwinnable by hand at
     * higher waves. The choice worth making is "reinforce now or spend the
     * CARBON on an upgrade", and that survives batching intact.
     */
    if (a === 'deploy' && S.reserve > 0 && S.garrison < garrisonCap()) {
      var room = garrisonCap() - S.garrison, sent = 0;
      while (room-- > 0 && S.reserve > 0) {
        S.reserve--; S.garrison++; sent++;
        if (S.weapons > 0) { S.weapons--; S.armed++; }
      }
      log(sent + ' to the wall.');
    } else if (a === 'raise' && S.dead > 0 && S.carbon >= raiseCost()) {
      var raised = 0;
      while (S.dead > 0 && S.carbon >= raiseCost()) { S.carbon -= raiseCost(); S.dead--; S.reserve++; raised++; }
      log('The Crypt gives ' + raised + ' back.');
    } else if (a === 'sortie' && S.reserve > 0 && S.prod.portal >= portalRate()) {
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
      var n = Math.min(sortieSize(), S.reserve);
      for (var i = 0; i < n; i++) {
        S.reserve--;
        var armed = S.weapons > 0;
        if (armed) S.weapons--;
        // Armour goes out with them. A sortie has no tower behind it, so this
        // is where protection is felt most sharply.
        var prot = S.armor > 0;
        if (prot) S.armor--;
        // slot picks which enlisted NFT this guardian is, and stays fixed for
        // its life so the face on the field doesn't change between renders.
        S.emerging.push({ pos:PORTAL_X,
                         hp:(armed ? 6 : 4) + (prot ? 2 + REALM.alevel : 0),
                         armed:armed, prot:prot, slot:unitSeq++ });
      }
      log(n + ' guardian' + (n > 1 ? 's ride' : ' rides') + ' out through the Portal.');
    } else if (a === 'fortify' && S.items > 0) {
      S.items--;
      S.hp = Math.min(S.maxhp, S.hp + 12 + L('factory') * 2);
      S.fortifyFor = 60;   // six seconds of heavier fire
      log('The Factory shores up the wall. The guns bite harder.');
    } else if (a.indexOf('up-') === 0) {
      var k = a.slice(3);
      if (S.carbon >= upgradeCost(k)) { S.carbon -= upgradeCost(k); S.lvl[k]++; S.bought[k]++; log(k + ' raised to ' + S.lvl[k] + '.'); }
    }
    render();
  }

  function end() {
    S.over = true; S.running = false;
    clearInterval(S.timer);
    musicStop();
    el.status.textContent = 'The wall is breached';
    log('The realm falls at wave ' + S.wave + '. Guardians lost: ' + S.dead + '.', true);
    beginBtn.textContent = 'Hold again';
    beginBtn.hidden = false;
  }

  document.addEventListener('click', function (e) {
    var b = e.target.closest('.rg-act');
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

  beginBtn.addEventListener('click', function () {
    rand = mulberry32(SEED);
    actionLog = [];
    HORDE = VERIFIED.slice();   // frozen for the run: no reshuffling faces
    reset();

    S.running = true;
    logEl.innerHTML = '';
    beginBtn.hidden = true;
    startWave();
    musicStart();
    S.timer = setInterval(step, TICK);
  });

  reset();
  render();
})();
</script>

</body>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>
