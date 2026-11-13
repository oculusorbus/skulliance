<?php
/* polish-harness.php — CLI only. Static. No database.
 *
 * THE GRADIENT LAYER IS A TRIAL, SO WHAT THIS TESTS IS THE REVERT.
 *
 * Two files carry it. dist/polish.css holds the tokens and every
 * platform surface, linked once from header.php. missions-polish.php
 * holds the .ms- rules, and has to be separate because missions.php's
 * stylesheet is INLINE and therefore later in the document than any
 * <link> -- it would outrank the platform file.
 *
 * Everything worth asserting is about keeping that arrangement honest:
 * two one-line reverts, nothing merged into flexbox.css, no selector
 * reaching further than the rule it shadows, no !important anywhere, and
 * -- the one that matters -- no colour invented that the platform had
 * not already chosen.
 *
 * It does NOT assert what the gradients look like. That is the question
 * the trial is asking, and a harness that answered it would be asserting
 * my own taste back at me.
 */
$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$strip = function ($s) {
	$s = preg_replace('~/\*.*?\*/~s', '', $s);
	return preg_replace('~^\s*//.*$~m', '', $s);
};
$plat = (string) file_get_contents(__DIR__ . '/dist/polish.css');
$pol  = (string) file_get_contents(__DIR__ . '/missions-polish.php');
$flex = (string) file_get_contents(__DIR__ . '/dist/flexbox.css');
$page = (string) file_get_contents(__DIR__ . '/missions.php');
$head = (string) file_get_contents(__DIR__ . '/header.php');

$platCss  = preg_replace('~/\*.*?\*/~s', '', $plat);
$flexCss  = preg_replace('~/\*.*?\*/~s', '', $flex);
$polCode  = $strip($pol);
$pageCode = $strip($page);
$headCode = $strip($head);
/* The .ms- layer's rules only, comments gone: the prose in these files
   describes the behaviour closely enough to satisfy a plain search on
   its own, which is how an earlier version passed with code deleted. */
$polCss = substr($pol, strpos($pol, '<style>') + 7);
$polCss = preg_replace('~/\*.*?\*/~s', '', substr($polCss, 0, strpos($polCss, '</style>')));

/* Both layers, for the checks that apply to the whole trial. */
$both = array('dist/polish.css' => $platCss, 'missions-polish.php' => $polCss);

echo "each layer is one line to revert\n";
ok(substr_count($headCode, 'dist/polish.css') === 1,
   'header.php links the platform layer more than once, or not at all');
ok(substr_count($pageCode, "include __DIR__ . '/missions-polish.php'") === 1,
   'missions.php includes the page layer more than once, or not at all');
/* A second caller is a second thing to unpick, which is the whole
   failure these guard. */
foreach (array('dist/polish.css' => array('header.php'),
               'missions-polish' => array('missions.php', 'missions-polish.php', 'polish-harness.php')) as $needle => $allowed) {
	$extra = array();
	foreach (array_merge(glob(__DIR__ . '/*.php'), glob(__DIR__ . '/ajax/*.php')) as $f) {
		$b = basename($f);
		if (in_array($b, $allowed, true) || $b === 'polish-harness.php') continue;
		/* COMMENT-STRIPPED: each layer's header comment names the other
		   one, so a raw search reported the prose as a second caller. */
		if (strpos($strip((string) file_get_contents($f)), $needle) !== false) $extra[] = $b;
	}
	ok(count($extra) === 0,
	   "$needle is also reached from " . implode(', ', $extra) . ', so one line is not the revert');
}
/* And nothing may have been merged into the stylesheet it overrides. */
ok(strpos($flexCss, '--msp-') === false,
   'the trial has leaked its tokens into flexbox.css, so it is no longer separable');
ok(strpos($pageCode, '--msp-') === false,
   'the trial has leaked its tokens into missions.php itself');

echo "\nthe tokens have one home\n";
/* Declared in the platform layer, consumed in both. Two declarations is
   two places to tune and one of them silently loses. */
foreach (array('--msp-lift', '--msp-lift-soft', '--msp-fill', '--msp-fill-in', '--msp-ghost') as $t) {
	ok(preg_match('~\s' . preg_quote($t, '~') . '\s*:~', $platCss) === 1,
	   "$t is not declared in the platform layer");
	ok(preg_match('~\s' . preg_quote($t, '~') . '\s*:~', $polCss) !== 1,
	   "$t is declared a second time in the page layer");
}
/*
 * AND THE PAGE LAYER FALLS BACK TO FLAT, NOT TO BLANK. An undefined
 * custom property makes the whole declaration invalid at computed-value
 * time, so `background: var(--msp-lift), #0a1929` with the platform
 * layer removed is not a flat panel -- it is a TRANSPARENT one. Deleting
 * one of the two links has to degrade, not break.
 */
preg_match_all('~var\((--msp-[a-z-]+)(,[^)]*)?\)~', $polCss, $vars, PREG_SET_ORDER);
ok(count($vars) >= 10, 'the page layer uses almost no tokens, so this is reading nothing');
$nofall = array();
foreach ($vars as $v) if (trim((string) (isset($v[2]) ? $v[2] : '')) !== ', none') $nofall[] = $v[1];
ok(count($nofall) === 0,
   'these have no `none` fallback, so dropping the platform layer blanks the panel: '
   . implode(', ', array_unique($nofall)));

echo "\nthe platform layer loads after what it overrides\n";
/*
 * Every rule matches the specificity of the one it shadows, so the only
 * thing making it win is document order.
 */
