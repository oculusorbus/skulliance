<?php
include '../db.php';

header('Content-Type: application/json');

$metric = isset($_GET['metric']) ? trim($_GET['metric']) : 'stakers';
$start  = isset($_GET['start'])  ? trim($_GET['start'])  : '';
$end    = isset($_GET['end'])    ? trim($_GET['end'])    : '';

// Validate date format
if ($start && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = '';
if ($end   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = '';

// Metrics: [table, date_col, extra_where, aggregate]
$metrics = [
    'stakers'      => ['users',        'date_created', '',                                                'COUNT(*)'],
    'nfts'         => ['nfts',         'created_date',  "AND user_id != 0",                              'COUNT(*)'],
    'wallets'      => ['wallets',      'date_created', '',                                                'COUNT(*)'],
    'realms'       => ['realms',       'created_date',  '',                                               'COUNT(*)'],
    'rewards'      => ['transactions', 'date_created', "AND bonus = 1",                                   'COUNT(*)'],
    'missions'     => ['missions',     'created_date',  '',                                               'COUNT(*)'],
    'raids'        => ['raids',        'created_date',  '',                                               'COUNT(*)'],
    'skullswap'    => ['scores',       'date_created', "AND project_id = 0",                              'COALESCE(SUM(attempts),0)'],
    'monstrocity'  => ['scores',       'date_created', "AND project_id = 36",                             'COALESCE(SUM(attempts),0)'],
    'bossbattles'  => ['encounters',   'date_created', '',                                                'COUNT(*)'],
    'upgrades'     => ['transactions', 'date_created', "AND location_id IS NOT NULL AND location_id > 0", 'COUNT(*)'],
    'crafting'     => ['transactions', 'date_created', "AND crafting = 1",                                'COUNT(*)'],
    'store'        => ['transactions', 'date_created', "AND item_id IS NOT NULL AND item_id > 0",         'COUNT(*)'],
    'transactions' => ['transactions', 'date_created', '',                                                'COUNT(*)'],
];

if (!array_key_exists($metric, $metrics)) {
    echo json_encode(['labels' => [], 'data' => [], 'error' => 'Invalid metric']);
    exit;
}

[$table, $date_col, $extra_where, $aggregate] = $metrics[$metric];

// Determine granularity
$all_time  = (!$start && !$end);
$diff_days = 9999;
if ($start && $end) {
    $diff_days = max(1, (strtotime($end) - strtotime($start)) / 86400);
} elseif ($start) {
    $diff_days = max(1, (time() - strtotime($start)) / 86400);
}

$fmt = ($all_time || $diff_days > 365) ? '%Y-%m' : '%Y-%m-%d';

// Build date filter
$date_filter = '';
if ($start) $date_filter .= " AND DATE($date_col) >= '" . $conn->real_escape_string($start) . "'";
if ($end)   $date_filter .= " AND DATE($date_col) <= '" . $conn->real_escape_string($end) . "'";

$sql = "SELECT DATE_FORMAT($date_col, '$fmt') AS period, $aggregate AS total
        FROM `$table`
        WHERE 1=1 $extra_where $date_filter
        GROUP BY period
        ORDER BY period ASC";

$result = $conn->query($sql);
$labels = [];
$data   = [];
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $labels[] = $row['period'];
        $data[]   = intval($row['total']);
    }
}

$conn->close();
echo json_encode(['labels' => $labels, 'data' => $data]);
