<?php
/**
 * nudge-harness.php — the daily suggestion, driven over real footprints.
 *
 * Run: php nudge-harness.php
 *
 * THE RISK IS NOT A CRASH, IT IS NAGGING THE WRONG PEOPLE. A nudge shown
 * to somebody who has already played everything is noise, and a nudge
 * that repeats the same card forever is worse than none. Neither failure
 * raises an error, so both are checked by running the real selection over
 * the real footprints off the Activity wall.
 *
 * The rows below are transcribed from that page, including the players
 * the owner marked as targets and the ones he left alone -- so this is a
 * test against his judgement, not against mine.
 */
require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/nudge-lib.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return true; }
	$fail++; echo "  FAIL  $msg\n"; return false;
}

/* MARKED AS TARGETS by the owner. */
$narrow = array(
	'bigredcrypto'     => array('mission' => 1840, 'daily' => 12, 'fighter' => 2),
	'scorba'           => array('mission' => 1397, 'daily' => 12, 'dropship' => 10, 'gauntlet' => 2),
	'speakeasy51'      => array('mission' => 668, 'raid' => 106, 'daily' => 2, 'skullswap' => 1),
	'risingphoenix.ii' => array('mission' => 641, 'dropship' => 14, 'daily' => 7),
	'cobreean.pxlz'    => array('mission' => 597, 'daily' => 13),
	'neimo'            => array('mission' => 218, 'daily' => 10),
	'president_crypto' => array('mission' => 216, 'daily' => 8),
	'7ragon'           => array('mission' => 216, 'daily' => 1),
	'.goatrodeo'       => array('mission' => 39, 'daily' => 1),
	'rezen.rez'        => array('mission' => 9),
	'sts0541'          => array('mission' => 1),
);
/* LEFT ALONE: players already spread across the platform. */
$broad = array(
	'oculusorbus' => array('mission'=>2847,'gauntlet'=>780,'boss'=>245,'crawl'=>214,'arena'=>142,
	                       'skullswap'=>77,'raid'=>64,'conquest'=>61,'fighter'=>61,'obscura'=>40,
	                       'racer'=>25,'dropship'=>16,'daily'=>10,'monstrocity'=>5,'guardians'=>1),
	'fomz'        => array('mission'=>610,'boss'=>53,'crawl'=>37,'obscura'=>17,'gauntlet'=>12,
	                       'raid'=>11,'daily'=>9,'arena'=>7,'skullswap'=>6,'conquest'=>6,
	                       'fighter'=>6,'guardians'=>2,'racer'=>1,'dropship'=>1),
	'thepriebe'   => array('crawl'=>23,'mission'=>20,'raid'=>4,'conquest'=>1,'obscura'=>1,'dropship'=>1),
	'inguada'     => array('mission'=>138,'skullswap'=>12,'gauntlet'=>12,'crawl'=>9,'obscura'=>8,
	                       'daily'=>6,'conquest'=>1,'racer'=>1,'guardians'=>1,'fighter'=>1),
);

echo "\nwho gets nudged is about breadth, not score\n";
/*
 * THE WHOLE POINT. bigredcrypto is ninth on the platform by weighted
 * score and touches three things; thepriebe is near the bottom and
 * touches six. Ranking by activity would nudge exactly the wrong one.
 */
foreach ($narrow as $who => $fp) {
	if (!ok(nudge_breadth($fp) <= NUDGE_NARROW_MAX,
	        "$who has " . nudge_breadth($fp) . ' sources and would NOT be nudged')) break;
}
foreach ($broad as $who => $fp) {
	if (!ok(nudge_breadth($fp) > NUDGE_NARROW_MAX,
	        "$who has " . nudge_breadth($fp) . ' sources and WOULD be nudged')) break;
}
ok(nudge_breadth(array('mission' => 1840, 'daily' => 0, 'arena' => 0)) === 1,
   'a zero count is being read as having played that thing');
ok(nudge_breadth(array()) === 0, 'an empty footprint has breadth');

echo "\nthe suggestion is one they can act on today\n";
/*
 * UNUSED TRAITS OUTRANK EVERYTHING, because the reason is a number off
 * their own account and the barrier is zero. bigredcrypto has built two
 * Fighters, so they know the assembler exists -- what they may not know
 * is that they are sitting on parts for more.
 */
$facts = array('has_realm' => false, 'fighters' => 2, 'free_traits' => 31);
$pick  = nudge_pick(nudge_candidates($narrow['bigredcrypto'], $facts), array());
ok($pick && $pick['key'] === 'fighter', 'unused traits did not win: ' . ($pick ? $pick['key'] : 'nothing'));
ok($pick && strpos($pick['why'], '31') !== false,
   'the reason does not name their own trait count: ' . ($pick ? $pick['why'] : ''));

/* Two traits is not enough to build with -- the minimum Fighter needs
   background, torso and head. Offering it would send them to a page that
   refuses them, which is worse than suggesting nothing. */
$facts2 = array('has_realm' => false, 'fighters' => 0, 'free_traits' => 2);
$pick2  = nudge_pick(nudge_candidates($narrow['cobreean.pxlz'], $facts2), array());
ok($pick2 && $pick2['key'] !== 'fighter',
   'a player with 2 spare traits is told to assemble a Fighter, which needs 3');
