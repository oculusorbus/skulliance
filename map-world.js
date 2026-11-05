/* map-world.js — the realm map's geography, as maths rather than as art.
 *
 * WHAT THIS REPLACES. Faction territories were a treemap: a rectangle per
 * faction, sized by realm count, packed into rows. jitterRect() and
 * chaikin() then roughed each rectangle into something hand-drawn, which
 * is why the map does not look like a spreadsheet -- but no amount of edge
 * jitter hides a grid of boxes, and the owner read the macro structure
 * correctly the first time he looked at it: "a collage of squares per
 * faction".
 *
 * WHAT MAKES A MAP FEEL LIKE A WORLD IS CARTOGRAPHY, NOT DIMENSIONALITY.
 * Three.js was the obvious suggestion and is the wrong tool: tilt the same
 * boxes into 3D and you have the same collage with perspective on it. What
 * a place has is a coastline, territories that interlock instead of
 * tiling, and the same shape every time you look at it. All three are
 * maths, which is the half of this that code is actually good at.
 *
 * SO: a continent outline, and territories grown from seeds inside it.
 *
 *   1. A coastline, from summed sine waves around a centre. Not noise for
 *      its own sake -- three harmonics give headlands and bays at
 *      different scales, which is what a coast is.
 *   2. One seed per faction, placed inside the land.
 *   3. Every land cell goes to its nearest seed, WEIGHTED by how many
 *      realms that faction has, so a big faction holds more ground.
 *   4. Lloyd relaxation: move each seed to the centre of what it holds,
 *      and do it again. Three passes turns scattered slivers into compact
 *      countries, and it is the single step that makes the result read as
 *      a political map rather than as a Voronoi diagram.
 *   5. Trace each region's outline off the grid and smooth it.
 *
 * DETERMINISTIC, AND THAT IS THE POINT. Everything is seeded from the
 * faction names, so the world is the SAME WORLD on every visit. A place
 * that rearranges itself when you reload is not a place. For the same
 * reason the seed does not include realm counts: a faction gaining a realm
 * must not redraw the continent.
 *
 * NO DOM, NO SVG, NO GLOBALS BEYOND THE EXPORTS. This file is pure
 * geometry so it can be run headlessly -- map-world-harness.js does
 * exactly that, and a map you can only check by looking at it is a map
 * nobody checks.
 */

/* ---------------------------------------------------------------------- *
 * Seeded randomness. Duplicated from map-kit.js rather than required,
 * because this file has to run under node with no browser globals and
 * map-kit.js touches document at load time (_measureSvg).
 * ---------------------------------------------------------------------- */
function mwHash(s) {
	let h = 2166136261;
	s = String(s);
	for (let i = 0; i < s.length; i++) {
		h ^= s.charCodeAt(i);
		h = Math.imul(h, 16777619);
	}
	return h >>> 0;
}

function mwRng(seed) {
	let a = seed >>> 0;
	return function () {
		a |= 0; a = (a + 0x6D2B79F5) | 0;
		let t = Math.imul(a ^ (a >>> 15), 1 | a);
		t = (t + Math.imul(t ^ (t >>> 7), 61 | t)) ^ t;
		return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
	};
}

/* ---------------------------------------------------------------------- *
 * THE TERRAIN.
 *
 * THIS REPLACED A RADIAL COASTLINE, and the owner's read of that was
 * right: summed harmonics around a centre can only ever produce one
 * blob, and "the map now feels like an island" is exactly what it was.
 * A map is a RECTANGLE with geography in it -- seas, inlets, offshore
 * islands, land running off the edge of the sheet -- and none of that
 * comes out of a single closed ring.
 *
 * So the land is a FIELD rather than an outline. Layered value noise over
 * the whole frame, thresholded into land and water. Everything the owner
 * asked for falls out of that for free: an archipelago where the field
 * dips, an inland sea where it dips in the middle of a mass, a peninsula
 * where a ridge runs to the edge. Nothing is placed; it is all one
 * surface read at one height.
 *
 * THE SEA LEVEL IS SEARCHED, NOT PICKED. A fixed threshold against noise
 * gives a different amount of land for every seed -- one platform gets a
 * drowned world and another gets a continent with a pond. Bisecting for a
 * target land fraction means every world has a usable balance of land and
 * water whatever the seed, which is the difference between generation
 * that works and generation you have to re-roll.
 * ---------------------------------------------------------------------- */

