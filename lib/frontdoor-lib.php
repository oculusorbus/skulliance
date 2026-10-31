<?php
/**
 * lib/frontdoor-lib.php -- is the rules gate actually the thing people press?
 *
 * WHY THIS EXISTS. "The application didn't respond in time" has two causes
 * that look identical from the inside of Discord and are opposites in the
 * code:
 *
 *   THE CLICK NEVER REACHED US. A button belongs to the application that
 *     posted it. An old BotGhost rules message still sitting in the channel
 *     carries BotGhost's buttons, and BotGhost is the thing that kept dying
 *     -- pressing it times out forever, no matter how healthy this endpoint
 *     is, and nothing arrives here to log. Same symptom if the Interactions
 *     Endpoint URL is not saved in the Developer Portal.
 *
 *   THE CLICK REACHED US AND WE WERE LATE. Host queueing, a cold worker, a
 *     startup warning printed in front of the JSON.
 *
 * Every tool for this so far has looked at the second one. This looks at the
 * first, which is the one that cannot be seen from the server at all without
 * asking Discord who owns the buttons in that channel.
 *
 * READ ONLY. Nothing here posts, edits or deletes anything -- a stale gate
 * message is removed in Discord, by the owner, in one tap. A diagnostic that
 * deletes things is a diagnostic nobody dares run.
 */

/** Our own bot user id, which is what a message we posted has as its author. */
function frontdoor_me($bot_token) {
	if (empty($bot_token) || !function_exists('sk_discord_api')) {
		return array('ok' => false, 'error' => 'No bot token.', 'id' => '', 'name' => '');
	}
	$r = sk_discord_api('GET', '/users/@me');
	if (empty($r['ok']) || !is_array($r['body'])) {
		return array('ok' => false, 'id' => '', 'name' => '',
		             'error' => 'Discord returned HTTP ' . (int)$r['status'] . ' for /users/@me.');
	}
	return array('ok' => true, 'error' => '',
	             'id'   => isset($r['body']['id']) ? (string)$r['body']['id'] : '',
	             'name' => isset($r['body']['username']) ? (string)$r['body']['username'] : '');
}

/**
 * Every message in a channel that carries a button, newest first.
 *
 * ONE PAGE OF FIFTY. The gate message is pinned near the top of a channel
 * that has almost nothing else in it, and a stale one from a year ago is
 * still within fifty -- a channel where it is not is a channel with a
 * different problem.
 *
 * Returns array(ok, error, messages), each message being id, at, author
 * name/id, whether the author is a bot, the message's application_id, and
 * the buttons on it as array(custom_id, label).
 */
function frontdoor_gate_messages($bot_token, $channel_id, $limit = 50) {
	$out = array('ok' => false, 'error' => '', 'messages' => array());
	$chan = preg_replace('/[^0-9]/', '', (string)$channel_id);
	if ($chan === '')      { $out['error'] = 'No rules channel id is set.'; return $out; }
	if (empty($bot_token)) { $out['error'] = 'No bot token.';               return $out; }
	if (!function_exists('sk_discord_api')) { $out['error'] = 'sk_discord_api() is not loaded.'; return $out; }

	$limit = max(1, min(100, (int)$limit));
	$r = sk_discord_api('GET', '/channels/' . $chan . '/messages?limit=' . $limit);
	if (empty($r['ok'])) {
		$st = (int)$r['status'];
		/* Said plainly, because the symptom at the Discord end of each of
		   these is an empty list, which reads as a tidy channel. */
		if ($st === 403) $out['error'] = 'Discord refused (403). The bot needs View Channel and '
		                               . 'Read Message History on the rules channel.';
		else if ($st === 404) $out['error'] = 'That channel does not exist, or the bot cannot see it.';
		else if ($st === 429) $out['error'] = 'Rate limited by Discord. Try again shortly.';
		else $out['error'] = 'Discord returned HTTP ' . $st . '.';
		return $out;
	}
	if (!is_array($r['body'])) { $out['error'] = 'Could not read the channel.'; return $out; }

	foreach ($r['body'] as $m) {
		$buttons = frontdoor_buttons(isset($m['components']) ? $m['components'] : array());
		if (!$buttons) continue;      // a plain message is not a door
		$out['messages'][] = array(
			'id'       => isset($m['id']) ? (string)$m['id'] : '',
			'at'       => isset($m['timestamp']) ? strtotime((string)$m['timestamp']) : 0,
			'author'   => isset($m['author']['username']) ? (string)$m['author']['username'] : '',
			'author_id'=> isset($m['author']['id']) ? (string)$m['author']['id'] : '',
			'is_bot'   => !empty($m['author']['bot']),
			'app_id'   => isset($m['application_id']) ? (string)$m['application_id'] : '',
			'buttons'  => $buttons,
		);
	}
	$out['ok'] = true;
	return $out;
}

/**
 * The buttons inside a components tree.
 *
 * WALKED, NOT INDEXED. Components nest -- rows hold buttons, and Discord's
 * newer container types hold rows -- so a reader that looks at
 * components[0]['components'] finds the gate today and nothing the day a
 * message is built any other way. Type 2 is a button; a LINK button (style
 * 5) has a url and no custom_id and is not an interaction at all, so it is
 * left out: it cannot time out and it is not a door.
 */
function frontdoor_buttons($components, $depth = 0) {
	$out = array();
	if (!is_array($components) || $depth > 5) return $out;
	foreach ($components as $c) {
		if (!is_array($c)) continue;
		if (isset($c['type']) && (int)$c['type'] === 2 && !empty($c['custom_id'])) {
			$out[] = array(
				'custom_id' => (string)$c['custom_id'],
				'label'     => isset($c['label']) ? (string)$c['label'] : '',
			);
		}
		if (!empty($c['components'])) {
			foreach (frontdoor_buttons($c['components'], $depth + 1) as $b) $out[] = $b;
		}
	}
	return $out;
}

