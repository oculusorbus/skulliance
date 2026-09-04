<?php
// cache-ohhmeed-art.php — bulk pre-cache of Ohh Meed artwork.
//
// WHY THIS EXISTS
// Oculus Lounge (a Drop Ship reskin, migrated in from madballs.net) shows
// each player's Ohh Meed NFTs as their in-game roster -- see
// dropshipOhhMeedLocalImages() in dropship/db.php, which reads Skulliance's
// own locally-cached copy instead of hotlinking jpgstoreapis.com. Same root
// problem cache-crypties-art.php was built for: an NFT can have a `nfts` row
// (metadata synced) with no image ever actually fetched to disk, especially
// for players who never loaded a Skulliance page that renders it. Reported
// live: a specific Oculus Lounge NFT still falling back to jpgstoreapis.com
// even after dropshipOhhMeedLocalImages() shipped -- this is why.
//
// Deliberately NOT a live auto-heal-on-image-error trigger. That was tried
// for the general NFT dashboard (healNFT() in skulliance.js) and had to be
// disabled after cascading cache-nft-image.php requests saturated PHP-FPM
// and locked the site -- see that function's own comment. A controlled,
// rate-limited, CLI-only batch job is the proven-safe pattern here, not a
// per-request fetch.
//
// USAGE -- CLI ONLY (see the SAPI guard below; over HTTP this 404s):
//   php cache-ohhmeed-art.php                       # dry run: just report
//   php cache-ohhmeed-art.php run=1                 # cache everything missing
//   php cache-ohhmeed-art.php run=1 limit=100       # do 100, then stop
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

// CLI ONLY -- same reasoning as cache-crypties-art.php: this has no auth and
// calls cacheNFTImage() directly, bypassing ajax/cache-nft-image.php's own
// per-IP rate limiting. Over HTTP it'd be a free way for any anonymous
// visitor to make this server perform hundreds of outbound IPFS fetches.
if (!$is_cli) {
	http_response_code(404);
	exit;
}

function oh_out($line) {
	echo $line . "\n";
	if (ob_get_level() > 0) { @ob_flush(); }
	@flush();
}

// Ohh Meed is Skulliance's own project_id 2 (see the assignRole block near
// the top of db.php for where that number is defined) -- filtered by
// project_id, not collection name, since that's the same key
// dropshipOhhMeedLocalImages() joins on and is unambiguous regardless of
// how any given collection happens to be named.
$result = $conn->query("
	SELECT n.id, n.ipfs, n.collection_id, c.project_id, c.name AS collection_name
	FROM nfts n
	INNER JOIN collections c ON c.id = n.collection_id
	WHERE c.project_id = 2
	ORDER BY n.collection_id ASC, n.id ASC
");

if (!$result) {
	oh_out('Query failed: ' . $conn->error);
	exit;
}

$total = 0; $already = 0; $missing = 0;
$todo = [];
while ($row = $result->fetch_assoc()) {
	$total++;
	// Same on-disk lookup getIPFS()/dropshipOhhMeedLocalImages() use, so
	// "already cached" here means exactly what the game means by it.
	if (cryptconquestHasLocalArt($row['ipfs'], $row['collection_id'], $row['project_id'])) {
		$already++;
		continue;
	}
	$missing++;
	$todo[] = $row;
}

oh_out('Ohh Meed NFTs found : ' . $total);
oh_out('Already cached      : ' . $already);
oh_out('Missing artwork     : ' . $missing);

if (!$do_run) {
	oh_out('');
	oh_out('Dry run -- nothing fetched. Re-run with run=1 to cache the missing ones.');
	$conn->close();
	exit;
}

if ($limit > 0 && count($todo) > $limit) {
	$todo = array_slice($todo, 0, $limit);
	oh_out('Limiting this pass to ' . $limit . '.');
}

oh_out('');
oh_out('Fetching ' . count($todo) . ' image(s). Already-cached files are never re-fetched.');
oh_out('');

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
			oh_out($label . ' OK');
		} elseif ($status === 'skipped') {
			$skipped++;
			oh_out($label . ' SKIP - ' . ($res['message'] ?? 'skipped'));
		} else {
			$failed++;
			oh_out($label . ' FAIL - ' . ($res['message'] ?? $status));
		}
	} catch (\Throwable $e) {
		// One bad row must never abort the batch.
		$failed++;
		oh_out($label . ' ERROR - ' . $e->getMessage());
	}
}

oh_out('');
oh_out('Done. cached=' . $ok . '  skipped=' . $skipped . '  failed=' . $failed);
if ($failed > 0) {
	oh_out('Failures are usually a dead/unpinned CID or a gateway timeout.');
	oh_out('Re-running is safe and will retry only what is still missing.');
}
$conn->close();
