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
					$diamond_skull_count = getTotalDiamondSkulls($conn);
					$max_delegations = array();
					$max_delegations[1] = $diamond_skull_count;
					$max_delegations[2] = $diamond_skull_count*2;
					$max_delegations[3] = $diamond_skull_count*3;
					$max_delegations[4] = $diamond_skull_count*4;
					$max_delegations[5] = $diamond_skull_count*4;
					$max_delegations[6] = $diamond_skull_count*5;

					$percentages = array();
					$project_delegations = getProjectDelegationTotals($conn);
					foreach($project_delegations AS $project_id => $total){
						$percentages[$project_id] = round($total/$max_delegations[$project_id]*100);
					}
					?>
					<div class="planets">
					<div class="planet crypties"><span class="percentage"><?php echo $percentages[6]; ?>%</span><img class="" src="images/planets/crypties.png"/><img src="icons/crypt.png"/></div>
					<div class="planet kimo"><span class="percentage"><?php echo $percentages[5]; ?>%</span><img class="" src="images/planets/kimo.png"/><img src="icons/cyber.png"/></div>
					<div class="planet sinder"><span class="percentage"><?php echo $percentages[4]; ?>%</span><img class="" src="images/planets/sinder.png"/><img src="icons/sinder.png"/></div>
					<div class="planet hype"><span class="percentage"><?php echo $percentages[3]; ?>%</span><img class="" src="images/planets/hype.png"/><img src="icons/hype.png"/></div>
					<div class="planet meed"><span class="percentage"><?php echo $percentages[2]; ?>%</span><img class="" src="images/planets/meed.png"/><img src="icons/dread.png"/></div>
					<div class="planet galactico"><span class="percentage"><?php echo $percentages[1]; ?>%</span><img class="" src="images/planets/galactico.png"/><img src="icons/star.png"/></div>
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
			planets.item(i).style.paddingTop = "800px";
		}else{
	   	 	planets.item(i).style.paddingTop = pixels;
   		}
	}
</script>
<script type="text/javascript" src="skulliance.js"></script>
</html>