<?php
/**
 * Customises the stock Storefront homepage template to include the sidebar and the boutique_before_homepage_content hook.
 *
 * Template name: Homepage
 *
 * @package storefront
 */

//get_header(); ?>
<!--
	<div class="boutique-featured-products site-main">
		<?php //do_action( 'boutique_before_homepage_content' ); ?>
	</div>

	<div id="primary" class="content-area">
		<main id="main" class="site-main" role="main">

			<?php //do_action( 'homepage' ); ?>

		</main>--><!-- #main -->
	<!--</div>--><!-- #primary -->

	<?php //do_action( 'storefront_sidebar' ); ?>

<?php //get_footer(); ?>
<html>
<head>
  <title>Skulliance</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
  <!--<link href="dist/output.css" rel="stylesheet">-->
  <link href="https://www.skulliance.io/staking/dist/flexbox.css?var=<?php echo rand(0,999); ?>" rel="stylesheet">
  <style>

	  .main {
	      align-items: center !important;
		  display: flex !important;
	  }
	  .banner {
	  	  background-image: url('https://www.skulliance.io/staking/images/skulliancebackground.png');
		  background-color: #36393F;
		  background-blend-mode: multiply;
		  background-size: cover;
		  background-repeat: no-repeat;
		  background-attachment: fixed;
		  background-position: center;
		  max-height: 1350px;
		  height: 100%;
	  }
	  .banner .logo{
	  	  margin: 0 auto;
	  }
	  .navbar img {
		  height: 13px;
		  width: auto;
	  }
	  .navbar{
	  	  position: fixed;
		  width: 100%;
		  align-items: center;
	  }
	  .navbar a{
		  font-size: 20px;
	  }
	  .navbar .icon {
	      position: absolute;
	      height: 36px;
		  padding-left: 5px;
	  }
	  .social:hover img{
	      -webkit-filter: invert(100%) !important;
	  }
	  .burger-menu{
	  	  display: none;
	  }
	  @media screen and (min-width: 701px) {
		  #burger-menu{
		  	  display: none;
		  }
		  #navbar{
		  	  display: flex;
		  }
	  }
	  @media screen and (max-width: 700px) {
		  .project {
			  width: 100%;
		  }
	  	  .navbar {
			  display: none;
	  	  }
		  .navbar .icon {
			  display: none;
		  }
		  .banner .logo img{
		  	  width: 90%;
		  }
		  .navbar a{
		  	  width: 100%;
		  }
	      #burger-menu {
			  display: block;
	          position: relative;
			  z-index: 99;
	      }
	      #burger-menu #burger-icon {
	          position: fixed;
	          top: 0px;
	          right: 0px;
			  width: 50px;
			  height: auto;
			  padding-top: 5px;
			  padding-right: 10px;
	      }
	  }
	  .show-menu{
		  display: flex !important;
	  }
	  .hide_menu{
	  	  display: none !important;
	  }
  </style>
</head>
<body>
	<div class="container">
		<div id="burger-menu">
			<img id="burger-icon" onclick="javascript:toggleMenu();" src="https://www.skulliance.io/staking/images/menu.png"/>
		</div>
		<!-- Navigation Bar -->
		<div class="navbar" id="navbar">
		  <img class="icon" src="https://www.skulliance.io/staking/images/skull.png" />
		  <a class="navbar-first" href="#mission">Mission</a>
		  <a href="#artists">Artists</a>
		  <a href="#partners">Partners</a>
		  <a href="https://www.skulliance.io/staking">Staking</a>
		  <a href="https://www.jpg.store/collection/skulliance">Diamond Skulls</a>
		  <a href="https://www.skulliance.io/shop">Merch</a>
		  <a class="social" href="https://discord.gg/JqqBZBrph2"><img src="https://www.skulliance.io/staking/images/discord.png" /></a>
		  <a class="social" href="https://www.x.com/skulliance"><img src="https://www.skulliance.io/staking/images/x.png" /></a>
		</div>

