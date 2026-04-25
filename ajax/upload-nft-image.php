<?php
ob_start();
ini_set('display_errors', 0);

register_shutdown_function(function() {
    $err = error_get_last();
    if ($err && ($err['type'] & (E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR))) {
        while (ob_get_level() > 0) ob_end_clean();
        if (!headers_sent()) header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Fatal: ' . $err['message'] . ' (' . basename($err['file']) . ':' . $err['line'] . ')']);
    }
});

include '../db.php';
header('Content-Type: application/json');

// ── Auth: must be logged in, must own the NFT ───────────────────────────────
if (!isset($_SESSION['userData']['user_id'])) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Not logged in.']);
    exit;
}
$user_id = intval($_SESSION['userData']['user_id']);

$nft_id = intval($_POST['nft_id'] ?? 0);
if ($nft_id < 1) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Missing nft_id.']);
    exit;
}

// ── File upload validation ──────────────────────────────────────────────────
if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
    $err_code = $_FILES['image']['error'] ?? -1;
    $err_msg  = match ($err_code) {
        UPLOAD_ERR_INI_SIZE   => 'File exceeds server upload limit.',
        UPLOAD_ERR_FORM_SIZE  => 'File exceeds form limit.',
        UPLOAD_ERR_PARTIAL    => 'Upload was interrupted.',
        UPLOAD_ERR_NO_FILE    => 'No file selected.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server is missing tmp directory.',
        UPLOAD_ERR_CANT_WRITE => 'Server could not write the file.',
        default               => 'Upload error (code ' . $err_code . ').',
    };
    ob_clean();
    echo json_encode(['success' => false, 'message' => $err_msg]);
    exit;
}

$tmp_path  = $_FILES['image']['tmp_name'];
$file_size = (int) $_FILES['image']['size'];
if ($file_size <= 0 || $file_size > 10 * 1024 * 1024) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'File must be between 1 byte and 10 MB.']);
    exit;
}

// ── Look up NFT and verify ownership ────────────────────────────────────────
// Owner-only restriction: prevents anyone from defacing other users' NFTs by
// uploading inappropriate replacements. Future relaxation could allow anyone
// logged in if abuse turns out not to be a concern.
$res = $conn->query("SELECT n.ipfs, n.collection_id, c.project_id
                     FROM nfts n JOIN collections c ON c.id = n.collection_id
                     WHERE n.id = " . $nft_id . " AND n.user_id = " . $user_id . " LIMIT 1");
$row = ($res && $res->num_rows > 0) ? $res->fetch_assoc() : null;
if (!$row) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'NFT not found or you don\'t own it.']);
    exit;
}

// ── Process and save with Imagick ───────────────────────────────────────────
// Mirror the cache script's pipeline: read, strip metadata (EXIF GPS etc.),
// resize to 1000px wide if larger, write to the same path the cache function
// would have written so getIPFS()'s local-first lookup picks it up next load.
try {
    $imagick = new Imagick();
    $imagick->setResourceLimit(Imagick::RESOURCETYPE_MEMORY, 256 * 1024 * 1024);
    $imagick->setResourceLimit(Imagick::RESOURCETYPE_MAP, 256 * 1024 * 1024);
    $imagick->readImage($tmp_path);

    $format = strtolower($imagick->getImageFormat());
    $ext_map = [
        'jpeg' => 'jpg',
        'jpg'  => 'jpg',
        'png'  => 'png',
        'gif'  => 'gif',
        'webp' => 'webp',
        'svg'  => 'svg',
    ];
    if (!isset($ext_map[$format])) {
        $imagick->clear();
        ob_clean();
        echo json_encode(['success' => false, 'message' => 'Unsupported image format: ' . $format]);
        exit;
    }
    $ext = $ext_map[$format];

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
    // Strip EXIF/metadata for user privacy (GPS, camera, etc.)
    if ($ext !== 'gif') $imagick->stripImage();

    $project_id    = (int) $row['project_id'];
    $collection_id = (int) $row['collection_id'];
    $md5           = md5($row['ipfs']);
    $dir           = dirname(__DIR__) . '/images/nfts/' . $project_id . '/' . $collection_id . '/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);

    // Remove any existing cached file with a different extension so the
    // new upload doesn't get masked by a stale glob match.
    foreach (glob($dir . $md5 . '.*') as $existing) {
        if (!str_ends_with($existing, '.lock')) @unlink($existing);
    }

    $filepath = $dir . $md5 . '.' . $ext;
    $imagick->writeImages($filepath, true);
    $imagick->clear();
    $imagick->destroy();

    // mtime cache-buster so the in-page <img> swap actually shows the new
    // file. Without this the URL is identical to any prior upload at the same
    // path/ext and the browser serves its cached copy.
    $mtime = @filemtime($filepath) ?: time();
    $url = '/staking/images/nfts/' . $project_id . '/' . $collection_id . '/' . $md5 . '.' . $ext . '?v=' . $mtime;
    $conn->close();
    ob_clean();
    echo json_encode(['success' => true, 'url' => $url]);
} catch (Exception $e) {
    ob_clean();
    echo json_encode(['success' => false, 'message' => 'Image processing failed: ' . $e->getMessage()]);
}
