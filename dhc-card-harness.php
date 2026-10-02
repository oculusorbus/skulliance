<?php
/* dhc-card-harness.php - CLI only. No database, no network.
 *
 * The 1200x630 share card X puts on a posted Fighter.
 *
 * WHY GEOMETRY IS THE WHOLE TEST. x.com/intent/post cannot attach a file --
 * the only image a post carries is the one the shared URL declares as its
 * twitter:image, fetched by X's crawler. And summary_large_image is 2:1:
 * hand X the square render and it centre-crops it, so the Fighter arrives
 * with its head and its feet cut off. A card that is the wrong shape is not
 * a cosmetic problem, it is the share being worthless, which is the thing
 * this was built to fix.
 *
 * So the REAL compositing block is lifted out of dhc-card.php and run
 * against a source image whose top and bottom rows are marked. If either
 * marker is missing from the output, the art is being cropped.
 *
 * Usage: php dhc-card-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }
function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	$s = preg_replace('~(?<![:/])//.*$~m', '', $s);
	return $s;
}

$src_php = file_get_contents(__DIR__ . '/dhc-card.php');
$clean   = no_comments($src_php);

echo "the card is the shape X actually renders\n";
ok(preg_match('/const DHCC_W = (\d+);/', $clean, $w) === 1, 'DHCC_W is gone');
ok(preg_match('/const DHCC_H = (\d+);/', $clean, $h) === 1, 'DHCC_H is gone');
$W = (int)$w[1]; $H = (int)$h[1];
ok($W === 1200 && $H === 630, "the card is {$W}x{$H}; X's summary_large_image wants 1200x630");
ok(abs(($W / $H) - 2) < 0.12, 'the card is not ~2:1, so X will crop it');

if (!function_exists('imagecreatetruecolor')) {
	echo "\n  (GD not available on this CLI build -- geometry not run)\n";
} else {
	echo "\nnothing is cropped off the Fighter\n";
	/* The real block, from the canvas to the sharp copy. */
	$a = strpos($clean, '$card = imagecreatetruecolor(DHCC_W, DHCC_H);');
	ok($a !== false, 'the compositing block moved; this check is now blind');
	$b = strpos($clean, 'imagecopyresampled($card, $src, (int)((DHCC_W - $fw)');
	$b = strpos($clean, ";\n", $b);
	$block = substr($clean, $a, $b - $a + 1);

	/* The real block references the constants by name, so define them from
	   the values just read out of the file rather than hardcoding a second
	   copy here -- that way changing them in dhc-card.php moves this test
	   with it instead of silently diverging from it. */
	if (!defined('DHCC_W')) define('DHCC_W', $W);
	if (!defined('DHCC_H')) define('DHCC_H', $H);

	$sw = $sh = 1000;
	$src = imagecreatetruecolor($sw, $sh);
	imagefilledrectangle($src, 0, 0, $sw, $sh, imagecolorallocate($src, 200, 30, 30));
	/* Markers on the extreme top and bottom rows of the source art. */
	imagefilledrectangle($src, 0, 0, $sw, 6, imagecolorallocate($src, 0, 255, 0));
	imagefilledrectangle($src, 0, $sh - 6, $sw, $sh, imagecolorallocate($src, 0, 0, 255));

	eval($block);

	ok(imagesx($card) === $W && imagesy($card) === $H,
	   'the composed canvas is ' . imagesx($card) . 'x' . imagesy($card) . ', not ' . $W . 'x' . $H);

	/* Find the sharp copy's own placement the same way the file does. */
	$fit = ($H - 36) / $sh;
	$fh2 = (int)round($sh * $fit);
	$y   = (int)(($H - $fh2) / 2);
	$top = imagecolorat($card, (int)($W / 2), $y + 2);
	$bot = imagecolorat($card, (int)($W / 2), $y + $fh2 - 3);
	ok((($top >> 8) & 255) > 150,
	   'the TOP of the art is not in the card -- a Fighter would be beheaded');
	ok(($bot & 255) > 150,
	   'the BOTTOM of the art is not in the card -- a Fighter would lose its feet');
	ok($y >= 4, 'the art is flush against the top edge with no air at all');

	/* The sides must not be bare slab: the backdrop has to have painted
	   something other than the flat ground colour. */
	$edge = imagecolorat($card, 8, (int)($H / 2));
	$ground = (7 << 16) | (17 << 8) | 29;
	ok($edge !== $ground, 'the sides are flat ground; the backdrop fill is gone');
}

