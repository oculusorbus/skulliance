<?php
/* dhc-assembler-harness.php — CLI only.
 *
 * dhc-assembler.php is included by two pages that treat it very differently,
 * and both of the things checked here have already broken once:
 *
 *  1. IT LEAKS NOTHING. It is included at GLOBAL scope, so any bare variable it
 *     leaves behind is a global by the time header.php runs. It left $name --
 *     a trait's display name -- and dhcsandbox.php, the one page that includes
 *     it BEFORE the header, printed "U. Vigilance Device #2" where the signed-in
 *     player's username belongs.
 *
 *  2. MODE AND STANDALONE ARE SEPARATE. $dhca_mode is what it can DO (every
 *     trait, draggable layers) and $dhca_standalone is what it LOOKS like and
 *     how it lays out. They were one flag, which made "the sandbox's powers in
 *     Skulliance chrome" inexpressible. Recoupling them would silently give the
 *     integrated sandbox its old ash-and-ochre skin back.
 *
 * Usage: php dhc-assembler-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$rarity = require __DIR__ . '/dhcrarity.php';
if (!function_exists('dhcf_rarity')) { function dhcf_rarity(){ global $rarity; return $rarity; } }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/**
 * ONE SUBPROCESS PER RUN. The assembler declares dhc_title() unguarded, so it
 * can only be included once in a process -- which is exactly right for a page
 * and useless for a loop. Shelling out also makes each case a genuinely fresh
 * request rather than one contaminated by the last, which is the thing being
 * checked. Adding function_exists() guards purely so a test could loop would be
 * changing shipped code to suit the test.
 *
 * Returns array(output, clobbered-variable-names).
 */
function run_assembler($mode, $standalone) {
	$script = '
		$rarity = require ' . var_export(__DIR__ . '/dhcrarity.php', true) . ';
		function dhcf_rarity(){ global $rarity; return $rarity; }
		$seed = array("name"=>"__PLAYER__","avatar_url"=>"__AVATAR__","title"=>"__TITLE__",
		              "url"=>"__URL__","user"=>"__USER__","id"=>"__ID__",
		              "status"=>"__STATUS__","member"=>"__MEMBER__");
		extract($seed);
		$dhca_mode = ' . var_export($mode, true) . ';
		$dhca_owned = null;
		$dhca_standalone = ' . ($standalone ? 'true' : 'false') . ';
		ob_start(); include ' . var_export(__DIR__ . '/dhc-assembler.php', true) . '; $out = ob_get_clean();
		$bad = array();
		foreach ($seed as $k => $v) if (isset($$k) && $$k !== $v) $bad[] = $k;
		echo json_encode(array("out"=>$out, "bad"=>$bad));
	';
	$cmd = PHP_BINARY . ' -d error_reporting=0 -r ' . escapeshellarg($script) . ' 2>/dev/null';
	$raw = shell_exec($cmd);
	$j = json_decode((string)$raw, true);
	if (!is_array($j)) { echo "  (subprocess produced nothing for $mode/$standalone)\n"; return array('', array()); }
	return array($j['out'], $j['bad']);
}

/* ---------- 1. nothing a host page relies on is clobbered ------------------- */
list($out, $clobbered) = run_assembler('sandbox', false);
ok(!$clobbered, 'the assembler clobbers host variables: '.implode(', ', $clobbered));
printf("globals: %s\n", $clobbered
	? 'LEAKED '.implode(', ', $clobbered)
	: 'nothing leaked (checked name, avatar_url, title, url, user, id, status, member)');

/* ---------- 2. behaviour and appearance are independent --------------------- */
$want = array(
	// mode       standalone  palette  ownsBody  reorder
	array('sandbox',  true,  'ash',  true,  'true'),
	array('sandbox',  false, 'navy', false, 'true'),
	array('fighters', false, 'navy', false, 'false'),
	array('fighters', true,  'ash',  true,  'false'),
);
foreach ($want as $w) {
	list($mode, $sa, $pal, $body, $reorder) = $w;
	list($o, ) = run_assembler($mode, $sa);
	$gotPal  = strpos($o, '--ink:#100d0d') !== false ? 'ash' : 'navy';
	$gotBody = (bool)preg_match('/html,body\{margin:0;height:100%\}/', $o);
	$gotRe   = preg_match('/ALLOW_REORDER = (\w+)/', $o, $m) ? $m[1] : '?';
	$good = ($gotPal === $pal && $gotBody === $body && $gotRe === $reorder);
	printf("  mode=%-9s standalone=%-5s -> %-4s  ownsBody=%-5s  reorder=%-5s  %s\n",
		$mode, $sa ? 'true' : 'false', $gotPal,
		$gotBody ? 'true' : 'false', $gotRe, $good ? 'ok' : 'WRONG');
	ok($good, "mode=$mode standalone=".($sa?'true':'false')
	        ." gave $gotPal/".($gotBody?'body':'nobody')."/$gotRe");
}
/* The fourth row is the one that proves they are INDEPENDENT rather than merely
   renamed: 'fighters' with standalone=true is a combination the old single flag
   could not express at all. */
