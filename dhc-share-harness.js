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

/* The share text moved into the shared modal when the assembler's own
   button was removed -- that one could never show the Fighter's art, which
   is the whole value of the post. One copy now, used by the Collection and
   by the panel the assembler opens on save. */
const gallery   = fs.readFileSync(path.join(__dirname, 'dhc-fighter-modal.php'), 'utf8');
const assembler = fs.readFileSync(path.join(__dirname, 'dhc-assembler.php'), 'utf8');
/* The Collection PAGE, as distinct from the panel it shares with the
   assembler: the deep-link opener and the card payload stayed here when the
   panel moved out. */
const page      = fs.readFileSync(path.join(__dirname, 'dhcgallery.php'), 'utf8');

/* X's own arithmetic: 280 total, any URL counts 23 (+1 for the space). */
const LIMIT = 280;
const URL_COST = 24;
const fits = (text) => text.length + URL_COST <= LIMIT;

/* ---- the Collection modal's shareText(), lifted out and run ---- */
const a = gallery.indexOf('  function shareText(f) {');
const b = gallery.indexOf('  function shareHref(f) {');
ok(a > -1 && b > a, 'shareText() is gone from dhcgallery.php');
/*
 * X_HANDLE IS READ OUT OF THE FILE, not declared here. It was stubbed as
 * '@skulliance' while the real one had become an 84-character call to
 * action -- so every budget assertion below was measuring a tail 73
 * characters shorter than the one that actually ships. A fixture that is
 * kinder than the real thing tests nothing.
 */
const handleMatch = gallery.match(/var X_HANDLE = '([^']+)';/);
if (!handleMatch) { console.log('  FAIL  X_HANDLE is gone from dhc-fighter-modal.php'); fail++; }
/* The capture is SOURCE text, so an escape in the literal arrives here as
   two characters. The browser sees one. Un-escaping matters for the length
   budget as well as for reading it: counting "\n" as 2 overstates the tail
   and would let a genuinely over-length post pass. */
