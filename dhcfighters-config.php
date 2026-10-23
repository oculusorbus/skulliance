<?php
/**
 * DHC FIGHTERS -- tunables
 *
 * Everything a designer would want to change without reading logic lives here:
 * which game pays which category, how good a drop is for a given achievement,
 * and how Fighters are numbered.
 */

/* ------------------------------------------------------------------ *
 * NUMBERING
 *
 * The minted collection is DHC2F001..DHC2F420 -- 226 actually minted, so the
 * range is full of holes. Those holes are NOT reused: they belong to the
 * collection if it ever completes, and reusing them would create a saved
 * assembly whose number later collides with a real mint.
 *
 * Assemblies continue the collection's own naming, starting after the top of
 * the minted range. They are NOT NFTs and cannot be minted -- the restriction
 * is carried by the interface wording rather than by the serial, which keeps
 * the numbering unbroken.
 * ------------------------------------------------------------------ */
define('DHCF_SERIAL_PREFIX', 'DHC2F');
define('DHCF_SERIAL_START',  421);          // first number after the collection's range
define('DHCF_SERIAL_PAD',    3);            // DHC2F421, DHC2F1000 when it gets there

/**
 * TEMPORARILY NOT DROPPING.
 *
 * Every torso is drawn with arms, so an Arms trait overlays limbs that are
 * already there. Most arms sit close enough to cover what is beneath; these
 * six do not, and read as two sets of arms until the hand-made armless torso
 * variants exist.
 *
 * Suspended from the DRAW only -- not removed. A player already holding one
 * keeps it, can still place it and still scores for it: taking back an awarded
 * trait would be far worse than an imperfect render. They simply stop being
 * handed out.
 *
 * EMPTIED 2026-09-15. All 25 armless torsos are hand-made and live, and each
 * of the six was checked against them before being let go: the four that
 * replace both limbs cover 78-100% of what is beneath, Head Chopper replaces
 * one and routes through the hybrid torso, and the Perforator covers neither
 * because it is drawn behind as an accent. See dhcf_armless_mode().
 *
 * Kept as the mechanism, not deleted. Add a slug here to pull a trait from the
 * drop pool without taking it away from anyone who already holds one.
 */
/**
 * Maxingo's project id. His own missions pay his own trait art, which is the
 * neatest fit on the platform -- and unlike the games, missions are something
 * the mission-and-dailies players already do.
 */
define('DHCF_MAXINGO_PROJECT', 9);

define('DHCF_SUSPENDED', array());

/**
 * ORIGINALITY BONUS -- what the first staker to build a configuration earns.
 *
 * A bonus for discovery, never a penalty for duplication. If a copied Fighter
 * lost points, the original builder would be marked down by somebody else's
 * action, which is the same unfairness that made blocking duplicate builds the
 * wrong answer: it punishes draw order rather than effort.
 *
 * 15% is enough to matter against scores in the hundreds without letting a
 * plain-but-unique build beat a genuinely rare one.
 */
define('DHCF_ORIGINALITY_BONUS', 0.15);

/** Bumped when a layering or exclusion rule changes; stamped on each save. */
define('DHCF_RULES_VERSION', '1');

/* ------------------------------------------------------------------ *
 * SLOTS
 *
 * Ten render slots, eight trait categories. Both weapon slots draw from the
 * same category, as do both effects slots -- owning a trait lets you place it
 * in either, subject to the front/back partition the assembler enforces.
 * ------------------------------------------------------------------ */
function dhcf_slots() {
	return array('background', 'weaponBack', 'torso', 'weapon', 'arms',
	             'effects1', 'effects2', 'head', 'headgear', 'companion');
}

function dhcf_slot_category($slot) {
	switch ($slot) {
		case 'weapon': case 'weaponBack': return 'weapon';
		case 'effects1': case 'effects2': return 'effects';
		default: return $slot;
	}
}

/*
 * A FIGHTER NEEDS A BODY. Background, torso and head are mandatory; everything
 * else is decoration. This is also why those three categories only drop from
 * games that cannot be lost -- gating a required part behind a win could leave
 * a player permanently unable to save anything.
 */
define('DHCF_REQUIRED', array('background', 'torso', 'head'));

/*
 * The artist, and where to send people who want to see more of the work.
 *
 * ONE DEFINITION because it belongs in every DHC intro -- the assembler, the
 * Collection, the sandbox and the marketing page all credit Maxingo, and a
 * credit that is a link on one page and plain text on the next is a credit
 * nobody follows. dhcgame.php had this URL hardcoded as $artist_x first; it
 * reads it from here now.
 *
 * NOTE: homepage.php's partner row links Maxingo to x.com/madmaxi__, a
 * different handle. That one is deliberately left alone -- it is a partner
 * listing rather than a DHC credit, and which account is current is not
 * something this file should guess at.
 */
define('DHCF_ARTIST',   'Maxingo');
define('DHCF_ARTIST_X', 'https://x.com/MMAXI404');

/** The artist's name as a link, for the intros that credit him. */
function dhcf_artist_link($text = null) {
	return '<a href="' . DHCF_ARTIST_X . '" target="_blank" rel="noopener">'
	     . htmlspecialchars($text === null ? DHCF_ARTIST : $text) . '</a>';
}

/*
 * The minted collection the trait art comes from, and the only place a
 * genuinely ownable Fighter exists. Same reasoning as the artist link: the
 * rights notice on dhcfighters.php, the subtitle above it, dhcgame.php's
 * credit block and the Skull Paper all point at this one policy, and a
 * notice that says "the genuine ones are over there" without saying WHERE
 * is doing half a job.
 *
 * The policy id is the marketplace identifier on Cardano -- see
 * multichain-schema.md. dhcrarity.php reads the same policy from Koios.
 */
define('DHCF_POLICY', 'b31a34ca2b08bfc905d2b630c9317d148554303fa7f0d605fd651cb5');
define('DHCF_COLLECTION_URL', 'https://www.wayup.io/collection/' . DHCF_POLICY);

/** The minted collection as a link, for the notices that point at it. */
function dhcf_collection_link($text = 'Digital Hell Citizens 2: Fighters') {
	return '<a href="' . DHCF_COLLECTION_URL . '" target="_blank" rel="noopener">'
	     . htmlspecialchars($text) . '</a>';
}

/** Required slots the layout is missing, by category name. Empty means valid. */
function dhcf_missing_required($traits) {
	$missing = array();
	foreach (DHCF_REQUIRED as $slot) {
		if (empty($traits[$slot])) $missing[] = $slot;
	}
	return $missing;
}

function dhcf_categories() {
	return array('background', 'torso', 'head', 'headgear', 'effects', 'arms', 'weapon', 'companion');
}

/** Human label for a trait, from the art index when present. */
function dhcf_trait_name($category, $slug) {
	static $idx = null;
	if ($idx === null) {
		$idx = array();
		foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
			$f = __DIR__ . '/' . $c . '/trait-index.json';
			if (is_file($f)) { $idx = json_decode(file_get_contents($f), true) ?: array(); break; }
		}
	}
	if (isset($idx[$category][$slug]['name'])) return $idx[$category][$slug]['name'];
	return ucwords(str_replace('-', ' ', $slug));
}

/* ------------------------------------------------------------------ *
 * LAYERING RULES -- the single source.
 *
 * The assembler emits these into its JS and the server-side compositor reads
 * them directly, so the picture Discord shows is drawn by the same rules as
 * the picture the player assembled. Two copies of this would drift, and every
 * one of these exceptions was expensive to find.
 * ------------------------------------------------------------------ */

/*
 * THE FOUR AXES a Fighter is ranked on, in display order, as slug => label.
 *
 * dhcgallery.php's sort buttons named these and the assembler's live preview
 * chips show them, so they live here rather than in either -- "Deadliest"
 * meaning one thing on the Collection and another on the canvas is the whole
 * failure this prevents. What each one IS lives in dhc-ranks.php
 * (dhcf_rank_values); this is only their names and their order.
 *
 * Here and not in dhc-ranks.php because the assembler needs the labels and
 * already requires this file, while dhc-ranks.php drags in the library and
 * the Arena engine.
 */
function dhcf_rank_axes() {
	return array(
		'rarest' => 'Rarest',
		'might'  => 'Deadliest',
		'tough'  => 'Toughest',
		'power'  => 'Hardest hitting',
	);
}

