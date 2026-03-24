<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;

$valid_amounts = array('blitz', 'tactical', 'recon');
$valid_weapons = array('aggressive', 'balanced', 'stealth');
$valid_armors  = array('heavy', 'medium', 'light');

$amount = in_array($_POST['amount'] ?? '', $valid_amounts) ? $_POST['amount'] : 'tactical';
$weapon = in_array($_POST['weapon'] ?? '', $valid_weapons) ? $_POST['weapon'] : 'balanced';
$armor  = in_array($_POST['armor']  ?? '', $valid_armors)  ? $_POST['armor']  : 'medium';

$_SESSION['raidDeployConfig'] = array('amount' => $amount, 'weapon' => $weapon, 'armor' => $armor);
echo json_encode(array('success' => true));
?>
