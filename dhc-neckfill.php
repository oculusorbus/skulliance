<?php
/**
 * dhc-neckfill.php -- derive the small neck fill from the 1000px one.
 *
 * WHAT THE NECK FILL IS. A head sits in the torso's neck socket and not every
 * head reaches the edges of it. The gap left over is a hole in the character's
 * own outline, so the BACKGROUND shows through it and the head reads as
 * detached. One black block, drawn immediately under the torso, closes it for
 * every torso and head at once.
 *
 * It is drawn UNDER the torso, which is what makes a single hand-placed block
 * workable: everything it spills sideways or downwards is hidden by the torso
 * itself, so it only has to be roughly right. The 1000px block is AUTHORED, in
 * an image editor, like the rest of the art.
 *
 * WHY THIS IS NOT A GENERATOR ANY MORE. It was. It computed the block as the
 * region that is provably never open sky for any torso/arms/head combination --
 * 17,290 flood fills, about fourteen minutes, and a shape covering 21.7% of the
 * frame. All of that effort went into proving an exact edge for something that
 * is hidden under a torso and only has to be approximately right, and the
 * authored block does the same job at 1.38% of the frame. The proof was real
 * and the problem did not need one. Kept as a cautionary note rather than as
 * code: when the thing is hidden, approximate is the requirement.
 *
 * WHAT IS LEFT is the one step that genuinely must not be done by hand: the
 * 250px copy has to be the same drawing at a quarter scale, or it drifts from
 * the 250px torso and head art it sits between. Resampled, exactly as every
 * other trait's 250px art is, so the three line up.
 *
 * Usage: php dhc-neckfill.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$base = __DIR__ . '/dhc/web';
$src  = "$base/1000/neckfill/neckfill.png";
if (!is_file($src)) { fwrite(STDERR, "no $src -- author the 1000px block first\n"); exit(1); }

$in = imagecreatefrompng($src);
if (!$in) { fwrite(STDERR, "cannot read $src\n"); exit(1); }
if (!imageistruecolor($in)) imagepalettetotruecolor($in);
$W = imagesx($in); $H = imagesy($in);
printf("source %dx%d\n", $W, $H);

$made = 0;
foreach (array(250) as $size) {
	$dir = "$base/$size/neckfill";
	if (!is_dir(dirname($dir))) { fwrite(STDERR, "  skip {$size}px: no art directory\n"); continue; }
	if (!is_dir($dir) && !mkdir($dir, 0775, true)) { fwrite(STDERR, "cannot create $dir\n"); continue; }

	$out = imagecreatetruecolor($size, $size);
	/* ALPHA BLENDING OFF BEFORE THE FILL AND THE COPY, SAVE-ALPHA ON. With
	   blending left on, the transparent fill composites against black instead
	   of replacing it and the whole frame comes out opaque black -- which under
	   a torso is a solid black square over the background, not a neck fill. */
	imagealphablending($out, false);
	imagesavealpha($out, true);
	imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
	imagecopyresampled($out, $in, 0, 0, 0, 0, $size, $size, $W, $H);
	imagepng($out, "$dir/neckfill.png");

	/* No manifest beside it. The old generator wrote a fingerprint of every
	   torso, arm and head so staleness could be detected -- that mattered when
	   the shape was DERIVED from all of them and one new head could invalidate
	   it. An authored block is not derived from anything, so there is nothing
	   to go stale against, and a fingerprint here would only claim a guarantee
	   that no longer exists. */
	@unlink("$dir/neckfill.json");
	printf("wrote %s/neckfill.png (%dpx, resampled from %dpx)\n", $dir, $size, $W);
	$made++;
}
@unlink("$base/1000/neckfill/neckfill.json");
exit($made ? 0 : 1);
