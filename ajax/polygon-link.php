<?php
/**
 * ajax/polygon-link.php — link a Polygon wallet reported by a browser wallet.
 *
 * ONE ENDPOINT, AND ON THIS CHAIN ALSO ONE BUTTON. Solana needed three
 * tiles because Solflare, Phantom and Backpack each inject their own
 * namespace; every EVM wallet injects window.ethereum and the user's
 * default is whichever answers, so there is nothing to choose between.
 * `via` records which one identified itself, and is whitelisted rather
 * than trusted.
 *
 * THE BROWSER REPORTS THE ADDRESS AND WE TAKE ITS WORD, which is the same
 * bar every other chain here uses (skulliance.php hands
 * $_POST['stakeaddress'] straight to checkAddress()). So this adds no new
 * CLASS of risk. wallets.link_method records the path, so a policy that
 * later wants to treat them differently can, without a re-link.
 *
 * A SIGN-IN-WITH-ETHEREUM CHECK IS NOT DONE, and unlike Solana it is not
 * merely undone -- it is the one chain here where it would be real work.
 * Recovering an address from an EIP-191 signature needs secp256k1 public
 * key recovery AND keccak256, and this server has neither: no
 * ext-secp256k1, and PHP's hash('sha3-256') is FIPS-202, a different
 * padding from Ethereum's keccak. Both would have to be vendored in pure
 * PHP. When it is wanted it is additive -- a new `via` value and a
 * signature field, with every existing row keeping its method.
 *
 * THE NETWORK THE WALLET IS ON IS NOT CHECKED, because it does not
 * matter. An EVM address is identical on every chain, and ownership is
 * read from this platform's own Polygon node server-side. The page asks
 * the wallet to switch to Polygon as reassurance; declining must still
 * link, and this endpoint never learns either way.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../verify-polygon.php';
require_once __DIR__ . '/../dhc-json.php';

/* THIS REQUEST READS A WHOLE COLLECTION'S OWNER MAP and then a metadata
   document per new token. The map is 18 calls at ~180ms for 4,444 tokens,
   so the floor is a few seconds; a holder with a hundred pieces adds a
   hundred gateway fetches on the first link. poly_verify_user()'s own 45s
   budget is the real limit -- this keeps PHP from pulling the rug out
   from under it first. */
@set_time_limit(90);

$user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) dhc_json(array('ok' => false, 'message' => 'Not signed in.'));

$account = isset($_POST['address']) ? trim((string)$_POST['address']) : '';
/*
 * FOLDED TO LOWER CASE BEFORE IT IS STORED, not just before it is
 * compared. ownerOf answers lower case and wallets report EIP-55 mixed
 * case; the verifier folds both sides, but a row stored in one case and
 * matched in the other is one refactor away from silently matching
 * nothing, and nothing would error when it stopped.
 */
$account = poly_norm_address($account);
if ($account === '')
	dhc_json(array('ok' => false, 'message' => 'That is not a Polygon address.'));

/* Whitelisted, not trusted: `via` is written to the row that records how
   this address was proved, so a client must not be able to label itself
   with a method implying more checking than actually happened. */
$allowed = array('metamask' => 1, 'rabby' => 1, 'coinbase' => 1, 'brave' => 1, 'trust' => 1);
$via = isset($_POST['via']) ? strtolower(trim((string)$_POST['via'])) : '';
if (!isset($allowed[$via])) $via = 'evm';

/* One wallet, one owner -- checked before the insert so the message can
   say what actually happened rather than surfacing a duplicate-key error.
   Compared lower-case against a column that now only ever holds lower
   case for this chain. */
$esc = $conn->real_escape_string($account);
$own = $conn->query("SELECT user_id FROM wallets WHERE stake_address = '$esc' LIMIT 1");
if ($own && $own->num_rows) {
	$row = $own->fetch_assoc();
	if ((int)$row['user_id'] !== $user_id)
		dhc_json(array('ok' => false, 'message' => 'That wallet is linked to another account.'));
	/* Already ours: fall through to verification rather than refusing.
	   Someone re-linking is usually someone wondering why their NFTs are
	   not showing. */
} else {
	/* Both columns, as on XRPL: there is no separate payment address on
	   an EVM chain to distinguish from -- the account IS the address --
	   and getAllAddresses() reads stake_address. */
	createAddress($conn, $account, $account, POLYGON_CHAIN_ID, $via);
}

/* Verify now. Nobody who has just connected a wallet should be told to
   come back tomorrow, and this chain can afford it: one owner-map read
   answers regardless of how much the wallet holds. */
$v = poly_verify_user($conn, $user_id);

if (!empty($v['message'])) $msg = 'Wallet linked. ' . $v['message'];
elseif ($v['staked'] > 0)  $msg = 'Wallet linked - ' . (int)$v['staked'] . ' NFT'
                                . ($v['staked'] === 1 ? '' : 's') . ' now staking.';
else                       $msg = 'Wallet linked. Nothing from a registered collection '
                                . 'in it yet.';

dhc_json(array('ok' => true, 'account' => $account, 'staked' => (int)$v['staked'],
	'message' => $msg));
