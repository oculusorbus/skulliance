<?php
/* frontdoor-harness.php - CLI only. No database, no network, no Discord.
 *
 * THE GATE'S DIAGNOSTICS, WHICH ARE THE ONLY THING LEFT WHEN A MEMBER CANNOT
 * GET IN AND CANNOT BE ASKED TO RUN ANYTHING.
 *
 * What makes this worth a harness is that every check in it is a check whose
 * WRONG ANSWER IS REASSURING:
 *
 *   - "clean response" written as strpos() !== false passes on exactly the
 *     corrupted body it exists to catch, because a warning printed in front
 *     of the right answer still contains the right answer. That spelling
 *     would have called the endpoint healthy through the afternoon it was
 *     taking the server's front door off its hinges.
 *   - a button is routed by WHO POSTED IT, not by what its custom_id says.
 *     A judgement made on the custom_id calls a dead BotGhost message live.
 *   - the interaction log is the thing that separates "we were slow" from
 *     "the click never arrived", and it only does that if it writes a line
 *     for EVERY interaction. The old handler returned early when the request
 *     was fast, which is how a silent log came to mean two opposite things.
 *
 * Usage: php frontdoor-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/frontdoor-lib.php';

/* The log writes to data/, so point it somewhere disposable first -- before
   the library is loaded, because the path is a define(). */
$tmp = sys_get_temp_dir() . '/sk-ilog-harness-' . getmypid() . '.log';
define('SK_ILOG_FILE', $tmp);
define('SK_ILOG_MAX', 4096);
define('SK_ILOG_KEEP', 2048);
require_once __DIR__ . '/lib/discord-interaction-log.php';
register_shutdown_function(function () use ($tmp) { @unlink($tmp); });

echo "a corrupted response is not a clean one\n";
{
	$expect = 'invalid request signature';
	ok(frontdoor_clean_body($expect, '', $expect) === true,
	   'the correct body is not recognised as clean');

	/*
	 * THE REAL ONE, VERBATIM FROM THE LOG OF 2026-10-30. A startup warning
	 * is written before the script is parsed, so it arrives IN FRONT of the
	 * body -- which is why this cannot be a "contains" test.
	 */
	$warned = "<br />\n<b>Warning</b>:  PHP Startup: Invalid date.timezone value '', "
	        . "using 'UTC' in Unknown on line <b>0</b><br />\n" . $expect;
	ok(frontdoor_clean_body($warned, '', $expect) === false,
	   'a startup warning printed in front of the body still reads as clean -- this is the '
	 . 'check written as strpos() !== false, and it reports a broken endpoint as healthy');

	/*
	 * A TRANSFER THAT FAILED IS NOT EVIDENCE OF HEALTH, even when the bytes
	 * that did arrive start correctly -- which is the only shape of this
	 * that a body test cannot catch on its own. The first draft of this
	 * assertion passed an EMPTY body alongside the error and so proved
	 * nothing: the body check was answering it.
	 */
	ok(frontdoor_clean_body($expect, 'Operation timed out after 8000 ms', $expect) === false,
	   'a response whose transfer failed part-way reads as clean because the bytes that '
	 . 'arrived happen to start right');
	ok(frontdoor_clean_body('', 'Connection timed out', $expect) === false,
	   'a request that never arrived reads as clean');
	ok(frontdoor_clean_body('', '', $expect) === false, 'an empty body reads as clean');
	/* And an endpoint answering something else entirely. */
	ok(frontdoor_clean_body('<html>404</html>', '', $expect) === false,
	   'a 404 page reads as clean');
}

