<?php
/**
 * admin-lapsed-harness.php — the list you would actually work down.
 *
 * Run: php admin-lapsed-harness.php
 *
 * TWO THINGS COULD BE SILENTLY WRONG HERE AND NEITHER WOULD THROW.
 *
 * The ORDER. Warmest first is the whole point -- somebody gone six
 * weeks still remembers their password and what they were mid-way
 * through; somebody gone two years is a cold start. Sorting the wrong
 * way round would produce a plausible page that wastes every message
 * sent from it.
 *
 * And the COST. One query per player across four hundred members is a
 * page that takes a minute, and nothing about it looks broken -- it
 * just quietly becomes the slowest thing in the admin.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/harness-strip.php';

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok    $what\n"; return true; }
	$fail++; echo "  FAIL  $what\n"; return false;
}
$src = harness_strip_comments(file_get_contents(__DIR__ . '/admin-lapsed.php'));

echo "\nwarmest first, and that is the default\n";
/*
 * ANCHORED ON THE PROPERTIES, NOT THE SPELLING. The first version of
 * these greped for the exact two-option ternary and went red the
 * moment two more sorts were added -- correct code, failing checks,
 * for the third time in this session. What matters is that the
 * choice is whitelisted, that warmest is the fallback, and that
 * warmest really is least-time-away.
 */
ok(preg_match('~in_array\(\$_GET\[.sort.\], \$al_sorts, true\)~', $src) === 1,
   'the sort parameter is not whitelisted');
foreach (array('warmest', 'coldest', 'active', 'quiet') as $k) {
	if (!ok(strpos($src, "'" . $k . "'") !== false, "the $k sort is missing")) break;
}
ok(preg_match("~\\? \\\$_GET\['sort'\] : 'warmest'~", $src) === 1,
   'warmest is not the fallback when no sort is given');
/* DESC on last_login is most-recent-login first, which is least time
   away. Getting this backwards is the one bug that would look fine. */
ok(strpos($src, "(\$al_sort === 'coldest' ? 'ASC' : 'DESC')") !== false,
   'the ordering is not last_login DESC for warmest -- the list is upside down');
/* The activity sorts cannot be done in SQL -- the weighted total is
   assembled in PHP from fifteen sources -- so they are a uasort, and
   the direction has to be right in both. */
ok(strpos($src, "\$al_dir = (\$al_sort === 'active') ? -1 : 1;") !== false,
   'the active/quiet sort direction is gone or inverted');
ok(strpos($src, 'uasort($al_rows') !== false,
   'the activity sort does not preserve the id keys, so every lookup by user id breaks');

echo "\nonly people who have actually gone quiet\n";
ok(strpos($src, 'last_login < NOW() - INTERVAL') !== false, 'the cutoff is missing');
/* A row with no last_login has never signed in since the column
   existed, which is not the same as having lapsed. */
ok(strpos($src, 'last_login IS NOT NULL') !== false,
   'members who have never logged in are counted as lapsed');
ok(strpos($src, "discord_id IS NOT NULL") !== false,
   'the query does not require a Discord id, so a future X-only row would appear with no way to contact it');
/* The window is whitelisted, not trusted -- it is concatenated into SQL. */
ok(strpos($src, 'in_array($al_days, array(30, 90, 180, 365), true)') !== false,
   'the day count is not whitelisted before it reaches the query');

echo "\nit costs one pass, not one per member\n";
/*
 * Each extra fact is a single grouped query over the whole set. The
 * giveaway for the per-row version is a query inside the render loop.
 */
$body = substr($src, strpos($src, 'foreach ($al_rows as $uid => $u):'));
ok(strpos($body, '$conn->query') === false,
   'there is a query inside the row loop, which is one round trip per member');
foreach (array('FROM nfts', 'FROM balances', 'FROM realms') as $t) {
	ok(substr_count($src, $t) === 1, "$t is queried more than once");
}
ok(strpos($src, 'IN ($ids)') !== false, 'the supporting queries are not batched by id');

echo "\nwhat they played is all-time, because recent is zero by definition\n";
/* These people have done nothing lately -- that is what lapsed means.
   A windowed activity read would report zero for everybody and the
   column would be blank for the entire table. */
ok(strpos($src, 'activity_totals($conn, 36500)') !== false,
   'the activity read is windowed, so every lapsed member shows no history');

/* ---------------------------------------------------------------- *
 * DID IT WORK -- the second view
 * ---------------------------------------------------------------- */
