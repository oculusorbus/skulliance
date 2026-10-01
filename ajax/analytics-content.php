<?php
include '../db.php';

function ana_stat($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) return 0;
    $row = $r->fetch_row();
    return intval($row[0]);
}
function ana_fmt($n) {
    $n = intval($n);
    if ($n >= 1000000) return number_format(round($n / 1000000, 1)) . 'M';
    if ($n >= 10000)   return round($n / 1000, 1) . 'K';
    return number_format($n);
}
function ana_pct($part, $total) {
    if (intval($total) == 0) return 0;
    return round(intval($part) / intval($total) * 100);
}
// Same shape as ana_stat() but keeps the decimals. Lap and race times are
// floats and intval() would turn a 95.42 second record into "95".
function ana_stat_f($conn, $sql) {
    $r = $conn->query($sql);
    if (!$r) return 0.0;
    $row = $r->fetch_row();
    return floatval($row[0]);
}

$tz     = new DateTimeZone('America/Chicago');
$now_dt = new DateTime('now', $tz);
$updated = $now_dt->format('M j, Y g:i A') . ' CST';
$mf = "DATE_FORMAT(CURDATE(),'%Y-%m-01')";

// ── Community ─────────────────────────────────────────────────────
$stakers       = ana_stat($conn, "SELECT COUNT(*) FROM users");
$nfts_staked   = ana_stat($conn, "SELECT COUNT(*) FROM nfts WHERE user_id != 0");
$wallets       = ana_stat($conn, "SELECT COUNT(*) FROM wallets");
$active_realms = ana_stat($conn, "SELECT COUNT(*) FROM realms WHERE active = 1");
$collections   = ana_stat($conn, "SELECT COUNT(*) FROM collections");

// ── Raids ─────────────────────────────────────────────────────────
$raids_all     = ana_stat($conn, "SELECT COUNT(*) FROM raids");
$raids_month   = ana_stat($conn, "SELECT COUNT(*) FROM raids WHERE DATE(created_date) >= $mf");
$raids_active  = ana_stat($conn, "SELECT COUNT(*) FROM raids WHERE outcome = 0");
$raids_success = ana_stat($conn, "SELECT COUNT(*) FROM raids WHERE outcome = 1");
$raids_done    = ana_stat($conn, "SELECT COUNT(*) FROM raids WHERE outcome != 0");

// ── Missions ──────────────────────────────────────────────────────
$missions_all     = ana_stat($conn, "SELECT COUNT(*) FROM missions");
$missions_month   = ana_stat($conn, "SELECT COUNT(*) FROM missions WHERE DATE(created_date) >= $mf");
$missions_active  = ana_stat($conn, "SELECT COUNT(*) FROM missions WHERE status = 0");
$missions_success = ana_stat($conn, "SELECT COUNT(*) FROM missions WHERE status = 1");
$missions_done    = ana_stat($conn, "SELECT COUNT(*) FROM missions WHERE status IN (1,2)");

// ── Daily Rewards ─────────────────────────────────────────────────
$claims_all      = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE bonus = 1");
$claims_month    = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE bonus = 1 AND DATE(date_created) >= $mf");
$claims_pace_pct = ($claims_all > 0 && $claims_month > 0)
    ? min(100, ana_pct($claims_month * 12, $claims_all)) : 0;

// ── Factions ──────────────────────────────────────────────────────
$factions_active = ana_stat($conn, "SELECT COUNT(DISTINCT project_id) FROM realms WHERE active = 1");
$faction_res = $conn->query(
    "SELECT p.name, p.currency, COUNT(r.id) AS realm_count
     FROM realms r
     INNER JOIN projects p ON p.id = r.project_id
     WHERE r.active = 1
     GROUP BY r.project_id
     ORDER BY realm_count DESC"
);
$faction_rows = [];
if ($faction_res) while ($fr = $faction_res->fetch_assoc()) $faction_rows[] = $fr;

// ── Boss Battles ──────────────────────────────────────────────────
$boss_all  = ana_stat($conn, "SELECT COUNT(*) FROM encounters");
$boss_week = ana_stat($conn, "SELECT COUNT(*) FROM encounters WHERE reward = 0");
$boss_dmg  = ana_stat($conn, "SELECT COALESCE(SUM(damage_dealt),0) FROM encounters");

