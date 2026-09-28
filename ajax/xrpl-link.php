<?php
/**
 * ajax/xrpl-link.php — link an XRPL wallet reported by a browser extension.
 *
 * ONE ENDPOINT FOR EVERY EXTENSION, because they differ only in which button
 * was pressed. Crossmark and GemWallet both hand the page an r-address and
 * nothing this endpoint can check differently, so a file each would be three
 * copies of the same twenty lines drifting apart. `via` records which, and is
 * whitelisted rather than trusted.
 *
 * These paths exist because Xaman cannot sign for a HARDWARE WALLET: a
 * Ledger-held XRPL account imports into Xaman read-only, and read-only cannot
 * sign a SignIn -- so the collectors most likely to hold something valuable
 * were the ones who could not link it. GemWallet is also what xrp.cafe offers,
 * which makes it what a lot of these NFTs were bought with.
 *
 * HOW THIS DIFFERS FROM XAMAN, AND IT MATTERS:
 *
 *   Xaman      the SERVER fetches the signed result from Xaman's API. The
 *              address is proved to us and the browser is not trusted.
 *   extensions the BROWSER reports an address. We take its word.
 *
 * That is the same bar the Cardano path has always used -- skulliance.php
 * hands $_POST['stakeaddress'] straight to checkAddress() -- so this adds no
 * new CLASS of risk to the platform. It is still weaker than the Xaman path,
 * which is why wallets.link_method records which was used: a policy that later
 * wants to treat them differently can, without asking anybody to re-link.
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

/* THIS REQUEST TALKS TO IPFS AND CANNOT BE FAST.
   A holder's metadata is fetched once, when they link, and gateways answer in
   seconds rather than milliseconds -- the first real link needed ~16s for 20
   NFTs even with the fetches running in parallel. PHP's default 30s ceiling
   killed the SERIAL version outright (111s), after the ownership reset and
   before the writes, so the holder saw nothing and the log said nothing.
   xrpl_verify_user()'s own 45s budget is the real limit and degrades to
   fallback names rather than dying; this just keeps PHP from pulling the rug
   out from under it first. */
@set_time_limit(90);

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

$account = isset($_POST['address']) ? trim((string)$_POST['address']) : '';
if (!xaman_valid_account($account))
	dhc_json(array('ok' => false, 'message' => 'That is not an XRPL address.'));

/* Whitelisted, not trusted: `via` is written to the row that records how this
   address was proved, so a client must not be able to label itself 'xaman'
   and look server-verified when it is not. */
$allowed = array('crossmark' => 1, 'gemwallet' => 1, 'ledger' => 1);
$via = isset($_POST['via']) ? strtolower(trim((string)$_POST['via'])) : '';
if (!isset($allowed[$via])) $via = 'extension';

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
	createAddress($conn, $account, $account, XRPL_CHAIN_ID, $via);
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
