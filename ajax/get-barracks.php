<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

// Lazy training: mark soldiers as trained if their timer has elapsed
updateSoldierTraining($conn, $realm_id);
$soldiers       = getBarracksSoldiers($conn, $realm_id);
$cap            = getDeploymentCap($conn, $realm_id);
$barracks_level = intval(getRealmLocationLevel($conn, $realm_id, 4));
$training_hours = getBarracksTrainingHours($conn, $realm_id);
$training_days  = $training_hours > 0 ? $training_hours / 24 : 0;

$reserved_ids = getReservedSoldierIds($conn, $realm_id);
$loc_labels = array(1 => 'Active Duty', 2 => 'Tower', 3 => 'On Raid');
$now = time();

$slot_cost_total = 0;
foreach ($soldiers as $s) {
    $slot_cost_total += ($s['project_id'] > 7 && $s['project_id'] != 15) ? 2 : 1;
}

$per_page    = 10;
$total       = count($soldiers);
$total_pages = max(1, ceil($total / $per_page));
$page        = max(1, min($total_pages, intval($_GET['page'] ?? 1)));
$soldiers_page = array_slice($soldiers, ($page - 1) * $per_page, $per_page);
?>
<div class="soldiers-stat-row">
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Enlistment Slots Used</span>
        <span class="soldiers-stat-value"><?php echo $slot_cost_total; ?> / <?php echo $cap; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Training Time</span>
        <span class="soldiers-stat-value"><?php echo $training_days > 0 ? $training_days . 'd' : 'N/A'; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Barracks Level</span>
        <span class="soldiers-stat-value"><?php echo $barracks_level; ?></span>
    </div>
</div>
<div class="soldiers-grid" id="barracks-soldiers-grid">
<?php foreach ($soldiers_page as $s):
    $is_partner = ($s['project_id'] > 7 && $s['project_id'] != 15);
    $slot_cost  = $is_partner ? 2 : 1;
    $img_src    = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
    $trained    = intval($s['trained']);
    $location   = intval($s['location']);
    $ready_at   = strtotime($s['ready_at']);
    $seconds_left = $ready_at - $now;

    $is_reserved = in_array($s['soldier_id'], $reserved_ids);
    if ($trained) {
        if ($is_reserved) {
            $status_label = 'Reserved';
            $status_class = 'status-reserved';
        } else {
            $status_label = $loc_labels[$location] ?? 'Active Duty';
            $status_class = $location == 1 ? 'status-ready' : 'status-deployed';
        }
    } else {
        $status_label = 'Training';
        $status_class = 'status-training';
    }
?>
<div class="soldier-card" data-soldier-id="<?php echo $s['soldier_id']; ?>">
    <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" />
    <div class="soldier-name"><?php echo htmlspecialchars($s['nft_name']); ?></div>
    <?php if ($is_partner): ?><div class="soldier-badge partner-badge">2 slots</div><?php endif; ?>
    <div class="soldier-status <?php echo $status_class; ?>">
        <?php if (!$trained && $seconds_left > 0):
            $h = floor($seconds_left / 3600);
            $m = floor(($seconds_left % 3600) / 60);
            echo "Training: " . ($h > 0 ? $h . "h " : "") . $m . "m";
        else:
            echo $status_label;
        endif; ?>
    </div>
    <div class="soldier-gear-row" style="width:100%;">
        <?php if ($s['weapon_id']): ?>
        <div class="soldier-gear-slot">
            <span class="gear-label"><?php echo htmlspecialchars($s['weapon_name']); ?></span>
            <img class="icon" src="icons/<?php echo strtolower(str_replace(' ', '-', $s['weapon_name'])); ?>.png" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;" />
            <span class="gear-label">Lv<?php echo $s['weapon_level']; ?></span>
        </div>
        <?php else: ?><div class="gear-empty">No Weapon</div><?php endif; ?>
        <?php if ($s['armor_id']): ?>
        <div class="soldier-gear-slot">
            <span class="gear-label"><?php echo htmlspecialchars($s['armor_name']); ?></span>
            <img class="icon" src="icons/<?php echo strtolower(str_replace(' ', '-', $s['armor_name'])); ?>.png" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;" />
            <span class="gear-label">Lv<?php echo $s['armor_level']; ?></span>
        </div>
        <?php else: ?><div class="gear-empty">No Armor</div><?php endif; ?>
    </div>
    <button class="small-button soldier-discharge-btn" onclick="dischargeSoldier(<?php echo $s['soldier_id']; ?>)">Discharge</button>
</div>
<?php endforeach; ?>
<?php if (empty($soldiers)): ?>
<p style="opacity:0.55;font-size:0.85rem;">No soldiers enlisted. Click Enlist to add NFTs to your army.</p>
<?php endif; ?>
</div>
<?php if ($total_pages > 1): ?>
<div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;font-size:0.82rem;">
    <?php if ($page > 1): ?>
    <button class="small-button" onclick="goBarracksPage(<?php echo $page - 1; ?>)">&#8249; Prev</button>
    <?php else: ?>
    <button class="small-button" disabled style="opacity:0.3;">&#8249; Prev</button>
    <?php endif; ?>
    <span style="opacity:0.6;"><?php echo $page; ?> / <?php echo $total_pages; ?></span>
    <?php if ($page < $total_pages): ?>
    <button class="small-button" onclick="goBarracksPage(<?php echo $page + 1; ?>)">Next &#8250;</button>
    <?php else: ?>
    <button class="small-button" disabled style="opacity:0.3;">Next &#8250;</button>
    <?php endif; ?>
</div>
<?php endif; ?>
<div class="raid-modal-footer" style="margin-top:16px;">
    <button class="small-button" onclick="openEnlistPicker()">+ Enlist Soldiers</button>
    <button class="small-button" onclick="autoFillBarracks()">Auto-Enlist</button>
    <?php if (!empty($soldiers)): ?>
    <button class="small-button soldier-discharge-btn" onclick="dischargeAllSoldiers()">Discharge All</button>
    <?php endif; ?>
</div>
<?php $conn->close(); ?>