echo "  (row 4 is the combination the single flag could not express)\n";

/* ---------- 3. the tabs are still complete ---------------------------------- */
$src = file_get_contents(__DIR__ . '/dhc-assembler.php');
preg_match('/\$dhc_slots = array\((.*?)\n\);/s', $src, $m1);
preg_match('/\$dhc_tab_order = array\((.*?)\);/s', $src, $m2);
preg_match_all("/'([a-zA-Z0-9]+)'\s*=>\s*array\(/", $m1[1], $sm);
preg_match_all("/'([a-zA-Z0-9]+)'/", $m2[1], $tm);
$draw = $sm[1]; $tabs = $tm[1];
ok(!array_diff($draw, $tabs), 'a slot has no tab: '.implode(',', array_diff($draw, $tabs)));
ok(!array_diff($tabs, $draw), 'a tab has no slot: '.implode(',', array_diff($tabs, $draw)));
ok($draw[0] === 'background', 'draw order no longer starts at background — layers will composite wrong');
printf("tabs: %d slots, %d tabs, opens on '%s', draws from '%s'\n",
	count($draw), count($tabs), $tabs[0], $draw[0]);

/* ---------------------------------------------------------------------------
 * THE "WHY ARE THESE GREY" BANNER.
 *
 * It used to quote the FIRST blocked tile's own reason, verbatim and with no
 * count. Open the Torso tab with one committed torso among forty free ones
 * and it announced "Your only copy is in a saved Fighter. Disassemble it to
 * free this trait." -- which reads as a statement about the trait you are
 * wearing, not about a tile further down that you never looked at. On a
 * fresh page with a randomised build of traits the player plainly owned, it
 * looked like a straightforward bug.
 *
 * Now: all blocked -> quote the reason (it is true of the whole tab, and is
 * usually a conflict rather than ownership). Some blocked -> a COUNT and no
 * cause, because blockedReason() returns ownership, head/headgear conflicts
 * AND arms/weapon exclusivity, so no single cause can be generalised.
 * ------------------------------------------------------------------------- */
$asm_src = file_get_contents(__DIR__ . '/dhc-assembler.php');
$asm_c   = preg_replace('!/\*.*?\*/!s', '', $asm_src);

ok(strpos($asm_c, 'if (!banner) banner = why;') === false,
   'the banner quotes the first blocked tile again, which reads as a claim about the build');
ok(preg_match('/blockedCount\+\+;\s*if \(!blockedWhy\) blockedWhy = why;/', $asm_c) === 1,
   'the blocked tiles are no longer counted');
ok(strpos($asm_c, "blockedCount + ' of ' + shown + ' unavailable with this build.") !== false,
   'the partial case no longer reports a count');
ok(preg_match('/\(blockedCount >= shown && blockedWhy\)/', $asm_c) === 1,
   'the reason is quoted without checking it applies to every tile on the tab');
/* The count must not name a cause: ownership is only one of three. */
$banner = substr($asm_c, strpos($asm_c, 'n.textContent = (blockedCount'), 260);
ok(stripos($banner, 'saved Fighter') === false,
   'the count names a cause; head/headgear and arms/weapon conflicts block tiles too, so it would be wrong on those tabs');

/* ---------------------------------------------------------------------------
 * ARMS vs AN ARMS-EXCLUSIVE WEAPON
 *
 * The exclusive weapons are drawn against the torso's own arms, so the real
 * question is whether the arm the weapon rests on is still visible -- not
 * whether an Arms trait is present at all. Only arms drawn UNDER the torso
 * pass, and that was established from renders:
 *
 *   perforator (BEHIND_TORSO) sits behind the shoulder and the blaster is
 *   untouched -- it was blocked for nothing.
 *
 *   infested-robo-limb (OVER_TORSO) keeps the native arms but draws over them
 *   AND over the weapon, swallowing the blaster's grip and trigger guard, so
 *   the gun reads as embedded rather than held. An earlier version of this
 *   rule exempted everything dhcf_armless_mode() calls 'none', which would
 *   have shipped exactly that.
 * ------------------------------------------------------------------------- */
