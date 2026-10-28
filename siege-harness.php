<?php
/**
 * siege-harness.php — the Siege map's data and geometry. CLI only.
 *
 * Run: php siege-harness.php
 *
 * THREE THINGS THAT FAIL QUIETLY, which is why they are here rather than
 * left to a look at the page:
 *
 *   THE WINDOW IS WEEKS. The Rivalries map this is modelled on windows on
 *   `started_at >= NOW() - INTERVAL n DAY`. Copy that here and "this week"
 *   becomes a rolling seven days that straddles the 4pm Thursday boundary --
 *   so damage against last week's crew is drawn against this week's. It
 *   would look completely normal.
 *
 *   THE SHELLS MUST STAY APART. Wave health collapses as the week runs out,
 *   so sqrt-scaled shells for the late waves land a few pixels apart and
 *   fuse into one thick line. An honest drawing nobody can read is not
 *   honest.
 *
 *   WAVES ARE NOT A RANK. Wave 1 stands for most of the week, so the wave a
 *   player reached is mostly a statement about when they logged in. Nothing
 *   here may turn it into a per-player score.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok    $what\n"; return true; }
	$fail++; echo "  FAIL  $what\n"; return false;
}

require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/week-start.php';
function discordmsg() { return true; }
require_once __DIR__ . '/dhcbosses-lib.php';
$LIB = harness_strip_comments(file_get_contents(__DIR__ . '/dhcbosses-lib.php'));

/*
 * A STUB THAT ANSWERS THE FOUR QUERIES dhcb_siege() ASKS, matched on a
 * fragment of each. Deliberately not a reimplementation: the real function
 * runs, builds the real arrays and is graded on what it produces.
 */
class SgRes {
	public $num_rows = 0; private $r; private $i = 0;
	public function __construct($r = array()) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return isset($this->r[$this->i]) ? $this->r[$this->i++] : null; }
}
class SgConn {
	public $players = array(), $days = array(), $waves = array(), $fighters = array();
	public $asked = array();
	public function real_escape_string($x) { return addslashes((string)$x); }
	public function query($sql) {
		if (strpos($sql, 'FROM dhc_boss_day') !== false)          { $this->asked[] = 'day';      return new SgRes($this->days); }
		if (strpos($sql, 'FROM dhc_boss_run_fighters') !== false) { $this->asked[] = 'fighters'; return new SgRes($this->fighters); }
		if (strpos($sql, 'GROUP BY user_id, wave') !== false)     { $this->asked[] = 'waves';    return new SgRes($this->waves); }
		if (strpos($sql, 'FROM dhc_boss_runs r') !== false)       { $this->asked[] = 'players';  $this->lastPlayers = $sql; return new SgRes($this->players); }
		return new SgRes();
	}
	public $lastPlayers = '';
}

/* A week shaped like the one that was reported from a phone: wave 6 standing,
   five crews already down, three players of very different sizes. */
