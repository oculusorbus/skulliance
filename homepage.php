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
	  	  background-image: url('images/skulliancebackground.png');
		  background-size: cover;
		  background-repeat: no-repeat;
		  background-attachment: fixed;
		  background-position: center;
		  min-height: 1300px;
	  }
	  .banner .logo{
	  	  margin: 0 auto;
	  }
  </style>
</head>
<body>
	<div class="container">
		<!-- Navigation Bar -->
		<div class="navbar">
		  <img class="rounded-full" src="images/skull.png" />	
		  <a class="navbar-first" href="store.php">Store</a>
		  <a href="leaderboards.php">Leaderboards</a>
		  <a href="collections.php">Collections</a>
		  <a href="transactions.php">Transactions</a>
    	  <a href="wallets.php">Wallets</a>
		  <a href="../shop">Merch</a>
		</div>

<!-- The flexible grid (content) -->
<div class="row banner" id="row1">
  <div class="main">
	<div class="logo"><img src="images/skulliancelogo.png"></div>
  </div>
</div>
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
<br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br><br>
	<!-- Footer -->
	<div class="footer">
	  <p>Skulliance<br>Copyright © <span id="year"></span>
	</div>
</div>
</body>
</html>