<?php
/* verify-xrpl-doctor.php — CLI only. "A holder connected their wallet and
 * nothing showed up." This says why.
 *
 *     php verify-xrpl-doctor.php <r-address | user_id>
 *
 * WHY A DEDICATED TOOL. There are six places this can break and five of them
 * fail SILENTLY -- a collection registered on the wrong chain, a taxon off by
 * one, a wallet row written with blockchain_id 1, an account that holds the
 * art on a different address, a node that would not answer. Every one of
 * them produces the same symptom: zero NFTs, no error. Checking them one
 * query at a time by hand is how an evening goes.
 *
 * It WRITES NOTHING. Read-only against the database and the ledger, so it is
 * safe to run while somebody is waiting on the other end of a chat.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

chdir(__DIR__);                      // db.php uses relative includes
include __DIR__ . '/db.php';
require_once __DIR__ . '/verify-xrpl.php';

$arg = isset($argv[1]) ? trim($argv[1]) : '';
if ($arg === '') {
	echo "usage: php verify-xrpl-doctor.php <r-address | user_id>\n";
	exit(1);
}

function hr($t){ echo "\n" . $t . "\n" . str_repeat('-', strlen($t)) . "\n"; }
$problems = array();

/* ---- 1. collections ---------------------------------------------------- */
hr('1. Registered XRPL collections');
$rows = array();
$q = $conn->query("SELECT id, name, policy, rate, blockchain_id, project_id
                   FROM collections ORDER BY id");
while ($q && $r = $q->fetch_assoc()) $rows[] = $r;

$xrpl = array(); $misfiled = array();
foreach ($rows as $r) {
	/* An XRPL policy is issuer:taxon. Spotting one on the wrong chain is the
	   single most likely cause of this whole problem: collections.blockchain_id
	   DEFAULTs to 1, so a row added through anything that does not name the
	   column looks perfect and is invisible to the XRPL pass. */
	$looks_xrpl = (bool)preg_match('/^r[1-9A-HJ-NP-Za-km-z]{24,34}:\d+$/', $r['policy']);
	if ((int)$r['blockchain_id'] === XRPL_CHAIN_ID) $xrpl[] = $r;
	elseif ($looks_xrpl) $misfiled[] = $r;
}
if (!$xrpl && !$misfiled) echo "  none.\n";
foreach ($xrpl as $r)
	printf("  [ok] #%s  %s\n       %s   rate %s  project %s\n",
		$r['id'], $r['name'], $r['policy'], $r['rate'], $r['project_id']);
foreach ($misfiled as $r) {
	printf("  [!!] #%s  %s\n       %s\n", $r['id'], $r['name'], $r['policy']);
	printf("       blockchain_id is %s, must be %d. The XRPL pass cannot see this row.\n",
		$r['blockchain_id'], XRPL_CHAIN_ID);
	printf("       UPDATE collections SET blockchain_id = %d WHERE id = %s;\n",
		XRPL_CHAIN_ID, $r['id']);
	$problems[] = "collection #{$r['id']} is on the wrong chain";
}

/* ---- 2. the wallet ----------------------------------------------------- */
hr('2. The wallet');
if (preg_match('/^\d+$/', $arg)) {
	$user_id = (int)$arg; $account = '';
} else {
	$user_id = 0; $account = $arg;
}
$esc = $conn->real_escape_string($account);
$sql = $account !== ''
	? "SELECT * FROM wallets WHERE stake_address = '$esc'"
	: "SELECT * FROM wallets WHERE user_id = " . (int)$user_id;
$w = $conn->query($sql);
$wallets = array();
while ($w && $r = $w->fetch_assoc()) $wallets[] = $r;

if (!$wallets) {
	echo "  NO WALLET ROW. The link never wrote one -- so whatever happened in\n";
	echo "  the browser, the server did not record it. Ask them to connect again\n";
	echo "  and watch for an error in the modal.\n";
	$problems[] = 'no wallet row';
} else {
	foreach ($wallets as $r) {
		$chain = (int)$r['blockchain_id'];
		printf("  user %s   chain %d %s  via %s\n     %s\n",
			$r['user_id'], $chain,
			$chain === XRPL_CHAIN_ID ? '(XRPL)' : '(NOT XRPL)',
			isset($r['link_method']) && $r['link_method'] !== null ? $r['link_method'] : '-',
			$r['stake_address']);
		if (preg_match('/^r[1-9A-HJ-NP-Za-km-z]{24,34}$/', $r['stake_address'])
		    && $chain !== XRPL_CHAIN_ID) {
			printf("     [!!] an r-address filed as chain %d. The XRPL pass skips it.\n", $chain);
			printf("     UPDATE wallets SET blockchain_id = %d WHERE id = %s;\n",
				XRPL_CHAIN_ID, $r['id']);
			$problems[] = 'wallet row on the wrong chain';
		}
		if ($user_id === 0) $user_id = (int)$r['user_id'];
		if ($account === '' && $chain === XRPL_CHAIN_ID) $account = $r['stake_address'];
	}
}

/* ---- 3. the ledger ----------------------------------------------------- */
hr('3. What the ledger says that account holds');
if ($account === '') {
	echo "  no XRPL address to read.\n";
} else {
	$api = getChainSetting($conn, XRPL_CHAIN_ID, 'api_base', 'https://xrplcluster.com');
	echo "  node    $api\n  account $account\n\n";
	$got = xrpl_account_nfts($api, $account, 'xrpl_http');
	if (!$got['ok']) {
		echo "  COULD NOT READ THE ACCOUNT. Node unreachable or erroring.\n";
		$problems[] = 'ledger unreadable';
	} elseif (!$got['list']) {
		echo "  The account read fine and holds NO NFTokens.\n";
		echo "  They are looking at a different address than the one they linked,\n";
		echo "  or the NFTs are still sitting in an unaccepted sell offer.\n";
		$problems[] = 'account holds nothing';
	} else {
		$by = array();
		foreach ($got['list'] as $n) $by[$n['policy']] = (isset($by[$n['policy']]) ? $by[$n['policy']] : 0) + 1;
		$registered = array();
		foreach ($xrpl as $r) $registered[$r['policy']] = $r['name'];
		printf("  %d NFToken(s) in %d collection(s)\n\n", count($got['list']), count($by));
		foreach ($by as $policy => $n) {
			$hit = isset($registered[$policy]);
			printf("  %-4s %-3d  %s%s\n", $hit ? '[ok]' : '[--]', $n, $policy,
				$hit ? '   ' . $registered[$policy] : '');
		}
		$matched = 0;
		foreach ($by as $policy => $n) if (isset($registered[$policy])) $matched += $n;
		printf("\n  %d of %d held NFTokens are in a registered collection.\n",
			$matched, count($got['list']));
		if ($matched === 0) {
			echo "\n  NOTHING MATCHES. Compare the [--] keys above with section 1:\n";
			echo "  if the issuer is right and the taxon differs, the registered\n";
			echo "  taxon is wrong. Confirm it with:\n";
			echo "    php verify-xrpl-probe.php <the collection's xrp.cafe slug>\n";
			$problems[] = 'no held collection matches a registered one';
		}
	}
}

/* ---- 4. what is stored ------------------------------------------------- */
hr('4. Rows already stored for this user');
if ($user_id > 0) {
	$n = $conn->query("SELECT n.id, n.asset_id, n.asset_name, n.collection_id, n.user_id
	                   FROM nfts n WHERE n.user_id = " . (int)$user_id
	                 . " AND n.blockchain_id = " . XRPL_CHAIN_ID . " LIMIT 20");
	$c = 0;
	while ($n && $r = $n->fetch_assoc()) {
		printf("  #%s  coll %s  %s\n", $r['id'], $r['collection_id'], $r['asset_name']);
		$c++;
	}
	if (!$c) echo "  none on chain " . XRPL_CHAIN_ID . ".\n";
} else {
	echo "  no user resolved.\n";
}

/* ---- verdict ----------------------------------------------------------- */
hr('Verdict');
if (!$problems) {
	echo "  Nothing obviously wrong. Re-run the link, or:\n";
	echo "    php verify.php verify=xrpl dry=1 addr=$account\n";
} else {
	foreach ($problems as $i => $p) printf("  %d. %s\n", $i + 1, $p);
	echo "\n  Fix the first one and re-run this. Nothing above was written.\n";
}
echo "\n";
