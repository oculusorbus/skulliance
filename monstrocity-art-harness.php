<?php
/*
 * The character art pipeline, driven for real.
 *
 * The claim this has to keep honest is a strong one: the generated
 * battle-damaged art is the SAME image the owner was producing by hand in
 * Photoshop. That was established by fitting against shipped pairs, and it
 * stays true only as long as something checks it, so this downloads real
 * base/damaged pairs from the live site and compares.
 *
 * Usage: php monstrocity-art-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }
require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/monstrocity-art-lib.php';

/* ---- the roster ---------------------------------------------------------- */
$chars = monstrocity_characters();
ok(count($chars) === 14, 'the roster is ' . count($chars) . ' characters, not 14');
$slugs = array_column($chars, 'slug');
ok(count(array_unique($slugs)) === 14, 'two characters share a filename');
ok(in_array('slime-mind', $slugs, true) && in_array('billandar-and-ted', $slugs, true),
   'multi-word names are not being slugged to the filenames the game asks for');
foreach ($chars as $c)
	ok(in_array($c['faces'], array('Left','Right'), true), $c['name'] . ' faces "' . $c['faces'] . '"');
/* The roster must match the game's own list, or a slot writes a file nothing loads. */
$game = file_get_contents(__DIR__ . '/monstrocity.php');
foreach ($chars as $c)
	ok(strpos($game, '"' . $c['name'] . '"') !== false,
	   $c['name'] . ' is not a character monstrocity.php knows about');

/* ---- the recolour, against the owner's own output ------------------------ */
echo "\nthe damage formula vs shipped art\n";
$pairs = array(
	array('blackflag','craig'), array('apprentices','merdock'), array('danketsu2','drake'),
	array('moebiuspioneers','ouchie'), array('rubberrebels','katastrophy'),
);
$tested = 0; $worstMean = 0;
foreach ($pairs as $p) {
	list($t, $c) = $p;
	$bt = @file_get_contents("https://www.skulliance.io/staking/images/monstrocity/$t/base/$c.png");
	$dt = @file_get_contents("https://www.skulliance.io/staking/images/monstrocity/$t/battle-damaged/$c.png");
	if (!$bt || !$dt) { echo "  (skipped $t/$c, not reachable)\n"; continue; }
	file_put_contents('/tmp/mah_b.png', $bt); file_put_contents('/tmp/mah_d.png', $dt);
	$base = mart_load('/tmp/mah_b.png'); $ship = mart_load('/tmp/mah_d.png');
	if (!$base || !$ship || imagesx($base) !== imagesx($ship)) continue;
	$mine = mart_damage($base);
	$w = imagesx($mine); $h = imagesy($mine); $n = 0; $sum = 0;
	for ($y = 0; $y < $h; $y += 3) for ($x = 0; $x < $w; $x += 3) {
		$q = imagecolorat($base, $x, $y); if ((($q >> 24) & 0x7F) > 100) continue;
		$a = imagecolorat($mine, $x, $y); $b = imagecolorat($ship, $x, $y);
		$sum += (abs((($a>>16)&255)-(($b>>16)&255)) + abs((($a>>8)&255)-(($b>>8)&255)) + abs(($a&255)-($b&255))) / 3;
		$n++;
	}
	if (!$n) continue;
	$mean = $sum / $n; $tested++; $worstMean = max($worstMean, $mean);
	ok($mean < 1.5, "$t/$c: generated damage differs from the shipped art by $mean/255");
	printf("  %-18s %-12s %.3f/255\n", $t, $c, $mean);
}
ok($tested >= 3, "only $tested pairs were reachable, so this check is not really testing anything");

/* ---- resize keeps the shape --------------------------------------------- */
echo "\nresize, flip, score\n";
$tall = imagecreatetruecolor(2000, 3000);
$r = mart_resize($tall);
ok(imagesx($r) === 500 && imagesy($r) === 750,
   'a 2000x3000 source became ' . imagesx($r) . 'x' . imagesy($r) . ' -- aspect must be preserved, not squared');
$wide = imagecreatetruecolor(1800, 900);
$r2 = mart_resize($wide);
ok(imagesx($r2) === 500 && imagesy($r2) === 250, 'a wide source was not scaled on width');
$small = imagecreatetruecolor(300, 300);
ok(imagesx(mart_resize($small)) === 300, 'art narrower than the target was upscaled into mush');

