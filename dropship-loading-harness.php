<?php
/**
 * dropship-loading-harness.php — the full-screen loading overlay is gone
 * from Drop Ship, and taking it out did not break the things that used it.
 *
 * Run: php dropship-loading-harness.php
 *
 * The overlay covered the page on every navigation and every form post,
 * which is most of what Drop Ship does, and it was a full-bleed gif rather
 * than progress -- it never said anything the browser's own loading state
 * did not. Removing it is easy; removing it safely is the part worth
 * pinning, because two scripts still call getElementById('loading') and an
 * unguarded one would throw and take the PROJECT PICKER with it.
 */

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}
function src($rel) { return (string)@file_get_contents(__DIR__ . '/' . $rel); }

echo "\nthe overlay renders on a theme change and on nothing else\n";
$hdr = src('dropship/header.php');
ok($hdr !== '', 'dropship/header.php could not be read');
/*
 * IT USED TO RENDER ON NOTHING AT ALL, and that was right while the only
 * loader was dsShowLoader(), built in JavaScript on the page being LEFT.
 * That overlay dies with the document swap, well before the arriving
 * page's art is there -- "it flashes for a second, then the page displays
 * without all the content rendered". The two halves are two documents, so
 * the arriving one paints its own.
 *
 * THE CONDITION IS THE WHOLE POINT. An overlay on every navigation is
 * what made the loader intolerable the first time and got it removed; a
 * theme change is the one request that re-reads the player's holdings.
 */
$ldg_at = strpos($hdr, '<div id="loading"');
ok($ldg_at !== false, 'the arriving page paints no overlay, so the loader still flashes and goes');
$ldg_head = $ldg_at === false ? '' : substr($hdr, 0, $ldg_at);
ok(strpos($ldg_head, '$project_id_changed === "true"') !== false,
   'the overlay is not gated on the theme actually having changed');
ok(strpos($hdr, 'id="loading-image"') !== false, 'the overlay has no image in it');
/* NOT A BLACK SCREEN WITH A BROKEN IMAGE. A theme with no spinner on disk
   gets no overlay. */
ok(strpos($ldg_head, "dropshipThemeArtFile(\$slug, 'images/loading', 'gif')") !== false,
   "the overlay does not resolve the theme's own spinner, or does not check it is there");
ok(preg_match("/\\\$ds_spin !== ''\)\s*\{/", $ldg_head) === 1,
   'a theme with no spinner gets a black overlay with a broken image on it');
/* The header also hands the theme's art folder to JavaScript, which is the
   one thing dsShowLoader() cannot work out for itself -- the reskins nest
   and Drop Ship's own art is flat. */
ok(strpos($hdr, 'data-ds-prefix=') !== false,
   'the header does not expose the art prefix, so the loader points at the wrong theme');
/* THE LIFT ITSELF is driven for real in dropship-loader-lift-harness.js --
   it is the half that can strand a page, so it is executed, not grepped. */
ok(is_file(__DIR__ . '/dropship-loader-lift-harness.js'),
   'the lift script has no harness, so nothing proves the overlay ever comes down');

echo "\nthe spinner is a slot like every other image\n";
require_once __DIR__ . '/dropship/dropship-themes.php';
require_once __DIR__ . '/dropship/dropship-art.php';
foreach (array('gif', 'jpg', 'png') as $e) {
	ok(in_array('images/loading.gif', dropshipArtManifest($e), true),
	   "the spinner is not in the .$e manifest, so there is no slot to upload it to");
}
$lg_where = '';
foreach (dropshipArtGroups('png') as $gn => $gg) {
	if (in_array('images/loading.gif', $gg['files'], true)) $lg_where = $gn;
}
ok($lg_where !== '', 'the spinner is in no art group, so the page never renders it');
ok($lg_where !== 'Everything else',
   'the spinner is in the leftovers bin, which is where nobody found the Discord images');
ok(dropshipArtTitle('images/loading.gif') !== 'Loading',
   'the spinner slot is labelled by its filename');
/* IT STAYS A GIF, BY NAME. dropship.js builds the path itself and a still
   spinner says nothing; it must not start following the theme extension
   the way the round art does. */
ok(!in_array('images/loading.png', dropshipArtManifest('png'), true),
   'the spinner follows the theme extension, so a png theme is asked for a still spinner');
ok(!in_array('images/loading.gif', dropshipThemeGifProbes(), true),
   'the spinner decides whether a theme counts as having animated art');

echo "\nnothing that used it throws without it\n";
/*
 * THIS IS THE ACTUAL RISK. selectProject() shows the overlay and then
 * submits the project form -- unguarded, a null deref there stops the
 * switch happening at all, so removing a cosmetic div would have broken
 * changing theme. connectWallet() has the same shape twice.
 */
