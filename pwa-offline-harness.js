/* pwa-offline-harness.js - node, no browser, no network.
 *
 * service-worker.js now intercepts page navigations. That is the most
 * dangerous file in the repo by blast radius: it sits in front of EVERY
 * request the installed app makes, it updates out of band, and a mistake in
 * it does not break one page, it breaks all of them with no address bar to
 * escape from. php -l cannot see it and neither can any test that only reads
 * the source, so the real handlers are loaded here and driven.
 *
 * What must stay true:
 *   - a POST navigation is NEVER intercepted. Form submits pay rewards; a
 *     replayed or substituted response could pay one twice.
 *   - sub-resources, fetch() and the ajax/ endpoints are NEVER intercepted.
 *   - cross-origin is never intercepted.
 *   - a working navigation gets the real response, untouched.
 *   - a failing or hanging one gets the offline page.
 *   - with no cached offline page it falls through to the network rather
 *     than inventing a response.
 *
 * Usage: node pwa-offline-harness.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');

let fail = 0;
const ok = (cond, what) => { if (!cond) { fail++; console.log('  FAIL  ' + what); } };

/* ---- a service worker global, enough of one to run the real file ---- */
function makeSW({ offlineCached = true, netBehaviour = 'ok' } = {}) {
	const handlers = {};
	const log = { fetches: [], cacheAdds: [], deleted: [] };

	const OFFLINE_RES = { body: 'OFFLINE_PAGE', __offline: true };

	const store = new Map();
	if (offlineCached) store.set('/staking/offline.html', OFFLINE_RES);

	const cacheObj = {
		add: (req) => { log.cacheAdds.push(String(req.url || req)); return Promise.resolve(); },
		match: (url) => Promise.resolve(store.get(String(url)) || undefined),
	};

	const sandbox = {
		self: {
			addEventListener: (ev, fn) => { handlers[ev] = fn; },
			skipWaiting: () => {},
			clients: { claim: () => Promise.resolve() },
			location: { origin: 'https://skulliance.io' },
		},
		caches: {
			open: () => Promise.resolve(cacheObj),
			keys: () => Promise.resolve(['skulliance-shell-v1', 'old-cache']),
			delete: (k) => { log.deleted.push(k); return Promise.resolve(true); },
			match: (url) => cacheObj.match(url),
		},
		fetch: (req) => {
			log.fetches.push(String(req.url || req));
			if (netBehaviour === 'reject') return Promise.reject(new TypeError('Failed to fetch'));
			if (netBehaviour === 'hang')   return new Promise(() => {});
			return Promise.resolve({ body: 'REAL_PAGE', __real: true });
		},
		Request: function (url, opts) { this.url = url; this.opts = opts; },
		URL, setTimeout, clearTimeout, Promise, console,
	};
	sandbox.self.location = { origin: 'https://skulliance.io' };
	vm.createContext(sandbox);
	vm.runInContext(fs.readFileSync(path.join(__dirname, 'service-worker.js'), 'utf8'), sandbox);
	return { handlers, log, sandbox, OFFLINE_RES };
}

function navEvent(url, { method = 'GET', mode = 'navigate' } = {}) {
	let responded = null;
	return {
		request: { url, method, mode },
		respondWith: (p) => { responded = p; },
		waitUntil: () => {},
		get responded() { return responded; },
	};
}

/* ---------------------------------------------------------------- */
console.log('requests the worker must not touch');
{
	const { handlers } = makeSW();
	const cases = [
		['a form POST (a submit can pay a reward)', 'https://skulliance.io/staking/missions.php', { method: 'POST' }],
		['a stylesheet',       'https://skulliance.io/staking/dist/flexbox.css', { mode: 'no-cors' }],
		['an ajax endpoint',   'https://skulliance.io/staking/ajax/dhc-delete-fighter.php', { mode: 'cors' }],
		['an image',           'https://skulliance.io/staking/dhc/web/250/torso/x.png', { mode: 'no-cors' }],
		['another origin',     'https://discord.com/api/webhooks/x', { mode: 'navigate' }],
	];
	for (const [label, url, opts] of cases) {
		const e = navEvent(url, opts);
		handlers.fetch(e);
		ok(e.responded === null, label + ' was intercepted; it must fall through untouched');
	}
}

