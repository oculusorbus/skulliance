/* nft-panel-harness.js - node, no browser, no network.
 *
 * Runs the My NFTs panel's REAL render() against fixtures and reads the
 * markup it produces.
 *
 * WHY THIS EXISTS SEPARATELY FROM nft-dossier-harness.php. That one proves
 * the numbers are right. This one proves they reach the screen, and the two
 * are not the same question: a source-text check that the file mentions
 * `ds.image` passed with the thumbnail wired to `false`, because the string
 * still appeared further down the same ternary. Only executing it can tell
 * a live render from a dead one.
 *
 * Invoked by nft-dossier-harness.php and reported there as one assertion.
 *
 * Usage: node nft-panel-harness.js
 */
'use strict';
const fs = require('fs');
const vm = require('vm');
const path = require('path');

let fail = 0;
const ok = (c, w) => { if (!c) { fail++; console.log('  FAIL  ' + w); } };

const page = fs.readFileSync(path.join(__dirname, 'my-nfts.php'), 'utf8');

/* The function, from its signature to the assignment that ends it. Returning
   `h` instead of writing it to the DOM is the only change, and it is made
   here rather than in the page so the page keeps doing what it does. */
const a = page.indexOf('  function render(d) {');
const b = page.indexOf('    body.innerHTML = h;', a);
ok(a > -1 && b > a, 'render() is gone from my-nfts.php');
const src = page.slice(a, b) + '    return h;\n  }\n';

