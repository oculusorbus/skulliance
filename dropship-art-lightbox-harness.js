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

/* THE CAPTION carries a theme's own words, so it is textContent. The one
   innerHTML left is the literal multiplication sign on the close button,
   which is a constant in this file and not page data. */
ok(!/innerHTML\s*=\s*[^'"]*(name|path|dims|textContent)/.test(SRC),
   'the caption is built with innerHTML, and a theme names its own icons');
ok((SRC.match(/innerHTML/g) || []).length <= 1,
   'more than one innerHTML in the lightbox; only the close glyph may be one');

/*
 * THE OVERLAY IS APPENDED TO <body>, SO ITS CSS MUST NOT BE SCOPED TO
 * THE PAGE WRAPPER. This is the bug the first version shipped with:
 * every rule was written `.dsart .lightbox` while the element lived on
 * body, so not one of them matched. It was an unstyled div at the foot
 * of the page -- not centred, no backdrop, nothing to click outside of,
 * and a fresh one on every click. A DOM stub cannot see that; the
 * stylesheet can be read.
 */
ok(/document\.body\.appendChild\(box\)/.test(SRC),
   'the overlay is no longer appended to body; the selector check below assumes it is');
const cls = (SRC.match(/box\.className\s*=\s*'([^']+)'/) || [])[1] || '';
ok(cls !== '', 'the overlay has no class, so nothing can style it');
ok(!new RegExp('\\.dsart\\s+\\.' + cls + '\\b').test(php),
   'the overlay is styled as a descendant of .dsart but is appended to body, so no rule matches');
ok(new RegExp('^\\.' + cls + '\\{', 'm').test(php),
   'there is no unscoped rule for .' + cls + ', so the overlay renders unstyled');
ok(new RegExp('\\.' + cls + '\\{[^}]*position:fixed').test(php),
   'the overlay is not fixed, so it does not cover the page');
ok(new RegExp('\\.' + cls + '\\{[^}]*justify-content:center').test(php),
   'the overlay does not centre what it shows');
ok(/big\.src = img\.src/.test(SRC),
   'the full-size image is not the card\'s own src, so it misses the cache buster '
 + 'and a slot replaced a moment ago shows the old picture');

/* ---------- the stub ---------------------------------------------------- */

function makeEl(tag) {
	return {
		tagName: tag.toUpperCase(),
		className: '', src: '', alt: '', textContent: '', type: '', innerHTML: '',
		attrs: {},
		setAttribute(n, v) { this.attrs[n] = v; },
		getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; },
		children: [], parent: null, listeners: {},
		appendChild(c) { c.parent = this; this.children.push(c); return c; },
		addEventListener(ev, fn) { (this.listeners[ev] = this.listeners[ev] || []).push(fn); },
		remove() {
			this.removed = true;
			if (this.parent) this.parent.children = this.parent.children.filter(c => c !== this);
		},
		fire(ev, target) { (this.listeners[ev] || []).forEach(f => f({ target: target || this })); },
		querySelector() { return null; },
	};
}

function run() {
	const body = makeEl('body');
	const docListeners = {};
	let standing = null;
	const doc = {
		body: body,
		createElement: makeEl,
		querySelector: function (q) {
			return (standing && !standing.removed && q.indexOf(standing.className) !== -1)
				? standing : null;
		},
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
	standing = body.children[body.children.length - 1] || null;
	return {
		open: function () { fn(img, slot); standing = body.children[body.children.length - 1]; },
		body: body,
		box: body.children[0],
		keys: () => (docListeners.keydown || []).length,
		esc: () => (docListeners.keydown || []).slice().forEach(f => f({ key: 'Escape' })),
		other: () => (docListeners.keydown || []).slice().forEach(f => f({ key: 'a' })),
	};
}

/* ---------- what it has to do ------------------------------------------- */

const a = run();
ok(!!a.box && a.box.className === 'ds-lightbox', 'no overlay was added to the page');
const big = a.box && a.box.children[0];
ok(big && big.tagName === 'IMG' && big.src.indexOf('?v=') !== -1,
   'the overlay has no image, or it lost the cache buster');
const cap = a.box && a.box.children[1];
ok(cap && JSON.stringify(cap.children.map(c => c.textContent)).indexOf('bunker/11.jpg') !== -1,
   'the caption does not say which slot is being looked at');
ok(a.keys() === 1, 'Escape is not listened for, or is listened for more than once');

/* THERE IS A CLOSE BUTTON, and it closes. */
const xb = a.box.children.filter(c => c.className === 'x')[0];
ok(!!xb, 'there is no close button');
ok(xb && xb.getAttribute('aria-label') === 'Close', 'the close button has no accessible name');
xb.fire('click');
ok(a.box.removed === true, 'the close button does not close it');
ok(a.keys() === 0, 'closing with the button leaves its listener behind');

/* THE BACKDROP CLOSES... */
const b2 = run();
b2.box.fire('click', b2.box);
ok(b2.box.removed === true, 'clicking the backdrop does not close it');
ok(b2.body.children.length === 0, 'the overlay is left on the page after closing');
ok(b2.keys() === 0,
   'the keydown listener outlives the overlay, so one leaks for every picture opened');

/* ...AND THE PICTURE DOES NOT. Pointing at the thing you came to look at
   must not throw it away. */
const c2 = run();
c2.box.fire('click', c2.box.children[0]);
ok(c2.box.removed !== true, 'clicking the picture itself closes the overlay');
c2.box.fire('click', c2.box);
ok(c2.box.removed === true, 'the backdrop stopped closing it');

/* ESCAPE CLOSES IT TOO, and only Escape. */
const d2 = run();
d2.other();
ok(d2.box.removed !== true, 'any key closes the overlay, not just Escape');
d2.esc();
ok(d2.box.removed === true, 'Escape does not close the overlay');
ok(d2.keys() === 0, 'closing with Escape leaves its own listener behind');

/* ONE AT A TIME. Every click used to append another, and with the
   stylesheet not matching they piled up down the page. */
const e2 = run();
e2.open();
ok(e2.body.children.length === 1,
   'opening a second picture stacks a second overlay: ' + e2.body.children.length);

/* TWICE IN A ROW, closed properly each time: fifty slots inspected in one
   sitting is the real usage and a leak only shows at that scale. */
const f2 = run(); f2.box.fire('click', f2.box);
const g2 = run(); g2.esc();
ok(f2.keys() === 0 && g2.keys() === 0, 'listeners accumulate across repeated opens');

/* A CLOSE THAT RUNS TWICE must not throw -- clicking as Escape is
   pressed is one gesture a person can actually make. */
let threw = false;
try { const h2 = run(); h2.box.fire('click', h2.box); h2.esc(); h2.box.fire('click', h2.box); }
catch (err) { threw = true; }
ok(!threw, 'closing an already-closed overlay throws, taking the page scripts with it');

console.log(fails ? '\n' + fails + ' check(s) FAILED' : '\nall good');
process.exit(fails ? 1 : 0);
