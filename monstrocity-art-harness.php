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

/* ---- the logo box -------------------------------------------------------- */
echo "\nlogos\n";
/*
 * monstrocity.php renders the logo as max-width:300 with a free height, so
 * the file's own dimensions decide how big it looks. Three different wrongs
 * come out of that, and all three are fixed by one box.
 */
$cases = array(
	array(400, 125, 'too wide: would be scaled to 300x94 and look smaller than its neighbours'),
	array(200, 400, 'too tall: would tower over the layout'),
	array(119, 128, 'narrow: would be dwarfed by a 300-wide logo'),
	array(232, 125, 'already a normal size'),
	array(300, 125, 'exactly the box'),
	array(1024, 392, 'the default theme\'s outlier'),
);
foreach ($cases as $c) {
	list($w, $h, $label) = $c;
	$im = imagecreatetruecolor($w, $h);
	imagefill($im, 0, 0, 0x3366cc);
	list($o, $note) = mart_fit_logo($im);
	ok(imagesx($o) === MONSTROCITY_LOGO_W && imagesy($o) === MONSTROCITY_LOGO_H,
	   "$label -> came out " . imagesx($o) . 'x' . imagesy($o) . ' instead of the box');
}

/* CONTAIN, NOT COVER. A logo is type and a mark; cropping either ruins it.
   A 200x400 source must fit entirely inside, which means its drawn width is
   125/400 of 200, about 63 -- not 300. */
$tall = imagecreatetruecolor(200, 400);
imagefill($tall, 0, 0, 0xff0000);
list($ft,) = mart_fit_logo($tall);
$cols = 0;
for ($x = 0; $x < MONSTROCITY_LOGO_W; $x++) {
	$p = imagecolorat($ft, $x, (int)(MONSTROCITY_LOGO_H / 2));
	if ((($p >> 24) & 0x7F) < 100) $cols++;
}
ok($cols > 50 && $cols < 80,
   'a 200x400 logo drew ' . $cols . ' opaque columns; contain-fit should give about 63, '
 . 'so it is being stretched or cropped rather than fitted');

/* THE PADDING MUST BE TRANSPARENT. imagecreatetruecolor() starts opaque
   black, so forgetting to fill gives every logo a black slab that looks
   almost right on a dark page. */
$corner = imagecolorat($ft, 1, 1);
ok((($corner >> 24) & 0x7F) > 100,
   'the padding around a fitted logo is opaque, so every logo gets a black slab behind it');

/* Centred, not shoved into a corner. */
$left = 0; $right = 0;
for ($x = 0; $x < MONSTROCITY_LOGO_W; $x++) {
	$p = imagecolorat($ft, $x, (int)(MONSTROCITY_LOGO_H / 2));
	if ((($p >> 24) & 0x7F) < 100) { if (!$left) $left = $x; $right = $x; }
}
$padL = $left; $padR = MONSTROCITY_LOGO_W - 1 - $right;
ok(abs($padL - $padR) <= 2, "the artwork is not centred: ${padL}px left, ${padR}px right");

/* Small art is enlarged rather than left tiny, but the caller is told. */
$tiny = imagecreatetruecolor(60, 25); imagefill($tiny, 0, 0, 0x00ff00);
list($fs, $noteS) = mart_fit_logo($tiny);
ok(imagesx($fs) === MONSTROCITY_LOGO_W, 'a tiny logo was not fitted to the box');
ok($noteS !== '', 'a logo enlarged 5x was accepted without a word about it looking soft');
list(, $noteOk) = mart_fit_logo(imagecreatetruecolor(300, 125));
ok($noteOk === '', 'a correctly sized logo was warned about anyway');

/* And the theme form routes logos through this, not the generic resizer. */
$tf = harness_strip_comments(file_get_contents(__DIR__ . '/admin-monstrocity.php'));
ok(strpos($tf, 'mart_accept_logo(') !== false,
   'the theme form still sends logos through adm_accept_upload, which only caps the long edge');
ok(preg_match("/adm_accept_upload\\('logo_file'/", $tf) !== 1,
   'the generic uploader is still handling the logo as well, so the last writer wins');

/* ---- bosses -------------------------------------------------------------- */
echo "\nbosses\n";
$okBoss = array('name'=>'Hopper King','project_id'=>4,'collection_id'=>9,'theme'=>'happyhoppers',
                'max_health'=>500,'strength'=>7,'speed'=>5,'tactics'=>6,'size'=>'Large',
                'powerup'=>'Heal','orientation'=>'Left','extension'=>'png');
