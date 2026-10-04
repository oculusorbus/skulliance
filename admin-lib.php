<?php
/**
 * admin-lib.php — the rules behind the admin panel, with no output.
 *
 * WHY A LIB AND NOT JUST admin.php. Every write this panel makes is also
 * an authorisation boundary, and store-edit-harness.php already paid for
 * the lesson that a page which decides for itself what to draw and an
 * endpoint which decides for itself what to accept are two rules that
 * drift apart. One answer, both callers, and a harness that runs the real
 * thing rather than a description of it.
 *
 * NOTHING HERE ECHOES and nothing here includes db.php, so the whole file
 * is testable against a fake connection. See admin-harness.php.
 *
 * The mission maths is written down in missions-economy.md; this is the
 * enforcing half of that document.
 */

if (!defined('ADMIN_SUPER_USER')) define('ADMIN_SUPER_USER', 1);

/* The multiplier ceiling. missions-economy.md section 5b: past this level
   a mission keeps climbing in cost and duration but not in rate, which is
   what stops a 46-mission project paying eight times a 6-mission one.
   0 disables the cap and reproduces the live uncapped behaviour exactly. */
if (!defined('ADMIN_MULT_CAP')) define('ADMIN_MULT_CAP', 0);

/**
 * WHO MAY USE THIS PANEL.
 *
 * Deliberately narrower than storeItemEditRights(): that one hands a
 * partner creator the keys to their own listings, which is right for a
 * shop. This panel creates projects and rewrites mission economics, so it
 * is user 1 and nobody else. Returning a reason rather than a bare false
 * so the page can say why instead of 404-ing a confused admin.
 */
function adminRights() {
	$uid = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
	if ($uid <= 0)                   return array('ok' => false, 'why' => 'not signed in');
	if ($uid !== ADMIN_SUPER_USER)   return array('ok' => false, 'why' => 'not an admin');
	return array('ok' => true, 'why' => '', 'user_id' => $uid);
}

function adminIsSuper() { $r = adminRights(); return $r['ok']; }

/* ---------- naming ------------------------------------------------------- */

/**
 * THE FILENAME A MISSION'S ART WILL TAKE.
 *
 * Must stay identical to mission_art_slug() in missions-lib.php -- that is
 * the function the game renders through, and a panel that previews a
 * different name than the one the player's browser asks for is worse than
 * no preview. admin-harness.php asserts the two agree.
 */
function admin_mission_slug($title) {
	return strtolower(str_replace("'", "", str_replace(" ", "-", (string)$title)));
}

/**
 * Is that slug safe to put on a filesystem and in a URL?
 *
 * mission_art_slug() only strips apostrophes and spaces, so a colon, a
 * slash, an ampersand or an accented character lands straight in the
 * filename. 455 live rows contain none -- discipline, not enforcement.
 * This is the enforcement.
 */
function admin_slug_problem($slug) {
	$slug = (string)$slug;
	if ($slug === '')                               return 'the title produces an empty filename';
	if (strlen($slug) > 120)                        return 'the title is too long to be a filename';
	if (!preg_match('/^[a-z0-9][a-z0-9\-]*$/', $slug)) {
		$bad = preg_replace('/[a-z0-9\-]/', '', $slug);
		$bad = count_chars($bad, 3);
		return 'the title would put ' . ($bad === '' ? 'an unusable character' : '"' . $bad . '"')
		     . ' in the filename -- use only letters, numbers, spaces and apostrophes';
	}
	return '';
}

/* ---------- mission economics ------------------------------------------- */

/**
 * WHAT THE NEXT MISSION ON THIS PROJECT SHOULD COST.
 *
 * The cost ladder is the only free choice in the scheme (see
 * missions-economy.md section 1a), and the safest choice is to CONTINUE
 * WHAT THE PROJECT ALREADY DOES rather than impose a house style on a
 * ladder somebody already tuned.
 *
 * Two families exist live. A project on flat 100s gets 100 * level. A
 * project on the wide spread gets the next rung of the master sequence,
 * and once that runs out it continues at its own last step -- which is
 * better than silently switching family mid-ladder, because switching is
 * exactly what made append-safety fail in the first draft of the tiers.
 *
 * A brand-new project starts on flat 100s: it is what the newer half of
 * the platform already uses, and it is the only schedule where every
 * field depends on the level alone, so appending stays a pure INSERT.
 */
function admin_wide_ladder() {
	return array(200, 300, 500, 700, 1000, 1500, 2000, 2500, 3000);
}

