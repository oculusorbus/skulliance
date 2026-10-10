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
/* Anchored on the NAME, not the full signature. These were pinned to
   '  function shareText(f) {' and adding a second parameter silently broke
   the lift -- the slice came back empty, shareText was never defined, and
   the harness died on the first call instead of reporting anything useful. */
const a = gallery.indexOf('  function shareText(');
const b = gallery.indexOf('  function shareHref(');
ok(a > -1 && b > a, 'shareText() is gone from dhc-fighter-modal.php');
/*
 * X_HANDLE IS READ OUT OF THE FILE, not declared here. It was stubbed as
 * '@skulliance' while the real one had become an 84-character call to
 * action -- so every budget assertion below was measuring a tail 73
 * characters shorter than the one that actually ships. A fixture that is
 * kinder than the real thing tests nothing.
 */
const handleMatch = gallery.match(/var X_HANDLE = '([^']+)';/);
if (!handleMatch) { console.log('  FAIL  X_HANDLE is gone from dhc-fighter-modal.php'); fail++; }
/* The brand account's tail, read out of the file for the same reason. */
const showMatch = gallery.match(/var X_SHOWCASE = '([^']+)';/);
if (!showMatch) { console.log('  FAIL  X_SHOWCASE is gone from dhc-fighter-modal.php'); fail++; }
/* The capture is SOURCE text, so an escape in the literal arrives here as
   two characters. The browser sees one. Un-escaping matters for the length
   budget as well as for reading it: counting "\n" as 2 overstates the tail
   and would let a genuinely over-length post pass. */
