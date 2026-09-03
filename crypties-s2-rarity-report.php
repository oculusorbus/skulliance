<?php
// crypties-s2-rarity-report.php — one-off report: pull on-chain metadata for
// the Crypties Season 2 NFTs Crypt Crawl's own CRYPTCRAWL_CARD_ART draws its
// 44-card pool from, so rank/suit assignment can be curated by real rarity
// (and, per the owner, an "animal head" trait aligned to suits) instead of
// the current hand-picked pass -- see CRYPTCRAWL_CARD_ART's own comment in
// db.php: its WTF/Mythic/Legendary tiers were assigned by the owner
// eyeballing pieces directly ("per owner's own ID"), not from any verified
// on-chain field. Same exercise as crypties-s1-rarity-report.php did for
// Crypt Conquest's S1 pool -- this is that script's structure, adapted.
//
// SCOPE
//   - Only CRYPTCRAWL_ART_USER_ID's wallet -- Crawl's card art has always
//     been single-wallet (see cryptcrawlGetCardArt() in db.php), unlike
//     Conquest's S1 pool which also draws from a backup wallet. No backup
//     wallet added here unless the owner asks for one once they see how
//     much S2 supply this one wallet actually has.
//   - Only the Crypties collection that ISN'T S1 -- same "any Crypties
//     collection with collections.id != CRYPTCONQUEST_S1_COLLECTION_ID"
//     trick cryptconquestGetS2ArtPool() already uses in db.php, rather than
//     hardcoding a second collection_id constant that doesn't exist yet.
//
// WHERE THE DATA COMES FROM (same as the S1 script)
// Nothing in this platform's own DB stores Crypties trait/rarity data.
// Pulled from real on-chain CIP-25 metadata via Koios's asset_info
// endpoint, batched by 35 -- same API/bearer/retry pattern verify.php
// already uses nightly. Also scans the wallet's FULL on-chain holdings
// (not just what's synced into the platform's own `nfts` table) via Koios
// account_assets, same reasoning as the S1 script: the local DB can
// undercount a wallet that isn't fully synced.
//
// OUTPUT
//   crypties-s2-rarity-report.json   -- every asset: identity, image, full
//                                        on-chain metadata, sync status.
//   crypties-s2-trait-summary.json   -- every attribute KEY found across the
//                                        whole pool, with a count of every
//                                        VALUE seen under it -- this is the
//                                        real data to look at for whatever
//                                        the "animal head" field turns out
//                                        to actually be called.
// Console output is a compact preview of both; the JSON is the real
// deliverable.
//
// USAGE -- CLI ONLY (same 404-over-HTTP guard as crypties-s1-rarity-
// report.php and cache-crypties-art.php, for the same reason: this makes
// outbound API calls and shouldn't be a free, unauthenticated trigger for
// anyone who finds the URL):
//   php crypties-s2-rarity-report.php
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

$user_id = intval(CRYPTCRAWL_ART_USER_ID);
$s1_collection_id = intval(CRYPTCONQUEST_S1_COLLECTION_ID);

report_out('Crypties S2 rarity/trait report');
report_out('  user_id = ' . $user_id . ' (Crawl\'s single art wallet)');
report_out('  collection: any Crypties collection with id != ' . $s1_collection_id);
report_out('');

// Resolve S2's actual collection_id + policy_id up front -- needed for the
// wallet scan below, and confirms there really is exactly one such
// collection before anything else runs.
$col_res = $conn->query("SELECT id, policy FROM collections WHERE name LIKE '%Crypties%' AND id != $s1_collection_id");
$s2_rows = [];
if ($col_res) { while ($r = $col_res->fetch_assoc()) $s2_rows[] = $r; }
if (count($s2_rows) !== 1) {
	report_out('Expected exactly one non-S1 Crypties collection, found ' . count($s2_rows) . ' -- aborting rather than guess which one is S2.');
	foreach ($s2_rows as $r) report_out('  id=' . $r['id'] . ' policy=' . $r['policy']);
	$conn->close();
	exit;
}
$s2_collection_id = intval($s2_rows[0]['id']);
$s2_policy = $s2_rows[0]['policy'];
report_out('S2 collection_id = ' . $s2_collection_id);
report_out('S2 policy_id = ' . $s2_policy);
report_out('');

$koios_bearer = 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXhybHB1d2R4MjN4bGRhM3hkOG40NnR3cW0zano5Y3hkNGYyazJoaDhzNGUwMGN3ZmFnNHUiLCJleHAiOjE3OTc5NjAyODEsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.JWfVIQGU6SH0p7BpyzqV931Em8nz_eKkVbheIGzLShg';

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

