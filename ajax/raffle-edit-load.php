<?php
ob_start();
header('Content-Type: application/json');
include '../db.php';

function json_exit($data) { ob_clean(); echo json_encode($data); exit; }

if (!isset($_SESSION['userData']['user_id'])) { json_exit(['success'=>false,'message'=>'Not logged in.']); }

$user_id   = intval($_SESSION['userData']['user_id']);
$raffle_id = intval($_GET['id'] ?? 0);
if (!$raffle_id) { json_exit(['success'=>false,'message'=>'Invalid raffle ID.']); }

$raffle = getRaffle($conn, $raffle_id);
if (!$raffle) { json_exit(['success'=>false,'message'=>'Raffle not found.']); }
if (intval($raffle['user_id']) !== $user_id) { json_exit(['success'=>false,'message'=>'Not authorized.']); }

$tz_chicago = new DateTimeZone('America/Chicago');
$tz_utc     = new DateTimeZone('UTC');

$start_fmt = '';
if (!empty($raffle['start_date'])) {
    $ds = new DateTime($raffle['start_date'], $tz_utc);
    $ds->setTimezone($tz_chicago);
    $start_fmt = $ds->format('Y-m-d\TH:i');
}

$end_fmt = '';
if (!empty($raffle['end_date'])) {
    $de = new DateTime($raffle['end_date'], $tz_utc);
    $de->setTimezone($tz_chicago);
    $end_fmt = $de->format('Y-m-d\TH:i');
}

$ticket_options = [];
foreach ($raffle['ticket_options'] as $opt) {
    $ticket_options[] = ['project_id' => intval($opt['project_id']), 'cost' => intval($opt['cost'])];
}

json_exit([
    'success'        => true,
    'title'          => $raffle['title'],
    'description'    => $raffle['description'],
    'asset_id'       => $raffle['asset_id'],
    'start_date'     => $start_fmt,
    'end_date'       => $end_fmt,
    'image_path'     => $raffle['image_path'],
    'ticket_options' => $ticket_options,
]);
