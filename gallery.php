<?php
include_once 'db.php';
session_start();

$my_user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;

// ── NFT select columns (reused for both queries) ───────────────────────────
$nft_cols = "nfts.id, asset_id, asset_name, nfts.name AS nft_display_name, ipfs,
               users.id AS user_id, users.username, users.discord_id, users.avatar,
               projects.id AS project_id, projects.name AS project_name, projects.currency,
               collections.id AS collection_id, collections.name AS collection_name,
               collections.policy AS collection_policy,
               collections.rate AS rate";
$nft_joins = "FROM nfts
        INNER JOIN users       ON users.id = nfts.user_id
        INNER JOIN collections ON nfts.collection_id = collections.id
        INNER JOIN projects    ON projects.id = collections.project_id";
$nft_where = "WHERE nfts.user_id != 0 AND nfts.ipfs IS NOT NULL AND nfts.ipfs != ''";

// ── Main NFT query: random 400 from visible users ─────────────────────────
$vis_clause = "users.visibility = 2" . ($my_user_id ? " AND nfts.user_id != $my_user_id" : "");
$sql = "SELECT $nft_cols $nft_joins $nft_where AND $vis_clause ORDER BY RAND() LIMIT 400";

// ── Logged-in user: always fetch ALL their own NFTs ────────────────────────
$sql_mine = $my_user_id
    ? "SELECT $nft_cols $nft_joins $nft_where AND nfts.user_id = $my_user_id"
    : null;

// ── Flat lookup: active missions (one query, build map) ────────────────────
$missions_map = [];
$mr = $conn->query("SELECT mn.nft_id, q.title
                    FROM missions_nfts mn
                    INNER JOIN missions m ON m.id = mn.mission_id AND m.status = '0'
                    INNER JOIN quests   q ON q.id = m.quest_id");
if($mr) while($row = $mr->fetch_assoc()) $missions_map[$row['nft_id']] = $row['title'];

// ── Flat lookup: diamond skull delegations ─────────────────────────────────
$skull_map = [];
$sr = $conn->query("SELECT ds.nft_id, n.id AS skull_id, n.name
                    FROM diamond_skulls ds
                    INNER JOIN nfts n ON n.id = ds.diamond_skull_id");
if($sr) while($row = $sr->fetch_assoc()) $skull_map[$row['nft_id']] = ['name' => $row['name'], 'skull_id' => (int)$row['skull_id']];

// ── Build NFT array ────────────────────────────────────────────────────────
function build_nft_row($row, $missions_map, $skull_map){
    $id   = $row['id'];
    $name = !empty($row['nft_display_name']) ? $row['nft_display_name'] : $row['asset_name'];
    $jpg_asset_id = $row['collection_policy'] . bin2hex($row['asset_name']);
    return [
        'asset_id'        => $row['asset_id'],
        'name'            => htmlspecialchars($name, ENT_QUOTES),
        'image'           => getIPFS($row['ipfs'], $row['collection_id']),
        'user_id'         => (int)$row['user_id'],
        'username'        => htmlspecialchars($row['username'] ?? 'Unknown', ENT_QUOTES),
        'discord_id'      => $row['discord_id'],
        'avatar'          => $row['avatar'],
        'project_id'      => (int)$row['project_id'],
        'project_name'    => htmlspecialchars($row['project_name'], ENT_QUOTES),
        'currency'        => strtolower(htmlspecialchars($row['currency'], ENT_QUOTES)),
        'collection_id'   => (int)$row['collection_id'],
        'collection_name'   => htmlspecialchars($row['collection_name'], ENT_QUOTES),
        'collection_policy' => $row['collection_policy'],
        'jpg_asset_id'      => $jpg_asset_id,
        'rate'              => (int)$row['rate'],
        'mission'          => isset($missions_map[$id]) ? htmlspecialchars($missions_map[$id],              ENT_QUOTES) : null,
        'diamond_skull'    => isset($skull_map[$id])   ? htmlspecialchars($skull_map[$id]['name'],          ENT_QUOTES) : null,
        'diamond_skull_id' => isset($skull_map[$id])   ? $skull_map[$id]['skull_id']                                    : null,
    ];
}

$nfts_data = [];
$result = $conn->query($sql);
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()) $nfts_data[] = build_nft_row($row, $missions_map, $skull_map);
}

