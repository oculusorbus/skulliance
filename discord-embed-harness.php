<?php
/* discord-embed-harness.php - CLI only. No database, no network.
 *
 * The monthly payouts ran on 2026-10-01 -- 208 CARBON credits, 5,086,926
 * paid, every reward flag closed -- and were reported as "the monthly script
 * didn't run", because not one announcement reached Discord. Two separate
 * defects, and the second is what made the first undiagnosable:
 *
 *   1. A leaderboard post lists up to 45 ranked players at ~107 characters
 *      each. That is 4,815 against Discord's 4,096 embed-description limit,
 *      and over a limit Discord rejects the WHOLE post -- there is no partial
 *      embed and no plain-text fallback. A board stops announcing itself the
 *      month it passes 38 ranked players.
 *   2. discordmsg() read curl's response into $response and never looked at
 *      it. 400, 401, 404, 429 and success were indistinguishable from inside
 *      the platform, and the cron runner swallows stdout on top of that.
 *
 * Usage: php discord-embed-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

function no_comments($s) {
	$s = preg_replace('!/\*.*?\*/!s', '', $s);
	$s = preg_replace('~(?<![:/])//.*$~m', '', $s);
	return $s;
}

$src = file_get_contents(__DIR__ . '/webhooks.php');

/* The real helper and the real constants, lifted out of webhooks.php. */
foreach (array('SKL_EMBED_TITLE_MAX', 'SKL_EMBED_DESC_MAX', 'SKL_EMBED_FOOTER_MAX', 'SKL_EMBED_TOTAL_MAX') as $c) {
	if (preg_match("/define\('" . $c . "',\s*(\d+)\)/", $src, $m)) define($c, (int)$m[1]);
}
$at  = strpos($src, 'function skl_embed_trim(');
ok($at !== false, 'skl_embed_trim() is gone from webhooks.php');
$end = strpos($src, "\n    }", $at);
eval(substr($src, $at, $end - $at + 6));

echo "Discord's documented limits\n";
ok(defined('SKL_EMBED_DESC_MAX')  && SKL_EMBED_DESC_MAX  === 4096, 'the description limit is no longer 4096');
ok(defined('SKL_EMBED_TITLE_MAX') && SKL_EMBED_TITLE_MAX === 256,  'the title limit is no longer 256');
ok(defined('SKL_EMBED_TOTAL_MAX') && SKL_EMBED_TOTAL_MAX === 6000, 'the total-embed limit is no longer 6000');

/* ---------------------------------------------------------------- *
 * The real post that was being rejected.
 * ---------------------------------------------------------------- */
echo "\nthe 45-player leaderboard post that was being rejected\n";
$entry = "- %s <@123456789012345678> Wins: 12, Best Depth: 10/12, Losses: 3\r\n"
       . "        119,047 CARBON = 1,190 DIAMOND\r\n";
$desc = '';
for ($i = 1; $i <= 45; $i++) $desc .= sprintf($entry, str_pad($i, 2, '0', STR_PAD_LEFT));
printf("  raw: %d chars, limit %d\n", mb_strlen($desc), SKL_EMBED_DESC_MAX);
ok(mb_strlen($desc) > SKL_EMBED_DESC_MAX,
   'the fixture no longer overflows, so it is not testing the bug any more');

$trimmed = skl_embed_trim($desc, SKL_EMBED_DESC_MAX);
printf("  trimmed: %d chars\n", mb_strlen($trimmed));
ok(mb_strlen($trimmed) <= SKL_EMBED_DESC_MAX, 'the trimmed description is STILL over the limit: ' . mb_strlen($trimmed));
ok(strpos($trimmed, 'truncated') !== false, 'a truncated list does not say it was truncated');
/* Cut on a line boundary: the last thing before the note should be a whole
   entry, not half of someone's rank line. */
$before = trim(substr($trimmed, 0, strrpos($trimmed, '…')));
ok(substr($before, -7) === 'DIAMOND',
   'the list was cut mid-entry; it should end on a whole line, ends with: "' . substr($before, -30) . '"');
ok(strpos($trimmed, '- 01 ') === 0, 'the top of the board was trimmed instead of the bottom');

