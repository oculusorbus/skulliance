<?php
/* nft-dossier-harness.php - CLI only. No database, no network.
 *
 * The raid record on the My NFTs panel.
 *
 * WHY THIS IS WORTH A HARNESS AT ALL. Every other number in the dossier is a
 * COUNT: missions done, gauntlet runs, how many are running now. The raid
 * record is the first one with a rule in it, and the rule is easy to get
 * backwards:
 *
 *     raids.outcome is written from the ATTACKER's point of view.
 *     '1' = the raid succeeded, '2' = it was repelled, '0' = still running.
 *
 * So outcome '1' is a WIN for a soldier on offense and a LOSS for one on
 * defense. Counting outcome='1' as a win outright credits every defender for
 * being overrun, and the panel would tell a player their Skull had won every
 * raid it lost. That is not a crash, it is a confident wrong number, which is
 * the kind this codebase has shipped before.
 *
 * So the REAL aggregation is lifted out of lib/nft-dossier.php and run
 * against rows this file makes up, rather than the shape being read.
 *
 * Usage: php nft-dossier-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
$src = file_get_contents(__DIR__ . '/lib/nft-dossier.php');

/*
 * A STUB THAT ANSWERS ONLY THE RAID QUERY. _nftd_rows() is the one seam, so
 * the fixture is injected by handing the lifted block its own version of it
 * -- the SQL is still the shipped SQL, which is what the assertions below
 * about the join are checking.
 */
$rows = array();
function _nftd_rows($conn, $sql) { global $rows; return $rows; }

/* The block, lifted whole: from the banner down to the end of the loop. */
$a = strpos($src, "\t\$d['raids'] = array(");
$b = strpos($src, "\n\t}\n", $a);
ok($a !== false && $b !== false, 'the raid aggregation is gone from lib/nft-dossier.php');
$block = substr($src, $a, $b - $a + 3);

function tally($fixture) {
	global $rows, $block;
	$rows = $fixture;
	$d = array(); $id = 1; $conn = null;
	eval($block);
	return $d['raids'];
}
function raid($side, $outcome, $dead = 0) {
	return array('side' => $side, 'outcome' => $outcome, 'dead' => $dead);
}

echo "a win depends on which side it fought on\n";
{
	/* The same outcome, both sides. If these two ever agree, the rule is gone. */
	$att = tally(array(raid('offense', '1')));
	ok($att['won'] === 1 && $att['lost'] === 0,
	   'an attacker in a raid that SUCCEEDED is not credited with a win');

	$def = tally(array(raid('defense', '1')));
	ok($def['lost'] === 1 && $def['won'] === 0,
	   'a defender whose realm was OVERRUN is credited with a win; outcome is written from '
	 . 'the attacker\'s point of view');

	$attLost = tally(array(raid('offense', '2')));
	ok($attLost['lost'] === 1 && $attLost['won'] === 0,
	   'an attacker whose raid was REPELLED is not credited with a loss');

	$defHeld = tally(array(raid('defense', '2')));
	ok($defHeld['won'] === 1 && $defHeld['lost'] === 0,
	   'a defender who HELD is not credited with a win');
}

echo "\nan unresolved raid is neither won nor lost\n";
{
	$live = tally(array(raid('offense', '0'), raid('defense', '0')));
	ok($live['now'] === 2, 'raids still running are not counted as in progress');
	ok($live['won'] === 0 && $live['lost'] === 0,
	   'a raid that has not finished is already being scored');
	ok($live['total'] === 2, 'an unresolved raid does not count towards raids fought');
}

echo "\nthe tallies add up\n";
{
	/*
	 * ONE FIXTURE WITH EVERY COMBINATION, because the per-case checks above
	 * each pass against a counter that only ever increments one field. This
	 * is the one that catches a tally written to the wrong key.
	 */
	$mixed = tally(array(
		raid('offense', '1'),        // won
		raid('offense', '1', 1),     // won, and fell
		raid('offense', '2'),        // lost
		raid('defense', '1'),        // lost
		raid('defense', '2'),        // won
		raid('defense', '2', 1),     // won, and fell
		raid('offense', '0'),        // running
	));
	ok($mixed['total']  === 7, 'total is ' . $mixed['total'] . ', not 7');
	ok($mixed['attack'] === 4, 'attack is ' . $mixed['attack'] . ', not 4');
	ok($mixed['defend'] === 3, 'defend is ' . $mixed['defend'] . ', not 3');
	ok($mixed['won']    === 4, 'won is ' . $mixed['won'] . ', not 4');
	ok($mixed['lost']   === 2, 'lost is ' . $mixed['lost'] . ', not 2');
	ok($mixed['fell']   === 2, 'fell is ' . $mixed['fell'] . ', not 2');
	ok($mixed['now']    === 1, 'now is ' . $mixed['now'] . ', not 1');
	/* Every resolved raid is either a win or a loss, and nothing is both. */
	ok($mixed['won'] + $mixed['lost'] + $mixed['now'] === $mixed['total'],
	   'the record does not account for every raid: ' . json_encode($mixed));
	ok($mixed['attack'] + $mixed['defend'] === $mixed['total'],
	   'the sides do not add up to the total: ' . json_encode($mixed));

	/* An NFT that has never been in a realm. */
	$none = tally(array());
	ok($none['total'] === 0 && $none['won'] === 0,
	   'an NFT with no raids does not come back as all zeroes');
}

