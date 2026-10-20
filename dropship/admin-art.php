<?php
/**
 * DROP SHIP THEME ART -- see what a theme actually has, and replace a slot.
 *
 * A reskin is about seventy images in a fixed tree, and until now the only
 * record of which seventy was the game itself, spread across live(), kill(),
 * extralife() and the weapon and equipment branches. Nobody could answer "is
 * this theme finished" without reading the source, so the answer was always
 * "probably not" and new themes stalled.
 *
 * This lists every slot the run will ask for, says whether the file is
 * there, and lets one be replaced. Uploads are scaled to the display pane
 * on the way in, because the page caps images at 940x650 anyway and
 * everything above that is bytes thrown away on every view.
 *
 * READS THE SAME MANIFEST THE AUDIT DOES, so a file added to the game shows
 * up here without anybody remembering to add it twice.
 */
include 'db.php';
include 'webhooks.php';
include 'dropship.php';
require_once 'dropship-art.php';
include 'header.php';

function dsaArtClose($conn) {
	echo '</div></div></div>'
	   . '<div class="footer"><p>Drop Ship | Ohh Meed\'s Shorty Verse<br>Copyright &copy; '
	   . '<span id="year"></span></div></div></div></body>';
	if (is_object($conn)) $conn->close();
	echo '<script type="text/javascript" src="dropship.js"></script></html>';
}

if (!dropshipThemeAdmin()) {
	echo '<div class="row"><div class="main"><div class="content"><h2>Theme art</h2>'
	   . '<p>Not yours to edit.</p>';
	dsaArtClose($conn);
	exit;
}

$msg = array('ok' => array(), 'err' => array());

/* Which theme. Defaults to the one being played, so arriving from the game
   lands on the right set rather than a picker. */
$pid = (int)($_GET['project_id'] ?? ($_POST['project_id'] ?? $_SESSION['userData']['dropship_project_id'] ?? 0));
$themes = dropshipThemeAll($conn);
$theme  = $pid ? dropshipThemeConfig($conn, $pid) : null;

/*
 * The slug and extension decide where the files live and what format each
 * slot is. Both come from the theme row when there is one; without a row
 * there is nothing to manage, and saying so beats guessing a path.
 */
$slug = $theme ? $theme['slug'] : '';
$ext  = $theme && !empty($theme['extension']) ? strtolower($theme['extension']) : 'gif';
$root = $slug === '' ? null : dropshipArtRoot($slug);
$base = $root === null ? null : rtrim(__DIR__, '/') . '/' . $root;

/* ---------------------------------------------------------------- rename - */
/*
 * Renaming an icon already on the server, rather than making somebody find
 * and re-upload the same picture under its new name.
 *
 * THE TARGET IS NEVER TAKEN FROM THE FORM. rename_to is checked against the
 * names THIS theme actually asks for, exactly as an upload slot is -- the
 * name is about to be a filesystem path, and a posted one would let a file
 * be moved anywhere the web user can reach.
 */
if ($theme && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['rename_from'])) {
	$to   = (string)($_POST['rename_to'] ?? '');
	$from = (string)($_POST['rename_from'] ?? '');
	$want = array();
	foreach (dropshipThemeIconSlots($conn, $pid) as $ic) if ($ic['safe']) $want[] = $ic['name'];
	if ($from === '') {
		$msg['err'][] = 'Pick the file to rename.';
	} elseif (!in_array($to, $want, true)) {
		$msg['err'][] = 'That is not an icon this theme asks for.';
	} else {
		$err = dropshipArtIconRename($conn, $from, $to, $pid);
		if ($err === '') $msg['ok'][] = 'Renamed to icons/' . htmlspecialchars($to) . '.png.';
		else             $msg['err'][] = htmlspecialchars($err);
	}
}