const unescapeJs = (t) => t.replace(/\\n/g, '\n').replace(/\\'/g, "'").replace(/\\\\/g, '\\');
const ctx = {
	AXES: [['rarest','Rarest'],['might','Deadliest'],['tough','Toughest'],['power','Hardest hitting']],
	X_HANDLE: handleMatch ? unescapeJs(handleMatch[1]) : '@skulliance',
	X_SHOWCASE: showMatch ? unescapeJs(showMatch[1]) : 'Arena.\n\nArt by @MMAXI404',
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

console.log('\nthe showcase post is the platform talking about a player');
{
	const f = fighter({ owner: 'nothooley' });
	const mine = ctx.shareText(f);
	const show = ctx.shareText(f, true);

	ok(mine !== show, 'the showcase voice is identical to the player voice');

	/* THE BUILDER IS THE POINT OF THE POST, so they are named and they lead. */
	ok(/^nothooley assembled /.test(show),
	   'the showcase post does not open by crediting the builder: ' + JSON.stringify(show.slice(0, 60)));
	ok(mine.indexOf('nothooley') === -1,
	   'the PLAYER\'s own post now names them, which it should not -- they know who they are');

	/* PLAIN TEXT, NOT AN @. f.owner is a Discord username and the platform
	   stores no X handle for anyone, so an @ would tag whichever stranger
	   holds that name on X. */
	ok(show.indexOf('@nothooley') === -1,
	   'the showcase post @-tags a Discord username as if it were an X handle');

	/* THE ACCOUNT MUST NOT TAG ITSELF. That is the one thing the player tail
	   does that the brand tail cannot. */
	ok(mine.indexOf('@skulliance') > -1, 'the player post stopped tagging the account');
	ok(show.indexOf('@skulliance') === -1,
	   'the showcase post tags @skulliance, which is the account tagging itself');

	/* It still has to invite, and it still has to credit the art. */
	ok(/Arena/.test(show), 'the showcase post no longer invites anyone to play: ' + show);
	ok(/Art by @MMAXI404/.test(show), 'the showcase post does not credit the artist: ' + show);
	ok(show.split('\n\n').length === 3,
	   'the showcase post is ' + show.split('\n\n').length + ' paragraphs, expected 3');

	/* The stats are the other half of what was asked for. */
	ok(/#4 deadliest/.test(show), 'the showcase post lost its best placement: ' + show);
	ok(/118 power/.test(show) && /742 health/.test(show),
	   'the showcase post lost the stats it exists to show off: ' + show);
	/* "X assembled Y ... assembled from 9 traits" says it twice. */
	ok(show.split('assembled').length - 1 === 1,
	   'the showcase post says "assembled" twice: ' + show);

	ok(fits(show), 'the showcase post is ' + (show.length + URL_COST) + ' characters with the URL, over ' + LIMIT);
	console.log('  ' + JSON.stringify(show));

	/* A showcase of a Fighter whose owner did not come through must not
	   print "undefined assembled ...". */
	const anon = ctx.shareText(fighter({ owner: '' }), true);
	ok(anon.indexOf('undefined') === -1 && /^DHC2F528 - /.test(anon),
	   'a missing owner breaks the showcase post: ' + JSON.stringify(anon.slice(0, 50)));

	/* The budget has to hold with the longest plausible name AND a builder. */
	const long = ctx.shareText(fighter({
		owner: 'a'.repeat(32), name: 'X'.repeat(48), rankOf: 987654,
		rank: { rarest: 123456, might: 234567, tough: 345678, power: 456789 },
		pow: 99999, hp: 99999, parts: new Array(99).fill(0),
	}), true);
	ok(fits(long), 'the longest showcase post overflows: ' + (long.length + URL_COST) + ' characters');
	ok(/Art by @MMAXI404/.test(long), 'the longest showcase post lost the artist credit');
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

console.log('\nsharing somebody else\'s Fighter is offered to ONE account');
{
	const g = strip(gallery);
	/*
	 * THE RULE CHANGED, THE PROTECTION DID NOT.
	 *
	 * These three used to assert a bare `mine`. User 1 -- the account that
	 * posts the Collection to X -- may now share and download any Fighter,
	 * so `mine` became `canUse`. The assertions are rewritten rather than
	 * deleted, because what they were really guarding is that an ORDINARY
	 * viewer still cannot, and a deleted check guards nothing.
	 *
	 * canUse is pinned to its exact definition below for the same reason:
	 * `mine || ME > 0` would also satisfy a loose "canUse is used here"
	 * check while opening the button to every signed-in player.
	 */
	ok(/var canUse = mine \|\| ME === 1;/.test(g),
	   'canUse is not "mine or user 1" -- the exemption is wider or narrower than one account');
	ok(/var staff\s+= ME === 1 && !mine;/.test(g),
	   'staff is not "user 1 on a Fighter they do not own"');
	ok(/share\.classList\.toggle\('on', canUse\)/.test(g),
	   'the share button is not gated on canUse');
	ok(/share\.href = canUse \? shareHref\(f, staff\) : '#'/.test(g),
	   'the share href is not gated on canUse, or does not pass the showcase flag');
	ok(/get\.classList\.toggle\('on', canUse\)/.test(g),
	   'the download button is not gated on canUse');
	ok(/get\.href = canUse \? 'dhc-download\.php\?serial=' \+ f\.serial : '#'/.test(g),
	   'the download href is not gated on canUse');
	ok(g.indexOf("'dhcgallery.php?fighter=' + f.serial") > -1,
	   'the shared link no longer names the Fighter, so it lands on the front of the Collection');
	/* The card renders on demand the first time -- a full composite and a
	   disk write, which is seconds. X fetches it once, shortly after the
	   composer opens, and caches "no card" for the URL if it is not ready.
	   Warming it when the composer opens puts it on disk long before the
	   crawler asks -- and user 1 opening somebody else's Fighter is now the
	   likeliest share on the platform, so the warm follows canUse too. */
	ok(/warm\.src = 'dhc-card\.php\?serial=' \+ f\.serial/.test(g),
	   'the card is no longer warmed when its owner opens it, so X can time out on the first fetch and cache nothing');
	ok(/if \(canUse && f\.serial\)/.test(g),
	   'the warm does not follow canUse, so a staff share races the crawler on a cold card');

	/*
	 * AND THE SERVER STILL DECIDES. The two lines above only draw buttons.
	 * dhc-download.php is where a stranger typing the URL is refused, so
	 * the exemption there has to be exactly one account and has to keep the
	 * disassembled check.
	 */
	const dl = fs.readFileSync(path.join(__dirname, 'dhc-download.php'), 'utf8');
	ok(/\$own = \(\$me === 1\) \? '' : sprintf\(' AND user_id = %d', \$me\);/.test(dl),
	   'dhc-download.php does not drop the ownership clause for exactly user 1');
	ok(/WHERE serial = %d%s AND disassembled_at IS NULL/.test(dl),
	   'the download no longer refuses a disassembled Fighter');
	/* The ?build= branch is deliberately NOT exempt: it exists to stop the
	   trait art being walked out a layer at a time, which does not change
	   for user 1. */
	ok(/dhcd_fail\(403, 'That build uses a trait you do not own\.'\)/.test(dl),
	   'the unsaved-build gate was loosened along with the serial one');
	/* In the PAGE, not the panel: the opener is how this page chooses which
	   Fighter to show, which is the one piece that did not move.
	   It reads as new URLSearchParams(location.search).get(...), so match
	   THAT, not a `searchParams.` property access that is never written --
	   the first version of this check failed against working code. */
	ok(/URLSearchParams\(window\.location\.search\)\.get\('fighter'\)/.test(strip(page)),
	   'nothing opens the named Fighter on arrival, so the deep link does nothing');
}

/* ---- one panel, two callers ---- */
console.log('\nthe Arena record reaches the Collection');
{
	/* dhc_arena_fighters keeps wins/losses as career totals that the schema
	   marks public, and nothing outside the Arena's own Crew picker showed
	   them -- the Collection described everything about a Fighter except
	   whether it wins. */
	const ajax = fs.readFileSync(path.join(__dirname, 'ajax', 'dhc-fighter.php'), 'utf8');

	/* BOTH feed the SAME panel. A field present in one and missing from the
	   other is a modal that shows the record only when you reached it one
	   particular way. */
	for (const [label, src] of [['dhcgallery.php', page], ['ajax/dhc-fighter.php', ajax]]) {
		ok(/LEFT JOIN dhc_arena_fighters af ON af\.fighter_id = f\.id/.test(src),
		   label + ' does not join the Arena record');
		/* LEFT, not INNER: a Fighter never fielded has no row there at all and
		   must still appear in its owner's Collection. */
		ok(!/INNER JOIN dhc_arena_fighters/.test(src),
		   label + ' inner-joins the record, so an unfought Fighter vanishes');
		ok(/COALESCE\(af\.wins, 0\)/.test(src) && /COALESCE\(af\.losses, 0\)/.test(src),
		   label + ' can emit null instead of 0 for a Fighter that never fought');
		ok(/'aw'\s*=>/.test(src) && /'al'\s*=>/.test(src),
		   label + ' does not put the record in the payload');
	}

	ok(/f\.aw \+ 'W \/ ' \+ f\.al \+ 'L'/.test(gallery),
	   'the modal does not render the record as W / L, matching the Arena picker');
	ok(gallery.indexOf('Arena record') > -1,
	   'the record tile has no label');
	/* 0W / 0L invites the reader to work out that it means untested. */
	ok(gallery.indexOf('Not fought yet') > -1,
	   'a Fighter that has never been fielded reads as 0W / 0L rather than in words');
}

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

console.log('\nthe panel can be closed on a phone\n');
{
	const m = strip(gallery);
	/*
	 * THE CLOSE CONTROL WAS ANCHORED TO THE SCREEN, NOT THE PANEL. Nothing
	 * between it and #dhcg-veil was positioned, so `top:12px` meant twelve
	 * pixels from the top of the VIEWPORT -- and the veil's padding reserves
	 * env(safe-area-inset-top) while an absolutely positioned child ignores
	 * padding. On a notched phone in the installed app that put the only
	 * close control under the status bar. The reported symptom was having to
	 * tap the very edge of the screen, which is the veil's click-to-close:
	 * all that was left.
	 *
	 * Measured after: 390px -> fixed, 65x44, in the viewport, unmoved when
	 * the panel is scrolled to the bottom, and elementFromPoint at its
	 * centre returns the button. 1200px -> absolute, anchored to
	 * #dhcg-panel, in the panel's own corner.
	 */
	ok(/#dhcg-panel\{[^}]*position:relative/.test(m),
	   'the panel is not positioned, so the close control anchors to the viewport again');
	ok(/@media \(max-width:760px\)\{[\s\S]{0,400}?#dhcg-close\{position:fixed/.test(m),
	   'the close control scrolls away with the panel on a phone');
	ok(/top:calc\(env\(safe-area-inset-top,0px\) \+ 10px\)/.test(m),
	   'the close control ignores the safe-area inset, so it sits under the notch');
	ok(/min-height:44px/.test(m),
	   'the close control is below the minimum touch target on a phone');
	/* The veil tap must survive as a second way out, not the only one. */
	ok(/veil\.addEventListener\('click'[\s\S]{0,120}?close\(\)/.test(m),
	   'tapping outside the panel no longer closes it');
	ok(/e\.key === 'Escape'/.test(m), 'Escape no longer closes the panel');
}

console.log('\none typeface across every DHC surface\n');
{
	/*
	 * Moving between the DHC tabs changed the body text and the headings.
	 * Measured in headless Chrome against the real CSS: the Fighter panel,
	 * which is position:fixed and sits OUTSIDE .dhcg-wrap on the Collection,
	 * rendered Arial/Arial/Arial for body, title and close button. With the
	 * shared block it is JetBrains Mono / Archivo Black / JetBrains Mono,
	 * and content outside DHC is still Arial.
	 */
	const type = fs.readFileSync(path.join(__dirname, 'dhc-type.php'), 'utf8');
	ok(/function dhc_type_styles\(/.test(type), 'dhc-type.php no longer exposes the styles');
	/* Inert on include: every DHC page requires its libraries before
	   header.php has emitted anything, and a partial that echoed on include
	   would print in front of the document. */
	ok(!/^\s*<style>/m.test(type.slice(0, type.indexOf('function dhc_type_styles'))),
	   'dhc-type.php emits markup on include, which lands in front of the document');
	ok(/if \(defined\('DHC_TYPE_RENDERED'\)\) return;/.test(type),
	   'the type block can be emitted more than once');

	/* Every DHC wrapper has to be in the selector list, or that surface
	   silently falls back to the platform's Arial. The fixed-position panel
	   and the travelling nav strip are the two that are not page wrappers
	   at all, and they are exactly the two that were wrong. */
	/* Check the BODY-FONT rule specifically, not the file: every one of
	   these also appears in the headings and controls selectors, so a loose
	   indexOf stayed green with the surface removed from the rule that
	   actually sets the family. Two mutations survived that way. */
	const bodyRule = type.slice(type.indexOf('.dhcf-wrap, .dhcg-wrap'));
	const bodySel  = bodyRule.slice(0, bodyRule.indexOf('{'));
	for (const sel of ['.dhcf-wrap', '.dhcg-wrap', '.arena-wrap', '.shell', '.dhcnav', '#dhcg-veil']) {
		ok(new RegExp('(^|,\\s*)' + sel.replace('.', '\\.') + '\\s*(,|$)').test(bodySel.trim()),
		   'dhc-type.php does not give ' + sel + ' the body face, so that surface renders in the site font');
	}
	ok(/#dhcg-veil h1, #dhcg-veil h2, #dhcg-veil h3/.test(type),
	   'the panel headings are not covered, so a Fighter name renders in the body face');

	/* And the three entry points call it. */
	for (const f of ['dhc-assembler.php', 'dhcgallery.php', 'dhcarena.php']) {
		const body = fs.readFileSync(path.join(__dirname, f), 'utf8');
		ok(body.indexOf('dhc_type_styles()') > -1, f + ' never emits the shared type block');
	}

	/* THE LEAK THAT HID THE GAP. dhc-assembler.php declared h1,h2,h3
	   unscoped, so including it restyled the host page's headings
	   document-wide -- and covered the panel's missing rule on the one page
	   that includes it, which is why this only looked broken on the
	   Collection. */
	const asmSrc = fs.readFileSync(path.join(__dirname, 'dhc-assembler.php'), 'utf8');
	ok(!/^h1,h2,h3,\.btn,\.tab\{/m.test(strip(asmSrc)),
	   'the assembler styles h1,h2,h3 unscoped again, restyling every heading on whatever page includes it');
}

console.log('\nevery DHC intro credits the artist with a link');
{
	/* A credit that is a link on one page and plain text on the next is a
	   credit nobody follows. One definition, used by all of them, so they
	   cannot drift to different accounts -- dhcgame.php had its own copy of
	   the URL before this. */
	const cfg = fs.readFileSync(path.join(__dirname, 'dhcfighters-config.php'), 'utf8');
	ok(/define\('DHCF_ARTIST_X', 'https:\/\/x\.com\/MMAXI404'\)/.test(cfg),
	   'the artist link is gone or points somewhere else');
	ok(/function dhcf_artist_link\(/.test(cfg), 'dhcf_artist_link() is gone');

	for (const f of ['dhcgallery.php', 'dhcfighters.php', 'dhcsandbox.php']) {
		const body = fs.readFileSync(path.join(__dirname, f), 'utf8');
		const vis  = strip(body);
		/* The NAME must not appear unlinked in rendered copy: that is the
		   state this fixes. Comments are stripped first -- several explain
		   the change and mention him by name. */
		ok(!/art by Maxingo/.test(vis),
		   f + ' still credits Maxingo as plain text; the credit should link to his account');
		ok(body.indexOf('dhcf_artist_link()') > -1,
		   f + ' does not use the shared artist link');
	}
	/* THE RIGHTS NOTICE HAS TO POINT SOMEWHERE. "The genuine, ownable
	   Fighters are from the official NFT collection" without a link is
	   half a sentence -- it names a destination and withholds it. */
	const fighters = fs.readFileSync(path.join(__dirname, 'dhcfighters.php'), 'utf8');
	ok(/dhcf_collection_link\('official NFT collection'\)/.test(fighters),
	   'the rights notice names the minted collection without linking to it');
	ok(/define\('DHCF_COLLECTION_URL'/.test(cfg), 'the collection URL is no longer defined once');
	ok(/function dhcf_collection_link\(/.test(cfg), 'dhcf_collection_link() is gone');
	/* And no page keeps its own copy of the policy id. */
	for (const f of ['dhcfighters.php', 'dhcgallery.php']) {
		const body = fs.readFileSync(path.join(__dirname, f), 'utf8');
		ok(body.indexOf('wayup.io/collection/b31a34ca2b08') === -1,
		   f + ' hardcodes the collection URL again instead of using the shared one');
	}

	/* The marketing page already linked him -- it must read the shared
	   constant rather than keep its own copy of the URL. */
	const game = fs.readFileSync(path.join(__dirname, 'dhcgame.php'), 'utf8');
	ok(/\$artist_x\s*=\s*defined\('DHCF_ARTIST_X'\)/.test(game),
	   'dhcgame.php hardcodes the artist URL again instead of reading the shared one');
	ok(/\$collection_url\s*=\s*defined\('DHCF_COLLECTION_URL'\)/.test(game),
	   'dhcgame.php hardcodes the collection URL again instead of reading the shared one');

	/* And the docs. */
	for (const f of ['skullpaper/games-dhc-fighters.md', 'skullpaper/games-dhc-arena.md']) {
		const md = fs.readFileSync(path.join(__dirname, f), 'utf8');
		ok(md.indexOf('(https://x.com/MMAXI404)') > -1, f + ' does not link the artist');
	}
}

console.log('\nthe Collection says who owns the art');
{
	/* "nobody owns the artwork" was both untrue and the opposite of what the
	   notice is for: assembling a Fighter here gives you no claim on the art
	   BECAUSE somebody already holds it. Checked on the rendered copy, not
	   the comment that explains the change. */
	const visible = strip(page);
	ok(visible.indexOf('nobody owns') === -1,
	   'the Collection says nobody owns the artwork; Maxingo does');
	/* The name is emitted by dhcf_artist_link() now, so it is not a literal
	   in the source any more -- assert on the call plus the clause it
	   introduces, which is what actually renders. */
	ok(/dhcf_artist_link\(\); \?> owns the artwork/.test(page),
	   'the Collection no longer says who owns the artwork');
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
