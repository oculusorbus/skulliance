// ── Data parsing ─────────────────────────────────────────────────────────────
if (!window.csvData) { console.warn('map.js: no csvData'); }
const rows = (window.csvData || '').split('\n').slice(1);
const data = rows.map(row => {
    const [user_name, user_image, realm_name, realm_image, faction_name, faction_currency, realm_id, avg_level] =
        row.split('","').map(v => v.replace(/^"|"$/g, ''));
    return { user_name, user_image, realm_name, realm_image, faction_name, faction_currency, realm_id, avg_level: parseInt(avg_level) || 0 };
});

const factions = Object.values(data.reduce((acc, d) => {
    if (!acc[d.faction_name]) acc[d.faction_name] = { name: d.faction_name, currency: d.faction_currency, realms: [] };
    acc[d.faction_name].realms.push(d);
    return acc;
}, {}));

// Sort realms within each faction by realm_id for stable marker placement
factions.forEach(f => f.realms.sort((a, b) => parseInt(a.realm_id) - parseInt(b.realm_id)));

/* ── Colors ──────────────────────────────────────────────────────────────────
   `palette`, hexToRgba, the seeded PRNG (mulberry32/hashStr), the polygon
   helpers (jitterRect/chaikin/toPath/centroid/inPoly), placeMarkers, svgEl and
   measureTextWidth all moved to map-kit.js, which the Rivalries map on the
   Arena shares. They are globals from a classic script, so they are used here
   exactly as before -- realms.php must load map-kit.js BEFORE this file.
   getColor stays: the realm map wants a random colour per faction, where the
   rivalry map wants a stable one per player (mapkitColorFor). */

/*
 * A FACTION'S COLOUR IS THE SAME COLOUR EVERY VISIT.
 *
 * This was Math.random() out of a pool, so every faction repainted itself
 * on every page load. Nothing can feel like a place if it changes colour
 * while you are looking away, and it is the cheapest fix on the map.
 *
 * NOT mapkitColorFor() ON ITS OWN, which is what the Rivalries map uses:
 * that hashes straight into the palette and two factions out of fifteen
 * collide often. Here the names are SORTED and dealt consecutive palette
 * entries from a hashed starting offset -- stable, and distinct until
 * there are more factions than colours.
 */
function stableColors(names) {
    const sorted = names.slice().sort();
    const start = hashStr(sorted.join('|')) % palette.length;
    const out = {};
    sorted.forEach((n, i) => { out[n] = palette[(start + i) % palette.length]; });
    return out;
}


// ── Seeded PRNG ───────────────────────────────────────────────────────────────


// ── Popup ─────────────────────────────────────────────────────────────────────
const popupOverlay = document.getElementById('popup-overlay');
const popupImage   = document.getElementById('popup-image');
const popupAvatar  = document.getElementById('popup-avatar');
const popupUser    = document.getElementById('popup-user');
const popupRealm   = document.getElementById('popup-realm');
const popupClose   = document.getElementById('popup-close');

function showPopup(realmSrc, avatarSrc, userName, realmName, factionName, factionCurrency, avgLevel, realmId) {
    popupImage.src = realmSrc;
    popupAvatar.src = avatarSrc;
    popupAvatar.onerror = function() { this.src = 'icons/skull.png'; };
    popupUser.innerHTML = '<a href="/staking/profile.php?username=' + encodeURIComponent(userName) + '" style="color:inherit;text-decoration:underline;" onclick="event.stopPropagation()">' + userName.replace(/</g,'&lt;').replace(/>/g,'&gt;') + '</a>';
    popupRealm.textContent = realmName;

    // Faction chip
    const factionIcon = document.getElementById('popup-faction-icon');
    factionIcon.src = 'icons/' + (factionCurrency || '').toLowerCase() + '.png';
    factionIcon.onerror = function() { this.style.display = 'none'; };
    factionIcon.style.display = '';
    document.getElementById('popup-faction-name').textContent = factionName || '';

    // Avg level chip
    document.getElementById('popup-avg-level').textContent = avgLevel || 0;

    // Raid status chips
    const pairs      = window.raidPairs || [];
    const rid        = String(realmId);
    const offenseCount = pairs.filter(p => String(p[0]) === rid).length;
    const defenseCount = pairs.filter(p => String(p[1]) === rid).length;
    const chipsEl    = document.getElementById('popup-raid-chips');
    chipsEl.innerHTML = '';
    if (offenseCount > 0) {
        chipsEl.innerHTML += '<span class="popup-stat popup-badge-attack">Raiding <strong>' + offenseCount + '</strong></span>';
    }
    if (defenseCount > 0) {
        chipsEl.innerHTML += '<span class="popup-stat popup-badge-defend">Defending <strong>' + defenseCount + '</strong></span>';
    }
    if (offenseCount === 0 && defenseCount === 0) {
        chipsEl.innerHTML = '<span class="popup-stat popup-badge-peace">Peaceful</span>';
    }

    /* Zoom out FIRST, so the card opens against the whole map rather than
       over a corner of it, then anchor -- see both functions below. */
    resetPageZoom();
    popupOverlay.style.display = 'flex';
    anchorPopup();
}

/*
 * ZOOM THE PAGE BACK OUT BEFORE THE CARD OPENS.
 *
 * iOS exposes no API for this. The one thing that works is to declare the
 * page briefly unzoomable in the viewport meta -- Safari clamps the current
 * scale to maximum-scale the moment the tag changes -- and then put the
 * original tag straight back, which hands pinch-zoom over again. Leaving
 * user-scalable=0 behind would disable pinch for the rest of the session,
 * which is a worse bug than the one being fixed, so the restore is not
 * optional and is not conditional.
 *
 * ONLY WHEN ACTUALLY ZOOMED. An ordinary tap at scale 1 must not touch the
 * meta tag at all; header.php's viewport is shared by every page.
 *
 * It is a trick, and Safari has changed its mind about it before. That is
 * why anchorPopup() stays: if the clamp silently does nothing, the card is
 * still placed over what the player is looking at rather than off-screen.
 */
function resetPageZoom() {
    const vv = window.visualViewport;
    if (!vv || vv.scale <= 1.01) return false;
    const meta = document.querySelector('meta[name="viewport"]');
    if (!meta) return false;
    const original = meta.getAttribute('content');
    if (/maximum-scale/.test(original)) return false;   /* someone else owns it */
    meta.setAttribute('content', original + ', maximum-scale=1, user-scalable=0');
    setTimeout(() => meta.setAttribute('content', original), 300);
    return true;
}

/*
 * THE POPUP FOLLOWS WHAT YOU ARE ACTUALLY LOOKING AT.
 *
 * Pinch-zoom the page and position:fixed still measures against the LAYOUT
 * viewport, not the visual one -- so a zoomed-in player tapping a marker got
 * an overlay laid out across the whole unzoomed page, mostly off-screen.
 *
 * The obvious fix is to zoom back out first, and there is no reliable way to
 * do that: iOS exposes no API for it, the maximum-scale meta trick is flaky
 * and global, and it would throw away the player's position on the map every
 * time they opened a realm. visualViewport is the standard answer -- it
 * reports exactly the rectangle being displayed, at any zoom or scroll -- so
 * the overlay is pinned to that instead and lands centred on screen whatever
 * the zoom is, with the map left where it was underneath.
 */
function anchorPopup() {
    const vv = window.visualViewport;
    if (!vv) return;                       /* then fixed/100% is already right */
    popupOverlay.style.width  = vv.width      + 'px';
    popupOverlay.style.height = vv.height     + 'px';
    popupOverlay.style.left   = vv.offsetLeft + 'px';
    popupOverlay.style.top    = vv.offsetTop  + 'px';
}
if (window.visualViewport) {
    /* Only while it is open: zooming or scrolling with the popup up has to
       keep it in view, and doing nothing when it is closed keeps this off
       the critical path of every pinch. */
    const follow = () => { if (popupOverlay.style.display === 'flex') anchorPopup(); };
    window.visualViewport.addEventListener('resize', follow);
    window.visualViewport.addEventListener('scroll', follow);
}
function hidePopup() {
    popupOverlay.style.display = 'none';
    /* Hand the sizing back to the stylesheet. */
    popupOverlay.style.width = popupOverlay.style.height = '';
    popupOverlay.style.left  = popupOverlay.style.top    = '';
}
popupOverlay.addEventListener('click', e => { if (e.target === popupOverlay) hidePopup(); });
popupClose.addEventListener('click', hidePopup);

// ── Territory geometry ────────────────────────────────────────────────────────


// ── Point in polygon ──────────────────────────────────────────────────────────


// ── Marker placement within territory ────────────────────────────────────────


// ── Packing ───────────────────────────────────────────────────────────────────
const CELL = 170;
const PAD  = 32;

function buildFactionData() {
    const colors = stableColors(factions.map(f => f.name));
    return factions.map(f => {
        const count = f.realms.length;
        const cols = Math.max(1, Math.ceil(Math.sqrt(count)));
        const rows = Math.max(1, Math.ceil(count / cols));
        const currency = (f.currency || '').toLowerCase();
        return Object.assign({}, f, { count: count, w: cols*CELL, h: rows*CELL,
            color: colors[f.name], currency: currency });
    });
}

/* Below this the map is FITTED to the screen by CSS rather than packed to
   it -- see map.css. Matches map.css's own breakpoint, not realms.php's 700:
   this is about how the drawing is laid out, not about the page's columns. */
const MOBILE_BP = 768;
/* The width the map is COMPOSED at on a phone. Packing to vw*.92 there means
   the packer cannot fit two faction blocks beside each other -- every block
   is ceil(sqrt(n))*170 wide, so anything with five realms is already 510 --
   and the map degenerates into a single column. Measured on a 52-realm, ten-
   faction population at a 390px phone: 542 x 4652, which is a twelve-screen
   ribbon that ALSO overflows sideways. Composed at 1400 the same data is
   1132 x 2062, the same shape it has on a desktop, and CSS scales that to
   the screen. */
const MOBILE_DESIGN_W = 1400;
/* .main's 20px each side plus #container-wrapper's 10px each side. */
const MOBILE_GUTTER = 60;

function packFactions(fdata) {
    const vw = window.innerWidth <= MOBILE_BP ? MOBILE_DESIGN_W : window.innerWidth;
    const totalArea = fdata.reduce((s,f) => s+f.w*f.h, 0);
    const containerW = Math.max(Math.min(vw*.92, Math.sqrt(totalArea*2.2)), 320);

    const sorted = [...fdata].sort((a,b) => b.w*b.h - a.w*a.h);
    const placed = [], occupied = [];
    let maxH = 0;

    for (const f of sorted) {
        let pos = null;
        const orients = [{w:f.w, h:f.h}, {w:f.h, h:f.w}];

        for (const o of orients) {
            if (pos) break;
            for (let y = 0; y <= maxH+o.h && !pos; y += 10) {
                for (let x = 0; x <= containerW-o.w && !pos; x += 10) {
                    const ok = occupied.every(s =>
                        x+o.w+PAD <= s.x || x >= s.x+s.w+PAD ||
                        y+o.h+PAD <= s.y || y >= s.y+s.h+PAD);
                    if (ok) pos = {x, y, w:o.w, h:o.h, faction:f};
                }
            }
        }

        if (!pos) pos = {x:0, y:maxH+PAD, w:f.w, h:f.h, faction:f};

        placed.push(pos);
        occupied.push({x:pos.x, y:pos.y, w:pos.w, h:pos.h});
        maxH = Math.max(maxH, pos.y+pos.h);
    }

    return placed;
}

// ── SVG helpers ───────────────────────────────────────────────────────────────
const NS = 'http://www.w3.org/2000/svg';


// Persistent hidden SVG in the DOM used for text measurement


// ── Render ────────────────────────────────────────────────────────────────────
/*
 * THE WORLD, OR THE OLD PACKING IF ANYTHING GOES WRONG.
 *
 * mwWorld() grows faction territories from seeds inside a generated
 * coastline (map-world.js). Downstream code wants {faction,x,y,w,h,poly},
 * so each region's bounding box stands in for the rectangle it used to
 * have -- the label pill and the marker placement both read those and
 * neither cares that the shape inside is no longer a rectangle.
 *
 * WRAPPED, AND IT FALLS BACK. This is the realms map on a live platform;
 * a thrown error in new geometry must cost the coastline, not the map.
 */
/*
 * THE SEA, THE SHALLOWS AND THE LAND, as plain layers in draw order.
 *
 * THE SHALLOWS USED TO BE A 9px STROKE at 0.13 alpha outside the
 * coastline -- "the cheapest thing on the map that says coast", and it
 * was, but a stroke is a constant width no matter what the water is
 * doing. It stayed 9px across a strait 12px wide and 9px off the open
 * ocean, so it read as an outline round the land rather than as water.
 *
 * These are contours of a DISTANCE FIELD (mwShores in map-world.js, the
 * technique from spritecook.ai), so a narrow channel is shallow all the
 * way across while open sea goes dark, and every island gets its own
 * shelf for free. Biggest band first, so they stack into a gradient with
 * no blur filter -- the thing that made the old border glow a smear.
 *
 * PURE, AND SEPARATE FROM THE DRAWING, so the harness can call it. A
 * source check cannot tell a band that is drawn from one sitting behind
 * `if (false)`: the strings it looks for are all still there.
 */
