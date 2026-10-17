/* dhcfighters-anchor-harness.js - node, no browser, no network.
 *
 * The mobile Games chip points at dhcfighters.php#drops, and arriving from
 * another page landed you at the top of the page instead of at the panel.
 *
 * WHY A HARNESS THAT RUNS THE CODE, AND NOT ONE THAT READS IT. The first fix
 * for this was reasoned from the source, shipped, and did not work -- twice.
 * The bug is not in what the landing code says, it is in WHEN it runs
 * relative to rpaint(), which hides all but one page of roster cards and
 * takes the height out from under the scroll. Only executing the two in
 * order against a document that actually shrinks can tell the difference,
 * so this builds a miniature DOM, runs the REAL landing block lifted out of
 * the page, and asserts where the reader ends up.
 *
 * Usage: node dhcfighters-anchor-harness.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');

let fail = 0;
const ok = (c, w) => { if (!c) { fail++; console.log('  FAIL  ' + w); } };

const page = fs.readFileSync(path.join(__dirname, 'dhcfighters.php'), 'utf8');

/* Every stub window carries location.search, because the block reads it for
   the ?landdebug=1 readout. A stub that is missing a property the real code
   touches fails as a TypeError inside the vm, which looks like a broken
   harness rather than the missing stub it is. */

/*
 * THE REAL BLOCK, lifted whole. Anchored on the banner comment and closed on
 * the IIFE's own terminator, so an edit that moves it moves this with it.
 */
const a = page.indexOf('  (function () {\n    var hash = window.location.hash;');
/* CLOSED ON ITS OWN INDENTATION, anchored to a line start. A plain
   indexOf('  })();') matched the nested debug IIFE's '      })();' -- two of
   its six spaces plus the terminator -- and lifted a truncated block that
   would not parse. */
const close = /\n {2}\}\)\(\);/.exec(page.slice(a));
const b = close ? a + close.index + close[0].length : -1;
ok(a > -1 && b > a, 'the anchor-landing block is gone from dhcfighters.php');
const block = page.slice(a, b);

/*
 * ORDERING IS THE WHOLE BUG, so it is asserted directly on the source as
 * well as exercised below: the landing must come AFTER the rpaint() that
 * collapses the roster, or it measures a document that is about to lose
 * most of its height.
 */
const paintAt = page.lastIndexOf('\n  rpaint();');
ok(paintAt > -1 && paintAt < a,
   'the landing runs BEFORE rpaint(), which then hides all but one page of roster cards and '
 + 'clamps the scroll back to the top -- this is the original bug');

/* ---- a document that shrinks, like the real one ---------------------- */

/*
 * A FAKE DOM, deliberately tiny: the only physics that matter are that the
 * target has a position, that scrollIntoView moves the viewport to it, and
 * that hiding cards above it moves it up. Everything else is noise.
 */
function makeWorld(opts) {
	const cards = opts.cards, perPage = 20, cardH = 300;
	const headerH = 1200;                 // masthead, picker, save bar
	let visible = cards;                  // every card rendered server-side
	let scrollY = 0;
	const frames = [];

	const aboveH = () => headerH + visible * cardH;

	const el = {
		get top() { return aboveH() - scrollY; },
		getBoundingClientRect() { return { top: el.top }; },
		scrollIntoView() {
			/* The browser cannot scroll past the end of the document. That
			   clamp is the original bug: shrink the page and the position
			   you asked for no longer exists. */
			const docH = aboveH() + opts.belowH;
			scrollY = Math.max(0, Math.min(aboveH(), docH - opts.viewportH));
			listeners.scroll.forEach(f => f());
		},
	};
	const listeners = { scroll: [], load: [], wheel: [], touchstart: [], keydown: [] };
	const world = {
		el, listeners,
		get scrollY() { return scrollY; },
		/* The reader dragging the page somewhere of their own choosing. */
		scrollTo(y) { scrollY = y; },
		paint() {                          // what rpaint() does
			visible = Math.min(cards, perPage);
			const docH = aboveH() + opts.belowH;
			scrollY = Math.max(0, Math.min(scrollY, docH - opts.viewportH));
		},
		frames,
	};
	return world;
}

