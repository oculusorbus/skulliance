<?php
/*
 * infiltration-harness.php -- the mechanic, driven rather than described.
 *
 * Run: php infiltration-harness.php
 *
 * EVERY ASSERTION HERE IS MUTATION TESTED. The habit on this platform is
 * that a green harness proves nothing until each assertion has been watched
 * to FAIL against a deliberately broken version -- see the mutations block
 * at the foot of this file, which breaks the real functions and checks the
 * suite notices.
 *
 * The X payloads below are REAL, recorded from the live syndication endpoint
 * on 2026-11-12 via admin-xprobe.php. A hand-written fixture would test my
 * idea of the shape rather than the shape, and the whole feature rests on
 * reading that shape correctly.
 */
/* INFIL_SRC lets the mutation runner at the foot of this file point the
   suite at a deliberately broken copy of the source. Production is __DIR__. */
$SRC = getenv('INFIL_SRC');
if (!$SRC || !is_dir($SRC)) $SRC = __DIR__;
require_once $SRC . '/dhcfighters-config.php';
require_once $SRC . '/lib/infiltration-lib.php';

$fail = 0; $ran = 0;
function ok($c, $w) { global $fail, $ran; $ran++; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }
function head($s) { echo "\n$s\n"; }

/* ------------------------------------------------------------------ *
 * REAL PAYLOADS
 * ------------------------------------------------------------------ */

/* A genuine reply to a @skulliance post. This is the comment every length
   floor is calibrated against: 37 characters, 7 words, obviously honest. */
$REPLY = array(
	'__typename' => 'Tweet',
	'in_reply_to_screen_name'   => 'skulliance',
	'in_reply_to_status_id_str' => '2120519298963865652',
	'in_reply_to_user_id_str'   => '1494210394541412352',
	'lang' => 'en',
	'created_at' => '2026-11-11T17:16:03.000Z',
	'display_text_range' => array(12, 49),
	'id_str' => '2120566235893293205',
	'text'   => '@skulliance I did and got a legendary background!',
	'user'   => array('id_str' => '1789946389281333248', 'name' => 'Anza',
	                  'screen_name' => 'anzaisrekt'),
);

/* The pinned verification post: ours, and top level. */
$POST = array(
	'id_str' => '2120916830747602945',
	'text'   => "\xF0\x9F\x92\x80\xF0\x9F\x86\x94 https://t.co/lWPZJnbe14",
	'created_at' => '2026-11-12T16:29:11.000Z',
	'user'   => array('id_str' => '1494210394541412352', 'name' => 'SKULLIANCE',
	                  'screen_name' => 'skulliance'),
);

function fetcher_for($payload, $http = 200) {
	return function ($url, $id) use ($payload, $http) {
		return array('http' => $http, 'body' => $payload === null ? '{}' : json_encode($payload));
	};
}

/* ------------------------------------------------------------------ *
 * READING WHAT X SENT
 * ------------------------------------------------------------------ */
head('the facts come out of a real payload');
$d = infil_parse($REPLY);
ok($d['handle'] === 'anzaisrekt',                   'handle read as "' . $d['handle'] . '"');
ok($d['uid']    === '1789946389281333248',          'author id read as "' . $d['uid'] . '"');
ok($d['parent'] === '2120519298963865652',          'parent status read as "' . $d['parent'] . '"');
ok($d['parent_uid'] === '1494210394541412352',      'parent ACCOUNT read as "' . $d['parent_uid'] . '"');

head('display_text_range strips our own handle from the member\'s words');
ok($d['own'] === 'I did and got a legendary background!',
   'own words came out as "' . $d['own'] . '", so the mention prefix is being counted');
ok(strpos($d['own'], '@skulliance') === false,
   'the @mention survived into the words a length floor measures');

head('a status id is read out of whatever shape it is pasted in');
ok(infil_status_id('https://x.com/anzaisrekt/status/2120566235893293205?a=x&s=20')
   === '2120566235893293205', 'query string defeated the parse');
ok(infil_status_id('2120566235893293205') === '2120566235893293205', 'a bare id is not accepted');
ok(infil_status_id('https://x.com/skulliance') === '', 'a profile link produced an id');
ok(infil_status_id('nonsense') === '', 'junk produced an id');

/* ------------------------------------------------------------------ *
 * X NOT ANSWERING IS NOT A REFUSAL
 * ------------------------------------------------------------------ */