$TH = array('happyhoppers','blackflag');
ok(monstrocity_validate_boss($okBoss, array(), $TH) === array(),
   'a valid boss was refused: ' . implode(' | ', monstrocity_validate_boss($okBoss, array(), $TH)));
ok(monstrocity_boss_slug('Hopper King') === 'hopper-king', 'the boss filename is not derived from the name');

/*
 * THE FAILURE THIS FORM EXISTS TO PREVENT. get-bosses.php INNER JOINs
 * projects AND collections, so a boss missing either does not error -- it
 * vanishes from the game with nothing logged. Both must be refused outright.
 */
foreach (array('project_id', 'collection_id') as $fk) {
	$b = $okBoss; $b[$fk] = 0;
	ok(monstrocity_validate_boss($b, array(), $TH) !== array(),
	   "a boss with no $fk was accepted; the inner join would hide it from the game silently");
}

/* Two bosses whose names reduce to the same filename share one image, and
   the second one saved replaces the first's art with no warning. */
$dup = $okBoss; $dup['name'] = 'Hopper  King';
ok(monstrocity_boss_slug('Hopper  King') !== 'hopper-king'
   || monstrocity_validate_boss($dup, array('hopper-king'), $TH) !== array(),
   'two bosses can share a filename, so one would overwrite the other\'s art');
$clash = $okBoss;
ok(monstrocity_validate_boss($clash, array('hopper-king'), $TH) !== array(),
   'a name that collides with an existing boss filename was accepted');

/* Stats the game actually understands. */
foreach (array(array('strength',0), array('strength',11), array('speed',0), array('tactics',99)) as $c) {
	$b = $okBoss; $b[$c[0]] = $c[1];
	ok(monstrocity_validate_boss($b, array(), $TH) !== array(), $c[0] . ' of ' . $c[1] . ' was accepted');
}
$b = $okBoss; $b['powerup'] = 'Invisibility';
ok(monstrocity_validate_boss($b, array(), $TH) !== array(),
   'a powerup the game does not implement was accepted, so the boss would have no ability');
$b = $okBoss; $b['size'] = 'Enormous';
ok(monstrocity_validate_boss($b, array(), $TH) !== array(), 'an unknown size was accepted');
$b = $okBoss; $b['max_health'] = 0;
ok(monstrocity_validate_boss($b, array(), $TH) !== array(), 'a boss with no health was accepted');
$b = $okBoss; $b['theme'] = 'nosuchtheme';
ok(monstrocity_validate_boss($b, array(), $TH) !== array(),
   'a boss pointing at a theme that does not exist was accepted');

/* Writing art: same 500-wide rule, and the red variant comes with it. */
$bdir = __DIR__ . '/' . monstrocity_boss_dir();
@mkdir($bdir . '/battle-damaged', 0775, true);
$tname = 'harness-boss-' . getmypid();
list($bw, $be) = mart_accept_boss('/tmp/mah_src.png', $tname, false, 'png');
ok($bw !== '' && $be === '', 'writing boss art failed: ' . $be);
ok(is_file($bdir . '/' . $tname . '.png'), 'the boss image was not written to the shared folder');
ok(is_file($bdir . '/battle-damaged/' . $tname . '.png'),
   'the boss red variant was not generated, so the pair can drift apart');
$bd = @getimagesize($bdir . '/' . $tname . '.png');
ok($bd && $bd[0] === MONSTROCITY_ART_WIDTH, 'boss art was not resized to the standard width');

/* A rename must take the art with it, or the boss shows a broken image. */
$tname2 = $tname . '-renamed';
ok(mart_rename_boss($tname, $tname2, 'png') === '', 'renaming boss art reported a failure');
ok(is_file($bdir . '/' . $tname2 . '.png') && !is_file($bdir . '/' . $tname . '.png'),
   'a renamed boss left its base art behind under the old filename');
ok(is_file($bdir . '/battle-damaged/' . $tname2 . '.png'),
   'a renamed boss left its red variant behind under the old filename');
ok(mart_rename_boss('../evil', 'x', 'png') !== '', 'a traversal was accepted as a rename source');

