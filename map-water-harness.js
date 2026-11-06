/*
 * map-water-harness.js -- the ocean on the realms map.
 *
 * Two bugs in this code were invisible on the page, which is why this
 * file exists.
 *
 * ONE: a config key was dropped in an edit. `WATER.forceGain` went
 * missing, so the drive term multiplied by undefined and EVERY cell of
 * the height field became NaN on the first frame. Nothing threw. The
 * renderer carried on, `NaN | 0` is 0, so every pixel sampled its own
 * position and the sea drew perfectly -- perfectly still. It looked
 * like a tuning problem and cost an afternoon.
 *
 * TWO: the sampler truncated its offset to a whole pixel, which put a
 * hard floor under the effect: under one pixel of displacement the
 * water did not move gently, it stopped. That split the sea into
 * living and dead halves with a line between them.
 *
 * Both are properties of pure functions, so both are checked here
 * rather than by looking at the page.
 */
const fs = require('fs');
const path = require('path');
let fails = 0;
const ok = (c, w) => { if (!c) { fails++; console.log('FAIL  ' + w); } else console.log('ok    ' + w); };

const SRC = fs.readFileSync(path.join(__dirname, 'map.js'), 'utf8');
const WORLD = fs.readFileSync(path.join(__dirname, 'map-world.js'), 'utf8');

/* Comments mention every one of these names; an assertion that matched
   the prose instead of the code would pass on a gutted file. */
const strip = (s) => s.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
const CODE = strip(SRC);

/* Lift the pure pieces into a scope, the way the browser sees them. */
const lift = (src, sig) => {
    const a = src.indexOf(sig);
    if (a < 0) throw new Error('map.js no longer defines ' + sig);
    let d = 0;
    for (let j = src.indexOf('{', a); j < src.length; j++) {
        if (src[j] === '{') d++;
        else if (src[j] === '}') { d--; if (!d) return src.slice(a, j + 1) + '\n'; }
    }
    throw new Error('unbalanced ' + sig);
};
const constOf = (src, name) => {
    const a = src.indexOf('const ' + name + ' = {');
    if (a < 0) throw new Error('map.js no longer defines ' + name);
    let d = 0;
    for (let j = src.indexOf('{', a); j < src.length; j++) {
        if (src[j] === '{') d++;
        else if (src[j] === '}') { d--; if (!d) return src.slice(a, j + 2) + '\n'; }
    }
    throw new Error('unbalanced ' + name);
};

const scalarConst = (src, name) => {
    const a = src.indexOf('const ' + name + ' =');
    if (a < 0) throw new Error('map.js no longer defines ' + name);
    return src.slice(a, src.indexOf('\n', a)) + '\n';
};

const scope = {};
new Function('out', [
    lift(WORLD, 'function mwRng('),
    scalarConst(SRC, 'WATER_TEMPO'),
    constOf(SRC, 'WATER'), constOf(SRC, 'SEA_BED'),
    lift(SRC, 'function waterStep('), lift(SRC, 'function waterWake('),
    lift(SRC, 'function waterStormCell('), lift(SRC, 'function waterPixel('),
    lift(SRC, 'function swellField('), lift(SRC, 'function smoothEdge('),
    'Object.assign(out, { WATER, SEA_BED, mwRng, waterStep, waterWake,',
    '  waterStormCell, waterPixel, swellField, smoothEdge });'
].join('\n'))(scope);

console.log('-- the config carries every key the renderer reads --');
/*
 * THE REAL TEST OF A CONFIG IS THE CODE THAT READS IT. Listing the
 * expected keys here would just be the same mistake written twice: an
 * edit that drops a key AND its reader would still pass. So the keys
 * are collected from every WATER.<name> in the source and checked
 * against the object.
 */
const read = [...new Set([...CODE.matchAll(/\bWATER\.([A-Za-z0-9_]+)/g)].map(m => m[1]))];
ok(read.length >= 15, `the renderer reads ${read.length} distinct WATER keys`);
const missing = read.filter(k => scope.WATER[k] === undefined);
ok(missing.length === 0, `every key it reads is defined (missing: ${missing.join(', ') || 'none'})`);
const notNum = read.filter(k => {
    const v = scope.WATER[k];
    return typeof v === 'number' ? !Number.isFinite(v) : !Array.isArray(v);
});
ok(notNum.length === 0, `and every one is a finite number or an array (bad: ${notNum.join(', ') || 'none'})`);

const bedRead = [...new Set([...CODE.matchAll(/\bSEA_BED\.([A-Za-z0-9_]+)/g)].map(m => m[1]))];
ok(bedRead.length >= 3 && bedRead.every(k => scope.SEA_BED[k] !== undefined),
   `SEA_BED defines all ${bedRead.length} keys the bed builder reads`);