/* ---------------------------------------------------------------- upload - */
if ($theme && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['art'])) {
	$slot = (string)($_POST['slot'] ?? '');
	/*
	 * THE SLOT MUST COME OUT OF THE MANIFEST, never off the form. It becomes
	 * a filesystem path, and a posted one would let anything be written
	 * anywhere the web user can reach.
	 */
	/*
	 * Icons live in ONE SHARED FOLDER, not per theme -- the markup asks for
	 * icons/<name>.png relative to the page and every project is served from
	 * /staking/dropship/. So an icon slot is validated against the names
	 * THIS theme asks for, and written to the shared folder.
	 */
	$manifest = dropshipArtManifest($ext);
	$icon_names = array();
	foreach (dropshipThemeIconSlots($conn, $pid) as $ic) if ($ic['safe']) $icon_names[] = $ic['rel'];
	$valid = in_array($slot, $manifest, true) || in_array($slot, $icon_names, true);
	$is_icon = in_array($slot, $icon_names, true);

	if (!$valid) {
		$msg['err'][] = 'That is not a slot this theme has.';
	} elseif ($_FILES['art']['error'] !== UPLOAD_ERR_OK) {
		$msg['err'][] = 'Upload failed (code ' . (int)$_FILES['art']['error'] . ').';
	} else {
		$slot_ext = strtolower(pathinfo($slot, PATHINFO_EXTENSION));
		/* An icon is shared, so it is written beside the page rather than
		   inside the theme folder. */
		$dest = ($is_icon ? rtrim(__DIR__, '/') . '/' : $base) . $slot;
		list($ok, $why) = dropshipArtStore($_FILES['art']['tmp_name'], $dest, $slot_ext);
		if ($ok) $msg['ok'][] = $slot . ' &mdash; ' . $why;
		else     $msg['err'][] = $slot . ' &mdash; ' . $why;
	}
}

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES); };
/* Browser-facing prefix. Drop Ship's own art is flat; every reskin nests. */
$web = '/staking/dropship/' . ($slug === 'drop-ship' ? '' : $slug . '/');
?>
<style>
/* FULL WIDTH of the content pane. A capped measure here just leaves empty
   screen beside a grid that would happily show two more columns. */
