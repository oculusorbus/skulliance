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
  <?php if(isset($extra_head)) echo $extra_head; ?>
</head>
<body>
	<div class="container">
		<div id="burger-menu">
			<img id="burger-icon" onclick="javascript:toggleMenu();" src="https://www.skulliance.io/staking/images/menu.png"/>
		</div>
		<!-- Navigation Bar -->
		<div class="navbar" id="navbar">
		  <?php if(isset($name)){?>
		  <?php if(isset($avatar_url)){?>
	      <img class="rounded-full" src="<?php echo $avatar_url?>" />
		  <?php } ?>
		  <a href="profile.php<?php echo (isset($name)) ? '?username='.urlencode($name) : ''; ?>"><?php echo (isset($name))?$name:"";?></a>

		  <!-- Play -->
		  <div class="nav-dropdown navbar-first">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Play &#9660;</span>
		    <div class="nav-dropdown-menu">
		      <a href="missions.php">Missions</a>
		      <a href="realms.php">Realms</a>
		      <a href="monstrocity.php" target="_blank">Match 3 RPG</a>
		      <a href="skullswap.php">Skull Swap</a>
		      <a href="https://www.madballs.net/drop-ship" target="_blank">Drop Ship</a>
		    </div>
		  </div>

		  <!-- NFTs -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">NFTs &#9660;</span>
		    <div class="nav-dropdown-menu">
		      <a href="dashboard.php">Dashboard</a>
		      <a href="store.php">Store</a>
		      <a href="collections.php">Collections</a>
		      <a href="diamond-skulls.php">Diamond Skulls</a>
		      <a href="skulliverse.php">Skulliverse</a>
		    </div>
		  </div>

		  <!-- Stats -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Stats &#9660;</span>
		    <div class="nav-dropdown-menu">
		      <a href="profile.php<?php echo (isset($name)) ? '?username='.urlencode($name) : ''; ?>">Profile</a>
		      <a href="leaderboards.php">Leaderboards</a>
		    </div>
		  </div>

		  <!-- Account -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Account &#9660;</span>
		    <div class="nav-dropdown-menu">
		      <a href="transactions.php">Transactions</a>
		      <a href="wallets.php">Wallets</a>
		    </div>
		  </div>

		  <a href="logout.php">Logout</a>
		  <?php } ?>
		</div>
		<div id="revealPoint"></div>
		<button onclick="topFunction()" id="back-to-top-button" title="Go to top">^</button>
