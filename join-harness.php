<?php
/* join-harness.php — CLI only. Static. No database.
 *
 *     php join-harness.php
 *
 * THE RULES QUIZ ON THE WEB, AND THE THINGS THAT MAKE IT A SECOND FRONT
 * DOOR RATHER THAN A SECOND RULEBOOK.
 *
 * It exists because the Discord button flow has to answer inside a 3000ms
 * interaction deadline and people were bouncing off "the application did
 * not respond in time". A web page has no deadline -- so most of what
 * matters here is that it stays PUBLIC, stays JS-free, and keeps reading
 * its questions and roles from the same file the bot reads.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$src = (string) file_get_contents(__DIR__ . '/join.php');
$oa  = (string) file_get_contents(__DIR__ . '/process-oauth.php');
/* The prose describes every trap below; a raw search reads the comments. */
$code = preg_replace('~/\*.*?\*/~s', '', preg_replace('~^\s*//.*$~m', '', $src));

echo "it is reachable by somebody with no account\n";
/*
 * THE WHOLE POINT. These are people arriving from a partner project who
 * have never seen Skulliance. A login gate on this page is the same closed
 * door in a different colour.
 */
foreach (array('skulliance.php', 'verify.php') as $gate)
	ok(preg_match('~(require|include)[^\n;]*[\'"]/?' . preg_quote($gate, '~') . '~', $code) !== 1,
	   "join.php includes $gate, which gates the page the un-onboarded are sent to");
/* It still needs the bot token and role ids, which is what this pulls in --
   the same include discord-interactions.php (also public) uses. */
ok(strpos($code, "discord-rules-lib.php") !== false,
   'join.php does not load the rules library, so it has no roles or token');

echo "\nand it runs without javascript\n";
/* Most arrivals open this inside Discord's in-app browser. Every step is a
   form POST for that reason. */
ok(strpos($src, '<script') === false,
   'join.php has grown a <script>; the flow must survive Discord\'s in-app browser');
ok(substr_count($src, '<form method="post">') >= 3,
   'the steps are no longer plain form posts');

echo "\nthe questions are the bot's questions\n";
/*
 * NOT COPIED. Two rulebooks drifting apart is how the answer that passes in
 * Discord fails on the site. The page must read $SK_CHAINS and grade with
 * sk_is_correct() rather than carry its own.
 */
ok(strpos($code, '$SK_CHAINS') !== false,
   'join.php does not read $SK_CHAINS, so it has its own copy of the questions');
ok(strpos($code, 'sk_is_correct(') !== false,
   'join.php grades answers itself instead of calling sk_is_correct()');
foreach (array('Hoskinson', 'Buterin', 'native token') as $leak)
	ok(strpos($src, $leak) === false,
	   "join.php contains the literal \"$leak\" -- the questions have been copied in");
/*
 * SHUFFLED, like sk_question_payload() does. The options are authored
 * correct-answer-first, so a fixed order puts the right answer at the top
 * of every question and the quiz tests nothing at all.
 */
ok(preg_match('~shuffle\(\$opt_keys\)~', $code) === 1,
   'the options are not shuffled, so the correct answer is first every time');

echo "\nthe roles are set in the order that cannot strand anybody\n";
/* Add Candidate first, then remove Visitor: if the second call fails they
   hold both, which is odd but harmless. The other order leaves them with
   nothing. Same reasoning as discord-interactions.php. */
$addAt = strpos($code, "assignRole(\$skj_discord_id, \$role_candidate");
$delAt = strpos($code, "assignRole(\$skj_discord_id, \$role_visitor, 'delete'");
ok($addAt !== false, 'the Candidate role is never granted');
ok($delAt !== false, 'the Visitor role is never removed');
ok($addAt !== false && $delAt !== false && $addAt < $delAt,
   'Visitor is removed before Candidate is granted, so a failure strands them with no role');
/* And a failed grant must not remove Visitor -- leaving them exactly as
   they were is what makes "try again" work. */
ok(preg_match('~if \(!\$granted\) \{\s*\$skj_step\s*=\s*.rolefail.;\s*\} else \{~s', $code) === 1,
   'a failed role grant no longer leaves the member untouched, so retrying cannot work');

echo "\n\"not a member\" and \"could not tell\" stay different answers\n";
/*
 * Telling somebody who IS in the server to go and join it is exactly the
 * dead end this page exists to remove, so the lookup returns three things:
 * the member, false for a definite 404, null for "we could not tell".
 */
ok(preg_match('~if \(\$code === 404\) return false;~', $code) === 1,
   'the member lookup does not treat 404 as a definite "not a member"');