function admin_next_cost($existing_costs, $level) {
	$level = (int)$level;
	if ($level <= 1) return 0;

	$paid = array();
	foreach ((array)$existing_costs as $c) { $c = (int)$c; if ($c > 0) $paid[] = $c; }
	sort($paid);

	/* No history, or the project is already on flat 100s: 100 * level. */
	$flat = true;
	foreach ($paid as $i => $c) { if ($c !== 100 * ($i + 2)) { $flat = false; break; } }
	if (!$paid || $flat) return 100 * $level;

	/* On the wide spread: take the next rung past the highest used. */
	$top = end($paid);
	foreach (admin_wide_ladder() as $rung) if ($rung > $top) return $rung;

	/* Past the master sequence. Continue by its own final step rather than
	   dropping back to a flat 100, which would make a mission CHEAPER than
	   the one below it. */
	$n = count($paid);
	$step = ($n >= 2) ? $paid[$n - 1] - $paid[$n - 2] : 500;
	return $top + max(100, (int)round($step / 100) * 100);
}

/** reward = cost * (1 + min(level, cap)/10). Cap 0 means uncapped. */
function admin_mission_reward($cost, $level, $cap = null) {
	$cap   = ($cap === null) ? ADMIN_MULT_CAP : (int)$cap;
	$level = (int)$level;
	$k     = ($cap > 0) ? min($level, $cap) : $level;
	return (int)(((int)$cost * (10 + $k)) / 10);
}

/** duration = cost / 100, in days. One field with the cost, never two. */
function admin_mission_duration($cost) { return (int)((int)$cost / 100); }

/** The number missions-lib.php puts in front of the player. */
function admin_mission_per_day($cost, $reward, $duration) {
	$duration = (int)$duration;
	return ($duration > 0) ? (((float)$reward - (float)$cost) / $duration) : 0.0;
}

/**
 * Everything the editor should pre-fill for a new mission at this level.
 * The operator writes the title, the description and the art; the
 * economics are not their problem.
 */
function admin_mission_defaults($existing_costs, $level, $cap = null) {
	$level = (int)$level;
	if ($level <= 1) {
		return array('level' => 1, 'cost' => 0, 'reward' => 10, 'duration' => 1, 'per_day' => 10.0);
	}
	$cost = admin_next_cost($existing_costs, $level);
	$rew  = admin_mission_reward($cost, $level, $cap);
	$dur  = admin_mission_duration($cost);
	return array('level' => $level, 'cost' => $cost, 'reward' => $rew, 'duration' => $dur,
	             'per_day' => admin_mission_per_day($cost, $rew, $dur));
}

/**
 * EVERY RULE FROM missions-economy.md SECTION 7, IN ONE PLACE.
 *
 * Returns a list of human-readable problems; empty means saveable. It
 * takes the project's OTHER levels rather than reading them itself so the
 * whole function stays testable with no database.
 */
function admin_validate_mission($m, $other_levels = array(), $cap = null) {
	$e     = array();
	$level = (int)(isset($m['level']) ? $m['level'] : 0);
	$cost  = (int)(isset($m['cost']) ? $m['cost'] : 0);
	$rew   = (int)(isset($m['reward']) ? $m['reward'] : 0);
	$dur   = (int)(isset($m['duration']) ? $m['duration'] : 0);
	$title = trim((string)(isset($m['title']) ? $m['title'] : ''));

	if ($title === '') $e[] = 'A mission needs a title; it is also the art filename.';
	else {
		$p = admin_slug_problem(admin_mission_slug($title));
		if ($p !== '') $e[] = ucfirst($p) . '.';
	}

	if ($level < 1) $e[] = 'Level must be 1 or more.';
	if (in_array($level, array_map('intval', (array)$other_levels), true))
		$e[] = "Level $level already exists on this project.";

	if ($level === 1) {
		/* The free intro, and it is the same on all 39 projects. */
		if ($cost !== 0 || $rew !== 10 || $dur !== 1)
			$e[] = 'Level 1 is the free intro: cost 0, reward 10, duration 1 day.';
	} else {
		if ($cost <= 0)        $e[] = 'Cost must be more than 0 above level 1.';
		if ($cost % 100 !== 0) $e[] = 'Cost must be a multiple of 100, or the duration cannot be whole days.';
		$want_dur = admin_mission_duration($cost);
		if ($dur !== $want_dur)
			$e[] = "Duration must be $want_dur day" . ($want_dur === 1 ? '' : 's')
			     . " for a cost of " . number_format($cost) . " -- duration is cost / 100, not a separate dial.";
		$want_rew = admin_mission_reward($cost, $level, $cap);
		if ($rew !== $want_rew)
			$e[] = 'Reward must be ' . number_format($want_rew) . ' for level ' . $level
			     . ' at that cost. Changing it breaks the one rule the whole economy rests on.';
		if ($rew % 10 !== 0)
			$e[] = 'Reward is not a multiple of ten, which means a multiplier finer than a tenth got in.';
	}
	return $e;
}

