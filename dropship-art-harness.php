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
/*
 * NARROWER THAN IT WAS, deliberately. "Animated and no Imagick" used to be
 * enough to store unresized; it now also has to be going to a .gif slot
 * and actually need resizing. Flattening is unavoidable when the target is
 * a still, so refusing to resize there just stored an oversized file for
 * no benefit, and a file that already fits never reaches here at all.
 *
 * A SINGLE-QUOTED LITERAL, not a regex: the first version used a
 * double-quoted pattern, and PHP interpolated $animated and $want out of
 * it before preg_match ever saw them.
 */
ok(strpos($art, '$animated && $want === \'gif\' && !$fits && !class_exists(\'Imagick\')') !== false,
   'there is no path that stores an oversized animated gif unresized when Imagick is missing');
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

echo "\nthe page background is found, not hardcoded\n";
/*
 * THREE WAYS TO GET THIS WRONG AND IT MANAGED TWO. The markup was repeated
 * per project id: header.php handled project 4 only, dashboard.php had a
 * second copy for 3 and 4, and the Filthy Mermaid one read
 * url('/filthy-mermaid/filthymermaidbackground.jpg') -- missing the
 * /staking/dropship prefix every other path on the page carries -- so it
 * 404'd while the file sat on the server. Even fixed, the dashboard copy
 * would have left every other page bare.
 */
ok(dropshipArtBackgroundSlot('') === null, 'a theme with no slug still gets a background path');
$fm = dropshipArtBackgroundSlot('filthy-mermaid');
ok($fm['dir'] === 'filthy-mermaid/', 'a reskin background is not looked for under its own slug');
ok($fm['stem'] === 'filthymermaidbackground', 'the background stem is not the slug with hyphens removed');
/* Drop Ship's art is flat, so its background is too. */
$ds = dropshipArtBackgroundSlot('drop-ship');
ok($ds['dir'] === '', "Drop Ship's background is looked for in a subfolder it does not have");
ok($ds['stem'] === 'dropshipbackground', "Drop Ship's background stem is wrong");
/* The slug is concatenated into a path, so it is rebuilt, never trusted. */
$esc = dropshipArtBackgroundSlot('../../etc');
ok($esc === null || strpos($esc['dir'], '..') === false,
   'a slug can walk out of the dropship folder');

/*
 * DRIVEN FOR REAL against a throwaway theme folder, because the extension
 * genuinely varies -- Oculus Lounge is .png, Filthy Mermaid is .jpg -- so
 * the file is FOUND rather than assumed, and that is the part worth
 * testing rather than asserting.
 */
$tdir = __DIR__ . '/dropship/harness-bg-test';
$made = @mkdir($tdir);
if ($made) {
	ok(dropshipThemeBackground('harness-bg-test') === '',
	   'a theme with no background file still returns a url, which renders as a broken one');
	file_put_contents($tdir . '/harnessbgtestbackground.png', 'x');
	ok(dropshipThemeBackground('harness-bg-test') === '/staking/dropship/harness-bg-test/harnessbgtestbackground.png',
	   'a .png background is not found: ' . var_export(dropshipThemeBackground('harness-bg-test'), true));
	@unlink($tdir . '/harnessbgtestbackground.png');
	file_put_contents($tdir . '/harnessbgtestbackground.jpg', 'x');
	ok(dropshipThemeBackground('harness-bg-test') === '/staking/dropship/harness-bg-test/harnessbgtestbackground.jpg',
	   'a .jpg background is not found, so the extension is being assumed');
	/* It must carry the prefix. That one missing segment is the whole bug. */
	ok(strpos(dropshipThemeBackground('harness-bg-test'), '/staking/dropship/') === 0,
	   'the background url is missing the /staking/dropship prefix, exactly as before');
	/* A file that is not an image must not be picked up as one. */
	@unlink($tdir . '/harnessbgtestbackground.jpg');
	file_put_contents($tdir . '/harnessbgtestbackground.txt', 'x');
	ok(dropshipThemeBackground('harness-bg-test') === '',
	   'a non-image is served as the page background');
	@unlink($tdir . '/harnessbgtestbackground.txt');
	@rmdir($tdir);
	ok(!is_dir($tdir), 'the harness left its throwaway theme folder behind');
} else {
	ok(false, 'could not create a throwaway theme folder, so the lookup was never driven');
}

/* The plan must actually carry it, or there is no upload control at all. */
$bgplan = dropshipArtPlan($cz, 1, 'gif', 'filthy-mermaid');
ok(isset($bgplan['The page background']), 'the art page has no background slot');
ok(array_key_first($bgplan) === 'The page background',
   'the background is not the first thing on the page, though it is the first thing seen');
$bgslots = $bgplan['The page background']['rows'][0]['slots'];
ok(count($bgslots) === 1 && $bgslots[0]['kind'] === 'bg',
   'the background row does not hold exactly one background slot');
ok($bgslots[0]['rel'] === 'filthy-mermaid/filthymermaidbackground.jpg',
   'the background slot points somewhere unexpected: ' . $bgslots[0]['rel']);
