<?php
/**
 * dhc-arena-card.php?battle=N -- the picture X and Discord put on a shared
 * Arena result: your Crew of three facing theirs, the fallen dimmed.
 *
 * WHY. A shared match carried dhcarena.php's own og:image, which is the same
 * site collage for every result anybody has ever posted. The text now says
 * who was beaten and which Fighters held the line; this makes the picture
 * say it too.
 *
 * NO TEXT ON THE IMAGE. There is no TTF in this repo -- see dhc-card.php,
 * which went text-free for exactly this -- and GD's bitmap fonts at 1200px
 * look like a mistake. X and Discord render og:title at full size beside the
 * card, and that is where the result already is.
 *
 * PUBLIC, AND THAT EXPOSES NOTHING NEW. It draws the six Fighters that
 * fought, every one of which is already on the public Collection with its
 * own share card. There is no score, no account and no board here.
 *
 * NOTHING MAY PRINT BEFORE THE IMAGE: display_errors is on platform-wide and
 * a notice in front of the JPEG header is a corrupt image the crawler caches.
 */
ob_start();
include __DIR__ . '/db.php';
require_once __DIR__ . '/dhcfighters-lib.php';
if (!function_exists('dhcf_render_fighter')) {
	$n = __DIR__ . '/dhcfighters-notify.php';
	if (is_readable($n)) @include_once $n;
}
ob_end_clean();

const DAC_W = 1200;
const DAC_H = 630;

function dac_fail($code = 404) {
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	header('Cache-Control: public, max-age=300');
	echo 'No card.';
	exit;
}
function dac_send($file) {
	header('Content-Type: image/jpeg');
	header('Content-Length: ' . filesize($file));
	header('Cache-Control: public, max-age=604800, immutable');
	readfile($file);
	exit;
}

$battle = isset($_GET['battle']) ? (int)$_GET['battle'] : 0;
if ($battle <= 0) dac_fail(400);
if (!function_exists('imagecreatetruecolor')) dac_fail(503);

/*
 * THE SAVED STATE IS THE WHOLE RECORD. dhc_arena_state holds the engine's own
 * $b for the battle, and every Fighter in it already carries its traits and
 * its ko flag -- so there is no second read of dhc_fighters and no chance of
 * the card disagreeing with the match it is a picture of.
 *
 * $conn->query(), not prepare()+get_result(): this server's mysqli has no
 * mysqlnd, so get_result() is a fatal there and php -l cannot see it.
 */
