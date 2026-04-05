<!doctype html>
<html>
<head>
  <title>Skulliance</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
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
.nl-bar-wrap { width: 200px; height: 3px; background: rgba(255,255,255,.08); border-radius: 2px; overflow: hidden; }
.nl-bar      { height: 100%; background: #00c8a0; width: 0%; }
.nl-text     { font-size: .78rem; color: rgba(255,255,255,.35); letter-spacing: .1em; text-transform: uppercase; }
</style>
<div id="nav-loader">
    <div style="font-size:3rem;">&#x1F480;</div>
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
        el.style.display = 'flex';
        requestAnimationFrame(function(){ el.classList.add('active'); });
    }
    document.addEventListener('click', function(e) {
        var a = e.target.closest('a[href]');
        if (!a || e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
        var href = a.getAttribute('href');
        if (!href || href.charAt(0) === '#' || href.indexOf('javascript') === 0) return;
        // Only internal links
        var isInternal = href.charAt(0) === '/' || href.charAt(0) === '.' ||
                         (!href.match(/^https?:\/\//) || href.indexOf(window.location.hostname) !== -1);
        if (isInternal && a.target !== '_blank') showNavLoader();
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

		  <!-- Play -->
		  <div class="nav-dropdown navbar-first">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Play</span>
		    <div class="nav-dropdown-menu">
		      <a href="missions.php">Missions</a>
		      <a href="realms.php">Realms</a>
		      <a href="gauntlets.php">Gauntlets</a>
		      <a href="merchandising.php">Merch</a>
		      <a href="monstrocity.php" target="_blank">Match 3 RPG</a>
		      <a href="monstrocity.php#boss" target="_blank">Boss Battles</a>
		      <a href="skullswap.php">Skull Swap</a>
		      <a href="https://www.madballs.net/drop-ship" target="_blank">Drop Ship</a>
		    </div>
		  </div>

		  <!-- NFTs -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">NFTs</span>
		    <div class="nav-dropdown-menu">
		      <a href="dashboard.php">Dashboard</a>
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
