<?php
/**
 * lib/launchpad-catalogue.php — the platform, as a list, in one place.
 *
 * LIFTED OUT OF launchpad.php BECAUSE A SECOND READER APPEARED.
 * admin-outreach.php builds a post telling a partner community what is on
 * the platform now, and the honest source for that is the same list the
 * Launchpad shows players -- it is the first page after login, so it is
 * kept current because people use it. A hand-written second copy in the
 * outreach tool would be wrong within a month, and wrong in the direction
 * that matters: a post that omits the game somebody would have come back
 * for.
 *
 * This platform has had the same bug four times with gateway lists and
 * chain maps, and admin_tabs() exists for exactly this reason: two copies
 * of a list are one list and one bug.
 *
 * NOTHING BUT THE ARRAY LIVES HERE. No db.php, no session, no output --
 * so any page can require it, including one that has already drawn its
 * header. launchpad.php requires this and then renders it; the outreach
 * tool requires it and reads the names.
 */

/*
 * A FIFTH ELEMENT, OPTIONAL: what the account must already have.
 *
 *   array(url, emoji, name, one-line description [, requirement])
 *
 * Read by player_need_met() in lib/player-state.php, which the Launchpad
 * groups by and the nav marks by. Readers that only want the names --
 * admin-outreach.php builds a partner post from this list -- index 0 to 3
 * and are unaffected.
 *
 * ONLY VERIFIED GATES CARRY ONE. A tile wrongly marked ready costs a click;
 * a tile wrongly marked "needs a Fighter" tells somebody a door is shut
 * that was open, and they believe it. So each requirement below was read
 * out of the destination page, and everything unchecked stays open. Realm
 * Guardians is the case that proves it -- it reads like it needs a realm
 * and explicitly does not: "You have no realm, so you hold the wall with
 * conscripts." The four Drop Ship themes are unmarked for the same reason:
 * nobody has established what they do with an empty roster.
 */

/*
 * The platform, as a player sees it. Mirrors the nav's own grouping (Play /
 * NFTs / Stats / Account) rather than inventing a second taxonomy, so someone
 * who learns this page can still find things in the menu afterwards.
 *
 * Emoji rather than image icons: images deploy by FTP outside this repo, so
 * 26 new icons would mean 26 uploads that have to land before this page stops
 * looking broken -- and this is the first page after login.
 */
