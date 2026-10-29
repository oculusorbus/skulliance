<?php
/**
 * monstrocity-stats-harness.php — the stats a theme NFT gets.
 *
 * Run: php monstrocity-stats-harness.php
 *
 * This drives the REAL monstrocity_nft_stats() rather than a copy of it. The
 * whole point of the function is the exact numbers it returns, and a copy in
 * here would agree with itself forever while the endpoint drifted.
 */
require_once __DIR__ . '/lib/monstrocity-stats-lib.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

/* Real id shapes, one per chain, exactly as each verifier writes them into
   nfts.asset_id. These are what the old gate silently dropped. */
$chains = array(
	'cardano' => 'asset1w8wgpwcnyqzj0r9z0pdwvvnqhpvxjv5zpq0a4s',
	'solana'  => '7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU',
	'xrpl'    => '000800009A1B2C3D4E5F60718293A4B5C6D7E8F900000000000000000000001A',
	'polygon' => '0xee79e2c0e3b5d2f8a1b4c7d9e0f1a2b3c4d5c5c4:4444',
);

echo "\nevery chain gets a card\n";
foreach ($chains as $chain => $id) {
	$s = monstrocity_nft_stats($id);
	ok(is_array($s), "a $chain NFT gets no stats at all, so the theme drops it");
}

echo "\nstats land in the ranges the character sheet expects\n";
/* monstrocity.php shows strength out of 8 and speed/tactics out of 7, and
   createCharacter() switches on the exact size/type/powerup words. A value
   outside these does not error -- it draws a blank bar or falls through to
   the default 85 health, which is why this is asserted and not eyeballed. */
$sizes    = array('Small', 'Medium', 'Large');
$types    = array('Base', 'Leader', 'Battle Damaged');
$powerups = array('Minor Regen', 'Regenerate', 'Boost Attack', 'Heal');
$bad = array();
foreach ($chains as $chain => $id) {
	/* Many ids per chain, not one: one id exercises one path through the
	   modulo and proves nothing about the range. */
	for ($n = 0; $n < 400; $n++) {
		$s = monstrocity_nft_stats($id . $n);
		if ($s['strength'] < 1 || $s['strength'] > 8) $bad[] = "$chain strength {$s['strength']}";
		if ($s['speed'] < 1 || $s['speed'] > 7)       $bad[] = "$chain speed {$s['speed']}";
		if ($s['tactics'] < 1 || $s['tactics'] > 7)   $bad[] = "$chain tactics {$s['tactics']}";
		if (!in_array($s['size'], $sizes, true))      $bad[] = "$chain size {$s['size']}";
		if (!in_array($s['type'], $types, true))      $bad[] = "$chain type {$s['type']}";
		if (!in_array($s['powerup'], $powerups, true)) $bad[] = "$chain powerup {$s['powerup']}";
	}
}
ok(empty($bad), 'out of range: ' . implode(', ', array_slice($bad, 0, 4)));

echo "\nthe same NFT always rolls the same card\n";
/* Stats are not stored anywhere. If they moved between requests a player's
   roster would reshuffle on every theme switch. */
$a = monstrocity_nft_stats($chains['solana']);
$b = monstrocity_nft_stats($chains['solana']);
ok($a === $b, 'two calls for one NFT disagree, so a roster is not stable');

echo "\nsize no longer decides type\n";
/*
 * THE BUG THIS PINS: size and type were both crc32($seed) % 3 over
 * three-element maps, so the indexes were always equal -- every Small was
 * Base, every Medium a Leader, every Large Battle Damaged. Three outcomes
 * out of nine, and Medium+Leader (100 health, no tactics penalty) was
 * strictly the best card a third of any collection could roll.
 *
 * Asserted as the JOINT distribution, because checking "some NFT is a Large
 * Leader" passes on a coincidence. All nine pairings have to occur.
 */
$seen = array();
for ($n = 0; $n < 1500; $n++) {
	$s = monstrocity_nft_stats($chains['solana'] . $n);
	$seen[$s['size'] . '/' . $s['type']] = true;
}
$missing = array();
foreach ($sizes as $sz) foreach ($types as $ty) {
	if (!isset($seen[$sz . '/' . $ty])) $missing[] = "$sz/$ty";
}
ok(count($seen) === 9 && empty($missing),
   'size and type are still locked together; never saw ' . implode(', ', $missing));

