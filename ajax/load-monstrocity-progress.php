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

  try {
    $stmt = $conn->prepare("SELECT level, score FROM monstrocity_progress WHERE user_id = ? AND project_id = ?");
    $stmt->bind_param("ii", $user_id, $project_id);
    $stmt->execute();
    $stmt->bind_result($current_level, $grand_total_score);
    $has_result = $stmt->fetch();
    $stmt->close();

    if ($has_result) {
      echo json_encode([
        'status' => 'success',
        'progress' => [
          'currentLevel' => $current_level,
          'grandTotalScore' => $grand_total_score
        ]
      ]);
    } else {
      echo json_encode(['status' => 'success', 'progress' => null]);
    }
  } catch (Exception $e) {
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
  }
} else {
  echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
}

$conn->close();
?>