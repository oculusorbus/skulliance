<?php
/**
 * admin-missions.php — build and extend a project's mission ladder.
 *
 * The page the whole missions-economy.md exercise was for. You type the
 * title, the description and the format; cost comes pre-filled from the
 * ladder this project already uses, and reward and duration are DERIVED
 * -- shown but never posted, so a hand-edited field cannot reach the
 * table. That is what makes the two live typos that started this
 * unsaveable rather than merely corrected.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/missions-lib.php';

$MSG = array('ok' => array(), 'err' => array());
$pid = isset($_GET['project']) ? (int)$_GET['project'] : 0;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!adminIsSuper()) { http_response_code(403); exit; }

$id      = (int)($_POST['quest_id'] ?? 0);
$project = (int)($_POST['project_id'] ?? 0);
$title   = trim((string)($_POST['title'] ?? ''));
$desc    = trim((string)($_POST['description'] ?? ''));
$level   = (int)($_POST['level'] ?? 0);
$cost    = (int)($_POST['cost'] ?? 0);
$ext     = strtolower(trim((string)($_POST['extension'] ?? 'png')));

/* DERIVED, NEVER POSTED. The form shows them; it does not send
   them, so a hand-edited field cannot reach the table. */
$reward   = admin_mission_reward($cost, $level);
$duration = admin_mission_duration($cost);

$existing = adm_missions($conn, $project);
$others   = array();
foreach ($existing as $lv => $row) if ((int)$row['id'] !== $id) $others[] = (int)$lv;

$errs = admin_validate_mission(
	array('title' => $title, 'level' => $level, 'cost' => $cost,
	      'reward' => $reward, 'duration' => $duration), $others);
if ($project <= 0) $errs[] = 'Pick a project.';
if ($desc === '')  $errs[] = 'A mission needs a description; it is what the player reads before launching.';
if (!isset(admin_art_kinds()[$ext])) $errs[] = 'Pick a format this platform renders.';

/* The filename is the title, platform-wide. */
$slug = admin_mission_slug($title);
$taken = adm_all_titles($conn, $id);
if (isset($taken[$slug]))
	$errs[] = 'Another mission (id ' . $taken[$slug] . ') already produces the art filename "'
	        . htmlspecialchars($slug) . '". Saving this would overwrite its image.';

/* Which art files exist, counting whatever arrived with this post. */
$dir  = __DIR__ . '/images/missions';
$have = array();
foreach (array('png','jpg','gif','mp4') as $e) if (is_file("$dir/$slug.$e")) $have[] = $e;
$upload_err = '';
foreach (array('art' => array('png','jpg','gif','mp4'), 'art_still' => array('gif','png','jpg')) as $field => $allow) {
	if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) continue;
	if (!$errs) {
		$e = adm_accept_upload($field, $dir, $slug, $allow);
		if ($e !== '') $upload_err = $e;
	}
}
if ($upload_err !== '') $errs[] = $upload_err;
if (!$errs) {
	$have = array();
	foreach (array('png','jpg','gif','mp4') as $e) if (is_file("$dir/$slug.$e")) $have[] = $e;
	foreach (admin_art_missing($ext, $have) as $m) $errs[] = $m;
}

