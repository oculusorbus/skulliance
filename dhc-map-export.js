/*
 * dhc-map-export.js -- turning a map into a picture, for every map that is one.
 *
 * LIFTED OUT OF rivalries.js WHEN THE SIEGE MAP NEEDED THE SAME BUTTONS.
 * Every hard-won detail in here was learned once and must not be learned
 * again in a copy: the secure-static-mode inlining, the idle warm-up, the
 * two idle APIs taking different second arguments, and the clipboard write
 * having to happen inside the click's own activation. A second copy of this
 * is the DHCF_COVER_EFFECTS drift with a worse failure mode -- a button
 * that silently does nothing.
 *
 * Needs js/save-image.js (window.sklImage) and a map drawn as an <svg>
 * inside `host`. Call it once, after the map is in the DOM:
 *
 *   DHCMapExport.wire({ host: el, stem: 'skulliance-siege', bg: '#050c14',
 *                       ids: { download: 'sg-download', copy: 'sg-copy',
 *                              note: 'sg-shot-note' } });
 */
(function (root) {
  'use strict';

  function wire(o) {
    const host = o.host;
    if (!host) return;
    const ID = o.ids || {};
    /* NOT shadowed off `root`. In a browser they are the same objects; in
       the harnesses `document` and `window` are the function's own
       parameters and root.document is not set, so aliasing them here is how
       this threw the first time and took both buttons with it. */

    /* ---- taking a picture of it -------------------------------------------
     *
     * The map is an SVG, and an SVG rasterises through an <img> -- which puts
     * it in SECURE STATIC MODE, where it may not fetch anything at all. So
     * every <image> in the copy has to carry its pixels inline as a data uri
     * before it is serialised, or the export comes back as a graph of lines
     * with holes where every avatar and every Fighter used to be.
     *
     * Both sources can be read: the trait art is a relative path on this
     * origin, and cdn.discordapp.com sends access-control-allow-origin: *.
     * One of them failing costs one picture, not the export.
     *
     * BUILT ONCE AND CACHED, AND STARTED BEFORE ANYBODY ASKS. Copying needs
     * the blob inside the click's own activation -- Safari drops it across an
     * await -- so the work happens on idle after the map is drawn and the
     * button hands the finished promise straight to the clipboard.
     */
    const SCALE = o.scale || 2;   /* a 2x raster, so the text is not mushy */
    const BG    = o.bg || '#050c14';   /* the map box's ground: an SVG has none */

    /*
     * EVERYTHING THE EXPORT NEEDS, OR NO BUTTONS AT ALL. js/save-image.js is
     * a separate file and a separate request; if it does not arrive, a
     * button that throws on click is worse than one that was never shown.
     * The same guard is why this whole block is skipped in the renderer's
     * test harness, which has a DOM but no network.
     */
    const CAN_EXPORT = typeof window.XMLSerializer === 'function'
                    && typeof window.fetch === 'function'
                    && typeof window.FileReader === 'function'
                    && typeof window.setTimeout === 'function'
                    && !!window.sklImage;

    const dataUri = (() => {
      const seen = new Map();
      return (url) => {
        if (seen.has(url)) return seen.get(url);
        const p = fetch(url, { mode: 'cors', credentials: 'omit' })
          .then((r) => (r.ok ? r.blob() : Promise.reject(new Error(String(r.status)))))
          .then((b) => new Promise((res, rej) => {
            const fr = new FileReader();
            fr.onload = () => res(fr.result);
            fr.onerror = rej;
            fr.readAsDataURL(b);
          }))
          .catch(() => null);          /* one dead url costs one picture */
        seen.set(url, p);
        return p;
      };
    })();

    function exportBlob() {
      const svg = host.querySelector('svg');
      if (!svg) return Promise.reject(new Error('no map'));

      const copy = svg.cloneNode(true);
      copy.setAttribute('xmlns', 'http://www.w3.org/2000/svg');
      copy.setAttribute('xmlns:xlink', 'http://www.w3.org/1999/xlink');

      /* The focus dimming is a reading aid, not part of the picture. */
      copy.querySelectorAll('[opacity]').forEach((el) => el.removeAttribute('opacity'));

      /* COUNTED, because a dropped picture is otherwise silent: the export
         succeeds, the png is exactly the right size, and a face is missing
         from it. The button says so rather than letting it pass. */
      let dropped = 0;
      const imgs = Array.from(copy.querySelectorAll('image'));
      return Promise.all(imgs.map((im) => {
        const href = im.getAttribute('href') || im.getAttribute('xlink:href') || '';
        if (!href || href.indexOf('data:') === 0) return null;
        return dataUri(new URL(href, document.baseURI).href).then((d) => {
          if (d) im.setAttribute('href', d);
          else { im.remove(); dropped++; }   /* a broken frame is worse than a gap */
          im.removeAttribute('xlink:href');
        });
      })).then(() => {
        const W = Math.ceil(parseFloat(copy.getAttribute('width')));
        const H = Math.ceil(parseFloat(copy.getAttribute('height')));
        const xml = new XMLSerializer().serializeToString(copy);
        const url = 'data:image/svg+xml;charset=utf-8,' + encodeURIComponent(xml);
        return new Promise((res, rej) => {
          const im = new Image();
          im.onload = () => {
            const cv = document.createElement('canvas');
            cv.width = W * SCALE; cv.height = H * SCALE;
            const c = cv.getContext('2d');
            c.fillStyle = BG; c.fillRect(0, 0, cv.width, cv.height);
            c.drawImage(im, 0, 0, cv.width, cv.height);
            /* Everything is inline by now, so the canvas is not tainted and
               this cannot throw -- but it is the step that would. */
            try {
              const blob = window.sklImage.canvasBlob(cv);
              blob.dropped = dropped;
              res(blob);
            } catch (e) { rej(e); }
          };
          im.onerror = () => rej(new Error('the map could not be rasterised'));
          im.src = url;
        });
      });
    }

    let pending = null;
    const build = () => (pending = pending || exportBlob());
    root[o.expose || 'mapExportBlob'] = exportBlob;   /* reached by the export harness */

    if (!CAN_EXPORT) {
      [ID.download, ID.copy].forEach((id) => {
        const b = document.getElementById(id);
        if (b) b.hidden = true;
      });
      return;
    }

    /*
     * Warmed on idle so a click has it ready.
     *
     * THE TWO APIS DO NOT TAKE THE SAME SECOND ARGUMENT, and conflating
     * them threw: requestIdleCallback's second parameter is an
     * IdleRequestOptions object, so passing setTimeout's milliseconds is a
     * TypeError. It fired synchronously, right here, and took the rest of
     * this function with it -- so the two click handlers below were never
     * attached and both buttons silently did nothing.
     */
    const warm = () => { build().catch(() => {}); };
    if (typeof window.requestIdleCallback === 'function') window.requestIdleCallback(warm, { timeout: 2500 });
    else window.setTimeout(warm, 1200);

    const fname = () => o.stem + '-' + new Date().toISOString().slice(0, 10) + '.png';
    const say = (m) => { const n = document.getElementById(ID.note); if (n) n.textContent = m; };

    const missing = (b) => (b && b.dropped)
      ? ' ' + b.dropped + (b.dropped === 1 ? ' picture was' : ' pictures were') + ' unavailable.'
      : '';

    const dl = document.getElementById(ID.download);
    if (dl) dl.addEventListener('click', () => {
      say('Preparing\u2026');
      build()
        .then((b) => window.sklImage.save(b, fname()).then((m) => (m || 'Saved.') + missing(b)))
        .then(say)
        .catch(() => say('The picture could not be made. Try reloading the map.'));
    });

    const cp = document.getElementById(ID.copy);
    if (cp) {
      if (!window.sklImage.canCopy()) cp.hidden = true;
      /* The promise goes to the clipboard INSIDE the click. Awaiting the blob
         first and copying after loses Safari's activation and the write is
         refused -- which looks exactly like the button doing nothing. */
      else cp.addEventListener('click', () => {
        say('Preparing\u2026');
        /* The promise, not an awaited blob -- see js/save-image.js. The
           dropped count is read afterwards, once it has settled. */
        const p = build();
        window.sklImage.copy(p)
          .then((m) => p.then((b) => say(m + missing(b))))
          .catch(() => say('Copying was refused. Download it instead.'));
      });
    }
  }

  root.DHCMapExport = { wire: wire };
})(typeof window !== 'undefined' ? window : globalThis);