/*
 * THE MOVING WATER, EVALUATED PER PIXEL.
 *
 * WHY THIS IS NOT SVG. His shader is three lines:
 *
 *     if (d < 2.2 + 1.1 * sin(TIME * 2.5)) c = foam;
 *     float phase = mod(d + TIME * wave_speed, wave_spacing);
 *     if (phase < 1.6 && d > 4.0 && d < 20.0) c = wave;
 *
 * The edges are HARD -- there is no smoothstep anywhere in it. All of
 * the fluidity comes from `d` being evaluated at every pixel, so the
 * ring slides a fraction of a pixel per frame and never sits still.
 *
 * Two attempts here cross-faded fixed contours instead: five rings, then
 * eight with softer keyframes and overlapping phases. Both were reported
 * as "flashing lights in succession", and both always would be. A
 * contour is ONE position. N contours give the ring N positions, and
 * fading between two static shapes is not movement -- you see both at
 * once, then one of them. No keyframe fixes that. The geometry has to
 * stop being fixed, which means pixels.
 */

/* Distances in screen pixels. His 4-20 scales to 13-67 here, which is
   the full-shelf version the owner turned down; these are the tight
   surf numbers kept from that round. */
/*
 * HALF HIS TEMPO, ON THE OWNER'S CALL: "the example takes about 6
 * seconds to complete a wave and ours is running at 3".
 *
 * One factor on both clocks rather than two separate numbers, so the
 * relationship his shader sets between the foam breathing and the waves
 * arriving survives the change -- retiming them independently is how
 * the foam stops landing with the crest.
 *
 * Measured off the clip for the record: at a fixed point near the shore
 * a crest passes every 2.1-2.3s, which is FASTER than this. The visible
 * journey of one line is the longer thing, and it is what a reader
 * actually times. This is the owner's reading of his own map against
 * ours, not a number derived from the video, and that is the right
 * authority for how busy the page should feel.
 */
/* What renderMap() paints under the shelf bands: the wrapper's own
   background colour and the whole-sheet sea rect over it. The bed has
   to start from the same two or the canvas would not match the SVG it
   covers. */
const SEA_BED = { page: '#07111d', sheet: 'rgba(10,32,52,0.55)',
                  mottle: 58, mottleAmp: 3 };

const WATER_TEMPO = 0.5;
const WATER = {
    foamMid: 7.4, foamSwing: 3.7,                /* his 2.2 +/- 1.1, scaled */
    foamHz: 2.5 * WATER_TEMPO,                   /* his sin(TIME * 2.5) */
    waveLo: 3, waveHi: 22, spacing: 14, width: 3,
    speed: 10 * WATER_TEMPO,
    /*
     * THE OPEN SEA: A WAVE FIELD, DRIVEN BUT NEVER STRUCK.
     *
     * The owner found two canvas water demos and both are the classic
     * two-buffer height field. Built here, it gave the best result on
     * the page -- but only after several ripples had spread and
     * interfered: "the dynamic that occurs after it settles into a
     * nice undulation feels the most natural, but the initial circle
     * animations were spreading out and blending together to achieve
     * that, so it needs a catalyst, and the catalyst is what is
     * jarring." Exactly right. A circle appearing in open water means
     * something the size of a comet just hit it.
     *
     * So the simulation stays and the IMPULSES GO. Instead of striking
     * the water in one place, every wet cell is pushed every frame by a
     * tiny amount read from a slow scrolling field -- thousands of
     * times too small to see as an event, and never the same across
     * two neighbouring cells. The wave equation does the rest: it
     * spreads that forcing, interferes it with itself, and bounces it
     * off the coastlines, which is the settled state the owner liked,
     * arrived at without anything visibly causing it.
     *
     * It is also deterministic. The drop version wanted Math.random(),
     * which this map has banned since faction colours repainted on
     * every load.
     *
     * THE FORCING FIELD IS BUILT FROM WHOLE HARMONICS of its grid, so
     * that reading it at a wrapped offset has no seam; the swell
     * arrives from a direction and keeps arriving.
     *
     * swellFade keeps the drawing out of the shallows: the surf owns
     * that band, and two moving things in the same pixels is mush.
     */
    /*
     * AND THE WEATHER COMES FROM OVER THE HORIZON.
     *
     * The impulse is what made a comet of it: a circle appearing in
     * open water, with a rim, that the eye tracks back to a point. The
     * owner's fix is better than removing it -- "if that type of
     * catalyst happens outside of the container, the user wouldn't see
     * the comet, only the results of the impact". Which is also how a
     * real swell works: it is made by weather somewhere else and
     * arrives already spread out.
     *
     * So the simulation grid is BIGGER THAN THE MAP. simPad cells of
     * open ocean surround the visible sheet on every side, the strikes
     * all land out there, and only the wavefronts they send inward are
     * ever drawn. Nothing visible starts anything.
     *
     * DAMPING HAD TO RISE FOR IT TO WORK. At 0.982 a wave fades over
     * about 55 cells and would die in the margin without ever reaching
     * the map. It travels roughly a cell a step, so the damping sets
     * how far offshore the weather can be, and these two numbers have
     * to be chosen together.
     *
     * The strikes are deterministic -- mwRng seeded off the world, not
     * Math.random, which this map has banned since faction colours
     * repainted on every load.
     */
    simDiv: 2, simPad: 44, damping: 0.9945, simHz: 20,
    /*
     * AND THE WEATHER IS FRENZIED, NOT PUNCTUAL.
     *
     * One strike every half second out in the margin still arrives as
     * one wavefront: a clean arc crossing the coast, which is the
     * comet again with a longer fuse. "The activity outside the
     * container should be so random and frenzied that I don't see
     * individual circles spreading out into the map."
     *
     * So it is a storm rather than a drumbeat: several small strikes
     * every single frame, scattered right round the ring, each far too
     * weak to be followed. What crosses into the map is their sum,
     * which has no shape to recognise.
     */
    /* Large and weak, not small and sharp: a small source makes a
       short wavelength, and a sea full of short wavelengths is static.
       Measured over radius 4 / 10 / 20 / 30, the picture gets steadily
       smoother as the sources get broader. */
    /*
     * AND THE STORMS ARE NEARLY OFF, BECAUSE THE RIM WAS THE PROBLEM.
     *
     * Every strike lands just outside the sheet, so the rim of the map
     * sees the weather at close range -- short, sharp, newly made
     * chop -- while only the long wavelengths survive the crossing to
     * the middle. That is a gradient in CHARACTER, not just strength,
     * and the owner picked it out exactly: "the activity on the
     * outskirts is way too busy, very staticy and distracting... the
     * center water actually looks more natural than the outer water".
     *
     * The centre looks better because out there the sea is driven
     * almost entirely by the scrolling forcing field, which is smooth,
     * even, and the same everywhere. So that becomes the main driver
     * and the storms drop to an occasional broad nudge -- big enough
     * to keep the field from settling into the forcing's own rhythm,
     * too soft and too wide to read as weather arriving.
     */
    stormsPerFrame: 1, stormForce: 0.09, stormRadius: 18,
    /*
     * REFRACTION, NOT AN OVERLAY. THIS IS THE ONE THAT WORKS.
     *
     * Everything before this painted a translucent colour ON TOP of the
     * sea -- pale on a crest, dark in a trough -- and every single
     * complaint the owner made about it traces to that one decision.
     * An overlay has no good setting. Turn it down and the sea is
     * flat; turn it up and you get "nearly zebra stripes", "large
     * swaths of undulating light high contrast color", and under the
     * pointer "a long swath of color, like a ghost flying over the
     * world, or fog". Adding a stipple to break the swaths up only
     * swapped one complaint for another: "textured instead of water
     * like".
     *
     * His own reference never adds colour at all. Almeros's canvas
     * water takes the image underneath and SAMPLES IT AT AN OFFSET
     * read off the slope of the wave field, which is what a water
     * surface actually does to what is under it. Every pixel on screen
     * is still a sea pixel; it has just moved a little. So it cannot
     * blow out, cannot band, and cannot fog, because there is no
     * second colour anywhere in it to do any of those things.
     *
     * What it needs is something worth bending, and this map already
     * has it: the shelf bands. Those contours ring every coast and
     * island, and seen through moving water they ripple like a seabed
     * through a swell. The bed below is drawn from seaLayers(), the
     * same function the SVG draws, so the canvas is an exact copy of
     * the sea it covers and the only difference a reader can see is
     * the movement.
     *
     * refract is the offset in canvas pixels per unit of slope;
     * lightGain is Almeros's shading term, the small lightening on a
     * face tilted towards the light, added to the sampled pixel rather
     * than painted over it.
     */
    /*
     * TUNED AT EQUILIBRIUM, NOT ON THE WAY UP.
     *
     * The sea takes the better part of a minute to settle: forced
     * continuously and damped at 0.9945, its amplitude climbs and then
     * levels off. Tuned by eye a few seconds after a reload, it is
     * being judged while still quiet, and it goes on getting busier
     * after the judging stops -- which is what happened here, twice.
     * Measured, the mean rose from 1.79 through 1.04 and settled at
     * 1.02, half again as lively as the moment it was signed off.
     *
     * So this number is set against the SETTLED field. Anything
     * re-tuned here has to be read after thirty seconds or more.
     */
    forceGain: 0.008, refract: 22, lightGain: 15,
    swellFade: 2.2,
    swellDrift: [[15.0, 5.4], [-6.6, -9.0], [3.3, -4.2]],
    /* The wake. Wide and shallow on purpose: see waterWake(). */
    wakeRadius: 14, wakeForce: 0.22, wakeSpeedRef: 320,
    /*
     * SOFT EDGES, WHICH HIS SHADER DOES NOT HAVE, AND WHY.
     *
     * His test is `phase < 1.6` -- a hard in-or-out. That makes a given
     * pixel's alpha a STEP FUNCTION OF TIME: it is off, then on, with
     * nothing in between. At his tempo the steps come fast enough to
     * read as flow. Halved, they do not: the owner saw it immediately
     * as "creeping and stuttery", which is thousands of pixels each
     * snapping on at its own moment.
     *
     * A ramp instead of a step makes alpha a CONTINUOUS function of t,
     * because the phase term carries t continuously even though the
     * distance at a pixel never changes. That is what sub-pixel motion
     * looks like when you cannot move a pixel.
     *
     * aa is the ramp width in screen pixels; fade is how far a crest
     * takes to arrive and to die at the two ends of the surf, so rings
     * do not pop into existence offshore or vanish at the beach.
     */
    aa: 1.6, fade: 5,
    foam: [168, 226, 243, 0.42], wave: [190, 232, 248, 0.5]
};

function smoothEdge(e0, e1, x) {
    let t = (x - e0) / (e1 - e0);
    t = t < 0 ? 0 : (t > 1 ? 1 : t);
    return t * t * (3 - 2 * t);
}

/** Where the foam edge stands at time t -- his 2.2 + 1.1 * sin(TIME * 2.5). */
function foamEdgeAt(t) {
    return WATER.foamMid + WATER.foamSwing * Math.sin(t * WATER.foamHz);
}

/** Foam coverage at distance d, 0..1, given the edge for this frame. */
function surfFoam(d, edge) {
    if (d <= 0) return 0;
    return 1 - smoothEdge(edge - WATER.aa, edge + WATER.aa, d);
}

/**
 * Wave coverage at distance d, 0..1, for a frame whose crests have been
 * carried `shift` pixels shoreward (shift = t * speed, hoisted out of
 * the pixel loop).
 */
function surfWave(d, shift) {
    if (d <= 0 || d <= WATER.waveLo || d >= WATER.waveHi) return 0;
    let phase = (d + shift) % WATER.spacing;
    if (phase < 0) phase += WATER.spacing;
    const half = WATER.width / 2;
    /* CIRCULAR distance to the crest, or the band is sliced in half at
       the modulus boundary: the soft trailing edge sits just below
       phase 0, wraps round to just below `spacing`, and a plain
       abs(phase - half) throws it away. Measured as a 0.54 alpha jump
       in a single frame at the wrap -- a step, which is the whole thing
       being fixed here. */
    let dp = Math.abs(phase - half);
    if (dp > WATER.spacing / 2) dp = WATER.spacing - dp;
    let a = 1 - smoothEdge(half - WATER.aa, half + WATER.aa, dp);
    /* Arrive and die gently at the two ends of the surf zone. */
    a *= smoothEdge(WATER.waveLo, WATER.waveLo + WATER.fade, d);
    a *= 1 - smoothEdge(WATER.waveHi - WATER.fade, WATER.waveHi, d);
    return a;
}

/*
 * WHERE THE WATER CANVAS HAS TO SIT.
 *
 * The distance field is built in WORLD coordinates, 0..svgW by 0..svgH.
 * The SVG does not show that rectangle 1:1: after everything is drawn
 * the viewBox is grown to take in the faction labels that bleed past
 * the edges, so world (0,0) ends up at (-vx, -vy) inside an element
 * that is fitW wide for vw of world.
 *
 * Stretching the canvas over the container with width:100% ignores all
 * of that. Measured live: a 1940x1146 world inside a viewBox of
 * -6 -6 1952 1157, so the surf was drawn 0.62% too wide and 6px off --
 * dead on near the middle of the map and out by up to 6px at the
 * edges, in whichever direction a landmass happened to sit. Reported
 * as the shoreline lining up on one side of a landmass and leaving
 * gaps on the others, which is exactly what a small stretch looks
 * like: it cannot be wrong by the same amount everywhere.
 *
 * Pure, and the harness checks it against the mapping the SVG itself
 * uses, because "these two coordinate systems agree" is not something
 * a screenshot shows you until it is already wrong.
 */
