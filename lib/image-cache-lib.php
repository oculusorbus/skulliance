<?php
// ─── NFT image caching — shared library ──────────────────────────────────────
// Extracted from the nightly CLI script so web-context callers (AJAX self-heal)
// and the CLI worker pool share one implementation.
//
// Return shape: ['status' => ..., 'url' => ..., 'message' => ...]
//   status values:
//     'cached'  — newly fetched and written locally
//     'exists'  — already on disk, no work done
//     'skipped' — empty / malformed CID, video/audio/app mime, or locked
//     'error'   — all gateways failed or Imagick failed
//   url — web path like "/staking/images/nfts/{project}/{collection}/{md5}.{ext}"
//         on cached/exists; null otherwise
//   message — human-readable detail (also echoed when $verbose is true)

function cacheNFTImage(
    string $ipfs,
    int $collection_id,
    int $project_id,
    ?string $base_path = null,
    bool $verbose = false,
    int $wid = 0,
    ?string $label = null
): array {
    if ($base_path === null) {
        $base_path = dirname(__DIR__) . '/images/nfts/';
    }
    $prefix = $label ? "$label " : '';

    $emit = function(string $msg) use ($verbose, $prefix) {
        if ($verbose) echo "  {$prefix}{$msg}\n";
        return $msg;
    };

    $clean_check = str_replace('ipfs/', '', $ipfs);
    if (empty(trim($clean_check))) {
        return ['status' => 'skipped', 'url' => null,
                'message' => $emit("[SKIP]   Empty IPFS hash for collection $collection_id")];
    }
    $dir = $base_path . $project_id . '/' . $collection_id . '/';
    $md5 = md5($ipfs);
    $web_base = '/staking/images/nfts/' . $project_id . '/' . $collection_id . '/' . $md5;

    // ── Skip obviously malformed CIDs before any network activity ─────────────
    if (!str_contains($ipfs, 'data:image/svg+xml;base64')) {
        $cid_check = trim($clean_check);
        if (strlen($cid_check) < 46 || stripos($cid_check, 'ImageCID') !== false) {
            return ['status' => 'skipped', 'url' => null,
                    'message' => $emit("[SKIP]   Malformed CID '$cid_check' (collection $collection_id)")];
        }
    }

    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    // ── On-chain SVG: save directly from base64 data, no HTTP fetch needed ──
    if (str_contains($ipfs, 'data:image/svg+xml;base64')) {
        $filepath = $dir . $md5 . '.svg';
        if (file_exists($filepath)) {
            return ['status' => 'exists', 'url' => $web_base . '.svg',
                    'message' => "[EXISTS] $filepath"];
        }
        $comma = strpos($ipfs, ',');
        if ($comma === false) {
            return ['status' => 'error', 'url' => null,
                    'message' => $emit("[ERROR] Malformed SVG data URI for md5 $md5")];
        }
        $svg = base64_decode(substr($ipfs, $comma + 1));
        if (file_put_contents($filepath, $svg) !== false) {
            return ['status' => 'cached', 'url' => $web_base . '.svg',
                    'message' => $emit("[SVG]    $filepath")];
        }
        return ['status' => 'error', 'url' => null,
                'message' => $emit("[ERROR] Could not write SVG $filepath")];
    }

    // ── Check if already cached (any extension) ──────────────────────────────
    $existing = glob($dir . $md5 . '.*');
    // Filter out the lock file if it's colocated (belt-and-suspenders — lock
    // files live in sys_get_temp_dir() but defend against misconfig anyway).
    $existing = array_filter($existing, fn($p) => !str_ends_with($p, '.lock'));
    if (!empty($existing)) {
        $ext = pathinfo(reset($existing), PATHINFO_EXTENSION);
        return ['status' => 'exists', 'url' => $web_base . '.' . $ext,
                'message' => ''];
    }

    // ── Concurrent-request lock: skip if another process is already fetching
    //     this same CID within the last 5 minutes (stale locks get overridden).
    $lock_path = sys_get_temp_dir() . '/nft-img-' . $md5 . '.lock';
    $lock_stale_after = 300;
    if (file_exists($lock_path) && (time() - filemtime($lock_path)) < $lock_stale_after) {
        return ['status' => 'skipped', 'url' => null,
                'message' => $emit("[SKIP]   Another process is caching $md5")];
    }
    @file_put_contents($lock_path, (string) time());

    try {
        return _doCacheFetch($ipfs, $collection_id, $project_id, $dir, $md5, $web_base, $wid, $emit);
    } finally {
        @unlink($lock_path);
    }
}