echo "\nCardano stats have not moved\n";
/*
 * REGRESSION, AND THE REASON THE 5-CHARACTER STRIP SURVIVED. Which bytes get
 * hashed decides the numbers, so seeding from the full `asset1...` string
 * would re-roll strength, speed and tactics for every Cardano NFT already in
 * a theme -- every roster on the platform changing overnight for a fix that
 * was supposed to be additive. These values were captured by running the OLD
 * algorithm before any of it was touched.
 */
$baseline = array(
	'asset1w8wgpwcnyqzj0r9z0pdwvvnqhpvxjv5zpq0a4s' => array(6, 7, 6, 'Medium'),
	'asset1qz9x7v2m4k6n8p0r3t5w7y9a1c3e5g7i9k1m3o' => array(8, 6, 3, 'Small'),
	'asset14xyz7890abcdefghijklmnopqrstuvwxyz1234' => array(4, 1, 3, 'Small'),
	'asset1aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa' => array(8, 6, 5, 'Small'),
);
foreach ($baseline as $id => $want) {
	$s = monstrocity_nft_stats($id);
	ok((int)$s['strength'] === $want[0] && (int)$s['speed'] === $want[1]
	   && (int)$s['tactics'] === $want[2] && $s['size'] === $want[3],
	   'a Cardano NFT re-rolled: ' . substr($id, 0, 14) . ' was '
	 . implode('/', $want) . ', is now ' . $s['strength'] . '/' . $s['speed']
	 . '/' . $s['tactics'] . '/' . $s['size']);
}
/* The strip itself, stated outright: it is the one thing holding the above. */
ok(monstrocity_nft_seed('asset1abc') === '1abc',
   'the Cardano 5-character strip changed, which re-rolls every Cardano NFT');
ok(monstrocity_nft_seed('7xKXtg2CW87d') === '7xKXtg2CW87d',
   'a non-Cardano id is being truncated, so its stats come from the wrong bytes');

echo "\nnothing to hash is the only rejection left\n";
ok(monstrocity_nft_stats('') === null, 'an empty asset_id is accepted and hashes nothing');
ok(monstrocity_nft_stats('asset') === null, 'a bare "asset" prefix with no id behind it is accepted');

echo "\nthe endpoint actually calls the library\n";
/* A perfect library nothing uses fixes no bug. Read from source: the
   endpoint needs a DB and a session to run. */
$ep = file_get_contents(__DIR__ . '/ajax/get-nft-assets.php');
ok(strpos($ep, "require_once __DIR__ . '/../lib/monstrocity-stats-lib.php';") !== false,
   'get-nft-assets.php does not include the stats library');
ok(preg_match('~monstrocity_nft_stats\(\s*\$asset_id\s*,~', $ep) === 1,
   'get-nft-assets.php does not call monstrocity_nft_stats()');
/* AND HANDS IT THE TRAITS AND THE POLICY. The mapped path cannot run
   without both, and the failure is silent: a mapped collection would
   quietly keep hashing and nobody would see anything wrong. */
ok(strpos($ep, 'nft_metadata_traits($metadata)') !== false,
   'the endpoint does not pass the NFT metadata, so a mapped collection still hashes');
ok(preg_match('~monstrocity_nft_stats\([^;]*\$policy\)~s', $ep) === 1,
   'the endpoint does not pass the policy, so nothing can ever match a map');
ok(strpos($ep, 'nfts.metadata,') !== false,
   'the endpoint does not SELECT nfts.metadata');
ok(preg_match('~bind_result\(\$policy, \$name, \$ipfs, \$asset_id, \$metadata,~', $ep) === 1,
   'bind_result does not match the SELECT, so every column after it is shifted');
/* THE GATE IS GONE, not merely bypassed. */
ok(strpos($ep, "strpos(\$asset_id, 'asset') !== 0") === false,
   'the Cardano-only asset_id gate is still in the endpoint, still dropping other chains');
