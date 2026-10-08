<?php
/**
 * dhc-download.php -- hand a player the full-size picture of their Fighter.
 *
 *     dhc-download.php?serial=123
 *
 * Streams a 1000px PNG as an attachment. That is the size of Maxingo's master
 * art: every layer under dhc/web/1000 is 1000x1000, so this is the largest
 * composite that is real detail rather than an upscale, and it is what a
 * player needs to post their assembly anywhere that is not this site. The
 * gallery already shows those same masters stacked in the browser -- what was
 * missing was a way to leave with one file instead of eleven.
 *
 * IT DOES NOT COMPOSE ANYTHING, for the reason dhc-compose.php spells out at
 * length: dhcf_render_fighter() knows the armless-torso variants, the
 * single-arm hybrid clip and the per-trait nudges, and a second composer
 * written for this page would quietly draw Fighters that differ from the
 * assembler, the Arena and the Discord embed. This asks that renderer for
 * 1000 instead of 500 and sends the bytes.
 *
 *     dhc-download.php?build=<json of slot => slug>
 *
 * YOURS ONLY. It shipped public first, on the argument that the collection is
 * public anyway and the 1000px layers are already served to anyone who opens a
 * Fighter in the gallery. That was wrong: a flattened, named, ready-to-post
 * picture of somebody's assembly is theirs to hand out, not a stranger's to
 * take. Two forms, both gated:
 *
 *   ?serial=  a Fighter you saved. Ownership is the WHERE clause, the same way
 *             ajax/dhc-rename-fighter.php does it -- not a read then a compare,
 *             which races and also confirms that a serial exists.
 *
 *   ?build=   the arrangement currently on the assembler canvas, which usually
 *             has not been saved and has no serial at all. Gated twice: every
 *             piece has to be one you have actually been awarded, AND it has
 *             to be a whole Fighter -- background, torso and head, the same
 *             three a save insists on. Without the second gate this is a way
 *             to walk the trait art out one clean 1000px layer at a time.
 *
 * NOTHING MAY PRINT BEFORE THE IMAGE. db.php runs with display_errors on, so
 * the include is buffered; a notice landing in front of the PNG header is a
 * corrupt download, not a warning.
 */

ob_start();
include 'db.php';
require_once __DIR__ . '/dhcfighters-lib.php';
require_once __DIR__ . '/dhcfighters-notify.php';
ob_end_clean();

/* A plain-text failure, never a half-written image.
 *
 * Unwinding to ZERO is right here and wrong in rewards.php, which is worth
 * saying because the two look identical: this file is the request, there is
 * no caller whose buffer could be destroyed, and anything already buffered
 * is by definition not part of the answer. run_reward_jobs() is called from
 * inside somebody else's buffering and swallowed its own failure manifest
 * doing this. */
function dhcd_fail($code, $msg) {
	while (ob_get_level() > 0) ob_end_clean();
	http_response_code($code);
	header('Content-Type: text/plain; charset=utf-8');
	echo $msg . "\n";
	exit;
}

/* db.php returns false rather than throwing -- mysqli_report is off
   platform-wide -- so a dead connection arrives here as an undefined $conn
   and a fatal on the next line. */
if (!isset($conn) || !$conn) dhcd_fail(503, 'Database unavailable.');

/*
 * WHO IS ASKING. The conditional gate dhcgallery.php and dhcsandbox.php use:
 * skulliance.php redirects an anonymous visitor to error.php, which would
 * hand an HTML page to something expecting a PNG, so guests are turned away
 * here with plain text instead. verify.php first -- it defines checkUser(),
 * which skulliance.php calls to resolve the user id. Merge the cookie, never
 * assign; see skulliance.php's own note.
 */
if (!isset($_SESSION['logged_in']) && isset($_COOKIE['SessionCookie'])) {
	$dhcd_ck = json_decode($_COOKIE['SessionCookie'], true);
	if (is_array($dhcd_ck)) {
		/* isset() first, unlike the copies of this block on the ordinary
		   pages. They can afford a warning; this one is streaming binary,
		   and one notice in front of the PNG is a corrupt download. */
		$_SESSION = array_merge(isset($_SESSION) ? (array)$_SESSION : array(), $dhcd_ck);
	} else {
		setcookie('SessionCookie', '', time() - 3600);
	}
	unset($dhcd_ck);
}
if (empty($_SESSION['logged_in'])) dhcd_fail(403, 'Sign in to download your Fighters.');
ob_start();
include 'verify.php';
include 'skulliance.php';
ob_end_clean();
$me = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
if ($me <= 0) dhcd_fail(403, 'Sign in to download your Fighters.');

$serial = isset($_GET['serial']) ? (int)$_GET['serial'] : 0;
$build  = isset($_GET['build'])  ? json_decode((string)$_GET['build'], true) : null;

