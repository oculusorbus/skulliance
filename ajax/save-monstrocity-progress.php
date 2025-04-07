<?php
header('Content-Type: application/json');
include '../db.php';
include '../skulliance.php';

// Set up logging to a file
$log_file = __DIR__ . '/debug.log';
function log_message($message) {
  global $log_file;
  file_put_contents($log_file, date('Y-m-d H:i:s') . " - " . $message . "\n", FILE_APPEND);
}

log_message("Starting save-monstrocity-progress.php");

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

if (isset($_SESSION['userData']['user_id'])) {
  $user_id = intval($_SESSION['userData']['user_id']);
  log_message("User ID: $user_id");

  $project_id = 36;
  $data = json_decode(file_get_contents('php://input'), true);
  log_message("Received data: " . print_r($data, true));

  if (isset($data['currentLevel']) && isset($data['grandTotalScore'])) {
    $level = intval($data['currentLevel']);
    $score = intval($data['grandTotalScore']);
    log_message("Level: $level, Score: $score");

    try {
      // Check for existing records
      $stmt = $conn->prepare("SELECT id FROM progress WHERE user_id = ? AND project_id = ?");
      $stmt->bind_param("ii", $user_id, $project_id);
      $stmt->execute();

      // Use bind_result and fetch instead of get_result
      $stmt->bind_result($id);
      $existing_records = [];
      while ($stmt->fetch()) {
        $existing_records[] = ['id' => $id];
      }
      $stmt->close();
      log_message("Existing records: " . count($existing_records));

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
          log_message("Deleted older records: " . print_r($delete_ids, true));
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
        $affected_rows = $stmt->affected_rows;
        $stmt->close();
        log_message("Updated record ID $keep_id, affected rows: $affected_rows");

        echo json_encode(['status' => 'success', 'message' => 'Progress updated']);
      } else {
        // Insert a new record
        $stmt = $conn->prepare("
          INSERT INTO progress (user_id, project_id, level, score, date_created)
          VALUES (?, ?, ?, ?, CURRENT_TIMESTAMP)
        ");
        $stmt->bind_param("iiii", $user_id, $project_id, $level, $score);
        $stmt->execute();
        $insert_id = $stmt->insert_id;
        $stmt->close();
        log_message("Inserted new record, ID: $insert_id");

        echo json_encode(['status' => 'success', 'message' => 'Progress saved']);
      }
    } catch (Exception $e) {
      log_message("Database error: " . $e->getMessage());
      echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
  } else {
    log_message("Missing progress data: " . print_r($data, true));
    echo json_encode(['status' => 'error', 'message' => 'Missing progress data']);
  }
} else {
  log_message("User not logged in");
  echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
}

$conn->close();
log_message("Finished save-monstrocity-progress.php");
?>