<?php
/**
 * lib/return-gift.php -- a welcome-back package, derived not stored.
 *
 * Three traits for a lapsed member who signs back in: one background,
 * one torso, one head. Those three are the minimum legal Fighter
 * (DHCF_REQUIRED), so the gift is not three nice pictures -- it is
 * exactly enough to assemble something the moment they arrive.
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

/** The three slots a minimum legal Fighter needs. */
if (!defined('RETURN_GIFT_SLOTS'))  define('RETURN_GIFT_SLOTS', 'background,torso,head');
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
		/* The same function every other drop goes through, so the player
		   gets the usual reveal and the usual announcement rather than
		   traits appearing silently in their inventory. */
		if (function_exists('dhcf_commit_drop')) {
			dhcf_commit_drop($conn, $uid, $cat, $slug, $info, 'returngift', 'welcome back');
		}
		$given[$cat] = $slug;
	}
	return $given;
}