<!-- The flexible grid (content) -->
<div class="row banner" id="row1">
  <div class="main">
	<div class="logo"><img src="https://www.skulliance.io/staking/images/skulliancelogo.png"></div>
  </div>
</div>
<div class="projects-container">
<a id="mission" name="mission"></a>
<br>
<h2>Mission</h2>
<p>The mission of Skulliance is to connect skull art investors with the premier skull NFT artists on the Cardano blockchain and elevate the collective art form and community within the space.</p>
<img src="images/skulliance-group.jpg" width="100%"/>
<a id="artists" name="artists"></a>
<br><br>
<h2>Founding Artists</h2>
<p>The following artists specialize in skull art on Cardano and came together to form Skulliance. Skulliance developed a staking platform that allows holders of NFTs from these artists to stake them and earn nightly off-chain points that can be redeemed for exclusive incentives.</p>
<div class="row project-row" id="row1">
	<div class="projects">
		<div class="project">
			<a href="https://x.com/GalacticoNFT" target="_blank"><img src="images/projects/galactico.png"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/ohh_meed" target="_blank"><img src="images/projects/ohhmeed.png"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/haveyouseenhype" target="_blank"><img src="images/projects/hype.png"/></a>
		</div>
	</div>
</div>
<div class="row project-row" id="row1">
	<div class="projects">
		<div class="project">
			<a href="https://x.com/SinderSkullz" target="_blank"><img src="images/projects/sinderskullz.png"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/Nft4R" target="_blank"><img src="images/projects/kimosabe.png"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/cryptiesnft" target="_blank"><img src="images/projects/crypties.png"/></a>
		</div>
	</div>
</div>
<a id="partners" name="partners"></a>
<br>
<h2>Staking Partners</h2>
<p>With the success of the Skulliance staking platform. Skulliance chose to invite other high quality artists and projects on Cardano to participate in partner staking. This allows for holders of their NFTs to earn off-chain points and redeem them for exclusive incentives.</p>
<div class="row project-row" id="row1">
	<div class="projects">
		<div class="project">
			<a href="https://x.com/netanelchn" target="_blank"><img src="images/projects/netanelcohen.png"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/diexgrey" target="_blank"><img src="images/projects/grey.png"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/Threefoldbold" target="_blank"><img src="images/projects/threefoldbold.png"/></a>
		</div>
	</div>
</div>
<div class="row project-row" id="row1">
	<div class="projects">
		<div class="project">
			<a href="https://x.com/madmaxi__" target="_blank"><img src="images/projects/maxingo.png"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/deadpophell" target="_blank"><img src="images/projects/deadpophell.png"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/JordiLeitao" target="_blank"><img src="images/projects/jordi.png"/></a>
		</div>
	</div>
</div>
<div class="row project-row" id="row1">
	<div class="projects">
		<div class="project">
			<a href="https://x.com/DanketsuNFT" target="_blank"><img src="images/projects/danketsu.png"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/cardanocamera" target="_blank"><img src="images/projects/cardanocamera.jpg"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/ApprenticesCNFT" target="_blank"><img src="images/projects/apprentices.png"/></a>
		</div>
	</div>
</div>
<div class="row project-row" id="row1">
	<div class="projects">
		<div class="project">
			<a href="https://x.com/AscenderOne" target="_blank"><img src="images/projects/ascenderone.jpg"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/MipaToys" target="_blank"><img src="images/projects/mipatoys.jpg"/></a>
		</div>
		<div class="project">
			<a href="https://x.com/skulliance" target="_blank"><img src="images/projects/skulliance.jpg"/></a>
		</div>
	</div>
</div>
</div>

	<!-- Footer -->
	<div class="footer">
	  <p>Skulliance<br>Copyright © <span id="year"></span>
	</div>
</div>
  <script type="text/javascript">
	  document.getElementById("year").innerHTML = new Date().getFullYear();
	  
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
</body>
</html>