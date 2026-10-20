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
/*
 * BUFFER BEFORE ANYTHING PRINTS, when this is an in-place upload.
 *
 * header.php emits the entire page head and nav as soon as it is included,
 * so by the time the upload handler runs the response is already most of a
 * web page. An in-place upload has to answer with JSON and nothing else,
 * which means capturing all of that and throwing it away -- and the same
 * buffer catches any warning printed on the way, which matters because
 * display_errors is on platform-wide and one notice would make the
 * response unparseable. That reads as "uploads stopped working" rather
 * than as the notice it is.
 */
$dsa_inplace = ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['ajax']));
if ($dsa_inplace) ob_start();

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
	echo '<script type="text/javascript" src="' . dropshipAsset('dropship.js') . '"></script></html>';
}

if (!dropshipThemeAdmin()) {
	/* A refused in-place upload must still answer in the shape the page is
	   waiting for, or it silently does nothing and the card never updates. */
	if ($dsa_inplace) {
		while (ob_get_level()) ob_end_clean();
		header('Content-Type: application/json');
		echo json_encode(array('ok' => false, 'note' => 'Not yours to edit.'));
		if (is_object($conn)) $conn->close();
		exit;
	}
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
$theme_ext = $theme && !empty($theme['extension']) ? strtolower($theme['extension']) : 'gif';
/*
 * WHICH SET OF ART THIS PAGE IS MANAGING.
 *
 * A theme can ship two: the one its extension names, and a gif set the
 * player can switch on when it exists. Both are real art that somebody
 * has to upload, and this page could only ever see the first -- so the
 * second had to be FTPed by hand, which is the gap the whole panel exists
 * to close.
 *
 * NOT THE SAME SWITCH AS THE PLAYER'S. Theirs chooses what the game
 * renders and is gated on the files existing; this one chooses what the
 * ADMIN is looking at, and has to work when the set is EMPTY -- otherwise
 * there is no way to create it in the first place.
 *
 * Validated against the two real answers, never taken off the query
 * string: $ext reaches the filesystem through the manifest.
 */
$ext = $theme_ext;
if (isset($_GET['format']) && $_GET['format'] === 'gif') $ext = 'gif';
if (isset($_POST['format']) && $_POST['format'] === 'gif') $ext = 'gif';
/* A gif theme has nothing to switch to; the two sets would be one. */
$can_switch = ($theme_ext !== 'gif');
if (!$can_switch) $ext = $theme_ext;
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
	/* The page background is its own slot: not under images/, one per
	   theme, and scaled to the viewport rather than to a pane. */
	$bg_slot = dropshipArtBackgroundSlot($slug);
	$is_bg   = ($bg_slot && $slot === $bg_slot['rel']);
	$valid = $is_bg || in_array($slot, $manifest, true) || in_array($slot, $icon_names, true);
	$is_icon = in_array($slot, $icon_names, true);

	if (!$valid) {
		$msg['err'][] = 'That is not a slot this theme has.';
	} elseif ($_FILES['art']['error'] !== UPLOAD_ERR_OK) {
		$msg['err'][] = 'Upload failed (code ' . (int)$_FILES['art']['error'] . ').';
	} else {
		$slot_ext = strtolower(pathinfo($slot, PATHINFO_EXTENSION));
		if ($is_bg) {
			/* Keeps whatever extension was uploaded and clears the others,
			   because the lookup globs and would otherwise pick between a
			   .jpg and a .png by alphabet. */
			$up_ext = strtolower(pathinfo((string)$_FILES['art']['name'], PATHINFO_EXTENSION));
			$why = dropshipArtStoreBackground($_FILES['art']['tmp_name'], $slug, $up_ext);
			if ($why === '') $msg['ok'][] = 'Background replaced.';
			else             $msg['err'][] = 'Background &mdash; ' . htmlspecialchars($why);
		} else {
		/* An icon is shared, so it is written beside the page rather than
		   inside the theme folder. */
		$dest = ($is_icon ? rtrim(__DIR__, '/') . '/' : $base) . $slot;
		list($ok, $why) = dropshipArtStore($_FILES['art']['tmp_name'], $dest, $slot_ext);
		if ($ok) $msg['ok'][] = $slot . ' &mdash; ' . $why;
		else     $msg['err'][] = $slot . ' &mdash; ' . $why;
		}
	}
}

