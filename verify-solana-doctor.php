<?php
/* verify-solana-doctor.php — CLI only. "A holder connected Solflare and
 * nothing showed up." This says why.
 *
 *     php verify-solana-doctor.php                       # list Solana wallets
 *     php verify-solana-doctor.php <address | user_id | username>
 *
 * WHY A DEDICATED TOOL. There are seven places this can break and six of them
 * fail SILENTLY -- the blockchains row missing, no collection registered, the
 * collection registered on the wrong chain, the wallet row written with
 * blockchain_id 1, the holder keeping the art on a different address, the
 * assets being on a standard this platform does not read, or the cluster
 * declining to answer. Every one produces the same symptom: zero NFTs and no
 * error anywhere.
 *
 * It WRITES NOTHING. Read-only against the database and the chain, so it is
 * safe to run while somebody is waiting on the other end of a chat.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

chdir(__DIR__);                      // db.php uses relative includes
include __DIR__ . '/db.php';
require_once __DIR__ . '/verify-solana.php';

$arg = isset($argv[1]) ? trim($argv[1]) : '';
$CH  = SOLANA_CHAIN_ID;

function say($s = '') { echo $s . "\n"; }
function head($s) { say(); say($s); say(str_repeat('-', strlen($s))); }

/*
 * NO ARGUMENT LISTS THE WALLETS. You almost never have the address when you
 * need this -- the report arrives as "I connected and nothing showed up", and
 * asking somebody to find their Solana address is another round trip while
 * they are still in the mood to help. Whoever just linked is the last row.
 */
