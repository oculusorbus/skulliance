<?php
/**
 * player-digest.php -- "here is what you did yesterday", once a day.
 *
 * Included by header.php, so it can greet somebody wherever they land. On
 * the first page they open on a new day it fetches the day that just ended
 * and, if they did anything, draws it as a poster they can keep or post.
 *
 * SILENT WHEN THERE IS NOTHING. No activity yesterday means no modal at
 * all: a poster reading "you did nothing" is worse than saying nothing.
 *
 * DRAWN IN THE BROWSER, like admin-digest.php and the flyer builder, and
 * for the same reason -- THERE IS NO TTF IN THIS REPO (see dhc-card.php,
 * which went text-free over it), and a poster whose whole job is naming a
 * player and counting their day cannot be text-free. Canvas has the
 * system fonts; GD does not.
 *
 * TAINTING IS THE RISK AND IT FAILS LATE AND TOTAL. A canvas that has drawn
 * a cross-origin image without CORS throws on toBlob(), so the symptom is
 * not a missing tile, it is the download doing nothing after everything
 * looked right. Two defences, both needed: every src was normalised
 * server-side by activity_digest_src() to a relative path or to Discord's
 * CDN (which answers access-control-allow-origin: *), and crossOrigin is
 * set BEFORE .src, which is the only order that works.
 *
 * Deliberately standalone -- no framework, no platform CSS -- because
 * header.php puts it on every page on the site.
 */
if (defined('SKL_PDIGEST_LOADED')) return;
define('SKL_PDIGEST_LOADED', true);
/* Nothing to show a visitor who is not signed in, and no reason to ship
   them the markup or the fetch. */
if (!isset($_SESSION['userData']['user_id'])) return;
?>
<style>
#pdg-veil{position:fixed;inset:0;z-index:99998;display:none;align-items:center;justify-content:center;
  background:rgba(4,12,22,.9);backdrop-filter:blur(3px);padding:18px}
#pdg-veil.on{display:flex}
#pdg-box{width:min(420px,100%);max-height:94vh;overflow:auto;background:#0a1929;border:1px solid #1b3346;
  border-radius:4px;box-shadow:0 24px 70px rgba(0,0,0,.6);text-align:center;
  font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace;color:#e8eaed;
  animation:pdg-in .3s cubic-bezier(.2,.9,.3,1.2)}
@keyframes pdg-in{from{transform:scale(.92) translateY(12px);opacity:0}to{transform:none;opacity:1}}
#pdg-kicker{font-size:9px;letter-spacing:.24em;text-transform:uppercase;padding:14px 0 0;color:#7a9eb0}
#pdg-shot{display:block;width:100%;height:auto;margin:12px 0 0;background:#06101a}
#pdg-note{font-size:11px;color:#7a9eb0;padding:10px 16px 0;line-height:1.6}
/* The controls defend themselves: this sits on every page on the site and
   cannot know what each one does to a button. Same reasoning, and the same
   !important, as dhc-dropmodal.php's actions row. */
#pdg-actions{display:flex;flex-wrap:wrap;gap:8px;padding:14px;margin:0}
#pdg-actions button{
  flex:1 1 0 !important;display:block !important;width:auto !important;min-width:0 !important;
  margin:0 !important;font-family:ui-monospace,SFMono-Regular,Menlo,Consolas,monospace !important;
  font-size:10px !important;font-weight:400 !important;line-height:1.2 !important;
  letter-spacing:.1em !important;text-transform:uppercase !important;text-align:center !important;
  padding:10px 6px !important;cursor:pointer !important;border-radius:2px !important;
  box-shadow:none !important;background:transparent !important;border:1px solid #2a4a63 !important;
  color:#c7d3dc !important}
#pdg-actions button:hover{border-color:#00c8a0 !important;color:#00c8a0 !important}
#pdg-actions button#pdg-share{
  flex:1 1 100% !important;order:-1;background:#000 !important;border-color:#3a4a56 !important;
  color:#fff !important;font-weight:700 !important}
