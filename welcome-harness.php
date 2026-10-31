<?php
/**
 * welcome-harness.php — the newcomers panel.
 *
 *   php welcome-harness.php
 *
 * WHAT IS AT RISK HERE. The page sorts real people into three groups and
 * writes a different message to each, so putting somebody in the wrong
 * one sends them a message about a problem they do not have -- asking
 * somebody who is staking happily why they left. The grouping is pure and
 * is driven directly below; everything touching Discord is checked by
 * reading the source, because no Discord call is made from a harness.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
if (!isset($bot_token)) $bot_token = '';
require_once __DIR__ . '/lib/welcome-channel.php';
require_once __DIR__ . '/lib/outreach-log.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$VIS = '111'; $CAND = '222'; $OTHER = '333';

echo "who goes in which group\n";
$cases = array(
	// in_guild, roles,                     expected, why
	array(false, array(),                   'gone',      'left the server'),
	array(false, array($VIS, $CAND),        'gone',      'left, with roles still cached'),
	array(true,  array($VIS),               'visitor',   'in, holding only Visitor'),
	array(true,  array($CAND),              'candidate', 'in, holding only Candidate'),
	array(true,  array($VIS, $CAND),        'candidate', 'holds BOTH -- the gate failed to strip Visitor'),
	array(true,  array($CAND, $OTHER),      '',          'got further than Candidate'),
	array(true,  array($OTHER),             '',          'a role we do not track'),
	array(true,  array(),                   '',          'in the server holding nothing'),
	array(true,  null,                      '',          'roster cannot say what they hold'),
);
foreach ($cases as $c) {
	$got = welcome_group($c[0], $c[1], $VIS, $CAND);
	ok($got === $c[2], "somebody who $c[3] is grouped as '"
	   . ($got === '' ? 'none' : $got) . "', expected '" . ($c[2] === '' ? 'none' : $c[2]) . "'");
}

/*
 * NULL IS NOT AN EMPTY ROLE LIST, and this is the one that would do real
 * damage quietly. A roster cached before roles were captured knows a
 * member is present and nothing about what they hold. Read as "holds no
 * roles" that is harmless here -- but read as "holds neither Visitor nor
 * Candidate, so they must be new" it would write to every long-standing
 * member in the server.
 */
ok(welcome_group(true, null, $VIS, $CAND) === '',
   'an unknown role list is treated as a known one, so a stale roster puts established '
 . 'members into a newcomer group');
/*
 * AND THE REFRESH HAS TO STORE THEM. Reading roles back is useless if
 * nothing writes them -- and because every group falls through to ''
 * when the roles are unknown, that failure is SILENT: the page simply
 * shows nobody in the Visitor or Candidate buckets, which looks exactly
 * like a week when nobody got stuck.
 */
$gm = file_get_contents(__DIR__ . '/lib/guild-members.php');
ok(strpos($gm, "'roles' => \$roles") !== false,
   'guild_members_refresh() no longer writes roles into the cache, so the Visitor and '
 . 'Candidate groups are permanently empty and look like good news');
ok(strpos($gm, "\$m['roles']") !== false,
   'the refresh does not read roles out of the Discord response');
ok(guild_member_roles(array('ids' => array('5' => true)), '5') === null,
   'a roster with no roles key reports an empty role list rather than "unknown"');
ok(guild_member_roles(array('roles' => array('5' => array('9'))), '5') === array('9'),
   'roles are not read back out of the cache');

/* Order matters: the gate adds Candidate and removes Visitor, so a
   half-applied change leaves both and the further-along answer is right. */
ok(welcome_group(true, array($VIS, $CAND), $VIS, $CAND) === 'candidate',
   'somebody holding both roles is treated as a Visitor, so a member who passed the gate '
 . 'is asked why they could not pass it');

/* With the role ids unset, nothing may be asserted about anybody. */
ok(welcome_group(true, array($VIS), '', '') === '',
   'with no role ids configured, members are still being sorted into groups -- on no '
 . 'evidence at all');

echo "\nthree groups, three messages\n";
$gone = welcome_text('gone'); $vis = welcome_text('visitor'); $cand = welcome_text('candidate');
ok($gone !== '' && $vis !== '' && $cand !== '', 'a group has no message');
ok($gone !== $vis && $vis !== $cand && $gone !== $cand,
   'two groups get the same message, so at least one person is asked about a problem they '
 . 'do not have');
