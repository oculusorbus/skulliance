<?php
include '../db.php';
include '../skulliance.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id   = intval($_SESSION['userData']['user_id']);
$raffle_id = intval($_POST['raffle_id'] ?? 0);

if (!$raffle_id) { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$result = cancelRaffle($conn, $raffle_id, $user_id);
$conn->close();
echo json_encode($result);