// Step 1: every S2 NFT synced into the platform DB for this wallet.
$result = $conn->query("
	SELECT nfts.id, nfts.name, nfts.asset_name, collections.policy, nfts.ipfs, nfts.user_id, collections.project_id
	FROM nfts
	INNER JOIN collections ON collections.id = nfts.collection_id
	WHERE nfts.collection_id = $s2_collection_id AND nfts.user_id = $user_id
	ORDER BY nfts.name ASC
");
if (!$result) {
	report_out('Query failed: ' . $conn->error);
	$conn->close();
	exit;
}
$db_rows = [];
$db_keys = [];
while ($row = $result->fetch_assoc()) {
	$db_rows[] = $row;
	$db_keys[$row['policy'] . ':' . bin2hex($row['asset_name'])] = true;
}
report_out('Synced in the platform DB: ' . count($db_rows) . ' NFT(s).');

// Step 2: the full on-chain picture for this wallet -- every stake address
// on file, every S2-policy asset in each, via Koios account_assets.
$wallet_only = [];
$addr_res = $conn->query("SELECT DISTINCT stake_address FROM wallets WHERE user_id = $user_id AND stake_address != ''");
$stakes = [];
if ($addr_res) { while ($r = $addr_res->fetch_assoc()) $stakes[] = $r['stake_address']; }
if (!$stakes) {
	report_out('No stake address on file for user_id ' . $user_id . ' -- skipping on-chain scan.');
} else {
	$assets = koios_post('account_assets', ['_stake_addresses' => $stakes]);
	$s2_owned = array_values(array_filter($assets, function ($a) use ($s2_policy) {
		return ($a['policy_id'] ?? '') === $s2_policy;
	}));
	report_out(count($stakes) . ' wallet(s) on file, ' . count($s2_owned) . ' S2 asset(s) held on-chain.');
	foreach ($s2_owned as $a) {
		$key = $a['policy_id'] . ':' . $a['asset_name'];
		if (isset($db_keys[$key])) continue;
		if (isset($wallet_only[$key])) continue;
		$wallet_only[$key] = ['asset_name_hex' => $a['asset_name'], 'fingerprint' => $a['fingerprint'] ?? null];
	}
}
if ($wallet_only) {
	report_out('');
	report_out('FOUND ' . count($wallet_only) . ' S2 asset(s) held on-chain but NOT synced into the platform DB:');
	foreach ($wallet_only as $key => $w) {
		report_out('  asset_name(hex)=' . $w['asset_name_hex'] . ' fingerprint=' . $w['fingerprint']);
	}
} else {
	report_out('No on-chain S2 assets found outside what the DB already has -- the DB set is complete.');
}
report_out('');

if (!$db_rows && !$wallet_only) {
	report_out('Nothing to report.');
	$conn->close();
	exit;
}

// Step 3: batch-fetch on-chain metadata for both DB-known rows and
// DB-only holdings.
$asset_list = [];
foreach ($db_rows as $row) $asset_list[] = [$row['policy'], bin2hex($row['asset_name'])];
foreach ($wallet_only as $w) $asset_list[] = [$s2_policy, $w['asset_name_hex']];

$batches = array_chunk($asset_list, 35);
$by_policy_asset = [];
foreach ($batches as $i => $batch) {
	report_out('Fetching metadata batch ' . ($i + 1) . '/' . count($batches) . ' (' . count($batch) . ' assets)...');
	foreach (koios_post('asset_info', ['_asset_list' => $batch]) as $entry) {
		$key = ($entry['policy_id'] ?? '') . ':' . ($entry['asset_name'] ?? '');
		$by_policy_asset[$key] = $entry;
	}
}
report_out('Metadata received for ' . count($by_policy_asset) . '/' . count($asset_list) . ' asset(s).');
report_out('');

// Same rarity-signal + full trait-flatten machinery as the S1 script.
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
$trait_values = [];
foreach (array_merge(
	array_map(function ($r) { return ['db' => $r, 'wallet_only' => null]; }, $db_rows),
	array_map(function ($w) { return ['db' => null, 'wallet_only' => $w]; }, array_values($wallet_only))
) as $item) {
	$row = $item['db'];
	$w = $item['wallet_only'];
	$policy = $row ? $row['policy'] : $s2_policy;
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
		'nft_id'           => $row ? intval($row['id']) : null,
		'synced_in_db'     => $row !== null,
		'name'             => $row ? $row['name'] : ($entry['asset_name_ascii'] ?? null),
		'image_url'        => $row ? getIPFS($row['ipfs'], $s2_collection_id, intval($row['project_id'])) : (is_array($metadata) ? ($metadata['image'] ?? null) : null),
		'fingerprint'      => $entry['fingerprint'] ?? ($w['fingerprint'] ?? null),
		'rarity_guess'     => $rarity_guess,
		'onchain_metadata' => $metadata,
	];
}

$json_path = __DIR__ . '/crypties-s2-rarity-report.json';
file_put_contents($json_path, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
report_out('Wrote ' . $json_path . ' (' . count($report) . ' entries).');

ksort($trait_values);
$summary = [];
foreach ($trait_values as $path => $values) {
	arsort($values);
	$summary[$path] = $values;
}
$summary_path = __DIR__ . '/crypties-s2-trait-summary.json';
file_put_contents($summary_path, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
report_out('Wrote ' . $summary_path . ' (' . count($summary) . ' trait path(s)).');
report_out('');

report_out('TRAIT SUMMARY (path: value=count, ...):');
foreach ($summary as $path => $values) {
	$pairs = [];
	foreach ($values as $v => $c) $pairs[] = "$v=$c";
	report_out('  ' . $path . ': ' . implode(', ', array_slice($pairs, 0, 16)) . (count($pairs) > 16 ? ' ... (+' . (count($pairs) - 16) . ' more)' : ''));
}
report_out('');

report_out(str_pad('SYNC', 6) . str_pad('NAME', 22) . 'RARITY-LOOKING FIELD');
report_out(str_repeat('-', 90));
foreach ($report as $r) {
	report_out(
		str_pad($r['synced_in_db'] ? 'yes' : 'NO', 6) .
		str_pad(substr($r['name'] ?? '(no name)', 0, 20), 22) .
		($r['rarity_guess'] ?? ($r['onchain_metadata'] === null ? '(no metadata returned)' : '(no obvious rarity field)'))
	);
}

$conn->close();