require_once __DIR__ . '/dhcfighters-config.php';

ok(dhcf_arms_weapon_clash('perforator-arm-replacement', 'plastic-blaster') === false,
   'a behind-torso arm is still blocked from an exclusive weapon');
ok(dhcf_arms_weapon_clash('infested-robo-limb', 'plastic-blaster') === true,
   'an over-torso arm is allowed with an exclusive weapon, and it covers the grip');
ok(dhcf_arms_weapon_clash('head-chopper', 'plastic-blaster') === true,
   'a hybrid-armless arm is allowed with an exclusive weapon');
ok(dhcf_arms_weapon_clash('connected-protector-limbs', 'plastic-blaster') === true,
   'a full-armless arm is allowed with an exclusive weapon');
ok(dhcf_arms_weapon_clash('connected-protector-limbs', 'axe') === false,
   'a NON-exclusive weapon is blocked by arms');
ok(dhcf_arms_weapon_clash('', 'plastic-blaster') === false
   && dhcf_arms_weapon_clash('connected-protector-limbs', '') === false,
   'an empty slot counts as a clash');
/* Every exclusive weapon, not just the one that prompted this. */
foreach (DHCF_ARMS_EXCLUSIVE as $w) {
	ok(dhcf_arms_weapon_clash('perforator-arm-replacement', $w) === false,
	   "perforator is still blocked from $w");
	ok(dhcf_arms_weapon_clash('connected-protector-limbs', $w) === true,
	   "a full-armless arm is allowed with $w");
}

/* THE SERVER IS THE GATE. This rule lived only in the assembler's JavaScript,
   so unlike the headgear rule beside it a stale tab or hand-edited link could
   commit a Fighter the renderers then draw wrong. */
$lib_src = file_get_contents(__DIR__ . '/dhcfighters-lib.php');
ok(substr_count($lib_src, 'dhcf_arms_weapon_clash(') === 2,
   'the save and edit paths do not BOTH enforce the arms/weapon rule');
ok(strpos($lib_src, 'dhcf_headgear_blocked(') !== false,
   'the headgear rule lost its server-side check');

/* And the browser must apply the same pair rule, or it offers tiles the
   server will refuse. */
ok(strpos($asm_c, 'function armsWeaponClash(arms, weapon)') !== false,
   'the assembler does not have the pairwise check');
/* Populated FROM the constant, not merely declared. Stubbing it to [] leaves
   the name in place and silently restores the blanket block in the browser
   while the server still allows the pair -- the two then disagree, which is
   the one outcome this whole change exists to avoid. */
ok(strpos($asm_src, 'var ARMS_BEHIND_TORSO = <?php echo json_encode(DHCF_ARMS_BEHIND_TORSO); ?>;') !== false,
   'the assembler does not take its exempt-arms list from DHCF_ARMS_BEHIND_TORSO');
ok(preg_match('/\bsel\.arms && armsExclusive\(/', $asm_c) !== 1,
   'a call site still blocks on the weapon alone, ignoring which arm it is');

/* ---------------------------------------------------------------------------
 * A HEAD THAT REFUSES A COMPANION
 *
 * The Demon grips the torso and the bare skull. On a Beheaded Cyborg there is
 * no skull to grip, so dhcf_layers() drops BOTH halves of it -- and a dropped
 * trait is one the player paid for and cannot see. The picker has to grey the
 * tile and the save has to refuse it, in both directions, because either slot
 * can be filled first.
 * ------------------------------------------------------------------------- */
foreach (DHCF_COMPANION_EXCLUDED_BY_HEAD as $head => $comps) {
	foreach ($comps as $comp) {
		ok(dhcf_companion_blocked($head, $comp) === true,
		   "$head no longer refuses $comp");
	}
}
ok(dhcf_companion_blocked('', 'demon-companion') === false,
   'an empty head counts as a refusal, which would block the tile on a fresh build');
ok(dhcf_companion_blocked('beheaded-cyborg', '') === false,
   'an empty companion counts as a refusal');

/* Extracted per function, so dropping the check from ONE of the two save
   paths fails here -- a count over the whole file would not. */