/*
 * Companions that belong against the body, drawn below Arms rather than last.
 *
 * DH VISION SHOULDER CAM CAME OFF THIS LIST. It was put on it because a
 * shoulder mount drawn in front of the character looks stuck to the outside
 * of it -- but "below Arms" is also below Effects, the head and the headgear,
 * and a headgear piece drawn over a shoulder cam looks worse than the problem
 * it was solving. There is no position in a flat stack that is under the arms
 * and over the head, so it goes back to the default: last, like every
 * ordinary companion.
 *
 * AND THEN CODE SEA PREDATOR CAME OFF IT TOO, for exactly the same reason the
 * cam did. "Wraps the body and still reads correctly underneath" was true of
 * the torso and false of the head: demoted below Arms is also below the head
 * and the headgear, so a big piece -- Alien Predator, Alien Skull -- buried
 * the creature's own head. Reported from the Collection with five Fighters
 * showing it. It takes the cam's solution: stay last, and move the ARMS
 * forward instead (DHCF_ARMS_OVER_COMPANION).
 *
 * THE LIST IS NOW EMPTY, and the rule that reads it is kept anyway. Nothing
 * about the mechanism was wrong -- a companion really can want to sit against
 * the body -- it is just that both of its members turned out to want the head
 * more. Deleting the rule would mean rediscovering it the next time.
 */
define('DHCF_COMPANION_UNDER', array());

/*
 * COMPANIONS THE ARMS DRAW OVER -- without dropping them below the head.
 *
 * The DH Vision Shoulder Cam wants two things at once: above the head and the
 * headgear (a headgear piece drawn over a shoulder mount looks broken), and
 * under the arms (an arm passing in front of the shoulder should pass in
 * front of what is bolted to it). The slot order runs arms, effects, head,
 * headgear, companion -- so there is no position for the COMPANION that
 * satisfies both.
 *
 * So the arms move instead. With the cam on, Arms is drawn last, above the
 * companion. Everything else keeps its place.
 *
 * WHAT THIS COSTS, and it is a real trade: Arms then also draws over the head
 * and the headgear. Checked against the art -- the arm traits hang at the
 * sides and do not reach the face -- but a future arm that raises a hand
 * above the shoulder would cover it, and the fix then is to take the cam off
 * this list, not to add a per-arm exception on top of a per-companion one.
 *
 * Not applied to arms that are themselves drawn behind the torso
 * (DHCF_ARMS_BEHIND_TORSO): those are accents flanking the body, and hoisting
 * them to the very front is the opposite of what they are for.
 *
 * CODE SEA PREDATOR JOINED, off the back of the same report that took it out
 * of DHCF_COMPANION_UNDER: it wants the head and the headgear underneath it,
 * and an arm crossing the body in front of it.
 */
define('DHCF_ARMS_OVER_COMPANION', array('dh-vision-shoulder-cam', 'code-sea-predator'));

/*
 * ARMS THAT STAY BEHIND A COMPANION THAT OTHERWISE DRAWS OVER IT.
 *
 * THE NOTE ABOVE ADVISES AGAINST THIS, and it is worth saying why this is not
 * the case it was warning about. That warning is about an arm that COVERS the
 * companion -- the answer there is to take the companion off the hoist list,
 * because the arm is doing its job. This is the opposite: Turret Blue Cyborg
 * Limbs are bulky enough to swallow the creature rather than cross in front
 * of it, so the companion belongs in front of THEM specifically while still
 * passing behind every ordinary arm.
 *
 * Those limbs are already recognised as unusually big elsewhere for the same
 * reason -- they are in DHCF_TWOPART_OVER_ARMS, which puts the whole Demon in
 * front of them. Two independent readings of the art agreeing is the reason
 * this is a per-arm exception rather than a second opinion.
 *
 * Keyed by companion, listing the arms, so this cannot become a blanket rule
 * by accident.
 */
define('DHCF_ARMS_UNDER_COMPANION', array(
	'code-sea-predator' => array('turret-blue-cyborg-limbs'),
));

/*
 * HEADGEAR THAT DRAWS OVER A PARTICULAR COMPANION.
 *
 * A companion is last in dhcf_slots(), so it draws over the headgear, and for
 * the floating pets that is right -- they hover in front of the shoulder.
 * The Saber Tech Headgear is the exception: its pipe comes down off the helmet
 * and past the shoulder, and the DH Vision Shoulder Cam is BOLTED to that
 * shoulder. A pipe running behind the mount reads as the pipe passing through
 * the character; nested over it, it reads as a pipe lying across a fitting,
 * which is what the art draws.
 *
 * KEYED BY PAIR. The cam still draws over every other headgear, and this
 * helmet still draws under every other companion -- it is the two specific
 * pieces whose shapes meet, not a rule about helmets or about cams.
 *
 * THE THIRD PAIRWISE EXCEPTION OF THIS SHAPE (see DHCF_ARMS_OVER_HEADGEAR and
 * DHCF_ARMS_UNDER_COMPANION). They are deliberately separate constants rather
 * than one table keyed by slot pair: each is read by a different step of
 * dhcf_layer_order() and they fire at different points in the sequence, so
 * merging them would mean one table whose rows mean different things depending
 * on which two slots they name. If a fourth lands, revisit that.
 */
define('DHCF_HEADGEAR_OVER_COMPANION', array(
	'saber-tech-headgear' => array('dh-vision-shoulder-cam'),
));

/** Does this headgear draw in front of this companion? */
function dhcf_headgear_over_companion($headgear, $companion) {
	if ($headgear === '' || $companion === '') return false;
	$x = DHCF_HEADGEAR_OVER_COMPANION;
	return isset($x[$headgear]) && in_array($companion, $x[$headgear], true);
}

/** Do these arms stay behind this companion, despite the hoist rule? */
function dhcf_arms_under_companion($companion, $arms) {
	if ($companion === '' || $arms === '') return false;
	$x = DHCF_ARMS_UNDER_COMPANION;
	return isset($x[$companion]) && in_array($arms, $x[$companion], true);
}

/** Effects that read as environment: dropped to just behind the torso. */
define('DHCF_EFFECTS_BEHIND_TORSO', array('xlon-s-black-fire-attack'));

/*
 * THE COMIC COVERS. Not an effect that happens to the Fighter -- a frame the
 * Fighter is standing inside, masthead and price box and barcode. The
 * character deliberately breaks out of it: head, headgear, weapon and a
 * normal companion all draw over the frame, which is the look.
 *
 * dhc-download-harness.php checks this list against the art on disk, so a
 * fourth cover cannot be added and silently miss the rule below.
 */
define('DHCF_COVER_EFFECTS', array(
	'dhc2-comic-cover-1', 'dhc2-comic-cover-2', 'dhc2-comic-cover-3',
	'dhc2-comic-cover-4',
));

/*
 * COMPANIONS THAT STAY ON TOP OF A COVER.
 *
 * A demoted companion (see DHCF_COMPANION_UNDER) is moved to just before
 * Arms, which is what the shoulder-cam rule is for -- but the slot order
 * runs arms, effects, head, headgear, so "below Arms" unavoidably means
 * below the Effects slots too. With a comic cover in one of them the frame
 * ended up drawn OVER the companion, while every ordinary companion drew
 * over the frame. Same Fighter, two opposite answers depending on which pet
 * it carried.
 *
 * There is no position in a flat stack that is below Arms and above Effects,
 * so the cover moves instead: it drops to just before the companion. The
 * companion stays against the body and the frame stays behind both.
 *
 * DH Vision Shoulder Cam is NOT on this list. It has exactly the same
 * problem and adding it is one word, but it was not what was asked for and
 * it changes the look of Fighters already saved.
 *
 * Every entry here must also be in DHCF_COMPANION_UNDER. A companion that
 * was never demoted still draws last, and pushing the cover below it would
 * put the frame over the head instead -- the harness asserts this.
 */
/* EMPTY NOW, and the rule is kept for the same reason DHCF_COMPANION_UNDER's
   is: this only ever matters for a DEMOTED companion, and there are none. An
   undemoted companion is already last, which is above both effects slots, so
   a cover cannot get in front of it and there is nothing to correct. */
define('DHCF_COMPANION_OVER_COVER', array());

/**
 * Arms drawn BEHIND the torso: accents flanking the body, not replacements for
 * its arms. Permanent -- the Perforator's cutoffs are not clean enough to sit
 * over an armless torso, and it reads better as an accent anyway. Members are
 * exempt from the armless-torso swap; see dhc-assembler.php.
 */
define('DHCF_ARMS_BEHIND_TORSO', array('perforator-arm-replacement'));

/**
 * SINGLE-SIDED ARMS -- arms that replace one limb, not both.
 *
 * Head Chopper only covers the image-LEFT arm. Swapping in the armless torso
 * for it therefore deletes the other arm and leaves a stump, but drawing it
 * over the normal torso does not work either: measured against the armless
 * variants it covers only 80-88% of the arm beneath it, so the original shows
 * around the edge.
 *
 * So the torso is drawn as TWO layers: the armless variant, then the NORMAL
 * torso clipped to the half this trait does not cover, which puts that one arm
 * back. No new art -- the body is pixel-identical in both files, so the join is
 * invisible provided it falls between the arms rather than through one.
 *
 * The value is which half of the normal torso to keep.
 */