if ($serial > 0) {
	/* Ownership is the WHERE clause. A Fighter that is not yours reads exactly
	   like one that does not exist, which is the answer a stranger should get.
	 *
	 * ONE ACCOUNT IS EXEMPT, AND ONLY HERE. User 1 is the platform's own
	 * account and the one that posts the Collection to X; showcasing what
	 * players have built is the point of it, and it needs the picture to do
	 * that. So for user 1 the ownership half of the clause is dropped and
	 * the Fighter still has to exist and not be disassembled.
	 *
	 * THIS IS THE CHECK THAT COUNTS. dhc-fighter-modal.php draws or hides
	 * the button on the same rule, but a hidden button is not a permission
	 * -- anyone can type the URL, so the gate lives in the query.
	 *
	 * The ?build= branch below is NOT exempted. A build is an arrangement
	 * sitting on somebody's canvas rather than a saved Fighter, and its gate
	 * exists to stop the trait art being walked out one clean 1000px layer
	 * at a time; that reasoning does not change for user 1, who in any case
	 * has no way to be looking at another player's unsaved canvas. */
	$row = null;
	$own = ($me === 1) ? '' : sprintf(' AND user_id = %d', $me);
	$res = $conn->query(sprintf(
		"SELECT id, serial, name, traits FROM dhc_fighters
		 WHERE serial = %d%s AND disassembled_at IS NULL LIMIT 1",
		$serial, $own));
	if ($res) $row = $res->fetch_assoc();
	if (!$row) dhcd_fail(404, $me === 1
		? 'No Fighter with that number.'
		: 'No Fighter of yours with that number.');

	$traits = json_decode($row['traits'], true);
	if (!is_array($traits) || !$traits) dhcd_fail(404, 'That Fighter has no layout to draw.');
} elseif (is_array($build) && $build) {
	/*
	 * AN UNSAVED BUILD. dhcf_clean_traits() drops anything that is not a real
	 * slot, so the only thing left to check is that the pieces are the
	 * player's -- dhcf_owned(), not dhcf_available(), because a trait already
	 * committed to another saved Fighter is still a trait you own and still
	 * something you are entitled to a picture of.
	 */
	$traits = dhcf_clean_traits($build);
	if (!$traits) dhcd_fail(400, 'That build has nothing in it.');
	/*
	 * IT HAS TO BE A FIGHTER, not a trait.
	 *
	 * Without this, ?build={"head":"x"} renders that head alone on a
	 * transparent 1000px canvas -- which turns this endpoint into a way to
	 * walk Maxingo's art out of the platform one clean layer at a time. The
	 * required three are the same three dhcf_save_fighter() insists on
	 * (DHCF_REQUIRED), so the rule is "you can take a picture of anything you
	 * could save", not a new restriction invented here.
	 *
	 * A background is one of them, so every download lands on solid art
	 * rather than alpha, and a torso and a head cover the middle of it.
	 */
	$missing = dhcf_missing_required($traits);
	if ($missing) {
		dhcd_fail(400, 'A Fighter needs a ' . implode(', ', $missing)
		             . ' before it can be downloaded.');
	}
	$owned = dhcf_owned($conn, $me);
	foreach ($traits as $slot => $slug) {
		$cat = dhcf_slot_category($slot);
		if (empty($owned[$cat][$slug])) dhcd_fail(403, 'That build uses a trait you do not own.');
	}
	/* No serial to name the file after, so the traits name it. Identical
	   builds reuse the filename instead of piling up as "(1)", "(2)". */
	$row = array('serial' => null,
	             'name'   => 'build-' . substr(md5(json_encode($traits)), 0, 6));
} else {
	dhcd_fail(400, 'No Fighter asked for.');
}

/*
 * 1000 first, 500 as a fallback. The fallback is not about GD being missing --
 * then both fail -- but about the day a 1000px source is absent for one trait
 * while the 250 and 500 derivatives are fine. A slightly smaller download
 * beats a broken button.
 */
ob_start();
$url = dhcf_render_fighter($traits, (int)$row['serial'], 1000);
if ($url === '') $url = dhcf_render_fighter($traits, (int)$row['serial'], 500);
ob_end_clean();
if ($url === '') dhcd_fail(503, 'Could not draw that Fighter right now.');

$abs = __DIR__ . '/dhcrenders/' . basename(parse_url($url, PHP_URL_PATH));
if (!is_file($abs)) dhcd_fail(503, 'Could not draw that Fighter right now.');

/* The rule lives in the library so dhc-download-harness.php can check it
   without standing up a web request. */
$file = dhcf_download_filename($row);

while (ob_get_level() > 0) ob_end_clean();
header('Content-Type: image/png');
header('Content-Disposition: attachment; filename="' . $file . '"');
header('Content-Length: ' . filesize($abs));
/* A Fighter can be edited, and editing re-hashes the render but not this URL,
   so the browser must come back and ask rather than serve yesterday's build. */
header('Cache-Control: private, no-cache');
readfile($abs);
$conn->close();
