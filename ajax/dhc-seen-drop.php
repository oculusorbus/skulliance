<?php
/**
 * ajax/dhc-seen-drop.php
 * Clears the held reveal queue once the browser has actually shown it.
 *
 * Deliberately only called AFTER a reveal renders, never when the drop is
 * awarded: a trait the player never saw is still owed them, and the queue is
 * what makes sure the next page they open pays that debt. Clearing early would
 * quietly swallow exactly the case this exists for.
 *
 * Touches nothing but the session -- the ledger is the record, this is only
 * about whether a moment has been delivered.
 */
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../dhc-json.php';

if (session_status() === PHP_SESSION_ACTIVE) unset($_SESSION['dhcf_unseen']);
$conn->close();
dhc_json(array('ok' => true));
