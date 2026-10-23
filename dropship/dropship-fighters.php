<?php
/**
 * dropship-fighters.php -- assembled DHC Fighters as Drop Ship soldiers.
 *
 * WHY THIS IS POSSIBLE AT ALL. Three things used to block it and all
 * three are gone:
 *
 *   The two apps are in different databases. dropshipSyncFromSkulliance()
 *   already crosses that boundary for Oculus Lounge, by policy and
 *   discord_id, so the plumbing existed.
 *
 *   Fighter art is assembled on the fly. dhcf_render_fighter() writes a
 *   real PNG to dhcrenders/ named for the serial and a hash of the
 *   traits, and hands back an absolute URL -- so the art IS a file, and
 *   dropshipSoldierImage() now accepts a URL where a CID used to go.
 *
 *   Nothing knew what a Fighter was worth in Drop Ship terms. The DHC2
 *   trait mapping reads torso, headgear, arms, weapon, companion and
 *   effects, which is exactly what an assembled Fighter has.
 *
 * NO CAP AND NO GATE, at the owner's direction. Fighters are free to
 * assemble, so in principle a player could field an army for nothing --
 * raised before building and answered: three holders play this game and
 * all three are already building armies. The point is to open the
 * weekly ladder up, and a Fighter is another reason to assemble.
 *
 * A FIGHTER IS NOT AN NFT and the sync must never pretend otherwise. It
 * writes to the same soldiers table because that is what the run reads,
 * but the asset_name is prefixed so nothing can confuse one with a
 * minted DHC2F row -- including dropshipNftLoadouts(), whose table is
 * keyed on the minted names.
 */

/** The prefix that marks a soldier as an assembled Fighter, not a mint. */
function dropshipFighterPrefix() { return 'DHCFA'; }

/** Is this soldier an assembled Fighter? */
function dropshipIsFighter($asset_name) {
	return strncmp((string)$asset_name, dropshipFighterPrefix(), 5) === 0;
}

/**
 * The live scoring data, read once.
 *
 * Holds the minted pool's trait frequencies -- which is what lets a
 * Fighter that was never minted be rarity-weighted at all -- and the
 * three armour-score boundaries of that pool, so an assembled Fighter is
 * placed on the same curve as the collection rather than its own.
 */
function dhc2FighterRules() {
	static $r = null;
	if ($r !== null) return $r;
	$raw = @file_get_contents(__DIR__ . '/../dhc2-fighter-rules.json');
	$j   = $raw === false ? null : json_decode($raw, true);
	$r = (is_array($j) && !empty($j['freq']) && !empty($j['cuts'])) ? $j : array();
	return $r;
}

/**
 * Armor and gear for an assembled Fighter, from its traits.
 *
 * THE SAME RULES AS THE MINTED TABLE, run live instead of looked up:
 * armour is a rarity-weighted score over the slots that carry weight,
 * cut against the minted pool's own quartiles; gear is the lean of the
 * value names, and a near dead heat is None.
 *
 * Returns array('armor'=>..., 'gear'=>...), falling back to Base/None
 * when the rules file is missing rather than guessing.
 */
function dhc2FighterStats($traits) {
	$rules = dhc2FighterRules();
	if (!$rules || !is_array($traits)) return array('armor' => 'Base', 'gear' => 'None');
	$freq  = $rules['freq'];
	$total = max(1, (int)$rules['minted']);

	$rarity = function ($cat, $val) use ($freq, $total) {
		$n = isset($freq[$cat][$val]) ? (int)$freq[$cat][$val] : 1;
		/* A trait NO minted Fighter carries counts as a 1-of-1 rather than
		   as infinitely rare -- max(1) above -- or a single novel trait
		   would outweigh the whole build. */
		return log(1 + ($total / max(1, $n)), 2);
	};
	$hits = function ($v, $words) {
		$n = 0;
		foreach ($words as $w) if (strpos($v, $w) !== false) $n++;
		return $n;
	};

	$KW = array(
		'armour' => array('armor','armour','knight','shield','deflektor','protector','plate',
		                  'guard','centinel','sentinel','dreadnought','mech','exo','limb',
		                  'cyborg','robo','robot','amplified','crusher','reinforce'),
		'melee'  => array('axe','scythe','krusher','morning-star','blade','sword','claw',
		                  'perforator','crusher','sash','belt','flexx','arm'),
		'demo'   => array('blaster','cannon','taser','bomb','bio','spike','fire','lava',
		                  'thunder','destruction','attack','explos','annihilation','smoke'),
		'medkit' => array('recovery','bubble','heal','med','symbio','fren','companion',
		                  'interface','restor','nano'),
	);
	$SLOT_W = array('torso' => 3, 'headgear' => 2, 'arms' => 2, 'weapon' => 1,
	                'companion' => 1, 'effects1' => 1, 'effects2' => 1,
	                'background' => 0, 'head' => 0);

	$score = 0;
	foreach ($traits as $slot => $val) {
		$slot = (string)$slot; $val = (string)$val;
		$w = isset($SLOT_W[$slot]) ? $SLOT_W[$slot] : 0;
		if ($w === 0 || $val === '') continue;
		$score += $w * $rarity($slot, $val) * (1 + $hits($val, $KW['armour']));
	}
	$cuts  = $rules['cuts'];
	$armor = ($score >= $cuts[2]) ? 'Heavy'
	       : (($score >= $cuts[1]) ? 'Medium'
	       : (($score >= $cuts[0]) ? 'Light' : 'Base'));

	$lean = array('Melee' => 0.0, 'Demolition' => 0.0, 'Medkit' => 0.0);
	$map  = array('melee' => 'Melee', 'demo' => 'Demolition', 'medkit' => 'Medkit');
	foreach ($traits as $slot => $val) {
		$slot = (string)$slot; $val = (string)$val;
		if ($slot === 'background' || $slot === 'head' || $val === '') continue;
		$r = $rarity($slot, $val);
		if ($slot === 'companion') $lean['Medkit'] += 2 * $r;
		if ($slot === 'arms')      $lean['Melee']  += 1 * $r;
		foreach ($map as $k => $label) {
			$h = $hits($val, $KW[$k]);
			if ($h) $lean[$label] += $h * $r;
		}
	}
	arsort($lean);
	$top = key($lean); $best = current($lean); next($lean);
	$gear = ($best <= 0 || ($best > 0 && current($lean) / $best > 0.85)) ? 'None' : $top;

	return array('armor' => $armor, 'gear' => $gear);
}

