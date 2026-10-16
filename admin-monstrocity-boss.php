<?php
/*
 * Monstrocity bosses: list, create, edit.
 *
 * There was no way to make one. Bosses are hand-inserted in phpMyAdmin,
 * which is how a boss ends up pointing at a collection that is not there:
 * get-bosses.php INNER JOINs projects AND collections, so a bad id does not
 * raise anything, the boss simply never appears in the game. That is the
 * failure this form exists to make impossible.
 *
 * ART IS ONE FLAT FOLDER, shared by every theme -- images/monstrocity/bosses/
 * with the red variant under bosses/battle-damaged/ and the same filename.
 * The filename comes from the NAME, so renaming a boss moves its art rather
 * than orphaning it.
 *
 * A boss belongs to a theme, and the theme has to exist first.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/admin-lib.php';
admin_require();
require_once __DIR__ . '/monstrocity-art-lib.php';

$msgs = array('err' => array(), 'ok' => array());
$edit_id  = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
$creating = isset($_GET['new']);

function mb_row($conn, $id) {
	$r = $conn->query('SELECT * FROM bosses WHERE id = ' . (int)$id);
	return $r ? $r->fetch_assoc() : null;
}
function mb_all($conn) {
	$out = array();
	$r = $conn->query('SELECT b.*, p.name AS project_name, c.name AS collection_name
	                     FROM bosses b
	                     LEFT JOIN projects p ON p.id = b.project_id
	                     LEFT JOIN collections c ON c.id = b.collection_id
	                    ORDER BY b.theme ASC, b.strength ASC, b.speed ASC, b.tactics ASC, b.id ASC');
	if ($r) while ($x = $r->fetch_assoc()) $out[] = $x;
	return $out;
}
function mb_themes($conn) {
	$out = array();
	$r = $conn->query('SELECT slug, project_name, title FROM monstrocity_themes
	                    WHERE active = 1 OR 1 = 1 ORDER BY sort_order ASC');
	if ($r) while ($x = $r->fetch_assoc()) $out[$x['slug']] = $x['project_name'] . ' - ' . $x['title'];
	return $out;
}
function mb_collections($conn) {
	$out = array();
	$r = $conn->query('SELECT c.id, c.name, c.project_id, p.name AS project
	                     FROM collections c LEFT JOIN projects p ON p.id = c.project_id
	                    ORDER BY p.name ASC, c.name ASC');
	if ($r) while ($x = $r->fetch_assoc()) $out[] = $x;
	return $out;
}
/**
 * What art exists for a boss.
 *
 * THE SCORE IS OPT-IN, AND THAT IS THE WHOLE PERFORMANCE STORY. Computing it
 * decodes BOTH PNGs in full -- half a megabyte of pixels each, more for the
 * larger bosses -- and then samples twenty-odd thousand of them. At about
 * 11ms a boss on a fast machine and several times that on the host, a list
 * of forty was doing a second or two of image work before it drew anything,
 * every single load, to print a number nobody was reading there.
 *
 * The existence checks and the dimensions come from the file header and cost
 * nothing measurable. Only the tune screen and the edit form ask for the
 * score, and they look at one theme or one boss.
 */
function mb_art($slug, $ext, $with_score = false) {
	$dir = __DIR__ . '/' . monstrocity_boss_dir();
	$base = $dir . '/' . $slug . '.' . $ext;
	$dmg  = $dir . '/battle-damaged/' . $slug . '.' . $ext;
	$a = array('base' => is_file($base), 'damaged' => is_file($dmg), 'score' => -1, 'dim' => '');
	if ($a['base']) { $d = @getimagesize($base); if ($d) $a['dim'] = $d[0] . 'x' . $d[1]; }
	if ($with_score && $a['base'] && $a['damaged']) {
		$b = mart_load($base); $v = mart_load($dmg);
		if ($b && $v && imagesx($b) === imagesx($v)) $a['score'] = round(mart_damage_score($b, $v), 1);
	}
	return $a;
}

/*
 * A POST OVER post_max_size ARRIVES AS NO POST AT ALL -- $_POST and $_FILES
 * are both discarded before this runs, with no warning and nothing in
 * $_FILES['x']['error'] because $_FILES does not exist. The page would then
 * validate an empty form and blame whichever field it checked first. Same
 * detection as the theme form; boss art is the biggest thing posted here.
 */
$post_discarded = monstrocity_post_discarded(
	$_SERVER['REQUEST_METHOD'] ?? '', $_POST, $_SERVER['CONTENT_LENGTH'] ?? 0);
if ($post_discarded) {
	$msgs['err'][] = 'That image was too large, so the server dropped the whole form before this '
	               . 'page ran. You sent ' . number_format((int)$_SERVER['CONTENT_LENGTH'] / 1048576, 1)
	               . 'MB; the limit is ' . htmlspecialchars((string)ini_get('post_max_size'))
	               . ' per form and ' . htmlspecialchars((string)ini_get('upload_max_filesize'))
	               . ' per file. Export the art smaller -- it is resized to '
	               . MONSTROCITY_ART_WIDTH . 'px wide anyway.';
	$creating = true;
}

