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
		if (!empty($this->queue)) {
			/* A queued entry may be one row or a list of them. An assoc
			   array is one row: handed straight to FakeRes it would be
			   read as a list of FIELDS, and fetch_assoc() would return
			   a single value where the caller expects a record. */
			$next = array_shift($this->queue);
			if (is_array($next) && $next !== array() && array_keys($next) !== range(0, count($next) - 1)) {
				$next = array($next);
			}
			return new FakeRes($next);
		}
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
/* A missing OR MISNAMED credential is silent by design, so the admin
   page has to say which it is -- the alternative is noticing weeks
   later that a channel went quiet. */
ok(strpos($adm, "function_exists('getTestimonialsWebhook')") !== false,
   'the admin page does not check whether the Discord credential is wired');
ok(strpos($adm, 'getTestimonialsWebhook()') !== false,
   'the page never names the function a typo would have landed on');
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
$modal0 = file_get_contents(__DIR__ . '/testimonial-modal.php');
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
/*
 * TWO STATES, AND NO PICKER AT SUBMIT TIME.
 *
 * 'anonymous' was removed: an unattributed quote is weak proof, and it
 * does not buy privacy, because the avatar URL carries the permanent
 * Discord id while a username does not. Anything that reaches the wall
 * is named, so a third state creeping back in is a change to who gets
 * published and must fail here.
 */
ok(testimonial_visibilities() === array('named', 'private'),
   'the visibility states are ' . implode('/', testimonial_visibilities()) . ', not named/private');
$anon = new FakeConn();
testimonial_save($anon, 9, 'back', str_repeat('a', 90), 'anonymous', true);
ok($anon->said("'named'"), 'an unknown visibility is not coerced to named on save');
ok(!$anon->said("'anonymous'"), 'a save accepted a visibility outside the two states');
ok(strpos($modal0, 'name="tst-vis"') === false,
   'the modal still offers a visibility picker, which is the thing that was cut');

/*
 * THE CONFIRMATION, AND THE FACT THAT THE SERVER ENFORCES IT.
 *
 * A tick box is a user interface. Publishing somebody's username and
 * avatar because a checkbox was missing from a POST is the mistake
 * worth refusing to make, so the save has to be what requires it.
 */
$noc = new FakeConn();
ok(testimonial_save($noc, 9, 'back', str_repeat('a', 90), 'named', false) !== '',
   'a testimonial saves with no confirmation, so a bare POST publishes a username');
ok(!$noc->said('INSERT INTO testimonials'),
   'the unconfirmed save still wrote a row');
$yesc = new FakeConn();
ok(testimonial_save($yesc, 9, 'back', str_repeat('a', 90), 'named', true) === '',
   'a confirmed testimonial of the right length is refused');
ok($yesc->said('INSERT INTO testimonials'), 'a confirmed save wrote nothing');
/* And the default is no: a caller that forgets the argument must not
   be treated as having agreed on the player's behalf. */
$defc = new FakeConn();
ok(testimonial_save($defc, 9, 'back', str_repeat('a', 90), 'named') !== '',
   'consent defaults to true, so a caller that omits it publishes a username');
ok(strpos($modal0, 'id="tst-consent"') !== false,
   'the modal has no confirmation control');
/*
 * AND IT SAYS WHY. A disabled Send stops the submission and explains
 * nothing: three sentences written, a dead button, no reason given.
 * The check belongs where the length check already is.
 */
ok(strpos($modal0, 'sendBtn.disabled = !consent.checked') === false,
   'the send button is disabled rather than explaining itself');
ok(preg_match('~if \(!consent\.checked\) \{\s*err\.textContent~', $modal0) === 1,
   'pressing send without the tick does not say why nothing happened');
ok(preg_match('~if \(!consent\.checked\)[^}]*consent\.focus\(\)~s', $modal0) === 1,
   'the message does not point at the control it is about');
$ajax0 = file_get_contents(__DIR__ . '/ajax/testimonial.php');
ok(strpos($ajax0, "!empty(\$_POST['consent'])") !== false,
   'the endpoint never forwards the confirmation, so the save always refuses');
ok(stripos($modal0, 'username and avatar') !== false,
   'the modal never says what sending it publishes, so sending it is not informed consent');

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

/*
 * A REJECTION HAS TO GO SOMEWHERE.
 *
 * It used to go nowhere: the rejected row stayed current, so the modal
 * -- which refuses anybody who already has one -- never asked again,
 * and the profile said "not published" with no reason and no way to
 * rewrite. The review note was stored and shown to nobody.
 */