echo "\nthe panel can show who actually came back\n";
/*
 * THE WELCOME-BACK PACKAGE IS THE RECEIPT. It is granted once per
 * lapse on a sign-in after a month away, so a 'returngift' row is a
 * returning member with a timestamp, and nothing extra had to be
 * recorded to measure the campaign. Change that source string in
 * lib/return-gift.php and this panel silently reports nobody.
 */
/*
 * EVERY QUERY THAT READS THE LEDGER, not a fixed count of them. This
 * asserted exactly two and went red the moment a third was added for
 * the lapse count -- correct code, failing check, which is the same
 * brittleness as pinning a spelling. The property is that no query
 * against dhc_trait_drops here reads anything other than the
 * welcome-back rows; miss the source on one and it silently counts
 * every trait ever dropped.
 */
/* Split rather than matched: the first version of this carried a
   literal backspace where \b was meant -- inserted through a script
   that ate the escape -- so the pattern never matched anything and
   the check passed on every mutation. A substring walk cannot rot
   that way. */
$parts = explode('dhc_trait_drops', $src);
$loose = 0;
for ($qi = 1; $qi < count($parts); $qi++) {
	if (strpos(substr($parts[$qi], 0, 300), "source = 'returngift'") === false) $loose++;
}
ok(count($parts) - 1 >= 2, 'the ledger queries have gone, so the returns view reads nothing');
ok($loose === 0,
   "$loose quer(y/ies) against dhc_trait_drops do not filter on the welcome-back package, "
 . 'so they count every trait ever dropped');
ok(strpos($src, "view=back") !== false && strpos($src, "'back'") !== false,
   'there is no way to reach the returns view');

/*
 * BUILT SINCE THEY CAME BACK, measured against EACH MEMBER'S OWN
 * return date. A fixed date, or no date at all, would count Fighters
 * built years before the invite and report a campaign that worked
 * when nothing happened -- and it would look entirely plausible.
 */
ok(strpos($src, 'f.created_at >= g.back_at') !== false,
   'Fighters are not counted from each member\'s own return date, so the headline number is meaningless');
/* One query for everybody, not one per returning member. */
ok(substr_count($src, 'FROM dhc_fighters') <= 1,
   'the Fighter count is being asked per member, which is a query per row');

/*
 * The two views must not share a filter that only makes sense on one
 * of them: "came back" is not a subset of "still away", and running
 * the lapsed day-window over it would show an empty page forever.
 */
ok(preg_match('~\$al_back\[\(int\)\$x\[.user_id.\]\] = \$x~', $src) === 1,
   'the returns list is not keyed by user id, so the Fighter and login lookups cannot match it');

/*
 * ONE CLOCK. awarded_at and last_login are both written with MySQL's
 * NOW(), and this server runs MySQL and PHP on different clocks --
 * subtracting a stored timestamp from PHP's time() rendered "-1 days
 * ago" for a member who had returned that morning, and shifts every
 * row in the lapsed list by the skew. Any age shown on this page is
 * asked of the database.
 */
ok(strpos($src, 'DATEDIFF(NOW(), MIN(awarded_at))') !== false,
   'the return age is not measured by the database, so it drifts with the PHP/MySQL clock skew');
ok(strpos($src, 'DATEDIFF(NOW(), last_login)') !== false,
   'the lapsed age is not measured by the database, so every row can be a day out');
ok(strpos($src, 'time()') === false,
   'this page is mixing PHP wall-clock time with MySQL timestamps again');

/* ---------------------------------------------------------------- *
 * THE INVITE COPY
 * ---------------------------------------------------------------- */
echo "\nthe generated invite says only true things\n";
/*
 * THIS TEXT GETS PASTED INTO A REAL DM TO A REAL PERSON, so a claim it
 * invents is a claim the owner makes. Driven through the real
 * functions rather than grepped.
 */
/* Brace-walked out of the page and RUN, the same way admin-harness.php
   lifts the two naming functions: these are pure, and reading them
   would not catch a sentence that is grammatical but untrue. */
function lap_lift($src, $sig) {
	$at = strpos($src, $sig); if ($at === false) return '';
	$i = strpos($src, '{', $at); $d = 0;
	for ($j = $i; $j < strlen($src); $j++) {
		if ($src[$j] === '{') $d++;
		elseif ($src[$j] === '}') { $d--; if (!$d) return substr($src, $at, $j - $at + 1); }
	}
	return '';
}
/* THE CONSTANT TOO, taken from the page rather than declared here:
   defining our own value would make the "is this the live invite"
   check below grade its own homework. */
