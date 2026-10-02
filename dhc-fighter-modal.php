<?php
/**
 * dhc-fighter-modal.php -- one Fighter, full size, with its ranks and the
 * two things you can do with it.
 *
 * ONE COPY, because there are now two callers. It was written for the
 * Collection, where you click a card; dhcfighters.php opens the same panel
 * the moment you save, so the Fighter you just built can be looked at,
 * downloaded and posted without going and finding it. The platform has
 * shipped a redesigned-but-invisible panel three times by letting a second
 * copy of some markup exist (see realms-attack.php), so this is a partial
 * the first time rather than the third.
 *
 * The host sets these before including it:
 *
 *   $dhcm_user   int   the viewer's user id, 0 for a guest. Decides whether
 *                      Download and Share are offered at all -- both are
 *                      owner-only, and dhc-download.php checks again anyway.
 *   $dhcm_base   string  the trait art directory, from dhcf_art_dir().
 *
 * and then drives it with window.DHC_MODAL.open(fighter) / .close().
 * window.DHC_MODAL.onClose can be set to a function; the Collection leaves
 * it alone, the assembler uses it to reload so the new Fighter appears in
 * the roster.
 *
 * The `fighter` object is the same shape dhcgallery.php puts in each card's
 * data-f, and ajax/dhc-fighter.php returns exactly that for one serial.
 */
if (!defined('DHCM_RENDERED')) {
	define('DHCM_RENDERED', 1);
	$dhcm_user = isset($dhcm_user) ? (int)$dhcm_user : 0;
	$dhcm_base = isset($dhcm_base) ? $dhcm_base : (function_exists('dhcf_art_dir') ? dhcf_art_dir() : '');
	/* Only the Collection owns the ?fighter= URL. On the assembler the same
	   panel is a review of what you just saved, and rewriting the address to
	   a different page would be a lie about where you are -- and would send
	   a refresh somewhere else. */
	$dhcm_deeplink = !empty($dhcm_deeplink);
?>
<style>
#dhcg-veil{position:fixed;inset:0;z-index:9998;display:none;align-items:center;justify-content:center;
  background:rgba(4,12,22,.86);
  padding:calc(env(safe-area-inset-top,0px) + 20px) 20px calc(env(safe-area-inset-bottom,0px) + 20px)}
#dhcg-veil.on{display:flex}
/* Wider than the metadata needs, because the art is the point: the grid shows
   Fighters at 250px and this is the only place one is seen large. The art
   column takes the larger share and is capped to the panel height so a short
   window scrolls the trait list rather than the character. */
/* 100% not 90vh: vh ignores the veil's padding, so the panel could still
   grow back up into the inset the padding just cleared. */
#dhcg-panel{width:min(1180px,100%);max-height:100%;overflow:auto;background:var(--panel,#0a1929);
  border:1px solid var(--line,#1b3346);border-radius:4px;display:grid;
  grid-template-columns:minmax(0,1.35fr) minmax(0,1fr)}
