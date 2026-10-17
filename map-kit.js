/*
 * map-kit.js -- the geometry and SVG primitives behind the Skulliance maps.
 *
 * WHY THIS FILE EXISTS. map.js drew the realm map: faction territories as
 * jittered, smoothed polygons, with avatar markers packed inside them and
 * arrowed lines between the markers. The Rivalries map on the Arena needs the
 * same machinery for a different graph -- player clusters instead of faction
 * territories, Fighters instead of realms, battles instead of raids -- and
 * copying 150 lines of polygon maths into a second file is how two maps end up
 * disagreeing about what a centroid is.
 *
 * So the generic half lives here and both maps load it. Every function below
 * was moved out of map.js UNCHANGED; if one of them needs to behave
 * differently for one map, it takes a parameter rather than a fork.
 *
 * A CLASSIC SCRIPT, NOT A MODULE, because that is what map.js is and what
 * realms.php loads it as. These become globals, so nothing here may redeclare
 * a name either map already owns -- a duplicate top-level `const` across two
 * classic scripts is a SyntaxError that takes the whole page down, not a
 * shadowed variable.
 *
 * LAYOUT MUST BE STABLE ACROSS RELOADS. mulberry32 seeded off hashStr is the
 * whole reason: a map that reshuffles every time you open it cannot be learned,
 * and "my cluster is bottom left" is most of how people navigate one. Nothing
 * here may reach for Math.random without being handed it.
 */

/* The SVG namespace, needed by svgEl and the text measurer. */
const MAPKIT_NS = 'http://www.w3.org/2000/svg';

/* ---- palette -------------------------------------------------------------
   Shared so the two maps cannot drift into different house colours. How a
   colour is CHOSEN differs: the realm map picks at random per faction, the
   rivalry map derives one from the player id so a person keeps their colour
   between visits. See mapkitColorFor(). */
const palette = [
    '#FF3366','#FA00FF','#00E6B3','#00FFFF','#FF8C33',
    '#33CC99','#8C33FF','#0077FF','#FF66CC','#FF80FF',
    '#8000FF','#00B3B3','#4D4DFF','#CC99FF','#009999'
];

/** A stable colour for a key -- the same player is the same colour every visit. */
function mapkitColorFor(key) {
    return palette[hashStr(String(key)) % palette.length];
}

/* ---- colour ---- */
function hexToRgba(hex, a) {
    const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${a})`;
}

/* ---- seeded PRNG ----
   mulberry32(hashStr(someStableName)) is the idiom: deterministic, so a
   territory and the markers inside it land in the same place every render. */
function mulberry32(seed) {
    return function() {
        seed |= 0; seed = seed + 0x6D2B79F5 | 0;
        let t = Math.imul(seed ^ (seed >>> 15), 1 | seed);
        t = t + Math.imul(t ^ (t >>> 7), 61 | t) ^ t;
        return ((t ^ (t >>> 14)) >>> 0) / 4294967296;
    };
}

function hashStr(s) {
    let h = 0;
    for (let i = 0; i < s.length; i++) h = Math.imul(31, h) + s.charCodeAt(i) | 0;
    return Math.abs(h);
}

/* ---- polygons ----
   A rectangle roughed up into something hand-drawn (jitterRect), then rounded
   off (chaikin), then written as a path (toPath). The three are always used in
   that order. */
function jitterRect(x, y, w, h, j, rng) {
    rng = rng || Math.random.bind(Math);
    const corners = [{x: x, y: y}, {x: x+w, y: y}, {x: x+w, y: y+h}, {x: x, y: y+h}];
    const pts = [];
    for (let i = 0; i < 4; i++) {
        const a = corners[i], b = corners[(i+1)%4];
        const horiz = (i === 0 || i === 2);
        pts.push({ x: a.x + (rng()-.5)*j, y: a.y + (rng()-.5)*j });
        for (const t of [0.33, 0.67]) {
            pts.push({
                x: a.x + (b.x-a.x)*t + (horiz ? 0 : 1) * (rng()-.5)*j*1.8,
                y: a.y + (b.y-a.y)*t + (horiz ? 1 : 0) * (rng()-.5)*j*1.8
            });
        }
    }
    return pts;
}

function chaikin(pts, n=3) {
    for (let i = 0; i < n; i++) {
        const r = [];
        for (let j = 0; j < pts.length; j++) {
            const a = pts[j], b = pts[(j+1)%pts.length];
            r.push({ x:.75*a.x+.25*b.x, y:.75*a.y+.25*b.y });
            r.push({ x:.25*a.x+.75*b.x, y:.25*a.y+.75*b.y });
        }
        pts = r;
    }
    return pts;
}

function toPath(pts) {
    return pts.map((p,i) => `${i?'L':'M'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ') + 'Z';
}

