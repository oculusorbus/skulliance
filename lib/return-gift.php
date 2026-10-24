<?php
/**
 * lib/return-gift.php -- a welcome-back package, derived not stored.
 *
 * One trait of every category for a lapsed member who signs back in:
 * background, torso, head, arms, weapon, headgear, effects and a
 * companion. Not three nice pictures -- a complete, finished Fighter
 * they can assemble on the page they land on.
 *
 * DERIVED FROM THE DISCORD ID, WHICH IS WHY THERE IS NO TABLE. The
 * package is a pure function of who they are, so it can be previewed
 * in the admin, pasted into a DM, and handed over weeks later without
 * anything being reserved, expired or reconciled. The preview cannot
 * disagree with what they receive because both are the same
 * calculation.
 *
 * RENDEZVOUS HASHING, NOT MODULO. The obvious version is
 * hash(id) % count(slugs), and it is subtly wrong: adding one trait to
 * a category changes the count and reshuffles EVERY player's pick. A
 * preview sent on Monday would be a different package by Friday, which
 * breaks the one promise the DM makes. Here each slug is scored by
 * hash(id|category|slug) and the lowest wins, so adding a trait only
 * changes somebody's result if the new slug happens to score lower --
 * roughly one player in N rather than all of them.
 *
 * COMMON AND UNCOMMON ONLY. A welcome-back present must not mint
 * mythics. The rarest tiers drive Fighter scoring and the Drop Ship
 * armour curve, and handing them out for signing in would devalue
 * every one somebody actually earned.
 *
 * CLAIMED ONCE, EVER, and recorded in the nudges table rather than a
 * new one -- it is already a per-user event log with exactly the right
 * shape. Once-ever also means the eligibility window cannot be farmed
 * by going quiet on purpose.
 *
 * EVERYONE WHO COMES BACK, NOT EVERYONE WHO WAS INVITED. The owner's
 * lapsed panel is a list of people to write to personally; this is not
 * gated on it. Somebody who drifts back on their own after a month
 * gets the same package the DM would have promised, because the thing
 * being fixed is "there is nothing to do on arrival", and that is true
 * whether or not anybody wrote to them.
 *
 * NO TABLE MEANS NO GIFT, deliberately. Every read here fails closed:
 * a missing nudges table reads as already-claimed, so the feature
 * degrades to silence rather than to handing out traits on repeat.
 */

require_once __DIR__ . '/../dhcfighters-lib.php';

/*
 * ONE OF EVERY CATEGORY. This started as the three slots a minimum
 * legal Fighter needs (DHCF_REQUIRED) and was deliberately widened:
 * the three-trait version got somebody to a plain Fighter, and the
 * point of the package is that coming back feels like a win. Eight
 * means they can build a FINISHED one -- armed, with a companion, with
 * effects -- on the page they land on.
 *
 * WRITTEN OUT RATHER THAN READ FROM THE RARITY TABLE, so adding a
 * ninth category is a decision somebody makes instead of a giveaway
 * that quietly grows. return-gift-harness.php compares this list
 * against the rarity table and fails when they diverge, so the
 * decision cannot be skipped by accident.
 */
if (!defined('RETURN_GIFT_SLOTS'))  define('RETURN_GIFT_SLOTS',
	'background,torso,head,arms,weapon,headgear,effects,companion');
/** How long away before signing in earns one. */
if (!defined('RETURN_GIFT_DAYS'))   define('RETURN_GIFT_DAYS', 30);
/** Tiers a gift may draw from. */
if (!defined('RETURN_GIFT_TIERS'))  define('RETURN_GIFT_TIERS', 'common,uncommon');
/** The one switch. Off makes return_gift_due() answer no, everywhere. */
if (!defined('RETURN_GIFT_ENABLED')) define('RETURN_GIFT_ENABLED', true);

/*
 * HOW THE RENDER IS ADDRESSED. Serial 0 is a namespace no saved Fighter
 * can occupy -- dhcf_next_serial() starts at 1 -- so a package can never
 * collide with somebody's real build. 500 is the unsuffixed size in
 * dhcf_render_fighter()'s cache, which is what Discord and the Arena
 * already ask for.
 */
if (!defined('RETURN_GIFT_SERIAL')) define('RETURN_GIFT_SERIAL', 0);
if (!defined('RETURN_GIFT_SIZE'))   define('RETURN_GIFT_SIZE', 500);

/**
 * The public address of the rendered package, WITHOUT rendering it.
 *
 * The admin list needs a paste-able link on every row, and rendering
 * four hundred Fighters to find out where they live is not a page load.
 * The filename is a pure function of the traits, so it can be computed;
 * the picture starts existing when something asks the endpoint for it.
 *
 * THIS RESTATES dhcf_render_fighter()'s NAMING, which is a duplication
 * and therefore a drift hazard: change the scheme there and every link
 * here becomes a 404 with nothing to notice it. lapsed-harness.php runs
 * both and fails on any disagreement, so the copy cannot rot quietly.
 */
function return_gift_url($discord_id) {
	$traits = return_gift_traits($discord_id);
	if (!$traits) return '';
	$name = 'f' . (int)RETURN_GIFT_SERIAL . '-' . substr(md5(json_encode($traits)), 0, 8)
	      . (RETURN_GIFT_SIZE === 500 ? '' : '@' . RETURN_GIFT_SIZE) . '.png';
	return 'https://skulliance.io/staking/dhcrenders/' . $name;
}

/**
 * The package for this person: category => slug.
 *
 * Pure. Same answer every time, on any server, with no state read or
 * written. Returns array() if the rarity table cannot be loaded.
 */
