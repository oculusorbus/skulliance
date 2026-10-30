<?php
/**
 * discord-rules-harness.php — the Discord "Agree to the rules" gate.
 *
 *   php discord-rules-harness.php
 *
 * WHY THIS EXISTS AT ALL. A wrong answer KICKS THE MEMBER OUT OF THE SERVER
 * (SK_KICK_ON_WRONG), so every defect in the question data is a defect that
 * ejects real people, silently, and the only evidence is somebody not being
 * there. That is not a thing to leave to review.
 *
 * It is also already proven to fail that way. The original two questions
 * asked about "our blockchain" with Ethereum, Solana and Polygon as the wrong
 * answers -- the three other chains the platform now runs on -- so a Polygon
 * holder answering Sandeep Nailwal and MATIC was ejected for being right
 * about the chain they actually hold. Found when it happened to one.
 *
 * Pure data and pure functions, so no Discord call is made and nothing here
 * needs a token.
 */
error_reporting(E_ALL & ~E_DEPRECATED & ~E_WARNING);
if (!isset($bot_token)) $bot_token = '';
require_once __DIR__ . '/discord-rules-lib.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* ---------------------------------------------------------------------------
 * WHO OWNS WHICH NAME AND TICKER.
 *
 * The map a distractor is checked against. A key may belong to MORE THAN ONE
 * chain, and that is the entire point of keeping it: Charles Hoskinson
 * co-founded Ethereum as well as Cardano, so he is wrong as a distractor on
 * either, and nothing but a list like this notices.
 * ------------------------------------------------------------------------- */
$OWNED_BY = [
	'hoskinson' => ['cardano', 'ethereum'],   // co-founded BOTH
	'wood'      => ['cardano', 'ethereum'],   // Jeremy Wood / Gavin Wood both read this way
	'buterin'   => ['ethereum'],
	'yakovenko' => ['solana'],
	'gokal'     => ['solana'],
	'nailwal'   => ['polygon'],
	'kanani'    => ['polygon'],
	'arjun'     => ['polygon'],
	'bjelic'    => ['polygon'],
	'schwartz'  => ['xrpl'],
	'mccaleb'   => ['xrpl'],
	'britto'    => ['xrpl'],
	'larsen'    => ['xrpl'],
	'ada'       => ['cardano'],
	'xrp'       => ['xrpl'],
	'sol'       => ['solana'],
	'pol'       => ['polygon'],
	'matic'     => ['polygon'],
	'eth'       => ['ethereum'],
];

echo "the chain picker\n";
ok(count($SK_CHAINS) >= 2, 'fewer than two chains, so the pick is not a choice');
/* Discord allows five buttons per action row and sk_chain_payload() emits one
   row. A sixth chain renders nothing and the gate dead-ends. */
ok(count($SK_CHAINS) <= 5,
   'more than five chains, which overflows a Discord action row: sk_chain_payload() must '
 . 'split into a second row before another is added');
$pick = sk_chain_payload();
ok(count($pick['components'][0]['components']) === count($SK_CHAINS),
   'the picker does not offer every chain');
ok(($pick['flags'] & SK_EPHEMERAL) === SK_EPHEMERAL, 'the picker is not ephemeral');
/* No grading happens on the pick, so no pick can kick. Asserted on the
   endpoint, since that is where the kick lives. */
$int = file_get_contents(__DIR__ . '/discord-interactions.php');
$cBranch = substr($int, strpos($int, "strpos(\$custom_id, 'sk_c:')"));
$cBranch = substr($cBranch, 0, strpos($cBranch, "\n// ---"));
ok(strpos($cBranch, 'DELETE') === false && strpos($cBranch, 'KICK') === false,
   'the chain-pick branch can kick, but picking a chain has no wrong answer');