foreach (array('dhcf_save_fighter', 'dhcf_update_fighter') as $fn) {
	$at = strpos($lib_src, 'function ' . $fn . '(');
	$body = $at === false ? '' : substr($lib_src, $at, 4000);
	ok($at !== false, "found $fn() in dhcfighters-lib.php");
	ok(strpos($body, 'dhcf_companion_blocked(') !== false,
	   "$fn() does not refuse a companion the head cannot hold, so a stale tab can commit one");
}
/* Both directions in the picker: whichever slot the player fills first has to
   rule the other one out, or the tab they open second offers a pairing the
   save will then reject. */
ok(preg_match("/key === 'companion' && companionBlocked\(sel\.head, slug\)/", $asm_c) === 1,
   'the Companion tab does not grey companions the chosen head refuses');
ok(preg_match("/key === 'head' && companionBlocked\(slug, sel\.companion\)/", $asm_c) === 1,
   'the Head tab does not grey heads that refuse the chosen companion');
ok(preg_match('/var COMPANION_BLOCKED\s*=\s*<\?php echo json_encode\(DHCF_COMPANION_EXCLUDED_BY_HEAD\);/', $asm_src) === 1,
   'the assembler does not take its refusal map from DHCF_COMPANION_EXCLUDED_BY_HEAD');

/* ---------------------------------------------------------------------------
 * ART, RARITY AND NAME MUST DESCRIBE THE SAME SET.
 *
 * Three files have to agree and none of them is generated from the others:
 * the PNGs under dhc/web/1000 (uploaded by FTP, gitignored), dhcrarity.php
 * (regenerated from chain by gen-rarity.py) and dhc/web/trait-index.json (the
 * display names, written when art is imported).
 *
 * All three were out of step after the last drop and nothing noticed. Art with
 * no rarity row is UNDROPPABLE -- dhcf_draw() pools from the rarity table, so
 * the piece exists, renders, and can never be earned. A rarity row with no art
 * is worse: it drops, and the player owns a layer that draws nothing. A
 * missing NAME is the quiet one -- dhcf_trait_name() falls back to the slug,
 * so ten new traits shipped reading "Dh Samurai Helmet" and "Dhc2 Comic Cover
 * 4" in the picker, the modal, the Discord post and the share text.
 *
 * SKIPPED ENTIRELY WITHOUT THE ART, because dhc/ is gitignored: a checkout
 * with no trait art must not fail this, or the harness is unrunnable anywhere
 * but a machine that has FTP'd the collection down.
 * ------------------------------------------------------------------------- */
