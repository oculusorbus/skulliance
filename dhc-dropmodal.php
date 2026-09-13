<?php
/**
 * DHC TRAIT DROP MODAL -- shared across every game.
 *
 * Include once, near the end of a game page. It defines window.DHC_DROP(),
 * which a game calls from its win/loss/defeat screen:
 *
 *     DHC_DROP({ game: 'guardians', value: wavesHeld, placement: rank });
 *
 * It claims the drop server-side and, if something was awarded, shows it. If
 * nothing was awarded -- below the floor, daily cap, not signed in -- it stays
 * silent rather than announcing a non-event, so a game can call it after every
 * run without checking the rules itself. The rules live in one place and it is
 * not here.
 *
 * Deliberately standalone: no framework, no platform CSS dependency, scoped
 * class names. It has to sit on top of nine different game interfaces without
 * any of them having to accommodate it.
 */
?>
<style>
#dhcdrop-veil{position:fixed;inset:0;z-index:99999;display:none;align-items:center;justify-content:center;
  background:rgba(8,6,6,.82);backdrop-filter:blur(3px);padding:20px}
#dhcdrop-veil.on{display:flex}
#dhcdrop-box{width:min(340px,100%);background:#16110f;border:1px solid #3a2e29;border-radius:4px;
  box-shadow:0 24px 70px rgba(0,0,0,.7);overflow:hidden;text-align:center;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#e8e2d8;
  animation:dhcdrop-in .32s cubic-bezier(.2,.9,.3,1.2)}
