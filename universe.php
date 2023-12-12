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
.planet{
	width: 12%;
	margin: 0 auto;
	position: absolute;
	padding-top: 20%;
}
.crypties{
	width: 2%;
	left: -5%;
}
.kimo{
	width: 4%;
	left: 5%;
}
.sinder{
	width: 6%;
	left: -10%;
}
.hype{
	width: 8%;
	left: 10%;
}
.meed{
	width: 10%;
	left: -15;
}
.galactico{
	width: 12%;
	left: 15%;
}
</style>
		<div class="row" id="row1">
			<div class="col1of3">
			    <div class="content">
					<img class="planet crypties" src="images/planets/crypties.png"/>
					<img class="planet kimo" src="images/planets/kimo.png"/>
					<img class="planet sinder" src="images/planets/sinder.png"/>
					<img class="planet hype" src="images/planets/hype.png"/>
					<img class="planet meed" src="images/planets/meed.png"/>
					<img class="planet galactico" src="images/planets/galactico.png"/>
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