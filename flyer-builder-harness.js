/* flyer-builder-harness.js - node, no browser, no network.
 *
 * The flyer builder's LAYOUT, run against a recording canvas.
 *
 * WHY THIS EXISTS. "Logo up/down" moved the logo and every line underneath
 * it: logoBottom was 1180 + logoY and the text stack started at
 * logoBottom + 72, so nudging a logo down by a hundred pushed the url off
 * the bottom of the flyer. Reported by the owner, who wanted the one thing
 * the control is named for -- placing the logo.
 *
 * WHY IT RECORDS RATHER THAN RENDERS. The question is where things land,
 * and that is answered by the coordinates render() passes to drawImage()
 * and fillText(), not by pixels. A stub 2d context captures every call with
 * its arguments, so the whole layout can be asserted in node with no canvas
 * and no Chrome -- and a coupling between two elements shows up as two
 * numbers moving together, which no source grep can see.
 *
 * Usage: node flyer-builder-harness.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');

let fail = 0;
const ok = (c, w) => { if (!c) { fail++; console.log('  FAIL  ' + w); } };

/** A 2d context that draws nothing and remembers everything. */
function recorder(log) {
	const noop = () => {};
	return {
		save: noop, restore: noop, beginPath: noop, closePath: noop, clip: noop,
		fill: noop, stroke: noop, arc: noop, lineTo: noop, moveTo: noop,
		fillRect: (x, y, w, h) => log.push({ op: 'fillRect', x, y, w, h }),
		drawImage: function () { log.push({ op: 'drawImage', a: [].slice.call(arguments) }); },
		fillText: (t, x, y) => log.push({ op: 'fillText', t: String(t), x, y }),
		/* Wide enough that the auto-shrink branches engage like they would
		   with a real font, and proportional to the string so the two
		   measure-then-rescale paths behave. */
		measureText: (t) => ({ width: String(t).length * 26 }),
		createLinearGradient: () => ({ addColorStop: noop }),
		getImageData: (x, y, w, h) => ({ data: new Uint8ClampedArray(w * h * 4) }),
		fillStyle: '', strokeStyle: '', font: '', globalAlpha: 1,
		lineWidth: 1, textAlign: '', textBaseline: '', letterSpacing: '',
	};
}
function fakeCanvas(log) {
	return { width: 0, height: 0, getContext: () => recorder(log) };
}

/* The module expects a browser. Nothing it needs for layout is real: the
   off-screen canvases it makes for grit and tinting record into throwaway
   logs, and the one it is handed records into ours. */
const src = fs.readFileSync(path.join(__dirname, 'js', 'flyer-builder.js'), 'utf8');
function run(state, imgs) {
	const log = [];
	const sandbox = {
		window: {},
		document: { createElement: () => fakeCanvas([]) },
		Image: function () {},
		Math: Math, Date: Date, isFinite: isFinite, parseFloat: parseFloat,
		Uint8ClampedArray: Uint8ClampedArray, console: console,
	};
	vm.createContext(sandbox);
	vm.runInContext(src, sandbox);
	sandbox.window.FlyerBuilder.render(fakeCanvas(log), state, imgs);
	return log;
}

const base = {
	seed: 1337,
	artZoom: 1, artRot: 0, artX: 0, artY: 0, shade: 0.5,
	winTopL: 0, winTopR: 0, winBotL: 0, winBotR: 0, grit: 0,
	logoScale: 1, logoY: 0, iconX: 0, iconY: 0, iconScale: 1,
	colHighlight: '#0ff', colDivider: '#888', colBullets: '#ccc', colRing: '#0f0',
	logoWhite: false, iconWhite: false, showIcon: true, artFlip: false,
	/* Name and ticker deliberately DIFFERENT: both are drawn with a plain
	   fillText and with the same string the probe below found the ticker,
	   which does not move with the logo, and reported a passing control as
	   broken. */
	name: 'Drekl', ticker: 'DRKL', line1: 'LINE ONE', line2: 'LINE TWO',
	line3: 'LINE THREE', url: 'skulliance.io', headFont: 'bebas',
	bullets: ['ONE', 'TWO', 'THREE'],
};
const withY = (y, over) => Object.assign({}, base, { logoY: y }, over || {});
/* A logo that is not square, so "a tall logo grows upward" is testable. */
const logoImg = { width: 800, height: 300 };

