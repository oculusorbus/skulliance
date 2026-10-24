<?php
/**
 * return-gift-card.php — the picture that goes in the invitation.
 *
 *     return-gift-card.php?d=<discord_id>   ->  302 to the rendered PNG
 *
 * WHY A REDIRECT AND NOT A STREAM. What the admin needs is not a
 * picture on screen, it is a URL they can paste into a DM -- and a
 * URL that is paste-able must be PUBLIC. This endpoint is behind the
 * admin gate, so its own address is useless to the recipient: paste
 * it and they get "Not found." So it does the one thing they cannot
 * do themselves, which is cause the render to exist, and then hands
 * the browser the public dhcrenders/ URL. Copy Image Address on the
 * admin page therefore yields the address that works for everybody.
 *
 * ON DEMAND, WHICH IS THE WHOLE REASON IT IS A SEPARATE FILE. The
 * lapsed list can be hundreds of rows, and compositing a stack of
 * thousand-pixel layers per row would make the page unusable to open.
 * The column carries lazy <img>es pointing here, so only the rows
 * somebody actually scrolls to are ever rendered, and each is
 * rendered once -- dhcf_render_fighter() caches on the traits.
 *
 * NOTHING MAY PRINT BEFORE THE REDIRECT. display_errors is on
 * platform-wide and a notice ahead of a Location header means the
 * header never goes out and the admin sees a wall of text where a
 * Fighter should be. Same reason dropship-card.php buffers.
 */

ob_start();
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();                       /* exits plain-text 404, no chrome */
require_once __DIR__ . '/lib/return-gift.php';
require_once __DIR__ . '/dhcfighters-notify.php';
ob_end_clean();

function rgc_fail($code, $msg) {
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	echo $msg . "\n";
	exit;
}

$d = isset($_GET['d']) ? preg_replace('/[^0-9]/', '', (string)$_GET['d']) : '';
if ($d === '') rgc_fail(400, 'No id.');

$traits = return_gift_traits($d);
if (!$traits) rgc_fail(503, 'No package (the rarity table did not load).');

/*
 * SERIAL 0, because this is not a saved Fighter and must never collide
 * with one. Real serials come from dhcf_next_serial() and start at 1,
 * so f0-* is a namespace nothing else writes into. The traits hash in
 * the filename still keeps two different packages apart.
 */
/* THE LAYOUT, NOT THE CATEGORY MAP: dhcf_layers() walks slots, and
   'effects' is not one of them -- see return_gift_layout(). */
$url = dhcf_render_fighter(return_gift_layout($traits), RETURN_GIFT_SERIAL, RETURN_GIFT_SIZE);
if ($url === '') rgc_fail(503, 'Could not render (no GD, or the art is missing).');

/* 302, not 301: the art behind a slug can be replaced by FTP, and a
   permanent redirect cached in the admin's browser would outlive it. */
header('Location: ' . $url, true, 302);
header('Cache-Control: private, max-age=600');
exit;