$res = $conn->query("SELECT state FROM dhc_arena_state WHERE battle_id = $battle
                     ORDER BY updated_at DESC LIMIT 1");
if (!$res) dac_fail(503);
$row = $res->fetch_assoc();
if (!$row) dac_fail();
$b = json_decode($row['state'], true);
if (!is_array($b) || empty($b['mine']) || empty($b['foes'])) dac_fail();

/* Keyed on the battle AND on how it ended, so a card rendered mid-match is
   replaced once the result is in rather than cached for a week. */
$sig = '';
foreach (array('mine', 'foes') as $side) {
	foreach ($b[$side] as $f) $sig .= ($f['ko'] ? '1' : '0');
}
$key   = substr(md5('arena|' . $battle . '|' . $sig . '|' . DAC_W . 'x' . DAC_H), 0, 24);
$dir   = __DIR__ . '/dhcrenders';
$cache = $dir . '/arena-' . $key . '.jpg';
if (is_file($cache) && filesize($cache) > 0) dac_send($cache);

if (!function_exists('dhcf_render_fighter')) dac_fail(503);

/* One Fighter's art, at the master size, through the ONE renderer the
   assembler, the Arena and Discord all use. A second opinion about draw
   order is the bug class that has already cost this codebase twice. */
function dac_art($f, $dir) {
	if (empty($f['traits'])) return null;
	$url = dhcf_render_fighter($f['traits'], isset($f['uid']) ? (int)$f['uid'] : 0, 500);
	if ($url === '') return null;
	$abs = $dir . '/' . basename(parse_url($url, PHP_URL_PATH));
	return is_file($abs) ? @imagecreatefrompng($abs) : null;
}

$card = imagecreatetruecolor(DAC_W, DAC_H);
imagefilledrectangle($card, 0, 0, DAC_W, DAC_H, imagecolorallocate($card, 7, 17, 29));

/*
 * SIX TILES, NOT SIX OVERLAPPING PICTURES.
 *
 * dhcf_render_fighter() returns a SQUARE CARD, background trait and all --
 * not a cut-out figure. The first version of this laid those side by side at
 * different sizes and the card came out as a strip of overlapping black
 * rectangles with no sense of two Crews at all. Rendered it and looked.
 *
 * So each Fighter gets a discrete portrait tile with its own border and a
 * gap, three a side with a seam between, which is how the Arena itself shows
 * a Crew. The crop is a centre column of the square render: a Fighter is a
 * vertical figure, so a portrait window uses the card's height instead of
 * wasting it.
 */
$TW = 164; $TH = 470; $GAP = 18; $EDGE = 40;
$top = (int)((DAC_H - $TH) / 2);
$line = imagecolorallocate($card, 27, 51, 70);

/* The seam, drawn first so the tiles sit over it. */
imagefilledrectangle($card, (int)(DAC_W / 2) - 1, 56, (int)(DAC_W / 2), DAC_H - 56, $line);

foreach (array('mine' => true, 'foes' => false) as $side => $isLeft) {
	$fs = array_values($b[$side]);
	for ($i = 0; $i < 3; $i++) {
		/* Left Crew reads left-to-right; the right Crew mirrors it, so the
		   two front ranks are the pair nearest the seam. */
		$n  = $isLeft ? $i : (2 - $i);
		$x  = $isLeft
			? $EDGE + $i * ($TW + $GAP)
			: DAC_W - $EDGE - ($i + 1) * $TW - $i * $GAP;

		imagefilledrectangle($card, $x - 1, $top - 1, $x + $TW, $top + $TH,
		                     imagecolorallocate($card, 13, 30, 46));
		imagerectangle($card, $x - 1, $top - 1, $x + $TW, $top + $TH, $line);

		if (!isset($fs[$n])) continue;
		$art = dac_art($fs[$n], $dir);
		if (!$art) continue;
		$aw = imagesx($art); $ah = imagesy($art);

		/* Fill the tile from a centre window of the square render. */
		$scale = max($TW / $aw, $TH / $ah);
		$sw2   = (int)round($TW / $scale);
		$sh2   = (int)round($TH / $scale);
		$sx    = (int)(($aw - $sw2) / 2);
		$sy    = (int)(($ah - $sh2) / 2);

		$tile = imagecreatetruecolor($TW, $TH);
		imagecopyresampled($tile, $art, 0, 0, $sx, $sy, $TW, $TH, $sw2, $sh2);

		/*
		 * A FALLEN FIGHTER IS DIMMED, NOT HIDDEN -- leaving it out would make
		 * a sweep look like a two-on-three. Greyed and darkened, but not so
		 * far that the tile reads as empty: the first attempt took it to
		 * -72 and the Fighter vanished into its own background.
		 */
		if (!empty($fs[$n]['ko']) && function_exists('imagefilter')) {
			@imagefilter($tile, IMG_FILTER_GRAYSCALE);
			@imagefilter($tile, IMG_FILTER_BRIGHTNESS, -42);
		}
		imagecopy($card, $tile, $x, $top, 0, 0, $TW, $TH);
	}
}

/* Atomic write, same reason dhc-card.php does it: two crawlers can arrive at
   once and a half-written file that is nonzero would be served as a valid
   cache hit for a week. */
@mkdir($dir, 0755, true);
$tmpf = $dir . '/.arena-' . $key . '.' . getmypid() . '-' . mt_rand(1000, 9999) . '.tmp';
if (@imagejpeg($card, $tmpf, 86) && @rename($tmpf, $cache)
    && is_file($cache) && filesize($cache) > 0) {
	dac_send($cache);
}
@unlink($tmpf);

header('Content-Type: image/jpeg');
header('Cache-Control: public, max-age=3600');
imagejpeg($card, null, 86);
