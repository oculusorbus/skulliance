<?php
/**
 * DHC TRAIT DROP MODAL -- shared across every game.
 *
 * Include once, near the end of a game page. It defines window.DHC_DROP(),
 * which a game calls from its win/loss/defeat screen:
 *
 *     DHC_DROP({ game: 'guardians', value: wavesHeld, unit: 'waves held' });
 *
 * It claims the drop server-side and, if something was awarded, reveals it. If
 * nothing was awarded -- below the floor, daily cap, not signed in -- it stays
 * silent (or shows the near-miss target when the game names its unit), so a
 * game can call it after every run without knowing any of the rules. The rules
 * live in one place and it is not here.
 *
 * THE REVEAL IS THE PRODUCT. A trait appearing instantly is a notification; a
 * trait that builds, cracks and bursts is a moment. The sequence is staged --
 * shutter, crack, burst, settle -- and its intensity scales with the tier, so a
 * common is a pleasant tick and a mythic is an event. Getting that wrong makes
 * every drop feel the same, which is the fastest way to make drops stop
 * mattering.
 *
 * Deliberately standalone: no framework, no platform CSS dependency, scoped
 * names, animation on transform/opacity only. It has to sit on top of nine
 * different game interfaces without any of them accommodating it.
 */

// Idempotent: header.php includes this for every page so a drop can surface
// wherever it happens (a daily-reward claim fires from anywhere), while the
// nine game pages still include it directly. Without this guard the second
// include would duplicate every id and the modal would stop working.
if (defined('DHC_DROPMODAL_LOADED')) return;
define('DHC_DROPMODAL_LOADED', true);
?>
<style>
#dhcdrop-veil{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;
  background:rgba(4,12,22,.88);backdrop-filter:blur(3px);padding:20px}
#dhcdrop-veil.on{display:flex}
#dhcdrop-box{width:min(348px,100%);background:#0a1929;border:1px solid #1b3346;border-radius:4px;
  box-shadow:0 24px 70px rgba(0,0,0,.6);overflow:hidden;text-align:center;position:relative;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#e8eaed;
  animation:dhcdrop-in .3s cubic-bezier(.2,.9,.3,1.2)}
@keyframes dhcdrop-in{from{transform:scale(.88) translateY(14px);opacity:0}to{transform:none;opacity:1}}

/* tier colour drives everything downstream: rays, glow, badge, shimmer */
#dhcdrop-box{--dt:#7a9eb0;--glow:0}

#dhcdrop-kicker{font-size:9px;letter-spacing:.24em;text-transform:uppercase;padding:13px 0 0;color:#7a9eb0}

/* ---- the stage ---- */
#dhcdrop-stage{width:196px;height:196px;margin:10px auto 0;position:relative}

/* rays: a slow conic sweep, only visible once the crack happens, and only
   really bright for the rare tiers */
#dhcdrop-rays{position:absolute;inset:-46px;opacity:0;pointer-events:none;
  background:conic-gradient(from 0deg,transparent 0 6deg,var(--dt) 7deg 8deg,transparent 9deg 22deg);
  mask:radial-gradient(circle,#000 22%,transparent 68%);
  -webkit-mask:radial-gradient(circle,#000 22%,transparent 68%)}
.revealed #dhcdrop-rays{opacity:calc(.18 + var(--glow) * .5);animation:dhcdrop-spin 14s linear infinite}
@keyframes dhcdrop-spin{to{transform:rotate(360deg)}}

/* expanding shock ring at the moment of the crack */
#dhcdrop-ring{position:absolute;inset:0;border-radius:50%;border:2px solid var(--dt);opacity:0}
.cracking #dhcdrop-ring{animation:dhcdrop-ring .7s cubic-bezier(.2,.7,.3,1) forwards}
@keyframes dhcdrop-ring{
  0%{opacity:.9;transform:scale(.55)}
  100%{opacity:0;transform:scale(1.9)}}

/* the shutter: what sits there while the drop is "deciding" */
#dhcdrop-shut{position:absolute;inset:0;border-radius:3px;border:1px solid #1b3346;
  background:repeating-linear-gradient(135deg,#0d1e2e 0 9px,#0f2436 9px 18px);
  display:flex;align-items:center;justify-content:center;font-size:40px;color:#3d5b73;
  animation:dhcdrop-throb .62s ease-in-out infinite}
@keyframes dhcdrop-throb{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.045);opacity:.82}}
.revealed #dhcdrop-shut{opacity:0;transform:scale(1.25);transition:opacity .28s,transform .28s;animation:none}

