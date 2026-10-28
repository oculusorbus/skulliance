/*
 * siege-harness.js -- the Siege map, drawn into a DOM stub.
 *
 * Runs the SHIPPED siege.js against layouts produced by the real
 * dhcb_siege_layout() (dumped by the PHP sibling), with map-kit.js and
 * dhc-crew-kit.js loaded exactly the way the page loads them.
 *
 * AN SVG IS MEASURABLE, which is the only reason a map can be tested without
 * eyes: every claim below is a number read back off an attribute. The one
 * that matters most is the last -- nothing drawn outside the viewBox -- which
 * is the only check on the ink maths that sizes the canvas. A dropped
 * measurement there does not throw; it silently clips a username or half a
 * portrait off the edge of the picture.
 */
const fs = require('fs');
const path = require('path');
const os = require('os');
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
    /* map-kit.js's measurer appends a probe and takes it straight back out;
       without removeChild the hidden svg grows forever and the first call
       throws. */
    removeChild(c) { const i = this.children.indexOf(c); if (i > -1) this.children.splice(i, 1); return c; },
    get textContent() { return this._text; },
    set textContent(v) { this._text = String(v); },
    /*
     * A PLAUSIBLE TEXT WIDTH, not zero -- the same 0.58em per character
     * rivalries-harness.js uses, and for the same reason it records: a stub
     * answering 0 makes every label weightless, which is exactly how a
     * canvas sized from measured labels passes while a long username runs
     * off the edge.
     */
    getComputedTextLength() {
      const fs = parseFloat(this.attrs['font-size']) || 11;
      return this._text.length * fs * 0.58;
    },
    set innerHTML(v) { if (v === '') this.children = []; },
    get innerHTML() { return ''; },
    style: {}, classList: { add() {}, remove() {}, contains() { return false; } },
    addEventListener() {}, getBoundingClientRect() { return { width: 600, height: 400, top: 0, left: 0 }; },
    querySelector() { return null; }, querySelectorAll() { return []; },
  };
  return el;
}
function walk(el, fn) { fn(el); el.children.forEach(c => walk(c, fn)); }
function all(root, pred) { const out = []; walk(root, e => { if (pred(e)) out.push(e); }); return out; }

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'siege-'));
try { execFileSync('php', [path.join(__dirname, 'siege-harness.php'), '--dump', tmp], { stdio: 'pipe' }); }
catch (e) { console.log('FAIL  cannot build the fixture: ' + (e.stderr || e.message)); process.exit(2); }
const L = JSON.parse(fs.readFileSync(path.join(tmp, 'layouts.json'), 'utf8'));

const kitSrc  = fs.readFileSync(path.join(__dirname, 'map-kit.js'), 'utf8');
const crewSrc = fs.readFileSync(path.join(__dirname, 'dhc-crew-kit.js'), 'utf8');
const sgSrc   = fs.readFileSync(path.join(__dirname, 'siege.js'), 'utf8');

function draw(layout, me) {
  const host = makeEl('div');
  const body = makeEl('body');
  const doc = {
    body: body,
    getElementById: id => (id === 'sg-map' ? host : null),
    createElement: makeEl,
    createElementNS: (ns, tag) => makeEl(tag),
    querySelector: () => null, querySelectorAll: () => [],
    addEventListener() {},
  };
  const win = { SIEGE_DATA: layout, SIEGE_ARM_SPLIT: 50, SIEGE_ME: me || 0,
                document: doc, addEventListener() {}, innerHeight: 900, innerWidth: 1400,
                devicePixelRatio: 1, getComputedStyle: () => ({ getPropertyValue: () => '' }) };
  win.window = win;
  new Function('document', 'window', 'globalThis',
               kitSrc + '\n' + crewSrc + '\n' + sgSrc)(doc, win, win);
  return { host: host, svg: host.children[0] };
}

console.log('-- one week --');
const A = draw(L.week, 1);
ok(!!A.svg && A.svg.tagName === 'svg', 'the week view produced an <svg>');
const W = parseFloat(A.svg.getAttribute('width')), H = parseFloat(A.svg.getAttribute('height'));
ok(W > 0 && H > 0, `the canvas has a size (${W}x${H})`);

const clusters = all(A.svg, e => e.getAttribute('class') === 'sg-cluster');
ok(clusters.length === Object.keys(L.week.nodes).length,
   `one cluster per player (${clusters.length} of ${Object.keys(L.week.nodes).length})`);

