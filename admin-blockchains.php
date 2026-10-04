<?php
/**
 * admin-blockchains.php — the per-chain configuration.
 *
 * Everything here is a column SPECIFICALLY so it can be changed without a
 * deploy: api_base so a slow node can be swapped mid-incident,
 * ipfs_gateway so a gateway going bad is a config change, marketplace_url
 * so a marketplace going down is not a code release. Until this page they
 * were columns nobody could edit, which is the worst of both.
 *
 * WHAT IS NOT HERE, and why: collections.marketplace_slug. The MARKETPLACE
 * belongs to the chain; the IDENTIFIER belongs to the collection. xrp.cafe
 * addresses a collection by an artist-chosen slug -- bootlegs, moneyhorse,
 * vipasana, random-digi-hell-scenes are four live values on four
 * collections of the SAME chain -- so it can never be one chain-level
 * setting. See collectionMarketUrl() in db.php.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php

$MSG = array('ok' => array(), 'err' => array());

/* The editable columns, with what each one actually does. The whitelist
   is here AND in getChainSetting(); a field missing from either is read
   as its default and silently ignored. */
$FIELDS = array(
	'api_base'        => array('What the verifier talks to',
	                           'Swapped mid-incident when a node is slow. Blank falls back to the built-in default.'),
	'explorer_nft'    => array('Explorer link for one NFT',
	                           'A printf template. %s is the asset id.'),
	'ipfs_gateway'    => array('Gateway for ipfs:// art',
	                           'Tried first, ahead of the built-in list.'),
	'marketplace_url' => array('Marketplace link for a collection',
	                           'A printf template. %s is the collection\'s slug where it has one, otherwise its on-chain id.'),
);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!adminIsSuper()) { http_response_code(403); exit; }

	$id = (int)($_POST['blockchain_id'] ?? 0);
	if ($id <= 0) $MSG['err'][] = 'No chain given.';

	$sets = array();
	foreach ($FIELDS as $col => $meta) {
		$v = trim((string)($_POST[$col] ?? ''));
		/* A TEMPLATE WITHOUT ITS PLACEHOLDER IS A LINK TO THE SAME PAGE
		   FOREVER, which is worse than no link: every collection on the
		   chain would point at one marketplace listing. */
		if (in_array($col, array('explorer_nft', 'marketplace_url'), true)
		    && $v !== '' && strpos($v, '%s') === false)
			$MSG['err'][] = ucfirst(str_replace('_', ' ', $col)) . ' needs %s in it, or every link goes to the same page.';
		if ($v !== '' && !preg_match('~^https?://~i', $v))
			$MSG['err'][] = ucfirst(str_replace('_', ' ', $col)) . ' must start with http:// or https://.';
		if (strlen($v) > 255)
			$MSG['err'][] = ucfirst(str_replace('_', ' ', $col)) . ' is longer than the column holds (255).';
		$sets[] = "`$col` = " . ($v === '' ? 'NULL' : "'" . $conn->real_escape_string($v) . "'");
	}
	$active = isset($_POST['active']) ? 1 : 0;
	$sets[] = "active = $active";

	if (!$MSG['err']) {
		$wrote = $conn->query("UPDATE blockchains SET " . implode(', ', $sets) . " WHERE id = $id");
		if (!$wrote) $MSG['err'][] = 'The database refused that write: ' . htmlspecialchars($conn->error);
		else { header('Location: admin-blockchains.php?saved=1'); exit; }
	}
}
if (isset($_GET['saved'])) $MSG['ok'][] = 'Saved. The next verifier pass and the next page load use the new values.';

/* SELECT * because this page edits the row as a whole, and because the
   marketplace_url column may not exist yet -- see multichain-schema.md.
   A named list would make the whole page fatal until the ALTER is run. */
$CHAINS = array();
$r = @$conn->query("SELECT * FROM blockchains ORDER BY id ASC");
while ($r && $row = $r->fetch_assoc()) $CHAINS[(int)$row['id']] = $row;
$missing_col = ($CHAINS && !array_key_exists('marketplace_url', reset($CHAINS)));

include 'header.php';   // GLOBAL scope: header.php reads $name and $avatar_url
admin_chrome('blockchains');
admin_flash($MSG);
?>
<?php if (!$CHAINS): ?>
  <p class="adm-msg bad">No rows came back from <code>blockchains</code>.
     <?php if ($conn->error) echo htmlspecialchars($conn->error); ?></p>
<?php endif; ?>
<?php if ($missing_col): ?>
  <p class="adm-msg bad">The <code>marketplace_url</code> column does not exist yet, so that field will not save.
     The one-line migration is in <code>multichain-schema.md</code>.</p>
<?php endif; ?>

<p class="adm-note">These are platform-level. A collection's own marketplace slug lives on the
   collection, because it differs per collection &mdash; see
   <a href="admin-collections.php">Collections</a>.</p>

<?php foreach ($CHAINS as $id => $c): ?>
<div class="adm-card">
  <h3><?php echo htmlspecialchars($c['name']); ?>
      <span class="adm-chainslug"><?php echo htmlspecialchars($c['slug']); ?> &middot; id <?php echo $id; ?></span>
      <?php if (!(int)$c['active']): ?><span class="bad-dot">&mdash; inactive</span><?php endif; ?></h3>
  <form method="post" class="adm-form">
    <input type="hidden" name="blockchain_id" value="<?php echo $id; ?>">
    <div class="adm-grid">
      <?php foreach ($FIELDS as $col => $meta): ?>
        <label><?php echo htmlspecialchars($meta[0]); ?>
          <input type="text" name="<?php echo $col; ?>" spellcheck="false"
                 <?php echo ($col === 'marketplace_url' && $missing_col) ? 'disabled ' : ''; ?>
                 value="<?php echo htmlspecialchars($c[$col] ?? ''); ?>">
          <small><code><?php echo $col; ?></code> &mdash; <?php echo $meta[1]; ?></small></label>
      <?php endforeach; ?>
    </div>
    <label class="adm-inline">
      <input type="checkbox" name="active" value="1" <?php echo (int)$c['active'] ? 'checked' : ''; ?>>
      <span>Active &mdash; the nightly job verifies this chain</span></label>
    <button type="submit">Save <?php echo htmlspecialchars($c['name']); ?></button>
  </form>
</div>
<?php endforeach; ?>

</div></div>
<?php include 'admin-css.php'; ?>