/* flip twice is the identity, which is the cheapest proof it is a mirror and
   not a rotate or a no-op */
$src = imagecreatetruecolor(8, 4);
for ($x = 0; $x < 8; $x++) for ($y = 0; $y < 4; $y++) imagesetpixel($src, $x, $y, ($x * 30) << 16 | $y * 60);
$once = mart_flip($src); $twice = mart_flip($once);
$same = true; $moved = false;
for ($x = 0; $x < 8; $x++) for ($y = 0; $y < 4; $y++) {
	if (imagecolorat($src, $x, $y) !== imagecolorat($twice, $x, $y)) $same = false;
	if (imagecolorat($src, $x, $y) !== imagecolorat($once, $x, $y))  $moved = true;
}
ok($same,  'flipping twice does not return the original, so it is not a mirror');
ok($moved, 'flipping once changed nothing');

/* the visibility score separates the two cases it exists to separate */
$flat = imagecreatetruecolor(40, 40);
imagefill($flat, 0, 0, 0x804020);
ok(mart_damage_score($flat, $flat) === 0.0, 'identical images did not score 0');
ok(mart_damage_score($flat, mart_damage($flat)) > MONSTROCITY_DAMAGE_WEAK,
   'a recoloured mid-tone scored as weak, so the threshold would flag healthy art');
$dark = imagecreatetruecolor(40, 40);
imagefill($dark, 0, 0, 0x0a0a0a);
ok(mart_damage_score($dark, mart_damage($dark)) < MONSTROCITY_DAMAGE_WEAK,
   'near-black art scored as readable damage, but red on black is invisible -- '
 . 'that is precisely the case the owner needs flagged');

/* ---- the endpoint's gates ------------------------------------------------ */
echo "\nendpoint\n";
$ep = file_get_contents(__DIR__ . '/ajax/monstrocity-art.php');
$epNo = harness_strip_comments($ep);
ok(strpos($epNo, "user_id'] !== 1") !== false, 'the art endpoint is not restricted to the admin');
$gate = strpos($epNo, "user_id'] !== 1");
ok($gate < strpos($epNo, '$_FILES'), 'the endpoint touches the upload before checking who is asking');
/* The slug must come from the table. A slug from the request is a path. */
ok(strpos($epNo, "SELECT slug FROM monstrocity_themes WHERE id = ") !== false,
   'the theme folder is not read from the database');
ok(strpos($epNo, "\$_POST['slug']") === false && strpos($epNo, "\$_POST['theme_slug']") === false,
   'the endpoint accepts a folder name from the request, which is a path traversal');
/* And the character must be one of the fourteen, not any string. */
ok(strpos($epNo, 'isset($known[$char])') !== false,
   'any character name is accepted, so an arbitrary filename can be written');
$known = strpos($epNo, 'isset($known[$char])');
ok($known < strpos($epNo, "action === 'upload'"), 'the character is validated after the upload is handled');
ok(strpos($epNo, "in_array(\$slot, array('base', 'leader', 'damaged'), true)") !== false,
   'the slot is not restricted to the three real folders');

/* ---- mart_accept(), end to end on disk ----------------------------------- */
echo "\nwriting a slot\n";
$tmpTheme = 'harness-tmp-' . getmypid();
$tdir = __DIR__ . '/' . monstrocity_theme_dir($tmpTheme);
@mkdir($tdir . '/base', 0775, true);
@mkdir($tdir . '/leader', 0775, true);
@mkdir($tdir . '/battle-damaged', 0775, true);

/* A source wider than the target, with real colour in it, so the resize and
   the recolour both have something to do. */
$srcIm = imagecreatetruecolor(1200, 900);
for ($x = 0; $x < 1200; $x += 10)
	imagefilledrectangle($srcIm, $x, 0, $x + 9, 899, imagecolorallocate($srcIm, 240, 200 - ($x % 180), 30));
imagepng($srcIm, '/tmp/mah_src.png');

list($written, $err) = mart_accept('/tmp/mah_src.png', $tmpTheme, 'base', 'craig', false);
ok($written !== '' && $err === '', 'writing a base slot failed: ' . $err);
ok(is_file($tdir . '/base/craig.png'), 'the base image was not written');
/*
 * THE ONE THAT MATTERS: a base defines its own damaged variant, and writing
 * one regenerates the other. The live library has pairs that drifted apart
 * precisely because a base was replaced on its own, so this is the behaviour
 * that stops that happening again.
 */
