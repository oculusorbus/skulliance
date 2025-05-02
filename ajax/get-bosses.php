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

    // Initialize query variables for error logging
    $sql = '';
    $healthSql = '';
    $countSql = '';
    $nftsSql = '';

    try {
        // Query 1: Fetch boss data
        $sql = "
            SELECT 
                p.name AS project_name,
                c.policy AS policy,
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
                p.currency,
				b.theme,
				b.orientation,
                b.extension
            FROM bosses b
            INNER JOIN projects p ON b.project_id = p.id
            INNER JOIN collections c ON b.collection_id = c.id 
			ORDER BY b.project_id, b.max_health
        ";
        $result = $conn->query($sql);
        if (!$result) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        $results = $result->fetch_all(MYSQLI_ASSOC);

        // Check if no bosses exist
        if (empty($results)) {
            echo json_encode([]);
            return;
        }

		// Query 2: Fetch player health for the user
		$healthSql = "
		    SELECT boss_id, health
		    FROM health
		    WHERE user_id = ?
		";
		$healthStmt = $conn->prepare($healthSql);
		if (!$healthStmt) {
		    throw new Exception('Prepare failed: ' . $conn->error);
		}
		$healthStmt->bind_param('i', $userId);
		$healthStmt->execute();
		$healthStmt->bind_result($bossId, $health);
		$healthMap = [];
		while ($healthStmt->fetch()) {
		    // Only cast to int if health is not null
		    $healthMap[$bossId] = $health !== null ? (int)$health : null;
		}
		$healthStmt->close();

        // Query 3: Fetch player counts for participation multiplier
        $countSql = "
            SELECT 
                boss_id,
                COUNT(DISTINCT user_id) AS player_count
            FROM encounters
            WHERE reward = 0
            GROUP BY boss_id
        ";
        $countResult = $conn->query($countSql);
        if (!$countResult) {
            throw new Exception('Query failed: ' . $conn->error);
        }
        $playerCounts = $countResult->fetch_all(MYSQLI_ASSOC);

        // Build player count map
        $playerCountMap = [];
        $maxPlayerCount = 1;
        foreach ($playerCounts as $count) {
            $playerCountMap[$count['boss_id']] = (int)$count['player_count'];
            $maxPlayerCount = max($maxPlayerCount, $count['player_count']);
        }

        // Query 4: Fetch user’s eligible policies
        $nftsSql = "
            SELECT DISTINCT c.policy
            FROM nfts n
            INNER JOIN collections c ON n.collection_id = c.id
            WHERE n.user_id = ?
        ";
        $nftsStmt = $conn->prepare($nftsSql);
        if (!$nftsStmt) {
            throw new Exception('Prepare failed: ' . $conn->error);
        }
        $nftsStmt->bind_param('i', $userId);
        $nftsStmt->execute();
        $nftsStmt->bind_result($policy);
        $userPolicies = [];
        while ($nftsStmt->fetch()) {
            $userPolicies[] = $policy;
        }
        $nftsStmt->close();

        // Process results
        $bosses = [];
        foreach ($results as $row) {
            // Calculate participation multiplier (1.0–2.0x)
            $playerCount = isset($playerCountMap[$row['id']]) ? $playerCountMap[$row['id']] : 0;
            $multiplier = max(1.0, 2.0 - ($playerCount / $maxPlayerCount));

            // Slugify boss name (e.g., "Jack O'Treat" -> "jack-o-treat")
            $slugifiedName = strtolower($row['boss_name']);
            $slugifiedName = preg_replace(
                ["/\s+/", "/\'/", "/[^a-z0-9\-]+/", "/-+/"], 
                ["-", "", "-", "-"], 
                $slugifiedName
            );
            $slugifiedName = trim($slugifiedName, '-');

            // Check if player can fight (owns matching NFT)
            $canFight = in_array($row['policy'], $userPolicies);

            // Get player health (null if no record, to be set by NFT selection)
            $playerHealth = isset($healthMap[$row['id']]) ? $healthMap[$row['id']] : null;

            $bosses[] = [
                'id' => (int)$row['id'],
                'projectName' => $row['project_name'],
                'policy' => $row['policy'],
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
				'theme' => $row['theme'],
				'orientation' => ucfirst($row['orientation']),
                'extension' => $row['extension'],
                'imageUrl' => "images/monstrocity/bosses/{$slugifiedName}.{$row['extension']}",
				'battleDamagedUrl' => "images/monstrocity/bosses/battle-damaged/{$slugifiedName}.{$row['extension']}",
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
        error_log("SQL Error in getBosses: " . $e->getMessage() . "\nMain Query: " . $sql . "\nHealth Query: " . $healthSql . "\nCount Query: " . $countSql . "\nNFTs Query: " . $nftsSql);
        http_response_code(500);
        echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
    }
}

getBosses($conn);

// Close DB Connection
$conn->close();
?>