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
	/* FROM THE CROP, not from the canvas. The trait branch crops the source
	   to its own ink and then re-reads $sw/$sh, and both of those decide
	   where the art lands -- lifting only from the canvas down left the one
	   step that was actually wrong outside the test. */
	$a = strpos($clean, "if (\$traitq !== '' && function_exists('imagecropauto')) {");
	ok($a !== false, 'the compositing block moved; this check is now blind');
	$b = strpos($clean, 'imagecopyresampled($card, $src, (int)((DHCC_W - $fw)');
	$b = strpos($clean, ";\n", $b);
	$block = substr($clean, $a, $b - $a + 1);

	/* The real block references the constants by name, so define them from
	   the values just read out of the file rather than hardcoding a second
	   copy here -- that way changing them in dhc-card.php moves this test
	   with it instead of silently diverging from it. */
	foreach (array('DHCC_W' => $W, 'DHCC_H' => $H) as $cname => $cval) {
		if (!defined($cname)) define($cname, $cval);
	}
	/* Every other constant the block uses, read out of the file the same
	   way, so adding one there does not mean a fatal here. */
	if (preg_match_all('/const (DHCC_[A-Z]+) = (-?\d+);/', $clean, $cm, PREG_SET_ORDER)) {
		foreach ($cm as $c) if (!defined($c[1])) define($c[1], (int)$c[2]);
	}

	/*
	 * A CHECKERBOARD, not a flat fill. The backdrop checks below ask whether
	 * the blur is soft and whether it is dark, and a flat source answers
	 * both by accident -- it has no detail to turn into a mosaic and no
	 * brightness to leave in. Both mutations (one-jump upscale, and the dim
	 * reduced to nothing) passed against a plain red square. Fine detail in
	 * bright colours is what the real trait art looks like to a downscaler.
	 */
	$sw = $sh = 1000;
	$src = imagecreatetruecolor($sw, $sh);
	$hot  = imagecolorallocate($src, 245, 215, 70);
	$cold = imagecolorallocate($src, 210, 40, 40);
	for ($cx = 0; $cx < $sw; $cx += 25) {
		for ($cy = 0; $cy < $sh; $cy += 25) {
			imagefilledrectangle($src, $cx, $cy, $cx + 24, $cy + 24,
				((($cx / 25) + ($cy / 25)) % 2) ? $hot : $cold);
		}
	}
	/* Markers on the extreme top and bottom rows of the source art. */
	imagefilledrectangle($src, 0, 0, $sw, 6, imagecolorallocate($src, 0, 255, 0));
	imagefilledrectangle($src, 0, $sh - 6, $sw, $sh, imagecolorallocate($src, 0, 0, 255));

	/* A FIGHTER, so no crop: the render fills its canvas and cropping it
	   would do nothing. The branch is what decides that, and it reads
	   $traitq, so this run has to say which kind it is. */
	$traitq = '';
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

	/*
	 * AND IT HAS TO BE SOFT. The first version jumped 28px straight to
	 * 1200px; GD stops interpolating usefully at that ratio and renders hard
	 * squares -- a mosaic that reads as a rendering fault rather than a
	 * backdrop. That shipped, and I only saw it by looking at a real card.
	 *
	 * MEASURING IT TOOK THREE TRIES. Neighbour-difference does not separate
	 * the two: the -110 dim clamps most of the backdrop towards black, so a
	 * hard edge and a soft one both come out as a small difference. What
	 * does separate them is the LONGEST RUN OF IDENTICAL PIXELS -- a mosaic
	 * block survives the clamp as one flat slab. Measured on this fixture:
	 * 86 staged, 341 one-jump. 200 sits between them with room either side.
	 */
	$maxrun = 0;
	for ($y = 120; $y < $H - 110; $y += 40) {
		$run = 0; $prev = null;
		for ($x = 10; $x < 290; $x++) {
			$p = imagecolorat($card, $x, $y);
			if ($prev !== null) {
				if ($p === $prev) { $run++; if ($run > $maxrun) $maxrun = $run; }
				else $run = 0;
			}
			$prev = $p;
		}
	}
	ok($maxrun < 200,
	   "the backdrop has a $maxrun-pixel run of identical colour; it is a mosaic, not a blur");

	/* And pushed back far enough that it is a ground, not a second picture. */
	$lum = 0; $n = 0;
	for ($x = 10; $x < 260; $x += 10) for ($y = 40; $y < $H - 40; $y += 40) {
		$c = imagecolorat($card, $x, $y);
		$lum += 0.2126 * (($c >> 16) & 255) + 0.7152 * (($c >> 8) & 255) + 0.0722 * ($c & 255);
		$n++;
	}
	$avg = $lum / max(1, $n);
	ok($avg < 70, sprintf('the backdrop averages %.0f/255 luminance; that competes with the Fighter', $avg));

	/*
	 * A TRAIT IS NOT A FIGHTER, AND THAT IS THE WHOLE DIFFICULTY.
	 *
	 * A Fighter render fills its 1000x1000 canvas. A trait layer does not: it
	 * is drawn at the position it occupies ON a body, so a belt is a band
	 * across the lower third of an otherwise empty square. The first version
	 * of the trait card fitted that whole square to the card height, and the
	 * belt arrived small, low, and sitting on a near-black wash -- technically
	 * the trait, visibly nothing. I only saw it by looking at a real render.
	 *
	 * The fixture is that shape exactly: transparent, with one opaque band.
	 * Two things have to be true of where it lands, and they fail to
	 * OPPOSITE mutations, which is why both are here:
	 *
	 *   drop the crop          -> the band is scaled by 594/1000 and lands
	 *                             small and off-centre, as it shipped
	 *   drop the width bound   -> the cropped band is scaled by 594/180 and
	 *                             runs off both edges of the card
	 */
	echo "\na trait fills the card instead of floating in it\n";

	/*
	 * BOTH COLOUR MODELS, because a trait PNG can be either and the crop
	 * behaves differently in each.
	 *
	 * IMG_CROP_TRANSPARENT -- what this shipped with -- compares every pixel
	 * against imagecolortransparent(), a SINGLE palette index, and 22 of the
	 * 235 trait PNGs do not carry a usable one: 18 are truecolor with an
	 * alpha channel (no index at all), and four are palette files whose index
	 * is not the colour their border uses. IMG_CROP_SIDES decides from the
	 * corner colours instead and handles all of them, which is why it now
	 * runs first.
	 *
	 * Both synthetic fixtures below fail if the SIDES mode goes, and the
	 * check after them runs the shipped crop over the REAL art, which is the
	 * only thing that can speak for 235 files.
	 */
	$band = function ($palette) {
		$im = imagecreatetruecolor(1000, 1000);
		imagesavealpha($im, true);
		imagealphablending($im, false);
		imagefilledrectangle($im, 0, 0, 1000, 1000, imagecolorallocatealpha($im, 0, 0, 0, 127));
		/* Full width, a fifth of the height, low down: a belt. */
		imagefilledrectangle($im, 0, 700, 999, 879, imagecolorallocate($im, 40, 220, 90));
		imagealphablending($im, true);
		if (!$palette) return $im;
		/* Round-tripped through a real PNG, which is how the shipped code
		   gets its source -- imagecreatefrompng() is what decides whether a
		   transparent index exists, and building the palette in memory does
		   not reproduce that. */
		$f = tempnam(sys_get_temp_dir(), 'dhcc') . '.png';
		imagetruecolortopalette($im, false, 255);
		imagecolortransparent($im, imagecolorexact($im, 0, 0, 0));
		imagepng($im, $f);
		$back = imagecreatefrompng($f);
		@unlink($f);
		return $back;
	};

	/* Where the ink actually is in the finished card. The backdrop is the
	   same art blurred and dimmed by DHCC_DIM, so only the sharp copy can
	   still be this bright. */
	$isInk = function ($c) {
		return (($c >> 8) & 255) > 170 && (($c >> 16) & 255) < 130;
	};

	foreach (array('palette, with a transparent index' => true,
	               'truecolor, with an alpha channel'  => false) as $what => $pal) {
		$src = $band($pal);
		$sw = imagesx($src); $sh = imagesy($src);
		$traitq = 'weapon:annihilation-belt';
		eval($block);

		/* The band's own rows first, THEN its extent along one of them. A
		   horizontal scan down the middle row reported "not in the card at
		   all" for the uncropped case, where the band is really present and
		   merely small and low -- a true failure with a misleading reason is
		   the next bug to chase. */
		$top2 = -1; $bot2 = -1;
		for ($y = 0; $y < $H; $y++) {
			if ($isInk(imagecolorat($card, (int)($W / 2), $y))) { if ($top2 < 0) $top2 = $y; $bot2 = $y; }
		}
		$left = -1; $right = -1;
		if ($top2 >= 0) {
			$row = (int)(($top2 + $bot2) / 2);
			for ($x = 0; $x < $W; $x++) {
				if ($isInk(imagecolorat($card, $x, $row))) { if ($left < 0) $left = $x; $right = $x; }
			}
		}
		ok($left >= 0 && $top2 >= 0, "the trait is not in the card at all ($what)");
		if ($left < 0 || $top2 < 0) continue;

		$drawn = $right - $left + 1;
		ok($drawn > 1000,
		   "the trait is only {$drawn}px wide on a {$W}px card ($what); it is being fitted "
		 . 'as if it filled its canvas, which is how the belt shipped small and low');
		/* And not so wide that it leaves. A share card with the subject
		   bleeding off both sides is the other half of the same mistake. */
		ok($left >= 20 && $right <= $W - 20,
		   "the trait runs from x={$left} to x={$right} on a {$W}px card ($what); the width no longer bounds the fit");
		/* Centred, which the uncropped version was not: the ink sat in the
		   lower third of a centred square. */
		$mid = ($top2 + $bot2) / 2;
		ok(abs($mid - $H / 2) < 8,
		   sprintf('the trait centres on y=%.0f, not %d (%s); it is sitting where it would on a body',
		           $mid, $H / 2, $what));
	}

	/*
	 * AND NOW THE REAL ART, all of it.
	 *
	 * The fixtures prove the geometry; only the files can prove the crop
	 * reaches them. The shipped $cropped helper is lifted out and run over
	 * every trait PNG in the repo, asserting three things: the known-sparse
	 * traits shrink (these are the ones that rendered as a speck), nothing
	 * degenerates to a sliver, and art that fills its canvas on purpose is
	 * left alone.
	 *
	 * Named files rather than a count, because a count passes while the
	 * wrong 22 are cropped. Each name below is a trait a player can be
	 * holding right now, and tank-helmet and axe are specifically the two
	 * that IMG_CROP_TRANSPARENT could never touch.
	 */
	$artdir = '';
	foreach (array('web', 'dhc', 'dhc/web', 'traits') as $cand) {
		if (is_dir(__DIR__ . '/' . $cand . '/1000')) { $artdir = __DIR__ . '/' . $cand . '/1000'; break; }
	}
	if ($artdir === '') {
		echo "\n  (no trait art in this checkout -- real-art crop not run)\n";
	} else {
		echo "\nevery sparse trait actually crops\n";
		$ca = strpos($clean, '$cropped = function ($im, $mode) {');
		$cb = strpos($clean, "\n\t};", $ca);
		ok($ca !== false && $cb !== false, 'the crop helper moved; the real-art check is blind');
		eval(substr($clean, $ca, $cb - $ca + 4));
		/* The real chain, in the real order, read out of the file so a
		   reordering here is not a second opinion about it. */
		preg_match_all('/\$cropped\(\$src, (IMG_CROP_[A-Z]+)\)/', $clean, $modes);
		ok(count($modes[1]) >= 1, 'nothing calls the crop helper any more');

		$crop_real = function ($file) use ($cropped, $modes) {
			$im = @imagecreatefrompng($file);
			if (!$im) return null;
			foreach ($modes[1] as $mode) {
				$c = $cropped($im, constant($mode));
				if ($c !== null) return array(imagesx($c), imagesy($c), imagesx($im), imagesy($im));
			}
			return array(imagesx($im), imagesy($im), imagesx($im), imagesy($im));
		};

		/* Sparse art: a weapon, a helmet, a companion, a neck filler. Mixed
		   palette and truecolor on purpose. */
		foreach (array('weapon/annihilation-belt', 'weapon/axe', 'weapon/bioweapon',
		               'headgear/tank-helmet', 'headgear/dh-samurai-helmet',
		               'companion/demon-companion', 'neckfill/neckfill') as $rel) {
			$f = $artdir . '/' . $rel . '.png';
			if (!is_file($f)) continue;
			$r = $crop_real($f);
			ok($r !== null && ($r[0] < $r[2] || $r[1] < $r[3]),
			   $rel . ' is not cropped to its ink (' . ($r ? $r[0] . 'x' . $r[1] : 'unreadable')
			 . '); its card renders the trait as a speck low on a dark slab');
		}

		/* Nothing may come back a sliver, on any file. */
		$slivers = array();
		foreach (glob($artdir . '/*/*.png') as $f) {
			$r = $crop_real($f);
			if ($r !== null && ($r[0] <= 8 || $r[1] <= 8))
				$slivers[] = basename(dirname($f)) . '/' . basename($f) . ' ' . $r[0] . 'x' . $r[1];
		}
		ok(!$slivers, count($slivers) . ' trait(s) crop to a sliver: ' . implode(', ', array_slice($slivers, 0, 4)));

		/* And the flat colour swatches, which are entirely one colour, must
		   come through untouched rather than crop to nothing. */
		foreach (array('background/black', 'background/white') as $rel) {
			$f = $artdir . '/' . $rel . '.png';
			if (!is_file($f)) continue;
			$r = $crop_real($f);
			ok($r !== null && $r[0] === $r[2] && $r[1] === $r[3],
			   $rel . ' was cropped (' . ($r ? $r[0] . 'x' . $r[1] : '?') . '); a flat background has no ink to crop to');
		}
	}

	/* A FIGHTER MUST NOT BE CROPPED. Its render fills the canvas, so there is
	   nothing to trim -- but IMG_CROP_SIDES decides from corner colour, and a
	   Fighter on a flat-coloured background has uniform corners. Dropping the
	   $traitq guard would start trimming the background off Fighter cards,
	   which is a silent change to every share that already works. */
	ok(preg_match("/if \\(\\\$traitq !== '' && function_exists\\('imagecropauto'\\)\\)/", $clean) === 1,
	   'the crop is no longer gated on the trait branch, so Fighter cards get cropped too');
}

