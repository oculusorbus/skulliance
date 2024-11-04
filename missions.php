<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';

$username="";
if(isset($_GET['username'])){
	$username = $_GET['username'];
}
?>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
	<div id='stats'>
	<?php
	if(isset($_SESSION['userData']['user_id'])){
		getTotalMissions($conn);
	}else{
		echo "<p>Please connect a Cardano wallet to view missions.<br><br>Once you begin staking your NFTs, you will need to become a Skulliance member before you can claim items from the store.<br><br><a href='info.php'>View info on how to become a member of Skulliance.</a></p>";
	}?>
	<?php 
	if(isset($_SESSION['userData']['user_id'])){
		getCurrentMissions($conn);
	}else{
		echo "<p>Please connect a Cardano wallet to view missions.<br><br>Once you begin staking your NFTs, you will need to become a Skulliance member before you can claim items from the store.<br><br><a href='info.php'>View info on how to become a member of Skulliance.</a></p>";
	} 
	?>
	</div>
  </div>
</div>

<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <?php 
  if(isset($_POST["start_all"])){
  	startAllFreeEligibleMissions($conn);
  }
  $rate_tally = 0;
  $project_id = 0;
  $quest_id = 0;
  if(isset($_POST["project_id"]) && isset($_POST["quest_id"])){ 
	  $project_id = $_POST["project_id"];
	  $quest_id = $_POST["quest_id"];
	  $_SESSION['userData']['mission'] = array();
	  $_SESSION['userData']['mission']['quest_id'] = $_POST["quest_id"];
	  $_SESSION['userData']['mission']['nfts'] = array();
	  $_SESSION['userData']['mission']['consumables'] = array();
  ?>	
  <div class="side" id="mission">
  	<a name="inventory" id="inventory"></a>
	<div class="content inventory">
		<?php 
		$rate_tally = getInventory($conn, $project_id, $quest_id);
		if($rate_tally == 0){
			echo "<h2>Inventory</h2><ul><li class='role no-border-style'><strong>No Inventory Available for this Mission</strong></li></ul>";
		}
	    ?>
	</div>
  </div>
  <?php
  }else{?>
	  <div class="side" id="rewards">
			<?php if(isset($_SESSION['userData']['user_id'])){ ?>
			<h2>Daily Rewards</h2>
			<div class="content" id="player-stats">
				<?php renderWalletConnection("missions"); ?>
				<?php renderDailyRewardsSection(); ?>
			</div>
			<?php } ?>
	  </div>
  <?php
  }
  ?>
  <div class="main">
	<div id='available'>
	<h2>Available Missions</h2>
	<a name="missions" id="missions"></a>
    <div class="content missions">
		<?php //filterMissions($project_id); ?>
			<?php 
			if(isset($_SESSION['userData']['user_id'])){
				echo "<div id='filter'>";
				getMissionsFilters($conn, $quest_id);
				echo "</div>";
				echo "<div id='quests'>";
				getMissions($conn, $quest_id);
				echo "</div>";
			}else{
				echo "<p>Please connect a Cardano wallet to view missions.<br><br>Once you begin staking your NFTs, you will need to become a Skulliance member before you can claim items from the store.<br><br><a href='info.php'>View info on how to become a member of Skulliance.</a></p>";
			} 
			?>
    </div>
	</div>
  </div>
</div>
<div id="quick-menu">
	<img id="rewards-icon" src="icons/rewards.png" onclick="toggleSections('rewards');">
	<img id="stats-icon" src="icons/stats.png" onclick="toggleSections('stats');">
	<img id="filter-icon" src="icons/filter.png" onclick="toggleSections('filter');">
	<img id="quests-icon" src="icons/quests.png" onclick="toggleSections('quests');">
	<img id="mission-icon" src="icons/mission.png" onclick="toggleSections('mission');">
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
	echo "<script type='text/javascript'>document.getElementById('filterNFTs').value = '".$filterby."';</script>";
}?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<script type='text/javascript'>setSuccessRate('<?php echo $rate_tally; ?>');</script>
<script type='text/javascript'>
	if($(window).width() <= 700){
		document.getElementById('back-to-top-button').style.zIndex = "-1";
		document.getElementById('quick-menu').style.display = "block";
		if(window.location.hash == "#inventory"){
			document.getElementById('mission-icon').classList.add("selected");
			document.getElementById('stats').style.display = "none";
			document.getElementById('stats-icon').classList.remove("selected");
			document.getElementById('filter').style.display = "none";
			document.getElementById('filter-icon').classList.remove("selected");
			document.getElementById('quests').style.display = "none";
			document.getElementById('quests-icon').classList.remove("selected");
			document.getElementById('available').style.display = "none";
		}else{
			document.getElementById('stats').style.display = "none";
			document.getElementById('stats-icon').classList.remove("selected");
			document.getElementById('filter').style.display = "none";
			document.getElementById('filter-icon').classList.remove("selected");
			document.getElementById('quests').style.display = "none";
			document.getElementById('quests-icon').classList.remove("selected");
			document.getElementById('mission-icon').classList.remove("selected");
			document.getElementById('available').style.display = "none";
			document.getElementById('rewards-icon').classList.add("selected");
		}
	}else{
		document.getElementById('quick-menu').style.display = "none";
	}
	
	function toggleSections(selection){
		if($(window).width() <= 700){
			window.scrollTo(0, 0);
			if ($('#rewards').length > 0) {
				document.getElementById('rewards').style.display = "none";
			}
			document.getElementById('rewards-icon').classList.remove("selected");
			document.getElementById('stats').style.display = "none";
			document.getElementById('stats-icon').classList.remove("selected");
			document.getElementById('filter').style.display = "none";
			document.getElementById('filter-icon').classList.remove("selected");
			document.getElementById('quests').style.display = "none";
			document.getElementById('quests-icon').classList.remove("selected");
			document.getElementById('mission-icon').classList.remove("selected");
			document.getElementById('available').style.display = "none";
			if ($('#mission').length > 0) {
			  document.getElementById('mission').style.display = "none";
			  document.getElementById('mission-icon').classList.remove("selected");
			}
			if ($('#'+selection).length > 0) {
				document.getElementById(selection).style.display = "block";
				document.getElementById(selection+"-icon").classList.add("selected");
				if(selection == "filter" || selection == "quests"){
					document.getElementById('available').style.display = "block";
				}else{
					document.getElementById('available').style.display = "none";
				}
			}else{
				if(selection == "mission"){
					document.getElementById('available').style.display = "block";
					document.getElementById('quests').style.display = "block";
					document.getElementById('mission-icon').classList.add("selected");
				}
				if(selection == "rewards"){
					document.getElementById('mission').style.display = "block";
					document.getElementById('rewards-icon').classList.add("selected");
					window.location.href = 'missions.php';
				}
			}
		}
	}
</script>
</html>