/* A theme with no slug gets no background group rather than a broken one. */
ok(!isset(dropshipArtPlan($cz, 1, 'gif', '')['The page background']),
   'a theme with no slug still gets a background slot');
/* ONE copy of the flat-vs-nested rule. Two functions decided it
   independently, and a mutation breaking one left the other passing. */
$th3 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-themes.php'));
$art5 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-art.php'));
/*
 * ONE DEFINITION of "Drop Ship is flat, every reskin nests". It had reached
 * four copies -- the background lookup, the background slot, the gif probe
 * and dropshipArtRoot() -- and a mutation breaking one left the rest
 * passing, which is how the duplication surfaced.
 */
ok(substr_count($th3 . $art5, "'drop-ship' ? '' :") === 1,
   'the flat-vs-nested rule is written in more than one place again');
ok(strpos($th3, 'function dropshipThemeRoot') !== false,
   'the shared root helper is gone, so every caller decides for itself again');
ok(strpos($art5, 'function dropshipArtBackgroundSlot') === false,
   'the slot builder is back in dropship-art.php, which the game pages do not load');
/* dropshipArtRoot() must keep returning '' rather than null, because it is
   concatenated straight into paths by callers that never checked. */
ok(dropshipArtRoot('') === '', 'dropshipArtRoot() stopped returning a string for an unknown theme');

/* It is set ONCE, in the header every page includes. */
$hdr2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/header.php'));
ok(strpos($hdr2, 'dropshipThemeBackground($slug)') !== false,
   'the header does not look the background up, so only hardcoded themes get one');
ok(strpos($hdr2, 'oculusloungebackground') === false,
   'the header still hardcodes one theme\'s background');
$dash5 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash5, 'backgroundImage') === false
   || strpos($dash5, 'results-image') !== false,
   'the dashboard sets the page background again, so it differs from every other page');
ok(strpos($dash5, 'filthymermaidbackground') === false,
   'the dashboard still hardcodes the Filthy Mermaid background');
/* $slug has to exist by the time the header runs, on EVERY page. */
foreach (glob(__DIR__ . '/dropship/*.php') as $page) {
	$psrc = (string)file_get_contents($page);
	$at_hdr = strpos($psrc, "include 'header.php'");
	if ($at_hdr === false) continue;
	$at_ds = strpos($psrc, "include 'dropship.php'");
	ok($at_ds !== false && $at_ds < $at_hdr,
	   basename($page) . ' includes header.php before dropship.php, so $slug is undefined there');
}

/* A background is scaled to the VIEWPORT, not to a pane. */
ok(dropshipArtMaxBackgroundWidth() > dropshipArtMaxWidth(),
   'the background is capped at the round-art size, which is visibly soft full-screen');
$art4 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-art.php'));
ok(strpos($art4, 'function dropshipArtStore($tmp_path, $dest_abs, $declared_ext, $max_w = 0, $max_h = 0)') !== false,
   'the store cannot be given a different maximum, so the background shares the pane cap');
ok(strpos($art4, 'if ($max_w <= 0) $max_w = dropshipArtMaxWidth();') !== false,
   'omitting the maximum no longer falls back to the pane-derived default');
/* One background per theme: the old extension has to go, or the glob picks
   between a .jpg and a .png by alphabet. */
ok(strpos($art4, 'if (realpath($old) !== realpath($dest)) @unlink($old);') !== false,
   'replacing a background with a different extension leaves both files behind');
$adm5 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-art.php'));
ok(strpos($adm5, 'dropshipArtStoreBackground($_FILES[\'art\'][\'tmp_name\'], $slug, $up_ext)') !== false,
   'the art admin cannot replace the background');
ok(strpos($adm5, '$is_bg   = ($bg_slot && $slot === $bg_slot[\'rel\']);') !== false,
   'the background is not recognised as a valid upload slot');

echo "\nthe base game's slug maps to the flat folder\n";
/*
 * Project 1 is seeded with slug 'drop-ship' so the base game can be
 * reskinned and its files managed here. That only works if the slug maps to
 * the FLAT folder -- Drop Ship's own art is not nested, every other theme's
 * is -- or the admin would write into dropship/drop-ship/ and the game
 * would keep reading from dropship/.
 */
ok(dropshipArtRoot('drop-ship') === '',
   "the 'drop-ship' slug no longer maps to the flat art folder");
ok(dropshipArtRoot('oculus-lounge') === 'oculus-lounge/',
   'a reskin no longer nests under its own slug');
$seedsrc = file_get_contents(__DIR__ . '/dropship/dropship-themes-seed.sql');
ok(strpos($seedsrc, "(1, 'drop-ship', 1, 'gif'") !== false,
   'the seed does not give project 1 the slug the flat mapping needs');

