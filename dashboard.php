<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
$is_mobile = preg_match('/(android|iphone|ipad|ipod|mobile)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
function strip_nft_images($html){
	return preg_replace("/<span class='nft-image'>.*?<\\/span>/s", '', $html);
}
include 'header.php';
?>

<!-- Modal -->
<div id="myModal" class="modal">
	<!-- Modal content -->
	<div class="modal-content">
	  <div class="modal-header">
	    <span class="close">&times;</span>
	    <h2 id="modal-header">IMPORTANT ANNOUNCEMENT</h2>
	  </div>
	  <div class="modal-body">
	    <p id="modal-text">
			Leaderboards have been updated to allow for stakers to view other staker's NFT collections by clicking on usernames from the leaderboards. You have a choice as to whether you want other stakers to view your NFT collection or not. Select 'Visible' to allow. Select 'Hidden' to reject. This setting can be changed at any time under the Wallets menu item. This message will continue to be displayed on the dashboard until a selection is submitted.
			<?php renderVisibility("dashboard"); ?>
		</p>
	  </div>
	  <div class="modal-footer">
	    <h3></h3>
	  </div>
	</div>
</div>
<?php
if(getVisibility($conn) == "0"){
	?>
	<script type="text/javascript">
     document.getElementById("myModal").style.display = "block";
	 document.getElementById("visibility-button").className = "button";
	</script>
	<?php
}
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
		<?php if(isset($_SESSION['userData']['user_id'])){ ?>
		<h2>Daily Rewards</h2>
		<div class="content" id="player-stats">
			<?php renderDailyRewardsSection(); ?>
		</div>
		<h2>Crafting</h2>
		<div class="content" id="player-stats">
			<?php renderCrafting($conn, "dashboard"); ?>
		</div>
		<h2>Partners</h2>
		<div class="content" id="player-stats">
			<ul>
			<?php renderCurrency($conn, false); ?>
		</div>
		<?php } ?>
  </div>
  <div class="main">
	<h2>Staked NFTs</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content" id="filtered-content">
		<?php filterNFTs("dashboard"); ?>
		<div id="nfts" class="nfts">
			<?php
			if(isset($_SESSION['userData']['user_id'])){
				ob_start();
				getNFTs($conn, $filterby);
				$html = ob_get_clean();
				echo $is_mobile ? strip_nft_images($html) : $html;
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
if($filterby != ""){
	echo "<script type='text/javascript'>document.getElementById('filterNFTs').value = '".$filterby."';</script>";
}?>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php if($is_mobile): ?>
<script type="text/javascript">
if(typeof revealObserver !== 'undefined'){ revealObserver.disconnect(); }
document.querySelectorAll('section.reveal').forEach(function(el){ el.classList.add('active'); });
</script>
<?php endif; ?>
<?php
// Close DB Connection
$conn->close();
?>
</html>