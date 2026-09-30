<?php
/**
 * dhc-download.php -- hand a player the full-size picture of their Fighter.
 *
 *     dhc-download.php?serial=123
 *
 * Streams a 1000px PNG as an attachment. That is the size of Maxingo's master
 * art: every layer under dhc/web/1000 is 1000x1000, so this is the largest
 * composite that is real detail rather than an upscale, and it is what a
 * player needs to post their assembly anywhere that is not this site. The
 * gallery already shows those same masters stacked in the browser -- what was
 * missing was a way to leave with one file instead of eleven.
 *
 * IT DOES NOT COMPOSE ANYTHING, for the reason dhc-compose.php spells out at
 * length: dhcf_render_fighter() knows the armless-torso variants, the
 * single-arm hybrid clip and the per-trait nudges, and a second composer
 * written for this page would quietly draw Fighters that differ from the
 * assembler, the Arena and the Discord embed. This asks that renderer for
 * 1000 instead of 500 and sends the bytes.
 *
 * PUBLIC, like dhcgallery.php. Nobody owns these -- the whole collection is
 * browsable by a stranger and the 1000px layers are already served to any
 * visitor who opens a Fighter -- so gating the flattened version would only
 * stop the sharing this exists to enable. The owner is not checked and does
 * not need to be.
 *
 * NOTHING MAY PRINT BEFORE THE IMAGE. db.php runs with display_errors on, so
 * the include is buffered; a notice landing in front of the PNG header is a
 * corrupt download, not a warning.
 */

ob_start();
include 'db.php';
require_once __DIR__ . '/dhcfighters-lib.php';
require_once __DIR__ . '/dhcfighters-notify.php';
ob_end_clean();

/* A plain-text failure, never a half-written image.
 *
 * Unwinding to ZERO is right here and wrong in rewards.php, which is worth
 * saying because the two look identical: this file is the request, there is
 * no caller whose buffer could be destroyed, and anything already buffered
 * is by definition not part of the answer. run_reward_jobs() is called from
 * inside somebody else's buffering and swallowed its own failure manifest
 * doing this. */
function dhcd_fail($code, $msg) {
	while (ob_get_level() > 0) ob_end_clean();
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	echo $msg . "\n";
	exit;
}

/* db.php returns false rather than throwing -- mysqli_report is off
   platform-wide -- so a dead connection arrives here as an undefined $conn
   and a fatal on the next line. */
if (!isset($conn) || !$conn) dhcd_fail(503, 'Database unavailable.');

$serial = isset($_GET['serial']) ? (int)$_GET['serial'] : 0;
if ($serial <= 0) dhcd_fail(400, 'No Fighter asked for.');

$row = null;
$res = $conn->query(sprintf(
	"SELECT id, serial, name, traits FROM dhc_fighters
	 WHERE serial = %d AND disassembled_at IS NULL LIMIT 1", $serial));
if ($res) $row = $res->fetch_assoc();
if (!$row) dhcd_fail(404, 'No Fighter with that number.');

$traits = json_decode($row['traits'], true);
if (!is_array($traits) || !$traits) dhcd_fail(404, 'That Fighter has no layout to draw.');

/*
 * 1000 first, 500 as a fallback. The fallback is not about GD being missing --
 * then both fail -- but about the day a 1000px source is absent for one trait
 * while the 250 and 500 derivatives are fine. A slightly smaller download
 * beats a broken button.
 */
ob_start();
$url = dhcf_render_fighter($traits, (int)$row['serial'], 1000);
if ($url === '') $url = dhcf_render_fighter($traits, (int)$row['serial'], 500);
ob_end_clean();
if ($url === '') dhcd_fail(503, 'Could not draw that Fighter right now.');

$abs = __DIR__ . '/dhcrenders/' . basename(parse_url($url, PHP_URL_PATH));
if (!is_file($abs)) dhcd_fail(503, 'Could not draw that Fighter right now.');

/* The rule lives in the library so dhc-download-harness.php can check it
   without standing up a web request. */
$file = dhcf_download_filename($row);

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($abs));
/* A Fighter can be edited, and editing re-hashes the render but not this URL,
   so the browser must come back and ask rather than serve yesterday's build. */
header('Cache-Control: private, no-cache');
readfile($abs);
$conn->close();
