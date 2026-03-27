<?php
include '../db.php';
include '../skulliance.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id    = intval($_SESSION['userData']['user_id']);
$auction_id = intval($_POST['auction_id'] ?? 0);

if (!$auction_id) { echo json_encode(['success'=>false,'message'=>'Invalid request.']); exit; }

$result = cancelAuction($conn, $auction_id, $user_id);
$conn->close();
echo json_encode($result);
