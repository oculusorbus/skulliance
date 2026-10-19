/**
 * player-digest-geometry-harness.js - the player poster's layout, driven.
 *
 *   node player-digest-geometry-harness.js
 *   (also run by player-digest-harness.php, reported as one assertion)
 *
 * WHY THIS EXISTS. The poster had no geometry test and shipped a poster
 * with "4 day login streak" and "skulliance.io" printed ON TOP of the
 * bottom row of artwork, on a real post, where a player saw it before I
 * did. The old layout grew downwards -- the art grid started below however
 * many tally rows there were -- and when it ran past the bottom the footer
 * text was clamped back up into it with Math.min(gy + 36, H - 120). A
 * clamp is not a layout: it guarantees a collision instead of an overflow.
 *
 * It also shipped as 1080x1350 portrait into a summary_large_image card,
 * which crops to about 1.91:1, so what went out was a band through the
 * middle with the whole collage cut off.
 *
 * draw() is lifted out of player-digest.php and run against a RECORDING
 * 2d context, so this exercises the shipping code. Every fill, stroke and
 * image is recorded with its box, and the assertions are the two things a
 * reader notices: nothing outside the card, and nothing on top of
 * anything else.
 */
'use strict';
const fs = require('fs'), vm = require('vm'), path = require('path');

let fail = 0;
const ok = (c, what) => { if (!c) { fail++; console.log('  FAIL  ' + what); } };

const php = fs.readFileSync(path.join(__dirname, 'player-digest.php'), 'utf8');
const m = php.match(/<script[^>]*>([\s\S]*?)<\/script>/);
if (!m) { console.log('FAIL  no script block in player-digest.php'); process.exit(1); }
const js = m[1];

function lift(name) {
	const a = js.indexOf('function ' + name + '(');
	if (a < 0) { console.log('FAIL  ' + name + '() is gone from player-digest.php'); process.exit(1); }
	let o = js.indexOf('{', a), d = 0;
	for (let i = o; i < js.length; i++) {
		if (js[i] === '{') d++;
		else if (js[i] === '}') { d--; if (!d) return js.slice(a, i + 1); }
	}
	console.log('FAIL  unbalanced ' + name); process.exit(1);
}

/* The constants block, so W/H/INK come from the file rather than from here. */
const consts = js.slice(js.indexOf('var W = '), js.indexOf('var D = null;'));
const ctx = {};
vm.createContext(ctx);
vm.runInContext([consts, lift('roundRect'), lift('cover'), lift('prettyDay'),
                 lift('fit'), lift('gridFit'), lift('draw')].join('\n'), ctx);

const W = ctx.W, H = ctx.H;

/*
 * A RECORDING CONTEXT.
 *
 * ui-monospace at size s advances about 0.6s per character. Being a little
 * GENEROUS is deliberate: the real canvas must never measure wider than
 * what the layout was checked against, so an over-estimate fails safe.
 */