if ($arg === '') {
	head('Linked Solana wallets');
	$q = $conn->query("SELECT w.id, w.user_id, w.blockchain_id, w.link_method,
	                          w.stake_address, u.username
	                   FROM wallets w LEFT JOIN users u ON u.id = w.user_id
	                   WHERE w.blockchain_id = $CH
	                   ORDER BY w.id DESC LIMIT 25");
	$n = 0;
	while ($q && $r = $q->fetch_assoc()) {
		$n++;
		printf("  %-44s  user %-5s %-12s %s\n", $r['stake_address'],
			$r['user_id'], (string)$r['username'], (string)$r['link_method']);
	}
	if (!$n) {
		say('  none.');
		say();
		say('  If somebody has linked one, it went in as another chain. Look for a');
		say('  base58 address sitting in a blockchain_id 1 row:');
		say("    SELECT id, user_id, blockchain_id, stake_address FROM wallets");
		say("     WHERE stake_address NOT LIKE 'stake1%' AND stake_address NOT LIKE 'r%';");
	}
	say();
	say('  Then: php verify-solana-doctor.php <address>');
	exit(0);
}

/* ---- 1. is the chain even set up? ---------------------------------------- */
head('The chain');
$b = $conn->query("SELECT * FROM blockchains WHERE id = $CH LIMIT 1");
$brow = ($b && $b->num_rows) ? $b->fetch_assoc() : null;
if (!$brow) {
	say("  NO blockchains ROW FOR id $CH. Nothing will ever verify.");
	say('  See solana-schema.md — one INSERT.');
} else {
	printf("  %s (%s)%s\n", $brow['name'], $brow['slug'],
		isset($brow['active']) && !$brow['active'] ? '   INACTIVE' : '');
}
$api = getChainSetting($conn, $CH, 'api_base', 'https://api.mainnet-beta.solana.com');
say('  rpc: ' . $api);

/* ---- 2. are there collections to match against? -------------------------- */
head('Registered Solana collections');
$collections = getCollectionIDs($conn, $CH);
if (!$collections) {
	say('  NONE. A holder can link all day and nothing will ever be staked:');
	say('  the pass matches what they hold against this list and an empty list');
	say('  matches nothing.');
	say('    php verify-solana-probe.php <any asset address>   # prints the INSERT');
} else {
	$q = $conn->query("SELECT c.id, c.name, c.policy, c.rate, p.name AS project
	                   FROM collections c LEFT JOIN projects p ON p.id = c.project_id
	                   WHERE c.blockchain_id = $CH");
	while ($q && $r = $q->fetch_assoc())
		printf("  #%-4s %-22s %-44s rate %-8s %s\n", $r['id'], $r['name'],
			$r['policy'], (string)$r['rate'], (string)$r['project']);
}

/* ---- 3. who is this? ----------------------------------------------------- */
head('The wallet');
$addresses = array(); $user_id = 0;
if (ctype_digit($arg)) {
	$user_id = (int)$arg;
} else if (sol_valid_address($arg)) {
	$esc = $conn->real_escape_string($arg);
	$q = $conn->query("SELECT user_id, blockchain_id FROM wallets WHERE stake_address = '$esc' LIMIT 1");
	if ($q && $q->num_rows) {
		$r = $q->fetch_assoc();
		$user_id = (int)$r['user_id'];
		/* THE ONE THAT BIT XRPL: an address stored under the wrong chain. It
		   reads as linked, it is in the table, and the chain-scoped pass
		   never looks at it. */
		if ((int)$r['blockchain_id'] !== $CH)
			say("  ** THIS ADDRESS IS FILED AS blockchain_id " . (int)$r['blockchain_id']
			  . ", NOT $CH. The Solana pass will never read it, and the Cardano pass"
			  . " will send it to Koios. Fix: UPDATE wallets SET blockchain_id = $CH"
			  . " WHERE stake_address = '" . $arg . "';");
	} else {
		say('  Not linked to any account. Reading the chain anyway.');
	}
	$addresses[] = $arg;
} else {
	$esc = $conn->real_escape_string($arg);
	$q = $conn->query("SELECT id FROM users WHERE username = '$esc' LIMIT 1");
	if ($q && $q->num_rows) $user_id = (int)$q->fetch_assoc()['id'];
	else { say('  No user, address or id matches "' . $arg . '".'); exit(1); }
}

if ($user_id > 0) {
	printf("  user_id %d\n", $user_id);
	$q = $conn->query("SELECT stake_address, blockchain_id, link_method FROM wallets
	                   WHERE user_id = $user_id ORDER BY blockchain_id");
	while ($q && $r = $q->fetch_assoc()) {
		printf("    chain %-2s %-44s %s\n", $r['blockchain_id'], $r['stake_address'],
			(string)$r['link_method']);
		if ((int)$r['blockchain_id'] === $CH && !in_array($r['stake_address'], $addresses, true))
			$addresses[] = $r['stake_address'];
	}
	if (!$addresses) say('    no Solana wallet linked to this account.');
}
if (!$addresses) exit(0);

/* ---- 4. what does the chain actually say? -------------------------------- */
foreach ($addresses as $addr) {
	head('On chain: ' . $addr);
	$got = sol_account_assets($api, $addr, 'sol_http');
	if (!$got['ok']) {
		say('  COULD NOT READ. The cluster refused or rate-limited.');
		say('  That is also what the nightly pass would hit — and it correctly');
		say('  writes nothing rather than reading this as an empty wallet.');
		continue;
	}
	if (!$got['list']) {
		say('  Holds no Metaplex Core assets at all.');
		say();
		say('  If they can SEE NFTs in Solflare, those are on another standard:');
		say('    Token Metadata (an SPL mint plus a metadata PDA) or compressed.');
		say('  Neither is read by verify-solana.php, and neither is a setting —');
		say('  each would be its own reader. Confirm with:');
		say('    php verify-solana-probe.php <one of their asset addresses>');
		continue;
	}
	$mine = 0; $theirs = array();
	foreach ($got['list'] as $a) {
		if (isset($collections[$a['collection']])) $mine++;
		else $theirs[$a['collection']] = (isset($theirs[$a['collection']]) ? $theirs[$a['collection']] : 0) + 1;
	}
	printf("  %d Core asset(s): %d in registered collections, %d not.\n",
		count($got['list']), $mine, count($got['list']) - $mine);
	foreach ($theirs as $c => $n)
		printf("    unregistered  %-44s %d held\n", $c, $n);
	if ($mine === 0 && $theirs) {
		say();
		say('  THIS IS THE USUAL ANSWER: they hold Core assets, just not from a');
		say('  collection anybody registered. Run the probe on one of the');
		say('  addresses above to get its INSERT.');
	}

	/* ---- 5. and what did we write? --------------------------------------- */
	$esc = $conn->real_escape_string($addr);
	$q = $conn->query("SELECT COUNT(*) AS n FROM nfts n
	                   JOIN wallets w ON w.user_id = n.user_id
	                   WHERE w.stake_address = '$esc' AND n.blockchain_id = $CH");
	$have = ($q && $q->num_rows) ? (int)$q->fetch_assoc()['n'] : 0;
	printf("  in the database for this holder: %d row(s) on chain %d\n", $have, $CH);
	if ($mine > 0 && $have === 0) {
		say();
		say('  THEY HOLD REGISTERED ASSETS AND WE HAVE NONE. The pass has not run');
		say('  since they linked, or it refused. Run it dry and read the line:');
		say('    php verify.php verify=solana dry=1 addr=' . $addr);
	}
	if ($have > $mine) {
		say();
		say('  WE HAVE MORE THAN THEY HOLD. Rows from a previous night that the');
		say('  last pass could not clear — expected if the most recent pass');
		say('  aborted on an unreadable address. It corrects itself on the next');
		say('  clean run.');
	}
}
say();