class TConnArt {
	public $said;
	public function __construct($said) { $this->said = $said; }
	public function query($sql) {
		if (strpos($sql, 'SHOW TABLES') !== false) return new TResArt(array(array('t' => 'x')));
		if (strpos($sql, 'FROM themes WHERE project_id') !== false)
			return new TResArt(array(array('id' => 9, 'project_id' => 55, 'slug' => 'z')));
		if (strpos($sql, 'FROM theme_text') !== false) {
			$rows = array();
			foreach ($this->said as $find => $rep) {
				$rows[] = array('match_type' => 'exact', 'find' => $find, 'replacement' => $rep);
			}
			return new TResArt($rows);
		}
		return new TResArt(array());
	}
}
class TResArt {
	public $num_rows; private $r; private $i = 0;
	public function __construct($r) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return $this->i < count($this->r) ? $this->r[$this->i++] : null; }
}

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
/* Flatten a group to its slot paths, in render order. */
$flatg = function ($group) use ($plan) {
	$o = array();
	foreach ($plan[$group]['rows'] as $r) foreach ($r['slots'] as $sl) $o[] = $sl['rel'];
	return $o;
};
$rowsBy = function ($group) use ($plan) {
	$o = array();
	foreach ($plan[$group]['rows'] as $r) $o[$r['label']] = $r;
	return $o;
};
ok(isset($plan['Weapons']), 'the plan has no Weapons group');
/*
 * ONE ROW PER THING. Five frames and an icon flowed into a single grid sat
 * wherever the column count put them, split across lines, with nothing
 * saying the six were one object. A reskin is decided one object at a time.
 */
$wr = $rowsBy('Weapons');
ok(isset($wr['Rocket Launcher']), 'the rocket launcher has no row of its own');
ok(array_column($wr['Rocket Launcher']['slots'], 'rel') === array(
	'images/weapons/rocket-launcher1.gif', 'images/weapons/rocket-launcher2.gif',
	'images/weapons/rocket-launcher3.gif', 'images/weapons/rocket-launcher4.gif',
	'images/weapons/rocket-launcher5.gif', 'icons/rocket-launcher.png'),
   'the rocket launcher row is not its five frames then its icon');
ok(count($wr) === 9, 'the Weapons group has ' . count($wr) . ' rows, not one per weapon');
foreach ($wr as $label => $r) {
	ok($label !== '', 'a weapon is in an unnamed band rather than a row of its own');
	$last = end($r['slots']);
	ok($last['kind'] === 'icon', "the $label row does not end with its icon");
}
/* 'demo' is 'demolition' typed short. A prefix rule would also pair
   'drop-box' with 'drop-ship', so the alias is declared. */
ok(dropshipArtIconTerm('images/weapons/demo2.gif') === 'demolition',
   'the demo frames are not paired with the demolition icon');
ok(isset($wr['Demolition']) && count($wr['Demolition']['slots']) === 3,
   'the two demo frames and the demolition icon are not one row');
ok(dropshipArtIconTerm('images/equipment/drop-box.gif') === 'drop-box',
   'drop-box art is not paired with its own icon');
ok(dropshipArtIconTerm('images/live/beach/3.gif') === null,
   'a beach frame is treated as a picture of a nameable thing');
ok(dropshipArtIconTerm('images/die/1.gif') === null, 'a death frame is paired with an icon');
/* Art that is not a picture of a nameable thing stays one unnamed band, so
   it renders as the plain grid it always was. */
$br = $rowsBy('The beach');
ok(count($br) === 1 && isset($br['']), 'the beach frames were split into named rows');
ok(count($br['']['slots']) === 10, 'the beach band lost frames');
/* A thing with TWO icon files keeps them in ONE row: dropship.png and
   supersoldier.png are two pictures of the same squad mark. */
$ir = $rowsBy('Identity');
ok(count($ir) === 1, 'Identity has ' . count($ir) . ' rows; the two squad marks are one thing');
ok(isset($ir['Super Soldier']) && count($ir['Super Soldier']['slots']) === 2,
   'the two Super Soldier icons are not in one row');
/* The row says what the theme calls the thing, so the header carries the
   mapping instead of every card repeating it. */
$ct2 = new TConnArt(array('Rocket Launcher' => 'Strap-On Cannon'));
$p2  = dropshipArtPlan($ct2, 55, 'gif');
$r2  = array();
foreach ($p2['Weapons']['rows'] as $r) $r2[$r['label']] = $r['said'];
ok(($r2['Rocket Launcher'] ?? '') === 'Strap-On Cannon',
   'the row does not say what this theme calls the thing');
ok(($r2['Grenade'] ?? 'x') === '',
   'a thing the theme has not renamed still shows a mapping, which is noise');
/* Every icon must land somewhere, or one quietly becomes unmanageable. */
$placed = array();
foreach ($plan as $g) foreach ($g['rows'] as $r) foreach ($r['slots'] as $sl) {
	if ($sl['kind'] === 'icon') $placed[$sl['term']] = true;
}
$want = array();
foreach (dropshipThemeIconSlots($cz, 1) as $ic) $want[$ic['term']] = true;
ok(count($want) > 20, 'only ' . count($want) . ' icons were found, so this check proves little');
ok(count($placed) === count($want),
   count($want) - count($placed) . ' icons are in no row at all, so they cannot be replaced');
