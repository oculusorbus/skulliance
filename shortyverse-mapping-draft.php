<?php
/* DRAFT ONLY -- wired to nothing. Models a stat mapping for SHORTY VERSE,
   the collection behind BOTH Dread City (project 2) and Filthy Mermaid
   (project 3), against the real mint metadata in
   shortyverse-mint-traits.json. Compares it to what those themes do today,
   which is a crc32 of the asset name. */
$ROOT = __DIR__;
require_once $ROOT . '/dropship/dropship-odds.php';
require_once $ROOT . '/dropship/dropship-grunts.php';
require_once $ROOT . '/dropship/dropship-squad-plan.php';

$d = json_decode(file_get_contents($ROOT . '/shortyverse-mint-traits.json'), true);
$T = $d['traits'];

/*
 * THIS COLLECTION HAS NO TRAITS, SO THE MAPPING READS WHAT IT DOES HAVE.
 *
 * Disco Solaris has an Outerwear trait; DHC2 has nine layers. Shorty Verse
 * has a name, a one-line summary, a description and nothing else -- every
 * token is total_supply 1, so even the edition size is not a field. It is
 * recovered from the asset names: TRUST001, TRUST002 and TRUST003 are
 * three editions of one character, and 81 characters exist exactly once.
 *
 * ARMOUR IS SCARCITY. A 1/1 is the rarest thing in the collection and a
 * 1/20 the commonest, which is a graded signal 161 characters deep and
 * the only one here that is not a matter of opinion. Ranked into quartiles
 * so the tiers stay balanced.
 *
 * GEAR IS WHAT THE SUMMARY SAYS. "DETECTIVE TRUST", "ANGEL POST FIGHT",
 * "MEDIC" -- the one-liners are short and literal, which makes keywords
 * reliable in a way they would not be over prose.
 */
$KW = array(
	'medkit' => array('MEDIC','ANGEL','SAINT','SACRED','NURSE','HEAL','GUARDIAN',
	                  'BLESS','SAVIOR','SAVIOUR','DOC','CARE'),
	'melee'  => array('FIGHT','BLOOD','MANIC','BANDIT','BRAWL','FIST',
	                  'KNUCKLE','BLADE','SLASH','RAGE','BEAST'),
	'demo'   => array('WARS','WAR','DEATH','BOMB','BLAST','DEMO','DESTROY','NUKE',
	                  'GUN','SHOT','SNIPER','ARTILLERY','AUTOMATED','PROTO'),
	/* Words that read as station or standing rather than kit -- they lift
	   the armour score instead of choosing a weapon. */
	'armour' => array('VIP','BOSS','KING','QUEEN','CHIEF','AGENT','OFFICER','COP',
	                  'ARMOR','ARMOUR','SHIELD','TANK','HEAVY','ELITE','PRIME'),
);
function svHits($text, $words) {
	$n = 0;
	foreach ($words as $w) if (strpos($text, $w) !== false) $n++;
	return $n;
}
function svText($r) {
	return strtoupper(($r['name'] ?? '') . ' ' . ($r['summary'] ?? '') . ' ' . ($r['base'] ?? ''));
}

/* Scarcity weight: a 1/1 counts most, a 1/20 least, on the same log scale
   the DHC2 draft uses so the two models stay comparable. */
function svScarcity($edition, $maxEd) {
	return log(1 + ($maxEd / max(1, (int)$edition)), 2);
}

$maxEd = 1;
foreach ($T as $r) $maxEd = max($maxEd, (int)$r['edition']);

