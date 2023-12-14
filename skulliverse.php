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

.diamond{
	width: 12%;
	left: 47%;
	top: 18%;
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
	left: 37%;
	top: 30%;
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
.inactive{
	opacity: 0.5;
    -webkit-filter: grayscale(100%);
       -moz-filter: grayscale(100%);
         -o-filter: grayscale(100%);
        -ms-filter: grayscale(100%);
            filter: grayscale(100%); 
}

/* The Modal (background) */
.modal {
  display: none; /* Hidden by default */
  position: fixed; /* Stay in place */
  z-index: 1; /* Sit on top */
  left: 0;
  top: 0;
  width: 100%; /* Full width */
  height: 100%; /* Full height */
  overflow: auto; /* Enable scroll if needed */
  background-color: rgb(0,0,0); /* Fallback color */
  background-color: rgba(0,0,0,0.4); /* Black w/ opacity */
}

/* Modal Content/Box */
.modal-content {
  background-color: #fefefe;
  margin: 15% auto; /* 15% from the top and centered */
  padding: 20px;
  border: 1px solid #888;
  width: 80%; /* Could be more or less, depending on screen size */
}

/* The Close Button */
.close {
  color: #aaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
}

.close:hover,
.close:focus {
  color: black;
  text-decoration: none;
  cursor: pointer;
}
</style>
		<div class="row" id="row1">
			<div class="col1of3">
			    <div class="content">
					<?php 
					$percentages = array();
					$percentages = getProjectDelegationPercentages($conn);
					// Calculate average percentage for all projects to determine Diamond Skull percentage
					$average =  round(array_sum($percentages) / count($percentages));
					$percentages[7] = $average;
					?>
					<div class="planets">
					<div class="planet diamond"><span class="percentage"><?php echo $percentages[7]; ?>%</span><img class="<?php echo ($percentages[7] < 100)?"inactive":"active"; ?>" id="myBtn" src="images/planets/diamond.png"/></div>
					<div class="planet crypties"><span class="percentage"><?php echo $percentages[6]; ?>%</span><img class="<?php echo ($percentages[6] < 100)?"inactive":"active"; ?>" id="myBtn" src="images/planets/crypties.png"/></div>
					<div class="planet kimo"><span class="percentage"><?php echo $percentages[5]; ?>%</span><img class="<?php echo ($percentages[5] < 100)?"inactive":"active"; ?>" id="myBtn" src="images/planets/kimo.png"/></div>
					<div class="planet sinder"><span class="percentage"><?php echo $percentages[4]; ?>%</span><img class="<?php echo ($percentages[4] < 100)?"inactive":"active"; ?>" id="myBtn" id="myBtn" src="images/planets/sinder.png"/></div>
					<div class="planet hype"><span class="percentage"><?php echo $percentages[3]; ?>%</span><img class="<?php echo ($percentages[3] < 100)?"inactive":"active"; ?>" id="myBtn" src="images/planets/hype.png"/></div>
					<div class="planet meed"><span class="percentage"><?php echo $percentages[2]; ?>%</span><img class="<?php echo ($percentages[2] < 100)?"inactive":"active"; ?>" id="myBtn" src="images/planets/meed.png"/></div>
					<div class="planet galactico"><span class="percentage"><?php echo $percentages[1]; ?>%</span><img class="<?php echo ($percentages[1] < 100)?"inactive":"active"; ?>" id="myBtn" src="images/planets/galactico.png"/></div>
					</div>
				</div>
			</div>
		</div>
		<!-- Modal -->
		<div id="myModal" class="modal">

		  <!-- Modal content -->
		  <div class="modal-content">
		    <span class="close">&times;</span>
		    <p>Some text in the Modal..</p>
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
	
	// Get the modal
	var modal = document.getElementById("myModal");

	// Get the button that opens the modal
	var btn = document.getElementById("myBtn");

	// Get the <span> element that closes the modal
	var span = document.getElementsByClassName("close")[0];

	// When the user clicks on the button, open the modal
	btn.onclick = function() {
	  modal.style.display = "block";
	}

	// When the user clicks on <span> (x), close the modal
	span.onclick = function() {
	  modal.style.display = "none";
	}

	// When the user clicks anywhere outside of the modal, close it
	window.onclick = function(event) {
	  if (event.target == modal) {
	    modal.style.display = "none";
	  }
	}
</script>
<script type="text/javascript" src="skulliance.js"></script>
</html>