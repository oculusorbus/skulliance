<?php
/**
 * admin-projects.php — create a project and set its currency icon.
 *
 * One of three admin pages (projects / collections / missions). They were
 * one page with tabs and are now separate, because each is a different
 * job: you onboard a project once, add collections rarely, and write
 * missions for an hour.
 *
 * Every rule is in admin-lib.php. This collects input, calls the
 * validator and renders. admin_chrome() does the rights check.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';

$MSG = array('ok' => array(), 'err' => array());
$pid = isset($_GET['project']) ? (int)$_GET['project'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	/* Checked again rather than trusting that the page drew a form. */
	if (!adminIsSuper()) { http_response_code(403); exit; }

	$id       = (int)($_POST['project_id'] ?? 0);
	$name     = trim((string)($_POST['name'] ?? ''));
	$currency = strtoupper(trim((string)($_POST['currency'] ?? '')));
	$discord  = trim((string)($_POST['discord_id'] ?? ''));
	$divider  = (float)($_POST['divider'] ?? 1);

	$taken = array();
	foreach (adm_projects($conn) as $p_id => $p) if ($p_id !== $id) $taken[] = $p['currency'];

	if ($name === '') $MSG['err'][] = 'A project needs a name.';
	$cp = admin_currency_problem($currency, $taken);
	if ($cp !== '') $MSG['err'][] = $cp;
	if ($divider <= 0) $MSG['err'][] = 'Divider must be above 0; it scales every store price for this project.';

	if (!$MSG['err']) {
		$n = $conn->real_escape_string($name);
		$c = $conn->real_escape_string($currency);
		$d = $conn->real_escape_string($discord);
		$v = (float)$divider;
		$wrote = ($id > 0)
			? $conn->query("UPDATE projects SET name='$n', currency='$c', discord_id='$d', divider=$v WHERE id=$id")
			: $conn->query("INSERT INTO projects (name, currency, discord_id, divider) VALUES ('$n','$c','$d',$v)");
		if ($id <= 0 && $wrote) $id = (int)$conn->insert_id;

		/* mysqli_report is OFF platform-wide, so a failed write returns
		   false rather than throwing. Saying so beats a silent redirect
		   back to a form that looks like it worked. */
		if (!$wrote) {
			$MSG['err'][] = 'The database refused that write: ' . htmlspecialchars($conn->error);
		} else {
			/* The icon is named for the CURRENCY, never for the upload. */
			$slug = strtolower(str_replace('$', '', $currency));
			$up = adm_accept_upload('icon', __DIR__ . '/icons', $slug, array('png'));
			if ($up !== '') $MSG['err'][] = 'Project saved, but the icon did not: ' . $up;
			else { header('Location: admin-projects.php?project=' . $id . '&saved=1'); exit; }
		}
	}
}
if (isset($_GET['saved'])) $MSG['ok'][] = 'Saved.';

$PROJECTS = adm_projects($conn);
if ($pid && !isset($PROJECTS[$pid])) $pid = 0;
$P = $pid ? $PROJECTS[$pid] : null;

admin_chrome('projects');
admin_flash($MSG);
?>
<?php if (!$PROJECTS): ?>
  <p class="adm-msg bad">No projects came back from the database.
     <?php if ($conn->error) echo 'Last error: ' . htmlspecialchars($conn->error); ?></p>
<?php endif; ?>

<?php admin_project_picker($PROJECTS, $pid, 'admin-projects.php'); ?>
<p class="adm-note"><a href="admin-projects.php">Start a new project &rarr;</a>
  <?php if ($P): ?> &nbsp;|&nbsp;
    <a href="admin-collections.php?project=<?php echo $pid; ?>">Collections</a> &nbsp;|&nbsp;
    <a href="admin-missions.php?project=<?php echo $pid; ?>">Missions</a>
  <?php endif; ?></p>

<section class="adm-card">
  <h3><?php echo $P ? 'Edit ' . htmlspecialchars($P['name']) : 'New project'; ?></h3>
  <form method="post" enctype="multipart/form-data" class="adm-form">
    <input type="hidden" name="project_id" value="<?php echo $pid; ?>">
    <div class="adm-grid">
      <label>Name
        <input type="text" name="name" required value="<?php echo htmlspecialchars($P['name'] ?? ''); ?>"></label>
      <label>Currency
        <input type="text" name="currency" required maxlength="16" pattern="[A-Za-z0-9]{2,16}"
               value="<?php echo htmlspecialchars($P['currency'] ?? ''); ?>">
        <small>Becomes <code>icons/<em>currency</em>.png</code> and a Skull Swap tile. Must be unique,
               ignoring case.</small></label>
      <label>Discord ID
        <input type="text" name="discord_id" value="<?php echo htmlspecialchars($P['discord_id'] ?? ''); ?>">
        <small>Lets this creator edit their own store listings.</small></label>
      <label>Divider
        <input type="number" name="divider" step="0.01" min="0.01"
               value="<?php echo htmlspecialchars($P['divider'] ?? '1'); ?>">
        <small>Scales store prices for this project.</small></label>
      <label>Currency icon (PNG)
        <input type="file" name="icon" accept="image/png">
        <small>Resized to 1000px and stripped. Saved as the currency, not as the filename you upload.</small></label>
    </div>
    <?php if ($P): $ic = admin_currency_icon($P['currency']); ?>
      <p class="adm-note">Icon: <code><?php echo htmlspecialchars($ic); ?></code>
        <?php if (is_file(__DIR__ . '/' . $ic)): ?>
          <img src="<?php echo htmlspecialchars($ic); ?>" alt="" class="adm-icon">
        <?php else: ?><strong class="warn">&mdash; not on the server yet</strong><?php endif; ?></p>
    <?php endif; ?>
    <button type="submit"><?php echo $P ? 'Save project' : 'Create project'; ?></button>
  </form>
</section>

<section class="adm-card">
  <h3>All projects</h3>
  <div class="adm-tablewrap"><table class="adm-table">
    <thead><tr><th>Project</th><th>Currency</th><th>Icon</th><th>Collections</th><th>Missions</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($PROJECTS as $id => $p):
      $ic = admin_currency_icon($p['currency']);
      $nc = count(adm_collections($conn, $id));
      $nm = count(adm_missions($conn, $id));
    ?>
      <tr>
        <td><?php echo htmlspecialchars($p['name']); ?></td>
        <td class="mono"><?php echo htmlspecialchars($p['currency']); ?></td>
        <td><?php echo is_file(__DIR__ . '/' . $ic)
             ? '<span class="ok-dot">&#10003;</span>'
             : '<span class="bad-dot" title="' . htmlspecialchars($ic) . ' is missing">&#10007;</span>'; ?></td>
        <td class="mono"><?php echo $nc ?: '<span class="bad-dot">0</span>'; ?></td>
        <td class="mono"><?php echo $nm ?: '<span class="bad-dot">0</span>'; ?></td>
        <td><a href="admin-projects.php?project=<?php echo $id; ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
</section>

</div></div>
<?php include 'admin-css.php'; ?>