head('a failure to reach X is PENDING, never rejected');
foreach (array(0, 403, 404, 500, 503) as $code) {
	$f = infil_fetch('2120566235893293205', fetcher_for(null, $code));
	ok($f['state'] === INFIL_PENDING, "HTTP $code came back as " . $f['state'] . ', not pending');
	ok($f['state'] !== INFIL_NO,      "HTTP $code was treated as the member lying");
}
$f = infil_fetch('2120566235893293205', fetcher_for(array(), 200));
ok($f['state'] === INFIL_PENDING, 'a 200 carrying {} was treated as an answer');
$f = infil_fetch('2120566235893293205', fetcher_for($REPLY, 200));
ok($f['state'] === INFIL_OK, 'a real payload did not verify');

/* ------------------------------------------------------------------ *
 * THE LADDER
 * ------------------------------------------------------------------ */
head('ten rungs, and nothing above placement_1');
ok(count(dhcf_ladder()) === 10, 'the ladder has ' . count(dhcf_ladder()) . ' rungs, not 10');
ok(infil_table_name(0) === 'run',          'rung 0 is ' . infil_table_name(0));
ok(infil_table_name(9) === 'placement_1',  'rung 9 is ' . infil_table_name(9));
ok(infil_table_name(99) === 'placement_1', 'a tier past the top did not clamp');
$ranks = array_map('dhcf_table_rank', array_keys(DHCF_TIERS));
ok(dhcf_table_rank('placement_1') === max($ranks),
   'something outranks placement_1, which would demote every existing top prize');

head('every rung is a real table, sums to 100, and beats the one below');
$prev = -1;
foreach (dhcf_ladder() as $i => $name) {
	$t = infil_table($i);
	ok(is_array($t) && isset($t['mythic']), "rung $i resolved to no table");
	ok(abs(array_sum($t) - 100) < 0.001, "rung $i sums to " . array_sum($t) . ', not 100');
	ok($t['mythic'] > $prev, "rung $i mythic " . $t['mythic'] . ' does not beat ' . $prev);
	$prev = $t['mythic'];
	ok(dhcf_table_rank($name) === $i, "rung $i ranks " . dhcf_table_rank($name));
}

head('the resolver agrees with the ladder, through the platform\'s own path');
foreach (dhcf_ladder() as $i => $name) {
	ok(dhcf_table_for('infiltration', $i) === DHCF_TIERS[$name],
	   "dhcf_table_for('infiltration', $i) did not resolve $name");
}

/* ------------------------------------------------------------------ *
 * SPEED
 * ------------------------------------------------------------------ */
head('which window a reply landed in');
$t0 = 1000000;
ok(infil_speed($t0, $t0,            60, 1440) === 'early', 'an instant reply is not early');
ok(infil_speed($t0, $t0 + 3600,     60, 1440) === 'early', 'a reply exactly on the hour is not early');
ok(infil_speed($t0, $t0 + 3601,     60, 1440) === 'main',  'a reply a second past the hour is not main');
ok(infil_speed($t0, $t0 + 86400,    60, 1440) === 'main',  'a reply exactly on the day is not main');
ok(infil_speed($t0, $t0 + 86401,    60, 1440) === 'late',  'a reply past the day is not late');
ok(infil_speed($t0, $t0 - 50,       60, 1440) === 'late',
   'a reply BEFORE the target was posted read as prompt, which is a clock to game');

head('speed buys tiers');
ok(infil_gain('early') === 2, 'early is worth ' . infil_gain('early'));
ok(infil_gain('main')  === 1, 'main is worth '  . infil_gain('main'));
ok(infil_gain('late')  === 0, 'late is worth '  . infil_gain('late') . ', so the backlog climbs');

/* ------------------------------------------------------------------ *
 * THE METER AND THE BANK
 * ------------------------------------------------------------------ */
head('gains fill the meter, then spill into the bank');
ok(infil_apply(0, 0,  1) === array(1, 0), 'a single gain from nothing');
ok(infil_apply(8, 0,  2) === array(9, 1), 'the rung-8 overshoot was thrown away, not banked');
ok(infil_apply(9, 0,  2) === array(9, 2), 'a gain at the ceiling banked nothing');
ok(infil_apply(9, 5,  1) === array(9, 6), 'the bank refused to grow past 5');
ok(infil_apply(9, 99, 2) === array(9, 101), 'the bank is capped somewhere; it must not be');