@unlink($bdir . '/' . $tname2 . '.png');
@unlink($bdir . '/battle-damaged/' . $tname2 . '.png');
ok(!is_file($bdir . '/' . $tname2 . '.png'), 'the harness left boss art behind in the live folder');

/* The page's own gates. */
$bp = harness_strip_comments(file_get_contents(__DIR__ . '/admin-monstrocity-boss.php'));
ok(strpos($bp, 'admin_require();') !== false, 'the boss admin is not gated to the admin');
ok(strpos($bp, 'monstrocity_validate_boss(') !== false, 'the boss form does not validate anything');
/*
 * NO WRITE MAY HAPPEN BEFORE VALIDATION, and none may happen when it fails.
 * Checking only that the FIRST write sits after the validate call misses a
 * write injected after it but outside the else -- so both the ordering and
 * the containment are asserted.
 */
/*
 * EVERY WRITE IS GATED BY A VALIDATION, on BOTH paths.
 *
 * There are two now -- one boss at a time, and a whole theme's stats at once
 * -- so a single "is the write after the else" check no longer says anything
 * true: the batch update legitimately sits before the single-boss branch and
 * failed that check while being perfectly well guarded.
 *
 * What holds for both is that each write has a validate call before it AND a
 * branch on the result between the two. Finding the NEAREST preceding
 * validate for each write is what makes that work with more than one path.
 */
$valAts = array();
if (preg_match_all('/monstrocity_validate_boss\(/', $bp, $vm, PREG_OFFSET_CAPTURE))
	foreach ($vm[0] as $v) $valAts[] = $v[1];
$writes = array();
if (preg_match_all('/(INSERT INTO bosses|UPDATE bosses SET)/', $bp, $wm, PREG_OFFSET_CAPTURE))
	foreach ($wm[1] as $w) $writes[] = $w[1];
ok($writes !== array(), 'the boss form never writes to the bosses table at all');
ok(count($valAts) >= 2, 'only ' . count($valAts) . ' validation call(s) for ' . count($writes)
   . ' write path(s) -- one of them is unguarded');
foreach ($writes as $w) {
	$near = -1;
	foreach ($valAts as $v) if ($v < $w && $v > $near) $near = $v;
	ok($near >= 0, 'a write to bosses at offset ' . $w . ' has no validation before it at all');
	if ($near < 0) continue;
	/* Something between the two must act on the result, or the validation is
	   decoration. Either branch shape counts: refuse-and-skip, or else. */
	$between = substr($bp, $near, $w - $near);
	ok(preg_match('/if \(\$e(rrs)?\)|\} else \{/', $between) === 1
	   || preg_match('/if \(\$e(rrs)?\)/', $between) === 1,
	   'nothing between the validation and the write at offset ' . $w . ' branches on the result');
}
/* The batch path specifically must SKIP a bad row rather than writing it. */
ok(preg_match('/if \(\$e\) \{[^}]*continue;/s', $bp) === 1,
   'the batch save does not skip a row that failed validation');
$bad = 0;
if (preg_match_all('/\$conn->query\(((?:[^()]|\([^()]*\))*)\)/s', $bp, $qm))
	foreach ($qm[1] as $q) if (strpos($q, '$_POST') !== false || strpos($q, '$_GET') !== false) $bad++;
ok($bad === 0, $bad . ' boss queries interpolate $_POST or $_GET directly');
ok(strpos($bp, 'mart_rename_boss(') !== false, 'renaming a boss does not move its art');

/* ---- a set of bosses, read together ------------------------------------- */
echo "\nboss sets\n";
$mk = function ($n, $str, $hp) {
	return array('name'=>$n,'strength'=>$str,'speed'=>5,'tactics'=>5,'max_health'=>$hp);
};
/* A clean ladder has nothing to say about it. */
$good = array($mk('A',2,200), $mk('B',4,400), $mk('C',7,900));
ok(monstrocity_boss_set_problems($good) === array(),
   'a properly escalating ladder was flagged: ' . implode(' | ', monstrocity_boss_set_problems($good)));
ok(monstrocity_boss_set_problems(array($mk('Only',5,500))) === array(),
   'a single boss was reported as a bad set');
ok(monstrocity_boss_set_problems(array()) === array(), 'an empty theme produced problems');

/*
 * THE ONE THAT IS NOT A JUDGEMENT: get-bosses.php orders by strength and
 * nothing else, so two bosses sharing one have no defined sequence -- the
 * order can differ between page loads with nothing changed.
 */