if (preg_match("~define\('LAP_DISCORD_INVITE', '([^']+)'\)~", $src, $lm)) define('LAP_DISCORD_INVITE', $lm[1]);
ok(defined('LAP_DISCORD_INVITE'), 'the invite constant could not be lifted from the page');

$lift_line = lap_lift($src, 'function lap_invite_line(');
$lift_text = lap_lift($src, 'function lap_invite_text(');
ok($lift_line !== '' && $lift_text !== '',
   'the invite copy functions could not be lifted, so nothing below ran');
eval($lift_line);
eval($lift_text);
require_once __DIR__ . '/activity-sources-lib.php';
$LB = activity_labels();

/* A realm is only mentioned when they actually have one -- it is the
   strongest line in the message and the easiest to disprove. */
$heavy = array('by' => array('mission' => 900, 'raid' => 240));
ok(strpos(lap_invite_text($heavy, $LB, true),  'realm is still standing') !== false,
   'a member with a realm is not told it is still there');
ok(strpos(lap_invite_text($heavy, $LB, false), 'realm') === false,
   'the invite claims a realm the member does not have');

/* Somebody with almost no history must not be told they were big on
   anything. That reads as a mail merge, which is the one thing a
   personal invite cannot read as. */
ok(lap_invite_line(array('by' => array('skullswap' => 3)), $LB) === '',
   'a member with three runs is told they were big on a game');
ok(strpos(lap_invite_line($heavy, $LB), 'Missions') !== false,
   'a heavy player gets no personal line at all');

/* Daily Streaks is a login counter. "Big on Daily Streaks" is not a
   sentence a person writes. */
ok(strpos(lap_invite_line(array('by' => array('daily' => 400)), $LB), 'Daily') === false,
   'Daily Streaks is being named as something they played');

/* Two labels are column headings and need rephrasing before they can
   sit in a sentence. */
$fb = lap_invite_line(array('by' => array('fighter' => 80)), $LB);
ok(strpos($fb, 'Fighters Built') === false && strpos($fb, 'building Fighters') !== false,
   'the Fighters Built label is dropped into the sentence raw: ' . $fb);

/*
 * AND NOTHING THAT ADMITS TO A BATCH. "I'm reaching out to folks who
 * haven't been around" tells the reader they are one of a list, which
 * is the opposite of what a personal invitation is for -- worse than
 * saying nothing about their absence. Swept for, because the natural
 * way to write a fallback for "no history" is exactly that sentence.
 */
$both = lap_invite_text($heavy, $LB, true)
      . lap_invite_text(array('by' => array()), $LB, false)
      . lap_invite_text(array('by' => array('skullswap' => 2)), $LB, false);
foreach (array('folks', 'everyone who', 'members who', 'anyone who',
               'all of you', 'people who hav') as $batch) {
	if (!ok(stripos($both, $batch) === false,
	        'the invite reads as a batch send: "' . $batch . '"')) break;
}

/* No invented history: these go to people the owner has never
   messaged. */
$all = lap_invite_text($heavy, $LB, true) . lap_invite_text(array('by' => array()), $LB, false);
foreach (array('miss seeing you', 'last time we', 'good to hear', 'as always') as $phrase) {
	if (!ok(stripos($all, $phrase) === false,
	        'the invite implies a conversation that never happened: "' . $phrase . '"')) break;
}
/*
 * THE WAY IN. A message that asks somebody to remember a URL is a
 * message they do not act on. Checked for the trailing slash too,
 * because /staking 301s to /staking/ -- and for the bare host,
 * because the login cookie has no domain and a session on www is not
 * a session on skulliance.io.
 */
ok(strpos($all, 'https://skulliance.io/staking/') !== false,
   'the invite does not tell them where to go');
ok(stripos($all, 'www.skulliance.io') === false,
   'the invite sends them to www, which does not share a login session with the bare host');

/* And it must describe the gift as traits, not as a finished Fighter,
   because traits are what lands. */
ok(stripos($all, 'collection of traits') !== false,
   'the invite does not say the gift is traits, so it promises a built Fighter');

/* ---------------------------------------------------------------- *
 * THE OUTREACH LOG
 * ---------------------------------------------------------------- */
