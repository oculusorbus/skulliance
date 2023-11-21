<?php
include_once 'db.php';
include 'webhooks.php';
include 'message.php';
include 'role.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';
?>

<a name="diamond-skulls" id="diamond-skulls"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
	<h2>Diamond Skulls</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content">
		<?php filterDiamondSkulls("diamond-skulls"); ?>
		<div id="nfts" class="nfts">
			<?php 
			if(isset($_SESSION['userData']['user_id'])){ 
				if($filterbydiamond == "MY" || $filterbydiamond == ""){
					$all = false;
				}else if($filterbydiamond == "ALL"){
					$all = true;
				}
				if($diamond_skull_id != ""){
					$_SESSION['userData']['diamond_skull_id'] = $diamond_skull_id;
				}
				if(!isset($_SESSION['userData']['diamond_skull_id'])){
					$_SESSION['userData']['diamond_skull_id'] = "";
				}
				getNFTs($conn, 7, $all, $diamond_skull=true, $_SESSION['userData']['diamond_skull_id']); 
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
<div class="row" id="row1">
    <div class="main-diamond">
	<h2>Diamond Skull Activation</h2>
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
				getNFTs($conn, $_SESSION['userData']['filterby'], $all=false, $diamond_skull=false, $diamond_skull_id="", $core_projects=true); 
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