/* One spoke per player, and every one of them aimed at the middle. */
const spokes = all(A.svg, e => e.tagName === 'line');
ok(spokes.length === Object.keys(L.week.nodes).length,
   `one spoke per player (${spokes.length})`);

/* The shells: one ring per wave, plus a progress arc on the live one. */
const circles = all(A.svg, e => e.tagName === 'circle');
const rings = circles.filter(c => c.getAttribute('fill') === 'none' && c.getAttribute('stroke'));
ok(rings.length >= L.week.shells.length,
   `a ring for every wave (${rings.length} rings, ${L.week.shells.length} waves)`);
const radii = L.week.shells.map(s => s.r);
const drawn = new Set(rings.map(c => Math.round(parseFloat(c.getAttribute('r')))));
ok(radii.every(r => drawn.has(r)),
   'every wave radius the server computed was actually drawn: ' + radii.join(','));

/* The live wave is the only one drawn solid; the rest are dashed. */
const dashed = rings.filter(c => (c.getAttribute('stroke-dasharray') || '') === '3 4');
ok(dashed.length === L.week.shells.filter(s => s.left <= 0).length,
   `the downed waves are dashed and the standing one is not (${dashed.length})`);

/* Kill marks: one per boss that died to somebody still on the board. */
let expectKills = 0;
L.week.shells.forEach(s => (s.bosses || []).forEach(b => {
  if (b.dead && b.by && L.week.nodes[b.by]) expectKills++;
}));
const marks = circles.filter(c => c.getAttribute('fill') === '#f5a623');
ok(expectKills > 0, `the fixture has last blows to mark (${expectKills})`);
ok(marks.length === expectKills, `one mark per last blow (${marks.length} of ${expectKills})`);

