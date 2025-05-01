<?php
include '../db.php'; // Adjust path to your database connection file
include '../skulliance.php';
header('Content-Type: application/json');

$boss_id = isset($_POST['boss_id']) ? (int)$_POST['boss_id'] : 0;
$health = isset($_POST['health']) ? (int)$_POST['health'] : 0;

if ($boss_id <= 0 || $health < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

$query = "UPDATE bosses SET health = ?, date_updated = CURRENT_TIMESTAMP WHERE id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $health, $boss_id);
$stmt->execute();

if ($stmt->affected_rows > 0) {
    echo json_encode(['success' => true, 'message' => 'Boss health updated']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update boss health']);
}

$stmt->close();
$conn->close();
?>