// ── Skull Swap ────────────────────────────────────────────────────
$swap_all  = ana_stat($conn, "SELECT COALESCE(SUM(attempts),0) FROM scores WHERE project_id = 0");
$swap_week = ana_stat($conn, "SELECT COALESCE(SUM(attempts),0) FROM scores WHERE project_id = 0 AND reward = 0");

// ── Monstrocity ───────────────────────────────────────────────────
$mono_all   = ana_stat($conn, "SELECT COALESCE(SUM(attempts),0) FROM scores WHERE project_id = 36");
$mono_month = ana_stat($conn, "SELECT COALESCE(SUM(attempts),0) FROM scores WHERE project_id = 36 AND DATE(date_created) >= $mf");

/*
 * ── The rest of the games ─────────────────────────────────────────
 *
 * Every count below uses the SAME definition of a play as the Activity
 * leaderboard in db.php (its $sources array) and as the trend chart in
 * ajax/analytics-trends.php: completed runs only for the solo games, resolved
 * encounters for Gauntlets, one row per fallen siege for Guardians. Three
 * places, one definition - if a game's definition changes, all three move.
 *
 * Date columns differ per table and are not guessable (created_at for the
 * racer and for Fighters, started_at for Arena, resolved_date for Gauntlet
 * encounters, date_created for the rest). Each one is taken from that table's
 * own CREATE in the code, not assumed.
 */

// ── Crypt Crawl ───────────────────────────────────────────────────
$crawl_all   = ana_stat($conn, "SELECT COUNT(*) FROM cryptcrawls WHERE status IN ('won','lost')");
$crawl_month = ana_stat($conn, "SELECT COUNT(*) FROM cryptcrawls WHERE status IN ('won','lost') AND DATE(date_created) >= $mf");
$crawl_won   = ana_stat($conn, "SELECT COUNT(*) FROM cryptcrawls WHERE status = 'won'");

// ── Crypt Conquest ────────────────────────────────────────────────
$conq_all   = ana_stat($conn, "SELECT COUNT(*) FROM cryptconquests WHERE status IN ('won','lost')");
$conq_month = ana_stat($conn, "SELECT COUNT(*) FROM cryptconquests WHERE status IN ('won','lost') AND DATE(date_created) >= $mf");
$conq_won   = ana_stat($conn, "SELECT COUNT(*) FROM cryptconquests WHERE status = 'won'");

// ── Realm Guardians ───────────────────────────────────────────────
// Every row is a fallen realm -- there is no win condition -- so the count is
// sieges played, and `held` is waves survived past the wave you started on.
$grd_all   = ana_stat($conn, "SELECT COUNT(*) FROM guardians_scores");
$grd_month = ana_stat($conn, "SELECT COUNT(*) FROM guardians_scores WHERE DATE(date_created) >= $mf");
$grd_held  = ana_stat($conn, "SELECT COALESCE(SUM(held),0) FROM guardians_scores");

// ── Skull Racer ───────────────────────────────────────────────────
// Only finished, sanity-checked races are ever inserted, so there is no
// status filter to apply -- the table has nothing else in it.
$racer_all   = ana_stat($conn,   "SELECT COUNT(*) FROM skull_racer_runs");
$racer_month = ana_stat($conn,   "SELECT COUNT(*) FROM skull_racer_runs WHERE DATE(created_at) >= $mf");
$racer_best  = ana_stat_f($conn, "SELECT COALESCE(MIN(fastest_lap),0) FROM skull_racer_runs");

// ── Obscura ───────────────────────────────────────────────────────
// Runs, not solves, for the card's headline -- a solve is a ~15 second unit
// and counting those would dwarf every other game here. Solves go in the note.
$obs_runs   = ana_stat($conn, "SELECT COUNT(*) FROM obscura_scores WHERE active = 0");
$obs_month  = ana_stat($conn, "SELECT COUNT(*) FROM obscura_scores WHERE active = 0 AND DATE(date_created) >= $mf");
$obs_solves = ana_stat($conn, "SELECT COALESCE(SUM(solves),0) FROM obscura_scores");

// ── Gauntlets ─────────────────────────────────────────────────────
$gaunt_all   = ana_stat($conn, "SELECT COUNT(*) FROM gauntlets_encounters WHERE outcome != 'pending'");
$gaunt_month = ana_stat($conn, "SELECT COUNT(*) FROM gauntlets_encounters WHERE outcome != 'pending' AND DATE(resolved_date) >= $mf");
$gaunt_runs  = ana_stat($conn, "SELECT COUNT(*) FROM gauntlets");

