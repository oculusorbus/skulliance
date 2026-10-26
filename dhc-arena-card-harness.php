<?php
/* dhc-arena-card-harness.php - CLI only. No database, no network.
 *
 * The Crew-vs-Crew card on a shared Arena result.
 *
 * WHY GEOMETRY IS THE TEST. dhcf_render_fighter() returns a SQUARE CARD --
 * background trait and all -- not a cut-out figure. The first version of
 * this card laid six of those side by side at three different sizes, and it
 * came out as a strip of overlapping black rectangles with no two Crews
 * visible in it at all. Nothing errored; it just looked like nothing. Only
 * running the compositor and measuring where things landed can catch that.
 *
 * So the real block is lifted out of dhc-arena-card.php and run against
 * flat-coloured stand-ins for the Fighter art, which makes every tile's
 * position and every dimmed tile measurable to the pixel.
 *
 * Usage: php dhc-arena-card-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$src   = file_get_contents(__DIR__ . '/dhc-arena-card.php');
$clean = preg_replace('!/\*.*?\*/!s', '', $src);
$clean = preg_replace('~(?<![:/])//.*$~m', '', $clean);

echo "the card is the shape X actually renders\n";
ok(preg_match('/const DAC_W = (\d+);/', $clean, $w) === 1, 'DAC_W is gone');
ok(preg_match('/const DAC_H = (\d+);/', $clean, $h) === 1, 'DAC_H is gone');
$W = (int)$w[1]; $H = (int)$h[1];
ok($W === 1200 && $H === 630, "the card is {$W}x{$H}; summary_large_image wants 1200x630");