function run(opts) {
	const world = makeWorld(opts);
	let now = 0;
	const queue = [];
	const ctx = {
		window: {
			location: { hash: '#drops', search: '' },
			addEventListener: (n, f) => { (world.listeners[n] || (world.listeners[n] = [])).push(f); },
		},
		document: { querySelector: (s) => (s === '#drops' ? world.el : null) },
		requestAnimationFrame: (f) => { queue.push(f); },
		setTimeout: (f) => { queue.push(f); },
		Date: { now: () => now },
		Math,
	};
	vm.createContext(ctx);

	if (opts.paintFirst) world.paint();
	vm.runInContext(block, ctx);
	if (!opts.paintFirst) world.paint();   // the original order

	/* Drain the frame queue, advancing the clock like a real one. */
	for (let i = 0; i < 400 && queue.length; i++) {
		now += 16;
		queue.shift()();
	}
	/* Then the load event, and drain again. */
	(world.listeners.load || []).forEach(f => f());
	for (let i = 0; i < 200 && queue.length; i++) { now += 16; queue.shift()(); }

	return { world, top: world.el.top };
}

console.log('a deep roster still lands on the drop panel');
{
	/* 60 Fighters: the server renders all 60, rpaint() hides 40. A player
	   with 20 or fewer never saw this bug, which is exactly why it did not
	   reproduce for me. */
	const deep = run({ cards: 60, belowH: 2400, viewportH: 800, paintFirst: true });
	ok(Math.abs(deep.top) <= 2,
	   'with 60 Fighters the panel ends up ' + Math.round(deep.top) + 'px from the top of the '
	 + 'viewport instead of at it');
	ok(deep.world.scrollY > 0, 'the page never scrolled at all');

	/* A shallow roster was always fine and must stay fine. */
	const shallow = run({ cards: 8, belowH: 2400, viewportH: 800, paintFirst: true });
	ok(Math.abs(shallow.top) <= 2,
	   'with 8 Fighters the panel is ' + Math.round(shallow.top) + 'px off');
}

console.log('\nthe fixture has teeth: the old two-liner fails it');
{
	/*
	 * THE PREVIOUS ATTEMPT, VERBATIM, RUN THROUGH THE SAME WORLD.
	 *
	 * Without this the suite is self-congratulatory: every assertion above
	 * would pass against a fixture too gentle to fail anything.
	 *
	 * The case that separates them is NOT invented. The old version's only
	 * correction was a load listener, so it has no correction at all when
	 * load has already fired by the time the script runs -- a restored page,
	 * or a script that re-executes. The new one's rAF watch does not depend
	 * on load, so it corrects either way. Modelled by simply not dispatching
	 * load, which is the honest way to say "that listener never ran".
	 *
	 * I did NOT model roster art growing after load, which was the first
	 * thing I reached for: .dhcf-card .art is aspect-ratio:1 with the image
	 * absolutely positioned inside it, so those cards do not change height
	 * when an image decodes. A fixture for a thing that cannot happen would
	 * have had me "fixing" it.
	 */
	const legacy = `
	  (function () {
	    var hash = window.location.hash;
	    if (!hash || hash.length < 2) return;
	    var el;
	    try { el = document.querySelector(hash); } catch (e) { return; }
	    if (!el) return;
	    var land = function () { el.scrollIntoView({ block: 'start' }); };
	    land();
	    window.addEventListener('load', land);
	  })();`;

	/* Landing runs BEFORE the collapse, and load never arrives. */
	const trial = (src) => {
		const world = makeWorld({ cards: 60, belowH: 2400, viewportH: 800 });
		let now = 0; const queue = [];
		const ctx = {
			window: { location: { hash: '#drops', search: '' },
			          addEventListener: (n, f) => { (world.listeners[n] || (world.listeners[n] = [])).push(f); } },
			document: { querySelector: () => world.el },
			requestAnimationFrame: (f) => queue.push(f),
			setTimeout: (f) => queue.push(f),
			Date: { now: () => now }, Math,
		};
		vm.createContext(ctx);
		vm.runInContext(src, ctx);
		world.paint();                      // rpaint() collapses the roster
		for (let i = 0; i < 400 && queue.length; i++) { now += 16; queue.shift()(); }
		return world;
	};

	const old = trial(legacy);
	ok(Math.abs(old.el.top) > 2,
	   'the old land-once-and-on-load version lands correctly with no load event, so this '
	 + 'fixture does not discriminate and the checks above prove nothing');

	const now = trial(block);
	ok(Math.abs(now.el.top) <= 2,
	   'the shipped landing is ' + Math.round(now.el.top) + 'px off when load never fires');
}