/* the art itself */
#dhcdrop-art{position:absolute;inset:0;border-radius:3px;overflow:hidden;opacity:0;transform:scale(.62);
  background:repeating-conic-gradient(#0d1e2e 0% 25%,#0a1929 0% 50%) 50%/14px 14px}
#dhcdrop-art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.revealed #dhcdrop-art{opacity:1;transform:none;
  transition:opacity .34s ease-out,transform .52s cubic-bezier(.18,1.5,.4,1);
  box-shadow:0 0 calc(16px + var(--glow) * 46px) rgba(255,255,255,calc(var(--glow) * .16)),
             0 0 0 1px rgba(255,255,255,.05)}

/* a single shimmer sweep across the art, rare tiers only */
#dhcdrop-shine{position:absolute;inset:0;pointer-events:none;opacity:0;
  background:linear-gradient(115deg,transparent 38%,rgba(255,255,255,.5) 50%,transparent 62%)}
.revealed.rare #dhcdrop-shine{animation:dhcdrop-shine 1.05s .32s ease-out}
@keyframes dhcdrop-shine{0%{opacity:0;transform:translateX(-115%)}
  22%{opacity:.85}100%{opacity:0;transform:translateX(115%)}}

/* text stages in after the art lands, so the eye goes to the art first */
#dhcdrop-name,#dhcdrop-tier,#dhcdrop-meta{opacity:0;transform:translateY(7px)}
.revealed #dhcdrop-name{opacity:1;transform:none;transition:all .34s .16s}
.revealed #dhcdrop-tier{opacity:1;transform:none;transition:all .34s .26s}
.revealed #dhcdrop-meta{opacity:1;transform:none;transition:all .34s .36s}

#dhcdrop-name{font-size:16px;padding:13px 14px 2px;line-height:1.3}
#dhcdrop-tier{font-size:10px;letter-spacing:.22em;text-transform:uppercase;padding:2px 0 0;color:var(--dt);
  text-shadow:0 0 calc(var(--glow) * 16px) var(--dt)}
#dhcdrop-meta{font-size:10.5px;color:#7a9eb0;padding:7px 14px 0;line-height:1.6}
#dhcdrop-new,#dhcdrop-dupe{display:inline-block;margin:9px 0 0;font-size:9px;letter-spacing:.16em;
  text-transform:uppercase;border-radius:999px;padding:3px 9px}
#dhcdrop-new{border:1px solid #00c8a0;color:#00c8a0}
#dhcdrop-dupe{border:1px solid #2a4a63;color:#7a9eb0}

/* near miss: no art, no fanfare, just the target */
#dhcdrop-veil.miss #dhcdrop-stage,#dhcdrop-veil.miss #dhcdrop-new,#dhcdrop-veil.miss #dhcdrop-dupe{display:none}
#dhcdrop-veil.miss #dhcdrop-name,#dhcdrop-veil.miss #dhcdrop-tier,#dhcdrop-veil.miss #dhcdrop-meta{
  opacity:1;transform:none}
#dhcdrop-bar{height:5px;background:#0d1e2e;border-radius:3px;margin:13px 24px 0;overflow:hidden}
#dhcdrop-bar i{display:block;height:100%;background:#00c8a0;width:0;transition:width .7s .1s cubic-bezier(.2,.8,.3,1)}

/*
 * THE CONTROLS DEFEND THEMSELVES.
 *
 * This modal is dropped onto nine game pages, each with its own button and
 * link styling, and it cannot know what any of them do. On Monstrocity the
 * host's own button rules repainted Close as a filled mint button and
 * recoloured the link, so the secondary action looked like the primary one
 * and the pair read as a mistake.
 *
 * !important is the right tool here rather than a smell: these are a widget's
 * own controls, scoped under its id, and the alternative is losing a
 * specificity race against nine stylesheets that will keep changing.
 */
#dhcdrop-actions{display:flex;gap:8px;padding:15px;margin:0}
#dhcdrop-actions a,#dhcdrop-actions button{
  flex:1 1 0 !important;
  display:block !important;
  width:auto !important;
  min-width:0 !important;
  margin:0 !important;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace !important;
  font-size:10px !important;
  font-weight:400 !important;
  line-height:1.2 !important;
  letter-spacing:.1em !important;
  text-transform:uppercase !important;
  text-decoration:none !important;
  text-align:center !important;
  padding:10px 6px !important;
  cursor:pointer !important;
  border-radius:2px !important;
  box-shadow:none !important;
  /* SECONDARY by default: outline only. Close must never look like the
     primary action, which is the whole point of having two. */
  background:transparent !important;
  border:1px solid #2a4a63 !important;
  color:#c7d3dc !important;
}
#dhcdrop-actions a.go{
  background:#00c8a0 !important;
  border-color:#00c8a0 !important;
  color:#07111d !important;
  font-weight:700 !important;
}
#dhcdrop-actions button:hover{border-color:#00c8a0 !important;color:#00c8a0 !important}
#dhcdrop-actions a.go:hover{filter:brightness(1.12)}
#dhcdrop-actions a:focus-visible,#dhcdrop-actions button:focus-visible{
  outline:2px solid #00c8a0 !important;outline-offset:2px !important}

