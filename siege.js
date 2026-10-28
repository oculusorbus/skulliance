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
  /*
   * ONE NUMBER FOR HOW MUCH ROOM THE CENTRE TAKES, whichever centre it is.
   *
   * The wide view's week bars hang below the crew, and the first cut
   * measured them separately -- a second ink call that had to be kept in
   * step with the drawing by hand, and which no fixture could be built to
   * fail. Deriving CORE from the bars instead means the one tested line,
   * ink.disc(0, 0, CORE), covers everything the middle draws.
   */
  const WIDE = (function () {
    const inner  = Math.max(34, Math.round(22 + byWeek.length * 4));
    const barMax = Math.round(inner * 0.6);
    const n      = byWeek.length || 1;
    const colW   = Math.min(14, Math.max(5, (inner * 3) / n));
    return { inner: inner, barMax: barMax, colW: colW, n: n,
             /* crew, gap, bars, then the caption under them */
             below: inner + 12 + barMax + 20,
             half:  Math.max(inner + 8, (n * colW) / 2 + 6) };
  })();
  const CORE = shells.length ? shells[0].r + 14
             : Math.max(WIDE.below, WIDE.half);

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
  /*
   * THE CENTRE IS INK TOO, and on a quiet week it is the widest of it.
   *
   * MEASURED BEFORE THE FRAME IS TAKEN, which is the only moment it counts.
   * The first cut of the wide view measured its week bars AFTER
   * ink.frame() had already been called -- dead code that read as
   * protection and gave none. The bars hang below the core disc by more
   * than its own radius on a small core, so this is not theoretical.
   */
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
    /* Whose line this is, so hover can isolate it. One spoke per player, so
       the id is enough -- there are no shared edges here the way the Arena
       map has pairs. */
    line.setAttribute('class', 'sg-line');
    line.setAttribute('data-id', p.id);
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

  /*
   * THE CREW, DRAWN IN THE MIDDLE. Shared by both branches: the week view
   * passes the live wave's bosses and its innermost radius, the wider views
   * pass the static crew. The wider ones used to pass nothing at all, which
   * left every spoke on the map pointing at a bar chart with no bosses in
   * it -- reported by the owner on the first 4-week view.
   */
  function drawCrew(bosses, inner, tip) {
    const br = Math.max(11, Math.min(22, Math.round(inner * 0.46)));
    bosses.forEach((b, i) => {
      const n = bosses.length;
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
      bt.textContent = tip(b);
      core.appendChild(bt);
    });
  }

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

    /* The live crew's own portraits, inside the innermost shell -- which the
       cumulative sizing guarantees IS the live wave, since the health summed
       from the last wave onward is the smallest sum there is. */
    const liveWave = shells.filter(s2 => s2.left > 0).pop();
    if (liveWave && liveWave.bosses.length)
      drawCrew(liveWave.bosses, liveWave.r, b => b.name + ' \u00b7 '
        + (b.dead ? 'down' : b.left.toLocaleString() + ' / ' + b.max.toLocaleString()));
  } else {
    /*
     * THE WIDER WINDOWS GET WEEKS, NOT SHELLS. Wave numbers restart every
     * Thursday, so thirteen weeks is thirteen wave 1s and nesting them would
     * draw a structure that never existed. One mark per week, how deep it
     * got, oldest at the bottom -- the trend across weeks rather than the
     * structure within one.
     */
    /* The crew first, at the middle, so the spokes still point at something.
       Sized off a nominal inner radius since there is no shell to sit in. */
    /* FROM WIDE, not re-derived: these are the numbers CORE was computed
       from, so the thing measured is literally the thing drawn. */
    const inner = WIDE.inner, barMax = WIDE.barMax, colW = WIDE.colW, n = WIDE.n;
    core.appendChild(svgEl('circle', {
      cx: CX, cy: CY, r: inner, fill: 'none',
      stroke: 'rgba(224,70,107,.55)', 'stroke-width': 1.5,
    }));
    drawCrew(D.crew || [], inner, b => b.name);

    /* Then one bar per week, in a row UNDER them rather than through them. */
    const top = byWeek.reduce((m, w) => Math.max(m, w.top), 1);
    const x0 = CX - (n * colW) / 2;
    const base = CY + inner + 12 + barMax;
    byWeek.forEach((w, i) => {
      const h = Math.max(4, Math.round((w.top / top) * barMax));
      const bx = x0 + i * colW;
      core.appendChild(svgEl('rect', {
        x: bx.toFixed(1), y: (base - h).toFixed(1),
        width: Math.max(2, colW - 3).toFixed(1), height: h,
        fill: w.downed > 0 ? 'rgba(0,200,160,.65)' : 'rgba(122,158,176,.45)',
      }));
      const t = svgEl('title', {});
      t.textContent = 'Week of ' + w.day + ' \u00b7 reached wave ' + w.top
        + ' \u00b7 ' + w.downed + ' crew' + (w.downed === 1 ? '' : 's') + ' down';
      core.appendChild(t);
    });
    /*
     * THE CAPTION NAMES THE WINDOW, NOT THE BARS. It said "1 week" under a
     * four-week window whenever only one of those weeks had runs in it,
     * which reads as contradicting the button just pressed. The window is
     * one fact and how many of its weeks were played is another; both are
     * worth saying and neither can stand for the other.
     */
    const cap = svgEl('text', {
      x: CX, y: (base + 14).toFixed(1), 'text-anchor': 'middle',
      'font-size': 9, fill: '#4f7488', 'font-family': FONT,
    });
    cap.textContent = D.weeks + ' weeks \u00b7 ' + byWeek.length + ' played';
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
      /* A DISASSEMBLED FIGHTER IS DRAWN AS ONE. dhcf_delete_fighter() really
         removes the row, so there is no art to stack and the kit draws the
         disc and stops. Left at the normal ring that reads as a broken
         image, which is how the owner found it. Dashed and dimmed says the
         Fighter is gone, which is true; the damage it dealt is real and
         stays in the player's total either way. */
      const sg = KIT.drawPortrait(sv, {
        svgEl: svgEl, defs: defs, uid: 'sgsat' + p.id + '_' + f.id,
        ring: f.gone ? 'rgba(122,158,176,.55)' : 'rgba(0,200,160,.6)',
        ground: '#0a1929', armSplit: ARM_SPLIT,
      });
      if (f.gone && sg.children && sg.children[0] && sg.children[0].setAttribute)
        sg.children[0].setAttribute('stroke-dasharray', '3 3');
      sg.setAttribute('class', f.gone ? 'sg-sat sg-sat-gone' : 'sg-sat');
      sg.setAttribute('data-fid', f.id);
      /* The serial too: ajax/dhc-fighter.php is keyed on it, and that is how
         a click opens the Collection's panel. */
      sg.setAttribute('data-serial', f.serial);
      const t = svgEl('title', {});
      t.textContent = (f.gone ? 'A Fighter since disassembled' : f.name)
        + ' · ' + f.dealt.toLocaleString() + ' damage in '
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

  /* ====================================================================
     HOVER: ISOLATE ONE ATTACKER
     ====================================================================

     The Arena map dims the clusters a hovered player has NOT fought and
     marches the lines they have. Here every line runs to the same centre,
     so "connected" is not a question -- what a hover answers instead is
     "which of these is mine", which on a busy week is the only way to pick
     one attacker out of twenty.

     HOVERED AND NOT THE ONE: neither class, never both. A CSS animation
     beats an ordinary declaration, so a dimmed line still running the march
     keeps animating its own opacity and the dimming never lands -- the map
     would highlight without ever dimming. rivalries.js records the same
     trap; it is not tidiness, it is the only way the dim applies.
  */
  const lineEls = [];
  const clusterEls = [];
  (function collect(el) {
    const c = el.getAttribute && el.getAttribute('class');
    if (c === 'sg-line') lineEls.push(el);
    if (c === 'sg-cluster') clusterEls.push(el);
    (el.children || []).forEach ? Array.prototype.forEach.call(el.children, collect) : null;
  })(svg);

  function focus(id) {
    const idle = (id == null);
    lineEls.forEach(ln => {
      const mine = !idle && String(ln.getAttribute('data-id')) === String(id);
      ln.classList.toggle('sg-march', mine);
      ln.classList.toggle('sg-dim', !idle && !mine);
    });
    clusterEls.forEach(c => {
      c.classList.toggle('sg-dim', !idle && String(c.getAttribute('data-id')) !== String(id));
    });
    /* The centre never dims: it is what every one of them is attacking, and
       fading it would hide the thing the hovered line is pointing at. */
  }
  clusterEls.forEach(c => {
    c.addEventListener('mouseenter', () => focus(c.getAttribute('data-id')));
    c.addEventListener('mouseleave', () => focus(null));
  });
  lineEls.forEach(ln => {
    ln.addEventListener('mouseenter', () => focus(ln.getAttribute('data-id')));
    ln.addEventListener('mouseleave', () => focus(null));
  });

  /* ====================================================================
     CLICKING THINGS
     ==================================================================== */

  const esc = t => String(t == null ? '' : t)
    .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');

  /*
   * A FIGHTER OPENS THE COLLECTION'S PANEL, not one of ours.
   *
   * dhc-fighter-modal.php is included on this page and ajax/dhc-fighter.php
   * returns exactly the payload it expects, keyed on serial -- the same pair
   * the Collection, the roster and the Rivalries map use. So a Fighter
   * clicked here shows its traits, its ranks and its proven record
   * identically to everywhere else. A second panel repeating most of it is
   * how two surfaces start disagreeing about what a Fighter is.
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

  /* ---- the player panel ----
     Built from the data already on the page: every number in it is in
     window.SIEGE_DATA, so there is nothing to fetch. */
  const veil  = document.getElementById('sg-veil');
  const pbody = document.getElementById('sg-body');
  function closePlayer() { if (veil) veil.hidden = true; }
  if (veil) {
    const x = document.getElementById('sg-close');
    if (x) x.addEventListener('click', closePlayer);
    /* The backdrop closes it; the sheet itself must not. */
    veil.addEventListener('click', e => { if (e.target === veil) closePlayer(); });
  }
  document.addEventListener('keydown', e => {
    if (e.key === 'Escape' && veil && !veil.hidden) closePlayer();
  });

  /** A Fighter portrait as stacked <img> -- the HTML counterpart of the SVG
      one, using the kit's layer geometry so the two cannot disagree. */
  function portrait(f) {
    if (!f.layers || !f.layers.length) return '<span class="art empty"></span>';
    const L = f.layers.map(l => {
      const g = KIT.layerGeom(l, 100, ARM_SPLIT);
      const clip = g.cut ? `clip-path:inset(0 ${100 - g.cut.to}% 0 ${g.cut.from}%);` : '';
      return `<img src="${esc(l.src)}" alt="" style="top:${g.dy.toFixed(2)}%;${clip}">`;
    }).join('');
    return '<span class="art">' + L + '</span>';
  }

  function openPlayer(id) {
    const n = D.nodes[id];
    if (!n || !veil || !pbody) return;
    const waves = Object.keys(n.waves || {}).map(Number).sort((a, b) => a - b);
    const crew = (n.fighters || []).slice().sort((a, b) => b.dealt - a.dealt);
    let h = '';
    h += '<div class="sg-phead">';
    if (n.avatar) h += `<img class="av" src="${esc(n.avatar)}" alt="">`;
    h += `<div><h2 id="sg-pname">${esc(n.name)}</h2>`;
    h += `<p class="sub">${n.dealt.toLocaleString()} damage · ${n.per.toLocaleString()} per Fighter</p>`;
    h += '</div></div>';
    h += '<dl class="sg-pstats">';
    h += `<dt>Crews sent</dt><dd>${n.runs}</dd>`;
    h += `<dt>Rounds turned up</dt><dd>${n.rounds}</dd>`;
    h += `<dt>Fighters fielded</dt><dd>${n.fielded}</dd>`;
    if (waves.length) h += `<dt>Waves fought</dt><dd>${waves.join(', ')}</dd>`;
    if (n.kills && n.kills.length)
      h += `<dt>Last blows landed</dt><dd>${n.kills.length}</dd>`;
    h += '</dl>';
    if (crew.length) {
      h += '<p class="sg-psect">The Crew</p><div class="sg-pcrew">';
      crew.forEach(f => {
        /* A disassembled Fighter has no art and no serial -- say that
           rather than offering a click that cannot resolve. */
        const tag = f.gone ? 'span' : 'button';
        const attr = f.gone ? ' class="gone"' : ` type="button" data-serial="${esc(f.serial)}"`;
        h += `<${tag}${attr}>${portrait(f)}<b>${esc(f.name)}</b>`
           + `<i>${f.dealt.toLocaleString()} dmg</i></${tag}>`;
      });
      h += '</div>';
    }
    pbody.innerHTML = h;
    pbody.querySelectorAll('button[data-serial]').forEach(btn => {
      btn.addEventListener('click', () => openFighter(btn.getAttribute('data-serial')));
    });
    veil.hidden = false;
  }

  /*
   * DELEGATED, not bound per node: a Fighter opens its own panel, anything
   * else inside a cluster opens the player's.
   */
  host.addEventListener('click', e => {
    const t = e.target;
    const sat = t.closest ? t.closest('.sg-sat') : null;
    if (sat) {
      const serial = sat.getAttribute('data-serial');
      if (serial) openFighter(serial);
      return;
    }
    const cl = t.closest ? t.closest('.sg-cluster') : null;
    if (cl) { openPlayer(cl.getAttribute('data-id')); return; }
    focus(null);
  });
})();