/**
 * A WHOLE PROJECT'S LADDER, checked as a set.
 *
 * Per-row validation cannot see a gap or a missing level 1, and both are
 * the kind of thing that only shows up as "mission 7 is unreachable"
 * weeks later. $rows is level => array(cost, reward, duration).
 */
function admin_validate_ladder($rows, $cap = null) {
	$e = array();
	if (!$rows) return array('This project has no missions yet.');

	$levels = array_map('intval', array_keys($rows));
	sort($levels);
	if ($levels[0] !== 1) $e[] = 'There is no level 1, so the project has no free opening mission.';
	for ($i = 1; $i < count($levels); $i++) {
		if ($levels[$i] !== $levels[$i - 1] + 1)
			$e[] = 'Levels jump from ' . $levels[$i - 1] . ' to ' . $levels[$i]
			     . '; the ladder has to be contiguous or the gap is unreachable.';
	}
	foreach ($rows as $lv => $r) {
		$r['level'] = (int)$lv;
		if (!isset($r['title'])) $r['title'] = 'placeholder';
		foreach (admin_validate_mission($r, array(), $cap) as $msg) $e[] = "Level $lv: $msg";
	}
	return $e;
}

/* ---------- currency icons ---------------------------------------------- */

/**
 * A PROJECT'S CURRENCY IS A FILENAME.
 *
 * Every surface renders it as icons/{lowercase(currency)}.png -- gallery,
 * realms, skulliance.php, Skull Swap's tile set. So two projects sharing a
 * currency share an icon, and skullswap.php's board dedupes on
 * LOWER(currency), which means the collision is not even visible as two
 * entries. Case-insensitive, because that is how the game groups them.
 */
function admin_currency_icon($currency) {
	return 'icons/' . strtolower(str_replace('$', '', trim((string)$currency))) . '.png';
}

function admin_currency_problem($currency, $taken = array()) {
	$c = trim((string)$currency);
	if ($c === '')                            return 'A project needs a currency; it is the icon filename.';
	if (!preg_match('/^[A-Za-z0-9]{2,16}$/', $c))
		return 'Currency must be 2 to 16 letters or digits -- it becomes a filename and a tile on the Skull Swap board.';
	foreach ((array)$taken as $t) {
		if (strcasecmp(trim((string)$t), $c) === 0)
			return 'Another project already uses "' . $t . '". They would share one icon, and Skull Swap would '
			     . 'show them as a single tile.';
	}
	return '';
}

/* ---------- uploads ------------------------------------------------------ */

/**
 * WHAT MAY BE UPLOADED, AND AS WHAT.
 *
 * quests.extension drives which files the game asks for, and the pairing
 * is not obvious: an mp4 mission needs the .mp4 AND a .gif beside it,
 * because every <img> surface asks for the gif (mission_art_ext()).
 *
 * mov is refused rather than accepted-and-converted. There is no ffmpeg
 * and no exec() on this host and Imagick has no video delegates, so there
 * is nothing to convert it WITH -- and accepting it would write a row the
 * game renders as a broken tile. A refusal with a reason is the honest
 * version. See missions-economy.md section 6d.
 */
function admin_art_kinds() {
	return array(
		'png' => array('still' => 'png', 'video' => false, 'mime' => array('image/png')),
		'jpg' => array('still' => 'jpg', 'video' => false, 'mime' => array('image/jpeg')),
		'gif' => array('still' => 'gif', 'video' => false, 'mime' => array('image/gif')),
		'mp4' => array('still' => 'gif', 'video' => true,  'mime' => array('video/mp4')),
	);
}

/** Mirrors mission_art_ext() in missions-lib.php. The harness pins them equal. */
function admin_art_ext($extension) { return ($extension === 'mp4') ? 'gif' : $extension; }

/**
 * Which files an operator still owes us for this mission.
 * $have is the set of extensions actually uploaded, e.g. array('mp4').
 */
function admin_art_missing($extension, $have) {
	$kinds = admin_art_kinds();
	if (!isset($kinds[$extension])) return array('"' . $extension . '" is not a format this platform renders.');
	$have = array_map('strtolower', (array)$have);
	$need = array($kinds[$extension]['still']);
	if ($kinds[$extension]['video']) $need[] = $extension;
	$missing = array();
	foreach ($need as $n) if (!in_array($n, $have, true)) $missing[] = $n;
	if ($missing && $extension === 'mp4')
		return array('An mp4 mission needs both files: the .mp4 and a .gif beside it for the still image. '
		           . 'Missing: .' . implode(', .', $missing) . '.');
	return $missing ? array('Missing the .' . implode(', .', $missing) . ' file.') : array();
}

/**
 * Resize and optimise through the SAME Imagick path the NFT cache uses.
 *
 * lib/image-cache-lib.php already does this in the web SAPI --
 * ajax/cache-nft-image.php drives it over HTTP -- so the memory limits,
 * the gif coalescing and the Lanczos filter are settled and proven here
 * rather than invented. Returns '' on success or a message.
 */
