<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
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

function renderDailyRewards($rewards){
	$count = 0;
	if(is_array($rewards)){
		foreach($rewards AS $index => $reward){
			echo "<li class='role'>";
			echo "<strong>Day ".$reward["day"].":</strong> &nbsp;&nbsp;<img class='icon' src='icons/".strtolower($reward["currency"]).".png'/> ".$reward["amount"]." ".$reward["currency"];
			echo "</li>";
		}
		$count = count($rewards);
	}else{
		$count = 0;
	}
	$count++;
	for ($count; $count <= 7; $count++) {
      echo "<li class='role'>";
	  echo "<strong>Day ".$count.":</strong> &nbsp;&nbsp;<img class='icon' src='icons/mystery.png'/> ".$count." RANDOM";
      echo "</li>";
	}	
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
			<ul>
				<?php
				echo "<li class='role'><strong>Current Daily Rewards Streak</strong></li>";
				echo "<li class='role'>Collect daily random rewards for up to 7 days in a row.</li>";
				$days = getStreakRewards($conn);
				renderDailyRewards($days);
				?>
					<?php 
					if(getDailyRewardEligibility($conn)) { 
						// Reset daily reward streak if yesterday's daily reward wasn't claimed
						if(!verifyYesterdaysRewards($conn)){
							resetDailyRewardStreak($conn);
						}
						?>
						<li class="role" id="reward" style="display:none"></li>
						<br>
						<!--<img class="icon" id="dailyRewardIcon" src="icons/diamond.png" style="display:none;"/>-->
						<input id="claimRewardButton" type="button" value="Claim Daily Reward" class="button" onclick="javascript:dailyReward();">
					<?php } else { ?>
							<?php
							// Display 7 day completed rewards despite streak being reset
							$current_streak = getCurrentDailyRewardStreak($conn);
							if($current_streak == 0){
								$rewards = getCompletedRewards($conn);
								renderDailyRewards($rewards);
							}
							?>
						<li class="role">
						<strong>Daily Reward Already Claimed</strong>
						</li>
					<?php } ?>
			</ul>
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
	<h2>Qualifying NFTs</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content" id="filtered-content">
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
if($filterby != ""){
	echo "<script type='text/javascript'>document.getElementById('filterNFTs').value = '".$filterby."';</script>";
}?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
// Close DB Connection
$conn->close();
?>
</html>