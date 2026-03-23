<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$info          = getArmoryInfo($conn, $realm_id);
$armory_level  = $info['armory_level'];
$drops         = $info['drops_per_night'];
$pending_drops = $info['pending_drops'];
$soldiers      = $info['soldiers'];
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
<?php if ($armory_level > 0): ?>
<div class="claim-panel" style="margin-top:14px;padding:12px;background:rgba(0,200,160,0.08);border:1px solid rgba(0,200,160,0.25);border-radius:8px;text-align:center;">
    <div style="font-size:0.82rem;opacity:0.7;margin-bottom:6px;">Gear Drops Ready</div>
    <div style="font-size:1.4rem;font-weight:700;color:#00c8a0;margin-bottom:10px;"><?php echo $pending_drops; ?> Drop<?php echo $pending_drops != 1 ? 's' : ''; ?></div>
    <?php if ($pending_drops > 0): ?>
    <button class="small-button" onclick="claimArmory()" style="background:#00c8a0;color:#000;">Claim Gear</button>
    <?php else: ?>
    <div style="font-size:0.8rem;opacity:0.5;">Nothing to claim yet — come back tomorrow!</div>
    <?php endif; ?>
</div>
<?php endif; ?>
<p style="font-size:0.82rem;opacity:0.6;margin-top:12px;">
    The Armory generates <strong><?php echo $drops; ?> gear drop<?php echo $drops != 1 ? 's' : ''; ?> per day</strong>
    and assigns each piece to your least-equipped trained soldier. Higher Armory levels unlock
    higher-tier weapons and armor.
</p>
<?php if (!empty($soldiers)): ?>
<div style="margin-top:14px;">
    <strong style="font-size:0.85rem;">Current Soldier Loadout</strong>
    <div class="soldiers-grid" style="margin-top:8px;">
    <?php foreach ($soldiers as $s):
        $img_src = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
        $loc_map = array(1=>'Reserve', 2=>'Tower', 3=>'Raid');
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
            </div>
            <?php else: ?><div class="gear-empty" style="font-size:0.72rem;">No Weapon</div><?php endif; ?>
            <?php if ($s['armor_id']): ?>
            <div class="soldier-gear-slot" title="<?php echo htmlspecialchars($s['armor_name']); ?>">
                <img class="icon" src="icons/armor/<?php echo strtolower(str_replace(' ', '-', $s['armor_name'])); ?>.png" onerror="this.src='icons/skull.png'" />
                <span class="gear-label" style="font-size:0.7rem;">Lv<?php echo $s['armor_level']; ?></span>
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
<?php $conn->close(); ?>
