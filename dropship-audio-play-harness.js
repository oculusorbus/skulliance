/**
 * dropship-audio-play-harness.js -- node, no browser, no network.
 *
 * Runs the REAL playRoundAudio() out of dropship.js against a stub.
 *
 * It replaced thirty-four if-branches, and the thing those branches
 * carried that is easy to lose is ORDER: the match is a substring of the
 * caption, so "Smoke Bomb" also matches "Pull Smoke Bomb Pin" and the pin
 * has to win. First match wins, nothing after it fires, and a delayed row
 * still only fires once.
 *
 * Usage: node dropship-audio-play-harness.js
 */
'use strict';
const fs = require('fs');

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

const js = fs.readFileSync(__dirname + '/dropship/dropship.js', 'utf8');
const m = js.match(/function playRoundAudio\(round\) \{[\s\S]*?\n\}/);
ok(!!m, 'playRoundAudio() could not be found, so nothing below proves anything');
const SRC = m ? m[0] : 'function playRoundAudio(){}';

function run(rows, caption) {
	const played = [];
	const timers = [];
	const sandbox = {
		window: { dsAudio: rows === null ? null : { rows: rows } },
		roundCaption: function () { return caption; },
		loadFile: function (src, path) { played.push({ src: src, path: path, at: 'now' }); },
		setTimeout: function (fn, ms) { timers.push({ fn: fn, ms: ms }); },
	};
	sandbox.dsAudio = sandbox.window.dsAudio;
	const fn = new Function('window', 'dsAudio', 'roundCaption', 'loadFile', 'setTimeout',
		SRC + '\nreturn playRoundAudio;')(
		sandbox.window, sandbox.dsAudio, sandbox.roundCaption, sandbox.loadFile, sandbox.setTimeout);
	fn(5);
	return {
		played: played,
		timers: timers,
		fire: function () { timers.forEach(t => { t.fn(); }); },
	};
}

const ROWS = [
	{ m: 'Pull Smoke Bomb Pin', f: 'sounds/pull-smoke-bomb-pin.mp3?v=1', d: 600 },
	{ m: 'Smoke Bomb',          f: 'sounds/smoke-bomb.mp3?v=1',          d: 0 },
	{ m: 'Survived',            f: 'sounds/survived.mp3?v=1',            d: 0 },
];

/* THE SHADOWING CASE, which is the whole reason order is in the data. */
const pin = run(ROWS, 'Round 9 - Pull Smoke Bomb Pin');
ok(pin.played.length === 0, 'a delayed sound played immediately instead of waiting');
ok(pin.timers.length === 1 && pin.timers[0].ms === 600,
   'the pin did not schedule its 600ms wait');
/* FIRST MATCH WINS AND NOTHING AFTER IT FIRES, counted BEFORE the timer
   is run: a caption containing the pin also contains "Smoke Bomb", and
   both firing is two sounds over each other. */
ok(pin.played.length + pin.timers.length === 1,
   'more than one row fired for one caption, so two sounds overlap');
pin.fire();
ok(pin.played.length === 1 && pin.played[0].path.indexOf('pull-smoke-bomb-pin') !== -1,
   'the pin played the wrong file: ' + JSON.stringify(pin.played));

const bomb = run(ROWS, 'Round 9 - Smoke Bomb');
ok(bomb.played.length === 1 && bomb.played[0].path.indexOf('smoke-bomb') !== -1,
   'a plain smoke bomb did not play its own sound');
ok(bomb.timers.length === 0, 'an undelayed row went through setTimeout');

/* THE CATCH-ALL still answers a caption nothing else matches. */
const surv = run(ROWS, 'Round 4 - Survived');
ok(surv.played.length === 1 && surv.played[0].path.indexOf('survived') !== -1,
   'a plain surviving round played nothing');

/* NOTHING MATCHES: silence, not a throw and not a stray play. */
const none = run(ROWS, 'Round 4 - Beach Secured');
ok(none.played.length === 0 && none.timers.length === 0,
   'a caption matching no row still played something');

/*
 * AN EMPTY CAPTION is an unreadable round and must play nothing.
 *
 * EQUIVALENT MUTANT, recorded rather than chased: deleting the explicit
 * `cap === ''` guard changes no answer today, because every row's match
 * text is a real word and ''.indexOf(word) is -1. The guard is there
 * because the danger is the other way round -- a row whose match text
 * were ever empty would match EVERY caption, since indexOf('') is 0 on
 * any string -- so the row below is the one actually worth asserting.
 */
const empty = run(ROWS, '');
ok(empty.played.length === 0 && empty.timers.length === 0,
   'an unreadable round played something');
const blank = run([{ m: '', f: 'sounds/x.mp3', d: 0 }], 'Round 4 - Survived');
ok(blank.played.length === 0,
   'a row with empty match text matched a caption, and it would match every caption');

/* NO TABLE AT ALL: a page that did not emit one must not throw and take
   the rest of the round with it. */
let threw = false;
try { run(null, 'Round 4 - Survived'); } catch (e) { threw = true; }
ok(!threw, 'playRoundAudio throws when the page emitted no table');

/* THE DELAY IS CARRIED PER ROW, not shared. Two delayed rows in one
   table must not close over the same file. */
const two = run([
	{ m: 'Air Strike', f: 'sounds/air-strike.mp3', d: 2000 },
	{ m: 'Exo Suit',   f: 'sounds/exo-suit.mp3',   d: 300 },
], 'Round 14 - Exo Suit');
ok(two.timers.length === 1 && two.timers[0].ms === 300, 'the wrong row scheduled');
two.fire();
ok(two.played.length === 1 && two.played[0].path.indexOf('exo-suit') !== -1,
   'the delayed row played another row\'s file: ' + JSON.stringify(two.played));

console.log(fails ? '\n' + fails + ' check(s) FAILED' : '\nall good');
process.exit(fails ? 1 : 0);
