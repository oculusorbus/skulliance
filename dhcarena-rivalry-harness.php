<?php
/**
 * dhcarena-rivalry-harness.php -- the rivalry graph's arithmetic.
 *
 * dhca_rivalry_layout() takes arrays, so all of this runs with no database.
 * What it is protecting: a radius scale that collapses every node to the same
 * size, and a fold that loses one direction of a feud. Both of those look
 * fine in a screenshot and are wrong.
 *
 * THE FIXTURE IS DELIBERATELY LOPSIDED. Equal battle counts cannot distinguish
 * sqrt scaling from linear, and a fixture where every pair is mutual cannot
 * tell a fold that keeps both directions from one that overwrites.
 */
require_once __DIR__ . '/dhcarena-lib.php';

$fails = 0;
function ok($c, $w) { global $fails; if (!$c) { $fails++; echo "FAIL  $w\n"; } else echo "ok    $w\n"; }

/* 4 players.
 *   1 attacks 2 nine times and wins 6.      (pure aggressor)
 *   2 attacks 1 once and wins 1.            (so 1v2 is a mutual feud, 10 deep)
 *   3 attacks 4 four times and wins 0.      (one-way, and losing)
 *   nobody attacks 3, so 3 is a pure aggressor with no incoming.
 * Battle totals: 1 -> 10, 2 -> 10, 3 -> 4, 4 -> 4.
 */
$riv = array(
	'days' => 30, 'battles' => 14,
	'players' => array(
		1 => array('id'=>1,'name'=>'Ash',  'avatar'=>'a.png','att'=>0,'def'=>0,'wins'=>0,'losses'=>0,'battles'=>10),
		2 => array('id'=>2,'name'=>'Bry',  'avatar'=>'b.png','att'=>0,'def'=>0,'wins'=>0,'losses'=>0,'battles'=>10),
		3 => array('id'=>3,'name'=>'Cid',  'avatar'=>'c.png','att'=>0,'def'=>0,'wins'=>0,'losses'=>0,'battles'=>4),
		4 => array('id'=>4,'name'=>'Dex',  'avatar'=>'d.png','att'=>0,'def'=>0,'wins'=>0,'losses'=>0,'battles'=>4),
	),
	/* THE LIGHT PAIR IS LISTED FIRST, so a layout that forgot to sort would
	   hand back 3v4 ahead of the 10-deep feud and the ordering check below
	   would catch it. A fixture already in the right order cannot. */
	'edges' => array(
		array('a'=>3,'d'=>4,'n'=>4,'aw'=>0),
		array('a'=>1,'d'=>2,'n'=>9,'aw'=>6),
		array('a'=>2,'d'=>1,'n'=>1,'aw'=>1),
	),
	'fighters' => array(
		1 => array(array('id'=>71,'name'=>'Vex','battles'=>10,'att'=>9,'def'=>1,'fell'=>2,'dealt'=>900)),
		/* player 2 has NO fighter rows -- the normal early state */
	),
);

echo "-- the radius scale --\n";
$L = dhca_rivalry_layout($riv, 16, 46);
$n = $L['nodes'];
ok($L['busiest'] === 10, 'the busiest player set the top of the scale');
ok($n[1]['r'] === 46, 'the busiest player gets rmax (got ' . $n[1]['r'] . ')');
ok($n[3]['r'] > 16, 'a quieter player is still bigger than rmin');
ok($n[3]['r'] < 46, '...and smaller than the busiest (got ' . $n[3]['r'] . ')');
/* SQRT, NOT LINEAR. 4/10 of the battles is sqrt(0.4) = 0.632 of the way up the
   range, so r = 16 + 30*0.632 = 35. Linear would give 16 + 30*0.4 = 28. The
   two differ by 7px, which is why the fixture uses 4 and 10 and not 5 and 10
   (sqrt(0.5)=0.707 vs 0.5 would also differ, but 4/10 is further from the
   midpoint and so harder to reach by accident). */
ok($n[3]['r'] === 35, 'r scales with sqrt of the share: 35, not 28 (got ' . $n[3]['r'] . ')');
ok($n[3]['r'] !== 28, '...and specifically not the linear answer');
ok($n[1]['r'] === $n[2]['r'], 'two players with equal battles get equal radii');

