<?php
header('Content-Type: application/json');
include '../db.php';
include '../skulliance.php';

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

if (isset($_SESSION['userData']['user_id'])) {
  $user_id = intval($_SESSION['userData']['user_id']);
  $project_id = 36;
  $data = json_decode(file_get_contents('php://input'), true);

  if (isset($data['currentLevel']) && isset($data['grandTotalScore'])) {
    try {
      $stmt = $conn->prepare("INSERT INTO progress (user_id, project_id, level, score) VALUES (?, ?, ?, ?) ON DUPLICATE KEY UPDATE level = ?, score = ?");
      $stmt->bind_param("iiiiii", $user_id, $project_id, $data['currentLevel'], $data['grandTotalScore'], $data['currentLevel'], $data['grandTotalScore']);
      $stmt->execute();
      $stmt->close();

      echo json_encode(['status' => 'success', 'message' => 'Progress saved']);
    } catch (Exception $e) {
      echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
  } else {
    echo json_encode(['status' => 'error', 'message' => 'Missing progress data']);
  }
} else {
  echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
}

$conn->close();
?>