<?php
/**
 * admin.php — one place to onboard a project and build its missions,
 * so none of it needs the database or an FTP client.
 *
 * USER 1 ONLY, checked here and again before every write. adminRights()
 * is the single answer both the page and the writes ask, because a page
 * that decides for itself what to draw and a write that decides for
 * itself what to accept are two rules that drift (store-edit-harness.php
 * paid for that lesson).
 *
 * EVERY RULE LIVES IN admin-lib.php, not here. This file collects input,
 * calls the validator, and renders. The reason is testability: the lib
 * runs with no database and no output, so admin-harness.php can drive the
 * real rules, and the two live typos that started all this -- The Worm's
 * duration and Trash Collection's reward -- are now unsaveable rather
 * than merely corrected.
 *
 * WRITES ARE POST-TO-SELF, not an ajax endpoint. Uploads are multipart
 * and there is exactly one consumer, so a second file would be a second
 * auth surface for no gain. Redirect after a successful write so a
 * refresh cannot repeat it.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
require_once __DIR__ . '/missions-lib.php';

$rights = adminRights();
if (!$rights['ok']) {
	/* 404 rather than 403: an admin panel should not confirm it exists. */
	http_response_code(404);
	include 'header.php';
	echo '<div class="container"><div class="row"><div class="column"><h2>Not found</h2></div></div></div>';
	exit;
}

$MSG = array('ok' => array(), 'err' => array());
$tab = isset($_GET['tab']) ? $_GET['tab'] : 'projects';
if (!in_array($tab, array('projects', 'missions'), true)) $tab = 'projects';
$pid = isset($_GET['project']) ? (int)$_GET['project'] : 0;

/* ---------- reads -------------------------------------------------------- */