$tie = array($mk('A',5,200), $mk('B',5,400));
$p = monstrocity_boss_set_problems($tie);
ok($p !== array() && strpos($p[0], 'Strength 5') !== false,
   'two bosses sharing a strength were not flagged, though their running order is undefined');

/*
 * THE SET MUST BE SORTED BEFORE IT IS JUDGED. Rows arrive in whatever order
 * the query gave them, and "comes after" only means anything in strength
 * order. Fed out of order, this clean ladder must still come back clean --
 * every other fixture here happened to be pre-sorted, so dropping the sort
 * changed nothing and the mutation passed.
 */
$shuffled = array($mk('C',7,900), $mk('A',2,200), $mk('B',4,400));
ok(monstrocity_boss_set_problems($shuffled) === array(),
   'a clean ladder handed over out of order was reported as broken: '
 . implode(' | ', monstrocity_boss_set_problems($shuffled)));

/* Power should climb with position; a dip is nearly always a typo. */
$dip = array($mk('A',2,500), $mk('B',4,300), $mk('C',7,900));
$p2 = monstrocity_boss_set_problems($dip);
ok(count(array_filter($p2, function ($x) { return strpos($x, 'less health') !== false; })) === 1,
   'a later boss with less health than the one before it was not reported');

/* A flat run is what duplicating a boss and forgetting to retune produces. */
$flat = array($mk('A',2,500), $mk('B',4,500), $mk('C',7,500));
ok(count(array_filter(monstrocity_boss_set_problems($flat),
     function ($x) { return strpos($x, 'same health') !== false; })) === 1,
   'three bosses with identical health were not flagged as a fight that never gets harder');
/* But two identical ones are a pair, not a pattern. */
ok(count(array_filter(monstrocity_boss_set_problems(array($mk('A',2,500), $mk('B',4,500))),
     function ($x) { return strpos($x, 'same health') !== false; })) === 0,
   'two bosses with matching health were reported as a flat ladder');

/* Duplicating must not carry the name or the art, both keyed by the name. */
$cp = monstrocity_boss_copyable();
ok(!in_array('name', $cp, true),
   'duplicating copies the name, which is the filename -- two bosses would share one image');
ok(!in_array('health', $cp, true), 'duplicating copies current health rather than letting it track max');
ok(in_array('theme', $cp, true) && in_array('max_health', $cp, true) && in_array('powerup', $cp, true),
   'duplicating does not carry the settings worth copying');

/* The page offers all three routes. */
ok(strpos($bp, 'new=1&amp;from=') !== false, 'there is no way to duplicate a boss from the list');
ok(strpos($bp, "isset(\$_GET['from'])") !== false, 'the form ignores the boss it was told to copy');
ok(strpos($bp, "min(10, (int)\$src['strength'] + 1)") !== false,
   'a duplicate keeps its source\'s strength, so the copy ties with it and their order is undefined');
ok(strpos($bp, 'monstrocity_boss_set_problems(') !== false,
   'the tuning view does not check the ladder it is showing');
ok(strpos($bp, "name=\"batch\"") !== false || strpos($bp, "name='batch'") !== false,
   'there is no batch save');
/* The batch is FIELDS only. Images stay one request each. */
ok(stripos($bp, 'batch') !== false && strpos($bp, 'mart_accept_boss') !== false
   && strpos($bp, "\$_FILES['art']") !== false,
   'the art upload path went missing');
/*
 * The batch block ONLY. An unbounded `batch.*?$_FILES` spans from the word
 * batch to the single-boss upload hundreds of lines later and always
 * matches -- the same reach-past-the-target mistake that has bitten three
 * checks in this feature already. Brace-match the block instead.
 */
$bbAt = strpos($bp, "isset(\$_POST['batch'])");
$bbBlock = '';
if ($bbAt !== false) {
	$open = strpos($bp, '{', $bbAt); $d = 0;
	for ($i = $open; $i < strlen($bp); $i++) {
		if ($bp[$i] === '{') $d++;
		elseif ($bp[$i] === '}') { $d--; if (!$d) break; }
	}
	$bbBlock = substr($bp, $open, $i - $open + 1);
}
ok($bbBlock !== '' && strlen($bbBlock) < 4000,
   'the batch block could not be isolated (' . strlen($bbBlock) . ' bytes), so the check below '
 . 'is reaching past it');