if (!function_exists('imagecreatetruecolor')) {
	echo "\n  (GD not available -- geometry not run)\n";
} else {
	/* EVERY DAC_ CONSTANT, READ OUT OF THE FILE. Defining just W and H by
	   hand meant adding the measured head constants to the page fataled the
	   harness -- a test that breaks when the code grows is a test people
	   start skipping. */
	if (preg_match_all('/const (DAC_[A-Z_]+)\s*=\s*(-?[\d.]+);/', $clean, $cm, PREG_SET_ORDER)) {
		foreach ($cm as $c) {
			if (!defined($c[1])) define($c[1], strpos($c[2], '.') !== false ? (float)$c[2] : (int)$c[2]);
		}
	}
	ok(defined('DAC_W') && defined('DAC_H'), 'the card constants are gone');

	/*
	 * A FLAT COLOUR PER FIGHTER, so a tile can be identified by what is in
	 * it. Real art would make "is this one dimmed" a judgement call; a
	 * saturated fill makes it arithmetic.
	 */
	$COLS = array(array(230, 40, 40), array(40, 230, 40), array(40, 80, 230),
	              array(230, 200, 40), array(230, 40, 200), array(40, 220, 220));
	$made = array();
	function dac_art($f, $dir) {
		global $COLS, $made;
		$n = (int)$f['uid'];
		$im = imagecreatetruecolor(500, 500);
		imagefilledrectangle($im, 0, 0, 500, 500,
			imagecolorallocate($im, $COLS[$n][0], $COLS[$n][1], $COLS[$n][2]));
		/*
		 * A WHITE MARK, OFF CENTRE, because a flat fill cannot tell you which
		 * way it is facing and the defending Crew has to be mirrored so the
		 * two sides look at each other.
		 *
		 * INSIDE THE CROP WINDOW. The first attempt put a band down the far
		 * left edge, which the card's centre-column crop discards entirely:
		 * every tile came back with no mark at all and all six checks failed
		 * against correct code. The window is roughly the middle third, so
		 * the mark goes just inside its left edge.
		 */
		/* FULL HEIGHT, so any horizontal scan line crosses it. A short mark
		   fell outside the row the check samples once the crop moved up to
		   centre the face, and all six orientation checks failed against a
		   correct card. */
		imagefilledrectangle($im, 172, 0, 212, 500, imagecolorallocate($im, 255, 255, 255));
		/*
		 * A BLACK BAND WHERE THE FACE IS. The card crops around the head
		 * rather than filling the tile, because filling it put every face
		 * jammed against the top edge of its frame. That is only testable
		 * if the fixture has a face: this sits at the same fraction of the
		 * render the real heads do, and the check below asks where it
		 * ended up in the finished tile.
		 */
		/* At the MEASURED head centre -- x 0.569, y 0.358 of the canvas --
		   x 0.605, y 0.360 -- not the 0.26 and 0.5 guessed first. These numbers are the
		   fact about the art; the constants in the card claim to encode
		   them, and the block below checks that claim separately against
		   the real PNGs, so this fixture is not just agreeing with the
		   code by construction. */
		imagefilledrectangle($im, 270, 160, 335, 200, imagecolorallocate($im, 0, 0, 0));
		$made[] = $n;
		return $im;
	}

	$a = strpos($clean, '$card = imagecreatetruecolor(DAC_W, DAC_H);');
	$b = strpos($clean, 'imagejpeg($card, $tmpf');
	$b = strpos($clean, "\n", strpos($clean, '@mkdir($dir', $a));
	ok($a !== false && $b !== false, 'the compositing block moved; this check is blind');
	$block = substr($clean, $a, $b - $a);

	$mk = function ($uid, $ko) {
		return array('uid' => $uid, 'ko' => $ko, 'name' => 'F' . $uid,
		             'traits' => array('torso' => 't'));
	};
	$run = function ($mineKo, $foeKo) use ($block, $mk) {
		global $made; $made = array();
		$b = array('mine' => array(), 'foes' => array());
		foreach ($mineKo as $i => $k) $b['mine'][] = $mk($i, $k);
		foreach ($foeKo  as $i => $k) $b['foes'][] = $mk(3 + $i, $k);
		$dir = __DIR__ . '/dhcrenders';
		eval($block);
		return $card;
	};

	echo "\nall six Fighters are on the card\n";
	$card = $run(array(false, false, false), array(false, false, false));
	ok(count($made) === 6, 'only ' . count($made) . ' of 6 Fighters were drawn');

	/* Find each flat colour and measure the box it occupies. */
	$box = function ($card, $rgb) use ($W, $H) {
		$x0 = $W; $x1 = -1; $y0 = $H; $y1 = -1;
		for ($y = 0; $y < $H; $y += 2) for ($x = 0; $x < $W; $x += 2) {
			$c = imagecolorat($card, $x, $y);
			if (abs((($c >> 16) & 255) - $rgb[0]) < 26 && abs((($c >> 8) & 255) - $rgb[1]) < 26
			    && abs(($c & 255) - $rgb[2]) < 26) {
				if ($x < $x0) $x0 = $x; if ($x > $x1) $x1 = $x;
				if ($y < $y0) $y0 = $y; if ($y > $y1) $y1 = $y;
			}
		}
		return $x1 < 0 ? null : array($x0, $y0, $x1, $y1);
	};

	$boxes = array();
	for ($i = 0; $i < 6; $i++) $boxes[$i] = $box($card, $COLS[$i]);
	foreach ($boxes as $i => $bx) ok($bx !== null, "Fighter $i is not visible on the card");

	echo "\nnothing overlaps, and nothing runs off the edge\n";
	if (!in_array(null, $boxes, true)) {
		/* THE SEAM SEPARATES THE CREWS. Three tiles must sit entirely left of
		   centre and three entirely right; a tile crossing it is the
		   overlapping-rectangles bug coming back. */
		for ($i = 0; $i < 3; $i++) ok($boxes[$i][2] < $W / 2, "your Fighter $i crosses the centre seam");
		for ($i = 3; $i < 6; $i++) ok($boxes[$i][0] > $W / 2, 'a defending Fighter crosses the centre seam');
		foreach ($boxes as $i => $bx) {
			ok($bx[0] >= 8 && $bx[2] <= $W - 8, "Fighter $i runs off the side of the card");
			ok($bx[1] >= 8 && $bx[3] <= $H - 8, "Fighter $i runs off the top or bottom");
		}
		/* And no two tiles share any pixel column-and-row space. */
		$clash = 0;
		for ($i = 0; $i < 6; $i++) for ($j = $i + 1; $j < 6; $j++) {
			if ($boxes[$i][0] <= $boxes[$j][2] && $boxes[$j][0] <= $boxes[$i][2]
			    && $boxes[$i][1] <= $boxes[$j][3] && $boxes[$j][1] <= $boxes[$i][3]) $clash++;
		}
		ok($clash === 0, $clash . ' pairs of Fighters overlap; the card reads as a strip of boxes');
		/* The tiles must be a usable size, not slivers in a sea of ground. */
		$areaFrac = 0;
		foreach ($boxes as $bx) $areaFrac += (($bx[2] - $bx[0]) * ($bx[3] - $bx[1])) / ($W * $H);
		/* The threshold is a SLIVER TEST, not a layout opinion: the tiles are
		   sized by what lets a face be centred without clipping the head, and
		   that is the designer's call. The mutation that shrinks them to
		   nothing measures 2%. */
		/*
		 * A SLIVER TEST, NOT A LAYOUT OPINION. The tile shape is fixed by
		 * arithmetic -- a head is 0.477 of the canvas wide and 0.573 tall,
		 * so a tile that contains one and centres it must be about 0.83 as
		 * wide as it is tall, and three across caps the width. Six of those
		 * cover roughly a third of the card and that is simply what the
		 * constraint produces. The shrink mutation measures 2%.
		 */
		ok($areaFrac > 0.20, sprintf('the Fighters cover only %.0f%% of the card', $areaFrac * 100));
	}

	echo "\nthe two Crews face each other\n";
	{
		/* The marker band sits on the left of the source art. After
		   compositing it must still be on the left for your Crew and on the
		   RIGHT for theirs, which is what mirroring does. The Arena itself
		   flips them the same way: .teamcol.foes .tok .art{scaleX(-1)}. */
		$bandSide = function ($card, $bx) {
			$mid = (int)(($bx[0] + $bx[2]) / 2);
			$y   = (int)(($bx[1] + $bx[3]) / 2);
			$l = 0; $r = 0;
			for ($x = $bx[0]; $x < $mid; $x += 2) {
				$c = imagecolorat($card, $x, $y);
				if ((($c >> 16) & 255) > 200 && (($c >> 8) & 255) > 200 && ($c & 255) > 200) $l++;
			}
			for ($x = $mid; $x <= $bx[2]; $x += 2) {
				$c = imagecolorat($card, $x, $y);
				if ((($c >> 16) & 255) > 200 && (($c >> 8) & 255) > 200 && ($c & 255) > 200) $r++;
			}
			return $l > $r ? 'left' : ($r > $l ? 'right' : 'none');
		};
		for ($i = 0; $i < 3; $i++) {
			if ($boxes[$i]) ok($bandSide($card, $boxes[$i]) === 'left',
			                   "your Fighter $i was mirrored; only the defenders should be");
		}
		for ($i = 3; $i < 6; $i++) {
			if ($boxes[$i]) ok($bandSide($card, $boxes[$i]) === 'right',
			                   'a defending Fighter is not mirrored, so both Crews face the same way');
		}
	}

	echo "\nthe faces are centred in their frames\n";
	{
		/* Where the black face band ended up, down the middle of a tile. */
		$faceMid = function ($card, $bx) {
			$x = (int)(($bx[0] + $bx[2]) / 2);
			$y0 = -1; $y1 = -1;
			for ($y = $bx[1]; $y <= $bx[3]; $y++) {
				$c = imagecolorat($card, $x, $y);
				if ((($c >> 16) & 255) < 30 && (($c >> 8) & 255) < 30 && ($c & 255) < 30) {
					if ($y0 < 0) $y0 = $y; $y1 = $y;
				}
			}
			return $y0 < 0 ? null : ($y0 + $y1) / 2;
		};
		for ($i = 0; $i < 6; $i++) {
			if (!$boxes[$i]) continue;
			$mid = $faceMid($card, $boxes[$i]);
			$tileMid = ($boxes[$i][1] + $boxes[$i][3]) / 2;
			$tileH   = $boxes[$i][3] - $boxes[$i][1];
			ok($mid !== null, "Fighter $i's face is not in its frame at all");
			if ($mid !== null) {
				$off = abs($mid - $tileMid) / max(1, $tileH);
				ok($off < 0.14, sprintf("Fighter %d's face sits %.0f%% away from the middle of its "
				   . 'frame; the crop is filling the tile instead of framing the head', $i, $off * 100));
			}
		}
	}

	echo "\na fallen Fighter is dimmed, and a standing one is not\n";
	$card2 = $run(array(false, false, true), array(true, true, true));
	$lum = function ($card, $rgb) use ($box) {
		$bx = $box($card, $rgb);
		return $bx;   // found at full saturation?
	};
	/* The KO'd ones must NOT still be their saturated colour... */
	ok($box($card2, $COLS[2]) === null, 'a fallen Fighter of yours is not dimmed');
	for ($i = 3; $i < 6; $i++) ok($box($card2, $COLS[$i]) === null, 'a fallen defender is not dimmed');
	/* ...and the standing ones must be. */
	ok($box($card2, $COLS[0]) !== null, 'a Fighter still standing was dimmed');
	ok($box($card2, $COLS[1]) !== null, 'a Fighter still standing was dimmed');

	/*
	 * DIMMED, NOT ERASED. The first attempt darkened by 72 and the Fighter
	 * disappeared into its own background, which makes a sweep look like a
	 * two-on-three. The tile still has to carry visible ink.
	 */
	$seen = 0; $sum = 0;
	for ($y = 120; $y < $H - 120; $y += 4) for ($x = $W - 300; $x < $W - 60; $x += 4) {
		$c = imagecolorat($card2, $x, $y);
		$sum += 0.2126 * (($c >> 16) & 255) + 0.7152 * (($c >> 8) & 255) + 0.0722 * ($c & 255);
		$seen++;
	}
	$avg = $sum / max(1, $seen);
	ok($avg > 24, sprintf('the fallen Crew averages %.0f/255; it has gone black', $avg));
	ok($avg < 150, sprintf('the fallen Crew averages %.0f/255; it is not dimmed at all', $avg));
}

