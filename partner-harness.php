<?php
/* partner-harness.php - CLI only. No database, no network.
 *
 * The invitation page.
 *
 * WHAT GOES WRONG ON A MARKETING PAGE is not a crash. It is a promise the
 * platform does not keep: a currency rule that has since changed, an art
 * list that is short by a third, a chain we do not actually support. The
 * page is sent to people deciding whether to trust us, and nobody notices a
 * stale number on it for months.
 *
 * So every figure on the page is asserted against THE CODE THAT ENFORCES IT,
 * not against a copy of it here. If admin_currency_problem() starts allowing
 * 20 characters, this fails until the page says 20.
 *
 * It also guards the two things that would quietly break the page itself:
 * the login gate (its whole audience is logged out) and the ?for= parameter,
 * which is reflected into the markup AND into the og:title.
 *
 * Usage: php partner-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
$src   = file_get_contents(__DIR__ . '/partner.php');
$clean = harness_strip_comments($src);

echo "it is public, because its whole audience is logged out\n";
{
	/*
	 * skulliance.php redirects anonymous visitors to error.php. An
	 * invitation behind the login gate is an invitation nobody invited can
	 * read, and the failure is invisible to whoever sends the link because
	 * THEY are signed in.
	 */
	ok(strpos($clean, "include 'skulliance.php'") === false
	   && strpos($clean, 'include "skulliance.php"') === false,
	   'partner.php includes the login gate; every invitee would be redirected to error.php');
	ok(strpos($clean, "include 'verify.php'") === false,
	   'partner.php includes verify.php, the heavy nightly machinery, on a marketing page');
	/* And a stranger needs a way out, which header.php does not give them. */
	ok(strpos($clean, 'site-header.php') !== false,
	   'an anonymous visitor gets no navigation at all; header.php\'s navbar is inside if(isset($name))');
	ok(strpos($clean, '$pt_public_nav = !isset($name)') !== false,
	   'the public nav is rendered for members too, stacking two menu bars');
	/* Session restore must MERGE. */
	ok(strpos($clean, '$_SESSION = array_merge((array)$_SESSION, $cookie)') !== false,
	   'the SessionCookie restore raw-assigns, which can wipe another page\'s session state');
}

echo "\nthe numbers come from the code that enforces them\n";
{
	require_once __DIR__ . '/admin-lib.php';
	require_once __DIR__ . '/monstrocity-themes-lib.php';

	/*
	 * THE CURRENCY RULE. admin_currency_problem() is what actually refuses a
	 * bad name, so the page's "2 to 16 letters or digits" is checked by
	 * RUNNING it rather than by reading the sentence: the boundary is the
	 * claim, and a page that says 16 while the validator says 12 sends
	 * somebody away to make art for a name we will reject.
	 */
	ok(admin_currency_problem('ab') === '',  'a 2-character currency is refused, but the page promises 2');
	ok(admin_currency_problem('a') !== '',   'a 1-character currency is allowed; the page says the minimum is 2');
	ok(admin_currency_problem(str_repeat('a', 16)) === '',
	   'a 16-character currency is refused, but the page promises 16');
	ok(admin_currency_problem(str_repeat('a', 17)) !== '',
	   'a 17-character currency is allowed; the page says the maximum is 16');
	ok(admin_currency_problem('ab cd') !== '', 'a currency with a space is allowed; the page says it cannot have one');
	ok(preg_match('/2 to 16 letters or digits/', $clean) === 1,
	   'the page no longer states the currency rule the validator enforces');

	/* THE ICON PATH, which is why the name cannot have a space. */
	ok(admin_currency_icon('DREAD') === 'icons/dread.png',
	   'the icon path changed; the page explains the filename rule on the old one');

	/*
	 * THE MONSTROCITY ART LIST is the biggest ask on the page and the
	 * easiest to get wrong from memory. Computed, not typed.
	 */
	$chars = count(require __DIR__ . '/monstrocity-default-characters.php');
	$sets  = count(monstrocity_theme_subdirs());
	$total = $chars * $sets;
	ok(strpos($clean, '$pt_mon_total = $pt_mon_chars * count($pt_mon_sets)') !== false,
	   'the Monstrocity image count is hardcoded instead of derived from the roster and the states');
	ok(strpos($clean, (string)$total) !== false || strpos($clean, '$pt_mon_total') !== false,
	   'the page does not state how many character images a theme needs');
	/* A hardcoded 28 or 42 anywhere in the prose would drift silently. */
	ok(!preg_match('/\b(28|42) (character )?images\b/', $clean),
	   'the art list has a hardcoded image count in the copy; it must come from $pt_mon_total');

	/* THE CHAINS, from the file that validates a policy per chain. */
	$adm = file_get_contents(__DIR__ . '/admin-collections.php');
	preg_match("/\\\$CHAINS = array\(([^)]*)\)/", $adm, $cm);
	ok(!empty($cm[1]), 'admin-collections.php no longer declares $CHAINS');
	if (!empty($cm[1])) {
		preg_match_all("/'([A-Za-z]+)'/", $cm[1], $names);
		foreach ($names[1] as $chain) {
			ok(strpos($clean, $chain) !== false,
			   "the platform supports $chain and the invitation does not mention it");
		}
		ok(substr_count($clean, '$pt_chains') >= 2,
		   'the chain list is written out in the markup rather than held in one variable');
	}
}

