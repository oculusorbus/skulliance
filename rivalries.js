/*
 * rivalries.js -- the Arena's Rivalries map.
 *
 * One cluster per player who has fought in the window: their avatar at the
 * middle sized by how much they have fought, their Fighters orbiting it sized
 * by how much each one has. Arrowed lines between clusters, thickness by how
 * many battles the pair have had, two arrowheads where it runs both ways.
 *
 * READS window.RIVALRY_DATA, which dhcarena.php inlines from
 * dhca_rivalry_layout(). Nothing is computed here that the server could have
 * computed: the radii, the pair folding and the lean labels all arrive done,
 * because they are assertions about the data and belong where they can be
 * tested without a browser. This file decides only WHERE things go.
 *
 * SHARES map-kit.js WITH THE REALM MAP, which must be loaded first. The
 * polygon and packing maths are identical; what differs is that the realm map
 * groups by faction and this groups by player.
 *
 * THE LAYOUT IS STABLE ACROSS RELOADS, and that is not a nicety. "My cluster
 * is the one bottom-left" is how a person navigates a graph they come back to,
 * so every random choice here is seeded off a player id through
 * mulberry32(hashStr(...)) and never off Math.random.
 */
(function () {
  const D = window.RIVALRY_DATA;
  /* DHCF_ONE_ARM_SPLIT, inlined by the page: where a two-arm master is cut to
     make a one-sided arm. The same constant the Collection and assembler use. */
  const ARM_SPLIT = window.RIVALRY_ARM_SPLIT || 50;
  const host = document.getElementById('riv-map');
  if (!host) return;
  if (!D || !D.nodes || !Object.keys(D.nodes).length) return;   // the panel's own empty state stands

  const ids = Object.keys(D.nodes);
  const N   = ids.length;

  /* ---- how big a cluster is -----------------------------------------------
     A hub plus a ring of Fighter portraits around it. The portraits are real
     art rather than dots, so they are big enough to read -- which means the
     cluster's footprint is driven by them, not by the avatar. */
  const SAT_MIN = 15, SAT_MAX = 30;      // Fighter portrait radius, at a small Crew
  /*
   * THE CREW OCCUPIES THE OUTER HALF of its hub, a little under 180 degrees.
   * Anything wider tips the end portraits back inward, where the grudge lines
   * arrive and the name sits. 0.9pi leaves both ends comfortably outward
   * instead of exactly on the boundary.
   */
  const CREW_SPAN = Math.PI * 0.9;
  /*
   * AND IT IS BOUNDED, in three ways, because one player's roster blew the
   * whole map apart.
   *
   * Portraits laid along an arc need ring radius proportional to how many
   * there are: 25 Fighters at 30px each wanted a ring over 500px, so that one
   * cluster swept a third of the canvas and dragged every other cluster's
   * spacing out with it. A roster is also not the same question as a Crew --
   * somebody who has fielded 25 different Fighters in a month is telling you
   * about their collection, not about who they fight with.
   *
   *   CREW_MAX  draw the most-fielded few; the rest are counted in the label.
   *   shrinking portraits get smaller as the Crew grows, so ten fit in
   *             roughly the room six used to need.
   *   shells    beyond PER_SHELL they stack outward in a second arc instead
   *             of stretching the first one further round.
   */
  const CREW_MAX = window.RIVALRY_CREW_MAX || 12, PER_SHELL = 6;

  /*
   * WHERE A TRAIT LAYER SITS, in whatever is drawing it.
   *
   * The map draws portraits as SVG and the player panel draws them as stacked
   * <img>, which are different enough that one renderer cannot serve both --
   * but the RULES are the same two numbers: how far the layer is nudged down,
   * and where a single-sided arm is cut from the two-arm master. Those live
   * here so the two cannot disagree about what a Fighter looks like.
   */
  function layerGeom(L, size) {
    const g = { dy: (L.n || 0) / 100 * size, cut: null };
    if (L.clip === 'right')     g.cut = { from: ARM_SPLIT, to: 100 };
    else if (L.clip === 'left') g.cut = { from: 0, to: ARM_SPLIT };
    return g;
  }

  function satR(f, busiest, smax) {
    const smin = Math.max(6, Math.round(smax * 0.5));
    return Math.round(smin + (smax - smin) * Math.sqrt(f.battles / (busiest || 1)));
  }

  function geom(node) {
    const total = node.fighters.length;
    const k = Math.min(total, CREW_MAX);
    const busiest = node.fighters.reduce((m, f) => Math.max(m, f.battles), 0) || 1;
    if (!k) return { busiest: busiest, shown: 0, hidden: 0, smax: 0, shells: [], outer: node.r + 2 };

    /* Portraits shrink with the Crew, so a deep one stays compact instead of
       demanding a bigger ring for every extra Fighter. */
    const smax = k <= PER_SHELL ? SAT_MAX
               : Math.max(12, Math.round(SAT_MAX * Math.sqrt(PER_SHELL / k)));

    /* Spread as evenly as possible over as few shells as will hold them. */
    const nShell = Math.ceil(k / PER_SHELL);
    const counts = [];
    let left = k;
    for (let i = 0; i < nShell; i++) {
      const n = Math.ceil(left / (nShell - i));
      counts.push(n);
      left -= n;
    }

    /* Each shell has to clear the one inside it AND be long enough to hold
       its own portraits side by side along CREW_SPAN. */
    const shells = [];
    let prev = node.r;
    counts.forEach(n => {
      const arc = n > 1 ? (2 * smax * (n - 1)) / CREW_SPAN : 0;
      const r   = Math.max(prev + 6 + smax, arc);
      shells.push({ n: n, r: r });
      prev = r + smax;
    });

    return {
      busiest: busiest, smax: smax,
      shown: k, hidden: total - k, shells: shells,
      outer: shells[shells.length - 1].r + smax + 2,
    };
  }

  /*
   * ---- the ring ------------------------------------------------------------
   *
   * A GRID WAS WRONG. Every cell had to be as big as the biggest cluster, so
   * one player with a deep Crew inflated all of them and the map became mostly
   * empty space -- unreadable at ten players and worse at thirty.
   *
   * A ring fixes both halves of that. Clusters sit on a circle sized to what
   * they actually need, so a quiet player takes a quiet player's room; and
   * every grudge line becomes a chord across the middle, where it can be seen,
   * instead of a strand threading between boxes.
   *
   * THE ORDER AROUND THE RING IS THE SERIATION, which matters more here than
   * it did in a grid: neighbours on a circle are adjacent, so putting heavy
   * rivals next to each other turns the deepest feuds into short chords near
   * the rim and leaves the middle for the long, rarer ones.
   */
  function order() {
    const weight = {};   // id -> id -> battles
    for (const p of D.pairs) {
      (weight[p.lo] = weight[p.lo] || {})[p.hi] = p.n;
      (weight[p.hi] = weight[p.hi] || {})[p.lo] = p.n;
    }
    const left = ids.slice().sort((a, b) =>
      D.nodes[b].battles - D.nodes[a].battles || String(a).localeCompare(String(b)));
    const out = [];
    while (left.length) {
      let best = 0, bestW = -1;
      if (out.length) {
        for (let i = 0; i < left.length; i++) {
          let w = 0;
          for (const o of out) w = Math.max(w, (weight[left[i]] || {})[o] || 0);
          /* Ties broken by battles, then by id, so the order is total and the
             map cannot shuffle between two reloads with equal data. */
          if (w > bestW || (w === bestW && D.nodes[left[i]].battles > D.nodes[left[best]].battles)) {
            bestW = w; best = i;
          }
        }
      }
      out.push(left.splice(best, 1)[0]);
    }
    return out;
  }

  const seq = order();
  const G   = {};
  seq.forEach(id => { G[id] = geom(D.nodes[id]); });

  /* ONE DEFINITION OF THE SECOND LABEL LINE. It is measured below to size
     the canvas and written out again when the cluster is drawn; two copies
     of the string would mean measuring something other than what renders. */
  function subText(n, g) {
    return `${n.battles} battle${n.battles === 1 ? '' : 's'} · ${n.wins}W ${n.losses}L`
         + (g.hidden ? ` · +${g.hidden} more` : '');
  }

  /*
   * LABELS SIT RADIALLY OUTSIDE THE RING, not under their cluster.
   *
   * "Below" is only unambiguous in a grid. On a ring, the space below a
   * cluster on the right-hand side is the inside of its neighbour, and that
   * is exactly where a username landed -- swallowed by the next player's
   * disc. Pushed outward instead, every label sits in the empty annulus
   * beyond the clusters, where it has MORE angular room than the cluster it
   * belongs to rather than less.
   *
   * LABEL is the margin that annulus needs: the offset, two lines of text,
   * and enough width for a centred username to spread either side.
   */
  const LABEL_GAP = 17, GAP = 26;
  const outer = seq.map(id => G[id].outer);
  const maxOut = Math.max.apply(null, outer);

  /*
   * THE RADIUS COMES FROM THE CIRCUMFERENCE THE CLUSTERS NEED, not from a
   * guess. Each wants 2*outer plus a gap of arc to itself, so the ring is big
   * enough when its circumference covers the sum -- which handles wildly
   * uneven cluster sizes without leaving a hole where a small one sits.
   */
  /* Arc length each cluster needs to itself: its own width, plus a gap. */
  const demand = seq.map(id => 2 * G[id].outer + GAP);
  const needed = demand.reduce((t, d) => t + d, 0);
  const R = N === 1 ? 0 : Math.max(maxOut + 40, needed / (2 * Math.PI));

  /*
   * ---- THE CANVAS IS SIZED TO THE INK ----
   *
   * Not to a margin. A uniform PAD has to be the largest any cluster could
   * need, applied on all four sides, so the map carries slack everywhere
   * except at the one cluster that set it -- and the cluster that sets the
   * margin is rarely the one sitting at the edge. That slack is dead space on
   * a page that wants to be full width, and it also hides mistakes: a label
   * measurement could be dropped entirely and the leftover room would absorb
   * it.
   *
   * So the clusters are laid out around an origin, every piece of ink they
   * produce is measured -- avatar, Crew, and both label lines in the
   * direction that label actually runs -- and the canvas is the union, shifted
   * into view. The widest ink on the realm map turned out to be a username
   * rather than any shape, which is why map-kit.js carries a text measurer;
   * the same is true here.
   */
  const FONT = 'Arial, sans-serif';
  let inkX0 = Infinity, inkY0 = Infinity, inkX1 = -Infinity, inkY1 = -Infinity;
  function ink(x0, y0, x1, y1) {
    if (x0 < inkX0) inkX0 = x0;
    if (y0 < inkY0) inkY0 = y0;
    if (x1 > inkX1) inkX1 = x1;
    if (y1 > inkY1) inkY1 = y1;
  }

  const used  = needed / (R || 1);
  const slack = Math.max(0, Math.PI * 2 - used) / N;
  let acc = 0;
  /* Laid out around (0,0) and shifted once the ink is known. Start at the top
     and run clockwise, so the busiest player -- first in the seriation -- is
     where the eye lands. */
  const placed = seq.map((id, i) => {
    const slice = demand[i] / (R || 1) + slack;
    /*
     * WITH ONE PLAYER THE RING HAS NO RADIUS, and the angle arithmetic goes
     * with it: every cluster lands at the origin whatever the angle says, so
     * `a` is free to be nonsense -- and it was, which pointed the lone
     * player's Crew and label off in whichever direction the division
     * happened to produce. There is nothing to orient around, so it faces up.
     */
    const a = N === 1 ? -Math.PI / 2 : (-Math.PI / 2 + acc + slice / 2);
    acc += slice;
    const g = G[id], n = D.nodes[id];
    const x = Math.cos(a) * R, y = Math.sin(a) * R;

    /*
     * WHERE EACH PORTRAIT GOES, decided once.
     *
     * The draw loop used to work this out for itself, which left the ink pass
     * with only `outer` to go on -- the cluster's worst-case reach in ANY
     * direction. But the Crew occupies an ARC, so a box of `outer` on all four
     * sides is far bigger than the cluster really is, and the canvas came out
     * padded with dead space the drawing never used.
     *
     * Computing the positions here and handing them to the renderer makes the
     * ink exact and removes the second copy of the arc maths at the same
     * time: the thing measured is now literally the thing drawn.
     */
    const sats = [];
    let fi = 0;
    g.shells.forEach(shell => {
      for (let j = 0; j < shell.n; j++, fi++) {
        const f = n.fighters[fi];
        if (!f) break;
        const t0 = shell.n === 1 ? 0.5 : j / (shell.n - 1);
        const sa = a - CREW_SPAN / 2 + t0 * CREW_SPAN;
        const fr = satR(f, g.busiest, g.smax);
        sats.push({ f: f, r: fr, x: x + Math.cos(sa) * shell.r, y: y + Math.sin(sa) * shell.r });
      }
    });
    /* The avatar, allowing for its lean ring, then every portrait.
       (The +5 for the ring is conservatism the tests cannot pin down: a
       leaning avatar is never the outermost ink, because its own label always
       reaches further out than it does. It stays because it is correct, not
       because anything is currently measuring it.) */
    ink(x - n.r - 5, y - n.r - 5, x + n.r + 5, y + n.r + 5);
    sats.forEach(sv => ink(sv.x - sv.r, sv.y - sv.r, sv.x + sv.r, sv.y + sv.r));

    /* The label, which runs outward from the cluster's edge and is the ink
       most likely to be furthest out. Its horizontal reach depends on which
       way it is anchored, so that is computed the same way it is drawn. */
    const cosA = Math.cos(a);
    const anchor = cosA > 0.3 ? 'start' : (cosA < -0.3 ? 'end' : 'middle');
    const lw = Math.max(
      measureTextWidth(n.name, 11, FONT, 'normal', '0'),
      measureTextWidth(subText(n, g), 9.5, FONT, 'normal', '0'));
    const lx = x + cosA * (g.outer + LABEL_GAP);
    const ly = y + Math.sin(a) * (g.outer + LABEL_GAP) + 4;
    const x0 = anchor === 'start' ? lx : (anchor === 'end' ? lx - lw : lx - lw / 2);
    ink(x0, ly - 10, x0 + lw, ly + 12 + 4);

    return { id: id, node: n, g: g, ang: a, slice: slice, anchor: anchor,
             x: x, y: y, sats: sats };
  });

  /* Shift the whole drawing into positive space with a hair of breathing room,
     and the canvas is exactly the ink plus that. */
  const EDGE = 10;
  const OX = EDGE - inkX0, OY = EDGE - inkY0;
  placed.forEach(p => {
    p.x += OX; p.y += OY;
    p.sats.forEach(sv => { sv.x += OX; sv.y += OY; });
  });
  const W = Math.round(inkX1 - inkX0 + EDGE * 2);
  const H = Math.round(inkY1 - inkY0 + EDGE * 2);

  const svg = svgEl('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}` });
  svg.setAttribute('class', 'riv-svg');
  const defs = svgEl('defs');
  svg.appendChild(defs);

  /*
   * ARROWHEADS: one PAIR PER COLOUR, at a fixed size.
   *
   * markerUnits defaults to strokeWidth, which scales the head with the line.
   * The heaviest grudge draws a 6px line, so the default turned its arrowhead
   * into a 50px black wedge that swallowed the avatar it was pointing at.
   * userSpaceOnUse pins the head to the size given here, so a thick line reads
   * as thick rather than as an arrow with a tail.
   *
   * And the fill is a real colour, not `fill: context-stroke`. That keyword
   * takes the line's own stroke and is exactly what is wanted, but it is not
   * supported everywhere -- where it is not, the head falls back to BLACK,
   * which on this background is an arrowhead that has simply vanished. A
   * marker is referenced by id, so the colour cannot come from the line; it
   * has to come from the id. There are at most as many as the palette.
   */
  const markerIds = {};
  function arrowFor(col) {
    if (markerIds[col]) return markerIds[col];
    const n = 'riv-a' + Object.keys(markerIds).length;
    for (const [suffix, d, rx] of [['', 'M0,0 L0,6 L7,3 z', 7], ['b', 'M7,0 L7,6 L0,3 z', 0]]) {
      const m = svgEl('marker', {
        id: n + suffix, markerWidth: 7, markerHeight: 6, refX: rx, refY: 3,
        orient: 'auto', markerUnits: 'userSpaceOnUse',
      });
      m.appendChild(svgEl('path', { d: d, fill: col }));
      defs.appendChild(m);
    }
    markerIds[col] = n;
    return n;
  }
  const glow = svgEl('filter', { id: 'riv-glow', x: '-30%', y: '-30%', width: '160%', height: '160%' });
  glow.appendChild(svgEl('feGaussianBlur', { in: 'SourceGraphic', stdDeviation: '3', result: 'b' }));
  const gm = svgEl('feMerge');
  gm.appendChild(svgEl('feMergeNode', { in: 'b' }));
  gm.appendChild(svgEl('feMergeNode', { in: 'SourceGraphic' }));
  glow.appendChild(gm);
  defs.appendChild(glow);

  /* On the ring, a cluster's hub IS its position. */
  const at = {};
  placed.forEach(p => { at[p.id] = { x: p.x, y: p.y, r: p.node.r }; });

  /*
   * NOTHING IS DRAWN UNDER A CLUSTER, and that is deliberate.
   *
   * The realm map puts each faction inside a jittered polygon because a
   * faction OWNS GROUND -- the shape is the territory, and the realms in it
   * are what is standing there. None of that is true here. A player is a
   * person, not a place, and their Fighters are not occupying anything: they
   * are a crew standing with them. Drawing a box around each one borrowed
   * the realm map's furniture along with its geometry and said something
   * about the Arena that is not so.
   *
   * What groups a Crew to its owner instead is proximity and colour: the
   * portraits sit in a tight arc against the avatar and are ringed in that
   * player's colour. p.g.outer is still the cluster's reach -- the ring
   * sizing and the label offsets are measured from it -- it is simply not
   * painted.
   */

  /* ---- the lines ----------------------------------------------------------
     Drawn AFTER the territories and BEFORE the avatars, so a grudge reads as
     running between two people rather than over their faces. Heaviest first
     (the server sorted them), so the big feuds sit underneath. */
  const lines = svgEl('g', {});
  svg.appendChild(lines);
  const heaviest = D.pairs.length ? D.pairs[0].n : 1;

  D.pairs.forEach(pr => {
    const a = at[pr.lo], b = at[pr.hi];
    if (!a || !b) return;
    /* Stop the line at each avatar's edge rather than its centre, so an
       arrowhead points AT the player instead of vanishing under them. */
    const dx = b.x - a.x, dy = b.y - a.y;
    const len = Math.hypot(dx, dy) || 1;
    const ux = dx / len, uy = dy / len;
    const x1 = a.x + ux * (a.r + 3), y1 = a.y + uy * (a.r + 3);
    const x2 = b.x - ux * (b.r + 3), y2 = b.y - uy * (b.r + 3);

    /* sqrt again: stroke width is read as weight, and weight is area. */
    const w = 1 + 5 * Math.sqrt(pr.n / heaviest);
    /* The heavier party colours the line, so a line belongs to whoever is
       driving the rivalry. An even feud takes the lower id, which is stable. */
    const owner = pr.lohi >= pr.hilo ? pr.lo : pr.hi;
    const col   = mapkitColorFor(owner);
    const mk    = arrowFor(col);
    const line = svgEl('line', {
      x1: x1.toFixed(1), y1: y1.toFixed(1), x2: x2.toFixed(1), y2: y2.toFixed(1),
      stroke: hexToRgba(col, pr.mutual ? 0.75 : 0.45),
      'stroke-width': w.toFixed(1),
      'stroke-dasharray': pr.mutual ? '' : '7 5',
      'marker-end':   pr.lohi > 0 ? `url(#${mk})`  : '',
      'marker-start': pr.hilo > 0 ? `url(#${mk}b)` : '',
    });
    line.setAttribute('data-lo', pr.lo);
    line.setAttribute('data-hi', pr.hi);
    const nm = (id) => (D.nodes[id] ? D.nodes[id].name : '?');
    const bits = [];
    if (pr.lohi) bits.push(`${nm(pr.lo)} attacked ${nm(pr.hi)} ${pr.lohi}×`);
    if (pr.hilo) bits.push(`${nm(pr.hi)} attacked ${nm(pr.lo)} ${pr.hilo}×`);
    bits.push(`${nm(pr.lo)} leads ${pr.lowins}–${pr.hiwins}`);
    const t = svgEl('title', {});
    t.textContent = bits.join('. ') + '.';
    line.appendChild(t);
    lines.appendChild(line);
  });

  /* ---- the clusters ---- */
  placed.forEach(p => {
    const n = p.node, col = mapkitColorFor(p.id);
    const g = svgEl('g', {});
    g.setAttribute('class', 'riv-cluster');
    g.setAttribute('data-id', p.id);

    /* Satellites first, so a Fighter can never cover its owner's face. */
    const hub = at[p.id];
    /*
     * THE CREW FACES OUTWARD. Each cluster sits on a ring and every grudge
     * line is a chord across the middle, so the inward side of a hub is where
     * those lines arrive -- a portrait there sits on top of the very thing it
     * is meant to explain. The Fighters therefore occupy the arc pointing AWAY
     * from the centre of the map, which is also the only space a cluster
     * reliably has to itself.
     */
    /* FROM p.sats, not worked out again here. The ink pass that sized the
       canvas placed these; recomputing them is how a drawing comes to sit
       somewhere the canvas was not measured for. */
    p.sats.forEach(sv => {
      {
        const f = sv.f, fr = sv.r, cx = sv.x, cy = sv.y;

      const sg = svgEl('g', {});
      sg.setAttribute('class', 'riv-sat');
      sg.setAttribute('data-fid', f.id);
      /* The serial too, because ajax/dhc-fighter.php is keyed on it and that
         endpoint is how a click opens the Collection's panel. */
      sg.setAttribute('data-serial', f.serial);
      /* A disc behind the art: the trait PNGs are transparent, and with no
         ground behind them a Fighter reads as floating debris, not a
         portrait. */
      sg.appendChild(svgEl('circle', {
        cx: cx.toFixed(1), cy: cy.toFixed(1), r: fr,
        fill: '#0a1929', stroke: hexToRgba(col, 0.75), 'stroke-width': 1.5,
      }));
      /* THE FIGHTER ITSELF, from the same layer stack the Collection and the
         Discord render use. dhcf_layers() decided the draw order, the
         armless-torso swap and the single-arm restore; none of it is
         re-decided here. */
      if (f.layers && f.layers.length) {
        const cid = 'rivsat' + p.id + '_' + f.id;
        const cp  = svgEl('clipPath', { id: cid });
        cp.appendChild(svgEl('circle', { cx: cx.toFixed(1), cy: cy.toFixed(1), r: fr - 1 }));
        defs.appendChild(cp);
        const box = svgEl('g', { 'clip-path': 'url(#' + cid + ')' });
        const S = fr * 2, X = cx - fr, Y = cy - fr;
        f.layers.forEach((L, li) => {
          const lg = layerGeom(L, S);
          const attrs = {
            x: X.toFixed(1), y: (Y + lg.dy).toFixed(1),
            width: S.toFixed(1), height: S.toFixed(1),
            href: L.src, preserveAspectRatio: 'xMidYMid meet',
          };
          /* A single-sided arm is the two-arm master with half cut away. The
             Collection cuts it with a CSS inset(); a clipPath rect is the same
             cut and does not rely on CSS applying to an SVG <image>. */
          if (lg.cut) {
            const hid = cid + '_a' + li;
            const hcp = svgEl('clipPath', { id: hid });
            hcp.appendChild(svgEl('rect', {
              x: (X + S * lg.cut.from / 100).toFixed(1),
              y: Y.toFixed(1),
              width: (S * (lg.cut.to - lg.cut.from) / 100).toFixed(1),
              height: S.toFixed(1),
            }));
            defs.appendChild(hcp);
            const wrap = svgEl('g', { 'clip-path': 'url(#' + hid + ')' });
            wrap.appendChild(svgEl('image', attrs));
            box.appendChild(wrap);
          } else {
            box.appendChild(svgEl('image', attrs));
          }
        });
        sg.appendChild(box);
      }
      const t = svgEl('title', {});
      /* Said in words. "4/1/0" would need a legend nobody reads. */
      t.textContent = f.name + ' \u00b7 ' + f.battles + ' battle' + (f.battles === 1 ? '' : 's')
        + ' (' + f.att + ' attacking, ' + f.def + ' defending), '
        + f.dealt.toLocaleString() + ' damage dealt'
        + (f.fell ? ', knocked out ' + f.fell + '\u00d7' : ', never knocked out');
      sg.appendChild(t);
      g.appendChild(sg);
      }
    });

    /* The hub: a ring that says which way this player leans, then the avatar. */
    if (n.lean) {
      g.appendChild(svgEl('circle', {
        cx: hub.x, cy: hub.y, r: n.r + 5, fill: 'none',
        stroke: n.lean === 'aggressor' ? 'rgba(224,70,107,.85)' : 'rgba(0,200,160,.85)',
        'stroke-width': 2, filter: 'url(#riv-glow)',
      }));
    }
    const cid = 'rivclip' + p.id;
    const cp  = svgEl('clipPath', { id: cid });
    cp.appendChild(svgEl('circle', { cx: hub.x, cy: hub.y, r: n.r }));
    defs.appendChild(cp);
    g.appendChild(svgEl('circle', { cx: hub.x, cy: hub.y, r: n.r, fill: '#0a1929' }));
    if (n.avatar) {
      g.appendChild(svgEl('image', {
        x: hub.x - n.r, y: hub.y - n.r, width: n.r * 2, height: n.r * 2,
        href: n.avatar, 'clip-path': `url(#${cid})`, preserveAspectRatio: 'xMidYMid slice',
      }));
    }
    g.appendChild(svgEl('circle', {
      cx: hub.x, cy: hub.y, r: n.r, fill: 'none', stroke: col, 'stroke-width': 2,
    }));

    /*
     * Radially outward, beyond the Crew -- and ANCHORED BY WHICH SIDE IT IS
     * ON. A centred label at the outward point still spreads half its width
     * back across the cluster, which on the left and right of the ring put
     * the username over its own portraits. Anchoring start on the right and
     * end on the left makes the text run AWAY from the cluster; only at the
     * top and bottom, where it runs along the tangent, does centred work.
     */
    /* p.anchor, NOT a second copy of the rule. The ink pass that sized the
       canvas measured this label assuming one anchor; computing it again here
       is how the canvas comes to be measured for a label other than the one
       that gets drawn. */
    const LR = p.g.outer + LABEL_GAP;
    const anchor = p.anchor;
    const LX = hub.x + Math.cos(p.ang) * LR;
    const LY = hub.y + Math.sin(p.ang) * LR + 4;
    const label = svgEl('text', {
      x: LX.toFixed(1), y: LY.toFixed(1), 'text-anchor': anchor,
      fill: '#cfe3ee', 'font-size': 11, 'font-family': 'Arial, sans-serif',
    });
    label.textContent = n.name;
    g.appendChild(label);

    const sub = svgEl('text', {
      x: LX.toFixed(1), y: (LY + 12).toFixed(1), 'text-anchor': anchor,
      fill: hexToRgba(col, 0.85), 'font-size': 9.5, 'font-family': 'Arial, sans-serif',
    });
    /* A CAPPED CREW SAYS SO. Without this a player who has fielded 25
       Fighters looks like one who has fielded 12, which is a quieter lie
       than it sounds: the size of the roster is the interesting thing
       about them. */
    sub.textContent = subText(n, p.g);
    g.appendChild(sub);

    const t = svgEl('title', {});
    t.textContent = `${n.name} \u00b7 ${n.battles} battles in the last ${D.days} days: `
      + `${n.att} attacking, ${n.def} defending. ${n.wins}W ${n.losses}L.`
      + (n.fighters.length
          ? ` ${n.fighters.length} Fighter${n.fighters.length === 1 ? '' : 's'} fielded`
            + (p.g.hidden ? `, the ${p.g.shown} most-used shown.` : '.')
          : '');
    g.appendChild(t);
    svg.appendChild(g);
  });

  host.innerHTML = '';
  host.appendChild(svg);

  /*
   * ---- FIT THE MAP TO WHAT IS ACTUALLY LEFT ON SCREEN ----
   *
   * The stylesheet caps the map at `calc(100vh - 330px)`, which is a guess at
   * how much page sits above and below it, and a guess is wrong twice: too
   * small and the map runs off the bottom, too large and it leaves a band of
   * dead space under the legend. 330 was too large, by about the height of
   * the legend again.
   *
   * Nothing has to guess. The map's own top edge says how much page is above
   * it, and its following siblings say how much is below -- the legend, and
   * the note about missing Fighters, which is there on some days and not
   * others. The leftover is the answer, and it is re-taken on resize.
   *
   * The CSS cap stays as the pre-script value so the box is never briefly
   * taller than the window before this runs.
   */
  function fit() {
    let after = 0;
    for (let el = host.nextElementSibling; el; el = el.nextElementSibling) {
      const st = window.getComputedStyle(el);
      after += el.offsetHeight + parseFloat(st.marginTop || 0) + parseFloat(st.marginBottom || 0);
    }
    /*
     * TWO MORE TERMS, BOTH FOUND BY MEASURING THE REAL PAGE rather than
     * reasoning about it, and each one produced its own small scrollbar:
     *
     *   the wrapper's bottom padding, which sits below the last sibling and
     *   so is invisible to the loop above -- 14px;
     *
     *   the map box's own padding and border, which sit BETWEEN the box edge
     *   and the drawing. The space measured here belongs to the box, but the
     *   cap is set on the svg inside it, so the box ends up that much taller
     *   than the room it was given -- another 12px.
     */
    const num  = (v) => parseFloat(v || 0) || 0;
    const wrap = host.parentElement;
    const pad  = wrap ? num(window.getComputedStyle(wrap).paddingBottom) : 0;
    const bs   = window.getComputedStyle(host);
    const box  = num(bs.paddingTop) + num(bs.paddingBottom)
               + num(bs.borderTopWidth) + num(bs.borderBottomWidth);
    const availH = Math.max(320, window.innerHeight - host.getBoundingClientRect().top
                                 - after - pad - box - 6);
    const availW = host.clientWidth - num(bs.paddingLeft) - num(bs.paddingRight);

    /*
     * ---- SCALE TO FIT, IN BOTH DIRECTIONS, INCLUDING UPWARDS ----
     *
     * This was max-height alone, with width:auto and a rule against ever
     * enlarging past the natural size. The reasoning was that blowing a
     * drawing up to fill a wide column coarsens it -- which is true of a
     * photograph and false of this. The canvas is sized to its own ink, so a
     * quiet month draws a SMALL one: 961x720 on the live map. Capping that at
     * a 1072px allowance changes nothing, and the page sat with three hundred
     * pixels of nothing under the legend while the map stayed postage-sized
     * in the middle of it.
     *
     * Nothing here loses by being enlarged. The shapes and the type are
     * vector. The only raster is the Fighter portraits, drawn from 250px
     * masters at radii of 15 to 30 -- so even at four times they are still
     * sampling below source resolution.
     *
     * So the scale is whichever axis runs out first, applied to both, which
     * preserves the aspect exactly and fills the space in either direction.
     */
    const natW = parseFloat(svg.getAttribute('width'))  || 1;
    const natH = parseFloat(svg.getAttribute('height')) || 1;
    const scale = Math.min(availW / natW, availH / natH);
    svg.style.width  = Math.floor(natW * scale) + 'px';
    svg.style.height = Math.floor(natH * scale) + 'px';
  }
  fit();
  /* Re-fit on resize, and once more after layout settles -- web fonts land
     after first paint and change how tall the heading and legend are. */
  window.addEventListener('resize', fit);
  if (document.fonts && document.fonts.ready) document.fonts.ready.then(fit);

  /*
   * HOVER A CLUSTER, LIGHT ITS GRUDGES. On a graph this dense the lines are
   * the hard part to read -- which of these strands is mine -- and dimming
   * everything else answers it without a click. Driven by a class on the
   * container so there is one source of truth for "something is focused", and
   * so a touch device that never fires mouseleave cannot leave the map stuck
   * dimmed: the next tap elsewhere clears it.
   */
  function focus(id) {
    host.classList.toggle('riv-focused', id != null);
    for (const ln of lines.children) {
      const on = id != null
        && (ln.getAttribute('data-lo') === String(id) || ln.getAttribute('data-hi') === String(id));
      ln.classList.toggle('riv-on', on);
    }
    svg.querySelectorAll('.riv-cluster').forEach(c => {
      c.classList.toggle('riv-dim', id != null && c.getAttribute('data-id') !== String(id)
        && !D.pairs.some(p => (String(p.lo) === String(id) && String(p.hi) === c.getAttribute('data-id'))
                           || (String(p.hi) === String(id) && String(p.lo) === c.getAttribute('data-id'))));
    });
  }
  svg.querySelectorAll('.riv-cluster').forEach(c => {
    c.addEventListener('mouseenter', () => focus(c.getAttribute('data-id')));
    c.addEventListener('mouseleave', () => focus(null));
  });

  /* ====================================================================
     CLICKING THINGS
     ==================================================================== */

  const esc = (t) => String(t == null ? '' : t)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  /*
   * A FIGHTER OPENS THE COLLECTION'S PANEL, not one of ours.
   *
   * dhc-fighter-modal.php is included on this page and ajax/dhc-fighter.php
   * returns exactly the payload it expects, keyed on serial -- the same pair
   * the Collection and the roster use. So a Fighter clicked here shows its
   * traits, its ranks and its proven record identically to everywhere else,
   * and none of that has to be known about here. A second panel repeating
   * most of it is how two surfaces start disagreeing about what a Fighter is.
   */
  let figBusy = false;
  function openFighter(serial) {
    if (!serial || figBusy || !window.DHC_MODAL) return;
    figBusy = true;
    fetch('ajax/dhc-fighter.php?serial=' + encodeURIComponent(serial), { credentials: 'same-origin' })
      .then(r => r.json())
      .then(d => { if (d && d.ok && d.fighter) window.DHC_MODAL.open(d.fighter); })
      .catch(() => {})
      .then(() => { figBusy = false; });
  }

  /* ---- the player panel ---- */
  const veil = document.getElementById('riv-veil');
  const pbody = document.getElementById('riv-body');

  function closePlayer() {
    if (veil) veil.hidden = true;
  }
  if (veil) {
    const x = document.getElementById('riv-close');
    if (x) x.addEventListener('click', closePlayer);
    /* The backdrop closes it; the sheet itself must not. */
    veil.addEventListener('click', (e) => { if (e.target === veil) closePlayer(); });
  }
  document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape' && veil && !veil.hidden) closePlayer();
  });

  /** A Fighter portrait as stacked <img>, the HTML counterpart of the SVG one. */
  function portrait(f) {
    if (!f.layers || !f.layers.length) return '';
    return f.layers.map(L => {
      const g = layerGeom(L, 100);
      const st = [];
      if (g.dy) st.push('transform:translateY(' + g.dy + '%)');
      if (g.cut) st.push('clip-path:inset(0 ' + (100 - g.cut.to) + '% 0 ' + g.cut.from + '%)');
      return '<img alt="" loading="lazy" src="' + esc(L.src) + '"'
           + (st.length ? ' style="' + st.join(';') + '"' : '') + '>';
    }).join('');
  }

  /**
   * Everything this page knows about one player, which is everything in
   * RIVALRY_DATA -- so the panel opens instantly with nothing to fetch.
   */
  function openPlayer(id) {
    const n = D.nodes[id];
    if (!n || !veil || !pbody) return;

    /* Their side of every pair they are in. The stored pair is keyed low-id
       to high-id and knows both directions, so reading it from one player's
       point of view is a matter of which end they are. */
    const mine = [];
    for (const p of D.pairs) {
      const isLo = String(p.lo) === String(id);
      if (!isLo && String(p.hi) !== String(id)) continue;
      const foe = D.nodes[isLo ? p.hi : p.lo];
      if (!foe) continue;
      mine.push({
        foe: foe,
        n: p.n,
        theyAttacked: isLo ? p.lohi : p.hilo,
        wereAttacked: isLo ? p.hilo : p.lohi,
        won:  isLo ? p.lowins : p.hiwins,
        lost: isLo ? p.hiwins : p.lowins,
      });
    }
    mine.sort((a, b) => b.n - a.n || b.won - a.won);

    const nemesis = mine.length ? mine[0] : null;
    /*
     * "BEST AGAINST" MUST ACTUALLY BE A WINNING RECORD. Taking the maximum
     * of (won - lost) returns something even when every record is losing, and
     * the panel then said "best against oculusorbus at 5-6" -- which is not a
     * best, it is the least bad. Same the other way for worst.
     */
    const deep  = mine.filter(m => m.n >= 3);
    const best  = deep.filter(m => m.won > m.lost)
                      .sort((a, b) => (b.won - b.lost) - (a.won - a.lost))[0];
    const worst = deep.filter(m => m.lost > m.won)
                      .sort((a, b) => (a.won - a.lost) - (b.won - b.lost))[0];

    let h = '<div class="riv-ph">'
      + (n.avatar ? '<img src="' + esc(n.avatar) + '" alt="">' : '')
      + '<div><h2 id="riv-pname">' + esc(n.name)
      + (n.lean === 'aggressor' ? '<span class="lean a">mostly attacking</span>' : '')
      + (n.lean === 'target'    ? '<span class="lean d">mostly attacked</span>' : '')
      + '</h2>'
      + '<a href="dhcgallery.php?owner=' + encodeURIComponent(n.id) + '">'
      + 'See every Fighter ' + esc(n.name) + ' has built &rsaquo;</a>'
      + '</div></div>';

    const pct = n.battles ? Math.round(100 * n.wins / n.battles) : 0;
    h += '<div class="riv-stats">'
      + '<div><b>' + n.battles.toLocaleString() + '</b><span>Battles, ' + D.days + 'd</span></div>'
      + '<div><b>' + n.wins + '\u2013' + n.losses + '</b><span>Record</span></div>'
      + '<div><b>' + pct + '%</b><span>Win rate</span></div>'
      + '<div><b>' + n.att.toLocaleString() + '</b><span>Attacking</span></div>'
      + '<div><b>' + n.def.toLocaleString() + '</b><span>Defending</span></div>'
      + '<div><b>' + mine.length + '</b><span>Rival' + (mine.length === 1 ? '' : 's') + '</span></div>'
      + '</div>';

    if (nemesis) {
      /* Sentences, each starting with a capital -- joined with '. ' these
         were running on as "11 times. best against...". */
      const bits = [];
      bits.push('Fights <b>' + esc(nemesis.foe.name) + '</b> most, ' + nemesis.n
              + ' time' + (nemesis.n === 1 ? '' : 's') + '.');
      if (best) {
        bits.push('Best against <b>' + esc(best.foe.name) + '</b> at '
                + best.won + '\u2013' + best.lost + '.');
      }
      if (worst && worst !== best) {
        bits.push('Worst against <b>' + esc(worst.foe.name) + '</b> at '
                + worst.won + '\u2013' + worst.lost + '.');
      }
      h += '<p class="riv-empty-note" style="margin:-8px 0 16px">' + bits.join(' ') + '</p>';
    }

    h += '<p class="riv-sect">Rivalries</p>';
    if (!mine.length) {
      h += '<p class="riv-empty-note">No settled battles against anyone in this window.</p>';
    } else {
      h += '<table class="riv-vs"><thead><tr><th>Opponent</th><th class="r">Battles</th>'
         + '<th class="r">They started</th><th class="r">Were attacked</th><th class="r">Record</th>'
         + '</tr></thead><tbody>';
      for (const m of mine) {
        const cls = m.won > m.lost ? 'won' : (m.won < m.lost ? 'lost' : '');
        h += '<tr>'
          + '<td><span class="who">'
          + (m.foe.avatar ? '<img src="' + esc(m.foe.avatar) + '" alt="">' : '')
          + esc(m.foe.name) + '</span></td>'
          + '<td class="r">' + m.n + '</td>'
          + '<td class="r">' + m.theyAttacked + '</td>'
          + '<td class="r">' + m.wereAttacked + '</td>'
          + '<td class="r ' + cls + '">' + m.won + '\u2013' + m.lost + '</td>'
          + '</tr>';
      }
      h += '</tbody></table>';
    }

    /*
     * THE WHOLE ROSTER, including the ones the map had no room for. The map
     * draws the CREW_MAX most-fielded and says "+N more"; this is where the
     * N live, because a panel can scroll and a ring cannot.
     */
    const crew = n.fighters || [];
    h += '<p class="riv-sect">Fighters fielded'
       + (crew.length ? ' \u00b7 ' + crew.length : '') + '</p>';
    if (!crew.length) {
      h += '<p class="riv-empty-note">No Fighters recorded yet. Which Fighters were in a '
         + 'Crew has only been kept since the participation ledger landed.</p>';
    } else {
      h += '<div class="riv-crew">';
      for (const f of crew) {
        h += '<button type="button" class="riv-fig" data-serial="' + esc(f.serial) + '">'
          + '<span class="art">' + portrait(f) + '</span>'
          + '<span class="nm">' + esc(f.name) + '</span>'
          + '<span class="st">' + f.battles + ' battle' + (f.battles === 1 ? '' : 's')
          + ' \u00b7 ' + f.dealt.toLocaleString() + ' dmg</span>'
          + '</button>';
      }
      h += '</div>';
    }

    pbody.innerHTML = h;
    veil.hidden = false;
    /* A Fighter in the roster opens the Collection's panel over this one. */
    pbody.querySelectorAll('.riv-fig').forEach(btn => {
      btn.addEventListener('click', () => openFighter(btn.getAttribute('data-serial')));
    });
    const x = document.getElementById('riv-close');
    if (x) x.focus();
  }

  /*
   * ONE CLICK HANDLER ON THE MAP. A Fighter opens its own panel and stops
   * there; anything else inside a cluster opens the player. Delegated rather
   * than bound per node, because the map is rebuilt wholesale on a redraw and
   * per-node listeners would have to be rebound with it.
   */
  host.addEventListener('click', (e) => {
    const t = e.target;
    const sat = t.closest ? t.closest('.riv-sat') : null;
    if (sat) { openFighter(sat.getAttribute('data-serial')); return; }
    const cl = t.closest ? t.closest('.riv-cluster') : null;
    if (cl) { openPlayer(cl.getAttribute('data-id')); return; }
    focus(null);
  });
})();
