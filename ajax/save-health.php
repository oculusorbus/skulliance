<?php
include '../db.php';
include '../skulliance.php';
header('Content-Type: application/json');

// Check database connection
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'error' => 'Database connection failed']);
    exit;
}

// Get and sanitize POST data
$user_id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
$boss_id = isset($_POST['boss_id']) ? (int)$_POST['boss_id'] : 0;
$health = isset($_POST['health']) ? (int)$_POST['health'] : 0;

// Validate inputs
if ($user_id <= 0 || $boss_id <= 0 || $health < 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid input']);
    $conn->close();
    exit;
}

// Step 1: Check if health record exists
$query = "SELECT health FROM health WHERE user_id = " . $conn->real_escape_string($user_id) . " AND boss_id = " . $conn->real_escape_string($boss_id);
$result = $conn->query($query);
if ($result === false) {
    error_log("Query failed (check health): " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    $conn->close();
    exit;
}

$exists = false;
$current_health = 0;
if ($row = $result->fetch_assoc()) {
    $exists = true;
    $current_health = (int)$row['health'];
}
$result->free(); // Free the result set

$damage_taken = $exists ? max(0, $current_health - $health) : 0;

// Step 2: Update or insert health record
if ($exists) {
    $query = "UPDATE health SET health = " . $conn->real_escape_string($health) . ", date_updated = CURRENT_TIMESTAMP 
              WHERE user_id = " . $conn->real_escape_string($user_id) . " AND boss_id = " . $conn->real_escape_string($boss_id);
    if ($conn->query($query) === false) {
        error_log("Query failed (update health): " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        $conn->close();
        exit;
    }

    if ($conn->affected_rows > 0) {
        // Step 3: Check for an existing encounter
        $query = "SELECT id, damage_taken FROM encounters 
                  WHERE user_id = " . $conn->real_escape_string($user_id) . " 
                  AND boss_id = " . $conn->real_escape_string($boss_id) . " 
                  AND reward = 0 
                  ORDER BY id DESC LIMIT 1";
        $result = $conn->query($query);
        if ($result === false) {
            error_log("Query failed (select encounter): " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
            $conn->close();
            exit;
        }

        if ($row = $result->fetch_assoc()) {
            // Step 4a: Update existing encounter
            $encounter_id = (int)$row['id'];
            $existing_damage_taken = (int)$row['damage_taken'];
            $new_damage_taken = $existing_damage_taken + $damage_taken;

            $query = "UPDATE encounters SET damage_taken = " . $conn->real_escape_string($new_damage_taken) . ", 
                      date_created = CURRENT_TIMESTAMP 
                      WHERE id = " . $conn->real_escape_string($encounter_id);
            if ($conn->query($query) === false) {
                error_log("Query failed (update encounter): " . $conn->error);
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
                $conn->close();
                exit;
            }
        } else {
            // Step 4b: Insert new encounter
            $query = "INSERT INTO encounters (user_id, boss_id, damage_dealt, damage_taken, reward, date_created) 
                      VALUES (" . $conn->real_escape_string($user_id) . ", " . $conn->real_escape_string($boss_id) . ", 0, " . 
                      $conn->real_escape_string($damage_taken) . ", 0, CURRENT_TIMESTAMP)";
            if ($conn->query($query) === false) {
                error_log("Query failed (insert encounter): " . $conn->error);
                echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
                $conn->close();
                exit;
            }
        }
        $result->free();
        echo json_encode(['success' => true, 'message' => 'Health and encounters updated']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to update health']);
    }
} else {
    // Insert new health record
    $query = "INSERT INTO health (user_id, boss_id, health, date_created, date_updated) 
              VALUES (" . $conn->real_escape_string($user_id) . ", " . $conn->real_escape_string($boss_id) . ", " . 
              $conn->real_escape_string($health) . ", CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)";
    if ($conn->query($query) === false) {
        error_log("Query failed (insert health): " . $conn->error);
        echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
        $conn->close();
        exit;
    }

    if ($conn->affected_rows > 0) {
        // Initialize encounter
        $query = "INSERT INTO encounters (user_id, boss_id, damage_dealt, damage_taken, reward, date_created) 
                  VALUES (" . $conn->real_escape_string($user_id) . ", " . $conn->real_escape_string($boss_id) . ", 0, 0, 0, CURRENT_TIMESTAMP)";
        if ($conn->query($query) === false) {
            error_log("Query failed (init encounter): " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
            $conn->close();
            exit;
        }
        echo json_encode(['success' => true, 'message' => 'Health saved and encounters initialized']);
    } else {
        echo json_encode(['success' => false, 'error' => 'Failed to save health']);
    }
}

$conn->close();
?>