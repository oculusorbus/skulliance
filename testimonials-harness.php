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
	public $log = array(); public $queue = array(); public $tables = true; public $columns = true;
	function query($sql) {
		$this->log[] = preg_replace('~\s+~', ' ', trim($sql));
		if (stripos($sql, 'SHOW TABLES') === 0) return new FakeRes($this->tables ? array(array('x')) : array());
		/* SHOW COLUMNS answers the edited_body probe. Without this the
		   fake said the column was absent, testimonials_can_edit()
		   cached false for the whole process, and every editorial
		   assertion tested a function that had already returned. */
		if (stripos($sql, 'SHOW COLUMNS') === 0) return new FakeRes($this->columns ? array(array('x')) : array());
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
		/* Record the whole shape, not three fields: the bug that got
		   through was an embed Discord refused, and the refused part
		   was the footer. */
		$GLOBALS['tst_posts'][] = array('title' => $title, 'desc' => $desc, 'channel' => $channel,
		                                'footer' => $footer, 'thumb' => $thumb, 'color' => $color);
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
/* One question now, with the old two moved inside the brief as
   prompts. The count is not the point; what matters is that whatever
   is asked is a question, and that the brief still says who is going
   to read the answer. */
ok(count($prompts) >= 1, 'there is no question to ask at all');
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
/*
 * THE POSTER HAS TO BE LOADED, NOT ASSUMED.
 *
 * skl_discordmsg() lives in webhooks.php, which arrives through
 * verify.php -- and an admin page has no reason to include verify.php.
 * It did not, so function_exists() was false, testimonial_announce()
 * returned early, and the first real approval went live on the site
 * and posted nothing. Silently, and indistinguishable from a missing
 * credential, which is what the status line then blamed.
 */
/*
 * EVERY VERDICT IS REVERSIBLE. The queue listed `pending` only and
 * rendered the approved ones through testimonial_public(), which
 * drops the row id -- so once something was judged there was no
 * control anywhere to judge it again, and changing your mind meant
 * editing the database by hand.
 */
ok(strpos($adm, 'testimonial_queue($conn, \'approved\')') !== false,
   'the admin page cannot list approved testimonials, so none can be pulled down');
ok(strpos($adm, 'testimonial_queue($conn, \'rejected\')') !== false,
   'the admin page cannot list rejected testimonials, so none can be let through');
/* The card moved to the library, so the admin page no longer holds
   the button markup -- it holds the three calls that pass the
   verdicts. Check those, and the rendering is checked where it now
   lives, by running it. */
ok(substr_count($adm, 'testimonial_admin_card($t, array(') === 3,
   'the admin page no longer renders all three lists through one card');
ok(function_exists('testimonial_admin_card'),
   'the review card is not in the library, so nothing can run it');
$q = new FakeConn();
$q->queue[] = array();
testimonial_queue($q, 'approved');
ok($q->said("status = 'approved'"), 'testimonial_queue() ignores the status it is given');
$q2 = new FakeConn();
testimonial_queue($q2, "'; DROP TABLE testimonials; --");
ok($q2->said("status = 'pending'"), 'testimonial_queue() passes an unknown status straight into SQL');
ok(strpos($adm, "include_once __DIR__ . '/webhooks.php'") !== false,
   'the admin page never loads webhooks.php, so approving posts nothing');
/* Extracted here, not borrowed from a $ann defined further down the
   file. Reading a variable a section before it is assigned is how
   three assertions in this harness have quietly tested empty strings. */
$annSrc = substr($src, strpos($src, 'function testimonial_announce('));
$annSrc = substr($annSrc, 0, strpos($annSrc, "\n}\n"));
ok(strpos($annSrc, "include_once __DIR__ . '/webhooks.php'") !== false,
   'testimonial_announce() does not load its own poster, so the next caller repeats the bug');
/* The load has to come BEFORE the bail, or it changes nothing. */
$loadAt = strpos($annSrc, "include_once __DIR__ . '/webhooks.php'");
$bailAt = strpos($annSrc, "if (!function_exists('skl_discordmsg')) return false;");
$flagAt = strpos($annSrc, 'discord_posted = 1');
ok($loadAt !== false && $bailAt !== false && $loadAt < $bailAt,
   'webhooks.php is loaded after the function_exists bail, which is too late to help');
/* And the bail must come before the flag, or a failed post marks the
   row as posted and nothing will ever send it. */
ok($bailAt !== false && $flagAt !== false && $bailAt < $flagAt,
   'a missing poster still flags the row as posted, so the testimonial can never be sent');
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
/* A real prompt key: the fixture used 'back', left over from when
   there were three questions, and an unrecognised key correctly
   yields no footer -- which made the footer assertion fail against
   code that was right. */
$pk  = array_keys(testimonial_prompts());
$row = array('id' => 5, 'user_id' => 9, 'status' => 'approved', 'visibility' => 'named',
             'discord_posted' => 0, 'prompt_key' => $pk[0], 'body' => 'Good people.',
             'username' => 'someone');
ok(testimonial_announce($warm, $row) === true, 'approving does not post to Discord');
ok(count($GLOBALS['tst_posts']) === 1, 'approving posted ' . count($GLOBALS['tst_posts']) . ' times, not once');
ok($GLOBALS['tst_posts'][0]['channel'] === 'testimonials',
   'the post went to the wrong channel: ' . $GLOBALS['tst_posts'][0]['channel']);
ok($warm->said('discord_posted = 1'), 'the row is not flagged, so the next approval posts it again');
/*
 * THE EMBED HAS TO BE ONE DISCORD WILL TAKE.
 *
 * skl_discordmsg() drops $footer straight into the embed and
 * Discord's footer must be an object with a `text` key. A bare string
 * produced 400 / {"embeds":["0"]} and Skull Bot announcing its own
 * post rejected. The fake records what it was handed.
 */
ok(is_array($GLOBALS['tst_posts'][0]['footer'] ?? null)
   && isset($GLOBALS['tst_posts'][0]['footer']['text']),
   'the footer is not an object with a text key, so Discord rejects the embed');
/*
 * AND THE FLAG GOES ON AFTER THE POST. It was set first, to stop a
 * timed-out-but-delivered post being retried into a double. The first
 * real post was REJECTED, the row was already marked delivered, and
 * nothing could ever send it again -- which is the worse failure of
 * the two, and the one that actually happened.
 */
$annFlag = strpos($annSrc2 = $annSrc, 'discord_posted = 1');
$annSend = strpos($annSrc, 'skl_discordmsg(');
ok($annFlag !== false && $annSend !== false && $annSend < $annFlag,
   'the row is flagged as posted before the post, so a rejected embed can never be retried');
/* And a deliberate way to lift that flag when a post was refused. */
/* An unrecognised prompt key -- a row written before the questions
   changed -- must send NO footer rather than an empty one, which is
   its own invalid embed. */
$GLOBALS['tst_posts'] = array();
$old = $row; $old['prompt_key'] = 'a-question-that-no-longer-exists';
testimonial_announce(new FakeConn(), $old);
/* array_key_exists, not ??. The null coalescing operator returns its
   fallback for a value that IS null, so `$v ?? 'x'` can never equal
   null and the test could only ever fail. */
ok(array_key_exists('footer', $GLOBALS['tst_posts'][0])
   && $GLOBALS['tst_posts'][0]['footer'] === null,
   'a row with an unknown prompt key sends an empty footer instead of none');

ok(function_exists('testimonial_unpost'), 'there is no way to clear the posted flag after a refusal');
$un = new FakeConn();
testimonial_unpost($un, 5);
ok($un->said('discord_posted = 0'), 'testimonial_unpost() does not clear the flag');
/* The BUTTON, not the word: 'repost' also appears in the POST
   handler, so searching for it passes with no control to press. */
ok(preg_match("~'repost'\s*=>\s*'~", $adm) === 1,
   'the live cards offer no re-post control, so a rejected post is stuck');
ok(strpos($adm, "\$_POST['verdict'] === 'repost'") !== false,
   'the admin page has no handler for the re-post verdict');
ok(strpos($adm, 'testimonial_unpost($conn, $tid)') !== false,
   're-posting does not clear the flag first, so the guard still blocks it');

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

/*
 * THE FOUNDER'S WORDING, SEPARATE FROM THE PLAYER'S WORDS.
 *
 * A testimonial can be adjusted for public readability without the
 * player being asked again. The original must survive untouched --
 * it is the only thing that can answer "I did not say that" -- so the
 * polish is a second column and everything public reads through one
 * function.
 */
ok(testimonial_display_body(array('body' => 'raw')) === 'raw',
   'with no edit, the player\'s own words are not what publishes');
ok(testimonial_display_body(array('body' => 'raw', 'edited_body' => null)) === 'raw',
   'a null edit does not fall back to the original');
ok(testimonial_display_body(array('body' => 'raw', 'edited_body' => '   ')) === 'raw',
   'a whitespace-only edit publishes as blank instead of falling back');
ok(testimonial_display_body(array('body' => 'raw', 'edited_body' => 'polished')) === 'polished',
   'the founder\'s wording is not what publishes');
/* A row from an install without the ALTER has no key at all. */
$noCol = array('body' => 'raw');
ok(testimonial_display_body($noCol) === 'raw',
   'a row with no edited_body column does not publish the original');

/* Everything public has to go through that one function. */
$pubFn = substr($src, strpos($src, 'function testimonial_public('));
$pubFn = substr($pubFn, 0, strpos($pubFn, "\n}\n"));
ok(strpos($pubFn, 'testimonial_display_body($row)') !== false,
   'the public wall reads body directly, so an edited testimonial publishes the raw words');
ok(strpos($annSrc, 'testimonial_display_body($row)') !== false,
   'the Discord post reads body directly, so an edited testimonial posts the raw words');
$panelFn = substr($src, strpos($src, 'function testimonial_self_panel('));
$panelFn = substr($panelFn, 0, strpos($panelFn, "\n}\n"));
ok(strpos($panelFn, 'testimonial_share_url($pubText)') !== false,
   'the share link posts the raw words rather than what is published');
ok(strpos($panelFn, 'As published') !== false,
   'a player cannot see the wording that is published under their name');

/* The original is never written by the editorial path. */
$setFn = substr($src, strpos($src, 'function testimonial_set_public_text('));
$setFn = substr($setFn, 0, strpos($setFn, "\n}\n"));
ok(strpos($setFn, 'edited_body =') !== false && strpos($setFn, 'SET body') === false,
   'the editorial save writes body, which destroys the words the player agreed to');
$sp = new FakeConn();
testimonial_set_public_text($sp, 5, 'tidied up');
ok($sp->said("edited_body = 'tidied up'"), 'the wording is not stored');
$sp2 = new FakeConn();
testimonial_set_public_text($sp2, 5, '   ');
ok($sp2->said('edited_body = NULL'), 'clearing the wording does not restore the original');
$sp3 = new FakeConn();
ok(testimonial_set_public_text($sp3, 5, str_repeat('x', TESTIMONIAL_MAX + 1)) === false,
   'an over-long editorial rewrite is accepted');

/* And the modal has to say it may happen. */
$modalNow = file_get_contents(__DIR__ . '/testimonial-modal.php');
ok(stripos($modalNow, 'adjust the') !== false && stripos($modalNow, 'wording') !== false,
   'the modal never warns that the wording may be adjusted');
ok(stripos($modalNow, 'kept exactly as you wrote them') !== false,
   'the modal says the wording may change without saying the original is kept');
/* The revision box sits under the quote it revises. */
/*
 * RENDERED, NOT GREPPED. The card was a function inside the admin
 * page, so the only available check was searching its source -- which
 * passes just as happily when the whole revision block sits behind
 * `if (false)`. It is in the library now and this runs it.
 */
$cardRow = array('id' => 7, 'user_id' => 9, 'username' => 'someone',
                 'created_date' => '2026-01-01 00:00:00', 'visibility' => 'named',
                 'discord_posted' => 0, 'prompt_key' => $pk[0],
                 'body' => 'THE ORIGINAL LINE', 'edited_body' => 'THE TIDIED LINE',
                 'review_note' => '', 'status' => 'approved');
ob_start();
testimonial_admin_card($cardRow, array('reject' => 'Pull it down'), true);
$cardOut = ob_get_clean();
$quoteAt   = strpos($cardOut, 'THE ORIGINAL LINE');
$reviseAt  = strpos($cardOut, 'adm-revise');
$verdictAt = strpos($cardOut, 'adm-verdict');
ok($quoteAt !== false && $reviseAt !== false && $verdictAt !== false
   && $quoteAt < $reviseAt && $reviseAt < $verdictAt,
   'the revision box is not directly under the quote, which is where it is used from');
ok(strpos($cardOut, 'THE TIDIED LINE') !== false,
   'the revision box does not start from the wording already saved');
ok(strpos($cardOut, 'value="revise"') !== false,
   'the revision form posts no verdict of its own, so saving wording is a verdict');
/* The original is always what the card quotes: the point of the
   column is being able to compare the two. */
ok(strpos($cardOut, 'THE ORIGINAL LINE') !== false,
   'the card quotes the edit instead of what the player wrote');
/* And with no column, no box -- but still a card. */
ob_start();
testimonial_admin_card($cardRow, array('reject' => 'Pull it down'), false);
$cardNoEdit = ob_get_clean();
ok(strpos($cardNoEdit, 'adm-revise') === false,
   'the revision box renders on an install with no edited_body column');
ok(strpos($cardNoEdit, 'adm-verdict') !== false,
   'losing the revision box takes the verdict buttons with it');

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
/* PLATFORM-WIDE, not Realms-wide. Requiring a won raid made a
   platform feature into a Realms one: somebody who plays the card
   games nightly and never raids could not be asked. */
ok(preg_match('~testimonial_recently_active~', $off) === 1,
   'the offer does not require recent activity, so it interrupts people who came to play');
ok(strpos($src, 'FROM activity') !== false,
   'eligibility no longer reads the platform-wide activity log');
ok(strpos($src, 'return testimonial_recent_win($conn, $user_id);') !== false,
   'there is no fallback when the activity table is absent, so nobody would ever be asked');
/* The activity read must tolerate a missing table the way activity_log()
   does, or an install without it is a warning printed mid-page. */
ok(preg_match('~@\$conn->query\(\s*"SELECT COUNT\(\*\) AS n FROM activity~s', $src) === 1,
   'the activity count is not suppressed, so a missing table prints a warning into the page');
/*
 * EVERY PAGE A PLAYER LANDS ON, not a handful. This platform has a lot
 * of games and a lot of ways to win, and somebody who finishes one
 * comes back to whichever page they came from -- so the ask lives in
 * header.php, which is all of them.
 */
$hdrSrc = file_get_contents(__DIR__ . '/header.php');
ok(strpos($hdrSrc, "include __DIR__ . '/testimonial-modal.php'") !== false,
   'header.php does not carry the ask, so it only appears where somebody remembered to add it');
/* Three queries on every page view of the platform is not a trade
   worth making to find out somebody was asked last Tuesday. */
ok(strpos($src, 'function testimonial_offer_cached') !== false,
   'the offer is not cached, so every page view costs three queries');
ok(strpos(file_get_contents(__DIR__ . '/testimonial-modal.php'), 'testimonial_offer_cached($conn') !== false,
   'the modal calls the uncached offer from a header included on every page');
/* Every branch that changes the answer has to clear it: shown,
   dismiss and save. Checking the string appears somewhere passes
   while two of the three still have it. */
ok(substr_count($ajax0, 'testimonial_offer_forget()') >= 3,
   'not every endpoint branch clears the cached decision, so the modal returns after it is answered');
/* In the save branch: after the error bail, before the success
   reply. Ordering in the extracted branch, not a regex over the whole
   file trying to describe a multi-line call. */
$saveBranch = substr($ajax0, strpos($ajax0, "if (\$action === 'save')"));
$bail   = strpos($saveBranch, "if (\$err !== '')");
$forget = strpos($saveBranch, 'testimonial_offer_forget()');
$reply  = strpos($saveBranch, "'success' => true");
ok($bail !== false && $forget !== false && $reply !== false
   && $bail < $forget && $forget < $reply,
   'a successful save does not clear the cached decision, so somebody is asked again after writing one');
/* The games themselves skip header.php on purpose: nothing should open
   a dialog over a game in progress, and they carry none of this CSS. */
foreach (array('dhcgame.php', 'skullswap.php', 'match3rpg.php') as $game) {
	if (!is_file(__DIR__ . '/' . $game)) continue;
	ok(strpos(file_get_contents(__DIR__ . '/' . $game), "include 'header.php'") === false,
	   "$game now pulls header.php, which would open the ask over a game in progress");
}
/* Two openers would race: realms.php has a real moment, everywhere
   else falls back to a timer. */
/* Read here rather than relying on a $realms/$modal defined further
   down the file: an assertion that runs before its own input is
   loaded tests an empty string and passes for no reason. That has
   now happened twice in this harness. */
$realmsSrc = file_get_contents(__DIR__ . '/realms.php');
$modalSrc  = file_get_contents(__DIR__ . '/testimonial-modal.php');
/* header.php runs near the top of a page, so a flag the including
   page was meant to set first would always be read before it was
   written. The modal asks the path instead. */
ok(strpos($modalSrc, "!== 'realms.php'") !== false,
   'the modal no longer excludes realms.php from self-opening, so it can appear mid-raid');
ok(strpos($realmsSrc, 'skTestimonialOffer') !== false,
   'realms.php no longer opens the ask when a raid result closes');
ok(strpos($modalSrc, 'selfOpen') !== false,
   'the modal cannot open itself, so pages without a raid never show it');
ok(preg_match('~\} else if \(selfOpen\) \{\s*setTimeout~s', $modalSrc) === 1,
   'the self-open is not on a delay, so it greets people on arrival');
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

/*
 * A COOKIELESS VISITOR HAS NO $_SESSION AT ALL. db.php starts one only
 * when a cookie is already there, so every first arrival and every
 * crawler on a public page reaches the modal with the superglobal
 * undefined. isset() on an element is safe; passing the whole array
 * to a function is not, and warned into the page.
 */
ok(strpos($modalSrc, 'isset($_SESSION) ? $_SESSION : array()') !== false,
   'the modal hands $_SESSION straight to a function, which warns for a visitor with no session');

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
/*
 * THE MODAL IS INCLUDED FROM header.php NOW, which runs on pages with
 * a connection and on pages without one -- public pages, error pages,
 * anything that pulls the header before opening a database. It has to
 * check rather than assume, or those pages take a fatal in the middle
 * of their own markup.
 */
$mSrc = file_get_contents(__DIR__ . '/testimonial-modal.php');
ok(strpos($mSrc, 'isset($conn) && is_object($conn)') !== false,
   'the modal assumes a connection, so a header-only page fatals on it');
ok(strpos($mSrc, '$tst_uid > 0') !== false,
   'the modal queries for a signed-out visitor');

echo "\nthe share link can actually be retweeted\n";
$url = testimonial_share_url('I came for the raids and stayed for the people in my faction.');
ok(strpos($url, 'x.com/intent/tweet') !== false, 'the share link does not go to an X intent');
ok(strpos($url, rawurlencode('@skulliance')) !== false || strpos($url, '%40skulliance') !== false,
   'the share text does not mention @skulliance, so the account never sees it to retweet');
/* The front door, not a page inside the platform. It pointed at the
   realms map, chosen when the map was where testimonials appeared;
   the wall came off the map and the link outlived the reason. */
ok(strpos($url, rawurlencode(TESTIMONIAL_SHARE_URL)) !== false,
   'the share link does not carry the site URL');
ok(TESTIMONIAL_SHARE_URL === 'https://skulliance.io',
   'the share link points at ' . TESTIMONIAL_SHARE_URL . ' rather than the front door');
ok(strpos($url, 'realms-map') === false,
   'the share link still sends readers to one feature of the platform');
$long = testimonial_share_url(str_repeat('word ', 120));
ok(strlen($long) < 1200, 'a long testimonial makes an intent URL nobody can post');
ok(substr_count($long, '...') === 1, 'a trimmed share text does not say it was trimmed');
/*
 * THE BUDGET. X counts every link as 23 characters through t.co
 * whatever its length, so the room for the quote is 280 less the
 * link, the space before it and the mention. The old reservation was
 * 180 with no derivation behind it, which trimmed testimonials that
 * would have fitted whole.
 */
$fits = str_repeat('a', 240);
parse_str((string) parse_url(testimonial_share_url($fits), PHP_URL_QUERY), $q240);
ok(strpos($q240['text'], '...') === false,
   'a 240-character testimonial is trimmed, when it fits inside a post');
parse_str((string) parse_url(testimonial_share_url(str_repeat('a', TESTIMONIAL_MAX)), PHP_URL_QUERY), $qMax);
ok(strlen($qMax['text']) + 23 + 1 <= 280,
   'the longest allowed testimonial makes a post X will refuse (' . (strlen($qMax['text']) + 24) . ')');
/* The player is posting their own words from their own account. */
ok(strpos($q240['text'], '"') === false,
   'the share text quotes the player back to themselves');

echo "\nthe copy says what it is for\n";
/* The rule is about user-facing copy; comments and docblocks are
   exempt, and this file's own header carries one. Strip them first or
   the assertion fails on prose no player will ever read. */
$modalCopy = preg_replace('~/\*.*?\*/~s', '', $modal);
$modalCopy = preg_replace('~^\s*//.*$~m', '', $modalCopy);
ok(stripos($modalCopy, 'deciding whether to join') !== false,
   'the modal never says who the testimonial is for, which is how you get "its fun"');
/* The brief carries the follow-up prompts that used to be separate
   questions; without them somebody who stalls has nowhere to go. */
ok(substr_count($modalCopy, '?') >= 4,
   'the brief has lost its prompting questions, leaving one ask and a blank box');
ok(stripos($modalCopy, 'the founder reads it') !== false,
   'the modal no longer says who reads it before publication');
ok(preg_match('~\x{2014}~u', $modalCopy) === 0, 'the modal copy contains an em dash');

echo "\nthe pieces are wired up\n";
ok(strpos(file_get_contents(__DIR__ . '/admin-lib.php'), "'testimonials'") !== false,
   'there is no admin tab, so the queue cannot be reached');
ok(strpos(file_get_contents(__DIR__ . '/webhooks.php'), 'getTestimonialsWebhook') !== false,
   'webhooks.php has no testimonials channel');
/*
 * THE STYLESHEET IS WHAT HIDES THE MODAL, so a page that serves the
 * markup without it does not degrade -- it prints a heading, a
 * textarea and two buttons into the page. That shipped: the link was
 * behind an allowlist written when the ask lived only on the raid
 * screen, the ask moved to launchpad.php, and the list did not.
 *
 * No allowlist now, and the modal carries the one rule that matters
 * inline so a missing link or a 404 cannot unroll it either.
 */
$hdr0 = file_get_contents(__DIR__ . '/header.php');
ok(strpos($hdr0, 'dist/testimonials.css') !== false, 'the stylesheet is never linked');
/* No path allowlist anywhere in the header. map.css uses an explicit
   === comparison for two pages that genuinely are the only two; an
   in_array list of page names is the shape that went stale. */
ok(strpos($hdr0, 'in_array($sk_path') === false,
   'the header gates a stylesheet on a page allowlist again, which is how launchpad lost this one');
ok(strpos($modalSrc2 = file_get_contents(__DIR__ . '/testimonial-modal.php'),
   '<style>.tst-overlay{display:none}</style>') !== false,
   'the modal has no inline guard, so a missing stylesheet dumps it into the page');
/* And the rule it relies on must still be the one in the file. */
ok(preg_match('~\.tst-overlay\s*\{[^}]*display:\s*none~s',
   file_get_contents(__DIR__ . '/dist/testimonials.css')) === 1,
   'dist/testimonials.css no longer hides .tst-overlay by default');
$prof = file_get_contents(__DIR__ . '/profile.php');
ok(strpos($prof, 'testimonial_self_panel') !== false,
   'a player cannot change their mind later');
/* BELOW THE HERO. Above it, a box somebody set once and finished with
   pushed their own profile down the page. */
$heroAt  = strpos($prof, '<div class="profile-hero"');
$panelAt = strpos($prof, 'testimonial_self_panel($conn');
ok($heroAt !== false && $panelAt > $heroAt,
   'the testimonial panel sits above the hero, pushing the profile down');
ok(strpos($prof, '$is_own_profile && function_exists') !== false,
   'the panel is drawn on other people\'s profiles, not only your own');
/*
 * THE WALL IS NOT ON THE MAP OR THE ONBOARDING PAGE.
 *
 * Both were tried and both were wrong: the map is art and reads
 * better without a column of quotes under it, and the onboarding page
 * sits behind the login gate where no visitor reaches it.
 * testimonial_wall() stays in the library for wherever it goes next.
 */
$mapSrc = file_get_contents(__DIR__ . '/realms-map.php');
ok(strpos($mapSrc, 'testimonial_wall') === false,
   'the wall is back on the map, which was asked to be left alone');
ok(strpos(file_get_contents(__DIR__ . '/realms-onboarding.php'), 'testimonial_wall') === false,
   'the wall is back on the onboarding page, behind the login gate');
ok(function_exists('testimonial_wall'), 'testimonial_wall() was deleted rather than unplaced');
/* The share has to outlive the thank-you card, and only once it is real. */
ok(strpos($panelApproved = $outA, 'Share it on X') !== false,
   'an approved testimonial offers no way to share it after the thank-you card is closed');
ok(strpos($out, 'Share it on X') === false,
   'a rejected testimonial is offered a share link');
ok(is_file(__DIR__ . '/testimonials-schema.md'), 'the schema is not written down anywhere');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