function sg_week() {
	$c = new SgConn();
	$c->players = array(
		array('user_id'=>1,'username'=>'oculusorbus','discord_id'=>'d1','avatar'=>'a1',
		      'dealt'=>22571,'runs'=>11,'fielded'=>33,'scoring'=>33,'weeks_in'=>1,'rounds'=>4,'whi'=>6),
		array('user_id'=>2,'username'=>'mikej','discord_id'=>'d2','avatar'=>'a2',
		      'dealt'=>9499,'runs'=>5,'fielded'=>15,'scoring'=>15,'weeks_in'=>1,'rounds'=>3,'whi'=>4),
		array('user_id'=>3,'username'=>'michael','discord_id'=>'d3','avatar'=>'a3',
		      'dealt'=>5630,'runs'=>3,'fielded'=>9,'scoring'=>9,'weeks_in'=>1,'rounds'=>2,'whi'=>2),
	);
	/* Real measured budgets: 26,334 / 8,927 / 3,026 / 1,622 / 1,374, then the
	   wave standing now at full health. Three bosses per wave, split on the
	   shipped hp shares so the totals are the crew's. */
	$sizes = array(1=>26334, 2=>8927, 3=>3026, 4=>1622, 5=>1374, 6=>7126);
	foreach ($sizes as $w => $tot) {
		foreach (dhcb_bosses() as $b) {
			$hp   = (int)round($tot * $b['hp'] / 1.0);
			$live = ($w === 6);
			$c->days[] = array('day'=>'2026-10-22','wave'=>$w,'boss_key'=>$b['key'],
			                   'max_health'=>$hp, 'health'=>$live ? $hp : 0,
			                   'killed_at'=>$live ? null : '2026-10-24 10:00:00',
			                   'killed_by'=>$live ? null : ($b['rank'] === 2 ? 2 : 1));
		}
	}
	$c->waves = array(
		array('user_id'=>1,'wave'=>1,'n'=>4,'dealt'=>9000),
		array('user_id'=>1,'wave'=>3,'n'=>3,'dealt'=>6000),
		array('user_id'=>1,'wave'=>6,'n'=>4,'dealt'=>7571),
		array('user_id'=>2,'wave'=>1,'n'=>3,'dealt'=>6000),
		array('user_id'=>2,'wave'=>4,'n'=>2,'dealt'=>3499),
		array('user_id'=>3,'wave'=>1,'n'=>3,'dealt'=>5630),
	);
	$c->fighters = array(
		array('fighter_id'=>11,'user_id'=>1,'serial'=>101,'name'=>'Big Dog','traits'=>'{"torso":"t1"}','dealt'=>9000,'runs'=>8,'fell'=>2),
		array('fighter_id'=>12,'user_id'=>1,'serial'=>102,'name'=>'','traits'=>'{"torso":"t2"}','dealt'=>7000,'runs'=>7,'fell'=>1),
		array('fighter_id'=>21,'user_id'=>2,'serial'=>201,'name'=>'Marge','traits'=>'{"torso":"t3"}','dealt'=>9499,'runs'=>5,'fell'=>0),
		array('fighter_id'=>31,'user_id'=>3,'serial'=>301,'name'=>null,'traits'=>'bad json','dealt'=>5630,'runs'=>3,'fell'=>3),
		/* DISASSEMBLED. dhcf_delete_fighter() really deletes the row, so the
		   LEFT JOIN returns a run with no Fighter behind it -- serial and
		   name and traits all NULL. Left alone it fell through to
		   dhcf_default_name(0) = "DHC2F000", a Fighter number that cannot
		   exist, printed next to real damage. */
		array('fighter_id'=>99,'user_id'=>2,'serial'=>null,'name'=>null,'traits'=>null,'dealt'=>1200,'runs'=>2,'fell'=>1),
	);
	return $c;
}

echo "-- the window is whole weeks, never a rolling seven days --\n";
$src = substr($LIB, strpos($LIB, 'function dhcb_siege('));
$src = substr($src, 0, strpos($src, "\nfunction dhcb_siege_art"));
ok(strpos($src, 'INTERVAL') === false,
   'dhcb_siege() windows on an INTERVAL, so "this week" is a rolling seven days that '
 . 'straddles the 4pm Thursday boundary and mixes two boss crews');
ok(substr_count($src, "r.day >= '\$ef'") >= 1 && strpos($src, "r.day <= '\$ew'") !== false,
   'the window is not bounded by week keys at both ends');
/* And the key arithmetic itself, run rather than read. */
ok(dhcb_week_back('2026-10-22', 1) === '2026-10-22', 'one week back is not the week itself');
ok(dhcb_week_back('2026-10-22', 4) === '2026-10-01', 'four weeks back is wrong: ' . dhcb_week_back('2026-10-22', 4));
ok(dhcb_week_back('2026-10-22', 13) === '2026-07-30', 'thirteen weeks back is wrong: ' . dhcb_week_back('2026-10-22', 13));
/* Every key it produces must still be a Thursday, or the window starts
   mid-week and the first week of it is a partial crew. */
