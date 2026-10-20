<?php
/**
 * DROP SHIP THEME ADMIN -- add and edit a theme without touching PHP.
 *
 * The thing that stopped new themes was never the art. It was that every
 * reskin meant editing a 202-line if/else chain in db.php, a slug derived
 * from the project name, and a set of labels buried in dashboard.php. This
 * page owns all of that.
 *
 * READS AND WRITES ONLY THE TWO NEW TABLES. It never edits projects, because
 * the collection a theme draws from is already data there (projects.policy_id)
 * and several themes legitimately share one collection -- Dread City and
 * Filthy Mermaid both sit on Shorty Verse. Presentation here, ownership there.
 *
 * SAFE TO DEPLOY BEFORE THE TABLES EXIST: it says so and stops, rather than
 * erroring, because nothing in the app creates them.
 */
include 'db.php';
include 'webhooks.php';          // the $discordid_* globals the gate reads
include 'dropship.php';
include 'header.php';

/* The page chrome closes the same way every Drop Ship page does; there is no
   footer.php to include, so it is emitted here and reused by the gate. */
function dsaClose($conn) {
	echo '</div></div></div>'
	   . '<div class="footer"><p>Drop Ship | Ohh Meed\'s Shorty Verse<br>Copyright &copy; '
	   . '<span id="year"></span></div></div></div></body>';
	if (is_object($conn)) $conn->close();
	echo '<script type="text/javascript" src="dropship.js"></script></html>';
}

if (!dropshipThemeAdmin()) {
	echo '<div class="row"><div class="main"><div class="content">'
	   . '<h2>Themes</h2><p>Not yours to edit.</p>';
	dsaClose($conn);
	exit;
}

$msg = array('ok' => array(), 'err' => array());
$have_tables = dropshipThemesTable($conn);