function recorder() {
	const boxes = [];
	let font = '16px', fill = '#000', align = 'left', size = 16;
	const adv = (t) => String(t).length * size * 0.62;
	const api = {
		get fillStyle() { return fill; }, set fillStyle(v) { fill = v; },
		strokeStyle: '#000', lineWidth: 1,
		get font() { return font; },
		set font(v) { font = v; const n = /(\d+(?:\.\d+)?)px/.exec(v); size = n ? parseFloat(n[1]) : 16; },
		get textAlign() { return align; }, set textAlign(v) { align = v; },
		measureText: (t) => ({ width: adv(t) }),
		fillText(t, x, y) {
			if (String(t) === '') return;
			const w = adv(t);
			const x0 = align === 'center' ? x - w / 2 : align === 'right' ? x - w : x;
			/*
			 * Ascent is generous, descent is NOT ASSUMED. A flat 0.22em
			 * below every baseline says "112" hangs below the line it sits
			 * on, which it does not -- that reported a collision between
			 * the headline number and its own caption where the real
			 * canvas has clear air. Only strings that actually contain a
			 * descender get one.
			 */
			const desc = /[gjpqy,;]/.test(String(t)) ? 0.22 : 0.04;
			boxes.push({ kind: 'text', t: String(t), x: x0,
			             y: y - size * 0.76, w, h: size * (0.76 + desc) });
		},
		/*
		 * cover() DRAWS OVERSIZED AND CLIPS, which is how it fills a square
		 * without squashing. Recording the draw rather than the clip
		 * reports an 813px image on a 646px panel every time.
		 */
		drawImage(im, x, y, w, h) {
			const cl = this._clip;
			let b = { kind: 'image', t: '<image>', x, y, w, h };
			if (cl) {
				const x0 = Math.max(x, cl.x), y0 = Math.max(y, cl.y);
				const x1 = Math.min(x + w, cl.x + cl.w), y1 = Math.min(y + h, cl.y + cl.h);
				b = { kind: 'image', t: '<image>', x: x0, y: y0, w: x1 - x0, h: y1 - y0 };
			}
			boxes.push(b);
		},
		fillRect(x, y, w, h) { boxes.push({ kind: 'rect', t: '<rect>', x, y, w, h }); },
		/* Paths: record only the rounded panels, which是 the ones that can
		   collide with text. arc() is the avatar and is tracked separately. */
		beginPath() { this._p = null; }, closePath() {}, moveTo() {}, arcTo() {}, lineTo() {},
		arc(x, y, r) { this._p = { x: x - r, y: y - r, w: r * 2, h: r * 2 }; },
		stroke() { if (this._p) boxes.push(Object.assign({ kind: 'arc', t: '<avatar>' }, this._p)); },
		fill() { if (this._rr) { boxes.push(Object.assign({ kind: 'panel', t: '<panel>' }, this._rr)); this._rr = null; } },
		/* A one-deep clip stack is enough: cover() and the avatar each
		   save, clip, draw, restore without nesting. */
		save() { this._saved = this._clip; },
		restore() { this._clip = this._saved || null; },
		clip() { this._clip = this._rr || this._p || null; this._rr = null; },
		_rr: null, _p: null, _clip: null, _saved: null,
	};
	return { api, boxes };
}

/* roundRect() is lifted real code, so intercept its geometry by wrapping. */
function instrument(rec) {
	const real = ctx.roundRect;
	ctx.roundRect = function (c, x, y, w, h, r) { c._rr = { x, y, w, h }; return real(c, x, y, w, h, r); };
	return () => { ctx.roundRect = real; };
}

/*
 * The real labels, plus one that is longer than any of them. A channel with
 * no entry in activity_channel_label()'s map falls through to
 * ucwords(str_replace(...)) on the raw channel name, so the longest label
 * the poster can be handed is not in the map and is not bounded by it --
 * and the only fixtures here were short enough that the row truncation was
 * never exercised at all.
 */
const CHANS = ['Realm Guardians', 'Crypt Conquest', 'DHC Fighters', 'Monstrocity', 'Skull Swap',
               'Daily Rewards', 'Boss Battles', 'Gauntlets', 'Missions', 'Raids', 'Obscura',
               'Inner Circle', 'Delegations',
               'Some Channel Nobody Added To The Label Map Yet'];
const NAMES = ['a', 'oculusorbus', 'a_very_long_staker_name_that_keeps_going_and_going', 'Ω' .repeat(30)];

function overlaps(a, b) {
	const pad = 0.5;
	return a.x < b.x + b.w - pad && b.x < a.x + a.w - pad
	    && a.y < b.y + b.h - pad && b.y < a.y + a.h - pad;
}

