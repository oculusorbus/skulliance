<?php
include '../db.php';
include '../message.php';
include '../verify.php';
include '../webhooks.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id  = intval($_SESSION['userData']['user_id']);
$title    = trim($_POST['title'] ?? '');
$desc     = trim($_POST['description'] ?? '');
$nft_name = trim($_POST['nft_name'] ?? '');
$asset_id = trim($_POST['asset_id'] ?? '');
$start_bid = floatval($_POST['start_bid'] ?? 0);
$bid_pid  = intval($_POST['bid_project_id'] ?? 0);
$end_date_raw = trim($_POST['end_date'] ?? '');
$allowed_raw  = $_POST['allowed_projects'] ?? '[]';

if (!$title) { echo json_encode(['success'=>false,'message'=>'Title is required.']); exit; }
if ($start_bid < 1) { echo json_encode(['success'=>false,'message'=>'Starting bid must be at least 1.']); exit; }
if (!$end_date_raw) { echo json_encode(['success'=>false,'message'=>'End date is required.']); exit; }
if ($asset_id && !preg_match('/^[0-9a-fA-F]{56,120}$/', $asset_id)) {
    echo json_encode(['success'=>false,'message'=>'Invalid asset ID format.']); exit;
}

// Normalize end_date to midnight CST (UTC-6)
$dt = new DateTime($end_date_raw, new DateTimeZone('America/Chicago'));
$dt->setTime(0, 0, 0);
$dt->setTimezone(new DateTimeZone('UTC'));
$end_date = $dt->format('Y-m-d H:i:s');

if (strtotime($end_date) <= time()) { echo json_encode(['success'=>false,'message'=>'End date must be in the future.']); exit; }

$allowed_pids = json_decode($allowed_raw, true) ?: [];
if ($bid_pid) $allowed_pids = [$bid_pid]; // single-currency overrides multi-select

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

$auction_id = createAuction($conn, $user_id, $title, $desc, $image_path, $asset_id, $nft_name, $start_bid, $bid_pid ?: null, $allowed_pids, $end_date);
if (!$auction_id) { echo json_encode(['success'=>false,'message'=>'Database error creating auction.']); exit; }

// Discord notification
$creator = $_SESSION['userData']['name'] ?? 'Unknown';
$cur_label = $bid_pid ? '' : 'Any currency';
if ($bid_pid) {
    $pr = $conn->query("SELECT name FROM projects WHERE id=".intval($bid_pid)." LIMIT 1");
    if ($pr && $pr->num_rows) { $prow = $pr->fetch_assoc(); $cur_label = $prow['name']; }
}
$end_fmt = (new DateTime($end_date, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Chicago'))->format('M j, Y');
discordmsg(
    '🔨 New Auction: ' . $title,
    "**" . $creator . "** listed a new auction!\n" .
    "Starting Bid: **" . number_format($start_bid) . ($cur_label ? ' ' . strtoupper($cur_label) : ' pts') . "**\n" .
    "Ends: **" . $end_fmt . "**\n" .
    ($desc ? "\n" . mb_substr($desc, 0, 150) . (mb_strlen($desc) > 150 ? '…' : '') : ''),
    $image_path ? 'https://skulliance.io/staking/' . $image_path : '',
    'https://skulliance.io/staking/auctions.php',
    'auctions', '', '00c8a0'
);

$conn->close();
echo json_encode(['success'=>true,'auction_id'=>$auction_id]);
