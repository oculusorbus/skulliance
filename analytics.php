<?php
include_once 'db.php';

// Set up session variables for header.php without forcing a login redirect
$name = "";
$avatar_url = "";
if (isset($_SESSION['userData'])) {
    extract($_SESSION['userData']);
    $avatar_url = "https://cdn.discordapp.com/avatars/$discord_id/$avatar.jpg";
}

// Loader CSS injected into <head> so it's available before body renders
$extra_head = '<style>
#loader {
    position: fixed; inset: 0;
    background: #07111d;
    z-index: 100;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center; gap: 20px;
    transition: opacity 0.6s ease;
}
#loader.fade-out { opacity: 0; pointer-events: none; }
.loader-skull { font-size: 3rem; animation: lp 1.2s ease-in-out infinite; }
@keyframes lp { 0%,100%{opacity:.3;transform:scale(.92)} 50%{opacity:1;transform:scale(1)} }
.loader-bar-wrap { width:200px;height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden; }
.loader-bar { height:100%;background:#00c8a0;width:0%;animation:lb 6s ease-out forwards; }
@keyframes lb { to { width:90%; } }
.loader-text { font-size:.78rem;color:rgba(255,255,255,.35);letter-spacing:.1em;text-transform:uppercase; }
</style>';

include 'header.php';
?>

<div id="loader">
    <div class="loader-skull">💀</div>
    <div class="loader-bar-wrap"><div class="loader-bar"></div></div>
    <div class="loader-text">Loading Analytics</div>
</div>

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

/* ── Hero stat ───────────────────────────────────────────────── */
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

/* ── Economy ─────────────────────────────────────────────────── */
.ana-econ-strip {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
}
.ana-econ-item {
    background: rgba(0,200,160,0.05);
    border: 1px solid rgba(0,200,160,0.08);
    border-radius: 6px;
    padding: 11px 10px 9px;
}
.ana-econ-value { font-size: 1.5rem; font-weight: 700; color: #fff; margin-bottom: 3px; line-height: 1; }
.ana-econ-label { font-size: 0.62rem; text-transform: uppercase; letter-spacing: 0.05em; color: #7a9eb0; margin-bottom: 5px; }
.ana-econ-sub { display: flex; gap: 8px; margin-top: 5px; flex-wrap: wrap; }
.ana-econ-sub-item { font-size: 0.6rem; color: #7a9eb0; white-space: nowrap; }
.ana-econ-sub-item.up { color: #00c8a0; }
.ana-econ-sub-item.down { color: #ff7070; }

/* ── Projects ────────────────────────────────────────────────── */
.ana-proj-cols { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
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

/* ── Trends ──────────────────────────────────────────────────── */
.trend-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 8px;
    margin-bottom: 16px;
}
.trend-select {
    background: rgba(0,200,160,0.07);
    border: 1px solid rgba(0,200,160,0.2);
    border-radius: 6px;
    color: #c8dce8;
    font-size: 0.78rem;
    padding: 6px 10px;
    cursor: pointer;
    outline: none;
}
.trend-select option { background: #0d2035; }
.trend-range-group { display: flex; gap: 4px; flex-wrap: wrap; }
.trend-range-btn {
    background: rgba(255,255,255,0.05);
    border: 1px solid rgba(255,255,255,0.1);
    border-radius: 5px;
    color: #7a9eb0;
    font-size: 0.72rem;
    padding: 5px 10px;
    cursor: pointer;
    transition: background 0.15s, color 0.15s, border-color 0.15s;
}
.trend-range-btn:hover { background: rgba(0,200,160,0.1); color: #c8dce8; }
.trend-range-btn.active {
    background: rgba(0,200,160,0.15);
    border-color: rgba(0,200,160,0.4);
    color: #00c8a0;
}
.trend-custom { display: none; align-items: center; gap: 6px; flex-wrap: wrap; }
.trend-custom-label { font-size: 0.7rem; color: #7a9eb0; }
.trend-date-input {
    background: rgba(0,200,160,0.07);
    border: 1px solid rgba(0,200,160,0.2);
    border-radius: 5px;
    color: #c8dce8;
    font-size: 0.72rem;
    padding: 5px 8px;
    cursor: pointer;
    outline: none;
    color-scheme: dark;
}
.trend-custom-go {
    background: rgba(0,200,160,0.15);
    border: 1px solid rgba(0,200,160,0.4);
    border-radius: 5px;
    color: #00c8a0;
    font-size: 0.72rem;
    padding: 5px 10px;
    cursor: pointer;
}
.trend-chart-wrap { position: relative; height: 280px; }
.trend-loading {
    position: absolute;
    inset: 0;
    display: none;
    align-items: center;
    justify-content: center;
    font-size: 0.75rem;
    color: #7a9eb0;
    background: rgba(13,32,53,0.7);
    border-radius: 6px;
}

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width: 1100px) {
    .ana-row-5, .ana-row-4 { grid-template-columns: repeat(3, 1fr); }
    .ana-econ-strip { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 700px) {
    .ana-row-5, .ana-row-4, .ana-row-3 { grid-template-columns: 1fr 1fr; }
    .ana-dual-grid, .ana-game-grid { grid-template-columns: 1fr 1fr; }
    .ana-econ-strip { grid-template-columns: 1fr 1fr; }
    .ana-proj-cols { grid-template-columns: 1fr 1fr; }
    .ana-hero { flex-direction: column; align-items: flex-start; gap: 6px; }
}
@media (max-width: 480px) {
    .ana-row-5, .ana-row-4, .ana-row-3 { grid-template-columns: 1fr; }
    .ana-proj-cols { grid-template-columns: 1fr; }
}
</style>

<div class="ana-page">

    <div id="ana-content"></div>

    <!-- ── Trends ── -->
    <div class="ana-section-label">Trends</div>
    <div class="ana-card" id="trends-card">

        <div class="trend-controls">
            <select id="trend-metric" class="trend-select" onchange="fetchTrend()">
                <option value="transactions">Transactions</option>
                <option value="stakers">Stakers Joined</option>
                <option value="nfts">NFTs Added</option>
                <option value="wallets">Wallets Connected</option>
                <option value="realms">Realms Created</option>
                <option value="rewards">Daily Rewards</option>
                <option value="missions">Missions</option>
                <option value="raids">Raids</option>
                <option value="skullswap">Skull Swap</option>
                <option value="monstrocity">Monstrocity</option>
                <option value="bossbattles">Boss Battles</option>
                <option value="upgrades">Location Upgrades</option>
                <option value="crafting">Crafting</option>
                <option value="store">Store Claims</option>
            </select>

            <div class="trend-range-group">
                <button class="trend-range-btn" data-range="week"   onclick="setTrendRange(this)">Week</button>
                <button class="trend-range-btn" data-range="month"  onclick="setTrendRange(this)">Month</button>
                <button class="trend-range-btn" data-range="year"   onclick="setTrendRange(this)">Year</button>
                <button class="trend-range-btn" data-range="all"    onclick="setTrendRange(this)">All Time</button>
                <button class="trend-range-btn" data-range="custom" onclick="setTrendRange(this)">Custom</button>
            </div>

            <div class="trend-custom" id="trend-custom">
                <span class="trend-custom-label">From</span>
                <input type="date" id="trend-start" class="trend-date-input">
                <span class="trend-custom-label">to</span>
                <input type="date" id="trend-end" class="trend-date-input">
                <button class="trend-custom-go" onclick="fetchTrend()">Go</button>
            </div>
        </div>

        <div class="trend-chart-wrap">
            <canvas id="trend-canvas"></canvas>
            <div class="trend-loading" id="trend-loading">Loading&hellip;</div>
        </div>

    </div>

</div>

<script>
// ── Dismiss loader after stats content is injected ────────────────
function dismissLoader() {
    var l = document.getElementById('loader');
    l.classList.add('fade-out');
    setTimeout(function() { l.style.display = 'none'; }, 650);
}

// ── Fetch stat sections via AJAX ──────────────────────────────────
fetch('ajax/analytics-content.php')
    .then(function(r) { return r.text(); })
    .then(function(html) {
        document.getElementById('ana-content').innerHTML = html;
        dismissLoader();
    })
    .catch(function() { dismissLoader(); });

// ── Trends chart ──────────────────────────────────────────────────
(function() {
    const metricLabels = {
        stakers:      'Stakers Joined',
        nfts:         'NFTs Added',
        wallets:      'Wallets Connected',
        realms:       'Realms Created',
        rewards:      'Daily Rewards',
        missions:     'Missions',
        raids:        'Raids',
        skullswap:    'Skull Swap',
        monstrocity:  'Monstrocity',
        bossbattles:  'Boss Battles',
        upgrades:     'Location Upgrades',
        crafting:     'Crafting',
        store:        'Store Claims',
        transactions: 'Transactions',
    };

    let trendChart = null;
    let activeRange = 'all';
    let chartJsLoaded = false;

    function dateStr(d) { return d.toISOString().slice(0, 10); }

    function getDateRange() {
        const today = new Date();
        if (activeRange === 'week')   return { start: dateStr(new Date(today - 7   * 86400000)), end: dateStr(today) };
        if (activeRange === 'month')  return { start: dateStr(new Date(today - 30  * 86400000)), end: dateStr(today) };
        if (activeRange === 'year')   return { start: dateStr(new Date(today - 365 * 86400000)), end: dateStr(today) };
        if (activeRange === 'custom') return { start: document.getElementById('trend-start').value, end: document.getElementById('trend-end').value };
        return { start: '', end: '' };
    }

    function loadChartJs(callback) {
        if (chartJsLoaded) { callback(); return; }
        const s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        s.onload = function() { chartJsLoaded = true; callback(); };
        document.head.appendChild(s);
    }

    window.fetchTrend = function() {
        if (!chartJsLoaded) { loadChartJs(fetchTrend); return; }
        const metric = document.getElementById('trend-metric').value;
        const { start, end } = getDateRange();
        const params = new URLSearchParams({ metric });
        if (start) params.append('start', start);
        if (end)   params.append('end',   end);
        document.getElementById('trend-loading').style.display = 'flex';
        fetch('ajax/analytics-trends.php?' + params)
            .then(r => r.json())
            .then(d => renderTrendChart(d, metricLabels[metric] || metric))
            .catch(() => document.getElementById('trend-loading').style.display = 'none');
    };

    window.setTrendRange = function(btn) {
        document.querySelectorAll('.trend-range-btn').forEach(b => b.classList.remove('active'));
        btn.classList.add('active');
        activeRange = btn.dataset.range;
        document.getElementById('trend-custom').style.display = activeRange === 'custom' ? 'flex' : 'none';
        if (activeRange !== 'custom') fetchTrend();
    };

    function renderTrendChart(data, label) {
        document.getElementById('trend-loading').style.display = 'none';
        const ctx = document.getElementById('trend-canvas').getContext('2d');
        if (trendChart) trendChart.destroy();
        const pointRadius = data.labels.length > 60 ? 0 : (data.labels.length > 20 ? 2 : 3);
        trendChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: data.labels,
                datasets: [{
                    label: label,
                    data: data.data,
                    borderColor: '#00c8a0',
                    backgroundColor: 'rgba(0,200,160,0.07)',
                    borderWidth: 2,
                    pointRadius: pointRadius,
                    pointHoverRadius: 5,
                    pointBackgroundColor: '#00c8a0',
                    fill: true,
                    tension: 0.35,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                animation: { duration: 300 },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: '#071524',
                        borderColor: 'rgba(0,200,160,0.25)',
                        borderWidth: 1,
                        titleColor: '#00c8a0',
                        bodyColor: '#c8dce8',
                        padding: 10,
                        callbacks: {
                            title: items => items[0].label,
                            label: item => ' ' + label + ': ' + item.raw.toLocaleString(),
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { color: 'rgba(255,255,255,0.04)' },
                        ticks: { color: '#7a9eb0', maxTicksLimit: 14, maxRotation: 0 }
                    },
                    y: {
                        grid: { color: 'rgba(255,255,255,0.04)' },
                        ticks: { color: '#7a9eb0', callback: v => v >= 1000 ? Math.round(v/1000)+'K' : v },
                        beginAtZero: true,
                    }
                }
            }
        });
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.trend-range-btn[data-range="all"]').classList.add('active');
        const today = dateStr(new Date());
        document.getElementById('trend-end').value = today;
        document.getElementById('trend-start').value = dateStr(new Date(Date.now() - 30 * 86400000));

        const observer = new IntersectionObserver(function(entries) {
            if (entries[0].isIntersecting) {
                observer.disconnect();
                loadChartJs(fetchTrend);
            }
        }, { rootMargin: '200px' });
        observer.observe(document.getElementById('trends-card'));
    });
})();
</script>

<div class="footer">
    <p>Skulliance<br>Copyright &copy; <span id="year"></span></p>
</div>
<script>document.getElementById("year").innerHTML = new Date().getFullYear();</script>
</body>
</html>
