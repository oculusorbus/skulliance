<?php
/* DRAFT ONLY -- not wired to anything. Models a trait-based armour/gear
   mapping for Digital Hell Citizens 2: Fighters against the real minted
   metadata in dhc-mint-traits.json, and compares it to what the game does
   today (a crc32 of the asset name, which means nothing). */
$ROOT = __DIR__;
require_once $ROOT . '/dropship/dropship-odds.php';
require_once $ROOT . '/dropship/dropship-grunts.php';
require_once $ROOT . '/dropship/dropship-squad-plan.php';

$d = json_decode(file_get_contents($ROOT . '/dhc-mint-traits.json'), true);
$T = $d['traits'];

/*
 * SCORED FROM THE NAMES, WEIGHTED BY RARITY.
 *
 * Three passes got here and each dropped an assumption:
 *
 *   1. Hand-sorted torso values into tiers -- every judgement was mine.
 *   2. Counted which trait SLOTS were filled -- no opinion, but a
 *      "Tracksuit" and a "Cyber 666 Knight Armor" counted the same.
 *   3. This: read the VALUE names, and weigh each by how rare it is.
 *
 * THE NAMES CARRY THE MEANING. "cyber-666-knight-armor", "deflektor",
 * "connected-protector-limbs" and "amplified-dreadnought" all say armour
 * without anybody deciding they do; "annihilation-belt" and
 * "mega-taser-cannon" say damage; "earth-recovery-system-interface" says
 * exactly what it is. Keywords over a value name beat a list of 24 torso
 * values, because the list stops working the moment a new trait is minted
 * and the keywords keep going.
 *
 * RARITY IS THE WEIGHT. A torso 21 fighters share says less about one of
 * them than a torso only one has. Scored as log2(1 + total/count), so a
 * 1-of-1 counts about 3.5x a common trait rather than 226x -- scarcity
 * should tilt a fighter, not decide them single-handedly.
 *
 * RANKED INTO QUARTILES, so the four tiers stay balanced whatever the
 * weights do. A weighting that is slightly wrong shuffles neighbours; it
 * cannot empty a tier, which is the failure that makes a mapping
 * unplayable.
 */
function dhc2Freq($T) {
	$f = array();
	foreach ($T as $tr) foreach ($tr as $c => $v) {
		if (!isset($f[$c][$v])) $f[$c][$v] = 0;
		$f[$c][$v]++;
	}
	return $f;
}
function dhc2Rarity($f, $c, $v, $total) {
	$n = isset($f[$c][$v]) ? $f[$c][$v] : 1;
	return log(1 + ($total / max(1, $n)), 2);
}

/* What a name says it is. Matched on the slug, so order inside the value
   does not matter and a new trait scores itself. */
$KW = array(
	'armour' => array('armor','armour','knight','shield','deflektor','protector','plate',
	                  'guard','centinel','sentinel','dreadnought','mech','exo','limb',
	                  'cyborg','robo','robot','amplified','crusher','reinforce'),
	'melee'  => array('axe','scythe','krusher','morning-star','blade','sword','claw',
	                  'perforator','crusher','sash','belt','flexx','arm'),
	'demo'   => array('blaster','cannon','taser','bomb','bio','spike','fire','lava',
	                  'thunder','destruction','attack','explos','annihilation','smoke'),
	'medkit' => array('recovery','bubble','heal','med','symbio','fren','companion',
	                  'interface','restor','nano'),
);
function dhc2Hits($value, $words) {
	$n = 0;
	foreach ($words as $w) if (strpos($value, $w) !== false) $n++;
	return $n;
}

/* Which slots can speak to armour at all, and how loudly. */
$SLOT_W = array('torso' => 3, 'headgear' => 2, 'arms' => 2, 'weapon' => 1,
                'companion' => 1, 'effects1' => 1, 'effects2' => 1,
                'background' => 0, 'head' => 0);

function armourScore($t, $SLOT_W, $KW, $f, $total) {
	$s = 0;
	foreach ($t as $c => $v) {
		$w = isset($SLOT_W[$c]) ? $SLOT_W[$c] : 0;
		if ($w === 0 || $v === '') continue;
		$r = dhc2Rarity($f, $c, $v, $total);
		/* Occupying the slot at all is worth something; a name that reads
		   as armour is worth more. */
		$s += $w * $r * (1 + dhc2Hits($v, $KW['armour']));
	}
	return $s;
}