echo "\nit draws through the one renderer, and only for saved Fighters\n";
ok(strpos($clean, 'dhcf_render_fighter(') !== false,
   'the card composes its own Fighter instead of calling dhcf_render_fighter(); that is the bug class that cost the Arena its effects twice');
ok(strpos($clean, 'disassembled_at IS NULL') !== false,
   'a disassembled Fighter still has a card');
ok(strpos($clean, "_GET['build']") === false && strpos($clean, '$_GET[\'traits\']') === false,
   'the card accepts an arbitrary build; that is a way to walk the trait art out without owning any of it');
/* Not bind_param: prepared statements are unusable here (no mysqlnd, see
   below). The platform's own guarantee is the CAST, so that is what is
   checked -- the serial must be (int) before it is anywhere near a query. */
ok(preg_match('/\$serial = isset\(\$_GET\[\'serial\'\]\) \? \(int\)\$_GET\[\'serial\'\] : 0;/', $clean) === 1,
   'the serial is not cast to an integer on the way in');
ok(preg_match('/WHERE serial = \$serial\b/', $clean) === 1,
   'the query no longer uses the cast $serial; anything else here is an injection');

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

/*
 * THE SERVER HAS NO mysqlnd, SO get_result() IS A FATAL.
 *
 * mysqli_stmt::get_result() is only compiled in when mysqli is built
 * against mysqlnd, and this host's is not. The method simply does not
 * exist there -- "Call to undefined method mysqli_stmt::get_result()",
 * fatal, whole page gone. php -l cannot see it (the parser has no opinion
 * about which methods a class has at runtime) and neither can any local
 * test, because the CLI php here DOES have mysqlnd.
 *
 * This shipped in both of these files and took down every shared
 * ?fighter= link. The constraint was already written down in
 * ajax/cache-nft-image.php -- "mysqli on this server is built without
 * mysqlnd, so get_result() is unavailable, stick with the codebase's
 * standard string-concat + query() pattern" -- and I did not look.
 *
 * Checked across the WHOLE repo, not just these two files: the next person
 * to reach for a prepared statement will hit it in some third place.
 */
