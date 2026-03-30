<?php
ob_start();
header('Content-Type: application/json');
include '../db.php';
include '../webhooks.php';

function json_exit($data) { ob_clean(); echo json_encode($data); exit; }

if (!isset($_SESSION['userData']['user_id'])) { json_exit(['success'=>false,'message'=>'Not logged in.']); }

$user_id         = intval($_SESSION['userData']['user_id']);
$title           = trim($_POST['title'] ?? '');
$desc            = trim($_POST['description'] ?? '');
$asset_id        = trim($_POST['asset_id'] ?? '');
$options_raw     = $_POST['ticket_options'] ?? '[]';
$start_date_raw  = trim($_POST['start_date'] ?? '');
$end_date_raw    = trim($_POST['end_date'] ?? '');

if (!$title)        { json_exit(['success'=>false,'message'=>'Title is required.']); }
if (!$end_date_raw) { json_exit(['success'=>false,'message'=>'End date is required.']); }
if (!$asset_id)     { json_exit(['success'=>false,'message'=>'Cardano Asset ID is required.']); }
if (!preg_match('/^asset1[a-z0-9]{38}$/', $asset_id)) {
    json_exit(['success'=>false,'message'=>'Invalid asset ID — must be in asset1... fingerprint format.']);
}

$ticket_options = json_decode($options_raw, true) ?: [];
$ticket_options = array_values(array_filter($ticket_options, function($o) {
    return intval($o['project_id'] ?? 0) > 0 && intval($o['cost'] ?? 0) > 0;
}));
if (empty($ticket_options)) { json_exit(['success'=>false,'message'=>'At least one ticket currency with a price is required.']); }

// Optional start_date (defaults to now)
if ($start_date_raw) {
    $ds = new DateTime($start_date_raw, new DateTimeZone('America/Chicago'));
    $ds->setTimezone(new DateTimeZone('UTC'));
    $start_date = $ds->format('Y-m-d H:i:s');
} else {
    $start_date = date('Y-m-d H:i:s');
}

// Normalize end_date to midnight CST
$dt = new DateTime($end_date_raw, new DateTimeZone('America/Chicago'));
$dt->setTime(0, 0, 0);
$dt->setTimezone(new DateTimeZone('UTC'));
$end_date = $dt->format('Y-m-d H:i:s');

if (strtotime($end_date) <= time()) { json_exit(['success'=>false,'message'=>'End date must be in the future.']); }

// Image upload
$image_path = '';
if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $file = $_FILES['image'];
    if ($file['size'] > 50 * 1024 * 1024) { json_exit(['success'=>false,'message'=>'Image must be under 50MB.']); }
    $allowed_types = ['image/png','image/gif','image/jpeg','image/webp'];
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($file['tmp_name']);
    if (!in_array($mime, $allowed_types)) { json_exit(['success'=>false,'message'=>'Invalid image type.']); }

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

// IPFS fallback: if no file uploaded, try the auto-fetched IPFS URL
if ($image_path === '') {
    $ipfs_raw = trim($_POST['ipfs_url'] ?? '');
    if ($ipfs_raw !== '') {
        $clean_ipfs = str_replace('ipfs/', '', $ipfs_raw);
        $gateways   = ['https://ipfs5.jpgstoreapis.com/ipfs/', 'https://cloudflare-ipfs.com/ipfs/', 'https://ipfs.io/ipfs/'];
        foreach ($gateways as $gw) {
            $ch = curl_init($gw . $clean_ipfs);
            curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER=>1, CURLOPT_FOLLOWLOCATION=>1, CURLOPT_TIMEOUT=>15,
                CURLOPT_HTTPHEADER=>['User-Agent: Mozilla/5.0']]);
            $body = curl_exec($ch);
            $ct   = curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
            $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($body !== false && $code === 200) {
                $mime = trim(explode(';', $ct)[0]);
                $allowed_types = ['image/png','image/gif','image/jpeg','image/webp'];
                if (in_array($mime, $allowed_types)) {
                    $dir = __DIR__ . '/../images/raffles/';
                    if (!is_dir($dir)) mkdir($dir, 0755, true);
                    if (class_exists('Imagick')) {
                        $imagick = new Imagick();
                        $imagick->readImageBlob($body);
                        if ($imagick->getImageWidth() > 1000) $imagick->resizeImage(1000, 0, Imagick::FILTER_LANCZOS, 1);
                        $imagick->setImageFormat('png');
                        $fname = uniqid('raffle_', true) . '.png';
                        $imagick->writeImage($dir . $fname);
                        $imagick->clear(); $imagick->destroy();
                    } else {
                        $ext_map = ['image/png'=>'png','image/gif'=>'gif','image/jpeg'=>'jpg','image/webp'=>'webp'];
                        $fname = uniqid('raffle_', true) . '.' . ($ext_map[$mime] ?? 'jpg');
                        file_put_contents($dir . $fname, $body);
                    }
                    $image_path = 'images/raffles/' . $fname;
                }
                break;
            }
        }
    }
}

$quantity  = max(1, intval($_POST['quantity'] ?? 1));
$raffle_id = createRaffle($conn, $user_id, $title, $desc, $image_path, $asset_id, $start_date, $end_date, $ticket_options, $quantity);
if (!$raffle_id) { json_exit(['success'=>false,'message'=>'Database error creating raffle.']); }

// Build Discord labels before closing connection
$creator     = $_SESSION['userData']['name'] ?? 'Unknown';
$end_fmt     = (new DateTime($end_date, new DateTimeZone('UTC')))->setTimezone(new DateTimeZone('America/Chicago'))->format('M j, Y');
$opt_labels  = [];
foreach ($ticket_options as $opt) {
    $pr = $conn->query("SELECT name, currency FROM projects WHERE id=".intval($opt['project_id'])." LIMIT 1");
    if ($pr && $pr->num_rows) { $row = $pr->fetch_assoc(); $opt_labels[] = number_format($opt['cost']) . ' ' . strtoupper($row['currency']); }
}
$conn->close();

// Send response to browser immediately, then fire Discord
ob_clean();
echo json_encode(['success'=>true,'raffle_id'=>$raffle_id]);
if (function_exists('fastcgi_finish_request')) fastcgi_finish_request();

discordmsg(
    '🎟️ New Raffle: ' . $title,
    "**$creator** started a new raffle!\n" .
    "Ticket Price: **" . implode(' / ', $opt_labels) . "**\n" .
    "Ends: **$end_fmt**",
    $image_path ? 'https://skulliance.io/staking/' . $image_path : '',
    'https://skulliance.io/staking/raffles.php',
    'raffles', $image_path ? 'https://skulliance.io/staking/' . $image_path : '', 'a040ff'
);