/* ...and nothing may be listed twice, or two upload forms write one file. */
$seen = array();
foreach ($plan as $gname => $g) foreach ($g['rows'] as $r) foreach ($r['slots'] as $sl) {
	ok(!isset($seen[$sl['rel']]), $sl['rel'] . ' appears twice');
	$seen[$sl['rel']] = $gname;
}
/* The icons with no round art of their own still get a home, named for what
   they are rather than dumped in a leftovers bin. */
foreach (array('Armor' => 'icons/heavy-armor.png', 'Identity' => 'icons/supersoldier.png',
               'Items' => 'icons/medkit.png') as $group => $rel) {
	ok(isset($seen[$rel]) && $seen[$rel] === $group,
	   $rel . ' is filed under ' . ($seen[$rel] ?? 'nothing') . ' rather than ' . $group);
}
ok(count($rowsBy('Armor')) === 8, 'the Armor group does not hold all eight worn things');

/* The page must render the one merged list, not the two it replaced. */
$adm3 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-art.php'));
ok(strpos($adm3, 'dropshipArtPlan($conn, $pid, $ext, $slug)') !== false,
   'the page does not render the merged plan');
ok(strpos($adm3, '<h3>Icons</h3>') === false,
   'the separate Icons block is back, so an icon is away from the art it belongs with');

echo "\nan upload lands in place, without reloading the page\n";
/*
 * Replacing art is a dozen small edits in a row. A full reload for each one
 * threw away the scroll position and flashed a loading bar, so after every
 * upload you had to find your way back to the thing you were working on.
 */
$adm4 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-art.php'));
ok(strpos($adm4, "\$dsa_inplace = (\$_SERVER['REQUEST_METHOD'] === 'POST' && !empty(\$_POST['ajax']))") !== false,
   'nothing distinguishes an in-place upload from an ordinary post');
/*
 * BUFFERED FROM BEFORE header.php, not from the handler. header.php prints
 * the whole page head and nav the moment it is included, so by the time the
 * upload runs the response is already most of a web page -- and
 * display_errors is on platform-wide, so a single notice on the way would
 * make the JSON unparseable and read as "uploads stopped working".
 */
$ob = strpos($adm4, 'if ($dsa_inplace) ob_start();');
ok($ob !== false, 'the in-place response is never buffered');
/* BEFORE THE FIRST INCLUDE, not merely before header.php. db.php and
   dropship.php run first and display_errors is on, so a notice from either
   escapes a buffer opened after them and corrupts the JSON just as surely. */
ok($ob < strpos($adm4, "include 'db.php'"),
   'buffering starts after the includes have had a chance to print');
ok(strpos($adm4, 'while (ob_get_level()) ob_end_clean();') !== false,
   'the captured page is not discarded, so it is prepended to the JSON');
ok(substr_count($adm4, 'while (ob_get_level()) ob_end_clean();') === 2,
   'only one of the two in-place exits discards the buffer');
ok(strpos($adm4, "header('Content-Type: application/json')") !== false,
   'the in-place reply is not declared as JSON');
/* dsaArtClose() prints the page footer, so it must not run on this path. */
/* The TOP-LEVEL block, matched with its leading newline: the admin gate has
   an indented `if ($dsa_inplace) {` of its own a hundred lines earlier, and
   a bare search found that one and then ran past its closing brace into the
   HTML branch -- so this check was reading the wrong code entirely. */
$jsonblk = substr($adm4, strpos($adm4, "\nif (\$dsa_inplace) {\n") + 1);
$jsonblk = substr($jsonblk, 0, strpos($jsonblk, "\n}\n"));
ok(strpos($jsonblk, "'Content-Type: application/json'") !== false,
   'the block being checked is not the in-place reply');
ok(strpos($jsonblk, 'dsaArtClose') === false,
   'the in-place reply appends the page footer to its own JSON');
ok(strpos($jsonblk, 'if (is_object($conn)) $conn->close();') !== false,
   'the in-place reply leaves the connection open');
/* A refused upload must answer in the shape the page waits for, or the
   card silently never updates. */
ok(strpos($adm4, "echo json_encode(array('ok' => false, 'note' => 'Not yours to edit.'))") !== false,
   'a refused in-place upload answers with HTML, so the page just hangs on it');
/*
 * THE HANDLER IS UNCHANGED. This only decides how the result is phrased --
 * same validation, same slot-from-the-manifest rule -- and the forms are
 * ordinary POSTs that still work with the script absent.
 */
ok(strpos($adm4, '$valid = $is_bg || in_array($slot, $manifest, true) || in_array($slot, $icon_names, true);') !== false,
   'the in-place path skipped the slot validation');
/* The background is a slot like any other, so it must be validated the
   same way rather than trusted because it came from a different branch. */
ok(strpos($adm4, "\$is_bg   = (\$bg_slot && \$slot === \$bg_slot['rel']);") !== false,
   'the background upload target is taken from the form rather than computed');
ok(strpos($adm4, 'ev.preventDefault()') !== false, 'the script never intercepts the submit');
ok(strpos($adm4, "data.append('ajax', '1')") !== false,
   'the script posts without the flag, so it gets a whole page back');
