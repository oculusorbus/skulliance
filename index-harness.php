<?php
/* index-harness.php - CLI only. No database, no network.
 *
 * The staking root: the page every invite lands on.
 *
 * IT IS THE LAST STEP OF A FUNNEL NOBODY MEASURES. A poster is drawn, a
 * post is written, three traits are promised on each side, and all of it
 * delivers a stranger to this one file. For most of its life this file was
 * fourteen lines of static HTML with a single Discord button, and it did
 * not even capture the referral code -- so the invite paid nobody and
 * nothing anywhere said so.
 *
 * WHAT IS CHECKED HERE IS WHAT FAILS SILENTLY:
 *   - the capture running after the first byte (setcookie returns quietly)
 *   - the page reaching for db.php, which is the one include that makes a
 *     cold landing page slow for every crawler and every arrival
 *   - a marquee of 1-3MB partner flyers behind a 112px tile
 *   - loading="lazy" on a marquee, where tiles arrive by transform and so
 *     never load at all
 *   - a grid track wider than a phone
 *
 * Each case runs in its OWN php process, so one render cannot leak state
 * into the next and nothing has to care whether index.php is re-includable.
 *
 * Usage: php index-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
$src = file_get_contents(__DIR__ . '/index.php');
$raw = $src;
$src = harness_strip_comments($src);

/** Render index.php in a fresh process with $_GET set, and return the html. */
function render($get = array()) {
	$boot = '$_GET = ' . var_export($get, true) . '; include ' . var_export(__DIR__ . '/index.php', true) . ';';
	$tmp  = sys_get_temp_dir() . '/ix-boot-' . getmypid() . '-' . mt_rand() . '.php';
	file_put_contents($tmp, "<?php\n" . $boot . "\n");
	$out = (string) @shell_exec('php ' . escapeshellarg($tmp) . ' 2>&1');
	@unlink($tmp);
	return $out;
}

/* The stat cache homepage-data.php reads. Seeded so the figures path is
   exercised without a database, and cleared to prove the other branch. */
function hp_seed($vals) {
	foreach ($vals as $k => $v) @file_put_contents(sys_get_temp_dir() . '/hp-stat-' . $k . '.txt', (string) $v);
}
function hp_clear() {
	foreach (array('nfts_staked', 'artists', 'collections', 'chains', 'projecticons') as $k) {
		@unlink(sys_get_temp_dir() . '/hp-stat-' . $k . '.txt');
	}
}

echo "the page renders at all\n";
{
	hp_clear();
	$html = render();
	ok(strpos($html, '<!doctype html>') !== false, 'the page did not render: ' . substr($html, 0, 160));
	ok(stripos($html, 'Fatal error') === false && stripos($html, 'Warning:') === false
	   && stripos($html, 'Notice:') === false,
	   'php complained while rendering: ' . substr(strstr($html, 'rror') ?: $html, 0, 140));
	ok(substr_count($html, 'Login with Discord') >= 1, 'the call to action is gone');
}

echo "\nthe call to action is still the Discord button\n";
{
	$html = render();
	/* The OAuth url is not ours to tidy: the client id, the registered
	   redirect and the scope list all have to match what Discord has. */
	$oauth = 'https://discord.com/api/oauth2/authorize?client_id=1007909434141462588'
	       . '&permissions=268437504'
	       . '&redirect_uri=https%3A%2F%2Fskulliance.io%2Fstaking%2Fprocess-oauth.php'
	       . '&response_type=code&scope=identify%20guilds%20guilds.members.read';
	ok(strpos($html, htmlspecialchars($oauth, ENT_QUOTES, 'UTF-8')) !== false,
	   'the OAuth url changed, so the login button no longer works');
	/* Top and bottom: a long page with one button at the top asks a reader
	   who scrolled the whole thing to scroll back up again. */
	/* class="ix-cta", not ix-cta: the <style> block names .ix-cta four
	   times, so counting the bare class counts the stylesheet and the
	   assertion can never fail however many buttons the page has. */
	ok(substr_count($html, 'class="ix-cta"') >= 2,
	   'the call to action appears once, not at both ends');
	/* And nothing competes with it. The two free games are named in prose
	   as evidence, never rendered as a second button. */
	ok(substr_count($html, 'class="ix-cta"') === substr_count($html, 'Login with Discord'),
	   'something other than the Discord button is styled as the primary action');
}

