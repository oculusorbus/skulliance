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
require_once __DIR__ . '/harness-strip.php';

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

echo "\nthe reply is JSON and nothing but JSON\n";
/*
 * THE BUG THAT COST AN AFTERNOON. php.ini here has date.timezone empty, so
 * PHP emits "PHP Startup: Invalid date.timezone value ''" when a worker
 * starts. A startup warning is written BEFORE the script is parsed, so the
 * ini_set('display_errors','0') at the top of the endpoint cannot stop it --
 * it landed in front of the JSON and Discord could not read the reply. The
 * member saw "didn't respond in time"; every measurement said the endpoint
 * was fast and healthy, because it was.
 *
 * Intermittent, too: a startup warning fires per worker, not per request, so
 * the first click failed and the retry worked.
 */
ok(strpos($int, 'ob_start()') !== false,
   'the endpoint does not take over the output buffer, so anything PHP printed before it '
 . 'ran rides in front of the JSON and Discord cannot parse the reply');
$respond = substr($int, strpos($int, 'function sk_respond'));
$respond = substr($respond, 0, strpos($respond, "\n}") + 2);
ok(strpos($respond, 'ob_get_clean') !== false,
   'sk_respond() emits JSON without first clearing the buffer, so a stray notice from any '
 . 'include is sent with it');
/* ANCHORED ON THE WRITE, NOT ON HOW THE BODY HAPPENS TO BE BUILT. This read
   `echo json_encode` until the body was encoded into a variable first so its
   length could be declared -- at which point strpos() returned false, false
   compared as 0, and a correct file failed. The property is "cleared before
   written", so find the write. */
$sk_write = strpos($respond, 'echo $out');
ok($sk_write !== false, 'sk_respond() no longer writes the body it built');
ok($sk_write !== false && strpos($respond, 'ob_get_clean') < $sk_write,
   'sk_respond() clears the buffer AFTER writing the JSON, which throws away the reply '
 . 'rather than the warning');
ok(strpos($respond, 'error_log') !== false,
   'stray output is discarded silently, so the thing that broke the gate for an afternoon '
 . 'would leave no trace the next time');
$reject = substr($int, strpos($int, 'function sk_reject'));
$reject = substr($reject, 0, strpos($reject, "\n}") + 2);
ok(strpos($reject, 'ob_end_clean') !== false,
   'sk_reject() does not clear the buffer -- Discord checks for a CLEAN 401 when the '
 . 'endpoint URL is saved, so a warning in front of it fails validation too');
/* And the source fix, so nobody removes it thinking it is unused. */
/* The ASSIGNMENT, not the word: the comment above it explains the bug at
   length and mentions date.timezone five times, so a bare strpos passes
   even with the setting deleted. Lines starting ';' are comments in ini. */
$ini = @parse_ini_file(__DIR__ . '/.user.ini');
ok(is_array($ini) && !empty($ini['date.timezone']),
   '.user.ini does not SET date.timezone, which is where the stray startup warning that '
 . 'broke the gate comes from');

/* ---------------------------------------------------------------------- *
 * EVERY REPLY DECLARES ITS LENGTH.
 *
 * Measured 2026-10-31: seven presses, all seven delivered to the endpoint
 * and answered in 3ms with sub-quarter-second lag, and the member still saw
 * "the application didn't respond in time" on most of them. The request
 * arrived and the reply was written; the three seconds went somewhere
 * between writing it and Discord considering the body FINISHED.
 *
 * Without Content-Length the body goes out chunked and is only complete at
 * the terminating chunk -- a client on a keep-alive connection has nothing
 * else to tell it the response ended. The kick branch always declared its
 * length, for the flush, and is the one reply on this endpoint nobody ever
 * reported failing.
 * ---------------------------------------------------------------------- */