// ── DHC Fighters ──────────────────────────────────────────────────
// Assembled Fighters currently standing -- a disassembled one is removed from
// the table, so this is a population, not a lifetime total. The trait ledger
// IS append-only, which is why the note carries it.
$dhcf_all   = ana_stat($conn, "SELECT COUNT(*) FROM dhc_fighters");
$dhcf_month = ana_stat($conn, "SELECT COUNT(*) FROM dhc_fighters WHERE DATE(created_at) >= $mf");
$dhct_all   = ana_stat($conn, "SELECT COUNT(*) FROM dhc_trait_drops");

// ── DHC Arena ─────────────────────────────────────────────────────
$dhca_all   = ana_stat($conn, "SELECT COUNT(*) FROM dhc_arena_battles");
$dhca_month = ana_stat($conn, "SELECT COUNT(*) FROM dhc_arena_battles WHERE DATE(started_at) >= $mf");
$dhca_done  = ana_stat($conn, "SELECT COUNT(*) FROM dhc_arena_battles WHERE outcome != 0");

/*
 * ── Drop Ship and Oculus Lounge ───────────────────────────────────
 *
 * Their own database, reached through db.php's dropShipDbConnection() (the
 * same one the leaderboard snapshots use). Two reasons the shape is different
 * from every card above:
 *
 *  1. `results` has NO date column. A run is filed against a game ROUND, not
 *     a timestamp, so "this month" cannot be asked -- the comparable period is
 *     the round that is running right now, which is exactly what Drop Ship's
 *     own current board shows. That is also why neither game appears in the
 *     trend chart.
 *  2. One row per player per round, replaced when they beat it (see
 *     deleteResult() in dropship/db.php), so a count is best runs recorded,
 *     not every attempt ever made.
 *
 * A down or missing Drop Ship database leaves $ds_ok false and the whole
 * section is skipped rather than rendering a row of zeros, which would read
 * as "nobody plays it".
 */
$ds_ok = false;
$ds_games = array();
$ds_conn = function_exists('dropShipDbConnection') ? dropShipDbConnection() : null;
if ($ds_conn) {
    $ds_ok = true;
    foreach (array(
        DROPSHIP_PROJECT_DROPSHIP => array('Drop Ship',     '🪖'),
        DROPSHIP_PROJECT_LOUNGE   => array('Oculus Lounge', '🪩'),
    ) as $ds_pid => $ds_meta) {
        $ds_pid  = intval($ds_pid);
        $gr      = $ds_conn->query("SELECT id FROM games WHERE active = 1 AND project_id = $ds_pid LIMIT 1");
        $game_id = ($gr && $gr->num_rows > 0) ? intval($gr->fetch_assoc()['id']) : 0;
        $ds_games[] = array(
            'name'    => $ds_meta[0],
            'icon'    => $ds_meta[1],
            'round'   => $game_id ? ana_stat($ds_conn, "SELECT COUNT(*) FROM results WHERE project_id = $ds_pid AND game_id = $game_id") : 0,
            'live'    => $game_id > 0,
            'all'     => ana_stat($ds_conn, "SELECT COUNT(*) FROM results WHERE project_id = $ds_pid"),
            'players' => ana_stat($ds_conn, "SELECT COUNT(DISTINCT user_id) FROM results WHERE project_id = $ds_pid"),
        );
    }
}

// ── Economy ───────────────────────────────────────────────────────
$total_trans      = ana_stat($conn, "SELECT COUNT(*) FROM transactions");
$total_credits    = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE type = 'credit'");
$total_debits     = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE type = 'debit'");
$items_bought     = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE item_id IS NOT NULL AND item_id > 0");
$crafting_trans   = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE crafting = 1");
$crafting_credits = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE crafting = 1 AND type = 'credit'");
$crafting_debits  = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE crafting = 1 AND type = 'debit'");
$mission_trans    = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE mission_id IS NOT NULL AND mission_id > 0");
$mission_credits  = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE mission_id IS NOT NULL AND mission_id > 0 AND type = 'credit'");
$mission_debits   = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE mission_id IS NOT NULL AND mission_id > 0 AND type = 'debit'");
$raid_trans       = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE raid_id IS NOT NULL AND raid_id > 0");
$raid_credits     = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE raid_id IS NOT NULL AND raid_id > 0 AND type = 'credit'");
$raid_debits      = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE raid_id IS NOT NULL AND raid_id > 0 AND type = 'debit'");
$upgrade_trans    = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE location_id IS NOT NULL AND location_id > 0");