$artRoot = __DIR__ . '/dhc/web/1000';
$idxFile = __DIR__ . '/dhc/web/trait-index.json';
if (is_dir($artRoot) && is_file($idxFile)) {
	$rar = require __DIR__ . '/dhcrarity.php';
	$idx = json_decode(file_get_contents($idxFile), true);
	ok(is_array($idx) && $idx, 'trait-index.json did not parse, so every trait falls back to its slug');
	/* NOT categories. torso-noarms and companion-front are derived art keyed by
	   another trait's slug -- they have no rarity, no name and no slot, and
	   counting them here would demand rows that must never exist. */
	/* neckfill joins them: it is a single shared shape keyed to nothing at all
	   -- not a trait, not equippable, not scored -- and demanding a rarity row
	   and a display name for it would be demanding rows that must never exist. */
	$derived = array('torso-noarms' => 1, 'companion-front' => 1, 'neckfill' => 1);
	$noRarity = array(); $noName = array(); $noArt = array();
	foreach (glob($artRoot . '/*', GLOB_ONLYDIR) as $dir) {
		$cat = basename($dir);
		if (isset($derived[$cat])) continue;
		foreach (glob($dir . '/*.png') as $png) {
			$slug = basename($png, '.png');
			if (!isset($rar[$cat][$slug]))          $noRarity[] = "$cat/$slug";
			if (!isset($idx[$cat][$slug]['name']))  $noName[]   = "$cat/$slug";
		}
	}
	foreach ($rar as $cat => $rows) {
		foreach ($rows as $slug => $_) {
			if (!is_file($artRoot . '/' . $cat . '/' . $slug . '.png')) $noArt[] = "$cat/$slug";
		}
	}
	ok(!$noRarity, 'art with no rarity row, so it can never drop: ' . implode(', ', $noRarity));
	ok(!$noArt,    'rarity row with no art, so it drops and draws nothing: ' . implode(', ', $noArt));
	ok(!$noName,   'art with no display name, so it shows as its slug: ' . implode(', ', $noName));
	$nTraits = 0;
	foreach ($rar as $rows) $nTraits += count($rows);
	printf("art/rarity/name: %d traits, all three files agree\n", $nTraits);

	/*
	 * A BACKGROUND MUST NOT BE FLATTENED INTO BANDS.
	 *
	 * Backgrounds are the one layer that is full-frame and carries a smooth
	 * gradient across hundreds of pixels. Quantised to 256 colours with no
	 * dithering that gradient becomes flat plateaus with a visible step
	 * between them -- and PIL's FASTOCTREE does not even spend the 256 it is
	 * given. Reported off a screenshot of the XLONs Room Entrance, whose green
	 * doorway glow banded into rings, after it had been true of the whole set
	 * for months with nothing to say so.
	 *
	 * COLOURS USED, NOT THE PALETTE DECLARED, and that distinction is the
	 * whole check. imagecolorstotal() returns 256 for a FASTOCTREE file that
	 * only ever puts 90 of them on the canvas, so a version of this built on
	 * it passed the very mutation it existed to catch. Counting the values
	 * actually present separates the two with room to spare: measured across
	 * all 45, dithered output uses at least 247 and FASTOCTREE at most 160.
	 *
	 * READ AT 250px, where an exact count is 62,500 pixels rather than a
	 * million, and the two populations are just as far apart.
	 *
	 * SOLID BACKGROUNDS ARE EXEMPT BY MEASUREMENT, NOT BY NAME: a flat colour
	 * has nothing to dither and stays on one or two values. The threshold sits
	 * in the empty space between that and the 247.
	 */
	$banded = array();
	foreach ((array)glob(__DIR__ . '/dhc/web/250/background/*.png') as $png) {
		$im = @imagecreatefrompng($png);
		if (!$im) continue;
		$seen = array(); $w = imagesx($im); $h = imagesy($im);
		for ($y = 0; $y < $h; $y++) {
			for ($x = 0; $x < $w; $x++) $seen[imagecolorat($im, $x, $y)] = 1;
		}
		imagedestroy($im);
		$n = count($seen);
		if ($n > 2 && $n < 200) $banded[] = basename($png, '.png') . " ($n)";
	}
	ok(!$banded,
	   'these backgrounds put only a handful of colours on the canvas, which renders a '
	 . 'gradient as visible bands -- rebuild them with dhc/make-web.py, which dithers: '
	 . implode(', ', $banded));
} else {
	echo "art/rarity/name: SKIPPED (no dhc/web art in this checkout)\n";
}

/* ---------------------------------------------------------------------------
 * A RARER TRAIT MUST DROP LESS OFTEN THAN A COMMONER ONE.
 *
 * The one property the rarity labels promise, and nothing checked it. The
 * table was built by giving each TIER a share of the category and splitting it
 * among its members -- which is only the same as a per-trait weight when the
 * tiers hold similar numbers. The DH Samurai Suit was the only legendary
 * torso, so it took all 9% and dropped more often than any individual common
 * torso at 5.50%. Headgear had the mirror: one common taking all 44%. Eight of
 * 65 tier comparisons were inverted, the worst an uncommon weapon at 28.57%
 * against a common at 14.97%.
 *
 * gen-rarity.py reads those numbers as per-trait WEIGHTS now, so the property
 * holds by construction. This asserts it anyway, per category, because the
 * next change to that script will not be thinking about it.
 * ------------------------------------------------------------------------- */
$rarAll = require __DIR__ . '/dhcrarity.php';
$tierOrder = array('common', 'uncommon', 'epic', 'legendary', 'mythic');
$inversions = array();
foreach ($rarAll as $cat => $rows) {
	/* One rate per tier -- every member of a tier shares it, which is itself
	   worth confirming: a tier whose members differ would mean the weight was
	   not applied uniformly. */
	$byTier = array();
	foreach ($rows as $slug => $info) {
		$t = $info[0]; $rate = (float)$info[2];
		if (isset($byTier[$t]) && abs($byTier[$t] - $rate) > 0.011) {
			$inversions[] = "$cat/$t has two different rates ({$byTier[$t]} and $rate)";
		}
		$byTier[$t] = $rate;
	}
	$present = array();
	foreach ($tierOrder as $t) if (isset($byTier[$t])) $present[$t] = $byTier[$t];
	$keys = array_keys($present);
	for ($i = 0; $i < count($keys); $i++) {
		for ($j = $i + 1; $j < count($keys); $j++) {
			if ($present[$keys[$j]] > $present[$keys[$i]]) {
				$inversions[] = sprintf('%s: %s drops at %.2f%% but %s only %.2f%%',
					$cat, $keys[$j], $present[$keys[$j]], $keys[$i], $present[$keys[$i]]);
			}
		}
	}
}
ok(!$inversions, "a rarer tier drops MORE often than a commoner one:\n        "
   . implode("\n        ", $inversions));
