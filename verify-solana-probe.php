<?php
/**
 * verify-solana-probe.php — find a Solana collection, and print its INSERT.
 *
 *   php verify-solana-probe.php <collection address>
 *   php verify-solana-probe.php <asset address>        one NFT from it
 *   php verify-solana-probe.php <wallet address>       list what a wallet holds
 *
 * READS THE CHAIN AND NOTHING ELSE. No database, no credentials, no linked
 * wallet — so a collection can be checked before any of the go-live steps
 * have been taken, which is the point. It prints the INSERT; a human runs it.
 *
 * WHY AN ASSET ADDRESS IS ENOUGH. Nobody should have to know what an MPL Core
 * collection account is. A holder or an artist can paste any one NFT — the
 * thing a marketplace or an explorer link gives them — and the collection is
 * read off it. Same reasoning as verify-xrpl-probe.php taking an NFTokenID:
 * ask for the thing people actually have.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/verify-solana.php';

$arg = isset($argv[1]) ? trim($argv[1]) : '';
$api = isset($argv[2]) ? trim($argv[2]) : 'https://api.mainnet-beta.solana.com';

if ($arg === '') {
	echo "Usage: php verify-solana-probe.php <collection|asset|wallet address> [rpc url]\n\n"
	   . "  Any Solana address will do. The probe works out which kind it is and\n"
	   . "  prints the INSERT for the collection it finds.\n";
	exit(1);
}
if (!sol_valid_address($arg)) {
	echo "That is not a Solana address. A pubkey is 32 bytes, written as 32-44\n"
	   . "base58 characters (no 0, O, I or l).\n";
	exit(1);
}

/** One account, raw. */
function probe_account($api, $pubkey) {
	$body = sol_http($api, json_encode(array(
		'jsonrpc' => '2.0', 'id' => 1, 'method' => 'getAccountInfo',
		'params'  => array($pubkey, array('encoding' => 'base64', 'commitment' => 'confirmed')),
	)));
	$j = json_decode((string)$body, true);
	if (!is_array($j) || isset($j['error']) || empty($j['result']['value'])) return null;
	return $j['result']['value'];
}

/** A CollectionV1 account: key byte 5, then update authority, name, uri, and
    the two counters that say how big the collection is. */
function probe_parse_collection($b64) {
	$d = base64_decode($b64, true);
	if ($d === false || strlen($d) < 70 || ord($d[0]) !== 5) return null;
	$p = 1 + 32;                                   // key, update authority
	$str = function ($d, &$p) {
		if (strlen($d) < $p + 4) return null;
		$len = unpack('V', substr($d, $p, 4))[1]; $p += 4;
		if ($len > 2048 || strlen($d) < $p + $len) return null;
		$s = substr($d, $p, $len); $p += $len; return $s;
	};
	$name = $str($d, $p); if ($name === null) return null;
	$uri  = $str($d, $p); if ($uri  === null) return null;
	$minted = (strlen($d) >= $p + 4) ? unpack('V', substr($d, $p, 4))[1] : 0; $p += 4;
	$size   = (strlen($d) >= $p + 4) ? unpack('V', substr($d, $p, 4))[1] : 0;
	return array('name' => trim($name), 'uri' => trim($uri),
	             'minted' => $minted, 'size' => $size);
}

function probe_print_insert($address, $name, $size) {
	echo "\n  to register it:\n";
	printf("    INSERT INTO collections (blockchain_id, project_id, name, policy, rate)\n");
	printf("    VALUES (%d, <project_id>, '%s', '%s', <rate>);\n\n",
		SOLANA_CHAIN_ID, addslashes($name !== '' ? $name : '<name>'), addslashes($address));
	echo "  project_id is the ARTIST'S EXISTING project, so their Solana, XRPL and\n";
	echo "  Cardano collections sit under one artist. See multichain.md §3d.\n";
	if ($size > 0) {
		printf("\n  MIND THE RATE. This collection is %s assets. Every one of them\n", number_format($size));
		echo "  earns at the rate you set here, every day, from the night it is\n";
		echo "  registered — so pick it against the reward pool, not against what\n";
		echo "  a comparable Cardano collection pays.\n";
	}
}

