/*
 * dhc-crew-kit.js -- drawing a player's Crew, for every map that draws one.
 *
 * TWO MAPS NOW DRAW FIGHTERS AROUND A HUB: rivalries.js (who is fighting
 * whom in the Arena) and siege.js (who is attacking the boss crew). The
 * packing, the portrait and the trait-layer geometry are identical in both;
 * only what sits at the middle of the picture differs.
 *
 * SO IT IS MOVED HERE RATHER THAN COPIED. A second copy of this would be the
 * DHCF_COVER_EFFECTS bug again: one list grew, the other did not, and the
 * drift was found by a player rather than a check. There is one answer to
 * what a Fighter looks like on a map.
 *
 * LOADED AFTER map-kit.js, which owns svgEl() and the text measurer, and
 * before whichever map is using it.
 *
 * Nothing here decides WHERE a cluster goes -- that is the map's own
 * question, and it is the only one they answer differently.
 */
(function (root) {
  'use strict';

  /*
   * WHERE A TRAIT LAYER SITS, in whatever is drawing it.
   *
   * A map draws portraits as SVG and the player panels draw them as stacked
   * <img>, which are different enough that one renderer cannot serve both --
   * but the RULES are the same two numbers: how far the layer is nudged
   * down, and where a single-sided arm is cut from the two-arm master.
   * armSplit is DHCF_ONE_ARM_SPLIT, inlined by the page.
   */
  function layerGeom(L, size, armSplit) {
    var split = armSplit || 50;
    var g = { dy: (L.n || 0) / 100 * size, cut: null };
    if (L.clip === 'right')     g.cut = { from: split, to: 100 };
    else if (L.clip === 'left') g.cut = { from: 0, to: split };
    return g;
  }

  /*
   * HOW MUCH ROOM A CREW NEEDS, and how it is stacked.
   *
   * Portraits laid along an arc need ring radius proportional to how many
   * there are, so an unbounded roster sweeps a third of the canvas and drags
   * every other cluster's spacing out with it. Three bounds, all of which
   * were added because one player's roster blew a map apart:
   *
   *   max       draw the most-active few; the rest are counted in the label.
   *   shrinking portraits get smaller as the Crew grows, so ten fit in
   *             roughly the room six used to need.
   *   shells    beyond perShell they stack outward in a second arc rather
   *             than stretching the first one further round.
   *
   * `weight` reads whatever the map ranks a Fighter on -- battles on the
   * Arena map, damage on the Siege map -- so the kit never learns either.
   */
  function crewGeom(node, opt) {
    var o = opt || {};
    var max      = o.max      || 12,
        perShell = o.perShell || 6,
        satMax   = o.satMax   || 30,
        span     = o.span     || (Math.PI * 0.9),
        weight   = o.weight   || function (f) { return 1; };

    var fighters = node.fighters || [];
    var total = fighters.length;
    var k = Math.min(total, max);
    var busiest = fighters.reduce(function (m, f) { return Math.max(m, weight(f)); }, 0) || 1;
    if (!k) return { busiest: busiest, shown: 0, hidden: 0, smax: 0, shells: [],
                     outer: node.r + 2, span: span };

    var smax = k <= perShell ? satMax
             : Math.max(12, Math.round(satMax * Math.sqrt(perShell / k)));

    /* Spread as evenly as possible over as few shells as will hold them. */
    var nShell = Math.ceil(k / perShell), counts = [], left = k, i;
    for (i = 0; i < nShell; i++) {
      var n = Math.ceil(left / (nShell - i));
      counts.push(n);
      left -= n;
    }

    /* Each shell has to clear the one inside it AND be long enough to hold
       its own portraits side by side along the span. */
    var shells = [], prev = node.r;
    counts.forEach(function (n) {
      var arc = n > 1 ? (2 * smax * (n - 1)) / span : 0;
      var r   = Math.max(prev + 6 + smax, arc);
      shells.push({ n: n, r: r });
      prev = r + smax;
    });

    return { busiest: busiest, smax: smax, shown: k, hidden: total - k,
             shells: shells, outer: shells[shells.length - 1].r + smax + 2,
             span: span };
  }

  /** One portrait's radius, by its share of the cluster's busiest. */
  function satRadius(w, busiest, smax) {
    var smin = Math.max(6, Math.round(smax * 0.5));
    return Math.round(smin + (smax - smin) * Math.sqrt(w / (busiest || 1)));
  }

  /*
   * WHERE EACH PORTRAIT GOES, decided once and handed to the renderer.
   *
   * A draw loop that works this out for itself leaves the ink pass with only
   * the cluster's worst-case reach to go on, so the canvas comes out padded
   * with dead space the drawing never uses. Computing the positions here
   * makes the ink exact and removes the second copy of the arc maths: the
   * thing measured is literally the thing drawn.
   *
   * `ang` is the direction the Crew faces, which every map so far wants to
   * be AWAY from the middle of the picture -- the inward side of a hub is
   * where the lines arrive, and a portrait there sits on top of the thing it
   * is meant to explain.
   */
  function crewPlaces(x, y, ang, node, g, weight) {
    var w = weight || function (f) { return 1; };
    var sats = [], fi = 0;
    g.shells.forEach(function (shell) {
      for (var j = 0; j < shell.n; j++, fi++) {
        var f = node.fighters[fi];
        if (!f) break;
        var t0 = shell.n === 1 ? 0.5 : j / (shell.n - 1);
        var sa = ang - g.span / 2 + t0 * g.span;
        var fr = satRadius(w(f), g.busiest, g.smax);
        sats.push({ f: f, r: fr, x: x + Math.cos(sa) * shell.r, y: y + Math.sin(sa) * shell.r });
      }
    });
    return sats;
  }

  /*
   * ONE FIGHTER, DRAWN.
   *
   * The layer stack is dhcf_layers()' -- the same one the Collection and the
   * Discord render use -- so the draw order, the armless-torso swap and the
   * single-arm restore are not re-decided here or anywhere else.
   *
   * Returns the <g>; the caller owns the class, the data attributes and the
   * <title>, because those are the map's own vocabulary.
   */
  function drawPortrait(sv, opt) {
    var o = opt || {};
    var svgEl = o.svgEl || root.svgEl;
    var defs  = o.defs;
    var uid   = o.uid || 'crew';
    var ring  = o.ring || 'rgba(255,255,255,.5)';
    var ground = o.ground || '#0a1929';
    var split = o.armSplit;

    var f = sv.f, fr = sv.r, cx = sv.x, cy = sv.y;
    var g = svgEl('g', {});
    /* A disc behind the art: the trait PNGs are transparent, and with no
       ground behind them a Fighter reads as floating debris, not a
       portrait. */
    g.appendChild(svgEl('circle', {
      cx: cx.toFixed(1), cy: cy.toFixed(1), r: fr,
      fill: ground, stroke: ring, 'stroke-width': 1.5,
    }));
    if (!f.layers || !f.layers.length) return g;

    var cid = uid + '_c';
    var cp  = svgEl('clipPath', { id: cid });
    cp.appendChild(svgEl('circle', { cx: cx.toFixed(1), cy: cy.toFixed(1), r: fr - 1 }));
    defs.appendChild(cp);
    var box = svgEl('g', { 'clip-path': 'url(#' + cid + ')' });
    var S = fr * 2, X = cx - fr, Y = cy - fr;
    f.layers.forEach(function (L, li) {
      var lg = layerGeom(L, S, split);
      var attrs = {
        x: X.toFixed(1), y: (Y + lg.dy).toFixed(1),
        width: S.toFixed(1), height: S.toFixed(1),
        href: L.src, preserveAspectRatio: 'xMidYMid meet',
      };
      /* A single-sided arm is the two-arm master with half cut away. The
         Collection cuts it with a CSS inset(); a clipPath rect is the same
         cut and does not rely on CSS applying to an SVG <image>. */
      if (lg.cut) {
        var hid = cid + '_a' + li;
        var hcp = svgEl('clipPath', { id: hid });
        hcp.appendChild(svgEl('rect', {
          x: (X + S * lg.cut.from / 100).toFixed(1),
          y: Y.toFixed(1),
          width: (S * (lg.cut.to - lg.cut.from) / 100).toFixed(1),
          height: S.toFixed(1),
        }));
        defs.appendChild(hcp);
        var wrap = svgEl('g', { 'clip-path': 'url(#' + hid + ')' });
        wrap.appendChild(svgEl('image', attrs));
        box.appendChild(wrap);
      } else {
        box.appendChild(svgEl('image', attrs));
      }
    });
    g.appendChild(box);
    return g;
  }

  /*
   * THE CANVAS IS SIZED TO THE INK, NOT TO A MARGIN.
   *
   * A uniform pad has to be the largest any cluster could need, applied on
   * all four sides, so the map carries slack everywhere except at the one
   * cluster that set it. That slack is dead space, and it hides mistakes: a
   * label measurement could be dropped entirely and the leftover room would
   * absorb it. So every piece of ink is measured and the canvas is the
   * union.
   */
  function Ink() {
    this.x0 = Infinity; this.y0 = Infinity;
    this.x1 = -Infinity; this.y1 = -Infinity;
  }
  Ink.prototype.add = function (x0, y0, x1, y1) {
    if (x0 < this.x0) this.x0 = x0;
    if (y0 < this.y0) this.y0 = y0;
    if (x1 > this.x1) this.x1 = x1;
    if (y1 > this.y1) this.y1 = y1;
  };
  Ink.prototype.disc = function (x, y, r) { this.add(x - r, y - r, x + r, y + r); };
  /** Shift everything into positive space; returns {ox, oy, w, h}. */
  Ink.prototype.frame = function (edge) {
    var e = edge === undefined ? 10 : edge;
    return { ox: e - this.x0, oy: e - this.y0,
             w: Math.round(this.x1 - this.x0 + e * 2),
             h: Math.round(this.y1 - this.y0 + e * 2) };
  };

  root.DHCCrewKit = {
    layerGeom: layerGeom,
    crewGeom: crewGeom,
    satRadius: satRadius,
    crewPlaces: crewPlaces,
    drawPortrait: drawPortrait,
    Ink: Ink,
  };
})(typeof window !== 'undefined' ? window : globalThis);
