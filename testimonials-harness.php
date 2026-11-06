<?php
/* testimonials-harness.php — CLI only. Static plus a fake mysqli. No database.
 *
 * THE RULES THIS FEATURE RESTS ON, each of which is invisible when broken:
 *
 *   NOTHING PUBLISHES ITSELF. If a submit ever reaches Discord or the
 *   public wall without an approval, whatever a player typed goes out
 *   under the Skulliance name, and a webhook post cannot be recalled.
 *
 *   AN EDIT RESETS APPROVAL. Updating a row in place would let somebody
 *   have a kind paragraph approved and then swap it. Editing must write
 *   a NEW row at pending and retire the old one.
 *
 *   PRIVATE MEANS GONE. A player who takes their name off has to vanish
 *   from the public wall on the next load.
 *
 *   IT IS INERT WITHOUT ITS TABLES. The feature ships before the schema
 *   is run. Every entry point must no-op rather than fatal, or the first
 *   player to win a raid after deploy gets an error inside the raid screen.
 *
 * The fake mysqli below records the SQL rather than running it, which is
 * enough to settle all four: they are properties of the statements, not
 * of any data.
 */
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/testimonials-lib.php';

/** Records every query; answers whatever the test queued up. */
class FakeRes {
	public $num_rows; private $rows;
	function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	function fetch_assoc() { return array_shift($this->rows); }
}
class FakeConn {
	public $log = array(); public $queue = array(); public $tables = true;
	function query($sql) {
		$this->log[] = preg_replace('~\s+~', ' ', trim($sql));
		if (stripos($sql, 'SHOW TABLES') === 0) return new FakeRes($this->tables ? array(array('x')) : array());
		/*
		 * FALSE, LIKE THE REAL ONE. mysqli::query() returns false when
		 * the table does not exist, and the next line is usually
		 * ->num_rows on it. Returning an empty result here instead
		 * made the whole inert section unfalsifiable: removing a
		 * testimonials_ready() guard changed nothing the harness could
		 * see, while on the live site it is a fatal inside the raid
		 * screen.
		 */
		if (!$this->tables && stripos($sql, 'testimonial') !== false) return false;
		if (!empty($this->queue)) return new FakeRes(array_shift($this->queue));
		return new FakeRes(array());
	}
	function real_escape_string($s) { return str_replace("'", "\\'", $s); }
	function said($needle) {
		foreach ($this->log as $q) if (stripos($q, $needle) !== false) return true;
		return false;
	}
}
$fresh = function () { static $n = 0; $n++; return new FakeConn(); };

/*
 * The webhook stub is defined BEFORE the cold branch so both processes
 * have it. Without that, announce() in the cold process refuses for
 * want of skl_discordmsg() rather than for want of its tables, and the
 * assertion that it stays quiet on a fresh install cannot fail.
 */
$GLOBALS['tst_posts'] = array();
if (!function_exists('skl_discordmsg')) {
	function skl_discordmsg($title, $desc = '', $img = '', $url = '', $channel = '',
	                        $thumb = '', $color = '', $author = null, $footer = null,
	                        $content = '', $actor = null, $actor2 = 0) {
		$GLOBALS['tst_posts'][] = array('title' => $title, 'desc' => $desc, 'channel' => $channel);
	}
}

/*
 * THE COLD INSTALL RUNS IN ITS OWN PROCESS.
 *
 * testimonials_ready() caches per request, which is right on the live
 * site -- a table does not appear halfway through a page load -- and
 * fatal for a harness: once the no-tables check has run, every later
 * call in this process sees the cached false and the warm assertions
 * all pass for the wrong reason. So the inert section re-runs this
 * file with an argument, which is also a truer model of the thing
 * being tested: a whole request against a database with no tables.
 */
