<?php
include_once 'db.php';
session_start();

// Public page — no auth required, but session used for "My NFTs" mode
$my_user_id = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;

// Fetch all staked NFTs with owner, project, collection, rate, mission, and delegation info
$sql = "SELECT nfts.id, asset_id, asset_name, nfts.name AS nft_display_name, ipfs,
               users.id AS user_id, users.username, users.discord_id, users.avatar,
               projects.id AS project_id, projects.name AS project_name, projects.currency,
               collections.id AS collection_id, collections.name AS collection_name,
               collections.rate AS rate,
               (SELECT quests.title FROM missions_nfts
                INNER JOIN missions ON missions.id = missions_nfts.mission_id
                INNER JOIN quests   ON quests.id   = missions.quest_id
                WHERE missions_nfts.nft_id = nfts.id AND missions.status = '0'
                LIMIT 1) AS mission_title,
               (SELECT ds_nft.name FROM diamond_skulls
                INNER JOIN nfts ds_nft ON ds_nft.id = diamond_skulls.diamond_skull_id
                WHERE diamond_skulls.nft_id = nfts.id
                LIMIT 1) AS diamond_skull_name
        FROM nfts
        INNER JOIN users       ON users.id = nfts.user_id
        INNER JOIN collections ON nfts.collection_id = collections.id
        INNER JOIN projects    ON projects.id = collections.project_id
        WHERE nfts.user_id != 0 AND nfts.ipfs IS NOT NULL AND nfts.ipfs != ''
          AND (users.visibility = 2 OR users.id = ".$my_user_id.")
        ORDER BY project_id, collection_id";

$result = $conn->query($sql);
$nfts_data = [];
if($result && $result->num_rows > 0){
    while($row = $result->fetch_assoc()){
        $name = !empty($row['nft_display_name']) ? $row['nft_display_name'] : $row['asset_name'];
        $nfts_data[] = [
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
            'collection_name' => htmlspecialchars($row['collection_name'], ENT_QUOTES),
            'rate'            => (int)$row['rate'],
            'mission'         => $row['mission_title'] ? htmlspecialchars($row['mission_title'], ENT_QUOTES) : null,
            'diamond_skull'   => $row['diamond_skull_name'] ? htmlspecialchars($row['diamond_skull_name'], ENT_QUOTES) : null,
        ];
    }
}
$conn->close();
$nfts_json   = json_encode($nfts_data, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT);
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

    /* ── Mobile ── */
    @media (max-width: 600px) {
      #nft-main, .nft-layer { max-width: 88vw; max-height: 52vh; }
      #placard { min-width: 92vw; padding: 10px 14px; }
      #p-meta { gap: 8px; }
    }
  </style>
</head>
<body>

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
      <img id="p-icon" src="" onerror="this.style.display='none'" alt="" />
      <span id="p-rate-val"></span>
    </div>
    <span class="p-sep">|</span>
    <span id="p-project"></span>
  </div>
  <div id="p-collection"></div>
  <div id="p-badges"></div>
</div>

<div id="progress"></div>

<div id="controls">
  <a class="ctrl-logo" href="dashboard.php">← Skulliance</a>
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

<div id="settings">
  <div class="s-head">Gallery Settings</div>

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
</div>

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
let fx        = 'fade';
let slideTimer, hideTimer;
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
const progress  = document.getElementById('progress');
const controls  = document.getElementById('controls');
const slideCtrl = document.getElementById('slide-ctr');
const ivVal     = document.getElementById('iv-val');
const ivSlider  = document.getElementById('iv-slider');
const settingsEl= document.getElementById('settings');
const statsEl   = document.getElementById('stats-display');

// ── Cross-fade background ──────────────────────────────────────────────────
function setBg(url){
  if(bgActive === 'a'){
    bgB.style.backgroundImage = "url('"+url+"')";
    bgB.style.opacity = '1';
    bgA.style.opacity = '0';
    bgActive = 'b';
  } else {
    bgA.style.backgroundImage = "url('"+url+"')";
    bgA.style.opacity = '1';
    bgB.style.opacity = '0';
    bgActive = 'a';
  }
}

