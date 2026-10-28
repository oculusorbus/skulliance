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
/*
 * THE STUB HAS TO BE WRONG IN THE SAME WAYS THE DOM IS, or it grades a
 * different renderer than the one that ships.
 *
 * `children` was a plain Array here. In a browser it is an HTMLCollection,
 * which has NO forEach -- and siege.js walked the tree with
 * `(el.children || []).forEach ? ... : null`, so live it never recursed at
 * all, collected nothing, and hover did nothing. Every check passed,
 * because the Array in this file answered a method the real thing does not
 * have. So children is array-LIKE here now: indices and length, no
 * iteration helpers, exactly like the real one.
 *
 * classList, querySelectorAll and closest are real for the same reason:
 * stubbing them to no-ops is how an interaction layer gets shipped without
 * ever being exercised.
 */
function makeEl(tag) {
  const kids = [];
  const el = {
    tagName: tag, _kids: kids, attrs: {}, _class: '', _text: '', parent: null,
    setAttribute(k, v) { if (k === 'class') this._class = String(v); this.attrs[k] = String(v); },
    getAttribute(k) { return k === 'class' ? (this._class || null) : (k in this.attrs ? this.attrs[k] : null); },
    removeAttribute(k) { delete this.attrs[k]; if (k === 'class') this._class = ''; },
    appendChild(c) { c.parent = this; kids.push(c); return c; },
    removeChild(c) { const i = kids.indexOf(c); if (i > -1) kids.splice(i, 1); return c; },
    /* An HTMLCollection: indices and length, and nothing else. */
    get children() {
      const col = { length: kids.length, item: i => kids[i] || null };
      kids.forEach((k, i) => { col[i] = k; });
      return col;
    },
    get textContent() { return this._text; },
    set textContent(v) { this._text = String(v); },
    /*
     * A PLAUSIBLE TEXT WIDTH, not zero -- the same 0.58em per character
     * rivalries-harness.js uses, and for the reason it records: a stub
     * answering 0 makes every label weightless, which is exactly how a
     * canvas sized from measured labels passes while a long username runs
     * off the edge.
     */
    getComputedTextLength() {
      const fs = parseFloat(this.attrs['font-size']) || 11;
      return this._text.length * fs * 0.58;
    },
    _html: '',
    set innerHTML(v) { this._html = String(v); if (v === '') kids.length = 0; },
    get innerHTML() { return this._html; },
    get classList() {
      const self = this;
      const list = () => (self._class ? self._class.split(/[ ]+/).filter(Boolean) : []);
      const put = a => { self._class = a.join(' '); self.attrs['class'] = self._class; };
      return {
        contains: c => list().indexOf(c) > -1,
        add(c) { const a = list(); if (a.indexOf(c) < 0) { a.push(c); put(a); } },
        remove(c) { put(list().filter(x => x !== c)); },
        toggle(c, on) { (on === undefined ? !this.contains(c) : on) ? this.add(c) : this.remove(c); },
      };
    },
    /* Enough of a selector engine for ".cls" and "tag", which is all either
       map asks for. Anything else throws rather than quietly matching
       nothing -- a selector that silently returns [] is how this whole
       layer went untested. */
    matches(sel) {
      if (sel.charAt(0) === '.') return this.classList.contains(sel.slice(1));
      if (/^[a-z]+$/i.test(sel)) return this.tagName === sel;
      if (/^[a-z]*\[[a-z-]+\]$/i.test(sel)) {
        const m = sel.match(/^([a-z]*)\[([a-z-]+)\]$/i);
        return (!m[1] || this.tagName === m[1]) && this.getAttribute(m[2]) != null;
      }
      throw new Error('stub selector not supported: ' + sel);
    },
    closest(sel) { let n = this; while (n) { if (n.matches && n.matches(sel)) return n; n = n.parent; } return null; },
    querySelectorAll(sel) {
      const out = [];
      (function go(n) { kidsOf(n).forEach(c => { if (c.matches(sel)) out.push(c); go(c); }); })(this);
      return out;
    },
    querySelector(sel) { return this.querySelectorAll(sel)[0] || null; },
    _on: {},
    addEventListener(t, fn) { (this._on[t] = this._on[t] || []).push(fn); },
    /* Bubbling, because the click handling is delegated to the host. */
    fire(t, ev) {
      ev = ev || {}; ev.target = ev.target || this;
      let n = this;
      while (n) { (n._on[t] || []).forEach(fn => fn(ev)); n = n.parent; }
    },
    style: {},
    getBoundingClientRect() { return { width: 600, height: 400, top: 0, left: 0, bottom: 400, right: 600 }; },
  };
  return el;
}
function kidsOf(el) { return el && el._kids ? el._kids : []; }

