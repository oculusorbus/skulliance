/**
 * swipe-nav.js -- swipe left and right to move between the sections of a page.
 *
 * Missions and realms both grew the same control this year: a sticky strip of
 * section links at the top of the page. On a phone that strip is a horizontal
 * scroller five items wide, so getting to the far end is reach, scroll, aim,
 * tap. A swipe across the page body is the gesture people already try there,
 * and it costs them nothing to discover -- nothing about the page changes if
 * they never do.
 *
 * IT DRIVES THE NAV, IT DOES NOT REIMPLEMENT IT. A swipe finds the next
 * visible link and CLICKS it, so whatever that link already did keeps
 * happening and keeps happening in one place: on realms it is
 * onclick="return rlNav(...)", which swaps panels and returns false; on
 * missions it is a real #hash jump plus the scroll-spy's pin. Neither page
 * needed a line of new navigation logic, and neither can drift from what its
 * own links do.
 *
 * MOBILE ONLY, and by touch rather than by breakpoint alone -- these are
 * touchstart/touchend listeners, so a mouse never triggers them, and the
 * width check keeps a tablet in landscape out of it as well.
 *
 * WHAT IT REFUSES TO ACT ON matters more than what it acts on. A horizontal
 * drag that starts on something which itself scrolls sideways belongs to that
 * thing -- the nav strip, a wide table, the map's pan surface -- and stealing
 * it would break the page in exactly the places a phone user is already
 * fighting. So the gesture walks up from its own target first and gives way
 * to any sideways scroller, any field, and anything the page marks
 * data-no-swipe.
 */
(function (w, d) {
	'use strict';

	var MAX_WIDTH = 700;   /* the breakpoint where the navbar becomes a burger */
	var MIN_DIST  = 55;    /* px of horizontal travel before it counts */
	var MAX_DRIFT = 0.7;   /* vertical drift allowed, as a fraction of the dx */
	var MAX_TIME  = 800;   /* ms; a slow drag is a scroll that wandered */

	function ownsTheGesture(el, root) {
		for (var n = el; n && n !== root && n.nodeType === 1; n = n.parentNode) {
			if (n.hasAttribute && n.hasAttribute('data-no-swipe')) return true;
			var t = n.tagName;
			if (t === 'INPUT' || t === 'TEXTAREA' || t === 'SELECT' || t === 'BUTTON') return true;
			if (n.isContentEditable) return true;
			/* Sideways scrollers. The 8px slack is for the sub-pixel
			   difference a border or a zoomed viewport leaves behind on
			   elements that do not actually scroll. */
			if (n.scrollWidth - n.clientWidth > 8) {
				var ox = w.getComputedStyle(n).overflowX;
				if (ox === 'auto' || ox === 'scroll') return true;
			}
		}
		return false;
	}

	function visibleLinks(nav) {
		var out = [];
		[].forEach.call(nav.querySelectorAll('a[data-sec]'), function (a) {
			/* offsetParent is null for display:none, which is how realms
			   drops the one section that is not a destination of its own. */
			if (a.offsetParent !== null) out.push(a);
		});
		return out;
	}

	/**
	 * init(navId, opts)
	 *   opts.blocked  optional () => bool, true while something modal is up.
	 */
	function init(navId, opts) {
		var nav = d.getElementById(navId);
		if (!nav) return;
		opts = opts || {};

		var x0 = 0, y0 = 0, t0 = 0, live = false;

		d.addEventListener('touchstart', function (e) {
			live = false;
			if (w.innerWidth > MAX_WIDTH) return;
			if (e.touches.length !== 1) return;          /* a pinch is not a swipe */
			/* The burger is a full-screen affordance on every page here, so
			   it is guarded in the shared code rather than by each caller. */
			var nb = d.getElementById('navbar');
			if (nb && nb.classList.contains('show-menu')) return;
			if (opts.blocked && opts.blocked()) return;
			if (ownsTheGesture(e.target, d.body)) return;
			x0 = e.touches[0].clientX;
			y0 = e.touches[0].clientY;
			t0 = Date.now();
			live = true;
		}, {passive: true});

		d.addEventListener('touchend', function (e) {
			if (!live) return;
			live = false;
			if (!e.changedTouches || !e.changedTouches.length) return;
			if (Date.now() - t0 > MAX_TIME) return;

			var dx = e.changedTouches[0].clientX - x0;
			var dy = e.changedTouches[0].clientY - y0;
			if (Math.abs(dx) < MIN_DIST) return;
			if (Math.abs(dy) > Math.abs(dx) * MAX_DRIFT) return;

			var links = visibleLinks(nav);
			if (links.length < 2) return;

			var at = 0;
			for (var i = 0; i < links.length; i++) {
				if (links[i].classList.contains('on')) { at = i; break; }
			}
			/* Swipe left, the next section comes in from the right. Ends are
			   walls rather than a wrap: on a list this short, wrapping reads
			   as the page having jumped rather than moved. */
			var to = at + (dx < 0 ? 1 : -1);
			if (to < 0 || to >= links.length) return;
			links[to].click();
		}, {passive: true});
	}

	w.SkullSwipe = {init: init};
}(window, document));