/** Hash-lattice value noise. Deterministic, no tables, no allocation. */
function mwNoise(seed) {
	const h = (x, y) => {
		let n = (Math.imul(x | 0, 374761393) ^ Math.imul(y | 0, 668265263) ^ seed) >>> 0;
		n = Math.imul(n ^ (n >>> 13), 1274126177) >>> 0;
		return ((n ^ (n >>> 16)) >>> 0) / 4294967296;
	};
	const smooth = (t) => t * t * (3 - 2 * t);
	return (x, y) => {
		const xi = Math.floor(x), yi = Math.floor(y);
		const u = smooth(x - xi), v = smooth(y - yi);
		const a = h(xi, yi), b = h(xi + 1, yi), c = h(xi, yi + 1), d = h(xi + 1, yi + 1);
		return (a * (1 - u) + b * u) * (1 - v) + (c * (1 - u) + d * u) * v;
	};
}

/**
 * The elevation field, as a Float32Array over the grid.
 *
 * FOUR OCTAVES. One is a smear with no coastline worth looking at; eight
 * is static, and every island it makes is a single cell. Four at halving
 * amplitude gives masses, bays and a scatter of islands at three readable
 * scales, which is the same reasoning the old three-harmonic coast used
 * and the only part of it worth keeping.
 *
 * THE EDGE FALLOFF IS DELIBERATELY WEAK. A strong one is how you force an
 * island, which is the thing being fixed here. This only discourages land
 * in the last few percent of the frame, so a mass can still run off the
 * sheet the way it does on a real map, while the corners stay water and
 * the frame reads as a border rather than as a crop.
 */
function mwField(gw, gh, seed, opts) {
	opts = opts || {};
	const n1 = mwNoise(seed), n2 = mwNoise(seed ^ 0x51ed270b);
	/*
	 * MEASURED, THEN CHOSEN BY EYE -- in that order.
	 *
	 * Swept fourteen worlds across five settings. 1.2 gives one continent
	 * with a pond; 4.8 averaged 3.6 masses but ranged 1-8, so one world in
	 * several was still a single blob. The owner picked the archipelago out
	 * of four previews, so the floor matters more than the average: 6.2
	 * averages 4.9 and has never produced fewer than three.
	 */
	const base = opts.scale || 6.2;
	const f = new Float32Array(gw * gh);
	let lo = Infinity, hi = -Infinity;

	for (let j = 0; j < gh; j++) {
		for (let i = 0; i < gw; i++) {
			const u = i / gw, v = j / gh;
			let e = 0, amp = 1, frq = base, norm = 0;
			for (let o = 0; o < 4; o++) {
				/* Two offset fields summed per octave, which breaks up the
				   square lattice -- one alone leaves visible grid artefacts
				   along the 45s. */
				e += amp * (n1(u * frq, v * frq) * 0.65 + n2(v * frq + 7.3, u * frq + 1.7) * 0.35);
				norm += amp;
				amp *= 0.5; frq *= 2.07;
			}
			e /= norm;

			/* Weak frame falloff: nothing for the middle 86%, easing to a
			   real push only in the outermost band. */
			const edge = Math.min(Math.min(u, 1 - u) / 0.07, Math.min(v, 1 - v) / 0.07);
			if (edge < 1) e -= (1 - Math.max(0, edge)) * 0.26;

			f[j * gw + i] = e;
			if (e < lo) lo = e;
			if (e > hi) hi = e;
		}
	}
	/* Normalised so the bisection below works in a known range whatever
	   the seed produced. */
	const span = Math.max(1e-6, hi - lo);
	for (let k = 0; k < f.length; k++) f[k] = (f[k] - lo) / span;
	return f;
}

/**
 * Sea level for a target land fraction, by bisection.
 *
 * Returns the threshold, not the mask -- the caller wants both the mask
 * and the ability to ask "how deep is this" for anything drawn later.
 */
