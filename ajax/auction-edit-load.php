<?php
ob_start();
header('Content-Type: application/json');
include '../db.php';

function json_exit($data) { ob_clean(); echo json_encode($data); exit; }

if (!isset($_SESSION['userData']['user_id'])) { json_exit(['success'=>false,'message'=>'Not logged in.']); }

$user_id    = intval($_SESSION['userData']['user_id']);
$auction_id = intval($_GET['id'] ?? 0);
if (!$auction_id) { json_exit(['success'=>false,'message'=>'Invalid auction ID.']); }

$auction = getAuction($conn, $auction_id);
if (!$auction) { json_exit(['success'=>false,'message'=>'Auction not found.']); }
if (intval($auction['user_id']) !== $user_id) { json_exit(['success'=>false,'message'=>'Not authorized.']); }
if (!empty($auction['current_bidder_id'])) { json_exit(['success'=>false,'message'=>'Cannot edit: this auction already has bids.']); }

$tz_chicago = new DateTimeZone('America/Chicago');
$tz_utc     = new DateTimeZone('UTC');

$start_fmt = '';
if (!empty($auction['start_date'])) {
    $ds = new DateTime($auction['start_date'], $tz_utc);
    $ds->setTimezone($tz_chicago);
    $start_fmt = $ds->format('Y-m-d\TH:i');
}

$end_fmt = '';
if (!empty($auction['end_date'])) {
    $de = new DateTime($auction['end_date'], $tz_utc);
    $de->setTimezone($tz_chicago);
    $end_fmt = $de->format('Y-m-d\TH:i');
}

$projects = [];
foreach ($auction['allowed_projects'] as $p) {
    $projects[] = ['project_id' => intval($p['project_id']), 'minimum_bid' => intval($p['minimum_bid'])];
}

json_exit([
    'success'    => true,
    'title'      => $auction['title'],
    'description'=> $auction['description'],
    'asset_id'   => $auction['asset_id'],
    'start_date' => $start_fmt,
    'end_date'   => $end_fmt,
    'image_path' => $auction['image_path'],
    'projects'   => $projects,
]);
