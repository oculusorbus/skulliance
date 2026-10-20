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
		if (strpos($sql, 'FROM dropship_themes WHERE project_id') !== false)
			return new TResArt(array(array('id' => 9, 'project_id' => 55, 'slug' => 'z')));
		if (strpos($sql, 'FROM dropship_theme_text') !== false) {
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
ok(strpos($adm3, 'dropshipArtPlan($conn, $pid, $ext)') !== false,
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
ok(strpos($adm4, '$valid = in_array($slot, $manifest, true) || in_array($slot, $icon_names, true);') !== false,
   'the in-place path skipped the slot validation');
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