$atFlex = strpos($headCode, 'dist/flexbox.css');
$atPol  = strpos($headCode, 'dist/polish.css');
ok($atFlex !== false && $atPol !== false && $atFlex < $atPol,
   'polish.css is linked before flexbox.css, so none of it applies');
$lastStyle = strrpos(substr($pageCode, 0, strpos($pageCode, 'missions-polish.php')), '</style>');
ok($lastStyle !== false,
   'the page layer is included before any stylesheet on missions.php, so none of it applies');
$guest = strpos($pageCode, 'if ($ms_user <= 0)');
ok($guest !== false && strpos($pageCode, 'missions-polish.php') < $guest,
   'the page layer is included inside a branch, so one of the two views is unstyled');
foreach ($both as $name => $css) {
	ok(strpos($css, '!important') === false,
	   "$name needs !important, which means it is fighting the platform instead of following it");
}

echo "\nboth layers can be switched off without a deploy\n";
/*
 * The owner is usually on a phone and cannot run anything, so comparing
 * the two states has to be a URL -- and it has to be ONE url, or half
 * the page reverts and the other half does not.
 */
foreach (array('header.php' => $headCode, 'missions-polish.php' => $polCode) as $name => $src) {
	ok(preg_match('~\$_GET\[.polish.\]\s*!==\s*.0.~', $src) === 1
	   || preg_match('~\$_GET\[.polish.\]\s*===\s*.0.~', $src) === 1,
	   "$name has no off switch, or does not compare against the string 0");
}
$off = strpos($polCode, "\$_GET['polish']");
$def = strpos($polCode, "define('MS_POLISH'");
$sty = strpos($polCode, '<style>');
ok($off !== false && $sty !== false && $off < $sty,
   'the page layer prints its stylesheet before reading the off switch');
ok($off !== false && $def !== false && $off < $def,
   'the off switch runs after MS_POLISH is defined, so a second include prints anyway');
ok(strpos($polCode, "defined('MS_POLISH')") !== false,
   'the page layer is not guarded, so a second include duplicates the whole block');
/* The link has to be inside the branch, not merely near it. */
/* BETWEEN THE if AND ITS endif, not within N characters of the test: the
   PHP close tag between them contains a `?`, so a [^?] window stopped
   before ever reaching the link. */
$ifAt  = strpos($headCode, "\$_GET['polish']");
$endAt = $ifAt !== false ? strpos($headCode, 'endif;', $ifAt) : false;
ok($ifAt !== false && $endAt !== false
   && strpos($headCode, 'dist/polish.css', $ifAt) !== false
   && strpos($headCode, 'dist/polish.css', $ifAt) < $endAt,
   'the off switch does not actually gate the <link>');

echo "\nit is a stylesheet and nothing else\n";
ok(substr_count($pol, '<style>') === 1 && substr_count($pol, '</style>') === 1,
   'the page layer is not exactly one style block');
ok(trim(substr($pol, strpos($pol, '</style>') + 8)) === '',
   'the page layer emits something after its stylesheet');
ok(preg_match('~<(?!style|/style)[a-z]~i', substr($pol, strpos($pol, '?>'))) !== 1,
   'the page layer emits markup, so removing it moves things on the page');
ok(strpos($polCode, '$conn') === false && strpos($polCode, 'include') === false,
   'the page layer queries or includes something; it is meant to be a stylesheet');

echo "\nthe page layer stays inside the missions namespace\n";
/*
 * It is loaded into a page that also carries flexbox.css and header.php.
 * A bare `.button {` or `a {` here would reach the nav and the footer at
 * once -- and anything that SHOULD reach them belongs in the platform
 * layer, which is the point of there being two.
 */
$bad = array();
foreach (preg_split('~\}~', $polCss) as $chunk) {
	$sel = trim(substr($chunk, 0, strpos($chunk . '{', '{')));
	if ($sel === '' || $sel[0] === '@') continue;
	foreach (explode(',', $sel) as $one) {
		$one = trim($one);
		if ($one === '' || $one === ':root') continue;
		if (strpos($one, '.ms-') !== 0) $bad[] = $one;
	}
}
ok(count($bad) === 0, 'these page-layer selectors are not scoped to missions: ' . implode(' | ', $bad));

echo "\nno colour is invented, in either layer\n";
/*
 * THE REAL RISK IN A GRADIENT PASS. Every tint on this platform means
 * something -- teal for ready, amber for new, red for failed -- and a
 * layer that repaints a fill instead of sitting on top of it silently
 * retunes the whole state vocabulary. So each rule keeps the surface's
 * own colour as its LAST layer, and that exact value has to still be
 * declared somewhere the platform already uses it.
 *
 * Borders are exempt by nature: a bevel edge is a lighter tint of the
 * fill, which is a colour that by definition did not exist before. Only
 * `background:` is checked.
 */
$known = $flexCss . "\n" . $pageCode;
$madeup = array();
$fills  = 0;
foreach ($both as $name => $css) {
	preg_match_all('~background:\s*var\(--msp-(?!ghost)[a-z-]+(?:,\s*none)?\),\s*([^;]+);~i', $css, $m, PREG_SET_ORDER);
	foreach ($m as $r) {
		$fills++;
		$c = trim($r[1]);
		if (stripos($known, $c) === false) $madeup[] = "$name: $c";
	}
}
ok($fills >= 30, 'only ' . $fills . ' fill rules were found across both layers, so this is reading nothing');
ok(count($madeup) === 0, 'these colours exist nowhere on the platform: ' . implode(' | ', array_unique($madeup)));

