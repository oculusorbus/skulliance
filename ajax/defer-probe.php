<?php
/*
 * ajax/defer-probe.php — does this server let go of the browser?
 *
 * THE QUESTION IS NOT ACADEMIC AND THE PROJECT HAS ANSWERED IT WRONG
 * BEFORE. skullpaper/MAINTENANCE.md records three production failures
 * at deferring work past the response and concludes it cannot be done
 * here. But what was tried was fastcgi_finish_request(), which does not
 * exist on this SAPI and was therefore a silent no-op, and
 * X-Accel-Buffering, which is an nginx header and means nothing to
 * LiteSpeed. litespeed_finish_request() -- the one call that applies --
 * was never tried.
 *
 * So this measures it rather than arguing about it. fire() answers
 * immediately, releases, sleeps, and only then writes a marker. If the
 * browser gets its reply in milliseconds AND the marker turns up with a
 * gap in it, the release is real. If the reply itself takes the whole
 * sleep, it is not, and the daily reward's second-request pattern stays
 * the only thing that works here.
 *
 * Writes one small file to the system temp directory and nothing else.
 * Sends no Discord message, touches no player data.
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../skulliance.php';
require_once __DIR__ . '/../admin-lib.php';

header('Content-Type: application/json');
/* adminRights() rather than admin_require(): that one answers a refusal
   in text/plain, and this endpoint's caller is parsing JSON. */
$dp_r = adminRights();
if (empty($dp_r['ok'])) { http_response_code(404); echo json_encode(array('ok' => false, 'why' => 'Not found.')); exit; }

define('DP_SLEEP', 3);
function dp_file($token) {
	return rtrim(sys_get_temp_dir(), '/') . '/skl-defer-' . preg_replace('~[^a-f0-9]~', '', $token) . '.json';
}

$mode = isset($_GET['mode']) ? (string) $_GET['mode'] : 'fire';

if ($mode === 'read') {
	$f = dp_file(isset($_GET['token']) ? $_GET['token'] : '');
	if (!is_file($f)) { echo json_encode(array('ok' => true, 'done' => false)); exit; }
	$d = json_decode((string) file_get_contents($f), true);
	@unlink($f);
	echo json_encode(array('ok' => true, 'done' => true, 'marker' => $d));
	exit;
}

$token = bin2hex(random_bytes(8));
$t0    = microtime(true);

/* THE ANSWER, WRITTEN AND MEASURED BEFORE ANYTHING SLOW HAPPENS. */
$body = json_encode(array(
	'ok'       => true,
	'token'    => $token,
	'sapi'     => php_sapi_name(),
	'released' => function_exists('litespeed_finish_request') ? 'litespeed_finish_request'
	            : (function_exists('fastcgi_finish_request') ? 'fastcgi_finish_request' : 'none'),
	'sleep'    => DP_SLEEP,
));
ignore_user_abort(true);
if (!headers_sent()) {
	header('Content-Length: ' . strlen($body));
	header('Connection: close');
}
echo $body;

/* The session lock is released too: without this the player's NEXT
   request queues behind this one and the stall has only moved. */
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

if (function_exists('litespeed_finish_request'))      { litespeed_finish_request(); }
elseif (function_exists('fastcgi_finish_request'))    { fastcgi_finish_request(); }
else { while (ob_get_level() > 0) { @ob_end_flush(); } @flush(); }

/* Everything below here is the part a player must never wait for. */
@set_time_limit(60);
sleep(DP_SLEEP);
@file_put_contents(dp_file($token), json_encode(array(
	'answered_at' => $t0,
	'finished_at' => microtime(true),
	'worked_for'  => round(microtime(true) - $t0, 3),
)));
exit;