echo "\n-- a single player, and an empty graph --\n";
$solo = dhca_rivalry_layout(array('days'=>30,'battles'=>2,'fighters'=>array(),
	'players'=>array(9=>array('id'=>9,'name'=>'Solo','avatar'=>'','att'=>0,'def'=>0,'wins'=>0,'losses'=>0,'battles'=>2)),
	'edges'=>array(array('a'=>9,'d'=>9,'n'=>2,'aw'=>1))));
ok($solo['nodes'][9]['r'] === 46, 'the only player on the graph is the busiest, so rmax');
$empty = dhca_rivalry_layout(array('days'=>30,'battles'=>0,'players'=>array(),'edges'=>array(),'fighters'=>array()));
ok($empty['nodes'] === array() && $empty['pairs'] === array(), 'an empty window lays out to nothing');
ok($empty['busiest'] === 0, 'and divides by nothing');
$zero = dhca_rivalry_layout(array('days'=>30,'battles'=>0,'fighters'=>array(),
	'players'=>array(5=>array('id'=>5,'name'=>'Z','avatar'=>'','att'=>0,'def'=>0,'wins'=>0,'losses'=>0,'battles'=>0)),
	'edges'=>array()));
ok($zero['nodes'][5]['r'] === 16, 'a player with zero battles gets rmin, not a division warning');

echo "\n-- the fold: one pair, both directions remembered --\n";
$p = $L['pairs'];
ok(count($p) === 2, 'three edges fold into two pairs (got ' . count($p) . ')');
ok($p[0]['n'] === 10, 'the heaviest pair is first, and is 9+1 = 10 deep');
ok($p[0]['lo'] === 1 && $p[0]['hi'] === 2, 'keyed low id to high id so direction cannot flip the key');
ok($p[0]['lohi'] === 9, 'Ash attacked Bry 9 times');
ok($p[0]['hilo'] === 1, 'and Bry attacked Ash once -- BOTH directions survived the fold');
ok($p[0]['mutual'] === true, 'so the pair is a feud');
ok($p[1]['n'] === 4 && $p[1]['mutual'] === false, 'the one-way pair is not a feud');
ok($p[1]['hilo'] === 0, 'and its unused direction is zero, not missing');

echo "\n-- two edges in the SAME direction accumulate --\n";
/* dhca_rivalries() GROUPs BY (attacker, defender) so it never emits two rows
   for one direction -- but dhca_rivalry_layout() is a public function and the
   fold must add rather than overwrite, or a caller that splits edges any
   other way (by season, by day) silently loses battles. */
$dup = dhca_rivalry_layout(array('days'=>30,'battles'=>5,'fighters'=>array(),
	'players'=>array(
		1=>array('id'=>1,'name'=>'A','avatar'=>'','att'=>0,'def'=>0,'wins'=>0,'losses'=>0,'battles'=>5),
		2=>array('id'=>2,'name'=>'B','avatar'=>'','att'=>0,'def'=>0,'wins'=>0,'losses'=>0,'battles'=>5)),
	'edges'=>array(
		array('a'=>1,'d'=>2,'n'=>2,'aw'=>1),
		array('a'=>1,'d'=>2,'n'=>3,'aw'=>2))));
ok($dup['pairs'][0]['n'] === 5,      'two same-direction edges sum to 5, not 3 (got ' . $dup['pairs'][0]['n'] . ')');
ok($dup['pairs'][0]['lohi'] === 5,   'and the direction sums too, not overwrites');
ok($dup['pairs'][0]['lowins'] === 3, 'wins accumulate across both rows');
ok($dup['pairs'][0]['mutual'] === false, 'both rows point the same way, so no feud');

echo "\n-- wins are credited to the right player through the fold --\n";
/* Ash won 6 of its 9 attacks and lost the 1 time Bry attacked (aw=1 for Bry).
   So Ash has 6 wins in this pair and Bry has 3 + 1 = 4. */