/* Someone who asked for less motion still gets the drop, just immediately. */
@media (prefers-reduced-motion:reduce){
  #dhcdrop-box,#dhcdrop-shut,#dhcdrop-rays,#dhcdrop-ring,#dhcdrop-shine{animation:none!important}
  #dhcdrop-art,#dhcdrop-name,#dhcdrop-tier,#dhcdrop-meta{transition:none!important}
}
</style>

<div id="dhcdrop-veil" role="dialog" aria-modal="true" aria-labelledby="dhcdrop-name">
  <div id="dhcdrop-box">
    <div id="dhcdrop-kicker">Trait acquired</div>
    <div id="dhcdrop-stage">
      <div id="dhcdrop-rays"></div>
      <div id="dhcdrop-art"><img id="dhcdrop-img" alt=""><div id="dhcdrop-shine"></div></div>
      <div id="dhcdrop-ring"></div>
      <div id="dhcdrop-shut">?</div>
    </div>
    <div id="dhcdrop-name"></div>
    <div id="dhcdrop-tier"></div>
    <div id="dhcdrop-meta"></div>
    <div id="dhcdrop-actions">
      <button type="button" id="dhcdrop-close">Close</button>
      <a class="go" href="dhcfighters.php">Open assembler</a>
    </div>
  </div>
</div>