/**
 * What this message is, as far as the gate is concerned.
 *
 * THE AUTHOR DECIDES WHERE A CLICK GOES, and that is the whole point of this
 * function. Discord routes an interaction to the application that owns the
 * message, not to whichever application happens to understand the custom_id
 * -- so a rules message posted by any other bot is a dead door that this
 * endpoint will never hear about, however correct everything here is.
 *
 * Returns array(state, say):
 *   live     ours, and carrying the gate button
 *   foreign  somebody else's application: clicks go there and die there
 *   ours     ours, but not the gate button -- another feature's message
 *   unknown  we could not establish our own id, so nothing can be judged
 */
function frontdoor_classify($msg, $our_id, $gate_custom_id) {
	$has_gate = false;
	foreach ((array)$msg['buttons'] as $b) {
		if ((string)$b['custom_id'] === (string)$gate_custom_id) { $has_gate = true; break; }
	}
	$our_id = (string)$our_id;
	if ($our_id === '') {
		return array('unknown', 'Cannot tell: Discord would not say which bot we are.');
	}
	$mine = ((string)$msg['author_id'] === $our_id);
	if (!$mine) {
		return array('foreign',
			'Posted by ' . ($msg['author'] !== '' ? $msg['author'] : 'another bot')
			. ', so Discord sends clicks on it to THAT application, not here. Anyone who '
			. 'presses this gets "the application did not respond". Delete the message.');
	}
	if ($has_gate) return array('live', 'This is the working gate.');
	return array('ours',
		'Ours, but not the rules button -- some other feature posted it.');
}

/**
 * Hit the interactions endpoint as an unsigned stranger, a few times.
 *
 * WHAT THIS CATCHES THAT NOTHING ELSE CAN. The endpoint must answer an
 * unsigned request with a clean 401 and nothing but its own body; Discord
 * refuses to save the endpoint URL otherwise. A PHP STARTUP WARNING is
 * written before the script is parsed, so it lands in FRONT of that body --
 * and it fires per WORKER, not per request, so the first hit after a worker
 * spawns is corrupted and the next one is fine. That is why every single
 * measurement said the endpoint was healthy while members could not get in.
 *
 * So: several hits, and the bytes are inspected rather than the status code.
 * Over HTTP, which is the only way this means anything -- the warning is a
 * property of the web PHP's ini, and the shell's php is a different build.
 *
 * Returns array of array(status, ms, clean, head) -- `clean` false means
 * something was printed before our own first byte.
 */
function frontdoor_probe($url, $times = 3, $expect = 'invalid request signature') {
	$out = array();
	$times = max(1, min(6, (int)$times));
	for ($i = 0; $i < $times; $i++) {
		$t  = microtime(true);
		$ch = curl_init();
		curl_setopt_array($ch, array(
			CURLOPT_URL            => $url,
			CURLOPT_POST           => 1,
			CURLOPT_POSTFIELDS     => '{"type":1}',
			CURLOPT_HTTPHEADER     => array('Content-Type: application/json'),
			CURLOPT_RETURNTRANSFER => 1,
			CURLOPT_TIMEOUT        => 8,
			CURLOPT_SSL_VERIFYPEER => 0,
		));
		$body   = (string)curl_exec($ch);
		$status = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$err    = curl_error($ch);
		curl_close($ch);
		$ms = (microtime(true) - $t) * 1000;

		$clean = frontdoor_clean_body($body, $err, $expect);
		$out[] = array(
			'status' => $status,
			'ms'     => (int)round($ms),
			'clean'  => $clean,
			'error'  => $err,
			'head'   => substr(str_replace(array("\r", "\n"), ' ', $body), 0, 160),
		);
	}
	return $out;
}

/**
 * Did the response start where it should?
 *
 * ITS OWN FUNCTION BECAUSE THE TEST IS THE WHOLE POINT, and the obvious
 * spelling of it is wrong. A startup warning is printed IN FRONT of the
 * body, so the right answer is still in there -- strpos() !== false passes
 * on exactly the broken response this exists to catch, and would have
 * reported the endpoint healthy through the afternoon it was not. It has to
 * be position zero.
 */
function frontdoor_clean_body($body, $err, $expect) {
	if ((string)$err !== '') return false;
	if ((string)$expect === '') return false;
	return strpos((string)$body, (string)$expect) === 0;
}

/**
 * The ini values that only mean something when read over HTTP.
 *
 * GLOBAL VALUE, NOT THE EFFECTIVE ONE. db.php calls ini_set() and
 * date_default_timezone_set() on every page, so ini_get() here reports what
 * this page did to itself and says nothing about the worker PHP started. The
 * php.ini value is what a startup warning is emitted from, so that is the
 * one to show -- ini_get_all() carries both.
 */
function frontdoor_ini() {
	$all = @ini_get_all(null, true);
	$g = function ($k) use ($all) {
		if (!is_array($all) || !isset($all[$k])) return null;
		$v = $all[$k];
		return is_array($v) && array_key_exists('global_value', $v) ? $v['global_value'] : null;
	};
	return array(
		'sapi'             => PHP_SAPI,
		'version'          => PHP_VERSION,
		'date.timezone'    => $g('date.timezone'),
		'display_errors'   => $g('display_errors'),
		'output_buffering' => $g('output_buffering'),
		'sodium'           => function_exists('sodium_crypto_sign_verify_detached'),
	);
}