function mwSeaLevel(field, target) {
	let lo = 0, hi = 1;
	for (let it = 0; it < 24; it++) {
		const mid = (lo + hi) / 2;
		let land = 0;
		for (let k = 0; k < field.length; k++) if (field[k] > mid) land++;
		if (land / field.length > target) lo = mid; else hi = mid;
	}
	return (lo + hi) / 2;
}

/**
 * Drop the specks.
 *
 * A noise field produces single-cell islands by the dozen, and at map
 * scale they are dirt rather than geography. Anything under a floor of
 * the frame is flooded; everything bigger is kept, because a genuine
 * offshore island is the most characterful thing on the map.
 *
 * Also returns the surviving masses, largest first, so a caller can seed
 * into real land rather than onto a rock.
 */
function mwFloodSpecks(mask, gw, gh, minCells) {
	const seen = new Int32Array(gw * gh).fill(-1);
	const masses = [];
	const stack = [];
	for (let start = 0; start < mask.length; start++) {
		if (!mask[start] || seen[start] >= 0) continue;
		const id = masses.length;
		const cells = [];
		stack.length = 0; stack.push(start); seen[start] = id;
		while (stack.length) {
			const k = stack.pop();
			cells.push(k);
			const i = k % gw, j = (k / gw) | 0;
			if (i > 0      && mask[k - 1]  && seen[k - 1]  < 0) { seen[k - 1]  = id; stack.push(k - 1); }
			if (i < gw - 1 && mask[k + 1]  && seen[k + 1]  < 0) { seen[k + 1]  = id; stack.push(k + 1); }
			if (j > 0      && mask[k - gw] && seen[k - gw] < 0) { seen[k - gw] = id; stack.push(k - gw); }
			if (j < gh - 1 && mask[k + gw] && seen[k + gw] < 0) { seen[k + gw] = id; stack.push(k + gw); }
		}
		masses.push(cells);
	}
	const kept = [];
	for (const cells of masses) {
		if (cells.length < minCells) { for (const k of cells) mask[k] = 0; continue; }
		kept.push(cells);
	}
	kept.sort((a, b) => b.length - a.length);
	return kept;
}

/** Winding-number test. Works for the concave rings a coast produces,
 *  where a crossing-count test on a self-touching ring does not. */
function mwInside(px, py, poly) {
	let wn = 0;
	for (let i = 0, j = poly.length - 1; i < poly.length; j = i++) {
		const a = poly[j], b = poly[i];
		if (a.y <= py) {
			if (b.y > py && ((b.x - a.x) * (py - a.y) - (px - a.x) * (b.y - a.y)) > 0) wn++;
		} else if (b.y <= py && ((b.x - a.x) * (py - a.y) - (px - a.x) * (b.y - a.y)) < 0) wn--;
	}
	return wn !== 0;
}

/* ---------------------------------------------------------------------- *
 * TRACE A REGION OFF THE GRID.
 *
 * Every cell in the region contributes the sides that face OUT of it, each
 * emitted in the same rotational direction, and the segments are then
 * chained end to end. That gives a closed ring with consistent winding and
 * needs no marching-squares table.
 *
 * THE LONGEST RING WINS. A region can be disconnected -- an island of one
 * cell cut off by a neighbour's growth -- and drawing only the main body
 * is better than drawing a territory with confetti around it.
 * ---------------------------------------------------------------------- */