// ── Diamond Skull Delegations ─────────────────────────────────────
$diamonds = ana_stat($conn, "SELECT COUNT(*) FROM diamond_skulls");
$diamond_proj_res = $conn->query(
    "SELECT p.id, p.name, p.currency, COUNT(ds.id) AS delegation_count
     FROM projects p
     INNER JOIN collections c ON c.project_id = p.id
     INNER JOIN nfts n ON n.collection_id = c.id
     INNER JOIN diamond_skulls ds ON ds.nft_id = n.id
     WHERE p.id <= 6
     GROUP BY p.id
     ORDER BY delegation_count DESC"
);
$diamond_projs = [];
if ($diamond_proj_res) while ($dr = $diamond_proj_res->fetch_assoc()) $diamond_projs[] = $dr;

// ── Projects ──────────────────────────────────────────────────────
$proj_res = $conn->query(
    "SELECT p.id, p.name, p.currency, COUNT(n.id) AS nft_count
     FROM projects p
     LEFT JOIN collections c ON c.project_id = p.id
     LEFT JOIN nfts n ON n.collection_id = c.id AND n.user_id != 0
     WHERE p.id NOT IN (7, 15)
     GROUP BY p.id
     ORDER BY nft_count DESC, p.id"
);
$core_projs    = [];
$partner_projs = [];
if ($proj_res) {
    while ($pr = $proj_res->fetch_assoc()) {
        if ($pr['id'] <= 6) $core_projs[]    = $pr;
        else                $partner_projs[] = $pr;
    }
}
$total_projects = count($core_projs) + count($partner_projs);

$conn->close();
?>

<div class="ana-hero">
    <h1><span>Skulliance</span> Analytics</h1>
    <div class="ana-hero-right">
        <div class="ana-updated">Updated: <strong><?php echo $updated; ?></strong></div>
        <div class="ana-tagline">Public engagement report - transparency is a feature</div>
    </div>
</div>

<!-- ── Community ── -->
<div class="ana-section-label">Community</div>
<div class="ana-row ana-row-5">

    <div class="ana-card ana-card-accent-top">
        <div class="ana-stat-label">Stakers</div>
        <div class="ana-stat-value"><?php echo ana_fmt($stakers); ?></div>
        <div class="ana-stat-sub">Registered users</div>
    </div>

    <div class="ana-card ana-card-accent-top">
        <div class="ana-stat-label">NFTs Staked</div>
        <div class="ana-stat-value"><?php echo ana_fmt($nfts_staked); ?></div>
        <div class="ana-stat-sub">Across <?php echo $total_projects; ?> projects</div>
    </div>

    <div class="ana-card ana-card-accent-top">
        <div class="ana-stat-label">Wallets Connected</div>
        <div class="ana-stat-value"><?php echo ana_fmt($wallets); ?></div>
        <div class="ana-stat-sub">Cardano addresses</div>
    </div>

    <div class="ana-card ana-card-accent-top">
        <div class="ana-stat-label">Active Realms</div>
        <div class="ana-stat-value"><?php echo ana_fmt($active_realms); ?></div>
        <div class="ana-stat-sub">PvP kingdoms active</div>
    </div>

    <div class="ana-card ana-card-accent-top">
        <div class="ana-stat-label">NFT Collections</div>
        <div class="ana-stat-value"><?php echo ana_fmt($collections); ?></div>
        <div class="ana-stat-sub">Across all projects</div>
    </div>

</div>