console.log('\n-- a NaN anywhere in the field would be drawn as still water --');
/*
 * The failure above reached the screen as "the sea does not move",
 * which is indistinguishable from a tuning mistake. Run the real step
 * on a small pond and insist the field stays finite.
 */
const W = 40, H = 30;
const land = new Uint8Array(W * H);
for (let y = 12; y < 18; y++) for (let x = 12; x < 20; x++) land[y * W + x] = 1;
const wet = [];
for (let y = 1; y < H - 1; y++) for (let x = 1; x < W - 1; x++)
    if (!land[y * W + x]) wet.push(y * W + x);
const wetA = new Int32Array(wet);
let cur = new Float32Array(W * H), prev = new Float32Array(W * H);
scope.waterWake(cur, land, W, H, 6, 6, 5, 0.5);
for (let n = 0; n < 400; n++) {
    scope.waterStep(cur, prev, wetA, W, scope.WATER.damping);
    const t = cur; cur = prev; prev = t;
}
ok(wetA.every(k => Number.isFinite(cur[k])), 'every wet cell is still a finite number after 400 steps');
ok(wet.some(k => cur[k] !== 0), '...and the pond is not simply flat');
let onLand = 0;
for (let i = 0; i < land.length; i++) if (land[i] && cur[i] !== 0) onLand++;
ok(onLand === 0, 'land stays pinned at zero, which is what makes a coast reflect');

/*
 * Amplitude must fall. The field is forced continuously on the page
 * and this damping is the only thing standing between it and a basin
 * that rings louder every second. Measured as peak height rather than
 * summed energy, because the two-buffer scheme keeps half its state
 * in the other buffer and the sum over one of them is not the energy.
 */
let c2 = new Float32Array(W * H), p2 = new Float32Array(W * H);
scope.waterWake(c2, land, W, H, 20, 20, 6, 1);
const peak = (f) => wetA.reduce((m, k) => Math.max(m, Math.abs(f[k])), 0);
const peak0 = peak(c2);
for (let n = 0; n < 2000; n++) {
    scope.waterStep(c2, p2, wetA, W, scope.WATER.damping);
    const t = c2; c2 = p2; p2 = t;
}
ok(peak(c2) < peak0 * 0.5,
   `an unforced pond dies away (peak ${peak0.toFixed(3)} -> ${peak(c2).toFixed(4)})`);
ok(scope.WATER.damping < 1, `and the damping that does it is below 1 (${scope.WATER.damping})`);

console.log('\n-- the bed is read between pixels, not at one --');
/*
 * A horizontal ramp, one level per pixel. A displacement of a quarter
 * of a pixel must move the colour a quarter of a level. The bug this
 * replaces returned the untouched pixel for any offset under 1.
 */
const BW = 8, BH = 3;
const ramp = new Uint8Array(BW * BH);
for (let y = 0; y < BH; y++) for (let x = 0; x < BW; x++) ramp[y * BW + x] = x * 10;
const rgb = new Uint8Array(3);
const sampleAt = (gx, d) => {
    scope.waterPixel(ramp, ramp, ramp, BW, BH, 3, 1, gx, 0, d, 0, rgb);
    return rgb[0];
};
ok(sampleAt(0, 0) === 30, 'no displacement reads the pixel itself (30)');
const quarter = sampleAt(1, 0.25);
ok(quarter > 30 && quarter < 35,
   `a quarter-pixel shift moves it a fraction of a level, not none and not a whole one (got ${quarter})`);
ok(sampleAt(1, 0.1) !== sampleAt(1, 0.4),
   'two different sub-pixel offsets give two different colours');
ok(sampleAt(1, 1) === 40 && sampleAt(-1, 1) === 20,
   'a whole-pixel shift lands exactly on the neighbour either way');

/* The shading is added to the sampled colour, and cannot drive it out
   of range however hard the water is pushed. */
scope.waterPixel(ramp, ramp, ramp, BW, BH, 3, 1, 0, 0, 0, 500, rgb);
ok(rgb[0] === 255, 'a huge positive shade clamps at 255 rather than wrapping');
scope.waterPixel(ramp, ramp, ramp, BW, BH, 3, 1, 0, 0, 0, -500, rgb);
ok(rgb[0] === 0, 'a huge negative shade clamps at 0');

/* And an offset that would run off the sheet is pulled back onto it,
   so the sampler can never read outside its own buffer. */
let offEdge = true;
for (const [x, y, gx, gy] of [[0, 0, 50, 50], [BW - 1, BH - 1, -50, -50], [0, 0, -50, -50]]) {
    scope.waterPixel(ramp, ramp, ramp, BW, BH, x, y, gx, gy, 10, 0, rgb);
    if (!Number.isFinite(rgb[0]) || rgb[0] === undefined) offEdge = false;
}
ok(offEdge, 'a displacement far off the sheet still reads a real pixel');

