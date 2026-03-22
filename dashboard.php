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
?>

<a name="dashboard" id="dashboard"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
	<h2>Staked NFTs</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content" id="filtered-content">
		<?php filterNFTs("dashboard", "", "get"); ?>
		<div id="nfts" class="nfts">
			<?php
			if(isset($_SESSION['userData']['user_id'])){
				$page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
				$per_page = 24;
				$total_nfts  = countNFTs($conn, $filterby);
				$total_pages = (int)ceil($total_nfts / $per_page);
				if($page > $total_pages && $total_pages > 0) $page = $total_pages;
				getNFTs($conn, $filterby, "", false, "", false, "", $page, $per_page);
				if($total_pages > 1){
					$fb = urlencode($filterby);
					echo "<div class='nft-pagination'>";
					if($page > 1){
						echo "<a class='page-btn' href='dashboard.php?filterby={$fb}&page=".($page-1)."'>&#8592; Prev</a>";
					}else{
						echo "<span class='page-btn disabled'>&#8592; Prev</span>";
					}
					$prev = 0;
					for($p = 1; $p <= $total_pages; $p++){
						if($p == 1 || $p == $total_pages || ($p >= $page - 2 && $p <= $page + 2)){
							if($prev && $p - $prev > 1){
								echo "<span class='page-ellipsis'>&#8230;</span>";
							}
							if($p == $page){
								echo "<span class='page-btn active'>{$p}</span>";
							}else{
								echo "<a class='page-btn' href='dashboard.php?filterby={$fb}&page={$p}'>{$p}</a>";
							}
							$prev = $p;
						}
					}
					if($page < $total_pages){
						echo "<a class='page-btn' href='dashboard.php?filterby={$fb}&page=".($page+1)."'>Next &#8594;</a>";
					}else{
						echo "<span class='page-btn disabled'>Next &#8594;</span>";
					}
					echo "<div class='page-info'>Page {$page} of {$total_pages} &nbsp;({$total_nfts} NFTs)</div>";
					echo "</div>";
				}
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
<?php
// Close DB Connection
$conn->close();
?>
</html>