echo "\n?for= is reflected into the page AND the link preview\n";
{
	/*
	 * It lands in the <h1>, the <title>, the og:title and the canonical URL.
	 * Escaping alone is not enough for a value that goes into a meta
	 * attribute and a URL, so it is sanitised to a character class first and
	 * length-capped -- a 400-character "name" would wreck the hero and the
	 * card both.
	 */
	/* strpos, not a regex: the character class in the shipped line contains an
	   escaped quote, and [^']* in the pattern stopped dead on it -- the
	   assertion failed against correct code, which is the worst kind. */
	ok(strpos($clean, "preg_replace('/[^A-Za-z0-9 ") !== false,
	   'the ?for= value is not reduced to a safe character class before being reflected');
	ok(strpos($clean, 'mb_substr($pt_for, 0, 48)') !== false,
	   'the ?for= value is not length-capped; a long one wrecks the hero and the og:title');
	ok(strpos($clean, 'rawurlencode($pt_for)') !== false,
	   'the canonical URL interpolates the name without encoding it');
	/* Everything still has to read correctly with no parameter. */
	ok(substr_count($clean, "\$pt_for !== ''") >= 3,
	   'the page does not branch on the name being absent; a plain visit would read oddly');
	ok(strpos($clean, "\$pt_who   = \$pt_for !== '' ? \$pt_for : 'your project'") !== false,
	   'there is no fallback for the name in the body copy');
	/* Reflected values are escaped at every output site. */
	ok(substr_count($clean, '$pt_e($pt_for)') >= 2, 'the name is echoed unescaped somewhere');
}

