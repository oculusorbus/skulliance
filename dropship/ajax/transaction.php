<?php
include '../webhooks.php';
include '../role.php';

// Same gate as dropship/db.php (duplicated, not shared -- see that file's
// comment for why): this endpoint verifies a real ADA transaction and
// grants Discord VIP roles off $_SESSION['userData']['discord_id'], so it
// needs the same login check the rest of Drop Ship gets via db.php, not a
// bare session_start() trusting whatever's already there.
if (isset($_COOKIE[session_name()]) || isset($_COOKIE['SessionCookie'])) {
	session_start();
}
if (!isset($_SESSION['logged_in'])) {
	$cookie = isset($_COOKIE['SessionCookie']) ? json_decode($_COOKIE['SessionCookie'], true) : null;
	if (is_array($cookie)) {
		$_SESSION = array_merge((array)$_SESSION, $cookie);
	} else {
		header('Location: ../../error.php');
		exit();
	}
}

// Rewritten 2026-09 -- the original version of this endpoint had three
// separate bugs that together made verification a near coin flip:
//
// 1. The retry loop was dead code. The intended shape (poll every 5s for
//    up to 2 minutes) was all there -- sleep(5), a commented-out
//    `if($counter == 24)` -- but the "timed out" echo/exit that was
//    supposed to be INSIDE that condition sat outside it instead, firing
//    unconditionally after the very first pass. So this checked once,
//    immediately, and gave up for good -- clicking Verify a few seconds
//    too early after sending a payment failed permanently, no matter how
//    long you waited afterward.
// 2. Only the first ~10 transactions Koios returned got checked, with no
//    guarantee a brand-new payment was anywhere near the front of that
//    list for a busy address.
// 3. It assumed the LAST output in a transaction was always the payment
//    output. Wallets order change vs. payment outputs differently, so a
//    genuinely correct payment could put the real payment output
//    somewhere else and never match at all.
//
// Fixed: a real retry loop (up to $max_attempts, actually waiting between
// them), scanning the most recent transactions first (not an arbitrary
// early slice), and checking every output in a transaction instead of
// guessing at one. Matching is now by the EXACT expected lovelace total
// (not a substring "does this number appear anywhere" check), computed
// from the same unique per-session code discoin.php already generates.
set_time_limit(150); // longer than the ~120s the retry loop can take

$discoin_policy_id = "5612bee388219c1b76fd527ed0fa5aa1d28652838bcab4ee4ee63197";
$discoin_address = "addr1q9spjm8huu3svyh286wcrfs8hvv2pa0rlewk5zsj308wwduf9vr444v7z8xktt4l5z20f6dv2yujs9z6gc3hxzqjunqsrl06ny";
$discoin_min_quantity = 100000000000; // 1,000 DISCOIN at 8 decimals, per discoin.php's own instructions

// discoin.php tells the player to send "1.XXXXXX ADA", where XXXXXX is
// this session's own random 6-digit disambiguation code -- so the exact
// lovelace total to look for is 1,000,000 (1 ADA) plus that code.
$expected_lovelace = 1000000 + intval($_SESSION['userData']['transaction']);

// True if any output in this transaction pays the expected exact ADA
// amount together with enough DISCOIN. Checks every output, not just one
// guessed position.
function dropshipTxHasDiscoinPayment($tx_response, $expected_lovelace, $discoin_policy_id, $discoin_min_quantity) {
	if (!isset($tx_response[0]->outputs) || !is_array($tx_response[0]->outputs)) return false;
	foreach ($tx_response[0]->outputs as $output) {
		if (!isset($output->value) || intval($output->value) != $expected_lovelace) continue;
		if (!isset($output->asset_list) || !is_array($output->asset_list)) continue;
		foreach ($output->asset_list as $asset) {
			$policy_id = $asset->policy_id ?? '';
			$quantity = intval($asset->quantity ?? 0);
			if ($policy_id === $discoin_policy_id && $quantity >= $discoin_min_quantity) {
				return true;
			}
		}
	}
	return false;
}

$max_attempts = 24; // ~2 minutes total at 5s apart -- same window the original code's commented-out counter implied
$verified = false;

for ($attempt = 0; $attempt < $max_attempts && !$verified; $attempt++) {
	if ($attempt > 0) sleep(5);

	$ch = curl_init("https://api.koios.rest/api/v0/address_txs");
	curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
	curl_setopt( $ch, CURLOPT_POST, 1);
	curl_setopt( $ch, CURLOPT_POSTFIELDS, '{"_addresses":["'.$discoin_address.'"],"_after_block_height":6238675}');
	curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt( $ch, CURLOPT_HEADER, 0);
	curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
	$response = curl_exec( $ch );
	curl_close( $ch );
	$response = json_decode($response);

	if (!is_array($response)) continue;

	// Koios returns address_txs oldest-first, so a brand-new payment is
	// always near the END of the raw list -- reverse so "check the most
	// recent activity" actually means the most recent activity. Still
	// capped (not the whole address history every 5 seconds), but a lot
	// more generous than the original 10, and now looking at the right
	// end of the list.
	$recent = array_slice(array_reverse($response), 0, 40);

	foreach ($recent as $tx_ref) {
		if (!isset($tx_ref->tx_hash)) continue;

		$ch = curl_init("https://api.koios.rest/api/v0/tx_info");
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		curl_setopt( $ch, CURLOPT_POST, 1);
		curl_setopt( $ch, CURLOPT_POSTFIELDS, '{"_tx_hashes":["'.$tx_ref->tx_hash.'"]}');
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		$tx_response = curl_exec( $ch );
		curl_close( $ch );
		$tx_response = json_decode($tx_response);

		if (dropshipTxHasDiscoinPayment($tx_response, $expected_lovelace, $discoin_policy_id, $discoin_min_quantity)) {
			$verified = true;
			break;
		}
	}
}

if ($verified) {
	// Assign VIP role
	assignRole($_SESSION['userData']['discord_id'], "966399108011163678");
	// Assign Disco role
	assignRole($_SESSION['userData']['discord_id'], "966399231671812106");
	// Assign Disco VIP role
	assignRole($_SESSION['userData']['discord_id'], "966399671184556052");
	$_SESSION['userData']['VIP'] = 1;
	echo "Your transaction was successfully verified. You have now been assigned temporary VIP status in Discord and can participate in the Oculus Lounge game.";
} else {
	echo "The verification timed out. Please hit the verify transaction button to try again.";
}