ok(is_file($tdir . '/battle-damaged/craig.png'),
   'writing a base did NOT regenerate its battle-damaged variant, which is how '
 . 'the two drift apart');
$bw = @getimagesize($tdir . '/base/craig.png');
ok($bw && $bw[0] === 500 && $bw[1] === 375, 'the slot was not resized to 500 wide with aspect kept');
$dw = @getimagesize($tdir . '/battle-damaged/craig.png');
ok($dw && $dw[0] === $bw[0] && $dw[1] === $bw[1], 'the damaged variant is a different size to its base');
$b = mart_load($tdir . '/base/craig.png');
$d = mart_load($tdir . '/battle-damaged/craig.png');
ok($b && $d && mart_damage_score($b, $d) > MONSTROCITY_DAMAGE_WEAK,
   'the generated damage is not visibly different from the base it came from');

/* A leader does NOT generate damage -- only the base does. */
list($w2, $e2) = mart_accept('/tmp/mah_src.png', $tmpTheme, 'leader', 'merdock', false);
ok($w2 !== '', 'writing a leader slot failed: ' . $e2);
ok(!is_file($tdir . '/battle-damaged/merdock.png'),
   'a leader generated a damaged variant; only the base should');

/* Flipping on the way in actually flips. */
mart_accept('/tmp/mah_src.png', $tmpTheme, 'base', 'drake', false);
$plain = mart_load($tdir . '/base/drake.png');
mart_accept('/tmp/mah_src.png', $tmpTheme, 'base', 'texby', true);
$flipped = mart_load($tdir . '/base/texby.png');
ok($plain && $flipped && imagecolorat($plain, 5, 5) !== imagecolorat($flipped, 5, 5),
   'the flip option on upload did nothing');

/* Hostile inputs never reach the disk. */
list($bad1,) = mart_accept('/tmp/mah_src.png', '../../etc', 'base', 'craig', false);
ok($bad1 === '', 'a traversal in the theme name was accepted');
list($bad2,) = mart_accept('/tmp/mah_src.png', $tmpTheme, 'base', '../../evil', false);
ok($bad2 === '', 'a traversal in the character name was accepted');
list($bad3,) = mart_accept('/tmp/mah_src.png', $tmpTheme, 'nonsense', 'craig', false);
ok($bad3 === '', 'an unknown slot was accepted');

array_map('unlink', glob($tdir . '/*/*.png') ?: array());
foreach (array('base','leader','battle-damaged') as $sd) @rmdir($tdir . '/' . $sd);
@rmdir($tdir);
ok(!is_dir($tdir), 'the harness left its temporary theme folder behind at ' . $tdir);

/* ---- the board ----------------------------------------------------------- */
echo "\nthe board\n";
$pg = file_get_contents(__DIR__ . '/admin-monstrocity-art.php');
$pgNo = harness_strip_comments($pg);
ok(strpos($pgNo, 'admin_require();') !== false, 'the art board is not gated to the admin');
/* Resize before send is the whole reason a 40MB NFT cannot kill this page. */
ok(strpos($pgNo, 'function shrink(') !== false, 'the browser does not resize before uploading');
/* The CALL SITE, not the definition. `function shrink(file)` contains the
   string `shrink(file)`, so anchoring on that matched the definition and the
   check passed with the call replaced by Promise.resolve(file). */
$shrinkAt = strpos($pgNo, 'shrink(file).then(');
$appendAt = strpos($pgNo, "b.append('image'");
ok($shrinkAt !== false && $appendAt !== false && $shrinkAt < $appendAt,
   'the file is attached to the request before it has been resized');
/* Stepwise halving, not one jump -- a single large downscale aliases. */
ok(strpos($pgNo, 'while (cw / 2 > WIDTH)') !== false,
   'the resize is a single jump, which aliases on detailed art at large ratios');
/* No batch control anywhere. */
ok(stripos($pgNo, 'save all') === false && stripos($pgNo, 'upload all') === false,
   'there is a batch control on the board, which is the one thing it must not have');
