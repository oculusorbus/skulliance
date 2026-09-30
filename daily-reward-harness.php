<?php
/* daily-reward-harness.php — CLI only. No database.
 *
 * The daily reward claim, after the Discord post was moved out of it.
 *
 * WHAT WENT WRONG. discordmsg() is a synchronous curl POST with an 8 second
 * timeout and it sat inside getRandomReward(), between the player pressing
 * Claim and the reply that redraws the strip. Every claim waited on a third
 * party; a slow minute at Discord's end was a claim that looked like it had
 * hung. Reported as "a bit slow, and the button doesn't say it is working".
 *
 * WHAT IS CHECKED, and how honestly:
 *
 *  1. dailyRewardAnnounce() builds the right message from the four facts it
 *     is handed and refuses a day outside 1..7. REAL: the function is
 *     brace-extracted from db.php and run against a stub $conn and a stub
 *     discordmsg() that records what it was asked to post.
 *
 *  2. The claim no longer posts to Discord, and leaves a marker instead.
 *     STRUCTURAL: getRandomReward() cannot be run here -- it needs a live
 *     connection, a session and the whole of db.php -- so this reads its
 *     body. That is weaker than a behavioural test and it is the regression
 *     that matters most: putting one discordmsg() call back restores the
 *     wait with no other symptom.
 *
 *  3. The announce endpoint clears the marker BEFORE it posts, not after.
 *     STRUCTURAL, by source order. An 8 second curl is exactly the window in
 *     which a second call arrives, and clearing afterwards announces twice.
 *
 *  4. The button says it is working, refuses a second press, and comes back
 *     on every failure path. STRUCTURAL on skulliance.js.
 *
 * Usage: php daily-reward-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/**
 * PHP source with every comment removed and every string literal blanked.
 *
 * Both matter, and the first one bit immediately: the explanation of WHY the
 * Discord post was moved out of getRandomReward() contains the words
 * "discordmsg()", so a plain strpos() for it found the comment and reported
 * the bug as un-fixed. Blanking strings protects the brace matching below
 * from a '{' inside an SQL fragment.
 */
function php_strip($src, $blank_strings = true) {
	$out = '';
	foreach (token_get_all($src) as $t) {
		if (is_string($t)) { $out .= $t; continue; }
		switch ($t[0]) {
			case T_COMMENT: case T_DOC_COMMENT:
				$out .= str_repeat("\n", substr_count($t[1], "\n")); break;
			case T_CONSTANT_ENCAPSED_STRING:
				$out .= $blank_strings ? "''" : $t[1]; break;
			case T_ENCAPSED_AND_WHITESPACE:
				$out .= $blank_strings ? ' ' : $t[1]; break;
			default:
				$out .= $t[1];
		}
	}
	return $out;
}

function extract_fn($src, $name) {
	$at = strpos($src, 'function ' . $name . '(');
	if ($at === false) return '';
	$open = strpos($src, '{', $at);
	$d = 0;
	for ($i = $open, $n = strlen($src); $i < $n; $i++) {
		if ($src[$i] === '{') $d++;
		elseif ($src[$i] === '}') { $d--; if ($d === 0) return substr($src, $at, $i - $at + 1); }
	}
	return '';
}

$db = file_get_contents(__DIR__ . '/db.php');
/* Comment- and string-free copies for every structural check below. The
   originals are still used where the text itself is the point. */
$dbCode = php_strip($db);
/* Comments gone, strings KEPT. Blanking the strings turns $_SESSION['dr_announce']
   into $_SESSION[''], which erases the very key the marker checks look for. */
$dbText = php_strip($db, false);

