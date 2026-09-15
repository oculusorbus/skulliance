<?php
/**
 * DHC FIGHTERS -- reclaim retired numbers. ONE-OFF.
 *
 * Until editing existed, changing a Fighter meant disassembling and rebuilding,
 * and disassembly retired the number for good. 422 and 423 went that way in the
 * first week. dhcf_delete_fighter() now releases the serial instead, so this
 * cannot keep happening -- but the numbers already burned need freeing by hand.
 *
 * Two jobs, both optional, both previewed before they touch anything:
 *
 *   --release   clear the serial on every disassembled Fighter, returning
 *               those numbers to the pool. Safe and unremarkable: a
 *               disassembled Fighter is hidden from the gallery, the boards
 *               and dhcf_is_first_build(), so nothing reads its number.
 *
 *   --compact   renumber the LIVE Fighters contiguously from
 *               DHCF_SERIAL_START in build order, closing gaps that already
 *               exist. This RENAMES any Fighter without a custom name, since
 *               the serial is its default name -- so an announcement already
 *               posted to Discord will name a number the Fighter no longer
 *               has. Sensible now, at a handful of Fighters. Not sensible
 *               later.
 *
 * CLI only, like dhcf-rescore.php, and for the same reason: it rewrites rows
 * belonging to other people and there is no admin role to gate a web version.
 *
 *     php dhcf-renumber.php                      # show what exists
 *     php dhcf-renumber.php --release            # preview
 *     php dhcf-renumber.php --release --confirm  # do it
 */

if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
chdir(__DIR__);

include __DIR__ . '/db.php';
require_once __DIR__ . '/dhcfighters-lib.php';

$release = in_array('--release', $argv, true);
$compact = in_array('--compact', $argv, true);
$confirm = in_array('--confirm', $argv, true);

$res = $conn->query("SELECT id, user_id, serial, name, created_at, disassembled_at
                     FROM dhc_fighters ORDER BY created_at ASC, id ASC");
if (!$res) { fwrite(STDERR, "query failed: " . $conn->error . "\n"); exit(1); }
$rows = array();
while ($r = $res->fetch_assoc()) $rows[] = $r;
if (!$rows) { echo "No Fighters.\n"; exit(0); }

echo "current state\n";
foreach ($rows as $r) {
	printf("  #%-4d user %-5d %-12s %-22s %s\n",
		$r['id'], $r['user_id'],
		$r['serial'] === null ? '(released)' : dhcf_default_name((int)$r['serial']),
		$r['name'] !== null && $r['name'] !== '' ? '"' . $r['name'] . '"' : '(uses its number)',
		$r['disassembled_at'] === null ? 'live' : 'disassembled ' . $r['disassembled_at']);
}

$live = array_values(array_filter($rows, function ($r) { return $r['disassembled_at'] === null; }));
$dead = array_values(array_filter($rows, function ($r) { return $r['disassembled_at'] !== null; }));
printf("\n%d live, %d disassembled\n", count($live), count($dead));

if (!$release && !$compact) {
	echo "\nNothing asked for. Pass --release and/or --compact (add --confirm to write).\n";
	$conn->close(); exit(0);
}

/* ---- what --release would free ---- */
$freeing = array();
foreach ($dead as $r) if ($r['serial'] !== null) $freeing[] = (int)$r['serial'];
if ($release) {
	sort($freeing);
	echo "\n--release: " . ($freeing
		? 'returns ' . implode(', ', array_map('dhcf_default_name', $freeing)) . ' to the pool'
		: 'nothing to free') . "\n";
}

/* ---- what --compact would renumber ---- */
$moves = array();
if ($compact) {
	$n = DHCF_SERIAL_START;
	foreach ($live as $r) {
		if ((int)$r['serial'] !== $n) $moves[] = array($r['id'], (int)$r['serial'], $n, $r['name']);
		$n++;
	}
	echo "\n--compact: " . (count($moves) ? count($moves) . " Fighter(s) renumbered" : "already contiguous") . "\n";
	foreach ($moves as $m) {
		printf("   #%-4d %s -> %s%s\n", $m[0],
			dhcf_default_name($m[1]), dhcf_default_name($m[2]),
			($m[3] === null || $m[3] === '') ? '   (its displayed name changes)' : '   (named, display unaffected)');
	}
}

if (!$confirm) {
	echo "\nDRY RUN -- nothing written. Re-run with --confirm to apply.\n";
	$conn->close(); exit(0);
}

$conn->begin_transaction();
try {
	if ($release) {
		if (!$conn->query("UPDATE dhc_fighters SET serial = NULL, updated_at = NOW()
		                   WHERE disassembled_at IS NOT NULL AND serial IS NOT NULL")) {
			throw new Exception($conn->error);
		}
		printf("released %d number(s)\n", $conn->affected_rows);
	}
	if ($compact && $moves) {
		/*
		 * Two passes. Assigning final numbers directly would collide with a
		 * serial still held by a row further down the list -- the unique index
		 * would reject it halfway through. Parking the live rows on NULL first
		 * clears the whole range at once, which a unique index allows because
		 * it does not compare NULLs.
		 */
		$ids = array_map(function ($m) { return (int)$m[0]; }, $moves);
		if (!$conn->query("UPDATE dhc_fighters SET serial = NULL WHERE id IN (" . implode(',', $ids) . ")")) {
			throw new Exception($conn->error);
		}
		foreach ($moves as $m) {
			if (!$conn->query(sprintf(
				"UPDATE dhc_fighters SET serial = %d, updated_at = NOW() WHERE id = %d",
				(int)$m[2], (int)$m[0]))) {
				throw new Exception($conn->error);
			}
		}
		printf("renumbered %d Fighter(s)\n", count($moves));
	}
	$conn->commit();
	echo "done. next number is now " . dhcf_default_name(dhcf_next_serial($conn)) . "\n";
} catch (Exception $e) {
	$conn->rollback();
	fwrite(STDERR, "rolled back, nothing changed: " . $e->getMessage() . "\n");
	exit(1);
}
$conn->close();