echo "\nevery question is answerable, and none is a giveaway\n";
foreach ($SK_CHAINS as $chain => $c) {
	ok(isset($c['label']) && $c['label'] !== '', "$chain has no label for its button");
	ok(count($c['questions']) >= 1, "$chain has no questions");
	foreach ($c['questions'] as $i => $q) {
		$id   = "$chain q" . ($i + 1);
		$opts = array_keys($q['options']);

		/* CHECKED AND SKIPPED BEFORE ANYTHING USES IT. array_intersect() with a
		   string raises a TypeError in PHP 8, which kills the run -- so a
		   'correct' reverted to a bare string produced a fatal instead of a
		   failure, and a fatal reports zero failing checks. */
		if (!is_array($q['correct']) || !$q['correct']) {
			ok(false, "$id has no correct list -- it must be an ARRAY of keys, not a "
			        . gettype($q['correct']));
			continue;
		}
		$good = array_intersect($opts, $q['correct']);

		/* THE ONE THAT MATTERS. A question whose correct answer is not among
		   its own options cannot be passed, and with SK_KICK_ON_WRONG every
		   member who reaches it is ejected whatever they press. */
		ok(count($good) >= 1,
		   "$id has no correct answer among its options, so every member who reaches it is "
		 . 'kicked whatever they press');
		ok(count($good) < count($opts), "$id marks every option correct, so it tests nothing");
		ok(count($opts) >= 3, "$id offers fewer than three options");
		ok(count($opts) <= 5, "$id offers more than five options, which overflows an action row");
		ok(trim($q['prompt']) !== '', "$id has an empty prompt");

		foreach ($opts as $key) {
			/* The custom_id is colon-delimited and parsed with explode(), so a
			   key carrying a colon silently reassigns the chain and index. */
			ok(strpos($key, ':') === false, "$id option key '$key' contains a colon, which breaks "
			 . 'the custom_id parse in discord-interactions.php');
			ok(preg_match('/^[a-z0-9_-]+$/', $key) === 1,
			   "$id option key '$key' is not a plain stable identifier");
			/* Discord refuses a custom_id over 100 bytes. */
			$cid = 'sk_a:' . $chain . ':' . $i . ':' . $key;
			ok(strlen($cid) <= 100, "$id custom_id '$cid' is over Discord's 100-byte limit");
		}

		/*
		 * NO DISTRACTOR MAY BELONG TO THE CHAIN BEING ASKED ABOUT.
		 *
		 * This is the original bug stated as a property. Every wrong option
		 * has to belong to a DIFFERENT chain, or somebody who knows the
		 * subject better than the quiz does gets ejected for it -- which is
		 * exactly what happened to the Polygon holder, and exactly what
		 * offering Charles Hoskinson as a wrong answer to an Ethereum
		 * question would do next.
		 */
		foreach ($opts as $key) {
			if (in_array($key, $q['correct'], true)) continue;
			$owners = isset($OWNED_BY[$key]) ? $OWNED_BY[$key] : [];
			ok(!in_array($chain, $owners, true),
			   "$id offers '$key' as a WRONG answer, but it belongs to $chain -- a member who "
			 . 'knows that is kicked for being right');
			ok($owners !== [],
			   "$id option '$key' is in no chain's list in this harness, so nothing can check "
			 . 'whether it is a fair distractor. Add it to $OWNED_BY.');
		}
		/* And the correct answer really is this chain's. */
		foreach ($good as $key) {
			$owners = isset($OWNED_BY[$key]) ? $OWNED_BY[$key] : [];
			ok(in_array($chain, $owners, true),
			   "$id marks '$key' correct but it does not belong to $chain");
		}
	}
}

echo "\ngrading is by key, and only the listed keys pass\n";
foreach ($SK_CHAINS as $chain => $c) {
	foreach ($c['questions'] as $i => $q) {
		foreach ($q['correct'] as $key) {
			ok(sk_is_correct($chain, $i, $key) === true,
			   "sk_is_correct() rejects '$key', which $chain q" . ($i + 1) . ' lists as correct');
		}
		foreach (array_keys($q['options']) as $key) {
			if (in_array($key, $q['correct'], true)) continue;
			ok(sk_is_correct($chain, $i, $key) === false,
			   "sk_is_correct() accepts '$key' on $chain q" . ($i + 1) . ', which is a distractor');
		}
	}
}
ok(sk_is_correct('no-such-chain', 0, 'ada') === false, 'an unknown chain grades as correct');
ok(sk_is_correct('cardano', 99, 'ada')      === false, 'an out-of-range index grades as correct');
ok(sk_is_correct('cardano', 0, '')          === false, 'an empty answer grades as correct');

