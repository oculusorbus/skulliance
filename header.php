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
	      <img class="rounded-full" src="<?php echo $avatar_url?>" />
		  <a href="https://discord.gg/DHbGU9ZDyG"><?php echo $name;?></a>
		  <a class="navbar-first" href="dashboard.php">Dashboard</a>
		  <a class="navbar-first" href="transactions.php">Transactions</a>
		  <a href="logout.php">Logout</a>
		</div>
		<button onclick="topFunction()" id="back-to-top-button" title="Go to top">^</button>
