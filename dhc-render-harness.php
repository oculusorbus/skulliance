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

echo "\nthe embed carries its own image\n";
/*
 * The render race was real but it was never the whole story: RAIDS fail the
 * same way, and those use realm theme art that has existed for months, so
 * nothing about when a file was written explains them. What Fighters, trait
 * drops and raids share is that Discord's proxy has to reach this server at
 * post time, and a miss is cached against the URL.
 *
 * So discordmsg() uploads any image that lives on our own domain. These
 * checks drive the REAL function at a local server standing in for Discord
 * and read what actually arrived -- the shape of a multipart body is not
 * something to verify by reading it.
 */
$wh = file_get_contents(__DIR__ . '/webhooks.php');
ok(strpos($wh, 'CURLFile($attachfile') !== false,
   'discordmsg() no longer uploads the file, so Discord is back to fetching '
 . 'the URL at the one moment it is least likely to work');
ok(preg_match('/if \(\$attachok\) \{(?:(?!CURLOPT_HTTPHEADER).)*?\n            \} else/s', $wh) === 1,
   'the multipart branch sets its own Content-type; curl has to write the '
 . 'boundary itself or Discord rejects the post');

$port = 8791;
$root = __DIR__ . '/tmp-whtest-' . getmypid();
@mkdir($root, 0775, true);
/* files[0] arrives as $_FILES['files']['name'][0] -- PHP folds the index
   into every sub-key rather than giving one entry per file, so a naive
   read prints "Array". Flattened here. */
file_put_contents($root . '/hook.php',
    '<?php $f = array();' . "\n"
  . 'foreach ($_FILES as $v) {' . "\n"
  . '  $names = is_array($v["name"]) ? $v["name"] : array($v["name"]);' . "\n"
  . '  $sizes = is_array($v["size"]) ? $v["size"] : array($v["size"]);' . "\n"
  . '  foreach ($names as $i => $n) $f[] = array("name" => $n, "size" => $sizes[$i] ?? 0);' . "\n"
  . '}' . "\n"
  . 'file_put_contents(__DIR__ . "/got.txt", ($_POST["payload_json"] ?? file_get_contents("php://input")) . "\n---FILES---\n" . json_encode($f));' . "\n"
  . 'echo "ok";');
$im = imagecreatetruecolor(64, 64);
imagefilledrectangle($im, 0, 0, 63, 63, imagecolorallocate($im, 10, 200, 160));
imagepng($im, $root . '/shot.png');

/* A NONCE, BECAUSE THE PORT IS FIXED AND A STALE SERVER ANSWERS ON IT.
   This harness fataled once (discordmsg() gained a call to skl_embed_trim()
   and only discordmsg() was being lifted), which skipped the cleanup at the
   bottom and left a php -S from that run still listening on 8791, still
   serving the PREVIOUS run's directory. Every run after that posted to the
   old server, wrote got.txt somewhere else, and reported three confident
   failures about webhooks.php that had nothing to do with webhooks.php.
   That cost an hour. So: prove the thing answering is the server this run
   started, and say so plainly if it is not. */
$nonce = bin2hex(random_bytes(8));
file_put_contents($root . '/nonce.txt', $nonce);

$srv = proc_open('php -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($root),
    array(0 => array('file', '/dev/null', 'r'),
          1 => array('file', '/dev/null', 'w'),
          2 => array('file', '/dev/null', 'w')), $pipes);

/* AND KILL IT WHATEVER HAPPENS. The cleanup at the bottom of this file only
   runs when the file reaches the bottom; a fatal, an exception or a Ctrl-C
   all skip it, which is how the orphan above came to exist. */
register_shutdown_function(function () use (&$srv, $root) {
    if (is_resource($srv)) { @proc_terminate($srv); @proc_close($srv); $srv = null; }
    array_map('unlink', glob($root . '/*') ?: array());
    @rmdir($root);
});

for ($i = 0; $i < 40; $i++) {               /* wait for it, do not guess */
    $c = @fsockopen('127.0.0.1', $port, $e, $es, 0.2);
    if ($c) { fclose($c); break; }
    usleep(100000);
}
$whose = @file_get_contents('http://127.0.0.1:' . $port . '/nonce.txt');
if (trim((string)$whose) !== $nonce) {
    echo "  FAIL  something else is already listening on 127.0.0.1:$port -- almost\n"
       . "        certainly a php -S orphaned by an earlier run of this file that\n"
       . "        did not reach its cleanup. Every check below would fail against\n"
       . "        it and none of those failures would be about webhooks.php.\n"
       . "        Fix:  pkill -f 'php -S 127.0.0.1:$port'\n";
    exit(1);
}

