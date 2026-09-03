<?php
// crypties-s1-rarity-report.php — one-off report: pull on-chain metadata for
// the Crypties Season 1 NFTs Crypt Conquest actually draws its PLAYER card
// art from, so rank/suit assignment can be curated by real rarity/traits
// instead of the current auto-assignment (see cryptconquestGetCardArtPools()'s
// own comment in db.php: "NOT the hand-curated-by-rarity pass Crypt Crawl's
// own CRYPTCRAWL_CARD_ART got ... just an auto-assignment from current
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
// TWO DATA SOURCES, not just the local DB -- added after a fair question:
// the platform's own `nfts` table only has whatever's been SYNCED (staked +
// verified at some point), which could genuinely undercount a wallet,
// especially the backup one. So this now ALSO queries Koios directly for
// every stake address on file for each user, filtered to the S1 policy, and
// reports anything found on-chain that ISN'T already a row in `nfts` --
// still fetched for metadata either way, just flagged as unsynced.
//
// WHERE THE RARITY/TRAIT DATA ACTUALLY COMES FROM
// Nothing in this platform's own DB stores Crypties trait/rarity data --
// confirmed: no metadata/attributes/traits column on `nfts`. Crypt Crawl's
// own WTF/Mythic/Legendary tiers were assigned by the owner eyeballing
// pieces directly (db.php's CRYPTCRAWL_CARD_ART comment: "per owner's own
// ID"), not from any stored field. So this pulls the REAL on-chain CIP-25
// metadata straight from Koios's asset_info endpoint -- same API, same
// bearer token, same batching-by-35 pattern verify.php already uses nightly
// for platform-wide NFT verification (see verifyAssetInWallet() and the
// asset_info call in verify.php).
//
// CIP-25 metadata lives at minting_tx_metadata["721"][policy_id][asset_name]
// -- NOT a top-level "onchain_metadata" field the way verify.php's own code
// reads it. Confirmed against a live Koios v1 call (a well-known
// non-Crypties asset, just to see the real response shape) before trusting
// it -- the API has moved on from whatever shape verify.php was written
// against, so that existing code likely has the same stale-field problem.
// Not fixed here (out of scope for a one-off report), but worth a look.
//
// OUTPUT
//   crypties-s1-rarity-report.json   -- every asset: identity, image, full
//                                        on-chain metadata, sync status.
//   crypties-s1-trait-summary.json   -- every attribute KEY found across the
//                                        whole pool, with a count of every
//                                        VALUE seen under it (e.g. how many
//                                        pieces are "Element: Fire" vs
//                                        "Element: Water") -- this is the
//                                        actual data to look at for routing
//                                        suits by theme, not a guess.
// Console output is a compact preview of both -- skim it, then open the
// JSON (or turn either into an Artifact) to make the real rank/suit calls
// by eye, the same way Crypt Crawl's own art got curated.
//
// USAGE -- CLI ONLY (see the SAPI guard below; over HTTP this 404s, same
// convention as cache-crypties-art.php and for the same reason: this makes
// outbound API calls and shouldn't be a free, unauthenticated trigger for
// anyone who finds the URL):
//   php crypties-s1-rarity-report.php
//
// SAFE TO RE-RUN. Read-only against the DB and against Koios; writes only
// the two JSON report files, overwritten each run.
include_once 'db.php';

if (php_sapi_name() !== 'cli') {
	http_response_code(404);
	exit;
}

function report_out($line) {
	echo $line . "\n";
}

$koios_bearer = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXhybHB1d2R4MjN4bGRhM3hkOG40NnR3cW0zano5Y3hkNGYyazJoaDhzNGUwMGN3ZmFnNHUiLCJleHAiOjE3OTc5NjAyODEsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.JWfVIQGU6SH0p7BpyzqV931Em8nz_eKkVbheIGzLShg';