ok($p[0]['lowins'] === 6, 'Ash took 6 wins (got ' . $p[0]['lowins'] . ')');
ok($p[0]['hiwins'] === 4, 'Bry took 4 (got ' . $p[0]['hiwins'] . ')');
ok($p[0]['lowins'] + $p[0]['hiwins'] === $p[0]['n'], 'every battle in the pair has exactly one winner');
/* Cid attacked Dex 4 times and won none, so all 4 wins belong to Dex (the hi id). */
ok($p[1]['lowins'] === 0 && $p[1]['hiwins'] === 4, 'the losing aggressor gets none of them');
ok($p[1]['lowins'] + $p[1]['hiwins'] === $p[1]['n'], 'and that pair also balances');

echo "\n-- lean: where a player's activity sits --\n";
/* Set the att/def tallies the query would have produced. */
$riv2 = $riv;
$riv2['players'][1]['att'] = 9; $riv2['players'][1]['def'] = 1;   // 9 >= 2 -> aggressor
$riv2['players'][2]['att'] = 1; $riv2['players'][2]['def'] = 9;   // 9 >= 2 -> target
$riv2['players'][3]['att'] = 4; $riv2['players'][3]['def'] = 0;   // aggressor
$riv2['players'][4]['att'] = 0; $riv2['players'][4]['def'] = 4;   // target
$L2 = dhca_rivalry_layout($riv2);
ok($L2['nodes'][1]['lean'] === 'aggressor', 'mostly attacking reads aggressor');
ok($L2['nodes'][2]['lean'] === 'target',    'mostly attacked reads target');
ok($L2['nodes'][1]['lean'] !== $L2['nodes'][2]['lean'], 'and the two are not the same label');
/* Even split, and above the floor. */
$riv3 = $riv2;
$riv3['players'][1]['att'] = 5; $riv3['players'][1]['def'] = 5;
$L3 = dhca_rivalry_layout($riv3);
ok($L3['nodes'][1]['lean'] === '', 'an even split leans neither way');
/* Below the floor, a lopsided ratio means nothing. */
$riv4 = $riv2;
$riv4['players'][3]['battles'] = 2; $riv4['players'][3]['att'] = 2; $riv4['players'][3]['def'] = 0;
$L4 = dhca_rivalry_layout($riv4);
ok($L4['nodes'][3]['lean'] === '', 'two battles is too few to call anyone an aggressor');

echo "\n-- fighters ride with their owner, and an owner may have none --\n";
ok(count($n[1]['fighters']) === 1, 'Ash carries its one Fighter');
ok($n[1]['fighters'][0]['name'] === 'Vex', 'and it is the right one');
ok($n[2]['fighters'] === array(), 'Bry has no Fighter rows yet and gets an empty list, not a null');
ok(isset($n[4]['fighters']), 'every node has the key, so the renderer can loop unconditionally');

echo "\n-- dhca_rivalries() itself, against a stub that reads the SQL --\n";
/*
 * The stub DISPATCHES ON THE QUERY it is handed rather than returning the same
 * rows to every call. A stub that ignores its SQL will happily satisfy a
 * function that asks for the wrong thing, which makes the test worse than
 * nothing: it is the one thing a fake connection must get right.
 */