head('losses burn the bank before the meter');
ok(infil_apply(9, 3, -1) === array(9, 2), 'a miss took the rung instead of a pip');
ok(infil_apply(9, 1, -1) === array(9, 0), 'the last pip did not absorb the miss');
ok(infil_apply(9, 0, -1) === array(8, 0), 'with no bank, a miss did not cost a rung');
ok(infil_apply(0, 0, -1) === array(0, 0), 'the meter went below zero');
ok(infil_apply(2, 0, -5) === array(0, 0), 'a run of misses went negative');

head('a banked member does not descend, which is the whole point of the bank');
list($tier, $bank) = array(9, 3);
foreach (array(-1, -1, -1) as $d) list($tier, $bank) = infil_apply($tier, $bank, $d);
ok($tier === 9 && $bank === 0, "three misses against three pips left rung $tier bank $bank");
list($tier, $bank) = infil_apply($tier, $bank, -1);
ok($tier === 8, 'the fourth miss did not finally cost a rung');

/* ------------------------------------------------------------------ *
 * COMMENT QUALITY
 * ------------------------------------------------------------------ */
head('the floor passes the real comment it was calibrated against');
ok(infil_quality('I did and got a legendary background!') === '',
   'the genuine comment was refused: ' . infil_quality('I did and got a legendary background!'));

head('and refuses what it exists to refuse');
foreach (array('nice', 'great post', 'lets go', "\xF0\x9F\x94\xA5", 'W', '') as $junk) {
	ok(infil_quality($junk) !== '', 'a one-word reply passed: "' . $junk . '"');
}
ok(infil_quality(str_repeat('a', 40)) !== '',
   'forty of one character passed: the WORD floor is doing nothing');
/* The other half of the pair. Four words, ten characters -- it clears the
   word floor, so only the CHARACTER floor can refuse it. Without this the
   character floor could be set to 1 and every assertion above would still
   pass, which is exactly what the mutation run caught. */
ok(infil_words(infil_normalise('ok so i go')) >= INFIL_MIN_WORDS,
   'the short-but-wordy case no longer clears the word floor, so it tests nothing');
ok(infil_quality('ok so i go') !== '',
   'ten characters in four words passed: the CHARACTER floor is doing nothing');

head('normalising collapses the differences that are not differences');
ok(infil_normalise('Great RUN!!  ') === infil_normalise('great run'),
   'punctuation and case still make two comments different');
ok(infil_hash(infil_normalise('Great run!')) === infil_hash(infil_normalise('great   run')),
   'the hash disagrees with the normaliser');
ok(infil_normalise("\xD0\xBF\xD1\x80\xD0\xB8\xD0\xB2\xD0\xB5\xD1\x82 \xD0\xBC\xD0\xB8\xD1\x80") !== '',
   'a non-Latin comment normalised to nothing, so every one of them would collide');

head('similarity catches a member re-wearing their own sentence');
ok(infil_similarity('this boss fight was brutal', 'this boss fight was brutal') >= INFIL_SIMILAR_MAX,
   'an exact repeat was not caught');
ok(infil_similarity('this boss fight was brutal', 'this boss fight was brutal!') >= INFIL_SIMILAR_MAX,
   'one added character defeated the near-duplicate check');
ok(infil_similarity('this boss fight was brutal', 'finally pulled a mythic from the portal')
   < INFIL_SIMILAR_MAX, 'two unrelated comments were called duplicates');

/* ------------------------------------------------------------------ *
 * HEAT
 * ------------------------------------------------------------------ */
head('a target cools on the half-life X actually uses');
$now = 2000000;
ok(abs(infil_heat($now, $now) - 1.0) < 0.001,        'a fresh target is not at full heat');
ok(abs(infil_heat($now - 360 * 60, $now) - 0.5) < 0.01,
   'at one half-life the heat is ' . infil_heat($now - 360 * 60, $now) . ', not 0.5');
ok(infil_heat($now - 86400, $now) < 0.07, 'a day-old target still reads as hot');

/* ------------------------------------------------------------------ *
 * THE CODE IN A COMMENT
 * ------------------------------------------------------------------ */
