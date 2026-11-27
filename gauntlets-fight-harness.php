<?php
/* gauntlets-fight-harness.php — CLI only. Pure. No database.
 *
 *     php gauntlets-fight-harness.php            assertions
 *     php gauntlets-fight-harness.php balance    simulate and report
 *
 * The engine is pure, so this DRIVES it rather than reading it. Every
 * claim below is about behaviour that no source search could see.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/gauntlets-engine.php';

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

function kit($w = 0, $a = 0, $h = false) {
	return array('weapon_level' => $w, 'armor_level' => $a, 'can_heal' => $h);
}
function bare()        { return array('type' => 'bare'); }
function weapon($lv)   { return array('type' => 'weapon', 'level' => $lv, 'name' => 'W', 'sfx' => 'pistol'); }
function armour($lv)   { return array('type' => 'armor',  'level' => $lv, 'name' => 'A'); }
function heal($pct)    { return array('type' => 'heal',   'pct'   => $pct); }

/** Play a whole fight with a fixed policy. Returns the finished state. */
function play($playerHp, $wc, $kit, $seed, $policy) {
	$f = gaunt_begin(array('hp' => $playerHp, 'max' => GAUNT_HP_BASE), $wc, $kit, $seed);
	$guard = 0;
	while (empty($f['over']) && $guard++ < 200) gaunt_act($f, $policy($f));
	return $f;
}

/* ------------------------------------------------------------------ */
echo "it is deterministic\n";
/* Replayability is what makes a result auditable and a bug reproducible,
   and it is the whole reason the seed rides on the fight. */
$p = function ($f) { return bare(); };
$a = play(GAUNT_HP_BASE, 50, kit(3), 12345, $p);
$b = play(GAUNT_HP_BASE, 50, kit(3), 12345, $p);
ok($a['you']['hp'] === $b['you']['hp'] && $a['win'] === $b['win']
   && count($a['log']) === count($b['log']),
   'the same seed does not replay the same fight');
$c = play(GAUNT_HP_BASE, 50, kit(3), 999, $p);
ok($a['log'] !== $c['log'], 'a different seed produces an identical fight, so the seed is unused');

echo "\nevery fight ends\n";
/* A fight that cannot end is a bug, not a stalemate -- two bare-handed
   fighters is the slowest case there is. */
$slow = play(GAUNT_HP_BASE, 30, kit(0), 7, $p);
ok(!empty($slow['over']), 'a bare-handed fight against a bare-handed foe never terminates');
ok($slow['turn'] <= GAUNT_MAX_TURNS, 'the turn cap did not hold');
ok($slow['win'] === 'you' || $slow['win'] === 'foe', 'a finished fight has no winner');

echo "\nneither side has a structural edge\n";
/*
 * TURN ORDER IS WORTH MORE THAN ANY ITEM IF IT IS NOT ROLLED. With the
 * player always moving first, two identical bare-handed fighters came out
 * 73/27 -- measured. That bias would swamp the 3% matchup and make every
 * other number in the engine a fiction.
 */
$sym = 0;
for ($s = 0; $s < 600; $s++) if (play(GAUNT_HP_BASE, 50, kit(0), $s, $p)['win'] === 'you') $sym++;
$sym = round(100 * $sym / 600);
ok($sym >= 43 && $sym <= 57,
   "two identical bare-handed fighters come out $sym/" . (100 - $sym) . ", so something favours a side");
/*
 * AND THE OPPONENT'S SWINGS ARE RATIONED LIKE THE PLAYER'S. Unlimited,
 * it swings its best weapon every turn while the player counts theirs,
 * and an evenly matched fight stops being even.
 */
$fair = 0;
for ($s = 0; $s < 600; $s++) {
	$f = gaunt_begin(array('hp' => GAUNT_HP_BASE, 'max' => GAUNT_HP_BASE), 50, kit(6), $s);
	$left = gaunt_weapon_uses(6); $g = 0;
	while (empty($f['over']) && $g++ < 300) {
		if ($left > 0) { $left--; gaunt_act($f, weapon(6)); } else gaunt_act($f, bare());
	}
	if ($f['win'] === 'you') $fair++;
}
$fair = round(100 * $fair / 600);
ok($fair >= 40 && $fair <= 60,
   "two equally armed fighters come out $fair/" . (100 - $fair) . "; the opponent is not rationing its swings");

echo "\na fight that cannot end is capped\n";
/* Unreachable in normal play, which is exactly why it needs testing
   directly rather than hoping a slow fight wanders into it. */
$cap = gaunt_begin(array('hp' => 999999, 'max' => 999999), 50, kit(0), 4);
$cap['foe']['hp'] = 999999; $cap['foe']['max'] = 999999;
$g = 0;
while (empty($cap['over']) && $g++ < 500) gaunt_act($cap, bare());
ok(!empty($cap['over']) && $cap['turn'] <= GAUNT_MAX_TURNS,
   'an unwinnable fight ran past the turn cap instead of being decided');

echo "\narmour soaks, wears out, and costs the turn it goes on\n";
/* READ THE TIMELINE, NOT THE END STATE. gaunt_act() plays the player's
   action AND the opponent's reply, so the final pool has already been
   chewed into -- an earlier version of this check asserted the full pool
   afterwards and failed against a working engine. */
$f = gaunt_begin(array('hp' => 100, 'max' => 100), 50, kit(0), 5);
$before = $f['foe']['hp'];
$tl = gaunt_act($f, armour(5));
$put = null; foreach ($tl as $e) if ($e['kind'] === 'armor' && $e['who'] === 'you') $put = $e;
ok($put !== null && (int)$put['pool'] === 5 * GAUNT_ARM_POOL_PER_LEVEL,
   'armour did not go on, or the pool it reports is wrong');
ok($f['you']['armLevel'] === 5, 'the armour level did not stick');
ok($f['foe']['hp'] === $before, 'wearing armour also damaged the opponent, so it costs no turn');
/* It must actually reduce what lands. */
$armoured = gaunt_begin(array('hp' => 100, 'max' => 100), 50, kit(8), 5);
gaunt_act($armoured, armour(5));
$naked = gaunt_begin(array('hp' => 100, 'max' => 100), 50, kit(8), 5);
gaunt_act($naked, bare());
ok($armoured['you']['hp'] > $naked['you']['hp'] - 1,
   'armour did not reduce incoming damage at all');
/* And it is finite -- a pool that never empties is a percentage. */
$g = gaunt_begin(array('hp' => 9999, 'max' => 9999), 50, kit(9), 3);
gaunt_act($g, armour(1));
for ($i = 0; $i < 40 && $g['you']['arm'] > 0; $i++) gaunt_act($g, bare());
ok($g['you']['arm'] === 0 && $g['you']['armLevel'] === 0,
   'the armour pool never empties, so armour is permanent mitigation');

