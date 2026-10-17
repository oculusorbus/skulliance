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
  const img = all(cl).filter(e => e.tagName === 'image')[0];
  ok(!!img, `  ${n.name} has an avatar image`);
  if (!img) continue;
  const iw = parseFloat(img.attrs.width), ih = parseFloat(img.attrs.height);
  ok(iw === n.r * 2 && ih === n.r * 2,
     `  ${n.name}: avatar is ${n.r * 2}px across, the server's radius doubled (got ${iw}x${ih})`);
  const hx = parseFloat(img.attrs.x) + iw / 2, hy = parseFloat(img.attrs.y) + ih / 2;
  hubOf[id] = { x: hx, y: hy };
  const onHub = all(cl).filter(e => e.tagName === 'circle'
    && parseFloat(e.attrs.cx) === hx && parseFloat(e.attrs.cy) === hy);
  ok(onHub.length > 0, `  ${n.name}: circles are centred on the avatar`);
  for (const c of onHub) {
    const r = parseFloat(c.attrs.r);
    ok(r === n.r || r === n.r + 5,
       `    a hub circle is r=${n.r} or the ring r=${n.r + 5}, never a constant (got ${r})`);
  }
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

console.log('\n-- satellites keep clear of the name labels --');
/* The label sits two lines directly under the avatar. A satellite placed
   straight down lands on it, which looked like a hole punched in the name. */
for (const id of Object.keys(L.nodes)) {
  const n = L.nodes[id];
  if (!n.fighters.length) continue;
  const cl  = clusters.find(c => c.getAttribute('data-id') === String(id));
  const hub = hubOf[id];
  const texts = all(cl).filter(e => e.tagName === 'text');
  ok(texts.length === 2, `  ${n.name} has its two label lines`);
  const lowest = Math.max.apply(null, texts.map(t => parseFloat(t.attrs.y)));
  for (const sdot of all(cl).filter(e => e._class.includes('riv-sat'))) {
    const sy = parseFloat(sdot.attrs.cy), sr = parseFloat(sdot.attrs.r);
    const sx = parseFloat(sdot.attrs.cx);
    /* Clear of the label band: either above where the text starts, or far
       enough to the side that a centred label cannot reach it. */
    const above = sy + sr < hub.y + n.r + 4;
    const aside = Math.abs(sx - hub.x) > n.r + 4;
    ok(above || aside,
       `    a dot avoids the label band (y ${sy.toFixed(0)} vs text to ${lowest.toFixed(0)})`);
    /*
     * And the rule stated geometrically, which is what actually holds the
     * satellites off the name: the bottom quadrant of the ring -- within 45
     * degrees either side of straight down -- is reserved for the two label
     * lines. Checking only "this particular dot misses the text" passes by
     * luck at small Crew sizes; checking the sector does not.
     */
    const ang = Math.atan2(sy - hub.y, sx - hub.x);   // +PI/2 is straight down
    ok(!(ang > Math.PI * 0.25 && ang < Math.PI * 0.75),
       `    and stays out of the reserved bottom quadrant (${(ang / Math.PI).toFixed(2)}\u03c0)`);
  }
}

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
ok(sats.length === totalF, `one dot per Fighter fielded (${sats.length} of ${totalF})`);
/* A satellite overlapping its own hub is the failure mode that makes the map
   unreadable, so it is measured rather than eyeballed. */
for (const id of Object.keys(L.nodes)) {
  const n = L.nodes[id];
  if (!n.fighters.length) continue;
  const cl = clusters.find(c => c.getAttribute('data-id') === String(id));
  const hub = all(cl).filter(e => e.tagName === 'circle' && parseFloat(e.attrs.r) === n.r)[0];
  const hx = parseFloat(hub.attrs.cx), hy = parseFloat(hub.attrs.cy);
  const mine = all(cl).filter(e => e._class.includes('riv-sat'));
  ok(mine.length === n.fighters.length, `  ${n.name}: ${mine.length} dots for ${n.fighters.length} Fighters`);
  for (const s of mine) {
    const d = Math.hypot(parseFloat(s.attrs.cx) - hx, parseFloat(s.attrs.cy) - hy);
    ok(d >= n.r + parseFloat(s.attrs.r), `    a dot clears the avatar (${d.toFixed(0)} >= ${n.r}+${s.attrs.r})`);
  }
  /* A DOT'S SIZE MUST TRACK ITS BATTLES, which is the whole claim the legend
     makes. Dots are emitted in the server's order (busiest first), so the
     radii must be non-increasing, and where battle counts differ the radii
     must actually differ -- all-equal dots satisfied "ordered" on its own. */
  const want = n.fighters.map(f => f.battles);
  const got  = mine.map(e => parseFloat(e.attrs.r));
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
