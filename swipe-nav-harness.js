/**
 * swipe-nav-harness.js -- node, no browser, no network.
 *
 * Runs the REAL swipe-nav.js against a hand-built DOM stub. It is a stub and
 * not jsdom on purpose: the only DOM this file touches is listeners,
 * classList, querySelectorAll, offsetParent and getComputedStyle, and a stub
 * that small is readable in one screen, where a jsdom dependency would be a
 * build step nobody on this repo has.
 *
 * WHAT IT IS ACTUALLY PROTECTING is the list of gestures that must NOT
 * navigate. Getting "swipe left goes right" correct is one line; giving way
 * to a sideways scroller, a pinch, a slow wandering scroll, an open burger
 * and an open dialog is the part that decides whether the feature is an
 * accelerator or a page that fights you.
 *
 * Usage: node swipe-nav-harness.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

/* ---------- the stub ---------------------------------------------------- */

function makeEl(tag, attrs) {
	const el = {
		tagName: tag.toUpperCase(),
		attrs: Object.assign({}, attrs),
		classes: new Set(),
		parentNode: null,
		children: [],
		style: {},
		isContentEditable: false,
		scrollWidth: 0, clientWidth: 0,
		hidden: false,
		_hiddenLink: false,
		clicked: 0,
		nodeType: 1,
		hasAttribute(n) { return n in this.attrs; },
		getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; },
		click() { this.clicked++; },
		get offsetParent() { return this._hiddenLink ? null : doc.body; },
		querySelectorAll(sel) { return matchAll(this, sel); },
		querySelector(sel) { return matchAll(this, sel)[0] || null; }
	};
	el.classList = {
		add: c => el.classes.add(c),
		remove: c => el.classes.delete(c),
		contains: c => el.classes.has(c),
		toggle: (c, on) => on ? el.classes.add(c) : el.classes.delete(c)
	};
	if (attrs && attrs.class) String(attrs.class).split(/\s+/).forEach(c => el.classes.add(c));
	return el;
}
function append(parent, child) { child.parentNode = parent; parent.children.push(child); return child; }
function walk(root, out) { root.children.forEach(c => { out.push(c); walk(c, out); }); return out; }
/* Only the selector shapes swipe-nav.js actually uses. */
function matchAll(root, sel) {
	return walk(root, []).filter(el => {
		if (sel === 'a[data-sec]') return el.tagName === 'A' && 'data-sec' in el.attrs;
		const m = /^a\[data-sec="([^"]+)"\]$/.exec(sel);
		if (m) return el.tagName === 'A' && el.attrs['data-sec'] === m[1];
		return false;
	});
}

const listeners = {};
const doc = {
	body: null,
	byId: {},
	getElementById(id) { return this.byId[id] || null; },
	addEventListener(type, fn) { (listeners[type] = listeners[type] || []).push(fn); },
	querySelectorAll() { return []; }
};
doc.body = makeEl('body');

const win = {
	innerWidth: 390,
	getComputedStyle(el) { return {overflowX: el.style.overflowX || 'visible'}; }
};

/* ---------- the page under test ----------------------------------------- */

const nav = makeEl('nav', {id: 'rl-nav'});
doc.byId['rl-nav'] = nav;
append(doc.body, nav);
const SECS = ['locations', 'realm', 'map', 'raids', 'realms'];
const links = SECS.map(s => append(nav, makeEl('a', {'data-sec': s})));
links[0].classes.add('on');
/* The nav strip is itself a horizontal scroller under 700px. */
nav.scrollWidth = 600; nav.clientWidth = 390; nav.style.overflowX = 'auto';

const navbar = makeEl('div', {id: 'navbar'});
doc.byId['navbar'] = navbar;
append(doc.body, navbar);

const panel = append(doc.body, makeEl('div', {id: 'locations'}));
const table = append(panel, makeEl('div', {class: 'wide'}));
table.scrollWidth = 900; table.clientWidth = 360; table.style.overflowX = 'auto';
const cellInTable = append(table, makeEl('span', {}));
const field = append(panel, makeEl('input', {}));
const plain = append(panel, makeEl('p', {}));

