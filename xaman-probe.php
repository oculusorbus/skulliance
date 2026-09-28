<?php
/* xaman-probe.php — CLI only. Answers one question: do the Xaman credentials
 * in credentials/db_credentials.php actually work?
 *
 *     php xaman-probe.php
 *
 * WHY THIS EXISTS. The first time a Xaman key is wrong you find out from the
 * Connect modal, which can only say "Could not reach Xaman" -- deliberately,
 * since a stranger clicking Connect must not be told whether the platform's
 * API secret is missing, malformed or revoked. That is right for the modal and
 * useless for setup, so the answer lives here instead, where the only person
 * reading it is the one who owns the key.
 *
 * It creates one real SignIn payload (the same call ajax/xaman-start.php
 * makes) and throws it away. Nothing is submitted to the ledger, nothing is
 * written here, and an unsigned payload expires on its own.
 *
 * Scan the QR it prints with Xaman to test the whole round trip.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/* Relative include inside db_credentials' neighbours resolves against the
   working directory, not this file. Same trap dhcf-rescore.php documents. */
chdir(__DIR__);

$cred = __DIR__ . '/credentials/db_credentials.php';
if (!is_file($cred)) {
	echo "No credentials/db_credentials.php here. Run this on the server.\n";
	exit(1);
}
require $cred;
require __DIR__ . '/xaman.php';

/* Say WHICH half is missing. "Credentials not configured" sends you looking at
   both, and pasting a key into the secret's slot is the likely mistake. */
$miss = array();
if (empty($xaman_api_key))    $miss[] = '$xaman_api_key';
if (empty($xaman_api_secret)) $miss[] = '$xaman_api_secret';
if ($miss) {
	echo "Not set in credentials/db_credentials.php: " . implode(' and ', $miss) . "\n\n";
	echo "  \$xaman_api_key    = \"...\";\n";
	echo "  \$xaman_api_secret = \"...\";\n\n";
	echo "Both are UUIDs from https://apps.xaman.dev (free). See multichain.md §4.\n";
	exit(1);
}

/* Both are UUIDs. Checking the shape before spending a call turns the commonest
   paste error -- a trailing space, or half a line -- into an instant answer
   rather than a generic 403 from Xaman. */
$uuid = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
foreach (array('$xaman_api_key' => $xaman_api_key, '$xaman_api_secret' => $xaman_api_secret) as $n => $v) {
	if (!preg_match($uuid, trim($v))) {
		printf("%s does not look like a UUID: %s\n", $n, substr($v, 0, 12) . '...');
		echo "Both credentials are UUIDs, e.g. 1a2b3c4d-5e6f-7081-92a3-b4c5d6e7f809.\n";
		exit(1);
	}
	if ($v !== trim($v)) { printf("%s has whitespace around it. Strip it.\n", $n); exit(1); }
}
if ($xaman_api_key === $xaman_api_secret) {
	echo "The key and the secret are the same value. One of them is pasted wrong.\n";
	exit(1);
}

echo "key    " . substr($xaman_api_key, 0, 8) . "...\n";
echo "secret " . substr($xaman_api_secret, 0, 8) . "...\n\n";

$start = xaman_start();
if (!$start) {
	echo "FAILED. Xaman rejected the call or could not be reached.\n\n";
	echo "The HTTP status is in the PHP error log -- 403 means the credentials are\n";
	echo "wrong or the application was deleted; a timeout means the network.\n";
	exit(1);
}

echo "WORKS. Xaman accepted the credentials and created a sign-in payload.\n\n";
printf("  uuid      %s\n", $start['uuid']);
printf("  qr        %s\n", $start['qr']);
printf("  deeplink  %s\n", $start['link']);
printf("  socket    %s\n\n", $start['websocket']);
echo "Open the QR in a browser and scan it with Xaman to test the round trip.\n";
echo "Ignoring it costs nothing -- an unsigned payload expires by itself.\n";
