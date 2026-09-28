<?php
/**
 * ajax/xaman-done.php — finish an XRPL wallet link.
 *
 * Called once, by the browser, after its websocket says the payload resolved.
 * Reads the result server-side and binds the proven account to the user.
 *
 * NOTHING THE BROWSER SAYS IS TRUSTED except which pending sign-in it is
 * talking about, and even that is checked against the session. The address
 * comes from Xaman's own answer, never from the request.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../xaman.php';
require_once __DIR__ . '/../dhc-json.php';

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

$uuid = isset($_POST['uuid']) ? (string)$_POST['uuid'] : '';
if (!xaman_claims($uuid))
	dhc_json(array('ok' => false, 'message' => 'That sign-in was not started here.'));

$res = xaman_result($uuid);

/* PENDING IS NOT FAILURE. The websocket can beat Xaman's own API by a moment,
   and telling somebody their sign-in failed while the prompt is still open on
   their phone is how a working flow gets abandoned. The client retries. */
if ($res['state'] === 'pending')
	dhc_json(array('ok' => false, 'retry' => true, 'message' => 'Waiting for Xaman…'));

if ($res['state'] === 'rejected') {
	xaman_forget();
	dhc_json(array('ok' => false, 'message' => 'Sign-in was declined.'));
}
if ($res['state'] !== 'signed')
	dhc_json(array('ok' => false, 'retry' => true, 'message' => 'Could not reach Xaman.'));

$account = $res['account'];
if (!xaman_valid_account($account)) {
	xaman_forget();
	dhc_json(array('ok' => false, 'message' => 'That address was not understood.'));
}

/* One wallet, one owner. Checked before the insert rather than relying on a
   unique key, so the message can say what actually happened. */
$esc = $conn->real_escape_string($account);
$own = $conn->query("SELECT user_id FROM wallets WHERE stake_address = '$esc' LIMIT 1");
if ($own && $own->num_rows) {
	$row = $own->fetch_assoc();
	xaman_forget();
	dhc_json((int)$row['user_id'] === $user_id
		? array('ok' => true, 'account' => $account, 'message' => 'That wallet is already linked to you.')
		: array('ok' => false, 'message' => 'That wallet is linked to another account.'));
}

createAddress($conn, $account, $account, XRPL_CHAIN_ID);
xaman_forget();

dhc_json(array('ok' => true, 'account' => $account,
	'message' => 'Wallet linked. Your XRPL holdings are counted on the next verification.'));