function _doCacheFetch(
    string $ipfs,
    int $collection_id,
    int $project_id,
    string $dir,
    string $md5,
    string $web_base,
    int $wid,
    callable $emit
): array {
    // ── Fetch with gateway fallback and retry ────────────────────────────────
    $clean_ipfs = str_replace('ipfs/', '', $ipfs);

    // jpg.store CDN is the fastest option and still live until 2026-05-23 —
    // pin it at position 0. After May 23, remove it from this list.
    $primary_gateway = 'https://ipfs5.jpgstoreapis.com/ipfs/';
    $fallback_gateways = [
        'https://nftstorage.link/ipfs/',
        'https://w3s.link/ipfs/',
        'https://gateway.pinata.cloud/ipfs/',
        'https://4everland.io/ipfs/',
        'https://ipfs.io/ipfs/',
        'https://dweb.link/ipfs/',
    ];
    $offset            = $wid % count($fallback_gateways);
    $fallback_gateways = array_merge(
        array_slice($fallback_gateways, $offset),
        array_slice($fallback_gateways, 0, $offset)
    );
    $gateways = array_merge([$primary_gateway], $fallback_gateways);

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
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
            curl_setopt($ch, CURLOPT_TIMEOUT, 45);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36',
                'Accept: image/avif,image/webp,image/apng,image/*,*/*;q=0.8',
                'Accept-Language: en-US,en;q=0.9',
                'Referer: https://www.jpg.store/',
            ]);
            $resp = curl_exec($ch);
            $ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($resp !== false && $code > 0 && $code < 400) {
                $body         = $resp;
                $content_type = $ct;
                $http_code    = $code;
                $tried_url    = $url;
                break 2;
            }
            if ($attempt === 1) usleep(1000000);
        }
    }

    if ($body === false || $http_code === 0) {
        return ['status' => 'error', 'url' => null,
                'message' => $emit("[ERROR] All gateways failed for $clean_ipfs")];
    }
    if ($http_code >= 400) {
        return ['status' => 'error', 'url' => null,
                'message' => $emit("[ERROR] HTTP $http_code fetching $tried_url (mime: $content_type)")];
    }

    $mime = trim(explode(';', $content_type)[0]);

    // Skip video/audio/application content
    foreach (['video/', 'audio/', 'application/'] as $type) {
        if (str_starts_with($mime, $type)) {
            return ['status' => 'skipped', 'url' => null,
                    'message' => $emit("[SKIP]   $tried_url ($mime)")];
        }
    }

    $ext_map = [
        'image/jpeg'    => 'jpg',
        'image/png'     => 'png',
        'image/gif'     => 'gif',
        'image/svg+xml' => 'svg',
        'image/webp'    => 'webp',
    ];
    $ext = $ext_map[$mime] ?? 'jpg';

    if ($ext === 'svg') {
        $filepath = $dir . $md5 . '.svg';
        if (file_put_contents($filepath, $body) !== false) {
            return ['status' => 'cached', 'url' => $web_base . '.svg',
                    'message' => $emit("[SVG]    $filepath")];
        }
        return ['status' => 'error', 'url' => null,
                'message' => $emit("[ERROR] Could not write SVG $filepath")];
    }

    $filepath = $dir . $md5 . '.' . $ext;

    try {
        $imagick = new Imagick();
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
        $imagick->setResourceLimit(Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
        $imagick->readImageBlob($body);

        $width = $imagick->getImageWidth();
        if ($width > 1000) {
            if ($ext === 'gif') {
                $imagick = $imagick->coalesceImages();
                foreach ($imagick as $frame) {
                    $frame->resizeImage(1000, 0, Imagick::FILTER_LANCZOS, 1);
                }
                $imagick = $imagick->deconstructImages();
            } else {
                $imagick->resizeImage(1000, 0, Imagick::FILTER_LANCZOS, 1);
            }
        }

        $imagick->writeImages($filepath, true);
        $imagick->clear();
        $imagick->destroy();

        return ['status' => 'cached', 'url' => $web_base . '.' . $ext,
                'message' => $emit("[CACHED] $filepath")];
    } catch (Exception $e) {
        return ['status' => 'error', 'url' => null,
                'message' => $emit("[ERROR] Imagick failed for $tried_url: " . $e->getMessage())];
    }
}
