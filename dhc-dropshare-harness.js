/*
 * dhc-dropshare-harness.js — what the trait drop posts to X.
 *
 * Run: node dhc-dropshare-harness.js
 *
 * shareText() is LIFTED OUT OF dhc-dropmodal.php AND RUN. The thing that
 * matters is the exact string a player posts and whether it survives X's
 * character limit, and neither is visible to a source check.
 */
const fs = require('fs');
const vm = require('vm');
const path = require('path');

let fail = 0;
function ok(cond, msg) {
	if (cond) { console.log('  ok    ' + msg); return; }
	fail++; console.log('  FAIL  ' + msg);
}

const modal = fs.readFileSync(path.join(__dirname, 'dhc-dropmodal.php'), 'utf8');
/* From the X_TAIL constant through the end of shareHref(), which is the whole
   self-contained share block. */
const a = modal.indexOf('  var X_TAIL =');
const b = modal.indexOf("  window.__shareText = shareText;");
ok(a > -1 && b > a, 'the share block could not be lifted from dhc-dropmodal.php');
const ctx = { console };
vm.createContext(ctx);
vm.runInContext(modal.slice(a, b), ctx);

/* X counts any URL as 23 characters whatever its length, and the post carries
   one. 280 is the limit. */
const URL_COST = 23;
const fits = t => (t.length + URL_COST) <= 280;

const drop = (o = {}) => Object.assign({
	name: 'Bone Saw', tier: 'epic', category: 'weapon',
	slug: 'bone-saw', worn: 3, rate: 2.4, points: 48, is_new: true,
	source: 'cryptcrawl', from: 'Crypt Crawl',
}, o);

console.log('\nthe post says how the trait was earned');
let t = ctx.shareText(drop());
ok(/Dropped from Crypt Crawl\./.test(t),
   'the post does not say where the trait came from: ' + t);
/* The trait sentence has to survive too -- this is an addition, not a
   replacement. */
ok(/Earned Bone Saw - an epic weapon trait/.test(t), 'the post lost the trait itself: ' + t);
ok(/worn by 3 of the 226 original DHC Fighters/.test(t), 'the post lost the rarity detail: ' + t);

console.log('\nthe two outside games are tagged');
/*
 * Drop Ship and Oculus Lounge are not Skulliance's own, so a post about a
 * trait out of one should reach the people behind it. The trait is still
 * @MMAXI404's art and the tail still says so -- this tags the GAME.
 */
t = ctx.shareText(drop({ source: 'dropship', from: 'Drop Ship', tag: '@ohh_meed' }));
ok(/Dropped from Drop Ship \(@ohh_meed\)\./.test(t), 'a Drop Ship drop does not tag the game: ' + t);
ok(/Art by @MMAXI404/.test(t), 'tagging the game cost the trait artist their credit: ' + t);
t = ctx.shareText(drop({ source: 'oculuslounge', from: 'Oculus Lounge', tag: '@oculuslounge' }));
ok(/Dropped from Oculus Lounge \(@oculuslounge\)\./.test(t),
   'an Oculus Lounge drop does not tag the lounge: ' + t);
/* Everything else stays untagged -- the tail already carries @skulliance,
   and an empty tag must not leave "Realm Guardians ()." behind. */
t = ctx.shareText(drop({ from: 'Realm Guardians', tag: '' }));
ok(/Dropped from Realm Guardians\./.test(t), 'an untagged game reads wrong: ' + t);
ok(t.indexOf('()') === -1, 'an empty tag left empty brackets in the post: ' + t);
t = ctx.shareText(drop({ from: 'Realm Guardians', tag: undefined }));
ok(t.indexOf('undefined') === -1, 'an absent tag printed "undefined": ' + t);
/* A tag with no source has nothing to attach to and must not appear alone. */
t = ctx.shareText(drop({ from: '', tag: '@ohh_meed' }));
ok(t.indexOf('@ohh_meed') === -1, 'a tag printed without the game it belongs to: ' + t);

console.log('\nthe three sources that are not games read as sentences');
/*
 * "Dropped from Invite a friend" is not a sentence. dhcf_earned_from() gives
 * these their own wording server-side; the post just has to not mangle it.
 */
