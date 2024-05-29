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
	
    background-color: #36393F;
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

.planet img:hover{
	cursor: pointer;
}

.diamond{
	width: 15%;
	left: 45%;
	top: 21%;
}
.crypties{
	width: 5%;
	left: 36%;
	top: 7%;
}
.kimo{
	width: 6%;
	left: 6%;
	top: 19%;
}
.sinder{
	width: 7%;
	left: 68%;
	top: 31%;
}
.hype{
	width: 8%;
	left: 85%;
	top: 44%;
}
.meed{
	width: 9%;
	left: 53%;
	top: 53%;
}
.galactico{
	width: 10%;
	left: 16%;
	top: 43%;
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



#myProgress {
  width: 100%;
  background-color: gray;
  margin-bottom: 40px;
}

#myBar {
  width: 1%;
  height: 30px;
  background-color: #D6DDDE;
  padding: 6px;
  text-align: right;
}
</style>
		<div class="row" id="row1">
			<div class="col1of3">
			    <div class="content">
					<?php 
					$percentages = array();
					$percentages = getProjectDelegationPercentages($conn);
					// Calculate average percentage for all projects to determine Diamond Skull percentage
					$average =  array_sum($percentages) / count($percentages);
					$percentages[7] = $average;
					
					// Get max delegations for projects
					$diamond_skull_count = getTotalDiamondSkulls($conn);
					$max_delegations = array();
					$max_delegations[1] = $diamond_skull_count;
					$max_delegations[2] = $diamond_skull_count*2;
					$max_delegations[3] = $diamond_skull_count*3;
					$max_delegations[4] = $diamond_skull_count*4;
					$max_delegations[5] = $diamond_skull_count*4;
					$max_delegations[6] = $diamond_skull_count*5;
					// Get max delegations for all projects
					foreach($max_delegations AS $project_id => $max_delegation){
						if(!isset($max_delegations[7])){
							$max_delegations[7] = 0;
						}
						$max_delegations[7] = $max_delegation+$max_delegations[7];
					}
					
					// Get project delegations
					$project_delegations = getProjectDelegationTotals($conn);
					// Get total delegations for all projects
					foreach($project_delegations AS $project_id => $total){
						if(!isset($project_delegations[7])){
							$project_delegations[7] = 0;
						}
						$project_delegations[7] = $total+$project_delegations[7];
					}
					
					// Assemble delegations display for modal window
					$delegations = array();
					foreach($project_delegations AS $project_id => $total){
						$delegations[$project_id] = number_format($total)." of ".number_format($max_delegations[$project_id])." (".($max_delegations[$project_id]-$total)." Remaining)";
					}
					
					$delegators = getProjectDelegatorTotals($conn);
					$delegators[7] = getDelegatorTotal($conn);
					
					$categories = array();
					$categories[1] = "Galactic";
					$categories[2] = "Terrestrial";
					$categories[3] = "Ring";
					$categories[4] = "Desert";
					$categories[5] = "City";
					$categories[6] = "Lava";
					$categories[7] = "Carbon";
					
					$inhabitants = array();
					$inhabitants[1] = "Celestials";
					$inhabitants[2] = "Assassins";
					$inhabitants[3] = "Skullective";
					$inhabitants[4] = "Militants";
					$inhabitants[5] = "Machines";
					$inhabitants[6] = "Necromancers";
					$inhabitants[7] = "Skulliance";
					
					$projects = getProjects($conn, $type="");
					
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
<div class="planet diamond">
<!--<span class="percentage"><?php echo $percentages[7]; ?>%</span>-->
<img class="<?php echo $seven; ?>" onclick="javascript:openModal(7, '<?php echo $seven; ?>', <?php echo $percentages[7]; ?>, '<?php echo $delegations[7]; ?>', <?php echo $delegators[7]; ?>, '<?php echo $categories[7]; ?>', '<?php echo $inhabitants[7]; ?>', '<?php echo $projects[7]['currency']; ?>', '<?php echo getTotalNFTCount($conn, 7); ?>');" src="images/planets/diamond.gif"/></div>
<div class="planet crypties">
<!--<span class="percentage"><?php echo $percentages[6]; ?>%</span>-->
<img class="<?php echo $six; ?>" onclick="javascript:openModal(6, '<?php echo $six; ?>', <?php echo $percentages[6]; ?>, '<?php echo $delegations[6]; ?>', <?php echo $delegators[6]; ?>, '<?php echo $categories[6]; ?>', '<?php echo $inhabitants[6]; ?>', '<?php echo $projects[6]['currency']; ?>', '<?php echo getTotalNFTCount($conn, 6); ?>');" src="images/planets/crypties.gif"/></div>
<div class="planet kimo">
<!--<span class="percentage"><?php echo $percentages[5]; ?>%</span>-->
<img class="<?php echo $five; ?>" onclick="javascript:openModal(5, '<?php echo $five; ?>', <?php echo $percentages[5]; ?>, '<?php echo $delegations[5]; ?>', <?php echo $delegators[5]; ?>, '<?php echo $categories[5]; ?>', '<?php echo $inhabitants[5]; ?>', '<?php echo $projects[5]['currency']; ?>', '<?php echo getTotalNFTCount($conn, 5); ?>');" src="images/planets/kimo.gif"/></div>
<div class="planet sinder">
<!--<span class="percentage"><?php echo $percentages[4]; ?>%</span>-->
<img class="<?php echo $four; ?>" onclick="javascript:openModal(4, '<?php echo $four; ?>', <?php echo $percentages[4]; ?>, '<?php echo $delegations[4]; ?>', <?php echo $delegators[4]; ?>, '<?php echo $categories[4]; ?>', '<?php echo $inhabitants[4]; ?>', '<?php echo $projects[4]['currency']; ?>', '<?php echo getTotalNFTCount($conn, 4); ?>');" src="images/planets/sinder.gif"/></div>
<div class="planet hype">
<!--<span class="percentage"><?php echo $percentages[3]; ?>%</span>-->
<img class="<?php echo $three; ?>" onclick="javascript:openModal(3, '<?php echo $three; ?>', <?php echo $percentages[3]; ?>, '<?php echo $delegations[3]; ?>', <?php echo $delegators[3]; ?>, '<?php echo $categories[3]; ?>', '<?php echo $inhabitants[3]; ?>', '<?php echo $projects[3]['currency']; ?>', '<?php echo getTotalNFTCount($conn, 3); ?>');" src="images/planets/hype.gif"/></div>
<div class="planet meed">
<!--<span class="percentage"><?php echo $percentages[2]; ?>%</span>-->
<img class="<?php echo $two; ?>" onclick="javascript:openModal(2, '<?php echo $two; ?>', <?php echo $percentages[2]; ?>, '<?php echo $delegations[2]; ?>', <?php echo $delegators[2]; ?>, '<?php echo $categories[2]; ?>', '<?php echo $inhabitants[2]; ?>', '<?php echo $projects[2]['currency']; ?>', '<?php echo getTotalNFTCount($conn, 2); ?>');" src="images/planets/meed.gif"/></div>
<div class="planet galactico">
<!--<span class="percentage"><?php echo $percentages[1]; ?>%</span>-->
<img class="<?php echo $one; ?>" onclick="javascript:openModal(1, '<?php echo $one; ?>', <?php echo $percentages[1]; ?>, '<?php echo $delegations[1]; ?>', <?php echo $delegators[1]; ?>, '<?php echo $categories[1]; ?>', '<?php echo $inhabitants[1]; ?>', '<?php echo $projects[1]['currency']; ?>', '<?php echo getTotalNFTCount($conn, 1); ?>');" src="images/planets/galactico.gif"/></div>
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
				<form id="delegationForm" action="diamond-skulls.php" method="post">
				  <input type="submit" value="Delegate" class="button">
				</form>
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
<script type="text/javascript" src="skulliance.js"></script>
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
	
	function openModal(project_id, status, percentage, delegations, delegators, category, inhabitants, currency, population){
		 modal.style.display = "block";
		 //document.getElementById('myBar').style.width = percentage+"%";
		 move(percentage);
		 document.getElementById('modal-text').innerHTML = 
		 "<strong>Planet Type:</strong> "+category+
		 "<br><strong>Inhabitants:</strong> "+inhabitants+
 		 "<br><strong>Currency:</strong> "+currency+
		 "<br><strong>Population:</strong> "+population+
		 "<br><strong>Delegations:</strong> "+delegations+
		 "<br><strong>Delegators:</strong> "+delegators;
		 
		 if(project_id != 7){
			 document.getElementById('modal-text').innerHTML = document.getElementById('modal-text').innerHTML+"<br><strong>2x CARBON Rewards:</strong> "+status.toUpperCase();
		 }else{
			 document.getElementById('modal-text').innerHTML = document.getElementById('modal-text').innerHTML+"<br><strong>2x DIAMOND Rewards:</strong> "+status.toUpperCase();
		 }

		 if(project_id == 1){
			 document.getElementById('modal-image').src = "images/planets/galactico.gif";
 			 document.getElementById("modal-image").removeAttribute("class");
			 document.getElementById("modal-image").classList.add(status);
			 document.getElementById('modal-header').innerText = "Galactico";
		 }else if(project_id == 2){
			 document.getElementById('modal-image').src = "images/planets/meed.gif";
			 document.getElementById("modal-image").removeAttribute("class");
 			 document.getElementById("modal-image").classList.add(status);
			 document.getElementById('modal-header').innerText = "Ohh Meed";
		 }else if(project_id == 3){
			 document.getElementById('modal-image').src = "images/planets/hype.gif";
			 document.getElementById("modal-image").removeAttribute("class");
			 document.getElementById("modal-image").classList.add(status);
			 document.getElementById('modal-header').innerText = "H.Y.P.E.";
		 }else if(project_id == 4){
			 document.getElementById('modal-image').src = "images/planets/sinder.gif";
			 document.getElementById("modal-image").removeAttribute("class");
			 document.getElementById("modal-image").classList.add(status);
			 document.getElementById('modal-header').innerText = "Sinder Skullz";
		 }else if(project_id == 5){
			 document.getElementById('modal-image').src = "images/planets/kimo.gif";
			 document.getElementById("modal-image").removeAttribute("class");
			 document.getElementById("modal-image").classList.add(status);
			 document.getElementById('modal-header').innerText = "Kimosabe Art";
		 }else if(project_id == 6){
			 document.getElementById('modal-image').src = "images/planets/crypties.gif";
			 document.getElementById("modal-image").removeAttribute("class");
			 document.getElementById("modal-image").classList.add(status);
			 document.getElementById('modal-header').innerText = "Crypties";
		 }else if(project_id == 7){
			 document.getElementById('modal-image').src = "images/planets/diamond.gif";
			 document.getElementById("modal-image").removeAttribute("class");
			 document.getElementById("modal-image").classList.add(status);
			 document.getElementById('modal-header').innerText = "Diamond Skulls";
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
			elem.innerText = width+"%";
	      }
	    }
	  }
	}
</script>
</html>