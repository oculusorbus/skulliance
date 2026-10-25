<?php
/**
 * dhc-arena-card.php?battle=N -- the picture X and Discord put on a shared
 * Arena result: your Crew of three facing theirs, the fallen dimmed.
 *
 * dhc-arena-card.php?run=N does the same for a DHC Bosses run: your Crew of
 * three facing the boss crew. Same endpoint on purpose -- the layout, the
 * crop, the seam, the mirroring and the dimming are the thing being shared
 * in both cases, and a second file would be a copy of all of it that drifted
 * the first time either was touched. Only the two loaders differ, and the
 * right-hand three carry flat art instead of a trait stack, which is the
 * same single difference the battle client already handles.
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

/*
 * WHERE A HEAD IS ON THE CANVAS, measured across all 71 head and headgear
 * layers rather than guessed. The art is composed to a fixed body layout --
 * that is the whole reason dhcf_layers() can stack traits at all -- so these
 * are properties of the collection, not of one Fighter.
 *
 *   heads span y 0.072 .. 0.650   -> centre 0.360
 *   heads span x 0.330 .. 0.880   -> centre 0.605, NOT the middle
 *
 * A ROBUST SPAN, not the absolute one: head/ and headgear/ also hold a few
 * full-canvas layers, so the true min and max are 0 and 1 and would say
 * "crop nothing". The 10th and 90th percentiles are the real head.
 *
 * The window is a little larger than that so no horn touches an edge.
 *
 * THE WINDOW NOW TAKES THE WHOLE HEIGHT OF THE ART, which is what makes the
 * tiles tall: the render is a SQUARE 500px card, so the window's height is
 * all there is to take and its WIDTH is then fixed by the tile's aspect at
 * TW/TH. Centre 0.5 follows from height 1.0 -- anything else would hang the
 * window off the top or bottom of the canvas and the clamp would quietly
 * slide it back.
 *
 * That turns the tile height into the only free number, and it is bounded
 * on BOTH sides:
 *
 *   TH >= 228   or the window is wider than the canvas and gets clamped
 *   TH <= 327   or the window is narrower than 0.330..0.880 and clips horns
 *
 * 310 sits inside that with room, and is as tall as a tile can be while
 * still showing the full height of the art and the whole of a head.
 */
const DAC_HEAD_X = 0.605;
const DAC_HEAD_Y = 0.500;
const DAC_HEAD_H = 1.000;

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
$run    = isset($_GET['run'])    ? (int)$_GET['run']    : 0;
if ($battle <= 0 && $run <= 0) dac_fail(400);
if (!function_exists('imagecreatetruecolor')) dac_fail(503);

$b = array('mine' => array(), 'foes' => array());