echo "\nthe buttons are found wherever they are nested\n";
{
	/* An action row holding a button: what the gate message is today. */
	$row = array(array('type' => 1, 'components' => array(
		array('type' => 2, 'style' => 1, 'label' => 'I Agree to the Rules',
		      'custom_id' => 'sk_rules_start'))));
	$b = frontdoor_buttons($row);
	ok(count($b) === 1 && $b[0]['custom_id'] === 'sk_rules_start',
	   'the button in a plain action row was not found');
	ok($b[0]['label'] === 'I Agree to the Rules', 'the label did not come through');

	/*
	 * ONE LEVEL DEEPER. Discord's container components hold rows which hold
	 * buttons, so a reader that looks at components[0]['components'] finds
	 * the gate today and nothing the day a message is built any other way --
	 * and "no buttons on it" is indistinguishable from "a tidy channel".
	 */
	$nested = array(array('type' => 17, 'components' => $row));
	$b = frontdoor_buttons($nested);
	ok(count($b) === 1 && $b[0]['custom_id'] === 'sk_rules_start',
	   'a button nested inside a container was missed');

	/*
	 * A LINK BUTTON IS NOT A DOOR. Style 5 carries a url and no custom_id,
	 * never produces an interaction, and cannot time out -- listing it would
	 * put a row on the page that can never be the problem.
	 */
	$link = array(array('type' => 1, 'components' => array(
		array('type' => 2, 'style' => 5, 'label' => 'Website', 'url' => 'https://x.test'))));
	ok(frontdoor_buttons($link) === array(), 'a link button is being counted as an interaction');

	/* A select menu is type 3; not a button, and not what the gate uses. */
	$sel = array(array('type' => 1, 'components' => array(
		array('type' => 3, 'custom_id' => 'pick'))));
	ok(frontdoor_buttons($sel) === array(), 'a select menu is being reported as a button');

	ok(frontdoor_buttons(array()) === array(), 'an empty components tree is not empty');
	ok(frontdoor_buttons('nonsense') === array(), 'a non-array components value is not handled');
}

echo "\nwho posted it decides where a click goes\n";
{
	$us    = '123456789012345678';
	$them  = '987654321098765432';
	$gate  = 'sk_rules_start';
	$msg = function ($author, $custom) {
		return array('id' => '1', 'at' => 0, 'author' => 'SomeBot', 'author_id' => $author,
		             'is_bot' => true, 'app_id' => '',
		             'buttons' => array(array('custom_id' => $custom, 'label' => 'x')));
	};

	list($st,) = frontdoor_classify($msg($us, $gate), $us, $gate);
	ok($st === 'live', "our own gate message is classified as '$st'");

	/*
	 * THE ASSERTION THIS FILE EXISTS FOR.
	 *
	 * Discord routes an interaction to the application that OWNS THE
	 * MESSAGE, not to whichever application understands the custom_id. A
	 * stale BotGhost rules message can carry a button whose id we recognise
	 * perfectly, and pressing it will still never reach this server. A
	 * classifier that reads the custom_id first calls that message live and
	 * sends the owner looking at the endpoint for a bug that is not there.
	 */
	list($st, $say) = frontdoor_classify($msg($them, $gate), $us, $gate);
	ok($st === 'foreign',
	   "a message posted by ANOTHER application carrying our own custom_id is classified "
	 . "as '$st' -- the custom_id is being read as if it decided routing");
	ok(stripos($say, 'delete') !== false,
	   'the verdict on a dead door does not say what to do about it');

	list($st,) = frontdoor_classify($msg($us, 'something_else'), $us, $gate);
	ok($st === 'ours', "our own message with a different button is classified as '$st'");

	/* NO ANSWER IS NOT A VERDICT. If /users/@me failed we do not know which
	   bot we are, and guessing would accuse a healthy message. */
	list($st,) = frontdoor_classify($msg($us, $gate), '', $gate);
	ok($st === 'unknown', "with our own id unknown the verdict is '$st' rather than unknown");
}

echo "\nthe lag comes out of the interaction id itself\n";
{
	/*
	 * A SNOWFLAKE CARRIES ITS OWN CREATION TIME, so lag is measured against
	 * Discord's clock and not ours -- the server's clock has been wrong on
	 * this host before (five hours out, 2026-10-30) and a lag computed from
	 * it would have read as hours.
	 *
	 * Worked the other way for the fixture: a known millisecond, shifted
	 * into an id, must come back out.
	 */
	$ms = 1762000000000;                       // 2025-11-01, comfortably real
	$id = (string)((($ms - 1420070400000) * 4194304) + 7);
	ok(sk_ilog_snowflake_ms($id) === $ms,
	   'a snowflake does not decode back to the millisecond it was made: got '
	 . sk_ilog_snowflake_ms($id));

	ok(sk_ilog_snowflake_ms('') === 0, 'an empty id produces a time');
	ok(sk_ilog_snowflake_ms('123') === 0, 'a too-short id produces a time');
	ok(sk_ilog_snowflake_ms('not-a-number') === 0, 'a non-numeric id produces a time');
	/* A future id means a bad read, and a NEGATIVE lag printed next to a
	   timeout is worse than no lag at all. */
	$future = (string)(((time() + 86400 * 7) * 1000 - 1420070400000) * 4194304);
	ok(sk_ilog_snowflake_ms($future) === 0, 'an id from the future is accepted');
}

