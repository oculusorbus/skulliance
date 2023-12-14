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

/* The Close Button */
.close {
  color: #aaa;
  float: right;
  font-size: 28px;
  font-weight: bold;
}

.close:hover,
.close:focus {
  color: #fff;
  text-decoration: none;
  cursor: pointer;
}
/* Modal Header */
.modal-header {
  padding: 2px 16px;
  background-color: #000;
  color: white;
}

/* Modal Body */
.modal-body {padding: 2px 16px;}

/* Modal Footer */
.modal-footer {
  padding: 2px 16px;
  background-color: #000;
  color: white;
}

/* Modal Content */
.modal-content {
  position: relative;
  background-color: #000;
  margin: 15% auto; /* 15% from the top and centered */
  padding: 0;
  border: 1px solid #888;
  width: 80%;
  max-width: 580px;
  min-height: 400px;
  box-shadow: 0 4px 8px 0 rgba(0,0,0,0.2),0 6px 20px 0 rgba(0,0,0,0.19);
  animation-name: animatetop;
  animation-duration: 0.4s;
}

#modal-image{
	max-width: 200px;
	float: left;
}

#modal-text{
	font-weight: bold;
	position: relative;
	left: 20px;
}

/* Add Animation */
@keyframes animatetop {
  from {top: -300px; opacity: 0}
  to {top: 0; opacity: 1}
}

#myProgress {
  width: 100%;
  background-color: gray;
  margin-bottom: 40px;
}

#myBar {
  width: 1%;
  height: 30px;
  background-color: white;
}

@media screen and (max-width: 700px) {
	#modal-image{
		float: none;
	}
	#modal-text{
		position: relative;
		left: 0px;
	}
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
					
					$numbers = array();
					$numbers[1] = "one";
					$numbers[2] = "two";
					$numbers[3] = "three";
					$numbers[4] = "four";
					$numbers[5] = "five";
					$numbers[6] = "six";
					$numbers[7] = "seven";
					foreach($percentages AS $project_id => $percentage){
						$number = $numbers[$project_id];
						if($percentage < 100){
							$$number = "inactive";
						}else{
							$$number = "active";
						}
					}
					?>
					<div class="planets">
					<div class="planet diamond"><span class="percentage"><?php echo $percentages[7]; ?>%</span><img class="<?php echo $seven; ?>" onclick="javascript:openModal(7, '<?php echo $seven; ?>', <?php echo $percentages[7]; ?>);" src="images/planets/diamond.png"/></div>
					<div class="planet crypties"><span class="percentage"><?php echo $percentages[6]; ?>%</span><img class="<?php echo $six; ?>" onclick="javascript:openModal(6, '<?php echo $six; ?>', <?php echo $percentages[6]; ?>);" src="images/planets/crypties.png"/></div>
					<div class="planet kimo"><span class="percentage"><?php echo $percentages[5]; ?>%</span><img class="<?php echo $five; ?>" onclick="javascript:openModal(5, '<?php echo $five; ?>', <?php echo $percentages[5]; ?>);" src="images/planets/kimo.png"/></div>
					<div class="planet sinder"><span class="percentage"><?php echo $percentages[4]; ?>%</span><img class="<?php echo $four; ?>" onclick="javascript:openModal(4, '<?php echo $four; ?>', <?php echo $percentages[4]; ?>);" src="images/planets/sinder.png"/></div>
					<div class="planet hype"><span class="percentage"><?php echo $percentages[3]; ?>%</span><img class="<?php echo $three; ?>" onclick="javascript:openModal(3, '<?php echo $three; ?>', <?php echo $percentages[3]; ?>);" src="images/planets/hype.png"/></div>
					<div class="planet meed"><span class="percentage"><?php echo $percentages[2]; ?>%</span><img class="<?php echo $two; ?>" onclick="javascript:openModal(2, '<?php echo $two; ?>', <?php echo $percentages[2]; ?>);" src="images/planets/meed.png"/></div>
					<div class="planet galactico"><span class="percentage"><?php echo $percentages[1]; ?>%</span><img class="<?php echo $one; ?>" onclick="javascript:openModal(1, '<?php echo $one; ?>', <?php echo $percentages[1]; ?>);" src="images/planets/galactico.png"/></div>
					</div>
				</div>
			</div>
		</div>
		<!-- Modal -->
		<div id="myModal" class="modal">
			<!-- Modal content -->
			<div class="modal-content">
			  <div class="modal-header">
			    <span class="close">&times;</span>
			    <h2 id="modal-header">Modal Header</h2>
			  </div>
			  <div class="modal-body">
				<div id="myProgress">
				  <div id="myBar"></div>
				</div>
				<img id="modal-image" src=""/>
			    <p id="modal-text"></p>
			  </div>
			  <div class="modal-footer">
			    <h3></h3>
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
			planets.item(i).style.paddingTop = (window.innerHeight*1.3)+"px";
		}else{
	   	 	planets.item(i).style.paddingTop = pixels;
   		}
	}
	
	function openModal(project_id, status, percentage){
		 modal.style.display = "block";
		 //document.getElementById('myBar').style.width = percentage+"%";
		 move(percentage);
		 if(project_id != 7){
			 document.getElementById('modal-text').innerHTML = "2x CARBON Rewards: "+status.toUpperCase()+"<br><br>Delegations: "+percentage+"%";
		 }else{
		 	document.getElementById('modal-text').innerHTML = "2x DIAMOND Rewards: "+status.toUpperCase()+"<br><br>Delegations: "+percentage+"%";
		 }
		 if(project_id == 1){
			 document.getElementById('modal-image').src = "images/planets/galactico.png";
			 document.getElementById('modal-header').innerText = "Galactico";
		 }else if(project_id == 2){
			 document.getElementById('modal-image').src = "images/planets/meed.png";
			 document.getElementById('modal-header').innerText = "Ohh Meed";
		 }else if(project_id == 3){
			 document.getElementById('modal-image').src = "images/planets/hype.png";
			 document.getElementById('modal-header').innerText = "H.Y.P.E.";
		 }else if(project_id == 4){
			 document.getElementById('modal-image').src = "images/planets/sinder.png";
			 document.getElementById('modal-header').innerText = "Sinder Skullz";
		 }else if(project_id == 5){
			 document.getElementById('modal-image').src = "images/planets/kimo.png";
			 document.getElementById('modal-header').innerText = "Kimosabe Art";
		 }else if(project_id == 6){
			 document.getElementById('modal-image').src = "images/planets/crypties.png";
			 document.getElementById('modal-header').innerText = "Crypties";
		 }else if(project_id == 7){
			 document.getElementById('modal-image').src = "images/planets/diamond.png";
			 document.getElementById('modal-header').innerText = "Diamond Skulls";
		 }
	}
	
	// Get the modal
	var modal = document.getElementById("myModal");

	// Get the <span> element that closes the modal
	var span = document.getElementsByClassName("close")[0];

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
	
	// Progress Bar
	var i = 0;
	function move(percentage) {
	  if (i == 0) {
	    i = 1;
	    var elem = document.getElementById("myBar");
	    var width = 1;
	    var id = setInterval(frame, 10);
	    function frame() {
	      if (width >= percentage) {
	        clearInterval(id);
	        i = 0;
	      } else {
	        width++;
	        elem.style.width = width + "%";
	      }
	    }
	  }
	}
</script>
<script type="text/javascript" src="skulliance.js"></script>
</html>