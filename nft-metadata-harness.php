<?php
/**
 * nft-metadata-harness.php — the on-chain document, kept.
 *
 * Run: php nft-metadata-harness.php
 *
 * WHAT GOES WRONG QUIETLY HERE. Every failure in this feature is silent by
 * nature: a crawl that writes nothing leaves a column that looks the same as
 * one nobody has reached yet, and a backfill that marks a row done without
 * writing it can never be made to try again. So the thing under test is not
 * "does it store a document" -- it is whether UNKNOWN stays distinguishable
 * from EMPTY, on every path.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
error_reporting(E_ALL & ~E_DEPRECATED);

$fail = 0;
function ok($cond, $what) {
	global $fail;
	if ($cond) { echo "  ok    $what\n"; return true; }
	$fail++; echo "  FAIL  $what\n"; return false;
}
require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/lib/nft-metadata-lib.php';

class MRes {
	public $num_rows; private $r; private $i = 0;
	public function __construct($r) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return isset($this->r[$this->i]) ? $this->r[$this->i++] : null; }
}
class MConn {
	public $log = array(); public $fail = false; public $rows = array();
	public function real_escape_string($s) { return addslashes((string)$s); }
	public function query($sql) {
		$this->log[] = preg_replace('~\s+~', ' ', trim($sql));
		if ($this->fail) return false;
		if (stripos(ltrim($sql), 'SELECT') === 0) return new MRes($this->rows);
		return true;
	}
}

/* ---------------------------------------------------------------- *
 * UNKNOWN IS NOT EMPTY
 * ---------------------------------------------------------------- */
echo "a document nobody could read is never recorded as read\n";
/*
 * THE FAILURE THIS FEATURE IS MOST LIKELY TO HAVE. metadata_at is what the
 * backfill walks; stamping it on a row whose document could not be fetched
 * retires that row forever, and the trait is simply missing from then on
 * with nothing anywhere saying why.
 */
$c = new MConn();
ok(nft_metadata_store($c, 'asset1abc', array()) === false,
   'an empty document is written, which stamps metadata_at and retires the row from the '
 . 'backfill forever');
ok(count($c->log) === 0, 'an empty document still issued a query');
ok(nft_metadata_store($c, 'asset1abc', null) === false, 'a null document is written');
ok(nft_metadata_store($c, '', array('a' => 1)) === false, 'a document with no asset to '
 . 'attach it to is written against an empty asset_id, which matches every such row');
/* A real one does get written, and stamps the time. */
$c2 = new MConn();
ok(nft_metadata_store($c2, 'asset1abc', array('Type' => 'Orc')) === true,
   'a real document is not stored');
ok(count($c2->log) === 1 && stripos($c2->log[0], 'UPDATE nfts SET metadata') === 0,
   'the write is not an UPDATE on nfts: ' . (isset($c2->log[0]) ? $c2->log[0] : 'nothing'));
ok(strpos($c2->log[0], 'metadata_at = NOW()') !== false,
   'metadata_at is not stamped, so the backfill will fetch this row again forever');
/* BY asset_id, so every RFT row for one asset says the same thing. */
ok(strpos($c2->log[0], "WHERE asset_id = 'asset1abc'") !== false,
   'the write is keyed on something other than asset_id, so the copies of a multi-holder '
 . 'asset drift apart');
ok(strpos($c2->log[0], 'LIMIT') === false,
   'the write is limited to one row, so only one holder of an RFT gets the metadata');
/* A FAILED QUERY REPORTS FAILURE. The backfill counts on it to retry. */
$c3 = new MConn(); $c3->fail = true;
ok(nft_metadata_store($c3, 'asset1abc', array('Type' => 'Orc')) === false,
   'a failed write reports success, so the backfill moves on and the row stays empty');

/*
 * AND A HANDLE THAT CANNOT DO THE JOB IS REFUSED, NOT CALLED.
 *
 * This is not hypothetical: the Polygon harness's stub had no
 * real_escape_string() and the first write took the whole pass down with a
 * fatal. In production that is a nightly crawl dying, and a staker's NFTs
 * not verifying, over a column that exists to be convenient. Strictly
 * additive means it cannot cost the run.
 */
