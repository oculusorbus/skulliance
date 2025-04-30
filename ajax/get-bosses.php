<?php
include '../db.php';
include '../skulliance.php';

function getBosses($conn) {
    // Set JSON header
    header('Content-Type: application/json');

    // Get user_id from session
    $userId = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
    if ($userId <= 0) {
        http_response_code(401);
        echo json_encode(['error' => 'User not authenticated']);
        return;
    }

    // SQL query to fetch bosses, player health, and player count
	$sql = "
        SELECT 
            p.name AS project_name,
            c.policy_id AS policy_id,
            b.id,
            b.name AS boss_name,
            b.health,
            b.max_health,
            b.strength,
            b.speed,
            b.tactics,
            b.size,
            b.powerup,
            b.bounty,
            b.currency,
            b.extension,
            h.health AS player_health
        FROM bosses b
        INNER JOIN projects p ON b.project_id = p.id
        INNER JOIN collections c ON b.policy_id = c.policy_id
        LEFT JOIN health h ON h.boss_id = b.id AND h.user_id = :userId
    ";

    try {
        // Prepare and execute query
		$stmt = $conn->prepare($sql);
        $stmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $stmt->execute();

        // Calculate participation multipliers
        $bosses = [];
        $maxPlayerCount = 1;
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($results as $row) {
            $maxPlayerCount = max($maxPlayerCount, $row['player_count']);
        }

        // Process results
        foreach ($results as $row) {
            // Calculate participation multiplier (1.0–2.0x)
            $multiplier = max(1.0, 2.0 - ($row['player_count'] / $maxPlayerCount));

            // Slugify boss name (e.g., "Dragon’s Wrath" -> "dragons-wrath")
            $slugifiedName = strtolower(preg_replace(
                ["/\s+/", "/\'/", "/[^a-z0-9\-]+/"], 
                ["-", "", "-"], 
                trim($row['boss_name'])
            ));

            $bosses[] = [
                'id' => (int)$row['id'],
                'projectName' => $row['project_name'],
                'policyId' => $row['policy_id'],
                'name' => $row['boss_name'],
                'health' => (int)$row['health'],
                'maxHealth' => (int)$row['max_health'],
                'strength' => (int)$row['strength'],
                'speed' => $row['speed'] ? (int)$row['speed'] : null,
                'tactics' => (int)$row['tactics'],
                'size' => $row['size'],
                'powerup' => $row['powerup'] ?: null,
                'bounty' => (int)$row['bounty'],
                'currency' => $row['currency'],
                'extension' => $row['extension'],
                'imageUrl' => "/images/monstrocity/bosses/{$slugifiedName}.{$row['extension']}",
                'playerHealth' => $row['player_health'] ? (int)$row['player_health'] : 1000, // Default if no record
                'playerCount' => (int)$row['player_count'],
                'participationMultiplier' => round($multiplier, 2),
                'canFight' => (bool)$row['can_fight']
            ];
        }

        // Output JSON
        echo json_encode($bosses);

    } catch (Exception $e) {
        // Handle errors
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
}

getBosses($conn);

// Close DB Connection
$conn->close();
?>