$scored = array(); $i = 0;
foreach ($T as $asset => $r) {
	$txt = svText($r);
	$score = svScarcity($r['edition'], $maxEd) * 3        /* scarcity dominates */
	       + svHits($txt, $KW['armour']) * 1.5;           /* station adds to it */
	/*
	 * ENGAGED MEANS ARMED -- IT JUST DOES NOT SAY WITH WHAT.
	 *
	 * It appears in 130 of 381 summaries, and the first draft read that
	 * frequency as noise and dropped it. Wrong: the word marks a fighter
	 * in action, typically wielding a weapon and sometimes an explosive.
	 * It is the strongest gear signal the collection has. What it is NOT
	 * is a choice between melee and demolition, and reading it as Melee
	 * put 128 soldiers in one bucket and capped the collection at 20
	 * complete squads.
	 *
	 * So it decides WHETHER there is a weapon, and the print number
	 * decides WHICH. Nothing in the metadata separates TRUST001 from
	 * TRUST003 -- same name, same image, same summary -- so the print is
	 * the only honest distinguisher between prints of one character, and
	 * it splits the two weapons evenly by construction.
	 *
	 * DISENGAGED IS CHECKED FIRST, because it contains ENGAGED as a
	 * substring and a plain strpos() would call every stood-down fighter
	 * armed.
	 */
	$print = (int)preg_replace('/\D/', '', substr($asset, strlen($r['base'])));
	if ($print < 1) $print = 1 + (crc32($asset) % 4);

	$disengaged = strpos($txt, 'DISENGAGED') !== false;
	$engaged    = !$disengaged && strpos($txt, 'ENGAGED') !== false;

	$heal = svHits($txt, $KW['medkit']);
	$mel  = svHits($txt, $KW['melee']);
	$dem  = svHits($txt, $KW['demo']);

	if ($heal > 0 && $heal >= $mel && $heal >= $dem) {
		$gear = 'Medkit';                    /* a medic is a medic, armed or not */
	} elseif ($engaged) {
		/* Armed. Which weapon is the print's to decide, unless the
		   summary is explicit about it. */
		if ($mel > $dem)      $gear = 'Melee';
		elseif ($dem > $mel)  $gear = 'Demolition';
		else                  $gear = ($print % 2) ? 'Melee' : 'Demolition';
	} elseif ($disengaged) {
		$gear = 'None';                      /* stood down, carrying nothing */
	} else {
		if ($mel > $dem && $mel > $heal)      $gear = 'Melee';
		elseif ($dem > $mel && $dem > $heal)  $gear = 'Demolition';
		else {
			$rot  = array('Melee','Demolition','Medkit','None');
			$gear = $rot[($print - 1) % 4];
		}
	}
	$scored[] = array('id' => ++$i, 'asset' => $asset, 'score' => $score, 'gear' => $gear);
}
usort($scored, function ($a, $b) { return $a['score'] <=> $b['score']; });
$tiers = array('Base','Light','Medium','Heavy'); $n = count($scored);
$prop = array();
foreach ($scored as $k => $row) { $row['armor'] = $tiers[min(3, intdiv($k * 4, $n))]; $prop[] = $row; }

function svReport($label, $roster) {
	$ac = array(); $gc = array();
	foreach ($roster as $r) { $ac[$r['armor']] = ($ac[$r['armor']] ?? 0)+1;
	                          $gc[$r['gear']]  = ($gc[$r['gear']] ?? 0)+1; }
	echo "$label\n  armour: ";
	foreach (dropshipArmorTiers() as $t) printf("%-7s%-5d", $t, $ac[$t] ?? 0);
	echo "\n  gear:   ";
	foreach (array('Medkit','Melee','Demolition','None') as $g) printf("%-11s%-5d", $g, $gc[$g] ?? 0);
	$p = dropshipSquadPlan($roster);
	echo "\n  -> " . $p['count'] . " complete squads from the whole collection, "
	   . count($p['leftover']) . " left over\n\n";
}
svReport("PROPOSED -- scarcity + summary (" . count($prop) . " assets)", $prop);

$today = array(); $i = 0;
$am = array("Base","Light","Medium","Heavy"); $ge = array("None","Melee","Demolition","Medkit");
foreach (array_keys($T) as $asset) {
	$today[] = array('id' => ++$i, 'armor' => $am[crc32($asset) % 4],
	                 'gear' => $ge[crc32('gear:'.$asset) % 4]);
}
svReport("TODAY -- crc32 of the asset name, meaning nothing", $today);

echo "SAMPLE -- scarcest first, then commonest\n\n";
$pick = array_merge(array_slice($prop, -5), array_slice($prop, 0, 5));
foreach ($pick as $r) {
	$t = $T[$r['asset']];
	printf("  %-18s 1/%-3d %-7s %-11s %s\n", $r['asset'], $t['edition'],
	       $r['armor'], $r['gear'], substr($t['summary'], 0, 34));
}