// Generic Koios POST helper -- same retry-on-transient-failure approach
// verify.php already relies on nightly (Koios' PostgREST backend
// intermittently 504s). Used for both account_assets (wallet scan) and
// asset_info (metadata fetch) below.
function koios_post($endpoint, $payload) {
	global $koios_bearer;
	$max_attempts = 4;
	for ($attempt = 1; $attempt <= $max_attempts; $attempt++) {
		$ch = curl_init('https://api.koios.rest/api/v1/' . $endpoint);
		curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-type: application/json', 'authorization: Bearer ' . $koios_bearer]);
		curl_setopt($ch, CURLOPT_POST, 1);
		curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		$response = curl_exec($ch);
		$http_code = ($response === false) ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$decoded = ($response === false) ? null : json_decode($response, true);
		if ($response !== false && $http_code >= 200 && $http_code < 300 && is_array($decoded)) {
			return $decoded;
		}
		if ($attempt < $max_attempts) sleep($attempt * 3);
	}
	return [];
}

$user_ids = [
	intval(CRYPTCRAWL_ART_USER_ID) => 'primary (my collection)',
	intval(CRYPTCONQUEST_S1_EXTRA_USER_ID) => 'backup (Dean\'s collection)',
];
$s1_collection_id = intval(CRYPTCONQUEST_S1_COLLECTION_ID);

report_out('Crypties S1 rarity/trait report');
report_out('  collection_id = ' . $s1_collection_id);
foreach ($user_ids as $uid => $label) report_out('  user_id ' . $uid . ' = ' . $label);
report_out('');

// S1's own policy_id -- needed up front, both for the wallet scan below and
// to build the request list for the DB-known rows further down.
$pol_res = $conn->query("SELECT policy FROM collections WHERE id = $s1_collection_id LIMIT 1");
$s1_policy = ($pol_res && $pol_res->num_rows) ? $pol_res->fetch_assoc()['policy'] : null;
if (!$s1_policy) {
	report_out('Could not resolve a policy_id for collection_id ' . $s1_collection_id . ' -- aborting.');
	$conn->close();
	exit;
}
report_out('S1 policy_id = ' . $s1_policy);
report_out('');

// Step 1: every S1 NFT these two wallets have SYNCED into the platform DB --
// the exact same set cryptconquestGetS1ArtPool() draws the player-card pool
// from today.
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
	$conn->close();
	exit;
}
$db_rows = [];
$db_keys = []; // policy:asset_name_hex -> true, for the on-chain cross-check below
while ($row = $result->fetch_assoc()) {
	$db_rows[] = $row;
	$db_keys[$row['policy'] . ':' . bin2hex($row['asset_name'])] = true;
}
report_out('Synced in the platform DB: ' . count($db_rows) . ' NFT(s).');

// Step 2: the FULL on-chain picture for each user -- every stake address
// they have on file (not just their "main" one), every S1-policy asset in
// each, via Koios account_assets. Answers "is the DB actually complete?"
// directly instead of assuming it.
$wallet_only = []; // policy:asset_name_hex -> ['user_id'=>.., 'asset_name_hex'=>.., 'fingerprint'=>..]
foreach ($user_ids as $uid => $label) {
	$addr_res = $conn->query("SELECT DISTINCT stake_address FROM wallets WHERE user_id = $uid AND stake_address != ''");
	$stakes = [];
	if ($addr_res) { while ($r = $addr_res->fetch_assoc()) $stakes[] = $r['stake_address']; }
	if (!$stakes) {
		report_out('  ' . $label . ': no stake address on file, skipping on-chain scan.');
		continue;
	}
	$assets = koios_post('account_assets', ['_stake_addresses' => $stakes]);
	$s1_owned = array_values(array_filter($assets, function ($a) use ($s1_policy) {
		return ($a['policy_id'] ?? '') === $s1_policy;
	}));
	report_out('  ' . $label . ': ' . count($stakes) . ' wallet(s) on file, ' . count($s1_owned) . ' S1 asset(s) held on-chain.');
	foreach ($s1_owned as $a) {
		$key = $a['policy_id'] . ':' . $a['asset_name'];
		if (isset($db_keys[$key])) continue; // already covered by Step 1
		if (isset($wallet_only[$key])) continue; // seen from another of this user's wallets
		$wallet_only[$key] = ['user_id' => $uid, 'asset_name_hex' => $a['asset_name'], 'fingerprint' => $a['fingerprint'] ?? null];
	}
}
if ($wallet_only) {
	report_out('');
	report_out('FOUND ' . count($wallet_only) . ' S1 asset(s) held on-chain but NOT synced into the platform DB:');
	foreach ($wallet_only as $key => $w) {
		report_out('  ' . $user_ids[$w['user_id']] . ': asset_name(hex)=' . $w['asset_name_hex'] . ' fingerprint=' . $w['fingerprint']);
	}
} else {
	report_out('No on-chain S1 assets found outside what the DB already has -- the DB set is complete.');
}
report_out('');

