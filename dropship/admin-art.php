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
	echo '<div class="row"><div class="main"><div class="content"><h2>Theme Art</h2>'
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

/*
 * WHICH SLOTS THIS THEME HAS, and where each one lives on disk.
 *
 * Lifted out of the upload handler because the copy below has to answer
 * exactly the same question about TWO paths, and a second copy of the
 * rule is how a validated destination ends up beside an unvalidated
 * source. Returns null for anything that is not a slot of this theme.
 */
$dsaSlot = function ($rel) use ($conn, $pid, $ext, $slug, $base) {
	$rel = (string)$rel;
	/* No theme means no folder to resolve into. $base is null then, and
	   concatenating it yields a RELATIVE path -- is_file() against the
	   working directory, which is nobody's art. */
	if ($rel === '' || $base === null) return null;
	$icon_names = array();
	foreach (dropshipThemeIconSlots($conn, $pid) as $ic) if ($ic['safe']) $icon_names[] = $ic['rel'];
	$bg_slot = dropshipArtBackgroundSlot($slug);
	$is_bg   = ($bg_slot && $rel === $bg_slot['rel']);
	$is_icon = in_array($rel, $icon_names, true);
	if (!$is_bg && !$is_icon && !in_array($rel, dropshipArtManifest($ext), true)) return null;
	/* An icon is one shared folder for every theme; a background sits
	   beside the theme's images/ rather than inside it. Both are addressed
	   from the dropship directory, round art from the theme's own. */
	$beside = ($is_icon || $is_bg);
	return array(
		'rel'  => $rel,
		'icon' => $is_icon,
		'bg'   => $is_bg,
		'abs'  => ($beside ? rtrim(__DIR__, '/') . '/' : $base) . $rel,
	);
};

/* ------------------------------------------------------------------ copy - */
/*
 * DRAG A PICTURE FROM ONE SLOT ONTO ANOTHER AND IT LANDS THERE TOO.
 *
 * Several slots are the same picture by design -- the two Super Soldier
 * icons, a weapon whose frames barely move, a theme reusing one death
 * across all three -- and the only way to say so was to find the file on
 * your own machine again and upload it a second time. Everything the
 * upload path does still happens: the destination still comes out of the
 * manifest, and dropshipArtStore() still converts the format and scales
 * to the pane, so dragging a .jpg slot onto a .gif one writes a gif.
 *
 * THE SOURCE IS VALIDATED EXACTLY LIKE THE DESTINATION. It arrives off
 * the form and becomes a filesystem path to READ, so an unchecked one is
 * a file disclosure -- the page would happily copy ../../credentials into
 * a slot and then serve it. Same $dsaSlot(), same answer.
 */
if ($theme && $_SERVER['REQUEST_METHOD'] === 'POST'
    && !isset($_FILES['art']) && isset($_POST['copy_from'])) {
	$dest_s = $dsaSlot($_POST['slot'] ?? '');
	$src_s  = $dsaSlot($_POST['copy_from']);
	if (!$dest_s || !$src_s) {
		$msg['err'][] = 'That is not a slot this theme has.';
	} elseif ($src_s['rel'] === $dest_s['rel']) {
		$msg['err'][] = 'That is the same slot.';
	} elseif (!is_file($src_s['abs'])) {
		$msg['err'][] = 'There is nothing in the slot you dragged from.';
	} else {
		/*
		 * COPIED TO A SCRATCH FILE FIRST. dropshipArtStore() MOVES its
		 * input when the file already fits and needs no conversion, which
		 * would empty the slot being dragged FROM -- one picture in two
		 * places was the whole request.
		 */
		$tmp = tempnam(sys_get_temp_dir(), 'dsart');
		if ($tmp === false || !@copy($src_s['abs'], $tmp)) {
			if ($tmp !== false) @unlink($tmp);
			$msg['err'][] = 'Could not read the slot you dragged from.';
		} else {
			if ($dest_s['bg']) {
				$why = dropshipArtStoreBackground($tmp, $slug,
					strtolower(pathinfo($src_s['rel'], PATHINFO_EXTENSION)));
				if ($why === '') $msg['ok'][] = 'Background replaced.';
				else             $msg['err'][] = 'Background &mdash; ' . htmlspecialchars($why);
			} else {
				list($ok, $why) = dropshipArtStore($tmp, $dest_s['abs'],
					strtolower(pathinfo($dest_s['rel'], PATHINFO_EXTENSION)));
				if ($ok) $msg['ok'][] = $dest_s['rel'] . ' &mdash; ' . $why;
				else     $msg['err'][] = $dest_s['rel'] . ' &mdash; ' . $why;
			}
			@unlink($tmp);
		}
	}
}

