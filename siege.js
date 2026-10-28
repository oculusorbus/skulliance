/*
 * siege.js -- the DHC Bosses Siege map.
 *
 * The boss crew in the middle, everyone who attacked it ringed around. Every
 * element carries something:
 *
 *   the centre   the week's waves as nested shells, each sized on the health
 *                it was actually budgeted. Wave 1 is the fat outer ring and
 *                the late waves collapse inward exactly as fast as they get
 *                cheaper to drop, because a wave is budgeted against the
 *                DAYS LEFT. The live one is drawn with its bosses in it.
 *   a cluster    one player, sized on the damage they put into the bar,
 *                with the Fighters they fielded drawn around them.
 *   a spoke      which shells that player was present for. Every line here
 *                points at the same centre, so the line alone would say only
 *                "took part"; the waves it crosses are when they were here.
 *   a mark       who landed the last blow on a boss, on that boss's shell.
 *
 * WAVES ARE THE TIME AXIS, NOT A RANK. Wave 1 stands for about 4.2 of the
 * week's 7 days and every wave after it falls in under one, so the wave a
 * player reached is mostly a statement about when they logged in. Nothing
 * here sizes, orders or colours a player by it.
 *
 * READS window.SIEGE_DATA, from dhcb_siege_layout(). The radii, the shell
 * spacing and the per-week summary all arrive done, because they are
 * assertions about the data and belong where they can be tested without a
 * browser. This file decides only WHERE things go.
 *
 * SHARES map-kit.js AND dhc-crew-kit.js with the Rivalries map, both loaded
 * first. What differs between the two maps is only what sits in the middle.
 */