.dsart{text-align:left}
.dsart h2,.dsart h3{color:#00c8a0;text-align:left}
.dsart h4{color:#e8eaed;font-size:13px;margin:18px 0 2px;text-transform:uppercase;
  letter-spacing:.07em;opacity:.8;text-align:left}
.dsart .note{font-size:12px;color:#7a9eb0;line-height:1.6;margin:0 0 10px}
.dsart .msg{padding:8px 11px;border-radius:3px;margin:0 0 8px;font-size:13px}
.dsart .msg.bad{background:rgba(224,70,107,.12);color:#e8a0b0}
.dsart .msg.good{background:rgba(0,200,160,.12);color:#00c8a0}
.dsart .slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(210px,100%),1fr));gap:10px}
/* A COLUMN, so the upload control sits on the floor of every card. The
   cards carry different numbers of lines -- a mapping, a warning, a rename
   -- and with the default stretch the buttons landed at a different height
   in every one, which is what made a row of them look broken. */
.dsart .slot{border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:8px;
  background:rgba(0,0,0,.25);display:flex;flex-direction:column}
.dsart .slot form{margin-top:auto}
.dsart .slot .ren{margin-top:6px}
.dsart .slot.gap{border-color:rgba(224,70,107,.45)}
.dsart .shot{width:100%;height:110px;background:#07111d;border-radius:3px;overflow:hidden;
  display:flex;align-items:center;justify-content:center;margin:0 0 6px;flex:none}
.dsart .shot img{max-width:100%;max-height:110px;width:auto;margin:0}
.dsart .shot .none{font-size:11px;color:#e8a0b0}
.dsart .name{font-size:12px;color:#e8eaed;display:block;font-weight:bold;margin:0 0 2px}
.dsart .maps{font-size:10.5px;color:#7a9eb0;display:block}
.dsart .maps b{color:#00c8a0;font-weight:normal}
.dsart .warn{font-size:10.5px;color:#e8a0b0;display:block}
.dsart .path{font-family:ui-monospace,Menlo,monospace;font-size:10.5px;color:#7a9eb0;
  word-break:break-all;display:block}
.dsart .dims{font-size:10.5px;color:#7a9eb0;display:block;margin:2px 0 5px}
.dsart input[type=file]{width:100%;font-size:11px;color:#e8eaed}
.dsart button{background:#00c8a0;color:#07111d;border:0;border-radius:3px;padding:4px 9px;
  font-weight:bold;font-size:11px;cursor:pointer;margin-top:5px}
.dsart .pick a{display:inline-block;margin:0 8px 8px 0;font-size:13px}
.dsart .bar{font-size:12px;color:#7a9eb0;margin:0 0 14px}
/* An icon is a small square next to landscape frames, so it is shown at its
   own size on a checked ground rather than stretched to fill the box -- these
   are transparent PNGs and a flat dark panel hides their edges. */
.dsart .slot.is-icon{background:rgba(0,200,160,.05)}
/* SAME HEIGHT as an art slot, so the title line lands at the same place
   right across a row; only the image inside is capped. */
.dsart .slot.is-icon .shot{background-color:#0b1724;
  background-image:linear-gradient(45deg,rgba(255,255,255,.04) 25%,transparent 25%,transparent 75%,rgba(255,255,255,.04) 75%),
                   linear-gradient(45deg,rgba(255,255,255,.04) 25%,transparent 25%,transparent 75%,rgba(255,255,255,.04) 75%);
  background-size:14px 14px;background-position:0 0,7px 7px}
.dsart .slot.is-icon .shot img{max-height:64px;max-width:64px}
.dsart .tag{font-size:9.5px;color:#00c8a0;border:1px solid rgba(0,200,160,.4);
  border-radius:3px;padding:0 4px;margin-left:4px;vertical-align:1px;font-weight:normal}
.dsart .ren{margin-top:6px;border-top:1px solid rgba(255,255,255,.08);padding-top:6px}
.dsart .ren label{display:block;font-size:10.5px;color:#7a9eb0;margin:0 0 3px;text-align:left}
.dsart .ren select{width:100%;box-sizing:border-box;background:#07111d;color:#e8eaed;
  border:1px solid rgba(255,255,255,.16);border-radius:3px;padding:4px;font-size:11px}
</style>
<div class="row"><div class="main"><div class="content dsart">
<h2>Theme art</h2>

<?php foreach ($msg['err'] as $m): ?><div class="msg bad"><?php echo $m; ?></div><?php endforeach; ?>
<?php foreach ($msg['ok'] as $m): ?><div class="msg good"><?php echo $m; ?></div><?php endforeach; ?>

<p class="pick">
<?php foreach ($themes as $t): ?>
	<a href="admin-art.php?project_id=<?php echo (int)$t['project_id']; ?>"><?php
		echo $h($t['project_name'] ?: $t['slug']); ?></a>
<?php endforeach; ?>
<a href="admin-themes.php">&larr; Wording</a>
</p>

<?php if (!$theme): ?>
	<p class="note">No theme is configured for that project yet. Add one under
	<a href="admin-themes.php">Wording</a> first &mdash; the slug it saves is the
	folder these files live in.</p>
<?php else:
	$manifest = dropshipArtManifest($ext);
	$missing  = 0;
	foreach ($manifest as $rel) if (!is_file($base . $rel)) $missing++;
?>
	<p class="bar"><b><?php echo count($manifest) - $missing; ?></b> of
	<b><?php echo count($manifest); ?></b> files in place for
	<b><?php echo $h($theme['slug']); ?></b>
	<?php if ($missing): ?>&middot; <span style="color:#e8a0b0"><?php echo $missing; ?> missing</span><?php endif; ?>
	&middot; media extension <code>.<?php echo $h($ext); ?></code></p>

	<p class="note">Uploads are scaled to fit <b><?php echo dropshipArtMaxWidth(); ?>&times;<?php
	echo dropshipArtMaxHeight(); ?></b>. That is the pane at <b>898px</b> times the
	<b>1.2&times;</b> the Ken Burns pan zooms to, so art stays sharp at the end of the
	drift rather than only at the start. Anything already smaller is left alone and
	nothing is enlarged, so an existing 1024px file is kept as it is &mdash; it ends
	up about 5% soft at full zoom, which is not worth re-cutting for.
	An animated gif is only resized where Imagick is available, because the
	alternative flattens it to a single frame.</p>

	<?php
	/*
	 * ONE LIST, ART AND ICONS TOGETHER. images/weapons/rocket-launcher3.gif
	 * and icons/rocket-launcher.png are two pictures of one object, and the
	 * only reason they used to sit on opposite halves of this page is that
	 * they live in different folders. dropshipArtPlan() puts the icon in
	 * after the last frame of the thing it belongs to.
	 */
	$icon_files = dropshipArtIconFiles();
	foreach (dropshipArtPlan($conn, $pid, $ext) as $group => $g): ?>
	<h4><?php echo $h($group); ?></h4>
	<p class="note"><?php echo $h($g['note']); ?></p>
	<div class="slots">
	<?php foreach ($g['slots'] as $slot):
		$is_icon = ($slot['kind'] === 'icon');
		$rel     = $slot['rel'];
		/* Icons are ONE SHARED FOLDER for every theme -- the page asks for
		   icons/<name>.png and all four projects are served from the same
		   place -- so they are addressed from here, not from the theme's
		   own folder. */
		$abs  = $is_icon ? rtrim(__DIR__, '/') . '/' . $rel : $base . $rel;
		$src  = $is_icon ? '/staking/dropship/' . $rel : $web . $rel;
		$here = is_file($abs);
		$dims = '';
		if ($here) {
			$i = @getimagesize($abs);
			$dims = ($i ? $i[0] . '&times;' . $i[1] : '?') . ' &middot; '
			      . round(@filesize($abs) / 1024) . 'KB';
		}
	?>
		<?php /* is-icon, NOT icon: .icon is a GLOBAL class in dist/flexbox.css
		         (width:40px, for the little images in the nav and on cards), so
		         naming the modifier 'icon' collapsed every icon slot to 55px and
		         wrapped its text one character per line. */ ?>
		<div class="slot <?php echo $here ? '' : 'gap'; ?><?php echo $is_icon ? ' is-icon' : ''; ?>">
			<div class="shot">
			<?php if ($here): ?>
				<img src="<?php echo $h($src); ?>?v=<?php echo @filemtime($abs); ?>" alt="" loading="lazy">
			<?php else: ?><span class="none">missing</span><?php endif; ?>
			</div>
		<?php if ($is_icon): ?>
			<span class="name"><?php echo $h($slot['label']); ?> <span class="tag">icon</span></span>
			<?php if ($slot['name'] !== $slot['term']): ?>
				<span class="maps">this theme calls it <b><?php echo $h($slot['name']); ?></b></span>
			<?php endif; ?>
		<?php else: ?>
			<span class="name"><?php echo $h(dropshipArtTitle($rel)); ?></span>
			<?php foreach (dropshipArtSlotMapping($conn, $pid, $rel) as $mp): ?>
				<span class="maps"><?php echo $h($mp['from']); ?> &rarr; <b><?php
					echo $h($mp['to']); ?></b></span>
			<?php endforeach; ?>
		<?php endif; ?>
			<span class="path"><?php echo $h($rel); ?></span>
		<?php if ($is_icon && !$slot['safe']): ?>
			<span class="warn">That word cannot be a filename, so the page will ask
			for something it can never find. Change the wording.</span>
		<?php else: ?>
			<span class="dims"><?php echo $here ? $dims
				: ($is_icon ? 'the page will ask for this' : 'the run will ask for this'); ?></span>
			<form method="post" enctype="multipart/form-data" action="admin-art.php?project_id=<?php echo $pid; ?>">
				<input type="hidden" name="project_id" value="<?php echo $pid; ?>">
				<input type="hidden" name="slot" value="<?php echo $h($rel); ?>">
				<input type="file" name="art" accept="<?php echo $is_icon ? 'image/png' : 'image/*'; ?>" required>
				<button type="submit"><?php echo $here ? 'Replace' : 'Upload'; ?></button>
			</form>
			<?php
			/*
			 * RENAME, NOT RE-UPLOAD. When the wording changes, the picture
			 * the theme wants is usually already on the server under the
			 * name it used to ask for -- icons/nurse.png when it now wants
			 * icons/sexy-nurse.png. Making somebody find and re-upload that
			 * file is busywork, and leaving it behind quietly fills the
			 * shared folder with orphans.
			 */
			if ($is_icon && !$here && $slot['safe'] && $icon_files): ?>
			<form method="post" action="admin-art.php?project_id=<?php echo $pid; ?>" class="ren">
				<input type="hidden" name="project_id" value="<?php echo $pid; ?>">
				<input type="hidden" name="rename_to" value="<?php echo $h($slot['name']); ?>">
				<label>or rename one already on the server</label>
				<select name="rename_from">
					<option value="">pick a file&hellip;</option>
					<?php foreach ($icon_files as $f): ?>
					<option value="<?php echo $h($f); ?>"><?php echo $h($f); ?>.png</option>
					<?php endforeach; ?>
				</select>
				<button type="submit">Rename</button>
			</form>
			<?php endif; ?>
		<?php endif; ?>
		</div>
	<?php endforeach; ?>
	</div>
	<?php endforeach; ?>
<?php endif; ?>
<?php dsaArtClose($conn); ?>
