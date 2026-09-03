<?php
// crypties-s1-rarity-report.php — one-off report: pull on-chain metadata for
// the Crypties Season 1 NFTs Crypt Conquest actually draws its PLAYER card
// art from, so rank/suit assignment can be curated by real rarity instead of
// the current auto-assignment (see cryptconquestGetCardArtPools()'s own
// comment in db.php: "NOT the hand-curated-by-rarity pass Crypt Crawl's own
// CRYPTCRAWL_CARD_ART got ... just an auto-assignment from current
// holdings").
//
// SCOPE, deliberately narrow -- exactly what was asked for:
//   - Only the two wallets Conquest's player-card pool already draws from:
//     CRYPTCRAWL_ART_USER_ID (the owner's own wallet, "my collection") and
//     CRYPTCONQUEST_S1_EXTRA_USER_ID ("Dean's collection", the backup) --
//     see cryptconquestGetS1ArtPool() in db.php.
//   - Only collection_id = CRYPTCONQUEST_S1_COLLECTION_ID (Crypties Season 1).
//   - NOT the enemy/court pool -- that one is platform-wide (any public
//     staker, see cryptconquestFetchCourtCandidates()) and explicitly out of
//     scope per the request ("not worrying about the enemy art from
//     stakers").
//
// WHERE THE RARITY DATA ACTUALLY COMES FROM
// Nothing in this platform's own DB stores Crypties trait/rarity data --
// confirmed: no metadata/attributes/traits column on `nfts`, and Crypt
// Crawl's own WTF/Mythic/Legendary tiers were assigned by the owner
// eyeballing pieces directly (db.php's CRYPTCRAWL_CARD_ART comment: "per
// owner's own ID"), not from any stored field. So this pulls the REAL
// on-chain CIP-25 metadata straight from Koios's asset_info endpoint --
// same API, same bearer token, same batching-by-35 pattern verify.php
// already uses nightly for platform-wide NFT verification (see
// verifyAssetInWallet() and the asset_info call in verify.php) -- and dumps
// whatever attribute/trait fields the mint actually embedded, since this
// script doesn't know Crypties' own metadata schema in advance.
//
// OUTPUT
// Writes crypties-s1-rarity-report.json (full data: every asset, every
// on-chain metadata field found) and prints a compact console table
// (owner, name, image URL, and a best-guess "rarity-looking" field if one
// turns up under a key containing rank/rarity/tier -- purely a convenience
// scan, not a claim about what Crypties' own rarity system actually is).
// The JSON is the real deliverable; skim the console table, then open the
// JSON (or turn it into an Artifact) to actually pick rank/suit assignments
// by eye, the same way Crypt Crawl's own art got curated.
//
// USAGE -- CLI ONLY (see the SAPI guard below; over HTTP this 404s, same
// convention as cache-crypties-art.php and for the same reason: this makes
// outbound API calls and shouldn't be a free, unauthenticated trigger for
// anyone who finds the URL):
//   php crypties-s1-rarity-report.php
//
// SAFE TO RE-RUN. Read-only against the DB and against Koios; writes only
// the one JSON report file, overwritten each run.
include_once 'db.php';

if (php_sapi_name() !== 'cli') {
	http_response_code(404);
	exit;
}

function report_out($line) {
	echo $line . "\n";
}

$user_ids = [
	intval(CRYPTCRAWL_ART_USER_ID) => 'primary (my collection)',
	intval(CRYPTCONQUEST_S1_EXTRA_USER_ID) => 'backup (Dean\'s collection)',
];
$s1_collection_id = intval(CRYPTCONQUEST_S1_COLLECTION_ID);

report_out('Crypties S1 rarity report');
report_out('  collection_id = ' . $s1_collection_id);
foreach ($user_ids as $uid => $label) report_out('  user_id ' . $uid . ' = ' . $label);
report_out('');