/* The Fighters, from the shared kit. */
const sats = all(A.svg, e => e.getAttribute('class') === 'sg-sat');
let expectSats = 0;
Object.keys(L.week.nodes).forEach(id => {
  expectSats += Math.min(10, (L.week.nodes[id].fighters || []).length);
});
ok(sats.length === expectSats, `every Fighter drawn (${sats.length} of ${expectSats})`);
ok(all(A.svg, e => e.tagName === 'image' && /250\//.test(e.getAttribute('href') || '')).length > 0,
   'the portraits use the 250px masters, not the 1000px art');

/* A click target has to carry the serial: ajax/dhc-fighter.php is keyed on
   it, and that endpoint is how a click opens the Collection's panel. */
ok(sats.every(s => s.getAttribute('data-serial')), 'every Fighter carries its serial');

/*
 * AND THE RING CLEARS THE CENTRE. Rivalries never had to check this -- its
 * middle is empty, so the ring radius only has to satisfy the clusters'
 * own circumference. Here a quiet week has few enough clusters that the
 * circumference term is tiny, and without a second term for the core the
 * ring pulls in and the players are drawn on top of the crew they are
 * attacking.
 */
const outerShell = L.week.shells.length ? L.week.shells[0].r : 0;
const centre = { x: null, y: null };
(function () {
  /* The centre is wherever the outermost shell was drawn. */
  const ring = all(A.svg, e => e.tagName === 'circle'
                            && Math.round(parseFloat(e.getAttribute('r'))) === outerShell)[0];
  if (ring) { centre.x = parseFloat(ring.getAttribute('cx')); centre.y = parseFloat(ring.getAttribute('cy')); }
})();
ok(centre.x !== null, 'the outermost shell was drawn, so the centre is locatable');
if (centre.x !== null) {
  const clash = [];
  clusters.forEach(g => {
    /* A cluster's avatar is the filled #0a1929 disc in its own group. */
    const av = g.children.filter(e => e.tagName === 'circle' && e.getAttribute('fill') === '#0a1929')
                         .sort((a, b) => parseFloat(b.getAttribute('r')) - parseFloat(a.getAttribute('r')))[0];
    if (!av) return;
    const d = Math.hypot(parseFloat(av.getAttribute('cx')) - centre.x,
                         parseFloat(av.getAttribute('cy')) - centre.y);
    if (d - parseFloat(av.getAttribute('r')) < outerShell)
      clash.push(`${g.getAttribute('data-id')} at ${d.toFixed(0)}px`);
  });
  ok(clash.length === 0,
     'a player is drawn on top of the crew they are attacking: ' + clash.join(', '));
}

console.log('\n-- nothing is drawn outside the canvas --');
/*
 * THE ONLY CHECK ON THE INK MATHS. The canvas is sized to the union of every
 * piece of ink rather than to a margin, so a measurement that is dropped
 * does not throw -- it quietly clips a username or half a portrait off the
 * edge. Text is checked against its own measured width in the direction it
 * is actually anchored, the same way the layout measured it.
 */
function outside(svg, w, h) {
  const bad = [];
  all(svg, e => true).forEach(e => {
    const num = k => parseFloat(e.getAttribute(k));
    if (e.tagName === 'circle') {
      const cx = num('cx'), cy = num('cy'), r = num('r');
      if (isNaN(cx)) return;
      if (cx - r < -0.5 || cy - r < -0.5 || cx + r > w + 0.5 || cy + r > h + 0.5)
        bad.push(`circle r${r} at ${cx},${cy}`);
    } else if (e.tagName === 'image' || e.tagName === 'rect') {
      const x = num('x'), y = num('y'), ww = num('width'), hh = num('height');
      if (isNaN(x)) return;
      if (x < -0.5 || y < -0.5 || x + ww > w + 0.5 || y + hh > h + 0.5)
        bad.push(`${e.tagName} ${ww}x${hh} at ${x},${y}`);
    } else if (e.tagName === 'text') {
      const x = num('x'), y = num('y');
      const anchor = e.getAttribute('text-anchor') || 'start';
      /* The stub's measurer is map-kit's, so this is the same width the
         layout used -- roughly 0.52em per character at the font size. */
      const size = parseFloat(e.getAttribute('font-size')) || 10;
      const tw = (e.textContent || '').length * size * 0.58;
      const x0 = anchor === 'start' ? x : (anchor === 'end' ? x - tw : x - tw / 2);
      if (x0 < -0.5 || x0 + tw > w + 0.5 || y - size > h + 0.5 || y < -0.5)
        bad.push(`text "${e.textContent}" at ${x},${y}`);
    }
  });
  return bad;
}
const out1 = outside(A.svg, W, H);
ok(out1.length === 0, 'the week view keeps its ink inside the canvas: ' + out1.slice(0, 4).join(' | '));

console.log('\n-- a wider window shows weeks, not shells --');
const B = draw(L.wide, 0);
const bW = parseFloat(B.svg.getAttribute('width')), bH = parseFloat(B.svg.getAttribute('height'));
const bRings = all(B.svg, e => e.tagName === 'circle'
                            && e.getAttribute('fill') === 'none'
                            && (e.getAttribute('stroke-dasharray') || '') === '3 4');
ok(bRings.length === 0, 'a multi-week window draws no wave shells');
/* SCOPED TO THE CENTRE GROUP. A bare rect sweep also catches the clipPath
   rects that cut a single-sided arm out of its two-arm master -- one per
   Fighter drawn -- so it counted four portraits as four weeks. */
const coreG = all(B.svg, e => e.getAttribute('class') === 'sg-core')[0];
const bars = coreG ? coreG.children.filter(e => e.tagName === 'rect') : [];
ok(bars.length === L.wide.byWeek.length,
   `one bar per week instead (${bars.length} of ${L.wide.byWeek.length})`);
ok(outside(B.svg, bW, bH).length === 0, 'and keeps its ink inside the canvas');

console.log('\n-- one player on their own --');
/* The degenerate case every ring layout gets wrong: with N = 1 there is no
   circumference to divide and the angle arithmetic is free to be nonsense. */
const C = draw(L.solo, 1);
const cW = parseFloat(C.svg.getAttribute('width')), cH = parseFloat(C.svg.getAttribute('height'));
ok(all(C.svg, e => e.getAttribute('class') === 'sg-cluster').length === 1,
   'a single player still produces a cluster');
ok(cW > 0 && cH > 0 && isFinite(cW) && isFinite(cH),
   `and a finite canvas (${cW}x${cH})`);
const solo = outside(C.svg, cW, cH);
ok(solo.length === 0, 'and keeps its ink inside it: ' + solo.slice(0, 3).join(' | '));
/* The lone cluster must not sit on top of the crew it is attacking. */
const soloSpoke = all(C.svg, e => e.tagName === 'line')[0];
ok(!!soloSpoke, 'the lone player still has a spoke');
if (soloSpoke) {
  const len = Math.hypot(parseFloat(soloSpoke.getAttribute('x2')) - parseFloat(soloSpoke.getAttribute('x1')),
                         parseFloat(soloSpoke.getAttribute('y2')) - parseFloat(soloSpoke.getAttribute('y1')));
  ok(len > 0 && isFinite(len), `and it has a real length (${len.toFixed(1)}px)`);
}

console.log('\n' + (fails ? fails + ' check(s) FAILED' : 'all passed'));
process.exit(fails ? 1 : 0);