echo "\nwho has been written to survives closing the page\n";
require_once __DIR__ . '/lib/outreach-log.php';
/* The live log is a real file; move it aside for the run. */
$op = outreach_path(); $osave = $op . '.harness-save'; $ohad = is_file($op);
if ($ohad) @rename($op, $osave);
register_shutdown_function(function () use ($op, $osave, $ohad) {
	@unlink($op);
	if ($ohad && is_file($osave)) @rename($osave, $op);
});

/*
 * NO LOG MEANS NOBODY HAS BEEN WRITTEN TO. The inversion -- an
 * unreadable log reading as "everybody done" -- would empty the To
 * write list and look like finishing.
 */
@unlink($op);
ok(outreach_load() === array(), 'a missing outreach log does not read as empty');
ok(outreach_done(outreach_load(), '123') === false,
   'with no log everybody reads as already written to, which empties the list');

ok(outreach_mark('123', true) === true, 'marking somebody failed');
ok(outreach_done(outreach_load(), '123') === true, 'the mark did not survive a reload');
ok(outreach_done(outreach_load(), '456') === false, 'marking one person marked everybody');
/* Two in a row must both stick -- a write that replaces rather than
   merges would silently undo the previous one. */
outreach_mark('456', true);
$lg = outreach_load();
ok(outreach_done($lg, '123') && outreach_done($lg, '456'),
   'a second mark overwrote the first, so the log only ever holds one person');
ok(outreach_mark('123', false) === true && outreach_done(outreach_load(), '123') === false,
   'unmarking does not clear the row');
ok(outreach_done(outreach_load(), '456') === true, 'unmarking one person cleared everybody');
/* Junk cannot poison the file. */
ok(outreach_mark('', true) === false, 'an empty id is written to the log');
/* NOT IN THE RENDER CACHE. dhcrenders/ is emptied whenever a picture
   looks wrong, and this is a record of messages actually sent -- the
   one file here that must not sit somewhere disposable. */
ok(strpos(outreach_path(), '/dhcrenders/') === false,
   'the outreach log lives in the render cache, where clearing stale images deletes it');
ok(preg_match('~^/?data/~m', file_get_contents(__DIR__ . '/.gitignore')) === 1,
   'data/ is not gitignored, so one `git add -A` commits the outreach log');

/* ---- a different fighter each time they come back ---- */
echo "\na repeat return is a new package, and the first one did not move\n";
require_once __DIR__ . '/lib/return-gift.php';
$rid = '578386308406181918';
/*
 * THE REGRESSION THAT WOULD HAVE BEEN SILENT. Three hundred and
 * forty four previews are already out in DMs, each showing a
 * rendered file keyed on the traits. Salting lapse 0 would have
 * changed every one of them while the pictures already sent kept
 * showing the old fighter, and nothing would have errored.
 *
 * These eight are transcribed from the ledger of a member who
 * actually collected -- the real answer, not this code's answer.
 */
$promised = array('background' => 'light-purple', 'torso' => 'c38', 'head' => 'cyborg-stryker-02',
                  'arms' => 'robo-crusher-arms', 'weapon' => 'annihilation-belt',
                  'headgear' => 'malware-detecting-mask', 'effects' => 'digital-fire',
                  'companion' => 'dh-vision-shoulder-cam');
$first = return_gift_traits($rid, 0);
ksort($first); ksort($promised);
ok($first === $promised,
   'the first package no longer matches what was already promised in DMs');
ok(return_gift_traits($rid) === return_gift_traits($rid, 0),
   'the default lapse is not 0, so an un-salted call differs from a first package');

$second = return_gift_traits($rid, 1);
ok($second !== $first, 'a second lapse hands over the same eight traits again');
ok(count($second) === count($first), 'a later package is a different size');
ok(return_gift_traits($rid, 1) === $second, 'the second package is not stable between calls');
ok(return_gift_url($rid, 1) !== return_gift_url($rid, 0),
   'both packages render to the same file, so the preview cannot tell them apart');

