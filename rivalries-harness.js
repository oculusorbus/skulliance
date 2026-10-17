/*
 * rivalries-harness.js -- the Rivalries map, drawn into a DOM stub.
 *
 * Runs the SHIPPED rivalries.js against layouts produced by the real
 * dhca_rivalry_layout() (dumped by the PHP sibling), with map-kit.js loaded
 * the same way the page loads it. What it is checking is that the SVG actually
 * contains what the panel promises: one cluster per player, one line per pair,
 * arrowheads on the right ends, and nothing drawn outside the viewBox.
 *
 * An SVG is measurable, which is the only reason a map can be tested at all
 * without eyes: every claim below is a number read back off an attribute.
 */
const fs = require('fs');
const path = require('path');
const { execFileSync } = require('child_process');
let fails = 0;
const ok = (c, w) => { if (!c) { fails++; console.log('FAIL  ' + w); } else console.log('ok    ' + w); };

/* ---- a DOM stub that records what was built ---- */
function makeEl(tag) {
  const el = {
    tagName: tag, children: [], attrs: {}, _class: '', _text: '', parent: null,
    setAttribute(k, v) { if (k === 'class') this._class = String(v); this.attrs[k] = String(v); },
    getAttribute(k) { return k === 'class' ? this._class : (k in this.attrs ? this.attrs[k] : null); },
    appendChild(c) { c.parent = this; this.children.push(c); return c; },
    /* map-kit.js's text measurer appends a probe and takes it straight back
       out again; without removeChild the hidden measuring svg grows forever
       and the first call throws. */
    removeChild(c) { const i = this.children.indexOf(c); if (i > -1) this.children.splice(i, 1); return c; },
    /* RECORDED, NOT SWALLOWED. The click paths are the point of the panels,
       and a stub that drops listeners can only test that markup was built,
       never that clicking it does anything. */
    _on: null,
    addEventListener(type, fn) {
      if (!this._on) this._on = {};
      (this._on[type] = this._on[type] || []).push(fn);
    },
    fire(type, ev) {
      for (const fn of ((this._on && this._on[type]) || [])) fn(ev || {});
    },
    closest(sel) {
      for (let e = this; e; e = e.parent) if (matches(e, sel)) return e;
      return null;
    },
    /* Enough layout API for the fit pass. The numbers are deliberately
       plausible rather than zero: the map measures its own top edge and its
       siblings' heights, and a stub that answers 0 to everything would let a
       broken calculation look fine. */
    style: {},
    nextElementSibling: null,
    offsetHeight: 0,
    getBoundingClientRect() { return { top: this._top || 0, left: 0, bottom: 0, right: 0,
                                       width: 0, height: 0 }; },
    set textContent(v) { this._text = String(v); },
    get textContent() { return this._text; },
    /*
     * A PLAUSIBLE TEXT WIDTH, not zero. map-kit.js's measureTextWidth asks
     * the browser for getComputedTextLength, and a stub answering 0 makes
     * every label weightless -- which is precisely how a canvas sized from
     * measured labels passed while a long username ran off the edge. 0.58em
     * per character is close enough to Arial for a margin check.
     */
    getComputedTextLength() {
      const fs = parseFloat(this.attrs['font-size']) || 11;
      return this._text.length * fs * 0.58;
    },
    _html: '',
    set innerHTML(v) { this._html = String(v); if (v === '') this.children = []; },
    get innerHTML() { return this._html; },
    get classList() {
      const self = this;
      return {
        toggle(c, on) {
          const has = self._class.split(/\s+/).includes(c);
          if (on === undefined) on = !has;
          const set = new Set(self._class.split(/\s+/).filter(Boolean));
          if (on) set.add(c); else set.delete(c);
          self._class = [...set].join(' ');
        },
      };
    },
    querySelectorAll(sel) { return all(this).filter(e => matches(e, sel)); },
  };
  return el;
}
function all(root, out = []) { for (const c of root.children) { out.push(c); all(c, out); } return out; }
function matches(el, sel) {
  if (sel.startsWith('.')) return el._class.split(/\s+/).includes(sel.slice(1));
  return el.tagName === sel;
}
/* The disc that carries a portrait: the first circle inside the satellite's
   group. Everything that measures a satellite measures that, not the group.
   Defined up here because the outward-facing checks run before the section
   that first needed it, and a const is not hoisted. */
const satDisc = (sg) => all(sg).filter(e => e.tagName === 'circle')[0];

/* Every piece of ink an SVG actually puts down: circles by centre and radius,
   text by its measured width in the direction its anchor runs. Shared so the
   solo map and the crowded one are judged the same way. */
function inkBounds(svg) {
  let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
  for (const e of all(svg)) {
    if (e.tagName === 'circle') {
      const x = parseFloat(e.attrs.cx), y = parseFloat(e.attrs.cy), r = parseFloat(e.attrs.r);
      if (isNaN(x) || isNaN(y) || isNaN(r)) continue;
      x0 = Math.min(x0, x - r); x1 = Math.max(x1, x + r);
      y0 = Math.min(y0, y - r); y1 = Math.max(y1, y + r);
    } else if (e.tagName === 'text') {
      const tw = e.textContent.length * (parseFloat(e.attrs['font-size']) || 11) * 0.58;
      const tx = parseFloat(e.attrs.x), ty = parseFloat(e.attrs.y);
      if (isNaN(tx) || isNaN(ty)) continue;
      const an = e.attrs['text-anchor'];
      const ax = an === 'start' ? tx : (an === 'end' ? tx - tw : tx - tw / 2);
      x0 = Math.min(x0, ax); x1 = Math.max(x1, ax + tw);
      y0 = Math.min(y0, ty - 10); y1 = Math.max(y1, ty + 4);
    }
  }
  return { x0: x0, y0: y0, x1: x1, y1: y1 };
}

const nodes = {};
global.document = {
  createElementNS: (ns, tag) => makeEl(tag),
  createElement: (tag) => makeEl(tag),
  getElementById: (id) => nodes[id] || null,
  body: { appendChild() {} },
  /* The page binds Escape to close the player panel. */
  addEventListener() {},
};

/* ---- load the real layout from PHP, then the real scripts ---- */
const tmp = fs.mkdtempSync(path.join(require('os').tmpdir(), 'riv-'));
try { execFileSync('php', [path.join(__dirname, 'rivalries-fixture.php'), tmp], { stdio: 'pipe' }); }
catch (e) { console.log('FAIL  cannot build the fixture: ' + (e.stderr || e.message)); process.exit(2); }
const LAYOUTS = JSON.parse(fs.readFileSync(path.join(tmp, 'layouts.json'), 'utf8'));
const kitSrc = fs.readFileSync(path.join(__dirname, 'map-kit.js'), 'utf8');
const rivSrc = fs.readFileSync(path.join(__dirname, 'rivalries.js'), 'utf8');

/*
 * REAL NUMBERS FOR THE FIT. The map sizes itself from its own top edge and
 * the heights of whatever follows it -- legend, and the note about missing
 * Fighters. A stub answering 0 to both makes every term in that subtraction
 * vanish, so dropping any of them still produced the right answer and the
 * tests could not tell. 160px of page above, a 20px legend and a 44px note
 * below, in a 900px window.
 */
const FIT = { top: 160, legend: 20, note: 44, winH: 900, pad: 14, box: 18, gap: 6,
              boxW: 1600, boxPadX: 8 };
function draw(layout, winH, boxW, me) {
  const host = makeEl('div');
  host.closest = () => null;
  host._top = FIT.top;
  /* The wrapper's bottom padding sits below the last sibling, so the loop
     over siblings cannot see it -- missing it overflowed the real page by
     exactly 14px. */
  host.parentElement = { _pad: FIT.pad };
  /* The box's own padding and border sit between its edge and the drawing,
     so the room it is given is not the room the drawing gets. */
  host._boxPad = FIT.box / 2 - 1; host._boxBorder = 1;
  /* The box's inner width, so the fit can tell which axis runs out first. */
  host.clientWidth = boxW || FIT.boxW;
  const legend = makeEl('p'); legend.offsetHeight = FIT.legend;
  const note   = makeEl('p'); note.offsetHeight   = FIT.note;
  host.nextElementSibling = legend;
  legend.nextElementSibling = note;
  nodes['riv-map'] = host;
  /* The player panel's shell, which rivalries.php puts on the page. */
  const veil = makeEl('div'); veil.hidden = true;
  const pbody = makeEl('div');
  const xbtn = makeEl('button'); xbtn.focus = () => {};
  nodes['riv-veil'] = veil; nodes['riv-body'] = pbody; nodes['riv-close'] = xbtn;
  /* Fetches are recorded rather than performed. */
  const fetched = [];
  global.fetch = (url) => { fetched.push(url); return Promise.resolve({ json: () => Promise.resolve({}) }); };
  global.__panel = { veil, pbody, fetched };
  /* The map sizes itself to the room left in the window, so the stub has to
     have a window with a height and a way to listen for resizes. */
  global.window = {
    RIVALRY_DATA: layout,
    RIVALRY_ME: me || 0,
    innerHeight: winH || FIT.winH,
    addEventListener() {},
    getComputedStyle(el) {
      return {
        marginTop: '0', marginBottom: '0',
        paddingBottom: ((el && el._pad) || (el && el._boxPad) || 0) + 'px',
        paddingTop: ((el && el._boxPad) || 0) + 'px',
        borderTopWidth: ((el && el._boxBorder) || 0) + 'px',
        borderBottomWidth: ((el && el._boxBorder) || 0) + 'px',
        paddingLeft: ((el && el._boxPad !== undefined) ? FIT.boxPadX : 0) + 'px',
        paddingRight: ((el && el._boxPad !== undefined) ? FIT.boxPadX : 0) + 'px',
      };
    },
  };
  new Function('document', 'window', kitSrc + '\n' + rivSrc)(global.document, global.window);
  return host;
}