echo "\nthe head constants match the actual art\n";
{
	/*
	 * THE SAME CHECK WITHOUT THE ART, so it runs in every checkout.
	 *
	 * The measurement below needs the trait PNGs, which are deployed by
	 * FTP and are not in the repo -- so on a laptop it prints a note and
	 * checks nothing, and the tile height could be changed to anything at
	 * all with a green harness. These are the numbers that measurement
	 * recorded, written down, so the geometry is still guarded here.
	 */
	{
		$HEAD_X0 = 0.330; $HEAD_X1 = 0.880;   /* 10th/90th percentile, 71 layers */
		$HEAD_Y0 = 0.072; $HEAD_Y1 = 0.650;
		preg_match('/\$TW = (\d+); \$TH = (\d+); \$GAP = (\d+); \$EDGE = (\d+);/', $src, $tm);
		ok($tm, 'the tile geometry could not be read out of the card');
		if ($tm) {
			list(, $tw, $th, $gap, $edge) = array_map('intval', $tm);
			$ww = DAC_HEAD_H * ($tw / $th);
			$x0 = DAC_HEAD_X - $ww / 2; $x1 = DAC_HEAD_X + $ww / 2;
			$y0 = DAC_HEAD_Y - DAC_HEAD_H / 2; $y1 = DAC_HEAD_Y + DAC_HEAD_H / 2;

			/* THE TILE ASPECT IS THE WINDOW WIDTH, because the render is
			   square and the window takes its full height -- so a TALLER
			   tile is a NARROWER slice, and past a point it is narrower
			   than a head. That is the trap in making these bigger. */
			ok($x0 <= $HEAD_X0 && $x1 >= $HEAD_X1,
			   sprintf('a %dx%d tile crops x %.3f..%.3f, which slices the recorded heads '
			         . '(%.3f..%.3f) -- a taller tile is a narrower slice',
			           $tw, $th, $x0, $x1, $HEAD_X0, $HEAD_X1));
			ok($y0 <= $HEAD_Y0 && $y1 >= $HEAD_Y1,
			   sprintf('the window crops y %.3f..%.3f, above the recorded heads (%.3f..%.3f)',
			           $y0, $y1, $HEAD_Y0, $HEAD_Y1));
			ok($x0 >= 0 && $x1 <= 1 && $y0 >= 0 && $y1 <= 1,
			   sprintf('the window (%.3f..%.3f x %.3f..%.3f) runs off the square render, so the '
			         . 'clamp slides it and nothing is centred any more', $x0, $x1, $y0, $y1));

			/* And the tiles have to fit the card they are drawn on. */
			$top = (int) ((DAC_H - $th) / 2);
			ok($top >= 0 && $top + $th <= DAC_H,
			   sprintf('a %dpx tile does not fit a %dpx card', $th, DAC_H));
			ok($edge + 3 * $tw + 2 * $gap < DAC_W / 2,
			   'three tiles a side no longer fit without crossing the seam');
			/* It is meant to be MOST of the card now, not a strip in the
			   middle of it -- that is the whole point of the height. */
			$fill = (6 * $tw * $th) / (DAC_W * DAC_H);
			ok($fill > 0.40,
			   sprintf('the six portraits cover only %.0f%% of the card; the rest is empty ink',
			           $fill * 100));
		}
	}

	/*
	 * THE CROP IS ONLY RIGHT IF THESE NUMBERS ARE. DAC_HEAD_X/Y/H say where
	 * a head sits on the canvas, and the first version of the card guessed
	 * 0.26 for the vertical centre and assumed the horizontal one was 0.5 --
	 * which is why faces came out high and three-quarters cropped. They are
	 * measurable, so they are measured: if Maxingo ever recomposes the body
	 * layout, this fails rather than the cards quietly going wrong.
	 *
	 * A sample, not all 71, because this runs on every pass.
	 */
	$dir0 = '';
	foreach (array('dhc/web', 'web', 'dhc', 'traits') as $cand) {
		if (is_dir(__DIR__ . '/' . $cand . '/1000/head')) { $dir0 = __DIR__ . '/' . $cand . '/1000'; break; }
	}
	if ($dir0 === '') {
		echo "  (no trait art in this checkout -- head constants not measured)\n";
	} else {
		/* EVERY layer, not a sample. A 20-file subset put the 90th
		   percentile of the right edge at 0.876 where the full set has it
		   at 0.880 -- close, but the check is a boundary and a sample that
		   moves it is a check that passes or fails by luck. */
		$files = array_merge(glob($dir0 . '/head/*.png'), glob($dir0 . '/headgear/*.png'));
		$tops = array(); $bots = array(); $ls = array(); $rs = array();
		foreach ($files as $f) {
			$im = @imagecreatefrompng($f);
			if (!$im) continue;
			/* Most trait PNGs are PALETTE: imagecolorat returns an index
			   there, so the alpha shift is meaningless until this. */
			if (!imageistruecolor($im)) imagepalettetotruecolor($im);
			$w = imagesx($im); $h = imagesy($im);
			$t = -1; $bm = -1; $l = -1; $r = -1;
			for ($y = 0; $y < $h; $y += 6) for ($x = 0; $x < $w; $x += 6) {
				if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) < 64) {
					if ($t < 0) $t = $y; $bm = $y;
					if ($l < 0 || $x < $l) $l = $x;
					if ($x > $r) $r = $x;
				}
			}
			if ($t < 0) continue;
			$tops[] = $t / $h; $bots[] = $bm / $h; $ls[] = $l / $w; $rs[] = $r / $w;
		}
		ok(count($tops) >= 5, 'could not read enough head layers to measure anything');
		if (count($tops) >= 5) {
			sort($tops); sort($bots); sort($ls); sort($rs);
			$q = function ($a, $f) { return $a[min(count($a) - 1, (int)(count($a) * $f))]; };
			$top = $q($tops, 0.1); $bot = $q($bots, 0.9);
			$lft = $q($ls, 0.1);   $rgt = $q($rs, 0.9);

			/* The window the card crops, from its own constants. */
			$wy0 = DAC_HEAD_Y - DAC_HEAD_H / 2;
			$wy1 = DAC_HEAD_Y + DAC_HEAD_H / 2;
			/*
			 * THE TILE ASPECT, READ OUT OF THE CARD, not typed here. It
			 * was hardcoded 180/218, so making the tiles taller would have
			 * left this measuring a window the card no longer crops -- it
			 * would have gone on passing while every horn got sliced off.
			 */
			ok(preg_match('/\$TW = (\d+); \$TH = (\d+);/', $src, $tm) === 1,
			   'the tile size could not be read out of dhc-arena-card.php');
			$tw = $tm ? (int) $tm[1] : 180;
			$th = $tm ? (int) $tm[2] : 218;
			$ww  = DAC_HEAD_H * ($tw / $th);          /* window aspect = tile aspect */
			$wx0 = DAC_HEAD_X - $ww / 2;
			$wx1 = DAC_HEAD_X + $ww / 2;

			ok($wy0 <= $top && $wy1 >= $bot,
			   sprintf('the crop window (y %.3f..%.3f) does not contain the heads (y %.3f..%.3f)',
			           $wy0, $wy1, $top, $bot));
			ok($wx0 <= $lft && $wx1 >= $rgt,
			   sprintf('the crop window (x %.3f..%.3f) does not contain the heads (x %.3f..%.3f) '
			         . '-- this is what cropped three-quarters of every face', $wx0, $wx1, $lft, $rgt));
			/* And it must stay inside the canvas, or the clamp silently
			   un-centres everything. */
			ok($wy0 >= 0 && $wy1 <= 1 && $wx0 >= 0 && $wx1 <= 1,
			   'the crop window runs off the canvas, so the clamp moves the head off centre');
		}
	}
}

