<?php
/**
 * ajax/get-raid-result.php
 * Returns all data needed to replay a completed raid in the portal animation.
 * GET: raid_id
 *
 * Returns JSON:
 *   success, raid_id, outcome (1=offense win, 2=defense win),
 *   perspective ('outgoing'|'incoming'), attacker{}, defender{},
 *   loot{amount, currency}, location_changes[]
 */
include '../db.php';
include '../skulliance.php';

header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) {
    echo json_encode(['success' => false]); exit;
}

$raid_id = intval($_GET['raid_id'] ?? 0);
if ($raid_id <= 0) {
    echo json_encode(['success' => false, 'error' => 'Invalid raid_id']); exit;
}

$uid = intval($_SESSION['userData']['user_id']);

// Verify user is involved in this raid and it's completed
$rr = $conn->query("
    SELECT r.id, r.offense_id, r.defense_id, r.outcome
    FROM raids r
    INNER JOIN realms ro ON ro.id = r.offense_id
    INNER JOIN realms rd ON rd.id = r.defense_id
    WHERE r.id = $raid_id
      AND r.outcome != 0
      AND (ro.user_id = $uid OR rd.user_id = $uid)
    LIMIT 1
");
if (!$rr || $rr->num_rows === 0) {
    echo json_encode(['success' => false, 'error' => 'Raid not found or not completed']); exit;
}
$rrow       = $rr->fetch_assoc();
$offense_id = intval($rrow['offense_id']);
$defense_id = intval($rrow['defense_id']);
$outcome    = intval($rrow['outcome']); // 1 = offense win, 2 = defense win

// Determine current user's perspective
$my_realm_id = getRealmID($conn);
$perspective = ($my_realm_id == $offense_id) ? 'outgoing' : 'incoming';

// ── Helpers ──────────────────────────────────────────────────────────────────

function buildRealmData($conn, $realm_id) {
    $realm_id = intval($realm_id);
    $res = $conn->query("SELECT name, theme_id FROM realms WHERE id = $realm_id LIMIT 1");
    if (!$res || $res->num_rows === 0) return null;
    $realm     = $res->fetch_assoc();
    $locations = getRealmLocationsWithShields($conn, $realm_id);
    return [
        'realm_id'  => $realm_id,
        'name'      => $realm['name'],
        'theme_id'  => $realm['theme_id'],
        'locations' => $locations,
    ];
}

function rrAbsoluteIPFS($ipfs, $collection_id, $project_id) {
    return getIPFS($ipfs, $collection_id, $project_id) ?: 'icons/skull.png';
}

// ── Attacking soldiers (all participants, offense side) ───────────────────────
$attack_soldiers = [];
$atk_sr = $conn->query("
    SELECT rs.soldier_id, COALESCE(rs.dead, 0) AS dead,
           nfts.name AS nft_name, nfts.ipfs, nfts.collection_id,
           projects.id AS project_id
    FROM raids_soldiers rs
    INNER JOIN soldiers s    ON s.id    = rs.soldier_id
    INNER JOIN nfts          ON nfts.id = s.nft_id
    INNER JOIN collections   ON collections.id  = nfts.collection_id
    INNER JOIN projects      ON projects.id     = collections.project_id
    WHERE rs.raid_id = $raid_id AND rs.side = 'offense'
");
if ($atk_sr) {
    while ($row = $atk_sr->fetch_assoc()) {
        $attack_soldiers[] = [
            'id'      => intval($row['soldier_id']),
            'name'    => $row['nft_name'],
            'img_url' => rrAbsoluteIPFS($row['ipfs'], $row['collection_id'], $row['project_id']),
            'dead'    => (bool)(int)$row['dead'],
        ];
    }
}

// ── Defending soldiers: full historical record from raids_soldiers ────────────
// All garrison soldiers (dead and alive) are written to raids_soldiers at raid
// resolution time, so getTowerGarrison is not needed and would include
// replacement soldiers added after the raid.
$defense_soldiers = [];
$def_sr = $conn->query("
    SELECT rs.soldier_id, rs.dead,
           nfts.name AS nft_name, nfts.ipfs, nfts.collection_id,
           projects.id AS project_id
    FROM raids_soldiers rs
    INNER JOIN soldiers s    ON s.id    = rs.soldier_id
    INNER JOIN nfts          ON nfts.id = s.nft_id
    INNER JOIN collections   ON collections.id  = nfts.collection_id
    INNER JOIN projects      ON projects.id     = collections.project_id
    WHERE rs.raid_id = $raid_id AND rs.side = 'defense'
");
if ($def_sr) {
    while ($row = $def_sr->fetch_assoc()) {
        $defense_soldiers[] = [
            'id'      => intval($row['soldier_id']),
            'name'    => $row['nft_name'],
            'img_url' => rrAbsoluteIPFS($row['ipfs'], $row['collection_id'], $row['project_id']),
            'dead'    => (bool)(int)$row['dead'],
        ];
    }
}

// ── Loot ──────────────────────────────────────────────────────────────────────
$loot = null;
$lr = $conn->query("
    SELECT rp.amount, p.currency, p.id AS project_id
    FROM raids_projects rp
    INNER JOIN projects p ON p.id = rp.project_id
    WHERE rp.raid_id = $raid_id
    LIMIT 1
");
if ($lr && $lr->num_rows > 0) {
    $lrow = $lr->fetch_assoc();
    $loot = [
        'amount'     => intval($lrow['amount']),
        'currency'   => $lrow['currency'],
        'project_id' => intval($lrow['project_id']),
    ];
}

// ── Location changes ──────────────────────────────────────────────────────────
$location_changes = [];
$locr = $conn->query("
    SELECT rl.amount, rl.type, rl.faction, l.name AS location_name
    FROM raids_locations rl
    INNER JOIN locations l ON l.id = rl.location_id
    WHERE rl.raid_id = $raid_id
");
if ($locr) {
    while ($row = $locr->fetch_assoc()) {
        $location_changes[] = [
            'faction'       => $row['faction'],
            'location_name' => $row['location_name'],
            'amount'        => intval($row['amount']),
            'type'          => $row['type'], // 'debit' or 'credit'
        ];
    }
}

// ── Assemble realm objects ────────────────────────────────────────────────────
$attacker = buildRealmData($conn, $offense_id);
$defender = buildRealmData($conn, $defense_id);
if ($attacker) $attacker['soldiers'] = $attack_soldiers;
if ($defender) $defender['soldiers'] = $defense_soldiers;

echo json_encode([
    'success'          => true,
    'raid_id'          => $raid_id,
    'outcome'          => $outcome,
    'perspective'      => $perspective,
    'attacker'         => $attacker,
    'defender'         => $defender,
    'loot'             => $loot,
    'location_changes' => $location_changes,
]);
$conn->close();
?>
