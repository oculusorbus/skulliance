<?php
/**
 * merch-verify.php
 * Standalone nightly verification for merch listings.
 * - Archives listings where the NFT has left the holder's wallet.
 * - Archives listings where the product was manually deleted on Printful.
 *
 * Run via cron independently of verify.php so merch issues can't affect
 * the core NFT/staking verification process.
 */
include_once 'db.php';
include_once 'webhooks.php';

verifyMerchListings($conn);

$conn->close();
?>