if (isset($argv[1]) && $argv[1] === 'cold') {
echo "it is inert until the tables exist\n";
	/*
	 * testimonials_ready() caches per request, so each of these needs its
	 * own process-level reset -- which a static cannot give. Instead the
	 * SHOW TABLES behaviour is checked through the one call that happens
	 * first in a cold request.
	 */
	$cold = new FakeConn();
	$cold->tables = false;
	ok(testimonials_ready($cold) === false, 'testimonials_ready() says yes with no tables');
	/* Everything below runs with the cache now warm and FALSE, which is
	   exactly the cold-deploy state the live site will be in. */
	/*
 * Note on what these can and cannot prove. Every one of these
 * functions also guards each individual result (`if ($r)` before
 * reading it), so the early testimonials_ready() return is redundancy
 * rather than the only thing standing between a cold install and a
 * fatal. Removing one is not a defect and these assertions correctly
 * do not fire on it. What they do prove is the thing that matters on
 * deploy day: a request against a database with no tables returns
 * empty everywhere, writes nothing, and posts nothing.
 */
ok(testimonial_current($cold, 7) === null, 'testimonial_current() reads a table that does not exist');
	ok(testimonial_offer($cold, 7) === null, 'the modal offers itself with no tables behind it');
	ok(testimonial_public($cold, 5) === array(), 'the public wall queries a table that does not exist');
	ok(testimonial_pending($cold) === array(), 'the review queue queries a table that does not exist');
	ok(testimonial_save($cold, 7, 'back', str_repeat('a', 100), 'named') !== '',
	   'a save with no tables reports success');
	ok(testimonial_mark_shown($cold, 7) === false, 'mark_shown writes with no tables');
	ok(testimonial_dismiss($cold, 7) === false, 'dismiss writes with no tables');
	ok(testimonial_set_visibility($cold, 7, 'private') === false, 'visibility writes with no tables');
	ok(testimonial_review($cold, 1, true, 1) === null, 'review writes with no tables');
	ok(testimonial_announce($cold, array('id' => 1, 'user_id' => 2, 'status' => 'approved',
	   'visibility' => 'named', 'discord_posted' => 0, 'prompt_key' => 'back',
	   'body' => 'x', 'username' => 'y')) === false,
	   'announce runs with no tables behind it');
	$writes = 0;
	foreach ($cold->log as $q) if (preg_match('~^(INSERT|UPDATE|DELETE)~i', $q)) $writes++;
	ok($writes === 0, "an inert install still issued $writes writes");
	
	exit($fail ? 1 : 0);
}

$cold_out = array();
$cold_rc  = 0;
exec(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__FILE__) . ' cold 2>&1', $cold_out, $cold_rc);
echo implode("\n", $cold_out) . "\n";
if ($cold_rc !== 0) $fail += 1;

echo "\nwhat a testimonial has to be\n";
ok(testimonial_validate(str_repeat('a', TESTIMONIAL_MIN - 1)) !== '', 'a one-liner is accepted');
ok(testimonial_validate(str_repeat('a', TESTIMONIAL_MIN)) === '', 'a piece of exactly the minimum is refused');
ok(testimonial_validate(str_repeat('a', TESTIMONIAL_MAX + 1)) !== '', 'an essay is accepted');
ok(testimonial_validate(str_repeat('a', TESTIMONIAL_MAX)) === '', 'a piece of exactly the maximum is refused');
/* A link in a testimonial is somebody advertising, every time. */
foreach (array('http://x.y', 'https://x.y', 'www.x.y', 'discord.gg/abc') as $bad) {
	ok(testimonial_validate(str_repeat('a', 80) . ' ' . $bad) !== '',
	   "a testimonial carrying $bad is accepted");
}
ok(testimonial_validate(str_repeat('a', 80)) === '', 'a clean piece of the right length is refused');

echo "\nthe questions\n";
$prompts = testimonial_prompts();
ok(count($prompts) >= 3, 'fewer than three questions to rotate');
foreach ($prompts as $k => $q) {
	ok(substr(trim($q), -1) === '?', "prompt '$k' is not a question, which is what gets a real answer");
	ok(stripos($q, 'testimonial') === false,
	   "prompt '$k' uses the word testimonial, which is what produces \"its fun\"");
}
$a = testimonial_prompt_for(41); $b = testimonial_prompt_for(41);
ok($a === $b, 'the question changes under the same player between loads');
ok(isset($prompts[$a]), 'prompt_for() returns a key that is not in the list');

echo "\nan edit is a new row, never an update in place\n";
$c = new FakeConn();
testimonials_ready($c);                      /* warm the cache true for this object */
$ref = new ReflectionFunction('testimonial_save');
$src = file_get_contents(__DIR__ . '/testimonials-lib.php');
$body = substr($src, strpos($src, 'function testimonial_save('));
$body = substr($body, 0, strpos($body, "\n}\n"));
ok(preg_match('~INSERT INTO testimonials~', $body) === 1,
   'testimonial_save() does not insert, so there is no archive and no re-review');
