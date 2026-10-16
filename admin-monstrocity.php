<?php
/*
 * Monstrocity themes: list, create, edit.
 *
 * THE JOB IS ADDING THEMES, not editing the 49 that already exist. So the
 * list is deliberately plain -- it is for finding a theme, not working in --
 * and everything that could be fussy about creating one is handled for you:
 * the folders are made, the art is resized and written, and the fields that
 * can be derived are derived.
 *
 * Phase 2 of the plan: themes and their two theme-level images. The 14x3
 * character set is Phase 3 and gets its own staging screen; the "Art" column
 * here just reports what is on disk so far.
 *
 * Writes into images/monstrocity/<slug>/, which is FTP territory and outside
 * git. That is the same channel, not a new one -- nothing written here is
 * ever committed. See the images-by-FTP rule.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();                       // before any output, and before header.php
require_once __DIR__ . '/monstrocity-themes-lib.php';

$msgs = array('err' => array(), 'ok' => array());
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$creating = isset($_GET['new']);

/* ---------- helpers that need $conn ---------------------------------------- */

function mon_theme_row($conn, $id) {
	$id = (int)$id;
	$r = $conn->query('SELECT * FROM monstrocity_themes WHERE id = ' . $id);
	return $r ? $r->fetch_assoc() : null;
}
function mon_all_slugs($conn, $except = 0) {
	$out = array();
	$r = $conn->query('SELECT id, slug FROM monstrocity_themes');
	if ($r) while ($x = $r->fetch_assoc()) if ((int)$x['id'] !== (int)$except) $out[] = $x['slug'];
	return $out;
}
function mon_groups($conn) {
	$out = array();
	$r = $conn->query('SELECT DISTINCT theme_group, group_order FROM monstrocity_themes ORDER BY group_order ASC');
	if ($r) while ($x = $r->fetch_assoc()) $out[$x['theme_group']] = (int)$x['group_order'];
	return $out;
}
function mon_links($conn, $theme_id) {
	$out = array();
	$r = $conn->query('SELECT collection_id FROM monstrocity_theme_collections
	                    WHERE theme_id = ' . (int)$theme_id . ' ORDER BY sort_order ASC');
	if ($r) while ($x = $r->fetch_assoc()) $out[] = (int)$x['collection_id'];
	return $out;
}
function mon_collections($conn) {
	$out = array();
	$r = $conn->query('SELECT c.id, c.name, c.policy, p.name AS project
	                     FROM collections c LEFT JOIN projects p ON p.id = c.project_id
	                    ORDER BY p.name ASC, c.name ASC');
	if ($r) while ($x = $r->fetch_assoc()) $out[] = $x;
	return $out;
}
/* What art exists on disk for a theme, so the list can say what is still
   missing without anyone opening an FTP client. */
function mon_art_state($slug, $ext) {
	$dir = __DIR__ . '/' . monstrocity_theme_dir($slug);
	$chars = require __DIR__ . '/monstrocity-default-characters.php';
	$have = array('background' => is_file($dir . '/monstrocity.png'),
	              'logo'       => is_file($dir . '/logo.png'));
	foreach (monstrocity_theme_subdirs() as $sub) {
		$n = 0;
		foreach ($chars as $c) {
			$f = $dir . '/' . $sub . '/' . str_replace(' ', '-', strtolower($c['name'])) . '.' . $ext;
			if (is_file($f)) $n++;
		}
		$have[$sub] = $n;
	}
	return $have;
}

/* ---------- save ----------------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$id  = (int)($_POST['id'] ?? 0);
	$t = array(
		'slug'            => strtolower(trim($_POST['slug'] ?? '')),
		'project_id'      => (int)($_POST['project_id'] ?? 0),
		'project_name'    => trim($_POST['project_name'] ?? ''),
		'title'           => trim($_POST['title'] ?? ''),
		'policy_ids'      => trim($_POST['policy_ids'] ?? ''),
		'orientations'    => $_POST['orientations'] ?? '',
		'ipfs_prefixes'   => trim($_POST['ipfs_prefixes'] ?? ''),
		'background'      => empty($_POST['background']) ? 0 : 1,
		'extension'       => $_POST['extension'] ?? 'png',
		'theme_group'     => trim($_POST['theme_group_new'] ?? '') !== ''
		                     ? trim($_POST['theme_group_new']) : trim($_POST['theme_group'] ?? ''),
		'req_innercircle' => empty($_POST['req_innercircle']) ? 0 : 1,
		'req_dev'         => empty($_POST['req_dev']) ? 0 : 1,
		'active'          => empty($_POST['active']) ? 0 : 1,
	);
	$links = monstrocity_clean_collection_ids($_POST['collections'] ?? array());
	$errs  = monstrocity_validate_theme($t, mon_all_slugs($conn, $id));

	/* A slug change would orphan the art, so it is refused rather than
	   silently leaving 42 files in a folder nothing points at. Renaming a
	   theme's folder is a job for Phase 3, where the art is being moved
	   anyway and there is something to show for it. */
	if ($id) {
		$was = mon_theme_row($conn, $id);
		if ($was && $was['slug'] !== $t['slug'])
			$errs[] = 'The folder name cannot be changed once art has been uploaded -- '
			        . 'the images under ' . monstrocity_theme_dir($was['slug']) . '/ would be orphaned.';
	}

	if ($errs) {
		$msgs['err'] = $errs;
		$edit_id = $id; $creating = !$id;
	} else {
		$groups = mon_groups($conn);
		$gorder = isset($groups[$t['theme_group']]) ? $groups[$t['theme_group']] : (count($groups) ? max($groups) + 1 : 0);
		$esc = function ($v) use ($conn) { return "'" . $conn->real_escape_string($v) . "'"; };

		if ($id) {
			$sql = 'UPDATE monstrocity_themes SET '
			     . 'project_id='      . ($t['project_id'] ? (int)$t['project_id'] : 'NULL') . ','
			     . 'project_name='    . $esc($t['project_name']) . ','
			     . 'title='           . $esc($t['title']) . ','
			     . 'policy_ids='      . $esc($t['policy_ids']) . ','
			     . 'orientations='    . $esc($t['orientations']) . ','
			     . 'ipfs_prefixes='   . $esc($t['ipfs_prefixes']) . ','
			     . 'background='      . (int)$t['background'] . ','
			     . 'extension='       . $esc($t['extension']) . ','
			     . 'theme_group='     . $esc($t['theme_group']) . ','
			     . 'group_order='     . (int)$gorder . ','
			     . 'req_innercircle=' . (int)$t['req_innercircle'] . ','
			     . 'req_dev='         . (int)$t['req_dev'] . ','
			     . 'active='          . (int)$t['active'] . ','
			     . 'updated_at=NOW() WHERE id=' . $id;
			$conn->query($sql);
		} else {
			/* New themes land at the end of their group's run, so the picker
			   order never shuffles under an existing theme. */
			$r = $conn->query('SELECT COALESCE(MAX(sort_order),0)+1 AS n FROM monstrocity_themes');
			$next = $r ? (int)$r->fetch_assoc()['n'] : 1;
			$conn->query('INSERT INTO monstrocity_themes
				(slug, project_id, project_name, title, policy_ids, orientations, ipfs_prefixes,
				 background, extension, theme_group, group_order, req_innercircle, req_dev,
				 sort_order, active, created_at) VALUES ('
				. $esc($t['slug']) . ','
				. ($t['project_id'] ? (int)$t['project_id'] : 'NULL') . ','
				. $esc($t['project_name']) . ',' . $esc($t['title']) . ','
				. $esc($t['policy_ids']) . ',' . $esc($t['orientations']) . ','
				. $esc($t['ipfs_prefixes']) . ',' . (int)$t['background'] . ','
				. $esc($t['extension']) . ',' . $esc($t['theme_group']) . ',' . (int)$gorder . ','
				. (int)$t['req_innercircle'] . ',' . (int)$t['req_dev'] . ','
				. $next . ',' . (int)$t['active'] . ', NOW())');
			$id = (int)$conn->insert_id;
			$msgs['ok'][] = 'Created "' . htmlspecialchars($t['title']) . '".';
		}

		/* Links are replaced wholesale: the form submits the complete set, so
		   a diff would only be a way to get it subtly wrong. */
		$conn->query('DELETE FROM monstrocity_theme_collections WHERE theme_id = ' . (int)$id);
		foreach ($links as $i => $cid)
			$conn->query('INSERT INTO monstrocity_theme_collections (theme_id, collection_id, sort_order) VALUES ('
				. (int)$id . ',' . (int)$cid . ',' . (int)$i . ')');

		/* Folders first, so an upload never fails on a missing directory and
		   so Phase 3 has somewhere to put things. */
		$dir = __DIR__ . '/' . monstrocity_theme_dir($t['slug']);
		@mkdir($dir, 0755, true);
		foreach (monstrocity_theme_subdirs() as $sub) @mkdir($dir . '/' . $sub, 0755, true);

		/* Background and logo. Both capped on the long edge and re-encoded;
		   neither is forced to an aspect ratio, because a background is chosen
		   to sit either side of the board and there is no one shape for that. */
		$up = adm_accept_upload('bg_file',   $dir, 'monstrocity', array('png','jpg','gif'));
		if ($up !== '') $msgs['err'][] = 'Background: ' . $up;
		$up = adm_accept_upload('logo_file', $dir, 'logo',        array('png','jpg','gif'));
		if ($up !== '') $msgs['err'][] = 'Logo: ' . $up;

		if (!$msgs['err']) $msgs['ok'][] = 'Saved.';
		$edit_id = $id; $creating = false;
	}
}

/* ---------- read for display ----------------------------------------------- */

$themes = array();
$r = $conn->query('SELECT * FROM monstrocity_themes ORDER BY sort_order ASC, id ASC');
if ($r) while ($x = $r->fetch_assoc()) $themes[] = $x;

$linkCount = array();
$r = $conn->query('SELECT theme_id, COUNT(*) AS n FROM monstrocity_theme_collections GROUP BY theme_id');
if ($r) while ($x = $r->fetch_assoc()) $linkCount[(int)$x['theme_id']] = (int)$x['n'];

$projects    = adm_projects($conn);
$allGroups   = mon_groups($conn);
$collections = mon_collections($conn);
$row         = $edit_id ? mon_theme_row($conn, $edit_id) : null;
$rowLinks    = $edit_id ? mon_links($conn, $edit_id) : array();
if ($creating && !$row) {
	$row = array('id'=>0,'slug'=>'','project_id'=>0,'project_name'=>'','title'=>'','policy_ids'=>'',
	             'orientations'=>'','ipfs_prefixes'=>'https://ipfs.io/ipfs/','background'=>1,
	             'extension'=>'png','theme_group'=>'','req_innercircle'=>0,'req_dev'=>0,'active'=>1);
}

include 'header.php';
admin_chrome('monstrocity');
?>
<style>
.mt-wrap { display:grid; grid-template-columns: minmax(0,1fr); gap:18px }
@media (min-width:1100px) { .mt-wrap.editing { grid-template-columns: minmax(0,420px) minmax(0,1fr) } }
table.mt { width:100%; border-collapse:collapse; font-size:12px }
table.mt th { text-align:left; font-weight:600; color:#7a9eb0; padding:6px 8px; border-bottom:1px solid rgba(0,200,160,.18); white-space:nowrap }
table.mt td { padding:6px 8px; border-bottom:1px solid rgba(255,255,255,.05); vertical-align:middle }
table.mt tr.on td { background:rgba(0,200,160,.07) }
table.mt a.slug { font-family:ui-monospace,Menlo,monospace; text-align:left; color:#00c8a0 }
.mt-pill { display:inline-block; font-size:10px; padding:1px 6px; border-radius:999px; border:1px solid rgba(255,255,255,.18); color:#9fb4c2 }
.mt-pill.ic { border-color:#f5a623; color:#f5a623 }
.mt-pill.dev{ border-color:#e0466b; color:#e0466b }
.mt-pill.off{ opacity:.5; text-decoration:line-through }
.mt-art { font-family:ui-monospace,Menlo,monospace; font-size:11px; color:#7a9eb0; white-space:nowrap }
.mt-art b { color:#00c8a0; font-weight:600 }
.mt-art i { color:#e0466b; font-style:normal }
.mt-form { background:#0a1929; border:1px solid rgba(0,200,160,.15); border-radius:5px; padding:14px }
.mt-form h3 { margin:0 0 10px; font-size:14px }
.mt-form label { display:block; font-size:11px; color:#7a9eb0; margin:10px 0 3px }
.mt-form input[type=text], .mt-form select, .mt-form textarea {
  width:100%; box-sizing:border-box; background:#07111d; color:#e8eaed;
  border:1px solid rgba(255,255,255,.14); border-radius:3px; padding:6px 8px; font-size:12px }
.mt-form textarea { min-height:52px; font-family:ui-monospace,Menlo,monospace; font-size:11px }
.mt-form .row2 { display:flex; gap:10px } .mt-form .row2 > * { flex:1; min-width:0 }
.mt-check { display:flex; align-items:center; gap:6px; font-size:12px; color:#e8eaed; margin:9px 0 }
.mt-check input { margin:0 }
.mt-note { font-size:11px; color:#5a7888; margin:4px 0 0; line-height:1.45 }
.mt-cols { max-height:190px; overflow:auto; border:1px solid rgba(255,255,255,.12); border-radius:3px; padding:6px; background:#07111d }
.mt-cols label { display:flex; gap:6px; align-items:flex-start; margin:0 0 5px; color:#e8eaed; font-size:11px }
.mt-cols input { margin:2px 0 0 }
.mt-cols .pol { font-family:ui-monospace,Menlo,monospace; color:#5a7888; font-size:10px }
.mt-actions { display:flex; gap:8px; align-items:center; margin-top:14px }
</style>

<p class="adm-sub">
  <b><?php echo count($themes); ?></b> themes. The game reads this table directly &mdash;
  there is no theme list in the code any more.
  <a class="adm-btn" href="admin-monstrocity.php?new=1" style="margin-left:8px">New theme</a>
</p>
<?php admin_flash($msgs); ?>

<div class="mt-wrap<?php echo $row ? ' editing' : ''; ?>">

<?php if ($row): ?>
  <form class="mt-form" method="post" enctype="multipart/form-data" action="admin-monstrocity.php">
    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
    <h3><?php echo $row['id'] ? 'Edit ' . htmlspecialchars($row['title']) : 'New theme'; ?></h3>

    <label>Folder name</label>
    <input type="text" name="slug" value="<?php echo htmlspecialchars($row['slug']); ?>"
           <?php echo $row['id'] ? 'readonly' : ''; ?> placeholder="happyhoppers">
    <p class="mt-note">
      <?php if ($row['id']): ?>
        Fixed once created &mdash; the art under <code><?php echo htmlspecialchars(monstrocity_theme_dir($row['slug'])); ?>/</code>
        is addressed by it.
      <?php else: ?>
        Lowercase, numbers and hyphens. Becomes <code>images/monstrocity/&lt;name&gt;/</code>
        and cannot be changed afterwards.
      <?php endif; ?>
    </p>

    <div class="row2">
      <div>
        <label>Project name (shown to players)</label>
        <input type="text" name="project_name" value="<?php echo htmlspecialchars($row['project_name']); ?>">
      </div>
      <div>
        <label>Theme title</label>
        <input type="text" name="title" value="<?php echo htmlspecialchars($row['title']); ?>">
      </div>
    </div>

    <label>Skulliance project (optional)</label>
    <select name="project_id">
      <option value="0">&mdash; not a Skulliance project &mdash;</option>
      <?php foreach ($projects as $pid => $p): ?>
        <option value="<?php echo (int)$pid; ?>"<?php echo (int)$row['project_id'] === (int)$pid ? ' selected' : ''; ?>>
          <?php echo htmlspecialchars($p['name']); ?></option>
      <?php endforeach; ?>
    </select>
    <p class="mt-note">Plenty of themes are for projects that are not on Skulliance. Leaving this
       unset is normal; the project name above is what players see either way.</p>

    <div class="row2">
      <div>
        <label>Group</label>
        <select name="theme_group">
          <option value="">&mdash; pick &mdash;</option>
          <?php foreach ($allGroups as $g => $o): ?>
            <option value="<?php echo htmlspecialchars($g); ?>"<?php echo $row['theme_group'] === $g ? ' selected' : ''; ?>>
              <?php echo htmlspecialchars($g); ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>&hellip;or a new group</label>
        <input type="text" name="theme_group_new" placeholder="leave blank to use the pick">
      </div>
    </div>

    <div class="row2">
      <div>
        <label>Source art faces</label>
        <select name="orientations">
          <?php foreach (monstrocity_orientations() as $o): ?>
            <option value="<?php echo $o; ?>"<?php echo $row['orientations'] === $o ? ' selected' : ''; ?>>
              <?php echo $o === '' ? '— unset —' : $o; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label>Character file type</label>
        <select name="extension">
          <?php foreach (monstrocity_extensions() as $e): ?>
            <option value="<?php echo $e; ?>"<?php echo $row['extension'] === $e ? ' selected' : ''; ?>>.<?php echo $e; ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>
    <p class="mt-note"><b>Random</b> means the collection's art faces both ways, so each
       character is decided by eye rather than flipped as a batch.</p>

    <label>Collections this theme covers</label>
    <div class="mt-cols">
      <?php if (!$collections): ?><em style="color:#5a7888;font-size:11px">No collections on the platform.</em><?php endif; ?>
      <?php foreach ($collections as $c): ?>
        <label>
          <input type="checkbox" name="collections[]" value="<?php echo (int)$c['id']; ?>"
                 <?php echo in_array((int)$c['id'], $rowLinks, true) ? 'checked' : ''; ?>>
          <span><?php echo htmlspecialchars($c['name']); ?>
            <?php if ($c['project']): ?><span class="pol">&middot; <?php echo htmlspecialchars($c['project']); ?></span><?php endif; ?>
            <br><span class="pol"><?php echo htmlspecialchars(substr((string)$c['policy'], 0, 24)); ?>&hellip;</span>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="mt-note">Tick any and the policy ids come from those collections, so a policy is
       changed in one place. Tick none and the list below is used as-is.</p>

    <label>Policy ids (used only when no collection is ticked)</label>
    <textarea name="policy_ids" spellcheck="false"><?php echo htmlspecialchars($row['policy_ids']); ?></textarea>
    <p class="mt-note">Comma separated, 56 hex characters each. A truncated one is refused here
       rather than becoming a theme that silently matches nobody.</p>

    <label>IPFS gateway prefix</label>
    <input type="text" name="ipfs_prefixes" value="<?php echo htmlspecialchars($row['ipfs_prefixes']); ?>">

    <div class="row2">
      <div>
        <label>Background image (<code>monstrocity.png</code>)</label>
        <input type="file" name="bg_file" accept="image/*">
      </div>
      <div>
        <label>Project logo (<code>logo.png</code>)</label>
        <input type="file" name="logo_file" accept="image/*">
      </div>
    </div>
    <p class="mt-note">Resized on the long edge and re-encoded. No fixed shape &mdash; a background
       is picked to read either side of the board, and that is not one ratio.</p>

    <label class="mt-check"><input type="checkbox" name="background" value="1" <?php echo !empty($row['background']) ? 'checked' : ''; ?>> Theme has a background image</label>
    <label class="mt-check"><input type="checkbox" name="req_innercircle" value="1" <?php echo !empty($row['req_innercircle']) ? 'checked' : ''; ?>> Inner Circle only</label>
    <label class="mt-check"><input type="checkbox" name="req_dev" value="1" <?php echo !empty($row['req_dev']) ? 'checked' : ''; ?>> Dev only <span class="mt-note" style="margin:0">(must also be Inner Circle)</span></label>
    <label class="mt-check"><input type="checkbox" name="active" value="1" <?php echo !empty($row['active']) ? 'checked' : ''; ?>> Active</label>

    <div class="mt-actions">
      <button class="adm-btn" type="submit"><?php echo $row['id'] ? 'Save' : 'Create theme'; ?></button>
      <a class="adm-btn" href="admin-monstrocity.php">Cancel</a>
    </div>
  </form>
<?php endif; ?>

  <div>
    <table class="mt">
      <tr><th>#</th><th>Folder</th><th>Project</th><th>Title</th><th>Group</th>
          <th>Policies</th><th>Art on disk</th><th></th></tr>
      <?php foreach ($themes as $t):
        $a = mon_art_state($t['slug'], $t['extension']);
        $nlinks = isset($linkCount[(int)$t['id']]) ? $linkCount[(int)$t['id']] : 0;
        $npol = $t['policy_ids'] === '' ? 0 : count(explode(',', $t['policy_ids'])); ?>
      <tr class="<?php echo (int)$t['id'] === (int)$edit_id ? 'on' : ''; ?>">
        <td style="color:#5a7888"><?php echo (int)$t['sort_order']; ?></td>
        <td><a class="slug" href="admin-monstrocity.php?edit=<?php echo (int)$t['id']; ?>"><?php echo htmlspecialchars($t['slug']); ?></a></td>
        <td><?php echo htmlspecialchars($t['project_name']); ?></td>
        <td><?php echo htmlspecialchars($t['title']); ?>
            <?php if (empty($t['active'])): ?> <span class="mt-pill off">off</span><?php endif; ?>
            <?php if (!empty($t['req_dev'])): ?> <span class="mt-pill dev">dev</span>
            <?php elseif (!empty($t['req_innercircle'])): ?> <span class="mt-pill ic">inner</span><?php endif; ?></td>
        <td style="color:#7a9eb0"><?php echo htmlspecialchars($t['theme_group']); ?></td>
        <td class="mt-art">
          <?php if ($nlinks): ?><b><?php echo $nlinks; ?> collection<?php echo $nlinks === 1 ? '' : 's'; ?></b>
          <?php elseif ($npol): ?><?php echo $npol; ?> listed
          <?php else: ?><i>none</i><?php endif; ?>
        </td>
        <td class="mt-art">
          <?php foreach (array('base','leader','battle-damaged') as $k):
            $n = $a[$k]; ?><?php echo substr($k,0,1); ?><?php
            echo $n === 14 ? '<b>14</b>' : ($n === 0 ? '<i>0</i>' : $n); ?><?php echo $k === 'battle-damaged' ? '' : ' '; ?><?php endforeach; ?>
          <?php echo $a['background'] ? ' <b>bg</b>' : ' <i>bg</i>'; ?><?php echo $a['logo'] ? ' <b>logo</b>' : ' <i>logo</i>'; ?>
        </td>
        <td><a class="adm-btn" href="admin-monstrocity.php?edit=<?php echo (int)$t['id']; ?>">Edit</a></td>
      </tr>
      <?php endforeach; ?>
    </table>
  </div>
</div>
<?php include 'admin-css.php'; ?>
