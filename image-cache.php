<?php
include_once 'db.php';
require_once __DIR__ . '/lib/image-cache-lib.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

$base_path   = __DIR__ . '/images/nfts/';
$num_workers = 16; // Parallel worker processes

// ─── Fetch all NFTs belonging to active users, Diamond Skull owners, and delegators ───
$sql = "
    SELECT DISTINCT n.id, n.ipfs, n.collection_id, c.project_id
    FROM nfts n
    JOIN collections c ON c.id = n.collection_id
    WHERE n.user_id > 0
    AND n.user_id IN (
        SELECT id FROM (
            SELECT id FROM users
            WHERE last_login >= NOW() - INTERVAL 1 MONTH

            UNION

            SELECT DISTINCT user_id AS id FROM nfts
            WHERE collection_id = 16

            UNION

            SELECT DISTINCT n2.user_id AS id FROM nfts n2
            JOIN diamond_skulls ds ON ds.nft_id = n2.id
        ) AS active_users
    )
";

$result = $conn->query($sql);

if (!$result) {
    die("Query failed: " . $conn->error . "\n");
}

$rows = [];
while ($row = $result->fetch_assoc()) {
    $rows[] = $row;
}

// Close DB connection before forking — each child gets its own copy of the data
$conn->close();

$total = count($rows);

// ─── Single-process fallback if pcntl not available ──────────────────────────
if (!function_exists('pcntl_fork') || $total === 0) {
    if ($total === 0) {
        echo "No NFTs to process.\n";
        exit(0);
    }
    echo "pcntl not available — running single-process.\n";
    echo "Found $total NFTs to process.\n\n";
    $cached = $skipped = $errors = $existing = 0;
    foreach ($rows as $row) {
        $outcome = safeCache($row, $base_path, null, 0);
        tally($outcome, $cached, $skipped, $errors, $existing);
        gc_collect_cycles();
    }
    printSummary($existing, $cached, $skipped, $errors, $total);
    exit(0);
}

// ─── Multi-process: split rows into chunks and fork workers ──────────────────
$actual_workers = min($num_workers, $total);
$chunks         = array_chunk($rows, (int) ceil($total / $actual_workers));
unset($rows); // free master memory before forking

echo "Found $total NFTs — spawning $actual_workers workers.\n\n";

$children  = [];
$tmp_dir   = sys_get_temp_dir();

foreach ($chunks as $wid => $chunk) {
    $stats_file = "$tmp_dir/image_cache_w{$wid}.json";
    @unlink($stats_file); // clear any stale file from a prior run

    $pid = pcntl_fork();

    if ($pid === -1) {
        die("Failed to fork worker $wid\n");
    }

    if ($pid === 0) {
        // ── Child worker ──────────────────────────────────────────────────────
        $label   = '[W' . ($wid + 1) . ']';
        $wcached = $wskipped = $werrors = $wexisting = 0;

        foreach ($chunk as $row) {
            $outcome = safeCache($row, $base_path, $label, $wid);
            tally($outcome, $wcached, $wskipped, $werrors, $wexisting);
            gc_collect_cycles();
        }

        file_put_contents($stats_file, json_encode([
            'cached'   => $wcached,
            'skipped'  => $wskipped,
            'errors'   => $werrors,
            'existing' => $wexisting,
        ]));

        echo "$label Done — existing:$wexisting cached:$wcached skipped:$wskipped errors:$werrors\n";
        exit(0);
    }

    // ── Parent: record child ──────────────────────────────────────────────────
    $children[$wid] = ['pid' => $pid, 'stats' => $stats_file];
}

// ─── Wait for all workers to finish ──────────────────────────────────────────
foreach ($children as $child) {
    pcntl_waitpid($child['pid'], $status);
}

// ─── Aggregate stats ─────────────────────────────────────────────────────────
$cached = $skipped = $errors = $existing = 0;

foreach ($children as $wid => $child) {
    if (!file_exists($child['stats'])) {
        echo "[W" . ($wid + 1) . "] WARNING: no stats file found\n";
        continue;
    }
    $s = json_decode(file_get_contents($child['stats']), true);
    @unlink($child['stats']);
    $existing += $s['existing'];
    $cached   += $s['cached'];
    $skipped  += $s['skipped'];
    $errors   += $s['errors'];
}

printSummary($existing, $cached, $skipped, $errors, $total);


// ─── Helpers ─────────────────────────────────────────────────────────────────

function safeCache(array $row, string $base_path, ?string $label, int $wid): string {
    try {
        $result = cacheNFTImage(
            (string) $row['ipfs'],
            (int) $row['collection_id'],
            (int) $row['project_id'],
            $base_path,
            true,    // verbose — keep CLI echo output
            $wid,
            $label
        );
        return $result['status'];
    } catch (Throwable $e) {
        $prefix = $label ? "$label " : '';
        echo "  {$prefix}[ERROR] Caught exception for NFT {$row['id']}: " . $e->getMessage() . "\n";
        return 'error';
    }
}

function tally(string $outcome, int &$cached, int &$skipped, int &$errors, int &$existing): void {
    match ($outcome) {
        'exists'  => $existing++,
        'cached'  => $cached++,
        'skipped' => $skipped++,
        default   => $errors++,
    };
}

function printSummary(int $existing, int $cached, int $skipped, int $errors, int $total): void {
    echo "\n--- Done ---\n";
    echo "Already cached : $existing\n";
    echo "Newly cached   : $cached\n";
    echo "Skipped        : $skipped\n";
    echo "Errors         : $errors\n";
    echo "Total          : $total\n";
}


// cacheNFTImage() lives in lib/image-cache-lib.php — shared between this
// CLI worker pool and the ajax/cache-nft-image.php self-heal endpoint.
?>
