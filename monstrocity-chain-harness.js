/*
 * monstrocity-chain-harness.js — where an NFT's picture is looked for.
 *
 * Run: node monstrocity-chain-harness.js
 *
 * monstrocityImageChain() is LIFTED OUT OF monstrocity.php AND EXECUTED, not
 * pattern-matched. What it returns is an ordered list of URLs, and the bug
 * this exists for -- a gateway prepended to an absolute URL -- leaves every
 * source string in the function intact while building
 * "https://gateway/ipfs/https://omenati.com/...". Only running it shows that.
 */
const fs = require('fs');
const path = require('path');

let fail = 0;
function ok(cond, msg) {
	if (cond) { console.log('  ok    ' + msg); return; }
	fail++; console.log('  FAIL  ' + msg);
}

const src = fs.readFileSync(path.join(__dirname, 'monstrocity.php'), 'utf8');
const at = src.indexOf('function monstrocityImageChain(config) {');
const end = src.indexOf('\n    }', at);
ok(at !== -1 && end !== -1, 'monstrocityImageChain() could not be lifted from the page');

/* The real gateway list comes from lib/ipfs-gateways.php through PHP, so the
   harness supplies a stand-in -- but a DEAD one is included on purpose,
   because dropping those is part of what the function does. */
const GW = ['https://ipfs.io/ipfs/', 'https://cf-ipfs.com/ipfs/', 'https://gateway.pinata.cloud/ipfs/'];
const DEAD = ['ipfs.io', 'dweb.link', 'nftstorage.link', 'w3s.link'];
const body = src.slice(at, end + '\n    }'.length);
const monstrocityImageChain = new Function(
	'MONSTROCITY_GATEWAYS', 'MONSTROCITY_DEAD_GATEWAYS',
	body + '; return monstrocityImageChain;')(GW, DEAD);

const CID  = 'QmW8wgpwcnyqzj0r9z0pdwvvnqhpvxjv5zpq0a4sabcdefgh';
const HTTP = 'https://omenati.com/art/01998.jpg';
const DATA = 'data:image/svg+xml;base64,PHN2Zz48L3N2Zz4=';

console.log('\na bare CID still races the gateways');
/* Cardano and XRPL store a bare CID; that path must not change. */
let out = monstrocityImageChain({ ipfs: CID });
ok(out.includes('https://cf-ipfs.com/ipfs/' + CID),
   'a bare CID is no longer tried against a live gateway');
ok(out.filter(u => u.indexOf('ipfs.io') !== -1).length === 0,
   'a gateway known to refuse this server is still in the chain');

console.log('\nan absolute URL is the picture, not a CID');
/*
 * THE BUG. nfts.ipfs holds a whole https URL for any collection that hosts
 * its own art -- Solana's OMEN serves from omenati.com -- and the chain used
 * to hang it off every gateway, so all candidates 404'd and the card drew the
 * skull. Stats worked, art did not.
 */
out = monstrocityImageChain({ ipfs: HTTP });
ok(out[0] === HTTP, 'an absolute image URL is not tried first, as itself');
ok(out.every(u => !/\/ipfs\/https?:/i.test(u)),
   'a gateway is still being prepended to an absolute URL: ' + out[0]);
ok(out.length === 2, 'an absolute URL is being multiplied across gateways: ' + JSON.stringify(out));

console.log('\nan inline SVG is itself too');
out = monstrocityImageChain({ ipfs: DATA });
ok(out[0] === DATA, 'a data: URI is not passed through untouched');
ok(out.every(u => !/\/ipfs\/data:/i.test(u)), 'a gateway is being prepended to a data: URI');

console.log('\nthe cached copy still wins, whatever the form');
/* The cache keys on md5(ipfs), which works for a CID or a URL, so a local
   file must still come first -- it is the only candidate with no gateway. */
for (const [label, v] of [['CID', CID], ['absolute URL', HTTP]]) {
	out = monstrocityImageChain({ localUrl: '/staking/images/nfts/7/42/abc.png', ipfs: v });
	ok(out[0] === '/staking/images/nfts/7/42/abc.png',
	   'the locally cached copy is not tried first for an NFT stored as a ' + label);
}

console.log('\nthe chain never ends empty');
/* An empty chain means a broken <img> with no onerror target at all. */
for (const cfg of [{}, { ipfs: '' }, { ipfs: CID }, { ipfs: HTTP }]) {
	out = monstrocityImageChain(cfg);
	ok(out.length >= 1 && out[out.length - 1] === 'icons/skull.png',
	   'the chain does not end at the skull for ' + JSON.stringify(cfg));
}

console.log(fail ? '\n' + fail + ' check(s) FAILED\n' : '\nall good\n');
process.exit(fail ? 1 : 0);