ok(strpos($ep, 'substr($asset_id, 5)') === false,
   'the endpoint still strips the prefix itself instead of leaving it to the library');
/* And the payload carries the derived values, not leftover locals. */
foreach (array('strength', 'speed', 'tactics', 'size', 'type', 'powerup') as $k) {
	ok(strpos($ep, "'$k' => \$stats['$k']") !== false,
	   "the endpoint does not send the derived $k");
}

/* ------------------------------------------------------------------ *
 * A MAPPED COLLECTION READS ITS OWN TRAITS
 * ------------------------------------------------------------------ */
echo "\nthe Ancestors map\n";
$ANC = '3966cf1c948109e34f2c5a9f9670445ccc85008e5b8a6e67f913b491';
$map = monstrocity_trait_map($ANC);
ok(is_array($map), 'the Ancestors policy is not mapped');
$cen = $map ? monstrocity_trait_census($map['slug']) : null;
ok(is_array($cen), 'the Ancestors census is missing or unreadable');
ok($cen && $cen['policy'] === $ANC,
   'the census is for a different policy than the map it serves');

/*
 * REAL ANCESTORS, PINNED. Fifteen of them: one per species, the three
 * 1-of-1s, the leanest and the most loaded, and both spellings of the
 * scythe. The whole value of the feature is the exact card a given NFT
 * plays, so a check that only asserted "some array came back" would pass
 * through any change to any weight.
 */
$fx = json_decode((string)@file_get_contents(__DIR__ . '/monstrocity-ancestors-fixture.json'), true);
ok(is_array($fx) && count($fx) >= 12, 'the Ancestors fixture is missing');
$bad = array();
foreach ((array)$fx as $f) {
	$got = monstrocity_trait_stats($ANC, $f['traits']);
	if ($got !== $f['want']) $bad[] = $f['asset'] . ': ' . json_encode($got);
}
ok(!$bad, "a pinned Ancestor plays a different card now:\n        " . implode("\n        ", $bad));

/* THE RANGES THE CHARACTER SHEET EXPECTS, for the mapped path too. An
   out-of-range value does not error; it draws a blank bar or falls through
   to the default 85 health. */
$mSizes = array('Small', 'Medium', 'Large');
$mTypes = array('Base', 'Leader', 'Battle Damaged');
$mPows  = array('Minor Regen', 'Regenerate', 'Boost Attack', 'Heal');
$oor = array();
foreach ((array)$fx as $f) {
	$g = monstrocity_trait_stats($ANC, $f['traits']);
	if ($g['strength'] < 1 || $g['strength'] > 8) $oor[] = 'strength ' . $g['strength'];
	if ($g['speed'] < 1 || $g['speed'] > 7)       $oor[] = 'speed ' . $g['speed'];
	if ($g['tactics'] < 1 || $g['tactics'] > 7)   $oor[] = 'tactics ' . $g['tactics'];
	if (!in_array($g['size'], $mSizes, true))     $oor[] = 'size ' . $g['size'];
	if (!in_array($g['type'], $mTypes, true))     $oor[] = 'type ' . $g['type'];
	if (!in_array($g['powerup'], $mPows, true))   $oor[] = 'powerup ' . $g['powerup'];
}
ok(!$oor, 'a mapped stat is outside what createCharacter() switches on: ' . implode(', ', $oor));

/*
 * THE MINT TYPO. Ancestors carries both "Legendary Scythe" and "Legendary
 * scythe". Four owners of the same weapon getting different cards is not a
 * rarity, so the rarity lookup folds case.
 */
$rA = monstrocity_trait_rarity($cen, 'Weapon', 'Legendary Scythe');
$rB = monstrocity_trait_rarity($cen, 'Weapon', 'legendary SCYTHE');
ok($rA > 0 && abs($rA - $rB) < 1e-9,
   "the same weapon spelled differently is priced differently ($rA vs $rB)");

/*
 * EVERY EAR IS NAMED. The charm decides the powerup, and a value that falls
 * through to the weapon rule is a trait that quietly stopped meaning
 * anything -- which is the failure this whole feature exists to fix.
 */
