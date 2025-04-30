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

    try {
        // Ensure native prepared statements
        $conn->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

        // Query 1: Fetch boss data (without player health)
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
                b.extension
            FROM bosses b
            INNER JOIN projects p ON b.project_id = p.id
            INNER JOIN collections c ON b.policy_id = c.policy_id
        ";
        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Query 2: Fetch player health for the user
        $healthSql = "
            SELECT boss_id, health
            FROM health
            WHERE user_id = :userId
        ";
        $healthStmt = $conn->prepare($healthSql);
        $healthStmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $healthStmt->execute();
        $healthResults = $healthStmt->fetchAll(PDO::FETCH_ASSOC);
        $healthMap = [];
        foreach ($healthResults as $healthRow) {
            $healthMap[$healthRow['boss_id']] = (int)$healthRow['health'];
        }

        // Query 3: Fetch player counts for participation multiplier
        $countSql = "
            SELECT 
                boss_id,
                COUNT(DISTINCT user_id) AS player_count
            FROM encounters
            WHERE rewarded = FALSE
            GROUP BY boss_id
        ";
        $countStmt = $conn->prepare($countSql);
        $countStmt->execute();
        $playerCounts = $countStmt->fetchAll(PDO::FETCH_ASSOC);

        // Build player count map
        $playerCountMap = [];
        $maxPlayerCount = 1;
        foreach ($playerCounts as $count) {
            $playerCountMap[$count['boss_id']] = (int)$count['player_count'];
            $maxPlayerCount = max($maxPlayerCount, $count['player_count']);
        }

        // Query 4: Fetch user’s eligible policy_ids
        $nftsSql = "
            SELECT DISTINCT c.policy_id
            FROM nfts n
            INNER JOIN collections c ON n.collection_id = c.id
            WHERE n.user_id = :userId
        ";
        $nftsStmt = $conn->prepare($nftsSql);
        $nftsStmt->bindValue(':userId', $userId, PDO::PARAM_INT);
        $nftsStmt->execute();
        $userPolicyIds = array_column($nftsStmt->fetchAll(PDO::FETCH_ASSOC), 'policy_id');

        // Process results
        $bosses = [];
        foreach ($results as $row) {
            // Calculate participation multiplier (1.0–2.0x)
            $playerCount = isset($playerCountMap[$row['id']]) ? $playerCountMap[$row['id']] : 0;
            $multiplier = max(1.0, 2.0 - ($playerCount / $maxPlayerCount));

            // Slugify boss name (e.g., "Dragon’s Wrath" -> "dragons-wrath")
            $slugifiedName = strtolower(preg_replace(
                ["/\s+/", "/\'/", "/[^a-z0-9\-]+/"],
                ["-", "", "-"],
                trim($row['boss_name'])
            ));

            // Check if player can fight (owns matching NFT)
            $canFight = in_array($row['policy_id'], $userPolicyIds);

            // Get player health
            $playerHealth = isset($healthMap[$row['id']]) ? $healthMap[$row['id']] : 1000;

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
                'playerHealth' => $playerHealth,
                'playerCount' => $playerCount,
                'participationMultiplier' => round($multiplier, 2),
                'canFight' => $canFight
            ];
        }

        // Output JSON
        echo json_encode($bosses);

    } catch (Exception $e) {
        // Log error for debugging
        error_log("SQL Error in getBosses: " . $e->getMessage() . "\nMain Query: " . $sql . "\nHealth Query: " . $healthSql);
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
}

getBosses($conn);

// Close DB Connection
$conn->close();
?>