/*
 * THE GHOST RULES ARE THE ONE EXCEPTION, and it is narrow. An outline
 * control was `transparent` -- fine against a flat panel, a hole against
 * one with a gradient -- so each gets a faint fill of its own. The rule
 * is that the only selectors allowed a new fill are ones the platform
 * declared transparent, or ones whose fill it already had.
 */
$opaque = array();
foreach ($both as $name => $css) {
	preg_match_all('~([^{};]+)\{\s*background:\s*var\(--msp-ghost(?:,\s*none)?\),\s*([^;]+);~i', $css, $g, PREG_SET_ORDER);
	foreach ($g as $r) {
		$sel = trim(strtok($r[1], ','));
		/* A :hover rule is judged on its BASE selector: .ms-retreat is the
		   one the platform declares transparent, and .ms-retreat:hover
		   never had a rule of its own to be transparent in. */
		$sel = preg_replace('~:[a-z-]+$~', '', $sel);
		$c   = trim($r[2]);
		if (stripos($known, $c) !== false) continue;            /* reusing an existing fill */
		if (preg_match('~' . preg_quote($sel, '~') . '[^{]*\{[^}]*background(-color)?:\s*transparent~s', $known) === 1) continue;
		$opaque[] = "$name: $sel -> $c";
	}
}
ok(count($opaque) === 0,
   'these were not transparent on the platform, so the trial repainted a real fill: ' . implode(' | ', $opaque));

echo "\nthe state variants are covered, not just the base\n";
/*
 * THE EASY ONES TO MISS. flexbox.css colours these with selectors MORE
 * specific than the base, so they outrank it. Left out, a won raid, a
 * failed mission's bar and a highlighted leaderboard row would be the
 * only flat things on a page where everything else is lit, which reads
 * as those three being broken rather than as the pass being partial.
 */
$variants = array(
	'.mc-card.success'                      => 'a won mission card',
	'.mc-card.failure'                      => 'a lost mission card',
	'.lb-row.lb-highlight'                  => 'your own row on a leaderboard',
	'.mf-progress.complete .mf-progress-fill' => 'a finished mission-field bar, which turns teal',
	'.mc-card.success .mc-progress-fill'    => "a won card's bar, coloured separately",
	'.mc-card.failure .mc-progress-fill'    => "a lost card's bar, coloured separately",
	'.wallet-panel:hover'                   => 'a hovered wallet panel',
);
foreach ($variants as $sel => $what) {
	ok(preg_match('~(^|\n)\s*' . preg_quote($sel, '~') . '\s*(,[^{}]*)?\{\s*background:\s*var\(--msp-~', $platCss) === 1,
	   "$what stays flat while its sibling is lit");
}

echo "\nevery bar fill is lit, and no rail is\n";
/*
 * THE SHEEN RUNS ACROSS A BAR, NEVER ALONG IT: a fill that changes along
 * its length reads as though the value at the far end means something
 * different from the value at the near end. Named rather than counted --
 * a count passes with a third of them dropped.
 */
$bars = array(
	'.w3-grey-rewards'          => 'the 24px reward countdown bar',
	'.mf-progress-fill'         => 'the mission-field bar',
	'.rc-progress-fill'         => 'the raid progress bar',
	'.mc-progress-fill'         => 'the mission card bar',
	'.modal-skull-loader .lbar' => "the modal's loading bar",
);
foreach ($bars as $sel => $what) {
	ok(preg_match('~(^|\n)\s*' . preg_quote($sel, '~') . '\s*(,[^{}]*)?\{\s*background:\s*var\(--msp-fill~', $platCss) === 1,
	   "$what stays flat while every other bar is lit");
}
/* An empty bar is a groove. `.mc-progress-fill` is the fill and
   `.mc-progress` on its own is the rail, so this looks for the rail
   without its child. */
foreach (array('.mc-progress', '.mf-progress', '.rc-progress-bar', '.modal-skull-loader .lbar-wrap') as $rail) {
	ok(preg_match('~(^|\n)\s*' . preg_quote($rail, '~') . '\s*(,|\{)~', $platCss) !== 1,
	   "the trial lights the $rail rail, which is a groove, not a fill");
}
/* .ms-proj-bar is 2px: the ramp would be one light pixel and one dark
   one with none of the fill left in between -- and that fill is #ffcc44
   when a project is part ready and #00c8a0 when it is full. */
ok(strpos($polCss, '.ms-proj-bar') === false,
   'the 2px project bar was given a ramp, which washes out the colour that is its only state');

echo "\nthe wells and the fields stay flat\n";
/*
 * #080f18 and #0d1e2e are holes cut INTO a panel, not surfaces sitting
 * on one, and lighting a recess the same way as the surface is what
 * makes a gradient pass look cheap.
 */
foreach (array('.w3-border', '#results', '.col1of2', '.projects-container',
               '.wallet-connect select', '#filterNFTs', '#filterRealms') as $well) {
	ok(preg_match('~(^|\n)\s*' . preg_quote($well, '~') . '\s*(,|\{)~', $platCss) !== 1,
	   "the platform layer lights $well, which is a recess, not a surface");
}
foreach (array('.ms-mcard-art', '.ms-quest-art', '.ms-d-art', '.ms-d-nft', '.ms-d-item') as $well) {
	ok(strpos($polCss, $well) === false, "the page layer lights $well, which is a recess, not a surface");
}

