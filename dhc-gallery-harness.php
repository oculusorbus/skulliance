<?php
/* dhc-gallery-harness.php - CLI only. No database.
 *
 * CLICKING A TRAIT IN THE FIGHTER PANEL FILTERS THE COLLECTION TO IT, the same
 * move as clicking a builder's name. Two rules carry it and both live in
 * dhcfighters-lib.php rather than inside dhcgallery.php, so they can be
 * executed here instead of pattern-matched: dhcf_trait_filter() parses the URL
 * and dhcf_traits_include() decides a row.
 *
 * THE WHOLE RISK IS THE NINE DOUBLE SLUGS. dh-alien-punk, golden-cyborg,
 * silver-cyborg, mk100, mk200, c73, dh-cop-2, inferno-wasp-guardian and
 * planet-8tz-specter are each BOTH a head and a torso -- different art,
 * different rarity, separate ledger rows. A filter keyed on the slug alone
 * looks correct on every other trait in the game and silently pools these,
 * so most of these checks are about that.
 *
 * Usage: php dhc-gallery-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/dhcfighters-lib.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }
function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	return preg_replace('~(?<![:/])//.*$~m', '', $s);
}

/* ---------- 1. the double slugs are real, or the rest tests nothing -------- */
$rar = dhcf_rarity();
$both = array();
foreach ($rar as $cat => $rows) {
	foreach ($rows as $slug => $_) {
		foreach ($rar as $cat2 => $rows2) {
			if ($cat2 === $cat) continue;
			if (isset($rows2[$slug])) $both[$slug][$cat] = 1;
		}
	}
}
printf("slugs that exist in more than one category: %d\n", count($both));
ok(count($both) >= 2,
   'no slug exists in two categories any more, so the cat:slug key is untested '
 . '-- if the collection really changed, say so here rather than deleting these checks');
ok(isset($both['mk100']), 'mk100 is no longer both a head and a torso; pick another fixture');

/* ---------- 2. parsing ----------------------------------------------------- */
ok(dhcf_trait_filter('head:mk100') === 'head:mk100', 'a valid pair does not survive parsing');
ok(dhcf_trait_filter('torso:mk100') === 'torso:mk100', 'the torso of the same slug does not parse');
ok(dhcf_trait_filter('HEAD:MK100') === 'head:mk100', 'case is not normalised');
foreach (array('', 'mk100', 'head:', ':mk100', '::', 'head:nope', 'nope:mk100',
               'headgear:../../etc/passwd', 'head:mk100;drop', '<b>:x') as $junk) {
	ok(dhcf_trait_filter($junk) === '',
	   "'" . $junk . "' was accepted as a trait filter; it must fall back to the whole collection");
}
/* A pair that sanitises cleanly but names nothing must be REFUSED, not passed
   through -- an empty grid under a chip reads as "nobody has this". */
ok(dhcf_trait_filter('head:not-a-real-trait') === '',
   'an unknown but well-formed pair is accepted, so the page would show an empty grid '
 . 'under a chip naming a trait that does not exist');

/* ---------- 3. matching ---------------------------------------------------- */
$parts = array(
	array('slot' => 'head',     'cat' => 'head',     'slug' => 'mk100'),
	array('slot' => 'torso',    'cat' => 'torso',    'slug' => 'c73'),
	array('slot' => 'effects2', 'cat' => 'effects',  'slug' => 'electricity'),
	array('slot' => 'weaponBack','cat'=> 'weapon',   'slug' => 'scythe-sash'),
);
ok(dhcf_traits_include($parts, 'head:mk100') === true,  'a worn trait does not match');
ok(dhcf_traits_include($parts, 'torso:mk100') === false,
   'THE TORSO mk100 matched a Fighter wearing the HEAD mk100 -- the filter is keyed on '
 . 'the slug alone and two different traits have been pooled');
ok(dhcf_traits_include($parts, 'head:c73') === false,
   'the head c73 matched a Fighter wearing the TORSO c73');
ok(dhcf_traits_include($parts, 'torso:c73') === true, 'the torso c73 does not match itself');
/* Both effects slots and both weapon slots share a category on purpose: which
   of the two a trait sits in is an assembly detail, not the question. */