function surfBox(vx, vy, vw, fitW, svgW, svgH) {
    const scale = fitW / vw;
    return {
        left: (0 - vx) * scale,
        top: (0 - vy) * scale,
        width: svgW * scale,
        height: svgH * scale
    };
}

/**
 * The baked swell: one value per pixel, built from whole harmonics of
 * the canvas so that sampling it at a wrapped offset has no seam.
 *
 * WHOLE HARMONICS IS THE WHOLE TRICK. The field is scrolled rather
 * than recomputed, which means it is read at (x + ox) mod W. If the
 * wave numbers were not integer multiples of the canvas, that wrap
 * would put a visible straight seam down the open sea every time the
 * offset passed zero -- and it is open sea, so there is nothing to
 * hide it behind.
 *
 * Pulled out so the harness can check the seam: the first column and
 * the last must meet, and so must the first row and the last.
 */
function swellField(w, h) {
    const f = new Float32Array(w * h);
    for (let y = 0; y < h; y++) {
        const v = y / h;
        for (let x = 0; x < w; x++) {
            const u = x / w;
            f[y * w + x] =
                  Math.sin(2 * Math.PI * (2 * u + 1 * v)) * 0.55
                + Math.sin(2 * Math.PI * (3 * u - 2 * v)) * 0.28
                + Math.sin(2 * Math.PI * (5 * u + 4 * v)) * 0.17;
        }
    }
    return f;
}

/**
 * One step of the height field, written into `nxt`.
 *
 * The classic two-buffer wave equation both of the owner's references
 * use: each cell becomes the average of its four neighbours minus its
 * own previous value, then damped. `wet` lists the cells that are
 * water AND off the outer ring, so land is never written and never
 * stepped -- which is what makes a coastline a wall that swell
 * reflects off instead of a hole it drains into. Those reflections
 * are most of why this reads as a sea and not a screensaver.
 *
 * DAMPING MUST BE BELOW 1. The equation has no friction of its own,
 * and this field is forced continuously, so the only thing standing
 * between it and a basin that rings louder every second is this
 * multiply.
 *
 * Out here at module scope so the harness can run it on a pond small
 * enough to reason about: energy must fall, land must stay at zero,
 * and a disturbance must spread the same way in all four directions.
 */
function waterStep(cur, nxt, wet, w, damping) {
    for (let n = 0; n < wet.length; n++) {
        const k = wet[n];
        nxt[k] = ((cur[k - 1] + cur[k + 1] + cur[k - w] + cur[k + w]) * 0.5 - nxt[k]) * damping;
    }
    return nxt;
}

/**
 * A soft push into the height field, under the pointer.
 *
 * THE SHAPE IS THE WHOLE DIFFERENCE between a wake and a comet. A
 * hard-edged disc of constant force reads as something ARRIVING: an
 * event, with a rim, that the eye tracks back to a point. The owner's
 * verdict on those was exact -- "a massive comet would have to hit the
 * ocean to create that effect". The same energy spread with a smooth
 * falloff, wide and shallow, reads as something PASSING: "more like a
 * motorboat that disrupts the ocean as the user moves the mouse, and
 * that makes the user feel in control."
 *
 * So the profile is (1 - (r/R)^2)^2, with no edge anywhere in it, and
 * the amplitude rides on how fast the pointer is travelling, because a
 * boat sitting still does not leave a wake either.
 *
 * IT REFUSES LAND, AND THAT IS NOT COSMETIC. A land cell is never
 * stepped and never damped, so a value written to one stays for the
 * life of the page, and waterStep reads its neighbours: it becomes a
 * permanent source quietly pumping the sea. The paint loop skips land,
 * so it is invisible in a screenshot and only shows up minutes later
 * as a bright patch that will not fade. The outer ring is refused for
 * the same reason.
 */
function waterWake(cur, land, w, h, cx, cy, radius, force) {
    const r = Math.max(1, radius | 0), r2 = r * r;
    let hit = 0;
    for (let j = -r; j <= r; j++) {
        const y = cy + j;
        if (y < 1 || y >= h - 1) continue;
        for (let i = -r; i <= r; i++) {
            const x = cx + i;
            if (x < 1 || x >= w - 1) continue;
            const q = (i * i + j * j) / r2;
            if (q >= 1) continue;
            const k = y * w + x;
            if (land[k]) continue;
            const soft = (1 - q) * (1 - q);
            cur[k] += force * soft;
            hit++;
        }
    }
    return hit;
}

/**
 * The sea, as pixels, for the water to bend.
 *
 * An exact copy of what the SVG paints under the canvas: the page's
 * own background, the whole-sheet sea rect over it, then the shelf
 * bands from seaLayers() -- the same function the renderer draws from,
 * so the two cannot drift apart. The land rings are skipped; the water
 * never samples them and the canvas leaves those pixels transparent so
 * the real land, its texture and its avatars show through.
 *
 * Built once. It is the seabed: it does not move, the water over it
 * does.
 */
function seaBed(world, w, h, scale) {
    const cv = document.createElement('canvas');
    cv.width = w; cv.height = h;
    const g = cv.getContext('2d', { willReadFrequently: true });
    if (!g) return null;
    g.fillStyle = SEA_BED.page;
    g.fillRect(0, 0, w, h);
    g.fillStyle = SEA_BED.sheet;
    g.fillRect(0, 0, w, h);
    for (const L of seaLayers(world)) {
        if (L.band < 0) continue;                  /* land is not seabed */
        g.fillStyle = L.fill;
        g.beginPath();
        const rings = L.rings || [L.ring];
        for (const ring of rings) {
            for (let i = 0; i < ring.length; i++) {
                const x = ring[i].x * scale, y = ring[i].y * scale;
                if (i) g.lineTo(x, y); else g.moveTo(x, y);
            }
            g.closePath();
        }
        g.fill('nonzero');
    }
    /*
     * AND A SEABED OUT IN THE DEEP, OR THE OPEN WATER CANNOT MOVE.
     *
     * Refraction can only show what is already there: offset a flat
     * colour by a few pixels and you get the same flat colour back.
     * Measured on the finished canvas, the shelf bands rippled on 11
     * to 13 per cent of their pixels each frame while the open middle
     * -- one flat fill, nothing in it -- moved 0.2 per cent. The sea
     * was alive only where it happened to have contours.
     *
     * So the deep gets a seabed too: very large, very faint mottling,
     * a handful of levels either side of the fill, far too soft to
     * read as a pattern when the water is still. It is not decoration.
     * It is the thing the swell is seen THROUGH, and the whole visible
     * amplitude of the open ocean is set by how much of it there is --
     * which is also why this effect cannot blow out the way the
     * overlay did. The contrast has a ceiling, and the ceiling is the
     * bed's own.
     */
    const img = g.getImageData(0, 0, w, h);
    const px = img.data;
    const rnd = (typeof mwRng === 'function')
        ? mwRng(((world && world.seed) || 1) ^ 0xbed5ea) : null;
    if (rnd) {
        const sc = SEA_BED.mottle, amp = SEA_BED.mottleAmp;
        const gw = Math.ceil(w / sc) + 2, gh = Math.ceil(h / sc) + 2;
        const n1 = new Float32Array(gw * gh);
        for (let i = 0; i < n1.length; i++) n1[i] = rnd() * 2 - 1;
        /* a second octave at half the size, a third of the weight */
        const sc2 = sc / 2.3;
        const gw2 = Math.ceil(w / sc2) + 2, gh2 = Math.ceil(h / sc2) + 2;
        const n2 = new Float32Array(gw2 * gh2);
        for (let i = 0; i < n2.length; i++) n2[i] = rnd() * 2 - 1;
        const samp = (n, nw, scale, x, y) => {
            const fx = x / scale, ix = fx | 0, tx = fx - ix;
            const fy = y / scale, iy = fy | 0, ty = fy - iy;
            const sx = tx * tx * (3 - 2 * tx), sy = ty * ty * (3 - 2 * ty);
            const a = n[iy * nw + ix], b = n[iy * nw + ix + 1];
            const c = n[(iy + 1) * nw + ix], d = n[(iy + 1) * nw + ix + 1];
            return (a + (b - a) * sx) * (1 - sy) + (c + (d - c) * sx) * sy;
        };
        for (let y = 0; y < h; y++) {
            for (let x = 0; x < w; x++) {
                const v = (samp(n1, gw, sc, x, y) + samp(n2, gw2, sc2, x, y) * 0.34) * amp;
                const i = (y * w + x) * 4;
                px[i] = Math.max(0, Math.min(255, px[i] + v));
                px[i + 1] = Math.max(0, Math.min(255, px[i + 1] + v));
                px[i + 2] = Math.max(0, Math.min(255, px[i + 2] + v * 1.25));
            }
        }
        g.putImageData(img, 0, 0);
    }
    return new Uint32Array(img.data.buffer);
}

/**
 * One pixel of moving water: read the bed at an offset and shade it.
 *
 * `gx, gy` is the slope of the height field at this pixel's cell, `d`
 * the displacement per unit slope, `sh` the shading to add. The bed is
 * read BETWEEN pixels -- the offset is a float and the four
 * neighbours are blended -- which is the whole point of pulling this
 * out where it can be tested.
 *
 * Truncating that offset to a whole pixel is a bug that looks like a
 * rounding detail and is not: below one pixel of displacement the
 * sample index stops changing, so gentle water does not move a little,
 * it stops dead. Measured when it was wrong, 9 per cent of pixels over
 * the shelf bands moved each frame against 0.2 per cent out in the
 * deep, with a line between them.
 *
 * Writes r, g, b into `rgb` rather than returning a triple: this runs
 * a few hundred thousand times a second and an array per pixel is a
 * few hundred thousand allocations.
 */
function waterPixel(bedR, bedG, bedB, w, h, x, y, gx, gy, d, sh, rgb) {
    let fx = x + gx * d, fy = y + gy * d;
    if (fx < 0) fx = 0; else if (fx > w - 1.001) fx = w - 1.001;
    if (fy < 0) fy = 0; else if (fy > h - 1.001) fy = h - 1.001;
    const ix = fx | 0, iy = fy | 0;
    const tx = fx - ix, ty = fy - iy;
    const p0 = iy * w + ix, p1 = p0 + w;
    const w00 = (1 - tx) * (1 - ty), w10 = tx * (1 - ty);
    const w01 = (1 - tx) * ty, w11 = tx * ty;
    let r = bedR[p0] * w00 + bedR[p0 + 1] * w10 + bedR[p1] * w01 + bedR[p1 + 1] * w11 + sh;
    let g = bedG[p0] * w00 + bedG[p0 + 1] * w10 + bedG[p1] * w01 + bedG[p1 + 1] * w11 + sh;
    let b = bedB[p0] * w00 + bedB[p0 + 1] * w10 + bedB[p1] * w01 + bedB[p1 + 1] * w11 + sh;
    rgb[0] = r < 0 ? 0 : (r > 255 ? 255 : r);
    rgb[1] = g < 0 ? 0 : (g > 255 ? 255 : g);
    rgb[2] = b < 0 ? 0 : (b > 255 ? 255 : b);
}

/**
 * Where offshore weather strikes: a cell in the margin, never on the map.
 *
 * THE WHOLE POINT IS THAT IT IS NEVER VISIBLE. A strike inside the
 * sheet is a circle appearing in open water with a rim on it, which
 * the eye tracks straight back to a point: "a massive comet would have
 * to hit the ocean to create that effect". Out in the margin the same
 * strike is unseen and only its wavefront ever arrives.
 *
 * So this returns a cell on the ring of ocean OUTSIDE the visible
 * rectangle, and the harness checks that it cannot do otherwise --
 * that is the one property of this feature that a screenshot cannot
 * verify, because a strike that lands one pixel inside the frame looks
 * fine in a still and awful in motion.
 *
 * `rnd` is the world's own seeded generator, not Math.random: this map
 * has banned that since faction colours repainted on every load.
 */
function waterStormCell(rnd, w, h, pad, vw, vh) {
    if (pad < 3) return null;
    const pick = (n) => 1 + ((rnd() * Math.max(1, n - 2)) | 0);
    const side = (rnd() * 4) | 0;
    if (side === 0) return [pick(pad), pick(h)];                    /* west */
    if (side === 1) return [w - 1 - pick(pad), pick(h)];            /* east */
    if (side === 2) return [pick(w), pick(pad)];                    /* north */
    return [pick(w), h - 1 - pick(pad)];                            /* south */
}

let waterRAF = 0;
/* The pointer listener lives on #container, which survives a redraw,
   so it has to be taken off by hand or every rebuild adds another. */
let waterCleanup = null;

/* map.js rebuilds the whole map on resize and on every filter change; an
   orphaned loop would keep painting a canvas that has left the document. */
function stopWater() {
    if (waterRAF) { cancelAnimationFrame(waterRAF); waterRAF = 0; }
    if (waterCleanup) { waterCleanup(); waterCleanup = null; }
}