$ep_src  = harness_strip_comments((string)file_get_contents(__DIR__ . '/discord-interactions.php'));
function sk_fn_body($src, $sig) {
	$a = strpos($src, $sig);
	if ($a === false) return '';
	$b = strpos($src, "\n}\n", $a);
	return $b === false ? '' : substr($src, $a, $b - $a);
}
foreach (array('function sk_respond(' => 'the reply', 'function sk_reject(' => 'the 401') as $sig => $what) {
	$body = sk_fn_body($ep_src, $sig);
	ok($body !== '', "$sig is gone from discord-interactions.php");
	ok(strpos($body, 'Content-Length') !== false,
	   "$what does not declare its length, so it is only complete at the terminating chunk and "
	 . 'a client on a keep-alive connection waits for one');
	/*
	 * BYTES, NOT CHARACTERS. The payloads carry em dashes and a skull
	 * emoji; a character count declares the body SHORTER than it is and
	 * Discord reads a truncated, unparseable JSON document -- which is a
	 * worse failure than the one this fixes, because it would be constant
	 * rather than intermittent.
	 */
	ok(strpos($body, 'mb_strlen(') === false,
	   "$what measures its length in characters, not bytes");
	ok(strpos($body, 'strlen(') !== false, "$what does not measure its length at all");
}
/* And that distinction is not hypothetical for this payload. */
$emdash = json_encode(array('content' => "You're already verified \u{2014} nothing to do here."),
                      JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
ok(strlen($emdash) !== mb_strlen($emdash),
   'the check above is vacuous: this payload has no multi-byte characters in it');

/* An encode failure used to echo `false` -- a 200 with an empty body, which
   Discord reports to the member as a timeout. */
$body = sk_fn_body($ep_src, 'function sk_respond(');
ok(strpos($body, 'is_string($out)') !== false,
   'a json_encode failure sends an empty 200, which Discord reports as a timeout');

/*
 * THE SHUTDOWN TAIL IS MEASURED, because it is the one segment the
 * endpoint's own numbers cannot see: LiteSpeed holds the response until the
 * script terminates, and `work` is sampled at the TOP of the shutdown
 * handler -- before the log write, its lock, and the rotation check.
 * Reporting 3ms while an unknown amount of IO sits between the reply and
 * the wire is how a problem hides for a month.
 */
$shut = substr($ep_src, strpos($ep_src, 'register_shutdown_function'));
$shut = substr($shut, 0, strpos($shut, "\n});") + 4);
$at_write = strpos($shut, 'sk_ilog(');
$at_tail  = strpos($shut, '$tail');
ok($at_tail !== false, 'the shutdown handler does not measure its own tail, so the time between '
 . 'the reply being written and the script ending is invisible');
/*
 * THE ASSIGNMENT HAS TO BE A MEASUREMENT, not merely present. Anchored on
 * the name alone this passed with the body replaced by `$tail = 0;` -- a
 * check that survives its own subject being gutted, which is the failure
 * mode this file has now hit five times. It must read a clock.
 */
$tail_line = substr($shut, $at_tail, strpos($shut, ';', $at_tail) - $at_tail);
ok(strpos($tail_line, 'microtime(') !== false && strpos($tail_line, '$ms') !== false,
   'the shutdown tail is assigned without reading a clock, so it reports zero however long '
 . 'the write took: ' . trim($tail_line));
ok($at_write !== false && $at_tail !== false && $at_tail > $at_write,
   'the tail is measured BEFORE the log write it is supposed to be measuring');

/* ---------------------------------------------------------------------- *
 * THE GATE MESSAGE, AGAINST DISCORD'S OWN LIMITS.
 *
 * Every limit below REJECTS THE WHOLE MESSAGE rather than truncating it, so
 * overrunning one does not produce a slightly wrong rules post -- it
 * produces no rules post, a 400 in the log, and a channel nobody can get in
 * through. The message is edited from an admin page now, by someone on a
 * phone at one in the morning, so "be careful" is not the control.
 * ---------------------------------------------------------------------------- */
$pay = sk_rules_payload();
$btn = $pay['components'][0]['components'][0];

ok(mb_strlen($btn['label']) <= 80,
   'the button label is ' . mb_strlen($btn['label']) . ' characters; Discord caps it at 80 and '
 . 'refuses the entire message past it');
ok(mb_strlen($pay['content']) <= 2000,
   'the rules content is ' . mb_strlen($pay['content']) . ' characters; Discord caps a message '
 . 'at 2000');
ok(mb_strlen($pay['embeds'][0]['description']) <= 4096,
   'the embed description is ' . mb_strlen($pay['embeds'][0]['description']) . '; Discord caps '
 . 'it at 4096');
ok(strlen($btn['custom_id']) <= 100, 'the button custom_id is over Discord\'s 100 bytes');

/*
 * THE TWO THINGS A STUCK MEMBER NEEDS, and they are on the message because
 * measurement said so: every press that reaches the endpoint is answered in
 * 3ms, and the presses reported as failures never arrive at all. The retry
 * instruction and the way to reach a human are the only help this server can
 * actually give, so losing either silently is worth failing over.
 */
ok(stripos($btn['label'], 'CLICK MULTIPLE TIMES') !== false,
   'the button no longer tells a member to press again, which is the only workaround there is '
 . 'for a press that never reaches Discord');
ok(stripos($pay['embeds'][0]['description'], 'persistent error') !== false,
   'the rules message no longer says who to contact when the button will not work');

/* The mention is built from the id, so it is one tap rather than a search. */
$GLOBALS['discordid_oculusorbus'] = '772831523899965440';
ok(sk_rules_owner_mention() === '<@772831523899965440>',
   'the owner is not rendered as a clickable mention: ' . sk_rules_owner_mention());
$GLOBALS['discordid_oculusorbus'] = '';
ok(sk_rules_owner_mention() === '@Oculus Orbus',
   'with no id configured the message would read "Contact <@>" rather than a plain name');
$GLOBALS['discordid_oculusorbus'] = '<@772831523899965440>';
ok(sk_rules_owner_mention() === '<@772831523899965440>',
   'an id that already carries its own angle brackets produces a broken double mention');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all Discord rules checks passed\n");
exit($fail ? 1 : 0);