$thu = true;
foreach (array(1,2,4,8,13,26) as $n) {
	$d = DateTime::createFromFormat('!Y-m-d', dhcb_week_back('2026-10-22', $n), new DateTimeZone('America/Chicago'));
	if (!$d || $d->format('N') !== '4') $thu = false;
}
ok($thu, 'a window start is not a Thursday, so it begins mid-week');

echo "\n-- what one week produces --\n";
$c = sg_week();
$s = dhcb_siege($c, 1, '2026-10-22');
ok(count($s['players']) === 3, 'three players in, ' . count($s['players']) . ' out');
ok($s['dealt'] === 37700, 'the window total is ' . $s['dealt'] . ', expected 37,700');
ok(count($s['waves']) === 6, 'six waves stood this week, got ' . count($s['waves']));
ok($s['downed'] === 5, 'five crews went down, got ' . $s['downed']);
/* The per-Fighter figure is the ladder's ranking number and must match it. */
ok($s['players'][1]['per'] === (int)round(22571 / 33),
   'per-Fighter disagrees with the ladder: ' . $s['players'][1]['per']);
/* A kill is attributed to the player who landed it, and to nobody else. */
ok(count($s['players'][1]['kills']) === 10 && count($s['players'][2]['kills']) === 5,
   'kills are not attributed by killed_by: ' . count($s['players'][1]['kills'])
 . '/' . count($s['players'][2]['kills']));
ok(empty($s['players'][3]['kills']), 'a player who landed no kill was credited with one');
/* Waves present, which is the spoke. */
ok(array_keys($s['players'][1]['waves']) === array(1,3,6),
   'the waves a player was present for are wrong: ' . implode(',', array_keys($s['players'][1]['waves'])));
/* A Fighter row with unparseable traits must still produce a Fighter. */
ok(isset($s['fighters'][3][0]) && $s['fighters'][3][0]['traits'] === array(),
   'a Fighter whose traits will not decode was dropped rather than drawn blank');
ok($s['fighters'][1][0]['name'] === 'Big Dog' && $s['fighters'][1][1]['name'] !== '',
   'an unnamed Fighter has no display name');
/* THE DISASSEMBLED ONE. Its damage is real and stays in the player's total,
   so it is still drawn -- but it must say what it is rather than inventing
   a serial. */
$dis = null;
foreach ($s['fighters'][2] as $f) if (!empty($f['gone'])) $dis = $f;
ok($dis !== null, 'a run whose Fighter has been disassembled vanished from the map');
if ($dis) {
	ok($dis['name'] === 'Disassembled',
	   'a disassembled Fighter is named "' . $dis['name'] . '", which looks like a real one');
	ok((int)$dis['serial'] === 0, 'a disassembled Fighter claims serial ' . $dis['serial']);
	ok($dis['dealt'] === 1200, 'its damage was dropped from the total: ' . $dis['dealt']);
}

echo "\n-- waves are the centre, never a per-player rank --\n";
/*
 * Wave 1 stands for most of the week, so a player who ground Thursday to
 * Monday tops out at wave 1 and a player who turned up once on the last day
 * tops out at 6. If anything sized or ranked a player on that, the second
 * would outrank the first on one run.
 */
$lay = dhcb_siege_layout($s);
ok($lay['nodes'][1]['r'] > $lay['nodes'][3]['r'],
   'the biggest contributor is not the biggest circle');
/*
 * THE DECIDING CASE, and the fixture is built so it cannot pass by accident:
 * a player who turned up ONCE on the last block tops out at the highest wave
 * on the board with almost no damage, and the player carrying the week tops
 * out lower because wave 1 stood for most of it. If anything sized or ranked
 * on the wave reached, the tourist would outrank the grinder.
 */
