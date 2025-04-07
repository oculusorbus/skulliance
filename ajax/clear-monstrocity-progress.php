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

log_message("Starting clear-monstrocity-progress.php");

if (session_status() == PHP_SESSION_NONE) {
  session_start();
}

if (isset($_SESSION['userData']['user_id'])) {
  $user_id = intval($_SESSION['userData']['user_id']);
  $project_id = 36;
  log_message("User ID: $user_id");

  try {
    $stmt = $conn->prepare("DELETE FROM progress WHERE user_id = ? AND project_id = ?");
    $stmt->bind_param("ii", $user_id, $project_id);
    $stmt->execute();
    $affected_rows = $stmt->affected_rows;
    $stmt->close();
    log_message("Deleted records, affected rows: $affected_rows");

    echo json_encode(['status' => 'success', 'message' => 'Progress cleared']);
  } catch (Exception $e) {
    log_message("Database error: " . $e->getMessage());
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
  }
} else {
  log_message("User not logged in");
  echo json_encode(['status' => 'error', 'message' => 'User not logged in']);
}

$conn->close();
log_message("Finished clear-monstrocity-progress.php");
?>