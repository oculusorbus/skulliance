/* dhc-share-harness.js - node, no browser, no network.
 *
 * The two Share-on-X buttons: the icon on the assembler and the one in the
 * Collection modal.
 *
 * WHAT GOES WRONG QUIETLY HERE. X counts ANY url as 23 characters however
 * long it really is, and silently cuts whatever does not fit -- off the END,
 * which is exactly where the @skulliance handle lives. A post that loses its
 * handle still looks fine to the person posting it and reaches nobody; that
 * is the whole point of tagging the account, and shareOnXUrl() in db.php
 * already carries a comment about it. These two build their text in the
 * browser instead, so they need the same budget and nothing enforces it.
 *
 * The rank selection matters too: the claim is "#4 deadliest of 108", and
 * picking the WORST two placements instead of the best would be worse than
 * posting nothing.
 *
 * Usage: node dhc-share-harness.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');

let fail = 0;
const ok = (c, w) => { if (!c) { fail++; console.log('  FAIL  ' + w); } };
const strip = (t) => t.replace(/<\?php[\s\S]*?\?>/g, '0');

const gallery   = fs.readFileSync(path.join(__dirname, 'dhcgallery.php'), 'utf8');
const assembler = fs.readFileSync(path.join(__dirname, 'dhc-assembler.php'), 'utf8');

/* X's own arithmetic: 280 total, any URL counts 23 (+1 for the space). */
const LIMIT = 280;
const URL_COST = 24;
const fits = (text) => text.length + URL_COST <= LIMIT;

/* ---- the Collection modal's shareText(), lifted out and run ---- */
const a = gallery.indexOf('  function shareText(f) {');
const b = gallery.indexOf('  function shareHref(f) {');
ok(a > -1 && b > a, 'shareText() is gone from dhcgallery.php');
const ctx = {
	AXES: [['rarest','Rarest'],['might','Deadliest'],['tough','Toughest'],['power','Hardest hitting']],
	X_HANDLE: '@skulliance',
};
vm.createContext(ctx);
vm.runInContext(strip(gallery.slice(a, b)), ctx);

const fighter = (over) => Object.assign({
	name: 'DHC2F528', rankOf: 108, pow: 118, hp: 742,
	parts: new Array(9).fill(0),
	rank: { rarest: 54, might: 4, tough: 61, power: 57 },
}, over || {});

