<?php
include '../db.php';
include '../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) exit;
$realm_id = getRealmID($conn);
if (!$realm_id) exit;

$project_id    = isset($_GET['project_id'])    ? intval($_GET['project_id'])    : 0;
$collection_id = isset($_GET['collection_id']) ? intval($_GET['collection_id']) : 0;
$nfts = getEligibleEnlistNFTs($conn, $realm_id, $project_id, $collection_id);
?>
<div class="soldiers-grid" id="enlist-picker-grid">
<?php foreach ($nfts as $n):
    $img_src    = getIPFS($n['ipfs'], $n['collection_id'], $n['project_id']);
    $is_partner = ($n['project_id'] > 7 && $n['project_id'] != 15);
?>
<div class="soldier-card enlist-candidate" data-nft-id="<?php echo $n['nft_id']; ?>" data-project-id="<?php echo $n['project_id']; ?>" data-project-name="<?php echo htmlspecialchars($n['project_name']); ?>" data-collection-id="<?php echo $n['collection_id']; ?>" data-collection-name="<?php echo htmlspecialchars($n['collection_name']); ?>" onclick="toggleEnlistSelect(this)">
    <img class="soldier-nft-img" src="<?php echo htmlspecialchars($img_src); ?>" onerror="this.src='icons/skull.png'" />
    <div class="soldier-name"><?php echo htmlspecialchars($n['nft_name']); ?></div>
    <?php if ($is_partner): ?><div class="soldier-badge partner-badge">2 slots</div><?php endif; ?>
    <div class="soldier-status"><?php echo htmlspecialchars($n['project_name']); ?></div>
</div>
<?php endforeach; ?>
<?php if (empty($nfts)): ?>
<p style="opacity:0.55;font-size:0.85rem;">No eligible NFTs. All owned NFTs are already enlisted or on missions.</p>
<?php endif; ?>
</div>
<?php $conn->close(); ?>