if ($run > 0) {
	/*
	 * A BOSS RUN. Same two sides, loaded from the Bosses ledger.
	 *
	 * MOST RUNS END WITH THE CREW WIPED and that is the normal, paying
	 * outcome here -- so this card is usually three dimmed Fighters facing
	 * three bosses still standing, which is exactly the picture worth
	 * posting. It says what the fight is in one look.
	 */
	/* BUFFERED, like the includes at the top of this file and for the same
	   reason: display_errors is on platform-wide, and one notice in front
	   of the JPEG header is a corrupt image the crawler caches for a week. */
	ob_start();
	require_once __DIR__ . '/dhcbosses-lib.php';
	ob_end_clean();
	$rr = $conn->query("SELECT user_id, day, wave FROM dhc_boss_runs
	                     WHERE id = $run AND outcome <> 0 LIMIT 1");
	if (!$rr) dac_fail(503);
	$meta = $rr->fetch_assoc();
	if (!$meta) dac_fail();
	$uid  = (int)$meta['user_id'];
	$wave = max(1, (int)$meta['wave']);
	$day  = $conn->real_escape_string($meta['day']);

	$res = $conn->query(
		"SELECT rf.`rank`, rf.fell, f.serial, f.traits
		   FROM dhc_boss_run_fighters rf
		   INNER JOIN dhc_fighters f ON f.id = rf.fighter_id
		  WHERE rf.run_id = $run
		  ORDER BY rf.`rank` ASC, rf.fighter_id ASC");
	if (!$res) dac_fail(503);
	while ($r = $res->fetch_assoc()) {
		$traits = json_decode($r['traits'], true);
		if (!is_array($traits)) continue;
		$b['mine'][] = array('traits' => $traits, 'uid' => (int)$r['serial'],
		                     'ko' => !empty($r['fell']));
	}

	/*
	 * WHICH BOSSES THIS PLAYER PUT DOWN. dhc_boss_day records who landed
	 * the last hit, per boss per wave, so no new column is needed -- and
	 * dimming a boss somebody else finished would be claiming their kill.
	 */
	$downed = array();
	$kr = $conn->query("SELECT boss_key FROM dhc_boss_day
	                     WHERE day = '$day' AND wave = $wave
	                       AND killed_at IS NOT NULL AND killed_by = $uid");
	if ($kr) while ($k = $kr->fetch_assoc()) $downed[$k['boss_key']] = true;

	foreach (dhcb_bosses() as $bz) {
		$abs = __DIR__ . '/' . dhcb_art($bz['key']);
		$b['foes'][] = array('file' => $abs, 'boss' => true,
		                     'ko' => isset($downed[$bz['key']]));
	}
	if (!$b['mine'] || !$b['foes']) dac_fail();
	$sig = 'run|' . $run . '|' . $wave;
	foreach (array('mine', 'foes') as $side)
		foreach ($b[$side] as $f) $sig .= ($f['ko'] ? '1' : '0');
	$key   = substr(md5($sig . '|' . DAC_W . 'x' . DAC_H), 0, 24);
	$dir   = __DIR__ . '/dhcrenders';
	$cache = $dir . '/arena-' . $key . '.jpg';
	if (is_file($cache) && filesize($cache) > 0) dac_send($cache);
} else {

/*
 * THE LEDGER, NOT THE LIVE STATE.
 *
 * dhc_arena_state is the IN-PROGRESS store and is DELETED the moment a
 * battle resolves -- and a result is only ever shared after it resolves, so
 * reading it meant this endpoint 404'd for every single match anybody
 * posted. It did exactly that in production before anyone noticed, because
 * a missing og:image is a card with no picture rather than an error.
 *
 * dhc_arena_battle_fighters is the durable record: six rows saying who was
 * in the battle, which side, and whether they fell. Traits come from
 * dhc_fighters, which is where they live anyway.
 *
 * side 1 is the attacker, 2 the defender -- see dhca_log_participants().
 *
 * $conn->query(), not prepare()+get_result(): this server's mysqli has no
 * mysqlnd, so get_result() is fatal there and php -l cannot see it.
 */
$res = $conn->query(
	"SELECT bf.side, bf.fell, bf.`rank`, f.id, f.serial, f.traits
	   FROM dhc_arena_battle_fighters bf
	   INNER JOIN dhc_fighters f ON f.id = bf.fighter_id
	  WHERE bf.battle_id = $battle
	  ORDER BY bf.side ASC, bf.`rank` ASC, bf.fighter_id ASC");
if (!$res) dac_fail(503);

while ($r = $res->fetch_assoc()) {
	$traits = json_decode($r['traits'], true);
	if (!is_array($traits)) continue;
	$b[(int)$r['side'] === 1 ? 'mine' : 'foes'][] = array(
		'traits' => $traits,
		/* The SERIAL keys the render cache, so this card and the Fighter's
		   own share card reuse one file rather than drawing it twice. */
		'uid'    => (int)$r['serial'],
		'ko'     => !empty($r['fell']),
	);
}
if (!$b['mine'] || !$b['foes']) dac_fail();

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
}

if (!function_exists('dhcf_render_fighter')) dac_fail(503);

/* One Fighter's art, at the master size, through the ONE renderer the
   assembler, the Arena and Discord all use. A second opinion about draw
   order is the bug class that has already cost this codebase twice. */
function dac_art($f, $dir) {
	/* A BOSS IS ONE PICTURE, which is the single place a boss is not simply
	   a Fighter -- the same short-circuit layerImgs() makes in the battle
	   client and dhcb_art() makes on the panel. */
	if (!empty($f['file'])) return is_file($f['file']) ? @imagecreatefrompng($f['file']) : null;
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
/*
 * TILE SHAPE IS DECIDED BY THE HEAD, not chosen and then cropped to.
 *
 * MEASURED, not guessed: scanning all 71 head and headgear layers, a head
 * spans y 0.072 to 0.645 of the canvas and x 0.330 to 0.807 -- so its centre
 * is (0.569, 0.358) and it is 0.477 of the canvas wide. It is NOT centred
 * horizontally. The first version guessed 0.26 for the centre and assumed
 * 0.5 across, which is why faces came out high and three-quarters cropped.
 *
 * A window that holds all of that with a little air is 0.55 wide by 0.665
 * tall. Three across caps the width at ~180, and the HEIGHT is then the
 * only number left to choose.
 *
 * AS TALL AS THE ART ALLOWS. The render is square, so once the window
 * takes the full height of it the window's width is TW/TH and nothing
 * else -- a taller tile is a NARROWER slice of the same picture, and past
 * TH 327 the slice is narrower than a head. 310 is the tallest that still
 * holds one, and it is 42% more picture than the 218 it replaces. Beyond
 * that the card can only be filled with something other than these six
 * portraits.
 */
$TW = 180; $TH = 310; $GAP = 10; $EDGE = 30;
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

		/*
		 * CROPPED AROUND THE FACE, not around the middle of the picture.
		 *
		 * Filling the tile from a centred window shows the whole body, and
		 * since a Fighter's head sits in the top quarter of its render, every
		 * face ended up jammed against the top edge of its frame. A portrait
		 * of six fighters should look at you.
		 *
		 * DHCA_FACE_Y is where a head sits in the canonical render -- the art
		 * is composed to a fixed body layout, which is the whole reason
		 * dhcf_layers() can stack traits at all, so this is a property of the
		 * collection rather than a guess about one Fighter. The window is
		 * centred on it and clamped inside the source.
		 */
		$winH = (int)round($ah * DAC_HEAD_H);
		$winW = (int)round($winH * ($TW / $TH));
		if ($winW > $aw) { $winW = $aw; $winH = (int)round($winW * ($TH / $TW)); }
		$sw2 = $winW;
		$sh2 = $winH;
		/*
		 * A BOSS IS CROPPED FROM THE CENTRE, NOT FROM DAC_HEAD.
		 *
		 * Those two constants were measured across all 71 head and headgear
		 * layers of the collection -- they are a property of the assembled
		 * body layout, and the bosses are not assembled. They are painted
		 * pieces that fill their own square, so the only honest window is
		 * the middle of it; DAC_HEAD_X 0.605 on a piece composed to its own
		 * centre slices off a third of the figure.
		 */
		$isBoss = !empty($fs[$n]['boss']);
		$hx = $isBoss ? 0.5 : DAC_HEAD_X;
		$hy = $isBoss ? 0.5 : DAC_HEAD_Y;
		/* Centred on the head, then clamped -- and the clamp should never
		   fire at these proportions, which is the point of deriving them
		   from the measurement rather than trimming until it looks right. */
		$sx = (int)max(0, min($aw - $sw2, $aw * $hx - $sw2 / 2));
		$sy = (int)max(0, min($ah - $sh2, $ah * $hy - $sh2 / 2));

		$tile = imagecreatetruecolor($TW, $TH);
		imagecopyresampled($tile, $art, 0, 0, $sx, $sy, $TW, $TH, $sw2, $sh2);

		/*
		 * THE DEFENDERS FACE LEFT, so the two Crews look at each other
		 * instead of all six marching the same way. The Arena itself does
		 * exactly this -- .teamcol.foes .tok .art{transform:scaleX(-1)} --
		 * and a card of a fight where nobody is facing anybody is a card of
		 * six portraits.
		 */
		/*
		 * EVERYTHING ON THE RIGHT FACES LEFT, bosses included.
		 *
		 * This exempted them at first, because they carry lettering on the
		 * armour and flipping reverses it. The owner's call, and it is the
		 * right one: a card of a fight where one side is looking away is a
		 * card of six portraits, and the reversed text is a detail at
		 * 180px that nobody reads. The board already mirrors the enemy
		 * column, so exempting them here made the card disagree with the
		 * screen it was shared from.
		 */
		if (!$isLeft && function_exists('imageflip')) imageflip($tile, IMG_FLIP_HORIZONTAL);

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
