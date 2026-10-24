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
/* BOTH QUERIES, COUNTED. This checked for the string and passed while
   one of the two still had it -- changing the source in the returns
   list alone left the check green and the panel reporting nobody. */
ok(substr_count($src, "source = 'returngift'") === 2,
   'the returns list and the Fighter join must both key on the welcome-back package; found '
 . substr_count($src, "source = 'returngift'") . ' of 2');
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

/* No invented history: these go to people the owner has never
   messaged. */
$all = lap_invite_text($heavy, $LB, true) . lap_invite_text(array('by' => array()), $LB, false);
foreach (array('miss seeing you', 'last time we', 'good to hear', 'as always') as $phrase) {
	if (!ok(stripos($all, $phrase) === false,
	        'the invite implies a conversation that never happened: "' . $phrase . '"')) break;
}
/* And it must describe the gift as traits, not as a finished Fighter,
   because traits are what lands. */
ok(stripos($all, 'collection of traits') !== false,
   'the invite does not say the gift is traits, so it promises a built Fighter');

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

echo "\n" . ($fail ? "$fail check(s) FAILED\n" : "all lapsed checks passed\n");
exit($fail ? 1 : 0);
