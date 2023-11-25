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
	  .container {
		  max-width: none !important;
	  }
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
	  @media screen and (max-width: 700px) {
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
	      .burger-menu {
			  display: block;
	          position: relative;
			  z-index: 99;
	      }
	      .burger-menu .burger-icon {
	          position: absolute;
	          top: 0px;
	          right: 0px;
			  width: 50px;
			  height: auto;
			  padding-top: 5px;
			  padding-right: 10px;
	      }
	  }
  </style>
</head>
<body>
	<div class="container">
		<div class="burger-menu">
			<img class="burger-icon" onclick="javascript:" src="https://www.skulliance.io/staking/images/menu.png"/>
		</div>
		<!-- Navigation Bar -->
		<div class="navbar" id="navbar">
		  <img class="icon" src="https://www.skulliance.io/staking/images/skull.png" />
		  <a class="navbar-first" href="https://www.skulliance.io/staking">Staking</a>
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
	<!-- Footer -->
	<div class="footer">
	  <p>Skulliance<br>Copyright © <span id="year"></span>
	</div>
</div>
  <script type="text/javascript">
	  document.getElementById("year").innerHTML = new Date().getFullYear();
	  
	  function toggleMenu(){
	  	document.getElementById('navbar').style.display='flex';
		document.getElementById('burger-icon').src = "https://www.skulliance.io/staking/images/close.png";
	  }
  </script>
</body>
</html>