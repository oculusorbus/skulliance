/* map-world-harness.js - node, no browser, no network.
 *
 * The realm map's geography. Invoked by realms-harness.php and reported
 * there as one assertion, the same arrangement nft-panel-harness.js has.
 *
 * WHY A MAP NEEDS A HARNESS AT ALL. Every defect in generated geometry
 * looks like a style choice. The first render of this had the coastline
 * overflowing the canvas by 89px, so the grid clipped it and four
 * territories came out with a RULER-STRAIGHT eastern edge -- on a map
 * whose whole purpose is to not look like boxes. It read as "a design
 * decision I disagree with" rather than as a bug, and the only reason it
 * was caught is that the numbers were measured instead of eyeballed.
 *
 * So the properties below are the ones a reader cannot check by looking:
 * that the world is the SAME world next time, that it fits, that a
 * faction's share of the land tracks its realms, and that nobody is left
 * off it.
 *
 * Usage: node map-world-harness.js
 */
'use strict';
const W = require('./map-world.js');

let fail = 0;
const ok = (c, w) => { if (!c) { fail++; console.log('  FAIL  ' + w); } };

const FACTIONS = [
	{ name: 'Dead Heads Club', weight: 18 }, { name: 'Ohh Meed', weight: 9 },
	{ name: 'Omen', weight: 6 }, { name: 'Danketsu', weight: 5 },
	{ name: 'Crypties', weight: 12 }, { name: 'Havoc Worlds', weight: 3 },
	{ name: 'Ancestors', weight: 7 }, { name: 'Wavy Apes', weight: 2 }
];
const bbox = (p) => p.reduce((a, q) => ({
	x0: Math.min(a.x0, q.x), y0: Math.min(a.y0, q.y),
	x1: Math.max(a.x1, q.x), y1: Math.max(a.y1, q.y)
}), { x0: Infinity, y0: Infinity, x1: -Infinity, y1: -Infinity });

console.log('the same world every visit');
{
	/*
	 * A PLACE THAT REARRANGES ITSELF WHEN YOU RELOAD IS NOT A PLACE, and
	 * this is the property the whole feature rests on. The old map used
	 * Math.random() for faction colours, so it repainted on every load.
	 */
	const a = W.mwWorld(FACTIONS, 1400, 980);
	const b = W.mwWorld(FACTIONS, 1400, 980);
	ok(JSON.stringify(a) === JSON.stringify(b), 'two runs produce two different worlds');

	/* AND THE QUERY'S ROW ORDER MUST NOT MATTER. The faction list arrives
	   from SQL with no ORDER BY on name; if the seed depended on that
	   order the continent would reshape whenever the planner changed. */
	const c = W.mwWorld(FACTIONS.slice().reverse(), 1400, 980);
	ok(JSON.stringify(a.coast) === JSON.stringify(c.coast),
	   'the coastline depends on the order the factions arrived in');

	/*
	 * AND A NEW REALM MUST NOT REDRAW THE CONTINENT. Borders move, which
	 * is correct -- territory follows realms -- but the coast is the thing
	 * players recognise and it has to survive somebody joining.
	 */
	const grown = FACTIONS.map(f => f.name === 'Omen' ? { name: f.name, weight: f.weight + 4 } : f);
	const d = W.mwWorld(grown, 1400, 980);
	ok(JSON.stringify(a.coast) === JSON.stringify(d.coast),
	   'a faction gaining realms redraws the coastline, so the world changes shape whenever '
	 + 'anybody joins');
	ok(JSON.stringify(a.regions) !== JSON.stringify(d.regions),
	   'a faction gaining four realms does not change the borders at all, so territory does '
	 + 'not follow the game');
}