console.log('the Collection share names the BEST placements');
{
	const t = ctx.shareText(fighter());
	ok(/#4 deadliest/.test(t), 'the best placement (#4 deadliest) is not in the post: ' + t);
	ok(/#54 rarest/.test(t),   'the second-best placement is not in the post: ' + t);
	ok(!/#61 toughest/.test(t), 'a worse placement was included over a better one: ' + t);
	ok(t.indexOf('@skulliance') > -1, 'the post does not tag the account, which is the point of posting it');
	ok(fits(t), 'the post is ' + (t.length + URL_COST) + ' characters with the URL, over X\'s ' + LIMIT);
	console.log('  ' + JSON.stringify(t));
}

console.log('\nand stays inside the budget when everything is long');
{
	/* 48 characters is the name column's maximum and six-figure ranks are
	   what this looks like if the collection ever gets that big -- and
	   together they still come to about 180, well inside the limit. So this
	   case proves the realistic post fits, and NOT that the guard works: a
	   mutation removing the budget entirely survived it. */
	const real = ctx.shareText(fighter({
		name: 'X'.repeat(48), rankOf: 987654,
		rank: { rarest: 123456, might: 234567, tough: 345678, power: 456789 },
		pow: 99999, hp: 99999, parts: new Array(99).fill(0),
	}));
	ok(fits(real), 'the longest realistic Fighter overflows: ' + (real.length + URL_COST) + ' characters');
	ok(real.indexOf('@skulliance') > -1, 'the longest realistic post lost its handle');

	/* THE GUARD ITSELF. The name column caps at 48 today, so nothing in the
	   data can reach the limit -- which is exactly why the truncation path
	   needs driving deliberately rather than hoping a fixture trips it. */
	const huge = ctx.shareText(fighter({ name: 'Z'.repeat(400) }));
	ok(fits(huge), 'truncation did not bring an oversized post under the limit: '
	   + (huge.length + URL_COST) + ' characters');
	ok(huge.indexOf('@skulliance') > -1,
	   'truncation ate the handle -- the exact failure shareOnXUrl() documents: ' + JSON.stringify(huge.slice(-40)));
	ok(/…\n\n@skulliance$/.test(huge), 'an oversized post was cut without saying it was cut');
}

console.log('\nthe stat tail is dropped rather than overflowing');
{
	const short = ctx.shareText(fighter());
	const long  = ctx.shareText(fighter({ name: 'Y'.repeat(48) }));
	ok(/power/.test(short), 'the short post lost its stat tail, which it has room for');
	ok(fits(long), 'the long post kept a tail it had no room for');
}

/* ---- the assembler's icon button ---- */
console.log('\nthe assembler icon builds the same shape of post');
{
	const i = assembler.indexOf('shareXBtn.addEventListener');
	ok(i > -1, 'the assembler share handler is gone');
	const seg = assembler.slice(i, assembler.indexOf('\n  }', i));
	ok(/280 - 24 - tail\.length/.test(seg),
	   'the assembler post is not budgeted against X\'s 23-character URL cost');
	ok(/@skulliance/.test(seg), 'the assembler post does not tag the account');
	ok(/sort\(function \(a, b\) \{ return a\.rank - b\.rank; \}\)/.test(seg),
	   'the assembler no longer picks the BEST placements');
	ok(/slice\(0, 2\)/.test(seg), 'the assembler names more than two placements; that reads as a stat dump');
	ok(/dhcgallery\.php/.test(seg),
	   'the assembler links somewhere other than the public Collection; dhcfighters.php is behind the login '
	 + 'and a shared link that greets a stranger with a sign-in wall is worse than no link');
}

console.log('\nthe button cannot post an empty claim');
{
	const h = strip(assembler);
	ok(/id="sharex"[^>]*disabled/.test(h),
	   'the share button starts enabled, so it can be clicked before the ranks have answered');
	ok(h.indexOf('if (shareXBtn) shareXBtn.disabled = false;') > -1,
	   'the button is never enabled once the ranks arrive');
	ok(/LAST_AXES = null;\n\s*if \(shareXBtn\) shareXBtn\.disabled = true;/.test(h),
	   'a failed rank fetch leaves the button enabled with nothing to say');
	ok(/aria-label="Share this build on X"/.test(h),
	   'the icon-only button has no accessible name');
}

console.log('\nsharing somebody else\'s Fighter is not offered');
{
	const g = strip(gallery);
	ok(/share\.classList\.toggle\('on', mine\)/.test(g),
	   'the Collection share button is shown for Fighters the viewer does not own');
	ok(/share\.href = mine \? shareHref\(f\) : '#'/.test(g),
	   'the share href is built for Fighters the viewer does not own');
	ok(g.indexOf("'dhcgallery.php?fighter=' + f.serial") > -1,
	   'the shared link no longer names the Fighter, so it lands on the front of the Collection');
	/* The card renders on demand the first time -- a full composite and a
	   disk write, which is seconds. X fetches it once, shortly after the
	   composer opens, and caches "no card" for the URL if it is not ready.
	   Warming it when the owner opens their own Fighter puts it on disk long
	   before the crawler asks. */
	ok(/warm\.src = 'dhc-card\.php\?serial=' \+ f\.serial/.test(g),
	   'the card is no longer warmed when its owner opens it, so X can time out on the first fetch and cache nothing');
	ok(/if \(mine && f\.serial\)/.test(g),
	   'the warm fires for Fighters the viewer does not own, rendering cards nobody asked for');
	/* The opener reads it as new URLSearchParams(location.search).get(...),
	   so match THAT, not a `searchParams.` property access that is never
	   written. The first version of this check failed against working code. */
	ok(/URLSearchParams\(window\.location\.search\)\.get\('fighter'\)/.test(g),
	   'nothing opens the named Fighter on arrival, so the deep link does nothing');
}

console.log('\nthe modal shows where it places');
{
	const g = strip(gallery);
	ok(g.indexOf("document.getElementById('dhcg-rank')") > -1, 'the rank strip is not rendered');
	ok(/'rank'    => \$f\['rank'\]/.test(gallery), 'the card payload no longer carries the ranks');
	ok(/\$dhcg_pool = dhcf_rank_pool\(\$conn\);/.test(gallery), 'the rank pool is gone');
	/* Once per page, not once per row. */
	const occurrences = (gallery.match(/dhcf_rank_pool\(\$conn\)/g) || []).length;
	ok(occurrences === 1, 'dhcf_rank_pool() is called ' + occurrences + ' times; it belongs outside the row loop');
}

console.log('\n' + (fail ? fail + ' FAILED\n' : 'all good\n'));
process.exit(fail ? 1 : 0);
