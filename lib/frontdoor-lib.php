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

/*
 * ---------------------------------------------------------------------------
 * THE LAYER BELOW PHP.
 *
 * The interaction log can only record requests that REACHED PHP. If the web
 * server refuses one first -- a ModSecurity rule, a CloudLinux resource
 * limit, a connection cap -- PHP never runs, nothing is logged, and the
 * result is indistinguishable from Discord never calling at all.
 *
 * That gap is exactly where this bug has been hiding. The endpoint is clean
 * when probed, the gate message is ours, the member still cannot get in --
 * and the self-probe CANNOT see it, because a request from this host to this
 * host does not look like a request from Discord to a stranger and may not
 * be inspected the same way.
 *
 * The web server's access log sees all of it: every request Discord made,
 * with the status the server actually returned. Comparing its count against
 * the interaction log's is the measurement nothing else here can make.
 * ---------------------------------------------------------------------------
 */

/** Where cPanel keeps this account's raw access logs, if we can read them. */
function frontdoor_access_paths($host = '') {
	$host  = preg_replace('/[^A-Za-z0-9.-]/', '', (string)$host);
	$home  = isset($_SERVER['HOME']) ? $_SERVER['HOME'] : '';
	if ($home === '' && function_exists('posix_getpwuid')) {
		$pw = @posix_getpwuid(posix_getuid());
		if (is_array($pw) && !empty($pw['dir'])) $home = $pw['dir'];
	}
	/* DOCUMENT_ROOT is public_html, so two levels up is the home directory
	   on every cPanel account -- the fallback for a SAPI that does not set
	   HOME, which LiteSpeed sometimes does not. */
	if ($home === '' && !empty($_SERVER['DOCUMENT_ROOT'])) {
		$home = dirname($_SERVER['DOCUMENT_ROOT']);
	}
	if ($home === '') return array();

	/*
	 * MATCHED BY PREFIX, NOT BY EXACT NAME, AND THAT IS NOT BELT AND BRACES.
	 *
	 * The first version of this looked for ~/access-logs/<domain> and
	 * ~/access-logs/<domain>-ssl_log and found NEITHER, because cPanel names
	 * them after the domain AND the account's main domain:
	 *
	 *     ~/access-logs/skulliance.io.jeremiahbarber.com-ssl_log
	 *
	 * The page then reported "no readable access log" on an account where
	 * the log was right there, 5.3MB of it -- which is the worst answer this
	 * panel can give, because "no log to read" and "no requests" are
	 * opposite findings and the whole point of the panel is not confusing
	 * them. So: list the directory and take anything that STARTS with the
	 * host, which covers the bare name, the -ssl_log suffix, and whatever
	 * the next cPanel version decides to append.
	 */
	$names = array($host);
	if ($host !== '') {
		if (strpos($host, 'www.') === 0) $names[] = substr($host, 4);
		else                             $names[] = 'www.' . $host;
	}
	$out = array();
	foreach (array('/access-logs/', '/logs/') as $dir) {
		$found = @scandir($home . $dir);
		if (!is_array($found)) continue;
		foreach ($found as $f) {
			if ($f === '.' || $f === '..') continue;
			/* Rotated archives are gzipped and cannot be tailed as text. */
			if (substr($f, -3) === '.gz') continue;
			foreach ($names as $n) {
				if ($n === '' || strpos($f, $n) !== 0) continue;
				$p = $home . $dir . $f;
				if (is_file($p) && is_readable($p)) $out[$p] = true;
			}
		}
	}
	return array_keys($out);
}

/**
 * The last $bytes of a file, cut at a line boundary.
 *
 * SEEKED, NOT READ WHOLE. An access log on a live domain is tens of
 * megabytes; file_get_contents() on one inside a page request is how a
 * diagnostic becomes the outage.
 */
function frontdoor_tail($path, $bytes = 262144) {
	$fh = @fopen($path, 'rb');
	if (!$fh) return '';
	$size = @filesize($path);
	if ($size === false) { fclose($fh); return ''; }
	$bytes = max(1024, (int)$bytes);
	if ($size > $bytes) @fseek($fh, -$bytes, SEEK_END);
	$raw = @stream_get_contents($fh);
	fclose($fh);
	if ($raw === false) return '';
	/* The first line is half a line when we seeked into the middle of one. */
	if ($size > $bytes) {
		$nl = strpos($raw, "\n");
		if ($nl !== false) $raw = substr($raw, $nl + 1);
	}
	return $raw;
}

/**
 * One combined-log line, or null when it is not one.
 *
 * TOLERANT ABOUT THE FRONT, STRICT ABOUT THE MIDDLE. Hosts prefix these
 * differently -- some put the vhost first, some the port -- and none of that
 * is worth failing over; the request line and the status code are what the
 * question needs, and those have one shape.
 */