class FakeRes {
	private $rows; private $i = 0;
	public $num_rows;
	public function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	public function fetch_assoc() { return $this->i < count($this->rows) ? $this->rows[$this->i++] : null; }
}
class FakeConn {
	public $seen = array();
	public function query($sql) {
		$this->seen[] = $sql;
		if (strpos($sql, 'FROM dhc_arena_battles') !== false && strpos($sql, 'attacker_id,') !== false) {
			return new FakeRes(array(
				/* a self-battle, which must be dropped */
				array('attacker_id'=>1,'defender_id'=>1,'n'=>3,'aw'=>3),
				array('attacker_id'=>1,'defender_id'=>2,'n'=>9,'aw'=>6),
				array('attacker_id'=>2,'defender_id'=>1,'n'=>1,'aw'=>1),
				/* a battle naming a user the users table will not return */
				array('attacker_id'=>1,'defender_id'=>99,'n'=>2,'aw'=>2),
			));
		}
		if (strpos($sql, 'FROM users') !== false) {
			return new FakeRes(array(
				array('id'=>1,'username'=>'Ash','discord_id'=>'11','avatar'=>'h1'),
				array('id'=>2,'username'=>'Bry','discord_id'=>'22','avatar'=>'h2'),
				/* 99 is deliberately absent -- a deleted account */
			));
		}
		if (strpos($sql, 'dhc_arena_battle_fighters') !== false) {
			return new FakeRes(array(
				array('fighter_id'=>71,'user_id'=>1,'name'=>'Vex','serial'=>71,'n'=>4,'att'=>4,'def'=>0,'fell'=>1,'dealt'=>400),
				array('fighter_id'=>72,'user_id'=>1,'name'=>'','serial'=>72,'n'=>9,'att'=>8,'def'=>1,'fell'=>0,'dealt'=>900),
				/* owned by somebody not on the graph -- must be dropped */
				array('fighter_id'=>73,'user_id'=>99,'name'=>'Ghost','serial'=>73,'n'=>5,'att'=>5,'def'=>0,'fell'=>0,'dealt'=>50),
			));
		}
		return new FakeRes(array());
	}
}
$fc = new FakeConn();
$R  = dhca_rivalries($fc, 30);
ok(count($fc->seen) === 3, 'three reads, not one per player (got ' . count($fc->seen) . ')');
ok(count($R['edges']) === 3, 'the self-battle is dropped, leaving 3 edges (got ' . count($R['edges']) . ')');
foreach ($R['edges'] as $e) ok($e['a'] !== $e['d'], '  no edge points at itself');
ok($R['battles'] === 12, 'battles counts only the kept edges: 9+1+2 = 12 (got ' . $R['battles'] . ')');
ok(count($R['players']) === 2, 'only the two users the table returned become players');
ok(!isset($R['players'][99]), 'the deleted account does not become a node');
/* Ash: attacked 9 + 2 = 11, defended 1. Won 6 attacking, 2 attacking 99, and
   lost the one Bry won -> 8 wins, 4 losses, 12 battles. */
ok($R['players'][1]['att'] === 11, 'Ash attacked 11 times (got ' . $R['players'][1]['att'] . ')');
ok($R['players'][1]['def'] === 1,  'and defended once');
ok($R['players'][1]['wins'] === 8, 'Ash took 8 wins (got ' . $R['players'][1]['wins'] . ')');
ok($R['players'][1]['losses'] === 4, 'and 4 losses');
ok($R['players'][1]['wins'] + $R['players'][1]['losses'] === $R['players'][1]['battles'],
   "Ash's record balances against its battle count");
/* Bry: attacked 1 (won it), defended 9 (won 3 of them) -> 4 wins, 6 losses. */
ok($R['players'][2]['wins'] === 4 && $R['players'][2]['losses'] === 6, 'Bry is 4-6');
ok($R['players'][2]['wins'] + $R['players'][2]['losses'] === $R['players'][2]['battles'], "and Bry's balances too");
ok($R['players'][1]['att'] !== $R['players'][2]['att'], 'the two players are not given the same tallies');
/* Fighters: Ash keeps two, the ghost is dropped, and the unnamed one falls
   back to its default name rather than coming through blank. */
ok(count($R['fighters'][1]) === 2, 'Ash keeps both its Fighters');
ok(!isset($R['fighters'][99]), 'a Fighter owned by a non-node is dropped');
ok($R['fighters'][1][0]['battles'] === 9, 'busiest Fighter sorts first (got ' . $R['fighters'][1][0]['battles'] . ')');
ok($R['fighters'][1][0]['name'] !== '', 'an unnamed Fighter gets its default name, not an empty label');

echo "\n-- the query is shaped to only count settled battles --\n";
$src = file_get_contents(__DIR__ . '/dhcarena-lib.php');
$i = strpos($src, 'function dhca_rivalries(');
$body = substr($src, $i, strpos($src, "\n}", $i) - $i);
ok(substr_count($body, 'outcome IN (1,2)') === 2, 'both battle reads exclude in-progress games');
ok(strpos($body, 'started_at >= NOW() - INTERVAL') !== false, 'and both are windowed');
ok(substr_count($body, 'INTERVAL $d DAY') === 2, 'by the same $d, not two different windows');
ok(strpos($body, '$d   = max(1, (int)$days);') !== false, 'the window is cast and floored before interpolation');

echo "\n-- the DHC strip knows about every DHC page --\n";
/*
 * dhc-nav.php is the one strip across Fighters, Collection, Arena, Sandbox
 * and Rivalries, and its whole point is that it cannot drift into five
 * slightly different strips. The way it drifts anyway is a new page that
 * includes it but is not IN it, which looks fine on every other page and
 * leaves that one with nothing highlighted.
 */