// ── Show slide ─────────────────────────────────────────────────────────────
function showSlide(n){
  const nft = playlist[n];
  if(!nft) return;

  const poolUrl   = 'https://pool.pm/' + nft.asset_id;
  const avatarUrl = (nft.discord_id && nft.avatar)
    ? 'https://cdn.discordapp.com/avatars/'+nft.discord_id+'/'+nft.avatar+'.png'
    : 'icons/skull.png';

  // Hide NFT + placard
  nftMain.classList.remove('visible','kenburns');
  glitchR.classList.remove('active');
  glitchB.classList.remove('active');
  placard.classList.remove('visible');

  const doReveal = function(){
    setBg(nft.image);
    nftLink.href   = poolUrl;
    pTitle.href    = poolUrl;
    pTitle.textContent    = nft.name;
    pAvatar.src           = avatarUrl;
    // Owner links to their public profile
    pUsername.innerHTML   = '<a href="profile.php?username='+encodeURIComponent(nft.username)+'" target="_blank" style="color:#00c8a0;text-decoration:none;">'+nft.username+'</a>';
    pIcon.src             = 'icons/' + nft.currency + '.png';
    pRateVal.textContent  = nft.rate + ' ' + nft.currency.toUpperCase() + '/night';
    pProject.textContent  = nft.project_name;
    pColl.textContent     = nft.collection_name;
    slideCtrl.textContent = (n+1) + ' / ' + playlist.length;
    // Status badges
    pBadges.innerHTML = '';
    if(nft.mission){
      const b = document.createElement('span');
      b.className = 'badge badge-mission';
      b.textContent = '⚔ On Mission: ' + nft.mission;
      pBadges.appendChild(b);
    }
    if(nft.diamond_skull){
      const b = document.createElement('span');
      b.className = 'badge badge-skull';
      b.textContent = '💎 Delegated: ' + nft.diamond_skull;
      pBadges.appendChild(b);
    }

    nftMain.style.removeProperty('animation');
    void nftMain.offsetWidth; // reflow to restart animation

    if(fx === 'kenburns'){
      nftMain.style.setProperty('--kb-dur', (intervalMs/1000)+'s');
    }

    nftMain.onload = reveal;
    nftMain.src = nft.image;
    if(nftMain.complete && nftMain.naturalWidth > 0) reveal();

    if(fx === 'glitch'){
      glitchR.src = nft.image;
      glitchB.src = nft.image;
    }
  };

  function reveal(){
    nftMain.classList.add('visible');
    if(fx === 'kenburns') nftMain.classList.add('kenburns');
    placard.classList.add('visible');
    if(fx === 'glitch'){
      setTimeout(function(){
        glitchR.classList.add('active');
        glitchB.classList.add('active');
        setTimeout(function(){
          glitchR.classList.remove('active');
          glitchB.classList.remove('active');
        }, 450);
      }, 150);
    }
    startProgress();
  }

  // Brief dark gap between slides (skip for glitch — glitch IS the transition)
  if(fx === 'glitch') doReveal();
  else setTimeout(doReveal, 500);
}

// ── Progress bar ───────────────────────────────────────────────────────────
function startProgress(){
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

// ── Timer ──────────────────────────────────────────────────────────────────
function scheduleNext(){
  clearTimeout(slideTimer);
  if(playing) slideTimer = setTimeout(advance, intervalMs);
}

function advance(){
  idx++;
  if(idx >= playlist.length){
    playlist = buildPlaylist();
    idx = 0;
  }
  showSlide(idx);
  scheduleNext();
}

// ── Controls ───────────────────────────────────────────────────────────────
document.getElementById('btn-next').onclick = function(){
  clearTimeout(slideTimer);
  advance();
};

document.getElementById('btn-prev').onclick = function(){
  clearTimeout(slideTimer);
  idx = (idx - 1 + playlist.length) % playlist.length;
  showSlide(idx);
  scheduleNext();
};

const btnPP = document.getElementById('btn-pp');
btnPP.onclick = function(){
  playing = !playing;
  btnPP.innerHTML = playing ? '&#9646;&#9646;' : '&#9654;';
  if(playing){ scheduleNext(); startProgress(); }
  else { clearTimeout(slideTimer); progress.style.transition='none'; }
};

// ── Settings toggle ────────────────────────────────────────────────────────
document.getElementById('btn-cfg').onclick = function(e){
  e.stopPropagation();
  settingsEl.classList.toggle('open');
};
document.addEventListener('click', function(e){
  if(!settingsEl.contains(e.target)) settingsEl.classList.remove('open');
});

// ── Effect pills ───────────────────────────────────────────────────────────
document.querySelectorAll('#fx-pills .pill').forEach(function(p){
  p.onclick = function(){
    document.querySelectorAll('#fx-pills .pill').forEach(function(x){ x.classList.remove('on'); });
    p.classList.add('on');
    fx = p.dataset.fx;
  };
});

// ── Interval slider ────────────────────────────────────────────────────────
ivSlider.oninput = function(){
  intervalMs = parseInt(this.value) * 1000;
  ivVal.textContent = this.value + 's';
  if(playing){ clearTimeout(slideTimer); scheduleNext(); startProgress(); }
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
      clearTimeout(slideTimer);
      showSlide(idx);
      scheduleNext();
      updateStats();
    };
  });
}

// ── Reshuffle ──────────────────────────────────────────────────────────────
document.getElementById('btn-reshuffle').onclick = function(){
  playlist = buildPlaylist();
  idx = 0;
  clearTimeout(slideTimer);
  showSlide(idx);
  scheduleNext();
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
  else if(e.key==='Escape')            { settingsEl.classList.remove('open'); }
});

// ── Boot ───────────────────────────────────────────────────────────────────
if(NFTS.length > 0){
  showSlide(idx);
  scheduleNext();
}else{
  document.getElementById('stage').innerHTML =
    '<p style="color:rgba(255,255,255,0.4);font-size:1.2rem">No staked NFTs found.</p>';
}
showControls();
</script>
</body>
</html>