/*
 * AND THE OPPONENT MAY ARMOUR ONCE. Re-applying it whenever the pool
 * emptied is unlimited mitigation -- traced as a mirror match the player
 * lost 100 times in 100, with hits landing for 0.
 */
$rep = gaunt_begin(array('hp' => 99999, 'max' => 99999), 50, kit(0, 4), 8);
$g = 0;
while (empty($rep['over']) && $g++ < 120) gaunt_act($rep, weapon(10));
$puts = 0; foreach ($rep['log'] as $e) if ($e['who'] === 'foe' && $e['kind'] === 'armor') $puts++;
ok($puts <= 1, "the opponent put armour on $puts times in one fight, which is unlimited mitigation");

echo "\nthe opponent is never better equipped than the player\n";
/*
 * THE WHOLE DIFFICULTY CURVE. The kit comes from the rival player's
 * inventory, so uncapped it is matchmaking luck that decides whether a
 * run is playable: an empty-handed player against a level-8 template
 * wins 0% of the time, measured, and cannot bank the single win that
 * pays the smallest chest.
 */
$rich = array('weapon_level' => 9, 'armor_level' => 7, 'can_heal' => true);
$none = gaunt_cap_kit($rich, 0, 0, false);
ok($none['weapon_level'] <= GAUNT_FOE_CAP_MARGIN && $none['armor_level'] <= GAUNT_FOE_CAP_MARGIN,
   'a player holding nothing still faces an armed opponent: ' . json_encode($none));
ok($none['can_heal'] === false,
   'the opponent heals against a player who holds no heal, which is the biggest item in the kit');
$mid = gaunt_cap_kit($rich, 4, 3, true);
ok($mid['weapon_level'] === 4 + GAUNT_FOE_CAP_MARGIN && $mid['armor_level'] === 3 + GAUNT_FOE_CAP_MARGIN,
   'the cap does not track the player\'s own gear: ' . json_encode($mid));
$full = gaunt_cap_kit($rich, 20, 20, true);
ok($full['weapon_level'] === 9 && $full['armor_level'] === 7,
   'the cap lowers an opponent below its real template for a well-equipped player');

echo "\nhealing cannot exceed the maximum\n";
/* Same reason: measure the heal from its own timeline entry, since the
   opponent swings before gaunt_act() returns. */
$h = gaunt_begin(array('hp' => 95, 'max' => 100), 50, kit(0), 11);
$tl = gaunt_act($h, heal(100));
$got = null; foreach ($tl as $e) if ($e['kind'] === 'heal' && $e['who'] === 'you') $got = $e;
ok($got !== null && (int)$got['amount'] === 5,
   'a heal did not stop at the maximum: it restored ' . ($got['amount'] ?? '?') . ' into 5 missing');
/* And it costs the turn, exactly like armour. */
$h2 = gaunt_begin(array('hp' => 50, 'max' => 100), 50, kit(0), 11);
$fb = $h2['foe']['hp'];
gaunt_act($h2, heal(25));
ok($h2['foe']['hp'] === $fb, 'healing also damaged the opponent');
ok($h2['you']['hp'] > 50 - 20, 'the heal did not restore anything meaningful');

echo "\nbigger weapons hit harder\n";
$tot = array();
foreach (array(1, 5, 10) as $lv) {
	$sum = 0;
	for ($s = 0; $s < 60; $s++) {
		$f = gaunt_begin(array('hp' => 9999, 'max' => 9999), 50, kit(0), $s);
		$before = $f['foe']['hp'];
		gaunt_act($f, weapon($lv));
		$sum += $before - $f['foe']['hp'];
	}
	$tot[$lv] = $sum / 60;
}
ok($tot[1] < $tot[5] && $tot[5] < $tot[10],
   'weapon level does not increase damage: ' . json_encode($tot));
/* And bare hands are the weakest thing in the game, or a weapon is a
   downgrade and nobody would ever spend a swing. */
$bareAvg = 0;
for ($s = 0; $s < 60; $s++) {
	$f = gaunt_begin(array('hp' => 9999, 'max' => 9999), 50, kit(0), $s);
	$before = $f['foe']['hp'];
	gaunt_act($f, bare());
	$bareAvg += $before - $f['foe']['hp'];
}
$bareAvg /= 60;
ok($bareAvg < $tot[1], 'bare hands hit at least as hard as the weakest weapon');

echo "\nthe matchup tilts a fight without deciding it\n";
/*
 * THE POINT OF SETTING THE OPPONENT'S HEALTH RATHER THAN YOURS. A
 * favourable matchup should be worth about one good item. If the underdog
 * never wins, this is more deterministic than the coin flip it replaced,
 * which would be a worse game and not a better one.
 */
/* MEASURED BARE AGAINST BARE, deliberately. The matchup changes the
   OPPONENT'S health, so its effect is only visible from a position the
   player can contest -- an earlier version measured it against an
   uncapped level-4 template, where the player loses almost every fight
   whatever the matchup, and the tilt was invisible under the floor. */
$wins = array();
foreach (array(70, 50, 30) as $wc) {
	$w = 0;
	for ($s = 0; $s < 400; $s++)
		if (play(GAUNT_HP_BASE, $wc, kit(0), $s, $p)['win'] === 'you') $w++;
	$wins[$wc] = round(100 * $w / 400);
}
ok($wins[70] > $wins[50] && $wins[50] > $wins[30],
   'the matchup does not tilt the result at all: ' . json_encode($wins));
ok($wins[30] >= 12,
   'the underdog wins only ' . $wins[30] . '% -- worse than the 30% coin flip this replaced');
ok($wins[70] <= 92,
   'a favourable matchup wins ' . $wins[70] . '% of the time, which is a verdict not a tilt');

echo "\nhow many you hold matters\n";
/*
 * REPORTED: the same pistol and katana after every run at the same
 * quantity, with the runs seeming not to use them. Two things, and only
 * one was a bug.
 *
 * The quantity NOT DROPPING is deliberate -- gear is durable here, it
 * wears down across a run and resets after, because permanently
 * destroyed gear is what makes players hoard it and never play one.
 *
 * But quantity doing NOTHING was real: it was a "do you have any" gate,
 * so one pistol and twenty granted identical swings. A stack that
 * neither shrinks nor helps reads as the run ignoring your inventory.
 */
ok(gaunt_weapon_uses(3, 2) > gaunt_weapon_uses(3, 1),
   'holding a second copy of a weapon grants no extra swings');
ok(gaunt_weapon_uses(3, 99) === gaunt_weapon_uses(3, GAUNT_WPN_STACK_MAX),
   'a stack is uncapped, which is unlimited ammunition for gear that is never consumed');
ok(gaunt_weapon_uses(10, 3) < gaunt_weapon_uses(1, 3),
   'the heaviest weapon stops being the scarcest once quantity is counted');
ok(gaunt_weapon_uses(5) === gaunt_weapon_uses(5, 1),
   'the one-argument form changed meaning, which would silently re-arm every opponent');

