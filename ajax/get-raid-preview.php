<?php
/**
 * ajax/get-raid-preview.php
 * Returns realm data for both the attacker and defender needed to render
 * the raid launch animation. Called before / concurrent with start_raid.php.
 * GET: defense_id, soldiers[] (array of soldier IDs being deployed)
 */
include '../db.php';
include '../skulliance.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['success' => false]); exit;
}

$defense_id  = intval($_GET['defense_id'] ?? 0);
$soldier_ids = (isset($_GET['soldiers']) && is_array($_GET['soldiers']))
    ? array_map('intval', $_GET['soldiers']) : [];

if ($defense_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid defense_id']); exit;
}

$offense_id = getRealmID($conn);
if (!$offense_id) {
    echo json_encode(['success' => false, 'error' => 'No realm']); exit;
}

// ── Helper: build realm data ─────────────────────────────────
function buildRealmData($conn, $realm_id) {
    $realm_id = intval($realm_id);
    $res = $conn->query("SELECT r.name, r.theme_id FROM realms r WHERE r.id = $realm_id LIMIT 1");
    if (!$res || $res->num_rows === 0) return null;
    $realm = $res->fetch_assoc();

    $locations = getRealmLocationsWithShields($conn, $realm_id);
    foreach ($locations as &$loc) {
        $loc['icon'] = 'icons/locations/' . $loc['name'] . '.png';
    }
    unset($loc);

    return [
        'realm_id'  => $realm_id,
        'name'      => $realm['name'],
        'theme_id'  => $realm['theme_id'],
        'locations' => $locations,
    ];
}

// ── Helper: fully-qualified image URL ───────────────────────
function absoluteIPFS($ipfs, $collection_id, $project_id) {
    $url = getIPFS($ipfs, $collection_id, $project_id);
    if ($url && $url[0] === '/') $url = 'https://skulliance.io/staking' . $url;
    return $url ?: 'icons/skull.png';
}

// ── Attacker realm ───────────────────────────────────────────
$attacker = buildRealmData($conn, $offense_id);

// Selected attacking soldiers
$attack_soldiers = [];
if (!empty($soldier_ids) && $attacker) {
    $ids_safe = implode(',', $soldier_ids);
    $sol_res  = $conn->query("
        SELECT s.id, nfts.name AS nft_name, nfts.ipfs, nfts.collection_id, projects.id AS project_id
        FROM soldiers s
        INNER JOIN nfts         ON nfts.id         = s.nft_id
        INNER JOIN collections  ON collections.id  = nfts.collection_id
        INNER JOIN projects     ON projects.id     = collections.project_id
        WHERE s.id IN ($ids_safe) AND s.realm_id = $offense_id
    ");
    if ($sol_res) while ($row = $sol_res->fetch_assoc()) {
        $attack_soldiers[] = [
            'id'      => intval($row['id']),
            'name'    => $row['nft_name'],
            'img_url' => absoluteIPFS($row['ipfs'], $row['collection_id'], $row['project_id']),
        ];
    }
}
if ($attacker) $attacker['soldiers'] = $attack_soldiers;

// ── Defender realm ───────────────────────────────────────────
$defender = buildRealmData($conn, $defense_id);

// Tower soldiers (location = 2)
$tower_soldiers = [];
if ($defender) {
    $sol_res = $conn->query("
        SELECT s.id, nfts.name AS nft_name, nfts.ipfs, nfts.collection_id, projects.id AS project_id
        FROM soldiers s
        INNER JOIN nfts         ON nfts.id         = s.nft_id
        INNER JOIN collections  ON collections.id  = nfts.collection_id
        INNER JOIN projects     ON projects.id     = collections.project_id
        WHERE s.realm_id = $defense_id AND s.location = 2 AND s.active = 1 AND s.dead IS NULL
        LIMIT 12
    ");
    if ($sol_res) while ($row = $sol_res->fetch_assoc()) {
        $tower_soldiers[] = [
            'id'      => intval($row['id']),
            'name'    => $row['nft_name'],
            'img_url' => absoluteIPFS($row['ipfs'], $row['collection_id'], $row['project_id']),
        ];
    }
}
if ($defender) $defender['soldiers'] = $tower_soldiers;

echo json_encode([
    'success'  => true,
    'attacker' => $attacker,
    'defender' => $defender,
]);
$conn->close();
?>
