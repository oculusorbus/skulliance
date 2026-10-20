<?php
/**
 * dropship-art-harness.php — the theme art manager.
 *
 * Run: php dropship-art-harness.php
 *
 * The two things worth holding: the slot a file is written to can only come
 * out of the manifest, and an animated gif is never flattened to resize it.
 * Both are silent failures otherwise -- one writes where it should not, the
 * other breaks a theme in a way nobody notices until a player says the
 * animations stopped.
 */
require_once __DIR__ . '/dropship/dropship-art.php';
require_once __DIR__ . '/harness-strip.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

echo "\nevery file the run asks for is manageable\n";
/*
 * The groups are DERIVED from the manifest, so a file added to the game
 * appears in the admin without anybody remembering to add it twice. A
 * regrouping that drops one would hide a slot nobody can then upload.
 */
foreach (array('gif', 'png') as $ext) {
	$manifest = dropshipArtManifest($ext);
	$grouped  = array();
	foreach (dropshipArtGroups($ext) as $g) foreach ($g['files'] as $f) $grouped[] = $f;
	sort($manifest); $s = $grouped; sort($s);
	ok($s === $manifest,
	   "[$ext] the groups do not cover the manifest exactly: "
	 . count($grouped) . ' grouped vs ' . count($manifest) . ' expected');
	ok(count($grouped) === count(array_unique($grouped)),
	   "[$ext] a file appears in more than one group");
	/* NOTE on what this does and does not prove: deleting a group entirely is
	   an EQUIVALENT MUTANT, because the "Everything else" catch-all picks the
	   orphans back up and they stay manageable -- only the heading changes.
	   That is the catch-all doing its job, and it is why the assertion above
	   is about coverage rather than about the group list. Mutation-tested and
	   confirmed: removing Equipment moved its 8 files into the catch-all. */
	foreach ($manifest as $rel) {
		ok(substr($rel, 0, 7) === 'images/', "a manifest path escapes the images folder: $rel");
		ok(strpos($rel, '..') === false, "a manifest path contains ..: $rel");
	}
}
/* The extension flows through, or a png theme is offered gif slots. */
$png = dropshipArtManifest('png');
ok(in_array('images/live/beach/1.png', $png, true), 'a png theme is not offered png slots');
ok(!in_array('images/live/beach/1.gif', $png, true), 'a png theme is still offered gif slots');
/* intro.jpg is a jpg whatever the theme is, and must not be rewritten. */
ok(in_array('images/reset/intro.jpg', $png, true), 'intro.jpg was renamed by the extension');

echo "\nthe scale target is derived, not picked\n";
/*
 * .container 1400 -> .main flex 67% = 938 -> minus 20px padding each side =
 * 898 of content. #results pins min-height AND max-height to 898, so the box
 * is square, and #results-image is background-size: contain inside it. Ken
 * Burns then scales the element 1.0 -> 1.2, so the widest the art is ever
 * PAINTED is 898 x 1.2 = 1078.
 *
 * Capping at the pane size instead would look right on the first frame and
 * soften over the next forty seconds, which a pan makes obvious because the
 * viewer stares at one image the whole time.
 */
$css = file_get_contents(__DIR__ . '/dropship/dist/flexbox.css');
ok(strpos($css, 'max-width: 1400px') !== false, 'the container no longer caps at 1400');
ok(preg_match('/\.main\s*\{[^}]*flex:\s*67%/s', $css) === 1, 'the results column is no longer flex 67%');
ok(preg_match('/#results\s*\{[^}]*max-height:\s*898px/s', $css) === 1,
   'the results box is no longer pinned at 898, so the scale target is stale');
ok(strpos($css, 'background-size: contain') !== false,
   'the art is no longer contained in the box, so the derivation does not hold');
ok(strpos($css, 'scale(1.2)') !== false, 'the Ken Burns zoom is no longer 1.2x');
$peak = (int)round(898 * 1.2);
ok(dropshipArtMaxWidth() >= $peak,
   'the max width is ' . dropshipArtMaxWidth() . ' but Ken Burns paints up to ' . $peak
 . ', so the art is upscaled at full zoom');
ok(dropshipArtMaxWidth() <= $peak + 60,
   'the max width is ' . dropshipArtMaxWidth() . ', well past the ' . $peak
 . ' the browser can show; that is load time for pixels nobody sees');
/* SQUARE, because contain fits by whichever side is limiting and the box is
   square -- a portrait still needs the same headroom as a landscape one. */
ok(dropshipArtMaxHeight() === dropshipArtMaxWidth(),
   'the height cap is lower than the width cap, so a tall image is upscaled by the zoom');

