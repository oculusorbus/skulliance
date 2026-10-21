/**
 * dropship-picker-harness.js -- node, no browser, no network.
 *
 * Runs the REAL game picker out of dropship/dashboard.php against a DOM stub.
 *
 * WHY IT IS NOT A SOURCE CHECK. dropship-themes-harness.php already reads the
 * markup, and that caught five of six mutations when this was written. The one
 * it could not catch is the one worth catching: wrapping the data-src swap in
 * a condition that is never true leaves the line exactly where a grep expects
 * to find it, and opens the picker on four empty boxes. Reachability is not
 * visible in text, so the handler is executed here instead.
 *
 * Usage: node dropship-picker-harness.js
 */
'use strict';
const fs = require('fs');

let fails = 0;
function ok(cond, what) { if (cond) { console.log('  ok   ' + what); return true; }
	fails++; console.log('  FAIL  ' + what); return false; }

const php = fs.readFileSync(__dirname + '/dropship/dashboard.php', 'utf8');

/* The whole IIFE, lifted. Anchored on the first line of it rather than on a
   function name, because it has none -- and taking it verbatim means a rename
   in the page stops this running rather than letting it drift. */
const m = php.match(/\(function \(\) \{\n\tvar veil = document\.getElementById\('dsgame'\);[\s\S]*?\n\}\(\)\);/);
ok(!!m, 'the picker script could not be lifted from dashboard.php');
if (!m) { console.log('\ncannot continue'); process.exit(1); }
const SRC = m[0];

/* ---------- a DOM with just enough in it ---------------------------------- */

function makeEl(tag, attrs) {
	const el = {
		tagName: (tag || 'DIV').toUpperCase(),
		hidden: false, listeners: {}, attrs: Object.assign({}, attrs || {}),
		focused: 0, src: '',
		getAttribute(k) { return Object.prototype.hasOwnProperty.call(this.attrs, k) ? this.attrs[k] : null; },
		addEventListener(ev, fn) { (this.listeners[ev] = this.listeners[ev] || []).push(fn); },
		focus() { this.focused++; doc.activeElement = this; },
		closest(sel) { return sel === '.dsgame-tile' && this.tile ? this : null; },
		fire(ev, obj) { (this.listeners[ev] || []).forEach(f => f(Object.assign({ target: this }, obj || {}))); },
	};
	return el;
}

const tiles = [0, 1, 2, 3].map(i => {
	const img = makeEl('img', { 'data-src': '/art/' + i + '.jpg' });
	const a = makeEl('a');
	a.tile = true; a.img = img;
	return a;
});
const veil = makeEl('div');
const open = makeEl('button');
const box  = makeEl('div');
const x    = makeEl('button');
veil.hidden = true;
veil.querySelectorAll = sel =>
	sel === 'img[data-src]' ? tiles.map(t => t.img).filter(im => im.getAttribute('data-src')) : [];

const docListeners = {};
const doc = {
	activeElement: open,
	getElementById(id) {
		return ({ dsgame: veil, 'dsgame-open': open, 'dsgame-box': box, 'dsgame-x': x })[id] || null;
	},
	addEventListener(ev, fn) { (docListeners[ev] = docListeners[ev] || []).push(fn); },
};
let loaderCalls = 0;
new Function('document', 'dsShowLoader', SRC)(doc, () => { loaderCalls++; });

const key = k => (docListeners.keydown || []).forEach(f => f({ key: k }));

/* ---------- 1. opening ----------------------------------------------------- */

console.log('opening the picker');
ok(veil.hidden === true, 'the picker starts open, over the dashboard');
open.fire('click');
ok(veil.hidden === false, 'pressing Select Game does not open it');

/* THE MUTANT THE SOURCE CHECK CANNOT SEE: a swap that is present and never
   reached. Four tiles with no src is a picker of empty boxes. */
const got = tiles.map(t => t.img.src);
ok(got.every(s => s && s.indexOf('/art/') === 0),
   'the tile art never got its src, so the picker opens empty: ' + JSON.stringify(got));
ok(box.focused > 0, 'focus stays behind the picker, so Escape and Tab go to the page under it');

/* ---------- 2. it is fetched ONCE ------------------------------------------ */

console.log('\nand the art is fetched once');
tiles.forEach(t => { t.img.src = 'SENTINEL'; });
key('Escape');
open.fire('click');
ok(tiles.every(t => t.img.src === 'SENTINEL'),
   'every open re-assigns the art, so switching back and forth refetches it');

/* ---------- 3. closing ----------------------------------------------------- */

console.log('\nclosing it');
ok(veil.hidden === false, 'the picker did not reopen for the checks below');
x.fire('click');
ok(veil.hidden === true, 'the close button does not close it');
open.fire('click'); veil.fire('click', { target: veil });
ok(veil.hidden === true, 'clicking the backdrop does not close it');
/* ...and a click INSIDE must not fall through to it. */
open.fire('click'); veil.fire('click', { target: box });
ok(veil.hidden === false, 'a click inside the picker closes it as if it were the backdrop');
key('Escape');
ok(veil.hidden === true, 'Escape does not close it');
/* Escape with the picker already shut must not do anything. */
const before = open.focused;
key('Escape');
ok(open.focused === before, 'Escape keeps firing once the picker is closed');
/* Focus goes back where it came from, or the page loses the caret. */
open.fire('click'); key('Escape');
ok(open.focused > before, 'focus is not returned to the button that opened it');

/* ---------- 4. the loader --------------------------------------------------- */

console.log('\nswitching game');
open.fire('click');
loaderCalls = 0;
veil.fire('click', { target: box });
ok(loaderCalls === 0, 'a click that is not a tile puts the loader up over the dashboard');
veil.fire('click', { target: tiles[2] });
ok(loaderCalls === 1, 'choosing a game does not show the loader, so the switch looks hung');

console.log('\n' + (fails ? fails + ' check(s) FAILED\n' : 'all picker checks passed\n'));
process.exit(fails ? 1 : 0);
