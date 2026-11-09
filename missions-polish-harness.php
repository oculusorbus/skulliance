<?php
/* missions-polish-harness.php — CLI only. Static. No database.
 *
 * THIS IS A TRIAL, SO WHAT IT TESTS IS THE REVERT.
 *
 * missions-polish.php exists to be looked at and then either kept or
 * deleted. Everything worth asserting about it is about that: that it is
 * one include line, that it touches nothing outside the missions
 * namespace, that it changes no colour the page had already chosen, and
 * that it can be switched off without a deploy.
 *
 * It does NOT assert what the gradients look like. That is the question
 * the trial is asking, and a harness that answered it would be asserting
 * my own taste back at me.
 */
$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$pol  = (string) file_get_contents(__DIR__ . '/missions-polish.php');
$page = (string) file_get_contents(__DIR__ . '/missions.php');
$strip = function ($s) {
	$s = preg_replace('~/\*.*?\*/~s', '', $s);
	return preg_replace('~^\s*//.*$~m', '', $s);
};
$polCode  = $strip($pol);
$pageCode = $strip($page);

echo "the revert is one line\n";
ok(substr_count($pageCode, "include __DIR__ . '/missions-polish.php'") === 1,
   'missions.php includes the trial more than once, or not at all');
/* Nothing else may reach for it. A second caller is a second thing to
   remember to unpick, which is the whole failure this guards. */
$others = 0;
foreach (glob(__DIR__ . '/*.php') as $f) {
	$b = basename($f);
	if ($b === 'missions.php' || $b === 'missions-polish.php' || $b === 'missions-polish-harness.php') continue;
	if (strpos((string) file_get_contents($f), 'missions-polish') !== false) { $others++; echo "    also: $b\n"; }
}
ok($others === 0, 'another file reaches for the trial, so deleting one line is not the revert');
/* And the trial must not have edited the page's own stylesheet. If the
   gradients were merged in there, deleting the include changes nothing. */
ok(strpos($pageCode, '--msp-') === false,
   'the trial has leaked custom properties into missions.php itself');
ok(strpos($pageCode, 'linear-gradient') === false,
   'missions.php now declares gradients of its own, so the trial is not separable');

echo "\nit is a stylesheet and nothing else\n";
ok(substr_count($pol, '<style>') === 1 && substr_count($pol, '</style>') === 1,
   'the trial is not exactly one style block');
/* No markup: a trial that adds elements changes the layout, and then
   removing it is a layout change rather than a colour one. */
$afterStyle = substr($pol, strpos($pol, '</style>') + 8);
ok(trim($afterStyle) === '', 'the trial emits something after its stylesheet');
ok(preg_match('~<(?!style|/style)[a-z]~i', substr($pol, strpos($pol, '?>'))) !== 1,
   'the trial emits markup, so removing it moves things on the page');
ok(strpos($polCode, '$conn') === false && strpos($polCode, 'include') === false,
   'the trial queries or includes something; it is meant to be a stylesheet');

echo "\nit can be switched off without a deploy\n";
/*
 * The owner is usually on a phone and cannot run anything, so comparing
 * the two states has to be a URL. The guard has to come BEFORE the
 * define and before any output, or ?polish=0 returns a page that has
 * already printed the stylesheet.
 */
ok(strpos($polCode, "\$_GET['polish']") !== false, 'there is no off switch');
$off = strpos($polCode, "\$_GET['polish']");
$def = strpos($polCode, "define('MS_POLISH'");
$sty = strpos($polCode, '<style>');
ok($off !== false && $sty !== false && $off < $sty,
   'the off switch runs after the stylesheet has been printed');
ok($off !== false && $def !== false && $off < $def,
   'the off switch runs after MS_POLISH is defined, so a second include prints anyway');
ok(preg_match('~\$_GET\[.polish.\]\s*===\s*.0.~', $polCode) === 1,
   'the off switch does not compare against the string 0, so a stray ?polish=1 disables it');
ok(strpos($polCode, "defined('MS_POLISH')") !== false,
   'the trial is not guarded, so a second include duplicates the whole block');

echo "\nit stays inside the missions namespace\n";
/*
 * This is an override layer loaded into a page that also carries
 * flexbox.css and header.php. A bare `.button {` or `a {` here would
 * reach the nav, the footer and the drawer at once.
 */
