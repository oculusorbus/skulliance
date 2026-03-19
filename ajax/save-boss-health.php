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

// Step 1: Fetch current boss health
$query = "SELECT health FROM bosses WHERE id = " . $conn->real_escape_string($boss_id);
$result = $conn->query($query);
if ($result === false) {
    error_log("Query failed (select boss health): " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    $conn->close();
    exit;
}

if ($row = $result->fetch_assoc()) {
    $current_health = (int)$row['health'];
    $damage_dealt = max(0, $current_health - $health); // Calculate damage dealt
} else {
    echo json_encode(['success' => false, 'error' => 'Boss not found']);
    $conn->close();
    exit;
}
$result->free(); // Free the result set

// Step 2: Update boss health
$query = "UPDATE bosses SET health = " . $conn->real_escape_string($health) . ", 
          date_updated = CURRENT_TIMESTAMP WHERE id = " . $conn->real_escape_string($boss_id);
if ($conn->query($query) === false) {
    error_log("Query failed (update boss health): " . $conn->error);
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
    $conn->close();
    exit;
}

if ($conn->affected_rows > 0) {
    // Step 3: Check for an existing encounter
    $query = "SELECT id, damage_dealt FROM encounters 
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
        $existing_damage_dealt = (int)$row['damage_dealt'];
        $new_damage_dealt = $existing_damage_dealt + $damage_dealt;

        $query = "UPDATE encounters SET damage_dealt = " . $conn->real_escape_string($new_damage_dealt) . ", 
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
                  VALUES (" . $conn->real_escape_string($user_id) . ", " . $conn->real_escape_string($boss_id) . ", " . 
                  $conn->real_escape_string($damage_dealt) . ", 0, 0, CURRENT_TIMESTAMP)";
        if ($conn->query($query) === false) {
            error_log("Query failed (insert encounter): " . $conn->error);
            echo json_encode(['success' => false, 'error' => 'Database error: ' . $conn->error]);
            $conn->close();
            exit;
        }
    }
    $result->free(); // Free the result set
    echo json_encode(['success' => true, 'message' => 'Boss health and encounters updated']);

    // Discord webhook — Boss Battle victory (fires only when this hit kills the boss)
    if ($health === 0) {
        $bb_res = $conn->query("SELECT b.name, b.max_health, b.strength, b.tactics, b.size, b.extension, b.theme, b.collection_id, p.currency, p.name AS project_name FROM bosses b INNER JOIN projects p ON p.id = b.project_id WHERE b.id='".$boss_id."'");
        if ($bb_res && ($bb_row = $bb_res->fetch_assoc())) {
            $bb_slug       = strtolower(preg_replace(['/\s+/', '/\'/', '/[^a-z0-9\-]+/', '/-+/'], ['-', '', '-', '-'], $bb_row['name']));
            $bb_image_url  = "https://skulliance.io/staking/images/monstrocity/bosses/".$bb_slug.".".$bb_row['extension'];
            $bb_enc_res    = $conn->query("SELECT damage_dealt FROM encounters WHERE user_id='".$user_id."' AND boss_id='".$boss_id."' AND reward=0 ORDER BY id DESC LIMIT 1");
            $bb_dmg        = 0;
            if ($bb_enc_res && ($bb_enc = $bb_enc_res->fetch_assoc())) $bb_dmg = (int)$bb_enc['damage_dealt'];
            // Player's NFT character from the boss's collection
            $bb_char_icon  = "";
            $bb_nft_res    = $conn->query("SELECT n.ipfs, n.collection_id FROM nfts n WHERE n.user_id='".$user_id."' AND n.collection_id='".$bb_row['collection_id']."' ORDER BY n.id DESC LIMIT 1");
            if ($bb_nft_res && ($bb_nft = $bb_nft_res->fetch_assoc())) {
                $bb_char_icon = getIPFS($bb_nft['ipfs'], $bb_nft['collection_id']);
            }
            $bb_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
            $bb_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
            $bb_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
            $bb_avatar_url = ($bb_discord && $bb_avatar) ? "https://cdn.discordapp.com/avatars/".$bb_discord."/".$bb_avatar.".png" : "";
            $bb_icon       = $bb_char_icon ?: $bb_avatar_url;
            $bb_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($bb_username);
            $bb_mention    = $bb_discord ? "<@".$bb_discord.">" : $bb_username;
            $bb_desc  = $bb_mention." **defeated ".$bb_row['name']."**!\n\n";
            $bb_desc .= "💥 **Damage Dealt:** ".number_format($bb_dmg)."\n";
            $bb_desc .= "⚔️ **Strength:** ".$bb_row['strength']."　🧠 **Tactics:** ".$bb_row['tactics']."　📐 **Size:** ".$bb_row['size']."\n";
            $bb_desc .= "💰 **Bounty:** ".number_format($bb_row['max_health'])." ".$bb_row['currency']."\n";
            $bb_desc .= "🎮 **Project:** ".$bb_row['project_name'];
            $bb_author = array("name" => $bb_username, "icon_url" => $bb_icon, "url" => $bb_profile);
            discordmsg("🏆 Boss Defeated: ".$bb_row['name'], $bb_desc, $bb_image_url, "https://skulliance.io/staking/monstrocity.php", "bossbattles", $bb_icon, "00C8A0", $bb_author);
        }
    }
} else {
    echo json_encode(['success' => false, 'error' => 'Failed to update boss health']);
}

$conn->close();
?>