echo "\nthe log keeps what it is given, newest first\n";
{
	@unlink($tmp);
	ok(sk_ilog_read(10) === array(), 'a log that does not exist reads as something');

	sk_ilog(array('outcome' => 'chain-picker', 'user' => '111', 'lag' => '120ms', 'work' => '48ms'));
	sk_ilog(array('outcome' => 'passed', 'user' => '222', 'lag' => '310ms', 'work' => '900ms'));
	$rows = sk_ilog_read(10);
	ok(count($rows) === 2, 'two writes did not produce two lines');
	/* NEWEST FIRST, because the line somebody needs is the one from the
	   click thirty seconds ago, and a page that puts it at the bottom of
	   forty rows is a page they scroll past it on. */
	ok($rows[0]['outcome'] === 'passed', 'the log is not newest first');
	ok($rows[1]['user'] === '111', 'the older line is wrong or missing');
	ok(isset($rows[0]['at']) && $rows[0]['at'] !== '', 'a line carries no timestamp');

	/* A LINE IS A LINE. A note carrying a newline -- a fatal's message, say
	   -- would otherwise split into a second row that parses as garbage. */
	sk_ilog(array('outcome' => 'fatal', 'note' => "Call to undefined\nfunction foo()\ton line 3"));
	$rows = sk_ilog_read(10);
	ok(count($rows) === 3, 'a value containing a newline split the line in two');
	ok(strpos($rows[0]['note'], 'undefined function') !== false,
	   'the note was mangled: ' . $rows[0]['note']);

	/* An empty value is dropped rather than written as `lag=`, which would
	   read as a measurement of nothing. */
	sk_ilog(array('outcome' => 'ping', 'lag' => '', 'user' => null));
	$rows = sk_ilog_read(10);
	ok(!isset($rows[0]['lag']) && !isset($rows[0]['user']),
	   'an empty field is written out as a key with no value');
	ok(sk_ilog(array()) === false, 'an empty write is attempted anyway');
}

echo "\none press delivered twice is not two presses\n";
{
	/*
	 * THIS IS THE DISTINCTION THE WHOLE LOG EXISTS FOR, SECOND EDITION.
	 *
	 * Discord re-sends an interaction it does not consider answered, and the
	 * re-send carries the SAME id. Five lines five seconds apart are either
	 * five people pressing a button -- fine -- or one press whose reply
	 * Discord refused, which is a bug and is invisible in a list of
	 * timestamps. The id tells them apart outright.
	 */
	$rows = array(
		array('iid' => '111', 'outcome' => 'chain-picker'),
		array('iid' => '111', 'outcome' => 'chain-picker'),
		array('iid' => '111', 'outcome' => 'chain-picker'),
		array('iid' => '222', 'outcome' => 'chain-picker'),
	);
	$n = frontdoor_retry_counts($rows);
	ok(isset($n['111']) && $n['111'] === 3, 'a re-sent interaction is not counted as re-sent');
	ok(isset($n['222']) && $n['222'] === 1, 'a single delivery is counted as more than one');

	/*
	 * A ROW WITH NO ID MUST NOT COUNT. Older lines carry none, and they
	 * would all collide on the empty string -- reporting a quiet log as one
	 * interaction retried forty times, which is the exact wrong answer and
	 * the most alarming one the page could print.
	 */
	$n = frontdoor_retry_counts(array(
		array('outcome' => 'ping'), array('outcome' => 'ping'),
		array('iid' => '', 'outcome' => 'ping'),
	));
	ok($n === array(), 'rows with no interaction id are being counted together as retries');
	ok(frontdoor_retry_counts(array()) === array(), 'an empty log produces counts');
	ok(frontdoor_retry_counts('nonsense') === array(), 'a non-array is not handled');

	/* And the endpoint has to write the id, or none of the above can fire. */
	$src = harness_strip_comments((string)file_get_contents(__DIR__ . '/discord-interactions.php'));
	ok(preg_match("/'iid'\s*=>/", $src) === 1,
	   'the endpoint does not log the interaction id, so a retry cannot be told from a click');
}