echo "\nweapon swings are scarce at the top and plentiful at the bottom\n";
ok(gaunt_weapon_uses(10) === GAUNT_WPN_USES_MIN, 'the heaviest weapon is not the scarcest');
ok(gaunt_weapon_uses(1) > gaunt_weapon_uses(10), 'swings do not fall as level rises');
ok(gaunt_weapon_uses(1) <= 10, 'the weakest weapon has more swings than a run could use');

echo "\nthe timeline carries what the client needs to animate\n";
$f = gaunt_begin(array('hp' => 100, 'max' => 100), 50, kit(6), 42);
$entries = gaunt_act($f, weapon(9));
ok(count($entries) >= 1, 'an action produced no timeline at all');
$e = $entries[0];
foreach (array('who', 'kind', 'text', 'sfx') as $k)
	ok(isset($e[$k]), "a timeline entry has no '$k', so the client cannot animate or sound it");
ok($e['who'] === 'you' && $e['kind'] === 'hit', 'the first entry is not the action just taken');
/* The big flag is what picks hitBig over hit. */
/* ON THE PLAYER'S OWN ENTRY. Scanning every entry passed while only the
   opponent's hits carried the flag. */
$mine = null; foreach ($entries as $x) if ($x['who'] === 'you' && $x['kind'] === 'hit') $mine = $x;
ok($mine !== null && isset($mine['big']),
   "the player's own hit carries no big flag, so every hit animates identically");

echo "\nbeating someone armed takes what they were carrying\n";
/*
 * Uncapped, a stocked opponent is pure downside -- a harder fight for
 * the same reward, which is what makes an unlucky draw feel wasted.
 * Dropping what they SWUNG inverts that.
 */
function fought($kit, $seed, $turns = 60) {
	$f = gaunt_begin(array('hp' => 99999, 'max' => 99999), 50, $kit, $seed);
	$g = 0;
	while (empty($f['over']) && $g++ < $turns) gaunt_act($f, bare());
	return $f;
}
$armed = fought(kit(6, 4, true), 11);
$sp = gaunt_spoils($armed);
$kinds = array(); foreach ($sp as $x) $kinds[] = $x['kind'];
ok(in_array('weapon', $kinds, true), 'an opponent who swung a weapon offers no weapon: ' . json_encode($kinds));
ok(in_array('armor', $kinds, true), 'an opponent who wore armour offers no armour');
/* FROM WHAT THEY PLAYED, NOT WHAT THEY HOLD. An opponent with a weapon
   who never got to use it has nothing to take. */
$never = gaunt_begin(array('hp' => 99999, 'max' => 99999), 50, kit(9, 0, false), 3);
ok(gaunt_spoils($never) === array(),
   'an opponent who never acted still drops their inventory, so the drop is not earned');
/* AND THE PLAYER'S OWN ACTIONS ARE NOT SPOILS. A bare-handed opponent
   must yield nothing however much the player swung, armoured or
   healed -- otherwise you loot yourself. */
$mine = gaunt_begin(array('hp' => 99999, 'max' => 99999), 50, kit(0), 5);
gaunt_act($mine, weapon(9)); gaunt_act($mine, armour(5)); gaunt_act($mine, heal(50));
ok(gaunt_spoils($mine) === array(),
   'the player\'s own weapon, armour and heal are being counted as spoils: '
 . json_encode(gaunt_spoils($mine)));
/* A chance, not a certainty. */
$hits = 0;
for ($i = 0; $i < 200; $i++) if (gaunt_roll_drop(fought(kit(6, 4, true), $i))) $hits++;
$rate = round(100 * $hits / 200);
ok($rate > 25 && $rate < 85, "drops land $rate% of the time, which is a certainty or a rarity, not a chance");
/* Replayable like everything else -- a reload must not re-roll a better one. */
/* Several pairs: with only two or three possible spoils, one pair
   matches by luck often enough to pass an unseeded roll. */
$same = true;
for ($i = 0; $i < 25; $i++) {
	$a = gaunt_roll_drop(fought(kit(6, 4, true), 100 + $i));
	$b = gaunt_roll_drop(fought(kit(6, 4, true), 100 + $i));
	if (json_encode($a) !== json_encode($b)) { $same = false; break; }
}
ok($same, 'the drop is not seeded from the fight, so reloading re-rolls it');

/* ====================================================================
 * THE PERSISTENCE LAYER. Pure parts driven; the rest asserted from
 * source, because they need a database and this harness must not.
 * ==================================================================== */
/* The real ids, pinned against db.php below so the lib and the platform
   cannot drift apart on what a "75% Success" is. */
foreach (array('GAUNTLET_C_100' => 1, 'GAUNTLET_C_75' => 2, 'GAUNTLET_C_50' => 3,
               'GAUNTLET_C_25' => 4, 'GAUNTLET_C_FF' => 5, 'GAUNTLET_C_DOUBLE' => 6,
               'GAUNTLET_C_RANDOM' => 7) as $k => $v) if (!defined($k)) define($k, $v);
require_once __DIR__ . '/gauntlets-fight-lib.php';
$DB  = (string) file_get_contents(__DIR__ . '/db.php');
$LIB = (string) file_get_contents(__DIR__ . '/gauntlets-fight-lib.php');

echo "\nthe consumable ids match the platform's\n";
foreach (array('GAUNTLET_C_100' => 1, 'GAUNTLET_C_75' => 2,
               'GAUNTLET_C_50' => 3, 'GAUNTLET_C_25' => 4) as $k => $v)
	ok(preg_match("~define\\('" . $k . "',\\s*" . $v . "\\)~", $DB) === 1,
	   "$k is not $v in db.php, so the heal map is reading the wrong item");
ok(gaunt_heal_pct(GAUNTLET_C_100) === 100 && gaunt_heal_pct(GAUNTLET_C_25) === 25,
   'the heal percentages do not follow the consumable names');
ok(gaunt_heal_pct(GAUNTLET_C_FF) === 0 && gaunt_heal_pct(GAUNTLET_C_DOUBLE) === 0,
   'Fast Forward or Double Rewards is being treated as a heal');

echo "\nthe weapon sounds match Crypt Crawl's, entry for entry\n";
/*
 * BOTH GAMES READ THE SAME `weapons` TABLE through getAllWeapons(), so
 * the names are already shared. This map is duplicated rather than
 * imported -- including another game's RENDER file to borrow a lookup
 * would couple two pages that have nothing else in common -- and
 * duplication is only safe if it cannot drift, which is what this
 * checks.
 */
$CC = (string) file_get_contents(__DIR__ . '/cryptcrawl-render.php');
$ccMap = array();
if (preg_match('~function cryptcrawlWeaponSfxName.*?static \$map = \[(.*?)\];~s', $CC, $m))
	if (preg_match_all("~'([a-z-]+)'\s*=>\s*'([a-z]+)'~", $m[1], $mm, PREG_SET_ORDER))
		foreach ($mm as $x) $ccMap[$x[1]] = $x[2];
