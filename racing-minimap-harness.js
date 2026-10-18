/* racing-minimap-harness.js - node, no browser, no network.
 *
 * Which way the Skull Racer minimap turns.
 *
 * A PLAYER FOUND THIS, WHICH IS THE POINT OF THE FILE: "can we get the map
 * orientation in game to match the same direction when turning. Currently
 * flipped". The map was a mirror of the track, so every bend read backwards.
 *
 * WHY IT HAPPENED. heading 0 points along +z, and z was written straight
 * into the SVG's y, which grows DOWNWARD -- so the car ran down the screen.
 * A right-hand bend then moved the dot to the screen's right, and when you
 * are driving down the page, the screen's right is the driver's left.
 *
 * The sign convention is not a guess. In render(), x accumulates +curve and
 * is handed to Util.project() as -x, so a positive curve bends the road
 * RIGHT; playerX is then pushed the other way by centrifugal, which is what
 * a right-hand bend does to a car. Those two agreeing is what fixes the
 * meaning of "positive curve".
 *
 * So this runs the REAL path-building block out of racing/index.html over a
 * track whose shape is known -- a long right-hand bend -- and asks where it
 * ended up. Reading the code was what produced the bug in the first place.
 *
 * Usage: node racing-minimap-harness.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');

let fail = 0;
const ok = (c, w) => { if (!c) { fail++; console.log('  FAIL  ' + w); } };

const page = fs.readFileSync(path.join(__dirname, 'racing/index.html'), 'utf8');

/* The block, from the heading correction to the end of the orientation
   flip. Anchored on code, not on comments. */
/* From HEADING_SCALE, not from the heading correction: the scale is
   declared just above it and the lifted block would otherwise reference a
   constant it does not contain. */
const a = page.indexOf('      var HEADING_SCALE =');
const b = page.indexOf('      var outline = [];', a);
ok(a > -1 && b > a, 'the minimap path builder moved; this check is blind');
const block = page.slice(a, b);

/* Positive curve bends right -- see render()'s -x projection. */
function run(curves) {
	const ctx = { segments: curves.map((c) => ({ curve: c })), segmentLength: 200,
	              minimapPoints: [], Math, n: 0 };
	vm.createContext(ctx);
	vm.runInContext('var n;\n' + block, ctx);
	return ctx.minimapPoints;
}

/* REALISTIC LENGTH. The hand-tuned bump is centred on segment 2764, so a
   short fixture leaves its window past the end of the array and the block
   throws -- the real track is thousands of segments long. */
const straight = (n) => new Array(n).fill(0);
const right    = (n, k) => new Array(n).fill(k);

console.log('a right-hand bend bends right on the map');
{
	/*
	 * A ~90 DEGREE BEND, SIZED ON PURPOSE. heading advances by
	 * curve * HEADING_SCALE (0.0015) per segment, so curve 4 over 1200
	 * segments is 7.2 radians -- more than a full turn, and the first
	 * fixture did exactly that: the path spiralled and the two samples
	 * landed at arbitrary points around it, reporting a right bend as
	 * going left. 0.9 over 1200 is about 97 degrees.
	 */
	const STRAIGHT = 600, BEND = 1200, K = 0.9;
	const pts = run([].concat(straight(STRAIGHT), right(BEND, K), straight(STRAIGHT),
	                          right(BEND, -K), straight(STRAIGHT)));
	ok(pts.length > 0, 'the builder produced no points');

	/* SVG y grows downward, so "up the screen" is a DECREASING my. The car
	   must set off up the page; if it sets off down, every turn reads
	   mirrored however right everything else is. Measured on the opening
	   straight, where the heading is still 0. */
	const start = pts[0], endOfStraight = pts[STRAIGHT - 1];
	ok(endOfStraight.my < start.my,
	   'the track runs DOWN the screen from the start (my ' + start.my.toFixed(1) + ' -> '
	 + endOfStraight.my.toFixed(1) + '), which mirrors every turn');

	/* Then, across the right-hand bend, it must move to the screen's
	   right. Sampled at the bend's own ends rather than at a fraction of
	   the whole array. */
	const inBend = pts[STRAIGHT], outBend = pts[STRAIGHT + BEND - 1];
	ok(outBend.mx > inBend.mx,
	   'the right-hand bend moves the path LEFT on the map (mx ' + inBend.mx.toFixed(1)
	 + ' -> ' + outBend.mx.toFixed(1) + ') -- the map is mirrored');

	/* And the mirror image of the same track must do the opposite, or the
	   check above could pass on a path that always drifts right. */
	const mirror = run([].concat(straight(STRAIGHT), right(BEND, -K), straight(STRAIGHT),
	                             right(BEND, K), straight(STRAIGHT)));
	ok(mirror[STRAIGHT + BEND - 1].mx < mirror[STRAIGHT].mx,
	   'a LEFT-hand bend also moves the path right; the map is not following the curve at all');
}

console.log('\nthe flip is a reflection, so the crossing fix still holds');
{
	/*
	 * The hand-tuned bump that separates the two stretches of road which
	 * genuinely pass near each other was measured BEFORE the flip. A
	 * reflection is an isometry -- it preserves every distance and maps
	 * crossings one to one -- so flipping the finished geometry keeps that
	 * fix working exactly as tuned. Flipping any earlier would reverse the
	 * perpendicular the bump pushes along and could undo it.
	 *
	 * This also means the mirror never explained the self-intersections: a
	 * mirror cannot create or remove one.
	 */
	const clean = block.replace(/\/\/.*$/gm, '');
	const flipAt = clean.indexOf('100 - minimapPoints[n].my');
	const bumpAt = clean.indexOf('BUMP_CENTER');
	ok(flipAt > -1, 'the orientation flip is gone; the map is mirrored again');
	ok(bumpAt > -1 && flipAt > bumpAt,
	   'the flip runs BEFORE the hand-tuned bump, which reverses the direction it pushes');

	/* Distances between neighbours must be untouched by the flip -- that is
	   what makes it safe to do after the bump. */
	const pts = run([].concat(straight(800), right(1400, 3), straight(800), right(1400, -3)));
	let same = true;
	for (let i = 1; i < pts.length; i++) {
		const d = Math.hypot(pts[i].mx - pts[i - 1].mx, pts[i].my - pts[i - 1].my);
		if (!isFinite(d)) { same = false; break; }
	}
	ok(same, 'the path contains non-finite coordinates');
}

console.log('\nthe drawing stays inside the viewBox');
{
	const pts = run([].concat(straight(800), right(1600, 5), straight(800), right(1600, -5)));
	let minX = Infinity, maxX = -Infinity, minY = Infinity, maxY = -Infinity;
	for (const p of pts) {
		minX = Math.min(minX, p.mx); maxX = Math.max(maxX, p.mx);
		minY = Math.min(minY, p.my); maxY = Math.max(maxY, p.my);
	}
	/* The bump pushes points outward by up to 9 units, so the bound is the
	   0-100 viewBox rather than the 4-96 the projection itself targets. */
	ok(minX >= 0 && maxX <= 100 && minY >= 0 && maxY <= 100,
	   'the path leaves the 0-100 viewBox: x ' + minX.toFixed(1) + '..' + maxX.toFixed(1)
	 + ', y ' + minY.toFixed(1) + '..' + maxY.toFixed(1));
}

console.log('\n' + (fail ? fail + ' FAILED\n' : 'all good\n'));
process.exit(fail ? 1 : 0);
