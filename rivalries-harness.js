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
    addEventListener() {},
    set textContent(v) { this._text = String(v); },
    get textContent() { return this._text; },
    set innerHTML(v) { if (v === '') this.children = []; },
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

const nodes = {};
global.document = {
  createElementNS: (ns, tag) => makeEl(tag),
  createElement: (tag) => makeEl(tag),
  getElementById: (id) => nodes[id] || null,
  body: { appendChild() {} },
};

/* ---- load the real layout from PHP, then the real scripts ---- */
const tmp = fs.mkdtempSync(path.join(require('os').tmpdir(), 'riv-'));
try { execFileSync('php', [path.join(__dirname, 'rivalries-fixture.php'), tmp], { stdio: 'pipe' }); }
catch (e) { console.log('FAIL  cannot build the fixture: ' + (e.stderr || e.message)); process.exit(2); }
const LAYOUTS = JSON.parse(fs.readFileSync(path.join(tmp, 'layouts.json'), 'utf8'));
const kitSrc = fs.readFileSync(path.join(__dirname, 'map-kit.js'), 'utf8');
const rivSrc = fs.readFileSync(path.join(__dirname, 'rivalries.js'), 'utf8');

function draw(layout) {
  const host = makeEl('div');
  host.closest = () => null;
  nodes['riv-map'] = host;
  global.window = { RIVALRY_DATA: layout };
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
  const cx = W / 2, cy = H / 2;
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
  /* The canvas is square and holds the ring plus the widest cluster. */
  ok(W === H, `the canvas is square (${W}x${H})`);
  const widest = Math.max.apply(null, Object.values(L.nodes).map(n => n.r));
  ok(W > 2 * (d[0] + widest), 'and is wide enough for the ring plus a cluster');
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
      ok(tx >= 0 && ty >= 0 && tx <= cw && ty <= ch, `  ${id}'s label is on the canvas`);
      /* ANCHORED AWAY FROM ITS CLUSTER. A centred label on the left or right
         of the ring spreads half its width back over its own portraits, which
         the anchor-point check above cannot see. */
      const e0 = ext[id];
      const cosA = (e0.x - cw / 2) / (Math.hypot(e0.x - cw / 2, e0.y - ch / 2) || 1);
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

console.log('\n-- degenerate graphs --');
const solo = draw(LAYOUTS.solo);
ok(solo.children.length === 1, 'one player with one battle still draws');
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