echo "\nshort posts are left alone\n";
$short = "- 01 <@1> Wins: 3\r\n";
ok(skl_embed_trim($short, SKL_EMBED_DESC_MAX) === $short, 'a post well under the limit was altered');
ok(skl_embed_trim('', SKL_EMBED_DESC_MAX) === '', 'an empty description was altered');
$exact = str_repeat('a', SKL_EMBED_DESC_MAX);
ok(skl_embed_trim($exact, SKL_EMBED_DESC_MAX) === $exact, 'a description exactly at the limit was trimmed');
ok(mb_strlen(skl_embed_trim($exact . 'b', SKL_EMBED_DESC_MAX)) <= SKL_EMBED_DESC_MAX, 'one character over was not brought back under');
/* A single enormous paragraph has no newline to snap to; it must still fit. */
$para = str_repeat('word ', 2000);
ok(mb_strlen(skl_embed_trim($para, SKL_EMBED_DESC_MAX)) <= SKL_EMBED_DESC_MAX, 'a newline-free description was not trimmed to fit');
/*
 * MULTI-BYTE. Discord counts CHARACTERS; strlen counts bytes. 3,000 skulls is
 * 3,000 characters and 12,000 bytes -- comfortably inside the limit, and a
 * byte-based check would trim it to a quarter of itself.
 *
 * Asserting "the result is under the limit" does NOT catch this: cutting by
 * bytes produces a SHORTER string, which passes that check while mangling the
 * post. An earlier version of this harness did exactly that and the mutation
 * survived. What has to be asserted is that it is left ALONE, and that
 * whatever does come back is still valid UTF-8.
 */
$emoji = str_repeat('💀', 3000);
ok(mb_strlen($emoji) < SKL_EMBED_DESC_MAX && strlen($emoji) > SKL_EMBED_DESC_MAX,
   'the multi-byte fixture no longer straddles the byte/character boundary, so it tests nothing');
ok(skl_embed_trim($emoji, SKL_EMBED_DESC_MAX) === $emoji,
   'an emoji description inside the CHARACTER limit was trimmed anyway -- measured in bytes');
$long_emoji = skl_embed_trim(str_repeat('💀', 9000), SKL_EMBED_DESC_MAX);
ok(mb_strlen($long_emoji) <= SKL_EMBED_DESC_MAX, 'an over-long emoji description was not brought under the limit');
ok(mb_check_encoding($long_emoji, 'UTF-8'), 'trimming split a multi-byte character and produced invalid UTF-8');

/* ---------------------------------------------------------------- *
 * discordmsg() applies it, and looks at what Discord said.
 * ---------------------------------------------------------------- */
echo "\ndiscordmsg applies the limits and reads the response\n";
$clean = no_comments($src);
ok(preg_match('/\$description\s*=\s*skl_embed_trim\(\$description,\s*SKL_EMBED_DESC_MAX\)/', $clean) === 1,
   'discordmsg no longer trims the description, so a long board is rejected whole again');
ok(preg_match('/\$title\s*=\s*skl_embed_trim\(\$title,\s*SKL_EMBED_TITLE_MAX\)/', $clean) === 1,
   'discordmsg no longer trims the title');
ok(strpos($clean, 'SKL_EMBED_TOTAL_MAX - $other') !== false,
   'discordmsg no longer budgets the description against the 6000 total');

ok(strpos($clean, 'CURLINFO_RESPONSE_CODE') !== false,
   'discordmsg discards the response again -- every failure looks like success');
ok(preg_match('/\$status\s*<\s*200\s*\|\|\s*\$status\s*>=\s*300/', $clean) === 1,
   'discordmsg no longer tests the status; note Discord answers 204, so a 2xx RANGE is required, not == 200');
ok(strpos($clean, 'error_log("discordmsg:') !== false, 'a rejected post is no longer logged');
ok(strpos($clean, 'alertAdmin(') !== false, 'a rejected post no longer raises an admin alert');

/* THE RECURSION. alertAdmin() reports by calling discordmsg(). Without the
   guard, a dead default webhook means the alert fails, which alerts, which
   fails. The guard must be STATIC -- a local would reset on each call and
   guard nothing. */
ok(preg_match('/static\s+\$skl_alerting\s*=\s*false/', $clean) === 1,
   'the alert re-entry guard is gone or is no longer static; a dead default webhook will now recurse');
ok(preg_match('/if\s*\(!\$skl_alerting\s*&&/', $clean) === 1,
   'the guard is declared but not checked before alerting');
ok(preg_match('/\$skl_alerting\s*=\s*true;.*?alertAdmin\(.*?\$skl_alerting\s*=\s*false;/s', $clean) === 1,
   'the guard is not released after alerting, so only one failure is ever reported per request');

/* alertAdmin really does call back into discordmsg -- if that ever stops
   being true the guard is pointless and this check should go with it. */
$aa_at = strpos($clean, 'function alertAdmin(');
ok($aa_at !== false && strpos($clean, 'discordmsg(', $aa_at) !== false,
   'alertAdmin no longer calls discordmsg; the re-entry guard is now dead weight');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