console.log('\nthe continent fits inside the canvas');
{
	/*
	 * THE BUG THIS EXISTS FOR. Three harmonics summing to 1.275 of a base
	 * radius of 0.46w put the coast at x1489 of 1400. The grid stops at
	 * the canvas, so the trace followed the clip and four territories got
	 * a dead-straight eastern border -- the single most un-worldly thing
	 * on a map built to stop looking like boxes.
	 */
	for (const [w, h] of [[1400, 980], [900, 630], [2400, 1200], [640, 900]]) {
		const world = W.mwWorld(FACTIONS, w, h);
		ok(world !== null, 'no world at ' + w + 'x' + h);
		if (!world) continue;
		const c = bbox(world.coast);
		ok(c.x0 >= 0 && c.y0 >= 0 && c.x1 <= w && c.y1 <= h,
		   'the coast overflows a ' + w + 'x' + h + ' canvas: x ' + c.x0.toFixed(0) + '-'
		 + c.x1.toFixed(0) + ', y ' + c.y0.toFixed(0) + '-' + c.y1.toFixed(0));
		/* And it has to USE the canvas. A continent fitted so hard it sits
		   in the middle at a quarter size is "fits" and is not a map. */
		ok((c.x1 - c.x0) > w * 0.80 || (c.y1 - c.y0) > h * 0.80,
		   'the continent only fills ' + (((c.x1 - c.x0) / w) * 100).toFixed(0) + '% of a '
		 + w + 'x' + h + ' canvas');
	}
}

console.log('\nnobody is left off the world, and land tracks realms');
{
	const world = W.mwWorld(FACTIONS, 1400, 980);
	ok(world.regions.length === FACTIONS.length,
	   'only ' + world.regions.length + ' of ' + FACTIONS.length + ' factions got territory; '
	 + 'a faction with no land is a faction nobody can click');

	const total = world.regions.reduce((a, r) => a + r.cells, 0);
	const wTotal = FACTIONS.reduce((a, f) => a + f.weight, 0);
	/*
	 * BIGGER FACTION, MORE GROUND. Not proportional -- Lloyd relaxation
	 * and a coastline both distort it, and a map where every border is
	 * exactly fair is a pie chart. The test is ORDER: the largest faction
	 * must hold more land than the smallest, and nobody should be starved.
	 */
	const big = world.regions.find(r => r.name === 'Dead Heads Club');
	const small = world.regions.find(r => r.name === 'Wavy Apes');
	ok(big.cells > small.cells * 3,
	   'the 18-realm faction holds ' + big.cells + ' cells against the 2-realm faction\'s '
	 + small.cells + '; land does not track realms');
	for (const r of world.regions) {
		const want = FACTIONS.find(f => f.name === r.name).weight / wTotal;
		const got = r.cells / total;
		ok(got > want * 0.35,
		   r.name + ' holds ' + (got * 100).toFixed(1) + '% of the land for '
		 + (want * 100).toFixed(1) + '% of the realms -- squeezed off the map');
	}
}

console.log('\nthe borders are smoothed, not stepped');
{
	/*
	 * A TRACED REGION IS ALL AXIS-ALIGNED UNIT STEPS, so a diagonal border
	 * is dozens of two-point stairs. Chaikin rounds each step individually
	 * and the result is visibly serrated -- which is exactly what the
	 * first render showed on every internal border while the coast, being
	 * analytic, came out smooth. mwDedupe() collapses the straight runs
	 * first so Chaikin has real corners to cut.
	 */
	const square = [];
	for (let i = 0; i < 10; i++) square.push({ x: i, y: 0 });
	for (let i = 0; i < 10; i++) square.push({ x: 10, y: i });
	for (let i = 10; i > 0; i--) square.push({ x: i, y: 10 });
	for (let i = 10; i > 0; i--) square.push({ x: 0, y: i });
	ok(W.mwDedupe(square).length === 4,
	   'a traced square keeps ' + W.mwDedupe(square).length + ' points instead of its 4 corners');
	/* It must never destroy a shape it cannot simplify. */
	const tri = [{ x: 0, y: 0 }, { x: 10, y: 0 }, { x: 5, y: 9 }];
	ok(W.mwDedupe(tri).length === 3, 'a triangle was simplified away');

	/*
	 * ON THE REAL THING: HOW MUCH BORDER LENGTH STILL RUNS ALONG AN AXIS.
	 *
	 * COUNTED BY LENGTH, NOT BY SEGMENT, and that matters -- the first
	 * version of this counted segments over a threshold and PASSED with
	 * mwDedupe() removed entirely, because Chaikin turns each stair into
	 * many short segments and short ones were not counted. Measured by
	 * length the two are unmistakable: 0.24 with the dedupe, 0.56
	 * without. The threshold sits between the measurements rather than
	 * being picked.
	 */
	const world = W.mwWorld(FACTIONS, 1400, 980);
	let near = 0, total = 0;
	for (const r of world.regions) {
		for (let i = 0; i < r.poly.length; i++) {
			const a = r.poly[i], b = r.poly[(i + 1) % r.poly.length];
			const dx = Math.abs(a.x - b.x), dy = Math.abs(a.y - b.y);
			const d = Math.hypot(dx, dy);
			if (d < 1e-9) continue;
			total += d;
			const ang = Math.atan2(dy, dx) * 180 / Math.PI;
			if (ang < 2 || ang > 88) near += d;
		}
	}
	const frac = near / total;
	ok(frac < 0.35,
	   (frac * 100).toFixed(0) + '% of border LENGTH runs along an axis (0.24 is smoothed, '
	 + '0.56 is the raw grid); the staircase is showing through');
	console.log('        (near-axis border length ' + (frac * 100).toFixed(0) + '%)');
}

