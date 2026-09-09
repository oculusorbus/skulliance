import {Lucid} from "https://unpkg.com/lucid-cardano@0.8.7/web/mod.js";
window.Lucid = Lucid;

let walletBusy = false;

/*
 * Connect a wallet.
 *
 * EVERY failure in here used to be silent. There was no try/catch, and the
 * modal was only opened once the whole chain had already succeeded -- so a
 * locked extension, a dismissed approval popup, an unreachable Blockfrost, or
 * a wallet with no stake key all produced exactly one symptom: clicking the
 * icon did nothing at all. Reported by a new user trying to add a second
 * wallet, who had no way to tell any of those apart.
 *
 * So: feedback before the first await, and every path ends in a message.
 */
async function connectWallet(wallet) {
	if (wallet === "none") return;
	if (walletBusy) return;   // a second click used to start a second chain
	walletBusy = true;

	openWalletModal();
	showWalletConnecting(wallet);

	try {
		const the_wallet = window.cardano && window.cardano[wallet];
		if (!the_wallet) throw new Error("missing-extension");

		// enable() FIRST, because it is what raises the wallet's own approval
		// popup. Initialising Lucid first meant waiting on a network round trip
		// before the user saw their wallet ask for anything.
		const api = await the_wallet.enable();

		/*
		 * NO PROVIDER. This is the fix for:
		 *
		 *   Uncaught (in promise) CostModel operation 166 out of bounds. Max is 166
		 *
		 * Passing a Blockfrost provider makes Lucid.new() fetch live protocol
		 * parameters and hand the cost models to CML. Cardano has since grown
		 * more cost-model entries than the pinned lucid-cardano@0.8.7 WASM can
		 * accept, so that call now throws for everyone, on every wallet.
		 *
		 * We never build a transaction here. All we do is read two addresses,
		 * and in Lucid the provider is optional -- every line that touches
		 * protocol parameters and cost models sits behind `if (provider)`.
		 * selectWallet()'s address() and rewardAddress() only decode what the
		 * CIP-30 extension already returned. So dropping the provider skips the
		 * broken path entirely rather than working around it.
		 *
		 * It also removes a Blockfrost API key that was sitting in readable
		 * client-side source. The server uses its own key from config; this one
		 * was separate, and is no longer needed at all.
		 */
		const lucid = await Lucid.new(undefined, "Mainnet");
		lucid.selectWallet(api);
		const address = await lucid.wallet.address();
		const stakeAddress = await lucid.wallet.rewardAddress();
		/*
		 * Previously `if (stakeAddress !== "")` with no else, which was wrong
		 * twice over. Lucid's rewardAddress() returns NULL when the wallet has
		 * no stake key, not "" -- so null !== "" passed, and a wallet with no
		 * staking address was sent to the server as though it had one. And when
		 * it did fail the check, there was no else, so it left silently.
		 */
		if (!stakeAddress) throw new Error("no-stake-address");

		sendAddress(address, stakeAddress, wallet);
	} catch (e) {
		walletBusy = false;
		showWalletResult(false, walletErrorMessage(e, wallet));
	}
}

/*
 * Turn whatever the extension threw into something a player can act on.
 *
 * CIP-30 rejects with a plain {code, info} object, NOT an Error, so this has to
 * read both shapes.
 */
function walletErrorMessage(e, wallet) {
	const name = capitalizeFirstLetter(wallet);
	if (e && e.message === "missing-extension") {
		return name + " did not respond. Make sure the extension is installed, unlocked, and enabled for this site, then try again.";
	}
	if (e && e.message === "no-stake-address") {
		return name + " returned no staking address. A wallet has to be delegated to a stake pool before it can be connected.";
	}
	// code 2 is CIP-30 APIError.Refused -- the approval popup was dismissed.
	// That is a choice, not a fault, so it should not read like a crash.
	if (e && (e.code === 2 || e.code === -3)) {
		return "Connection cancelled in " + name + ". Nothing was changed.";
	}
	const detail = (e && (e.info || e.message)) ? String(e.info || e.message) : "Unknown error.";
	return "Could not connect to " + name + ". " + detail;
}

window.connectWallet = connectWallet;

function sendAddress(address, stakeaddress, wallet) {
	const formData = new FormData();
	formData.append('wallet', wallet);
	formData.append('address', address);
	formData.append('stakeaddress', stakeaddress);

	openWalletModal();
	showWalletLoading();

	fetch('wallet-ajax.php', { method: 'POST', body: formData })
		.then(r => r.json())
		.then(data => { walletBusy = false; showWalletResult(data.success, data.message, data.redirect || null); })
		.catch(() => { walletBusy = false; showWalletResult(false, 'Connection error. Please try again.'); });
}

window.openWalletModal = function() {
	document.getElementById('wallet-modal-overlay').style.display = 'block';
	document.getElementById('wallet-modal').style.display = 'flex';
};

window.closeWalletModal = function() {
	document.getElementById('wallet-modal-overlay').style.display = 'none';
	document.getElementById('wallet-modal').style.display = 'none';
	resetWalletModal();
};

function showWalletLoading() {
	document.getElementById('wallet-grid').style.display = 'none';
	const refresh = document.querySelector('.wallet-modal-refresh');
	if (refresh) refresh.style.display = 'none';
	const status = document.getElementById('wallet-status');
	status.innerHTML = '<div class="wallet-spinner"></div><p class="wallet-status-text">Verifying NFTs&hellip;<br><small>This may take a moment.</small></p>';
	status.style.display = 'flex';
}

