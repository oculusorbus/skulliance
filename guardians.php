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
$rg_alevel = 1;        // best armor level, decides how much a breach is absorbed

if ($rg_me > 0) {
	$rr = $conn->query("SELECT id, name FROM realms WHERE user_id = $rg_me AND active = 1 LIMIT 1");
	if ($rr && $rr->num_rows > 0) {
		$rrow = $rr->fetch_assoc();
		$rg_realm_id = intval($rrow['id']);
		$rg_realm_name = (string)$rrow['name'];
		$rg_has_realm = true;

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
                    ORDER BY RAND() LIMIT 40");
if ($hr) while ($h = $hr->fetch_assoc()) {
	$rg_horde[] = array(
		'name' => $h['username'],
		'img'  => 'https://cdn.discordapp.com/avatars/' . $h['discord_id'] . '/' . $h['avatar'] . '.png',
	);
}
?>

<div class="row" id="row1">
  <div class="col1of3" style="max-width:820px;margin:0 auto;flex:1 1 100%;">

	<h2 class="rg-intro">Realm Guardians <span class="rg-tag">prototype</span></h2>
	<div class="rg-blurb rg-intro">
		<?php if ($rg_has_realm): ?>
			Defending <strong><?php echo htmlspecialchars($rg_realm_name); ?></strong> &mdash;
			<?php echo $rg_army; ?> guardians, <?php echo $rg_cache; ?> weapons in the cache,
			<?php echo $rg_total; ?> total location levels. Power <?php echo $rg_power; ?> starts you at wave <?php echo $rg_start_wave; ?>.
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

		<div id="rg-field">
			<div id="rg-wall"></div>
			<div id="rg-sortie"></div>
			<div id="rg-enemies"></div>
		</div>

		<div id="rg-locations">
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/tower.png" alt="" onerror="this.style.display='none'">Tower <span class="rg-lvl" id="rg-lvl-tower">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-garrison">0</strong>/<span id="rg-garrison-cap">4</span> garrison &middot; <span id="rg-armed">0</span> armed &middot; <span id="rg-armored">0</span> armoured</div>
				<button type="button" class="rg-act" data-act="deploy">Deploy</button>
				<button type="button" class="rg-act rg-up" data-act="up-tower">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/barracks.png" alt="" onerror="this.style.display='none'">Barracks <span class="rg-lvl" id="rg-lvl-barracks">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-reserve">0</strong> in reserve</div>
				<div class="rg-bar"><i id="rg-bar-barracks"></i></div>
				<button type="button" class="rg-act rg-up" data-act="up-barracks">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/armory.png" alt="" onerror="this.style.display='none'">Armory <span class="rg-lvl" id="rg-lvl-armory">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-weapons">0</strong> weapons &middot; <span id="rg-armor">0</span> armour</div>
				<div class="rg-bar"><i id="rg-bar-armory"></i></div>
				<button type="button" class="rg-act rg-up" data-act="up-armory">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/crypt.png" alt="" onerror="this.style.display='none'">Crypt <span class="rg-lvl" id="rg-lvl-crypt">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-dead">0</strong> dead</div>
				<button type="button" class="rg-act" data-act="raise">Raise</button>
				<button type="button" class="rg-act rg-up" data-act="up-crypt">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/portal.png" alt="" onerror="this.style.display='none'">Portal <span class="rg-lvl" id="rg-lvl-portal">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-sortied">0</strong> in the field</div>
				<div class="rg-bar"><i id="rg-bar-portal"></i></div>
				<button type="button" class="rg-act" data-act="sortie">Sortie</button>
				<button type="button" class="rg-act rg-up" data-act="up-portal">Upgrade</button>
			</div>
			<div class="rg-loc">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/factory.png" alt="" onerror="this.style.display='none'">Factory <span class="rg-lvl" id="rg-lvl-factory">1</span></div>
				<div class="rg-loc-stat"><strong id="rg-items">0</strong> items</div>
				<div class="rg-bar"><i id="rg-bar-factory"></i></div>
				<button type="button" class="rg-act" data-act="fortify">Fortify</button>
				<button type="button" class="rg-act rg-up" data-act="up-factory">Upgrade</button>
			</div>
			<div class="rg-loc rg-wide">
				<div class="rg-loc-name"><img class="rg-icon" src="icons/locations/mine.png" alt="" onerror="this.style.display='none'">Mine <span class="rg-lvl" id="rg-lvl-mine">1</span></div>
				<div class="rg-loc-stat">CARBON flowing &middot; <span id="rg-mine-rate">+0/s</span></div>
				<div class="rg-bar"><i id="rg-bar-mine"></i></div>
				<button type="button" class="rg-act rg-up" data-act="up-mine">Upgrade</button>
			</div>
		</div>

		<div id="rg-log"></div>
		<button type="button" id="rg-begin">Begin the siege</button>
	</div>

  </div>
</div>

<style>
.rg-blurb { font-size:.82rem; color:rgba(255,255,255,.5); margin:-6px 0 16px; line-height:1.5; }
.rg-blurb strong { color:#00c8a0; }
.rg-tag { font-size:.6rem; text-transform:uppercase; letter-spacing:.12em; color:#ffcc44; border:1px solid rgba(255,204,68,.4); border-radius:10px; padding:2px 8px; vertical-align:middle; }
.rg-hud { display:flex; gap:16px; font-size:.78rem; color:rgba(255,255,255,.55); margin-bottom:10px; flex-wrap:wrap; align-items:center; }
.rg-hud strong { color:#00c8a0; font-size:1rem; }
#rg-status { margin-left:auto; color:#ffcc44; }
#rg-sound, #rg-music { background:none; border:0; font-size:1rem; cursor:pointer; padding:0 2px; line-height:1; }
#rg-track { background:#0d1e30; color:rgba(255,255,255,.75); border:1px solid rgba(255,255,255,.15); border-radius:5px; font-size:.72rem; padding:3px 5px; }
#rg-vol { width:70px; accent-color:#00c8a0; vertical-align:middle; }
/* On a phone the HUD is already tight; the volume slider is the first thing
   that can go, since the mute button covers the urgent case. */
@media (max-width:560px) { #rg-vol { display:none; } }

#rg-field { position:relative; height:80px; background:#0a1929; border:1px solid rgba(255,255,255,.08); border-radius:8px; overflow:hidden; margin-bottom:12px; }
#rg-wall { position:absolute; left:0; top:0; bottom:0; width:10px; background:linear-gradient(180deg,#00c8a0,#007a61); }
#rg-enemies, #rg-sortie { position:absolute; inset:0; }
.rg-foe { position:absolute; top:50%; transform:translateY(-50%); width:22px; height:22px; border-radius:50%; background:#c0392b; border:2px solid #c0392b; transition:left .1s linear; }
.rg-foe img { width:100%; height:100%; border-radius:50%; display:block; object-fit:cover; }
.rg-foe.rg-tough { width:30px; height:30px; border-color:#c39bd3; box-shadow:0 0 8px rgba(195,155,211,.6); }
.rg-foe i { position:absolute; left:0; bottom:-6px; height:2px; background:#ff6b6b; }
/* Sortied guardians sit above the line so they read as yours, not theirs. */
/* Your guardians: their own NFT art, ringed in the platform green so they read
   as yours at a glance against the red horde. */
.rg-unit { position:absolute; top:16%; width:22px; height:22px; border-radius:50%; background:#0a1929; border:2px solid #00c8a0; box-shadow:0 0 6px rgba(0,200,160,.5); transition:left .1s linear; }
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
    alevel: <?php echo intval($rg_alevel); ?>
  };
  var HORDE = <?php echo json_encode($rg_horde); ?>;
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
  var TICK = 100;
  var actionLog = [];

  var S = {};
  function reset() {
    S = {
      running:false, over:false, tick:0, wave:REALM.start - 1,
      hp:100, maxhp:100, carbon:0,
      reserve:REALM.army, weapons:REALM.cache, armor:REALM.acache, dead:0,
      garrison:0, armed:0, armored:0, items:0, sortied:[],
      lvl:JSON.parse(JSON.stringify(REALM.levels)),
      prod:{ barracks:0, armory:0, factory:0, mine:0, portal:0, reinforce:0 },
      foes:[], nextAttack:0, betweenWaves:0, fortifyFor:0
    };
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
  function upgradeCost(k) { return 16 * L(k) + 8 * L(k) * L(k); }
  // Weapon LEVEL matters, not just count -- a better cache hits harder.
  function towerDamage()  {
    var d = S.armed * (2 + REALM.wlevel) + (S.garrison - S.armed) * 1;
    return S.fortifyFor > 0 ? Math.round(d * 1.5) : d;
  }

  var el = {};
  ['wave','hp','carbon','reserve','weapons','dead','garrison','garrison-cap','armed','status',
   'sortied','items','mine-rate','armor','armored',
   'lvl-tower','lvl-barracks','lvl-armory','lvl-crypt','lvl-portal','lvl-factory','lvl-mine',
   'bar-barracks','bar-armory','bar-factory','bar-mine','bar-portal']
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
    a.loop = true;
    a.volume = musicVol;
    // A missing or unplayable track must not take the game with it.
    a.addEventListener('error', function () { if (music === a) music = null; });
    music = a;
    if (was && musicOn) music.play().catch(function () {});
  }
  function musicStart() {
    if (!TRACKS.length || !musicOn) return;
    if (!music) musicLoad(trackIdx);
    if (music) music.play().catch(function () {});
  }
  function musicStop() { if (music) music.pause(); }

  var foeSeq = 0, unitSeq = 0;
  function foeIdentity(f) {
    if (!HORDE.length) return { name:'A raider', img:'' };
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
    S.prod.armory++;
    if (S.prod.armory >= armoryRate()) { S.prod.armory = 0; if (S.weapons < weaponCap()) S.weapons++; }
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
    el.sortied.textContent = S.sortied.length;
    el['garrison-cap'].textContent = garrisonCap();
    el['mine-rate'].textContent = '+' + L('mine') + ' per ' + (mineRate() / 10).toFixed(1) + 's';
    ['tower','barracks','armory','crypt','portal','factory','mine'].forEach(function (k) {
      el['lvl-' + k].textContent = S.lvl[k];
    });
    el['bar-barracks'].style.width = Math.round(S.prod.barracks / barracksRate() * 100) + '%';
    el['bar-armory'].style.width   = Math.round(S.prod.armory / armoryRate() * 100) + '%';
    el['bar-factory'].style.width  = Math.round(S.prod.factory / factoryRate() * 100) + '%';
    el['bar-mine'].style.width     = Math.round(S.prod.mine / mineRate() * 100) + '%';
    el['bar-portal'].style.width   = Math.round(S.prod.portal / portalRate() * 100) + '%';

    var html = '';
    for (var i = 0; i < S.foes.length; i++) {
      var f = S.foes[i];
      if (f.pos > 100) continue;
      var who = foeIdentity(f);
      html += '<div class="rg-foe' + (f.tough ? ' rg-tough' : '') + '" style="left:' + f.pos + '%"'
            + ' title="' + escAttr(who.name) + '">'
            + (who.img ? '<img src="' + escAttr(who.img) + '" alt="" onerror="this.onerror=null;this.src=\'icons/skull.png\'">' : '')
            + '<i style="width:' + Math.max(0, Math.round(f.hp / f.max * 20)) + 'px"></i></div>';
    }
    foesEl.innerHTML = html;

    // Your guardians wear their own NFT art, with the weapon they carry badged
    // on top -- so the field reads as your skulls against their faces, and an
    // armed guardian is visibly armed.
    var shtml = '';
    for (var u = 0; u < S.sortied.length; u++) {
      var un = S.sortied[u];
      var art = UNITS.length ? UNITS[un.slot % UNITS.length] : null;
      shtml += '<div class="rg-unit' + (un.armed ? ' rg-armed' : '') + (un.prot ? ' rg-prot' : '') + '" style="left:' + un.pos + '%"'
             + (art ? ' title="' + escAttr(art.name) + '"' : '') + '>'
             + (art ? '<img src="' + escAttr(art.img) + '" alt="" onerror="this.style.display=\'none\'">' : '')
             + (un.armed && WICON ? '<b><img src="' + escAttr(WICON) + '" alt="" onerror="this.parentNode.style.display=\'none\'"></b>' : '')
             + (un.prot && AICON ? '<u><img src="' + escAttr(AICON) + '" alt="" onerror="this.parentNode.style.display=\'none\'"></u>' : '')
             + '</div>';
    }
    sortieEl.innerHTML = shtml;

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
        S.sortied.push({ pos:35 + i * 4,
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
      if (S.carbon >= upgradeCost(k)) { S.carbon -= upgradeCost(k); S.lvl[k]++; log(k + ' raised to ' + S.lvl[k] + '.'); }
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
  try { if (localStorage.getItem('rg-sound') === 'off') sfxOn = false; } catch (e) {}
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
