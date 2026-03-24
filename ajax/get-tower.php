<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$tower_level = intval(getRealmLocationLevel($conn, $realm_id, 3));
$garrison    = getTowerGarrison($conn, $realm_id);

// Reserve soldiers available to assign to tower, sorted by combined gear level desc
$barracks_soldiers = getBarracksSoldiers($conn, $realm_id);
$reserved_ids = getReservedSoldierIds($conn, $realm_id);
$available_for_tower = array_values(array_filter($barracks_soldiers, function($s) use ($reserved_ids) {
    return intval($s['location']) == 1 && intval($s['trained']) == 1 && !in_array(intval($s['soldier_id']), $reserved_ids);
}));
usort($available_for_tower, function($a, $b) {
    $a_score = intval($a['weapon_level']) + intval($a['armor_level']);
    $b_score = intval($b['weapon_level']) + intval($b['armor_level']);
    return $b_score - $a_score;
});

$per_page    = 10;
$total_avail = count($available_for_tower);
$total_pages = max(1, ceil($total_avail / $per_page));
$page        = max(1, min($total_pages, intval($_GET['page'] ?? 1)));
$available_page = array_slice($available_for_tower, ($page - 1) * $per_page, $per_page);
?>
<div class="soldiers-stat-row">
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Tower Level</span>
        <span class="soldiers-stat-value"><?php echo $tower_level; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Garrison</span>
        <span class="soldiers-stat-value"><?php echo count($garrison); ?> / 10</span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Defense Bonus</span>
        <span class="soldiers-stat-value">+<?php echo min(count($garrison), 10); ?>%</span>
    </div>
</div>
<?php if (!empty($garrison)): ?>
<div style="display:flex;align-items:center;justify-content:space-between;margin-top:12px;margin-bottom:4px;">
    <strong style="font-size:0.85rem;">Garrison</strong>
    <button class="small-button" onclick="removeAllFromTower()">Remove All</button>
</div>
<?php endif; ?>
<div class="soldiers-grid" id="tower-garrison-grid">
<?php foreach ($garrison as $s):
    $img_src = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
?>
<div class="soldier-card garrison-card" data-soldier-id="<?php echo $s['soldier_id']; ?>">
    <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" />
    <div class="soldier-name"><?php echo htmlspecialchars($s['nft_name']); ?></div>
    <div class="soldier-status status-ready">Active Duty</div>
    <div class="soldier-gear-compact">
        <div class="gear-compact-slot" title="<?php echo $s['weapon_id'] ? htmlspecialchars($s['weapon_name']) : 'No Weapon'; ?>">
            <?php if ($s['weapon_id']): ?>
                <img class="icon" src="icons/<?php echo strtolower(str_replace(' ', '-', $s['weapon_name'])); ?>.png" onerror="this.src='icons/skull.png'" style="width:18px;height:18px;" />
                <span class="gear-label">Lv<?php echo intval($s['weapon_level']); ?></span>
            <?php else: ?><span class="gear-compact-empty">—</span><?php endif; ?>
        </div>
        <div class="gear-compact-slot" title="<?php echo $s['armor_id'] ? htmlspecialchars($s['armor_name']) : 'No Armor'; ?>">
            <?php if ($s['armor_id']): ?>
                <img class="icon" src="icons/<?php echo strtolower(str_replace(' ', '-', $s['armor_name'])); ?>.png" onerror="this.src='icons/skull.png'" style="width:18px;height:18px;" />
                <span class="gear-label">Lv<?php echo intval($s['armor_level']); ?></span>
            <?php else: ?><span class="gear-compact-empty">—</span><?php endif; ?>
        </div>
    </div>
    <button class="small-button" onclick="removeFromTower(<?php echo $s['soldier_id']; ?>)">Remove</button>
</div>
<?php endforeach; ?>
<?php if (empty($garrison)): ?>
<p style="opacity:0.55;font-size:0.85rem;">Tower is undefended. Add trained soldiers from your Barracks.</p>
<?php endif; ?>
</div>
<?php
$slots_remaining = 10 - count($garrison);
if ($slots_remaining > 0 && !empty($available_for_tower)):
?>
<div style="margin-top:16px;">
    <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:8px;">
        <strong style="font-size:0.85rem;">Add to Garrison:</strong>
        <div style="display:flex;align-items:center;gap:8px;">
            <span id="tower-select-count" style="font-size:0.8rem;opacity:0.65;">0 of <?php echo $slots_remaining; ?> slots selected</span>
            <button id="tower-select-all-btn" class="small-button" onclick="selectAllTower()">Select All</button>
        </div>
    </div>
    <div class="soldiers-grid" id="tower-available-grid" data-max="<?php echo $slots_remaining; ?>" style="margin-top:0;">
    <?php foreach ($available_page as $s):
        $img_src = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
    ?>
    <div class="soldier-card tower-pick" data-soldier-id="<?php echo $s['soldier_id']; ?>" onclick="toggleTowerSelect(this)">
        <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" />
        <div class="soldier-name"><?php echo htmlspecialchars($s['nft_name']); ?></div>
        <div class="soldier-status status-ready">Active Duty</div>
        <div class="soldier-gear-compact">
            <div class="gear-compact-slot" title="<?php echo $s['weapon_id'] ? htmlspecialchars($s['weapon_name']) : 'No Weapon'; ?>">
                <?php if ($s['weapon_id']): ?>
                    <img class="icon" src="icons/<?php echo strtolower(str_replace(' ', '-', $s['weapon_name'])); ?>.png" onerror="this.src='icons/skull.png'" style="width:18px;height:18px;" />
                    <span class="gear-label">Lv<?php echo $s['weapon_level']; ?></span>
                <?php else: ?><span class="gear-compact-empty">—</span><?php endif; ?>
            </div>
            <div class="gear-compact-slot" title="<?php echo $s['armor_id'] ? htmlspecialchars($s['armor_name']) : 'No Armor'; ?>">
                <?php if ($s['armor_id']): ?>
                    <img class="icon" src="icons/<?php echo strtolower(str_replace(' ', '-', $s['armor_name'])); ?>.png" onerror="this.src='icons/skull.png'" style="width:18px;height:18px;" />
                    <span class="gear-label">Lv<?php echo $s['armor_level']; ?></span>
                <?php else: ?><span class="gear-compact-empty">—</span><?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;font-size:0.82rem;">
        <?php if ($page > 1): ?>
        <button class="small-button" onclick="goTowerPage(<?php echo $page - 1; ?>)">&#8249; Prev</button>
        <?php else: ?>
        <button class="small-button" disabled style="opacity:0.3;">&#8249; Prev</button>
        <?php endif; ?>
        <span style="opacity:0.6;"><?php echo $page; ?> / <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
        <button class="small-button" onclick="goTowerPage(<?php echo $page + 1; ?>)">Next &#8250;</button>
        <?php else: ?>
        <button class="small-button" disabled style="opacity:0.3;">Next &#8250;</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:10px;display:flex;justify-content:space-between;align-items:center;">
        <button id="tower-clear-all-btn" class="small-button soldier-discharge-btn" onclick="clearAllTower()" style="display:none;">Clear All</button>
        <button class="button" onclick="deployToTower()">Deploy to Tower</button>
    </div>
</div>
<?php endif; ?>
<?php $conn->close(); ?>
