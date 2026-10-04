<?php
/**
 * admin-collections.php — the collections under one project.
 *
 * A collection is what makes an NFT stakeable, so the on-chain id is the
 * field that matters and the one worth validating hard: an identifier off
 * by a character matches nothing, raises nothing, and looks exactly like
 * a correct verifier run against an empty wallet (multichain.md section 5).
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();   // before any output, and before header.php

$MSG = array('ok' => array(), 'err' => array());
$pid = isset($_GET['project']) ? (int)$_GET['project'] : 0;
$cid = isset($_GET['collection']) ? (int)$_GET['collection'] : 0;

$CHAINS = array(1 => 'Cardano', 2 => 'XRPL', 3 => 'Solana');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!adminIsSuper()) { http_response_code(403); exit; }

	$id      = (int)($_POST['collection_id'] ?? 0);
	$project = (int)($_POST['project_id'] ?? 0);
	$name    = trim((string)($_POST['name'] ?? ''));
	$policy  = trim((string)($_POST['policy'] ?? ''));
	$rate    = (float)($_POST['rate'] ?? 0);
	$chain   = (int)($_POST['blockchain_id'] ?? 1);
	$slug    = trim((string)($_POST['marketplace_slug'] ?? ''));

	if ($project <= 0)  $MSG['err'][] = 'Pick a project for this collection.';
	if ($name === '')   $MSG['err'][] = 'A collection needs a name.';
	if (!isset($CHAINS[$chain])) $MSG['err'][] = 'Unknown chain.';
	if ($policy === '') $MSG['err'][] = 'A collection needs its on-chain id.';
	else {
		if ($chain === 1 && !preg_match('/^[0-9a-f]{56}$/i', $policy))
			$MSG['err'][] = 'A Cardano policy id is 56 hex characters.';
		if ($chain === 2 && strpos($policy, ':') === false)
			$MSG['err'][] = 'An XRPL collection is issuer:taxon. verify-xrpl-probe.php prints it.';
		/* Decoded, not pattern-matched: base58 carries no checksum, so a
		   truncated Solana address is still legal-looking. */
		if ($chain === 3 && !(function_exists('sol_base58_decode')
		    ? strlen(sol_base58_decode($policy)) === 32
		    : preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $policy)))
			$MSG['err'][] = 'That is not a 32-byte Solana address. verify-solana-probe.php prints the right one.';
	}
	if ($rate < 0) $MSG['err'][] = 'Rate cannot be negative.';

	/* One collection per on-chain id, platform-wide: two rows sharing a
	   policy would both claim the same NFTs. */
	if (!$MSG['err']) {
		$pe = $conn->real_escape_string($policy);
		$dup = $conn->query("SELECT id, project_id FROM collections WHERE policy = '$pe'"
		                  . ($id ? " AND id != $id" : "") . " LIMIT 1");
		if ($dup && $dup->num_rows) {
			$d = $dup->fetch_assoc();
			$MSG['err'][] = 'Collection ' . (int)$d['id'] . ' (project ' . (int)$d['project_id']
			              . ') already uses that on-chain id.';
		}
	}

	if (!$MSG['err']) {
		$n = $conn->real_escape_string($name);
		$p = $conn->real_escape_string($policy);
		$s = $conn->real_escape_string($slug);
		$sv = ($s === '') ? 'NULL' : "'$s'";
		$wrote = ($id > 0)
			? $conn->query("UPDATE collections SET name='$n', policy='$p', rate=$rate,
			                blockchain_id=$chain, marketplace_slug=$sv WHERE id=$id")
			: $conn->query("INSERT INTO collections (blockchain_id, project_id, name, policy, rate, marketplace_slug)
			                VALUES ($chain, $project, '$n', '$p', $rate, $sv)");
		if (!$wrote) $MSG['err'][] = 'The database refused that write: ' . htmlspecialchars($conn->error);
		else { header('Location: admin-collections.php?project=' . $project . '&saved=1'); exit; }
	}
}
if (isset($_GET['saved'])) $MSG['ok'][] = 'Saved.';

$PROJECTS = adm_projects($conn);
if ($pid && !isset($PROJECTS[$pid])) $pid = 0;

include 'header.php';   // GLOBAL scope: header.php reads $name and $avatar_url
admin_chrome('collections');
admin_flash($MSG);
admin_project_picker($PROJECTS, $pid, 'admin-collections.php');

if (!$pid) { echo '<p class="adm-note">Pick a project to see its collections.</p></div></div>';
             include 'admin-css.php'; exit; }

$COLS = adm_collections($conn, $pid);
$C = null; foreach ($COLS as $c) if ((int)$c['id'] === $cid) $C = $c;
?>
<p class="adm-note">
  <a href="admin-projects.php?project=<?php echo $pid; ?>">Project settings</a> &nbsp;|&nbsp;
  <a href="admin-missions.php?project=<?php echo $pid; ?>">Missions</a></p>

<div class="adm-card">
  <h3><?php echo htmlspecialchars($PROJECTS[$pid]['name']); ?> &mdash;
      <?php echo count($COLS); ?> collection<?php echo count($COLS) === 1 ? '' : 's'; ?></h3>
  <?php if ($COLS): ?>
  <div class="adm-tablewrap"><table class="adm-table">
    <thead><tr><th>Name</th><th>Chain</th><th>On-chain id</th><th>Rate</th><th>Staked</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($COLS as $c):
      $n = $conn->query("SELECT COUNT(*) AS n FROM nfts WHERE collection_id = " . (int)$c['id']);
      $n = ($n && $n->num_rows) ? (int)$n->fetch_assoc()['n'] : 0;
    ?>
      <tr>
        <td><?php echo htmlspecialchars($c['name']); ?></td>
        <td><?php echo $CHAINS[(int)$c['blockchain_id']] ?? (int)$c['blockchain_id']; ?></td>
        <td class="mono trunc" title="<?php echo htmlspecialchars($c['policy']); ?>"><?php echo htmlspecialchars($c['policy']); ?></td>
        <td class="mono"><?php echo htmlspecialchars($c['rate']); ?></td>
        <td class="mono"><?php echo number_format($n); ?></td>
        <td><a href="admin-collections.php?project=<?php echo $pid; ?>&collection=<?php echo (int)$c['id']; ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
    <p class="adm-note">No collections yet. Nothing from this project can be staked until one exists.</p>
  <?php endif; ?>
</div>

<div class="adm-card">
  <h3><?php echo $C ? 'Edit ' . htmlspecialchars($C['name']) : 'Add a collection'; ?></h3>
  <form method="post" class="adm-form">
    <input type="hidden" name="project_id" value="<?php echo $pid; ?>">
    <input type="hidden" name="collection_id" value="<?php echo $C ? (int)$C['id'] : 0; ?>">
    <div class="adm-grid">
      <label>Name <input type="text" name="name" required value="<?php echo htmlspecialchars($C['name'] ?? ''); ?>"></label>
      <label>Chain
        <select name="blockchain_id">
          <?php foreach ($CHAINS as $k => $v): ?>
            <option value="<?php echo $k; ?>" <?php echo ((int)($C['blockchain_id'] ?? 1) === $k) ? 'selected' : ''; ?>><?php echo $v; ?></option>
          <?php endforeach; ?>
        </select></label>
      <label>On-chain id
        <input type="text" name="policy" required value="<?php echo htmlspecialchars($C['policy'] ?? ''); ?>">
        <small>Cardano: 56 hex. XRPL: issuer:taxon. Solana: the collection address.</small></label>
      <label>Rate <input type="number" name="rate" step="0.01" min="0"
               value="<?php echo htmlspecialchars($C['rate'] ?? '0'); ?>">
        <small>Points per NFT per day.</small></label>
      <label>Marketplace slug
        <input type="text" name="marketplace_slug" value="<?php echo htmlspecialchars($C['marketplace_slug'] ?? ''); ?>">
        <small>XRPL and Solana only &mdash; xrp.cafe addresses a collection by an artist-chosen
               slug, and Tensor takes either. Wayup addresses by policy, so a slug is ignored on
               Cardano rather than producing a dead link.</small></label>
    </div>
    <button type="submit"><?php echo $C ? 'Save collection' : 'Add collection'; ?></button>
    <?php if ($C): ?><p class="adm-note"><a href="admin-collections.php?project=<?php echo $pid; ?>">Cancel &mdash; add a new one instead</a></p><?php endif; ?>
  </form>
</div>

</div></div>
<?php include 'admin-css.php'; ?>