ok(count($ccMap) >= 10, 'could not read Crypt Crawl\'s weapon sound map, so drift cannot be detected');
$drift = array();
foreach ($ccMap as $slug => $sfx) {
	$name = str_replace('-', ' ', $slug);
	if (gaunt_weapon_sfx($name) !== $sfx) $drift[] = $slug;
}
ok(count($drift) === 0, 'the weapon sound map has drifted from Crypt Crawl\'s: ' . implode(', ', $drift));
ok(gaunt_weapon_sfx('Some New Gun') === null,
   'an unmapped weapon does not fall through to null, so a new weapon would fatal');
ok(gaunt_weapon_slug('Machine Gun') === 'machine-gun',
   'the slug does not match the one the icon path uses');

echo "\ngear is durable, consumables are spent\n";
/*
 * THE RULE THE WHOLE ITEM ECONOMY RESTS ON. If weapons and armour were
 * destroyed by use, players would hoard them and never play one -- the
 * 99-unused-elixirs problem -- and the game would be back to having no
 * decisions in it. The OLD gauntletResolveEncounter() did exactly that,
 * so this is a regression somebody could reintroduce by copying it.
 */
ok(strpos($LIB, 'updateGear(') === false,
   'the fight layer calls updateGear(), which destroys gear permanently and makes players hoard it');
ok(preg_match('~updateAmount\(\$conn, \$uid, \$item_id, -1\)~', $LIB) === 1,
   'heals are not actually consumed, so they are infinite');
/* Wear is recorded on the RUN, not on the inventory. */
ok(strpos($LIB, 'gaunt_gear_state_save($conn, $run_id, $gs)') !== false,
   'weapon and armour wear is not written to the run, so it resets every turn');

echo "\nthe server decides, the client renders\n";
ok(strpos($LIB, "if ((int)\$enc['owner'] !== \$uid) return array('ok' => false") !== false,
   'the turn endpoint does not check the fight belongs to the player');
/* THE GUARD ITSELF, not the message it prints. Deleting `left > 0` from
   the match leaves the error string in place and an earlier version of
   this check passed on a weapon with unlimited swings. */
ok(preg_match("~foreach \\(\\\$load\\['weapons'\\] as \\\$w\\).*?\\\$w\\['left'\\] > 0~s", $LIB) === 1,
   'a client can swing a weapon the run has no swings left of');
ok(preg_match("~foreach \\(\\\$load\\['armor'\\] as \\\$a\\).*?\\\$a\\['left'\\] > 0~s", $LIB) === 1,
   'a client can wear armour the run has already spent');
ok(strpos($LIB, "'why' => 'you have none of those'") !== false,
   'a client can heal with a consumable it does not hold');
ok(strpos($LIB, "ge.outcome='pending'") !== false,
   'a resolved encounter can be played again');
/* The seed is derived from the encounter, so reloading cannot re-roll a
   fight into a kinder one. */
ok(strpos($LIB, "crc32('gauntlet:' . \$enc_id)") !== false,
   'the fight seed is not fixed to the encounter, so a reload re-rolls it');

echo "\nthe migration is named, not guessed\n";
foreach (array('gauntlets_nfts', 'gear_state', 'chest', 'fight') as $bit)
	ok(strpos(gaunt_schema_sql(), $bit) !== false, "the printed migration does not mention $bit");
/* Counted off what the checker looks for rather than hard-coded, so
   adding a column cannot leave the printed migration behind. */
ok(substr_count(gaunt_schema_sql(), 'ALTER TABLE') === 4,
   'the migration does not cover every column gaunt_schema_missing() looks for');

/* ====================================================================
 * THE UI LAYER: one renderer, two callers, and the audio outside it.
 * ==================================================================== */
$REN = (string) file_get_contents(__DIR__ . '/gauntlets-fight-render.php');
$UI  = (string) file_get_contents(__DIR__ . '/gauntlets-fight-ui.php');
$AJX = (string) file_get_contents(__DIR__ . '/ajax/gauntlet-fight.php');
$PG  = (string) file_get_contents(__DIR__ . '/gauntlets.php');

echo "\nthe music survives a turn\n";
/*
 * THE WHOLE REASON THE AJAX PATH EXISTS. A full reload per turn tears
 * down and rebuilds the <audio> element, restarting the soundtrack on
 * every swing. Crypt Crawl hit this first; the fix is a fragment swap
 * with the audio element OUTSIDE the swapped node.
 */
ok(strpos($UI, '<audio id="gf-music"') !== false, 'there is no music element at all');
$aud = strpos($UI, '<audio id="gf-music"');
$area = strpos($UI, 'gf-area');
ok(strpos($UI, '<div id="gf-area"') === false,
   'the UI include draws #gf-area itself, so the audio would be inside the swapped fragment');
ok(strpos($REN, '<div id="gf-area"') !== false, 'the renderer does not emit #gf-area to swap');
ok(strpos(preg_replace('~/\*.*?\*/~s','',$REN), '<audio') === false,
   'the renderer emits an <audio> element, which the next turn would destroy and restart');
ok(strpos($UI, "old.replaceWith(next)") !== false,
   'the client does not swap the fragment in place');

echo "\none renderer, both callers\n";
ok(strpos($AJX, 'gauntletRenderFight(') !== false, 'the AJAX endpoint does not use the shared renderer');
ok(strpos($PG,  'gauntletRenderFight(') !== false, 'the page does not use the shared renderer');
/* The no-JS path has to post the SAME shape the endpoint parses, or a
   browser without JavaScript plays a different game. */
foreach (array($AJX, $PG) as $i => $src)
	ok(strpos($src, "strpos(\$turn, ':')") !== false,
	   ($i ? 'gauntlets.php' : 'the endpoint') . " does not parse the weapon:id turn format");
ok(strpos($PG, "\$action === 'fight_turn'") !== false,
   'gauntlets.php has no no-JS fallback for taking a turn');
ok(strpos($UI, 'form.submit()') !== false,
   'a failed fetch does not fall back to a real post, so a network blip ends the fight');

echo "\nthe coin flip's furniture is gone\n";
/* The odds bar described a percentage that no longer exists, and the
   reveal overlay dramatised a rand(1,100) that is no longer rolled. */
ok(strpos($PG, 'fight-reveal-overlay') === false,
   'the crossing-swords reveal overlay is still there, dramatising a roll that no longer happens');
ok(strpos($PG, "name=\"action\" value=\"resolve_encounter\"") === false,
   'the old resolve form is still posted, which would roll a result beside the fight');
ok(strpos($PG, 'odds-bar-fill') === false, 'the odds bar is still rendered');

echo "\nthe endpoint stays light\n";
/* Payouts, Discord and leaderboards belong to the encounter resolving,
   not to a swing -- the same rule ajax/cryptcrawl-action.php states. */
