<?php
/**
 * rivalries-fixture.php -- real dhca_rivalry_layout() output for the JS harness.
 *
 * The layouts are produced by the SHIPPED function, not hand-written JSON, so
 * the renderer is tested against the shape it will actually be handed and a
 * change to the layout's keys breaks the map's tests rather than the map.
 */
$dir = isset($argv[1]) ? rtrim($argv[1], '/') : sys_get_temp_dir();
require_once __DIR__ . '/dhcarena-lib.php';

function fx_player($id, $name, $att, $def, $wins, $losses) {
	return array('id'=>$id, 'name'=>$name, 'avatar'=>"https://example.test/$id.png",
	             'att'=>$att, 'def'=>$def, 'wins'=>$wins, 'losses'=>$losses,
	             'battles'=>$att + $def);
}
/*
 * Fighters carry LAYERS, the shape dhca_rivalry_art() leaves behind: the trait
 * PNGs that compose the portrait. One Fighter below wears a single-sided arm,
 * which is drawn from the two-arm master with half clipped away -- the only
 * layer kind that needs more than a plain <image>, and therefore the one worth
 * having in a fixture.
 */
function fx_fighter($id, $name, $b, $att, $def, $fell, $dealt, $clip = '') {
	$layers = array(
		array('src'=>"web/250/torso/t$id.png", 'n'=>0,  'clip'=>''),
		array('src'=>"web/250/head/h$id.png",  'n'=>-4, 'clip'=>''),
	);
	if ($clip !== '') $layers[] = array('src'=>"web/250/arms/a$id.png", 'n'=>0, 'clip'=>$clip);
	return array('id'=>$id, 'name'=>$name, 'battles'=>$b, 'att'=>$att,
	             'def'=>$def, 'fell'=>$fell, 'dealt'=>$dealt, 'layers'=>$layers);
}

/*
 * THE BUSY FIXTURE IS ASYMMETRIC ON EVERY AXIS the renderer reads, because a
 * map test that cannot tell one player from another proves nothing:
 *   - battle counts differ, so the radii differ
 *   - one pair is a feud, one is one-sided, so arrowheads and dashes differ
 *   - pair weights differ, so stroke widths differ
 *   - one player leans attacking, one leans defending, one leans neither
 *   - one player fields three Fighters, one fields one, one fields none
 */
$busy = dhca_rivalry_layout(array(
	'days' => DHCA_RIVALRY_DAYS, 'battles' => 26,
	'players' => array(
		1 => fx_player(1, 'Ashgrave',  14, 2, 9, 7),    // aggressor, 16 battles
		2 => fx_player(2, 'Bryn',       2, 12, 5, 9),   // target, 14
		3 => fx_player(3, 'Cinderhal',  6, 5, 6, 5),    // even, 11
		4 => fx_player(4, 'Dross',      1, 3, 2, 2),    // quiet, 4
	),
	'edges' => array(
		array('a'=>3, 'd'=>4, 'n'=>3,  'aw'=>2),   // one-sided, light
		array('a'=>1, 'd'=>2, 'n'=>11, 'aw'=>7),   // the feud, heaviest
		array('a'=>2, 'd'=>1, 'n'=>2,  'aw'=>2),
		array('a'=>1, 'd'=>3, 'n'=>3,  'aw'=>2),
		array('a'=>3, 'd'=>1, 'n'=>3,  'aw'=>1),   // a second, lighter feud
		array('a'=>4, 'd'=>2, 'n'=>1,  'aw'=>0),
	),
	'fighters' => array(
		/* SIX FIGHTERS, not three. The satellites are spread over an arc that
		   deliberately skips the bottom of the ring so they cannot land on the
		   two label lines -- and with only three dots, a bug that restored the
		   full 360 degrees still happens to miss the label. Six is enough that
		   a full ring must put one there. */
		1 => array(
			fx_fighter(71, 'Vex',       16, 14, 2, 3, 1600, 'right'),
			fx_fighter(72, 'Hollow',    11,  9, 2, 1, 900, 'left'),
			fx_fighter(73, 'Pale Cur',   9,  7, 2, 2, 540),
			fx_fighter(74, 'Grin',       6,  5, 1, 0, 410),
			fx_fighter(75, 'Sallow',     6,  4, 2, 1, 300),
			fx_fighter(76, 'Thin Reed',  4,  4, 0, 0, 220),
		),
		3 => array(fx_fighter(91, 'Ninefold', 11, 6, 5, 2, 700)),
		/* 2 and 4 field nothing: battles logged before the participation
		   ledger existed. */
	),
));