/**
 * Bake a distance field off the drawn coastline, then animate the surf.
 *
 * THE FIELD IS RASTERISED FROM THE COAST POLYGONS, NOT THE CELL GRID, so
 * the water lines up with the shoreline actually on screen rather than
 * with the grid it was traced from. Half a cell out is plainly visible
 * when the foam is three pixels wide.
 *
 * HALF RESOLUTION, STRETCHED BACK UP: a quarter of the pixels to touch
 * each frame, and the browser's bilinear upscale softens his hard edges
 * into something that suits a map better than it does pixel art.
 */
function waterLayer(container, world, svgW, svgH, box) {
    stopWater();
    if (typeof mwDistance !== 'function' || !world || !world.coast || !world.coast.length) return null;

    const SCALE = 0.5;
    const W = Math.max(1, Math.round(svgW * SCALE)), H = Math.max(1, Math.round(svgH * SCALE));

    const off = document.createElement('canvas');
    off.width = W; off.height = H;
    const octx = off.getContext('2d', { willReadFrequently: true });
    if (!octx) return null;
    octx.fillStyle = '#000';
    octx.fillRect(0, 0, W, H);
    octx.fillStyle = '#fff';
    octx.beginPath();
    for (const ring of world.coast) {
        for (let i = 0; i < ring.length; i++) {
            const x = ring[i].x * SCALE, y = ring[i].y * SCALE;
            if (i) octx.lineTo(x, y); else octx.moveTo(x, y);
        }
        octx.closePath();
    }
    /* nonzero, and the lake rings wind the other way, so they come out as
       holes in the land and get their own shoreline for nothing. */
    octx.fill('nonzero');

    const px = octx.getImageData(0, 0, W, H).data;
    const mask = new Uint8Array(W * H);
    for (let i = 0; i < mask.length; i++) mask[i] = px[i * 4] > 127 ? 1 : 0;

    /* In half-res cells, doubled back to screen pixels as it is read. */
    const dist = mwDistance(mask, W, H);

    /* Only the pixels the surf can ever reach are touched per frame. */
    const reach = (Math.max(WATER.foamMid + WATER.foamSwing, WATER.waveHi) + WATER.aa + 2) / 2;
    const live = [];
    for (let i = 0; i < dist.length; i++) if (dist[i] > 0 && dist[i] < reach) live.push(i);
    if (!live.length) return null;
    const idx = new Int32Array(live);

    const cv = document.createElement('canvas');
    cv.width = W; cv.height = H;
    cv.className = 'sea-surf';
    if (box) {
        cv.style.left = box.left + 'px';
        cv.style.top = box.top + 'px';
        cv.style.width = box.width + 'px';
        cv.style.height = box.height + 'px';
    }
    const ctx = cv.getContext('2d');
    if (!ctx) return null;
    container.appendChild(cv);

    /*
     * THE SIMULATION GRID: half the surf canvas again, a quarter of the
     * screen. Almeros's own notes on this algorithm say JavaScript is
     * too slow for it and to shrink the canvas and drop the resolution,
     * which is exactly what makes it affordable. A swell is low
     * frequency, so it loses nothing by being coarse, and the browser's
     * bilinear upscale does the smoothing for free.
     */
    const SD = Math.max(1, WATER.simDiv | 0);
    const PAD = Math.max(0, WATER.simPad | 0);
    /* The visible sheet in sim cells, and then the ocean around it. */
    const VW = Math.max(3, Math.ceil(W / SD)), VH = Math.max(3, Math.ceil(H / SD));
    const SW = VW + 2 * PAD, SH = VH + 2 * PAD;
    const simLand = new Uint8Array(SW * SH);
    for (let y = 0; y < VH; y++) {
        for (let x = 0; x < VW; x++) {
            const px = Math.min(W - 1, x * SD), py = Math.min(H - 1, y * SD);
            if (dist[py * W + px] <= 0) simLand[(y + PAD) * SW + (x + PAD)] = 1;
        }
    }
    /* Water, and off the outer ring: anything outside this list is
       never stepped, so anything left in one would sit there forever
       feeding its neighbours. */
    const wetL = [], wfx = [], wfy = [];
    for (let y = 1; y < SH - 1; y++) {
        for (let x = 1; x < SW - 1; x++) {
            if (simLand[y * SW + x]) continue;
            wetL.push(y * SW + x); wfx.push(x); wfy.push(y);
        }
    }
    if (!wetL.length) return null;
    const wetIdx = new Int32Array(wetL);
    const fX = new Int16Array(wfx), fY = new Int16Array(wfy);
    const force = swellField(SW, SH);
    let cur = new Float32Array(SW * SH), prev = new Float32Array(SW * SH);
    const lum = new Float32Array(SW * SH);

    /*
     * THE OPEN SEA, as pixels. Position and depth weight worked out
     * once: recovering x and y from a flat index inside the frame loop
     * is a divide and a modulo per pixel, a few hundred thousand times
     * a second, for two numbers that never change.
     */
    const surfEnd = reach * 2;
    const sis = [], sfs = [], sks = [], sxs = [], sys = [];
    for (let y = 0; y < H; y++) {
        for (let x = 0; x < W; x++) {
            const i = y * W + x;
            if (dist[i] <= 0) continue;                    /* land stays untouched */
            /*
             * EVERY water pixel is drawn now, not just the ones past
             * the surf. The canvas is opaque over the sea, so a pixel
             * it skipped would be a hole. The weight still fades to
             * nothing at the beach -- the surf owns that band, and
             * where the weight is zero the pixel is simply the bed,
             * which is exactly what the SVG underneath already shows.
             */
            sis.push(i); sxs.push(x); sys.push(y);
            sfs.push(smoothEdge(surfEnd, surfEnd * WATER.swellFade, dist[i] * 2));
            sks.push((Math.min(VH - 1, (y / SD) | 0) + PAD) * SW
                   + (Math.min(VW - 1, (x / SD) | 0) + PAD));
        }
    }
    const swI = new Int32Array(sis), swF = new Float32Array(sfs), swK = new Int32Array(sks);
    const swX = new Int16Array(sxs), swY = new Int16Array(sys);

    const little = new Uint8Array(new Uint32Array([0x11223344]).buffer)[0] === 0x44;
    const bed = seaBed(world, W, H, SCALE);
    if (!bed) return null;
    /* Split into planes once. The sampler reads four neighbours per
       pixel and unpacking a word four times costs more than three
       plane reads do. */
    const bedR = new Uint8Array(W * H), bedG = new Uint8Array(W * H), bedB = new Uint8Array(W * H);
    for (let i = 0; i < bed.length; i++) {
        const c = bed[i];
        if (little) { bedR[i] = c & 255; bedG[i] = (c >> 8) & 255; bedB[i] = (c >> 16) & 255; }
        else { bedR[i] = (c >>> 24) & 255; bedG[i] = (c >> 16) & 255; bedB[i] = (c >> 8) & 255; }
    }

    const rgb = new Uint8Array(3);   /* scratch for waterPixel */
    const frame = ctx.createImageData(W, H);
    const out = new Uint32Array(frame.data.buffer);
    const F = WATER.foam, V = WATER.wave;

    /*
     * Every live pixel is written every frame, including the empty ones,
     * so nothing is left over from the frame before.
     *
     * The foam edge and the crest shift depend only on t, so both are
     * hoisted out: a Math.sin per pixel would be thirty thousand of
     * them a frame for one number.
     */
    const DR = WATER.swellDrift;

    /*
     * PUSH, THEN LET IT SPREAD.
     *
     * Three scrolled reads of the forcing field, summed and scaled
     * right down, added to every wet cell. No cell gets enough in one
     * frame to be seen arriving; the wave equation accumulates it,
     * carries it, and reflects it off the coasts.
     */
    const driveAndStep = (t, damping) => {
        let ax = ((t * DR[0][0]) | 0) % SW, ay = ((t * DR[0][1]) | 0) % SH;
        let bx = ((t * DR[1][0]) | 0) % SW, by = ((t * DR[1][1]) | 0) % SH;
        let cx = ((t * DR[2][0]) | 0) % SW, cy = ((t * DR[2][1]) | 0) % SH;
        if (ax < 0) ax += SW; if (ay < 0) ay += SH;
        if (bx < 0) bx += SW; if (by < 0) by += SH;
        if (cx < 0) cx += SW; if (cy < 0) cy += SH;
        /* Per second, not per step: the drive is quoted at 60Hz like
           the damping, so changing simHz slows the sea down without
           also starving it. */
        const g = WATER.forceGain * (60 / SIM_HZ);
        for (let n = 0; n < wetIdx.length; n++) {
            const x = fX[n], y = fY[n];
            let xa = x + ax; if (xa >= SW) xa -= SW;
            let ya = y + ay; if (ya >= SH) ya -= SH;
            let xb = x + bx; if (xb >= SW) xb -= SW;
            let yb = y + by; if (yb >= SH) yb -= SH;
            let xc = x + cx; if (xc >= SW) xc -= SW;
            let yc = y + cy; if (yc >= SH) yc -= SH;
            cur[wetIdx[n]] +=
                (force[ya * SW + xa] + force[yb * SW + xb] + force[yc * SW + xc]) * g;
        }
        waterStep(cur, prev, wetIdx, SW, damping);
        const t2 = cur; cur = prev; prev = t2;
    };

    const paint = (t) => {
        const edge = foamEdgeAt(t), shift = t * WATER.speed;
        const RF = WATER.refract, LG = WATER.lightGain;

        /*
         * THE SEA, BENT. For each water pixel: read the slope of the
         * field at its cell, step that far across the bed, and take
         * the colour that is there. Nothing is mixed in and nothing is
         * laid over; the pixel is a sea pixel that has moved.
         *
         * The sample is clamped to the sheet and rejected if it lands
         * on land -- a crest near a coast would otherwise drag the
         * shoreline colour out into the water, which reads as the land
         * smearing rather than the water moving.
         *
         * Then Almeros's shading: a face tilted towards the light is
         * lifted a few levels, away from it dropped. Added to the
         * sampled colour, so the brightest water on the map is still
         * the bed's own brightest colour plus a little.
         */
        for (let n = 0; n < swI.length; n++) {
            const i = swI[n], k = swK[n], wgt = swF[n];
            const d = RF * wgt;
            const gx = cur[k - 1] - cur[k + 1];
            const gy = cur[k - SW] - cur[k + SW];
            /* waterPixel reads the bed between pixels and adds the
               shading; no land test, because seaBed() never draws the
               land rings and there is nothing to smear out of a coast. */
            waterPixel(bedR, bedG, bedB, W, H, swX[n], swY[n], gx, gy, d,
                       gy * LG * wgt, rgb);
            out[i] = little
                ? ((255 << 24) | (rgb[2] << 16) | (rgb[1] << 8) | rgb[0]) >>> 0
                : ((rgb[0] << 24) | (rgb[1] << 16) | (rgb[2] << 8) | 255) >>> 0;
        }

        for (let k = 0; k < idx.length; k++) {
            const i = idx[k], d = dist[i] * 2;
            const fa = surfFoam(d, edge) * F[3];
            const wa = surfWave(d, shift) * V[3];
            /* foam over wave, both over nothing */
            const under = wa * (1 - fa);
            const a = fa + under;
            if (a < 0.004) continue;          /* leave whatever the swell put there */
            let r = (F[0] * fa + V[0] * under) / a;
            let g = (F[1] * fa + V[1] * under) / a;
            let b = (F[2] * fa + V[2] * under) / a;
            /*
             * OVER THE SEA, NOT OVER NOTHING. The sea pixels are opaque
             * now -- the canvas IS the water -- so the surf has to be
             * composited onto what the pass above wrote. Left as a
             * translucent write it would punch the foam's own alpha
             * into the water and show the page through the breakers.
             */
            const u = out[i], k2 = a > 1 ? 1 : a, inv = 1 - k2;
            let ur, ug, ub;
            if (little) { ur = u & 255; ug = (u >> 8) & 255; ub = (u >> 16) & 255; }
            else { ur = (u >>> 24) & 255; ug = (u >> 16) & 255; ub = (u >> 8) & 255; }
            r = r * k2 + ur * inv; g = g * k2 + ug * inv; b = b * k2 + ub * inv;
            out[i] = little
                ? ((255 << 24) | ((b | 0) << 16) | ((g | 0) << 8) | (r | 0)) >>> 0
                : (((r | 0) << 24) | ((g | 0) << 16) | ((b | 0) << 8) | 255) >>> 0;
        }
        ctx.putImageData(frame, 0, 0);
    };

    const still = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
    /* Reduced motion gets a sea, not a flat colour: the field is run
       forward far enough to settle, then drawn once and left alone. */
    if (still) {
        const hz = Math.max(1, WATER.simHz || 60);
        for (let n = 0; n < 600; n++) {
            for (let q = WATER.stormsPerFrame; q > 0; q--) strike();
            driveAndStep(n / hz, Math.pow(WATER.damping, 60 / hz));
        }
        paint(0);
        return cv;
    }

    /*
     * THE MOTORBOAT. Nothing disturbs this sea on its own: the ambient
     * undulation comes from forcing nobody can see arriving, and the
     * only thing that leaves a mark is the reader's own pointer.
     *
     * The listener goes on the CONTAINER, not the canvas, because the
     * canvas is pointer-events:none so that it cannot swallow a realm
     * click. One wake a frame at most, or a slow drag lays down sixty.
     */
    let lastAt = 0, lastX = 0, lastY = 0, haveLast = false;
    const onMove = (ev) => {
        const now = (window.performance && performance.now) ? performance.now() : Date.now();
        if (now - lastAt < 16) return;
        const rc = cv.getBoundingClientRect();
        if (!rc.width || !rc.height) return;
        const x = ((ev.clientX - rc.left) / rc.width) * VW + PAD;
        const y = ((ev.clientY - rc.top) / rc.height) * VH + PAD;
        const dt = (now - lastAt) / 1000;
        let speed = 0;
        if (haveLast && dt > 0 && dt < 0.25) {
            speed = Math.sqrt((x - lastX) * (x - lastX) + (y - lastY) * (y - lastY)) / dt;
        }
        lastAt = now; lastX = x; lastY = y; haveLast = true;
        if (x < PAD || y < PAD || x >= PAD + VW || y >= PAD + VH) return;
        /* A still pointer leaves no wake; a brisk drag leaves a full one. */
        const drive = Math.min(1, speed / WATER.wakeSpeedRef);
        if (drive <= 0.02) return;
        waterWake(cur, simLand, SW, SH, x | 0, y | 0, WATER.wakeRadius,
                  WATER.wakeForce * drive);
    };
    container.addEventListener('pointermove', onMove, { passive: true });
    waterCleanup = () => { container.removeEventListener('pointermove', onMove); };

    /*
     * THE WEATHER, OUT PAST THE EDGE OF THE SHEET.
     *
     * Every strike lands in the margin, never on the map, so what a
     * reader sees is only ever the wavefront arriving. Positions come
     * from the world's own seeded generator, so the sea is the same
     * sea on every load -- Math.random() has been banned here since
     * faction colours repainted themselves between page views.
     */
    const stormRnd = (typeof mwRng === 'function')
        ? mwRng(((world.seed || 1) ^ 0x5eed10) >>> 0) : null;
    const strike = () => {
        if (!stormRnd) return;
        const at = waterStormCell(stormRnd, SW, SH, PAD, VW, VH);
        if (!at) return;
        waterWake(cur, simLand, SW, SH, at[0], at[1], WATER.stormRadius, WATER.stormForce);
    };

    /*
     * THE SEA KEEPS ITS OWN CLOCK, SLOWER THAN THE SCREEN.
     *
     * Stepped once per rendered frame, a wave crosses one cell every
     * sixteen milliseconds -- sixty cells a second -- and the whole
     * ocean hurries: "you've got a good undulation going, it's just
     * too fast and frantic to give the laid back water vibe". The
     * undulation was right; only its speed was wrong, and the speed of
     * a wave in this scheme is one cell per STEP. So the fix is to
     * take fewer steps per second, not to change anything about the
     * water.
     *
     * A fixed-rate accumulator does it: the field advances at simHz
     * whatever the display is doing, which also makes the sea run at
     * the same speed on a 120Hz screen as on a 60Hz one -- tied to the
     * frame rate it would have run at double speed there.
     *
     * DAMPING IS PER STEP, so it has to move with simHz or the sea
     * changes character as well as speed: a third as many steps a
     * second means a third as much decay, and the field rings on and
     * builds up. WATER.damping is therefore quoted for 60Hz and
     * rescaled here to preserve the decay PER SECOND, which is the
     * thing a reader can actually see.
     */
    const SIM_HZ = Math.max(1, WATER.simHz || 60);
    const STEP = 1 / SIM_HZ;
    const damp = Math.pow(WATER.damping, 60 / SIM_HZ);

    const t0 = (window.performance && performance.now) ? performance.now() : Date.now();
    let simT = 0, acc = 0, prevT = 0;
    const tick = (now) => {
        const t = (now - t0) / 1000;
        /* Clamped: a backgrounded tab comes back with a huge gap and
           would otherwise try to catch up in one frame. */
        acc += Math.min(0.25, t - prevT);
        prevT = t;
        while (acc >= STEP) {
            for (let n = WATER.stormsPerFrame; n > 0; n--) strike();
            driveAndStep(simT, damp);
            simT += STEP;
            acc -= STEP;
        }
        paint(t);
        waterRAF = requestAnimationFrame(tick);
    };
    waterRAF = requestAnimationFrame(tick);
    return cv;
}