/*
 * BATCH TUNING. Bosses are met as a sequence, so the numbers only make sense
 * next to each other -- which a one-at-a-time form cannot show. This saves a
 * whole theme's worth of stats in one pass.
 *
 * It is a batch of FIELDS, not of files. Nothing here uploads anything, so
 * the one-request-per-image rule the art board lives by is untouched.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$post_discarded && isset($_POST['batch'])) {
	$rows = isset($_POST['b']) && is_array($_POST['b']) ? $_POST['b'] : array();
	$saved = 0; $bad = array();
	$existing = array();
	foreach (mb_all($conn) as $o) $existing[(int)$o['id']] = $o;

	foreach ($rows as $rid => $f) {
		$rid = (int)$rid;
		if (!isset($existing[$rid])) continue;
		/* Merge over the stored row: the batch form carries the tunable
		   numbers only, and validating a half-row would reject it for fields
		   this screen never showed. */
		$merged = array(
			'max_health' => (int)($f['max_health'] ?? 0),
			'strength'   => (int)($f['strength'] ?? 0),
			'speed'      => (int)($f['speed'] ?? 0),
			'tactics'    => (int)($f['tactics'] ?? 0),
			'size'       => $f['size'] ?? '',
			'powerup'    => $f['powerup'] ?? '',
		);
		/* ONLY WHAT THIS SCREEN SHOWS. Running the full boss validator over
		   the stored row refused thirteen rows for an orientation this form
		   does not display, one boss saved, and there was no way to act on
		   any of it. A pre-existing problem in a field the screen does not
		   offer belongs to the single-boss form, which does offer it. */
		$e = monstrocity_validate_boss_stats($merged);
		if ($e) { $bad[] = $existing[$rid]['name'] . ': ' . $e[0]; continue; }
		$conn->query('UPDATE bosses SET max_health=' . (int)$merged['max_health']
			. ',strength=' . (int)$merged['strength']
			. ',speed='    . (int)$merged['speed']
			. ',tactics='  . (int)$merged['tactics']
			. ',size='     . "'" . $conn->real_escape_string($merged['size']) . "'"
			. ',powerup='  . "'" . $conn->real_escape_string($merged['powerup']) . "'"
			. ' WHERE id = ' . $rid);
		$saved++;
	}
	if ($saved) $msgs['ok'][] = $saved . ' boss' . ($saved === 1 ? '' : 'es') . ' updated.';
	foreach ($bad as $m) $msgs['err'][] = $m;
	$batch_theme = (string)($_POST['batch_theme'] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$post_discarded && !isset($_POST['batch'])) {
	$id = (int)($_POST['id'] ?? 0);
	$b = array(
		'name'          => trim($_POST['name'] ?? ''),
		'project_id'    => (int)($_POST['project_id'] ?? 0),
		'collection_id' => (int)($_POST['collection_id'] ?? 0),
		'theme'         => trim($_POST['theme'] ?? ''),
		'max_health'    => (int)($_POST['max_health'] ?? 0),
		'strength'      => (int)($_POST['strength'] ?? 0),
		'speed'         => (int)($_POST['speed'] ?? 0),
		'tactics'       => (int)($_POST['tactics'] ?? 0),
		'size'          => $_POST['size'] ?? '',
		'powerup'       => $_POST['powerup'] ?? '',
		'orientation'   => $_POST['orientation'] ?? '',
		'extension'     => $_POST['extension'] ?? 'png',
	);
	$themes = mb_themes($conn);
	$taken = array();
	foreach (mb_all($conn) as $o) if ((int)$o['id'] !== $id) $taken[] = monstrocity_boss_slug($o['name']);
	$errs = monstrocity_validate_boss($b, $taken, array_keys($themes));

	if ($errs) {
		$msgs['err'] = $errs; $edit_id = $id; $creating = !$id; $repost = $b;
	} else {
		$slug = monstrocity_boss_slug($b['name']);
		$esc = function ($v) use ($conn) { return "'" . $conn->real_escape_string($v) . "'"; };
		$was = $id ? mb_row($conn, $id) : null;

		/* RENAME MOVES THE ART. A boss name is free text somebody will want to
		   correct, and the filename is derived from it, so leaving the files
		   behind is how a boss starts showing a broken image. */
		if ($was) {
			$mv = mart_rename_boss(monstrocity_boss_slug($was['name']), $slug, $was['extension']);
			if ($mv !== '') $msgs['err'][] = $mv;
		}

		$set = 'name=' . $esc($b['name'])
		     . ',project_id=' . (int)$b['project_id']
		     . ',collection_id=' . (int)$b['collection_id']
		     . ',theme=' . $esc($b['theme'])
		     . ',max_health=' . (int)$b['max_health']
		     . ',strength=' . (int)$b['strength']
		     . ',speed=' . (int)$b['speed']
		     . ',tactics=' . (int)$b['tactics']
		     . ',size=' . $esc($b['size'])
		     . ',powerup=' . $esc($b['powerup'])
		     . ',orientation=' . $esc($b['orientation'])
		     . ',extension=' . $esc($b['extension']);
		if ($id) {
			$conn->query('UPDATE bosses SET ' . $set . ' WHERE id = ' . $id);
			$msgs['ok'][] = 'Saved.';
		} else {
			/* health starts full; the nightly reset in db.php sets it back to
			   max_health anyway, but a new boss should not arrive at zero. */
			$conn->query('INSERT INTO bosses SET ' . $set . ', health=' . (int)$b['max_health']);
			$id = (int)$conn->insert_id;
			$msgs['ok'][] = 'Created "' . htmlspecialchars($b['name']) . '".';
		}

		if (isset($_FILES['art']) && $_FILES['art']['error'] !== UPLOAD_ERR_NO_FILE) {
			if ($_FILES['art']['error'] !== UPLOAD_ERR_OK) {
				$msgs['err'][] = 'Art: upload failed (code ' . (int)$_FILES['art']['error'] . ').';
			} else {
				list($w, $e) = mart_accept_boss($_FILES['art']['tmp_name'], $slug,
				                                !empty($_POST['flip']), $b['extension']);
				if ($w === '')      $msgs['err'][] = 'Art: ' . $e;
				elseif ($e !== '')  $msgs['err'][] = 'Art: ' . $e;
				else                $msgs['ok'][]  = 'Art saved and the red variant regenerated.';
			}
		}
		/*
		 * WHERE TO GO NEXT. Saving used to land you back on the boss you had
		 * just finished, so building a set meant going out to the list and
		 * back in for every one.
		 *
		 * "Save and copy" is the one that matters here: a ladder is built by
		 * making the next boss out of the last one, and this is that loop
		 * with nothing in between. It is a redirect rather than a re-render,
		 * so a refresh cannot submit the same boss twice.
		 *
		 * Only on a clean save. If anything went wrong -- the art was
		 * refused, say -- staying put is the only way to see why.
		 */
		if (!$msgs['err'] && !empty($_POST['after'])) {
			$to = $_POST['after'] === 'copy'
			    ? 'admin-monstrocity-boss.php?new=1&from=' . (int)$id
			    : 'admin-monstrocity-boss.php?new=1';
			header('Location: ' . $to . '&saved=' . urlencode($b['name']));
			exit;
		}
		$edit_id = $id; $creating = false;
	}
}