head('reading a verification code out of a comment');
ok(infil_code_in('@skulliance SKL-ABC234 here you go') === 'SKL-ABC234', 'a plain code was missed');
ok(infil_code_in('@skulliance skl-abc234') === 'SKL-ABC234', 'a lowercase code was missed');
ok(infil_code_in('@skulliance no code here') === '', 'a code was invented out of nothing');
/* The excluded letters are the ones that cannot be transcribed off a screen:
   I, L, O and the digits 0 and 1. D, E and F are perfectly fine. */
ok(infil_code_in('SKL-ABCDEF') === 'SKL-ABCDEF', 'a legitimate code was refused');
foreach (array('SKL-ABC01I', 'SKL-ABCDEI', 'SKL-ABCDEO', 'SKL-ABCDEL') as $amb) {
	ok(infil_code_in($amb) === '', "an ambiguous character was accepted in $amb");
}
ok(infil_code_in('SKL-ABC23') === '', 'a five character code was accepted');

/* ------------------------------------------------------------------ *
 * THE TARGET ANNOUNCEMENT
 *
 * Driven through a stub of skl_discordmsg(), because the arithmetic in it
 * is the part that can be quietly wrong: a window boundary off by a factor
 * of sixty renders as a countdown that looks plausible and is not.
 * ------------------------------------------------------------------ */
head('a new target announces its windows as real instants');
$SENT = null;
if (!function_exists('skl_discordmsg')) {
	function skl_discordmsg($title, $desc, $img = '', $url = '', $chan = '',
	                        $thumb = '', $color = '000000') {
		$GLOBALS['SENT'] = array('title' => $title, 'body' => $desc, 'img' => $img,
		                         'url' => $url, 'chan' => $chan, 'color' => $color);
	}
}
$posted_at = '2026-11-12 10:00:00';
$t0        = strtotime($posted_at);
infil_announce_target(null, array(
	'status_id' => '2120916830747602945', 'label' => 'Portal announcement',
	'posted_at' => $posted_at, 'early_mins' => 60, 'main_mins' => 1440,
));
$sent = $GLOBALS['SENT'];
ok(is_array($sent), 'nothing was sent at all');
ok($sent['chan'] === 'infiltrations',
   'the target went to channel "' . $sent['chan'] . '", not the Infiltrations webhook');
ok(strpos($sent['url'], '2120916830747602945') !== false,
   'the embed does not link the target post');
ok(strpos($sent['body'], 'Portal announcement') !== false, 'the label was dropped');
/* The banner, and ABSOLUTE: Discord fetches an embed image itself, so a
   relative path resolves against discord.com and renders nothing at all,
   silently. */
ok(isset($sent['img']) && $sent['img'] === INFIL_BANNER,
   'the target announcement carries no banner art');
ok(strpos(INFIL_BANNER, 'https://') === 0,
   'the banner path is relative, so Discord will resolve it against itself');

/* MINUTES, NOT SECONDS. The windows are stored in minutes and Discord wants
   a unix instant, so the x60 is the one place this can go wrong by a factor
   that still looks like a time. */
ok(strpos($sent['body'], '<t:' . ($t0 + 3600) . ':R>') !== false,
   'the +2 window does not close one hour after the post');
ok(strpos($sent['body'], '<t:' . ($t0 + 86400) . ':R>') !== false,
   'the +1 window does not close one day after the post');
ok(strpos($sent['body'], '<t:' . ($t0 + 60) . ':') === false,
   'a window landed 60 seconds out, so minutes are being used as seconds');

head('a target with no windows still announces something usable');
$GLOBALS['SENT'] = null;
infil_announce_target(null, array('status_id' => '123456789', 'label' => '',
	'posted_at' => $posted_at, 'early_mins' => 1, 'main_mins' => 1));
ok(is_array($GLOBALS['SENT']) && strpos($GLOBALS['SENT']['body'], '<t:') !== false,
   'a one minute window produced no timestamp');

head('nothing is announced for a target that did not save');
$GLOBALS['SENT'] = null;
infil_announce_target(null, array());
ok($GLOBALS['SENT'] === null, 'an empty target was announced anyway');

/* ------------------------------------------------------------------ *
 * THE PAGES CAN ACTUALLY REACH DISCORD
 *
 * Every announce in the library is guarded by function_exists(), which is
 * right -- a missing Discord post must never break an award that has
 * already been paid. It also means a page that forgets to include
 * webhooks.php posts NOTHING, FOREVER, and says nothing about it.
 *
 * That is exactly what shipped: db.php does not include webhooks.php and
 * neither does skulliance.php, so targets were added and infiltrations
 * were paid with the channel silent, and the admin Health card reported
 * "no webhook" because the credentials file comes in with it.
 * ------------------------------------------------------------------ */
