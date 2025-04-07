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
    $level = intval($data['currentLevel']);
    $score = intval($data['grandTotalScore']);

    try {
      // Check for existing records
      $stmt = $conn->prepare("SELECT id FROM progress WHERE user_id = ? AND project_id = ?");
      $stmt->bind_param("ii", $user_id, $project_id);
      $stmt->execute();
      $result = $stmt->get_result();
      $existing_records = $result->fetch_all(MYSQLI_ASSOC);
      $stmt->close();

      if (count($existing_records) > 0) {
        // If multiple records exist, keep the most recent (based on id) and delete the rest
        if (count($existing_records) > 1) {
          // Sort by id descending to keep the most recent
          usort($existing_records, function($a, $b) {
            return $b['id'] - $a['id'];
          });
          $keep_id = $existing_records[0]['id'];
          $delete_ids = array_column(array_slice($existing_records, 1), 'id');
          
          // Delete older records
          $placeholders = implode(',', array_fill(0, count($delete_ids), '?'));
          $stmt = $conn->prepare("DELETE FROM progress WHERE id IN ($placeholders)");
          $stmt->bind_param(str_repeat('i', count($delete_ids)), ...$delete_ids);
          $stmt->execute();
          $stmt->close();
        } else {
          $keep_id = $existing_records[0]['id'];
        }

        // Update the existing record
        $stmt = $conn->prepare("
          UPDATE progress
          SET level = ?, score = ?, date_created = CURRENT_TIMESTAMP
          WHERE id = ?
        ");
        $stmt->bind_param("iii", $level, $score, $keep_id);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['status' => 'success', 'message' => 'Progress updated']);
      } else {
        // Insert a new record
        $stmt = $conn->prepare("
          INSERT INTO progress (user_id, project_id, level, score, date_created)
          VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->bind_param("iiii", $user_id, $project_id, $level, $score);
        $stmt->execute();
        $stmt->close();

        echo json_encode(['status' => 'success', 'message' => 'Progress saved']);
      }
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