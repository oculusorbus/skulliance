<?php
/* dhc-download-harness.php — CLI only.
 *
 * Covers the full-size Fighter download: dhcf_render_fighter()'s new $size
 * argument and the filename dhc-download.php hands the browser.
 *
 * THE THREE THINGS THAT CAN GO WRONG HERE:
 *
 *  1. THE 500 CACHE GETS ORPHANED. dhcrenders/ holds one file per Fighter
 *     under the plain name f<serial>-<hash>.png, built before this function
 *     took a size. Put the size in every filename and every one of them is
 *     abandoned at once, so Discord, the Arena and the landing thumbnails all
 *     recompose a stack of thousand-pixel layers the next time they ask.
 *     500 must keep the unsuffixed name; anything else must not collide.
 *
 *  2. THE SIZE-DEPENDENT MATHS STOPS TRACKING. The per-trait nudge is
 *     DHCF_NUDGE * $size / 1000, and until now nothing ever called this with
 *     a size other than 500 -- so the scaling was never exercised. If it
 *     breaks, a weapon sits somewhere different in the download than on the
 *     card, which is the exact "second composer" failure dhc-compose.php's
 *     header warns about, arriving through the back door. Checked by
 *     rendering both sizes and comparing the 1000 scaled down against the
 *     native 500 pixel by pixel: same picture, or the maths diverged.
 *
 *     THE ARM CLIP IS NOT POLICED BY THAT, and saying so is more useful than
 *     implying it is. DHCF_ONE_ARM_SPLIT has a deliberate ~200px tolerance
 *     window (the left arm ends by x=299, the right starts after x=723), so
 *     a wrong split still lands on plain torso and the pictures stay within
 *     noise -- measured: hardcoding the split to a 500px canvas moves the
 *     mean from 0.68 to 0.75. The Fighter below still wears a single-sided
 *     arm so the path RUNS, but only the nudge is actually asserted.
 *
 *  3. THE FILENAME. It is what the player sees in their downloads folder and
 *     usually what ends up typed into the post, and an unnamed Fighter's
 *     display name IS its serial -- so the naive "serial dash name" gives
 *     DHC2F0123-DHC2F0123.png.
 *
 * The art lives under dhc/, which is gitignored and deployed by FTP, so the
 * image half SKIPS with a loud note when it is not on this machine rather
 * than failing. The filename half always runs.
 *
 * Usage: php dhc-download-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/* The library loads its own rarity table and declares dhcf_rarity() itself --
   the stub dhc-assembler-harness.php needs is for the assembler, which does
   not include the library. Declaring one here is a fatal redeclare. */
require_once __DIR__ . '/dhcfighters-lib.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* ---------- 1. the filename ------------------------------------------------ */
/* The library is included above, so this is the REAL rule and not a copy. */
echo "filename\n";
$cases = array(
	array(array('serial' => 123, 'name' => null),                 'DHC2F123.png',
	      'unnamed: the serial alone, never DHC2F123-DHC2F123'),
	array(array('serial' => 123, 'name' => 'DHC2F123'),           'DHC2F123.png',
	      'named back to its own serial: still not doubled'),
	array(array('serial' => 7,   'name' => 'Skull Krusher'),      'DHC2F007-Skull-Krusher.png',
	      'named: padded serial, then the name'),
	array(array('serial' => 9,   'name' => '  ¡Hola! /../ x  '),  'DHC2F009-Hola-x.png',
	      'a name full of punctuation cannot escape the folder'),
	array(array('serial' => 9,   'name' => '¿¡...!?'),            'DHC2F009.png',
	      'a name with nothing usable in it falls back to the serial'),
);
foreach ($cases as $c) {
	list($row, $want, $why) = $c;
	$got = dhcf_download_filename($row);
	printf("  %-30s %s\n", $got, $got === $want ? 'ok' : "WANTED $want");
	ok($got === $want, $why);
}
/* The rule exists to be safe on a filesystem, so say that outright. */
foreach ($cases as $c) {
	$got = dhcf_download_filename($c[0]);
	ok(strpos($got, '/') === false && strpos($got, '\\') === false && strpos($got, '..') === false,
	   "filename is path-safe: $got");
	ok(strpos($got, '"') === false, "filename cannot break out of the Content-Disposition quotes: $got");
}

