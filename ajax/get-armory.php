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
$log_types     = array('weapon', 'armor');
$logs          = getUnclaimedRealmLogs($conn, $realm_id, $log_types);
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
<?php if ($armory_level > 0):
    if ($armory_level <= 2)      $tier_odds = array(1=>50, 2=>35, 3=>15);
    elseif ($armory_level <= 4)  $tier_odds = array(1=>35, 2=>25, 3=>20, 4=>12, 5=>8);
    elseif ($armory_level <= 6)  $tier_odds = array(1=>20, 2=>18, 3=>17, 4=>13, 5=>11, 6=>9, 7=>7, 8=>5);
    elseif ($armory_level <= 8)  $tier_odds = array(1=>12, 2=>14, 3=>14, 4=>13, 5=>11, 6=>10, 7=>9, 8=>8, 9=>5, 10=>4);
    else                         $tier_odds = array(1=>8,  2=>10, 3=>12, 4=>12, 5=>12, 6=>11, 7=>10, 8=>9, 9=>9, 10=>7);
    // Build tier → name maps
    $weapons_by_tier = array(); $armor_by_tier = array();
    $wr = $conn->query("SELECT level, name FROM weapons ORDER BY level ASC");
    while ($row = $wr->fetch_assoc()) $weapons_by_tier[intval($row['level'])] = $row['name'];
    $ar = $conn->query("SELECT level, name FROM armor ORDER BY level ASC");
    while ($row = $ar->fetch_assoc()) $armor_by_tier[intval($row['level'])] = $row['name'];
?>
<div style="margin-top:14px;">
    <strong style="font-size:0.85rem;">Drop Rates (Level <?php echo $armory_level; ?>)</strong>
    <table class="soldiers-table" style="width:100%;font-size:0.8rem;margin-top:8px;">
        <tr><th>~%</th><th>Weapon</th><th>Armor</th></tr>
        <?php foreach ($tier_odds as $tier => $pct): ?>
        <tr>
            <td><?php echo $pct; ?>%</td>
            <td><?php echo htmlspecialchars($weapons_by_tier[$tier] ?? 'Lv'.$tier); ?></td>
            <td><?php echo htmlspecialchars($armor_by_tier[$tier]  ?? 'Lv'.$tier); ?></td>
        </tr>
        <?php endforeach; ?>
    </table>
</div>
<?php endif; ?>

<?php if (!empty($inventory)): ?>
<div style="margin-top:14px;">
    <strong style="font-size:0.85rem;">Gear Inventory</strong>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:6px;">
    <?php foreach ($inventory as $item):
        $is_weapon = $item['type'] === 'weapon';
        $name      = $is_weapon ? $item['weapon_name'] : $item['armor_name'];
        $level     = $is_weapon ? $item['weapon_level'] : $item['armor_level'];
        $icon_dir  = $is_weapon ? 'weapons' : 'armor';
        $icon_file = strtolower(str_replace(' ', '-', $name)) . '.png';
    ?>
    <div style="display:flex;align-items:center;gap:8px;font-size:0.82rem;background:rgba(255,255,255,0.04);border-radius:6px;padding:6px 8px;">
        <img class="icon" src="icons/<?php echo $icon_dir; ?>/<?php echo $icon_file; ?>" onerror="this.src='icons/skull.png'" style="width:22px;height:22px;" />
        <span style="flex:1;">Lv<?php echo $level; ?> <?php echo htmlspecialchars($name); ?></span>
        <span style="opacity:0.5;margin-right:6px;">×<?php echo $item['quantity']; ?></span>
        <?php if (!empty($soldiers)): ?>
        <select style="font-size:0.75rem;background:#1a1a1a;color:#fff;border:1px solid rgba(255,255,255,0.2);border-radius:4px;padding:2px 4px;" id="equip-target-<?php echo $item['type'].'-'.$item['item_id']; ?>">
            <option value="">Equip to...</option>
            <?php foreach ($soldiers as $s): ?>
            <option value="<?php echo $s['soldier_id']; ?>"><?php echo htmlspecialchars($s['nft_name']); ?></option>
            <?php endforeach; ?>
        </select>
        <button class="small-button" style="padding:2px 8px;" onclick="equipGearItem('<?php echo $item['type']; ?>', <?php echo $item['item_id']; ?>)">Equip</button>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<?php if (!empty($soldiers)): ?>
<div style="margin-top:14px;">
    <strong style="font-size:0.85rem;">Soldier Loadout</strong>
    <div class="soldiers-grid" style="margin-top:8px;">
    <?php foreach ($soldiers as $s):
        $img_src   = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
        $loc_map   = array(1=>'Reserve', 2=>'Tower', 3=>'Raid');
        $loc_label = $loc_map[intval($s['location'])] ?? 'Reserve';
    ?>
    <div class="soldier-card">
        <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" />
        <div class="soldier-name"><?php echo htmlspecialchars($s['nft_name']); ?></div>
        <div class="soldier-status"><?php echo $loc_label; ?></div>
        <div class="soldier-gear-row" style="margin-top:4px;">
            <?php if ($s['weapon_id']): ?>
            <div class="soldier-gear-slot" title="<?php echo htmlspecialchars($s['weapon_name']); ?>">
                <img class="icon" src="icons/weapons/<?php echo strtolower(str_replace(' ', '-', $s['weapon_name'])); ?>.png" onerror="this.src='icons/skull.png'" />
                <span class="gear-label" style="font-size:0.7rem;">Lv<?php echo $s['weapon_level']; ?></span>
                <button class="small-button" style="font-size:0.65rem;padding:1px 5px;margin-top:2px;" onclick="unequipGearItem(<?php echo $s['soldier_id']; ?>, 'weapon')">-</button>
            </div>
            <?php else: ?><div class="gear-empty" style="font-size:0.72rem;">No Weapon</div><?php endif; ?>
            <?php if ($s['armor_id']): ?>
            <div class="soldier-gear-slot" title="<?php echo htmlspecialchars($s['armor_name']); ?>">
                <img class="icon" src="icons/armor/<?php echo strtolower(str_replace(' ', '-', $s['armor_name'])); ?>.png" onerror="this.src='icons/skull.png'" />
                <span class="gear-label" style="font-size:0.7rem;">Lv<?php echo $s['armor_level']; ?></span>
                <button class="small-button" style="font-size:0.65rem;padding:1px 5px;margin-top:2px;" onclick="unequipGearItem(<?php echo $s['soldier_id']; ?>, 'armor')">-</button>
            </div>
            <?php else: ?><div class="gear-empty" style="font-size:0.72rem;">No Armor</div><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
    </div>
</div>
<?php else: ?>
<p style="opacity:0.55;font-size:0.85rem;margin-top:12px;">No soldiers in Barracks yet. Enlist soldiers to receive gear drops.</p>
<?php endif; ?>
<?php include 'realm-log-panel.php'; ?>
<?php $conn->close(); ?>