/* Every rule still holds on a later package, not just the first. */
$rar = dhcf_rarity();
$allow2 = array('common' => 1, 'uncommon' => 1);
$bad2 = array();
for ($lp = 1; $lp <= 4; $lp++) {
	$t = return_gift_traits($rid, $lp);
	if (count($t) !== 8) $bad2[] = "lapse $lp has " . count($t) . ' traits';
	foreach ($t as $c => $sl) {
		$tier = isset($rar[$c][$sl][0]) ? $rar[$c][$sl][0] : '?';
		if (!isset($allow2[$tier])) $bad2[] = "lapse $lp $c/$sl is $tier";
	}
	$h = isset($t['head']) ? $t['head'] : ''; $a2 = isset($t['arms']) ? $t['arms'] : '';
	if (isset($t['headgear'])  && dhcf_headgear_blocked($h, $t['headgear']))  $bad2[] = "lapse $lp headgear clash";
	if (isset($t['companion']) && dhcf_companion_blocked($h, $t['companion'])) $bad2[] = "lapse $lp companion clash";
	if (isset($t['weapon'])    && dhcf_arms_weapon_clash($a2, $t['weapon']))   $bad2[] = "lapse $lp weapon clash";
}
ok(!$bad2, 'later packages break the rules the first one follows: ' . implode(' | ', array_slice($bad2, 0, 3)));

/* The lapse count must ignore the package being handed over right
   now, or a resumed grant finishes with traits from a different set. */
$rgs = harness_strip_comments(file_get_contents(__DIR__ . '/lib/return-gift.php'));
ok(strpos($rgs, 'awarded_at <= DATE_SUB(NOW(), INTERVAL $days DAY)') !== false,
   'the lapse count includes the current window, so a resumed grant switches to a different package');
ok(strpos($rgs, 'COUNT(DISTINCT DATE(awarded_at))') !== false,
   'packages are counted by row rather than by day, which miscounts any grant that was resumed');

/* ---- catching up on a batch ---- */
echo "\nmarking many is one write, and does not lose the others\n";
/*
 * ONE WRITE, NOT A LOOP. Seventy eight calls to outreach_mark() is
 * seventy eight read-modify-write cycles on the same file, and any
 * two that overlap lose each other -- which is exactly the data this
 * exists to not lose.
 */
outreach_mark('111', true);
$batch = array();
for ($i = 1; $i <= 78; $i++) $batch[] = (string)(700000000000000000 + $i);
ok(outreach_mark_many($batch, true) === 78, 'the batch did not apply every id');
$lg2 = outreach_load();
ok(outreach_done($lg2, $batch[0]) && outreach_done($lg2, $batch[77]),
   'the batch lost ids at one end, so part of the selection was not saved');
ok(outreach_done($lg2, '111'),
   'the batch replaced the log instead of merging, wiping everybody marked before it');
ok(count($lg2) === 80, 'the batch wrote ' . count($lg2) . ' rows, expected 80');
ok(outreach_mark_many($batch, false) === 78 && count(outreach_load()) === 2,
   'unmarking a batch does not clear exactly that batch');
ok(outreach_mark_many(array(), true) === 0, 'an empty batch is reported as work done');
ok(outreach_mark_many(array('', 'abc'), true) === 0, 'junk ids are written to the log');
@unlink($op);

/* The endpoint must take the batch as a batch. */
$oe0 = harness_strip_comments(file_get_contents(__DIR__ . '/ajax/admin-outreach.php'));
ok(strpos($oe0, 'outreach_mark_many(') !== false,
   'the endpoint has no batch path, so the browser must send one request per person');
ok(preg_match('~foreach[^;]*outreach_mark\(~', $oe0) !== 1,
   'the endpoint loops single marks, which is one file rewrite per person');

/* ---- the filter that makes the list shorter ---- */
echo "\nthe To write list drops what is not work\n";
ok(strpos($src, "\$al_shows = array('todo', 'sent', 'left', 'all')") !== false,
   'the show filter is gone or no longer whitelisted');
/*
 * THE MESSAGE FOR SOMEBODY WHO LEFT HAS TO BE REACHABLE. The invite
 * line only renders for them, and they are deliberately kept out of
 * the To write list, so without their own view the only way to find
 * them was to scroll Everyone.
 */
ok(strpos($src, "\$al_show === 'left'") !== false,
   'people who left the server have no view of their own, so their invite is unreachable');
ok(strpos($src, 'LAP_DISCORD_INVITE') !== false,
   'the message carries no way back into Discord');
ok(strpos($src, 'discord.gg/JqqBZBrph2') !== false,
   'the Discord invite is not the live SKULLIANCE one');
/* It must be CONDITIONAL: a current member told they are not in the
   server reads as a mail merge, which is the thing to avoid. */