$lib = $src;
$panel = substr($lib, strpos($lib, 'function testimonial_self_panel('));
$panel = substr($panel, 0, strpos($panel, "\n}\n"));
/*
 * RENDERED, NOT GREPPED. Searching the source for 'review_note' passes
 * on `if (false) { echo $t['review_note']; }`, which is exactly the
 * regression worth catching: the note was already being stored and
 * shown to nobody. So the panel is actually run against a rejected row
 * and the output is read.
 */
$pc = new FakeConn();
$pc->queue[] = array('id' => 3, 'user_id' => 9, 'prompt_key' => 'back',
	'body' => 'The faction chat is the reason I log in.', 'visibility' => 'named',
	'status' => 'rejected', 'is_current' => 1, 'created_date' => '2026-01-01 00:00:00',
	'review_note' => 'Could you say more about the raids?', 'discord_posted' => 0);
ob_start(); testimonial_self_panel($pc, 9); $out = ob_get_clean();
ok(strpos($out, 'Could you say more about the raids?') !== false,
   'a rejected testimonial does not show the owner\'s note, so the player is told nothing');
ok(strpos($out, 'not published') !== false, 'a rejected testimonial does not say it was not published');
ok(strpos($out, 'rewrite it below') !== false, 'a rejection does not invite another go');
ok(preg_match('~<textarea~', $out) === 1, 'the panel has no way to rewrite, so a rejection is a dead end');
ok(strpos($out, 'The faction chat is the reason I log in.') !== false,
   'the rewrite box does not start from what they already wrote');
ok(strpos($out, 'value="save"') !== false, 'the rewrite does not post a save, so it cannot resubmit');
ok(strpos($out, 'name="consent"') !== false, 'the rewrite skips the confirmation that the submit requires');

/* An approved one says what editing costs, and offers no reason text. */
$pa = new FakeConn();
$pa->queue[] = array('id' => 4, 'user_id' => 9, 'prompt_key' => 'back',
	'body' => 'Good people and a map worth looking at.', 'visibility' => 'named',
	'status' => 'approved', 'is_current' => 1, 'created_date' => '2026-01-01 00:00:00',
	'review_note' => '', 'discord_posted' => 1);
ob_start(); testimonial_self_panel($pa, 9); $outA = ob_get_clean();
ok(strpos($outA, 'live on the site') !== false, 'an approved testimonial does not say it is live');
ok(strpos($outA, 'rewrite it below') === false, 'an approved testimonial is told it was rejected');
ok(strpos($outA, 'come off the site until it is approved') !== false,
   'editing a live testimonial does not warn that it comes down until re-approved');

/* And nothing at all for somebody who never wrote one. */
ob_start(); testimonial_self_panel(new FakeConn(), 9); $outN = ob_get_clean();
ok(trim($outN) === '', 'the panel renders for a player with no testimonial');
/* Rewriting is the same insert-and-retire as a first submission, so a
   revision is re-reviewed rather than going live on an old approval. */
$rev = new FakeConn();
testimonial_save($rev, 9, 'back', str_repeat('b', 90), 'named', true);
ok($rev->said('UPDATE testimonials SET is_current = 0') && $rev->said('INSERT INTO testimonials'),
   'a revision does not retire the old row and insert a new one');
ok($rev->said("'pending'"), 'a revision does not go back to pending');

echo "\nthe preview is a look, not a session\n";
require_once __DIR__ . '/lib/realm-preview.php';
$owner  = array('userData' => array('user_id' => 1));
$player = array('userData' => array('user_id' => 42));
ok(testimonial_preview_requested(array('preview' => 'testimonial'), $owner) === true,
   'the owner cannot open the testimonial preview');
ok(testimonial_preview_requested(array('preview' => 'testimonial'), $player) === false,
   'any player can open the testimonial preview');
ok(testimonial_preview_requested(array('preview' => 'newcomer'), $owner) === false,
   'the newcomer preview also opens the testimonial modal');
ok(testimonial_preview_requested(array(), $owner) === false,
   'the testimonial preview turns itself on without being asked');
ok(testimonial_preview_requested(array('preview' => 'testimonial'), array()) === false,
   'a request with no session opens the preview');
$modalP = file_get_contents(__DIR__ . '/testimonial-modal.php');
ok(strpos($modalP, 'if (offer.preview) return Promise.resolve') !== false,
   'the preview posts to the endpoint, so looking at it spends a real nudge');
ok(strpos($modalP, 'testimonial_preview_requested') !== false,
   'the modal does not honour the preview at all');

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