$ct = sg_week();
$ct->players[] = array('user_id'=>4,'username'=>'latecomer','discord_id'=>'d4','avatar'=>'a4',
                       'dealt'=>120,'runs'=>1,'fielded'=>3,'scoring'=>3,'weeks_in'=>1,'rounds'=>1,'whi'=>6);
$ct->players[0]['whi'] = 3;      // the grinder (user 1) never saw past wave 3
$st  = dhcb_siege($ct, 1, '2026-10-22');
$lt  = dhcb_siege_layout($st);
ok($st['players'][4]['top'] > $st['players'][1]['top'],
   'the fixture does not actually invert wave against damage, so this proves nothing');
ok($lt['nodes'][4]['r'] < $lt['nodes'][1]['r'],
   'a player who turned up once on the last block is drawn BIGGER than the one who '
 . 'carried the week, because the wave reached leaked into the sizing');
$smallest = null;
foreach ($lt['nodes'] as $id => $n) if ($smallest === null || $n['r'] < $lt['nodes'][$smallest]['r']) $smallest = $id;
ok($smallest === 4, 'the least damage is not the smallest circle; got player ' . $smallest);

echo "\n-- the shells are the waves, and they stay apart --\n";
ok(count($lay['shells']) === 6, 'six shells for six waves, got ' . count($lay['shells']));
$rs = array(); foreach ($lay['shells'] as $sh) $rs[] = $sh['r'];
ok($lay['shells'][0]['wave'] === 1, 'wave 1 is not the outermost shell');
/*
 * IN WAVE ORDER, OUTERMOST FIRST -- the check that was missing, and the
 * fixture already had the case that breaks it.
 *
 * Wave health is NOT monotonic: the live wave stands at full health (7,126
 * here) while the cheap late waves it followed are already down (1,374).
 * Sizing each ring on its OWN health and sorting by radius therefore drew a
 * real week as 1, 2, 6, 3, 4, 5 -- and the numbers up the top of the map
 * read as nonsense, which is how the owner found it. "Reading inward is
 * reading forward through the week" is the premise of the whole centre and
 * the spoke lengths depend on it.
 */
$order = array(); foreach ($lay['shells'] as $sh) $order[] = (int)$sh['wave'];
ok($order === array(1, 2, 3, 4, 5, 6),
   'the shells are not in wave order outermost-first: ' . implode(',', $order));
/* And the fixture must actually contain the inversion, or this proves
   nothing: a week whose waves happen to shrink monotonically passes under
   either rule. */
$own = array(); foreach ($lay['shells'] as $sh) $own[] = (int)$sh['max'];
$mono = true; for ($i = 1; $i < count($own); $i++) if ($own[$i] > $own[$i - 1]) $mono = false;
ok(!$mono, 'the fixture has no fatter-later wave, so the ordering rule is untested: '
   . implode(',', $own));
/* The band between two rings is the wave, so its area is that wave's
   health. Checked on the outer bands, where the legibility floor has not
   had to touch the radii. */
$a1 = $lay['shells'][0]['r'] ** 2 - $lay['shells'][1]['r'] ** 2;
$a2 = $lay['shells'][1]['r'] ** 2 - $lay['shells'][2]['r'] ** 2;
$h1 = (int)$lay['shells'][0]['max'];
$h2 = (int)$lay['shells'][1]['max'];
ok(abs(($a1 / $a2) - ($h1 / $h2)) < 0.25 * ($h1 / $h2),
   sprintf('the band areas are not proportional to the waves: %.2f vs %.2f',
           $a1 / $a2, $h1 / $h2));
$desc = true; for ($i = 1; $i < count($rs); $i++) if ($rs[$i] >= $rs[$i-1]) $desc = false;
ok($desc, 'the shells do not shrink inward: ' . implode(' > ', $rs));
$tight = 0; for ($i = 1; $i < count($rs); $i++) if ($rs[$i-1] - $rs[$i] < 9) $tight++;
ok($tight === 0, "$tight pair(s) of shells are closer than 9px and fuse into one line: "
   . implode(' > ', $rs));
