<?php
// cache-crypties-art.php — bulk pre-cache of Crypties artwork.
//
// WHY THIS EXISTS
// Crypt Conquest's court cards are drawn from Crypties Season 1 held by ANY
// public staker (see cryptconquestFetchCourtCandidates() in db.php), so the
// game now surfaces NFTs that nobody may ever have loaded a page for --
// inactive users especially. getIPFS() falls back to a public IPFS gateway
// for those, but that fallback is slow and often fails outright, which shows
// up in-game as a court card with no artwork at all.
//
// cryptconquestPickUniqueOwnerArt() already works around this by preferring
// owners whose art IS cached locally. That's a mitigation, not a cure: it
// quietly under-represents exactly the holders the platform-wide pool was
// meant to include. Running this once fixes it properly -- every Crypties
// holder becomes equally eligible, because every Crypties image is on disk.
//
// USAGE -- CLI ONLY (see the SAPI guard below; over HTTP this 404s):
//   php cache-crypties-art.php                       # dry run: just report
//   php cache-crypties-art.php run=1                 # cache everything missing
//   php cache-crypties-art.php run=1 limit=100       # do 100, then stop
//
// SAFE TO RE-RUN. Anything already on disk is skipped without a network call,
// so repeat runs cost almost nothing and only pick up whatever is new or
// previously failed. There is no delete path here -- it only ever adds files.
include_once 'db.php';
require_once __DIR__ . '/lib/image-cache-lib.php';

if (isset($argv)) {
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
}

$is_cli  = (php_sapi_name() === 'cli');
$do_run  = isset($_GET['run']);
$limit   = isset($_GET['limit']) ? max(0, intval($_GET['limit'])) : 0;

// CLI ONLY. This has no auth and calls cacheNFTImage() directly, which
// bypasses the per-IP rate limiting ajax/cache-nft-image.php applies -- so
// over HTTP it was a free way for any anonymous visitor to make this server
// perform hundreds of outbound IPFS fetches. Not theoretical: a browser hit
// on ?run=1&limit=10 during testing really did execute it, and collided with
// the CLI run that was already going (harmless -- the library's lock caught
// it -- but it proved the endpoint was live and unauthenticated).
//
// Kept rather than deleted because it stays useful: any Cryptie staked in
// future that wasn't on the platform before will need its art cached, and
// re-running is free for everything already on disk.
if (!$is_cli) {
	http_response_code(404);
	exit;
}

function cc_out($line) {
	echo $line . "\n";
	if (ob_get_level() > 0) { @ob_flush(); }
	@flush();
}

$result = $conn->query("
	SELECT n.id, n.ipfs, n.collection_id, c.project_id, c.name AS collection_name
	FROM nfts n
	INNER JOIN collections c ON c.id = n.collection_id
	WHERE c.name LIKE '%Crypties%'
	ORDER BY n.collection_id ASC, n.id ASC
");

if (!$result) {
	cc_out('Query failed: ' . $conn->error);
	exit;
}

$total = 0; $already = 0; $missing = 0;
$todo = [];
while ($row = $result->fetch_assoc()) {
	$total++;
	// Same on-disk lookup getIPFS()/cryptconquestHasLocalArt() use, so
	// "already cached" here means exactly what the game means by it.
	if (cryptconquestHasLocalArt($row['ipfs'], $row['collection_id'], $row['project_id'])) {
		$already++;
		continue;
	}
	$missing++;
	$todo[] = $row;
}

cc_out('Crypties NFTs found : ' . $total);
cc_out('Already cached      : ' . $already);
cc_out('Missing artwork     : ' . $missing);

if (!$do_run) {
	cc_out('');
	cc_out('Dry run -- nothing fetched. Re-run with run=1 to cache the missing ones.');
	$conn->close();
	exit;
}

if ($limit > 0 && count($todo) > $limit) {
	$todo = array_slice($todo, 0, $limit);
	cc_out('Limiting this pass to ' . $limit . '.');
}

cc_out('');
cc_out('Fetching ' . count($todo) . ' image(s). Already-cached files are never re-fetched.');
cc_out('');

$ok = 0; $failed = 0; $skipped = 0; $i = 0;
foreach ($todo as $row) {
	$i++;
	$label = '[' . $i . '/' . count($todo) . '] nft ' . intval($row['id']);
	try {
		$res = cacheNFTImage(
			$row['ipfs'],
			intval($row['collection_id']),
			intval($row['project_id']),
			null,
			false,      // quiet -- this script prints its own one-line-per-NFT summary
			0,
			null,
			25          // per-image budget; a stuck gateway must not stall the batch
		);
		// cacheNFTImage() returns exactly: cached | exists | skipped | error
		// (verified in lib/image-cache-lib.php). 'exists' means another
		// process cached it between our on-disk check above and this call.
		$status = $res['status'] ?? 'unknown';
		if ($status === 'cached' || $status === 'exists') {
			$ok++;
			cc_out($label . ' OK');
		} elseif ($status === 'skipped') {
			$skipped++;
			cc_out($label . ' SKIP - ' . ($res['message'] ?? 'skipped'));
		} else {
			$failed++;
			cc_out($label . ' FAIL - ' . ($res['message'] ?? $status));
		}
	} catch (\Throwable $e) {
		// One bad row must never abort the batch.
		$failed++;
		cc_out($label . ' ERROR - ' . $e->getMessage());
	}
}

cc_out('');
cc_out('Done. cached=' . $ok . '  skipped=' . $skipped . '  failed=' . $failed);
if ($failed > 0) {
	cc_out('Failures are usually a dead/unpinned CID or a gateway timeout.');
	cc_out('Re-running is safe and will retry only what is still missing.');
}
$conn->close();
