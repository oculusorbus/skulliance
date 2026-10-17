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
  const host = document.getElementById('riv-map');
  if (!host) return;
  if (!D || !D.nodes || !Object.keys(D.nodes).length) return;   // the panel's own empty state stands

  const ids = Object.keys(D.nodes);
  const N   = ids.length;

  /* ---- cluster sizes -------------------------------------------------------
     A cluster has to hold the hub plus its Fighters without them overlapping,
     and placeMarkers will NOT refuse -- it lays the overflow on a circle round
     the centroid and lets them collide. So the box is sized from the Crew
     count here, which is the only place that can prevent it. */
  const SAT_R = 11;                       // a Fighter satellite's radius
  function boxFor(node) {
    const hub  = node.r;
    const sats = node.fighters.length;
    /* Enough ring room for the satellites at SAT_R*3 spacing, outside the hub.
       sqrt because they occupy a ring's area, not its circumference. */
    const need = hub + SAT_R * 3 * Math.max(1, Math.sqrt(sats));
    return Math.round(Math.max(hub * 2.6, need * 2) + 26);   // +26 for the name under it
  }

  /* ---- placing the clusters ------------------------------------------------
     A grid, ordered so the busiest player is central and heavy rivals sit near
     each other. Not a force simulation: with tens of nodes a greedy seriation
     reads just as well, costs nothing, and -- unlike a simulation -- lands in
     the same place every time.

     The order is: busiest first, then repeatedly whichever remaining player
     has the heaviest grudge with somebody already placed. That keeps feuds
     adjacent, so the thick lines stay short and the map does not turn into a
     star of long crossing strands. */
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

  const seq  = order();
  const cols = Math.ceil(Math.sqrt(N));
  const boxes = seq.map(id => boxFor(D.nodes[id]));
  const cell  = Math.max.apply(null, boxes);
  const GAP   = Math.round(cell * 0.22);

  /* Row-major, but serpentine: row 1 right-to-left, so a player at the end of
     one row sits above the start of the next rather than diagonally across the
     map from it. Adjacent in the ordering stays adjacent on screen. */
  const placed = [];
  seq.forEach((id, i) => {
    const row = Math.floor(i / cols);
    let col = i % cols;
    if (row % 2 === 1) col = cols - 1 - col;
    placed.push({
      id: id, node: D.nodes[id],
      x: GAP + col * (cell + GAP),
      y: GAP + row * (cell + GAP),
      w: cell, h: cell,
    });
  });

  const rows = Math.ceil(N / cols);
  const W = GAP + cols * (cell + GAP);
  const H = GAP + rows * (cell + GAP);

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

  const at = {};
  placed.forEach(p => { at[p.id] = { x: p.x + p.w / 2, y: p.y + p.h / 2, r: p.node.r }; });

  /* ---- the territories, under everything ---- */
  placed.forEach(p => {
    const rng  = mulberry32(hashStr('riv_' + p.id));
    const poly = chaikin(jitterRect(p.x + 8, p.y + 8, p.w - 16, p.h - 16, 16, rng), 3);
    p.poly = poly;
    const col = mapkitColorFor(p.id);
    svg.appendChild(svgEl('path', { d: toPath(poly), fill: hexToRgba(col, 0.05),
                                    stroke: hexToRgba(col, 0.3), 'stroke-width': 1 }));
  });

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
    const rng  = mulberry32(hashStr('riv_sat_' + p.id));
    const hub  = at[p.id];
    const busiest = n.fighters.reduce((m, f) => Math.max(m, f.battles), 0) || 1;
    /* A ring around the hub, not placeMarkers: these belong TO the avatar and
       should read as orbiting it, which a scatter inside the territory does
       not. The ring clears the hub, and a seeded offset keeps two neighbouring
       clusters from lining their satellites up identically. */
    const ringR = n.r + SAT_R * 2.1;
    /*
     * THE BOTTOM OF THE RING IS RESERVED FOR THE NAME. Two lines of label sit
     * directly under the avatar, and a full 360-degree ring drops a satellite
     * straight onto them -- it read as a dot punched through the username.
     * So the Fighters occupy the top 270 degrees, centred on straight up, and
     * the lower quadrant is left to the text. The seeded offset is a nudge
     * WITHIN that arc rather than a free rotation, or it would put a dot back
     * where the label is.
     */
    const SPAN  = Math.PI * 1.5;
    const START = -Math.PI / 2 - SPAN / 2;
    const off   = (rng() - 0.5) * 0.3;
    n.fighters.forEach((f, i) => {
      const a  = START + off + ((i + 0.5) / Math.max(1, n.fighters.length)) * SPAN;
      const fr = Math.max(4, Math.round(SAT_R * Math.sqrt(f.battles / busiest)));
      const cx = hub.x + Math.cos(a) * ringR, cy = hub.y + Math.sin(a) * ringR;
      const dot = svgEl('circle', {
        cx: cx.toFixed(1), cy: cy.toFixed(1), r: fr,
        fill: hexToRgba(col, 0.9), stroke: 'rgba(0,0,0,.6)', 'stroke-width': 1,
      });
      dot.setAttribute('class', 'riv-sat');
      const t = svgEl('title', {});
      /* Said in words. "4/1/0" would need a legend nobody reads. */
      t.textContent = `${f.name} — ${f.battles} battle${f.battles === 1 ? '' : 's'}`
        + ` (${f.att} attacking, ${f.def} defending), ${f.dealt.toLocaleString()} damage dealt`
        + (f.fell ? `, knocked out ${f.fell}×` : ', never knocked out');
      dot.appendChild(t);
      g.appendChild(dot);
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

    const label = svgEl('text', {
      x: hub.x, y: hub.y + n.r + 15, 'text-anchor': 'middle',
      fill: '#cfe3ee', 'font-size': 11, 'font-family': 'Arial, sans-serif',
    });
    label.textContent = n.name;
    g.appendChild(label);

    const sub = svgEl('text', {
      x: hub.x, y: hub.y + n.r + 27, 'text-anchor': 'middle',
      fill: hexToRgba(col, 0.85), 'font-size': 9.5, 'font-family': 'Arial, sans-serif',
    });
    sub.textContent = `${n.battles} battle${n.battles === 1 ? '' : 's'} · ${n.wins}W ${n.losses}L`;
    g.appendChild(sub);

    const t = svgEl('title', {});
    t.textContent = `${n.name} — ${n.battles} battles in the last ${D.days} days: `
      + `${n.att} attacking, ${n.def} defending. ${n.wins}W ${n.losses}L.`
      + (n.fighters.length ? ` ${n.fighters.length} Fighter${n.fighters.length === 1 ? '' : 's'} fielded.` : '');
    g.appendChild(t);
    svg.appendChild(g);
  });

  host.innerHTML = '';
  host.appendChild(svg);

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
  host.addEventListener('click', (e) => {
    const c = e.target.closest ? e.target.closest('.riv-cluster') : null;
    focus(c ? c.getAttribute('data-id') : null);
  });
})();