/*
 * TEXTURED LAND: flecks and an edge fade.
 *
 * After SpriteCook's tileset generator (MIT), which builds its terrain
 * from seeded value noise plus "flecks / pixel clusters", and an "edge
 * fade" controlling how far a rim colour reaches in from the
 * silhouette. That tool draws tiles; this is one continent-shaped
 * polygon, so what carries over is the IDEA rather than any code: a
 * sparse scatter of specks to stop a large flat fill reading as a flat
 * fill, and a band of lighter ground just inside the shoreline.
 *
 * DONE AS SVG, NOT PIXELS, unlike the water. The water had to be
 * per-pixel because it moves and a contour cannot. The land never
 * moves, so a tiled <pattern> is drawn once by the browser, stays
 * crisp at any zoom, and costs nothing per frame.
 *
 * TWO TILES AT COPRIME SIZES. A single repeating tile across an
 * 1800px map repeats about thirteen times and the eye finds the grid
 * immediately. 137 and 89 are coprime, so the combined pattern only
 * repeats every 12,193px -- about seven map widths, which is to say
 * never.
 */
const LAND_TEX = {
    /*
     * SCATTERED OVER THE WHOLE SHEET, NOT TILED.
     *
     * This was two <pattern> tiles at coprime sizes, on the theory that
     * 137 and 89 only realign every 12,193px so the repeat would never
     * show. That reasoning was wrong: the COMBINED pattern repeats
     * rarely, but each layer still repeats on its own every 137px and
     * the eye locks onto a single layer's recurring cluster long before
     * it notices the two agreeing. Reported as "the flecking is too
     * predictable, I can identify the pattern across a landmass".
     *
     * So there is no tile. Each layer is one scatter across the whole
     * sheet, emitted as a single <path> of little squares -- one DOM
     * node per layer rather than thousands of <circle>s, and clipped to
     * the land by being the same shape as the land.
     *
     * CLUSTERED, NOT UNIFORM. Uniform random reads as television static,
     * which is just as recognisable as a grid. Real ground is patchy, so
     * flecks come in small groups: the harness measures the mean
     * nearest-neighbour distance against what a uniform scatter of the
     * same density would give, because "clustered" is otherwise a word
     * with no test attached to it.
     */
    layers: [
        { per1000: 1.39, group: 3, spread: 14, sMin: 0.9, sMax: 2.4, fill: 'rgba(7,22,38,0.30)' },
        { per1000: 1.77, group: 3, spread: 15, sMin: 0.7, sMax: 1.7, fill: 'rgba(132,178,208,0.16)' }
    ],
    /* The edge fade, as inner rims. A stroke centred on the shoreline
       and clipped to the land leaves exactly half its width inside, so
       these are 7px and 2.5px of shore. */
    rims: [
        { width: 14, stroke: 'rgba(96,150,182,0.18)' },
        { width: 5,  stroke: 'rgba(150,198,226,0.20)' }
    ]
};

/*
 * THE LAND HAS A SURFACE NOW, FOR THE SAME REASON THE SEA DOES.
 *
 * With the ocean moving, the territories read as flat paper laid on
 * top of it: "there's so much going on now with the water and all the
 * land mass is just flat space". The fix is the oldest trick in
 * cartography -- shaded relief. Build a height field over the land,
 * light it from one direction, and draw only the lighting.
 *
 * LIGHTING IS LUMINANCE ONLY, NEVER HUE. The faction colour is the
 * map's primary information and it arrives as a 22% wash over the
 * land, so anything that tints the terrain changes what a territory
 * looks like it belongs to. The relief is therefore white where a
 * slope faces the light and black where it faces away, both at low
 * alpha: it darkens and lightens, and every hue on the map survives
 * it untouched.
 *
 * AND IT GOES UNDER THE WASH, not over it. Painted on top it is a
 * grey film lying across the colours; underneath, each faction's wash
 * tints its own hills and the relief belongs to the ground.
 *
 * THE TERRAIN IGNORES THE BORDERS, which is the whole point of
 * putting it in. Mountains are older than politics, so the height
 * field is built across the landmass and the territories are drawn on
 * top of whatever geography they happen to contain. A per-faction
 * treatment would turn the relief back into a second way of drawing
 * the same borders.
 *
 * LIT FROM THE NORTH-WEST, the same direction as the water's own
 * shading. Two light sources on one picture is the fastest way to
 * make a scene look assembled rather than seen.
 */
const LAND_RELIEF = {
    /*
     * COASTS ARE FLAT. Height is multiplied by a ramp that is zero at
     * the shoreline, so hills start inland and the beach stays level.
     * Without it the relief fights the shore animation and the rims
     * for the same pixels, and a mountain that runs straight into the
     * sea at full height reads as a cliff round the entire coastline.
     */
    /*
     * COASTS ARE FLAT -- AND ON THIS WORLD THAT RAMP IS ALSO THE
     * VOLUME KNOB, WHICH IS WORTH KNOWING BEFORE TOUCHING IT.
     *
     * Height is multiplied by a ramp that is zero at the shoreline,
     * so hills start inland and the beach stays level. But measured
     * on the real world, no land pixel is more than 26px from water
     * at half scale and the median is 6: these territories are
     * ribbons, not continents, so the ramp never reaches 1 anywhere
     * and holds the whole relief to roughly a tenth of its nominal
     * height.
     *
     * That gentleness is the look that was chosen, out of several
     * passes. Cutting the ramp to suit the ribbons -- the obviously
     * "correct" fix -- turns the relief up by a factor of ten and
     * gives crisp rocky terrain, which was rejected beside water this
     * quiet. So this number controls amplitude far more than it
     * controls the beach, and shade has to move with it.
     */
    plainPx: 30,
    /*
     * HILLS AND RIDGES, MIXED. Plain fBm gives rounded lumps -- hills,
     * but no mountains, because a mountain range has a CREST and
     * smooth noise has none. Ridged noise (1 - |2n-1|) folds the
     * field at its midline, which turns every zero crossing into a
     * sharp line, and those lines chain up into ranges. Neither is
     * right alone: all fBm is a golf course, all ridges is crumpled
     * foil.
     */
    cell: 104, octaves: 4, ridgeMix: 0.46, gamma: 1.35,
    /* The lighting. shade is the slope term and is what you actually
       see; height lifts the high ground a touch on top of it, which
       keeps a long even slope from disappearing. */
    /*
     * shade is large because it is a gradient PER PIXEL: the height
     * field runs 0..1 across cells about a hundred pixels wide, so a
     * typical slope is around a hundredth, and a gain near 1 draws a
     * peak alpha of 11/255 -- which is nothing. It scales with cell:
     * halve the cell and the same terrain is twice as steep.
     */
    light: [-0.7071, -0.7071], shade: 22, height: 0.12,
    lit: [214, 234, 246], dark: [2, 8, 16],
    litMax: 0.30, darkMax: 0.34
};

/**
 * The height field over the land, 0..1, zero everywhere that is sea.
 *
 * `mask` is 1 for land. Pure, and the shape of the terrain is decided
 * entirely here, so the harness can ask the questions that matter --
 * does the coast stay flat, is there anything inland, is it the same
 * terrain every visit -- against a mask it built itself.
 */
function landHeightField(mask, w, h, seed, cfg) {
    cfg = cfg || LAND_RELIEF;
    const out = new Float32Array(w * h);
    if (typeof mwDistance !== 'function' || typeof mwNoise !== 'function') return out;

    /* Depth inland. mwDistance zeroes the cells you give it and grows
       outward, so handing it the SEA gives every land pixel its
       distance from the nearest shore -- in chamfer units, where an
       orthogonal step is 3. */
    const sea = new Uint8Array(w * h);
    for (let i = 0; i < mask.length; i++) sea[i] = mask[i] ? 0 : 1;
    const inland = mwDistance(sea, w, h);

    /*
     * EVERY OCTAVE ON ITS OWN AXIS, AND ITS OWN SEED.
     *
     * Stacked on one lattice this produced diagonal hatching across
     * whole territories rather than hills. Value noise on a square
     * grid has axis- and diagonal-aligned structure; summing octaves
     * that share the lattice lines them all up, the ridge fold turns
     * every one of those lines into a crease, and lighting the result
     * from 45 degrees -- straight down the lattice diagonal -- shows
     * them off as well as it possibly could.
     *
     * So each octave is rotated by an angle that is no fraction of a
     * right angle and offset by an unrelated amount, and draws from
     * its own hash. Nothing lines up with anything, and the creases
     * go back to being ridges that wander.
     */
    const oct = Math.max(1, cfg.octaves | 0);
    const fbN = [], rgN = [], rc = [], rs = [], ro = [];
    for (let o = 0; o < oct; o++) {
        fbN.push(mwNoise((seed ^ Math.imul(o + 1, 0x9e3779b9)) >>> 0));
        rgN.push(mwNoise((seed ^ Math.imul(o + 1, 0x85ebca6b) ^ 0x51ed) >>> 0));
        const th = 0.683 * (o + 1);
        rc.push(Math.cos(th)); rs.push(Math.sin(th));
        ro.push([o * 37.13, o * 19.77]);
    }
    /* Normaliser, so cell/octaves can be retuned without the height
       silently leaving 0..1 and clipping every peak to the same flat
       white. */
    let norm = 0;
    for (let o = 0; o < oct; o++) norm += Math.pow(0.5, o);

    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = y * w + x;
            if (!mask[i]) continue;
            const deep = inland[i] / 3;
            if (deep <= 0) continue;
            const ramp = smoothEdge(0, cfg.plainPx, deep);
            let fb = 0, rg = 0, amp = 1, cell = cfg.cell;
            for (let o = 0; o < oct; o++) {
                const xr = (x * rc[o] - y * rs[o]) / cell + ro[o][0];
                const yr = (x * rs[o] + y * rc[o]) / cell + ro[o][1];
                fb += fbN[o](xr, yr) * amp;
                /* Folded at the midline: the crease is the ridge. */
                rg += (1 - Math.abs(2 * rgN[o](xr, yr) - 1)) * amp;
                amp *= 0.5; cell *= 0.5;
            }
            fb /= norm; rg /= norm;
            let v = fb * (1 - cfg.ridgeMix) + rg * cfg.ridgeMix;
            out[i] = Math.pow(v < 0 ? 0 : (v > 1 ? 1 : v), cfg.gamma) * ramp;
        }
    }

    return out;
}

