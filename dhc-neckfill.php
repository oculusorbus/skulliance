<?php
/**
 * dhc-neckfill.php -- builds the shared neck fill.
 *
 * THE PROBLEM. A head sits in the torso's neck socket, and some heads do not
 * reach the edges of it. The gap left over is a hole in the character's own
 * outline, so the BACKGROUND shows through it and the head reads as detached.
 * Reported on golden-cyborg + mk100, which leaves 93px of it at thumbnail size.
 *
 * THE FIX. One black layer, drawn immediately under the torso. Under, because
 * then it only has to be roughly right: anything it spills sideways or
 * downwards is hidden by the torso itself, and only its TOP edge is exposed.
 *
 * WHAT MAKES IT SAFE. A single shared shape is drawn beneath every torso, so
 * it has to be black only where black can never be wrong. A pixel qualifies
 * when, for EVERY combination the assembler can produce, it is either covered
 * (by the torso, the arms or the head) or enclosed by them -- that is, inside
 * a hole. Anywhere that is open sky for even one combination is disqualified
 * for all of them, because this one file cannot know which Fighter it is under.
 *
 * That is why this is generated and not drawn by hand: the condition is a
 * property of every piece of art in the game at once, and it changes whenever
 * a head, torso or arm is added. REGENERATE AFTER ANY NEW ART. The harness
 * checks the file is current and will fail if it is not.
 *
 * THE COMBINATIONS IT CHECKS:
 *   torso x head                       -- the plain case
 *   torso-noarms x arms x head         -- what the assembler actually draws
 *                                         when an Arms trait is equipped
 * The second matters: an armless torso has LESS coverage, so a fill that is
 * safe over the full torso can be exposed once the arms are swapped in.
 *
 * NO-HEAD IS DELIBERATELY NOT IN THE SET, and the renderer must not draw this
 * layer without a head. With no head the whole socket is open sky, so
 * including that case would shrink the fill to nothing -- which is correct,
 * and useless. dhcf_layers() only emits the layer when a head is present.
 *
 * ONE COMPUTATION, AT 1000, AND THE SMALLER SIZES ARE DERIVED FROM IT.
 *
 * This used to run the whole check once per size. That is not just 16x the
 * work for nothing -- it is two sources of truth. The 250px art is an
 * antialiased downscale of the 1000px art, so thresholding ITS alpha gives a
 * slightly different mask, and the two fills could disagree about a pixel with
 * nothing to say which was right. The art is the same drawing at both sizes,
 * so the shape is computed once at full resolution and reduced.
 *
 * THE REDUCTION IS CONSERVATIVE: a 250px pixel is filled only when ALL 16 of
 * its source pixels were safe. Downscaling can only ever shrink the shape that
 * way, and shrinking is the safe direction -- a fill that stops a pixel short
 * leaves a pixel of background, while one that overreaches puts black where
 * the picture should be open. (The reverse, computing small and upscaling,
 * has the opposite bias and is why it was not done that way.)
 *
 * IT LIVES IN THE REPO ROOT, NOT IN dhc/. Everything under dhc/ is gitignored
 * (it is the artist's source art), and a generator that is not in the repo is
 * one nobody can re-run after the art changes -- which is exactly when it is
 * needed. Its OUTPUT goes into dhc/web/ and ships by FTP like the rest of the art.
 *
 * Usage: php dhc-neckfill.php [--reduce-only]
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$size = 1000;                      /* the resolution the shape is COMPUTED at */
$derive = array(250);              /* sizes reduced from it afterwards */
/* --reduce-only re-derives the smaller sizes from the 1000px shape already on
   disk, without recomputing it. The reduction is the same code either way, so
   this is not a shortcut around the check -- it cannot invent coverage the
   full run did not already prove. Useful when the small sizes are missing or
   a new one is added; the fingerprint is carried over from the 1000px file,
   so a stale shape stays detectably stale. */
$reduceOnly = in_array('--reduce-only', $argv, true);
$root = __DIR__ . '/dhc/web/' . $size;
if (!is_dir($root)) { fwrite(STDERR, "no art at $root\n"); exit(1); }