echo "\nit draws through the one renderer, and only for saved Fighters\n";
ok(strpos($clean, 'dhcf_render_fighter(') !== false,
   'the card composes its own Fighter instead of calling dhcf_render_fighter(); that is the bug class that cost the Arena its effects twice');
ok(strpos($clean, 'disassembled_at IS NULL') !== false,
   'a disassembled Fighter still has a card');
ok(strpos($clean, "_GET['build']") === false && strpos($clean, '$_GET[\'traits\']') === false,
   'the card accepts an arbitrary build; that is a way to walk the trait art out without owning any of it');
ok(preg_match('/bind_param\(\'i\'/', $clean) === 1, 'the serial is not bound as an integer parameter');

echo "\nthe cache cannot serve a stale or half-written card\n";
/* In the KEY, not merely somewhere in the file: traits_hash is also in the
   SELECT, so a bare strpos stayed green when the key was switched to the
   serial -- which is the stale-after-edit bug this is here to catch. */
ok(preg_match('/\$key\s*=.*traits_hash/', $clean) === 1,
   'the cache key is not traits-based; editing a Fighter keeps its serial, so a serial-keyed card would serve the old picture for a week');
ok(preg_match('/@rename\(\$tmp, \$cache\)/', $clean) === 1,
   'the card is written in place rather than renamed into position; a half-written nonzero file would be served for a week');
ok(strpos($clean, 'filesize($cache) > 0') !== false, 'a zero-byte cache file counts as a hit');

echo "\nnothing can print in front of the JPEG\n";
ok(preg_match('/^ob_start\(\);\s*include __DIR__ \. \'\/db\.php\';/m', $clean) === 1,
   'db.php is included unbuffered; display_errors is ON, and a notice before the header is a corrupt card that X then caches');
ok(strpos($clean, 'ob_end_clean();') !== false, 'the buffer is never discarded');
/* imagedestroy() has done nothing since PHP 8.0 and is deprecated in 8.5 --
   with display_errors on, the notice lands in the image. */
ok(preg_match('/^[^\/\n]*imagedestroy\s*\(/m', $clean) === 0,
   'imagedestroy() is back; it is a no-op since PHP 8.0, deprecated in 8.5, and its notice would corrupt the JPEG');

echo "\nthe gallery points X at the card\n";
$g = no_comments(file_get_contents(__DIR__ . '/dhcgallery.php'));
ok(strpos($g, 'dhc-card.php?serial=') !== false,
   'the gallery no longer declares a per-Fighter image, so every share carries the same house logo');
ok(preg_match('/twitter:card" content="summary_large_image"/', $g) === 1,
   'the card type is not summary_large_image, so X renders a thumbnail instead of the Fighter');
ok(strpos($g, 'og:image:width') !== false && strpos($g, 'og:image:height') !== false,
   'the image dimensions are not declared; crawlers that do not fetch the image cannot size the card');
ok(strpos($g, "isset(\$_GET['fighter'])") !== false, 'the per-Fighter branch is gone');
/* It must be its own query: the rows on the page are whatever it is filtered
   and paged to, and a link to a Fighter outside that filter must still
   unfurl. */
ok(preg_match('/FROM dhc_fighters f\s*\n?\s*LEFT JOIN users/', $g) === 1,
   'the per-Fighter lookup scans the page rows instead of querying; a shared Fighter outside the current filter would not unfurl');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
