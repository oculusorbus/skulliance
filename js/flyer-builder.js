/**
 * flyer-builder.js — draws the "is now live on Skulliance" partner flyer.
 *
 * Rebuilt from three finished flyers (Muses of the Multiverse, Goat Tribe,
 * DRKL) after the designer's PSD went missing. Everything is drawn on one
 * 1500px-wide canvas so the download is the same file a designer would
 * have exported -- no screenshot, no html2canvas.
 *
 * THE WINDOW is the slanted band the project art shows through. Its two
 * long edges are dry-brushed: streaks laid ALONG the edge, some cutting
 * into the art and some spilling out past it, plus splatter. The grit is
 * seeded, so the preview does not crawl every time a slider moves, and
 * "Re-roll" gives a new brush pass.
 *
 * TAINTING. toBlob() throws on a canvas that has drawn a cross-origin
 * image. Uploads are read as data URLs and the defaults (the Skulliance
 * logo, icons/{currency}.png) are same-origin, so the canvas stays clean.
 * Do not point a default at the CDN host.
 *
 * Used by admin-flyers.php. Exposes window.FlyerBuilder.
 */
(function () {
	'use strict';

	var W = 1500, H = 1600;   // the URL baseline lands at 1552, leaving ~48px under it

	/* mulberry32: a few lines, good enough for brush noise, and seedable. */
	function rng(seed) {
		var a = seed >>> 0;
		return function () {
			a |= 0; a = a + 0x6D2B79F5 | 0;
			var t = Math.imul(a ^ a >>> 15, 1 | a);
			t = t + Math.imul(t ^ t >>> 7, 61 | t) ^ t;
			return ((t ^ t >>> 14) >>> 0) / 4294967296;
		};
	}
	function gauss(r) { return (r() + r() + r() + r() - 2) / 0.58; } // ~N(0,1), cheap

	/* ---------- the window ------------------------------------------- */

	/* Lines as y = y0 + (y1 - y0) * x / W, across the full width. */
	function lineY(y0, y1, x) { return y0 + (y1 - y0) * x / W; }

	/*
	 * The brush's own wobble: a few slow sines with random phase, so the
	 * edge undulates like one long stroke instead of a ruled line.
	 */
	function wobble(r, amp) {
		var w = [[600, 18], [170, 8], [45, 3]].map(function (k) {
			return { len: k[0] * (0.7 + r() * 0.6), a: k[1] * amp, ph: r() * Math.PI * 2 };
		});
		return function (x) {
			return w.reduce(function (y, k) { return y + Math.sin(x / k.len * Math.PI * 2 + k.ph) * k.a; }, 0);
		};
	}

	function windowPath(ctx, s, r) {
		var pad = 200, step = 4, top = wobble(r, s.grit), bot = wobble(r, s.grit), x;
		ctx.beginPath();
		for (x = -pad; x <= W + pad; x += step)
			ctx.lineTo(x, lineY(s.winTopL, s.winTopR, x) + top(x) + (r() - 0.5) * 3 * s.grit);
		for (x = W + pad; x >= -pad; x -= step)
			ctx.lineTo(x, lineY(s.winBotL, s.winBotR, x) + bot(x) + (r() - 0.5) * 3 * s.grit);
		ctx.closePath();
	}

	/**
	 * One brushed edge. `inward` is +1 when the window lies below the line
	 * (the top edge) and -1 when it lies above (the bottom edge).
	 */
	function brushEdge(m, r, y0, y1, inward, grit) {
		var ang = Math.atan2(y1 - y0, W);
		var ux = Math.cos(ang), uy = Math.sin(ang);
		var nx = -uy * inward, ny = ux * inward;   // unit normal INTO the window
		var len = W / Math.cos(ang) + 400;
		var sx = -200, sy = lineY(y0, y1, -200);
		var g = grit;

		function streak(t, d, L, th, rot) {
			var cx = sx + ux * t + nx * d, cy = sy + uy * t + ny * d;
			m.save();
			m.translate(cx, cy);
			m.rotate(ang + rot);
			m.fillRect(-L / 2, -th / 2, L, th);
			m.restore();
		}

		/* Bristle streaks. Positive d is inside the window (cut it away),
		   negative is outside (paint more). Tails are long and thin. */
		var n = Math.round(1100 * g);
		for (var i = 0; i < n; i++) {
			var t = r() * len;
			var d = gauss(r) * 13 * g;
			if (r() < 0.06) d += (r() < 0.5 ? -1 : 1) * r() * 60 * g;
			var L = 8 + Math.pow(r(), 2.5) * 150;
			var th = 0.8 + Math.pow(r(), 3) * 4;
			var rot = (r() - 0.5) * 0.05;
			m.globalCompositeOperation = d > 0 ? 'destination-out' : 'source-over';
			m.globalAlpha = 0.55 + r() * 0.45;
			streak(t, d, L, th, rot);
		}

		/* Splatter flung off the brush, thinning out with distance. */
		var k = Math.round(1400 * g);
		m.globalCompositeOperation = 'source-over';
		for (var j = 0; j < k; j++) {
			var t2 = r() * len;
			var d2 = -Math.log(1 - r() * 0.999) * 40 * g;
			var rad = 0.5 + Math.pow(r(), 5) * 4.5;
			var cx = sx + ux * t2 - nx * d2, cy = sy + uy * t2 - ny * d2;
			m.globalAlpha = 0.5 + r() * 0.5;
			m.beginPath(); m.arc(cx, cy, rad, 0, Math.PI * 2); m.fill();
		}
		/* And black flecks eaten into the art just inside. */
		m.globalCompositeOperation = 'destination-out';
		for (var q = 0; q < k * 0.7; q++) {
			var t3 = r() * len;
			var d3 = -Math.log(1 - r() * 0.999) * 30 * g;
			var rad3 = 0.6 + Math.pow(r(), 4) * 4;
			m.globalAlpha = 0.6 + r() * 0.4;
			m.beginPath(); m.arc(sx + ux * t3 + nx * d3, sy + uy * t3 + ny * d3, rad3, 0, Math.PI * 2); m.fill();
		}
		m.globalAlpha = 1;
		m.globalCompositeOperation = 'source-over';
	}

	var maskCache = { key: '', canvas: null };
	function windowMask(s) {
		var key = [s.winTopL, s.winTopR, s.winBotL, s.winBotR, s.grit, s.seed].join(',');
		if (maskCache.key === key) return maskCache.canvas;
		var c = document.createElement('canvas'); c.width = W; c.height = H;
		var m = c.getContext('2d');
		var r = rng(s.seed);
		m.fillStyle = '#fff';
		windowPath(m, s, r); m.fill();
		if (s.grit > 0) {
			brushEdge(m, r, s.winTopL, s.winTopR, 1, s.grit);
			brushEdge(m, r, s.winBotL, s.winBotR, -1, s.grit);
		}
		maskCache = { key: key, canvas: c };
		return c;
	}

	/* ---------- helpers ------------------------------------------------ */

	/* Letter-spaced text, centred. ctx.letterSpacing is not in every
	   browser yet, so it is spaced by hand. */
	function spaced(ctx, text, cx, y, spacing) {
		var chars = Array.from(text), widths = chars.map(function (c) { return ctx.measureText(c).width; });
		var total = widths.reduce(function (a, b) { return a + b; }, 0) + spacing * Math.max(0, chars.length - 1);
		var x = cx - total / 2, align = ctx.textAlign;
		ctx.textAlign = 'left';
		chars.forEach(function (c, i) { ctx.fillText(c, x, y); x += widths[i] + spacing; });
		ctx.textAlign = align;
		return total;
	}
	function spacedWidth(ctx, text, spacing) {
		var chars = Array.from(text);
		return chars.reduce(function (a, c) { return a + ctx.measureText(c).width; }, 0) + spacing * Math.max(0, chars.length - 1);
	}

	/* Font specs are "weight family", e.g. '800 "Saira Semi Condensed"'. */
	function font(spec, px) {
		var i = spec.indexOf(' ');
		return spec.slice(0, i) + ' ' + px + 'px ' + spec.slice(i + 1);
	}

	/* An image recoloured to a flat colour, keeping its alpha. */
	function tinted(img, w, h, color) {
		var c = document.createElement('canvas'); c.width = Math.max(1, Math.round(w)); c.height = Math.max(1, Math.round(h));
		var x = c.getContext('2d');
		x.drawImage(img, 0, 0, c.width, c.height);
		x.globalCompositeOperation = 'source-in';
		x.fillStyle = color; x.fillRect(0, 0, c.width, c.height);
		return c;
	}

	/* ---------- the flyer ---------------------------------------------- */

	function render(canvas, s, imgs) {
		canvas.width = W; canvas.height = H;
		var ctx = canvas.getContext('2d');
		ctx.fillStyle = '#000'; ctx.fillRect(0, 0, W, H);

		/* Art, cover-fit to the window's bounding box, then panned/zoomed. */
		if (imgs.art) {
			/* Cover what is VISIBLE of the window, not its full slanted
			   extent above the canvas, or every image comes in zoomed. */
			var top = Math.max(0, Math.min(s.winTopL, s.winTopR) - 40), bot = Math.min(H, Math.max(s.winBotL, s.winBotR) + 60);
			var bw = W, bh = bot - top;
			/* ROTATION KEEPS THE COVER. A tilted image needs to be bigger to
			   still reach every corner of the box, so the fit is computed
			   against the box's extent in the image's own rotated frame --
			   otherwise turning it to match the slant opens black corners. */
			var th = (s.artRot || 0) * Math.PI / 180, cs = Math.abs(Math.cos(th)), sn = Math.abs(Math.sin(th));
			var a = imgs.art, sc = Math.max((bw * cs + bh * sn) / a.width, (bw * sn + bh * cs) / a.height) * s.artZoom;
			var dw = a.width * sc, dh = a.height * sc;
			var cx = W / 2 + s.artX * (W / 2), cy = top + bh / 2 + s.artY * (bh / 2);

			var art = document.createElement('canvas'); art.width = W; art.height = H;
			var ac = art.getContext('2d');
			ac.save();
			ac.translate(cx, cy); ac.rotate(th);
			if (s.artFlip) ac.scale(-1, 1);   // after the rotate, so a flip keeps the tilt
			ac.drawImage(a, -dw / 2, -dh / 2, dw, dh);
			ac.restore();
			/* The shade: a falloff toward the bottom edge so the title reads. */
			if (s.shade > 0) {
				var yb = (s.winBotL + s.winBotR) / 2;
				var gr = ac.createLinearGradient(0, yb - 520, 0, yb + 40);
				gr.addColorStop(0, 'rgba(0,0,0,0)');
				gr.addColorStop(1, 'rgba(0,0,0,' + s.shade + ')');
				ac.fillStyle = gr; ac.fillRect(0, 0, W, H);
			}
			ac.globalCompositeOperation = 'destination-in';
			ac.drawImage(windowMask(s), 0, 0);
			ctx.drawImage(art, 0, 0);
		} else {
			ctx.globalAlpha = 0.18;
			ctx.drawImage(windowMask(s), 0, 0);
			ctx.globalAlpha = 1;
		}

		/* Skulliance mark, top left. */
		if (imgs.brand) {
			var lw = 340, lh = lw * imgs.brand.height / imgs.brand.width;
			ctx.drawImage(imgs.brand, 82, 62, lw, lh);
		}

		/* Points icon in a ring, with the ticker under it. */
		if (s.showIcon) {
			var ix = s.iconX, iy = s.iconY, R = 84;
			ctx.beginPath(); ctx.arc(ix, iy, R, 0, Math.PI * 2);
			ctx.fillStyle = '#000'; ctx.fill();
			ctx.lineWidth = 5; ctx.strokeStyle = s.colRing; ctx.stroke();
			if (imgs.icon) {
				var box = R * 2 * 0.72 * s.iconScale;
				var isc = Math.min(box / imgs.icon.width, box / imgs.icon.height);
				var iw = imgs.icon.width * isc, ih = imgs.icon.height * isc;
				ctx.save();
				ctx.beginPath(); ctx.arc(ix, iy, R - 3, 0, Math.PI * 2); ctx.clip();
				ctx.drawImage(s.iconWhite ? tinted(imgs.icon, iw, ih, '#fff') : imgs.icon, ix - iw / 2, iy - ih / 2, iw, ih);
				ctx.restore();
			}
			if (s.ticker) {
				ctx.fillStyle = s.colRing; ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
				ctx.font = '600 30px "Open Sans"';
				ctx.fillText(s.ticker.toUpperCase(), ix, iy + R + 48);
			}
		}

		/* Project logo, or the name set big if there is no logo yet. Its
		   box sits on the stack below, so a tall logo grows upward. */
		var logoBottom = 1180 + s.logoY;
		if (imgs.logo) {
			var maxW = 960 * s.logoScale, maxH = 420 * s.logoScale;
			var lsc = Math.min(maxW / imgs.logo.width, maxH / imgs.logo.height);
			var gw = imgs.logo.width * lsc, gh = imgs.logo.height * lsc;
			var src = s.logoWhite ? tinted(imgs.logo, gw, gh, '#fff') : imgs.logo;
			ctx.drawImage(src, (W - gw) / 2, logoBottom - gh, gw, gh);
		} else if (s.name) {
			ctx.fillStyle = '#fff'; ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';
			var fs = 220 * s.logoScale;
			ctx.font = font(s.headFont, fs);
			var nw = ctx.measureText(s.name.toUpperCase()).width;
			if (nw > 1240) { fs *= 1240 / nw; ctx.font = font(s.headFont, fs); }
			ctx.fillText(s.name.toUpperCase(), W / 2, logoBottom);
		}

		/* The stack. Positions are from the DRKL flyer, the tightest of the
		   three; the others only differ in how tall the logo is. */
		var y = logoBottom + 72;
		ctx.textAlign = 'center'; ctx.textBaseline = 'alphabetic';

		ctx.fillStyle = s.colHighlight;
		ctx.font = font(s.headFont, 58);
		spaced(ctx, s.line1.toUpperCase(), W / 2, y, 14);

		y += 108;
		ctx.fillStyle = '#fff';
		var hs = 120;
		ctx.font = font(s.headFont, hs);
		var hw = spacedWidth(ctx, s.line2.toUpperCase(), 3);
		hs *= 1280 / hw;
		ctx.font = font(s.headFont, hs);
		spaced(ctx, s.line2.toUpperCase(), W / 2, y, 3 * hs / 120);

		y += 38;
		ctx.fillStyle = s.colDivider;
		ctx.fillRect(136, y - 2, W - 272, 4);

		y += 58;
		ctx.fillStyle = '#fff';
		ctx.font = '600 32px "Open Sans"';
		var tl = s.line3.toUpperCase(), tsp = 11;
		if (spacedWidth(ctx, tl, tsp) > 1300) tsp = Math.max(1, tsp - (spacedWidth(ctx, tl, tsp) - 1300) / Math.max(1, tl.length - 1));
		spaced(ctx, tl, W / 2, y, tsp);

		y += 52;
		var bullets = s.bullets.filter(function (b) { return b.trim() !== ''; }).map(function (b) { return b.trim().toUpperCase(); });
		if (bullets.length) {
			ctx.fillStyle = s.colBullets;
			ctx.font = '400 26px "Open Sans"';
			spaced(ctx, bullets.join('  \u2022  '), W / 2, y, 0.5);
			y += 44;
		}

		if (s.url) {
			ctx.fillStyle = '#fff';
			ctx.font = 'italic 600 20px "Open Sans"';
			spaced(ctx, s.url.toUpperCase(), W / 2, y, 9);
		}
	}

	/* Rough "dominant vivid colours" of the art, for the swatch row. A
	   coarse hue histogram weighted by saturation, so a big dark
	   background does not win. */
	function palette(img, n) {
		var c = document.createElement('canvas'), S = 80;
		c.width = S; c.height = Math.max(1, Math.round(S * img.height / img.width));
		var x = c.getContext('2d'); x.drawImage(img, 0, 0, c.width, c.height);
		var d = x.getImageData(0, 0, c.width, c.height).data, bins = {};
		for (var i = 0; i < d.length; i += 4) {
			var r = d[i] / 255, g = d[i + 1] / 255, b = d[i + 2] / 255;
			var mx = Math.max(r, g, b), mn = Math.min(r, g, b), l = (mx + mn) / 2, ch = mx - mn;
			if (ch < 0.25 || l < 0.2 || l > 0.85) continue;
			var h;
			if (mx === r) h = ((g - b) / ch) % 6; else if (mx === g) h = (b - r) / ch + 2; else h = (r - g) / ch + 4;
			var k = Math.floor(((h * 60 + 360) % 360) / 15);
			var e = bins[k] || (bins[k] = { w: 0, r: 0, g: 0, b: 0 });
			e.w += ch; e.r += d[i] * ch; e.g += d[i + 1] * ch; e.b += d[i + 2] * ch;
		}
		var list = Object.keys(bins).map(function (k) { return bins[k]; }).sort(function (a, b) { return b.w - a.w; });
		var out = [];
		list.forEach(function (e) {
			if (out.length >= n) return;
			var hex = '#' + [e.r, e.g, e.b].map(function (v) {
				var s = Math.round(v / e.w).toString(16); return s.length < 2 ? '0' + s : s;
			}).join('');
			out.push(hex);
		});
		return out;
	}

	window.FlyerBuilder = { W: W, H: H, render: render, palette: palette };
})();
