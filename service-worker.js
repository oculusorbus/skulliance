/*
 * service-worker.js — a navigation safety net, and nothing else.
 *
 * WHAT THIS DELIBERATELY DOES NOT DO: cache the site. Every HTML response
 * here is per-player and live -- balances, mission timers, raid state, what
 * is left to claim today -- and serving a stale one would be worse than
 * serving none. Static assets are not cached either: flexbox.css and the
 * game JS are unversioned URLs, so a cached copy would survive a deploy and
 * the update banner (version.php, polled from header.php) would be telling
 * people to refresh into the old stylesheet. If asset caching is ever added,
 * it has to be invalidated by that same VERSION signal, not by a cache name
 * someone remembers to bump.
 *
 * WHAT IT DOES: catch a page navigation that fails or hangs, and put
 * something on screen that explains it and offers a way out.
 *
 * The app is display:standalone. In a browser tab a dead navigation still
 * leaves an address bar, a back button and a reload button. Installed, it
 * leaves a blank frame and no controls at all, which is why the only
 * recovery was force-quitting the app and reopening it. That is the bug.
 *
 * SCOPE OF THE INTERCEPT, kept as narrow as it can usefully be:
 *   - GET only. A navigation can be a form POST, and replaying or faking a
 *     response to one of those could pay a reward twice.
 *   - mode 'navigate' only. Images, CSS, JS, fetch() and the AJAX endpoints
 *     are untouched and go straight to the network as before.
 *   - same origin only.
 * Anything else falls through with no respondWith at all, which is exactly
 * the old no-op behaviour.
 *
 * THE TIMEOUT IS A FLOOR, NOT A DEADLINE. 20s is long on purpose: cutting
 * off a slow-but-progressing page and replacing it with an error would make
 * a bad connection worse, and the reported complaint is stalls that never
 * resolve, not pages that take eight seconds. The fallback offers Retry
 * rather than claiming the request failed.
 */

var CACHE = 'skulliance-shell-v1';
var OFFLINE = '/staking/offline.html';
var NAV_TIMEOUT_MS = 20000;

self.addEventListener('install', function (event) {
	event.waitUntil(
		caches.open(CACHE).then(function (c) {
			/* reload, so installing a new worker cannot pick the old copy of
			   this file out of the HTTP cache. */
			return c.add(new Request(OFFLINE, { cache: 'reload' }));
		}).catch(function () {
			/* A failed precache must not block activation -- it would leave
			   the old worker in place and nothing improved. The fetch handler
			   copes with a missing fallback below. */
		})
	);
	self.skipWaiting();
});

self.addEventListener('activate', function (event) {
	event.waitUntil(
		caches.keys().then(function (keys) {
			return Promise.all(keys.map(function (k) {
				return k === CACHE ? null : caches.delete(k);
			}));
		}).then(function () {
			return self.clients.claim();
		})
	);
});

function navigateWithFallback(request) {
	var timer;
	var timeout = new Promise(function (resolve) {
		timer = setTimeout(function () { resolve('timeout'); }, NAV_TIMEOUT_MS);
	});

	return Promise.race([fetch(request).catch(function () { return 'failed'; }), timeout])
		.then(function (res) {
			clearTimeout(timer);
			if (res && res !== 'timeout' && res !== 'failed') return res;
			return caches.match(OFFLINE, { ignoreSearch: true }).then(function (page) {
				if (page) return page;
				/* No cached fallback (first run, or the precache failed). Let
				   the real failure surface rather than inventing a response --
				   the browser's own error page is worse than ours but better
				   than a blank frame with no explanation. */
				return fetch(request);
			});
		});
}

self.addEventListener('fetch', function (event) {
	var req = event.request;
	if (req.method !== 'GET') return;
	if (req.mode !== 'navigate') return;
	try {
		if (new URL(req.url).origin !== self.location.origin) return;
	} catch (e) {
		return;
	}
	event.respondWith(navigateWithFallback(req));
});
