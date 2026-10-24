<?php
/**
 * credentials-harness.php — a page that loads a secret must not print.
 *
 * Run: php credentials-harness.php
 *
 * THIS HAS HAPPENED TWICE. First a curl debug trace in message.php wrote
 * the Discord BOT TOKEN into request.txt, served publicly for months.
 * Then process-oauth.php was found opening with print_r($payload) --
 * the Discord token exchange, which carries client_secret -- with
 * display_errors forced on three lines above it, in the callback every
 * single login passes through.
 *
 * Both were leftover debugging in a path nobody re-reads. Neither threw,
 * neither logged, and the second was invisible because php.ini happened
 * to have output_buffering on and was swallowing the body.
 *
 * So this is a SWEEP, not a list: every file that loads a credentials
 * file is checked for a live output statement. A new one cannot quietly
 * join them.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/harness-strip.php';

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok    $what\n"; return true; }
	$fail++; echo "  FAIL  $what\n"; return false;
}

/**
 * Output statements that are actually reachable.
 *
 * Comments are stripped first -- this file would otherwise flag the
 * commented-out addUserToGuild() in process-oauth.php, which has an
 * echo in it and has been dead for years. Echoing inside a STRING is
 * not output either, but nothing here does that and a false positive
 * is cheap to inspect.
 */
function live_output($src) {
	$clean = harness_strip_comments($src);
	preg_match_all('~^.*\b(echo|print_r|var_dump|printf)\b.*$~m', $clean, $m);
	$out = array();
	foreach ($m[0] as $line) {
		$t = trim($line);
		/* A function DEFINITION named like one of these is not a call. */
		if (strpos($t, 'function ') === 0) continue;
		$out[] = $t;
	}
	return $out;
}

/* ---------------------------------------------------------------- *
 * The OAuth callback specifically: it handles the token exchange.
 * ---------------------------------------------------------------- */
echo "the Discord OAuth callback stays silent\n";
$po = file_get_contents(__DIR__ . '/process-oauth.php');
$live = live_output($po);
ok(!$live, 'process-oauth.php prints ' . count($live) . ' time(s): '
         . implode(' | ', array_slice($live, 0, 3)));
/*
 * AND IT MUST NOT TURN ERRORS INTO PAGE CONTENT. display_errors on, in
 * a file holding a client secret, means any notice anywhere in it or
 * its includes becomes part of the response.
 */
ok(preg_match("~ini_set\('display_errors',\s*1\)~", $po) !== 1,
   'display_errors is forced ON in the OAuth callback');
ok(preg_match("~ini_set\('display_errors',\s*0\)~", $po) === 1,
   'display_errors is not explicitly turned off in the OAuth callback');
/*
 * The page ends in a redirect, so stray output breaks the login AND
 * exposes whatever was printed. Its own buffer, not the server's:
 * output_buffering is a php.ini setting somebody can change from a
 * cPanel dropdown, and relying on it is how the print_r stayed
 * invisible.
 */
$ob  = strpos($po, 'ob_start()');
$clr = strpos($po, 'ob_end_clean()');
$hdr = strpos($po, 'header("location: launchpad.php")');
ok($ob !== false, 'the callback does not buffer its own output');
ok($clr !== false && $hdr !== false && $clr < $hdr,
   'the buffer is not discarded before the redirect, so a stray notice still breaks it');

/* ---------------------------------------------------------------- *
 * And the sweep: anything that loads a credentials file.
 * ---------------------------------------------------------------- */
echo "\nnothing that loads a credential dumps a variable\n";
/*
 * NOT "does this file echo". The first version of this swept for any
 * output statement and needed a skip list nineteen files long, because
 * db.php and webhooks.php render markup by the yard -- and a sweep that
 * excludes almost everything checks almost nothing. It passed one file:
 * the one I had just fixed by hand.
 *
 * So it looks for the two shapes that are never deliberate in a page
 * holding a secret:
 *
 *   print_r / var_dump / var_export of anything. These are debugging.
 *   Both real incidents here were exactly this, left behind.
 *
 *   echo or print of a variable NAMED like a credential. $payload is on
 *   the list because that is the one that bit, and it reads as harmless
 *   until you remember what a token exchange carries.
 *
 * No skip list, so every file that loads a credential is actually
 * checked, including the big ones.
 */
/*
 * print_r($x, true) and var_export($x, true) RETURN a string and print
 * nothing -- that is the correct way to get a dump into error_log, and
 * flagging it would push people back to the unsafe form to keep this
 * green. Only the printing shapes count; var_dump always prints.
 */
$dumpers = '~\\b(print_r|var_dump|var_export)\\s*\\([^;]*\\)\\s*;~';
$secrets = '~\b(echo|print)\s+\$(\w*(token|secret|password|passwd|payload|credential|client_id|api_key)\w*)~i';
$checked = 0; $hits = array();
$files = array_merge(glob(__DIR__ . '/*.php'), glob(__DIR__ . '/ajax/*.php'),
                     glob(__DIR__ . '/lib/*.php'), glob(__DIR__ . '/dropship/*.php'));
foreach ($files as $f) {
	$rel = ltrim(str_replace(__DIR__, '', $f), '/');
	if (strpos($rel, 'harness') !== false) continue;
	$src = file_get_contents($f);
	if (strpos($src, 'credentials/') === false) continue;
	$checked++;
	$clean = harness_strip_comments($src);
	if (preg_match_all($dumpers, $clean, $m)) {
		foreach ($m[0] as $call) {
			$call = trim($call);
			/* The return-mode second argument makes it a string builder.
			   Matched as a substring, not anchored to the end: these
			   calls are usually nested inside error_log(), so the line
			   ends in a run of closing parens rather than in `, true)`. */
			if (strpos($call, ', true') !== false || strpos($call, ',true') !== false) continue;
			$hits[] = $rel . ': ' . (strlen($call) > 60 ? substr($call, 0, 60) . '...' : $call);
			break;
		}
	}
	if (preg_match($secrets, $clean, $m2))    $hits[] = $rel . ': echo $' . $m2[2];
}
ok($checked >= 10, "only $checked files load a credential, which looks like the sweep stopped finding them");
ok(!$hits, 'a file holding a credential dumps a variable: ' . implode(' | ', array_slice($hits, 0, 4)));
printf("  %d file(s) swept\n", $checked);

/* ---------------------------------------------------------------- *
 * The directory itself.
 * ---------------------------------------------------------------- */
echo "\nthe credentials directory cannot be committed by accident\n";
/*
 * It was merely UNTRACKED, not ignored. That is one `git add -A` away
 * from a repo full of secrets -- which is exactly how a stray mp3 got
 * committed and broke git pull on the live server.
 */
$gi = file_get_contents(__DIR__ . '/.gitignore');
ok(preg_match('~^/?credentials/~m', $gi) === 1,
   'credentials/ is not in .gitignore, so one `git add -A` commits every secret on the platform');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all credential checks passed\n");
exit($fail ? 1 : 0);
