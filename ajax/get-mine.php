<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$info = getMineInfo($conn, $realm_id);
$logs        = getUnclaimedRealmLogs($conn, $realm_id, array('carbon'));
$claim_types = array('carbon');
?>
<div class="soldiers-stat-row">
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Mine Level</span>
        <span class="soldiers-stat-value"><?php echo $info['mine_level']; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">CARBON / Day</span>
        <span class="soldiers-stat-value"><?php echo number_format($info['nightly']); ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">CARBON Balance</span>
        <span class="soldiers-stat-value"><?php echo number_format($info['carbon']); ?></span>
    </div>
</div>
<p style="font-size:0.82rem;opacity:0.6;margin-top:12px;">
    The Mine generates <strong><?php echo number_format($info['nightly']); ?> CARBON per day</strong>, distributed nightly.
    CARBON can be crafted into DIAMOND. Upgrade the Mine to increase your daily yield.
</p>
<div style="margin-top:12px;">
    <table class="soldiers-table" style="width:100%;font-size:0.8rem;">
        <tr><th>Level</th><th>CARBON / Day</th></tr>
        <?php for ($lv = 1; $lv <= 10; $lv++): ?>
        <tr style="<?php echo ($lv == $info['mine_level']) ? 'color:#00c8a0;font-weight:bold;' : ''; ?>">
            <td><?php echo $lv; ?></td>
            <td><?php echo number_format($lv * 100); ?></td>
        </tr>
        <?php endfor; ?>
    </table>
</div>
<?php include 'realm-log-panel.php'; ?>
<?php $conn->close(); ?>