$unnamed = array();
foreach (array_keys($cen['slots']['Ear']['values']) as $v) {
	$hit = false;
	foreach ($map['power'] as $k => $_) if (mb_strtolower($k) === mb_strtolower($v)) $hit = true;
	if (!$hit) $unnamed[] = $v;
}
ok(!$unnamed, 'an Ear value has no powerup of its own: ' . implode(', ', $unnamed));

echo "\nand everything unmapped still hashes\n";
/*
 * THE WHOLE SAFETY OF THIS CHANGE. A theme can be any of thirty-five
 * collections and "what is a trait worth" has no answer across them, so
 * every policy without a map must come out byte-identical to before --
 * traits present or not.
 */
$otherPolicy = str_repeat('ab', 28);
$ancTraits = $fx[0]['traits'];
foreach ($chains as $chain => $id) {
	$plain  = monstrocity_nft_stats($id);
	$withTr = monstrocity_nft_stats($id, $ancTraits, $otherPolicy);
	ok($plain === $withTr, "an unmapped policy stopped hashing for $chain");
}
/* And a mapped policy with nothing to read falls back too, rather than
   returning half a card. */
$fpAnc = 'asset1w8wgpwcnyqzj0r9z0pdwvvnqhpvxjv5zpq0a4s';
ok(monstrocity_nft_stats($fpAnc, array(), $ANC) === monstrocity_nft_stats($fpAnc),
   'a mapped NFT the crawl has not reached yet gets something other than the hash');
ok(monstrocity_nft_stats($fpAnc, null, $ANC) === monstrocity_nft_stats($fpAnc),
   'null metadata does not fall back to the hash');
ok(monstrocity_trait_stats($otherPolicy, $ancTraits) === null,
   'an unmapped policy returns a card from the Ancestors rules');
/* The mapped path must actually differ, or none of the above proves it ran. */
ok(monstrocity_nft_stats($fpAnc, $ancTraits, $ANC) !== monstrocity_nft_stats($fpAnc),
   'the mapped card is identical to the hashed one, so the map is not running');

/* ------------------------------------------------------------------ *
 * HELLKNIGHTS: THE SECOND AUTHORED MAP
 * ------------------------------------------------------------------ */
echo "\nthe Hellknights map\n";
$HK = 'f0eca3e1fbe2d3801eff903b755d06bed64f19c30aacb1f124d18d6c';
$hkMap = monstrocity_trait_map($HK);
ok(is_array($hkMap), 'the Hellknights policy is not mapped');
$hkCen = $hkMap ? monstrocity_trait_census($hkMap['slug']) : null;
ok(is_array($hkCen) && !empty($hkCen['authoredCuts']),
   'the Hellknights census carries no authored cuts, so the map cannot band anything');

$hkFx = json_decode((string)@file_get_contents(__DIR__ . '/monstrocity-hellknights-fixture.json'), true);
ok(is_array($hkFx) && count($hkFx) >= 5, 'the Hellknights fixture is missing');
$hkBad = array();
foreach ((array)$hkFx as $f) {
	$got = monstrocity_trait_stats($HK, $f['traits']);
	if ($got !== $f['want']) $hkBad[] = $f['asset'] . ': ' . json_encode($got);
}
ok(!$hkBad, "a pinned Hellknight plays a different card now:\n        " . implode("\n        ", $hkBad));

/*
 * THE RULES THAT MAKE IT HELLKNIGHTS RATHER THAN A RARITY SORT. `chain` is
 * a literal rank ladder and `wings` a speed axis; if either stopped being
 * read, every card would still look plausible.
 */
$base = array('weapon' => 'Iron Sword', 'armor' => 'Northern Steel', 'helmet' => 'Angel Steel',
              'eyes' => 'Blizzard', 'horns' => 'Scarab Bone', 'insignia' => 'Cthulu',
              'background' => 'Mosaic Blue', 'wings' => 'None', 'chain' => 'None');
$with = function ($k, $v) use ($base) { $b = $base; $b[$k] = $v; return $b; };
ok(monstrocity_trait_stats($HK, $with('chain', 'Lord'))['type'] === 'Leader',
   'a Lord is not a Leader, so the rank chain is not being read');
ok(monstrocity_trait_stats($HK, $with('chain', 'Ancient'))['type'] === 'Leader',
   'an Ancient is not a Leader');