function mwTrace(owner, id, gw, gh, cs, ox, oy) {
	const key = (x, y) => Math.round(x) + ',' + Math.round(y);
	const edges = new Map();
	const has = (i, j) => i >= 0 && j >= 0 && i < gw && j < gh && owner[j * gw + i] === id;

	for (let j = 0; j < gh; j++) {
		for (let i = 0; i < gw; i++) {
			if (owner[j * gw + i] !== id) continue;
			const x0 = ox + i * cs, y0 = oy + j * cs, x1 = x0 + cs, y1 = y0 + cs;
			/* Clockwise in screen coordinates (y down), so the ring comes
			   out consistently wound whichever cells are present. */
			if (!has(i, j - 1)) edges.set(key(x0, y0), { x: x0, y: y0, nx: x1, ny: y0 });
			if (!has(i + 1, j)) edges.set(key(x1, y0), { x: x1, y: y0, nx: x1, ny: y1 });
			if (!has(i, j + 1)) edges.set(key(x1, y1), { x: x1, y: y1, nx: x0, ny: y1 });
			if (!has(i - 1, j)) edges.set(key(x0, y1), { x: x0, y: y1, nx: x0, ny: y0 });
		}
	}
	if (!edges.size) return [];

	/*
	 * EVERY RING, LARGEST FIRST -- not just the biggest one.
	 *
	 * It used to return only the longest, which was right when a region
	 * was one blob on one island. With real terrain a faction's territory
	 * legitimately includes offshore islands, and dropping them silently
	 * deleted the most characterful thing on the map. Callers that only
	 * want a body take [0]; callers drawing the land take all of them.
	 */
	const rings = [];
	while (edges.size) {
		const first = edges.values().next().value;
		const ring = [];
		let cur = first;
		/* Bounded: every step consumes an edge, and there are finitely
		   many. A malformed grid cannot spin this forever. */
		let guard = edges.size + 1;
		while (cur && guard-- > 0) {
			ring.push({ x: cur.x, y: cur.y });
			edges.delete(key(cur.x, cur.y));
			cur = edges.get(key(cur.nx, cur.ny));
		}
		if (ring.length >= 4) rings.push(ring);
	}
	rings.sort((a, b) => b.length - a.length);
	return rings;
}

/*
 * COLLAPSE THE STAIRCASE BEFORE SMOOTHING IT.
 *
 * A traced ring is all axis-aligned unit steps, so a diagonal border is
 * dozens of two-point stairs. Chaikin rounds each step individually and
 * the result is a visibly serrated edge -- which is what the first render
 * showed on every internal border while the coast, being analytic, was
 * smooth. Dropping the mid-points of straight runs first leaves real
 * corners for Chaikin to cut, and cuts the point count by about half.
 */
function mwDedupe(pts) {
	if (pts.length < 3) return pts;
	const out = [];
	for (let i = 0; i < pts.length; i++) {
		const a = pts[(i - 1 + pts.length) % pts.length], b = pts[i], c = pts[(i + 1) % pts.length];
		const cross = (b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x);
		if (Math.abs(cross) > 1e-9) out.push(b);
	}
	return out.length >= 3 ? out : pts;
}

/** Chaikin corner cutting. Local copy for the same reason as the RNG. */
function mwSmooth(pts, n) {
	let p = pts;
	for (let k = 0; k < (n === undefined ? 2 : n); k++) {
		if (p.length < 3) return p;
		const out = [];
		for (let i = 0; i < p.length; i++) {
			const a = p[i], b = p[(i + 1) % p.length];
			out.push({ x: a.x * 0.75 + b.x * 0.25, y: a.y * 0.75 + b.y * 0.25 });
			out.push({ x: a.x * 0.25 + b.x * 0.75, y: a.y * 0.25 + b.y * 0.75 });
		}
		p = out;
	}
	return p;
}

/* ---------------------------------------------------------------------- *
 * THE WORLD.
 *
 * factions: [{ name, weight }]  weight is the realm count, minimum 1.
 * Returns { coast, regions: [{ name, poly, cells, centre }], grid }
 * or null when there is nothing to draw -- the caller keeps its old
 * layout in that case rather than rendering an empty continent.
 * ---------------------------------------------------------------------- */