function frontdoor_parse_access_line($line) {
	$line = trim((string)$line);
	if ($line === '') return null;
	if (!preg_match('~\[([^\]]+)\]\s+"([A-Z]+)\s+(\S+)[^"]*"\s+(\d{3})~', $line, $m)) return null;

	$when = 0;
	$dt = DateTime::createFromFormat('d/M/Y:H:i:s O', $m[1]);
	if ($dt instanceof DateTime) $when = $dt->getTimestamp();

	$head = substr($line, 0, strpos($line, '['));
	$ip   = '';
	if (preg_match('~(\d{1,3}(?:\.\d{1,3}){3})~', $head, $ipm)) $ip = $ipm[1];
	elseif (preg_match('~([0-9a-f]{1,4}(?::[0-9a-f]{0,4}){2,7})~i', $head, $ipm)) $ip = $ipm[1];

	/* The user agent is the last quoted field. It is the one thing that says
	   whether a request came from Discord or from somebody scanning. */
	$ua = '';
	if (preg_match_all('~"([^"]*)"~', $line, $q) && $q[1]) $ua = (string)end($q[1]);

	return array(
		'at' => $when, 'ip' => $ip, 'method' => $m[2], 'path' => $m[3],
		'status' => (int)$m[4], 'ua' => $ua,
	);
}

/** Is this the real Discord calling, rather than a scanner that found the URL? */
function frontdoor_is_discord($ua) {
	return stripos((string)$ua, 'discord') !== false;
}

/**
 * What the web server answered, for requests to one path, newest first.
 *
 * Returns array(ok, error, paths, hits).
 */
function frontdoor_access_hits($paths, $needle, $limit = 40) {
	$out = array('ok' => false, 'error' => '', 'paths' => $paths, 'hits' => array());
	if (!$paths) {
		$out['error'] = 'No readable access log. cPanel keeps them in ~/access-logs/, and some '
		              . 'plans do not expose them to PHP at all -- cPanel > Raw Access Logs, or '
		              . 'cPanel > ModSecurity Tools, answers the same question by hand.';
		return $out;
	}
	$needle = (string)$needle;
	$rows = array();
	foreach ($paths as $p) {
		foreach (explode("\n", frontdoor_tail($p)) as $line) {
			if ($needle !== '' && strpos($line, $needle) === false) continue;
			$r = frontdoor_parse_access_line($line);
			if ($r) $rows[] = $r;
		}
	}
	usort($rows, function ($a, $b) { return $b['at'] - $a['at']; });
	$out['ok']   = true;
	$out['hits'] = array_slice($rows, 0, max(1, (int)$limit));
	return $out;
}

/**
 * What a status code on THIS endpoint means, in a sentence.
 *
 * THE POINT OF THE PANEL IS THE CODES PHP NEVER SAW. 200 and 401 are the
 * endpoint answering; everything else was decided above it, and those are
 * the ones that look like "Skull Bot didn't respond" with an empty PHP log
 * behind them.
 */
function frontdoor_status_say($status) {
	$status = (int)$status;
	if ($status === 200) return array('ok',  'Answered.');
	if ($status === 401) return array('ok',  'Signature refused -- a scanner, or Discord validating the URL.');
	if ($status === 400) return array('warn','Body could not be read.');
	if ($status === 403 || $status === 406 || $status === 418 || $status === 412)
		return array('bad', 'REFUSED BEFORE PHP RAN. This is a firewall or ModSecurity rule, not '
		                  . 'our code -- check cPanel > ModSecurity Tools > Hits List. Discord '
		                  . 'sees no valid reply and the member is told the app did not respond.');
	if ($status === 429)  return array('bad', 'Rate limited by the server itself.');
	if ($status === 503 || $status === 508)
		return array('bad', 'RESOURCE LIMIT. The CloudLinux LVE turned the request away before PHP '
		                  . 'started -- cPanel > Resource Usage shows which limit.');
	if ($status >= 500)   return array('bad', 'The server failed on it.');
	if ($status === 408 || $status === 499) return array('bad', 'Discord gave up waiting.');
	return array('warn', 'Unexpected for this endpoint.');
}

/**
 * How many times each interaction id appears in a set of log rows.
 *
 * ONE PRESS, DELIVERED TWICE, IS NOT TWO PRESSES. Discord re-sends an
 * interaction it does not consider answered and the re-send carries the same
 * id, so a repeated id in this log means our reply was made and REFUSED --
 * which is a completely different bug from the endpoint being slow, and
 * reads identically in a list of timestamps.
 *
 * Rows with no id (an older line, or a request that never parsed) are not
 * counted: they would all collide on the empty string and report themselves
 * as one interaction retried many times, which is the exact wrong answer.
 */