echo "\nthe nav is built from the platform's own colours\n";
/*
 * IT WAS THE ONLY PART NOT BUILT FROM THEM. #040c16 and white text is a
 * darker, flatter, greyer thing than anything underneath it, so the
 * chrome read as belonging to a different product than the page.
 *
 * The fix is not a new palette: it is the panel colour at the top edge
 * falling to the nav's own near-black at the bottom, and the same teal
 * hairline every panel already uses. So every colour in these rules has
 * to be one flexbox.css already declares.
 */
foreach (array('.navbar', '.nav-dropdown-menu', '#quick-menu') as $sel) {
	ok(preg_match('~(^|\n)' . preg_quote($sel, '~') . '\s*\{~', $platCss) === 1,
	   "$sel is untouched, so part of the chrome still looks like a different product");
}
$navBlock = substr($platCss, strpos($platCss, '.navbar {'));
preg_match_all('~#[0-9a-f]{6}~i', $navBlock, $hexes);
$strange = array();
foreach (array_unique($hexes[0]) as $h) if (stripos($flexCss, $h) === false) $strange[] = $h;
ok(count($strange) === 0,
   'the nav introduces colours the platform does not have: ' . implode(', ', $strange));
/* A bar with no bottom edge floats; the hairline is what seats it. */
ok(preg_match('~\.navbar\s*\{[^}]*border-bottom:[^}]*rgba\(0,200,160~s', $platCss) === 1,
   'the navbar has no teal hairline under it, so it still floats off the page');
ok(preg_match('~#quick-menu\s*\{[^}]*border-top:[^}]*rgba\(0,200,160~s', $platCss) === 1,
   'the phone bottom bar has no hairline over it');
/* Hover was a soft teal rectangle with no boundary. */
ok(preg_match('~\.navbar a:hover[^{]*\{[^}]*box-shadow:\s*inset~s', $platCss) === 1,
   'nav hover is still a wash with no edge');


echo "\nthe DHC pages are covered too, and the same way\n";
/*
 * THEY WERE MISSING ENTIRELY. Every DHC surface is declared in an inline
 * <style> block, which is later in the document than any <link>, so
 * dist/polish.css could never reach them -- the same reason missions.php
 * has its own layer. Reported as "I don't see the new gradient style
 * applied to any of the DHC Fighter pages".
 */
$dhc  = (string) @file_get_contents(__DIR__ . '/polish-dhc.php');
$dhcCss = $dhc === '' ? '' : preg_replace('~/\*.*?\*/~s',
	'', substr($dhc, strpos($dhc, '<style>') + 7, strpos($dhc, '</style>') - strpos($dhc, '<style>') - 7));
ok($dhc !== '', 'the DHC layer is gone');
ok(strpos($dhc, "\$_GET['polish']") !== false, 'the DHC layer has no off switch');
ok(strpos($dhc, "defined('DHC_POLISH')") !== false, 'the DHC layer is not guarded against a second include');
ok(strpos($dhcCss, '!important') === false, 'the DHC layer needs !important');
/* Included AFTER each page's own stylesheet, or it is outranked. */
foreach (array('dhcfighters.php', 'dhcgallery.php', 'dhcarena.php') as $dhcPage) {
	$src = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/' . $dhcPage));
	$at  = strpos($src, 'polish-dhc.php');
	ok(substr_count($src, "include __DIR__ . '/polish-dhc.php'") === 1,
	   "$dhcPage includes the DHC layer more than once, or not at all");
	ok($at !== false && strrpos(substr($src, 0, $at), '</style>') !== false,
	   "$dhcPage includes the DHC layer before its own stylesheet ends, so it is outranked");
}
/* .dhcf-panel had a BORDER AND NO BACKGROUND -- a rectangle drawn on the
   page, not a panel sitting on it, with nothing for a gradient to light. */
ok(preg_match('~(^|\n)\s*\.dhcf-panel\s*(,[^{}]*)?\{\s*background:\s*var\(--msp-lift~', $dhcCss) === 1,
   '.dhcf-panel still has no fill, so there is nothing to light');
/* --panel2 is the platform field colour. Wells stay flat here too. */
foreach (array('.dhcg-art', '.boardwrap') as $dhcWell) {
	ok(strpos($dhcCss, $dhcWell) === false,
	   "the DHC layer lights $dhcWell, which is --panel2: a recess, not a surface");
}
/* Read the TOKEN, not a hex: the Arena retunes --panel on its battle
   board, and a pinned colour would ignore that. */
/* The count of token uses was a second assertion that could not fail on
   its own: anything that pinned a hex also dropped the count, so it only
   ever duplicated the check below. */
/* The `, none` fallback sits inside the var(), so [a-z-]+ stopped at the
   comma and this matched nothing either way. */
/*
 * ONLY THE SURFACE COLOURS, not every hex. The Arena's primary button is
 * the platform teal and has no DHC token to read, so #00c8a0 there is
 * correct; a first pass flagged it and failed on a clean tree. What must
 * never be pinned is a colour the pages DO tokenise -- --ink, --panel,
 * --panel2 -- because the Arena's battle board retunes all three and a
 * pinned hex would ignore that.
 */