echo "\nit reads the record that survives the battle\n";
{
	/*
	 * THE BUG THIS EXISTS FOR. The card first read dhc_arena_state, which is
	 * the IN-PROGRESS store and is DELETED the moment a battle resolves --
	 * and a result is only ever shared after it resolves. So the endpoint
	 * 404'd for every single match anybody posted, and nobody could tell,
	 * because a missing og:image is a card with no picture rather than an
	 * error.
	 *
	 * dhc_arena_battle_fighters is the ledger that survives: who was in it,
	 * which side, whether they fell.
	 */
	ok(strpos($clean, 'dhc_arena_battle_fighters') !== false,
	   'the card does not read the ledger, so it cannot describe a finished battle');
	ok(strpos($clean, 'FROM dhc_arena_state') === false,
	   'the card reads dhc_arena_state, which is deleted the moment a battle ends -- every '
	 . 'shared result would 404');
	/* And the table it reads really is the one that outlives a battle. */
	$lib = file_get_contents(__DIR__ . '/dhcarena-lib.php');
	ok(strpos($lib, 'DELETE FROM dhc_arena_state WHERE battle_id') !== false,
	   'dhcarena-lib.php no longer deletes the live state; if that changed, revisit which '
	 . 'table this card should read');
	ok(strpos($lib, 'INSERT IGNORE INTO dhc_arena_battle_fighters') !== false,
	   'nothing writes the ledger any more, so there is nothing for the card to read');
	/* side 1 is the attacker, 2 the defender -- dhca_log_participants(). */
	ok(strpos($clean, "(int)\$r['side'] === 1 ? 'mine' : 'foes'") !== false,
	   'the card does not split the two Crews by the ledger\'s side column');
	/* Traits come from dhc_fighters; a row whose Fighter is gone must not
	   land on the card as a blank tile. */
	ok(strpos($clean, 'INNER JOIN dhc_fighters f ON f.id = bf.fighter_id') !== false,
	   'the join to dhc_fighters is gone or is a LEFT JOIN; a deleted Fighter would draw blank');
}

