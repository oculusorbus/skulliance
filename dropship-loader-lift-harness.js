/**
 * dropship-loader-lift-harness.js -- node, no browser, no network.
 *
 * Runs the REAL lift script out of dropship/header.php against a DOM stub.
 *
 * WHAT IT IS PROTECTING is the one failure that is worse than the bug it
 * fixes. The overlay covers the entire page at z-index 99, so if it never
 * lifts the site is gone -- and the obvious way to lift it, dropship.js's
 * $(window).on('load'), depends on jQuery arriving from Google's CDN,
 * which Brave's Shields block for some stakers. A loader that never lifts
 * is far worse than one that lifts early, so this pins three things: the
 * listener is plain DOM, a page already loaded when the script runs lifts
 * immediately rather than waiting for an event that has been and gone,
 * and there is a cap behind both of them.
 *
 * Usage: node dropship-loader-lift-harness.js
 */
'use strict';
const fs = require('fs');

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

/* ---------- lift the real script out of the template -------------------- */

const hdr = fs.readFileSync(__dirname + '/dropship/header.php', 'utf8');
const m = hdr.match(/<script>\s*(\(function \(\) \{[\s\S]*?\}\)\(\);)\s*<\/script>/);
ok(!!m, 'the lift script could not be found in header.php, so nothing below proves anything');
const SRC = m ? m[1] : '(function(){})();';
ok(/addEventListener/.test(SRC) && !/\$\(/.test(SRC),
   'the overlay is lifted through jQuery, which Brave blocks for some stakers');

/* ---------- the stub ---------------------------------------------------- */

function run(readyState, missing) {
	const el = missing ? null : { style: {} };
	const listeners = {};
	const timers = [];
	const doc = {
		readyState: readyState,
		getElementById(id) { return (id === 'loading') ? el : null; },
	};
	const win = {
		addEventListener(ev, fn) { (listeners[ev] = listeners[ev] || []).push(fn); },
		setTimeout(fn, ms) { timers.push({ fn: fn, ms: ms }); return timers.length; },
	};
	const fn = new Function('document', 'window', 'setTimeout', SRC);
	fn(doc, win, win.setTimeout);
	return {
		el: el,
		hidden: function () { return !!el && el.style.display === 'none'; },
		fire: function (ev) { (listeners[ev] || []).forEach(function (f) { f(); }); },
		has: function (ev) { return (listeners[ev] || []).length > 0; },
		timers: timers,
	};
}

/* ---------- what it has to do ------------------------------------------- */

const loading = run('loading');
ok(!loading.hidden(), 'the overlay is lifted before the page has finished loading');
ok(loading.has('load'), 'nothing listens for the page finishing, so the overlay waits on the timeout');
loading.fire('load');
ok(loading.hidden(), 'the page finished loading and the overlay stayed up');

/* ALREADY COMPLETE. A cached page, or a script that runs late, has missed
   the load event entirely -- waiting for one that has been and gone is how
   an overlay strands a page for its full timeout. */
const done = run('complete');
ok(done.hidden(), 'a page that had already finished loading keeps the overlay up anyway');

/* THE CAP. Behind both paths, because a subresource that hangs rather than
   fails never fires load at all. */
const capped = run('loading');
ok(capped.timers.length === 1, 'there is no timeout behind the load event');
if (capped.timers.length) {
	ok(capped.timers[0].ms >= 3000 && capped.timers[0].ms <= 20000,
	   'the timeout is ' + capped.timers[0].ms + 'ms, which is either a flash or a hostage');
	capped.timers[0].fn();
	ok(capped.hidden(), 'the timeout fires and the overlay stays up');
}

/* IDEMPOTENT. Both paths can fire on one page load and the second must not
   undo anything -- nor throw, which would take the rest of the page's
   scripts with it. */
const both = run('loading');
both.fire('load');
both.timers[0].fn();
ok(both.hidden(), 'the timeout firing after the load event puts the overlay back');

/*
 * NO ELEMENT, NO WORK. The overlay only renders on a theme change, so on
 * every other page this script is not emitted -- but if it ever is, it
 * must not throw, because an uncaught error here takes every script below
 * it on the page with it.
 *
 * DRIVEN TO THE END, not just constructed. An early return is invisible at
 * construction time: the listener registers and the timer is set either
 * way, and the deref only happens when one of them FIRES. A test that
 * stops at construction passes with the guard deleted.
 */
let threw = '';
try {
	const none = run('loading', true);
	none.fire('load');
	none.timers.forEach(function (t) { t.fn(); });
	const none2 = run('complete', true);
	none2.timers.forEach(function (t) { t.fn(); });
} catch (e) { threw = String(e && e.message || e); }
ok(threw === '', 'the lift script throws when there is no overlay on the page: ' + threw);

console.log(fails ? '\n' + fails + ' check(s) FAILED' : '\nall good');
process.exit(fails ? 1 : 0);