ok($pick2 && $pick2['key'] === 'realm', 'the realm did not come next: ' . $pick2['key']);

echo "\nand never something they have already done\n";
/* speakeasy51 raids, so "you have never sent anyone out of your realm"
   would be a lie told to their face. */
$facts3 = array('has_realm' => true, 'fighters' => 0, 'free_traits' => 0);
$cands  = nudge_candidates($narrow['speakeasy51'], $facts3);
$keys   = array();
foreach ($cands as $c) $keys[] = $c['key'];
ok(!in_array('raid', $keys, true), 'a player with 106 raids is offered raiding');
ok(!in_array('realm', $keys, true), 'a player who HAS a realm is told to build one');
ok(!in_array('skullswap', $keys, true), 'a game they have played is still offered');
ok(in_array('guardians', $keys, true), 'a realm holder who has never played Guardians is not offered it');

/* And a Crew needs three Fighters. */
$two = nudge_candidates(array('mission' => 10), array('has_realm' => true, 'fighters' => 2, 'free_traits' => 0));
$k2 = array(); foreach ($two as $c) $k2[] = $c['key'];
ok(!in_array('arena', $k2, true), 'a player with 2 Fighters is sent to the Arena, which needs a Crew of 3');
$three = nudge_candidates(array('mission' => 10), array('has_realm' => true, 'fighters' => 3, 'free_traits' => 0));
$k3 = array(); foreach ($three as $c) $k3[] = $c['key'];
ok(in_array('arena', $k3, true), 'a player with 3 Fighters is not offered the Arena');

echo "\nthe same card is not shown forever\n";
/*
 * A nudge that repeats is nagging. Anything suggested inside
 * NUDGE_REPEAT_DAYS is skipped, and when everything has been offered
 * recently the answer is silence rather than the top of the list again.
 */
$cands = nudge_candidates($narrow['cobreean.pxlz'], $facts2);
$first = nudge_pick($cands, array());
$hist  = array($first['key'] => date('Y-m-d H:i:s', strtotime('-2 days')));
$second = nudge_pick($cands, $hist);
ok($second && $second['key'] !== $first['key'],
   'the same suggestion came back two days later: ' . $first['key']);
/* But an old one is fair game again. */
$old = array($first['key'] => date('Y-m-d H:i:s', strtotime('-' . (NUDGE_REPEAT_DAYS + 5) . ' days')));
ok(nudge_pick($cands, $old)['key'] === $first['key'],
   'a suggestion from more than ' . NUDGE_REPEAT_DAYS . ' days ago never comes back');
/* Everything recently offered -> nothing. */
$all = array();
foreach ($cands as $c) $all[$c['key']] = date('Y-m-d H:i:s');
ok(nudge_pick($cands, $all) === null, 'with everything offered this week it repeats instead of stopping');

echo "\nthe headline names what they DO\n";
/* "You have been deep in Missions" reads as being noticed. "You have not
   played 12 games" reads as a telling off, and one of those gets clicked. */
$h = nudge_headline($narrow['cobreean.pxlz']);
ok(strpos($h, 'Missions') !== false, 'the headline does not name their main activity: ' . $h);
ok(stripos($h, 'not ') === false && strpos($h, 'never') === false,
   'the headline tells them off: ' . $h);
ok(nudge_headline(array()) !== '', 'a player with no footprint gets an empty headline');
$h2 = nudge_headline($narrow['scorba']);
ok(substr_count($h2, ' and ') === 1, 'two activities are not joined readably: ' . $h2);

echo "\nnothing here can break a page\n";
$src = harness_strip_comments(file_get_contents(__DIR__ . '/lib/nudge-lib.php'));
ok(substr_count($src, '@$conn->query') >= 4,
   'a query in the nudge library is unsuppressed, so a missing table prints into the page');
ok(strpos($src, "SHOW TABLES LIKE 'nudges'") !== false,
   'the table check is gone, so this fatals everywhere until the SQL is run');
$lp = harness_strip_comments(file_get_contents(__DIR__ . '/launchpad.php'));
ok(strpos($lp, 'empty($lp_todo)') !== false,
   'the nudge no longer waits for Start Here, so a new account gets both boxes');
ok(strpos($lp, 'nudge_quiet($conn') !== false,
   'the once-a-day gate is gone, so fifteen queries run on every launchpad load');
/* The gate must come BEFORE the expensive part. */
$q = strpos($lp, 'nudge_quiet($conn');
$f = strpos($lp, 'nudge_footprint($conn');
ok($q !== false && $f !== false && $q < $f, 'the footprint is computed before the gate that avoids it');

echo "\nthe endpoint cannot be used on somebody else\n";
$ep = harness_strip_comments(file_get_contents(__DIR__ . '/ajax/nudge.php'));
ok(strpos($ep, "\$_POST['user") === false && strpos($ep, "\$_GET['user") === false,
   'the endpoint takes a user id, so anyone can snooze anyone');
ok(strpos($ep, "REQUEST_METHOD'] !== 'POST'") !== false,
   'the endpoint answers GET, so an <img src> can snooze somebody for a week');
ok(strpos($ep, "in_array(\$action, array('clicked', 'snoozed'), true)") !== false,
   'the action is no longer whitelisted');

echo "\n" . ($fail ? "$fail FAILED\n" : "all passed\n");
exit($fail ? 1 : 0);
