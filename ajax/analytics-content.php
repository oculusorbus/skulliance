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
<div class="ana-section-label">Gaming <span style="font-size:0.6rem;font-weight:400;color:#7a9eb0;letter-spacing:0;text-transform:none;">Skull Swap &amp; Boss Battles cycle resets Thursday 4pm CST · Monstrocity is monthly</span></div>
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
