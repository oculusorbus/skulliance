<?php
include '../db.php';
include '../message.php';
include '../verify.php';
include '../webhooks.php';
header('Content-Type: application/json');

if (!isset($_SESSION['userData']['user_id'])) { echo json_encode(['success'=>false,'message'=>'Not logged in.']); exit; }

$user_id   = intval($_SESSION['userData']['user_id']);
$title     = trim($_POST['title'] ?? '');
$desc      = trim($_POST['description'] ?? '');
$nft_name  = trim($_POST['nft_name'] ?? '');
$asset_id  = trim($_POST['asset_id'] ?? '');
$tpid      = intval($_POST['ticket_project_id'] ?? 0);
$tprice    = floatval($_POST['ticket_price'] ?? 0);
$max_t     = $_POST['max_tickets'] !== '' ? intval($_POST['max_tickets']) : null;
$per_u     = $_POST['tickets_per_user'] !== '' ? intval($_POST['tickets_per_user']) : null;
$end_date_raw = trim($_POST['end_date'] ?? '');

if (!$title) { echo json_encode(['success'=>false,'message'=>'Title is required.']); exit; }
if (!$tpid) { echo json_encode(['success'=>false,'message'=>'Ticket currency is required.']); exit; }
if ($tprice < 1) { echo json_encode(['success'=>false,'message'=>'Ticket price must be at least 1.']); exit; }
if (!$end_date_raw) { echo json_encode(['success'=>false,'message'=>'End date is required.']); exit; }
if ($asset_id && !preg_match('/^[0-9a-fA-F]{56,120}$/', $asset_id)) {
    echo json_encode(['success'=>false,'message'=>'Invalid asset ID format.']); exit;
}

// Normalize end_date to midnight CST
$dt = new DateTime($end_date_raw, new DateTimeZone('America/Chicago'));
$dt->setTime(0, 0, 0);
$dt->setTimezone(new DateTimeZone('UTC'));
$end_date = $dt->format('Y-m-d H:i:s');

if (strtotime($end_date) <= time()) { echo json_encode(['success'=>false,'message'=>'End date must be in the future.']); exit; }

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
    $dir = __DIR__ . '/../images/raffles/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    if (class_exists('Imagick') && $mime !== 'image/gif') {
        $imagick = new Imagick();
        $imagick->readImageBlob(file_get_contents($file['tmp_name']));
        if ($imagick->getImageWidth() > 1000) $imagick->resizeImage(1000, 0, Imagick::FILTER_LANCZOS, 1);
        $imagick->setImageFormat('png');
        $ext = 'png';
        $fname = uniqid('raffle_', true) . '.' . $ext;
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
        $fname = uniqid('raffle_', true) . '.gif';
        $imagick->writeImages($dir . $fname, true);
        $imagick->clear(); $imagick->destroy();
        $ext = 'gif';
    } else {
        $fname = uniqid('raffle_', true) . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $fname);
    }
    $image_path = 'images/raffles/' . $fname;
}

$raffle_id = createRaffle($conn, $user_id, $title, $desc, $image_path, $asset_id, $nft_name, $tprice, $tpid, $max_t, $per_u, $end_date);
if (!$raffle_id) { echo json_encode(['success'=>false,'message'=>'Database error creating raffle.']); exit; }

// Discord notification
$creator = $_SESSION['userData']['name'] ?? 'Unknown';
$pr = $conn->query("SELECT name, currency FROM projects WHERE id=".intval($tpid)." LIMIT 1");
$cur = 'pts'; $proj_name = '';
if ($pr && $pr->num_rows) { $row = $pr->fetch_assoc(); $cur = strtoupper($row['currency']); $proj_name = $row['name']; }
$end_fmt = (new DateTime($end_date, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Chicago'))->format('M j, Y');
$max_label = $max_t ? " (max $max_t tickets)" : '';
discordmsg(
    '🎟️ New Raffle: ' . $title,
    "**" . $creator . "** started a new raffle!\n" .
    "Ticket Price: **" . number_format($tprice) . " $cur**$max_label\n" .
    "Ends: **" . $end_fmt . "**\n" .
    ($desc ? "\n" . mb_substr($desc, 0, 150) . (mb_strlen($desc) > 150 ? '…' : '') : ''),
    $image_path ? 'https://skulliance.io/staking/' . $image_path : '',
    'https://skulliance.io/staking/raffles.php',
    'raffles', '', 'a040ff'
);

$conn->close();
echo json_encode(['success'=>true,'raffle_id'=>$raffle_id]);
