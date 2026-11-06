<?php
/**
 * ajax/testimonial.php — save, dismiss, or re-point a testimonial.
 *
 * POST ONLY AND SIGNED IN. Every branch writes, and a GET would let an
 * <img src> fire one from anywhere.
 *
 * It answers for the player, never for the queue: approving is the
 * admin page's job and nothing here can reach it.
 */
ob_start();
ini_set('display_errors', 0);

function tst_out($a) {
	while (ob_get_level() > 0) ob_end_clean();
	header('Content-Type: application/json');
	echo json_encode($a);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') tst_out(array('success' => false, 'error' => 'POST only'));

include_once __DIR__ . '/../db.php';
include __DIR__ . '/../skulliance.php';
require_once __DIR__ . '/../testimonials-lib.php';

$user_id = isset($_SESSION['userData']['user_id']) ? (int) $_SESSION['userData']['user_id'] : 0;
if ($user_id <= 0) tst_out(array('success' => false, 'error' => 'Sign in first.'));
if (!testimonials_ready($conn)) tst_out(array('success' => false, 'error' => 'Testimonials are not switched on yet.'));

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

if ($action === 'shown') {
	testimonial_mark_shown($conn, $user_id);
	testimonial_offer_forget();
	tst_out(array('success' => true));
}

if ($action === 'dismiss') {
	$never = !empty($_POST['never']);
	testimonial_dismiss($conn, $user_id, $never);
	testimonial_offer_forget();
	tst_out(array('success' => true, 'muted' => $never));
}

if ($action === 'visibility') {
	$ok = testimonial_set_visibility($conn, $user_id, isset($_POST['visibility']) ? (string) $_POST['visibility'] : '');
	tst_out(array('success' => $ok));
}

if ($action === 'save') {
	$err = testimonial_save(
		$conn, $user_id,
		isset($_POST['prompt_key']) ? (string) $_POST['prompt_key'] : '',
		isset($_POST['body']) ? (string) $_POST['body'] : '',
		isset($_POST['visibility']) ? (string) $_POST['visibility'] : 'named',
		/* The explicit confirmation. The save refuses without it. */
		!empty($_POST['consent']));
	if ($err !== '') tst_out(array('success' => false, 'error' => $err));
	testimonial_offer_forget();   /* they have one now; stop offering */
	tst_out(array(
		'success' => true,
		/* The share link is built from the saved text, so what gets
		   posted to X is what was actually written. */
		'share'   => testimonial_share_url((string) $_POST['body']),
	));
}

tst_out(array('success' => false, 'error' => 'unknown action'));