/*
 * WHICH KIND OF ADDRESS IS THIS?
 *
 * A wallet is NOT a special case of an account here. A wallet that has
 * never held SOL has no account at all -- getAccountInfo returns null --
 * and one that has holds a System Program account with no data. The first
 * version of this treated both as "no account" and "wrong program" and so
 * could never reach the wallet listing at all, which was found by pointing
 * it at the holder it was written for.
 *
 * So: missing or System-owned means "ask what this address HOLDS"; Core
 * means "this address IS a collection or an asset"; anything else is a
 * different standard and worth saying so plainly.
 */
if (!defined('SOLANA_SYSTEM_PROGRAM'))
	define('SOLANA_SYSTEM_PROGRAM', '11111111111111111111111111111111');

$acct  = probe_account($api, $arg);
$owner = ($acct !== null && isset($acct['owner'])) ? $acct['owner'] : '';
$data  = ($acct !== null && isset($acct['data'][0])) ? $acct['data'][0] : '';

if ($acct !== null && $owner !== SOLANA_CORE_PROGRAM && $owner !== SOLANA_SYSTEM_PROGRAM) {
	echo "That account belongs to program:\n    $owner\n\n";
	echo "This platform reads METAPLEX CORE only:\n    " . SOLANA_CORE_PROGRAM . "\n\n";
	echo "A Token Metadata NFT (the older standard, an SPL mint plus a metadata\n"
	   . "PDA) or a compressed NFT (which is not an account at all — it lives in a\n"
	   . "Merkle tree) cannot be read by verify-solana.php. Supporting either is a\n"
	   . "separate reader, not a setting.\n";
	exit(1);
}

/* A collection account: the direct answer. */
$c = ($owner === SOLANA_CORE_PROGRAM) ? probe_parse_collection($data) : null;
if ($c !== null) {
	echo "A Metaplex Core COLLECTION.\n\n";
	printf("  address : %s\n", $arg);
	printf("  name    : %s\n", $c['name'] !== '' ? $c['name'] : '(unnamed)');
	printf("  uri     : %s\n", $c['uri']);
	printf("  minted  : %s\n", number_format($c['minted']));
	printf("  current : %s   (minted minus burned)\n", number_format($c['size']));
	probe_print_insert($arg, $c['name'], $c['size']);
	exit(0);
}

/* An asset: read its collection off it, then describe that. */
$a = ($owner === SOLANA_CORE_PROGRAM) ? sol_parse_asset($data) : null;
if ($a !== null) {
	echo "A Metaplex Core ASSET.\n\n";
	printf("  name       : %s\n", $a['name']);
	printf("  held by    : %s\n", $a['owner']);
	printf("  uri        : %s\n", $a['uri']);
	if ($a['collection'] === '') {
		echo "\n  It is not in a collection — its update authority is an address, not a\n"
		   . "  collection account. There is nothing to register: a collections row\n"
		   . "  needs one identifier that every piece shares, and this piece has none.\n";
		exit(1);
	}
	printf("  collection : %s\n", $a['collection']);

	/* Say what the image will actually be, because that is the question that
	   bit this integration: an https image is now storable, but it was not
	   before and still is not on XRPL. */
	$urls = sol_metadata_urls($a['uri']);
	$img  = '';
	foreach ($urls as $u) {
		$body = sol_http($u, null);
		if ($body !== '') { $m = sol_parse_metadata($a, $body); $img = $m['image']; break; }
	}
	printf("  image      : %s\n", $img !== '' ? $img : '(not resolved — see the log for why it was dropped)');

	$ca = probe_account($api, $a['collection']);
	$cc = ($ca !== null && isset($ca['data'][0])) ? probe_parse_collection($ca['data'][0]) : null;
	if ($cc !== null) {
		echo "\n  the collection it belongs to:\n";
		printf("    name    : %s\n", $cc['name'] !== '' ? $cc['name'] : '(unnamed)');
		printf("    current : %s assets\n", number_format($cc['size']));
		probe_print_insert($a['collection'], $cc['name'], $cc['size']);
	} else {
		probe_print_insert($a['collection'], '', 0);
	}
	exit(0);
}

