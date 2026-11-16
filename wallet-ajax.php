<?php
ob_start();
include_once 'db.php';
include_once 'verify.php';

header('Content-Type: application/json');

// Restore session from cookie if PHP session has expired
if (!isset($_SESSION['logged_in'])) {
	if (isset($_COOKIE['SessionCookie'])) {
		$cookie = $_COOKIE['SessionCookie'];
		$cookie = json_decode($cookie, true);
		if (is_array($cookie)) {
			// Merge, not replace -- see skulliance.php's own fix for why.
			$_SESSION = array_merge((array)$_SESSION, $cookie);
		}
	}
}

if (!isset($_SESSION['logged_in'])) {
	ob_end_clean();
	echo json_encode(['success' => false, 'message' => 'You must be logged in to connect a wallet.']);
	exit;
}

$completed = false;

register_shutdown_function(function() use (&$completed) {
	if (!$completed) {
		$captured = '';
		if (ob_get_level() > 0) {
			$captured = ob_get_clean();
		}
		$msg = strip_tags(trim($captured));
		if (empty($msg)) {
			$msg = 'Verification encountered an error. Please try again.';
		}
		echo json_encode(['success' => false, 'message' => $msg]);
	}
});

if (isset($_POST['stakeaddress'])) {
	checkUser($conn);
	$wallet_status = checkAddress($conn, $_POST['stakeaddress'], $_POST['address']);

	// Not a Cardano address at all. Says which chain's button to use rather
	// than "invalid address", because the likeliest way to get here is
	// reaching the Cardano path with an XRPL account in hand.
	if ($wallet_status === 'invalid') {
		$completed = true;
		ob_end_clean();
		echo json_encode([
			'success' => false,
			'message' => 'That is not a Cardano address. If it is an XRPL account, '
			           . 'use the XRPL option in the Connect Wallet window.'
		]);
		exit;
	}

	// A wallet already held by another account was silently reported as
	// connected: it never joined this user's list, and nothing said why.
	if ($wallet_status === 'exists_other') {
		$completed = true;
		ob_end_clean();
		echo json_encode([
			'success' => false,
			'message' => 'That wallet is already connected to a different Skulliance account. '
			           . 'Disconnect it there first, or connect a different wallet.'
		]);
		exit;
	}

	$addresses = getAddresses($conn);
	$policies = getPolicies($conn);
	$asset_ids = getNFTAssetIDs($conn);
	removeUser($conn, $_SESSION['userData']['user_id'], 1);   // Cardano: verifyNFTs() below restores only this chain
	verifyNFTs($conn, $addresses, $policies, $asset_ids);
	assignRole($_SESSION['userData']['discord_id'], "1119732763956871199");
	$completed = true;
	ob_end_clean();
	// Reconnecting a wallet already on the account is legitimate -- it is how a
	// user re-verifies after buying -- but calling it "connected" made people
	// think a second wallet had been added when it had not.
	echo json_encode([
		'success'  => true,
		'message'  => $wallet_status === 'exists_mine'
			? 'That wallet was already connected. Your NFTs have been re-verified.'
			: 'Wallet connected! Your NFTs have been verified and will begin accruing rewards nightly.',
		'redirect' => 'dashboard.php'
	]);
	exit;
}

