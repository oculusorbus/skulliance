<?php
/* verify-xrpl-probe.php — CLI only. Point it at an XRPL account and it prints
   what the verifier would see.
 *
 * Adding an XRPL collection needs its ISSUER and TAXON, and both are usually
 * readable off a marketplace -- Bithomp's NFT explorer filters on exactly those
 * two fields -- so this is not the only way to get them.
 *
 * It is the RELIABLE way, and the difference matters. A marketplace shows how
 * IT grouped a drop, and the taxon is a number the minter picks freely: plenty
 * use 0 for everything, so one issuer's several drops can share a taxon, or a
 * marketplace collection can span more than one. The verifier matches on what
 * the LEDGER says about the token in hand, so this asks the ledger the same
 * question the nightly pass will ask and prints the answer that will actually
 * match. Hold one in a wallet and run:
 *
 *     php verify-xrpl-probe.php rYourAccountAddressHere
 *
 * It reads the live ledger through the same code path the nightly pass uses --
 * same paging, same parsing, same metadata resolution -- and prints the
 * collections it found plus the INSERT that would register them.
 *
 * It WRITES NOTHING. No database, no session. Safe to run against anything.
 *
 * Optional second argument overrides the node:
 *     php verify-xrpl-probe.php rAccount https://s1.ripple.com:51234
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require __DIR__ . '/verify-xrpl.php';

$arg = isset($argv[1]) ? trim($argv[1]) : '';
$api = isset($argv[2]) ? trim($argv[2]) : 'https://xrplcluster.com';

if ($arg === '') {
	echo "usage: php verify-xrpl-probe.php <r-address | NFTokenID> [node-url]\n\n";
	echo "  r-address   lists every collection that wallet holds\n";
	echo "  NFTokenID   decodes ONE NFT to its collection -- no wallet needed,\n";
	echo "              nothing has to be delivered, works from any NFT's id\n";
	exit(1);
}

/*
 * AN NFTokenID IS ENOUGH, and this is the path to use in practice.
 *
 * Artists mint through xrp.cafe and think in COLLECTIONS. They do not know
 * what a taxon is and should not need to -- the minting tool assigns one per
 * collection and they never see it. So the only thing to ask an artist for is
 * a link to any NFT in the collection; its id carries the issuer and taxon,
 * and nothing has to be sent anywhere.
 */
if (preg_match('/^[0-9A-Fa-f]{64}$/', $arg)) {
	$d = xrpl_decode_nftoken_id($arg);
	if (!$d) { echo "That is 64 hex characters but did not decode as an NFTokenID.\n"; exit(1); }
	printf("NFTokenID %s\n\n", strtoupper($arg));
	printf("  issuer %s\n  taxon  %d\n  serial %d\n\n", $d['issuer'], $d['taxon'], $d['serial']);
	printf("  collection key: %s\n\n", $d['policy']);

	/* Decoding is offline, but the collection NAME lives in the metadata, so
	   fetch it -- typing a name by hand is how a collection ends up listed as
	   something the artist does not call it. */
	$probe = array('id' => strtoupper($arg), 'uri' => '', 'serial' => $d['serial'],
	               'policy' => $d['policy'], 'issuer' => $d['issuer'], 'taxon' => $d['taxon']);
	$one = xrpl_account_nfts($api, $d['issuer'], 'xrpl_http');
	$name = '';
	if ($one['ok']) {
		foreach ($one['list'] as $x) {
			if ($x['policy'] !== $d['policy']) continue;
			$m = xrpl_resolve_metadata($x, 'https://gateway.pinata.cloud/ipfs/', 'xrpl_http');
			if ($m['collection'] !== '') { $name = $m['collection']; break; }
		}
	}
	if ($name !== '') printf("  collection name: %s  (from the artist's own metadata)\n\n", $name);
	else              echo "  collection name: not resolved — the issuer holds none of this\n"
	                     . "                   collection, so ask the artist what to call it\n\n";

	echo "  to register it:\n";
	printf("    INSERT INTO collections (blockchain_id, project_id, name, policy, rate)\n");
	printf("    VALUES (%d, <project_id>, '%s', '%s', <rate>);\n\n",
		XRPL_CHAIN_ID, $name !== '' ? addslashes($name) : '<name>', addslashes($d['policy']));
	echo "  project_id is the ARTIST'S EXISTING project, so their XRPL and Cardano\n";
	echo "  collections sit under one artist. See multichain.md §3d.\n";
	exit(0);
}

