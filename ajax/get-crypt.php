<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$crypt_level  = intval(getRealmLocationLevel($conn, $realm_id, 6));
$res_days     = max(1, 11 - $crypt_level);
$soldiers     = getCryptSoldiers($conn, $realm_id);
$eligible_count = 0;
foreach ($soldiers as $s) { if ($s['eligible']) $eligible_count++; }
$now = time();
?>
<div class="soldiers-stat-row">
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Crypt Level</span>
        <span class="soldiers-stat-value"><?php echo $crypt_level; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Resurrection Time</span>
        <span class="soldiers-stat-value"><?php echo $res_days; ?> <?php echo $res_days == 1 ? 'Day' : 'Days'; ?></span>
    </div>
    <div class="soldiers-stat">
        <span class="soldiers-stat-label">Dead Soldiers</span>
        <span class="soldiers-stat-value"><?php echo count($soldiers); ?></span>
    </div>
</div>
<div class="soldiers-grid" id="crypt-soldiers-grid">
<?php foreach ($soldiers as $s):
    $img_src   = getIPFS($s['ipfs'], $s['collection_id'], $s['project_id']);
    $ready_at  = strtotime($s['ready_at']);
    $secs_left = $ready_at - $now;
?>
<div class="soldier-card <?php echo $s['eligible'] ? 'soldier-ready' : 'soldier-dead'; ?>">
    <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" style="opacity:<?php echo $s['eligible'] ? '1' : '0.5'; ?>;"/>
    <div class="soldier-name"><?php echo htmlspecialchars($s['nft_name']); ?></div>
    <div class="soldier-status <?php echo $s['eligible'] ? 'status-ready' : 'status-dead'; ?>">
        <?php if ($s['eligible']): ?>
            Ready to Resurrect
        <?php else:
            $d = floor($secs_left / 86400);
            $h = floor(($secs_left % 86400) / 3600);
            echo $d . "d " . $h . "h remaining";
        endif; ?>
    </div>
</div>
<?php endforeach; ?>
<?php if (empty($soldiers)): ?>
<p style="opacity:0.55;font-size:0.85rem;">The Crypt is empty. No fallen soldiers.</p>
<?php endif; ?>
</div>
<?php if ($eligible_count > 0): ?>
<div class="raid-modal-footer" style="margin-top:16px;">
    <button class="button" onclick="resurrectAllSoldiers()">Resurrect All (<?php echo $eligible_count; ?> Ready)</button>
</div>
<?php endif; ?>
<?php $conn->close(); ?>