// Append all of the logged-in user's own NFTs (not already in the set)
$mine_data = [];
if($sql_mine){
    $result_mine = $conn->query($sql_mine);
    if($result_mine && $result_mine->num_rows > 0){
        while($row = $result_mine->fetch_assoc()) $mine_data[] = build_nft_row($row, $missions_map, $skull_map);
    }
    // Merge into main array (dedup by asset_id not needed since we excluded them above)
    $nfts_data = array_merge($nfts_data, $mine_data);
}
$conn->close();
$nfts_json    = json_encode($nfts_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
$my_user_json = json_encode($my_user_id);
?>
<!doctype html>
<html lang="en">
<head>
  <title>Skulliance Gallery</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <style>
    *, *::before, *::after { margin:0; padding:0; box-sizing:border-box; }

    body {
      background: #000;
      color: #fff;
      font-family: Arial, sans-serif;
      overflow: hidden;
      transition: background-color 1.8s ease;
      width: 100vw;
      height: 100vh;
      user-select: none;
    }

    /* ── Ambient blurred background ── */
    #bg-a, #bg-b {
      position: absolute;
      inset: -40px;
      background-size: cover;
      background-position: center;
      filter: blur(35px) brightness(0.35) saturate(1.4);
      transform: scale(1.12);
      z-index: 0;
      transition: opacity 1.2s ease;
    }
    #bg-b { opacity: 0; }

    /* Radial vignette */
    #vignette {
      position: absolute;
      inset: 0;
      background: radial-gradient(ellipse at center, transparent 25%, rgba(0,0,0,0.72) 100%);
      z-index: 1;
      pointer-events: none;
    }

    /* ── Stage ── */
    #stage {
      position: absolute;
      inset: 0;
      display: flex;
      align-items: center;
      justify-content: center;
      z-index: 2;
    }

    /* ── NFT image ── */
    #nft-wrap {
      position: relative;
      display: flex;
      align-items: center;
      justify-content: center;
    }
    #nft-wrap a { display: block; }

    .nft-layer {
      position: absolute;
      top: 0; left: 0;
      max-width: 58vw;
      max-height: 68vh;
      object-fit: contain;
      border-radius: 10px;
      pointer-events: none;
    }
    #nft-main {
      position: relative;
      max-width: 58vw;
      max-height: 68vh;
      object-fit: contain;
      border-radius: 10px;
      display: block;
      box-shadow: 0 0 80px rgba(0,200,160,0.25), 0 0 200px rgba(0,200,160,0.08);
      opacity: 0;
      transition: opacity 0.7s ease;
    }
    #nft-main.visible { opacity: 1; }

    /* Pause overlay */
    #pause-overlay {
      position: absolute; inset: 0;
      display: flex; align-items: center; justify-content: center;
      font-size: 2.5rem; color: rgba(255,255,255,0.85);
      text-shadow: 0 0 24px rgba(0,0,0,0.9);
      opacity: 0; pointer-events: none;
      transition: opacity 0.25s;
      border-radius: 10px;
    }
    #nft-wrap.hover-paused #pause-overlay { opacity: 1; }
    #nft-wrap.hover-paused {
      cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32'%3E%3Crect x='8' y='7' width='5' height='18' rx='2.5' fill='white'/%3E%3Crect x='19' y='7' width='5' height='18' rx='2.5' fill='white'/%3E%3C/svg%3E") 16 16, default;
    }

    /* Ken Burns */
    @keyframes kb {
      from { transform: scale(1.00) translate( 0%,  0%); }
      to   { transform: scale(1.10) translate(-1.5%, 1%); }
    }
    #nft-main.kenburns { animation: kb var(--kb-dur, 10s) ease-out forwards; }

    /* Glitch layers */
    #glitch-r, #glitch-b { opacity: 0; border-radius: 10px; }

    @keyframes gc {
      0%   { clip-path: inset(35% 0 52% 0); transform: translate(-5px, 0); }
      15%  { clip-path: inset(65% 0 12% 0); transform: translate( 5px, 0); }
      30%  { clip-path: inset(15% 0 68% 0); transform: translate(-3px, 0); }
      50%  { clip-path: inset(78% 0  8% 0); transform: translate( 3px, 0); }
      70%  { clip-path: inset(42% 0 40% 0); transform: translate(-5px, 0); }
      100% { clip-path: inset(42% 0 40% 0); transform: translate( 0px, 0); }
    }
    #glitch-r.active {
      opacity: 0.75;
      mix-blend-mode: screen;
      filter: hue-rotate(0deg) saturate(4) brightness(1.5);
      animation: gc 0.35s steps(1) forwards;
    }
    #glitch-b.active {
      opacity: 0.55;
      mix-blend-mode: screen;
      filter: hue-rotate(200deg) saturate(4) brightness(1.5);
      animation: gc 0.35s steps(1) 0.06s forwards;
    }

    /* ── Placard ── */
    #placard {
      position: absolute;
      bottom: 56px;
      left: 50%;
      transform: translateX(-50%);
      background: rgba(0,0,0,0.52);
      backdrop-filter: blur(14px);
      -webkit-backdrop-filter: blur(14px);
      border: 1px solid rgba(0,200,160,0.22);
      border-radius: 14px;
      padding: 13px 22px 11px;
      min-width: 300px;
      max-width: 88vw;
      text-align: center;
      z-index: 4;
      opacity: 0;
      transition: opacity 0.7s ease;
    }
    #placard.visible { opacity: 1; }

    #p-title {
      font-size: 1.05rem;
      font-weight: bold;
      color: #fff;
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
      margin-bottom: 8px;
      text-decoration: none;
      display: block;
      transition: color 0.2s;
    }
    #p-title:hover { color: #00c8a0; }

    #p-meta {
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 14px;
      font-size: 0.8rem;
      color: #bbb;
      flex-wrap: wrap;
    }
    .p-owner { display: flex; align-items: center; gap: 6px; }
    .p-owner img {
      width: 22px; height: 22px;
      border-radius: 50%;
      border: 1px solid rgba(0,200,160,0.5);
      object-fit: cover;
    }
    .p-owner span { color: #00c8a0; font-weight: bold; }
    .p-project { display: flex; align-items: center; gap: 5px; }
    .p-project img { width: 15px; height: 15px; object-fit: contain; }
    .p-sep { color: rgba(255,255,255,0.18); }

    .p-rate { display: flex; align-items: center; gap: 5px; }
    .p-rate img { width: 15px; height: 15px; object-fit: contain; }
    .p-rate span { color: #00c8a0; font-weight: bold; }

    #p-collection {
      margin-top: 5px;
      font-size: 0.7rem;
      color: rgba(255,255,255,0.35);
      letter-spacing: 0.07em;
      text-transform: uppercase;
    }

    #p-badges { margin-top: 7px; display: flex; gap: 6px; justify-content: center; flex-wrap: wrap; }
    #p-actions { margin-top: 8px; display: flex; justify-content: center; }
    .p-action-btn {
      display: inline-flex; align-items: center; gap: 6px;
      padding: 5px 14px; border-radius: 20px;
      background: rgba(255,255,255,0.10); border: 1px solid rgba(255,255,255,0.18);
      color: #e8eaed; text-decoration: none; font-size: 0.75rem;
      transition: background 0.2s;
    }
    .p-action-btn:hover { background: rgba(255,255,255,0.20); }
    .p-action-btn img { height: 14px; width: auto; filter: brightness(0) invert(1); }
    .badge {
      font-size: 0.68rem;
      padding: 3px 9px;
      border-radius: 10px;
      border: 1px solid;
      letter-spacing: 0.04em;
    }
    .badge-mission { border-color: rgba(255,180,0,0.5); color: rgba(255,200,60,0.9); background: rgba(255,180,0,0.08); }
    .badge-skull   { border-color: rgba(0,200,160,0.4); color: rgba(0,200,160,0.9);  background: rgba(0,200,160,0.08); }

    /* ── Progress bar ── */
    #progress {
      position: absolute;
      bottom: 0; left: 0;
      height: 3px;
      width: 0%;
      background: #00c8a0;
      z-index: 6;
      transition: none;
    }
    @keyframes progress-glow {
      0%, 100% { box-shadow: none; opacity: 1; }
      50%       { box-shadow: 0 0 8px 3px #00c8a0; opacity: 0.5; }
    }
    #progress.waiting { animation: progress-glow 1.2s ease-in-out infinite; }

    /* ── Controls ── */
    #controls {
      position: absolute;
      top: 0; left: 0; right: 0;
      padding: 14px 20px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      z-index: 10;
      background: linear-gradient(to bottom, rgba(0,0,0,0.65), transparent);
      opacity: 0;
      transition: opacity 0.35s ease;
    }
    #controls.show { opacity: 1; }

    .ctrl-logo {
      color: rgba(255,255,255,0.6);
      font-size: 0.8rem;
      letter-spacing: 0.08em;
      text-decoration: none;
    }
    .ctrl-logo:hover { color: #00c8a0; }

    #ctrl-mid { display: flex; gap: 10px; align-items: center; }
    #ctrl-right { display: flex; gap: 10px; align-items: center; }

    .cbtn {
      width: 36px; height: 36px;
      border-radius: 50%;
      border: 1px solid rgba(255,255,255,0.18);
      background: rgba(0,0,0,0.45);
      color: #fff;
      cursor: pointer;
      font-size: 13px;
      display: flex; align-items: center; justify-content: center;
      transition: border-color 0.2s, background 0.2s;
      flex-shrink: 0;
    }
    .cbtn:hover { border-color: #00c8a0; background: rgba(0,200,160,0.18); }

    #slide-ctr {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.35);
      min-width: 70px;
      text-align: right;
    }

    /* ── Settings panel ── */
    #settings {
      position: absolute;
      top: 0; right: 0;
      width: 250px;
      height: 100vh;
      background: rgba(5,12,20,0.94);
      backdrop-filter: blur(18px);
      -webkit-backdrop-filter: blur(18px);
      border-left: 1px solid rgba(0,200,160,0.14);
      z-index: 20;
      transform: translateX(101%);
      transition: transform 0.3s ease;
      padding: 64px 22px 22px;
      overflow-y: auto;
    }
    #settings.open { transform: translateX(0); }

    .s-head {
      color: #00c8a0;
      font-size: 0.72rem;
      text-transform: uppercase;
      letter-spacing: 0.12em;
      margin-bottom: 18px;
    }
    .s-group { margin-bottom: 22px; }
    .s-label {
      display: block;
      font-size: 0.7rem;
      color: rgba(255,255,255,0.45);
      text-transform: uppercase;
      letter-spacing: 0.09em;
      margin-bottom: 9px;
    }

    .pills { display: flex; flex-wrap: wrap; gap: 6px; }
    .pill {
      padding: 5px 11px;
      border-radius: 20px;
      border: 1px solid rgba(255,255,255,0.15);
      font-size: 0.72rem;
      cursor: pointer;
      color: rgba(255,255,255,0.55);
      background: transparent;
      transition: all 0.2s;
    }
    .pill:hover { border-color: #00c8a0; color: #00c8a0; }
    .pill.on { border-color: #00c8a0; background: rgba(0,200,160,0.14); color: #00c8a0; }

    #iv-val {
      text-align: center;
      font-size: 1.5rem;
      font-weight: bold;
      color: #00c8a0;
      margin-bottom: 6px;
    }
    input[type=range] { width: 100%; accent-color: #00c8a0; cursor: pointer; }

    .s-stat {
      font-size: 0.72rem;
      color: rgba(255,255,255,0.35);
      margin-top: 6px;
      line-height: 1.7;
    }
    .s-stat strong { color: rgba(255,255,255,0.6); }

    /* ── Loading overlay ── */
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
    .loader-bar { height:100%;background:#00c8a0;width:0%;animation:lb 9s ease-out forwards; }
    @keyframes lb { to { width:90%; } }
    .loader-text { font-size:.78rem;color:rgba(255,255,255,.35);letter-spacing:.1em;text-transform:uppercase; }

    /* ── Mobile ── */
    @media (max-width: 600px) {
      #nft-main, .nft-layer { max-width: 88vw; max-height: 48vh; }
      #placard { min-width: 92vw; padding: 10px 14px; }
      #p-meta { gap: 8px; }
      #stage { align-items: flex-start; padding-top: 8vh; }

      /* Settings: full-width overlay with backdrop on mobile */
      #settings {
        width: 100vw;
        border-left: none;
        border-bottom: 1px solid rgba(0,200,160,0.14);
        padding: 52px 20px 28px;
      }
      /* Settings backdrop — tapping outside closes the panel */
      #settings-backdrop {
        display: none;
        position: fixed; inset: 0;
        z-index: 19;
        background: rgba(0,0,0,0.5);
      }
      #settings-backdrop.open { display: block; }

      /* Hide Spotify on mobile — too fiddly */
      .s-group-spotify { display: none !important; }

      /* Prevent iOS from zooming in on input focus */
      input, select, textarea { font-size: 16px !important; }
    }
  </style>
</head>
<body>

<div id="loader">
  <div class="loader-skull">💀</div>
  <div class="loader-bar-wrap"><div class="loader-bar"></div></div>
  <div class="loader-text">Loading Gallery</div>
</div>

<div id="bg-a"></div>
<div id="bg-b"></div>
<div id="vignette"></div>

<div id="stage">
  <div id="nft-wrap">
    <a id="nft-link" href="#" target="_blank" rel="noopener">
      <img id="nft-main" src="" alt="" />
      <img id="glitch-r" class="nft-layer" src="" alt="" />
      <img id="glitch-b" class="nft-layer" src="" alt="" />
    </a>
    <div id="pause-overlay">&#9646;&#9646;</div>
  </div>
</div>

<div id="placard">
  <a id="p-title" href="#" target="_blank" rel="noopener"></a>
  <div id="p-meta">
    <div class="p-owner">
      <img id="p-avatar" src="" onerror="this.src='icons/skull.png'" alt="" />
      <span id="p-username"></span>
    </div>
    <span class="p-sep">|</span>
    <div class="p-rate">
      <img id="p-icon" alt="" style="display:none" />
      <span id="p-rate-val"></span>
    </div>
    <span class="p-sep">|</span>
    <a id="p-project" href="#" style="color:inherit;text-decoration:underline dotted;cursor:pointer;"></a>
  </div>
  <div id="p-collection"><a id="p-coll-link" href="#" target="_blank" rel="noopener" style="color:inherit;text-decoration:underline dotted;cursor:pointer;"></a></div>
  <div id="p-badges"></div>
  <form id="nav-mission-form" action="missions.php#missions" method="post" target="_blank" style="display:none">
    <input type="hidden" name="filterby" value="core">
    <input type="hidden" name="reset_mission" value="1">
    <input type="hidden" id="nav-mission-project-id" name="nft_project_id" value="">
  </form>
  <form id="nav-skull-form" action="diamond-skulls.php#diamond-skull" method="post" target="_blank" style="display:none">
    <input type="hidden" id="nav-skull-id" name="diamond_skull_id" value="">
  </form>
  <form id="nav-collections-form" action="collections.php" method="post" target="_blank" style="display:none">
    <input type="hidden" id="nav-collections-filterby" name="filterby" value="">
  </form>
  <div id="p-actions">
    <a id="p-pool-btn" href="#" target="_blank" rel="noopener" class="p-action-btn"><img src="https://pool.pm/pool.pm.svg" alt="pool.pm" />View on pool.pm</a>
    <a id="p-offer-btn" href="#" target="_blank" rel="noopener" class="p-action-btn"><img src="https://static.jpgstoreapis.com/icons/jpg-nav-logo-dark.svg" alt="jpg.store" />Make Offer</a>
  </div>
</div>

<div id="progress"></div>

<div id="controls">
  <a class="ctrl-logo" href="profile.php">← Skulliance</a>
  <div id="ctrl-mid">
    <button class="cbtn" id="btn-prev" title="Previous (←)">&#9664;</button>
    <button class="cbtn" id="btn-pp"   title="Pause/Play (P)">&#9646;&#9646;</button>
    <button class="cbtn" id="btn-next" title="Next (→)">&#9654;</button>
  </div>
  <div id="ctrl-right">
    <span id="slide-ctr"></span>
    <button class="cbtn" id="btn-cfg" title="Settings">&#9881;</button>
  </div>
</div>

<div id="settings-backdrop"></div>
<div id="settings">
  <div class="s-head" style="display:flex;align-items:center;justify-content:space-between;">
    <span>Gallery Settings</span>
    <button onclick="closeSettings()" style="background:none;border:none;color:rgba(255,255,255,0.5);font-size:1.3rem;cursor:pointer;padding:0;line-height:1;" title="Close">&times;</button>
  </div>

  <div class="s-group">
    <span class="s-label">Transition</span>
    <div class="pills" id="fx-pills">
      <button class="pill on" data-fx="fade">Fade</button>
      <button class="pill"    data-fx="kenburns">Ken Burns</button>
      <button class="pill"    data-fx="glitch">Glitch</button>
    </div>
  </div>

  <div class="s-group">
    <span class="s-label">Interval</span>
    <div id="iv-val">8s</div>
    <input type="range" id="iv-slider" min="3" max="60" value="8" step="1">
  </div>

  <div class="s-group" id="my-nfts-group" style="display:none">
    <span class="s-label">Filter</span>
    <div class="pills" id="filter-pills">
      <button class="pill on" data-filter="all">All NFTs</button>
      <button class="pill"    data-filter="mine">My NFTs</button>
    </div>
  </div>

  <div class="s-group">
    <span class="s-label">Playlist</span>
    <button class="pill" id="btn-reshuffle">↺ Reshuffle</button>
    <div class="s-stat" id="stats-display"></div>
  </div>

  <div class="s-group s-group-spotify">
    <span class="s-label">Music (Spotify)</span>
    <input type="text" id="spotify-url" placeholder="Paste playlist or album link…" autocomplete="off"
      style="width:100%;box-sizing:border-box;background:rgba(255,255,255,0.07);border:1px solid rgba(255,255,255,0.15);border-radius:6px;color:#e8eaed;padding:6px 8px;font-size:0.75rem;margin-top:6px;">
    <div style="font-size:0.65rem;color:rgba(255,255,255,0.35);margin-top:5px;line-height:1.4;">
      Use a <strong style="color:rgba(255,255,255,0.5);">playlist</strong>, <strong style="color:rgba(255,255,255,0.5);">album</strong>, or <strong style="color:rgba(255,255,255,0.5);">track</strong> link. Artist links require Spotify Premium.
    </div>
    <button class="pill" id="btn-spotify-load" style="margin-top:8px;width:100%;">▶ Load Player</button>
    <button class="pill" id="btn-spotify-hide" style="margin-top:6px;width:100%;display:none;">✕ Hide Player</button>
  </div>
</div>


<!-- Spotify mini player (fixed bottom-left) — iframe injected dynamically to avoid src="" state issues -->
<div id="spotify-player" style="opacity:0;pointer-events:none;position:fixed;bottom:4px;left:0;z-index:9999;width:300px;user-select:auto;transition:opacity 0.2s;"></div>

<script>
const NFTS       = <?php echo $nfts_json; ?>;
const MY_USER_ID = <?php echo $my_user_json; ?>;
let   activeFilter = 'all';
let   sourceNfts   = NFTS;

// ── Fisher-Yates shuffle ───────────────────────────────────────────────────
function shuffle(a){ for(let i=a.length-1;i>0;i--){const j=Math.floor(Math.random()*(i+1));[a[i],a[j]]=[a[j],a[i]];}return a; }

// ── Build interleaved playlist: round-robin by collection ─────────────────
// Groups each collection, shuffles within, then interleaves so every project
// gets a turn before any repeats — full cycle before reshuffle.
function buildPlaylist(){
  const groups = {};
  sourceNfts.forEach(n => {
    if(!groups[n.collection_id]) groups[n.collection_id] = [];
    groups[n.collection_id].push(n);
  });
  const keys = shuffle(Object.keys(groups));
  keys.forEach(k => shuffle(groups[k]));
  const out = [];
  const max = Math.max(...keys.map(k => groups[k].length));
  for(let i=0; i<max; i++) keys.forEach(k => { if(groups[k][i]) out.push(groups[k][i]); });
  return out;
}

// ── State ──────────────────────────────────────────────────────────────────
let playlist  = buildPlaylist();
let idx       = 0;
let playing   = true;
let intervalMs = 8000;
let fxSet     = new Set(['fade']); // active effects — multiple can be on at once
let hideTimer;
let bgActive  = 'a'; // which bg layer is on top

// ── DOM ────────────────────────────────────────────────────────────────────
const bgA       = document.getElementById('bg-a');
const bgB       = document.getElementById('bg-b');
const nftMain   = document.getElementById('nft-main');
const glitchR   = document.getElementById('glitch-r');
const glitchB   = document.getElementById('glitch-b');
const nftLink   = document.getElementById('nft-link');
const placard   = document.getElementById('placard');
const pTitle    = document.getElementById('p-title');
const pAvatar   = document.getElementById('p-avatar');
const pUsername = document.getElementById('p-username');
const pIcon     = document.getElementById('p-icon');
const pRateVal  = document.getElementById('p-rate-val');
const pProject  = document.getElementById('p-project');
const pColl     = document.getElementById('p-collection');
const pBadges   = document.getElementById('p-badges');
const pPoolBtn  = document.getElementById('p-pool-btn');
const pOfferBtn = document.getElementById('p-offer-btn');
const pCollLink = document.getElementById('p-coll-link');
const progress  = document.getElementById('progress');
const controls  = document.getElementById('controls');
const slideCtrl = document.getElementById('slide-ctr');
const ivVal     = document.getElementById('iv-val');
const ivSlider  = document.getElementById('iv-slider');
const settingsEl= document.getElementById('settings');
const statsEl   = document.getElementById('stats-display');

// ── Preloader: load image in background, callback on success or skip ───────
const preloader = new Image();
let   preloaded = {};   // cache: url -> true/false

function preloadImage(url, cb){
  if(preloaded[url] === true)  { cb(true);  return; }
  if(preloaded[url] === false) { cb(false); return; }
  const img = new Image();
  img.onload  = function(){ preloaded[url] = true;  cb(true);  };
  img.onerror = function(){ preloaded[url] = false; cb(false); };
  img.src = url;
}

// ── Cross-fade background ──────────────────────────────────────────────────
function setBg(url){
  if(bgActive === 'a'){
    bgB.style.backgroundImage = "url('"+url+"')";
    bgB.style.opacity = '1'; bgA.style.opacity = '0'; bgActive = 'b';
  } else {
    bgA.style.backgroundImage = "url('"+url+"')";
    bgA.style.opacity = '1'; bgB.style.opacity = '0'; bgActive = 'a';
  }
}

// ── Show slide — waits for image, skips broken ones ────────────────────────
let pendingSlide = null;

function showSlide(n){
  const nft = playlist[n];
  if(!nft) return;
  pendingSlide = n;

  preloadImage(nft.image, function(ok){
    // If a newer slide was requested while we were loading, discard this one
    if(pendingSlide !== n) return;
    if(!ok){
      // Skip this NFT and move to next
      idx++;
      if(idx >= playlist.length){ playlist = buildPlaylist(); idx = 0; }
      pendingSlide = null;
      showSlide(idx);
      return;
    }
    pendingSlide = null;
    renderSlide(n, nft);
  });
}

function renderSlide(n, nft){
  const poolUrl   = 'https://pool.pm/' + nft.asset_id;
  const avatarUrl = (nft.discord_id && nft.avatar)
    ? 'https://cdn.discordapp.com/avatars/'+nft.discord_id+'/'+nft.avatar+'.png'
    : 'icons/skull.png';
  const iconUrl = 'icons/' + nft.currency + '.png';

  // Fade out current
  nftMain.classList.remove('visible','kenburns');
  glitchR.classList.remove('active');
  glitchB.classList.remove('active');
  placard.classList.remove('visible');

  setTimeout(function(){
    // Background
    setBg(nft.image);

    // Links + text
    nftLink.href          = poolUrl;
    pTitle.href           = poolUrl;
    pTitle.textContent    = nft.name;
    pAvatar.src           = avatarUrl;
    pUsername.innerHTML   = '<a href="profile.php?username='+encodeURIComponent(nft.username)+'" target="_blank" style="color:#00c8a0;text-decoration:none;">'+nft.username+'</a>';
    pIcon.style.display = '';
    pIcon.onerror = function(){ this.style.display = 'none'; };
    pIcon.src             = iconUrl;
    pRateVal.textContent  = nft.rate + ' ' + nft.currency.toUpperCase();
    pProject.textContent  = nft.project_name;
    pProject.style.cursor = 'pointer';
    pProject.style.textDecoration = 'underline dotted';
    pProject.onclick = (function(pid){ return function(e){ e.preventDefault(); document.getElementById('nav-collections-filterby').value = pid; document.getElementById('nav-collections-form').submit(); }; })(nft.project_id);
    pCollLink.textContent = nft.collection_name;
    pCollLink.href        = 'https://www.jpg.store/collection/' + (nft.collection_policy || '');
    pPoolBtn.href         = poolUrl;
    pOfferBtn.href        = 'https://www.jpg.store/asset/' + nft.jpg_asset_id;
    slideCtrl.textContent = (n+1) + ' / ' + playlist.length;

    // Badges
    pBadges.innerHTML = '';
    if(nft.mission){
      const b = document.createElement('span');
      b.className = 'badge badge-mission';
      b.style.cursor = 'pointer';
      b.title = 'Go to Missions';
      b.textContent = '⚔ ' + nft.mission;
      b.onclick = (function(pid){ return function(){ document.getElementById('nav-mission-project-id').value = pid; document.getElementById('nav-mission-form').submit(); }; })(nft.project_id);
      pBadges.appendChild(b);
    }
    if(nft.diamond_skull){
      const b = document.createElement('span');
      b.className = 'badge badge-skull';
      b.style.cursor = 'pointer';
      b.title = 'Go to Diamond Skulls';
      b.textContent = '💎 ' + nft.diamond_skull;
      b.onclick = function(){
        document.getElementById('nav-skull-id').value = nft.diamond_skull_id;
        document.getElementById('nav-skull-form').submit();
      };
      pBadges.appendChild(b);
    }

    // Apply effect + show (image already preloaded so it's instant)
    nftMain.style.removeProperty('animation');
    void nftMain.offsetWidth;
    if(fxSet.has('kenburns')) nftMain.style.setProperty('--kb-dur', (intervalMs/1000)+'s');
    nftMain.src = nft.image;
    nftMain.classList.add('visible');
    if(fxSet.has('kenburns')) nftMain.classList.add('kenburns');
    placard.classList.add('visible');

    if(fxSet.has('glitch')){
      if(glitchR) { glitchR.src = nft.image; glitchB.src = nft.image; }
      setTimeout(function(){
        glitchR.classList.add('active'); glitchB.classList.add('active');
        setTimeout(function(){ glitchR.classList.remove('active'); glitchB.classList.remove('active'); }, 450);
      }, 120);
    }

    startProgress();
    sampleAmbient(nft.image);

    // Preload next 2 images in background
    [1, 2].forEach(function(offset){
      const ahead = playlist[(n + offset) % playlist.length];
      if(ahead) preloadImage(ahead.image, function(){});
    });

    // Dismiss loader on first successful slide
    const ldr = document.getElementById('loader');
    if(ldr && !ldr.classList.contains('fade-out')){
      ldr.classList.add('fade-out');
      setTimeout(function(){ ldr.style.display='none'; }, 700);
    }
  }, fxSet.has('fade') ? 400 : 0);
}

// ── Progress bar ───────────────────────────────────────────────────────────
progress.addEventListener('transitionend', function(e){
  if(e.propertyName === 'width' && playing) progress.classList.add('waiting');
});

function startProgress(){
  progress.classList.remove('waiting');
  progress.style.transition = 'none';
  progress.style.width = '0%';
  if(!playing) return;
  requestAnimationFrame(function(){
    requestAnimationFrame(function(){
      progress.style.transition = 'width '+intervalMs+'ms linear';
      progress.style.width = '100%';
    });
  });
}

// ── Heartbeat timer (setInterval — reliable, independent of image loading) ─
let heartbeat = null;

function startHeartbeat(){
  clearInterval(heartbeat);
  if(!playing) return;
  heartbeat = setInterval(function(){
    idx = (idx + 1) % playlist.length;
    if(idx === 0) playlist = buildPlaylist();
    showSlide(idx);
  }, intervalMs);
}

function stopHeartbeat(){
  clearInterval(heartbeat);
  heartbeat = null;
}

// ── Controls ───────────────────────────────────────────────────────────────
document.getElementById('btn-next').onclick = function(){
  idx = (idx + 1) % playlist.length;
  if(idx === 0) playlist = buildPlaylist();
  showSlide(idx);
  if(playing){ startHeartbeat(); startProgress(); }
};

document.getElementById('btn-prev').onclick = function(){
  idx = (idx - 1 + playlist.length) % playlist.length;
  showSlide(idx);
  if(playing){ startHeartbeat(); startProgress(); }
};

const btnPP = document.getElementById('btn-pp');
btnPP.onclick = function(){
  playing = !playing;
  btnPP.innerHTML = playing ? '&#9646;&#9646;' : '&#9654;';
  if(playing){ startHeartbeat(); startProgress(); }
  else { stopHeartbeat(); progress.style.transition='none'; }
};

// ── Settings toggle — gear icon becomes × when panel is open ──────────────
const btnCfg   = document.getElementById('btn-cfg');
const backdrop = document.getElementById('settings-backdrop');

function openSettings(){
  settingsEl.classList.add('open');
  backdrop.classList.add('open');
  btnCfg.innerHTML = '&times;';
}
function closeSettings(){
  settingsEl.classList.remove('open');
  backdrop.classList.remove('open');
  btnCfg.innerHTML = '&#9881;';
}

btnCfg.addEventListener('click', function(e){
  e.stopPropagation();
  settingsEl.classList.contains('open') ? closeSettings() : openSettings();
});
backdrop.addEventListener('click', closeSettings);
backdrop.addEventListener('touchstart', closeSettings);
document.addEventListener('click', function(e){
  if(!settingsEl.contains(e.target) && e.target !== btnCfg) closeSettings();
});

// ── Effect pills (toggle independently — effects stack) ────────────────────
document.querySelectorAll('#fx-pills .pill').forEach(function(p){
  p.onclick = function(){
    const name = p.dataset.fx;
    if(fxSet.has(name)){ fxSet.delete(name); p.classList.remove('on'); }
    else               { fxSet.add(name);    p.classList.add('on');    }
  };
});

// ── Interval slider ────────────────────────────────────────────────────────
ivSlider.oninput = function(){
  intervalMs = parseInt(this.value) * 1000;
  ivVal.textContent = this.value + 's';
  if(playing){ startHeartbeat(); startProgress(); }
};

// ── My NFTs filter ─────────────────────────────────────────────────────────
if(MY_USER_ID > 0){
  document.getElementById('my-nfts-group').style.display = '';
  document.querySelectorAll('#filter-pills .pill').forEach(function(p){
    p.onclick = function(){
      document.querySelectorAll('#filter-pills .pill').forEach(function(x){ x.classList.remove('on'); });
      p.classList.add('on');
      activeFilter = p.dataset.filter;
      sourceNfts   = activeFilter === 'mine' ? NFTS.filter(function(n){ return n.user_id === MY_USER_ID; }) : NFTS;
      if(!sourceNfts.length) sourceNfts = NFTS; // fallback if no owned NFTs
      playlist = buildPlaylist();
      idx = 0;
      showSlide(idx);
      if(playing){ startHeartbeat(); startProgress(); }
      updateStats();
    };
  });
}

// ── Reshuffle ──────────────────────────────────────────────────────────────
document.getElementById('btn-reshuffle').onclick = function(){
  playlist = buildPlaylist();
  idx = 0;
  showSlide(idx);
  if(playing){ startHeartbeat(); startProgress(); }
  updateStats();
};

// ── Stats display ──────────────────────────────────────────────────────────
function updateStats(){
  const src         = sourceNfts;
  const collections = new Set(src.map(n => n.collection_id)).size;
  const projects    = new Set(src.map(n => n.project_id)).size;
  statsEl.innerHTML =
    '<strong>'+src.length+'</strong> NFTs across<br>'+
    '<strong>'+collections+'</strong> collections · <strong>'+projects+'</strong> projects';
}
updateStats();

// ── Auto-hide controls ─────────────────────────────────────────────────────
function showControls(){
  controls.classList.add('show');
  clearTimeout(hideTimer);
  hideTimer = setTimeout(function(){
    if(!settingsEl.classList.contains('open')) controls.classList.remove('show');
  }, 3500);
}
document.addEventListener('mousemove', showControls);
document.addEventListener('touchstart', showControls);

// ── Keyboard ───────────────────────────────────────────────────────────────
document.addEventListener('keydown', function(e){
  if(e.key==='ArrowRight'||e.key===' '){ e.preventDefault(); document.getElementById('btn-next').click(); }
  else if(e.key==='ArrowLeft')         { document.getElementById('btn-prev').click(); }
  else if(e.key==='p'||e.key==='P')    { btnPP.click(); }
  else if(e.key==='Escape')            { closeSettings(); }
});

// ── Ambient background color ───────────────────────────────────────────────
(function(){
  const cv  = document.createElement('canvas');
  cv.width = cv.height = 16;
  const ctx = cv.getContext('2d');
  window.sampleAmbient = function(url){
    const img = new Image();
    img.crossOrigin = 'anonymous';
    img.onload = function(){
      try {
        ctx.drawImage(img, 0, 0, 16, 16);
        const d = ctx.getImageData(0, 0, 16, 16).data;
        let r=0, g=0, b=0, n=0;
        for(let i=0; i<d.length; i+=4){
          const lum = (d[i]+d[i+1]+d[i+2]) / 3;
          if(lum > 30 && lum < 230){ r+=d[i]; g+=d[i+1]; b+=d[i+2]; n++; }
        }
        if(!n) return;
        document.body.style.backgroundColor =
          'rgb('+Math.round(r/n*.13)+','+Math.round(g/n*.13)+','+Math.round(b/n*.13)+')';
      } catch(e) { /* CORS blocked — skip */ }
    };
    img.src = url;
  };
})();

// ── Auto-pause on hover ────────────────────────────────────────────────────
let hoverPaused = false;
let _hoverTimer  = null;

const nftWrap = document.getElementById('nft-wrap');

function _hoverPause(){
  clearTimeout(_hoverTimer);
  if(!playing || hoverPaused) return;
  stopHeartbeat();
  progress.classList.remove('waiting');
  const w = getComputedStyle(progress).width;
  progress.style.transition = 'none';
  progress.style.width = w;
  nftMain.style.animationPlayState = 'paused';
  nftWrap.classList.add('hover-paused');
  hoverPaused = true;
}
function _hoverResume(){
  _hoverTimer = setTimeout(function(){
    if(!hoverPaused || !playing) return;
    hoverPaused = false;
    nftMain.style.animationPlayState = 'running';
    nftWrap.classList.remove('hover-paused');
    startHeartbeat();
    startProgress();
  }, 80);
}
[nftWrap, placard].forEach(function(el){
  el.addEventListener('mouseenter', _hoverPause);
  el.addEventListener('mouseleave', _hoverResume);
});

// ── Spotify player ─────────────────────────────────────────────────────────
(function(){
  const spotifyInput  = document.getElementById('spotify-url');
  const spotifyPlayer = document.getElementById('spotify-player');
  const btnLoad = document.getElementById('btn-spotify-load');
  const btnHide = document.getElementById('btn-spotify-hide');

  function parseSpotifyEmbed(raw){
    raw = raw.trim();
    // Handle spotify:type:id URI
    let m = raw.match(/^spotify:(track|album|artist|playlist|episode):([A-Za-z0-9]+)$/);
    if(m) return { url: 'https://open.spotify.com/embed/'+m[1]+'/'+m[2]+'?utm_source=generator&theme=0&autoplay=1', type: m[1] };
    // Handle open.spotify.com URLs (including /embed/ already)
    m = raw.match(/open\.spotify\.com\/(?:embed\/)?(track|album|artist|playlist|episode)\/([A-Za-z0-9]+)/);
    if(m) return { url: 'https://open.spotify.com/embed/'+m[1]+'/'+m[2]+'?utm_source=generator&theme=0&autoplay=1', type: m[1] };
    return null;
  }

  let playerLoaded = false;

  function showPlayer(){ spotifyPlayer.style.opacity='1'; spotifyPlayer.style.pointerEvents='all'; btnHide.textContent='Hide Player'; }
  function hidePlayer(){ spotifyPlayer.style.opacity='0'; spotifyPlayer.style.pointerEvents='none'; btnHide.textContent='Show Player'; }

  function loadIframe(result){
    spotifyPlayer.innerHTML = '';
    const iframe = document.createElement('iframe');
    iframe.src    = result.url;
    iframe.width  = '300';
    iframe.height = (result.type === 'track') ? '152' : '352';
    iframe.setAttribute('frameborder', '0');
    iframe.setAttribute('allow', 'autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture');
    iframe.style.cssText = 'display:block;border-radius:0 12px 0 0;user-select:auto;pointer-events:all;';
    spotifyPlayer.appendChild(iframe);
    if(result.type === 'track'){
      const replayBtn = document.createElement('button');
      replayBtn.textContent = '↺ Replay';
      replayBtn.style.cssText = 'display:block;width:100%;padding:5px;background:rgba(255,255,255,0.08);border:none;color:#e8eaed;cursor:pointer;font-size:0.72rem;user-select:auto;';
      replayBtn.onclick = function(){ loadIframe(result); };
      spotifyPlayer.appendChild(replayBtn);
    }
    playerLoaded = true;
    showPlayer();
    btnHide.style.display = 'block';
  }

  btnLoad.onclick = function(){
    const result = parseSpotifyEmbed(spotifyInput.value);
    if(!result){ alert('Paste a valid Spotify link or URI (track, album, artist, playlist).'); return; }
    if(result.type === 'artist'){
      alert('Artist embeds require Spotify Premium to play. Paste a playlist or album link instead for free playback.');
      return;
    }
    loadIframe(result);
  };

  btnHide.onclick = function(){
    if(spotifyPlayer.style.opacity === '0'){ showPlayer(); }
    else { hidePlayer(); }
  };
})();

// ── Boot ───────────────────────────────────────────────────────────────────
if(NFTS.length > 0){
  showSlide(idx);
  startHeartbeat();
}else{
  document.getElementById('stage').innerHTML =
    '<p style="color:rgba(255,255,255,0.4);font-size:1.2rem">No staked NFTs found.</p>';
}
showControls();
</script>
</body>
</html>