class MBare { }                       // no query(), no real_escape_string()
class MNoEscape { function query($s) { return true; } }
ok(nft_metadata_store(new MBare(), 'asset1abc', array('a' => 1)) === false,
   'an object that is not a connection is called anyway, which is a fatal inside a '
 . 'nightly worker');
ok(nft_metadata_store(new MNoEscape(), 'asset1abc', array('a' => 1)) === false,
   'a handle that cannot escape is called anyway, so the crawl dies on the first write');
ok(nft_metadata_store(null, 'asset1abc', array('a' => 1)) === false, 'a null handle is called');

/* AND AN ABSURD DOCUMENT IS REFUSED, NOT TRUNCATED. A non-strict MySQL cuts
   an over-long value silently, and half a JSON document is unparseable --
   worse than none, because metadata_at would say it had been read. */
$c4 = new MConn();
ok(nft_metadata_store($c4, 'asset1abc', array('x' => str_repeat('a', 70000))) === false,
   'a document too large for the column is sent anyway and silently truncated into '
 . 'unparseable JSON');
ok(count($c4->log) === 0, 'the oversized write was issued');

/* ---------------------------------------------------------------- *
 * THE DOCUMENT ITSELF
 * ---------------------------------------------------------------- */
echo "\nwhat is kept is the traits, not the picture again\n";
$ancestor = array(
	'Ear' => 'Warrior', 'Body' => 'Bone Armor', 'Face' => 'Dumb',
	'Head' => 'Soldier Helmet', 'Type' => 'Orc', 'name' => 'The Ancestor #151',
	'Weapon' => 'Bone Axe', 'Background' => 'Sea Green', 'project' => 'The Ancestors',
	'image' => array('ipfs://bafy...', '2i'), 'mediaType' => 'image/png',
	'files' => array(array('src' => 'ipfs://bafy...', 'name' => 'x')),
	'Discord' => 'https://discord.gg/x', 'Twitter' => 'https://x.com/x',
);
$clean = nft_metadata_clean($ancestor);
foreach (array('image', 'files', 'mediaType', 'Discord', 'Twitter') as $k)
	ok(!isset($clean[$k]), "the picture or the project's links are stored again under $k; "
	 . 'nfts.ipfs was derived from exactly that and the row doubles for nothing');
foreach (array('Ear', 'Body', 'Face', 'Head', 'Type', 'Weapon', 'Background') as $k)
	ok(isset($clean[$k]), "the $k trait was dropped");
ok(isset($clean['name']), 'the asset name was dropped, which is the one key every reader '
                        . 'needs to tie a document back to its asset');
/* A trait nobody has seen before survives. The column exists for the question
   that has not been asked yet. */
$clean2 = nft_metadata_clean(array('Something New' => 'yes', 'image' => 'x'));
ok(isset($clean2['Something New']), 'an unrecognised trait is dropped, so the column only '
                                  . 'ever holds what somebody already thought of');
ok(strlen(json_encode($clean)) < strlen(json_encode($ancestor)),
   'cleaning made the document bigger');
echo '  measured: ' . strlen(json_encode($ancestor)) . ' bytes raw, '
   . strlen(json_encode($clean)) . " stored\n";

/* ---------------------------------------------------------------- *
 * THE KOIOS SHAPE, WHICH IS WHERE CARDANO'S TRAITS ACTUALLY LIVE
 * ---------------------------------------------------------------- */
echo "\nthe CIP-25 walk finds the right asset in the envelope\n";
/*
 * minting_tx_metadata is a LIST of label objects, and the 721 one nests
 * policy -> asset name -> traits. A mint transaction carries EVERY asset it
 * minted, so taking the first object in the policy would hand back a
 * different NFT's traits -- which is the bug that looks completely fine.
 */
$row = (object)array(
	'policy_id' => 'abc123', 'asset_name_ascii' => 'The Ancestor #1714',
	'minting_tx_metadata' => array((object)array('abc123' => (object)array(
		'The Ancestor #733'  => (object)array('Type' => 'Dwarf', 'Weapon' => 'Wine Bottle'),
		'The Ancestor #1714' => (object)array('Type' => 'Orc',   'Weapon' => 'Bone Axe'),
	))),
);
$doc = nft_metadata_from_koios($row);
ok(isset($doc['Type']) && $doc['Type'] === 'Orc',
   'the walk returned the wrong asset out of the mint transaction: '
 . json_encode($doc));