const ctx = {
	esc: (s) => String(s == null ? '' : s)
		.replace(/&/g, '&amp;').replace(/</g, '&lt;')
		.replace(/>/g, '&gt;').replace(/"/g, '&quot;'),
	encodeURIComponent,
};
vm.createContext(ctx);
vm.runInContext(src, ctx);

const base = (over) => Object.assign({
	mine: true, cached: true, image: 'x.png', explorer: '',
	nft: { name: 'TRUST #03', collection_name: 'Drop Ship', project_name: 'Ohh Meed',
	       rate: 1, currency: 'DREAD', marketplace_slug: '', username: 'someone' },
	missions: [], missionsDone: 0, gauntlets: [],
	raids: { total: 0, attack: 0, defend: 0, won: 0, lost: 0, fell: 0, now: 0 },
	diamond: null, soldier: null,
}, over || {});

console.log('the realm shows its picture');
{
	const h = ctx.render(base({ soldier: { active: '1', dead: 0, trained: '1',
	                                       realm_name: 'Oculus Realm', theme_id: 7 } }));
	ok(/<img class="thumb"[^>]*src="images\/themes\/7\.jpg"/.test(h),
	   'the realm row renders no theme image: ' + (h.match(/Standing in[^<]*/) || [''])[0]);
	ok(h.indexOf('Oculus Realm') > -1, 'the realm is not named');

	/* A realm with no theme must show NO thumbnail rather than a broken one
	   pointed at images/themes/.jpg. */
	const none = ctx.render(base({ soldier: { active: '1', dead: 0, trained: '0',
	                                          realm_name: 'Nameless', theme_id: null } }));
	ok(none.indexOf('images/themes/') === -1,
	   'a realm with no theme still draws an image tag, which resolves to nothing');
	ok(none.indexOf('Nameless') > -1, 'the realm row vanished along with its picture');
}

console.log('\nthe Diamond Skull shows its picture and its name');
{
	const h = ctx.render(base({ diamond: { name: 'DIAMOND SKULL #112', image: '/staking/i/1.png' } }));
	ok(/<img class="thumb"[^>]*src="\/staking\/i\/1\.png"/.test(h),
	   'the Diamond Skull row renders no image');
	ok(h.indexOf('DIAMOND SKULL #112') > -1, 'the Diamond Skull is not named');
	/* It must never print the bare row id again. */
	ok(!/Part of a Diamond Skull<\/b><span[^>]*>\d+<\/span>/.test(h),
	   'the Diamond Skull row is back to printing its raw id');

	/* Falls back to the asset name, and survives having no picture at all. */
	const noimg = ctx.render(base({ diamond: { asset_name: 'SKULL404', image: '' } }));
	ok(noimg.indexOf('SKULL404') > -1, 'the Diamond Skull has no name to fall back on');
	ok(noimg.indexOf('<img class="thumb"') === -1,
	   'a Diamond Skull with no resolved image still draws an empty img');
}

console.log('\nthe raid record is labelled, and only appears once it has fought');
{
	const h = ctx.render(base({ raids: { total: 14, attack: 9, defend: 5,
	                                     won: 8, lost: 5, fell: 2, now: 1 } }));
	ok(/class="nftd-note"><b>Raids<\/b>/.test(h),
	   'the raid record is not labelled, so it reads as a comment on whichever tile is nearest');
	ok(h.indexOf('8 won') > -1 && h.indexOf('5 lost') > -1, 'the record is missing');
	ok(h.indexOf('9 attacking, 5 defending') > -1, 'the record does not say which side');
	ok(h.indexOf('fell 2 times') > -1, 'the record does not say it fell');
	ok(h.indexOf('RAIDS FOUGHT') > -1 || /Raids? fought/i.test(h), 'the raid tile is missing');
	/* An unresolved raid belongs in Right now, not in the record. */
	ok(h.indexOf('In a raid') > -1, 'a raid in progress is not surfaced');

	const never = ctx.render(base());
	ok(!/Raids? fought/i.test(never),
	   'an NFT that has never raided still shows a raid tile, which is a column of zeroes');
	ok(!/class="nftd-note"/.test(never), 'an NFT that has never raided still shows a record');
	ok(never.indexOf('In a raid') === -1, 'an idle NFT is reported as being in a raid');

	/* Singular, because "1 raids fought" is the kind of thing that gets
	   screenshotted. */
	const one = ctx.render(base({ raids: { total: 1, attack: 1, defend: 0,
	                                       won: 1, lost: 0, fell: 0, now: 0 } }));
	ok(/1<\/b><span>Raid fought/.test(one), 'the tile reads "1 Raids fought"');
	ok(one.indexOf('always attacking') > -1,
	   'a record with only one side still prints "1 attacking, 0 defending"');
}

console.log('\nthe traits are listed, in the order the document wrote them');
{
	const h = ctx.render(base({
		metadataAt: '2026-10-30 04:11:02',
		traits: [{ name: 'Background', value: 'Crypt' },
		         { name: 'Head',       value: 'Bone Blaster' },
		         { name: 'Eyes',       value: 'Neon' }],
		metadata: { name: 'ANCESTOR #41', Background: 'Crypt' },
	}));
	ok(h.indexOf('<p class="nftd-sect">Traits</p>') > -1, 'there is no Traits section');
	ok(/<div class="nftd-traits">/.test(h), 'the traits are not rendered as the trait grid');
	ok(h.indexOf('<span>Head</span><b>Bone Blaster</b>') > -1,
	   'a trait renders without its name beside its value');
	/* ORDER IS MEANING. For a layered collection it is the order the art is
	   built in, and a renderer that sorts or re-keys loses it silently. */
	ok(h.indexOf('Background') < h.indexOf('Head') && h.indexOf('Head') < h.indexOf('Eyes'),
	   'the traits came out in a different order than the document wrote them');
	/* The traits describe the thing; Right now describes its week. */
	ok(h.indexOf('Traits</p>') < h.indexOf('Right now</p>'),
	   'the traits are rendered below Right now');
	ok(h.indexOf('carries no traits') === -1 && h.indexOf('has not been read') === -1,
	   'an NFT WITH traits also gets one of the empty messages');

	/* A value is page content from a stranger's mint, so it is escaped. */
	const xss = ctx.render(base({ metadataAt: 'x',
		traits: [{ name: 'Head', value: '<img src=x onerror=alert(1)>' }] }));
	ok(xss.indexOf('<img src=x') === -1, 'a trait value is written to the panel unescaped');
}

console.log('\nno traits and never looked are different sentences');
{
	/* metadataAt set, nothing in it: the document was read and genuinely has
	   no traits. */
	const empty = ctx.render(base({ metadataAt: '2026-10-30 04:11:02', traits: [] }));
	ok(empty.indexOf('carries no traits') > -1,
	   'an asset whose document has no traits says nothing at all');
	ok(empty.indexOf('has not been read') === -1,
	   'an asset that HAS been read is told nobody has looked at it');

	/* metadataAt null: nobody has crawled it. Saying "no traits" here is a
	   confident wrong answer about an asset that may be covered in them. */
	const never = ctx.render(base({ metadataAt: null, traits: [] }));
	ok(never.indexOf('has not been read') > -1,
	   'an uncrawled asset is reported as having no traits');
	ok(never.indexOf('carries no traits') === -1,
	   'an uncrawled asset gets the "no traits" sentence as well');
	/* The section header is there either way, or the panel looks like it
	   forgot to render something. */
	ok(never.indexOf('Traits</p>') > -1, 'the Traits heading vanishes when there are none');
}

console.log('\nthe raw document is offered only when there is one');
{
	const h = ctx.render(base({ metadataAt: 'x', traits: [],
	                            metadata: { name: 'A', Background: 'Crypt' } }));
	ok(/<details class="nftd-raw">/.test(h), 'the raw metadata view is gone');
	ok(h.indexOf('&quot;Background&quot;') > -1,
	   'the raw document is not printed, or is printed unescaped');

	/* An empty <details> is a dead control. */
	const none = ctx.render(base({ metadataAt: null, traits: [], metadata: null }));
	ok(none.indexOf('nftd-raw') === -1,
	   'the Raw metadata control is offered on an NFT with no stored document');
}

console.log('\na payload from before any of this still renders');
{
	/* The endpoint response is fetched per open, but a stale one must not
	   take the panel down. */
	const old = base();
	delete old.raids; delete old.diamond; delete old.soldier;
	delete old.traits; delete old.metadata; delete old.metadataAt;
	let threw = false, h = '';
	try { h = ctx.render(old); } catch (e) { threw = true; }
	ok(!threw, 'a payload with no raids/diamond/soldier keys throws');
	ok(h.indexOf('TRUST #03') > -1, 'the panel rendered nothing');
}

console.log('\n' + (fail ? fail + ' FAILED\n' : 'all good\n'));
process.exit(fail ? 1 : 0);
