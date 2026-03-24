<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$info          = getArmoryInfo($conn, $realm_id);
$armory_level  = $info['armory_level'];
$drops         = $info['drops_per_night'];
$soldiers      = $info['soldiers'];
$inventory     = $info['inventory'];
$logs          = getUnclaimedRealmLogs($conn, $realm_id, array('weapon', 'armor'));
$claim_types   = array('weapon', 'armor');

$per_page       = 8;
$total_soldiers = count($soldiers);
$total_pages    = max(1, ceil($total_soldiers / $per_page));
$page           = max(1, min($total_pages, intval($_GET['page'] ?? 1)));
$soldiers_page  = array_slice($soldiers, ($page - 1) * $per_page, $per_page);
?>
<div class="soldiers-stat-row">
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Armory Level</span>
        <span class="soldiers-stat-value"><?php echo $armory_level; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Gear Drops / Day</span>
        <span class="soldiers-stat-value"><?php echo $drops; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Armed Soldiers</span>
        <span class="soldiers-stat-value">
            <?php
            $armed = 0;
            foreach ($soldiers as $s) { if ($s['weapon_id'] || $s['armor_id']) $armed++; }
            echo $armed . ' / ' . count($soldiers);
            ?>
        </span>
    </div>
</div>
<p style="font-size:0.82rem;opacity:0.6;margin-top:12px;">
    The Armory generates <strong><?php echo $drops; ?> gear drop<?php echo $drops != 1 ? 's' : ''; ?> per day</strong>, distributed nightly
    into your inventory. Equip gear to your soldiers from the inventory below.
</p>

<?php include 'realm-log-panel.php'; ?>

<?php
$weapons = array_filter($inventory, fn($i) => $i['type'] === 'weapon');
$armors  = array_filter($inventory, fn($i) => $i['type'] === 'armor');
usort($weapons, fn($a,$b) => $b['weapon_level'] - $a['weapon_level']);
usort($armors,  fn($a,$b) => $b['armor_level']  - $a['armor_level']);
?>
<?php if (!empty($inventory)): ?>
<div style="margin-top:14px;display:flex;align-items:center;justify-content:space-between;">
    <strong style="font-size:0.85rem;">Gear Inventory</strong>
    <?php if (!empty($soldiers)): ?>
    <button class="small-button" onclick="autoEquipGear()">Auto-Equip All</button>
    <?php endif; ?>
</div>
<div class="gear-inventory-row" style="margin-top:8px;display:flex;gap:12px;">
    <?php foreach (array('Weapons' => $weapons, 'Armor' => $armors) as $label => $group): ?>
    <?php if (!empty($group)): ?>
    <div style="flex:1;min-width:0;">
        <div style="font-size:0.72rem;opacity:0.5;text-transform:uppercase;letter-spacing:0.04em;margin-bottom:5px;"><?php echo $label; ?></div>
        <div style="display:flex;flex-direction:column;gap:5px;">
        <?php foreach ($group as $item):
            $name      = $label === 'Weapons' ? $item['weapon_name'] : $item['armor_name'];
            $level     = $label === 'Weapons' ? $item['weapon_level'] : $item['armor_level'];
            $icon_file = strtolower(str_replace(' ', '-', $name)) . '.png';
        ?>
        <div style="display:flex;align-items:center;gap:7px;font-size:0.8rem;background:rgba(255,255,255,0.04);border-radius:6px;padding:5px 8px;">
            <img class="icon" src="icons/<?php echo $icon_file; ?>" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;" />
            <span style="flex:1;">Lv<?php echo $level; ?> <?php echo htmlspecialchars($name); ?></span>
            <span style="opacity:0.5;">×<?php echo $item['quantity']; ?></span>
        </div>
        <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php else: ?>
<p style="opacity:0.55;font-size:0.85rem;margin-top:12px;">No gear in inventory. Claim nightly drops to receive weapons and armor.</p>
<?php endif; ?>

<?php if (!empty($soldiers)): ?>
<div style="margin-top:14px;">
    <strong style="font-size:0.85rem;">Soldier Loadout</strong>
    <div id="armory-soldiers-grid" class="soldiers-grid armory-soldiers-grid" style="margin-top:8px;">
    <?php foreach ($soldiers_page as $s):
        $img_src   = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
        $loc_map       = array(1=>'Active Duty', 2=>'Tower', 3=>'On Raid');
        $loc_label     = $loc_map[intval($s['location'])] ?? 'Active Duty';
        $loc_class_map = array(1=>'status-ready', 2=>'status-deployed', 3=>'status-deployed');
        $status_class  = $loc_class_map[intval($s['location'])] ?? '';
    ?>
    <div class="soldier-card">
        <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" />
        <div class="soldier-name"><?php echo htmlspecialchars($s['nft_name']); ?></div>
        <div class="soldier-status <?php echo $status_class; ?>"><?php echo $loc_label; ?></div>
        <div class="soldier-gear-compact">
            <?php if ($s['weapon_id']): ?>
            <div class="gear-compact-slot" title="<?php echo htmlspecialchars($s['weapon_name']); ?>">
                <img class="icon" src="icons/<?php echo strtolower(str_replace(' ', '-', $s['weapon_name'])); ?>.png" onerror="this.src='icons/skull.png'" style="width:18px;height:18px;" />
                <span class="gear-label">Lv<?php echo $s['weapon_level']; ?></span>
            </div>
            <?php else: ?><div class="gear-compact-slot gear-compact-empty">—</div><?php endif; ?>
            <?php if ($s['armor_id']): ?>
            <div class="gear-compact-slot" title="<?php echo htmlspecialchars($s['armor_name']); ?>">
                <img class="icon" src="icons/<?php echo strtolower(str_replace(' ', '-', $s['armor_name'])); ?>.png" onerror="this.src='icons/skull.png'" style="width:18px;height:18px;" />
                <span class="gear-label">Lv<?php echo $s['armor_level']; ?></span>
            </div>
            <?php else: ?><div class="gear-compact-slot gear-compact-empty">—</div><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
    <?php if ($total_pages > 1): ?>
    <div style="display:flex;align-items:center;justify-content:center;gap:8px;margin-top:12px;font-size:0.82rem;">
        <?php if ($page > 1): ?>
        <button class="small-button" onclick="goArmoryPage(<?php echo $page - 1; ?>)">&#8249; Prev</button>
        <?php else: ?>
        <button class="small-button" disabled style="opacity:0.3;">&#8249; Prev</button>
        <?php endif; ?>
        <span style="opacity:0.6;"><?php echo $page; ?> / <?php echo $total_pages; ?></span>
        <?php if ($page < $total_pages): ?>
        <button class="small-button" onclick="goArmoryPage(<?php echo $page + 1; ?>)">Next &#8250;</button>
        <?php else: ?>
        <button class="small-button" disabled style="opacity:0.3;">Next &#8250;</button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>
<?php else: ?>
<p style="opacity:0.55;font-size:0.85rem;margin-top:12px;">No soldiers in Barracks yet. Enlist soldiers to receive gear drops.</p>
<?php endif; ?>
<?php $conn->close(); ?>