(function () {
  const D   = window.SIEGE_DATA;
  const KIT = window.DHCCrewKit;
  const ARM_SPLIT = window.SIEGE_ARM_SPLIT || 50;
  const ME  = window.SIEGE_ME || 0;
  const host = document.getElementById('sg-map');
  if (!host || !KIT) return;
  if (!D || !D.nodes || !Object.keys(D.nodes).length) return;   // the page's empty state stands

  const ids = Object.keys(D.nodes);
  const N   = ids.length;
  const WEIGHT = f => f.dealt;

  const SAT_MAX = 26, CREW_MAX = 10, PER_SHELL = 5;
  const CREW_SPAN = Math.PI * 0.9;
  const LABEL_GAP = 16, GAP = 24;
  const FONT = 'Arial, sans-serif';

  const ink = new KIT.Ink();
  const geom = node => KIT.crewGeom(node, {
    max: CREW_MAX, perShell: PER_SHELL, satMax: SAT_MAX, span: CREW_SPAN, weight: WEIGHT,
  });

  /* ---- the centre ---------------------------------------------------------
     Shells on the week view, a column of weeks on the wider ones. Either way
     it occupies a disc of CORE radius, and the ring of players clears it. */
  const shells = D.shells || [];
  const byWeek = D.byWeek || [];
  const CORE = shells.length ? shells[0].r + 14
             : Math.max(70, 26 + byWeek.length * 13);

  /* ---- the ring -----------------------------------------------------------
     Heaviest first and running clockwise from the top, so the eye lands on
     the player carrying the week. No seriation: there are no chords between
     clusters to shorten, only spokes to the middle, so neighbours on the
     ring have no relationship to order by. */
  const seq = ids.slice().sort((a, b) =>
    D.nodes[b].dealt - D.nodes[a].dealt || String(a).localeCompare(String(b)));
  const G = {};
  seq.forEach(id => { G[id] = geom(D.nodes[id]); });

  function subText(n, g) {
    return `${n.dealt.toLocaleString()} dmg · ${n.per.toLocaleString()}/fighter`
         + (g.hidden ? ` · +${g.hidden} more` : '');
  }

  /*
   * THE RADIUS COMES FROM THE CIRCUMFERENCE THE CLUSTERS NEED, and then has
   * to clear the centre as well. Rivalries only has the first constraint
   * because its middle is empty; here a small turnout would otherwise pull
   * the ring in on top of the shells.
   */
  const demand = seq.map(id => 2 * G[id].outer + GAP);
  const needed = demand.reduce((t, d) => t + d, 0);
  const maxOut = Math.max.apply(null, seq.map(id => G[id].outer));
  const R = Math.max(CORE + maxOut + 18, maxOut + 40, needed / (2 * Math.PI));

  const used  = needed / (R || 1);
  const slack = Math.max(0, Math.PI * 2 - used) / N;
  let acc = 0;
  const placed = seq.map((id, i) => {
    const slice = demand[i] / (R || 1) + slack;
    const a = (-Math.PI / 2 + acc + slice / 2);
    acc += slice;
    const g = G[id], n = D.nodes[id];
    const x = Math.cos(a) * R, y = Math.sin(a) * R;
    /* The Crew faces outward: the inward side of a hub is where the spoke
       arrives, and a portrait there sits on top of the thing it explains. */
    const sats = KIT.crewPlaces(x, y, a, n, g, WEIGHT);

    ink.disc(x, y, n.r + 5);
    sats.forEach(sv => ink.disc(sv.x, sv.y, sv.r));

    const cosA = Math.cos(a);
    const anchor = cosA > 0.3 ? 'start' : (cosA < -0.3 ? 'end' : 'middle');
    const lw = Math.max(
      measureTextWidth(n.name, 11, FONT, 'normal', '0'),
      measureTextWidth(subText(n, g), 9.5, FONT, 'normal', '0'));
    const lx = x + cosA * (g.outer + LABEL_GAP);
    const ly = y + Math.sin(a) * (g.outer + LABEL_GAP) + 4;
    const x0 = anchor === 'start' ? lx : (anchor === 'end' ? lx - lw : lx - lw / 2);
    ink.add(x0, ly - 10, x0 + lw, ly + 16);

    return { id: id, node: n, g: g, ang: a, anchor: anchor, x: x, y: y, sats: sats };
  });
  /* The centre is ink too, and on a one-player week it is the widest of it. */
  ink.disc(0, 0, CORE);

  const EDGE = 10;
  const F = ink.frame(EDGE);
  placed.forEach(p => {
    p.x += F.ox; p.y += F.oy;
    p.sats.forEach(sv => { sv.x += F.ox; sv.y += F.oy; });
  });
  const CX = F.ox, CY = F.oy;          // the centre, shifted with everything else
  const W = F.w, H = F.h;

  const svg = svgEl('svg', { width: W, height: H, viewBox: `0 0 ${W} ${H}` });
  svg.setAttribute('class', 'sg-svg');
  const defs = svgEl('defs');
  svg.appendChild(defs);

  const glow = svgEl('filter', { id: 'sg-glow', x: '-30%', y: '-30%', width: '160%', height: '160%' });
  glow.appendChild(svgEl('feGaussianBlur', { in: 'SourceGraphic', stdDeviation: '3', result: 'b' }));
  const gm = svgEl('feMerge');
  gm.appendChild(svgEl('feMergeNode', { in: 'b' }));
  gm.appendChild(svgEl('feMergeNode', { in: 'SourceGraphic' }));
  glow.appendChild(gm);
  defs.appendChild(glow);

  /* ---- 1. the spokes, under everything ------------------------------------
     Drawn first so a portrait is never crossed by a line. A spoke runs from
     the cluster inward to the outermost shell that player was present for --
     so its LENGTH is when they arrived and its reach is how long they
     stayed. With no shells (the wider windows) it simply reaches the core. */
  const lines = svgEl('g', {});
  svg.appendChild(lines);
  placed.forEach(p => {
    const n = p.node;
    const waves = n.waves || {};
    const present = Object.keys(waves).map(Number).sort((a, b) => a - b);
    /* Which shell to stop at: the innermost wave they fought. Shells are
       ordered outermost first, so that is the smallest radius among them. */
    let stop = CORE;
    if (shells.length && present.length) {
      let r = Infinity;
      shells.forEach(sh => { if (waves[sh.wave] && sh.r < r) r = sh.r; });
      if (r !== Infinity) stop = r;
    }
    const dx = CX - p.x, dy = CY - p.y;
    const len = Math.sqrt(dx * dx + dy * dy) || 1;
    const ux = dx / len, uy = dy / len;
    const x1 = p.x + ux * (n.r + 2), y1 = p.y + uy * (n.r + 2);
    const x2 = CX - ux * stop,       y2 = CY - uy * stop;
    /* Weight by damage, so the line itself says how much went down it. */
    const wgt = D.biggest > 0 ? Math.sqrt(n.dealt / D.biggest) : 0;
    const line = svgEl('line', {
      x1: x1.toFixed(1), y1: y1.toFixed(1), x2: x2.toFixed(1), y2: y2.toFixed(1),
      stroke: n.kills && n.kills.length ? 'rgba(245,166,35,.75)' : 'rgba(0,200,160,.38)',
      'stroke-width': (1 + 4 * wgt).toFixed(2), 'stroke-linecap': 'round',
    });
    const t = svgEl('title', {});
    t.textContent = n.name + ' · ' + n.dealt.toLocaleString() + ' damage in '
      + n.runs + ' Crew' + (n.runs === 1 ? '' : 's')
      + (present.length ? ', waves ' + present.join(', ') : '');
    line.appendChild(t);
    lines.appendChild(line);
  });

  /* ---- 2. the centre ------------------------------------------------------ */
  const core = svgEl('g', {});
  core.setAttribute('class', 'sg-core');
  svg.appendChild(core);

  if (shells.length) {
    /* Outermost first, so a later wave draws over an earlier one. */
    shells.forEach(sh => {
      const live = sh.left > 0;
      core.appendChild(svgEl('circle', {
        cx: CX, cy: CY, r: sh.r, fill: 'none',
        stroke: live ? 'rgba(224,70,107,.9)' : 'rgba(122,158,176,.30)',
        'stroke-width': live ? 2 : 1,
        'stroke-dasharray': live ? '' : '3 4',
      }));
      /* How far this wave got, as an arc on its own ring -- a wave that was
         cleared is a full circle, the live one is the share already taken
         off its bars. */
      if (live && sh.max > 0) {
        const done = Math.max(0, Math.min(1, (sh.max - sh.left) / sh.max));
        if (done > 0.001) {
          const C = 2 * Math.PI * sh.r;
          core.appendChild(svgEl('circle', {
            cx: CX, cy: CY, r: sh.r, fill: 'none', stroke: '#00c8a0', 'stroke-width': 4,
            'stroke-dasharray': (C * done).toFixed(1) + ' ' + (C * (1 - done)).toFixed(1),
            transform: `rotate(-90 ${CX} ${CY})`, filter: 'url(#sg-glow)',
          }));
        }
      }
      const wt = svgEl('title', {});
      wt.textContent = 'Wave ' + sh.wave + ' · ' + sh.max.toLocaleString() + ' health'
        + (live ? ', ' + sh.left.toLocaleString() + ' still standing' : ', down');
      core.appendChild(wt);

      /* The wave's number, on the ring at the top. */
      const lab = svgEl('text', {
        x: CX, y: (CY - sh.r + 3.5).toFixed(1), 'text-anchor': 'middle',
        'font-size': 8.5, fill: live ? '#e0466b' : '#4f7488', 'font-family': FONT,
      });
      lab.textContent = sh.wave;
      core.appendChild(lab);

      /* WHO LANDED THE LAST BLOW, on the shell that boss died on. Fifteen
         marks a week, all of them already recorded in dhc_boss_day and shown
         nowhere until now. */
      (sh.bosses || []).forEach(b => {
        if (!b.dead || !b.by) return;
        const at = placed.filter(p => String(p.id) === String(b.by))[0];
        if (!at) return;
        const ang = Math.atan2(at.y - CY, at.x - CX);
        const mx = CX + Math.cos(ang) * sh.r, my = CY + Math.sin(ang) * sh.r;
        const m = svgEl('circle', {
          cx: mx.toFixed(1), cy: my.toFixed(1), r: 3.4,
          fill: '#f5a623', stroke: '#07111d', 'stroke-width': 1,
        });
        const mt = svgEl('title', {});
        mt.textContent = b.name + ' · wave ' + sh.wave + ' · last blow by '
          + (D.nodes[b.by] ? D.nodes[b.by].name : 'someone no longer on the board');
        m.appendChild(mt);
        core.appendChild(m);
      });
    });

    /* The live crew's own portraits, inside the innermost shell. */
    const liveWave = shells.filter(s => s.left > 0).pop();
    if (liveWave && liveWave.bosses.length) {
      const inner = liveWave.r;
      const br = Math.max(11, Math.min(22, Math.round(inner * 0.46)));
      liveWave.bosses.forEach((b, i) => {
        const n = liveWave.bosses.length;
        const ang = -Math.PI / 2 + (i / n) * Math.PI * 2;
        const rr = n === 1 ? 0 : Math.max(0, inner - br - 3) * 0.55;
        const bx = CX + Math.cos(ang) * rr, by = CY + Math.sin(ang) * rr;
        const cid = 'sgboss' + i;
        const cp = svgEl('clipPath', { id: cid });
        cp.appendChild(svgEl('circle', { cx: bx.toFixed(1), cy: by.toFixed(1), r: br - 1 }));
        defs.appendChild(cp);
        core.appendChild(svgEl('circle', {
          cx: bx.toFixed(1), cy: by.toFixed(1), r: br,
          fill: '#07111d', stroke: b.dead ? 'rgba(122,158,176,.5)' : 'rgba(224,70,107,.9)',
          'stroke-width': 1.5,
        }));
        const box = svgEl('g', { 'clip-path': 'url(#' + cid + ')' });
        box.appendChild(svgEl('image', {
          x: (bx - br).toFixed(1), y: (by - br).toFixed(1),
          width: (br * 2).toFixed(1), height: (br * 2).toFixed(1),
          href: b.art, preserveAspectRatio: 'xMidYMid slice',
          opacity: b.dead ? 0.35 : 1,
        }));
        core.appendChild(box);
        const bt = svgEl('title', {});
        bt.textContent = b.name + ' · ' + (b.dead ? 'down'
          : b.left.toLocaleString() + ' / ' + b.max.toLocaleString());
        core.appendChild(bt);
      });
    }
  } else {
    /*
     * THE WIDER WINDOWS GET WEEKS, NOT SHELLS. Wave numbers restart every
     * Thursday, so thirteen weeks is thirteen wave 1s and nesting them would
     * draw a structure that never existed. One mark per week, how deep it
     * got, oldest at the bottom -- the trend across weeks rather than the
     * structure within one.
     */
    const n = byWeek.length || 1;
    const top = byWeek.reduce((m, w) => Math.max(m, w.top), 1);
    const colW = Math.min(16, (CORE * 1.5) / n);
    const x0 = CX - (n * colW) / 2;
    byWeek.forEach((w, i) => {
      const h = Math.max(4, Math.round((w.top / top) * CORE * 1.1));
      const bx = x0 + i * colW;
      core.appendChild(svgEl('rect', {
        x: bx.toFixed(1), y: (CY + CORE * 0.55 - h).toFixed(1),
        width: Math.max(2, colW - 3).toFixed(1), height: h,
        fill: w.downed > 0 ? 'rgba(0,200,160,.65)' : 'rgba(122,158,176,.45)',
      }));
      const t = svgEl('title', {});
      t.textContent = 'Week of ' + w.day + ' · reached wave ' + w.top
        + ' · ' + w.downed + ' crew' + (w.downed === 1 ? '' : 's') + ' down';
      core.appendChild(t);
    });
    const cap = svgEl('text', {
      x: CX, y: (CY + CORE * 0.55 + 14).toFixed(1), 'text-anchor': 'middle',
      'font-size': 9, fill: '#4f7488', 'font-family': FONT,
    });
    cap.textContent = byWeek.length + ' week' + (byWeek.length === 1 ? '' : 's');
    core.appendChild(cap);
  }

  /* ---- 3. the clusters ---------------------------------------------------- */
  placed.forEach(p => {
    const n = p.node;
    const g = svgEl('g', {});
    g.setAttribute('class', 'sg-cluster');
    g.setAttribute('data-id', p.id);

    p.sats.forEach(sv => {
      const f = sv.f;
      const sg = KIT.drawPortrait(sv, {
        svgEl: svgEl, defs: defs, uid: 'sgsat' + p.id + '_' + f.id,
        ring: 'rgba(0,200,160,.6)', ground: '#0a1929', armSplit: ARM_SPLIT,
      });
      sg.setAttribute('class', 'sg-sat');
      sg.setAttribute('data-fid', f.id);
      /* The serial too: ajax/dhc-fighter.php is keyed on it, and that is how
         a click opens the Collection's panel. */
      sg.setAttribute('data-serial', f.serial);
      const t = svgEl('title', {});
      t.textContent = f.name + ' · ' + f.dealt.toLocaleString() + ' damage in '
        + f.runs + ' Crew' + (f.runs === 1 ? '' : 's')
        + (f.fell ? ', knocked out ' + f.fell + '×' : ', never knocked out');
      sg.appendChild(t);
      g.appendChild(sg);
    });

    /* A ring on anyone who landed a last blow. Said in the legend, because a
       colour alone is a code nobody reads. */
    if (n.kills && n.kills.length) {
      g.appendChild(svgEl('circle', {
        cx: p.x.toFixed(1), cy: p.y.toFixed(1), r: n.r + 5, fill: 'none',
        stroke: 'rgba(245,166,35,.9)', 'stroke-width': 2, filter: 'url(#sg-glow)',
      }));
    }
    const cid = 'sgav' + p.id;
    const cp  = svgEl('clipPath', { id: cid });
    cp.appendChild(svgEl('circle', { cx: p.x.toFixed(1), cy: p.y.toFixed(1), r: n.r }));
    defs.appendChild(cp);
    g.appendChild(svgEl('circle', {
      cx: p.x.toFixed(1), cy: p.y.toFixed(1), r: n.r, fill: '#0a1929',
    }));
    if (n.avatar) {
      g.appendChild(svgEl('image', {
        x: (p.x - n.r).toFixed(1), y: (p.y - n.r).toFixed(1),
        width: (n.r * 2).toFixed(1), height: (n.r * 2).toFixed(1),
        href: n.avatar, preserveAspectRatio: 'xMidYMid slice',
        'clip-path': 'url(#' + cid + ')',
      }));
    }
    if (ME && String(ME) === String(p.id)) {
      g.appendChild(svgEl('circle', {
        cx: p.x.toFixed(1), cy: p.y.toFixed(1), r: n.r + 2, fill: 'none',
        stroke: '#e8eaed', 'stroke-width': 1.5, 'stroke-dasharray': '3 3',
      }));
    }
    const ht = svgEl('title', {});
    ht.textContent = n.name + ' · ' + n.dealt.toLocaleString() + ' damage, '
      + n.per.toLocaleString() + ' per Fighter · ' + n.runs + ' Crew'
      + (n.runs === 1 ? '' : 's') + ' over ' + n.rounds + ' round'
      + (n.rounds === 1 ? '' : 's')
      + (n.kills && n.kills.length
         ? ' · landed ' + n.kills.length + ' last blow' + (n.kills.length === 1 ? '' : 's')
         : '');
    g.appendChild(ht);

    /* The label, radially outside the cluster. */
    const cosA = Math.cos(p.ang);
    const lx = p.x + cosA * (p.g.outer + LABEL_GAP);
    const ly = p.y + Math.sin(p.ang) * (p.g.outer + LABEL_GAP) + 4;
    const t1 = svgEl('text', {
      x: lx.toFixed(1), y: ly.toFixed(1), 'text-anchor': p.anchor,
      'font-size': 11, fill: '#e8eaed', 'font-family': FONT,
    });
    t1.textContent = n.name;
    g.appendChild(t1);
    const t2 = svgEl('text', {
      x: lx.toFixed(1), y: (ly + 12).toFixed(1), 'text-anchor': p.anchor,
      'font-size': 9.5, fill: '#7a9eb0', 'font-family': FONT,
    });
    t2.textContent = subText(n, p.g);
    g.appendChild(t2);

    svg.appendChild(g);
  });

  host.innerHTML = '';
  host.appendChild(svg);
})();