echo "\nthe cache follows the result, and the write is atomic\n";
/* Keyed on the KO flags as well as the battle: a card rendered mid-match
   would otherwise be served for a week after the match ended. */
ok(strpos($clean, "\$sig .= (\$f['ko'] ? '1' : '0');") !== false,
   'the cache key ignores how the battle ended, so a mid-match card outlives the match');
ok(strpos($clean, '@rename($tmpf, $cache)') !== false,
   'the card is written in place; a half-written one would be cached by the crawler');
ok(strpos($clean, 'imagettftext') === false && strpos($clean, 'imagestring') === false,
   'the card draws text, and there is no TTF in this repo (see dhc-card.php)');
/* Built rather than written, because dhc-card-harness.php scans every PHP
   file in the repo for this call and a literal here is an offender. */
$gr = 'get_' . 'result(';
ok(strpos($clean, $gr) === false,
   'the endpoint calls ' . $gr . '), which is fatal on this server (no mysqlnd)');

echo "\nand a boss run gets the same card, from the same endpoint\n";
{
	$src = file_get_contents(__DIR__ . '/dhc-arena-card.php');
	/*
	 * SAME ENDPOINT, TWO LOADERS. The layout, the crop, the seam, the
	 * mirroring and the dimming are the thing being shared in both cases; a
	 * second file would be a copy of all of it that drifted the first time
	 * either was touched. So what this checks is that the DRAWING is shared
	 * and only the loading forks.
	 */
	ok(strpos($src, "\$run    = isset(\$_GET['run'])") !== false,
	   'the card does not take ?run=, so a shared boss run has no picture');
	ok(substr_count($src, 'imagecopyresampled(') === 1
	   && substr_count($src, '$TW = 180; $TH = 310;') === 1,
	   'there are two drawing paths now, which is the copy this endpoint exists to avoid');
	ok(strpos($src, 'FROM dhc_boss_run_fighters rf') !== false,
	   'the boss loader does not read the run ledger, so it cannot know who fell');
	/* A BOSS IS ONE PICTURE, not a trait stack -- the same short-circuit the
	   battle client and the panel make. */
	ok(strpos($src, "if (!empty(\$f['file'])) return is_file(\$f['file'])") !== false,
	   'a boss is sent through the Fighter renderer, which has no traits to stack and '
	 . 'returns nothing, so the right-hand tiles come out empty');
	/*
	 * AND THE TWO THINGS MEASURED FOR FIGHTERS MUST NOT BE APPLIED TO THEM.
	 * DAC_HEAD_X/Y were measured across the collection's 71 head layers and
	 * describe the assembled body layout; the bosses are painted pieces
	 * composed to their own centre, so that window slices a third off.
	 * Mirroring reverses the lettering on their armour.
	 */
	ok(strpos($src, "\$isBoss = !empty(\$fs[\$n]['boss']);") !== false,
	   'the card cannot tell a boss from a Fighter, so both get the head-measured crop');
	ok(strpos($src, '$hx = $isBoss ? 0.5 : DAC_HEAD_X;') !== false,
	   'a boss is cropped on the Fighter head window, which cuts a third off the figure');
	/* BOSSES ARE MIRRORED TOO. They were exempt at first over the lettering
	   on their armour; the owner called it, and the card agreeing with the
	   board matters more than text nobody reads at 180px wide. */
	ok(strpos($src, "if (!\$isLeft && function_exists('imageflip')) imageflip(") !== false,
	   'the right-hand side is not mirrored, so one Crew is looking away and the card is six '
	 . 'portraits rather than a fight');
	ok(strpos($src, '!$isBoss && function_exists(\'imageflip\')') === false,
	   'the bosses are exempt from the mirror again, so they face away from the Crew '
	 . 'attacking them');
	/*
	 * A BOSS IS GREY IF IT WAS DOWN WHEN THE RUN ENDED, whoever put it
	 * there -- the card is a picture of a FIGHT, not a credit.
	 *
	 * It filtered on killed_by with no time bound, which was wrong both
	 * ways: a boss this player dropped in an earlier run came out grey on
	 * every later card, and a boss somebody else dropped came out standing
	 * in a fight where it was already on the floor. Who landed the last
	 * hit is the question the post's TEXT answers, from this run's own
	 * killedKeys.
	 */
	ok(strpos($src, 'd.killed_at <= COALESCE(r.ended_at, NOW())') !== false,
	   'the card does not bound the kills to this run, so an earlier kill greys a boss in '
	 . 'a fight where it was still standing');
	ok(strpos($src, 'AND d.killed_by') === false,
	   'the card is back to filtering on who landed the hit, so a boss somebody else '
	 . 'dropped renders as standing in a fight where it was already down');
	/* Nothing may print before the JPEG, and the boss library is the one
	   include that happens after the buffered block at the top. */
	$at = strpos($src, "require_once __DIR__ . '/dhcbosses-lib.php';");
	ok($at !== false && strpos(substr($src, max(0, $at - 220), 220), 'ob_start();') !== false,
	   'the boss library is included unbuffered, so one notice from it corrupts the JPEG');
	/*
	 * THE WAVE'S COLOURWAY REACHES THE CARD.
	 *
	 * The bosses get back up each week in a different colourway and the
	 * page does that with filter:hue-rotate() in even fifths. A card drawn
	 * from the flat art would post a wave 3 crew in wave 1 colours -- a
	 * picture of a crew nobody fought.
	 *
	 * GD has no hue filter, so this is the Filter Effects matrix the
	 * browser itself uses. It is LUMINANCE PRESERVING, which is why it is
	 * these coefficients and not an HSL spin: that shifts brightness and
	 * the card would not match the page beside it.
	 */
	/* The matrix moved to lib/hue-rotate.php when the Discord embed needed
	   it too, and the degrees to dhcb_wave_hue() so the CSS, the card and
	   the embed cannot drift. The card calls both rather than carrying
	   either. */
	ok(strpos($src, "require_once __DIR__ . '/lib/hue-rotate.php';") !== false,
	   'the card cannot rotate hue, so every wave posts in wave 1 colours');
	ok(strpos($src, '0.213 + $c * 0.787') === false,
	   'the card carries its own copy of the hue matrix again');
	ok(strpos($src, "'wave' => \$wave") !== false,
	   'the wave is not carried onto the boss tiles, so the card cannot know which '
	 . 'colourway to draw');
	ok(strpos($src, "skl_hue_rotate(\$tile, dhcb_wave_hue((int)\$fs[\$n]['wave']))") !== false,
	   'the card does not take its rotation from dhcb_wave_hue(), so it can disagree with '
	 . 'the page and the Discord embed about what wave 3 looks like');
	/* AND THE CALL IS REACHABLE. Checking the arithmetic is present passes
	   with the whole block behind if(false) -- the strings survive, the
	   rotation does not. */
	ok(strpos($src, "if (\$isBoss && !empty(\$fs[\$n]['wave'])) {") !== false,
	   'the hue is not applied under its real guard, so it may never run');
	/* EXECUTED against the spec's own numbers rather than read. A
	   transcription slip in one coefficient would still look like a hue
	   rotation and be the wrong one. */
	/* This file has no brace_extract(); lift it here rather than add a
	   second helper nobody else uses. */
	$src2 = file_get_contents(__DIR__ . '/lib/hue-rotate.php');
	$hfA = strpos($src2, 'function skl_hue_rotate($im, $deg)');
	$hf  = '';
	if ($hfA !== false) {
		$d = 0;
		for ($j = strpos($src2, '{', $hfA); $j < strlen($src2); $j++) {
			if ($src2[$j] === '{') $d++;
			elseif ($src2[$j] === '}') { $d--; if (!$d) { $hf = substr($src2, $hfA, $j - $hfA + 1); break; } }
		}
	}
	ok($hf !== '', 'skl_hue_rotate() could not be lifted from lib/hue-rotate.php');
	if ($hf !== '' && function_exists('imagecreatetruecolor')) {
		eval(str_replace('function skl_hue_rotate(', 'function dac_hue_t(', $hf));
		/* Pure red through 120 degrees is pure green in this matrix's
		   luminance-preserving form -- the channels rotate but the
		   brightness does not jump. Checked as a direction rather than an
		   exact value, because the spec's matrix is deliberately not a
		   naive HSL spin. */
		$im = imagecreatetruecolor(1, 1);
		imagesetpixel($im, 0, 0, (255 << 16));          // pure red
		dac_hue_t($im, 120);
		$p = imagecolorat($im, 0, 0);
		$r = ($p >> 16) & 255; $g = ($p >> 8) & 255; $bl = $p & 255;
		ok($g > $r && $g > $bl,
		   'red rotated 120 degrees is not predominantly green (' . "$r/$g/$bl" . '), so the '
		 . 'matrix is transcribed wrong');
		/*
		 * LUMINANCE IS INVARIANT, which is the defining property of this
		 * matrix and the reason it is not a naive HSL spin. It is also the
		 * only check that catches a slip in ANY single coefficient -- a
		 * direction test passes with one number wrong, because the hue
		 * still moves, just to the wrong place and at the wrong brightness.
		 */
		$lum = function ($rr, $gg, $bb) { return 0.213*$rr + 0.715*$gg + 0.072*$bb; };
		$worst = 0.0;
		/* MID-TONES ONLY. A fully saturated primary rotates straight off the
		   edge of the gamut and the clamp eats 30 to 45 of luminance, which
		   is the clamp doing its job and not the matrix being wrong. In
		   gamut the drift is under half a unit, so the tolerance can be
		   tight enough to catch a single coefficient. */
		foreach (array(array(200,120,40), array(128,128,128), array(150,110,90),
		               array(120,140,100), array(90,100,130)) as $c0) {
			foreach (array(36, 72, 144, 216, 288) as $dg) {
				$t = imagecreatetruecolor(1, 1);
				imagesetpixel($t, 0, 0, ($c0[0] << 16) | ($c0[1] << 8) | $c0[2]);
				dac_hue_t($t, $dg);
				$v = imagecolorat($t, 0, 0);
				$d = abs($lum(($v >> 16) & 255, ($v >> 8) & 255, $v & 255)
				       - $lum($c0[0], $c0[1], $c0[2]));
				if ($d > $worst) $worst = $d;
			}
		}
		/* Clamping at the gamut edges moves it a little; a transcription
		   error moves it a lot. */
		ok($worst < 1.5,
		   sprintf('the hue matrix shifts luminance by up to %.1f, so it is not the '
		 . 'luminance-preserving one from the spec and the card will not match the page',
		   $worst));
		/* And 0 degrees must be identity, or every wave 1 card is subtly
		   recoloured for no reason. */
		$im2 = imagecreatetruecolor(1, 1);
		imagesetpixel($im2, 0, 0, (137 << 16) | (42 << 8) | 200);
		dac_hue_t($im2, 0);
		ok(imagecolorat($im2, 0, 0) === ((137 << 16) | (42 << 8) | 200),
		   'hue-rotate(0) is not identity, so an unrotated wave 1 boss is still altered');
		/* 360 is a full turn and must come back to itself. */
		$im3 = imagecreatetruecolor(1, 1);
		imagesetpixel($im3, 0, 0, (137 << 16) | (42 << 8) | 200);
		dac_hue_t($im3, 360);
		$q = imagecolorat($im3, 0, 0);
		ok(abs((($q >> 16) & 255) - 137) <= 2 && abs((($q >> 8) & 255) - 42) <= 2
		   && abs(($q & 255) - 200) <= 2,
		   'a full 360 turn does not return the original colour, so the matrix drifts');
	}
	/* THE GREY FOR A DOWNED BOSS STILL WINS. The page stacks them that way
	   too: a boss somebody dropped is grey whatever wave it belonged to. */
	$hueAt  = strpos($src, 'skl_hue_rotate($tile');
	$greyAt = strpos($src, 'IMG_FILTER_GRAYSCALE');
	ok($hueAt !== false && $greyAt !== false && $hueAt < $greyAt,
	   'the hue is applied after the dimming, so a dropped boss comes out coloured');

	/* And the page has to point og:image at it, or none of this is reached. */
	$page = file_get_contents(__DIR__ . '/dhcarena.php');
	ok(strpos($page, 'dhc-arena-card.php?run=') !== false,
	   'the Bosses page never points og:image at the run card');
	ok(strpos($page, "\$dhca_share_run = (\$IS_BOSS && isset(\$_GET['run']))") !== false,
	   'the run card is claimed outside boss mode, so the Arena can serve a boss picture');
	ok(strpos($page, "+ (bossRun > 0 ? '?run=' + bossRun : '')") !== false,
	   'the share link does not name the run, so the post gets the generic card');
}

