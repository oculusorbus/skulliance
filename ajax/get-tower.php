<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$tower_level = intval(getRealmLocationLevel($conn, $realm_id, 3));
$garrison    = getTowerGarrison($conn, $realm_id);

// Reserve soldiers available to assign to tower
$barracks_soldiers = getBarracksSoldiers($conn, $realm_id);
$available_for_tower = array_filter($barracks_soldiers, function($s) {
    return intval($s['location']) == 1 && intval($s['trained']) == 1;
});
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
        <span class="soldiers-stat-value">+<?php echo $tower_level; ?>%</span>
    </div>
</div>
<div class="soldiers-grid" id="tower-garrison-grid">
<?php foreach ($garrison as $s):
    $img_src = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
?>
<div class="soldier-card garrison-card" data-soldier-id="<?php echo $s['soldier_id']; ?>">
    <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" />
    <div class="soldier-name"><?php echo htmlspecialchars($s['nft_name']); ?></div>
    <div class="soldier-gear-row">
        <div class="soldier-gear-slot" title="Weapon">
            <?php if ($s['weapon_id']): ?>
                <img class="icon" src="icons/weapons/<?php echo strtolower(str_replace(' ', '-', $s['weapon_name'])); ?>.png" onerror="this.src='icons/skull.png'" />
                <span class="gear-label"><?php echo htmlspecialchars($s['weapon_name']); ?></span>
            <?php else: ?><span class="gear-empty">No Weapon</span><?php endif; ?>
        </div>
        <div class="soldier-gear-slot" title="Armor">
            <?php if ($s['armor_id']): ?>
                <img class="icon" src="icons/armor/<?php echo strtolower(str_replace(' ', '-', $s['armor_name'])); ?>.png" onerror="this.src='icons/skull.png'" />
                <span class="gear-label"><?php echo htmlspecialchars($s['armor_name']); ?></span>
            <?php else: ?><span class="gear-empty">No Armor</span><?php endif; ?>
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
            <button class="small-button" onclick="selectAllTower()">Select All</button>
        </div>
    </div>
    <div class="soldiers-grid" id="tower-available-grid" data-max="<?php echo $slots_remaining; ?>" style="margin-top:0;">
    <?php foreach ($available_for_tower as $s):
        $img_src = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
    ?>
    <div class="soldier-card tower-pick" data-soldier-id="<?php echo $s['soldier_id']; ?>" onclick="toggleTowerSelect(this)">
        <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" />
        <div class="soldier-name"><?php echo htmlspecialchars($s['nft_name']); ?></div>
        <?php if ($s['weapon_id']): ?>
        <div class="soldier-status" style="font-size:0.65rem;">Lv<?php echo $s['weapon_level']; ?> <?php echo htmlspecialchars($s['weapon_name']); ?></div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
    <div style="margin-top:10px;text-align:right;">
        <button class="button" onclick="deployToTower()">Deploy to Tower</button>
    </div>
</div>
<?php endif; ?>
<?php $conn->close(); ?>