/* ---------- 1. the announcer, actually run --------------------------------- */
echo "the announcement\n";
$GLOBALS['posted'] = array();
function discordmsg($title, $description, $imageurl, $url = '', $channel = '',
                    $thumbnail = '', $color = '000000', $author = null,
                    $footer = null, $content = '') {
	$GLOBALS['posted'][] = compact('title', 'description', 'channel', 'color', 'author');
	return true;
}
class DRRes {
	public $rows;
	function __construct($r) { $this->rows = $r; }
	function fetch_assoc() { return $this->rows ? array_shift($this->rows) : null; }
}
class DRConn {
	function query($sql) {
		if (strpos($sql, 'consumables') !== false) return new DRRes(array(array('name' => 'Fast Forward')));
		if (strpos($sql, 'transactions') !== false) return new DRRes(array(array('total' => 41)));
		return false;
	}
}
$_SESSION = array('userData' => array(
	'user_id' => 7, 'username' => 'oculus', 'discord_id' => '123', 'avatar' => 'abc'));

$body = extract_fn($db, 'dailyRewardAnnounce');
ok($body !== '', 'dailyRewardAnnounce() is gone from db.php -- the claim has nowhere to '
               . 'defer its announcement to');
if ($body === '') { echo "\nFAILED: $fail check(s)\n"; exit(1); }
eval($body);

$conn = new DRConn();
dailyRewardAnnounce($conn, 3, 15, 'SKULL');
ok(count($GLOBALS['posted']) === 1, 'one claim should post exactly one message, got '
                                  . count($GLOBALS['posted']));
$p = $GLOBALS['posted'] ? $GLOBALS['posted'][0] : array('description' => '', 'channel' => '');
printf("  channel=%s  %s\n", $p['channel'], str_replace("\n", ' / ', $p['description']));
ok(strpos($p['description'], 'Day 3 of 7') !== false, 'the streak day is missing');
ok(strpos($p['description'], '15 SKULL') !== false, 'the amount and currency are missing');
ok(strpos($p['description'], 'Fast Forward') !== false,
   'the bonus item is missing -- it is read from the database, not passed in, which is '
 . 'what stops a client dictating the post');
ok(strpos($p['description'], '41') !== false, 'the total claim count is missing');
ok($p['channel'] === 'dailyrewards', 'posted to the wrong channel: ' . $p['channel']);
ok(substr_count($p['description'], "\xF0\x9F\x94\xA5") === 3,
   'the streak bar should show three lit days for day 3');

/* A day outside the streak cannot address the consumables table, and used to
   be impossible only because the caller was trustworthy. It is a client-
   triggered path now. */
$GLOBALS['posted'] = array();
foreach (array(0, 8, -1, 99) as $bad) dailyRewardAnnounce($conn, $bad, 15, 'SKULL');
ok($GLOBALS['posted'] === array(),
   'a day outside 1..7 still posted -- the endpoint is client-triggered now, so this '
 . 'has to refuse rather than index past the end of the table');

/* ---------- 2. the claim no longer waits ----------------------------------- */
echo "\nthe claim\n";
$claim = extract_fn($dbText, 'getRandomReward');
ok($claim !== '', 'getRandomReward() not found');
/* Brace matching over a 14,000-line file is only as good as the stripping, so
   prove the extraction actually landed on this function and stopped inside it
   rather than running on into the next twenty. */
ok(strpos($claim, 'return $project;') !== false && strlen($claim) < 4000,
   'the extracted getRandomReward() body looks wrong (' . strlen($claim) . ' bytes) -- '
 . 'every check below it would be reading some other function');
ok(strpos($claim, 'discordmsg(') === false,
   'getRandomReward() posts to Discord again -- that is a synchronous curl with an 8 '
 . 'second timeout sitting between the press and the reply, which is the whole bug');
ok(strpos($claim, "\$_SESSION['dr_announce']") !== false,
   'the claim no longer leaves a marker, so nothing will ever be announced');
ok(preg_match("/'at'\s*=>\s*time\(\)/", $claim) === 1,
   'the marker carries no timestamp -- skulliance.php serialises $_SESSION into a '
 . 'six-month cookie, so a stranded marker would ride along in it indefinitely');