function centroid(pts) {
    return {
        x: pts.reduce((s,p)=>s+p.x,0)/pts.length,
        y: pts.reduce((s,p)=>s+p.y,0)/pts.length
    };
}

/* ---- point in polygon ---- */
function inPoly(px, py, poly) {
    let inside = false;
    for (let i=0, j=poly.length-1; i<poly.length; j=i++) {
        const {x:xi,y:yi}=poly[i], {x:xj,y:yj}=poly[j];
        if (((yi>py) !== (yj>py)) && px < (xj-xi)*(py-yi)/(yj-yi)+xi) inside=!inside;
    }
    return inside;
}

/* ---- packing markers inside a polygon ----
   A candidate grid, rejected where it falls outside the shape, shuffled, then
   taken greedily so long as each new point clears R*3 of the ones already
   chosen.

   IT ALWAYS RETURNS EXACTLY `count`. When the polygon cannot hold that many
   at that spacing, the remainder are laid on a circle around the centroid --
   so a cramped cluster degrades into overlapping markers rather than losing
   any. A caller that must not overlap has to size the polygon to the count
   BEFORE calling (see rivalries.js, which grows a cluster with its Crew). */
function placeMarkers(poly, count, bbox, R, rng) {
    rng = rng || Math.random.bind(Math);
    const {x, y, w, h} = bbox;
    const minDist = R * 3;
    const cols = Math.ceil(Math.sqrt(count)) + 2;
    const step = Math.max(minDist, Math.min(w, h) / cols);
    const candidates = [];

    for (let cy = y + R*2; cy < y+h-R*2; cy += step) {
        for (let cx = x + R*2; cx < x+w-R*2; cx += step) {
            const jx = cx + (rng()-.5)*step*.5;
            const jy = cy + (rng()-.5)*step*.5;
            if (inPoly(jx, jy, poly)) candidates.push({x:jx, y:jy});
        }
    }

    // Shuffle
    for (let i = candidates.length-1; i > 0; i--) {
        const j = Math.floor(rng()*(i+1));
        [candidates[i], candidates[j]] = [candidates[j], candidates[i]];
    }

    const chosen = [];
    for (const c of candidates) {
        if (chosen.length >= count) break;
        if (!chosen.some(p => Math.hypot(p.x-c.x, p.y-c.y) < minDist)) chosen.push(c);
    }

    // Fallback: spiral from centroid
    if (chosen.length < count) {
        const cen = centroid(poly);
        const rad = Math.min(w, h) * 0.28;
        for (let i = chosen.length; i < count; i++) {
            const a = (i / count) * Math.PI * 2;
            chosen.push({ x: cen.x + Math.cos(a)*rad, y: cen.y + Math.sin(a)*rad });
        }
    }

    return chosen.slice(0, count);
}

/* ---- SVG ---- */
function svgEl(tag, attrs={}) {
    const e = document.createElementNS(MAPKIT_NS, tag);
    for (const [k,v] of Object.entries(attrs)) e.setAttribute(k, v);
    return e;
}

/*
 * A hidden SVG kept in the document purely to measure text.
 *
 * The maps size their own viewBox to fit their ink, and the widest ink on the
 * realm map turned out to be a username under a marker, not any shape -- so
 * the bounds cannot be computed without actually measuring the type.
 */
const _measureSvg = document.createElementNS(MAPKIT_NS, 'svg');
_measureSvg.setAttribute('style', 'position:absolute;visibility:hidden;width:0;height:0;overflow:hidden');
document.body.appendChild(_measureSvg);

function measureTextWidth(text, fontSize, fontFamily, fontWeight, letterSpacing) {
    const probe = document.createElementNS(MAPKIT_NS, 'text');
    probe.setAttribute('font-size', fontSize);
    probe.setAttribute('font-family', fontFamily);
    probe.setAttribute('font-weight', fontWeight);
    probe.setAttribute('letter-spacing', letterSpacing);
    probe.textContent = text;
    _measureSvg.appendChild(probe);
    const w = probe.getComputedTextLength();
    _measureSvg.removeChild(probe);
    return w;
}