/**
 * Hillshade: how much each land pixel faces the light, -1..1.
 *
 * The same arithmetic the water uses on its own field -- the surface
 * normal of a height field is (-dh/dx, -dh/dy, 1) and this is that
 * dotted with the light -- which is why the two read as one scene.
 *
 * Differences are taken against the pixel itself at a coast rather
 * than against the sea, so the shoreline does not light up as one
 * enormous cliff edge: the sea is not lower ground, it is not ground.
 */
function reliefShade(height, mask, w, h, lx, ly, gain, hGain) {
    const out = new Float32Array(w * h);
    for (let y = 0; y < h; y++) {
        for (let x = 0; x < w; x++) {
            const i = y * w + x;
            if (!mask[i]) continue;
            const l = (x > 0 && mask[i - 1]) ? height[i - 1] : height[i];
            const r = (x < w - 1 && mask[i + 1]) ? height[i + 1] : height[i];
            const u = (y > 0 && mask[i - w]) ? height[i - w] : height[i];
            const d = (y < h - 1 && mask[i + w]) ? height[i + w] : height[i];
            const v = -((r - l) * lx + (d - u) * ly) * gain + height[i] * hGain;
            out[i] = v < -1 ? -1 : (v > 1 ? 1 : v);
        }
    }
    return out;
}

/**
 * The relief, as an <image> to slide under the territory washes.
 *
 * Rendered at half the sheet and upscaled, like the water: this is
 * all low-frequency shading and the browser's bilinear is free
 * smoothing. Returns null rather than throwing if there is no canvas
 * to draw on, because the map without relief is still a map.
 */
function landReliefImage(world, svgW, svgH) {
    if (!world || !world.coast || !world.coast.length) return null;
    if (typeof mwDistance !== 'function') return null;
    const SCALE = 0.5;
    const w = Math.max(1, Math.round(svgW * SCALE)), h = Math.max(1, Math.round(svgH * SCALE));

    const cv = document.createElement('canvas');
    cv.width = w; cv.height = h;
    const g = cv.getContext('2d', { willReadFrequently: true });
    if (!g) return null;
    g.fillStyle = '#000';
    g.fillRect(0, 0, w, h);
    g.fillStyle = '#fff';
    g.beginPath();
    for (const ring of world.coast) {
        for (let i = 0; i < ring.length; i++) {
            const x = ring[i].x * SCALE, y = ring[i].y * SCALE;
            if (i) g.lineTo(x, y); else g.moveTo(x, y);
        }
        g.closePath();
    }
    /* nonzero, so the lakes stay holes -- the same rule the coast is
       filled with everywhere else, and it keeps terrain out of them. */
    g.fill('nonzero');

    const px = g.getImageData(0, 0, w, h);
    const mask = new Uint8Array(w * h);
    for (let i = 0; i < mask.length; i++) mask[i] = px.data[i * 4] > 127 ? 1 : 0;

    const C = LAND_RELIEF;
    const height = landHeightField(mask, w, h, ((world.seed || 1) ^ 0x1a4d) >>> 0, C);
    const shade = reliefShade(height, mask, w, h, C.light[0], C.light[1], C.shade, C.height);

    const img = g.createImageData(w, h);
    const d = img.data;
    const L = C.lit, D = C.dark;
    for (let i = 0; i < mask.length; i++) {
        if (!mask[i]) continue;
        const v = shade[i];
        const up = v > 0;
        const a = (up ? v : -v) * (up ? C.litMax : C.darkMax);
        if (a < 0.004) continue;
        const c = up ? L : D;
        const o = i * 4;
        d[o] = c[0]; d[o + 1] = c[1]; d[o + 2] = c[2];
        d[o + 3] = (a * 255) | 0;
    }
    g.putImageData(img, 0, 0);
    return cv.toDataURL('image/png');
}

/**
 * One layer's flecks over a whole w x h sheet, as plain data.
 *
 * Returned rather than drawn so the harness can measure the things that
 * actually matter about a texture: that it is the same ground on every
 * visit, that it does not repeat, and that it clumps.
 */
function fleckField(seed, w, h, spec) {
    const rnd = mwRng(seed);
    const total = Math.round((w * h / 1000) * spec.per1000);
    const groups = Math.max(1, Math.round(total / spec.group));
    const out = [];
    for (let g = 0; g < groups; g++) {
        const cx = rnd() * w, cy = rnd() * h;
        for (let n = 0; n < spec.group && out.length < total; n++) {
            /* Two uniforms summed lands more of the group near its
               centre than a single one does, so a clump reads as a
               clump rather than as a square of confetti. */
            const dx = (rnd() + rnd() - 1) * spec.spread;
            const dy = (rnd() + rnd() - 1) * spec.spread;
            const sz = spec.sMin + rnd() * (spec.sMax - spec.sMin);
            const x = cx + dx, y = cy + dy;
            if (x < 0 || y < 0 || x + sz > w || y + sz > h) continue;
            out.push({ x: x, y: y, s: sz });
        }
    }
    return out;
}

/**
 * Append the land texture: the rims first, then the flecks over them.
 *
 * Everything is the SAME path as the land fill, so it is clipped to the
 * coastline for free and the lakes stay holes -- no clip-path needed
 * for the flecks, and the rims get one because a stroke straddles the
 * line it follows.
 */
function paintLandTexture(svg, defs, world, d, w, h) {
    if (!world.coast || !world.coast.length || typeof mwRng !== 'function') return 0;
    const seed = world.seed || 1;
    let drawn = 0;

    const clipId = 'sk-land-clip';
    const clip = svgEl('clipPath', { id: clipId });
    clip.appendChild(svgEl('path', { d: d }));
    defs.appendChild(clip);

    /* The shore band. Half of each stroke falls outside the coastline
       and is clipped away, which is the point: it fades inward only. */
    for (const rim of LAND_TEX.rims) {
        svg.appendChild(svgEl('path', {
            d: d, fill: 'none', stroke: rim.stroke, 'stroke-width': String(rim.width),
            'stroke-linejoin': 'round', 'clip-path': 'url(#' + clipId + ')'
        }));
        drawn++;
    }

    LAND_TEX.layers.forEach((spec, n) => {
        let path = '';
        for (const f of fleckField(seed + n * 7919, w, h, spec)) {
            const x = f.x.toFixed(1), y = f.y.toFixed(1), s = f.s.toFixed(1);
            path += 'M' + x + ',' + y + 'h' + s + 'v' + s + 'h-' + s + 'Z';
        }
        if (!path) return;
        svg.appendChild(svgEl('path', {
            d: path, fill: spec.fill, 'clip-path': 'url(#' + clipId + ')'
        }));
        drawn++;
    });
    return drawn;
}

/* Several rings as ONE path, so opposite-wound rings read as holes under
   the default nonzero rule. A lake is a ring wound the other way. */
function toPathAll(rings) {
    let d = '';
    for (const r of rings) d += toPath(r);
    return d;
}

function seaLayers(world) {
    const SHADE = ['rgba(120,200,230,0.26)', 'rgba(32,84,116,0.55)',
                   'rgba(22,62,92,0.5)',     'rgba(16,48,74,0.42)'];
    const layers = [];
    if (!world) return layers;
    /*
     * THE STATIC SHELF ONLY. The foam and the waves used to be here as
     * cross-faded contours; they are a canvas now (waterLayer above),
     * because a fixed contour cannot move and fading between fixed
     * shapes is not movement. This half never moves and SVG draws it
     * perfectly well.
     */
    if (world.shores) {
        for (let b = world.shores.length - 1; b >= 0; b--) {
            const fill = SHADE[b] || SHADE[SHADE.length - 1];
            for (const ring of world.shores[b].rings) layers.push({ ring: ring, fill: fill, band: b });
        }
    }
    /*
     * THE LAND IS ONE PATH, NOT ONE PATH PER RING.
     *
     * Every ring used to be its own <path>, and SVG fills a closed
     * subpath whatever direction it is wound -- so the lake rings, which
     * the geometry produces wound the other way precisely so they read
     * as holes, were being filled with the land colour. Two lakes of
     * 3,786 and 2,053px2 painted over as dry land on a 16-faction world.
     * All the rings in one path with the default nonzero rule makes them
     * holes again, which is what they already were everywhere else: the
     * distance field rasterises the coast exactly this way, so the water
     * knew about the lakes while the land did not.
     */
    if (world.coast && world.coast.length) {
        /* AND NO STROKE ROUND IT: "it doesn't let the animation breathe
           and the shore animation itself acts as the border line". The
           foam is the better edge because it moves. */
        layers.push({ rings: world.coast, fill: 'rgba(18,54,82,0.95)', band: -1 });
    }
    return layers;
}

function worldLayout(fdata, w, h) {
    if (typeof mwWorld !== 'function') return null;
    let world;
    try {
        world = mwWorld(fdata.map(f => ({ name: f.name, weight: Math.max(1, f.count) })), w, h);
    } catch (e) {
        console.warn('map.js: world generation failed, falling back to packing', e);
        return null;
    }
    if (!world || !world.regions || !world.regions.length) return null;

    const byName = {};
    fdata.forEach(f => { byName[f.name] = f; });
    const placedWithPoly = [];
    for (const r of world.regions) {
        const f = byName[r.name];
        if (!f) continue;
        let x0 = Infinity, y0 = Infinity, x1 = -Infinity, y1 = -Infinity;
        for (const q of r.poly) {
            if (q.x < x0) x0 = q.x; if (q.x > x1) x1 = q.x;
            if (q.y < y0) y0 = q.y; if (q.y > y1) y1 = q.y;
        }
        placedWithPoly.push({ faction: f, poly: r.poly, polys: r.polys,
            borders: r.borders,
            x: x0, y: y0, w: x1 - x0, h: y1 - y0 });
    }
    /* A faction with no land is a faction nobody can click. Better to draw
       the old map than to drop somebody off the world. */
    if (placedWithPoly.length !== fdata.length) return null;
    /* EVERYTHING THE RENDERER DRAWS HAS TO COME THROUGH HERE. This builds
       a NEW object rather than passing mwWorld's through, so a layer added
       to the generator and not added to this line is computed on every
       load and silently dropped -- which is exactly what happened to the
       waves and the foam, and shipped. */
    return { placedWithPoly: placedWithPoly, coast: world.coast, shores: world.shores,
             seed: world.seed };
}

/*
 * HOW WIDE THE WORLD IS COMPOSED.
 *
 * NOT window.innerWidth, WHICH IS WHAT THIS SHIPPED AS. The page content
 * lives inside `.container`, which flexbox.css caps at 2000px and centres
 * -- so on anything wider the map was drawn to the full viewport and ran
 * out past the header on both sides. The old packer could use innerWidth
 * and get away with it because its width came from the blocks it managed
 * to fit; a continent fills whatever canvas it is given, so the canvas has
 * to be the right size.
 *
 * MEASURED OFF THE NAVBAR, which is inside `.container` and is the width
 * the owner actually means by "the header". It cannot be measured off
 * #container: renderMap() runs at page load while #map is display:none, and
 * a hidden subtree reports zero -- the same trap that made getBBox() return
 * nothing here, which is written up a few lines further down.
 *
 * Every fallback is a real width rather than a guess that could be zero: a
 * zero canvas means no land, no regions, and worldLayout() handing back
 * null forever.
 */
function composeWidth() {
    if (window.innerWidth <= MOBILE_BP) return MOBILE_DESIGN_W;
    const probe = document.getElementById('navbar')
               || document.querySelector('.navbar')
               || document.querySelector('.container');
    const w = (probe && probe.clientWidth) ? probe.clientWidth
            : Math.min(window.innerWidth, 2000);
    return Math.max(900, w - MOBILE_GUTTER);
}

/*
 * AND HOW TALL, SO THE WHOLE WORLD IS ON ONE SCREEN.
 *
 * A map you scroll is a map you navigate instead of reading, and a
 * continent's shape is the first thing it has to say. The old packer grew
 * downwards without limit -- 52 realms came out 2062px tall -- which was
 * tolerable for a stack of blocks and is not for a place.
 *
 * DESKTOP is direct: the SVG carries concrete width and height, so the
 * composed height IS the drawn height and it only has to clear the chrome
 * above it.
 *
 * MOBILE IS NOT. map.css lets the browser scale the SVG down to the column
 * (max-width:100% over a viewBox), so the drawn height is the composed
 * height times that scale. Composing to the raw viewport there would give
 * a map a third of a screen tall. The composed height is divided back
 * through the scale, which is the same reasoning MOBILE_DESIGN_W already
 * rests on.
 *
 * MEASURED OFF THE NAVBAR for the same reason composeWidth() is, and with
 * the same floor: #map is display:none at render time and a hidden subtree
 * measures zero.
 */