ok(strpos($claim, 'dhcf_award(') !== false,
   "the seventh-day trait award was moved out of the claim too -- it is a database "
 . 'write, it belongs inside the transaction that earns it, and the reply carries it');

/* ---------- 3. the endpoint clears before it posts -------------------------- */
echo "\nthe announce endpoint\n";
$epRaw  = file_get_contents(__DIR__ . '/ajax/daily-reward-announce.php');
/* Stripped, because this file's own header explains what dailyRewardAnnounce()
   is for -- and strpos() found that sentence before the call, which made the
   ordering check below claim the marker was cleared too late. */
$ep     = php_strip($epRaw);
$unset  = strpos($ep, 'unset($_SESSION[');
$post   = strpos($ep, 'dailyRewardAnnounce(');
$cookie = strpos($ep, 'setcookie(');
ok($unset !== false, 'the endpoint never clears the marker, so it announces on every call');
ok($post  !== false, 'the endpoint never calls the announcer');
ok($unset !== false && $post !== false && $unset < $post,
   'the marker is cleared AFTER the post -- the 8 second curl is exactly the window a '
 . 'second call arrives in, and the claim gets announced twice');
ok($cookie !== false && $cookie > $unset,
   'the endpoint does not rewrite SessionCookie after clearing -- on a session where '
 . 'the cookie IS the session (iOS ITP, PWA) the marker comes straight back and the '
 . 'claim is re-announced on every page open. See ajax/dhc-seen-drop.php.');
ok(strpos($ep, '900') !== false, 'the staleness window is gone; a marker stranded by a '
                               . 'closed tab would announce yesterday as though it just happened');
ok(strpos($ep, '$_GET') === false && strpos($ep, '$_POST') === false,
   'the endpoint reads client input -- it must take no parameters at all, or it becomes '
 . 'a way to post chosen text to Discord');
echo "  clears at byte $unset, posts at $post, rewrites the cookie at $cookie\n";

/* ---------- 4. the button ---------------------------------------------------- */
echo "\nthe button\n";
$js  = file_get_contents(__DIR__ . '/skulliance.js');
/* JS, so token_get_all cannot help -- but dailyReward()'s comments do not
   mention any of the identifiers checked below, and its braces are all real. */
$fn  = extract_fn($js, 'dailyReward');
ok($fn !== '', 'dailyReward() not found in skulliance.js');
ok(strpos($fn, 'if (btn.disabled) return;') !== false,
   'a second press is not refused -- two claims racing both pass '
 . 'getDailyRewardEligibility() before either increments the streak');
ok(strpos($fn, "btn.value = 'Claiming") !== false, 'the button never says it is working');
ok(strpos($fn, 'btn.style.minWidth') !== false,
   'the width is not pinned, so the button resizes under the finger that pressed it');
foreach (array('ontimeout', 'onerror') as $h) {
	ok(strpos($fn, $h) !== false, "no $h handler -- that path leaves the button stuck on "
	                            . '"Claiming..." with the reward still unclaimed');
}
/* Three CALLS: the timeout, the transport error and the non-200 branch. The
   definition is `var restore = function (msg)`, which is not one of them. */
ok(substr_count($fn, 'restore(') >= 3,
   'not every failure path restores the button; found ' . substr_count($fn, 'restore(')
 . ' calls (want timeout, transport error and the non-200 branch)');
ok(strpos($fn, 'daily-reward-announce.php') !== false,
   'the claim no longer triggers the announcement, so nothing reaches Discord at all');
$annAt = strpos($fn, 'daily-reward-announce.php');
$hide  = strpos($fn, "style.display = \"none\"");
ok($hide !== false && $annAt > $hide,
   'the announcement is fired before the strip is redrawn -- the whole point is that it '
 . 'happens after the player has already seen their reward');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all daily reward checks passed\n");
exit($fail ? 1 : 0);
