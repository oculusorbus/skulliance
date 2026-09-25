<?php
/* ============================================================================
   dhcarena-engine.php — pure rules engine for DHC Arena.

   See dhcarena.md for the design this implements, and dhcarena-prototype.php
   for the throwaway the rules were proven in.

   DELIBERATELY ISOLATED from the DB and session layers, exactly as
   cryptconquest-engine.php is: every function here takes and/or returns a plain
   $b (battle) array. No $conn, no $_SESSION, no echo. That split is worth more
   here than it was there, because combat has to be BALANCED, and balance means
   running ten thousand battles with no browser and no database in the loop.

   THE SERVER OWNS THE GAME. The prototype computed damage in the browser, which
   is fine for a toy and unacceptable for something paying real traits and
   ladder points. Here the client sends only "slide gem A to B"; this engine
   validates it, resolves everything, and returns a TIMELINE of what happened
   for the client to animate. The client decides nothing.

   Randomness is seeded and stored with the battle, so a resolution can be
   replayed exactly -- which is what makes a result auditable and a bug
   reproducible.

   $b shape:
     seed        int      PRNG state, advances as the battle runs
     board       int[49]  gem type per cell, 0-4
     bomb        int[49]  0 none, 1 cross, 2 board; negative = armed this move
     mine,foes   fighter[3]
     turn        'mine'|'foes'
     round       int
     over        null|'mine'|'foes'      who WON
     terrain     string   terrain id
     stats       array    bombs, blasts, best chain
     log         array    lines for this resolution only

   fighter shape:
     uid, name, traits{}, kit, rank, side
     maxHp, hp, shield, bleed, surge, ko
     power, critC
   ============================================================================ */

