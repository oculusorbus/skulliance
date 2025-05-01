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

// Fetch current boss health to calculate damage dealt
$query = "SELECT health FROM bosses WHERE id = ?";
$stmt1 = $conn->prepare($query);
$stmt1->bind_param('i', $boss_id);
$stmt1->execute();
$stmt1->bind_result($current_health);
if ($stmt1->fetch()) {
    $damage_dealt = $current_health - $health; // Damage dealt by user
    if ($damage_dealt < 0) $damage_dealt = 0; // No negative damage (e.g., healing)
} else {
    echo json_encode(['success' => false, 'error' => 'Boss not found']);
    $stmt1->close();
    $conn->close();
    exit;
}
$stmt1->free_result(); // Free the result set
$stmt1->close();

// Update boss health
$query = "UPDATE bosses SET health = ?, date_updated = CURRENT_TIMESTAMP WHERE id = ?";
$stmt2 = $conn->prepare($query);
$stmt2->bind_param('ii', $health, $boss_id);
$stmt2->execute();

if ($stmt2->affected_rows > 0) {
    // Update encounters table
    $query = "SELECT id, damage_dealt FROM encounters WHERE user_id = ? AND boss_id = ? AND reward = 0 ORDER BY id DESC LIMIT 1";
    $stmt3 = $conn->prepare($query);
    $stmt3->bind_param('ii', $user_id, $boss_id);
    $stmt3->execute();
    $stmt3->bind_result($encounter_id, $existing_damage_dealt);
    if ($stmt3->fetch()) {
        // Update existing row with reward = 0
        $new_damage_dealt = $existing_damage_dealt + $damage_dealt;
        $query = "UPDATE encounters SET damage_dealt = ?, date_created = CURRENT_TIMESTAMP WHERE id = ?";
        $stmt4 = $conn->prepare($query);
        $stmt4->bind_param('ii', $new_damage_dealt, $encounter_id);
        $stmt4->execute();
        $stmt4->close();
    } else {
        // No row with reward = 0 exists, create a new one
        $query = "INSERT INTO encounters (user_id, boss_id, damage_dealt, damage_taken, reward, date_created) 
                  VALUES (?, ?, ?, 0, 0, CURRENT_TIMESTAMP)";
        $stmt4 = $conn->prepare($query);
        $stmt4->bind_param('iii', $user_id, $boss_id, $damage_dealt);
        $stmt4->execute();
        $stmt4->close();
    }
    $stmt3->free_result(); // Free the result set
    $stmt3->close();
    echo json_encode(['success' => true, 'message' => 'Boss health and encounters updated']);
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update boss health']);
}
$stmt2->close();

$conn->close();
?>