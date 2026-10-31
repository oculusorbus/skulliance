<?php
/* launchpad-state-harness.php - CLI only. No database, no network.
 *
 * THE LAUNCHPAD STOPPED BEING A LIST AND BECAME A JUDGEMENT ABOUT SOMEBODY,
 * which is a different kind of thing to get wrong. A list can only be
 * incomplete; a judgement can be confidently false, and this one is printed
 * on the first page after login, in front of the people least able to tell
 * that it is lying.
 *
 * THE ASYMMETRY IS THE WHOLE DESIGN. A tile wrongly shown as ready costs one
 * click onto a page with a good empty state. A tile wrongly marked "needs a
 * Fighter" tells somebody a door is shut that was open, and they believe it
 * and never try again. So every failure mode here has to fall the same way:
 * unknown means AVAILABLE. Signed out, a dead table, a 32-bit surprise, a
 * requirement nobody has defined -- all of them must resolve to "show it".
 *
 * Usage: php launchpad-state-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/player-state.php';
require_once __DIR__ . '/lib/launchpad-catalogue.php';

function st($over = array()) {
	return array_merge(array('known' => true, 'wallets' => 1, 'nfts' => 5, 'fighters' => 2), $over);
}

echo "unknown always means available, never locked\n";
{
	/*
	 * EVERY WAY OF NOT KNOWING, and all of them have to fall open. This is
	 * the only group of assertions in the file that protects a PLAYER rather
	 * than a number.
	 */
	$signed_out = array('known' => false, 'wallets' => 0, 'nfts' => 0, 'fighters' => 0);
	ok(player_need_met('nft', $signed_out) === true,
	   'a signed-out visitor is told the platform is locked to them');
	ok(player_need_met('fighter', $signed_out) === true,
	   'a signed-out visitor is told Arena is locked to them');

	/* -1 is what player_state() stores when a read FAILED, as opposed to 0
	   which means the account genuinely has none. Conflating those two is
	   how a database hiccup turns into "you have nothing". */
	ok(player_need_met('nft', st(array('nfts' => -1))) === true,
	   'a failed NFT count reads as "you have no NFTs" rather than "could not tell"');
	ok(player_need_met('fighter', st(array('fighters' => -1))) === true,
	   'a failed Fighter count reads as "you have no Fighters"');

	/* A requirement nobody has defined is not a lock. */
	ok(player_need_met('spaceship', st(array('nfts' => 0, 'fighters' => 0))) === true,
	   'an unrecognised requirement locks the tile, so a typo in the catalogue closes a door');
	ok(player_need_met('', st()) === true, 'an item with no requirement is being gated');
	ok(player_need_met(null, st()) === true, 'a null requirement is being gated');
}

echo "\nand a real zero does mean locked\n";
{
	/* The other half: if unknown always passed AND zero passed, the feature
	   would be decorative and every check above would be vacuous. */
	ok(player_need_met('nft', st(array('nfts' => 0))) === false,
	   'an account with no staked NFTs is told Missions is ready');
	ok(player_need_met('fighter', st(array('fighters' => 0))) === false,
	   'an account with no Fighters is told Arena is ready');
	ok(player_need_met('nft', st(array('nfts' => 1))) === true,
	   'one staked NFT is not enough to unlock an NFT game');
}

echo "\nthe remedy depends on the account, which is why it is computed\n";
{
	/*
	 * Somebody with no wallet and somebody with a wallet and nothing staked
	 * have different problems. Sending both to the same page is what a
	 * hardcoded "locked" badge would do, and it is wrong for one of them
	 * every time.
	 */
	list($url, $label) = player_need_fix('nft', st(array('wallets' => 0, 'nfts' => 0)));
	ok($url === 'wallets.php', "no wallet should send them to wallets.php, not $url");
	ok(stripos($label, 'connect') !== false, "the label does not say what to do: $label");

	list($url, $label) = player_need_fix('nft', st(array('wallets' => 2, 'nfts' => 0)));
	ok($url !== 'wallets.php',
	   'somebody who already has two wallets connected is told to connect a wallet');
	ok($url !== '' && $label !== '', 'there is no remedy for a staked-NFT requirement');

	list($url,) = player_need_fix('fighter', st(array('fighters' => 0)));
	ok($url === 'dhcfighters.php', "the Fighter remedy points at $url");

	/* Every declared requirement has a label and a remedy, or a tile shows
	   an empty amber chip and an empty link. */
	foreach (array('nft', 'fighter') as $need) {
		ok(player_need_label($need) !== '', "the '$need' requirement has no label");
		$fix = player_need_fix($need, st());
		ok($fix[0] !== '' && $fix[1] !== '', "the '$need' requirement has no remedy");
	}
}