function nf_bits($f, &$W, &$H) {
	$im = @imagecreatefrompng($f); if (!$im) return null;
	/* PALETTE PNGs give imagecolorat() an INDEX, not a colour, so the alpha
	   bits are meaningless -- half this collection is palette art. */
	if (!imageistruecolor($im)) imagepalettetotruecolor($im);
	$W = imagesx($im); $H = imagesy($im);
	$s = str_repeat("\0", $W * $H);
	for ($y = 0; $y < $H; $y++) for ($x = 0; $x < $W; $x++)
		if ((((imagecolorat($im, $x, $y) >> 24) & 0x7F)) < 64) $s[$y * $W + $x] = "\1";
	return $s;
}
/** Transparent pixels reachable from the frame edge: open sky for this build. */
function nf_sky($solid, $W, $H) {
	$N = $W * $H; $seen = str_repeat("\0", $N); $st = array();
	for ($x = 0; $x < $W; $x++) foreach (array(0, $H-1) as $y) {
		$k = $y*$W+$x; if ($solid[$k] === "\0") $st[] = $k; }
	for ($y = 0; $y < $H; $y++) foreach (array(0, $W-1) as $x) {
		$k = $y*$W+$x; if ($solid[$k] === "\0") $st[] = $k; }
	while ($st) {
		$k = array_pop($st);
		if ($seen[$k] === "\1") continue;
		$seen[$k] = "\1";
		$x = $k % $W; $y = intdiv($k, $W);
		if ($x > 0)    { $n=$k-1;  if ($seen[$n]==="\0" && $solid[$n]==="\0") $st[]=$n; }
		if ($x < $W-1) { $n=$k+1;  if ($seen[$n]==="\0" && $solid[$n]==="\0") $st[]=$n; }
		if ($y > 0)    { $n=$k-$W; if ($seen[$n]==="\0" && $solid[$n]==="\0") $st[]=$n; }
		if ($y < $H-1) { $n=$k+$W; if ($seen[$n]==="\0" && $solid[$n]==="\0") $st[]=$n; }
	}
	return $seen;
}
$W = 0; $H = 0;
$load = function ($dir) use ($root, &$W, &$H) {
	$o = array();
	foreach (glob("$root/$dir/*.png") as $f) { $b = nf_bits($f, $W, $H); if ($b) $o[basename($f,'.png')] = $b; }
	return $o;
};
$torso  = $load('torso');
$noarms = $load('torso-noarms');
$arms   = $load('arms');
$heads  = $load('head');
printf("%dpx: %d torso, %d torso-noarms, %d arms, %d heads\n",
	$size, count($torso), count($noarms), count($arms), count($heads));
if (!$torso || !$heads) { fwrite(STDERR, "missing art\n"); exit(1); }

$N = $W * $H;

if ($reduceOnly) {
	$have = "$root/neckfill/neckfill.png";
	if (!is_file($have)) { fwrite(STDERR, "--reduce-only needs $have\n"); exit(1); }
	$src = imagecreatefrompng($have);
	if (!imageistruecolor($src)) imagepalettetotruecolor($src);
	$safe = array();
	for ($y = 0; $y < $H; $y++) for ($x = 0; $x < $W; $x++)
		$safe[$y*$W+$x] = ((imagecolorat($src, $x, $y) >> 24) & 0x7F) < 64;
	$meta = @json_decode(@file_get_contents("$root/neckfill/neckfill.json"), true);
	$fingerprint = array();   /* carried over, not re-derived: see the flag note */
	$carriedHash = is_array($meta) && isset($meta['hash']) ? $meta['hash'] : '';
	$carriedN    = is_array($meta) && isset($meta['inputs']) ? (int)$meta['inputs'] : 0;
	printf("reducing the existing %dpx shape (%d px set)\n", $size, count(array_filter($safe)));
	nf_derive($safe, $W, $H, $size, $derive, $carriedHash, $carriedN);
	exit(0);
}

$sky = str_repeat("\0", $N);
$pairs = 0; $t0 = microtime(true);
foreach ($torso as $tb) {
	foreach ($heads as $hb) { $sky = $sky | nf_sky($tb | $hb, $W, $H); $pairs++; }
}
/* The armless variant is what actually gets drawn when an Arms trait is on,
   and it covers less -- so a fill that is safe under the full torso can be
   exposed under the armless one. Checked with the arms in place, because that
   is the only way the armless torso is ever drawn. */
foreach ($noarms as $nb) {
	foreach ($arms as $ab) {
		$body = $nb | $ab;
		foreach ($heads as $hb) { $sky = $sky | nf_sky($body | $hb, $W, $H); $pairs++; }
	}
}
printf("checked %d combinations in %.1fs\n", $pairs, microtime(true) - $t0);