ok(preg_match("~UPDATE testimonials SET is_current = 0~", $body) === 1,
   'testimonial_save() does not retire the previous row, so a player can have two current ones');
ok(preg_match("~'pending'~", $body) === 1,
   'a saved testimonial does not land at pending, so it publishes itself');
ok(preg_match('~UPDATE testimonials SET body~i', $body) === 0,
   'testimonial_save() updates the text in place, which lets an approved piece be swapped');

echo "\nnothing reaches the public or Discord unapproved\n";
$pub = substr($src, strpos($src, 'function testimonial_public('));
$pub = substr($pub, 0, strpos($pub, "\n}\n"));
ok(preg_match("~status\s*=\s*'approved'~", $pub) === 1,
   'the public wall does not filter on approved');
ok(preg_match("~is_current\s*=\s*1~", $pub) === 1,
   'the public wall shows retired rows as well as current ones');
ok(preg_match("~visibility\s*<>\s*'private'~", $pub) === 1,
   'the public wall shows pieces their author set to private');
$adm = file_get_contents(__DIR__ . '/admin-testimonials.php');
ok(preg_match('~testimonial_announce\(\$conn, \$row\)~', $adm) === 1,
   'the admin page does not hand an approved row to testimonial_announce()');
$ajax = file_get_contents(__DIR__ . '/ajax/testimonial.php');
ok(stripos($ajax, 'discordmsg') === false,
   'the submit endpoint posts to Discord, so a player publishes themselves');
ok(stripos($ajax, 'approve') === false,
   'the submit endpoint can reach approval, which is the admin page\'s job');
$ann = substr($src, strpos($src, 'function testimonial_announce('));
$ann = substr($ann, 0, strpos($ann, "\n}\n"));
ok(preg_match("~discord_posted~", $ann) === 1,
   'testimonial_announce() ignores discord_posted, so a second approval posts again');
ok(preg_match("~status'\]\s*!==\s*'approved'~", $ann) === 1,
   'testimonial_announce() does not require approved status');

/*
 * APPROVING IS WHAT POSTS, and it posts once. Testable now that the
 * rule lives in testimonial_announce() rather than inside the admin
 * page -- where the only check available was grepping for the function
 * name, which passes just as happily on `false && skl_discordmsg(...)`.
 */
$warm = new FakeConn();
$row = array('id' => 5, 'user_id' => 9, 'status' => 'approved', 'visibility' => 'named',
             'discord_posted' => 0, 'prompt_key' => 'back', 'body' => 'Good people.',
             'username' => 'someone');
ok(testimonial_announce($warm, $row) === true, 'approving does not post to Discord');
ok(count($GLOBALS['tst_posts']) === 1, 'approving posted ' . count($GLOBALS['tst_posts']) . ' times, not once');
ok($GLOBALS['tst_posts'][0]['channel'] === 'testimonials',
   'the post went to the wrong channel: ' . $GLOBALS['tst_posts'][0]['channel']);
ok($warm->said('discord_posted = 1'), 'the row is not flagged, so the next approval posts it again');

$GLOBALS['tst_posts'] = array();
$again = $row; $again['discord_posted'] = 1;
ok(testimonial_announce(new FakeConn(), $again) === false && !$GLOBALS['tst_posts'],
   'a second approval posts the same piece twice');
$pend = $row; $pend['status'] = 'pending';
ok(testimonial_announce(new FakeConn(), $pend) === false, 'a pending piece posts to Discord');
$priv = $row; $priv['visibility'] = 'private';
ok(testimonial_announce(new FakeConn(), $priv) === false, 'a private piece posts to Discord');
$anon = $row; $anon['visibility'] = 'anonymous';
$GLOBALS['tst_posts'] = array();
testimonial_announce(new FakeConn(), $anon);
ok(!empty($GLOBALS['tst_posts']) && stripos($GLOBALS['tst_posts'][0]['title'], 'someone') === false,
   'an anonymous testimonial is posted to Discord under the player\'s name');

echo "\nthe asking stops\n";
ok(TESTIMONIAL_MAX_NUDGES > 0 && TESTIMONIAL_MAX_NUDGES <= 5,
   'the nudge cap is ' . TESTIMONIAL_MAX_NUDGES . ', which is not a cap anybody would call reasonable');
$off = substr($src, strpos($src, 'function testimonial_offer('));
$off = substr($off, 0, strpos($off, "\n}\n"));
ok(preg_match('~shown >= TESTIMONIAL_MAX_NUDGES~', $off) === 1,
   'the offer ignores the nudge cap, so it asks forever');
