<?php
/**
 * dhc-earned-from-harness.php — "Dropped from ___" for every trait source.
 *
 * Run: php dhc-earned-from-harness.php
 *
 * dhcf_earned_from() is driven for real against the live registry, so adding a
 * source without thinking about its wording fails here rather than shipping a
 * sentence like "Dropped from Invite a friend" into somebody's X post.
 */
require_once __DIR__ . '/dhcfighters-config.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

echo "\nevery source has a phrase that reads after \"Dropped from\"\n";
/*
 * The registry's own 'trigger' is no help: those are written as instructions
 * ("clear 12 of the 15 crypts"), not as a description of what somebody did.
 * The label works for every GAME and for nothing else.
 */
foreach (array_keys($GLOBALS['DHCF_GAMES']) as $key) {
	$from = dhcf_earned_from($key);
	ok(is_string($from) && $from !== '', "$key has no provenance phrase at all");
	/* A phrase that is just the key means the lookup fell through -- the
	   player would read "Dropped from dailystreak". */
	ok($from !== $key, "$key falls through to its raw key: \"Dropped from $from\"");
	/* It goes into a 280-character post that already carries a URL and a
	   two-line tail. Anything long enough to matter is a mistake. */
	ok(strlen($from) <= 40, "$key's phrase is " . strlen($from) . " characters, long enough to eat the post");
	/* No trailing punctuation: the post adds the full stop itself, and the
	   modal does not. */
	ok(substr($from, -1) !== '.', "$key's phrase ends in a full stop, so the post reads \"$from.\"");
}

echo "\nthe three sources that are not games say something else\n";
/*
 * These are the whole reason the function exists. "Dropped from Invite a
 * friend" and "Dropped from Digest cache" are not sentences.
 */
foreach (array('dailystreak', 'referral', 'digestcache') as $key) {
	$g = dhcf_game($key);
	ok(!empty($g['earned_from']),
	   "$key has no wording of its own, so the post reads \"Dropped from " . $g['label'] . '"');
	ok(dhcf_earned_from($key) !== $g['label'], "$key still uses its label in the post");
}
/* And the games must NOT be overridden -- the label is already right, and a
   second phrasing to maintain is how the two drift apart. */
foreach (array('dropship', 'oculuslounge', 'guardians', 'cryptcrawl', 'arena') as $key) {
	ok(dhcf_earned_from($key) === dhcf_game($key)['label'],
	   "$key overrides its label for no reason");
}

echo "\nthe two outside games carry an X handle\n";
/*
 * THESE ARE REAL ACCOUNTS. A wrong handle tags a stranger, in public, from a
 * player's own timeline -- so they are pinned here rather than left to drift.
 * Both came from the owner; the codebase holds Discord ids for these people
 * and no X handles at all.
 */
ok(dhcf_earned_tag('dropship') === '@ohh_meed',
   'Drop Ship drops no longer tag @ohh_meed: "' . dhcf_earned_tag('dropship') . '"');
ok(dhcf_earned_tag('oculuslounge') === '@oculuslounge',
   'Oculus Lounge drops no longer tag @oculuslounge: "' . dhcf_earned_tag('oculuslounge') . '"');
/* Every handle must look like one, or the post prints a bare word. */
foreach (array('dropship', 'oculuslounge') as $key) {
	$tag = dhcf_earned_tag($key);
	ok(strpos($tag, '@') === 0, "$key's handle does not start with @: \"$tag\"");
	ok(preg_match('/^@[A-Za-z0-9_]{1,15}$/', $tag) === 1,
	   "$key's handle is not a valid X handle: \"$tag\"");
}
/* Everything Skulliance makes itself stays untagged -- the post's tail
   already carries @skulliance, and a second tag on every post is noise. */
foreach (array('guardians', 'cryptcrawl', 'arena', 'referral', 'dailystreak', 'skullswap') as $key) {
	ok(dhcf_earned_tag($key) === '', "$key gained an X handle it should not have");
}
ok(dhcf_earned_tag('nosuchgame') === '', 'an unknown source invents an X handle');

echo "\nan unknown source cannot break the post\n";
/* Queued drops from before this existed, and anything awarded by a source
   that has since been removed from the registry. */
ok(dhcf_earned_from('nosuchgame') === 'nosuchgame', 'an unknown source does not fall back to its key');
ok(dhcf_earned_from('') === '', 'an empty source invents a phrase');

echo "\nthe payload carries it, or nothing downstream can show it\n";
/*
 * Read from source: dhcf_drop_payload() needs a database and a trait index.
 * This payload is the ONE shape a drop takes -- the modal reveals it, the
 * held queue stores it across page loads, and the X post is built from it --
 * so a field it does not carry is a field the share cannot mention.
 */
$lib = file_get_contents(__DIR__ . '/dhcfighters-lib.php');
ok(strpos($lib, "function dhcf_drop_payload(\$conn, \$user_id, \$cat, \$slug, \$info = null, \$source = '')") !== false,
   'dhcf_drop_payload() does not take a source');
ok(strpos($lib, "'from'     => \$source !== '' ? dhcf_earned_from(\$source) : '',") !== false,
   'the payload does not resolve the provenance phrase');
ok(strpos($lib, "'source'   => (string)\$source,") !== false,
   'the payload does not carry the raw source key');
ok(strpos($lib, "'tag'      => \$source !== '' ? dhcf_earned_tag(\$source) : '',") !== false,
   'the payload does not carry the X handle, so the post cannot tag the game');
/* The award path has to pass it through, or every game drop arrives blank. */
ok(strpos($lib, 'dhcf_drop_payload($conn, $user_id, $cat, $slug, $info, $source);') !== false,
   'dhcf_commit_drop() builds the payload without the source, so every drop reads blank');
/* So does the referral path, which builds a payload directly. */
$ref = file_get_contents(__DIR__ . '/lib/referral-lib.php');
ok(strpos($ref, "dhcf_drop_payload(\$conn, \$user_id, \$row['category'], \$row['slug'], null, 'referral')") !== false,
   'the invite drops do not name their source, so they post with no provenance');

echo "\nthe modal shows it as well as posting it\n";
$modal = file_get_contents(__DIR__ . '/dhc-dropmodal.php');
ok(strpos($modal, "id=\"dhcdrop-from\"") !== false, 'the reveal does not show where the trait came from');
ok(strpos($modal, '#dhcdrop-from{') !== false, 'the provenance line has no styling of its own');
/* Escaped: it is the first free-text field this innerHTML has carried. */
ok(strpos($modal, 'escHtml(d.from)') !== false, 'the provenance is injected into innerHTML unescaped');
/* Only when there is one, or the reveal reads "Dropped from ". */
ok(strpos($modal, 'var fromLine = d.from') !== false,
   'the reveal prints the provenance line even when there is no source');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
