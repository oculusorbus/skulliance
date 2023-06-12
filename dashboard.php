<?php
include_once 'db.php';
include 'skulliance.php';
include 'webhooks.php';
include 'message.php';
include 'header.php';
include 'verify.php';



?>

<a name="dashboard" id="dashboard"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="side">
		<h2>Skulliance Staking</h2>
		<div class="content" id="player-stats">
				<?php renderWalletConnection(); ?>
				<?php renderCurrency($conn); ?>
		</div>
		<h2>Crafting</h2>
		<div class="content" id="player-stats">
			<ul>
			<li class="role">
			<?php 
			$balances = array();
			$balances = getBalances($conn);
			unset($balances["\$DIAMOND"]);
			$zero = false;
			foreach($balances AS $currency => $balance){
				if($balance == 0){
					$zero = true;
				}
			}
			if($zero){
				echo "You do not have balances for all currency to craft.";
			}else{
				?>
				<form id="craftingForm" action="dashboard.php" method="post">
				  Convert the following amount of every project currency to $DIAMOND:<br><br>
				  <img class="icon" src="icons/diamond.png">MAX&nbsp;
				  <input type="number" size="10" id="balance" name="balance" min="1" max="<?php echo min($balances);?>" value="<?php echo min($balances);?>">	
				  <input type="submit" value="Submit" class="small-button">
				</form>
				<?php
			}
			
			?>
			</li>
			</ul>
		</div>
		<h2>Store</h2>
		<a name="store" id="store"></a>
		<div class="content">
			<div id="nfts" class="nfts">
				<?php 
				getItems($conn);
				renderItemSubmissionForm($creators);
				?>				
			</div>
		</div>
  </div>
  <div class="main">
	<h2>Qualifying NFTs</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content">
		<?php filterNFTs("dashboard"); ?>
		<div id="nfts" class="nfts">
			<?php 
			getNFTs($conn, $filterby); 
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