for (const [from, want] of [
	['a 7-day reward streak', 'Dropped from a 7-day reward streak.'],
	['inviting a friend', 'Dropped from inviting a friend.'],
	['a cache in the daily digest', 'Dropped from a cache in the daily digest.'],
	['Realm Guardians', 'Dropped from Realm Guardians.'],
]) {
	ok(ctx.shareText(drop({ from })).indexOf(want) > -1,
	   'the post reads badly for "' + from + '": ' + ctx.shareText(drop({ from })));
}

console.log('\na drop with no known source still posts');
/* Older queued drops, and anything awarded before the source was carried,
   have no 'from' at all. */
t = ctx.shareText(drop({ from: '', source: '' }));
ok(t.indexOf('Dropped from') === -1, 'an empty source still prints "Dropped from": ' + t);
ok(/Earned Bone Saw/.test(t), 'a drop with no source lost its post entirely');
ok(!/ \./.test(t.split('\n')[0]), 'an empty source left a dangling space before a full stop: ' + t);
t = ctx.shareText(drop({ from: undefined, source: undefined }));
ok(t.indexOf('undefined') === -1, 'an absent source printed "undefined": ' + t);

console.log('\nthe provenance survives the trim, and the post still fits');
/*
 * THE WHOLE REASON IT IS APPENDED AFTER THE TRIM. The post is budgeted
 * against X's limit and over-long text is cut from the END -- so building the
 * sentence with the source already on it would make the source the first
 * thing lost, on exactly the posts about the rarest traits, whose names run
 * longest.
 */
const long = ctx.shareText(drop({ name: 'Z'.repeat(400) }));
ok(/Dropped from Crypt Crawl\./.test(long),
   'a long trait name pushed the source out of the post: ' + long);
ok(fits(long), 'the post is ' + (long.length + URL_COST) + ' characters with the URL, over 280');
/* And the ordinary case must be nowhere near the limit. */
ok(fits(ctx.shareText(drop())), 'an ordinary post does not fit in 280 characters');
/* The longest real source phrase, with a long name, still fits. */
const worst = ctx.shareText(drop({ name: 'Z'.repeat(400), from: 'a cache in the daily digest' }));
/* And the longest real clause of all: the longest tagged game name. */
const tagged = ctx.shareText(drop({ name: 'Z'.repeat(400), from: 'Oculus Lounge', tag: '@oculuslounge' }));
ok(fits(tagged), 'a tagged post is ' + (tagged.length + URL_COST) + ' characters, over 280');
ok(/Dropped from Oculus Lounge \(@oculuslounge\)\./.test(tagged),
   'a long trait name trimmed the tag off a tagged post: ' + tagged);
ok(fits(worst), 'the worst case is ' + (worst.length + URL_COST) + ' characters, over 280');
ok(/Dropped from a cache in the daily digest\./.test(worst),
   'the longest source phrase was trimmed away: ' + worst);

console.log('\nthe tail that makes the post worth posting is intact');
ok(t.indexOf('@skulliance') > -1, 'the post stopped tagging the account');
ok(/Art by @MMAXI404/.test(ctx.shareText(drop())), 'the post stopped crediting the artist');
/* Three blocks: the sentence, the invite, the art credit. X_TAIL carries the
   break between the last two, so this is 3 and not 2. */
ok(ctx.shareText(drop()).split('\n\n').length === 3,
   'the post lost a paragraph break: ' + JSON.stringify(ctx.shareText(drop())));

console.log('\nthe link still points at the trait');
const href = ctx.shareHref(drop());
ok(href.indexOf('dhcgallery.php%3Ftrait%3Dweapon%3Abone-saw') > -1,
   'the share link no longer names the trait, so the post has no art: ' + href);
ok(decodeURIComponent(href).indexOf('Dropped from Crypt Crawl.') > -1,
   'the source did not make it into the actual share url');

console.log(fail ? '\n' + fail + ' check(s) FAILED\n' : '\nall good\n');
process.exit(fail ? 1 : 0);
