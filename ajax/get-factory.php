<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$info = getFactoryInfo($conn, $realm_id);
$logs        = getUnclaimedRealmLogs($conn, $realm_id, array('consumable'));
$claim_types = array('consumable');
$amounts     = getCurrentAmounts($conn);
$con_names = array(
    1 => '100% Success', 2 => '75% Success', 3 => '50% Success',
    4 => '25% Success',  5 => 'Fast Forward', 6 => 'Double Rewards', 7 => 'Random Reward'
);
?>
<div class="soldiers-stat-row">
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Factory Level</span>
        <span class="soldiers-stat-value"><?php echo $info['factory_level']; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Items Per Day</span>
        <span class="soldiers-stat-value"><?php echo $info['drops_per_night']; ?></span>
    </div>
</div>
<p style="font-size:0.82rem;opacity:0.6;margin-top:12px;">
    The Factory generates <strong><?php echo $info['drops_per_night']; ?> consumable item<?php echo $info['drops_per_night'] != 1 ? 's' : ''; ?> per day</strong>, distributed nightly.
    Higher Factory levels unlock better drop odds.
</p>
<?php include 'realm-log-panel.php'; ?>
<div style="margin-top:14px;">
    <strong style="font-size:0.85rem;">Drop Rates (Level <?php echo $info['factory_level']; ?>)</strong>
    <?php $odds = getFactoryOdds($info['factory_level']); ?>
    <div style="margin-top:8px;display:flex;flex-direction:column;gap:4px;">
        <?php foreach ($odds as $cid => $pct):
            $icon = strtolower(str_replace(array('%',' '), array('','-'), $con_names[$cid])) . '.png';
            $qty  = intval($amounts[$cid]['amount'] ?? 0);
        ?>
        <div style="display:flex;align-items:center;gap:8px;font-size:0.8rem;">
            <img class="icon" src="icons/<?php echo $icon; ?>" onerror="this.src='icons/skull.png'" style="width:18px;height:18px;flex-shrink:0;"/>
            <span style="width:110px;opacity:0.75;flex-shrink:0;"><?php echo $con_names[$cid]; ?></span>
            <div style="flex:1;height:6px;background:rgba(255,255,255,0.08);border-radius:3px;overflow:hidden;">
                <div style="height:100%;width:<?php echo $pct; ?>%;background:rgba(0,200,160,0.7);border-radius:3px;"></div>
            </div>
            <span style="width:32px;text-align:right;opacity:0.7;"><?php echo $pct; ?>%</span>
            <span style="width:28px;text-align:right;color:#00c8a0;opacity:<?php echo $qty > 0 ? '1' : '0.3'; ?>;">x<?php echo $qty; ?></span>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $conn->close(); ?>