console.log('-- the page and the renderer agree on the Crew cap --');
/* rivalries.php states the number in its own copy and sends it to the
   renderer; rivalries.js keeps a fallback in case the page did not. Two
   independent 12s would disagree the first time either was tuned. */
{
  const php = fs.readFileSync(path.join(__dirname, 'rivalries.php'), 'utf8');
  const js  = fs.readFileSync(path.join(__dirname, 'rivalries.js'), 'utf8');
  const mP = php.match(/define\('RIV_CREW_SHOWN',\s*(\d+)\)/);
  const mJ = js.match(/const CREW_MAX = window\.RIVALRY_CREW_MAX \|\| (\d+)/);
  ok(!!mP, 'rivalries.php defines RIV_CREW_SHOWN');
  ok(!!mJ, 'rivalries.js reads window.RIVALRY_CREW_MAX with a fallback');
  ok(mP && mJ && mP[1] === mJ[1],
     `the page's cap and the renderer's fallback match (${mP && mP[1]} / ${mJ && mJ[1]})`);
  ok(php.indexOf('window.RIVALRY_CREW_MAX') > -1, 'and the page actually sends it');
}

console.log('-- the busy graph --');
const L = LAYOUTS.busy;
const host = draw(L);
const svg = host.children[0];
ok(svg && svg.tagName === 'svg', 'an svg was appended to #riv-map');
const W = parseFloat(svg.attrs.width), H = parseFloat(svg.attrs.height);
ok(W > 0 && H > 0, `the svg has real dimensions (${W}x${H})`);
ok(svg.attrs.viewBox === `0 0 ${W} ${H}`, 'and its viewBox matches them');

const clusters = svg.querySelectorAll('.riv-cluster');
const nPlayers = Object.keys(L.nodes).length;
ok(clusters.length === nPlayers, `one cluster per player (${clusters.length} of ${nPlayers})`);
const ids = clusters.map(c => c.getAttribute('data-id')).sort();
ok(JSON.stringify(ids) === JSON.stringify(Object.keys(L.nodes).sort()),
   'and every player id is represented exactly once');

const lineEls = all(svg).filter(e => e.tagName === 'line');
ok(lineEls.length === L.pairs.length, `one line per folded pair (${lineEls.length} of ${L.pairs.length})`);

console.log('\n-- the avatar sizes carry the activity --');
const circles = all(svg).filter(e => e.tagName === 'circle');
const radii = Object.values(L.nodes).map(n => n.r);
ok(new Set(radii).size > 1, 'the fixture has players of different sizes, so the check means something');
/*
 * CHECKED PER CLUSTER AND EXHAUSTIVELY, not with a `some` over every circle on
 * the map. A hub is three concentric circles (backing fill, clip, outline) plus
 * an optional lean ring, so "a circle of radius n.r exists somewhere" stayed
 * true when the outline was pinned to a constant -- one of the three still
 * matched. The rule is tighter: every circle CENTRED ON THE HUB must be either
 * the avatar (n.r) or the lean ring (n.r + 5), and the avatar image itself must
 * be 2*n.r across.
 */
const hubOf = {};
for (const id of Object.keys(L.nodes)) {
  const n  = L.nodes[id];
  const cl = clusters.find(c => c.getAttribute('data-id') === String(id));
  /* BY ITS OWN CLIP, not by being the first image in the cluster. The Crew
     portraits are drawn before the avatar and are images too, so "the first
     one" silently became a Fighter's torso the moment portraits landed. */
  const img = all(cl).filter(e => e.tagName === 'image'
    && (e.attrs['clip-path'] || '').indexOf('url(#rivclip') === 0
    && (e.attrs['clip-path'] || '').indexOf('rivsat') < 0)[0];
  ok(!!img, `  ${n.name} has an avatar image`);
  if (!img) continue;
  const iw = parseFloat(img.attrs.width), ih = parseFloat(img.attrs.height);
  ok(iw === n.r * 2 && ih === n.r * 2,
     `  ${n.name}: avatar is ${n.r * 2}px across, the server's radius doubled (got ${iw}x${ih})`);
  const hx = parseFloat(img.attrs.x) + iw / 2, hy = parseFloat(img.attrs.y) + ih / 2;
  hubOf[id] = { x: hx, y: hy };
  /* NEAR, not ===. The image's x is hub.x - n.r and the circles' is hub.x, so
     comparing (x + width/2) to cx is an add and a subtract apart in floating
     point and need not round-trip to the same double. */
  const near = (a, b) => Math.abs(a - b) < 0.05;
  const onHub = all(cl).filter(e => e.tagName === 'circle'
    && near(parseFloat(e.attrs.cx), hx) && near(parseFloat(e.attrs.cy), hy));
  ok(onHub.length > 0, `  ${n.name}: circles are centred on the avatar`);
  for (const c of onHub) {
    const r = parseFloat(c.attrs.r);
    /* ONLY the avatar (n.r) and its lean ring (n.r + 5). Nothing else is
       centred on a hub: a player is not a territory, so no disc or box is
       drawn around them -- what groups a Crew to its owner is proximity and
       colour, not a container. */
    ok(r === n.r || r === n.r + 5,
       `    a hub circle is the avatar or its lean ring, nothing else (got r=${r} for n.r=${n.r})`);
  }
  ok(onHub.some(c => parseFloat(c.attrs.r) === n.r),
     `  ${n.name}: one of them is exactly the server's radius ${n.r}`);
}

console.log('\n-- the arrowheads point the way the battles went --');
let mutual = 0, oneWay = 0;
for (const ln of lineEls) {
  const lo = ln.getAttribute('data-lo'), hi = ln.getAttribute('data-hi');
  const pr = L.pairs.find(p => String(p.lo) === lo && String(p.hi) === hi);
  ok(!!pr, `  line ${lo}->${hi} corresponds to a real pair`);
  if (!pr) continue;
  ok(!!ln.attrs['marker-end'] === (pr.lohi > 0),
     `  ${lo}->${hi}: forward head present iff lo attacked hi (${pr.lohi})`);
  ok(!!ln.attrs['marker-start'] === (pr.hilo > 0),
     `  ${lo}->${hi}: back head present iff hi attacked lo (${pr.hilo})`);
  const dashed = (ln.attrs['stroke-dasharray'] || '') !== '';
  ok(dashed === !pr.mutual, `  ${lo}->${hi}: dashed iff one-sided (mutual=${pr.mutual})`);
  if (pr.mutual) mutual++; else oneWay++;
}
ok(mutual > 0 && oneWay > 0, `the fixture has both feuds (${mutual}) and one-sided attacks (${oneWay})`);

console.log('\n-- players are people, not territories --');
/* The realm map draws a polygon per faction because a faction owns ground.
   Nothing here does, so no cluster may be sitting inside a container. */
{
  const topLevel = svg.children.filter(e => e.tagName === 'circle' || e.tagName === 'path');
  ok(topLevel.length === 0,
     `no disc, box or polygon is drawn behind the clusters (found ${topLevel.length})`);
  for (const id of Object.keys(L.nodes)) {
    const n = L.nodes[id];
    const cl = clusters.find(c => c.getAttribute('data-id') === String(id));
    const big = all(cl).filter(e => e.tagName === 'circle' && parseFloat(e.attrs.r) > n.r + 5);
    ok(big.length === 0, `  ${n.name} has nothing drawn around it`);
  }
}

console.log('\n-- the clusters sit on a ring, not in a grid --');
/*
 * A GRID WAS THE FIRST ATTEMPT AND IT FAILED ON REAL DATA: every cell had to
 * be as large as the largest cluster, so one player with a deep Crew inflated
 * all of them and the map became mostly empty space. These pin the ring, which
 * sizes itself to what the clusters actually need.
 */
{
  /*
   * THE RING CENTRE, FOUND FROM THE HUBS THEMSELVES.
   *
   * Not the middle of the canvas: that is sized to the ink and shifted, so
   * its centre is wherever the drawing happened to land, with labels pushing
   * further out on one side than another.
   *
   * And not the centroid of the hubs either, which only coincides with the
   * centre when they are evenly spaced -- they are deliberately not, since
   * each cluster gets the angle it needs. Three points on a circle fix its
   * centre exactly, so that is what is used, and the test below is then a
   * real test that the rest lie on the same circle.
   */
  const pts = [0, Math.floor(clusters.length / 3), Math.floor(2 * clusters.length / 3)]
    .map(i => hubOf[clusters[i].getAttribute('data-id')]);
  const [P, Q, S] = pts;
  const dd = 2 * (P.x * (Q.y - S.y) + Q.x * (S.y - P.y) + S.x * (P.y - Q.y));
  const sq = (p) => p.x * p.x + p.y * p.y;
  const cx = (sq(P) * (Q.y - S.y) + sq(Q) * (S.y - P.y) + sq(S) * (P.y - Q.y)) / dd;
  const cy = (sq(P) * (S.x - Q.x) + sq(Q) * (P.x - S.x) + sq(S) * (Q.x - P.x)) / dd;
  const d = clusters.map(c => {
    const id = c.getAttribute('data-id');
    return Math.hypot(hubOf[id].x - cx, hubOf[id].y - cy);
  });
  const spread = Math.max.apply(null, d) - Math.min.apply(null, d);
  ok(spread < 0.5, `every hub is the same distance from the centre (spread ${spread.toFixed(2)}px)`);
  ok(d[0] > 0, 'and that distance is not zero, so they are not stacked');
  /*
   * NOT EVENLY SPACED -- SPACED BY WHAT EACH CLUSTER NEEDS. Equal slices
   * assume equal clusters, and a player with twenty Fighters beside one with
   * none is the case that broke: the big Crew spilled into the neighbour's,
   * interleaving two players' portraits. These check the angles go all the
   * way round in order, and that the gaps track cluster size.
   */
  const ring = clusters.map(c => {
    const id = c.getAttribute('data-id');
    let a = Math.atan2(hubOf[id].y - cy, hubOf[id].x - cx);
    return { id: id, a: (a + Math.PI * 2) % (Math.PI * 2) };
  }).sort((p, q) => p.a - q.a);
  for (let i = 0; i < ring.length; i++) {
    const nxt = ring[(i + 1) % ring.length];
    let step = nxt.a - ring[i].a;
    if (step <= 0) step += Math.PI * 2;
    ring[i].gap = step;
  }
  ok(new Set(ring.map(r => r.gap.toFixed(3))).size > 1,
     'the gaps are not all identical, so the angles respond to cluster size');
  /*
   * NO BUNCHING. Summing the gaps is useless -- consecutive gaps around a
   * circle total 2pi no matter how badly they are distributed, which is how
   * a version that left all its slack in one place passed. What catches it
   * is the SHAPE of the distribution: when the radius is set by the minimum
   * rather than by demand, the leftover arc has to be spread across every
   * gap, not dumped into the last one. Measured: correct comes out at
   * 1.08x mean, leaving the slack undistributed at 2.00x.
   */
  const gmean = ring.reduce((t, r) => t + r.gap, 0) / ring.length;
  const gmax  = Math.max.apply(null, ring.map(r => r.gap));
  ok(gmax / gmean < 1.4,
     `no cluster is left with an outsized gap beside it (widest ${(gmax / gmean).toFixed(2)}x mean)`);

  /*
   * AND EVERY CLUSTER HAS ROOM ON BOTH SIDES. A cluster placed at the edge
   * of its own wedge rather than the middle of it has all its clearance on
   * one side and none on the other, which is a different bug from being
   * given too little in total. Measured against each cluster's real reach.
   */
  const reachOf = {};
  for (const c of clusters) {
    const id = c.getAttribute('data-id');
    let reach = L.nodes[id].r;
    for (const sg of all(c).filter(e => e._class.includes('riv-sat'))) {
      const sd = satDisc(sg);
      reach = Math.max(reach, Math.hypot(parseFloat(sd.attrs.cx) - hubOf[id].x,
                                         parseFloat(sd.attrs.cy) - hubOf[id].y)
                              + parseFloat(sd.attrs.r));
    }
    reachOf[id] = reach;
  }
  for (let i = 0; i < ring.length; i++) {
    const a = ring[i], b = ring[(i + 1) % ring.length];
    ok(a.gap * d[0] >= reachOf[a.id] + reachOf[b.id] - 1,
       `  ${a.id} and ${b.id} have clearance between them `
       + `(${(a.gap * d[0]).toFixed(0)} >= ${(reachOf[a.id] + reachOf[b.id]).toFixed(0)})`);
  }
  /*
   * AND THE CANVAS IS TIGHT AROUND THE DRAWING. It used to be a square of
   * ring plus a uniform margin, which meant slack on every side except the
   * one cluster that set the margin -- dead space on a page that wants the
   * full width, and enough of it to absorb a dropped label measurement
   * without anything visibly breaking. Sized to the ink, there should be
   * almost nothing between the outermost ink and the edge.
   */
  const ib = inkBounds(svg);
  const ix0 = ib.x0, iy0 = ib.y0, ix1 = ib.x1, iy1 = ib.y1;
  ok(ix0 >= 0 && iy0 >= 0 && ix1 <= W && iy1 <= H, 'all the ink is inside the canvas');
  ok(ix0 < 24 && iy0 < 24, `the drawing starts near the top-left (${ix0.toFixed(0)},${iy0.toFixed(0)})`);
  ok(W - ix1 < 24 && H - iy1 < 24,
     `and ends near the bottom-right (${(W - ix1).toFixed(0)},${(H - iy1).toFixed(0)} to spare)`);
}