if (!$db_rows && !$wallet_only) {
	report_out('Nothing to report.');
	$conn->close();
	exit;
}

// Step 3: batch-fetch on-chain metadata (both DB-known rows AND
// DB-only holdings, so unsynced pieces still get a real trait/rarity read)
// -- 35 assets per request, same limit verify.php already relies on.
$asset_list = [];
foreach ($db_rows as $row) $asset_list[] = [$row['policy'], bin2hex($row['asset_name'])];
foreach ($wallet_only as $w) $asset_list[] = [$s1_policy, $w['asset_name_hex']];

$batches = array_chunk($asset_list, 35);
$by_policy_asset = [];
foreach ($batches as $i => $batch) {
	report_out('Fetching metadata batch ' . ($i + 1) . '/' . count($batches) . ' (' . count($batch) . ' assets)...');
	// Bug fixed here: asset_info needs its batch wrapped as {"_asset_list":
	// [...]}, same as account_assets wraps its own payload above -- the
	// previous run posted the bare array, Koios 400'd ("All object keys
	// must match"), which decodes to an array too so it wasn't an obvious
	// crash, just four retries per batch before giving up empty. Confirmed
	// live against a real (non-Crypties) asset before shipping this fix.
	foreach (koios_post('asset_info', ['_asset_list' => $batch]) as $entry) {
		$key = ($entry['policy_id'] ?? '') . ':' . ($entry['asset_name'] ?? '');
		$by_policy_asset[$key] = $entry;
	}
}
report_out('Metadata received for ' . count($by_policy_asset) . '/' . count($asset_list) . ' asset(s).');
report_out('');

// Best-effort rarity-guess scan, kept as a quick console pointer -- not an
// assumption about Crypties' real rarity system, and not the primary output
// anymore (see the trait summary below, which is the real answer to "what
// options do we actually have").
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

// Flattens metadata into dotted-path => scalar-value pairs, skipping pure
// identity fields (name/image/arweaveId/mediaType and the like) that would
// otherwise dominate a frequency count with 92 unique values each. This is
// what actually answers "what traits exist and how are they distributed" --
// the real input for routing by suit, not a single guessed field.
function flatten_traits($node, $path, &$out) {
	static $skip_keys = ['name', 'image', 'mediatype', 'arweaveid', 'files', 'description'];
	if (is_array($node)) {
		$is_list = array_keys($node) === range(0, count($node) - 1);
		foreach ($node as $k => $v) {
			if (is_string($k) && in_array(strtolower($k), $skip_keys, true)) continue;
			$here = $is_list ? $path : ($path . ($path !== '' ? '.' : '') . $k);
			flatten_traits($v, $here, $out);
		}
		return;
	}
	if (is_scalar($node) && $path !== '') $out[$path][] = (string)$node;
}

