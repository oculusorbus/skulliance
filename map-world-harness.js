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
/* The faction names the live map actually carries, in its own order. */
const LIVE_NAMES = ['Omen', 'Kimosabe Art', 'Perps', 'Black Flag', 'Maxingo',
	'Nethanel Cohen', 'Sinder Skullz', 'Ohh Meed', 'Heist on Alpha', 'Old Money',
	'Hellknights', 'Dead Heads Club', 'Crypties', 'Danketsu', 'Ancestors',
	'Havoc Worlds'];

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
	   'the geography depends on the order the factions arrived in');

	/*
	 * AND A NEW REALM MUST NOT REDRAW THE CONTINENT. Borders move, which
	 * is correct -- territory follows realms -- but the coast is the thing
	 * players recognise and it has to survive somebody joining.
	 */
	const grown = FACTIONS.map(f => f.name === 'Omen' ? { name: f.name, weight: f.weight + 4 } : f);
	const d = W.mwWorld(grown, 1400, 980);
	ok(JSON.stringify(a.coast) === JSON.stringify(d.coast),
	   'a faction gaining realms redraws the land, so the world changes shape whenever '
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
	/*
	 * THE SHAPES THE PAGE ACTUALLY COMPOSES AT, not a square-ish one that
	 * happens to work. The first version of this tested 1400x980 only, and
	 * every realistic desktop canvas was starving one faction out of eight
	 * -- which tripped worldLayout()'s "did everyone get land" guard and
	 * silently reverted the whole map to the old packing. A correct-looking
	 * fallback is the worst way for a feature to fail, and a test list that
	 * omits the real aspect ratios is how it got shipped.
	 */
	for (const [w, h] of [[1400, 980], [1860, 888], [1380, 708], [1940, 1208],
	                      [900, 630], [2400, 700], [900, 430], [640, 900]]) {
		const world = W.mwWorld(FACTIONS, w, h);
		ok(world !== null, 'no world at ' + w + 'x' + h);
		if (!world) continue;
		const c = bbox([].concat.apply([], world.coast));
		ok(c.x0 >= -1 && c.y0 >= -1 && c.x1 <= w + 1 && c.y1 <= h + 1,
		   'the land overflows a ' + w + 'x' + h + ' sheet: x ' + c.x0.toFixed(0) + '-'
		 + c.x1.toFixed(0) + ', y ' + c.y0.toFixed(0) + '-' + c.y1.toFixed(0));
		/* And it has to USE the sheet. A map is a rectangle with geography
		   in it; land huddled in the middle is the island this replaced. */
		ok((c.x1 - c.x0) > w * 0.80 && (c.y1 - c.y0) > h * 0.70,
		   'the land only spans ' + (((c.x1 - c.x0) / w) * 100).toFixed(0) + '% x '
		 + (((c.y1 - c.y0) / h) * 100).toFixed(0) + '% of a ' + w + 'x' + h + ' sheet');
	}
}