console.log('\nit gives way to the reader');
{
	/* A retry loop that fights someone scrolling is worse than the bug. */
	for (const ev of ['wheel', 'touchstart', 'keydown']) {
		const world = makeWorld({ cards: 60, belowH: 2400, viewportH: 800 });
		let now = 0; const queue = [];
		const ctx = {
			window: { location: { hash: '#drops', search: '' },
			          addEventListener: (n, f) => { (world.listeners[n] || (world.listeners[n] = [])).push(f); } },
			document: { querySelector: () => world.el },
			requestAnimationFrame: (f) => queue.push(f),
			setTimeout: (f) => queue.push(f),
			Date: { now: () => now }, Math,
		};
		vm.createContext(ctx);
		world.paint();
		vm.runInContext(block, ctx);
		/*
		 * The reader intervenes AND MOVES AWAY. Dispatching the event
		 * without moving proved nothing: the loop would re-scroll to where
		 * the page already was, so scrollY was unchanged whether or not the
		 * listener existed, and deleting the wheel handler passed this.
		 */
		(world.listeners[ev] || []).forEach(f => f());
		world.scrollTo(120);
		const at = world.scrollY;
		for (let i = 0; i < 100 && queue.length; i++) { now += 16; queue.shift()(); }
		ok(world.scrollY === at,
		   'a ' + ev + ' from the reader does not stop the landing; it yanked the page from '
		 + at + ' back to ' + world.scrollY);
	}
	ok(/addEventListener\('wheel'/.test(block) && /addEventListener\('touchstart'/.test(block),
	   'the landing does not listen for the reader starting to scroll');
}

console.log('\nit stops, and it does not run where it should not');
{
	/* Unbounded retries on a page whose layout never settles would spin for
	   the life of the tab. */
	const r = run({ cards: 60, belowH: 2400, viewportH: 800, paintFirst: true });
	ok(/Date\.now\(\) < until/.test(block), 'the retry loop has no deadline');
	/* COMMENTS STRIPPED FIRST. The block's own comment explains why there is
	   no early exit, and a bare /settled/ matched that sentence -- the test
	   was reading my prose rather than the code, and passed with the early
	   exit still in. */
	const code = block.replace(/\/\*[\s\S]*?\*\//g, '').replace(/^\s*\/\/.*$/gm, '');
	ok(!/settled/.test(code),
	   'the loop exits early once the layout "looks right", which is exactly when the previous '
	 + 'version stopped too');
	ok(!/return stop\(\)/.test(code),
	   'the loop still has an early exit inside it; only the deadline and the reader may end it');

	/* No hash, nothing to do. A page opened normally must not be scrolled. */
	let touched = false;
	const ctx = {
		window: { location: { hash: '', search: '' }, addEventListener: () => {} },
		document: { querySelector: () => { touched = true; return null; } },
		requestAnimationFrame: () => {}, setTimeout: () => {}, Date: { now: () => 0 }, Math,
	};
	vm.createContext(ctx);
	vm.runInContext(block, ctx);
	ok(!touched, 'the landing looks for a target even with no hash, and scrolls a normal visit');

	/* A hash that is not a valid selector must not throw on the page. */
	let threw = false;
	const ctx2 = {
		window: { location: { hash: '#2cool', search: '' }, addEventListener: () => {} },
		document: { querySelector: () => { throw new Error('bad selector'); } },
		requestAnimationFrame: () => {}, setTimeout: () => {}, Date: { now: () => 0 }, Math,
	};
	vm.createContext(ctx2);
	try { vm.runInContext(block, ctx2); } catch (e) { threw = true; }
	ok(!threw, 'a hash that is not a valid CSS selector takes the whole page script down');
}

console.log('\n' + (fail ? fail + ' FAILED\n' : 'all good\n'));
process.exit(fail ? 1 : 0);