$css = substr($pol, strpos($pol, '<style>') + 7);
$css = substr($css, 0, strpos($css, '</style>'));
$css = preg_replace('~/\*.*?\*/~s', '', $css);
$bad = array();
foreach (preg_split('~\}~', $css) as $chunk) {
	$sel = trim(substr($chunk, 0, strpos($chunk . '{', '{')));
	if ($sel === '' || $sel[0] === '@') continue;
	foreach (explode(',', $sel) as $one) {
		$one = trim($one);
		if ($one === '' || $one === ':root') continue;
		/* Every selector must START in a namespace that only exists on
		   this page. A descendant of one is fine (.ms-daily .button, and
		   `.w3-grey-rewards` scoped under either prefix, which is how a
		   platform class gets styled here without reaching other pages);
		   a bare .button or .w3-grey-rewards is not.
		   #mcm- is missions-claim-modal.php, included only from here. */
		$scoped = false;
		foreach (array('.ms-', '#mcm-') as $ns) if (strpos($one, $ns) === 0) $scoped = true;
		if (!$scoped) $bad[] = $one;
	}
}
ok(count($bad) === 0, 'these selectors are not scoped to missions: ' . implode(' | ', $bad));
ok(strpos($css, '!important') === false,
   'the trial needs !important, which means it is fighting the page instead of following it');
/* Custom properties are global once declared on :root, so they are
   prefixed rather than named after what they do. */
preg_match_all('~(--[a-z-]+)\s*:~', $css, $vars);
$unprefixed = array_values(array_filter(array_unique($vars[1]), function ($v) {
	return strpos($v, '--msp-') !== 0;
}));
ok(count($unprefixed) === 0,
   'these custom properties are not --msp- prefixed and could collide: ' . implode(', ', $unprefixed));

echo "\nit changes no colour the page had already chosen\n";
/*
 * THE REAL RISK IN A GRADIENT PASS. Every tinted panel on this page
 * means something -- teal is claimable, amber is new -- and a layer that
 * repaints the fill instead of sitting on top of it silently retunes the
 * page's state colours. So each rule here keeps the panel's own colour
 * as its last layer, and the exact value has to still appear in
 * missions.php against that same selector.
 */
/* --msp-lift and --msp-lift-soft are the PANEL layers. The buttons are
   checked separately below, because a button is where a new colour is
   legitimate. */
/* The whole selector LIST, not just the one before the brace: most of
   these panels share a single grouped rule, so capturing the last
   selector counted fifteen panels as one and the coverage check below
   read 8 where it should read 20. */
preg_match_all('~([^{}]+)\{\s*background:\s*var\(--msp-lift[a-z-]*\),\s*([^;]+);~i',
	$css, $rules, PREG_SET_ORDER);
$panels = array();
$invented = array();
foreach ($rules as $r) {
	$colour = trim($r[2]);
	foreach (explode(',', $r[1]) as $one) {
		$one = trim($one);
		if ($one === '') continue;
		$panels[] = $one;
		/* Matched against the page verbatim: it writes rgba without spaces. */
		if (strpos($pageCode, 'background: ' . $colour) === false) $invented[] = $one . ' -> ' . $colour;
	}
}
ok(count($panels) >= 18,
   'only ' . count($panels) . ' panels are covered, so most of the page was missed');
ok(count($invented) === 0,
   'these panels were given a colour missions.php does not use: ' . implode(' | ', $invented));
/*
 * And every one of them has to be something missions.php ALREADY PAINTS.
 *
 * Existing as a selector is not enough. .ms-section is a real class with
 * no background at all -- adding it to the list would put a panel on the
 * page where there was none, which is a layout decision wearing a
 * gradient pass's clothes. So each selector has to have a rule of its own
 * that declares a background.
 */
$unpainted = array();
foreach ($panels as $one) {
	$painted = false;
	$at = 0;
	while (($i = strpos($pageCode, $one, $at)) !== false) {
		$at = $i + 1;
		/* .ms-fig must not match .ms-fig-go or .ms-figures. */
		$next = isset($pageCode[$i + strlen($one)]) ? $pageCode[$i + strlen($one)] : '';
		if (strpos(" ,{.:\n\t", $next) === false) continue;
		$end = strpos($pageCode, '}', $i);
		if ($end !== false && strpos(substr($pageCode, $i, $end - $i), 'background:') !== false) {
			$painted = true; break;
		}
	}
	if (!$painted) $unpainted[] = $one;
}
ok(count($unpainted) === 0,
   'missions.php paints no background for these, so the trial invents panels: ' . implode(', ', $unpainted));

/*
 * THE GHOST BUTTONS ARE THE ONE EXCEPTION, and it is a narrow one.
 * .ms-daily-lb, .ms-more-btn, .ms-d-tool and .ms-retreat were
 * `background: transparent` -- fine against a flat panel, and a hole
 * against one with a gradient, so each gets a faint fill of its own.
 * That is a colour the page did not have, so the rule is: the only
 * selectors allowed a new fill are ones the page declared transparent.
 */
preg_match_all('~(\.[a-z0-9_.-]+)\s*\{\s*background:\s*var\(--msp-ghost\),~i',
	$css, $ghosts, PREG_SET_ORDER);
ok(count($ghosts) >= 2, 'the ghost buttons are not using the quiet ramp');
$opaque = array();
foreach ($ghosts as $g) {
	if (preg_match('~' . preg_quote($g[1], '~') . '[^{]*\{[^}]*background:\s*transparent~s', $pageCode) !== 1) {
		$opaque[] = $g[1];
	}
}
ok(count($opaque) === 0,
   'these were not transparent in missions.php, so the trial repainted a real fill: ' . implode(', ', $opaque));

