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

/* The inset margin mwWorld reserves so the shelf has somewhere to go.
   Mirrored here on purpose: if the two formulas drift apart the
   assertions below stop describing the map that ships. */
const HARNESS_MARGIN = (w) => Math.ceil(26 * (220 / 360) * 0.65 * (w / 220) * 1.08);

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
		/*
		 * And it has to USE the sheet it was given. Land huddled in the
		 * middle is the island this replaced.
		 *
		 * MEASURED AGAINST THE GENERATED BOX, not the canvas. The world
		 * is inset by a margin so the shelf and the surf have somewhere
		 * to go, and on a short wide sheet that margin is a real slice
		 * of the height -- 2400x700 reserves 122px top and bottom out of
		 * 700. Against the canvas that reads as 60% and looks like the
		 * island coming back; against the box the land was actually
		 * drawn into, it is 92%.
		 */
		const mg = HARNESS_MARGIN(w);
		const bw = w - 2 * mg, bh = h - 2 * mg;
		ok((c.x1 - c.x0) > bw * 0.80 && (c.y1 - c.y0) > bh * 0.70,
		   'the land only spans ' + (((c.x1 - c.x0) / bw) * 100).toFixed(0) + '% x '
		 + (((c.y1 - c.y0) / bh) * 100).toFixed(0) + '% of the box generated inside a '
		 + w + 'x' + h + ' sheet');
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
	 * IT FILLS THE BOX IT WAS GENERATED INTO.
	 *
	 * THIS USED TO SAY THE OPPOSITE: land had to run off at least two
	 * edges of the CANVAS, because that is what separates a map from an
	 * island. Right when the coast was a line; wrong once the coast
	 * grows things outward -- shelf bands to 10.3 cells with a surf
	 * animation over them -- because a coast flush against the frame
	 * has all of that sliced off. Reported as "the land is in the
	 * container... barely, but with all our topography and animations,
	 * all that is bleeding over".
	 *
	 * The world is generated into an inset rectangle and placed in the
	 * middle, so the anti-island property moves with it. The geography
	 * is untouched: same noise, same archipelago.
	 *
	 * (Pushing land inward with a stronger edge falloff was tried first
	 * and measured. The sea level is bisected for a target land
	 * FRACTION, so suppressing the rim just concentrates the same land
	 * in the middle: masses 4.5 -> 1.6, span 94% -> 58%. That IS the
	 * island. The falloff was left exactly as it was.)
	 */
	const edgeWorld = W.mwWorld(FACTIONS, 1860, 888);
	const allPts = [].concat.apply([], edgeWorld.coast);
	const emg = HARNESS_MARGIN(1860);
	const ebw = 1860 - 2 * emg, ebh = 888 - 2 * emg;
	const sides = {
		left:   allPts.some(p => p.x < emg + ebw * 0.04),
		right:  allPts.some(p => p.x > 1860 - emg - ebw * 0.04),
		top:    allPts.some(p => p.y < emg + ebh * 0.04),
		bottom: allPts.some(p => p.y > 888 - emg - ebh * 0.04)
	};
	const touching = Object.keys(sides).filter(k => sides[k]).length;
	ok(touching >= 2,
	   'land reaches only ' + touching + ' of the four edges of the box it was generated '
	 + 'into, so it is an island in open sea rather than a sheet of map');

	/*
	 * AND THE MARGIN IS WIDE ENOUGH BY ARITHMETIC, not by luck of the
	 * terrain. An empirical check passes on a margin far too thin,
	 * because land rarely runs right up to the edge of its box --
	 * measured, a 45% margin still cleared fifteen worlds. But the rule
	 * directly above REQUIRES land to reach that edge, so the day it
	 * does, the shelf needs the room.
	 */
	const SHAPES = [[1860, 888], [1200, 700], [2400, 700], [1400, 980], [960, 520]];
	for (const [cw, ch] of SHAPES) {
		const wm = W.mwWorld(FACTIONS, cw, ch);
		if (!wm) continue;
		/* Read off the world that SHIPPED, not off a copy of the formula
		   -- a mirrored constant here would agree with itself while the
		   two numbers in map-world.js quietly drifted apart. */
		const reach = wm.shores[wm.shores.length - 1].d * wm.cell;
		ok(wm.margin >= reach,
		   'the ' + cw + 'x' + ch + ' sheet reserves a ' + wm.margin + 'px margin for a shelf '
		 + 'that reaches ' + reach.toFixed(0) + 'px, so a coast on the edge of its box is cut off');
	}

	/* And on real worlds: the outermost band is the furthest thing from
	   the land, so if it is inside the sheet the surf and foam are too. */
	let worstOut = -Infinity, outAt = '';
	for (const [cw, ch] of SHAPES) {
		const wd2 = W.mwWorld(FACTIONS, cw, ch);
		if (!wd2) continue;
		for (const r of wd2.shores[wd2.shores.length - 1].rings) {
			for (const p of r) {
				const out = Math.max(-p.x, -p.y, p.x - cw, p.y - ch);
				if (out > worstOut) { worstOut = out; outAt = cw + 'x' + ch; }
			}
		}
	}
	ok(worstOut < 0,
	   'the outermost shelf band runs ' + worstOut.toFixed(1) + 'px past the edge of the '
	 + outAt + ' sheet, so the topography is cut off by the container');
	console.log('        (shelf clears the sheet edge by ' + (-worstOut).toFixed(0) + 'px)');

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

