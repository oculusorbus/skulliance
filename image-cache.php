<?php
include_once 'db.php';

set_time_limit(0);

$base_path = __DIR__ . '/images/nfts/';

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

$total     = $result->num_rows;
$cached    = 0;
$skipped   = 0;
$errors    = 0;
$existing  = 0;

echo "Found $total NFTs to process.\n\n";

while ($row = $result->fetch_assoc()) {
    $outcome = cacheNFTImage($row['ipfs'], $row['collection_id'], $row['project_id'], $base_path);
    if ($outcome === 'exists')  { $existing++; }
    elseif ($outcome === 'cached') { $cached++;  }
    elseif ($outcome === 'skipped') { $skipped++; }
    else { $errors++; }
}

echo "\n--- Done ---\n";
echo "Already cached : $existing\n";
echo "Newly cached   : $cached\n";
echo "Skipped (video): $skipped\n";
echo "Errors         : $errors\n";
echo "Total          : $total\n";


// ─── Core caching function ───────────────────────────────────────────────────

function cacheNFTImage($ipfs, $collection_id, $project_id, $base_path) {
    $dir = $base_path . $project_id . '/' . $collection_id . '/';
    $md5 = md5($ipfs);

    // ── On-chain SVG: save directly from base64 data, no HTTP fetch needed ──
    if (str_contains($ipfs, 'data:image/svg+xml;base64')) {
        $filepath = $dir . $md5 . '.svg';
        if (file_exists($filepath)) return 'exists';
        if (!is_dir($dir)) mkdir($dir, 0755, true);
        $comma = strpos($ipfs, ',');
        if ($comma === false) {
            echo "  [ERROR] Malformed SVG data URI for md5 $md5\n";
            return 'error';
        }
        $svg = base64_decode(substr($ipfs, $comma + 1));
        if (file_put_contents($filepath, $svg) !== false) {
            echo "  [SVG]    $filepath\n";
            return 'cached';
        }
        echo "  [ERROR] Could not write SVG $filepath\n";
        return 'error';
    }

    // ── Check if already cached (any extension) ──────────────────────────────
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    $existing = glob($dir . $md5 . '.*');
    if (!empty($existing)) return 'exists';

    // ── Fetch from JPGStore ───────────────────────────────────────────────────
    $clean_ipfs = str_replace('ipfs/', '', $ipfs);
    $url = 'https://ipfs5.jpgstoreapis.com/ipfs/' . $clean_ipfs;

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    $body         = curl_exec($ch);
    $content_type = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $http_code    = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($body === false || $http_code >= 400) {
        echo "  [ERROR] HTTP $http_code fetching $url\n";
        return 'error';
    }

    // Strip charset suffix if present (e.g. "image/jpeg; charset=...")
    $mime = trim(explode(';', $content_type)[0]);

    // ── Skip video and non-image content ─────────────────────────────────────
    $skip_types = ['video/', 'audio/', 'application/'];
    foreach ($skip_types as $type) {
        if (str_starts_with($mime, $type)) {
            echo "  [SKIP]   $url ($mime)\n";
            return 'skipped';
        }
    }

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
            echo "  [SVG]    $filepath\n";
            return 'cached';
        }
        echo "  [ERROR] Could not write SVG $filepath\n";
        return 'error';
    }

    // ── Resize and save with Imagick ──────────────────────────────────────────
    $filepath = $dir . $md5 . '.' . $ext;

    try {
        $imagick = new Imagick();
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

        echo "  [CACHED] $filepath\n";
        return 'cached';

    } catch (Exception $e) {
        echo "  [ERROR] Imagick failed for $url: " . $e->getMessage() . "\n";
        return 'error';
    }
}
?>