ok(isset($doc['Weapon']) && $doc['Weapon'] === 'Bone Axe', 'the traits did not come through');
/* AND IT REFUSES RATHER THAN GUESSES when the envelope is not what it expects. */
ok(nft_metadata_from_koios((object)array('policy_id' => 'abc123')) === array(),
   'a row with no metadata produced a document anyway');
ok(nft_metadata_from_koios(null) === array(), 'a null row produced a document');
$miss = (object)array('policy_id' => 'abc123', 'asset_name_ascii' => 'Not Minted Here',
	'minting_tx_metadata' => array((object)array('abc123' => (object)array(
		'Something Else' => (object)array('Type' => 'Orc')))));
ok(nft_metadata_from_koios($miss) === array(),
   'an asset absent from its own mint metadata was handed another asset\'s traits');

/* ---------------------------------------------------------------- *
 * ONE ANSWER TO "WHAT ARE THIS NFT'S TRAITS"
 * ---------------------------------------------------------------- */
echo "\nevery chain's shape reads back as the same trait map\n";
/* CIP-25 / XLS-24: flat keys. */
$t1 = nft_metadata_traits(json_encode(array('Type' => 'Orc', 'Weapon' => 'Bone Axe',
	'name' => 'The Ancestor #151', 'description' => 'blah')));
ok($t1 === array('Type' => 'Orc', 'Weapon' => 'Bone Axe'),
   'a flat document did not read back as traits: ' . json_encode($t1));
/* Metaplex / EVM: attributes[{trait_type,value}]. */
$t2 = nft_metadata_traits(json_encode(array('name' => 'x', 'attributes' => array(
	array('trait_type' => 'Body', 'value' => 'Armor'),
	array('trait_type' => 'Face', 'value' => 'Wise')))));
ok($t2 === array('Body' => 'Armor', 'Face' => 'Wise'),
   'an attributes array did not read back as traits: ' . json_encode($t2));
ok(nft_metadata_traits('not json') === array(), 'a corrupt document threw instead of '
                                              . 'returning nothing');
ok(nft_metadata_traits(null) === array(), 'a null document threw');
ok(nft_metadata_traits(json_encode(array('name' => 'x'))) === array(),
   'the asset name is reported as a trait');

/* ---------------------------------------------------------------- *
 * EVERY CHAIN IS WIRED, AND WIRED IN THE RIGHT ORDER
 * ---------------------------------------------------------------- */
echo "\nall four crawls keep the document, after the row exists\n";
$CHAINS = array(
	/* THE CALL, NOT THE DEFINITION. processNFTMetadata() is defined a
	   hundred lines BELOW its own call site, so matching the bare name
	   found the definition and reported correct code as broken. */
	'verify.php'         => array('nft_metadata_from_koios($tokenresponsedata)',
	                              '$payload = processNFTMetadata($conn'),
	'verify-xrpl.php'    => array("nft_metadata_store(\$conn, \$nft['id']", 'processNFT('),
	'verify-solana.php'  => array("nft_metadata_store(\$conn, \$a['id']", 'processNFT('),
	'verify-polygon.php' => array("nft_metadata_store(\$conn, \$q['asset_id']", 'processNFT('),
);
foreach ($CHAINS as $file => $pair) {
	$src = harness_strip_comments(file_get_contents(__DIR__ . '/' . $file));
	ok(strpos($src, "require_once __DIR__ . '/lib/nft-metadata-lib.php'") !== false,
	   "$file calls the metadata library without loading it");
	ok(strpos($src, 'nft_metadata_store(') !== false,
	   "$file fetches the document and still throws it away");
	/*
	 * AFTER THE WRITE THAT CREATES THE ROW, NOT BEFORE.
	 *
	 * nft_metadata_store() is an UPDATE keyed on asset_id. Issued before
	 * processNFT() has created the row it matches nothing, affects nothing,
	 * and reports nothing -- the crawl looks healthy and the column stays
	 * empty forever. This is the single most likely way to get this wrong.
	 */
	$store = strpos($src, 'nft_metadata_store($conn');
	$write = strrpos($src, $pair[1]);
	ok($store !== false && $write !== false && $store > $write,
	   "$file stores the document BEFORE the row is created, so the UPDATE matches no "
	 . "rows and says nothing");
}

