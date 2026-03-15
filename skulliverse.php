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



/* Skulliverse: .content wraps the planet layout — must be invisible */
.content {
    background: transparent !important;
    border: none !important;
    padding: 0 !important;
    margin: 0 !important;
    overflow: visible !important;
}

#myProgress {
  width: 100%;
  background-color: gray;
  margin-bottom: 40px;
  overflow: hidden;
}

#myBar {
  width: 1%;
  height: 30px;
  background-color: #D6DDDE;
  padding: 6px;
  text-align: right;
  white-space: nowrap;
  overflow: hidden;
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
(function(){
	var paddingPx = Math.round(window.innerHeight * 0.20) + 'px';
	var allPlanets = document.getElementsByClassName('planet');

	// Mobile: restore original static layout and exit
	if(window.innerWidth <= 700){
		for(var i = 0; i < allPlanets.length; i++){
			allPlanets[i].style.paddingTop = paddingPx;
		}
		return;
	}

	var diamondEl  = document.querySelector('.planet.diamond');
	var diamondImg = diamondEl.querySelector('img');

	// Restore paddingTop on diamond skull so it sits at its original visual position
	diamondEl.style.paddingTop = paddingPx;

	// Tilt +20deg: lower-left = near foreground, upper-right = far background
	// Reduced from 30° — sin(20°)≈0.34 vs sin(30°)=0.5, much less vertical bleed from horizontal axis
	var TILT    = Math.PI / 9;
	var cosTilt = Math.cos(TILT), sinTilt = Math.sin(TILT);

	// Orbits ordered smallest → largest radius.
	// aFrac/bFrac as fractions of viewport width. b/a ≈ 0.36 gives perspective compression.
	// Scaled down ~25% from previous to keep planets within the background image bounds.
	var configs = [
		{ cls:'crypties',   aFrac:0.10, bFrac:0.036, period:20, phase:0.7  },
		{ cls:'meed',       aFrac:0.15, bFrac:0.054, period:26, phase:2.2  },
		{ cls:'galactico',  aFrac:0.20, bFrac:0.072, period:33, phase:4.0  },
		{ cls:'sinder',     aFrac:0.25, bFrac:0.090, period:39, phase:5.5  },
		{ cls:'kimo',       aFrac:0.30, bFrac:0.108, period:46, phase:1.5  },
		{ cls:'hype',       aFrac:0.35, bFrac:0.126, period:54, phase:3.3  },
	];

	var orbiters = configs.map(function(c){
		var el = document.querySelector('.planet.' + c.cls);
		return { el: el, cfg: c };
	}).filter(function(o){ return !!o.el; });

	// cx/cy in page coordinates (viewport coords = page coords on non-scrolling page)
	var cx = 0, cy = 0, vw = 0;

	function measure(){
		var dir = diamondImg.getBoundingClientRect();
		vw = window.innerWidth;
		cx = dir.left + window.scrollX + dir.width  / 2;
		cy = dir.top  + window.scrollY + dir.height / 2;
	}

	var startTs = null;
	function tick(ts){
		if(!startTs) startTs = ts;
		var t = (ts - startTs) / 1000;

		orbiters.forEach(function(o){
			var c = o.cfg, el = o.el;
			var a = c.aFrac * vw;
			var b = c.bFrac * vw;
			var angle = (t / c.period) * Math.PI * 2 + c.phase;

			var ex = Math.cos(angle) * a;
			var ey = Math.sin(angle) * b;
			var rx = ex * cosTilt - ey * sinTilt;
			var ry = ex * sinTilt + ey * cosTilt;

			// depth from actual screen y-position: positive = below diamond skull = foreground
			// ryMax = amplitude of ry over the orbit cycle
			var ryMax = Math.sqrt(a * sinTilt * a * sinTilt + b * cosTilt * b * cosTilt);
			var depth = ryMax > 0 ? ry / ryMax : 0;
			// Scale ±30% relative to each planet's CSS-defined size
			var scale = 1.0 + 0.30 * depth;

			var pw = el.offsetWidth;
			var ph = el.offsetHeight;
			el.style.left            = (cx + rx - pw / 2) + 'px';
			el.style.top             = (cy + ry - ph / 2) + 'px';
			el.style.paddingTop      = '0';
			el.style.transform       = 'scale(' + scale.toFixed(3) + ')';
			el.style.transformOrigin = 'center center';
			el.style.zIndex          = depth > 0 ? 12 : 8;
		});

		diamondEl.style.zIndex = 10;
		requestAnimationFrame(tick);
	}

	function onResize(){
		var newPad = Math.round(window.innerHeight * 0.20) + 'px';
		diamondEl.style.paddingTop = newPad;
		// Re-measure after paddingTop has been applied (next frame)
		requestAnimationFrame(measure);
	}

	// Initial measure after paddingTop settles
	requestAnimationFrame(function(){ measure(); startTs = null; requestAnimationFrame(tick); });
	window.addEventListener('resize', onResize);
})();
	
	function openModal(project_id, status, percentage, delegations, delegators, category, inhabitants, currency, population){
		 modal.style.display = "block";
		 //document.getElementById('myBar').style.width = percentage+"%";
		 move(percentage);
		 document.getElementById('modal-text').innerHTML = 
		 "<span><strong>Planet Type:</strong> "+category+"</span>"+
		 "<span><strong>Inhabitants:</strong> "+inhabitants+"</span>"+
 		 "<span><strong>Currency:</strong> "+currency+"</span>"+
		 "<span><strong>Population:</strong> "+population+"</span>"+
		 "<span><strong>Delegations:</strong> "+delegations+"</span>"+
		 "<span><strong>Delegators:</strong> "+delegators+"</span>";
		 
		 if(project_id != 7){
			 document.getElementById('modal-text').innerHTML = document.getElementById('modal-text').innerHTML+"<span><strong>2x CARBON Rewards:</strong> "+status.toUpperCase()+"</span>";
		 }else{
			 document.getElementById('modal-text').innerHTML = document.getElementById('modal-text').innerHTML+"<span><strong>2x DIAMOND Rewards:</strong> "+status.toUpperCase()+"</span>";
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