<script>
(function () {
  var TIER = {
    common:    {color:'#7a9eb0', glow:0,    build:420,  rare:false},
    uncommon:  {color:'#00c8a0', glow:.18,  build:560,  rare:false},
    epic:      {color:'#8b7bd8', glow:.42,  build:780,  rare:true },
    legendary: {color:'#f5a623', glow:.72,  build:1050, rare:true },
    mythic:    {color:'#ff4f8b', glow:1,    build:1400, rare:true }
  };

  var veil = document.getElementById('dhcdrop-veil');
  var box  = document.getElementById('dhcdrop-box');
  var base = <?php
      // The art folder, resolved the same way the assembler resolves it.
      $b = '';
      foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
          if (is_dir(__DIR__ . '/' . $c . '/1000')) { $b = $c; break; }
      }
      echo json_encode($b);
  ?>;
  var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  var timers = [];

  function clearTimers() { timers.forEach(clearTimeout); timers = []; }
  /*
   * Drops awarded but never shown -- because the player left the game page
   * before the reveal fired. Emitted by whichever page they land on next,
   * since header.php carries this modal everywhere.
   */
  var pending = <?php
      echo json_encode(
        (session_status() === PHP_SESSION_ACTIVE && !empty($_SESSION['dhcf_unseen']))
          ? array_values($_SESSION['dhcf_unseen']) : array()
      );
  ?>;

  function close() { clearTimers(); veil.classList.remove('on'); }
  document.getElementById('dhcdrop-close').addEventListener('click', close);
  veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  /**
   * The sequence. Rarer drops hold the shutter longer before cracking -- the
   * wait IS the tell, so a long pause starts meaning something good is coming
   * before the colour ever appears.
   */
  function show(d) {
    clearTimers();
    var t = TIER[d.tier] || TIER.common;

    veil.className = 'on';                       // clears miss/revealed/rare
    box.classList.remove('revealed', 'cracking', 'rare');
    box.style.setProperty('--dt', t.color);
    box.style.setProperty('--glow', t.glow);
    if (t.rare) box.classList.add('rare');

    document.getElementById('dhcdrop-kicker').textContent = 'Trait acquired';
    document.getElementById('dhcdrop-img').src = base + '/250/' + d.category + '/' + d.slug + '.png';
    document.getElementById('dhcdrop-name').textContent = d.name;
    document.getElementById('dhcdrop-tier').textContent = d.tier;

    // The provenance is the interesting part of a rare drop: not just "mythic"
    // but "no minted Fighter wears this".
    var worn = d.worn > 0
      ? d.worn + ' of the 226 original Fighters wear this'
      : 'No minted Fighter wears this';
    document.getElementById('dhcdrop-meta').innerHTML =
      worn + '<br>' + d.rate + '% drop &middot; ' + d.points + ' pts' +
      (d.band ? ' &middot; ' + d.band : '') +
      (d.is_new ? '<br><span id="dhcdrop-new">New to you</span>'
                : '<br><span id="dhcdrop-dupe">Duplicate &mdash; lets you build a second</span>');

    if (reduce) { box.classList.add('revealed'); return; }

    timers.push(setTimeout(function () { box.classList.add('cracking'); }, t.build));
    timers.push(setTimeout(function () { box.classList.add('revealed'); }, t.build + 90));
  }

  /** Out of drops for today on this game. States when it resets. */
  function showCapped(d) {
    clearTimers();
    veil.className = 'on miss';
    box.classList.remove('revealed', 'cracking', 'rare');
    document.getElementById('dhcdrop-kicker').textContent = 'No trait this run';
    document.getElementById('dhcdrop-name').textContent =
      'Daily limit reached' + (d.game ? ' for ' + d.game : '');
    document.getElementById('dhcdrop-tier').textContent = '';
    document.getElementById('dhcdrop-meta').innerHTML =
      'You have taken all <b>' + (d.cap || '') + '</b> of today\'s traits from this game.' +
      '<br>It resets at midnight — other games still have theirs.';
  }

  /** A near miss: the target, and how close they got. No fanfare. */
  function showMiss(d, opts) {
    clearTimers();
    veil.className = 'on miss';
    box.classList.remove('revealed', 'cracking', 'rare');
    document.getElementById('dhcdrop-kicker').textContent = 'No trait this run';
    document.getElementById('dhcdrop-name').textContent = d.value + ' of ' + d.floor + ' ' + opts.unit;
    document.getElementById('dhcdrop-tier').textContent = '';
    document.getElementById('dhcdrop-meta').innerHTML =
      'Reach <b>' + d.floor + ' ' + opts.unit + '</b> to earn a trait.' +
      '<div id="dhcdrop-bar"><i></i></div>';
    var pct = Math.max(4, Math.min(100, Math.round(d.value / d.floor * 100)));
    requestAnimationFrame(function () {
      var bar = document.querySelector('#dhcdrop-bar i');
      if (bar) bar.style.width = pct + '%';
    });
  }

  /**
   * Claim a drop for a finished run. Safe to call after every run: the server
   * decides whether anything is owed, and a refusal shows nothing.
   *
   * opts.delay lets a game let its own end screen land first -- a trait modal
   * that covers the victory banner steals the moment instead of adding to it.
   */
  /**
   * Show a drop that was ALREADY awarded server-side, rather than claiming one.
   * The seven-day reward streak pays out inside its own claim, so by the time
   * the browser hears about it the ledger row exists and there is nothing left
   * to ask for -- only something to reveal.
   */
  window.DHC_SHOW_DROP = show;
  window.__show = show; window.__miss = showMiss;   // harness hooks

  /* Reveal anything that was waiting, then tell the server it has been seen --
     only then, so a reveal that never happened is still owed. */
  function flushPending() {
    if (!pending.length) return;
    var queue = pending.slice(); pending = [];
    var i = 0;
    (function next() {
      if (i >= queue.length) return;
      show(queue[i++]);
      // Subsequent ones wait for the veil to close, so two drops are two
      // moments rather than one overwriting the other.
      var poll = setInterval(function () {
        if (!veil.classList.contains('on')) { clearInterval(poll); next(); }
      }, 400);
    })();
    fetch('ajax/dhc-seen-drop.php', { method: 'POST', credentials: 'same-origin', keepalive: true })
      .catch(function () {});
  }
  if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', function () { setTimeout(flushPending, 600); });
  else setTimeout(flushPending, 600);

  window.DHC_DROP = function (opts) {
    if (!opts || !opts.game) return;
    var body = 'game=' + encodeURIComponent(opts.game) + '&value=' + encodeURIComponent(opts.value || 0);
    if (opts.placement) body += '&placement=' + encodeURIComponent(opts.placement);
    /*
     * keepalive: the claim must survive the player navigating away.
     *
     * A win screen invites a click, and the drop is decided by this request --
     * not by the modal. Without keepalive, clicking a nav link in the moment
     * between the game ending and this reaching the server cancels it, and the
     * trait is never awarded at all. Same reason cryptcrawl.php uses it for its
     * finalize call. The window is small but it lands on exactly the players
     * who move fastest.
     */
    return fetch('ajax/dhc-claim-drop.php', {
      method: 'POST', credentials: 'same-origin', keepalive: true,
      headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body
    }).then(function (r) { return r.json(); })
      .then(function (d) {
        var wait = opts.delay || 0;
        if (d && d.ok && d.drop) {
          pending = [];   // shown here; nothing owed on the next page
          fetch('ajax/dhc-seen-drop.php', { method: 'POST', credentials: 'same-origin', keepalive: true })
            .catch(function () {});
          setTimeout(function () { show(d.drop); }, wait);
        }
        else if (d && d.why === 'below the threshold' && opts.unit)
          setTimeout(function () { showMiss(d, opts); }, wait);
        // The cap is worth saying out loud. A player who just won and saw
        // nothing assumes it is broken -- which is exactly what the earlier
        // bugs looked like, so silence here is expensive.
        else if (d && d.why === 'daily limit reached')
          setTimeout(function () { showCapped(d); }, wait);
        return d;
      })
      .catch(function () { /* a missed drop must never break a game's end screen */ });
  };
})();
</script>
