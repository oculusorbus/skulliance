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
require_once 'dropship-theme-vocab.php';
require_once 'dropship-nft-probe.php';
include 'header.php';

/* The page chrome closes the same way every Drop Ship page does; there is no
   footer.php to include, so it is emitted here and reused by the gate. */
function dsaClose($conn) {
	echo '</div></div></div>'
	   . '<div class="footer"><p>Drop Ship | Ohh Meed\'s Shorty Verse<br>Copyright &copy; '
	   . '<span id="year"></span></div></div></div></body>';
	if (is_object($conn)) $conn->close();
	echo '<script type="text/javascript" src="' . dropshipAsset('dropship.js') . '"></script></html>';
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
		      . ", trait_source = '"    . (($_POST['trait_source'] ?? '') === 'skulliance'
		                                   ? 'skulliance' : 'koios') . "'"
		      . ", stage_order = '"     . (($_POST['stage_order'] ?? '') === 'linear'
		                                   ? 'linear' : 'random') . "'"
		      . ", ken_burns = '"       . (in_array($_POST['ken_burns'] ?? '', array('on', 'off'), true)
		                                   ? $_POST['ken_burns'] : 'auto') . "'";
		/* NO LABEL COLUMNS. Currency, squad and character were the words
		   SCRIP, Super Soldier and Troops, which the wording below already
		   owns -- two places to change one word, and the dashboard read the
		   column, so renaming SCRIP in the wording changed nothing there.
		   trait_source is NOT one of those: it picks between two real
		   NFT-loading pipelines and nothing derives it. It was removed once
		   by confusing it with the same-named key in dropship-traitdrop.php,
		   which names the game a Discord drop came from and IS derived. */

		$existing = dropshipThemeConfig($conn, $pid);
		if ($existing) {
			$ok = $conn->query("UPDATE themes SET $cols, updated_at = NOW()
			                     WHERE id = " . (int)$existing['id']);
			$theme_id = (int)$existing['id'];
		} else {
			$ok = $conn->query("INSERT INTO themes SET $cols, created_at = NOW()");
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
			$rows = dropshipThemeVocabExpand($_POST);
			/* The free-form table at the bottom posts finished rows, so it
			   needs no expansion -- it is already one row per word. */
			$finds = (array)($_POST['find'] ?? array());
			$reps  = (array)($_POST['replacement'] ?? array());
			$types = (array)($_POST['match_type'] ?? array());
			foreach ($finds as $i => $find) {
				$rows[] = array(
					'type'        => (isset($types[$i]) && $types[$i] === 'description') ? 'description' : 'exact',
					'find'        => trim((string)$find),
					'replacement' => isset($reps[$i]) ? trim((string)$reps[$i]) : '',
				);
			}
			$conn->query("DELETE FROM theme_text WHERE theme_id = $theme_id");
			$wrote = 0; $wanted = 0;
			foreach ($rows as $row) {
				$find = $row['find'];
				$rep  = $row['replacement'];
				if ($find === '') continue;              /* a blank row is a deleted row */
				if ($rep === '') continue;               /* no override; no row */
				$wanted++;
				$type = $row['type'];
				if ($conn->query("INSERT INTO theme_text
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
	$r = $conn->query("SELECT match_type, find, replacement FROM theme_text
	                    WHERE theme_id = " . (int)$theme['id'] . "
	                    ORDER BY match_type ASC, sort_order ASC, id ASC");
	if ($r) while ($row = $r->fetch_assoc()) $pairs[] = $row;
}
/* EVERY project, not getProjects(). That is the player-facing list and it
   hides Filthy Mermaid and Dread City by name -- the two themes most in
   need of editing were the two this dropdown could not offer. */
$projects = dropshipAdminProjects($conn);
$h = function ($v) { return htmlspecialchars((string)$v, ENT_QUOTES); };
$val = function ($k, $d = '') use ($theme, $h) {
	return $h($theme && isset($theme[$k]) && $theme[$k] !== '' ? $theme[$k] : $d);
};
?>
<style>
/* FULL WIDTH of the content pane -- a capped column here is just empty
   screen beside the form. */
.dsa{text-align:left}
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
.dsa h4{color:#e8eaed;font-size:13px;margin:16px 0 4px;text-align:left;
  text-transform:uppercase;letter-spacing:.07em;opacity:.8}
.dsa .all{font-size:10px;color:#00c8a0;margin-left:5px}
.dsa .also{font-size:11px;color:#7a9eb0}
.dsa .pol{font-family:ui-monospace,Menlo,monospace;font-size:11px;color:#7a9eb0;
  word-break:break-all;padding:6px 8px;background:#07111d;border-radius:3px;
  border:1px solid rgba(255,255,255,.16);text-align:left}
.dsa .pol.none{font-family:inherit;color:#e8a0b0;border-color:rgba(224,70,107,.45)}
/* The editor is folded away until asked for: the wording table alone is
   sixty rows, and the list of themes is what the page is actually for. */
.dsa details.editor{margin:0 0 14px}
.dsa details.editor > summary{display:inline-block;cursor:pointer;list-style:none;
  background:#00c8a0;color:#07111d;border-radius:4px;padding:8px 15px;
  font-weight:bold;font-size:13px;text-align:left}
.dsa details.editor > summary::-webkit-details-marker{display:none}
.dsa details.editor[open] > summary{background:none;color:#00c8a0;padding:0 0 10px;
  border-bottom:1px solid rgba(0,200,160,.25);border-radius:0;display:block}
.dsa .good-n{color:#00c8a0;font-weight:bold}
.dsa .bad-n{color:#e8a0b0;font-weight:bold}
.dsa code{color:#e8eaed}
.dsa button,.dsa .btn{background:#00c8a0;color:#07111d;border:0;border-radius:4px;
  padding:8px 15px;font-weight:bold;cursor:pointer;font-size:13px;text-decoration:none;display:inline-block}
</style>
<div class="row"><div class="main"><div class="content dsa">
<h2>Themes</h2>

<?php if (!$have_tables): ?>
	<div class="msg bad">The tables <code>themes</code> and
	<code>theme_text</code> do not exist on this database. Nothing here can
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

<?php
/*
 * FOLDED AWAY UNTIL IT IS WANTED. The wording table alone is sixty rows,
 * so the page opened on a wall of inputs that is only relevant once you
 * have decided to change something -- and the list of themes, which is
 * what you actually came to look at, was pushed off the top.
 *
 * OPEN WHEN EDITING, because arriving from an Edit link IS the decision;
 * making somebody click a second time to see what they asked for would be
 * worse than the wall.
 *
 * <details> rather than a script: it keeps working with JavaScript off,
 * the browser gives it keyboard and screen-reader behaviour for free, and
 * there is no state to get out of step with the heading.
 */
?>
<details class="editor"<?php echo $theme ? ' open' : ''; ?>>
<summary><?php echo $theme
	? 'Editing ' . $h($theme['slug']) . ' &mdash; hide'
	: 'Add a theme'; ?></summary>
<form method="post" action="admin-themes.php">
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
	<div><label>Media extension</label>
		<input type="text" name="extension" value="<?php echo $val('extension', 'gif'); ?>"></div>
	<div><label>Collection (projects.policy_id)</label>
		<?php $pol = $edit_pid ? dropshipProjectPolicy($conn, $edit_pid) : ''; ?>
		<?php if ($pol !== ''): ?>
			<div class="pol"><?php echo $h($pol); ?></div>
		<?php else: ?>
			<div class="pol none">Not set &mdash; this theme will load no NFTs on
			<b>either</b> setting below, with no error anywhere. Set
			<code>projects.policy_id</code> for this project first.</div>
		<?php endif; ?></div>
	<div><label>Ken Burns pan</label>
		<?php $kb = $theme && !empty($theme['ken_burns']) ? $theme['ken_burns'] : 'auto'; ?>
		<select name="ken_burns">
			<option value="auto"<?php echo !in_array($kb, array('on', 'off'), true) ? ' selected' : ''; ?>>Follow the format &mdash; stills drift, gifs do not</option>
			<option value="on"<?php echo $kb === 'on' ? ' selected' : ''; ?>>Always on</option>
			<option value="off"<?php echo $kb === 'off' ? ' selected' : ''; ?>>Always off</option>
		</select></div>
	<div><label>Stage art order</label>
		<?php $so = $theme && !empty($theme['stage_order']) ? $theme['stage_order'] : 'random'; ?>
		<select name="stage_order">
			<option value="random"<?php echo $so !== 'linear' ? ' selected' : ''; ?>>Shuffled &mdash; a different frame each round</option>
			<option value="linear"<?php echo $so === 'linear' ? ' selected' : ''; ?>>In order &mdash; 1, 2, 3 through the scene</option>
		</select></div>
	<div><label>NFT holdings from</label>
		<select name="trait_source">
			<?php $ts = $theme && !empty($theme['trait_source']) ? $theme['trait_source'] : 'koios'; ?>
			<option value="koios"<?php echo $ts !== 'skulliance' ? ' selected' : ''; ?>>Koios &mdash; read the player&rsquo;s wallets</option>
			<option value="skulliance"<?php echo $ts === 'skulliance' ? ' selected' : ''; ?>>Skulliance &mdash; use holdings already synced</option>
		</select></div>
	<div><label>Is it working?</label>
		<a class="btn" href="admin-themes.php?project_id=<?php echo (int)$edit_pid; ?>&amp;probe=1">Check this theme</a></div>
	<div><label>Active</label>
		<select name="active">
			<option value="0"<?php echo ($theme && !$theme['active']) ? ' selected' : ''; ?>>no</option>
			<option value="1"<?php echo ($theme && $theme['active']) ? ' selected' : ''; ?>>yes</option>
		</select></div>
</div>

<?php
/*
 * WHY NO NFTs -- on demand, because both pipelines fail silently and in
 * several different places, and every one of them looks identical from the
 * outside: an empty barracks and no error. Behind a GET so it only runs
 * when asked; it writes nothing.
 */
if ($edit_pid && isset($_GET['probe'])):
	$probe = dropshipNftProbe($conn, $edit_pid,
		isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '');
?>
<h3>Theme check</h3>
<p class="note">Everything that has to be true before this theme can be played,
checked for your own account. <b>Any zero is a reason it is not working.</b></p>
<table>
	<tr><th style="width:320px">Step</th><th style="width:70px">Found</th><th>&nbsp;</th></tr>
	<?php foreach ($probe as $row): ?>
	<tr>
		<td><?php echo $h($row['step']); ?></td>
		<td><?php if ($row['count'] === null): ?><span class="bad-n">failed</span>
		    <?php else: ?><span class="<?php echo $row['count'] ? 'good-n' : 'bad-n'; ?>"><?php
		    echo (int)$row['count']; ?></span><?php endif; ?></td>
		<td class="pol" style="border:0;background:none;padding:0"><?php echo $h($row['detail']); ?></td>
	</tr>
	<?php endforeach; ?>
</table>
<?php endif; ?>

<h3>Wording</h3>
<?php
/*
 * THE GUIDE. Every Drop Ship word the existing themes had to answer for,
 * so reworking a theme is a form to fill in rather than a blank box to
 * speculate into.
 *
 * ONE BOX PER THING, NOT PER STRING. The game asks for the same thing in up
 * to four ways -- the bare tier on a soldier card, the item in the shop, the
 * word mid-sentence in a results line, and the icon filename -- and they
 * always take the same answer. dropshipThemeVocabulary() groups those into
 * families; this form shows the family, and dropshipThemeVocabExpand()
 * writes every word in it.
 *
 * NOTHING IS SHOWN THAT CAN BE WORKED OUT. An icon filename is the theme's
 * own word slugified -- dropshipThemeText() derives it on the way out -- so
 * it is not a field here and not a row in the table. It was both, briefly,
 * and that was the same fact in two places: rename Medkit and the icon row
 * still said 'nurse' until somebody edited it too, with nothing on screen
 * saying that was why the shop had the wrong picture. The FILE is managed on
 * the art screen, next to the art for the same thing.
 */
$vocab = dropshipThemeVocabulary();
$have  = array();                       /* find => replacement, already saved */
foreach ($pairs as $pr) $have[$pr['match_type'] . '|' . $pr['find']] = $pr['replacement'];
$known = array();
foreach ($vocab as $rows) foreach ($rows as $r) {
	foreach ($r['exact'] as $w)  $known['exact|' . $w] = true;
	foreach ($r['desc'] as $w)   $known['description|' . $w] = true;
	foreach ($r['icons'] as $ic) $known['exact|' . $ic] = true;
}
/* What this theme already says for a family: whichever of its words is
   saved. They all take the same answer, so the first one found is it. */
$said = function ($r) use ($have) {
	foreach ($r['exact'] as $w)  if (isset($have['exact|' . $w]))       return $have['exact|' . $w];
	foreach ($r['desc'] as $w)   if (isset($have['description|' . $w])) return $have['description|' . $w];
	return '';
};
$done = 0; $total = 0;
foreach ($vocab as $rows) foreach ($rows as $r) { $total++; if ($said($r) !== '') $done++; }
?>
<p class="note"><b><?php echo $done; ?> of <?php echo $total; ?></b> terms given this
theme&rsquo;s own word. Leave a box empty and the theme keeps the Drop Ship word, so
you only fill in what you actually want to change.<br>
One box covers every way the game asks for that thing: the label on its own, the
item in the shop, and the word in the middle of a results line.<br>
It names the icon too. Call something &ldquo;Smoking Jacket&rdquo; and the page
asks for <code>icons/smoking-jacket.png</code> - you never type that, and there
is nowhere here to change it. The picture itself lives on
<a href="admin-art.php?project_id=<?php echo (int)$edit_pid; ?>">Theme Art</a>,
beside the rest of the art for the same thing.</p>

<?php foreach ($vocab as $group => $rows): ?>
<h4><?php echo $h($group); ?></h4>
<table>
	<tr><th style="width:240px">Drop Ship word</th><th>This theme says</th></tr>
	<?php foreach ($rows as $r):
		$say  = $said($r);
		/* What else this one box covers, so it is clear why there is only
		   one. The label itself is always in there, so it is not repeated. */
		$also = array();
		foreach ($r['exact'] as $w) if ($w !== $r['label']) $also[] = $w;
		if ($r['desc'])  $also[] = 'in a sentence';
		if ($r['icons']) $also[] = 'its icon';
		?>
	<tr>
		<td>
			<input type="hidden" name="fam_label[]" value="<?php echo $h($r['label']); ?>">
			<code><?php echo $h($r['label']); ?></code>
			<?php if ($r['themes'] >= 3): ?><span class="all">all themes</span><?php endif; ?>
			<?php if ($also): ?><br><span class="also">also
				<?php echo $h(implode(', ', $also)); ?></span><?php endif; ?>
		</td>
		<td><input type="text" name="fam_say[]" value="<?php echo $h($say); ?>"
		           placeholder="<?php echo $h($r['label']); ?>"></td>
	</tr>
	<?php endforeach; ?>
</table>
<?php endforeach; ?>

<h4>Anything else</h4>
<p class="note">The list above is a guide, not a limit. Add a row for a word it
does not cover.</p>
<table id="dsa-pairs">
	<tr><th style="width:150px">Match</th><th>Replace this</th><th>With this</th></tr>
	<?php
	/* Saved pairs that are not in the vocabulary, plus blanks for new ones. */
	$extra = array();
	foreach ($pairs as $pr) {
		if (!isset($known[$pr['match_type'] . '|' . $pr['find']])) $extra[] = $pr;
	}
	foreach (array_merge($extra, array(null, null)) as $pr): ?>
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
</details>
<script>
function dsaAddRow(){
	var t = document.getElementById('dsa-pairs');
	var r = t.insertRow(-1);
	r.innerHTML = t.rows[t.rows.length-2].innerHTML
		.replace(/ selected/g, '').replace(/value="[^"]*"/g, 'value=""');
}
</script>
<?php dsaClose($conn); ?>