ok(preg_match('~if \(\$muted\) return null~', $off) === 1,
   'the offer ignores muted, so "never again" lasts a fortnight');
ok(preg_match('~TESTIMONIAL_NUDGE_DAYS~', $off) === 1,
   'the offer has no cooling-off period between asks');
ok(preg_match('~last_call~', $off) === 1,
   'the last ask is not flagged, so nobody is told it is their last chance');
ok(preg_match('~testimonial_recent_win~', $off) === 1,
   'the offer does not require a recent win, so it interrupts people who came to play');
ok(preg_match('~testimonial_current~', $off) === 1,
   'somebody who already wrote one is asked again');
$modal = file_get_contents(__DIR__ . '/testimonial-modal.php');
ok(strpos($modal, 'This is the last time we will ask') !== false,
   'the modal never warns that a reminder is the last one');

echo "\nthe modal is offered, not forced\n";
ok(strpos($modal, 'window.skTestimonialOffer') !== false,
   'the modal does not expose skTestimonialOffer(), so no page can time it');
ok(preg_match('~\.on\b~', $modal) === 1 || strpos($modal, "classList.add('on')") !== false,
   'the modal has no explicit open, so it may render visible on load');
$realms = file_get_contents(__DIR__ . '/realms.php');
ok(strpos($realms, 'skTestimonialOffer') !== false,
   'realms.php never calls the offer, so the modal can never appear');
ok(strpos($realms, 'moreQueued && typeof window.skTestimonialOffer') !== false,
   'the offer is not held back until the raid queue is empty, so it lands on the next animation');
/* $conn->close() is far above the script block; an include that needs the
   connection has to sit above it. realms-harness.php exists because of
   exactly this class of mistake. */
$incl = strpos($realms, "require_once __DIR__ . '/testimonial-modal.php'");
$close = strpos($realms, '$conn->close();');
ok($incl !== false && $close !== false && $incl < $close,
   'the testimonial include runs after $conn->close(), so it warns inside a <script> block');

echo "\nthe share link can actually be retweeted\n";
$url = testimonial_share_url('I came for the raids and stayed for the people in my faction.');
ok(strpos($url, 'x.com/intent/tweet') !== false, 'the share link does not go to an X intent');
ok(strpos($url, rawurlencode('@skulliance')) !== false || strpos($url, '%40skulliance') !== false,
   'the share text does not mention @skulliance, so the account never sees it to retweet');
ok(strpos($url, rawurlencode('https://skulliance.io/staking/realms-map.php')) !== false,
   'the share link does not point at the public map');
$long = testimonial_share_url(str_repeat('word ', 120));
ok(strlen($long) < 1200, 'a long testimonial makes an intent URL nobody can post');
ok(substr_count($long, '...') === 1, 'a trimmed share text does not say it was trimmed');

echo "\nthe copy says what it is for\n";
ok(stripos($modal, 'deciding whether to start') !== false,
   'the modal never says who the testimonial is for, which is how you get "its fun"');
/* The rule is about user-facing copy; comments and docblocks are
   exempt, and this file's own header carries one. Strip them first or
   the assertion fails on prose no player will ever read. */
$modalCopy = preg_replace('~/\*.*?\*/~s', '', $modal);
$modalCopy = preg_replace('~^\s*//.*$~m', '', $modalCopy);
ok(preg_match('~\x{2014}~u', $modalCopy) === 0, 'the modal copy contains an em dash');

echo "\nthe pieces are wired up\n";
ok(strpos(file_get_contents(__DIR__ . '/admin-lib.php'), "'testimonials'") !== false,
   'there is no admin tab, so the queue cannot be reached');
ok(strpos(file_get_contents(__DIR__ . '/webhooks.php'), 'getTestimonialsWebhook') !== false,
   'webhooks.php has no testimonials channel');
ok(strpos(file_get_contents(__DIR__ . '/header.php'), 'dist/testimonials.css') !== false,
   'the stylesheet is never linked');
ok(strpos(file_get_contents(__DIR__ . '/profile.php'), 'testimonial_self_panel') !== false,
   'a player cannot change their mind later');
ok(strpos(file_get_contents(__DIR__ . '/realms-onboarding.php'), 'testimonial_wall') !== false,
   'the pitch page does not show any of them');
ok(is_file(__DIR__ . '/testimonials-schema.md'), 'the schema is not written down anywhere');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
