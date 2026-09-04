<!doctype html>
<html>
<head>
  <title>Drop Ship</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
  <!--<link href="dist/output.css" rel="stylesheet">-->
  <link href="dist/flexbox.css?var=<?php echo rand(0,999); ?>" rel="stylesheet">
  <script>
  // Toggle dropdown -- ported from Skulliance's own header.php verbatim.
  // Above 700px the CSS opens a dropdown on :hover on its own (see
  // dist/flexbox.css); this is what makes tapping the trigger work at any
  // width, including the narrow case where :hover isn't a real gesture.
  function toggleDropdown(el){
    var menu = el.nextElementSibling;
    var isOpen = menu.classList.contains('open');
    document.querySelectorAll('.nav-dropdown-menu.open').forEach(function(m){ m.classList.remove('open'); });
    if(!isOpen) menu.classList.add('open');
  }
  </script>
  <?php
  if($_SESSION["userData"]["dropship_project_id"] == 4){?>
	<?php if(str_contains($_SERVER['PHP_SELF'], "battles.php")){?>
	<style>
		.button{
			filter: hue-rotate(145deg);
		}
	</style>
	  <?php }else{ ?>
		<style>
			.button, .small-button{
				filter: hue-rotate(145deg);
			}
			.credit{
				filter: hue-rotate(45deg);
			}
			.battle-credit{
				filter: hue-rotate(145deg);
			}
		</style>
	  <?php } ?>
  <?php } ?>
</head>
<body>
	<div id="loading" <?php echo (isset($_POST['run']) || isset($_POST['instant_replay']))?'style="display:none"':""; ?>>
	  <img id="loading-image" src="<?php echo $prefix;?>images/loading.gif" alt="Loading..." />
	</div>
	<div class="container">
		<!-- Navigation Bar -->
		<!-- Grouped into dropdowns mirroring Skulliance's own nav (Play/NFTs/
		     Stats/Account, same class names and toggleDropdown() mechanism --
		     see staking's own header.php + dist/flexbox.css) instead of one
		     long jammed-full row of top-level links. -->
		<div class="navbar">
	      <img class="rounded-full" src="<?php echo $avatar_url?>" />
		  <a href="https://discord.gg/DHbGU9ZDyG"><?php echo $name;?></a>

		  <!-- Play -->
		  <div class="nav-dropdown navbar-first">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Play</span>
		    <div class="nav-dropdown-menu">
		      <a href="dashboard.php">Dashboard</a>
		      <a href="dashboard.php#barracks"><?php echo evaluateText("Barracks");?></a>
		      <a href="dashboard.php#armory"><?php echo evaluateText("Armory");?></a>
		      <a href="battles.php"><?php echo evaluateText("Battles");?></a>
		      <?php if($_SESSION["userData"]["dropship_project_id"] == 4){?>
		      <a href="discoin.php">Buy Temp VIP</a>
		      <?php } ?>
		    </div>
		  </div>

		  <!-- NFTs -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">NFTs</span>
		    <div class="nav-dropdown-menu">
		      <a href="soldiers.php"><?php echo evaluateText("Soldiers");?></a>
		    </div>
		  </div>

		  <!-- Stats -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Stats</span>
		    <div class="nav-dropdown-menu">
		      <a href="leaderboards.php">Leaderboards</a>
		      <a href="achievements.php">Achievements</a>
		    </div>
		  </div>

		  <!-- Account -->
		  <div class="nav-dropdown">
		    <span class="nav-dropdown-trigger" onclick="toggleDropdown(this)">Account</span>
		    <div class="nav-dropdown-menu">
		      <a href="transactions.php">Transactions</a>
		    </div>
		  </div>

		  <a href="../dashboard.php">&larr; Back to Skulliance</a>
		</div>
		<button onclick="topFunction()" id="back-to-top-button" title="Go to top">^</button>
<?php 
if($_SESSION['userData']['dropship_project_id'] == 4){?>
	<script type='text/javascript'>document.body.style.backgroundImage = "url('/staking/dropship/oculus-lounge/oculusloungebackground.png')";</script>
<?php }
?>