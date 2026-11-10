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

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
