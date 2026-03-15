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
			$_SESSION = $cookie;
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
	checkAddress($conn, $_POST['stakeaddress'], $_POST['address']);
	$addresses = getAddresses($conn);
	$policies = getPolicies($conn);
	$asset_ids = getNFTAssetIDs($conn);
	removeUser($conn, $_SESSION['userData']['user_id']);
	verifyNFTs($conn, $addresses, $policies, $asset_ids);
	assignRole($_SESSION['userData']['discord_id'], "1119732763956871199");
	$completed = true;
	ob_end_clean();
	echo json_encode([
		'success'  => true,
		'message'  => 'Wallet connected! Your NFTs have been verified and will begin accruing rewards nightly.',
		'redirect' => 'dashboard.php'
	]);
	exit;
}

if (isset($_POST['refresh'])) {
	$asset_ids = getNFTAssetIDs($conn);
	removeUser($conn, $_SESSION['userData']['user_id']);
	verifyNFTs($conn, getAddresses($conn), getPolicies($conn), $asset_ids);
	$completed = true;
	ob_end_clean();
	echo json_encode([
		'success'  => true,
		'message'  => 'Wallet(s) refreshed. Any newly acquired NFTs have been verified.',
		'redirect' => 'dashboard.php'
	]);
	exit;
}

$completed = true;
ob_end_clean();
echo json_encode(['success' => false, 'message' => 'Invalid request.']);