define('DHCF_ONE_ARM', array('head-chopper' => 'right'));

/**
 * Where that join sits, as a percentage of width. The centre: across all 25
 * torsos the left arm always ends by x=299 and the right never starts before
 * x=723 (of 1000), so 50% clears both by ~200px. Any value in that window
 * works; there is nothing to tune per torso.
 */
define('DHCF_ONE_ARM_SPLIT', 50);

/**
 * Arms that sit cleanly OVER a normal torso, so the armless variant is not
 * used at all. Infested Robo Limb is one: it replaces a single limb and covers
 * what is under it well enough that the torso's own arms can stay.
 *
 * The difference from DHCF_ONE_ARM is what happens to the OTHER arm. Head
 * Chopper needs the armless torso because its own silhouette does not hide the
 * limb beneath, so that limb is deleted and the far one restored; these keep
 * both of the torso's arms and simply cover one.
 */
define('DHCF_ARMS_OVER_TORSO', array('infested-robo-limb'));

/**
 * HOW AN ARMS TRAIT TREATS THE TORSO BENEATH IT. One answer for every renderer
 * -- the canvas, the Discord render and the card grids all have to agree, and
 * they were each growing their own copy of these conditions.
 *
 *   'full'    swap in the armless torso: the arm replaces both limbs
 *   'hybrid'  armless torso, plus the normal torso's other half put back
 *   'none'    leave the torso alone; it keeps its own arms
 */
function dhcf_armless_mode($arms) {
	if (empty($arms))                                        return 'none';
	if (in_array($arms, DHCF_ARMS_BEHIND_TORSO, true))       return 'none';
	if (in_array($arms, DHCF_ARMS_OVER_TORSO, true))         return 'none';
	if (isset(DHCF_ONE_ARM[$arms]))                          return 'hybrid';
	return 'full';
}

/*
 * TETHERED WEAPON HALVES -- two art files that are one weapon.
 *
 * The scythe and the skull krusher were separated by hand into the weapon
 * itself (behind the body) and the sash it hangs from (in front of it). Neither
 * half reads on its own: a sash with nothing hanging from it is not a trait,
 * and the weapon floats without one. The assembler therefore treats them as one
 * pick -- choosing either brings the other, dropping either drops both.
 *
 * Which is why the DROP has to hand out both as well. The rarity table is keyed
 * by art directory, so both halves sit in the weapon pool as separate entries
 * and a draw could land a lone sash -- a trait the player owns and cannot build
 * with until its partner happens to drop too, refused at save with a shortfall
 * message about a trait they never had.
 *
 * Mutual pairs ONLY. The axe pulls in the electric morning star in the
 * assembler, but the morning star is a weapon in its own right and stands alone
 * perfectly well, so it is a UI convenience rather than a tether and is not
 * listed here. That distinction is `mutual` in the assembler's COUPLE table,
 * which reads its mutual pairs from this constant so the two cannot drift.
 */
define('DHCF_TETHERED', array(
	'scythe'             => 'scythe-sash',
	'scythe-sash'        => 'scythe',
	'skull-krusher'      => 'skull-krusher-sash',
	'skull-krusher-sash' => 'skull-krusher',
));

/** The other half of a tethered weapon, or '' when a trait stands alone. */
function dhcf_tethered_partner($slug) {
	$t = DHCF_TETHERED;
	return isset($t[$slug]) ? $t[$slug] : '';
}

/*
 * Marks a ledger row as the second half of a tethered pair rather than a drop
 * in its own right. Written into source_detail, and excluded from the daily cap
 * count -- one draw that happens to be two pieces must not cost two of the
 * three traits a day a source is allowed to give.
 */
define('DHCF_PAIRED_MARK', 'paired:');

/** Vertical nudge in pixels of the 1000px master, positive = down. */
define('DHCF_NUDGE', array('skull-krusher' => 23, 'skull-krusher-sash' => 13, 'axe' => 13));

/*
 * A COMPANION THAT IS ATTACHED, NOT FLOATING.
 *
 * Every other companion is a mass hovering at the shoulder, so one layer over
 * the top of everything is the whole of it. The Demon is gripping the Fighter:
 * its body stands BEHIND the torso and its claws come over the front, around
 * the head. One trait, two pieces of art, two places in the stack.
 *
 * The art pairs by SLUG across two directories, exactly as torso-noarms pairs
 * with torso:
 *
 *     companion/<slug>.png        the body, drawn behind the torso
 *     companion-front/<slug>.png  the claws, drawn over the head
 *
 * companion-front is deliberately NOT a slot. dhc-assembler.php's $dhc_slots
 * drives the sandbox's per-directory glob(), so a directory that is not a slot
 * never becomes a selectable trait -- which is what keeps this one trait
 * rather than two.
 *
 * WHERE THE CLAWS GO, and it is a compromise the stack forces. They must sit
 * above the head or the grip does not read at all -- rendered both ways, and
 * below the head the claw simply disappears behind the skull. Headgear stays
 * on top of them. Arms end up underneath, because dhcf_slots() already draws
 * head over arms globally, so "above the head but below the arms" is not
 * expressible in one pass. In practice the claws sit at head height and rarely
 * meet an arms trait at all.
 */
define('DHCF_COMPANION_TWO_PART', array('demon-companion'));

/*
 * WHERE THE TWO HALVES GO WHEN THE DEFAULT IS WRONG.
 *
 * The default is body behind the torso, claws over the head and under the
 * headgear. Five kinds of trait break it, all found by assembling them in the
 * sandbox rather than by reasoning about the art:
 *
 * These lists name the OTHER trait, not the companion, because there is one
 * two-part companion and the exceptions are properties of what it is worn
 * with. A second one would want them keyed by companion.
 */

/* Torsos the body must sit IN FRONT of. Inferno Wasp Guardian has wings that
   the body would otherwise hide behind. */
define('DHCF_TWOPART_BODY_OVER_TORSO', array(
	'inferno-wasp-guardian', 'red-cyborg',
));

/* Arms that must cover the claws. The claws normally draw above the head, and
   dhcf_slots() puts arms BELOW the head, so the only way an arm can cover them
   is for that arm to move above the head too -- which is the same move
   DHCF_ARMS_OVER_COMPANION already makes for the shoulder cam. */
define('DHCF_TWOPART_HANDS_UNDER_ARMS', array(
	'skeletal-stabber', 'cyber-666-knight-armor', 'connected-protector-limbs',
	'deflektor', 'dh-samurai-augmented-arms',
));

/* Arms the WHOLE demon sits in front of -- body included, so the body leaves
   its place behind the torso entirely. */
define('DHCF_TWOPART_OVER_ARMS', array(
	'turret-blue-cyborg-limbs', 'robo-crusher-arms',
));

/* Headgear the claws must sit in front of, rather than under. */
define('DHCF_TWOPART_HANDS_OVER_HEADGEAR', array(
	'merged', 'ww2-helmet', 'cyber-insekt-helmet', 'xlon-implant', 'dh-samurai-helmet',
));

/* Heads the demon cannot be worn with at all. Same shape and same reasoning as
   DHCF_HEADGEAR_EXCLUDED_BY_HEAD: keyed by head, listing what it refuses. */
define('DHCF_COMPANION_EXCLUDED_BY_HEAD', array(
	'beheaded-cyborg'   => array('demon-companion'),
	'cyborg-stryker-02' => array('demon-companion'),
));

/*
 * ARMS THAT GO IN FRONT OF A PARTICULAR HEADGEAR.
 *
 * Headgear draws over arms everywhere else and should: a helmet is worn on the
 * head, which is in front of the shoulders. The DH Samurai set breaks that,
 * because the helmet's neck flare and the suit's shoulder plates occupy the
 * same band -- and the flare is a soft hanging guard that belongs BEHIND a
 * rigid pauldron, so with the default order the helmet reads as cutting the
 * shoulder in half.
 *
 * KEYED BY PAIR, NOT BY EITHER TRAIT ALONE, and that is from measurement
 * rather than caution. Generalising it either way was rendered and rejected:
 *   - "the samurai helmet goes under every arm" changes 17 combinations, and
 *     Skeletal Stabber (which covers 28.9% of the helmet) swallows it whole;
 *   - "the samurai arms go over every headgear" changes 22, and the DH Manta
 *     Ray (12.9%) and Tank (10.5%) helmets are broad flat pieces that then sit
 *     behind the shoulder spikes, which is the same fault in reverse.
 * Only the two samurai pieces are drawn to overlap this way, so only that pair
 * is listed.
 */
define('DHCF_ARMS_OVER_HEADGEAR', array(
	'dh-samurai-helmet' => array('dh-samurai-augmented-arms'),
));

