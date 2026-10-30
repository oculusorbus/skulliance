<?php
/**
 * dhc-trait-migrate.php — the data half of the 2026-10-30 trait integration.
 * CLI only.
 *
 *   php dhc-trait-migrate.php          what it WOULD change
 *   php dhc-trait-migrate.php --run    actually change it
 *
 * TWO THINGS, both one-off, both consequences of art arriving later than the
 * slug that was guessing at it.
 *
 * 1. THREE SLUGS POINTED AT THE WRONG ART. Maxingo's delivery settled what
 *    the collection actually contains, and in three cases the piece we had
 *    under a name was not the piece the chain means by it:
 *
 *      electric-morning-star  was the skull-and-chains. The real Electric
 *                             Morning Star is the mace that hangs from them.
 *      code-sea-predator      was a piece that is not in the collection at
 *                             all. The collection's own one is green.
 *      dh-sorceror            was the blue one. The chain's single
 *                             'DH Sorceror' is the dark one.
 *
 *    The slug has to go to the piece the chain names, or rarity describes the
 *    wrong art and the minted Fighters in dhc-mint-traits.json render pieces
 *    those NFTs do not have. So the ART keeps the slug and the PLAYERS keep
 *    their art: whatever they were given is renamed to the slug it now lives
 *    under, and no assembled Fighter changes appearance. All three move to a
 *    rarer tier, never a commoner one.
 *
 * 2. THE BIO WEAPON SHIPPED WITHOUT ITS STRAP. The strap arrived in this
 *    delivery and the two are now tethered (DHCF_TETHERED), which means the
 *    assembler places both or neither. A player holding the weapon and no
 *    strap would have their Fighter refused at save for a shortfall on a
 *    trait they were never given -- so every bioweapon already in the ledger
 *    gets its strap here, one each.
 *
 *    Bone Blaster 3X needs no equivalent: both halves are new in this same
 *    delivery, so nobody holds one without the other.
 *
 * DRY RUN BY DEFAULT. Nothing is written without --run.
 *
 * SAFE TO RE-RUN. The renames match only the OLD slug, which no longer
 * exists afterwards, and the backfill awards the difference between weapons
 * held and straps held, which is zero on a second pass.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__ . '/db.php';
require_once __DIR__ . '/dhcfighters-lib.php';

$run = in_array('--run', $argv, true);
printf("DHC trait migration%s\n\n", $run ? '' : '  (DRY RUN, nothing written)');

/*
 * category, old slug, new slug. The category matters: a slug is only unique
 * within its own art directory, and matching on the slug alone would reach
 * into another category that happens to share the name.
 */
$RENAME = array(
	array('weapon',    'electric-morning-star', 'skull-and-chains'),
	array('companion', 'code-sea-predator',     'steel-code-sea-predator'),
	array('headgear',  'dh-sorceror',           'blue-dh-sorceror'),
);

/* ------------------------------------------------------------------ *
 * 1. THE LEDGER. One row per trait a player was given.
 * ------------------------------------------------------------------ */
echo "dhc_trait_drops\n";
$ledgerTotal = 0;
foreach ($RENAME as $r) {
	list($cat, $old, $new) = $r;
	$q = sprintf("SELECT COUNT(*) AS n, COUNT(DISTINCT user_id) AS u FROM dhc_trait_drops
	               WHERE category = '%s' AND slug = '%s'",
		$conn->real_escape_string($cat), $conn->real_escape_string($old));
	$res = $conn->query($q);
	$row = $res ? $res->fetch_assoc() : array('n' => 0, 'u' => 0);
	$n = (int)$row['n'];
	printf("  %-11s %-24s -> %-24s %4d row(s), %d player(s)\n", $cat, $old, $new, $n, (int)$row['u']);
	$ledgerTotal += $n;
	if (!$n || !$run) continue;
	$ok = $conn->query(sprintf("UPDATE dhc_trait_drops SET slug = '%s'
	                             WHERE category = '%s' AND slug = '%s'",
		$conn->real_escape_string($new), $conn->real_escape_string($cat),
		$conn->real_escape_string($old)));
	if (!$ok) { fwrite(STDERR, "  FAILED: " . $conn->error . "\n"); exit(1); }
}
printf("  %d ledger row(s)%s\n\n", $ledgerTotal, $run ? ' renamed' : ' would be renamed');

