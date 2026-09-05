<!doctype html>
<html>
<head>
  <title>Drop Ship</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <!-- TEMPORARILY reverted to Google's CDN (2026-09-05): a self-hosted
       dist/jquery-3.6.3.min.js reference was live here, but that file was
       never actually uploaded to the server -- 404'd, breaking every
       $-dependent script on the page (dropship.js included). The intent
       (mirroring Skulliance's own header.php: Brave's Shields blocks
       ajax.googleapis.com for some stakers) is still worth doing, but
       needs the actual file deployed to dropship/dist/ first -- swap
       this back to <script src="dist/jquery-3.6.3.min.js"></script>
       once that file is confirmed live (curl it, don't assume). -->
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
  <!--<link href="dist/output.css" rel="stylesheet">-->
  <!-- filemtime()-based cache-bust, not rand(0,999) -- a random 0-999 value
       can (and, live-tested, DID) collide with one already sitting in a
       browser's cache from before a real CSS change shipped, silently
       serving stale styles. filemtime() only changes when the file
       actually changes, so a fresh deploy always gets a fresh URL. Same
       pattern the main site already uses for its PWA version banner
       (header.php's own app-version meta tag). -->
  <link href="dist/flexbox.css?var=<?php echo @filemtime(__DIR__ . '/dist/flexbox.css') ?: rand(0,999); ?>" rel="stylesheet">
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
  // Toggle burger menu -- ported from Skulliance's own header.php verbatim
  // (same icon swap / show-menu class dance), so the nav is hidden by
  // default on mobile instead of always rendered full-height like before.
  // Reuses Skulliance's own menu/close icons (no local copies exist in
  // dropship/images) -- same precedent as instructions.php's DREAD/MOON
  // icons pointing at /staking/icons/ directly.
  function toggleMenu(){
    if(document.getElementById('burger-icon').src.indexOf('close.png') === -1){
      document.getElementById('burger-icon').src = "https://www.skulliance.io/staking/images/close.png";
      document.getElementById("navbar").classList.add('show-menu');
    }else{
      document.getElementById('burger-icon').src = "https://www.skulliance.io/staking/images/menu.png";
      document.getElementById("navbar").classList.remove('show-menu');
    }
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
		<div id="burger-menu">
			<img id="burger-icon" onclick="javascript:toggleMenu();" src="https://www.skulliance.io/staking/images/menu.png"/>
		</div>
		<!-- Navigation Bar -->
		<!-- Grouped into dropdowns mirroring Skulliance's own nav (Play/NFTs/
		     Stats/Account, same class names and toggleDropdown() mechanism --
		     see staking's own header.php + dist/flexbox.css) instead of one
		     long jammed-full row of top-level links. Hidden by default on
		     mobile now (id="navbar" + #burger-menu above), same as
		     Skulliance's own -- it used to render full-height and always
		     visible on narrow screens instead of collapsing behind a
		     hamburger icon. -->
		<div class="navbar" id="navbar">
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

		  <?php if($_SESSION["userData"]["dropship_project_id"] == 1 || $_SESSION["userData"]["dropship_project_id"] == 2 || $_SESSION["userData"]["dropship_project_id"] == 4){?>
		  <a href="instructions.php">How to Play</a>
		  <?php } ?>
		  <a href="../dashboard.php">&larr; Back to Skulliance</a>
		</div>
		<!-- Mobile bottom quick-links bar (icons/scoreboard.png etc. all
		     curl-verified live) -- same visual pattern as Missions/Realms'
		     own #quick-menu, but real links rather than in-page section
		     toggling, since these four are genuinely different pages/anchors
		     (Stats is a separate page, not a section of this one). Shown on
		     every Drop Ship page via header.php, mobile-only via CSS.
		     Which icon is "selected" has to be decided client-side, not in
		     PHP -- Barracks/Armory are hash anchors on this same
		     dashboard.php page, and a URL fragment is never sent to the
		     server at all, so $_SERVER['PHP_SELF'] alone can't tell
		     "#barracks" apart from plain dashboard.php. -->
		<div id="quick-menu">
			<a href="dashboard.php" title="Game"><img src="icons/dropship.png" data-page="dashboard.php" data-hash=""/></a>
			<a href="dashboard.php#barracks" title="<?php echo evaluateText("Barracks");?>"><img src="icons/supersoldier.png" data-page="dashboard.php" data-hash="barracks"/></a>
			<a href="dashboard.php#armory" title="<?php echo evaluateText("Armory");?>"><img src="icons/shield.png" data-page="dashboard.php" data-hash="armory"/></a>
			<a href="leaderboards.php" title="Stats"><img src="icons/scoreboard.png" data-page="leaderboards.php" data-hash=""/></a>
		</div>
		<script>
		(function(){
			var page = location.pathname.split('/').pop();
			var hash = location.hash.replace('#', '');
			document.querySelectorAll('#quick-menu img').forEach(function(img){
				img.classList.toggle('selected', img.dataset.page === page && img.dataset.hash === hash);
			});
		})();
		</script>
		<button onclick="topFunction()" id="back-to-top-button" title="Go to top">^</button>
<?php 
if($_SESSION['userData']['dropship_project_id'] == 4){?>
	<script type='text/javascript'>document.body.style.backgroundImage = "url('/staking/dropship/oculus-lounge/oculusloungebackground.png')";</script>
<?php }
?>