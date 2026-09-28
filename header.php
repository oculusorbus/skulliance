<!doctype html>
<html>
<head>
  <title><?php echo isset($page_title_override) ? htmlspecialchars($page_title_override) : 'Skulliance'; ?></title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">

  <!-- PWA -->
  <link rel="manifest" href="/staking/manifest.webmanifest">
  <!-- Same VERSION-file mtime signal version.php serves -- see that file's
       own comment. Was filemtime(__FILE__) (header.php's own mtime), which
       missed any push that didn't happen to touch header.php itself. -->
  <meta name="app-version" content="<?php echo @filemtime(__DIR__ . '/VERSION') ?: time(); ?>">
  <meta name="theme-color" content="#161616">
  <meta name="application-name" content="Skulliance">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="Skulliance">
  <meta name="mobile-web-app-capable" content="yes">
  <link rel="apple-touch-icon" sizes="180x180" href="/staking/pwa/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/staking/pwa/favicon-32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/staking/pwa/favicon-16.png">
  <link rel="icon" type="image/png" sizes="192x192" href="/staking/pwa/icon-192.png">
  <link rel="shortcut icon" href="/staking/pwa/favicon-32.png">

  <!-- Self-hosted, not loaded from Google's CDN (ajax.googleapis.com) --
       a staker on Brave couldn't use Missions/Realms at all for months:
       Brave's Shields (or a filter list it ships) resolves that specific
       host to nowhere (net::ERR_NAME_NOT_RESOLVED in their own console),
       jQuery never loads, and every $-dependent script on the page --
       skulliance.js, wallet.js, missions.php's own inline script --
       throws "$ is not defined" and silently does nothing. Self-hosting
       removes the cross-origin dependency entirely; nothing left for a
       privacy blocklist to target by domain. dist/jquery-3.6.3.min.js
       pulled directly from code.jquery.com (the jQuery Foundation's own
       CDN, not a third-party mirror), unmodified. -->
  <script src="dist/jquery-3.6.3.min.js"></script>
  <!--<link href="dist/output.css" rel="stylesheet">-->
  <link href="dist/flexbox.css?var=<?php echo rand(0,999); ?>" rel="stylesheet">
  <link href="dist/modal.css?var=<?php echo rand(0,999); ?>" rel="stylesheet">
  <link href="dist/circular-progress-bar.css?var=<?php echo rand(0,999); ?>" media="all" rel="stylesheet" />
  <?php
  if(basename($_SERVER['REQUEST_URI']) == "realms.php"){
  ?>  
	  <link href="dist/map.css?var=<?php echo rand(0,999); ?>" media="all" rel="stylesheet" />
  <?php
  }
  ?>
  <script type="text/javascript">
	  // Toggle burger menu
	  function toggleMenu(){
	  	if(document.getElementById('burger-icon').src == "https://www.skulliance.io/staking/images/menu.png"){
	  		document.getElementById('burger-icon').src = "https://www.skulliance.io/staking/images/close.png";
	  		document.getElementById("navbar").classList.add('show-menu');
	  		document.getElementById("navbar").classList.remove('hide-menu');
	  		// Auto-expand Play section — most-used on mobile, saves one tap
	  		var playMenu = document.querySelector('.nav-dropdown.navbar-first .nav-dropdown-menu');
	  		if(playMenu) playMenu.classList.add('open');
	  	}else{
	  		document.getElementById('burger-icon').src = "https://www.skulliance.io/staking/images/menu.png";
	  		document.getElementById("navbar").classList.add('hide-menu');
	  		document.getElementById("navbar").classList.remove('show-menu');
	  	}
	  }
	  // Toggle dropdown (mobile)
	  function toggleDropdown(el){
	  	var menu = el.nextElementSibling;
	  	var isOpen = menu.classList.contains('open');
	  	document.querySelectorAll('.nav-dropdown-menu.open').forEach(function(m){ m.classList.remove('open'); });
	  	if(!isOpen) menu.classList.add('open');
	  }
  </script>
  <script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
  <script>
    // Register service worker — needed for Android PWA install eligibility.
    if ('serviceWorker' in navigator) {
      window.addEventListener('load', function() {
        navigator.serviceWorker.register('/staking/service-worker.js', { scope: '/staking/' });
      });
    }
  </script>
  <?php if(isset($extra_head)) echo $extra_head; ?>
</head>
<body>
<!-- Global navigation loader — shown when clicking any internal link -->
<style>
#nav-loader {
    position: fixed; inset: 0;
    background: #07111d;
    z-index: 99999;
    display: none; flex-direction: column;
    align-items: center; justify-content: center; gap: 20px;
    opacity: 0; transition: opacity .25s ease;
}
#nav-loader.active { opacity: 1; }
@keyframes nl-bar { to { width: 90%; } }
@keyframes lp { 0%,100%{opacity:.3;transform:scale(.92)} 50%{opacity:1;transform:scale(1)} }
.nl-bar-wrap { width: 200px; height: 3px; background: rgba(255,255,255,.08); border-radius: 2px; overflow: hidden; }
.nl-bar      { height: 100%; background: #00c8a0; width: 0%; }
.nl-text     { font-size: .78rem; color: rgba(255,255,255,.35); letter-spacing: .1em; text-transform: uppercase; }
</style>
<div id="nav-loader">
    <div style="animation:lp 1.2s ease-in-out infinite;"><img src="/staking/pwa/skulliance-logo-icon.png" alt="" width="35" height="48"></div>
    <div class="nl-bar-wrap"><div class="nl-bar"></div></div>
    <div class="nl-text">Loading&hellip;</div>
</div>
<script>
(function(){
    function showNavLoader() {
        var el = document.getElementById('nav-loader');
        if (!el) return;
        var bar = el.querySelector('.nl-bar');
        if (bar) { var nb = bar.cloneNode(true); nb.style.animation = 'nl-bar 12s ease-out forwards'; bar.parentNode.replaceChild(nb, bar); }
        el.style.transition = 'none';
        el.style.opacity = '1';
        el.style.display = 'flex';
        el.classList.add('active');
    }
    document.addEventListener('click', function(e) {
        var a = e.target.closest('a[href]');
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript') === 0) return;
        // Only internal links
        var isInternal = href.charAt(0) === '/' || href.charAt(0) === '.' ||
                         (!href.match(/^https?:\/\//) || href.indexOf(window.location.hostname) !== -1);
        if (isInternal && a.target !== '_blank') {
            // Prevent default so we can flush DOM changes before navigating.
            // Mobile browsers won't repaint between classList changes and navigation
            // unless we yield back to the rendering engine first.
            e.preventDefault();
            var navbar = document.getElementById('navbar');
            if (navbar && navbar.classList.contains('show-menu')) {
                navbar.classList.remove('show-menu');
                var icon = document.getElementById('burger-icon');
                if (icon) icon.src = 'https://www.skulliance.io/staking/images/menu.png';
            }
            showNavLoader();
            var dest = a.href;
            setTimeout(function(){ window.location.href = dest; }, 50);
        }
    });
    // Hide if browser restores page from bfcache
    window.addEventListener('pageshow', function(e) {
        if (e.persisted) {
            var el = document.getElementById('nav-loader');
            if (el) { el.classList.remove('active'); setTimeout(function(){ el.style.display='none'; }, 300); }
        }
    });
})();
</script>
	<div class="container">
		<div id="burger-menu">
			<img id="burger-icon" onclick="javascript:toggleMenu();" src="https://www.skulliance.io/staking/images/menu.png"/>
		</div>
		<!-- Navigation Bar -->
		<div class="navbar" id="navbar">
		  <?php if(isset($name)){?>
		  <?php if(isset($avatar_url)){?>
	      <img class="rounded-full" src="<?php echo $avatar_url?>" onerror="this.src='icons/skull.png'" />
		  <?php } ?>
		  <a href="profile.php<?php echo (isset($name)) ? '?username='.urlencode($name) : ''; ?>"><?php echo (isset($name))?$name:"";?></a>

		  <!-- Launchpad: a top-level link, deliberately not inside a dropdown.
		       It is the page after login and the way back to everything, so
		       burying it in a menu would defeat the point of it existing. -->
		  <a href="launchpad.php">Launchpad</a>

		  <!-- Play -->
		  <div class="nav-dropdown navbar-first">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Play</span>
		    <div class="nav-dropdown-menu">
		      <a href="missions.php">Missions</a>
		      <a href="realms.php">Realms</a>
		      <!-- Listed under Realms deliberately: it reads that realm's locations,
		           soldiers and gear to build its starting position.
		           Points at the MARKETING page, not straight at the game, matching
		           Crypt Crawl / Crypt Conquest / Skull Racer below. Obscura is the
		           one exception and says so in its own comment. -->
		      <a href="guardiansgame.php">Guardians</a>
		      <a href="dhcfighters.php">DHC Fighters</a>
		      <!-- The real Arena, wired to the platform: real Fighters, a real
		           opponent, a server that owns every rule, real trait drops. The
		           throwaway it grew out of is still in the repo as
		           dhcarena-prototype.php, deliberately unlisted -- it keeps no
		           state and pays nothing, so a player finding it would only be
		           confused about which one counts. See dhcarena.md. -->
		      <a href="dhcarena.php">DHC Arena</a>
		      <a href="cryptcrawlgame.php">Crypt Crawl</a>
		      <a href="cryptconquestgame.php">Crypt Conquest</a>
		      <a href="match3rpg.php">Monstrocity</a>
		      <a href="monstrocity.php#boss">Boss Battles</a>
		      <a href="skullswap.php">Skull Swap</a>
		      <a href="skullracergame.php">Skull Racer</a>
              <a href="gauntlets.php">Gauntlets</a>		
		      <!-- Straight to the game, not a *game.php marketing landing:
		           Obscura deliberately has no public page, it's an internal one. -->		  
		      <a href="obscura.php">Obscura</a>
		      <!-- DHC Fighters only. The Fighter Collection is deliberately NOT
		           listed here: Play is a list of things to play, and the gallery
		           is reached from inside DHC Fighters, where a player already has
		           a Fighter worth going to look at. -->
		      <a href="dropship/dashboard.php">Drop Ship</a>
		    </div>
		  </div>

		  <!-- NFTs -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">NFTs</span>
		    <div class="nav-dropdown-menu">
		      <a href="my-nfts.php">My NFTs</a>
		      <a href="store.php">Store</a>
		      <a href="auctions.php">Auctions</a>
		      <a href="raffles.php">Raffles</a>
		      <a href="collections.php">Collections</a>
		      <a href="gallery.php">Gallery</a>
		      <a href="diamond-skulls.php">Diamond Skulls</a>
		      <a href="skulliverse.php">Skulliverse</a>
		    </div>
		  </div>

		  <!-- Stats -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Stats</span>
		    <div class="nav-dropdown-menu">
		      <a href="profile.php<?php echo (isset($name)) ? '?username='.urlencode($name) : ''; ?>">Profile</a>
		      <a href="leaderboards.php">Leaderboards</a>
      <a href="analytics.php">Analytics</a>
		    </div>
		  </div>

		  <!-- Account -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Account</span>
		    <div class="nav-dropdown-menu">
		      <a href="points.php">Points</a>
		      <a href="crafting.php">Crafting</a>
		      <a href="transactions.php">Transactions</a>
		      <a href="wallets.php">Wallets</a>
		    </div>
		  </div>

		  <a href="skullpaper.php">Skull Paper</a>
		  <a href="logout.php">Logout</a>
		  <button id="wallet-nav-btn" onclick="openWalletModal()" title="Connect Wallet"><img src="icons/wallet.png" class="wallet-nav-icon" alt="Wallet"/></button>
		  <?php } ?>
		</div>
		<div id="revealPoint"></div>
		<button onclick="topFunction()" id="back-to-top-button" title="Go to top">^</button>

		<!-- Confirm Modal -->
		<div id="confirm-overlay" style="display:none"></div>
		<div id="confirm-modal" role="dialog" aria-modal="true" style="display:none">
			<div class="notify-header">
				<span>Confirm</span>
				<button class="notify-close" onclick="closeConfirm()">&times;</button>
			</div>
			<div id="confirm-body" class="notify-body"></div>
			<div class="notify-footer" style="gap:10px;">
				<button onclick="closeConfirm()" class="small-button" style="background:rgba(255,255,255,0.08);color:#e8eaed;">Cancel</button>
				<button id="confirm-ok" class="small-button">Confirm</button>
			</div>
		</div>
		<script>
			function openConfirm(message, onConfirm) {
				document.getElementById('confirm-body').innerHTML = message.replace(/\r?\n/g, '<br>');
				document.getElementById('confirm-overlay').style.display = 'block';
				document.getElementById('confirm-modal').style.display = 'flex';
				document.getElementById('confirm-ok').onclick = function() {
					closeConfirm();
					onConfirm();
				};
			}
			function closeConfirm() {
				document.getElementById('confirm-overlay').style.display = 'none';
				document.getElementById('confirm-modal').style.display = 'none';
			}
			function confirmForm(form, message) {
				openConfirm(message, function() { form.submit(); });
			}
		</script>

		<!-- Notification Modal -->
		<div id="notify-overlay" onclick="closeNotify()" style="display:none"></div>
		<div id="notify-modal" role="dialog" aria-modal="true" style="display:none">
			<div class="notify-header">
				<span>Notification</span>
				<button class="notify-close" onclick="closeNotify()">&times;</button>
			</div>
			<div id="notify-body" class="notify-body"></div>
			<div class="notify-footer">
				<button onclick="closeNotify()" class="small-button">OK</button>
			</div>
		</div>
		<script>
			function openNotify(message) {
				document.getElementById('notify-body').innerHTML = message;
				document.getElementById('notify-overlay').style.display = 'block';
				document.getElementById('notify-modal').style.display = 'flex';
			}
			function closeNotify() {
				document.getElementById('notify-overlay').style.display = 'none';
				document.getElementById('notify-modal').style.display = 'none';
			}
		</script>

		<!-- Wallet Connect Modal -->
		<div id="wallet-modal-overlay" onclick="closeWalletModal()" style="display:none"></div>
		<div id="wallet-modal" role="dialog" aria-modal="true" style="display:none">
			<div class="wallet-modal-header">
				<span>Connect Wallet</span>
				<button class="wallet-modal-close" onclick="closeWalletModal()">&times;</button>
			</div>
			<div id="wallet-grid" class="wallet-grid">
				<div class="wallet-panel-empty">Detecting wallets&hellip;</div>
			</div>
			<div id="wallet-status" style="display:none"></div>

			<?php if(isset($_SESSION['userData']['user_id'])): ?>
			<?php /* XRP LEDGER. Not a wallet the CIP-30 grid can detect, because
			         Xaman is a phone app rather than a browser extension -- so
			         it is offered explicitly rather than "detected". */ ?>
			<div class="wallet-xrpl">
				<button type="button" class="wallet-xrpl-btn" onclick="xamanConnect()">
					<span class="wallet-xrpl-mark">XRP</span>
					<span>Connect an XRP Ledger wallet<small>Scan with Xaman</small></span>
				</button>
				<?php /* CROSSMARK and GEMWALLET. Desktop paths for people who do
				         not keep their XRPL account on a phone. Only offered when
				         the extension is actually present.

				         NEITHER ONE CONNECTS A HARDWARE WALLET, and this file
				         said otherwise for a while -- "works with Ledger" under
				         the Crossmark button, which was simply false and sends a
				         hardware holder to install an extension that cannot help
				         them. No XRPL browser extension supports one; that is
				         what the Ledger button below is for. multichain.md §4g. */ ?>
				<button type="button" class="wallet-xrpl-btn" id="crossmark-btn" hidden
				        onclick="xrplExtConnect('crossmark')" style="margin-top:6px">
					<span class="wallet-xrpl-mark">XRP</span>
					<span>Connect with Crossmark<small>Browser extension — software accounts only</small></span>
				</button>
				<button type="button" class="wallet-xrpl-btn" id="gemwallet-btn" hidden
				        onclick="xrplExtConnect('gemwallet')" style="margin-top:6px">
					<span class="wallet-xrpl-mark">XRP</span>
					<span>Connect with GemWallet<small>Browser extension — software accounts only</small></span>
				</button>
				<div id="ledger-pick" hidden></div>
				<?php /* LEDGER. Via xrpl-connect's LedgerAdapter -- one npm package
				         with ZERO dependencies whose tarball is verified against
				         the registry integrity hash, which is what the raw
				         @ledgerhq libraries could never offer (they ship no UMD,
				         so the only browser form came from a transpiling CDN).
				         Shown only where WebHID exists. */ ?>
				<button type="button" class="wallet-xrpl-btn" id="ledger-btn" hidden
				        onclick="ledgerConnect()" style="margin-top:6px">
					<span class="wallet-xrpl-mark">XRP</span>
					<span>Connect a Ledger<small>The only hardware-wallet path — plug in and open the XRP app</small></span>
				</button>
				<div id="xaman-panel" hidden>
					<div id="xaman-msg">Creating a sign-in request&hellip;</div>
					<img id="xaman-qr" alt="Scan this with Xaman" hidden>
					<a id="xaman-link" href="#" target="_blank" rel="noopener" hidden>Open Xaman on this device</a>
					<button type="button" class="wallet-xrpl-cancel" onclick="xamanCancel()">Cancel</button>
				</div>
			</div>
			<style>
			.wallet-xrpl{margin:10px 12px 0;padding-top:10px;border-top:1px solid rgba(255,255,255,.12)}
			.wallet-xrpl-btn{display:flex;align-items:center;gap:10px;width:100%;
			  background:#0d1e2e;border:1px solid #1b3346;color:#e8eaed;border-radius:4px;
			  padding:9px 12px;cursor:pointer;font:inherit;text-align:left}
			.wallet-xrpl-btn:hover{border-color:#00c8a0;color:#00c8a0}
			.wallet-xrpl-btn small{display:block;font-size:10px;opacity:.6}
			.wallet-xrpl-mark{font-size:10px;font-weight:700;letter-spacing:.08em;
			  background:#1b3346;border-radius:3px;padding:4px 6px}
			#xaman-panel{margin-top:10px;text-align:center;font-size:12px}
			#xaman-qr{width:190px;height:190px;margin:8px auto;display:block;background:#fff;border-radius:4px}
			#xaman-link{display:inline-block;margin:4px 0 8px;color:#00c8a0;font-size:12px}
			.wallet-xrpl-cancel{background:none;border:1px solid #1b3346;color:#7a9eb0;
			  border-radius:3px;padding:4px 10px;cursor:pointer;font:inherit;font-size:11px}
			</style>
			<script>
			/* THE BROWSER HOLDS THE WEBSOCKET, not the server.
			   Xaman allows ~60-200 calls a minute. Polling a pending sign-in every
			   couple of seconds is ~30 calls each, so a handful of simultaneous
			   sign-ins would throttle the whole platform -- and launch day is
			   exactly when that happens. refs.websocket_status is addressed by the
			   payload uuid alone and needs no API secret, so this listens directly
			   and the server makes exactly two calls per link. */
			/* XRPL BROWSER EXTENSIONS. Two of them, differing only in how the
			   page asks for an address, so they share everything after that.

			   Each is DETECTED rather than offered blind -- Crossmark sets
			   window.xrpl.isCrossmark, GemWallet sets window.gemWallet -- and
			   detection runs on a short timer rather than once at load,
			   because an extension's content script can land after the page
			   script does and a one-shot check tells somebody who has it
			   installed that they do not. */
			/* Load a vendored UMD bundle once, by script tag, and resolve the
			   global it defines. Served from our own origin: these SDKs run in
			   a page that can reach a wallet, and import() cannot carry
			   Subresource Integrity, so a pinned CDN version protects against a
			   bad release but not a compromised CDN. See vendor/xrpl/README.md. */
			var vendorLoaded = {};
			function loadVendor(src, pick){
				var got = pick();
				if (got) return Promise.resolve(got);
				if (!vendorLoaded[src]) {
					vendorLoaded[src] = new Promise(function(resolve, reject){
						var el = document.createElement('script');
						el.src = src;
						el.onload = function(){
							var v = pick();
							v ? resolve(v) : reject(new Error('Wallet library loaded but exposed nothing.'));
						};
						el.onerror = function(){ reject(new Error('Could not load the wallet library.')); };
						document.head.appendChild(el);
					});
				}
				return vendorLoaded[src];
			}

			var XRPL_EXT = {
				crossmark: {
					el: 'crossmark-btn',
					has: function(){ return typeof window.xrpl !== 'undefined' && !!window.xrpl.isCrossmark; },
					/* Signs the same SignIn pseudo-transaction Xaman uses:
					   signature only, never submitted, no fee. */
					address: function(){
						return loadVendor('vendor/xrpl/crossmark-sdk-0.4.0.umd.js', function(){
							/* The UMD spreads its exports onto window, so the SDK
							   arrives as window.default. Ugly, and the price of a
							   single self-contained file over a 713-file graph. */
							return window.default && window.default.methods ? window.default : null;
						}).then(function(sdk){
							return sdk.methods.signInAndWait();
						}).then(function(r){
							return r && r.response && r.response.data && r.response.data.address;
						});
					}
				},
				gemwallet: {
					el: 'gemwallet-btn',
					has: function(){ return typeof window.gemWallet !== 'undefined'; },
					address: function(){
						return loadVendor('vendor/xrpl/gemwallet-api-3.8.0.umd.js', function(){
							return window.GemWalletApi || null;
						}).then(function(api){
							return api.getAddress();
						}).then(function(r){
							/* type is "response" or "reject" -- a decline is not
							   an error, it is an answer. */
							if (!r || r.type === 'reject') return null;
							return r.result && r.result.address;
						});
					}
				}
			};
			(function(){
				var tries = 0;
				var t = setInterval(function(){
					var left = 0;
					for (var k in XRPL_EXT) {
						var e = document.getElementById(XRPL_EXT[k].el);
						if (!e) continue;
						if (e.hidden && XRPL_EXT[k].has()) e.hidden = false;
						if (e.hidden) left++;
					}
					if (!left || ++tries > 20) clearInterval(t);   // ~5s, then quietly stop
				}, 250);
			})();

			/* A DIRECT-LEDGER PATH WAS BUILT AND THEN REMOVED. The browser can
			   talk to a Ledger over WebHID with no wallet provider in the way,
			   which answers a real complaint: Crossmark and GemWallet both make
			   you create a software wallet before they will touch a hardware
			   one.

			   It was pulled because the libraries could not be VERIFIED. They
			   ship no UMD build, so the only browser-ready form came from
			   esm.sh, which transpiles and rewrites rather than serving npm's
			   files -- meaning the vendored copy could never be checked against
			   npm's published hashes the way vendor/xrpl/ is. Asking anybody to
			   plug a hardware wallet into unverified third-party code is not a
			   liability worth carrying for convenience.

			   Recoverable: multichain.md §4g records what a verified vendoring
			   needs. Until then hardware holders add their Ledger inside
			   Crossmark or GemWallet, whose SDKs ARE verified against npm.
			   Removed 2026-09-28; the implementation is in git history. */

			/* WEBHID IS CHROME, EDGE AND OPERA ONLY -- not Firefox, not Safari.
			   Feature-detected, so the button does not appear where it cannot
			   work. */
			if (navigator.hid) {
				var lb = document.getElementById('ledger-btn');
				if (lb) lb.hidden = false;
			}

			function ledgerConnect(){
				xamanCancel();
				var p = document.getElementById('xaman-panel');
				var qr = document.getElementById('xaman-qr'), lk = document.getElementById('xaman-link');
				p.hidden = false; qr.hidden = true; lk.hidden = true;
				xamanSay('Unlock your Ledger and open the XRP app\u2026');

				/* NAME COLLISION, AND IT WOULD BE A NASTY ONE. xrpl-connect's UMD
				   wrapper reads window.xrpl expecting the xrpl.js LIBRARY, while
				   Crossmark injects window.xrpl as its own marker object. Loading
				   the bundle with Crossmark installed would hand it Crossmark's
				   marker and call it a library. Stash it, load, put it back. */
				var stashed = window.xrpl, hadStash = ('xrpl' in window);
				try { delete window.xrpl; } catch(e) { window.xrpl = undefined; }

				var restore = function(){
					if (hadStash) window.xrpl = stashed; else { try { delete window.xrpl; } catch(e){} }
				};

				loadVendor('vendor/xrpl/xrpl-connect-0.8.2.umd.js', function(){
					return (window.XRPLConnect && window.XRPLConnect.LedgerAdapter)
					     ? window.XRPLConnect : null;
				}).then(function(XC){
					restore();
					var adapter = new XC.LedgerAdapter();
					return adapter.connect().then(function(){
						/* Adapters expose the account differently across versions;
						   take whichever of these is actually populated rather
						   than guessing one and failing silently. */
						return adapter.address || adapter.account
						     || (adapter.getAddress && adapter.getAddress());
					});
				}).then(function(addr){
					if (addr && typeof addr === 'object') addr = addr.address || addr.account;
					if (!addr) throw new Error('The Ledger did not return an address.');
					xamanSay('Linking\u2026');
					var fd = new FormData();
					fd.append('address', addr); fd.append('via', 'ledger');
					return fetch('ajax/xrpl-link.php',
						{method:'POST', body:fd, credentials:'same-origin'});
				}).then(function(r){ return r.text(); })
				.then(function(t){
					var res; try { res = JSON.parse(t); } catch(e){ throw new Error(t.slice(0,120)); }
					if (!res.ok) { xamanSay(res.message || 'Could not link.'); return; }
					xamanSay(res.message || 'Wallet linked.');
					setTimeout(function(){ location.reload(); }, 1400);
				}).catch(function(e){
					restore();
					var m = (e && e.message) ? e.message : '';
					xamanSay(/denied|cancel|no device|not selected/i.test(m)
						? 'No device was selected.'
						: (m || 'Could not talk to the Ledger.'));
				});
			}

			function xrplExtConnect(which){
				var ext = XRPL_EXT[which]; if (!ext) return;
				var p = document.getElementById('xaman-panel');
				var qr = document.getElementById('xaman-qr'), lk = document.getElementById('xaman-link');
				xamanCancel(); p.hidden = false; qr.hidden = true; lk.hidden = true;
				xamanSay('Approve the sign-in in your wallet\u2026');
				/* Loaded on click, off a pinned CDN. No build step in this
				   codebase, and importing on click keeps ~60KB per SDK off
				   every page view for the many people who use neither. */
				ext.address().then(function(addr){
					if (!addr) throw new Error('Sign-in was not completed.');
					xamanSay('Linking\u2026');
					var fd = new FormData();
					fd.append('address', addr); fd.append('via', which);
					return fetch('ajax/xrpl-link.php',
						{method:'POST', body:fd, credentials:'same-origin'});
				}).then(function(r){ return r.text(); })
				.then(function(t){
					var res; try { res = JSON.parse(t); } catch(e){ throw new Error(t.slice(0,120)); }
					if (!res.ok) { xamanSay(res.message || 'Could not link.'); return; }
					xamanSay(res.message || 'Wallet linked.');
					setTimeout(function(){ location.reload(); }, 1400);
				}).catch(function(e){
					xamanSay((e && e.message) ? e.message : 'Sign-in was not completed.');
				});
			}

			var xamanSock = null, xamanUuid = null, xamanPoll = null;
			function xamanSay(t){ var e=document.getElementById('xaman-msg'); if(e) e.textContent=t; }
			function xamanCancel(){
				if(xamanSock){ try{ xamanSock.close(); }catch(e){} xamanSock=null; }
				if(xamanPoll){ clearInterval(xamanPoll); xamanPoll=null; }
				xamanUuid=null;
				var p=document.getElementById('xaman-panel'); if(p) p.hidden=true;
			}
			function xamanConnect(){
				var p=document.getElementById('xaman-panel');
				var qr=document.getElementById('xaman-qr'), lk=document.getElementById('xaman-link');
				xamanCancel(); p.hidden=false; qr.hidden=true; lk.hidden=true;
				xamanSay('Creating a sign-in request\u2026');
				var fd=new FormData(); fd.append('do','start');
				fetch('ajax/xaman-start.php',{method:'POST',body:fd,credentials:'same-origin'})
					.then(function(r){ return r.text(); })
					.then(function(t){
						var res; try{ res=JSON.parse(t); }catch(e){ throw new Error(t.slice(0,120)); }
						if(!res.ok) throw new Error(res.message||'Could not start.');
						xamanUuid=res.uuid;
						if(res.qr){ qr.src=res.qr; qr.hidden=false; }
						if(res.link){ lk.href=res.link; lk.hidden=false; }
						xamanSay('Scan with Xaman, or open it on this device.');
						xamanListen(res.websocket);
					})
					.catch(function(e){ xamanSay(e.message||'Could not reach Xaman.'); });
			}
			function xamanListen(url){
				/* A slow 5s poll runs alongside as a fallback -- some networks block
				   wss entirely, and at that rate even the fallback is cheap. */
				xamanPoll = setInterval(function(){ xamanFinish(true); }, 5000);
				if(!url || typeof WebSocket==='undefined') return;
				try{ xamanSock = new WebSocket(url); }catch(e){ return; }
				xamanSock.onmessage = function(ev){
					var m; try{ m=JSON.parse(ev.data); }catch(e){ return; }
					/* Keepalives carry expires_in_seconds. Expiry here is a SCAN
					   deadline, not a resolution deadline -- somebody who already
					   opened the prompt can still finish -- so this offers a fresh
					   code rather than declaring failure. */
					if(m.expired){ xamanSay('That code expired. Tap Connect again for a new one.'); return; }
					if(typeof m.signed !== 'undefined' || m.payload_uuidv4) xamanFinish(false);
				};
			}
			function xamanFinish(quiet){
				if(!xamanUuid) return;
				if(!quiet) xamanSay('Confirming\u2026');
				var fd=new FormData(); fd.append('uuid',xamanUuid);
				fetch('ajax/xaman-done.php',{method:'POST',body:fd,credentials:'same-origin'})
					.then(function(r){ return r.text(); })
					.then(function(t){
						var res; try{ res=JSON.parse(t); }catch(e){ return; }
						if(res.retry) return;               // still pending; the poll or socket retries
						if(!res.ok){ xamanSay(res.message||'Sign-in failed.'); xamanCancel(); return; }
						xamanSay(res.message||'Wallet linked.');
						xamanCancel();
						setTimeout(function(){ location.reload(); }, 1200);
					})
					.catch(function(){ /* a dropped confirm is covered by the next tick */ });
			}
			</script>
			<?php endif; ?>
			<?php if(isset($_SESSION['userData']['user_id'])): ?>
			<div class="wallet-modal-refresh">
				<form id="refreshWallet" action="<?php echo basename($_SERVER['PHP_SELF']); ?>" method="post">
					<input type="hidden" name="refresh" value="refresh">
					<button type="submit" class="wallet-refresh-btn">&#8635; Refresh Connected Wallet(s)</button>
				</form>
			</div>
			<?php endif; ?>
		</div>

		<!-- Hidden address form for wallet submission -->
		<form id="addressForm" action="<?php echo basename($_SERVER['PHP_SELF']); ?>" method="post" style="display:none">
			<input type="hidden" id="wallet" name="wallet" value="">
			<input type="hidden" id="address" name="address" value="">
			<input type="hidden" id="stakeaddress" name="stakeaddress" value="">
			<input type="submit" value="Submit" style="display:none;">
		</form>

		<!-- App-version update banner: appears when /staking/version.php returns a
		     mtime newer than the one rendered into the page. Auto-reloads on
		     visibility change after the user has been away. Style is intentionally
		     compact so it doesn't fight the navbar for attention. -->
		<style>
		#app-version-banner {
			position: fixed;
			top: env(safe-area-inset-top, 0px);
			left: 0; right: 0;
			z-index: 99990;
			display: flex;
			align-items: center;
			gap: 10px;
			padding: 10px 14px;
			background: linear-gradient(90deg, #002f44 0%, #165777 100%);
			border-bottom: 1px solid rgba(0,200,160,.5);
			color: #e8eaed;
			font-size: .85rem;
			box-shadow: 0 2px 12px rgba(0,0,0,.35);
			transform: translateY(-110%);
			transition: transform .35s cubic-bezier(.2,.8,.2,1);
		}
		#app-version-banner.show { transform: translateY(0); }
		#app-version-banner .avb-msg { flex: 1; line-height: 1.3; }
		#app-version-banner .avb-refresh {
			background: #00c8a0; color: #0a0a0a;
			border: 0; border-radius: 6px;
			padding: 7px 12px;
			font-weight: 600; font-size: .82rem;
			cursor: pointer;
		}
		#app-version-banner .avb-dismiss {
			background: transparent; border: 0; color: rgba(255,255,255,.6);
			font-size: 1.2rem; line-height: 1; cursor: pointer; padding: 4px 6px;
		}
		#app-version-banner .avb-dismiss:hover { color: #fff; }

		/* Pull-to-refresh indicator — only visible inside an installed PWA where
		   the browser's native pull-to-refresh is gone. Sits below the safe area
		   so it doesn't collide with the OS status bar. */
		#ptr-indicator {
			position: fixed;
			top: env(safe-area-inset-top, 0px);
			left: 0; right: 0;
			height: 3px;
			z-index: 99989;
			pointer-events: none;
			background: rgba(0,200,160,.12);
			opacity: 0;
			transition: opacity .15s;
		}
		#ptr-indicator .ptr-bar {
			height: 100%;
			width: 0%;
			background: #00c8a0;
			box-shadow: 0 0 8px rgba(0,200,160,.55);
			transition: width .08s linear;
		}
		#ptr-indicator.armed .ptr-bar { background: #00ffc8; }
		#ptr-indicator.refreshing {
			opacity: 1;
		}
		#ptr-indicator.refreshing .ptr-bar {
			animation: ptr-loading 1.4s linear infinite;
			width: 40%;
		}
		@keyframes ptr-loading {
			0%   { transform: translateX(-100%); width: 30%; }
			50%  { width: 60%; }
			100% { transform: translateX(250%); width: 30%; }
		}
		</style>

		<div id="app-version-banner" role="status" aria-live="polite" style="display:none;">
			<span class="avb-msg">A new version of Skulliance is available.</span>
			<button type="button" class="avb-refresh">Refresh</button>
			<button type="button" class="avb-dismiss" aria-label="Dismiss">&times;</button>
		</div>

		<div id="ptr-indicator"><div class="ptr-bar"></div></div>

		<script>
		(function(){
			// === App version auto-refresh ===
			var meta = document.querySelector('meta[name="app-version"]');
			if (meta) {
				var currentVersion = (meta.getAttribute('content') || '').trim();
				var POLL_MS = 5 * 60 * 1000; // 5 minutes
				var FIRST_CHECK_MS = 30 * 1000; // first check 30s after load
				var mismatchKnown = false;
				var banner = document.getElementById('app-version-banner');

				function showBanner() {
					if (!banner) return;
					banner.style.display = 'flex';
					// Force a reflow so the transform transition kicks in
					void banner.offsetHeight;
					banner.classList.add('show');
				}
				function hideBanner() {
					if (!banner) return;
					banner.classList.remove('show');
				}
				if (banner) {
					banner.querySelector('.avb-refresh').addEventListener('click', function(){
						window.location.reload();
					});
					banner.querySelector('.avb-dismiss').addEventListener('click', hideBanner);
				}

				function checkVersion() {
					try {
						fetch('/staking/version.php?_=' + Date.now(), { cache: 'no-store' })
							.then(function(r){ return r.ok ? r.text() : null; })
							.then(function(v){
								if (!v) return;
								v = v.trim();
								if (!v || v === currentVersion) return;
								if (mismatchKnown) return;
								mismatchKnown = true;
								if (document.visibilityState === 'visible') {
									showBanner();
								}
							})
							.catch(function(){});
					} catch (e) {}
				}

				document.addEventListener('visibilitychange', function() {
					if (document.visibilityState !== 'visible') return;
					if (mismatchKnown) {
						// User came back to a page with stale code — silent reload
						window.location.reload();
					} else {
						// Opportunistic re-check on focus
						checkVersion();
					}
				});

				setTimeout(checkVersion, FIRST_CHECK_MS);
				setInterval(checkVersion, POLL_MS);
			}

			// === Pull-to-refresh (PWA standalone only) ===
			// Native pull-to-refresh is disabled when the page is launched as
			// an installed PWA, so we add our own. In a regular browser tab the
			// native gesture still works, so we don't intercept there.
			var inStandalone = (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
				|| window.navigator.standalone === true;
			if (!inStandalone) return;
			// Skip PTR on game pages where touchstart/move are needed for
			// gameplay (tile dragging in Skull Swap, etc.). Pages can opt out
			// by adding their basename here or by setting body.dataset.noPtr.
			var skipPtrPages = ['skullswap.php'];
			var currentPage = (location.pathname.split('/').pop() || '').toLowerCase();
			if (skipPtrPages.indexOf(currentPage) !== -1 || document.body.dataset.noPtr === '1') return;

			var ptr = document.getElementById('ptr-indicator');
			if (!ptr) return;
			var ptrBar = ptr.querySelector('.ptr-bar');

			var THRESHOLD = 80;
			var MAX_PULL = 160;
			var startY = 0, currentY = 0, pulling = false, refreshing = false;

			function onTouchStart(e) {
				if (refreshing) return;
				if (window.scrollY > 0) return;
				if (e.touches.length !== 1) return;
				startY = e.touches[0].clientY;
				currentY = startY;
				pulling = true;
			}
			function onTouchMove(e) {
				if (!pulling || refreshing) return;
				currentY = e.touches[0].clientY;
				var delta = currentY - startY;
				if (delta <= 0) {
					ptr.style.opacity = 0;
					ptr.classList.remove('armed');
					ptrBar.style.width = '0%';
					return;
				}
				// Damping past threshold so the user gets resistance feedback
				var pull = Math.min(delta, MAX_PULL);
				var pct = Math.min(pull / THRESHOLD, 1);
				ptr.style.opacity = String(0.4 + pct * 0.6);
				ptrBar.style.width = (pct * 100) + '%';
				if (pull >= THRESHOLD) ptr.classList.add('armed');
				else ptr.classList.remove('armed');
			}
			function onTouchEnd() {
				if (!pulling || refreshing) { pulling = false; return; }
				var delta = currentY - startY;
				pulling = false;
				if (delta >= THRESHOLD) {
					refreshing = true;
					ptr.classList.remove('armed');
					ptr.classList.add('refreshing');
					// Brief visual feedback before navigation tears down the page
					setTimeout(function(){ window.location.reload(); }, 250);
				} else {
					ptr.style.opacity = 0;
					ptrBar.style.width = '0%';
				}
			}

			document.addEventListener('touchstart', onTouchStart, { passive: true });
			document.addEventListener('touchmove', onTouchMove, { passive: true });
			document.addEventListener('touchend', onTouchEnd, { passive: true });
			document.addEventListener('touchcancel', onTouchEnd, { passive: true });
		})();
		</script>

		<?php if(isset($name)): ?>
		<!-- PWA Install Prompt (mobile only, dismissable, logged-in only) -->
		<style>
		#pwa-install-overlay {
			position: fixed; inset: 0;
			background: rgba(0,0,0,0.72);
			z-index: 99998;
			display: none;
			align-items: flex-end;
			justify-content: center;
			animation: pwa-fade-in .25s ease;
		}
		#pwa-install-overlay.show { display: flex; }
		@keyframes pwa-fade-in { from { opacity: 0; } to { opacity: 1; } }
		@keyframes pwa-slide-up { from { transform: translateY(100%); } to { transform: translateY(0); } }
		#pwa-install-panel {
			width: 100%;
			max-width: 480px;
			background: linear-gradient(180deg, #1a1a1a 0%, #121212 100%);
			color: #e8eaed;
			border-top: 2px solid #00c8a0;
			border-radius: 16px 16px 0 0;
			padding: 20px 22px 28px;
			box-shadow: 0 -8px 30px rgba(0,200,160,.15);
			animation: pwa-slide-up .3s cubic-bezier(.2,.8,.2,1);
			max-height: 90vh;
			overflow-y: auto;
		}
		#pwa-install-panel .pwa-header {
			display: flex; align-items: center; gap: 12px; margin-bottom: 14px;
		}
		#pwa-install-panel .pwa-icon {
			width: 48px; height: 48px; border-radius: 12px;
			background: #161616; padding: 4px; flex-shrink: 0;
		}
		#pwa-install-panel .pwa-title {
			font-size: 1.05rem; font-weight: 600; line-height: 1.2;
		}
		#pwa-install-panel .pwa-subtitle {
			font-size: .78rem; color: rgba(255,255,255,.55); margin-top: 2px;
		}
		#pwa-install-panel .pwa-close {
			margin-left: auto;
			background: transparent; border: 0; color: rgba(255,255,255,.5);
			font-size: 1.4rem; cursor: pointer; padding: 4px 8px; line-height: 1;
		}
		#pwa-install-panel .pwa-close:hover { color: #fff; }
		#pwa-install-panel .pwa-blurb {
			font-size: .85rem; color: rgba(255,255,255,.75);
			margin-bottom: 16px; line-height: 1.45;
		}
		#pwa-install-panel ol {
			margin: 0 0 18px; padding-left: 0; list-style: none;
			counter-reset: pwa-step;
		}
		#pwa-install-panel ol li {
			counter-increment: pwa-step;
			position: relative;
			padding: 10px 0 10px 38px;
			font-size: .9rem;
			line-height: 1.4;
			border-bottom: 1px solid rgba(255,255,255,.05);
		}
		#pwa-install-panel ol li:last-child { border-bottom: 0; }
		#pwa-install-panel ol li::before {
			content: counter(pwa-step);
			position: absolute; left: 0; top: 10px;
			width: 26px; height: 26px;
			background: #00c8a0; color: #0a0a0a;
			border-radius: 50%;
			display: flex; align-items: center; justify-content: center;
			font-size: .78rem; font-weight: 700;
		}
		#pwa-install-panel .pwa-glyph {
			display: inline-block;
			vertical-align: middle;
			margin: 0 4px;
			padding: 2px 6px;
			background: rgba(0,200,160,.15);
			border: 1px solid rgba(0,200,160,.3);
			border-radius: 5px;
			font-size: .82rem;
			color: #00c8a0;
		}
		#pwa-install-panel .pwa-actions {
			display: flex; gap: 10px; flex-wrap: wrap;
		}
		#pwa-install-panel .pwa-btn {
			flex: 1; min-width: 120px;
			padding: 11px 14px;
			border-radius: 8px; border: 0;
			font-size: .88rem; font-weight: 600;
			cursor: pointer;
			transition: opacity .15s;
		}
		#pwa-install-panel .pwa-btn:hover { opacity: .85; }
		#pwa-install-panel .pwa-btn-primary {
			background: #00c8a0; color: #0a0a0a;
		}
		#pwa-install-panel .pwa-btn-secondary {
			background: rgba(255,255,255,.08); color: #e8eaed;
		}
		#pwa-install-panel .pwa-btn-ghost {
			background: transparent; color: rgba(255,255,255,.45);
			font-size: .78rem;
			padding: 6px 0;
			text-align: center;
			width: 100%;
			margin-top: 12px;
		}
		#pwa-install-panel .pwa-btn-ghost:hover { color: #fff; }
		@media (min-width: 600px) {
			#pwa-install-overlay { align-items: center; }
			#pwa-install-panel { border-radius: 16px; border-top: 0; border: 2px solid #00c8a0; }
		}
		</style>

		<div id="pwa-install-overlay" role="dialog" aria-modal="true" aria-labelledby="pwa-install-title">
			<div id="pwa-install-panel">
				<div class="pwa-header">
					<img src="/staking/pwa/icon-192.png" alt="" class="pwa-icon">
					<div>
						<div id="pwa-install-title" class="pwa-title">Install Skulliance</div>
						<div class="pwa-subtitle">Get a fullscreen app on your home screen</div>
					</div>
					<button class="pwa-close" type="button" aria-label="Dismiss" data-pwa-action="later">&times;</button>
				</div>
				<div class="pwa-blurb">No app store needed &mdash; this site can be installed straight to your home screen for one-tap access, fullscreen view, and faster loads.</div>

				<!-- iOS instructions (Safari) -->
				<ol id="pwa-steps-ios" style="display:none">
					<li>Tap the <span class="pwa-glyph">Share &#x2191;</span> button at the bottom of Safari.</li>
					<li>Scroll and tap <span class="pwa-glyph">Add to Home Screen</span>.</li>
					<li>Tap <span class="pwa-glyph">Add</span> in the top right.</li>
				</ol>

				<!-- iOS instructions (non-Safari: Chrome / Firefox / Edge / Opera / Brave / in-app browsers / etc.) -->
				<div id="pwa-steps-ios-switch" style="display:none">
					<div class="pwa-blurb" style="background:rgba(255,170,0,.08); border:1px solid rgba(255,170,0,.25); border-radius:8px; padding:10px 12px; margin-bottom:14px;">
						<strong style="color:#ffaa00;">Heads up:</strong> Adding to Home Screen on iPhone only works in <strong>Safari</strong> &mdash; not <span id="pwa-ios-browser-name">your current browser</span>. Open this page in Safari first, then come back to install.
					</div>
					<button class="pwa-btn pwa-btn-primary" type="button" id="pwa-open-in-safari-btn" style="display:none; width:100%; margin-bottom:14px;">Open in Safari &rarr;</button>
					<ol>
						<li>Tap the <span class="pwa-glyph">Share &#x2191;</span> button (or <span class="pwa-glyph">&#x22EF;</span> menu) in this browser.</li>
						<li>Tap <span class="pwa-glyph">Open in Safari</span>.</li>
						<li>Once the page loads in Safari, tap <span class="pwa-glyph">Share &#x2191;</span> &rarr; <span class="pwa-glyph">Add to Home Screen</span>.</li>
					</ol>
					<button class="pwa-btn pwa-btn-secondary" type="button" id="pwa-copy-link-btn" style="width:100%; margin-bottom:10px;">Copy link to paste in Safari</button>
				</div>

				<!-- Android instructions (fallback when beforeinstallprompt unavailable) -->
				<ol id="pwa-steps-android" style="display:none">
					<li>Tap the <span class="pwa-glyph">&#x22EE; menu</span> in your browser's top-right corner.</li>
					<li>Tap <span class="pwa-glyph">Install app</span> or <span class="pwa-glyph">Add to Home screen</span>.</li>
					<li>Tap <span class="pwa-glyph">Install</span> to confirm.</li>
				</ol>

				<!-- Android native install prompt -->
				<div id="pwa-native-android" style="display:none">
					<p class="pwa-blurb" style="margin-bottom:14px;">Tap install to add Skulliance to your home screen.</p>
				</div>

				<div class="pwa-actions">
					<button class="pwa-btn pwa-btn-primary" id="pwa-native-install-btn" type="button" style="display:none" data-pwa-action="install">Install</button>
					<button class="pwa-btn pwa-btn-primary" id="pwa-got-it-btn" type="button" data-pwa-action="later">Got it &mdash; remind me in 7 days</button>
				</div>
			</div>
		</div>

		<script>
		(function(){
			var STORAGE_KEY = 'pwa-prompt-dismissed-until';
			var REMIND_DAYS = 7;
			var SHOW_DELAY_MS = 2500;

			function isStandalone() {
				return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches)
					|| window.navigator.standalone === true
					|| document.referrer.indexOf('android-app://') === 0;
			}

			function detectPlatform() {
				var ua = navigator.userAgent || '';
				if (/iPad|iPhone|iPod/.test(ua) && !window.MSStream) return 'ios';
				if (/Android/i.test(ua)) return 'android';
				return null;
			}

			// Returns Safari label or third-party iOS browser name. iOS PWA install
			// only works in Safari proper — every third-party iOS browser uses
			// WebKit but cannot Add to Home Screen. Note: Brave on iOS spoofs
			// Safari's UA for privacy, so it can only be detected via the async
			// navigator.brave.isBrave() API — see resolveIOSBrowser.
			function detectIOSBrowserSync() {
				var ua = navigator.userAgent || '';
				if (/CriOS\//.test(ua)) return 'Chrome';
				if (/FxiOS\//.test(ua)) return 'Firefox';
				if (/EdgiOS\//.test(ua)) return 'Edge';
				if (/OPiOS\//.test(ua)) return 'Opera';
				if (/DuckDuckGo/.test(ua)) return 'DuckDuckGo';
				if (/GSA\//.test(ua)) return 'Google App';
				if (/FBAN|FBAV|Instagram|Twitter|Snapchat|Line\/|musical_ly/.test(ua)) return 'in-app browser';
				// Genuine Safari has Safari/ token but none of the above
				if (/Safari\//.test(ua) && /Version\//.test(ua)) return 'Safari';
				return 'this browser';
			}

			// Resolves the iOS browser name asynchronously, upgrading a tentative
			// "Safari" detection to "Brave" when navigator.brave.isBrave() is true.
			// All other browsers resolve immediately via the sync UA check.
			function resolveIOSBrowser(callback) {
				var sync = detectIOSBrowserSync();
				if (sync === 'Safari' && navigator.brave && typeof navigator.brave.isBrave === 'function') {
					try {
						var p = navigator.brave.isBrave();
						if (p && typeof p.then === 'function') {
							p.then(function(isBrave) { callback(isBrave ? 'Brave' : 'Safari'); },
								   function() { callback('Safari'); });
							return;
						}
					} catch (e) {}
				}
				callback(sync);
			}

			// Only in-app browsers reliably honor x-safari-https://. Chrome iOS,
			// Firefox iOS, Edge iOS, Opera iOS, Brave iOS, DuckDuckGo, etc. all
			// either intercept the scheme or stay in their own app, so showing a
			// magic button there would be misleading.
			function iosBrowserSupportsSafariScheme(name) {
				return name === 'in-app browser';
			}

			function isDismissed() {
				try {
					var until = parseInt(localStorage.getItem(STORAGE_KEY) || '0', 10);
					return until && Date.now() < until;
				} catch (e) { return false; }
			}

			function setDismissed(days) {
				try {
					var until = Date.now() + days * 24 * 60 * 60 * 1000;
					localStorage.setItem(STORAGE_KEY, String(until));
				} catch (e) {}
			}

			var deferredPrompt = null;
			window.addEventListener('beforeinstallprompt', function(e) {
				e.preventDefault();
				deferredPrompt = e;
				var btn = document.getElementById('pwa-native-install-btn');
				var gotIt = document.getElementById('pwa-got-it-btn');
				var androidSteps = document.getElementById('pwa-steps-android');
				var nativeBlurb = document.getElementById('pwa-native-android');
				if (btn) btn.style.display = '';
				if (gotIt) gotIt.style.display = 'none';
				if (androidSteps) androidSteps.style.display = 'none';
				if (nativeBlurb) nativeBlurb.style.display = '';
			});

			window.addEventListener('appinstalled', function() {
				setDismissed(365 * 10);
				var ov = document.getElementById('pwa-install-overlay');
				if (ov) ov.classList.remove('show');
			});

			document.addEventListener('click', function(e) {
				var btn = e.target.closest('[data-pwa-action]');
				if (!btn) return;
				var action = btn.getAttribute('data-pwa-action');
				var overlay = document.getElementById('pwa-install-overlay');

				if (action === 'install' && deferredPrompt) {
					deferredPrompt.prompt();
					deferredPrompt.userChoice.then(function(choice) {
						// Either way, dismiss for 7 days. If install succeeded
						// the appinstalled handler upgrades to a 10-year flag.
						setDismissed(REMIND_DAYS);
						deferredPrompt = null;
						if (overlay) overlay.classList.remove('show');
					});
					return;
				}

				// All other dismissals (X close, Got it) re-nag in 7 days.
				// Permanent suppression only happens via successful install
				// (appinstalled event) or detected standalone display mode.
				if (action === 'later') setDismissed(REMIND_DAYS);

				if (overlay) overlay.classList.remove('show');
			});

			function maybeShow() {
				if (isStandalone() || isDismissed()) return;
				var platform = detectPlatform();
				if (!platform) return;
				var overlay = document.getElementById('pwa-install-overlay');
				if (!overlay) return;
				if (platform === 'ios') {
					resolveIOSBrowser(function(iosBrowser) {
						if (iosBrowser === 'Safari') {
							document.getElementById('pwa-steps-ios').style.display = '';
						} else {
							document.getElementById('pwa-ios-browser-name').textContent = iosBrowser;
							document.getElementById('pwa-steps-ios-switch').style.display = '';
							document.getElementById('pwa-install-title').textContent = 'Open in Safari to install';
							if (iosBrowserSupportsSafariScheme(iosBrowser)) {
								document.getElementById('pwa-open-in-safari-btn').style.display = '';
							}
						}
						setTimeout(function(){ overlay.classList.add('show'); }, SHOW_DELAY_MS);
					});
					return;
				} else if (platform === 'android') {
					if (deferredPrompt) {
						document.getElementById('pwa-native-android').style.display = '';
						document.getElementById('pwa-native-install-btn').style.display = '';
						document.getElementById('pwa-got-it-btn').style.display = 'none';
					} else {
						document.getElementById('pwa-steps-android').style.display = '';
					}
				}
				setTimeout(function(){ overlay.classList.add('show'); }, SHOW_DELAY_MS);
			}

			function wireCopyLink() {
				var btn = document.getElementById('pwa-copy-link-btn');
				if (!btn) return;
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					var url = window.location.href;
					var done = function() {
						var orig = btn.textContent;
						btn.textContent = 'Link copied — paste in Safari';
						btn.style.background = 'rgba(0,200,160,.2)';
						setTimeout(function(){
							btn.textContent = orig;
							btn.style.background = '';
						}, 2200);
					};
					if (navigator.clipboard && navigator.clipboard.writeText) {
						navigator.clipboard.writeText(url).then(done, function(){
							window.prompt('Copy this URL, then open Safari and paste:', url);
						});
					} else {
						window.prompt('Copy this URL, then open Safari and paste:', url);
					}
				});
			}

			function wireOpenInSafari() {
				var btn = document.getElementById('pwa-open-in-safari-btn');
				if (!btn) return;
				btn.addEventListener('click', function(e) {
					e.stopPropagation();
					// x-safari-https:// scheme escapes most in-app browser WebViews
					// (Instagram, Facebook, Twitter, TikTok, Snapchat) and opens
					// the URL in Safari. Strips the https:// prefix and prepends
					// x-safari-https://.
					var url = window.location.href;
					var safariUrl = url.replace(/^https?:\/\//, 'x-safari-https://');
					window.location.href = safariUrl;
				});
			}

			function init() {
				wireCopyLink();
				wireOpenInSafari();
				maybeShow();
			}

			if (document.readyState === 'loading') {
				document.addEventListener('DOMContentLoaded', init);
			} else {
				init();
			}
		})();
		</script>
		<?php endif; ?>

<?php
/*
 * DHC FIGHTERS DROP MODAL, platform-wide.
 *
 * A daily-reward claim can happen from any page, so the reveal has to be
 * available everywhere rather than only on the nine game pages. The include
 * guards itself, so those pages including it directly stays harmless.
 */
if (is_file(__DIR__ . '/dhc-dropmodal.php')) include __DIR__ . '/dhc-dropmodal.php';
?>
