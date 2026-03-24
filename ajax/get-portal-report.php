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
<?php $conn->close(); ?>
