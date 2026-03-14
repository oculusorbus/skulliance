<?php
include_once 'db.php';

// Set up session variables for header.php without forcing a login redirect
$name = "";
$avatar_url = "";
if (isset($_SESSION['userData'])) {
    extract($_SESSION['userData']);
    $avatar_url = "https://cdn.discordapp.com/avatars/$discord_id/$avatar.jpg";
}

// Determine which profile to show
$profile_username = isset($_GET['username']) ? trim($_GET['username']) : '';
if ($profile_username === '' && isset($_SESSION['userData'])) {
    $profile_username = $_SESSION['userData']['username'] ?? $_SESSION['userData']['name'] ?? '';
}

if ($profile_username === '') {
    header('Location: error.php');
    exit();
}

// Load profile user
$safe_username = mysqli_real_escape_string($conn, $profile_username);
$user_result   = $conn->query("SELECT id, discord_id, avatar, username, streak, date_created, visibility FROM users WHERE username = '$safe_username' LIMIT 1");

if (!$user_result || $user_result->num_rows === 0) {
    if (!isset($_GET['username']) && isset($_SESSION['logged_in'])) {
        header('Location: dashboard.php');
        exit();
    }
    include 'header.php';
    echo "<div class='row' id='row1'><div class='main'><h2>Profile Not Found</h2><p style='color:#aaa'>No user found for <strong>" . htmlspecialchars($profile_username) . "</strong>.</p></div></div></div></div></body></html>";
    $conn->close();
    exit();
}

$profile_user   = $user_result->fetch_assoc();
$tid            = (int)$profile_user['id'];
$show_nfts      = ($profile_user['visibility'] == 2);
$is_own_profile = isset($_SESSION['userData']['user_id']) && (int)$_SESSION['userData']['user_id'] === $tid;
$profile_avatar = "https://cdn.discordapp.com/avatars/{$profile_user['discord_id']}/{$profile_user['avatar']}.png";
$display_name   = htmlspecialchars($profile_user['username']);
$member_since   = date('M Y', strtotime($profile_user['date_created']));

// ── Missions ───────────────────────────────────────────────────────────────