function walk(el, fn) { fn(el); kidsOf(el).forEach(c => walk(c, fn)); }
function all(root, pred) { const out = []; walk(root, e => { if (pred(e)) out.push(e); }); return out; }

const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'siege-'));
try { execFileSync('php', [path.join(__dirname, 'siege-harness.php'), '--dump', tmp], { stdio: 'pipe' }); }
catch (e) { console.log('FAIL  cannot build the fixture: ' + (e.stderr || e.message)); process.exit(2); }
const L = JSON.parse(fs.readFileSync(path.join(tmp, 'layouts.json'), 'utf8'));

const kitSrc  = fs.readFileSync(path.join(__dirname, 'map-kit.js'), 'utf8');
const crewSrc = fs.readFileSync(path.join(__dirname, 'dhc-crew-kit.js'), 'utf8');
/* dhc-map-export.js owns the download/copy buttons, shared with the
   Rivalries map. Loaded the same way the page loads it. */
const expSrc  = fs.readFileSync(path.join(__dirname, 'dhc-map-export.js'), 'utf8');
const sgSrc   = fs.readFileSync(path.join(__dirname, 'siege.js'), 'utf8');

function draw(layout, me) {
  const host = makeEl('div');
  const body = makeEl('body');
  /* The page's own panel elements, so the click paths have somewhere to go.
     A stub that answers null for these makes openPlayer() return early and
     a broken click look exactly like a working one. */
  const veil = makeEl('div'); veil.hidden = true;
  const pbody = makeEl('div');
  const closeBtn = makeEl('button');
  const dlBtn = makeEl('button'), cpBtn = makeEl('button'), note = makeEl('span');
  const byId = { 'sg-map': host, 'sg-veil': veil, 'sg-body': pbody, 'sg-close': closeBtn,
                 'sg-download': dlBtn, 'sg-copy': cpBtn, 'sg-shot-note': note };
  const doc = {
    body: body,
    getElementById: id => byId[id] || null,
    createElement: makeEl,
    createElementNS: (ns, tag) => makeEl(tag),
    querySelector: () => null, querySelectorAll: () => [],
    addEventListener() {},
  };
  const opened = { fighters: [] };
  const win = { SIEGE_DATA: layout, SIEGE_ARM_SPLIT: 50, SIEGE_ME: me || 0,
                document: doc, addEventListener() {}, innerHeight: 900, innerWidth: 1400,
                devicePixelRatio: 1, getComputedStyle: () => ({ getPropertyValue: () => '' }),
                DHC_MODAL: { open(f) { opened.fighters.push(f); } },
                fetch: url => { opened.fetched = url;
                  return Promise.resolve({ ok: false, status: 0,
                    json: () => Promise.resolve({ ok: true, fighter: { serial: 1 } }) }); },
                /*
                 * EVERYTHING dhc-map-export.js GUARDS ON, so the real path
                 * runs rather than the "this browser cannot" branch -- which
                 * hides both buttons and makes a dead wiring look deliberate.
                 *
                 * requestIdleCallback IS STRICT ON PURPOSE, the same as in
                 * rivalries-harness.js: its second argument is an
                 * IdleRequestOptions object, not setTimeout's milliseconds,
                 * and the real one throws on a number. A lenient stub lets
                 * the exact bug that once killed both buttons straight
                 * through.
                 */
                requestIdleCallback(fn, opts) {
                  if (opts !== undefined && (typeof opts !== 'object' || opts === null))
                    throw new TypeError("Failed to execute 'requestIdleCallback' on 'Window': "
                      + 'The provided value is not of type IdleRequestOptions.');
                  return 1;
                },
                setTimeout(fn, ms) {
                  if (typeof ms !== 'number' && ms !== undefined) throw new TypeError('bad delay');
                  return 1;
                },
                XMLSerializer: function () { this.serializeToString = () => '<svg/>'; },
                FileReader: function () {},
                sklImage: { canvasBlob: () => ({}), save: () => Promise.resolve(''),
                            copy: () => Promise.resolve(''), canCopy: () => true } };
  win.window = win;
  /* fetch as a PARAMETER. A bare `fetch` in the map resolves to the real
     global one in node, not to win.fetch -- so the click would fire a live
     request at a relative URL and the check would see nothing. In a browser
     the two are the same object; here they have to be made so. */
  new Function('document', 'window', 'globalThis', 'fetch',
               kitSrc + '\n' + crewSrc + '\n' + expSrc + '\n' + sgSrc)(doc, win, win, win.fetch);
  return { host: host, svg: kidsOf(host)[0], veil: veil, pbody: pbody, opened: opened,
           dlBtn: dlBtn, cpBtn: cpBtn, note: note };
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
/* CLASS-CONTAINS, not equality: a disassembled Fighter carries
   'sg-sat sg-sat-gone' and an equality test silently stopped counting it. */
const hasCls = (e, c) => ((e.getAttribute('class') || '').split(/[ ]+/).indexOf(c) > -1);
const sats = all(A.svg, e => hasCls(e, 'sg-sat'));
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
    const av = kidsOf(g).filter(e => e.tagName === 'circle' && e.getAttribute('fill') === '#0a1929')
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
      /* THE BASELINE, not the top of the glyphs. `y - size > h` only fires
         once the whole line is below the canvas, so a caption hanging a few
         pixels off the bottom edge read as inside -- which is precisely the
         overflow the centre measurement exists to prevent. */
      if (x0 < -0.5 || x0 + tw > w + 0.5 || y > h + 0.5 || y - size < -0.5)
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
const bars = coreG ? kidsOf(coreG).filter(e => e.tagName === 'rect') : [];
ok(bars.length === L.wide.byWeek.length,
   `one bar per week instead (${bars.length} of ${L.wide.byWeek.length})`);
const outB = outside(B.svg, bW, bH);
ok(outB.length === 0, 'and keeps its ink inside the canvas: ' + outB.slice(0, 4).join(' | '));
/*
 * AND THE CREW IS STILL IN THE MIDDLE. The first wide view drew only bars,
 * so every spoke on the map pointed at a bar chart with no bosses in it --
 * reported on the first 4-week view opened. The three bosses are static, so
 * there is no reason thirteen weeks cannot show the thing they were spent
 * attacking.
 */
/* SCOPED TO THE CENTRE GROUP. A bare image sweep also catches every trait
   layer of every Fighter portrait -- two per Fighter in this fixture -- so
   it counted fourteen and passed with the crew deleted. */
const bCore = all(B.svg, e => e.getAttribute('class') === 'sg-core')[0];
const bImgs = bCore ? all(bCore, e => e.tagName === 'image') : [];
ok(bImgs.length === (L.wide.crew || []).length,
   `the boss crew is drawn in a multi-week window (${bImgs.length} of `
   + `${(L.wide.crew || []).length})`);
/* The caption names the WINDOW, not just the weeks that had runs in them --
   "1 week" under a 4-week button reads as contradicting the button. */
const caps = all(B.svg, e => e.tagName === 'text' && /played/.test(e.textContent || ''));
ok(caps.length === 1 && caps[0].textContent.indexOf(String(L.wide.weeks)) === 0,
   'the caption leads with the window: "' + (caps[0] ? caps[0].textContent : '') + '"');

console.log('\n-- the interactions actually run --');
/*
 * DISPATCHED, NOT INSPECTED. The first cut of this asserted that the data
 * attributes were present and called it covered -- and the map shipped with
 * hover doing nothing at all, because siege.js walked the tree with
 * `el.children.forEach` and an HTMLCollection has no forEach. Checking that
 * a hook EXISTS says nothing about whether anything is holding it.
 */
const hv = draw(L.week, 1);
const hClusters = hv.svg.querySelectorAll('.sg-cluster');
const hLines = hv.svg.querySelectorAll('.sg-line');
ok(hClusters.length > 1, `the fixture has clusters to isolate between (${hClusters.length})`);
const mine = hClusters[0], other = hClusters[1];
const myId = mine.getAttribute('data-id');
mine.fire('mouseenter');
ok(!mine.classList.contains('sg-dim'), 'the hovered player stays lit');
ok(other.classList.contains('sg-dim'), 'every other player dims');
const myLine = hLines.filter(l => l.getAttribute('data-id') === myId)[0];
const otherLine = hLines.filter(l => l.getAttribute('data-id') !== myId)[0];
ok(!!myLine && myLine.classList.contains('sg-march'), "the hovered player's line marches");
ok(!!otherLine && otherLine.classList.contains('sg-dim'), 'and the rest dim');
/* NEITHER CLASS ON A DIMMED LINE, never both: a CSS animation beats an
   ordinary declaration, so a dimmed line still marching keeps animating its
   own opacity and the dim never lands. */
ok(!otherLine.classList.contains('sg-march'), 'a dimmed line is not also marching');
mine.fire('mouseleave');
ok(!other.classList.contains('sg-dim') && !myLine.classList.contains('sg-march'),
   'leaving puts every line and cluster back');

console.log('\n-- clicking opens the panels --');
const sat = hv.svg.querySelectorAll('.sg-sat').filter(x => x.getAttribute('data-serial') !== '0')[0];
ok(!!sat, 'there is a live Fighter to click');
sat.fire('click');
ok(/dhc-fighter\.php\?serial=/.test(hv.opened.fetched || ''),
   'clicking a Fighter asks the Collection endpoint: ' + (hv.opened.fetched || 'nothing'));
/* A disassembled one must not: there is no serial the endpoint could
   resolve, and a panel that opens empty is worse than one that does not. */
hv.opened.fetched = null;
const goneSat = hv.svg.querySelectorAll('.sg-sat-gone')[0];
if (goneSat) { goneSat.fire('click'); }
ok(!hv.opened.fetched, 'clicking a disassembled Fighter asks for nothing');
/* And the player panel. */
ok(hv.veil.hidden === true, 'the player panel starts closed');
mine.fire('click');
ok(hv.veil.hidden === false, 'clicking a player opens the panel');
ok((hv.pbody.innerHTML || '').indexOf(D_name(L.week, myId)) > -1,
   'and the panel is about the player that was clicked');
function D_name(layout, id) { return layout.nodes[id].name; }

console.log('\n-- the picture buttons are wired --');
/*
 * THE SAME CHECK rivalries-harness.js MAKES, and for the same reason: these
 * are wired at the very end of the map script, so anything that throws
 * between drawing the map and reaching them leaves two buttons that look
 * live and do nothing. The map shipped once with no buttons at all and
 * once with hover dead; a dead button is the same failure wearing a
 * different hat.
 */
ok((hv.dlBtn._on.click || []).length === 1,
   `sg-download has ${(hv.dlBtn._on.click || []).length} click handlers after the script runs`);
ok((hv.cpBtn._on.click || []).length === 1,
   `sg-copy has ${(hv.cpBtn._on.click || []).length} click handlers after the script runs`);
ok(hv.dlBtn.hidden !== true && hv.cpBtn.hidden !== true,
   'the buttons were hidden although this browser can export');

console.log('\n-- the hooks are addressable --');
/*
 * FOUR THINGS THE ARENA MAP HAS AND THIS ONE SHIPPED WITHOUT, all reported
 * in a row because the gap was systematic rather than four oversights: the
 * drawing was built and the interaction layer was not. These assert the
 * hooks are present -- a listener cannot be fired through this stub, but a
 * missing class or a missing data attribute is exactly how they went absent
 * the first time.
 */
const sLines = all(A.svg, e => e.getAttribute('class') === 'sg-line');
ok(sLines.length === Object.keys(L.week.nodes).length,
   `every spoke is addressable for hover (${sLines.length})`);
ok(sLines.every(l => l.getAttribute('data-id')), 'and carries whose line it is');
ok(clusters.every(c => c.getAttribute('data-id')), 'every cluster carries its player id');
/* A disassembled Fighter is drawn -- its damage is real and stays in the
   player's total -- but it must not offer a click that cannot resolve. */
const gone = [];
Object.keys(L.week.nodes).forEach(id => {
  (L.week.nodes[id].fighters || []).forEach(f => { if (f.gone) gone.push(f); });
});
ok(gone.length > 0, 'the fixture has a disassembled Fighter in it, so the rest is not vacuous');
ok(gone.every(f => f.name === 'Disassembled' && f.serial === 0),
   'a disassembled Fighter is labelled as one rather than falling back to DHC2F000');
/* Drawn, and drawn differently -- an ordinary empty disc reads as a broken
   image, which is how this was found. */
const goneEls = all(A.svg, e => hasCls(e, 'sg-sat-gone'));
ok(goneEls.length === gone.length,
   `a disassembled Fighter is marked on the map (${goneEls.length} of ${gone.length})`);
ok(goneEls.every(g => !g.getAttribute('data-serial')
                   || g.getAttribute('data-serial') === '0'),
   'a disassembled Fighter offers a click that cannot resolve');

console.log('\n-- one player, wide window: the centre is the widest ink --');
/*
 * With several players the ring surrounds the centre and their own ink
 * encloses it, so a centre that is never measured still lands inside the
 * canvas. One player puts the whole middle outside that ink -- this is the
 * only shape in which the centre measurement can be shown to matter.
 */
const WS = draw(L.wideSolo, 0);
const wsW = parseFloat(WS.svg.getAttribute('width')), wsH = parseFloat(WS.svg.getAttribute('height'));
const wsOut = outside(WS.svg, wsW, wsH);
ok(wsOut.length === 0,
   'a one-player wide window clips its own centre: ' + wsOut.slice(0, 4).join(' | '));

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