// Step 1: pull every S1 NFT these two wallets actually hold, straight from
// the platform DB -- this is the exact same set cryptconquestGetS1ArtPool()
// draws the player-card pool from (primary wallet's rows first, then the
// backup's), just with the columns needed to ask Koios for on-chain
// metadata (policy + asset_name) instead of just an image URL.
// INNER JOIN collections for project_id (needed so getIPFS() below can
// check the local image cache before falling back to the slow public IPFS
// gateway) AND for policy -- that lives on `collections`, not `nfts` (first
// version of this script guessed nfts.policy from a misread of
// getMonstrocityAssets()'s own unqualified column; a real run against
// production caught it: "Unknown column 'nfts.policy'").
$ids_sql = implode(',', array_keys($user_ids));
$result = $conn->query("
	SELECT nfts.id, nfts.name, nfts.asset_name, collections.policy, nfts.ipfs, nfts.user_id, collections.project_id
	FROM nfts
	INNER JOIN collections ON collections.id = nfts.collection_id
	WHERE nfts.collection_id = $s1_collection_id
	  AND nfts.user_id IN ($ids_sql)
	ORDER BY FIELD(nfts.user_id, " . implode(',', array_keys($user_ids)) . "), nfts.name ASC
");
if (!$result) {
	report_out('Query failed: ' . $conn->error);
	exit;
}

$rows = [];
while ($row = $result->fetch_assoc()) $rows[] = $row;
report_out('Found ' . count($rows) . ' S1 NFT(s) across both wallets.');
if (!$rows) { $conn->close(); exit; }

// Step 2: batch-fetch on-chain metadata from Koios, 35 assets per request --
// same limit and retry-on-transient-failure approach verify.php already
// relies on nightly (Koios' PostgREST backend intermittently 504s).
$koios_bearer = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXhybHB1d2R4MjN4bGRhM3hkOG40NnR3cW0zano5Y3hkNGYyazJoaDhzNGUwMGN3ZmFnNHUiLCJleHAiOjE3OTc5NjAyODEsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.JWfVIQGU6SH0p7BpyzqV931Em8nz_eKkVbheIGzLShg';

function koios_asset_info($asset_list) {
	global $koios_bearer;
	$payload = ['_asset_list' => $asset_list];
	$max_attempts = 4;
	for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
		$ch = curl_init('https://api.koios.rest/api/v1/asset_info');
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json', 'authorization: Bearer ' . $koios_bearer]);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		$response = curl_exec($ch);
		$http_code = ($response === false) ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);
		$decoded = ($response === false) ? null : json_decode($response, true);
		if ($response !== false && $http_code >= 200 && $http_code < 300 && is_array($decoded)) {
			return $decoded;
		}
		if ($attempt < $max_attempts) sleep($attempt * 3);
	}
	return [];
}

$asset_list = [];
foreach ($rows as $row) {
	$asset_list[] = [$row['policy'], bin2hex($row['asset_name'])];
}
$batches = array_chunk($asset_list, 35);
$by_fingerprint = [];
foreach ($batches as $i => $batch) {
	report_out('Fetching metadata batch ' . ($i + 1) . '/' . count($batches) . ' (' . count($batch) . ' assets)...');
	$results = koios_asset_info($batch);
	foreach ($results as $entry) {
		$fp = $entry['fingerprint'] ?? null;
		if ($fp) $by_fingerprint[$fp] = $entry;
	}
}
report_out('Metadata received for ' . count($by_fingerprint) . '/' . count($rows) . ' asset(s).');
report_out('');

// Step 3: match Koios results back to DB rows by fingerprint isn't directly
// possible (the DB doesn't store fingerprint) -- Koios returns policy_id +
// asset_name on each result too, so match on that pair instead, hex-encoded
// the same way the request built it.
$by_policy_asset = [];
foreach ($by_fingerprint as $fp => $entry) {
	$key = ($entry['policy_id'] ?? '') . ':' . ($entry['asset_name'] ?? '');
	$by_policy_asset[$key] = $entry;
}