console.log('\nthe shallows are contours of a distance field, not an outline');
{
	/*
	 * THE TECHNIQUE IS KIMO'S (spritecook.ai). Shade water by how far it
	 * is from land and band that, instead of stroking an outline round
	 * the coast -- a stroke is a constant width whatever the water is
	 * doing, so it looked the same across a 12px strait as on open ocean.
	 */

	/* THE FIELD ITSELF. One land cell in the middle of a 5x5: its
	   neighbours are 1 away and its diagonals 4/3, because the chamfer
	   pass charges 3 for a step and 4 for a diagonal. A field that only
	   counted city blocks would say 2 for the diagonal and the bands
	   would come out with square corners. */
	const one = new Uint8Array(25);
	one[12] = 1;
	const df = W.mwDistance(one, 5, 5);
	ok(df[12] === 0, 'land is not at distance zero from itself');
	ok(Math.abs(df[11] - 1) < 1e-6 && Math.abs(df[7] - 1) < 1e-6,
	   'a cell beside the land is ' + df[11] + ' away instead of 1');
	ok(Math.abs(df[6] - 4 / 3) < 1e-6,
	   'the diagonal is ' + df[6].toFixed(3) + ' away instead of 4/3, so the field is '
	 + 'counting city blocks and the bands will have square corners');
	ok(df[0] > df[6], 'the field does not grow with distance from the land');

	/* THE BANDS NEST, AND EVERY ONE CONTAINS THE LAND. Drawn biggest
	   first and stacked, so if they ever came back in the wrong order or
	   overlapping wrongly the sea would go flat. */
	const wd = W.mwWorld(FACTIONS, 1860, 888);
	ok(wd.shores && wd.shores.length === 4,
	   'the world came back with ' + (wd.shores ? wd.shores.length : 0) + ' shore bands, not 4');
	const ringArea = (p) => {
		let a = 0;
		for (let i = 0; i < p.length; i++) {
			const q = p[i], r = p[(i + 1) % p.length];
			a += q.x * r.y - r.x * q.y;
		}
		return a / 2;
	};
	const bandArea = (b) => b.rings.reduce((a, r) => a + ringArea(r), 0);
	const landArea = wd.coast.reduce((a, r) => a + ringArea(r), 0);
	let nested = true;
	for (let i = 1; i < wd.shores.length; i++) {
		if (!(bandArea(wd.shores[i]) > bandArea(wd.shores[i - 1]))) nested = false;
		if (!(wd.shores[i].d > wd.shores[i - 1].d)) nested = false;
	}
	ok(nested, 'the shore bands are not strictly nested, so they stack into a flat wash '
	 + 'instead of a gradient');
	ok(bandArea(wd.shores[0]) > landArea,
	   'the innermost band is smaller than the land it is supposed to surround');

	/*
	 * AND THE SEA IS NOT A MEGABYTE OF PATH DATA.
	 *
	 * Chaikin quadruples the points every two passes and most of them
	 * land within a pixel of each other on a smooth contour: invisible,
	 * but about fifteen characters of `d` attribute each. Measured in a
	 * real browser, the sea was 910KB of path data before thinning and
	 * 658KB after -- a parse cost on a phone long before it is a paint
	 * cost. Only the static shelf is geometry now; the surf is pixels.
	 */
	const seaPoints = (world) =>
		world.shores.reduce((a, b) => a + b.rings.reduce((x, r) => x + r.length, 0), 0);
	ok(seaPoints(wd) < 20000, 'the shelf is ' + seaPoints(wd)
	 + ' points of path data, which goes into the document as text on every load');

	/* AND THINNING KEEPS THE SHAPE. A circle of 720 points comes back as
	   240 with its area intact to four parts in ten thousand; a filter
	   that cut the shape instead of the redundancy would show up here. */
	const circle = [];
	for (let i = 0; i < 720; i++) {
		const t = i / 720 * 2 * Math.PI;
		circle.push({ x: 200 + 100 * Math.cos(t), y: 200 + 100 * Math.sin(t) });
	}
	const thinned = W.mwThin(circle, 2);
	const ringArea2 = (p) => {
		let a = 0;
		for (let i = 0; i < p.length; i++) {
			const q = p[i], r = p[(i + 1) % p.length];
			a += q.x * r.y - r.x * q.y;
		}
		return Math.abs(a / 2);
	};
	ok(thinned.length < circle.length / 2,
	   'thinning kept ' + thinned.length + ' of 720 points, so it is barely doing anything');
	ok(Math.abs(ringArea2(thinned) - ringArea2(circle)) / ringArea2(circle) < 0.001,
	   'thinning changed the enclosed area by more than 0.1%, so it is cutting the shape '
	 + 'and not just the redundant points');
	/* It must never destroy a shape it cannot thin. */
	ok(W.mwThin([{ x: 0, y: 0 }, { x: 1, y: 1 }], 5).length === 2,
	   'thinning destroyed a two-point line instead of leaving it alone');

	/* AND A SMALL RING IS LEFT ENTIRELY ALONE. Six points is a speck of
	   an island, and dropping even one of them is a visible change to
	   its shape rather than the removal of a redundant point -- there is
	   no redundancy left at that size. Below eight points mwThin does
	   not touch the ring at all. */
	const speck = [{ x: 0, y: 0 }, { x: 0.5, y: 0 }, { x: 100, y: 0 },
	               { x: 100, y: 100 }, { x: 0, y: 100 }, { x: 0, y: 50 }];
	ok(W.mwThin(speck, 2).length === 6,
	   'a six-point ring was thinned to ' + W.mwThin(speck, 2).length
	 + ' points, which reshapes a speck rather than de-duplicating it');

	/*
	 * AND THE WOBBLE IS THE WHOLE TRICK.
	 *
	 * Contouring the raw field gives bands at a CONSTANT distance from
	 * the coast, which reads as a sticker cut round the land -- the one
	 * thing that would look worse than no bands at all. Kimo adds noise
	 * to the distance before banding for exactly this reason.
	 *
	 * MEASURED AS THE SPREAD OF THE BAND'S DISTANCE FROM THE COAST, which
	 * is what "wanders instead of tracing" means as a number: constant
	 * distance is a small spread. (The first spelling of this compared
	 * PERIMETERS, which does not work -- the outermost band comes out
	 * 2% SHORTER with the wobble on, because wandering outward into open
	 * water cuts corners as often as it adds them.) Measured on band 2:
	 * 2.16px with the wobble off, 5.69px on.
	 */
	const spread = (world, bi) => {
		const pts = [];
		for (const r of world.coast) for (const p of r) pts.push(p);
		const vals = [];
		for (const r of world.shores[bi].rings) {
			for (let i = 0; i < r.length; i += 3) {
				const q = r[i];
				let best = Infinity;
				for (const c of pts) {
					const dx = c.x - q.x, dy = c.y - q.y, d = dx * dx + dy * dy;
					if (d < best) best = d;
				}
				vals.push(Math.sqrt(best));
			}
		}
		const m = vals.reduce((a, v) => a + v, 0) / vals.length;
		return Math.sqrt(vals.reduce((a, v) => a + (v - m) * (v - m), 0) / vals.length);
	};
	const flat = W.mwWorld(FACTIONS, 1860, 888, { shoreWobble: 0 });
	const sOn = spread(wd, 2), sOff = spread(flat, 2);
	ok(sOn > 3.5, 'the shore band sits ' + sOn.toFixed(2) + 'px from the coast give or take, '
	 + 'against ' + sOff.toFixed(2) + 'px with the wobble off -- so it is tracing the '
	 + 'coastline at a constant offset, like a sticker cut round the land');
	console.log('        (band 2 wanders ' + sOn.toFixed(2) + 'px, against '
	          + sOff.toFixed(2) + 'px with the wobble off)');

	/*
	 * THE SHALLOWS DEPEND ON THE LAND AND NOTHING ELSE. They are built
	 * from the land mask, so like the coastline they must not move when
	 * somebody gains a realm.
	 */
	const plainS = LIVE_NAMES.map((name, i) => ({ name: name, weight: 1 + (i % 4) }));
	const lopS = LIVE_NAMES.map((name, i) => ({ name: name, weight: i === 0 ? 90 : 1 }));
	ok(JSON.stringify(W.mwWorld(plainS, 1860, 888).shores)
	   === JSON.stringify(W.mwWorld(lopS, 1860, 888).shores),
	   'the shallows moved when the realm counts did, so joining a faction redraws the sea');
}

