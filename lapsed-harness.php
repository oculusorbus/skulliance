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
ok(strpos($src, "\$_GET['sort'] === 'coldest'") !== false, 'the sort cannot be flipped');
/* DESC on last_login is most-recent-login first, which is least time
   away. Getting this backwards is the one bug that would look fine. */
ok(strpos($src, "(\$al_sort === 'coldest' ? 'ASC' : 'DESC')") !== false,
   'the ordering is not last_login DESC for warmest -- the list is upside down');
ok(preg_match("~\\\$al_sort = .*'coldest'.*\\? 'coldest' : 'warmest'~", $src) === 1,
   'the default sort is not warmest');

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