/** Do these arms draw in front of this headgear? */
function dhcf_arms_over_headgear($headgear, $arms) {
	if ($headgear === '' || $arms === '') return false;
	$x = DHCF_ARMS_OVER_HEADGEAR;
	return isset($x[$headgear]) && in_array($arms, $x[$headgear], true);
}

/** Is this companion refused by this head? */
function dhcf_companion_blocked($head, $companion) {
	if ($head === '' || $companion === '') return false;
	$x = DHCF_COMPANION_EXCLUDED_BY_HEAD;
	return isset($x[$head]) && in_array($companion, $x[$head], true);
}

/** Which slot the claws are drawn immediately after. */
function dhcf_twopart_hands_after($traits) {
	$gear = isset($traits['headgear']) ? $traits['headgear'] : '';
	if ($gear !== '' && in_array($gear, DHCF_TWOPART_HANDS_OVER_HEADGEAR, true)) return 'headgear';
	return 'head';
}

/** Is this companion the attached kind, with a second piece over the front? */
function dhcf_companion_two_part($slug) {
	return $slug !== '' && in_array($slug, DHCF_COMPANION_TWO_PART, true);
}

/**
 * TRAITS THAT DO NOT FALL WHEN A FIGHTER DIES.
 *
 * The Arena's death sequence drops each layer out of the composite
 * (keyframes disA/disB in dhcarena.php). That is wrong for a piece drawn as
 * SUSPENDED FROM THE TOP OF THE FRAME: its art is cut off at the edge
 * because it is meant to read as continuing past it, so the moment it
 * translates down the cut is exposed and the frame edge shows.
 *
 * bio-circuit-head-port is the case that found this -- a head port on cables
 * that run up and off the top. Pinned, the body falls out of it and it stays
 * hanging, which is both the correct picture and a better death than
 * everything sliding down together.
 *
 * MEASURED, NOT GUESSED: of 21 pieces whose art touches the top edge, the
 * effects slots are all exempt already (they use `snuff`, which fades and
 * scales UP, so it cannot open a gap). The remaining candidates in a falling
 * slot are arms/dh-pilot, arms/symbio-flexx and companion/mk100-insekt --
 * only the last reads as hanging rather than reaching, and none has been
 * reported, so they are left alone pending a look.
 */
define('DHCF_DEATH_ANCHORED', array('bio-circuit-head-port'));

/** Weapons drawn against the torso's own arms; cannot coexist with MOST Arms. */
define('DHCF_ARMS_EXCLUSIVE', array('plastic-blaster', 'dh-raider-equipment', 'electric-morning-star'));

/**
 * DOES THIS ARMS TRAIT ACTUALLY CLASH WITH THIS WEAPON?
 *
 * The exclusive weapons are drawn against the torso's own arms, so the real
 * question is not "are there arms?" but "is the arm this weapon rests on
 * still visible?". Three cases, and only one of them is safe:
 *
 *   BEHIND_TORSO  drawn under everything, so it cannot cover the native arm
 *                 or the weapon. SAFE -- perforator-arm-replacement's spikes
 *                 read as an accent behind the shoulder and the blaster is
 *                 untouched.
 *
 *   OVER_TORSO    keeps the native arms but draws OVER them, and over the
 *                 weapon too, because arms come after weapon in dhcf_slots().
 *                 Infested Robo Limb swallows the blaster's grip and trigger
 *                 guard, so the gun reads as embedded in the limb rather than
 *                 held. Its own comment says it "covers what is under it" --
 *                 which here includes the arm doing the holding. BLOCKED.
 *
 *   full/hybrid   the native arm is replaced outright. BLOCKED.
 *
 * Checked against renders rather than reasoned: the first version of this
 * exempted everything dhcf_armless_mode() called 'none', which is both of the
 * first two cases, and the infested render showed that was wrong.
 */
function dhcf_arms_weapon_clash($arms, $weapon) {
	if (empty($arms) || empty($weapon))                     return false;
	if (!in_array($weapon, DHCF_ARMS_EXCLUSIVE, true))      return false;
	if (in_array($arms, DHCF_ARMS_BEHIND_TORSO, true))      return false;
	return true;
}

/*
 * HEADGEAR A GIVEN HEAD CANNOT WEAR.
 *
 * Keyed by head slug, listing the headgear that will not sit on it. Beheaded
 * Cyborg is the case that created this: there is no skull under the headgear to
 * carry these pieces, so they read as floating rather than worn. Not every piece
 * fails -- most of the 32 still work -- so this is an explicit list per head
 * rather than a blanket "headless heads wear nothing" rule.
 *
 * THE MINTED COLLECTION OVERRULES THIS LIST. The pairings here were judged by
 * eye, and one of them -- beheaded-cyborg + trojan-detection-mask -- is a
 * Fighter that actually exists: DHC2F043, confirmed against the CIP-25
 * metadata under the DHC2 policy. Calling a canonical Fighter unbuildable is a
 * worse fault than any layering oddity, so it came off the list. The art says
 * why it belongs off: the Trojan mask is a full faceplate that encloses the
 * neck stump, so unlike a helmet it needs no skull beneath it.
 *
 * All 14 were checked the same way, not just the reported one. Only two
 * Beheaded Cyborgs in the mint wear headgear at all (DHC2F038 Cyber Purr
 * Helmet, which was never excluded, and DHC2F043), so the other 13 exclusions
 * have no mint precedent and stand. dhc-mint-harness.php re-runs that check
 * against a committed snapshot of the metadata.
 *
 * Enforced in three places, which is why it lives here rather than in any one of
 * them: dhcf_layers() for every PHP renderer, the canvas's own paint() in
 * dhc-assembler.php, and blockedReason() in the picker so the pairing cannot be
 * made in the first place. A Fighter saved BEFORE a rule lands still holds the
 * pairing -- the ledger is the record and its traits are not at risk -- so the
 * renderers have to drop the layer rather than assume it can never occur.
 */
define('DHCF_HEADGEAR_EXCLUDED_BY_HEAD', array(
	'beheaded-cyborg' => array(
		'merged',
		'steel-viking-helmet',
		'666-demon-headgear',
		'dh-fire-goggles',
		'killer-phantom-mask',
		'ww2-helmet',
		'arachno-neural-implant',
		'code-prisoner-helmet',
		'dm-mask',
		'golden-cyber-plague-detecting-mask',
		'mk200-cyber-plague-vr-mask',
		/* NOT trojan-detection-mask: DHC2F043 wears exactly this pairing. */
		'xlon-implant',
		'malware-detecting-mask',
	),
));

/** Whether $headgear is refused by $head. Empty either side is never blocked. */
function dhcf_headgear_blocked($head, $headgear) {
	if (empty($head) || empty($headgear)) return false;
	$excluded = DHCF_HEADGEAR_EXCLUDED_BY_HEAD;
	return isset($excluded[$head]) && in_array($headgear, $excluded[$head], true);
}

/**
 * Draw order for a saved layout, exceptions applied. Mirrors layerOrder() in
 * the assembler's JS -- same inputs, same output, minus the drag handling the
 * server has no use for.
 */
