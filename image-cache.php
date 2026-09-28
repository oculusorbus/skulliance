<?php
include_once 'db.php';
require_once __DIR__ . '/lib/image-cache-lib.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

$base_path   = __DIR__ . '/images/nfts/';
$num_workers = 16; // Parallel worker processes

// Defensive sweep of stale lock files from prior runs. Each lock is touched
// at /tmp/nft-img-{md5}.lock when a fetch starts and unlinked in a finally
// block when it completes. But if a worker is OOM-killed mid-fetch (LVE
// limits, etc.), the lock survives — and CageFS /tmp is invisible from the
// outside, so leaks accumulate unseen. Anything older than 6 hours is from
// a prior crashed run; reap before tonight's pass starts.
$lock_age_threshold = 6 * 3600;
$now_ts             = time();
$stale_cleaned      = 0;
foreach (glob(sys_get_temp_dir() . '/nft-img-*.lock') ?: [] as $lock) {
    $mt = @filemtime($lock);
    if ($mt !== false && $mt < $now_ts - $lock_age_threshold) {
        if (@unlink($lock)) $stale_cleaned++;
    }
}
if ($stale_cleaned > 0) {
    echo "Cleaned $stale_cleaned stale lock file(s) from prior runs.\n";
}

/*
 * ─── Optional filters ───────────────────────────────────────────────────────
 *
 *   php image-cache.php                 everything, as always
 *   php image-cache.php --chain=2       one blockchain
 *   php image-cache.php --collection=289
 *   php image-cache.php --project=9
 *
 * WHY. A full run walks every NFT of every active user and retries the
 * Cardano stragglers -- the handful whose gateways have never answered --
 * on every pass. A newly registered chain's NFTs are the newest rows, so
 * they sit at the BACK of that queue: after an XRPL launch you would wait
 * out the entire Cardano base before the images anybody is about to look at
 * get cached.
 *
 * Nothing about the filter changes what caching does, only which rows are
 * considered, so a targeted run and a full run cannot disagree.
 */
$filter_sql = '';
$filter_desc = 'all active users';
/* $argv exists only under the CLI SAPI. This script has no CLI guard and may
   be reachable over HTTP or driven by a URL cron, where array_slice($argv, 1)
   would be a fatal -- so read arguments only where there are arguments. */
$cli_args = (PHP_SAPI === 'cli' && isset($argv) && is_array($argv))
          ? array_slice($argv, 1) : array();
foreach ($cli_args as $arg) {
    if (preg_match('/^--chain=(\d+)$/', $arg, $m)) {
        $filter_sql .= ' AND n.blockchain_id = ' . (int)$m[1];
        $filter_desc = 'blockchain_id ' . (int)$m[1];
    } elseif (preg_match('/^--collection=(\d+)$/', $arg, $m)) {
        $filter_sql .= ' AND n.collection_id = ' . (int)$m[1];
        $filter_desc = 'collection ' . (int)$m[1];
    } elseif (preg_match('/^--project=(\d+)$/', $arg, $m)) {
        $filter_sql .= ' AND c.project_id = ' . (int)$m[1];
        $filter_desc = 'project ' . (int)$m[1];
    } else {
        echo "Unknown argument: $arg\n";
        echo "usage: php image-cache.php [--chain=N] [--collection=N] [--project=N]\n";
        exit(1);
    }
}
echo "Scope: $filter_desc\n";

// ─── Fetch all NFTs belonging to active users, Diamond Skull owners, and delegators ───
$sql = "
    SELECT DISTINCT n.id, n.ipfs, n.collection_id, c.project_id
    FROM nfts n
    JOIN collections c ON c.id = n.collection_id
    WHERE n.user_id > 0" . $filter_sql . "
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
/*
 * WORKERS SCALE TO THE JOB, not to the maximum.
 *
 * min($num_workers, $total) spawned 16 processes for a 33-NFT run -- three
 * NFTs each -- and hit the host's process limit: pcntl_fork() returned
 * EAGAIN ("Error 11"). Forking is not free on CloudLinux, where the LVE
 * caps concurrent processes per account, and a targeted run is now a normal
 * thing to do (--chain, --collection, --project) so small jobs are common.
 *
 * At least this many rows before another worker is worth its own process.
 */
$min_per_worker = 25;
$actual_workers = max(1, min($num_workers, (int) ceil($total / $min_per_worker)));
$chunks         = array_chunk($rows, (int) ceil($total / $actual_workers));
unset($rows); // free master memory before forking

echo "Found $total NFTs — spawning $actual_workers workers.\n\n";

$children  = [];
$tmp_dir   = sys_get_temp_dir();
/* Counted into the final summary alongside the workers', so a run that fell
   back to the parent still adds up to $total. */
$total_forked = 0;
$p_cached = $p_skipped = $p_errors = $p_existing = 0;

foreach ($chunks as $wid => $chunk) {
    $stats_file = "$tmp_dir/image_cache_w{$wid}.json";
    @unlink($stats_file); // clear any stale file from a prior run

    $pid = pcntl_fork();

    /*
     * A FAILED FORK IS NOT FATAL, and treating it as one was the worse bug.
     * die() here left the already-forked children running as orphans, wrote
     * no summary, and -- the part that actually costs something -- silently
     * dropped every chunk after this one. A 33-NFT run reported nothing and
     * left a third of the images uncached.
     *
     * The rows still need doing, so the parent does them itself. Slower than
     * a worker and entirely correct, which is the right trade for a fallback.
     */
    if ($pid === -1) {
        echo "[W" . ($wid + 1) . "] could not fork (" . $total_forked . " workers running)"
           . " — processing its " . count($chunk) . " row(s) in the parent\n";
        foreach ($chunk as $row) {
            $outcome = safeCache($row, $base_path, '[P]', 0);
            tally($outcome, $p_cached, $p_skipped, $p_errors, $p_existing);
            gc_collect_cycles();
        }
        continue;
    }
    $total_forked++;

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

$existing += $p_existing; $cached += $p_cached;
$skipped  += $p_skipped;  $errors += $p_errors;

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
