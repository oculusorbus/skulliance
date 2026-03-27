<?php
ob_start();
header('Content-Type: application/json');

// DEBUG — remove after resolving
$_debug_log = __DIR__ . '/../debug_auction_create.txt';
function dbg($msg) { global $_debug_log; file_put_contents($_debug_log, date('H:i:s') . ' ' . $msg . "\n", FILE_APPEND); }
dbg('--- REQUEST START ---');
dbg('POST: ' . json_encode($_POST));
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    dbg("PHP ERROR [$errno] $errstr in $errfile:$errline");
    return false;
});
register_shutdown_function(function() {
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR])) {
        dbg('FATAL: ' . $e['message'] . ' in ' . $e['file'] . ':' . $e['line']);
    }
    dbg('--- REQUEST END ---');
});

include '../db.php';
dbg('db.php loaded, session user_id: ' . ($_SESSION['userData']['user_id'] ?? 'NOT SET'));
include '../webhooks.php';
dbg('webhooks.php loaded');
dbg('ob buffer contents so far: ' . substr(ob_get_contents(), 0, 500));

dbg('step: auth check');
if (!isset($_SESSION['userData']['user_id'])) { ob_clean(); echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }
dbg('step: auth passed');

$user_id        = intval($_SESSION['userData']['user_id']);
$title          = trim($_POST['title'] ?? '');
$desc           = trim($_POST['description'] ?? '');
$asset_id       = trim($_POST['asset_id'] ?? '');
$projects_raw   = $_POST['projects'] ?? '[]';
$start_date_raw = trim($_POST['start_date'] ?? '');
$end_date_raw   = trim($_POST['end_date'] ?? '');
dbg('step: vars assigned - title=' . $title . ' asset_id=' . $asset_id . ' end_date_raw=' . $end_date_raw);

if (!$title) { ob_clean(); echo json_encode(['success'=>false,'message'=>'Title is required.']); exit; }
dbg('step: title ok');
if (!$end_date_raw) { ob_clean(); echo json_encode(['success'=>false,'message'=>'End date is required.']); exit; }
dbg('step: end_date ok');
if (!$asset_id) { ob_clean(); echo json_encode(['success'=>false,'message'=>'Cardano Asset ID is required.']); exit; }
dbg('step: asset_id present');
if (!preg_match('/^asset1[a-z0-9]{38}$/', $asset_id)) {
    dbg('step: asset_id REGEX FAILED for: ' . $asset_id . ' len=' . strlen($asset_id));
    ob_clean(); echo json_encode(['success'=>false,'message'=>'Invalid asset ID — must be in asset1... fingerprint format.']); exit;
}
dbg('step: asset_id regex ok');

$projects = json_decode($projects_raw, true) ?: [];
$projects = array_values(array_filter($projects, function($p) {
    return intval($p['project_id'] ?? 0) > 0 && intval($p['minimum_bid'] ?? 0) > 0;
}));
dbg('step: projects count=' . count($projects));
if (empty($projects)) { ob_clean(); echo json_encode(['success'=>false,'message'=>'At least one currency with a minimum bid is required.']); exit; }

// Optional start_date (defaults to now)
if ($start_date_raw) {
    $ds = new DateTime($start_date_raw, new DateTimeZone('America/Chicago'));
    $ds->setTimezone(new DateTimeZone('UTC'));
    $start_date = $ds->format('Y-m-d H:i:s');
} else {
    $start_date = date('Y-m-d H:i:s');
}

// Normalize end_date to midnight CST (UTC-6)
$dt = new DateTime($end_date_raw, new DateTimeZone('America/Chicago'));
$dt->setTime(0, 0, 0);
$dt->setTimezone(new DateTimeZone('UTC'));
$end_date = $dt->format('Y-m-d H:i:s');