if (!defined('DHCA_N')) {

/* What each individual trait DOES, read from its own label. Kept in its own
   file because it is a table to be argued with, not a rule to be derived. */
require_once __DIR__ . '/dhcarena-roles.php';

define('DHCA_N', 7);            // board is 7x7
define('DHCA_GEMS', 5);         // 0,1,2 = the three ranks; 3 Shield; 4 Charge

/* Tuning. Every one of these was found by simulation in the prototype, not
   chosen by taste -- see the commit history on dhcarena-prototype.php. */
define('DHCA_HP_BASE',      320);   // 150 gave 8-move battles, 380 gave 18
define('DHCA_POWER_BASE',   20);
define('DHCA_VARIANCE',     0.6);   // §4: variance between traits must exceed the tier gap
define('DHCA_SURGE_GAIN',   1);     // flat, NOT match length
define('DHCA_SURGE_MAX',    10);
define('DHCA_EXTRA_TURN',   5);     // a 4 already leaves a bomb
/* A LONE BOMB IS A BIG MATCH. A CHAIN IS AN EVENT.
   Bombs already chained -- a blast that reaches another live bomb sets it off --
   and half of all detonations were multi-bomb. What was missing was any
   consequence: blasts were 7.7% of all damage in a battle, and a blast resolved
   as a match of at most 4, which reaches the MID rank at best. So you could
   hoard four bombs, clear the whole board, and the enemy back Fighter would not
   be touched by it.
   The single bomb is left where it was, because it was balanced. What escalates
   is the chain: every extra bomb raises the damage, and enough of them punch
   all the way to the back rank -- because you did not clear that board with one
   bomb, you cleared it with bombs you had been sitting on. */
define('DHCA_BLAST_CAP',    4);      // reach of a lone blast: the mid rank
define('DHCA_BLAST_CAP_MAX',6);      // reach of a big chain: the back rank
define('DHCA_BLAST_SCALE',  0.45);
define('DHCA_BLAST_CHAIN',  0.40);   // extra damage per additional bomb
define('DHCA_MULTI_MIN',    6);
define('DHCA_MULTI_MEGA',   9);
define('DHCA_MULTI_BONUS',  1.30);
define('DHCA_MULTI_BIG',    1.90);
define('DHCA_CRIT_MULT',    1.6);

/* ---------- what a trait's ROLE is worth ------------------------------------
   Every piece a Fighter wears contributes something, decided by what the piece
   IS rather than by which hook it hangs on -- see dhcarena-roles.php. Six slots
   contribute (head, headgear, arms, both effects, companion); torso and weapon
   keep their existing jobs of setting base health and base power plus the gem's
   fighting style, and the background is the arena.

   Each contribution is scaled by the piece's own rarity tier and its
   deterministic variance, exactly as the base stats are, so two epics still
   differ and §4 still holds.

   CAPPED, because six slots pulling the same way is a real build and must not
   be an unanswerable one. Resistance especially: uncapped it turns a battle
   into a stalemate, which is a worse outcome than a Fighter being too strong. */
define('DHCA_ROLE_CRIT',    0.055);   // an optic, per piece
define('DHCA_ROLE_RESIST',  0.045);   // armour, per piece
define('DHCA_ROLE_POWER',   0.070);   // a weapon, per piece, on base power
define('DHCA_ROLE_CHARGE',  0.220);   // energy, per piece, on Charge gain
define('DHCA_ROLE_ASSIST',  0.110);   // a beast, per piece: chance of a extra hit
define('DHCA_ROLE_PLAINHP', 0.030);   // anything unplaced, per piece, on health
define('DHCA_RESIST_CAP',   0.34);
define('DHCA_CRIT_CAP',     0.45);
define('DHCA_ASSIST_CAP',   0.40);
define('DHCA_ASSIST_SHARE', 0.45);    // an assist hits for this much of a strike
define('DHCA_BOMB_CROSS',   1);
define('DHCA_BOMB_BOARD',   2);

/* The eight weapon kits. A Fighter's weapon decides what its gem DOES, which is
   the trait economy reaching into the puzzle. */
function dhca_kits() {
	return array(
		array('id'=>'heavy',  'emoji'=>'🔨','name'=>'Smash', 'dmg'=>1.55,'note'=>'one big hit'),
		array('id'=>'cleave', 'emoji'=>'🪓','name'=>'Cleave','dmg'=>0.80,'cleave'=>1,'note'=>'hits whole rank'),
		array('id'=>'drain',  'emoji'=>'🩸','name'=>'Drain', 'dmg'=>1.05,'drain'=>0.45,'note'=>'hits/steals health'),
		array('id'=>'sunder', 'emoji'=>'⛏️','name'=>'Break', 'dmg'=>0.95,'sunder'=>1,'note'=>'smashes shields'),
		array('id'=>'precise','emoji'=>'🎯','name'=>'Snipe', 'dmg'=>1.15,'crit'=>0.28,'note'=>'often hits harder'),
		array('id'=>'volley', 'emoji'=>'🏹','name'=>'Volley','dmg'=>0.62,'all'=>1,'note'=>'weak hit to all'),
		array('id'=>'brutal', 'emoji'=>'🗡️','name'=>'Bleed', 'dmg'=>1.30,'bleed'=>1,'note'=>'hurts for 3 rounds'),
		array('id'=>'quick',  'emoji'=>'⚔️','name'=>'Double','dmg'=>0.85,'echo'=>1,'note'=>'strikes twice'),
	);
}
function dhca_terrains() {
	return array(
		array('id'=>'crit',  'name'=>'Fractured Signal','note'=>'everyone lands big hits more often'),
		array('id'=>'dmg',   'name'=>'Overclocked',     'note'=>'everyone deals 10% more damage'),
		array('id'=>'guard', 'name'=>'Dense Cover',     'note'=>'🛡️ Shield gives 50% more'),
		array('id'=>'surge', 'name'=>'Low Gravity',     'note'=>'⚡ Charge builds twice as fast'),
		array('id'=>'frail', 'name'=>'Corrosive Haze',  'note'=>'everyone has 8% less health'),
	);
}

/* ---------- deterministic randomness ----------------------------------------
   A battle carries its own seed so the server can replay a resolution exactly.
   mulberry32, same generator the prototype used, so results are comparable. */
function dhca_rand(&$b) {
	$b['seed'] = ($b['seed'] + 0x6D2B79F5) & 0xFFFFFFFF;
	$t = $b['seed'];
	$t = (dhca_imul($t ^ ($t >> 15), 1 | $t)) & 0xFFFFFFFF;
	$t = ($t + dhca_imul($t ^ ($t >> 7), 61 | $t)) & 0xFFFFFFFF;
	$t = $t ^ ($t >> 14);
	return ($t & 0xFFFFFFFF) / 4294967296;
}
function dhca_imul($a, $b) {
	$a &= 0xFFFFFFFF; $b &= 0xFFFFFFFF;
	$ah = ($a >> 16) & 0xFFFF; $al = $a & 0xFFFF;
	$bh = ($b >> 16) & 0xFFFF; $bl = $b & 0xFFFF;
	return (($al * $bl) + ((($ah * $bl + $al * $bh) << 16))) & 0xFFFFFFFF;
}
function dhca_hash($s) {
	$h = 2166136261;
	for ($i = 0; $i < strlen($s); $i++) {
		$h ^= ord($s[$i]);
		$h = dhca_imul($h, 16777619);
	}
	return $h & 0xFFFFFFFF;
}

/* ---------- board ---------------------------------------------------------- */
function dhca_idx($r, $c) { return $r * DHCA_N + $c; }

/** Row and column runs of 3+, with intersecting runs merged into one match --
 *  an L, T or plus of five gems is a five-match, not two threes. */
function dhca_matches($board) {
	$N = DHCA_N; $raw = array();
	for ($r = 0; $r < $N; $r++) {
		$c = 0;
		while ($c < $N) {
			$run = 1;
			while ($c + $run < $N && $board[dhca_idx($r,$c+$run)] === $board[dhca_idx($r,$c)]
			       && $board[dhca_idx($r,$c)] !== -1) $run++;
			if ($run >= 3) {
				$g = array();
				for ($i = 0; $i < $run; $i++) $g[] = dhca_idx($r, $c+$i);
				$raw[] = array('cells'=>$g, 'type'=>$board[dhca_idx($r,$c)]);
			}
			$c += $run;
		}
	}
	for ($c = 0; $c < $N; $c++) {
		$r = 0;
		while ($r < $N) {
			$run = 1;
			while ($r + $run < $N && $board[dhca_idx($r+$run,$c)] === $board[dhca_idx($r,$c)]
			       && $board[dhca_idx($r,$c)] !== -1) $run++;
			if ($run >= 3) {
				$g = array();
				for ($i = 0; $i < $run; $i++) $g[] = dhca_idx($r+$i, $c);
				$raw[] = array('cells'=>$g, 'type'=>$board[dhca_idx($r,$c)]);
			}
			$r += $run;
		}
	}
	// flood runs that share a cell together; sharing a cell means sharing a colour
	$used = array_fill(0, count($raw), false);
	$out = array();
	for ($i = 0; $i < count($raw); $i++) {
		if ($used[$i]) continue;
		$used[$i] = true;
		$cells = array(); $type = $raw[$i]['type']; $stack = array($raw[$i]);
		while ($stack) {
			$g = array_pop($stack);
			foreach ($g['cells'] as $cix) $cells[$cix] = 1;
			for ($j = 0; $j < count($raw); $j++) {
				if ($used[$j] || $raw[$j]['type'] !== $type) continue;
				foreach ($raw[$j]['cells'] as $cix) {
					if (isset($cells[$cix])) { $used[$j] = true; $stack[] = $raw[$j]; break; }
				}
			}
		}
		$list = array_map('intval', array_keys($cells));
		$out[] = array('cells'=>$list, 'type'=>$type, 'len'=>count($list));
	}
	return $out;
}

/** The slide: a gem travels along its row or column, everything it passes
 *  shifts one step back. Matched to monstrocity.php slideTiles(). */
function dhca_slid($board, $a, $z) {
	if ($a === $z) return null;
	$N = DHCA_N;
	$ra = intdiv($a,$N); $ca = $a % $N; $rz = intdiv($z,$N); $cz = $z % $N;
	if ($ra !== $rz && $ca !== $cz) return null;
	$n = $board; $t = $board[$a];
	if ($ra === $rz) {
		if ($ca < $cz) { for ($x=$ca; $x<$cz; $x++) $n[dhca_idx($ra,$x)] = $board[dhca_idx($ra,$x+1)]; }
		else           { for ($x=$ca; $x>$cz; $x--) $n[dhca_idx($ra,$x)] = $board[dhca_idx($ra,$x-1)]; }
		$n[dhca_idx($ra,$cz)] = $t;
	} else {
		if ($ra < $rz) { for ($y=$ra; $y<$rz; $y++) $n[dhca_idx($y,$ca)] = $board[dhca_idx($y+1,$ca)]; }
		else           { for ($y=$ra; $y>$rz; $y--) $n[dhca_idx($y,$ca)] = $board[dhca_idx($y-1,$ca)]; }
		$n[dhca_idx($rz,$ca)] = $t;
	}
	return $n;
}

/** Every legal slide on the board, as [from, to] pairs. */
function dhca_all_slides() {
	static $cache = null;
	if ($cache !== null) return $cache;
	$N = DHCA_N; $out = array();
	for ($r = 0; $r < $N; $r++) for ($c = 0; $c < $N; $c++) {
		$a = dhca_idx($r,$c);
		for ($k = 0; $k < $N; $k++) { if ($k !== $c) $out[] = array($a, dhca_idx($r,$k)); }
		for ($k = 0; $k < $N; $k++) { if ($k !== $r) $out[] = array($a, dhca_idx($k,$c)); }
	}
	$cache = $out;
	return $out;
}

function dhca_has_move($b) {
	foreach (dhca_all_slides() as $mv) {
		$nb = dhca_slid($b['board'], $mv[0], $mv[1]);
		if ($nb && dhca_matches($nb)) return true;
	}
	return false;
}

function dhca_collapse(&$b) {
	$N = DHCA_N;
	for ($c = 0; $c < $N; $c++) {
		$col = array(); $bmb = array();
		for ($r = $N-1; $r >= 0; $r--) {
			$v = $b['board'][dhca_idx($r,$c)];
			if ($v !== -1) { $col[] = $v; $bmb[] = $b['bomb'][dhca_idx($r,$c)]; }
		}
		for ($r = $N-1, $k = 0; $r >= 0; $r--, $k++) {
			$at = dhca_idx($r,$c);
			$b['board'][$at] = $k < count($col) ? $col[$k] : (int)floor(dhca_rand($b) * DHCA_GEMS);
			$b['bomb'][$at]  = $k < count($bmb) ? $bmb[$k] : 0;
		}
	}
}

function dhca_fill_board(&$b) {
	$keepBombs = isset($b['bomb']) ? $b['bomb'] : null;
	$guard = 0;
	do {
		$b['board'] = array();
		for ($i = 0; $i < DHCA_N*DHCA_N; $i++) $b['board'][] = (int)floor(dhca_rand($b) * DHCA_GEMS);
		if ($keepBombs === null) { $b['bomb'] = array_fill(0, DHCA_N*DHCA_N, 0); }
	} while ((dhca_matches($b['board']) || !dhca_has_move($b)) && $guard++ < 400);
	if ($keepBombs !== null) $b['bomb'] = $keepBombs;   // a reshuffle keeps live bombs
}


/* ---------- Fighters ------------------------------------------------------- */

/** Everything a Fighter is, derived from its traits. Nothing is stored. */
function dhca_build_fighter($traits, $name, $uid, $rarity) {
	/*
	 * A FIGHTER IS ONLY REQUIRED TO HAVE A BACKGROUND, A TORSO AND A HEAD.
	 * DHCF_REQUIRED says so, and the assembler will happily save one with no
	 * weapon and no headgear -- so those two arrive missing, and every read of
	 * them below was an undefined-key warning. On a page that is merely bad; on
	 * ajax/dhcarena-action.php the warnings print ahead of the JSON and the
	 * reply cannot be parsed, which is the same failure that makes a battle
	 * look like it stalled.
	 *
	 * Absent is not the same as common, either. A Fighter with no weapon should
	 * not quietly score as though it had a common one, so an empty slug falls
	 * through to the baseline everywhere: no multiplier, no variance, and the
	 * first kit rather than a hash of nothing.
	 */
	foreach (array('background','torso','head','headgear','arms','weapon','effects','companion') as $slot)
		if (!isset($traits[$slot]) || $traits[$slot] === null) $traits[$slot] = '';

	$m = function($cat,$slug) use ($rarity) {
		if ($slug === '') return 1.0;
		static $mult = array('common'=>1.00,'uncommon'=>1.08,'epic'=>1.16,
		                     'legendary'=>1.24,'mythic'=>1.32);
		$t = isset($rarity[$cat][$slug][0]) ? $rarity[$cat][$slug][0] : 'common';
		return isset($mult[$t]) ? $mult[$t] : 1.0;
	};
	// §4: variance BETWEEN traits must be wider than the gap between tiers, or
	// rarity decides the fight. Deterministic per slug, so a trait is itself.
	$v = function($slug) {
		if ($slug === '') return 1.0;
		return 1 + ((dhca_hash($slug) % 1000)/1000 - 0.5) * DHCA_VARIANCE;
	};

	/* WHICH TRAIT DID WHAT. Carried on the Fighter so the battle screen can say
	   it out loud: only torso, weapon and headgear touch the numbers, and
	   background matters only for whichever Fighter is the defender's front
	   rank, because that one sets the arena. Arms, effects and companion are
	   pure art. Nothing in the UI should have to rediscover that by reading
	   this function. */
	$tier = function($cat, $slug) use ($rarity) {
		return isset($rarity[$cat][$slug][0]) ? $rarity[$cat][$slug][0] : 'common';
	};
	$tiers = array();
	foreach ($traits as $cat => $slug) if ($slug !== '') $tiers[$cat] = $tier($cat, $slug);

	/* EVERY PIECE PULLS ITS WEIGHT. Torso and weapon keep their jobs below; the
	   other six are read for what they are and add accordingly. */
	$roleAdd = array('crit'=>0.0, 'resist'=>0.0, 'power'=>0.0,
	                 'charge'=>0.0, 'assist'=>0.0, 'hp'=>0.0);
	$roleOf  = array();
	if (function_exists('dhca_trait_role')) {
		foreach (array('head','headgear','arms','effects','effects1','effects2','companion') as $slot) {
			if (empty($traits[$slot])) continue;
			$slug  = $traits[$slot];
			$cat   = ($slot === 'effects1' || $slot === 'effects2') ? 'effects' : $slot;
			$role  = dhca_trait_role($slug, $cat);
			$scale = $m($cat, $slug) * $v($slug);
			$roleOf[$slot] = $role;
			switch ($role) {
				case DHCA_ROLE_OPTIC:  $roleAdd['crit']   += DHCA_ROLE_CRIT    * $scale; break;
				case DHCA_ROLE_ARMOUR: $roleAdd['resist'] += DHCA_ROLE_RESIST  * $scale; break;
				case DHCA_ROLE_WEAPON: $roleAdd['power']  += DHCA_ROLE_POWER   * $scale; break;
				case DHCA_ROLE_ENERGY: $roleAdd['charge'] += DHCA_ROLE_CHARGE  * $scale; break;
				case DHCA_ROLE_BEAST:  $roleAdd['assist'] += DHCA_ROLE_ASSIST  * $scale; break;
				default:               $roleAdd['hp']     += DHCA_ROLE_PLAINHP * $scale; break;
			}
		}
	}

	$kits = dhca_kits();
	$hp = (int)round(DHCA_HP_BASE * $m('torso',$traits['torso']) * $v($traits['torso'])
	                 * (1 + $roleAdd['hp']));
	return array(
		'uid'=>$uid, 'name'=>$name, 'traits'=>$traits, 'tiers'=>$tiers,
		'kit'=>$kits[$traits['weapon'] === '' ? 0 : dhca_hash($traits['weapon']) % count($kits)],
		'maxHp'=>$hp, 'hp'=>$hp, 'shield'=>0, 'bleed'=>0, 'surge'=>0, 'ko'=>false,
		'power'=>(int)round(DHCA_POWER_BASE * $m('weapon',$traits['weapon']) * $v($traits['weapon'])
		                    * (1 + $roleAdd['power'])),
		'critC'=>min(DHCA_CRIT_CAP, 0.06 + $roleAdd['crit']),
		'resist'=>min(DHCA_RESIST_CAP, $roleAdd['resist']),
		'assist'=>min(DHCA_ASSIST_CAP, $roleAdd['assist']),
		'charge'=>1 + $roleAdd['charge'],
		'roles'=>$roleOf,
		'rank'=>0, 'side'=>'',
		// Damage this Fighter has dealt, all battle. Kept so the winning Crew
		// has a FIERCEST -- the one that actually did the work, rather than the
		// one with the best stat line, which is a different question.
		'dealt'=>0,
	);
}

function dhca_team(&$b, $side) { return $side === 'mine' ? $b['mine'] : $b['foes']; }
function dhca_alive($b, $side) {
	$out = array();
	foreach (($side === 'mine' ? $b['mine'] : $b['foes']) as $i => $f) if (!$f['ko']) $out[$i] = $f;
	return $out;
}
function dhca_fighter_for_gem($b, $side, $g) {
	foreach (($side === 'mine' ? $b['mine'] : $b['foes']) as $i => $f) if ($f['rank'] === $g) return $i;
	return -1;
}
function dhca_other($side) { return $side === 'mine' ? 'foes' : 'mine'; }

/* ---------- damage --------------------------------------------------------- */

function dhca_reach($len) { return $len >= 5 ? 2 : ($len === 4 ? 1 : 0); }

/** Indices into the target team that a match of this length can touch. */
function dhca_targets(&$b, $side, $depth) {
	$live = dhca_alive($b, $side);
	if (!$live) return array();
	$elig = array();
	foreach ($live as $i => $f) if ($f['rank'] <= $depth) $elig[] = $i;
	if ($elig) return $elig;
	// reach is a ceiling, not a requirement: if nothing shallow enough is left,
	// the frontmost survivor takes it
	$best = -1; $bestRank = 99;
	foreach ($live as $i => $f) if ($f['rank'] < $bestRank) { $bestRank = $f['rank']; $best = $i; }
	return $best >= 0 ? array($best) : array();
}

function dhca_hurt(&$b, $side, $i, $amt, $crit) {
	$t =& $b[$side][$i];
	/* ARMOUR FIRST, before the shield and before health. Resistance comes from
	   the armoured pieces a Fighter is wearing -- a helmet, plating, a deflektor
	   arm -- and it is a percentage rather than a flat subtraction so it stays
	   meaningful against a big hit instead of only blunting small ones. Capped
	   at DHCA_RESIST_CAP: six armour pieces is a real build and should be a
	   tough one, not an unkillable one. A hit never drops below 1. */
	if (!empty($t['resist'])) $amt = max(1, (int)round($amt * (1 - $t['resist'])));
	if ($t['shield'] > 0) {
		$a = min($t['shield'], $amt); $t['shield'] -= $a; $amt -= $a;
		if ($a > 0) $b['fx'][] = array('k'=>'shielded','side'=>$side,'i'=>$i,'v'=>$a);
	}
	if ($amt <= 0) return 0;
	$t['hp'] = max(0, $t['hp'] - $amt);
	$b['fx'][] = array('k'=>'hit','side'=>$side,'i'=>$i,'v'=>$amt,'crit'=>$crit?1:0);
	if ($t['hp'] === 0 && !$t['ko']) {
		$t['ko'] = true;
		$b['fx'][] = array('k'=>'ko','side'=>$side,'i'=>$i);
		$b['log'][] = '☠ '.$t['name'].' falls.';
	}
	return $amt;
}
function dhca_heal(&$b, $side, $i, $amt) {
	$f =& $b[$side][$i];
	$before = $f['hp']; $f['hp'] = min($f['maxHp'], $f['hp'] + $amt);
	if ($f['hp'] > $before) $b['fx'][] = array('k'=>'heal','side'=>$side,'i'=>$i,'v'=>$f['hp']-$before);
}

/** One matched group's whole effect, for the side that matched it. */
function dhca_resolve_group(&$b, $side, $grp, $chain, $scale) {
	$foe = dhca_other($side);
	$mult = (1 + ($chain - 1) * 0.35) * $scale;
	if ($b['terrain'] === 'dmg') $mult *= 1.10;

	if ($grp['type'] === 3) {                       // Shield
		$amt = (int)round((6 + $grp['len'] * 4) * $mult * ($b['terrain'] === 'guard' ? 1.5 : 1));
		foreach (dhca_alive($b, $side) as $i => $f) {
			$b[$side][$i]['shield'] += $amt;
			$b['fx'][] = array('k'=>'shield','side'=>$side,'i'=>$i,'v'=>$amt);
		}
		$b['log'][] = '🛡️ Shield x'.$grp['len'].' — +'.$amt.' to the whole Crew.';
		return;
	}
	if ($grp['type'] === 4) {                       // Charge
		$add = DHCA_SURGE_GAIN * ($b['terrain'] === 'surge' ? 2 : 1);
		$live = dhca_alive($b, $side);
		if (!$live) return;
		/* Each Fighter charges at its OWN rate now: energy pieces -- flames,
		   lava, sparks, an inferno limb -- build it faster, so a Fighter built
		   around them erupts before the rest of the Crew rather than everybody
		   arriving together. The Crew meter shows whoever is closest, which is
		   the one about to go off and therefore the one worth watching. */
		foreach ($live as $i => $f) {
			$rate = isset($f['charge']) ? $f['charge'] : 1;
			$b[$side][$i]['surge'] = min(DHCA_SURGE_MAX, $b[$side][$i]['surge'] + $add * $rate);
		}
		$liveNow = dhca_alive($b, $side); $any = reset($liveNow);
		$b['log'][] = '⚡ Charge x'.$grp['len'].' — now '.$any['surge'].'/'.DHCA_SURGE_MAX.'.';
		foreach (dhca_alive($b, $side) as $i => $f) {
			if ($f['surge'] < DHCA_SURGE_MAX) continue;
			$b[$side][$i]['surge'] = 0;
			$b['fx'][] = array('k'=>'erupt','side'=>$side,'i'=>$i);
			foreach (array_keys(dhca_alive($b, $foe)) as $ti)
				$b[$side][$i]['dealt'] += dhca_hurt($b, $foe, $ti, (int)round($f['power'] * 0.9 * $mult), false);
			$b['log'][] = '⚡ '.$f['name'].' ERUPTS — hits everything.';
		}
		return;
	}

	$fi = dhca_fighter_for_gem($b, $side, $grp['type']);
	if ($fi < 0) return;
	$f = $b[$side][$fi];
	if ($f['ko']) { $b['log'][] = 'Matched a gem whose Fighter is down — wasted.'; return; }

	$depth = dhca_reach($grp['len']);
	$ts = dhca_targets($b, $foe, $depth);
	if (!$ts) return;
	$k = $f['kit'];
	$b['fx'][] = array('k'=>'act','side'=>$side,'i'=>$fi);

	if (!empty($k['all']))         $list = array_keys(dhca_alive($b, $foe));
	elseif (!empty($k['cleave']))  $list = $ts;
	else                           $list = array(end($ts));

	$times = !empty($k['echo']) ? 2 : 1;
	for ($n = 0; $n < $times; $n++) {
		foreach ($list as $ti) {
			$tgt = $b[$foe][$ti];
			if ($tgt['ko']) continue;
			$critChance = $f['critC'] + (isset($k['crit']) ? $k['crit'] : 0)
			            + ($b['terrain'] === 'crit' ? 0.12 : 0);
			$crit = dhca_rand($b) < $critChance;
			$base = $f['power'] * $k['dmg'] * $mult * ($crit ? DHCA_CRIT_MULT : 1)
			      * (1 + ($grp['len'] - 3) * 0.30);
			if (!empty($k['sunder']) && $tgt['shield'] > 0) {
				$b[$foe][$ti]['shield'] = max(0, $b[$foe][$ti]['shield'] - (int)round($base * 0.5));
			}
			$dealt = dhca_hurt($b, $foe, $ti, max(1, (int)round($base)), $crit);
			$b[$side][$fi]['dealt'] += $dealt;
			/* THE THING FIGHTING BESIDE YOU JOINS IN. A companion creature, or
			   an arm that is itself alive, gets a chance to add a smaller second
			   hit on the same target -- which is what having one should feel
			   like, and it is the only role that does something the player can
			   SEE happen rather than quietly shifting a number. */
			if (!empty($f['assist']) && !$b[$foe][$ti]['ko'] && dhca_rand($b) < $f['assist']) {
				$extra = dhca_hurt($b, $foe, $ti,
					max(1, (int)round($base * DHCA_ASSIST_SHARE)), false);
				$b[$side][$fi]['dealt'] += $extra;
				$b['fx'][] = array('k'=>'assist','side'=>$side,'i'=>$fi,'t'=>$ti);
			}
			if (!empty($k['drain'])) dhca_heal($b, $side, $fi, (int)round($dealt * $k['drain']));
			if (!empty($k['bleed']) && !$b[$foe][$ti]['ko']) $b[$foe][$ti]['bleed'] = 3;
		}
	}
	$b['log'][] = $f['name'].' — '.$k['name'].' x'.$grp['len']
		. ($chain > 1 ? ' (chain '.$chain.')' : '')
		. ' → '.array('front','mid','back')[$depth].' rank';
}

function dhca_tick_bleeds(&$b, $side) {
	foreach (dhca_alive($b, $side) as $i => $f) {
		if ($f['bleed'] <= 0) continue;
		$b[$side][$i]['bleed']--;
		$d = (int)round($f['maxHp'] * 0.04);
		dhca_hurt($b, $side, $i, $d, false);
		$b['log'][] = $f['name'].' bleeds for '.$d.'.';
	}
}


/* ---------- resolving a whole move ----------------------------------------
   THE ONE ENTRY POINT. The client says "slide A to B"; everything else is
   decided here. Returns false if the move is illegal, which is the only answer
   a tampered request gets. */
function dhca_play(&$b, $side, $a, $z) {
	if ($b['over'] !== null) return false;
	if ($b['turn'] !== $side) return false;
	$nb = dhca_slid($b['board'], $a, $z);
	if (!$nb || !dhca_matches($nb)) return false;     // no match = not a move

	$b['fx'] = array(); $b['log'] = array();
	$b['fx'][] = array('k'=>'slide','side'=>$side,'a'=>$a,'z'=>$z);

	// the bomb layer travels with its gems
	$b['bomb'] = dhca_slid_bombs($b['bomb'], $a, $z);
	$b['board'] = $nb;
	$b['lastTo'] = $z;

	$best = 0;
	for ($chain = 1; $chain <= 40; $chain++) {
		$ms = dhca_matches($b['board']);
		if (!$ms) break;
		$best = max($best, dhca_longest($ms));
		if ($chain > $b['stats']['best']) $b['stats']['best'] = $chain;
		dhca_resolve_wave($b, $side, $ms, $chain);
		if ($b['over'] !== null) break;
		dhca_clear_and_drop($b, $ms);
	}

	// everything armed this move goes live for both sides
	for ($i = 0; $i < count($b['bomb']); $i++) if ($b['bomb'][$i] < 0) $b['bomb'][$i] = -$b['bomb'][$i];

	if ($b['over'] === null && !dhca_has_move($b)) {
		dhca_fill_board($b);
		$b['log'][] = 'No moves left — board reshuffled.';
		$b['fx'][] = array('k'=>'reshuffle');
	}

	dhca_check_over($b);
	if ($b['over'] !== null) { dhca_final_hurrah($b); return true; }

	// a five or more goes again; anything less hands the turn over
	if ($best >= DHCA_EXTRA_TURN) {
		$b['fx'][] = array('k'=>'again','len'=>$best);
		$b['log'][] = 'Match of '.$best.' — '.($side==='mine'?'you go':'they go').' again.';
		return true;
	}
	dhca_tick_bleeds($b, dhca_other($side));
	dhca_check_over($b);
	if ($b['over'] !== null) { dhca_final_hurrah($b); return true; }
	$b['turn'] = dhca_other($side);
	if ($b['turn'] === 'mine') $b['round']++;
	return true;
}

function dhca_longest($ms) { $n = 0; foreach ($ms as $g) $n = max($n, $g['len']); return $n; }

function dhca_slid_bombs($bomb, $a, $z) {
	$n = dhca_slid($bomb, $a, $z);
	return $n === null ? $bomb : $n;
}

/** One cascade step: detonations, the multi-match bonus, every group's effect,
 *  then any bombs this wave armed. */
function dhca_resolve_wave(&$b, $side, $ms, $chain) {
	$cleared = array();
	foreach ($ms as $g) foreach ($g['cells'] as $i) $cleared[$i] = 1;

	// The wave opens the beat for the client: these are the gems going, and this
	// is which link of the chain it is. Everything below appends to the same
	// timeline, so the browser can play the move back in the order it happened
	// without knowing a single rule.
	$b['fx'][] = array('k'=>'wave','chain'=>$chain,'cells'=>array_keys($cleared),
	                   'len'=>dhca_longest($ms),'groups'=>count($ms));

	// detonate live bombs caught in the clear, chaining through what they reach
	$extra = array(); $blasts = array(); $boom = 0;
	$queue = array();
	foreach (array_keys($cleared) as $i) if ($b['bomb'][$i] > 0) $queue[] = $i;
	while ($queue) {
		$at = array_shift($queue);
		$kind = $b['bomb'][$at];
		if ($kind <= 0) continue;
		$colour = $b['board'][$at];
		$b['bomb'][$at] = 0; $boom++; $b['stats']['blasts']++;
		$hit = ($kind === DHCA_BOMB_BOARD) ? range(0, DHCA_N*DHCA_N-1) : dhca_row_col($at);
		$own = 0;
		foreach ($hit as $j) {
			if ($b['bomb'][$j] > 0) $queue[] = $j;
			if ($b['board'][$j] === $colour) $own++;
			if (!isset($cleared[$j])) { $cleared[$j] = 1; $extra[$j] = 1; }
		}
		$blasts[] = array('colour'=>$colour,'kind'=>$kind,'own'=>$own);
	}

	// one slide making two matches pays, cascades do not -- Monstrocity's rule
	$multi = 1.0;
	if ($chain === 1 && count($ms) >= 2) {
		$tiles = 0; foreach ($ms as $g) $tiles += $g['len'];
		if ($tiles >= DHCA_MULTI_MEGA)   $multi = DHCA_MULTI_BIG;
		elseif ($tiles >= DHCA_MULTI_MIN) $multi = DHCA_MULTI_BONUS;
		if ($multi > 1) {
			$mega = ($multi === DHCA_MULTI_BIG);
			$b['fx'][] = array('k'=>'multi','mega'=>$mega?1:0,'tiles'=>$tiles,'groups'=>count($ms));
			$b['log'][] = ($mega?'✦✦ MEGA MULTI-MATCH':'✦ MULTI-MATCH').' — '.count($ms)
				.' matches, '.$tiles.' gems, +'.round(($multi-1)*100).'% damage.';
		}
	}

	foreach ($ms as $g) dhca_resolve_group($b, $side, $g, $chain, $multi);

	if ($boom) {
		$b['fx'][] = array('k'=>'boom','cells'=>array_keys($extra),
		                   'kind'=>max(array_map(function($x){return $x['kind'];}, $blasts)),
		                   'n'=>$boom);
		$b['log'][] = ($boom > 1 ? '💥 '.$boom.' BOMBS CHAIN — ' : 'Bomb — ')
		            . count($extra).' gems caught'
		            . ($boom > 2 ? ', reaching their back rank.' : '.');
		/* The chain is the thing being rewarded, not the individual bomb: the
		   whole detonation escalates with how many went off, and the reach
		   climbs with it -- two bombs still stop at the mid rank, three or more
		   carry to the back. */
		$chainMult = 1 + ($boom - 1) * DHCA_BLAST_CHAIN;
		$cap       = min(DHCA_BLAST_CAP_MAX, DHCA_BLAST_CAP + max(0, $boom - 2));
		foreach ($blasts as $bl) {
			$scale = DHCA_BLAST_SCALE * $chainMult * ($bl['kind'] === DHCA_BOMB_BOARD ? 1.6 : 1);
			dhca_resolve_group($b, $side,
				array('type'=>$bl['colour'],'len'=>min($cap, max(3,$bl['own'])),'cells'=>array()),
				$chain, $scale);
		}
	}

	// arm bombs from this wave's own matches; the bomb cell survives the clear
	foreach ($ms as $g) {
		if ($g['len'] < 4) continue;
		$big  = ($g['len'] >= 5);
		$kind = $big ? DHCA_BOMB_BOARD : DHCA_BOMB_CROSS;
		$upgrade = false;

		/*
		 * ONE BOMB PER CELL. The preferred site is the gem the player actually
		 * moved, then the middle of the run -- but if something is already
		 * sitting there the bomb must go somewhere else, because writing over
		 * it means two bombs earned and one bomb delivered, with the first
		 * disappearing silently: no blast, no log line, nothing.
		 *
		 * It happens across the waves of a cascade. lastTo does not change for
		 * the whole move, and the bomb's cell deliberately survives the clear,
		 * so the same cell can match again on a later wave and be chosen again.
		 * Protecting the cell from clearing made that MORE likely, not less.
		 */
		$want = array();
		if (isset($b['lastTo']) && in_array($b['lastTo'], $g['cells'], true)) $want[] = $b['lastTo'];
		$want[] = $g['cells'][intdiv(count($g['cells']), 2)];
		foreach ($g['cells'] as $c) $want[] = $c;
		$at = null;
		foreach ($want as $c) if (empty($b['bomb'][$c])) { $at = $c; break; }
		if ($at === null) {
			// every cell of this run already carries one. Nowhere to put a
			// second, so the one that is there is upgraded instead -- a bigger
			// bomb is a fair answer to a bigger match, and it is never nothing.
			$at   = $want[0];
			$kind = max($kind, abs($b['bomb'][$at]));
			$big  = ($kind === DHCA_BOMB_BOARD);
			$upgrade = true;
		}

		$b['bomb'][$at] = -$kind;                 // negative: not live yet
		// An upgrade is not a new bomb. Counting it as one overstates the total
		// and, worse, makes an audit of "armed minus detonated" look like bombs
		// are going missing when nothing has.
		if (empty($upgrade)) $b['stats']['bombs']++;
		unset($cleared[$at]);
		$b['fx'][] = array('k'=>'arm','at'=>$at,'big'=>$big?1:0,
		                   'up'=>empty($upgrade)?0:1);
		$b['log'][] = ($big?'💣 BOARD BOMB':'✛ Bomb').' armed — either side can set it off.';
	}
	/*
	 * A BOMB THAT IS NOT LIVE YET CANNOT BE CLEARED AWAY.
	 *
	 * Arming stores the bomb negative so it cannot go off inside its own move,
	 * and the arm loop above drops its cell from this wave's clear so the gem
	 * under it survives. That was only ever half the protection: a cascade is
	 * several waves, and from the SECOND wave onward the bomb's gem is an
	 * ordinary gem again. Match it -- or catch it in a blast -- and the cell
	 * clears, dhca_collapse() only carries a bomb along if its gem survived,
	 * and the bomb is gone. No detonation, no blast, nothing in the log: the
	 * player made a bomb, watched the cascade, and it simply was not there.
	 *
	 * Measured before this line existed: 63% of every bomb armed disappeared
	 * this way. Not an edge case -- the common case, because a four-match that
	 * arms a bomb is exactly the kind of match that starts a cascade.
	 *
	 * Live bombs are untouched by this. A positive bomb caught in a clear is
	 * SUPPOSED to go off, and does, higher up.
	 */
	foreach (array_keys($cleared) as $i) if ($b['bomb'][$i] < 0) unset($cleared[$i]);

	$b['_cleared'] = array_keys($cleared);
}

function dhca_row_col($i) {
	$N = DHCA_N; $r = intdiv($i,$N); $c = $i % $N; $out = array();
	for ($k = 0; $k < $N; $k++) { $out[] = dhca_idx($r,$k); if ($k !== $r) $out[] = dhca_idx($k,$c); }
	return $out;
}

function dhca_clear_and_drop(&$b, $ms) {
	foreach ($b['_cleared'] as $i) $b['board'][$i] = -1;
	dhca_collapse($b);
	$b['fx'][] = array('k'=>'drop','board'=>$b['board'],'bomb'=>$b['bomb']);
}

function dhca_check_over(&$b) {
	if ($b['over'] !== null) return;
	if (!dhca_alive($b,'foes')) $b['over'] = 'mine';
	elseif (!dhca_alive($b,'mine')) $b['over'] = 'foes';
}

/** Every bomb still on the board goes off once the battle is decided. Pure
 *  spectacle -- nothing is resolved against a Crew that has already lost. */
function dhca_final_hurrah(&$b) {
	$live = array();
	for ($i = 0; $i < count($b['bomb']); $i++) if ($b['bomb'][$i]) $live[] = $i;
	if (!$live) return;
	$cells = array(); $kind = 0;
	while ($live) {
		$at = array_shift($live);
		$k = abs($b['bomb'][$at]); if (!$k) continue;
		$b['bomb'][$at] = 0; $kind = max($kind, $k);
		foreach (($k === DHCA_BOMB_BOARD ? range(0, DHCA_N*DHCA_N-1) : dhca_row_col($at)) as $j) {
			if ($b['bomb'][$j]) $live[] = $j;
			$cells[$j] = 1;
		}
	}
	$b['fx'][] = array('k'=>'hurrah','cells'=>array_keys($cells),'kind'=>$kind);
	$b['log'][] = '💥 Last hurrah — every bomb still on the board goes off.';
}


/* ---------- starting a battle ---------------------------------------------- */

/**
 * @param array $mineTraits  three trait maps, front/mid/back
 * @param array $foeTraits   three trait maps
 * @param array $rarity      dhcrarity.php's table
 * @param int   $seed        anything; stored so the battle can be replayed
 */
function dhca_new_battle($mineTraits, $foeTraits, $names, $rarity, $seed) {
	$b = array('seed'=>$seed & 0x7FFFFFFF, 'turn'=>'mine', 'round'=>1, 'over'=>null,
	           'mine'=>array(), 'foes'=>array(), 'fx'=>array(), 'log'=>array(),
	           'stats'=>array('bombs'=>0,'blasts'=>0,'best'=>1), 'bomb'=>null);
	foreach (array('mine','foes') as $side) {
		$src = $side === 'mine' ? $mineTraits : $foeTraits;
		foreach ($src as $i => $t) {
			$f = dhca_build_fighter($t, $names[$side][$i], $side.$i, $rarity);
			$f['rank'] = $i; $f['side'] = $side;
			$b[$side][] = $f;
		}
	}
	// the DEFENDER's front-rank background is the arena
	$terr = dhca_terrains();
	$b['terrain'] = $terr[dhca_hash($foeTraits[0]['background']) % count($terr)]['id'];
	$b['terrainBg'] = $foeTraits[0]['background'];
	if ($b['terrain'] === 'frail') {
		foreach (array('mine','foes') as $side) foreach ($b[$side] as $i => $f) {
			$b[$side][$i]['maxHp'] = (int)round($f['maxHp'] * 0.92);
			$b[$side][$i]['hp'] = $b[$side][$i]['maxHp'];
		}
	}
	$b['bomb'] = null;
	dhca_fill_board($b);
	return $b;
}

/* ---------- the defending AI ------------------------------------------------
   Plays the same board by the same rules. Deliberately not optimal: slides give
   about 545 legal moves and a machine that always takes the best of them is a
   wall, not an opponent. It picks from its shortlist instead, so a player who
   spots the five-match is rewarded for spotting it. */
function dhca_ai_move(&$b) {
	$side = $b['turn'];
	$cand = array();
	foreach (dhca_all_slides() as $mv) {
		$sc = dhca_score_move($b, $side, $mv[0], $mv[1]);
		if ($sc > 0) $cand[] = array('a'=>$mv[0], 'z'=>$mv[1], 's'=>$sc);
	}
	if (!$cand) return null;
	usort($cand, function($x,$y){ return $y['s'] <=> $x['s']; });
	$pick = (dhca_rand($b) < 0.5) ? $cand[0]
	      : $cand[min(count($cand)-1, 1 + (int)floor(dhca_rand($b) * 4))];
	return array($pick['a'], $pick['z']);
}

function dhca_score_move($b, $side, $a, $z) {
	$nb = dhca_slid($b['board'], $a, $z);
	if (!$nb) return -1;
	$ms = dhca_matches($nb);
	if (!$ms) return -1;
	$sc = 0; $tiles = 0;
	foreach ($ms as $g) {
		$len = $g['len']; $tiles += $len;
		if ($g['type'] === 3)      $sc += $len * 4;
		elseif ($g['type'] === 4)  $sc += $len * 5;
		else {
			$fi = dhca_fighter_for_gem($b, $side, $g['type']);
			if ($fi < 0 || $b[$side][$fi]['ko']) { $sc -= 2; }
			else {
				$f = $b[$side][$fi];
				$sc += $len * 10 + $f['power'] * 0.4;
				foreach (dhca_targets($b, dhca_other($side), dhca_reach($len)) as $ti) {
					$t = $b[dhca_other($side)][$ti];
					if ($f['power'] * $f['kit']['dmg'] * (1 + ($len-3)*0.3) >= $t['hp']) $sc += 28;
				}
				if ($len >= DHCA_EXTRA_TURN) $sc += 14;
			}
		}
		foreach ($g['cells'] as $i) {
			if ($b['bomb'][$i] == DHCA_BOMB_BOARD)      $sc += 120;
			elseif ($b['bomb'][$i] == DHCA_BOMB_CROSS)  $sc += 45;
		}
		if ($len >= 5)      $sc += 30;
		elseif ($len === 4) $sc += 12;
	}
	if (count($ms) >= 2) {
		if ($tiles >= DHCA_MULTI_MEGA)   $sc += 40;
		elseif ($tiles >= DHCA_MULTI_MIN) $sc += 18;
	}
	return $sc;
}

/** What the client needs to draw, with nothing it could cheat with. */
function dhca_public(&$b) {
	$tn = ''; $tnote = '';
	foreach (dhca_terrains() as $t)
		if ($t['id'] === $b['terrain']) { $tn = $t['name']; $tnote = $t['note']; }
	$slim = function($f) {
		return array('uid'=>$f['uid'],'name'=>$f['name'],'traits'=>$f['traits'],
			'kit'=>array('id'=>$f['kit']['id'],'emoji'=>$f['kit']['emoji'],
			             'name'=>$f['kit']['name'],'note'=>$f['kit']['note']),
			'rank'=>$f['rank'],'hp'=>$f['hp'],'maxHp'=>$f['maxHp'],
			'shield'=>$f['shield'],'surge'=>round($f['surge'], 1),
			'bleed'=>$f['bleed'],'ko'=>$f['ko'],
			'dealt'=>isset($f['dealt']) ? (int)$f['dealt'] : 0,
			// for the Fighter card: what this one hits for, how often it lands
			// big, and which tier each of its traits came from
			'power'=>(int)$f['power'],
			'crit'=>round($f['critC'], 4),
			'resist'=>round(isset($f['resist']) ? $f['resist'] : 0, 4),
			'assist'=>round(isset($f['assist']) ? $f['assist'] : 0, 4),
			'charge'=>round(isset($f['charge']) ? $f['charge'] : 1, 3),
			'roles'=>isset($f['roles']) ? $f['roles'] : array(),
			'tiers'=>isset($f['tiers']) ? $f['tiers'] : array());
	};
	return array(
		'board'=>$b['board'], 'bomb'=>$b['bomb'],
		'mine'=>array_map($slim, $b['mine']), 'foes'=>array_map($slim, $b['foes']),
		'turn'=>$b['turn'], 'round'=>$b['round'], 'over'=>$b['over'],
		'terrain'=>$b['terrain'], 'terrainBg'=>$b['terrainBg'],
		'terrainName'=>$tn, 'terrainNote'=>$tnote,
		'stats'=>$b['stats'], 'fx'=>$b['fx'], 'log'=>$b['log'],
		// How many slides have been played. The client uses it to tell a battle
		// that has not started from one being picked back up -- the first gets
		// an entrance, the second must not.
		'moves'=>isset($b['meta']['moves']) ? count($b['meta']['moves']) : 0,
	);
}

}   // DHCA_N guard