ok(strpos($adm4, 'if (!root || !window.FormData || !window.fetch) return;') !== false,
   'the script does not stand aside on a browser that cannot do this');
ok(strpos($adm4, 'method: \'POST\'') !== false && strpos($adm4, "credentials: 'same-origin'") !== false,
   'the in-place post would not carry the session cookie');
/* The new src must bust the cache, or the old picture stays on screen and
   the upload looks like it did nothing. */
ok(strpos($adm4, "\$rel . '?v=' . (\$here ? @filemtime(\$abs) : 0)") !== false,
   'the replacement image is served without a cache buster');
/* An icon is written beside the page, not inside the theme folder, and the
   reply has to say so or the card points at a path that does not exist. */
ok(strpos($adm4, "\$aico = strpos(\$rel, 'icons/') === 0;") !== false,
   'the in-place reply does not tell an icon from a theme file');
ok(strpos($adm4, "(\$aico ? '/staking/dropship/' : \$aweb)") !== false,
   'the in-place reply builds one url for both icons and theme art');

echo "\nboth sets of a theme's art can be managed here\n";
/*
 * A still theme ships TWO sets: the one its extension names, and the gif
 * set the player's Animation switch turns on. This page could only ever
 * see the first, so the second had to be FTPed by hand -- which is the
 * gap the whole panel exists to close.
 */
$adm7 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-art.php'));
ok(strpos($adm7, "\$theme_ext = \$theme && !empty(\$theme['extension'])") !== false,
   'the page no longer remembers which format is the theme\'s own');
/* NEVER taken off the query string as-is: $ext builds the manifest, which
   becomes filesystem paths. Only the two real answers. */
ok(strpos($adm7, "if (isset(\$_GET['format']) && \$_GET['format'] === 'gif') \$ext = 'gif';") !== false,
   'the format is read from the query string without being checked');
ok(strpos($adm7, '$ext = $_GET') === false && strpos($adm7, '$ext = $_POST') === false,
   'the format is assigned straight from the request');
/* A gif theme has nothing to switch to. */
ok(strpos($adm7, "\$can_switch = (\$theme_ext !== 'gif');") !== false,
   'the switch is offered on a gif theme, where both sets would be one');
ok(strpos($adm7, 'if (!$can_switch) $ext = $theme_ext;') !== false,
   'a gif theme can still be forced onto another set by hand');
/*
 * IT HAS TO WORK WHEN THE SET IS EMPTY, unlike the player's switch, which
 * is gated on the files existing. Otherwise there is no way to create the
 * second set in the first place -- and the count is shown on the tab so
 * the switch says what it leads to rather than making somebody click.
 */
ok(strpos($adm7, 'dropshipThemeHasGifArt') === false,
   'the admin switch is gated on the gifs existing, so an empty set cannot be built');
ok(strpos($adm7, '$o_man = dropshipArtManifest($other);') !== false,
   'the other set is not counted, so the switch does not say what it leads to');
/* The chosen format must survive every round trip, or an upload lands in
   the set you were not looking at. */
ok(substr_count($adm7, 'name="format"') === 2,
   'the upload and rename forms do not carry the format, so uploads land in the wrong set');
ok(strpos($adm7, 'format=<?php echo $h($ext); ?>') !== false
   || strpos($adm7, 'format=') !== false,
   'the form actions drop the format');
ok(strpos($adm7, "if (isset(\$_POST['format']) && \$_POST['format'] === 'gif') \$ext = 'gif';") !== false,
   'a posted format is ignored, so an in-place upload reverts to the default set');
/* The manifest and the plan both have to follow the chosen format. */
ok(strpos($adm7, 'dropshipArtPlan($conn, $pid, $ext, $slug)') !== false,
   'the slot list ignores the chosen format');
ok(substr_count($adm7, 'dropshipArtManifest($ext)') === 2,
   'the upload validator and the status bar disagree about which set is being managed');

echo "\na file in the wrong format is converted, not refused\n";
/*
 * The run asks for its art by filename, extension included --
 * images/weapons/rocket-launcher1.gif -- so whatever arrives has to end up
 * in that format. Refusing a mismatch meant exporting again and coming
 * back, for a conversion the server can do. DRIVEN FOR REAL through GD,
 * which is what this box has; the Imagick branch is asserted on source.
 */