echo "\nan invited arrival is told what is waiting\n";
{
	require_once __DIR__ . '/lib/referral-lib.php';
	$good = render(array('r' => ref_code(4821)));
	ok(strpos($good, 'You were invited') !== false, 'an invited arrival is not told they were invited');
	ok(strpos($good, (string) REF_ARRIVAL_DRAWS . ' DHC traits') !== false,
	   'the offer does not name the ' . REF_ARRIVAL_DRAWS . ' traits the code actually pays');
	/* The number comes from the constant. A 3 typed into the copy goes on
	   promising three the day REF_ARRIVAL_DRAWS becomes anything else. */
	ok(strpos($src, 'REF_ARRIVAL_DRAWS') !== false && strpos($src, 'REF_INVITER_DRAWS') !== false,
	   'the traits on offer are hardcoded in the copy rather than read from the constants');

	/*
	 * AND THE BUTTON COMES BEFORE THE PITCH FOR THEM. An invited arrival
	 * has already been sold by whoever sent them; making them read the
	 * paragraph first puts the thing they came to do below the fold on a
	 * phone. Everyone else gets the pitch first, because for them the
	 * button is a question nobody has asked yet.
	 */
	$pitch = strpos($good, 'Skulliance is a free staking platform');
	$cta   = strpos($good, 'class="ix-cta"');
	ok($cta !== false && $pitch !== false && $cta < $pitch,
	   'an invited arrival has to read the pitch before reaching the button');
	$cold  = render();
	$cpit  = strpos($cold, 'Skulliance is a free staking platform');
	$ccta  = strpos($cold, 'class="ix-cta"');
	ok($ccta !== false && $cpit !== false && $cpit < $ccta,
	   'a cold visitor is asked to sign in before being told what this is');
	/* Either way the note travels with the button rather than being
	   orphaned halfway up the page. */
	ok(substr_count($good, 'Signing in is Discord only') === 1,
	   'the sign-in note is printed ' . substr_count($good, 'Signing in is Discord only') . ' times');

	ok(strpos(render(), 'You were invited') === false,
	   'every visitor is told they were invited, including the ones who were not');
	ok(strpos(render(array('r' => 'zzzz')), 'You were invited') === false,
	   'a junk code still promises traits that will never arrive');
}

echo "\nthe figures are live, or they are not shown\n";
{
	hp_seed(array('nfts_staked' => 12418, 'artists' => 40, 'collections' => 96, 'chains' => 4));
	$html = render();
	ok(strpos($html, '12,418') !== false, 'the live figures are not rendered');
	ok(strpos($html, 'NFTs staked') !== false, 'the headline figure lost its label');

	/*
	 * ALL OR NOTHING. hp_stat_chains() falls back to 1 while every other
	 * figure falls back to 0, so an unreachable database renders exactly
	 * one tile reading "1 chain" -- which argues against the page it is on.
	 */
	hp_clear();
	$html = render();
	/* The MARKUP, not the stylesheet: the <style> block defines .ix-stats
	   unconditionally, so a bare strpos for the class matches its own CSS
	   and can never fail. */
	ok(strpos($html, '<ul class="ix-stats">') === false,
	   'with no figures available the page still prints a strip, which will read "1 chain"');
	ok(strpos($html, '>0<') === false && strpos($html, '0 NFTs staked') === false,
	   'a zero is printed as a figure');
	/* And the page is still a page without them. */
	ok(strpos($html, 'Login with Discord') !== false, 'losing the figures took the button with it');
}

echo "\nnothing here makes a cold landing page expensive\n";
{
	/*
	 * NO db.php. This is the most-hit cold page on the platform and a
	 * crawler's first stop; homepage-data.php exists precisely so a public
	 * page can have live figures without pulling in the whole platform.
	 */
	ok(preg_match("~(include|require)(_once)?\s*\(?\s*['\"]\.?/?db\.php~", $src) === 0,
	   'index.php includes db.php, which is the whole weight of the platform on the login page');
	ok(strpos($src, 'homepage-data.php') !== false, 'the cached figures are gone');

	/*
	 * THE FLYERS ARE NOT THE ASSET. 27 partner flyers are 42MB, 1-3MB each
	 * at 1500px, rendered here into 112px tiles. homepage.php pulled its
	 * own flyer wall for exactly this and left a note saying to bring it
	 * back only with resized sources. The currency icons are 6-8KB.
	 */
	ok(strpos($src, 'partner-flyers.php') === false,
	   'the partner flyers are back on the landing page: 42MB behind a 112px tile');
	ok(strpos($src, 'images/projects/') === false,
	   'the page loads full-size project flyers');
	ok(strpos($src, 'hp_project_icons') !== false, 'the project icon strip is gone');

	/*
	 * A MARQUEE CANNOT BE LAZY. Its tiles arrive by transform rather than
	 * by layout, so loading="lazy" leaves most of the track permanently
	 * blank -- which is affordable to avoid only because these are icons.
	 */
	if (preg_match('~<div class="ix-marq.*?</div>\s*</div>~s', $raw, $m)) {
		ok(strpos($m[0], 'loading="lazy"') === false,
		   'the marquee is lazy-loaded, so most of its tiles never appear');
	} else {
		ok(false, 'the marquee markup could not be found to check');
	}
	/* Everything below the fold that is NOT a marquee should be lazy. */
	ok(substr_count($src, 'loading="lazy"') >= 2,
	   'the screenshots are eager, so 5MB loads before anyone scrolls');
}

echo "\nit holds together on a phone\n";
{
	/*
	 * auto-fit still makes a track as wide as its own minimum when the
	 * container is NARROWER than that, so minmax(380px,1fr) is a 380px
	 * column inside a 360px phone and the whole page scrolls sideways.
	 * Measured: 400px of content in a 360px column before min() was added.
	 */
	ok(preg_match('~minmax\(\s*\d+px~', $src) === 0,
	   'a grid track has a fixed floor, so it will be wider than a phone: '
	 . (preg_match('~minmax\([^)]*\)~', $src, $mm) ? $mm[0] : ''));
	ok(substr_count($src, 'minmax(min(') >= 2, 'the grid floors do not use min(...,100%)');
	/* 100vw includes the scrollbar, which is how partner.php grew a
	   horizontal scrollbar with nothing wider than its own column. */
	ok(strpos($src, '100vw') === false, 'something is sized in 100vw, which includes the scrollbar');
	ok(strpos($src, 'prefers-reduced-motion') !== false, 'the marquee animates regardless of the setting');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