/*
 * ANY bare hex as the final layer, not just the three surface colours.
 *
 * THE BUG THIS MISSED. The first version only objected to #07111d,
 * #0a1929 and #0d1e2e, so `.arena-wrap .btn { ..., #00c8a0 }` sailed
 * through -- and `.btn` is the Arena's QUIET button (var(--panel2)),
 * not its primary, so the X share link came out brand green. The same
 * blind spot pinned #00a882 on `.btn:active`, which made "Back to the
 * Arena" flip from red to green on press, because that button is
 * `.btn.go` and its colour is var(--blood), which #arenaBattle retunes
 * to #e0466b.
 *
 * A hex INSIDE a var() fallback is fine and is the point -- it is the
 * default when the token is absent. Only the layer after the var() is
 * checked, because that is the one that ignores the page.
 */
ok(preg_match('~background:\s*var\(--msp-[a-z-]+(?:,\s*none)?\),\s*#[0-9a-f]{3,8}\b~i', $dhcCss) !== 1,
   'the DHC layer pins a hex where the page has a token, so a page that retunes it is ignored');
/*
 * AND THE TWO ARENA BUTTONS BY NAME, because which is which is the
 * thing that was actually misread. `.btn` is quiet, `.btn.go` is the
 * primary; getting them the wrong way round is not a colour mistake,
 * it is a hierarchy one.
 */
ok(preg_match('~\.arena-wrap \.btn \{[^}]*var\(--msp-ghost~', $dhcCss) === 1,
   "the Arena's quiet button is not using the quiet ramp; it is .btn.go that is the primary");
ok(preg_match('~\.arena-wrap \.btn\.go \{[^}]*var\(--blood~', $dhcCss) === 1,
   "the Arena's primary does not keep var(--blood), which #arenaBattle retunes to red");
ok(preg_match('~\.arena-wrap \.btn:(hover|active)\s*\{~', $dhcCss) !== 1,
   'the quiet button has a hover or active fill of its own, which overrides the page and flips its colour on press');
/* Same fallbacks as the missions layer, for the same reason. */
preg_match_all('~var\((--msp-[a-z-]+)(,[^)]*)?\)~', $dhcCss, $dv, PREG_SET_ORDER);
$dnofall = array();
foreach ($dv as $v) if (trim((string) (isset($v[2]) ? $v[2] : '')) !== ', none') $dnofall[] = $v[1];
ok(count($dnofall) === 0,
   'these DHC tokens have no `none` fallback, so dropping the platform layer blanks the panel: '
   . implode(', ', array_unique($dnofall)));


echo "\nMonstrocity is covered too, and it had to be different\n";
/*
 * TWO REASONS THIS PAGE NEEDED ITS OWN LAYER, not one.
 *
 * It does not include header.php at all -- it is a standalone game page
 * like skullswap.php -- so dist/polish.css is never linked, the --msp-
 * tokens do not exist, and polish-sweep.js never runs either. AND every
 * surface it draws is in an inline <style>, so even a linked stylesheet
 * would be outranked. So this layer DECLARES the tokens as well as
 * consuming them, and it uses !important, and both of those are
 * deviations the other two layers are forbidden -- which is exactly why
 * they are asserted here rather than left to read as sloppiness.
 */
$mo    = (string) @file_get_contents(__DIR__ . '/polish-monstrocity.php');
$moPage = preg_replace('~/\*.*?\*/~s', '', (string) file_get_contents(__DIR__ . '/monstrocity.php'));
$moCss = $mo === '' ? '' : preg_replace('~/\*.*?\*/~s',
	'', substr($mo, strpos($mo, '?>') + 2));
$moCss = $moCss === '' ? '' : substr($moCss, strpos($moCss, '<style>') + 7);
$moCss = $moCss === '' ? '' : substr($moCss, 0, strrpos($moCss, '</style>'));
ok($mo !== '', 'the Monstrocity layer is gone');
ok(strpos($mo, "\$_GET['polish']") !== false, 'the Monstrocity layer has no off switch');
ok(strpos($mo, "defined('MONSTROCITY_POLISH')") !== false,
   'the Monstrocity layer is not guarded against a second include');
ok(substr_count($moPage, "include __DIR__ . '/polish-monstrocity.php'") === 1,
   'monstrocity.php includes the layer more than once, or not at all');
/*
 * AFTER BOTH OF THE PAGE'S STYLESHEETS, or the page outranks it.
 *
 * Measured against the real <style> blocks, not the last `</style>` in
 * the file: there is a document.write('<style>...</style>') further down
 * that hides one container on #boss, and a plain strrpos reported that
 * string as a stylesheet and failed on a clean tree.
 */
$moAt    = strpos($moPage, 'polish-monstrocity.php');
$moSheets = array();
if (preg_match_all('~<style>(.*?)</style>~s', $moPage, $mb, PREG_OFFSET_CAPTURE | PREG_SET_ORDER))
	foreach ($mb as $b) if (strlen($b[1][0]) > 500) $moSheets[] = $b[0][1] + strlen($b[0][0]);
ok(count($moSheets) === 2, 'monstrocity.php no longer has its two stylesheets where this expects them');
ok($moAt !== false && count($moSheets) > 0 && max($moSheets) < $moAt,
   'the Monstrocity layer is included before the page stops declaring styles, so it is outranked');

/*
 * THE TOKENS ARE A COPY, AND THIS IS WHAT STOPS THEM DRIFTING.
 *
 * They cannot be shared: linking dist/polish.css here would also drag
 * its .content and .button rules into a page that has its own meanings
 * for neither. So the five declarations are duplicated, and compared
 * character for character against the platform's. Anything else and the
 * platform quietly grows a second, slightly different look.
 */
