/**
 * save-image-harness.js - js/save-image.js, driven per platform.
 *
 *   node save-image-harness.js
 *
 * This file exists because getting a picture out of a page is three lines
 * that are wrong on exactly one platform each, and every one of those was
 * found by a player rather than by me:
 *
 *   - <a download> does nothing on an iPhone. Safari reports the attribute
 *     and then navigates to the blob instead, taking the person off the
 *     page and saving nothing.
 *   - Gating the share sheet on canShare() alone gives the sheet to the
 *     DESKTOP too, so a button marked Download offers to message the
 *     picture to somebody's contacts. That shipped.
 *   - The clipboard needs the PROMISE. Safari drops the click's
 *     activation across an await, so a write issued after the picture is
 *     ready is refused and the button looks dead.
 *
 * None of it is visible in the source, so this drives the real functions
 * under stubbed platforms and records which route each one takes.
 */
'use strict';
const fs = require('fs'), path = require('path'), vm = require('vm');

let fail = 0;
const ok = (c, what) => { if (!c) { fail++; console.log('  FAIL  ' + what); } };

const src = fs.readFileSync(path.join(__dirname, 'js', 'save-image.js'), 'utf8');

/** Load the module against one stubbed platform, and report what it did. */
function world(ua, platform, touch, opts) {
	opts = opts || {};
	const log = { route: null, shared: null, downloadName: null, revoked: false,
	              clipboard: null, created: [] };
	const win = {
		navigator: {
			userAgent: ua, platform: platform, maxTouchPoints: touch,
			canShare: opts.noShare ? undefined : () => true,
			share: opts.noShare ? undefined : (d) => {
				log.route = 'sheet'; log.shared = d;
				return opts.cancel ? Promise.reject(new Error('AbortError')) : Promise.resolve();
			},
			clipboard: opts.noClipboard ? undefined : {
				write: (items) => { log.clipboard = items; return Promise.resolve(); },
			},
		},
		ClipboardItem: opts.noClipboard ? undefined : function (m) { this.m = m; },
		atob: (x) => Buffer.from(x, 'base64').toString('binary'),
		Blob: function (parts) { this.parts = parts; this.size = 1; },
		File: function (parts, name) { this.name = name; },
		URL: { createObjectURL: () => 'blob:stub', revokeObjectURL: () => { log.revoked = true; } },
		setTimeout: (f) => { f(); },
		document: {
			createElement: () => {
				const a = { click() { log.route = 'download'; log.downloadName = a.download; },
				            remove() {}, href: '', download: '' };
				log.created.push(a);
				return a;
			},
			body: { appendChild() {} },
		},
	};
	win.window = win;
	vm.createContext(win);
	vm.runInContext(src, win);
	return { api: win.sklImage, log: log, win: win };
}

const MAC  = ['Mozilla/5.0 (Macintosh; Intel Mac OS X) Chrome/130', 'MacIntel', 0];
const WIN  = ['Mozilla/5.0 (Windows NT 10.0) Chrome/130', 'Win32', 0];
const IPH  = ['Mozilla/5.0 (iPhone; CPU iPhone OS 17_0) Safari', 'iPhone', 5];
const IPAD = ['Mozilla/5.0 (Macintosh; Intel Mac OS X) Safari/17', 'MacIntel', 5];
const AND  = ['Mozilla/5.0 (Linux; Android 14) Chrome/130', 'Linux armv8l', 5];