/* The real functions, with the webhook pointed at our stand-in and __DIR__
   rebased so a "skulliance.io" URL resolves into the temp dir. */
function be2($src, $sig) {
    $at = strpos($src, $sig); if ($at === false) return '';
    $i = strpos($src, '{', $at); $d = 0;
    for ($j = $i; $j < strlen($src); $j++) {
        if ($src[$j] === '{') $d++;
        elseif ($src[$j] === '}') { $d--; if (!$d) return substr($src, $at, $j - $at + 1); }
    }
    return '';
}
$resolver = be2($wh, 'function skl_local_image_path($url)');
$resolver = str_replace('realpath(__DIR__)', 'realpath(' . var_export($root, true) . ')', $resolver);
eval($resolver);
/* discordmsg() CALLS skl_embed_trim(), so lifting one without the other is
   a fatal the moment the post is built -- which is exactly what happened
   when the embed-limit work added that call: this harness went red and
   stayed red, because nothing it prints before the fatal looks wrong.
   The limit constants come with it; they are read inside the trim. */
foreach (array('SKL_EMBED_TITLE_MAX', 'SKL_EMBED_DESC_MAX',
               'SKL_EMBED_FOOTER_MAX', 'SKL_EMBED_TOTAL_MAX') as $c) {
	if (!defined($c) && preg_match("/define\('" . $c . "',\s*(\d+)\)/", $wh, $m))
		define($c, (int)$m[1]);
}
if (!function_exists('skl_embed_trim')) eval(be2($wh, 'function skl_embed_trim('));

$fn = be2($wh, 'function discordmsg(');
/* EVERY assignment, and matched loosely: discordmsg picks its webhook
   through ~24 branches and several are ternaries
   (function_exists('getXWebhook') ? getXWebhook() : ""), so a pattern for
   a bare call misses exactly the channel this test uses. */
$fn = preg_replace('/\$webhook\s*=\s*[^;]+;/',
                   '$webhook = "http://127.0.0.1:' . $port . '/hook.php";', $fn);
eval($fn);

ok(skl_local_image_path('https://skulliance.io/staking/shot.png') !== '',
   'an image on our own domain is not being resolved to a file');
ok(skl_local_image_path('https://example.invalid/x.png') === '',
   'a THIRD PARTY url resolves to a local file, which it must never do');
/* A traversal that reaches a file which REALLY EXISTS outside the root --
   ../../etc/passwd resolves to nothing from here, so it passed with the
   guard removed and proved nothing. */
ok(skl_local_image_path('https://skulliance.io/staking/../webhooks.php') === '',
   'a traversal in the url escapes the web root and would upload a source '
 . 'file to Discord');
ok(skl_local_image_path('https://skulliance.io/staking/missing.png') === '',
   'a url with no file behind it still claims an attachment');

discordmsg('t', 'd', 'https://skulliance.io/staking/shot.png', '', 'dhcfighters', '', '00C8A0', null, null, '');
usleep(700000);
$got = @file_get_contents($root . '/got.txt');
ok(!empty($got), 'the stand-in webhook received nothing at all');
if (!empty($got)) {
    list($json, $files) = array_pad(explode("\n---FILES---\n", $got, 2), 2, '');
    $f = json_decode($files, true);
    $first = (is_array($f) && $f) ? reset($f) : array();
    printf("  uploaded %s (%s bytes)\n", $first['name'] ?? '(nothing)', $first['size'] ?? 0);
    ok(($first['size'] ?? 0) > 0, 'no file arrived with the post');
    ok(($first['name'] ?? '') === 'shot.png',
       'the upload lost its filename, which attachment:// refers to by name');
    ok(strpos($json, 'attachment://shot.png') !== false,
       'the embed points at a URL instead of the attachment it just uploaded');
    ok(strpos($json, 'https://skulliance.io/staking/shot.png') === false,
       'the original URL is still in the embed, so Discord would fetch it anyway');
}

/* A third-party image must behave exactly as before: URL, no upload. */
@unlink($root . '/got.txt');
discordmsg('t', 'd', 'https://example.invalid/outside.png', '', 'dhcfighters', '', '00C8A0', null, null, '');
usleep(700000);
$got2 = @file_get_contents($root . '/got.txt');
ok(!empty($got2) && strpos($got2, 'example.invalid/outside.png') !== false,
   'a third-party image URL no longer reaches the embed');
ok(!empty($got2) && strpos($got2, '---FILES---' . "\n" . '[]') !== false
   || (!empty($got2) && substr_count($got2, '"size"') === 0),
   'something was uploaded for a third-party URL');

if (is_resource($srv)) { proc_terminate($srv); proc_close($srv); }
array_map('unlink', glob($root . '/*') ?: array());
@rmdir($root);

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "all render cache checks passed\n";