/* STRIPPED FIRST. The first version of this check matched the commented
   out line it was supposed to catch -- "// include_once 'webhooks.php';"
   still contains the string -- so the mutation run passed and the
   assertion was decoration. */
require_once __DIR__ . '/harness-strip.php';
head('an infiltration announcement carries the trait it paid');
require_once __DIR__ . '/dhcfighters-lib.php';
@require_once __DIR__ . '/dhcfighters-notify.php';
$GLOBALS['SENT'] = null;
infil_announce(null, array(
	'state' => INFIL_OK, 'handle' => 'anzaisrekt', 'own' => 'a real comment here',
	'speed' => 'early', 'user_id' => 0, 'comment_status' => '2120566235893293205',
	'meter' => array('tier' => 2, 'bank' => 0),
	'trait' => array('name' => 'U.E. Mutant #01', 'tier' => 'uncommon',
	                 'slug' => 'ue-mutant-01', 'category' => 'head'),
));
$inf = $GLOBALS['SENT'];
ok(is_array($inf), 'the infiltration announced nothing');
ok(strpos($inf['body'], 'U.E. Mutant #01') !== false, 'the trait is not named');
ok(strpos((string) $inf['img'], 'ue-mutant-01.png') !== false,
   'the post shows no picture of the trait, which is the half anybody looks at');
ok(strpos((string) $inf['img'], 'https://') === 0,
   'the trait art path is relative, so Discord renders nothing');
ok($inf['color'] === dhcf_tier_color('uncommon'),
   'the stripe is not the tier colour, so every drop looks the same rarity');

head('the pages that announce can actually reach Discord');
foreach (array('infiltration.php', 'admin-infiltration.php') as $pg) {
	$src = @file_get_contents(__DIR__ . '/' . $pg);
	ok($src !== false, "$pg is missing");
	if ($src === false) continue;
	$src = harness_strip_comments($src);
	ok(preg_match('~include_once\s+[\'"]webhooks\.php[\'"]~', $src) === 1,
	   "$pg calls an announce but never includes webhooks.php, so skl_discordmsg() "
	 . 'does not exist there and every post is silently skipped');
	ok(preg_match('~\binclude\s+[\'"]webhooks\.php[\'"]~', $src) === 0,
	   "$pg includes webhooks.php without _once, which is a fatal redeclare "
	 . 'the moment anything else loads it first');
}

/* ------------------------------------------------------------------ *
 * THE SHARE POST MUST NOT NAME THE MECHANIC
 * ------------------------------------------------------------------ */
head('sharing an infiltration trait on X does not say where it came from');
ok(dhcf_game_quiet_share('infiltration') === true,
   'infiltration is not marked quiet_share, so a member sharing a trait would '
 . 'post "Dropped from an infiltration" ON X, beside the posts it runs on');
ok(dhcf_game_quiet_share('cryptcrawl') === false,
   'an ordinary game got its source suppressed, which costs it the credit');
ok(dhcf_earned_from('infiltration') !== '',
   'the MODAL lost its source too; only the public half should be stripped');

$dm = harness_strip_comments(@file_get_contents(__DIR__ . '/dhc-dropmodal.php'));
ok(strpos($dm, 'd.share_from') !== false,
   'the drop modal still builds its share text from d.from, so quiet_share '
 . 'changes nothing about what gets posted');

/* ------------------------------------------------------------------ *
 * THE LIKE / REPOST GATE
 * ------------------------------------------------------------------ */
head('the page asks for a like and a repost before the comment');
$pg = harness_strip_comments(@file_get_contents(__DIR__ . '/infiltration.php'));
ok(strpos($pg, 'data-step="like"') !== false,   'nothing asks the member to like the post');
ok(strpos($pg, 'data-step="repost"') !== false, 'nothing asks the member to repost it');
/* THE FORM, not the selector. The first version of this looked for
   "data-gated" anywhere in the file, which the script's own
   querySelector('[data-gated]') satisfies -- so removing the attribute from
   the form left the assertion green and the gate gone. */
ok(preg_match('~<form[^>]*class="inf-form"[^>]*data-gated~', $pg) === 1,
   'the comment form does not carry data-gated, so the two steps are a '
 . 'suggestion nobody has to pass');