/* A wallet, then: list what it holds, grouped by collection. This is also the "what would this staker
   get?" question, answered before anybody links anything. */
$got = sol_account_assets($api, $arg, 'sol_http');
if (!$got['ok']) {
	echo "Could not read that wallet's Core assets. The cluster may be rate-limiting;\n"
	   . "try again, or pass your own RPC url as the second argument.\n";
	exit(1);
}
if (!$got['list']) {
	/*
	 * NO ACCOUNT *AND* NOTHING HELD IS THE SIGNATURE OF A MISTYPED ADDRESS,
	 * and it has to be called out, because the honest answer ("holds no Core
	 * assets") is true of a wrong address and tells you nothing.
	 *
	 * SOLANA ADDRESSES CARRY NO CHECKSUM. Cardano's bech32 and XRPL's base58
	 * both embed one, so a dropped character is rejected outright. Here it
	 * is not: dropping the last character of the OMEN collection leaves 43
	 * base58 characters that still decode to exactly 32 bytes -- a perfectly
	 * valid address for an account that does not exist. That is a real
	 * truncated paste, not a hypothetical, and it produced this exact
	 * message.
	 *
	 * "No account" on its own is NOT the signal: a wallet that has never
	 * held SOL has no account either, and can still hold Core assets. It is
	 * the combination that means nobody is home.
	 */
	if ($acct === null) {
		printf("Nothing at that address: no account, and no Metaplex Core assets.\n\n");
		printf("  you gave  : %s  (%d characters)\n\n", $arg, strlen($arg));
		echo "CHECK THE ADDRESS FIRST. Solana addresses have no checksum, so a\n"
		   . "truncated or mistyped one is still a VALID address -- just somebody\n"
		   . "else's, or nobody's. A dropped character cannot be detected here, and\n"
		   . "a 43-character paste decodes to a perfectly good 32-byte key.\n";
		if (strlen($arg) < 44)
			echo "\nYours is shorter than the usual 44 characters, which is what a\n"
			   . "truncated copy looks like. Re-copy the whole thing.\n";
		echo "\nIf the address IS right, then it is an empty or unfunded wallet.\n";
		exit(1);
	}
	echo "That wallet holds no Metaplex Core assets.\n\n"
	   . "If they can see NFTs in their wallet app, those are on a different\n"
	   . "standard — Token Metadata or compressed — which this platform does not\n"
	   . "read. verify-solana-doctor.php says which.\n";
	exit(0);
}

$by = array();
foreach ($got['list'] as $a) {
	if (!isset($by[$a['collection']])) $by[$a['collection']] = array();
	$by[$a['collection']][] = $a;
}
printf("That wallet holds %d Metaplex Core asset(s) across %d collection(s).\n\n",
	count($got['list']), count($by));
foreach ($by as $coll => $list) {
	$ca = probe_account($api, $coll);
	$cc = ($ca !== null && isset($ca['data'][0])) ? probe_parse_collection($ca['data'][0]) : null;
	printf("  %s  (%d held)\n", $cc ? $cc['name'] : '(unnamed)', count($list));
	printf("    %s\n", $coll);
	foreach (array_slice($list, 0, 5) as $a) printf("      %s\n", $a['name']);
	if (count($list) > 5) printf("      ... and %d more\n", count($list) - 5);
	probe_print_insert($coll, $cc ? $cc['name'] : '', $cc ? $cc['size'] : 0);
	echo "\n";
}