ok(min($rs) >= 10, 'a late wave collapsed to a dot at the origin: ' . min($rs));
/*
 * AND A WEEK THAT WENT DEEP. Fifteen waves at a fixed 9px step walks past
 * zero, and a shell at a negative radius is drawn as nothing -- the late
 * waves simply disappear with no error anywhere. The step has to divide the
 * room by the shells that must fit in it.
 */
$cd = new SgConn();
$cd->players = array(array('user_id'=>1,'username'=>'solo','discord_id'=>'d1','avatar'=>'a1',
                           'dealt'=>90000,'runs'=>40,'fielded'=>120,'scoring'=>120,
                           'weeks_in'=>1,'rounds'=>7,'whi'=>15));
for ($w = 1; $w <= 15; $w++) {
	$cd->days[] = array('day'=>'2026-10-22','wave'=>$w,'boss_key'=>'d34thbrngr',
	                    'max_health'=>(int)round(26334 * pow(0.62, $w - 1)),
	                    'health'=>0,'killed_at'=>'2026-10-24 10:00:00','killed_by'=>1);
}
$ld = dhcb_siege_layout(dhcb_siege($cd, 1, '2026-10-22'));
$rd = array(); foreach ($ld['shells'] as $sh) $rd[] = $sh['r'];
ok(count($rd) === 15, 'fifteen waves produced ' . count($rd) . ' shells');
ok(min($rd) >= 10, 'a deep week pushed a shell to ' . min($rd) . 'px, at or past the origin');
$dd = true; $mg = (int)max(1, floor($ld['shellGap']));
for ($i = 1; $i < count($rd); $i++) if ($rd[$i-1] - $rd[$i] < $mg) $dd = false;
ok($dd, 'a deep week\'s shells stop shrinking inward or invert: ' . implode(' > ', $rd));
ok($ld['shellGap'] < 9,
   'the step did not shrink for fifteen shells, so it only fits by luck: ' . $ld['shellGap']);
/* Un-forced, the measured week really does fuse -- so the spacing rule is
   doing work rather than guarding a case that cannot happen. */
$raw = array(); $fat = 0;
foreach ($s['waves'] as $w) $fat = max($fat, $w['max']);
foreach ($s['waves'] as $w) $raw[] = (int)round(26 + (120 - 26) * sqrt($w['max'] / $fat));
rsort($raw);
$rawTight = 0; for ($i = 1; $i < count($raw); $i++) if ($raw[$i-1] - $raw[$i] < 9) $rawTight++;
ok($rawTight > 0, 'the raw sqrt radii never collide, so the minimum-step rule is untested: '
   . implode(' > ', $raw));

echo "\n-- a four-week window has no shells, and says so in weeks --\n";
$c4 = sg_week();
$c4->days[] = array('day'=>'2026-10-15','wave'=>1,'boss_key'=>'d34thbrngr',
                    'max_health'=>20000,'health'=>0,'killed_at'=>'2026-10-17 12:00:00','killed_by'=>1);
$s4 = dhcb_siege($c4, 4, '2026-10-22');
$l4 = dhcb_siege_layout($s4);
ok($s4['from'] === '2026-10-01', 'the four-week window starts at ' . $s4['from']);
ok($l4['shells'] === array(),
   'a multi-week window still nests shells, so four different wave 1s are drawn inside '
 . 'each other');
ok(count($l4['byWeek']) === 2, 'the per-week summary has ' . count($l4['byWeek']) . ' weeks, expected 2');
ok($l4['byWeek'][0]['day'] === '2026-10-15' && $l4['byWeek'][1]['day'] === '2026-10-22',
   'the weeks are not in order');
ok($l4['byWeek'][1]['top'] === 6, 'the week summary lost how deep the week got');
/* And a multi-week window must not claim wave membership, because wave
   numbers restart every Thursday. */