function adm_projects($conn) {
	$out = array();
	$r = $conn->query("SELECT id, name, currency, discord_id, divider FROM projects ORDER BY name ASC");
	while ($r && $row = $r->fetch_assoc()) $out[(int)$row['id']] = $row;
	return $out;
}
function adm_collections($conn, $project_id) {
	$out = array(); $project_id = (int)$project_id;
	$r = $conn->query("SELECT id, name, policy, rate, blockchain_id, marketplace_slug
	                   FROM collections WHERE project_id = $project_id ORDER BY name ASC");
	while ($r && $row = $r->fetch_assoc()) $out[] = $row;
	return $out;
}
function adm_missions($conn, $project_id) {
	$out = array(); $project_id = (int)$project_id;
	$r = $conn->query("SELECT id, title, description, extension, cost, reward, duration, level
	                   FROM quests WHERE project_id = $project_id ORDER BY level ASC");
	while ($r && $row = $r->fetch_assoc()) $out[(int)$row['level']] = $row;
	return $out;
}
/* Every title on the platform, because the art filename is the title and
   a collision silently overwrites another project's file. */
function adm_all_titles($conn, $except_id = 0) {
	$out = array(); $except_id = (int)$except_id;
	$r = $conn->query("SELECT id, title FROM quests" . ($except_id ? " WHERE id != $except_id" : ""));
	while ($r && $row = $r->fetch_assoc()) $out[admin_mission_slug($row['title'])] = (int)$row['id'];
	return $out;
}

/* ---------- uploads ------------------------------------------------------ */

/**
 * Accept one uploaded file into $dir/$basename.$ext, resized and stripped.
 * Returns '' or a message. Nothing is written until the type is confirmed
 * from the FILE, never from the name the browser supplied.
 */
function adm_accept_upload($field, $dir, $basename, $allowed_ext) {
	if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) return '';
	$f = $_FILES[$field];
	if ($f['error'] !== UPLOAD_ERR_OK)  return 'Upload failed (code ' . (int)$f['error'] . ').';
	if ($f['size'] > 24 * 1024 * 1024)  return 'That file is over 24MB.';

	/* THE EXTENSION COMES FROM THE CONTENT. A browser-supplied name is a
	   claim, and this writes into a directory the web server serves. */
	$mime = function_exists('finfo_open')
		? finfo_file(finfo_open(FILEINFO_MIME_TYPE), $f['tmp_name']) : '';
	$by_mime = array('image/png' => 'png', 'image/jpeg' => 'jpg',
	                 'image/gif' => 'gif', 'video/mp4' => 'mp4');
	$ext = isset($by_mime[$mime]) ? $by_mime[$mime] : '';
	if ($ext === '') return 'That is not a PNG, JPG, GIF or MP4 (it looks like "' . htmlspecialchars($mime) . '").';
	if (!in_array($ext, $allowed_ext, true))
		return 'A .' . $ext . ' is not accepted here. Allowed: .' . implode(', .', $allowed_ext) . '.';

	if (!is_dir($dir) && !@mkdir($dir, 0755, true)) return 'Could not create ' . basename($dir) . '/.';
	$dest = rtrim($dir, '/') . '/' . $basename . '.' . $ext;

	/* An mp4 is stored as uploaded -- Imagick cannot read one, and there
	   is no ffmpeg on this host to transcode with. See the pairing rule in
	   missions-economy.md section 6b. */
	if ($ext === 'mp4') return @move_uploaded_file($f['tmp_name'], $dest) ? '' : 'Could not write ' . basename($dest) . '.';
	return admin_write_image($f['tmp_name'], $dest);
}

/* ---------- writes ------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$r2 = adminRights();                       // checked again, not trusted from above
	if (!$r2['ok']) { http_response_code(403); exit; }
	$action = isset($_POST['action']) ? $_POST['action'] : '';

	/* --- a project, and its currency icon --- */
	if ($action === 'project') {
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
			if ($id > 0) {
				$conn->query("UPDATE projects SET name='$n', currency='$c', discord_id='$d', divider=$v WHERE id=$id");
			} else {
				$conn->query("INSERT INTO projects (name, currency, discord_id, divider) VALUES ('$n','$c','$d',$v)");
				$id = (int)$conn->insert_id;
			}
			if ($id > 0) {
				/* The icon is named for the currency, never for the upload. */
				$slug = strtolower(str_replace('$', '', $currency));
				$up = adm_accept_upload('icon', __DIR__ . '/icons', $slug, array('png'));
				if ($up !== '') $MSG['err'][] = 'Project saved, but the icon did not: ' . $up;
				else            $MSG['ok'][]  = 'Saved ' . htmlspecialchars($name) . '.';
				if (!$MSG['err']) { header('Location: admin.php?tab=projects&project=' . $id . '&saved=1'); exit; }
			} else {
				$MSG['err'][] = 'The project row did not save.';
			}
		}
	}

	/* --- a collection under a project --- */
	if ($action === 'collection') {
		$id      = (int)($_POST['collection_id'] ?? 0);
		$project = (int)($_POST['project_id'] ?? 0);
		$name    = trim((string)($_POST['name'] ?? ''));
		$policy  = trim((string)($_POST['policy'] ?? ''));
		$rate    = (float)($_POST['rate'] ?? 0);
		$chain   = (int)($_POST['blockchain_id'] ?? 1);
		$slug    = trim((string)($_POST['marketplace_slug'] ?? ''));

		if ($project <= 0) $MSG['err'][] = 'Pick a project for this collection.';
		if ($name === '')  $MSG['err'][] = 'A collection needs a name.';
		if ($policy === '') $MSG['err'][] = 'A collection needs its on-chain id.';
		/* The identifier is chain-shaped, and getting it wrong matches
		   nothing, raises nothing and looks exactly like a correct run
		   against an empty wallet. See multichain.md section 5. */
		if ($chain === 1 && !preg_match('/^[0-9a-f]{56}$/i', $policy))
			$MSG['err'][] = 'A Cardano policy id is 56 hex characters.';
		if ($chain === 2 && strpos($policy, ':') === false)
			$MSG['err'][] = 'An XRPL collection is issuer:taxon -- run verify-xrpl-probe.php to get it.';
		if ($chain === 3 && !preg_match('/^[1-9A-HJ-NP-Za-km-z]{32,44}$/', $policy))
			$MSG['err'][] = 'A Solana collection is a base58 address -- run verify-solana-probe.php to get it.';
		if ($rate < 0) $MSG['err'][] = 'Rate cannot be negative.';

		if (!$MSG['err']) {
			$n = $conn->real_escape_string($name);
			$p = $conn->real_escape_string($policy);
			$s = $conn->real_escape_string($slug);
			if ($id > 0) $conn->query("UPDATE collections SET name='$n', policy='$p', rate=$rate,
			                           blockchain_id=$chain, marketplace_slug=" . ($s === '' ? 'NULL' : "'$s'") . "
			                           WHERE id=$id");
			else         $conn->query("INSERT INTO collections (blockchain_id, project_id, name, policy, rate, marketplace_slug)
			                           VALUES ($chain, $project, '$n', '$p', $rate, " . ($s === '' ? 'NULL' : "'$s'") . ")");
			header('Location: admin.php?tab=projects&project=' . $project . '&saved=1'); exit;
		}
	}

	/* --- a mission --- */
	if ($action === 'mission') {
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
			if ($id > 0) $conn->query("UPDATE quests SET title='$t', description='$d', extension='$x',
			                           cost=$cost, reward=$reward, duration=$duration, level=$level
			                           WHERE id=$id");
			else         $conn->query("INSERT INTO quests (title, description, extension, project_id, cost, reward, duration, level)
			                           VALUES ('$t','$d','$x',$project,$cost,$reward,$duration,$level)");
			header('Location: admin.php?tab=missions&project=' . $project . '&saved=1'); exit;
		}
	}
}

if (isset($_GET['saved'])) $MSG['ok'][] = 'Saved.';

$PROJECTS = adm_projects($conn);
if ($pid && !isset($PROJECTS[$pid])) $pid = 0;

include 'header.php';
?>
<div class="container"><div class="row"><div class="column">
<div class="adm">

  <div class="adm-head">
    <h2>Admin</h2>
    <nav class="adm-tabs">
      <a href="admin.php?tab=projects<?php echo $pid ? '&project='.$pid : ''; ?>"
         class="<?php echo $tab === 'projects' ? 'on' : ''; ?>">Projects &amp; Collections</a>
      <a href="admin.php?tab=missions<?php echo $pid ? '&project='.$pid : ''; ?>"
         class="<?php echo $tab === 'missions' ? 'on' : ''; ?>">Missions</a>
    </nav>
  </div>

  <?php foreach ($MSG['err'] as $m): ?><p class="adm-msg bad"><?php echo $m; ?></p><?php endforeach; ?>
  <?php foreach ($MSG['ok'] as $m): ?><p class="adm-msg good"><?php echo $m; ?></p><?php endforeach; ?>

  <form method="get" action="admin.php" class="adm-pick">
    <input type="hidden" name="tab" value="<?php echo htmlspecialchars($tab); ?>">
    <label for="project">Project</label>
    <select name="project" id="project" onchange="this.form.submit()">
      <option value="0">&mdash; pick a project &mdash;</option>
      <?php foreach ($PROJECTS as $id => $p): ?>
        <option value="<?php echo $id; ?>" <?php echo $id === $pid ? 'selected' : ''; ?>>
          <?php echo htmlspecialchars($p['name']); ?> (<?php echo htmlspecialchars($p['currency']); ?>)
        </option>
      <?php endforeach; ?>
    </select>
    <noscript><button type="submit">Go</button></noscript>
  </form>

<?php if ($tab === 'projects'): $P = $pid ? $PROJECTS[$pid] : null; ?>

  <section class="adm-card">
    <h3><?php echo $P ? 'Edit ' . htmlspecialchars($P['name']) : 'New project'; ?></h3>
    <form method="post" enctype="multipart/form-data" class="adm-form">
      <input type="hidden" name="action" value="project">
      <input type="hidden" name="project_id" value="<?php echo $pid; ?>">
      <div class="adm-grid">
        <label>Name
          <input type="text" name="name" required value="<?php echo htmlspecialchars($P['name'] ?? ''); ?>"></label>
        <label>Currency
          <input type="text" name="currency" required maxlength="16" pattern="[A-Za-z0-9]{2,16}"
                 value="<?php echo htmlspecialchars($P['currency'] ?? ''); ?>">
          <small>Becomes <code>icons/<em>currency</em>.png</code> and a Skull Swap tile. Must be unique.</small></label>
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
        <p class="adm-note">Current icon: <code><?php echo htmlspecialchars($ic); ?></code>
          <?php if (is_file(__DIR__ . '/' . $ic)): ?>
            <img src="<?php echo htmlspecialchars($ic); ?>" alt="" class="adm-icon">
          <?php else: ?><strong class="warn">&mdash; not on the server yet</strong><?php endif; ?></p>
      <?php endif; ?>
      <button type="submit"><?php echo $P ? 'Save project' : 'Create project'; ?></button>
    </form>
  </section>

  <?php if ($P): $COLS = adm_collections($conn, $pid); ?>
  <section class="adm-card">
    <h3>Collections</h3>
    <?php if ($COLS): ?>
    <div class="adm-tablewrap"><table class="adm-table">
      <thead><tr><th>Name</th><th>Chain</th><th>On-chain id</th><th>Rate</th><th></th></tr></thead>
      <tbody>
      <?php $chains = array(1=>'Cardano', 2=>'XRPL', 3=>'Solana'); foreach ($COLS as $c): ?>
        <tr>
          <td><?php echo htmlspecialchars($c['name']); ?></td>
          <td><?php echo $chains[(int)$c['blockchain_id']] ?? (int)$c['blockchain_id']; ?></td>
          <td class="mono trunc"><?php echo htmlspecialchars($c['policy']); ?></td>
          <td class="mono"><?php echo htmlspecialchars($c['rate']); ?></td>
          <td><a href="admin.php?tab=projects&project=<?php echo $pid; ?>&collection=<?php echo (int)$c['id']; ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody></table></div>
    <?php else: ?><p class="adm-note">No collections yet. Nothing from this project can be staked until one exists.</p><?php endif; ?>

    <?php
      $cid = isset($_GET['collection']) ? (int)$_GET['collection'] : 0;
      $C = null; foreach ($COLS as $c) if ((int)$c['id'] === $cid) $C = $c;
    ?>
    <form method="post" class="adm-form">
      <input type="hidden" name="action" value="collection">
      <input type="hidden" name="project_id" value="<?php echo $pid; ?>">
      <input type="hidden" name="collection_id" value="<?php echo $C ? (int)$C['id'] : 0; ?>">
      <h4><?php echo $C ? 'Edit ' . htmlspecialchars($C['name']) : 'Add a collection'; ?></h4>
      <div class="adm-grid">
        <label>Name <input type="text" name="name" required value="<?php echo htmlspecialchars($C['name'] ?? ''); ?>"></label>
        <label>Chain
          <select name="blockchain_id">
            <?php foreach (array(1=>'Cardano',2=>'XRPL',3=>'Solana') as $k=>$v): ?>
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
          <small>XRPL only. Cardano and Solana build the link from the id.</small></label>
      </div>
      <button type="submit"><?php echo $C ? 'Save collection' : 'Add collection'; ?></button>
    </form>
  </section>
  <?php endif; ?>

<?php else: /* ---------------- missions ---------------- */ ?>

  <?php if (!$pid): ?>
    <p class="adm-note">Pick a project to see its mission ladder.</p>
  <?php else:
    $M = adm_missions($conn, $pid);
    $costs = array(); foreach ($M as $row) $costs[] = (int)$row['cost'];
    $next_level = $M ? (max(array_keys($M)) + 1) : 1;
    $def = admin_mission_defaults($costs, $next_level);
    $ladder = array();
    foreach ($M as $lv => $row) $ladder[$lv] = array('cost'=>(int)$row['cost'], 'reward'=>(int)$row['reward'],
                                                     'duration'=>(int)$row['duration'], 'title'=>$row['title']);
    $problems = $M ? admin_validate_ladder($ladder) : array();
    $qid = isset($_GET['quest']) ? (int)$_GET['quest'] : 0;
    $Q = null; foreach ($M as $row) if ((int)$row['id'] === $qid) $Q = $row;
  ?>
  <section class="adm-card">
    <h3><?php echo htmlspecialchars($PROJECTS[$pid]['name']); ?> &mdash; <?php echo count($M); ?> mission<?php echo count($M)===1?'':'s'; ?></h3>
    <?php if ($problems): ?>
      <p class="adm-msg bad"><strong>This ladder has problems:</strong></p>
      <?php foreach ($problems as $p): ?><p class="adm-msg bad"><?php echo htmlspecialchars($p); ?></p><?php endforeach; ?>
    <?php endif; ?>
    <div class="adm-tablewrap"><table class="adm-table">
      <thead><tr><th>Lvl</th><th>Mission</th><th>Art</th><th>Cost</th><th>Reward</th><th>&times;</th><th>Days</th><th>Net/day</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($M as $lv => $row):
        $c=(int)$row['cost']; $w=(int)$row['reward']; $d=(int)$row['duration'];
        $slug = admin_mission_slug($row['title']);
        $still = $slug . '.' . admin_art_ext($row['extension']);
        $art_ok = is_file(__DIR__ . '/images/missions/' . $still)
               && ($row['extension'] !== 'mp4' || is_file(__DIR__ . '/images/missions/' . $slug . '.mp4'));
      ?>
        <tr>
          <td class="mono"><?php echo (int)$lv; ?></td>
          <td><?php echo htmlspecialchars($row['title']); ?></td>
          <td><?php if ($art_ok): ?><span class="ok-dot" title="<?php echo htmlspecialchars($still); ?>">&#10003;</span>
              <?php else: ?><span class="bad-dot" title="missing <?php echo htmlspecialchars($still); ?>">&#10007;</span><?php endif; ?>
              <?php echo htmlspecialchars($row['extension']); ?></td>
          <td class="mono"><?php echo $c ? number_format($c) : '&mdash;'; ?></td>
          <td class="mono"><?php echo number_format($w); ?></td>
          <td class="mono"><?php echo $c ? number_format($w / $c, 1) : '&mdash;'; ?></td>
          <td class="mono"><?php echo $d; ?></td>
          <td class="mono"><?php echo $d ? number_format(admin_mission_per_day($c, $w, $d)) : '&mdash;'; ?></td>
          <td><a href="admin.php?tab=missions&project=<?php echo $pid; ?>&quest=<?php echo (int)$row['id']; ?>">Edit</a></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table></div>
  </section>

  <section class="adm-card">
    <h3><?php echo $Q ? 'Edit level ' . (int)$Q['level'] : 'Add mission &mdash; level ' . $next_level; ?></h3>
    <form method="post" enctype="multipart/form-data" class="adm-form">
      <input type="hidden" name="action" value="mission">
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
          <small>Reward and duration are derived from this and the level. You cannot set them directly.</small></label>
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
        <strong class="mono"><?php echo number_format($Q ? admin_mission_per_day((int)$Q['cost'],(int)$Q['reward'],(int)$Q['duration']) : $def['per_day']); ?></strong> net/day.</p>
      <button type="submit"><?php echo $Q ? 'Save mission' : 'Add mission'; ?></button>
    </form>
  </section>
  <?php endif; ?>
<?php endif; ?>

</div>
</div></div></div>

<style>
.adm{max-width:980px;margin:0 auto;padding:0 4px 60px;color:#c8d8e8;
     font:400 15px/1.6 Arial,Helvetica,sans-serif}
.adm h2{color:#00c8a0;letter-spacing:.04em;text-transform:uppercase;margin:0}
.adm h3{font-size:1.05rem;letter-spacing:.03em;text-transform:uppercase;color:#c8d8e8;margin:0 0 4px}
.adm h4{font-size:.9rem;letter-spacing:.03em;text-transform:uppercase;color:#5a7888;margin:18px 0 0}
.adm-head{display:flex;flex-wrap:wrap;gap:14px;align-items:baseline;justify-content:space-between;
          padding:18px 0 14px;border-bottom:1px solid rgba(0,200,160,.14)}
.adm-tabs{display:flex;gap:6px;flex-wrap:wrap}
.adm-tabs a{padding:9px 14px;border:1px solid rgba(0,200,160,.14);color:#5a7888;
            text-decoration:none;font-size:.82rem}
.adm-tabs a.on{background:#00c8a0;color:#07111d;border-color:#00c8a0;font-weight:bold}
.adm-msg{margin:12px 0;padding:11px 14px;border-left:3px solid;font-size:.88rem}
.adm-msg.bad{border-color:#e0705f;background:rgba(224,112,95,.1);color:#e0705f}
.adm-msg.good{border-color:#00c8a0;background:rgba(0,200,160,.1);color:#00c8a0}
.adm-pick{display:flex;gap:10px;align-items:center;margin:18px 0}
.adm-pick label{font-size:.72rem;letter-spacing:.14em;text-transform:uppercase;color:#5a7888}
.adm-card{background:#0a1929;border:1px solid rgba(0,200,160,.14);padding:20px;margin:0 0 18px}
.adm-form{display:flex;flex-direction:column;gap:14px;margin-top:12px}
.adm-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:14px}
.adm label{display:flex;flex-direction:column;gap:5px;font-size:.74rem;letter-spacing:.1em;
           text-transform:uppercase;color:#5a7888;min-width:0}
.adm-wide{width:100%}
.adm input,.adm select,.adm textarea{background:#07111d;border:1px solid rgba(0,200,160,.18);
  color:#c8d8e8;padding:9px 10px;font:400 14px/1.4 Arial,sans-serif;text-transform:none;
  letter-spacing:0;border-radius:0;max-width:100%}
.adm input:focus,.adm select:focus,.adm textarea:focus{outline:2px solid #00c8a0;outline-offset:1px}
.adm small{font-size:.72rem;letter-spacing:0;text-transform:none;color:#5a7888;line-height:1.45}
.adm button{background:#00c8a0;color:#07111d;border:0;padding:11px 20px;font-weight:bold;
            cursor:pointer;align-self:flex-start;font-size:.85rem}
.adm button:hover{background:#00e0b4}
.adm-note{font-size:.84rem;color:#5a7888;margin:10px 0 0}
.adm-note .warn{color:#e8b14c}
.adm-icon{width:26px;height:26px;vertical-align:middle;margin-left:6px}
.adm-tablewrap{overflow-x:auto;margin-top:12px}
.adm-table{border-collapse:collapse;width:100%;min-width:620px;font-size:.86rem}
.adm-table th{text-align:left;padding:9px 10px;border-bottom:1px solid rgba(0,200,160,.14);
  color:#5a7888;font-size:.68rem;letter-spacing:.12em;text-transform:uppercase;font-weight:normal}
.adm-table td{padding:8px 10px;border-bottom:1px solid rgba(0,200,160,.06)}
.adm-table .mono{font-family:ui-monospace,Menlo,monospace;font-variant-numeric:tabular-nums}
.adm-table .trunc{max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.adm-table a{color:#00c8a0}
.ok-dot{color:#00c8a0}.bad-dot{color:#e0705f}
@media (max-width:560px){.adm-head{flex-direction:column;align-items:flex-start}}
</style>
