<?php
/**
 * Stats for a theme NFT, derived from its on-chain identifier.
 *
 * WHAT THIS IS, SO NOBODY GOES LOOKING FOR AUTHORED STATS: a Monstrocity
 * THEME can be any collection on the platform, and those collections know
 * nothing about Monstrocity -- no strength trait, no size trait, nothing to
 * read. So the stats are invented here, deterministically, from the one thing
 * every NFT has: its identifier. The same NFT always rolls the same card.
 *
 * The ORIGINAL Monstrocity collection is the exception and does not come
 * through here at all: its stats were authored at mint and are read from the
 * CIP-25 metadata by ajax/get-monstrocity-assets.php via Koios.
 *
 * ANY CHAIN, NOT JUST CARDANO. This used to live inline in
 * ajax/get-nft-assets.php behind `strpos($asset_id, 'asset') !== 0`, which
 * `continue`d on anything that was not a Cardano CIP-14 fingerprint. Every
 * other chain stores a different shape --
 *
 *   cardano  asset1w8wgpwcnyq...             CIP-14 fingerprint
 *   solana   7xKXtg2CW87d97TXJSDpbD5jBkhe... base58 mint address
 *   xrpl     000800009A1B2C3D...             64-hex NFTokenID
 *   polygon  0xee79...c5c4:4444              contract:tokenId
 *
 * -- so all three of those were dropped, the theme fell back to the player's
 * Monstrocity roster, and nothing said why. The derivation was never
 * Cardano-specific; it is a hash of a string.
 */

/* The mapped collections. Required here rather than by each caller: this
   file is the one answer to "what card does this NFT play", and a caller
   that forgot the include would silently get the hash for a collection
   that is supposed to read its traits. */
require_once __DIR__ . '/monstrocity-trait-maps.php';

/**
 * The bytes the stats are hashed from.
 *
 * A CARDANO FINGERPRINT KEEPS ITS 5-CHARACTER STRIP, exactly as before.
 * Which bytes go in decides the stats, so seeding from the full `asset1...`
 * string would re-roll strength, speed and tactics for every Cardano NFT on
 * the platform -- every theme roster would silently change overnight.
 * Everything else seeds from the whole identifier.
 */
function monstrocity_nft_seed($asset_id) {
	$asset_id = (string)$asset_id;
	return strpos($asset_id, 'asset') === 0 ? substr($asset_id, 5) : $asset_id;
}

/**
 * array(strength, speed, tactics, size, type, powerup), or null when there is
 * no identifier to hash. Ranges are what monstrocity.php's character sheet
 * expects: strength 1-8, speed and tactics 1-7.
 *
 * A MAPPED COLLECTION ANSWERS FIRST, AND EVERYTHING ELSE STILL HASHES.
 *
 * Some collections' traits mean something -- an Ancestor carrying a Sacred
 * Golden Sword and a King Crown should not roll the same card as one
 * holding a chicken leg, and a collector should be able to look at their
 * NFT and see why it plays the way it does. Those are named by policy in
 * lib/monstrocity-trait-maps.php.
 *
 * Everything unmapped falls through to exactly the hash it always used, by
 * exactly the same bytes, which is what makes this safe to add: a theme can
 * be any of thirty-five collections and "what is a trait worth" has no
 * answer across them. The harness pins four captured baselines on that.
 *
 * $traits is nfts.metadata read through nft_metadata_traits(); $policy is
 * the collection's. Both optional, so every existing caller is unaffected.
 */
function monstrocity_nft_stats($asset_id, $traits = null, $policy = '') {
	if ($policy !== '' && is_array($traits) && $traits
	    && function_exists('monstrocity_trait_stats')) {
		$mapped = monstrocity_trait_stats($policy, $traits);
		if (is_array($mapped)) return $mapped;
	}

	$seed = monstrocity_nft_seed($asset_id);
	if ($seed === '') return null;

	$length = strlen($seed);

	/* Whole string. The doubling lifts the bottom of the range off 1-3, so a
	   theme is not half-full of cards nobody would field. */
	$char_sum = 0;
	for ($i = 0; $i < $length; $i++) $char_sum += ord($seed[$i]);
	$strength = ($char_sum % 8) + 1;
	if ($strength < 4) $strength = $strength * 2;

	/* Head and tail, so the three stats do not all move together. */
	$speed_sum = 0;
	for ($i = 0; $i < min(10, $length); $i++) $speed_sum += ord($seed[$i]);
	$speed = ($speed_sum % 7) + 1;

	$tactics_sum = 0;
	for ($i = max(0, $length - 10); $i < $length; $i++) $tactics_sum += ord($seed[$i]);
	$tactics = ($tactics_sum % 7) + 1;

	$size_map = array('Small', 'Medium', 'Large');
	$size = $size_map[crc32($seed) % 3];

	/*
	 * A SALTED SEED, OR SIZE WOULD DECIDE TYPE. Both of these were
	 * crc32($seed) % 3 over three-element maps, so the two indexes were
	 * always identical: every Small was Base, every Medium a Leader, every
	 * Large Battle Damaged. type sets base health (100 Leader / 85 Base /
	 * 70 Battle Damaged) and size scales it (x1.2 Large / x1 Medium / x0.8
	 * Small) plus shifts tactics, so the collection had three outcomes
	 * instead of nine -- and Medium+Leader, 100 health with no tactics
	 * penalty, was strictly the best card, rolled by a third of every
	 * collection.
	 *
	 * SIZE KEEPS THE ORIGINAL HASH so no existing NFT changes size; only
	 * the type is re-seeded.
	 */
	$type_map = array('Base', 'Leader', 'Battle Damaged');
	$type = $type_map[crc32('type:' . $seed) % 3];

	$powerup_map = array('Minor Regen', 'Regenerate', 'Boost Attack', 'Heal');
	$powerup = $powerup_map[ord($seed[$length - 1]) % 4];

	return array(
		'strength' => $strength,
		'speed'    => $speed,
		'tactics'  => $tactics,
		'size'     => $size,
		'type'     => $type,
		'powerup'  => $powerup,
	);
}
