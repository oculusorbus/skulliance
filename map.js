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

// ── Colors ────────────────────────────────────────────────────────────────────
const palette = [
    '#FF3366','#FA00FF','#00E6B3','#00FFFF','#FF8C33',
    '#33CC99','#8C33FF','#0077FF','#FF66CC','#FF80FF',
    '#8000FF','#00B3B3','#4D4DFF','#CC99FF','#009999'
];
let colorPool = [];

function getColor() {
    if (!colorPool.length) colorPool = [...palette];
    const i = Math.floor(Math.random() * colorPool.length);
    return colorPool.splice(i, 1)[0];
}

function hexToRgba(hex, a) {
    const r = parseInt(hex.slice(1,3),16), g = parseInt(hex.slice(3,5),16), b = parseInt(hex.slice(5,7),16);
    return `rgba(${r},${g},${b},${a})`;
}

// ── Seeded PRNG ───────────────────────────────────────────────────────────────
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
    popupUser.textContent = userName;
    popupRealm.textContent = realmName;

    // Faction chip
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

    popupOverlay.style.display = 'flex';
}
function hidePopup() { popupOverlay.style.display = 'none'; }
popupOverlay.addEventListener('click', e => { if (e.target === popupOverlay) hidePopup(); });
popupClose.addEventListener('click', hidePopup);

// ── Territory geometry ────────────────────────────────────────────────────────
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

// ── Point in polygon ──────────────────────────────────────────────────────────
function inPoly(px, py, poly) {
    let inside = false;
    for (let i=0, j=poly.length-1; i<poly.length; j=i++) {
        const {x:xi,y:yi}=poly[i], {x:xj,y:yj}=poly[j];
        if (((yi>py) !== (yj>py)) && px < (xj-xi)*(py-yi)/(yj-yi)+xi) inside=!inside;
    }
    return inside;
}

// ── Marker placement within territory ────────────────────────────────────────
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

// ── Packing ───────────────────────────────────────────────────────────────────
const CELL = 170;
const PAD  = 32;

function buildFactionData() {
    colorPool = [...palette];
    return factions.map(f => {
        const count = f.realms.length;
        const cols = Math.max(1, Math.ceil(Math.sqrt(count)));
        const rows = Math.max(1, Math.ceil(count / cols));
        const currency = (f.currency || '').toLowerCase();
        return Object.assign({}, f, { count: count, w: cols*CELL, h: rows*CELL, color: getColor(), currency: currency });
    });
}

function packFactions(fdata) {
    const vw = window.innerWidth;
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
function svgEl(tag, attrs={}) {
    const e = document.createElementNS(NS, tag);
    for (const [k,v] of Object.entries(attrs)) e.setAttribute(k, v);
    return e;
}

// Persistent hidden SVG in the DOM used for text measurement
const _measureSvg = document.createElementNS(NS, 'svg');
_measureSvg.setAttribute('style', 'position:absolute;visibility:hidden;width:0;height:0;overflow:hidden');
document.body.appendChild(_measureSvg);

function measureTextWidth(text, fontSize, fontFamily, fontWeight, letterSpacing) {
    const probe = document.createElementNS(NS, 'text');
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

// ── Render ────────────────────────────────────────────────────────────────────
function renderMap() {
    const container = document.getElementById('container');
    const fdata = buildFactionData();
    const placed = packFactions(fdata);

    let svgW = 0, svgH = 0;
    for (const p of placed) {
        svgW = Math.max(svgW, p.x+p.w+PAD);
        svgH = Math.max(svgH, p.y+p.h+PAD);
    }

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
    const placedWithPoly = placed.map(p => {
        const rng = mulberry32(hashStr(p.faction.name + '_territory'));
        const inset = PAD * 0.6;
        const jitter = Math.min(20, Math.min(p.w, p.h) * 0.09);
        const poly = chaikin(jitterRect(p.x+inset, p.y+inset, p.w-inset*2, p.h-inset*2, jitter, rng), 3);
        return Object.assign({}, p, { poly: poly });
    });

    const R = 22;
    let clipIdx = 0;

    // ── Layer 1: territory fills ──────────────────────────────────────────────
    const LOGO_MAX = 96; // max natural size cap in px
    for (const {faction, poly} of placedWithPoly) {
        svg.appendChild(svgEl('path', {
            d: toPath(poly),
            fill: hexToRgba(faction.color, 0.13),
            stroke: 'none'
        }));
        svg.appendChild(svgEl('path', {
            d: toPath(poly),
            fill: 'none',
            stroke: faction.color,
            'stroke-width': '2',
            'stroke-dasharray': '10,6',
            'stroke-linejoin': 'round',
            'stroke-linecap': 'round',
            filter: 'url(#glow)',
            opacity: '0.85'
        }));

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

    // ── Layer 2.5: raid lines group (populated after markers are placed) ────────
    const linesGroup = document.createElementNS(NS, 'g');
    svg.appendChild(linesGroup);

    // ── Layer 3: realm markers ────────────────────────────────────────────────
    const realmPositions = {}; // realm_id → {x, y, color}
    const markerGroups   = {}; // realm_id → <g> element

    // Build sets of realm IDs active in raids this month
    const activeRaidRealms = new Set(); // attackers + defenders (used for avatar display)
    const activeAttackers  = new Set(); // attackers only (used for glow)
    for (const pair of (window.raidPairs || [])) {
        activeRaidRealms.add(String(pair[0]));
        activeRaidRealms.add(String(pair[1]));
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
            const halo = svgEl('circle', {
                cx:pos.x, cy:pos.y, r:Rr+5,
                fill: hexToRgba(faction.color, isActiveRaider ? 0.35 : 0.18),
                stroke: isActiveRaider ? faction.color : 'none',
                'stroke-width': isActiveRaider ? '2' : '0',
                'stroke-opacity': '0.7'
            });
            if (isActiveRaider) halo.classList.add('marker-active-glow');
            g.appendChild(halo);

            // Realm theme image (default), swaps to avatar on hover
            const img = svgEl('image', {
                href: (realm.realm_id && activeRaidRealms.has(realm.realm_id)) ? realm.user_image : 'icons/skull.png',
                x: pos.x-Rr, y: pos.y-Rr,
                width: Rr*2, height: Rr*2,
                'clip-path': `url(#ac${idx})`,
                preserveAspectRatio: 'xMidYMid slice'
            });
            img.addEventListener('error', function() {
                this.setAttribute('href', 'icons/skull.png');
            });
            g.appendChild(img);

            // Faction-colored border ring
            g.appendChild(svgEl('circle', {
                cx:pos.x, cy:pos.y, r:Rr,
                fill: 'none',
                stroke: faction.color,
                'stroke-width': '2.5',
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
                const defaultAvatar = (realm.realm_id && activeRaidRealms.has(realm.realm_id)) ? realm.user_image : 'icons/skull.png';
                img.setAttribute('href', defaultAvatar);
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

    container.innerHTML = '';
    container.style.width  = `${svgW}px`;
    container.style.height = `${svgH}px`;
    container.appendChild(svg);
}

renderMap();

let resizeTimer;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(renderMap, 200);
});