<!-- ── Engagement ── -->
<div class="ana-section-label">Engagement</div>
<div class="ana-row ana-row-3">

    <div class="ana-card">
        <div class="ana-dual-title">🪙 Daily Reward Claims</div>
        <div class="ana-dual-grid">
            <div class="ana-period">
                <div class="ana-period-label">This Month</div>
                <div class="ana-period-value"><?php echo ana_fmt($claims_month); ?></div>
                <div class="ana-period-sub">claims</div>
            </div>
            <div class="ana-period">
                <div class="ana-period-label">All Time</div>
                <div class="ana-period-value"><?php echo ana_fmt($claims_all); ?></div>
                <div class="ana-period-sub">total claims</div>
            </div>
        </div>
        <div class="ana-bar-row">
            <span class="ana-bar-label">Monthly vs historical avg</span>
            <div class="ana-bar-track"><div class="ana-bar-fill" style="width:<?php echo $claims_pace_pct; ?>%"></div></div>
            <span class="ana-bar-pct"><?php echo $claims_pace_pct; ?>%</span>
        </div>
    </div>

    <div class="ana-card">
        <div class="ana-dual-title">🎒 Missions</div>
        <div class="ana-dual-grid">
            <div class="ana-period">
                <div class="ana-period-label">This Month</div>
                <div class="ana-period-value"><?php echo ana_fmt($missions_month); ?></div>
                <div class="ana-period-sub">started</div>
            </div>
            <div class="ana-period">
                <div class="ana-period-label">All Time</div>
                <div class="ana-period-value"><?php echo ana_fmt($missions_all); ?></div>
                <div class="ana-period-sub"><?php echo ana_fmt($missions_active); ?> in progress</div>
            </div>
        </div>
        <div class="ana-bar-row">
            <span class="ana-bar-label">Success rate</span>
            <div class="ana-bar-track"><div class="ana-bar-fill" style="width:<?php echo ana_pct($missions_success, $missions_done); ?>%"></div></div>
            <span class="ana-bar-pct"><?php echo ana_pct($missions_success, $missions_done); ?>%</span>
        </div>
    </div>

    <div class="ana-card">
        <div class="ana-dual-title">⚔️ Raids</div>
        <div class="ana-dual-grid">
            <div class="ana-period">
                <div class="ana-period-label">This Month</div>
                <div class="ana-period-value"><?php echo ana_fmt($raids_month); ?></div>
                <div class="ana-period-sub">launched</div>
            </div>
            <div class="ana-period">
                <div class="ana-period-label">All Time</div>
                <div class="ana-period-value"><?php echo ana_fmt($raids_all); ?></div>
                <div class="ana-period-sub"><?php echo ana_fmt($raids_active); ?> in progress</div>
            </div>
        </div>
        <div class="ana-bar-row">
            <span class="ana-bar-label">Offense win rate</span>
            <div class="ana-bar-track"><div class="ana-bar-fill" style="width:<?php echo ana_pct($raids_success, $raids_done); ?>%"></div></div>
            <span class="ana-bar-pct"><?php echo ana_pct($raids_success, $raids_done); ?>%</span>
        </div>
    </div>

</div>

