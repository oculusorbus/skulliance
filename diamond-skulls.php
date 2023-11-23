<?php
include_once 'db.php';
include 'message.php';
include 'role.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';
?>

<a name="diamond-skulls" id="diamond-skulls"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
	<?php if($filterbydiamond == "MY" || $filterbydiamond == ""){ ?>
	<h2>My Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "DELEGATED"){ ?>
	<h2>My Delegated Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "ALL"){ ?>
	<h2>All Diamond Skulls</h2>
	<?php } ?>
	<a name="diamond-skulls" id="diamond-skulls"></a>
    <div class="content">
		<?php filterDiamondSkulls("diamond-skulls"); ?>
		<div id="nfts" class="nfts">
			<?php 			
			if(isset($_SESSION['userData']['user_id'])){ 
				if($filterbydiamond == "MY" || $filterbydiamond == ""){
					$advanced_filter = "my";
				}else if($filterbydiamond == "ALL"){
					$advanced_filter = "all";
				}else if($filterbydiamond == "DELEGATED"){
					$advanced_filter = "delegated";
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


if($_SESSION['userData']['diamond_skull_id'] != ""){ ?>
<a name="diamond-skull" id="diamond-skull"></a>
<div class="row" id="row1">
    <div class="main-diamond">
	<h2>Diamond Skull Delegation</h2>
    <div class="content">
		<div id="nfts" class="nfts">
			<?php getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 5, $projects, $project_names); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 6, $projects, $project_names); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 4, $projects, $project_names); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 3, $projects, $project_names); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 2, $projects, $project_names); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 1, $projects, $project_names); ?>
		</div>
	</div>
	</div>
</div>

<div class="row" id="row1">
  <div class="main">
	<h2>NFTs</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content">
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