<?php
/**
 * ajax/craft.php — convert, shatter, burn.
 *
 * POST ONLY AND SIGNED IN. Every branch moves a balance, and a GET would
 * let an <img src> fire one from anywhere.
 *
 * WHY IT EXISTS. These three were full-page form posts handled at the top
 * of skulliance.php, which meant the only moment available to show
 * anything was after the reload -- so the reveal would have had to animate
 * a result the page had already printed, and a refused craft would have
 * animated a success that never happened. The modal has to know the real
 * outcome before it plays, so the work had to move behind a request the
 * browser waits on.
 *
 * THE VALIDATION IS THE SAME VALIDATION, deliberately copied rather than
 * loosened: convert is bounded by getMinimumBalance(), shatter by the
 * DIAMOND balance, burn by the CARBON balance. The client's number is
 * never trusted -- it is clamped against a figure re-read from the
 * database inside this request.
 *
 * The three writers themselves (craft/shatter/burn in db.php) are
 * untouched. This endpoint decides whether they may run and reports what
 * they did; it does not reimplement them.
 */
ob_start();
ini_set('display_errors', 0);

function cft_out($a) {
	while (ob_get_level() > 0) ob_end_clean();
	header('Content-Type: application/json');
	echo json_encode($a);
	exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') cft_out(array('success' => false, 'error' => 'POST only'));

include_once __DIR__ . '/../db.php';
include __DIR__ . '/../skulliance.php';

if (!isset($_SESSION['userData']['user_id'])) cft_out(array('success' => false, 'error' => 'Sign in first.'));

$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
$amount = isset($_POST['amount']) ? (int) $_POST['amount'] : 0;
if ($amount <= 0) cft_out(array('success' => false, 'error' => 'Nothing to craft.'));

/*
 * BALANCES BEFORE AND AFTER, both read from the database rather than
 * computed. The modal shows what actually moved, and a figure this
 * endpoint worked out for itself could disagree with the page that
 * reloads behind it.
 */
$cft_before = getBalances($conn);

if ($action === 'convert') {
	$min = (int) getMinimumBalance($conn);
	if ($amount > $min) cft_out(array('success' => false,
		'error' => 'You only have ' . number_format($min) . ' of your smallest core point.'));
	craft($conn, $amount);
	$result = array(
		'kind'   => 'convert',
		'spent'  => $amount,          /* of each of the six */
		'gained' => $amount,          /* DIAMOND, 1:1 */
	);
} elseif ($action === 'shatter') {
	$diamond = isset($cft_before['DIAMOND']) ? (int) $cft_before['DIAMOND'] : 0;
	if ($amount > $diamond) cft_out(array('success' => false,
		'error' => 'You only have ' . number_format($diamond) . ' DIAMOND.'));
	shatter($conn, $amount);
	$result = array(
		'kind'   => 'shatter',
		'spent'  => $amount,          /* DIAMOND */
		'gained' => $amount,          /* of each of the six */
	);
} elseif ($action === 'burn') {
	$carbon = (int) getBalance($conn, 15);
	if ($amount > $carbon) cft_out(array('success' => false,
		'error' => 'You only have ' . number_format($carbon) . ' CARBON.'));
	/* 100 CARBON = 1 DIAMOND, and the slider steps in hundreds. A number
	   that is not a multiple of 100 would mint a fractional DIAMOND, so
	   it is floored here rather than refused: the remainder stays yours. */
	$amount = (int) (floor($amount / 100) * 100);
	if ($amount < 100) cft_out(array('success' => false, 'error' => 'It takes 100 CARBON to form 1 DIAMOND.'));
	burn($conn, $amount, 15);
	$result = array(
		'kind'   => 'burn',
		'spent'  => $amount,                  /* CARBON */
		'gained' => (int) ($amount / 100),    /* DIAMOND */
	);
} else {
	cft_out(array('success' => false, 'error' => 'unknown action'));
}

$result['success'] = true;
$result['balances'] = getBalances($conn);
cft_out($result);