console.log('\nthe pieces behave on their own');
{
	/* Winding number, which is what a concave coast needs -- a crossing
	   count gets a self-touching ring wrong. */
	const sq = [{ x: 0, y: 0 }, { x: 10, y: 0 }, { x: 10, y: 10 }, { x: 0, y: 10 }];
	ok(W.mwInside(5, 5, sq) === true, 'a point inside a square reads as outside');
	ok(W.mwInside(-1, 5, sq) === false, 'a point left of a square reads as inside');
	ok(W.mwInside(11, 5, sq) === false, 'a point right of a square reads as inside');

	/* A traced ring closes. An open ring draws as a gash across a
	   territory and is the kind of thing that only shows up on one
	   faction, on one screen size, once. */
	const owner = new Int16Array(16).fill(-1);
	[5, 6, 9, 10].forEach(i => { owner[i] = 0; });
	const ring = W.mwTrace(owner, 0, 4, 4, 10, 0, 0);
	ok(ring.length === 8 || ring.length === 4,
	   'a 2x2 block traced to ' + ring.length + ' points');
	const rb = bbox(ring);
	ok(rb.x0 === 10 && rb.y0 === 10 && rb.x1 === 30 && rb.y1 === 30,
	   'the traced ring is in the wrong place: ' + JSON.stringify(rb));

	/* NOTHING TO DRAW IS NULL, NOT AN EMPTY CONTINENT. map.js falls back
	   to its old packing on null; an empty world would draw a sea. */
	ok(W.mwWorld([], 1400, 980) === null, 'no factions still produces a world');
	ok(W.mwWorld(FACTIONS, 0, 980) === null, 'a zero-width canvas still produces a world');
	ok(W.mwWorld(null, 1400, 980) === null, 'a null faction list still produces a world');

	/* One faction is a real state -- a new platform, or every project but
	   one having no realms -- and it must get the whole continent. */
	const solo = W.mwWorld([{ name: 'Only', weight: 1 }], 1400, 980);
	ok(solo && solo.regions.length === 1, 'a single faction does not get a world');
}

console.log('\nit is cheap enough for a phone');
{
	/* This runs on page load on a mobile browser. The grid is ~24k cells
	   with a point-in-polygon test each, four times over. */
	const t0 = Date.now();
	W.mwWorld(FACTIONS, 1400, 980);
	const ms = Date.now() - t0;
	ok(ms < 400, 'the world took ' + ms + 'ms to build, which is a visible stall on load');
	console.log('        (built in ' + ms + 'ms)');
}

console.log('\n' + (fail ? fail + ' FAILED\n' : 'all good\n'));
process.exit(fail ? 1 : 0);