$tokens = array('--msp-lift', '--msp-lift-soft', '--msp-fill', '--msp-fill-in', '--msp-ghost');
$norm = function ($css, $t) {
	if (preg_match('~' . preg_quote($t, '~') . '\s*:\s*([^;]+);~', $css, $m) !== 1) return null;
	return preg_replace('~\s+~', ' ', trim($m[1]));
};
foreach ($tokens as $t) {
	$a = $norm($platCss, $t);
	$b = $norm($moCss, $t);
	ok($b !== null, "$t is not declared in the Monstrocity layer, and nothing else declares it on that page");
	ok($a !== null && $a === $b, "$t has drifted from the platform's value");
}
/* And the reason the copy is necessary has to stay true. */
ok(strpos($moPage, 'dist/polish.css') === false,
   'monstrocity.php now links the platform layer, so the tokens are declared twice');
ok(strpos($moPage, '--msp-') === false,
   'the trial has leaked its tokens into monstrocity.php itself');

/*
 * !important IS REQUIRED HERE, which is the opposite of the rule the
 * other two layers follow. Every surface this shadows is declared
 * !important by the page's own skin block, so a layer without it loses
 * wherever it sits. The guard is that it may not be used on anything
 * but the paint: a layout or type property forced from here would be
 * the layer doing more than lighting.
 */
ok(strpos($moCss, '!important') !== false,
   'the Monstrocity layer dropped !important, so the page skin outranks all of it');
ok(preg_match('~\bbutton\s*\{[^}]*!important~', $moPage) === 1,
   'the page no longer forces its own button fill, so the layer does not need !important either');
$forced = array();
preg_match_all('~([a-z-]+)\s*:[^;{}]*!important~i', $moCss, $imp, PREG_SET_ORDER);
foreach ($imp as $r) if (!in_array(strtolower($r[1]), array('background', 'background-image', 'border', 'border-color', 'box-sizing'), true)) $forced[] = $r[1];
ok(count($forced) === 0,
   'the Monstrocity layer forces properties that are not paint: ' . implode(', ', array_unique($forced)));

/*
 * NO COLOUR IS INVENTED. Same rule as the platform layer: each surface
 * keeps its own fill as the LAST layer, and that value has to be one
 * monstrocity.php already chose.
 */
/* Spaces stripped from both sides: the page writes rgba(255, 255, 255,
   0.08) and the layer writes it closed up, and a literal compare called
   a colour that is right there on the page invented. */
$moFills = 0; $moMade = array();
$moFlat  = preg_replace('~\s+~', '', $moPage);
preg_match_all('~background:\s*var\(--msp-[a-z-]+\),\s*([^;!]+)~i', $moCss, $mf, PREG_SET_ORDER);
foreach ($mf as $r) {
	$c = trim($r[1]);
	if ($c === 'transparent') continue;
	$moFills++;
	if (stripos($moFlat, preg_replace('~\s+~', '', $c)) === false) $moMade[] = $c;
}
ok($moFills >= 8, 'only ' . $moFills . ' fill rules in the Monstrocity layer, so this is reading nothing');
ok(count($moMade) === 0,
   'these colours exist nowhere on the page, so the layer repainted rather than lit: '
   . implode(' | ', array_unique($moMade)));

/*
 * THE RECESSES STAY FLAT, and on this page there are five of them. The
 * board is the ground the tiles sit IN and they carry their own emboss,
 * so a lift behind them reads as a smear across the top row; the filter
 * is a form field; the three select containers are #07111d, the PAGE
 * colour used as a full-bleed modal ground. Naming them is the only way
 * to catch somebody adding them later for consistency.
 */
foreach (array('#game-board', '.boss-project-filter', '#character-select-container',
               '#theme-select-container', '#boss-select-container') as $moWell) {
	ok(strpos($moCss, $moWell) === false,
	   "the Monstrocity layer lights $moWell, which is a recess or a ground, not a surface");
}
/* The bar is the one place the rail and the fill are both present and
   only one of them may be lit. */
ok(preg_match('~(^|\n)\s*\.health\s*\{[^}]*var\(--msp-fill~', $moCss) === 1,
   'the health fill is not lit, so the one bar on the page stays flat');
ok(preg_match('~(^|\n)\s*\.health-bar\s*[,{]~', $moCss) !== 1,
   'the Monstrocity layer lights .health-bar, which is the groove the fill runs in');

/*
 * THE LEGEND IS A KEY TO THE TILES, so its swatches carry the tile's own
 * treatment. The tiles got theirs in afe55149 and the swatches did not,
 * which is how a key stops looking like the thing it describes. Compared
 * against the page rather than pinned, so retuning .tile retunes this or
 * fails here.
 */
$tileRamp = preg_match('~\.tile\s*\{[^}]*background-image:\s*(linear-gradient\([^;]+)~s', $moPage, $tr)
          ? preg_replace('~\s+~', '', $tr[1]) : '';
$legRamp  = preg_match('~\.legend-tile\s*\{[^}]*background-image:\s*(linear-gradient\([^;]+)~s', $moCss, $lr)
          ? preg_replace('~\s+~', '', $lr[1]) : '';
ok($tileRamp !== '', "the tile's own gradient cannot be read from monstrocity.php");
ok($legRamp === $tileRamp,
   'the legend swatches no longer carry the tile gradient, so the key and the board disagree');

