<?php
/* dhc-render-harness.php — CLI only. Real GD, real files, no database.
 *
 * WHY: Discord embeds for Fighters were showing broken images, sometimes,
 * for months. discordmsg() omits the image key entirely when the render
 * returns '' -- so a FAILED render gives an embed with no picture, not a
 * broken one. A broken one means a URL was emitted and Discord could not
 * load what was behind it.
 *
 * dhcf_render_fighter() used to imagepng() straight onto its cache name
 * while the early return trusted is_file(). That gives two ways to serve a
 * URL pointing at a file that is not a valid PNG: a concurrent reader
 * catching the write in progress, and a write that dies partway leaving a
 * stub that is_file() honours forever.
 *
 * These checks drive the REAL function against a temp directory.
 *
 * Usage: php dhc-render-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
if (!function_exists('imagecreatetruecolor')) { echo "GD missing; skipping\n"; exit(0); }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$src = file_get_contents(__DIR__ . '/dhcfighters-notify.php');

echo "the cache write is atomic\n";

/* 1. Nothing may be written to the cache name before it is complete. */
ok(preg_match('/\$tmp\s*=\s*\$dir\s*\.\s*\x27\/\.\x27/', $src) === 1,
   'the render no longer composes into a temp file, so a concurrent reader '
 . 'can catch a half-written PNG and hand that URL to Discord');
ok(preg_match('/@rename\(\$tmp,\s*\$dir\s*\.\s*\x27\/\x27\s*\.\s*\$name\)/', $src) === 1,
   'the temp file is not rename()d into place -- rename is what makes the '
 . 'swap atomic, so a reader sees no file or a finished one, never a partial');
ok(preg_match('/@imagepng\(\$out,\s*\$dir\s*\.\s*\x27\/\x27\s*\.\s*\$name/', $src) === 0,
   'imagepng() writes straight to the cache name again, which is the bug: it '
 . 'creates the destination immediately and fills it progressively');
/* 2. A failed or empty write must not leave the stub behind. */
ok(preg_match('/if \(!\$ok \|\| !@filesize\(\$tmp\)\) \{ @unlink\(\$tmp\); return \x27\x27; \}/', $src) === 1,
   'a failed or zero-byte write is not cleaned up, so the next call inherits '
 . 'a stub');
/* 3. And an existing stub must not be served. */
ok(preg_match('/is_file\(\$dir \. \x27\/\x27 \. \$name\) && @filesize\(\$dir \. \x27\/\x27 \. \$name\) > 0/', $src) === 1,
   'the cache hit still trusts is_file() alone, so the zero-byte stubs this '
 . 'bug has already left on the server keep being served to Discord');
/* 4. And NOT with imagedestroy(). It looks like the obvious way to bound
 *    memory across nine 1000px layers, and it is a trap here: it has had no
 *    effect since PHP 8.0 and emits a deprecation notice from 8.5, on a
 *    platform that runs display_errors ON -- so a notice from inside a
 *    render prints into whatever page or JSON called it, which on this
 *    codebase has already meant a corrupt download and a dead <script>
 *    block. GD frees on return. */
$code = preg_replace('~/\*.*?\*/~s', '', preg_replace('~//[^\n]*~', '', $src));
ok(strpos($code, 'imagedestroy(') === false,
   'imagedestroy() is being called again -- no effect since PHP 8.0, '
 . 'deprecated from 8.5, and this platform prints notices');

echo "\nrename() really is atomic here\n";
/*
 * Not taken on faith: write a large PNG to a temp name in the same
 * directory, rename it, and confirm a reader polling the destination only
 * ever sees a complete file. The write has to be big enough that it is not
 * instantaneous, or the test proves nothing.
 */
$dir = sys_get_temp_dir() . '/dhcrender-test-' . getmypid();
@mkdir($dir, 0775, true);
/* Noise, not a gradient: a gradient compresses to a few KB and would not
   be a meaningful stand-in for a real composite. */
$im = imagecreatetruecolor(1000, 1000);
mt_srand(7);
for ($y = 0; $y < 1000; $y++) {
	for ($x = 0; $x < 1000; $x += 4) {
		imagefilledrectangle($im, $x, $y, $x + 3, $y,
			imagecolorallocate($im, mt_rand(0,255), mt_rand(0,255), mt_rand(0,255)));
	}
}
$dest = $dir . '/f1-abc.png';
$tmp  = $dir . '/.f1-abc.png.tmp';
imagepng($im, $tmp, 6);
$bytes = filesize($tmp);
printf("  test png is %s bytes\n", number_format($bytes));
ok($bytes > 200000, 'the test image is too small to be a meaningful check');
ok(!file_exists($dest), 'the destination exists before the rename, so the '
 . 'write was not isolated');
rename($tmp, $dest);
ok(file_exists($dest) && filesize($dest) === $bytes,
   'the renamed file is not the complete one');
ok(@imagecreatefrompng($dest) !== false, 'the renamed file is not a readable PNG');
ok(!file_exists($tmp), 'the temp file survived the rename');

/* And the old way, for contrast: a reader during a direct write. */
$direct = $dir . '/direct.png';
$half   = (int)($bytes / 2);
file_put_contents($direct, substr(file_get_contents($dest), 0, $half));   /* what a caught mid-write looks like */
ok(is_file($direct), 'could not stage the truncated file');
ok(@imagecreatefrompng($direct) === false,
   'a half-written PNG still decodes, so truncation would not actually break '
 . 'a Discord embed and this diagnosis is wrong');
printf("  a %s-byte truncation of it is_file()s true and decodes false\n", number_format($half));

array_map('unlink', glob($dir . '/*') ?: array());
@rmdir($dir);

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all render cache checks passed\n";