$tmpd = sys_get_temp_dir() . '/dsart-' . getmypid();
@mkdir($tmpd, 0777, true);
$mk = function ($fmt, $w = 40, $h = 30, $alpha = false) use ($tmpd) {
	$im = imagecreatetruecolor($w, $h);
	if ($alpha) {
		imagealphablending($im, false); imagesavealpha($im, true);
		imagefill($im, 0, 0, imagecolorallocatealpha($im, 255, 0, 0, 127));
	} else {
		imagefill($im, 0, 0, imagecolorallocate($im, 10, 200, 160));
	}
	$f = $tmpd . '/in.' . $fmt;
	if ($fmt === 'png') imagepng($im, $f);
	if ($fmt === 'jpg') imagejpeg($im, $f, 90);
	if ($fmt === 'gif') imagegif($im, $f);
	imagedestroy($im);
	return $f;
};
$typeof = function ($f) {
	$i = @getimagesize($f);
	$map = array(IMAGETYPE_GIF => 'gif', IMAGETYPE_PNG => 'png', IMAGETYPE_JPEG => 'jpg', IMAGETYPE_WEBP => 'webp');
	return ($i && isset($map[$i[2]])) ? $map[$i[2]] : 'unreadable';
};
foreach (array(array('png', 'jpg'), array('jpg', 'png'), array('png', 'gif'),
               array('gif', 'png'), array('jpg', 'gif')) as $pair) {
	list($from, $to) = $pair;
	$src = $mk($from);
	$dst = $tmpd . '/out.' . $to;
	@unlink($dst);
	list($ok, $why) = dropshipArtStore($src, $dst, $to);
	ok($ok, "a $from on a .$to slot was refused: " . $why);
	ok($typeof($dst) === $to,
	   "a $from written to a .$to slot is actually a " . $typeof($dst) . ', so the run 404s on it');
	ok(strpos($why, "converted $from to $to") !== false,
	   "the result does not say the $from was converted: " . $why);
	@unlink($dst);
}
/* A file ALREADY in the slot's format is left alone, not re-encoded. */
$same = $mk('png');
$dst = $tmpd . '/out.png';
list($ok, $why) = dropshipArtStore($same, $dst, 'png');
ok($ok && strpos($why, 'converted') === false,
   'a file already in the right format is reported as converted: ' . $why);
@unlink($dst);
/*
 * TRANSPARENCY HAS TO LAND ON SOMETHING. A png with alpha encoded as jpeg
 * renders every transparent pixel BLACK unless it is composited first --
 * and onto the page's own dark ground, because white would ring every
 * edge on a dark page.
 */
$tr = $mk('png', 20, 20, true);
$dst = $tmpd . '/alpha.jpg';
list($ok, $why) = dropshipArtStore($tr, $dst, 'jpg');
ok($ok, 'a transparent png could not be converted to jpg: ' . $why);
$im = @imagecreatefromjpeg($dst);
ok($im !== false, 'the converted jpg is unreadable');
if ($im) {
	$rgb = imagecolorat($im, 2, 2);
	$r = ($rgb >> 16) & 0xFF; $g = ($rgb >> 8) & 0xFF; $b = $rgb & 0xFF;
	/* #07111d, within jpeg's own slop. */
	ok(abs($r - 0x07) < 12 && abs($g - 0x11) < 12 && abs($b - 0x1d) < 12,
	   sprintf('transparency flattened to #%02x%02x%02x, not the page ground #07111d', $r, $g, $b));
	imagedestroy($im);
}
@unlink($dst);
/*
 * A slot extension that is not an image format is refused UP FRONT and
 * says why. Without the guard nothing is written either way -- no encoder
 * matches, so the outcome is the same -- but the message degrades from
 * naming the four formats to "Could not write x.php", which reads as a
 * permissions problem and sends somebody chasing the wrong thing.
 */
list($ok, $why) = dropshipArtStore($mk('png'), $tmpd . '/x.php', 'php');
ok(!$ok, 'a .php slot was accepted');
ok(strpos($why, 'gif, png, jpg or webp') !== false,
   'a bad slot extension is not explained, it just fails to write: ' . $why);
ok(!file_exists($tmpd . '/x.php'), 'a file was written for a non-image slot extension');
/* Clean up after ourselves. */
foreach ((array)@glob($tmpd . '/*') as $f) @unlink($f);
@rmdir($tmpd);
ok(!is_dir($tmpd), 'the harness left its temp files behind');

/* The lossy cases are named rather than done quietly. */
$art6 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-art.php'));
ok(strpos($art6, "its animation was lost, because this slot is a .") !== false,
   'flattening an animated gif onto a still slot is not reported');
ok(strpos($art6, 'gif is 256 colours') !== false,
   'converting a photograph to gif is not reported');
/* Imagick must not write one file per frame when the target is a still. */
ok(strpos($art6, "if (\$animated && \$want === 'gif') {") !== false,
   'Imagick keeps the frames whatever the target is, which writes png-0, png-1, ...');
ok(strpos($art6, "\$im->setImageFormat(\$want === 'jpg' ? 'jpeg' : \$want);") !== false,
   'Imagick writes the source format regardless of the slot');
ok(strpos($art6, "new ImagickPixel('#07111d')") !== false,
   'Imagick flattens alpha onto the default white rather than the page ground');
/* GD must key its alpha handling on the TARGET, not the source. */
ok(strpos($art6, "if (\$want === 'jpg') {") !== false,
   'GD decides alpha handling from the source format, so a png on a jpg slot goes black');
ok(strpos($art6, 'if ($want === \'png\')  $ok = imagepng($dst, $dest_abs);') !== false,
   'GD encodes using the source format rather than the slot format');