const unescapeJs = (t) => t.replace(/\\n/g, '\n').replace(/\\'/g, "'").replace(/\\\\/g, '\\');
const ctx = {
	AXES: [['rarest','Rarest'],['might','Deadliest'],['tough','Toughest'],['power','Hardest hitting']],
	X_HANDLE: handleMatch ? unescapeJs(handleMatch[1]) : '@skulliance',
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
	/* And it has to INVITE, not merely tag: a bare handle says who made the
	   thing and nothing about why a stranger should join. */
	ok(/Join @skulliance/.test(t) && /Arena/.test(t),
	   'the post no longer invites anyone to play: ' + JSON.stringify(t.slice(-60)));
	/* None of this exists without the art, and a Fighter going out
	   uncredited is the wrong default. */
	ok(/Art by @MMAXI404/.test(t),
	   'the post no longer credits the artist: ' + JSON.stringify(t.slice(-80)));
	/* Its own paragraph, not a second line of the pitch -- X collapses
	   nothing, so a single newline reads as one block of four lines. */
	ok(/Arena!\n\nArt by @MMAXI404/.test(t),
	   'the credit is not separated from the invitation by a blank line');
	/* Three paragraphs: the Fighter, the invitation, the credit. */
	ok(t.split('\n\n').length === 3,
	   'the post is ' + t.split('\n\n').length + ' paragraphs, expected 3');
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
	/* The ellipsis sits where the body was cut, immediately before the tail
	   -- matched relative to the tail rather than to a literal handle, which
	   is what broke this check when the handle became an invitation. */
	ok(huge.indexOf('…\n\n') > -1 && huge.endsWith(ctx.X_HANDLE),
	   'an oversized post was cut without saying it was cut, or lost its tail: '
	 + JSON.stringify(huge.slice(-30)));
}

console.log('\nthe stat tail is dropped rather than overflowing');
{
	const short = ctx.shareText(fighter());
	const long  = ctx.shareText(fighter({ name: 'Y'.repeat(48) }));
	ok(/power/.test(short), 'the short post lost its stat tail, which it has room for');
	ok(fits(long), 'the long post kept a tail it had no room for');
}

/* ---- the assembler has no share button any more ---- */
console.log('\nthe assembler does not offer a share it cannot illustrate');
{
	/* x.com/intent/post shows the image the SHARED URL declares, and an
	   unsaved build on a login-gated page has no URL and no card. The button
	   there could only ever post text. Saving opens the Collection's own
	   panel instead, which has the Fighter, the card and the ranks. */
	ok(assembler.indexOf('id="sharex"') === -1,
	   'the assembler share button is back; it cannot put the Fighter in the post');
	ok(assembler.indexOf('LAST_AXES') === -1, 'dead share state left behind in the assembler');
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
	/* In the PAGE, not the panel: the opener is how this page chooses which
	   Fighter to show, which is the one piece that did not move.
	   It reads as new URLSearchParams(location.search).get(...), so match
	   THAT, not a `searchParams.` property access that is never written --
	   the first version of this check failed against working code. */
	ok(/URLSearchParams\(window\.location\.search\)\.get\('fighter'\)/.test(strip(page)),
	   'nothing opens the named Fighter on arrival, so the deep link does nothing');
}

/* ---- one panel, two callers ---- */
console.log('\nthe panel is shared, not copied');
{
	/* RAW for anything in PHP, stripped only for JS and markup: strip()
	   removes <?php ?> blocks wholesale, and the include lines, the
	   $dhcm_deeplink flags and the DHCM_RENDERED guard all live in one.
	   Checking the stripped text reported three working things as missing. */
	const m = strip(gallery), mRaw = gallery;
	const pg = strip(page),   pgRaw = page;
	const asm = fs.readFileSync(path.join(__dirname, 'dhcfighters.php'), 'utf8');
	/* Three copies of a detail panel is how this platform has shipped a
	   redesigned-but-invisible panel three times. */
	ok(pgRaw.indexOf("include __DIR__ . '/dhc-fighter-modal.php'") > -1,
	   'the Collection no longer includes the shared panel');
	ok(asm.indexOf("include __DIR__ . '/dhc-fighter-modal.php'") > -1,
	   'the assembler no longer includes the shared panel');
	ok(pgRaw.indexOf('<div id="dhcg-veil"') === -1,
	   'the Collection has its own copy of the panel markup again');
	ok(m.indexOf('window.DHC_MODAL = {') > -1, 'the panel exposes no way to open it');

	/*
	 * EVERY RULE FOR THE PANEL LIVES WITH THE PANEL.
	 *
	 * #dhcg-close was left behind in dhcgallery.php when this was pulled
	 * out -- the extraction started at the #dhcg-veil rule and that one sat
	 * above it. The Collection kept its styling and the assembler rendered a
	 * bare browser <button>: white, rounded, system font, in the middle of a
	 * dark panel. Nothing warns about a rule only one of two callers has, and
	 * neither page is "wrong" on its own.
	 *
	 * So: no stylesheet outside the partial may style anything named dhcg-.
	 */
	/* The names the PANEL emits, read from its own markup -- not every
	   dhcg- prefix: the Collection page has .dhcg-wrap, .dhcg-head and
	   .dhcg-grid of its own, which share the prefix and are nothing to do
	   with this. Matching on the prefix flagged four of those. */
	const panelMarkup = gallery.slice(gallery.indexOf('</style>'));
	const owned = new Set();
	for (const mm of panelMarkup.matchAll(/id="(dhcg-[a-z-]+)"/g)) owned.add('#' + mm[1]);
	for (const mm of panelMarkup.matchAll(/class="(dhcg-[a-z-]+)"/g)) owned.add('.' + mm[1]);

	const strays = [];
	for (const file of ['dhcgallery.php', 'dhcfighters.php', 'dhc-assembler.php']) {
		const body = fs.readFileSync(path.join(__dirname, file), 'utf8');
		for (const block of body.match(/<style[^>]*>[\s\S]*?<\/style>/g) || []) {
			/* Selector position only -- a rule BODY mentioning the name (a
			   url(), a content string) is not styling it. */
			for (const sel of block.match(/^[^@{}\n][^{}\n]*\{/gm) || []) {
				for (const name of owned) {
					/* Word-boundaried: #dhcg-get must not match #dhcg-getter. */
					if (new RegExp(name.replace('.', '\\.') + '(?![a-z-])').test(sel)) {
						strays.push(file + ': ' + sel.trim().replace(/\{$/, ''));
						break;
					}
				}
			}
		}
	}
	ok(owned.size >= 10, 'only ' + owned.size + ' panel selectors found; the scan is looking at the wrong text');
	ok(strays.length === 0,
	   'the panel is styled from outside the partial, so one caller will render it wrong:\n    '
	 + strays.join('\n    '));
	ok(mRaw.indexOf("defined('DHCM_RENDERED')") > -1,
	   'the panel can be emitted twice on one page, which would duplicate every id in it');

	/* ONLY THE COLLECTION OWNS THE ?fighter= URL. On the assembler the panel
	   is a review of what was just saved; rewriting the address to a
	   different page would send a refresh somewhere else entirely. */
	ok(/\$dhcm_deeplink = true;/.test(pgRaw), 'the Collection stopped claiming the ?fighter= URL');
	ok(/\$dhcm_deeplink = false;/.test(asm), 'the assembler rewrites the address to dhcgallery.php');
	ok(m.indexOf('if (DEEPLINK && f.serial)') > -1,
	   'the panel rewrites the URL unconditionally, which is wrong on every page but one');

	/* Saving opens it, and closing it is what reloads -- a timer the player
	   cannot see is a worse place for the page change. */
	ok(asm.indexOf('window.DHC_MODAL.onClose = after;') > -1,
	   'closing the panel after a save no longer refreshes the roster behind it');
	ok(asm.indexOf("fetch('ajax/dhc-fighter.php?serial=") > -1,
	   'the assembler no longer fetches the saved Fighter, so it has nothing to show');
	/* A failed fetch must not strand someone on a saved Fighter with no
	   panel and no reload. */
	ok(/\.catch\(function \(\) \{ setTimeout\(after, 900\); \}\);/.test(asm),
	   'a failed panel fetch leaves the page as it was, with the save invisible');
	ok(/if \(!d\.serial \|\| !window\.DHC_MODAL\) \{ setTimeout\(after, 900\); return; \}/.test(asm),
	   'nothing falls back when the panel or the serial is missing');

	/* AN EDIT GETS THE PANEL TOO. Save and update share one success path --
	   editId only picks the endpoint, the wording and where `after` goes
	   (dhcfighters.php, to shed ?edit=, rather than a reload). Reviewing
	   what an edit turned the Fighter into is the same moment as reviewing
	   a new one, so this asserts the two have not been branched apart. */
	const okBlock = asm.slice(asm.indexOf('var after = function ()'),
	                          asm.indexOf('.catch(function () { setTimeout(after, 900); });'));
	ok(okBlock.length > 0, 'the save success path moved');
	ok(!/editId/.test(okBlock.slice(okBlock.indexOf('if (!d.serial'))),
	   'the panel is now gated on editId, so an edit no longer gets it');
	ok(/if \(editId\) location\.href = 'dhcfighters\.php';/.test(okBlock),
	   'an edit no longer sheds ?edit= when the panel closes, so a reload reopens the editor');
}

console.log('\nthe builder link counts their Fighters');
{
	const m = strip(gallery);
	ok(/see their ' \+ f\.ownerN\.toLocaleString\(\) \+ ' Fighter'/.test(m),
	   'the builder link no longer names how many Fighters they have');
	ok(/f\.ownerN === 1 \? '' : 's'/.test(m), 'the count is not pluralised');
	/* Never "see their 0 Fighters": a payload without the number falls back
	   to the bare wording. */
	ok(/f\.ownerN > 0/.test(m), 'a missing count would render as "see their 0 Fighters"');

	/* THE COUNT IS EVERY FIGHTER THEY HAVE, not the ones on screen. Tallying
	   the page's own rows would say "see their 3 Fighters" to somebody
	   looking at a tier filter when the builder has thirty. */
	ok(/SELECT user_id, COUNT\(\*\) AS n FROM dhc_fighters\s*\n?\s*WHERE disassembled_at IS NULL GROUP BY user_id/.test(page),
	   'the Collection no longer counts each builder\'s Fighters with its own query');
	const ep = fs.readFileSync(path.join(__dirname, 'ajax/dhc-fighter.php'), 'utf8');
	ok(/'ownerN'  => \$ownerN,/.test(ep), 'the single-Fighter endpoint does not send the count');
	ok(/WHERE user_id = " \. \(int\)\$row\['user_id'\] \. " AND disassembled_at IS NULL/.test(ep),
	   'the endpoint counts disassembled Fighters, or does not cast the id');
}

console.log('\nthe modal shows where it places');
{
	const g = strip(gallery);
	ok(g.indexOf("document.getElementById('dhcg-rank')") > -1, 'the rank strip is not rendered');
	ok(/'rank'    => \$f\['rank'\]/.test(page), 'the card payload no longer carries the ranks');
	ok(/\$dhcg_pool = dhcf_rank_pool\(\$conn\);/.test(page), 'the rank pool is gone');
	/* Once per page, not once per row. */
	const occurrences = (page.match(/dhcf_rank_pool\(\$conn\)/g) || []).length;
	ok(occurrences === 1, 'dhcf_rank_pool() is called ' + occurrences + ' times; it belongs outside the row loop');
}

console.log('\n' + (fail ? fail + ' FAILED\n' : 'all good\n'));
process.exit(fail ? 1 : 0);