$account = $arg;
if (!preg_match('/^r[rpshnaf39wBUDNEGHJKLM4PQRST7VWXYZ2bcdeCg65jkm8oFqi1tuvAxyz]{24,34}$/', $account)) {
	echo "That is neither an XRPL address (starts with r) nor a 64-hex NFTokenID.\n";
	exit(1);
}

echo "node:    $api\n";
echo "account: $account\n\n";

$got = xrpl_account_nfts($api, $account, 'xrpl_http');
if (!$got['ok']) {
	echo "FAILED to read the account. Either the node is unreachable or it\n";
	echo "returned an error. Try another node, e.g.\n";
	echo "  php verify-xrpl-probe.php $account https://s1.ripple.com:51234\n";
	exit(1);
}
if (!$got['list']) {
	echo "The account read fine and holds no NFTokens.\n";
	exit(0);
}

/* Group by collection, which is what a collections row actually is. */
$by = array();
foreach ($got['list'] as $n) {
	if (!isset($by[$n['policy']])) $by[$n['policy']] = array();
	$by[$n['policy']][] = $n;
}

/*
 * HELD IS NOT THE SAME AS CREATED, and conflating them is how you end up
 * registering another artist's collection under this artist's project.
 * account_nfts returns what an account HOLDS -- which for any active artist
 * is mostly other people's work that they bought. Only NFTs whose Issuer is
 * this account are theirs.
 */
$own = 0; $bought = 0;
foreach ($got['list'] as $n) { if ($n['issuer'] === $account) $own++; else $bought++; }
printf("%d NFToken(s) in %d collection(s) — %d self-issued, %d bought from others\n\n",
	count($got['list']), count($by), $own, $bought);

if ($bought) {
	echo "  Collections below are marked SELF-ISSUED or HELD. Only register the\n";
	echo "  self-issued ones under this artist's project.\n\n";
}

/*
 * AND THIS ONLY SEES WHAT IS STILL HELD. An artist who sold out a collection
 * holds none of it, so it does not appear here at all -- checked against two
 * real artists, one of whom had minted 3,569 and held 62. There is no way to
 * enumerate an issuer's collections from core rippled; that needs an indexer.
 *
 * Which is why the NFTokenID form of this command is the one to use for
 * onboarding: it works from any single NFT, sold out or not.
 */

foreach ($by as $policy => $nfts) {
	list($issuer, $taxon) = explode(':', $policy, 2);
	printf("── %s   %s\n", $policy, $issuer === $account ? '[SELF-ISSUED]' : '[held, not theirs]');
	printf("   issuer %s   taxon %s   holding %d\n", $issuer, $taxon, count($nfts));

	/* Resolve one, so the name and image can be eyeballed before a collection
	   is registered on the strength of them. This is the same resolver the
	   verifier uses, including its fallbacks. */
	$meta = xrpl_resolve_metadata($nfts[0], 'https://gateway.pinata.cloud/ipfs/', 'xrpl_http');
	printf("   sample %s\n", $nfts[0]['id']);
	printf("     name       %s\n", $meta['name'] !== '' ? $meta['name'] : '(none)');
	printf("     image      %s\n", $meta['image'] !== '' ? $meta['image'] : '(none)');
	if ($meta['collection'] !== '')
		printf("     collection %s   <- use this as the collections.name\n", $meta['collection']);
	if ($nfts[0]['uri'] === '')       echo "     note  this NFToken has no URI; names will be synthesised\n";
	elseif ($meta['image'] === '')    echo "     note  metadata resolved but carried no image\n";
	if (strpos($meta['name'], 'XRPL #') === 0)
		echo "     note  the name is a FALLBACK -- the metadata could not be read.\n"
		   . "           Staking still works, but check the IPFS gateway before launch.\n";

	echo "\n   to register this collection:\n";
	printf("     INSERT INTO collections (blockchain_id, project_id, name, policy, rate)\n");
	printf("     VALUES (%d, <project_id>, '%s', '%s', <rate>);\n\n",
		XRPL_CHAIN_ID,
		$meta['collection'] !== '' ? addslashes($meta['collection']) : '<name>',
		addslashes($policy));
}

echo "project_id is the ARTIST'S EXISTING project — an XRPL collection sits under\n";
echo "the same project as their Cardano ones, which is what keeps one artist from\n";
echo "becoming two. See multichain.md §3d.\n";
echo "\nNOTE: this lists only what the wallet still HOLDS. A sold-out collection\n";
echo "does not appear. To register one regardless, use any NFT's id:\n";
echo "  php verify-xrpl-probe.php <NFTokenID>\n";
