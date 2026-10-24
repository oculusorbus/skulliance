<?php
/**
 * ajax/admin-outreach.php — mark one lapsed member as written to.
 *
 * In ajax/ rather than as an admin-*.php page because it answers JSON
 * and never renders: the admin page sweep expects an admin-*.php file
 * to be either a panel with a layout or a redirect, and this is
 * neither.
 *
 * POST ONLY, and gated. Nothing here is destructive -- the worst a
 * stranger could do is tick a name off the owner's own notes -- but
 * it is still a write, and a GET would let an <img src> fire it.
 */
ob_start();
ini_set('display_errors', 0);
header('Content-Type: application/json');

function out($a) { while (ob_get_level() > 0) ob_end_clean(); header('Content-Type: application/json'); echo json_encode($a); exit; }

if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] !== 'POST') {
	http_response_code(405);
	out(array('ok' => false, 'why' => 'POST only.'));
}

include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../admin-lib.php';
require_once __DIR__ . '/../lib/outreach-log.php';

/* admin_require() answers with a 404 page, which is wrong for an
   endpoint the browser reads as JSON, so the same rights check is made
   directly and answered in kind. */
$rights = function_exists('adminRights') ? adminRights() : array('ok' => false);
if (empty($rights['ok'])) { http_response_code(403); out(array('ok' => false, 'why' => 'Not allowed.')); }

$on = !isset($_POST['off']);

/*
 * A BATCH IS ONE WRITE, not one request per person. Seventy eight
 * separate calls would be seventy eight read-modify-write cycles on
 * the same file, and overlapping ones lose each other's changes.
 */
if (isset($_POST['ids'])) {
	$ids = is_array($_POST['ids']) ? $_POST['ids'] : explode(',', (string)$_POST['ids']);
	/* A cap, because the body is a form post and the list is a list of
	   rows on one page -- anything larger is a mistake or a probe. */
	if (count($ids) > 2000) out(array('ok' => false, 'why' => 'Too many at once.'));
	$n = outreach_mark_many($ids, $on);
	if ($n < 0) out(array('ok' => false, 'why' => 'Could not save.'));
	out(array('ok' => true, 'n' => $n, 'done' => $on));
}

$id = isset($_POST['discord_id']) ? preg_replace('/[^0-9]/', '', (string)$_POST['discord_id']) : '';
if ($id === '') out(array('ok' => false, 'why' => 'No id.'));

out(array('ok' => outreach_mark($id, $on), 'done' => $on));
