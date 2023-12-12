<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
<style>
.container{
	max-width: 100%;
}
.row {
	background-size: cover;
	background-image: url('images/space.jpg');
	height: 100%;
	background-position: center;
}	
.col1of3{
	background-color: transparent;
	text-align: center;
}

.planets{
	
}

.planet{
	width: 12%;
	margin: 0 auto;
	position: absolute;
	padding-top: 300px;
}
.crypties{
	width: 2%;
	left: 40%;
	top: 1%;
}
.kimo{
	width: 4%;
	left: 7%;
	top: 8%;
}
.sinder{
	width: 6%;
	left: 45%;
	top: 24%;
}
.hype{
	width: 8%;
	left: 74%;
	top: 11%;
}
.meed{
	width: 10%;
	left: 22%;
	top: 28%;
}
.galactico{
	width: 12%;
	left: 64%;
	top: 35%;
}
</style>
		<div class="row" id="row1">
			<div class="col1of3">
			    <div class="content">
					<div class="planets">
					<div class="planet"><img class="crypties" src="images/planets/crypties.png"/></div>
					<div class="planet"><img class="kimo" src="images/planets/kimo.png"/></div>
					<div class="planet"><img class="sinder" src="images/planets/sinder.png"/></div>
					<div class="planet"><img class="hype" src="images/planets/hype.png"/></div>
					<div class="planet"><img class="meed" src="images/planets/meed.png"/></div>
					<div class="planet"><img class="galactico" src="images/planets/galactico.png"/></div>
					</div>
				</div>
			</div>
		</div>
		<!-- Footer -->
		<div class="footer">
		  <p>Skulliance<br>Copyright © <span id="year"></span>
		</div>
	</div>
  </div>
</body>
<?php
// Close DB Connection
$conn->close();
if($filterby != ""){
	echo "<script type='text/javascript'>document.getElementById('filterLeaderboard').value = '".$filterby."';</script>";
}?>
<script type="text/javascript" src="skulliance.js"></script>
</html>