printf("rarity: %d categories, every tier rarer than the one above it\n", count($rarAll));

/* And nothing that is not a slot may be in the table at all. torso-noarms,
   companion-front and neckfill are derived art; neckfill reached it once and
   was scored mythic at 0.24% -- a drop nobody can receive for a slot nobody
   can fill. */
foreach (array('neckfill', 'torso-noarms', 'companion-front') as $notASlot) {
	ok(!isset($rarAll[$notASlot]),
	   "$notASlot is in the rarity table; it is derived art, not a trait");
}

/* ---------- 5. a comic cover is offered in Effects 2 only ------------------- */
/*
 * READ OFF THE RENDERED PAGE, NOT OFF THE SOURCE ARRAY. The picker draws
 * from `var TRAITS = {...}`, so parsing that out of the captured output
 * tests the filter that actually runs. A check that re-read the PHP array
 * would have agreed with the bug.
 *
 * THE BUG THIS CATCHES SHIPPED. dhc-assembler.php kept its own list of
 * three covers while DHCF_COVER_EFFECTS grew to four -- dhc-layerorder-
 * harness.php checks that constant against the art on disk, and nothing
 * checked this one. So DHC2 Comic Cover #4 was offered in Effects 1, the
 * lower slot, where a second effect draws on top of a full-frame cover.
 * That is the single combination the Effects-2-only rule exists to make
 * impossible, and it was reachable for exactly one of the four covers.
 */
if (preg_match('/var TRAITS = (\{.*?\});\s*$/m', $out, $tm3)) {
	$TR = json_decode($tm3[1], true);
	$slugs = function ($key) use ($TR) {
		$o = array();
		foreach (isset($TR[$key]) ? $TR[$key] : array() as $t)
			if (isset($t['slug'])) $o[] = $t['slug'];
		return $o;
	};
	$e1 = $slugs('effects1'); $e2 = $slugs('effects2');
	/* Not vacuous: if the art is absent both lists are empty and every
	   assertion below passes for the wrong reason. */
	ok(count($e1) > 0 && count($e2) > 0,
	   'the rendered page offers no effects at all, so the cover checks below prove nothing');
	$covers = DHCF_COVER_EFFECTS;
	ok(count($covers) >= 4,
	   'DHCF_COVER_EFFECTS lists only ' . count($covers) . ' covers; the art folder has four');
	$inLower = array_values(array_intersect($covers, $e1));
	ok(!$inLower,
	   'a comic cover is selectable in Effects 1, where a second effect can be drawn over '
	 . 'a full-frame cover: ' . implode(', ', $inLower));
	$missing = array_values(array_diff($covers, $e2));
	ok(!$missing,
	   'a comic cover is not offered in Effects 2 either, so it cannot be used at all: '
	 . implode(', ', $missing));
	/* And the filter must take exactly the covers out, not a slot's worth of
	   effects -- the two lists come from one art folder. */
	ok(array_values(array_diff($e2, $e1)) === array_values($covers),
	   'Effects 2 differs from Effects 1 by something other than the covers: '
	 . implode(', ', array_diff($e2, $e1)) . ' vs ' . implode(', ', $covers));
	printf("covers: %d, Effects 1 offers %d effects, Effects 2 offers %d\n",
	       count($covers), count($e1), count($e2));
} else {
	echo "covers: SKIPPED (no TRAITS block in the rendered page)\n";
}

