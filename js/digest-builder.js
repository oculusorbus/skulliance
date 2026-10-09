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

	/* ---------- the mosaic --------------------------------------------- */

	/*
	 * A COLLAGE, NOT A CONTACT SHEET. A plain grid of equal tiles says
	 * "report"; the brief was a visual feast whose shape is dictated by the
	 * day. So the band is packed in columns of varying width, each column
	 * split into one, two or three tiles -- which is what gives a big
	 * Monstrocity boss next to a stack of three small Fighters, and makes a
	 * ten-event day look different from a sixty-event one without anything
	 * saying so.
	 *
	 * Packed left to right so there is never a hole: the last column is
	 * whatever width is left rather than whatever the random pick wanted.
	 */
	function mosaic(r, x, y, w, h, n) {
		var cells = [], cols = [], left = w, GAP = 6;
		if (n < 1) return cells;

		/* Column widths, biased so one or two are noticeably wider. */
		var minW = Math.max(110, w / Math.min(n, 9));
		while (left > minW * 1.2 && cols.length < n) {
			var cw = minW * (0.85 + r() * 1.5);
			if (cw > left - minW) break;
			cols.push(cw); left -= cw + GAP;
		}
		cols.push(left);

		var cx = x;
		for (var i = 0; i < cols.length; i++) {
			var cw2 = cols[i];
			/* A wide column gets fewer, taller tiles; a narrow one stacks. */
			var want = cw2 > w / 4 ? 1 + Math.floor(r() * 2) : 1 + Math.floor(r() * 3);
			var rows = Math.max(1, Math.min(want, n - cells.length));
			if (i === cols.length - 1) rows = Math.max(1, Math.min(n - cells.length, 3));
			var ch = (h - GAP * (rows - 1)) / rows, cy = y;
			for (var j = 0; j < rows && cells.length < n; j++) {
				cells.push({ x: cx, y: cy, w: cw2, h: ch });
				cy += ch + GAP;
			}
			cx += cw2 + GAP;
			if (cells.length >= n) break;
		}
		return cells;
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

		/* ---- header ---- */
		c.textBaseline = 'alphabetic';
		c.fillStyle = TEXT;
		c.font = '800 ' + titleS + 'px "Saira Semi Condensed", Impact, sans-serif';
		var title = (opts.headline || 'SKULLIANCE').toUpperCase();
		c.fillText(fit(c, title, W - PAD * 2), PAD, PAD + titleS);

		c.fillStyle = ACCENT;
		c.font = '700 ' + Math.round(titleS * 0.42) + 'px Rajdhani, sans-serif';
		var sub = opts.headline ? prettyDay(D.day).toUpperCase()
		                        : prettyDay(D.day).toUpperCase() + '  ·  ' + D.total +
		                          ' EVENT' + (D.total === 1 ? '' : 'S') + ' ACROSS THE PLATFORM';
		c.fillText(fit(c, sub, W - PAD * 2), PAD, PAD + titleS + Math.round(titleS * 0.55));

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
			var take = Math.min(art.length, cap);
			var cells = mosaic(r, mx, my, mw, mh, take);

			/* art[i], never art[i % len]. take is capped at art.length, so a
			   modulo could only ever mean repeating a tile -- and the same
			   picture twice in a collage reads as a bug, not as emphasis. */
			for (var i = 0; i < cells.length; i++) {
				var cell = cells[i], a = art[i];
				c.save();
				roundRect(c, cell.x, cell.y, cell.w, cell.h, 8);
				c.clip();
				c.fillStyle = PANEL; c.fillRect(cell.x, cell.y, cell.w, cell.h);
				drawCover(c, a.img, cell.x, cell.y, cell.w, cell.h);

				/* A label only where there is room for one to be read. The
				   gradient is what keeps it legible over art that might be
				   pale at the bottom. */
				if (cell.w > 150 && cell.h > 90 && a.label) {
					var g = c.createLinearGradient(0, cell.y + cell.h - 46, 0, cell.y + cell.h);
					g.addColorStop(0, 'rgba(4,10,18,0)'); g.addColorStop(1, 'rgba(4,10,18,0.88)');
					c.fillStyle = g; c.fillRect(cell.x, cell.y + cell.h - 46, cell.w, 46);
					c.fillStyle = ACCENT;
					c.font = '700 ' + Math.round(chipS * 0.82) + 'px Rajdhani, sans-serif';
					c.fillText(fit(c, a.label.toUpperCase(), cell.w - 20), cell.x + 10, cell.y + cell.h - 14);
				}
				c.restore();

				c.strokeStyle = 'rgba(0,200,160,0.16)'; c.lineWidth = 1;
				roundRect(c, cell.x + 0.5, cell.y + 0.5, cell.w - 1, cell.h - 1, 8);
				c.stroke();
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
			var parts = D.channels.slice(0, 9).map(function (ch) { return ch.n + ' ' + ch.label; });
			c.fillStyle = MUTE;
			c.fillText(fit(c, parts.join('   ·   '), W - PAD * 2), PAD, ty);
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

	window.DigestBuilder = { draw: draw, mosaic: mosaic, fit: fit, rng: rng, seedOf: seedOf };
})();
