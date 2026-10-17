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
  const SAT_MIN = 15, SAT_MAX = 30;      // Fighter portrait radius
  /*
   * THE CREW OCCUPIES THE OUTER HALF of its hub, a little under 180 degrees.
   * Anything wider tips the end portraits back inward, where the grudge lines
   * arrive and the name sits. 0.9pi leaves both ends comfortably outward
   * instead of exactly on the boundary.
   */
  const CREW_SPAN = Math.PI * 0.9;
  function satR(f, busiest) {
    return Math.round(SAT_MIN + (SAT_MAX - SAT_MIN) * Math.sqrt(f.battles / (busiest || 1)));
  }
  function geom(node) {
    const k = node.fighters.length;
    const busiest = node.fighters.reduce((m, f) => Math.max(m, f.battles), 0) || 1;
    const big = k ? Math.max.apply(null, node.fighters.map(f => satR(f, busiest))) : 0;
    /*
     * The portraits sit on a ring clear of the avatar -- and that ring also
     * has to be long enough to hold them side by side. k portraits spread
     * over CREW_SPAN are ring*SPAN/(k-1) apart, which must clear 2*big or
     * they overlap. Taking the larger of the two is the only thing preventing
     * a deep Crew from drawing itself as a pile.
     */
    const clear = k > 1 ? (2 * big * (k - 1)) / CREW_SPAN : 0;
    const ring  = k ? Math.max(node.r + 6 + big, clear) : 0;
    return { busiest: busiest, ring: ring, outer: Math.max(node.r, ring + big) + 2 };
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
  const LABEL = 76, GAP = 26;
  const outer = seq.map(id => G[id].outer);
  const maxOut = Math.max.apply(null, outer);

  /*
   * THE RADIUS COMES FROM THE CIRCUMFERENCE THE CLUSTERS NEED, not from a
   * guess. Each wants 2*outer plus a gap of arc to itself, so the ring is big
   * enough when its circumference covers the sum -- which handles wildly
   * uneven cluster sizes without leaving a hole where a small one sits.
   */
  const needed = outer.reduce((t, o) => t + 2 * o + GAP, 0);
  const R = N === 1 ? 0 : Math.max(maxOut + 40, needed / (2 * Math.PI));

  const PAD = maxOut + LABEL + 20;
  const W = Math.round(2 * (R + PAD));
  const H = Math.round(2 * (R + PAD));
  const CX = W / 2, CY = H / 2;

  /* Start at the top and run clockwise, so the busiest player -- first in the
     seriation -- is where the eye lands. */
  const placed = seq.map((id, i) => {
    const a = -Math.PI / 2 + (i / N) * Math.PI * 2;
    return {
      id: id, node: D.nodes[id], g: G[id], ang: a,
      x: CX + Math.cos(a) * R,
      y: CY + Math.sin(a) * R,
    };
  });

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
    const mid = p.ang;                        // the outward direction
    n.fighters.forEach((f, i) => {
      const t0 = n.fighters.length === 1 ? 0.5 : i / (n.fighters.length - 1);
      const a  = mid - CREW_SPAN / 2 + t0 * CREW_SPAN;
      const fr = satR(f, p.g.busiest);
      const cx = hub.x + Math.cos(a) * p.g.ring, cy = hub.y + Math.sin(a) * p.g.ring;

      const sg = svgEl('g', {});
      sg.setAttribute('class', 'riv-sat');
      sg.setAttribute('data-fid', f.id);
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
          const attrs = {
            x: X.toFixed(1), y: (Y + (L.n || 0) / 100 * S).toFixed(1),
            width: S.toFixed(1), height: S.toFixed(1),
            href: L.src, preserveAspectRatio: 'xMidYMid meet',
          };
          /* A single-sided arm is the two-arm master with half cut away. The
             Collection cuts it with a CSS inset(); a clipPath rect is the same
             cut and does not rely on CSS applying to an SVG <image>. */
          if (L.clip === 'left' || L.clip === 'right') {
            const hid = cid + '_a' + li;
            const hcp = svgEl('clipPath', { id: hid });
            hcp.appendChild(svgEl('rect', {
              x: (L.clip === 'right' ? X + S * (ARM_SPLIT / 100) : X).toFixed(1),
              y: Y.toFixed(1),
              width: (S * (L.clip === 'right' ? (100 - ARM_SPLIT) : ARM_SPLIT) / 100).toFixed(1),
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
    const LR = p.g.outer + 17;
    const cosA = Math.cos(p.ang);
    const anchor = cosA > 0.3 ? 'start' : (cosA < -0.3 ? 'end' : 'middle');
    const LX = hub.x + cosA * LR;
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
    sub.textContent = `${n.battles} battle${n.battles === 1 ? '' : 's'} · ${n.wins}W ${n.losses}L`;
    g.appendChild(sub);

    const t = svgEl('title', {});
    t.textContent = `${n.name} \u00b7 ${n.battles} battles in the last ${D.days} days: `
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
