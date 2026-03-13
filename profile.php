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
    // username is set after checkUser() runs; name is always set from Discord login
    $profile_username = $_SESSION['userData']['username'] ?? $_SESSION['userData']['name'] ?? '';
}

// Redirect to error if no target
if ($profile_username === '') {
    header('Location: error.php');
    exit();
}

// Load profile user
$safe_username = mysqli_real_escape_string($conn, $profile_username);
$user_sql = "SELECT id, discord_id, avatar, username, streak, date_created, visibility FROM users WHERE username = '$safe_username' LIMIT 1";
$user_result = $conn->query($user_sql);

if (!$user_result || $user_result->num_rows === 0) {
    // If viewing own profile and not found yet, send to dashboard so checkUser() can register them
    if (!isset($_GET['username']) && isset($_SESSION['logged_in'])) {
        header('Location: dashboard.php');
        exit();
    }
    include 'header.php';
    echo "<div class='row' id='row1'><div class='main'><h2>Profile Not Found</h2><p style='color:#aaa'>No user found with the username <strong>" . htmlspecialchars($profile_username) . "</strong>.</p></div></div></div></div></body></html>";
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

// ── Stats ──────────────────────────────────────────────────────────────────

$missions_result    = $conn->query("SELECT COUNT(*) as c FROM missions WHERE user_id='$tid' AND status='1'");
$missions_completed = $missions_result ? (int)$missions_result->fetch_assoc()['c'] : 0;

$missions_total_r   = $conn->query("SELECT COUNT(*) as c FROM missions WHERE user_id='$tid'");
$missions_total     = $missions_total_r ? (int)$missions_total_r->fetch_assoc()['c'] : 0;
$missions_failed    = max(0, $missions_total - $missions_completed);

$raid_sql = "SELECT
    COUNT(*) as total,
    SUM(CASE WHEN outcome='1' AND o_realm.user_id='$tid' THEN 1
             WHEN outcome='0' AND d_realm.user_id='$tid' THEN 1
             ELSE 0 END) as wins
    FROM raids
    INNER JOIN realms AS o_realm ON o_realm.id = raids.offense_id
    INNER JOIN realms AS d_realm ON d_realm.id = raids.defense_id
    WHERE o_realm.user_id='$tid' OR d_realm.user_id='$tid'";
$raid_result  = $conn->query($raid_sql);
$raid_stats   = $raid_result ? $raid_result->fetch_assoc() : ['total' => 0, 'wins' => 0];
$raid_total   = (int)($raid_stats['total'] ?? 0);
$raid_wins    = (int)($raid_stats['wins'] ?? 0);
$raid_losses  = max(0, $raid_total - $raid_wins);

$boss_result  = $conn->query("SELECT COUNT(*) as total, COALESCE(SUM(damage_dealt),0) as damage FROM encounters WHERE user_id='$tid'");
$boss_stats   = $boss_result ? $boss_result->fetch_assoc() : ['total' => 0, 'damage' => 0];
$boss_total   = (int)($boss_stats['total'] ?? 0);
$boss_damage  = (int)($boss_stats['damage'] ?? 0);

$mono_r       = $conn->query("SELECT COALESCE(MAX(score),0) as best FROM scores WHERE user_id='$tid' AND reward='0'");
$mono_score   = $mono_r ? (int)$mono_r->fetch_assoc()['best'] : 0;

$mono_lvl_r   = $conn->query("SELECT COALESCE(MAX(level),0) as lvl FROM progress WHERE user_id='$tid'");
$mono_level   = $mono_lvl_r ? (int)$mono_lvl_r->fetch_assoc()['lvl'] : 0;

$streak_r     = $conn->query("SELECT COUNT(*) as c FROM transactions WHERE user_id='$tid' AND bonus='1'");
$streak_days  = $streak_r ? (int)$streak_r->fetch_assoc()['c'] : 0;

$pts_r        = $conn->query("SELECT COALESCE(SUM(balance),0) as total FROM balances WHERE user_id='$tid'");
$total_points = $pts_r ? (int)$pts_r->fetch_assoc()['total'] : 0;

// ── Balances ───────────────────────────────────────────────────────────────

$balances = [];
$bal_sql  = "SELECT p.name as project_name, p.currency, b.balance, p.id as project_id
    FROM balances b INNER JOIN projects p ON p.id = b.project_id
    WHERE b.user_id='$tid' AND b.balance > 0 ORDER BY b.balance DESC";
$bal_result = $conn->query($bal_sql);
if ($bal_result && $bal_result->num_rows > 0) {
    while ($row = $bal_result->fetch_assoc()) { $balances[] = $row; }
}

// ── Streak Calendar (13 weeks) ─────────────────────────────────────────────

$claim_days = [];
$cal_sql    = "SELECT DATE(date_created) as day FROM transactions WHERE user_id='$tid' AND bonus='1' AND date_created >= DATE_SUB(CURDATE(), INTERVAL 91 DAY) GROUP BY DATE(date_created)";
$cal_result = $conn->query($cal_sql);
if ($cal_result && $cal_result->num_rows > 0) {
    while ($row = $cal_result->fetch_assoc()) { $claim_days[$row['day']] = true; }
}

// ── Realm ──────────────────────────────────────────────────────────────────

$realm = null;
$realm_sql = "SELECT r.name as realm_name, p.name as project_name, p.currency FROM realms r
    INNER JOIN projects p ON p.id = r.project_id WHERE r.user_id='$tid' AND r.active='1' LIMIT 1";
$realm_result = $conn->query($realm_sql);
if ($realm_result && $realm_result->num_rows > 0) { $realm = $realm_result->fetch_assoc(); }

// ── Hero Background NFTs (random sample) ──────────────────────────────────

$hero_nfts = [];
$hero_sql  = "SELECT ipfs, nfts.name as nft_name, collection_id FROM nfts WHERE user_id='$tid' ORDER BY RAND() LIMIT 9";
$hero_r    = $conn->query($hero_sql);
if ($hero_r && $hero_r->num_rows > 0) {
    while ($row = $hero_r->fetch_assoc()) {
        $hero_nfts[] = ['url' => getIPFS($row['ipfs'], $row['collection_id']), 'name' => htmlspecialchars($row['nft_name'])];
    }
}

// ── NFT Gallery ────────────────────────────────────────────────────────────

$gallery_nfts = [];
if ($show_nfts) {
    $gal_sql = "SELECT ipfs, nfts.name as nft_name, collection_id, p.name as project_name, p.currency
        FROM nfts INNER JOIN collections c ON c.id = nfts.collection_id INNER JOIN projects p ON p.id = c.project_id
        WHERE nfts.user_id='$tid' ORDER BY c.project_id LIMIT 24";
    $gal_result = $conn->query($gal_sql);
    if ($gal_result && $gal_result->num_rows > 0) {
        while ($row = $gal_result->fetch_assoc()) {
            $gallery_nfts[] = [
                'url'     => getIPFS($row['ipfs'], $row['collection_id']),
                'name'    => htmlspecialchars($row['nft_name']),
                'project' => htmlspecialchars($row['project_name']),
                'currency'=> strtolower($row['currency'])
            ];
        }
    }
}

// ── Recent Raid Opponents ──────────────────────────────────────────────────

$opponents = [];
$opp_sql = "SELECT DISTINCT u.discord_id, u.avatar, u.username
    FROM raids r
    INNER JOIN realms o ON o.id = r.offense_id
    INNER JOIN realms d ON d.id = r.defense_id
    INNER JOIN users u ON u.id = IF(o.user_id='$tid', d.user_id, o.user_id)
    WHERE o.user_id='$tid' OR d.user_id='$tid'
    ORDER BY r.created_date DESC LIMIT 8";
$opp_result = $conn->query($opp_sql);
if ($opp_result && $opp_result->num_rows > 0) {
    while ($row = $opp_result->fetch_assoc()) { $opponents[] = $row; }
}

// ── Core Project Holdings (for membership badges) ─────────────────────────

$held_core = [];
$core_sql  = "SELECT DISTINCT c.project_id FROM nfts INNER JOIN collections c ON c.id = nfts.collection_id WHERE nfts.user_id='$tid' AND c.project_id BETWEEN 1 AND 7";
$core_r    = $conn->query($core_sql);
if ($core_r && $core_r->num_rows > 0) {
    while ($row = $core_r->fetch_assoc()) { $held_core[] = (int)$row['project_id']; }
}
$has_diamond = in_array(7, $held_core);
$core_count  = count(array_filter($held_core, fn($p) => $p < 7));
$is_elite    = $core_count >= 6;
$is_member   = $core_count >= 3;

include 'header.php';
?>

<style>
/* ── Profile Page Styles ─────────────────────────────────────────────────── */

.profile-wrap {
    width: 100%;
    max-width: 1100px;
    margin: 0 auto;
    padding: 0 16px 60px;
    box-sizing: border-box;
    color: #e8eaed;
    font-family: Arial, sans-serif;
}

/* ── Hero ── */
.profile-hero {
    position: relative;
    width: 100%;
    min-height: 260px;
    border-radius: 12px;
    overflow: hidden;
    margin-bottom: 0;
    background: #0a1929;
}

.hero-mosaic {
    position: absolute;
    inset: 0;
    display: grid;
    grid-template-columns: repeat(9, 1fr);
    gap: 2px;
    opacity: 0.22;
    filter: blur(1px);
    transform: scale(1.04);
}

.hero-mosaic img {
    width: 100%;
    height: 140px;
    object-fit: cover;
    display: block;
}

.hero-overlay {
    position: absolute;
    inset: 0;
    background: linear-gradient(160deg, rgba(0,47,68,0.70) 0%, rgba(13,20,30,0.92) 100%);
}

.hero-content {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: flex-end;
    gap: 22px;
    padding: 36px 28px 0;
}

.hero-avatar-wrap {
    flex-shrink: 0;
    position: relative;
    bottom: -28px;
}

.hero-avatar {
    width: 100px;
    height: 100px;
    border-radius: 50%;
    border: 4px solid #00c8a0;
    box-shadow: 0 0 24px rgba(0,200,160,0.4);
    background: #0a1929;
    object-fit: cover;
    display: block;
}

.hero-text {
    padding-bottom: 14px;
    flex: 1;
    min-width: 0;
}

.hero-username {
    font-size: 1.8rem;
    font-weight: bold;
    color: #fff;
    margin: 0 0 8px;
    text-shadow: 0 2px 8px rgba(0,0,0,0.8);
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.hero-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 10px;
    align-items: center;
}

.badge {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    padding: 3px 10px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: bold;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}
.badge-member    { background: rgba(180,180,200,0.18); color: #c8d0e0; border: 1px solid #555; }
.badge-elite     { background: rgba(245,197,24,0.15);  color: #f5c518; border: 1px solid #f5c518; }
.badge-diamond   { background: rgba(138,86,255,0.18);  color: #c79fff; border: 1px solid #9b59b6; }
.badge-realm     { background: rgba(0,200,160,0.12);   color: #00c8a0; border: 1px solid #00c8a0; }
.badge-since     { background: rgba(255,255,255,0.06); color: #8899aa; border: 1px solid #2a3a4a; font-weight: normal; text-transform: none; font-size: 0.75rem; }

/* ── Section card ── */
.profile-section {
    background: rgba(12, 28, 42, 0.85);
    border: 1px solid rgba(0,200,160,0.10);
    border-radius: 10px;
    padding: 22px 24px;
    margin-top: 18px;
}

.section-title {
    font-size: 0.75rem;
    font-weight: bold;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #00c8a0;
    margin: 0 0 16px;
    padding-bottom: 8px;
    border-bottom: 1px solid rgba(0,200,160,0.15);
}

/* ── Stat grid ── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(130px, 1fr));
    gap: 12px;
    margin-top: 18px;
}

.stat-card {
    background: rgba(22, 87, 119, 0.25);
    border: 1px solid rgba(0,200,160,0.12);
    border-radius: 8px;
    padding: 16px 14px 14px;
    text-align: center;
    transition: border-color 0.2s, background 0.2s;
}

.stat-card:hover {
    border-color: rgba(0,200,160,0.35);
    background: rgba(22, 87, 119, 0.40);
}

.stat-number {
    font-size: 1.7rem;
    font-weight: bold;
    color: #fff;
    line-height: 1;
    display: block;
    margin-bottom: 5px;
}

.stat-number.gold   { color: #f5c518; }
.stat-number.teal   { color: #00c8a0; }
.stat-number.purple { color: #c79fff; }
.stat-number.coral  { color: #ff7f7f; }

.stat-label {
    font-size: 0.68rem;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #6a8090;
}

/* ── Two-col layout ── */
.profile-cols {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 18px;
    margin-top: 18px;
}

@media (max-width: 680px) {
    .profile-cols { grid-template-columns: 1fr; }
}

/* ── Currencies ── */
.currency-list {
    display: flex;
    flex-direction: column;
    gap: 9px;
}

.currency-row {
    display: flex;
    align-items: center;
    gap: 10px;
    background: rgba(22,87,119,0.18);
    border-radius: 6px;
    padding: 7px 12px;
    transition: background 0.15s;
}

.currency-row:hover { background: rgba(22,87,119,0.32); }

.currency-icon {
    width: 24px;
    height: 24px;
    object-fit: contain;
    flex-shrink: 0;
}

.currency-name {
    flex: 1;
    font-size: 0.82rem;
    color: #aac0cc;
}

.currency-amount {
    font-weight: bold;
    font-size: 0.9rem;
    color: #e8eaed;
    white-space: nowrap;
}

/* ── Activity breakdown ── */
.activity-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.activity-card {
    background: rgba(22,87,119,0.20);
    border: 1px solid rgba(0,200,160,0.08);
    border-radius: 8px;
    padding: 14px;
}

.activity-card-title {
    font-size: 0.68rem;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: #5a7888;
    margin-bottom: 8px;
}

.activity-stat-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 4px;
}

.activity-stat-label {
    font-size: 0.78rem;
    color: #7a9aaa;
}

.activity-stat-val {
    font-size: 0.88rem;
    font-weight: bold;
    color: #e0e8ee;
}

/* ── Streak Calendar ── */
.calendar-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 8px;
}

.calendar-legend {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 0.7rem;
    color: #5a7888;
}

.legend-swatch {
    width: 10px;
    height: 10px;
    border-radius: 2px;
}

.calendar-days-label {
    display: grid;
    grid-template-columns: repeat(13, 1fr);
    gap: 4px;
    margin-bottom: 3px;
    font-size: 0.58rem;
    color: #3a5060;
    text-align: center;
}

.calendar-grid {
    display: grid;
    grid-template-columns: repeat(13, 1fr);
    gap: 4px;
}

.calendar-week {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.calendar-day {
    width: 100%;
    aspect-ratio: 1;
    border-radius: 3px;
    cursor: default;
    transition: transform 0.1s;
}

.calendar-day:hover { transform: scale(1.3); z-index: 1; position: relative; }
.calendar-day.empty  { background: #0e1e2a; }
.calendar-day.missed { background: #152230; }
.calendar-day.claimed { background: #00c8a0; box-shadow: 0 0 6px rgba(0,200,160,0.5); }

/* ── NFT Gallery ── */
.nft-gallery-scroll {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 8px;
    scrollbar-width: thin;
    scrollbar-color: #00c8a0 #0e1e2a;
}

.nft-gallery-scroll::-webkit-scrollbar { height: 5px; }
.nft-gallery-scroll::-webkit-scrollbar-track { background: #0e1e2a; border-radius: 3px; }
.nft-gallery-scroll::-webkit-scrollbar-thumb { background: #00c8a0; border-radius: 3px; }

.gallery-item {
    flex-shrink: 0;
    width: 130px;
    background: rgba(22,87,119,0.22);
    border: 1px solid rgba(0,200,160,0.10);
    border-radius: 8px;
    overflow: hidden;
    transition: border-color 0.2s, transform 0.2s;
    cursor: pointer;
}

.gallery-item:hover {
    border-color: #00c8a0;
    transform: translateY(-3px);
}

.gallery-item img {
    width: 100%;
    height: 130px;
    object-fit: cover;
    display: block;
}

.gallery-item-label {
    padding: 6px 8px;
    font-size: 0.65rem;
    color: #7a9aaa;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

/* ── Opponents ── */
.opponents-row {
    display: flex;
    flex-wrap: wrap;
    gap: 14px;
    align-items: center;
}

.opponent-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    text-decoration: none;
    transition: transform 0.15s;
}

.opponent-item:hover { transform: translateY(-2px); }

.opponent-avatar {
    width: 48px;
    height: 48px;
    border-radius: 50%;
    border: 2px solid rgba(0,200,160,0.35);
    object-fit: cover;
    display: block;
    background: #0a1929;
}

.opponent-name {
    font-size: 0.62rem;
    color: #6a8090;
    text-align: center;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 56px;
    text-decoration: none;
}

.no-data {
    color: #3a5060;
    font-size: 0.82rem;
    font-style: italic;
    text-align: center;
    padding: 10px 0;
}

/* ── Visibility notice ── */
.visibility-notice {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 16px;
    background: rgba(22,87,119,0.18);
    border: 1px dashed rgba(0,200,160,0.2);
    border-radius: 8px;
    color: #5a7888;
    font-size: 0.8rem;
}

/* ── Own profile edit link ── */
.own-profile-bar {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 12px;
    margin-bottom: 4px;
    font-size: 0.78rem;
    color: #4a6070;
}

.own-profile-bar a {
    color: #00c8a0;
    text-decoration: none;
    border: 1px solid rgba(0,200,160,0.3);
    padding: 4px 12px;
    border-radius: 20px;
    transition: background 0.15s;
}

.own-profile-bar a:hover {
    background: rgba(0,200,160,0.12);
}

/* ── Share button ── */
.share-btn {
    background: rgba(0,200,160,0.12);
    border: 1px solid rgba(0,200,160,0.35);
    color: #00c8a0;
    padding: 5px 16px;
    border-radius: 20px;
    cursor: pointer;
    font-size: 0.78rem;
    transition: background 0.15s;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 5px;
}

.share-btn:hover { background: rgba(0,200,160,0.22); color: #00c8a0; }
.share-btn.copied { color: #f5c518; border-color: #f5c518; }
</style>

<div class="row" id="row1">
<div class="profile-wrap">

<?php if ($is_own_profile): ?>
<div class="own-profile-bar">
    Your public profile &nbsp;·&nbsp;
    <a href="wallets.php">Change visibility</a>
</div>
<?php endif; ?>

<!-- ── Hero ─────────────────────────────────────────────────────────── -->
<div class="profile-hero">
    <div class="hero-mosaic">
        <?php foreach ($hero_nfts as $hn): ?>
            <img src="<?php echo htmlspecialchars($hn['url']); ?>" alt="" loading="lazy" onerror="this.style.display='none'">
        <?php endforeach; ?>
        <?php for ($i = count($hero_nfts); $i < 9; $i++): ?>
            <div style="background:#0a1929;"></div>
        <?php endfor; ?>
    </div>
    <div class="hero-overlay"></div>
    <div class="hero-content">
        <div class="hero-avatar-wrap">
            <img class="hero-avatar" src="<?php echo $profile_avatar; ?>?size=128" alt="<?php echo $display_name; ?>" onerror="this.src='icons/skull.png'">
        </div>
        <div class="hero-text">
            <div class="hero-username"><?php echo $display_name; ?></div>
            <div class="hero-meta">
                <?php if ($has_diamond): ?>
                    <span class="badge badge-diamond">&#9670; Diamond Skull</span>
                <?php endif; ?>
                <?php if ($is_elite): ?>
                    <span class="badge badge-elite">&#9733; Elite</span>
                <?php elseif ($is_member): ?>
                    <span class="badge badge-member">&#9670; Member</span>
                <?php endif; ?>
                <?php if ($realm): ?>
                    <span class="badge badge-realm">&#9956; <?php echo htmlspecialchars($realm['realm_name']); ?></span>
                <?php endif; ?>
                <span class="badge badge-since">Since <?php echo $member_since; ?></span>
                <button class="share-btn" id="share-btn" onclick="copyProfileLink()">&#128279; Share</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Key Stats ──────────────────────────────────────────────────────── -->
<div class="stat-grid">
    <div class="stat-card">
        <span class="stat-number gold" data-count="<?php echo number_format($total_points); ?>"><?php echo number_format($total_points); ?></span>
        <span class="stat-label">Total Points</span>
    </div>
    <div class="stat-card">
        <span class="stat-number teal" data-count="<?php echo $missions_completed; ?>"><?php echo $missions_completed; ?></span>
        <span class="stat-label">Missions Done</span>
    </div>
    <div class="stat-card">
        <span class="stat-number" data-count="<?php echo $raid_wins; ?>"><?php echo $raid_wins; ?></span>
        <span class="stat-label">Raid Wins</span>
    </div>
    <div class="stat-card">
        <span class="stat-number coral" data-count="<?php echo $boss_damage; ?>"><?php echo number_format($boss_damage); ?></span>
        <span class="stat-label">Boss Damage</span>
    </div>
    <div class="stat-card">
        <span class="stat-number purple" data-count="<?php echo number_format($mono_score); ?>"><?php echo number_format($mono_score); ?></span>
        <span class="stat-label">Best Score</span>
    </div>
    <div class="stat-card">
        <span class="stat-number teal" data-count="<?php echo $streak_days; ?>"><?php echo $streak_days; ?></span>
        <span class="stat-label">Days Claimed</span>
    </div>
</div>

<!-- ── Two-column: Currencies + Activity ─────────────────────────────── -->
<div class="profile-cols">

    <!-- Currencies -->
    <div class="profile-section">
        <div class="section-title">Currencies</div>
        <?php if (!empty($balances)): ?>
        <div class="currency-list">
            <?php foreach ($balances as $b): ?>
            <div class="currency-row">
                <img class="currency-icon" src="icons/<?php echo strtolower(htmlspecialchars($b['currency'])); ?>.png" alt="" onerror="this.style.visibility='hidden'">
                <span class="currency-name"><?php echo htmlspecialchars($b['project_name']); ?></span>
                <span class="currency-amount"><?php echo number_format((int)$b['balance']); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <p class="no-data">No balances yet.</p>
        <?php endif; ?>
    </div>

    <!-- Activity Breakdown -->
    <div class="profile-section">
        <div class="section-title">Activity</div>
        <div class="activity-grid">

            <div class="activity-card">
                <div class="activity-card-title">&#9876; Missions</div>
                <div class="activity-stat-row"><span class="activity-stat-label">Completed</span><span class="activity-stat-val" style="color:#00c8a0"><?php echo $missions_completed; ?></span></div>
                <div class="activity-stat-row"><span class="activity-stat-label">Total Sent</span><span class="activity-stat-val"><?php echo $missions_total; ?></span></div>
                <?php if ($missions_total > 0): ?>
                <div class="activity-stat-row"><span class="activity-stat-label">Success Rate</span><span class="activity-stat-val"><?php echo round(($missions_completed / $missions_total) * 100); ?>%</span></div>
                <?php endif; ?>
            </div>

            <div class="activity-card">
                <div class="activity-card-title">&#9876; Raids</div>
                <div class="activity-stat-row"><span class="activity-stat-label">Total</span><span class="activity-stat-val"><?php echo $raid_total; ?></span></div>
                <div class="activity-stat-row"><span class="activity-stat-label">Wins</span><span class="activity-stat-val" style="color:#00c8a0"><?php echo $raid_wins; ?></span></div>
                <div class="activity-stat-row"><span class="activity-stat-label">Losses</span><span class="activity-stat-val" style="color:#ff7f7f"><?php echo $raid_losses; ?></span></div>
            </div>

            <div class="activity-card">
                <div class="activity-card-title">&#128296; Bosses</div>
                <div class="activity-stat-row"><span class="activity-stat-label">Encounters</span><span class="activity-stat-val"><?php echo $boss_total; ?></span></div>
                <div class="activity-stat-row"><span class="activity-stat-label">Dmg Dealt</span><span class="activity-stat-val" style="color:#ff7f7f"><?php echo number_format($boss_damage); ?></span></div>
            </div>

            <div class="activity-card">
                <div class="activity-card-title">&#9670; Monstrocity</div>
                <div class="activity-stat-row"><span class="activity-stat-label">Best Score</span><span class="activity-stat-val" style="color:#c79fff"><?php echo number_format($mono_score); ?></span></div>
                <div class="activity-stat-row"><span class="activity-stat-label">Level Reached</span><span class="activity-stat-val"><?php echo $mono_level; ?></span></div>
            </div>

        </div>
    </div>
</div>

<!-- ── Streak Calendar ────────────────────────────────────────────────── -->
<div class="profile-section">
    <div class="section-title" style="margin-bottom:10px">Daily Rewards — Last 13 Weeks</div>
    <div class="calendar-header">
        <span style="font-size:0.78rem;color:#5a7888">
            <?php echo $streak_days; ?> total claims
            <?php if (!empty($claim_days)): ?>
            &nbsp;·&nbsp; <?php echo count($claim_days); ?> in last 91 days
            <?php endif; ?>
        </span>
        <div class="calendar-legend">
            <div class="legend-swatch" style="background:#152230;"></div> Missed
            <div class="legend-swatch" style="background:#00c8a0;"></div> Claimed
        </div>
    </div>
    <div class="calendar-grid">
        <?php
        // Build 13 weeks × 7 days grid ending today
        $today     = new DateTime();
        $start     = clone $today;
        $start->modify('-90 days');
        // Rewind to the start of that week (Sunday=0)
        $dow       = (int)$start->format('w'); // 0=Sun
        $start->modify("-{$dow} days");

        for ($week = 0; $week < 13; $week++):
        ?>
        <div class="calendar-week">
            <?php for ($day = 0; $day < 7; $day++):
                $d   = clone $start;
                $d->modify("+{$week} weeks +{$day} days");
                $key = $d->format('Y-m-d');
                if ($d > $today) {
                    $cls = 'calendar-day empty';
                    $title = '';
                } elseif (isset($claim_days[$key])) {
                    $cls = 'calendar-day claimed';
                    $title = 'Claimed ' . $d->format('M j');
                } else {
                    $cls = 'calendar-day missed';
                    $title = $d->format('M j');
                }
            ?>
            <div class="<?php echo $cls; ?>" title="<?php echo $title; ?>"></div>
            <?php endfor; ?>
        </div>
        <?php endfor; ?>
    </div>
</div>

<!-- ── NFT Gallery ────────────────────────────────────────────────────── -->
<?php if ($show_nfts && !empty($gallery_nfts)): ?>
<div class="profile-section">
    <div class="section-title">NFT Collection</div>
    <div class="nft-gallery-scroll">
        <?php foreach ($gallery_nfts as $nft): ?>
        <div class="gallery-item" title="<?php echo $nft['name']; ?>">
            <img src="<?php echo htmlspecialchars($nft['url']); ?>" alt="<?php echo $nft['name']; ?>" loading="lazy" onerror="this.src='icons/skull.png'">
            <div class="gallery-item-label"><?php echo $nft['name']; ?></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php if ($missions_total > 24 || count($gallery_nfts) >= 24): ?>
    <div style="text-align:right;margin-top:8px">
        <a href="showcase.php?username=<?php echo urlencode($profile_user['username']); ?>" style="font-size:0.78rem;color:#00c8a0;text-decoration:none">View full collection &rarr;</a>
    </div>
    <?php endif; ?>
</div>
<?php elseif (!$show_nfts): ?>
<div class="profile-section">
    <div class="section-title">NFT Collection</div>
    <div class="visibility-notice">
        &#128274;&nbsp; This user has set their collection to private.
        <?php if ($is_own_profile): ?>
        &nbsp;<a href="wallets.php" style="color:#00c8a0;text-decoration:none">Change in Wallets &rarr;</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- ── Recent Raid Opponents ─────────────────────────────────────────── -->
<?php if (!empty($opponents)): ?>
<div class="profile-section">
    <div class="section-title">Recent Raid Opponents</div>
    <div class="opponents-row">
        <?php foreach ($opponents as $opp):
            $opp_avatar = "https://cdn.discordapp.com/avatars/{$opp['discord_id']}/{$opp['avatar']}.png";
            $opp_name   = htmlspecialchars($opp['username']);
        ?>
        <a href="profile.php?username=<?php echo urlencode($opp['username']); ?>" class="opponent-item" title="<?php echo $opp_name; ?>">
            <img class="opponent-avatar" src="<?php echo $opp_avatar; ?>?size=64" alt="<?php echo $opp_name; ?>" onerror="this.src='icons/skull.png'">
            <span class="opponent-name"><?php echo $opp_name; ?></span>
        </a>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

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
// Copy profile link to clipboard
function copyProfileLink() {
    const url = window.location.href.split('?')[0] + '?username=<?php echo urlencode($profile_user['username']); ?>';
    navigator.clipboard.writeText(url).then(() => {
        const btn = document.getElementById('share-btn');
        btn.textContent = '✓ Copied!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.innerHTML = '&#128279; Share';
            btn.classList.remove('copied');
        }, 2000);
    }).catch(() => {
        // Fallback for older browsers
        const ta = document.createElement('textarea');
        ta.value = url;
        document.body.appendChild(ta);
        ta.select();
        document.execCommand('copy');
        document.body.removeChild(ta);
    });
}

// Animated count-up on stat numbers
document.addEventListener('DOMContentLoaded', () => {
    const stats = document.querySelectorAll('.stat-number[data-count]');
    const observer = new IntersectionObserver((entries) => {
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
                const value    = Math.round(ease * target);
                el.textContent = value.toLocaleString();
                if (progress < 1) requestAnimationFrame(tick);
            }
            requestAnimationFrame(tick);
            observer.unobserve(el);
        });
    }, { threshold: 0.3 });
    stats.forEach(el => observer.observe(el));

    // Set footer year
    const yr = document.getElementById('year');
    if (yr) yr.textContent = new Date().getFullYear();
});
</script>

<?php $conn->close(); ?>
</html>