console.log('\nnobody is left off the world, and land tracks realms');
{
	/*
	 * ONE REALM BESIDE FOUR HUNDRED.
	 *
	 * Territory is apportioned by realm count, and the spread between the
	 * biggest holder and a brand-new faction has no ceiling. Uncapped, the
	 * big faction's seed reaches so far that a small one is left with no
	 * cell at all -- and a faction with no land trips the landless guard
	 * in worldLayout(), which throws away THE WHOLE MAP and falls back to
	 * the old packing. So the loss is never "one faction looks small", it
	 * is "the new map silently stopped rendering".
	 *
	 * Hence the seed weight cap. Measured: uncapped, one of these worlds
	 * loses a faction; capped, none of them do.
	 */
	let landless = 0, built = 0;
	for (const big of [400, 60, 16]) {
		for (const [w, h] of [[1860, 888], [1200, 700], [960, 520]]) {
			for (let k = 0; k < 3; k++) {
				const F = LIVE_NAMES.map((name, i) => ({
					name: name + (k ? ' ' + k : ''), weight: i === 0 ? big : 1
				}));
				const wd = W.mwWorld(F, w, h);
				built++;
				landless += wd ? F.length - wd.regions.length : F.length;
			}
		}
	}
	ok(landless === 0, landless + ' factions across ' + built
	 + ' lopsided worlds got no land at all, which reverts the entire map');

	/* AT EVERY SHAPE, for the reason in the block above: this held at
	   1400x980 and failed at every real desktop size. */
	for (const [w, h] of [[1400, 980], [1860, 888], [1380, 708], [1940, 1208],
	                      [900, 430], [2400, 700]]) {
		const wd = W.mwWorld(FACTIONS, w, h);
		ok(wd && wd.regions.length === FACTIONS.length,
		   'at ' + w + 'x' + h + ' only ' + (wd ? wd.regions.length : 0) + ' of '
		 + FACTIONS.length + ' factions got territory, so worldLayout() reverts to the old '
		 + 'packing and the whole map quietly stops being a world');
	}
	/* And a faction squeezed to nothing must be RESCUED rather than left
	   out: one realm against a field of big ones is the common case. */
	const lopsided = [{ name: 'Giant', weight: 400 }, { name: 'Tiny', weight: 1 },
	                  { name: 'Small', weight: 2 }];
	const lw = W.mwWorld(lopsided, 1860, 888);
	ok(lw && lw.regions.length === 3,
	   'a one-realm faction beside a 400-realm one is squeezed off the map entirely');

	const world = W.mwWorld(FACTIONS, 1400, 980);

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

console.log('\nit is a map sheet, not an island');
{
	/*
	 * THE FIRST VERSION WAS A RADIAL COASTLINE -- summed harmonics around
	 * a centre -- and the owner's read of it was exactly right: "the map
	 * now feels like an island". One closed ring around one centre cannot
	 * produce anything else. The land is a NOISE FIELD over the whole
	 * rectangle now, thresholded, so seas, inlets, offshore islands and
	 * land running off the sheet all fall out of one surface.
	 *
	 * Twelve worlds, because a single seed proves nothing about variety.
	 */
	const base = FACTIONS.map(f => f.name);
	let masses = 0, worlds = 0, islandsHeld = 0, mostMasses = 0, fewestMasses = Infinity;
	for (let k = 0; k < 12; k++) {
		const F = FACTIONS.map((f, i) => ({ name: base[i] + (k ? ' ' + k : ''), weight: f.weight }));
		const wd = W.mwWorld(F, 1860, 888);
		ok(wd !== null, 'world ' + k + ' did not generate');
		if (!wd) continue;
		worlds++;
		masses += wd.masses;
		if (wd.masses > mostMasses) mostMasses = wd.masses;
		if (wd.masses < fewestMasses) fewestMasses = wd.masses;
		islandsHeld += wd.regions.reduce((a, r) => a + (r.polys.length - 1), 0);
		ok(wd.regions.length === FACTIONS.length,
		   'world ' + k + ' left ' + (FACTIONS.length - wd.regions.length) + ' faction(s) '
		 + 'without land');
	}
	const avg = masses / worlds;
	/* Measured across the sweep that chose the defaults: 3.1/0.62 gives
	   one continent with a pond, 5.4/0.40 gives six scraps. The shipped
	   4.8/0.45 sits around three. */
	ok(avg > 1.6, 'twelve worlds averaged ' + avg.toFixed(1) + ' landmasses; the map is a '
	 + 'single blob again');
	ok(mostMasses >= 4, 'no world in twelve had more than ' + mostMasses + ' landmasses, so '
	 + 'there is no variety between them');
	/*
	 * AND THE FLOOR MATTERS MORE THAN THE AVERAGE. The owner picked the
	 * archipelago out of four previews; a setting that averages four
	 * masses but drops to one every few worlds still hands somebody the
	 * island this replaced.
	 */
	ok(fewestMasses >= 2, 'one of twelve worlds came out as ' + fewestMasses + ' landmass(es); '
	 + 'the floor is what a player actually gets, not the average');

	/*
	 * IT REACHES THE EDGES OF THE SHEET. This is what separates a map from
	 * an island, and the land-span check above does NOT catch it -- a
	 * strong edge falloff still leaves land spanning most of the frame
	 * while rounding every corner into open water. A real sheet has land
	 * running off at least a couple of sides.
	 */
	const edgeWorld = W.mwWorld(FACTIONS, 1860, 888);
	const allPts = [].concat.apply([], edgeWorld.coast);
	const band = 1860 * 0.04;
	const sides = {
		left:   allPts.some(p => p.x < band),
		right:  allPts.some(p => p.x > 1860 - band),
		top:    allPts.some(p => p.y < 888 * 0.04),
		bottom: allPts.some(p => p.y > 888 - 888 * 0.04)
	};
	const touching = Object.keys(sides).filter(k => sides[k]).length;
	ok(touching >= 2,
	   'land reaches only ' + touching + ' of the four edges, so the frame reads as open sea '
	 + 'around an island rather than as a map sheet');
	ok(islandsHeld > 0,
	   'not one faction in twelve worlds holds an offshore island; the terrain is producing '
	 + 'no islands worth keeping, or the tracer is dropping them');
	console.log('        (' + avg.toFixed(1) + ' landmasses on average, up to ' + mostMasses
	          + '; ' + islandsHeld + ' faction-held islands across twelve worlds)');

	/* EVERY REGION CARRIES ITS PIECES. mwTrace used to return the longest
	   ring only, which silently deleted a faction's islands. */
	const one = W.mwWorld(FACTIONS, 1860, 888);
	ok(one.regions.every(r => Array.isArray(r.polys) && r.polys.length >= 1),
	   'a region has no polys list, so map.js draws only its main body');
	ok(one.regions.every(r => r.polys[0] === r.poly),
	   'poly is not the first and largest of polys, so the label and the markers sit on an '
	 + 'island instead of the body');
}

console.log('\nno slivers, no dots -- at the faction count the live map actually has');
{
	/*
	 * THE EIGHT-FACTION FIXTURE WAS HIDING THIS. The live map carries
	 * sixteen factions, most of them with one or two realms, and at that
	 * density the nearest-seed assignment strands single cells inside
	 * neighbours and runs ownership one cell wide for long stretches.
	 * Traced and smoothed those are dots and worms -- reported as "all
	 * this messiness and garbled borders disrupts the cleanliness and
	 * readability of the map", with the scraps circled.
	 *
	 * Measured in PIXELS OF AREA, because that is what a reader sees. The
	 * worst piece before the majority filter was 60px2, which is a speck
	 * with a faction colour on it.
	 */
	const LIVE = [['Omen',3],['Kimosabe Art',1],['Perps',2],['Black Flag',2],['Maxingo',1],
		['Nethanel Cohen',1],['Sinder Skullz',2],['Ohh Meed',2],['Heist on Alpha',2],
		['Old Money',1],['Hellknights',1],['Dead Heads Club',4],['Crypties',2],
		['Danketsu',1],['Ancestors',1],['Havoc Worlds',1]]
		.map(([name, weight]) => ({ name, weight }));
	const area = (p) => {
		let a = 0;
		for (let i = 0; i < p.length; i++) {
			const q = p[i], r = p[(i + 1) % p.length];
			a += q.x * r.y - r.x * q.y;
		}
		return Math.abs(a / 2);
	};

	let worst = Infinity, pieces = 0, islands = 0;
	for (let k = 0; k < 6; k++) {
		const F = LIVE.map((f, i) => ({ name: f.name + (k ? ' ' + k : ''), weight: f.weight }));
		const wd = W.mwWorld(F, 1860, 888);
		ok(wd && wd.regions.length === F.length,
		   'world ' + k + ': only ' + (wd ? wd.regions.length : 0) + ' of ' + F.length
		 + ' factions got territory at the live faction count');
		if (!wd) continue;
		for (const r of wd.regions) {
			islands += r.polys.length - 1;
			for (const piece of r.polys) {
				pieces++;
				const a = area(piece);
				if (a < worst) worst = a;
			}
		}
	}
	ok(worst > 2500,
	   'the smallest drawn piece across six live-shaped worlds is ' + Math.round(worst)
	 + 'px2; anything under a few thousand is a dot with a faction colour on it');
	console.log('        (' + pieces + ' pieces, smallest ' + Math.round(worst)
	          + 'px2, ' + islands + ' islands kept)');

	/* AND THE ISLANDS SURVIVED THE TIDY-UP. The fix for scraps is "absorb
	   anything small that TOUCHES a neighbour", not "delete anything
	   small" -- an offshore island has no land neighbour and is the best
	   thing the terrain makes. Deleting it to fix the symptom would have
	   thrown away the feature. */
	ok(islands > 0,
	   'six live-shaped worlds produced no faction-held islands at all; the scrap absorption '
	 + 'is eating them');
}

console.log('\nthe grid is tidied before it is traced');
{
	/* A stranded cell is outvoted by its neighbours. */
	const g = new Int16Array(25).fill(0);
	g[12] = 1;                                   // one cell of faction 1 inside faction 0
	W.mwMajority(g, 5, 5, 1, -1);
	ok(g[12] === 0, 'a single stranded cell survives the majority filter');

	/* Sea is not a candidate and is never overwritten: smoothing WHO owns
	   a coast must not be able to move the coast. */
	const g2 = new Int16Array(9).fill(-1);
	g2[4] = 0;
	W.mwMajority(g2, 3, 3, 2, -1);
	ok(g2[4] === 0 && g2[0] === -1,
	   'the majority filter moved the coastline while smoothing ownership');

	/*
	 * A POND BELOW THE ISLAND FLOOR IS FILLED, AND THE OCEAN IS NOT.
	 *
	 * This is the one that produced the four smallest "pieces" on the
	 * live-shaped worlds: a two-cell lake comes back from mwTrace as an
	 * interior ring, the renderer fills it like any other piece, and it
	 * lands on the map as an 8x17px dot in the faction's colour.
	 */
	const gPond = new Uint8Array(100).fill(1);
	gPond[44] = 0; gPond[45] = 0;                       // a two-cell pond, enclosed
	[90, 91, 92].forEach(k => { gPond[k] = 0; });    // sea on the bottom edge
	const pond = W.mwFillPonds(gPond, 10, 10, 8);
	ok(pond === 2 && gPond[44] === 1 && gPond[45] === 1,
	   'a two-cell pond was left in the land to be drawn as a faction-coloured dot');
	ok(gPond[91] === 0, 'the ocean was filled in along with the pond');

	/* AND A LAKE THAT CLEARS THE FLOOR STAYS. The rule is "too small to
	   be a lake", not "no water inland" -- an inland sea is a feature. */
	const gLake = new Uint8Array(100).fill(1);
	[33, 34, 35, 43, 44, 45, 53, 54, 55].forEach(k => { gLake[k] = 0; });
	W.mwFillPonds(gLake, 10, 10, 8);
	ok(gLake[44] === 0, 'a nine-cell inland lake was filled in despite clearing the floor');

	/* A scrap attached to a neighbour is absorbed -- but it has to be a
	   scrap, not the faction's only land. Faction 1 gets a body in the
	   corner AND a detached two-cell lobe out in faction 0's ground. */
	const g3 = new Int16Array(100).fill(0);
	[0, 1, 10, 11].forEach(k => { g3[k] = 1; });  // the body, four cells
	[44, 45].forEach(k => { g3[k] = 1; });        // the scrap, two cells
	const moved = W.mwAbsorbFragments(g3, 10, 10, 3);
	ok(moved === 2 && g3[44] === 0, 'an attached two-cell scrap was not absorbed');
	ok(g3[0] === 1, 'the faction\'s body was absorbed along with its scrap');

	/* AND A FACTION WHOSE WHOLE TERRITORY IS UNDER THE FLOOR KEEPS IT.
	   Small is not the same as spare: absorbing it takes the faction off
	   the map, and worldLayout() then reverts the entire map. */
	const g5 = new Int16Array(100).fill(0);
	[44, 45].forEach(k => { g5[k] = 1; });        // all faction 1 has
	W.mwAbsorbFragments(g5, 10, 10, 8);
	ok(g5[44] === 1, 'a faction whose entire territory is small was absorbed out of existence');

	/* ...but an ISLAND is not, because it touches nobody. */
	const g4 = new Int16Array(100).fill(-1);
	[11, 12].forEach(k => { g4[k] = 1; });        // an island of faction 1, all sea around
	[55, 56, 65, 66].forEach(k => { g4[k] = 0; });
	W.mwAbsorbFragments(g4, 10, 10, 8);
	ok(g4[11] === 1,
	   'an offshore island was absorbed as if it were a scrap; it touches no other faction '
	 + 'and is the most characterful thing on the map');
}

console.log('\nborders run between factions, not around them');
{
	/*
	 * REPORTED AS "a white boundary line wrapping the terrain, garbled
	 * between the factions". Every region's whole outline was stroked, so
	 * each seaward edge carried the shoreline, the faction's dashed line
	 * and a glow on one path. Real maps draw a border inland and stop at
	 * the coast, which is what mwBorders() returns.
	 *
	 * Tested on a hand-built grid, because on real terrain "is this edge
	 * coast or border" is not something an assertion can eyeball.
	 */
	// 4x4, left half faction 0, right half faction 1.
	const owner = new Int16Array(16);
	for (let j = 0; j < 4; j++) for (let i = 0; i < 4; i++) owner[j * 4 + i] = i < 2 ? 0 : 1;
	const b0 = W.mwBorders(owner, 0, 4, 4, 10, 10, 0, 0);
	ok(b0.length === 1, 'a straight shared edge produced ' + b0.length + ' border lines');
	const line = b0[0] || [];
	ok(line.length === 5, 'the shared edge has ' + line.length + ' points, not 5');
	ok(line.every(p => Math.round(p.x) === 20),
	   'the border is not on the line between the two factions');
	/* OPEN, NEVER CLOSED. Joining the ends draws a line straight across
	   somebody's territory. */
	ok(!(line[0].x === line[line.length - 1].x && line[0].y === line[line.length - 1].y),
	   'the border closed into a ring, so it cuts across the territory it belongs to');

	/* A FACTION WITH NO NEIGHBOURS HAS NO BORDER AT ALL -- its whole
	   outline is coast, which is the case that was drawing a doubled line
	   all the way round an island. */
	const alone = new Int16Array(16).fill(-1);
	[5, 6, 9, 10].forEach(k => { alone[k] = 0; });
	ok(W.mwBorders(alone, 0, 4, 4, 10, 10, 0, 0).length === 0,
	   'an island faction with no land neighbours is still given a drawn border, which is '
	 + 'the doubled line this replaced');

	/* The open smoother must keep its endpoints where they are, or a
	   border stops short of the coast it ran to. */
	const chain = [{ x: 0, y: 0 }, { x: 10, y: 0 }, { x: 10, y: 10 }];
	const sm = W.mwSmoothOpen(chain, 2);
	ok(sm[0].x === 0 && sm[0].y === 0, 'the open smoother moved the first point');
	ok(sm[sm.length - 1].x === 10 && sm[sm.length - 1].y === 10,
	   'the open smoother moved the last point, so the border no longer meets the coast');
	ok(W.mwDedupeOpen(chain).length === 3, 'an open chain lost a real corner');

	/* And on the real thing: every region carries its borders. */
	const wd = W.mwWorld(FACTIONS, 1860, 888);
	ok(wd.regions.every(r => Array.isArray(r.borders)),
	   'a region has no borders list, so map.js has nothing to draw between factions');
	const totalLines = wd.regions.reduce((a, r) => a + r.borders.length, 0);
	ok(totalLines > 0, 'eight factions on one world share no borders at all');
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
	 * SPECKS ARE FLOODED. A noise field makes single-cell islands by the
	 * dozen and at map scale they are dirt, not geography -- and each one
	 * is a ring the renderer draws. Tested on the function directly,
	 * because by the time mwWorld() returns they are already gone.
	 */
	const mask = new Uint8Array(100);
	[11, 12, 21, 22].forEach(k => { mask[k] = 1; });   // a 2x2 island
	[55, 77, 99].forEach(k => { mask[k] = 1; });        // three specks
	const kept = W.mwFloodSpecks(mask, 10, 10, 4);
	ok(kept.length === 1, 'speck flooding kept ' + kept.length + ' masses instead of 1');
	ok(mask[55] === 0 && mask[77] === 0 && mask[99] === 0,
	   'the single-cell specks survived, so the map is drawn with grit on it');
	ok(mask[11] === 1, 'the real island was flooded along with the specks');

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
	/* mwTrace returns ALL rings now, largest first -- it used to return the
	   longest one on its own, which deleted a faction's islands. */
	const rings = W.mwTrace(owner, 0, 4, 4, 10, 0, 0);
	ok(Array.isArray(rings) && rings.length === 1,
	   'a single 2x2 block traced to ' + (rings ? rings.length : 0) + ' rings');
	const ring = rings[0] || [];
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

console.log('\nthe canvas is sized from the page, and it is RUN to find out');
{
	/*
	 * EXECUTED, NOT READ. A source check cannot tell a real measurement
	 * from a constant: `const navH = 0;` leaves every string these
	 * assertions look for exactly where it was, and the map still runs off
	 * the bottom of the window by the height of the nav. The only way to
	 * know is to call the thing -- the same reason nft-panel-harness.js
	 * executes render() instead of reading it.
	 */
	const fs = require('fs');
	const vm = require('vm');
	const src = fs.readFileSync(__dirname + '/map.js', 'utf8');
	const grab = (sig) => {
		const a = src.indexOf(sig);
		if (a < 0) return '';
		const b = src.indexOf('\n}\n', a);
		return b < 0 ? '' : src.slice(a, b + 3);
	};
	const cw = grab('function composeWidth(');
	const ch = grab('function composeHeight(');
	ok(cw !== '' && ch !== '', 'composeWidth/composeHeight could not be lifted out of map.js');

	const run = (vw, vh, navW, navH) => {
		const ctx = {
			MOBILE_BP: 768, MOBILE_DESIGN_W: 1400, MOBILE_GUTTER: 60,
			window: { innerWidth: vw, innerHeight: vh },
			document: {
				getElementById: (id) => id === 'navbar'
					? { clientWidth: navW, offsetHeight: navH } : null,
				querySelector: () => null
			}
		};
		vm.createContext(ctx);
		vm.runInContext(cw + '\n' + ch + '\nvar _w = composeWidth(); var _h = composeHeight(_w);', ctx);
		return { w: ctx._w, h: ctx._h };
	};

	/* A 2560 viewport with the page capped at 2000: the map must follow the
	   page, not the screen. This is the bug the owner reported. */
	const wide = run(2560, 1080, 2000, 64);
	ok(wide.w <= 2000 - 60 + 1,
	   'on a 2560 screen with a 2000px header the map composes ' + wide.w + ' wide');
	ok(wide.w > 1600, 'the map is far narrower than the header it should match: ' + wide.w);

	/* THE NAV IS REALLY SUBTRACTED. A taller header must give a shorter
	   map, one pixel for one pixel -- which `navH = 0` cannot fake. */
	const short = run(1920, 1080, 1860, 64);
	const tall  = run(1920, 1080, 1860, 200);
	ok(short.h - tall.h === 136,
	   'a header 136px taller shortened the map by ' + (short.h - tall.h)
	 + 'px; the header height is not actually being subtracted');
	ok(short.h < 1080 && short.h > 300,
	   'the composed height of ' + short.h + ' does not fit sensibly in a 1080 window');

	/* MOBILE COMPOSES WIDE AND IS SCALED DOWN BY CSS, so its height has to
	   be divided back through that scale or the map lands a third of a
	   screen tall. */
	const phone = run(390, 844, 390, 56);
	ok(phone.w === 1400, 'a phone no longer composes at the design width: ' + phone.w);
	const drawn = phone.h * ((390 - 60) / 1400);
	ok(drawn > 300 && drawn < 844,
	   'on a 390x844 phone the map draws ' + drawn.toFixed(0) + 'px tall, which is not a '
	 + 'screenful');

	/* Nothing may come back zero: #map is display:none when this runs, and
	   a hidden probe measures zero. A zero canvas means no land at all. */
	const blind = run(1920, 1080, 0, 0);
	ok(blind.w > 0 && blind.h > 0,
	   'an unmeasurable page yields a ' + blind.w + 'x' + blind.h + ' canvas, so the world '
	 + 'is empty and the map falls back for good');
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