echo "\nthe log cannot grow without bound, and trims to whole lines\n";
{
	@unlink($tmp);
	/* SK_ILOG_MAX is 4096 here. Write past it. */
	for ($i = 0; $i < 400; $i++) {
		sk_ilog(array('outcome' => 'chain-picker', 'user' => '1000000000000000' . ($i % 10),
		              'note' => str_repeat('x', 40)));
	}
	clearstatcache(true, $tmp);
	$size = filesize($tmp);
	ok($size <= SK_ILOG_MAX,
	   'the log grew to ' . $size . ' bytes past a cap of ' . SK_ILOG_MAX
	 . ' -- a public endpoint can fill a disk');
	$rows = sk_ilog_read(500);
	ok(count($rows) > 1, 'trimming emptied the log instead of trimming it');
	/*
	 * THE OLDEST SURVIVING LINE IS WHOLE. Cutting at a byte offset leaves
	 * half a line at the top, which parses as a row with no timestamp and
	 * no outcome -- and reads as a mystery rather than as a trim.
	 */
	$oldest = end($rows);
	ok(preg_match('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', $oldest['at']) === 1,
	   'the oldest line after a trim starts mid-line: at="' . $oldest['at'] . '"');
	ok(isset($oldest['outcome']), 'the oldest line after a trim has no outcome');
	/* And the newest write survived the trim that its own write triggered. */
	ok(isset($rows[0]['outcome']) && $rows[0]['outcome'] === 'chain-picker',
	   'the line that triggered the trim was the line thrown away');
}

echo "\nthe endpoint logs every interaction, not only the slow ones\n";
{
	$src   = harness_strip_comments((string)file_get_contents(__DIR__ . '/discord-interactions.php'));
	ok($src !== '', 'discord-interactions.php is gone; these checks are blind');

	/*
	 * THE ORDER IS THE WHOLE FIX. The handler used to `return` when the
	 * request was fast, so a quiet log meant either a healthy gate or a gate
	 * Discord never calls -- and it was read as the first while it was the
	 * second for most of a day. The write has to come BEFORE the early
	 * return, or the log answers nothing it is there to answer.
	 */
	$at_log  = strpos($src, 'sk_ilog(');
	$at_stop = strpos($src, '$ms < SK_SLOW_MS');
	ok($at_log !== false, 'the endpoint no longer writes an interaction log line');
	ok($at_stop !== false, 'the slow-request threshold is gone');
	ok($at_log !== false && $at_stop !== false && $at_log < $at_stop,
	   'the interaction log is written AFTER the fast-path return, so only slow requests are '
	 . 'recorded -- which is the thing that could not tell "we were slow" from "nobody called"');

	/* Only signed traffic reaches the file: the endpoint is a public URL and
	   a scanner would otherwise push out the lines somebody needs. */
	ok(strpos($src, "HTTP_X_SIGNATURE_ED25519'])") !== false
	   && strpos($src, 'if (!empty($_SERVER[\'HTTP_X_SIGNATURE_ED25519\'])) {') !== false,
	   'the log write is not fenced on the request carrying a signature');

	/*
	 * NO REPLY LEAVES WITHOUT NAMING ITSELF.
	 *
	 * Walked rather than counted: every sk_respond()/sk_replace() CALL SITE
	 * must have an sk_out() between it and the previous one, so a branch
	 * added later cannot quietly log as 'started'. A count would pass while
	 * the new branch is the unnamed one.
	 */
	$body = substr($src, strpos($src, '$type = $data['));
	preg_match_all('/\bsk_(out|respond|replace)\s*\(/', $body, $m, PREG_OFFSET_CAPTURE);
	$named = false; $unnamed = 0;
	foreach ($m[1] as $hit) {
		if ($hit[0] === 'out') { $named = true; continue; }
		if (!$named) $unnamed++;
		$named = false;
	}
	ok($unnamed === 0, $unnamed . ' repl' . ($unnamed === 1 ? 'y' : 'ies')
	   . ' leave the endpoint without naming an outcome first, so they log as "started"');
}