echo "\nthe payload a member actually sees\n";
foreach ($SK_CHAINS as $chain => $c) {
	$p = sk_question_payload($chain, 0);
	$btns = $p['components'][0]['components'];
	ok(count($btns) === count($c['questions'][0]['options']), "$chain q1 drops an option");
	ok(($p['flags'] & SK_EPHEMERAL) === SK_EPHEMERAL, "$chain q1 is not ephemeral");
	foreach ($btns as $b) {
		ok(strpos($b['custom_id'], 'sk_a:' . $chain . ':0:') === 0,
		   "$chain q1 button carries the wrong custom_id: " . $b['custom_id']);
		ok(trim((string)$b['label']) !== '', "$chain q1 has a button with no label");
	}
	/* Shuffled, or the answer is always in the same seat and the gate becomes
	   "press the third one". Over 40 renders a fixed order is beyond unlikely. */
	$seen = [];
	for ($n = 0; $n < 40; $n++) {
		$ids = array_map(function ($b) { return $b['custom_id']; },
		                 sk_question_payload($chain, 0)['components'][0]['components']);
		$seen[implode(',', $ids)] = 1;
	}
	ok(count($seen) > 1, "$chain q1 renders the options in the same order every time");
}

echo "\nthe endpoint cannot kick somebody for our deploy timing\n";
/*
 * Ephemeral messages persist, so a member part-way through the OLD two-question
 * quiz still has "sk_a:<index>:<key>" buttons in front of them. Three parts,
 * where the new format has four. If that fell through to the grader it would
 * read the index as a chain, fail the lookup, and -- before this was handled --
 * could reach the wrong-answer branch and kick them.
 */
$aBranch = substr($int, strpos($int, "strpos(\$custom_id, 'sk_a:')"));
ok(strpos($aBranch, 'count($parts) === 3') !== false,
   'the endpoint does not recognise a custom_id from before the chain pick existed');
$legacy = strpos($aBranch, 'count($parts) === 3');
$kick   = strpos($aBranch, 'SK_KICK_ON_WRONG');
ok($legacy !== false && $kick !== false && $legacy < $kick,
   'the legacy custom_id is handled AFTER the kick branch, so an in-flight member is ejected '
 . 'for being mid-quiz when this deployed');
ok(strpos($int, 'sk_chain_payload()') !== false,
   'the endpoint never shows the chain picker');
ok(strpos($int, 'sk_is_correct(') !== false,
   'the endpoint grades with something other than sk_is_correct(), so the correct LIST is '
 . 'probably being compared as a string and every multi-answer question rejects half its '
 . 'right answers');

echo "\ntiming never reaches the response body\n";
/*
 * The file's own header is explicit: a single stray byte printed here
 * corrupts the JSON and Discord shows the member an error. Timing added to
 * chase a 3-second budget must not become the thing that breaks the reply.
 */
ok(strpos($int, 'register_shutdown_function') !== false,
   'nothing records how long a slow interaction took, so "did not respond in time" stays '
 . 'unmeasurable');
$sd = substr($int, strpos($int, 'register_shutdown_function'));
$sd = substr($sd, 0, strpos($sd, "\n});") + 4);
ok(strpos($sd, 'error_log') !== false, 'the slow-request report does not go to the log');
/* Word-boundaried: a bare strpos for 'printf(' also matches the 'sprintf('
   inside error_log(sprintf(...)), which is correct code -- the first version
   of this check failed the very thing it is meant to allow. */
foreach (array('echo', 'print', 'printf', 'var_dump', 'print_r') as $bad) {
	ok(preg_match('/(?<![a-z_])' . preg_quote($bad, '/') . '\s*\(?/i', $sd) !== 1,
	   "the shutdown handler uses $bad, which writes into the response body and corrupts the JSON");
}
ok(strpos($sd, 'SK_SLOW_MS') !== false,
   'the handler logs every request rather than only the slow ones');
/* sk_mark() is called on paths that exit early, so it has to exist before
   the first one can reach it -- defined above the includes. */
ok(strpos($int, 'function sk_mark(') < strpos($int, "require_once __DIR__ . '/discord-rules-lib.php'"),
   'sk_mark() is defined after the includes it is meant to time');
ok(substr_count($int, 'sk_mark(') >= 5,
   'too few timing marks to tell which stage is slow');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all Discord rules checks passed\n");
exit($fail ? 1 : 0);