ok(dhcf_traits_include($parts, 'effects:electricity') === true,
   'a trait in the SECOND effects slot does not match, so half the collection is invisible '
 . 'to an effects filter');
ok(dhcf_traits_include($parts, 'weapon:scythe-sash') === true,
   'a trait in the weaponBack slot does not match');
ok(dhcf_traits_include($parts, '') === true, 'an empty filter hides rows');
ok(dhcf_traits_include(array(), 'head:mk100') === false, 'a traitless Fighter matched a filter');

/* ---------- 4. the page and the panel are wired to these ------------------- */
$gal = no_comments(file_get_contents(__DIR__ . '/dhcgallery.php'));
ok(strpos($gal, 'dhcf_trait_filter(') !== false,
   'dhcgallery.php no longer parses ?trait= through the shared rule, so this harness '
 . 'is testing a function the page does not call');
ok(strpos($gal, 'dhcf_traits_include(') !== false,
   'dhcgallery.php no longer filters rows through the shared rule');
/* The chip has to be clearable, or a filter is a one-way door. */
ok(preg_match("/dhcg_url\(array\('trait' => ''\)\)/", $gal) === 1,
   'the trait chip has no link that clears it');

$modal = no_comments(file_get_contents(__DIR__ . '/dhc-fighter-modal.php'));
ok(strpos($modal, "'dhcgallery.php?trait=' + encodeURIComponent(p.cat + ':' + p.slug)") !== false,
   'the panel does not build a cat:slug trait link -- either it stopped linking, or it '
 . 'went back to linking by slug alone');
/* The payload it reads from. Both callers build `parts` the same way, and a
   link that reads p.cat would silently produce "undefined:mk100" if either
   stopped emitting it. */
foreach (array(__DIR__ . '/dhcgallery.php', __DIR__ . '/ajax/dhc-fighter.php') as $f) {
	$src = no_comments(file_get_contents($f));
	ok(preg_match("/'cat'\s*=>/", $src) === 1 || strpos($src, "'cat' =>") !== false,
	   basename($f) . " stopped putting 'cat' in each trait, so the panel's links read undefined");
}

/* ---------- 5. the global anchor rules these links sit under --------------- */
/*
 * dist/flexbox.css carries a BARE `a { ... }` -- every anchor on the platform
 * gets its colour and its text-align from it. The trait cell was a <span>
 * until it became a link, so turning it into one inherited both: the trait
 * list shipped CENTRED, which is what the owner reported.
 *
 * Asserted CONDITIONALLY. If that global rule is ever dropped, the guard in
 * the panel becomes unnecessary rather than wrong, and a test demanding it
 * forever would be pinning a workaround to a problem that no longer exists.
 * So this checks the global first and only then requires the counter.
 */
$fbx = @file_get_contents(__DIR__ . '/dist/flexbox.css');
if ($fbx === false) {
	echo "global anchor rules: SKIPPED (dist/flexbox.css not in this checkout)\n";
} else {
	/* The bare `a {` block, not `.navbar a` or any other scoped one. */
	$bare = preg_match('/(^|\})\s*a\s*\{([^}]*)\}/m', $fbx, $m) ? $m[2] : '';
	$centres = stripos($bare, 'text-align') !== false && stripos($bare, 'center') !== false;
	$colours = stripos($bare, 'color') !== false;
	printf("global a{}: text-align:center %s, color %s\n",
		$centres ? 'YES' : 'no', $colours ? 'YES' : 'no');
	if ($centres) {
		ok(preg_match('/#dhcg-traits \.tn\{[^}]*text-align:\s*left/', $modal) === 1,
		   'flexbox.css centres every bare <a> and #dhcg-traits .tn does not set text-align:left, '
		 . 'so the trait list renders centred -- it did exactly that once');
	}
	if ($colours) {
		ok(preg_match('/#dhcg-traits \.tn\{[^}]*color:\s*inherit/', $modal) === 1,
		   'flexbox.css colours every bare <a> and .tn does not re-state a colour, so forty '
		 . 'trait names turn into the link accent and bury the tier chips');
	}
}

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all gallery checks passed\n");
exit($fail ? 1 : 0);