function mwWorld(factions, w, h, opts) {
	opts = opts || {};
	if (!factions || !factions.length || !(w > 0) || !(h > 0)) return null;

	/* SEEDED ON THE NAMES ONLY, sorted, so the continent is the same shape
	   whatever order the query returned and whatever anybody has done this
	   week. Realm counts deliberately excluded: gaining a realm must not
	   redraw the coast. */
	const names = factions.map(f => String(f.name)).sort();
	const seed = mwHash(names.join('|'));

	/* A coarse grid. Fine enough that a border reads as a line rather than
	   as stairs once smoothed, coarse enough to stay cheap on a phone:
	   160 x 110 is ~18k cells and one point-in-polygon test each. */
	const gw = opts.gw || 220;
	const gh = opts.gh || Math.max(40, Math.round(gw * (h / w)));
	const cs = w / gw;
	const ox = 0, oy = 0;

	const cellH = h / gh;

	/* ---- the geography ---- */
	const field = mwField(gw, gh, seed, opts);
	/* 0.42 from the same sweep. Below 0.38 the map is mostly water and
	   territories have nowhere to breathe; 0.62 is one continent. */
	const sea = mwSeaLevel(field, opts.landFraction === undefined ? 0.42 : opts.landFraction);
	const land = new Uint8Array(gw * gh);
	for (let k = 0; k < field.length; k++) land[k] = field[k] > sea ? 1 : 0;

	/* Specks flooded, masses returned largest first. A single-cell island
	   is dirt; a real one is the best thing on the map. */
	const masses = mwFloodSpecks(land, gw, gh,
		Math.max(6, Math.round(gw * gh * (opts.minIsland === undefined ? 0.0025 : opts.minIsland))));
	if (!masses.length) return null;

	/*
	 * SEEDS GO ON REAL LAND, SPREAD ACROSS THE MASSES.
	 *
	 * The old version placed them on a ring and walked them toward the
	 * centre until they hit land, which only works when there IS a centre
	 * -- with an archipelago the middle of the frame is often sea. Each
	 * faction instead takes the cell furthest from every seed placed so
	 * far, out of the land that exists. That is farthest-point seeding: it
	 * spreads them across the masses by construction, puts the first few
	 * on the biggest islands, and cannot land one in the water.
	 */
	const rng = mwRng(seed ^ 0x9e3779b9);
	const landIdx = [];
	for (let k = 0; k < land.length; k++) if (land[k]) landIdx.push(k);
	const cellXY = (k) => ({ x: ox + ((k % gw) + 0.5) * cs, y: oy + (((k / gw) | 0) + 0.5) * cellH });

	const seeds = [];
	for (const nm of names) {
		let pick = -1, best = -1;
		if (!seeds.length) {
			/* The first seed is the centre of the largest mass, so the
			   biggest faction is not handed an outlying rock. */
			const big = masses[0];
			let mx = 0, my = 0;
			for (const k of big) { const p = cellXY(k); mx += p.x; my += p.y; }
			mx /= big.length; my /= big.length;
			let bd = Infinity;
			for (const k of big) {
				const p = cellXY(k);
				const d = (p.x - mx) * (p.x - mx) + (p.y - my) * (p.y - my);
				if (d < bd) { bd = d; pick = k; }
			}
		} else {
			for (const k of landIdx) {
				const p = cellXY(k);
				let near = Infinity;
				for (const sd of seeds) {
					const dx = p.x - sd.x, dy = p.y - sd.y;
					const d = dx * dx + dy * dy;
					if (d < near) near = d;
				}
				if (near > best) { best = near; pick = k; }
			}
		}
		const p = cellXY(pick);
		seeds.push({
			name: nm, x: p.x, y: p.y,
			weight: Math.max(1, Number((factions.find(f => String(f.name) === nm) || {}).weight) || 1)
		});
	}

	/*
	 * WEIGHTED NEAREST, THEN LLOYD.
	 *
	 * Distance is divided by the square root of the weight, so a faction
	 * with four times the realms claims about twice the radius and so
	 * about four times the area. Dividing by the weight itself made one
	 * large faction swallow the continent.
	 */
	const owner = new Int16Array(gw * gh).fill(-1);
	const passes = opts.relax === undefined ? 3 : opts.relax;

	for (let pass = 0; pass <= passes; pass++) {
		for (let j = 0; j < gh; j++) {
			for (let i = 0; i < gw; i++) {
				const idx = j * gw + i;
				if (!land[idx]) { owner[idx] = -1; continue; }
				const x = ox + (i + 0.5) * cs, y = oy + (j + 0.5) * cellH;
				let bi = 0, bd = Infinity;
				for (let s = 0; s < seeds.length; s++) {
					const dx = x - seeds[s].x, dy = y - seeds[s].y;
					const d = Math.sqrt(dx * dx + dy * dy) / Math.sqrt(seeds[s].weight);
					if (d < bd) { bd = d; bi = s; }
				}
				owner[idx] = bi;
			}
		}
		if (pass === passes) break;
		/* Move each seed to the centre of what it holds. A seed that holds
		   nothing stays put rather than jumping to NaN. */
		const sx = new Float64Array(seeds.length), sy = new Float64Array(seeds.length);
		const sn = new Int32Array(seeds.length);
		for (let j = 0; j < gh; j++) {
			for (let i = 0; i < gw; i++) {
				const o = owner[j * gw + i];
				if (o < 0) continue;
				sx[o] += ox + (i + 0.5) * cs; sy[o] += oy + (j + 0.5) * cellH; sn[o]++;
			}
		}
		for (let s = 0; s < seeds.length; s++) {
			if (sn[s]) {
				seeds[s].x = sx[s] / sn[s];
				seeds[s].y = sy[s] / sn[s];
				continue;
			}
			/*
			 * A STARVED SEED IS RESCUED, NOT LEFT OUT.
			 *
			 * Weighted nearest can squeeze a small faction out entirely --
			 * seven of eight survived on every realistic desktop canvas,
			 * which tripped the caller's "did everyone get land" guard and
			 * silently reverted the whole map to the old packing. The
			 * failure was invisible: a correct-looking fallback.
			 *
			 * BELT AND BRACES WITH THE on-land SEED CLAMP ABOVE, and
			 * deliberately: either alone fixes the eight-faction case, and
			 * map-world-harness.js therefore catches neither on its own --
			 * only removing BOTH reproduces the failure. Do not delete one
			 * because a mutation says nothing breaks.
			 *
			 * The rescue drops the seed on the land cell FURTHEST from its
			 * current owner, which is the cell that owner holds most
			 * loosely, and only takes from a region big enough to spare it.
			 * The remaining passes then settle the new border properly.
			 */
			let takeIdx = -1, takeD = -1;
			for (let j = 0; j < gh; j++) {
				for (let i = 0; i < gw; i++) {
					const idx = j * gw + i, o = owner[idx];
					if (o < 0 || sn[o] < 8) continue;
					const x = ox + (i + 0.5) * cs, y = oy + (j + 0.5) * cellH;
					const dx = x - seeds[o].x, dy = y - seeds[o].y;
					const d = dx * dx + dy * dy;
					if (d > takeD) { takeD = d; takeIdx = idx; }
				}
			}
			if (takeIdx >= 0) {
				seeds[s].x = ox + ((takeIdx % gw) + 0.5) * cs;
				seeds[s].y = oy + (Math.floor(takeIdx / gw) + 0.5) * cellH;
			}
		}
	}

	const dress = (ring) => mwSmooth(mwDedupe(ring), opts.smooth === undefined ? 3 : opts.smooth);

	const regions = [];
	for (let s = 0; s < seeds.length; s++) {
		const rings = mwTrace(owner, s, gw, gh, cs, ox, oy);
		if (!rings.length) continue;
		let cells = 0;
		for (let k = 0; k < owner.length; k++) if (owner[k] === s) cells++;
		const polys = rings.map(dress);
		regions.push({
			name: seeds[s].name,
			/* poly is the BODY -- markers, the label and the bounding box
			   all want one shape. polys is every piece, including the
			   faction's offshore islands, and is what gets drawn. */
			poly: polys[0],
			polys: polys,
			cells: cells,
			centre: { x: seeds[s].x, y: seeds[s].y }
		});
	}
	if (!regions.length) return null;

	/* The land outline: every mass and every lake shore, for the coast
	   stroke. Traced off the land mask with the same tracer. */
	const landMask = new Int16Array(gw * gh);
	for (let k = 0; k < land.length; k++) landMask[k] = land[k] ? 0 : -1;
	const coastRings = mwTrace(landMask, 0, gw, gh, cs, ox, oy).map(dress);

	return { coast: coastRings, regions: regions, width: w, height: h,
	         masses: masses.length };
}

/* Browser globals, and CommonJS for the harness. */
if (typeof module !== 'undefined' && module.exports) {
	module.exports = { mwHash, mwRng, mwNoise, mwField, mwSeaLevel, mwFloodSpecks,
	                   mwInside, mwTrace, mwDedupe, mwSmooth, mwWorld };
}