echo "\nit reaches raids through the soldier, over every soldier row\n";
{
	$clean = harness_strip_comments($src);
	/* raids_soldiers keys on soldier_id; only soldiers knows about nft_id. */
	ok(preg_match('/INNER JOIN soldiers s ON s\.id = rs\.soldier_id/', $clean) === 1,
	   'the raid query no longer joins soldiers, so it cannot reach an NFT at all');
	ok(preg_match('/WHERE s\.nft_id = \$id/', $clean) === 1,
	   'the raid query does not filter to this NFT');
	/*
	 * NO LIMIT 1 AND NO s.active. The soldier the panel SHOWS is the active
	 * one (ORDER BY s.active DESC ... LIMIT 1), but a soldier can be retired
	 * and replaced and the raids it fought still happened. Scoping the count
	 * the same way would quietly drop a player's whole history the day their
	 * Skull died.
	 */
	$q = substr($clean, strpos($clean, 'FROM raids_soldiers rs'));
	$q = substr($q, 0, strpos($q, '") as'));
	ok(strpos($q, 'LIMIT') === false,
	   'the raid count is limited, so an NFT with a long history reports a short one');
	ok(strpos($q, 's.active') === false,
	   'the raid count is scoped to the active soldier, so a retired one loses its record');
	/* INNER JOIN on raids, not LEFT: a raids_soldiers row whose raid is gone
	   has no outcome to score and must not land in the total. */
	ok(preg_match('/INNER JOIN raids\s+r ON r\.id = rs\.raid_id/', $clean) === 1,
	   'a raids_soldiers row with no surviving raid is counted, with no outcome to score it by');
}

echo "\nthe realm and the Diamond Skull are shown, not just named\n";
{
	$clean = harness_strip_comments($src);
	$page  = file_get_contents(__DIR__ . '/my-nfts.php');
	$ep    = file_get_contents(__DIR__ . '/ajax/nft-dossier.php');

	/* The realm's picture IS its theme file, so the query has to bring the id
	   back. Without it the row renders a name and no picture, silently. */
	ok(strpos($clean, 'r.theme_id') !== false,
	   'the soldier query no longer selects the realm theme, so the realm has no picture');
	ok(strpos($page, "images/themes/'") !== false
	   || strpos($page, 'images/themes/') !== false,
	   'the realm row does not draw the theme image');
	/* A realm with no theme must show no thumbnail rather than a broken one. */
	ok(strpos($page, 'd.soldier.theme_id') !== false,
	   'the realm thumbnail is drawn unconditionally; a realm with no theme gets a broken image');

	/*
	 * diamond_skulls.diamond_skull_id IS ITSELF AN nfts.id -- gallery.php has
	 * relied on that for as long as the feature has existed. Returning the
	 * bare id put a row number on the panel that the owner has never seen
	 * anywhere else.
	 */
	ok(preg_match('/INNER JOIN nfts\s+dn ON dn\.id = ds\.diamond_skull_id/', $clean) === 1,
	   'the diamond skull is not joined to nfts, so it has neither a name nor a picture');
	ok(strpos($page, 'ds.name || ds.asset_name') !== false,
	   'the diamond skull row still renders the raw id instead of its name');
	/*
	 * WHETHER THE PICTURES ACTUALLY RENDER IS NOT A QUESTION SOURCE TEXT CAN
	 * ANSWER. A structural check for "this block mentions ds.image and an
	 * img tag" passed with the thumbnail wired to `false`, because the
	 * string still appeared further down the same ternary. So the real
	 * render() is EXECUTED, in nft-panel-harness.js, and reported here as
	 * one assertion -- the same arrangement digest-harness.php uses for its
	 * geometry.
	 */
	$out = array(); $rc = 0;
	exec('node ' . escapeshellarg(__DIR__ . '/nft-panel-harness.js') . ' 2>&1', $out, $rc);
	if ($rc !== 0) {
		echo "  --- nft-panel-harness.js ---\n";
		foreach ($out as $line) if (strpos($line, 'FAIL') !== false) echo "  $line\n";
	}
	ok($rc === 0, 'nft-panel-harness.js failed: render() does not produce what the panel needs');
	/* The image is resolved by the CALLER, the same split the main image
	   uses: getIPFS() is db.php's and the library does not include it. */
	ok(strpos($clean, 'getIPFS(') === false,
	   'the dossier library calls getIPFS(), which it does not include; the endpoint resolves images');
	ok(strpos($ep, "\$d['diamond']['image'] = getIPFS(") !== false,
	   'the endpoint does not resolve the diamond skull image, so the panel gets a raw CID');
	/* And the raw CID must not go out with it. */
	ok(strpos($ep, "unset(\$d['diamond']['ipfs'])") !== false,
	   'the diamond skull\'s raw ipfs value is still sent to the browser');
}

