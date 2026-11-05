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
 * THE COASTLINE.
 *
 * Three harmonics, not one and not ten. One gives an egg; ten gives a
 * ragged blob with no readable shape. Three at falling amplitude gives a
 * continent with a couple of large bays, a few headlands, and detail at
 * the scale a reader notices -- which is how a coast is drawn by hand too.
 *
 * Returned as a closed ring of points, already in map coordinates.
 * ---------------------------------------------------------------------- */
function mwCoast(w, h, seed, steps) {
	const rng = mwRng(seed);
	const n = steps || 220;
	/* Inset so the coast never touches the canvas edge: a continent that
	   runs off the side reads as a crop of a bigger map, and the whole
	   point of having a coast is that the map has edges of its own. */
	const cx = w / 2, cy = h / 2;
	const rx = w * 0.46, ry = h * 0.46;

	const a1 = rng() * Math.PI * 2, a2 = rng() * Math.PI * 2, a3 = rng() * Math.PI * 2;
	/* Harmonic COUNTS are randomised within a band as well as their phase,
	   so two different platforms do not get the same silhouette with the
	   bays in different places. */
	const k1 = 2 + Math.floor(rng() * 2);      // 2-3  : the overall lobes
	const k2 = 5 + Math.floor(rng() * 3);      // 5-7  : bays and headlands
	const k3 = 11 + Math.floor(rng() * 5);     // 11-15: coastal detail

	const pts = [];
	for (let i = 0; i < n; i++) {
		const t = (i / n) * Math.PI * 2;
		const f = 1
			+ 0.155 * Math.sin(k1 * t + a1)
			+ 0.085 * Math.sin(k2 * t + a2)
			+ 0.035 * Math.sin(k3 * t + a3);
		pts.push({ x: cx + Math.cos(t) * rx * f, y: cy + Math.sin(t) * ry * f });
	}

	/*
	 * FITTED AFTER THE FACT, NOT PREDICTED.
	 *
	 * The three harmonics can sum to 1.275 of the base radius, so a radius
	 * of 0.46 overflows the canvas -- which it did: the coast ran to x1489
	 * of 1400 and every territory on that side came out with a RULER-
	 * STRAIGHT edge, because the grid stops at the canvas and the trace
	 * followed the clip. The single most un-worldly thing on the map, and
	 * it was a sign error away from invisible.
	 *
	 * Working out the worst case by hand means redoing it every time an
	 * amplitude changes. Measuring the ring and fitting it cannot drift.
	 */
	let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
	for (const p of pts) {
		if (p.x < x0) x0 = p.x; if (p.x > x1) x1 = p.x;
		if (p.y < y0) y0 = p.y; if (p.y > y1) y1 = p.y;
	}
	const m = 0.035;                                   // margin, each side
	const sx = (w * (1 - m * 2)) / Math.max(1e-6, x1 - x0);
	const sy = (h * (1 - m * 2)) / Math.max(1e-6, y1 - y0);
	const k = Math.min(sx, sy);
	const offx = (w - (x1 - x0) * k) / 2 - x0 * k;
	const offy = (h - (y1 - y0) * k) / 2 - y0 * k;
	return pts.map(p => ({ x: p.x * k + offx, y: p.y * k + offy }));
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

	let best = [];
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
		if (ring.length > best.length) best = ring;
	}
	return best;
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

	const coast = mwCoast(w, h, seed, opts.coastSteps);

	/* A coarse grid. Fine enough that a border reads as a line rather than
	   as stairs once smoothed, coarse enough to stay cheap on a phone:
	   160 x 110 is ~18k cells and one point-in-polygon test each. */
	const gw = opts.gw || 220;
	const gh = opts.gh || Math.max(40, Math.round(gw * (h / w)));
	const cs = w / gw;
	const ox = 0, oy = 0;

	const land = new Uint8Array(gw * gh);
	let landCount = 0;
	for (let j = 0; j < gh; j++) {
		for (let i = 0; i < gw; i++) {
			const x = ox + (i + 0.5) * cs, y = oy + (j + 0.5) * (h / gh);
			if (mwInside(x, y, coast)) { land[j * gw + i] = 1; landCount++; }
		}
	}
	if (!landCount) return null;

	/* Seeds spread around a ring inside the coast, in sorted-name order, so
	   neighbours are stable and a faction keeps roughly its part of the
	   world as others come and go. */
	const rng = mwRng(seed ^ 0x9e3779b9);
	const seeds = names.map((nm, idx) => {
		const t = (idx / names.length) * Math.PI * 2 + rng() * 0.25;
		const rr = 0.18 + rng() * 0.42;
		return {
			name: nm,
			x: w / 2 + Math.cos(t) * w * 0.40 * rr * 2,
			y: h / 2 + Math.sin(t) * h * 0.40 * rr * 2,
			weight: Math.max(1, Number((factions.find(f => String(f.name) === nm) || {}).weight) || 1)
		};
	});

	/*
	 * WEIGHTED NEAREST, THEN LLOYD.
	 *
	 * Distance is divided by the square root of the weight, so a faction
	 * with four times the realms claims about twice the radius and so
	 * about four times the area. Dividing by the weight itself made one
	 * large faction swallow the continent.
	 */
	const owner = new Int16Array(gw * gh).fill(-1);
	const cellH = h / gh;
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
			if (!sn[s]) continue;
			seeds[s].x = sx[s] / sn[s];
			seeds[s].y = sy[s] / sn[s];
		}
	}

	const regions = [];
	for (let s = 0; s < seeds.length; s++) {
		const ring = mwTrace(owner, s, gw, gh, cs, ox, oy);
		if (ring.length < 4) continue;
		let cells = 0;
		for (let k = 0; k < owner.length; k++) if (owner[k] === s) cells++;
		regions.push({
			name: seeds[s].name,
			poly: mwSmooth(mwDedupe(ring), opts.smooth === undefined ? 3 : opts.smooth),
			cells: cells,
			centre: { x: seeds[s].x, y: seeds[s].y }
		});
	}
	if (!regions.length) return null;

	return { coast: coast, regions: regions, width: w, height: h };
}

/* Browser globals, and CommonJS for the harness. */
if (typeof module !== 'undefined' && module.exports) {
	module.exports = { mwHash, mwRng, mwCoast, mwInside, mwTrace, mwDedupe, mwSmooth, mwWorld };
}
