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
						<?php
						$wallets = getWallets($conn);
						foreach($wallets AS $stake_address => $address){ 
							?>
							<li class="role">
								<?php
								echo "<a href='https://pool.pm/".$address."'>Address ending in ".substr($address, -4)."</a>";
								?>
							</li>
						<?php } ?>
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