function dhcf_layer_order($traits) {
	$order = dhcf_slots();

	if (!empty($traits['companion']) && in_array($traits['companion'], DHCF_COMPANION_UNDER, true)) {
		$order = dhcf_move_before($order, 'companion', 'arms');
	}
	/* An ATTACHED companion goes further back than a demoted one: behind the
	   TORSO, not merely behind the arms, because its body is standing behind
	   the Fighter rather than hovering beside it. Its front half is not a slot
	   and is added by dhcf_layers().

	   Three exceptions, each found in the sandbox:
	     - some torsos have parts the body must not hide (wings), so it moves
	       back in FRONT of the torso;
	     - some arms the whole demon sits in front of, which takes the body
	       further forward still;
	     - some arms have to cover the CLAWS, and since the claws draw above
	       the head and arms draw below it, the only way is to lift that arm
	       above the head -- the same move DHCF_ARMS_OVER_COMPANION makes. */
	/* Skipped when a head refuses it, matching the canvas: a companion that
	   will not be drawn has no business being repositioned, and the two must
	   agree about the order or the harness that compares them is right to
	   complain. */
	if (!empty($traits['companion']) && dhcf_companion_two_part($traits['companion'])
	    && !dhcf_companion_blocked(isset($traits['head']) ? $traits['head'] : '', $traits['companion'])) {
		$torso = isset($traits['torso']) ? $traits['torso'] : '';
		$arms2 = isset($traits['arms'])  ? $traits['arms']  : '';

		if ($arms2 !== '' && in_array($arms2, DHCF_TWOPART_OVER_ARMS, true)) {
			$order = dhcf_move_after($order, 'companion', 'arms');
		} elseif ($torso !== '' && in_array($torso, DHCF_TWOPART_BODY_OVER_TORSO, true)) {
			$order = dhcf_move_after($order, 'companion', 'torso');
		} else {
			$order = dhcf_move_before($order, 'companion', 'torso');
		}

		/* After the HEAD, not last. dhcf_layers() inserts the claws immediately
		   after the head slot, so head > claws > arms > headgear puts the arm
		   over the claws without also lifting it over the helmet -- which
		   dhcf_move_last() would have done. */
		if ($arms2 !== '' && in_array($arms2, DHCF_TWOPART_HANDS_UNDER_ARMS, true)) {
			$order = dhcf_move_after($order, 'arms', 'head');
		}
	}
	if (!empty($traits['arms']) && in_array($traits['arms'], DHCF_ARMS_BEHIND_TORSO, true)) {
		$order = dhcf_move_before($order, 'arms', 'torso');
	}
	/* Arms to the very front for the shoulder cam -- see
	   DHCF_ARMS_OVER_COMPANION. After the behind-torso rule below would
	   undo it, so it runs here and skips arms that belong behind the body. */
	if (!empty($traits['companion']) && !empty($traits['arms'])
	    && in_array($traits['companion'], DHCF_ARMS_OVER_COMPANION, true)
	    && !in_array($traits['arms'], DHCF_ARMS_BEHIND_TORSO, true)
	    && !dhcf_arms_under_companion($traits['companion'], $traits['arms'])) {
		$order = dhcf_move_last($order, 'arms');
	}
	/*
	 * THE SAMURAI PAIR -- see DHCF_ARMS_OVER_HEADGEAR.
	 *
	 * Last of the arm moves, so it beats the demon's hands-under-arms move
	 * (which only lifts the arm above the HEAD) when both apply. That is not
	 * an accident of ordering, it is the only arrangement that satisfies every
	 * rule at once: the samurai helmet is on DHCF_TWOPART_HANDS_OVER_HEADGEAR
	 * so the claws go over the helmet, and the samurai arms are on
	 * DHCF_TWOPART_HANDS_UNDER_ARMS so the arms go over the claws. Headgear <
	 * claws < arms is the only order that is all three, and it needs the arms
	 * after the headgear -- which is exactly what this move does. Without it
	 * the two demon rules contradict each other on this pairing and the claws
	 * land on top of the arm that is supposed to cover them.
	 *
	 * Skips arms that belong behind the body, same guard as the rule above:
	 * hoisting one of those to the front is the opposite of its own rule.
	 */
	if (!empty($traits['arms']) && !empty($traits['headgear'])
	    && dhcf_arms_over_headgear($traits['headgear'], $traits['arms'])
	    && !in_array($traits['arms'], DHCF_ARMS_BEHIND_TORSO, true)) {
		/* ONLY FORWARD, NEVER BACK. The shoulder-cam rule above has already
		   pushed the arms to the very END for its own pairing, and moving
		   them to just-after-headgear from there would pull them back BEHIND
		   the companion -- undoing the one rule DHCF_ARMS_OVER_COMPANION
		   exists to enforce. Caught by the harness on
		   samurai pair + dh-vision-shoulder-cam, which is a real build.
		   Anything already in front of the headgear is already satisfying
		   this rule and must be left alone. */
		$ai = array_search('arms', $order, true);
		$gi = array_search('headgear', $order, true);
		if ($ai !== false && $gi !== false && $ai < $gi) {
			$order = dhcf_move_after($order, 'arms', 'headgear');
		}
	}
	/*
	 * THE SABER PIPE OVER THE SHOULDER CAM -- see DHCF_HEADGEAR_OVER_COMPANION.
	 *
	 * Runs AFTER the arms-over-companion rule above, deliberately. That rule
	 * moves the arms to the very END for the cam, and this one only moves the
	 * HEADGEAR past the companion -- so the arms stay in front of both, which
	 * is what the cam rule exists to guarantee. Putting this first would still
	 * work today, but only because move_last() happens to run afterwards; the
	 * order is written down so it cannot become accidental.
	 */
	if (!empty($traits['headgear']) && !empty($traits['companion'])
	    && dhcf_headgear_over_companion($traits['headgear'], $traits['companion'])) {
		$order = dhcf_move_after($order, 'headgear', 'companion');
	}
	/* A comic cover goes under a companion that was demoted below Arms --
	   see DHCF_COMPANION_OVER_COVER. Runs AFTER the companion move, because
	   it targets wherever the companion ended up, and BEFORE the
	   behind-torso rule, which is more specific and must still win. */
	if (!empty($traits['companion'])
	    && in_array($traits['companion'], DHCF_COMPANION_OVER_COVER, true)) {
		foreach (array('effects1', 'effects2') as $k) {
			if (empty($traits[$k]) || !in_array($traits[$k], DHCF_COVER_EFFECTS, true)) continue;
			/* Only when the cover is currently ABOVE the companion. If the
			   companion was never demoted it is already last, and moving the
			   cover down to meet it would put the frame over the head -- the
			   opposite of the point. The canvas guards the same way (it has
			   to, for its splice arithmetic), and the two must not differ
			   even on a misconfigured list. */
			$at = array_search($k, $order, true);
			$ci = array_search('companion', $order, true);
			if ($at === false || $ci === false || $at <= $ci) continue;
			$order = dhcf_move_before($order, $k, 'companion');
		}
	}
	foreach (array('effects1', 'effects2') as $k) {
		if (!empty($traits[$k]) && in_array($traits[$k], DHCF_EFFECTS_BEHIND_TORSO, true)) {
			$order = dhcf_move_before($order, $k, 'torso');
		}
	}
	return $order;
}

/** Move $what to the very end, preserving everything else. */
function dhcf_move_last($order, $what) {
	$out = array();
	foreach ($order as $k) if ($k !== $what) $out[] = $k;
	if (count($out) === count($order)) return $order;   // it was not in the list
	$out[] = $what;
	return $out;
}

/** Move $what immediately before $before, preserving everything else. */
function dhcf_move_before($order, $what, $before) {
	$out = array();
	foreach ($order as $k) { if ($k !== $what) $out[] = $k; }
	$at = array_search($before, $out, true);
	if ($at === false) return $order;
	array_splice($out, $at, 0, array($what));
	return $out;
}

/* The mirror of the above. Both leave the order untouched when the anchor is
   not present, so a rule naming a slot this Fighter does not wear is a no-op
   rather than a reshuffle. */
function dhcf_move_after($order, $what, $after) {
	$out = array();
	foreach ($order as $k) { if ($k !== $what) $out[] = $k; }
	$at = array_search($after, $out, true);
	if ($at === false) return $order;
	array_splice($out, $at + 1, 0, array($what));
	return $out;
}

/* ------------------------------------------------------------------ *
 * WHICH GAME PAYS WHICH CATEGORY
 *
 * The organising rule: a MANDATORY trait may only come from a game that cannot
 * be lost. Torso, Background and Head are needed by every Fighter, so gating
 * them behind a win would let a player grind for months and still be unable to
 * finish a character -- and losing is the norm here (Crypt Conquest's number
 * two sits at 7 wins against 34 losses).
 *
 *   score games  -> the three mandatory categories
 *   winnable     -> optional categories, paid on the win
 *   gated        -> the wildcard only, so being locked out costs a bonus and
 *                   never a slot anyone needs
 * ------------------------------------------------------------------ */