/* ------------------------------------------------------------------ *
 * 2. ASSEMBLED FIGHTERS. traits is a JSON object of slot => slug, so this
 *    cannot be done in SQL without picking apart the document. Read, rewrite,
 *    write back -- and only the rows that actually change.
 *
 *    KEYED BY SLOT, MATCHED BY CATEGORY. 'weapon' and 'weaponBack' are two
 *    slots over one art directory, and a disassembled Fighter is included on
 *    purpose: its row survives so its history cannot be replayed as new, and
 *    a stale slug in it would resurface if it were ever rebuilt.
 * ------------------------------------------------------------------ */
echo "dhc_fighters.traits\n";
$bySlug = array();
foreach ($RENAME as $r) $bySlug[$r[0] . '|' . $r[1]] = $r[2];

$res = $conn->query('SELECT id, traits FROM dhc_fighters');
$touched = 0; $fields = 0;
while ($res && ($row = $res->fetch_assoc())) {
	$t = json_decode((string)$row['traits'], true);
	if (!is_array($t)) continue;
	$changed = false;
	foreach ($t as $slot => $slug) {
		if (!is_string($slug) || $slug === '') continue;
		$k = dhcf_slot_category($slot) . '|' . $slug;
		if (!isset($bySlug[$k])) continue;
		$t[$slot] = $bySlug[$k];
		$changed = true; $fields++;
	}
	if (!$changed) continue;
	$touched++;
	if (!$run) continue;
	$ok = $conn->query(sprintf("UPDATE dhc_fighters SET traits = '%s' WHERE id = %d",
		$conn->real_escape_string(json_encode($t)), (int)$row['id']));
	if (!$ok) { fwrite(STDERR, "  FAILED on fighter " . (int)$row['id'] . ": " . $conn->error . "\n"); exit(1); }
}
printf("  %d Fighter(s), %d trait field(s)%s\n\n", $touched, $fields, $run ? ' rewritten' : ' would be rewritten');

/* ------------------------------------------------------------------ *
 * 3. THE MISSING STRAP. One per Bio Weapon held, and the difference only --
 *    a player who somehow already has straps is topped up, not doubled.
 * ------------------------------------------------------------------ */
echo "bio-weapon-strap backfill\n";
$WEAPON = 'bioweapon';
$STRAP  = dhcf_tethered_partner($WEAPON);
if ($STRAP === '') {
	fwrite(STDERR, "  bioweapon has no tethered partner -- is DHCF_TETHERED loaded?\n");
	exit(1);
}
$info = dhcf_trait_info('weapon', $STRAP);
if (!$info) {
	fwrite(STDERR, "  no rarity row for weapon/$STRAP -- run dhc/gen-rarity.py first\n");
	exit(1);
}

$held = array();
$res = $conn->query(sprintf("SELECT user_id,
                                    SUM(slug = '%s') AS w,
                                    SUM(slug = '%s') AS s
                               FROM dhc_trait_drops
                              WHERE category = 'weapon' AND slug IN ('%s', '%s')
                           GROUP BY user_id",
	$conn->real_escape_string($WEAPON), $conn->real_escape_string($STRAP),
	$conn->real_escape_string($WEAPON), $conn->real_escape_string($STRAP)));
while ($res && ($row = $res->fetch_assoc())) {
	$owe = (int)$row['w'] - (int)$row['s'];
	if ($owe > 0) $held[(int)$row['user_id']] = $owe;
}

if (!$held) {
	echo "  nothing outstanding\n";
} else {
	$total = 0;
	foreach ($held as $uid => $owe) {
		$nm = '';
		$ur = $conn->query('SELECT username FROM users WHERE id = ' . (int)$uid);
		if ($ur && ($ux = $ur->fetch_assoc())) $nm = (string)$ux['username'];
		printf("  user %-6d %-22s %d strap(s)\n", $uid, substr($nm, 0, 22), $owe);
		$total += $owe;
		if (!$run) continue;
		for ($i = 0; $i < $owe; $i++) {
			/* The same call the draw makes for a tethered partner, so the row
			   is shaped identically -- except that it does not announce. A
			   backfill is not news, and one Discord post per strap for art the
			   player has effectively been holding all along is noise. */
			dhcf_commit_drop($conn, (int)$uid, 'weapon', $STRAP, $info,
				'backfill', DHCF_PAIRED_MARK . $WEAPON, false);
		}
	}
	printf("  %d strap(s) for %d player(s)%s\n", $total, count($held), $run ? ' awarded' : ' would be awarded');
}

echo $run ? "\nDone.\n" : "\nDry run. Re-run with --run to apply.\n";
