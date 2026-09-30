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

/* ---------- 3. the endpoint itself ------------------------------------------ */
/*
 * Everything above tests the renderer. This tests dhc-download.php, over real
 * HTTP, because what breaks it is not something a function call can show: the
 * response headers, and whether ANY stray output got in front of the PNG.
 * db.php runs with display_errors on and prints nothing today -- add one echo
 * or raise one notice and every download becomes a corrupt file with no error
 * anywhere. The endpoint buffers the include for exactly that reason, so the
 * stub below deliberately prints while loading and the response still has to
 * begin with the PNG magic.
 *
 * THE DATABASE IS STUBBED BY SHADOWING db.php ON include_path. The endpoint
 * says `include 'db.php'` with no leading dot, which searches include_path
 * first, so a db.php in the temp directory wins. Everything else -- the
 * library, the renderer, the art, the headers -- is the real thing.
 *
 * TWO TRAPS, both hit writing this:
 *
 *   shell_exec() BLOCKS on a backgrounded `php -S`, redirects and all, because
 *   it reads the pipe until EOF and the server never closes it. proc_open with
 *   the descriptors pointed at /dev/null is the way to start it and not wait.
 *
 *   $http_response_header is deprecated in PHP 8.5 and prints a notice per
 *   request, so the request is written on a raw socket instead. That also
 *   makes the status line and the header block ordinary strings rather than
 *   something the stream wrapper has already interpreted.
 *
 * Anything that cannot be set up SKIPS loudly. A harness that hangs is worse
 * than one that says it did not run.
 */