$GLOBALS['DHCF_GAMES'] = array(
	// 'base'  the tier table a qualifying result rolls on before any bonus.
	//         Win-gated games start higher: on the live boards a Crypt Crawl
	//         week is typically 0-1 wins against 2 losses, so a win is already
	//         an achievement and should not roll the same table as a routine run.
	// 'bands' value => better table, for games where doing MORE should pay more.
	//         Without these a drop is binary, and publishing the floor teaches
	//         players to stop at it -- hold 10 waves, die, restart, which turns
	//         a 30-minute game into a 5-minute farm.
	// 'lower' set when a smaller value is better (race times).
	/* URLs GO STRAIGHT TO THE GAME. This list renders on dhcfighters.php as
   "where traits drop" -- a list of things to go and play, shown only to a
   signed-in member. Five of these pointed at the *game.php marketing
   landings, which put a pitch between a member and the game every time,
   the same extra click the Play menu had. The public pages keep their own
   audience; this list is not it. */
	'skullswap'      => array('label' => 'Skull Swap', 'url' => 'skullswap.php',      'category' => 'torso',      'trigger' => 'score threshold',
	                          'base' => 'run',          'bands' => array(9000 => 'placement_10', 12000 => 'placement_3', 15000 => 'placement_1')),
	'skullracer'     => array('label' => 'Skull Racer', 'url' => 'skullracer.php',     'category' => 'head',       'trigger' => 'finish the race',
	                          'base' => 'run',          'lower' => true,
	                          'bands' => array(400 => 'placement_10', 345 => 'placement_3', 330 => 'placement_1')),
	'obscura'        => array('label' => 'Obscura', 'url' => 'obscura.php',         'category' => 'background', 'trigger' => 'finish a run of 10+ solves',
	                          'base' => 'run',          'bands' => array(15 => 'placement_10', 25 => 'placement_3', 40 => 'placement_1')),
	// DEPTH, NOT THE WIN. Both card games used to pay only on a full clear, and
	// on the live boards that is roughly 0-1 wins against 2 losses a week -- so
	// Weapon and Headgear were shut to most players however much they played,
	// which is the one thing the category map is supposed to prevent.
	//
	// The qualifying depth is the last stretch of each game rather than a flat
	// number: 12 of Crawl's 15, 10 of Conquest's 12. Conquest is the shorter
	// game, so the same count would have been a much softer bar there -- these
	// are both four fifths of the way in, which is the thing being asked for.
	//
	// The bands still pay a full clear exactly what the win paid before, so
	// qualifying adds a roll and takes nothing off the win. Depth is also what
	// both leaderboards already rank on, so this grades what the games measure.
	'cryptcrawl'     => array('label' => 'Crypt Crawl', 'url' => 'cryptcrawl.php',     'category' => 'weapon',     'trigger' => 'clear 12 of the 15 crypts',
	                          'base' => 'run',          'bands' => array(13 => 'placement_10', 15 => 'placement_3')),
	'cryptconquest'  => array('label' => 'Crypt Conquest', 'url' => 'cryptconquest.php',  'category' => 'headgear',   'trigger' => 'defeat 10 of the 12 court cards',
	                          'base' => 'run',          'bands' => array(12 => 'placement_10')),
	'gauntlets'      => array('label' => 'Gauntlets', 'url' => 'gauntlets.php',       'category' => 'effects',    'trigger' => 'sweep the gauntlet',
	                          'base' => 'placement_10'),
	'guardians'      => array('label' => 'Realm Guardians', 'url' => 'guardians.php', 'category' => 'companion',  'trigger' => 'waves held',
	                          'base' => 'run',          'bands' => array(20 => 'placement_10', 40 => 'placement_3', 70 => 'placement_1')),
	'monstrocity'    => array('label' => 'Monstrocity', 'url' => 'monstrocity.php',     'category' => 'arms',       'trigger' => 'complete all 28 levels',
	                          'base' => 'placement_1'),
	// No bands and no floor: killing a boss IS the achievement, and there is no
	// "how well" to grade it on. The bands here used to read damage contributed,
	// from when the trigger was a damage milestone -- against the match-3 score
	// actually passed now, they graded nothing and the old floor of 100 silently
	// refused low-scoring kills.
	'bosses'         => array('label' => 'Boss Battles', 'url' => 'monstrocity.php#boss',    'category' => 'wildcard',   'trigger' => 'every boss defeat', 'gated' => true,
	                          'base' => 'placement_10'),
	// Every successful Maxingo mission pays, at any level -- most stakers have
	// already unlocked all ten, so paying only first clears would shut them out.
	// The level decides the odds, since the deeper ones are harder to reach.
	// A wildcard, because a mission is not tied to one part of a Fighter.
	//
	// The farm is stopped by the daily cap, which dhcf_award() now enforces for
	// every source: a 100% success consumable makes completion certain and
	// returns the NFTs immediately, so without it MAXI would buy an unbounded
	// supply of best-table traits. Three a day however many missions are run.
	'maxingo'        => array('label' => 'Maxingo Missions', 'url' => 'missions.php',
	                          'category' => 'wildcard', 'trigger' => 'complete a mission',
	                          'base' => 'run',
	                          'bands' => array(4 => 'placement_10', 7 => 'placement_3', 9 => 'placement_1')),

	// REALMS RAIDS -- the first source that is not a solo game: the trait comes
	// out of a contest with another staker rather than a board. BOTH SIDES of a
	// decided raid pay, and they share ONE key deliberately, so the per-source
	// cap means three a day from Realms in total -- not three attacking plus
	// three defending.
	//
	// Gated: raiding needs a Realm, a trained army and a Portal, so a staker
	// without one is shut out completely. That is precisely the case the
	// wildcard exists for -- being locked out costs a bonus, never a slot.
	//
	// 'base' is the ROUTINE table, not a win-gated one. A raid win is nothing
	// like a Crypt Crawl win: evenly matched realms sit near 50%, and the
	// attacker chooses the target, so the floor here is a coin flip you picked.
	//
	// The bands read HOW FAR UP THE WINNER PUNCHED -- the loser's rating minus
	// their own -- which asks both sides the same question: how much stronger
	// was the realm you beat. It mirrors the loot rule (a weaker attacker takes
	// up to 9%, a stronger one is capped at 3%) and it stops "farm the softest
	// eligible realm" from being the optimal way to collect traits.
	//
	// Attackers reach the top band and defenders essentially cannot: startRaid()
	// refuses a target more than 3 defense levels below you, so a defender's gap
	// caps at +3 outside a revenge raid. That asymmetry is intended. Punching up
	// five levels is a deliberate long-odds choice; nobody chooses to be raided.
	/* realms.php, NOT the old standalone raids.php -- that page renders the
	   same raid lists with none of the Realms page's CSS and landed people
	   on an unstyled wall of markup; it redirects here now.
	   The plain page, not #raids: someone arriving to go raiding wants the
	   Realms page, and Locations is where a raid starts. The section hash
	   still works for anyone who uses one -- see realms.php -- it is just
	   not what this link needs to do. */
	'raids'          => array('label' => 'Realm Raids', 'url' => 'realms.php',
	                          'category' => 'wildcard', 'trigger' => 'win a raid, or repel one',
	                          'gated' => true, 'base' => 'run',
	                          'bands' => array(1 => 'placement_10', 3 => 'placement_3', 5 => 'placement_1')),

	// DHC ARENA. Gated hard -- it needs a Crew of three saved Fighters, so a
	// staker without one is shut out entirely, which is exactly the case the
	// wildcard exists for: being locked out costs a bonus and never a slot.
	//
	// 'base' is the routine table. Winning a battle is the normal outcome of
	// playing one, not an achievement in itself; what deserves paying more is
	// beating a Crew better than yours. The bands read the gap between the two
	// Crews' best rarity scores in hundreds, so punching up pays and farming
	// somebody weaker does not -- the same shape as the Raids bands, and the
	// same reason.
	//
	// Capped at the default 3/day, and Arena additionally allows only one
	// REWARDED battle per opponent per day, so a willing pair cannot trade
	// wins. See dhca_already_rewarded().
	'arena'          => array('label' => 'DHC Arena', 'url' => 'dhcarena.php',
	                          'category' => 'wildcard', 'trigger' => 'win a battle',
	                          'gated' => true, 'base' => 'run',
	                          'bands' => array(1 => 'placement_10', 3 => 'placement_3', 6 => 'placement_1')),

	/*
	 * THE TWO SPECIALTY GAMES, AND THE ONE RULE HERE THAT IS NOT LIKE THE
	 * OTHERS.
	 *
	 * Everywhere else on this list a genuine attempt qualifies and PLACEMENT
	 * ONLY DRIVES QUALITY -- see the note above DHCF_TIERS, which is explicit
	 * that making placement drive the RATE lets strong players compound away
	 * from everyone else. These two invert that on purpose: nothing drops
	 * unless the run TAKES OR TIES THE LEAD of the current weekly game, and
	 * then it pays the first-place table.
	 *
	 * The owner's call, and the reasoning is specific to these two: they are
	 * niche boards with a handful of regulars, where the problem is not
	 * runaway winners but silence. Rewarding the EVENT (the lead changing)
	 * rather than the STATE (being good) is what makes a quiet board worth
	 * opening, and it pays fastest to whoever turns up first in a new game.
	 *
	 * WHY IT CANNOT RUN AWAY: the lead is scoped to one game_id, and
	 * dropship/automate.php closes each game and opens the next on a cron, so
	 * the board empties on a fixed cadence and no single score can freeze the
	 * reward. A second gate -- the run must also beat that player's own
	 * previous score in the same game -- stops the leader farming by beating
	 * themselves. See dropship/dropship-traitdrop.php, which is where both
	 * gates actually live, because only Drop Ship can see those scores.
	 *
	 * FLOOR 0, deliberately. Every other floor is a score threshold; here the
	 * lead IS the threshold, and a number on top of it would do nothing but
	 * shut out the first player in a fresh game.
	 *
	 * 'wildcard' because all eight trait slots are already spoken for by the
	 * eight games above -- and it suits these two anyway: a wildcard can be
	 * any category at all, which is the right shape for a prize you only get
	 * by being top of the board.
	 */
	/* THE ONE LISTED ROW of the four. "in any theme" is doing real work: the
	   other three are unlisted (see below), so this line is the only place a
	   player is told that Oculus Lounge, Filthy Mermaid and Dread City pay
	   the same drop and are reached from inside this one. */
	'dropship'       => array('label' => 'Drop Ship', 'x_tag' => '@ohh_meed', 'url' => 'dropship/dashboard.php?project_id=1',
	                          'category' => 'wildcard', 'trigger' => 'take or tie the lead in any theme',
	                          'base' => 'placement_1',
	                          'cap' => 0,
	                          'limit_note' => 'only when you take the lead'),
	/*
	 * THE THREE RESKINS ARE UNLISTED, and for a different reason than the
	 * streak and the referral below: those are not games, these are the SAME
	 * game.
	 *
	 * Oculus Lounge, Filthy Mermaid and Dread City are Drop Ship themes. You
	 * switch between them inside it, so four rows on "where traits drop" sent
	 * a player to four doors into one room -- and because all four are
	 * 'wildcard', the rows rendered IDENTICALLY: same "any trait", same count
	 * out of the whole collection, same trigger, same limit note, four times.
	 * Four sixteenths of a list of games to go and play, all saying one thing.
	 * Drop Ship's row stands for them, and its trigger is where that is said.
	 *
	 * THEY STAY IN DHCF_GAMES, exactly as the streak does, because everything
	 * functional reads this array: dropshipDropAward() derives these KEYS from
	 * the theme slug and dhcf_award() resolves the label, the X tag and the
	 * link from them, so deleting a row would post that theme's drops as Drop
	 * Ship or as nothing at all. Unlisted hides the row and changes nothing
	 * else -- they pay, count and cap as they always did.
	 */
	'oculuslounge'   => array('label' => 'Oculus Lounge', 'x_tag' => '@oculuslounge', 'url' => 'dropship/dashboard.php?project_id=4',
	                          'category' => 'wildcard', 'trigger' => 'take or tie the lead this game',
	                          'base' => 'placement_1',
	                          'cap' => 0,
	                          'limit_note' => 'only when you take the lead',
	                          'unlisted' => true),
	// Ohh Meed's too, so the same @ohh_meed credit as Drop Ship. The KEY is
	// the Drop Ship theme slug with the hyphens taken out, because that is
	// what dropshipDropAward() derives -- 'filthy-mermaid' -> 'filthymermaid'.
	// Get that wrong and the drop silently posts as Drop Ship instead.
	'filthymermaid'  => array('label' => 'Filthy Mermaid', 'x_tag' => '@ohh_meed', 'url' => 'dropship/dashboard.php?project_id=3',
	                          'category' => 'wildcard', 'trigger' => 'take or tie the lead this game',
	                          'base' => 'placement_1',
	                          'cap' => 0,
	                          'limit_note' => 'only when you take the lead',
	                          'unlisted' => true),
	// Ohh Meed's as well -- same Shorty Verse collection Filthy Mermaid uses.
	'dreadcity'      => array('label' => 'Dread City', 'x_tag' => '@ohh_meed', 'url' => 'dropship/dashboard.php?project_id=2',
	                          'category' => 'wildcard', 'trigger' => 'take or tie the lead this game',
	                          'base' => 'placement_1',
	                          'cap' => 0,
	                          'limit_note' => 'only when you take the lead',
	                          'unlisted' => true),
	/*
	 * Maxingo's art, on Digital Hell Citizens 2: Fighters.
	 *
	 * THE KEY IS THE SLUG WITH THE HYPHENS TAKEN OUT, because that is what
	 * dropshipDropAward() derives -- 'dhc2-fighters' -> 'dhc2fighters'. Get
	 * it wrong and nothing breaks loudly: the drop still lands, credited to
	 * Drop Ship, because an unregistered key falls back rather than failing.
	 * That is the right failure and a silent one, so the key was confirmed
	 * against the theme's actual slug rather than guessed.
	 *
	 * @MMAXI404 rather than @ohh_meed: the art is Maxingo's, and the tag is
	 * what a shared drop credits on X. Taken from the account itself
	 * (x.com/MMAXI404) rather than guessed from the name -- an @ in a post
	 * tags whoever actually holds that handle, so a near miss credits a
	 * stranger. Same rule dhc-fighter-modal.php follows for a builder.
	 */
	'dhc2fighters'   => array('label' => 'DHC2 Fighters', 'x_tag' => '@MMAXI404', 'url' => 'dropship/dashboard.php?project_id=5',
	                          'category' => 'wildcard', 'trigger' => 'take or tie the lead this game',
	                          'base' => 'placement_1',
	                          'cap' => 0,
	                          'limit_note' => 'only when you take the lead',
	                          'unlisted' => true),

	// NOT A GAME, and 'unlisted' is what that now costs it. It stays in
	// DHCF_GAMES because everything functional reads this array -- dhcf_award(),
	// the daily cap, the claim endpoint, the notifier -- and taking it out would
	// stop the streak paying at all. It is only kept OFF the "where traits drop"
	// table on dhcfighters.php, which is a list of games you can go and play:
	// every other row links to a game and names a thing to do, and a row whose
	// link is the Launchpad and whose trigger is "wait seven days" broke the
	// symmetry of the set.
	//
	// Aimed at the members who claim dailies and run missions but never open a
	// game; a wildcard is the right lure because it can be any trait at all.
	// 'limit_note' replaces the "N of N left today" line where a daily count
	// does not describe the real limit. The streak pays once per seven days by
	// definition, so quoting a daily cap implies three are available today.
	'dailystreak'    => array('label' => 'Daily Reward Streak', 'earned_from' => 'a 7-day reward streak', 'url' => 'launchpad.php', 'category' => 'wildcard',
	                          'trigger' => 'complete a 7-day streak', 'base' => 'placement_3',
	                          'cap' => 1, 'limit_note' => 'once per 7-day streak',
	                          'unlisted' => true),

	// Bringing somebody back. Not a game either, and unlisted for the same
	// reason the streak is -- it has no page to send anybody to.
	//
	// placement_3, NOT 'run'. The whole complaint about an ordinary drop as a
	// reward is that 'run' is 55% common, so three of them is three commons
	// and the effort buys disappointment. placement_3 is 25/30/27/15/3, which
	// is the table a seven-day streak pays -- the right comparison, because
	// bringing a player back is worth about what a week of showing up is.
	// placement_1 stays reserved for topping a board; spending it here would
	// quietly devalue the thing it exists for.
	//
	// The cap counts DRAWS, and an invite is three of them plus one for the
	// person arriving, so 60 is about twenty invites in a day. That is
	// effectively no limit for anyone honest and still a wall for a script --
	// which is what this ceiling is for, per dhcf_cap()'s own note.
	'referral'       => array('label' => 'Invite a friend', 'earned_from' => 'inviting a friend', 'url' => 'launchpad.php', 'category' => 'wildcard',
	                          'trigger' => 'a friend joins or returns through your link',
	                          'base' => 'placement_3', 'cap' => 60,
	                          'limit_note' => 'three traits per friend who turns up',
	                          'unlisted' => true),

	// A cache that sometimes falls out of the daily digest. Not a game
	// either, and unlisted for the same reason the other two are.
	//
	// THE POINT IS THE HABIT, and the habit is OPENING THE DIGEST -- which
	// already requires having played the day before, since there is no
	// digest on a quiet day. Hanging it there rather than on the Share
	// button is deliberate: a reward for sharing is a reward for posting on
	// X, which is the one thing that would put the invite scheme on the
	// wrong side of X's rule against paying for engagement.
	//
);