$bosses      = mb_all($conn);
$themes      = mb_themes($conn);
$projects    = adm_projects($conn);
$collections = mb_collections($conn);
$row = $edit_id ? mb_row($conn, $edit_id) : null;
if ($creating && !$row) {
	$row = array('id'=>0,'name'=>'','project_id'=>0,'collection_id'=>0,'theme'=>'','max_health'=>250,
	             'strength'=>5,'speed'=>5,'tactics'=>5,'size'=>'Medium','powerup'=>'Heal',
	             'orientation'=>'Left','extension'=>'png');
	/*
	 * DUPLICATE. Everything except the name and the art, because both are
	 * identified by the name: copying the filename would point two bosses at
	 * one image and the second one saved would replace the first's.
	 *
	 * Strength is bumped by one so the copy lands AFTER its source rather
	 * than tying with it -- a tie has no defined order at all, which is the
	 * single easiest thing to get wrong when building a set this way.
	 */
	if (isset($_GET['saved']) && $_GET['saved'] !== '')
		$msgs['ok'][] = 'Saved "' . htmlspecialchars((string)$_GET['saved']) . '". Here is a blank one.';
	$from = isset($_GET['from']) ? (int)$_GET['from'] : 0;
	if ($from && ($src = mb_row($conn, $from))) {
		foreach (monstrocity_boss_copyable() as $k)
			if (isset($src[$k])) $row[$k] = $src[$k];
		$row['strength'] = min(10, (int)$src['strength'] + 1);
		$dup_from = $src['name'];
		if (isset($_GET['saved'])) {
			array_pop($msgs['ok']);
			$msgs['ok'][] = 'Saved "' . htmlspecialchars((string)$_GET['saved'])
			              . '". This one is a copy of it &mdash; rename it and adjust the numbers.';
		}
	}
}
if (isset($repost)) $row = array_merge((array)$row, $repost);

/*
 * A theme's bosses, in the order the game will present them -- through the
 * SAME function the game's SQL mirrors, not a second sort that drifts.
 *
 * AND THEIR GLOBAL POSITION. The tune screen shows one theme; the game lists
 * every boss in one climb, so a theme's bosses are a SUBSEQUENCE of that. A
 * row numbered 3 of 5 here can be 11th overall, and without saying so the
 * panel looks like it disagrees with the game.
 */
$batch_theme = isset($batch_theme) ? $batch_theme : (isset($_GET['tune']) ? (string)$_GET['tune'] : '');
$globalPos = array();
foreach (monstrocity_boss_order($bosses) as $gi => $gb) $globalPos[(int)$gb['id']] = $gi + 1;
$batch_rows = array();
if ($batch_theme !== '') {
	foreach ($bosses as $bx) if ($bx['theme'] === $batch_theme) $batch_rows[] = $bx;
	$batch_rows = monstrocity_boss_order($batch_rows);
}
$batch_problems = monstrocity_boss_set_problems($batch_rows);
$batch_maxhp = 1;
foreach ($batch_rows as $bx) $batch_maxhp = max($batch_maxhp, (int)$bx['max_health']);

