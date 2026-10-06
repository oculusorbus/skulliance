<?php
/* collections-harness.php — CLI only. No database, no network.
 *
 * The Collections table grew a Chain column and a chain filter. Three
 * things here are worth a test rather than a look:
 *
 *  1. THE WHERE CLAUSE. The old version string-appended "WHERE ..." in
 *     front of "AND users.id != '0'", so with no project filter the user
 *     condition ended up inside the JOIN's ON instead of a WHERE.
 *     Harmless for an INNER JOIN and a trap the moment one becomes LEFT
 *     -- and there was no room in that shape for a second filter at all.
 *  2. THE COLUMN IS CONDITIONAL. One chain means a column of identical
 *     logos, which is worse than no column.
 *  3. THE ICON FILENAME IS NOT THE SLUG for xrpl, and that has already
 *     shipped as a broken image once, on the homepage.
 *
 * Usage: php collections-harness.php */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

require __DIR__ . '/lib/chain-icons.php';

/* ---- lift the real functions out of db.php ---------------------------- */
$dsrc = file_get_contents(__DIR__ . '/db.php');
function lift($src, $sig, $what) {
	$at = strpos($src, $sig);
	ok($at !== false, "$what is gone from db.php");
	if ($at === false) return '';
	$i = strpos($src, '{', $at); $d = 0;
	for ($n = strlen($src); $i < $n; $i++) {
		if ($src[$i] === '{') $d++;
		elseif ($src[$i] === '}') { $d--; if (!$d) return substr($src, $at, $i - $at + 1); }
	}
	return '';
}
eval(lift($dsrc, 'function chainBadge(', 'chainBadge()'));
eval(lift($dsrc, 'function getChainsWithCollections(', 'getChainsWithCollections()'));
eval(lift($dsrc, 'function getPoliciesListing(', 'getPoliciesListing()'));

/* collectionMarketUrl() is the cell's link and is tested in
   verify-polygon-harness; here it only has to exist. */
if (!defined('XRPL_CHAIN_ID'))    define('XRPL_CHAIN_ID', 2);
if (!defined('SOLANA_CHAIN_ID'))  define('SOLANA_CHAIN_ID', 3);
if (!defined('POLYGON_CHAIN_ID')) define('POLYGON_CHAIN_ID', 4);
eval(lift($dsrc, 'function collectionMarketUrl(', 'collectionMarketUrl()'));
function getChainSetting($c, $b, $f, $d = '') { return $d; }

/* ---- a database that records what it was asked ------------------------ */
$SQL = array();
class CRes {
	public $num_rows; private $rows;
	function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	function fetch_assoc() { return array_shift($this->rows); }
}
class CConn {
	public $chains = array(); public $rows = array();
	function query($sql) {
		global $SQL; $SQL[] = preg_replace('/\s+/', ' ', $sql);
		if (strpos($sql, 'FROM blockchains') !== false) return new CRes($this->chains);
		return new CRes($this->rows);
	}
}
function render($conn, $pid = 0, $bid = 0) {
	global $SQL; $SQL = array();
	ob_start(); getPoliciesListing($conn, $pid, $bid); return ob_get_clean();
}
function listing_sql() {
	global $SQL;
	foreach ($SQL as $q) if (strpos($q, 'FROM collections') !== false) return $q;
	return '';
}

$CH4 = array(
	array('id' => 1, 'slug' => 'cardano', 'name' => 'Cardano'),
	array('id' => 2, 'slug' => 'xrpl',    'name' => 'XRP Ledger'),
	array('id' => 4, 'slug' => 'polygon', 'name' => 'Polygon'),
);
$ROWS = array(
	array('collection_name' => 'Skulliance', 'policy' => str_repeat('a', 56), 'blockchain_id' => 1,
	      'marketplace_slug' => null, 'rate' => '5', 'project_name' => 'Skulliance',
	      'currency' => 'SKULL', 'total' => '120'),
	array('collection_name' => 'Danketsu', 'policy' => '0xee79a3e8aef1109a6ee82bf399ce9e1bd43cf5c4',
	      'blockchain_id' => 4, 'marketplace_slug' => 'danketsu-nft', 'rate' => '3',
	      'project_name' => 'Danketsu', 'currency' => 'DANK', 'total' => '1'),
);

echo "every collection is listed, staked or not\n";

$c = new CConn(); $c->chains = $CH4; $c->rows = $ROWS;
render($c);
$q = listing_sql();

/*
 * THE PAGE IS A SHOP, NOT JUST A REGISTRY. A collection nobody has
 * staked yet is the one a staker can go and buy into, and INNER JOIN
 * nfts hid exactly those: no staked NFTs, no rows, gone from its own
 * registry.
 */
ok(strpos($q, 'LEFT JOIN nfts') !== false,
   'nfts is INNER JOINed again, so a collection nobody has staked vanishes from the list');

