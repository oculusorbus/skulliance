<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$info = getMineInfo($conn, $realm_id);
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
<?php if ($info['mine_level'] > 0): ?>
<div class="claim-panel" style="margin-top:14px;padding:12px;background:rgba(0,200,160,0.08);border:1px solid rgba(0,200,160,0.25);border-radius:8px;text-align:center;">
    <div style="font-size:0.82rem;opacity:0.7;margin-bottom:6px;">Accrued Since Last Claim</div>
    <div style="font-size:1.4rem;font-weight:700;color:#00c8a0;margin-bottom:10px;"><?php echo number_format($info['pending_carbon']); ?> CARBON</div>
    <?php if ($info['pending_carbon'] > 0): ?>
    <button class="small-button" onclick="claimMine()" style="background:#00c8a0;color:#000;">Claim CARBON</button>
    <?php else: ?>
    <div style="font-size:0.8rem;opacity:0.5;">Nothing to claim yet — come back tomorrow!</div>
    <?php endif; ?>
</div>
<?php endif; ?>
<p style="font-size:0.82rem;opacity:0.6;margin-top:12px;">
    The Mine generates <strong><?php echo number_format($info['nightly']); ?> CARBON per day</strong>.
    CARBON can be crafted into DIAMOND. Upgrade the Mine to increase your daily yield.
</p>
<div style="margin-top:12px;">
    <table class="soldiers-table" style="width:100%;font-size:0.8rem;">
        <tr><th>Level</th><th>CARBON / Day</th></tr>
        <?php for ($lv = 1; $lv <= 10; $lv++): ?>
        <tr style="<?php echo ($lv == $info['mine_level']) ? 'color:#00c8a0;font-weight:bold;' : ''; ?>">
            <td><?php echo $lv; ?></td>
            <td><?php echo number_format($lv * 10000); ?></td>
        </tr>
        <?php endfor; ?>
    </table>
</div>
<?php $conn->close(); ?>
