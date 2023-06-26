<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
		<div class="row" id="row4">
			<div class="main">
				<h2>Wallets</h2>
					<div class="content" id="transactions-pane">
						<ul>
						<li class="role">Address ending in...</li>
						<?php
						$wallets = getWallets($conn);
						$wallet_counter = 1;
						foreach($wallets AS $address => $main){ 
							?>
							<li class="role">
								<?php
								echo $wallet_counter.".&nbsp;<a href='https://pool.pm/".$address."' target='_blank'>".substr($address, -20)."</a>&nbsp;";
								if($main == "0"){
									echo "<form id='walletForm' action='wallets.php' method='post'>";
									echo "<input type='submit' value='Make Primary' class='small-button'>";
									echo "</form>";
								}
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
<script type="text/javascript" src="skulliance.js"></script>
</html>