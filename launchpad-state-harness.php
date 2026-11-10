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


echo "\nthe hero banner degrades, and never blocks the links on it\n";
{
	$bn = (string) file_get_contents(__DIR__ . '/launchpad.php');
	/*
	 * IMAGES DEPLOY BY FTP, NOT WITH THE CODE. Until launchpad-hero.jpg
	 * is on the server this has to be exactly the page that was there
	 * before: dark ground, no broken frame, nothing moved. The art is an
	 * improvement layered on, never a dependency.
	 */
	ok(preg_match('~\.lp-banner\s*\{[^}]*background-color:\s*#07111d~s', $bn) === 1,
	   'the banner has no colour under the art, so a missing upload is a blank or broken band');
	$bnPos = strpos($bn, 'background-color: #07111d');
	$bnImg = strpos($bn, 'background-image: url(images/launchpad-hero.jpg)');
	ok($bnImg !== false && $bnPos !== false && $bnPos < $bnImg,
	   'the fallback colour is declared after the image, so it can override it');
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

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