$inv_in  = lap_invite_text($heavy, $LB, false, false);
$inv_out = lap_invite_text($heavy, $LB, false, true);
ok(strpos($inv_in, 'discord.gg') === false,
   'a member still in the server is invited to rejoin it');
ok(strpos($inv_out, 'discord.gg') !== false,
   'somebody who left is not given a way back in');
/* The default is 'all' so an in-progress pass is not renumbered;
   what matters is that it is one of the three and not something the
   whitelist would reject, which would silently show everyone anyway. */
ok(preg_match("~\\? \\\$_GET\['show'\] : '(todo|sent|all)'~", $src) === 1,
   'the default view is not one of the three the filter accepts');
/*
 * UNKNOWN MUST STAY IN THE LIST. Dropping it alongside "left" would
 * hide everybody whenever the roster cache is missing -- the same
 * failure guild-members.php guards against, one layer up.
 */
ok(strpos($src, "guild_member_in(\$al_guild, \$u['discord_id']) === false) unset") !== false,
   'the todo filter drops rows on anything other than a confirmed "left the server"');

/* ---- the endpoint ---- */
$oe = harness_strip_comments(file_get_contents(__DIR__ . '/ajax/admin-outreach.php'));
ok(strpos($oe, "!== 'POST'") !== false, 'the outreach endpoint accepts GET');
$og = strpos($oe, 'adminRights()');
$ow = strpos($oe, 'outreach_mark(');
ok($og !== false && $ow !== false && $og < $ow,
   'the outreach endpoint writes before it checks who is asking');

echo "\nread only\n";
/* It is a list to work from. Nothing is marked, nothing is sent. */
foreach (array('INSERT', 'UPDATE ', 'DELETE') as $w) {
	ok(stripos($src, $w) === false, "admin-lapsed.php performs a $w");
}
ok(strpos($src, 'admin_require()') !== false, 'the page is not behind the admin gate');
ok(strpos($src, "include 'admin-css.php'") !== false,
   'the .adm wrapper is opened without its styling');
$tabs = harness_strip_comments(file_get_contents(__DIR__ . '/admin-lib.php'));
ok(strpos($tabs, "'lapsed'") !== false, 'the Lapsed tab is not in the admin nav');

/* ---------------------------------------------------------------------------
 * THE FOLLOW-UP, for somebody who took the package and then stalled.
 *
 * 17 came back, 8 built a Fighter, 5 signed in again -- so the interesting
 * question stopped being "will they return" and became "what stopped them".
 * This message exists to get an answer, which puts two things at risk that
 * the invite does not have: it must ask the RIGHT question of the right
 * person, and it must not read like a mail merge to somebody who is being
 * asked for a personal favour.
 * ------------------------------------------------------------------------- */
echo "\nthe follow-up\n";
$lift_follow = lap_lift($src, 'function lap_followup_text(');
ok($lift_follow !== '', 'lap_followup_text() could not be lifted, so nothing below ran');
if ($lift_follow !== '') {
	eval($lift_follow);

	/* The three situations are three different people. Each has to get its
	   own question, or two of them are being guessed at. */
	$cold  = lap_followup_text('n', 40, 9,  false, false);   // took it, vanished
	$near  = lap_followup_text('n',  3, 12, false, true);    // around, never built
	$gone  = lap_followup_text('n', 20, 9,  true,  false);   // built, then quiet
	ok($cold !== $near && $near !== $gone && $cold !== $gone,
	   'two of the three situations get the same message, so at least one person is being '
	 . 'asked a question that does not apply to them');
	ok(strpos($near, 'builder') !== false,
	   'somebody who is signing in and still has not assembled anything is not asked about '
	 . 'the builder, which is the only one of the three where the answer is likely a fix');
	ok(strpos($gone, 'Fighter') !== false,
	   'somebody who DID build is not told so, and reads a message written for someone who '
	 . 'did not');
	ok(strpos($cold, 'not see') !== false,
	   'the coldest case does not offer "you never saw them" as an out -- that is much '
	 . 'easier to agree with than to volunteer, and it is the likeliest answer');

	/* IT ASKS AND STOPS. A question with a pitch under it gets the pitch
	   ignored and the question with it; the invite already does the selling. */
	foreach (array('cold' => $cold, 'near' => $near, 'gone' => $gone) as $k => $t) {
		ok(strpos($t, 'http') === false, "the $k follow-up carries a link, so it reads as a "
		 . 'second invite to somebody who already accepted the first');
		ok(stripos($t, 'new feature') === false && stripos($t, 'TON of') === false,
		   "the $k follow-up pitches the platform instead of asking a question");
		ok(substr_count($t, '?') >= 1, "the $k follow-up never actually asks anything");
		ok(strpos($t, '  ') === false && strpos($t, " \n") === false,
		   "the $k follow-up has doubled or trailing whitespace from a concatenation");
	}

	/*
	 * PRONOUNS AGREE WITH THE COUNT. "1 trait landed ... do anything with
	 * them" was in the first version and is exactly the tell that a message
	 * was generated -- on the one message whose whole purpose is to read as
	 * though a person wrote it. Caught by reading the output, not by a test,
	 * so there is a test now.
	 */
	foreach (array(0, 1, 2, 11) as $n) {
		foreach (array(array(false, false), array(false, true), array(true, false)) as $st) {
			$t = lap_followup_text('n', 10, $n, $st[0], $st[1]);
			if ($n === 1) {
				ok(strpos($t, '1 traits') === false, "'1 traits' in the follow-up at n=1");
				ok(!preg_match('/\b1 trait\b.*\bthem\b/s', $t),
				   'the follow-up says "1 trait" and then calls it "them"');
				ok(!preg_match('/\b1 trait\b.*\bthey are\b/s', $t),
				   'the follow-up says "1 trait" and then "they are"');
			} elseif ($n > 1) {
				ok(strpos($t, $n . ' traits') !== false || strpos($t, 'Fighter') !== false,
				   "the follow-up at n=$n neither counts the traits nor names the Fighter");
			}
		}
	}
}

