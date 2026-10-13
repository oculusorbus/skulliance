<?php
/**
 * dhc-roles-harness.php -- what a trait DOES, and the fact nothing checked it.
 *
 * dhcarena-roles.php decides every trait's game effect from ~60 keywords tried
 * in order, and until this file existed nothing tested it at all. The order is
 * the whole design -- "blaster-arms" is a weapon before it is a limb -- so a
 * rule inserted in the wrong place silently reclassifies traits that have been
 * fighting one way for months.
 *
 * It also PRINTS the traits that fall through to ROLE_PLAIN in a slot whose
 * role is actually read. That list is the thing to look at when Maxingo
 * delivers new art: plain is a legitimate answer (it pays health), but a piece
 * that obviously does something and lands there wants a keyword or an override.
 *
 * Usage: php dhc-roles-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/dhcarena-roles.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* THE SLOTS WHOSE ROLE IS READ. dhca_build_fighter() loops exactly these --
   torso is NOT among them (its health comes straight from its rarity) and
   neither are background or weapon. A role on any other slot is dead data, so
   this list is what the report below is allowed to care about. */
$READ = array('head', 'headgear', 'arms', 'effects', 'companion');

/* Lifted from dhcarena-engine.php rather than retyped: if that loop ever stops
   reading a slot, the reporting here must stop caring about it too. */
$eng = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '',
                    file_get_contents(__DIR__ . '/dhcarena-engine.php'));
ok(preg_match("/foreach \(array\((\s*'[a-z0-9']+',?)+\s*\) as \\\$slot\)/", $eng) === 1
   || strpos($eng, "'head','headgear','arms','effects','effects1','effects2','companion'") !== false,
   'the engine no longer loops a literal slot list, so this harness cannot tell '
 . 'which roles are read -- re-derive $READ by hand');
ok(strpos($eng, "'torso'") === false
   || !preg_match("/array\('head'[^)]*'torso'[^)]*\) as \\\$slot/", $eng),
   'torso joined the role loop; its role used to be dead data and this file says so');

/* ---------- 1. the overrides actually win --------------------------------- */
foreach (dhca_role_overrides() as $slug => $want) {
	ok(dhca_trait_role($slug, 'companion') === $want
	   || dhca_trait_role($slug, 'headgear') === $want,
	   "the override for $slug does not win over the keyword table");
}
/* Named, not just "whatever is in the array" -- a loop over the overrides
   cannot notice one being deleted. Both exist because a keyword matched the
   piece's NAME rather than its job. */
ok(dhca_trait_role('dh-destroyer', 'headgear') === DHCA_ROLE_ARMOUR,
   'dh-destroyer is a weapon again; it is headgear, however it is named');
ok(dhca_trait_role('demon-companion', 'companion') === DHCA_ROLE_BEAST,
   'the Demon Companion is energy again; it is a creature fighting alongside you, '
 . 'and every other living companion is a beast');

/* ---------- 2. the ordering traps the comments warn about ----------------- */
$order = array(
	array('mk100-blaster-arms',  'arms',     DHCA_ROLE_WEAPON, 'a blaster-arm is a weapon before it is a limb'),
	array('cyber-666-knight-armor', 'arms',  DHCA_ROLE_ARMOUR, 'knight armor is armour'),
	array('golden-cyber-plague-detecting-mask', 'headgear', DHCA_ROLE_OPTIC,
	      'a detecting mask is an optic before "mask" is generic'),
	array('love-demonstration', 'effects',   DHCA_ROLE_PLAIN,
	      '"demonstration" contains "demon" -- the rule is hyphenated for exactly this'),
	array('dh-vision-shoulder-cam', 'companion', DHCA_ROLE_OPTIC, 'the shoulder cam sees'),
	array('digital-666-smoke',  'effects',   DHCA_ROLE_ENERGY, 'occult smoke is energy'),
);
foreach ($order as $c) {
	list($slug, $slot, $want, $why) = $c;
	$got = dhca_trait_role($slug, $slot);
	ok($got === $want, "$slug in $slot is '$got', expected '$want' -- $why");
}

/* ---------- 3. a worn creature is armour, not a pet ------------------------ */
foreach (array('head', 'headgear', 'torso') as $worn) {
	ok(dhca_trait_role('alien-skull', $worn) === DHCA_ROLE_ARMOUR,
	   "an alien skull WORN on your $worn is still being counted as a pet");
}
ok(dhca_trait_role('golden-isopunk', 'companion') === DHCA_ROLE_BEAST,
   'a companion creature stopped being a beast');

/* ---------- 4. every trait with art gets a role, and the plain report ------ */
$rarity = require __DIR__ . '/dhcrarity.php';
$plain = array();
$counts = array();
foreach ($rarity as $cat => $rows) {
	if (!in_array($cat, $READ, true)) continue;
	foreach ($rows as $slug => $_) {
		$r = dhca_trait_role($slug, $cat);
		ok(is_string($r) && $r !== '', "$cat/$slug came back with no role at all");
		$counts[$cat][$r] = (isset($counts[$cat][$r]) ? $counts[$cat][$r] : 0) + 1;
		if ($r === DHCA_ROLE_PLAIN) $plain[] = "$cat/$slug";
	}
}
echo "roles, for the slots the engine actually reads\n";
foreach ($counts as $cat => $byRole) {
	ksort($byRole);
	$bits = array();
	foreach ($byRole as $r => $n) $bits[] = "$r $n";
	printf("  %-10s %s\n", $cat, implode(' / ', $bits));
}
printf("\nunplaced (ROLE_PLAIN -> +%.1f%% health each), %d of them:\n",
	0.030 * 100, count($plain));
foreach ($plain as $p) echo "  $p\n";
echo "  ^ plain is a real answer, not a bug. Look here after new art lands:\n"
   . "    a piece that obviously does something wants a keyword or an override.\n";

/* A whole category falling to plain means a keyword list stopped matching
   rather than a few pieces being quiet. */
foreach ($counts as $cat => $byRole) {
	$tot = array_sum($byRole);
	$pl  = isset($byRole[DHCA_ROLE_PLAIN]) ? $byRole[DHCA_ROLE_PLAIN] : 0;
	ok($tot === 0 || $pl < $tot,
	   "EVERY $cat trait is unplaced -- the keyword table is not matching that category at all");
}

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all role checks passed\n");
exit($fail ? 1 : 0);
