/*
 * map-kit-harness.js -- the primitives map.js and rivalries.js share.
 *
 * map.js is live on the realms page, so the extraction into map-kit.js has to
 * be behaviour-preserving, not merely parseable. These run the kit's functions
 * against values computed from the PRE-EXTRACTION map.js (see the golden
 * numbers below, taken from /tmp/map.js.orig during the split and pinned here)
 * and against the properties the two maps actually rely on.
 */
const fs = require('fs');
const path = require('path');
let fails = 0;
const ok = (c, w) => { if (!c) { fails++; console.log('FAIL  ' + w); } else console.log('ok    ' + w); };

/* A DOM stub: only what the kit touches at load time and in measureTextWidth. */
global.document = {
  createElementNS: () => ({
    setAttribute() {}, appendChild() {}, removeChild() {},
    getComputedTextLength: () => 0, set textContent(v) {},
  }),
  body: { appendChild() {} },
};

const kitSrc = fs.readFileSync(path.join(__dirname, 'map-kit.js'), 'utf8');
const mapSrc = fs.readFileSync(path.join(__dirname, 'map.js'), 'utf8');

/* Load the kit the way a browser does: a classic script into a shared scope. */
const scope = {};
new Function('document', kitSrc + `
  Object.assign(arguments[1], { palette, mapkitColorFor, hexToRgba, mulberry32,
    hashStr, jitterRect, chaikin, toPath, centroid, inPoly, placeMarkers, svgEl,
    measureTextWidth, MAPKIT_NS });
`)(global.document, scope);

console.log('-- the kit exposes what both maps call --');
for (const fn of ['hexToRgba','mulberry32','hashStr','jitterRect','chaikin','toPath',
                  'centroid','inPoly','placeMarkers','svgEl','measureTextWidth','mapkitColorFor']) {
  ok(typeof scope[fn] === 'function', `${fn} is a function`);
}
ok(Array.isArray(scope.palette) && scope.palette.length === 15, 'the palette carries 15 colours');

console.log('\n-- exactly one definition of each, across both files --');
for (const fn of ['hexToRgba','mulberry32','hashStr','jitterRect','chaikin','toPath',
                  'centroid','inPoly','placeMarkers','svgEl','measureTextWidth']) {
  const inKit = (kitSrc.match(new RegExp('^function ' + fn + '\\s*\\(', 'm')) || []).length;
  const inMap = (mapSrc.match(new RegExp('^function ' + fn + '\\s*\\(', 'm')) || []).length;
  ok(inKit === 1 && inMap === 0, `${fn}: kit defines it, map.js does not`);
}
/* A duplicate top-level const across two classic scripts is a SyntaxError that
   takes the whole page down, so this is not a style check. */
