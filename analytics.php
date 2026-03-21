<?php
include_once 'db.php';
include 'header.php';

// ── Week boundaries (Thursday 4pm CST) ───────────────────────────
$tz = new DateTimeZone('America/Chicago');
$now_dt = new DateTime('now', $tz);
$dow  = (int)$now_dt->format('N'); // 1=Mon … 4=Thu … 7=Sun
$hour = (int)$now_dt->format('G');
if ($dow == 4 && $hour >= 16)   { $days_back = 0; }
elseif ($dow > 4)                { $days_back = $dow - 4; }
else                             { $days_back = $dow + 3; }
$week_start = clone $now_dt;
if ($days_back > 0) $week_start->modify("-{$days_back} days");
$week_start->setTime(16, 0, 0);
$updated = $now_dt->format('M j, Y g:i A') . ' CST';

// ── Helpers ───────────────────────────────────────────────────────
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
$claims_all   = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE bonus = 1");
$claims_month = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE bonus = 1 AND DATE(date_created) >= $mf");
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

// ── Monstrocity (monthly via date_created) ────────────────────────
$mono_all   = ana_stat($conn, "SELECT COALESCE(SUM(attempts),0) FROM scores WHERE project_id = 36");
$mono_month = ana_stat($conn, "SELECT COALESCE(SUM(attempts),0) FROM scores WHERE project_id = 36 AND DATE(date_created) >= $mf");

// ── Economy ───────────────────────────────────────────────────────
$total_trans  = ana_stat($conn, "SELECT COUNT(*) FROM transactions");
$items_bought = ana_stat($conn, "SELECT COUNT(*) FROM transactions WHERE item_id IS NOT NULL AND item_id > 0");
$diamonds     = ana_stat($conn, "SELECT COUNT(*) FROM diamond_skulls");

