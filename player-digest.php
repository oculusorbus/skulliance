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
/*
 * THE OFFER IS NOT THE STATUS LINE.
 *
 * The reason to press Share used to live in #pdg-note, which is also
 * where "Preparing your image..." and "Saved." land -- so the one line
 * explaining what the player gets was overwritten by housekeeping the
 * moment they touched anything. It has its own element, and it is the
 * brightest text under the picture, because it is the only part of this
 * modal that asks for something.
 */
#pdg-offer{font-size:12.5px;color:#cfe7df;line-height:1.65;padding:14px 16px 0;margin:0}
#pdg-offer b{color:#00c8a0}
#pdg-note{font-size:11px;color:#7a9eb0;padding:8px 16px 0;line-height:1.6;margin:0}
/* The controls defend themselves: this sits on every page on the site and
   cannot know what each one does to a button. Same reasoning, and the same
   !important, as dhc-dropmodal.php's actions row. */
#pdg-dm{margin:14px 14px 0;text-align:left;border:1px solid #1b3346;border-radius:3px;
  background:#0d1e2e}
#pdg-dm summary{cursor:pointer;padding:10px 12px;font-size:11px;letter-spacing:.08em;
  text-transform:uppercase;color:#7a9eb0;list-style:none}
#pdg-dm summary::-webkit-details-marker{display:none}
#pdg-dm summary::before{content:"+ ";color:#00c8a0}
#pdg-dm[open] summary::before{content:"\2212 "}
#pdg-dm summary:hover{color:#00c8a0}
.pdg-dm-hint{margin:0;padding:0 12px 8px;font-size:11px;line-height:1.55;color:#7a9eb0}
#pdg-dm textarea{display:block;width:calc(100% - 24px);margin:0 12px;background:#07111d;
  color:#c7d3dc;border:1px solid #1b3346;border-radius:2px;padding:9px;resize:vertical;
  font-family:inherit;font-size:11.5px;line-height:1.55}
#pdg-dm button{margin:10px 12px 12px !important;padding:8px 14px !important;font-size:10px !important;
  letter-spacing:.1em !important;text-transform:uppercase !important;cursor:pointer !important;
  background:transparent !important;border:1px solid #2a4a63 !important;color:#c7d3dc !important;
  border-radius:2px !important;font-family:inherit !important}