function frontdoor_retry_counts($rows) {
	$n = array();
	foreach ((array)$rows as $r) {
		if (!is_array($r) || empty($r['iid'])) continue;
		$id = (string)$r['iid'];
		$n[$id] = isset($n[$id]) ? $n[$id] + 1 : 1;
	}
	return $n;
}

/**
 * WHAT DISCORD ITSELF HAS RECORDED AS OUR INTERACTIONS ENDPOINT.
 *
 * The Developer Portal is a form; this is the stored value, read back
 * through the API, and the two can disagree. It is the only check here that
 * can see a delivery problem on DISCORD's side rather than ours:
 *
 *   EMPTY        nothing is delivered to HTTP at all, whatever the portal
 *                appears to show. Discord CLEARS this field when an endpoint
 *                fails often enough, and this one was returning corrupted
 *                bodies for an afternoon.
 *   WRONG HOST   www and the apex are different origins, and Discord does
 *                not follow a redirect for an interaction -- it takes the
 *                3xx as the answer and the member sees a failure.
 *   RIGHT        delivery is configured, and a click that never arrived was
 *                never sent.
 *
 * Returns array(ok, error, url, name, id).
 */
function frontdoor_app() {
	$out = array('ok' => false, 'error' => '', 'url' => '', 'name' => '', 'id' => '');
	if (!function_exists('sk_discord_api')) { $out['error'] = 'sk_discord_api() is not loaded.'; return $out; }
	$r = sk_discord_api('GET', '/applications/@me');
	if (empty($r['ok']) || !is_array($r['body'])) {
		$out['error'] = 'Discord returned HTTP ' . (int)$r['status'] . ' for /applications/@me.';
		return $out;
	}
	$out['ok']   = true;
	$out['url']  = isset($r['body']['interactions_endpoint_url'])
		? (string)$r['body']['interactions_endpoint_url'] : '';
	$out['name'] = isset($r['body']['name']) ? (string)$r['body']['name'] : '';
	$out['id']   = isset($r['body']['id']) ? (string)$r['body']['id'] : '';
	return $out;
}

/**
 * Does the URL Discord has stored point at THIS endpoint?
 *
 * COMPARED BY HOST AND PATH, NOT BY STRING. A trailing slash or a different
 * scheme is not a mismatch worth alarming anybody about; a different HOST
 * is, because www.skulliance.io and skulliance.io are different origins and
 * one of them redirects -- which Discord does not follow.
 *
 * Returns array(state, say) where state is ok, warn or bad.
 */
function frontdoor_app_url_verdict($stored, $host, $path) {
	$stored = trim((string)$stored);
	if ($stored === '') {
		return array('bad',
			'EMPTY. Discord is not delivering interactions over HTTP at all, whatever the '
		  . 'Developer Portal appears to show -- and Discord CLEARS this field itself when an '
		  . 'endpoint fails often enough. Re-save the URL in the portal.');
	}
	$p = @parse_url($stored);
	if (!is_array($p) || empty($p['host'])) {
		return array('bad', 'Discord has stored something that is not a URL: ' . $stored);
	}
	$theirs = strtolower($p['host']);
	$ours   = strtolower((string)$host);
	if ($theirs !== $ours) {
		/* www and the apex are different origins here, and one redirects. */
		return array('bad',
			'Points at ' . $theirs . ', and you are reading this on ' . $ours . '. Those are '
		  . 'different origins, one of them redirects, and Discord does NOT follow a redirect '
		  . 'for an interaction -- it takes the 3xx as the answer and the member sees a failure.');
	}
	$theirpath = isset($p['path']) ? $p['path'] : '';
	if (rtrim($theirpath, '/') !== rtrim((string)$path, '/')) {
		return array('warn',
			'Points at ' . $theirpath . ' on this host, not ' . $path . '. If that is another '
		  . 'copy of the endpoint, its log is the one to read.');
	}
	return array('ok', 'Delivery is configured and points here.');
}

/*
 * ---------------------------------------------------------------------------
 * WHAT AN ORDINARY MEMBER CAN ACTUALLY DO IN THE RULES CHANNEL.
 *
 * THE ASYMMETRY THIS EXISTS TO EXPLAIN. The gate works every time for the
 * owner and fails for newcomers, and the owner has Administrator --
 * which BYPASSES EVERY CHANNEL OVERWRITE. So the one class of cause that
 * cannot be reproduced from the owner's own account is a permission the
 * Visitor role is missing in that channel, and it is invisible in testing
 * for exactly the reason it is invisible in the settings dialog: the
 * person checking is never subject to it.
 *
 * Discord does not publish a permission named "may press a button", so
 * nothing here declares a verdict it cannot support. What it does is put
 * the effective bits on screen, computed the way Discord computes them,
 * so the question stops being a guess.
 * ---------------------------------------------------------------------------
 */

