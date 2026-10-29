<?php
/**
 * lib/monstrocity-trait-maps.php — stats a collection's own traits decide.
 *
 * WHAT THIS CHANGES. monstrocity_nft_stats() invents a card by hashing an
 * NFT's identifier, because a Monstrocity THEME can be any collection on the
 * platform and most of them have nothing Monstrocity could read. That stays
 * the default and stays right for them.
 *
 * It is wrong for a collection whose traits mean something. An Ancestor
 * carrying a Sacred Golden Sword and a King Crown should not roll the same
 * card as one holding a chicken leg, and a collector should be able to look
 * at their NFT and see why it plays the way it does. So a collection can be
 * MAPPED: named here, by policy, with rules that say what its own traits are
 * worth. Everything unmapped keeps hashing, which is the part that makes
 * this safe to add -- see the warning in skullpaper/MAINTENANCE.md about not
 * deciding what a trait means across thirty-five unrelated collections.
 *
 * RARITY IS MEASURED AGAINST THE WHOLE COLLECTION, NOT THE STAKED ROWS.
 * lib/monstrocity-census-<slug>.json is taken from the chain by
 * monstrocity-trait-census.php. Counting nfts.metadata would measure
 * whoever happens to be staked this week and would move every night.
 *
 * AND THE BANDS ARE FIXED, NOT RELATIVE. The cut points below were computed
 * once over the whole census so the eight strength values are all used and
 * roughly evenly. Recomputing them per player would mean the same NFT
 * scoring differently in two people's rosters.
 */