echo "\nthe sweep reaches what a stylesheet cannot\n";
/*
 * FIFTY THREE PAGES carry panels in their own inline <style>, which is
 * later in the document than any <link> and therefore outranks
 * dist/polish.css. Each has its own class names -- .lp-tile, .ac-card,
 * .rl-slot, .st-fig -- so naming them in the stylesheet means naming all
 * of them and missing the next one somebody writes.
 *
 * They are not fifty three palettes though: #0a1929 appears sixty four
 * times across them. So the sweep matches on COLOUR, which is something
 * the browser will tell us about any element and a stylesheet cannot ask.
 */
/* COMMENT-STRIPPED, like every other source this harness reads: the file's
   own header explains that it is injected last so that it does NOT need
   !important, and the check for that phrase was matching the explanation. */
$swRaw = (string) @file_get_contents(__DIR__ . '/dist/polish-sweep.js');
$sw    = $strip($swRaw);
ok($swRaw !== '', 'the sweep is gone');
ok(substr_count($headCode, 'dist/polish-sweep.js') === 1,
   'header.php loads the sweep more than once, or not at all');
$atCss = strpos($headCode, 'dist/polish.css');
$atJs  = strpos($headCode, 'dist/polish-sweep.js');
ok($atCss !== false && $atJs !== false && $atCss < $atJs,
   'the sweep is loaded before the stylesheet whose tokens it uses');
$ifAt  = strpos($headCode, "\$_GET['polish']");
$endAt = $ifAt !== false ? strpos($headCode, 'endif;', $ifAt) : false;
ok($ifAt !== false && $endAt !== false && $atJs > $ifAt && $atJs < $endAt,
   'the off switch does not gate the sweep, so ?polish=0 only half reverts');
/* [\s\S]{0,160}? and not [^>]*: the cache-buster is a PHP tag, and `?>`
   contains a > that stopped the window before it reached the attribute. */
ok(strpos($headCode, 'polish-sweep.js?var=') !== false
   && preg_match('~polish-sweep\.js[\s\S]{0,160}?defer~', $headCode) === 1,
   'the sweep is not deferred; it reads layout and must not do that during load');

echo "\nit adds a layer, it can never change a colour\n";
/*
 * THE WHOLE SAFETY ARGUMENT. It sets background-IMAGE over whatever fill
 * is already there, so a tint that means something keeps meaning it, and
 * an element whose colour it does not recognise is simply left alone.
 */
ok(strpos($sw, 'background-image:var(--msp-lift)') !== false,
   'the sweep no longer applies the lift');
ok(preg_match('~background-color\s*:~', $sw) !== 1 && preg_match("~style\.background\s*=~", $sw) !== 1,
   'the sweep sets a background COLOUR, so it can repaint a tint that means something');
ok(strpos($sw, '!important') === false,
   'the sweep needs !important; it is injected last precisely so it does not');
ok(preg_match('~document\.body\.appendChild\(s\)~', $sw) === 1,
   'the sweep does not inject its rules at the end of the body, so a page stylesheet outranks them');

echo "\nthe grounds, the wells and the fields are not in its list\n";
/*
 * This is the list that keeps it safe, and it is safe by OMISSION rather
 * than by exclusion: #07111d is the page itself, #080f18 and #0d1e2e are
 * recesses cut into a panel, #040c16 is the nav which the stylesheet
 * already does properly. None of them appear, so nothing has to filter
 * them out.
 */
preg_match('~var PANEL = \{(.*?)\};~s', $sw, $pm);
preg_match_all("~'rgb\((\d+), (\d+), (\d+)\)'~", isset($pm[1]) ? $pm[1] : '', $pc, PREG_SET_ORDER);
ok(count($pc) >= 3, 'the sweep recognises almost no surfaces (' . count($pc) . ')');
$banned = array('7,17,29' => '#07111d the page ground', '8,15,24' => '#080f18 a well',
                '13,30,46' => '#0d1e2e a form field', '4,12,22' => '#040c16 the nav');
$in = array();
foreach ($pc as $m) $in[$m[1] . ',' . $m[2] . ',' . $m[3]] = 1;
foreach ($banned as $rgb => $what) {
	ok(!isset($in[$rgb]), "the sweep would light $what, which is not a surface");
}
/* And every colour it DOES list has to be one the platform actually
   paints, or it is guessing. */