echo "\nthe upload slot cannot come from the form\n";
/*
 * THE SHARPEST EDGE HERE. The slot becomes a filesystem path under the theme
 * folder. Taken from the post it would let anything be written anywhere the
 * web user can reach, so it is matched against the manifest first.
 */
$adm = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-art.php'));
ok(strpos($adm, 'in_array($slot, $manifest, true)') !== false,
   'the upload slot is not checked against the manifest, so a posted path is written as given');
$check_at = strpos($adm, 'in_array($slot, $manifest, true)');
$store_at = strpos($adm, 'dropshipArtStore(');
ok($check_at !== false && $store_at !== false && $check_at < $store_at,
   'the slot is used before it is validated');
ok(strpos($adm, 'dropshipThemeAdmin()') !== false, 'the art admin is not gated');
/* And it must refuse to guess a folder when no theme is configured. */
ok(strpos($adm, 'No theme is configured') !== false,
   'the page invents a path for a project with no theme row');

echo "\nan animated gif is never flattened\n";
/*
 * GD reads the first frame and writes a still. A slightly oversized
 * animation costs bandwidth; a flattened one is the theme broken in a way
 * nobody notices until a player mentions the animations stopped. So where
 * Imagick is absent, an animated upload is stored at its original size.
 */
$art = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-art.php'));
ok(strpos($art, 'dropshipArtIsAnimatedGif') !== false, 'nothing detects an animated gif');
ok(strpos($art, "class_exists('Imagick')") !== false, 'Imagick is assumed rather than checked');
ok(preg_match('/\$animated && !class_exists\(.Imagick.\)/', $art) === 1,
   'there is no path that stores an animated gif unresized when Imagick is missing');
ok(strpos($art, 'coalesceImages') !== false,
   'Imagick resizes an animated gif without coalescing, which corrupts the frames');
ok(strpos($art, 'deconstructImages') !== false, 'the resized gif is not deconstructed again');
/* Detection reads the bytes, not the extension: the extension is a claim. */
ok(strpos($art, 'getimagesize') !== false, 'the uploaded file type is taken on trust');

echo "\nthe format has to match the slot\n";
/* The run asks for the slot BY NAME, so a png written into a .gif slot is a
   404 at play time, not a cosmetic problem. */
ok(strpos($art, '$actual !== $want') !== false,
   'a file of the wrong format can be written into a slot');
ok(strpos($art, 'IMAGETYPE_GIF') !== false && strpos($art, 'IMAGETYPE_PNG') !== false,
   'the allowed types are not pinned, so anything readable could be stored');

echo "\nnothing is enlarged, and what fits is left alone\n";
ok(strpos($art, 'min($max_w / $w, $max_h / $h, 1)') !== false,
   'the scale is not clamped at 1, so a small image gets blown up');
ok(strpos($art, '$fits') !== false,
   'an image already within the pane is re-encoded rather than stored as it arrived');

echo "\nevery slot says what it is, in English\n";
/*
 * DERIVED FROM THE PATH, so a file added to the manifest gets a name with
 * nobody maintaining a second table that drifts out of step.
 */
require_once __DIR__ . '/dropship/dropship-themes.php';
require_once __DIR__ . '/dropship/dropship-theme-vocab.php';
foreach (dropshipArtManifest('gif') as $rel) {
	$t = dropshipArtTitle($rel);
	ok($t !== '' && $t !== $rel, "no readable name for $rel");
	ok(strpos($t, 'images/') === false, "the name for $rel is just its path again");
}
/* Numbered stage frames are picked at RANDOM from the set, so frame 3 is not
   round 3. Calling them scenes is the honest reading; naming them after
   rounds would be a lie that reads perfectly plausibly. */
ok(dropshipArtTitle('images/live/beach/3.gif') === 'Beach, scene 3 of 7',
   'the beach frame is misnamed: ' . dropshipArtTitle('images/live/beach/3.gif'));
ok(strpos(dropshipArtTitle('images/live/beach/3.gif'), 'round') === false,
   'a randomly-picked scene is being called a round');
ok(dropshipArtTitle('images/live/hill/exo/2.gif') === 'Hill in an Exo Suit, scene 2 of 2',
   'the exo variant is not distinguished');
ok(dropshipArtTitle('images/weapons/rocket-launcher3.gif') === 'Rocket Launcher, frame 3 of 5',
   'a weapon frame does not say which of how many');
ok(dropshipArtTitle('images/live/beach/beach-secured.gif') === 'Beach cleared',
   'the cleared frame is not distinguished from the scenes');

