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
	document.getElementById('wallet').value = wallet;
	document.getElementById('address').value = address;
	document.getElementById('stakeaddress').value = stakeaddress;
	document.getElementById("addressForm").submit();
}

window.openWalletModal = function() {
	document.getElementById('wallet-modal-overlay').style.display = 'block';
	document.getElementById('wallet-modal').style.display = 'flex';
};

window.closeWalletModal = function() {
	document.getElementById('wallet-modal-overlay').style.display = 'none';
	document.getElementById('wallet-modal').style.display = 'none';
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
		'flint',
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
	};

	function populateWalletGrid(wallets) {
		const grid = document.getElementById('wallet-grid');
		if (!grid) return;

		grid.innerHTML = '';

		if (wallets.length === 0) {
			grid.innerHTML = '<div class="wallet-panel-empty">No Cardano wallets detected.<br><small>Install Lace, Eternl, Vespr, or Flint to connect.</small></div>';
			return;
		}

		wallets.forEach((walletKey) => {
			const info = window.cardano[walletKey];
			const name = capitalizeFirstLetter(walletKey);
			const icon = (info && info.icon) ? info.icon : (fallbackIcons[walletKey] || 'icons/wallet.png');

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
	});
})(jQuery);
