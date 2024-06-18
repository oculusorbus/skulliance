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
  <?php 
  $rate_tally = 0;
  $project_id = 0;
  $quest_id = 0;
  if(isset($_POST["project_id"]) && isset($_POST["quest_id"])){ 
	  $project_id = $_POST["project_id"];
	  $quest_id = $_POST["quest_id"];
	  $_SESSION['userData']['mission'] = array();
	  $_SESSION['userData']['mission']['quest_id'] = $_POST["quest_id"];
	  $_SESSION['userData']['mission']['nfts'] = array();
  ?>	
  <div class="side">
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
  }
  
  $random = 0;
  $consumables = array();
  $consumables = getConsumables($conn);
  $consumable_ranges = array();
  $consumable_ranges = getConsumableRanges($conn);
  $random = rand(1, 100);
  $consumable_id = 0;
  
  foreach($consumable_ranges AS $id => $range){
	  foreach($range AS $start => $end){
		  if($random >= $start && $random <= $end){
			  $consumable_id = $id;
		  }
	  }
  }
  
  echo $consumables[$consumable_id];
  ?>
  <div class="main">
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
	<h2>Available Missions</h2>
	<a name="missions" id="missions"></a>
    <div class="content missions">
		<?php //filterMissions($project_id); ?>
		<div id="nfts" class="nfts">
			<?php 
			if(isset($_SESSION['userData']['user_id'])){
				getMissions($conn, $quest_id);
			}else{
				echo "<p>Please connect a Cardano wallet to view missions.<br><br>Once you begin staking your NFTs, you will need to become a Skulliance member before you can claim items from the store.<br><br><a href='info.php'>View info on how to become a member of Skulliance.</a></p>";
			} 
			?>
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
	echo "<script type='text/javascript'>document.getElementById('filterNFTs').value = '".$filterby."';</script>";
}?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<script type='text/javascript'>setSuccessRate('<?php echo $rate_tally; ?>');</script>
</html>