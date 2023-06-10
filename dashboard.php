<?php
include_once 'db.php';
include 'skulliance.php';
include 'webhooks.php';
include 'message.php';
include 'header.php';
include 'verify.php';

// Handle wallet selection
if(isset($_POST['stakeaddress'])){
	checkAddress($conn, $_POST['stakeaddress'], $_POST['address']);
	$addresses = array();
	//$addresses = getAddresses($conn);
	$addresses[0] = $_POST['stakeaddress'];
	$policies = array();
	$policies = getPolicies($conn);
	verifyNFTs($conn, $addresses, $policies);
}

// Crafting
if(isset($_POST['balance'])){
	$minbalance = 0;
	$minbalance = getMinimumBalance($conn);
	// Double check submitted balance before crafting
	if($_POST['balance'] > 0 && $_POST['balance'] <= $minbalance){
		craft($conn, $_POST['balance']);
	}
}

//Item purchases
if(isset($_POST['item_id'])) {
	if(!checkTransaction($conn, $_POST['item_id'])){
		$quantity = getItemQuantity($conn, $_POST['item_id']);
		if($quantity >= 1){
			$price = getItemPrice($conn, $_POST['item_id']);
			if($_POST['project_id'] == 7 && $_POST['primary_project_id'] != 7){
				$price = $price/10;
			}
			$balance = getBalance($conn, $_POST['project_id']);
			if($balance >= $price){
				updateBalance($conn, $user_id, $_POST['project_id'], -$price);
				updateQuantity($conn, $_POST['item_id']);
				logDebit($conn, $user_id, $_POST['item_id'], $price, $_POST['project_id']);
				$item = getItemInfo($conn, $_POST['item_id'], $_POST['project_id']);
				$title = $item["name"]." Purchased";
				$description = $item["name"]." purchased for ".$price." $".$item["currency"]." by ".getUsername($conn);
				$imageurl = $item["image_url"];
				discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
				$project = array();
				$project = getProjectInfo($conn, $_POST['primary_project_id']);
				# Open the DM first
				$newDM = MakeRequest('/users/@me/channels', array("recipient_id" => $project["discord_id"]));
				# Check if DM is created, if yes, let's send a message to this channel.
				if(isset($newDM["id"])) {
					$content = $item["name"]." purchased for ".$price." $".$item["currency"]." by ".getUsername($conn). "\r\n ".$imageurl." \r\n Please send NFT to ".getAddress($conn);
				    $newMessage = MakeRequest("/channels/".$newDM["id"]."/messages", array("content" => $content));
				}
			}else{
				alert("You do not have enough currency to purchase this item.");
			}
		}else{
			alert("You cannot purchase this item because it is out of stock.");
		}
	}else{
		alert("Your purchase has been prevented because you\'ve already purchased this item before. Don\'t be greedy. Let others have a chance to redeem NFT rewards.");
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
			<li class="role"><img class="icon" src="icons/<?php echo strtolower(str_replace("$", "", $currency));?>.webp"/>
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