// Best-effort convenience scan, not an assumption about Crypties' real
// rarity system (unknown until this output is actually seen) -- recurses
// through the WHOLE metadata tree (traits are as likely to be a nested list
// as a flat key) checking both KEYS (rarity/rank/tier/rare) and, since this
// exact codebase already has a known tier vocabulary on record (Crypt
// Crawl's own CRYPTCRAWL_CARD_ART comments: WTF/Mythic/Legendary), VALUES
// against that same vocabulary too.
$rarity_like_keys = ['rarity', 'rank', 'tier', 'rare'];
$rarity_like_values = ['wtf', 'mythic', 'legendary', 'epic', 'rare', 'common', 'uncommon'];
function find_rarity_signal($node, $path = '') {
	global $rarity_like_keys, $rarity_like_values;
	if (is_array($node)) {
		foreach ($node as $k => $v) {
			$here = $path . ($path !== '' ? '.' : '') . $k;
			if (is_string($k)) {
				foreach ($rarity_like_keys as $needle) {
					if (stripos($k, $needle) !== false) {
						return $here . '=' . (is_scalar($v) ? $v : json_encode($v));
					}
				}
			}
			$found = find_rarity_signal($v, is_int($k) ? $path : $here);
			if ($found) return $found;
		}
		return null;
	}
	if (is_string($node)) {
		foreach ($rarity_like_values as $needle) {
			if (stripos($node, $needle) !== false) return $path . '~="' . $node . '"';
		}
	}
	return null;
}

$report = [];
foreach ($rows as $row) {
	$key = $row['policy'] . ':' . bin2hex($row['asset_name']);
	$entry = $by_policy_asset[$key] ?? null;
	// CIP-25 metadata lives at minting_tx_metadata["721"][policy_id][asset_name_ascii]
	// -- NOT a top-level "onchain_metadata" key. Confirmed against a live Koios
	// v1 asset_info call (a well-known non-Crypties asset, just to see the real
	// response shape) before writing this: the API has moved on from whatever
	// shape verify.php's own onchain_metadata read was written against, so that
	// existing code may have the same stale-field problem -- worth a look
	// separately, not fixed here (out of scope for this one-off report).
	$metadata = null;
	if ($entry) {
		$policy_id = $entry['policy_id'] ?? '';
		$asset_name_ascii = $entry['asset_name_ascii'] ?? '';
		$metadata = $entry['minting_tx_metadata']['721'][$policy_id][$asset_name_ascii] ?? null;
	}
	$rarity_guess = is_array($metadata) ? find_rarity_signal($metadata) : null;
	$report[] = [
		'nft_id'        => intval($row['id']),
		'owner_user_id' => intval($row['user_id']),
		'owner_label'   => $user_ids[intval($row['user_id'])] ?? 'unknown',
		'name'          => $row['name'],
		'image_url'     => getIPFS($row['ipfs'], $s1_collection_id, intval($row['project_id'])),
		'fingerprint'   => $entry['fingerprint'] ?? null,
		'rarity_guess'  => $rarity_guess,
		'onchain_metadata' => $metadata,
	];
}

$json_path = __DIR__ . '/crypties-s1-rarity-report.json';
file_put_contents($json_path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
report_out('Wrote ' . $json_path . ' (' . count($report) . ' entries).');
report_out('');

report_out(str_pad('OWNER', 28) . str_pad('NAME', 22) . 'RARITY-LOOKING FIELD');
report_out(str_repeat('-', 90));
foreach ($report as $r) {
	report_out(
		str_pad(substr($r['owner_label'], 0, 26), 28) .
		str_pad(substr($r['name'] ?? '(no name)', 0, 20), 22) .
		($r['rarity_guess'] ?? ($r['onchain_metadata'] === null ? '(no metadata returned)' : '(no obvious rarity field)'))
	);
}

$conn->close();