ok(monstrocity_trait_stats($HK, $with('chain', 'Officer'))['type'] === 'Base',
   'an Officer outranks itself into a Leader');
ok(monstrocity_trait_stats($HK, $with('wings', 'Torn'))['type'] === 'Battle Damaged',
   'torn wings are not read as damage');
/*
 * AND "BLOODY" IS NOT DAMAGE. It prefixes 72 of 158 weapons; reading it as
 * damage made 40% of the collection the 70-health card.
 */
ok(monstrocity_trait_stats($HK, $with('weapon', 'Bloody Blade of Hatred'))['type'] === 'Base',
   'a bloodied weapon makes its owner Battle Damaged, which is 40% of the collection');
/* Wings decide speed, and the same armour that raises strength lowers it. */
$noWing = monstrocity_trait_stats($HK, $with('wings', 'None'));
$flying = monstrocity_trait_stats($HK, $with('wings', 'Fire Angel'));
ok($flying['speed'] > $noWing['speed'],
   "wings do not make a Hellknight faster ({$flying['speed']} vs {$noWing['speed']})");
$plainArmour = monstrocity_trait_stats($HK, $with('armor', 'Northern Steel'));
$rareArmour  = monstrocity_trait_stats($HK, $with('armor', 'Royal Gown'));
ok($rareArmour['strength'] >= $plainArmour['strength'] && $rareArmour['speed'] <= $plainArmour['speed'],
   'rarer armour does not trade speed for strength, which is the whole shape of the map');

echo "\nand the generic reading, for collections nobody has authored\n";
/*
 * A COLLECTION CAN BE READ WITHOUT BEING UNDERSTOOD. Happy Hoppers is
 * Eyes, Hands, Hat, Mouth, Skin and Wings -- nothing in it says "strong",
 * so there is nothing to author. What can still be read is how unusual a
 * given Hopper is, which is the part a holder wants reflected.
 */
$gen = array();
foreach (array('happyhoppers', 'wavyape', 'omen', 'meltingmoonboy', 'meltingmoongirls') as $slug) {
	$c = monstrocity_trait_census($slug);
	if (!$c) { ok(false, "the $slug census is missing"); continue; }
	ok(!empty($c['cuts']['strength']) && !empty($c['cuts']['size']) && !empty($c['cuts']['type']),
	   "the $slug census carries no band cuts, so nothing can be read from it");
	$gen[$slug] = $c;
}
/* Every slug the index names must have a census behind it, or a whole
   collection silently falls back to the hash. */
$missing = array();
foreach (array('wavyape', 'omen', 'meltingmoonboy', 'meltingmoongirls', 'happyhoppers',
               'dph-afterdark', 'dph-uni2', 'dph-2bdevilgirls', 'dph-demondolls', 'dph-amievil') as $slug)
	if (!monstrocity_trait_census($slug)) $missing[] = $slug;
ok(!$missing, 'a policy is indexed for the generic reading with no census behind it: '
   . implode(', ', $missing));

/*
 * NO INDEXED COLLECTION MAY HAVE DEGENERATE BAND CUTS.
 *
 * THE BUG THIS PINS, REPORTED OFF A ROSTER SCREEN TWICE. A cut set where
 * every threshold is the same number puts the entire collection in one
 * band: all 1,231 Moebius Pioneers showed strength 8, and so did every
 * Drop Ship. It happens two ways and both are real --
 *
 *   an IDENTIFIER SLOT, where most holders have a value nobody else has,
 *   so everyone's rarest trait is a 1-of-1: Moebius `background` (806
 *   values over 1,231), Drop Ship `ID`, two Galactico `Serial Number`s,
 *   The Fourth's `R.I.P The Fallen` at 1,600 of 1,602. Filtered out of
 *   the scoring now, in monstrocity_generic_slots().
 *
 *   NO RARITY AT ALL. Drop Ship still collapses with ID gone, because
 *   `Character` is 20 values at roughly 16 each and Rank and Level are
 *   perfectly even. Nothing to read; the collection is not indexed.
 *
 * Checked over every census rather than the indexed ones only, so a
 * collection cannot be added to the index without this noticing.
 */
