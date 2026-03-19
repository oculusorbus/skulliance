<?php
include '../db.php';
include '../webhooks.php';
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

        // Discord webhook — Boss Battle defeat (fires only when player health reaches 0)
        if ($health === 0) {
            $bbd_res = $conn->query("SELECT b.name, b.max_health, b.strength, b.speed, b.tactics, b.size, b.powerup, b.extension, b.theme, b.collection_id, p.currency, p.name AS project_name FROM bosses b INNER JOIN projects p ON p.id = b.project_id WHERE b.id='".$boss_id."'");
            if ($bbd_res && ($bbd_row = $bbd_res->fetch_assoc())) {
                $bbd_slug       = trim(strtolower(preg_replace(['/\s+/', '/\'/', '/[^a-z0-9\-]+/', '/-+/'], ['-', '', '-', '-'], $bbd_row['name'])), '-');
                $bbd_boss_url   = isset($_POST['bossImageUrl']) ? filter_var(trim($_POST['bossImageUrl']), FILTER_VALIDATE_URL) : false;
                $bbd_image_url  = $bbd_boss_url ?: "https://skulliance.io/staking/images/monstrocity/bosses/".$bbd_slug.".".$bbd_row['extension'];
                $bbd_enc_res    = $conn->query("SELECT damage_dealt, damage_taken FROM encounters WHERE user_id='".$user_id."' AND boss_id='".$boss_id."' AND reward=0 ORDER BY id DESC LIMIT 1");
                $bbd_dmg_dealt  = 0; $bbd_dmg_taken = 0;
                if ($bbd_enc_res && ($bbd_enc = $bbd_enc_res->fetch_assoc())) { $bbd_dmg_dealt = (int)$bbd_enc['damage_dealt']; $bbd_dmg_taken = (int)$bbd_enc['damage_taken']; }
                // Player's selected character image (passed from game client)
                $bbd_char_url   = isset($_POST['characterImageUrl']) ? filter_var(trim($_POST['characterImageUrl']), FILTER_VALIDATE_URL) : false;
                $bbd_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
                $bbd_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
                $bbd_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
                $bbd_avatar_url = ($bbd_discord && $bbd_avatar) ? "https://cdn.discordapp.com/avatars/".$bbd_discord."/".$bbd_avatar.".png" : "";
                $bbd_icon       = $bbd_char_url ?: $bbd_avatar_url;
                $bbd_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($bbd_username);
                $bbd_mention    = $bbd_discord ? "<@".$bbd_discord.">" : $bbd_username;
                $bbd_desc  = $bbd_mention." was **defeated by ".$bbd_row['name']."**!\n\n";
                $bbd_desc .= "💥 **Damage Dealt:** ".number_format($bbd_dmg_dealt)."\n";
                $bbd_desc .= "🩸 **Damage Taken:** ".number_format($bbd_dmg_taken)."\n";
                $bbd_desc .= "⚔️ **Strength:** ".$bbd_row['strength']."　💨 **Speed:** ".$bbd_row['speed']."　🧠 **Tactics:** ".$bbd_row['tactics']."　📐 **Size:** ".$bbd_row['size']."\n";
                $bbd_desc .= "✨ **Power-Up:** ".ucwords($bbd_row['powerup'])."\n";
                $bbd_desc .= "💰 **Bounty:** ".number_format($bbd_row['max_health'])." ".$bbd_row['currency'];
                $bbd_author = array("name" => $bbd_username, "icon_url" => $bbd_icon, "url" => $bbd_profile);
                discordmsg("💀 Defeated by: ".$bbd_row['name'], $bbd_desc, $bbd_image_url, "https://skulliance.io/staking/monstrocity.php", "bossbattles", $bbd_icon, "FF4444", $bbd_author);
            }
        }
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