ok(welcome_text('') === '' && welcome_text('nonsense') === '',
   'an unknown group still produces a message, which would be sent to somebody arbitrary');

/* Each has to name its own subject, or the split achieves nothing. */
ok(stripos($vis, 'rules') !== false,  'the Visitor message never mentions the rules questions');
ok(stripos($cand, 'wallet') !== false && stripos($cand, 'stak') !== false,
   'the Candidate message never mentions the wallet or staking, which is the only thing '
 . 'standing between them and being a member');
ok(stripos($gone, 'left') !== false, 'the message to somebody who left does not acknowledge it');

/*
 * NO LINK IN ANY OF THEM. Somebody stuck at the rules gate cannot use a
 * staking link, and somebody who has left is not being sold to again.
 */
foreach (array('gone' => $gone, 'visitor' => $vis, 'candidate' => $cand) as $k => $t) {
	ok(strpos($t, 'http') === false, "the $k message carries a link");
	ok(substr_count($t, '?') >= 1, "the $k message never actually asks anything");
	/* The owner's voice: open friendly, offer help, do not scold. */
	ok(stripos($t, 'Hey!') === 0, "the $k message does not open the way the others do");
	ok(strpos($t, '  ') === false, "the $k message has a doubled space from a concatenation");
}
/* And the one to somebody who left must not read as a complaint. */
ok(stripos($gone, 'no hard feelings') !== false,
   'the message to somebody who left reads as a reproach rather than a question');

echo "\nreading the channel\n";
$src = file_get_contents(__DIR__ . '/lib/welcome-channel.php');
/*
 * TWO MESSAGE SHAPES. type 7 is Discord's own join message and its AUTHOR
 * is the joiner; anything else is a greeting, where the joiner is the
 * first MENTION and the author is the greeter. A reader that knows only
 * the second builds a list of one name repeated -- whoever does the
 * welcoming -- and a reader that knows only the first returns nothing at
 * all on a server that uses a bot.
 */
ok(strpos($src, '$type === 7') !== false, 'the reader does not understand a Discord join message');
ok(strpos($src, "mentions'][0]") !== false, 'the reader cannot read a bot or human welcome');
ok(strpos($src, "empty(\$who['bot'])") !== false,
   'bots welcomed in the channel are listed as arrivals');
ok(strpos($src, 'isset($people[$id])') !== false,
   'somebody welcomed twice appears twice, and gets written to twice');
/* Every refusal says what to do about it, or an empty page is
   indistinguishable from a quiet week. */
foreach (array('403', '404', '429') as $code) {
	ok(strpos($src, $code) !== false, "an HTTP $code from Discord is not explained");
}
ok(stripos($src, 'Read Message History') !== false,
   'a 403 does not name the permission that fixes it');

echo "\nthe page\n";
$pg = file_get_contents(__DIR__ . '/admin-welcome.php');
ok(strpos($pg, 'admin_require();') !== false, 'the newcomers panel is not behind the admin gate');
ok(strpos($pg, "admin_require();   // before any output") !== false
   || strpos($pg, 'admin_require()') < strpos($pg, "include 'header.php'"),
   'admin_require() runs after output has started, so the redirect cannot fire');
/*
 * SCOPED TO THE POST BODY. A bare search for the constant passes on its
 * own define() and on the outreach_load() call, so it kept passing with
 * the list dropped from the request -- which would mark newcomers on the
 * LAPSED invite log instead.
 */
ok(strpos($pg, 'OUTREACH_LIST_WELCOME') !== false,
   'the page does not name its own outreach log at all');
$fetchAt = strpos($pg, "fetch('ajax/admin-outreach.php'");
ok($fetchAt !== false, 'the page never posts a mark');
if ($fetchAt !== false) {
	/* The body is assembled just above the fetch. */
	$bodyAt = strpos($pg, "body = 'discord_id=");
	ok($bodyAt !== false && strpos(substr($pg, $bodyAt, 400), 'OUTREACH_LIST_WELCOME') !== false,
	   'the mark is posted without naming its list, so writing to a newcomer marks them as '
	 . 'having had the lapsed invite');
}
/*
 * NO FORM FIELD MAY COLLIDE WITH skulliance.php, which is included by
 * every signed-in page and reads $_POST AT INCLUDE TIME. Its
 * `if(isset($_POST['refresh']))` is the WALLET refresh and calls
 * verifyNFTs() out of verify.php, which this page does not include -- so
 * a button named `refresh` took the whole page down with "Call to
 * undefined function verifyNFTs()" before a line of it ran. Reported on
 * the first press.
 */