$paints = $flexCss;
foreach (glob(__DIR__ . '/*.php') as $pf) {
	if (strpos($pf, 'harness') !== false) continue;
	$paints .= "\n" . (string) @file_get_contents($pf);
}
$unused = array();
foreach ($pc as $m) {
	$hex = sprintf('#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3]);
	if (stripos($paints, $hex) === false) $unused[] = $hex;
}
ok(count($unused) === 0,
   'these colours are not painted anywhere on the platform: ' . implode(', ', $unused));

echo "\nit never lifts the same surface twice, and never a chip\n";
ok(preg_match("~cs\.backgroundImage !== 'none'\) continue~", $sw) === 1,
   'the sweep lifts surfaces the stylesheet already lifted, which is visibly darker');
/* THE NUMBERS, not just the shape of the test: a floor of 0 matches the
   same pattern and lets every chip through. */
ok(preg_match('~r\.width < (\d+) \|\| r\.height < (\d+)~', $sw, $fl) === 1,
   'there is no size floor, so pills, chips and badges get a panel gradient');
if (!empty($fl)) {
	ok((int) $fl[1] >= 24 && (int) $fl[2] >= 16,
	   'the size floor is ' . $fl[1] . 'x' . $fl[2] . ', which is small enough to let chips through');
}
/* COLOUR FIRST, THEN SIZE. getBoundingClientRect forces layout, so it
   must only run for the few elements that already matched. */
$mk = substr($sw, strpos($sw, 'function mark('));
$mk = substr($mk, 0, strpos($mk, "\n\t}"));
ok(strpos($mk, 'if (!kind) continue;') !== false
   && strpos($mk, 'if (!kind) continue;') < strpos($mk, 'tooSmall(el)'),
   'the sweep measures every element before checking its colour, which forces a layout per element');
ok(strpos($sw, "closest('[data-msp-skip]')") !== false,
   'there is no way for a page to opt a subtree out');

echo "\nit runs after the paint, and catches what arrives later\n";
ok(preg_match('~requestIdleCallback\(start~', $sw) === 1,
   'the sweep does not wait for an idle moment');
ok(preg_match("~addEventListener\('load', boot\)~", $sw) === 1,
   'the sweep runs before the first paint');
/* Most of this platform arrives after the first paint -- the missions
   field, the feed, the store grid, every modal. */
ok(preg_match('~\.observe\(document\.body, \{ childList: true, subtree: true \}\)~', $sw) === 1,
   'content added after load stays flat beside content that is lifted');
ok(preg_match('~setTimeout\(function \(\) \{[\s\S]{0,400}?\}, 250\)~', $sw) === 1,
   'the observer is not debounced, so a busy page re-sweeps on every insertion');

echo "\nold corners are squared, deliberate ones are kept\n";
/*
 * The platform's older pages give panels an 8-10px radius and everything
 * built since is square or near it. The DHC pages use a deliberate 3px,
 * so this is a threshold rather than a blanket rule -- and a percentage
 * is an avatar and is never touched.
 */
ok(preg_match("~rad\.indexOf\('%'\) === -1 && parseFloat\(rad\) >= (\d+)~", $sw, $rt) === 1,
   'the radius rule is gone, or no longer skips percentages');
if (!empty($rt)) {
	ok((int) $rt[1] >= 4 && (int) $rt[1] <= 8,
	   'the squaring threshold is ' . $rt[1] . 'px, which will either flatten deliberate corners or miss the old ones');
}
ok(strpos($sw, '[data-msp-sq]{border-radius:0}') !== false,
   'nothing squares the marked corners');

echo "\nthe ground is lit, and it costs nothing to scroll\n";
/*
 * THE PAGE GROUND. A flat #07111d gives every panel the same edge
 * against the same nothing; a soft radial behind everything is what
 * makes a panel read as an object ON a ground. It is the claim
 * dialog's veil scaled up to the window.
 */
$grd = preg_match('~body::before \{.*?\n\}~s', $plat, $gm) ? $gm[0] : '';
ok($grd !== '', 'there is no page ground, so every panel sits on a flat sheet');
/*
 * A FIXED LAYER, NOT background-attachment: fixed.
 *
 * Both give a gradient sized to the window that stays put while the
 * page scrolls. The difference is that iOS Safari implements the
 * attachment by repainting a document-sized background on every scroll
 * frame -- the same class of mistake as a drop-shadow filter on a fixed
 * element, which cost this platform its back-to-top button. A
 * position:fixed layer is composited.
 */
ok(strpos($grd, 'position: fixed') !== false,
   'the ground is not a fixed layer, so it scrolls with the page or smears over its whole height');
/* THE DECLARATIONS, NOT THE FILE. The paragraph above this rule names
   the property in order to explain why it is not used -- so a plain
   search over the source matches the comment and reports the thing it
   is warning about. Comments out first. */
$platCode = preg_replace('~/\*.*?\*/~s', '', $plat);
ok(strpos($platCode, 'background-attachment: fixed') === false,
   'background-attachment:fixed is back; iOS repaints a document-sized background every scroll frame');
/*
 * BEHIND EVERYTHING, AND IT TAKES NOTHING. html carries no background,
 * so body's colour propagates to the canvas and this paints between the
 * canvas and the content. Without the negative z-index it covers the
 * page; without pointer-events it swallows every click on it.
 */
ok(preg_match('~z-index: -1~', $grd) === 1,
   'the ground is not behind the content, so it paints over the whole page');
ok(strpos($grd, 'pointer-events: none') !== false,
   'the ground takes pointer events, and it covers the entire window');
/* And the colour it fades to has to BE the ground, or the gradient ends
   on a seam. Read from flexbox.css, so the two cannot drift. */
$grdBody = preg_match('~\nbody \{[^}]*background-color:\s*(#[0-9a-f]{6})~si', $flex, $gb)
         ? strtolower($gb[1]) : '';
ok($grdBody !== '', 'the page ground colour cannot be read from flexbox.css');
$grdRgb = sprintf('rgba(%d,%d,%d,0)', hexdec(substr($grdBody,1,2)), hexdec(substr($grdBody,3,2)), hexdec(substr($grdBody,5,2)));
ok(substr_count($grd, $grdRgb) === 2,
   'a ground layer does not fade out to the page colour (' . $grdRgb . '), so it ends on a seam');
/* One <link> and ?polish=0 is the whole revert, and that only holds
   while this lives in polish.css rather than flexbox.css. */
ok(strpos($flex, 'body::before') === false,
   'the ground has been merged into flexbox.css, so deleting the polish link no longer reverts it');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
