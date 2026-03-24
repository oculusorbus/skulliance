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
    <div class="inv-info-grid" style="margin-top:10px;">
        <?php
        $level = $info['factory_level'];
        $odds = array();
        if ($level <= 3)      $odds = array(4=>50, 3=>35, 2=>15);
        elseif ($level <= 6)  $odds = array(4=>30, 3=>28, 2=>20, 5=>12, 1=>10);
        elseif ($level <= 9)  $odds = array(4=>18, 3=>20, 2=>17, 5=>13, 1=>12, 7=>11, 6=>9);
        else                  $odds = array(4=>12, 3=>16, 2=>16, 5=>13, 1=>13, 7=>13, 6=>17);

        foreach ($odds as $cid => $pct):
            $icon = strtolower(str_replace(array('%',' '), array('','-'), $con_names[$cid])) . '.png';
        ?>
        <div class="inv-info-item">
            <img class="icon" src="icons/<?php echo $icon; ?>" onerror="this.src='icons/skull.png'"/>
            <div>
                <strong><?php echo $con_names[$cid]; ?></strong>
                <p style="display:flex;justify-content:space-between;">~<?php echo $pct; ?>% chance <span style="color:#00c8a0;">x<?php echo intval($amounts[$cid]['amount'] ?? 0); ?></span></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $conn->close(); ?>
