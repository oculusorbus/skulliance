<?php
include '../db.php';
include '../skulliance.php';
header('Content-Type: application/json');

$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$boss_id = isset($_POST['boss_id']) ? (int)$_POST['boss_id'] : 0;
$health = isset($_POST['health']) ? (int)$_POST['health'] : 0;

if ($user_id <= 0 || $boss_id <= 0 || $health < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    exit;
}

// Check database connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Step 1: Fetch current boss health
$query = "SELECT health FROM bosses WHERE id = ?";
$stmt1 = $conn->prepare($query);
$stmt1->bind_param('i', $boss_id);
$stmt1->execute();
$stmt1->bind_result($current_health);
if ($stmt1->fetch()) {
    $damage_dealt = max(0, $current_health - $health); // Calculate damage dealt
} else {
    echo json_encode(['success' => false, 'error' => 'Boss not found']);
    $stmt1->close();
    $conn->close();
    exit;
}
$stmt1->free_result(); // Free the result set
$stmt1->close();       // Close the statement

// Step 2: Update boss health
$query = "UPDATE bosses SET health = ?, date_updated = CURRENT_TIMESTAMP WHERE id = ?";
$stmt2 = $conn->prepare($query);
$stmt2->bind_param('ii', $health, $boss_id);
$stmt2->execute();

if ($stmt2->affected_rows > 0) {
    // Step 3: Check for an existing encounter
    $query = "SELECT id, damage_dealt FROM encounters WHERE user_id = ? AND boss_id = ? AND reward = 0 ORDER BY id DESC LIMIT 1";
    $stmt3 = $conn->prepare($query);
    $stmt3->bind_param('ii', $user_id, $boss_id);
    $stmt3->execute();
    $stmt3->bind_result($encounter_id, $existing_damage_dealt);
    if ($stmt3->fetch()) {
        // Step 4a: Update existing encounter
        $new_damage_dealt = $existing_damage_dealt + $damage_dealt;
        $query = "UPDATE encounters SET damage_dealt = ?, date_created = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt4 = $conn->prepare($query);
        $stmt4->bind_param('ii', $new_damage_dealt, $encounter_id);
        $stmt4->execute();
        $stmt4->close();
    } else {
        // Step 4b: Insert new encounter
        $query = "INSERT INTO encounters (user_id, boss_id, damage_dealt, damage_taken, reward, date_created) 
                  VALUES (?, ?, ?, 0, 0, CURRENT_TIMESTAMP)";
        $stmt4 = $conn->prepare($query);
        $stmt4->bind_param('iii', $user_id, $boss_id, $damage_dealt);
        $stmt4->execute();
        $stmt4->close();
    }
    $stmt3->free_result(); // Free the result set
    $stmt3->close();       // Close the statement
    echo json_encode(['success' => true, 'message' => 'Boss health and encounters updated']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update boss health']);
}
$stmt2->close(); // Close the update statement

$conn->close(); // Close the database connection
?>