ok($bbBlock !== '' && strpos($bbBlock, '$_FILES') === false,
   'the batch save touches uploaded files; it is stats only, and images stay one request each');
ok($bbBlock !== '' && strpos($bbBlock, 'monstrocity_validate_boss(') !== false,
   'the batch save writes rows it never validated');

/* ---- form plumbing that silently submits the wrong thing ---------------- */
echo "\nselects and limits\n";
/*
 * REPORTED: "Extension must be one of: png, gif, jpg, mov" while PNG was
 * selected. An <option> with no value= submits its TEXT, and this one
 * displayed ".png" with a leading dot, so the form posted ".png" and
 * validation refused the thing the user had plainly chosen.
 *
 * The other selects on the page worked only because their text happened to
 * equal their value. Requiring value= on every option is what stops the next
 * decorated label repeating it.
 */
foreach (array('admin-monstrocity-boss.php', 'admin-monstrocity.php') as $formFile) {
	$src = file_get_contents(__DIR__ . '/' . $formFile);
	$total  = substr_count($src, '<option');
	$valued = substr_count($src, '<option value=');
	ok($total === $valued,
	   $formFile . ': ' . ($total - $valued) . ' of ' . $total . ' <option> tags have no value=, '
	 . 'so they submit their display text');
}

/*
 * AND THE SIZE GUARD. GD allocates about 4 bytes a pixel and a resize holds
 * source and destination at once, so a 4500x4500 PNG needs ~190MB. Over the
 * limit PHP does not fail politely, it dies mid-request -- which on the art
 * endpoint is a blank reply the page reports as "not JSON". Read from the
 * header, before any decode, because afterwards is too late.
 */
$mkpng = function ($w, $h) { $im = imagecreatetruecolor($w, $h); ob_start(); imagepng($im); return ob_get_clean(); };
ok(mart_too_big($mkpng(500, 500)) === '', 'a 500x500 image was refused as too large');
ok(mart_too_big($mkpng(1200, 1200)) === '', 'a 1200x1200 image was refused as too large');
$big = mart_too_big($mkpng(6000, 6000));
ok($big !== '', 'a 6000x6000 image was accepted; decoding it would exhaust memory mid-request');
ok(strpos($big, '6000x6000') !== false && stripos($big, 'MB') !== false,
   'the refusal does not say the size or the budget, so there is nothing to act on: ' . $big);
ok(stripos($big, '1500') !== false,
   'the refusal does not say what size to export instead');
ok(mart_too_big('not an image at all') !== '', 'a non-image was accepted by the size guard');

/* The guard must run BEFORE the decode, or it protects nothing. */
$lib = harness_strip_comments(file_get_contents(__DIR__ . '/monstrocity-art-lib.php'));
$tbAt = strpos($lib, 'mart_too_big($raw)');
$dcAt = strpos($lib, 'imagecreatefromstring($raw)');
ok($tbAt !== false && $dcAt !== false && $tbAt < $dcAt,
   'the size check runs after imagecreatefromstring(), by which point the memory is already gone');

/* A refused load has to explain itself or the page says "not an image" to
   somebody who uploaded a perfectly good one. */
file_put_contents('/tmp/mah_huge.png', $mkpng(6000, 6000));
ok(mart_load('/tmp/mah_huge.png') === null, 'an oversized image was loaded anyway');
ok(strpos(mart_load_why(), '6000x6000') !== false,
   'the reason an oversized image was refused is not available to the caller');
@unlink('/tmp/mah_huge.png');

/* The boss form must notice a dropped POST, same as the theme form. */
$bpRaw = file_get_contents(__DIR__ . '/admin-monstrocity-boss.php');
/* The ASSIGNMENT, not the mention. `$post_discarded = false && monstrocity_
   post_discarded(...)` leaves the name sitting there and a presence check
   is perfectly happy with it. */
ok(preg_match('/^\$post_discarded = monstrocity_post_discarded\(/m', $bpRaw) === 1,
   'the boss form does not set $post_discarded straight from the detector');
/* And both save paths must actually be gated on it. */
ok(substr_count($bpRaw, '!$post_discarded') === 2,
   'only ' . substr_count($bpRaw, '!$post_discarded') . ' of the 2 save paths skip a dropped POST');
ok(strpos($bpRaw, "'max_health'=>250") !== false,
   'a new boss no longer defaults to 250 health');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall art checks passed\n");
exit($fail ? 1 : 0);