$report = [];
$trait_values = []; // path => [value => count]
foreach (array_merge(
	array_map(function ($r) { return ['db' => $r, 'wallet_only' => null]; }, $db_rows),
	array_map(function ($w) use ($s1_policy) { return ['db' => null, 'wallet_only' => $w]; }, array_values($wallet_only))
) as $item) {
	$row = $item['db'];
	$w = $item['wallet_only'];
	$policy = $row ? $row['policy'] : $s1_policy;
	$asset_name_hex = $row ? bin2hex($row['asset_name']) : $w['asset_name_hex'];
	$key = $policy . ':' . $asset_name_hex;
	$entry = $by_policy_asset[$key] ?? null;
	$metadata = null;
	if ($entry) {
		$policy_id = $entry['policy_id'] ?? '';
		$asset_name_ascii = $entry['asset_name_ascii'] ?? '';
		$metadata = $entry['minting_tx_metadata']['721'][$policy_id][$asset_name_ascii] ?? null;
	}
	$rarity_guess = is_array($metadata) ? find_rarity_signal($metadata) : null;
	if (is_array($metadata)) {
		$flat = [];
		flatten_traits($metadata, '', $flat);
		foreach ($flat as $path => $values) {
			foreach ($values as $v) $trait_values[$path][$v] = ($trait_values[$path][$v] ?? 0) + 1;
		}
	}
	$report[] = [
		'nft_id'          => $row ? intval($row['id']) : null,
		'owner_user_id'   => $row ? intval($row['user_id']) : $w['user_id'],
		'owner_label'     => $user_ids[$row ? intval($row['user_id']) : $w['user_id']] ?? 'unknown',
		'synced_in_db'    => $row !== null,
		'name'            => $row ? $row['name'] : ($entry['asset_name_ascii'] ?? null),
		'image_url'       => $row ? getIPFS($row['ipfs'], $s1_collection_id, intval($row['project_id'])) : (is_array($metadata) ? ($metadata['image'] ?? null) : null),
		'fingerprint'     => $entry['fingerprint'] ?? ($w['fingerprint'] ?? null),
		'rarity_guess'    => $rarity_guess,
		'onchain_metadata'=> $metadata,
	];
}

$json_path = __DIR__ . '/crypties-s1-rarity-report.json';
file_put_contents($json_path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
report_out('Wrote ' . $json_path . ' (' . count($report) . ' entries).');

// Trait summary -- sorted by path, values sorted by frequency descending, so
// the most common groupings (the ones actually useful for a 4-way suit
// split) are easy to scan first.
ksort($trait_values);
$summary = [];
foreach ($trait_values as $path => $values) {
	arsort($values);
	$summary[$path] = $values;
}
$summary_path = __DIR__ . '/crypties-s1-trait-summary.json';
file_put_contents($summary_path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
report_out('Wrote ' . $summary_path . ' (' . count($summary) . ' trait path(s)).');
report_out('');

report_out('TRAIT SUMMARY (path: value=count, ...):');
foreach ($summary as $path => $values) {
	$pairs = [];
	foreach ($values as $v => $c) $pairs[] = "$v=$c";
	report_out('  ' . $path . ': ' . implode(', ', array_slice($pairs, 0, 12)) . (count($pairs) > 12 ? ' ... (+' . (count($pairs) - 12) . ' more)' : ''));
}
report_out('');

report_out(str_pad('OWNER', 28) . str_pad('SYNC', 6) . str_pad('NAME', 22) . 'RARITY-LOOKING FIELD');
report_out(str_repeat('-', 100));
foreach ($report as $r) {
	report_out(
		str_pad(substr($r['owner_label'], 0, 26), 28) .
		str_pad($r['synced_in_db'] ? 'yes' : 'NO', 6) .
		str_pad(substr($r['name'] ?? '(no name)', 0, 20), 22) .
		($r['rarity_guess'] ?? ($r['onchain_metadata'] === null ? '(no metadata returned)' : '(no obvious rarity field)'))
	);
}

$conn->close();
