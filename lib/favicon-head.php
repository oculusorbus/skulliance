<?php
/**
 * The platform's icon links, in one place.
 *
 * WHY AN INCLUDE AND NOT A FOURTH PASTE: these tags were copied into
 * header.php, index.php and homepage.php, and the copies had already drifted
 * -- homepage.php carries absolute www URLs and is missing icon-192 and the
 * shortcut icon.
 *
 * AND EVERY PAGE THAT SKIPS header.php HAD NO ICON AT ALL. That is every
 * standalone game -- Monstrocity, Skull Swap, Skull Racer, Crypt Crawl,
 * Crypt Conquest, Realm Guardians, DHC Arena -- plus the gallery and the
 * error page, so the tab, the bookmark and the phone home screen all showed
 * the browser's blank page icon on exactly the pages a player keeps open
 * longest.
 *
 * ROOT-RELATIVE, NOT ABSOLUTE. The login cookie is host-only, so
 * www.skulliance.io and skulliance.io are separate sessions, and hardcoding
 * one host is the habit that breaks links between them. /staking/... resolves
 * on both.
 *
 * theme-color IS NOT HERE. It is genuinely per-page (#161616 in header.php,
 * #0e0f13 on the landing page, #07111d on the homepage) and folding it in
 * would quietly restyle three pages' browser chrome.
 *
 * homepage.php is deliberately NOT converted: WordPress includes it from
 * ABSPATH . 'staking/homepage.php' and serves it at the site root, so its
 * head is not ours to normalise.
 */
?>
  <link rel="apple-touch-icon" sizes="180x180" href="/staking/pwa/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/staking/pwa/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/staking/pwa/favicon-16.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/staking/pwa/icon-192.png">
  <link rel="shortcut icon" href="/staking/pwa/favicon-32.png">