echo "\nthe art screen shows the wording too\n";
/*
 * A slot called rocket-launcher3 only means something if you know what your
 * theme calls a rocket launcher. Pairing the original with the mapping turns
 * "replace this file" into a decision.
 */
$t = dropshipArtSlotTerms('images/weapons/rocket-launcher3.gif');
$finds = array_column($t, 'find');
ok(in_array('Rocket Launcher', $finds, true), 'a weapon slot does not name its display word');
/* ...but NOT its icon filename. 'rocket-launcher -> strap-on-cannon' sits
   directly under 'Rocket Launcher -> Strap-On Cannon' and says nothing the
   line above has not: the same fact, spelled twice. The icons have their
   own section lower down the page. */
ok(!in_array('rocket-launcher', $finds, true),
   'a weapon slot lists its icon filename as well as its word, which is the same fact twice');
ok(array_column(dropshipArtSlotTerms('images/live/beach/3.gif'), 'find') === array('beach'),
   'a stage frame does not name the place it shows');
ok(in_array('Beach Secured', array_column(dropshipArtSlotTerms('images/live/beach/beach-secured.gif'), 'find'), true),
   'the cleared frame does not name its own label');
ok(dropshipArtSlotTerms('images/1up/1.gif') === array(),
   'a slot with no words invents some');
/* Untouched words must not render as "X -> X" noise. */
$c = new class { public function query($q) { return false; } };
ok(dropshipArtSlotMapping($c, 1, 'images/weapons/rocket-launcher3.gif') === array(),
   'a theme with no overrides still lists mappings');
$adm2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-art.php'));
ok(strpos($adm2, 'dropshipArtTitle($rel)') !== false, 'the page does not show slot names');
ok(strpos($adm2, 'dropshipArtSlotMapping($conn, $pid, $rel)') !== false,
   'the page does not show what the theme calls each thing');

echo "\nan icon sits with the art of the same thing\n";
/*
 * images/weapons/rocket-launcher3.gif and icons/rocket-launcher.png are two
 * pictures of one object. They were managed on opposite halves of this page
 * for no reason but that they live in different folders, so replacing "the
 * rocket launcher" meant finding five frames in one place and the icon in
 * another, below every other icon in the game.
 */
$cz   = new class { public function query($q) { return false; } };
$plan = dropshipArtPlan($cz, 1, 'gif');
$kinds = function ($group) use ($plan) {
	$o = array();
	foreach ($plan[$group]['slots'] as $s) $o[] = $s['kind'] === 'icon' ? $s['rel'] : $s['rel'];
	return $o;
};
ok(isset($plan['Weapons']), 'the plan has no Weapons group');
$w = $kinds('Weapons');
$i = array_search('icons/rocket-launcher.png', $w, true);
ok($i !== false, 'the rocket launcher icon is not in the Weapons group with its frames');
ok($w[$i - 1] === 'images/weapons/rocket-launcher5.gif',
   'the icon does not follow the LAST frame of the thing, so it splits the run');
ok($w[$i + 1] === 'images/weapons/smoke-bomb1.gif',
   'the icon is not immediately before the next thing');
/* 'demo' is 'demolition' typed short. A prefix rule would also pair
   'drop-box' with 'drop-ship', so the alias is declared. */
ok(dropshipArtIconTerm('images/weapons/demo2.gif') === 'demolition',
   'the demo frames are not paired with the demolition icon');
ok(dropshipArtIconTerm('images/equipment/drop-box.gif') === 'drop-box',
   'drop-box art is not paired with its own icon');
ok(dropshipArtIconTerm('images/live/beach/3.gif') === null,
   'a beach frame is treated as a picture of a nameable thing');
ok(dropshipArtIconTerm('images/die/1.gif') === null, 'a death frame is paired with an icon');
/* Every icon must land somewhere, or one quietly becomes unmanageable. */
$placed = array();
foreach ($plan as $g) foreach ($g['slots'] as $sl) if ($sl['kind'] === 'icon') $placed[$sl['term']] = true;
$want = array();
foreach (dropshipThemeIconSlots($cz, 1) as $ic) $want[$ic['term']] = true;
ok(count($want) > 20, 'only ' . count($want) . ' icons were found, so this check proves little');
ok(count($placed) === count($want),
   count($want) - count($placed) . ' icons are in no group at all, so they cannot be replaced');
/* ...and nothing may be listed twice, or two upload forms write one file. */
$seen = array();
foreach ($plan as $gname => $g) foreach ($g['slots'] as $sl) {
	ok(!isset($seen[$sl['rel']]), $sl['rel'] . ' appears in two groups');
	$seen[$sl['rel']] = $gname;
}
/* The icons with no round art of their own still get a home, named for what
   they are rather than dumped in a leftovers bin. */
