<?php
include 'db.php';
include 'webhooks.php';
include 'message.php';
include 'role.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';
?>
		<a name="store" id="store"></a>
		<div class="row" id="row1">
			<div class="side">
				<div class="content">
					<h2>Store</h2>
					<div class="content" id="player-stats">
							<?php renderWalletConnection("store"); ?>
							<?php renderCurrency($conn); ?>
					</div>
					<h2>Crafting</h2>
					<div class="content" id="player-stats">
						<?php renderCrafting($conn, "store"); ?>
					</div>
					<h2>Partner Projects</h2>
					<div class="content" id="player-stats">
						<?php renderCurrency($conn, false); ?>
					</div>
					<?php
					renderItemSubmissionForm($creators, "store");
					?>
				</div>
			</div>
    		<div class="main">
				<?php
				if($filterby != null && $filterby != 0){
					$project = getProjectInfo($conn, $filterby);
					$title = $project["name"];
				}else{
					$title = "All Projects";
					$filterby = 0;
				}
				echo "<h2>".$title."</h2>";
				filterItems("store");?>
				<a name="store" id="store"></a>
				<div class="content">
					<div id="nfts" class="nfts">
						<?php 
						getItems($conn, "store", $filterby);
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
	echo "<script type='text/javascript'>document.getElementById('filterItems').value = '".$filterby."';</script>";
}?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js"></script>
</html>