/*
 * AND THE USER CONDITION MUST BE IN THE JOIN, which is the exact
 * opposite of what this file asserted an hour ago -- and for a reason,
 * not a reversal. A LEFT JOIN followed by a WHERE on the right table's
 * column is an INNER JOIN with extra steps: the unmatched rows come
 * back NULL and the WHERE discards them, undoing the outer join
 * silently. The previous comment in db.php called this "a trap the
 * moment anything becomes a LEFT JOIN".
 */
ok(preg_match("/LEFT JOIN users ON users\\.id = nfts\\.user_id AND users\\.id != '0'/", $q) === 1,
   "the user condition is not in the users JOIN: $q");
ok(strpos($q, "WHERE users.id") === false,
   'the user condition is back in a WHERE, which collapses the LEFT JOIN to an INNER one and '
 . 'hides unstaked collections again');

/*
 * COUNT(users.id), NOT COUNT(nfts.id). With the join outer, an NFT whose
 * owner is user 0 or deleted still yields a row with nfts.id set and
 * users.id NULL -- counting nfts.id would credit the collection with it
 * and every existing total would quietly change.
 */
ok(strpos($q, 'COUNT(users.id)') !== false,
   'the count is back on nfts.id, which counts NFTs owned by nobody');

/* With no filters at all there is no WHERE to write. */
ok(strpos($q, 'WHERE') === false, "an unfiltered listing emits a WHERE clause: $q");

render($c, 9);
$q = listing_sql();
ok(strpos($q, "WHERE collections.project_id = '9'") !== false, 'the project filter is not applied');
ok(strpos($q, "users.id != '0'") !== false, 'the project filter dropped the user condition');
/* The filters are conditions on COLLECTIONS -- the left table -- so they
   belong in WHERE and do not re-collapse the outer join. */
ok(strpos($q, 'LEFT JOIN nfts') !== false,
   'filtering by project turned the nfts join back into an inner one');

render($c, 0, 4);
$q = listing_sql();
ok(strpos($q, "collections.blockchain_id = '4'") !== false, 'the chain filter is not applied');

/* BOTH AT ONCE. Picking a chain must not throw away the project. */
render($c, 9, 4);
$q = listing_sql();
ok(strpos($q, "collections.project_id = '9'") !== false
   && strpos($q, "collections.blockchain_id = '4'") !== false,
   'the two filters do not compose -- one replaces the other');

/* Injection: both arrive from a form. */
render($c, 0, 0);
$evil = render($c, "9 OR 1=1", "4; DROP TABLE nfts");
$q = listing_sql();
ok(strpos($q, 'DROP') === false && strpos($q, 'OR 1=1') === false,
   "a filter value reached the SQL unescaped: $q");

/* And the row itself: a zero-count collection renders, with its
   marketplace link intact, because that link is the entire point of
   showing it. */
$c0 = new CConn(); $c0->chains = $CH4;
$c0->rows = array(array('collection_name' => 'Brand New', 'policy' => str_repeat('b', 56),
	'blockchain_id' => 1, 'marketplace_slug' => null, 'rate' => '5',
	'project_name' => 'Skulliance', 'currency' => 'SKULL', 'total' => '0'));
$h0 = render($c0);
ok(strpos($h0, 'Brand New') !== false, 'a collection with nothing staked does not render at all');
ok(strpos($h0, 'wayup.io') !== false,
   'an unstaked collection has no marketplace link, which is the one thing a shopper came for');
ok(preg_match('/>0</', $h0) === 1, 'the zero total is not shown');

echo "\nthe Chain column earns its width or is not there\n";

$html = render($c);
ok(strpos($html, '<th align=\'left\'>Chain</th>') !== false, 'the Chain header is missing with 3 chains');
ok(substr_count($html, 'chain-badge') >= 2, 'the chain badges are missing from the rows');

/* One chain: a column of identical logos is worse than no column. */
$c1 = new CConn(); $c1->chains = array($CH4[0]); $c1->rows = $ROWS;
$html1 = render($c1);
ok(strpos($html1, 'Chain</th>') === false,
   'a single-chain platform still gets a Chain column of identical logos');
/* The header count and the body cell count must agree, or the table skews. */
foreach (array(array($c, 5), array($c1, 4)) as $case) {
	list($cc, $cols) = $case;
	$h = render($cc);
	ok(substr_count($h, '<th') === $cols,
	   'expected ' . $cols . ' headers, got ' . substr_count($h, '<th'));
	ok(substr_count($h, '<td') === $cols * count($ROWS),
	   'expected ' . ($cols * count($ROWS)) . ' cells, got ' . substr_count($h, '<td')
	 . ' -- a header and body mismatch shifts every column');
}

echo "\nthe filter control names its own axis\n";

/*
 * SIDE BY SIDE WITH THE PROJECT FILTER, which shows "Project" at rest
 * because its first option IS the label. Pre-selecting "All" on the
 * chain one put "Project" and "All" next to each other, where the
 * second does not say all of WHAT.
 */
$fsrc = file_get_contents(__DIR__ . '/skulliance.php');
$at   = strpos($fsrc, 'id="filterChainSel"');
ok($at !== false, 'the chain select is gone from filterPolicies()');
$sel_block = substr($fsrc, $at, 600);
ok(strpos($sel_block, '<option value="0">Chain</option>') !== false,
   'the chain select has no resting label, so it reads "All" beside "Project"');
