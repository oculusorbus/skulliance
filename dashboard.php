<?php
include_once 'db.php';
include 'skulliance.php';
include 'webhooks.php';
include 'message.php';
include 'header.php';
include 'verify.php';

// Item submission to store
if(isset($_POST['name'])){
	if($_POST['name'] != "" && $_POST['image_url'] != "" && $_POST['price'] != "" && $_POST['quantity'] != "" && $_POST['project_id'] != ""){
		createItem($conn, $_POST['name'], $_POST['image_url'], $_POST['price'], $_POST['quantity'], $_POST['project_id']);
		$title = "New Store Listing: ".$_POST['name'];
		$project = getProjectInfo($conn, $_POST['project_id']);
		$description = $_POST['name']." listed for ".$_POST['price']." $".$project["currency"]." by ".getUsername($conn)."\r\nQuantity: ".$_POST['quantity'];
		$imageurl = $_POST['image_url'];
		discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
	}else{
		alert("Please fill out all the fields in the item submission form.");
	}
}

// Handle wallet selection
if(isset($_POST['stakeaddress'])){
	checkAddress($conn, $_POST['stakeaddress'], $_POST['address']);
	$addresses = array();
	//$addresses = getAddresses($conn);
	$addresses[0] = $_POST['stakeaddress'];
	$policies = array();
	$policies = getPolicies($conn);
	verifyNFTs($conn, $addresses, $policies);
	alert("Your wallet with stake address: ".$_POST['stakeaddress']." has been successfully connected. The qualifying NFTs in your wallet have now been verified.");
}

// Crafting
if(isset($_POST['balance'])){
	$minbalance = 0;
	$minbalance = getMinimumBalance($conn);
	// Double check submitted balance before crafting
	if($_POST['balance'] > 0 && $_POST['balance'] <= $minbalance){
		craft($conn, $_POST['balance']);
		alert("You have successfully crafted ".$_POST['balance']." \$DIAMOND. ".$_POST['balance']." of every other project currency has been deducted from your balances.");
	}
}

?>

<a name="dashboard" id="dashboard"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
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
					  <input type="hidden" id="stakeaddress" name="stakeaddress" value="">
					  <input type="submit" value="Submit" style="display:none;">
					</form>
				</li>
				</div>
		<?php
		$balances = getBalances($conn);
		foreach($balances AS $currency => $balance){ 
			?>
			<li class="role"><img class="icon rounded-full" src="icons/<?php echo strtolower(str_replace("$", "", $currency));?>.webp"/>
				<?php
				echo number_format($balance)." ".$currency;
				?>
			</li>
		<?php } ?>
			</ul>
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