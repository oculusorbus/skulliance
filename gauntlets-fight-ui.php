<?php
/**
 * gauntlets-fight-ui.php -- the look, the noise and the music.
 *
 * Included ONCE by gauntlets.php, outside #gf-area. That placement is the
 * whole trick: the fragment swaps on every turn, and anything inside it
 * would be destroyed and rebuilt each time -- which for an <audio>
 * element means the soundtrack restarting on every swing.
 */
if (defined('GF_UI_DONE')) return;
define('GF_UI_DONE', 1);
?>
<style>
/* ── the fight ─────────────────────────────────────────────────────── */
.gf              { margin-bottom: 24px; position: relative; }
/*
 * THE OPPONENT'S WORLD. The encounter is staged in the theme of the
 * project their NFT comes from, reusing the Monstrocity theme art that
 * already exists per project. Heavily dimmed on purpose: it is a
 * backdrop, and a busy picture behind a health bar is just noise.
 */
.gf-has-bg::before { content: ''; position: absolute; inset: -14px -14px 0;
                     background-image: var(--gf-bg); background-size: cover;
                     background-position: center; opacity: .22; border-radius: 10px;
                     pointer-events: none; z-index: 0;
                     mask-image: linear-gradient(180deg, #000 0%, #000 55%, transparent 100%);
                     -webkit-mask-image: linear-gradient(180deg, #000 0%, #000 55%, transparent 100%); }
.gf-has-bg > *   { position: relative; z-index: 1; }
.gf-arena        { display: grid; grid-template-columns: 1fr auto 1fr; gap: 14px;
                   align-items: start; margin-bottom: 14px; }
.gf-vs           { align-self: center; font-size: 1.1rem; font-weight: 800;
                   color: rgba(255,255,255,.28); letter-spacing: .08em; }
/* The cap lives on the whole side, not just the portrait, so the bar and
   the name line up with the picture instead of running wider than it. */
.gf-side         { min-width: 0; max-width: min(190px, 42vw); margin: 0 auto; }
/* CAPPED, and that matters more than it sounds. Unconstrained these grew
   to fill half the grid column -- on a 900px page two 420px portraits and
   nothing else, with the bars, the log and the choices all below the
   fold. A fight you have to scroll to play is not a fight you read. */
.gf-portrait     { position: relative; border: 2px solid rgba(255,255,255,.1);
                   border-radius: 10px; overflow: hidden; background: #002f44;
                   margin: 0 auto; }
.gf-you .gf-portrait { border-color: #00c8a0; }
.gf-foe .gf-portrait { border-color: rgba(224,85,85,.55); }
.gf-portrait img { width: 100%; aspect-ratio: 1; object-fit: cover; display: block;
                   filter: drop-shadow(0 3px 8px rgba(0,0,0,.5)); }
/* The glow layer. Colour says WHAT happened, which is how a player reads
   an exchange without looking at the log. */
.gf-fx           { position: absolute; inset: 0; pointer-events: none; opacity: 0;
                   box-shadow: inset 0 0 26px 8px currentColor; color: transparent; }
.gf-name         { font-size: .8rem; font-weight: 600; color: #fff; margin: 7px 0 5px;
                   text-align: center; white-space: nowrap; overflow: hidden;
                   text-overflow: ellipsis; }

/* ── health, with the damage trail ─────────────────────────────────── */
.gf-bar          { position: relative; height: 20px; background: #0b1b24;
                   border: 1px solid rgba(0,0,0,.6); border-radius: 4px; overflow: hidden;
                   background-image: repeating-linear-gradient(90deg,
                       rgba(0,0,0,.30) 0 1px, rgba(0,0,0,0) 1px 10%);
                   box-shadow: inset 0 1px 3px rgba(0,0,0,.55); }
/* The trail drains a beat behind the fill, so the strip between them IS
   the hit that just landed. */
.gf-trail        { position: absolute; inset: 0 auto 0 0;
                   background: linear-gradient(180deg,#ff8a8a 0%,#c0392b 100%);
                   transition: width .55s cubic-bezier(.33,1,.68,1) .18s; }
.gf-fill         { position: absolute; inset: 0 auto 0 0; transition: width .3s ease;
                   background-image: linear-gradient(180deg, rgba(255,255,255,.30) 0,
                       rgba(255,255,255,.06) 48%, rgba(0,0,0,.18) 100%); }
.gf-bar.ok   .gf-fill { background-color: #00c8a0; }
.gf-bar.warn .gf-fill { background-color: #ffc800; }
.gf-bar.crit .gf-fill { background-color: #ff5c5c; }
/* The figure, drawn twice and clipped at the fill edge: dark over the
   colour, white over the empty rail. One ink cannot read against both. */
.gf-num          { position: absolute; inset: 0; pointer-events: none;
                   font: bold 11px/1 Arial, Helvetica, sans-serif;
                   font-variant-numeric: tabular-nums; letter-spacing: .03em; }
.gf-ink          { position: absolute; inset: 0; display: flex; align-items: center;
                   justify-content: center; transition: clip-path .3s ease; }
/* Near-black over the fill: it measures 6:1 or better on every band the
   bar uses, where white is 1.6:1 on the yellow. */
.gf-ink-on       { color: #07140f; text-shadow: 0 1px 0 rgba(255,255,255,.3);
                   clip-path: inset(0 calc(100% - var(--hp-pct, 100%)) 0 0); }
/* White over the empty rail and the damage trail, where black would be
   just as unreadable. */
.gf-ink-off      { color: #fff; text-shadow: 0 1px 2px rgba(0,0,0,.95), 0 0 3px rgba(0,0,0,.8);
                   clip-path: inset(0 0 0 var(--hp-pct, 100%)); }
.gf-armour       { font-size: .7rem; color: #9fd8ff; margin-top: 5px; text-align: center; }
.gf-shield       { color: #4fa3d9; }

/* ── the exchange log ──────────────────────────────────────────────── */
.gf-log          { list-style: none; margin: 0; padding: 10px 12px; background: #002f44;
                   border: 1px solid rgba(255,255,255,.07); border-radius: 8px;
                   max-height: 118px; overflow-y: auto; }
.gf-li           { font-size: .78rem; color: rgba(255,255,255,.75); margin: 3px 0; }
.gf-who          { display: inline-block; min-width: 42px; font-weight: 700;
                   text-transform: uppercase; font-size: .66rem; letter-spacing: .07em; }
.gf-you  .gf-who, .gf-li.gf-you  .gf-who { color: #00c8a0; }
.gf-li.gf-foe  .gf-who { color: #e05555; }
.gf-k-heal strong { color: #5ce8b0; }
.gf-k-hit  strong { color: #ff8a8a; }
.gf-soak         { color: rgba(159,216,255,.7); font-size: .7rem; }

/* ── the choices ───────────────────────────────────────────────────── */
.gf-actions      { display: flex; flex-direction: column; gap: 10px; }
.gf-group-label  { text-align: center; font-size: .62rem; text-transform: uppercase; letter-spacing: .12em;
                   color: rgba(255,255,255,.35); margin-bottom: 5px; }
/* CENTRED. Left-aligned, a short row like Defend sat hard against the
   edge under a long Attack row and read as a ragged column rather than
   a set of choices. */
.gf-group-row    { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.gf-btn          { display: inline-flex; align-items: center; gap: 7px; cursor: pointer;
                   background: #123349; color: #e8eaed; border: 1px solid rgba(0,200,160,.28);
                   border-radius: 8px; padding: 10px 13px; font: 600 .82rem/1 inherit;
                   /* a tap on the label must fire the button, not start a
                      selection -- the missions button taught us that */
                   user-select: none; -webkit-user-select: none; }
.gf-btn:hover:not(:disabled), .gf-btn:focus-visible { background: #1a4663; border-color: #00c8a0; }
.gf-btn:disabled { opacity: .35; cursor: default; }
.gf-btn img      { width: 18px; height: 18px; object-fit: contain; }
.gf-left         { font-style: normal; font-size: .68rem; color: #00c8a0;
                   background: rgba(0,0,0,.35); border-radius: 4px; padding: 2px 5px; }
.gf-btn.is-spent .gf-left { color: rgba(255,255,255,.4); }
.gf-heal         { border-color: rgba(92,232,176,.4); }
.gf-stake        { border-color: rgba(255,200,0,.4); color: #ffe08a; }
.gf-stake:hover:not(:disabled) { border-color: #ffc800; background: #2a2a18; }
.gf-go           { background: #00c8a0; color: #07111d; border-color: #2ee3bf; }
.gf-go:hover     { background: #00e6b8; color: #07111d; }
/* The result is ONE control, so it is not laid out like the stack of
   grouped choices -- as a column child it stretched full width with its
   label hard left, which reads as a broken row rather than a button. */
.gf-done         { display: flex; justify-content: center; margin-top: 4px; }
.gf-done .gf-btn { min-width: 180px; justify-content: center; }
.gf-loot         { font-style: normal; font-size: .6rem; text-transform: uppercase;
                   letter-spacing: .08em; color: #ffc800; background: rgba(255,200,0,.12);
                   border: 1px solid rgba(255,200,0,.35); border-radius: 3px; padding: 1px 4px; }
.gf-log          { margin-top: 14px; }
.gf-hint         { font-size: .72rem; color: rgba(255,255,255,.4); margin: 10px 0 0; }
.gf-result       { font-size: 1.1rem; font-weight: 700; text-align: center; padding: 12px;
                   border-radius: 8px; margin-bottom: 12px; }
.gf-result.win   { background: rgba(0,200,160,.14); border: 1px solid #00c8a0; color: #00c8a0; }
.gf-result.loss  { background: rgba(224,85,85,.14); border: 1px solid #e05555; color: #e05555; }
/* The replacement cards in the Fast Forward overlay. The bar is the
   reason the overlay exists, so it gets room; a card already at zero
   is out for the run and must not be selectable. */
.ff-hand-grid .gf-bar { height: 14px; margin-top: 6px; }
.ff-hand-grid .gf-num { font-size: 9px; }
.nft-card.ff-down { opacity: .45; cursor: not-allowed; pointer-events: none; }
.nft-card.ff-down .nft-img { filter: grayscale(1) brightness(.5); }
.ff-down-label   { font-size: .62rem; text-transform: uppercase; letter-spacing: .08em;
                   color: #e05555; margin-top: 4px; }
.gchest          { text-align: center; margin: 18px 0 8px; }
.gchest-btn      { background: none; border: 0; cursor: pointer; display: inline-flex;
                   flex-direction: column; align-items: center; gap: 8px; color: #ffc800;
                   font: 700 .85rem/1 inherit; text-transform: uppercase; letter-spacing: .08em;
                   user-select: none; -webkit-user-select: none; }
.gchest-img      { width: 150px; max-width: 46vw; height: auto; display: block;
                   filter: drop-shadow(0 6px 18px rgba(0,0,0,.55)); }
.gchest-btn:hover .gchest-img { transform: translateY(-3px); }
.gchest-btn .gchest-img { transition: transform .18s ease; animation: gchestBreathe 2.6s ease-in-out infinite; }
@keyframes gchestBreathe { 0%,100% { filter: drop-shadow(0 6px 18px rgba(0,0,0,.55)); }
                           50% { filter: drop-shadow(0 6px 22px rgba(255,200,0,.45)); } }
.gchest-title    { font-size: .72rem; text-transform: uppercase; letter-spacing: .12em;
                   color: rgba(255,255,255,.45); margin: 10px 0 8px; }
.gchest-items    { display: flex; flex-wrap: wrap; gap: 8px; justify-content: center; }
.gchest-item     { background: rgba(255,200,0,.1); border: 1px solid rgba(255,200,0,.35);
                   border-radius: 6px; padding: 6px 10px; font-size: .8rem; color: #ffe08a;
                   animation: gchestPop .45s cubic-bezier(.2,.8,.3,1) backwards; }
.gchest-item:nth-child(2) { animation-delay: .09s; }
.gchest-item:nth-child(3) { animation-delay: .18s; }
.gchest-item:nth-child(4) { animation-delay: .27s; }
.gchest-item:nth-child(n+5) { animation-delay: .36s; }
@keyframes gchestPop { from { opacity: 0; transform: translateY(10px) scale(.9); }
                       to   { opacity: 1; transform: none; } }
@media (prefers-reduced-motion: reduce) {
  .gchest-btn .gchest-img { animation: none; }
  .gchest-item { animation: none; }
}
.gf-err          { background: rgba(224,85,85,.12); border: 1px solid rgba(224,85,85,.5);
                   color: #ffb3b3; padding: 10px 12px; border-radius: 6px; font-size: .82rem; }

/* ── impact ────────────────────────────────────────────────────────── */
/* Percentage-based, so they scale to any portrait size. Lifted from
   DHC Arena's @keyframes hit / hitBig for exactly that reason. */
@keyframes gfHit    { 0%{transform:translateX(0)} 30%{transform:translateX(-3%)}
                      60%{transform:translateX(2.4%)} 100%{transform:translateX(0)} }
@keyframes gfHitBig { 0%{transform:translateX(0)} 20%{transform:translateX(-5%)}
                      45%{transform:translateX(4%)} 70%{transform:translateX(-2%)}
                      100%{transform:translateX(0)} }
.gf-side.is-hit    .gf-portrait { animation: gfHit .3s ease-out; }
.gf-side.is-hitbig .gf-portrait { animation: gfHitBig .42s ease-out; }
/* The drop-shadow is restated in both frames: filter is ONE property, so
   animating it to `none` drops the portrait's shadow and snaps it back. */
@keyframes gfFlash { 0%   { filter: drop-shadow(0 3px 8px rgba(0,0,0,.5)) brightness(2.5) saturate(.25); }
                     100% { filter: drop-shadow(0 3px 8px rgba(0,0,0,.5)); } }
.gf-side.is-hit .gf-portrait img, .gf-side.is-hitbig .gf-portrait img { animation: gfFlash .24s ease-out; }
.gf-fx.show      { animation: gfGlow .5s ease-out; }
@keyframes gfGlow { 0% { opacity: .85; } 100% { opacity: 0; } }
.gf-fx.c-hit     { color: #ff5c5c; }
.gf-fx.c-heal    { color: #5ce8b0; }
.gf-fx.c-armor   { color: #4fa3d9; }
.gf-fx.c-down    { color: #ff2b2b; }
/*
 * THE FALLEN. Grey, dimmed and slumped -- a fighter that is out should
 * read as out at a glance, without the result text. The transition is
 * long enough to watch rather than a cut.
 */
.gf-side .gf-portrait img { transition: filter .7s ease, opacity .7s ease, transform .7s ease; }
.gf-side.is-down .gf-portrait img { filter: grayscale(1) brightness(.42)
                                            drop-shadow(0 3px 8px rgba(0,0,0,.5));
                                    opacity: .55; transform: scale(.96) rotate(-2deg); }
.gf-side.is-down .gf-portrait { border-color: rgba(255,255,255,.12) !important; }
.gf-side.is-down .gf-name { color: rgba(255,255,255,.4); }
.gf-side.is-down .gf-portrait::after {
  content: ''; position: absolute; inset: 0; background: rgba(7,17,29,.35); pointer-events: none; }
@keyframes gfSlump { 0% { transform: none; } 100% { transform: scale(.96) rotate(-2deg); } }
.gf-k-down strong { color: #ff2b2b; }
/* The number, out of flow so it cannot push the card around. */
.gf-float        { position: absolute; left: 50%; top: 36%; transform: translate(-50%,0);
                   font: 900 26px/1 Arial, Helvetica, sans-serif; font-variant-numeric: tabular-nums;
                   pointer-events: none; z-index: 5; color: #ff5f5f;
                   text-shadow: 0 2px 0 #4a0d0d, 0 -1px 0 #4a0d0d, 1px 0 0 #4a0d0d,
                                -1px 0 0 #4a0d0d, 0 0 14px rgba(255,80,80,.6);
                   animation: gfFloat 1s cubic-bezier(.2,.7,.3,1) forwards; }
.gf-float.heal   { color: #5ce8b0; text-shadow: 0 2px 0 #06402c, 0 0 12px rgba(90,230,170,.5); }
.gf-float.big    { font-size: 38px; color: #ff4040; }
@keyframes gfFloat { 0%{opacity:0;transform:translate(-50%,8px) scale(.7)}
                     18%{opacity:1;transform:translate(-50%,-4px) scale(1.12)}
                     30%{transform:translate(-50%,-8px) scale(1)}
                     100%{opacity:0;transform:translate(-50%,-48px) scale(1)} }
@media (prefers-reduced-motion: reduce) {
  .gf-side.is-hit .gf-portrait, .gf-side.is-hitbig .gf-portrait,
  .gf-side.is-hit .gf-portrait img, .gf-side.is-hitbig .gf-portrait img { animation: none; }
  .gf-float { animation-duration: .6s; }
  .gf-trail, .gf-fill { transition: none; }
}
@media (max-width: 600px) {
  .gf-arena { gap: 8px; }
  .gf-vs { font-size: .85rem; }
  .gf-btn { padding: 9px 11px; font-size: .78rem; }
  .gf-float { font-size: 22px; } .gf-float.big { font-size: 30px; }
}
</style>

<!-- OUTSIDE #gf-area on purpose: the fragment is replaced every turn, and
     an <audio> element inside it would be torn down and rebuilt each time,
     restarting the soundtrack on every swing. -->
<audio id="gf-music" loop preload="none"></audio>

<script>
(function () {
  var AREA = 'gf-area';
  /* audio/sounds/, NOT sounds/. Everything 404'd silently on the first
     build because the path was read off the tail of a grep hit. */
  var SND  = '/staking/audio/sounds/';
  var TRK  = '/staking/audio/tracks/';
  /* The tracks already ARE the state machine; nothing new was composed. */
  var MUSIC = { theme: 'Crypt Crawl Theme.mp3', frantic: 'Crypt Crawl Frantic.mp3',
                doom: 'Crypt Crawl Doom.mp3', win: 'Crypt Crawl Triumph.mp3',
                lose: 'Crypt Crawl Death.mp3' };
  var cur = null, muted = false;

  try { muted = localStorage.getItem('gfMute') === '1'; } catch (e) {}

  /*
   * TWO SOUND LIBRARIES, and they are not interchangeable. The .mp3 set
   * Crypt Crawl uses lives under audio/sounds/; the .ogg set DHC Arena
   * and Monstrocity use lives under sounds/. skullcoinlose is in the
   * second -- it is the cue DHC plays when a fighter is knocked out, and
   * the one a player already associates with something falling.
   */
  var OGG = { ko: 'skullcoinlose' };
  function play(name) {
    if (!name || muted) return;
    var url = OGG[name] ? '/staking/sounds/' + OGG[name] + '.ogg' : SND + name + '.mp3';
    try { var a = new Audio(url); a.volume = OGG[name] ? 0.55 : 0.5; a.play().catch(function(){}); } catch (e) {}
  }
  function music(key) {
    if (cur === key) return;
    cur = key;
    var el = document.getElementById('gf-music');
    if (!el || muted) return;
    el.loop = (key === 'theme' || key === 'frantic' || key === 'doom');
    el.src  = TRK + encodeURIComponent(MUSIC[key]);
    el.volume = 0.32;
    el.play().catch(function () {});   /* blocked until a gesture; fine */
  }
  function moodFrom(area) {
    var hp = parseInt(area.getAttribute('data-hp') || '0', 10);
    var mx = parseInt(area.getAttribute('data-hpmax') || '1', 10) || 1;
    if (area.classList.contains('gf-won'))  return 'win';
    if (area.classList.contains('gf-lost')) return 'lose';
    var r = hp / mx;
    return r <= 0.25 ? 'doom' : (r <= 0.5 ? 'frantic' : 'theme');
  }

  /* Animate exactly what the server said happened, and nothing else. */
  function animate(area) {
    var tl;
    try { tl = JSON.parse(area.getAttribute('data-tl') || '[]'); } catch (e) { tl = []; }
    tl.forEach(function (e, i) {
      setTimeout(function () {
        var side = area.querySelector(e.who === 'you' ? '.gf-foe' : '.gf-you');
        if (e.kind === 'heal' || e.kind === 'armor') side = area.querySelector(e.who === 'you' ? '.gf-you' : '.gf-foe');
        if (!side) return;
        var fx = side.querySelector('.gf-fx');
        /* A FALL NAMES ITS OWN SIDE. Every other entry names the ACTOR,
           so the target is the other one -- `down` is the exception and
           has to short-circuit, or the slump and the red glow land on
           whoever is still standing. */
        if (e.kind === 'down') {
          var dead = area.querySelector(e.who === 'you' ? '.gf-you' : '.gf-foe');
          if (dead) {
            dead.classList.add('is-down');
            var dfx = dead.querySelector('.gf-fx');
            if (dfx) { dfx.className = 'gf-fx c-down'; void dfx.offsetWidth; dfx.classList.add('show'); }
          }
          play(e.sfx);
          return;
        }
        if (e.kind === 'hit') {
          side.classList.remove('is-hit', 'is-hitbig');
          void side.offsetWidth;                /* re-adding a class does not restart a CSS animation */
          side.classList.add(e.big ? 'is-hitbig' : 'is-hit');
          setTimeout(function () { side.classList.remove('is-hit', 'is-hitbig'); }, 500);
        }
        if (fx) {
          fx.className = 'gf-fx c-' + (e.kind === 'hit' ? 'hit' : e.kind);
          void fx.offsetWidth; fx.classList.add('show');
        }
        if (e.amount) {
          var p = side.querySelector('.gf-portrait');
          if (p) {
            var n = document.createElement('div');
            n.className = 'gf-float' + (e.kind === 'heal' ? ' heal' : '') + (e.big ? ' big' : '');
            n.textContent = (e.kind === 'heal' ? '+' : '-') + e.amount;
            n.style.left = (42 + Math.random() * 16) + '%';
            p.appendChild(n);
            n.addEventListener('animationend', function () { n.remove(); });
          }
        }
        play(e.sfx);
      }, i * 420);
    });
    music(moodFrom(area));
  }

  function wire(area) {
    var form = area.querySelector('#gf-form');
    if (!form) return;
    form.addEventListener('submit', function (ev) {
      var btn = ev.submitter;
      if (!btn || !btn.name) return;            /* no submitter: let it post normally */
      ev.preventDefault();
      Array.prototype.forEach.call(form.querySelectorAll('button'), function (b) { b.disabled = true; });
      var body = new URLSearchParams();
      body.append('encounter_id', form.querySelector('[name=encounter_id]').value);
      body.append('turn', btn.value);
      fetch('ajax/gauntlet-fight.php', {
        method: 'POST', credentials: 'same-origin',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString()
      }).then(function (r) { return r.text(); })
        .then(function (html) {
          var holder = document.createElement('div');
          holder.innerHTML = html;
          var next = holder.querySelector('#' + AREA);
          var old  = document.getElementById(AREA);
          if (!next || !old) { form.submit(); return; }   /* fall back to a real post */
          old.replaceWith(next);
          animate(next); wire(next);
        })
        .catch(function () { form.submit(); });
    });
  }

  function boot() {
    var area = document.getElementById(AREA);
    if (!area) return;
    wire(area); music(moodFrom(area));
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot);
  else boot();
})();
</script>