echo "\nthe Arena page only claims a match card when there is a match\n";
{
	$page = file_get_contents(__DIR__ . '/dhcarena.php');
	ok(strpos($page, "\$dhca_share_battle = isset(\$_GET['battle']) ? (int)\$_GET['battle'] : 0;") !== false,
	   'the page does not read ?battle=, so a shared match cannot have its own card');
	ok(strpos($page, 'dhc-arena-card.php?battle=') !== false,
	   'the page never points og:image at the match card');
	/* Without ?battle= it must still be the generic card -- most visits are
	   not shares. */
	ok(strpos($page, 'if ($dhca_share_battle > 0) {') !== false,
	   'the match card is claimed unconditionally, including on a plain visit');
	/*
	 * THE CANONICAL STAYS THE BARE PAGE. Thousands of shared matches must
	 * consolidate onto one indexable URL rather than spawning one each.
	 *
	 * It now varies by INTERFACE -- dhcbosses.php is the same file with
	 * $DHC_MODE set, and the two pages are genuinely two documents -- so this
	 * EXECUTES the expression in both modes instead of pinning one spelling.
	 * What must hold either way: a bare page URL and no query string, whatever
	 * ?battle= was on the request.
	 */
	$cexpr = '';
	if (preg_match('~\$dhca_canonical = (.*?);\n~s', $page, $mm)) $cexpr = $mm[1];
	ok($cexpr !== '', 'the canonical is not assigned in one statement any more');
	foreach (array(false => 'dhcarena.php', true => 'dhcbosses.php') as $isb => $want) {
		$IS_BOSS = (bool)$isb;
		$got = @eval('return ' . $cexpr . ';');
		ok($got === 'https://www.skulliance.io/staking/' . $want,
		   ($isb ? 'the Bosses' : 'the Arena') . ' canonical is not its own bare page: '
		 . var_export($got, true));
		ok(is_string($got) && strpos($got, '?') === false,
		   ($isb ? 'the Bosses' : 'the Arena') . ' canonical carries a query string, so every '
		 . 'shared match is its own indexable page');
	}
	ok(strpos($page, "\$dhca_canonical .=") === false,
	   'the canonical is appended to, so it can still grow a per-battle tail');
	ok(strpos($cexpr, 'share_battle') === false && strpos($cexpr, '_GET') === false,
	   'the canonical is built from the request, so a shared match gets its own URL');
	/* And the share link has to carry the battle or none of this fires. */
	ok(strpos($page, "+ (battleId > 0 ? '?battle=' + battleId : '')") !== false,
	   'the share link does not name the battle, so the post gets the generic card');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
