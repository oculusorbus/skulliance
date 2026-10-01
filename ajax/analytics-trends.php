<?php
include '../db.php';

header('Content-Type: application/json');

$metric = isset($_GET['metric']) ? trim($_GET['metric']) : 'stakers';
$start  = isset($_GET['start'])  ? trim($_GET['start'])  : '';
$end    = isset($_GET['end'])    ? trim($_GET['end'])    : '';

// Validate date format
if ($start && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $start)) $start = '';
if ($end   && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $end))   $end   = '';

/*
 * Metrics: [from, date_col, extra_where, aggregate]
 *
 * `from` is a raw FROM clause rather than a bare table name -- Gauntlets
 * counts resolved encounters, which needs an alias. Nothing in this map comes
 * from the request: $metric is checked against its keys before any of it
 * reaches a query, so these strings are as fixed as the SQL around them.
 *
 * EVERY GAME ROW MIRRORS THE ACTIVITY LEADERBOARD. db.php's $sources (search
 * for "Run each source as its own fast GROUP BY query") is the one place that
 * decides what counts as a play for each game -- completed delves only, one
 * row per fallen siege, attempts rather than rows for the two match-3s. The
 * rows below use the same table and the same WHERE clause so a trend line and
 * a board can never tell different stories about the same game. Change one,
 * change the other.
 *
 * Date columns are NOT uniform and are not guessable: skull_racer_runs uses
 * created_at, gauntlets_encounters uses resolved_date, the DHC tables use
 * created_at / started_at / awarded_at, and the older platform tables are
 * split between date_created and created_date. Each is taken from that
 * table's own CREATE in the code (racer in db.php, obscura in obscura-lib.php,
 * guardians in guardians-lib.php, the DHC pair in dhcarena-schema.md /
 * dhcfighters-schema.md) or from the Activity board's per-source filters.
 *
 * NOT HERE: Drop Ship and Oculus Lounge. They live in their own database
 * (dropShipDbConnection() in db.php) and their `results` table has no date
 * column at all -- a run is filed against a game round, not a timestamp -- so
 * there is nothing to plot a trend against. Their totals are on the cards
 * above instead. Giving them a trend needs a schema change over there first.
 */
$metrics = [
    // Platform
    'transactions'  => ['transactions',           'date_created',     '',                                                'COUNT(*)'],
    'stakers'       => ['users',                  'date_created',     '',                                                'COUNT(*)'],
    'nfts'          => ['nfts',                   'created_date',     "AND user_id != 0",                                'COUNT(*)'],
    'wallets'       => ['wallets',                'date_created',     '',                                                'COUNT(*)'],
    'rewards'       => ['transactions',           'date_created',     "AND bonus = 1",                                   'COUNT(*)'],
    // Realms and missions
    'realms'        => ['realms',                 'created_date',     '',                                                'COUNT(*)'],
    'missions'      => ['missions',               'created_date',     '',                                                'COUNT(*)'],
    'raids'         => ['raids',                  'created_date',     '',                                                'COUNT(*)'],
    'upgrades'      => ['transactions',           'date_created',     "AND location_id IS NOT NULL AND location_id > 0", 'COUNT(*)'],
    'crafting'      => ['transactions',           'date_created',     "AND crafting = 1",                                'COUNT(*)'],
    'store'         => ['transactions',           'date_created',     "AND item_id IS NOT NULL AND item_id > 0",         'COUNT(*)'],
    // Games
    'skullswap'     => ['scores',                 'date_created',     "AND project_id = 0",                              'COALESCE(SUM(attempts),0)'],
    'monstrocity'   => ['scores',                 'date_created',     "AND project_id = 36",                             'COALESCE(SUM(attempts),0)'],
    'bossbattles'   => ['encounters',             'date_created',     '',                                                'COUNT(*)'],
    'cryptcrawl'    => ['cryptcrawls',            'date_created',     "AND status IN ('won','lost')",                    'COUNT(*)'],
    'cryptconquest' => ['cryptconquests',         'date_created',     "AND status IN ('won','lost')",                    'COUNT(*)'],
    'gauntlets'     => ['gauntlets_encounters ge','ge.resolved_date', "AND ge.outcome != 'pending'",                     'COUNT(*)'],
    'skullracer'    => ['skull_racer_runs',       'created_at',       '',                                                'COUNT(*)'],
    'obscura'       => ['obscura_scores',         'date_created',     "AND active = 0",                                  'COUNT(*)'],
    'guardians'     => ['guardians_scores',       'date_created',     '',                                                'COUNT(*)'],
    'dhcarena'      => ['dhc_arena_battles',      'started_at',       '',                                                'COUNT(*)'],
    'dhcfighters'   => ['dhc_fighters',           'created_at',       '',                                                'COUNT(*)'],
    'dhctraits'     => ['dhc_trait_drops',        'awarded_at',       '',                                                'COUNT(*)'],
];

if (!array_key_exists($metric, $metrics)) {
    echo json_encode(['labels' => [], 'data' => [], 'error' => 'Invalid metric']);
    exit;
}

[$from, $date_col, $extra_where, $aggregate] = $metrics[$metric];

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
        FROM $from
        WHERE 1=1 $extra_where $date_filter
        GROUP BY period
        ORDER BY period ASC";

$result = $conn->query($sql);
$labels = [];
$data   = [];
$error  = null;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        // A row whose date column is NULL groups under a NULL period. That is
        // history from before a table got its date column (see db.php's note
        // on adding one nullable so the back catalogue stays NULL) and it has
        // no place on a timeline, so it is dropped rather than drawn at the
        // left edge as if it all happened at once.
        if ($row['period'] === null) continue;
        $labels[] = $row['period'];
        $data[]   = intval($row['total']);
    }
} else {
    // A failed query used to be indistinguishable from a game nobody played:
    // both drew an empty chart. A missing column or a renamed table is the
    // likeliest cause and it should say so, not look like zero interest.
    $error = 'Query failed for metric ' . $metric;
    error_log('analytics-trends: ' . $conn->error . ' -- ' . $sql);
}

$conn->close();
$out = ['labels' => $labels, 'data' => $data];
if ($error) $out['error'] = $error;
echo json_encode($out);