echo "\nnothing calls get_result(), which does not exist on this server\n";
{
	$offenders = array(); $scanned = 0;
	$needle = 'get_' . 'result(';   /* split, or this file matches itself */
	$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__,
		FilesystemIterator::SKIP_DOTS));
	foreach ($it as $f) {
		if ($f->getExtension() !== 'php') continue;
		$path = $f->getPathname();
		/* Skip vendored code and this file. A library shipping its own
		   mysqlnd path is not this platform's problem, and a harness that
		   names the thing it is banning is not an offender. */
		if (preg_match('#/(\.git|vendor|node_modules)/#', $path)) continue;
		if ($path === __FILE__) continue;
		$scanned++;
		if (strpos(no_comments(file_get_contents($path)), $needle) !== false) {
			$offenders[] = str_replace(__DIR__ . '/', '', $path);
		}
	}
	ok(!$offenders,
	   $needle . ') is called in: ' . implode(', ', $offenders)
	 . ' -- this server\'s mysqli has no mysqlnd, so that is a fatal in production '
	 . 'and invisible to php -l and to any local test');
	printf("  %d php files scanned, %d offender(s)\n", $scanned, count($offenders));
}

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

/*
 * AND AT THE TRAIT, which is what the drop modal posts.
 *
 * A trait share is a link to dhcgallery.php?trait=, and everything the post
 * looks like comes from that page's head: the card, the headline X renders
 * above it, and the sentence under that. Without the trait branch the page
 * served the Collection's own title, so a post about a single legendary
 * trait unfurled as "The Fighter Collection" over a picture of the trait --
 * the two halves describing different things.
 */
