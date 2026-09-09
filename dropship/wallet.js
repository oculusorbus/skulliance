import {Lucid} from "https://unpkg.com/lucid-cardano@0.8.7/web/mod.js";
window.Lucid=Lucid;

async function connectWallet(wallet){
	if(wallet != "none"){
		document.getElementById('loading').style.display = "block";
		try{
			/*
			 * NO PROVIDER -- same fix as the main platform's wallet.js.
			 *
			 * Passing Blockfrost makes Lucid.new() fetch protocol parameters and
			 * hand the cost models to CML, and Cardano now has more cost-model
			 * entries than this pinned lucid-cardano@0.8.7 accepts:
			 *   Uncaught (in promise) CostModel operation 166 out of bounds.
			 * That threw for every wallet, on every attempt. Nothing here builds
			 * a transaction -- it only reads a stake address -- and Lucid's
			 * provider is optional, so dropping it skips the broken path.
			 */
			const lucid = await Lucid.new(undefined, "Mainnet");
			//var wallet = "nami";
			const the_wallet = window.cardano[wallet];
			const api = await the_wallet.enable();
			lucid.selectWallet(api);
			const address = await lucid.wallet.address();
			const stakeAddress = await lucid.wallet.rewardAddress();
			/* Old address approach
			if(address != ""){
				sendAddress(address, wallet);
			}*/
			// rewardAddress() returns null, not "", when there is no stake key.
			if(stakeAddress){
				sendAddress(stakeAddress, wallet);
				return;
			}
		}catch(e){
			// Without this the spinner above stayed up forever on any failure.
			console.error('Wallet connection failed:', e);
		}
		document.getElementById('loading').style.display = "none";
	}
}

window.connectWallet = connectWallet;

function sendAddress(address, wallet){
	document.getElementById('wallet').value = wallet;
	document.getElementById('address').value = address;
	document.getElementById("addressForm").submit();
}

function capitalizeFirstLetter(string) {
  return string.charAt(0).toUpperCase() + string.slice(1);
}

(function ($) {
    const SupportedWallets = [
         'nami',
		 'eternl',
		 'yoroi',
		 'typhoncip30',
		 'gerowallet',
		 'flint',
		 'LodeWallet',
		 'nufi'
    ];

    let retries = 10;
    let loop = null;
    const InstalledWallets = [];

    async function findWallets() {
        if (retries <= 0) {
            clearInterval(loop);
            if (InstalledWallets.length) {
                InstalledWallets.forEach((wallet) => {
					var option = document.createElement("option");
					var displayText = wallet;
					if(displayText == "typhoncip30"){
						displayText = "Typhon";
					}
					option.text = capitalizeFirstLetter(displayText);
					option.value = wallet;
					var select = document.getElementById("wallets");
					select.appendChild(option);
                });
				// Update dropdown with selected wallet after wallet options finish populating
				//updateWallet();
            } else {
            }
        }

        if (window.cardano === undefined) {
            retries--;
            return;
        }

        SupportedWallets.forEach((wallet) => {
            if (window.cardano[wallet] === undefined) {
                return;
            }

            if (!InstalledWallets.includes(wallet)) {
                InstalledWallets.push(wallet);
            }
        });

        retries--;

    }

    $(document).ready(() => {
        loop = setInterval(findWallets, 200);
    });
})(jQuery);