#pdg-dm button:hover{border-color:#00c8a0 !important;color:#00c8a0 !important}
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
    <canvas id="pdg-shot" width="1200" height="630"></canvas>
    <?php
    /* STRAIGHT FROM THE CONSTANTS ref_credit() PAYS ON. A 3 typed into the
       copy goes on promising three the day REF_ARRIVAL_DRAWS changes, and
       this is the sentence the whole feature rests on. */
    $pdg_inv = defined('REF_INVITER_DRAWS') ? (int) REF_INVITER_DRAWS : 3;
    $pdg_arr = defined('REF_ARRIVAL_DRAWS') ? (int) REF_ARRIVAL_DRAWS : 3;
    $pdg_dor = defined('REF_DORMANT_DAYS')  ? (int) REF_DORMANT_DAYS  : 30;
    ?>
    <p id="pdg-offer"><b>Posting this is also your invite.</b>
      <?php echo $pdg_inv; ?> DHC Fighter traits for you every time somebody new signs in through
      it, and <?php echo $pdg_arr; ?> for them, on better odds than an ordinary drop. Anyone who
      has not played in <?php echo $pdg_dor; ?> days counts as new.</p>
    <p id="pdg-note"></p>
    <?php /*
     * THE OTHER WAY TO INVITE, folded away.
     *
     * Not everyone wants to post publicly, and a message to somebody you
     * actually know converts far better than a broadcast. This hands over
     * the text and the link and does nothing else: no bot sends it, nobody
     * is assigned to anybody, and nothing is tracked -- the link is the
     * attribution, exactly as it is for the post.
     *
     * COLLAPSED BY DEFAULT because it is the minority path and the modal
     * already has three buttons. <details> rather than script, so it works
     * before any JS has run.
     *
     * "Someone you know" is in the copy on purpose. An unsolicited DM from
     * an account with no history with you is what Discord actions people
     * for, and that risk lands on the player's account, not ours.
     */ ?>
    <details id="pdg-dm">
      <summary>Or send it to a friend on Discord</summary>
      <p class="pdg-dm-hint">Someone you actually know &mdash; a cold message from a stranger is
        what Discord suspends accounts over.</p>
      <textarea id="pdg-dm-text" readonly rows="5"></textarea>
      <button type="button" id="pdg-dm-copy">Copy message</button>
    </details>
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

  /*
   * 1200x630, WHICH IS THE SHAPE X ACTUALLY SHOWS.
   *
   * This was a 1080x1350 portrait poster and it read beautifully as a
   * download. A summary_large_image card crops to about 1.91:1, so what
   * went out on X was a horizontal band through the middle of it: the
   * header, the name and the ENTIRE art collage were cut, which is the one
   * part anybody wanted to look at. Reported from a real post.
   *
   * Landscape is therefore not a style preference, it is the card format.
   * The art takes the larger half, because the art is why a post gets
   * looked at; the numbers are why it gets believed.
   */
  var W = 1200, H = 630;
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

  /*
   * DISMISSED, NOT MERELY SEEN.
   *
   * markSeen() used to run the moment the digest was shown, so anything
   * that took the player off the page before they had finished with it --
   * which on an iPhone was the Download button itself, see saveImage() --
   * burned the digest for the day and it could not be got back. Reported
   * exactly that way: "that took me away from the dialog and now it's
   * gone."
   *
   * The keys are held until Close, which is also what the modal's own note
   * promises: keep it, post it, or close this. Share and Download
   * deliberately do NOT dismiss, so a player can do both.
   */
  var PENDING = [];
  function close() {
    veil.classList.remove('on');
    for (var i = 0; i < PENDING.length; i++) markSeen(PENDING[i]);
    PENDING = [];
  }
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

  /** Trim text to fit a width, with an ellipsis. */
  function fit(c, t, maxW) {
    t = String(t == null ? '' : t);
    if (c.measureText(t).width <= maxW) return t;
    while (t.length > 1 && c.measureText(t + '\u2026').width > maxW) t = t.slice(0, -1);
    return t + '\u2026';
  }

  /*
   * The squarest grid that fits n pictures in the panel, tiles as large as
   * the panel allows. Driven by BOTH dimensions: picking columns off the
   * width alone makes two rows that overflow the bottom the moment there
   * are five pictures rather than four.
   */
  function gridFit(n, RW, RH, gap) {
    var best = null;
    for (var cols = 1; cols <= Math.min(4, n); cols++) {
      var rows = Math.ceil(n / cols);
      var tw = Math.min((RW - gap * (cols - 1)) / cols, (RH - gap * (rows - 1)) / rows);
      /* A GAP IN THE LAST ROW COSTS SOMETHING. Eight pictures fit three
         across at a slightly larger tile than four, but that is 3/3/2 with
         a hole in it where 4/4 is a block. Each empty slot is worth about
         7% of tile size, so a full grid wins unless the ragged one is
         meaningfully bigger -- and five pictures, which cannot be square
         at all, still gets the best shape available. */
      var score = tw * (1 - 0.07 * (cols * rows - n));
      if (!best || score > best.score) best = { cols: cols, rows: rows, tw: tw, score: score };
    }
    return best;
  }

  function draw(d, images) {
    var cv = document.getElementById('pdg-shot');
    var c  = cv.getContext('2d');
    c.fillStyle = INK; c.fillRect(0, 0, W, H);

    var PAD = 44;
    /*
     * NINE, NOT EIGHT. Nine fills a 3x3 exactly, and at the same 173px
     * tile that eight gets -- eight is simply 3/3/2 with a hole in the
     * last row for no gain. The server already fetches up to
     * ACTIVITY_PDIGEST_ART (12) of them, so the ninth picture was being
     * thrown away here rather than never collected. Twelve would tile
     * cleanly too, at 4x3, but the tiles drop to 153px.
     */
    var art = images.art.filter(Boolean).slice(0, 9);

    /* With pictures the column sits left and the art takes the rest. With
       none, the same column is centred rather than left in a field of
       empty ink. */
    var colW = art.length ? 430 : 620;
    var colX = art.length ? PAD : Math.round((W - colW) / 2);

    /* ---- header ---- */
    c.textAlign = 'left';
    c.fillStyle = MUTE; c.font = '500 18px ui-monospace, monospace';
    c.fillText('YOUR DAY ON SKULLIANCE', colX, 66);
    c.fillStyle = TEXT; c.font = '700 28px ui-monospace, monospace';
    c.fillText(fit(c, prettyDay(d.day), colW), colX, 102);

    /* ---- who ---- */
    var ax = colX + 34, ay = 158, ar = 34;
    if (images.avatar) {
      c.save(); c.beginPath(); c.arc(ax, ay, ar, 0, Math.PI * 2); c.closePath(); c.clip();
      c.drawImage(images.avatar, ax - ar, ay - ar, ar * 2, ar * 2); c.restore();
      c.strokeStyle = ACCENT; c.lineWidth = 3;
      c.beginPath(); c.arc(ax, ay, ar, 0, Math.PI * 2); c.stroke();
    }
    c.fillStyle = TEXT; c.font = '700 26px ui-monospace, monospace';
    var nx = images.avatar ? colX + 82 : colX;
    c.fillText(fit(c, d.name || 'A staker', colX + colW - nx), nx, ay + 10);

    /* ---- the headline number ---- */
    c.fillStyle = ACCENT; c.font = '700 88px ui-monospace, monospace';
    c.fillText(String(d.total), colX, 292);
    c.fillStyle = MUTE; c.font = '500 18px ui-monospace, monospace';
    c.fillText(d.total === 1 ? 'THING DONE' : 'THINGS DONE', colX, 322);

    /* ---- the tally. Five rows, because the sixth would land on the
           footer: the height is fixed now and cannot grow to take it. ---- */
    var ty = 368, rows = d.channels.slice(0, 5);
    for (var i = 0; i < rows.length; i++) {
      var y = ty + i * 38;
      c.fillStyle = PANEL; roundRect(c, colX, y - 24, colW, 32, 4); c.fill();
      c.fillStyle = TEXT; c.font = '500 19px ui-monospace, monospace';
      var nTxt = String(rows[i].n);
      c.textAlign = 'right';
      c.fillStyle = ACCENT; c.font = '700 19px ui-monospace, monospace';
      c.fillText(nTxt, colX + colW - 14, y);
      var numW = c.measureText(nTxt).width;
      c.textAlign = 'left';
      c.fillStyle = TEXT; c.font = '500 19px ui-monospace, monospace';
      c.fillText(fit(c, rows[i].label, colW - 36 - numW), colX + 14, y);
    }

    /* ---- streak and footer, both pinned to the bottom of the column ---- */
    if (d.streak > 1) {
      c.fillStyle = MUTE; c.font = '500 18px ui-monospace, monospace';
      c.fillText(d.streak + ' day daily reward streak', colX, 566);
    }
    c.fillStyle = MUTE; c.font = '500 18px ui-monospace, monospace';
    c.fillText('skulliance.io', colX, 598);

    /* ---- their pictures from the day, in the larger half ---- */
    if (art.length) {
      var RX = colX + colW + 36, RY = PAD;
      var RW = W - PAD - RX, RH = H - PAD * 2;
      var gap = 12, g = gridFit(art.length, RW, RH, gap);
      var gh = g.rows * g.tw + gap * (g.rows - 1);
      var y0 = RY + (RH - gh) / 2;
      for (var k = 0; k < art.length; k++) {
        var r = Math.floor(k / g.cols);
        /* A short final row is CENTRED under the ones above it, so five
           pictures read as a deliberate arrangement rather than a grid
           that ran out. */
        var inRow = Math.min(g.cols, art.length - r * g.cols);
        var rowW  = inRow * g.tw + gap * (inRow - 1);
        cover(c, art[k],
              RX + (RW - rowW) / 2 + (k % g.cols) * (g.tw + gap),
              y0 + r * (g.tw + gap),
              g.tw, g.tw);
      }
    }
  }

  function filename(d) { return 'skulliance-' + (d.name || 'day') + '-' + d.day + '.png'; }

  /*
   * The canvas as a Blob, SYNCHRONOUSLY.
   *
   * toBlob() is asynchronous, and on iOS that is fatal here: navigator.share()
   * has to be called while the click's transient activation is still alive,
   * and a promise callback is a later task with no activation left. Safari
   * answers NotAllowedError and the button looks broken. toDataURL() and
   * atob() are both synchronous, so the whole chain stays inside the
   * handler. Throws on a tainted canvas, same as toBlob would.
   */
  function canvasBlob(cv) {
    var url = cv.toDataURL('image/png');
    var bin = atob(url.slice(url.indexOf(',') + 1));
    var u8  = new Uint8Array(bin.length);
    for (var i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
    return new Blob([u8], { type: 'image/png' });
  }

  /*
   * SAVE THE PICTURE.
   *
   * <a download> DOES NOT WORK ON AN iPHONE and never did. iOS Safari
   * ignores the download attribute on a blob: url -- it navigates to the
   * blob instead, which takes the player off the page and away from the
   * modal, and nothing reaches the camera roll. Reported exactly that way:
   * "Safari would say it is downloading but it never showed up", and then
   * the digest was gone because the page had changed.
   *
   * The supported path on iOS is the Web Share API with a file, which opens
   * the native sheet where Save Image puts it in Photos. It is also the
   * better answer on Android. Desktop browsers mostly do not accept files
   * through it, so they keep the anchor, which is where the anchor works.
   */
  function saveImage(d, then) {
    var cv   = document.getElementById('pdg-shot');
    var note = document.getElementById('pdg-note');
    var blob;
    try {
      blob = canvasBlob(cv);
    } catch (e) {
      note.textContent =
        'The image could not be saved: one of yesterday\u2019s pictures came from a host that ' +
        'does not allow it to be read back. Nothing is wrong with your day.';
      if (then) then();
      return;
    }

    /*
     * THE SHARE SHEET IS FOR iOS ONLY, and gating it on canShare() alone
     * was wrong: desktop Chrome and Safari answer yes too, so a button
     * labelled Download image opened a sheet offering to message the
     * picture to somebody's contacts. Everywhere an anchor works, use the
     * anchor -- that is what the button says it does.
     *
     * UA SNIFFING, DELIBERATELY, because the thing that needs detecting
     * cannot be feature-detected: iOS Safari reports 'download' in an
     * anchor and then ignores it. iPadOS reports itself as MacIntel, so
     * the touch-point count is what separates it from a desktop Mac.
     */
    var ua = navigator.userAgent || '';
    var isIOS = /iP(hone|ad|od)/.test(ua)
             || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    var file = null;
    try { file = new File([blob], filename(d), { type: 'image/png' }); } catch (e) { /* older browser */ }
    if (isIOS && file && navigator.canShare && navigator.share && navigator.canShare({ files: [file] })) {
      navigator.share({ files: [file] })
        /* A cancelled sheet is not a failure and must not say so. */
        .then(function () { note.textContent = 'Saved.'; })
        .catch(function () {})
        .then(function () { if (then) then(); });
      return;
    }

    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename(d);
    document.body.appendChild(a); a.click(); a.remove();
    setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
    if (then) then();
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
  /*
   * REPORT CARD FIRST, INVITE SECOND.
   *
   * The invite used to lead, and every player's post then opened with the
   * same byte-identical sentence. X's spam policy covers near-identical
   * content posted across many accounts, and the accounts that get actioned
   * would be the players', not ours. Their own numbers are the one part
   * nobody else can post, so they go first and every post is different from
   * the first character.
   *
   * It also just reads better: a result somebody earned, then an ask --
   * rather than an ad with a postscript.
   *
   * THE ASK STATES WHO QUALIFIES, and that is not fine print. Only new
   * players and ones away a month pay out, so without it every existing
   * follower clicks, gets nothing, and the post reads as bait to exactly
   * the people most likely to say so publicly.
   *
   * THE ODDS ARE NAMED because they are the real difference: these draw
   * from the table a seven-day streak pays, 25% common against an ordinary
   * drop's 55%. "Three traits" alone sounds like three commons.
   *
   * NOTHING IS PAID FOR THE POST. The traits land when somebody signs in,
   * which is a conversion on our own platform -- not a like, a repost or a
   * follow. That line is what keeps this clear of X's rule against paying
   * for engagement, and it is worth not crossing later.
   */
  /*
   * KEPT CLOSE IN LENGTH, deliberately. Everything after the ask is fixed
   * cost -- the claim line, the artist credit, 24 for the url -- so the
   * longest ask sets how much is left for the player's own numbers. The
   * owner's own rewrite of this post came to 362 characters against a 280
   * limit, and almost all of the excess was here.
   */
  var ASKS = [
    'Help me get 3 DHC Fighter traits. Grab 3 if you are new or a month away, better odds.',
    'Grab 3 DHC Fighter traits on better odds if you are new or a month away. I get 3 too.',
    'New here, or away a month? 3 DHC Fighter traits on better odds, and 3 for me.',
    'Fancy 3 DHC Fighter traits on better odds? New here or a month away, and I get 3 too.'
  ];

  function shareText(d) {
    /*
     * Their day, which no one else can post -- and the reason every one of
     * these is a different piece of text.
     *
     * FIRST PERSON, because it is their post. "I did 112 things" is a
     * person talking; "112 things on Skulliance yesterday" is a bot.
     *
     * THE LABEL IS A CHANNEL NAME, NOT A COUNTABLE NOUN. This once read
     * "including 4 skull swap" and "including 1 raids": the labels are
     * Missions, Raids, Monstrocity, Skull Swap, DHC Fighters, so a bare
     * number in front of one is grammatical for about a third of them.
     * "29 of them in Monstrocity" works for every label in the map.
     *
     * The owner would rather it read "29 Monstrocity levels", and it
     * should -- but that needs a unit noun per channel, and the code has
     * no such thing. Inventing one per channel means asserting what each
     * feature's announcements represent, and a wrong guess is a false
     * claim in a public post. The neutral form is true everywhere.
     */
    var things = 'I did ' + d.total + ' thing' + (d.total === 1 ? '' : 's')
               + ' on Skulliance yesterday';
    var card;
    if (!d.best)                  card = things + '.';
    else if (d.best.n >= d.total) card = things + ', all of it in ' + d.best.label + '.';
    else card = things + ', ' + d.best.n + ' of them in ' + d.best.label + '.';

    /*
     * A separate clause, because the trim below DROPS it whole rather than
     * cutting it in half. "148 days running." became "148\u2026".
     *
     * AND IT IS THE DAILY REWARD STREAK, which is what users.streak has
     * always been -- getCurrentDailyRewardStreak() in db.php. The post
     * said "4 days running", which reads as that much activity every day
     * for four days, and then said "login streak", which was simply the
     * wrong feature. It also resets to 0 at seven (db.php's
     * incrementDailyRewardStreak), so it never reads higher than 6.
     */
    var streak = d.streak > 1 ? ' ' + d.streak + ' day daily reward streak.' : '';

    /* Rotated on the sharer and the date, so one person's posts differ over
       time and two people posting on the same day usually differ too --
       near-identical text across many accounts is what X's spam policy
       covers, and it would be the players' accounts actioned. */
    var seed = 0, k = String(d.ref || '') + String(d.day || '');
    for (var i = 0; i < k.length; i++) seed = (seed * 31 + k.charCodeAt(i)) >>> 0;
    var ask = ASKS[seed % ASKS.length];

    /* The claim line names the handle AND says what the link below is,
       which a bare @skulliance did not. */
    var tail = '\n\nClaim your 3 traits from @skulliance below.\n\nTrait art by @MMAXI404';
    /* X counts ANY url as 23 characters and cuts the overflow off the END,
       where the handle and the credit live. The ask is the functional half,
       so the report card is what gives way if something has to. */
    var limit = 280 - 24 - tail.length;

    /*
     * TRIM BY DROPPING A WHOLE CLAUSE, never by cutting one. The ask is the
     * functional half, so everything else gives way first, in order of how
     * little it costs to lose: the streak, then the feature breakdown, then
     * the report card entirely. Each fallback is still a sentence.
     */
    var tries = [card + streak, card, things + '.', ''];
    var body = '';
    for (var t = 0; t < tries.length; t++) {
      body = (tries[t] ? tries[t] + '\n\n' : '') + ask;
      if (body.length <= limit) break;
    }
    /* Only reachable if the ask alone overruns, which no entry in ASKS
       does -- a cut word beats a post that X silently rejects. */
    if (body.length > limit) body = body.slice(0, limit - 1).replace(/\s+\S*$/, '') + '\u2026';
    return body + tail;
  }

  /*
   * THE ONE PLACE THE BARE INVITE URL IS BUILT.
   *
   * It was built twice -- once for the Discord message, once as the share's
   * fallback -- and the FALLBACK FORGOT THE ?r=. So a post that had to fall
   * back, which is every tainted canvas and every failed upload, went out
   * carrying a link that credited nobody: the sharer did the work, somebody
   * signed in through it, and neither of them was paid. Two copies of one
   * url is exactly how that happens, so now there is one.
   */
  function inviteUrl(d) {
    var r = String((d && d.ref) || '');
    return 'https://skulliance.io/staking/' + (r ? '?r=' + encodeURIComponent(r) : '');
  }

  function shareHref(d, url) {
    return 'https://x.com/intent/post?text=' + encodeURIComponent(shareText(d))
         + '&url=' + encodeURIComponent(url || inviteUrl(d));
  }

  /*
   * ONE CLICK, PICTURE INCLUDED.
   *
   * x.com/intent/post cannot attach a file, so the poster is STORED FIRST
   * and the link points at digest.php?d=<token>, a public page whose
   * og:image is that stored file. X fetches it from there.
   *
   * THE TAB IS OPENED ON THE CLICK, NOT AFTER THE UPLOAD. A window.open()
   * from a promise callback is no longer user-initiated and every browser
   * blocks it -- so a blank tab is opened immediately and pointed at X once
   * the poster has landed.
   *
   * AND IT IS OPENED WITHOUT 'noopener', WHICH IS THE WHOLE TRICK WORKING
   * OR NOT. window.open(url, name, 'noopener') RETURNS NULL -- that is what
   * noopener means: the opener is given no handle on the new window. So the
   * handle this design depends on never existed, the blank tab sat on
   * about:blank forever, and the code fell through to navigating the page
   * the player was already on. Reported exactly that way: a blank window
   * that never loaded, and the share dialog only after going back.
   *
   * The security property is kept by severing opener on the handle instead,
   * which is the same protection with a handle left over to point.
   *
   * If the upload fails it still goes to X, with the platform link instead
   * of the poster: a share button that does nothing is worse than a post
   * without a picture.
   */
  function share(d) {
    var note = document.getElementById('pdg-note');
    var tab  = window.open('', '_blank');
    if (tab) { try { tab.opener = null; } catch (e) { /* cross-origin, already safe */ } }
    note.textContent = 'Preparing your image\u2026';

    var done = function (url) {
      var href = shareHref(d, url);
      if (tab && !tab.closed) tab.location = href;
      else window.location.href = href;
      note.textContent = url
        ? 'Posted with your image.'
        : 'Shared. The image could not be uploaded, so the post carries the site card.';
    };

    var cv = document.getElementById('pdg-shot');
    try {
      /* toBlob throws on a tainted canvas; the post still goes out. */
      cv.toBlob(function (b) {
        if (!b) return done(null);
        var fd = new FormData();
        fd.append('poster', b, 'digest.png');
        fetch('ajax/digest-share.php', { method: 'POST', credentials: 'same-origin', body: fd })
          .then(function (r) { return r.json(); })
          .then(function (j) { done(j && j.ok && j.url ? j.url : null); })
          .catch(function () { done(null); });
      }, 'image/png');
    } catch (e) { done(null); }
  }

  document.getElementById('pdg-save').addEventListener('click', function () {
    if (D) saveImage(D);
  });
  document.getElementById('pdg-share').addEventListener('click', function () {
    if (D) share(D);
  });

  /*
   * THE DM TEXT. Plainer than the post: no handles, no credit line, no
   * hashtags -- those read as marketing in a one-to-one message and are the
   * fastest way to make a friend ignore it. The link is the bare invite
   * rather than the digest, because it needs no upload and works the
   * instant the modal opens.
   */
  function dmText(d) {
    var link = inviteUrl(d);
    return 'Been playing this thing called Skulliance - staking, missions, realms and a stack '
         + 'of games, all free in the browser.\n\n'
         + 'If you sign in with this link you get 3 DHC Fighter traits to build a fighter with, and I '
         + 'get 3 as well. Only works if you are new or have not been on in a month.\n\n'
         + link;
  }

  var dmCopy = document.getElementById('pdg-dm-copy');
  if (dmCopy) dmCopy.addEventListener('click', function () {
    var ta = document.getElementById('pdg-dm-text');
    /* The clipboard API needs a secure context and permission; selecting the
       text always works, so that is the fallback rather than a dead button. */
    try {
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(ta.value).then(function () {
          dmCopy.textContent = 'Copied';
          setTimeout(function () { dmCopy.textContent = 'Copy message'; }, 2000);
        });
        return;
      }
    } catch (e) {}
    ta.focus(); ta.select();
    dmCopy.textContent = 'Press Ctrl+C';
  });

  function show(d) {
    D = d;
    var ta = document.getElementById('pdg-dm-text');
    if (ta) ta.value = dmText(d);
    Promise.all([load(d.avatar)].concat(d.art.map(function (a) { return load(a.src); })))
      .then(function (ims) {
        draw(d, { avatar: ims[0], art: ims.slice(1) });
        /*
         * WHY THEY SHOULD DO ANYTHING WITH THIS. The line here used to
         * read "keep it, post it, or close this", which is instructions
         * for dismissing a box, not a reason to act on one. The reason is
         * that the post IS the invite and it pays in traits, both ways.
         */
        /* A cache is a surprise, so it is the first thing the line says. */
        document.getElementById('pdg-note').textContent = (d.cache > 0)
          ? 'A cache fell out of today\u2019s digest: ' + d.cache
            + ' traits, revealed once you close this.'
          : 'It will not ask again today once you close it.';
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
        /* Queued, not written -- see close(). Until it is dismissed the
           digest is still owed to them and comes back on the next page. */
        PENDING = [k, seenKey(j.digest.day, 'me')];
        show(j.digest);
      })
      .catch(function () { /* a missed digest must never break a page */ });
  }
  if (document.readyState === 'loading')
    document.addEventListener('DOMContentLoaded', function () { setTimeout(boot, 1200); });
  else setTimeout(boot, 1200);
})();
</script>
