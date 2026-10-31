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