#pdg-actions button#pdg-share:hover{border-color:#fff !important;color:#fff !important}
@media (prefers-reduced-motion:reduce){#pdg-box{animation:none}}
</style>

<div id="pdg-veil" role="dialog" aria-modal="true" aria-labelledby="pdg-kicker">
  <div id="pdg-box">
    <div id="pdg-kicker">Your day on Skulliance</div>
    <canvas id="pdg-shot" width="1080" height="1350"></canvas>
    <p id="pdg-note"></p>
    <div id="pdg-actions">
      <button type="button" id="pdg-share">Share on X</button>
      <button type="button" id="pdg-save">Download image</button>
      <button type="button" id="pdg-close">Close</button>
    </div>
  </div>
</div>

<script>
(function () {
  'use strict';
  var veil = document.getElementById('pdg-veil');
  if (!veil) return;

  var W = 1080, H = 1350;
  var INK = '#06101a', PANEL = '#0d1e2e', ACCENT = '#00c8a0', TEXT = '#e6f1f7', MUTE = '#8fa6b8';
  var D = null;

  /*
   * SEEN ONCE A DAY, remembered in localStorage rather than in a column.
   *
   * A new users column means a migration somebody has to run by hand, and
   * when that did not happen for monstrocity_theme_collections every write
   * failed silently for weeks. The cost of getting this wrong is seeing a
   * nice poster twice on a second device, which is not worth that risk.
   *
   * Storage throws in a private window and can simply be unavailable, so
   * every access is wrapped and the modal still works without it -- it just
   * shows again on the next page.
   */
  function seenKey(day, who) { return 'skl.pdigest.' + who + '.' + day; }
  function seen(k) { try { return localStorage.getItem(k) === '1'; } catch (e) { return false; } }
  function markSeen(k) { try { localStorage.setItem(k, '1'); } catch (e) {} }

  function close() { veil.classList.remove('on'); }
  document.getElementById('pdg-close').addEventListener('click', close);
  veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
  document.addEventListener('keydown', function (e) { if (e.key === 'Escape') close(); });

  /* crossOrigin BEFORE src -- after is silently ignored -- and a failed
     load resolves null so one dead url costs one tile, not the download. */
  function load(src) {
    return new Promise(function (res) {
      if (!src) return res(null);
      var im = new Image();
      im.crossOrigin = 'anonymous';
      im.onload  = function () { res(im.naturalWidth ? im : null); };
      im.onerror = function () { res(null); };
      im.src = src;
    });
  }
  function roundRect(c, x, y, w, h, r) {
    c.beginPath();
    c.moveTo(x + r, y); c.arcTo(x + w, y, x + w, y + h, r);
    c.arcTo(x + w, y + h, x, y + h, r); c.arcTo(x, y + h, x, y, r);
    c.arcTo(x, y, x + w, y, r); c.closePath();
  }
  function cover(c, im, x, y, w, h) {
    var s = Math.max(w / im.naturalWidth, h / im.naturalHeight);
    var dw = im.naturalWidth * s, dh = im.naturalHeight * s;
    c.save(); roundRect(c, x, y, w, h, 8); c.clip();
    c.drawImage(im, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
    c.restore();
  }
  function prettyDay(iso) {
    var p = String(iso).split('-');
    if (p.length !== 3) return String(iso);
    var M = ['January','February','March','April','May','June','July','August','September',
             'October','November','December'];
    return parseInt(p[2], 10) + ' ' + M[parseInt(p[1], 10) - 1] + ' ' + p[0];
  }

  function draw(d, images) {
    var cv = document.getElementById('pdg-shot');
    var c  = cv.getContext('2d');
    c.fillStyle = INK; c.fillRect(0, 0, W, H);

    /* ---- header ---- */
    c.textAlign = 'center';
    c.fillStyle = MUTE; c.font = '500 26px ui-monospace, monospace';
    c.fillText('YOUR DAY ON SKULLIANCE', W / 2, 74);
    c.fillStyle = TEXT; c.font = '700 44px ui-monospace, monospace';
    c.fillText(prettyDay(d.day), W / 2, 130);

    /* ---- who ---- */
    var cy = 236;
    if (images.avatar) {
      c.save(); c.beginPath(); c.arc(W / 2, cy, 64, 0, Math.PI * 2); c.closePath(); c.clip();
      c.drawImage(images.avatar, W / 2 - 64, cy - 64, 128, 128); c.restore();
      c.strokeStyle = ACCENT; c.lineWidth = 3;
      c.beginPath(); c.arc(W / 2, cy, 64, 0, Math.PI * 2); c.stroke();
    }
    c.fillStyle = TEXT; c.font = '700 40px ui-monospace, monospace';
    c.fillText(d.name || 'A staker', W / 2, cy + 112);

    /* ---- the headline number ---- */
    c.fillStyle = ACCENT; c.font = '700 140px ui-monospace, monospace';
    c.fillText(String(d.total), W / 2, cy + 268);
    c.fillStyle = MUTE; c.font = '500 26px ui-monospace, monospace';
    c.fillText(d.total === 1 ? 'THING DONE' : 'THINGS DONE', W / 2, cy + 308);

    /* ---- the tally ---- */
    var ty = cy + 372, rows = d.channels.slice(0, 6);
    c.textAlign = 'left';
    for (var i = 0; i < rows.length; i++) {
      var y = ty + i * 46;
      c.fillStyle = PANEL; roundRect(c, 110, y - 28, W - 220, 38, 4); c.fill();
      c.fillStyle = TEXT; c.font = '500 24px ui-monospace, monospace';
      c.fillText(rows[i].label, 128, y);
      c.textAlign = 'right';
      c.fillStyle = ACCENT; c.font = '700 24px ui-monospace, monospace';
      c.fillText(String(rows[i].n), W - 128, y);
      c.textAlign = 'left';
    }

    /* ---- their pictures from the day ---- */
    var gy = ty + rows.length * 46 + 26;
    var art = images.art.filter(Boolean).slice(0, 8);
    if (art.length) {
      var cols = art.length <= 4 ? art.length : 4;
      var rowsN = Math.ceil(art.length / cols);
      var gap = 14, pad = 110;
      var tw = (W - pad * 2 - gap * (cols - 1)) / cols;
      for (var k = 0; k < art.length; k++) {
        var cx2 = pad + (k % cols) * (tw + gap);
        var cy2 = gy + Math.floor(k / cols) * (tw + gap);
        cover(c, art[k], cx2, cy2, tw, tw);
      }
      gy += rowsN * (tw + gap);
    }

    /* ---- streak, only when it is worth saying ---- */
    c.textAlign = 'center';
    if (d.streak > 1) {
      c.fillStyle = MUTE; c.font = '500 26px ui-monospace, monospace';
      c.fillText(d.streak + ' day login streak', W / 2, Math.min(gy + 36, H - 120));
    }

    /* ---- footer ---- */
    c.fillStyle = MUTE; c.font = '500 24px ui-monospace, monospace';
    c.fillText('skulliance.io', W / 2, H - 56);
  }

  function filename(d) { return 'skulliance-' + (d.name || 'day') + '-' + d.day + '.png'; }

  function saveImage(d, then) {
    var cv = document.getElementById('pdg-shot');
    /* toBlob throws on a tainted canvas. Everything above is built to stop
       that, but if it ever happens the player needs to know why rather than
       watch a button do nothing. */
    try {
      cv.toBlob(function (b) {
        if (!b) { if (then) then(); return; }
        var a = document.createElement('a');
        a.href = URL.createObjectURL(b);
        a.download = filename(d);
        document.body.appendChild(a); a.click(); a.remove();
        setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
        if (then) then();
      }, 'image/png');
    } catch (e) {
      document.getElementById('pdg-note').textContent =
        'The image could not be saved: one of yesterday’s pictures came from a host that ' +
        'does not allow it to be read back. Nothing is wrong with your day.';
      if (then) then();
    }
  }

  /*
   * THE POST. x.com/intent/post cannot attach a file, so the image is saved
   * first and the player attaches it -- which is exactly what they were
   * doing by hand with screenshots before this existed. The text stands on
   * its own if they do not.
   *
   * It leads with what they DID, because a number nobody can place is not
   * an accolade, and it ends by inviting the reader rather than only naming
   * the platform.
   */
  function shareText(d) {
    var best = d.best ? (d.best.n + ' ' + String(d.best.label).toLowerCase()) : '';
    var body = 'Yesterday on Skulliance I got through ' + d.total + ' thing'
             + (d.total === 1 ? '' : 's') + (best ? ' - ' + best + ' among them' : '') + '.';
    if (d.streak > 1) body += ' ' + d.streak + ' days in a row now.';
    var tail = '\n\nStake your NFTs, run missions, hold a realm and play a dozen games free at '
             + '@skulliance.\n\nArt by @MMAXI404';
    /* X counts ANY url as 23 characters and cuts the overflow off the END,
       which is where the invitation and the credit live. */
    var limit = 280 - 24 - tail.length;
    if (body.length > limit) body = body.slice(0, limit - 1).replace(/\s+\S*$/, '') + '…';
    return body + tail;
  }
  function shareHref(d) {
    return 'https://x.com/intent/post?text=' + encodeURIComponent(shareText(d))
         + '&url=' + encodeURIComponent('https://skulliance.io/staking/');
  }

  document.getElementById('pdg-save').addEventListener('click', function () {
    if (D) saveImage(D);
  });
  document.getElementById('pdg-share').addEventListener('click', function () {
    if (!D) return;
    var w = window.open(shareHref(D), '_blank', 'noopener');
    saveImage(D, function () {
      document.getElementById('pdg-note').textContent =
        'Image saved — attach it to the post. X cannot pick it up on its own.';
    });
    if (!w) window.location.href = shareHref(D);
  });

  function show(d) {
    D = d;
    Promise.all([load(d.avatar)].concat(d.art.map(function (a) { return load(a.src); })))
      .then(function (ims) {
        draw(d, { avatar: ims[0], art: ims.slice(1) });
        document.getElementById('pdg-note').textContent =
          'Keep it, post it, or close this — it will not ask again today.';
        veil.classList.add('on');
      });
  }

  /*
   * ONE FETCH, AND ONLY WHEN THERE COULD BE SOMETHING TO SEE. The key is
   * checked before asking the server, so a player who has already closed
   * today's digest costs nothing on every subsequent page.
   *
   * The day comes from the SERVER, so the check has to happen after the
   * fetch as well -- the key before it is a cheap guard keyed on the
   * browser's own date, which is close enough to skip the common case.
   */
  function boot() {
    var localDay = new Date(Date.now() - 86400000).toISOString().slice(0, 10);
    if (seen(seenKey(localDay, 'me'))) return;
    fetch('ajax/player-digest.php', { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (j) {
        if (!j || !j.show || !j.digest) {
          if (j && j.day) markSeen(seenKey(j.day, 'me'));
          return;
        }
        var k = seenKey(j.digest.day, j.digest.name || 'me');
        if (seen(k)) return;
        markSeen(k);
        markSeen(seenKey(j.digest.day, 'me'));
        show(j.digest);
      })
      .catch(function () { /* a missed digest must never break a page */ });
  }
  if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', function () { setTimeout(boot, 1200); });
  else setTimeout(boot, 1200);
})();
</script>
