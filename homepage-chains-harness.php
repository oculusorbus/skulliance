<?php
/* homepage-chains-harness.php — CLI only. No database, no network.
 *
 * ONE RULE, AND IT IS THE REASON THE CHAIN BAND EXISTS: nothing in the
 * homepage's body copy may NAME the chains. The band names them, counted
 * from the database, and everything else talks about "the chains it runs
 * on" without listing them.
 *
 * This is written down because it has already failed twice. The page had
 * four hand-written chain lists; when Solana shipped only three were
 * updated, and one still read "built on Cardano with the XRP Ledger
 * alongside it" months later. Then the band's own kicker shipped reading
 * "Four chains" -- a hardcoded count inside the section built to stop
 * hardcoded counts.
 *
 * Nothing here can tell you the copy is GOOD. It can tell you the next
 * chain will not require an editing pass to avoid lying.
 *
 * Usage: php homepage-chains-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$raw = file_get_contents(__DIR__ . '/homepage.php');

/*
 * CARDANO IS EXEMPT AND THE OTHERS ARE NOT, which is not an oversight.
 * "Skulliance started on Cardano", "skull art on Cardano" and the artist
 * named Cardano Camera are history and proper nouns -- they do not go
 * stale when a chain is added. An enumeration of the CURRENT set does.
 *
 * The <head> is exempt too: homepage.php:43 says the meta, titles and
 * schema are deliberately Cardano-forward for search, which is a
 * positioning decision rather than a maintenance hazard.
 */
$body = preg_replace('!/\*.*?\*/!s', '', $raw);
$body = preg_replace('!<head>.*?</head>!s', '', $body);
$body = preg_replace('!<script type="application/ld\+json">.*?</script>!s', '', $body);

echo "no chain is named in the body copy\n";
foreach (array('Solana', 'Polygon', 'XRP Ledger', 'XRPL') as $chain) {
	$n = substr_count($body, $chain);
	ok($n === 0, "homepage body copy names \"$chain\" $n time(s) -- that list goes stale the "
	           . "next time a chain is added, which is exactly what the band exists to prevent");
}

echo "\nthe counts come from the data, not from typing\n";

/* "four blockchains" and "Four chains" are the two that were hand-typed. */
ok(preg_match('/\b(one|two|three|four|five|six|seven|eight|nine|ten)\s+(blockchains?|chains)\b/i', $body) === 0,
   'a spelled-out chain count is hardcoded in the copy again');
ok(preg_match('/\b\d+\s+(blockchains?|chains)\b/i', $body) === 0,
   'a numeric chain count is hardcoded in the copy again');
ok(strpos($raw, '$hp_chain_word') !== false, '$hp_chain_word is gone, so nothing counts the chains');
ok(substr_count($raw, 'echo htmlspecialchars($hp_chain_word)') >= 1
   && strpos($raw, 'ucfirst($hp_chain_word)') !== false,
   'the lede and the band kicker no longer both read the counted word');

echo "\nthe word it picks\n";

/* The real mapping, lifted rather than restated. */
preg_match('/\$hp_chain_word = \'multiple\';.*?\n  \}/s', $raw, $m);
ok(!empty($m[0]), 'the chain-word block is gone from homepage.php');
function word_for($n, $code) {
	$hp_chain_n = $n; $hp_chain_word = null;
	eval($code);
	return $hp_chain_word;
}
ok(word_for(4, $m[0]) === 'four',  'four chains does not read "four"');
ok(word_for(1, $m[0]) === 'one',   'one chain does not read "one"');
ok(word_for(5, $m[0]) === 'five',  'a fifth chain would not be counted');
ok(word_for(11, $m[0]) === '11',   'past ten it does not fall back to the digit');
/*
 * A ZERO IS THE DATABASE BEING DOWN, NOT A FACT ABOUT THE PLATFORM. The
 * page already refuses to print "0 NFTs staked" for the same reason; a
 * lede reading "0 blockchains" argues against the whole site.
 */
ok(word_for(0, $m[0]) === 'multiple',
   'with the database unreachable the lede would read "0 blockchains"');

echo "\nand the band renders nothing rather than something empty\n";
ok(strpos($raw, 'if ($hp_chains):') !== false,
   'the band is no longer guarded on having chains to show');
/*
 * THE MAP MOVED to lib/chain-icons.php, shared with the Collections
 * table, because homepage.php cannot include db.php and a second copy of
 * a four-entry map is how this codebase ended up with four different
 * IPFS gateway lists. So the check follows it: the band must USE the
 * shared helper, and the helper must still know about xrpl.
 */
require_once __DIR__ . '/lib/chain-icons.php';
ok(strpos($raw, "require_once __DIR__ . '/lib/chain-icons.php'") !== false,
   'the band no longer requires the shared chain-icon map');
ok(strpos($raw, 'chain_icon_file($hp_c[\'slug\'])') !== false,
   'the band builds its icon path itself again instead of using chain_icon_file()');
ok(chain_icon_file('xrpl') === 'xrp',
   "chain_icon_file() lost the xrpl->xrp mapping; blockchains.slug is 'xrpl' and the file is "
 . 'icons/xrp.png, so XRP Ledger renders as a broken image on the homepage and in Collections');

/* hp_chains() must keep the last good answer rather than caching an
   empty band over it for five minutes. */
$data = file_get_contents(__DIR__ . '/homepage-data.php');
ok(strpos($data, 'function hp_chains(') !== false, 'hp_chains() is gone from homepage-data.php');
ok(preg_match('/return \$out \? json_encode\(\$out\) : null;/', $data) === 1,
   'hp_chains() returns an encoded empty list instead of null, so hp_cached() would cache an '
 . 'empty band over the last good answer');
ok(strpos($data, 'INNER JOIN collections') !== false,
   'hp_chains() no longer INNER JOINs collections, so a configured-but-empty chain gets advertised');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "homepage chains: ok\n";