$solo = dhca_rivalry_layout(array(
	'days' => DHCA_RIVALRY_DAYS, 'battles' => 1,
	'players'  => array(8 => fx_player(8, 'Lone', 1, 0, 1, 0)),
	'edges'    => array(),
	'fighters' => array(8 => array(fx_fighter(55, 'Only', 1, 1, 0, 0, 90))),
));

$nofighters = dhca_rivalry_layout(array(
	'days' => DHCA_RIVALRY_DAYS, 'battles' => 5,
	'players' => array(
		5 => fx_player(5, 'Marrow', 5, 0, 3, 2),
		6 => fx_player(6, 'Quill',  0, 5, 2, 3),
	),
	'edges'    => array(array('a'=>5, 'd'=>6, 'n'=>5, 'aw'=>3)),
	'fighters' => array(),
));

$empty = dhca_rivalry_layout(array(
	'days' => DHCA_RIVALRY_DAYS, 'battles' => 0,
	'players' => array(), 'edges' => array(), 'fighters' => array(),
));

/*
 * A CROWDED MAP, at the scale the Arena actually runs at. The four-player
 * fixture above is driven by the ring's MINIMUM radius, so a renderer that
 * ignored how much room the clusters need still fit them in by luck. Ten
 * players with full Crews is where the circumference, and not the floor,
 * decides the radius -- and where clusters overlap if it is got wrong.
 */
$cp = array(); $ce = array(); $cf = array();
for ($i = 1; $i <= 10; $i++) {
	$att = 30 - $i * 2; $def = 4 + $i;
	$cp[$i] = fx_player($i, 'Player' . $i, $att, $def, (int)(($att + $def) * 0.5), (int)(($att + $def) * 0.5));
	$cf[$i] = array();
	/* Crews of 3 to 6, so cluster sizes differ and the ring has to cope. */
	for ($j = 0; $j < 3 + ($i % 4); $j++) {
		$cf[$i][] = fx_fighter(1000 + $i * 10 + $j, "F{$i}_{$j}", 12 - $j, 8 - $j, 4,
		                       $j % 2, 300 - $j * 20, $j === 0 ? 'right' : '');
	}
	/* A ring of grudges plus a couple of chords across it. */
	$ce[] = array('a' => $i, 'd' => ($i % 10) + 1, 'n' => 11 - $i, 'aw' => (int)((11 - $i) / 2));
	if ($i <= 3) $ce[] = array('a' => ($i % 10) + 1, 'd' => $i, 'n' => $i, 'aw' => 1);
	if ($i <= 4) $ce[] = array('a' => $i, 'd' => (($i + 4) % 10) + 1, 'n' => 2, 'aw' => 1);
}
$crowded = dhca_rivalry_layout(array(
	'days' => DHCA_RIVALRY_DAYS, 'battles' => 180,
	'players' => $cp, 'edges' => $ce, 'fighters' => $cf,
));

file_put_contents($dir . '/layouts.json', json_encode(array(
	'crowded' => $crowded,
	'busy' => $busy, 'solo' => $solo, 'nofighters' => $nofighters, 'empty' => $empty,
), JSON_UNESCAPED_SLASHES));
echo "layouts written: busy(" . count($busy['nodes']) . " nodes, " . count($busy['pairs']) . " pairs)"
   . ", crowded(" . count($crowded['nodes']) . " nodes, " . count($crowded['pairs']) . " pairs)\n";