echo "\nthe access log is parsed, including the lines PHP never saw\n";
{
	/* Combined format, as cPanel writes it. */
	$l = '1.2.3.4 - - [31/Oct/2026:01:02:03 -0500] "POST /staking/discord-interactions.php '
	   . 'HTTP/1.1" 200 61 "-" "Discord-Interactions/1.0 (+https://discord.com)"';
	$r = frontdoor_parse_access_line($l);
	ok($r !== null, 'an ordinary combined-log line does not parse');
	ok($r['status'] === 200, 'the status came back as ' . $r['status']);
	ok($r['ip'] === '1.2.3.4', 'the client address came back as "' . $r['ip'] . '"');
	ok($r['method'] === 'POST', 'the method came back as ' . $r['method']);
	/*
	 * THE AGENT IS THE LAST QUOTED FIELD, AND TAKING THE FIRST ONE LOOKS
	 * RIGHT. The first quoted field is the REQUEST LINE, and every line in
	 * this panel has "discord-interactions.php" in its request line -- so a
	 * parser that grabs the wrong quote reports every scanner on the
	 * internet as Discord, which is the one distinction the panel rests on.
	 * Asserted by value, not by whether it smells like Discord.
	 */
	ok($r['ua'] === 'Discord-Interactions/1.0 (+https://discord.com)',
	   'the user agent came back as "' . $r['ua'] . '"');
	ok(frontdoor_is_discord($r['ua']), 'a request from Discord is not recognised as one');

	/* The same endpoint, hit by something that is not Discord. */
	$scan = '9.9.9.9 - - [31/Oct/2026:02:00:00 -0500] "POST /staking/discord-interactions.php '
	      . 'HTTP/1.1" 401 24 "-" "Mozilla/5.0 (compatible; scanner)"';
	$r2 = frontdoor_parse_access_line($scan);
	ok($r2 !== null && !frontdoor_is_discord($r2['ua']),
	   'a scanner hitting this endpoint is counted as Discord, because the request line it '
	 . 'asked for has "discord" in it');

	/*
	 * NO CLIENT ADDRESS AT ALL. Searching the whole line for a dotted quad
	 * finds one in the query string and prints it as the source, which is a
	 * fabricated fact in a table people will act on. The address can only
	 * come from the part of the line BEFORE the timestamp.
	 */
	$noip = '- - - [31/Oct/2026:02:01:00 -0500] "POST /staking/discord-interactions.php?ip=9.9.9.9 '
	      . 'HTTP/1.1" 200 3 "-" "Discord-Interactions/1.0"';
	$r3 = frontdoor_parse_access_line($noip);
	ok($r3 !== null && $r3['ip'] === '',
	   'an address was invented from elsewhere in the line: "' . ($r3 ? $r3['ip'] : '') . '"');
	ok(date('Y-m-d H:i', $r['at']) === date('Y-m-d H:i', strtotime('2026-10-31 01:02:03 -0500')),
	   'the timestamp parsed to ' . date('c', $r['at']));

	/*
	 * HOSTS PREFIX THESE DIFFERENTLY -- some put the vhost and port in front
	 * -- and none of that is worth failing over, because the request line
	 * and the status are the only fields the question needs. A parser that
	 * anchors at the start of the line returns nothing on half of cPanel's
	 * own formats, and "nothing in the access log" is the single most
	 * misleading answer this page can give.
	 */
	$vhost = 'skulliance.io:443 5.6.7.8 - - [31/Oct/2026:01:02:03 -0500] '
	       . '"POST /staking/discord-interactions.php HTTP/1.1" 403 226 "-" "Discord-Interactions/1.0"';
	$r = frontdoor_parse_access_line($vhost);
	ok($r !== null, 'a line prefixed with the vhost does not parse');
	ok($r !== null && $r['status'] === 403, 'the status on a vhost-prefixed line was missed');
	ok($r !== null && $r['ip'] === '5.6.7.8',
	   'the vhost was read as the client address: "' . ($r ? $r['ip'] : '') . '"');

	ok(frontdoor_parse_access_line('') === null, 'an empty line parses as a request');
	ok(frontdoor_parse_access_line('not a log line at all') === null, 'junk parses as a request');

	/* A scanner is not Discord, and the panel leans on telling them apart. */
	ok(!frontdoor_is_discord('Mozilla/5.0 (compatible; Nmap)'), 'a scanner reads as Discord');
	ok(!frontdoor_is_discord(''), 'an empty agent reads as Discord');
}

