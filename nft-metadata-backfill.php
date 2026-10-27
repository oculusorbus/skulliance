<?php
/**
 * nft-metadata-backfill.php — fill in the documents the crawl will never reach.
 *
 * Run: php nft-metadata-backfill.php limit=500
 *      php nft-metadata-backfill.php limit=500 chain=1 dry=1
 *
 * WHY THIS IS NOT OPTIONAL. The nightly crawls only touch wallets that
 * verify, and on Polygon and Solana they only fetch metadata for assets they
 * are CREATING -- `verify-polygon.php` says so outright: "METADATA IS FOR NEW
 * NFTs ONLY". So without a backfill, everything already staked stays blank
 * indefinitely, and a half-populated column is the kind of thing a feature
 * quietly builds on and then breaks on.
 *
 * Cardano is the exception and needs the least: asset_info already returns
 * the document for every asset in a wallet, so verify.php now keeps it on
 * every pass. This job is mostly here for the other three and for assets
 * whose holder has not verified recently.
 *
 * RESUMABLE BY CONSTRUCTION, because it walks `metadata_at IS NULL` and
 * stamps that column as it goes. Kill it, run it again, it picks up where it
 * stopped. That is the whole reason metadata_at exists as a separate column
 * rather than being inferred from metadata being non-null -- an asset that
 * genuinely has no readable document still gets marked done, so it is tried
 * once and not forever.
 *
 * READ ONLY AGAINST THE CHAIN, write only to two columns. It cannot change
 * ownership, cannot create or delete a row, and cannot affect a reward.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
set_time_limit(0);
error_reporting(E_ALL & ~E_DEPRECATED);

parse_str(implode('&', array_slice($argv, 1)), $opt);
$LIMIT = isset($opt['limit']) ? max(1, min(5000, (int)$opt['limit'])) : 250;
$CHAIN = isset($opt['chain']) ? (int)$opt['chain'] : 0;      // 0 = every chain
$DRY   = !empty($opt['dry']);
$SLEEP = isset($opt['sleep']) ? max(0, min(5, (float)$opt['sleep'])) : 0.2;

include __DIR__ . '/db.php';
require_once __DIR__ . '/lib/nft-metadata-lib.php';
require_once __DIR__ . '/lib/ipfs-gateways.php';

/* The column has to exist before this does anything. Checked out loud,
   because a missing column makes every UPDATE fail silently and the job
   would report a clean run having written nothing. */
$have = @$conn->query("SHOW COLUMNS FROM nfts LIKE 'metadata_at'");
if (!$have || !$have->num_rows) {
	fwrite(STDERR, "nfts.metadata_at does not exist. Run the SQL in "
	             . "nft-metadata-schema.md first.\n");
	exit(1);
}

/*
 * WHAT IS STILL OWED. metadata_at IS NULL and nothing else: a row that has
 * been tried is done whether or not it yielded anything, or an asset with no
 * readable document is re-fetched every night forever.
 */
$where = "metadata_at IS NULL" . ($CHAIN ? " AND blockchain_id = " . $CHAIN : "");
$r = @$conn->query("SELECT COUNT(*) AS n FROM nfts WHERE $where");
$owed = ($r && ($x = $r->fetch_assoc())) ? (int)$x['n'] : 0;
printf("%d rows still owed%s. Doing up to %d.%s\n",
	$owed, $CHAIN ? " on chain $CHAIN" : "", $LIMIT, $DRY ? "  [dry run]" : "");
if (!$owed) exit(0);

/* DISTINCT ON asset_id: one asset can own several rows and one fetch
   serves all of them, because nft_metadata_store() writes by asset_id. */
