/**
 * rivalries-export-harness.js - the map's Download/Copy, driven.
 *
 *   node rivalries-export-harness.js
 *
 * WHY THIS IS NOT COVERED BY rivalries-harness.js: that one checks the SVG
 * the renderer builds. This checks the step AFTER it, which fails in a way
 * nothing on the page reports.
 *
 * AN SVG RASTERISES THROUGH AN <img>, WHICH PUTS IT IN SECURE STATIC MODE
 * and forbids it from fetching anything. So every <image> in the exported
 * copy must already carry its pixels as a data uri. Miss that and the
 * download still succeeds, still produces a PNG of exactly the right size,
 * and every avatar and Fighter in it is a hole -- there is no error
 * anywhere, and the only way to notice is to look at the picture.
 *
 * exportBlob() is lifted out of the shipping rivalries.js and run against
 * stubs that record what it asked for and what it produced.
 */
'use strict';
const fs = require('fs'), path = require('path');

let fail = 0;
const ok = (c, what) => { if (!c) { fail++; console.log('  FAIL  ' + what); } };

const src = fs.readFileSync(path.join(__dirname, 'rivalries.js'), 'utf8');

/* ---- the SVG the renderer would have built ---- */
function makeSvg(hrefs) {
	const kids = hrefs.map((h) => ({
		tagName: 'image', _attrs: { href: h },
		getAttribute(k) { return k in this._attrs ? this._attrs[k] : null; },
		setAttribute(k, v) { this._attrs[k] = v; },
		removeAttribute(k) { delete this._attrs[k]; },
		remove() { this._removed = true; },
		_removed: false,
	}));
	return {
		tagName: 'svg', _attrs: { width: '800', height: '600' }, _kids: kids,
		getAttribute(k) { return k in this._attrs ? this._attrs[k] : null; },
		setAttribute(k, v) { this._attrs[k] = v; },
		cloneNode() { return makeSvg(hrefs); },
		querySelectorAll(sel) { return sel === 'image' ? this._kids : []; },
	};
}

/* ---- run exportBlob() against a world we control ---- */
function run(hrefs, opts) {
	opts = opts || {};
	const asked = [];
	const svg = makeSvg(hrefs);
	let serialised = null, filled = null, drawn = null, canvasSize = null;

	const win = {
		XMLSerializer: function () {
			this.serializeToString = (node) => {
				serialised = node;
				return '<svg>' + node.querySelectorAll('image')
					.filter((i) => !i._removed)
					.map((i) => '<image href="' + i.getAttribute('href') + '"/>').join('') + '</svg>';
			};
		},
		FileReader: function () {
			this.readAsDataURL = (b) => { this.result = 'data:image/png;base64,' + b._tag; this.onload(); };
		},
		setTimeout: (f) => f,
		requestIdleCallback: null,
		fetch: (u) => {
			asked.push(u);
			if ((opts.dead || []).some((d) => u.indexOf(d) >= 0)) {
				return Promise.resolve({ ok: false, status: 404 });
			}
			return Promise.resolve({ ok: true, blob: () => Promise.resolve({ _tag: 'OK' }) });
		},
		sklImage: { canvasBlob: (cv) => ({ _png: true, w: cv.width, h: cv.height }) },
		Image: function () {
			setImmediate(() => { this.onload && this.onload(); });
		},
		URL: global.URL,
	};
	win.window = win;

	const doc = {
		baseURI: 'https://www.skulliance.io/staking/rivalries.php',
		getElementById: () => null,
		createElement: () => ({
			width: 0, height: 0,
			getContext: () => ({
				set fillStyle(v) { filled = v; },
				fillRect: (x, y, w, h) => { canvasSize = [w, h]; },
				drawImage: (im, x, y, w, h) => { drawn = [w, h]; },
			}),
		}),
	};

	/* Lift exportBlob() and the two constants it closes over. */
	const grab = (name) => {
		const a = src.indexOf('  function ' + name + '(');
		let o = src.indexOf('{', a), d = 0;
		for (let i = o; i < src.length; i++) {
			if (src[i] === '{') d++;
			else if (src[i] === '}') { d--; if (!d) return src.slice(a, i + 1); }
		}
		throw new Error('unbalanced ' + name);
	};
	const consts = src.slice(src.indexOf('  const SCALE = 2;'), src.indexOf('  const dataUri'));
	const dataUriSrc = src.slice(src.indexOf('  const dataUri'), src.indexOf('  function exportBlob'));

	const body = [consts, dataUriSrc, grab('exportBlob'),
	              'return exportBlob();'].join('\n');
	const fn = new Function('window', 'document', 'host', 'fetch', 'FileReader',
	                        'XMLSerializer', 'Image', 'URL',
		'"use strict";\n' + body);

	return fn(win, doc, { querySelector: () => svg }, win.fetch, win.FileReader,
	          win.XMLSerializer, win.Image, global.URL)
		.then((blob) => ({ blob, asked, serialised, filled, canvasSize, drawn }));
}