$lp_sections = array(
	'Play' => array(
		'accent' => '#ffcc44',
		'blurb'  => 'Every game on the platform. Most pay CARBON just for finishing a run.',
		'items'  => array(
			/*
			 * DHC LEADS THE LIST. It is four surfaces of one activity -- build
			 * a Fighter, fight a rival with it, fight the community's bosses
			 * with it, field it as a soldier -- and it is the thing a new
			 * player is most likely to be here for. Everything below is a
			 * game you can play on its own; these four feed each other, so
			 * they read as a block rather than four entries scattered through
			 * an alphabet.
			 */
			array('dhcfighters.php',     '🧬', 'DHC Fighters',    'Earn traits, assemble Fighters, top the board'),
			array('dhcarena.php',        '🏟️', 'DHC Arena',       'Send a Crew of three against a rival', 'fighter'),   // dhcarena.php's own empty state is "No Fighters yet"
			array('dhcbosses.php',       '☠️', 'DHC Bosses',      'Three shared bosses, one health bar each', 'fighter'),   // its header: a Crew of three, same as Arena
			/* ?project_id=5 for the same reason every Drop Ship entry below
			   carries one: they are a single codebase switched by a session
			   value, so without it each tile opens whichever game the session
			   was last left on. */
			array('dropship/dashboard.php?project_id=5', '🚀', 'DHC Drop Ship', 'Field your Fighters as soldiers'),

			array('missions.php',        '🗺️', 'Missions',        'Send NFTs on timed runs for points', 'nft'),   // missions send NFTs; an item-only load-out still needs some at home
			array('realms.php',          '🏰', 'Realms',          'Build a realm, raid your rivals', 'nft'),   // "You must own at least 1 NFT from ..."
			array('guardians.php',       '🛡️', 'Realm Guardians', 'Hold your realm against the horde'),
			array('cryptcrawl.php',      '💀', 'Crypt Crawl',     'Solo dungeon card game'),
			array('cryptconquest.php',   '👑', 'Crypt Conquest',  'Regicide-style card game'),
			array('monstrocity.php',     '👾', 'Monstrocity',     'Match 3 RPG campaign'),
			array('monstrocity.php#boss', '🐉', 'Boss Battles',    'Co-op community boss fights'),
			array('skullswap.php',       '🔄', 'Skull Swap',      'Match 3 score chase'),
			array('skullracer.php',      '🏁', 'Skull Racer',     'Pseudo-3D arcade racer'),
			array('gauntlets.php',       '🥊', 'Gauntlets',       'NFT roguelike run', 'nft'),   // every run is keyed on an nft_id
			array('obscura.php',         '🔍', 'Obscura',         'Name the collection from a sliver'),

			// dashboard.php, NOT the bare directory. `dropship/` serves index.php,
			// which is an unconditional redirect to the Skulliance login -- so a
			// player who was ALREADY logged in got bounced to a login page. The
			// nav link in header.php has always pointed at dashboard.php, which is
			// why that one worked and this one did not.
			// ?project_id= on EVERY one of them, not just the Lounge. They are one
			// codebase switched by a session value, so without it each tile opened
			// whichever game the session was last left on -- click Oculus Lounge,
			// then Drop Ship, and you got the Lounge again. dropship.php validates
			// the id against getProjects(). The ids are the ones in
			// dropshipArtThemes(): 1 Drop Ship, 2 Dread City, 3 Filthy Mermaid,
			// 4 Oculus Lounge.
			array('dropship/dashboard.php?project_id=1', '🪖', 'Drop Ship',      'NFT battler'),
			array('dropship/dashboard.php?project_id=2', '🌃', 'Dread City',     'NFT battler, Dread City theme'),
			array('dropship/dashboard.php?project_id=3', '🧜‍♀️', 'Filthy Mermaid', 'NFT battler, Filthy Mermaid theme'),
			array('dropship/dashboard.php?project_id=4', '🪩', 'Oculus Lounge',  'NSFW Club Crawler'),
		),
	),
	'Collect' => array(
		'accent' => '#4fa3ff',
		'blurb'  => 'Your NFTs, and the places to get more of them.',
		'items'  => array(
			array('my-nfts.php',         '🎒', 'My NFTs',         'Everything you have staked'),
			array('store.php',           '🏪', 'Store',           'Free member claims'),
			array('auctions.php',        '🔨', 'Auctions',        'Bid on NFTs'),
			array('raffles.php',         '🎟️', 'Raffles',         'Ticketed draws'),
			array('gallery.php',         '🖼️', 'Gallery',         'Browse the collections'),
			array('collections.php',     '📚', 'Collections',     'Every supported project'),
			array('musicvideos.php',     '🎬', 'Music Videos',    'Made with the projects\' art'),
			array('diamond-skulls.php',  '💎', 'Diamond Skulls',  'Delegation rewards'),
			array('skulliverse.php',     '🌌', 'Skulliverse',     'The wider universe'),
		),
	),
	'Progress' => array(
		'accent' => '#00c8a0',
		'blurb'  => 'Where you stand, and what your points are worth.',
		'items'  => array(
			array('leaderboards.php',    '🏆', 'Leaderboards',    'Every board, with who leads it'),
			array('profile.php',         '🪪', 'Profile',         'Your public page'),
			array('points.php',          '⚡', 'Points',          'What you have earned'),
			array('crafting.php',        '⚗️', 'Crafting',        'Burn CARBON for DIAMOND'),
			array('transactions.php',    '🧾', 'Transactions',    'Your full history'),
			array('wallets.php',         '👛', 'Wallets',         'Connected addresses'),
			array('analytics.php',       '📊', 'Analytics',       'Platform-wide numbers'),
			array('skullpaper.php',      '📖', 'Skull Paper',     'How all of it works'),
		),
	),
);

/**
 * The requirement declared for a URL, or '' when there is none.
 *
 * HERE SO THE NAV AND THE LAUNCHPAD CANNOT DISAGREE. The nav's link list
 * lives in header.php and is written out by hand; this is the lookup that
 * lets it mark the same things this page marks, without a second table of
 * requirements that would drift from the first one inside a month.
 *
 * Matched on the path only -- the nav carries query strings and anchors
 * that the catalogue does not, and vice versa.
 */
function lp_need_for_url($url) {
	global $lp_sections;
	static $map = null;
	if ($map === null) {
		$map = array();
		foreach ((array)$lp_sections as $sec) {
			foreach ($sec['items'] as $it) {
				if (empty($it[4])) continue;
				$map[strtok((string)$it[0], '?#')] = $it[4];
			}
		}
	}
	$path = strtok((string)$url, '?#');
	return isset($map[$path]) ? $map[$path] : '';
}

/**
 * Catalogue items split into what this account can do and what it cannot.
 *
 * READY FIRST, LOCKED AFTER, ORIGINAL ORDER WITHIN EACH. Not three new
 * sections: the Play / Collect / Progress grouping mirrors the nav, which
 * is the thing somebody learns and then uses for the next year, and
 * replacing it with buckets that collapse into one the moment an account is
 * set up would be churn rather than design.
 */
function lp_split_items($items, $state) {
	$ready = array();
	$later = array();
	foreach ((array)$items as $it) {
		$need = isset($it[4]) ? $it[4] : '';
		if (player_need_met($need, $state)) $ready[] = $it;
		else                                $later[] = $it;
	}
	return array($ready, $later);
}