/** The permission bits worth looking at here, in the order worth reading. */
function frontdoor_perm_bits() {
	return array(
		'VIEW_CHANNEL'             => array(10, 'See the channel at all. Without it there is no button to press.'),
		'USE_APPLICATION_COMMANDS' => array(31, 'Use an app in this channel. The closest thing Discord has to a '
		                                      . '"may press a button" permission, and the usual cause of a button '
		                                      . 'that works for an admin and does nothing for everyone else.'),
		'SEND_MESSAGES'            => array(11, 'Post here. Normal to deny in a rules channel, and denying it is '
		                                      . 'not supposed to stop a button working.'),
		'READ_MESSAGE_HISTORY'     => array(16, 'See messages posted before they arrived -- including the gate '
		                                      . 'message itself, which was posted once and never again.'),
		'USE_EXTERNAL_APPS'        => array(50, 'Use apps that are not installed to this server. Not how this bot '
		                                      . 'is installed, so it should not matter here.'),
	);
}

/** Does this permission bitfield (a decimal string from Discord) carry $bit? */
function frontdoor_has_perm($bits, $bit) {
	if (PHP_INT_SIZE < 8) return null;          // 64-bit only; a guess is worse than nothing
	$n = (int)$bits;
	return ($n & (1 << (int)$bit)) !== 0;
}

/**
 * The permissions a role effectively has IN one channel.
 *
 * COMPUTED THE WAY DISCORD COMPUTES THEM, which is an order and not a
 * union: the @everyone role's guild permissions, then the role's own, then
 * the channel's @everyone overwrite, then the role's channel overwrite --
 * deny before allow at each step. Doing it in any other order gives an
 * answer that looks plausible and is wrong for the one case that matters,
 * which is a permission allowed at the guild and denied in the channel.
 *
 * ADMINISTRATOR SHORT-CIRCUITS, because it does in Discord. That is the
 * whole reason this page is needed: the person testing has it.
 *
 * Returns array(ok, error, bits, admin, role_name).
 */
function frontdoor_role_channel_perms($guild_id, $channel_id, $role_id) {
	$out = array('ok' => false, 'error' => '', 'bits' => 0, 'admin' => false, 'role_name' => '');
	if (!function_exists('sk_discord_api')) { $out['error'] = 'sk_discord_api() is not loaded.'; return $out; }
	$gid = preg_replace('/[^0-9]/', '', (string)$guild_id);
	$cid = preg_replace('/[^0-9]/', '', (string)$channel_id);
	$rid = preg_replace('/[^0-9]/', '', (string)$role_id);
	if ($gid === '' || $cid === '') { $out['error'] = 'No guild or channel id.'; return $out; }

	$rr = sk_discord_api('GET', '/guilds/' . $gid . '/roles');
	if (empty($rr['ok']) || !is_array($rr['body'])) {
		$out['error'] = 'Could not read the roles (HTTP ' . (int)$rr['status'] . ').'; return $out;
	}
	$cr = sk_discord_api('GET', '/channels/' . $cid);
	if (empty($cr['ok']) || !is_array($cr['body'])) {
		$out['error'] = 'Could not read the channel (HTTP ' . (int)$cr['status'] . ').'; return $out;
	}

	$base = 0;
	foreach ($rr['body'] as $r) {
		if (!isset($r['id'])) continue;
		/* The @everyone role's id IS the guild id. */
		if ((string)$r['id'] === $gid || ($rid !== '' && (string)$r['id'] === $rid)) {
			$base |= (int)$r['permissions'];
			if ((string)$r['id'] === $rid) $out['role_name'] = isset($r['name']) ? (string)$r['name'] : '';
		}
	}
	if (frontdoor_has_perm($base, 3)) {          // ADMINISTRATOR
		$out['ok'] = true; $out['admin'] = true; $out['bits'] = $base; return $out;
	}

	$ow = isset($cr['body']['permission_overwrites']) ? $cr['body']['permission_overwrites'] : array();
	/* @everyone's channel overwrite first, then the role's -- in that order,
	   because a role allow is meant to win over the everyone deny. */
	foreach (array($gid, $rid) as $want) {
		if ($want === '') continue;
		foreach ((array)$ow as $o) {
			if (!isset($o['id']) || (string)$o['id'] !== $want) continue;
			$base &= ~((int)(isset($o['deny']) ? $o['deny'] : 0));
			$base |=   (int)(isset($o['allow']) ? $o['allow'] : 0);
		}
	}
	$out['ok'] = true;
	$out['bits'] = $base;
	return $out;
}
