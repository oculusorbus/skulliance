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

echo "\nthe pane size comes from the stylesheet\n";
/* .container caps at 1400, the results column is flex 67%, #results img caps
   height at 650. Scaling past those is bytes thrown away on every view. */
ok(dropshipArtMaxWidth() > 600 && dropshipArtMaxWidth() <= 1400,
   'the max width is ' . dropshipArtMaxWidth() . ', which is not the results pane');
$css = file_get_contents(__DIR__ . '/dropship/dist/flexbox.css');
ok(strpos($css, 'max-height: 650px') !== false,
   'the stylesheet no longer caps images at 650, so the scale target is stale');

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

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