function return_gift_traits($discord_id) {
	$id = preg_replace('/[^0-9]/', '', (string)$discord_id);
	if ($id === '') return array();
	$rarity = dhcf_rarity();
	if (!$rarity) return array();

	$tiers = array_flip(explode(',', RETURN_GIFT_TIERS));
	$out   = array();
	foreach (explode(',', RETURN_GIFT_SLOTS) as $cat) {
		if (empty($rarity[$cat])) continue;
		$best = ''; $bestScore = '';
		foreach ($rarity[$cat] as $slug => $info) {
			/* $info is [tier, count, rate]. */
			if (!isset($tiers[$info[0]])) continue;
			$score = hash('sha256', $id . '|' . $cat . '|' . $slug);
			/* String compare: both are 64 hex chars, so this orders them
			   exactly as the numbers would, without touching floats. */
			if ($bestScore === '' || strcmp($score, $bestScore) < 0) {
				$bestScore = $score; $best = $slug;
			}
		}
		if ($best !== '') $out[$cat] = $best;
	}
	return $out;
}

/** Has this person already had one? */
function return_gift_claimed($conn, $user_id) {
	$uid = (int)$user_id;
	if ($uid <= 0) return true;
	/* The nudges table doubles as the per-user event log. No table, no
	   schema change, and it is already indexed on user_id. */
	$r = @$conn->query("SELECT 1 FROM nudges
	                     WHERE user_id = $uid AND nudge_key = 'returngift'
	                       AND action = 'claimed' LIMIT 1");
	/* A missing table means we cannot prove they have NOT had one, and
	   handing out duplicates is worse than handing out none. */
	return !$r || $r->num_rows > 0;
}

/**
 * Should this sign-in earn a package?
 *
 * $prev_login is last_login AS IT WAS before this request updated it --
 * skulliance.php already reads that value for the invite payout, and
 * asking afterwards makes every returning player look active.
 */
function return_gift_due($conn, $user_id, $prev_login) {
	if (!RETURN_GIFT_ENABLED) return false;
	if (empty($prev_login)) return false;
	$was = strtotime((string)$prev_login);
	if (!$was) return false;
	if ((time() - $was) < RETURN_GIFT_DAYS * 86400) return false;
	return !return_gift_claimed($conn, $user_id);
}

/**
 * ONE POST, NOT EIGHT.
 *
 * dhcf_commit_drop() announces every trait it commits, which is right
 * for a drop that fell out of a game and wrong for a package of eight
 * arriving at once -- that is a wall of embeds, and it reads as a bug
 * rather than a welcome. So the per-trait announcements are suppressed
 * and this goes out instead, carrying the assembled package as its
 * picture. The render is the same file the admin preview links to, so
 * the post and any DM sent beforehand show the same Fighter.
 *
 * Swallows everything. A present is not worth a white page, and the
 * traits are already committed by the time this runs.
 */
function return_gift_announce($conn, $user_id, $traits, $discord_id) {
	try {
		if (!function_exists('skl_discordmsg')) return;
		if (!function_exists('dhcf_author_block')) return;
		list($author, $name, $mention) = dhcf_author_block($conn, $user_id);

		$lines = array();
		foreach ($traits as $cat => $slug) {
			$lines[] = '**' . ucfirst($cat) . '** · ' . dhcf_trait_name($cat, $slug);
		}
		$desc = $name . ' has been away a while. Here is a whole Fighter to come back to: '
		      . 'one trait of every category, ready to assemble.' . "\n\n"
		      . implode("\n", $lines);

		skl_discordmsg(
			'Welcome back, ' . $name,
			$desc,
			return_gift_url($discord_id),      // the assembled package
			'https://skulliance.io/staking/dhcfighters.php',
			'dhcfighters',
			'',
			0x00c8a0,
			$author,
			array('text' => 'Welcome-back package · ' . count($traits) . ' traits'),
			$mention
		);
	} catch (Throwable $e) {
		error_log('return-gift announce: ' . $e->getMessage());
	}
}

/**
 * Hand it over. Returns the slugs granted, or array().
 *
 * THE LEDGER ROW IS WRITTEN FIRST. If the grant half fails partway,
 * the worst case is somebody owed traits; if the claim half failed
 * after granting, they could collect again on the next page load.
 */
function return_gift_grant($conn, $user_id, $discord_id) {
	$uid = (int)$user_id;
	$traits = return_gift_traits($discord_id);
	if ($uid <= 0 || !$traits) return array();

	$ok = @$conn->query("INSERT INTO nudges (user_id, nudge_key, action, until, created_at)
	                     VALUES ($uid, 'returngift', 'claimed', NULL, NOW())");
	if (!$ok) { error_log('return-gift: could not record the claim, not granting'); return array(); }

	$given = array();
	foreach ($traits as $cat => $slug) {
		$info = dhcf_trait_info($cat, $slug);
		if (!$info) continue;
		/* The same function every other drop goes through, so the ledger
		   row and the reveal are identical to any other trait. The final
		   false suppresses the PER-TRAIT Discord post only: eight of them
		   in a row is a wall, and one post with the assembled Fighter
		   goes out below instead. */
		if (function_exists('dhcf_commit_drop')) {
			dhcf_commit_drop($conn, $uid, $cat, $slug, $info, 'returngift', 'welcome back', false);
		}
		$given[$cat] = $slug;
	}

	/* After the loop, so the post describes what was actually committed
	   rather than what was intended. */
	if ($given) return_gift_announce($conn, $uid, $given, $discord_id);
	return $given;
}