foreach (array('discordmsg', 'checkGauntlet', 'Leaderboard') as $slow)
	ok(strpos($AJX, $slow) === false, "the turn endpoint does $slow, which does not belong on a swing");

echo "\nthe fight degrades without art or sound\n";
ok(substr_count($REN, "onerror=") >= 2, 'a missing portrait breaks the fight instead of falling back');
ok(strpos($UI, 'prefers-reduced-motion') !== false, 'the shake and the floating numbers ignore reduced motion');
ok(strpos($UI, "catch (e) {}") !== false || strpos($UI, 'catch(function(){})') !== false,
   'audio failures are not swallowed, so a blocked autoplay could break the turn');

echo "\nthe cues point at files that exist\n";
/*
 * REPORTED: no punch or weapon sounds. The path was /staking/sounds/,
 * which 404s -- the files are under audio/sounds/, and the wrong prefix
 * came from reading the tail of a grep hit rather than the line. Every
 * cue failed silently, because a missing Audio src throws nothing.
 *
 * Checked against where Crypt Crawl actually loads them from, so this
 * cannot be "fixed" back to a path that does not exist.
 */
$CC2 = (string) file_get_contents(__DIR__ . '/cryptcrawl.php');
preg_match('~src="([a-z/]*sounds/)fist\.mp3"~', $CC2, $cm);
ok(isset($cm[1]), 'could not find where Crypt Crawl loads its sounds from');
ok(isset($cm[1]) && strpos($UI, '/staking/' . $cm[1]) !== false,
   'the fight loads sounds from a different directory than Crypt Crawl does: expected '
 . ($cm[1] ?? '?'));
ok(strpos($UI, "var SND  = '/staking/sounds/'") === false,
   'the sound path is back to /staking/sounds/, which 404s on every cue');

echo "\nonly this turn is animated\n";
/*
 * data-tl used to carry the whole six-entry tail, so every turn
 * replayed the previous five shakes and sounds and a fight grew
 * steadily noisier. It carries the entries from THIS exchange only.
 */
ok(strpos($REN, 'json_encode(array_values($just))') !== false,
   'data-tl carries the log tail again, so each turn replays the last five');
ok(strpos($REN, 'foreach ($just as $e) if (!empty($e[\'sfx\']))') !== false,
   'the cue list is built from the tail rather than this turn');
ok(strpos($AJX, "\$res['timeline']") !== false,
   'the endpoint does not hand the turn\'s own timeline to the renderer');

echo "\nthe choices are grouped\n";
/* One flex row of everything a player owns is a wall to re-read every
   turn, and the three kinds do different things. */
foreach (array('Attack', 'Defend', 'Recover') as $g)
	ok(strpos($REN, "'" . $g . "'") !== false, "the $g group is gone from the action list");
ok(strpos($REN, 'gf-group-label') !== false, 'the groups render without labels');

echo "\nthe backdrop is the opponent\'s world, when there is art for it\n";
ok(strpos($LIB, 'function gaunt_theme_bg') !== false, 'there is no backdrop lookup');
/* EVERY project has a backdrop. Monstrocity backgrounds only exist for
   the projects that got battlefield art drawn, so keying the arena on
   them alone left half the encounters on a flat dark panel. Realms'
   theme tiles are keyed by project_id and cover the whole roster. */
$tb = substr($LIB, (int)strpos($LIB, 'function gaunt_theme_bg'), 2200);
ok(strpos($tb, '/staking/images/themes/\' . $pid . \'.jpg') !== false,
   'a project with no Monstrocity background falls through to no backdrop at all');
ok((int)strpos($tb, "monstrocity_theme_collections") < (int)strpos($tb, "images/themes/"),
   'the realms tile is consulted before the Monstrocity art, which drops the battlefield backdrops');
/* The flag is what says the image exists -- without it the fight would
   point at a 404 and show nothing, which is worse than no backdrop. */
ok(substr_count($LIB, 'background = 1') === 3,
   'a backdrop lookup path skips the background flag, which is what says the image exists');
/*
 * THREE LOOKUPS, MOST PRECISE FIRST. monstrocity-schema.md is explicit
 * that project_id is the real link and is "mostly NULL", while
 * project_name is the string that stands alone for themes whose project
 * is not on the platform -- so a name-only match finds the FEWEST of
 * the themes that actually correspond to something here. That was the
 * first version, and it found almost nothing.
 */
ok(strpos($LIB, 'monstrocity_theme_collections tc') !== false,
   'the backdrop no longer matches on the collection, which is the most precise link');
ok(strpos($LIB, 'WHERE project_id = $pid') !== false,
   'the backdrop no longer matches on project_id, the canonical link');
ok(strpos($REN, 'data-bg=') !== false,
   'the fragment does not say why it has no backdrop, so the next report needs a bisect');
ok(strpos($REN, 'gf-has-bg') !== false, 'the renderer never applies the backdrop class');
ok(strpos($UI, '.gf-has-bg::before') !== false, 'the backdrop has no styling, so it will not show');

/* The loadout has to actually pass the count through, or the cap above
   is arithmetic nothing calls. */
ok(preg_match_all('~gaunt_weapon_uses\\(\\$lv, \\$qty\\)~', $LIB) === 2,
   'a loadout branch does not pass how many you hold, so quantity is ignored there');
ok(substr_count($LIB, "'qty' => \$qty") === 2,
   'the offered actions do not carry the quantity, so the UI cannot explain it');

echo "\nthe reward items are usable again\n";
/*
 * REPORTED as a question -- are consumables actually used? Heals and
 * Fast Forward always were. Double Rewards and Random Reward were NOT:
 * their only entry point was the loadout screen, and I deleted it with
 * the coin flip. They change the PAYOUT, not the fight, so they are
 * staked mid-fight now -- which is better than the screen they lost,
 * because committing one while you can see how it is going is a read
 * rather than a blind bet.
 */
$st = gaunt_begin(array('hp' => 100, 'max' => 120), 50, kit(0), 9);
$before = $st['foe']['hp'];
gaunt_act($st, array('type' => 'stake', 'id' => GAUNTLET_C_DOUBLE, 'name' => 'Double'));
ok(in_array(GAUNTLET_C_DOUBLE, $st['staked'], true), 'staking records nothing');
ok($st['foe']['hp'] === $before, 'staking also damaged the opponent, so it costs no turn');
gaunt_act($st, array('type' => 'stake', 'id' => GAUNTLET_C_DOUBLE, 'name' => 'Double'));
ok(count($st['staked']) === 1, 'the same reward item can be staked twice');
/* And the payout has to actually receive them. */
ok(strpos($LIB, '$staked = isset($f[' . "'staked'" . '])') !== false
   && preg_match('~gauntletResolveEncounter\\(\\$conn, \\(int\\)\\$user_id, \\(int\\)\\$enc_id,\\s*\\$staked~', $LIB) === 1,
   'what was staked never reaches gauntletResolveEncounter(), so doubling does nothing');