console.log('\na navigation that works is passed through unchanged');
(async () => {
	{
		const { handlers, log } = makeSW({ netBehaviour: 'ok' });
		const e = navEvent('https://skulliance.io/staking/dhcfighters.php');
		handlers.fetch(e);
		ok(e.responded !== null, 'a same-origin GET navigation was not handled at all');
		const res = await e.responded;
		ok(res && res.__real === true, 'a working navigation did not get the real response back');
		ok(log.fetches.length === 1, 'the worker fetched ' + log.fetches.length + ' times for one navigation');
	}

	console.log('\na navigation that fails gets the offline page');
	{
		const { handlers } = makeSW({ netBehaviour: 'reject' });
		const e = navEvent('https://skulliance.io/staking/dhcfighters.php');
		handlers.fetch(e);
		const res = await e.responded;
		ok(res && res.__offline === true, 'a rejected navigation did not fall back to the offline page');
	}

	console.log('\nand so does one that hangs');
	{
		const { handlers, sandbox } = makeSW({ netBehaviour: 'hang' });
		/* Drive the clock rather than waiting 20 real seconds. */
		const real = sandbox.setTimeout;
		const pending = [];
		sandbox.setTimeout = (fn, ms) => { pending.push({ fn, ms }); return pending.length; };
		sandbox.clearTimeout = () => {};
		const { handlers: h2 } = (() => ({ handlers }))();
		const e = navEvent('https://skulliance.io/staking/dhcfighters.php');
		h2.fetch(e);
		ok(pending.length === 1, 'the hang path set ' + pending.length + ' timers, expected 1');
		ok(pending[0].ms >= 15000,
		   'the navigation timeout is ' + pending[0].ms + 'ms; under ~15s it starts cutting off slow-but-working pages');
		pending.forEach(t => t.fn());
		const res = await e.responded;
		ok(res && res.__offline === true, 'a hanging navigation did not fall back to the offline page');
		sandbox.setTimeout = real;
	}

	console.log('\nwith nothing cached it must not invent a response');
	{
		const { handlers, log } = makeSW({ netBehaviour: 'reject', offlineCached: false });
		const e = navEvent('https://skulliance.io/staking/dhcfighters.php');
		handlers.fetch(e);
		let threw = false;
		try { await e.responded; } catch (err) { threw = true; }
		ok(threw, 'with no cached fallback the worker swallowed a real network failure instead of surfacing it');
		ok(log.fetches.length === 2, 'expected a retry against the network, saw ' + log.fetches.length + ' fetch(es)');
	}

	console.log('\ninstall precaches the offline page, activate clears old caches');
	{
		const { handlers, log } = makeSW();
		handlers.install({ waitUntil: (p) => p });
		await new Promise(r => setImmediate(r));
		ok(log.cacheAdds.some(u => u.indexOf('offline.html') > -1),
		   'install no longer precaches the offline page, so the first failure has nothing to show');
		let activated;
		handlers.activate({ waitUntil: (p) => { activated = p; } });
		await activated;
		ok(log.deleted.indexOf('old-cache') > -1, 'activate leaves stale caches behind');
		ok(log.deleted.indexOf('skulliance-shell-v1') === -1, 'activate deleted its OWN cache');
	}

	/* The fallback page has to work with nothing else available. */
	console.log('\nthe offline page stands on its own');
	{
		const html = fs.readFileSync(path.join(__dirname, 'offline.html'), 'utf8');
		ok(!/<link[^>]+stylesheet/i.test(html), 'offline.html links a stylesheet it cannot fetch when offline');
		ok(!/<script[^>]+src=/i.test(html),     'offline.html loads an external script it cannot fetch when offline');
		ok(!/<img[^>]+src=/i.test(html),        'offline.html requests an image it cannot fetch when offline');
		ok(/id="retry"/.test(html),             'offline.html has no retry control, which is the only way out of a standalone window');
	}

	/* ---------------------------------------------------------------- *
	 * The loader that covers the screen while a navigation is in flight.
	 *
	 * This is the half of the problem the service worker cannot reach. The
	 * overlay goes up on click and, before this, nothing ever took it down:
	 * its bar animated for twelve seconds and then sat at 90% forever. On a
	 * failing connection that is a full-screen spinner with no cancel, no
	 * retry and no explanation -- and standalone, no address bar behind it
	 * either. Driven in headless Chrome as well; this pins the wiring.
	 * ---------------------------------------------------------------- */
	console.log('\nthe navigation loader gives up out loud');
	{
		const hdr = fs.readFileSync(path.join(__dirname, 'header.php'), 'utf8');
		const strip = (t) => t.replace(/\/\*[\s\S]*?\*\//g, '');
		const h = strip(hdr);
		ok(/var STALL_MS = (\d+);/.test(h), 'the loader has no stall timeout again; it waits forever');
		const ms = parseInt((h.match(/var STALL_MS = (\d+);/) || [])[1], 10);
		ok(ms >= 5000 && ms <= 15000,
		   'STALL_MS is ' + ms + 'ms; under ~5s it fires on merely slow pages, over ~15s people have already force-quit');
		/* INSIDE showNavLoader, not merely somewhere in the file: the retry
		   handler arms the same timer, so a bare indexOf still passed with
		   the arming removed from the place that matters. */
		const snl = h.slice(h.indexOf('function showNavLoader()'));
		const snlBody = snl.slice(0, snl.indexOf('\n    }'));
		ok(snlBody.indexOf('setTimeout(showStall, STALL_MS)') > -1,
		   'showNavLoader no longer arms the stall timer, so the overlay waits forever again');
		ok(h.indexOf('function hideNavLoader()') > -1 && h.indexOf("clearTimeout(stallTimer)") > -1,
		   'nothing clears the stall timer, so it can fire after the loader is gone');
		ok(/id="ns-retry"/.test(h) && /id="ns-cancel"/.test(h),
		   'the stall panel has lost a control; it needs BOTH a retry and a way back to the page underneath');
		ok(h.indexOf("window.addEventListener('offline'") > -1,
		   'losing the connection no longer short-circuits the wait, so a known failure still sits for the full timeout');
		ok(h.indexOf('pendingDest') > -1, 'retry no longer knows where it was going');
		/* bfcache restore must clear it, or coming back lands on a spinner. */
		ok(/e\.persisted[\s\S]{0,200}clearTimeout\(stallTimer\)/.test(h),
		   'a bfcache restore leaves the stall timer running, so the panel can appear over a page that already loaded');
	}

	console.log('\n' + (fail ? fail + ' FAILED\n' : 'all good\n'));
	process.exit(fail ? 1 : 0);
})();