/*
 * AND THE SAME RULE FOR EVERY OTHER FILL -- buttons, bars, both meter
 * states, both mission-card outcomes. The panel check above only covers
 * the --msp-lift rules; this catches a hex typed straight into this file
 * for anything else. Checked against flexbox.css too, because the
 * buttons' colours and the reward bar's live there, not in missions.php.
 */
$known = $pageCode . "\n" . (string) file_get_contents(__DIR__ . '/dist/flexbox.css');
preg_match_all('~background:\s*var\(--msp-(?!ghost)[a-z-]+\),\s*([^;]+);~i', $css, $fills, PREG_SET_ORDER);
ok(count($fills) >= 14, 'only ' . count($fills) . ' fill rules were found, so this is reading nothing');
/*
 * NAMED, not counted. A count passes with a third of the bars dropped,
 * and the ones easiest to drop are the state variants: the page colours
 * a successful mission's bar purple and a failed one red with selectors
 * MORE specific than the base, so missing them leaves exactly those two
 * flat while every other bar on the page is lit.
 */
$bars = array(
	'.ms-daily-bar .w3-grey-rewards'    => 'the daily reward countdown bar',
	'#mcm-streak .w3-grey-rewards'      => 'the same bar inside the claim reveal',
	'.ms-d-meter i'                     => 'the drawer success meter',
	'.ms-d-meter.sure i'                => 'the meter at 100%, which the page colours differently',
	'.ms-mcard-bar i'                   => 'the mission card time bar',
	'.ms-mcard.success .ms-mcard-bar i' => 'a successful mission bar, coloured separately by the page',
	'.ms-mcard.failure .ms-mcard-bar i' => 'a failed mission bar, coloured separately by the page',
);
foreach ($bars as $sel => $what) {
	/* ANCHORED TO THE START OF ITS OWN RULE. `.ms-mcard-bar i` is a
	   substring of `.ms-mcard.success .ms-mcard-bar i`, so an unanchored
	   search found the base bar inside the success rule and passed with
	   the base rule deleted. */
	ok(preg_match('~(^|\n)\s*' . preg_quote($sel, '~') . '\s*(,[^{}]*)?\{\s*background:\s*var\(--msp-fill\)~', $css) === 1,
	   "$what stays flat while every other bar is lit");
}
$madeup = array();
foreach ($fills as $f) {
	$c = trim($f[1]);
	if (strpos($known, $c) === false) $madeup[] = $c;
}
ok(count($madeup) === 0,
   'these colours exist nowhere on the platform: ' . implode(', ', array_unique($madeup)));

echo "\nthe wells stay flat\n";
/*
 * .ms-mcard-art and friends are #07111d holes cut INTO a panel. Lighting
 * a recess the same way as the surface is what makes a gradient pass
 * look cheap, so they are deliberately untouched.
 */
foreach (array('.ms-mcard-art', '.ms-quest-art', '.ms-d-art', '.ms-d-nft', '.ms-d-item') as $well) {
	ok(strpos($css, $well) === false, "the trial lights $well, which is a recess, not a surface");
}
/*
 * A PROGRESS TRACK IS A GROOVE. The bar FILLS are lit; the rails they
 * run in are not. `.ms-mcard-bar i` is the fill, `.ms-mcard-bar` on its
 * own is the rail, so this looks for the rail without the child.
 */
foreach (array('.ms-mcard-bar', '.ms-d-meter', '.ms-proj-bar') as $rail) {
	ok(preg_match('~' . preg_quote($rail, '~') . '[a-z.]*\s*(,|\{)~', $css) !== 1,
	   "the trial lights the $rail rail, which is a groove, not a fill");
}
/*
 * And .ms-proj-bar is left alone entirely: at 2px the ramp is one light
 * pixel and one dark one, with none of the fill left in between -- and
 * that fill is the only thing separating a part-ready project (#ffcc44)
 * from a full one (#00c8a0).
 */
ok(strpos($css, '.ms-proj-bar') === false,
   'the 2px project bar was given a ramp, which washes out the colour that is its only state');

echo "\nthe override wins on document order, not on force\n";
/*
 * Each rule here matches the specificity of the one it shadows, so the
 * only thing making it win is that it comes later. That holds exactly as
 * long as the include sits after the page's own stylesheet.
 */
$lastStyle = strrpos(substr($pageCode, 0, strpos($pageCode, 'missions-polish.php')), '</style>');
ok($lastStyle !== false,
   'the trial is included before any stylesheet on the page, so none of it applies');
$inc = strpos($pageCode, 'missions-polish.php');
$guest = strpos($pageCode, 'if ($ms_user <= 0)');
ok($guest !== false && $inc < $guest,
   'the trial is included inside a branch, so one of the two views is unstyled');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