(async () => {
	console.log('which platform gets which route');
	for (const [name, p, want] of [
		['desktop Mac', MAC,  'download'],
		['Windows',     WIN,  'download'],
		['Android',     AND,  'download'],
		['iPhone',      IPH,  'sheet'],
		['iPad',        IPAD, 'sheet'],
	]) {
		const w = world(p[0], p[1], p[2]);
		await w.api.save(new w.win.Blob(['x']), 'map.png');
		ok(w.log.route === want,
		   name + ' takes the "' + w.log.route + '" route, expected "' + want + '"'
		 + (want === 'download'
		    ? ' -- a button marked Download must save a file, not open a share menu'
		    : ' -- an anchor download there navigates to the blob and saves nothing'));
	}

	console.log('\nthe download is a real download');
	{
		const w = world(MAC[0], MAC[1], MAC[2]);
		const msg = await w.api.save(new w.win.Blob(['x']), 'skulliance-rivalries-2026-10-18.png');
		ok(w.log.downloadName === 'skulliance-rivalries-2026-10-18.png',
		   'the filename does not reach the anchor (got ' + w.log.downloadName + ')');
		ok(w.log.revoked, 'the object url is never revoked, which leaks the blob for the session');
		ok(typeof msg === 'string' && msg !== '', 'the caller is told nothing happened');
	}

	console.log('\na cancelled share sheet is not an error');
	{
		const w = world(IPH[0], IPH[1], IPH[2], { cancel: true });
		const msg = await w.api.save(new w.win.Blob(['x']), 'x.png');
		ok(msg === '', 'cancelling the sheet reports something, and there is nothing to report');
	}

	console.log('\niOS with no share support still gets a try');
	{
		/* Better a download that might not work than a button that does
		   nothing at all. */
		const w = world(IPH[0], IPH[1], IPH[2], { noShare: true });
		await w.api.save(new w.win.Blob(['x']), 'x.png');
		ok(w.log.route === 'download', 'an iPhone without the share api gets no route at all');
	}

	console.log('\nthe clipboard is handed the promise, not the picture');
	{
		const w = world(MAC[0], MAC[1], MAC[2]);
		let settled = false;
		const pending = new Promise((res) => setTimeout(() => {
			settled = true; res(new w.win.Blob(['x']));
		}, 5));
		const p = w.api.copy(pending);
		/* The write must already be issued while the blob is still
		   pending -- that is the whole point, and it is what keeps
		   Safari's activation. */
		ok(w.log.clipboard !== null && settled === false,
		   'the clipboard write waited for the picture, which loses the activation Safari needs');
		await p;
		ok(w.log.clipboard[0].m['image/png'] === pending,
		   'the ClipboardItem was not given the promise');
	}

	console.log('\nand it says so when it cannot copy at all');
	{
		const w = world(MAC[0], MAC[1], MAC[2], { noClipboard: true });
		ok(w.api.canCopy() === false, 'canCopy() claims a clipboard that is not there');
		let threw = false;
		await w.api.copy(Promise.resolve(null)).catch(() => { threw = true; });
		ok(threw, 'copy() resolved on a browser with no clipboard api, so the button lies');
		const ok2 = world(MAC[0], MAC[1], MAC[2]);
		ok(ok2.api.canCopy() === true, 'canCopy() is false where the clipboard does exist');
	}

	console.log('\nthe canvas export is synchronous');
	{
		/* toBlob() is async, and on iOS a promise callback has no
		   activation left for share(). */
		/* The CALL, not the word: the comment in save-image.js explaining
		   why toBlob is not used contains "toBlob", so a bare search for
		   it fails on correct code forever. */
		ok(/toDataURL\s*\(/.test(src) && !/\.toBlob\s*\(/.test(src),
		   'the blob is built with .toBlob(), which loses the activation share() needs');
		const w = world(MAC[0], MAC[1], MAC[2]);
		const blob = w.api.canvasBlob({ toDataURL: () => 'data:image/png;base64,aGk=' });
		ok(blob && blob.parts, 'canvasBlob() produced nothing');
	}

	console.log('\nthe platform test cannot be replaced by feature detection');
	{
		/* iOS claims the capability it lacks, so this is the one place a
		   user-agent test is the correct tool. iPadOS reports MacIntel,
		   which is why the touch count is in there. */
		const w = world(IPAD[0], IPAD[1], IPAD[2]);
		ok(w.api.isIOS() === true, 'iPadOS is read as a desktop Mac');
		ok(world(MAC[0], MAC[1], MAC[2]).api.isIOS() === false, 'a desktop Mac is read as iOS');
		ok(world(AND[0], AND[1], AND[2]).api.isIOS() === false, 'Android is read as iOS');
	}

	console.log(fail ? '\n' + fail + ' FAILED' : '\nall good');
	process.exit(fail ? 1 : 0);
})();