console.log('\nthe tracer does not lose an edge where land pinches');
{
	/*
	 * TWO CELLS TOUCHING ONLY AT A CORNER ARE TWO BLOBS, NOT ONE.
	 *
	 * A noise field produces diagonal pinches constantly. The boundary
	 * passes through that shared corner twice, and the tracer keyed its
	 * edges by vertex alone in a Map -- so the second edge overwrote the
	 * first and was silently DROPPED, and the ring that came out was not
	 * the outline of anything.
	 *
	 * Arriving at such a corner the tracer now takes the sharpest RIGHT
	 * turn, which keeps the land on the right and closes each blob as its
	 * own ring. That is not a free choice: every flood fill in this file
	 * treats cells as 4-connected, so a tracer that turned left and
	 * stapled the two blobs into one ring would disagree with the
	 * ownership it is drawing. Measured: right gives 2 rings of 4 points,
	 * left gives 1 ring of 8.
	 */
	const pinch = new Int16Array(16).fill(-1);
	pinch[5] = 0; pinch[10] = 0;        // (1,1) and (2,2), corner to corner
	const pr = W.mwTrace(pinch, 0, 4, 4, 10, 0, 0);
	ok(pr.length === 2, 'two diagonally-touching cells traced as ' + pr.length
	 + ' ring(s) instead of 2, so the tracer disagrees with the 4-connected fills');
	ok(pr.every(r => r.length === 4),
	   'the pinched rings came out as ' + pr.map(r => r.length).join('/')
	 + ' points instead of 4 and 4, so an edge was lost or two blobs were stapled together');

	/*
	 * THE RINGS ACCOUNT FOR EVERY CELL -- AT AWKWARD CELL SIZES.
	 *
	 * This is the assertion that was missing when the tracer shipped
	 * broken. It keyed each vertex as Math.round(x) + ',' + Math.round(y),
	 * and a cell emits a corner as x0 + cs while its neighbour emits the
	 * same corner as ox + (i + 1) * cs: equal in arithmetic, not always
	 * equal in floating point. Land on a vertex sitting a hair either
	 * side of .5 and the two round to different integers, nothing leaves
	 * that vertex, and the ring ends mid-coast as an open polyline whose
	 * area is nonsense. Measured on a real 1400x980 world: 1658 boundary
	 * edges, no pinches at all, 14 of them ending at a vertex with
	 * nothing leaving it, and 26 rings totalling 22,905px2 of a
	 * 443,347px2 continent.
	 *
	 * Nothing here noticed, because every fixture used round numbers:
	 * cs = 10, ox = 0. The offsets below are deliberately nasty, the
	 * kind the inset margin produces, and the property is exact -- the
	 * area the rings enclose IS the cell count times the cell area, or
	 * a chain broke.
	 */
	const blob = new Int16Array(26 * 26).fill(-1);
	let blobCells = 0;
	for (let j = 2; j < 24; j++) {
		for (let i = 2; i < 24; i++) {
			const dd = Math.hypot(i - 13, j - 13);
			if (dd < 10.4 && dd > 3.2 && !(i === 7 && j === 19)) { blob[j * 26 + i] = 0; blobCells++; }
		}
	}
	const sArea = (pp) => {
		let a = 0;
		for (let i = 0; i < pp.length; i++) {
			const q = pp[i], t = pp[(i + 1) % pp.length];
			a += q.x * t.y - t.x * q.y;
		}
		return a / 2;
	};
	let worstErr = 0, worstCase = '';
	for (const cs of [10, 5.718, 7.5905, 3.9182, 9.80909, 6.36363, 8.4545]) {
		for (const ox of [0, 71, 95.5, 122.25, 49.37]) {
			const rs = W.mwTrace(blob, 0, 26, 26, cs, ox, ox);
			const err = Math.abs(rs.reduce((a, r) => a + sArea(r), 0) - blobCells * cs * cs)
				/ (blobCells * cs * cs);
			if (err > worstErr) { worstErr = err; worstCase = 'cs=' + cs + ' ox=' + ox; }
		}
	}
	ok(worstErr < 1e-9,
	   'the traced rings enclose an area ' + (worstErr * 100).toFixed(1) + '% off the cells '
	 + 'they came from (' + worstCase + '), so a chain is breaking and leaving open polylines');

	/* AND NOTHING IS DROPPED: both squares are still there. */
	const tot = pr.reduce((a, r) => a + Math.abs(r.reduce((b, q, i) => {
		const t = r[(i + 1) % r.length];
		return b + (q.x * t.y - t.x * q.y);
	}, 0) / 2), 0);
	ok(Math.round(tot) === 200, 'the two 10x10 cells traced to ' + Math.round(tot)
	 + 'px2 instead of 200, so an edge went missing at the pinch');
}