/*
 * Shown the instant an icon is clicked, before anything can block or fail.
 * Names the wallet, because the next thing the player should see is that
 * extension's own approval popup -- and if it does not appear, knowing which
 * one we are waiting on is the whole diagnosis.
 */
function showWalletConnecting(wallet) {
	document.getElementById('wallet-grid').style.display = 'none';
	const refresh = document.querySelector('.wallet-modal-refresh');
	if (refresh) refresh.style.display = 'none';
	const status = document.getElementById('wallet-status');
	status.innerHTML = '<div class="wallet-spinner"></div><p class="wallet-status-text">Waiting for ' +
		capitalizeFirstLetter(wallet) + '&hellip;<br><small>Approve the connection in your wallet.</small></p>';
	status.style.display = 'flex';
}

function showWalletResult(success, message, redirect) {
	const status = document.getElementById('wallet-status');
	const iconClass = success ? 'wallet-result-icon success' : 'wallet-result-icon error';
	const icon = success ? '&#10003;' : '&#10007;';
	let actions = '';
	if (success && redirect) {
		actions = '<a href="' + redirect + '" class="wallet-refresh-btn wallet-result-action">Go to Dashboard &rarr;</a>';
	} else if (!success) {
		actions = '<button class="wallet-refresh-btn wallet-result-action" onclick="resetWalletModal()">Try Again</button>';
	}
	status.innerHTML =
		'<span class="' + iconClass + '">' + icon + '</span>' +
		'<p class="wallet-status-text">' + message + '</p>' +
		actions;
}

window.resetWalletModal = function() {
	// Without this, a failed attempt would leave the guard latched and every
	// later click would be swallowed -- reintroducing the exact bug this fixes.
	walletBusy = false;
	document.getElementById('wallet-grid').style.display = '';
	const refresh = document.querySelector('.wallet-modal-refresh');
	if (refresh) refresh.style.display = '';
	const status = document.getElementById('wallet-status');
	status.style.display = 'none';
	status.innerHTML = '';
};

function capitalizeFirstLetter(string) {
	if (string === "typhoncip30") return "Typhon";
	if (string === "gerowallet") return "Gero";
	if (string === "LodeWallet") return "Lode";
	if (string === "nufi") return "NuFi";
	return string.charAt(0).toUpperCase() + string.slice(1);
}

(function ($) {
	const SupportedWallets = [
		'lace',
		'eternl',
		'vespr',
		'typhoncip30',
		'tokeo',
		'yoroi',
		'gerowallet',
		'LodeWallet',
		'nufi'
	];

	let retries = 10;
	let loop = null;
	const InstalledWallets = [];

	async function findWallets() {
		if (window.cardano !== undefined) {
			SupportedWallets.forEach((wallet) => {
				if (window.cardano[wallet] !== undefined && !InstalledWallets.includes(wallet)) {
					InstalledWallets.push(wallet);
				}
			});
		}

		retries--;

		if (retries <= 0) {
			clearInterval(loop);
			populateWalletGrid(InstalledWallets);
		}
	}

	const fallbackIcons = {
		'lace':        'icons/lace.svg',
		'eternl':      'icons/eternl.png',
		'vespr':       'icons/vespr.svg',
		'typhoncip30': 'icons/typhon.svg',
		'tokeo':       'icons/tokeo.png',
		'yoroi':       'icons/yoroi.svg',
		'gerowallet':  'icons/gero.svg',
		'nufi':        'icons/nufi.svg',
	};

	function populateWalletGrid(wallets) {
		const grid = document.getElementById('wallet-grid');
		if (!grid) return;

		grid.innerHTML = '';

		if (wallets.length === 0) {
			grid.innerHTML = '<div class="wallet-panel-empty">No Cardano wallets detected.<br><small>Install Lace, Eternl, Vespr, or Yoroi to connect.</small></div>';
			return;
		}

		wallets.forEach((walletKey) => {
			const info = window.cardano[walletKey];
			const name = capitalizeFirstLetter(walletKey);
			// Prefer local icons when defined (avoids bad data URIs from some extensions)
			const icon = fallbackIcons[walletKey] || (info && info.icon) || 'icons/wallet.png';

			const panel = document.createElement('div');
			panel.className = 'wallet-panel';
			panel.title = 'Connect ' + name;
			panel.onclick = function() { connectWallet(walletKey); };
			panel.innerHTML =
				'<img class="wallet-panel-icon" src="' + icon + '" alt="' + name + '">' +
				'<span class="wallet-panel-name">' + name + '</span>';
			grid.appendChild(panel);
		});
	}

	$(document).ready(() => {
		loop = setInterval(findWallets, 200);

		const refreshForm = document.getElementById('refreshWallet');
		if (refreshForm) {
			refreshForm.addEventListener('submit', function(e) {
				e.preventDefault();
				showWalletLoading();
				fetch('wallet-ajax.php', { method: 'POST', body: new FormData(this) })
					.then(r => r.json())
					.then(data => showWalletResult(data.success, data.message, data.redirect || null))
					.catch(() => showWalletResult(false, 'Connection error. Please try again.'));
			});
		}
	});
})(jQuery);
