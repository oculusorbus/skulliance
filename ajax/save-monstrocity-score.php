<?php
header('Content-Type: application/json'); // Set headers first

include '../db.php';
include '../skulliance.php';

// Process JSON POST data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($_SESSION['userData']['user_id']) && isset($data['score']) && isset($data['level'])) {
  $user_id = intval($_SESSION['userData']['user_id']); // Sanitize user_id
  $result = saveMonstrocityScore($conn, $user_id, $data['score'], $data['level']);
  echo json_encode($result);
} else {
  echo json_encode(['status' => 'error', 'message' => 'User not logged in or missing score/level data']);
}

// Close DB Connection
$conn->close();
?>