$degen = array();
foreach (glob(__DIR__ . '/lib/monstrocity-census-*.json') as $cf) {
	$slug = substr(basename($cf), strlen('monstrocity-census-'), -5);
	$doc  = json_decode((string)file_get_contents($cf), true);
	if (!is_array($doc) || empty($doc['cuts'])) continue;
	$indexed = monstrocity_generic_policy_slug_indexed($slug);
	if (!$indexed) continue;
	foreach ($doc['cuts'] as $stat => $cuts)
		if ($cuts && count(array_unique($cuts)) === 1)
			$degen[] = "$slug/$stat all = " . $cuts[0];
}
ok(!$degen, 'an indexed collection bands every NFT the same: ' . implode('; ', $degen));
/* And the filter that stops the commonest cause. */
$ids = array();
foreach (glob(__DIR__ . '/lib/monstrocity-census-*.json') as $cf) {
	$slug = substr(basename($cf), strlen('monstrocity-census-'), -5);
	$doc  = json_decode((string)file_get_contents($cf), true);
	if (!is_array($doc) || !isset($doc['slots']) || !is_array($doc['slots'])) continue;
	$scored = monstrocity_generic_slots($doc);
	foreach ($doc['slots'] as $k => $v) {
		$cov = (int)$v['coverage'];
		if ($cov < 20) continue;
		$ones = 0;
		foreach ($v['values'] as $c) if ((int)$c === 1) $ones++;
		if ($ones > $cov / 2 && in_array($k, $scored, true)) $ids[] = "$slug.$k";
	}
}
ok(!$ids, 'an identifier slot is being scored as a trait: ' . implode(', ', $ids));

/* Driven on real documents. */
$gfx = json_decode((string)@file_get_contents(__DIR__ . '/monstrocity-generic-fixture.json'), true);
ok(is_array($gfx) && count($gfx) >= 5, 'the generic fixture is missing');
$gbad = array(); $gor = array();
foreach ((array)$gfx as $f) {
	$got = monstrocity_trait_stats($f['policy'], $f['traits']);
	if ($got !== $f['want']) $gbad[] = $f['asset'] . ': ' . json_encode($got);
	if (!is_array($got)) continue;
	if ($got['strength'] < 1 || $got['strength'] > 8) $gor[] = 'strength ' . $got['strength'];
	if ($got['speed'] < 1 || $got['speed'] > 7)       $gor[] = 'speed ' . $got['speed'];
	if ($got['tactics'] < 1 || $got['tactics'] > 7)   $gor[] = 'tactics ' . $got['tactics'];
	if (!in_array($got['size'], $mSizes, true))       $gor[] = 'size ' . $got['size'];
	if (!in_array($got['type'], $mTypes, true))       $gor[] = 'type ' . $got['type'];
	if (!in_array($got['powerup'], $mPows, true))     $gor[] = 'powerup ' . $got['powerup'];
}
ok(!$gbad, "a pinned generic NFT plays a different card now:\n        " . implode("\n        ", $gbad));
ok(!$gor, 'a generic stat is outside what createCharacter() switches on: ' . implode(', ', $gor));

/*
 * THE THREE AXES ARE NOT ONE AXIS. strength is your rarest trait, tactics
 * your commonest, speed the inverse of the lot -- if any two collapsed
 * into the same ranking there would be one best card and nothing to
 * choose between the rest.
 */
$hop = monstrocity_trait_census('happyhoppers');
if ($hop) {
	$slots = monstrocity_generic_slots($hop);
	ok(count($slots) >= 5, 'the generic slot filter left ' . count($slots) . ' of the Hoppers slots');
	/* Boilerplate slots must be gone: one value across the collection is an
	   artist credit, not a trait. */
	$one = array();
	foreach ($hop['slots'] as $k => $v) if (count($v['values']) < 2) $one[] = $k;
	foreach ($one as $k) if (in_array($k, $slots, true)) ok(false, "the generic reading scores '$k', which has one value across the whole collection");
	ok(true, 'single-value slots are scored as traits');
}
/* A collection with a census but NOT in the index still hashes: a census
   file is research, and dropping one in a directory must not change what
   every holder plays. */