if (!function_exists('monstrocity_trait_map')) {

/**
 * Every mapped collection, by policy.
 *
 * `speed0`      species starting speed, before what it is carrying.
 * `weight`      how much a filled slot slows it down. Armour costs more
 *               than a beard, which is the whole reason speed is not just
 *               a second copy of the rarity score: trait LOAD falls as
 *               species rarity rises (Human 7.6 slots, Legendary Ancient
 *               5.0), so the two axes genuinely pull apart and a maxed-out
 *               legendary does not top every stat.
 * `cuts`        score thresholds, computed over the census. Eight bands for
 *               strength, seven for speed and tactics.
 * `lead`/`dmg`  what makes a Leader or a Battle Damaged card. Read off the
 *               HEAD only: allowing Body and Beard to promote made 38% of
 *               the collection Leaders, and Leader is the 100-health card.
 */
function monstrocity_trait_map($policy) {
	static $maps = null;
	if ($maps === null) $maps = array(
		/* The Ancestors. 1,225 assets, 10 trait slots, 432 distinct values. */
		'3966cf1c948109e34f2c5a9f9670445ccc85008e5b8a6e67f913b491' => array(
			'slug'    => 'ancestors',
			'arms'    => array('Weapon', 'Additional Weapon', 'Back Gear'),
			'mind'    => array('Head', 'Face'),
			/*
			 * TENTHS, AS INTEGERS, AND THAT IS NOT A STYLE CHOICE. The speed
			 * score is a species figure minus a subset of seven fixed
			 * weights, so it only ever lands on a few dozen values -- and
			 * a great many of those sit exactly ON a band boundary. In
			 * floating point the comparison at that boundary is decided by
			 * the last bit, which put 306 Ancestors in one speed band and
			 * 47 in the next. Integers make it exact.
			 */
			'speed0'  => array('Elf' => 40, 'Human' => 30, 'Orc' => 24, 'Dwarf' => 18,
			                   'Pure Ancient' => 36, 'Legendary Ancient' => 42),
			'speedDefault' => 28,
			'weight'  => array('Body' => 10, 'Weapon' => 9, 'Back Gear' => 8,
			                   'Additional Weapon' => 7, 'Head' => 5,
			                   'Beard' => 2, 'Ear' => 1),
			'cuts'    => array(
				'strength' => array(1.3892, 1.4971, 1.7079, 1.8840, 2.8645, 3.2582, 3.5988),
				'tactics'  => array(2.9916, 3.2743, 3.4158, 3.5145, 3.6977, 4.2842),
				'speed'    => array(-9, -4, -1, 3, 6, 14),
			),
			'sizeBy'  => 'Type',
			'size'    => array('Dwarf' => 'Small', 'Human' => 'Medium', 'Elf' => 'Medium',
			                   'Orc' => 'Large', 'Pure Ancient' => 'Large',
			                   'Legendary Ancient' => 'Large'),
			'sizeDefault' => 'Medium',
			'rankBy'  => array('Head'),
			'lead'    => '~\b(crown|king|prince|chief|royal|warlord|commander|sacred|high)\b~i',
			'dmgBy'   => array('Head', 'Body', 'Ear'),
			'dmg'     => '~\b(cursed|biten|bitten|broken|scar|dark|skull|bone|dead|rotten)\b~i',
			/* The earring is the charm. Every one of the thirteen is named,
			   because a value falling through to a default is how a trait
			   stops meaning anything. */
			'powerBy' => 'Ear',
			'power'   => array(
				'Leaf Of Life Earring'        => 'Heal',
				'Magic Feather Earring'       => 'Heal',
				'Golden Earring Ear'          => 'Regenerate',
				'Big Golden Earring'          => 'Regenerate',
				'Double Golden Earrings'      => 'Regenerate',
				'Warrior'                     => 'Boost Attack',
				'Commander Earring'           => 'Boost Attack',
				'Fat Iron Earring'            => 'Boost Attack',
				'Nail Earring'                => 'Boost Attack',
				'Bone Earring'                => 'Boost Attack',
				'Basic Ear'                   => 'Minor Regen',
				'Nosy'                        => 'Minor Regen',
				'Biten Ear With Hoop Earring' => 'Minor Regen',
			),
			/* 295 Ancestors have no ear at all, so the weapon answers
			   instead rather than handing a quarter of the collection the
			   same charm. */
			'powerFallback' => array(
				array('~staff|magic|elixir|sphere|book|pipe|rune~i', 'Regenerate'),
				array('~bow|arrow|dagger|sword|axe|scythe|spear|pilum~i', 'Boost Attack'),
			),
			'powerDefault' => 'Minor Regen',
			'powerWeapons' => array('Weapon', 'Additional Weapon'),
		),
	);
	$policy = strtolower(trim((string)$policy));
	return isset($maps[$policy]) ? $maps[$policy] : null;
}

/**
 * The census for a mapped collection: value => how many of the collection
 * carry it. Read once per process.
 */
function monstrocity_trait_census($slug) {
	static $cache = array();
	$slug = preg_replace('/[^a-z0-9-]/', '', (string)$slug);
	if ($slug === '') return null;
	if (array_key_exists($slug, $cache)) return $cache[$slug];
	$f = __DIR__ . '/monstrocity-census-' . $slug . '.json';
	$doc = is_file($f) ? json_decode((string)file_get_contents($f), true) : null;
	$cache[$slug] = (is_array($doc) && !empty($doc['assets']) && !empty($doc['slots'])) ? $doc : null;
	return $cache[$slug];
}

/**
 * How rare one trait value is, as -log10 of its share of the collection.
 *
 * SHARE OF THE WHOLE COLLECTION, not of the slot. Only 240 Ancestors carry
 * Back Gear at all, so having any is itself rare and the arithmetic should
 * say so -- dividing by the slot's own coverage would price a common Back
 * Gear the same as a common Face.
 *
 * MATCHED CASE-INSENSITIVELY. The Ancestors mint carries both "Legendary
 * Scythe" and "Legendary scythe"; four owners of the same weapon getting
 * different cards is not a rarity, it is a typo.
 */
function monstrocity_trait_rarity($census, $slot, $value) {
	if (!is_array($census) || $value === null || $value === '') return 0.0;
	if (!isset($census['slots'][$slot]['values'])) return 0.0;
	$n = 0;
	$want = mb_strtolower((string)$value);
	foreach ($census['slots'][$slot]['values'] as $v => $c)
		if (mb_strtolower((string)$v) === $want) $n += (int)$c;
	if ($n <= 0) return 0.0;
	return -log10($n / max(1, (int)$census['assets']));
}

/** Which band a score falls in, 1-based. Cuts are ascending. */
function monstrocity_trait_band($score, $cuts) {
	$i = 0;
	foreach ($cuts as $c) { if ($score < $c) break; $i++; }
	return $i + 1;
}

/**
 * The card a mapped collection's traits decide, or null when the policy is
 * not mapped, the census is missing, or the NFT has no traits to read.
 *
 * NULL IS THE CONTRACT, and it is what keeps this safe: every one of those
 * three is an ordinary state -- an unmapped collection, a dev checkout with
 * no census file, an NFT the nightly crawl has not reached -- and the
 * caller falls back to the hash it has always used. A half-derived card
 * would be worse than either.
 */
function monstrocity_trait_stats($policy, $traits) {
	$map = monstrocity_trait_map($policy);
	if (!$map || !is_array($traits) || !$traits) return null;
	$census = monstrocity_trait_census($map['slug']);
	if (!$census) return null;

	$get = function ($slot) use ($traits) {
		return isset($traits[$slot]) && is_scalar($traits[$slot]) ? (string)$traits[$slot] : '';
	};

	/* What it is carrying, and what that is worth. */
	$arms = 0.0;
	foreach ($map['arms'] as $s) $arms += monstrocity_trait_rarity($census, $s, $get($s));
	$mind = 0.0;
	foreach ($map['mind'] as $s) $mind += monstrocity_trait_rarity($census, $s, $get($s));

	/* Speed is species minus load, which is the one axis that does not
	   simply follow rarity -- see the note on `weight` above. */
	$species = $get($map['sizeBy']);
	$speed = isset($map['speed0'][$species]) ? $map['speed0'][$species] : $map['speedDefault'];
	foreach ($map['weight'] as $s => $w) if ($get($s) !== '') $speed -= $w;

	$size = isset($map['size'][$species]) ? $map['size'][$species] : $map['sizeDefault'];

	$type = 'Base';
	foreach ($map['rankBy'] as $s)
		if ($get($s) !== '' && preg_match($map['lead'], $get($s))) { $type = 'Leader'; break; }
	if ($type === 'Base') {
		foreach ($map['dmgBy'] as $s)
			if ($get($s) !== '' && preg_match($map['dmg'], $get($s))) { $type = 'Battle Damaged'; break; }
	}

	$powerup = null;
	$charm = $get($map['powerBy']);
	foreach ($map['power'] as $k => $v)
		if (mb_strtolower($k) === mb_strtolower($charm)) { $powerup = $v; break; }
	if ($powerup === null) {
		$weapons = '';
		foreach ($map['powerWeapons'] as $s) $weapons .= ' ' . $get($s);
		foreach ($map['powerFallback'] as $rule)
			if (preg_match($rule[0], $weapons)) { $powerup = $rule[1]; break; }
	}
	if ($powerup === null) $powerup = $map['powerDefault'];

	return array(
		'strength' => monstrocity_trait_band($arms,  $map['cuts']['strength']),
		'speed'    => monstrocity_trait_band($speed, $map['cuts']['speed']),
		'tactics'  => monstrocity_trait_band($mind,  $map['cuts']['tactics']),
		'size'     => $size,
		'type'     => $type,
		'powerup'  => $powerup,
	);
}

}
