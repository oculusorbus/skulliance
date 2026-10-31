<?php
/**
 * lib/player-state.php — what this account can actually do, once per request.
 *
 * WHY THIS EXISTS. The Launchpad is the first page after login and it shows
 * thirty-six tiles, every one of them looking equally available. For a new
 * account most of them are not: Missions needs a staked NFT, Arena needs a
 * Fighter, Realms needs an NFT from a project that has one. So the first
 * thing a new member does is pick a door at random and find out it is
 * locked, which is the "brick wall" that gets reported as the site being
 * hard to understand. The platform's size reads as a wall instead of a
 * promise purely because nothing says which part of it is theirs yet.
 *
 * ONE DEFINITION, TWO CONSUMERS. The Launchpad groups by it and the nav
 * marks by it. If those two ever disagree about whether somebody can play
 * Arena, the menu is lying to the page -- which is this platform's most
 * repeated bug and the reason admin_tabs(), activity_sources() and
 * sk_rules_payload() all exist.
 *
 * ONLY WHAT IS VERIFIED GETS A LOCK. A tile wrongly marked "ready" costs a
 * click; a tile wrongly marked "needs a Fighter" costs a player, because it
 * tells them a door is shut that was open. So a requirement is only
 * declared for a gate that was read in the destination page's own code, and
 * everything unverified stays open. Realm Guardians is the case that proves
 * the rule -- it reads like it needs a realm and does not: "You have no
 * realm, so you hold the wall with conscripts."
 *
 * CHEAP, GUARDED, AND NEVER FATAL. This runs on the first page after login
 * and, through the nav, potentially on every page. Two indexed COUNTs,
 * memoised per request, every read suppressed, and any failure degrades to
 * "unknown" -- which renders exactly like today's page does. Nothing here
 * may ever be the reason a page does not load.
 */

/**
 * The account's facts. Memoised: the nav and the page body both ask.
 *
 * 'known' false means we could not establish anything -- signed out, or a
 * read failed -- and every caller must treat that as "show it all", never
 * as "nothing is available".
 */
function player_state($conn, $user_id = null) {
	static $memo = array();

	if ($user_id === null) {
		$user_id = isset($_SESSION['userData']['user_id'])
			? (int)$_SESSION['userData']['user_id'] : 0;
	}
	$user_id = (int)$user_id;
	if (isset($memo[$user_id])) return $memo[$user_id];

	$s = array('known' => false, 'wallets' => 0, 'nfts' => 0, 'fighters' => 0);
	if ($user_id <= 0 || !is_object($conn) || !method_exists($conn, 'query')) {
		return $memo[$user_id] = $s;
	}

	$one = function ($sql) use ($conn) {
		$r = @$conn->query($sql);
		if (!$r) return null;                 // missing table, or a bad day
		$row = $r->fetch_assoc();
		return $row === null ? 0 : (int)$row['n'];
	};

	/* Staked NFTs. nfts.user_id is how the whole platform decides what you
	   hold -- removeUser() sets it to 0 and verifyNFTs() sets it back. */
	$nfts = $one("SELECT COUNT(*) AS n FROM nfts WHERE user_id = $user_id");
	/* invalid = 0 matches what the Activity leaderboard counts: a Fighter
	   stranded by a later rules change cannot be sent anywhere. */
	$figs = $one("SELECT COUNT(*) AS n FROM dhc_fighters WHERE user_id = $user_id AND invalid = 0");
	$wals = $one("SELECT COUNT(*) AS n FROM wallets WHERE user_id = $user_id");

	/* ONE FAILED READ DOES NOT POISON THE REST, but a failure of the read a
	   lock depends on must leave that lock unset rather than wrongly shut.
	   null means "could not tell", which the met() tests below pass. */
	$s['nfts']     = $nfts === null ? -1 : $nfts;
	$s['fighters'] = $figs === null ? -1 : $figs;
	$s['wallets']  = $wals === null ? -1 : $wals;
	$s['known']    = true;
	return $memo[$user_id] = $s;
}

/**
 * The requirements a catalogue item may declare.
 *
 * Deliberately few. Each one was read out of the destination page rather
 * than assumed:
 *   nft      missions.php ("You must own at least 1 NFT from ..."),
 *            realms.php, gauntlets.php (every run is keyed on an nft_id)
 *   fighter  dhcarena.php ("No Fighters yet"), dhcbosses.php (a Crew of
 *            three, and its own header says so)
 */
function player_need_label($need) {
	switch ($need) {
		case 'nft':     return 'needs a staked NFT';
		case 'fighter': return 'needs a Fighter';
	}
	return '';
}

/**
 * Can they? true, false, or TRUE WHEN WE CANNOT TELL.
 *
 * Unknown resolves to yes on purpose. The cost of a wrong yes is one click
 * that lands on a page with a good empty state; the cost of a wrong no is
 * somebody believing a part of the platform is closed to them.
 */
function player_need_met($need, $state) {
	if ($need === '' || $need === null) return true;
	if (empty($state['known'])) return true;
	switch ($need) {
		case 'nft':     return ((int)$state['nfts'])     !== 0;   // -1 is "could not tell"
		case 'fighter': return ((int)$state['fighters']) !== 0;
	}
	return true;
}

/**
 * Where to go to meet it, as array(url, label).
 *
 * THE REMEDY DEPENDS ON THE ACCOUNT, which is the whole point of saying it
 * here rather than printing a generic "locked". Somebody with no wallet is
 * told to connect one; somebody with a wallet and nothing staked has a
 * different problem and a different page.
 */
function player_need_fix($need, $state) {
	switch ($need) {
		case 'nft':
			return ((int)$state['wallets']) === 0
				? array('wallets.php',    'Connect a wallet')
				: array('collections.php', 'Find a supported NFT');
		case 'fighter':
			return array('dhcfighters.php', 'Build a Fighter');
	}
	return array('', '');
}

/**
 * The attributes a nav link should carry, or '' when it should carry none.
 *
 * THE MENU MARKS THE SAME THINGS THE LAUNCHPAD MARKS, from the same table,
 * because a menu that disagrees with the page is worse than one that says
 * nothing. The requirement is looked up by URL in the catalogue rather than
 * restated here -- see lp_need_for_url().
 *
 * MARKS, NEVER HIDES. A menu that removes entries teaches people the
 * platform is smaller than it is, and they stop looking. Dimming says "not
 * yet"; disappearing says "not for you", and the second one is a lie.
 *
 * Returns a leading-space attribute string so it drops straight into a tag.
 */
function player_nav_attrs($url, $state) {
	if (!function_exists('lp_need_for_url')) return '';
	$need = lp_need_for_url($url);
	if ($need === '' || player_need_met($need, $state)) return '';
	return ' class="nav-later" title="' . htmlspecialchars(player_need_label($need), ENT_QUOTES) . '"';
}
