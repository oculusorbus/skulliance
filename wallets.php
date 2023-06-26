<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';

// Set primary wallet
if(isset($_POST['wallet_id'])){
	setPrimaryWallet($conn, $_POST['wallet_id']);
}
?>
		<div class="row" id="row4">
			<div class="main">
				<h2>Wallets</h2>
					<div class="content" id="transactions-pane">
						<?php renderWalletConnection("dashboard"); ?>
						<?php
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