/*
 * A FINGERPRINT OF THE INPUTS, so staleness is detectable.
 *
 * The shape is a property of every torso, arm and head in the game at once --
 * add one head and the safety condition changes, silently, with no error
 * anywhere. The harness compares this against the art on disk and fails if
 * they have diverged, which is the only thing that turns "remember to
 * regenerate" into something that cannot be forgotten.
 *
 * SLUGS AND SIZES, not mtimes: a re-upload with the same content should not
 * invalidate it, and a file that changed content SHOULD. Size is a cheap
 * proxy for content that costs nothing to collect.
 */
$fingerprint = array();
foreach (array('torso','torso-noarms','arms','head') as $d) {
	foreach (glob("$root/$d/*.png") as $f) $fingerprint[] = $d . '/' . basename($f) . ':' . filesize($f);
}
sort($fingerprint);

$img = imagecreatetruecolor($W, $H);
imagealphablending($img, false); imagesavealpha($img, true);
imagefill($img, 0, 0, imagecolorallocatealpha($img, 0, 0, 0, 127));
$black = imagecolorallocatealpha($img, 0, 0, 0, 0);
$n = 0;
for ($k = 0; $k < $N; $k++) if ($sky[$k] === "\0") { imagesetpixel($img, $k % $W, intdiv($k, $W), $black); $n++; }
$dir = "$root/neckfill";
if (!is_dir($dir) && !mkdir($dir, 0775, true)) { fwrite(STDERR, "cannot create $dir\n"); exit(1); }
imagepng($img, "$dir/neckfill.png");
printf("wrote %s/neckfill.png -- %d px (%.1f%% of frame)\n", $dir, $n, $n*100.0/$N);
file_put_contents("$dir/neckfill.json", json_encode(array(
	'generated' => gmdate('c'),
	'size'      => $size,
	'pixels'    => $n,
	'inputs'    => count($fingerprint),
	'hash'      => sha1(implode("\n", $fingerprint)),
), JSON_PRETTY_PRINT) . "\n");
printf("fingerprint: %s over %d files\n", substr(sha1(implode("\n", $fingerprint)), 0, 12), count($fingerprint));

$safe = array();
for ($k = 0; $k < $N; $k++) $safe[$k] = ($sky[$k] === "\0");
nf_derive($safe, $W, $H, $size, $derive, sha1(implode("\n", $fingerprint)), count($fingerprint));

/* ---- the smaller sizes, reduced from the shape just computed ---- */
function nf_derive($safe, $W, $H, $size, $derive, $hash, $inputs) {
foreach ($derive as $small) {
	$sroot = __DIR__ . '/dhc/web/' . $small;
	if (!is_dir($sroot)) { fwrite(STDERR, "  skip {$small}px: no art directory\n"); continue; }
	if ($W % $small !== 0) { fwrite(STDERR, "  skip {$small}px: $W is not a whole multiple\n"); continue; }
	$f = intdiv($W, $small);
	$out = imagecreatetruecolor($small, $small);
	imagealphablending($out, false); imagesavealpha($out, true);
	imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
	$blk = imagecolorallocatealpha($out, 0, 0, 0, 0);
	$m = 0;
	for ($y = 0; $y < $small; $y++) {
		for ($x = 0; $x < $small; $x++) {
			/* ALL of the source block, not any of it: the reduction must not
			   grow the shape, because growth is the direction that puts black
			   where the picture should be open. */
			$all = true;
			for ($dy = 0; $dy < $f && $all; $dy++) {
				for ($dx = 0; $dx < $f; $dx++) {
					if (!$safe[($y*$f+$dy)*$W + ($x*$f+$dx)]) { $all = false; break; }
				}
			}
			if ($all) { imagesetpixel($out, $x, $y, $blk); $m++; }
		}
	}
	$sdir = "$sroot/neckfill";
	if (!is_dir($sdir) && !mkdir($sdir, 0775, true)) { fwrite(STDERR, "cannot create $sdir\n"); continue; }
	imagepng($out, "$sdir/neckfill.png");
	/* The SAME fingerprint: it describes the inputs the shape came from, and
	   this file is that shape reduced, not a second computation. */
	file_put_contents("$sdir/neckfill.json", json_encode(array(
		'generated'  => gmdate('c'),
		'size'       => $small,
		'derived_from' => $size,
		'pixels'     => $m,
		'inputs'     => $inputs,
		'hash'       => $hash,
	), JSON_PRETTY_PRINT) . "\n");
	printf("wrote %s/neckfill.png -- %d px, reduced %dx from %dpx\n", $sdir, $m, $f, $size);
}
}