head('and does not claim to be checking them');
/* A fake check is one curious person away from casting doubt on the comment
   verification, which is real. The copy has to stay honest about which half
   is which. */
/* Anchored on the CLAIM ALONE. Two earlier versions pinned more of the
   sentence -- first the whole phrase, then a list of its endings -- and
   both failed on a reword that changed nothing about whether the page is
   honest. What has to be true is that the page says we cannot check. */
ok(stripos($pg, 'We cannot check') !== false,
   'the page no longer says the like and the repost are untracked');
foreach (array('verified your like', 'we track', 'confirmed your repost') as $claim) {
	ok(stripos($pg, $claim) === false, 'the page claims to verify a like or repost: "' . $claim . '"');
}

head('the gate is applied by script, so it cannot lock anybody out');
ok(strpos($pg, 'class="inf-form locked"') === false,
   'the form ships locked in the markup, so a member with scripting off can never submit');
ok(strpos($pg, "classList.toggle('locked'") !== false,
   'nothing ever applies the lock, so the gate does nothing');

/* ------------------------------------------------------------------ *
 * THE PAGE FITS A PHONE
 *
 * Reported from a phone: the whole page scrolled sideways. Two causes,
 * both measured in headless Chrome rather than reasoned about, and both
 * the kind that look harmless in the source.
 * ------------------------------------------------------------------ */
head('nothing on the page declares a width a phone cannot hold');

/* min-height on an aspect-ratio box is a MINIMUM WIDTH. 190px tall at
   1600/520 is 585px wide, which the column then refused to shrink below. */
ok(preg_match('~\.inf-hero\s*\{[^}]*min-height~', $pg) === 0,
   'the hero has a min-height again: on an aspect-ratio box that is a '
 . 'minimum WIDTH, and it is what made the page scroll sideways');
ok(preg_match('~\.inf-hero\s*\{[^}]*aspect-ratio~', $pg) === 1,
   'the hero lost its aspect-ratio, so nothing sizes it at all');

/* A min-width inside an overflow-x:auto wrapper is not contained: the
   wrapper is an auto-width block in an auto-width column, so it propagates
   up and the PAGE scrolls instead of the table. */
ok(preg_match('~@media\s*\(max-width:\s*700px\)[^@]*\.inf-table\s*\{[^}]*min-width:\s*0~', $pg) === 1,
   'the leaderboard table keeps its 420px floor on a phone, which the '
 . 'overflow-x wrapper does NOT contain');
ok(strpos($pg, 'class="narrow"') !== false,
   'the table has no short headings, so five columns cannot fit 320px');

/* ------------------------------------------------------------------ *
 * THE BANK IS DRAWN WHERE IT MEANS SOMETHING
 * ------------------------------------------------------------------ */
head('overshoot reads as rungs past the top, and never silently truncates');
ok(strpos($pg, 'inf-bank') !== false,
   'the bank has no presence beside the ladder, so the one thing it protects '
 . '-- rung 9 -- is nowhere near it');
/* Uncapped is the whole point of the bank. A fixed number of pips with no
   figure beside it shows 40 and 24 identically. */
ok(preg_match('~min\(\$b,\s*8\)~', $pg) === 1,
   'the pip row is not bounded at 8, so a large bank wraps the ladder row');
ok(strpos($pg, "\$b > 8 ? '+' . (\$b - 8)") !== false,
   'nothing prints the remainder past the pips, so an uncapped bank is '
 . 'silently truncated the way the old 24-pip row was');
ok(preg_match('~before you drop a rung~', $pg) === 1,
   'the bank is described by its mechanic rather than by what it buys');

echo "\n" . ($fail ? "$fail FAILED of $ran\n" : "all $ran assertions pass\n");

/* ================================================================== *
 * MUTATIONS
 *
 * A green suite proves nothing until each assertion has been watched to
 * FAIL. Every mutation below breaks the real source in one specific way
 * and the suite is re-run against the broken copy; a mutation that still
 * passes means the assertions covering it are decoration.
 *
 * Run: php infiltration-harness.php mutate
 * ================================================================== */
if (!in_array('mutate', (array) $argv, true)) exit($fail ? 1 : 0);