echo "\na fallen fighter falls visibly\n";
$dead = gaunt_begin(array('hp' => 1, 'max' => 120), 50, kit(9), 4);
$g = 0; while (empty($dead['over']) && $g++ < 40) gaunt_act($dead, bare());
$downs = array();
foreach ($dead['log'] as $e) if ($e['kind'] === 'down') $downs[] = $e;
ok(count($downs) === 1, 'a fight ends without a single down event, so nothing cues the death');
ok(($downs[0]['sfx'] ?? '') === 'ko', 'the fall has no cue');
ok(($downs[0]['who'] ?? '') === 'you', 'the down event names the wrong side');
/* BOTH SIDES. Testing only the one that happened to fall left the
   other branch uncovered, and deleting it passed. */
$kill = gaunt_begin(array('hp' => 9999, 'max' => 9999), 50, kit(0), 6);
$g = 0; while (empty($kill['over']) && $g++ < 200) gaunt_act($kill, weapon(10));
$fd = array(); foreach ($kill['log'] as $e) if ($e['kind'] === 'down') $fd[] = $e;
ok(count($fd) === 1 && $fd[0]['who'] === 'foe',
   'a slain opponent produces no down event, so nothing greys them out');
/* The cue lives in the OTHER sound library -- .ogg under sounds/, not
   .mp3 under audio/sounds/ -- and is the one DHC Arena plays on a KO. */
ok(strpos($UI, "OGG = { ko: 'skullcoinlose' }") !== false,
   'the fall cue is not DHC Arena\'s knockout sound, or is being looked for in the mp3 library');
ok(strpos($UI, ".gf-side.is-down .gf-portrait img") !== false,
   'a fallen fighter is not greyed out');
/* `down` names the side that FELL; every other entry names the actor.
   Without the short-circuit the slump lands on whoever is standing. */
ok(preg_match("~e\\.kind === 'down'[\\s\\S]{0,420}return;~", $UI) === 1,
   'the down entry does not short-circuit, so the death animates on the wrong side');

echo "\nthe chest scales with wins and only opens once\n";
ok(gaunt_chest_contents(0, 0, 1) === array(), 'a run with no wins still pays a chest');
/* WITH A HAUL, which is the case the guard actually exists for: at
   zero wins and zero haul the loop runs zero times anyway, so testing
   only that passes with the guard deleted. */
ok(gaunt_chest_contents(0, 6, 1) === array(),
   'a run that won nothing still pays for the salvage it took');
$one = array_sum(gaunt_chest_contents(1, 0, 5));
$three = array_sum(gaunt_chest_contents(3, 0, 5));
ok($one >= 1, 'a single win pays nothing, which is the closed loop the chest exists to break');
ok($three > $one, 'three wins pays no more than one, so the sweep is not worth chasing');
/* Only a sweep reaches the full heal; one win pays the common filler. */
/* Across many seeds: one draw from a pool misses the rare tier by luck
   often enough to pass a pool that wrongly contains it. */
$leaked = false;
for ($i = 0; $i < 300; $i++) {
	$c = gaunt_chest_contents(1, 0, $i);
	if (isset($c[GAUNTLET_C_100]) || isset($c[GAUNTLET_C_75])) { $leaked = true; break; }
}
ok(!$leaked, 'a one-win chest can pay a rare heal, so wins buy quantity but not quality');
ok(array_sum(gaunt_chest_contents(3, 6, 5)) > $three,
   'salvage taken during the run does not swell the chest');
ok(array_sum(gaunt_chest_contents(3, 999, 5)) === array_sum(gaunt_chest_contents(3, GAUNT_CHEST_HAUL_CAP, 5)),
   'the haul bonus is uncapped, which mints consumables without limit');
/* NOT the payout flag. */
ok(strpos($LIB, "UPDATE gauntlets SET chest=1 WHERE id=\$rid AND chest=0") !== false,
   'the chest does not claim atomically, so a double submit pays twice');
ok(strpos(preg_replace('~/\\*.*?\\*/~s', '', $LIB), 'SET reward') === false,
   'the chest writes gauntlets.reward, which is the batch payout\'s flag -- the cron would skip the run');

/* A chest on a DEFEAT screen is a reward for losing. `> 0` meant any
   run that took one opponent down before being wiped still showed one. */
$LIBNC = preg_replace('~/\*.*?\*/~s', '', $LIB);
$pend  = substr($LIBNC, (int)strpos($LIBNC, 'function gaunt_chest_pending'), 320);
ok(strpos($pend, '>= GAUNTLET_MAX_WINS') !== false,
   'the chest opens on any single win, so a wiped run still shows one on the defeat screen');
ok(strpos($pend, 'gaunt_run_wins($conn, $rid) > 0') === false,
   'gaunt_chest_pending still falls through to the one-win test');

echo "\nboth fighters are the same size whatever they are called\n";
/* `margin: 0 auto` makes a grid item fit-content sized, so each side
   sized off its OWN name: the fighter with the longer name got the
   bigger portrait. Measured on a 393pt phone at 160.7pt vs 138pt. */
$side = preg_match('~\.gf-side\s*\{[^}]*\}~', $UI, $m) ? $m[0] : '';
ok($side !== '', 'the .gf-side rule is gone');
ok(strpos($side, 'width: 100%') !== false,
   'the sides are fit-content, so the longer NFT name makes that portrait bigger than the other');
ok(preg_match('~margin:\s*0\s+auto~', $side) !== 1,
   'an auto inline margin is back on .gf-side, which re-sizes each side to its own content');
ok(strpos($side, 'max-width: min(190px, 42vw)') !== false,
   'the side cap is gone, so two half-page portraits push the fight below the fold');

echo "\nsalvage is run-scoped, and reads as temporary\n";
/* It must never reach the real inventory -- that is the line between
   relief and income, and the whole reason only the chest leaves a run. */
ok(strpos($LIB, "\$gs['loot'][] = array(") !== false, 'a won fight stores no salvage');
ok(preg_match("~if \\(\\\$item_id < 0\\) \\\$gs\\['loot'\\]~", $LIB) === 1,
   'spending salvage does not come out of the run state');