echo "\nthe raid line says which stat it is about\n";
{
	$page = file_get_contents(__DIR__ . '/my-nfts.php');
	/*
	 * It shipped as a bare .nftd-idle under a FIVE-TILE grid, so "8 won, 5
	 * lost" could have been qualifying missions, gauntlets or the nightly
	 * rate. A stat line with no label is a different number depending on who
	 * reads it.
	 */
	ok(strpos($page, '<p class="nftd-note"><b>Raids</b>') !== false,
	   'the raid record has no label, so it reads as a comment on whichever tile is nearest');
	/* And the label needs a rule, or it is just more body text. */
	ok(preg_match('/\.nftd-note b\s*\{/', $page) === 1,
	   'the record label has no styling, so it does not read as a label');
	ok(preg_match('/\.nftd-note \+ \.nftd-idle\s*\{/', $page) === 1,
	   'nothing separates the record from the artwork note; they render as one block');
}

echo "\nthe panel only shows a record once there is one\n";
{
	$page = file_get_contents(__DIR__ . '/my-nfts.php');
	ok(strpos($page, "'raids'    => \$d['raids'],") !== false
	   || strpos(file_get_contents(__DIR__ . '/ajax/nft-dossier.php'), "'raids'") !== false,
	   'the endpoint does not send the raid record to the panel');
	/* A "0 raids" tile on an NFT that has never been in a realm is a column
	   of zeroes rather than information. */
	ok(strpos($page, '(r.total') !== false,
	   'the raid tile renders unconditionally, so every idle NFT shows a zero');
	/* Older payloads, and a panel opened against a cached endpoint response,
	   must not throw on a missing key. */
	ok(strpos($page, 'var r = d.raids || {};') !== false,
	   'a payload without a raids key takes the whole panel down');
}

