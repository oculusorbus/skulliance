<?php
/**
 * ajax/solana-link.php — link a Solana wallet reported by a browser wallet.
 *
 * ONE ENDPOINT FOR EVERY WALLET, because they differ only in which button was
 * pressed. Solflare, Phantom and Backpack all hand the page a base58 pubkey
 * and nothing this endpoint can check differently; a file each would be three
 * copies of the same twenty lines drifting apart. `via` records which, and is
 * whitelisted rather than trusted.
 *
 * THE BROWSER REPORTS THE ADDRESS AND WE TAKE ITS WORD, which is the same bar
 * the Cardano path has always used (skulliance.php hands
 * $_POST['stakeaddress'] straight to checkAddress()) and the same one the
 * XRPL extensions use. So this adds no new CLASS of risk. wallets.link_method
 * records which path was used, so a policy that later wants to treat them
 * differently can, without asking anybody to re-link.
 *
 * AND UNLIKE XRPL, THE STRONGER VERSION IS ACTUALLY AVAILABLE HERE. Solana
 * signatures are ed25519 and nothing else, this repo already vendors
 * lib/sodium_compat (because no PHP build on this server has ext/sodium), and
 * sodium_crypto_sign_verify_detached() is all a Sign-In-With-Solana check
 * needs. XRPL could not do that -- its keys are secp256k1 OR ed25519 and the
 * secp256k1 half has no pure-PHP verifier here.
 *
 * It is NOT done yet, deliberately rather than half-done: it is a second
 * round trip through the wallet for every link, and it buys nothing until
 * there is something worth stealing behind a linked address. When it is
 * wanted, it is additive -- a new `via` value and a signature field, with
 * every existing row keeping the method it was linked under.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../verify-solana.php';
require_once __DIR__ . '/../dhc-json.php';

/* THIS REQUEST TALKS TO A METADATA HOST AND CANNOT BE INSTANT. Cheaper than
   the XRPL path -- OMEN's documents come from one origin rather than a race
   of IPFS gateways -- but a holder with a hundred pieces is still a hundred
   fetches on the first link. sol_verify_user()'s own 45s budget is the real
   limit; this keeps PHP from pulling the rug out from under it first. */
@set_time_limit(90);

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

$account = isset($_POST['address']) ? trim((string)$_POST['address']) : '';
/* Decoded, not pattern-matched: a 44-character string of legal base58
   characters can still decode to 33 bytes, and the place that would notice
   is the nightly job. */
if (!sol_valid_address($account))
	dhc_json(array('ok' => false, 'message' => 'That is not a Solana address.'));

/* Whitelisted, not trusted: `via` is written to the row that records how this
   address was proved, so a client must not be able to label itself with a
   method that implies more checking than actually happened. */
$allowed = array('solflare' => 1, 'phantom' => 1, 'backpack' => 1);
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
	createAddress($conn, $account, $account, SOLANA_CHAIN_ID, $via);
}

/* Verify now. Nobody who has just connected a wallet should be told to come
   back tomorrow -- both other chains verify on connect and this one is the
   fastest of the three. */
$v = sol_verify_user($conn, $user_id);

if (!empty($v['message'])) $msg = 'Wallet linked. ' . $v['message'];
elseif ($v['staked'] > 0)  $msg = 'Wallet linked — ' . (int)$v['staked'] . ' NFT'
                                . ($v['staked'] === 1 ? '' : 's') . ' now staking.';
else                       $msg = 'Wallet linked. Nothing from a registered collection '
                                . 'in it yet.';

dhc_json(array('ok' => true, 'account' => $account, 'staked' => (int)$v['staked'],
	'message' => $msg));
