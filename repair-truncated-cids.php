<?php
/**
 * repair-truncated-cids.php — CLI only. Put back the CIDs an old bug ate.
 *
 * WHAT HAPPENED. processNFT() used to run substr($image, 7) on every image
 * value to strip an "ipfs://" prefix, without first checking there was one.
 * Metadata that stores a BARE CID -- which is most of Cardano, and certainly
 * the Happy Hoppers Club Posters -- therefore lost its first seven
 * characters on the way into the database:
 *
 *     QmQtVpbUTXB29tNsdzaTrtEpn51NxFn6d68izsTUthbieW   on chain, 46 chars
 *     UTXB29tNsdzaTrtEpn51NxFn6d68izsTUthbieW          stored,   39 chars
 *
 * The normalisation is fixed, but that only helps rows written since. There
 * is no UPDATE of nfts.ipfs anywhere in verify.php, so every row mangled
 * while the bug was live still holds a CID no gateway can resolve -- the art
 * is fine and pinned, the pointer to it is not. Those rows show the skull,
 * and the cache button reports them as failures, because the thing being
 * asked for does not exist.
 *
 * The lost prefix cannot be derived from what is left, so each row is
 * re-read from chain. pool.pm answers on the fingerprint, which is exactly
 * what nfts.asset_id holds, and needs no key.
 *
 * DRY RUN BY DEFAULT. Nothing is written without --apply.
 *
 *     php repair-truncated-cids.php              # report only
 *     php repair-truncated-cids.php --apply      # fix them
 *     php repair-truncated-cids.php --limit=50   # work in batches
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

include __DIR__ . '/db.php';

$apply = in_array('--apply', $argv, true);
$limit = 0;
foreach ($argv as $a) if (preg_match('/^--limit=(\d+)$/', $a, $m)) $limit = (int)$m[1];

/*
 * WHAT A MANGLED CID LOOKS LIKE. A CIDv0 is 46 characters beginning "Qm";
 * a CIDv1 begins "baf". Anything else that is not a URL, not a data URI and
 * not empty is a candidate -- and a 39-character value is the signature of
 * this particular bug, seven short of 46.
 *
 * Deliberately conservative: it reports what it would change and changes
 * nothing that already looks like a valid pointer.
 */
$sql = "SELECT n.id, n.asset_id, n.name, n.ipfs, c.name AS collection
          FROM nfts n
          INNER JOIN collections c ON c.id = n.collection_id
         WHERE n.ipfs <> ''
           AND n.ipfs NOT LIKE 'Qm%'
           AND n.ipfs NOT LIKE 'baf%'
           AND n.ipfs NOT LIKE 'http%'
           AND n.ipfs NOT LIKE 'data:%'
         ORDER BY n.id" . ($limit ? " LIMIT $limit" : "");

$rows = array();
$res = $conn->query($sql);
if ($res) while ($r = $res->fetch_assoc()) $rows[] = $r;

printf("%d row%s with an unresolvable ipfs value%s\n\n",
       count($rows), count($rows) === 1 ? '' : 's', $apply ? '' : ' (dry run)');
if (!$rows) { echo "Nothing to do.\n"; exit(0); }

$fixed = $skipped = $failed = 0;
foreach ($rows as $r) {
	$fp = trim((string)$r['asset_id']);
	if (strpos($fp, 'asset1') !== 0) {
		printf("  SKIP  #%-7s %-34s no fingerprint to look up\n", $r['id'], substr($r['name'], 0, 34));
		$skipped++;
		continue;
	}

	$ch = curl_init('https://pool.pm/api/asset/' . rawurlencode($fp));
	curl_setopt_array($ch, array(
		CURLOPT_RETURNTRANSFER => 1, CURLOPT_TIMEOUT => 20,
		CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_FOLLOWLOCATION => 1,
		CURLOPT_HTTPHEADER => array('Accept: application/json', 'User-Agent: Skulliance/1.0'),
	));
	$body = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);

	$d = ($code === 200) ? json_decode((string)$body, true) : null;
	$img = ($d && isset($d['metadata']['image'])) ? $d['metadata']['image'] : null;
	if (is_array($img)) $img = implode('', $img);   // split across lines, as CIP-25 allows

	if (!$img) {
		printf("  FAIL  #%-7s %-34s chain lookup gave no image (HTTP %d)\n",
		       $r['id'], substr($r['name'], 0, 34), $code);
		$failed++;
		usleep(300000);
		continue;
	}

	/* THE SAME NORMALISATION verify.php uses now -- strip a prefix only when
	   there is one. Getting this wrong here would re-create the bug. */
	if (stripos($img, 'ipfs://') === 0) $img = substr($img, 7);
	$img = trim($img);

	if ($img === $r['ipfs']) {
		printf("  SAME  #%-7s %-34s already matches chain\n", $r['id'], substr($r['name'], 0, 34));
		$skipped++;
		usleep(300000);
		continue;
	}

	/* The stored value should be a SUFFIX of the real one -- that is what
	   losing a prefix means. If it is not, this row is wrong for some other
	   reason and is left alone rather than overwritten on a guess. */
	$suffix = (substr($img, -strlen($r['ipfs'])) === $r['ipfs']);
	if (!$suffix) {
		printf("  LEAVE #%-7s %-34s stored value is not a suffix of %s\n",
		       $r['id'], substr($r['name'], 0, 34), substr($img, 0, 20) . '...');
		$skipped++;
		usleep(300000);
		continue;
	}

	printf("  %s  #%-7s %-34s %s -> %s\n", $apply ? 'FIX ' : 'WOULD', $r['id'],
	       substr($r['name'], 0, 34), substr($r['ipfs'], 0, 14) . '...', substr($img, 0, 16) . '...');

	if ($apply) {
		$conn->query("UPDATE nfts SET ipfs = '" . $conn->real_escape_string($img) . "'
		               WHERE id = " . (int)$r['id']);
	}
	$fixed++;
	usleep(300000);   // be a good citizen on a free API
}

printf("\n%s: %d repaired, %d left alone, %d lookups failed\n",
       $apply ? 'Done' : 'Dry run', $fixed, $skipped, $failed);
if (!$apply && $fixed) echo "Re-run with --apply to write these.\n";
$conn->close();