/**
 * The y of the last line of the stack -- the url, which fell off the flyer.
 *
 * spaced() draws one CHARACTER per fillText so it can letter-space by hand,
 * so there is no single call carrying the whole string to search for. The
 * url is the last thing render() draws, so the last fillText is its final
 * character.
 */
function urlY(log) {
	const t = log.filter((e) => e.op === 'fillText');
	return t.length ? t[t.length - 1].y : null;
}
/** Where the logo image was drawn. */
function logoAt(log) {
	const d = log.filter((e) => e.op === 'drawImage' && e.a.length === 5);
	return d.length ? { y: d[d.length - 1].a[2], h: d[d.length - 1].a[4] } : null;
}

console.log('the logo moves on its own');
{
	const a = run(withY(0), { logo: logoImg }),
	      b = run(withY(-200), { logo: logoImg }),
	      c = run(withY(160), { logo: logoImg });
	ok(logoAt(a) && logoAt(b) && logoAt(c), 'the logo was never drawn, so nothing below is tested');
	ok(urlY(a) !== null, 'the url line was never drawn, so the stack cannot be measured');

	/* THE BUG. These three were 1252, 1052 and 1412 -- the whole stack rode
	   the logo slider, and at +160 the url sat past the 1600px canvas. */
	ok(urlY(a) === urlY(b) && urlY(a) === urlY(c),
	   'the text stack still moves with the logo: url at ' + urlY(b) + ' / ' + urlY(a)
	 + ' / ' + urlY(c) + ' for logoY -200 / 0 / +160');
	ok(urlY(c) < 1600,
	   'the last line of the stack is drawn at ' + urlY(c) + ', past the bottom of a '
	 + '1600px flyer');

	/* And the control still does its job. */
	ok(logoAt(b).y < logoAt(a).y && logoAt(a).y < logoAt(c).y,
	   'the slider no longer moves the logo at all: ' + logoAt(b).y + ' / ' + logoAt(a).y
	 + ' / ' + logoAt(c).y);
	ok(logoAt(c).y - logoAt(a).y === 160 && logoAt(a).y - logoAt(b).y === 200,
	   'the logo does not move one pixel per unit of the slider');
}

console.log('a bigger logo still grows upward from its own bottom edge');
{
	/* This was already right and is the reason logoScale can be swept
	   without the text shifting. Decoupling logoY must not have cost it. */
	const s1 = run(withY(0, { logoScale: 0.5 }), { logo: logoImg });
	const s2 = run(withY(0, { logoScale: 1.5 }), { logo: logoImg });
	ok(logoAt(s2).h > logoAt(s1).h, 'logoScale no longer changes the drawn size');
	ok(logoAt(s1).y + logoAt(s1).h === logoAt(s2).y + logoAt(s2).h,
	   'the logo grows downward from its top rather than upward from its bottom: bottoms at '
	 + (logoAt(s1).y + logoAt(s1).h) + ' and ' + (logoAt(s2).y + logoAt(s2).h));
	ok(urlY(s1) === urlY(s2), 'resizing the logo moves the text stack');
}

console.log('the name falls back to text, and moves the same way');
{
	/* No logo uploaded: the project name is set big on the same baseline,
	   so it has to honour the same slider and leave the stack alone. */
	const a = run(withY(0), {}), b = run(withY(-120), {});
	const nameY = (log) => {
		const h = log.filter((e) => e.op === 'fillText' && e.t === 'DREKL');
		return h.length ? h[0].y : null;
	};
	ok(nameY(a) !== null, 'the name is not drawn when there is no logo');
	ok(nameY(a) - nameY(b) === 120, 'the name fallback ignores the logo slider');
	ok(urlY(a) === urlY(b), 'the name fallback drags the stack with it');
}

console.log(fail ? '\nFAILED: ' + fail + ' check(s)' : '\nall flyer layout checks passed');
process.exit(fail ? 1 : 0);