if ($errs) { $MSG['err'] = array_merge($MSG['err'], $errs); }
else {
	$t = $conn->real_escape_string($title);
	$d = $conn->real_escape_string($desc);
	$x = $conn->real_escape_string($ext);
	/* mysqli_report is OFF platform-wide, so query() returns false rather
	   than throwing -- and redirecting back to a form that looks saved is
	   how a mission silently fails to exist. */
	$wrote = ($id > 0)
		? $conn->query("UPDATE quests SET title='$t', description='$d', extension='$x',
		                cost=$cost, reward=$reward, duration=$duration, level=$level WHERE id=$id")
		: $conn->query("INSERT INTO quests (title, description, extension, project_id, cost, reward, duration, level)
		                VALUES ('$t','$d','$x',$project,$cost,$reward,$duration,$level)");
	if (!$wrote) $MSG['err'][] = 'The database refused that write: ' . htmlspecialchars($conn->error);
	else { header('Location: admin-missions.php?project=' . $project . '&saved=1'); exit; }
}
}
if (isset($_GET['saved'])) $MSG['ok'][] = 'Saved.';

$PROJECTS = adm_projects($conn);
if ($pid && !isset($PROJECTS[$pid])) $pid = 0;

admin_chrome('missions');
admin_flash($MSG);
admin_project_picker($PROJECTS, $pid, 'admin-missions.php');

if (!$pid) { echo '<p class="adm-note">Pick a project to see its mission ladder.</p></div></div>';
             include 'admin-css.php'; exit; }

$M = adm_missions($conn, $pid);
$costs = array(); foreach ($M as $row) $costs[] = (int)$row['cost'];
$next_level = $M ? (max(array_keys($M)) + 1) : 1;
$def = admin_mission_defaults($costs, $next_level);
$ladder = array();
foreach ($M as $lv => $row) $ladder[$lv] = array('cost' => (int)$row['cost'], 'reward' => (int)$row['reward'],
                                                 'duration' => (int)$row['duration'], 'title' => $row['title']);
$problems = $M ? admin_validate_ladder($ladder) : array();
$qid = isset($_GET['quest']) ? (int)$_GET['quest'] : 0;
$Q = null; foreach ($M as $row) if ((int)$row['id'] === $qid) $Q = $row;
?>
<p class="adm-note">
  <a href="admin-projects.php?project=<?php echo $pid; ?>">Project settings</a> &nbsp;|&nbsp;
  <a href="admin-collections.php?project=<?php echo $pid; ?>">Collections</a></p>

<section class="adm-card">
  <h3><?php echo htmlspecialchars($PROJECTS[$pid]['name']); ?> &mdash;
      <?php echo count($M); ?> mission<?php echo count($M) === 1 ? '' : 's'; ?></h3>
  <?php foreach ($problems as $p): ?>
    <p class="adm-msg bad"><?php echo htmlspecialchars($p); ?></p>
  <?php endforeach; ?>
  <?php if ($M): ?>
  <div class="adm-tablewrap"><table class="adm-table">
    <thead><tr><th>Lvl</th><th>Mission</th><th>Art</th><th>Cost</th><th>Reward</th><th>&times;</th><th>Days</th><th>Net/day</th><th></th></tr></thead>
    <tbody>
    <?php foreach ($M as $lv => $row):
      $c = (int)$row['cost']; $w = (int)$row['reward']; $d = (int)$row['duration'];
      $slug  = admin_mission_slug($row['title']);
      $still = $slug . '.' . admin_art_ext($row['extension']);
      $art_ok = is_file(__DIR__ . '/images/missions/' . $still)
             && ($row['extension'] !== 'mp4' || is_file(__DIR__ . '/images/missions/' . $slug . '.mp4'));
    ?>
      <tr>
        <td class="mono"><?php echo (int)$lv; ?></td>
        <td><?php echo htmlspecialchars($row['title']); ?></td>
        <td><?php echo $art_ok
             ? '<span class="ok-dot">&#10003;</span>'
             : '<span class="bad-dot" title="missing ' . htmlspecialchars($still) . '">&#10007;</span>'; ?>
            <?php echo htmlspecialchars($row['extension']); ?></td>
        <td class="mono"><?php echo $c ? number_format($c) : '&mdash;'; ?></td>
        <td class="mono"><?php echo number_format($w); ?></td>
        <td class="mono"><?php echo $c ? number_format($w / $c, 1) : '&mdash;'; ?></td>
        <td class="mono"><?php echo $d; ?></td>
        <td class="mono"><?php echo $d ? number_format(admin_mission_per_day($c, $w, $d)) : '&mdash;'; ?></td>
        <td><a href="admin-missions.php?project=<?php echo $pid; ?>&quest=<?php echo (int)$row['id']; ?>">Edit</a></td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table></div>
  <?php else: ?>
    <p class="adm-note">No missions yet. The first one is the free intro: level 1, cost 0, reward 10, one day.</p>
  <?php endif; ?>
</section>

<section class="adm-card">
  <h3><?php echo $Q ? 'Edit level ' . (int)$Q['level'] : 'Add mission &mdash; level ' . $next_level; ?></h3>
  <form method="post" enctype="multipart/form-data" class="adm-form">
    <input type="hidden" name="project_id" value="<?php echo $pid; ?>">
    <input type="hidden" name="quest_id" value="<?php echo $Q ? (int)$Q['id'] : 0; ?>">
    <input type="hidden" name="level" value="<?php echo $Q ? (int)$Q['level'] : $next_level; ?>">
    <div class="adm-grid">
      <label>Title
        <input type="text" name="title" required value="<?php echo htmlspecialchars($Q['title'] ?? ''); ?>">
        <small>Also the art filename, platform-wide. Letters, numbers, spaces and apostrophes only.</small></label>
      <label>Format
        <select name="extension">
          <?php foreach (array_keys(admin_art_kinds()) as $k): ?>
            <option value="<?php echo $k; ?>" <?php echo (($Q['extension'] ?? 'png') === $k) ? 'selected' : ''; ?>>.<?php echo $k; ?></option>
          <?php endforeach; ?>
        </select>
        <small>An .mp4 needs a .gif uploaded too &mdash; that is the still every tile shows.</small></label>
      <label>Cost
        <input type="number" name="cost" step="100" min="0"
               value="<?php echo $Q ? (int)$Q['cost'] : $def['cost']; ?>">
        <small>Reward and duration follow from this and the level. You cannot set them directly.</small></label>
      <label>Art file
        <input type="file" name="art" accept="image/png,image/jpeg,image/gif,video/mp4"></label>
      <label>Still image (mp4 only)
        <input type="file" name="art_still" accept="image/gif,image/png,image/jpeg"></label>
    </div>
    <label class="adm-wide">Description
      <textarea name="description" rows="6" required><?php echo htmlspecialchars($Q['description'] ?? ''); ?></textarea></label>
    <p class="adm-note">At level <?php echo $Q ? (int)$Q['level'] : $next_level; ?>, a cost of
      <strong class="mono"><?php echo number_format($Q ? (int)$Q['cost'] : $def['cost']); ?></strong> gives
      reward <strong class="mono"><?php echo number_format($Q ? (int)$Q['reward'] : $def['reward']); ?></strong>,
      <strong class="mono"><?php echo $Q ? (int)$Q['duration'] : $def['duration']; ?></strong> days,
      <strong class="mono"><?php echo number_format($Q ? admin_mission_per_day((int)$Q['cost'], (int)$Q['reward'], (int)$Q['duration']) : $def['per_day']); ?></strong> net/day.</p>
    <button type="submit"><?php echo $Q ? 'Save mission' : 'Add mission'; ?></button>
    <?php if ($Q): ?><p class="adm-note"><a href="admin-missions.php?project=<?php echo $pid; ?>">Cancel &mdash; add a new mission instead</a></p><?php endif; ?>
  </form>
</section>

</div></div>
<?php include 'admin-css.php'; ?>
