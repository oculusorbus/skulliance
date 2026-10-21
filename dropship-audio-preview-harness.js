/**
 * dropship-audio-preview-harness.js -- node, no browser, no network.
 *
 * Runs the REAL sndSrc() out of dropship/admin-art.php against a stub.
 *
 * Reported: a Sound row read "silent, Drop Ship uses demo.mp3" and then
 * would not play it. Two different questions got the same answer -- what
 * the RUN does is the status text, what you can LISTEN to is anything the
 * row can point at -- so the button previews a candidate, and follows the
 * picker, which is the point of being offered a list of sounds at all.
 *
 * Usage: node dropship-audio-preview-harness.js
 */
'use strict';
const fs = require('fs');

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

const php = fs.readFileSync(__dirname + '/dropship/admin-art.php', 'utf8');
const m = php.match(/\tfunction sndSrc\(btn\) \{[\s\S]*?\n\t\}/);
ok(!!m, 'sndSrc() could not be found in admin-art.php, so nothing below proves anything');
const SRC = m ? m[0] : 'function sndSrc(){return "";}';

function btnWith(dataSrc, selValue) {
	const sel = selValue === null ? null : { name: 'audio_use', value: selValue };
	const row = { querySelector: function (q) { return q.indexOf('audio_use') !== -1 ? sel : null; } };
	return { closest: function () { return row; },
	         getAttribute: function (n) { return n === 'data-src' ? dataSrc : null; } };
}
const sndSrc = new Function(SRC + '\nreturn sndSrc;')();

/* THE SERVER'S ANSWER when nothing is picked. */
ok(sndSrc(btnWith('sounds/demo.mp3?v=17', '')) === 'sounds/demo.mp3?v=17',
   'the button ignores the file the server put on it');

/* THE PICKER WINS when something is chosen -- that is the audition. */
const picked = sndSrc(btnWith('sounds/demo.mp3?v=17', 'spankme.mp3'));
ok(picked.indexOf('sounds/spankme.mp3') === 0,
   'choosing a file in the dropdown does not change what the button plays: ' + picked);
/* BUSTED, because the file may have been replaced since the page loaded
   and the whole reason to press it is to hear what is there now. */
ok(/\?t=\d{10,}$/.test(picked), 'the picked preview can be served from cache: ' + picked);

/* A ROW WITH NOTHING AT ALL stays silent rather than playing "sounds/". */
ok(sndSrc(btnWith(null, '')) === '', 'a row with nothing to play builds a path anyway');
ok(sndSrc(btnWith('', null)) === '', 'a row with no picker and no file builds a path anyway');

/* AND A ROW THAT ONLY HAS A PICK still plays -- the silent row is the
   one most in need of an audition. */
const only = sndSrc(btnWith(null, 'ballgag.mp3'));
ok(only.indexOf('sounds/ballgag.mp3') === 0,
   'a silent row cannot preview the file you just chose for it: ' + only);

/* It must not throw where closest() is missing, which is every browser
   this does not run in and every stub that forgets it. */
let threw = false;
try { sndSrc({ getAttribute: function () { return 'sounds/x.mp3'; } }); } catch (e) { threw = true; }
ok(!threw, 'sndSrc throws without closest(), taking the click handler with it');

console.log(fails ? '\n' + fails + ' check(s) FAILED' : '\nall good');
process.exit(fails ? 1 : 0);