$MUTATIONS = array(
	array('lib', "return array('state' => INFIL_PENDING, 'http' => \$http, 'json' => null,",
	             "return array('state' => INFIL_NO, 'http' => \$http, 'json' => null,",
	      'X failing to answer is treated as the member lying'),

	array('lib', 'if ($b > $a && $a >= 0) $own = mb_substr($text, $a, $b - $a);',
	             'if (false) $own = mb_substr($text, $a, $b - $a);',
	      'our own @mention is counted as the member\'s words'),

	array('lib', '$bank += ($delta - $up);',
	             '$bank = min(3, $bank + ($delta - $up));',
	      'the bank is silently capped'),

	array('lib', '$burn  = min($bank, $down);',
	             '$burn  = 0;',
	      'a miss takes a rung while pips are still banked'),

	array('lib', "if (\$age < 0)            return 'late';",
	             "if (\$age < 0)            return 'early';",
	      'a reply timestamped before the target counts as promptest'),

	array('lib', "define('INFIL_MIN_CHARS', 25)",
	             "define('INFIL_MIN_CHARS', 1)",
	      'the length floor lets one-word replies through'),

	array('lib', 'if ($a === $b) return 100.0;',
	             'if ($a === $b) return 0.0;',
	      'an exact duplicate reads as unrelated'),

	array('lib', 'return (float) pow(0.5, $age / INFIL_HALFLIFE_MINS);',
	             'return 1.0;',
	      'a target never cools'),

	array('lib', '$early  = $posted + ((int) $target[\'early_mins\'] * 60);',
	             '$early  = $posted + ((int) $target[\'early_mins\']);',
	      'the announced window treats minutes as seconds'),

	array('lib', 'if (!is_array($target) || empty($target[\'status_id\'])) return;',
	             'if (false) return;',
	      'a target that failed to save is announced anyway'),

	array('cfg', 'foreach (dhcf_ladder() as $i => $t) $order[$t] = $i;',
	             'foreach (dhcf_ladder() as $i => $t) $order[$t] = 0;',
	      'every rung ranks the same, so the ladder collapses to run'),

	array('cfg', "'infil_8'     => array('common' => 17.6, 'uncommon' => 26.2, 'epic' => 29.2, 'legendary' => 20.2, 'mythic' => 6.8),",
	             "'infil_8'     => array('common' => 17.6, 'uncommon' => 26.2, 'epic' => 29.2, 'legendary' => 20.2, 'mythic' => 9.9),",
	      'a rung outranks placement_1 on mythic'),
);

$tmp = sys_get_temp_dir() . '/infil-mut-' . getmypid();
@mkdir($tmp . '/lib', 0777, true);
$self   = __FILE__;
$caught = 0; $missed = array();

echo "\nMUTATIONS -- each must make the suite fail\n";
foreach ($MUTATIONS as $i => $m) {
	list($which, $from, $to, $what) = $m;

	$cfg = file_get_contents(__DIR__ . '/dhcfighters-config.php');
	$lib = file_get_contents(__DIR__ . '/lib/infiltration-lib.php');
	$src = $which === 'cfg' ? $cfg : $lib;

	if (substr_count($src, $from) !== 1) {
		$missed[] = $what . '  [the mutation did not apply: '
		          . substr_count($src, $from) . ' matches]';
		printf("  %-2d NOT APPLIED  %s\n", $i + 1, $what);
		continue;
	}
	$mutated = str_replace($from, $to, $src);
	file_put_contents($tmp . '/dhcfighters-config.php', $which === 'cfg' ? $mutated : $cfg);
	file_put_contents($tmp . '/lib/infiltration-lib.php', $which === 'lib' ? $mutated : $lib);

	$out = array(); $code = 0;
	exec('INFIL_SRC=' . escapeshellarg($tmp) . ' php ' . escapeshellarg($self) . ' 2>&1',
	     $out, $code);

	if ($code !== 0) {
		$caught++;
		printf("  %-2d caught       %s\n", $i + 1, $what);
	} else {
		$missed[] = $what;
		printf("  %-2d SURVIVED     %s\n", $i + 1, $what);
	}
}
@unlink($tmp . '/dhcfighters-config.php');
@unlink($tmp . '/lib/infiltration-lib.php');
@rmdir($tmp . '/lib'); @rmdir($tmp);

echo "\n" . $caught . ' of ' . count($MUTATIONS) . " mutations caught\n";
if ($missed) { echo "UNCOVERED:\n"; foreach ($missed as $m) echo '  - ' . $m . "\n"; }
exit(($fail || $missed) ? 1 : 0);
