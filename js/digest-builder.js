/**
 * digest-builder.js — one day of Skulliance drawn as a poster.
 *
 * Used by admin-digest.php. Reads window.DIGEST (see activity_digest()) and
 * draws the art that appeared that day, the players who turned up, and a
 * tally per feature, onto one canvas the admin downloads.
 *
 * TAINTING IS THE WHOLE RISK, and it fails late and total: a canvas that has
 * drawn a cross-origin image without CORS throws on toBlob(), so the symptom
 * is not a missing tile, it is no download at all after everything looked
 * fine. Two defences, and both are needed:
 *   - every src was normalised server-side (activity_digest_src) to a
 *     relative path or to Discord's CDN, which answers with
 *     access-control-allow-origin: *;
 *   - every Image gets crossOrigin='anonymous' BEFORE .src is set, which is
 *     the only order that works, and anything that fails to load is simply
 *     dropped rather than drawn.
 * js/flyer-builder.js carries the same warning for the same reason.
 *
 * SEEDED BY THE DATE. Same day, same poster; different day, different one.
 * That is the point of the thing -- a layout dictated by what happened --
 * and it also means the preview does not reshuffle every time a control
 * moves. Re-roll advances the seed by hand.
 */
(function () {
	'use strict';

	var D = window.DIGEST || { total: 0, art: [], players: [], channels: [] };
	var LOGO = null;   /* the homepage wordmark, loaded below */

	/* Skulliance's own ground and accent, so the poster reads as the site. */
	var INK = '#06101a', PANEL = '#0d1e2e', ACCENT = '#00c8a0', TEXT = '#e6f1f7', MUTE = '#8fa6b8';

	var FORMATS = {
		wide:   { w: 1600, h: 900  },
		tall:   { w: 1200, h: 1500 },
		square: { w: 1200, h: 1200 }
	};

	/* mulberry32, same generator the flyer builder uses: a few lines, good
	   enough for layout jitter, and seedable. */
	function rng(seed) {
		var a = seed >>> 0;
		return function () {
			a |= 0; a = a + 0x6D2B79F5 | 0;
			var t = Math.imul(a ^ a >>> 15, 1 | a);
			t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
			return ((t ^ t >>> 14) >>> 0) / 4294967296;
		};
	}
	function seedOf(str) {
		var h = 2166136261;
		for (var i = 0; i < str.length; i++) { h ^= str.charCodeAt(i); h = Math.imul(h, 16777619); }
		return h >>> 0;
	}

	/* ---------- loading ------------------------------------------------ */

	/* Resolves to the image or to null. NEVER rejects: one dead URL out of
	   forty must cost that tile and nothing else. */
	function load(src) {
		return new Promise(function (res) {
			if (!src) return res(null);
			var im = new Image();
			/* Before .src, always. Setting it after is ignored and the canvas
			   is tainted with no warning. */
			im.crossOrigin = 'anonymous';
			im.onload  = function () { res(im.naturalWidth ? im : null); };
			im.onerror = function () { res(null); };
			im.src = src;
		});
	}

	/* ---------- drawing helpers ---------------------------------------- */

	function roundRect(c, x, y, w, h, r) {
		c.beginPath();
		c.moveTo(x + r, y);
		c.arcTo(x + w, y,     x + w, y + h, r);
		c.arcTo(x + w, y + h, x,     y + h, r);
		c.arcTo(x,     y + h, x,     y,     r);
		c.arcTo(x,     y,     x + w, y,     r);
		c.closePath();
	}

	/* cover-crop: fill the box, centre what does not fit. The art is mostly
	   square and the boxes are not, so letterboxing every tile would leave
	   the poster full of holes. */
	function drawCover(c, im, x, y, w, h) {
		var s = Math.max(w / im.naturalWidth, h / im.naturalHeight);
		var dw = im.naturalWidth * s, dh = im.naturalHeight * s;
		c.drawImage(im, x + (w - dw) / 2, y + (h - dh) / 2, dw, dh);
	}

	function circle(c, im, cx, cy, r) {
		c.save();
		c.beginPath(); c.arc(cx, cy, r, 0, Math.PI * 2); c.closePath(); c.clip();
		drawCover(c, im, cx - r, cy - r, r * 2, r * 2);
		c.restore();
	}

	/* Trim to fit a width, with a real ellipsis rather than a hard cut. */
	function fit(c, text, max) {
		text = String(text == null ? '' : text);
		if (c.measureText(text).width <= max) return text;
		while (text.length > 1 && c.measureText(text + '…').width > max) text = text.slice(0, -1);
		return text + '…';
	}

	function prettyDay(iso) {
		var p = String(iso).split('-');
		if (p.length !== 3) return iso;
		var M = ['January','February','March','April','May','June','July',
		         'August','September','October','November','December'];
		return parseInt(p[2], 10) + ' ' + M[parseInt(p[1], 10) - 1] + ' ' + p[0];
	}

	/* ---------- the clusters ------------------------------------------ */

	/*
	 * ONE LABEL PER CATEGORY, NOT ONE PER TILE.
	 *
	 * The first version captioned every tile, and on a real day that read
	 * DHC FIGHTERS five times, BOSS BATTLES three times and MISSIONS twice
	 * down one poster -- the repetition was the loudest thing on it, and it
	 * told you nothing the first instance had not. Art is grouped by channel
	 * now, each group gets a contiguous block, and the name is said once
	 * underneath it.
	 *
	 * The variety that the per-tile mosaic was there for has to come from
	 * somewhere else, so it comes from the data: a block's WIDTH is its
	 * share of the day and its internal grid follows from that, which means
	 * a day of mostly Monstrocity looks nothing like a day of mostly raids.
	 */
	function group(art) {
		var by = {}, order = [];
		for (var i = 0; i < art.length; i++) {
			var k = art[i].channel || '_';
			if (!by[k]) { by[k] = { label: art[i].label || '', items: [] }; order.push(k); }
			by[k].items.push(art[i]);
		}
		return order.map(function (k) { return by[k]; })
		            .sort(function (a, b) { return b.items.length - a.items.length; });
	}

	/* How many tiles each category is allowed, proportional to its share of
	   the day, with everyone who showed up at all getting at least one. */
	function budget(groups, cap) {
		var total = 0, i;
		for (i = 0; i < groups.length; i++) total += groups[i].items.length;
		if (!total) return [];

		var out = [], left = Math.min(cap, total);
		for (i = 0; i < groups.length && left > 0; i++) {
			var want = Math.max(1, Math.round(cap * groups[i].items.length / total));
			want = Math.min(want, groups[i].items.length, left);
			out.push({ label: groups[i].label, items: groups[i].items.slice(0, want), src: groups[i] });
			left -= want;
		}
		/* Spend whatever rounding left over on the categories that still
		   have art to give, biggest first. */
		var guard = 0;
		while (left > 0 && guard++ < cap * 4) {
			var moved = false;
			for (i = 0; i < out.length && left > 0; i++) {
				if (out[i].items.length < out[i].src.items.length) {
					out[i].items.push(out[i].src.items[out[i].items.length]);
					left--; moved = true;
				}
			}
			if (!moved) break;
		}
		return out;
	}

	/* Lay the blocks across the band. Each is a column group of its own
	   width with its own internal grid, and a strip underneath for the one
	   label. */
	function clusters(r, x, y, w, h, plan) {
		/* TWO GAPS, AND THE DIFFERENCE IS THE GROUPING. With one gap
		   everywhere the blocks were only distinguishable by reading the
		   labels -- the tiles sat in one even field and the clustering, the
		   whole point of the change, was invisible. A wider gutter between
		   categories than between their own tiles is what makes a block
		   read as a block. */
		var GAP = 7, BGAP = 22, out = [], i, k;
		var LBL = Math.max(20, Math.round(h * 0.085));
		if (!plan.length) return out;

		/*
		 * A BLOCK TOO NARROW FOR ITS OWN NAME IS WORSE THAN NO BLOCK. The
		 * first version handed every category a share of the width and the
		 * smallest ones came out at 90-odd pixels, which fit two tiles and
		 * an ellipsised label -- a real poster ended on "REAL…". The tally
		 * line already names every category and counts it, so dropping the
		 * tail from the COLLAGE loses nothing: it is the pictures that have
		 * to earn their room.
		 */
		var MINW = 150;
		var maxBlocks = Math.max(1, Math.floor((w + BGAP) / (MINW + BGAP)));
		if (plan.length > maxBlocks) plan = plan.slice(0, maxBlocks);

		var tiles = 0;
		for (i = 0; i < plan.length; i++) tiles += plan[i].items.length;
		if (!tiles) return out;

		/* Widths proportional to share, with the rounding error given to the
		   BIGGEST block rather than to the last one -- the last is usually
		   the smallest, and handing it the remainder is how it ended up
		   either squeezed or stretched. */
		var innerW = w - BGAP * (plan.length - 1), widths = [], sum = 0;
		for (i = 0; i < plan.length; i++) {
			widths[i] = Math.max(MINW, Math.floor(innerW * plan[i].items.length / tiles));
			sum += widths[i];
		}
		widths[0] += innerW - sum;
		if (widths[0] < MINW) widths[0] = MINW;

		var cx = x, bodyH = h - LBL;
		for (i = 0; i < plan.length; i++) {
			var g = plan[i], gw = widths[i], n = g.items.length;

			/* Columns from the space available, not from the count: a wide
			   block of three wants one row of three, a narrow block of three
			   wants a stack. ~165px is where a tile stops reading. */
			var cols = Math.max(1, Math.min(n, Math.round(gw / 165) || 1));
			var rows = Math.max(1, Math.ceil(n / cols));

			/*
			 * EVERY ROW IS FULL. Laying n items into a fixed cols x rows
			 * grid leaves the last row short -- 5 tiles in 3 columns is a
			 * row of 3 and a row of 2 with a hole beside it, and a hole in a
			 * collage reads as a picture that failed to load. The items are
			 * spread across the rows instead and each row's tiles are sized
			 * to fill the block, so a short row is simply a row of wider
			 * pictures.
			 */
			var base = Math.floor(n / rows), extra = n % rows;
			var cells = [], idx = 0, cy = y;
			var th = (bodyH - GAP * (rows - 1)) / rows;
			for (var rr = 0; rr < rows; rr++) {
				var cnt = base + (rr < extra ? 1 : 0);
				if (cnt < 1) continue;
				var tw = (gw - GAP * (cnt - 1)) / cnt, tx = cx;
				for (k = 0; k < cnt; k++) {
					cells.push({ x: tx, y: cy, w: tw, h: th, item: g.items[idx++] });
					tx += tw + GAP;
				}
				cy += th + GAP;
			}
			out.push({ label: g.label, x: cx, w: gw, labelY: y + h - Math.round(LBL * 0.22), cells: cells });
			cx += gw + BGAP;
		}
		return out;
	}

	/* ---------- the poster --------------------------------------------- */

	function draw(canvas, opts) {
		var fmt = FORMATS[opts.format] || FORMATS.wide;
		var W = fmt.w, H = fmt.h;
		canvas.width = W; canvas.height = H;
		var c = canvas.getContext('2d');
		var r = rng(seedOf(D.day + '|' + opts.format + '|' + opts.nudge));

		c.fillStyle = INK; c.fillRect(0, 0, W, H);

		/*
		 * THE ART GETS THE PAGE. Measured off the first render rather than
		 * guessed: at the proportions this started with, the header and the
		 * shout-out block between them took 44% of a wide poster and the
		 * collage was a thin strip across the middle. The chrome is there to
		 * frame the pictures, so it is held to roughly a third and the
		 * mosaic takes whatever is left.
		 */
		var PAD    = Math.round(W * 0.028);
		var titleS = Math.round(W * (opts.format === 'wide' ? 0.042 : 0.052));
		var headH  = Math.round(titleS * 1.9);

		/* ---- header ----
		 * THE REAL WORDMARK, not the word set in a Google font. The homepage
		 * logo is the platform's own lettering and a poster that goes out as
		 * marketing should wear it rather than an approximation. It is
		 * 700x300, drawn to a height and left to find its own width, so the
		 * proportions are the designed ones. Falls back to type if the file
		 * does not load -- a poster with no masthead is worse than one with
		 * a typeset masthead. */
		c.textBaseline = 'alphabetic';
		var markH = Math.round(titleS * 1.15), baseline = PAD + titleS;

		if (!opts.headline && LOGO) {
			var markW = LOGO.naturalWidth * (markH / LOGO.naturalHeight);
			c.drawImage(LOGO, PAD, PAD, markW, markH);
		} else {
			c.fillStyle = TEXT;
			c.font = '800 ' + titleS + 'px "Saira Semi Condensed", Impact, sans-serif';
			c.fillText(fit(c, (opts.headline || 'SKULLIANCE').toUpperCase(), W - PAD * 2), PAD, baseline);
		}

		c.fillStyle = ACCENT;
		c.font = '700 ' + Math.round(titleS * 0.42) + 'px Rajdhani, sans-serif';
		var sub = opts.headline ? prettyDay(D.day).toUpperCase()
		                        : prettyDay(D.day).toUpperCase() + '  ·  ' + D.total +
		                          ' EVENT' + (D.total === 1 ? '' : 'S') + ' ACROSS THE PLATFORM';
		c.fillText(fit(c, sub, W - PAD * 2), PAD, PAD + markH + Math.round(titleS * 0.5));

		/* ---- footer block: the tally, then the people ---- */
		var avR      = Math.round(W * (opts.format === 'wide' ? 0.027 : 0.034));
		var nameS    = Math.round(avR * 0.52);
		/* Must match the py arithmetic below, or the mosaic and the shout-out
		   row disagree about where the floor is. */
		var peopleH  = D.players.length ? avR * 2 + nameS + 14 : 0;
		var chipS    = Math.round(W * 0.016);
		var tallyH   = D.channels.length ? Math.round(chipS * 2.2) : 0;
		var footH    = peopleH + tallyH + Math.round(PAD * 0.3);

		/* ---- the mosaic fills whatever is between them ---- */
		var mx = PAD, my = PAD + headH, mw = W - PAD * 2;
		var mh = H - my - footH - PAD;

		var art = D.art.filter(function (a) { return a.img; });
		if (art.length) {
			/* More art than will read at this size is worse than less of it:
			   past about 14 tiles on a wide poster each one is a thumbnail
			   and the feast becomes a mosaic of mush. A tall poster has the
			   room for a few more. */
			var cap  = opts.format === 'wide' ? 14 : 16;
			var blocks = clusters(r, mx, my, mw, mh, budget(group(art), cap));

			for (var bi = 0; bi < blocks.length; bi++) {
				var blk = blocks[bi];
				for (var i = 0; i < blk.cells.length; i++) {
					var cell = blk.cells[i], a = cell.item;
					c.save();
					roundRect(c, cell.x, cell.y, cell.w, cell.h, 8);
					c.clip();
					c.fillStyle = PANEL; c.fillRect(cell.x, cell.y, cell.w, cell.h);
					drawCover(c, a.img, cell.x, cell.y, cell.w, cell.h);
					c.restore();
					c.strokeStyle = 'rgba(0,200,160,0.16)'; c.lineWidth = 1;
					roundRect(c, cell.x + 0.5, cell.y + 0.5, cell.w - 1, cell.h - 1, 8);
					c.stroke();
				}
				/* The one label, under the block and on the poster's own
				   ground rather than over the art -- so it is legible
				   whatever the pictures happen to be, which a gradient over
				   pale art never quite is. */
				if (blk.label) {
					c.fillStyle = ACCENT;
					c.font = '700 ' + Math.round(chipS * 0.95) + 'px Rajdhani, sans-serif';
					c.fillText(fit(c, blk.label.toUpperCase(), blk.w), blk.x, blk.labelY);
				}
			}
		} else {
			c.fillStyle = PANEL; roundRect(c, mx, my, mw, mh, 10); c.fill();
			c.fillStyle = MUTE;
			c.font = '600 ' + Math.round(W * 0.018) + 'px Rajdhani, sans-serif';
			c.fillText('No pictures were announced on this day.', mx + 24, my + 44);
		}

		/* ---- the tally ---- */
		var ty = H - PAD - peopleH - Math.round(tallyH * 0.35);
		if (D.channels.length) {
			c.font = '700 ' + chipS + 'px Rajdhani, sans-serif';
			/* DROP WHOLE ENTRIES, never ellipsise. A fixed slice(0,9) ran
			   past the edge on a busy day and fit() cut it mid-word -- the
			   poster said "19 Daily Rewar…", which reads as broken rather
			   than as abbreviated. Fewer categories, each one whole. */
			var parts = [], room = W - PAD * 2, sep = '   ·   ';
			for (var ci = 0; ci < D.channels.length; ci++) {
				var bit = D.channels[ci].n + ' ' + D.channels[ci].label;
				var test = parts.concat([bit]).join(sep);
				if (c.measureText(test).width > room) break;
				parts.push(bit);
			}
			c.fillStyle = MUTE;
			c.fillText(parts.join(sep), PAD, ty);
		}

		/* ---- the shout-outs ---- */
		if (D.players.length) {
			/* The block is avatar (2*avR) THEN the name under it, so the top
			   of it has to leave room for both. This was short by one radius,
			   which the wide poster had the slack to absorb and the tall one
			   did not -- the names were clipped off the bottom edge. */
			var py = H - PAD - avR * 2 - nameS - 2;
			var step = avR * 2 + Math.round(avR * 0.72);
			var room = Math.floor((W - PAD * 2 + Math.round(avR * 0.72)) / step);
			var show = D.players.slice(0, Math.max(1, Math.min(room, 12)));
			var px = PAD;

			for (var k = 0; k < show.length; k++) {
				var p = show[k], ccx = px + avR, ccy = py + avR;
				if (p.img) circle(c, p.img, ccx, ccy, avR);
				else {
					/* No avatar is normal -- plenty of accounts never set one.
					   An initial is better than a hole and better than a
					   generic mark repeated down the row. */
					c.fillStyle = PANEL;
					c.beginPath(); c.arc(ccx, ccy, avR, 0, Math.PI * 2); c.fill();
					c.fillStyle = ACCENT;
					c.font = '800 ' + Math.round(avR) + 'px "Saira Semi Condensed", sans-serif';
					c.textAlign = 'center';
					c.fillText((p.name || '?').charAt(0).toUpperCase(), ccx, ccy + avR * 0.36);
					c.textAlign = 'left';
				}
				c.strokeStyle = ACCENT; c.lineWidth = 2;
				c.beginPath(); c.arc(ccx, ccy, avR, 0, Math.PI * 2); c.stroke();

				c.fillStyle = TEXT;
				c.font = '700 ' + nameS + 'px Rajdhani, sans-serif';
				c.textAlign = 'center';
				c.fillText(fit(c, p.name, step - 6), ccx, py + avR * 2 + nameS + 2);
				c.textAlign = 'left';
				px += step;
			}
		}

		/* ---- the mark ---- */
		c.fillStyle = 'rgba(143,166,184,0.55)';
		c.font = '600 ' + Math.round(W * 0.013) + 'px Rajdhani, sans-serif';
		c.textAlign = 'right';
		c.fillText('skulliance.io', W - PAD, PAD + Math.round(W * 0.013));
		c.textAlign = 'left';
	}

	/* ---------- wiring -------------------------------------------------- */

	var canvas = document.getElementById('dg-canvas');
	if (!canvas || !D.total) return;

	var nudge = 0;
	var report = document.getElementById('dg-report');
	var miss   = document.getElementById('dg-miss');

	function render() {
		draw(canvas, {
			format:   (document.getElementById('dg-format') || {}).value || 'wide',
			headline: ((document.getElementById('dg-headline') || {}).value || '').trim(),
			nudge:    nudge
		});
	}

	/* Load everything first, then draw once. Drawing per image would show the
	   poster assembling itself, and would also redraw the mosaic on every
	   arrival -- the layout depends on how many pieces loaded. */
	Promise.all(
		D.art.map(function (a) { return load(a.src).then(function (im) { a.img = im; }); })
		.concat(D.players.map(function (p) { return load(p.avatar).then(function (im) { p.img = im; }); }))
		/* Same-origin and relative, so it is safe for the canvas by the same
		   rule everything else here follows. */
		.concat([load('images/skulliancelogo.png').then(function (im) { LOGO = im; })])
	).then(function () {
		var gotArt = D.art.filter(function (a) { return a.img; }).length;
		var gotAv  = D.players.filter(function (p) { return p.img; }).length;
		if (report) {
			report.innerHTML = '<b>' + D.total + '</b> events · <b>' + gotArt + '</b> of ' +
				D.art.length + ' pictures · <b>' + D.players.length + '</b> player' +
				(D.players.length === 1 ? '' : 's') + ' (' + gotAv + ' with an avatar)';
		}
		/* Say what did not arrive. A tile missing from a collage is invisible
		   -- you cannot tell a quiet day from a broken URL by looking. */
		var lost = D.art.length - gotArt;
		if (lost > 0 && miss) {
			miss.hidden = false;
			miss.textContent = lost + ' picture' + (lost === 1 ? '' : 's') +
				' could not be loaded and were left out (deleted art, or hosted somewhere ' +
				'the canvas cannot read).';
		}
		render();
	});

	var fm = document.getElementById('dg-format');
	var hl = document.getElementById('dg-headline');
	if (fm) fm.addEventListener('change', render);
	if (hl) hl.addEventListener('input', render);

	var rr = document.getElementById('dg-reroll');
	if (rr) rr.addEventListener('click', function () { nudge++; render(); });

	var save = document.getElementById('dg-save');
	if (save) save.addEventListener('click', function () {
		/* toBlob throws on a tainted canvas. Everything above is built to
		   stop that happening, but if it ever does the admin needs to know
		   WHY rather than watch a button do nothing. */
		try {
			canvas.toBlob(function (b) {
				if (!b) return;
				var a = document.createElement('a');
				a.href = URL.createObjectURL(b);
				a.download = 'skulliance-' + D.day + '.png';
				document.body.appendChild(a); a.click(); a.remove();
				setTimeout(function () { URL.revokeObjectURL(a.href); }, 4000);
			}, 'image/png');
		} catch (e) {
			alert('The poster could not be exported: one of the pictures came from a host that ' +
			      'does not allow it to be read back. Nothing is wrong with the day — tell the ' +
			      'developer which day this was.');
		}
	});

	window.DigestBuilder = { draw: draw, group: group, budget: budget, clusters: clusters,
	                         fit: fit, rng: rng, seedOf: seedOf };
})();
