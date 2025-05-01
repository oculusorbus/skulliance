<?php
include '../db.php'; // Adjust path to your database connection file
include '../skulliance.php';
header('Content-Type: application/json');

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$boss_id = isset($_POST['boss_id']) ? (int)$_POST['boss_id'] : 0;

if ($user_id <= 0 || $boss_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid user_id or boss_id']);
    exit;
}

$query = "SELECT health FROM health WHERE user_id = ? AND boss_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $user_id, $boss_id);
$stmt->execute();
$stmt->bind_result($health);
if ($stmt->fetch()) {
    echo json_encode(['success' => true, 'health' => $health]);
} else {
    echo json_encode(['success' => true, 'health' => null]); // No record found
}
$stmt->close();
$conn->close();
?>