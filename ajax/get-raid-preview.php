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

// Support two input modes:
// 1. raid_id  — retreat path: look up defense_id + soldier_ids from the raid record
// 2. defense_id + soldiers[] — raid launch path (existing behaviour)
$raid_id = intval($_GET['raid_id'] ?? 0);
if ($raid_id > 0) {
    $uid     = intval($_SESSION['userData']['user_id']);
    $rr      = $conn->query("SELECT r.offense_id, r.defense_id FROM raids r INNER JOIN realms ro ON ro.id = r.offense_id INNER JOIN realms rd ON rd.id = r.defense_id WHERE r.id = $raid_id AND (ro.user_id = $uid OR rd.user_id = $uid) LIMIT 1");
    if (!$rr || $rr->num_rows === 0) { echo json_encode(['success'=>false,'error'=>'Raid not found']); exit; }
    $rrow       = $rr->fetch_assoc();
    $offense_id = intval($rrow['offense_id']);
    $defense_id = intval($rrow['defense_id']);
    $sr         = $conn->query("SELECT soldier_id FROM raids_soldiers WHERE raid_id = $raid_id AND side = 'offense'");
    $soldier_ids = [];
    if ($sr) { while ($row = $sr->fetch_assoc()) $soldier_ids[] = intval($row['soldier_id']); }
} else {
    $defense_id  = intval($_GET['defense_id'] ?? 0);
    $soldier_ids = (isset($_GET['soldiers']) && is_array($_GET['soldiers']))
        ? array_map('intval', $_GET['soldiers']) : [];
    if ($defense_id <= 0) { echo json_encode(['success'=>false,'error'=>'Invalid defense_id']); exit; }
    $offense_id = getRealmID($conn);
    if (!$offense_id) { echo json_encode(['success'=>false,'error'=>'No realm']); exit; }
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
    return getIPFS($ipfs, $collection_id, $project_id) ?: 'icons/skull.png';
}

// ── Fetch soldiers first — before location queries touch the connection ──

// Attacking soldiers (by selected ID)
$attack_soldiers = [];
if (!empty($soldier_ids)) {
    $ids_safe = implode(',', $soldier_ids);
    $sol_res  = $conn->query("
        SELECT s.id, nfts.name AS nft_name, nfts.ipfs, nfts.collection_id, projects.id AS project_id
        FROM soldiers s
        INNER JOIN nfts         ON nfts.id         = s.nft_id
        INNER JOIN collections  ON collections.id  = nfts.collection_id
        INNER JOIN projects     ON projects.id     = collections.project_id
        WHERE s.id IN ($ids_safe) AND s.realm_id = $offense_id
    ");
    if ($sol_res) {
        while ($row = $sol_res->fetch_assoc()) {
            $attack_soldiers[] = [
                'id'      => intval($row['id']),
                'name'    => $row['nft_name'],
                'img_url' => absoluteIPFS($row['ipfs'], $row['collection_id'], $row['project_id']),
            ];
        }
        $sol_res->free();
    }
}

// Tower garrison — same function the Tower UI uses
$tower_soldiers = [];
foreach (getTowerGarrison($conn, $defense_id) as $row) {
    $tower_soldiers[] = [
        'id'      => intval($row['soldier_id']),
        'name'    => $row['nft_name'],
        'img_url' => absoluteIPFS($row['ipfs'], $row['collection_id'], $row['project_id']),
    ];
}

// ── Realm data (location + shield queries) ───────────────────
$attacker = buildRealmData($conn, $offense_id);
$defender = buildRealmData($conn, $defense_id);

if ($attacker) $attacker['soldiers'] = $attack_soldiers;
if ($defender) $defender['soldiers'] = $tower_soldiers;

echo json_encode([
    'success'  => true,
    'attacker' => $attacker,
    'defender' => $defender,
]);
$conn->close();
?>