echo "\nthe link preview carries the name and real art\n";
{
	/*
	 * This page is SENT IN A DM, so the unfurled card is the first thing the
	 * invitee sees and often the only thing they read before deciding to
	 * click. Two things have to be true of it.
	 */
	ok(strpos($clean, 'name="twitter:card" content="summary_large_image"') !== false,
	   'the card is a thumbnail, not a large image');
	/* The art must FIT that card. summary_large_image is ~1.91:1 and X crops
	   anything else; skulliancelogo.png is 700x300 (2.33) and letterboxed. */
	ok(strpos($clean, 'skulliancelogo.png') === false,
	   'the card is back to the 700x300 wordmark, which X letterboxes');
	ok(strpos($clean, 'skulliancebackground.png') !== false,
	   'the card has no fallback art');
	/* A 4.5MB PNG is a slow unfurl and some crawlers skip a large image, so
	   a resized copy wins automatically once it is uploaded -- and the
	   fallback exists so the card is never pointed at a 404. */
	ok(strpos($clean, "is_file(\$pt_card_opt)") !== false,
	   'the optimised card is not preferred when present, or there is no fallback');
	ok(strpos($clean, 'images/partner-card.jpg') !== false,
	   'the optimised card path is gone, so uploading one would change nothing');
	ok(strpos($clean, 'og:image:width') !== false && strpos($clean, 'og:image:height') !== false,
	   'the card declares no dimensions; a crawler that does not fetch the image cannot size it');
	/* And the NAME has to reach the preview, which is the whole point of
	   personalising the link. There is no TTF in this repo so it cannot be
	   drawn into the image -- it goes in the title and the description, both
	   of which X and Discord render. */
	ok(strpos($clean, '$pt_e($pt_title)') !== false
	   && substr_count($clean, 'og:title') >= 1,
	   'the og:title is not the personalised title');
	ok(strpos($clean, "\$pt_title = \$pt_for !== ''") !== false,
	   'the title does not vary with ?for=, so every invitation previews identically');
	ok(strpos($clean, "\$pt_desc  = (\$pt_for !== ''") !== false,
	   'the description does not carry the name');
	ok(strpos($clean, 'og:image:alt') !== false, 'the card art has no alt text');
}

echo "\nthe roster and the marquee are live, not a hardcoded list\n";
{
	/*
	 * The crowd is the most persuasive thing on the page and the fastest to
	 * go stale. A hardcoded list starts lying the first time a project joins
	 * or leaves, and nobody re-reads a marketing page.
	 */
	ok(strpos($clean, 'FROM projects') !== false,
	   'the project list is hardcoded; it will be wrong within a month');
	ok(strpos($clean, 'count($pt_names)') !== false,
	   'the page states a project count that is not counted');
	/* A row of fallback skulls argues the opposite of the intended point. */
	ok(strpos($clean, 'is_file(__DIR__ . \'/\' . $file)') !== false,
	   'the marquee shows projects whose icon is missing, rendering a row of broken images');
	/* The marquee is doubled for a seamless loop; the copy must not be read
	   out twice by a screen reader. */
	ok(strpos($clean, 'aria-hidden="true"') !== false,
	   'the duplicated marquee track is announced twice to screen readers');
	ok(strpos($clean, 'translateX(-50%)') !== false,
	   'the marquee no longer loops on the half-way point, so the duplicate is visible');
	/* And it must not animate for someone who asked it not to. */
	ok(preg_match('/prefers-reduced-motion:\s*reduce/', $clean) === 1,
	   'the marquee animates regardless of prefers-reduced-motion');
	/* With few projects the roster section would read as a thin crowd. */
	ok(strpos($clean, 'count($pt_names) >= 6') !== false,
	   'the roster renders however few projects there are');
}

echo "\nit says what Skulliance is before it asks for anything\n";
{
	/*
	 * The page was written for people who already know what this is, and
	 * most invitees do not. The origin section comes BEFORE the roster and
	 * the ask: a stranger reading "40 projects are already here" has no
	 * idea what they are already here for.
	 */
	$what  = strpos($src, 'What this is');
	$who   = strpos($src, 'Who else was asked');
	$asked = strpos($src, 'Everything we need from you');
	ok($what !== false, 'the page no longer says what Skulliance is');
	ok($what !== false && $who !== false && $what < $who,
	   'the origin section sits after the roster; a stranger is shown a crowd before being told '
	 . 'what they are a crowd for');
	ok($what !== false && $asked !== false && $what < $asked,
	   'the page asks for something before explaining what it is');
	/* The claims in it are the ones the Skull Paper already makes. */
	ok(strpos($src, 'Oculus Orbus') !== false, 'the founder is not named');
	ok(strpos($src, 'skull art on Cardano') !== false,
	   'the origin no longer says what the founding artists have in common');
	/* And it must give the REASON it keeps growing, which is the argument
	   this section exists to make -- not just a history. */
	ok(stripos($src, 'cannot justify building') !== false,
	   'the origin recounts history without saying why the platform keeps growing, which is the '
	 . 'only part of it an invitee needs');
}

