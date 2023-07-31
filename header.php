<!doctype html>
<html>
<head>
  <title>Skulliance</title>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.3/jquery.min.js"></script>
  <!--<link href="dist/output.css" rel="stylesheet">-->
  <link href="dist/flexbox.css?var=<?php echo rand(0,999); ?>" rel="stylesheet">
</head>
<body>
	<div class="container">
		<!-- Navigation Bar -->
		<div class="navbar">
		  <?php if(isset($avatar_url)){?>
	      <img class="rounded-full" src="<?php echo $avatar_url?>" />
		  <?php } ?>
		  <a href="http://discord.gg/JqqBZBrph2"><?php echo (isset($name))?$name:"";?></a>
		  <a class="navbar-first" href="dashboard.php">Dashboard</a>
		  <a href="store.php">Store</a>
		  <a href="leaderboards.php">Leaderboards</a>
		  <a href="collections.php">Collections</a>
		  <a href="transactions.php">Transactions</a>
    	  <a href="wallets.php">Wallets</a>
		  <a href="logout.php">Logout</a>
		</div>
		<button onclick="topFunction()" id="back-to-top-button" title="Go to top">^</button>
