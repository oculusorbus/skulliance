<?php
ob_start();
ini_set('display_errors', 0);

// Register BEFORE includes so a missing/broken lib or db.php still returns
// JSON instead of a bare 500 page.
register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal: ' . $err['message'] . ' (' . basename($err['file']) . ':' . $err['line'] . ')']);
    }
});

include '../db.php';
require_once __DIR__ . '/../lib/image-cache-lib.php';

header('Content-Type: application/json');

// Hard cap so a single request can't hang a PHP-FPM worker on a slow gateway.
// Means some 4everland-style recoveries won't finish in time — that's OK, next
// visitor tries again after the in-memory lock expires.
set_time_limit(15);

// ── IP-based rate limit ──────────────────────────────────────────────────────
// 30 requests / 60s per IP. File holds newline-separated unix timestamps,
// stale entries pruned on each call. Works across anonymous and logged-in
// visitors since public pages (profile.php) can trigger this.
$ip         = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rl_window  = 60;
$rl_max     = 30;
$rl_path    = sys_get_temp_dir() . '/nft-cache-rl-' . md5($ip);
$now        = time();
$timestamps = [];
if (file_exists($rl_path)) {
    $raw = @file_get_contents($rl_path);
    foreach (explode("\n", (string) $raw) as $line) {
        $line = trim($line);
        if ($line !== '' && ctype_digit($line) && ((int) $line) > ($now - $rl_window)) {
            $timestamps[] = (int) $line;
        }
    }
}
if (count($timestamps) >= $rl_max) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Rate limit: try again in a moment.']);
    exit;
}
$timestamps[] = $now;
@file_put_contents($rl_path, implode("\n", $timestamps));

// ── Input validation ─────────────────────────────────────────────────────────
$nft_id = intval($_POST['nft_id'] ?? $_GET['nft_id'] ?? 0);
if ($nft_id < 1) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing nft_id.']);
    exit;
}

// Never trust a client-supplied IPFS hash — look it up from the DB so this
// endpoint can only be used to cache NFTs the platform already knows about.
// $nft_id is intval'd above so safe to concatenate. mysqli on this server
// is built without mysqlnd, so get_result() is unavailable — stick with the
// codebase's standard string-concat + query() pattern.
$res = $conn->query("SELECT n.ipfs, n.collection_id, c.project_id
                     FROM nfts n JOIN collections c ON c.id = n.collection_id
                     WHERE n.id = " . $nft_id . " LIMIT 1");
$row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;

if (!$row) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'NFT not found.']);
    exit;
}

// ── Run the cache attempt ────────────────────────────────────────────────────
try {
    $result = cacheNFTImage(
        (string) $row['ipfs'],
        (int) $row['collection_id'],
        (int) $row['project_id'],
        null,     // default base_path (dirname(__DIR__ of lib) . '/images/nfts/')
        false,    // not verbose — no stdout in web context
        0,
        null,
        12        // max_fetch_seconds — leaves headroom for Imagick + network
                  // inside the client's 20s AJAX timeout
    );
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Cache exception: ' . $e->getMessage()]);
    exit;
}

$conn->close();
ob_clean();

if (in_array($result['status'], ['cached', 'exists'], true) && !empty($result['url'])) {
    echo json_encode([
        'success' => true,
        'status'  => $result['status'],
        'url'     => $result['url'],
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status'  => $result['status'],
        'message' => $result['message'] ?: 'Unable to cache image.',
    ]);
}