/* ----------------------------------------------------------------- audio - */
/*
 * THE SOUND A ROUND PLAYS, managed beside the picture it belongs to.
 *
 * The filename is the wording, exactly as it is for an icon: Oculus
 * Lounge calling the Melee a "Vibrator" IS the instruction to supply
 * sounds/vibrator.mp3. Nothing is stored; see dropship-audio.php.
 *
 * THE WARNING IS THE POINT OF THIS HANDLER. sounds/ is ONE FOLDER for
 * every theme, so writing melee.mp3 because Drop Ship's melee needs
 * replacing also replaces it for every other theme whose word slugs to
 * the same name, and for every event that falls back to it. The icon
 * rename can refuse, because a rename has somewhere to leave the file;
 * an upload has nowhere, so this refuses ONCE, says exactly who else is
 * listening to that file, and goes ahead when told again.
 */
$dsaAudioSlotFor = function ($term) use ($conn, $pid) {
	foreach (dropshipAudioEvents() as $e) {
		if ($e['term'] !== $term) continue;
		return dropshipAudioSlot($conn, $pid, $e);
	}
	if ($term === 'Opening') return dropshipAudioOpenSlot($conn, $pid);
	return null;
};
if ($theme && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['audio_term'])) {
	$a_slot = $dsaAudioSlotFor((string)$_POST['audio_term']);
	$a_dir  = rtrim(__DIR__, '/') . '/sounds/';
	if (!$a_slot || $a_slot['stem'] === '') {
		$msg['err'][] = 'That is not a sound this theme asks for.';
	} else {
		$a_dest = $a_dir . $a_slot['stem'] . '.mp3';
		/*
		 * WHO ELSE IS LISTENING. Asked before the write and shown once;
		 * a second post with confirm set goes ahead. Only counts OTHER
		 * claims, or replacing a theme's own sound would nag about
		 * itself.
		 */
		$a_claims = dropshipAudioClaims($conn, $a_slot['stem'], $pid, $a_slot['term']);
		$a_overwrite = is_file($a_dest);
		$a_go = (!$a_overwrite || !$a_claims || !empty($_POST['confirm']));

		/* USE ONE ALREADY ON THE SERVER. The same picker the icons have,
		   except an icon is RENAMED and a sound is COPIED -- the file it
		   came from is still being asked for under its own name. */
		$a_use = isset($_POST['audio_use']) ? basename((string)$_POST['audio_use']) : '';
		if ($a_use !== '' && !in_array($a_use, dropshipAudioFiles(), true)) {
			$msg['err'][] = 'That is not a sound on the server.';
		} elseif (!$a_go) {
			$msg['err'][] = 'That would also change ' . htmlspecialchars(implode(', ', $a_claims))
			              . '. Press again to go ahead.';
			$msg['warn'] = true;
		} elseif ($a_use !== '') {
			if ($a_dir . $a_use === $a_dest) {
				$msg['err'][] = 'That is already the file this asks for.';
			} elseif (!@copy($a_dir . $a_use, $a_dest)) {
				$msg['err'][] = 'Could not copy that sound.';
			} else {
				dropshipArtReadable($a_dest);
				$msg['ok'][] = $a_slot['stem'] . '.mp3 now plays ' . htmlspecialchars($a_use) . '.';
			}
		} elseif (isset($_FILES['audio']) && $_FILES['audio']['error'] === UPLOAD_ERR_OK) {
			/*
			 * MP3 ONLY, and checked by its BYTES. There is no ffmpeg
			 * here, so unlike a picture a sound cannot be converted into
			 * the format the slot needs -- the honest answer is to
			 * refuse rather than to store something the browser will not
			 * play. An ID3 tag or a bare frame header are both valid
			 * starts to an mp3.
			 */
			$a_head = (string)@file_get_contents($_FILES['audio']['tmp_name'], false, null, 0, 3);
			$a_ismp3 = (substr($a_head, 0, 3) === 'ID3')
			        || (strlen($a_head) >= 2 && (ord($a_head[0]) === 0xFF) && ((ord($a_head[1]) & 0xE0) === 0xE0));
			if (!$a_ismp3) {
				$msg['err'][] = 'That is not an mp3, and this server has no way to convert one.';
			} elseif (!@move_uploaded_file($_FILES['audio']['tmp_name'], $a_dest)
			          && !@rename($_FILES['audio']['tmp_name'], $a_dest)) {
				$msg['err'][] = 'Could not write ' . $a_slot['stem'] . '.mp3.';
			} else {
				dropshipArtReadable($a_dest);
				$msg['ok'][] = $a_slot['stem'] . '.mp3 replaced.';
			}
		} else {
			$msg['err'][] = 'Pick an mp3, or a sound already on the server.';
		}
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
	/*
	 * RESOLVED THE SAME WAY THE WRITE WAS. This worked out the path a
	 * second time and only knew about icons, so the BACKGROUND slot -- the
	 * one whose rel already starts with the theme folder -- came back as
	 * <slug>/<slug>/..., which exists nowhere: the file saved correctly and
	 * the card reported it missing. One resolver now answers both.
	 */
	$a_s  = $dsaSlot($rel);
	$aico = $a_s ? $a_s['icon'] : (strpos($rel, 'icons/') === 0);
	$abeside = $a_s ? ($a_s['icon'] || $a_s['bg']) : $aico;
	$aweb = '/staking/dropship/' . ($slug === 'drop-ship' || $slug === '' ? '' : $slug . '/');
	$abs  = $a_s ? $a_s['abs']
	             : (($aico ? rtrim(__DIR__, '/') . '/' : (string)$base) . $rel);
	$here = $rel !== '' && is_file($abs);
	$dims = '';
	if ($here) {
		$i = @getimagesize($abs);
		$dims = ($i ? $i[0] . '×' . $i[1] : '?') . ' · ' . round(@filesize($abs) / 1024) . 'KB';
	}
	$body = json_encode(array(
		'ok'    => empty($msg['err']),
		/* DECODED, because these messages are HTML for the non-JS path and
		   carry &mdash;. strip_tags() removes tags, not entities, so the
		   card was reporting "bunker/7.jpg &mdash; Stored at 1080x1080"
		   with the entity spelled out. */
		'note'  => html_entity_decode(
			strip_tags(implode(' ', $msg['err'] ? $msg['err'] : $msg['ok'])),
			ENT_QUOTES, 'UTF-8'),
		'rel'   => $rel,
		'src'   => ($abeside ? '/staking/dropship/' : $aweb) . $rel . '?v=' . ($here ? @filemtime($abs) : 0),
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
/* A picture on this page is a drag SOURCE as well as a target: drag it
   onto another slot and it lands there too. The cursor is the only thing
   that says so, so it is not optional. */
.dsart .slot .shot img{cursor:grab}
.dsart .slot .shot img:active{cursor:grabbing}
/* CLICK TO SEE IT PROPERLY. A 1080px picture shown at 210 tells you which
   image is in the slot and nothing about whether it is any good. */
.dsart .lightbox{position:fixed;inset:0;z-index:200;display:flex;align-items:center;
  justify-content:center;flex-direction:column;gap:10px;padding:24px;
  background:rgba(4,10,18,.93);cursor:zoom-out}
.dsart .lightbox img{max-width:min(96vw,1200px);max-height:80vh;width:auto;height:auto;
  /* The run paints every frame on the game's own dark pane, so a picture
     with transparency has to be judged against that and not against the
     overlay, which is darker. */
  background:#07111d;border:1px solid rgba(255,255,255,.14);border-radius:4px}
.dsart .lightbox .cap{color:#e8eaed;font-size:13px;text-align:center;line-height:1.5;
  max-width:min(96vw,1200px)}
.dsart .lightbox .cap b{color:#00c8a0}
.dsart .lightbox .cap code{color:#9fb3c8;font-size:12px}
.dsart .lightbox .hint{color:#6f8296;font-size:11px}
/* SOUND, beside the pictures of the same thing.
   A GRID, NOT A FLOW. The first pass let each row wrap on its own, so the
   file input, the picker and the button started at a different x on every
   line and the native <audio> widgets -- light grey, and a different width
   depending on whether the file had loaded -- shoved the lot around. Four
   fixed columns: what it is, what it asks for, play, change it. */
.dsart .snd{margin-top:10px;border:1px solid rgba(255,255,255,.10);border-radius:4px;
  background:rgba(255,255,255,.02);overflow:hidden}
.dsart .snd .ev{display:grid;align-items:center;gap:4px 14px;padding:9px 12px;
  grid-template-columns:minmax(150px,1fr) minmax(190px,1.3fr) 30px minmax(250px,1.1fr);
  grid-template-areas:'name file play act' 'warn warn warn warn';font-size:12px}
.dsart .snd .ev + .ev{border-top:1px solid rgba(255,255,255,.07)}
.dsart .snd .ev:nth-child(even){background:rgba(255,255,255,.015)}
.dsart .snd .nm{grid-area:name;color:#e8eaed;font-weight:600;line-height:1.35}
.dsart .snd .nm .said{display:block;color:#00c8a0;font-weight:400}
.dsart .snd .fl{grid-area:file;line-height:1.4;min-width:0}
.dsart .snd .fl code{color:#9fb3c8;font-size:11px}
/* SPACED FROM THE FILENAME. These two sit on one line to keep the row a
   single line high, which means the gap has to be put there on purpose --
   without it the page reads "sounds/dread-city.mp3silent". */
.dsart .snd .fl .st{font-size:11px;margin-left:9px;white-space:nowrap}
.dsart .snd .fl .ok2{color:#6f8296}
.dsart .snd .fl .fell{color:#e8c070}
.dsart .snd .fl .gapn{color:#e8a0b0}
.dsart .snd .fl .delay{color:#6f8296}
/* ONE SMALL DARK BUTTON instead of the browser's own player: the native
   control is light, 250px wide and resizes itself once the file loads. */
.dsart .snd .play{grid-area:play;width:28px;height:28px;padding:0;border-radius:50%;
  border:1px solid rgba(0,200,160,.5);background:rgba(0,200,160,.10);color:#00c8a0;
  font-size:11px;line-height:1;cursor:pointer}
.dsart .snd .play:hover{background:rgba(0,200,160,.22)}
.dsart .snd .play.on{background:#00c8a0;color:#06131f}
.dsart .snd .play[disabled]{opacity:.25;cursor:default;border-color:rgba(255,255,255,.18);
  background:none;color:#6f8296}
.dsart .snd form{grid-area:act;display:grid;grid-template-columns:1fr auto;gap:6px;
  align-items:center;margin:0}
.dsart .snd form .pick{display:grid;gap:4px;min-width:0}
.dsart .snd select,.dsart .snd input[type=file]{font-size:11px;width:100%;min-width:0}
.dsart .snd form button{padding:5px 12px;font-size:11px;white-space:nowrap}
/* A CHIP, NOT A LINE. Nearly every sound is shared with something --
   that is the normal state of one folder serving four themes -- so a
   full amber sentence per row doubled the height of the table and read
   as a page full of warnings. The detail is on the chip's tooltip, and
   the actual refusal still happens at the moment of writing, which is
   where a warning belongs. */
.dsart .snd .claim{grid-area:warn;display:none}
.dsart .snd .chip{display:inline-block;margin-left:6px;padding:0 5px;border-radius:8px;
  font-size:10px;line-height:15px;color:#e8c070;border:1px solid rgba(232,192,112,.4);
  cursor:help;vertical-align:1px}
@media (max-width:860px){
  .dsart .snd .ev{grid-template-columns:1fr 30px;
    grid-template-areas:'name play' 'file file' 'act act' 'warn warn'}
}
.dsart .inplace{display:block;font-size:10.5px;margin-top:4px;text-align:left}
.dsart .inplace.good{color:#00c8a0}
.dsart .inplace.bad{color:#e8a0b0}
</style>
<div class="row"><div class="main"><div class="content dsart">
<h2>Theme Art</h2>

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
	<b>Drag a picture already on this page onto another slot</b> and it lands there
	too, converted to that slot's format, without going back to your own machine
	for it. The slot you dragged from keeps its picture.<br>
	<b>Click a picture to see it full size.</b><br>
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
	/*
	 * SOUNDS, BUCKETED BY THE THING THEY BELONG TO, so each one renders
	 * under the pictures of the same object -- the rocket launcher's five
	 * frames, its icon and its four sounds in one row. Whatever belongs to
	 * no object gets its own section at the end, the way icons with no
	 * round art do.
	 */
	$snd_files = dropshipAudioFiles();
	/* THE PLAN ONCE, because the sounds have to be bucketed against the
	   rows that will actually render -- see dropshipAudioFamily()'s own
	   note on why the longest-matching family is the wrong answer. */
	$art_plan = dropshipArtPlan($conn, $pid, $ext, $slug);
	$row_labels = array();
	foreach ($art_plan as $g) foreach ($g['rows'] as $r) {
		if ($r['label'] !== '') $row_labels[] = $r['label'];
	}
	$snd_by_thing = array();
	$snd_loose = array();
	foreach (dropshipAudioSlots($conn, $pid) as $as) {
		$fam = dropshipAudioFamily($as['term'], $row_labels);
		if ($fam !== '' && in_array($fam, $row_labels, true)) $snd_by_thing[$fam][] = $as;
		else                                                  $snd_loose[] = $as;
	}
	$snd_loose[] = dropshipAudioOpenSlot($conn, $pid);

	/* One renderer for both places, or the row and the section drift. */
	$sndRow = function ($as) use ($h, $snd_files, $conn, $pid) {
		$claims = dropshipAudioClaims($conn, $as['stem'], $pid, $as['term']);
		/* WHAT IS ACTUALLY PLAYING, which is not always what it asks for:
		   a theme with no file of its own falls back to Drop Ship's, and
		   Drop Ship's own files are still on their old sound-named paths
		   until they are renamed onto the wording. */
		/* THE SAME RULE THE RUN USES, or the page says one thing and the
		   game does another. A renamed thing never falls back to Drop
		   Ship's sound; it is silent until it has one of its own. */
		$renamed = ($as['said'] !== $as['term']);
		$playing = $as['exists'] ? $as['rel']
		         : ($renamed ? ''
		         : ($as['base_has'] ? $as['base_rel']
		         : ($as['legacy_has'] ? 'sounds/' . $as['legacy'] . '.mp3' : '')));
		/* What it WOULD have fallen back to, offered as the one-click
		   answer: nine times out of ten the right file is already on the
		   server under its old name. */
		$suggest = ($playing === '' && $as['legacy_has']) ? $as['legacy'] . '.mp3' : '';
		?>
		<div class="ev">
			<span class="nm"><?php echo $h($as['term']); ?>
				<?php if ($as['said'] !== $as['term'] && $as['said'] !== ''): ?>
					<span class="said"><?php echo $h($as['said']); ?></span>
				<?php endif; ?>
			</span>
			<span class="fl">
				<code><?php echo $h($as['rel'] !== '' ? $as['rel'] : 'no usable filename'); ?></code>
				<?php if ($claims): ?><span class="chip" title="Also used by <?php
					echo $h(implode(', ', $claims)); ?>. Replacing this file changes it for them too."
					>shared &times;<?php echo count($claims); ?></span><?php endif; ?>
				<span class="st">
				<?php if ($as['exists']): ?>
					<span class="ok2">its own file<?php if ($as['delay']): ?>,
						after <?php echo (int)$as['delay']; ?>ms<?php endif; ?></span>
				<?php elseif ($playing !== ''): ?>
					<span class="fell">playing <?php echo $h(basename($playing)); ?></span><?php
					if ($as['delay']): ?><span class="delay">, after <?php
						echo (int)$as['delay']; ?>ms</span><?php endif; ?>
				<?php else: ?>
					<span class="gapn">silent<?php if ($suggest !== ''): ?> &middot; Drop Ship uses
						<?php echo $h($suggest); ?><?php endif; ?></span>
				<?php endif; ?>
				</span>
			</span>
			<?php /* One button, one shared player: see the script below. */ ?>
			<button type="button" class="play" aria-label="Play"
			        <?php echo $playing === '' ? 'disabled' : 'data-src="' . $h(dropshipAudioUrl($playing)) . '"'; ?>>&#9654;</button>
			<?php if ($as['stem'] !== ''): ?>
			<form method="post" enctype="multipart/form-data"
			      action="admin-art.php?project_id=<?php echo $pid; ?>&amp;format=<?php echo $h($ext); ?>">
				<input type="hidden" name="project_id" value="<?php echo $pid; ?>">
				<input type="hidden" name="format" value="<?php echo $h($ext); ?>">
				<input type="hidden" name="audio_term" value="<?php echo $h($as['term']); ?>">
				<?php /* A SECOND PRESS IS THE CONFIRMATION. The handler refuses
				         once and says who else is listening; this carries the
				         answer back. */ ?>
				<input type="hidden" name="confirm" value="<?php echo
					(isset($_POST['audio_term']) && $_POST['audio_term'] === $as['term']) ? '1' : ''; ?>">
				<span class="pick">
					<select name="audio_use">
						<option value="">use one already here&hellip;</option>
						<?php if ($suggest !== '' && in_array($suggest, $snd_files, true)): ?>
							<option value="<?php echo $h($suggest); ?>">&#9733; <?php
								echo $h($suggest); ?> &mdash; what Drop Ship plays</option>
						<?php endif; ?>
						<?php foreach ($snd_files as $f): if ($f === $suggest) continue; ?>
							<option value="<?php echo $h($f); ?>"><?php echo $h($f); ?></option>
						<?php endforeach; ?>
					</select>
					<input type="file" name="audio" accept="audio/mpeg,.mp3">
				</span>
				<button class="button" type="submit">Set</button>
			</form>
			<?php else: ?><span></span><?php endif; ?>

		</div>
	<?php };

	foreach ($art_plan as $group => $g): ?>
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
	<?php /* THE SOUNDS OF THIS THING, under its pictures. A reskin is
	         decided one object at a time and the answer is frames, an
	         icon and whatever it sounds like; those were on three
	         different parts of the screen and the sounds on none of
	         them. */
	if ($row['label'] !== '' && !empty($snd_by_thing[$row['label']])): ?>
	<div class="snd">
		<?php foreach ($snd_by_thing[$row['label']] as $as) $sndRow($as); ?>
	</div>
	<?php unset($snd_by_thing[$row['label']]); endif; ?>
	</div>
	<?php endforeach; ?>
	<?php endforeach; ?>

	<?php /* WHAT BELONGS TO NO OBJECT: the opening, a cleared stage, a
	         round survived, the extra life. Same renderer, own section,
	         exactly the way icons with no round art get one. And anything
	         whose thing has no art row at all lands here rather than
	         vanishing. */
	foreach ($snd_by_thing as $leftover) foreach ($leftover as $as) $snd_loose[] = $as;
	if ($snd_loose): ?>
	<h4>Sound</h4>
	<p class="note">The rest of the audio: what a run opens on, a stage cleared, a
	round survived. The file a sound asks for is its WORDING, the same way an icon
	filename is &mdash; rename the thing and it asks for a file under the new name.
	One sounds/ folder serves every theme, so a row says who else is listening
	before you replace anything.</p>
	<div class="thing"><div class="snd">
		<?php foreach ($snd_loose as $as) $sndRow($as); ?>
	</div></div>
	<?php endif; ?>
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
	function send(form, dropped, copyFrom) {
		var slot = card(form);
		if (!slot) return;
		var file = form.querySelector('input[type=file]');
		if (!dropped && !copyFrom && file && !file.value) return;

		var data = new FormData(form);
		if (dropped) data.set('art', dropped);
		/* A COPY CARRIES NO FILE AT ALL. The empty file input would arrive
		   as $_FILES['art'] with UPLOAD_ERR_NO_FILE, and the upload handler
		   runs on `isset($_FILES['art'])` -- so leaving it in means the
		   server answers "Upload failed (code 4)" and never looks at the
		   copy. */
		if (copyFrom) { data.delete('art'); data.set('copy_from', copyFrom); }
		data.append('ajax', '1');
		var btn = form.querySelector('button');
		var was = btn ? btn.textContent : '';
		if (btn) { btn.disabled = true; btn.textContent = 'Working'; }
		say(slot, copyFrom ? 'copying' : 'uploading', true);

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

	/*
	 * CLICK A PICTURE TO SEE IT FULL SIZE.
	 *
	 * The cards are 210px wide and the art is up to 1080, so a thumbnail
	 * says which image is in the slot and nothing about whether it is the
	 * right one -- which is the question somebody is on this page to
	 * answer. Opening the file in a new tab was the alternative and it
	 * loses your place in seventy slots.
	 *
	 * BUILT ON DEMAND and removed on close, so there is no hidden overlay
	 * sitting over the page waiting to be shown by mistake -- the same
	 * reason dsShowLoader() builds rather than toggles.
	 */
	function lightbox(img, slot) {
		var box = document.createElement('div');
		box.className = 'lightbox';
		var big = document.createElement('img');
		/* The card's own src, so it carries the same cache buster and a
		   slot replaced a moment ago shows the NEW picture. */
		big.src = img.src;
		big.alt = '';
		box.appendChild(big);

		var cap = document.createElement('div');
		cap.className = 'cap';
		var name = slot.querySelector('.name');
		var path = slot.querySelector('.path');
		var dims = slot.querySelector('.dims');
		/* textContent throughout: these are page-rendered strings, but a
		   theme names its own icons and that name reaches .name. */
		var b = document.createElement('b');
		b.textContent = name ? name.textContent : '';
		cap.appendChild(b);
		if (path) {
			var c = document.createElement('code');
			c.textContent = ' ' + path.textContent;
			cap.appendChild(document.createElement('br'));
			cap.appendChild(c);
		}
		if (dims) {
			var d = document.createElement('span');
			d.textContent = ' ' + dims.textContent;
			cap.appendChild(d);
		}
		box.appendChild(cap);
		var hint = document.createElement('div');
		hint.className = 'hint';
		hint.textContent = 'click anywhere, or press Escape, to close';
		box.appendChild(hint);

		function close() {
			box.remove();
			document.removeEventListener('keydown', onKey);
		}
		function onKey(ev) { if (ev.key === 'Escape') close(); }
		box.addEventListener('click', close);
		document.addEventListener('keydown', onKey);
		document.body.appendChild(box);
	}

	/*
	 * ONE PLAYER FOR THE WHOLE PAGE.
	 *
	 * Twenty-eight <audio controls> elements is twenty-eight light-grey
	 * widgets on a dark page, each a different width depending on whether
	 * its file had loaded, shoving every row's controls out of line. One
	 * Audio object and a small button per row instead -- and one object
	 * means starting a second sound stops the first, which is what you
	 * want when comparing two of them rather than hearing both at once.
	 */
	var sndNow = null, sndBtn = null;
	function sndStop() {
		if (sndNow) { sndNow.pause(); sndNow = null; }
		if (sndBtn) { sndBtn.classList.remove('on'); sndBtn.innerHTML = '&#9654;'; sndBtn = null; }
	}
	root.addEventListener('click', function (ev) {
		var b = ev.target.closest ? ev.target.closest('.snd .play') : null;
		if (!b || !b.getAttribute('data-src')) return;
		ev.preventDefault();
		var again = (b === sndBtn);
		sndStop();
		if (again) return;                      /* pressing it again stops it */
		sndNow = new Audio(b.getAttribute('data-src'));
		sndBtn = b;
		b.classList.add('on');
		b.innerHTML = '&#9632;';
		sndNow.addEventListener('ended', sndStop);
		/* A file that 404s or will not decode must put the button back,
		   or the row looks like it is playing something forever. */
		sndNow.addEventListener('error', sndStop);
		sndNow.play().catch(sndStop);
	});

	root.addEventListener('click', function (ev) {
		var img = ev.target;
		if (!img || img.tagName !== 'IMG') return;
		var slot = card(img);
		if (!slot || !slot.querySelector('.shot').contains(img)) return;
		ev.preventDefault();
		lightbox(img, slot);
	});

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

	/* Which slot a card IS, read off the hidden field the form already
	   posts, so the name can never disagree with what gets written. */
	function relOf(slot) {
		var f = slot && slot.querySelector('input[name=slot]');
		return f ? f.value : '';
	}

	/*
	 * A PICTURE ALREADY ON THE PAGE IS A DRAG SOURCE TOO.
	 *
	 * Several slots are deliberately the same image -- the two Super
	 * Soldier icons, a weapon whose frames barely move -- and saying so
	 * meant finding the file on your own machine and uploading it again.
	 *
	 * ITS OWN MIME TYPE, not the image's URL. A dragged <img> already
	 * offers text/uri-list, and reading that back would mean parsing a url
	 * into a path on the server -- the kind of parsing that ends up
	 * accepting one it should not. This carries the slot name the form
	 * itself posts, and anything without it is treated as an outside drag.
	 */
	var DS_SLOT = 'application/x-dropship-slot';
	root.addEventListener('dragstart', function (ev) {
		var slot = card(ev.target);
		var img  = slot && slot.querySelector('.shot img');
		if (!slot || !img || ev.target !== img) return;
		var rel = relOf(slot);
		if (!rel) return;
		ev.dataTransfer.setData(DS_SLOT, rel);
		/* text/plain as well, or Firefox treats the drag as carrying
		   nothing and refuses to start it. */
		ev.dataTransfer.setData('text/plain', rel);
		ev.dataTransfer.effectAllowed = 'copy';
	});

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
		var dt = ev.dataTransfer;
		var files = dt && dt.files;
		if (!files || !files.length) {
			/* Not a file from outside, so it may be one of our own slots.
			   getData() is only readable inside the drop handler. */
			var from = dt ? dt.getData(DS_SLOT) : '';
			if (!from) return;
			if (from === relOf(slot)) { say(slot, 'that is the same slot', false); return; }
			send(slot.querySelector('form'), null, from);
			return;
		}
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
