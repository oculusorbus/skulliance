import {Blockfrost, Lucid} from "https://unpkg.com/lucid-cardano@0.8.7/web/mod.js";
window.Lucid = Lucid;

async function connectWallet(wallet) {
	if (wallet !== "none") {
		const lucid = await Lucid.new(
			new Blockfrost("https://mainnet.blockfrost.io/api/v0", "mainnetn6TwLzWl4yFlbMUnKN9rOueczD7dOXgo"),
			"Mainnet",
		);
		const the_wallet = window.cardano[wallet];
		const api = await the_wallet.enable();
		lucid.selectWallet(api);
		const address = await lucid.wallet.address();
		const stakeAddress = await lucid.wallet.rewardAddress();
		if (stakeAddress !== "") {
			sendAddress(address, stakeAddress, wallet);
		}
	}
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
		.then(data => showWalletResult(data.success, data.message, data.redirect || null))
		.catch(() => showWalletResult(false, 'Connection error. Please try again.'));
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