include 'header.php';
admin_chrome('bosses');   // its own tab, not the themes one
?>
<style>
table.mb { width:100%; border-collapse:collapse; font-size:12px }
table.mb th { text-align:left; font-weight:600; color:#7a9eb0; padding:7px 8px; border-bottom:1px solid rgba(0,200,160,.18); white-space:nowrap }
table.mb td { padding:7px 8px; border-bottom:1px solid rgba(255,255,255,.05); vertical-align:middle }
table.mb a.bn { font-family:ui-monospace,Menlo,monospace; text-align:left; color:#00c8a0 }
table.mb tr.gap td { border-top:1px solid rgba(0,200,160,.22); padding-top:11px }
.mb-theme { font-size:10px; letter-spacing:.05em; text-transform:uppercase; color:#00c8a0 }
.mb-stat { font-family:ui-monospace,Menlo,monospace; color:#9fb4c2 }
.mb-art { font-family:ui-monospace,Menlo,monospace; font-size:11px; white-space:nowrap }
.mb-art b { color:#00c8a0 } .mb-art i { color:#e0466b; font-style:normal }
.mb-warn { color:#f5a623 }
.mb-thumb { width:40px; height:40px; object-fit:contain; background:#07111d; border-radius:3px; display:block }
.mb-sect { background:#0a1929; border:1px solid rgba(0,200,160,.15); border-radius:5px; padding:16px 18px; margin:0 0 14px }
.mb-sect > h3 { margin:0 0 3px; font-size:13px; letter-spacing:.04em; text-transform:uppercase; color:#00c8a0 }
.mb-sect > .lead { margin:0 0 14px; font-size:11px; color:#5a7888; line-height:1.5 }
.mb-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(200px,280px)); gap:14px 20px; justify-content:start }
.mb-field label { display:block; font-size:11px; color:#7a9eb0; margin:0 0 4px; text-align:left }
.mb-field input[type=text], .mb-field input[type=number], .mb-field select, .mb-field input[type=file] {
  width:100%; box-sizing:border-box; background:#07111d; color:#e8eaed;
  border:1px solid rgba(255,255,255,.14); border-radius:3px; padding:7px 9px; font-size:12px }
.mb-field .hint { font-size:11px; color:#5a7888; margin:5px 0 0; line-height:1.45 }
.mb-check { display:flex; align-items:center; gap:7px; font-size:12px; color:#e8eaed; margin:9px 0 0 }
.mb-back { font-size:12px; display:inline-block; margin:0 0 12px; text-align:left }
.mb-actions { display:flex; gap:9px; margin-top:12px }
.mb-lead { font-size:11px; color:#5a7888; margin:0 0 12px; line-height:1.55; max-width:80ch }
.mb-probs { background:rgba(224,70,107,.08); border:1px solid rgba(224,70,107,.35); border-radius:4px;
            padding:9px 12px; margin:0 0 14px; font-size:11px; color:#e8a0b0; line-height:1.6 }
table.mb-tune input.n { width:52px; text-align:center; background:#07111d; color:#e8eaed;
  border:1px solid rgba(255,255,255,.14); border-radius:3px; padding:5px 4px; font-size:12px;
  font-family:ui-monospace,Menlo,monospace }
table.mb-tune input.n.wide { width:74px }
table.mb-tune select { background:#07111d; color:#e8eaed; border:1px solid rgba(255,255,255,.14);
  border-radius:3px; padding:5px 6px; font-size:11px }
.mb-bar { height:8px; background:#07111d; border:1px solid rgba(255,255,255,.1); border-radius:999px; overflow:hidden }
.mb-bar span { display:block; height:100%; background:linear-gradient(90deg,#00a882,#00c8a0);
                transition:width .18s ease }
.mb-mv { white-space:nowrap }
.mb-mv button { background:#10222f; color:#9fb4c2; border:1px solid rgba(255,255,255,.14);
                border-radius:3px; cursor:pointer; font-size:12px; line-height:1; padding:3px 6px }
.mb-mv button:hover:not(:disabled) { border-color:#00c8a0; color:#00c8a0 }
.mb-mv button:disabled { opacity:.25; cursor:default }
table.mb-tune tr.moved td { background:rgba(0,200,160,.09) }
.mb-dirty { color:#f5a623; font-size:11px; margin-left:10px }
details.mb-proj { border:1px solid rgba(0,200,160,.18); border-radius:5px; margin:0 0 8px; background:#0a1929 }
/*
 * THE PROJECT NAME WAS CENTRED, AND NOT BY AN INHERITED RULE.
 *
 * The disclosure triangle is a ::before on this summary, and a pseudo-
 * element on a flex container IS A FLEX ITEM. So there were three items --
 * marker, name, count -- and justify-content:space-between does exactly
 * what it says with three: first at the start, last at the end, and the
 * middle one CENTRED. The name sat in the middle of a very wide row.
 *
 * flex-start keeps the name against its marker; margin-left:auto on the
 * count takes it to the far edge, which is what space-between was there
 * for in the first place. text-align:left is stated anyway: flexbox.css
 * centres bare anchors platform-wide and this panel has been caught by
 * inherited alignment before.
 */
details.mb-proj > summary { cursor:pointer; padding:10px 13px; font-size:13px; color:#e8eaed;
  list-style:none; display:flex; justify-content:flex-start; align-items:baseline; gap:12px;
  text-align:left }
details.mb-proj > summary span { margin-left:auto }
details.mb-proj > summary::-webkit-details-marker { display:none }
details.mb-proj > summary::before { content:'\25B8'; color:#00c8a0; margin-right:8px; font-size:11px }
details.mb-proj[open] > summary::before { content:'\25BE' }
details.mb-proj > summary span { color:#5a7888; font-size:11px; font-family:ui-monospace,Menlo,monospace }
details.mb-coll { margin:0 11px 10px 22px; border-left:2px solid rgba(0,200,160,.2) }
details.mb-coll > summary { cursor:pointer; padding:6px 10px; font-size:11px; color:#00c8a0;
  letter-spacing:.04em; text-transform:uppercase; list-style:none; text-align:left }
details.mb-coll > summary::-webkit-details-marker { display:none }
details.mb-coll > summary span { color:#5a7888; margin-left:8px; text-transform:none }
details.mb-coll table.mb { margin:0 0 4px 10px; width:calc(100% - 10px) }
table.mb-tune tr.mb-run td { background:rgba(0,200,160,.07); border-top:1px solid rgba(0,200,160,.3);
  font-size:11px; letter-spacing:.05em; text-transform:uppercase; color:#00c8a0; padding:8px 8px 6px;
  text-align:left }
table.mb-tune tr.mb-run td span { color:#5a7888; text-transform:none; letter-spacing:0 }
.mb-prev { display:flex; gap:10px; align-items:flex-start; margin-top:8px }
.mb-prev figure { margin:0; text-align:center }
.mb-prev img { width:110px; height:110px; object-fit:contain; background:#07111d;
               border:1px solid rgba(255,255,255,.12); border-radius:4px; display:block }
.mb-prev figcaption { font-size:10px; color:#5a7888; margin-top:3px }
</style>

<?php if (!$row && $batch_theme !== ''): /* ========== TUNE A THEME'S LADDER ========== */ ?>

  <a class="mb-back" href="admin-monstrocity-boss.php">&larr; All bosses</a>
  <?php admin_flash($msgs); ?>
  <p class="adm-sub">
    Tuning <b><?php echo htmlspecialchars(isset($themes[$batch_theme]) ? $themes[$batch_theme] : $batch_theme); ?></b>
    &mdash; <b><?php echo count($batch_rows); ?></b> bosses, shown in the order players meet them.
    <a class="adm-btn" href="admin-monstrocity-boss.php?new=1" style="margin-left:8px">New boss</a>
  </p>
  <p class="mb-lead">
    <b>The picker groups by project, then collection</b>, and inside each group orders by
    strength, then speed, then tactics. The headings below are those groups, so what you see here
    is the sequence players meet. Sharing a strength is fine &mdash; speed and then tactics
    separate them, which is what makes a boss placeable without guesswork.<br>
    The arrows swap a boss's whole difficulty block with its neighbour, so the curve you tune
    stays put and only the bosses move through it. <b>In game</b> is the position among all
    <?php echo count($bosses); ?> bosses; the bar is health relative to the toughest on this theme.
  </p>

  <?php if ($batch_problems): ?>
    <div class="mb-probs">
      <?php foreach ($batch_problems as $pr): ?><div>&bull; <?php echo htmlspecialchars($pr); ?></div><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (!$batch_rows): ?>
    <p class="mb-lead">No bosses on this theme yet.</p>
  <?php else: ?>
  <form method="post" action="admin-monstrocity-boss.php">
    <input type="hidden" name="batch" value="1">
    <input type="hidden" name="batch_theme" value="<?php echo htmlspecialchars($batch_theme); ?>">
    <table class="mb mb-tune">
      <tr><th>#</th><th title="Position among ALL bosses, which is the order players see">In&nbsp;game</th>
          <th>Move</th><th></th><th>Boss</th><th>Health</th><th>Str</th><th>Spd</th><th>Tac</th>
          <th>Size</th><th>Powerup</th><th>Curve</th></tr>
      <?php $runKey = null; $runPos = 0;
            foreach ($batch_rows as $i => $bx): $bs = monstrocity_boss_slug($bx['name']);
            /* With the score: one theme at a time, and the number is the point here. */
            $ba = mb_art($bs, $bx['extension'], true);
            /* THE PICKER GROUPS BY PROJECT AND COLLECTION, so the climb a
               player experiences happens inside one run. Showing the same
               breaks here is the only way this screen can be read as the
               sequence it actually produces. */
            $thisRun = (int)$bx['project_id'] . ':' . (int)$bx['collection_id'];
            if ($thisRun !== $runKey): $runKey = $thisRun; $runPos = 0; ?>
      <tr class="mb-run"><td colspan="12">
        <?php echo htmlspecialchars($bx['project_name'] === null ? 'No project' : $bx['project_name']); ?>
        <span>&middot; <?php echo htmlspecialchars($bx['collection_name'] === null ? 'no collection' : $bx['collection_name']); ?></span>
      </td></tr>
      <?php endif; $runPos++; ?>
      <tr>
        <td class="mb-pos" style="color:#5a7888"><?php echo $runPos; ?></td>
        <td class="mb-glob" style="color:#5a7888;font-family:ui-monospace,Menlo,monospace">
          <?php echo isset($globalPos[(int)$bx['id']]) ? $globalPos[(int)$bx['id']] . '<span style="opacity:.5">/' . count($bosses) . '</span>' : '&mdash;'; ?>
        </td>
        <td class="mb-mv">
          <button type="button" class="mb-up" title="Move earlier">&uarr;</button>
          <button type="button" class="mb-dn" title="Move later">&darr;</button>
        </td>
        <td><?php if ($ba['base']): ?><img class="mb-thumb" alt=""
              src="<?php echo htmlspecialchars(monstrocity_boss_dir() . '/' . $bs . '.' . $bx['extension']); ?>"><?php endif; ?></td>
        <td><a class="bn" href="admin-monstrocity-boss.php?edit=<?php echo (int)$bx['id']; ?>"><?php echo htmlspecialchars($bx['name']); ?></a></td>
        <td><input type="number" name="b[<?php echo (int)$bx['id']; ?>][max_health]" min="1" max="100000"
                   value="<?php echo (int)$bx['max_health']; ?>" class="n wide"></td>
        <?php foreach (array('strength','speed','tactics') as $st): ?>
          <td><input type="number" name="b[<?php echo (int)$bx['id']; ?>][<?php echo $st; ?>]" min="1" max="10"
                     value="<?php echo (int)$bx[$st]; ?>" class="n"></td>
        <?php endforeach; ?>
        <?php /* Through the safe renderer: a strict === against the allowed list
                 silently showed the FIRST option for anything stored with a
                 different case, and this form submits every field -- one
                 "Save all" would have rewritten them. */ ?>
        <td><select name="b[<?php echo (int)$bx['id']; ?>][size]"><?php
              echo monstrocity_select_options($bx['size'], monstrocity_sizes()); ?></select></td>
        <td><select name="b[<?php echo (int)$bx['id']; ?>][powerup]"><?php
              echo monstrocity_select_options($bx['powerup'], monstrocity_powerups()); ?></select></td>
        <td style="width:150px">
          <div class="mb-bar"><span style="width:<?php echo (int)round((int)$bx['max_health'] / $batch_maxhp * 100); ?>%"></span></div>
        </td>
      </tr>
      <?php endforeach; ?>
    </table>
    <div class="mb-actions">
      <button class="adm-btn" type="submit">Save all <?php echo count($batch_rows); ?></button>
      <a class="adm-btn" href="admin-monstrocity-boss.php?new=1">Add another</a>
      <span class="mb-dirty" id="mb-dirty" hidden>Reordered &mdash; not saved yet.</span>
    </div>
  </form>

<script>
(function () {
  var table = document.querySelector('table.mb-tune');
  if (!table) return;

  /*
   * ARROWS MOVE THE BOSS, NOT THE NUMBERS.
   *
   * Order is strength, so reordering used to mean working out which numbers
   * produce the sequence you wanted -- arithmetic in service of a decision
   * that is purely "this one comes first".
   *
   * So the LADDER keeps its difficulty curve and the bosses move between its
   * rungs: pressing up swaps this boss's whole difficulty block (health,
   * strength, speed, tactics) with the one above. The curve stays exactly as
   * tuned and stays monotonic by construction, and the boss that moved takes
   * the difficulty of the position it moved into.
   *
   * Size and powerup do NOT swap. Those belong to the boss, not the rung --
   * a Large boss does not become Small by being fought earlier.
   *
   * Nothing is saved until Save all, so a reorder can be abandoned by
   * leaving the page.
   */
  var FIELDS = ['max_health', 'strength', 'speed', 'tactics'];

  function cellsOf(tr) {
    var out = {};
    FIELDS.forEach(function (f) {
      out[f] = tr.querySelector('input[name$="[' + f + ']"]');
    });
    return out;
  }

  function swapStats(a, b) {
    var ca = cellsOf(a), cb = cellsOf(b);
    FIELDS.forEach(function (f) {
      if (!ca[f] || !cb[f]) return;
      var t = ca[f].value; ca[f].value = cb[f].value; cb[f].value = t;
    });
  }

  function renumber() {
    var rows = Array.prototype.slice.call(table.querySelectorAll('tr')).slice(1);
    var maxHp = 1;
    rows.forEach(function (r) {
      var h = r.querySelector('input[name$="[max_health]"]');
      if (h) maxHp = Math.max(maxHp, parseInt(h.value, 10) || 0);
    });
    /* Numbered within the run, and the arrows stop at its edges, because
       that is the ladder a player climbs. */
    var runPos = 0;
    rows.forEach(function (r, i) {
      if (r.classList.contains('mb-run')) { runPos = 0; return; }
      runPos++;
      var prev = r.previousElementSibling, next = r.nextElementSibling;
      var firstInRun = !prev || prev.classList.contains('mb-run');
      var lastInRun  = !next || next.classList.contains('mb-run');
      var pos = r.querySelector('.mb-pos');
      if (pos) pos.textContent = runPos;
      var up = r.querySelector('.mb-up'), dn = r.querySelector('.mb-dn');
      if (up) up.disabled = firstInRun;
      if (dn) dn.disabled = lastInRun;
      var bar = r.querySelector('.mb-bar span');
      var h = r.querySelector('input[name$="[max_health]"]');
      if (bar && h) bar.style.width = Math.round((parseInt(h.value, 10) || 0) / maxHp * 100) + '%';
    });
  }

  function dirty() { var d = document.getElementById('mb-dirty'); if (d) d.hidden = false; }

  table.addEventListener('click', function (e) {
    var btn = e.target.closest('.mb-up, .mb-dn');
    if (!btn || btn.disabled) return;
    var tr = btn.closest('tr');
    var other = btn.classList.contains('mb-up') ? tr.previousElementSibling : tr.nextElementSibling;
    /* Never across a group heading. A boss's place in the picker is decided
       by its project and collection first, so swapping stats with something
       in a different run would move neither of them where it looked like
       they were going. */
    if (!other || other.classList.contains('mb-run') || !other.querySelector('.mb-pos')) return;
    swapStats(tr, other);
    if (btn.classList.contains('mb-up')) tr.parentNode.insertBefore(tr, other);
    else tr.parentNode.insertBefore(other, tr);
    tr.classList.add('moved');
    renumber(); dirty();
    /* Keep the pressed arrow under the cursor after the row moves. */
    var again = tr.querySelector(btn.classList.contains('mb-up') ? '.mb-up' : '.mb-dn');
    if (again && !again.disabled) again.focus();
  });

  table.addEventListener('input', function (e) {
    if (e.target.matches('input[name$="[max_health]"]')) renumber();
    dirty();
  });

  renumber();
})();
</script>
  <?php endif; ?>

<?php elseif (!$row): ?>

  <?php
    /* Grouped the way the picker groups them, which is also how they are
       looked for: project, then collection. Closed by default -- a flat list
       of every boss was downloading every thumbnail at full size before you
       could read anything. */
    $tree = array();
    foreach ($bosses as $bx) {
      $pk = $bx['project_name'] === null ? '\u{200B}No project' : $bx['project_name'];
      $ck = $bx['collection_name'] === null ? 'No collection' : $bx['collection_name'];
      $tree[$pk][$ck][] = $bx;
    }
    ksort($tree);
    foreach ($tree as &$cols) ksort($cols);
    unset($cols);
    $openProject = isset($_GET['open']) ? (string)$_GET['open'] : '';
  ?>
  <p class="adm-sub">
    <b><?php echo count($bosses); ?></b> bosses across <b><?php echo count($tree); ?></b> projects.
    <a class="adm-btn" href="admin-monstrocity-boss.php?new=1" style="margin-left:8px">New boss</a>
    <a class="adm-btn" href="admin-monstrocity.php">Themes</a>
  </p>
  <?php admin_flash($msgs); ?>

  <?php foreach ($tree as $pname => $cols):
    $pcount = 0; foreach ($cols as $cb) $pcount += count($cb);
    $isOpen = ($openProject !== '' && $openProject === $pname); ?>
    <details class="mb-proj"<?php echo $isOpen ? ' open' : ''; ?>>
      <summary><?php echo htmlspecialchars(ltrim($pname, "\u{200B}")); ?>
        <span><?php echo $pcount; ?> boss<?php echo $pcount === 1 ? '' : 'es'; ?>
        &middot; <?php echo count($cols); ?> collection<?php echo count($cols) === 1 ? '' : 's'; ?></span>
      </summary>
      <?php foreach ($cols as $cname => $list): ?>
        <details class="mb-coll" open>
          <summary><?php echo htmlspecialchars($cname); ?>
            <span><?php echo count($list); ?></span></summary>
          <table class="mb">
            <tr><th></th><th>Boss</th><th>Theme</th><th>HP</th><th>Str/Spd/Tac</th>
                <th>Size</th><th>Powerup</th><th>Art</th><th></th></tr>
            <?php foreach ($list as $bx):
              $slug = monstrocity_boss_slug($bx['name']);
              $a = mb_art($slug, $bx['extension']); ?>
            <tr>
              <td><?php if ($a['base']): ?><img class="mb-thumb" alt="" loading="lazy" decoding="async"
                    src="<?php echo htmlspecialchars(monstrocity_boss_dir() . '/' . $slug . '.' . $bx['extension']); ?>"><?php endif; ?></td>
              <td><a class="bn" href="admin-monstrocity-boss.php?edit=<?php echo (int)$bx['id']; ?>"><?php echo htmlspecialchars($bx['name']); ?></a></td>
              <td class="mb-theme"><?php echo htmlspecialchars($bx['theme']); ?>
                  <?php if (!isset($themes[$bx['theme']])): ?><br><span class="mb-warn">no such theme</span><?php endif; ?></td>
              <td class="mb-stat"><?php echo number_format((int)$bx['max_health']); ?></td>
              <td class="mb-stat"><?php echo (int)$bx['strength'] . '/' . (int)$bx['speed'] . '/' . (int)$bx['tactics']; ?></td>
              <td style="color:#7a9eb0"><?php echo htmlspecialchars($bx['size']); ?></td>
              <td style="color:#7a9eb0"><?php echo htmlspecialchars($bx['powerup']); ?></td>
              <td class="mb-art">
                <?php echo $a['base'] ? '<b>art</b>' : '<i>no art</i>'; ?>
                <?php echo $a['damaged'] ? ' <b>red</b>' : ' <i>no red</i>'; ?>
                <?php if ($a['dim']): ?><br><span style="color:#5a7888"><?php echo $a['dim']; ?></span><?php endif; ?>
              </td>
              <td style="white-space:nowrap">
                <a class="adm-btn" href="admin-monstrocity-boss.php?edit=<?php echo (int)$bx['id']; ?>">Edit</a>
                <a class="adm-btn" href="admin-monstrocity-boss.php?new=1&amp;from=<?php echo (int)$bx['id']; ?>"
                   title="Start a new boss from this one's settings">Copy</a>
                <a class="adm-btn" href="admin-monstrocity-boss.php?tune=<?php echo urlencode($bx['theme']); ?>"
                   title="Tune every boss on this theme together">Tune</a>
              </td>
            </tr>
            <?php endforeach; ?>
          </table>
        </details>
      <?php endforeach; ?>
    </details>
  <?php endforeach; ?>
  <?php if (!$tree): ?><p class="mb-lead">No bosses yet.</p><?php endif; ?>

<?php else: ?>

  <a class="mb-back" href="admin-monstrocity-boss.php">&larr; All bosses</a>
  <?php admin_flash($msgs); ?>
  <?php $slug = $row['name'] !== '' ? monstrocity_boss_slug($row['name']) : ''; ?>
  <form method="post" enctype="multipart/form-data" action="admin-monstrocity-boss.php">
    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">

    <div class="mb-sect">
      <h3><?php echo $row['id'] ? 'Boss' : 'New boss'; ?></h3>
      <p class="lead">The name is also the image filename. Renaming moves the art with it.
        <?php if (isset($dup_from)): ?><br>
          <b style="color:#00c8a0">Copied from <?php echo htmlspecialchars($dup_from); ?>.</b>
          Everything came across except the name and the art &mdash; both are identified by the
          name, so sharing them would point two bosses at one image. Strength was bumped by one so
          this one lands after its source instead of tying with it.
        <?php endif; ?></p>
      <div class="mb-grid">
        <div class="mb-field">
          <label>Name</label>
          <input type="text" name="name" value="<?php echo htmlspecialchars($row['name']); ?>" placeholder="Hopper King">
          <p class="hint">Saved as <code><?php echo $slug !== '' ? htmlspecialchars($slug) : '&lt;name&gt;'; ?>.png</code></p>
        </div>
        <div class="mb-field">
          <label>Theme</label>
          <select name="theme">
            <option value="">&mdash; pick &mdash;</option>
            <?php foreach ($themes as $ts => $tl): ?>
              <option value="<?php echo htmlspecialchars($ts); ?>"<?php echo $row['theme'] === $ts ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($tl); ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint">The arena this boss is fought in. A theme has to exist first.</p>
        </div>
        <div class="mb-field">
          <label>Project</label>
          <select name="project_id" id="mb-proj">
            <option value="0">&mdash; pick &mdash;</option>
            <?php foreach ($projects as $pid => $p): ?>
              <option value="<?php echo (int)$pid; ?>"<?php echo (int)$row['project_id'] === (int)$pid ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($p['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="mb-field">
          <label>Collection</label>
          <select name="collection_id" id="mb-coll">
            <option value="0" data-project="">&mdash; pick &mdash;</option>
            <?php foreach ($collections as $c): ?>
              <option value="<?php echo (int)$c['id']; ?>" data-project="<?php echo (int)$c['project_id']; ?>"
                <?php echo (int)$row['collection_id'] === (int)$c['id'] ? ' selected' : ''; ?>>
                <?php echo htmlspecialchars($c['name']); ?></option>
            <?php endforeach; ?>
          </select>
          <p class="hint"><b>Both are required.</b> The game inner-joins them, so a boss missing
             either does not error &mdash; it just never appears.</p>
        </div>
      </div>
    </div>

    <div class="mb-sect">
      <h3>Fight</h3>
      <div class="mb-grid">
        <div class="mb-field"><label>Max health</label>
          <input type="number" name="max_health" min="1" max="100000" value="<?php echo (int)$row['max_health']; ?>"></div>
        <div class="mb-field"><label>Strength (1-10)</label>
          <input type="number" name="strength" min="1" max="10" value="<?php echo (int)$row['strength']; ?>"></div>
        <div class="mb-field"><label>Speed (1-10)</label>
          <input type="number" name="speed" min="1" max="10" value="<?php echo (int)$row['speed']; ?>"></div>
        <div class="mb-field"><label>Tactics (1-10)</label>
          <input type="number" name="tactics" min="1" max="10" value="<?php echo (int)$row['tactics']; ?>"></div>
        <div class="mb-field"><label>Size</label>
          <select name="size"><?php echo monstrocity_select_options($row['size'], monstrocity_sizes()); ?></select></div>
        <div class="mb-field"><label>Powerup</label>
          <select name="powerup"><?php echo monstrocity_select_options($row['powerup'], monstrocity_powerups()); ?></select>
          <p class="hint">Only these four are implemented.</p></div>
      </div>
    </div>

    <div class="mb-sect">
      <h3>Art</h3>
      <p class="lead">Resized to <?php echo (int)MONSTROCITY_ART_WIDTH; ?> wide with its shape kept,
         and the red variant is generated from it &mdash; the same treatment the character art gets.</p>
      <div class="mb-grid">
        <div class="mb-field">
          <label>Boss image</label>
          <input type="file" name="art" accept="image/*">
          <label class="mb-check"><input type="checkbox" name="flip" value="1"> Flip it horizontally</label>
          <p class="hint">Uploading replaces the red variant too, so the pair can never drift
             apart. Limits: <b><?php echo htmlspecialchars((string)ini_get('upload_max_filesize')); ?></b>
             per file, and anything over about 1500&times;1500 is refused because the server cannot
             decode it inside its memory limit &mdash; it is resized to
             <?php echo (int)MONSTROCITY_ART_WIDTH; ?>px wide regardless, so there is nothing to gain
             from a larger export.</p>
        </div>
        <div class="mb-field">
          <label>Faces</label>
          <select name="orientation"><?php echo monstrocity_select_options($row['orientation'], array('Left','Right')); ?></select>
          <label style="margin-top:10px">File type</label>
          <select name="extension"><?php foreach (monstrocity_extensions() as $e): ?>
            <?php /* value= IS NOT OPTIONAL HERE. Without it a browser submits the option's
                     TEXT, and this one displays ".png" with a leading dot, so the form posted
                     ".png" and validation rejected an extension the user had plainly chosen.
                     The selects below happened to work only because their text equals their
                     value; they are explicit now so the next decorated label cannot repeat it. */ ?>
            <option value="<?php echo $e; ?>"<?php echo $row['extension'] === $e ? ' selected' : ''; ?>>.<?php echo $e; ?></option>
          <?php endforeach; ?></select>
        </div>
        <?php if ($slug !== ''): $a = mb_art($slug, $row['extension'], true); if ($a['base']): ?>
        <div class="mb-field">
          <label>On disk now &mdash; <?php echo htmlspecialchars($a['dim']); ?></label>
          <div class="mb-prev">
            <figure><img alt="" src="<?php echo htmlspecialchars(monstrocity_boss_dir() . '/' . $slug . '.' . $row['extension']); ?>?v=<?php echo time(); ?>"><figcaption>base</figcaption></figure>
            <?php if ($a['damaged']): ?>
            <figure><img alt="" src="<?php echo htmlspecialchars(monstrocity_boss_dir() . '/battle-damaged/' . $slug . '.' . $row['extension']); ?>?v=<?php echo time(); ?>">
              <figcaption><?php echo $a['score'] >= 0
                ? ($a['score'] < MONSTROCITY_DAMAGE_WEAK ? '<span class="mb-warn">weak ' . $a['score'] . '</span>' : $a['score'])
                : 'red'; ?></figcaption></figure>
            <?php endif; ?>
          </div>
        </div>
        <?php endif; endif; ?>
      </div>
    </div>

    <div class="mb-actions">
      <button class="adm-btn" type="submit" name="after" value=""><?php echo $row['id'] ? 'Save boss' : 'Create boss'; ?></button>
      <button class="adm-btn" type="submit" name="after" value="copy"
              title="Save, then start the next boss from this one's settings">Save &amp; copy &rarr;</button>
      <button class="adm-btn" type="submit" name="after" value="new"
              title="Save, then start a blank boss">Save &amp; new</button>
      <?php if ($row['theme'] !== ''): ?>
        <a class="adm-btn" href="admin-monstrocity-boss.php?tune=<?php echo urlencode($row['theme']); ?>">Tune this ladder</a>
      <?php endif; ?>
      <a class="adm-btn" href="admin-monstrocity-boss.php">Cancel</a>
    </div>
  </form>

<script>
(function () {
  /* The collection list is every collection on the platform, so narrow it to
     the project once one is picked. A collection already chosen is never
     hidden -- you cannot confirm what you cannot see, and this pair being
     wrong is what makes a boss vanish from the game. */
  var proj = document.getElementById('mb-proj'), coll = document.getElementById('mb-coll');
  if (!proj || !coll) return;
  var all = Array.prototype.slice.call(coll.options);
  function filter() {
    var pid = proj.value;
    coll.innerHTML = '';
    all.forEach(function (o) {
      var keep = !o.value || !pid || pid === '0'
              || o.getAttribute('data-project') === pid || o.selected;
      if (keep) coll.appendChild(o);
    });
  }
  proj.addEventListener('change', filter);
  filter();
})();
</script>

<?php endif; ?>
<?php include 'admin-css.php'; ?>
