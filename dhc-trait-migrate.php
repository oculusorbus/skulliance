<?php
/**
 * dhc-trait-migrate.php — the data half of the 2026-10-30 trait integration.
 * CLI only.
 *
 *   php dhc-trait-migrate.php          what it WOULD change
 *   php dhc-trait-migrate.php --run    actually change it
 *
 * THREE PHASES. The first two are one-off repairs from the 2026-10-30
 * delivery; the third is general and worth re-running whenever a pair is
 * tethered.
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
 * 2. HALVES HANDED OUT ON THEIR OWN. A tethered pair is one weapon drawn in
 *    two files, and the draw awards both together -- but only since the pair
 *    was tethered. Four pairs have gone untethered at some point and the
 *    ledger carries the orphans:
 *
 *      bioweapon / bio-weapon-strap  the strap shipped late, 2026-10-30.
 *      dh-spike-blaster, lil-fren and mega-taser-cannon  Maxingo delivered
 *        these pre-split as `X.png` and `X 1.png`. Nobody separated them by
 *        hand, so nobody thought to tether them, and the draw handed out
 *        halves for as long as they have existed. A player could wear a
 *        bandolier with no gun behind it.
 *
 *    Now that they are tethered the assembler places both or neither, so a
 *    player short of one half cannot build with the other: the selection
 *    pulls the partner in and the save refuses it as a trait they never had.
 *    Phase 3 settles that in both directions.
 *
 * DRY RUN BY DEFAULT. Nothing is written without --run.
 *
 * SAFE TO RE-RUN, and phase 3 is MEANT to be. The renames match only the OLD
 * slug, which no longer exists afterwards; the backfill awards the difference
 * between the two halves held, which is zero once it has run.
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
 * 3. ORPHANED HALVES. Every tethered pair, both directions.
 *
 * A tethered pair is one weapon drawn in two files, and the draw awards both
 * halves together -- but only since the pair was tethered. Anything handed
 * out before that is a half on its own: a bandolier with no gun behind it,
 * or a gun with nothing holding it on.
 *
 *   bioweapon / bio-weapon-strap   the strap shipped late, 2026-10-30
 *   dh-spike-blaster, lil-fren and mega-taser-cannon   Maxingo delivered
 *     these pre-split as `X.png` and `X 1.png`, and nobody tethered them
 *     because nobody had to separate them by hand. The draw handed out
 *     halves for as long as they have existed.
 *
 * NOT HARDCODED TO A PAIR, because this is the third time it has been
 * needed. It walks DHCF_TETHERED, so tethering a new pair and running this
 * is the whole repair.
 *
 * BOTH DIRECTIONS. The earlier version only granted straps to weapon
 * holders, which was right for the Bio Weapon because the strap was the new
 * art. These three have been dropping as either half, so a player can be
 * short of either one.
 *
 * COUNTS, NOT PRESENCE: a player holding two guns and one strap is owed a
 * second strap, or they can build one Fighter and not the other.
 * ------------------------------------------------------------------ */
echo "orphaned tethered halves\n";

/* The category a slug lives in, read off the rarity table rather than
   assumed to be 'weapon' -- nothing about a tether says it must be. */
$rar = dhcf_rarity();
$catOf = array();
foreach ($rar as $cat => $rows) foreach ($rows as $slug => $_) $catOf[$slug] = $cat;

$pairs = array();
foreach (DHCF_TETHERED as $x => $y) {
	$k = $x < $y ? "$x|$y" : "$y|$x";
	$pairs[$k] = true;
}
/*
 * THE DETECTION AND THE REPAIR BOTH LIVE IN dhcfighters-lib.php NOW, as
 * dhcf_tether_orphans() and dhcf_tether_repair(). They were here, and then
 * the owner hit an orphan on a phone and this file was no use to him --
 * admin-tethers.php does the same job from a browser. Two copies of a
 * repair that writes ledger rows is one too many, so this calls the same
 * pair the page does.
 */
$orphans = dhcf_tether_orphans($conn);
$grandTotal = 0; $people = array();
foreach ($orphans as $o) { $grandTotal += (int)$o['owed']; $people[(int)$o['user_id']] = true; }

$byPair = array();
foreach ($orphans as $o) $byPair[$o['pair']][] = $o;
foreach ($byPair as $pair => $rows) {
	$n = 0; foreach ($rows as $o) $n += (int)$o['owed'];
	printf("  %-50s %d piece(s) for %d player(s)\n", $pair, $n, count($rows));
	foreach ($rows as $o) {
		printf("      user %-6d %-22s %d x %s\n", $o['user_id'],
			substr($o['username'], 0, 22), $o['owed'], $o['slug']);
	}
}
if (!$orphans) echo "  nothing outstanding\n";

if ($run && $orphans) dhcf_tether_repair($conn, $orphans);

printf("\n  %d piece(s) for %d player(s)%s\n",
	$grandTotal, count($people), $run ? ' awarded' : ' would be awarded');

echo $run ? "\nDone.\n" : "\nDry run. Re-run with --run to apply.\n";