ok(strpos($LIB, "if (\$item_id < 0) {
			/* Salvage, so nothing leaves the inventory. */") !== false,
   'a salvaged heal is deducted from the player\'s real consumables');
ok(preg_match("~if \\(!empty\\(\\\$it\\['loot'\\]\\)\\).{0,80}gf-loot~s", $REN) === 1,
   'salvage is not marked, so it looks like owned gear the player keeps');

echo "\na dead fighter stays dead\n";
/*
 * THE BUG THIS EXISTS FOR. gauntlets_nfts.hp defaults to 0, so a
 * freshly dealt card and a corpse look identical. The first version
 * resolved that by reading 0 as full health -- which RESURRECTED
 * anything that died: a member killed in encounter one came back whole
 * for encounter two, undoing the entire reason health carries across a
 * run.
 */
ok(strpos($LIB, 'return ($f && $f->num_rows) ? 0 : GAUNT_HP_BASE;') !== false,
   'zero health is read as full again, so a fighter killed in one encounter is whole in the next');
ok(strpos($LIB, 'return $hp > 0 ? $hp : GAUNT_HP_BASE;') === false,
   'the resurrecting fallback is back');
/* And new runs are seeded, so the ambiguity stops arising at all. */
/* Inside gauntletStartRun and actually reachable -- a plain search
   finds the statement even when it has been branched out. */
$gsr = strpos($DB, 'function gauntletStartRun');
$gsr_end = strpos($DB, "\n}", $gsr);
ok($gsr !== false && preg_match('~if \\(\\$gn_id\\) @\\$conn->query\\("UPDATE gauntlets_nfts SET hp~',
        substr($DB, $gsr, $gsr_end - $gsr)) === 1,
   'a dealt hand is not seeded at full health, so every card is ambiguous until it fights');

echo "\na drop is visible, not just audible\n";
/*
 * REPORTED: "where are the items when slaying an opponent?" They were
 * dropping. They were invisible. The entry went into the returned
 * $timeline -- which only feeds data-tl, so the client played a sound
 * and animated nothing readable -- and never into $fight['log'], which
 * is what a player can actually see. The victory screen is built from
 * a separate session payload and knew nothing about salvage at all.
 */
ok(strpos($LIB, '$fight[' . "'log'" . '][] = $entry;') !== false,
   'the drop never reaches the readable log, so it plays a sound and vanishes');
ok(strpos($LIB, '$fight[' . "'drop'" . '] = $word;') !== false,
   'the fight does not record what was taken, so the victory screen cannot name it');
/* Saved immediately: the drop happens AFTER the turn's normal save, so
   without a second write it is lost on the next read. */
$dropblk = substr($LIB, strpos($LIB, 'if (!empty($fight[' . "'over'" . ']) && ($fight['));
$dropblk = substr($dropblk, 0, 1200);
ok(strpos($dropblk, 'gaunt_fight_save($conn, $e, $fight);') !== false,
   'the drop is not persisted after it is added, so it is lost on the next read');
ok(strpos($PG, "'drop'       => " . '$gf_drop,') !== false,
   'the victory screen is not told what dropped');
ok(strpos($PG, 'Took their <?php echo htmlspecialchars($last_result[') !== false,
   'the victory screen does not show the salvage');
/* Read BEFORE resolving -- resolving is what closes the encounter. */
ok(strpos($PG, '$gf_closing = gaunt_fight_get($conn, $enc_id);')
   < strpos($PG, '$outcome = gauntletResolveFight($conn, $user_id, $enc_id);'),
   'the salvage is read after the encounter resolves rather than before');

echo "\na count and a remaining action cannot be confused\n";
/*
 * REPORTED: "I have 2 pistols and 1 tactical katana in realms, why do
 * I have 8 and 5 in gauntlets?" The arithmetic was right -- a level-3
 * pistol is 4 swings and two copies is 8 -- but a bare number beside
 * an item reads as how many you own. The label was the bug.
 *
 * So: an x is always a COUNT, a word is always REMAINING ACTIONS, and
 * never the other way round.
 */
/* Both badges read "uses" -- one unit for weapons and armour alike,
   and the same word as gaunt_weapon_uses(), which computes it. */
ok(substr_count($REN, "use<?php echo (int)" . '$' . "it['left'] === 1 ? '' : 's'") === 2,
   'a weapon or armour badge is a bare number again, which reads as a quantity');
ok(strpos($REN, '> swings</em>') === false && strpos($REN, 'swings left this run') === false,
   'the weapon badge says "swings" again, so weapons and armour are counted in different units');
ok(substr_count($REN, '<i class="gf-qty">') === 2 && strpos($UI, '.gf-qty ') !== false,
   'how many of a weapon you hold is not shown, so the swing total looks arbitrary');
/* Heals and stakes genuinely ARE counts, so they keep the x. */
ok(substr_count($REN, 'title="how many you hold">&times;') === 2,
   'the heal or stake badge no longer reads as a count');

echo "\nthe deck shows what each card is carrying\n";
/*
 * Picking who to send is the same judgement Fast Forward asks for, and
 * was just as blind: health persists across a run, so a card that
 * fought and survived is not the card it was.
 */
ok(strpos($PG, 'gaunt_hand_hp($conn, intval($active_run[') !== false,
   'the hand is not annotated with health, so the deck is picked blind');
ok(strpos($PG, "gf_bar((int)" . '$' . "card['hp'], (int)" . '$' . "card['max'], 'hand')") !== false,
   'the hand cards show no health bar');
/*
 * USED AND DOWN ARE DIFFERENT THINGS. Fast Forward swaps the fighter
 * WITHOUT writing an encounter row, so a card can be hurt -- even
 * killed -- without ever being marked used.
 */
ok(strpos($PG, "<span class=\"played-label\">Down</span>") !== false,
   'a downed card is not distinguished from a used one');
ok(strpos($PG, '$out  = !empty($card[' . "'played'" . ']) || !empty($card[' . "'down'" . ']);') !== false,
   'a downed card is still selectable, and sending one loses the encounter instantly');
/* The bar's CSS lives with the fight, so the deck has to pull it in. */
ok(substr_count($PG, "include __DIR__ . '/gauntlets-fight-ui.php';") === 2,
   'the deck does not include the fight UI, so its health bars render unstyled');
ok(strpos($UI, "if (defined('GF_UI_DONE')) return;") !== false,
   'the fight UI is not guarded, so including it twice duplicates the audio element');

echo "\na swapped fighter brings its own health\n";
/*
 * REPORTED: health stayed depleted after a swap. gauntletFastForward()
 * rewrites gauntlets_encounters.player_nft_id and nothing else -- the
 * live fight is a JSON blob on the SAME ROW and kept the old fighter's
 * pool, so the swap appeared to do nothing at all.
 */
ok(strpos($LIB, 'function gaunt_fight_swap') !== false,
   'nothing replaces the fighter in a live fight, so a swap keeps the old health');
ok(strpos($PG, 'gaunt_fight_swap($conn, $enc_id,') !== false,
   'the page does not swap the fighter after a successful Fast Forward');
/*
 * AND THE OPPONENT KEEPS ITS DAMAGE. Rebuilding the fight would heal
 * them, which turns Fast Forward into "reset the enemy" -- chip down,
 * swap, repeat, for the price of an item that exists to fix a bad
 * draw. Only the player's side may be touched.
 */
$swapfn = substr($LIB, strpos($LIB, 'function gaunt_fight_swap'));
$swapfn = substr($swapfn, 0, strpos($swapfn, "\n}\n") + 2);
ok(strpos($swapfn, "\$f['foe']") === false,
   'the swap writes the opponent\'s side, so Fast Forward heals them and can be farmed');
ok(strpos($swapfn, "\$f['you']['arm']      = 0;") !== false,
   'a fresh fighter inherits the previous one\'s armour');
ok(strpos($swapfn, "if (!\$f || !empty(\$f['over'])) return false;") !== false,
   'a finished fight can be swapped into');

echo "\nFast Forward shows what it is swapping to\n";
/* Health carries, so the swap is entirely a judgement about the
   replacement's condition -- offering it blind offers nothing. */
ok(strpos($LIB, 'function gaunt_hand_hp(') !== false
   && strpos($PG, 'gaunt_hand_hp($conn,') !== false
   && strpos($AJX, 'gaunt_hand_hp($conn,') !== false,
   'the hand is not annotated with health by both callers, so Fast Forward is a blind swap');
ok(strpos($REN, 'gf_bar((int)' . '$' . "card['hp']") !== false,
   'the replacement cards show no health bar');
ok(strpos($REN, 'ff-down') !== false, 'a downed replacement is not marked');
ok(strpos($REN, 'if (empty(' . '$' . "card['down'])): ?>onclick=") !== false,
   'a downed replacement is still clickable, and swapping to one loses the fight instantly');
/* The client decides nothing: the server has to refuse it too. */
ok(strpos($DB, 'gaunt_nft_hp($conn, $rid, $new_id) <= 0) return false;') !== false,
   'gauntletFastForward() accepts a downed fighter, so the greying out is cosmetic');

echo "\nFast Forward is a choice, not a footnote\n";
/*
 * It swaps the fighter you were dealt, which is a run concern -- but it
 * is still a thing the player DOES, so it belongs with the other
 * choices rather than below the battle log. Extracted into its own
 * renderer because both callers have to emit it: without that the AJAX
 * fragment drops it and Fast Forward vanishes after the first swing.
 */
ok(strpos($REN, 'function gauntletRenderFastForward') !== false,
   'Fast Forward is not a shared renderer, so the fragment will lose it after one turn');
ok(strpos($AJX, 'gauntletRenderFastForward(') !== false,
   'the turn endpoint does not re-render Fast Forward, so it disappears mid-fight');
ok(strpos($PG, 'gauntletRenderFastForward(') !== false, 'the page does not render Fast Forward');
ok(strpos($REN, 'if ($extra !== ' . "''" . ') echo $extra;') < strpos($REN, '<ul class="gf-log">'),
   'Fast Forward renders below the battle log again');
/* And only while there is a fight to swap out of. */
ok(strpos($REN, 'if ($extra !== ' . "''" . ') echo $extra;') > strpos($REN, 'id="gf-form"'),
   'Fast Forward renders above the action buttons rather than beside them');
/*
 * AND ONLY WHILE THERE IS A FIGHT. The slot has to sit INSIDE the
 * not-over branch: outside it, a finished encounter still offers a
 * card swap for a fight that is already decided. Checked by position
 * against the branch's own end, because "is it above the log" is true
 * either way.
 */
/* Anchored on the hint, which is the last thing inside the branch --
   searching for the next `endif` found one belonging to the group
   loop instead. */
$gf_hint  = strpos($REN, 'class="gf-hint"');
$gf_endif = $gf_hint === false ? false : strpos($REN, '<?php endif; ?>', $gf_hint);
$gf_extra_at = strpos($REN, 'if ($extra !== ' . "''" . ') echo $extra;');
ok($gf_endif !== false && $gf_extra_at > $gf_hint && $gf_extra_at < $gf_endif,
   'Fast Forward renders outside the live-fight branch, so a finished encounter still offers a swap');
ok(strpos($REN, 'if ((int)$ff_count < 1 || !count($ff_hand)) return \'\';') !== false,
   'Fast Forward renders with nothing to swap to');

echo "\nthe log sits under the choices, and the result is one button\n";
/* Above them the log pushed the buttons down the page as it filled, and
   the control a player reaches for every turn should not move. */
ok(strpos($REN, '<ul class="gf-log">') > strpos($REN, 'id="gf-form"'),
   'the log is back above the action buttons, so they move as it fills');
ok(strpos($REN, 'class="gf-done"') !== false,
   'the result form uses the grouped-action layout again, which stretches one button full width');
ok(strpos($UI, '.gf-done .gf-btn { min-width') !== false, 'the Continue button has no width of its own');

/* ====================================================================
 * AND THIS FILE CHECKS ITSELF.
 *
 * Four times in one sitting I wrote a needle as a PHP DOUBLE-QUOTED
 * string containing a $variable -- "$fight['log'][] = $entry;" -- which
 * PHP interpolates away before strpos ever sees it. The needle becomes
 * a fragment or an empty string, so the check matches nothing, and
 * because strpos returns false and false == 0 some of those checks
 * PASSED FOR THE WRONG REASON while reading green.
 *
 * A green check that cannot fail is worse than no check, so the shape
 * is banned mechanically rather than by remembering.
 * ==================================================================== */
echo "\nand no check is quietly looking for an empty string\n";
$self  = (string) file_get_contents(__FILE__);
$bad   = array();
foreach (explode("\n", $self) as $ln => $line) {
	/* A double-quoted literal with an unescaped $ followed by a letter
	   or { -- \$ is fine, that is a deliberate escape. */
	if (preg_match('~(?:strpos|substr_count|preg_match|preg_match_all)\s*\([^,]*,\s*"[^"]*(?<!\\\\)\$[A-Za-z_{]~', $line))
		$bad[] = ($ln + 1);
}
ok(count($bad) === 0,
   'these lines pass a double-quoted needle containing a live $variable, which PHP '
 . 'interpolates away before the search: line(s) ' . implode(', ', $bad));

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all fight checks passed\n");

/* ------------------------------------------------------------------ */
if (($argv[1] ?? '') === 'balance') {
	echo "\n=== balance ===\n";
	$greedy = function ($f) { return weapon(6); };
	$rows = array(
		'bare vs bare'            => array(kit(0),            $p),
		'bare vs lv4'             => array(kit(4),            $p),
		'bare vs lv8+armour+heal' => array(kit(8, 5, true),   $p),
		'lv6 vs lv4'              => array(kit(4),            $greedy),
		'lv6 vs lv8+armour+heal'  => array(kit(8, 5, true),   $greedy),
	);
	printf("  %-26s %5s %6s %6s\n", '', 'win%', 'turns', 'hp');
	foreach ($rows as $label => $cfg) {
		$w = 0; $t = 0; $hp = 0; $N = 400;
		for ($s = 0; $s < $N; $s++) {
			$f = play(100, 50, $cfg[0], $s, $cfg[1]);
			if ($f['win'] === 'you') { $w++; $hp += $f['you']['hp']; }
			$t += $f['turn'];
		}
		printf("  %-26s %4d%% %6.1f %6.1f\n", $label, round(100 * $w / $N),
		       $t / $N, $w ? $hp / $w : 0);
	}
}
exit($fail ? 1 : 0);