echo "\nonly verified gates are declared, and Guardians is the proof\n";
{
	/*
	 * Realm Guardians READS like it needs a realm and does not: "You have no
	 * realm, so you hold the wall with conscripts." It is the item that
	 * proves the rule, so it is asserted by name -- if somebody ever marks
	 * it, this says why they should not.
	 */
	$declared = array();
	foreach ($lp_sections as $sec) {
		foreach ($sec['items'] as $it) if (!empty($it[4])) $declared[$it[2]] = $it[4];
	}
	ok(!isset($declared['Realm Guardians']),
	   'Realm Guardians is marked as needing something; its own page says it works without a '
	 . 'realm -- "you hold the wall with conscripts"');

	/* The five that WERE read out of their destination pages. */
	foreach (array('DHC Arena' => 'fighter', 'DHC Bosses' => 'fighter',
	               'Missions' => 'nft', 'Realms' => 'nft', 'Gauntlets' => 'nft') as $name => $need) {
		ok(isset($declared[$name]) && $declared[$name] === $need,
		   "$name no longer declares '$need'");
	}
	/* And nothing has been added that player-state.php cannot answer. */
	foreach ($declared as $name => $need) {
		ok(player_need_label($need) !== '',
		   "$name declares '$need', which lib/player-state.php does not know -- it will show "
		 . 'as an empty chip and lock nothing');
	}
}

echo "\nthe catalogue's other reader still works\n";
{
	/*
	 * admin-outreach.php builds a partner post from this same list, which
	 * is the reason the catalogue was lifted out of launchpad.php in the
	 * first place. It filters on `count($it) >= 4` -- a >= that happens to
	 * tolerate the fifth element. If anybody ever tightens it to == 4, five
	 * games silently vanish from the post that tells a partner community
	 * what is on the platform, and nothing anywhere would say so.
	 */
	$out = (string)file_get_contents(__DIR__ . '/admin-outreach.php');
	ok(strpos($out, 'count($it) >= 4') !== false,
	   'admin-outreach.php no longer accepts items with a requirement on them; the five gated '
	 . 'games will be missing from the partner post');

	$gated = 0;
	foreach ($lp_sections['Play']['items'] as $it) if (count($it) > 4) $gated++;
	ok($gated > 0, 'no Play item carries a requirement, so the check above is vacuous');
}

echo "\nthe nav and the page read the same table\n";
{
	/*
	 * The nav's link list is written out by hand in header.php. If it grew a
	 * second table of requirements the two would disagree within a month,
	 * which on this platform is not a hypothetical -- it is why admin_tabs()
	 * and activity_sources() exist.
	 */
	ok(lp_need_for_url('dhcarena.php') === 'fighter', 'the URL lookup does not find Arena');
	ok(lp_need_for_url('missions.php') === 'nft', 'the URL lookup does not find Missions');
	ok(lp_need_for_url('obscura.php') === '', 'the URL lookup invented a requirement for Obscura');
	ok(lp_need_for_url('nonsense.php') === '', 'an unknown URL gets a requirement');
	/* The nav carries query strings and anchors the catalogue does not. */
	ok(lp_need_for_url('missions.php?project_id=5') === 'nft',
	   'a query string defeats the lookup, so the nav marks nothing');
	ok(lp_need_for_url('monstrocity.php#boss') === '', 'an anchor defeats the lookup');
}

echo "\nready first, locked after, and nothing is dropped\n";
{
	$items = array(
		array('a.php', '1', 'Open A',  'x'),
		array('b.php', '2', 'Needs',   'x', 'fighter'),
		array('c.php', '3', 'Open C',  'x'),
	);
	list($ready, $later) = lp_split_items($items, st(array('fighters' => 0)));
	ok(count($ready) === 2 && count($later) === 1, 'the split put the wrong items in each half');
	ok($ready[0][2] === 'Open A' && $ready[1][2] === 'Open C',
	   'the original order was not kept within the ready half');
	ok($later[0][2] === 'Needs', 'the locked item is not in the locked half');

	/* NOTHING MAY VANISH. A tile that disappears is worse than one that is
	   dimmed: the player cannot find out it exists, and the catalogue is
	   also what admin-outreach.php tells partner communities is here. */
	$all = array_merge($ready, $later);
	ok(count($all) === count($items), 'the split lost an item');

	/* With everything unlocked the page must be exactly what it was. */
	list($ready2, $later2) = lp_split_items($items, st());
	ok(count($later2) === 0 && count($ready2) === 3,
	   'a fully-equipped account still sees locked tiles');
	ok(array_merge($ready2, $later2) === $items,
	   'a veteran account gets its tiles reordered for no reason');
}