echo "\nthe traits reach the panel, in order, without a second opinion\n";
{
	/*
	 * THE REAL PIPELINE, EXECUTED. nft_metadata_traits() already has its own
	 * harness for the four document shapes; what is new here is the hand-off
	 * -- and the hand-off is where the order can be lost, which is a bug
	 * nothing on the screen would announce.
	 */
	require_once __DIR__ . '/lib/nft-metadata-lib.php';

	/* nftd_trait_pairs() is lifted rather than required: this file declares
	   its own _nftd_rows() as the fixture seam, so including the library
	   whole would be a redeclaration. */
	$fa = strpos($src, 'function nftd_trait_pairs(');
	$fb = strpos($src, "\n}\n", $fa);
	ok($fa !== false && $fb !== false, 'nftd_trait_pairs() is gone from lib/nft-dossier.php');
	eval(substr($src, $fa, $fb - $fa + 3));

	/* A CIP-25 document, the shape most of this platform's own art is in. */
	$cip25 = array('name' => 'ANCESTOR #41', 'image' => 'ipfs://bafyreallylong',
	               'Background' => 'Crypt', 'Head' => 'Bone Blaster', 'Eyes' => 'Neon');
	$pairs = nftd_trait_pairs(nft_metadata_traits($cip25));
	$names = array();
	foreach ($pairs as $t) $names[] = $t['name'];
	ok($names === array('Background', 'Head', 'Eyes'),
	   'the trait order did not survive: ' . implode(',', $names));
	ok($pairs[1]['value'] === 'Bone Blaster', 'the value did not come through');
	/* The name and the picture are not traits, and the URI test is by VALUE
	   -- a leaked ipfs:// under any key would read as "Image: ipfs://baf..." */
	ok(!in_array('image', $names, true) && !in_array('name', $names, true),
	   'the picture or the asset name is being listed as a trait');

	/* attributes[{trait_type,value}] -- Metaplex and every EVM collection. */
	$mp = array('attributes' => array(
		array('trait_type' => 'Suit',  'value' => 'Hazard'),
		array('trait_type' => 'Level', 'value' => 7),
		array('trait_type' => 'Animated', 'value' => true),
	));
	$pairs = nftd_trait_pairs(nft_metadata_traits($mp));
	ok(count($pairs) === 3, 'a Metaplex attributes list did not come through as three traits');
	ok($pairs[1]['value'] === '7', 'a numeric trait value is not a string by the time it ships');
	/*
	 * A BOOL IS SPELLED OUT. json_encode(true) reaches the browser as `true`
	 * and String()s to "true"; (string)true in PHP is "1". A trait called
	 * Animated must not print either of those.
	 */
	ok($pairs[2]['value'] === 'Yes', 'a boolean trait prints as ' . $pairs[2]['value']);

	/*
	 * TRAIT NAMES THAT ARE DIGITS -- the whole reason this is a LIST and not
	 * a map. PHP turns the keys '0','1','2' into integers, json_encode()
	 * emits a JSON array, and the browser reads indices where the names
	 * were. As pairs it cannot happen.
	 */
	$pairs = nftd_trait_pairs(array('0' => 'Alpha', '1' => 'Beta'));
	ok(isset($pairs[0]['name']) && $pairs[0]['name'] === '0' && $pairs[0]['value'] === 'Alpha',
	   'a trait named "0" lost its name on the way out');
	$json = json_encode($pairs);
	ok(strpos($json, '"name":"0"') !== false,
	   'the encoded payload does not carry the trait names: ' . $json);

	/* An empty value is not a trait, and nothing non-scalar is one. */
	$pairs = nftd_trait_pairs(array('Blank' => '', 'Deep' => array('a'), 'Real' => 'x'));
	ok(count($pairs) === 1 && $pairs[0]['name'] === 'Real',
	   'an empty or nested trait value is being printed as a trait');
}

echo "\nthe metadata column is read separately, and may not be there at all\n";
{
	$clean = harness_strip_comments($src);
	$ep    = file_get_contents(__DIR__ . '/ajax/nft-dossier.php');

	/*
	 * NOT IN THE BASE SELECT, AND THIS IS THE ONE THAT MATTERS.
	 *
	 * nfts.metadata and nfts.metadata_at are added by hand against the live
	 * database (nft-metadata-schema.md). Named in the base query, a
	 * deployment that has not run that ALTER gets NO PANEL -- the query
	 * fails, nft_dossier() returns null, and the endpoint says "No such
	 * NFT" about an NFT that is right there. Read on its own, the traits
	 * section is empty and everything else works.
	 */
	$ba = strpos($clean, '$base = _nftd_rows($conn,');
	$bb = strpos($clean, '");', $ba);
	$basesql = substr($clean, $ba, $bb - $ba);
	ok(strpos($basesql, 'metadata') === false,
	   'nfts.metadata is in the BASE select, so a database without that column loses the whole panel');
	ok(preg_match('/SELECT metadata, metadata_at\s+FROM nfts WHERE id = \$id/', $clean) === 1,
	   'the metadata read is gone, or is no longer a read of its own');

	/* THE SHAPE QUESTION IS NOT ANSWERED TWICE. Four chains write four
	   document shapes and nft_metadata_traits() is the one place that knows
	   them; a second reader here would drift. */
	ok(strpos($clean, 'nft_metadata_traits(') !== false,
	   'the dossier no longer uses nft_metadata_traits()');
	foreach (array('attributes', 'trait_type') as $shape) {
		ok(strpos($clean, "'" . $shape . "'") === false,
		   "lib/nft-dossier.php is reading document shapes itself ('$shape'); that lives in "
		 . 'nft_metadata_traits()');
	}
	ok(strpos($clean, "require_once __DIR__ . '/nft-metadata-lib.php'") !== false,
	   'the dossier calls nft_metadata_traits() without including the library that defines it');

	/* metadata_at IS WHAT SEPARATES "no traits" FROM "never looked". */
	ok(strpos($ep, "'metadataAt' => \$d['metadata_at']") !== false,
	   'metadata_at is not sent, so the panel cannot tell an asset with no traits from one '
	 . 'nobody has crawled');
	ok(strpos($ep, "'traits'     => \$d['traits']") !== false,
	   'the endpoint does not send the traits');
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