foreach (array('Armor' => 'icons/heavy-armor.png', 'Identity' => 'icons/supersoldier.png',
               'Items' => 'icons/medkit.png') as $group => $rel) {
	ok(isset($seen[$rel]) && $seen[$rel] === $group,
	   $rel . ' is filed under ' . ($seen[$rel] ?? 'nothing') . ' rather than ' . $group);
}
ok(!isset($plan['Armor']) || count($plan['Armor']['slots']) === 8,
   'the Armor group does not hold all eight worn things');
/* The page must render the one merged list, not the two it replaced. */
$adm3 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-art.php'));
ok(strpos($adm3, 'dropshipArtPlan($conn, $pid, $ext)') !== false,
   'the page does not render the merged plan');
ok(strpos($adm3, '<h3>Icons</h3>') === false,
   'the separate Icons block is back, so an icon is away from the art it belongs with');

echo "\na file already on the server can be renamed, not re-uploaded\n";
/*
 * When the wording changes, the picture the theme wants is usually already
 * on the server under the name it used to ask for. Re-uploading it is
 * busywork and leaves an orphan behind in a folder every theme shares.
 *
 * THE SHARED FOLDER IS THE WHOLE RISK: a rename is not a private edit, and
 * a missing icon is not an error, just a gap nobody notices for a week.
 */
$art3 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-art.php'));
ok(strpos($art3, 'function dropshipArtIconRename') !== false, 'there is no rename at all');
/* Both names rebuilt, never trusted: they become a filesystem path. */
ok(substr_count($art3, 'dropshipThemeSlug($from)') === 1
   && substr_count($art3, 'dropshipThemeSlug($to)') === 1,
   'a posted name reaches the filesystem without being rebuilt');
ok(strpos($art3, 'if (file_exists($dir') !== false,
   'a rename can overwrite an existing icon, silently covering up another theme');
ok(strpos($art3, 'foreach (dropshipThemeAll($conn) as $t)') !== false,
   'a rename does not check whether another theme still asks for that file');
/* The target must be one THIS theme asks for, checked in the page. */
ok(strpos($adm3, "in_array(\$to, \$want, true)") !== false,
   'the rename target comes off the form, so a file could be moved anywhere');
$err = dropshipArtIconRename($cz, 'nurse', 'nurse', 1);
ok($err !== '', 'renaming a file to its own name is allowed');
ok(dropshipArtIconRename($cz, '', 'sexy-nurse', 1) !== '', 'an empty source is allowed');
ok(dropshipArtIconRename($cz, 'does-not-exist-here', 'sexy-nurse', 1) !== '',
   'renaming a file that is not there reports success');
/* Listing the folder is what makes the rename possible without FTP. */
ok(strpos($art3, 'function dropshipArtIconFiles') !== false,
   'nothing lists what is actually in the icons folder');
ok(strpos($adm3, 'dropshipArtIconFiles()') !== false,
   'the page never offers the files already on the server');

echo "\nicons are shared, and named by the wording\n";
/*
 * ONE FOLDER FOR EVERY THEME. The markup asks for icons/<name>.png relative
 * to the page and all four projects are served from /staking/dropship/, so
 * there is no per-theme copy -- a theme renames the thing and ships a file
 * under the new name. Confirmed live: icons/medkit.png is a 200 and
 * oculus-lounge/icons/medkit.png is a 404.
 */
ok(strpos($adm2, 'dropshipThemeIconSlots($conn, $pid)') !== false,
   'the page does not list the icons this theme asks for');
ok(strpos($adm2, "rtrim(__DIR__, '/') . '/'") !== false,
   'an icon is written into the theme folder rather than the shared one');
ok(strpos($adm2, '$icon_names') !== false,
   'an icon upload is not validated against the names this theme asks for');
$art2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-art.php'));
ok(strpos($art2, "preg_match('#^[A-Za-z0-9._-]+\$#', \$name)") !== false,
   'a renamed icon is not checked for being a usable filename');
/* Resolution has to go through the theme, or the list is just Drop Ship's. */
ok(strpos($art2, 'dropshipThemeText($conn, $project_id, $term)') !== false,
   'the icon list is not resolved through the theme, so a rename is invisible');
/* A family can own more than one icon file -- Super Soldier owns both
   dropship.png and supersoldier.png -- so the list iterates them. */
ok(strpos($art2, "foreach (\$r['icons'] as \$term)") !== false,
   'the icon list takes one file per family, so the second file is never listed');
ok(strpos($art2, "'label'  => \$r['label']") !== false,
   "the icon list shows only the filename, so 'heavy-armor' appears with nothing saying it is Heavy");

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