/**
 * Pull this player's assembled Fighters in as soldiers.
 *
 * Mirrors dropshipSyncFromSkulliance() -- same cross-database read, same
 * create-or-update, same updateSoldiers() ownership pass at the end --
 * but reads dhc_fighters instead of nfts, and derives the loadout from
 * the Fighter's own traits rather than a policy's metadata.
 *
 * INVALID FIGHTERS ARE SKIPPED. A Fighter stranded by a later rules
 * change cannot be fielded in the Arena either; fielding it here would
 * make Drop Ship the place broken builds go to be useful.
 *
 * THE RENDER IS REQUESTED AT SYNC TIME, not at display time. It is a
 * file write, and the dashboard is not the place to do one per soldier
 * per page load. dhcf_render_fighter() returns the existing file's URL
 * when the traits have not changed, so a resync is cheap; when they
 * HAVE changed the name changes with them, which is what keeps a
 * re-assembled Fighter's picture honest.
 */
function dropshipSyncFighters($conn, $discord_id) {
	$sk = dropshipSkullianceDb();
	if (!$sk) return;
	$did = $sk->real_escape_string((string)$discord_id);
	$res = @$sk->query(
		"SELECT f.id, f.serial, f.name, f.traits
		   FROM dhc_fighters f
		   INNER JOIN users u ON u.id = f.user_id
		  WHERE u.discord_id = '$did' AND f.invalid = 0
		  ORDER BY f.serial ASC");
	$rows = array();
	if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;
	$sk->close();
	if (!$rows) return;

	/* The renderer lives on the platform side and is only needed here. A
	   missing file means no picture, never a fatal mid-sync. */
	if (!function_exists('dhcf_render_fighter')
	    && is_file(__DIR__ . '/../dhcfighters-notify.php')) {
		@require_once __DIR__ . '/../dhcfighters-notify.php';
	}

	$armor_weight = array('Base' => 0, 'Light' => 2, 'Medium' => 4, 'Heavy' => 6);
	$gear_weight  = array('None' => 1, 'Melee' => 2, 'Demolition' => 3, 'Medkit' => 4);
	$pid = (int)($_SESSION['userData']['dropship_project_id'] ?? 0);

	$asset_names = array();
	foreach ($rows as $r) {
		$traits = json_decode((string)$r['traits'], true);
		if (!is_array($traits)) continue;
		/* KEYED ON THE FIGHTER ROW ID, not the serial. Serials continue the
		   collection's numbering and a Fighter can be renamed; the id is
		   the only thing that cannot move under a soldier row. */
		$asset = dropshipFighterPrefix() . (int)$r['id'];
		$asset_names[] = $asset;

		$kit   = dhc2FighterStats($traits);
		$level = $armor_weight[$kit['armor']] + $gear_weight[$kit['gear']];
		$label = trim((string)$r['name']) !== ''
			? (string)$r['name'] : ('Fighter #' . (int)$r['serial']);
		$img = function_exists('dhcf_render_fighter')
			? (string)dhcf_render_fighter($traits, (int)$r['serial']) : '';

		$a = $conn->real_escape_string($asset);
		if (!checkSoldier($conn, $asset)) {
			createSoldier($conn, $asset, $label, null, DROPSHIP_DEFAULT_RANK,
			              $kit['armor'], $kit['gear'], $level, $img);
		} else {
			/* Traits can change -- a Fighter is editable -- so armour, gear
			   and the picture are all refreshed, exactly as the NFT sync
			   refreshes its own. */
			$conn->query(sprintf(
				"UPDATE soldiers SET name = '%s', armor = '%s', gear = '%s', level = '%d', ipfs = '%s'
				  WHERE asset_name = '%s' AND project_id = '%d'",
				$conn->real_escape_string($label),
				$conn->real_escape_string($kit['armor']),
				$conn->real_escape_string($kit['gear']),
				$level, $conn->real_escape_string($img), $a, $pid));
		}
	}

	if ($asset_names) updateSoldiers($conn, implode("', '", $asset_names));
}