ok(strpos($sel_block, '<option value="0">All</option>') !== false,
   'the chain select lost its plain All option');
/* The All option must carry NO conditional selected -- that is exactly
   what replaced the "Chain" label with the word "All". A real chain
   still does, or the control forgets what it is filtering by. */
ok(preg_match('/selected[^>]{0,40}>All</', $sel_block) === 0,
   'the All option is pre-selected again, which hides the "Chain" label');
ok(strpos($sel_block, "\$sel === (int)\$cid ? ' selected'") !== false,
   'a chosen chain is no longer marked selected, so the control forgets what it is filtering by');

/*
 * IT MUST LOOK LIKE THE CONTROL BESIDE IT. Compared against #filterNFTs
 * rather than asserted to exist: the first version checked only that
 * "#filterChainSel {" appeared somewhere, and the narrow-screen rule
 * uses the same selector -- so renaming the MAIN rule still passed.
 */
$css = file_get_contents(__DIR__ . '/dist/flexbox.css');
function decls($css, $sel) {
	if (!preg_match('/' . preg_quote($sel, '/') . '\s*\{([^}]*)\}/', $css, $m)) return null;
	$out = array();
	foreach (explode(';', $m[1]) as $d) {
		$d = trim($d); if ($d === '') continue;
		list($k, $v) = array_pad(explode(':', $d, 2), 2, '');
		$k = trim($k);
		/* width and margin legitimately differ: a chain name is one short
		   word where a project name is not. */
		if (in_array($k, array('width', 'margin-left', 'margin-top'), true)) continue;
		$out[$k] = trim($v);
	}
	return $out;
}
$a = decls($css, '#filterNFTs');
$b = decls($css, '#filterChainSel');
ok($b !== null, 'the chain select has no rule, so it renders as a white browser default beside a dark one');
ok($a !== null && $b !== null && $a == $b,
   'the chain select no longer matches #filterNFTs: ' . json_encode(array_diff_assoc((array)$a, (array)$b)));
ok(strpos($css, '#filterNFTs, #faction, #filterChainSel') !== false,
   'the chain select is left out of the narrow-screen rule');
echo "\nthe logo, and the name that is not the filename\n";

ok(chain_icon_file('xrpl') === 'xrp',
   "blockchains.slug 'xrpl' must map to icons/xrp.png; it shipped as a broken image on the homepage");
ok(chain_icon_file('cardano') === 'cardano', 'an unmapped slug no longer uses itself');
ok(chain_icon_file('polygon') === 'polygon', 'polygon does not resolve to its own file');
ok(chain_icon_file('') === '', 'an empty slug produces a filename');
ok(chain_icon_file('../../etc/passwd') === 'etcpasswd',
   'a slug with path characters is not stripped before becoming a filename');
ok(chain_icon_mark('polygon') === 'POL', 'the lettermark is wrong');

$badge = chainBadge('xrpl', 'XRP Ledger');
ok(strpos($badge, "icons/xrp.png") !== false, "the badge points at icons/xrp.png: $badge");
ok(strpos($badge, 'onerror=') !== false,
   'the badge has no lettermark fallback -- icons ship by FTP, so a new chain would show a broken image');

/*
 * LOGO ONLY, NAME IN A TOOLTIP. Repeating the word beside every logo
 * doubles the column to restate what the picture said.
 */
$text = trim(strip_tags($badge));
ok($text === '', "the chain name is printed beside the logo again: '$text'");
ok(strpos($badge, "title='XRP Ledger'") !== false,
   'the badge has no title, so a reader who does not recognise the mark cannot find out what it is');

/*
 * AND THE alt IS NOT EMPTY, which stopped being optional the moment the
 * visible name went away. While the name sat beside it the image was
 * decorative; now the image IS the information, so alt='' leaves the
 * column blank to a screen reader. title is not a substitute -- it is
 * not announced reliably and never appears on touch.
 */
ok(strpos($badge, "alt='XRP Ledger'") !== false,
   'the logo carries an empty alt, so the Chain column is unreadable without a mouse');
ok(strpos($badge, "alt=''") === false, 'the logo alt is empty');
/* The lettermark replacement has to carry it too, or the fallback is
   the state that loses the information. */
ok(strpos($badge, "aria-label':this.alt") !== false && strpos($badge, 'title:this.alt') !== false,
   'the lettermark fallback drops the chain name, so a missing icon makes the chain unknowable');

/* The name is admin-entered, and it lands inside SINGLE-quoted
   attributes -- an apostrophe must not close one. */
ok(strpos(chainBadge('x', '<script>'), '<script>') === false, 'the chain name is not escaped');
$apos = chainBadge('cardano', "Bob's Chain");
ok(strpos($apos, "Bob's") === false && strpos($apos, '&#039;') !== false,
   "an apostrophe in a chain name breaks out of the single-quoted title attribute: $apos");

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "collections: ok\n";
