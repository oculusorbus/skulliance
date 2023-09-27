<?php
include_once 'db.php';
include 'webhooks.php';
include 'message.php';
include 'role.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';
?>

<a name="dashboard" id="dashboard"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="side">
		<h2>Skulliance Staking</h2>
		<div class="content" id="player-stats">
				<?php renderWalletConnection("dashboard"); ?>
				<?php if(isset($_SESSION['userData']['user_id'])){ renderCurrency($conn); }?>
		</div>
  </div>
  <div class="side">
		<?php if(isset($_SESSION['userData']['user_id'])){ ?>
		<h2>Crafting</h2>
		<div class="content" id="player-stats">
			<?php renderCrafting($conn, "dashboard"); ?>
		</div>
  </div>
  <div class="side">
		<h2>Partner Projects</h2>
		<div class="content" id="player-stats">
			<?php renderCurrency($conn, false); ?>
		</div>
		<?php } ?>
  </div>
</div>  
<div class="row" id="row1">
  <div class="main">
	<h2>Qualifying NFTs</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content">
		<?php filterNFTs("dashboard"); ?>
		<div id="nfts" class="nfts">
			<?php 
			if(isset($_SESSION['userData']['user_id'])){ 
				getNFTs($conn, $filterby); 
			}else{
				echo "<p>Please connect a Cardano wallet to view your qualifying NFTs.<br><br>Once you begin staking your NFTs, you will need to become a Skulliance member before you can claim items from the store.<br><br><a href='info.php'>View info on how to become a member of Skulliance.</a></p>";
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
</html>