/* -------------------------------------------------------------------------
 * TETHERED WEAPONS MUST SPLIT ACROSS THE TWO WEAPON SLOTS, 2026-10-30.
 *
 * A tethered pair is one weapon drawn in two places: the weapon behind the
 * body and the piece it hangs from in front. That only works if exactly one
 * half is in $dhc_weapon_front -- both in front and the back is empty, both in
 * back and they are drawn on top of each other.
 *
 * AND IT IS HOW THE COUPLE TABLE TELLS THE HALVES APART. That loop used to
 * test for a '-sash' suffix, which was true of the only two pairs that
 * existed. The straps delivered on 2026-10-30 end in '-strap', so the suffix
 * test passed both directions of each new pair through and emitted SIX couple
 * rows for four pairs -- two of them with back and front swapped, which in the
 * browser means picking the strap drags the weapon into the front slot. It now
 * asks this list, so these checks are what keeps the two from drifting.
 * ------------------------------------------------------------------------- */
echo "\ntethered weapon halves\n";
$front = array();
if (preg_match('/\$dhc_weapon_front = array\((.*?)\);/s', $asm_src, $m)) {
	preg_match_all("/'([a-z0-9-]+)'/", $m[1], $mm);
	$front = $mm[1];
}
ok(count($front) >= 9, 'could not read $dhc_weapon_front out of the assembler');

$pairs = array();
foreach (DHCF_TETHERED as $a => $b) {
	$k = $a < $b ? "$a|$b" : "$b|$a";
	$pairs[$k] = true;
	/* Keyed both ways or the assembler only couples in one direction. */
	ok(isset(DHCF_TETHERED[$b]) && DHCF_TETHERED[$b] === $a,
	   "DHCF_TETHERED has $a => $b but not the reverse, so the pair only couples one way");
}
foreach (array_keys($pairs) as $k) {
	list($x, $y) = explode('|', $k);
	$n = (int)in_array($x, $front, true) + (int)in_array($y, $front, true);
	ok($n === 1, "tethered pair $x / $y has $n halves in the front weapon list, not exactly 1 -- "
	           . ($n ? 'both draw in front and nothing goes behind the body'
	                 : 'both draw behind the body, on top of each other'));
}
/*
 * THE ASSEMBLER'S OWN TEST, asserted on its source -- because the loop below
 * is a REIMPLEMENTATION of it, and a reimplementation cannot notice the
 * original drifting. Restoring the '-sash' suffix test in dhc-assembler.php
 * passed every check in this section until these two were added, which is the
 * exact bug the section exists for.
 */
ok(preg_match('/foreach \(DHCF_TETHERED as \$back => \$front\)[^}]*?'
            . 'in_array\(\$back, \$dhc_weapon_front, true\)/s', $asm_src) === 1,
   'the COUPLE emission does not tell a tethered pair\'s halves apart by the front-weapon '
 . 'list, so the two can disagree about which half draws in front');
ok(!preg_match('/-sash\x27\)?\s*\)?\s*continue/', $asm_src)
   && strpos($asm_src, "substr(\$back, -5)") === false,
   'the COUPLE emission still tells front from back by a \'-sash\' suffix. That is true of '
 . 'the two sashes and false of every strap, so each strap pair is emitted twice -- once '
 . 'with the halves swapped, which drags the weapon into the front slot');

/* The emission, run here against the same data, so a duplicate or a swapped
   row fails rather than being inspected. */
$emitted = array();
foreach (DHCF_TETHERED as $back => $fr) {
	if (in_array($back, $front, true)) continue;
	$emitted[] = array($back, $fr);
}
ok(count($emitted) === count($pairs),
   'the COUPLE table emits ' . count($emitted) . ' mutual rows for ' . count($pairs)
 . ' tethered pairs; each pair must appear exactly once');
foreach ($emitted as $e) {
	ok(!in_array($e[0], $front, true) && in_array($e[1], $front, true),
	   "COUPLE row back={$e[0]} front={$e[1]} has the halves the wrong way round");
}
/*
 * THE MANIFEST AND THE CONSTANT MUST NAME THE SAME PAIRS.
 *
 * This is the check that was missing. LAYER-MANIFEST.json has recorded all
 * seven composite weapons since the first pass -- including dh-spike-blaster,
 * lil-fren and mega-taser-cannon -- while DHCF_TETHERED enforced four. The
 * three Maxingo delivered PRE-SPLIT as `X.png` and `X 1.png` were never
 * tethered, because the pairs that got tethered were the ones somebody had to
 * separate by hand, and separating them was the moment you noticed. So the
 * draw handed out lone bandoliers for as long as those weapons have existed,
 * and the assembler let one be worn with no gun behind it. Reported by the
 * owner, not by anything here.
 *
 * The manifest is the decision record and the constant is what runs, so a
 * pair in one and not the other is a decision nobody implemented.
 */
