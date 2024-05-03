<?php
include 'db.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';

// Set primary wallet
if(isset($_POST['wallet_id'])){
	setPrimaryWallet($conn, $_POST['wallet_id']);
}
?>
		<div class="row" id="row4">
			<div class="col1of3">
				<h2>Wallets</h2>
					<div class="content" id="transactions-pane">
						<?php renderWalletConnection("wallets"); ?>
						
						<div class="privacy">
						<li class="role">
							<strong>NFT Collection Visibility</strong>
						</li>
						<li class="role">
							<form id="privacyForm" action="wallets.php" method="post">
								
							  <input type="radio" id="private" name="visibility" value="Hidden">
							  <label for="html">Hidden</label><br>
							  <input type="radio" id="public" name="visibility" value="Visible">
							  <label for="css">Visible</label><br>
							  
							  <input type="hidden" id="user_id" name="user_id" value="<?php echo $_SESSION['userData']['user_id']; ?>">	
							  <input type="submit" value="Submit" class="small-button">
							</form>
						</li>
						</div>
						
						<?php
						if(isset($_SESSION['userData']['user_id'])){ 
							$wallets = getWallets($conn);
							$wallet_counter = 1;
							foreach($wallets AS $id => $wallet){ 
								?>
								<li class="role">
									<?php
									echo $wallet_counter.".&nbsp;";
									if($wallet["main"] == "0"){
										echo "<form id='walletForm' action='wallets.php' method='post'>";
										echo "<input type='hidden' id='wallet_id' name='wallet_id' value='".$id."'>";
										echo "<input type='submit' value='Make Primary' class='small-button'>";
										echo "</form>";
									}else{
										echo "Primary Address: ";
									}
									echo "&nbsp;<a href='https://pool.pm/".$wallet["address"]."' target='_blank'>".substr($wallet["address"], -20)."</a>&nbsp;";
									?>
								</li>
							<?php 
							$wallet_counter++;
							}
						} ?>
						</ul>
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
?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js"></script>
</html>