/* ---------------------------------------------------------------- save -- */
if ($have_tables && $_SERVER['REQUEST_METHOD'] === 'POST') {
	$pid  = (int)($_POST['project_id'] ?? 0);
	$slug = dropshipThemeSlug($_POST['slug'] ?? '');
	$e    = function ($v) use ($conn) { return $conn->real_escape_string((string)$v); };

	if ($pid <= 0)      $msg['err'][] = 'Pick a project.';
	if ($slug === '')   $msg['err'][] = 'A slug is required: it is the image folder name.';

	if (!$msg['err']) {
		$cols = "project_id = $pid"
		      . ", slug = '"            . $e($slug) . "'"
		      . ", active = "           . (empty($_POST['active']) ? 0 : 1)
		      . ", extension = '"       . $e($_POST['extension'] ?? 'gif') . "'"
		      . ", currency = '"        . $e($_POST['currency'] ?? 'SCRIP') . "'"
		      . ", squad_label = '"     . $e($_POST['squad_label'] ?? '') . "'"
		      . ", character_label = '" . $e($_POST['character_label'] ?? '') . "'"
		      . ", trait_source = '"    . $e($_POST['trait_source'] ?? 'koios') . "'";

		$existing = dropshipThemeConfig($conn, $pid);
		if ($existing) {
			$ok = $conn->query("UPDATE dropship_themes SET $cols, updated_at = NOW()
			                     WHERE id = " . (int)$existing['id']);
			$theme_id = (int)$existing['id'];
		} else {
			$ok = $conn->query("INSERT INTO dropship_themes SET $cols, created_at = NOW()");
			$theme_id = (int)$conn->insert_id;
		}
		if (!$ok) {
			$msg['err'][] = 'Theme not saved: ' . htmlspecialchars($conn->error);
		} else {
			$msg['ok'][] = 'Theme saved.';

			/*
			 * TEXT PAIRS ARE REPLACED WHOLESALE, not merged. The form posts
			 * the complete set, so a pair deleted in the browser has to
			 * disappear here -- merging would make removal impossible and
			 * leave a word being rewritten with nothing in the UI saying so.
			 */
			$finds = (array)($_POST['find'] ?? array());
			$reps  = (array)($_POST['replacement'] ?? array());
			$types = (array)($_POST['match_type'] ?? array());
			$conn->query("DELETE FROM dropship_theme_text WHERE theme_id = $theme_id");
			$wrote = 0; $wanted = 0;
			foreach ($finds as $i => $find) {
				$find = trim((string)$find);
				$rep  = isset($reps[$i]) ? trim((string)$reps[$i]) : '';
				if ($find === '') continue;              /* a blank row is a deleted row */
				$wanted++;
				$type = (isset($types[$i]) && $types[$i] === 'description') ? 'description' : 'exact';
				if ($conn->query("INSERT INTO dropship_theme_text
				                  (theme_id, match_type, find, replacement, sort_order)
				                  VALUES ($theme_id, '$type', '" . $e($find) . "', '"
				                . $e($rep) . "', $wanted)")) $wrote++;
			}
			/* A half-written dictionary is a theme speaking two languages on
			   one page, so say so rather than reporting a clean save. */
			if ($wrote !== $wanted) {
				$msg['err'][] = "Only $wrote of $wanted overrides saved ("
				              . htmlspecialchars($conn->error) . '). This theme is now part-translated.';
			} else {
				$msg['ok'][] = $wrote . ' override' . ($wrote === 1 ? '' : 's') . ' saved.';
			}
		}
	}
}

/* ---------------------------------------------------------------- read -- */
$edit_pid = (int)($_GET['project_id'] ?? ($_POST['project_id'] ?? 0));
$theme    = $have_tables && $edit_pid ? dropshipThemeConfig($conn, $edit_pid) : null;
$pairs    = array();
if ($theme) {
	$r = $conn->query("SELECT match_type, find, replacement FROM dropship_theme_text
	                    WHERE theme_id = " . (int)$theme['id'] . "
	                    ORDER BY match_type ASC, sort_order ASC, id ASC");
	if ($r) while ($row = $r->fetch_assoc()) $pairs[] = $row;
}
$projects = getProjects($conn);
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES); };
$val = function ($k, $d = '') use ($theme, $h) {
	return $h($theme && isset($theme[$k]) && $theme[$k] !== '' ? $theme[$k] : $d);
};
?>
<style>
.dsa{max-width:960px;text-align:left}
.dsa h2,.dsa h3{color:#00c8a0;text-align:left}
.dsa table{width:100%;border-collapse:collapse;margin:0 0 14px}
.dsa td,.dsa th{padding:5px 7px;text-align:left;font-size:13px;
  border-bottom:1px solid rgba(255,255,255,.08)}
.dsa input[type=text],.dsa select{width:100%;box-sizing:border-box;background:#07111d;
  color:#e8eaed;border:1px solid rgba(255,255,255,.16);border-radius:3px;padding:6px 8px;font-size:13px}
.dsa label{display:block;font-size:11px;color:#7a9eb0;margin:0 0 3px;text-align:left}
.dsa .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(190px,100%),1fr));gap:10px;margin:0 0 14px}
.dsa .msg{padding:8px 11px;border-radius:3px;margin:0 0 10px;font-size:13px}
.dsa .msg.bad{background:rgba(224,70,107,.12);color:#e8a0b0}
.dsa .msg.good{background:rgba(0,200,160,.12);color:#00c8a0}
.dsa .note{font-size:12px;color:#7a9eb0;line-height:1.6}
.dsa button,.dsa .btn{background:#00c8a0;color:#07111d;border:0;border-radius:4px;
  padding:8px 15px;font-weight:bold;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block}
</style>
<div class="row"><div class="main"><div class="content dsa">
<h2>Themes</h2>

<?php if (!$have_tables): ?>
	<div class="msg bad">The tables <code>dropship_themes</code> and
	<code>dropship_theme_text</code> do not exist on this database. Nothing here can
	be saved until they are created with the statements in
	<code>dropship-themes-schema.md</code>.</div>
<?php endif; ?>
<?php foreach ($msg['err'] as $m): ?><div class="msg bad"><?php echo $m; ?></div><?php endforeach; ?>
<?php foreach ($msg['ok'] as $m): ?><div class="msg good"><?php echo $h($m); ?></div><?php endforeach; ?>

<p class="note">A theme is the wording and the art folder. The collection its
NFTs come from is <b>not</b> set here &mdash; that is <code>projects.policy_id</code>,
and more than one theme can share a collection. A theme with no overrides saved
falls back to the built-in wording, so an empty theme changes nothing.</p>

<table>
	<tr><th>Project</th><th>Slug</th><th>Active</th><th>Overrides</th><th></th></tr>
	<?php foreach (dropshipThemeAll($conn) as $t): ?>
	<tr>
		<td><?php echo $h($t['project_name'] ?: ('#' . $t['project_id'])); ?></td>
		<td><code><?php echo $h($t['slug']); ?></code></td>
		<td><?php echo $t['active'] ? 'yes' : 'no'; ?></td>
		<td><?php echo (int)$t['text_count']; ?></td>
		<td><a class="btn" href="admin-themes.php?project_id=<?php echo (int)$t['project_id']; ?>">Edit</a></td>
	</tr>
	<?php endforeach; ?>
</table>

<form method="post" action="admin-themes.php">
<h3><?php echo $theme ? 'Edit theme' : 'Add a theme'; ?></h3>
<div class="grid">
	<div><label>Project</label>
		<select name="project_id">
			<option value="0">&mdash; pick &mdash;</option>
			<?php foreach ($projects as $pid => $pname): ?>
			<option value="<?php echo (int)$pid; ?>"<?php
				echo ($theme && (int)$theme['project_id'] === (int)$pid) ? ' selected' : ''; ?>>
				<?php echo $h($pname); ?></option>
			<?php endforeach; ?>
		</select></div>
	<div><label>Slug (image folder)</label>
		<input type="text" name="slug" value="<?php echo $val('slug'); ?>" placeholder="oculus-lounge"></div>
	<div><label>Animation extension</label>
		<input type="text" name="extension" value="<?php echo $val('extension', 'gif'); ?>"></div>
	<div><label>Currency</label>
		<input type="text" name="currency" value="<?php echo $val('currency', 'SCRIP'); ?>"></div>
	<div><label>Squad label</label>
		<input type="text" name="squad_label" value="<?php echo $val('squad_label', 'Super Soldier'); ?>"></div>
	<div><label>Character label</label>
		<input type="text" name="character_label" value="<?php echo $val('character_label', 'Troops'); ?>"></div>
	<div><label>Trait source</label>
		<select name="trait_source">
			<?php foreach (array('koios' => 'Koios (the usual path)',
			                     'lounge' => 'Oculus Lounge sync') as $k => $lbl): ?>
			<option value="<?php echo $k; ?>"<?php
				echo ($theme && $theme['trait_source'] === $k) ? ' selected' : ''; ?>><?php echo $lbl; ?></option>
			<?php endforeach; ?>
		</select></div>
	<div><label>Active</label>
		<select name="active">
			<option value="0"<?php echo ($theme && !$theme['active']) ? ' selected' : ''; ?>>no</option>
			<option value="1"<?php echo ($theme && $theme['active']) ? ' selected' : ''; ?>>yes</option>
		</select></div>
</div>

<h3>Wording</h3>
<p class="note"><b>Exact</b> replaces a whole label and nothing else, so
&ldquo;Soldiers&rdquo; becomes &ldquo;Citizens&rdquo; but &ldquo;Soldiers of
fortune&rdquo; is untouched. <b>Description</b> rewrites the word wherever it
appears in a sentence, and only runs when no exact match was found. Clear the
left box to delete a row.</p>
<table id="dsa-pairs">
	<tr><th style="width:150px">Match</th><th>Replace this</th><th>With this</th></tr>
	<?php foreach (array_merge($pairs, array(null, null, null)) as $pr): ?>
	<tr>
		<td><select name="match_type[]">
			<option value="exact"<?php echo ($pr && $pr['match_type'] === 'exact') ? ' selected' : ''; ?>>Exact</option>
			<option value="description"<?php echo ($pr && $pr['match_type'] === 'description') ? ' selected' : ''; ?>>Description</option>
		</select></td>
		<td><input type="text" name="find[]" value="<?php echo $pr ? $h($pr['find']) : ''; ?>"></td>
		<td><input type="text" name="replacement[]" value="<?php echo $pr ? $h($pr['replacement']) : ''; ?>"></td>
	</tr>
	<?php endforeach; ?>
</table>
<button type="button" onclick="dsaAddRow()">Add a row</button>
<button type="submit">Save theme</button>
</form>
<script>
function dsaAddRow(){
	var t = document.getElementById('dsa-pairs');
	var r = t.insertRow(-1);
	r.innerHTML = t.rows[t.rows.length-2].innerHTML
		.replace(/ selected/g, '').replace(/value="[^"]*"/g, 'value=""');
}
</script>
<?php dsaClose($conn); ?>
