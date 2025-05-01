<?php
include '../db.php';
include '../skulliance.php';
header('Content-Type: application/json');

if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get POST data
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$boss_id = isset($_POST['boss_id']) ? (int)$_POST['boss_id'] : 0;
$health = isset($_POST['health']) ? (int)$_POST['health'] : 0;

// Validate input
if ($user_id <= 0 || $boss_id <= 0 || $health < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    $conn->close();
    exit;
}

// Check if row exists
$query = "SELECT id FROM health WHERE user_id = ? AND boss_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $user_id, $boss_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows > 0) {
    // Update existing row
    $query = "UPDATE health SET health = ?, date_updated = CURRENT_TIMESTAMP WHERE user_id = ? AND boss_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $health, $user_id, $boss_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Health updated']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update health']);
    }
} else {
    // Insert new row
    $query = "INSERT INTO health (user_id, boss_id, health, date_created, date_updated) VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $user_id, $boss_id, $health);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        echo json_encode(['success' => true, 'message' => 'Health saved']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save health']);
    }
}

$stmt->close();
$conn->close();
?>