echo "\nendpoint\n";
$stub = sys_get_temp_dir() . '/dhc-dl-stub-' . getmypid();
$port = 0;
if ($sock = @stream_socket_server('tcp://127.0.0.1:0', $e1, $e2)) {
	$nm   = stream_socket_get_name($sock, false);
	$port = (int)substr($nm, strrpos($nm, ':') + 1);
	fclose($sock);                       // released, then handed straight to php -S
}
if ($base === '' || !$port || !@mkdir($stub, 0777, true)) {
	echo "  SKIPPED — no trait art, or could not get a port / temp dir.\n";
} else {
	$traits = json_encode(array(
		'background' => $pick('background', 3), 'torso'  => $pick('torso', 5),
		'head'       => $pick('head', 7),       'weapon' => $pick('weapon', 6),
	));
	file_put_contents($stub . '/db.php',
		"<?php\n"
	  . "class StubRes { public \$r; function __construct(\$r){ \$this->r = \$r; }\n"
	  . "  function fetch_assoc(){ \$r = \$this->r; \$this->r = null; return \$r; } }\n"
	  . "class StubConn { function query(\$sql){\n"
	  . "    if (!preg_match('/serial = (\\d+)/', \$sql, \$m)) return false;\n"
	  . "    if ((int)\$m[1] !== 4242) return new StubRes(null);\n"
	  . "    return new StubRes(array('id'=>17,'serial'=>4242,'name'=>'Deep Sea Krusher',\n"
	  . "      'traits'=>" . var_export($traits, true) . ")); }\n"
	  . "  function close(){} }\n"
	  . "\$conn = new StubConn();\n"
	  . "/* THE POINT OF THE STUB: db.php is allowed to print, and none of it may\n"
	  . "   reach the image. */\n"
	  . "echo \"db.php printed this and it must never reach the PNG\\n\";\n");

	$null = defined('PHP_OS_FAMILY') && PHP_OS_FAMILY === 'Windows' ? 'NUL' : '/dev/null';
	$proc = @proc_open(
		/* output_buffering=0 ON PURPOSE. With the ini's default 4096 on, PHP
		   buffers db.php's echo for free and the endpoint looks safe even
		   with its own ob_start() deleted -- measured, that revert passed.
		   The live server's ini is not this machine's, so the test pins it
		   off and makes the code's own buffering do the work. */
		array(PHP_BINARY, '-d', 'include_path=' . $stub, '-d', 'output_buffering=0',
		      '-S', '127.0.0.1:' . $port, '-t', __DIR__),
		array(0 => array('file', $null, 'r'),
		      1 => array('file', $null, 'w'),
		      2 => array('file', $null, 'w')),
		$pipes, $stub);

	$up = false;
	for ($i = 0; $i < 50 && is_resource($proc); $i++) {
		if ($c = @fsockopen('127.0.0.1', $port, $en, $es, 0.2)) { fclose($c); $up = true; break; }
		usleep(100000);                  // 5s ceiling; the server binds in well under one
	}
	if (!$up) {
		echo "  SKIPPED — the built-in server never came up on port $port.\n";
	} else {
		/** One raw HTTP/1.0 request. Returns array(status line, headers, body). */
		$get = function ($qs) use ($port) {
			$fp = @fsockopen('127.0.0.1', $port, $en, $es, 5);
			if (!$fp) return array('', array(), '');
			stream_set_timeout($fp, 20);
			fwrite($fp, "GET /dhc-download.php" . $qs . " HTTP/1.0\r\n"
			          . "Host: 127.0.0.1:$port\r\nConnection: close\r\n\r\n");
			$raw = stream_get_contents($fp);
			fclose($fp);
			$cut = strpos($raw, "\r\n\r\n");
			if ($cut === false) return array('', array(), $raw);
			$head = explode("\r\n", substr($raw, 0, $cut));
			return array(array_shift($head), $head, substr($raw, $cut + 4));
		};
		$hdr = function ($head, $name) {
			foreach ($head as $h) {
				if (stripos($h, $name . ':') === 0) return trim(substr($h, strlen($name) + 1));
			}
			return '';
		};

		list($status, $head, $png) = $get('?serial=4242');
		printf("  %s  %d bytes  %s\n", $status, strlen($png), $hdr($head, 'Content-Disposition'));

		ok(strpos($status, '200') !== false, 'the endpoint answers 200 for a real Fighter');
		ok(substr($png, 0, 8) === "\x89PNG\r\n\x1a\n",
		   'the response STARTS with the PNG magic -- db.php printed while loading, and '
		 . 'one byte of that in front makes every download a corrupt file');
		ok(strpos($png, 'must never reach the PNG') === false,
		   "the stub's output leaked into the image body");
		file_put_contents($stub . '/out.png', $png);
		$sz = @getimagesize($stub . '/out.png');
		ok($sz && $sz[0] === 1000 && $sz[1] === 1000,
		   'the bytes on the wire are a real 1000x1000 PNG');
		ok($hdr($head, 'Content-Type') === 'image/png', 'served as image/png');
		ok($hdr($head, 'Content-Disposition')
		   === 'attachment; filename="DHC2F4242-Deep-Sea-Krusher.png"',
		   'the browser is told to save it, under the name the library decided');
		ok((int)$hdr($head, 'Content-Length') === strlen($png),
		   'Content-Length matches the body');

		/* A failure must be plain text with a real status, never a part-image. */
		foreach (array('' => 400, '?serial=0' => 400, '?serial=abc' => 400,
		               '?serial=-5' => 400, '?serial=9999' => 404) as $qs => $want) {
			list($st, $h2, $body) = $get($qs);
			ok(strpos($st, (string)$want) !== false,
			   "'$qs' should answer $want, answered: " . $st);
			ok(strpos($body, "\x89PNG") === false,
			   "'$qs' returned image bytes for a request with no Fighter behind it");
		}
		echo "  error paths: no serial / 0 / abc / -5 -> 400, unknown serial -> 404\n";

		/* The render cache is the whole reason this is affordable at all. */
		$t0 = microtime(true); $get('?serial=4242'); $warm = (microtime(true) - $t0) * 1000;
		printf("  warm request: %.1f ms (cold is ~125)\n", $warm);
		ok($warm < 60, sprintf('a repeat request should come straight out of dhcrenders/; '
		                     . '%.0f ms means it recomposed', $warm));
	}
	if (is_resource($proc)) { proc_terminate($proc); proc_close($proc); }
	foreach (glob(__DIR__ . '/dhcrenders/f4242-*') as $f) @unlink($f);
	foreach (glob($stub . '/*') as $f) @unlink($f);
	@rmdir($stub);
	echo "  (stub, server and test renders cleaned up)\n";
}

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all download checks passed\n");
exit($fail ? 1 : 0);
