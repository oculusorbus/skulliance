<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$report = getPortalReport($conn, $realm_id);
$portal_level = $report['portal_level'];
$raids_allowed = $report['raids_allowed'];
$active_raids  = $report['active_raids'];
$deployed      = $report['deployed'];
$cap           = $report['cap'];

// Soldiers currently on raids (location=3)
$sql = "SELECT soldiers.id AS soldier_id, nfts.name AS nft_name, nfts.ipfs, nfts.collection_id,
               collections.project_id, soldiers.weapon_id, soldiers.armor_id,
               weapons.name AS weapon_name, weapons.level AS weapon_level,
               armor.name AS armor_name, armor.level AS armor_level
        FROM soldiers
        INNER JOIN nfts ON nfts.id = soldiers.nft_id
        INNER JOIN collections ON collections.id = nfts.collection_id
        LEFT JOIN weapons ON weapons.id = soldiers.weapon_id
        LEFT JOIN armor ON armor.id = soldiers.armor_id
        WHERE soldiers.realm_id = $realm_id AND soldiers.location = 3 AND soldiers.dead IS NULL AND soldiers.active = 1
        ORDER BY soldiers.id ASC";
$res = $conn->query($sql);
$raid_soldiers = array();
while ($row = $res->fetch_assoc()) $raid_soldiers[] = $row;

$per_page    = 10;
$total       = count($raid_soldiers);
$total_pages = max(1, ceil($total / $per_page));
$page        = max(1, min($total_pages, intval($_GET['page'] ?? 1)));
$page_soldiers = array_slice($raid_soldiers, ($page - 1) * $per_page, $per_page);
?>
<div class="soldiers-stat-row">
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Portal Level</span>
        <span class="soldiers-stat-value"><?php echo $portal_level; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Raids Allowed</span>
        <span class="soldiers-stat-value"><?php echo $raids_allowed; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Active Raids</span>
        <span class="soldiers-stat-value"><?php echo $active_raids; ?> / <?php echo $raids_allowed; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Soldiers Per Raid</span>
        <span class="soldiers-stat-value"><?php echo $portal_level; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Soldiers Deployed</span>
        <span class="soldiers-stat-value"><?php echo $deployed; ?> / <?php echo $cap; ?></span>
    </div>
</div>

<?php if (!empty($raid_soldiers)): ?>
<div style="margin-top:14px;">
    <strong style="font-size:0.85rem;">Soldiers on Raids</strong>
    <div class="soldiers-grid" id="portal-soldiers-grid" style="margin-top:8px;">
    <?php foreach ($page_soldiers as $s):
        $img_src = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
    ?>
    <div class="soldier-card">
        <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" />
        <div class="soldier-name"><?php echo htmlspecialchars($s['nft_name']); ?></div>
        <div class="soldier-status status-deployed">On Raid</div>
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
        <button class="small-button soldier-discharge-btn" onclick="dischargeSoldier(<?php echo $s['soldier_id']; ?>, 'portal')">Discharge</button>
    </div>
    <?php endforeach; ?>
    </div>
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;font-size:0.82rem;">
        <?php if ($page > 1): ?>
        <button class="small-button" onclick="goPortalPage(<?php echo $page - 1; ?>)">&#8249; Prev</button>
        <?php else: ?>
        <button class="small-button" disabled style="opacity:0.3;">&#8249; Prev</button>
        <?php endif; ?>
        <span style="opacity:0.6;"><?php echo $page; ?> / <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
        <button class="small-button" onclick="goPortalPage(<?php echo $page + 1; ?>)">Next &#8250;</button>
        <?php else: ?>
        <button class="small-button" disabled style="opacity:0.3;">Next &#8250;</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="raid-modal-footer" style="margin-top:16px;">
        <button class="small-button soldier-discharge-btn" onclick="dischargeAllSoldiers(3)">Discharge All</button>
    </div>
</div>
<?php else: ?>
<p style="opacity:0.55;font-size:0.85rem;margin-top:12px;">No soldiers currently on raids.</p>
<?php endif; ?>
<?php $conn->close(); ?>
