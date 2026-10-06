/**
 * polygon-wallet-harness.js -- node, no browser, no network.
 *
 * Runs the REAL evmProvider(), evmName() and polygonConnect() out of
 * header.php against fake wallets.
 *
 * WHAT IS WORTH TESTING HERE IS NOT WHAT WAS WORTH TESTING ON SOLANA.
 * There, several wallets claim window.solana and the risk is connecting
 * the wrong one. Every EVM wallet shares window.ethereum by design, so
 * there is no wrong one to pick -- the risks are different:
 *
 *  1. A DECLINED NETWORK SWITCH MUST STILL LINK. The switch to Polygon
 *     buys reassurance and nothing else: eth_requestAccounts returns the
 *     same address on every chain and ownership is read server-side from
 *     our own node. If a refusal propagates, we have invented a failure
 *     for a step that did not need to succeed -- and it would only show
 *     up for people who keep their wallet on another chain, which is
 *     most of them.
 *  2. A USER CLOSING THE POPUP IS AN ANSWER, not an error with a Try
 *     Again button.
 *  3. The address that reaches the endpoint must be the one the wallet
 *     returned.
 *
 * Usage: node polygon-wallet-harness.js
 */
'use strict';
const fs = require('fs');
const path = require('path');

let fails = 0;
function ok(cond, what) { if (!cond) { fails++; console.log('  FAIL  ' + what); } }

const src = fs.readFileSync(path.join(__dirname, 'header.php'), 'utf8');

/* Brace-match the real functions rather than restating them -- a
   restatement is the thing that drifts. */
function grab(sig) {
	const at = src.indexOf(sig);
	ok(at !== -1, sig + ' is gone from header.php');
	if (at === -1) return '';
	let i = src.indexOf('{', at), depth = 0;
	for (; i < src.length; i++) {
		if (src[i] === '{') depth++;
		else if (src[i] === '}') { depth--; if (depth === 0) return src.slice(at, i + 1); }
	}
	return '';
}
function grabVar(sig) {
	const at = src.indexOf(sig);
	ok(at !== -1, sig + ' is gone from header.php');
	if (at === -1) return '';
	let i = src.indexOf('[', at), depth = 0;
	for (; i < src.length; i++) {
		if (src[i] === '[') depth++;
		else if (src[i] === ']') { depth--; if (depth === 0) return src.slice(at, i + 1) + ';'; }
	}
	return '';
}

const code = [grabVar('var EVM_WALLETS = ['), grab('function evmProvider('),
              grab('function evmName('), grab('function polygonConnect(')].join('\n');
if (fails) { console.log('\nFAILED\n'); process.exit(1); }

/* ---------- the world the page runs in ---------------------------------- */
function makeWorld(opts) {
	opts = opts || {};
	const log = {said: [], result: null, step: null, posted: null, reloaded: false};
	const env = {
		window: {},
		document: { getElementById: () => null, querySelector: () => null },
		solStatus: () => {},
		solEsc: s => String(s),
		solSay: m => log.said.push(m),
		polyResult: (okFlag, msg) => { log.result = {ok: okFlag, msg: msg}; },
		walletStep: s => { log.step = s; },
		FormData: function () { this.d = {}; this.append = (k, v) => { this.d[k] = v; }; },
		fetch: (url, init) => {
			log.posted = {url: url, body: init.body.d};
			return Promise.resolve({text: () => Promise.resolve(
				JSON.stringify(opts.reply || {ok: true, message: 'Wallet linked.'}))});
		},
		setTimeout: fn => { log.reloaded = true; },
		location: {reload: () => {}}
	};
	return {env, log};
}
function run(env, extra) {
	const names = Object.keys(env);
	const fn = new Function(...names, code + '\n' + extra);
	return fn(...names.map(k => env[k]));
}

console.log('picking a provider');

ok(run(makeWorld().env, 'return evmProvider();') === null,
   'no wallet installed still returns a provider');

let w = makeWorld();
w.env.window.ethereum = {isMetaMask: true};
ok(run(w.env, 'return evmProvider();') === w.env.window.ethereum, 'a lone provider is not returned');
ok(run(w.env, 'return evmName(evmProvider());') === 'MetaMask', 'MetaMask is not named');

/* Several installed: EIP-5749 lists them all and window.ethereum is
   whichever won the race. The tile's logo is MetaMask's fox, so MetaMask
   is what it should open when present. */
w = makeWorld();
const mm = {isMetaMask: true}, rabby = {isRabby: true};
w.env.window.ethereum = Object.assign({isRabby: true}, {providers: [rabby, mm]});
ok(run(w.env, 'return evmProvider();') === mm,
   'with several wallets installed the fox tile does not open MetaMask');