ok(/^const palette = \[/m.test(kitSrc), 'the kit declares palette');
ok(!/^const palette = \[/m.test(mapSrc), 'and map.js no longer redeclares it');
ok(!/^const _measureSvg/m.test(mapSrc), 'nor the hidden measuring svg');

console.log('\n-- map.js still USES the kit, so the extraction was not a deletion --');
ok(/placeMarkers\(/.test(mapSrc), 'map.js calls placeMarkers');
ok(/svgEl\(/.test(mapSrc),        'map.js calls svgEl');
ok(/mulberry32\(hashStr\(/.test(mapSrc), 'map.js still seeds its layout off a name');
ok(/palette/.test(mapSrc),        'map.js still reads the palette');

console.log('\n-- the PRNG is deterministic, which is what stable layout rests on --');
const a = scope.mulberry32(scope.hashStr('Krull_territory'));
const b = scope.mulberry32(scope.hashStr('Krull_territory'));
const first = [a(), a(), a()], second = [b(), b(), b()];
ok(JSON.stringify(first) === JSON.stringify(second), 'the same seed replays the same sequence');
ok(new Set(first).size === 3, 'and the sequence is not a constant');
const c = scope.mulberry32(scope.hashStr('Vexxed_territory'));
ok(c() !== first[0], 'a different seed starts somewhere else');
first.forEach((v, i) => ok(v >= 0 && v < 1, `  value ${i} is in [0,1)`));
/* GOLDEN: pinned from the pre-extraction map.js. If mulberry32 or hashStr were
   altered in the move, every map layout shifts and these change. */
ok(scope.hashStr('Krull_territory') === 578941673,
   'hashStr is unchanged by the move (got ' + scope.hashStr('Krull_territory') + ')');
ok(first[0].toFixed(10) === '0.5840602044',
   'mulberry32 is unchanged (got ' + first[0].toFixed(10) + ')');

console.log('\n-- stable colours, which the rivalry map needs and the realm map does not --');
ok(scope.mapkitColorFor(42) === scope.mapkitColorFor(42), 'the same key is the same colour twice');
ok(scope.mapkitColorFor('42') === scope.mapkitColorFor(42), 'and a numeric key matches its string');
ok(scope.palette.includes(scope.mapkitColorFor(7)), 'the colour comes from the palette');
const spread = new Set([1,2,3,4,5,6,7,8].map(scope.mapkitColorFor));
ok(spread.size >= 4, `eight players get at least 4 distinct colours (got ${spread.size})`);

console.log('\n-- geometry --');
/* NOT A SQUARE. On a square the x and y averages are the same number, so a
   centroid that averaged one axis twice would pass. This one is 10 wide and
   20 tall, so the two coordinates differ. */
const sq = [{x:0,y:0},{x:10,y:0},{x:10,y:20},{x:0,y:20}];
const ctr = scope.centroid(sq);
ok(ctr.x === 5,  'centroid x is 5 (got ' + ctr.x + ')');
ok(ctr.y === 10, 'centroid y is 10, not 5 -- both axes are read (got ' + ctr.y + ')');
ok(scope.inPoly(5, 10, sq) === true,  'a point inside reads inside');
ok(scope.inPoly(15, 10, sq) === false, 'a point to the right reads outside');
ok(scope.inPoly(5, -1, sq) === false,  'and so does one above it');
ok(scope.inPoly(5, 25, sq) === false,  'and one below -- the taller axis is tested too');
/* A DIAGONALLY OUTSIDE POINT. (-5,-5) is outside on both axes at once, and it
   is the case that separates a real crossing test from one that only compares
   x: drop the `(yi>py) !== (yj>py)` guard and this corner reads as inside. */
ok(scope.inPoly(-5, -5, sq) === false, 'a point outside on BOTH axes reads outside');
ok(scope.inPoly(-5, 25, sq) === false, 'and so does the opposite corner');
/* A triangle, so the test is not resting entirely on axis-aligned edges. */
const tri = [{x:0,y:0},{x:20,y:0},{x:0,y:20}];
ok(scope.inPoly(3, 3, tri) === true,   'inside the triangle');
ok(scope.inPoly(15, 15, tri) === false, 'beyond its hypotenuse is outside');
ok(scope.toPath(sq).startsWith('M0.0,0.0') && scope.toPath(sq).endsWith('Z'),
   'toPath opens with a moveto and closes the loop');
ok(scope.toPath(sq).split('L').length === 4, 'and draws the other three corners as linetos');
ok(scope.toPath(sq).includes('20.0'), 'and uses the real coordinates, not a squared-off guess');
/* chaikin must round the corners off, which means more points and a shape that
   no longer touches the original corners. */
const sm = scope.chaikin(sq, 2);
ok(sm.length > sq.length, `chaikin subdivides (${sq.length} -> ${sm.length})`);
ok(!sm.some(p => p.x === 0 && p.y === 0), 'and pulls the path off the sharp corner');
const jr = scope.jitterRect(0, 0, 10, 10, 2, scope.mulberry32(1));
ok(jr.length === 12, 'jitterRect returns 4 corners plus 2 midpoints a side');
ok(jr.some(p => p.x !== 0 && p.x !== 10), 'and actually displaces them');
ok(scope.hexToRgba('#FF3366', 0.5) === 'rgba(255,51,102,0.5)', 'hexToRgba converts correctly');

console.log('\n-- placeMarkers, which both maps use to fill a cluster --');
const big = [{x:0,y:0},{x:400,y:0},{x:400,y:400},{x:0,y:400}];
const R = 20, MIND = R * 3;   // the spacing placeMarkers actually enforces
/*
 * 10 MARKERS, SEED 3, in a 400x400 box. The count and the seed are not
 * arbitrary: the candidate grid here steps 80px and jitters +/-40, so adjacent
 * candidates CAN fall 40px apart. Ask for too few and the shuffle is unlikely
 * to draw two neighbours, and a placeMarkers with its spacing check deleted
 * passes anyway. At this count and seed the greedy check is load-bearing --
 * removing it drops the closest pair to 48px, inside MIND.
 */
const pm = scope.placeMarkers(big, 10, {x:0,y:0,w:400,h:400}, R, scope.mulberry32(3));
ok(pm.length === 10, `all ten markers were placed (got ${pm.length})`);
/* CHECKED AGAINST THE BOX, NOT AGAINST inPoly. Asking inPoly whether
   placeMarkers' own inPoly-filtered output is inside the polygon is circular:
   a broken inPoly would agree with itself. The polygon here IS the bounding
   box, so plain arithmetic is an independent judge. */
ok(pm.every(p => p.x >= 0 && p.x <= 400 && p.y >= 0 && p.y <= 400),
   'every marker lands inside the 400x400 box, checked without inPoly');
ok(pm.every(p => scope.inPoly(p.x, p.y, big)), 'and inPoly agrees');
let closest = Infinity;
for (let i = 0; i < pm.length; i++) {
  for (let j = i + 1; j < pm.length; j++) {
    closest = Math.min(closest, Math.hypot(pm[i].x - pm[j].x, pm[i].y - pm[j].y));
  }
}
ok(closest >= MIND, `the closest pair clears R*3 = ${MIND}px (got ${closest.toFixed(0)}px)`);
const pm2 = scope.placeMarkers(big, 10, {x:0,y:0,w:400,h:400}, R, scope.mulberry32(3));
ok(JSON.stringify(pm) === JSON.stringify(pm2), 'the same seed places them identically');
/*
 * THE CRAMPED CASE, which is the one that matters for the rivalry map.
 *
 * placeMarkers never drops a marker: what it cannot space out it lays on a
 * circle around the centroid. So a cluster with more Fighters than it has room
 * for returns all of them OVERLAPPING, and the fix belongs in the caller --
 * grow the cluster -- not here. This pins that behaviour so a future change to
 * the fallback cannot quietly start losing markers instead.
 */
const tiny = [{x:0,y:0},{x:60,y:0},{x:60,y:60},{x:0,y:60}];
const pmT = scope.placeMarkers(tiny, 20, {x:0,y:0,w:60,h:60}, 20, scope.mulberry32(3));
ok(pmT.length === 20, `a cramped polygon still returns all 20 (got ${pmT.length})`);
const overlaps = pmT.some((p, i) => pmT.some((q, j) => j > i && Math.hypot(p.x-q.x, p.y-q.y) < 40));
ok(overlaps, '...and they do overlap, which is the caller\'s problem to prevent');

console.log('\n' + (fails ? fails + ' FAILED' : 'all passed'));
process.exit(fails ? 1 : 0);