function gearLean($t, $KW, $f, $total) {
	$s = array('Melee' => 0.0, 'Demolition' => 0.0, 'Medkit' => 0.0);
	$map = array('melee' => 'Melee', 'demo' => 'Demolition', 'medkit' => 'Medkit');
	foreach ($t as $c => $v) {
		if ($c === 'background' || $c === 'head' || $v === '') continue;
		$r = dhc2Rarity($f, $c, $v, $total);
		/* The slot itself leans before the name is read: a companion is
		   something keeping you alive, arms are hands-on. */
		if ($c === 'companion') $s['Medkit'] += 2 * $r;
		if ($c === 'arms')      $s['Melee']  += 1 * $r;
		foreach ($map as $k => $label) {
			$h = dhc2Hits($v, $KW[$k]);
			if ($h) $s[$label] += $h * $r;
		}
	}
	arsort($s);
	$top = key($s); $best = current($s);
	if ($best <= 0) return 'None';
	next($s);
	/* A near dead heat is no lean at all, and the fourth slot wants None. */
	if ($best > 0 && current($s) / $best > 0.85) return 'None';
	return $top;
}

function report($label, $roster) {
	$ac = array(); $gc = array();
	foreach ($roster as $r) { $ac[$r['armor']] = ($ac[$r['armor']] ?? 0)+1;
	                          $gc[$r['gear']]  = ($gc[$r['gear']] ?? 0)+1; }
	echo "$label\n  armour: ";
	foreach (dropshipArmorTiers() as $t) printf("%-7s%-5d", $t, $ac[$t] ?? 0);
	echo "\n  gear:   ";
	foreach (array('Medkit','Melee','Demolition','None') as $g) printf("%-11s%-5d", $g, $gc[$g] ?? 0);
	$p = dropshipSquadPlan($roster);
	echo "\n  -> " . $p['count'] . " complete squads from the whole collection";
	echo ", " . count($p['leftover']) . " left over";
	echo $p['need'] ? "; one more " . $p['need']['armor'] . "/" . $p['need']['gear'] . " would add another\n\n"
	                : "; nothing would add another\n\n";
}

/* Score everybody, then cut the ranking into four equal tiers. */
$freq = dhc2Freq($T); $total = count($T);
$scored = array(); $i = 0;
foreach ($T as $name => $tr) {
	$scored[] = array('id' => ++$i,
	                  'score' => armourScore($tr, $SLOT_W, $KW, $freq, $total),
	                  'gear'  => gearLean($tr, $KW, $freq, $total));
}
usort($scored, function ($a, $b) { return $a['score'] <=> $b['score']; });
$n = count($scored); $tiers = array('Base','Light','Medium','Heavy');
$prop = array();
foreach ($scored as $k => $row) {
	$row['armor'] = $tiers[min(3, intdiv($k * 4, $n))];
	$prop[] = $row;
}
report("PROPOSED -- real traits (" . count($prop) . " minted fighters)", $prop);

$today = array(); $i = 0;
$am = array("Base","Light","Medium","Heavy"); $ge = array("None","Melee","Demolition","Medkit");
foreach (array_keys($T) as $name) {
	$today[] = array('id' => ++$i, 'armor' => $am[crc32($name) % 4],
	                 'gear' => $ge[crc32('gear:'.$name) % 4]);
}
report("TODAY -- crc32 of the asset name, meaning nothing", $today);

/* A sample to eyeball against the art: the strongest, the weakest, and a
   few in between, with the traits that produced the call. */
echo "SAMPLE -- does the call match the picture?\n\n";
$names = array_keys($T);
$withName = array();
foreach ($prop as $row) $withName[] = $row + array('name' => $names[$row['id'] - 1]);
$pick = array_merge(array_slice($withName, -4), array_slice($withName, 110, 3), array_slice($withName, 0, 3));
foreach ($pick as $r) {
	$tr = $T[$r['name']];
	$bits = array();
	foreach (array('torso','headgear','arms','weapon','companion','effects1') as $c) {
		if (!empty($tr[$c])) $bits[] = substr($c,0,4) . '=' . $tr[$c];
	}
	printf("  %-10s %-7s %-11s  %s\n", $r['name'], $r['armor'], $r['gear'],
	       implode('  ', array_slice($bits, 0, 4)));
}