ok(strpos($g, 'dhc-card.php?trait=') !== false,
   'the gallery does not declare a card for a trait, so a shared drop carries the house logo');
ok(preg_match("/if \\(\\\$dhcg_trait !== ''\\) \\{\s*\n\s*list\\(\\\$tcat0/", $g) === 1,
   'the trait branch is gone from the head builder');
/* The branch exists for the two strings in it. Asserting only that it is
   there passed with the title line deleted, which is the exact bug: a card
   of one trait under a headline about the whole Collection. */
ok(preg_match('/\\$dhcg_title = \\$tname0 \\./', $g) === 1,
   'the trait view has no title of its own; a shared trait unfurls as the whole Collection');
ok(preg_match('/\\$dhcg_desc\\s*= \\$tname0 \\./', $g) === 1,
   'the trait view has no description of its own; X renders the Collection blurb under the trait');
/* The order is the property: the general case sets the title, the trait
   case overrides it, and the single-Fighter case overrides that. Put the
   trait block last and a shared Fighter would unfurl as a trait. */
$tAt = strpos($g, 'if ($dhcg_trait !== \'\') {');
$gAt = strpos($g, '$dhcg_title = ($dhcg_tier');
$fAt = strpos($g, "\$dhcg_canonical = 'https://www.skulliance.io/staking/dhcgallery.php?fighter='");
ok($gAt !== false && $tAt !== false && $fAt !== false && $gAt < $tAt && $tAt < $fAt,
   'the title cases are out of order; general, then trait, then the single Fighter');
/* The canonical has to name the trait too, or every trait share points
   search engines and X's cache at the same unfiltered page. */
ok(strpos($g, "\$dhcg_canonical .= '?trait=' . rawurlencode(\$dhcg_trait);") !== false,
   'a shared trait is canonicalised to the whole Collection, so every trait shares one cached card');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