ok(monstrocity_generic_policy('3966cf1c948109e34f2c5a9f9670445ccc85008e5b8a6e67f913b491') === null,
   'the Ancestors is in the generic index as well as authored, so which one answers is luck');
ok(monstrocity_generic_policy($HK) === null,
   'Hellknights is in the generic index as well as authored');
ok(monstrocity_generic_policy(str_repeat('cd', 28)) === null,
   'an unknown policy resolves to a census');

/* ------------------------------------------------------------------ *
 * DISCO SOLARIS AND MOEBIUS PIONEERS
 * ------------------------------------------------------------------ */
echo "\ntwo more authored maps\n";
$DS = 'd0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779';
$MP = '9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99';
foreach (array('Disco Solaris' => $DS, 'Moebius Pioneers' => $MP) as $name => $P) {
	$m = monstrocity_trait_map($P);
	ok(is_array($m), "$name is not mapped");
	$c = $m ? monstrocity_trait_census($m['slug']) : null;
	ok(is_array($c) && !empty($c['authoredCuts']), "$name has no authored cuts");
	ok(monstrocity_generic_policy($P) === null,
	   "$name is in the generic index as well as authored, so which one answers is luck");
}

$afx = json_decode((string)@file_get_contents(__DIR__ . '/monstrocity-authored-fixture.json'), true);
ok(is_array($afx) && count($afx) >= 8, 'the Disco/Moebius fixture is missing');
$abad = array();
foreach ((array)$afx as $f) {
	$got = monstrocity_trait_stats($f['policy'], $f['traits']);
	if ($got !== $f['want']) $abad[] = $f['asset'] . ': ' . json_encode($got);
}
ok(!$abad, "a pinned Disco/Moebius NFT plays a different card now:\n        "
   . implode("\n        ", $abad));

/* THE MOON IS THE FRAME. It is the collection's own metaphor and the whole
   reason this map is authored rather than generic. */
$ds = array('Eyes'=>'Bored','Mouth'=>'Smile','Skin'=>'Peach','Body'=>'Tank Top',
            'Hair'=>'Short Red','Outerwear'=>'Bomber Jacket','Earrings'=>'Cross',
            'Phase'=>'First Quarter Moon');
$withDs = function ($k, $v) use ($ds) { $b = $ds; if ($v === null) unset($b[$k]); else $b[$k] = $v; return $b; };
$frames = array('New Moon'=>'Small','Waxing Crescent Moon'=>'Small','First Quarter Moon'=>'Medium',
                'Waxing Gibbous Moon'=>'Medium','Full Moon'=>'Large');
$wrong = array();
foreach ($frames as $moon => $want) {
	$g = monstrocity_trait_stats($DS, $withDs('Phase', $moon));
	if ($g['size'] !== $want) $wrong[] = "$moon -> {$g['size']}, wanted $want";
}
ok(!$wrong, 'the moon phase no longer decides the frame: ' . implode('; ', $wrong));
/* A uniform is a rank; the 607 Fans are not. */
ok(monstrocity_trait_stats($DS, $withDs('Special', 'Police Officer Uniform'))['type'] === 'Leader',
   'a police uniform is not a rank');
ok(monstrocity_trait_stats($DS, $withDs('Special', 'Fans'))['type'] === 'Base',
   'Fans are being read as a rank, which is 607 of the 725 Specials');
ok(monstrocity_trait_stats($DS, $withDs('Cheek', 'Bandage'))['type'] === 'Battle Damaged',
   'a bandage on the cheek is not read as damage');
/* AND RANK BEATS DAMAGE. An officer with a bandage is still an officer --
   asserted because the order of those two rules is arbitrary-looking and a
   swap would quietly demote every injured Leader. */
$both = $withDs('Special', 'Police Officer Uniform'); $both['Cheek'] = 'Bandage';
ok(monstrocity_trait_stats($DS, $both)['type'] === 'Leader',
   'a bandaged officer is demoted out of Leader');