echo "\nthe status codes PHP never sees are the ones called out\n";
{
	/*
	 * 200 AND 401 ARE THE ENDPOINT ANSWERING. Everything else was decided
	 * ABOVE PHP, and those are precisely the ones that look like "the
	 * application didn't respond" with an empty interaction log behind them
	 * -- the combination that has had this bug hidden for two weeks.
	 */
	list($st,) = frontdoor_status_say(200); ok($st === 'ok',  '200 is not reported as answered');
	list($st,) = frontdoor_status_say(401); ok($st === 'ok',  '401 is not reported as ours');

	foreach (array(403, 406, 503, 508, 500) as $code) {
		list($st, $say) = frontdoor_status_say($code);
		ok($st === 'bad', "$code is not flagged, so a request PHP never ran reads as healthy");
		ok(trim($say) !== '', "$code has no explanation beside it");
	}
	list($st, $say) = frontdoor_status_say(403);
	ok(stripos($say, 'modsecurity') !== false || stripos($say, 'firewall') !== false,
	   'a 403 does not name where to look for it');
	list($st, $say) = frontdoor_status_say(503);
	ok(stripos($say, 'resource') !== false,
	   'a 503 does not name the resource limit, which is the only thing it means here');
}

echo "\nthe log tail is seeked, not swallowed\n";
{
	/*
	 * AN ACCESS LOG ON A LIVE DOMAIN IS TENS OF MEGABYTES. Reading one whole
	 * inside a page request is how a diagnostic becomes the outage it was
	 * opened to investigate.
	 */
	$big = sys_get_temp_dir() . '/sk-fd-tail-' . getmypid() . '.log';
	$line = str_repeat('x', 199) . "\n";
	file_put_contents($big, str_repeat($line, 300));      // ~60KB
	$tail = frontdoor_tail($big, 4096);
	ok(strlen($tail) <= 4096, 'the tail returned ' . strlen($tail) . ' bytes for a 4096 cap');
	/* AND IT STARTS ON A LINE BOUNDARY: seeking into the middle of one
	   leaves a fragment that parses as nothing, or worse, as something. */
	ok($tail !== '' && substr($tail, 0, 1) === 'x' && strlen(explode("\n", $tail)[0]) === 199,
	   'the tail begins mid-line, so the oldest entry is a fragment');
	ok(substr($tail, -1) === "\n", 'the tail does not end where the file does');

	/* A file smaller than the window comes back whole, head included. */
	file_put_contents($big, "first\nsecond\n");
	ok(frontdoor_tail($big, 4096) === "first\nsecond\n",
	   'a short file is being trimmed as though it were long');
	ok(frontdoor_tail($big . '-missing', 4096) === '', 'a missing file returns something');
	@unlink($big);
}

