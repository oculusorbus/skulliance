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
require_once __DIR__ . '/monstrocity-art-lib.php';   // the logo box

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
	$r = $conn->query('SELECT c.id, c.name, c.policy, c.project_id, p.name AS project
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

/*
 * A POST OVER post_max_size ARRIVES AS NO POST AT ALL.
 *
 * PHP discards $_POST and $_FILES before the script runs -- no warning, and
 * nothing in $_FILES['x']['error'], because $_FILES does not exist either.
 * The page then sees an empty form and reports whichever field it validates
 * first, so an oversized background image was reported as "the title is
 * required" and everything typed was thrown away with it.
 *
 * CONTENT_LENGTH is still there, which is how this is detectable at all.
 */
$post_discarded = monstrocity_post_discarded(
	$_SERVER['REQUEST_METHOD'] ?? '', $_POST, $_SERVER['CONTENT_LENGTH'] ?? 0);
if ($post_discarded) {
	$sent = (int)$_SERVER['CONTENT_LENGTH'];
	$msgs['err'][] = 'That upload was too large, so the server discarded the whole form before '
	               . 'this page ran -- which is why nothing you typed came back. You sent '
	               . number_format($sent / 1048576, 1) . 'MB; the limit is '
	               . htmlspecialchars((string)ini_get('post_max_size')) . ' per form and '
	               . htmlspecialchars((string)ini_get('upload_max_filesize')) . ' per file. '
	               . 'Shrink the image, or raise both in .user.ini.';
	$creating = true;      // fall through to an empty form rather than the list
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$post_discarded) {
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

	/* Blank means inherit. Resolved here, once, so the stored row always has
	   a usable name even if the project is later unlinked -- the alternative
	   is a theme that renders a blank label the day somebody edits a project. */
	$t['project_name'] = monstrocity_display_project($t['project_name'], $t['project_id'], adm_projects($conn));

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
		/* HAND BACK WHAT THEY TYPED. Rebuilding the form from the database
		   on a validation failure discards the whole submission, which turns
		   one missing field into retyping everything -- and uploads cannot be
		   restored by anyone, so losing the text as well is the difference
		   between re-picking two files and starting over. */
		$repost      = $t;
		$repostLinks = $links;
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
		$up = adm_accept_upload('bg_file', $dir, 'monstrocity', array('png','jpg','gif'));
		if ($up !== '') $msgs['err'][] = 'Background: ' . $up;

		/*
		 * THE LOGO GETS FITTED, NOT JUST RESIZED.
		 *
		 * adm_accept_upload() caps the long edge and keeps the aspect, which
		 * is right for a background and wrong here. monstrocity.php renders
		 * the logo as max-width:300 with a free height, so a tall logo towers,
		 * a wide one is scaled down below everything else, and a narrow one
		 * looks dwarfed. Letterboxing every logo into one 300x125 transparent
		 * box makes all three render identically -- and it is the transparent
		 * padding that was previously being added by hand in Photoshop.
		 */
		if (isset($_FILES['logo_file']) && $_FILES['logo_file']['error'] !== UPLOAD_ERR_NO_FILE) {
			if ($_FILES['logo_file']['error'] !== UPLOAD_ERR_OK) {
				$msgs['err'][] = 'Logo: upload failed (code ' . (int)$_FILES['logo_file']['error'] . ').';
			} else {
				list($lw, $lnote) = mart_accept_logo($_FILES['logo_file']['tmp_name'], $t['slug']);
				if ($lw === '')        $msgs['err'][] = 'Logo: ' . $lnote;
				elseif ($lnote !== '') $msgs['ok'][]  = 'Logo saved. ' . $lnote;
				else                   $msgs['ok'][]  = 'Logo fitted to '
				                                      . MONSTROCITY_LOGO_W . 'x' . MONSTROCITY_LOGO_H . '.';
			}
		}

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
/* The submission wins over the stored row, so a refused save redisplays what
   was actually sent rather than what is in the table. */
if (isset($repost)) {
	$row      = array_merge((array)$row, $repost);
	$rowLinks = $repostLinks;
}
/* A brand-new group name is not in the dropdown yet, so it would vanish from
   a redisplayed form. Put it back in the field it came from. */
$group_new = isset($repost) ? monstrocity_group_override($row['theme_group'], $allGroups) : '';

include 'header.php';
admin_chrome('monstrocity');
?>
<style>
/* THE FORM IS ITS OWN PAGE. It was a 420px column beside the list holding
   seventeen controls, which is a column doing a page's job. List and form are
   now separate views of one URL: no ?edit / ?new shows the list, either one
   shows only the form. */
table.mt { width:100%; border-collapse:collapse; font-size:12px }
table.mt th { text-align:left; font-weight:600; color:#7a9eb0; padding:7px 8px; border-bottom:1px solid rgba(0,200,160,.18); white-space:nowrap }
table.mt td { padding:7px 8px; border-bottom:1px solid rgba(255,255,255,.05); vertical-align:middle }
table.mt a.slug { font-family:ui-monospace,Menlo,monospace; text-align:left; color:#00c8a0 }
.mt-pill { display:inline-block; font-size:10px; padding:1px 6px; border-radius:999px; border:1px solid rgba(255,255,255,.18); color:#9fb4c2 }
.mt-pill.ic { border-color:#f5a623; color:#f5a623 }
.mt-pill.dev{ border-color:#e0466b; color:#e0466b }
.mt-pill.off{ opacity:.5; text-decoration:line-through }
.mt-art { font-family:ui-monospace,Menlo,monospace; font-size:11px; color:#7a9eb0; white-space:nowrap }
.mt-art b { color:#00c8a0; font-weight:600 } .mt-art i { color:#e0466b; font-style:normal }

/* Full width. The cap made sense when the form shared the page with the
   list; on its own page it just wasted the right-hand third. The grid
   below is what keeps fields a readable width, not an outer limit. */
.mt-page { max-width:none }
.mt-sect { background:#0a1929; border:1px solid rgba(0,200,160,.15); border-radius:5px; padding:16px 18px; margin:0 0 14px }
.mt-sect > h3 { margin:0 0 3px; font-size:13px; letter-spacing:.04em; text-transform:uppercase; color:#00c8a0 }
.mt-sect > .lead { margin:0 0 14px; font-size:11px; color:#5a7888; line-height:1.5 }
.mt-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(260px,320px)); gap:14px 22px;
           justify-content:start }
.mt-field label { display:block; font-size:11px; color:#7a9eb0; margin:0 0 4px; text-align:left }
.mt-field input[type=text], .mt-field select, .mt-field textarea, .mt-field input[type=file] {
  width:100%; box-sizing:border-box; background:#07111d; color:#e8eaed;
  border:1px solid rgba(255,255,255,.14); border-radius:3px; padding:7px 9px; font-size:12px }
.mt-field textarea { min-height:64px; font-family:ui-monospace,Menlo,monospace; font-size:11px }
.mt-field .hint { font-size:11px; color:#5a7888; margin:5px 0 0; line-height:1.45 }
.mt-field.wide { grid-column:1/-1 }
.mt-check { display:flex; align-items:flex-start; gap:7px; font-size:12px; color:#e8eaed; margin:0 0 9px }
.mt-check input { margin:2px 0 0 } .mt-check span.sub { color:#5a7888; font-size:11px }
.mt-cols { max-height:260px; overflow:auto; border:1px solid rgba(255,255,255,.12); border-radius:3px; padding:8px; background:#07111d }
.mt-cols label { display:flex; gap:7px; align-items:flex-start; margin:0 0 7px; color:#e8eaed; font-size:11px; text-align:left }
.mt-cols label.hid { display:none }
.mt-cols input { margin:2px 0 0 } .mt-cols .pol { font-family:ui-monospace,Menlo,monospace; color:#5a7888; font-size:10px }
.mt-colbar { display:flex; align-items:center; gap:12px; font-size:11px; color:#7a9eb0; margin:0 0 8px }
.mt-actions { display:flex; gap:9px; align-items:center; margin-top:4px }
.mt-back { font-size:12px; display:inline-block; margin:0 0 12px; text-align:left }
</style>

<?php if (!$row): /* ================= LIST ================= */ ?>

  <p class="adm-sub">
    <b><?php echo count($themes); ?></b> themes. The game reads this table directly &mdash;
    there is no theme list in the code any more.
    <a class="adm-btn" href="admin-monstrocity.php?new=1" style="margin-left:8px">New theme</a>
  </p>
  <?php admin_flash($msgs); ?>
  <table class="mt">
    <tr><th>#</th><th>Folder</th><th>Project</th><th>Title</th><th>Group</th>
        <th>Policies</th><th>Art on disk</th><th></th></tr>
    <?php foreach ($themes as $t):
      $a = mon_art_state($t['slug'], $t['extension']);
      $nlinks = isset($linkCount[(int)$t['id']]) ? $linkCount[(int)$t['id']] : 0;
      $npol = $t['policy_ids'] === '' ? 0 : count(explode(',', $t['policy_ids'])); ?>
    <tr>
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
        <?php else: ?><i>none</i><?php endif; ?></td>
      <td class="mt-art">
        <?php foreach (array('base','leader','battle-damaged') as $k): $n = $a[$k];
          echo substr($k,0,1) . ($n === 14 ? '<b>14</b>' : ($n === 0 ? '<i>0</i>' : $n)) . ' ';
        endforeach;
        echo $a['background'] ? '<b>bg</b>' : '<i>bg</i>';
        echo $a['logo'] ? ' <b>logo</b>' : ' <i>logo</i>'; ?></td>
      <td style="white-space:nowrap">
        <a class="adm-btn" href="admin-monstrocity.php?edit=<?php echo (int)$t['id']; ?>">Edit</a>
        <a class="adm-btn" href="admin-monstrocity-art.php?theme=<?php echo (int)$t['id']; ?>">Art</a>
      </td>
    </tr>
    <?php endforeach; ?>
  </table>

<?php else: /* ================= FORM ================= */ ?>

  <div class="mt-page">
    <a class="mt-back" href="admin-monstrocity.php">&larr; All themes</a>
    <?php admin_flash($msgs); ?>
    <form method="post" enctype="multipart/form-data" action="admin-monstrocity.php">
      <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

      <div class="mt-sect">
        <h3><?php echo $row['id'] ? 'Theme' : 'New theme'; ?></h3>
        <p class="lead"><?php echo $row['id']
          ? 'Editing ' . htmlspecialchars($row['title']) . '.'
          : 'The folder is created for you, with base, leader and battle-damaged inside it.'; ?></p>
        <div class="mt-grid">
          <div class="mt-field">
            <label>Folder name</label>
            <input type="text" name="slug" value="<?php echo htmlspecialchars($row['slug']); ?>"
                   <?php echo $row['id'] ? 'readonly' : ''; ?> placeholder="happyhoppers">
            <p class="hint"><?php echo $row['id']
              ? 'Fixed once created &mdash; the art under <code>' . htmlspecialchars(monstrocity_theme_dir($row['slug'])) . '/</code> is addressed by it.'
              : 'Lowercase, digits and hyphens. Becomes <code>images/monstrocity/&lt;name&gt;/</code> and cannot be changed later.'; ?></p>
          </div>
          <div class="mt-field">
            <label>Theme title</label>
            <input type="text" name="title" value="<?php echo htmlspecialchars($row['title']); ?>" placeholder="Hoppy Valentine's">
            <p class="hint">This particular theme's name, shown under the project.</p>
          </div>
          <div class="mt-field">
            <label>Skulliance project</label>
            <select name="project_id" id="mt-proj">
              <option value="0" data-name="">&mdash; not a Skulliance project &mdash;</option>
              <?php foreach ($projects as $pid => $p): ?>
                <option value="<?php echo (int)$pid; ?>" data-name="<?php echo htmlspecialchars($p['name']); ?>"
                  <?php echo (int)$row['project_id'] === (int)$pid ? ' selected' : ''; ?>>
                  <?php echo htmlspecialchars($p['name']); ?></option>
              <?php endforeach; ?>
            </select>
            <p class="hint">Fills the name below and narrows the collection list. Plenty of themes
               are for projects that are not on Skulliance; leaving this unset is normal.</p>
          </div>
          <div class="mt-field">
            <label>Project name shown to players</label>
            <input type="text" name="project_name" id="mt-pname" value="<?php echo htmlspecialchars($row['project_name']); ?>">
            <p class="hint">Blank uses the linked project's name. Type here only to override it.</p>
          </div>
        </div>
      </div>

      <div class="mt-sect">
        <h3>Which NFTs this theme is for</h3>
        <p class="lead">Tick collections and the policy ids come from them, so a policy is changed
           in one place. Tick none and the list underneath is used as-is.</p>
        <div class="mt-colbar">
          <label class="mt-check" style="margin:0">
            <input type="checkbox" id="mt-allcols"> <span>Show every project's collections</span>
          </label>
          <span id="mt-colcount"></span>
        </div>
        <div class="mt-cols" id="mt-cols">
          <?php if (!$collections): ?><em style="color:#5a7888;font-size:11px">No collections on the platform.</em><?php endif; ?>
          <?php foreach ($collections as $c): $on = in_array((int)$c['id'], $rowLinks, true); ?>
            <label data-project="<?php echo (int)$c['project_id']; ?>">
              <input type="checkbox" name="collections[]" value="<?php echo (int)$c['id']; ?>" <?php echo $on ? 'checked' : ''; ?>>
              <span><?php echo htmlspecialchars($c['name']); ?>
                <?php if ($c['project']): ?><span class="pol">&middot; <?php echo htmlspecialchars($c['project']); ?></span><?php endif; ?>
                <br><span class="pol"><?php echo htmlspecialchars(substr((string)$c['policy'], 0, 32)); ?>&hellip;</span></span>
            </label>
          <?php endforeach; ?>
        </div>
        <div class="mt-field wide" style="margin-top:14px">
          <label>Policy ids (used only when no collection is ticked)</label>
          <textarea name="policy_ids" spellcheck="false"><?php echo htmlspecialchars($row['policy_ids']); ?></textarea>
          <p class="hint">Comma separated, 56 hex characters each. A truncated one is refused here
             rather than becoming a theme that silently matches nobody.</p>
        </div>
      </div>

      <div class="mt-sect">
        <h3>Art</h3>
        <p class="lead">Both are resized on the long edge and re-encoded. Neither is forced to a
           shape &mdash; a background is picked to read either side of the board.<br>
           Limits: <b><?php echo htmlspecialchars((string)ini_get('upload_max_filesize')); ?></b> per file,
           <b><?php echo htmlspecialchars((string)ini_get('post_max_size')); ?></b> for the whole form.
           Go over and the browser sends it anyway and the server drops everything, so the numbers
           are worth a glance before a big background.</p>
        <div class="mt-grid">
          <div class="mt-field">
            <label>Background &mdash; <code>monstrocity.png</code></label>
            <input type="file" name="bg_file" accept="image/*">
          </div>
          <div class="mt-field">
            <label>Project logo &mdash; <code>logo.png</code></label>
            <input type="file" name="logo_file" accept="image/*">
            <p class="hint">Fitted into <b><?php echo MONSTROCITY_LOGO_W; ?>&times;<?php echo MONSTROCITY_LOGO_H; ?></b>
               and centred, with the rest left transparent. Any shape works &mdash; tall, wide or
               narrow &mdash; and they all end up rendering the same size, so there is no need to
               pad it yourself. Supply it at least that big and it stays sharp.</p>
            <?php if ($row['id'] && is_file(__DIR__ . '/' . monstrocity_theme_dir($row['slug']) . '/logo.png')):
              $lp = monstrocity_theme_dir($row['slug']) . '/logo.png';
              $ld = @getimagesize(__DIR__ . '/' . $lp); ?>
              <p class="hint" style="margin-top:7px">Current:
                <?php echo $ld ? $ld[0] . '&times;' . $ld[1] : '?'; ?>
                <?php if ($ld && ($ld[0] != MONSTROCITY_LOGO_W || $ld[1] != MONSTROCITY_LOGO_H)): ?>
                  <b style="color:#f5a623">&mdash; not the standard box, re-upload to fix</b>
                <?php endif; ?>
              </p>
              <div style="margin-top:6px;background:#07111d url('data:image/svg+xml;utf8,<svg xmlns=\'http://www.w3.org/2000/svg\' width=\'16\' height=\'16\'><rect width=\'8\' height=\'8\' fill=\'%23121c26\'/><rect x=\'8\' y=\'8\' width=\'8\' height=\'8\' fill=\'%23121c26\'/></svg>');border:1px solid rgba(255,255,255,.12);border-radius:3px;padding:6px;text-align:center">
                <img src="<?php echo htmlspecialchars($lp); ?>?v=<?php echo @filemtime(__DIR__ . '/' . $lp); ?>"
                     alt="" style="max-width:100%;display:block;margin:0 auto">
              </div>
            <?php endif; ?>
          </div>
          <div class="mt-field">
            <label>Source art faces</label>
            <select name="orientations">
              <?php foreach (monstrocity_orientations() as $o): ?>
                <option value="<?php echo $o; ?>"<?php echo $row['orientations'] === $o ? ' selected' : ''; ?>>
                  <?php echo $o === '' ? '— unset —' : $o; ?></option>
              <?php endforeach; ?>
            </select>
            <p class="hint"><b>Random</b> means the collection faces both ways, so each character
               is judged by eye rather than flipped as a batch.</p>
          </div>
          <div class="mt-field">
            <label>Character file type</label>
            <select name="extension">
              <?php foreach (monstrocity_extensions() as $e): ?>
                <option value="<?php echo $e; ?>"<?php echo $row['extension'] === $e ? ' selected' : ''; ?>>.<?php echo $e; ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>

      <div class="mt-sect">
        <h3>Placement and access</h3>
        <div class="mt-grid">
          <div class="mt-field">
            <label>Group</label>
            <select name="theme_group">
              <option value="">&mdash; pick &mdash;</option>
              <?php foreach ($allGroups as $g => $o): ?>
                <option value="<?php echo htmlspecialchars($g); ?>"<?php echo $row['theme_group'] === $g ? ' selected' : ''; ?>>
                  <?php echo htmlspecialchars($g); ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="mt-field">
            <label>&hellip;or start a new group</label>
            <input type="text" name="theme_group_new" value="<?php echo htmlspecialchars($group_new); ?>"
                   placeholder="leave blank to use the pick">
          </div>
          <div class="mt-field">
            <label>Visibility</label>
            <label class="mt-check"><input type="checkbox" name="background" value="1" <?php echo !empty($row['background']) ? 'checked' : ''; ?>>
              <span>Has a background image</span></label>
            <label class="mt-check"><input type="checkbox" name="req_innercircle" value="1" <?php echo !empty($row['req_innercircle']) ? 'checked' : ''; ?>>
              <span>Inner Circle only</span></label>
            <label class="mt-check"><input type="checkbox" name="req_dev" value="1" <?php echo !empty($row['req_dev']) ? 'checked' : ''; ?>>
              <span>Dev only <span class="sub">(must also be Inner Circle)</span></span></label>
            <label class="mt-check"><input type="checkbox" name="active" value="1" <?php echo !empty($row['active']) ? 'checked' : ''; ?>>
              <span>Active &mdash; shown in the picker</span></label>
          </div>
        </div>
      </div>

      <div class="mt-actions">
        <button class="adm-btn" type="submit"><?php echo $row['id'] ? 'Save theme' : 'Create theme'; ?></button>
        <?php if ($row['id']): ?>
          <a class="adm-btn" href="admin-monstrocity-art.php?theme=<?php echo (int)$row['id']; ?>">Character art &rarr;</a>
        <?php endif; ?>
        <a class="adm-btn" href="admin-monstrocity.php">Cancel</a>
      </div>
    </form>
  </div>

<script>
(function () {
  var proj  = document.getElementById('mt-proj'),
      pname = document.getElementById('mt-pname'),
      all   = document.getElementById('mt-allcols'),
      box   = document.getElementById('mt-cols'),
      count = document.getElementById('mt-colcount');
  if (!proj || !box || !pname) return;
  var rows = Array.prototype.slice.call(box.querySelectorAll('label[data-project]'));

  /* THE TYPED NAME WINS. This only ever fills a field left empty, or replaces
     one it filled itself. The moment the field is typed in, dataset.auto is
     dropped and it is never touched again -- blank means "inherit" to the
     server, so a stale auto-filled value would be stored as a deliberate
     override of the project it came from. */
  function syncName() {
    var want = proj.options[proj.selectedIndex].getAttribute('data-name') || '';
    if (pname.value === '' || pname.value === pname.dataset.auto) {
      pname.value = want;
      if (want) pname.dataset.auto = want; else delete pname.dataset.auto;
    }
    pname.placeholder = want ? want + ' (inherited)' : 'required when no project is linked';
  }
  pname.addEventListener('input', function () { delete pname.dataset.auto; });

  /*
   * A TICKED COLLECTION IS NEVER HIDDEN, whatever the filter says.
   *
   * Not because hiding it would unlink it -- a checked box inside a
   * display:none label is still submitted; only `disabled` stops that. The
   * reason is plainer: a theme can legitimately span projects, and a filter
   * that hides a link already made means saving a theme while unable to see
   * what it is attached to.
   */
  function filter() {
    var pid = proj.value, shown = 0, hidden = 0;
    rows.forEach(function (r) {
      var keep = all.checked || pid === '0'
              || r.getAttribute('data-project') === pid
              || r.querySelector('input').checked;
      r.classList.toggle('hid', !keep);
      if (keep) shown++; else hidden++;
    });
    count.textContent = hidden
      ? shown + ' shown, ' + hidden + ' hidden by project'
      : shown + ' collection' + (shown === 1 ? '' : 's');
  }

  proj.addEventListener('change', function () { syncName(); filter(); });
  all.addEventListener('change', filter);
  box.addEventListener('change', filter);
  if (pname.value === '') syncName();
  filter();
})();
</script>

<?php endif; ?>
<?php include 'admin-css.php'; ?>
