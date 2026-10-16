<?php
/*
 * One character slot, one request.
 *
 * NOTHING HERE IS A BATCH. The page uploads, flips and clears a single slot
 * at a time, so a failure costs that slot and nothing else, and progress
 * survives whatever happens to the next request. That is the whole shape of
 * this endpoint and the reason it exists rather than a form post.
 *
 * The browser has already resized the image to the target width before
 * sending, so what arrives is a couple of hundred KB rather than a 40MB NFT.
 * The resize in mart_accept() is a backstop, not the main event.
 */
ob_start();
ini_set('display_errors', 0);          // this answers JSON; a notice would corrupt it
register_shutdown_function(function () {
	$e = error_get_last();
	if ($e && ($e['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
		while (ob_get_level() > 0) ob_end_clean();
		if (!headers_sent()) header('Content-Type: application/json');
		echo json_encode(array('ok' => false,
			'why' => 'Fatal: ' . $e['message'] . ' (' . basename($e['file']) . ':' . $e['line'] . ')'));
	}
});

include '../db.php';
require_once __DIR__ . '/../admin-lib.php';
require_once __DIR__ . '/../monstrocity-art-lib.php';
header('Content-Type: application/json');

function out($a) { ob_clean(); echo json_encode($a); exit; }

/* THE GATE. admin_require() redirects, which is wrong for an endpoint the
   page reads as JSON, so the same check is made directly and answered. */
if (empty($_SESSION['userData']['user_id']) || (int)$_SESSION['userData']['user_id'] !== 1)
	out(array('ok' => false, 'why' => 'Not allowed.'));

$theme_id = (int)($_POST['theme_id'] ?? 0);
$action   = (string)($_POST['action'] ?? '');
$slot     = (string)($_POST['slot'] ?? '');
$char     = (string)($_POST['char'] ?? '');
$flip     = !empty($_POST['flip']);

/* The theme's folder comes from the DATABASE, never from the request. The
   slug is the one piece of this that becomes a filesystem path, so the client
   does not get to name it. */
$res = $conn->query('SELECT slug FROM monstrocity_themes WHERE id = ' . $theme_id);
$row = $res ? $res->fetch_assoc() : null;
if (!$row) out(array('ok' => false, 'why' => 'No such theme.'));
$slug = $row['slug'];

/* And the character must be one of the fourteen. An arbitrary name here would
   write an arbitrary filename into a served directory. */
$known = array();
foreach (monstrocity_characters() as $c) $known[$c['slug']] = $c['name'];
if (!isset($known[$char])) out(array('ok' => false, 'why' => 'No such character.'));
if (!in_array($slot, array('base', 'leader', 'damaged'), true))
	out(array('ok' => false, 'why' => 'No such slot.'));

$dir    = __DIR__ . '/../' . monstrocity_theme_dir($slug);
$folder = monstrocity_slot_folder($slot);
$file   = $dir . '/' . $folder . '/' . $char . '.png';

/* A cache-busting URL, because a slot is overwritten in place and the browser
   would otherwise keep showing the version it already has. */
function slot_url($slug, $folder, $char, $file) {
	return monstrocity_theme_dir($slug) . '/' . $folder . '/' . $char . '.png'
	     . '?v=' . (is_file($file) ? filemtime($file) : 0);
}

/*
 * ACTIVATE. This is the only action here that changes what players see, which
 * is why the board calls it the save: every square already wrote itself to
 * disk, and the active flag is the thing that was actually still pending.
 *
 * REFUSED ON AN INCOMPLETE SET. A theme in the picker with empty slots shows
 * a broken image where a character should be, and the player cannot tell that
 * from a bug. Counting here rather than trusting the page means a stale tab
 * cannot publish a half-built theme.
 */
if ($action === 'activate') {
	$missing = mart_missing_slots($slug);
	if ($missing) {
		out(array('ok' => false, 'why' => count($missing) . ' slot'
			. (count($missing) === 1 ? ' is' : 's are') . ' still empty ('
			. implode(', ', array_slice($missing, 0, 4))
			. (count($missing) > 4 ? ', and more' : '') . '). A theme in the picker with an empty '
			. 'slot shows a broken image, and a player cannot tell that from a bug.'));
	}
	$conn->query('UPDATE monstrocity_themes SET active = 1, updated_at = NOW() WHERE id = ' . $theme_id);
	out(array('ok' => true));
}

if ($action === 'upload') {
	if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
		$code = $_FILES['image']['error'] ?? -1;
		out(array('ok' => false, 'why' => $code === UPLOAD_ERR_INI_SIZE
			? 'Over the server upload limit even after resizing -- that should not happen.'
			: 'Upload failed (code ' . (int)$code . ').'));
	}
	list($written, $err) = mart_accept($_FILES['image']['tmp_name'], $slug, $slot, $char, $flip);
	if ($written === '') out(array('ok' => false, 'why' => $err));
	out(array('ok' => true, 'why' => $err, 'url' => slot_url($slug, $folder, $char, $file),
	          'damaged' => $slot === 'base'
	              ? slot_url($slug, 'battle-damaged', $char, $dir . '/battle-damaged/' . $char . '.png') : ''));
}

if ($action === 'flip') {
	if (!is_file($file)) out(array('ok' => false, 'why' => 'Nothing in that slot yet.'));
	$im = mart_load($file);
	if (!$im) out(array('ok' => false, 'why' => 'Could not read what is in that slot.'));
	$err = mart_save_png(mart_flip($im), $file);
	if ($err !== '') out(array('ok' => false, 'why' => $err));
	/* A flipped base means a stale damaged variant -- regenerate rather than
	   leave the pair facing opposite ways, which is a real state in the live
	   library today. */
	$dmg = '';
	if ($slot === 'base') {
		$b = mart_load($file);
		if ($b) { mart_save_png(mart_damage($b), $dir . '/battle-damaged/' . $char . '.png');
		          clearstatcache();
		          $dmg = slot_url($slug, 'battle-damaged', $char, $dir . '/battle-damaged/' . $char . '.png'); }
	}
	clearstatcache();
	out(array('ok' => true, 'url' => slot_url($slug, $folder, $char, $file), 'damaged' => $dmg));
}

if ($action === 'clear') {
	if (is_file($file)) @unlink($file);
	/* Clearing a base takes its generated damage with it. A damaged variant
	   with no base is the orphan this whole pipeline exists to prevent. */
	if ($slot === 'base') @unlink($dir . '/battle-damaged/' . $char . '.png');
	out(array('ok' => true, 'url' => '', 'damaged' => ''));
}

if ($action === 'score') {
	$b = mart_load($dir . '/base/' . $char . '.png');
	$d = mart_load($dir . '/battle-damaged/' . $char . '.png');
	if (!$b || !$d) out(array('ok' => true, 'score' => -1));
	out(array('ok' => true, 'score' => round(mart_damage_score($b, $d), 1),
	          'weak' => mart_damage_score($b, $d) < MONSTROCITY_DAMAGE_WEAK));
}

out(array('ok' => false, 'why' => 'Unknown action.'));
