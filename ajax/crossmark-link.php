<?php
/**
 * ajax/crossmark-link.php — link an XRPL wallet through the Crossmark extension.
 *
 * The second XRPL path, and it exists for one reason: Xaman cannot sign for a
 * HARDWARE WALLET. A Ledger-held XRPL account imports into Xaman read-only,
 * and read-only cannot sign a SignIn -- so the collectors most likely to hold
 * something valuable were the ones who could not link it. Crossmark is a
 * browser extension that talks to a Ledger directly.
 *
 * HOW THIS DIFFERS FROM XAMAN, AND IT MATTERS:
 *
 *   Xaman     the SERVER fetches the signed result from Xaman's API. The
 *             address is proved to us and the browser is not trusted.
 *   Crossmark the BROWSER reports an address. We take its word.
 *
 * That is the same bar the Cardano path has always used -- skulliance.php
 * hands $_POST['stakeaddress'] straight to checkAddress() -- so this adds no
 * new CLASS of risk to the platform. It is still the weaker of the two XRPL
 * paths, which is why wallets.link_method records which was used: a policy
 * that later wants to treat them differently can, without asking anybody to
 * re-link.
 *
 * Verifying server-side would mean checking an XRPL signature in PHP. The
 * keys are secp256k1 or ed25519, this server has no ext/sodium on any of its
 * PHP builds, and a CLI probe cannot answer what the web SAPI has. Left
 * undone deliberately rather than half-done: see multichain.md §4f.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../xaman.php';        // xaman_valid_account(), XRPL_CHAIN_ID
require_once __DIR__ . '/../verify-xrpl.php';
require_once __DIR__ . '/../dhc-json.php';

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

$account = isset($_POST['address']) ? trim((string)$_POST['address']) : '';
if (!xaman_valid_account($account))
	dhc_json(array('ok' => false, 'message' => 'That is not an XRP Ledger address.'));

/* One wallet, one owner -- checked before the insert so the message can say
   what actually happened rather than surfacing a duplicate-key error. */
$esc = $conn->real_escape_string($account);
$own = $conn->query("SELECT user_id FROM wallets WHERE stake_address = '$esc' LIMIT 1");
if ($own && $own->num_rows) {
	$row = $own->fetch_assoc();
	if ((int)$row['user_id'] !== $user_id)
		dhc_json(array('ok' => false, 'message' => 'That wallet is linked to another account.'));
	/* Already ours: fall through to verification rather than refusing. Someone
	   re-linking is usually someone wondering why their NFTs are not showing. */
} else {
	createAddress($conn, $account, $account, XRPL_CHAIN_ID, 'crossmark');
}

/* Verify now, exactly as the Xaman path does. Nobody who has just connected a
   wallet should be told to come back tomorrow. */
$v = xrpl_verify_user($conn, $user_id);

if (!empty($v['message'])) $msg = 'Wallet linked. ' . $v['message'];
elseif ($v['staked'] > 0)  $msg = 'Wallet linked — ' . (int)$v['staked'] . ' NFT'
                                . ($v['staked'] === 1 ? '' : 's') . ' now staking.';
else                       $msg = 'Wallet linked. Nothing from a registered collection '
                                . 'in it yet.';

dhc_json(array('ok' => true, 'account' => $account, 'staked' => (int)$v['staked'],
	'message' => $msg));
