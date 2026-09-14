<?php
/**
 * JSON reply helper for the DHC endpoints.
 *
 * An AJAX endpoint that promises JSON has to deliver JSON even when something
 * upstream printed first. db.php runs with display_errors on, the platform has
 * output buffering enabled, and this chain pulls in db.php, skulliance.php,
 * webhooks.php and its credentials -- any one of which can emit a byte without
 * the endpoint knowing.
 *
 * Saving a Fighter succeeded and still reported "Network error" for exactly
 * that reason: 57 characters arrived ahead of the JSON, JSON.parse() threw,
 * and the browser reported a network failure for a request that had worked.
 * The row was written; only the reply was unreadable.
 *
 * So: discard anything buffered, log what it was so the leak can be found, and
 * emit clean JSON. Defensive rather than a substitute for fixing a leak --
 * but the endpoint's contract should not depend on every include behaving.
 */
function dhc_json($payload) {
	$leaked = '';
	while (ob_get_level() > 0) {
		$chunk  = ob_get_clean();
		$leaked = $chunk . $leaked;
	}
	if ($leaked !== '' && trim($leaked) !== '') {
		// Truncated: this is a breadcrumb for the error log, not a dump.
		error_log('dhc_json: discarded ' . strlen($leaked) . ' bytes before JSON: '
		          . substr(preg_replace('/\s+/', ' ', $leaked), 0, 200));
	}
	if (!headers_sent()) header('Content-Type: application/json');
	echo json_encode($payload);
	exit;
}
