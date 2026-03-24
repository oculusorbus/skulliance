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
        // Rarity matches consumables DB rates (id=7 most common → id=1/id=6 rarest at 5% each)
        if ($level <= 3)      $odds = array(7=>50, 4=>30, 5=>20);
        elseif ($level <= 6)  $odds = array(7=>40, 4=>25, 5=>20, 3=>15);
        elseif ($level <= 9)  $odds = array(7=>32, 4=>22, 5=>20, 3=>16, 2=>8, 6=>2);
        else                  $odds = array(7=>25, 4=>20, 5=>20, 3=>15, 2=>10, 1=>5, 6=>5);

        foreach ($odds as $cid => $pct):
            $icon = strtolower(str_replace(array('%',' '), array('','-'), $con_names[$cid])) . '.png';
        ?>
        <div class="inv-info-item">
            <img class="icon" src="icons/<?php echo $icon; ?>" onerror="this.src='icons/skull.png'"/>
            <div style="flex:1;min-width:0;">
                <strong><?php echo $con_names[$cid]; ?></strong>
                <p style="display:flex;justify-content:space-between;">~<?php echo $pct; ?>% chance <span style="color:#00c8a0;">x<?php echo intval($amounts[$cid]['amount'] ?? 0); ?></span></p>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php $conn->close(); ?>