$skl = file_get_contents(__DIR__ . '/skulliance.php');
/*
 * ONLY THE BARE ONES. A field is dangerous when the WHOLE condition is
 * `if (isset($_POST['x'])) {` -- that fires on any page that happens to
 * post it. $_POST['name'] appears in skulliance.php too, but inside a
 * compound condition precisely because it caused this once before: it
 * used to engage on the name alone and the Monstrocity boss form started
 * reporting an item-submission error on a save that worked. Matching
 * every mention would flag three admin pages that are already fine.
 */
preg_match_all('/if\s*\(\s*isset\(\$_POST\[\x27([a-z_]+)\x27\]\)\s*\)\s*\{/i', $skl, $sm);
$taken = array_unique($sm[1]);
preg_match_all('/name="([a-z_]+)"/i', $pg, $pm);
$clash = array_intersect(array_unique($pm[1]), $taken);
ok(!$clash,
   'this page posts field(s) that skulliance.php also reads at include time, so pressing '
 . 'the button runs ITS handler and not ours: ' . implode(', ', $clash)
 . '. Prefix them (aw_).');
ok(in_array('refresh', $taken, true),
   'the collision check found no $_POST reads in skulliance.php at all, so it is passing '
 . 'vacuously -- the pattern has probably rotted');

/*
 * AND THE DIALOG MUST CARRY ITS OWN STYLING. The .lap-m* rules live in
 * admin-lapsed.php's inline <style>, not in admin-css.php, so borrowing
 * the class names gave a backdrop with no position: removing [hidden]
 * revealed an unstyled block at the foot of the page and the button
 * looked dead. Reported as "the write button doesn't work".
 */
ok(!preg_match('/class="[^"]*\blap-/', $pg),
   'the dialog uses .lap-* classes, which are defined in admin-lapsed.php and not on this '
 . 'page, so it opens unstyled and reads as a dead button');
foreach (array('.aw-mw', '.aw-mp', '.aw-mu', '.aw-mc') as $cls) {
	ok(strpos($pg, $cls . ' {') !== false || strpos($pg, $cls . '[') !== false,
	   "the dialog markup uses $cls and the page never defines it");
}
ok(strpos($pg, 'position:fixed') !== false,
   'the dialog backdrop is not positioned, so it renders inline at the foot of the page '
 . 'instead of over it');

/* The refresh writes, so it must not be a GET. */
ok(strpos($pg, "REQUEST_METHOD'] === 'POST'") !== false,
   'the roster refresh is reachable by GET, so an <img src> can fire it');
ok(strpos($pg, 'header(\'Location: admin-welcome.php\');') !== false,
   'the refresh does not redirect, so reloading repeats it');
/*
 * THE THREE TOTALS DO NOT ADD UP TO THE ARRIVALS, AND THAT MUST BE SAID.
 * Somebody in the server whose roles we cannot read belongs to no group,
 * so while the roster has no roles the only bucket that can fill is
 * Left -- 98 of 98 read as "left", including someone welcomed that
 * morning, and it looked like a broken lookup. It was not; it was the
 * page declining to say what it could not see.
 */
/* The COUNTING, not the array it counts into: a bare search for
   $aw_skip passes on the initialiser and on the display block, so it
   kept passing with the two increments deleted. */
ok(strpos($pg, "\$aw_skip['noroles']++") !== false,
   'the page does not count the arrivals it put in no group, so three zeroes look like a '
 . 'broken lookup rather than a roster with no roles in it');
ok(strpos($pg, "\$aw_skip['settled']++") !== false,
   'the page does not count the arrivals who got further than Candidate');
ok(strpos($pg, 'cannot say what roles they hold') !== false,
   'the page never explains why the group totals are smaller than the arrivals read');
ok(strpos($pg, '$aw_found') !== false,
   'the page does not say how many arrivals it read, so there is nothing to compare the '
 . 'group totals against');
ok(strpos($pg, "count((array)\$aw_cache['ids'])") !== false,
   'the page does not show how big the roster is -- a fetch that got one page of a large '
 . 'server looks healthy until everybody reads as having left');
/* The two reasons for falling through mean opposite things. */
/* And told apart BY THE RIGHT TEST. Merging them into one counter is
   the failure that matters -- a gap in what we know would then read as
   good news. */
ok(preg_match('/\$roles === null\)\s*\$aw_skip/', $pg) === 1,
   'a member whose roles we cannot read and a member who is doing fine are counted '
 . 'together, so a gap in what we know is indistinguishable from good news');

