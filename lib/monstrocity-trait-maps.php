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
			/*
			 * FROM THE CENSUS, not written here. These were pasted in by
			 * hand before monstrocity-trait-census.php learned to compute
			 * them, and the moment the census was retaken -- after
			 * nft_metadata_traits() stopped reading leaked ipfs:// links as
			 * traits -- the hand-written set was describing a slightly
			 * different collection than the one being scored. One place.
			 */
			'cuts'    => array(),
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
		/*
		 * Hellknights. 3,000 assets, every slot filled on every one, and a
		 * vocabulary that reads like a character sheet already: `chain` is
		 * a literal rank ladder (Officer, Lord, Ancient), `wings` is a
		 * speed axis, and most weapons announce they are Bloody.
		 */
		'f0eca3e1fbe2d3801eff903b755d06bed64f19c30aacb1f124d18d6c' => array(
			'slug'    => 'hellknights',
			'arms'    => array('weapon', 'armor'),
			'mind'    => array('eyes', 'helmet'),
			/*
			 * WINGS DECIDE SPEED, and armour takes it back. Every Hellknight
			 * fills every slot, so Ancestors' "how many things are you
			 * carrying" has no variance here -- the load term has to be the
			 * WEIGHT of one slot rather than the count of filled ones, and
			 * elaborate armour is the obvious candidate. It also makes the
			 * trade explicit: the same armour that raises strength lowers
			 * speed.
			 */
			'speedBy' => 'wings',
			'speed0'  => array('Devilish' => 42, 'Fire Angel' => 40, 'Iron Angel' => 38,
			                   'Midnight Angel' => 39, 'Torn' => 30, 'Beast' => 26,
			                   'None' => 16),
			'speedDefault' => 28,
			'weight'  => array(),
			'speedLoadBy' => array('armor'),
			'cuts'    => array(),            // filled from the census below
			'sizeBy'  => 'horns',
			'size'    => array(),            // keyword rules instead
			/* Minotaur, Carnage, Maul and Underworld are the big frames.
			   Beast, Scarab, Spider and Spikes read as ordinary -- letting
			   "Spiked Beast" count as Large made 61% of the collection
			   Large, which is not a frame, it is the default. */
			'sizeBig'   => '~\b(minotaur|carnage|maul|underworld)\b~i',
			'sizeSmall' => '~\b(none|mop|mohawk|bangs|undercut)\b~i',
			'sizeDefault' => 'Medium',
			'rankBy'  => array('chain'),
			'lead'    => '~\b(lord|ancient|experimental)\b~i',
			/*
			 * NOT "Bloody". It prefixes 72 of the 158 weapons, so reading it
			 * as damage made 40% of the collection Battle Damaged -- the
			 * 70-health card -- and it does not even mean that: a bloodied
			 * blade is a used one, which says something about the weapon
			 * rather than about the knight carrying it.
			 *
			 * Torn wings, a mortal wound on the chain, an autopsy, or going
			 * out with no armour at all do mean it. That is about 26 of the
			 * 3,000, which is rare -- and correct. A type that only a
			 * handful of NFTs carry is a distinction, not a bug; inventing
			 * a common one would just be mislabelling 1,200 knights.
			 *
			 * The no-armour case is dropped: four assets, and the pattern
			 * for it matched "None" wings and "None" chains too, which made
			 * 2,044 knights Battle Damaged on the first run.
			 */
			'dmgBy'   => array('wings', 'chain'),
			'dmg'     => '~\b(torn|autopsy|mortal\s+wound)\b~i',
			/* The seven houses are evenly distributed, so they carry no
			   rarity at all -- which makes them the right thing to hang the
			   charm on, where rarity is not wanted. */
			'powerBy' => 'insignia',
			'power'   => array(
				'Cthulu'   => 'Regenerate',  'Yoroi'    => 'Boost Attack',
				'Daedalus' => 'Minor Regen', 'Ouroboros'=> 'Heal',
				'Ravanne'  => 'Boost Attack','Bajuzki'  => 'Regenerate',
				'None'     => 'Minor Regen',
			),
			'powerFallback' => array(),
			'powerDefault'  => 'Minor Regen',
			'powerWeapons'  => array('weapon'),
		),
		/*
		 * Disco Solaris. 5,777 of them, and a vocabulary built for this:
		 * `Phase` is a moon cycle, `Special` is a uniform, and `Cheek`
		 * includes a bandage.
		 */
		'd0112837f8f856b2ca14f69b375bc394e73d146fdadcc993bb993779' => array(
			'slug'    => 'disco-solaris',
			'arms'    => array('Outerwear', 'Body'),
			'mind'    => array('Eyes', 'Glasses'),
			/* Headphones on means you are in the groove; a top hat and a
			   heavy coat are not what you dance in. */
			/* Hundredths and a continuous load, for the reason given on
			   the Moebius map below: three headphone states and four
			   on/off weights is a dozen distinct scores across 5,777
			   dancers, which is not a spread. */
			'speedBy' => 'Headphones',
			/*
			 * A NARROW GAP, because the jump has to be smaller than the
			 * spread it sits inside. At 400/360/260 the 1,572 with
			 * headphones and the 4,205 without formed two clusters a
			 * hundred points apart with nothing between them -- 2,509 in
			 * speed band 1, 2,842 in band 7 and the middle bands empty.
			 * Fifty points reads the same and blends.
			 */
			'speed0'  => array('Headphones Large' => 330, 'Headphones Small' => 310),
			'speedDefault' => 280,
			'weight'  => array('Hat' => 80, 'Necklace' => 20),
			'speedLoadBy' => array('Outerwear', 'Body'),
			'speedLoadScale' => 100,
			'cuts'    => array(),
			/* THE MOON DECIDES THE FRAME. New and waxing crescent are slim,
			   a full moon is the whole disc. It is the collection's own
			   metaphor and it costs nothing to use it. */
			'sizeBy'  => 'Phase',
			'size'    => array('New Moon' => 'Small', 'Waxing Crescent Moon' => 'Small',
			                   'First Quarter Moon' => 'Medium', 'Waxing Gibbous Moon' => 'Medium',
			                   'Full Moon' => 'Large'),
			'sizeDefault' => 'Medium',
			/* A uniform is a rank. Fans -- 607 of the 725 Specials -- are
			   not, and should not be. */
			'rankBy'  => array('Special'),
			'lead'    => '~\b(police|sergeant|lieutenant|space\s*suit)\b~i',
			'dmgBy'   => array('Cheek'),
			'dmg'     => '~\bbandage\b~i',
			'powerBy' => 'Cheek',
			'power'   => array(
				'Heart'        => 'Heal',        'Kiss Mark'   => 'Boost Attack',
				'Blush Makeup' => 'Boost Attack','Smiley'      => 'Minor Regen',
				'Freckles'     => 'Minor Regen', 'Bandage'     => 'Regenerate',
			),
			/* Only half of them have a cheek mark, so the jewellery answers
			   for the rest rather than handing 2,800 the same charm. */
			'powerFallback' => array(
				array('~cross|pearl|amber~i', 'Heal'),
				array('~bolt|punk~i',         'Boost Attack'),
				array('~gold~i',              'Regenerate'),
			),
			'powerDefault'  => 'Minor Regen',
			'powerWeapons'  => array('Earrings', 'Necklace'),
		),
		/*
		 * Moebius Pioneers: Dressed Discos. The same universe, 1,231 of
		 * them, and unreadable until the edition suffix comes off -- see
		 * valueTrim and the note in monstrocity_trait_rarity().
		 */
		'9874142fc1a8687d0fa4c34140b4c8678e820c91c185cc3c099acb99' => array(
			'slug'    => 'moebius-pioneers-dressed-dis',
			'valueTrim' => '~\s*#\d+\s*$~',
			/*
			 * THE WHOLE KIT, NOT JUST THE COAT.
			 *
			 * This was outerwear + hand, and Moebius does not put its heavy
			 * gear in `outerwear`: a Sweeper in full powered armour carries
			 * it in `suit`, and a worker's helmet is in `helmet`. So 220
			 * Pioneers had an empty outerwear slot, 203 of them scored the
			 * bottom strength band, and the most armoured characters in the
			 * collection read as the weakest and smallest things in the
			 * game. Reported off a roster screen.
			 *
			 * Six slots, which is every one the collection dresses someone
			 * in. Only four Pioneers out of 1,231 wear none of them.
			 */
			'arms'    => array('outerwear', 'suit', 'helmet', 'chestcover', 'innerwear', 'hand'),
			/* A Sweeper Armor is the whole outfit, not one layer of it. */
			'armsWeight' => array('suit' => 2.5),
			'mind'    => array('eyes', 'glasses'),
			/*
			 * A FLYING BOARD IS A FLYING BOARD whichever colour it is, so
			 * speed is matched on the family rather than on 16 exact
			 * values. Premium boards beat standard ones, a pinwheel beats
			 * walking, and a Sweeper's kit is heavy.
			 */
			'speedBy' => 'backpack',
			/*
			 * HUNDREDTHS, AND A CONTINUOUS LOAD. Only 265 of the 1,231
			 * carry a backpack at all, so on the board alone everyone else
			 * landed on the same two or three numbers -- speed bands 2, 3
			 * and 4 were empty and 846 Pioneers sat in band 5. The
			 * outerwear rarity is continuous and gives the other 966
			 * somewhere to spread.
			 */
			'speedRules' => array(
				array('~flying board - premium~i', 440),
				array('~flying board~i',           380),
				array('~pinwheel~i',               330),
				array('~sweeper~i',                200),
				array('~backpack~i',               220),
			),
			'speed0'  => array(),
			'speedDefault' => 280,
			'weight'  => array('suit' => 90, 'helmet' => 40),
			'speedLoadBy' => array('outerwear'),
			'speedLoadScale' => 100,
			'cuts'    => array(),
			/* What you are wearing is your silhouette. Nothing at all is a
			   smaller one, which is 220 of them. */
			/* ARMOUR FIRST. A Sweeper Armor lives in `suit`, so reading
			   `outerwear` alone called the bulkiest thing in the collection
			   Small. Presence of a suit is a Large frame whatever else is
			   or is not worn. */
			'sizeBigSlots' => array('suit'),
			'sizeBy'  => 'outerwear',
			'size'    => array(),
			'sizeBig'   => '~suit|armor|coat~i',
			'sizeSmall' => '~^\s*$~',
			'sizeAbsent' => 'Small',
			'sizeDefault' => 'Medium',
			/* OG gear and the Moonlight Radio Pendant are the originals. */
			'rankBy'  => array('outerwear', 'necklace'),
			'lead'    => '~\bOG\b|moonlight radio~i',
			/* Smudges, stitches, grease and a damaged bionic hand. This is
			   a working crew, and it shows on them. */
			'dmgBy'   => array('bodycover', 'hand'),
			'dmg'     => '~smudge|stitch|grease|bandage|patch|damaged~i',
			'powerBy' => 'facetattoo',
			'power'   => array(
				'Heart'        => 'Heal',        'Pierced Heart' => 'Heal',
				'Broken Heart' => 'Regenerate',  'DBD Logo'      => 'Regenerate',
				'Star'         => 'Boost Attack','Discoin'       => 'Boost Attack',
				'Smiley'       => 'Minor Regen',
			),
			/*
			 * THE LIPS CATCH EVERYONE. Only 416 Pioneers have a face
			 * tattoo and 250 a body tattoo, so on those two alone 801 of
			 * 1,231 fell to the same default charm. `lips` is on all of
			 * them and carries the collection's four colours, which is
			 * exactly four charms.
			 */
			'powerFallback' => array(
				array('~skull|code~i',        'Boost Attack'),
				array('~moons|forever~i',     'Regenerate'),
				array('~skateboard|amber~i',  'Heal'),
				array('~green~i',             'Regenerate'),
				array('~red~i',               'Boost Attack'),
				array('~violet~i',            'Heal'),
			),
			'powerDefault'  => 'Minor Regen',
			'powerWeapons'  => array('tattoo', 'lips'),
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
function monstrocity_trait_rarity($census, $slot, $value, $trim = '') {
	if (!is_array($census) || $value === null || $value === '') return 0.0;
	if (!isset($census['slots'][$slot]['values'])) return 0.0;
	$n = 0;
	/*
	 * THE EDITION NUMBER IS NOT PART OF THE TRAIT.
	 *
	 * Moebius Pioneers writes its values as "<item> #<edition>" -- "Black
	 * Coat - New #16", "Choker 2 #31" -- so the same coat appears under
	 * five hundred different names and every Pioneer looks like it is
	 * wearing a one-of-one. Trimming the suffix takes `body` from 718
	 * distinct values to 14 and `earjewellery` from 155 to 9, which is the
	 * difference between a collection that cannot be read and one that
	 * reads beautifully.
	 *
	 * Counts are SUMMED across everything that trims to the same thing, so
	 * the rarity of "Choker 1" is how many Pioneers wear a Choker 1 rather
	 * than how many wear that exact edition of one.
	 */
	$norm = function ($v) use ($trim) {
		$v = (string)$v;
		if ($trim !== '') $v = preg_replace($trim, '', $v);
		return mb_strtolower(trim($v));
	};
	$want = $norm($value);
	foreach ($census['slots'][$slot]['values'] as $v => $c)
		if ($norm($v) === $want) $n += (int)$c;
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
 * Collections that get the generic reading: policy => census slug.
 *
 * LISTED, NOT GUESSED FROM THE FILES ON DISK. A census file is a piece of
 * research and dropping one in a directory must not silently change what
 * every holder of that collection plays. Adding a line here is the moment
 * a collection starts being read, and it is a decision somebody makes.
 *
 * Absent from here AND from monstrocity_trait_map() means the identifier
 * hash, exactly as before -- which is still the right answer for a theme
 * whose project is not on the platform at all.
 */
function monstrocity_generic_index() {
	static $byPolicy = null;
	if ($byPolicy === null) $byPolicy = array(
		'590048dda6a624c887c0466b178c16fff3dedc2aac5651a9e0fc41b6' => 'vampire-invasion',
		'11ff0e0d9ad037d18e3ed575cd35a0513b8473f83008124db89f1d8f' => 'happyhoppers',
		'da286f15e0de865e3d50fec6fa0484d7e2309671dc4ba8ce6bdd122b' => 'omen',
		'1088b361c41f49906645cedeeb7a9ef0e0b793b1a2d24f623ea74876' => 'havoc-worlds',
		'e6ba9c0ff27be029442c32533c6efd956a60d15ecb976acbb64c4de0' => 'perps',
		'8972aab912aed2cf44b65916e206324c6bdcb6fbd3dc4eb634fdbd28' => 'ug',
		'637bae5845e801779651d997bedc2dad53ddbe0d5f940b4ff723d769' => 'meltingmoonboy',
		'a616aab3b18eb855b4292246bd58f9e131d7c8c25d1d1d7c88b666c4' => 'pendulum-order',
		'd458857ae7d121bfb5af64d78c0454a519133248e030a0225734452b' => 'wavyape',
		'bcc954e4d0483a292b753df87ab9a72b857f9ee8f61aa4c42e03df8c' => 'meltingmoongirls',
		'83732ff37818e7e520592fcd3e5257e429307d40a9f5437240e926de' => 'sinder-skullz',
		'b3dab69f7e6100849434fb1781e34bd12a916557f6231b8d2629b6f6' => 'black-flag',
		'e77fe5101469bdbd2d596f69abbd8ea6311008f5687dec3d950bb17a' => 'crypties-season-2',
		'ac9067e22a857ee2e4ea20f77c6c047f78a11511f285d30d81857196' => 'revrs-happy-people',
		'a4b7f3bbb16b028739efc983967f1e631883f63a2671d508023b5dfb' => 'the-fourth',
		'93ff51e7dfdf32314fd2f99ff222aa9c92f486b7d2cc0d46b64a9785' => 'apprentices',
		'f85be1a54efa2607de78a0f8855f9d73c9dd79bfa116f3767c015098' => 'proxy-by-heist-on-alpha',
		'243f0ca18ded6b45ba87655ce3917abc8e04830b23ba1f498de67a8a' => 'machine-headz-carnage-editio',
		'f4ae899bda2d478baec88620a06108619b231dfc6197be2a3b74a0ea' => 'machine-headz-chaos-edition',
		'8f63e08467bfbbc260a382a80f3297f9a569fca0293f296620d72d17' => 'machine-headz',
		'6710d32c862a616ba81ef00294e60fe56969949e0225452c48b5f0ed' => 'dph-afterdark',
		'b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5' => 'digital-hell-citizens-2-figh',
		'43158c3ad95b6c97a6d71138f9ec41a1a46f1156d8db4a92b289e73f' => 'dph-uni2',
		'89c01dc68d57169d9c02c3f35562d219884bbafa9d9f868c2f39ce67' => 'galactico-2',
		/*
		 * NOT INDEXED, AND EACH FOR A STATED REASON. All of these keep
		 * hashing their identifier, exactly as before.
		 *
		 * NOTHING READABLE -- only a name, a description, an image and a
		 * website:
		 *   fauna-x-nemonium 152, john-doe 57, skada 50, galaxy-of-sons 29,
		 *   yume 22, beelzebub 16, machine-headz-clan-leaders 14,
		 *   island-of-the-uncanny-neighbours 4
		 *
		 * ONE usable slot, where strength, tactics and speed are the same
		 * number wearing three hats:
		 *   shorty-verse 381, sh4pes-x-nemonium 343, bogeyman 206,
		 *   dph-genesis1 82, derivative-heroes 19, dph-genesis2 12,
		 *   dph-uni2-special 8
		 *
		 * TOO FEW REACHABLE BANDS. A stat must be able to land in at least
		 * five of its bands or the collection is handing out two or three
		 * cards to everybody -- the hash gives them MORE differentiation
		 * than their own traits can, which is the one case where it wins.
		 *
		 *   muses-of-the-multiverse 14,800  3/4/5 bands. The biggest
		 *       collection here and the clearest example: `multiverse` is
		 *       five values at exactly 2,960 each, so it carries no rarity
		 *       at all, and `rarity` has four. Twenty trait combinations
		 *       across 14,800 NFTs -- no mapping can produce more cards
		 *       than the collection has combinations. It states its own
		 *       tier ("300 of 700 [Common]"), so an AUTHORED map could at
		 *       least make those twenty mean something; rarity cannot.
		 *   drop-ship 321           2/5/3. Character is 20 values at ~16
		 *       each and Rank and Level are perfectly even. Wants an
		 *       authored map: Rank -> type, Level -> strength.
		 *   dph-genesis3 55, galactico-1 30, dph-amievil 32,
		 *   dph-2bdevilgirls 17, anime-origins 16, dph-demondolls 10
		 */
	);
	return $byPolicy;
}

/** The census slug for a policy on the generic reading, or null. */
function monstrocity_generic_policy($policy) {
	$byPolicy = monstrocity_generic_index();
	$policy = strtolower(trim((string)$policy));
	return isset($byPolicy[$policy]) ? $byPolicy[$policy] : null;
}

/** Is this census slug reachable through the index? Used by the harness to
    check only what the game can actually serve. */
function monstrocity_generic_policy_slug_indexed($slug) {
	foreach (array('3966cf1c948109e34f2c5a9f9670445ccc85008e5b8a6e67f913b491' => 'ancestors',
	               'f0eca3e1fbe2d3801eff903b755d06bed64f19c30aacb1f124d18d6c' => 'hellknights') as $p => $sl)
		if ($sl === $slug) return true;                   // authored
	foreach (array_keys(monstrocity_generic_index()) as $p)
		if (monstrocity_generic_policy($p) === $slug) return true;
	return false;
}

/* ---------- the generic mapper ---------------------------------------- *
 *
 * A COLLECTION CAN BE READ WITHOUT BEING UNDERSTOOD.
 *
 * The Ancestors map above says a crown means leadership and armour slows
 * you down. That took reading 432 trait values and knowing the project's
 * own fiction. It does not generalise: Happy Hoppers is Eyes, Hands, Hat,
 * Mouth, Skin and Wings, and deciding which hat means "strong" would be me
 * inventing someone else's lore.
 *
 * What CAN be read off any collection is how unusual a given NFT is, and
 * that is the thing a holder actually wants reflected. So a collection with
 * a census but no authored map still gets a card built from its own traits
 * -- just one that makes no claim about what those traits mean.
 *
 * THREE DIFFERENT STATISTICS OF ONE VECTOR, not three copies of it:
 *
 *   strength  your RAREST single trait. One standout piece.
 *   tactics   your MEDIAN trait. How good your typical piece is.
 *   speed     the inverse of your total rarity, less what you carry.
 *             Travelling light.
 *
 * Related, because a rarer NFT genuinely should be a better card -- but not
 * the same ranking three times, which is what a naive "sum it all up" gives
 * and which would leave one best card and nothing to choose between the
 * rest.
 */

/**
 * The slots worth scoring: real traits, not mint boilerplate.
 *
 * A slot with ONE value across the whole collection is the artist's name or
 * a copyright line. It scores -log10(1) = 0 for everybody so it is already
 * harmless, but it would still dilute the halves and show up in a player's
 * trait list, so it goes. Empty and numeric slot names go too: Dead Pop
 * Hell Genesis 3 was minted with slots called '', '0' and '1'.
 */
function monstrocity_generic_slots($census) {
	$out = array();
	if (!is_array($census) || empty($census['slots'])) return $out;
	foreach ($census['slots'] as $k => $v) {
		$name = trim((string)$k);
		if ($name === '' || ctype_digit($name)) continue;
		if (!isset($v['values']) || count($v['values']) < 2) continue;
		/*
		 * AND NOT AN IDENTIFIER. A slot where most holders have a value
		 * nobody else has is a serial number, not a trait -- "ID",
		 * "Serial Number", "R.I.P The Fallen", and most of Moebius
		 * Pioneers, whose `background` has 806 values across 1,231 assets.
		 *
		 * It breaks the scoring rather than merely adding noise. Strength
		 * is the RAREST trait you carry, so one near-unique slot pins
		 * almost everybody at -log10(1/N) -- all seven Moebius strength
		 * cuts came out as the same number and every Pioneer in the game
		 * showed strength 8. Reported from a roster screen.
		 */
		$cov = isset($v['coverage']) ? (int)$v['coverage'] : 0;
		if ($cov >= 20) {
			$ones = 0;
			foreach ($v['values'] as $c) if ((int)$c === 1) $ones++;
			if ($ones > $cov / 2) continue;
		}
		$out[] = (string)$k;
	}
	sort($out);
	return $out;
}

/** The three raw scores, before banding. Shared by the scorer and the
    census tool that computes the bands, so they cannot disagree. */
function monstrocity_generic_score($census, $traits) {
	$rs = array();
	foreach (monstrocity_generic_slots($census) as $slot) {
		if (!isset($traits[$slot]) || !is_scalar($traits[$slot])) continue;
		$rs[] = monstrocity_trait_rarity($census, $slot, (string)$traits[$slot]);
	}
	if (!$rs) return null;
	rsort($rs, SORT_NUMERIC);
	$total = array_sum($rs);
	$n = count($rs);
	/*
	 * THE MEDIAN, NOT THE WEAKEST LINK.
	 *
	 * tactics was the minimum -- "no gaps in the kit" -- which reads well
	 * and does not work. On a collection where every asset fills every slot
	 * almost everyone shares some very common trait, so the minimum barely
	 * moves: 11,089 of 11,111 Happy Hoppers landed in tactics band 1, and
	 * the Ancestors only ever reached three of the seven bands. A stat that
	 * is the same number for 99.8% of a collection is not a stat.
	 *
	 * The median moves on both shapes -- correlation with strength +0.17 on
	 * the Hoppers and +0.65 on the Ancestors -- and still says something
	 * legible: how good your TYPICAL piece is, rather than your best one.
	 */
	$mid = $n % 2 ? $rs[intdiv($n, 2)]
	              : ($rs[$n / 2 - 1] + $rs[$n / 2]) / 2;
	return array(
		'strength' => $rs[0],                       // the best piece
		'tactics'  => $mid,                         // the typical piece
		/* Travelling light: little rarity on you, and few slots filled. The
		   load term is constant for a collection where every asset fills
		   every slot, which is most of them -- it costs nothing there and
		   earns its place on the ones that vary. */
		'speed'    => -$total - 0.6 * count($rs),
		'total'    => $total,
	);
}

/**
 * The card a census alone can justify. Null when there is nothing to read.
 *
 * SIZE AND TYPE COME FROM TOTAL RARITY, which is a trade rather than a
 * straight reward: in Monstrocity a larger character gains health and loses
 * tactics. So the rarest NFTs are big and slow and hit hard, which is a
 * recognisable kind of card rather than a strictly better one.
 */
function monstrocity_generic_stats($census, $traits) {
	if (!is_array($census) || empty($census['cuts'])) return null;
	if (!is_array($traits) || !$traits) return null;
	$sc = monstrocity_generic_score($census, $traits);
	if ($sc === null) return null;
	$c = $census['cuts'];

	$sizeMap = array('Small', 'Medium', 'Large');
	$size = $sizeMap[min(2, monstrocity_trait_band($sc['total'], $c['size']) - 1)];

	/* The rarest fifth wear the crown; the plainest sixth have been through
	   something. Everyone else is Base. */
	$tb = monstrocity_trait_band($sc['total'], $c['type']);
	$type = $tb >= 3 ? 'Leader' : ($tb <= 1 ? 'Battle Damaged' : 'Base');

	/*
	 * THE POWER-UP IS KEYED ON THE RAREST TRAIT, not on the asset id.
	 *
	 * There is no generic reading of "this trait heals" -- that is exactly
	 * the knowledge a generic mapper does not have. Keying it on the trait
	 * at least makes it a visible, explainable relationship: two NFTs whose
	 * standout piece is the same share a charm. Keying it on the identifier
	 * would be the hash this whole exercise exists to replace.
	 */
	$best = ''; $bestR = -1.0;
	foreach (monstrocity_generic_slots($census) as $slot) {
		if (!isset($traits[$slot]) || !is_scalar($traits[$slot])) continue;
		$r = monstrocity_trait_rarity($census, $slot, (string)$traits[$slot]);
		if ($r > $bestR) { $bestR = $r; $best = $slot . '=' . mb_strtolower((string)$traits[$slot]); }
	}
	$pows = array('Minor Regen', 'Regenerate', 'Boost Attack', 'Heal');
	$powerup = $pows[crc32($best) % 4];

	return array(
		'strength' => monstrocity_trait_band($sc['strength'], $c['strength']),
		'speed'    => monstrocity_trait_band($sc['speed'],    $c['speed']),
		'tactics'  => monstrocity_trait_band($sc['tactics'],  $c['tactics']),
		'size'     => $size,
		'type'     => $type,
		'powerup'  => $powerup,
	);
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
/**
 * The raw figures an authored map produces, before banding.
 *
 * SEPARATE FROM THE BANDING because monstrocity-trait-census.php has to
 * compute the band thresholds from exactly these numbers, over the whole
 * collection. A second copy of the formula in the census tool would agree
 * on the day it was written -- the Ancestors cuts were computed that way
 * once, in another language, and disagreed with PHP badly enough to pile a
 * quarter of the collection into one band.
 */
function monstrocity_authored_score($map, $census, $traits) {
	if (!is_array($map) || !is_array($census) || !is_array($traits) || !$traits) return null;
	$get = function ($slot) use ($traits) {
		return isset($traits[$slot]) && is_scalar($traits[$slot]) ? (string)$traits[$slot] : '';
	};

	$trim = isset($map['valueTrim']) ? $map['valueTrim'] : '';
	/*
	 * A SLOT CAN BE WORTH MORE THAN ONE GARMENT. Strength sums what you
	 * are wearing, which rewards layering -- and a Moebius Sweeper Armor
	 * is a full-body suit, so the one Pioneer wearing it and nothing else
	 * summed to a single rarity and read as barely dressed. A suit counts
	 * for what it covers.
	 */
	$aw = isset($map['armsWeight']) ? $map['armsWeight'] : array();
	$arms = 0.0;
	foreach ($map['arms'] as $s) {
		$r = monstrocity_trait_rarity($census, $s, $get($s), $trim);
		$arms += isset($aw[$s]) ? $r * $aw[$s] : $r;
	}
	$mind = 0.0;
	foreach ($map['mind'] as $s) $mind += monstrocity_trait_rarity($census, $s, $get($s), $trim);

	$speedSlot = isset($map['speedBy']) ? $map['speedBy'] : $map['sizeBy'];
	$speedVal  = $get($speedSlot);
	$speed = null;
	/* Regex rules where the vocabulary is "<family> - <variant>" and it is
	   the family that matters -- a Flying Board is fast whichever colour. */
	if (!empty($map['speedRules']) && $speedVal !== '') {
		foreach ($map['speedRules'] as $rule)
			if (preg_match($rule[0], $speedVal)) { $speed = $rule[1]; break; }
	}
	if ($speed === null)
		$speed = isset($map['speed0'][$speedVal]) ? $map['speed0'][$speedVal] : $map['speedDefault'];
	/* Load by PRESENCE, for a collection where what you carry varies. */
	foreach ($map['weight'] as $s => $w) if ($get($s) !== '') $speed -= $w;
	/* Load by WEIGHT, for one where every slot is always filled and the
	   count tells you nothing. Tenths, like everything else here. */
	if (!empty($map['speedLoadBy'])) {
		$scale = isset($map['speedLoadScale']) ? (int)$map['speedLoadScale'] : 10;
		/*
		 * NOT ROUNDED. Rounding a rarity to a whole number collapses every
		 * armour with a similar count onto one value -- ten of them landed
		 * between 167 and 170 -- which stacked hundreds of knights on a
		 * single score and left speed band 2 empty, unreachable by anything
		 * in the collection.
		 *
		 * The float is safe here in a way it was not for the Ancestors:
		 * that score was a sum of FIXED weights, so exact ties on a band
		 * boundary were common and the last bit decided them. A rarity is
		 * continuous, so two assets tie only when their traits are equally
		 * common -- and then landing in the same band is correct.
		 */
		foreach ($map['speedLoadBy'] as $s)
			$speed -= monstrocity_trait_rarity($census, $s, $get($s), $trim) * $scale;
	}

	return array('strength' => $arms, 'tactics' => $mind, 'speed' => $speed);
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
	/*
	 * NO AUTHORED MAP, BUT A CENSUS: the generic reading. It makes no claim
	 * about what a trait MEANS, only about how unusual it is, which is the
	 * part that can be read off any collection -- see the long note above
	 * monstrocity_generic_slots().
	 */
	if (!$map) {
		$slug = monstrocity_generic_policy($policy);
		return $slug ? monstrocity_generic_stats(monstrocity_trait_census($slug), $traits) : null;
	}
	if (!is_array($traits) || !$traits) return null;
	$census = monstrocity_trait_census($map['slug']);
	if (!$census) return null;

	$get = function ($slot) use ($traits) {
		return isset($traits[$slot]) && is_scalar($traits[$slot]) ? (string)$traits[$slot] : '';
	};
	$sc = monstrocity_authored_score($map, $census, $traits);
	if ($sc === null) return null;

	$frame = $get($map['sizeBy']);
	/* A slot whose mere presence settles the frame, checked before the
	   usual one -- see the Moebius `suit` note. */
	$bigBySlot = false;
	if (!empty($map['sizeBigSlots']))
		foreach ($map['sizeBigSlots'] as $sl) if ($get($sl) !== '') $bigBySlot = true;
	/* ABSENT IS A FRAME TOO. A Pioneer wearing no outerwear at all is a
	   smaller silhouette than one in a Corpo Suit, and reading "nothing"
	   as the default middle would throw that away. */
	if ($bigBySlot) {
		$size = 'Large';
	} elseif ($frame === '' && isset($map['sizeAbsent'])) {
		$size = $map['sizeAbsent'];
	} elseif (isset($map['size'][$frame])) {
		$size = $map['size'][$frame];
	} elseif (!empty($map['sizeBig']) && $frame !== '' && preg_match($map['sizeBig'], $frame)) {
		$size = 'Large';
	} elseif (!empty($map['sizeSmall']) && $frame !== '' && preg_match($map['sizeSmall'], $frame)) {
		$size = 'Small';
	} else {
		$size = $map['sizeDefault'];
	}

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

	/* An authored map may carry its own cuts (the Ancestors does, computed
	   before the census tool learned to emit them) or lean on the
	   collection's own. Never both, and never neither. */
	$cuts = !empty($map['cuts']['strength']) ? $map['cuts']
	      : (isset($census['authoredCuts']) ? $census['authoredCuts'] : null);
	if (!$cuts) return null;

	return array(
		'strength' => monstrocity_trait_band($sc['strength'], $cuts['strength']),
		'speed'    => monstrocity_trait_band($sc['speed'],    $cuts['speed']),
		'tactics'  => monstrocity_trait_band($sc['tactics'],  $cuts['tactics']),
		'size'     => $size,
		'type'     => $type,
		'powerup'  => $powerup,
	);
}

}