function dhcf_game($key) {
	return isset($GLOBALS['DHCF_GAMES'][$key]) ? $GLOBALS['DHCF_GAMES'][$key] : null;
}

/* ------------------------------------------------------------------ *
 * MINIMUM SCORE TO QUALIFY
 *
 * A run has to earn its roll, or quitting out repeatedly becomes the fastest
 * way to farm. Set well below what the live leaderboards currently show, so a
 * genuine attempt always qualifies -- these are floors, not targets.
 *
 * Derived from the top few players on each board, which is a biased sample;
 * if any floor ever excludes a real attempt it is too high.
 * ------------------------------------------------------------------ */
$GLOBALS['DHCF_FLOORS'] = array(
	'skullswap'     => 5000,   // board shows 11,870-14,400
	'skullracer'    => 0,      // finishing at all qualifies
	'obscura'       => 10,     // solves in a completed run; board shows streaks of 11-33
	'cryptcrawl'    => 12,     // crypts cleared, of 15; a win is 15
	'cryptconquest' => 10,     // court cards defeated, of 12; a win is 12
	'gauntlets'     => 0,      // win only
	'guardians'     => 10,     // waves held; board shows 12-81
	'monstrocity'   => 28,     // campaign completion
	'bosses'        => 0,      // the defeat itself qualifies; nothing to grade
	'dailystreak'   => 7,      // day 7 of 7, nothing earlier
	'maxingo'       => 1,      // any completed Maxingo mission; level sets the odds
	'raids'         => 0,      // the decided raid itself qualifies, won or repelled
	'arena'         => 0,      // the win itself qualifies; the bands grade the gap
	'dropship'      => 0,      // taking the lead is the gate; a score floor would only shut out the first player in a new game
	'oculuslounge'  => 0,      // same
	'filthymermaid' => 0,      // same
	'dreadcity'     => 0,      // same
);

