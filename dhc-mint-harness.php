<?php
/**
 * dhc-mint-harness.php -- no rule may make a minted Fighter unbuildable.
 *
 * The platform has several rules that REFUSE a pairing: head/headgear, head/
 * companion, arms/weapon. Every one of them was judged by eye from the art,
 * and that is the right way to find them -- but the 226 minted DHC2 Fighters
 * are the collection's own answer to what goes together, and they outrank an
 * opinion. A rule that forbids a Fighter which demonstrably exists is wrong by
 * definition.
 *
 * This caught exactly that: beheaded-cyborg + trojan-detection-mask was on the
 * exclusion list, and DHC2F043 wears it. Reported by the owner, confirmed here
 * against the chain. (The art agrees once you look: the Trojan mask is a full
 * faceplate enclosing the neck stump, so unlike a helmet it needs no skull
 * under it.)
 *
 * dhc-mint-traits.json is a committed snapshot of the CIP-25 metadata under
 * the DHC2 policy, resolved to platform slugs with the same alias and
 * normalisation rules dhc/gen-rarity.py uses. It is derived from public chain
 * data, not from the art, so it is safe to track even though dhc/ is not.
 *
 * Usage: php dhc-mint-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/dhcfighters-config.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$snapFile = __DIR__ . '/dhc-mint-traits.json';
ok(is_file($snapFile), 'dhc-mint-traits.json is missing, so nothing below is checked');
if (!is_file($snapFile)) { echo "\nFAILED: 1 check(s)\n"; exit(1); }
$snap = json_decode(file_get_contents($snapFile), true);
ok(is_array($snap) && !empty($snap['traits']), 'the mint snapshot did not parse');
$mint = $snap['traits'];
printf("mint snapshot: %d fighters under %s...\n", count($mint), substr($snap['policy'], 0, 12));

/* The snapshot must stay the size of the mint. A truncated file would pass
   every exclusion check below by simply containing no counter-examples. */
ok(count($mint) === 226,
   'the snapshot holds ' . count($mint) . ' fighters, not the 226 that were minted -- '
 . 'a short file passes every check below for the wrong reason');

/** Minted Fighters wearing both of these, as a list of token names. */
function mint_wearing($mint, $slotA, $slugA, $slotB, $slugB) {
	$hit = array();
	foreach ($mint as $name => $t) {
		if (!isset($t[$slotA]) || $t[$slotA] !== $slugA) continue;
		if (!isset($t[$slotB]) || $t[$slotB] !== $slugB) continue;
		$hit[] = $name;
	}
	return $hit;
}

/* ---------- 1. head refuses headgear --------------------------------------- */
$n = 0;
foreach (DHCF_HEADGEAR_EXCLUDED_BY_HEAD as $head => $gears) {
	foreach ($gears as $gear) {
		$n++;
		$hit = mint_wearing($mint, 'head', $head, 'headgear', $gear);
		ok(!$hit, "$head + $gear is refused, but " . implode(', ', $hit) . ' was minted wearing it');
	}
}
printf("  head/headgear exclusions checked against the mint: %d\n", $n);

/* ---------- 2. head refuses companion -------------------------------------- */
$n = 0;
foreach (DHCF_COMPANION_EXCLUDED_BY_HEAD as $head => $comps) {
	foreach ($comps as $comp) {
		$n++;
		$hit = mint_wearing($mint, 'head', $head, 'companion', $comp);
		ok(!$hit, "$head + $comp is refused, but " . implode(', ', $hit) . ' was minted wearing it');
	}
}
printf("  head/companion exclusions checked against the mint: %d\n", $n);

/* ---------- 3. an exclusive weapon refuses arms ---------------------------- */
/* dhcf_arms_weapon_clash() is a function rather than a list, so this asks it
   directly for every arms/weapon pairing the collection actually contains. */
$clashes = array();
foreach ($mint as $name => $t) {
	if (empty($t['arms']) || empty($t['weapon'])) continue;
	if (dhcf_arms_weapon_clash($t['arms'], $t['weapon'])) {
		$clashes[] = $name . ' (' . $t['arms'] . ' + ' . $t['weapon'] . ')';
	}
}
ok(!$clashes, 'the arms/weapon rule refuses minted Fighters: ' . implode(', ', $clashes));
printf("  arms/weapon pairings in the mint: all buildable\n");

/* ---------- 4. the one this file was written for --------------------------- */
/* Named explicitly. A loop over the exclusion list cannot notice a pairing
   being ADDED back, because the loop's answer is "no mint contradicts it" --
   which is false the moment it returns. */
ok(dhcf_headgear_blocked('beheaded-cyborg', 'trojan-detection-mask') === false,
   'beheaded-cyborg + trojan-detection-mask is refused again -- DHC2F043 is a real '
 . 'Fighter and the platform must be able to draw it');
ok(mint_wearing($mint, 'head', 'beheaded-cyborg', 'headgear', 'trojan-detection-mask') === array('DHC2F043'),
   'the snapshot no longer contains DHC2F043 wearing that pairing, so the check above '
 . 'is pinning a rule nothing justifies');
/* And the exclusions that DO stand, so removing them is not free either. */
foreach (array('ww2-helmet', 'merged', 'steel-viking-helmet') as $g) {
	ok(dhcf_headgear_blocked('beheaded-cyborg', $g) === true,
	   "beheaded-cyborg + $g stopped being refused; it has no skull to carry one");
	ok(!mint_wearing($mint, 'head', 'beheaded-cyborg', 'headgear', $g),
	   "the mint contains beheaded-cyborg + $g after all -- that exclusion must go too");
}

/* ---------- 5. every slug in the snapshot is a trait we actually have ------- */
$rar = require __DIR__ . '/dhcrarity.php';
$ghost = array();
foreach ($mint as $name => $t) {
	foreach ($t as $slot => $slug) {
		$cat = ($slot === 'effects1' || $slot === 'effects2') ? 'effects' : $slot;
		if (!isset($rar[$cat][$slug])) $ghost[$cat . '/' . $slug] = true;
	}
}
ok(!$ghost, 'the snapshot names traits that are not in dhcrarity.php: ' . implode(', ', array_keys($ghost)));

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all mint checks passed\n");
exit($fail ? 1 : 0);