w = makeWorld();
w.env.window.ethereum = {providers: [rabby]};
ok(run(w.env, 'return evmProvider();') === rabby, 'the only listed provider is not used');
ok(run(w.env, 'return evmName(evmProvider());') === 'Rabby', 'Rabby is not named');

w = makeWorld();
w.env.window.ethereum = {isCoinbaseWallet: true};
ok(run(w.env, 'return evmName(evmProvider());') === 'Coinbase Wallet', 'Coinbase is not named');
w = makeWorld();
w.env.window.ethereum = {someUnknownWallet: true};
ok(run(w.env, 'return evmName(evmProvider());') === 'Your wallet',
   'an unrecognised wallet is announced as MetaMask, which is a lie on the button');

console.log('\nthe network switch asks, it does not require');

const ADDR = '0x252f8AA06621248fdB5F7624Fb40D09B1dD1f58B';

function connectWith(switchBehaviour, opts) {
	const h = makeWorld(opts);
	const calls = [];
	h.env.window.ethereum = {
		isMetaMask: true,
		request: function (req) {
			calls.push(req.method);
			if (req.method === 'eth_requestAccounts') return Promise.resolve([ADDR]);
			if (req.method === 'wallet_switchEthereumChain') return switchBehaviour(req);
			return Promise.resolve(null);
		}
	};
	run(h.env, 'polygonConnect();');
	return new Promise(r => setImmediate(() => setImmediate(() => setImmediate(() => r({h, calls})))));
}

/* THE ONE THAT MATTERS. Refusing the switch must not refuse the link. */
connectWith(() => Promise.reject({code: 4001, message: 'User rejected the request.'}))
	.then(({h, calls}) => {
		ok(calls.indexOf('wallet_switchEthereumChain') !== -1, 'Polygon is never requested at all');
		ok(h.log.posted !== null,
		   'DECLINING THE NETWORK SWITCH ABORTED THE LINK -- the switch is reassurance, not a requirement');
		ok(h.log.posted && h.log.posted.body.address === ADDR,
		   'the address that reached the endpoint is not the one the wallet returned');
		ok(h.log.posted && /polygon-link\.php$/.test(h.log.posted.url), 'the link went to the wrong endpoint');
		ok(h.log.result && h.log.result.ok, 'a declined switch is reported to the user as a failure');

		/* 4902: the chain is not in their wallet. Same treatment -- we do
		   not prompt to ADD a network for something that changes nothing. */
		return connectWith(() => Promise.reject({code: 4902, message: 'Unrecognized chain ID.'}));
	})
	.then(({h}) => {
		ok(h.log.posted !== null, 'a wallet without Polygon configured cannot link');

		return connectWith(() => Promise.resolve(null));
	})
	.then(({h, calls}) => {
		ok(h.log.posted !== null, 'an ACCEPTED switch broke the link');
		ok(calls[0] === 'eth_requestAccounts',
		   'the chain switch is requested before the accounts, so it prompts before anyone has connected');

		console.log('\nclosing the popup is an answer, not an error');
		const h2 = makeWorld();
		h2.env.window.ethereum = {
			isMetaMask: true,
			request: () => Promise.reject({code: 4001, message: 'User rejected the request.'})
		};
		run(h2.env, 'polygonConnect();');
		return new Promise(r => setImmediate(() => setImmediate(() => r(h2))));
	})
	.then(h2 => {
		ok(h2.log.step === 'polygon',
		   'rejecting the CONNECT does not put the user back on the wallet grid');
		ok(h2.log.result === null,
		   'rejecting the connect shows an error with a Try Again button, which it is not');

		console.log('\na real failure is still a failure');
		const h3 = makeWorld({reply: {ok: false, message: 'That wallet is linked to another account.'}});
		h3.env.window.ethereum = {
			isMetaMask: true,
			request: req => req.method === 'eth_requestAccounts'
				? Promise.resolve([ADDR]) : Promise.resolve(null)
		};
		run(h3.env, 'polygonConnect();');
		return new Promise(r => setImmediate(() => setImmediate(() => setImmediate(() => r(h3)))));
	})
	.then(h3 => {
		ok(h3.log.result && h3.log.result.ok === false, "the endpoint's refusal was not surfaced");
		ok(h3.log.result && /another account/.test(h3.log.result.msg),
		   "the endpoint's own message was replaced with a generic one");

		console.log('');
		if (fails) { console.log(fails + ' check(s) FAILED'); process.exit(1); }
		console.log('polygon wallet: ok');
	})
	.catch(e => { console.log('  FAIL  harness threw: ' + (e && e.stack || e)); process.exit(1); });