/*
 * REFRESH CONNECTED WALLET(S) -- and it has to mean every chain, which it
 * did not. Reported from a real onboarding: connect Xaman, press refresh,
 * nothing appears, press it again, "Connection error", and now some CARDANO
 * NFTs are missing too. Three separate faults, all of them here.
 *
 *   1. IT ONLY EVER REFRESHED CARDANO. getAddresses() defaults to
 *      blockchain_id 1 and verifyNFTs() is the Cardano verifier, so an XRPL
 *      holder pressing a button labelled "Refresh Connected Wallet(s)" ran a
 *      pass that could not see their wallet. xrpl_verify_user() already
 *      existed, written for exactly this, and had no callers at all.
 *
 *   2. IT UNLINKED BEFORE IT COULD RESTORE. removeUser() cleared every
 *      Cardano NFT and trusted verifyNFTs() to put them back -- and that
 *      function retries each address up to four times against Koios, which
 *      on a phone is long enough for the gateway to give up. The request
 *      dies after the clear and before the rebuild, the browser shows
 *      "Connection error", and the NFTs are simply gone from the account
 *      until the nightly pass. verify-xrpl.php says this in its own header:
 *      "a cleared-then-partially-rebuilt table is not a smaller truth, it is
 *      a wrong one". The Cardano interactive path never got that treatment.
 *
 *   3. NOTHING CONNECTED STILL RAN THE WHOLE THING. No wallet is not an
 *      error and must not be a refresh either -- it was the most destructive
 *      case, because the clear still happened.
 *
 * A transaction around the clear-and-rebuild would also fix 2, and was not
 * used: verifyNFTs() spends minutes in an HTTP retry loop and holding row
 * locks on nfts for that long puts this in the way of the nightly job. The
 * snapshot below restores the same rows without holding anything.
 */
if (isset($_POST['refresh'])) {
	$uid     = (int) $_SESSION['userData']['user_id'];
	$cardano = getAddresses($conn, 1);
	$xrpl    = getAddresses($conn, XRPL_CHAIN_ID);

	/* SAY SO, AND TOUCH NOTHING. */
	if (!$cardano && !$xrpl) {
		$completed = true;
		ob_end_clean();
		echo json_encode([
			'success' => false,
			'message' => 'No wallet is connected yet, so there is nothing to refresh. '
			           . 'Connect one below -- your NFTs are verified as part of that.'
		]);
		exit;
	}

	$notes = array();

	/* XRPL FIRST, and through the function that already does it safely:
	   xrpl_verify_user() reads the whole ledger before it writes a row, so a
	   slow node leaves yesterday's rows standing instead of half of them. */
	if ($xrpl) {
		include_once 'verify-xrpl.php';
		$out = xrpl_verify_user($conn, $uid);
		if (!$out['ok'] && !empty($out['message'])) $notes[] = $out['message'];
	}

	if ($cardano) {
		/*
		 * THE SNAPSHOT IS THE SAFETY NET. Taken before the clear and put back
		 * by a shutdown handler if we never reach the end -- a timeout, a
		 * fatal, anything that stops the rebuild. `AND user_id = 0` so it can
		 * only ever undo OUR clear, never take a row verifyNFTs legitimately
		 * handed to somebody else.
		 *
		 * Restoring can re-link something sold since the last pass. That is
		 * the trade verify-xrpl.php already chose deliberately: yesterday's
		 * rows are right for everybody who has not traded, and the nightly
		 * pass corrects the rest. A missed refresh is recoverable; an account
		 * emptied by a timeout is what the player actually sees.
		 */
		$held = array();
		$snap = $conn->query("SELECT id FROM nfts WHERE user_id = $uid AND blockchain_id = 1");
		if ($snap) while ($row = $snap->fetch_assoc()) $held[] = (int) $row['id'];

		$cardano_done = false;
		register_shutdown_function(function () use ($conn, $uid, $held, &$cardano_done) {
			if ($cardano_done || !$held) return;
			$conn->query("UPDATE nfts SET user_id = $uid WHERE user_id = 0 AND id IN ("
			           . implode(',', $held) . ")");
			error_log('wallet refresh: Cardano rebuild did not finish for user ' . $uid
			        . '; restored ' . count($held) . ' NFT(s).');
		});

		removeUser($conn, $uid, 1);
		verifyNFTs($conn, $cardano, getPolicies($conn), getNFTAssetIDs($conn));
		$cardano_done = true;
	}

	$completed = true;
	ob_end_clean();
	echo json_encode([
		'success'  => true,
		'message'  => trim('Wallet(s) refreshed. Any newly acquired NFTs have been verified. '
		            . implode(' ', $notes)),
		'redirect' => 'dashboard.php'
	]);
	exit;
}

$completed = true;
ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