echo "\nthe follow-up log is its own\n";
require_once __DIR__ . '/lib/outreach-log.php';
/*
 * A MARK ON ONE LOG MUST NOT SHOW ON THE OTHER. The invite and the
 * follow-up are different messages to the same person, and the keys are
 * stripped to digits -- so a namespace prefix would be sanitised straight
 * back off and both would land in one file.
 */
ok(outreach_path() !== outreach_path(OUTREACH_LIST_ASK),
   'the follow-up log and the invite log are the same file, so marking somebody asked '
 . 'also marks them written to');
ok(strpos(basename(outreach_path(OUTREACH_LIST_ASK)), 'ask') !== false,
   'the follow-up log is not named for what it holds');
/* It lands in a path, so it cannot be whatever a caller passes. Tested on
   the BASENAME: the base is __DIR__ . '/../data/', which legitimately
   contains '..', so searching the whole path for it fails correct code --
   which is what the first version of this check did. */
ok(basename(outreach_path('../../etc/passwd')) === 'outreach-etcpasswd.json',
   'outreach_path() does not strip a traversal out of the suffix: got '
 . basename(outreach_path('../../etc/passwd')));
ok(strpos(basename(outreach_path('a/b')), '/') === false,
   'a slash in the suffix survives into the filename');
ok(dirname(outreach_path(OUTREACH_LIST_ASK)) === dirname(outreach_path()),
   'the two logs are not in the same directory');
/* And the endpoint must refuse a list it does not know, or an unknown name
   quietly creates a third log nothing ever reads. */
$oa = harness_strip_comments(file_get_contents(__DIR__ . '/ajax/admin-outreach.php'));
ok(strpos($oa, 'OUTREACH_LIST_ASK') !== false,
   'ajax/admin-outreach.php does not know about the follow-up log');
ok(strpos($oa, 'Unknown list') !== false,
   'the endpoint accepts any list name, so a typo writes to a file nothing reads');

echo "\nthe button only appears where there is something to ask\n";
$pg = harness_strip_comments($src);
ok(strpos($pg, 'lap-askbtn') !== false, 'the Came back table has no follow-up button');
ok(strpos($pg, "empty(\$al_built[\$uid]) || !\$seen") !== false,
   'the follow-up button is offered to everybody, including people who built a Fighter AND '
 . 'came back again -- for whom there is no question');
ok(strpos($pg, 'outreach_load(OUTREACH_LIST_ASK)') !== false,
   'the page never loads the follow-up log, so every button reads as un-asked forever');
/* The dialog writes, so it must send the list name or it marks the invite. */
ok(preg_match('/list=.{0,40}OUTREACH_LIST_ASK/s', $src) === 1,
   'the follow-up dialog posts without naming its list, so marking somebody asked marks '
 . 'them as having had the invite instead');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all lapsed checks passed\n");
exit($fail ? 1 : 0);