@keyframes dhcdrop-in{from{transform:scale(.86) translateY(14px);opacity:0}to{transform:none;opacity:1}}
@media (prefers-reduced-motion:reduce){#dhcdrop-box{animation:none}}
#dhcdrop-kicker{font-size:9px;letter-spacing:.24em;text-transform:uppercase;padding:12px 0 0;color:#8b8178}
#dhcdrop-art{width:190px;height:190px;margin:8px auto 0;position:relative;
  background:repeating-conic-gradient(#1b1616 0% 25%,#221b1b 0% 50%) 50%/14px 14px;border-radius:3px}
#dhcdrop-art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
#dhcdrop-name{font-size:15px;padding:12px 14px 2px;line-height:1.3}
#dhcdrop-tier{font-size:10px;letter-spacing:.2em;text-transform:uppercase;padding:2px 0 0;color:var(--dt,#8b8178)}
#dhcdrop-meta{font-size:10.5px;color:#8b8178;padding:6px 14px 0;line-height:1.6}
#dhcdrop-new{display:inline-block;margin:8px 0 0;font-size:9px;letter-spacing:.16em;text-transform:uppercase;
  border:1px solid #4f9d84;color:#4f9d84;border-radius:999px;padding:3px 9px}
#dhcdrop-dupe{display:inline-block;margin:8px 0 0;font-size:9px;letter-spacing:.16em;text-transform:uppercase;
  border:1px solid #6a5f57;color:#8b8178;border-radius:999px;padding:3px 9px}
#dhcdrop-veil.miss #dhcdrop-art,#dhcdrop-veil.miss #dhcdrop-new,#dhcdrop-veil.miss #dhcdrop-dupe{display:none}
#dhcdrop-bar{height:5px;background:#241d1b;border-radius:3px;margin:12px 22px 0;overflow:hidden}
#dhcdrop-bar i{display:block;height:100%;background:#c8913c}
#dhcdrop-actions{display:flex;gap:8px;padding:14px}
#dhcdrop-actions a,#dhcdrop-actions button{flex:1;font:inherit;font-size:10px;letter-spacing:.1em;
  text-transform:uppercase;padding:9px 0;cursor:pointer;border-radius:2px;text-decoration:none;
  border:1px solid #3a2e29;background:none;color:#e8e2d8;text-align:center}
#dhcdrop-actions .go{background:#8f2f27;border-color:#b8433a}
#dhcdrop-actions a:hover,#dhcdrop-actions button:hover{border-color:#c8913c;color:#c8913c}
#dhcdrop-actions .go:hover{background:#a8372d;color:#f2e9df;border-color:#b8433a}
</style>

<div id="dhcdrop-veil" role="dialog" aria-modal="true" aria-labelledby="dhcdrop-name">
  <div id="dhcdrop-box">
    <div id="dhcdrop-kicker">Trait acquired</div>
    <div id="dhcdrop-art"><img id="dhcdrop-img" alt=""></div>
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
  var TIER_COLOR = {
    common:'#8b8178', uncommon:'#4f9d84', epic:'#7d6bb0', legendary:'#c8913c', mythic:'#c2445c'
  };
  var veil = document.getElementById('dhcdrop-veil');
  var base = <?php
      // The art folder, resolved the same way the assembler resolves it.
      $b = '';
      foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
          if (is_dir(__DIR__ . '/' . $c . '/1000')) { $b = $c; break; }
      }
      echo json_encode($b);
  ?>;

  function close() { veil.classList.remove('on'); }
  document.getElementById('dhcdrop-close').addEventListener('click', close);
  veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  function show(d) {
    veil.classList.remove('miss');
    document.getElementById('dhcdrop-kicker').textContent = 'Trait acquired';
    document.getElementById('dhcdrop-img').src = base + '/250/' + d.category + '/' + d.slug + '.png';
    document.getElementById('dhcdrop-name').textContent = d.name;
    var t = document.getElementById('dhcdrop-tier');
    t.textContent = d.tier;
    t.style.setProperty('--dt', TIER_COLOR[d.tier] || '#8b8178');
    // The on-chain provenance is the interesting part of a rare drop: not just
    // "mythic" but "no minted Fighter wears this".
    var worn = d.worn > 0
      ? d.worn + ' of the 226 original Fighters wear this'
      : 'No minted Fighter wears this';
    document.getElementById('dhcdrop-meta').innerHTML =
      worn + '<br>' + d.rate + '% drop &middot; ' + d.points + ' pts' +
      (d.band ? ' &middot; ' + d.band : '') +
      (d.is_new ? '<br><span id="dhcdrop-new">New to you</span>'
                : '<br><span id="dhcdrop-dupe">Duplicate &mdash; lets you build a second</span>');
    veil.classList.add('on');
  }

  function showMiss(d, opts) {
    veil.classList.add('miss');
    document.getElementById('dhcdrop-kicker').textContent = 'No trait this run';
    document.getElementById('dhcdrop-name').textContent =
      d.value + ' of ' + d.floor + ' ' + opts.unit;
    document.getElementById('dhcdrop-tier').textContent = '';
    var pct = Math.max(4, Math.min(100, Math.round(d.value / d.floor * 100)));
    document.getElementById('dhcdrop-meta').innerHTML =
      'Reach <b>' + d.floor + ' ' + opts.unit + '</b> to earn a trait.' +
      '<div id="dhcdrop-bar"><i style="width:' + pct + '%"></i></div>';
    veil.classList.add('on');
  }

  /**
   * Claim a drop for a finished run. Safe to call after every run: the server
   * decides whether anything is owed, and a refusal shows nothing.
   */
  window.DHC_DROP = function (opts) {
    if (!opts || !opts.game) return;
    var body = 'game=' + encodeURIComponent(opts.game) + '&value=' + encodeURIComponent(opts.value || 0);
    if (opts.placement) body += '&placement=' + encodeURIComponent(opts.placement);
    return fetch('ajax/dhc-claim-drop.php', {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body
    }).then(function (r) { return r.json(); })
      .then(function (d) {
        if (d && d.ok && d.drop) { show(d.drop); return d; }
        // A near miss is worth saying out loud: after a long run, silence reads
        // as a bug, and the threshold is a target for the next attempt.
        if (d && d.why === 'below the threshold' && opts.unit) showMiss(d, opts);
        return d;
      })
      .catch(function () { /* a missed drop must never break a game's end screen */ });
  };
})();
</script>
