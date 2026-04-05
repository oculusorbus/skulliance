<?php
include_once 'credentials/webhooks_credentials.php';
$webhook = getGauntletsWebhook();
echo "Webhook: " . $webhook . "\n";

$ch = curl_init($webhook);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json']);
curl_setopt($ch, CURLOPT_POST, 1);
curl_setopt($ch, CURLOPT_POSTFIELDS, '{"content":"test"}');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
$r = curl_exec($ch);
echo "Error: " . curl_error($ch) . "\n";
echo "Response: " . $r . "\n";
curl_close($ch);
