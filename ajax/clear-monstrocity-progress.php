<?php
header('Content-Type: application/json');
include '../db.php';
include '../skulliance.php';

/*
 * THIS GOES TO THE ERROR LOG, NOT TO A FILE BESIDE THIS SCRIPT.
 *
 * It used to append to debug.log in this directory, which is inside the
 * document root and so was served to anyone who requested it -- 5.7MB of it,
 * appended on every save since April 2025, with no rotation and nothing ever
 * reading it. The contents were only levels and scores, but the shape of the
 * mistake is the one that also leaked a Discord token out of request.txt:
 * a debug sink written to a web-served path.
 *
 * error_log() lands in the same error_log the host already denies over HTTP,
 * so the call sites below keep working and stop being public.
 */
function log_message($message) {
  error_log('monstrocity-progress: ' . $message);
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