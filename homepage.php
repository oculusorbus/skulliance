<html>
<head>
  <title>Skulliance</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
  <script src="https://www.skulliance.io/staking/skulliance.js"></script>
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
		  min-height: 1300px;
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
	  @media screen and (max-width: 700px) {
	  	  .navbar .icon {
			  display: none;
		  }
	  }
  </style>
</head>
<body>
	<div class="container">
		<!-- Navigation Bar -->
		<div class="navbar">
		  <img class="icon" src="images/skull.png" />
		  <a class="navbar-first" href="https://www.skulliance.io/staking">Staking</a>
		  <a href="https://www.skulliance.io/shop">Merch</a>
		  <a class="social" href="https://discord.gg/JqqBZBrph2"><img src="images/discord.png" /></a>
		  <a class="social" href="https://www.x.com/skulliance"><img src="images/x.png" /></a>
		</div>

<!-- The flexible grid (content) -->
<div class="row banner" id="row1">
  <div class="main">
	<div class="logo"><img src="images/skulliancelogo.png"></div>
  </div>
</div>
	<!-- Footer -->
	<div class="footer">
	  <p>Skulliance<br>Copyright © <span id="year"></span>
	</div>
</div>
</body>
</html>