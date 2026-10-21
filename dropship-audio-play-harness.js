/**
 * dropship-audio-play-harness.js -- node, no browser, no network.
 *
 * Runs the REAL roundFrame() and playRoundAudio() out of dropship.js.
 *
 * The sound a round plays is keyed on its FRAME, not its caption.
 * machine-gun2 and machine-gun3 are both captioned "Machine Gun", as are
 * rocket-launcher2 and 3, so no amount of reading the words can tell you
 * which picture is on screen -- and the art already numbers them. That
 * is what these checks are for, plus the catch-all for a surviving round
 * which has no moment to name.
 *
 * Usage: node dropship-audio-play-harness.js
 */
'use strict';
const fs = require('fs');

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

const js = fs.readFileSync(__dirname + '/dropship/dropship.js', 'utf8');
const mf = js.match(/function roundFrame\(round\) \{[\s\S]*?\n\}/);
const mp = js.match(/function playRoundAudio\(round\) \{[\s\S]*?\n\}/);
ok(!!mf && !!mp, 'the audio functions could not be found, so nothing below proves anything');
const SRC = (mf ? mf[0] : '') + '\n' + (mp ? mp[0] : '');

function run(payload, imgSrc) {
	const played = [], timers = [];
	const win = { dsAudio: payload };
	const fn = new Function('window', 'dsAudio', 'roundImage', 'loadFile', 'setTimeout',
		SRC + '\nreturn {frame: roundFrame, play: playRoundAudio};')(
		win, payload,
		function () { return imgSrc; },
		function (s, p) { played.push(p); },
		function (f, ms) { timers.push({ f: f, ms: ms }); });
	return { fn: fn, played: played, timers: timers,
	         fire: function () { timers.forEach(t => t.f()); } };
}

/* ROUND 0 GOES THROUGH THE SAME PATH. Its sound used to be a project_id
   ladder in displayRound, then briefly a separate "open" key; round 0
   shows reset.gif and has a caption, so it is looked up like any other
   round. The call site is in displayRound, which needs the whole page
   to run, so it is read rather than driven. */
ok(/currentRound == 0\)\{[\s\S]{0,900}?playRoundAudio\(currentRound\);/.test(js),
   'round 0 no longer asks for its sound, so a run opens in silence');
ok(!/dsAudio\.open/.test(js),
   'the separate opening key is still there, and it has no frame to sit under');

/* ---------- the frame comes out of the image --------------------------- */
const r0 = run({ frames: {} }, 'https://x/staking/dropship/oculus-lounge/images/weapons/demo2.png?v=17');
ok(r0.fn.frame(5) === 'weapons/demo2',
   'the frame is not read off the image path: ' + r0.fn.frame(5));
const flat = run({ frames: {} }, 'https://x/staking/dropship/images/live/beach/3.gif');
ok(flat.fn.frame(5) === 'live/beach/3',
   "Drop Ship's own flat art does not resolve: " + flat.fn.frame(5));
ok(run({ frames: {} }, '').fn.frame(5) === '', 'a round with no image still names a frame');
ok(run({ frames: {} }, 'https://x/nothing/here.png').fn.frame(5) === '',
   'a path with no images/ segment still names a frame');

/* ---------- two frames, one caption, two sounds ------------------------ */
const PAY = { frames: {
	'weapons/machine-gun2': { f: 'sounds/machine-gun2.mp3', d: 0 },
	'weapons/machine-gun3': { f: 'sounds/machine-gun3.mp3', d: 0 },
	'weapons/demo1': { f: 'sounds/paddle1.mp3', d: 0 },
	'weapons/demo2': { f: 'sounds/paddle2.mp3', d: 600 },
}, any: [ { p: 'live/', f: 'sounds/survived.mp3', d: 0 } ] };

const g2 = run(PAY, '/staking/dropship/images/weapons/machine-gun2.gif');
g2.fn.play(5);
ok(g2.played.length === 1 && g2.played[0].indexOf('machine-gun2') !== -1,
   'frame 2 of the machine gun played ' + JSON.stringify(g2.played));
const g3 = run(PAY, '/staking/dropship/images/weapons/machine-gun3.gif');
g3.fn.play(5);
ok(g3.played.length === 1 && g3.played[0].indexOf('machine-gun3') !== -1,
   'frame 3 played the same file as frame 2, which is what the captions could not separate');

/* THE NUMBERED PAIR, which is the whole point: demo1 is the activation
   and demo2 the explosion, and under a theme that calls it a Paddle
   those are paddle1 and paddle2. */
const d1 = run(PAY, '/staking/dropship/oculus-lounge/images/weapons/demo1.png');
d1.fn.play(5);
ok(d1.played.length === 1 && d1.played[0] === 'sounds/paddle1.mp3',
   'the activation frame did not play the first sound: ' + JSON.stringify(d1.played));
const d2 = run(PAY, '/staking/dropship/oculus-lounge/images/weapons/demo2.png');
d2.fn.play(5);
ok(d2.played.length === 0 && d2.timers.length === 1 && d2.timers[0].ms === 600,
   'the explosion frame did not wait its delay');
d2.fire();
ok(d2.played[0] === 'sounds/paddle2.mp3',
   'the explosion frame played the activation sound: ' + JSON.stringify(d2.played));

/* ---------- the catch-all ---------------------------------------------- */
const sv = run(PAY, '/staking/dropship/images/live/beach/3.gif');
sv.fn.play(5);
ok(sv.played.length === 1 && sv.played[0].indexOf('survived') !== -1,
   'a surviving round played nothing: ' + JSON.stringify(sv.played));
/* AN EXACT FRAME BEATS THE CATCH-ALL, or a secured frame gets the
   gunshot that every other round gets. */
const PAY2 = { frames: { 'live/beach/beach-secured': { f: 'sounds/beach-secured.mp3', d: 0 } },
               any: [ { p: 'live/', f: 'sounds/survived.mp3', d: 0 } ] };
const sec = run(PAY2, '/staking/dropship/images/live/beach/beach-secured.gif');
sec.fn.play(5);
ok(sec.played.length === 1 && sec.played[0].indexOf('beach-secured') !== -1,
   'the catch-all beat an exact frame: ' + JSON.stringify(sec.played));

/* ---------- nothing to play -------------------------------------------- */
const none = run(PAY, '/staking/dropship/images/die/2.gif');
none.fn.play(5);
ok(none.played.length === 0 && none.timers.length === 0,
   'a frame with no sound and no catch-all played something');
const empty = run({ frames: {}, any: [] }, '/staking/dropship/images/weapons/demo1.gif');
empty.fn.play(5);
ok(empty.played.length === 0, 'an empty table still played something');
let threw = false;
try { run(null, '/staking/dropship/images/weapons/demo1.gif').fn.play(5); } catch (e) { threw = true; }
ok(!threw, 'playRoundAudio throws when the page emitted no table');
/* A row with no file must not reach loadFile with an empty src. */
const blank = run({ frames: { 'weapons/demo1': { f: '', d: 0 } }, any: [] },
                  '/staking/dropship/images/weapons/demo1.gif');
blank.fn.play(5);
ok(blank.played.length === 0, 'a row with no file was played as an empty src');

console.log(fails ? '\n' + fails + ' check(s) FAILED' : '\nall good');
process.exit(fails ? 1 : 0);
