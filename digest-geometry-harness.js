/**
 * digest-geometry-harness.js — the poster's layout maths, driven for real.
 *
 *   node digest-geometry-harness.js
 *   (also run by digest-harness.php, which reports it as one assertion)
 *
 * Everything else about the digest is checked by reading source. The
 * GEOMETRY cannot be, and geometry is where it went wrong twice: once with
 * a block hanging off the right edge of the band, and once with "REALM
 * GUAR…" on a post that had already gone out to X.
 *
 * group(), budget() and clusters() are lifted out of js/digest-builder.js
 * and run in a vm, so this tests the shipping code rather than a copy.
 */
'use strict';
const fs = require('fs'), vm = require('vm'), path = require('path');

const src = fs.readFileSync(path.join(__dirname, 'js', 'digest-builder.js'), 'utf8');
function lift(name) {
	const a = src.indexOf('\tfunction ' + name + '(');
	if (a < 0) { console.log('FAIL  ' + name + '() is gone from digest-builder.js'); process.exit(1); }
	let o = src.indexOf('{', a), d = 0;
	for (let i = o; i < src.length; i++) {
		if (src[i] === '{') d++;
		else if (src[i] === '}') { d--; if (!d) return src.slice(a, i + 1); }
	}
	console.log('FAIL  unbalanced ' + name); process.exit(1);
}
const ctx = {};
vm.createContext(ctx);
vm.runInContext([lift('group'), lift('budget'), lift('clusters')].join('\n'), ctx);

/* Rajdhani 700 is narrower than this. Being generous is the point: the
   measurement the real canvas makes must never be wider than what the
   layout was sized against. */
const measure = (t) => t.length * 13;

/* The platform's actual category names, longest included -- "REALM
   GUARDIANS" is the one that got cut in production. */
const CHANS = ['Realm Guardians', 'Crypt Conquest', 'DHC Fighters', 'Monstrocity',
               'Boss Battles', 'Daily Rewards', 'Gauntlets', 'Missions', 'Raids',
               'Skull Racer', 'DHC Arena', 'Auctions'];

let bad = [], checks = 0;
function run(label, art, band, cap) {
	const plan = ctx.budget(ctx.group(art), cap);
	const blocks = ctx.clusters(() => 0.5, 0, 0, band, 400, plan, measure);
	const tag = label + ' band=' + band + ' cap=' + cap;
	checks++;
	for (const b of blocks) {
		if (measure(b.label.toUpperCase()) > b.w) bad.push(tag + ': label "' + b.label + '" cut');
		if (b.x + b.w > band + 1)                 bad.push(tag + ': block past the edge');
	}
	for (const c of blocks.flatMap(b => b.cells)) {
		if (c.x + c.w > band + 1) bad.push(tag + ': tile past the edge');
		if (c.w < 1 || c.h < 1)   bad.push(tag + ': zero-size tile');
		if (!c.item)              bad.push(tag + ': cell with no picture');
	}
	/*
	 * EVERY SURVIVING BLOCK DRAWS ALL OF ITS OWN TILES.
	 *
	 * Per block, NOT per poster. A whole category being dropped for lack of
	 * room to print its name is intended, so a poster-wide "drawn ===
	 * planned" fails on correct behaviour -- the first version of this did
	 * exactly that and flagged 13 good layouts. What must never happen is a
	 * block that kept its place and then quietly left some of its art
	 * behind, which is what a row-distribution bug does: no hole, no error,
	 * just pictures missing.
	 */
	const byLabel = {};
	for (const g of plan) byLabel[g.label] = g.items.length;
	for (const b of blocks) {
		if (byLabel[b.label] !== undefined && b.cells.length !== byLabel[b.label]) {
			bad.push(tag + ': "' + b.label + '" drew ' + b.cells.length + ' of ' + byLabel[b.label]);
		}
	}

	/* AND EVERY ROW SPANS ITS BLOCK. A row that stops short is the hole
	   that made a collage look like a failed image load -- it is the thing
	   the row-filling exists for, so it is measured rather than trusted. */
	for (const b of blocks) {
		const rows = {};
		for (const c of b.cells) (rows[Math.round(c.y)] = rows[Math.round(c.y)] || []).push(c);
		for (const k of Object.keys(rows)) {
			const row = rows[k].sort((p, q) => p.x - q.x);
			const span = (row[row.length - 1].x + row[row.length - 1].w) - row[0].x;
			if (Math.abs(span - b.w) > 1.5) {
				bad.push(tag + ': a row spans ' + Math.round(span) + ' of ' + Math.round(b.w));
			}
		}
	}
}

/* Many categories, each with a few pieces -- the shape that broke. */
let many = [];
CHANS.forEach((c, i) => { for (let k = 0; k < 4 - (i % 3); k++) many.push({ channel: c, label: c }); });
/* One category dominating, which is a real Monstrocity-heavy day. */
let lopsided = [];
for (let k = 0; k < 20; k++) lopsided.push({ channel: 'Monstrocity', label: 'Monstrocity' });
lopsided.push({ channel: 'Realm Guardians', label: 'Realm Guardians' });
/* A single piece of art all day, which is a quiet day and the easiest to
   divide by zero on. */
const one = [{ channel: 'Realm Guardians', label: 'Realm Guardians' }];

for (const W of [1600, 1200, 900, 700]) {
	const band = W - Math.round(W * 0.028) * 2;
	for (const cap of [16, 14, 8, 6, 3, 2, 1]) {
		run('many', many, band, cap);
		run('lopsided', lopsided, band, cap);
		run('one', one, band, cap);
	}
}
/* No art at all must not throw or invent a block. */
checks++;
if (ctx.clusters(() => 0.5, 0, 0, 1000, 400, ctx.budget(ctx.group([]), 14), measure).length !== 0) {
	bad.push('an empty day produced blocks');
}

if (bad.length) {
	console.log('FAIL  ' + bad.length + ' of ' + checks + ' layouts are wrong:');
	[...new Set(bad)].slice(0, 12).forEach(b => console.log('   ' + b));
	process.exit(1);
}
console.log('OK  ' + checks + ' layouts, no cut label and nothing past the band');