// ── Projects breakdown ────────────────────────────────────────────
// Core: id 1–6; Partner: id > 7 (exclude 7=Diamond Skulls, 15=Carbon/internal)
$proj_res = $conn->query(
    "SELECT p.id, p.name, p.currency, COUNT(n.id) AS nft_count
     FROM projects p
     LEFT JOIN collections c ON c.project_id = p.id
     LEFT JOIN nfts n ON n.collection_id = c.id AND n.user_id != 0
     WHERE p.id NOT IN (7, 15)
     GROUP BY p.id
     ORDER BY p.id"
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

<style>
/* ── Analytics page ──────────────────────────────────────────── */
.ana-page {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px 32px;
}
.ana-hero {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 20px 0 16px;
    border-bottom: 1px solid rgba(0,200,160,0.15);
    margin-bottom: 4px;
}
.ana-hero h1 {
    font-size: 1.4rem;
    font-weight: 700;
    color: #fff;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    margin: 0;
}
.ana-hero h1 span { color: #00c8a0; }
.ana-hero-right { text-align: right; }
.ana-updated { font-size: 0.72rem; color: #7a9eb0; }
.ana-updated strong { color: #00c8a0; }
.ana-tagline { font-size: 0.7rem; color: rgba(255,255,255,0.25); margin-top: 2px; letter-spacing: 0.03em; }

/* ── Section label ───────────────────────────────────────────── */
.ana-section-label {
    font-size: 0.65rem;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.1em;
    color: #00c8a0;
    margin: 14px 0 7px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.ana-section-label::after {
    content: '';
    flex: 1;
    height: 1px;
    background: rgba(0,200,160,0.15);
}

/* ── Grid rows ───────────────────────────────────────────────── */
.ana-row { display: grid; gap: 11px; }
.ana-row-5 { grid-template-columns: repeat(5, 1fr); }
.ana-row-4 { grid-template-columns: repeat(4, 1fr); }
.ana-row-3 { grid-template-columns: repeat(3, 1fr); }

/* ── Base card ───────────────────────────────────────────────── */
.ana-card {
    background: #0d2035;
    border: 1px solid rgba(0,200,160,0.12);
    border-radius: 10px;
    padding: 13px 15px;
    position: relative;
    overflow: hidden;
}
.ana-card-accent-top::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 2px;
    background: linear-gradient(90deg, #00c8a0, rgba(0,200,160,0.1));
}

/* ── Hero stat (row 1) ───────────────────────────────────────── */
.ana-stat-label {
    font-size: 0.63rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #7a9eb0;
    margin-bottom: 5px;
}
.ana-stat-value {
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
    margin-bottom: 3px;
}
.ana-stat-sub { font-size: 0.67rem; color: #7a9eb0; }
.ana-stat-sub strong { color: #00c8a0; }

/* ── Dual-period card ────────────────────────────────────────── */
.ana-dual-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #00c8a0;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin-bottom: 11px;
}
.ana-dual-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 10px;
}
.ana-period {
    background: rgba(0,200,160,0.05);
    border: 1px solid rgba(0,200,160,0.08);
    border-radius: 6px;
    padding: 9px 10px 7px;
}
.ana-period-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #7a9eb0;
    margin-bottom: 3px;
}
.ana-period-value {
    font-size: 1.45rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
}
.ana-period-sub { font-size: 0.63rem; color: #7a9eb0; margin-top: 2px; }

/* ── Success bar ─────────────────────────────────────────────── */
.ana-bar-row {
    display: flex;
    align-items: center;
    gap: 7px;
    margin-top: 2px;
}
.ana-bar-label { font-size: 0.62rem; color: #7a9eb0; white-space: nowrap; }
.ana-bar-track {
    flex: 1;
    height: 4px;
    background: rgba(255,255,255,0.07);
    border-radius: 3px;
    overflow: hidden;
}
.ana-bar-fill { height: 100%; border-radius: 3px; background: #00c8a0; }
.ana-bar-pct { font-size: 0.63rem; color: #00c8a0; white-space: nowrap; min-width: 28px; text-align: right; }

/* ── Factions pill list ──────────────────────────────────────── */
.ana-faction-count {
    font-size: 2rem;
    font-weight: 700;
    color: #fff;
    line-height: 1;
    margin-bottom: 10px;
}
.ana-faction-count span { font-size: 0.67rem; font-weight: 400; color: #7a9eb0; margin-left: 4px; }
.ana-faction-pills { display: flex; flex-direction: column; gap: 5px; }
.ana-faction-pill {
    display: flex;
    align-items: center;
    gap: 7px;
    font-size: 0.7rem;
    color: #c8dce8;
}
.ana-faction-pill img { width: 14px; height: 14px; object-fit: contain; flex-shrink: 0; }
.ana-faction-bar-track {
    flex: 1;
    height: 4px;
    background: rgba(255,255,255,0.07);
    border-radius: 3px;
    overflow: hidden;
}
.ana-faction-bar-fill { height: 100%; border-radius: 3px; background: rgba(0,200,160,0.5); }
.ana-faction-pill-count { font-size: 0.65rem; color: #00c8a0; white-space: nowrap; }

/* ── Gaming card ─────────────────────────────────────────────── */
.ana-game-title {
    font-size: 0.72rem;
    font-weight: 700;
    color: #00c8a0;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin-bottom: 11px;
}
.ana-game-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    margin-bottom: 9px;
}
.ana-game-stat {
    background: rgba(0,200,160,0.05);
    border: 1px solid rgba(0,200,160,0.08);
    border-radius: 6px;
    padding: 9px 10px 7px;
}
.ana-game-stat.accent {
    border-color: rgba(0,200,160,0.3);
    background: rgba(0,200,160,0.09);
}
.ana-game-stat-label {
    font-size: 0.6rem;
    text-transform: uppercase;
    letter-spacing: 0.06em;
    color: #7a9eb0;
    margin-bottom: 3px;
}
.ana-game-stat-value { font-size: 1.45rem; font-weight: 700; color: #fff; line-height: 1; }
.ana-game-stat.accent .ana-game-stat-value { color: #00c8a0; }
.ana-game-note {
    font-size: 0.62rem;
    color: #7a9eb0;
    border-top: 1px solid rgba(255,255,255,0.05);
    padding-top: 7px;
}
.ana-game-note strong { color: #c8dce8; }

/* ── Economy + Projects unified card ────────────────────────── */
.ana-econ-strip {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid rgba(0,200,160,0.1);
}
.ana-econ-item {
    background: rgba(0,200,160,0.05);
    border: 1px solid rgba(0,200,160,0.08);
    border-radius: 6px;
    padding: 11px 10px 9px;
    text-align: center;
}
.ana-econ-value { font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 3px; line-height: 1; }
.ana-econ-label { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.05em; color: #7a9eb0; }
.ana-proj-cols { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.ana-proj-title {
    font-size: 0.7rem;
    font-weight: 700;
    color: #00c8a0;
    text-transform: uppercase;
    letter-spacing: 0.07em;
    margin-bottom: 8px;
    display: flex;
    align-items: baseline;
    gap: 8px;
}
.ana-proj-title span { font-size: 1.2rem; font-weight: 700; color: #fff; letter-spacing: 0; text-transform: none; }
.ana-proj-pills { display: flex; flex-wrap: wrap; gap: 5px; }
.ana-proj-pill {
    display: flex;
    align-items: center;
    gap: 5px;
    background: rgba(0,200,160,0.06);
    border: 1px solid rgba(0,200,160,0.15);
    border-radius: 5px;
    padding: 4px 8px;
    font-size: 0.68rem;
    color: #c8dce8;
}
.ana-proj-pill img { width: 13px; height: 13px; object-fit: contain; }
.ana-proj-pill strong { color: #00c8a0; }

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .ana-row-5, .ana-row-4 { grid-template-columns: repeat(3, 1fr); }
    .ana-econ-strip { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 700px) {
    .ana-row-5, .ana-row-4, .ana-row-3 { grid-template-columns: 1fr 1fr; }
    .ana-dual-grid, .ana-game-grid { grid-template-columns: 1fr 1fr; }
    .ana-econ-strip { grid-template-columns: 1fr 1fr; }
    .ana-proj-cols { grid-template-columns: 1fr; }
    .ana-hero { flex-direction: column; align-items: flex-start; gap: 6px; }
}
@media (max-width: 480px) {
    .ana-row-5, .ana-row-4, .ana-row-3 { grid-template-columns: 1fr; }
}
</style>

<div class="ana-page">

    <!-- ── Header ── -->
    <div class="ana-hero">
        <h1><span>Skulliance</span> Analytics</h1>
        <div class="ana-hero-right">
            <div class="ana-updated">Updated: <strong><?php echo $updated; ?></strong></div>
            <div class="ana-tagline">Public engagement report — transparency is a feature</div>
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
    <div class="ana-row ana-row-4">

        <!-- Daily Rewards -->
        <div class="ana-card">
            <div class="ana-dual-title">🔥 Daily Reward Claims</div>
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

        <!-- Missions -->
        <div class="ana-card">
            <div class="ana-dual-title">🗺️ Missions</div>
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

        <!-- Raids -->
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

        <!-- Factions -->
        <div class="ana-card">
            <div class="ana-dual-title">🏴 Factions</div>
            <div class="ana-faction-count"><?php echo ana_fmt($factions_active); ?> <span>active factions</span></div>
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

    <!-- ── Gaming ── -->
    <div class="ana-section-label">Gaming <span style="font-size:0.6rem;font-weight:400;color:#7a9eb0;letter-spacing:0;text-transform:none;">Skull Swap &amp; Boss Battles cycle resets Thursday 4pm CST · Monstrocity is monthly</span></div>
    <div class="ana-row ana-row-3">

        <!-- Skull Swap -->
        <div class="ana-card">
            <div class="ana-game-title">💠 Skull Swap</div>
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

        <!-- Monstrocity -->
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

        <!-- Boss Battles -->
        <div class="ana-card">
            <div class="ana-game-title">💀 Boss Battles</div>
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

    <!-- ── Economy & Projects (unified) ── -->
    <div class="ana-section-label">Economy &amp; Projects</div>
    <div class="ana-card">

        <!-- Economy stats strip -->
        <div class="ana-dual-title" style="margin-bottom:12px;">💰 Platform Economy</div>
        <div class="ana-econ-strip">
            <div class="ana-econ-item">
                <div class="ana-econ-value"><?php echo ana_fmt($total_trans); ?></div>
                <div class="ana-econ-label">Transactions</div>
            </div>
            <div class="ana-econ-item">
                <div class="ana-econ-value"><?php echo ana_fmt($items_bought); ?></div>
                <div class="ana-econ-label">Store Claims</div>
            </div>
            <div class="ana-econ-item">
                <div class="ana-econ-value"><?php echo ana_fmt($diamonds); ?></div>
                <div class="ana-econ-label">Diamond Delegations</div>
            </div>
        </div>

        <!-- Projects side by side -->
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
        </div>

    </div>

</div>

<div class="footer">
    <p>Skulliance<br>Copyright &copy; <span id="year"></span></p>
</div>
</body>
</html>
