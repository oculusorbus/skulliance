<?php
include_once 'db.php';

set_time_limit(0);
ini_set('memory_limit', '512M');

$base_path   = __DIR__ . '/images/nfts/';
$num_workers = 8; // Parallel worker processes

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
        $outcome = safeCache($row, $base_path, null);
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
            $outcome = safeCache($row, $base_path, $label);
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

function safeCache(array $row, string $base_path, ?string $label): string {
    try {
        return cacheNFTImage($row['ipfs'], $row['collection_id'], $row['project_id'], $base_path, $label);
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
    echo "Skipped (video): $skipped\n";
    echo "Errors         : $errors\n";
    echo "Total          : $total\n";
}


// ─── Core caching function ───────────────────────────────────────────────────

function cacheNFTImage($ipfs, $collection_id, $project_id, $base_path, $label = null) {
    $prefix = $label ? "$label " : '';

    $clean_check = str_replace('ipfs/', '', $ipfs);
    if (empty(trim($clean_check))) {
        echo "  {$prefix}[SKIP]   Empty IPFS hash for collection $collection_id\n";
        return 'skipped';
    }
    $dir = $base_path . $project_id . '/' . $collection_id . '/';
    $md5 = md5($ipfs);

    // ── On-chain SVG: save directly from base64 data, no HTTP fetch needed ──
    if (str_contains($ipfs, 'data:image/svg+xml;base64')) {
        $filepath = $dir . $md5 . '.svg';
        if (file_exists($filepath)) return 'exists';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $comma = strpos($ipfs, ',');
        if ($comma === false) {
            echo "  {$prefix}[ERROR] Malformed SVG data URI for md5 $md5\n";
            return 'error';
        }
        $svg = base64_decode(substr($ipfs, $comma + 1));
        if (file_put_contents($filepath, $svg) !== false) {
            echo "  {$prefix}[SVG]    $filepath\n";
            return 'cached';
        }
        echo "  {$prefix}[ERROR] Could not write SVG $filepath\n";
        return 'error';
    }

    // ── Check if already cached (any extension) ──────────────────────────────
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $existing = glob($dir . $md5 . '.*');
    if (!empty($existing)) return 'exists';

    // ── Fetch with gateway fallback and retry ────────────────────────────────
    $clean_ipfs = str_replace('ipfs/', '', $ipfs);
    $gateways = [
        'https://ipfs5.jpgstoreapis.com/ipfs/',
        'https://cloudflare-ipfs.com/ipfs/',
        'https://ipfs.io/ipfs/',
        'https://dweb.link/ipfs/',
    ];

    $body         = false;
    $content_type = '';
    $http_code    = 0;
    $tried_url    = '';

    foreach ($gateways as $gateway) {
        $url = $gateway . $clean_ipfs;
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Referer: https://www.jpg.store/',
            ]);
            $resp      = curl_exec($ch);
            $ct        = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $code      = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp !== false && $code > 0 && $code < 400) {
                $body         = $resp;
                $content_type = $ct;
                $http_code    = $code;
                $tried_url    = $url;
                break 2; // success — stop trying gateways
            }

            // Transient failure — pause briefly before retry or next gateway
            if ($attempt === 1) usleep(500000); // 0.5s between retries
        }
    }

    if ($body === false || $http_code === 0) {
        echo "  {$prefix}[ERROR] All gateways failed for $clean_ipfs\n";
        return 'error';
    }
    if ($http_code >= 400) {
        echo "  {$prefix}[ERROR] HTTP $http_code fetching $tried_url (mime: $content_type)\n";
        return 'error';
    }

    // Strip charset suffix if present (e.g. "image/jpeg; charset=...")
    $mime = trim(explode(';', $content_type)[0]);

    // ── Skip video and non-image content ─────────────────────────────────────
    $skip_types = ['video/', 'audio/', 'application/'];
    foreach ($skip_types as $type) {
        if (str_starts_with($mime, $type)) {
            echo "  {$prefix}[SKIP]   $url ($mime)\n";
            return 'skipped';
        }
    }

    // ── Skip animated GIFs (temporarily disabled for performance) ─────────────
    if ($mime === 'image/gif') {
        echo "  {$prefix}[SKIP]   $url (gif - temporarily skipped)\n";
        return 'skipped';
    }
    // ── END SKIP GIFs ──────────────────────────────────────────────────────────

    // ── Determine file extension ──────────────────────────────────────────────
    $ext_map = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/svg+xml' => 'svg',
        'image/webp'    => 'webp',
    ];
    $ext = $ext_map[$mime] ?? 'jpg';

    // ── SVG served by JPGStore: save directly, no Imagick needed ─────────────
    if ($ext === 'svg') {
        $filepath = $dir . $md5 . '.svg';
        if (file_put_contents($filepath, $body) !== false) {
            echo "  {$prefix}[SVG]    $filepath\n";
            return 'cached';
        }
        echo "  {$prefix}[ERROR] Could not write SVG $filepath\n";
        return 'error';
    }

    // ── Resize and save with Imagick ──────────────────────────────────────────
    $filepath = $dir . $md5 . '.' . $ext;

    try {
        $imagick = new Imagick();
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024); // 256MB cap
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
        $imagick->readImageBlob($body);

        $width = $imagick->getImageWidth();

        if ($width > 1000) {
            if ($ext === 'gif') {
                // Resize each frame of animated GIF
                $imagick = $imagick->coalesceImages();
                foreach ($imagick as $frame) {
                    $frame->resizeImage(1000, 0, Imagick::FILTER_LANCZOS, 1);
                }
                $imagick = $imagick->deconstructImages();
            } else {
                $imagick->resizeImage(1000, 0, Imagick::FILTER_LANCZOS, 1);
            }
        }

        // writeImages handles both single frames and multi-frame GIFs
        $imagick->writeImages($filepath, true);
        $imagick->clear();
        $imagick->destroy();

        echo "  {$prefix}[CACHED] $filepath\n";
        return 'cached';

    } catch (Exception $e) {
        echo "  {$prefix}[ERROR] Imagick failed for $url: " . $e->getMessage() . "\n";
        return 'error';
    }
}
?>