/*
 * ANSWER IN PLACE WHEN THE PAGE ASKS IN PLACE.
 *
 * Replacing art is a dozen small edits in a row, and a full reload for each
 * one threw away the scroll position and flashed a loading bar -- so after
 * every upload you had to find your way back to the thing you were working
 * on. The page posts the same form over fetch() and patches the one card.
 *
 * NOTHING ABOUT THE UPLOAD ITSELF CHANGES: same handler above, same
 * validation, same slot-from-the-manifest rule. This only decides how the
 * result is phrased. Without JavaScript the forms post normally and the
 * page reloads exactly as it did.
 *
 * BUFFERED, because display_errors is on platform-wide and a single warning
 * printed ahead of this would make the response unparseable -- which would
 * read as "uploads stopped working" rather than as the warning it is.
 */
if ($dsa_inplace) {
	$rel  = (string)($_POST['slot'] ?? '');
	$aico = strpos($rel, 'icons/') === 0;
	$aweb = '/staking/dropship/' . ($slug === 'drop-ship' || $slug === '' ? '' : $slug . '/');
	$abs  = ($aico ? rtrim(__DIR__, '/') . '/' : (string)$base) . $rel;
	$here = $rel !== '' && is_file($abs);
	$dims = '';
	if ($here) {
		$i = @getimagesize($abs);
		$dims = ($i ? $i[0] . '×' . $i[1] : '?') . ' · ' . round(@filesize($abs) / 1024) . 'KB';
	}
	$body = json_encode(array(
		'ok'    => empty($msg['err']),
		'note'  => strip_tags(implode(' ', $msg['err'] ? $msg['err'] : $msg['ok'])),
		'rel'   => $rel,
		'src'   => ($aico ? '/staking/dropship/' : $aweb) . $rel . '?v=' . ($here ? @filemtime($abs) : 0),
		'here'  => $here,
		'dims'  => $dims,
	));
	/* Everything header.php printed goes in the bin; JSON only. And NOT
	   dsaArtClose(), which would append the page footer to it. */
	while (ob_get_level()) ob_end_clean();
	header('Content-Type: application/json');
	echo $body;
	if (is_object($conn)) $conn->close();
	exit;
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
/* ONE OBJECT, ONE BORDERED ROW. The frames and the icon of a thing are kept
   inside it so a reskin reads as "here is our rocket launcher", not as a
   grid of 30 unrelated pictures. */
.dsart .thing{border:1px solid rgba(0,200,160,.22);border-radius:6px;padding:10px;
  margin:0 0 10px;background:rgba(0,200,160,.03)}
.dsart .thing.bare{border:0;padding:0;background:none}
.dsart .thing-head{display:flex;align-items:baseline;flex-wrap:wrap;gap:7px;
  margin:0 0 8px;font-size:13px;text-align:left}
.dsart .thing-head b{color:#e8eaed}
.dsart .thing-head .arrow{color:#7a9eb0}
.dsart .thing-head .said{color:#00c8a0;font-weight:bold}
.dsart .thing-head .same{color:#7a9eb0;font-size:11px;font-style:italic}
.dsart .thing-head .count{margin-left:auto;color:#7a9eb0;font-size:11px}
/* A COLUMN, so the upload control sits on the floor of every card. The
   cards carry different numbers of lines -- a mapping, a warning, a rename
   -- and with the default stretch the buttons landed at a different height
   in every one, which is what made a row of them look broken. */
.dsart .slot{border:1px solid rgba(255,255,255,.12);border-radius:4px;padding:8px;
  background:rgba(0,0,0,.25);display:flex;flex-direction:column}
.dsart .slot form{margin-top:auto}
.dsart .slot .ren{margin-top:6px}
.dsart .slot.gap{border-color:rgba(224,70,107,.45)}
/* AMBER, NOT RED. A borrowed slot is not broken -- the game renders it and
   nothing 404s -- it is just the wrong game's picture, which is a different
   job from art that was never made. */
.dsart .slot.borrowed{border-color:rgba(232,192,112,.5)}
.dsart .slot .borrow{display:block;margin-top:4px;font-size:11px;color:#e8c070;line-height:1.35}
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
/* DROP TARGET. The whole card lights up, not just the file input, because
   the card is what somebody aims at -- and the outline is drawn inside the
   border so the grid does not shift by a pixel as it appears. */
/* WHICH SET OF ART IS BEING MANAGED. Two sets exist for a still theme --
   its own, and the gif set the player's Animation switch turns on -- and
   this page could only ever see the first. */
.dsart .formats{margin:0 0 12px;display:flex;align-items:center;flex-wrap:wrap;gap:8px}
.dsart .fmt{display:inline-flex;align-items:center;gap:6px;text-decoration:none;
  border:1px solid rgba(255,255,255,.18);border-radius:4px;padding:5px 10px;
  font-size:13px;color:#7a9eb0;background:rgba(0,0,0,.25)}
.dsart .fmt.on{border-color:#00c8a0;color:#07111d;background:#00c8a0;font-weight:bold}
.dsart .fmt .n{font-size:11px;opacity:.85;font-variant-numeric:tabular-nums}
.dsart .fmt.on .tag{color:#07111d;border-color:rgba(7,17,29,.4)}
.dsart .formats .why{font-size:11.5px;color:#7a9eb0;flex:1 1 260px;min-width:0;text-align:left}
.dsart .slot.over{border-color:#00c8a0;background:rgba(0,200,160,.12);
  outline:2px dashed rgba(0,200,160,.55);outline-offset:-4px}
.dsart .slot.over *{pointer-events:none}
.dsart .inplace{display:block;font-size:10.5px;margin-top:4px;text-align:left}
.dsart .inplace.good{color:#00c8a0}
.dsart .inplace.bad{color:#e8a0b0}
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
	/*
	 * A PRESENT FILE IS NOT A THEMED ONE. Every reskin's folder was seeded
	 * by copying Drop Ship's, so a slot read as done whether it held this
	 * theme's picture or the original game's -- and the first anybody knew
	 * was a Dread City death posting Drop Ship's animated gif to Discord.
	 * Counted separately from missing, because they need different work:
	 * one is art nobody has made, the other is art nobody has replaced.
	 */
	$borrowed = 0;
	foreach ($manifest as $rel) {
		if (!is_file($base . $rel)) { $missing++; continue; }
		if (dropshipArtIsDropShipCopy($base . $rel, $rel)) $borrowed++;
	}
?>
	<p class="bar"><b><?php echo count($manifest) - $missing - $borrowed; ?></b> of
	<b><?php echo count($manifest); ?></b> files are this theme's own
	<?php if ($missing): ?>&middot; <span style="color:#e8a0b0"><?php echo $missing; ?> missing</span><?php endif; ?>
	<?php if ($borrowed): ?>&middot; <span style="color:#e8c070"><?php echo $borrowed; ?> still Drop Ship's</span><?php endif; ?>
	&middot; media extension <code>.<?php echo $h($ext); ?></code></p>

	<?php if ($can_switch):
		/* How much of the OTHER set is there, so the switch says what it
		   leads to rather than making somebody click to find out. */
		$other = ($ext === 'gif') ? $theme_ext : 'gif';
		$o_man = dropshipArtManifest($other);
		$o_have = 0;
		foreach ($o_man as $r) if (is_file($base . $r)) $o_have++;
	?>
	<p class="formats">
		<?php foreach (array($theme_ext, 'gif') as $f):
			$on = ($f === $ext);
			$n = ($f === $ext) ? count($manifest) - $missing : $o_have;
			$t = ($f === $ext) ? count($manifest) : count($o_man); ?>
			<a class="fmt<?php echo $on ? ' on' : ''; ?>"
			   href="admin-art.php?project_id=<?php echo $pid; ?>&amp;format=<?php echo $h($f); ?>">
				.<?php echo $h($f); ?>
				<?php if ($f === $theme_ext): ?><span class="tag">default</span><?php endif; ?>
				<span class="n"><?php echo $n; ?>/<?php echo $t; ?></span>
			</a>
		<?php endforeach; ?>
		<span class="why">Two sets of art, both managed here. The
		<b>.<?php echo $h($theme_ext); ?></b> set is what this theme renders;
		the <b>.gif</b> set is what the Animation switch turns on for players,
		and it only appears for them once the first and last rounds are both
		there.</span>
	</p>
	<?php endif; ?>

	<p class="note"><b>Drag an image onto any slot to replace it</b> &mdash; or pick
	a file, which uploads straight away. Nothing reloads, so you keep your place.<br>
	Uploads are scaled to fit <b><?php echo dropshipArtMaxWidth(); ?>&times;<?php
	echo dropshipArtMaxHeight(); ?></b>. That is the pane at <b>898px</b> times the
	<b>1.2&times;</b> the Ken Burns pan zooms to, so art stays sharp at the end of the
	drift rather than only at the start. Anything already smaller is left alone and
	nothing is enlarged, so an existing 1024px file is kept as it is &mdash; it ends
	up about 5% soft at full zoom, which is not worth re-cutting for.
	An animated gif is only resized where Imagick is available, because the
	alternative flattens it to a single frame.</p>

	<?php
	/*
	 * ONE ROW PER THING. images/weapons/rocket-launcher3.gif and
	 * icons/rocket-launcher.png are two pictures of one object, and they
	 * used to sit on opposite halves of this page purely because they live
	 * in different folders. Flowed into a single grid they were together
	 * but still split across whatever the column count happened to be,
	 * with nothing saying the six belonged to each other. Each thing is its
	 * own bordered row now, headed by its name and what this theme renames
	 * it to, because a reskin is decided one object at a time.
	 */
	$icon_files = dropshipArtIconFiles();
	foreach (dropshipArtPlan($conn, $pid, $ext, $slug) as $group => $g): ?>
	<h4><?php echo $h($group); ?></h4>
	<p class="note"><?php echo $h($g['note']); ?></p>
	<?php foreach ($g['rows'] as $row): ?>
	<div class="thing<?php echo $row['label'] === '' ? ' bare' : ''; ?>">
	<?php if ($row['label'] !== ''): ?>
		<?php /* The name and what this theme calls it, on the row rather than
		         on every card in it -- the whole row is one object. */ ?>
		<div class="thing-head">
			<b><?php echo $h($row['label']); ?></b>
			<?php if ($row['said'] !== ''): ?>
				<span class="arrow">&rarr;</span>
				<span class="said"><?php echo $h($row['said']); ?></span>
			<?php else: ?>
				<span class="same">not renamed by this theme</span>
			<?php endif; ?>
			<span class="count"><?php echo count($row['slots']); ?></span>
		</div>
	<?php endif; ?>
	<div class="slots">
	<?php foreach ($row['slots'] as $slot):
		$is_icon = ($slot['kind'] === 'icon');
		$is_bgs  = ($slot['kind'] === 'bg');
		$rel     = $slot['rel'];
		/* Icons are ONE SHARED FOLDER for every theme -- the page asks for
		   icons/<name>.png and all four projects are served from the same
		   place -- so they are addressed from here, not from the theme's
		   own folder. */
		/* A background and an icon both sit beside the page rather than
		   inside the theme's images/ folder. */
		$beside = ($is_icon || $is_bgs);
		$abs  = $beside ? rtrim(__DIR__, '/') . '/' . $rel : $base . $rel;
		$src  = $beside ? '/staking/dropship/' . $rel : $web . $rel;
		$here = is_file($abs);
		$dims = '';
		/* Present, but whose? Only round art can be a copy -- an icon is one
		   shared folder by design and a background is named per theme. */
		$borrowed_slot = ($here && !$beside && dropshipArtIsDropShipCopy($abs, $rel));
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
		<div class="slot <?php echo $here ? '' : 'gap'; ?><?php echo $is_icon ? ' is-icon' : ''; ?><?php echo $borrowed_slot ? ' borrowed' : ''; ?>">
			<div class="shot">
			<?php if ($here): ?>
				<img src="<?php echo $h($src); ?>?v=<?php echo @filemtime($abs); ?>" alt="" loading="lazy">
			<?php else: ?><span class="none">missing</span><?php endif; ?>
			</div>
		<?php if ($is_bgs): ?>
			<span class="name">Background</span>
		<?php elseif ($is_icon): ?>
			<?php /* The thing's name is on the row header; the card only has
			         to say which of the row's pictures this one is. */ ?>
			<span class="name">Icon</span>
		<?php else: ?>
			<span class="name"><?php echo $h(dropshipArtTitle($rel)); ?></span>
			<?php /* An unnamed band has no row header, so the mapping belongs
			         on the card there and nowhere else. */
			if ($row['label'] === ''):
				foreach (dropshipArtSlotMapping($conn, $pid, $rel) as $mp): ?>
				<span class="maps"><?php echo $h($mp['from']); ?> &rarr; <b><?php
					echo $h($mp['to']); ?></b></span>
			<?php endforeach; endif; ?>
		<?php endif; ?>
			<span class="path"><?php echo $h($rel); ?></span>
		<?php if ($borrowed_slot): ?>
			<span class="borrow">This is Drop Ship's file, not this theme's. Players see
			the original game's picture here.</span>
		<?php endif; ?>
		<?php if ($is_icon && !$slot['safe']): ?>
			<span class="warn">That word cannot be a filename, so the page will ask
			for something it can never find. Change the wording.</span>
		<?php else: ?>
			<span class="dims"><?php echo $here ? $dims
				: ($is_icon ? 'the page will ask for this' : 'the run will ask for this'); ?></span>
			<form method="post" enctype="multipart/form-data" action="admin-art.php?project_id=<?php echo $pid; ?>&amp;format=<?php echo $h($ext); ?>">
				<input type="hidden" name="project_id" value="<?php echo $pid; ?>">
				<input type="hidden" name="format" value="<?php echo $h($ext); ?>">
				<input type="hidden" name="slot" value="<?php echo $h($rel); ?>">
				<input type="file" name="art" accept="<?php echo $is_icon ? 'image/png' : 'image/*'; ?>" required>
				<?php if ($is_bgs): ?><span class="dims">scaled to fit <?php
					echo dropshipArtMaxBackgroundWidth(); ?>px wide</span><?php endif; ?>
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
			<form method="post" action="admin-art.php?project_id=<?php echo $pid; ?>&amp;format=<?php echo $h($ext); ?>" class="ren">
				<input type="hidden" name="project_id" value="<?php echo $pid; ?>">
				<input type="hidden" name="format" value="<?php echo $h($ext); ?>">
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
	</div>
	<?php endforeach; ?>
	<?php endforeach; ?>
<?php endif; ?>

<script>
/*
 * UPLOAD IN PLACE. Replacing art is a dozen small edits in a row, and a
 * full reload for each one threw away the scroll position and flashed a
 * loading bar, so after every upload you had to find your way back to the
 * thing you were working on.
 *
 * PROGRESSIVE: the forms are ordinary POSTs and still work with this
 * script absent or broken. All this does is intercept the submit, send the
 * same fields with an extra ajax flag, and patch the one card.
 */
(function () {
	var root = document.querySelector('.dsart');
	if (!root || !window.FormData || !window.fetch) return;

	function card(el) { while (el && !el.classList.contains('slot')) el = el.parentElement; return el; }

	function say(slot, text, good) {
		var n = slot.querySelector('.inplace');
		if (!n) {
			n = document.createElement('span');
			n.className = 'inplace';
			slot.appendChild(n);
		}
		n.textContent = text;
		n.className = 'inplace ' + (good ? 'good' : 'bad');
	}

	/*
	 * ONE SEND for the button, the file picker and a dropped file alike.
	 * `dropped` overrides whatever the form's own input holds, so a drop
	 * does not have to fake its way into that input first -- which is the
	 * part that needs DataTransfer and does not exist everywhere.
	 */
	function send(form, dropped) {
		var slot = card(form);
		if (!slot) return;
		var file = form.querySelector('input[type=file]');
		if (!dropped && file && !file.value) return;

		var data = new FormData(form);
		if (dropped) data.set('art', dropped);
		data.append('ajax', '1');
		var btn = form.querySelector('button');
		var was = btn ? btn.textContent : '';
		if (btn) { btn.disabled = true; btn.textContent = 'Working'; }
		say(slot, 'uploading', true);

		fetch(form.action, { method: 'POST', body: data, credentials: 'same-origin' })
			.then(function (r) { return r.json(); })
			.then(function (d) {
				if (btn) { btn.disabled = false; btn.textContent = d.ok ? 'Replace' : was; }
				say(slot, d.note || (d.ok ? 'saved' : 'failed'), !!d.ok);
				if (!d.ok) return;
				/* Swap the picture without touching anything around it. The
				   src carries the new mtime, so the browser cannot serve the
				   old file from cache. */
				var shot = slot.querySelector('.shot');
				var img  = shot ? shot.querySelector('img') : null;
				if (d.here && shot) {
					if (!img) {
						shot.innerHTML = '';
						img = document.createElement('img');
						img.loading = 'lazy';
						shot.appendChild(img);
					}
					img.src = d.src;
					slot.classList.remove('gap');
				}
				var dims = slot.querySelector('.dims');
				if (dims && d.dims) dims.textContent = d.dims;
				if (file) file.value = '';
				/* A rename makes the file exist under the new name, so the
				   "or rename one already on the server" control is spent. */
				var ren = slot.querySelector('.ren');
				if (ren && d.here) ren.remove();
			})
			.catch(function () {
				if (btn) { btn.disabled = false; btn.textContent = was; }
				say(slot, 'could not reach the server; try again', false);
			});
	}

	root.addEventListener('submit', function (ev) {
		if (!card(ev.target)) return;           /* not a per-card form */
		ev.preventDefault();
		send(ev.target, null);
	});

	/*
	 * PICKING A FILE UPLOADS IT. The Upload button is still there and still
	 * works, but choosing a file and then pressing a second button is two
	 * actions for one decision, seventy times over.
	 */
	root.addEventListener('change', function (ev) {
		var input = ev.target;
		if (!input || input.type !== 'file' || !input.value) return;
		var form = input.form;
		if (form && card(form)) send(form, null);
	});

	/*
	 * DRAG A FILE ONTO A SLOT AND IT REPLACES IT.
	 *
	 * Delegated, so it covers every card including ones that arrive in a
	 * later render, and it posts through the same send() as the button --
	 * same validation, same slot-from-the-manifest rule, same in-place
	 * patch of the card.
	 */
	function dropTarget(el) {
		var slot = card(el);
		if (!slot) return null;
		/* A card with no upload form is one the page refuses to write to --
		   an icon whose name cannot be a filename. Do not pretend. */
		return slot.querySelector('input[type=file]') ? slot : null;
	}

	root.addEventListener('dragover', function (ev) {
		var slot = dropTarget(ev.target);
		if (!slot) return;
		ev.preventDefault();                    /* required, or drop never fires */
		ev.dataTransfer.dropEffect = 'copy';
		slot.classList.add('over');
	});
	root.addEventListener('dragleave', function (ev) {
		var slot = dropTarget(ev.target);
		if (slot && !slot.contains(ev.relatedTarget)) slot.classList.remove('over');
	});
	root.addEventListener('drop', function (ev) {
		var slot = dropTarget(ev.target);
		if (!slot) return;
		ev.preventDefault();
		slot.classList.remove('over');
		var files = ev.dataTransfer && ev.dataTransfer.files;
		if (!files || !files.length) return;
		if (files.length > 1) {
			/* One slot is one picture. Saying so beats silently taking the
			   first and leaving somebody to wonder where the rest went. */
			say(slot, 'one file per slot, please', false);
			return;
		}
		if (files[0].type.indexOf('image/') !== 0) {
			say(slot, 'that is not an image', false);
			return;
		}
		send(slot.querySelector('form'), files[0]);
	});

	/*
	 * ...AND NOWHERE ELSE. A file dropped on any other part of the page
	 * would otherwise be OPENED by the browser, throwing away the page and
	 * whatever was half-filled on it.
	 */
	window.addEventListener('dragover', function (ev) {
		if (!dropTarget(ev.target)) ev.preventDefault();
	});
	window.addEventListener('drop', function (ev) {
		if (!dropTarget(ev.target)) ev.preventDefault();
	});
}());
</script>
<?php dsaArtClose($conn); ?>