echo "\nDiscord's stored endpoint url is judged by host, not by string\n";
{
	$host = 'skulliance.io';
	$path = '/staking/discord-interactions.php';

	list($st,) = frontdoor_app_url_verdict('https://skulliance.io/staking/discord-interactions.php', $host, $path);
	ok($st === 'ok', "the correct url is judged '$st'");

	/*
	 * EMPTY IS THE ONE THAT MATTERS MOST. Discord CLEARS this field itself
	 * when an endpoint fails often enough, and this one was returning
	 * corrupted bodies for an afternoon -- so an empty value here is not a
	 * configuration oversight, it is Discord having switched us off, and
	 * the Developer Portal's form can still look populated.
	 */
	list($st, $say) = frontdoor_app_url_verdict('', $host, $path);
	ok($st === 'bad', 'an empty stored url is not flagged');
	ok(stripos($say, 'clears') !== false || stripos($say, 'empty') !== false,
	   'the empty verdict does not say what an empty value means');

	/*
	 * www AND THE APEX ARE DIFFERENT ORIGINS HERE, one of them redirects,
	 * and DISCORD DOES NOT FOLLOW A REDIRECT for an interaction -- it takes
	 * the 3xx as the answer. A string comparison would call this a
	 * mismatch too, but so would a trailing slash, and only one of those is
	 * worth waking somebody up for.
	 */
	list($st,) = frontdoor_app_url_verdict('https://www.skulliance.io/staking/discord-interactions.php', $host, $path);
	ok($st === 'bad', "a url on the www host reads as '$st' when we are on the apex");

	/* Cosmetic differences are not alarms. */
	list($st,) = frontdoor_app_url_verdict('https://SKULLIANCE.IO/staking/discord-interactions.php', $host, $path);
	ok($st === 'ok', 'a difference in letter case is reported as a mismatch');
	list($st,) = frontdoor_app_url_verdict('https://skulliance.io/staking/discord-interactions.php/', $host, $path);
	ok($st === 'ok', 'a trailing slash is reported as a mismatch');

	/* A different path on the right host is a different copy of the
	   endpoint, which is worth saying but is not the same failure. */
	list($st,) = frontdoor_app_url_verdict('https://skulliance.io/old/discord-interactions.php', $host, $path);
	ok($st === 'warn', "another path on this host reads as '$st'");

	list($st,) = frontdoor_app_url_verdict('not a url', $host, $path);
	ok($st === 'bad', 'a stored value that is not a url is accepted');
}

echo "\nthe log files are found by the names cPanel actually uses\n";
{
	/*
	 * THE BUG THIS REPLACES. The first version looked for
	 * ~/access-logs/<domain> and <domain>-ssl_log and found neither,
	 * because cPanel names them after the domain AND the account's main
	 * domain:  skulliance.io.jeremiahbarber.com-ssl_log
	 *
	 * The page reported "no readable access log" on an account holding
	 * 5.3MB of exactly that log -- and "no log to read" versus "no
	 * requests" are opposite findings, which is the one confusion this
	 * whole panel exists to prevent.
	 */
	$home = sys_get_temp_dir() . '/sk-fd-home-' . getmypid();
	@mkdir($home . '/access-logs', 0777, true);
	@mkdir($home . '/logs', 0777, true);
	$put = function ($rel) use ($home) { file_put_contents($home . $rel, "x\n"); };
	$put('/access-logs/skulliance.io.jeremiahbarber.com');
	$put('/access-logs/skulliance.io.jeremiahbarber.com-ssl_log');
	$put('/access-logs/madballs.jeremiahbarber.com-ssl_log');
	$put('/logs/skulliance.io.jeremiahbarber.com-Sep-2026.gz');

	$keep = isset($_SERVER['HOME']) ? $_SERVER['HOME'] : null;
	$_SERVER['HOME'] = $home;
	$found = frontdoor_access_paths('skulliance.io');
	$base  = array_map('basename', $found);
	sort($base);

	ok(in_array('skulliance.io.jeremiahbarber.com-ssl_log', $base, true),
	   'the https access log is not found under the name cPanel gives it: '
	 . implode(', ', $base));
	ok(in_array('skulliance.io.jeremiahbarber.com', $base, true),
	   'the plain-http access log is not found');
	/* ANOTHER DOMAIN ON THE SAME ACCOUNT IS NOT THIS DOMAIN. A prefix match
	   that is too loose puts somebody else's traffic in this table. */
	ok(!in_array('madballs.jeremiahbarber.com-ssl_log', $base, true),
	   'another domain on the account is being read as this one');
	/* A gzipped archive cannot be tailed as text, so including it would
	   produce a screen of binary rather than an older entry. */
	ok(!in_array('skulliance.io.jeremiahbarber.com-Sep-2026.gz', $base, true),
	   'a gzipped archive is being read as a plain log');

	if ($keep === null) unset($_SERVER['HOME']); else $_SERVER['HOME'] = $keep;
	foreach (glob($home . '/*/*') as $f) @unlink($f);
	@rmdir($home . '/access-logs'); @rmdir($home . '/logs'); @rmdir($home);
}