ok(preg_match('~\$skj_member === null~', $code) === 1
   && preg_match('~\$skj_member === false~', $code) === 1,
   'the page collapses "could not check" into "not a member"');
/* Identity, not truthiness: an empty member array is falsy and would be
   read as a 404. */
ok(strpos($code, '$skj_member == false') === false,
   'the member check uses loose comparison, so an empty response reads as not-a-member');
/* No 3-second budget here, and that is the point -- but not unbounded. */
ok(preg_match('~CURLOPT_TIMEOUT\s*=>\s*\d+~', $code) === 1,
   'the member lookup has no timeout, so a hung Discord hangs the page');

echo "\nthe login round trip cannot be used as an open redirect\n";
/*
 * join.php asks process-oauth.php to send people back. That value decides
 * where a freshly authenticated session lands, so it is matched against a
 * FIXED LIST -- never sanitised and used.
 */
ok(strpos($oa, "\$_SESSION['skl_after_login']") !== false,
   'process-oauth.php no longer honours the return, so the quiz drops people on the Launchpad');
ok(preg_match('~in_array\(\$po_after, \$po_allowed, true\)~', $oa) === 1,
   'the post-login redirect is not whitelisted -- that is an open redirect on a fresh session');
ok(preg_match('~\$po_allowed = array\(\'join\.php\'\);~', $oa) === 1,
   'the redirect whitelist has changed; every entry here lands an authenticated session');
/* Consumed either way, so a stale value cannot redirect a later login. */
ok(preg_match('~unset\(\$_SESSION\[.skl_after_login.\]\);~', $oa) === 1,
   'the return destination is not cleared, so it fires again on an unrelated login');
ok(strpos($oa, 'launchpad.php') !== false,
   'the default landing is no longer the Launchpad');

echo "\nnothing user-supplied reaches the page unescaped\n";
ok(strpos($code, '<?php echo $skj_name') === false
   && strpos($src, 'htmlspecialchars($skj_name') !== false,
   'the Discord display name is printed unescaped');
ok(preg_match('~echo \$q\[.prompt.\]~', $code) !== 1,
   'a question prompt is printed unescaped');

echo "\nthe url unfurls as a card, not a bare blue string\n";
/*
 * THE CARD IS THE REASSURANCE. This link is posted into a channel full
 * of people who have been told since forever not to click links in
 * Discord. Unfurled it carries the Skulliance name, mark and teal
 * stripe; bare it is a string they are asked to trust.
 */
foreach (array('og:site_name', 'og:type', 'og:url', 'og:title', 'og:description', 'og:image') as $tag)
	ok(preg_match('~<meta property="' . preg_quote($tag, '~') . '"\s+content="[^"]+"~', $src) === 1,
	   "join.php has no $tag, so the link unfurls as a bare URL");
/* ABSOLUTE, both of them. An unfurler has no page to resolve a relative
   URL against, so a root-relative path silently yields no image. */
foreach (array('og:url', 'og:image') as $tag) {
	preg_match('~<meta property="' . preg_quote($tag, '~') . '"\s+content="([^"]+)"~', $src, $m);
	ok(isset($m[1]) && strpos($m[1], 'https://') === 0,
	   "$tag is not an absolute https URL, so it will not render");
}
/* Discord paints the card's left stripe with this. */
ok(preg_match('~<meta name="theme-color"\s+content="#00c8a0"~', $src) === 1,
   'the card has no brand stripe colour');
/*
 * AND NO noindex. Discord's unfurler goes by robots.txt rather than the
 * meta tag, so this is belt and braces -- but the entire point of the
 * page is that the card renders, and nothing is gained by risking it.
 */
ok(preg_match('~<meta name="robots"[^>]*noindex~', $src) !== 1,
   'join.php is marked noindex, which risks suppressing the very card it needs');

echo "\nand the button and the card point at the same place\n";
/*
 * Two files name this URL: the rules message's link button, and the
 * page's own og:url. If they drift, the card describes one page and the
 * button opens another.
 */
$lib = (string) file_get_contents(__DIR__ . '/discord-rules-lib.php');
preg_match("~define\('SK_WEB_JOIN_URL',\s*'([^']+)'\)~", $lib, $bm);
preg_match('~<meta property="og:url"\s+content="([^"]+)"~', $src, $om);
ok(isset($bm[1]), 'the rules library no longer defines SK_WEB_JOIN_URL');
ok(isset($bm[1]) && isset($om[1]) && rtrim($bm[1], '/') === rtrim($om[1], '/'),
   'the rules button and join.php\'s og:url disagree about where this page is');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all join checks passed\n");
exit($fail ? 1 : 0);
