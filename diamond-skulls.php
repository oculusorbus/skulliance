<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';
?>

<a name="diamond-skulls" id="diamond-skulls"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
	<?php if($filterbydiamond == "MY"){ ?>
	<h2>My Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "DELEGATED"){ ?>
	<h2>My Delegated Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "ALL" || $filterbydiamond == ""){ ?>
	<h2>All Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "ALL DELEGATED"){ ?>
	<h2>All Delegated Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "EMPTY"){ ?>
	<h2>Empty Diamond Skulls</h2>
	<?php } ?>
	<a name="diamond-skulls" id="diamond-skulls"></a>
    <div class="content" id="filtered-content">
		<?php filterDiamondSkulls("diamond-skulls"); ?>
		<div id="nfts" class="nfts">
			<?php 			
			if(isset($_SESSION['userData']['user_id'])){ 
				if($filterbydiamond == "MY"){
					$advanced_filter = "my";
				}else if($filterbydiamond == "ALL" || $filterbydiamond == ""){
					$advanced_filter = "all";
				}else if($filterbydiamond == "DELEGATED"){
					$advanced_filter = "delegated";
				}else if($filterbydiamond == "ALL DELEGATED"){
					$advanced_filter = "all delegated";
				}else if($filterbydiamond == "EMPTY"){
					$advanced_filter = "empty";
				}
				if($diamond_skull_id != ""){
					$_SESSION['userData']['diamond_skull_id'] = $diamond_skull_id;
				}
				if(!isset($_SESSION['userData']['diamond_skull_id'])){
					$_SESSION['userData']['diamond_skull_id'] = "";
				}
				$diamond_skull_totals = getDiamondSkullTotals($conn);
				getNFTs($conn, 7, $advanced_filter, $diamond_skull=true, $_SESSION['userData']['diamond_skull_id'], false, $diamond_skull_totals); 
			}else{
				echo "<p>You do not own a Diamond Skull NFT.<br><br>Please connect a Cardano wallet with a Diamond Skull NFT.</p>";
			} 
			?>
		</div>
    </div>
  </div>
</div>
<?php


if($_SESSION['userData']['diamond_skull_id'] != ""){
$skull_sections = [5=>'CYBER', 6=>'CRYPT', 4=>'SINDER', 3=>'HYPE', 2=>'DREAD', 1=>'STAR'];
?>
<a name="diamond-skull" id="diamond-skull"></a>
<h2>Diamond Skull Delegation</h2>
<div class="diamond-container">
<div class="row" id="row1">
  <div class="main" style="text-align:left;">
<?php foreach($skull_sections as $proj_id => $proj_name): ?>
<div class="skull-section">
  <div class="skull-section-header">
    <img class="skull-acc-icon" src="icons/<?php echo strtolower($proj_name); ?>.png" alt="<?php echo $proj_name; ?>">
    <span class="skull-acc-label"><?php echo $proj_name; ?></span>
  </div>
  <div class="nfts">
    <?php getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], $proj_id, $projects, $project_names); ?>
  </div>
</div>
<?php endforeach; ?>
  </div>
</div>
</div>

<div class="row" id="row1">
  <div class="main">
	<h2>NFTs</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content" id="filtered-content">
		<?php filterCoreNFTs("diamond-skulls"); ?>
		<div id="nfts" class="nfts">
			<?php 
			if(isset($_SESSION['userData']['user_id'])){
				getNFTs($conn, $_SESSION['userData']['filterby'], $advanced_filter="", $diamond_skull=false, $diamond_skull_id="", $core_projects=true); 
			}else{
				echo "<p>You do not own any qualifying NFTs.<br><br>Please connect a Cardano wallet to view your NFTs.</p>";
			} 
			?>
		</div>
    </div>
  </div>
</div>

<?php } ?>

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
	echo "<script type='text/javascript'>document.getElementById('filterDiamondSkulls').value = '".$filterby."';</script>";
}?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
</html>