$mis_r = $conn->query("SELECT
    COUNT(*) as total,
    SUM(CASE WHEN status='1' THEN 1 ELSE 0 END) as completed,
    SUM(CASE WHEN status='0' THEN 1 ELSE 0 END) as in_progress,
    SUM(CASE WHEN status='2' THEN 1 ELSE 0 END) as failed
    FROM missions WHERE user_id='$tid'");
$mis = $mis_r ? $mis_r->fetch_assoc() : ['total'=>0,'completed'=>0,'in_progress'=>0,'failed'=>0];
$missions_total     = (int)($mis['total'] ?? 0);
$missions_completed = (int)($mis['completed'] ?? 0);
$missions_progress  = (int)($mis['in_progress'] ?? 0);
$missions_failed    = (int)($mis['failed'] ?? 0);
$missions_rate      = $missions_total > 0 ? round(($missions_completed / $missions_total) * 100) : 0;

// Recent missions — this month only, one row per quest, ordered by most recent activity
$recent_missions = [];
$rm_result = $conn->query("SELECT q.title, q.extension, p.name as project_name,
    SUBSTRING_INDEX(GROUP_CONCAT(m.status ORDER BY m.id DESC), ',', 1) as status
    FROM missions m
    INNER JOIN quests q ON m.quest_id = q.id
    INNER JOIN projects p ON p.id = q.project_id
    WHERE m.user_id='$tid'
    AND DATE(m.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
    GROUP BY m.quest_id
    ORDER BY MAX(m.id) DESC LIMIT 16");
if ($rm_result && $rm_result->num_rows > 0) {
    while ($row = $rm_result->fetch_assoc()) {
        $ext  = ($row['extension'] === 'mp4') ? 'gif' : $row['extension'];
        $slug = strtolower(str_replace(' ', '-', $row['title']));
        $recent_missions[] = [
            'title'   => htmlspecialchars($row['title']),
            'project' => htmlspecialchars($row['project_name']),
            'status'  => (int)$row['status'],
            'image'   => "images/missions/{$slug}.{$ext}",
        ];
    }
    shuffle($recent_missions);
}

// ── Raids ──────────────────────────────────────────────────────────────────

$raid_sql = "SELECT COUNT(*) as total,
    SUM(CASE WHEN outcome='1' AND o_r.user_id='$tid' THEN 1
             WHEN outcome='0' AND d_r.user_id='$tid' THEN 1
             ELSE 0 END) as wins,
    SUM(CASE WHEN outcome IS NULL THEN 1 ELSE 0 END) as in_progress
    FROM raids
    INNER JOIN realms AS o_r ON o_r.id = raids.offense_id
    INNER JOIN realms AS d_r ON d_r.id = raids.defense_id
    WHERE o_r.user_id='$tid' OR d_r.user_id='$tid'";
$raid_r        = $conn->query($raid_sql);
$raid_stats    = $raid_r ? $raid_r->fetch_assoc() : ['total'=>0,'wins'=>0,'in_progress'=>0];
$raid_total    = (int)($raid_stats['total'] ?? 0);
$raid_wins     = (int)($raid_stats['wins'] ?? 0);
$raid_losses   = max(0, $raid_total - $raid_wins);
$raid_progress = (int)($raid_stats['in_progress'] ?? 0);
$raid_rate     = $raid_total > 0 ? round(($raid_wins / $raid_total) * 100) : 0;

// ── Boss Battles ───────────────────────────────────────────────────────────

$boss_r    = $conn->query("SELECT COUNT(*) as total,
    COALESCE(SUM(damage_dealt),0) as dealt,
    COALESCE(SUM(damage_taken),0) as taken,
    COALESCE(ROUND(AVG(damage_dealt)),0) as avg_dealt
    FROM encounters WHERE user_id='$tid'");
$boss_s    = $boss_r ? $boss_r->fetch_assoc() : ['total'=>0,'dealt'=>0,'taken'=>0,'avg_dealt'=>0];
$boss_total     = (int)($boss_s['total'] ?? 0);
$boss_dealt     = (int)($boss_s['dealt'] ?? 0);
$boss_taken     = (int)($boss_s['taken'] ?? 0);
$boss_avg_dealt = (int)($boss_s['avg_dealt'] ?? 0);

// Recent boss encounters — this week only (reward='0' matches weekly leaderboard window)
$recent_bosses = [];
$rb_result = $conn->query("SELECT b.name AS boss_name, b.extension, p.name AS project_name, e.damage_dealt
    FROM encounters e
    INNER JOIN bosses b ON b.id = e.boss_id
    INNER JOIN projects p ON p.id = b.project_id
    WHERE e.user_id='$tid' AND e.reward='0'
    ORDER BY e.date_created DESC LIMIT 16");
if ($rb_result && $rb_result->num_rows > 0) {
    while ($row = $rb_result->fetch_assoc()) {
        $slug = strtolower($row['boss_name']);
        $slug = preg_replace(["/\s+/", "/'/", "/[^a-z0-9\-]+/", "/-+/"], ["-", "", "-", "-"], $slug);
        $slug = trim($slug, '-');
        $recent_bosses[] = [
            'name'    => htmlspecialchars($row['boss_name']),
            'project' => htmlspecialchars($row['project_name']),
            'damage'  => (int)$row['damage_dealt'],
            'image'   => "images/monstrocity/bosses/{$slug}.{$row['extension']}",
        ];
    }
    shuffle($recent_bosses);
}

// ── Monstrocity — matches leaderboard: project_id='36', AVG for all-time ──

$mono_r = $conn->query("SELECT
    COALESCE(ROUND(AVG(score)),0) as avg_score,
    COALESCE(ROUND(AVG(level)),0) as avg_level,
    COALESCE(MAX(score),0) as best_score,
    COALESCE(MAX(level),0) as best_level,
    COALESCE(SUM(attempts),0) as completions
    FROM scores WHERE user_id='$tid' AND project_id='36'");
$mono = $mono_r ? $mono_r->fetch_assoc() : ['avg_score'=>0,'avg_level'=>0,'best_score'=>0,'best_level'=>0,'completions'=>0];
$mono_avg_score  = (int)($mono['avg_score'] ?? 0);
$mono_avg_level  = (int)($mono['avg_level'] ?? 0);
$mono_best_score = (int)($mono['best_score'] ?? 0);
$mono_best_level = (int)($mono['best_level'] ?? 0);
$mono_completions= (int)($mono['completions'] ?? 0);

// Monthly Monstrocity (unrewarded = current period)
$mono_monthly_r = $conn->query("SELECT COALESCE(MAX(level),0) as m_level, COALESCE(MAX(score),0) as m_score
    FROM scores WHERE user_id='$tid' AND project_id='36' AND reward='0'");
$mono_monthly = $mono_monthly_r ? $mono_monthly_r->fetch_assoc() : ['m_level'=>0,'m_score'=>0];
$mono_monthly_level = (int)($mono_monthly['m_level'] ?? 0);
$mono_monthly_score = (int)($mono_monthly['m_score'] ?? 0);

// Campaign opponents config (mirrors monstrocity.php opponentsConfig)
$campaign_config = [
    'Craig','Merdock','Goblin Ganger','Texby','Mandiblus','Koipon',
    'Slime Mind','Billandar and Ted','Dankle','Jarhead','Spydrax',
    'Katastrophy','Ouchie','Drake',
    'Craig','Merdock','Goblin Ganger','Texby','Mandiblus','Koipon',
    'Slime Mind','Billandar and Ted','Dankle','Jarhead','Spydrax',
    'Katastrophy','Ouchie','Drake',
];
$campaign_opponents = [];
for ($i = 0; $i < count($campaign_config); $i++) {
    $n         = $campaign_config[$i];
    $slug      = strtolower(str_replace(' ', '-', $n));
    $type      = ($i >= 14) ? 'Leader' : 'Base';
    $subfolder = ($i >= 14) ? 'leader' : 'base';
    $defeated  = ($i < $mono_monthly_level);
    $campaign_opponents[] = [
        'level'    => $i + 1,
        'name'     => $n,
        'type'     => $type,
        'defeated' => $defeated,
        'image'    => $defeated
                        ? "images/monstrocity/monstrocity/{$subfolder}/{$slug}.png"
                        : "icons/padlock.png",
    ];
}

// ── Skull Swap ─────────────────────────────────────────────────────────────

$swap_r = $conn->query("SELECT
    COALESCE(ROUND(AVG(score)),0) as avg_score,
    COALESCE(MAX(score),0) as best_score,
    COALESCE(SUM(attempts),0) as total_attempts
    FROM scores WHERE user_id='$tid' AND project_id='0'");
$swap = $swap_r ? $swap_r->fetch_assoc() : ['avg_score'=>0,'best_score'=>0,'total_attempts'=>0];
$swap_avg_score   = (int)($swap['avg_score'] ?? 0);
$swap_best_score  = (int)($swap['best_score'] ?? 0);
$swap_total_swaps = (int)($swap['total_attempts'] ?? 0);

// Weekly Skull Swap (unrewarded = current period)
$swap_weekly_r = $conn->query("SELECT
    COALESCE(ROUND(AVG(score)),0) as avg_score,
    COALESCE(MAX(score),0) as best_score,
    COALESCE(SUM(attempts),0) as week_attempts
    FROM scores WHERE user_id='$tid' AND project_id='0' AND reward='0'");
$swap_weekly = $swap_weekly_r ? $swap_weekly_r->fetch_assoc() : ['avg_score'=>0,'best_score'=>0,'week_attempts'=>0];
$swap_weekly_avg   = (int)($swap_weekly['avg_score'] ?? 0);
$swap_weekly_best  = (int)($swap_weekly['best_score'] ?? 0);
$swap_weekly_attempts = (int)($swap_weekly['week_attempts'] ?? 0);

// ── Total Points ───────────────────────────────────────────────────────────

$pts_r        = $conn->query("SELECT COALESCE(SUM(balance),0) as total FROM balances WHERE user_id='$tid'");
$total_points = $pts_r ? (int)$pts_r->fetch_assoc()['total'] : 0;

// ── Daily Reward Days ──────────────────────────────────────────────────────

$streak_r    = $conn->query("SELECT COUNT(*) as c FROM transactions WHERE user_id='$tid' AND bonus='1'");
$streak_days = $streak_r ? (int)$streak_r->fetch_assoc()['c'] : 0;

// ── Balances per project ───────────────────────────────────────────────────

$balances   = [];
$bal_result = $conn->query("SELECT p.name as project_name, p.currency, b.balance, p.id as project_id
    FROM balances b INNER JOIN projects p ON p.id = b.project_id
    WHERE b.user_id='$tid' AND b.balance > 0 ORDER BY b.balance DESC");
if ($bal_result && $bal_result->num_rows > 0) {
    while ($row = $bal_result->fetch_assoc()) { $balances[] = $row; }
}

// ── Streak Calendar (13 weeks) ─────────────────────────────────────────────

$claim_days = [];
$cal_result = $conn->query("SELECT DATE(date_created) as day FROM transactions WHERE user_id='$tid' AND bonus='1' AND date_created >= DATE_SUB(CURDATE(), INTERVAL 91 DAY) GROUP BY DATE(date_created)");
if ($cal_result && $cal_result->num_rows > 0) {
    while ($row = $cal_result->fetch_assoc()) { $claim_days[$row['day']] = true; }
}

// ── Mission Calendar (13 weeks) ────────────────────────────────────────────

$mission_days = [];
$mis_cal_result = $conn->query("SELECT DATE(created_date) as day FROM missions WHERE user_id='$tid' AND created_date >= DATE_SUB(CURDATE(), INTERVAL 91 DAY) GROUP BY DATE(created_date)");
if ($mis_cal_result && $mis_cal_result->num_rows > 0) {
    while ($row = $mis_cal_result->fetch_assoc()) { $mission_days[$row['day']] = true; }
}
$mission_cal_total = count($mission_days);

// ── Raid Calendar (13 weeks) ────────────────────────────────────────────────

$raid_days = [];
$raid_cal_result = $conn->query("SELECT DATE(r.created_date) as day
    FROM raids r
    INNER JOIN realms o ON o.id = r.offense_id
    INNER JOIN realms d ON d.id = r.defense_id
    WHERE (o.user_id='$tid' OR d.user_id='$tid')
    AND r.created_date >= DATE_SUB(CURDATE(), INTERVAL 91 DAY)
    GROUP BY DATE(r.created_date)");
if ($raid_cal_result && $raid_cal_result->num_rows > 0) {
    while ($row = $raid_cal_result->fetch_assoc()) { $raid_days[$row['day']] = true; }
}
$raid_cal_total = count($raid_days);

// ── Realm with theme ───────────────────────────────────────────────────────

$realm = null;
$realm_theme_id = null;
$realm_result = $conn->query("SELECT r.name as realm_name, r.theme_id, p.name as project_name, p.currency FROM realms r
    INNER JOIN projects p ON p.id = r.project_id WHERE r.user_id='$tid' AND r.active='1' LIMIT 1");
if ($realm_result && $realm_result->num_rows > 0) {
    $realm = $realm_result->fetch_assoc();
    $realm_theme_id = (int)$realm['theme_id'];
}

// ── NFT Gallery (random, for bottom column) ────────────────────────────────

$gallery_nfts = [];
if ($show_nfts) {
    $gal_result = $conn->query("SELECT ipfs, nfts.name as nft_name, collection_id, p.name as project_name, c.name as collection_name
        FROM nfts INNER JOIN collections c ON c.id = nfts.collection_id INNER JOIN projects p ON p.id = c.project_id
        WHERE nfts.user_id='$tid' ORDER BY RAND() LIMIT 12");
    if ($gal_result && $gal_result->num_rows > 0) {
        while ($row = $gal_result->fetch_assoc()) {
            $gallery_nfts[] = [
                'url'        => getIPFS($row['ipfs'], $row['collection_id']),
                'name'       => htmlspecialchars($row['nft_name']),
                'project'    => htmlspecialchars($row['project_name']),
                'collection' => htmlspecialchars($row['collection_name']),
            ];
        }
    }
}

// ── Redeemed Store Items ────────────────────────────────────────────────────

$redeemed_items = [];
$items_result = $conn->query("SELECT DISTINCT i.id, i.name AS item_name, i.image_url, i.quantity, p.name AS project_name
    FROM transactions t
    INNER JOIN items i ON i.id = t.item_id
    INNER JOIN projects p ON p.id = i.project_id
    WHERE t.user_id='$tid' AND t.item_id > 0 AND t.type='debit'
    ORDER BY RAND()");
if ($items_result && $items_result->num_rows > 0) {
    while ($row = $items_result->fetch_assoc()) {
        $redeemed_items[] = [
            'name'     => htmlspecialchars($row['item_name']),
            'image'    => htmlspecialchars($row['image_url']),
            'project'  => htmlspecialchars($row['project_name']),
            'quantity' => (int)$row['quantity'],
        ];
    }
}

// ── Raid Opponents with realm info ─────────────────────────────────────────

$opponents = [];
$opp_result = $conn->query("SELECT u.discord_id, u.avatar, u.username,
    IF(o.user_id='$tid', d.name, o.name) as opp_realm_name,
    IF(o.user_id='$tid', d.theme_id, o.theme_id) as opp_theme_id,
    IF(o.user_id='$tid', pd.name, po.name) as opp_project_name,
    IF(o.user_id='$tid', pd.currency, po.currency) as opp_currency
    FROM raids r
    INNER JOIN realms o ON o.id = r.offense_id
    INNER JOIN realms d ON d.id = r.defense_id
    INNER JOIN users u ON u.id = IF(o.user_id='$tid', d.user_id, o.user_id)
    INNER JOIN projects po ON po.id = o.project_id
    INNER JOIN projects pd ON pd.id = d.project_id
    WHERE (o.user_id='$tid' OR d.user_id='$tid')
    AND DATE(r.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
    GROUP BY u.id
    ORDER BY MAX(r.created_date) DESC LIMIT 8");
if ($opp_result && $opp_result->num_rows > 0) {
    while ($row = $opp_result->fetch_assoc()) { $opponents[] = $row; }
    shuffle($opponents);
}

// ── Membership badges ──────────────────────────────────────────────────────

$held_core = [];
$core_r    = $conn->query("SELECT DISTINCT c.project_id FROM nfts INNER JOIN collections c ON c.id = nfts.collection_id WHERE nfts.user_id='$tid' AND c.project_id BETWEEN 1 AND 7");
if ($core_r && $core_r->num_rows > 0) {
    while ($row = $core_r->fetch_assoc()) { $held_core[] = (int)$row['project_id']; }
}
$has_diamond = in_array(7, $held_core);
$core_count  = count(array_filter($held_core, fn($p) => $p < 7));
$is_elite    = $core_count >= 6;
$is_member   = $core_count >= 3;

// ── Open Graph / Twitter Card meta tags ───────────────────────────────────

$og_title = "{$display_name}'s Skulliance Profile";
$og_parts = [];
if ($missions_completed > 0) $og_parts[] = "⚔️ {$missions_completed} missions";
if ($raid_wins > 0)          $og_parts[] = "🏰 {$raid_wins} raid wins";
if ($boss_dealt > 0)         $og_parts[] = "☠️ " . number_format($boss_dealt) . " boss damage";
if ($mono_best_score > 0)    $og_parts[] = "🎮 " . number_format($mono_best_score) . " Match 3";
if ($swap_best_score > 0)    $og_parts[] = "💀 " . number_format($swap_best_score) . " Skull Swap";
$og_description = implode(' · ', $og_parts) ?: "Skulliance community player profile.";
$og_image = "https://www.skulliance.io/staking/images/og.jpg";
if ($realm_theme_id) {
    $og_image = "https://www.skulliance.io/staking/images/themes/{$realm_theme_id}.jpg";
}
$og_url = "https://www.skulliance.io/staking/profile.php?username=" . urlencode($profile_user['username']);

$extra_head = "
<meta property='og:type'        content='profile' />
<meta property='og:site_name'   content='Skulliance' />
<meta property='og:title'       content='" . htmlspecialchars($og_title, ENT_QUOTES) . "' />
<meta property='og:description' content='" . htmlspecialchars($og_description, ENT_QUOTES) . "' />
<meta property='og:image'       content='" . htmlspecialchars($og_image, ENT_QUOTES) . "' />
<meta property='og:url'         content='" . htmlspecialchars($og_url, ENT_QUOTES) . "' />
<meta name='twitter:card'        content='summary_large_image' />
<meta name='twitter:title'       content='" . htmlspecialchars($og_title, ENT_QUOTES) . "' />
<meta name='twitter:description' content='" . htmlspecialchars($og_description, ENT_QUOTES) . "' />
<meta name='twitter:image'       content='" . htmlspecialchars($og_image, ENT_QUOTES) . "' />
";

include 'header.php';
?>

<style>
/* ── Profile Page ────────────────────────────────────────────────────────── */

.profile-wrap {
    flex: 100%;
    width: 100%;
    padding: 20px 20px 60px;
    box-sizing: border-box;
    color: #e8eaed;
    font-family: Arial, sans-serif;
}

/* ── Hero ── */
.profile-hero {
    position: relative;
    width: 100%;
    min-height: 240px;
    border-radius: 12px;
    overflow: hidden;
    background-color: #0a1929;
    background-size: cover;
    background-position: center;
}
.hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(160deg, rgba(0,47,68,0.72) 0%, rgba(13,20,30,0.94) 100%);
}
.hero-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: flex-end;
    gap: 22px;
    padding: 36px 28px 0;
}
.hero-avatar-wrap { flex-shrink: 0; position: relative; bottom: -28px; }
@media (max-width: 500px) {
    .hero-content { align-items: flex-start; padding: 20px 18px 16px; }
    .hero-avatar-wrap { bottom: 0; }
}
.hero-avatar {
    width: 100px; height: 100px; border-radius: 50%;
    border: 4px solid #00c8a0;
    box-shadow: 0 0 24px rgba(0,200,160,0.4);
    background: #0a1929; object-fit: cover; display: block;
}
.hero-text { padding-bottom: 14px; flex: 1; min-width: 0; }
.hero-username {
    font-size: 1.8rem; font-weight: bold; color: #fff; margin: 0 0 8px;
    text-shadow: 0 2px 8px rgba(0,0,0,0.8);
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.hero-meta { display: flex; flex-wrap: wrap; gap: 10px; align-items: center; }

/* ── Badges ── */
.badge {
    display: inline-flex; align-items: center; gap: 5px;
    padding: 0 10px; border-radius: 20px; height: 24px; box-sizing: border-box;
    font-size: 0.72rem; font-weight: bold; letter-spacing: 0.04em; text-transform: uppercase;
}
.badge img { height: 13px; width: auto; flex-shrink: 0; }
.badge-member  { background: rgba(180,180,200,0.18); color: #c8d0e0; border: 1px solid #555; }
.badge-elite   { background: rgba(245,197,24,0.15);  color: #f5c518; border: 1px solid #f5c518; }
.badge-diamond { background: rgba(138,86,255,0.18);  color: #c79fff; border: 1px solid #9b59b6; }
.badge-realm   { background: rgba(0,200,160,0.12);   color: #00c8a0; border: 1px solid #00c8a0; }
.badge-faction { background: rgba(255,255,255,0.08);  color: #e8eef4; border: 1px solid rgba(255,255,255,0.3); }
.badge-since   { background: rgba(255,255,255,0.06); color: #8899aa; border: 1px solid #2a3a4a; font-weight: normal; text-transform: none; font-size: 0.75rem; }

/* ── Section card ── */
.profile-section {
    background: rgba(12, 28, 42, 0.85);
    border: 1px solid rgba(0,200,160,0.10);
    border-radius: 10px;
    padding: 22px 24px;
    margin-top: 18px;
}
.section-title {
    font-size: 0.75rem; font-weight: bold; letter-spacing: 0.12em;
    text-transform: uppercase; color: #00c8a0;
    margin: 0 0 16px; padding-bottom: 8px;
    border-bottom: 1px solid rgba(0,200,160,0.15);
    display: flex; align-items: center; justify-content: space-between;
}
.section-title-link {
    font-size: 0.68rem; font-weight: bold; letter-spacing: 0.06em;
    text-transform: uppercase; color: #07111d; text-decoration: none;
    background: #00c8a0; border-radius: 20px; padding: 4px 12px;
    transition: background 0.2s, color 0.2s; white-space: nowrap;
}
.section-title-link:hover { background: #00a882; color: #07111d; }

/* ── Stat grid ── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(var(--stat-cols, 6), 1fr);
    gap: 12px;
    margin-top: 18px;
}
@media (max-width: 900px) { .stat-grid { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 500px) { .stat-grid { grid-template-columns: repeat(2, 1fr); } }

.stat-card {
    background: rgba(22,87,119,0.25);
    border: 1px solid rgba(0,200,160,0.12);
    border-radius: 8px; padding: 16px 10px 14px; text-align: center;
    transition: border-color 0.2s, background 0.2s;
}
.stat-card:hover { border-color: rgba(0,200,160,0.35); background: rgba(22,87,119,0.40); }
.stat-number {
    font-size: 1.6rem; font-weight: bold; color: #fff;
    line-height: 1; display: block; margin-bottom: 5px;
}
.stat-number.gold   { color: #f5c518; }
.stat-number.teal   { color: #00c8a0; }
.stat-number.purple { color: #c79fff; }
.stat-number.coral  { color: #ff7f7f; }
.stat-label {
    font-size: 0.62rem; letter-spacing: 0.07em;
    text-transform: uppercase; color: #6a8090; line-height: 1.3;
}

/* ── Activity panels ── */
.activity-stats-row {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-bottom: 18px;
}
@media (max-width: 700px) { .activity-stats-row { grid-template-columns: repeat(2, 1fr); } }
.activity-stats-row-5 { grid-template-columns: repeat(5, 1fr); }
@media (max-width: 700px) { .activity-stats-row-5 { grid-template-columns: repeat(3, 1fr); } }
@media (max-width: 420px) { .activity-stats-row-5 { grid-template-columns: repeat(2, 1fr); } }

.act-stat {
    background: rgba(22,87,119,0.22);
    border: 1px solid rgba(0,200,160,0.08);
    border-radius: 8px; padding: 14px 12px; text-align: center;
}
.act-stat-num {
    font-size: 1.5rem; font-weight: bold; display: block; margin-bottom: 4px;
}
.act-stat-lbl {
    font-size: 0.65rem; text-transform: uppercase; letter-spacing: 0.08em; color: #5a7888;
}

/* ── Progress bar ── */
.progress-bar-wrap {
    background: rgba(22,87,119,0.20);
    border-radius: 4px; height: 6px; margin: 10px 0 4px; overflow: hidden;
}
.progress-bar-fill {
    height: 100%; border-radius: 4px;
    background: linear-gradient(90deg, #00c8a0, #0090c8);
    transition: width 0.8s ease;
}
.progress-bar-label {
    font-size: 0.7rem; color: #5a7888; text-align: right;
}

/* ── Image strip (horizontal scroll) ── */
.image-strip-section-label {
    font-size: 0.7rem; letter-spacing: 0.1em; text-transform: uppercase;
    color: #3a6070; margin: 20px 0 10px;
}
.image-strip {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 8px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #2a4050 transparent;
}
.image-strip::-webkit-scrollbar { height: 4px; }
.image-strip::-webkit-scrollbar-track { background: transparent; }
.image-strip::-webkit-scrollbar-thumb { background: #2a4050; border-radius: 2px; }
.strip-card {
    flex: 1 1 110px;
    min-width: 100px;
    max-width: 140px;
    border-radius: 8px;
    overflow: hidden;
    background: #0a1929;
    border: 1px solid rgba(0,200,160,0.10);
    transition: border-color 0.2s, transform 0.15s;
    cursor: default;
}
.strip-card:hover { border-color: rgba(0,200,160,0.35); transform: translateY(-2px); }
.strip-card img {
    width: 100%; aspect-ratio: 1; object-fit: cover; display: block;
    background: #122030;
}
.strip-card-body { padding: 6px 8px 8px; }
.strip-card-title {
    font-size: 0.68rem; font-weight: bold; color: #e0e8f0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: block;
}
.strip-card-sub {
    font-size: 0.6rem; color: #5a7888;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: block; margin-top: 2px;
}
.strip-card-badge {
    display: inline-block; font-size: 0.55rem; font-weight: bold;
    letter-spacing: 0.05em; padding: 1px 5px; border-radius: 3px;
    margin-top: 3px; text-transform: uppercase;
}
.badge-done    { background: rgba(0,200,160,0.18); color: #00c8a0; }
.badge-fail    { background: rgba(255,80,80,0.18);  color: #ff7f7f; }
.badge-going   { background: rgba(245,197,24,0.18); color: #f5c518; }
.badge-leader  { background: rgba(138,86,255,0.18); color: #c79fff; }
.badge-base    { background: rgba(0,200,160,0.12);  color: #00c8a0; }

/* Wider strip cards for campaign (more levels = more breathing room) */
.strip-wide .strip-card {
    flex: 1 1 96px;
    min-width: 88px;
    max-width: 120px;
}

/* ── Opponents grid ── */
.opponents-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(120px, 1fr));
    gap: 12px;
    margin-top: 8px;
}
.opponent-card {
    position: relative;
    border-radius: 8px;
    overflow: hidden;
    border: 1px solid rgba(0,200,160,0.12);
    background: #0a1929;
    transition: border-color 0.2s, transform 0.15s;
    text-decoration: none;
    display: block;
}
.opponent-card:hover { border-color: #00c8a0; transform: translateY(-2px); }
.opponent-theme-bg {
    width: 100%; height: 70px;
    background-size: cover; background-position: center;
    background-color: #122030;
}
.opponent-info { padding: 8px 10px 10px; }
.opponent-avatar {
    width: 36px; height: 36px; border-radius: 50%;
    border: 2px solid #00c8a0;
    object-fit: cover; display: block;
    margin-top: -18px; margin-bottom: 5px;
    background: #0a1929;
}
.opponent-name {
    font-size: 0.78rem; font-weight: bold; color: #e8eaed;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: block;
}
.opponent-realm {
    font-size: 0.65rem; color: #5a7888;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    display: block; margin-top: 2px;
}

/* ── Streak Calendar ── */
.calendar-outer {
    display: flex;
    gap: 6px;
    align-items: flex-start;
    overflow-x: auto;
}
.cal-day-labels {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-top: 22px;
    flex-shrink: 0;
}
.cal-day-label {
    font-size: 0.6rem;
    color: #3a5060;
    height: 13px;
    line-height: 13px;
    text-align: right;
    width: 24px;
}
.cal-weeks-wrap { flex: 1; min-width: 0; }
.cal-month-row {
    display: grid;
    grid-template-columns: repeat(13, 1fr);
    gap: 4px;
    margin-bottom: 4px;
}
.cal-month-label {
    font-size: 0.58rem; color: #3a7060;
    white-space: nowrap; overflow: hidden;
    text-overflow: clip;
}
.calendar-grid {
    display: grid;
    grid-template-columns: repeat(13, 1fr);
    gap: 4px;
}
.calendar-week { display: flex; flex-direction: column; gap: 4px; }
.calendar-day {
    width: 100%; aspect-ratio: 1; border-radius: 3px;
    cursor: default; transition: transform 0.1s; position: relative;
}
.calendar-day:hover { transform: scale(1.4); z-index: 2; }
.calendar-day.future  { background: #0e1e2a; }
.calendar-day.missed  { background: #152230; }
.calendar-day.claimed { background: #00c8a0; box-shadow: 0 0 5px rgba(0,200,160,0.45); }
.cal-legend {
    display: flex; align-items: center; gap: 8px;
    font-size: 0.7rem; color: #5a7888; margin-top: 10px; justify-content: flex-end;
}
.legend-swatch { width: 10px; height: 10px; border-radius: 2px; }

/* ── Bottom two-col: Points + NFTs ── */
.bottom-cols {
    display: flex;
    gap: 18px;
    margin-top: 18px;
    align-items: stretch;
}
.bottom-col-points {
    flex: 1;
    min-width: 0;
}
.bottom-col-nfts {
    flex: 1;
    min-width: 0;
    display: flex;
    flex-direction: column;
    gap: 18px;
}
@media (max-width: 680px) {
    .bottom-cols { flex-direction: column; }
    .bottom-col-nfts { overflow: visible; }
}

.currency-list { display: flex; flex-direction: column; gap: 9px; }
.currency-row {
    display: flex; align-items: center; gap: 10px;
    background: rgba(22,87,119,0.18); border-radius: 6px;
    padding: 7px 12px; transition: background 0.15s;
}
.currency-row:hover { background: rgba(22,87,119,0.32); }
.currency-icon { width: 24px; height: 24px; object-fit: contain; flex-shrink: 0; }
.currency-label { flex: 1; font-size: 0.82rem; color: #aac0cc; }
.currency-amount { font-weight: bold; font-size: 0.9rem; color: #e8eaed; white-space: nowrap; }

.nft-mosaic {
    display: flex;
    gap: 10px;
    overflow-x: auto;
    padding-bottom: 8px;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: thin;
    scrollbar-color: #2a4050 transparent;
}
.nft-mosaic::-webkit-scrollbar { height: 4px; }
.nft-mosaic::-webkit-scrollbar-track { background: transparent; }
.nft-mosaic::-webkit-scrollbar-thumb { background: #2a4050; border-radius: 2px; }
.nft-thumb {
    flex-shrink: 0;
    width: 130px;
    border-radius: 8px; overflow: hidden;
    border: 1px solid rgba(0,200,160,0.12);
    background: #0a1929;
    transition: border-color 0.2s, transform 0.15s;
}
.nft-thumb:hover { border-color: #00c8a0; transform: translateY(-2px); }
.nft-thumb img { width: 100%; height: 120px; object-fit: cover; display: block; }
.nft-thumb-body { padding: 6px 8px 8px; }
.nft-thumb-name {
    font-size: 0.65rem; font-weight: bold; color: #e0e8f0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;
}
.nft-thumb-sub {
    font-size: 0.58rem; color: #5a7888; display: block; margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}

/* ── Rewards strip ── */
.reward-strip-card {
    flex-shrink: 0;
    width: 120px;
    border-radius: 8px; overflow: hidden;
    background: #0a1929;
    border: 1px solid rgba(0,200,160,0.10);
    transition: border-color 0.2s, transform 0.15s;
}
.reward-strip-card:hover { border-color: rgba(0,200,160,0.35); transform: translateY(-2px); }
.reward-strip-card img { width: 100%; height: 110px; object-fit: cover; display: block; background: #122030; }
.reward-strip-card-body { padding: 6px 8px 8px; }
.reward-strip-card-name {
    font-size: 0.65rem; font-weight: bold; color: #e0e8f0;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;
}
.reward-strip-card-project {
    font-size: 0.58rem; color: #5a7888; display: block; margin-top: 2px;
    white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
.reward-strip-card-badge {
    display: inline-block; font-size: 0.52rem; font-weight: bold;
    padding: 1px 5px; border-radius: 3px; margin-top: 3px;
    text-transform: uppercase;
}
.badge-soldout { background: rgba(255,80,80,0.15); color: #ff7f7f; }
.badge-available { background: rgba(0,200,160,0.12); color: #00c8a0; }

/* ── Misc ── */
.no-data { color: #3a5060; font-size: 0.82rem; font-style: italic; padding: 10px 0; }
.visibility-notice {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 16px;
    background: rgba(22,87,119,0.18);
    border: 1px dashed rgba(0,200,160,0.2);
    border-radius: 8px; color: #5a7888; font-size: 0.8rem;
}
.own-profile-bar {
    display: flex; justify-content: flex-end; align-items: center;
    gap: 12px; margin-bottom: 4px; font-size: 0.78rem; color: #4a6070;
}
.own-profile-bar a {
    color: #00c8a0; text-decoration: none;
    border: 1px solid rgba(0,200,160,0.3);
    padding: 4px 12px; border-radius: 20px; transition: background 0.15s;
}
.own-profile-bar a:hover { background: rgba(0,200,160,0.12); }
.share-btn {
    background: rgba(0,200,160,0.12); border: 1px solid rgba(0,200,160,0.35);
    color: #00c8a0; padding: 5px 16px; border-radius: 20px; cursor: pointer;
    font-size: 0.78rem; transition: background 0.15s; text-decoration: none;
    display: inline-flex; align-items: center; gap: 5px;
}
.share-btn:hover { background: rgba(0,200,160,0.22); color: #00c8a0; }
.share-btn.copied { color: #f5c518; border-color: #f5c518; }

/* ── Leaderboard link-button ── */
.lb-form { display:inline; margin:0; padding:0; }
.lb-btn {
    background: none; border: none; padding: 0; cursor: pointer;
    font-size: 0.75rem; color: #00c8a0; text-decoration: none;
    font-family: Arial, sans-serif;
}
.lb-btn:hover { text-decoration: underline; }
</style>

<div class="row" id="row1">
<div class="profile-wrap">

<?php if ($is_own_profile): ?>
<div class="own-profile-bar">
    Your public profile &nbsp;·&nbsp;
    <a href="wallets.php">Change visibility</a>
</div>
<?php endif; ?>

<!-- ── Hero ──────────────────────────────────────────────────────────── -->
<div class="profile-hero"<?php if ($realm_theme_id): ?> style="background-image:url('images/themes/<?php echo $realm_theme_id; ?>.jpg')"<?php endif; ?>>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-avatar-wrap">
            <img class="hero-avatar" src="<?php echo $profile_avatar; ?>?size=128" alt="<?php echo $display_name; ?>" onerror="this.src='icons/skull.png'">
        </div>
        <div class="hero-text">
            <div class="hero-username"><?php echo $display_name; ?></div>
            <div class="hero-meta">
                <?php if ($has_diamond): ?><span class="badge badge-diamond">&#9670; Diamond Skull</span><?php endif; ?>
                <?php if ($is_elite): ?><span class="badge badge-elite">&#9733; Elite</span><span class="badge badge-member">&#9679; Member</span>
                <?php elseif ($is_member): ?><span class="badge badge-member">&#9679; Member</span><?php endif; ?>
                <?php if ($realm): ?><span class="badge badge-realm">&#9956; <?php echo htmlspecialchars($realm['realm_name']); ?></span><span class="badge badge-faction"><img src="icons/<?php echo strtolower(htmlspecialchars($realm['currency'])); ?>.png" onerror="this.style.display='none'"><?php echo htmlspecialchars($realm['project_name']); ?></span><?php endif; ?>
                <span class="badge badge-since">Since <?php echo $member_since; ?></span>
                <button class="share-btn" id="share-btn" onclick="copyProfileLink()">&#128279; Share</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Summary Stats ─────────────────────────────────────────────────── -->
<div class="stat-grid" style="--stat-cols:<?php echo $swap_best_score > 0 ? 7 : 6; ?>">
    <div class="stat-card">
        <span class="stat-number gold" data-count="<?php echo $total_points; ?>"><?php echo number_format($total_points); ?></span>
        <span class="stat-label">Total Points</span>
    </div>
    <div class="stat-card">
        <span class="stat-number teal" data-count="<?php echo $missions_completed; ?>"><?php echo number_format($missions_completed); ?></span>
        <span class="stat-label">Missions Completed</span>
    </div>
    <div class="stat-card">
        <span class="stat-number" data-count="<?php echo $raid_wins; ?>"><?php echo number_format($raid_wins); ?></span>
        <span class="stat-label">Raid Wins</span>
    </div>
    <div class="stat-card">
        <span class="stat-number coral" data-count="<?php echo $boss_dealt; ?>"><?php echo number_format($boss_dealt); ?></span>
        <span class="stat-label">Boss Damage</span>
    </div>
    <div class="stat-card">
        <span class="stat-number purple" data-count="<?php echo $mono_best_score; ?>"><?php echo number_format($mono_best_score); ?></span>
        <span class="stat-label">Best Match 3 RPG Score</span>
    </div>
    <?php if ($swap_best_score > 0): ?>
    <div class="stat-card">
        <span class="stat-number gold" data-count="<?php echo $swap_best_score; ?>"><?php echo number_format($swap_best_score); ?></span>
        <span class="stat-label">Best Skull Swap Score</span>
    </div>
    <?php endif; ?>
    <div class="stat-card">
        <span class="stat-number teal" data-count="<?php echo $streak_days; ?>"><?php echo number_format($streak_days); ?></span>
        <span class="stat-label">Daily Rewards Claimed</span>
    </div>
</div>

<!-- ── Missions ──────────────────────────────────────────────────────── -->
<div class="profile-section">
    <div class="section-title"><span>&#9876; Missions</span><a href="missions.php" class="section-title-link">Start Mission &rarr;</a></div>
    <div class="activity-stats-row">
        <div class="act-stat">
            <span class="act-stat-num" style="color:#e8eaed"><?php echo number_format($missions_total); ?></span>
            <span class="act-stat-lbl">Total Sent</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#00c8a0"><?php echo number_format($missions_completed); ?></span>
            <span class="act-stat-lbl">Completed</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#ff7f7f"><?php echo number_format($missions_failed); ?></span>
            <span class="act-stat-lbl">Failed</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#f5c518"><?php echo number_format($missions_progress); ?></span>
            <span class="act-stat-lbl">In Progress</span>
        </div>
    </div>
    <?php if ($missions_total > 0): ?>
    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" style="width:<?php echo $missions_rate; ?>%"></div>
    </div>
    <div class="progress-bar-label">Success rate: <?php echo $missions_rate; ?>%</div>
    <?php endif; ?>
    <?php if (!empty($recent_missions)): ?>
    <div class="image-strip-section-label"><?php echo date('F'); ?> Missions</div>
    <div class="image-strip">
        <?php foreach ($recent_missions as $m):
            $status_class = $m['status'] === 1 ? 'badge-done' : ($m['status'] === 2 ? 'badge-fail' : 'badge-going');
            $status_label = $m['status'] === 1 ? 'Done' : ($m['status'] === 2 ? 'Failed' : 'Active');
        ?>
        <div class="strip-card">
            <img src="<?php echo htmlspecialchars($m['image']); ?>" alt="<?php echo $m['title']; ?>" loading="lazy" onerror="this.style.background='#122030'">
            <div class="strip-card-body">
                <span class="strip-card-title"><?php echo $m['title']; ?></span>
                <span class="strip-card-sub"><?php echo $m['project']; ?></span>
                <span class="strip-card-badge <?php echo $status_class; ?>"><?php echo $status_label; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:14px;text-align:right">
        <form class="lb-form" action="leaderboards.php" method="post">
            <input type="hidden" name="filterby" value="monthly">
            <button class="lb-btn" type="submit"><?php echo date('F'); ?> Missions Leaderboard &rarr;</button>
        </form>
    </div>
</div>

<!-- ── Raids ─────────────────────────────────────────────────────────── -->
<div class="profile-section">
    <div class="section-title"><span>&#9876; Raids</span><a href="realms.php" class="section-title-link">Raid Realms &rarr;</a></div>
    <div class="activity-stats-row">
        <div class="act-stat">
            <span class="act-stat-num" style="color:#e8eaed"><?php echo number_format($raid_total); ?></span>
            <span class="act-stat-lbl">Total Raids</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#00c8a0"><?php echo number_format($raid_wins); ?></span>
            <span class="act-stat-lbl">Victories</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#ff7f7f"><?php echo number_format($raid_losses); ?></span>
            <span class="act-stat-lbl">Defeats</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#f5c518"><?php echo number_format($raid_progress); ?></span>
            <span class="act-stat-lbl">In Progress</span>
        </div>
    </div>
    <?php if ($raid_total > 0): ?>
    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" style="width:<?php echo $raid_rate; ?>%"></div>
    </div>
    <div class="progress-bar-label">Win rate: <?php echo $raid_rate; ?>%</div>
    <?php endif; ?>
    <?php if (!empty($opponents)): ?>
    <div class="image-strip-section-label"><?php echo date('F'); ?> Opponents</div>
    <div class="opponents-grid">
        <?php foreach ($opponents as $opp):
            $opp_av  = "https://cdn.discordapp.com/avatars/{$opp['discord_id']}/{$opp['avatar']}.png";
            $opp_tid = (int)$opp['opp_theme_id'];
            $opp_bg  = $opp_tid ? "background-image:url('images/themes/{$opp_tid}.jpg')" : "background-color:#122030";
        ?>
        <a href="profile.php?username=<?php echo urlencode($opp['username']); ?>" class="opponent-card">
            <div class="opponent-theme-bg" style="<?php echo $opp_bg; ?>"></div>
            <div class="opponent-info">
                <img class="opponent-avatar" src="<?php echo $opp_av; ?>?size=64" alt="" onerror="this.src='icons/skull.png'">
                <span class="opponent-name"><?php echo htmlspecialchars($opp['username']); ?></span>
                <?php if (!empty($opp['opp_realm_name'])): ?>
                <span class="opponent-realm"><?php echo htmlspecialchars($opp['opp_realm_name']); ?></span>
                <?php endif; ?>
                <?php if (!empty($opp['opp_project_name'])): ?>
                <span class="opponent-realm"><img src="icons/<?php echo strtolower(htmlspecialchars($opp['opp_currency'])); ?>.png" style="height:11px;vertical-align:middle;margin-right:3px" onerror="this.style.display='none'"><?php echo htmlspecialchars($opp['opp_project_name']); ?></span>
                <?php endif; ?>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:14px;text-align:right">
        <form class="lb-form" action="leaderboards.php" method="post">
            <input type="hidden" name="filterby" value="monthly-raids">
            <button class="lb-btn" type="submit"><?php echo date('F'); ?> Raids Leaderboard &rarr;</button>
        </form>
    </div>
</div>

<!-- ── Boss Battles ───────────────────────────────────────────────────── -->
<div class="profile-section">
    <div class="section-title"><span>&#9876; Boss Battles</span><a href="monstrocity.php" target="_blank" class="section-title-link">Begin Boss Battle &rarr;</a></div>
    <div class="activity-stats-row">
        <div class="act-stat">
            <span class="act-stat-num" style="color:#e8eaed"><?php echo number_format($boss_total); ?></span>
            <span class="act-stat-lbl">Encounters</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#ff7f7f"><?php echo number_format($boss_dealt); ?></span>
            <span class="act-stat-lbl">Damage Dealt</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#f5c518"><?php echo number_format($boss_taken); ?></span>
            <span class="act-stat-lbl">Damage Taken</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#c79fff"><?php echo number_format($boss_avg_dealt); ?></span>
            <span class="act-stat-lbl">Avg Damage Dealt</span>
        </div>
    </div>
    <?php if ($boss_total > 0 && ($boss_dealt + $boss_taken) > 0): ?>
    <?php $dmg_ratio = round(($boss_dealt / ($boss_dealt + $boss_taken)) * 100); ?>
    <div class="progress-bar-wrap">
        <div class="progress-bar-fill" style="width:<?php echo $dmg_ratio; ?>%;background:linear-gradient(90deg,#ff7f7f,#c8003f)"></div>
    </div>
    <div class="progress-bar-label">Damage dealt vs. taken: <?php echo $dmg_ratio; ?>%</div>
    <?php endif; ?>
    <?php if (!empty($recent_bosses)): ?>
    <div class="image-strip-section-label">This Week's Encounters</div>
    <div class="image-strip">
        <?php foreach ($recent_bosses as $b): ?>
        <div class="strip-card">
            <img src="<?php echo htmlspecialchars($b['image']); ?>" alt="<?php echo $b['name']; ?>" loading="lazy" onerror="this.style.background='#122030'">
            <div class="strip-card-body">
                <span class="strip-card-title"><?php echo $b['name']; ?></span>
                <span class="strip-card-sub"><?php echo $b['project']; ?></span>
                <?php if ($b['damage'] > 0): ?>
                <span class="strip-card-sub" style="color:#ff9090">&#128293; <?php echo number_format($b['damage']); ?> damage</span>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <div style="margin-top:14px;text-align:right">
        <form class="lb-form" action="leaderboards.php" method="post">
            <input type="hidden" name="filterbybosses" value="weekly-bosses">
            <button class="lb-btn" type="submit">Weekly Boss Battles Leaderboard &rarr;</button>
        </form>
    </div>
</div>

<!-- ── Monstrocity ───────────────────────────────────────────────────── -->
<div class="profile-section">
    <div class="section-title"><span>&#9670; Monstrocity — Match 3 RPG</span><a href="monstrocity.php" target="_blank" class="section-title-link">Play Match 3 RPG &rarr;</a></div>
    <div class="activity-stats-row activity-stats-row-5">
        <div class="act-stat">
            <span class="act-stat-num" style="color:#c79fff"><?php echo number_format($mono_avg_score); ?></span>
            <span class="act-stat-lbl">Avg Score</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#f5c518"><?php echo number_format($mono_best_score); ?></span>
            <span class="act-stat-lbl">Best Score</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#c79fff"><?php echo number_format($mono_avg_level); ?></span>
            <span class="act-stat-lbl">Avg Level</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#f5c518"><?php echo number_format($mono_best_level); ?></span>
            <span class="act-stat-lbl">Best Level</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#00c8a0"><?php echo number_format($mono_completions); ?></span>
            <span class="act-stat-lbl">Completions</span>
        </div>
    </div>
    <div class="image-strip-section-label">
        <?php echo date('F'); ?> Campaign — Level <?php echo $mono_monthly_level; ?> / <?php echo count($campaign_config); ?>
    </div>
    <div class="image-strip strip-wide">
        <?php foreach ($campaign_opponents as $opp): ?>
        <div class="strip-card" style="<?php echo !$opp['defeated'] ? 'opacity:0.25;filter:grayscale(60%)' : ''; ?>">
            <img src="<?php echo htmlspecialchars($opp['image']); ?>" alt="<?php echo htmlspecialchars($opp['name']); ?>" loading="lazy" onerror="this.style.background='#122030'" style="<?php echo !$opp['defeated'] ? 'object-fit:contain;padding:10px;background:#0a1929' : ''; ?>">
            <div class="strip-card-body">
                <span class="strip-card-sub" style="color:<?php echo $opp['defeated'] ? '#3a6070' : '#2a4050'; ?>;font-size:0.55rem">LVL <?php echo $opp['level']; ?></span>
                <span class="strip-card-title"><?php echo $opp['defeated'] ? htmlspecialchars($opp['name']) : '???'; ?></span>
                <span class="strip-card-badge <?php echo $opp['type'] === 'Leader' ? 'badge-leader' : 'badge-base'; ?>">
                    <?php echo $opp['type']; ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="margin-top:14px;text-align:right">
        <form class="lb-form" action="leaderboards.php" method="post">
            <input type="hidden" name="filterbybosses" value="monthly-monstrocity">
            <button class="lb-btn" type="submit"><?php echo date('F'); ?> Monstrocity Leaderboard &rarr;</button>
        </form>
    </div>
</div>

<!-- ── Skull Swap ─────────────────────────────────────────────────────── -->
<?php if ($swap_total_swaps > 0 || $swap_best_score > 0): ?>
<div class="profile-section">
    <div class="section-title"><span>&#128128; Skull Swap</span><a href="skullswap.php" class="section-title-link">Play Skull Swap &rarr;</a></div>
    <div class="activity-stats-row" style="grid-template-columns: repeat(3, 1fr)">
        <div class="act-stat">
            <span class="act-stat-num" style="color:#f5c518"><?php echo number_format($swap_best_score); ?></span>
            <span class="act-stat-lbl">Best Score</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#c79fff"><?php echo number_format($swap_avg_score); ?></span>
            <span class="act-stat-lbl">Avg Score</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#00c8a0"><?php echo number_format($swap_total_swaps); ?></span>
            <span class="act-stat-lbl">Total Attempts</span>
        </div>
    </div>
    <div class="image-strip-section-label">Weekly Skull Swap</div>
    <div class="activity-stats-row" style="grid-template-columns: repeat(3, 1fr)">
        <div class="act-stat">
            <span class="act-stat-num" style="color:#f5c518"><?php echo number_format($swap_weekly_best); ?></span>
            <span class="act-stat-lbl">Best Score</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#c79fff"><?php echo number_format($swap_weekly_avg); ?></span>
            <span class="act-stat-lbl">Avg Score</span>
        </div>
        <div class="act-stat">
            <span class="act-stat-num" style="color:#00c8a0"><?php echo number_format($swap_weekly_attempts); ?></span>
            <span class="act-stat-lbl">Attempts</span>
        </div>
    </div>
    <div style="margin-top:14px;text-align:right">
        <form class="lb-form" action="leaderboards.php" method="post">
            <input type="hidden" name="filterbyswaps" value="weekly-swaps">
            <button class="lb-btn" type="submit">Weekly Skull Swap Leaderboard &rarr;</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- ── NFT Collection (full-width horizontal strip) ─────────────────────── -->
<div class="profile-section" id="nft-col">
    <div class="section-title">NFT Collection</div>
    <?php if ($show_nfts && !empty($gallery_nfts)): ?>
    <div class="nft-mosaic">
        <?php foreach ($gallery_nfts as $nft): ?>
        <div class="nft-thumb">
            <img src="<?php echo htmlspecialchars($nft['url']); ?>" alt="<?php echo $nft['name']; ?>" loading="lazy" onerror="this.closest('.nft-thumb').style.display='none'">
            <div class="nft-thumb-body">
                <span class="nft-thumb-name"><?php echo $nft['name']; ?></span>
                <span class="nft-thumb-sub"><?php echo $nft['project']; ?></span>
                <span class="nft-thumb-sub"><?php echo $nft['collection']; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <div style="margin-top:14px;text-align:right">
        <a href="showcase.php?username=<?php echo urlencode($profile_user['username']); ?>" class="lb-btn">View All NFTs &rarr;</a>
    </div>
    <?php else: ?>
    <div class="visibility-notice">
        &#128274;&nbsp; This user's collection is private.
        <?php if ($is_own_profile): ?>
        &nbsp;<a href="wallets.php" style="color:#00c8a0;text-decoration:none">Change in Wallets &rarr;</a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<!-- ── Rewards (redeemed store items, horizontal strip) ─────────────────── -->
<div class="profile-section">
    <div class="section-title">Rewards Claimed</div>
    <?php if (!empty($redeemed_items)): ?>
    <div class="image-strip">
        <?php foreach ($redeemed_items as $item): ?>
        <div class="reward-strip-card">
            <img src="<?php echo $item['image']; ?>" alt="<?php echo $item['name']; ?>" loading="lazy" onerror="this.src='/staking/icons/skull.png'">
            <div class="reward-strip-card-body">
                <span class="reward-strip-card-name"><?php echo $item['name']; ?></span>
                <span class="reward-strip-card-project"><?php echo $item['project']; ?></span>
                <span class="reward-strip-card-badge <?php echo $item['quantity'] === 0 ? 'badge-soldout' : 'badge-available'; ?>">
                    <?php echo $item['quantity'] === 0 ? 'Sold Out' : 'Available'; ?>
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php else: ?>
    <p class="no-data">No store items redeemed yet.</p>
    <?php endif; ?>
    <div style="margin-top:14px;text-align:right">
        <a href="store.php" class="lb-btn">View Store Items &rarr;</a>
    </div>
</div>

<!-- ── Points | Daily Rewards ────────────────────────────────────────────── -->
<div class="bottom-cols">

    <!-- Points -->
    <div class="bottom-col-points profile-section" style="margin-top:0;order:2">
        <div class="section-title">Points</div>
        <?php if (!empty($balances)): ?>
        <div class="currency-list" id="currency-list">
            <?php foreach ($balances as $b): ?>
            <div class="currency-row">
                <img class="currency-icon" src="icons/<?php echo strtolower(htmlspecialchars($b['currency'])); ?>.png" alt="" onerror="this.style.visibility='hidden'">
                <span class="currency-label"><?php echo strtoupper(htmlspecialchars($b['currency'])); ?> &ndash; <?php echo htmlspecialchars($b['project_name']); ?></span>
                <span class="currency-amount"><?php echo number_format((int)$b['balance']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="no-data">No points yet.</p>
        <?php endif; ?>
    </div>

    <!-- Left column: Calendars -->
    <div class="bottom-col-nfts" style="order:1">

        <!-- Daily Rewards Calendar -->
        <div class="profile-section" style="margin-top:0">
            <div class="section-title" style="margin-bottom:10px">Daily Rewards — Last 13 Weeks</div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
                <span style="font-size:0.78rem;color:#5a7888">
                    <?php echo number_format($streak_days); ?> total claims
                    <?php if (!empty($claim_days)): ?>&nbsp;·&nbsp; <?php echo count($claim_days); ?> in last 91 days<?php endif; ?>
                </span>
                <div class="cal-legend">
                    <div class="legend-swatch" style="background:#152230"></div> Missed
                    <div class="legend-swatch" style="background:#00c8a0"></div> Claimed
                </div>
            </div>

            <?php
            $today   = new DateTime();
            $start   = clone $today;
            $start->modify('-90 days');
            $dow     = (int)$start->format('w');
            $start->modify("-{$dow} days");

            $month_labels = [];
            $prev_month   = '';
            for ($w = 0; $w < 13; $w++) {
                $d = clone $start;
                $d->modify("+{$w} weeks");
                $m = $d->format('M');
                if ($m !== $prev_month) {
                    $month_labels[$w] = $m . ' ' . $d->format('Y');
                    $prev_month = $m;
                }
            }
            $dow_labels = ['Sun','Mon','Tue','Wed','Thu','Fri','Sat'];
            ?>

            <div class="calendar-outer">
                <div class="cal-day-labels">
                    <?php foreach ($dow_labels as $lbl): ?>
                    <div class="cal-day-label"><?php echo $lbl; ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="cal-weeks-wrap">
                    <div class="cal-month-row">
                        <?php for ($w = 0; $w < 13; $w++): ?>
                        <div class="cal-month-label"><?php echo isset($month_labels[$w]) ? $month_labels[$w] : ''; ?></div>
                        <?php endfor; ?>
                    </div>
                    <div class="calendar-grid">
                        <?php for ($week = 0; $week < 13; $week++): ?>
                        <div class="calendar-week">
                            <?php for ($day = 0; $day < 7; $day++):
                                $d   = clone $start;
                                $d->modify("+{$week} weeks +{$day} days");
                                $key = $d->format('Y-m-d');
                                $dname = $dow_labels[$day];
                                $ddate = $d->format('M j, Y');
                                if ($d > $today) {
                                    $cls   = 'calendar-day future';
                                    $title = '';
                                } elseif (isset($claim_days[$key])) {
                                    $cls   = 'calendar-day claimed';
                                    $title = "&#10003; Claimed — {$dname}, {$ddate}";
                                } else {
                                    $cls   = 'calendar-day missed';
                                    $title = "Missed — {$dname}, {$ddate}";
                                }
                            ?>
                            <div class="<?php echo $cls; ?>" title="<?php echo $title; ?>"></div>
                            <?php endfor; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div style="margin-top:14px;text-align:right">
                <form class="lb-form" action="leaderboards.php" method="post">
                    <input type="hidden" name="filterbystreak" value="monthly-streaks">
                    <button class="lb-btn" type="submit"><?php echo date('F'); ?> Streaks Leaderboard &rarr;</button>
                </form>
            </div>
        </div>

        <!-- Missions Calendar -->
        <div class="profile-section">
            <div class="section-title" style="margin-bottom:10px">Missions — Last 13 Weeks</div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
                <span style="font-size:0.78rem;color:#5a7888">
                    <?php echo number_format($mission_cal_total); ?> active day<?php echo $mission_cal_total !== 1 ? 's' : ''; ?> in last 91 days
                </span>
                <div class="cal-legend">
                    <div class="legend-swatch" style="background:#152230"></div> None
                    <div class="legend-swatch" style="background:#2a7fff"></div> Active
                </div>
            </div>
            <div class="calendar-outer">
                <div class="cal-day-labels">
                    <?php foreach ($dow_labels as $lbl): ?>
                    <div class="cal-day-label"><?php echo $lbl; ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="cal-weeks-wrap">
                    <div class="cal-month-row">
                        <?php for ($w = 0; $w < 13; $w++): ?>
                        <div class="cal-month-label"><?php echo isset($month_labels[$w]) ? $month_labels[$w] : ''; ?></div>
                        <?php endfor; ?>
                    </div>
                    <div class="calendar-grid">
                        <?php for ($week = 0; $week < 13; $week++): ?>
                        <div class="calendar-week">
                            <?php for ($day = 0; $day < 7; $day++):
                                $d   = clone $start;
                                $d->modify("+{$week} weeks +{$day} days");
                                $key = $d->format('Y-m-d');
                                $dname = $dow_labels[$day];
                                $ddate = $d->format('M j, Y');
                                if ($d > $today) {
                                    $cls   = 'calendar-day future';
                                    $title = '';
                                } elseif (isset($mission_days[$key])) {
                                    $cls   = 'calendar-day';
                                    $title = "&#10003; Mission day — {$dname}, {$ddate}";
                                } else {
                                    $cls   = 'calendar-day missed';
                                    $title = "No mission — {$dname}, {$ddate}";
                                }
                            ?>
                            <div class="<?php echo $cls; ?>" style="<?php echo (isset($mission_days[$key]) && !($d > $today)) ? 'background:#2a7fff' : ''; ?>" title="<?php echo $title; ?>"></div>
                            <?php endfor; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div style="margin-top:14px;text-align:right">
                <a href="missions.php" class="lb-btn">Go to Missions &rarr;</a>
            </div>
        </div>

        <!-- Raids Calendar -->
        <div class="profile-section">
            <div class="section-title" style="margin-bottom:10px">Raids — Last 13 Weeks</div>
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:8px;flex-wrap:wrap;gap:8px">
                <span style="font-size:0.78rem;color:#5a7888">
                    <?php echo number_format($raid_cal_total); ?> raid day<?php echo $raid_cal_total !== 1 ? 's' : ''; ?> in last 91 days
                </span>
                <div class="cal-legend">
                    <div class="legend-swatch" style="background:#152230"></div> None
                    <div class="legend-swatch" style="background:#e05050"></div> Raided
                </div>
            </div>
            <div class="calendar-outer">
                <div class="cal-day-labels">
                    <?php foreach ($dow_labels as $lbl): ?>
                    <div class="cal-day-label"><?php echo $lbl; ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="cal-weeks-wrap">
                    <div class="cal-month-row">
                        <?php for ($w = 0; $w < 13; $w++): ?>
                        <div class="cal-month-label"><?php echo isset($month_labels[$w]) ? $month_labels[$w] : ''; ?></div>
                        <?php endfor; ?>
                    </div>
                    <div class="calendar-grid">
                        <?php for ($week = 0; $week < 13; $week++): ?>
                        <div class="calendar-week">
                            <?php for ($day = 0; $day < 7; $day++):
                                $d   = clone $start;
                                $d->modify("+{$week} weeks +{$day} days");
                                $key = $d->format('Y-m-d');
                                $dname = $dow_labels[$day];
                                $ddate = $d->format('M j, Y');
                                if ($d > $today) {
                                    $cls   = 'calendar-day future';
                                    $title = '';
                                } elseif (isset($raid_days[$key])) {
                                    $cls   = 'calendar-day';
                                    $title = "&#9876; Raid day — {$dname}, {$ddate}";
                                } else {
                                    $cls   = 'calendar-day missed';
                                    $title = "No raid — {$dname}, {$ddate}";
                                }
                            ?>
                            <div class="<?php echo $cls; ?>" style="<?php echo (isset($raid_days[$key]) && !($d > $today)) ? 'background:#e05050' : ''; ?>" title="<?php echo $title; ?>"></div>
                            <?php endfor; ?>
                        </div>
                        <?php endfor; ?>
                    </div>
                </div>
            </div>
            <div style="margin-top:14px;text-align:right">
                <a href="realms.php" class="lb-btn">Go to Realms &rarr;</a>
            </div>
        </div>

    </div><!-- /.bottom-col-nfts -->

</div><!-- /.bottom-cols -->

</div><!-- /.profile-wrap -->
</div><!-- /.row -->

<!-- Footer -->
<div class="footer">
    <p>Skulliance<br>Copyright &copy; <span id="year"></span></p>
</div>
</div>
</div>
</body>

<script>
// Copy profile link
function copyProfileLink() {
    const url = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '/profile.php') + '?username=<?php echo urlencode($profile_user['username']); ?>';
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.getElementById('share-btn');
        btn.textContent = '✓ Copied!';
        btn.classList.add('copied');
        setTimeout(() => { btn.innerHTML = '&#128279; Share'; btn.classList.remove('copied'); }, 2000);
    }).catch(() => {
        const ta = document.createElement('textarea');
        ta.value = window.location.href.split('?')[0] + '?username=<?php echo urlencode($profile_user['username']); ?>';
        document.body.appendChild(ta); ta.select(); document.execCommand('copy'); document.body.removeChild(ta);
    });
}

// Count-up animation
document.addEventListener('DOMContentLoaded', () => {
    // Footer year
    const yr = document.getElementById('year');
    if (yr) yr.textContent = new Date().getFullYear();

    // Stat number count-up
    const stats = document.querySelectorAll('.stat-number[data-count]');
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            const el     = entry.target;
            const target = parseInt(el.getAttribute('data-count').replace(/,/g, ''), 10);
            if (isNaN(target) || target === 0) return;
            const duration = 900;
            const start    = performance.now();
            function tick(now) {
                const progress = Math.min((now - start) / duration, 1);
                const ease     = 1 - Math.pow(1 - progress, 3);
                el.textContent = Math.round(ease * target).toLocaleString();
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
            obs.unobserve(el);
        });
    }, { threshold: 0.3 });
    stats.forEach(el => obs.observe(el));
});
</script>

<?php $conn->close(); ?>
</html>
