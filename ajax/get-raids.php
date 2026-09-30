<?php
/**
 * ajax/get-raids.php
 * The raid lists, re-rendered. toggleSections('raids') swaps this in.
 *
 * ONE COPY. This file used to write out the same four blocks realms.php
 * did, in the same order, which is the fourth panel on that page to have
 * been duplicated -- and every one of the other three shipped a redesign
 * the player never saw, because the first refresh replaced it with the old
 * markup. The order and the markup live in realms-raids.php now.
 */
include '../db.php';
include '../webhooks.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }
if(!checkRealm($conn)){ exit; }

include __DIR__ . '/../realms-raids.php';

$conn->close();