$manFile = __DIR__ . '/dhc/LAYER-MANIFEST.json';
if (is_file($manFile)) {
	$man = json_decode((string)file_get_contents($manFile), true);
	$manPairs = array();
	foreach ((array)($man['composite_weapons']['pairs'] ?? array()) as $pr) {
		if (count($pr) !== 2) continue;
		$manPairs[$pr[0] < $pr[1] ? "$pr[0]|$pr[1]" : "$pr[1]|$pr[0]"] = true;
	}
	ok((bool)$manPairs, 'no composite_weapons.pairs in LAYER-MANIFEST.json to check against');
	$onlyMan = array_diff_key($manPairs, $pairs);
	$onlyCfg = array_diff_key($pairs, $manPairs);
	ok(!$onlyMan, 'LAYER-MANIFEST calls these composite weapons but DHCF_TETHERED does not tether '
	            . 'them, so the draw hands out halves on their own: ' . implode(', ', array_keys($onlyMan)));
	ok(!$onlyCfg, 'DHCF_TETHERED tethers these but LAYER-MANIFEST does not record them as composite '
	            . 'weapons, so the decision record is behind the code: ' . implode(', ', array_keys($onlyCfg)));
}

/* Both halves of a pair must score the same tier. They are one weapon, and the
   draw awards them together -- a legendary weapon arriving with a common strap
   would make the pair's real rate the commoner of the two. Guaranteed today by
   the strap being ALIASed to its weapon's on-chain name in gen-rarity.py. */
foreach (array_keys($pairs) as $k) {
	list($x, $y) = explode('|', $k);
	$a = isset($rarity['weapon'][$x]) ? $rarity['weapon'][$x] : null;
	$b = isset($rarity['weapon'][$y]) ? $rarity['weapon'][$y] : null;
	ok($a && $b, "no rarity row for one of $x / $y");
	if ($a && $b) ok($a[0] === $b[0],
	   "tethered halves $x ($a[0]) and $y ($b[0]) score different tiers, so the pair's "
	 . 'real rate is the commoner of the two');
}

/* -------------------------------------------------------------------------
 * A BORROWED ARMLESS PLATE HAS TO EXIST, and the borrower must not have one.
 * ------------------------------------------------------------------------- */
echo "\nborrowed armless plates\n";
$plateDir = __DIR__ . '/dhc/web/1000/torso-noarms';
foreach (DHCF_ARMLESS_ALIAS as $torso => $plate) {
	ok($torso !== $plate, "$torso aliases itself, which is what no entry already means");
	ok(dhcf_armless_slug($torso) === $plate, "dhcf_armless_slug($torso) does not return $plate");
	if (!is_dir($plateDir)) continue;
	ok(is_file("$plateDir/$plate.png"),
	   "$torso borrows $plate's armless plate and that plate is not on disk, so the torso "
	 . 'renders as a missing image the moment an arms trait is picked');
	ok(!is_file("$plateDir/$torso.png"),
	   "$torso has an armless plate of its OWN as well as an alias, so the alias is dead "
	 . 'code and whichever wins is a coin toss');
}
/* The renderer must build the PATH from the alias, not merely consult it: the
   file is named after the plate, so returning "yes there is one" and then
   asking for <borrower>.png is a 404 the page shows as a hole. */
ok(strpos($lib_src, 'torso-noarms/\' . $noarms') !== false,
   'dhcf_layers() still builds the armless path from the torso slug rather than the '
 . 'plate it resolves to');
ok(preg_match("/'slug' => \\\$armless \\? \\\$noarms/", $lib_src) === 1,
   'dhcf_layers() emits the torso slug for the armless layer, so a borrowed plate is '
 . 'looked up under the wrong filename');
/* And the sandbox must pass a MAP. As a list, a borrowed plate cannot be
   expressed at all -- and three readers called indexOf() on it. */
ok(strpos($asm_src, 'JSON_FORCE_OBJECT') !== false,
   'the sandbox serialises NOARMS as a list, which cannot name a borrowed plate');
ok(strpos($asm_c, 'NOARMS.indexOf') === false,
   'something still calls indexOf() on NOARMS, which is an object now and will throw');
ok(strpos($asm_c, 'function armlessPlate(') !== false,
   'the sandbox has no single helper for reading NOARMS, so its three readers can drift');

echo "\n".($fail ? "FAILED: $fail check(s)\n" : "all assembler checks passed\n");
exit($fail ? 1 : 0);
