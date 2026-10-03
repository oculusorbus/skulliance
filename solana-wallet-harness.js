/**
 * solana-wallet-harness.js -- node, no browser, no network.
 *
 * Runs the REAL SOLANA_EXT detection table out of header.php against fake
 * window objects.
 *
 * WHY THIS ONE PIECE IS WORTH A HARNESS. Several Solana wallets claim
 * `window.solana`, and the last extension to load wins it. So a page with
 * both Phantom and Solflare installed has ONE window.solana and two wallets,
 * and matching on it means clicking "Solflare" can connect Phantom. That
 * failure is completely silent: a real wallet opens, a real address comes
 * back, the link succeeds, and the address belongs to the wrong wallet. The
 * holder then reports that their NFTs did not show up, and nothing in any log
 * disagrees with them.
 *
 * So each wallet is matched on its OWN namespace first, and window.solana is
 * read only when it identifies itself as the wallet that was clicked. These
 * tests are that rule, written down.
 *
 * Usage: node solana-wallet-harness.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

/* Lift the real table out of header.php rather than restating it -- a
   restatement is the thing that drifts. */
const src = fs.readFileSync(path.join(__dirname, 'header.php'), 'utf8');
const start = src.indexOf('var SOLANA_EXT = {');
ok(start !== -1, 'SOLANA_EXT is gone from header.php');
if (start === -1) { console.log('\nFAILED\n'); process.exit(1); }
/* Brace-match, so adding a wallet to the table does not move a marker. */
let i = src.indexOf('{', start), depth = 0, end = i;
for (; i < src.length; i++) {
	if (src[i] === '{') depth++;
	else if (src[i] === '}') { depth--; if (depth === 0) { end = i; break; } }
}
const table = src.slice(start, end + 1) + ';';

/* Each case builds a window, evaluates the real table against it, and asks
   which provider each wallet's get() returns. `window` is a parameter, so
   the lifted code closes over the fake rather than over node's globals. */
function detect(win) {
	const f = new Function('window', table + ' var out = {}; for (var k in SOLANA_EXT) out[k] = SOLANA_EXT[k].get(); return out;');
	return f(win);
}

const solflare = { isSolflare: true, tag: 'solflare' };
const phantom  = { isPhantom: true,  tag: 'phantom'  };
const backpack = { isBackpack: true, tag: 'backpack' };

console.log('one wallet installed');
let d = detect({ solflare: solflare });
ok(d.solflare === solflare, 'Solflare on its own namespace was not detected');
ok(d.phantom === null && d.backpack === null, 'a wallet that is not installed was detected');

d = detect({ phantom: { solana: phantom } });
ok(d.phantom === phantom, 'Phantom under window.phantom.solana was not detected');
ok(d.solflare === null, 'Solflare was detected when only Phantom is installed');

d = detect({ backpack: backpack });
ok(d.backpack === backpack, 'Backpack was not detected');

console.log('\nthe shared window.solana, which only one wallet can own');
/* Phantom took window.solana; Solflare is also installed on its own
   namespace. Clicking Solflare must get SOLFLARE. */
d = detect({ solana: phantom, solflare: solflare });
ok(d.solflare === solflare,
   'with Phantom holding window.solana, clicking Solflare returned Phantom -- the wrong address would be linked, silently');
ok(d.phantom === phantom, 'Phantom was not found via the window.solana it owns');

/* And the mirror: Solflare took window.solana, Phantom is on its own. */
d = detect({ solana: solflare, phantom: { solana: phantom } });
ok(d.phantom === phantom, 'with Solflare holding window.solana, clicking Phantom returned Solflare');
ok(d.solflare === solflare, 'Solflare was not found via the window.solana it owns');

console.log('\nand window.solana alone, which is the fallback and not the plan');
d = detect({ solana: solflare });
ok(d.solflare === solflare, 'a wallet that only sets window.solana was not reachable at all');
ok(d.phantom === null && d.backpack === null,
   'window.solana was handed to a wallet that did not claim it -- clicking Phantom must not connect Solflare');

console.log('\nand nothing at all');
d = detect({});
ok(d.solflare === null && d.phantom === null && d.backpack === null,
   'a wallet was detected on an empty page');
/* An unidentified provider is NOT a wallet we can name. Linking an address
   under the wrong `via` is a lie in the wallets table, not a convenience. */
d = detect({ solana: { tag: 'mystery' } });
ok(d.solflare === null && d.phantom === null && d.backpack === null,
   'an unidentified window.solana was claimed by a named wallet tile');

console.log('\nevery tile in the table has markup to reveal');
const f = new Function('window', table + ' var o = {}; for (var k in SOLANA_EXT) o[k] = SOLANA_EXT[k].el; return o;');
const els = f({});
for (const k of Object.keys(els)) {
	ok(src.includes('id="' + els[k] + '"'),
	   `SOLANA_EXT.${k} reveals #${els[k]}, which is not in the markup -- the tile can never appear`);
	ok(new RegExp('id="' + els[k] + '"[^>]*style="display:none"').test(src),
	   `#${els[k]} is not hidden by an inline display:none, so it shows whether or not the wallet is installed`);
	ok(src.includes("solanaConnect('" + k + "')"),
	   `nothing calls solanaConnect('${k}'), so that tile does nothing when clicked`);
}
/* The link endpoint whitelists `via`; a tile whose key is not on that list
   would be silently downgraded to 'extension' and the wallets table would
   stop recording which wallet was actually used. */
const linksrc = fs.readFileSync(path.join(__dirname, 'ajax/solana-link.php'), 'utf8');
for (const k of Object.keys(els)) {
	ok(new RegExp("'" + k + "'\\s*=>\\s*1").test(linksrc),
	   `ajax/solana-link.php does not whitelist via='${k}', so link_method would record 'extension' instead`);
}

console.log('\n' + (fails ? `FAILED (${fails})\n` : 'solana wallet detection: ok\n'));
process.exit(fails ? 1 : 0);
