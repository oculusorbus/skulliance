/* dhcarena-share-harness.js - node, no browser, no network.
 *
 * The Arena end card's Share-on-X button, run as the REAL shareLink().
 *
 * WHY THIS EXISTS, AND WHY IT EXECUTES RATHER THAN GREPS.
 *
 * Nothing covered this copy at all, which is how a loss post came to read
 * "My Crew went down to @someone's Crew in the DHC Arena. Going again." for
 * every loss ever played -- a sentence that says nothing about the battle
 * and asserts an intention the game cannot know. The owner asked what it
 * meant, which is the correct response to it.
 *
 * A source grep cannot catch that class of thing. It can only ask whether a
 * phrase appears, and every wrong version of this function contains
 * perfectly reasonable-looking phrases. So the function is lifted out of
 * dhcarena.php and run, and the POST ITSELF is read.
 *
 * WHAT GOES WRONG QUIETLY HERE, beyond the wording:
 *
 *   X counts ANY url as 23 characters and cuts the overflow off the END,
 *   which is exactly where the @skulliance invitation and Maxingo's credit
 *   live. A post that loses them still looks fine to whoever sent it.
 *   A practice result that reads as a ranked one is a false claim about
 *   another player, so "(Practice)" goes in FRONT, where the trim cannot
 *   reach it.
 *   The rivalry aside is 'tormentor' or 'nemesis' and never 'duck': the
 *   first two are the poster admitting something, the third names and
 *   humiliates somebody who did not write the post.
 *
 * Invoked by dhcarena-harness.php and reported there as one assertion.
 *
 * Usage: node dhcarena-share-harness.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');

let fail = 0;
const ok = (c, w) => { if (!c) { fail++; console.log('  FAIL  ' + w); } };

const page = fs.readFileSync(path.join(__dirname, 'dhcarena.php'), 'utf8');

/* The function, signature to the line that ends it. Nothing is rewritten:
   it sets el.href exactly as it does in the browser and the fixture below
   reads the href back, so what is asserted is what a player would post. */
const a = page.indexOf('function shareLink(won, standing){');
const b = page.indexOf('\n}\n', page.indexOf("el.href = 'https://x.com/intent/post?text='", a));
ok(a > -1 && b > a, 'shareLink() is gone from dhcarena.php, or no longer sets el.href');
const src = page.slice(a, b + 3);

/** Build the post a given battle would produce, and hand back its parts. */
function post(over) {
	const o = Object.assign({
		won: false, standing: 1, practice: false, battleId: 7,
		defenderName: 'michael_6658', defenderTag: '', best: 1,
		mine: ['Kat Skratch', 'OrbiTall', 'Grim'],
		foes: ['Bonecage', 'Sledge', 'Vex'],
	}, over || {});
	const el = { href: '' };
	const slim = (names, downFrom) => names.map((n, i) => ({ name: n, ko: i >= downFrom }));
	const ctx = {
		$: (id) => (id === 'ecShare' ? el : null),
		encodeURIComponent,
		practice: o.practice,
		battleId: o.battleId,
		S: {
			defenderName: o.defenderName, defenderTag: o.defenderTag,
			stats: { best: o.best },
			/* ko flags only matter to the win branch's hero list; the
			   count the post uses is the `standing` argument, exactly as
			   showEnd() computes and passes it. */
			mine: slim(o.mine, o.won ? o.standing : 0),
			foes: slim(o.foes, o.won ? 0 : o.standing),
		},
	};
	vm.createContext(ctx);
	vm.runInContext(src, ctx);
	ctx.shareLink(o.won, o.standing);
	const u = new URL(el.href);
	return { text: u.searchParams.get('text'), url: u.searchParams.get('url'), href: el.href };
}

console.log('a loss says how close it was, not what the player plans to do next');
/*
 * THE REPORTED BUG. Every loss, however close, posted the same sentence.
 */
{
	const p = post({ won: false, standing: 1 });
	ok(p.text.indexOf('Going again') === -1,
	   'the loss post still says "Going again", which describes no battle: ' + p.text);
	ok(/1 of theirs still standing/.test(p.text),
	   'a Crew that took two of three down does not say so: ' + p.text);
}
{
	const p = post({ won: false, standing: 3 });
	ok(/their whole Crew still standing/.test(p.text),
	   'a sweep against the poster reads the same as a near miss: ' + p.text);
	ok(p.text.indexOf('3 of theirs') === -1,
	   'a full Crew is counted out instead of named: ' + p.text);
}
/* AND THE THREE OUTCOMES MUST NOT COLLIDE. The whole point is that a reader
   can tell them apart, so the sentences have to actually differ. */
{
	const seen = [1, 2, 3].map((n) => post({ won: false, standing: n }).text);
	ok(new Set(seen).size === 3,
	   'two different losses produce the same post, so the number says nothing');
}

console.log('a win still reports its own margin');
{
	const p = post({ won: true, standing: 3 });
	ok(/Swept /.test(p.text) && /did not lose a Fighter/.test(p.text),
	   'a flawless win lost its wording: ' + p.text);
	const q = post({ won: true, standing: 2 });
	ok(/2 Fighters still standing/.test(q.text) && /held the line/.test(q.text),
	   'a win no longer names the Fighters that survived it: ' + q.text);
	const r = post({ won: true, standing: 1 });
	ok(/1 Fighter still standing/.test(r.text) && !/1 Fighters/.test(r.text),
	   'the win line does not agree with itself on number: ' + r.text);
}

console.log('the opponent is named in words, and only the asides that point at the poster');
{
	ok(post({}).text.indexOf('@michael_6658') === -1,
	   'the post @-tags a Discord name, which belongs to a stranger on X');
	ok(/michael_6658's Crew/.test(post({}).text), 'the opponent is not named at all');
	ok(/\(my nemesis\) michael_6658/.test(post({ defenderTag: 'nemesis' }).text),
	   'the rivalry standing the game already tracks is not offered');
	ok(post({ defenderTag: 'duck' }).text.indexOf('duck') === -1,
	   'the post calls somebody else a sitting duck, in a post they did not write');
	ok(/a practice Crew/.test(post({ practice: true }).text)
	   && post({ practice: true }).text.indexOf('michael') === -1,
	   'a practice battle claims a real opponent');
}

console.log('the budget holds, and the parts that must survive it are in front');
{
	/* X charges 23 for the url plus a space. The tail carries the invitation
	   and the art credit and is appended AFTER the trim, so the whole post
	   is what has to fit. */
	const long = 'a'.repeat(140);
	const p = post({ won: true, standing: 2, defenderName: long, best: 44 });
	ok(p.text.length + 24 <= 280,
	   'the post overflows 280 once X has charged for the url: ' + (p.text.length + 24));
	ok(/Join @skulliance/.test(p.text) && /MMAXI404/.test(p.text),
	   'the trim ate the invitation or the art credit, which is where it cuts from');
	const q = post({ won: false, standing: 1, practice: true, defenderName: long });
	ok(q.text.indexOf('(Practice)') === 0,
	   'a practice post can lose its own first word and read as a ranked result');
	ok(/battle=7/.test(post({}).url),
	   'the link does not name the battle, so the post gets the stock card');
	ok(post({ battleId: 0 }).url.indexOf('battle=') === -1,
	   'a battle with no id still claims one in its url');
}

console.log(fail ? '\nFAILED: ' + fail + ' check(s)' : '\nall Arena share checks passed');
process.exit(fail ? 1 : 0);
