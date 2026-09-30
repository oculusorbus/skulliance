<?php
/**
 * ajax/dhc-build-stats.php
 * Where the build currently on the assembler canvas would place.
 *
 * Answers the four axes the Collection sorts by -- Rarest, Deadliest,
 * Toughest, Hardest hitting -- for a trait set that has usually never been
 * saved, so the player can see what a piece does before spending it.
 *
 * SERVER-SIDE ON PURPOSE. Health and power come out of dhca_build_fighter()
 * and the score out of dhcf_score(); reimplementing either in the canvas's JS
 * would put a second opinion about what a torso is worth next to the one the
 * Arena actually fights with. The canvas asks and draws the answer.
 *
 * NO OWNERSHIP CHECK, deliberately, and it is not an oversight. This reads
 * nothing about the player and spends nothing: it is arithmetic over a trait
 * list plus counts from a collection that is already public on
 * dhcgallery.php. Signed-in is required only because there is no reason for a
 * stranger to be driving it. Saving is where ownership is enforced, by
 * dhcf_save_fighter(), which re-validates the whole layout regardless.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhc-ranks.php';
require_once __DIR__ . '/../dhc-json.php';

if (empty($_SESSION['userData']['user_id'])) dhc_json(array('ok' => false));

$traits = json_decode((string)($_POST['traits'] ?? ''), true);
$traits = is_array($traits) ? dhcf_clean_traits($traits) : array();
if (!$traits) dhc_json(array('ok' => false, 'empty' => true));

/*
 * EDITING: leave the Fighter's own row out of the pool it is being ranked
 * against, or it competes with itself and a build that is about to be the
 * rarest reads as second. The id is checked against this player, so a client
 * naming somebody else's Fighter drops nothing.
 */
$exclude = null;
$edit    = (int)($_POST['edit'] ?? 0);
if ($edit > 0) {
	$r = $conn->query(sprintf(
		"SELECT traits, rarity_score FROM dhc_fighters
		 WHERE id = %d AND user_id = %d AND disassembled_at IS NULL LIMIT 1",
		$edit, (int)$_SESSION['userData']['user_id']));
	if ($r && $row = $r->fetch_assoc()) {
		$t = json_decode($row['traits'], true);
		if (is_array($t) && $t) $exclude = dhcf_rank_values($t, (int)$row['rarity_score']);
	}
}

$out = dhcf_rank_build($conn, $traits, $exclude);
$conn->close();

dhc_json(array_merge(array('ok' => true), $out));