/* A move is only valid when nothing has to change. */
ok(strpos($art6, 'if ($fits && !$convert) {') !== false,
   'a file that fits is moved without re-encoding even when the format is wrong');

echo "\ndragging a file onto a slot replaces it\n";
/*
 * Seventy images per theme, each needing pick-then-press. Dropping onto
 * the slot is one action, and it posts through the SAME send() as the
 * button -- same validation, same slot-from-the-manifest rule, same
 * in-place patch -- rather than a second upload path that could drift.
 */
$adm6 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-art.php'));
ok(substr_count($adm6, 'function send(form, dropped)') === 1,
   'there is no single send(), so drop and button can diverge');
ok(substr_count($adm6, "data.append('ajax', '1')") === 1,
   'more than one upload path posts to the server');
ok(strpos($adm6, "if (dropped) data.set('art', dropped);") !== false,
   'a dropped file does not replace the form\'s own input, so the drop posts nothing');
/* set(), not append(): appending leaves the empty file input in the body
   and the server reads whichever came first. */
ok(strpos($adm6, "data.append('art'") === false,
   'the dropped file is appended beside the empty input rather than replacing it');
foreach (array('dragover', 'dragleave', 'drop') as $ev) {
	ok(strpos($adm6, "root.addEventListener('" . $ev . "'") !== false,
	   "slots do not handle $ev, so a drop does nothing");
}
/*
 * dragover MUST preventDefault or the drop event never fires at all.
 *
 * ANCHORED ON THE SLOT HANDLER. A loose search for "dragover" near a
 * preventDefault matched the WINDOW handler further down, which has one
 * for a different reason, so removing it from the slot handler passed.
 */
$slot_dragover = strpos($adm6, "root.addEventListener('dragover'");
ok($slot_dragover !== false, 'the slot dragover handler is gone');
$body = substr($adm6, $slot_dragover, strpos($adm6, '});', $slot_dragover) - $slot_dragover);
ok(strpos($body, 'ev.preventDefault()') !== false,
   'the slot dragover does not preventDefault, so drop never fires on a card');
ok(strpos($body, "dropEffect = 'copy'") !== false,
   'the cursor does not say a drop will copy, so it reads as rejected');
/*
 * AND THE REST OF THE PAGE MUST SWALLOW DROPS TOO. A file dropped slightly
 * off a card is otherwise OPENED by the browser, which throws the page
 * away mid-edit.
 */
ok(strpos($adm6, "window.addEventListener('drop'") !== false,
   'a file dropped off-target navigates away and loses the page');
ok(strpos($adm6, "window.addEventListener('dragover'") !== false,
   'the page does not suppress the browser default for an off-target drag');
/* A card with no upload form is one the page refuses to write to. */
ok(strpos($adm6, "return slot.querySelector('input[type=file]') ? slot : null;") !== false,
   'a slot with no upload form still accepts a drop it cannot act on');
/* One slot is one picture; several files is a mistake worth naming. */
ok(strpos($adm6, "say(slot, 'one file per slot, please', false)") !== false,
   'dropping several files silently takes one and discards the rest');
ok(strpos($adm6, "files[0].type.indexOf('image/') !== 0") !== false,
   'a non-image is posted to the server rather than refused up front');
/* Picking a file uploads it: two actions for one decision, seventy times. */
ok(strpos($adm6, "root.addEventListener('change'") !== false,
   'choosing a file no longer uploads it');
ok(strpos($adm6, "if (!input || input.type !== 'file' || !input.value) return;") !== false,
   'the change handler fires for every control on the page, not just file inputs');
/* The submit listener must still work -- the button is not going away. */
ok(strpos($adm6, "root.addEventListener('submit'") !== false, 'the Upload button stopped working');
/* The highlight must not move the grid, or every card shifts as you drag
   across it. */
$css = (string)file_get_contents(__DIR__ . '/dropship/admin-art.php');
ok(strpos($css, 'outline-offset:-4px') !== false,
   'the drop highlight is drawn outside the border, so the grid jumps as you drag');
ok(strpos($css, '.dsart .slot.over *{pointer-events:none}') !== false,
   'children of a card swallow the drag events, so the highlight flickers');

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


/* ------------------------------------------------------------------ */
/*
 * A PRESENT FILE IS NOT A THEMED ONE.
 *
 * Every reskin's folder was seeded by copying Drop Ship's, so the art page
 * counted a slot as done whether it held this theme's picture or the
 * original game's. Reported from Discord: a Dread City run posted Drop
 * Ship's animated death. The url was right -- dread-city/images/die/1.gif
 * -- and the file was there; it is byte-for-byte dropship/images/die/1.gif.
 * Dread City's own art is the .jpg set beside it, and its whole .gif set
 * is Drop Ship's.
 */
echo "\nart that is still Drop Ship's is counted as such\n";

$cp_tag  = 'harness-copy-' . getmypid();
$cp_rel  = 'images/' . $cp_tag . '.bin';          /* a name no theme asks for */
$cp_slug = $cp_tag . '-theme';
$cp_own  = __DIR__ . '/dropship/' . $cp_rel;
$cp_dir  = __DIR__ . '/dropship/' . $cp_slug;
$cp_them = $cp_dir . '/' . $cp_rel;