echo "\nthe page marks but never blocks\n";
{
	$page = (string)file_get_contents(__DIR__ . '/launchpad.php');
	$code = harness_strip_comments($page);

	/*
	 * A LOCKED TILE IS STILL A LINK. Every one of these destinations has an
	 * empty state that explains itself better than a dead tile could, and
	 * refusing the click would BE the brick wall this exists to remove.
	 */
	ok(strpos($code, 'lp-tile-later') !== false, 'the not-yet state is not rendered');
	ok(preg_match('/<a class="lp-tile<\?php echo \$lp_ok \? \'\' : \' lp-tile-later\'/', $code) === 1,
	   'the tile is no longer an <a> carrying the state as a class -- check it has not become '
	 . 'a disabled element');
	ok(strpos($code, 'disabled') === false && strpos($code, 'pointer-events: none') === false,
	   'a tile is being disabled rather than dimmed');

	/*
	 * NO LINK INSIDE A TILE. The tile is an <a>; an <a> inside an <a> is the
	 * bug that hoisted the body out of every card on the Activity wall, and
	 * it is why the remedies are printed once above the sections instead.
	 */
	$tile = substr($code, strpos($code, 'class="lp-tile'));
	$tile = substr($tile, 0, strpos($tile, '</a>'));
	ok(strpos($tile, '<a ') === false,
	   'there is an anchor inside the tile anchor, which the browser will unnest');

	ok(strpos($code, 'player_state(') !== false, 'the page no longer reads the account state');
	ok(substr_count($code, 'player_state(') === 1,
	   'player_state() is called more than once; it is memoised, but two calls means two '
	 . 'places deciding the same thing');
}

echo "\nthe menu marks the same things, and can never be the reason a page dies\n";
{
	$hdr = (string)file_get_contents(__DIR__ . '/header.php');
	$code = harness_strip_comments($hdr);

	/* Same table as the page, looked up by URL -- not a second list of
	   requirements written out in the nav, which is the duplication this
	   codebase has shipped four times with gateway lists and chain maps. */
	ok(strpos($code, 'player_nav_attrs(') !== false, 'the nav no longer marks anything');
	ok(strpos($code, "'nft'") === false && strpos($code, "'fighter'") === false,
	   'header.php names a requirement itself; it must look them up by URL so the menu and '
	 . 'the Launchpad cannot disagree');

	/*
	 * MARKED, NEVER HIDDEN. A menu that removes entries teaches somebody the
	 * platform is smaller than it is and they stop looking for the rest.
	 */
	foreach (array('missions.php', 'realms.php', 'dhcarena.php', 'dhcbosses.php', 'gauntlets.php') as $u) {
		ok(preg_match('~<a href="' . preg_quote($u, '~') . '"<\?php echo player_nav_attrs~', $code) === 1,
		   "the nav entry for $u is not marked, or has stopped being a plain link");
	}

	/*
	 * THIS IS THE NAVIGATION ON SIXTY-THREE PAGES. It must be impossible for
	 * it to be the reason one of them does not draw: no state read without a
	 * connection, and the library's absence must not be fatal.
	 */
	ok(strpos($code, "isset(\$conn) && is_object(\$conn)") !== false,
	   'the nav reads player state without checking there is a database connection -- '
	 . 'header.php includes nothing of its own and only has $conn because the page loaded it');
	ok(strpos($code, "is_file(__DIR__ . '/lib/player-state.php')") !== false,
	   'header.php requires the state library without checking it is there, so a half-deployed '
	 . 'pull takes down every page at once');
	ok(strpos($code, '$sk_nav_state = array(') !== false,
	   'there is no safe default for $sk_nav_state, so a skipped branch leaves it undefined in '
	 . 'the markup below');

	/* And the default must be the one that marks nothing. */
	ok(player_nav_attrs('dhcarena.php', array('known' => false)) === '',
	   'an unknown account gets menu entries dimmed, which tells a signed-in member with a '
	 . 'momentary read failure that half the platform is shut');
	ok(player_nav_attrs('dhcarena.php', st(array('fighters' => 0))) !== '',
	   'an account with no Fighters gets no mark at all, so the feature does nothing');
	ok(strpos(player_nav_attrs('dhcarena.php', st(array('fighters' => 0))), 'title=') !== false,
	   'the mark carries no sentence, so it reads as "something is wrong with this link"');
	ok(player_nav_attrs('obscura.php', st(array('fighters' => 0, 'nfts' => 0))) === '',
	   'a game with no requirement is being marked');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
