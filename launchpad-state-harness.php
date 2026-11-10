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
	/*
	 * THE TILE RULES, not the whole file. The hero banner's scrim carries
	 * `pointer-events: none` so that clicks pass THROUGH it to the links
	 * underneath -- the opposite of disabling something -- and a file-wide
	 * search read that as a tile being switched off.
	 */
	$lpTileCss = '';
	foreach (preg_split('~\}~', $code) as $chunk) {
		/* ::after is the scrim over the art, and it carries
		   pointer-events:none so clicks reach the tile underneath -- the
		   opposite of disabling it. */
		if (preg_match('~::(after|before)~', $chunk)) continue;
		if (preg_match('~\.lp-tile~', $chunk)) $lpTileCss .= $chunk . '}';
	}
	ok(strpos($lpTileCss, 'pointer-events: none') === false,
	   'a tile is being disabled rather than dimmed');
	ok(strpos($code, 'disabled') === false,
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

	/*
	 * START HERE SENDS PEOPLE TO THE BEST VERSION OF THE THING.
	 *
	 * "Claim your daily reward" pointed at points.php, which still carries
	 * the original seven-row renderer and its own claim button. The reward
	 * is a chest that opens on missions.php, and this tile is shown to
	 * somebody on the day they claim their FIRST one -- the one day it
	 * matters which version they meet. The deep link lands on the daily
	 * strip rather than the top of a long page.
	 */
	ok(preg_match('~\$lp_todo\[\] = array\(\x27missions\.php#ms-sec-daily\x27, \x27Claim your daily reward~', $code) === 1,
	   'the daily reward tile no longer points at the missions daily strip');
	ok(strpos($code, 'points.php') === false,
	   'the launchpad still sends somebody to points.php, which has the old claim UI');
	/* And the anchor has to exist on the page it points at. */
	$ms = harness_strip_comments((string)file_get_contents(__DIR__ . '/missions.php'));
	ok(strpos($ms, 'id="ms-sec-daily"') !== false,
	   'missions.php has no #ms-sec-daily anchor, so the deep link lands at the top of the page');

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


echo "\nthe line under the name says what the account has done\n";
{
	require_once __DIR__ . '/lib/launchpad-stats.php';
	$hsLab = function ($row) {
		$o = array();
		foreach ($row as $st) $o[] = lp_stat_label($st);
		return implode(' ', $o);
	};
	/*
	 * A ZERO IS NOT A STAT. "0 raids" under your own name tells you that
	 * you are failing at something you have not started, which is the
	 * opposite of what a launchpad is for.
	 */
	$hsBoth = lp_hero_stats(3, 40, 12, 9);
	ok($hsLab($hsBoth) === 'day streak missions running raids running',
	   'an account with both out does not get both: ' . $hsLab($hsBoth));
	$hsNoRaid = lp_hero_stats(3, 40, 0, 9);
	ok(strpos($hsLab($hsNoRaid), 'raid') === false,
	   'a player who has never raided is told they have 0 raids');
	$hsNoMis = lp_hero_stats(3, 0, 12, 9);
	ok(strpos($hsLab($hsNoMis), 'mission') === false,
	   'a player who has never sent a mission is told they have 0 missions');
	/* And the one that is dropped must not take the other with it. */
	ok(strpos($hsLab($hsNoMis), 'raids') !== false,
	   'dropping the missions stat drops raids as well, leaving a bare two-item row');
	/*
	 * WALLETS IS THE FALLBACK, NOT A STAT. It is the same number for a
	 * member in their first minute and one in their third year, and
	 * Start Here already asks for it -- so it shows only when there is
	 * nothing done to show instead.
	 */
	ok(strpos($hsLab($hsBoth), 'wallet') === false,
	   'wallets is back on the row for an account that has done things');
	$hsNew = lp_hero_stats(0, 0, 0, 0);
	ok($hsLab($hsNew) === 'day streak wallets',
	   'a brand new account gets a one-item row under its name instead of the wallet count: '
	 . $hsLab($hsNew));
	/* A zero wallet count is worth saying, because connecting one is
	   step one -- so the fallback must not drop out on zero too. */
	ok(count(lp_hero_stats(0, 0, 0, 0)) === 2,
	   'the fallback drops out at zero as well, so a new account gets no third stat at all');

	/*
	 * NO POINTS BALANCE. It led the row and was the least useful thing
	 * on it: a running total that only goes up, already on points.php
	 * and in the nav, and at eight digits it set the scale for
	 * everything beside it -- a 3 next to 19,875,730 does not read as a
	 * number at all.
	 */
	ok(strpos($hsLab($hsBoth), 'point') === false,
	   'the points balance is back on the row, setting the scale for the two numbers that matter');
	ok(strpos((string) file_get_contents(__DIR__ . '/lib/launchpad-stats.php'), '$points') === false,
	   'the stat row still takes a points balance, so it is one caller away from showing it again');
	/* "1 day streaks" is wrong and "1 raids" is wrong; the plural is on
	   the day for one and on the noun for the other. */
	ok(lp_stat_label(array(1, 'raid', 'raids')) === 'raid'
	   && lp_stat_label(array(2, 'raid', 'raids')) === 'raids',
	   'the label does not follow the count');
	$hsOne = lp_hero_stats(1, 1, 1, 1);
	ok($hsLab($hsOne) === 'day streak mission running raid running',
	   'a count of one reads as a plural: ' . $hsLab($hsOne));

	/*
	 * AND THE PAGE HAS TO ASK THE RIGHT QUESTIONS. Raids hang off the
	 * realm -- raids.offense_id is a realms.id -- so a count that filters
	 * raids on user_id finds nothing, and one that forgets the realm
	 * join counts every raid on the platform.
	 */
	$hsPage = (string) file_get_contents(__DIR__ . '/launchpad.php');
	ok(preg_match('~FROM missions\s+WHERE user_id = \x27" \. \$lp_user_id~s', $hsPage) === 1,
	   'the mission count is not scoped to this account');
	ok(preg_match('~INNER JOIN realms ON realms\.id = raids\.offense_id\s+WHERE realms\.user_id~s', $hsPage) === 1,
	   'the raid count does not reach the user through their realm, so it is either empty or everybody\'s');
	/*
	 * AND IT HAS TO BE WHAT IS OUT, NOT WHAT EVER WAS. An unfiltered
	 * count puts a five-figure career total under the name -- the same
	 * shape as the points balance beside it, and silent about today.
	 * status/outcome '0' is in progress on both tables; 1 is a success
	 * and 2 is a failure, which is the leaderboards' own definition.
	 */
	ok(preg_match('~FROM missions\s+WHERE user_id =.*?AND status = \x270\x27~s', $hsPage) === 1,
	   'the mission count is every mission ever sent, not the ones out on a run now');
	ok(preg_match('~FROM raids\s+INNER JOIN realms.*?AND raids\.outcome = \x270\x27~s', $hsPage) === 1,
	   'the raid count is every raid ever launched, not the ones in progress now');
}

echo "\nthe hero banner degrades, and never blocks the links on it\n";
{
	$bn = (string) file_get_contents(__DIR__ . '/launchpad.php');
	/*
	 * THE HEADING IS THE NAME. It used to pick between "Welcome back,
	 * <name>" and "Welcome to Skulliance, <name>" off three signals, so
	 * that a first-time member was not greeted as a returning one. All
	 * of that went: the greeting said nothing the player did not know
	 * and made the only real information on the line a suffix.
	 *
	 * Asserted as an absence AND a presence, because the easy way to
	 * bring it back is a new wording rather than the old one.
	 */
	/* STRINGS AND MARKUP, NOT THE WHOLE FILE. The paragraph above quotes
	   both old greetings to explain why they went, so a plain search over
	   the source matches my own comment and passes whatever the page
	   actually renders. token_get_all is the oracle: only what can reach
	   the browser -- string literals and inline HTML -- is looked at. */
	$bnSaid = '';
	foreach (token_get_all($bn) as $bnT) {
		if (is_array($bnT) && ($bnT[0] === T_CONSTANT_ENCAPSED_STRING
		    || $bnT[0] === T_INLINE_HTML || $bnT[0] === T_ENCAPSED_AND_WHITESPACE)) {
			/* A <style> block is inline HTML, so its CSS comments come
			   through here and are just as much prose as the PHP ones. */
			$bnSaid .= preg_replace('~/\*.*?\*/~s', '', $bnT[1]) . "\n";
		}
	}
	ok(stripos($bnSaid, 'welcome') === false,
	   'the launchpad greets again instead of simply naming the account');
	ok(preg_match('~\$lp_greeting = \$lp_user !== \x27\x27 \? \$lp_user :~', $bn) === 1,
	   'the heading is not the username, so the one thing it is there to say is wrapped in something else');
	/* A login-gated page with a blank <h2> is a worse failure than a
	   generic one, so the empty-name branch has to survive. */
	ok(preg_match('~\$lp_greeting = .*: \x27[^\x27]+\x27;~', $bn) === 1,
	   'a half-read session renders an empty heading rather than anything at all');
	/*
	 * IMAGES DEPLOY BY FTP, NOT WITH THE CODE. Until launchpad-hero.jpg
	 * is on the server this has to be exactly the page that was there
	 * before: dark ground, no broken frame, nothing moved. The art is an
	 * improvement layered on, never a dependency.
	 */
	/*
	 * THE FALLBACK IS NOW AN ABSENCE, which is a stronger one than the
	 * colour it replaced. The art moved off .lp-banner onto .lp-stage-art
	 * so it could run behind the whole page, and the banner became a hole
	 * the art shows through. Neither may paint a background: the banner
	 * would hide the art it sits on, and the art element would put a
	 * thirteen-hundred-pixel block over the page when the upload is
	 * missing. With neither, a 404 leaves the band falling through to the
	 * page's own ground -- byte for byte the banner that was there before
	 * the art existed.
	 */
	$bnBanner = '';
	if (preg_match('~\n\.lp-banner \{.*?\n\}~s', $bn, $bnm)) $bnBanner = $bnm[0];
	ok($bnBanner !== '', 'the .lp-banner rule is gone');
	ok(strpos($bnBanner, 'background') === false,
	   'the banner paints a background again, which hides the art it is supposed to be a hole in');
	$bnArt = '';
	if (preg_match('~\.lp-stage-art \{.*?\n\}~s', $bn, $bam)) $bnArt = $bam[0];
	ok($bnArt !== '', 'the .lp-stage-art rule is gone, so there is no backdrop');
	ok(strpos($bnArt, 'background-color') === false,
	   'the art declares a fallback colour, so a missing upload paints a slab over the page');
	/*
	 * AND IT ENDS BY BECOMING THE PAGE. The art has a hard bottom edge
	 * and on a wide screen you reach it, so the overlay's last stop has
	 * to be the page's own background rather than a colour that merely
	 * looks like it. Read from flexbox.css, so the two can never drift.
	 */
	$bnCss = (string) file_get_contents(__DIR__ . '/dist/flexbox.css');
	$bnGround = preg_match('~\nbody \{[^}]*background-color:\s*(#[0-9a-f]{6})~si', $bnCss, $bgm)
	          ? strtolower($bgm[1]) : '';
	ok($bnGround !== '', 'the page ground cannot be read from flexbox.css, so this check proves nothing');
	$bnFade = '';
	if (preg_match('~\.lp-stage-art::after \{.*?\n\}~s', $bn, $bfm)) $bnFade = $bfm[0];
	ok($bnFade !== '', 'the bottom of the art is not dissolved, so the picture ends in a hard edge');
	ok(preg_match('~linear-gradient\(0deg,\s*' . preg_quote($bnGround, '~') . '~i', $bnFade) === 1,
	   'the dissolve does not finish on the page ground (' . $bnGround . '), so the art ends in a seam');
	/*
	 * THE BACKDROP COVERS THE FIRST SECTION, so it lies over real links.
	 * z-index puts them above it, but a transparent box over a grid of
	 * tiles is one stacking mistake away from swallowing every tap on it.
	 */
	ok(strpos($bnArt, 'pointer-events: none') !== false,
	   'the backdrop can take clicks, and it lies over the first grid of tiles');
	/*
	 * ITS OWN SHAPE, FROM THE REAL FILE. 1728x1152 -- an aspect-ratio
	 * that does not match the upload crops or letterboxes it, and the
	 * fade distances below were measured against this one.
	 */
	ok(strpos($bnArt, 'aspect-ratio: 1728 / 1152') !== false,
	   'the backdrop is not the shape of the art, so cover crops the picture it exists to show');
	/*
	 * THE CROP IS A FRACTION OF THE WIDTH, NOT A NUMBER OF PIXELS.
	 *
	 * -180px is the owner's figure, found against the real art on a
	 * full-width browser. The box is the image's own shape, so its
	 * height follows its width -- at 1200px the picture is 800px tall
	 * and that same 180px is more than twice the share of it, walking
	 * the crop down the citadel as the window narrows. 180/2000 is 9%,
	 * so the shift is written as 9vw and clamped at 180px, which is
	 * where the 2000px container stops growing.
	 *
	 * Both halves are checked: a bare px shift is the bug, and a vw
	 * shift with no clamp keeps lifting on a 3000px monitor where the
	 * art has stopped widening.
	 */
	ok(preg_match('~background-position: 50% calc\(min\(9vw, 180px\) \* -1\)~', $bnArt) === 1,
	   'the crop shift is not a clamped fraction of the width, so it is only right at one window size');
	/*
	 * AND NOT ON A PHONE. Down there min-height wins, so cover is
	 * already scaling the picture up to fill the band rather than
	 * fitting it -- lifting it as well leaves a transparent strip along
	 * the bottom of the banner, inside the band.
	 */
	$bnPhone = preg_match('~@media \(max-width: 700px\) \{.*?\n\}~s', $bn, $bpm) ? $bpm[0] : '';
	ok($bnPhone !== '', 'the phone block is gone');
	/*
	 * AND IT HAS TO BE THE LAST THING IN THE SHEET.
	 *
	 * It used to sit in the middle, and CSS resolves a tie on source
	 * order: every base rule below it won. The phone never got
	 * minmax(140px) -- it got the 190px desktop track, which fits
	 * exactly one column in the ~385px a phone leaves, so every tile
	 * became a full-width square and the page became a mile of them.
	 * The smaller description size died the same way. Both rules were
	 * here, both were asserted, and neither had ever applied, because
	 * an assertion that a rule EXISTS is not an assertion that it wins.
	 *
	 * Checked as a position rather than as a list of selectors: if the
	 * block ends the stylesheet then nothing can be added after it, and
	 * nobody has to remember which properties it overrides.
	 */
	/* The STYLESHEET, not the file -- there is a </body> and a <script>
	   after it, and comparing against the rest of the file made this
	   fail on markup that has nothing to do with the cascade. */
	$bnEnd  = strpos($bn, $bnPhone);
	$bnShut = strpos($bn, '</style>', $bnEnd === false ? 0 : $bnEnd);
	ok($bnEnd !== false && $bnShut !== false
	   && trim(substr($bn, $bnEnd + strlen($bnPhone), $bnShut - $bnEnd - strlen($bnPhone))) === '',
	   'the phone block is not the last thing in the stylesheet, so any base rule below it wins the tie');
	/*
	 * A GRID ITEM WITH AN ASPECT RATIO NEEDS AN EXPLICIT WIDTH.
	 *
	 * Its default self-alignment is `normal`, which stretches to the
	 * track -- except, per CSS Sizing, for an item with an aspect ratio,
	 * where `normal` behaves as `start` and it sizes to its own content.
	 * Chrome stretches anyway; iOS Safari does not, so on a phone every
	 * tile shrank to the width of its own description and the column
	 * came out ragged, longest sentence making the widest panel.
	 *
	 * The backdrop has the mirror of the same problem: left:0 right:0
	 * resolved its width, then min-height made its HEIGHT definite and
	 * the ratio ran backwards to compute a width from it -- 270px times
	 * 1.5 is 405px against a 393px phone, twelve pixels of horizontal
	 * scroll on every page.
	 */
	ok(preg_match('~\.lp-tile\.lp-tile-art-on \{[^}]*width: 100%~s', $bn) === 1,
	   'the art tile has an aspect ratio and no width, so Safari sizes each one to its own text');
	ok(preg_match('~\.lp-stage-art \{[^}]*width: 100%;\s*\n\taspect-ratio~s', $bn) === 1,
	   'the backdrop lets its aspect ratio run backwards off the min-height and overflow the page');
	ok(preg_match('~\.lp-stage-art \{[^}]*background-position: 50% 0~s', $bnPhone) === 1,
	   'the phone block does not cancel the crop shift, so the bottom of the band goes transparent');
	/*
	 * AND IT NEVER COMES UP SHORT OF THE BAND. At 360px the natural
	 * height is 240px against a 270px banner, so aspect-ratio alone
	 * leaves bare page inside the banner. Both floors, both blocks.
	 */
	ok(preg_match('~\.lp-stage-art \{[^}]*min-height:\s*340px~s', $bn) === 1,
	   'the backdrop has no floor, so on a narrow window it stops above the bottom of the band');
	/*
	 * THE BLEED IS A VARIABLE BECAUSE THE COLUMN IS NOT ONE WIDTH.
	 * flexbox.css gives #row1 .col1of3 20px, and 4px under 700px -- so a
	 * hardcoded 20px hangs the art 16px off each side of a phone and
	 * gives the page a horizontal scrollbar.
	 */
	ok(preg_match('~\.lp-stage \{[^}]*--lp-bleed:\s*20px~s', $bn) === 1,
	   'the stage does not declare its bleed, so the art no longer reaches the menu edge');
	/* Three sides -- top, left and right -- and the top one is what makes
	   the art touch the menu instead of stopping a column-padding short
	   of it. Counted, because a margin that bleeds only sideways is the
	   state this started in and looks almost right. */
	ok(preg_match('~\.lp-stage \{.*?\n\tmargin: (calc\(var\(--lp-bleed\) \* -1\) ){2}0;~s', $bn) === 1,
	   'the stage does not bleed up as well as out, so there is a strip of page between the menu and the art');
	ok(strpos($bn, 'url(images/launchpad-hero.jpg)') !== false
	   && strpos($bn, 'url(https://') === false,
	   'the art is referenced absolutely; cookies here are host-only, so paths stay relative');

	/*
	 * THE SCRIM MUST NOT EAT THE CLICKS. Start Here is a list of links
	 * sitting on the art, and an absolutely positioned overlay across
	 * the whole band is exactly the thing that swallows taps.
	 */
	ok(preg_match('~\.lp-banner::before\s*\{[^}]*pointer-events:\s*none~s', $bn) === 1,
	   'the scrim does not pass clicks through, so Start Here cannot be tapped');
	ok(preg_match('~\.lp-banner-in\s*\{[^}]*position:\s*relative~s', $bn) === 1,
	   'the content is not lifted above the scrim, so the scrim paints over the text');

	/*
	 * MIN-HEIGHT, NOT PADDING. Start Here only renders while something is
	 * incomplete, so a band sized by its content would be tall mid-setup
	 * and short once finished -- the art changing size as a side effect
	 * of a checklist emptying.
	 */
	/* THE BASE RULE, not the phone one: the media query sets its own
	   min-height, so an unanchored match passed with the desktop floor
	   deleted. justify-content only appears in the base rule. */
	ok(preg_match('~\.lp-banner-in\s*\{[^}]*justify-content[^}]*min-height:\s*\d+px~s', $bn) === 1,
	   'the band is sized by its content, so it changes height when Start Here disappears');

	/* The documented trap in this very file: .main centres everything. */
	ok(preg_match('~\.lp-hero\s*\{[^}]*text-align:\s*left~s', $bn) === 1,
	   'the hero does not restate text-align, so the greeting centres itself over the art');
	/* Text on a photograph needs its own contrast, not the image's. */
	ok(strpos($bn, 'text-shadow') !== false,
	   'the text over the art has no shadow, so it depends on the image staying dark');
	/* A list of links keeps a surface; only prose sits straight on a photo. */
	ok(preg_match('~\.lp-banner \.lp-todo\s*\{[^}]*background:\s*rgba~s', $bn) === 1,
	   'Start Here sits directly on the photograph, where a row you must find and tap cannot be read');
	ok(preg_match('~\.lp-banner \.lp-todo\s*\{[^}]*border-radius:\s*0~s', $bn) === 1,
	   'the Start Here panel keeps the old rounded corner the rest of the platform has dropped');
}


echo "\nthe Play icons are art over emoji, never instead of it\n";
{
	/*
	 * The catalogue chose emoji for a specific reason: images deploy by
	 * FTP outside this repo, so an icon that has not landed yet would
	 * break the first page after login. That reason has not gone away --
	 * it is answered. Every item keeps its emoji, the markup renders the
	 * image WITH the emoji behind it, and a missing upload is the page
	 * exactly as it was.
	 */
	$ic_page = (string) file_get_contents(__DIR__ . '/launchpad.php');

	/*
	 * THE MAP AND THE LIST DRIFT APART, in both directions, and across
	 * all three sections now -- Play, Collect and Progress each came off
	 * their own sheet.
	 */
	$ic_play = array(); $ic_names = array(); $ic_miss = array();
	foreach ($lp_sections as $ic_sec => $ic_d) {
		foreach ($ic_d['items'] as $ic_it) {
			$ic_play[] = $ic_it;
			$ic_names[] = $ic_it[2];
			if (!isset($lp_icons[$ic_it[2]])) $ic_miss[] = $ic_sec . '/' . $ic_it[2];
		}
	}
	ok(count($ic_miss) === 0, 'these items have no icon: ' . implode(', ', $ic_miss));
	$ic_orphan = array_diff(array_keys($lp_icons), $ic_names);
	ok(count($ic_orphan) === 0,
	   'these icons are mapped to nothing: ' . implode(', ', $ic_orphan));
	/* The path carries its own sheet's folder, so two sections cannot
	   collide on a shared slug. */
	$ic_unpref = array();
	foreach ($lp_icons as $ic_k => $ic_v) {
		if (!is_string($ic_v) || !preg_match('~^(play|collect|progress)/[a-z0-9-]+$~', $ic_v)) {
			$ic_unpref[] = $ic_k;
		}
	}
	ok(count($ic_unpref) === 0,
	   'these icon paths are not section-prefixed: ' . implode(', ', $ic_unpref));
	$ic_files = array();
	foreach ($lp_icons as $ic_k => $ic_v) $ic_files[] = $ic_v;
	ok(count(array_unique($ic_files)) === count($ic_files),
	   'two Play items share one icon file');
	/*
	 * THE ACCENT COMES OUT OF THE ART. Every entry carries one, measured
	 * from the image's dominant lit hue -- so a tile whose art has not
	 * been uploaded still gets its colour and the section does not go
	 * half-coloured while the FTP catches up.
	 */
	/*
	 * A TILE WEARS ITS SECTION'S ACCENT. Per-tile colours made the grid a
	 * swatch book; the three section accents carry the identity instead.
	 */
	ok(preg_match('~--lp-accent[^;]*\$lp_acc~', $ic_page) !== 1,
	   'a tile sets an accent of its own again, so the section loses its colour');
	/*
	 * AND THE THREE ARE SEPARATED, because they have to read as three
	 * sections rather than three shades of the same one. 60 degrees is
	 * what this art allows: Collect and Progress are both violet-dominant,
	 * so Collect takes the magenta that is genuinely in its Store and
	 * Raffle art rather than a fourth violet.
	 */
	$ic_hue = function ($hex) {
		$r = hexdec(substr($hex,1,2))/255; $g = hexdec(substr($hex,3,2))/255; $b = hexdec(substr($hex,5,2))/255;
		$mx = max($r,$g,$b); $mn = min($r,$g,$b); $d = $mx-$mn;
		if ($d == 0) return 0;
		if ($mx == $r) $h = fmod((($g-$b)/$d), 6);
		elseif ($mx == $g) $h = (($b-$r)/$d) + 2;
		else $h = (($r-$g)/$d) + 4;
		return fmod($h*60 + 360, 360);
	};
	$ic_accs = array();
	foreach ($lp_sections as $ic_n => $ic_d) {
		ok(preg_match('~^#[0-9a-f]{6}$~i', $ic_d['accent']) === 1, "$ic_n has no hex accent");
		$ic_accs[$ic_n] = $ic_hue($ic_d['accent']);
	}
	$ic_keys = array_keys($ic_accs); $ic_minsep = 360;
	for ($a = 0; $a < count($ic_keys); $a++) {
		for ($b2 = $a+1; $b2 < count($ic_keys); $b2++) {
			$dd = abs($ic_accs[$ic_keys[$a]] - $ic_accs[$ic_keys[$b2]]);
			$ic_minsep = min($ic_minsep, min($dd, 360-$dd));
		}
	}
	ok($ic_minsep >= 50,
	   'the section accents are only ' . round($ic_minsep) . ' degrees apart; they will read as one colour');

	/* EVERY ITEM KEEPS ITS EMOJI, because that is the fallback. */
	$ic_noemoji = array();
	foreach ($ic_play as $ic_it) if (trim((string) $ic_it[1]) === '') $ic_noemoji[] = $ic_it[2];
	ok(count($ic_noemoji) === 0,
	   'these items lost their emoji, so a missing upload leaves them blank: ' . implode(', ', $ic_noemoji));

	/* THE SHAPE OF THE CATALOGUE IS A CONTRACT. Index 4 already means
	   "requirement", so the icon is a separate map rather than a sixth
	   element every ungated entry would need a placeholder for. */
	$ic_cat = (string) file_get_contents(__DIR__ . '/lib/launchpad-catalogue.php');
	ok(preg_match('~\$lp_icons\s*=\s*array\(~', $ic_cat) === 1,
	   'the icons are not a map of their own any more');
	foreach ($ic_play as $ic_it) {
		ok(count($ic_it) <= 5,
		   $ic_it[2] . ' has a sixth element; readers that use index 0 to 3 are a documented contract');
	}

	/* THE FALLBACK IS REAL, not a comment about one. */
	ok(strpos($ic_page, 'data-emoji="') !== false,
	   'the markup does not carry the emoji, so there is nothing to fall back to');
	ok(strpos($ic_page, 'this.parentNode.textContent=this.parentNode.dataset.emoji') !== false,
	   'a broken icon stays broken instead of showing the emoji it replaced');
	ok(preg_match('~src="icons/<\?php echo \$lp_ico;~', $ic_page) === 1,
	   'the markup hardcodes a section folder instead of using the mapped path');
	ok(strpos($ic_page, 'src="https://') === false,
	   'the icons are referenced absolutely; cookies here are host-only, so paths stay relative');

	/* The emoji was a text node and inherited the centring flexbox.css
	   puts on a bare `a`, which .lp-tile is. An image does not. */
	/*
	 * THE ART IS THE PANEL. Square because the source is -- these were
	 * cropped square out of a 5x4 sheet, and a wide short tile would cut
	 * the subject in half.
	 */
	ok(preg_match('~\.lp-tile\.lp-tile-art-on \{[^}]*aspect-ratio:\s*1~s', $ic_page) === 1,
	   'the art tiles are not square, so the subject is cropped out of its own panel');
	ok(preg_match('~\.lp-tile-art \{[^}]*object-fit:\s*cover~s', $ic_page) === 1,
	   'the art is not cover-fitted, so it distorts or letterboxes');
	ok(preg_match('~\.lp-tile\.lp-tile-art-on \{[^}]*overflow:\s*hidden~s', $ic_page) === 1,
	   'the art is not clipped to the panel');
	ok(preg_match('~\.lp-tile\.lp-tile-art-on \{[^}]*border-radius:\s*0~s', $ic_page) === 1,
	   'the art tile keeps the old rounded corner; the sweep cannot reach this one, its colour is not in the list');
	/* Words on a photograph need their own contrast. */
	ok(preg_match('~\.lp-tile-art-on::after \{[^}]*linear-gradient~s', $ic_page) === 1,
	   'there is no scrim, so the name and description sit raw on the art');
	ok(preg_match('~\.lp-tile-art-on::after \{[^}]*pointer-events:\s*none~s', $ic_page) === 1,
	   'the scrim does not pass clicks through, so the tile cannot be tapped');
	ok(strpos($ic_page, 'prefers-reduced-motion') !== false,
	   'the art hover animates regardless of reduced motion');
	/* A tile whose art fails must stop pretending to be an art tile. */
	ok(strpos($ic_page, "classList.remove('lp-tile-art-on')") !== false,
	   'a broken image leaves the tile square and empty instead of falling back to the old shape');
}


echo "\nthe page is as wide as the menu, and every tile is one size\n";
{
	$wd = (string) file_get_contents(__DIR__ . '/launchpad.php');

	/*
	 * TWO CAPS, NOT ONE. Removing this page's own 1100px max-width was
	 * not enough: flexbox.css puts a SECOND cap of 900px on .col1of3,
	 * which is why the navbar measured 2000px and the tiles 900 -- the
	 * column was centred inside a container twice its width. That rule
	 * is platform-wide and other pages want it, so it is overridden
	 * inline here rather than changed there.
	 */
	ok(preg_match('~class="col1of3"[^>]*max-width:none~', $wd) === 1,
	   'the column does not clear flexbox.css\'s 900px cap, so the page is half the width of its own menu');
	ok(preg_match('~\\.col1of3\\s*\\{[^}]*max-width:\\s*900px~s',
	   (string) file_get_contents(__DIR__ . '/dist/flexbox.css')) === 1,
	   'flexbox.css no longer caps .col1of3, so the inline override above is dead weight and should go');

	/*
	 * auto-FILL, NOT auto-fit. auto-fit collapses the tracks an item does
	 * not occupy, so a section with fewer items than columns stretches
	 * the ones it has -- Progress's eight tiles came out 235px wide
	 * against Play's 207, three sections and three tile sizes. It matters
	 * more now the tiles are square, because a wider tile is a taller one.
	 */
	ok(preg_match('~\\.lp-grid\\s*\\{[^}]*repeat\\(auto-fill,~s', $wd) === 1,
	   'the grid uses auto-fit, so a section with fewer items than columns gets wider tiles than the rest');

	/*
	 * AN EMOJI HAS NO AGREED WIDTH. This is the state every tile is in
	 * until the art is uploaded, and a stadium glyph is wider than a
	 * skull -- reported as "mobile icons are a mess, all varying sizes
	 * in width".
	 */
	ok(preg_match('~\\.lp-tile-icon\\s*\\{[^}]*width:\\s*\\d+px~s', $wd) === 1,
	   'the emoji has no box, so each tile sizes its icon to whatever glyph it happens to carry');
	ok(preg_match('~\\.lp-tile-icon\\s*\\{[^}]*justify-content:\\s*center~s', $wd) === 1,
	   'the emoji is not centred in its box');

	/*
	 * THE DAILY REWARD SHOWS THE CHEST. It is the row we most want
	 * pressed and the only Start Here entry with art of its own at the
	 * other end of the link.
	 */
	ok(preg_match("~'missions\\.php\\#ms-sec-daily'[^;]*images/chest/closed\\.webp~s", $wd) === 1,
	   'the daily reward row no longer carries the chest');
	/* [\s\S]{0,200}? and NOT [^>]*: there is a <?php ... ?> between the
	   class and the attribute, and `?>` carries the > that ends the
	   window. Fourth time in this session. Bound the search by what must
	   come after, never by what must not appear in between. */
	ok(preg_match('~class="lp-todo-pic"[\s\S]{0,200}?onerror~', $wd) === 1,
	   'the chest has no onerror, so a missing file leaves a broken frame in Start Here');
	ok(preg_match('~\\.lp-todo-art\\s*\\{[^}]*display:\\s*flex~s', $wd) === 1,
	   'the row with art is not laid out as a row, so the chest sits above the text');
	/* Only the row that HAS art becomes a flex row; the others are
	   untouched, which is what the separate class is for. */
	ok(preg_match('~isset\\(\\$t\\[3\\]\\) \\? \x27 lp-todo-art\x27~', $wd) === 1,
	   'every Start Here row is treated as if it had art');
}


echo "\nthe fallback's box does not outlive the fallback\n";
{
	$fx = (string) file_get_contents(__DIR__ . '/launchpad.php');
	/*
	 * THE BUG THIS EXISTS FOR. The 44px square is the EMOJI's box, added
	 * so a row of glyphs with different advance widths would line up.
	 * inset:0 does NOT override an explicit width and height, so once the
	 * art loaded every panel became a 44px picture pinned to the top left
	 * of its tile. Reported as "you made everything a tiny icon in the
	 * left corner".
	 */
	ok(preg_match('~\\.lp-tile-art-on \\.lp-tile-icon \\{[^}]*width:\\s*auto~s', $fx) === 1,
	   "the art container keeps the emoji's fixed width, so every tile is a 44px icon in the corner");
	ok(preg_match('~\\.lp-tile-art-on \\.lp-tile-icon \\{[^}]*height:\\s*auto~s', $fx) === 1,
	   "the art container keeps the emoji's fixed height");
	/* Belt and braces on the pair that caused it: if the emoji box ever
	   loses its size the icons go ragged again, and if the art rule ever
	   loses its reset they go tiny again. Both or neither. */
	ok(preg_match('~\\.lp-tile-icon \\{[^}]*width:\\s*44px~s', $fx) === 1,
	   'the emoji lost its box, so unuploaded tiles go ragged again');

	echo "\nthe banner reads at full width\n";
	/*
	 * At 2000px the band is nearly six times as wide as it is tall, and
	 * the arrangement built for a 900px column stopped working: the
	 * welcome sat low in a tall band and Start Here ran the entire width
	 * as one long empty box.
	 */
	ok(preg_match('~\\.lp-banner-in \\{[^}]*justify-content:\\s*flex-start~s', $fx) === 1,
	   'the welcome sits at the bottom of the band again');
	/*
	 * AND THE SCRIM HAS TO FOLLOW THE TEXT. The art is brightest along
	 * its TOP edge (mean luminance 66, against 14 at the bottom), so a
	 * bottom-weighted scrim does nothing for words placed at the top.
	 * The left third is dark rock and sky, so the weight goes there.
	 */
	/*
	 * AND IT COVERS THE TEXT, NOT THE BAND. The scrim used to be two
	 * full-width layers that put 0.4 to 0.5 of flat navy over the whole
	 * banner, which read as a haze with a visible step where it met the
	 * same picture carrying on below. It is a corner now: anchored at
	 * the top left where the name and stats are, and gone before it
	 * reaches an edge you can see.
	 */
	ok(preg_match('~\\.lp-banner::before \\{[^}]*radial-gradient\\([^)]*at 0% 0%~s', $fx) === 1,
	   'the scrim is not anchored on the text, so it is darkening the backdrop in one rectangle again');
	ok(preg_match('~\\.lp-banner::before \\{[^}]*rgba\\(7,17,29,0\\.00\\) 75%\\)~s', $fx) === 1,
	   'the scrim does not reach zero inside the band, so it ends on an edge');
	ok(preg_match('~\\.lp-banner \\.lp-todo \\{[^}]*max-width:\\s*\\d+px~s', $fx) === 1,
	   'Start Here spans the whole band again, which at full width is a mile of empty box');

	echo "\nthe phone gets more than one tile per row\n";
	/*
	 * minmax(190px) fits exactly one column in the ~350px a phone leaves,
	 * so every tile became a 350px square and 34 of them is a 13,000px
	 * page. 140px gives two across, and the page drops to a third.
	 */
	/*
	 * BOUNDED BY WHAT COMES AFTER, NOT BY A CHARACTER COUNT. This was a
	 * 1400-character window, and adding a paragraph of comment inside
	 * the block pushed the .lp-grid rule out of it -- a rule that had
	 * not moved, failing because prose above it grew. The block ends at
	 * the only unindented closing brace that follows it.
	 */
	$fx_mb = preg_match('~@media \(max-width: 700px\) \{.*?\n\}~s', $fx, $fx_mm) ? $fx_mm[0] : '';
	ok($fx_mb !== '', 'the phone block is gone');
	ok(preg_match('~\\.lp-grid \\{[^}]*minmax\\(140px~s', $fx_mb) === 1,
	   'the phone grid still asks for 190px columns, so a phone gets one tile per row');
	/*
	 * A DESCRIPTION MUST NEVER WRAP, at any width.
	 *
	 * An art tile is bottom-anchored, so a second line lifts that tile's
	 * title above its neighbours' and the row comes out staggered. A
	 * clamp at two lines does NOT fix that: it caps the damage, it does
	 * not level the titles, because a one-line description still sits
	 * its title lower than a two-line one.
	 *
	 * Shrinking the font is not a fix either. Measured over all 34, two
	 * wrap at 194px and still wrap three sizes down, because the cause
	 * is a long word run rather than the size.
	 */
	ok(preg_match('~\.lp-tile-art-on \.lp-tile-desc \{[^}]*white-space:\s*nowrap~s', $fx) === 1,
	   'a description can wrap, which lifts its own title above its neighbours and staggers the row');
	/*
	 * AND NOTHING IS ALLOWED TO NEED CUTTING.
	 *
	 * nowrap keeps the titles level, but on its own it would silently
	 * clip a long description instead. The rule is that the copy fits:
	 * measured in a browser at both real tile widths (207px desktop,
	 * 171px phone) with the shipped sizes, 36 characters is the longest
	 * that fits with room to spare and 38 is where it starts to bite.
	 * Two descriptions were over and were rewritten, not truncated.
	 *
	 * A character count is a proxy for a pixel width, and a crude one --
	 * but it is checkable without a browser, and it fails in the right
	 * direction: somebody adding a long description is told to shorten
	 * it rather than discovering a clipped word in production.
	 */
	$fx_long = array();
	foreach ($lp_sections as $fx_s => $fx_d) {
		foreach ($fx_d['items'] as $fx_it) {
			if (strlen($fx_it[3]) > 38) $fx_long[] = $fx_it[2] . ' (' . strlen($fx_it[3]) . ')';
		}
	}
	ok(count($fx_long) === 0,
	   'these descriptions are too long to fit one line and will be clipped: ' . implode(', ', $fx_long));
	ok(strpos($fx, 'text-overflow: ellipsis') === false,
	   'an ellipsis is back; the copy is meant to fit, not be cut');
	/* -webkit-box would hand the second line straight back. */
	ok(strpos($fx_mb, '-webkit-line-clamp') === false,
	   'the phone block clamps to two lines again, which overrides nowrap and re-staggers the titles');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