/* ---------------------------------------------------------------- *
 * THE ROWS THE CRAWL WOULD OTHERWISE NEVER REVISIT
 * ---------------------------------------------------------------- */
echo "\nthe three non-Cardano chains re-fetch rows that have no document yet\n";
/*
 * Those crawls fetch metadata for assets they are CREATING only --
 * verify-polygon.php says so in its own comment. Without a $needmeta set
 * every NFT already staked on those chains stays blank forever, and the
 * standalone backfill cannot help: the metadata URI is only known on chain.
 */
foreach (array('verify-xrpl.php', 'verify-solana.php', 'verify-polygon.php') as $file) {
	$src = harness_strip_comments(file_get_contents(__DIR__ . '/' . $file));
	ok(strpos($src, 'metadata_at IS NULL') !== false,
	   "$file never revisits a row with no document, so everything already staked on that "
	 . 'chain stays empty forever');
	ok(strpos($src, '$needmeta[') !== false, "$file has no needmeta set");
	/*
	 * AND IT MUST NOT BE FOLDED INTO $repair. `fix` means "rewrite this
	 * row's name and image"; a row that merely predates the column is not
	 * broken, and folding it in would rewrite a good name and image for
	 * every NFT on the chain.
	 */
	/* No trailing comma in the pattern: one of the three closes the array
	   on that line, so requiring one reported correct code as broken. */
	ok(preg_match("~'fix'\s*=>\s*isset\(\\\$repair\[.+?\]\)~", $src) === 1,
	   "$file widened 'fix' instead of 'need', so a row missing only its document has its "
	 . 'name and image rewritten too');
	ok(preg_match("~'need'\s*=>[^\n]*needmeta~", $src) === 1,
	   "$file does not let a missing document trigger the fetch");
	/* The previous check only proves $repair is still consulted; it passes
	   happily with needmeta ORed in beside it, which is the actual mistake. */
	ok(preg_match("~'fix'\s*=>[^\n]*needmeta~", $src) !== 1,
	   "$file's 'fix' consults needmeta, so a row missing only its document is treated as "
	 . 'broken and has its name and image rewritten');
}

/* ---------------------------------------------------------------- *
 * THE BACKFILL
 * ---------------------------------------------------------------- */
echo "\nthe backfill can be stopped and started\n";
$BF = harness_strip_comments(file_get_contents(__DIR__ . '/nft-metadata-backfill.php'));
ok(strpos($BF, 'metadata_at IS NULL') !== false,
   'the backfill does not walk the unfetched rows, so it cannot know what it owes');
ok(strpos($BF, "SHOW COLUMNS FROM nfts LIKE 'metadata_at'") !== false,
   'the backfill runs before the column exists, where every UPDATE fails silently and it '
 . 'reports a clean run having written nothing');
/* ASKED AND ACTED ON. Issuing the query and ignoring the answer reads as a
   guard and is not one. */
ok(preg_match('~if \(!\$have \|\| !\$have->num_rows\) \{~', $BF) === 1,
   'the backfill asks whether the column exists and then does not act on the answer');
ok(strpos($BF, 'exit(1);') !== false, 'the backfill carries on without the column');
ok(strpos($BF, 'GROUP BY asset_id') !== false,
   'the backfill fetches once per ROW rather than once per asset, so a multi-holder asset '
 . 'is fetched once per holder');
ok(strpos($BF, "PHP_SAPI !== 'cli'") !== false, 'the backfill is reachable over the web');
/* 35 is the batch size verify.php already observes against the free Koios plan. */
ok(strpos($BF, 'array_chunk($rows, 35)') !== false,
   'the backfill does not batch to 35, which is the free Koios plan limit verify.php '
 . 'already works around');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall NFT metadata checks passed\n");
exit($fail ? 1 : 0);
