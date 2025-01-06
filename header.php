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
  <script type="text/javascript">
	  // Toggle burger menu
	  function toggleMenu(){
	  	if(document.getElementById('burger-icon').src == "https://www.skulliance.io/staking/images/menu.png"){
	  	  	//document.getElementById('navbar').style.display='flex';
	  		document.getElementById('burger-icon').src = "https://www.skulliance.io/staking/images/close.png";
	  		document.getElementById("navbar").classList.add('show-menu');
	  		document.getElementById("navbar").classList.remove('hide-menu');
	  	}else{
	  	  	//document.getElementById('navbar').style.display='none';
	  		document.getElementById('burger-icon').src = "https://www.skulliance.io/staking/images/menu.png";
	  		document.getElementById("navbar").classList.add('hide-menu');
	  		document.getElementById("navbar").classList.remove('show-menu');
	  	}
	  }
  </script>
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
		  <a href="http://discord.gg/JqqBZBrph2"><?php echo (isset($name))?$name:"";?></a>
		  <a class="navbar-first" href="dashboard.php">Dashboard</a>
  		  <a href="missions.php">Missions</a>
		  <a href="realms.php">Realms</a>
		  <a href="store.php">Store</a>
		  <a href="leaderboards.php">Leaderboards</a>
		  <a href="collections.php">Collections</a>
		  <a href="transactions.php">Transactions</a>
  		  <a href="diamond-skulls.php">Diamond Skulls</a>
		  <a href="skulliverse.php">Skulliverse</a>
		  <a href="https://www.madballs.net/drop-ship" target="_blank">Drop Ship</a>
    	  <a href="wallets.php">Wallets</a>
		  <a href="logout.php">Logout</a>
		  <?php } ?>
		</div>
		<div id="revealPoint"></div>
		<button onclick="topFunction()" id="back-to-top-button" title="Go to top">^</button>
