/**
 * dropship-art-lightbox-harness.js -- node, no browser, no network.
 *
 * Runs the REAL lightbox() out of dropship/admin-art.php against a DOM
 * stub. Clicking a slot on the art page opens the picture full size,
 * because a 1080px frame shown at 210 says which image is in the slot and
 * nothing about whether it is the right one.
 *
 * WHAT IT IS PROTECTING is what happens on CLOSE. The overlay is fixed at
 * z-index 200 over the whole page, and it registers a document-level
 * keydown listener to catch Escape -- so a close that forgets to remove
 * that listener leaves one behind per open, and a close that forgets to
 * remove the node leaves the admin staring at a covered page. Both are
 * invisible until the fiftieth slot.
 *
 * Also pins that the caption is built with textContent. A theme names its
 * own icons and that name reaches the card, so innerHTML there is markup
 * from a database field.
 *
 * Usage: node dropship-art-lightbox-harness.js
 */
'use strict';
const fs = require('fs');

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

const php = fs.readFileSync(__dirname + '/dropship/admin-art.php', 'utf8');
const m = php.match(/\tfunction lightbox\(img, slot\) \{[\s\S]*?\n\t\}\n/);
ok(!!m, 'lightbox() could not be found in admin-art.php, so nothing below proves anything');
const SRC = m ? m[0] : 'function lightbox(){}';

ok(!/innerHTML/.test(SRC),
   'the caption is built with innerHTML, and a theme names its own icons');
ok(/big\.src = img\.src/.test(SRC),
   'the full-size image is not the card\'s own src, so it misses the cache buster '
 + 'and a slot replaced a moment ago shows the old picture');

/* ---------- the stub ---------------------------------------------------- */

function makeEl(tag) {
	return {
		tagName: tag.toUpperCase(),
		className: '', src: '', alt: '', textContent: '',
		children: [], parent: null, listeners: {},
		appendChild(c) { c.parent = this; this.children.push(c); return c; },
		addEventListener(ev, fn) { (this.listeners[ev] = this.listeners[ev] || []).push(fn); },
		remove() {
			this.removed = true;
			if (this.parent) this.parent.children = this.parent.children.filter(c => c !== this);
		},
		fire(ev) { (this.listeners[ev] || []).forEach(f => f({})); },
		querySelector() { return null; },
	};
}

function run() {
	const body = makeEl('body');
	const docListeners = {};
	const doc = {
		body: body,
		createElement: makeEl,
		addEventListener(ev, fn) { (docListeners[ev] = docListeners[ev] || []).push(fn); },
		removeEventListener(ev, fn) {
			docListeners[ev] = (docListeners[ev] || []).filter(f => f !== fn);
		},
	};
	const card = { name: 'Bunker, round 16 and beyond, scene 5 of 6',
	               path: 'images/live/bunker/11.jpg', dims: '1080×1080 · 222KB' };
	const slot = {
		querySelector(sel) {
			const key = sel.replace('.', '');
			if (!(key in card)) return null;
			const el = makeEl('span');
			el.textContent = card[key];
			return el;
		},
	};
	const img = makeEl('img');
	img.src = '/staking/dropship/dread-city/images/live/bunker/11.jpg?v=1771';

	const fn = new Function('document', SRC + '\nreturn lightbox;')(doc);
	fn(img, slot);
	return {
		body: body,
		box: body.children[0],
		keys: () => (docListeners.keydown || []).length,
		esc: () => (docListeners.keydown || []).slice().forEach(f => f({ key: 'Escape' })),
		other: () => (docListeners.keydown || []).slice().forEach(f => f({ key: 'a' })),
	};
}

/* ---------- what it has to do ------------------------------------------- */

const a = run();
ok(!!a.box && a.box.className === 'lightbox', 'no overlay was added to the page');
const big = a.box && a.box.children[0];
ok(big && big.tagName === 'IMG' && big.src.indexOf('?v=') !== -1,
   'the overlay has no image, or it lost the cache buster');
const cap = a.box && a.box.children[1];
ok(cap && JSON.stringify(cap.children.map(c => c.textContent)).indexOf('bunker/11.jpg') !== -1,
   'the caption does not say which slot is being looked at');
ok(a.keys() === 1, 'Escape is not listened for, or is listened for more than once');

/* CLICKING IT CLOSES IT, and takes the listener with it. */
a.box.fire('click');
ok(a.box.removed === true, 'clicking the overlay does not close it');
ok(a.body.children.length === 0, 'the overlay is left on the page after closing');
ok(a.keys() === 0,
   'the keydown listener outlives the overlay, so one leaks for every picture opened');

/* ESCAPE CLOSES IT TOO, and only Escape. */
const b = run();
b.other();
ok(b.box.removed !== true, 'any key closes the overlay, not just Escape');
b.esc();
ok(b.box.removed === true, 'Escape does not close the overlay');
ok(b.keys() === 0, 'closing with Escape leaves its own listener behind');

/* TWICE IN A ROW. Fifty slots inspected in one sitting is the real usage,
   and a leak only shows up at that scale. */
const c = run(); c.box.fire('click');
const d = run(); d.esc();
ok(c.keys() === 0 && d.keys() === 0, 'listeners accumulate across repeated opens');

/* A CLOSE THAT RUNS TWICE must not throw -- clicking as Escape is pressed
   is one gesture a person can actually make. */
let threw = false;
try { const e = run(); e.box.fire('click'); e.esc(); e.box.fire('click'); }
catch (err) { threw = true; }
ok(!threw, 'closing an already-closed overlay throws, taking the page scripts with it');

console.log(fails ? '\n' + fails + ' check(s) FAILED' : '\nall good');
process.exit(fails ? 1 : 0);