$nav = file_get_contents(__DIR__ . '/dhc-nav.php');
preg_match_all("/^\t'([a-z]+)'\s*=> array\('([^']+)'/m", $nav, $m, PREG_SET_ORDER);
$items = array();
foreach ($m as $hit) $items[$hit[1]] = $hit[2];
ok(count($items) >= 5, 'the strip lists at least five entries (' . count($items) . ')');
ok(isset($items['rivalries']), 'Rivalries is one of them');
ok($items['rivalries'] === 'rivalries.php', 'and points at the right page');
/*
 * A PAGE MAY BE SERVED BY ANOTHER PAGE. dhcbosses.php is two statements --
 * $DHC_MODE = 'bosses' and an include of dhcarena.php -- so the strip, and
 * the line that decides which chip is lit, live in the file it includes. A
 * check that only read the file itself called that a missing strip, which is
 * the opposite of the problem it exists to catch: this entry is a tab, it
 * does light up, and the shared implementation is the point.
 *
 * So the include is followed ONE hop, and only when the file itself has no
 * strip of its own. Deeper than that and a page is doing something this
 * check should not be guessing at.
 */
function nav_source($dir, $file) {
	$src = file_get_contents($dir . '/' . $file);
	if (strpos($src, 'dhcnav_at') !== false) return array($file, $src);
	if (preg_match('~(?:require|include)(?:_once)?\s*(?:__DIR__\s*\.\s*)?[\'"]/?([a-z0-9_-]+\.php)[\'"]~i',
	               $src, $m) && is_file($dir . '/' . $m[1])) {
		$inner = file_get_contents($dir . '/' . $m[1]);
		if (strpos($inner, 'dhcnav_at') !== false) return array($m[1], $inner);
	}
	return array($file, $src);
}
foreach ($items as $key => $href) {
	$file = preg_replace('/#.*$/', '', $href);
	if (!is_file(__DIR__ . '/' . $file)) { ok(false, "  $key points at a missing file ($file)"); continue; }
	list($from, $src) = nav_source(__DIR__, $file);
	$via = ($from === $file) ? '' : " (via $from)";
	ok(strpos($src, "dhcnav_at") !== false, "  $file includes the strip$via");
	/*
	 * THE STATEMENT, NOT A SPELLING. dhcarena.php serves two interfaces and
	 * picks its chip with a ternary, so pinning "dhcnav_at = 'arena'" failed
	 * on a page that highlights its tab correctly in both modes. What has to
	 * be true is that the key is named where the chip is decided.
	 */
	$stmt = preg_match('~\$dhcnav_at\s*=\s*(.*?);~s', $src, $mm) ? $mm[1] : '';
	ok(strpos($stmt, "'" . $key . "'") !== false
	   || (strpos($href, '#') !== false && strpos($src, '#') !== false),
	   "  $file never names itself '$key'$via, so its chip is not highlighted: "
	   . ($stmt === '' ? '(no assignment found)' : trim($stmt)));
}
/* And the page it was lifted out of must not also link to it: two routes to
   one page, one of them a whole panel, is how the Arena ended up advertising
   a map that is already a tab. */
$arena = file_get_contents(__DIR__ . '/dhcarena.php');
ok(strpos($arena, 'rivalries.php') === false,
   'the Arena does not carry its own second link to Rivalries');

echo "\n-- the page declares every custom property it uses --\n";
/*
 * A SILENT FAILURE WORTH A TEST. rivalries.php is a scoped component: it
 * declares its own palette on .riv-wrap, the way .arena-wrap and .dhcg-wrap
 * do. Reach for a token a sibling component owns -- --bone, in this case --
 * and `background: var(--bone)` is invalid at computed-value time, so the
 * property is simply dropped. The legend's two line swatches vanished and the
 * key read as a pair of captions with nothing beside them. No error, no
 * warning, and it looks fine in any preview that happens to load a stylesheet
 * which does declare it.
 *
 * A fallback -- var(--bone, #e8eaed) -- is equally acceptable: the rule is
 * that the page must not depend on a token it neither declares nor defaults.
 */