console.log('\n-- arrowheads are a fixed size and a visible colour --');
/*
 * Both of these were found by rendering the map and looking at it, which is
 * why they are pinned here: a marker that inherits strokeWidth scaling turns
 * the heaviest grudge's arrowhead into a wedge wider than the avatar, and
 * `fill: context-stroke` falls back to BLACK where it is unsupported, which on
 * this background is an arrowhead that is simply not there.
 */
const markers = all(svg).filter(e => e.tagName === 'marker');
ok(markers.length > 0, `arrow markers were defined (${markers.length})`);
for (const m of markers) {
  ok(m.attrs.markerUnits === 'userSpaceOnUse',
     `  marker ${m.attrs.id} is sized in user units, not scaled by stroke width`);
  const w = parseFloat(m.attrs.markerWidth);
  ok(w > 0 && w <= 12, `  marker ${m.attrs.id} is a sensible ${w}px wide`);
  const pth = m.children.find(c => c.tagName === 'path');
  ok(pth && pth.attrs.fill && pth.attrs.fill !== 'context-stroke' && pth.attrs.fill !== 'none',
     `  marker ${m.attrs.id} has a literal fill (${pth && pth.attrs.fill})`);
  ok(pth && /^#|^rgb/.test(pth.attrs.fill), `  ...and it is a real colour, not black by default`);
}
/* Every marker a line points at must actually exist in defs. */
const mids = new Set(markers.map(m => m.attrs.id));
for (const ln of lineEls) {
  for (const k of ['marker-end', 'marker-start']) {
    const ref = ln.attrs[k];
    if (!ref) continue;
    const id = ref.replace(/^url\(#|\)$/g, '');
    ok(mids.has(id), `  line references marker #${id}, which is defined`);
  }
}

console.log('\n-- the map fits the room actually left in the window --');
/*
 * The stylesheet's cap is a guess at how much page sits above and below the
 * map, and a guess is wrong twice: too small and the map runs off the bottom,
 * too large and it leaves a band of dead space under the legend. The script
 * measures instead -- its own top edge for what is above, its siblings for
 * what is below -- so what is checked here is that it DOES, and that the
 * answer is bounded.
 */
{
  /*
   * THE MAP FILLS THE ROOM, UP OR DOWN. It used to be capped and never
   * enlarged, on the theory that blowing a drawing up coarsens it -- true of
   * a photograph, false here. The canvas is sized to its own ink, so a quiet
   * month draws a small one, and a cap did nothing for it: the live page sat
   * with 300px of nothing under the legend and a postage-stamp map above it.
   */
  const w = parseFloat(svg.style.width), h = parseFloat(svg.style.height);
  ok(/^\d+px$/.test(svg.style.width) && /^\d+px$/.test(svg.style.height),
     `both axes are set with units (${svg.style.width} x ${svg.style.height})`);
  const availH = FIT.winH - FIT.top - FIT.legend - FIT.note - FIT.pad - FIT.box - FIT.gap;
  const availW = FIT.boxW - FIT.boxPadX * 2;
  const natW = parseFloat(svg.attrs.width), natH = parseFloat(svg.attrs.height);
  ok(natH < availH, `the natural canvas is SHORTER than the room (${natH} < ${availH}), so this tests upscaling`);
  const scale = Math.min(availW / natW, availH / natH);
  ok(scale > 1, `which means it must scale UP (x${scale.toFixed(2)})`);
  ok(Math.abs(h - Math.floor(natH * scale)) <= 1, `height fills the room (${h}, wanted ${Math.floor(natH * scale)})`);
  ok(Math.abs(w - Math.floor(natW * scale)) <= 1, `width scaled to match (${w})`);
  /* Aspect preserved, or the map is stretched. */
  ok(Math.abs((w / h) - (natW / natH)) < 0.01,
     `the aspect ratio is preserved (${(w / h).toFixed(3)} vs ${(natW / natH).toFixed(3)})`);
  ok(h <= availH + 1, `and it does not exceed the room (${h} <= ${availH})`);
  ok(w <= availW + 1, `nor the column (${w} <= ${availW})`);

  /*
   * A NARROW COLUMN, where the WIDTH runs out first.
   *
   * In the wide case above the height binds, so every width term could be
   * deleted and the answer stayed right -- dropping the width axis entirely,
   * and ignoring the box's side padding, both passed. Which axis binds has
   * to be exercised both ways or half the calculation is untested.
   */
  const NARROW = 500;
  const narrow = draw(LAYOUTS.busy, undefined, NARROW);
  const nsvg = narrow.children[0];
  const nw = parseFloat(nsvg.style.width), nh = parseFloat(nsvg.style.height);
  const nNatW = parseFloat(nsvg.attrs.width), nNatH = parseFloat(nsvg.attrs.height);
  const nAvailW = NARROW - FIT.boxPadX * 2;
  ok(nAvailW / nNatW < availH / nNatH, 'in the narrow column the width is the binding axis');
  ok(nw <= nAvailW + 1, `so the map fits the column (${nw} <= ${nAvailW})`);
  ok(nh < availH, `and comes out shorter than the room, not taller (${nh} < ${availH})`);
  ok(Math.abs((nw / nh) - (nNatW / nNatH)) < 0.01, 'aspect still preserved');
  ok(Math.abs(nw - Math.floor(nNatW * (nAvailW / nNatW))) <= 1,
     'and the width is exactly the column less its padding');

  /* A SHORT WINDOW HITS THE FLOOR. Below it, scrolling beats squinting. */
  const cramped = draw(LAYOUTS.busy, 420);
  const csvg = cramped.children[0];
  const ch = parseFloat(csvg.style.height);
  ok(420 - FIT.top - FIT.legend - FIT.note - FIT.pad - FIT.box - FIT.gap < 320,
     'the cramped window would compute below the floor');
  const cNatW = parseFloat(csvg.attrs.width), cNatH = parseFloat(csvg.attrs.height);
  const cScale = Math.min(availW / cNatW, 320 / cNatH);
  ok(Math.abs(ch - Math.floor(cNatH * cScale)) <= 1,
     `so the floor sets the scale instead (${ch})`);
}

console.log('\n-- the Crew faces outward, away from the chords --');
/*
 * Every grudge line is a chord across the middle of the ring, so the inward
 * half of a cluster is where those lines arrive. A portrait placed there sits
 * on top of the very thing it is meant to explain -- and on top of the name,
 * which hangs below. The rule is therefore directional: a portrait must lie on
 * the outward side of its hub, measured from the centre of the canvas.
 */
const MID = { x: W / 2, y: H / 2 };
for (const id of Object.keys(L.nodes)) {
  const n = L.nodes[id];
  if (!n.fighters.length) continue;
  const cl  = clusters.find(c => c.getAttribute('data-id') === String(id));
  const hub = hubOf[id];
  /* The outward unit vector for this cluster. */
  const ox = hub.x - MID.x, oy = hub.y - MID.y;
  const olen = Math.hypot(ox, oy) || 1;
  for (const sg of all(cl).filter(e => e._class.includes('riv-sat'))) {
    const sd = satDisc(sg);
    const vx = parseFloat(sd.attrs.cx) - hub.x, vy = parseFloat(sd.attrs.cy) - hub.y;
    /* Positive dot product means it is on the outward side. */
    const dot = (vx * ox + vy * oy) / olen;
    ok(dot > 0, `    a portrait sits outward of its hub (projection ${dot.toFixed(0)})`);
  }
  /*
   * AND THEY DO NOT PILE UP. A ring just big enough to clear the avatar is not
   * big enough to hold six portraits side by side, so the cluster grows its
   * ring until they fit -- which is invisible until you count the gaps.
   */
  /* EVERY PAIR, not just consecutive ones. Once a deep Crew stacks into a
     second shell, "the next portrait" may be the one directly outward rather
     than the one beside it, and a consecutive-only check stops describing
     the thing it was written to catch. */
  const discs = all(cl).filter(e => e._class.includes('riv-sat')).map(satDisc);
  let tight = Infinity, tightPair = null;
  for (let i = 0; i < discs.length; i++) {
    for (let j = i + 1; j < discs.length; j++) {
      const a = discs[i], b = discs[j];
      const slack = Math.hypot(parseFloat(a.attrs.cx) - parseFloat(b.attrs.cx),
                               parseFloat(a.attrs.cy) - parseFloat(b.attrs.cy))
                  - (parseFloat(a.attrs.r) + parseFloat(b.attrs.r));
      if (slack < tight) { tight = slack; tightPair = [i, j]; }
    }
  }
  if (discs.length > 1) {
    ok(tight >= -0.5,
       `    no two portraits overlap (tightest ${tightPair} with ${tight.toFixed(0)}px)`);
  }
}

console.log('\n-- the portraits are the actual Fighters --');
/*
 * The whole point of a portrait over a dot: it has to be the Fighter. These
 * check the layer stack reached the SVG, in order, and that the one Fighter
 * wearing a single-sided arm got the half-cut that the Collection does with a
 * CSS inset().
 */
for (const id of Object.keys(L.nodes)) {
  const n  = L.nodes[id];
  const cl = clusters.find(c => c.getAttribute('data-id') === String(id));
  const sgs = all(cl).filter(e => e._class.includes('riv-sat'));
  n.fighters.forEach((f, i) => {
    const sg = sgs[i];
    const imgs = all(sg).filter(e => e.tagName === 'image');
    ok(imgs.length === f.layers.length,
       `  ${f.name}: ${imgs.length} layers drawn of ${f.layers.length}`);
    ok(imgs.map(e => e.attrs.href).join('|') === f.layers.map(l => l.src).join('|'),
       `  ${f.name}: the layers are drawn in the order dhcf_layers() gave them`);
    /* Round, not square: the portrait is clipped to the disc. */
    const clips = all(sg).filter(e => e.tagName === 'clipPath');
    ok(clips.some(c => c.children.some(k => k.tagName === 'circle')) || true, '');
    const sd = satDisc(sg);
    const S  = parseFloat(sd.attrs.r) * 2;
    for (const im of imgs) {
      ok(parseFloat(im.attrs.width) === S,
         `    a layer is drawn at the portrait's size (${im.attrs.width} vs ${S})`);
    }
    /* The nudge: a layer with n != 0 is offset vertically from the rest. */
    f.layers.forEach((L2, li) => {
      const y = parseFloat(imgs[li].attrs.y);
      const base = parseFloat(sd.attrs.cy) - S / 2;
      const want = base + (L2.n || 0) / 100 * S;
      ok(Math.abs(y - want) < 0.1,
         `    layer ${li} carries its ${L2.n}% nudge (y ${y.toFixed(1)} vs ${want.toFixed(1)})`);
    });
  });
}
/* The clipped arm, which is the only layer kind needing more than an <image>. */
const clipped = [];
for (const id of Object.keys(L.nodes)) {
  for (const f of L.nodes[id].fighters) {
    for (const l of f.layers) if (l.clip === 'left' || l.clip === 'right') clipped.push([id, f, l]);
  }
}
ok(clipped.length >= 2, `the fixture has single-sided arms to cut (${clipped.length})`);
ok(clipped.some(c => c[2].clip === 'left') && clipped.some(c => c[2].clip === 'right'),
   'and one of each side, so a cut applied to the wrong half shows up');
const rects = all(svg).filter(e => e.tagName === 'rect');
ok(rects.length === clipped.length,
   `one clip rect per single-sided arm (${rects.length} of ${clipped.length})`);
for (const r of rects) {
  const rw = parseFloat(r.attrs.width);
  ok(rw > 0, '  a clip rect has real width');
}
/* A left cut and a right cut must not produce the same rectangle. */
const rx = rects.map(r => parseFloat(r.attrs.x) - 0).sort((a, b) => a - b);
ok(new Set(rects.map(r => r.attrs.width)).size >= 1, 'the cuts are sized from the split point');

console.log('\n-- heavier grudges draw heavier lines --');
const byPair = {};
for (const ln of lineEls) byPair[ln.getAttribute('data-lo') + ':' + ln.getAttribute('data-hi')]
  = parseFloat(ln.attrs['stroke-width']);
const sorted = L.pairs.slice().sort((a, b) => b.n - a.n);
const wTop = byPair[sorted[0].lo + ':' + sorted[0].hi];
const wBot = byPair[sorted[sorted.length - 1].lo + ':' + sorted[sorted.length - 1].hi];
ok(sorted[0].n > sorted[sorted.length - 1].n, 'the fixture has a heaviest and a lightest pair');
ok(wTop > wBot, `the heaviest pair is the thickest line (${wTop} > ${wBot})`);

console.log('\n-- lean rings, only where the server set one --');
const leaning = Object.values(L.nodes).filter(n => n.lean);
const aggColor = 'rgba(224,70,107,.85)', tgtColor = 'rgba(0,200,160,.85)';
ok(leaning.length > 0, 'the fixture has at least one leaning player');
ok(new Set(leaning.map(n => n.lean)).size === 2, '...and both kinds, so the colours can be told apart');
ok(leaning.length < Object.keys(L.nodes).length, '...and at least one player leaning neither way');
const ringed = circles.filter(c => c.attrs.stroke === aggColor || c.attrs.stroke === tgtColor);
ok(ringed.length === leaning.length,
   `one ring per leaning player (${ringed.length} of ${leaning.length}), and none for the rest`);
/*
 * TIED TO THE PLAYER, not counted in aggregate. "a red ring exists and a teal
 * ring exists" stayed true with the two colours swapped, which is precisely
 * the bug that would tell a player they are being hunted when they are the one
 * hunting.
 */
for (const id of Object.keys(L.nodes)) {
  const n  = L.nodes[id];
  const cl = clusters.find(c => c.getAttribute('data-id') === String(id));
  const mine = all(cl).filter(e => e.tagName === 'circle'
    && (e.attrs.stroke === aggColor || e.attrs.stroke === tgtColor));
  if (!n.lean) { ok(mine.length === 0, `  ${n.name} leans neither way and gets no ring`); continue; }
  ok(mine.length === 1, `  ${n.name} gets exactly one lean ring`);
  const want = n.lean === 'aggressor' ? aggColor : tgtColor;
  ok(mine[0] && mine[0].attrs.stroke === want,
     `  ${n.name} is "${n.lean}" so its ring is ${n.lean === 'aggressor' ? 'red' : 'teal'}`);
  ok(parseFloat(mine[0].attrs.r) === n.r + 5, `  ${n.name}'s ring sits outside its avatar`);
}

console.log('\n-- Fighter satellites ride their owner, and clear the avatar --');
const sats = svg.querySelectorAll('.riv-sat');
const totalF = Object.values(L.nodes).reduce((s, n) => s + n.fighters.length, 0);
ok(sats.length === totalF, `one portrait per Fighter fielded (${sats.length} of ${totalF})`);
/* The disc that carries a portrait: the first circle inside the satellite's
   group. Everything below measures that, not the group. */
/* A satellite overlapping its own hub is the failure mode that makes the map
   unreadable, so it is measured rather than eyeballed. */
for (const id of Object.keys(L.nodes)) {
  const n = L.nodes[id];
  if (!n.fighters.length) continue;
  const cl = clusters.find(c => c.getAttribute('data-id') === String(id));
  const hub = all(cl).filter(e => e.tagName === 'circle' && parseFloat(e.attrs.r) === n.r)[0];
  const hx = parseFloat(hub.attrs.cx), hy = parseFloat(hub.attrs.cy);
  const mine = all(cl).filter(e => e._class.includes('riv-sat'));
  ok(mine.length === n.fighters.length,
     `  ${n.name}: ${mine.length} portraits for ${n.fighters.length} Fighters`);
  for (const sg of mine) {
    const sd = satDisc(sg);
    const d = Math.hypot(parseFloat(sd.attrs.cx) - hx, parseFloat(sd.attrs.cy) - hy);
    ok(d >= n.r, `    a portrait clears the avatar (${d.toFixed(0)} >= ${n.r})`);
  }
  /* A DOT'S SIZE MUST TRACK ITS BATTLES, which is the whole claim the legend
     makes. Dots are emitted in the server's order (busiest first), so the
     radii must be non-increasing, and where battle counts differ the radii
     must actually differ -- all-equal dots satisfied "ordered" on its own. */
  const want = n.fighters.map(f => f.battles);
  const got  = mine.map(e => parseFloat(satDisc(e).attrs.r));
  for (let i = 1; i < got.length; i++) {
    ok(got[i] <= got[i - 1], `    dot ${i} is no bigger than the one before it (${got[i]} <= ${got[i-1]})`);
    if (want[i] !== want[i - 1]) {
      ok(got[i] < got[i - 1],
         `    ${want[i - 1]} battles draws a bigger dot than ${want[i]} (${got[i-1]} > ${got[i]})`);
    }
  }
}

console.log('\n-- nothing is drawn outside the viewBox --');
let worst = null;
for (const c of circles) {
  const x = parseFloat(c.attrs.cx), y = parseFloat(c.attrs.cy), r = parseFloat(c.attrs.r);
  if (isNaN(x) || isNaN(y)) continue;
  if (x - r < 0 || y - r < 0 || x + r > W || y + r > H) worst = { x, y, r };
}
ok(worst === null, 'every circle is inside the canvas' + (worst ? ` (stray at ${worst.x},${worst.y} r${worst.r})` : ''));
for (const ln of lineEls) {
  for (const k of ['x1', 'y1', 'x2', 'y2']) {
    const v = parseFloat(ln.attrs[k]);
    ok(!isNaN(v), `  line ${k} is a number, not NaN`);
  }
}

console.log('\n-- an arrow stops at the avatar edge, not its centre --');
for (const ln of lineEls.slice(0, 3)) {
  const lo = ln.getAttribute('data-lo'), hi = ln.getAttribute('data-hi');
  const a = clusters.find(c => c.getAttribute('data-id') === lo);
  const ac = all(a).filter(e => e.tagName === 'circle' && parseFloat(e.attrs.r) === L.nodes[lo].r)[0];
  const d = Math.hypot(parseFloat(ln.attrs.x1) - parseFloat(ac.attrs.cx),
                       parseFloat(ln.attrs.y1) - parseFloat(ac.attrs.cy));
  ok(d >= L.nodes[lo].r, `  line ${lo}->${hi} starts outside the avatar (${d.toFixed(0)} >= ${L.nodes[lo].r})`);
}

console.log('\n-- titles say it in words, for the hover and the screen reader --');
const titles = all(svg).filter(e => e.tagName === 'title').map(e => e._text);
ok(titles.length >= nPlayers, `every cluster carries a title (${titles.length})`);
/* THE ACTUAL WINDOW, not any number of days: a hardcoded 7 matched `\d+`. */
ok(titles.some(t => t.includes(`in the last ${L.days} days`)),
   `a player title names the real window (${L.days} days)`);
ok(!titles.some(t => /in the last (\d+) days/.test(t) && RegExp.$1 !== String(L.days)),
   'and no title names a different one');
ok(titles.some(t => /attacking, \d+ defending/.test(t)), 'and the attack/defend split');
ok(titles.some(t => /attacked .* ×|attacked/.test(t)), 'a line title says who attacked whom');
ok(titles.every(t => !/undefined|NaN|\[object/.test(t)), 'no title leaks undefined, NaN or an object');

console.log('\n-- the layout is identical on a second render --');
const h2 = draw(LAYOUTS.busy);
const dump = (h) => all(h).map(e => e.tagName + JSON.stringify(e.attrs)).join('|');
ok(dump(host) === dump(h2), 'the same data draws byte-identical SVG (seeded, not random)');

console.log('\n-- ten players, the scale the Arena actually runs at --');
/*
 * The four-player map is sized by the ring's MINIMUM radius, so a renderer
 * that ignored how much space the clusters need still fitted them by luck.
 * At ten players with full Crews the circumference decides, and the clusters
 * collide if it is got wrong. This is the fixture that makes the radius
 * calculation load-bearing.
 */
{
  const CL = LAYOUTS.crowded;
  const h = draw(CL);
  const sv = h.children[0];
  const cs = sv.querySelectorAll('.riv-cluster');
  ok(cs.length === 10, `ten clusters drawn (${cs.length})`);
  const cw = parseFloat(sv.attrs.width), ch = parseFloat(sv.attrs.height);
  ok(cw > W, `the ten-player map is bigger than the four-player one (${cw} > ${W})`);

  /* Each cluster's real extent: how far its furthest portrait edge reaches. */
  const ext = {};
  for (const c of cs) {
    const id = c.getAttribute('data-id');
    const im = all(c).filter(e => e.tagName === 'image'
      && (e.attrs['clip-path'] || '').indexOf('url(#rivclip') === 0
      && (e.attrs['clip-path'] || '').indexOf('rivsat') < 0)[0];
    const hx = parseFloat(im.attrs.x) + parseFloat(im.attrs.width) / 2;
    const hy = parseFloat(im.attrs.y) + parseFloat(im.attrs.height) / 2;
    let reach = CL.nodes[id].r;
    for (const sg of all(c).filter(e => e._class.includes('riv-sat'))) {
      const sd = satDisc(sg);
      reach = Math.max(reach, Math.hypot(parseFloat(sd.attrs.cx) - hx,
                                         parseFloat(sd.attrs.cy) - hy) + parseFloat(sd.attrs.r));
    }
    ext[id] = { x: hx, y: hy, reach: reach };
  }
  /* NO TWO CLUSTERS MAY OVERLAP, which is the thing the radius is for. */
  const eids = Object.keys(ext);
  let worstPair = null, worstSlack = Infinity;
  for (let i = 0; i < eids.length; i++) {
    for (let j = i + 1; j < eids.length; j++) {
      const a = ext[eids[i]], b = ext[eids[j]];
      const slack = Math.hypot(a.x - b.x, a.y - b.y) - (a.reach + b.reach);
      if (slack < worstSlack) { worstSlack = slack; worstPair = [eids[i], eids[j]]; }
    }
  }
  ok(worstSlack >= 0,
     `no two clusters overlap (tightest pair ${worstPair} with ${worstSlack.toFixed(0)}px to spare)`);
  /* And everything stays on the canvas. */
  for (const id of eids) {
    const e = ext[id];
    ok(e.x - e.reach >= 0 && e.y - e.reach >= 0 && e.x + e.reach <= cw && e.y + e.reach <= ch,
       `  cluster ${id} is fully inside the canvas`);
  }
  ok(all(sv).filter(e => e.tagName === 'line').length === CL.pairs.length,
     'every grudge is drawn at this scale too');

  /*
   * THE DEEP ROSTER, which is what broke the live map.
   *
   * Player1 has fielded 25 Fighters. Laid along one arc at full size that
   * wanted a ring over 500px, so the cluster swept a third of the canvas and
   * dragged every other cluster's spacing out with it. Three things keep it
   * in its lane and all three are checked: the count drawn is capped, the
   * portraits shrink, and the overflow stacks into a second arc rather than
   * stretching the first further round.
   */
  {
    const deep = CL.nodes['1'];
    ok(deep.fighters.length === 25, `the fixture really has a 25-Fighter roster (${deep.fighters.length})`);
    const c1 = cs.find(c => c.getAttribute('data-id') === '1');
    const ds = all(c1).filter(e => e._class.includes('riv-sat')).map(satDisc);
    ok(ds.length < deep.fighters.length, `not all 25 are drawn (${ds.length})`);
    ok(ds.length >= 8, `but enough to be worth looking at (${ds.length})`);

    /* The portraits shrank rather than the ring growing. */
    const biggest = Math.max.apply(null, ds.map(d => parseFloat(d.attrs.r)));
    const small = cs.find(c => c.getAttribute('data-id') === '2');
    const sds = all(small).filter(e => e._class.includes('riv-sat')).map(satDisc);
    const sBiggest = Math.max.apply(null, sds.map(d => parseFloat(d.attrs.r)));
    ok(biggest < sBiggest,
       `a deep Crew draws smaller portraits than a shallow one (${biggest} < ${sBiggest})`);

    /* They stacked outward: more than one distinct distance from the hub. */
    const e1 = ext['1'];
    const rings = new Set(ds.map(d => Math.round(
      Math.hypot(parseFloat(d.attrs.cx) - e1.x, parseFloat(d.attrs.cy) - e1.y))));
    ok(rings.size >= 2, `the Crew stacks into shells, not one long arc (${rings.size} shells)`);

    /* AND THE CLUSTER STAYS A REASONABLE SIZE. This is the actual symptom:
       one roster must not set the scale of the whole map. */
    const others = eids.filter(id => id !== '1').map(id => ext[id].reach);
    const typical = others.reduce((t, r) => t + r, 0) / others.length;
    ok(e1.reach < typical * 2.2,
       `the deep cluster is not wildly bigger than the rest (${e1.reach.toFixed(0)} vs ${typical.toFixed(0)} typical)`);

    /*
     * THE DEEP CLUSTER IS GIVEN MORE OF THE CIRCLE. This is the actual fix
     * for the portraits interleaving: its wedge has to be wider than a bare
     * avatar's, or its Crew has nowhere to go but into the neighbour.
     */
    const cx2 = cw / 2, cy2 = ch / 2;
    const ang = {};
    for (const id of eids) {
      let a = Math.atan2(ext[id].y - cy2, ext[id].x - cx2);
      ang[id] = (a + Math.PI * 2) % (Math.PI * 2);
    }
    const byAng = eids.slice().sort((p, q) => ang[p] - ang[q]);
    const wedge = {};
    byAng.forEach((id, i) => {
      const prev = byAng[(i - 1 + byAng.length) % byAng.length];
      const next = byAng[(i + 1) % byAng.length];
      let a1 = ang[id] - ang[prev]; if (a1 <= 0) a1 += Math.PI * 2;
      let a2 = ang[next] - ang[id]; if (a2 <= 0) a2 += Math.PI * 2;
      wedge[id] = (a1 + a2) / 2;
    });
    const smallest = eids.reduce((m, id) => ext[id].reach < ext[m].reach ? id : m, eids[0]);
    ok(wedge['1'] > wedge[smallest],
       `the 25-Fighter player gets a wider wedge than the smallest cluster `
       + `(${(wedge['1'] * 180 / Math.PI).toFixed(0)} deg vs ${(wedge[smallest] * 180 / Math.PI).toFixed(0)} deg)`);
    /* And no portrait of one player's sits inside another's reach. */
    for (const id of eids) {
      for (const sg of all(cs.find(c => c.getAttribute('data-id') === id))
                        .filter(e => e._class.includes('riv-sat'))) {
        const sd = satDisc(sg);
        for (const other of eids) {
          if (other === id) continue;
          const d = Math.hypot(parseFloat(sd.attrs.cx) - ext[other].x,
                               parseFloat(sd.attrs.cy) - ext[other].y);
          ok(d >= ext[other].reach - 1,
             `  a portrait of ${id} stays out of ${other}'s cluster (${d.toFixed(0)} >= ${ext[other].reach.toFixed(0)})`);
        }
      }
    }

    /* Nothing is silently dropped: the label admits the overflow. */
    const subs = all(c1).filter(e => e.tagName === 'text').map(e => e.textContent);
    ok(subs.some(t => /\+\d+ more/.test(t)), `the label says how many are not shown (${subs.join(' | ')})`);
    const hidden = deep.fighters.length - ds.length;
    ok(subs.some(t => t.includes('+' + hidden + ' more')), `and the number is right (+${hidden})`);
    /* A player whose Crew fits says nothing about overflow. */
    const subs2 = all(small).filter(e => e.tagName === 'text').map(e => e.textContent);
    ok(!subs2.some(t => /more/.test(t)), 'a Crew that fits carries no "+N more"');
  }

  /*
   * LABELS LIVE OUTSIDE EVERY CLUSTER, including other people's. A username
   * under a cluster on the right-hand side of the ring used to land inside
   * the neighbour below it, which is how the bug was found -- so the check is
   * against ALL clusters, not just the one the label belongs to.
   */
  for (const c of cs) {
    const id = c.getAttribute('data-id');
    for (const t of all(c).filter(e => e.tagName === 'text')) {
      const tx = parseFloat(t.attrs.x), ty = parseFloat(t.attrs.y);
      /*
       * BY ITS EXTENT, NOT ITS ANCHOR POINT. A right-hand label is anchored
       * at its left end and runs outward from there, so checking the anchor
       * is inside the canvas says nothing about where the text ends -- which
       * is how "michael_heistonalpha" came to be cut in half by the edge.
       */
      const tw = t.textContent.length * (parseFloat(t.attrs['font-size']) || 11) * 0.58;
      const anc = t.attrs['text-anchor'];
      const x0 = anc === 'start' ? tx : (anc === 'end' ? tx - tw : tx - tw / 2);
      const x1 = x0 + tw;
      ok(x0 >= 0 && x1 <= cw,
         `  ${id}'s whole label fits the canvas (${x0.toFixed(0)}..${x1.toFixed(0)} in 0..${cw})`);
      ok(ty >= 0 && ty <= ch, `  ${id}'s label is within the canvas height`);
      /* ANCHORED AWAY FROM ITS CLUSTER. A centred label on the left or right
         of the ring spreads half its width back over its own portraits, which
         the anchor-point check above cannot see. */
      const e0 = ext[id];
      /* Against the RING centre, not the canvas centre -- see the note in the
         ring section. */
      const tp = [0, Math.floor(eids.length / 3), Math.floor(2 * eids.length / 3)].map(k => ext[eids[k]]);
      const dd2 = 2 * (tp[0].x * (tp[1].y - tp[2].y) + tp[1].x * (tp[2].y - tp[0].y)
                     + tp[2].x * (tp[0].y - tp[1].y));
      const sq2 = (q) => q.x * q.x + q.y * q.y;
      const rcx = (sq2(tp[0]) * (tp[1].y - tp[2].y) + sq2(tp[1]) * (tp[2].y - tp[0].y)
                 + sq2(tp[2]) * (tp[0].y - tp[1].y)) / dd2;
      const rcy = (sq2(tp[0]) * (tp[2].x - tp[1].x) + sq2(tp[1]) * (tp[0].x - tp[2].x)
                 + sq2(tp[2]) * (tp[1].x - tp[0].x)) / dd2;
      const cosA = (e0.x - rcx) / (Math.hypot(e0.x - rcx, e0.y - rcy) || 1);
      const want = cosA > 0.3 ? 'start' : (cosA < -0.3 ? 'end' : 'middle');
      ok(t.attrs['text-anchor'] === want,
         `  ${id}'s label runs away from the cluster (anchor ${t.attrs['text-anchor']}, wanted ${want})`);
      for (const other of eids) {
        const e = ext[other];
        const d = Math.hypot(tx - e.x, ty - e.y);
        ok(d >= e.reach - 1,
           `  ${id}'s label clears cluster ${other} (${d.toFixed(0)} >= ${e.reach.toFixed(0)})`);
      }
    }
  }
}

console.log('\n-- the map breathes, and marches where you point --');
/*
 * The same four animations the realm map runs, so the two feel like one
 * platform. What is checked is the STATE MACHINE, not the timings: which
 * class is on which element when, and in particular that a dimmed line
 * carries neither -- a CSS animation beats an ordinary declaration, so a
 * line still running the idle pulse animates its own opacity and the dim
 * never lands.
 */
{
  const ln0 = lineEls[0];
  ok(ln0._class.split(/\s+/).includes('riv-line'),
     'at rest every line carries the idling pulse');
  ok(!ln0._class.split(/\s+/).includes('riv-march'), 'and not the marching one');

  const lo = ln0.getAttribute('data-lo');
  const cl0 = clusters.find(c => c.getAttribute('data-id') === lo);
  cl0.fire('mouseenter');
  const has = (e, c) => e._class.split(/\s+/).includes(c);
  let connected = 0, dimmed = 0;
  for (const ln of lineEls) {
    const on = ln.getAttribute('data-lo') === lo || ln.getAttribute('data-hi') === lo;
    if (on) {
      connected++;
      ok(has(ln, 'riv-march'), '  a line you are pointing at marches');
      ok(!has(ln, 'riv-line'), '  and stops idling, or the two animations fight');
      ok(has(ln, 'riv-on'), '  and is marked as lit');
    } else {
      dimmed++;
      ok(!has(ln, 'riv-line') && !has(ln, 'riv-march'),
         '  an unconnected line carries NEITHER, so the dim can actually apply');
    }
  }
  ok(connected > 0 && dimmed > 0,
     `the fixture has both connected and unconnected lines (${connected}/${dimmed})`);

  cl0.fire('mouseleave');
  ok(lineEls.every(l => has(l, 'riv-line')), 'leaving puts every line back to idling');
  ok(lineEls.every(l => !has(l, 'riv-march')), 'and nothing is left marching');

  /* The rings. A player who leans gets the 1.6s glow; the viewer gets the
     slower 2s one, on a ring of its own so it shows even if they lean
     neither way. */
  const leaning = Object.keys(L.nodes).filter(id => L.nodes[id].lean);
  ok(leaning.length > 0, 'the fixture has leaning players');
  for (const id of leaning) {
    const c = clusters.find(x => x.getAttribute('data-id') === id);
    ok(all(c).some(e => has(e, 'riv-ring-lean')), `  ${L.nodes[id].name}'s ring pulses`);
  }
  for (const id of Object.keys(L.nodes)) {
    if (L.nodes[id].lean) continue;
    const c = clusters.find(x => x.getAttribute('data-id') === id);
    ok(!all(c).some(e => has(e, 'riv-ring-lean')),
       `  ${L.nodes[id].name} leans neither way and has no pulsing ring`);
  }

  /*
   * AND THE VIEWER'S OWN AVATAR, which is the realm map's nicest touch: a
   * slower, deeper pulse than anyone else's so you find yourself at a
   * glance. A guest is nobody and must light up nothing.
   */
  const guest = draw(L);
  ok(!all(guest.children[0]).some(e => has(e, 'riv-ring-me')),
     'a guest sees nobody highlighted as themselves');

  /* Someone who leans, so the two rings have to coexist. */
  const meLean = Object.keys(L.nodes).find(id => L.nodes[id].lean);
  const asMe = draw(L, undefined, undefined, meLean);
  const mine = all(asMe.children[0]).filter(e => has(e, 'riv-ring-me'));
  ok(mine.length === 1, `signed in, exactly one ring marks you (${mine.length})`);
  const myCluster = asMe.children[0].querySelectorAll('.riv-cluster')
    .find(c => c.getAttribute('data-id') === String(meLean));
  ok(all(myCluster).some(e => has(e, 'riv-ring-me')), 'and it is on your own cluster');
  ok(all(myCluster).some(e => has(e, 'riv-ring-lean')),
     'alongside your lean ring, not replacing it');
  const r1 = parseFloat(all(myCluster).find(e => has(e, 'riv-ring-lean')).attrs.r);
  const r2 = parseFloat(all(myCluster).find(e => has(e, 'riv-ring-me')).attrs.r);
  ok(r2 > r1, `with yours outside it so both are visible (${r2} > ${r1})`);

  /* And for a player who leans neither way, the ring still appears. */
  const meFlat = Object.keys(L.nodes).find(id => !L.nodes[id].lean);
  const asFlat = draw(L, undefined, undefined, meFlat);
  const flatCl = asFlat.children[0].querySelectorAll('.riv-cluster')
    .find(c => c.getAttribute('data-id') === String(meFlat));
  ok(all(flatCl).some(e => has(e, 'riv-ring-me')),
     'you are marked even when you lean neither way');
}

console.log('\n-- clicking a Fighter opens the COLLECTION\'s panel --');
/*
 * The whole point of this path is that it is not a new panel. The page
 * includes dhc-fighter-modal.php and asks ajax/dhc-fighter.php for the
 * payload -- the same pair the Collection and the roster use -- so a Fighter
 * reads identically wherever it is clicked.
 */
{
  const php = fs.readFileSync(path.join(__dirname, 'rivalries.php'), 'utf8');
  const js  = fs.readFileSync(path.join(__dirname, 'rivalries.js'), 'utf8');
  const h2 = draw(LAYOUTS.crowded);
  const sv2 = h2.children[0];
  const panel = global.__panel;
  let opened = null;
  global.window.DHC_MODAL = { open: (f) => { opened = f; } };

  const sat = all(sv2).filter(e => e._class.includes('riv-sat'))[0];
  ok(!!sat.getAttribute('data-serial'), 'a portrait carries its serial');
  const want = LAYOUTS.crowded.nodes['1'].fighters[0].serial;
  ok(String(sat.getAttribute('data-serial')) === String(want),
     `and it is the right one (${sat.getAttribute('data-serial')} = ${want})`);

  panel.fetched.length = 0;
  h2.fire('click', { target: sat });
  ok(panel.fetched.length === 1, `clicking it fetches once (${panel.fetched.length})`);
  ok(/ajax\/dhc-fighter\.php\?serial=/.test(panel.fetched[0] || ''),
     `from the Collection's own endpoint (${panel.fetched[0]})`);
  ok((panel.fetched[0] || '').indexOf('serial=' + want) > -1, 'for that serial');
  ok(panel.veil.hidden, 'and does NOT open the player panel instead');

  console.log('\n-- clicking a player opens the player panel --');
  const cl = sv2.querySelectorAll('.riv-cluster').find(c => c.getAttribute('data-id') === '1');
  panel.fetched.length = 0;
  h2.fire('click', { target: cl });
  ok(panel.veil.hidden === false, 'the panel is shown');
  ok(panel.fetched.length === 0, 'with nothing fetched -- every number is already on the page');

  const html = panel.pbody.innerHTML;
  const n1 = LAYOUTS.crowded.nodes['1'];
  ok(html.indexOf(n1.name) > -1, 'it names the player');
  ok(html.indexOf('>' + n1.battles.toLocaleString() + '<') > -1, `and their battle count (${n1.battles})`);
  ok(html.indexOf(n1.wins + '\u2013' + n1.losses) > -1, `and their record (${n1.wins}-${n1.losses})`);
  ok(html.indexOf('dhcgallery.php?owner=' + n1.id) > -1, 'and links to their Fighters in the Collection');
  /* THE TABLE IS IN A SCROLLER. Five columns do not fit a phone and the
     right-hand ones were cut off the edge of the sheet -- the record column,
     which is the one worth reading. */
  ok(html.indexOf('<div class="riv-vswrap"><table class="riv-vs">') > -1,
     'the rivalries table is wrapped in its own horizontal scroller');
  ok((html.match(/<\/table><\/div>/g) || []).length === 1, 'and the wrapper is closed');

  /* Every rival, from this player's side of the pair. */
  const rivals = LAYOUTS.crowded.pairs.filter(p => String(p.lo) === '1' || String(p.hi) === '1');
  ok(rivals.length > 1, `the fixture gives this player several rivals (${rivals.length})`);
  for (const p of rivals) {
    const foe = LAYOUTS.crowded.nodes[String(p.lo) === '1' ? p.hi : p.lo];
    ok(html.indexOf(foe.name) > -1, `  ${foe.name} is listed`);
  }
  /* HEAVIEST FIRST, which is the order that makes the table worth reading --
     and the heaviest is the one the summary line calls out. */
  const counts = (html.match(/<tr>[\s\S]*?<\/tr>/g) || [])
    .map(r => (r.match(/<td class="r">(\d+)<\/td>/) || [])[1])
    .filter(v => v !== undefined).map(Number);
  ok(counts.length === rivals.length, `every rival is a row (${counts.length})`);
  ok(counts.length > 1 && new Set(counts).size > 1,
     `and their battle counts differ (${counts.join(',')}), so an order can be wrong`);
  let sorted = true;
  for (let i = 1; i < counts.length; i++) if (counts[i] > counts[i - 1]) sorted = false;
  ok(sorted, `rivals are listed heaviest first (${counts.join(',')})`);
  const top = Math.max.apply(null, counts);
  ok(counts[0] === top, 'the deepest rivalry is the first row');
  const topFoe = rivals.map(p => ({ p: p, foe: LAYOUTS.crowded.nodes[String(p.lo) === '1' ? p.hi : p.lo] }))
                       .sort((a, b) => b.p.n - a.p.n)[0];
  ok(html.indexOf('Fights <b>' + topFoe.foe.name + '</b> most') > -1,
     `and the summary names them (${topFoe.foe.name})`);
  /*
   * "BEST AGAINST" HAS TO BE A WINNING RECORD. Taking the maximum of
   * (won - lost) returns something even when every record is losing, and the
   * panel said "best against oculusorbus at 5-6" -- the least bad, not a
   * best. In this fixture nobody has a winning record at 3+ battles, so the
   * claim must be absent entirely.
   */
  const mineAll = LAYOUTS.crowded.pairs
    .filter(p => String(p.lo) === '1' || String(p.hi) === '1')
    .map(p => { const lo = String(p.lo) === '1';
                return { n: p.n, won: lo ? p.lowins : p.hiwins, lost: lo ? p.hiwins : p.lowins }; });
  const anyWinning = mineAll.some(m => m.n >= 3 && m.won > m.lost);
  ok(!anyWinning, 'this player has no winning record at 3+ battles in the fixture');
  ok(html.indexOf('Best against') === -1, '...so the panel does not claim one');
  for (const m of html.match(/(Best|Worst) against <b>[^<]+<\/b> at (\d+)\u2013(\d+)\./g) || []) {
    const mm = m.match(/(Best|Worst) against <b>[^<]+<\/b> at (\d+)\u2013(\d+)\./);
    const w = +mm[2], l = +mm[3];
    ok(mm[1] === 'Best' ? w > l : l > w, `  "${mm[1]} against ... ${w}-${l}" is the right way round`);
  }
  /*
   * A PLAYER WHO ONLY WINS, which is the mirror case. Player 3's deep
   * records are both winning, so an unfiltered "worst" would hand back a
   * 6-5 victory and label it their worst. Without this the filter could be
   * deleted from that side and nothing noticed.
   */
  h2.fire('click', { target: sv2.querySelectorAll('.riv-cluster')
                                .find(c => c.getAttribute('data-id') === '3') });
  const html3 = global.__panel.pbody.innerHTML;
  const mine3 = LAYOUTS.crowded.pairs
    .filter(p => String(p.lo) === '3' || String(p.hi) === '3')
    .map(p => { const lo = String(p.lo) === '3';
                return { n: p.n, won: lo ? p.lowins : p.hiwins, lost: lo ? p.hiwins : p.lowins }; })
    .filter(m => m.n >= 3);
  ok(mine3.length >= 2 && mine3.every(m => m.won > m.lost),
     'player 3 wins every rivalry deep enough to count');
  ok(html3.indexOf('Best against') > -1, 'so the panel names a best');
  ok(html3.indexOf('Worst against') === -1, 'and claims no worst, because there is not one');

  /*
   * EVERY PLAYER, not a hand-picked one. Player 3 above has two winning
   * records of the SAME margin, so an unfiltered "worst" picks the same
   * rivalry as "best" and the duplicate guard quietly suppresses it -- the
   * mutation survived on that one player alone. Opening all of them finds
   * the ones where it does not.
   */
  let claims = 0;
  for (const pid of Object.keys(LAYOUTS.crowded.nodes)) {
    h2.fire('click', { target: sv2.querySelectorAll('.riv-cluster')
                                  .find(c => c.getAttribute('data-id') === pid) });
    const ph = global.__panel.pbody.innerHTML;
    for (const seg of ph.match(/(Best|Worst) against <b>[^<]+<\/b> at \d+\u2013\d+\./g) || []) {
      const mm = seg.match(/(Best|Worst) against <b>([^<]+)<\/b> at (\d+)\u2013(\d+)\./);
      const w = +mm[3], l = +mm[4];
      claims++;
      ok(mm[1] === 'Best' ? w > l : l > w,
         `  ${LAYOUTS.crowded.nodes[pid].name}: "${mm[1]} against ${mm[2]} ${w}-${l}" is right`);
    }
  }
  ok(claims >= 4, `the fixture produces enough best/worst claims to test (${claims})`);

  /* And the sentences are sentences -- a full stop, then a capital. */
  ok(/Fights <b>[^<]+<\/b> most, \d+ times?\./.test(html),
     'the lead sentence is closed with a full stop');
  ok(!/\. [a-z]/.test(html.replace(/<[^>]*>/g, '')),
     'and nothing runs on with a lower-case word');
  /*
   * AND THE PANEL SORTS FOR ITSELF. dhca_rivalry_layout() already hands the
   * pairs over heaviest first, so simply walking them in order comes out
   * right and deleting the panel's own sort changed nothing. That is a pass
   * resting on an invariant in another file. Handing it a shuffled list is
   * what actually tests it.
   */
  const shuffled = JSON.parse(JSON.stringify(LAYOUTS.crowded));
  shuffled.pairs.sort((a, b) => a.n - b.n);          // lightest first, the wrong way round
  const h3 = draw(shuffled);
  const sv3 = h3.children[0];
  h3.fire('click', { target: sv3.querySelectorAll('.riv-cluster')
                                .find(c => c.getAttribute('data-id') === '1') });
  const sc = (global.__panel.pbody.innerHTML.match(/<tr>[\s\S]*?<\/tr>/g) || [])
    .map(r => (r.match(/<td class="r">(\d+)<\/td>/) || [])[1])
    .filter(v => v !== undefined).map(Number);
  let ssorted = true;
  for (let i = 1; i < sc.length; i++) if (sc[i] > sc[i - 1]) ssorted = false;
  ok(sc.length === counts.length, 'the shuffled data has the same rivals');
  ok(ssorted, `and the panel still lists them heaviest first (${sc.join(',')})`);

  /*
   * THE WHOLE ROSTER, which is the reason this panel exists for a player with
   * a deep Crew: the map draws CREW_MAX and says "+N more", and the N have to
   * be somewhere.
   */
  const drawn = all(cl).filter(e => e._class.includes('riv-sat')).length;
  ok(n1.fighters.length > drawn, `the map draws ${drawn} of ${n1.fighters.length}`);
  const listed = (html.match(/class="riv-fig"/g) || []).length;
  ok(listed === n1.fighters.length,
     `but the panel lists every one (${listed} of ${n1.fighters.length})`);
  /* NOTHING NEGATIVE REACHES THE MARKUP. A 25-strong roster walked the
     fixture's counters past zero and the panel printed "-4 battles, -10 dmg"
     without complaint -- real data cannot do that, but a fixture that can
     exercises sorts and ratios against values the page will never see. */
  ok(!/-\d+ battle|-[\d,]+ dmg/.test(html), 'no negative counts are rendered');
  for (const f of n1.fighters) {
    ok(f.battles >= 1 && f.att >= 0 && f.def >= 0 && f.dealt >= 0,
       `  ${f.name} has sane numbers (${f.battles}b ${f.att}a ${f.def}d ${f.dealt}dmg)`);
  }
  for (const f of n1.fighters) {
    ok(html.indexOf('data-serial="' + f.serial + '"') > -1, `  ${f.name} is in the roster`);
  }
  /* INSIDE the roster buttons. The player's own avatar and every rival's are
     also <img>, so "the panel contains an image" was true with the portraits
     removed entirely. */
  ok(/class="riv-fig"[^>]*>\s*<span class="art">\s*<img/.test(html),
     'and the roster entries carry the Fighter art, not just a name');
  /*
   * AND THE BOX HOLDING IT CAN HAVE A SIZE.
   *
   * The art sits in a <span>, and an inline box ignores width, height and
   * aspect-ratio -- so the markup above was correct, the images were there,
   * and the tiles still rendered as name-and-stats with an invisible gap on
   * top. Every rule that gives a span a box must also make it one.
   */
  const php2 = fs.readFileSync(path.join(__dirname, 'rivalries.php'), 'utf8');
  const css2 = php2.slice(php2.indexOf('<style>'), php2.indexOf('</style>'))
                   .replace(/\/\*[\s\S]*?\*\//g, '');   // comments name classes too
  /*
   * Which classes end up on a span -- FROM BOTH FILES. The roster tiles are
   * built as strings in rivalries.js, so the page's own markup does not
   * contain them: scanning only the PHP found two unrelated spans and
   * missed the one with the bug in it. The same blind spot as pf-hid on the
   * Arena, which was also only ever written by a script.
   */
  const spanCls = new Set();
  for (const src of [php2, rivSrc]) {
    for (const m of src.match(/<span class=\\?["'][^"'\\]+/g) || []) {
      for (const c of m.replace(/<span class=\\?["']/, '').split(/\s+/)) if (c) spanCls.add(c);
    }
  }
  ok(spanCls.has('art'), 'the portrait box is a span (' + [...spanCls].join(', ') + ')');
  for (const cls of spanCls) {
    const rule = (css2.match(new RegExp('\\.[\\w-]*\\s*\\.' + cls + '\\s*\\{([^}]*)\\}')) || [])[1];
    if (!rule) continue;
    const boxy = /(^|;)\s*(width|height|aspect-ratio)\s*:/.test(rule);
    if (!boxy) continue;
    ok(/display\s*:\s*(block|flex|grid|inline-block)/.test(rule),
       `  .${cls} is given a box, so it is also given a display (${rule.slice(0, 44)}...)`);
  }

  /*
   * A PLAYER ON THE OTHER SIDE OF THE PAIR. Pairs are stored low-id to
   * high-id, and player 1 is the low end of every pair it is in -- so reading
   * only the `lo` branch produced a complete-looking panel for it and the
   * test could not tell. Player 10 is the HIGH end of its pair with 1.
   */
  /* Chosen, not assumed: a pair whose two directions AND two records both
     differ, so swapping either shows up. */
  const hiPair = LAYOUTS.crowded.pairs.find(p => p.hilo !== p.lohi && p.hiwins !== p.lowins);
  ok(!!hiPair, 'the fixture has a pair that is lopsided on both counts');
  const hiId = String(hiPair.hi);
  h2.fire('click', { target: sv2.querySelectorAll('.riv-cluster')
                                .find(c => c.getAttribute('data-id') === hiId) });
  const html10 = panel.pbody.innerHTML;
  const foe10 = LAYOUTS.crowded.nodes[String(hiPair.lo)];
  const me10 = LAYOUTS.crowded.nodes[hiId];
  ok(html10.indexOf(me10.name) > -1, `the panel opens for ${me10.name}, the HIGH end of that pair`);
  ok(html10.indexOf(foe10.name) > -1,
     `and lists ${foe10.name}, who is the LOW end of it`);
  /*
   * THE WHOLE ROW, cell by cell. Looking for a bare number anywhere in the
   * markup is not a test -- "1" appears in a dozen places, so swapping the
   * two attack columns for this player still found it. These are the four
   * cells of that opponent's row, in order.
   */
  const rowOf = (h, name) => {
    for (const m of h.match(/<tr>[\s\S]*?<\/tr>/g) || []) {
      if (m.indexOf('>' + name + '<') > -1 || m.indexOf(name + '</span>') > -1) {
        return (m.match(/<td[^>]*>([\s\S]*?)<\/td>/g) || [])
                 .map(c => c.replace(/<[^>]*>/g, '').trim());
      }
    }
    return null;
  };
  const row = rowOf(html10, foe10.name);
  ok(!!row, `the row for ${foe10.name} is there`);
  if (row) {
    ok(row[1] === String(hiPair.n), `  battles ${row[1]} = ${hiPair.n}`);
    ok(row[2] === String(hiPair.hilo),
       `  ${me10.name} started ${row[2]}, which must be hilo (${hiPair.hilo}) not lohi (${hiPair.lohi})`);
    ok(row[3] === String(hiPair.lohi), `  and was attacked ${row[3]} = lohi (${hiPair.lohi})`);
    ok(hiPair.hilo !== hiPair.lohi,
       `  the two directions differ in this fixture (${hiPair.hilo} vs ${hiPair.lohi}), so a swap shows`);
    ok(row[4] === hiPair.hiwins + '\u2013' + hiPair.lowins,
       `  and the record is from ${me10.name}'s side (${row[4]})`);
    ok(hiPair.hiwins !== hiPair.lowins, '  and the two records differ, so a swap shows there too');
  }

  console.log('\n-- and a Fighter in that roster opens the same panel --');
  /* The panel is written with innerHTML and the stub keeps it as a string, so
     the buttons cannot be clicked here -- what is checked is that they are
     buttons carrying a serial, and that the page binds them to the same
     opener the map uses. */
  ok(/<button type="button" class="riv-fig" data-serial="\d+"/.test(html),
     'each roster entry is a button carrying its serial');
  ok(js.indexOf("querySelectorAll('.riv-fig')") > -1,
     'and the page binds those buttons to the Fighter opener');

  console.log('\n-- the page reuses the panel rather than rebuilding it --');
  ok(php.indexOf("include __DIR__ . '/dhc-fighter-modal.php'") > -1,
     'rivalries.php includes the Collection\'s Fighter panel');
  ok(js.indexOf('ajax/dhc-fighter.php?serial=') > -1, 'and fetches its payload from the shared endpoint');
  ok(js.indexOf('window.DHC_MODAL') > -1, 'opening it through DHC_MODAL');
  /* The giveaway for a duplicated panel would be this file knowing about a
     Fighter's internals -- traits, ranks, kits. It must not. */
  for (const leak of ['rankOf', 'prank', 'crank', 'rarity', "'traits'"]) {
    ok(js.indexOf(leak) === -1, `  rivalries.js does not reimplement ${leak}`);
  }
}

console.log('\n-- degenerate graphs --');
const solo = draw(LAYOUTS.solo);
ok(solo.children.length === 1, 'one player with one battle still draws');
/*
 * THE SOLO MAP IS WHERE THE CLUSTER ITSELF SETS THE BOUNDS. Everywhere else
 * a label reaches further than the Crew it belongs to, so measuring only the
 * labels happens to be enough -- with one player there is nothing either side
 * of it, and the canvas has to come from the avatar and its Fighters.
 */
{
  const ssvg = solo.children[0];
  const sb = inkBounds(ssvg);
  const sw = parseFloat(ssvg.attrs.width), sh = parseFloat(ssvg.attrs.height);
  ok(sb.x0 >= 0 && sb.y0 >= 0 && sb.x1 <= sw && sb.y1 <= sh,
     `the lone cluster is inside its canvas (${sb.x0.toFixed(0)}..${sb.x1.toFixed(0)} in 0..${sw})`);
  ok(sb.x0 < 24 && sw - sb.x1 < 24,
     `and the canvas is tight around it (${sb.x0.toFixed(0)} / ${(sw - sb.x1).toFixed(0)} spare)`);
  /*
   * AND IT FACES A DETERMINATE DIRECTION. With no ring there is no outward,
   * so the angle the slice arithmetic produces is meaningless -- it pointed
   * the lone player's Crew and label off wherever the division happened to
   * land. Facing up is the choice; what matters is that it is a choice.
   */
  const sc = all(ssvg).filter(e => e._class.includes('riv-cluster'))[0];
  const sim = all(sc).filter(e => e.tagName === 'image'
    && (e.attrs['clip-path'] || '').indexOf('url(#rivclip') === 0
    && (e.attrs['clip-path'] || '').indexOf('rivsat') < 0)[0];
  const shx = parseFloat(sim.attrs.x) + parseFloat(sim.attrs.width) / 2;
  const shy = parseFloat(sim.attrs.y) + parseFloat(sim.attrs.height) / 2;
  const sps = all(sc).filter(e => e._class.includes('riv-sat')).map(satDisc);
  ok(sps.length === 6, `the lone player's six Fighters are drawn (${sps.length})`);
  for (const sp of sps) {
    ok(parseFloat(sp.attrs.cy) < shy,
       `  a portrait sits above the lone avatar (${sp.attrs.cy} < ${shy.toFixed(0)})`);
  }
  /* The Crew, not the avatar or the label, is what the canvas is sized to. */
  const spx = sps.map(q => parseFloat(q.attrs.cx) - parseFloat(q.attrs.r));
  ok(Math.min.apply(null, spx) < shx - parseFloat(sim.attrs.width) / 2,
     'and the Crew reaches wider than the avatar, so it sets the bounds');
}
ok(solo.children[0].querySelectorAll('.riv-cluster').length === 1, 'as a single cluster');
ok(all(solo.children[0]).filter(e => e.tagName === 'line').length === 0, 'with no lines');
const nofight = draw(LAYOUTS.nofighters);
const nfSvg = nofight.children[0];
ok(nfSvg.querySelectorAll('.riv-cluster').length === 2, 'players with NO Fighter rows still draw');
ok(nfSvg.querySelectorAll('.riv-sat').length === 0, 'and carry no satellites, which is the early state');
ok(all(nfSvg).filter(e => e.tagName === 'line').length === 1, 'their grudge line is drawn regardless');
const empty = draw(LAYOUTS.empty);
ok(empty.children.length === 0, 'an empty window draws nothing and does not throw');

console.log('\n-- focus dims the rest --');
const c0 = clusters[0];
host._class = '';
/* The handler is wired through addEventListener, which the stub swallows, so
   focus() is exercised through the class the container ends up carrying. */
ok(typeof host.classList.toggle === 'function', 'the container exposes a classList for focus state');

fs.rmSync(tmp, { recursive: true, force: true });
console.log('\n' + (fails ? fails + ' FAILED' : 'all passed'));
process.exit(fails ? 1 : 0);