/* ---------- load the real file ------------------------------------------ */

const src = fs.readFileSync(path.join(__dirname, 'swipe-nav.js'), 'utf8');
new Function('window', 'document', src)(win, doc);
ok(typeof win.SkullSwipe === 'object' && typeof win.SkullSwipe.init === 'function',
   'swipe-nav.js did not export SkullSwipe.init');

let dialogOpen = false;
win.SkullSwipe.init('rl-nav', {blocked: () => dialogOpen});

/* ---------- driving it -------------------------------------------------- */

function fire(type, ev) { (listeners[type] || []).forEach(fn => fn(ev)); }
function swipe(opts) {
	const o = Object.assign({from: plain, dx: -120, dy: 0, ms: 200, fingers: 1}, opts);
	const touches = [];
	for (let i = 0; i < o.fingers; i++) touches.push({clientX: 200, clientY: 300});
	fire('touchstart', {touches, target: o.from});
	const then = Date.now;
	Date.now = () => then() + o.ms;
	fire('touchend', {changedTouches: [{clientX: 200 + o.dx, clientY: 300 + o.dy}], target: o.from});
	Date.now = then;
}
function lit(i) { links.forEach((l, n) => l.classList.toggle('on', n === i)); }
function clickedIndex() {
	for (let i = 0; i < links.length; i++) if (links[i].clicked) return i;
	return -1;
}
function reset(at) { links.forEach(l => l.clicked = 0); lit(at); }

function run(name, setup, expect) {
	reset(typeof setup.at === 'number' ? setup.at : 0);
	const before = Object.assign({}, {w: win.innerWidth});
	if (setup.width) win.innerWidth = setup.width;
	if (setup.hide !== undefined) links[setup.hide]._hiddenLink = true;
	if (setup.burger) navbar.classList.add('show-menu');
	if (setup.dialog) dialogOpen = true;
	swipe(setup);
	const got = clickedIndex();
	ok(got === expect, name + ': expected ' + (expect < 0 ? 'no navigation' : 'link ' + expect)
	   + ', got ' + (got < 0 ? 'no navigation' : 'link ' + got));
	if (setup.hide !== undefined) links[setup.hide]._hiddenLink = false;
	navbar.classList.remove('show-menu');
	dialogOpen = false;
	win.innerWidth = before.w;
}

console.log('\nit navigates');
run('swipe left goes to the next section',      {at: 0, dx: -120}, 1);
run('swipe right goes to the previous section', {at: 2, dx:  120}, 1);
run('a link hidden at this width is skipped',   {at: 0, dx: -120, hide: 1}, 2);
run('the last section is a wall, not a wrap',   {at: 4, dx: -120}, -1);
run('the first section is a wall too',          {at: 0, dx:  120}, -1);

console.log('\nit stands down');
run('a desktop window',                         {at: 0, dx: -120, width: 1280}, -1);
run('a pinch',                                  {at: 0, dx: -120, fingers: 2}, -1);
run('a short flick',                            {at: 0, dx:  -30}, -1);
run('a diagonal drag, which is a scroll',       {at: 0, dx: -120, dy: 110}, -1);
run('a slow drag, which is also a scroll',      {at: 0, dx: -120, ms: 1500}, -1);
run('a drag that starts on the nav strip',      {at: 0, dx: -120, from: links[2]}, -1);
run('a drag inside a sideways-scrolling table', {at: 0, dx: -120, from: cellInTable}, -1);
run('a drag that starts in a field',            {at: 0, dx: -120, from: field}, -1);
run('the burger menu being open',               {at: 0, dx: -120, burger: true}, -1);
run('a dialog being open',                      {at: 0, dx: -120, dialog: true}, -1);

console.log(fails ? '\nFAILED: ' + fails + ' check(s)' : '\nall swipe nav checks passed');
process.exit(fails ? 1 : 0);
