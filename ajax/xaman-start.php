<?php
/**
 * ajax/xaman-start.php — begin an XRPL wallet link.
 *
 * Creates a Xaman SignIn payload and hands the browser the uuid, a QR and the
 * websocket to listen on. The browser holds that socket itself, so this
 * endpoint and its sibling are the ONLY two Xaman calls per wallet link --
 * polling would have been ~30 and would throttle the platform on launch day.
 * See multichain.md §4d.
 *
 * Signed in only: a wallet is linked TO somebody.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../xaman.php';
require_once __DIR__ . '/../dhc-json.php';

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

$start = xaman_start();
if (!$start) dhc_json(array('ok' => false,
	'message' => 'Could not reach Xaman. Try again in a moment.'));

/* Bind it to THIS session before the browser ever sees the uuid. The uuid is
   not a secret -- it travels to the page and over a public websocket -- so
   without this anyone who learns one could have that wallet bound to their
   own account. */
xaman_remember($start['uuid']);

dhc_json(array('ok' => true) + $start);