foreach (array('dropship/dropship.js', 'dropship/wallet.js') as $f) {
	$js = src($f);
	ok($js !== '', "$f could not be read");
	/* Every getElementById('loading') must be assigned and tested, never
	   dereferenced inline. */
	$inline = preg_match_all("/getElementById\(['\"]loading['\"]\)\s*\.\s*style/", $js);
	ok($inline === 0, "$f dereferences the missing overlay directly ($inline place(s))");
	$assigned = preg_match_all("/=\s*document\.getElementById\(['\"]loading['\"]\)/", $js);
	$guarded  = preg_match_all("/if\s*\(\s*ldg\d*\s*\)/", $js);
	ok($assigned === 0 || $guarded >= $assigned,
	   "$f looks the overlay up $assigned time(s) but guards only $guarded");
}
/* jQuery's .hide() on an empty set is a no-op, so that one needs nothing. */
ok(strpos(src('dropship/dropship.js'), "\$('#loading').hide()") !== false,
   'the jQuery hide-on-load was removed too; it is harmless and costs nothing');

echo "\nthe loader is back, for theme changes and nothing else\n";
/*
 * It covered the screen on every navigation and every form post -- most
 * of what Drop Ship does -- for work that was usually instant. A theme
 * change is the exception: it re-reads the player's holdings and is the
 * one request that routinely takes a second or more.
 *
 * BUILT AT THE MOMENT OF THE SWITCH, not rendered and hidden. There is no
 * element for anything else to show, so it cannot come back everywhere by
 * accident, which is how it got everywhere the first time.
 */
$js3 = src('dropship/dropship.js');
ok(strpos($js3, 'function dsShowLoader(') !== false, 'nothing builds the loader');
ok(strpos($js3, "wrap.id = 'loading';") !== false,
   'the built element does not carry the id the shared stylesheet styles');
ok(strpos($js3, "img.id = 'loading-image';") !== false, 'the loader image is unstyled');
/* The ONLY caller is the theme switch. */
/* CALLS, not occurrences: the definition is `dsShowLoader(){` and matches
   the same substring, so counting those said two and meant one. */
$calls = substr_count($js3, 'dsShowLoader();');
ok($calls === 1, "the loader is shown from $calls places, not just the theme switch");
$sel = strpos($js3, 'function selectProject');
$selEnd = strpos($js3, "\n}", $sel);
ok($sel !== false && strpos(substr($js3, $sel, $selEnd - $sel), 'dsShowLoader()') !== false,
   'the theme switch does not show the loader');
/* ...and it must go up BEFORE the form is submitted, or it never paints. */
$body = substr($js3, $sel, $selEnd - $sel);
ok(strpos($body, 'dsShowLoader()') < strpos($body, 'projectForm'),
   'the loader is shown after the form submits, which is too late to see');
/* Idempotent: a second call must not stack a second overlay. */
ok(strpos($js3, "if (document.getElementById('loading')) return;") !== false,
   'calling it twice stacks overlays');
/* The art is the THEME's, from the prefix the page already knows. */
ok(strpos($js3, "getAttribute('data-ds-prefix')") !== false,
   'the loader does not use the theme art prefix, so a reskin shows Drop Ship\'s loader or a 404');
ok(strpos($js3, "pre + 'images/loading.gif'") !== false, 'the loader art path is wrong');
/* Picking the empty option must not submit anything. */
ok(preg_match('/alert\("Please select a project from the dropdown\."\);\s*\n\s*return;/', $js3) === 1,
   'choosing the blank option alerts and then submits it anyway');

echo "\nthe platform's own overlay is untouched\n";
/*
 * #loading is styled in dist/flexbox.css, which BOTH apps share, and
 * skulliance.js drives its own copy. Deleting the rule to tidy up would
 * have taken the platform's spinner with it.
 */
ok(strpos(src('dist/flexbox.css'), '#loading {') !== false,
   'the shared #loading rule was deleted, which breaks the platform spinner too');
ok(strpos(src('dist/flexbox.css'), '#loading-image {') !== false,
   'the shared #loading-image rule was deleted');
/*
 * AND THE PLATFORM NEVER RENDERED ONE AT ALL. Checking that turned up a
 * separate latent bug: skulliance.js has its own selectProject(), a
 * leftover copy of Drop Ship's, which dereferences #loading -- and no
 * platform page emits that element. Nothing calls the function, so it has
 * never thrown, but it would the moment anybody wired it up. Guarded the
 * same way rather than left as a trap.
 */
$sk = src('skulliance.js');
ok(preg_match("/getElementById\(['\"]loading['\"]\)\s*\.\s*style/", $sk) === 0,
   'skulliance.js dereferences #loading, which no platform page renders');
ok(strpos($sk, 'var ldg = document.getElementById(\'loading\');') !== false,
   'the platform lookup is no longer assigned before use');
$plat_renders = false;
foreach (glob(__DIR__ . '/*.php') as $f) {
	if (strpos((string)@file_get_contents($f), 'id="loading"') !== false
	    && strpos($f, 'harness') === false) { $plat_renders = true; break; }
}
ok(!$plat_renders,
   'a platform page renders #loading after all, so the guard above is hiding a real overlay');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
