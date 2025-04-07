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
    $stmt = $conn->prepare("SELECT level, score FROM progress WHERE user_id = ? AND project_id = ?");
    $stmt->bind_param("ii", $user_id, $project_id);
    $stmt->execute();

    // Use bind_result and fetch instead of get_result
    $stmt->bind_result($level, $score);
    $progress = null;
    if ($stmt->fetch()) {
      $progress = ['level' => $level, 'score' => $score];
    }
    $stmt->close();

    if ($progress) {
      echo json_encode(['status' => 'success', 'progress' => [
        'currentLevel' => $progress['level'],
        'grandTotalScore' => $progress['score']
      ]]);
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