echo "\nthe access panel says so when it cannot read anything\n";
{
	/* SILENCE MUST NOT READ AS EVIDENCE. "No requests" and "no log to look
	   at" are opposite answers, and conflating them is the same mistake the
	   interaction log was built to stop making. */
	$r = frontdoor_access_hits(array(), 'discord-interactions.php');
	ok($r['ok'] === false, 'with no readable log the panel reports success');
	ok(stripos($r['error'], 'cpanel') !== false,
	   'the no-log message does not say where to look by hand instead');

	$f = sys_get_temp_dir() . '/sk-fd-acc-' . getmypid() . '.log';
	file_put_contents($f,
		'1.1.1.1 - - [31/Oct/2026:01:00:00 -0500] "POST /staking/discord-interactions.php HTTP/1.1" 403 1 "-" "Discord-Interactions/1.0"' . "\n" .
		'2.2.2.2 - - [31/Oct/2026:01:00:05 -0500] "GET /staking/index.php HTTP/1.1" 200 1 "-" "Mozilla"' . "\n" .
		'3.3.3.3 - - [31/Oct/2026:01:00:09 -0500] "POST /staking/discord-interactions.php HTTP/1.1" 200 1 "-" "Discord-Interactions/1.0"' . "\n");
	$r = frontdoor_access_hits(array($f), 'discord-interactions.php');
	ok($r['ok'] === true, 'a readable log reports failure');
	ok(count($r['hits']) === 2, 'the needle matched ' . count($r['hits']) . ' lines, not 2');
	/* Newest first, for the same reason the interaction log is: the line
	   somebody needs is the one from the click a minute ago. */
	ok($r['hits'][0]['status'] === 200, 'the access hits are not newest first');
	@unlink($f);
}

echo "\nthe diagnostic is read only, and is not a public page\n";
{
	$page = (string)file_get_contents(__DIR__ . '/admin-frontdoor.php');
	$lib  = (string)file_get_contents(__DIR__ . '/lib/frontdoor-lib.php');
	$adm  = harness_strip_comments((string)file_get_contents(__DIR__ . '/admin-lib.php'));

	/*
	 * A DIAGNOSTIC THAT DELETES THINGS IS ONE NOBODY DARES RUN -- and the
	 * thing it would be deleting is a message in a public channel. Removing
	 * a stale gate message is one tap in Discord; it does not need to be
	 * here, and having it here means every future reader has to check.
	 */
	foreach (array('POST', 'PATCH', 'PUT', 'DELETE') as $verb) {
		ok(strpos($page, "sk_discord_api('" . $verb) === false,
		   "admin-frontdoor.php makes a $verb call to Discord; it is supposed to be read only");
		ok(strpos($lib, "sk_discord_api('" . $verb) === false,
		   "lib/frontdoor-lib.php makes a $verb call to Discord; it is supposed to be read only");
	}

	/* The gate, and BEFORE header.php -- admin_require() exits, and an exit
	   after output has already drawn the page around the refusal. */
	$g = strpos($page, 'admin_require()');
	$h = strpos($page, "include 'header.php'");
	ok($g !== false && $h !== false && $g < $h,
	   'admin-frontdoor.php does not gate before it starts drawing');

	/* The self-probe fetches this host from this host; on page load that is
	   a page anybody can make hammer the server by holding refresh. */
	ok(strpos($page, "isset(\$_POST['fd_probe'])") !== false,
	   'the endpoint probe is not behind a POST, so it runs on every page load');
	/* POST field names share a namespace with skulliance.php's wallet
	   refresh, which cleared the owner's NFTs once already. */
	ok(strpos($page, "name=\"refresh\"") === false && strpos($page, "'refresh'") === false,
	   'a form field here is called "refresh", which is skulliance.php\'s wallet refresh');

	/* And it is reachable: a page nobody can find is not a tool. */
	ok(strpos($adm, "'admin-frontdoor.php'") !== false,
	   'admin-frontdoor.php is not in admin_tabs(), so nothing links to it');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
