<?php
/*
 * gauntlets-balance-sim.php -- what a matchup is actually worth.
 *
 * WHY IT EXISTS. The project weakness chain sets the OPPONENT's
 * starting health and nothing else: 116 / 120 / 124 against a flat
 * 120. Eight health between the best and worst card looks like
 * nothing -- less than one bare punch -- and reasoning from that is
 * how you talk yourself into widening it. Attrition does not work
 * that way. Every point of health is another exchange the other side
 * has to survive, so the edge compounds, and the measured answer is
 * 70% / 50% / 30% -- the same numbers the run history prints.
 *
 * BOTH SIDES ARE MIRRORED here on purpose: the player is given exactly
 * what the opponent's kit allows and spends it on the same triggers,
 * so the only thing left between them is the matchup. An unmirrored
 * player with a level-4 weapon and two heals wins ~100% of everything
 * and measures nothing.
 *
 * Run it before touching GAUNT_FOE_HP_GOOD or GAUNT_FOE_HP_BAD:
 *
 *   php gauntlets-balance-sim.php
 *   GAUNT_FOE_HP_GOOD=0.94 GAUNT_FOE_HP_BAD=1.06 php gauntlets-balance-sim.php
 *
 * Measured, so nobody has to take my word for it:
 *   0.97 / 1.03   70.1%  49.6%  30.2%   <- shipped
 *   0.94 / 1.06   81.8%  49.6%  19.1%
 *   0.88 / 1.12   96.7%  49.6%   3.6%   <- a foregone conclusion
 */
foreach (array('GAUNT_FOE_HP_GOOD','GAUNT_FOE_HP_BAD') as $k)
	if (getenv($k) !== false) define($k, (float)getenv($k));
require __DIR__ . '/gauntlets-engine.php';

function bare()      { return array('type' => 'bare'); }
function weapon($lv) { return array('type' => 'weapon', 'level' => $lv, 'name' => 'W', 'sfx' => 'pistol'); }
function armour($lv) { return array('type' => 'armor', 'level' => $lv, 'name' => 'A'); }
function heal($p)    { return array('type' => 'heal', 'pct' => $p); }

/* MIRRORED. The player is given exactly what the opponent's kit allows
   and spends it on the same triggers, so the ONLY thing left between
   them is the matchup. */
function runFight($wc, $seed, $lvW, $lvA, $canHeal) {
	$kit = array('weapon_level' => $lvW, 'armor_level' => $lvA,
	             'can_heal' => $canHeal, 'heal_cid' => 3, 'heal_pct' => GAUNT_FOE_HEAL_PCT);
	$f = gaunt_begin(array('hp' => GAUNT_HP_BASE, 'max' => GAUNT_HP_BASE), $wc, $kit, $seed);
	$sw = $lvW > 0 ? gaunt_weapon_uses($lvW) : 0;
	$arm = $lvA > 0 ? 1 : 0; $hl = $canHeal ? 1 : 0; $g = 0;
	while (empty($f['over']) && $g++ < 200) {
		if ($hl > 0 && $f['you']['hp'] < $f['you']['max'] * GAUNT_FOE_HEAL_AT) { $hl--; $a = heal(GAUNT_FOE_HEAL_PCT); }
		elseif ($arm > 0) { $arm--; $a = armour($lvA); }
		elseif ($sw > 0)  { $sw--;  $a = weapon($lvW); }
		else $a = bare();
		gaunt_act($f, $a);
	}
	return array(($f['win'] ?? '') === 'you', $g);
}
$N = 4000;
foreach (array(array(0,0,false,'bare mirror'), array(4,2,true,'geared mirror')) as $cfg) {
	list($lw,$la,$ch,$label) = $cfg;
	echo "\n$label\n";
	foreach (array(70 => 'strong', 50 => 'even', 30 => 'weak') as $wc => $name) {
		$w = 0; $t = 0;
		for ($i = 0; $i < $N; $i++) { list($won,$tt) = runFight($wc, $i*7919+$wc, $lw,$la,$ch); if ($won) $w++; $t += $tt; }
		printf("  %-7s win=%5.1f%%  turns=%.1f  foeHP=%d\n", $name, 100*$w/$N, $t/$N, gaunt_foe_hp($wc));
	}
}
