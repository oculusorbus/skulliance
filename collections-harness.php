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

echo "the WHERE is built, not glued together\n";

$c = new CConn(); $c->chains = $CH4; $c->rows = $ROWS;
render($c);
$q = listing_sql();
ok(strpos($q, "WHERE users.id != '0'") !== false,
   "the user condition is not in a WHERE -- it is riding in the JOIN's ON again: $q");
ok(strpos($q, "project_id AND users.id") === false,
   'the user condition is glued onto the JOIN condition');

render($c, 9);
$q = listing_sql();
ok(strpos($q, "collections.project_id = '9'") !== false, 'the project filter is not applied');
ok(strpos($q, "users.id != '0'") !== false, 'the project filter dropped the user condition');

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
ok(strpos($badge, 'XRP Ledger') !== false, 'the badge lost the chain name');
ok(strpos($badge, 'onerror=') !== false,
   'the badge has no lettermark fallback -- icons ship by FTP, so a new chain would show a broken image');
/* The name is attacker-controlled only by an admin, but it is still output. */
ok(strpos(chainBadge('x', '<script>'), '<script>') === false, 'the chain name is not escaped');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "collections: ok\n";