<!-- ── Gaming ── -->
<div class="ana-section-label">Gaming <span style="font-size:0.6rem;font-weight:400;color:#7a9eb0;letter-spacing:0;text-transform:none;">Weekly cycles reset Thursday 4pm CST · everything else runs monthly</span></div>
<div class="ana-row ana-row-3">

    <div class="ana-card">
        <div class="ana-game-title">💀 Skull Swap</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Week</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($swap_week); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($swap_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Games played per weekly cycle</div>
    </div>

    <div class="ana-card">
        <div class="ana-game-title">🧟 Monstrocity · Match 3 RPG</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Month</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($mono_month); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($mono_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Sessions played</div>
    </div>

    <div class="ana-card">
        <div class="ana-game-title">⚔️ Boss Battles</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Week</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($boss_week); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($boss_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Total damage dealt: <strong><?php echo ana_fmt($boss_dmg); ?></strong></div>
    </div>

</div>

<div class="ana-row ana-row-3" style="margin-top:11px;">

    <div class="ana-card">
        <div class="ana-game-title">💀 Crypt Crawl · Scoundrel</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Month</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($crawl_month); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($crawl_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Delves completed · <strong><?php echo ana_pct($crawl_won, $crawl_all); ?>%</strong> survived</div>
    </div>

    <div class="ana-card">
        <div class="ana-game-title">👑 Crypt Conquest · Regicide</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Month</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($conq_month); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($conq_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Runs completed · <strong><?php echo ana_pct($conq_won, $conq_all); ?>%</strong> cleared the castle</div>
    </div>

    <div class="ana-card">
        <div class="ana-game-title">🛡️ Realm Guardians · Tower Defense</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Month</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($grd_month); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($grd_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Sieges defended · <strong><?php echo ana_fmt($grd_held); ?></strong> waves held</div>
    </div>

</div>

<div class="ana-row ana-row-3" style="margin-top:11px;">

    <div class="ana-card">
        <div class="ana-game-title">🏁 Skull Racer</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Month</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($racer_month); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($racer_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Races finished<?php if ($racer_best > 0): ?> · best lap <strong><?php echo number_format($racer_best, 2); ?>s</strong><?php endif; ?></div>
    </div>

    <div class="ana-card">
        <div class="ana-game-title">🔍 Obscura · Art Recognition</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Month</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($obs_month); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($obs_runs); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Runs completed · <strong><?php echo ana_fmt($obs_solves); ?></strong> NFTs identified</div>
    </div>

    <div class="ana-card">
        <div class="ana-game-title">🥊 Gauntlets</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Month</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($gaunt_month); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($gaunt_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Encounters resolved across <strong><?php echo ana_fmt($gaunt_runs); ?></strong> runs</div>
    </div>

</div>

<div class="ana-row ana-row-2" style="margin-top:11px;">

    <div class="ana-card">
        <div class="ana-game-title">🧬 DHC Fighters · Trait Assembly</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Month</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($dhcf_month); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">Standing</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($dhcf_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Fighters assembled · <strong><?php echo ana_fmt($dhct_all); ?></strong> traits awarded</div>
    </div>

    <div class="ana-card">
        <div class="ana-game-title">🏟️ DHC Arena · Crew Battles</div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label">This Month</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($dhca_month); ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($dhca_all); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Battles started · <strong><?php echo ana_fmt($dhca_done); ?></strong> fought to a result</div>
    </div>

</div>

<?php if ($ds_ok): ?>
<!-- ── Specialty Games ── -->
<div class="ana-section-label">Specialty Games <span style="font-size:0.6rem;font-weight:400;color:#7a9eb0;letter-spacing:0;text-transform:none;">Same server, own database · scored by game round rather than by date</span></div>
<div class="ana-row ana-row-2">
    <?php foreach ($ds_games as $g): ?>
    <div class="ana-card">
        <div class="ana-game-title"><?php echo $g['icon']; ?> <?php echo htmlspecialchars($g['name']); ?></div>
        <div class="ana-game-grid">
            <div class="ana-game-stat accent">
                <div class="ana-game-stat-label"><?php echo $g['live'] ? 'This Round' : 'No Round Live'; ?></div>
                <div class="ana-game-stat-value"><?php echo $g['live'] ? ana_fmt($g['round']) : '-'; ?></div>
            </div>
            <div class="ana-game-stat">
                <div class="ana-game-stat-label">All Time</div>
                <div class="ana-game-stat-value"><?php echo ana_fmt($g['all']); ?></div>
            </div>
        </div>
        <div class="ana-game-note">Best runs recorded · <strong><?php echo ana_fmt($g['players']); ?></strong> players</div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- ── Projects ── -->
<div class="ana-section-label">Projects</div>
<div class="ana-card">
    <div class="ana-proj-cols">
        <div>
            <div class="ana-proj-title">Core Projects <span><?php echo count($core_projs); ?></span></div>
            <div class="ana-proj-pills">
                <?php foreach ($core_projs as $p): ?>
                <div class="ana-proj-pill">
                    <img src="icons/<?php echo strtolower($p['currency']); ?>.png" onerror="this.style.display='none'">
                    <?php echo htmlspecialchars($p['name']); ?>
                    <strong><?php echo ana_fmt($p['nft_count']); ?> NFTs</strong>
                </div>
                <?php endforeach; ?>
            </div>
            <div class="ana-proj-title" style="margin-top:14px;">💎 Diamond Skull Delegations <span><?php echo ana_fmt($diamonds); ?></span></div>
            <div class="ana-proj-pills">
                <?php foreach ($diamond_projs as $dp): ?>
                <div class="ana-proj-pill">
                    <img src="icons/<?php echo strtolower($dp['currency']); ?>.png" onerror="this.style.display='none'">
                    <?php echo htmlspecialchars($dp['name']); ?>
                    <strong><?php echo ana_fmt($dp['delegation_count']); ?> NFTs</strong>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php if (!empty($partner_projs)): ?>
        <div>
            <div class="ana-proj-title">Partner Projects <span><?php echo count($partner_projs); ?></span></div>
            <div class="ana-proj-pills">
                <?php foreach ($partner_projs as $p): ?>
                <div class="ana-proj-pill">
                    <img src="icons/<?php echo strtolower($p['currency']); ?>.png" onerror="this.style.display='none'">
                    <?php echo htmlspecialchars($p['name']); ?>
                    <?php if (intval($p['nft_count']) > 0): ?>
                    <strong><?php echo ana_fmt($p['nft_count']); ?> NFTs</strong>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <div>
            <div class="ana-proj-title">Factions <span><?php echo $factions_active; ?></span></div>
            <div class="ana-faction-pills">
                <?php
                $max_realms = !empty($faction_rows) ? max(array_column($faction_rows, 'realm_count')) : 1;
                foreach ($faction_rows as $fr):
                    $bar_pct = $max_realms > 0 ? round($fr['realm_count'] / $max_realms * 100) : 0;
                ?>
                <div class="ana-faction-pill">
                    <img src="icons/<?php echo strtolower($fr['currency']); ?>.png" onerror="this.style.display='none'">
                    <span><?php echo htmlspecialchars($fr['name']); ?></span>
                    <div class="ana-faction-bar-track"><div class="ana-faction-bar-fill" style="width:<?php echo $bar_pct; ?>%"></div></div>
                    <span class="ana-faction-pill-count"><?php echo $fr['realm_count']; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Economy ── -->
<div class="ana-section-label">Economy</div>
<div class="ana-card">
    <div class="ana-econ-strip" style="margin-bottom:0;">
        <div class="ana-econ-item">
            <div class="ana-econ-label">Transactions</div>
            <div class="ana-econ-value"><?php echo ana_fmt($total_trans); ?></div>
            <div class="ana-econ-sub">
                <span class="ana-econ-sub-item up">&#9650; <?php echo ana_fmt($total_credits); ?> credits</span>
                <span class="ana-econ-sub-item down">&#9660; <?php echo ana_fmt($total_debits); ?> debits</span>
            </div>
        </div>
        <div class="ana-econ-item">
            <div class="ana-econ-label">Store Claims</div>
            <div class="ana-econ-value"><?php echo ana_fmt($items_bought); ?></div>
            <div class="ana-econ-sub">
                <span class="ana-econ-sub-item down">&#9660; costs only</span>
            </div>
        </div>
        <div class="ana-econ-item">
            <div class="ana-econ-label">Crafting</div>
            <div class="ana-econ-value"><?php echo ana_fmt($crafting_trans); ?></div>
            <div class="ana-econ-sub">
                <span class="ana-econ-sub-item up">&#9650; <?php echo ana_fmt($crafting_credits); ?> credits</span>
                <span class="ana-econ-sub-item down">&#9660; <?php echo ana_fmt($crafting_debits); ?> debits</span>
            </div>
        </div>
        <div class="ana-econ-item">
            <div class="ana-econ-label">Missions</div>
            <div class="ana-econ-value"><?php echo ana_fmt($mission_trans); ?></div>
            <div class="ana-econ-sub">
                <span class="ana-econ-sub-item up">&#9650; <?php echo ana_fmt($mission_credits); ?> rewards</span>
                <span class="ana-econ-sub-item down">&#9660; <?php echo ana_fmt($mission_debits); ?> costs</span>
            </div>
        </div>
        <div class="ana-econ-item">
            <div class="ana-econ-label">Raids</div>
            <div class="ana-econ-value"><?php echo ana_fmt($raid_trans); ?></div>
            <div class="ana-econ-sub">
                <span class="ana-econ-sub-item up">&#9650; <?php echo ana_fmt($raid_credits); ?> rewards</span>
                <span class="ana-econ-sub-item down">&#9660; <?php echo ana_fmt($raid_debits); ?> penalties</span>
            </div>
        </div>
        <div class="ana-econ-item">
            <div class="ana-econ-label">Location Upgrades</div>
            <div class="ana-econ-value"><?php echo ana_fmt($upgrade_trans); ?></div>
            <div class="ana-econ-sub">
                <span class="ana-econ-sub-item down">&#9660; costs only</span>
            </div>
        </div>
    </div>
</div>