let cases = 0, worstOut = null, worstHit = null;
const restore = instrument();
for (const nArt of [0, 1, 2, 3, 4, 5, 6, 7, 8]) {
	for (const nChan of [1, 2, 3, 4, 5, 6, 8]) {
		for (const total of [1, 9, 112, 9999]) {
			for (const streak of [0, 1, 4, 148]) {
				for (const name of NAMES) {
					const d = {
						day: '2026-10-17', name, total, streak,
						/* FIRST, not last: only five rows are drawn, so a long
						   label parked at position eight is sliced off before
						   it can test anything. */
						channels: (nChan >= 8
								? [CHANS[CHANS.length - 1]].concat(CHANS.slice(0, nChan - 1))
								: CHANS.slice(0, nChan))
							.map((l, i) => ({ label: l, n: (i + 1) * 7 })),
					};
					const images = {
						avatar: { naturalWidth: 128, naturalHeight: 128 },
						art: Array.from({ length: nArt }, () => ({ naturalWidth: 900, naturalHeight: 600 })),
					};
					const rec = recorder();
					ctx.document = { getElementById: () => ({ getContext: () => rec.api }) };
					ctx.draw(d, images);
					cases++;

					for (const b of rec.boxes) {
						if (b.x < -0.5 || b.y < -0.5 || b.x + b.w > W + 0.5 || b.y + b.h > H + 0.5) {
							if (!worstOut) worstOut = { b, nArt, nChan, streak, name: name.slice(0, 12) };
						}
					}
					/* TEXT ON ART is the bug that shipped. Panels sit behind
					   their own row's text by design, so only text-vs-image
					   and text-vs-text are collisions. */
					const texts = rec.boxes.filter((b) => b.kind === 'text');
					const arts  = rec.boxes.filter((b) => b.kind === 'image' || b.kind === 'arc');
					for (const t of texts) {
						for (const a of arts) {
							if (overlaps(t, a) && !worstHit) {
								worstHit = { t: t.t, nArt, nChan, streak, name: name.slice(0, 12) };
							}
						}
					}
					for (let i = 0; i < texts.length; i++) {
						for (let k = i + 1; k < texts.length; k++) {
							if (overlaps(texts[i], texts[k]) && !worstHit) {
								worstHit = { t: texts[i].t + ' / ' + texts[k].t, nArt, nChan, streak,
								             name: name.slice(0, 12) };
							}
						}
					}
				}
			}
		}
	}
}
restore();

ok(cases > 2000, 'only ' + cases + ' layouts were driven');
ok(!worstOut, worstOut && ('something is drawn outside the card: "' + worstOut.t + '" at ('
   + Math.round(worstOut.b.x) + ',' + Math.round(worstOut.b.y) + ') '
   + Math.round(worstOut.b.w) + 'x' + Math.round(worstOut.b.h) + ' on a ' + W + 'x' + H
   + ' card, with ' + worstOut.nArt + ' pictures and ' + worstOut.nChan + ' rows'));
ok(!worstHit, worstHit && ('text is drawn on top of something: "' + worstHit.t + '" with '
   + worstHit.nArt + ' pictures, ' + worstHit.nChan + ' rows, streak ' + worstHit.streak));

/* THE CARD SHAPE IS THE POINT. X crops summary_large_image to about 1.91:1,
   so a portrait card loses its middle band -- which is how the whole art
   collage ended up cut off the post. */
const ratio = W / H;
ok(ratio > 1.7 && ratio < 2.1,
   'the card is ' + W + 'x' + H + ' (' + ratio.toFixed(2) + ':1); X crops to about 1.91:1, '
 + 'so anything portrait loses the artwork');
/* And it has to survive the upload bounds in ajax/digest-share.php. */
ok(W >= 200 && H >= 200 && W <= 2400 && H <= 3000,
   'the card is outside the dimensions ajax/digest-share.php will accept');

console.log(fail ? '\n' + fail + ' FAILED' : 'OK  ' + cases + ' layouts, nothing outside the card and nothing overlapping');
process.exit(fail ? 1 : 0);