dbg('step: end_date parsed=' . $end_date);
if (strtotime($end_date) <= time()) { ob_clean(); echo json_encode(['success'=>false,'message'=>'End date must be in the future.']); exit; }
dbg('step: end_date in future ok');

// Image upload
$image_path = '';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    if ($file['size'] > 5 * 1024 * 1024) { echo json_encode(['success'=>false,'message'=>'Image must be under 5MB.']); exit; }
    $allowed_types = ['image/png','image/gif','image/jpeg','image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed_types)) { echo json_encode(['success'=>false,'message'=>'Invalid image type.']); exit; }

    $ext = ['image/png'=>'png','image/gif'=>'gif','image/jpeg'=>'jpg','image/webp'=>'webp'][$mime] ?? 'jpg';
    $dir = __DIR__ . '/../images/auctions/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (class_exists('Imagick') && $mime !== 'image/gif') {
        $imagick = new Imagick();
        $imagick->readImageBlob(file_get_contents($file['tmp_name']));
        if ($imagick->getImageWidth() > 1000) $imagick->resizeImage(1000, 0, Imagick::FILTER_LANCZOS, 1);
        $imagick->setImageFormat('png');
        $ext = 'png';
        $fname = uniqid('auction_', true) . '.' . $ext;
        $imagick->writeImage($dir . $fname);
        $imagick->clear(); $imagick->destroy();
    } elseif (class_exists('Imagick') && $mime === 'image/gif') {
        $imagick = new Imagick();
        $imagick->readImageBlob(file_get_contents($file['tmp_name']));
        if ($imagick->getImageWidth() > 1000) {
            $imagick = $imagick->coalesceImages();
            foreach ($imagick as $frame) { $frame->resizeImage(1000, 0, Imagick::FILTER_LANCZOS, 1); }
            $imagick = $imagick->deconstructImages();
        }
        $fname = uniqid('auction_', true) . '.gif';
        $imagick->writeImages($dir . $fname, true);
        $imagick->clear(); $imagick->destroy();
        $ext = 'gif';
    } else {
        $fname = uniqid('auction_', true) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $fname);
    }
    $image_path = 'images/auctions/' . $fname;
}

dbg('calling createAuction');
$auction_id = createAuction($conn, $user_id, $title, $desc, $image_path, $asset_id, $start_date, $end_date, $projects);
dbg('createAuction returned: ' . var_export($auction_id, true) . ' | conn error: ' . $conn->error);
if (!$auction_id) { echo json_encode(['success'=>false,'message'=>'Database error creating auction.']); exit; }

// Discord notification — build labels before closing conn, send after
$creator     = $_SESSION['userData']['name'] ?? 'Unknown';
$end_fmt     = (new DateTime($end_date, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Chicago'))->format('M j, Y');
$proj_labels = [];
foreach ($projects as $p) {
    $pr = $conn->query("SELECT name, currency FROM projects WHERE id=".intval($p['project_id'])." LIMIT 1");
    if ($pr && $pr->num_rows) { $row = $pr->fetch_assoc(); $proj_labels[] = number_format($p['minimum_bid']) . ' ' . strtoupper($row['currency']); }
}

$conn->close();

// Send success to client first, then fire Discord (non-blocking)
ob_end_clean();
dbg('sending success JSON for auction_id=' . $auction_id);
echo json_encode(['success'=>true,'auction_id'=>$auction_id]);

dbg('calling discordmsg');
discordmsg(
    '🔨 New Auction: ' . $title,
    "**$creator** listed a new auction!\n" .
    "Min Bid: **" . implode(' / ', $proj_labels) . "**\n" .
    "Ends: **$end_fmt**\n" .
    ($desc ? "\n" . mb_substr($desc, 0, 150) . (mb_strlen($desc) > 150 ? '…' : '') : ''),
    $image_path ? 'https://skulliance.io/staking/' . $image_path : '',
    'https://skulliance.io/staking/auctions.php',
    'auctions', '', '00c8a0'
);