/* ---------- 2. the render ---------------------------------------------------- */
echo "\nrender\n";
$base = '';
foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
	if (is_dir(__DIR__ . '/' . $c . '/1000')) { $base = $c; break; }
}
if ($base === '' || !function_exists('imagecreatetruecolor')) {
	echo "  SKIPPED — no trait art on this machine (dhc/ is gitignored, FTP-deployed)\n";
	echo "            or no GD. The render checks did NOT run.\n";
} else {
	require_once __DIR__ . '/dhcfighters-notify.php';

	/*
	 * TWO FIGHTERS, and the second one is the point.
	 *
	 * The first wears every slot, so the layer order and the armless-torso
	 * swap get a turn. The second is built specifically to hit the only two
	 * places $size appears in any arithmetic: DHCF_NUDGE (the skull krusher
	 * and its sash, 23 and 13 at 1000px) and the single-sided-arm clip
	 * (head-chopper, which is the whole of DHCF_ONE_ARM). Without it the
	 * comparison below passes with the scaling hardcoded back to 500,
	 * because nothing in the picture depends on it -- which is exactly what
	 * happened the first time this harness was written.
	 */
	$pick = function ($dir, $n) use ($base) {
		$g = glob(__DIR__ . '/' . $base . '/1000/' . $dir . '/*.png');
		sort($g);
		return $g ? basename($g[$n % count($g)], '.png') : '';
	};
	/* A torso with an armless variant, so the hybrid clip has something to
	   clip. Read off disk rather than named, so the art can change. */
	$armless = glob(__DIR__ . '/' . $base . '/1000/torso-noarms/*.png');
	$hybridTorso = '';
	foreach ($armless as $a) {
		if (is_file(__DIR__ . '/' . $base . '/1000/torso/' . basename($a))) {
			$hybridTorso = basename($a, '.png'); break;
		}
	}

	$sets = array(
		'every slot' => array_filter(array(
			'background' => $pick('background', 3), 'torso'    => $pick('torso', 5),
			'arms'       => $pick('arms', 2),       'head'     => $pick('head', 7),
			'headgear'   => $pick('headgear', 4),   'weapon'   => $pick('weapon', 6),
			'companion'  => $pick('companion', 1),  'effects1' => $pick('effects', 3),
		)),
		'nudge + one-arm clip' => array_filter(array(
			'background' => $pick('background', 0), 'torso'      => $hybridTorso,
			'arms'       => 'head-chopper',         'head'       => $pick('head', 2),
			'weaponBack' => 'skull-krusher',        'weapon'     => 'skull-krusher-sash',
		)),
	);
	/* Prove the second set really does exercise them, rather than silently
	   degrading to another plain Fighter when the art moves. */
	$two = $sets['nudge + one-arm clip'];
	ok($hybridTorso !== '', 'found a torso with an armless variant to clip');
	ok(isset(DHCF_NUDGE[$two['weaponBack']]) && isset(DHCF_NUDGE[$two['weapon']]),
	   'the second Fighter carries nudged traits -- without them this whole '
	 . 'comparison passes with the scaling hardcoded');
	ok(dhcf_armless_mode($two['arms']) === 'hybrid',
	   'the second Fighter has a single-sided arm, so the clip maths runs');

	$serial = 999999;                       // far past the collection, cleaned up below
	$dir    = __DIR__ . '/dhcrenders';
	foreach (glob($dir . '/f' . $serial . '-*') as $f) @unlink($f);

	foreach ($sets as $label => $traits) {
		$hash  = substr(md5(json_encode($traits)), 0, 8);
		$plain = $dir . '/f' . $serial . '-' . $hash . '.png';
		$big   = $dir . '/f' . $serial . '-' . $hash . '@1000.png';

		$u500  = dhcf_render_fighter($traits, $serial, 500);
		$u1000 = dhcf_render_fighter($traits, $serial, 1000);
		ok($u500 !== '' && $u1000 !== '', "$label: both sizes rendered");

		ok(is_file($plain), "$label: the 500 still writes the UNSUFFIXED name -- anything "
		                  . 'else orphans every render already in dhcrenders/');
		ok(is_file($big),   "$label: the 1000 writes its own file");
		ok($plain !== $big, "$label: the two sizes cannot land on the same file");

		$a = @getimagesize($plain); $b = @getimagesize($big);
		ok($a && $a[0] === 500,  "$label: the 500 is 500px");
		ok($b && $b[0] === 1000, "$label: the 1000 is really 1000px -- the masters are "
		                       . '1000, so this is the largest size that is detail not blur');

		/* Same picture, or the size-dependent maths diverged. Downscale the
		   1000 with the same resampler the renderer uses and compare against
		   the native 500. Resampling is lossy, so this is a mean difference
		   and not equality; a nudge that failed to scale moves a whole layer
		   and lands far above it. */
		$mean = 255.0;
		$im500 = @imagecreatefrompng($plain);
		$im1k  = @imagecreatefrompng($big);
		if ($im500 && $im1k) {
			$down = imagecreatetruecolor(500, 500);
			imagealphablending($down, false); imagesavealpha($down, true);
			imagecopyresampled($down, $im1k, 0, 0, 0, 0, 500, 500, 1000, 1000);
			$sum = 0; $n = 0;
			for ($y = 4; $y < 500; $y += 3) {
				for ($x = 4; $x < 500; $x += 3) {
					$p = imagecolorat($im500, $x, $y); $q = imagecolorat($down, $x, $y);
					$sum += abs((($p >> 16) & 255) - (($q >> 16) & 255))
					      + abs((($p >> 8)  & 255) - (($q >> 8)  & 255))
					      + abs(( $p        & 255) - ( $q        & 255));
					$n += 3;
				}
			}
			$mean = $n ? $sum / $n : 255;
			unset($down, $im500, $im1k);
		}
		printf("  %-22s 500px %6.1f KB   1000px %6.1f KB   mean diff %5.2f / 255\n",
			$label, filesize($plain)/1024, filesize($big)/1024, $mean);
		/*
		 * THE THRESHOLD IS MEASURED, not guessed. A correct pair differs by
		 * resampling loss alone: 0.07 for the plain Fighter, 0.68 for the
		 * nudged one. Hardcode the nudge back to `* 500 / 1000` and it goes
		 * to 2.46. 1.5 sits between them with room either side.
		 */
		ok($mean < 1.5, sprintf('%s: the 1000 is the same picture as the 500 (mean %.2f, '
		                      . 'expected under 0.7) -- a nudge that does not scale with '
		                      . '$size puts a layer somewhere else in the download than on '
		                      . 'the card', $label, $mean));

		/* $size is clamped: a caller asking for 4000 gets the master size, not
		   a 4000px upscale of 1000px art and the memory to match. */
		$u4k = dhcf_render_fighter($traits, $serial, 4000);
		$s4k = $u4k === '' ? null : @getimagesize($dir . '/' . basename(parse_url($u4k, PHP_URL_PATH)));
		ok($s4k && $s4k[0] === 1000, "$label: a request above 1000 is clamped to the master size");
	}

	foreach (glob($dir . '/f' . $serial . '-*') as $f) @unlink($f);
	echo "  (test renders cleaned up)\n";
}

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all download checks passed\n");
exit($fail ? 1 : 0);
