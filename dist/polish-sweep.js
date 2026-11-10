/*
 * polish-sweep.js — the gradient layer, applied by COLOUR instead of by
 * class name.
 *
 * WHY THIS EXISTS. dist/polish.css is a <link>, so every page's own
 * inline <style> block is later in the document and outranks it. Fifty
 * three pages carry surfaces that way, each with its own class names --
 * .lp-tile, .ac-card, .rl-slot, .st-fig, .riv-wrap and so on -- so
 * naming them in the stylesheet means naming them all, and missing the
 * next one somebody writes.
 *
 * But they are not fifty three palettes. They are one: #0a1929 appears
 * sixty four times across them, and the handful of neighbours below make
 * up nearly all of the rest. So the thing these surfaces have in common
 * is not a selector, it is a COLOUR -- and a colour is something the
 * browser will tell us about any element, which a stylesheet cannot ask.
 *
 * IT CANNOT RECOLOUR ANYTHING. All it adds is a background-IMAGE: the
 * same two fixed-distance layers the stylesheet uses, over whatever fill
 * is already there. An element whose colour it does not recognise is left
 * alone, and an element that already has a background-image -- which is
 * every surface dist/polish.css and the page-level layers already
 * reached -- is skipped, so nothing is lifted twice.
 *
 * TURN IT OFF with ?polish=0, the same flag header.php and the two
 * page-level layers read; the tag is not emitted at all in that case.
 */
(function () {
	'use strict';

	/* SURFACES. The panel colour and its near neighbours, as the browser
	   reports them. */
	var PANEL = {
		'rgb(10, 25, 41)':  1,   /* #0a1929 the panel */
		'rgb(13, 32, 53)':  1,   /* #0d2035 the lighter card */
		'rgb(13, 32, 48)':  1,   /* #0d2030 */
		'rgb(16, 38, 60)':  1,   /* #10263c a hovered tile */
		'rgb(18, 48, 73)':  1    /* #123049 */
	};
	/*
	 * NOT SURFACES, and this list is the whole reason the sweep is safe:
	 *   #07111d is the page itself,
	 *   #080f18 and #0d1e2e are wells and form fields -- holes cut INTO a
	 *     panel, and lighting a recess from above is what makes a
	 *     gradient pass look cheap,
	 *   #040c16 is the nav, which dist/polish.css already does properly.
	 * They are simply absent from PANEL, so nothing has to exclude them.
	 */

	/* A tint means something on this platform -- teal for ready, amber for
	   new -- so it keeps its colour and takes the softer lift, exactly as
	   the stylesheet does. */
	function tintOf(c) {
		var m = /^rgba\((\d+), (\d+), (\d+), ([\d.]+)\)$/.exec(c);
		if (!m) return 0;
		var a = parseFloat(m[4]);
		if (a <= 0.02 || a > 0.22) return 0;
		var r = +m[1], g = +m[2], b = +m[3];
		if (g > 150 && r < 80 && b > 100) return 1;    /* the brand teal */
		if (r > 200 && g > 120 && b < 80) return 1;    /* the amber */
		return 0;
	}

	/* Pills, chips, avatars and badges are not panels. A floor rather than
	   a guess at what each page calls them. */
	function tooSmall(el) {
		var r = el.getBoundingClientRect();
		return r.width < 48 || r.height < 26;
	}

	function mark(root) {
		var els = root.querySelectorAll('*');
		for (var i = 0; i < els.length; i++) {
			var el = els[i];
			if (el.hasAttribute('data-msp')) continue;
			if (el.closest('[data-msp-skip]')) continue;
			var cs = window.getComputedStyle(el);
			/* ALREADY LIFTED. dist/polish.css, missions-polish.php and
			   polish-dhc.php reach plenty of surfaces properly, and a
			   second lift on top of theirs is visibly darker. */
			if (cs.backgroundImage && cs.backgroundImage !== 'none') continue;
			var c = cs.backgroundColor;
			var kind = PANEL[c] ? 'panel' : (tintOf(c) ? 'tint' : '');
			if (!kind) continue;
			if (tooSmall(el)) continue;
			el.setAttribute('data-msp', kind);
			/*
			 * AND SQUARE IT, IF IT IS ROUNDED THE OLD WAY.
			 *
			 * The platform's older pages give panels an 8-10px radius;
			 * everything built since is square or near it. A blanket
			 * border-radius:0 would also flatten the DHC pages' deliberate
			 * 3px, so this is a threshold rather than a rule: six pixels
			 * and over is the old style, under it was chosen. Percentages
			 * are avatars and are never touched.
			 */
			var rad = cs.borderTopLeftRadius || '';
			if (rad.indexOf('%') === -1 && parseFloat(rad) >= 6) {
				el.setAttribute('data-msp-sq', '');
			}
		}
	}

	/*
	 * THE RULES GO IN LAST, at the end of the body, which is the only
	 * place they can outrank a page's own inline stylesheet without
	 * !important. [data-msp] and a single class weigh the same, so this
	 * wins on document order the way every other rule in this layer does.
	 */
	function inject() {
		if (document.getElementById('msp-sweep-css')) return;
		var s = document.createElement('style');
		s.id = 'msp-sweep-css';
		s.textContent =
			'[data-msp="panel"]{background-image:var(--msp-lift)}' +
			'[data-msp="tint"]{background-image:var(--msp-lift-soft)}' +
			'[data-msp-sq]{border-radius:0}';
		document.body.appendChild(s);
	}

	function sweep(root) {
		inject();
		mark(root || document.body);
	}

	function start() {
		sweep(document.body);
		/*
		 * MOST OF THIS PLATFORM ARRIVES AFTER THE FIRST PAINT -- the
		 * missions field, the activity feed, the store grid, every modal.
		 * Marking once would leave all of it flat, and flat beside lifted
		 * is worse than flat everywhere. Debounced, and only over what was
		 * actually added.
		 */
		if (!window.MutationObserver) return;
		var queue = [], timer = 0;
		new MutationObserver(function (muts) {
			for (var i = 0; i < muts.length; i++) {
				var added = muts[i].addedNodes;
				for (var j = 0; j < added.length; j++) {
					if (added[j].nodeType === 1) queue.push(added[j]);
				}
			}
			if (!queue.length || timer) return;
			timer = setTimeout(function () {
				timer = 0;
				var q = queue; queue = [];
				for (var k = 0; k < q.length; k++) {
					if (!q[k].isConnected) continue;
					if (q[k].matches && q[k].matches('*')) mark(q[k].parentNode || document.body);
				}
			}, 250);
		}).observe(document.body, { childList: true, subtree: true });
	}

	/* After the first paint, never before it: this reads layout for every
	   element, and doing that during load is the one way a cosmetic pass
	   can cost something real. */
	function boot() {
		if (window.requestIdleCallback) requestIdleCallback(start, { timeout: 1200 });
		else setTimeout(start, 200);
	}
	if (document.readyState === 'complete') boot();
	else window.addEventListener('load', boot);
})();
