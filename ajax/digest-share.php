<?php
/**
 * ajax/digest-share.php -- keep a player's day poster so X can show it.
 *
 * x.com/intent/post cannot attach a file. The only picture a post carries
 * is the one the shared URL declares, and that URL has to be fetchable by a
 * crawler that is not signed in -- so the poster the browser drew has to
 * land on the server before the post is written. The modal does this on the
 * Share click and then opens X with digest.php?d=<token>, which is a public
 * page whose og:image is the stored file. One click, picture included.
 *
 * WHAT MAKES THIS SAFE TO ACCEPT.
 *
 *   - Signed in, and the row is the CALLER'S own day. No user parameter.
 *   - The filename is DERIVED, never supplied: a token from the user id and
 *     the day. A client-chosen name is a file write wherever they like.
 *   - DECODED AND RE-ENCODED with GD rather than stored as sent. Whatever
 *     was in those bytes -- PHP in a comment chunk, a polyglot -- does not
 *     survive being turned into pixels and written back out.
 *   - Dimensions and byte size are capped before any of that.
 *   - One file per player per day, because the token is deterministic, so
 *     re-sharing overwrites instead of accumulating.
 *
 * NOTHING MAY PRINT BEFORE THE JSON: display_errors is on platform-wide.
 */
ob_start();
ini_set('display_errors', 0);
register_shutdown_function(function () {
	$err = error_get_last();
	if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
		while (ob_get_level() > 0) ob_end_clean();
		if (!headers_sent()) header('Content-Type: application/json');
		echo json_encode(array('ok' => false, 'message' => 'Fatal: ' . $err['message']));
	}
});

include '../db.php';
require_once __DIR__ . '/../lib/digest-share-lib.php';

header('Content-Type: application/json');

function dsh_out($ok, $extra = array()) {
	ob_clean();
	echo json_encode(array_merge(array('ok' => $ok), $extra), JSON_UNESCAPED_SLASHES);
	exit;
}

/* Same lightweight restore the digest endpoint uses, and merged rather than
   raw-assigned -- see skulliance.php's own note on why. */
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$cookie = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($cookie)) { $_SESSION = array_merge((array) $_SESSION, $cookie); }
}
$uid = isset($_SESSION['userData']['user_id']) ? (int) $_SESSION['userData']['user_id'] : 0;
if ($uid <= 0)                        dsh_out(false, array('message' => 'Not signed in.'));
if ($_SERVER['REQUEST_METHOD'] !== 'POST') dsh_out(false, array('message' => 'POST only.'));
if (!function_exists('imagecreatefromstring')) dsh_out(false, array('message' => 'No image support.'));

/* The day is the server's, exactly as ajax/player-digest.php computes it --
   the client does not get to say which day it is sharing. */
$day = date('Y-m-d', strtotime('-1 day'));

if (empty($_FILES['poster']['tmp_name']) || !is_uploaded_file($_FILES['poster']['tmp_name']))
	dsh_out(false, array('message' => 'No image received.'));
$size = (int) $_FILES['poster']['size'];
if ($size <= 0 || $size > 4 * 1024 * 1024)
	dsh_out(false, array('message' => 'That image is too large.'));

/* Checked as an IMAGE before it is read as one, and bounded: a 30000x30000
   PNG is a few hundred KB on disk and gigabytes once decoded. */
$info = @getimagesize($_FILES['poster']['tmp_name']);
if (!$info || $info[2] !== IMAGETYPE_PNG) dsh_out(false, array('message' => 'PNG only.'));
if ($info[0] < 200 || $info[1] < 200 || $info[0] > 2400 || $info[1] > 3000)
	dsh_out(false, array('message' => 'That is not a poster.'));

$im = @imagecreatefrompng($_FILES['poster']['tmp_name']);
if (!$im) dsh_out(false, array('message' => 'That image could not be read.'));

$dir = dirname(__DIR__) . '/' . DSH_DIR;
if (!is_dir($dir) && !@mkdir($dir, 0755, true))
	dsh_out(false, array('message' => 'Could not store the image.'));

$token = dsh_token($uid, $day);
$path  = $dir . '/' . $token . '.jpg';

/*
 * WRITTEN AS JPEG, not as the PNG that arrived. It is a photograph-like
 * poster, so a 1080x1350 PNG is ~900KB where the JPEG is ~200KB -- and a
 * multi-megabyte card is exactly what Discord's embed proxy skips (see
 * partner.php, where a 4.6MB og:image rendered as nothing at all).
 *
 * Atomic, because two taps on Share can race and a half-written file that
 * is nonzero would be served as a valid card.
 */
$tmp = $dir . '/.' . $token . '.' . getmypid() . '-' . mt_rand(1000, 9999) . '.tmp';
$wrote = @imagejpeg($im, $tmp, 86) && @rename($tmp, $path) && is_file($path) && filesize($path) > 0;
@unlink($tmp);
if (!$wrote) dsh_out(false, array('message' => 'Could not store the image.'));

dsh_prune($dir);

dsh_out(true, array(
	'url'   => DSH_BASE . 'digest.php?d=' . $token,
	'image' => DSH_BASE . DSH_DIR . '/' . $token . '.jpg',
));