/* Headphones make you faster, a top hat slows you down. */
$noPhones = monstrocity_trait_stats($DS, $withDs('Headphones', null));
$phones   = monstrocity_trait_stats($DS, $withDs('Headphones', 'Headphones Large'));
ok($phones['speed'] >= $noPhones['speed'], 'headphones do not make a dancer faster');
$hat = $withDs('Headphones', 'Headphones Large'); $hat['Hat'] = 'Top Hat';
ok(monstrocity_trait_stats($DS, $hat)['speed'] <= $phones['speed'],
   'a top hat does not slow anybody down');

/*
 * THE EDITION SUFFIX, which is the whole reason Moebius can be read at all.
 * "Choker 2 #31" and "Choker 2 #41" are the same choker; untrimmed, every
 * Pioneer wears a one-of-one and every strength lands in band 8 -- which is
 * exactly what the roster showed before this map existed.
 */
$mpCen = monstrocity_trait_census('moebius-pioneers-dressed-dis');
$trim  = '~\s*#\d+\s*$~';
$r1 = monstrocity_trait_rarity($mpCen, 'neck', 'Choker 2 #31', $trim);
$r2 = monstrocity_trait_rarity($mpCen, 'neck', 'Choker 2 #41', $trim);
ok($r1 > 0 && abs($r1 - $r2) < 1e-9,
   "two editions of one choker are priced differently ($r1 vs $r2)");
$untrimmed = monstrocity_trait_rarity($mpCen, 'neck', 'Choker 2 #31');
ok($untrimmed > $r1,
   'trimming the edition number does not change the rarity, so it is not being applied');
/* And the collapse it produces, stated as a number so a change to the
   pattern shows up here rather than on a roster screen. */
$raw = count($mpCen['slots']['body']['values']);
$seen = array();
foreach (array_keys($mpCen['slots']['body']['values']) as $v)
	$seen[mb_strtolower(preg_replace($trim, '', $v))] = true;
ok($raw > 200 && count($seen) < 30,
   "the Moebius body slot trims from $raw to " . count($seen) . ' values, expected hundreds to tens');

/* A Flying Board is the fastest thing in the collection, whichever colour. */
$mp = array('eyes'=>'Bored','lips'=>'Straight Line','body'=>'Violet Male','nose'=>'Nose 01 - Violet',
            'hair'=>'Curly Fringe','nipples'=>'Stars','background'=>'Disco Yellow',
            'outerwear'=>'Tech Crew Suit');
$withMp = function ($k, $v) use ($mp) { $b = $mp; if ($v === null) unset($b[$k]); else $b[$k] = $v; return $b; };
$walk  = monstrocity_trait_stats($MP, $mp);
$board = monstrocity_trait_stats($MP, $withMp('backpack', 'Flying Board - Premium - Pink #3'));
ok($board['speed'] > $walk['speed'],
   "a flying board does not make a Pioneer faster ({$board['speed']} vs {$walk['speed']})");
$pink  = monstrocity_trait_stats($MP, $withMp('backpack', 'Flying Board - Standard - Pink #1'));
$blue  = monstrocity_trait_stats($MP, $withMp('backpack', 'Flying Board - Standard - Blue #9'));
ok($pink['speed'] === $blue['speed'],
   'the same board in another colour is a different speed, so the family rule is not matching');
/* OG gear is the rank; nothing else is. */
ok(monstrocity_trait_stats($MP, $withMp('outerwear', 'OG H-Collar Jacket - Pink #61'))['type'] === 'Leader',
   'OG gear is not read as the original');
ok(monstrocity_trait_stats($MP, $withMp('outerwear', 'Tech Crew Suit'))['type'] === 'Base',
   'a plain crew suit outranks itself into a Leader');
ok(monstrocity_trait_stats($MP, $withMp('bodycover', 'Neck Stitch'))['type'] === 'Battle Damaged',
   'a neck stitch is not read as damage');
/* Wearing nothing is a smaller silhouette than a suit. */
ok(monstrocity_trait_stats($MP, $withMp('outerwear', null))['size'] === 'Small',
   'a Pioneer with no outerwear is not the smallest frame');
ok(monstrocity_trait_stats($MP, $withMp('outerwear', 'Corpo Suit - Pink'))['size'] === 'Large',
   'a Corpo Suit is not the largest frame');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