/* UNIQUELY NAMED AND REMOVED AFTER. dropship/images/ is the live art
   folder on the server; a probe file in it must be impossible to confuse
   with art and must not survive the run. */
$cp_ok = @mkdir(dirname($cp_own), 0777, true) || is_dir(dirname($cp_own));
$cp_ok = $cp_ok && @mkdir(dirname($cp_them), 0777, true);
ok($cp_ok, 'could not build the probe files, so nothing below is proving anything');
if ($cp_ok) {
	file_put_contents($cp_own,  str_repeat('A', 4096));
	file_put_contents($cp_them, str_repeat('A', 4096));
	ok(dropshipArtIsDropShipCopy($cp_them, $cp_rel) === true,
	   "a reskin's file that IS Drop Ship's file is not reported as borrowed");

	/* Same length, different bytes -- the case the cheap size test lets
	   through and the hash has to catch. */
	file_put_contents($cp_them, str_repeat('B', 4096));
	ok(dropshipArtIsDropShipCopy($cp_them, $cp_rel) === false,
	   'a different picture of the same size is reported as Drop Ship\'s');

	/* Different length -- answered without hashing anything. */
	file_put_contents($cp_them, str_repeat('A', 4097));
	ok(dropshipArtIsDropShipCopy($cp_them, $cp_rel) === false,
	   'a file of a different size is reported as Drop Ship\'s');

	/* Drop Ship's own art is Drop Ship's art; there is nothing to report,
	   and without this every slot on project 1 would be flagged. */
	ok(dropshipArtIsDropShipCopy($cp_own, $cp_rel) === false,
	   "Drop Ship's own slots are all reported as borrowed from itself");

	unlink($cp_them);
	/*
	 * SILENT ON A MISSING FILE. The answer is false either way -- filesize()
	 * and md5_file() both return false for a path that is not there -- so
	 * what the is_file() guard actually buys is that nothing is PRINTED.
	 * display_errors is on platform-wide and this runs seventy times on one
	 * admin page, so without the guard a theme mid-upload would paper the
	 * art page in filesize() warnings.
	 */
	ob_start();
	$cp_ans = dropshipArtIsDropShipCopy($cp_them, $cp_rel);
	$cp_noise = ob_get_clean();
	ok($cp_ans === false, 'a missing file is reported as a copy');
	ok($cp_noise === '',
	   'a missing file raises a warning, which display_errors prints onto the art page');
	unlink($cp_own);
	@rmdir(dirname($cp_them));
	@rmdir($cp_dir);
}
ok(!file_exists($cp_own), 'the probe file was left behind in the live art folder');

/*
 * THE SIZE TEST IS A COST GUARD, NOT A CORRECTNESS ONE, and an EQUIVALENT
 * MUTANT: deleting it leaves md5_file() to give the same answers, slowly.
 * Asserted at the source because the thing it protects is not in the
 * return value -- Drop Ship's art runs to 8MB a file and this is asked
 * about seventy slots on a single page load, so two pictures that merely
 * weigh different amounts must never be hashed.
 */
ok(preg_match('/\$a !== \$b\) return false;\s*\n\s*return md5_file/', $art2) === 1,
   'the size comparison no longer short-circuits the hash, so every slot is hashed in full');

/* AND IT IS ACTUALLY SHOWN. A detector nothing calls answers nothing. */
$adm = file_get_contents(__DIR__ . '/dropship/admin-art.php');
ok(substr_count($adm, 'dropshipArtIsDropShipCopy(') >= 2,
   'the art page does not say which slots are still Drop Ship\'s');
ok(strpos($adm, "still Drop Ship's") !== false,
   'the header count does not separate borrowed art from missing art');
ok(strpos($adm, '.dsart .slot.borrowed') !== false,
   'a borrowed slot looks exactly like a finished one');
/* COUNTED APART FROM MISSING, because they are different jobs: one is art
   nobody has made, the other is art nobody has replaced. */
ok(preg_match('/\$missing\+\+;\s*continue;/', $adm) === 1,
   'a missing file is also counted as borrowed, or the two counts overlap');

/*
 * THE DEATH IMAGE DISCORD POSTS asks for the theme's own format, and does
 * NOT fall back to the .gif set -- for Dread City that set is Drop Ship's
 * copies, so the fallback reproduces the bug it sits under.
 */
$dash = file_get_contents(__DIR__ . '/dropship/dashboard.php');
$die_at = strpos($dash, "'images/die/'.\$ds_die");
ok($die_at !== false, 'the death image no longer resolves through the theme');
if ($die_at !== false) {
	$die_block = substr($dash, $die_at, 400);
	ok(strpos($die_block, 'dropshipMediaExtension()') !== false,
	   "the death image does not use the theme's own extension");
	ok(strpos($die_block, "'.gif'") === false,
	   "the death image still falls back to the .gif set, which is Drop Ship's art");
}

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
