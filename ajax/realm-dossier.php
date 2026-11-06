<?php
/**
 * ajax/realm-dossier.php — one realm's card, as JSON.
 *
 * PUBLIC, DELIBERATELY, and it is the only endpoint in ajax/ that is.
 * The world map is reachable without a login (realms-map.php), so the
 * card a reader opens on it has to answer without one too. Every field
 * realm_dossier() returns is already published elsewhere: profile.php
 * serves any username and avatar to anyone, and a realm's levels are
 * what the raid screen shows an attacker before they commit.
 *
 * NO skulliance.php AND NO verify.php, for the same reason skullpaper
 * omits them -- including either turns a public URL into a redirect to
 * Discord. There is a harness assertion on that, because it is the kind
 * of line somebody adds while fixing something else.
 *
 * GET AND READ ONLY. It writes nothing, so a GET is correct here where
 * it would be wrong for the admin endpoints next to it.
 */
ob_start();
ini_set('display_errors', 0);

function rd_out($a) {
	while (ob_get_level() > 0) ob_end_clean();
	header('Content-Type: application/json');
	/* A card is the same card for everybody and changes when a raid
	   settles, so a minute of caching takes the repeat clicks off the
	   database without ever showing a stale fight. */
	header('Cache-Control: public, max-age=60');
	echo json_encode($a);
	exit;
}

include_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../realms-lib.php';

$realm_id = isset($_GET['realm_id']) ? intval($_GET['realm_id']) : 0;
if ($realm_id <= 0) rd_out(array('success' => false, 'error' => 'no realm'));

$card = realm_dossier($conn, $realm_id);
if (!$card) rd_out(array('success' => false, 'error' => 'not found'));

rd_out(array('success' => true, 'card' => $card));
