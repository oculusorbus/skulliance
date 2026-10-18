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
	if (!defined('DAC_W')) define('DAC_W', $W);
	if (!defined('DAC_H')) define('DAC_H', $H);

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
		imagefilledrectangle($im, 240, 110, 330, 150, imagecolorallocate($im, 0, 0, 0));
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
		ok($areaFrac > 0.30, sprintf('the Fighters cover only %.0f%% of the card', $areaFrac * 100));
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
	/* THE CANONICAL STAYS THE BARE PAGE. Thousands of shared matches must
	   consolidate onto one indexable URL rather than spawning one each. */
	$cpos = strpos($page, "\$dhca_canonical = 'https://www.skulliance.io/staking/dhcarena.php';");
	ok($cpos !== false, 'the canonical is no longer the bare Arena page');
	ok(strpos($page, "\$dhca_canonical .=") === false,
	   'the canonical now varies per battle, so every shared match is its own indexable page');
	/* And the share link has to carry the battle or none of this fires. */
	ok(strpos($page, "+ (battleId > 0 ? '?battle=' + battleId : '')") !== false,
	   'the share link does not name the battle, so the post gets the generic card');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
