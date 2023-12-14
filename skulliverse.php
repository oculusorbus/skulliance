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
	background-image: url('images/space.png');
	height: 100%;
	background-position: center;
	
    background-color: #36393F;
    background-blend-mode: overlay;
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

.planet img{
	max-width: 100%;
	min-width: 40px;
}
.crypties{
	width: 2%;
	left: 40%;
	top: 4%;
}
.kimo{
	width: 4%;
	left: 5%;
	top: 15%;
}
.sinder{
	width: 5%;
	left: 45%;
	top: 36%;
}
.hype{
	width: 6%;
	left: 81%;
	top: 27%;
}
.meed{
	width: 8%;
	left: 22%;
	top: 43%;
}
.galactico{
	width: 10%;
	left: 64%;
	top: 53%;
}

.percentage {
	color: white;
	font-size: 18px;
	font-weight: bold;
	background-color: black;
	padding: 5px;
	position: relative;
	top: -10px;
}
</style>
		<div class="row" id="row1">
			<div class="col1of3">
			    <div class="content">
					<?php 
					$percentages = array();
					$percentages = getProjectDelegationPercentages($conn);
					?>
					<div class="planets">
					<div class="planet crypties"><span class="percentage"><?php echo $percentages[6]; ?>%</span><img class="" src="images/planets/crypties.png"/></div>
					<div class="planet kimo"><span class="percentage"><?php echo $percentages[5]; ?>%</span><img class="" src="images/planets/kimo.png"/></div>
					<div class="planet sinder"><span class="percentage"><?php echo $percentages[4]; ?>%</span><img class="" src="images/planets/sinder.png"/></div>
					<div class="planet hype"><span class="percentage"><?php echo $percentages[3]; ?>%</span><img class="" src="images/planets/hype.png"/></div>
					<div class="planet meed"><span class="percentage"><?php echo $percentages[2]; ?>%</span><img class="" src="images/planets/meed.png"/></div>
					<div class="planet galactico"><span class="percentage"><?php echo $percentages[1]; ?>%</span><img class="" src="images/planets/galactico.png"/></div>
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
<script type="text/javascript">
	var planets = document.getElementsByClassName("planet");
	var pixels = "";
    pixels = (window.innerHeight*0.20)+"px";
    //alert(pixels);
	for (var i = 0; i < planets.length; i++) {
		if(window.innerWidth <= 700){
			planets.item(i).style.paddingTop = (window.innerHeight*0.8)+"px";
		}else{
	   	 	planets.item(i).style.paddingTop = pixels;
   		}
	}
</script>
<script type="text/javascript" src="skulliance.js"></script>
</html>