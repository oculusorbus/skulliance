<?php
include 'db.php';
include 'skulliance.php';
//include 'webhooks.php';
include 'header.php';

// Handle wallet selection
if(isset($_POST['address'])){
	checkAddress($conn, $_POST['address']);
}

$addresses = array();
$addresses = getAddresses($conn);
$policies = array();
$policies = getPolicies($conn);
print_r($policies);

foreach($addresses AS $index => $address){
	
}
?>

<a name="dashboard" id="dashboard"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
    <div class="content">
	
    </div>
  </div>
  <div class="side">
		<h2>Skulliance Staking</h2>
		<div class="content" id="player-stats">
			<ul>
				<div class="wallet-connect">
				<li class="role"><img class="icon" src="icons/wallet.png"/>
					<label for="wallets"><strong>Connect</strong>&nbsp;</label>
					<select onchange="javascript:connectWallet(this.options[this.selectedIndex].value);" name="wallets" id="wallets">
						<option value="none">Wallet</option>
					</select>
					<form id="addressForm" action="dashboard.php" method="post">
					  <input type="hidden" id="wallet" name="wallet" value="">	
					  <input type="hidden" id="address" name="address" value="">
					  <input type="submit" value="Submit" style="display:none;">
					</form>
				</li>
				</div>
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
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>