/**
 * How a trait was earned, as a phrase that reads after "Dropped from".
 *
 * THE LABEL IS THE ANSWER FOR EVERY GAME -- "Dropped from Realm Guardians"
 * says it. It is the three sources that are NOT games that need their own
 * wording: "Dropped from Invite a friend" is not a sentence, and the triggers
 * next door are no help either because they are written as instructions
 * ("clear 12 of the 15 crypts"), not as a description of what someone did.
 *
 * Falls back to the label, then to the key, so a source added later reads
 * sensibly without anyone remembering this function exists.
 */
function dhcf_earned_from($key) {
	$g = dhcf_game($key);
	if (!$g) return (string)$key;
	if (!empty($g['earned_from'])) return $g['earned_from'];
	return !empty($g['label']) ? $g['label'] : (string)$key;
}

/**
 * The X account to tag when a drop from this source is posted, or ''.
 *
 * NOT THE SAME THING AS THE TRAIT'S ARTIST. Every trait is @MMAXI404's work
 * and the post already credits them in its tail. This is the account behind
 * the GAME the trait fell out of, so a post about a Drop Ship drop reaches
 * the people who made and play Drop Ship.
 *
 * THESE ARE REAL ACCOUNTS AND A WRONG ONE TAGS A STRANGER, in public, from a
 * player's own timeline. Both were given by the owner rather than found in
 * the codebase, which holds Discord ids for these people but no X handles.
 * If either is wrong this is the one line to change.
 *
 * Only the two specialty games have one. The rest are Skulliance's own, and
 * the tail already tags @skulliance.
 */
function dhcf_earned_tag($key) {
	$g = dhcf_game($key);
	return ($g && !empty($g['x_tag'])) ? $g['x_tag'] : '';
}

function dhcf_floor($game) {
	return isset($GLOBALS['DHCF_FLOORS'][$game]) ? $GLOBALS['DHCF_FLOORS'][$game] : 0;
}

/* ------------------------------------------------------------------ *
 * HOW GOOD A DROP IS
 *
 * Placement drives QUALITY, not quantity. If topping a board multiplied both
 * the rate and the tier, strong players would compound away from everyone
 * else; this way the prize is real but the gap stays bounded.
 *
 * Every table keeps a non-zero mythic chance. A 0% would quietly tell most of
 * the base that the good traits are not for them, and the lottery feeling is
 * the whole engine.
 * ------------------------------------------------------------------ */
define('DHCF_TIERS', array(
	'run'         => array('common' => 55, 'uncommon' => 28, 'epic' => 13, 'legendary' => 3.5, 'mythic' => 0.5),
	'placement_10'=> array('common' => 38, 'uncommon' => 30, 'epic' => 21, 'legendary' => 9,   'mythic' => 2),
	'placement_3' => array('common' => 25, 'uncommon' => 30, 'epic' => 27, 'legendary' => 15,  'mythic' => 3),
	'placement_1' => array('common' => 15, 'uncommon' => 25, 'epic' => 30, 'legendary' => 22,  'mythic' => 8),
));

/** Rank the tables so "whichever is better" is a comparison, not a guess. */
function dhcf_table_rank($name) {
	$order = array('run' => 0, 'placement_10' => 1, 'placement_3' => 2, 'placement_1' => 3);
	return isset($order[$name]) ? $order[$name] : 0;
}

/** Table name earned by a leaderboard position (1-based), or 'run'. */
function dhcf_placement_table($placement = null) {
	if ($placement === null || $placement < 1) return 'run';
	if ($placement == 1)  return 'placement_1';
	if ($placement <= 3)  return 'placement_3';
	if ($placement <= 10) return 'placement_10';
	return 'run';
}

/**
 * Which tier table a result has earned: the BEST of the game's base table,
 * what its performance bands award, and what its leaderboard placement awards.
 *
 * Best-of rather than additive, so a player is never worse off for having done
 * well on two axes at once, and the ceiling stays the first-place table.
 */
function dhcf_table_for($key, $value = 0, $placement = null) {
	$g    = dhcf_game($key);
	$name = ($g && !empty($g['base'])) ? $g['base'] : 'run';

	if ($g && !empty($g['bands'])) {
		$lower = !empty($g['lower']);
		foreach ($g['bands'] as $threshold => $table) {
			$hit = $lower ? ($value > 0 && $value <= $threshold) : ($value >= $threshold);
			if ($hit && dhcf_table_rank($table) > dhcf_table_rank($name)) $name = $table;
		}
	}

	$p = dhcf_placement_table($placement);
	if (dhcf_table_rank($p) > dhcf_table_rank($name)) $name = $p;

	return DHCF_TIERS[$name];
}

/** The band a value has reached, for telling the player what they rolled on. */
function dhcf_band_label($key, $value = 0, $placement = null) {
	$g = dhcf_game($key);
	$name = ($g && !empty($g['base'])) ? $g['base'] : 'run';
	if ($g && !empty($g['bands'])) {
		$lower = !empty($g['lower']);
		foreach ($g['bands'] as $threshold => $table) {
			$hit = $lower ? ($value > 0 && $value <= $threshold) : ($value >= $threshold);
			if ($hit && dhcf_table_rank($table) > dhcf_table_rank($name)) $name = $table;
		}
	}
	$p = dhcf_placement_table($placement);
	if (dhcf_table_rank($p) > dhcf_table_rank($name)) $name = $p;
	$labels = array('run' => 'standard odds', 'placement_10' => 'improved odds',
	                'placement_3' => 'strong odds', 'placement_1' => 'best odds');
	return isset($labels[$name]) ? $labels[$name] : 'standard odds';
}

/* ------------------------------------------------------------------ *
 * DAILY CEILING
 *
 * An anti-abuse ceiling, not a balancing dial. At current volumes -- three or
 * four players per board logging a handful of runs a week -- no honest player
 * will ever reach it.
 * ------------------------------------------------------------------ */
define('DHCF_DAILY_CAP', 3);

/**
 * Daily cap for one source. Override per game by adding 'cap' to its
 * DHCF_GAMES entry; everything else falls back to the constant.
 *
 * The cap only binds on the short games -- a Guardians siege runs half an
 * hour, a Monstrocity campaign completes about once a month, and the daily
 * streak pays once a week by definition. It is an anti-abuse ceiling on the
 * few sources that could be ground, not a balancing dial for the rest.
 *
 * ZERO MEANS NO DAILY CEILING, and every caller has to read it that way --
 * `$used >= dhcf_cap($key)` is true of 0 against any count at all, so a
 * caller that forgets refuses every single draw rather than allowing them.
 * The Drop Ship family uses it: see their entries for why their own rule is
 * the limit there.
 */
function dhcf_cap($key) {
	$g = dhcf_game($key);
	return ($g && isset($g['cap'])) ? (int)$g['cap'] : DHCF_DAILY_CAP;
}

/**
 * Where the trait art landed on disk, relative to the web root.
 *
 * LIVED IN dhc-assembler.php AS A BARE $dhc_base, which was fine while the
 * assembler was the only thing that drew a Fighter. It is not any more:
 * dhcfighters.php renders its roster BEFORE including the assembler (the
 * cards are buffered into $dhca_aside so they can go in the picker column),
 * and at that point $dhc_base did not exist yet -- so every card emitted
 * `src="/250/torso/x.png"` with an "Undefined variable" warning printed into
 * the alt text. Which is exactly what shipped.
 *
 * Here instead, so both callers get the same answer whichever runs first.
 * Checked in order, so a different upload path is one edit.
 *
 * NAMED dhcf_art_dir, NOT dhcf_art_base: dhcfighters-notify.php has had a
 * dhcf_art_base() of its own for a long time and it is a DIFFERENT function
 * -- it returns the absolute https:// prefix Discord needs, because Discord
 * fetches the image itself and a relative path is nothing to it. Declaring a
 * second dhcf_art_base() here was a fatal "cannot redeclare" the moment both
 * files loaded, which dhcfighters-lib.php does on every trait award. Notify's
 * now builds its URL on this.
 */
function dhcf_art_dir() {
	static $base = null;
	if ($base !== null) return $base;
	$base = '';
	foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
		if (is_dir(__DIR__ . '/' . $c . '/1000')) { $base = $c; break; }
	}
	return $base;
}

