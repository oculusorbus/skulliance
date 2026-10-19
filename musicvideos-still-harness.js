/**
 * musicvideos-still-harness.js - the midpoint still, driven.
 *
 *   node musicvideos-still-harness.js
 *   (also run by musicvideos-harness.php, reported as one assertion)
 *
 * WHY THIS IS NOT A BROWSER TEST. The page has no poster images -- nothing
 * on this server can pull a frame out of an mp4 -- so the browser makes
 * the poster itself by seeking each video to its own midpoint. Whether
 * the resulting frame LOOKS right can only be judged by eye, and the
 * Chrome available here will not decode video at all (readyState stays 0
 * with no error). What CAN be checked is the handler: the arithmetic, the
 * guards, and the one piece of behaviour that is easy to get wrong.
 *
 * THAT PIECE: seeking leaves currentTime in the middle of the film, so
 * without a rewind the first press of play drops the viewer halfway
 * through the video they just chose to watch. It has to rewind once, and
 * it must NOT rewind somebody who seeked somewhere themselves.
 */
'use strict';
const fs = require('fs'), path = require('path'), vm = require('vm');

let fail = 0;
const ok = (c, what) => { if (!c) { fail++; console.log('  FAIL  ' + what); } };

const page = fs.readFileSync(path.join(__dirname, 'musicvideos.php'), 'utf8');
const m = page.match(/<script>([\s\S]*?)<\/script>/);
ok(!!m, 'there is no still-frame script in musicvideos.php');
const src = m ? m[1] : '';

/** A video element that records what was done to it. */
function makeVideo(duration) {
	const listeners = {};
	return {
		duration: duration, currentTime: 0, _sets: [],
		addEventListener(t, fn) { (listeners[t] = listeners[t] || []).push(fn); },
		fire(t) { (listeners[t] || []).forEach((fn) => fn()); },
		get _time() { return this.currentTime; },
	};
}

function run(videos) {
	const sandbox = {
		document: { querySelectorAll: () => videos },
		isFinite: isFinite, Math: Math,
	};
	vm.createContext(sandbox);
	vm.runInContext(src, sandbox);
	return videos;
}

console.log('the still is the middle of the film');
{
	const v = makeVideo(60);
	run([v]);
	v.fire('loadedmetadata');
	ok(v.currentTime === 30, 'seeked to ' + v.currentTime + 's of a 60s video, not the midpoint');

	/* Half a second in -- all a #t= fragment can ask for without knowing
	   the duration -- is usually still a fade from black. That is the
	   whole reason this script exists, so it must not settle for it. */
	ok(v.currentTime > 1, 'the still is within a second of the start, which is usually black');
}

console.log('\nplay still starts at the beginning');
{
	const v = makeVideo(60);
	run([v]);
	v.fire('loadedmetadata');
	ok(v.currentTime === 30, 'setup: not seeked');
	v.fire('play');
	ok(v.currentTime === 0,
	   'pressing play starts at ' + v.currentTime + 's -- the viewer is dropped halfway through '
	 + 'the video they just chose to watch');

	/*
	 * Only once. After the first play it is theirs -- and the case that
	 * proves it has to land ON the old midpoint, because that is the only
	 * time the rewind test would match again. Scrubbing back to the middle
	 * and pressing play is an ordinary thing to do, and being thrown to
	 * the start for it would be maddening.
	 */
	v.currentTime = 30;
	v.fire('play');
	ok(v.currentTime === 30,
	   'scrubbing back to the middle and pressing play rewound them to ' + v.currentTime);
	v.currentTime = 42;
	v.fire('play');
	ok(v.currentTime === 42, 'a later play rewound them again, from 42s back to ' + v.currentTime);
}

console.log('\nsomebody who seeks for themselves is left alone');
{
	const v = makeVideo(60);
	run([v]);
	v.fire('loadedmetadata');
	/* They dragged the scrubber before pressing play. */
	v.currentTime = 12;
	v.fire('play');
	ok(v.currentTime === 12,
	   'a viewer who scrubbed to 12s was rewound to ' + v.currentTime + 's when they pressed play');
}

console.log('\nnothing breaks on a video that cannot say how long it is');
{
	/* A stream, or metadata that never arrives: duration is NaN or 0 and
	   the seek must simply not happen. Setting currentTime = NaN throws in
	   a real browser. */
	for (const d of [NaN, 0, Infinity, -1]) {
		const v = makeVideo(d);
		run([v]);
		v.fire('loadedmetadata');
		ok(v.currentTime === 0,
		   'a duration of ' + d + ' produced a seek to ' + v.currentTime);
		v.fire('play');
		ok(v.currentTime === 0, 'and then play moved it to ' + v.currentTime);
	}
}

console.log('\nevery video on the page gets one, independently');
{
	const a = makeVideo(100), b = makeVideo(30);
	run([a, b]);
	a.fire('loadedmetadata'); b.fire('loadedmetadata');
	ok(a.currentTime === 50 && b.currentTime === 15,
	   'two videos did not get their own midpoints: ' + a.currentTime + ', ' + b.currentTime);
	/* Playing one must not rewind the other. */
	a.fire('play');
	ok(a.currentTime === 0 && b.currentTime === 15,
	   'playing one video rewound the other to ' + b.currentTime);
}

console.log('\na second loadedmetadata does not re-seek');
{
	/* Chrome fires it again after a seek on some paths; re-seeking would
	   fight the viewer every time they scrubbed. */
	const v = makeVideo(60);
	run([v]);
	v.fire('loadedmetadata');
	v.currentTime = 5;
	v.fire('loadedmetadata');
	ok(v.currentTime === 5, 'a repeat loadedmetadata dragged them back to ' + v.currentTime);
}

console.log(fail ? '\n' + fail + ' FAILED' : '\nall good');
process.exit(fail ? 1 : 0);