@media (max-width:760px){#dhcg-panel{grid-template-columns:1fr}}
#dhcg-panel .big{position:relative;aspect-ratio:1;max-height:100%;background:var(--panel2,#0d1e2e)}
#dhcg-panel .big img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
/* The close control. LEFT BEHIND when this panel was pulled out of
   dhcgallery.php: the extraction started at the #dhcg-veil rule and this one
   sat above it, so the Collection kept its styling and the assembler got a
   bare browser <button> -- white, rounded, system font, in the middle of a
   dark panel. Nothing warns about a rule that only one of two callers has. */
#dhcg-close{position:absolute;right:14px;top:12px;background:none;border:1px solid var(--line,#1b3346);
  color:var(--dim,#7a9eb0);font:inherit;font-size:10px;letter-spacing:.1em;text-transform:uppercase;
  padding:5px 10px;border-radius:2px;cursor:pointer}
#dhcg-close:hover{border-color:var(--ochre,#00c8a0);color:var(--ochre,#00c8a0)}
#dhcg-info{padding:18px 20px}
#dhcg-info h2{margin:0 0 2px;font-size:20px}
#dhcg-info .by{font-size:11px;opacity:.65;display:flex;align-items:center;gap:6px;margin:0 0 14px}
#dhcg-info .by img{width:20px;height:20px;border-radius:50%}
#dhcg-stat{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
#dhcg-stat div{border:1px solid var(--line,#1b3346);border-radius:3px;padding:6px 10px}
#dhcg-stat b{display:block;font-size:15px;font-variant-numeric:tabular-nums}
#dhcg-stat span{font-size:9px;letter-spacing:.12em;text-transform:uppercase;opacity:.55}
#dhcg-traits{list-style:none;margin:0;padding:0;font-size:11.5px}
#dhcg-traits li{display:grid;grid-template-columns:1fr auto auto;gap:10px;align-items:baseline;
  padding:6px 0;border-top:1px solid var(--line,#1b3346)}
#dhcg-traits .sl{font-size:9px;letter-spacing:.1em;text-transform:uppercase;opacity:.45;display:block}
#dhcg-traits .tr{font-size:9px;letter-spacing:.1em;text-transform:uppercase}
#dhcg-traits .rt{font-size:10px;opacity:.6;font-variant-numeric:tabular-nums}
/* Only ever shown on your own Fighters -- see the note above open(). */
#dhcg-get{display:none;align-items:center;gap:7px;margin:0 0 14px;text-decoration:none;
  border:1px solid var(--ochre,#00c8a0);color:var(--ochre,#00c8a0);font-size:10px;
  letter-spacing:.1em;text-transform:uppercase;padding:7px 12px;border-radius:2px}
#dhcg-get.on{display:inline-flex}
#dhcg-get:hover{background:var(--ochre,#00c8a0);color:var(--ink,#07111d)}
#dhcg-get small{letter-spacing:0;text-transform:none;opacity:.7;font-size:10px}
.dhcg-acts{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
.dhcg-acts #dhcg-get{margin:0}
/* Same shape as the download beside it -- they are the two things you do
   with a Fighter you own, and one looking like a button while the other
   looks like a link would imply one of them is the real one. */
#dhcg-share{display:none;align-items:center;gap:7px;text-decoration:none;
  font-size:11px;letter-spacing:.08em;text-transform:uppercase;
  border:1px solid var(--ochre,#00c8a0);color:var(--ochre,#00c8a0);padding:7px 12px}
#dhcg-share.on{display:inline-flex}
#dhcg-share:hover{background:var(--ochre,#00c8a0);color:var(--ink,#07111d)}
/* The four ranks, ahead of the raw stats: where it places is the headline,
   what it is made of is the detail. */
#dhcg-rank{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px;align-items:flex-end}
#dhcg-rank div{border:1px solid var(--line,#1b3346);padding:6px 10px;min-width:74px}
#dhcg-rank b{display:block;font-size:16px;font-variant-numeric:tabular-nums;color:var(--ochre,#00c8a0)}
#dhcg-rank span{font-size:8.5px;letter-spacing:.1em;text-transform:uppercase;opacity:.55}
#dhcg-rank .of{border:0;padding:0 0 6px;font-size:9.5px;letter-spacing:.08em;
  text-transform:uppercase;opacity:.45;min-width:0}</style>

<div id="dhcg-veil" role="dialog" aria-modal="true" aria-labelledby="dhcg-name">
  <div id="dhcg-panel">
    <div class="big" id="dhcg-big"></div>
    <div id="dhcg-info">
      <button type="button" id="dhcg-close">Close</button>
      <h2 id="dhcg-name"></h2>
      <p class="by" id="dhcg-by"></p>
      <div class="dhcg-acts">
        <a id="dhcg-get" href="#" download>&#8595; Full size <small>1000px PNG</small></a>
        <?php /* target=_blank: the composer must not replace the Collection
                 the player is still browsing. */ ?>
        <a id="dhcg-share" href="#" target="_blank" rel="noopener">&#120143; Share</a>
      </div>
      <div id="dhcg-rank"></div>
      <div id="dhcg-stat"></div>
      <ul id="dhcg-traits"></ul>
    </div>
  </div>
</div>

<script>
(function () {
  var veil = document.getElementById('dhcg-veil');
  var big  = document.getElementById('dhcg-big');
  var BASE = <?php echo json_encode($dhcm_base); ?>;
  var ARM  = <?php echo (int)DHCF_ONE_ARM_SPLIT; ?>;
  /* Whether this page's address should name the open Fighter. */
  var DEEPLINK = <?php echo $dhcm_deeplink ? 'true' : 'false'; ?>;

  function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

  var ME = <?php echo (int)$dhcm_user; ?>;

  /* The four axes, in the order dhcf_rank_axes() defines them, so the strip
     and the share sentence cannot disagree with the assembler's preview. */
  var AXES = <?php
    $ax = array();
    foreach (dhcf_rank_axes() as $k => $l) $ax[] = array($k, $l);
    echo json_encode($ax);
  ?>;

  /*
   * THE SHARE SENTENCE.
   *
   * Written here rather than through db.php's shareOnXButton() because this
   * one is built in the browser from whichever Fighter is open, and because
   * the interesting claim is the RANK, not the score -- "#4 deadliest of 108"
   * says something a stranger can weigh, where "6,846" says nothing at all.
   * Only the two best placements are named: four of them reads as a stat
   * dump, and X truncates anyway.
   *
   * X counts every URL as 23 characters whatever its length, which is what
   * the budget below accounts for -- the same arithmetic shareOnXUrl() does
   * server-side.
   */
  /*
   * THE TAIL IS A CALL TO ACTION, not just a tag. A bare @skulliance tells
   * a stranger scrolling past who made the thing and nothing about why they
   * should care; the point of the post is the next player, not the credit.
   * THE ARTIST IS CREDITED IN EVERY POST, in its own paragraph under the
   * invitation -- a blank line between them, so the credit reads as a
   * separate statement rather than a second sentence of the pitch. None of this exists without Maxingo's art, and a Fighter
   * going out into the world uncredited is the wrong default -- so it is
   * part of the tail rather than something a player has to remember. It
   * sits after the invitation rather than in front of it because the post
   * is an invitation first; the credit is a fact it carries, not its
   * opening line.
   *
   * The whole tail costs ~104 characters, which is why the stat sentence
   * below is conditional: the credit and the invitation both matter more
   * than the health number, so the stats are what drops if there is ever
   * not room.
   */
  var X_HANDLE = 'Join @skulliance to assemble your own Fighter and battle other players in the Arena!\n\nArt by @MMAXI404';

  function shareText(f) {
    var best = AXES.map(function (a) { return { label: a[1], rank: f.rank[a[0]] }; })
                   .sort(function (x, y) { return x.rank - y.rank; })
                   .slice(0, 2)
                   .map(function (r) { return '#' + r.rank.toLocaleString() + ' ' + r.label.toLowerCase(); });

    var body = f.name + ' - ' + best.join(', ') + ' of ' +
               f.rankOf.toLocaleString() + ' DHC Fighters.';
    /* What it is made of, only if it fits. */
    var tail = ' ' + f.pow + ' power, ' + f.hp + ' health, assembled from ' +
               f.parts.length + ' earned traits.';
    var limit = 280 - 24 - ('\n\n' + X_HANDLE).length;
    if ((body + tail).length <= limit) body += tail;
    if (body.length > limit) body = body.slice(0, limit - 1).replace(/\s+\S*$/, '') + '…';
    return body + '\n\n' + X_HANDLE;
  }

  function shareHref(f) {
    /* The link names the Fighter so the post lands on it, not on the front
       of the Collection. */
    var url = 'https://skulliance.io/staking/dhcgallery.php?fighter=' + f.serial;
    return 'https://x.com/intent/post?text=' + encodeURIComponent(shareText(f)) +
           '&url=' + encodeURIComponent(url);
  }
  function open(f) {
    // The same layer list the card used, so this is the card at a larger size
    // rather than a second opinion about draw order -- but pointed at the
    // 1000px masters. This is the only place a Fighter is shown big enough for
    // the detail in Maxingo's art to be worth the bytes.
    big.innerHTML = f.layers.map(function (l) {
      var st = (l.n ? 'transform:translateY(' + l.n + '%);' : '') +
               (l.k === 'right' ? 'clip-path:inset(0 0 0 ' + ARM + '%)'
                : l.k === 'left' ? 'clip-path:inset(0 ' + (100 - ARM) + '% 0 0)' : '');
      return '<img alt="" src="' + esc(BASE + '/1000/' + l.c + '/' + l.s + '.png') + '"' +
             (st ? ' style="' + st + '"' : '') + '>';
    }).join('');

    document.getElementById('dhcg-name').textContent = f.name;
    document.getElementById('dhcg-by').innerHTML =
      '<img alt="" src="' + esc(f.avatar) + '"> assembled by ' + esc(f.owner) +
      ' · <a href="dhcgallery.php?owner=' + f.ownerId + '" style="color:var(--ochre,#00c8a0)">'
      /* The count makes the link an actual invitation -- "see their 27
         Fighters" is worth a click in a way "see their Fighters" is not.
         Falls back to the bare wording if the caller did not send one,
         rather than rendering "see their 0 Fighters". */
      + (f.ownerN > 0
          ? 'see their ' + f.ownerN.toLocaleString() + ' Fighter' + (f.ownerN === 1 ? '' : 's')
          : 'see their Fighters')
      + '</a>';

    var get = document.getElementById('dhcg-get');
    var mine = ME > 0 && f.ownerId === ME;
    get.classList.toggle('on', mine);
    get.href = mine ? 'dhc-download.php?serial=' + f.serial : '#';

    /* SHARE IS OWNER-ONLY, the same rule the download follows and for the
       same reason: posting somebody else's assembly as the thing you built
       is not a share, and the composer would prefill it in their voice. */
    var share = document.getElementById('dhcg-share');
    share.classList.toggle('on', mine);
    share.href = mine ? shareHref(f) : '#';

    /*
     * WARM THE CARD NOW, not when X asks for it.
     *
     * dhc-card.php renders on demand the first time: a 1000px composite of
     * every layer, then the 1200x630 card, then a disk write. That is
     * seconds. X's crawler fetches the image once, shortly after the
     * composer opens, and if it does not get an answer quickly it shows no
     * card at all -- and then caches that nothing for the URL, so the next
     * person to open the same link sees no card either.
     *
     * Opening your own Fighter is the earliest moment we know which card
     * might be wanted, and it is a long way before the Share click. One
     * cached JPEG, fetched by the browser and then thrown away: by the time
     * the crawler arrives the file is on disk and the request is a readfile.
     */
    if (mine && f.serial) {
      var warm = new Image();
      warm.src = 'dhc-card.php?serial=' + f.serial;
    }

    /* The URL names the Fighter, so a share lands on it rather than on the
       front of the collection. replaceState, not pushState: opening a card
       should not add a history entry that Back then has to walk through. */
    if (DEEPLINK && f.serial) {
      try { history.replaceState(null, '', 'dhcgallery.php?fighter=' + f.serial); } catch (e) {}
    }

    var made = (f.created || '').replace(' ', ' · ').slice(0, 16);
    /* Rarity score first because it is what the board ranks on, then what the
       Arena will actually get: health from the torso, power from the weapon,
       and the fighting style the weapon decides. The two answer different
       questions and a collection should not imply they are the same one. */
    document.getElementById('dhcg-stat').innerHTML =
      '<div><b>' + f.score.toLocaleString() + '</b><span>Rarity score</span></div>' +
      '<div><b>' + f.hp + '</b><span>Health</span></div>' +
      '<div><b>' + f.pow + '</b><span>Power</span></div>' +
      '<div><b>' + f.crit + '%</b><span>Crit chance</span></div>' +
      (f.resist > 0 ? '<div><b>' + f.resist + '%</b><span>Damage resisted</span></div>' : '') +
      (f.assist > 0 ? '<div><b>' + f.assist + '%</b><span>Companion assist</span></div>' : '') +
      (f.charge > 1 ? '<div><b>&times;' + f.charge + '</b><span>Charge rate</span></div>' : '') +
      '<div><b style="font-size:11px">' + esc(f.kit.split(' — ')[0]) + '</b><span>In the Arena</span></div>' +
      '<div><b>' + f.parts.length + '</b><span>Traits</span></div>' +
      '<div><b>DHC2F' + f.serial + '</b><span>Number</span></div>' +
      '<div><b style="font-size:11px">' + esc(made) + '</b><span>Assembled</span></div>';

    /* WHERE IT PLACES, which is the thing worth saying out loud about a
       Fighter and the thing a share is built from. Same four axes and the
       same pool the assembler previews against, so a Fighter does not rank
       one way on the canvas and another here. */
    document.getElementById('dhcg-rank').innerHTML = f.rank
      ? AXES.map(function (a) {
          return '<div><b>#' + f.rank[a[0]].toLocaleString() + '</b><span>' + a[1] + '</span></div>';
        }).join('') + '<div class="of">of ' + f.rankOf.toLocaleString() + ' Fighters</div>'
      : '';

    document.getElementById('dhcg-traits').innerHTML = f.parts.map(function (p) {
      var worn = p.worn > 0 ? p.worn + ' of 226 wear this' : 'in no minted Fighter';
      return '<li><span><span class="sl">' + esc(p.slot) + '</span>' + esc(p.name) +
             '<span class="sl" style="opacity:.4">' + worn + '</span></span>' +
             '<span class="tr t-' + p.tier + '">' + p.tier + '</span>' +
             '<span class="rt">' + p.pts + ' pts</span></li>';
    }).join('');

    veil.classList.add('on');
    document.getElementById('dhcg-close').focus();
  }

  function close() {
    veil.classList.remove('on');
    if (typeof window.DHC_MODAL === 'object' && window.DHC_MODAL
        && typeof window.DHC_MODAL.onClose === 'function') {
      var fn = window.DHC_MODAL.onClose;
      window.DHC_MODAL.onClose = null;   // one shot: a reload must not re-fire
      fn();
    }
    /* Drop the ?fighter= again so a refresh or a copied URL from here is the
       Collection, not whichever card happened to be open last. */
    try {
      var u = new URL(window.location.href);
      if (DEEPLINK && u.searchParams.has('fighter')) {
        u.searchParams.delete('fighter');
        history.replaceState(null, '', u.pathname + (u.search || '') + u.hash);
      }
    } catch (e) {}
  }


  document.getElementById('dhcg-close').addEventListener('click', close);
  veil.addEventListener('click', function (e) { if (e.target === veil) close(); });

  document.getElementById('dhcg-close').addEventListener('click', close);
  veil.addEventListener('click', function (e) { if (e.target === veil) close(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && veil.classList.contains('on')) close();
  });

  /* The two callers reach it through here rather than through globals the
     page happens to leave lying around. */
  window.DHC_MODAL = { open: open, close: close, onClose: null };
})();
</script>
<?php
}
?>
