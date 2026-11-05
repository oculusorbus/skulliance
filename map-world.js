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

/*
 * PONDS FILLED IN -- the mirror of flooding specks.
 *
 * mwFloodSpecks sinks land too small to be an island. Nothing sank WATER
 * too small to be a lake, and a two-cell pond is worse than a two-cell
 * island: mwTrace returns it as an interior ring of the territory around
 * it, the renderer fills that ring like any other piece, and it lands on
 * the map as an 8x17px dot in the faction's colour. Four of those were
 * the smallest "pieces" on the live-shaped worlds, and they are exactly
 * the specks the owner circled.
 *
 * Sea that touches the frame is the ocean, however thin the channel --
 * only enclosed water is a candidate, and only below the same floor an
 * island has to clear. A lake bigger than the smallest island is a
 * feature and is left alone.
 */
function mwFillPonds(mask, gw, gh, minCells) {
	const seen = new Uint8Array(gw * gh);
	const stack = [];
	let filled = 0;
	for (let start = 0; start < mask.length; start++) {
		if (mask[start] || seen[start]) continue;
		const cells = [];
		let open = false;
		stack.length = 0; stack.push(start); seen[start] = 1;
		while (stack.length) {
			const k = stack.pop();
			cells.push(k);
			const i = k % gw, j = (k / gw) | 0;
			if (i === 0 || j === 0 || i === gw - 1 || j === gh - 1) open = true;
			if (i > 0      && !mask[k - 1]  && !seen[k - 1])  { seen[k - 1]  = 1; stack.push(k - 1); }
			if (i < gw - 1 && !mask[k + 1]  && !seen[k + 1])  { seen[k + 1]  = 1; stack.push(k + 1); }
			if (j > 0      && !mask[k - gw] && !seen[k - gw]) { seen[k - gw] = 1; stack.push(k - gw); }
			if (j < gh - 1 && !mask[k + gw] && !seen[k + gw]) { seen[k + gw] = 1; stack.push(k + gw); }
		}
		if (open || cells.length >= minCells) continue;
		for (const k of cells) mask[k] = 1;
		filled += cells.length;
	}
	return filled;
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
	/*
	 * A VERTEX CAN HAVE TWO EDGES LEAVING IT, so this is a map to a LIST.
	 *
	 * Where land pinches -- two cells meeting only at a corner, which a
	 * noise field produces constantly -- the boundary passes through that
	 * corner twice. Keyed by vertex alone, the second edge overwrote the
	 * first and was silently dropped, so the ring that came out was not
	 * the outline of anything: measured at 4,422px2 of a 16-faction world
	 * traced wrong, which no amount of careful smoothing downstream can
	 * put back.
	 */
	const edges = new Map();
	const push = (x, y, nx, ny) => {
		const k = key(x, y), e = { x: x, y: y, nx: nx, ny: ny };
		const at = edges.get(k);
		if (at) at.push(e); else edges.set(k, [e]);
	};
	const has = (i, j) => i >= 0 && j >= 0 && i < gw && j < gh && owner[j * gw + i] === id;

	for (let j = 0; j < gh; j++) {
		for (let i = 0; i < gw; i++) {
			if (owner[j * gw + i] !== id) continue;
			const x0 = ox + i * cs, y0 = oy + j * cs, x1 = x0 + cs, y1 = y0 + cs;
			/* Clockwise in screen coordinates (y down), so the ring comes
			   out consistently wound whichever cells are present. */
			if (!has(i, j - 1)) push(x0, y0, x1, y0);
			if (!has(i + 1, j)) push(x1, y0, x1, y1);
			if (!has(i, j + 1)) push(x1, y1, x0, y1);
			if (!has(i - 1, j)) push(x0, y1, x0, y0);
		}
	}
	if (!edges.size) return [];

	/*
	 * AT A PINCH, TAKE THE SHARPEST RIGHT TURN.
	 *
	 * Arriving at a corner where two edges leave, one continues around
	 * this blob and the other jumps across to the blob touching it
	 * diagonally. The rest of the file treats cells as 4-connected (the
	 * flood fills, the fragment absorber), so the tracer has to agree:
	 * turning right keeps the land on the right-hand side and closes this
	 * blob as its own ring, which is what 4-connectivity means. Turning
	 * left would staple two diagonally-touching blobs into one ring.
	 */
	const take = (k, dx, dy) => {
		const arr = edges.get(k);
		if (!arr || !arr.length) return null;
		let bi = 0;
		if (arr.length > 1 && (dx || dy)) {
			let best = -1;
			for (let i = 0; i < arr.length; i++) {
				const ex = Math.sign(arr[i].nx - arr[i].x), ey = Math.sign(arr[i].ny - arr[i].y);
				const cross = dx * ey - dy * ex;
				const score = cross > 0 ? 2 : (dx * ex + dy * ey > 0 ? 1 : 0);
				if (score > best) { best = score; bi = i; }
			}
		}
		const e = arr[bi];
		arr.splice(bi, 1);
		if (!arr.length) edges.delete(k);
		return e;
	};

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
	let left = 0;
	for (const arr of edges.values()) left += arr.length;
	while (left > 0) {
		const ring = [];
		let cur = take(edges.keys().next().value, 0, 0);
		if (cur) left--;
		/* Bounded: every step consumes an edge, and there are finitely
		   many. A malformed grid cannot spin this forever. */
		let guard = left + 2;
		while (cur && guard-- > 0) {
			ring.push({ x: cur.x, y: cur.y });
			const nxt = take(key(cur.nx, cur.ny),
				Math.sign(cur.nx - cur.x), Math.sign(cur.ny - cur.y));
			if (nxt) left--;
			cur = nxt;
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

/* ---------------------------------------------------------------------- *
 * SMOOTH THE GRID BEFORE TRACING IT.
 *
 * A nearest-seed assignment over a noise field produces two kinds of mess
 * that only show up with real data, and the live map had both: single
 * cells of one faction stranded inside another, and ownership one cell
 * wide running for a long way. Traced and smoothed, those come out as
 * dots and as wandering worms -- "all this messiness and garbled borders
 * disrupts the cleanliness and readability of the map".
 *
 * A MAJORITY FILTER FIXES BOTH AT ONCE and is the oldest trick in raster
 * processing: every cell takes whatever most of its neighbours are. A
 * stranded cell is outvoted; a one-cell isthmus has more outside
 * neighbours than inside ones and closes. Two passes is enough -- three
 * starts rounding off real headlands.
 *
 * SEA IS NOT A CANDIDATE when filtering OWNERSHIP, and land is not
 * reassigned: the two grids are filtered separately so that smoothing who
 * owns a coast can never move the coast.
 * ---------------------------------------------------------------------- */
function mwMajority(grid, gw, gh, passes, empty) {
	const n = passes === undefined ? 2 : passes;
	for (let pass = 0; pass < n; pass++) {
		const next = grid.slice();
		for (let j = 0; j < gh; j++) {
			for (let i = 0; i < gw; i++) {
				const k = j * gw + i;
				if (grid[k] === empty) continue;          // sea stays sea
				const tally = new Map();
				let bestV = grid[k], bestN = 0;
				for (let dj = -1; dj <= 1; dj++) {
					for (let di = -1; di <= 1; di++) {
						const x = i + di, y = j + dj;
						if (x < 0 || y < 0 || x >= gw || y >= gh) continue;
						const v = grid[y * gw + x];
						if (v === empty) continue;
						/* Its own value counts once, like any neighbour, so a
						   cell in a genuine thin strip with support on both
						   sides survives and a stranded one does not. */
						const c = (tally.get(v) || 0) + 1;
						tally.set(v, c);
						if (c > bestN || (c === bestN && v === grid[k])) { bestN = c; bestV = v; }
					}
				}
				next[k] = bestV;
			}
		}
		grid.set(next);
	}
	return grid;
}

/* ---------------------------------------------------------------------- *
 * ABSORB THE SCRAPS THAT ARE STILL ATTACHED.
 *
 * The majority filter removes stranded cells and thin necks; what it
 * cannot remove is a genuinely connected lobe of twenty cells hanging off
 * a neighbour's coast, and at map scale that draws as a dot with a label
 * nowhere near it. Any piece under the floor that TOUCHES another faction
 * is handed to whichever neighbour it shares the most edge with.
 *
 * AN OFFSHORE ISLAND IS NOT A SCRAP, and this is the whole reason the
 * test is "touches another faction" rather than "is small". An island has
 * no land neighbour to be absorbed into, and it is the most characterful
 * thing the terrain produces -- deleting it to tidy up would throw away
 * the feature to fix the symptom.
 * ---------------------------------------------------------------------- */
function mwAbsorbFragments(owner, gw, gh, minCells) {
	const seen = new Uint8Array(gw * gh);
	const stack = [];
	let moved = 0;

	/*
	 * A FACTION'S LARGEST PIECE IS NEVER A SCRAP, however small it is.
	 * Without this a faction whose whole territory is under the floor is
	 * absorbed out of existence -- which happened the moment the harness
	 * tried one realm beside four hundred: the tiny faction vanished from
	 * the map and worldLayout() then reverted the entire thing to the old
	 * packing. Small is not the same as spare.
	 */
	const biggest = new Map();
	{
		const mark = new Uint8Array(gw * gh);
		for (let st = 0; st < owner.length; st++) {
			if (owner[st] < 0 || mark[st]) continue;
			const id = owner[st];
			let n = 0;
			stack.length = 0; stack.push(st); mark[st] = 1;
			while (stack.length) {
				const k = stack.pop(); n++;
				const i = k % gw, j = (k / gw) | 0;
				const go = (x, y) => {
					if (x < 0 || y < 0 || x >= gw || y >= gh) return;
					const m = y * gw + x;
					if (!mark[m] && owner[m] === id) { mark[m] = 1; stack.push(m); }
				};
				go(i - 1, j); go(i + 1, j); go(i, j - 1); go(i, j + 1);
			}
			if (n > (biggest.get(id) || 0)) biggest.set(id, n);
		}
	}

	for (let start = 0; start < owner.length; start++) {
		if (owner[start] < 0 || seen[start]) continue;
		const id = owner[start];
		const cells = [];
		stack.length = 0; stack.push(start); seen[start] = 1;
		while (stack.length) {
			const k = stack.pop();
			cells.push(k);
			const i = k % gw, j = (k / gw) | 0;
			const step = (x, y) => {
				if (x < 0 || y < 0 || x >= gw || y >= gh) return;
				const n = y * gw + x;
				if (!seen[n] && owner[n] === id) { seen[n] = 1; stack.push(n); }
			};
			step(i - 1, j); step(i + 1, j); step(i, j - 1); step(i, j + 1);
		}
		if (cells.length >= minCells) continue;
		if (cells.length === biggest.get(id)) continue;   // its body, not a scrap

		/* Who does it touch, and along how much edge? */
		const touch = new Map();
		for (const k of cells) {
			const i = k % gw, j = (k / gw) | 0;
			const look = (x, y) => {
				if (x < 0 || y < 0 || x >= gw || y >= gh) return;
				const v = owner[y * gw + x];
				if (v < 0 || v === id) return;
				touch.set(v, (touch.get(v) || 0) + 1);
			};
			look(i - 1, j); look(i + 1, j); look(i, j - 1); look(i, j + 1);
		}
		if (!touch.size) continue;             // an island: left alone
		let to = id, best = 0;
		for (const [v, n] of touch) if (n > best) { best = n; to = v; }
		for (const k of cells) owner[k] = to;
		moved += cells.length;
	}
	return moved;
}


/** Chaikin for an OPEN chain: the ends are kept exactly where they are, so
 *  a border still meets the coast it started from. */
function mwSmoothOpen(pts, n) {
	let p = pts;
	for (let k = 0; k < (n === undefined ? 3 : n); k++) {
		if (p.length < 3) return p;
		const out = [p[0]];
		for (let i = 0; i < p.length - 1; i++) {
			const a = p[i], b = p[i + 1];
			out.push({ x: a.x * 0.75 + b.x * 0.25, y: a.y * 0.75 + b.y * 0.25 });
			out.push({ x: a.x * 0.25 + b.x * 0.75, y: a.y * 0.25 + b.y * 0.75 });
		}
		out.push(p[p.length - 1]);
		p = out;
	}
	return p;
}

/** Collinear collapse for an open chain. Endpoints always survive. */
function mwDedupeOpen(pts) {
	if (pts.length < 3) return pts;
	const out = [pts[0]];
	for (let i = 1; i < pts.length - 1; i++) {
		const a = pts[i - 1], b = pts[i], c = pts[i + 1];
		const cross = (b.x - a.x) * (c.y - a.y) - (b.y - a.y) * (c.x - a.x);
		if (Math.abs(cross) > 1e-9) out.push(b);
	}
	out.push(pts[pts.length - 1]);
	return out;
}

/** Chaikin corner cutting. Local copy for the same reason as the RNG. */
/**
 * HOW FAR EVERY CELL IS FROM LAND, in cells.
 *
 * A two-pass chamfer transform: sweep once from the top-left taking the
 * best of the neighbours already visited, once back from the bottom-
 * right, with 3 for a step and 4 for a diagonal so the result tracks
 * true distance instead of counting city blocks. Land is 0.
 *
 * It is what the shore bands are cut from. Contouring this is why the
 * bands hug every inlet and island at a constant width rather than
 * being outlines scaled up, which pulls away from the coast on a
 * straight run and bunches up inside a bay.
 */
function mwDistance(land, gw, gh) {
	const d = new Float32Array(gw * gh);
	for (let k = 0; k < land.length; k++) d[k] = land[k] ? 0 : 1e9;
	for (let j = 0; j < gh; j++) {
		for (let i = 0; i < gw; i++) {
			const k = j * gw + i;
			if (d[k] === 0) continue;
			let v = d[k];
			if (i > 0)                v = Math.min(v, d[k - 1] + 3);
			if (j > 0)                v = Math.min(v, d[k - gw] + 3);
			if (i > 0 && j > 0)       v = Math.min(v, d[k - gw - 1] + 4);
			if (i < gw - 1 && j > 0)  v = Math.min(v, d[k - gw + 1] + 4);
			d[k] = v;
		}
	}
	for (let j = gh - 1; j >= 0; j--) {
		for (let i = gw - 1; i >= 0; i--) {
			const k = j * gw + i;
			if (d[k] === 0) continue;
			let v = d[k];
			if (i < gw - 1)                  v = Math.min(v, d[k + 1] + 3);
			if (j < gh - 1)                  v = Math.min(v, d[k + gw] + 3);
			if (i < gw - 1 && j < gh - 1)    v = Math.min(v, d[k + gw + 1] + 4);
			if (i > 0 && j < gh - 1)         v = Math.min(v, d[k + gw - 1] + 4);
			d[k] = v;
		}
	}
	for (let k = 0; k < d.length; k++) d[k] /= 3;
	return d;
}

/**
 * Drop points closer together than minGap, keeping the shape.
 *
 * Chaikin quadruples the points every two passes, and on a smooth
 * contour most of them land within a pixel of each other: invisible on
 * screen, but every one is about fifteen characters of path data in the
 * document. The sea came to 910KB of `d` attributes, which is a parse
 * cost on a phone long before it is a paint cost.
 *
 * ONLY THE SEA USES THIS. The coastline and the territory outlines are
 * SHARED geometry -- a territory's shoreline is a slice of the coast
 * ring and a border is one curve used by both neighbours -- and thinning
 * them independently is precisely how the notches came back last time.
 * The shore bands belong to nobody and butt against nothing.
 */
function mwThin(pts, minGap) {
	if (pts.length < 8) return pts;
	const g2 = minGap * minGap;
	const out = [pts[0]];
	for (let i = 1; i < pts.length; i++) {
		const a = out[out.length - 1], b = pts[i];
		const dx = b.x - a.x, dy = b.y - a.y;
		if (dx * dx + dy * dy >= g2) out.push(b);
	}
	/* A closed ring whose last kept point crowds the first just closes. */
	if (out.length > 2) {
		const a = out[out.length - 1], b = out[0];
		if ((a.x - b.x) * (a.x - b.x) + (a.y - b.y) * (a.y - b.y) < g2) out.pop();
	}
	return out.length >= 4 ? out : pts;
}

/**
 * The shallows: nested contours of the distance field, biggest last.
 * Separate from mwWorld so the harness can measure a band's distance
 * from the land on a mask it built itself.
 */
function mwShores(land, gw, gh, cs, ox, oy, seed, smoothN, opts) {
	opts = opts || {};
	/*
	 * SHORE BANDS, OFF A DISTANCE FIELD.
	 *
	 * The idea is Kimo's (spritecook.ai, "2D Water Shorelines with
	 * Distance Fields"): instead of shading water by depth you do not
	 * have, shade it by HOW FAR IT IS FROM LAND, and band that. Every
	 * inlet gets shallows, every island gets a shelf, and the bands keep
	 * a constant width round a headland -- none of which an outline
	 * scaled up can do, because that pulls away from the coast on a
	 * straight run and bunches up inside a bay.
	 *
	 * HIS DISTANCES, CARRIED OVER BY PROPORTION, NOT BY NUMBER. He bands
	 * a 360px-wide map at 5 / 13 / 26px with anything past 32 reading as
	 * open sea. Ours is 220 cells wide, so the same fractions of the
	 * sheet are 3.1 / 7.9 / 15.9 cells, and they hold at any canvas
	 * because the grid is always 220 across. Copying 5 / 13 / 26 as
	 * literal pixels would have put all three inside the first cell.
	 *
	 * AND THE WOBBLE IS THE WHOLE TRICK. Contouring the raw field gives
	 * bands that trace the coastline exactly, which reads as a sticker
	 * cut round the land -- the one thing that would have looked worse
	 * than no bands at all. He adds +/-3px of smooth noise to the
	 * distance before banding; the same proportion here is +/-1.8 cells,
	 * two octaves, seeded off the world so the shallows are the same
	 * shallows on every visit.
	 */
	const px = gw / 360;
	/*
	 * AND THEN PULLED IN TO 65%, BY EYE, FOR A REASON HIS MAP DOES NOT
	 * HAVE: ours is an archipelago and the channels between the masses
	 * are narrower than his open sea. At the full distances the outer
	 * band meets itself in midwater and the whole sheet goes pale, which
	 * throws away the deep/shallow contrast the technique exists to
	 * create. Swept 100 / 65 / 40%: 100 floods the channels, 40 is a thin
	 * rim with no layering, 65 keeps both the shelf and the open water.
	 */
	const SHORE = 0.65;
	const bandAt = opts.shoreBands === undefined
		? [1.1, 5, 13, 26].map(v => v * px * SHORE) : opts.shoreBands;
	/* THE WOBBLE IS NOT SCALED DOWN WITH THEM. Narrowing the bands is
	   about our channels; the wobble is about not tracing the coast, and
	   that argument does not get weaker when the shelf gets thinner. At
	   0.65x it was visible but timid. His proportion is the one that
	   opens real pockets of deep water in the shelf. */
	const wobAmp = (opts.shoreWobble === undefined ? 3 : opts.shoreWobble) * px;
	const dist = mwDistance(land, gw, gh);
	if (wobAmp > 0) {
		const w1 = mwNoise(seed ^ 0x2f9a1c3d), w2 = mwNoise(seed ^ 0x7c31b5e9);
		for (let j = 0; j < gh; j++) {
			for (let i = 0; i < gw; i++) {
				const k = j * gw + i;
				if (dist[k] === 0) continue;          /* land stays land */
				const n = (w1(i / 7, j / 7) - 0.5) * 1.34 + (w2(i / 3.1, j / 3.1) - 0.5) * 0.66;
				dist[k] = Math.max(0.0001, dist[k] + n * wobAmp);
			}
		}
	}
	const contour = (t, sm, minPts, thin) => {
		const m = new Int16Array(gw * gh);
		for (let k = 0; k < dist.length; k++) m[k] = dist[k] <= t ? 0 : -1;
		let rings = mwTrace(m, 0, gw, gh, cs, ox, oy);
		if (minPts) rings = rings.filter(r => r.length >= minPts);
		/* A filled shelf band shows its edge against the band under it, so
		   it is thinned gently. A wave is a 1.1px stroke in open water and
		   tolerates a much coarser chord before anyone could tell. */
		const gap = cs * (thin === undefined ? 0.25 : thin);
		return { d: t, rings: rings.map(r =>
			mwThin(mwSmooth(mwDedupe(r), sm === undefined ? smoothN : sm), gap)) };
	};

	/*
	 * NO CONTOURS FOR THE MOVING WATER. There were two sets here -- a
	 * breathing foam ring and eight wave rings cross-faded in sequence --
	 * and they were deleted because the approach cannot work, not because
	 * they were tuned wrong.
	 *
	 * His shader is `mod(d + TIME * speed, spacing) < 1.6`, evaluated PER
	 * PIXEL. The edges are hard; the smoothness comes entirely from d
	 * being continuous, so the ring slides by a fraction of a pixel each
	 * frame. A fixed contour can only ever put that ring in one place, so
	 * N contours give it N places and cross-fading between them is
	 * opacity, not movement: the owner saw "flashing lights in
	 * succession" twice, and more rings and softer keyframes only ever
	 * made it a smoother flash.
	 *
	 * The surf is a canvas now (waterLayer in map.js), evaluating the
	 * shader per pixel over a distance field. These bands stay as
	 * geometry because they are STATIC topography -- the graduated
	 * shallows -- and SVG draws those perfectly well.
	 */
	return { bands: bandAt.map(t => contour(t)) };
}

/** A traced vertex, keyed the way mwTrace keys them, so the two agree. */
function mwVKey(p) { return Math.round(p.x) + ',' + Math.round(p.y); }

/**
 * WHO IS ON THE OTHER SIDE OF EACH EDGE OF A TRACED RING.
 *
 * A ring is a loop of unit cell edges. Each one has this territory on the
 * inside and something else on the outside: another faction, or the sea
 * (-1, which is also what falls off the grid). That "something else" is
 * what decides whether a stretch of outline is coast or border, and where
 * one stretch ends and the next begins.
 *
 * Returns one label per EDGE: sides[i] is what lies across ring[i]->ring[i+1].
 */
function mwRingSides(ring, owner, id, gw, gh, cs, ox, oy) {
	const n = ring.length;
	const sides = new Array(n);
	const at = (i, j) => (i < 0 || j < 0 || i >= gw || j >= gh) ? -1 : owner[j * gw + i];
	for (let e = 0; e < n; e++) {
		const a = ring[e], b = ring[(e + 1) % n];
		const gx = Math.round((Math.min(a.x, b.x) - ox) / cs);
		const gy = Math.round((Math.min(a.y, b.y) - oy) / cs);
		let o0, o1;
		if (Math.abs(a.y - b.y) < 1e-6) { o0 = at(gx, gy - 1); o1 = at(gx, gy); }
		else                            { o0 = at(gx - 1, gy); o1 = at(gx, gy); }
		sides[e] = (o0 === id) ? o1 : o0;
	}
	return sides;
}

/**
 * ONE SEAM, SMOOTHED ONCE, SHARED BY EVERYTHING THAT TOUCHES IT.
 *
 * REPORTED AS "triangular dips where territories meet each other, because
 * of rounded edges leading up to the boundary instead of a cohesive
 * shoreline that is split by the dividing line."
 *
 * The cause was three independent smoothings of geometry that is supposed
 * to be the SAME geometry. Every region ring was rounded as its own closed
 * loop and the coastline was rounded as another, so at a triple point --
 * where two territories and the sea meet -- Chaikin cut the corner off
 * each territory separately, pulling both fills back from the junction and
 * leaving a notch of bare land between them. Measured: 4.16% of the land
 * was covered by no territory fill at all.
 *
 * So the outline is cut at its JUNCTIONS first -- the vertices where the
 * thing on the other side changes -- and each stretch between two
 * junctions is smoothed as an OPEN polyline, which keeps its endpoints.
 * Two neighbours walk their shared stretch in opposite directions over the
 * same vertices and Chaikin is symmetric, so they get the identical curve
 * and the fills meet exactly. The coastline is cut at the same junctions,
 * so a shoreline arc is the same curve as the region arc beneath it: one
 * cohesive shoreline, split by the dividing line, which is what was asked
 * for.
 *
 * `pinned` carries junctions found on OTHER rings. A triple point is a
 * corner for the two territories but not for the coast, whose label does
 * not change there -- without it the coastline would round straight
 * through the junction the territories had stopped at.
 *
 * Returns the closed fill outline, and the stretches themselves so the
 * faction-to-faction ones can be drawn as borders without being rebuilt
 * from scratch and landing somewhere fractionally different.
 */
function mwSeams(ring, sides, pinned, smooth, coast) {
	const n = ring.length;
	const cut = [];
	for (let i = 0; i < n; i++) {
		if (sides[(i - 1 + n) % n] !== sides[i] || (pinned && pinned.has(mwVKey(ring[i])))) cut.push(i);
	}
	/* A LONE CUT IS NOT A SEAM. One pin and no side change means the
	   whole outline is a single stretch; slicing it at one point would
	   collapse the piece to nothing. */
	if (cut.length === 1) cut.length = 0;
	if (!cut.length) {
		/* No junction anywhere on this ring: an island whose whole outline
		   is coast, or an enclave wholly inside one neighbour. Rounded as
		   a closed loop, which is right -- there is no seam to line up
		   with and nothing to pin. */
		const whole = mwSmooth(mwDedupe(ring), smooth);
		return {
			poly: whole,
			runs: sides[0] >= 0 ? [{ side: sides[0], pts: whole.concat([whole[0]]) }] : []
		};
	}
	const poly = [], runs = [];
	for (let c = 0; c < cut.length; c++) {
		const a = cut[c], b = cut[(c + 1) % cut.length];
		const h0 = coast && coast.anchor(ring[a]), h1 = coast && coast.anchor(ring[b]);
		const raw = [ring[a]];
		let i = a;
		do { i = (i + 1) % n; raw.push(ring[i]); } while (i !== b);

		/*
		 * A SHORELINE STRETCH IS CUT FROM THE COASTLINE ITSELF rather than
		 * smoothed again from the grid. The coastline is already drawn,
		 * and a second smoothing of the same cells lands in a slightly
		 * different place -- which is the notch.
		 *
		 * ONLY WHERE BOTH ENDS COME ASHORE ON THE SAME COAST RING, so a
		 * stretch is never spliced out of some other island's outline.
		 * The lake case -- a self-touching outline that runs out to an
		 * inland lake, round it and back -- is handled in slice() itself,
		 * where the two ends land on the same point.
		 */
		/* The ring test is a PRECONDITION of slice(), not a tuning knob:
		   given two ends on different rings it would hunt one ring for
		   the other's index and return a whole garbage loop. Measured
		   over sixty worlds it never once fires, which is the reason to
		   state it rather than the reason to drop it. */
		const sliceable = sides[a] === -1 && h0 && h1 && h0.ring === h1.ring;
		let arc;
		if (sliceable) {
			arc = coast.slice(h0.ring, h0.idx, h1.idx, raw.length > 2);
		} else {
			/* Where this stretch starts or ends ON the coast, it starts or
			   ends at the coastline's own point, so the border meets the
			   shore exactly where the shore was split. */
			if (h0) raw[0] = h0.pt;
			if (h1) raw[raw.length - 1] = h1.pt;
			arc = mwSmoothOpen(mwDedupeOpen(raw), smooth);
		}
		runs.push({ side: sides[a], pts: arc });
		/* The last point of each arc is the first of the next. */
		for (let k = 0; k < arc.length - 1; k++) poly.push(arc[k]);
	}
	return { poly: poly, runs: runs };
}

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
	/* Coast first: a one-cell peninsula traced and smoothed is a worm,
	   and no amount of tidying the ownership on top of it helps. */
	mwMajority(land, gw, gh, opts.coastSmooth === undefined ? 2 : opts.coastSmooth, 255);
	const minIsland = Math.max(6,
		Math.round(gw * gh * (opts.minIsland === undefined ? 0.0025 : opts.minIsland)));
	mwFillPonds(land, gw, gh, minIsland);
	const masses = mwFloodSpecks(land, gw, gh, minIsland);
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
	for (const m of masses) for (const k of m) landIdx.push(k);
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
	/*
	 * THE WEIGHT RATIO IS CAPPED, AND THAT IS A DESIGN CALL.
	 *
	 * Distance divides by sqrt(weight), so four hundred realms against one
	 * is a twenty-fold radius and the small faction is left with a few
	 * cells -- or none, at which point it is off the map entirely. A map
	 * where one faction is ninety-nine percent of the land is not a map,
	 * and the point of the board is that everybody is ON it.
	 *
	 * Capped at sixteen, so the biggest territory is at most about sixteen
	 * times the smallest in AREA and four times in reach. Across the
	 * realistic spread -- one to eighteen realms -- nothing is clamped at
	 * all, so this only ever bites on the pathological case.
	 */
	const minW = seeds.reduce((a, s) => Math.min(a, s.weight), Infinity);
	for (const s of seeds) s.weight = Math.min(s.weight, minW * 16);

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

	/* And the ownership, once the seeds have settled. Done AFTER the last
	   assignment rather than inside the loop: the relaxation needs the raw
	   nearest-seed answer to move its seeds honestly. */
	/*
	 * NO MAJORITY FILTER ON THE OWNER GRID. There was one here. Measured
	 * over sixty worlds (3-16 factions, four canvas sizes) it shortened
	 * the drawn borders by 0.7% and removed 2 pieces out of 735 -- a pass
	 * over 23k cells for nothing a reader could see, and no threshold a
	 * harness could assert honestly. Weighted-nearest assignment is
	 * already compact, so the filter had almost nothing to flip. It stays
	 * on the LAND mask above, where it does real work: there it is the
	 * difference between a coast and a row of one-cell worms.
	 */
	/* Then the attached scraps. A floor of 0.35% of the grid is about the
	   size below which a piece cannot carry anything a reader can use. */
	mwAbsorbFragments(owner, gw, gh,
		Math.max(8, Math.round(gw * gh * (opts.minPiece === undefined ? 0.0035 : opts.minPiece))));

	const smoothN = opts.smooth === undefined ? 3 : opts.smooth;

	/*
	 * TRACED FIRST, DRESSED SECOND, because a junction found on one
	 * region's outline has to be honoured on everybody else's -- including
	 * the coastline, which cannot see it on its own.
	 */
	const traced = [];
	const pinned = new Set();
	for (let s = 0; s < seeds.length; s++) {
		const rings = mwTrace(owner, s, gw, gh, cs, ox, oy);
		if (!rings.length) continue;
		const sided = rings.map(r => mwRingSides(r, owner, s, gw, gh, cs, ox, oy));
		for (let r = 0; r < rings.length; r++) {
			const ring = rings[r], sides = sided[r], n = ring.length;
			for (let i = 0; i < n; i++) {
				if (sides[(i - 1 + n) % n] !== sides[i]) pinned.add(mwVKey(ring[i]));
			}
		}
		traced.push({ s: s, rings: rings, sided: sided });
	}
	if (!traced.length) return null;

	/*
	 * THE COASTLINE IS BUILT FIRST, AND IT KNOWS NOTHING ABOUT OWNERSHIP.
	 *
	 * It is traced off the land mask and rounded as a closed loop exactly
	 * as it always was, so gaining a realm still cannot redraw it -- the
	 * property the whole feature rests on, and the one the first attempt
	 * at this fix broke by cutting the coastline at the junctions too.
	 * The territories bend to the coastline instead: each one takes its
	 * shoreline as a SLICE of these very points.
	 */
	const landMask = new Int16Array(gw * gh);
	for (let k = 0; k < land.length; k++) landMask[k] = land[k] ? 0 : -1;
	const coastRaw = mwTrace(landMask, 0, gw, gh, cs, ox, oy);
	const coastRings = coastRaw.map(r => mwSmooth(mwDedupe(r), smoothN));

	const shoreSet = mwShores(land, gw, gh, cs, ox, oy, seed, smoothN, opts);

	/* Which coast ring a given grid vertex sits on, so a junction is
	   matched against its own shoreline and never a lake's. */
	const coastRingOf = new Map();
	for (let r = 0; r < coastRaw.length; r++) {
		for (const pt of coastRaw[r]) coastRingOf.set(mwVKey(pt), r);
	}

	/*
	 * WHERE A JUNCTION LANDS ON THE SMOOTHED COASTLINE.
	 *
	 * The junction is a grid corner; the coastline has been rounded away
	 * from it by up to a pixel or so. Snapping to the nearest point ON the
	 * coastline is what keeps the arithmetic exact: both neighbours snap
	 * to the SAME point, so their shoreline slices butt together with no
	 * gap, and the border between them starts from that same point.
	 */
	const anchorCache = new Map();
	const coastHelper = {
		anchor: (pt) => {
			const key = mwVKey(pt);
			if (anchorCache.has(key)) return anchorCache.get(key);
			const r = coastRingOf.get(key);
			let found = null;
			if (r !== undefined) {
				const cr = coastRings[r];
				let best = Infinity, bi = 0;
				for (let i = 0; i < cr.length; i++) {
					const dx = cr[i].x - pt.x, dy = cr[i].y - pt.y;
					const d = dx * dx + dy * dy;
					if (d < best) { best = d; bi = i; }
				}
				found = { ring: r, idx: bi, pt: cr[bi] };
			}
			anchorCache.set(key, found);
			return found;
		},
		/*
		 * A STRETCH THAT STARTS AND ENDS AT THE SAME POINT IS THE WHOLE
		 * RING, NOT AN EMPTY ONE.
		 *
		 * A territory holding a lake has a self-touching outline: it
		 * reaches the lake at one pinched corner, goes all the way round
		 * it, and comes back to that same corner. Both ends of that
		 * stretch anchor to the same point on the lake's shoreline, and
		 * walking "from i to i" with a while loop stops immediately and
		 * returns a single point -- which deleted the lake and painted
		 * 3,786px2 of open water in a faction's colour. The do-while
		 * walks the loop. `full` is what separates that from the other
		 * way two ends can coincide: two different junctions landing on
		 * the same coastline point, where an empty stretch is right.
		 */
		slice: (r, i0, i1, full) => {
			const cr = coastRings[r], out = [cr[i0]];
			if (i0 === i1 && !full) return out;
			let i = i0, guard = cr.length + 1;
			do { i = (i + 1) % cr.length; out.push(cr[i]); } while (i !== i1 && guard-- > 0);
			return out;
		}
	};

	const regions = [];
	for (const t of traced) {
		let cells = 0;
		for (let k = 0; k < owner.length; k++) if (owner[k] === t.s) cells++;
		const polys = [], borders = [];
		for (let r = 0; r < t.rings.length; r++) {
			const d = mwSeams(t.rings[r], t.sided[r], pinned, smoothN, coastHelper);
			polys.push(d.poly);
			/* Only the faction-to-faction stretches get a drawn border.
			   The rest of this outline is coast and is already stroked as
			   the shoreline; stroking it again is the doubled line this
			   replaced. */
			for (const run of d.runs) if (run.side >= 0) borders.push(run.pts);
		}
		regions.push({
			name: seeds[t.s].name,
			borders: borders,
			/* poly is the BODY -- markers, the label and the bounding box
			   all want one shape. polys is every piece, including the
			   faction's offshore islands, and is what gets drawn. */
			poly: polys[0],
			polys: polys,
			cells: cells,
			centre: { x: seeds[t.s].x, y: seeds[t.s].y }
		});
	}
	if (!regions.length) return null;

	return { coast: coastRings, shores: shoreSet.bands, regions: regions, width: w, height: h,
	         masses: masses.length };
}

/* Browser globals, and CommonJS for the harness. */
if (typeof module !== 'undefined' && module.exports) {
	module.exports = { mwHash, mwRng, mwNoise, mwField, mwSeaLevel, mwFloodSpecks,
	                   mwFillPonds, mwDistance, mwShores, mwInside, mwTrace, mwRingSides, mwSeams, mwMajority, mwAbsorbFragments,
	                   mwDedupe, mwDedupeOpen, mwThin,
	                   mwSmooth, mwSmoothOpen, mwWorld };
}