/* Each slot prints the direction it needs. */
ok(strpos($pg, 'faces <b>') !== false, 'a slot does not say which way the character should face');
/* A missed drop must not navigate the page to the image file, which on this
   board means walking away from a half-filled set. */
ok(strpos($pgNo, "window.addEventListener('drop'") !== false,
   'dropping outside a slot still navigates away from the board');
ok(strpos($pgNo, "e.target.closest('.ma-drop')") !== false,
   'the window-level drop guard does not let real slot drops through');
ok(substr_count($pgNo, "addEventListener('drop'") >= 2,
   'the slots themselves no longer accept a drop');

/* ---- activate, and the board's answer to "how do I save" ---------------- */
echo "\nfinishing a theme\n";
/* The board must SAY that nothing is pending. Reported from real use: 28
   squares filled, everything already on disk, and no way to tell. */
ok(strpos($pg, 'Nothing to save') !== false || strpos($pg, 'already saved') !== false,
   'the board never says that uploads have already saved, so there is nothing to look for');
ok(strpos($pgNo, "id=\"ma-activate\"") !== false || strpos($pgNo, "id='ma-activate'") !== false,
   'there is no way to put a finished theme live from the board');
ok(strpos($pgNo, "action', 'activate'") !== false, 'the activate button is wired to nothing');

/* Activation is the one action that changes what players see, so the server
   decides whether the set is complete -- not a page that might be stale. */
ok(strpos($epNo, "action === 'activate'") !== false, 'the endpoint cannot activate a theme');
/* DRIVEN, not read. The order-of-lines version of this passed with the whole
   guard replaced by `if (false)`. */
$gt = 'harness-gate-' . getmypid();
$gdir = __DIR__ . '/' . monstrocity_theme_dir($gt);
@mkdir($gdir . '/base', 0775, true); @mkdir($gdir . '/leader', 0775, true);
@mkdir($gdir . '/battle-damaged', 0775, true);
ok(count(mart_missing_slots($gt)) === 28, 'an empty theme does not report all 28 slots missing');
foreach (monstrocity_characters() as $c) {
	mart_accept('/tmp/mah_src.png', $gt, 'base', $c['slug'], false);
	mart_accept('/tmp/mah_src.png', $gt, 'leader', $c['slug'], false);
}
ok(mart_missing_slots($gt) === array(), 'a fully filled theme still reports missing slots: '
   . implode(', ', mart_missing_slots($gt)));
@unlink($gdir . '/leader/spydrax.png');
$m = mart_missing_slots($gt);
ok($m === array('Spydrax leader'), 'one removed leader was not reported alone: ' . implode(', ', $m));
@unlink($gdir . '/base/craig.png');
ok(count(mart_missing_slots($gt)) === 2, 'a missing base is not counted alongside a missing leader');
/* And the endpoint refuses on the result rather than ignoring it. */
ok(strpos($epNo, '$missing = mart_missing_slots($slug);') !== false,
   'the endpoint no longer asks which slots are missing');
ok(strpos($epNo, 'if ($missing) {') !== false && strpos($epNo, 'if ($missing) {') < strpos($epNo, 'SET active = 1'),
   'the endpoint activates without acting on the missing-slot list');
array_map('unlink', glob($gdir . '/*/*.png') ?: array());
foreach (array('base','leader','battle-damaged') as $sd) @rmdir($gdir . '/' . $sd);
@rmdir($gdir);
ok(!is_dir($gdir), 'the harness left ' . $gdir . ' behind');

/* "Flip both" must still be two single-slot requests -- the no-batch rule. */
ok(strpos($pgNo, 'function flipSlot(') !== false, 'there is no shared single-slot flip');
ok(strpos($pgNo, "flipSlot(row, 'base').then(function () { return flipSlot(row, 'leader'); })") !== false,
   'flip both is not two separate single-slot requests, so one failure could take the pair down');
ok(strpos($epNo, "action === 'flipboth'") === false && strpos($epNo, "'both'") === false,
   'a batch flip endpoint appeared, which is the one thing this design does not have');

/* The heading escaped its own separator and printed &mdash; as text. */
ok(strpos($pg, "htmlspecialchars(\$theme['project_name'] . ' &mdash; '") === false,
   'the heading runs its separator through htmlspecialchars, which prints the entity as text');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall art checks passed\n");
exit($fail ? 1 : 0);