const AV  = 'https://cdn.discordapp.com/avatars/123/abc.png';
const ART = 'images/dhc/250/head/skull.png';

(async () => {
	console.log('every picture is inlined before the svg is serialised');
	{
		const r = await run([AV, ART, AV]);
		/* Deduped: the same avatar twice is one request. */
		ok(r.asked.length === 2, 'the same url was fetched ' + r.asked.length + ' times, not once');
		ok(r.asked.some((u) => u.indexOf('cdn.discordapp.com') >= 0),
		   'the avatar was never fetched, so it cannot have been inlined');
		ok(r.asked.some((u) => u.indexOf('/staking/images/dhc/') >= 0),
		   'the relative trait art was not resolved against the page url: ' + r.asked.join(' '));

		const left = r.serialised.querySelectorAll('image').filter((i) => !i._removed);
		ok(left.length === 3, left.length + ' images survived, expected 3');
		const bad = left.filter((i) => String(i.getAttribute('href')).indexOf('data:') !== 0);
		ok(bad.length === 0,
		   bad.length + ' images are still external when the svg is serialised, which renders '
		 + 'as a hole in the exported picture: ' + bad.map((i) => i.getAttribute('href')).join(' '));
		/* xlink:href left behind would win over href in some renderers. */
		ok(left.every((i) => i.getAttribute('xlink:href') === null),
		   'a stale xlink:href is left on an image, which can beat the inlined href');
	}

	console.log('\na dead picture costs one tile, not the export');
	{
		const r = await run([AV, ART], { dead: ['cdn.discordapp.com'] });
		ok(!!r.blob, 'one unreachable image failed the whole export');
		const left = r.serialised.querySelectorAll('image').filter((i) => !i._removed);
		ok(left.length === 1, 'the dead image was kept as a broken frame');
		ok(String(left[0].getAttribute('href')).indexOf('data:') === 0, 'the good image was not inlined');
	}

	console.log('\nthe picture has a background and real resolution');
	{
		const r = await run([ART]);
		/* An SVG has no background; a transparent PNG reads as broken
		   wherever it is pasted onto white. */
		ok(r.filled === '#050c14',
		   'the canvas was not filled with the map background first (got ' + r.filled + ')');
		ok(r.canvasSize && r.canvasSize[0] === 1600 && r.canvasSize[1] === 1200,
		   'the raster is ' + (r.canvasSize || []).join('x') + ', not 2x the 800x600 map');
		ok(r.drawn && r.drawn[0] === 1600, 'the map was not drawn at the full canvas size');
		ok(r.blob && r.blob._png, 'no png came back');
	}

	console.log('\nthe button wiring says what it needs');
	{
		/* Copy must hand the clipboard a PROMISE from inside the click:
		   awaiting the blob first loses Safari's activation and the write
		   is refused, which looks exactly like a dead button. */
		/*
		 * The clipboard call must come BEFORE anything is awaited. Matching
		 * the exact spelling `copy(build())` broke the moment the handler
		 * grew a local for the promise, which was not a regression -- so
		 * check the property instead: inside the copy handler, no .then()
		 * may precede the copy() call. Awaiting the blob first is what
		 * loses Safari's activation.
		 */
		const h = src.slice(src.indexOf("cp.addEventListener('click'"));
		const handler = h.slice(0, h.indexOf('\n    });'));
		const atCopy = handler.indexOf('sklImage.copy(');
		const atThen = handler.indexOf('.then(');
		ok(atCopy >= 0, 'the copy handler never reaches the clipboard');
		ok(atCopy >= 0 && (atThen < 0 || atCopy < atThen),
		   'the copy handler awaits the picture before writing it, which Safari refuses');
		ok(!/sklImage\.copy\(\s*await/.test(handler),
		   'the clipboard is handed an awaited blob');

		/* A dropped picture is otherwise silent: right size, missing face. */
		ok(/dropped/.test(src), 'nothing counts the pictures that could not be inlined');
		ok(/missing\(b\)/.test(src), 'the buttons never mention a picture that was left out');
		ok(/sklImage\.save\(/.test(src), 'Download does not go through the shared saver');
		/* And neither button exists if the shared file did not load. */
		ok(/CAN_EXPORT/.test(src) && /!CAN_EXPORT/.test(src),
		   'the buttons are shown even when js/save-image.js has not loaded');
		ok(/window\.sklImage/.test(src), 'the export does not check for the shared helper at all');
	}

	console.log(fail ? '\n' + fail + ' FAILED' : '\nall good');
	process.exit(fail ? 1 : 0);
})();
