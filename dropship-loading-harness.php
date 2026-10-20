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

echo "\nDrop Ship no longer renders the overlay\n";
$hdr = src('dropship/header.php');
ok($hdr !== '', 'dropship/header.php could not be read');
ok(strpos($hdr, 'id="loading"') === false,
   'the full-screen loading overlay is back in Drop Ship\'s header');
ok(strpos($hdr, 'id="loading-image"') === false, 'the overlay image is back');
ok(strpos($hdr, 'images/loading.gif') === false, 'the header still loads the spinner gif');

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