$any = false; foreach ($s4['players'] as $p) if ($p['waves']) $any = true;
ok(!$any, 'a multi-week window reports which waves a player fought, but four weeks have '
        . 'four different wave 1s');

echo "\n-- nobody there, nothing drawn --\n";
$empty = new SgConn();
$se = dhcb_siege($empty, 1, '2026-10-22');
ok($se['players'] === array() && $se['waves'] === array(),
   'an empty week invented something to draw');
ok(dhcb_siege_layout($se)['nodes'] === array(), 'an empty week lays out nodes');

/*
 * AND THE LAYOUTS THE JS HARNESS DRAWS. Dumped from the REAL functions above
 * rather than hand-written in JavaScript, so siege.js is graded against what
 * the page will actually hand it -- the mistake that would otherwise hide
 * here is a renderer that works perfectly on a shape the server never sends.
 */
$dump = null;
foreach ($argv as $i => $a) if ($a === '--dump' && isset($argv[$i + 1])) $dump = $argv[$i + 1];
if ($dump !== null) {
	if (!is_dir($dump)) mkdir($dump, 0777, true);
	$ff = function ($s) { return $s; };
	$one  = dhcb_siege(sg_week(), 1, '2026-10-22');
	/*
	 * THE WIDE CASE NEEDS SEVERAL WEEKS IN IT. With one week of rows the bar
	 * row is a single stub that fits inside the core disc whatever the
	 * renderer does, so the ink check on the wide view passed with the
	 * measurement deleted. Thirteen weeks makes the row genuinely wider and
	 * taller than the disc, which is the case that has to hold.
	 */
	$cw = sg_week();
	foreach (array('2026-07-30','2026-08-27','2026-09-10','2026-09-24','2026-10-08','2026-10-15') as $k)
		$cw->days[] = array('day'=>$k,'wave'=>3,'boss_key'=>'d34thbrngr',
		                    'max_health'=>18000,'health'=>0,
		                    'killed_at'=>'2026-08-01 10:00:00','killed_by'=>1);
	$four = dhcb_siege($cw, 13, '2026-10-22');
	/* Layers stand in for art the harness box does not have: the shape is
	   what siege.js consumes, and a missing PNG is the normal case on a dev
	   checkout anyway. */
	foreach (array(&$one, &$four) as &$set) {
		foreach ($set['fighters'] as $uid => $list)
			foreach ($list as $i2 => $f)
				$set['fighters'][$uid][$i2]['layers'] = array(
					array('src' => 'dhc/web/250/torso/t1.png', 'n' => 0,  'clip' => null),
					array('src' => 'dhc/web/250/arms/a1.png',  'n' => 12, 'clip' => 'left'),
				);
	}
	unset($set);
	file_put_contents(rtrim($dump, '/') . '/layouts.json', json_encode(array(
		'week'  => dhcb_siege_layout($one),
		'wide'  => dhcb_siege_layout($four),
		/*
		 * A WIDE WINDOW WITH ONE PLAYER. With several players the ring
		 * surrounds the centre and the clusters' own ink encloses it, so a
		 * centre that is never measured still lands inside the canvas and the
		 * check cannot fail. One player puts the whole middle outside the ink
		 * the clusters produce, which is the case the measurement exists for.
		 */
		'wideSolo' => dhcb_siege_layout(dhcb_siege((function () use ($cw) {
			$c = $cw; $c->players = array($c->players[0]); return $c;
		})(), 13, '2026-10-22')),
		'solo'  => dhcb_siege_layout(dhcb_siege((function () {
			$c = sg_week();
			$c->players = array($c->players[0]);
			return $c;
		})(), 1, '2026-10-22')),
	), JSON_UNESCAPED_SLASHES));
	echo "  dumped layouts to $dump/layouts.json\n";
}

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all siege checks passed\n");
exit($fail ? 1 : 0);