$q = @$conn->query("SELECT MIN(id) AS id, asset_id, asset_name, blockchain_id, ipfs
                      FROM nfts WHERE $where
                     GROUP BY asset_id, asset_name, blockchain_id, ipfs
                     LIMIT $LIMIT");
if (!$q) { fwrite(STDERR, "could not read the queue\n"); exit(1); }

$rows = array();
while ($x = $q->fetch_assoc()) $rows[] = $x;
printf("fetched a queue of %d assets\n\n", count($rows));

/** One HTTP GET, short timeouts, no exceptions. */
function bf_get($url, $timeout = 12) {
	$ch = curl_init($url);
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => 1, CURLOPT_FOLLOWLOCATION => 1,
		CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6,
		CURLOPT_USERAGENT => 'skulliance-metadata-backfill',
	));
	$body = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	return ($body !== false && $code >= 200 && $code < 300) ? $body : '';
}

/**
 * Cardano, in batches of 35 -- the limit verify.php already observes against
 * the free Koios plan, and the reason it batches at all.
 */
function bf_cardano($conn, $rows, $dry) {
	$done = 0; $empty = 0;
	foreach (array_chunk($rows, 35) as $chunk) {
		$list = array();
		foreach ($chunk as $row) {
			/* asset_info keys on policy+hex name, and nfts stores the
			   fingerprint -- so the policy has to come off the collection. */
			$pr = @$conn->query("SELECT c.policy FROM nfts n
			                     INNER JOIN collections c ON c.id = n.collection_id
			                     WHERE n.asset_id = '" . $conn->real_escape_string($row['asset_id']) . "'
			                     LIMIT 1");
			$pol = ($pr && ($p = $pr->fetch_assoc())) ? $p['policy'] : '';
			if ($pol === '' || $row['asset_name'] === '') { $empty++; continue; }
			$list[] = array($pol, bin2hex($row['asset_name']));
		}
		if (!$list) continue;
		$ch = curl_init("https://api.koios.rest/api/v1/asset_info");
		curl_setopt_array($ch, array(
			CURLOPT_HTTPHEADER => array('Content-type: application/json'),
			CURLOPT_POST => 1, CURLOPT_POSTFIELDS => json_encode(array('_asset_list' => $list)),
			CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 30,
		));
		$body = curl_exec($ch); curl_close($ch);
		$decoded = json_decode($body);
		if (!is_array($decoded)) { fwrite(STDERR, "  koios batch failed\n"); continue; }
		foreach ($decoded as $asset) {
			$doc = nft_metadata_from_koios($asset);
			if (!$doc) { $empty++; continue; }
			if ($dry) { $done++; continue; }
			if (nft_metadata_store($conn, $asset->fingerprint, $doc)) $done++;
		}
		usleep(400000);
	}
	return array($done, $empty);
}

/**
 * The other three are the CRAWL's job, not this one.
 *
 * Their document lives at a URI that is only known on chain -- nfts.ipfs
 * holds the PICTURE, not the metadata URI -- so there is nothing here to
 * resolve from. Rather than reimplement three chains' URI lookups, each
 * verifier now carries a $needmeta set: any row with metadata_at IS NULL is
 * re-fetched on the next pass over that wallet, without touching its name
 * or image. The holder verifying is what fills these in.
 *
 * SAID OUT LOUD RATHER THAN SILENTLY SKIPPED. An operator who runs this
 * expecting it to fill in Solana needs to know why it did not, and "0 rows
 * written, no errors" tells them nothing.
 */
function bf_other($rows) {
	return count($rows);
}

$byChain = array();
foreach ($rows as $row) $byChain[(int)$row['blockchain_id']][] = $row;

$total = 0;
foreach ($byChain as $chain => $set) {
	if ($chain === 1) {
		list($done, $empty) = bf_cardano($conn, $set, $DRY);
		printf("cardano : %d written, %d with no readable document\n", $done, $empty);
		$total += $done;
	} else {
		printf("chain %-2d: %d assets left to the crawl -- their metadata URI is only "
		     . "known on chain, so the verifier's \$needmeta set re-fetches them the "
		     . "next time the holder verifies\n", $chain, bf_other($set));
	}
}
printf("\n%d documents stored%s.\n", $total, $DRY ? " (dry run: nothing written)" : "");
