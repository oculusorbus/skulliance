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

// Fetch current player health to calculate damage taken
$query = "SELECT health FROM health WHERE user_id = ? AND boss_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $user_id, $boss_id);
$stmt->execute();
$stmt->bind_result($current_health);
$exists = $stmt->fetch();
$stmt->close();

$damage_taken = $exists ? max(0, $current_health - $health) : 0; // Damage taken by player, 0 if new row

// Update or insert health row
if ($exists) {
    $query = "UPDATE health SET health = ?, date_updated = CURRENT_TIMESTAMP WHERE user_id = ? AND boss_id = ?";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $health, $user_id, $boss_id);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        // Update encounters table
        $query = "SELECT id, damage_taken FROM encounters WHERE user_id = ? AND boss_id = ? AND reward = 0 ORDER BY id DESC LIMIT 1";
        $stmt2 = $conn->prepare($query);
        $stmt2->bind_param('ii', $user_id, $boss_id);
        $stmt2->execute();
        $stmt2->bind_result($encounter_id, $existing_damage_taken);
        if ($stmt2->fetch()) {
            // Update existing row with reward = 0
            $new_damage_taken = $existing_damage_taken + $damage_taken;
            $query = "UPDATE encounters SET damage_taken = ?, date_created = CURRENT_TIMESTAMP WHERE id = ?";
            $stmt3 = $conn->prepare($query);
            $stmt3->bind_param('ii', $new_damage_taken, $encounter_id);
            $stmt3->execute();
            $stmt3->close();
        } else {
            // Create new row
            $query = "INSERT INTO encounters (user_id, boss_id, damage_dealt, damage_taken, reward, date_created) 
                      VALUES (?, ?, 0, ?, 0, CURRENT_TIMESTAMP)";
            $stmt3 = $conn->prepare($query);
            $stmt3->bind_param('iii', $user_id, $boss_id, $damage_taken);
            $stmt3->execute();
            $stmt3->close();
        }
        $stmt2->close();
        echo json_encode(['success' => true, 'message' => 'Health and encounters updated']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update health']);
    }
} else {
    $query = "INSERT INTO health (user_id, boss_id, health, date_created, date_updated) 
              VALUES (?, ?, ?, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
    $stmt = $conn->prepare($query);
    $stmt->bind_param('iii', $user_id, $boss_id, $health);
    $stmt->execute();
    if ($stmt->affected_rows > 0) {
        // New health row, initialize encounters
        $query = "INSERT INTO encounters (user_id, boss_id, damage_dealt, damage_taken, reward, date_created) 
                  VALUES (?, ?, 0, 0, 0, CURRENT_TIMESTAMP)";
        $stmt2 = $conn->prepare($query);
        $stmt2->bind_param('ii', $user_id, $boss_id);
        $stmt2->execute();
        $stmt2->close();
        echo json_encode(['success' => true, 'message' => 'Health saved and encounters initialized']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save health']);
    }
}

$stmt->close();
$conn->close();
?>