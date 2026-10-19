/*
 * js/save-image.js -- getting a picture out of a page, on every platform.
 *
 * ONE COPY, because the last two times this platform built the same thing
 * twice one of the copies was wrong within a week: the invite url was
 * assembled in two places and the fallback forgot the referral code, and
 * the digest poster's save worked everywhere except the one platform
 * nobody tested on. Both of those were found by a player, not by me.
 *
 * WHAT IS ACTUALLY HARD HERE, and why this is not three lines:
 *
 *   - <a download> DOES NOT WORK ON AN iPHONE. iOS Safari reports
 *     'download' in an anchor and then ignores it on a blob: url: it
 *     navigates to the blob instead, which takes the person off the page
 *     and puts nothing in their camera roll. The share sheet is the only
 *     route to Photos there.
 *   - IT IS ONLY WRONG ON iOS. Gated on navigator.canShare() alone,
 *     desktop Chrome and Safari answer yes as well, and a button marked
 *     Download opens a sheet offering to message the picture to the
 *     person's contacts. That shipped. Anywhere an anchor works, use it.
 *   - THE PLATFORM CANNOT BE FEATURE-DETECTED, which is why there is a
 *     user-agent test in here. The capability iOS lacks is one it claims
 *     to have. iPadOS reports itself as MacIntel, so the touch-point
 *     count is the only thing separating it from a desktop Mac.
 *   - THE CLIPBOARD NEEDS THE PROMISE, NOT THE BLOB. Safari drops the
 *     click's transient activation across an await, so a write issued
 *     after the picture is ready is refused. ClipboardItem accepts a
 *     promise, which lets the write be issued inside the gesture and
 *     settle afterwards -- so copy() takes a Promise<Blob> deliberately.
 *
 * Every entry point answers with a short string describing what happened,
 * so a caller can put it on screen without knowing any of the above.
 */
(function (w) {
	'use strict';

	function isIOS() {
		var n = w.navigator || {};
		var ua = n.userAgent || '';
		return /iP(hone|ad|od)/.test(ua)
		    || (n.platform === 'MacIntel' && n.maxTouchPoints > 1);
	}

	/*
	 * A canvas as a Blob, SYNCHRONOUSLY.
	 *
	 * toBlob() is asynchronous and that is fatal on iOS: navigator.share()
	 * has to be called while the click's activation is alive, and a
	 * promise callback is a later task with none left. toDataURL() and
	 * atob() are both synchronous, so the whole chain stays in the
	 * handler. Throws on a tainted canvas, exactly as toBlob would.
	 */
	function canvasBlob(cv) {
		var url = cv.toDataURL('image/png');
		var bin = w.atob(url.slice(url.indexOf(',') + 1));
		var u8  = new Uint8Array(bin.length);
		for (var i = 0; i < bin.length; i++) u8[i] = bin.charCodeAt(i);
		return new Blob([u8], { type: 'image/png' });
	}

	/** Save a picture: a real download wherever that works, the sheet on iOS. */
	function save(blob, name) {
		var file = null;
		try { file = new File([blob], name, { type: 'image/png' }); } catch (e) { /* older browser */ }

		if (isIOS() && file && w.navigator.canShare && w.navigator.share
		    && w.navigator.canShare({ files: [file] })) {
			return w.navigator.share({ files: [file] })
				.then(function () { return 'Saved.'; })
				/* A cancelled sheet is not a failure and must not say so. */
				.catch(function () { return ''; });
		}

		var a = w.document.createElement('a');
		a.href = w.URL.createObjectURL(blob);
		a.download = name;
		w.document.body.appendChild(a);
		a.click();
		a.remove();
		w.setTimeout(function () { w.URL.revokeObjectURL(a.href); }, 4000);
		return Promise.resolve('Downloaded.');
	}

	/**
	 * Copy a picture to the clipboard.
	 *
	 * TAKES A PROMISE. Call it synchronously from the click with whatever
	 * promise will eventually produce the blob -- handing it a finished
	 * Blob means the caller already awaited something, and Safari will
	 * have dropped the activation by then.
	 */
	function copy(blobPromise) {
		if (!w.navigator.clipboard || !w.navigator.clipboard.write || !w.ClipboardItem) {
			return Promise.reject(new Error('unsupported'));
		}
		return w.navigator.clipboard
			.write([new w.ClipboardItem({ 'image/png': blobPromise })])
			.then(function () { return 'Copied.'; });
	}

	/** Is copying a picture possible at all here? Firefox says no. */
	function canCopy() {
		return !!(w.navigator.clipboard && w.navigator.clipboard.write && w.ClipboardItem);
	}

	w.sklImage = { isIOS: isIOS, canvasBlob: canvasBlob, save: save, copy: copy, canCopy: canCopy };
})(window);