$rivsrc = file_get_contents(__DIR__ . '/rivalries.php');
$cssAt  = strpos($rivsrc, '<style>');
$css    = substr($rivsrc, $cssAt, strpos($rivsrc, '</style>') - $cssAt);
preg_match_all('/(--[a-z0-9-]+)\s*:/', $css, $dm);
$declared = array_flip($dm[1]);
ok(count($declared) >= 6, 'the page declares a palette of its own (' . count($declared) . ' tokens)');
ok(isset($declared['--bone']), '--bone among them, since the legend swatches are drawn in it');
preg_match_all('/var\(\s*(--[a-z0-9-]+)\s*(,?)/', $css, $um, PREG_SET_ORDER);
ok(count($um) > 0, 'and it uses them');
$orphans = array();
foreach ($um as $u) {
	if (!isset($declared[$u[1]]) && $u[2] !== ',') $orphans[$u[1]] = true;
}
ok(!$orphans, 'no token is used without either being declared here or carrying a fallback'
   . ($orphans ? ' (' . implode(', ', array_keys($orphans)) . ')' : ''));

echo "\n-- the map is bounded on both axes --\n";
/*
 * MEASURED IN A BROWSER, PINNED HERE. At 2560x1323 the map renders 1243x993
 * inside a full-width column, centred with equal gutters, and the page does
 * not scroll. That came from three attempts: a fixed-size box that scrolled
 * sideways, then width:100% which filled the column and ran off the bottom of
 * a large display, and finally bounds on both axes with the viewBox free to
 * scale between them.
 *
 * This cannot re-measure a layout without a browser, so it guards the shape
 * of the rule instead -- the regression it exists for is somebody restoring
 * width:100%, which silently reintroduces the overflow.
 */
$mapRule = '';
if (preg_match('/\.riv-map svg\{([^}]*)\}/', $css, $mm)) $mapRule = $mm[1];
ok($mapRule !== '', 'the map rule is there to check');
ok(strpos($mapRule, 'max-width') !== false,  'it is bounded horizontally');
ok(strpos($mapRule, 'max-height') !== false, 'and vertically, so it fits the window');
ok(!preg_match('/(^|;)\s*width\s*:\s*100%/', $mapRule),
   'and does NOT force width:100%, which is what made it overflow the screen');
ok(preg_match('/(^|;)\s*width\s*:\s*auto/', $mapRule)
   && preg_match('/(^|;)\s*height\s*:\s*auto/', $mapRule),
   'both axes are auto, so the viewBox scales to whichever bound bites first');
/* Centring moved to the flex container: margin:auto only centres a block
   whose width is resolved, and this one's deliberately is not. */
$boxRule = '';
if (preg_match('/\.riv-map\{([^}]*)\}/', $css, $bm)) $boxRule = $bm[1];
ok(strpos($boxRule, 'justify-content:center') !== false, 'the container centres it');
ok(strpos($boxRule, 'display:flex') !== false, '...as a flex row, not with margin:auto');

echo "\n-- the player panel stacks above the navbar, below the Fighter panel --\n";
/*
 * At z-index 70 this sat UNDER the site navbar (200) and the burger button
 * floated over the open sheet. The Fighter panel has always used 9998; the
 * player panel must clear the chrome the same way, and sit just BELOW the
 * Fighter panel so a Fighter opened from the roster layers on top of the
 * player it belongs to rather than behind them.
 */
$veil = '';
if (preg_match('/\.riv-veil\{([^}]*)\}/', $css, $vm)) $veil = $vm[1];
ok($veil !== '', 'the player veil has a rule');
preg_match('/z-index:\s*(\d+)/', $veil, $zm);
$z = $zm ? (int)$zm[1] : 0;
ok($z > 200, "it clears the navbar's z-index of 200 (got $z)");
$fm = file_get_contents(__DIR__ . '/dhc-fighter-modal.php');
preg_match('/#dhcg-veil\{[^}]*z-index:\s*(\d+)/', $fm, $fz);
$fzi = $fz ? (int)$fz[1] : 0;
ok($fzi > 0, "the Fighter panel declares one too ($fzi)");
ok($z < $fzi, "and the player panel sits below it ($z < $fzi), so a Fighter opens on top");
ok(strpos($veil, 'safe-area-inset-top') !== false,
   'and it pads for the safe area, as the Fighter panel does');

