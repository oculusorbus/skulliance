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
ok(strpos($ep, 'monstrocity_nft_stats($asset_id)') !== false,
   'get-nft-assets.php does not call monstrocity_nft_stats()');
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

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
