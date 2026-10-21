/**
 * dropship-art-lightbox-harness.js -- node, no browser, no network.
 *
 * Runs the REAL lightbox() out of dropship/admin-art.php against a DOM
 * stub, together with the two functions it leans on.
 *
 * WHAT IT IS PROTECTING is what happens on CLOSE and on STEP. The
 * overlay is fixed at z-index 2000 over the whole page and registers a
 * document-level keydown listener, so a close that forgets either
 * leaves the admin on a covered page or leaks a listener per open --
 * both invisible until the fiftieth slot. Stepping rebuilds the overlay
 * rather than swapping the image, because the caption and the
 * dimensions have to change with it, so every step is also a close.
 *
 * Usage: node dropship-art-lightbox-harness.js
 */
'use strict';
const fs = require('fs');

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

const php = fs.readFileSync(__dirname + '/dropship/admin-art.php', 'utf8');
function lift(re, what) {
	const m = php.match(re);
	ok(!!m, what + ' could not be lifted from admin-art.php');
	return m ? m[0] : '';
}
const SRC = [
	lift(/\tfunction card\(el\) \{[^\n]*\n/, 'card()'),
	lift(/\tfunction lightboxShots\(\) \{[\s\S]*?\n\t\}/, 'lightboxShots()'),
	lift(/\tfunction lightbox\(img, slot\) \{[\s\S]*?\n\t\}/, 'lightbox()'),
].join('\n');

/* The caption carries a theme's own words, so it is textContent. The
   innerHTML left is for constant glyphs: the close X and two arrows. */
ok(!/innerHTML\s*=\s*[^;]*(name|path|dims)\b/.test(SRC),
   'the caption is built with innerHTML, and a theme names its own icons');

/*
 * THE OVERLAY IS APPENDED TO <body>, SO ITS CSS MUST NOT BE SCOPED TO
 * THE PAGE WRAPPER. The first version wrote `.dsart .lightbox` while
 * the element lived on body, so not one rule matched: an unstyled div
 * at the foot of the page, not centred, no backdrop, a fresh one every
 * click. A DOM stub cannot see that; the stylesheet can be read.
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
/* ON THE RULE THAT POSITIONS THEM, not merely on the selector: a
   narrow-screen override inside @media uses the same selector and
   satisfies a bare check while the arrows sit unplaced in the flow. */
ok(new RegExp('\\.' + cls + '\\s+\\.nav\\{[^}]*position:absolute').test(php),
   'the step arrows are not positioned, so they land in the flow instead of beside the picture');

/* ---------- the stub ---------------------------------------------------- */

function makeEl(tag) {
	return {
		tagName: tag.toUpperCase(),
		className: '', src: '', alt: '', textContent: '', type: '', innerHTML: '',
		disabled: false, attrs: {},
		/* Every real element has one; card() walks up calling
		   classList.contains and would throw without it. */
		classList: { contains: () => false },
		parentElement: null,
		children: [], parent: null, listeners: {},
		setAttribute(n, v) { this.attrs[n] = v; },
		getAttribute(n) { return n in this.attrs ? this.attrs[n] : null; },
		appendChild(c) { c.parent = this; this.children.push(c); return c; },
		addEventListener(ev, fn) { (this.listeners[ev] = this.listeners[ev] || []).push(fn); },
		remove() {
			this.removed = true;
			if (this.parent) this.parent.children = this.parent.children.filter(c => c !== this);
		},
		fire(ev, target) {
			(this.listeners[ev] || []).forEach(f => f({
				target: target || this,
				stopPropagation() { this.stopped = true; },
				preventDefault() {},
			}));
		},
		querySelector() { return null; },
	};
}

/* A page of three slots, each with one picture, in document order. */
function makePage() {
	const slots = [];
	const shots = [];
	['bunker/1', 'bunker/2', 'bunker/3'].forEach(function (name, i) {
		const img = makeEl('img');
		img.src = '/staking/dropship/images/live/' + name + '.jpg?v=' + (100 + i);
		const slot = {
			classList: { contains: c => c === 'slot' },
			_name: name,
			querySelector(sel) {
				const map = { name: 'Bunker, scene ' + (i + 1) + ' of 3',
				              path: 'images/live/' + name + '.jpg',
				              dims: '1080x1080 - 200KB' };
				const key = sel.replace('.', '');
				if (!(key in map)) return null;
				const el = makeEl('span');
				el.textContent = map[key];
				return el;
			},
		};
		img.parentElement = slot;
		slot.parentElement = null;
		slots.push(slot);
		shots.push(img);
	});
	return { slots: slots, shots: shots };
}

function run(startAt) {
	const page = makePage();
	const body = makeEl('body');
	const docListeners = {};
	let standing = null;
	const doc = {
		body: body,
		createElement: makeEl,
		querySelector: q => (standing && !standing.removed
			&& q.indexOf(standing.className) !== -1) ? standing : null,
		addEventListener(ev, fn) { (docListeners[ev] = docListeners[ev] || []).push(fn); },
		removeEventListener(ev, fn) {
			docListeners[ev] = (docListeners[ev] || []).filter(f => f !== fn);
		},
	};
	const root = { querySelectorAll: () => page.shots };
	const fn = new Function('document', 'root', 'Array',
		SRC + '\nreturn lightbox;')(doc, root, Array);
	fn(page.shots[startAt], page.slots[startAt]);
	const api = {
		page: page, body: body,
		get box() { return body.children[body.children.length - 1] || null; },
		keys: () => (docListeners.keydown || []).length,
		key: k => (docListeners.keydown || []).slice().forEach(f =>
			f({ key: k, preventDefault() {} })),
	};
	Object.defineProperty(api, 'shown', {
		get() {
			const b = api.box;
			const im = b && b.children.filter(c => c.tagName === 'IMG')[0];
			return im ? im.src : '';
		},
	});
	return api;
}

/* ---------- opening ----------------------------------------------------- */

const a = run(0);
ok(!!a.box && a.box.className === 'ds-lightbox', 'no overlay was added to the page');
ok(a.shown.indexOf('bunker/1') !== -1, 'the wrong picture opened: ' + a.shown);
ok(a.keys() === 1, 'Escape is not listened for, or is listened for more than once');
const xb = a.box.children.filter(c => c.className === 'x')[0];
ok(!!xb, 'there is no close button');
xb.fire('click');
ok(a.body.children.length === 0, 'the close button does not close it');
ok(a.keys() === 0, 'closing with the button leaves its listener behind');

/* ---------- stepping ---------------------------------------------------- */

const b = run(0);
b.key('ArrowRight');
ok(b.shown.indexOf('bunker/2') !== -1, 'right arrow did not move on: ' + b.shown);
ok(b.body.children.length === 1,
   'stepping stacked a second overlay: ' + b.body.children.length);
ok(b.keys() === 1, 'stepping leaked a keydown listener: ' + b.keys());
b.key('ArrowRight');
ok(b.shown.indexOf('bunker/3') !== -1, 'right arrow did not move on twice');
/* STOPS AT THE END rather than wrapping: with seventy slots, silently
   looping to the first is disorienting. */
b.key('ArrowRight');
ok(b.shown.indexOf('bunker/3') !== -1, 'the last picture wrapped around to the first');
ok(b.body.children.length === 1, 'stepping past the end stacked an overlay');
b.key('ArrowLeft');
ok(b.shown.indexOf('bunker/2') !== -1, 'left arrow did not move back');
b.key('ArrowLeft');
b.key('ArrowLeft');
ok(b.shown.indexOf('bunker/1') !== -1, 'the first picture wrapped around to the last');
ok(b.keys() === 1, 'listeners accumulated across steps: ' + b.keys());
/* THE CAPTION FOLLOWS. Stepping rebuilds rather than swapping the src
   precisely so the words and dimensions change with the picture. */
const capb = b.box.children.filter(c => c.className === 'cap')[0];
ok(capb && JSON.stringify(capb.children.map(c => c.textContent)).indexOf('bunker/1.jpg') !== -1,
   'the caption still describes the picture that was open before');

/* ---------- the arrows on screen ---------------------------------------- */

const c = run(1);
const navs = c.box.children.filter(x => x.className && x.className.indexOf('nav') === 0);
ok(navs.length === 2, 'there are not two arrow buttons: ' + navs.length);
ok(navs.every(n => n.getAttribute('aria-label')), 'an arrow button has no accessible name');
navs.filter(n => n.className.indexOf('next') !== -1)[0].fire('click');
ok(c.shown.indexOf('bunker/3') !== -1, 'the next arrow does not step: ' + c.shown);
/* AT THE ENDS THEY ARE DISABLED rather than silently inert. */
const d = run(0);
const dnav = d.box.children.filter(x => x.className && x.className.indexOf('nav') === 0);
ok(dnav.filter(n => n.className.indexOf('prev') !== -1)[0].disabled === true,
   'the back arrow is live on the first picture');
ok(dnav.filter(n => n.className.indexOf('next') !== -1)[0].disabled === false,
   'the forward arrow is dead on the first picture');
/* CLICKING AN ARROW MUST NOT CLOSE. The backdrop closes on click and
   the arrows sit on it, so the handler has to stop the bubble. */
ok(/stopPropagation\(\)/.test(SRC),
   'an arrow click bubbles to the backdrop, which closes the overlay');

/* ---------- closing ----------------------------------------------------- */

const e = run(0);
e.box.fire('click', e.box);
ok(e.box === null || e.body.children.length === 0, 'clicking the backdrop does not close it');
ok(e.keys() === 0, 'the keydown listener outlives the overlay');

const f = run(0);
const fimg = f.box.children.filter(x => x.tagName === 'IMG')[0];
f.box.fire('click', fimg);
ok(f.body.children.length === 1, 'clicking the picture itself closes the overlay');

const g = run(0);
g.key('a');
ok(g.body.children.length === 1, 'any key closes the overlay, not just Escape');
g.key('Escape');
ok(g.body.children.length === 0, 'Escape does not close the overlay');
ok(g.keys() === 0, 'closing with Escape leaves its own listener behind');

let threw = false;
try { const h = run(0); h.box.fire('click', h.box); h.key('Escape'); h.key('ArrowRight'); }
catch (err) { threw = true; }
ok(!threw, 'stepping after the overlay closed throws, taking the page scripts with it');

console.log(fails ? '\n' + fails + ' check(s) FAILED' : '\nall good');
process.exit(fails ? 1 : 0);