echo "\n-- the two maps animate at the same rates --\n";
/*
 * COHESION IS THE REQUIREMENT, so it is the thing tested. The rivalry map
 * borrows the realm map's four animations, and the point is defeated the
 * moment one of them is retuned on its own -- the two surfaces would still
 * both move, just not together, which is harder to notice than it is to
 * cause. This reads both stylesheets and compares.
 */
$realm = file_get_contents(__DIR__ . '/dist/map.css');
ok($realm !== '', 'the realm map stylesheet is readable');

/** Pull the duration and the timing function out of a named rule. */
function riv_anim($css, $sel) {
	if (!preg_match('/' . preg_quote($sel, '/') . '\s*\{([^}]*)\}/', $css, $m)) return null;
	if (!preg_match('/animation:\s*([a-z-]+)\s+([\d.]+)s\s+([a-z-]+)/', $m[1], $a)) return null;
	return array('name' => $a[1], 'secs' => (float)$a[2], 'ease' => $a[3]);
}
/** And the from/to opacities of a named keyframes block. */
function riv_frames($css, $name) {
	if (!preg_match('/@keyframes\s+' . preg_quote($name, '/') . '\s*\{((?:[^{}]|\{[^}]*\})*)\}/', $css, $m)) return null;
	preg_match_all('/opacity:\s*([\d.]+)/', $m[1], $o);
	$v = array_map('floatval', $o[1]);
	if (!$v) {
		preg_match('/stroke-dashoffset:\s*(-?\d+)/', $m[1], $d);
		return $d ? array('dashoffset' => (int)$d[1]) : null;
	}
	sort($v);
	return array('lo' => $v[0], 'hi' => $v[count($v) - 1]);
}

$pairs = array(
	'lines at rest'   => array('.riv-map line.riv-line', '.raid-line',         'riv-pulse', 'raid-pulse'),
	'lines on hover'  => array('.riv-map line.riv-march', '.raid-line-active', 'riv-march', 'raid-march'),
	'a leaning player'=> array('.riv-ring-lean', '.marker-active-glow',        'riv-glow',  'marker-glow-pulse'),
	'you'             => array('.riv-ring-me',   '.marker-own-glow',           'riv-own',   'marker-own-pulse'),
);
foreach ($pairs as $what => $pr) {
	list($mineSel, $theirsSel, $mineKf, $theirsKf) = $pr;
	$a = riv_anim($css, $mineSel);
	$b = riv_anim($realm, $theirsSel);
	ok($a !== null, "$what: the rivalry rule is there ($mineSel)");
	ok($b !== null, "$what: the realm rule is there ($theirsSel)");
	if (!$a || !$b) continue;
	ok(abs($a['secs'] - $b['secs']) < 0.001,
	   "  $what runs for the same time ({$a['secs']}s vs {$b['secs']}s)");
	ok($a['ease'] === $b['ease'], "  and on the same curve ({$a['ease']})");
	$fa = riv_frames($css, $mineKf);
	$fb = riv_frames($realm, $theirsKf);
	ok($fa !== null && $fb !== null, "  both keyframes are readable");
	if ($fa && $fb) ok($fa == $fb, "  over the same range (" . json_encode($fa) . ")");
}
/* Your pulse must be the slower one, on both maps -- that is what makes it
   findable rather than just another moving thing. */
$me   = riv_anim($css, '.riv-ring-me');
$lean = riv_anim($css, '.riv-ring-lean');
ok($me && $lean && $me['secs'] > $lean['secs'],
   'your own pulse is slower than a leaning player\'s, as on the realm map');
$rme   = riv_anim($realm, '.marker-own-glow');
$rlean = riv_anim($realm, '.marker-active-glow');
ok($rme && $rlean && $rme['secs'] > $rlean['secs'], '...which is the relationship there too');

/* And motion is optional on both. */
ok(preg_match('/prefers-reduced-motion[^}]*\{[^@]*animation:\s*none/s', $css),
   'reduced motion stops the animations, not just the transitions');

echo "\n" . ($fails ? "$fails FAILED\n" : "all passed\n");
exit($fails ? 1 : 0);
