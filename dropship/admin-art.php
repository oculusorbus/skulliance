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

/* ---------------------------------------------------------------- upload - */
if ($theme && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['art'])) {
	$slot = (string)($_POST['slot'] ?? '');
	/*
	 * THE SLOT MUST COME OUT OF THE MANIFEST, never off the form. It becomes
	 * a filesystem path, and a posted one would let anything be written
	 * anywhere the web user can reach.
	 */
	$manifest = dropshipArtManifest($ext);
	if (!in_array($slot, $manifest, true)) {
		$msg['err'][] = 'That is not a slot this theme has.';
	} elseif ($_FILES['art']['error'] !== UPLOAD_ERR_OK) {
		$msg['err'][] = 'Upload failed (code ' . (int)$_FILES['art']['error'] . ').';
	} else {
		$slot_ext = strtolower(pathinfo($slot, PATHINFO_EXTENSION));
		list($ok, $why) = dropshipArtStore($_FILES['art']['tmp_name'], $base . $slot, $slot_ext);
		if ($ok) $msg['ok'][] = $slot . ' &mdash; ' . $why;
		else     $msg['err'][] = $slot . ' &mdash; ' . $why;
	}
}

$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES); };
/* Browser-facing prefix. Drop Ship's own art is flat; every reskin nests. */
$web = '/staking/dropship/' . ($slug === 'drop-ship' ? '' : $slug . '/');
?>
<style>
.dsart{max-width:1100px;text-align:left}
.dsart h2,.dsart h3{color:#00c8a0;text-align:left}
.dsart h4{color:#e8eaed;font-size:13px;margin:18px 0 2px;text-transform:uppercase;
  letter-spacing:.07em;opacity:.8;text-align:left}
.dsart .note{font-size:12px;color:#7a9eb0;line-height:1.6;margin:0 0 10px}
.dsart .msg{padding:8px 11px;border-radius:3px;margin:0 0 8px;font-size:13px}
.dsart .msg.bad{background:rgba(224,70,107,.12);color:#e8a0b0}
.dsart .msg.good{background:rgba(0,200,160,.12);color:#00c8a0}
.dsart .slots{display:grid;grid-template-columns:repeat(auto-fill,minmax(min(210px,100%),1fr));gap:10px}
.dsart .slot{border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:8px;
  background:rgba(0,0,0,.25)}
.dsart .slot.gap{border-color:rgba(224,70,107,.45)}
.dsart .shot{width:100%;height:110px;background:#07111d;border-radius:3px;overflow:hidden;
  display:flex;align-items:center;justify-content:center;margin:0 0 6px}
.dsart .shot img{max-width:100%;max-height:110px}
.dsart .shot .none{font-size:11px;color:#e8a0b0}
.dsart .path{font-family:ui-monospace,Menlo,monospace;font-size:10.5px;color:#e8eaed;
  word-break:break-all;display:block}
.dsart .dims{font-size:10.5px;color:#7a9eb0;display:block;margin:2px 0 5px}
.dsart input[type=file]{width:100%;font-size:11px;color:#e8eaed}
.dsart button{background:#00c8a0;color:#07111d;border:0;border-radius:3px;padding:4px 9px;
  font-weight:bold;font-size:11px;cursor:pointer;margin-top:5px}
.dsart .pick a{display:inline-block;margin:0 8px 8px 0;font-size:13px}
.dsart .bar{font-size:12px;color:#7a9eb0;margin:0 0 14px}
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
	echo dropshipArtMaxHeight(); ?></b>, which is the widest the results pane ever
	draws them. Anything already smaller is left alone, and nothing is enlarged.
	An animated gif is only resized where Imagick is available, because the
	alternative flattens it to a single frame.</p>

	<?php foreach (dropshipArtGroups($ext) as $group => $g): ?>
	<h4><?php echo $h($group); ?></h4>
	<p class="note"><?php echo $h($g['note']); ?></p>
	<div class="slots">
	<?php foreach ($g['files'] as $rel):
		$abs  = $base . $rel;
		$here = is_file($abs);
		$dims = '';
		if ($here) {
			$i = @getimagesize($abs);
			$dims = ($i ? $i[0] . '&times;' . $i[1] : '?') . ' &middot; '
			      . round(@filesize($abs) / 1024) . 'KB';
		}
	?>
		<div class="slot <?php echo $here ? '' : 'gap'; ?>">
			<div class="shot">
			<?php if ($here): ?>
				<img src="<?php echo $h($web . $rel); ?>?v=<?php echo @filemtime($abs); ?>" alt="" loading="lazy">
			<?php else: ?><span class="none">missing</span><?php endif; ?>
			</div>
			<span class="path"><?php echo $h($rel); ?></span>
			<span class="dims"><?php echo $here ? $dims : 'the run will ask for this'; ?></span>
			<form method="post" enctype="multipart/form-data" action="admin-art.php?project_id=<?php echo $pid; ?>">
				<input type="hidden" name="project_id" value="<?php echo $pid; ?>">
				<input type="hidden" name="slot" value="<?php echo $h($rel); ?>">
				<input type="file" name="art" accept="image/*" required>
				<button type="submit"><?php echo $here ? 'Replace' : 'Upload'; ?></button>
			</form>
		</div>
	<?php endforeach; ?>
	</div>
	<?php endforeach; ?>
<?php endif; ?>
<?php dsaArtClose($conn); ?>