function composeHeight(worldW) {
    const vh = window.innerHeight || 800;
    const nav = document.getElementById('navbar');
    const navH = (nav && nav.offsetHeight) ? nav.offsetHeight : 64;
    /* The realms tab row, #container-wrapper's 20px of padding and a little
       breathing room under the map. Measured rather than guessed would mean
       measuring hidden elements, which returns zero. */
    const CHROME = 128;
    const avail = Math.max(360, vh - navH - CHROME);
    if (window.innerWidth > MOBILE_BP) return Math.round(avail);
    const scale = Math.max(0.12, (window.innerWidth - MOBILE_GUTTER) / worldW);
    return Math.max(500, Math.round(avail / scale));
}

function renderMap() {
    const container = document.getElementById('container');
    const fdata = buildFactionData();

    const worldW = composeWidth();
    /* The world FILLS the box rather than keeping a fixed ratio: the coast
       fitter preserves the continent's own proportions inside whatever it
       is given (one scale factor, the smaller of the two), so a wide short
       canvas yields a centred continent and not a squashed one. */
    const worldH = composeHeight(worldW);
    const world = worldLayout(fdata, worldW, worldH);

    const placed = world ? [] : packFactions(fdata);

    let svgW = 0, svgH = 0;
    if (world) {
        svgW = worldW; svgH = worldH;
    } else {
        for (const p of placed) {
            svgW = Math.max(svgW, p.x+p.w+PAD);
            svgH = Math.max(svgH, p.y+p.h+PAD);
        }
    }

    /*
     * WHAT IS DRAWN OUTSIDE THE PACKED BLOCKS, tracked as we draw it.
     *
     * svgW/svgH above are the union of the faction BLOCKS, and two things
     * are deliberately drawn outside them: a faction's name pill sits at
     * `y - 17`, above its territory and centred on the territory's width
     * (so a long name on a block at x=0 hangs off the left as well), and a
     * marker's rings extend past its radius.
     *
     * THIS WAS getBBox() AND THAT WAS WRONG. getBBox returns all zeros for
     * an element inside a display:none subtree -- verified -- and
     * renderMap() runs at page load, when #map is hidden. So it silently
     * measured nothing and the box came out with only the slack, which is
     * why the top row of labels was still cut off under the nav. These
     * numbers are computed from the same geometry the shapes are drawn
     * from, so they do not care whether anything is laid out yet.
     */
    let inkX0 = 0, inkY0 = 0, inkX1 = svgW, inkY1 = svgH;
    const ink = (x0, y0, x1, y1) => {
        if (x0 < inkX0) inkX0 = x0;
        if (y0 < inkY0) inkY0 = y0;
        if (x1 > inkX1) inkX1 = x1;
        if (y1 > inkY1) inkY1 = y1;
    };

    const svg = svgEl('svg', {width:svgW, height:svgH, viewBox:`0 0 ${svgW} ${svgH}`});
    const defs = svgEl('defs');
    svg.appendChild(defs);

    // Glow filter for territory borders
    const glow = svgEl('filter', {id:'glow', x:'-25%', y:'-25%', width:'150%', height:'150%'});
    const glowBlur = svgEl('feGaussianBlur', {in:'SourceGraphic', stdDeviation:'3.5', result:'blur'});
    const glowMerge = svgEl('feMerge');
    glowMerge.appendChild(svgEl('feMergeNode', {in:'blur'}));
    glowMerge.appendChild(svgEl('feMergeNode', {in:'SourceGraphic'}));
    glow.appendChild(glowBlur);
    glow.appendChild(glowMerge);
    defs.appendChild(glow);

    // Drop shadow filter for avatar rings
    const shadow = svgEl('filter', {id:'dropshadow', x:'-30%', y:'-30%', width:'160%', height:'160%'});
    shadow.appendChild(svgEl('feDropShadow', {dx:'0', dy:'2', stdDeviation:'3', 'flood-color':'rgba(0,0,0,0.7)'}));
    defs.appendChild(shadow);

    // Arrowhead marker — fill:context-stroke inherits the line's stroke color
    const arrowMarker = svgEl('marker', {id:'raid-arrow', markerWidth:'8', markerHeight:'6', refX:'8', refY:'3', orient:'auto'});
    arrowMarker.appendChild(svgEl('path', {d:'M0,0 L0,6 L8,3 z', fill:'context-stroke'}));
    defs.appendChild(arrowMarker);

    // Pre-compute polygon for each placed faction using seeded RNG for stable shapes
    const placedWithPoly = world ? world.placedWithPoly : placed.map(p => {
        const rng = mulberry32(hashStr(p.faction.name + '_territory'));
        const inset = PAD * 0.6;
        const jitter = Math.min(20, Math.min(p.w, p.h) * 0.09);
        const poly = chaikin(jitterRect(p.x+inset, p.y+inset, p.w-inset*2, p.h-inset*2, jitter, rng), 3);
        return Object.assign({}, p, { poly: poly });
    });

    /*
     * THE SEA AND THE COAST, UNDER EVERYTHING.
     *
     * Drawn first so every territory sits on land. The land fill is a
     * shade off the page ground rather than a colour of its own: the
     * point is that the map has an edge, not that the sea is blue.
     */
    if (world && world.coast && world.coast.length) {
        /* THE SEA IS THE WHOLE SHEET. A map is a rectangle with geography
           in it; the first version drew one island centred in the frame
           and that is exactly what it looked like. */
        svg.appendChild(svgEl('rect', { x: 0, y: 0, width: svgW, height: svgH,
            fill: 'rgba(10,32,52,0.55)' }));
        /* Drawn from seaLayers(), which is pure and is RUN by the
           harness -- a source check cannot tell a band that is drawn
           from one behind `if (false)`. */
        for (const L of seaLayers(world)) {
            const attrs = { d: L.rings ? toPathAll(L.rings) : toPath(L.ring), fill: L.fill };
            if (L.stroke) {
                attrs.stroke = L.stroke;
                attrs['stroke-width'] = String(L.width || 1.6);
                attrs['stroke-linejoin'] = 'round';
            }
            if (L.cls) attrs.class = L.cls;
            const el = svgEl('path', attrs);
            /* Per-ring phase. The keyframes live in map.css behind a
               prefers-reduced-motion guard; this only says WHEN. */
            if (L.delay) el.style.animationDelay = L.delay;
            svg.appendChild(el);
            /* The land is the last sea layer; its texture goes straight
               on top, under the territory washes that follow. */
            if (L.band === -1 && L.rings) {
                /* Relief first, flecks on top of it: the flecks are
                   ground cover and belong on the hills, not under
                   them. Both sit under the territory washes. */
                const relief = landReliefImage(world, svgW, svgH);
                if (relief) {
                    svg.appendChild(svgEl('image', {
                        x: 0, y: 0, width: svgW, height: svgH,
                        href: relief, 'image-rendering': 'auto',
                        preserveAspectRatio: 'none', 'pointer-events': 'none'
                    }));
                }
                paintLandTexture(svg, defs, world, attrs.d, svgW, svgH);
            }
        }
    }

    const R = 22;
    let clipIdx = 0;

    // ── Layer 1: territory fills ──────────────────────────────────────────────
    const LOGO_MAX = 96; // max natural size cap in px
    for (const {faction, poly, polys, borders} of placedWithPoly) {
        /* EVERY PIECE A FACTION HOLDS. polys is the body plus its offshore
           islands; the old layout had one shape per faction and this read
           `poly` only, which silently dropped the islands -- the most
           characterful thing the terrain produces. */
        for (const piece of (polys && polys.length ? polys : [poly])) {
            svg.appendChild(svgEl('path', {
                d: toPath(piece),
                /* Up from 0.13: the territory used to be outlined all the
                   way round, so a faint wash was enough to say whose it
                   was. With only the inland border drawn, the FILL has to
                   carry it. */
                fill: hexToRgba(faction.color, 0.22),
                stroke: 'none'
            }));
        }

        /*
         * THE BORDER IS ONLY WHERE TWO FACTIONS MEET.
         *
         * This used to stroke the whole territory outline, so every
         * seaward edge carried the shoreline, this dashed line AND a glow
         * on the same path -- reported as "a white boundary line wrapping
         * the terrain, garbled between the factions", which is what three
         * strokes on one line look like. The coast is the coast now, and
         * the border stops when it reaches it.
         *
         * NO GLOW EITHER. At 2px with a 3.5 blur under it, a border that
         * wanders is a smear; the dashes already say "political line".
         */
        for (const line of (borders || [])) {
            if (line.length < 2) continue;
            svg.appendChild(svgEl('path', {
                d: 'M' + line.map(q => q.x.toFixed(1) + ' ' + q.y.toFixed(1)).join('L'),
                fill: 'none',
                stroke: faction.color,
                'stroke-width': '1.8',
                'stroke-dasharray': '9,6',
                'stroke-linejoin': 'round',
                'stroke-linecap': 'round',
                opacity: '0.9'
            }));
        }

        // Project logo watermark centered in territory
        if (faction.currency) {
            const c = centroid(poly);
            const logoImg = svgEl('image', {
                href: 'icons/' + faction.currency + '.png',
                x: c.x - LOGO_MAX/2, y: c.y - LOGO_MAX/2,
                width: LOGO_MAX, height: LOGO_MAX,
                opacity: '0.13',
                'pointer-events': 'none',
                preserveAspectRatio: 'xMidYMid meet'
            });
            svg.appendChild(logoImg);
        }
    }

    /*
     * EVERYTHING FROM HERE UP GOES ABOVE THE WATER.
     *
     * The surf is a canvas and a canvas cannot be interleaved into an
     * SVG, so it is laid over the whole map -- which was fine while it
     * only ever painted water, and is not fine at all for a marker or a
     * faction label sitting on a coast: "the tide animation covers both
     * avatars and faction labels, making them hard to see."
     *
     * So the drawing is split in two. Everything below this line is
     * terrain and belongs under the water; everything above it is
     * furniture -- raid lines, realm markers, faction labels -- and is
     * moved into a second SVG stacked on top of the canvas once the
     * viewBox is settled. Same viewBox, same box, so the coordinates do
     * not move.
     */
    const overlayFrom = svg.childNodes.length;

    // ── Layer 2.5: raid lines group (populated after markers are placed) ────────
    const linesGroup = document.createElementNS(NS, 'g');
    svg.appendChild(linesGroup);

    // ── Layer 3: realm markers ────────────────────────────────────────────────
    const realmPositions = {}; // realm_id → {x, y, color}
    const markerGroups   = {}; // realm_id → <g> element

    // Attackers this month, for the marker glow.
    //
    // There was a second set here -- attackers AND defenders -- that decided
    // whether a marker showed its owner's avatar or a skull. Every realm shows
    // its avatar now, so it had no readers left; a set that is still built and
    // never read is the kind of thing that gets "restored" later by someone
    // assuming it mattered.
    const activeAttackers = new Set(); // attackers only (used for glow)
    for (const pair of (window.raidPairs || [])) {
        activeAttackers.add(String(pair[0]));
    }
    for (const {faction, x, y, w, h, poly} of placedWithPoly) {
        const rng = mulberry32(hashStr(faction.name + '_markers'));
        const positions = placeMarkers(poly, faction.count, {x, y, w, h}, R, rng);

        for (let i = 0; i < faction.realms.length; i++) {
            const realm = faction.realms[i];
            const pos = positions[i] || centroid(poly);
            const idx = clipIdx++;
            const Rr = R + realm.avg_level; // grow marker by average location level
            /* The rings first: +11 is the own-realm ring, +3 its stroke. */
            ink(pos.x - Rr - 14, pos.y - Rr - 14, pos.x + Rr + 14, pos.y + Rr + 14);
            /* THEN THE TWO LABELS UNDER IT, which are the real reach. They
               are text-anchor:middle at 10px Arial, so a long username
               extends further left of a marker than any ring does -- the
               widest ink on the whole map turned out to be a realm name,
               not a faction pill. Measured, because it depends entirely on
               the name. The +26 is the second line's baseline and 4 is its
               halo stroke. */
            const nameW = Math.max(
                measureTextWidth(realm.user_name || '', 10, 'Arial, sans-serif', 'normal', '0'),
                measureTextWidth(realm.realm_name || '', 10, 'Arial, sans-serif', 'normal', '0'));
            ink(pos.x - nameW / 2 - 4, pos.y, pos.x + nameW / 2 + 4, pos.y + Rr + 26 + 4);

            // Circular clip for avatar
            const cp = svgEl('clipPath', {id:`ac${idx}`});
            cp.appendChild(svgEl('circle', {cx:pos.x, cy:pos.y, r:Rr}));
            defs.appendChild(cp);

            if (realm.realm_id) {
                realmPositions[realm.realm_id] = {x: pos.x, y: pos.y, color: faction.color, r: Rr};
            }

            const g = document.createElementNS(NS, 'g');
            g.style.cursor = 'pointer';

            if (realm.realm_id) markerGroups[realm.realm_id] = g;

            // Soft glow halo — pulsing ring for active raiders
            const isActiveRaider = realm.realm_id && activeAttackers.has(realm.realm_id);
            const isOwnRealm     = window.myRealmId && realm.realm_id && parseInt(realm.realm_id) === window.myRealmId;
            const halo = svgEl('circle', {
                cx:pos.x, cy:pos.y, r:Rr+5,
                fill: hexToRgba(faction.color, isActiveRaider ? 0.35 : 0.18),
                stroke: isActiveRaider ? faction.color : 'none',
                'stroke-width': isActiveRaider ? '2' : '0',
                'stroke-opacity': '0.7'
            });
            if (isActiveRaider) halo.classList.add('marker-active-glow');
            g.appendChild(halo);

            // Gold pulsing outer ring for the logged-in user's own realm
            if (isOwnRealm) {
                const ownRing = svgEl('circle', {
                    cx:pos.x, cy:pos.y, r:Rr+11,
                    fill: 'none',
                    stroke: '#FFD700',
                    'stroke-width': '2.5',
                });
                ownRing.classList.add('marker-own-glow');
                g.appendChild(ownRing);
            }

            // EVERY REALM SHOWS ITS OWNER'S AVATAR. This used to show a skull
            // for any realm that had not raided this month, so the map read as
            // "who fought" -- but a realm is only on the map at all because it
            // has been raided recently enough to qualify, and a field of skulls
            // reads as broken images rather than as a signal. The raid
            // highlighting it doubled up on is already carried by the glow and
            // the connecting lines.
            const img = svgEl('image', {
                href: realm.user_image,
                x: pos.x-Rr, y: pos.y-Rr,
                width: Rr*2, height: Rr*2,
                'clip-path': `url(#ac${idx})`,
                preserveAspectRatio: 'xMidYMid slice'
            });
            img.addEventListener('error', function() {
                this.setAttribute('href', 'icons/skull.png');
            });
            g.appendChild(img);

            // Faction-colored border ring (gold + thicker for own realm)
            g.appendChild(svgEl('circle', {
                cx:pos.x, cy:pos.y, r:Rr,
                fill: 'none',
                stroke: isOwnRealm ? '#FFD700' : faction.color,
                'stroke-width': isOwnRealm ? '3.5' : '2.5',
                filter: 'url(#dropshadow)'
            }));

            // Username label with dark stroke for readability
            const txt = svgEl('text', {
                x: pos.x, y: pos.y+Rr+14,
                'text-anchor': 'middle',
                'font-size': '10',
                'font-family': 'Arial, sans-serif',
                fill: '#e8eef4',
                'paint-order': 'stroke',
                stroke: 'rgba(0,0,0,0.9)',
                'stroke-width': '3',
                'stroke-linejoin': 'round',
                'pointer-events': 'none'
            });
            txt.textContent = realm.user_name;
            g.appendChild(txt);

            // Realm name below username
            const realmTxt = svgEl('text', {
                x: pos.x, y: pos.y+Rr+26,
                'text-anchor': 'middle',
                'font-size': '10',
                'font-family': 'Arial, sans-serif',
                fill: faction.color,
                'paint-order': 'stroke',
                stroke: 'rgba(0,0,0,0.95)',
                'stroke-width': '4',
                'stroke-linejoin': 'round',
                'pointer-events': 'none'
            });
            realmTxt.textContent = realm.realm_name;
            g.appendChild(realmTxt);

            // Popup on click
            g.addEventListener('click', () => showPopup(realm.realm_image, realm.user_image, realm.user_name, realm.realm_name, faction.name, faction.currency, realm.avg_level, realm.realm_id));

            // Swap to avatar on hover, back to realm theme on leave
            g.addEventListener('mouseenter', () => {
                img.setAttribute('href', realm.realm_image);
                img.onerror = function() { this.setAttribute('href', 'icons/skull.png'); };
                if (realm.realm_id) {
                    // Build set of realm_ids connected to this one via any raid line
                    const connected = new Set([realm.realm_id]);
                    for (const ln of linesGroup.children) {
                        if (ln.dataset.offense === realm.realm_id || ln.dataset.defense === realm.realm_id) {
                            connected.add(ln.dataset.offense);
                            connected.add(ln.dataset.defense);
                        }
                    }
                    // Hide unconnected lines; switch connected lines to marching animation
                    for (const ln of linesGroup.children) {
                        const involved = ln.dataset.offense === realm.realm_id || ln.dataset.defense === realm.realm_id;
                        if (involved) {
                            ln.classList.remove('raid-line');
                            ln.classList.add('raid-line-active');
                            ln.style.opacity = '1';
                            ln.style.strokeWidth = '3';
                        } else {
                            ln.classList.remove('raid-line', 'raid-line-active');
                            ln.style.opacity = '0';
                            ln.style.strokeWidth = '';
                        }
                    }
                    // Dim unconnected marker groups
                    for (const [rid, grp] of Object.entries(markerGroups)) {
                        grp.style.opacity = connected.has(rid) ? '1' : '0.1';
                    }
                }
            });
            g.addEventListener('mouseleave', () => {
                /* Back to the avatar, not to a skull -- see the note where the
                   image is created. */
                img.setAttribute('href', realm.user_image);
                img.onerror = function() { this.setAttribute('href', 'icons/skull.png'); };
                for (const ln of linesGroup.children) {
                    ln.style.opacity = '';
                    ln.style.strokeWidth = '';
                    ln.classList.remove('raid-line-active');
                    ln.classList.add('raid-line');
                }
                for (const grp of Object.values(markerGroups)) {
                    grp.style.opacity = '1';
                }
            });

            svg.appendChild(g);
        }
    }

    // ── Layer 2.5 fill: draw active raid lines between realm markers ──────────
    for (const pair of (window.raidPairs || [])) {
        const A = realmPositions[String(pair[0])];
        const B = realmPositions[String(pair[1])];
        if (!A || !B) continue;

        // Control point: perpendicular to midpoint for a gentle arc
        const mx = (A.x + B.x) / 2, my = (A.y + B.y) / 2;
        const dx = B.x - A.x,       dy = B.y - A.y;
        const len = Math.hypot(dx, dy) || 1;
        const ctrlX = mx - (dy / len) * 40;
        const ctrlY = my + (dx / len) * 40;

        // Tangent at A (t=0): direction from A toward ctrl
        const tAx = ctrlX - A.x, tAy = ctrlY - A.y;
        const tAlen = Math.hypot(tAx, tAy) || 1;
        const startX = A.x + (tAx / tAlen) * A.r;
        const startY = A.y + (tAy / tAlen) * A.r;

        // Tangent at B (t=1): direction from ctrl toward B
        const tBx = B.x - ctrlX, tBy = B.y - ctrlY;
        const tBlen = Math.hypot(tBx, tBy) || 1;
        const endX = B.x - (tBx / tBlen) * (B.r + 5); // +5 leaves room for arrowhead
        const endY = B.y - (tBy / tBlen) * (B.r + 5);

        const line = svgEl('path', {
            d: `M${startX.toFixed(1)},${startY.toFixed(1)} Q${ctrlX.toFixed(1)},${ctrlY.toFixed(1)} ${endX.toFixed(1)},${endY.toFixed(1)}`,
            fill: 'none',
            stroke: A.color,
            'stroke-width': '2',
            'stroke-dasharray': '6,3',
            'stroke-linecap': 'round',
            opacity: '0.85',
            'marker-end': 'url(#raid-arrow)',
            'data-offense': String(pair[0]),
            'data-defense': String(pair[1]),
            class: 'raid-line'
        });
        linesGroup.appendChild(line);
    }

    // ── Layer 4: faction name labels (pill badge at top of territory) ────────
    for (const {faction, x, y, w, poly} of placedWithPoly) {
        const c = centroid(poly);
        const fontSize = 12;
        const padX = 10, padY = 5;
        const iconSize = faction.currency ? 14 : 0;
        const iconGap  = faction.currency ? 6  : 0;

        // Measure actual rendered text width via persistent DOM SVG
        const actualTextW = measureTextWidth(faction.name, fontSize, 'Georgia, "Times New Roman", serif', 'bold', '1');

        const pillW = actualTextW + padX * 2 + iconSize + iconGap;
        const pillH = fontSize + padY * 2;
        const pillX = c.x - pillW / 2;
        const pillY = y - 17;
        ink(pillX, pillY, pillX + pillW, pillY + pillH);

        // Dark pill background
        svg.appendChild(svgEl('rect', {
            x: pillX, y: pillY,
            width: pillW, height: pillH,
            rx: '5', ry: '5',
            fill: 'rgba(0,0,0,0.72)',
            stroke: faction.color,
            'stroke-width': '1.2',
            opacity: '0.95',
            'pointer-events': 'none'
        }));

        // Project logo icon (left side of pill)
        if (faction.currency) {
            const iconY = pillY + (pillH - iconSize) / 2;
            svg.appendChild(svgEl('image', {
                href: 'icons/' + faction.currency + '.png',
                x: pillX + padX, y: iconY,
                width: iconSize, height: iconSize,
                'pointer-events': 'none',
                preserveAspectRatio: 'xMidYMid meet'
            }));
        }

        // Label text — anchored left-aligned after icon
        const textX = pillX + padX + iconSize + iconGap;
        const lbl = svgEl('text', {
            x: textX, y: pillY + padY + fontSize - 2,
            'text-anchor': 'start',
            'font-size': fontSize,
            'font-family': 'Georgia, "Times New Roman", serif',
            'font-weight': 'bold',
            'letter-spacing': '1',
            fill: faction.color,
            'pointer-events': 'none'
        });
        lbl.textContent = faction.name;
        svg.appendChild(lbl);
    }

    stopWater();
    container.innerHTML = '';
    container.appendChild(svg);

    /*
     * THE BOX HAS TO CONTAIN ITS OWN INK.
     *
     * map.css sets `overflow: visible`, so the pills and rings tracked by
     * ink() above were never clipped -- they simply painted outside the
     * element's box, over whatever the layout had put there. That was
     * harmless while the map section carried 40px of top padding. With the
     * padding gone it is the sticky nav, which has a background and a
     * higher z-index, so the top row of faction labels went under it.
     *
     * Padding it back by a guess is the wrong shape of fix: the left bleed
     * depends on the longest faction NAME, so it moves with the data.
     * Growing the viewBox to the tracked extent makes the overhang part of
     * the picture, and then zero section padding is exactly right.
     *
     * The slack is for the filter regions -- the glow on the territory
     * borders spreads past the geometry it is applied to.
     */
    const BLEED_SLACK = 6;
    const vx = Math.min(0, Math.floor(inkX0 - BLEED_SLACK));
    const vy = Math.min(0, Math.floor(inkY0 - BLEED_SLACK));
    const vw = Math.max(svgW, Math.ceil(inkX1 + BLEED_SLACK)) - vx;
    const vh = Math.max(svgH, Math.ceil(inkY1 + BLEED_SLACK)) - vy;
    svg.setAttribute('viewBox', `${vx} ${vy} ${vw} ${vh}`);

    /*
     * FIT IT HERE, IN PIXELS, and size the BOX with the picture.
     *
     * #container is given an explicit width and height below -- it has
     * always been sized to the drawing rather than by it -- so scaling the
     * map in CSS was never enough: the picture came down to 330 x 601 and
     * its box stayed 1132 x 2062, leaving 1471px of empty background under
     * it on a 390px phone. One scale factor, applied to both.
     */
    let fitW = vw, fitH = vh;
    if (window.innerWidth <= MOBILE_BP) {
        const avail = Math.max(240, window.innerWidth - MOBILE_GUTTER);
        const k = Math.min(1, avail / vw);
        fitW = Math.round(vw * k);
        fitH = Math.round(vh * k);
    }
    svg.setAttribute('width',  fitW);
    svg.setAttribute('height', fitH);
    container.style.width  = `${fitW}px`;
    container.style.height = `${fitH}px`;

    /*
     * THE WATER GOES ON LAST, once the viewBox is settled.
     *
     * It has to: the canvas is positioned against the FINAL mapping
     * from world coordinates to the element, and that mapping is not
     * known until the labels have been measured and the viewBox grown
     * to fit them. Drawn earlier and stretched to the container, the
     * surf sits a few pixels off the coast it belongs to.
     *
     * Over the map, under nothing: the field is zero on land, so the
     * canvas is transparent everywhere the territories and markers are.
     */
    waterLayer(container, world, svgW, svgH, surfBox(vx, vy, vw, fitW, svgW, svgH));

    /*
     * AND THE FURNITURE GOES BACK ON TOP, in its own SVG above the
     * canvas. The nodes are MOVED, not rebuilt, so every click handler,
     * filter reference and countdown already attached to them comes
     * along; url(#glow) and the marker clip paths still resolve, because
     * SVG ids are looked up across the whole document and <defs> stayed
     * behind in the terrain layer.
     */
    const over = svgEl('svg', {
        width: fitW, height: fitH, viewBox: `${vx} ${vy} ${vw} ${vh}`, class: 'map-over'
    });
    while (svg.childNodes.length > overlayFrom) over.appendChild(svg.childNodes[overlayFrom]);
    container.appendChild(over);
}

renderMap();

let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(renderMap, 200);
});
