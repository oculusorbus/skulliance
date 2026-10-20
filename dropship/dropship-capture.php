<?php
/**
 * CAPTURE A FUNCTION'S OUTPUT FOR DISCORD, WITHOUT ITS WARNINGS.
 *
 * checkLeaderboard() and friends print their output, so the only way to put
 * a leaderboard in a Discord embed is to buffer them. display_errors is ON
 * platform-wide, which means ANY notice raised while the buffer is open
 * lands in the embed and gets posted publicly -- a real Game Over post went
 * out reading:
 *
 *   Warning: Undefined array key "game_id" in
 *   /home/jeremiah/skulliance.io/staking/dropship/db.php on line 2220
 *
 * complete with the absolute server path, into a channel players read.
 *
 * TWO LAYERS, deliberately. display_errors is turned off for the duration,
 * which stops the common case; and whatever comes back is still scrubbed of
 * anything shaped like a PHP diagnostic, because an error raised before
 * this is reached, or printed by something that sets its own ini, would
 * otherwise sail straight through. A leaderboard that is missing a line is
 * a cosmetic problem; a server path in a public channel is not.
 *
 * Errors are not silenced, only kept out of the EMBED: they still reach the
 * error log, which is where they belong.
 */
function dropshipCapture(callable $fn) {
	$was = ini_get('display_errors');
	ini_set('display_errors', '0');
	ob_start();
	try {
		$fn();
	} catch (Throwable $e) {
		error_log('dropshipCapture: ' . $e->getMessage());
	}
	$out = (string)ob_get_clean();
	ini_set('display_errors', $was);
	return dropshipStripDiagnostics($out);
}

/** Remove PHP\'s own warning/notice/fatal lines from captured output. */
function dropshipStripDiagnostics($text) {
	$clean = preg_replace(
		'/^\\s*(Warning|Notice|Deprecated|Fatal error|Parse error|Strict Standards)\\s*:.*$/mi',
		'', (string)$text);
	/* Those arrive as "<br />\\n<b>Warning</b>: ..." when html_errors is on,
	   which the line-based pass above cannot see. */
	$clean = preg_replace(
		'#<br\\s*/?>\\s*<b>(Warning|Notice|Deprecated|Fatal error|Parse error)</b>:.*?(<br\\s*/?>|$)#is',
		'', (string)$clean);
	return trim((string)$clean);
}