function admin_write_image($tmp_path, $dest_path, $max_width = 1000) {
	if (!is_readable($tmp_path)) return 'The upload did not arrive.';
	if (!class_exists('Imagick')) {
		/* Still better than refusing: an unoptimised image renders. */
		return @copy($tmp_path, $dest_path) ? '' : 'Could not write ' . basename($dest_path) . '.';
	}
	try {
		$im = new Imagick();
		$im->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
		$im->setResourceLimit(Imagick::RESOURCETYPE_MAP,    256 * 1024 * 1024);
		$im->readImage($tmp_path);
		if ($im->getNumberImages() > 1) {
			/* Coalesce first or every frame after the first resizes against
			   a partial canvas -- the same correction image-cache-lib.php
			   carries for animated NFT art. */
			$im = $im->coalesceImages();
			foreach ($im as $frame) if ($frame->getImageWidth() > $max_width)
				$frame->resizeImage($max_width, 0, Imagick::FILTER_LANCZOS, 1);
			$im = $im->deconstructImages();
			$ok = $im->writeImages($dest_path, true);
		} else {
			if ($im->getImageWidth() > $max_width)
				$im->resizeImage($max_width, 0, Imagick::FILTER_LANCZOS, 1);
			$im->stripImage();                       // drop EXIF and colour profiles
			if (strtolower(pathinfo($dest_path, PATHINFO_EXTENSION)) === 'jpg')
				$im->setImageCompressionQuality(86);
			$ok = $im->writeImage($dest_path);
		}
		return $ok ? '' : 'Imagick could not write ' . basename($dest_path) . '.';
	} catch (Throwable $ex) {
		return 'Could not process that image: ' . $ex->getMessage();
	}
}

/* ---------- reads and uploads, shared by the three admin pages ----------- *
 *
 * They take $conn as a parameter and this file still includes nothing, so
 * admin-harness.php keeps running the rules with no database.
 */
/* reads -------------------------------------------------------- */

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


/**
 * THE GATE, AND THE CHROME, FOR EVERY ADMIN PAGE.
 *
 * Three pages now instead of one, which is three places to forget the
 * check. So they call this instead of each rolling their own: it decides,
 * renders the header, opens the layout and draws the sub-nav.
 *
 * LAYOUT, AND WHY THE FIRST VERSION RENDERED NOTHING USEFUL. header.php
 * leaves a `<div class="container">` OPEN for the page to fill. The panel
 * opened a SECOND container inside it and then a `<div class="column">` --
 * and `.column` does not exist in dist/flexbox.css at all. A classless div
 * in a flex row, inside a nested height:100% container, is why selecting a
 * project appeared to do nothing: the form submitted fine, the page came
 * back, and the content had no box to live in. The house pattern is a
 * `.row` holding `.col1of3` (flex: 33%), which is wrong for a full-width
 * table, so this opens a row with its own full-width child.
 */
function admin_chrome($active) {
	$r = adminRights();
	if (!$r['ok']) {
		http_response_code(404);              // never confirm the panel exists
		include __DIR__ . '/header.php';
		echo '<div class="row"><div class="adm"><h2>Not found</h2></div></div>';
		exit;
	}
	include __DIR__ . '/header.php';
	$tabs = array(
		'projects'    => array('admin-projects.php',    'Projects'),
		'collections' => array('admin-collections.php', 'Collections'),
		'missions'    => array('admin-missions.php',    'Missions'),
	);
	echo '<div class="row"><div class="adm">';
	echo '<div class="adm-head"><h2>Admin</h2><nav class="adm-tabs">';
	foreach ($tabs as $key => $t) {
		printf('<a href="%s" class="%s">%s</a>', $t[0], $key === $active ? 'on' : '', $t[1]);
	}
	echo '</nav></div>';
}

function admin_flash($msgs) {
	foreach ($msgs['err'] as $m) echo '<p class="adm-msg bad">' . $m . '</p>';
	foreach ($msgs['ok']  as $m) echo '<p class="adm-msg good">' . $m . '</p>';
}

/**
 * The project picker every page carries. A real submit button, not an
 * onchange -- the select works without JS and cannot fail silently.
 */
function admin_project_picker($projects, $pid, $self) {
	echo '<form method="get" action="' . htmlspecialchars($self) . '" class="adm-pick">';
	echo '<label for="project">Project</label>';
	echo '<select name="project" id="project"><option value="0">&mdash; pick a project &mdash;</option>';
	foreach ($projects as $id => $p) {
		printf('<option value="%d"%s>%s (%s)</option>', $id, $id === $pid ? ' selected' : '',
			htmlspecialchars($p['name']), htmlspecialchars($p['currency']));
	}
	echo '</select><button type="submit">Open</button></form>';
}
