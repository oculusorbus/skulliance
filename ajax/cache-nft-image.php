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

/*
 * TIME BUDGET.
 *
 * This was 15 seconds with a 12-second fetch, sized for the old caller: an
 * image that failed to load kicking this off by itself, where nobody was
 * waiting and giving up early cost nothing.
 *
 * The caller now is a person who pressed a button and is watching a progress
 * bar, and the two parts of the work do not share a clock. The gateway race
 * is bounded by max_fetch_seconds below; what follows it is Imagick, which
 * is unbounded -- a LANCZOS resize of a large PNG, or coalescing an animated
 * GIF frame by frame, both take real time after the fetch is already done.
 * At 15s the script was still working when the browser gave up, so the
 * failure reported was the client's generic timeout rather than whatever the
 * server would have said.
 *
 * set_time_limit also does not count time spent outside PHP on Linux, so it
 * never cut the curl or the Imagick work off anyway -- it was not the cap it
 * looked like.
 *
 * Holding a worker longer is the cost. It is bounded: the page sends these
 * strictly one at a time, so a staker occupies one worker, not twenty-four.
 */
set_time_limit(30);

/*
 * ── CONCURRENCY CAP, PLATFORM-WIDE ──────────────────────────────────────────
 *
 * THE RATE LIMIT BELOW DOES NOT PREVENT AN OUTAGE and never did. It counts
 * requests per minute per address, which says nothing about how many are
 * running AT ONCE -- and it is per IP, so it cannot see the rest of the
 * platform at all. Thirty slow requests inside a minute from one address is
 * within its limit and is also every worker the account has.
 *
 * That is what happened: a client abandoned requests it thought had timed
 * out and started more, the server went on working on all of them, and
 * PHP-FPM ran out of workers. The whole site answered 503, including pages
 * that have nothing to do with NFTs.
 *
 * The client bug is fixed, but relying on a caller to be well behaved is how
 * this happened twice. This is the server's own floor: a handful of slots,
 * taken with a non-blocking lock. No slot means an immediate, cheap refusal
 * that frees the worker at once rather than a long request that occupies one.
 *
 * flock is released by the kernel when the process exits, so a worker killed
 * mid-fetch does not strand its slot -- which a counter in a file would.
 */
$cc_slots = 3;
$cc_held  = null;
for ($i = 0; $i < $cc_slots; $i++) {
    $fh = @fopen(sys_get_temp_dir() . '/nft-cache-slot-' . $i, 'c');
    if (!$fh) continue;
    if (@flock($fh, LOCK_EX | LOCK_NB)) { $cc_held = $fh; break; }
    @fclose($fh);
}
if (!$cc_held) {
    ob_clean();
    echo json_encode(['success' => false, 'status' => 'busy',
        'message' => 'Skulliance is already caching as many images as it can at once. '
                   . 'Give it a moment and try again.']);
    exit;
}
register_shutdown_function(function () use ($cc_held) {
    @flock($cc_held, LOCK_UN);
    @fclose($cc_held);
});

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
    /* A STATUS, so a caller can tell this apart from a real failure. Without
       one the client counted it as an error, and three in a row tripped its
       breaker on a queue that was merely going too fast -- which a page of
       48 makes easy to hit. */
    echo json_encode(['success' => false, 'status' => 'ratelimited',
        'message' => 'Going too fast. Pausing for a moment.']);
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
$t0 = microtime(true);
try {
    $result = cacheNFTImage(
        (string) $row['ipfs'],
        (int) $row['collection_id'],
        (int) $row['project_id'],
        null,     // default base_path (dirname(__DIR__ of lib) . '/images/nfts/')
        false,    // not verbose — no stdout in web context
        0,
        null,
        18        // max_fetch_seconds, a budget the race ACTUALLY honours.
                  // Several CIDs that failed at 12s are slow rather than
                  // gone, and somebody is watching this one. Chosen so the
                  // fetch plus a heavy Imagick pass still lands inside the
                  // browser's 40s, with room spare.
    );
} catch (Throwable $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Cache exception: ' . $e->getMessage()]);
    exit;
}

$conn->close();
ob_clean();

/* How long it took, so a caller can tell a slow success from a fast one and
   show why a run felt long. */
$ms = (int)round((microtime(true) - $t0) * 1000);

if (in_array($result['status'], ['cached', 'exists'], true) && !empty($result['url'])) {
    echo json_encode([
        'success' => true,
        'status'  => $result['status'],
        'url'     => $result['url'],
        'ms'      => $ms,
    ]);
} else {
    echo json_encode([
        'success' => false,
        'status'  => $result['status'],
        'message' => $result['message'] ?: 'Unable to cache image.',
        'ms'      => $ms,
    ]);
}