/*
 * THE LAST REFRESH OUTCOME HAS TO SURVIVE A PAGE LOAD.
 *
 * Every caller showed it once and lost it, so three refreshes could fail
 * for the same reason without the reason ever being read -- the only
 * visible symptom was a stale roster with no explanation attached, and
 * the cache file cannot record a refresh that wrote nothing. Asked for
 * that error three times over a single afternoon before this went in.
 */
$gmf = file_get_contents(__DIR__ . '/lib/guild-members.php');
$body = substr($gmf, strpos($gmf, 'function guild_members_refresh'));
/*
 * SPLIT ON THE RETURN, not scanned line by line. The failure paths come
 * in two shapes -- a one-liner ending in `return $out; }` and a
 * multi-line concatenation -- and a per-line rule that handles one
 * misses the other. The first version of this required the line to end
 * in a semicolon and so skipped every one-liner, which is most of them.
 * Each chunk ending in a return that set an error must record it.
 */
$unrecorded = 0;
foreach (explode('return $out;', $body) as $chunk) {
	if (strpos($chunk, "\$out['error'] =") === false) continue;
	if (strpos($chunk, 'guild_members_note') === false) $unrecorded++;
}
ok($unrecorded === 0,
   "$unrecorded failure path(s) in guild_members_refresh() return without recording why, "
 . 'so that refresh leaves no trace at all');
ok(strpos($gmf, 'function guild_members_note(') !== false
   && strpos($gmf, 'function guild_members_last(') !== false,
   'the refresh outcome is not persisted, so it is lost on the next page load');
ok(strpos($gmf, 'guild_members_note(true') !== false,
   'only failures are recorded, so a page cannot tell "never tried" from "worked"');
ok(strpos($pg, 'guild_members_last()') !== false,
   'the page does not read the last refresh outcome, so the error still only flashes once');
ok(strpos($pg, 'Last refresh failed') !== false,
   'the page never surfaces a failed refresh');

/*
 * AND ONE MEMBER CAN BE INTERROGATED. Three rounds went on "this person
 * holds Candidate and is not in the Candidate list", every one of them
 * asking for something off the server that the page could have said.
 */
foreach (array('In the roster cache'     => 'whether the roster has them at all',
               'Roles the cache holds'   => 'what roles it has for them',
               'Configured Visitor id'   => 'what it is comparing those against',
               'In the welcome channel'  => 'whether the channel read found them') as $needle => $what) {
	ok(strpos($pg, $needle) !== false,
	   "the id lookup does not report $what, which is one of the four things that can be wrong");
}
ok(strpos($pg, 'UNKNOWN (cache has no roles)') !== false,
   'the lookup shows an empty role list where the cache simply has none, which are the two '
 . 'states that most need telling apart');

/* Every reason the list can be empty has to be on the page. */
foreach (array('Server Members Intent' => 'the intent that makes the roster possible',
               'Refresh roster'        => 'the button that fixes an empty roster',
               'predates role capture' => 'a cache too old to hold roles',
               'webhooks_credentials'  => 'the file the role ids live in') as $needle => $what) {
	ok(strpos($pg, $needle) !== false,
	   "the page never mentions $what, so an empty list looks like a quiet week");
}

echo "\nthe three logs stay apart\n";
$paths = array(outreach_path(), outreach_path(OUTREACH_LIST_ASK), outreach_path(OUTREACH_LIST_WELCOME));
ok(count(array_unique($paths)) === 3,
   'two of the three outreach logs are the same file, so marking one message sent marks '
 . 'another: ' . implode(', ', array_map('basename', $paths)));
$oa = file_get_contents(__DIR__ . '/ajax/admin-outreach.php');
ok(strpos($oa, 'OUTREACH_LIST_WELCOME') !== false,
   'the endpoint does not know about the newcomer log, so every mark from this page is '
 . 'refused as an unknown list');
ok(strpos($oa, 'Unknown list') !== false,
   'the endpoint accepts any list name, so a typo writes to a file nothing reads');

echo "\nthe tab exists\n";
$lib = file_get_contents(__DIR__ . '/admin-lib.php');
ok(strpos($lib, "'welcome'") !== false && strpos($lib, 'admin-welcome.php') !== false,
   'the Newcomers tab is not in the admin nav, so the page can only be reached by typing '
 . 'the URL');

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all newcomer checks passed\n");
exit($fail ? 1 : 0);