echo "\npoints are described as points, and as off chain\n";
{
	/*
	 * WHY THIS IS ASSERTED AND NOT JUST WRITTEN CAREFULLY ONCE.
	 *
	 * An artist reading "your own currency" hears "you want me to launch a
	 * token": a contract, a listing, a price chart, speculators, and a
	 * regulatory question nobody wants. The page lost people on the word
	 * alone. "Currency" is also the DATABASE COLUMN and half the function
	 * names around it, so it is the single most likely word to be typed
	 * back into the copy by someone working on the query right above it.
	 *
	 * So the check is on the MARKUP with the PHP stripped out: the column,
	 * admin_currency_icon() and the comments explaining all this are fine
	 * and must not trip it.
	 */
	$body = substr($src, strpos($src, '<div id="pt">'));
	$visible = preg_replace('/<\?php.*?\?>/s', ' ', $body);
	$visible = preg_replace('!/\*.*?\*/!s', ' ', $visible);
	ok(stripos($visible, 'currenc') === false,
	   'the word "currency" is back in the copy; it reads as a token launch, which is the '
	 . 'single thing this page has to rule out');

	/* And the reassurance itself has to be there, in the section about
	   points rather than buried at the end. */
	ok(strpos($visible, 'Points are not a token') !== false,
	   'the off-chain statement is gone; "points" alone does not answer the worry');
	/*
	 * SCOPED TO THE BLOCK, not the page. "no contract" matched "No
	 * contracts, no servers, no mod work" four sections away, so deleting
	 * the sentence that actually reassures anybody passed. A phrase that
	 * appears somewhere on a long page proves nothing about where it is.
	 */
	$pa = strpos($visible, 'pt-plain');
	$pb = $pa === false ? false : strpos($visible, '</div>', $pa);
	$block = ($pa === false || $pb === false) ? '' : substr($visible, $pa, $pb - $pa);
	ok($block !== '', 'the off-chain block is gone from the markup');
	foreach (array('off chain' => 'that points are off chain',
	               'no contract' => 'that there is no contract',
	               'traded' => 'that points cannot be traded',
	               'no value off the platform' => 'that points are worth nothing elsewhere') as $needle => $what) {
		ok(stripos($block, $needle) !== false, 'the off-chain block no longer says ' . $what);
	}
	/* It must come BEFORE the spec box: somebody who is worried stops
	   reading at the worry, not after the filename rules. */
	$plain = strpos($visible, 'Points are not a token');
	$spec  = strpos($visible, 'What to send');
	ok($plain !== false && $spec !== false && $plain < $spec,
	   'the reassurance sits after the art spec, which is after the point somebody worried '
	 . 'about tokens has stopped reading');
	/* The closing ask repeats it, because that is the last thing read. */
	ok(stripos($visible, 'no token') !== false,
	   'the closing list no longer rules out a token');
}

echo "\nthe claims match the Skull Paper\n";
{
	/* The store flow is a promise about what WE do, so the address it names
	   has to be the one in the brief and in the docs. */
	ok(strpos($clean, 'diamondskulls') !== false,
	   'the store section does not say where to send reward NFTs');
	/* Twelve games are listed in the Skull Paper nav; the page claims "a
	   dozen". If one is added or retired, this fails. */
	$sp = file_get_contents(__DIR__ . '/skullpaper.php');
	preg_match_all("/'slug' => 'games-[a-z-]+'/", $sp, $gm);
	$games = count($gm[0]);
	ok($games === substr_count($clean, '<li><i>&#'),
	   'the page lists ' . substr_count($clean, '<li><i>&#') . ' games, the Skull Paper lists ' . $games);
	/* And the page must point at the documentation rather than restating it. */
	ok(strpos($clean, 'skullpaper.php') !== false,
	   'the page does not link to the Skull Paper for anyone who wants the detail');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