console.log('\n-- the weather never strikes where it can be seen --');
/*
 * The owner's own idea, and the whole reason the sim grid is bigger
 * than the map: "if that type of catalyst happens outside of the
 * container, the user wouldn't see the comet, only the results". One
 * strike landing on the visible sheet undoes it.
 */
const PAD = scope.WATER.simPad, VW = 120, VH = 80;
const SW = VW + 2 * PAD, SH = VH + 2 * PAD;
const rnd = scope.mwRng(0x5eed10);
let inside = 0, outside = 0, offGrid = 0;
for (let n = 0; n < 60000; n++) {
    const at = scope.waterStormCell(rnd, SW, SH, PAD, VW, VH);
    if (!at) continue;
    const [x, y] = at;
    if (x < 1 || y < 1 || x >= SW - 1 || y >= SH - 1) offGrid++;
    else if (x >= PAD && x < PAD + VW && y >= PAD && y < PAD + VH) inside++;
    else outside++;
}
ok(inside === 0, `not one of 60,000 strikes lands on the visible map (got ${inside})`);
ok(offGrid === 0, `and none lands on the outer ring, which is never stepped (got ${offGrid})`);
ok(outside > 50000, `they do land -- ${outside} of them, out in the margin`);

/* Deterministic, because faction colours once repainted themselves
   between page views and Math.random has been banned here since. */
ok(!/Math\.random/.test(CODE), 'map.js calls Math.random nowhere');
const a1 = scope.waterStormCell(scope.mwRng(7), SW, SH, PAD, VW, VH);
const a2 = scope.waterStormCell(scope.mwRng(7), SW, SH, PAD, VW, VH);
ok(a1 && a2 && a1[0] === a2[0] && a1[1] === a2[1], 'the same seed strikes the same cell');

console.log('\n-- the bed is sea, never land --');
/*
 * seaBed() skips the land rings so that a crest near a coast cannot
 * drag the shoreline colour out into the water. Checked by running the
 * real function against a recording canvas.
 */
const filled = [];
global.document = {
    createElement: () => ({
        width: 0, height: 0,
        getContext: () => ({
            fillStyle: '',
            fillRect() {}, beginPath() {}, moveTo() {}, lineTo() {}, closePath() {},
            fill(rule) { filled.push({ fill: this.fillStyle, rule: rule }); },
            putImageData() {},
            getImageData: (x, y, w, h) => ({ data: new Uint8ClampedArray(w * h * 4) }),
        }),
    }),
};
const seaLayersSrc = lift(SRC, 'function seaLayers(');
const bedScope = {};
new Function('document', 'out', [
    lift(WORLD, 'function mwRng('), constOf(SRC, 'SEA_BED'), seaLayersSrc,
    lift(SRC, 'function seaBed('),
    'Object.assign(out, { seaBed, seaLayers });'
].join('\n'))(global.document, bedScope);

const ring = (n, r) => Array.from({ length: n }, (_, i) =>
    ({ x: 200 + r * Math.cos(i / n * 6.283), y: 200 + r * Math.sin(i / n * 6.283) }));
const fakeWorld = {
    seed: 42,
    coast: [ring(24, 60)],
    shores: [{ rings: [ring(24, 70)] }, { rings: [ring(24, 85)] }],
};
/* Wide enough that the mottle, whose scale is in hundreds of
   pixels, actually varies across the sample. */
const BEDW = 400, BEDH = 300;
const bedOut = bedScope.seaBed(fakeWorld, BEDW, BEDH, 0.5);
ok(bedOut instanceof Uint32Array && bedOut.length === BEDW * BEDH, 'seaBed returns one word per pixel');
const shadeFills = bedScope.seaLayers(fakeWorld).filter(L => L.band >= 0).length;
const landFill = bedScope.seaLayers(fakeWorld).find(L => L.band < 0);
ok(filled.length === shadeFills,
   `it fills the ${shadeFills} shelf bands and nothing else (filled ${filled.length})`);
ok(landFill && !filled.some(f => f.fill === landFill.fill),
   'the land colour is never painted into the bed');

/* The mottle is what the open ocean is seen through, so it has to
   actually be there -- and it has to be faint. */
let lo = 255, hi = 0;
for (let i = 0; i < bedOut.length; i++) {
    const v = bedOut[i] & 255;
    if (v < lo) lo = v;
    if (v > hi) hi = v;
}
ok(hi > lo, `the bed is not one flat colour (red spans ${lo}..${hi})`);
ok(hi - lo <= scope.SEA_BED.mottleAmp * 4,
   `and the mottle stays faint (${hi - lo} levels, cap ${scope.SEA_BED.mottleAmp * 4})`);

console.log('\n' + (fails ? fails + ' FAILED' : 'all passed'));
process.exit(fails ? 1 : 0);