console.log('\nthe territories tile the land, with no notch where they meet');
{
	/*
	 * REPORTED AS "triangular dips that occur when territories meet each
	 * other because of rounded edges leading up to the boundary instead
	 * of a cohesive shoreline that is split by the dividing line."
	 *
	 * Every region outline used to be rounded as its own closed loop, and
	 * the coastline as another. At a triple point -- two territories and
	 * the sea -- Chaikin cut the corner off each territory separately, so
	 * both fills pulled back from the junction and left a notch of bare
	 * land between them, which is what the owner circled.
	 *
	 * MEASURED AS AREA, because a notch is land that no territory covers.
	 * Sum the signed area of the coastline rings (signed, so a lake
	 * subtracts itself instead of being counted as more land -- the first
	 * spelling of this took abs() of every ring, reported 4.16% and barely
	 * moved when the bug was fixed, because it was measuring lakes).
	 * Then sum the signed area of every territory piece. The difference is
	 * land the map draws no owner for: 15,104px2 before, 0 after.
	 */
	const sarea = (p) => {
		let a = 0;
		for (let i = 0; i < p.length; i++) {
			const q = p[i], r = p[(i + 1) % p.length];
			a += q.x * r.y - r.x * q.y;
		}
		return a / 2;
	};
	/*
	 * ACROSS THE WHOLE MATRIX, NOT SIX WORLDS OF ONE SHAPE.
	 *
	 * Six worlds at one canvas size said 0.00px2 while a sixty-world
	 * sweep still had one at 3,786px2 -- a territory with a lake inside
	 * it, painting the open water its own colour. That is twice now that
	 * a too-narrow fixture has hidden one of these (the slivers needed 16
	 * factions, not 8), so this runs the shapes the platform can actually
	 * produce: 3 to 16 factions over four canvases.
	 */
	let worst = 0, worstAt = '', built = 0;
	for (const n of [3, 5, 8, 12, 16]) {
		for (const [w, h] of [[1860, 888], [1200, 700], [960, 520], [1400, 980]]) {
			for (let k = 0; k < 3; k++) {
				const F = LIVE_NAMES.slice(0, n).map((name, i) => ({
					name: name + (k ? ' ' + k : ''), weight: 1 + ((i * 7 + k) % 6)
				}));
				const wd = W.mwWorld(F, w, h);
				if (!wd) continue;
				built++;
				let coastArea = 0, heldArea = 0;
				for (const r of wd.coast) coastArea += sarea(r);
				for (const rg of wd.regions) for (const piece of rg.polys) heldArea += sarea(piece);
				const gap = Math.abs(coastArea - heldArea);
				if (gap > worst) { worst = gap; worstAt = n + ' factions at ' + w + 'x' + h + ' #' + k; }
			}
		}
	}
	ok(worst < 10, Math.round(worst) + 'px2 of land is covered by no territory at all (' + worstAt
	 + '), which is the notch where two territories meet the sea');
	console.log('        (' + built + ' worlds, worst uncovered ' + worst.toFixed(2) + 'px2)');

	/*
	 * AND THE COASTLINE ITSELF MUST NOT HAVE MOVED TO ACHIEVE IT.
	 *
	 * The first attempt cut the coastline at the junctions as well, which
	 * tiles perfectly and makes the shoreline depend on who owns what --
	 * so gaining a realm redrew the coast. The territories bend to the
	 * coastline, never the other way round. (The realm-count assertion at
	 * the top of this file is what caught it; this one states the rule
	 * where the fix lives.)
	 */
	const plain = LIVE_NAMES.map((name, i) => ({ name: name, weight: 1 + (i % 4) }));
	const lop = LIVE_NAMES.map((name, i) => ({ name: name, weight: i === 0 ? 90 : 1 }));
	ok(JSON.stringify(W.mwWorld(plain, 1860, 888).coast)
	   === JSON.stringify(W.mwWorld(lop, 1860, 888).coast),
	   'the coastline changed when the realm counts did, so joining a faction redraws the world');
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
	const ring0 = W.mwTrace(owner, 0, 4, 4, 10, 0, 0)[0];
	const sides0 = W.mwRingSides(ring0, owner, 0, 4, 4, 10, 0, 0);
	ok(sides0.filter(v => v === 1).length === 4 && sides0.filter(v => v === -1).length === 8,
	   'the outline was not split into 4 edges facing faction 1 and 8 facing the sea');
	const seam0 = W.mwSeams(ring0, sides0, null, 3);
	const b0 = seam0.runs.filter(r => r.side >= 0);
	ok(b0.length === 1, 'a straight shared edge produced ' + b0.length + ' border lines');
	const line = (b0[0] || { pts: [] }).pts;
	ok(line.length >= 2 && line.every(p => Math.round(p.x) === 20),
	   'the border is not on the line between the two factions');
	/* OPEN, NEVER CLOSED. Joining the ends draws a line straight across
	   somebody's territory. */
	ok(!(line[0].x === line[line.length - 1].x && line[0].y === line[line.length - 1].y),
	   'the border closed into a ring, so it cuts across the territory it belongs to');
	/* AND IT SPANS THE WHOLE SHARED EDGE, corner to corner. The open
	   smoother keeps its endpoints precisely so a border still reaches
	   the coast it ran to. */
	const ys = line.map(p => p.y);
	ok(Math.min(...ys) === 0 && Math.max(...ys) === 40,
	   'the border stops short of the coast at one or both ends');

	/*
	 * AND THE TWO NEIGHBOURS GET THE IDENTICAL CURVE.
	 *
	 * This is the whole point of dressing a seam once. Faction 1 walks
	 * the same edge in the opposite direction; if each smoothed its own
	 * outline independently the two fills would not meet, which is the
	 * notch this replaced.
	 */
	const ring1 = W.mwTrace(owner, 1, 4, 4, 10, 0, 0)[0];
	const seam1 = W.mwSeams(ring1, W.mwRingSides(ring1, owner, 1, 4, 4, 10, 0, 0), null, 3);
	const line1 = seam1.runs.filter(r => r.side >= 0)[0].pts.slice().reverse();
	ok(line1.length === line.length
	   && line.every((p, i) => Math.abs(p.x - line1[i].x) < 1e-9
	                        && Math.abs(p.y - line1[i].y) < 1e-9),
	   'the two sides of one border are not the same curve, so the fills leave a gap');

	/* A FACTION WITH NO NEIGHBOURS HAS NO BORDER AT ALL -- its whole
	   outline is coast, which is the case that was drawing a doubled line
	   all the way round an island. */
	const alone = new Int16Array(16).fill(-1);
	[5, 6, 9, 10].forEach(k => { alone[k] = 0; });
	const ringA = W.mwTrace(alone, 0, 4, 4, 10, 0, 0)[0];
	const seamA = W.mwSeams(ringA, W.mwRingSides(ringA, alone, 0, 4, 4, 10, 0, 0), null, 3);
	ok(seamA.runs.filter(r => r.side >= 0).length === 0,
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

console.log('\nthe shallows reach the drawing, and the surf actually flows');
{
	/*
	 * EXECUTED, NOT READ, and learned the hard way three times. A source
	 * check passed on a renderer wrapped in `if (false)`. A unit test on
	 * seaLayers() passed while the live map drew no water, because
	 * worldLayout() builds its own object and had dropped it. And the
	 * surf looked right in every static frame while reading as "flashing
	 * lights" in motion.
	 */
	const fs = require('fs');
	const vm = require('vm');
	const src = fs.readFileSync(__dirname + '/map.js', 'utf8');
	const lift = (sig) => {
		const i = src.indexOf(sig);
		const j = i < 0 ? -1 : src.indexOf('\n}\n', i);
		return (i >= 0 && j > i) ? src.slice(i, j + 3) : '';
	};
	const consts = src.slice(src.indexOf('const WATER_TEMPO'),
		src.indexOf('};', src.indexOf('const WATER = {')) + 2);
	const parts = ['function smoothEdge(', 'function foamEdgeAt(', 'function surfFoam(',
	               'function surfWave(', 'function seaLayers(', 'function worldLayout('].map(lift);
	ok(consts && parts.every(Boolean), 'the water functions could not be lifted out of map.js');

	const ctx = { mwWorld: W.mwWorld, Math: Math, console: { warn: () => {} } };
	vm.createContext(ctx);
	vm.runInContext(consts + '\n' + parts.join('\n')
		+ '\nvar _W = WATER, _foam = surfFoam, _wave = surfWave, _edge = foamEdgeAt;'
		+ '\nvar _joined = (f, w, h) => seaLayers(worldLayout(f, w, h));', ctx);
	const WAT = ctx._W, F = 1 / 60;

	/*
	 * NOTHING SNAPS ON. THIS IS THE ASSERTION THE REWRITE EXISTS FOR.
	 *
	 * His shader tests `phase < 1.6`, a hard in-or-out, which makes a
	 * pixel's alpha a STEP FUNCTION OF TIME: off, then on, nothing in
	 * between. At his tempo those steps come fast enough to read as
	 * flow; at half speed they do not, and the owner called it
	 * immediately -- "creeping and stuttery" is thousands of pixels each
	 * snapping on at its own moment.
	 *
	 * The ramp makes alpha continuous in t, because the phase term
	 * carries t continuously even though a pixel's distance never
	 * changes. Measured as the largest alpha change at a FIXED pixel
	 * between consecutive frames: 0.04 with the ramp, 1.00 with a step.
	 */
	let jump = 0, jumpAt = 0;
	for (let d = WAT.waveLo + 0.2; d < WAT.waveHi; d += 0.07) {
		let prev = ctx._wave(d, 1 * WAT.speed);
		for (let f = 1; f < 240; f++) {
			const al = ctx._wave(d, (1 + f * F) * WAT.speed);
			if (Math.abs(al - prev) > jump) { jump = Math.abs(al - prev); jumpAt = d; }
			prev = al;
		}
	}
	ok(jump < 0.15, 'a pixel\'s wave alpha jumps ' + jump.toFixed(3) + ' in one frame at d='
	 + jumpAt.toFixed(1) + '; that is a pixel snapping on, which is what stutter is');

	/* The same for the foam, whose edge breathes rather than travels. */
	let fj = 0;
	for (let d = 0.5; d < WAT.foamMid + WAT.foamSwing + 2; d += 0.07) {
		let prev = ctx._foam(d, ctx._edge(0));
		for (let f = 1; f < 400; f++) {
			const al = ctx._foam(d, ctx._edge(f * F));
			fj = Math.max(fj, Math.abs(al - prev));
			prev = al;
		}
	}
	ok(fj < 0.15, 'the foam alpha jumps ' + fj.toFixed(3) + ' in one frame');
	console.log('        (largest alpha change at a pixel per frame: wave ' + jump.toFixed(3)
	          + ', foam ' + fj.toFixed(3) + '; a hard edge would be 1.000)');

	/*
	 * AND THE BAND IS WHOLE AT THE WRAP. The crest lives on a modulus,
	 * so its soft trailing edge sits just below phase 0 and reappears
	 * just below `spacing`. Measuring the distance to the crest without
	 * wrapping slices that tail off and costs 0.54 of alpha in one
	 * frame -- which is a step, dressed up as a ramp.
	 */
	const atPhase = (ph) => ctx._wave(WAT.waveLo + (WAT.waveHi - WAT.waveLo) / 2, ph);
	const mid = WAT.waveLo + (WAT.waveHi - WAT.waveLo) / 2;
	let wrapJump = 0;
	for (let q = 0; q < WAT.spacing; q += 0.02) {
		const a0 = ctx._wave(mid, q), a1 = ctx._wave(mid, q + 0.02);
		wrapJump = Math.max(wrapJump, Math.abs(a1 - a0));
	}
	ok(wrapJump < 0.05,
	   'the crest alpha steps ' + wrapJump.toFixed(3) + ' across the modulus, so the band '
	 + 'is being sliced in half where the phase wraps');

	/*
	 * AND A CREST ARRIVES AND DIES, rather than appearing whole.
	 *
	 * Without the end fades the surf zone has hard walls: a crest
	 * switches on at full strength the moment it crosses waveHi and is
	 * cut off mid-stride at waveLo. That is a spatial edge, not a
	 * temporal one, so none of the per-frame measurements above can see
	 * it -- they all watch a fixed pixel over time, and this is about
	 * two particular pixels at the boundaries.
	 */
	const peakNear = (d) => {
		let m = 0;
		for (let q = 0; q < WAT.spacing; q += 0.05) m = Math.max(m, ctx._wave(d, q));
		return m;
	};
	ok(peakNear(WAT.waveLo + 0.15) < 0.15,
	   'a crest reaches ' + peakNear(WAT.waveLo + 0.15).toFixed(2)
	 + ' alpha right at the beach edge, so it is cut off mid-stride instead of dying there');
	ok(peakNear(WAT.waveHi - 0.15) < 0.15,
	   'a crest reaches ' + peakNear(WAT.waveHi - 0.15).toFixed(2)
	 + ' alpha at the seaward edge, so it switches on whole instead of arriving');
	/* And it does reach full strength in between, or the fades have
	   simply swallowed the wave. */
	ok(peakNear((WAT.waveLo + WAT.waveHi) / 2) > 0.8,
	   'the crest never reaches full strength anywhere in the surf zone');

	/*
	 * THE CREST TRAVELS SHOREWARD, found as the peak of the ramp rather
	 * than the first lit sample -- and measured ABOVE THE FOAM'S REACH,
	 * because the foam is drawn over the waves and a scan from the
	 * beach tracks the breathing edge instead of the crest. That
	 * reported the waves rolling out to sea once already.
	 */
	const floor = Math.max(WAT.waveLo, WAT.foamMid + WAT.foamSwing) + 0.2;
	const crestAt = (t) => {
		let best = -1, bestA = 0.02;
		for (let d = floor; d < WAT.waveHi; d += 0.02) {
			const al = ctx._wave(d, t * WAT.speed);
			if (al > bestA) { bestA = al; best = d; }
		}
		return best;
	};
	const pos = [];
	for (let k = 0; k <= 90; k++) pos.push(crestAt(1 + k * F));
	const steps = [];
	for (let k = 1; k < pos.length; k++) if (pos[k] > 0 && pos[k - 1] > 0) steps.push(pos[k] - pos[k - 1]);
	ok(steps.length > 60, 'only ' + steps.length + ' usable samples of the crest position');
	const sorted = steps.slice().sort((x, y) => x - y);
	ok(sorted[Math.floor(sorted.length / 2)] < 0,
	   'the crest moves outward on the median step, so the waves roll out to sea');
	const inward = steps.filter(v => v < 0).map(v => -v);
	ok(Math.max.apply(null, inward) < 1,
	   'the crest jumps ' + Math.max.apply(null, inward).toFixed(2) + 'px between frames');

	/*
	 * AND IT IS NOT FRANTIC. "The example takes about 6 seconds to
	 * complete a wave and ours is running at 3." Speed can be perfectly
	 * smooth and still wrong, so the journey is timed as well as the
	 * step.
	 */
	const crossing = (WAT.waveHi - WAT.waveLo) / WAT.speed;
	ok(crossing > 3, 'a crest crosses the surf in ' + crossing.toFixed(2) + 's, which reads busy');
	ok(crossing < 12, 'a crest takes ' + crossing.toFixed(2) + 's to cross; nothing seems to move');
	const breath = 2 * Math.PI / WAT.foamHz;
	ok(breath > crossing * 0.8 && breath < crossing * 2,
	   'the foam breathes every ' + breath.toFixed(2) + 's against a ' + crossing.toFixed(2)
	 + 's crossing, so the bloom has drifted off the wave arriving');

	/* NOTHING IS PAINTED ON LAND. The canvas sits OVER the territories
	   and the markers, so one lit pixel at d <= 0 is water on somebody's
	   realm. */
	ok(ctx._foam(0, ctx._edge(0)) === 0 && ctx._wave(0, 0) === 0 && ctx._wave(-4, 2) === 0,
	   'the surf paints at or inside the shoreline, which puts water over the land');
	ok(WAT.waveHi <= 25, 'the surf reaches ' + WAT.waveHi + 'px out, animating the topography');

	/*
	 * AND THE STATIC SHELF STILL REACHES THE RENDERER THROUGH worldLayout,
	 * which is the assertion that would have caught the shipped bug.
	 */
	const joined = ctx._joined(FACTIONS.map(f => ({ name: f.name, count: f.weight })), 1860, 888);
	ok(joined.length > 0, 'worldLayout fed seaLayers nothing at all');
	const bands = joined.filter(L => L.band >= 0);
	const order = [];
	for (const L of bands) if (order[order.length - 1] !== L.band) order.push(L.band);
	ok(order.length === 4 && order.every((v, i) => v === 3 - i),
	   'the shelf bands arrive in the order ' + order.join(',') + ' instead of 3,2,1,0');
	ok(new Set(bands.map(L => L.fill)).size === 4, 'the shelf is not four different shades');
	ok(bands.every(L => !L.cls && !L.stroke), 'a shelf band is animated or stroked');

	/*
	 * AND THE SHORELINE HAS NO RULE ROUND IT.
	 *
	 * "I don't think we need the line around the shore. It doesn't let
	 * the animation breathe and the shore animation itself acts as the
	 * border line." A hard 1.6px stroke round every coast competed with
	 * the foam sitting directly on top of it.
	 */
	const coastL = joined.filter(L => L.band === -1);
	ok(coastL.length > 0 && coastL.every(L => !L.stroke),
	   'the shoreline is still stroked, which crowds the foam that now draws that edge');
	ok(joined[joined.length - 1].band === -1, 'the land is not drawn last, over the shelf');

	/*
	 * THE SWELL, AND THE SEAM THAT WOULD GIVE IT AWAY.
	 *
	 * The open sea undulates by SCROLLING a baked field rather than
	 * simulating one: the two canvas water demos the owner found are
	 * both the classic two-buffer height field, which was built here
	 * and then thrown away, because raindrops and pointer ripples are a
	 * cup of water seen from six inches and this is an ocean seen from
	 * orbit. (It also wanted Math.random(), which this map has banned
	 * since faction colours repainted on every load.)
	 *
	 * Scrolling means the field is read at (x + offset) mod W, so it
	 * MUST be built from whole harmonics of the canvas. If it is not,
	 * the wrap puts a dead straight seam down the open sea every time
	 * the offset passes zero, and open sea is precisely where there is
	 * nothing to hide it behind.
	 */
	const sf = lift('function swellField(');
	ok(sf, 'swellField() could not be lifted out of map.js');
	vm.runInContext(sf + '\nvar _swell = swellField;', ctx);
	const FW = 96, FH = 64, fld = ctx._swell(FW, FH);
	ok(fld.length === FW * FH, 'the swell field is the wrong size');

	/* The step ACROSS the wrap must be an ordinary step, not a cliff. */
	let inner = 0, seamX = 0, seamY = 0;
	for (let y = 0; y < FH; y++) {
		for (let x = 1; x < FW; x++) inner = Math.max(inner, Math.abs(fld[y * FW + x] - fld[y * FW + x - 1]));
		seamX = Math.max(seamX, Math.abs(fld[y * FW] - fld[y * FW + FW - 1]));
	}
	for (let x = 0; x < FW; x++) seamY = Math.max(seamY, Math.abs(fld[x] - fld[(FH - 1) * FW + x]));
	ok(seamX <= inner * 1.5,
	   'the swell jumps ' + seamX.toFixed(3) + ' across the left/right wrap against ' + inner.toFixed(3)
	 + ' between ordinary columns, so a straight seam travels across the open sea');
	ok(seamY <= inner * 1.5,
	   'the swell jumps ' + seamY.toFixed(3) + ' across the top/bottom wrap, same problem sideways');

	/* Bounded, or the gain means nothing and the sea blows out white. */
	let amp = 0;
	for (let i = 0; i < fld.length; i++) amp = Math.max(amp, Math.abs(fld[i]));
	ok(amp > 0.3 && amp <= 1.0001,
	   'the swell peaks at ' + amp.toFixed(3) + ', so the gain is calibrated against nothing');

	/* The same sea every visit, like the coastline. */
	const fld2 = ctx._swell(FW, FH);
	let same = true;
	for (let i = 0; i < fld.length; i++) if (fld[i] !== fld2[i]) { same = false; break; }
	ok(same, 'the swell is not deterministic, so the sea is a different sea on every load');

	/*
	 * THE SEA IS DRAWN BY DISPLACEMENT NOW, NOT BY AN OVERLAY COLOUR
	 * (swellGain and its cap are gone; see map-water-harness.js). The
	 * two numbers that decide whether anything is visible are how far
	 * the water bends what is under it and how hard the slope is lit.
	 * At zero the ocean is a still picture; far too high and the bed
	 * is torn apart rather than rippled.
	 */
	ok(WAT.refract > 0 && WAT.refract < 80,
	   'refract is ' + WAT.refract + ': the sea is either frozen or shredded');
	ok(WAT.lightGain >= 0 && WAT.lightGain < 120,
	   'lightGain is ' + WAT.lightGain + ', which is past shading and into painting');
	ok(WAT.simHz > 0 && WAT.simHz <= 60,
	   'simHz is ' + WAT.simHz + '; above the frame rate it cannot be reached, at zero nothing moves');
	ok(WAT.forceGain > 0 && WAT.forceGain < 0.5,
	   'the forcing is off, or hard enough to be seen arriving, which is the thing it '
	 + 'exists to avoid');
	ok(WAT.damping > 0.9 && WAT.damping < 1,
	   'the shipped damping is ' + WAT.damping + '; at 1 or above a continuously forced '
	 + 'field gains energy without limit');
	ok(WAT.swellFade > 1,
	   'the swell is not faded out of the shallows, so it fights the surf in the same pixels');
	ok(Array.isArray(WAT.swellDrift) && WAT.swellDrift.length >= 2,
	   'there is only one drift, so the swell reads as a single sheet sliding past');

	/* THE CANVAS IS OVER THE MAP AND CANNOT EAT A CLICK. */
	const css = fs.readFileSync(__dirname + '/dist/map.css', 'utf8');
	const surfCss = css.slice(css.indexOf('#container .sea-surf'));
	const decl = surfCss.slice(0, surfCss.indexOf('}'));
	ok(decl.indexOf('pointer-events: none') > 0,
	   'the water canvas does not set pointer-events:none, so it swallows every realm click');
	/*
	 * THE CANVAS IS POSITIONED FROM THE FINAL viewBox, NOT STRETCHED TO
	 * THE CONTAINER.
	 *
	 * The distance field is built in world coordinates, 0..svgW. The
	 * SVG does not show that rectangle 1:1 -- after the drawing is
	 * measured the viewBox grows to take in faction labels that bleed
	 * past the edges, so world (0,0) sits at (-vx, -vy) inside an
	 * element that is fitW wide for vw of world. width:100% ignores all
	 * of it. Measured on the live page: a 1946x1095 world inside a
	 * viewBox of -6 -6 1952 1101, so the surf was 0.3% too wide and 6px
	 * adrift -- right in the middle and out by 6px at the edges.
	 * Reported as the shoreline matching one side of a landmass and
	 * gapping on the others, which is what a stretch looks like: it
	 * cannot be wrong by the same amount everywhere.
	 *
	 * Checked the only way that means anything: map a world point
	 * through the SVG's own viewBox mapping and through the canvas box,
	 * and compare.
	 */
	const sb = lift('function surfBox(');
	ok(sb, 'surfBox() could not be lifted out of map.js');
	vm.runInContext(sb + '\nvar _box = surfBox;', ctx);
	let worstOff = 0;
	for (const c of [
		{ vx: -6, vy: -6, vw: 1952, sw: 1946, sh: 1095, fitW: 1952 },
		{ vx: -74, vy: -20, vw: 2040, sw: 1940, sh: 1146, fitW: 2040 },
		{ vx: -74, vy: -20, vw: 2040, sw: 1940, sh: 1146, fitW: 1020 },
		{ vx: 0, vy: 0, vw: 1200, sw: 1200, sh: 700, fitW: 1200 }
	]) {
		const box = ctx._box(c.vx, c.vy, c.vw, c.fitW, c.sw, c.sh);
		const k = c.fitW / c.vw;
		for (const wx of [0, c.sw * 0.25, c.sw * 0.5, c.sw * 0.77, c.sw]) {
			worstOff = Math.max(worstOff, Math.abs((wx - c.vx) * k - (box.left + (wx / c.sw) * box.width)));
		}
		for (const wy of [0, c.sh * 0.5, c.sh]) {
			worstOff = Math.max(worstOff, Math.abs((wy - c.vy) * k - (box.top + (wy / c.sh) * box.height)));
		}
	}
	ok(worstOff < 0.001,
	   'the water canvas lands up to ' + worstOff.toFixed(2) + 'px from where the SVG puts '
	 + 'the same world point, so the surf sits off its own coastline');
	ok(!/width:\s*100%/.test(decl),
	   'the stylesheet still stretches the water canvas to the container');
	/* Anchored on the CALL, not the signature -- searching for
	   'surfBox(vx, vy, vw, fitW, svgW, svgH)' matches the function's own
	   declaration and passes with the call deleted. */
	/*
	 * AND THE MARKERS SIT ABOVE THE WATER.
	 *
	 * A canvas cannot be interleaved into an SVG's paint order, so the
	 * surf covers the whole map. That was fine while it only painted
	 * water and wrong the moment a marker or a faction label sat on a
	 * coast: "the tide animation covers both avatars and faction
	 * labels, making them hard to see." The furniture is MOVED into a
	 * second SVG stacked over the canvas -- moved, not rebuilt, so the
	 * click handlers and filter references travel with it.
	 */
	ok(src.indexOf('const overlayFrom = svg.childNodes.length;') > 0,
	   'map.js no longer marks where the terrain stops and the furniture starts');
	ok(src.indexOf('while (svg.childNodes.length > overlayFrom) over.appendChild(') > 0,
	   'the markers and labels are not moved into the overlay, so the tide washes over them');
	const overAt = src.indexOf("class: 'map-over'"), waterAt = src.indexOf('waterLayer(container, world, svgW, svgH, surfBox(');
	ok(overAt > 0 && waterAt > 0 && overAt > waterAt,
	   'the overlay is built before the water canvas, so it ends up underneath it');
	/* Same viewBox as the terrain, or the furniture lands somewhere else. */
	ok(/class: 'map-over'[\s\S]{0,40}\}\)/.test(src)
	   && src.slice(overAt - 220, overAt).indexOf('viewBox: `${vx} ${vy} ${vw} ${vh}`') > 0,
	   'the overlay does not carry the terrain viewBox, so the markers shift off their realms');
	const overCss = css.slice(css.indexOf('#container .map-over'));
	ok(/pointer-events:\s*none/.test(overCss.slice(0, overCss.indexOf('}'))),
	   'the overlay takes pointer events across the whole map, which swallows every click '
	 + 'that is not exactly on a marker');
	ok(/\.map-over > \*\s*\{\s*pointer-events:\s*auto/.test(css),
	   'the overlay contents cannot be clicked, so no realm marker responds any more');

	ok(src.indexOf('waterLayer(container, world, svgW, svgH, surfBox(') > 0,
	   'waterLayer is not given the box computed from the final viewBox, so the canvas '
	 + 'falls back to covering the container and the surf drifts off the coast');
	ok(src.indexOf('prefers-reduced-motion') > 0 && src.indexOf('cancelAnimationFrame') > 0,
	   'map.js never checks reduced motion, or never cancels its loop when the map redraws');
}

console.log('\nthe land is one shape, and it is textured');
{
	/*
	 * LAKES WERE BEING PAINTED AS LAND.
	 *
	 * Every coast ring used to be emitted as its own layer and drawn as
	 * its own <path>, and SVG fills a closed subpath whatever direction
	 * it is wound -- so the lake rings, which the geometry winds the
	 * other way precisely so they read as holes, came out filled with
	 * the land colour. Two lakes of 3,786 and 2,053px2 painted over as
	 * dry ground on a 16-faction world. The distance field rasterises
	 * the coast as a single path, so the WATER knew about the lakes the
	 * whole time while the land did not.
	 */
	const fs = require('fs');
	const vm = require('vm');
	const src = fs.readFileSync(__dirname + '/map.js', 'utf8');
	const lift = (sig) => {
		const i = src.indexOf(sig);
		const j = i < 0 ? -1 : src.indexOf('\n}\n', i);
		return (i >= 0 && j > i) ? src.slice(i, j + 3) : '';
	};
	const texConsts = src.slice(src.indexOf('const LAND_TEX = {'),
		src.indexOf('};', src.indexOf('const LAND_TEX = {')) + 2);

	/* A tiny stub DOM: paintLandTexture is run, not read. */
	const made = [];
	const node = (tag) => {
		const n = { tag: tag, attrs: {}, kids: [] };
		n.appendChild = (c) => { n.kids.push(c); return c; };
		return n;
	};
	const ctx = {
		mwWorld: W.mwWorld, mwRng: W.mwRng, Math: Math, console: { warn: () => {} },
		svgEl: (tag, attrs) => { const n = node(tag); n.attrs = attrs || {}; made.push(n); return n; },
		/* toPath lives in map-kit.js, not map.js; same shape. */
		toPath: (pts) => pts.map((q, i) => (i ? 'L' : 'M') + q.x.toFixed(1) + ',' + q.y.toFixed(1)).join(' ') + 'Z'
	};
	vm.createContext(ctx);
	vm.runInContext(texConsts + '\n' + lift('function fleckField(') + lift('function toPathAll(')
		+ lift('function paintLandTexture(') + lift('function seaLayers(') + lift('function worldLayout(')
		+ '\nvar _tex = paintLandTexture, _field = fleckField, _T = LAND_TEX;'
		+ '\nvar toPathAll_ = toPathAll;'
		+ '\nvar _joined = (f, w, h) => seaLayers(worldLayout(f, w, h));', ctx);

	/* The 16-faction 1200x700 world is the one with two real lakes. */
	const lakey = LIVE_NAMES.map((name, i) => ({ name: name + ' 1', count: 1 + ((i * 7 + 1) % 6) }));
	const layers = ctx._joined(lakey, 1200, 700);
	const land = layers.filter(L => L.band === -1);
	ok(land.length === 1,
	   'the coast came back as ' + land.length + ' layers; every one becomes its own <path> '
	 + 'and a lake drawn on its own is filled in as land');
	ok(land[0].rings && land[0].rings.length >= 3,
	   'the single land layer is not carrying all the coast rings, so the holes are lost');
	const sgn = (r) => {
		let a = 0;
		for (let i = 0; i < r.length; i++) {
			const q = r[i], t = r[(i + 1) % r.length];
			a += q.x * t.y - t.x * q.y;
		}
		return a;
	};
	ok(land[0].rings.some(r => sgn(r) < 0),
	   'no opposite-wound ring reached the renderer, so there is nothing to cut the lakes out');

	/* AND ALL OF THEM REACH THE PATH DATA. Carrying the rings in the
	   layer is no use if the path builder only emits the first: one
	   subpath, one filled blob, lakes gone again and the islands with
	   them. One moveto per ring. */
	const dAll = ctx.toPathAll_(land[0].rings);
	ok((dAll.match(/M/g) || []).length === land[0].rings.length,
	   'the land path has ' + (dAll.match(/M/g) || []).length + ' subpaths for '
	 + land[0].rings.length + ' rings, so rings are being dropped on the way to the screen');

	/*
	 * THE TEXTURE, after SpriteCook's tileset generator (MIT): seeded
	 * flecks, and an edge fade reaching in from the silhouette. Its
	 * code draws tiles and ours draws one continent, so the idea
	 * carries over and none of the code does.
	 */
	const svg = node('svg'), defs = node('defs');
	const drawn = ctx._tex(svg, defs, { coast: land[0].rings, seed: 12345 }, 'M0,0Z', 1860, 888);
	ok(drawn === ctx._T.rims.length + ctx._T.layers.length,
	   'paintLandTexture drew ' + drawn + ' layers');

	const rims = svg.kids.filter(k => k.attrs.stroke);
	ok(rims.length === ctx._T.rims.length, 'the shore rims were not drawn');
	ok(rims.every(r => r.attrs.fill === 'none' && r.attrs['clip-path']),
	   'a shore rim is filled, or is not clipped to the land -- an unclipped stroke puts '
	 + 'half its width out in the water, which is the hard shoreline rule all over again');
	ok(defs.kids.some(k => k.tag === 'clipPath'), 'no clipPath was added for the shore rims');

	/*
	 * NO TILE. THIS IS THE ONE THE OWNER CAUGHT.
	 *
	 * The flecks were two <pattern> tiles at coprime sizes, 137 and 89,
	 * on the theory that they only realign every 12,193px so the repeat
	 * would never show. The reasoning was wrong: the COMBINATION
	 * repeats rarely, but each layer still repeats every 137px on its
	 * own, and the eye locks onto one layer's recurring clump long
	 * before it notices the two agreeing. "I can identify the pattern
	 * across a landmass and it's less believable when the eye catches
	 * that."
	 */
	ok(!defs.kids.some(k => k.tag === 'pattern'),
	   'the flecks are still a tiled <pattern>, which repeats across the landmass');
	const fleckPaths = svg.kids.filter(k => !k.attrs.stroke && /^M/.test(k.attrs.d || ''));
	ok(fleckPaths.length === ctx._T.layers.length,
	   'expected one path per fleck layer, got ' + fleckPaths.length);
	ok(fleckPaths.every(f => f.attrs['clip-path']),
	   'a fleck layer is not clipped to the land, so specks land in the sea');

	for (let n = 0; n < ctx._T.layers.length; n++) {
		const f = ctx._field(12345 + n * 7919, 1860, 888, ctx._T.layers[n]);
		ok(f.length > 400, 'fleck layer ' + n + ' produced only ' + f.length + ' specks');
		ok(f.every(q => q.x >= 0 && q.y >= 0 && q.x + q.s <= 1860 && q.y + q.s <= 888),
		   'a speck in layer ' + n + ' falls outside the sheet');
		const xs = f.map(q => q.x), ys = f.map(q => q.y);
		ok(Math.max.apply(null, xs) - Math.min.apply(null, xs) > 1860 * 0.9
		   && Math.max.apply(null, ys) - Math.min.apply(null, ys) > 888 * 0.9,
		   'fleck layer ' + n + ' does not cover the sheet, so part of the land has no texture');

		/*
		 * MEASURED AS A TRANSLATIONAL SELF-MATCH, which is what "I can
		 * see the pattern" means as a number: slide the scatter
		 * sideways by every plausible period and count the specks that
		 * land on another speck. A tiled pattern scores ~100% at its
		 * tile width; this scores under 1%.
		 */
		const set = new Set(f.map(q => Math.round(q.x) + ',' + Math.round(q.y)));
		let best = 0, bestP = 0;
		for (let per = 40; per <= 420; per++) {
			let hit = 0;
			for (const q of f) if (set.has(Math.round(q.x + per) + ',' + Math.round(q.y))) hit++;
			if (hit > best) { best = hit; bestP = per; }
		}
		ok(best / f.length < 0.05,
		   'layer ' + n + ': ' + (100 * best / f.length).toFixed(0) + '% of specks have a twin '
		 + bestP + 'px to the right, so the texture repeats and the eye will find it');

		/*
		 * AND IT CLUMPS. Uniform random is as recognisable as a grid --
		 * it reads as television static. For a uniform scatter of
		 * density d the mean nearest-neighbour distance is 0.5/sqrt(d);
		 * patchy ground comes in well under that.
		 */
		const sample = f.filter((_, i) => i % 7 === 0).slice(0, 300);
		let sum = 0;
		for (const q of sample) {
			let nn = Infinity;
			for (const r of f) {
				if (r === q) continue;
				const dd = (r.x - q.x) * (r.x - q.x) + (r.y - q.y) * (r.y - q.y);
				if (dd < nn) nn = dd;
			}
			sum += Math.sqrt(nn);
		}
		const meanNN = sum / sample.length;
		const uniformNN = 0.5 / Math.sqrt(f.length / (1860 * 888));
		ok(meanNN < uniformNN * 0.75,
		   'layer ' + n + ' averages ' + meanNN.toFixed(1) + 'px to its nearest speck against '
		 + uniformNN.toFixed(1) + 'px for a uniform scatter, so the ground is static, not patchy');
	}

	/* THE SAME GROUND EVERY VISIT, same as the coastline. */
	const sp0 = ctx._T.layers[0];
	const g1 = JSON.stringify(ctx._field(999, 800, 600, sp0));
	const g2 = JSON.stringify(ctx._field(999, 800, 600, sp0));
	const g3 = JSON.stringify(ctx._field(1000, 800, 600, sp0));
	ok(g1 === g2, 'the flecks are not deterministic, so the ground shifts on every load');
	ok(g1 !== g3, 'the flecks ignore the seed, so every world has identical ground');
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
