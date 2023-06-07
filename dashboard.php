<?php
include_once 'db.php';
include 'skulliance.php';
include 'webhooks.php';
include 'message.php';
include 'header.php';
include 'verify.php';

// Handle wallet selection
if(isset($_POST['address'])){
	checkAddress($conn, $_POST['address']);
	$addresses = array();
	//$addresses = getAddresses($conn);
	$addresses[0] = $_POST['address'];
	$policies = array();
	$policies = getPolicies($conn);
	verifyNFTs($conn, $addresses, $policies);
}

$filterby = "";
if(isset($_POST['filterby'])){
	$filterby = $_POST['filterby'];
}

//Item purchases
if(isset($_POST['item_id'])) {
	$quantity = getItemQuantity($conn, $_POST['item_id']);
	if($quantity >= 1){
		$price = getItemPrice($conn, $_POST['item_id']);
		if($_POST['project_id'] == 7){
			$price = $price/10;
		}
		$balance = getBalance($conn, $_POST['project_id']);
		
		if($balance >= $price){
			updateBalance($conn, $user_id, $_POST['project_id'], -$price);
			updateQuantity($conn, $_POST['item_id']);
		}else{
			alert("You do not have enough currency to purchase this item.");
		}
		
		# Open the DM first
		$newDM = MakeRequest('/users/@me/channels', array("recipient_id" => "772831523899965440"));
		# Check if DM is created, if yes, let's send a message to this channel.
		if(isset($newDM["id"])) {
		    $newMessage = MakeRequest("/channels/".$newDM["id"]."/messages", array("content" => "Hello World. \r\n https://image-optimizer.jpgstoreapis.com/QmSJMAdXhMbk5n2YvGkcZ1bhGSxrStoQkJCvpGEGeNPeQV?width=600"));
		}
	}else{
		alert("You cannot purchase this item because it is out of stock.");
	}

	// Check to make sure there's still enough quantity
	// Check if user has the correct balance
	// Check what currency they purchased with
	
	// Reduce quantity by 1 upon successful purchase
	// Send webhook announcing purchase to discord
	// Send private message to project owner to fulfill purchase with user address
	/*
	// Disable purchasing of items if the game has already been played. This is a fallback error in case someone tried to hack the HTML.
	if(!isset($_SESSION['userData']['current_score']) || checkSquadCount($conn) > 0){
		// Buy item and pass false flag since it's not a Drop Box transaction
		buyItem($conn, $_POST['item_id'], false);
	}else{
		?><script type="text/javascript">alert("You've already played Drop Ship. You cannot purchase items until a new game is live.");</script><?php
	}*/
	//alert("");
}

function filterNFTs($page){
	$anchor = "";
	if($page == "dashboard"){
		$anchor = "#holdings";
	}
	echo'
	<div id="filter-nfts">
		<label for="filterNFTs"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterNFTs(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="None">Project</option>
			<option value="None">All</option>
			<option value="1">Galactico</option>
			<option value="2">Ohh Meed</option>
			<option value="3">HYPE</option>
			<option value="4">Sinder Skullz</option>
			<option value="5">Kimosabe Art</option>
			<option value="6">Crypties</option>
			<option value="7">Skulliance</option>
		</select>
		<form id="filterNFTsForm" action="'.$page.'.php'.$anchor.'" method="post">
		  <input type="hidden" id="filterby" name="filterby" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
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
					  <input type="submit" value="Submit" style="display:none;">
					</form>
				</li>
				</div>
		<?php
		$balances = getBalances($conn);
		foreach($balances AS $currency => $balance){ 
			?>
			<li class="role"><img class="icon" src="icons/<?php echo strtolower(str_replace("$", "", $currency));?>.png"/>
				<?php
				echo $balance." ".$currency;
				?>
			</li>
		<?php } ?>
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