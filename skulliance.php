<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if(!$_SESSION['logged_in']){
  header('Location: error.php');
  exit();
}
extract($_SESSION['userData']);
//print_r($_SESSION['userData']);
//print_r($_POST);
//exit();

$avatar_url = "https://cdn.discordapp.com/avatars/$discord_id/$avatar.jpg";

// Initiate variables
$member = false;
$roles = $_SESSION['userData']['roles'];
foreach ($roles as $key => $roleData) {
	switch ($roleData) {
	  case "949930195584954378":
		$member = true;
		break;
	  default:
		/*
		$dropship = 'false';
		$heavy = 'false';
		$medium = 'false';
		$light = 'false';
		$base = 'false';
		$melee = 'false';
		$demolition = 'false';
		$extralife = 'false';
		$features = 0;*/
		break;
	}
}
if(!$member){
	header("Location: https://discord.com/invite/JqqBZBrph2");
}

// Call initial DB functions
checkUser($conn);

$projects = array();
$projects = getProjects($conn);
$creators = array();
foreach($projects AS $id => $project){
	$creators[$id] = $project["discord_id"];
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
				$description = $item["name"]." purchased for ".number_format($price)." $".$item["currency"]." by ".getUsername($conn);
				$imageurl = $item["image_url"];
				discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
				$project = array();
				$project = getProjectInfo($conn, $_POST['primary_project_id']);
				if($item["override"] == "0"){
					$discord_id = $project["discord_id"];
				}else{
					$discord_id = "772831523899965440";
				}
				# Open the DM first
				$newDM = MakeRequest('/users/@me/channels', array("recipient_id" => $discord_id));
				# Check if DM is created, if yes, let's send a message to this channel.
				if(isset($newDM["id"])) {
					$content = $item["name"]." purchased for ".$price." $".$item["currency"]." by ".getUsername($conn). "\r\n ".$imageurl." \r\n Please send NFT to ".getAddress($conn);
				    $newMessage = MakeRequest("/channels/".$newDM["id"]."/messages", array("content" => $content));
				}
				alert("Congratulations! You have successfully purchased this item. The creator has received your wallet address and will send the item at their earliest convenience. Please be patient.");
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

// Item submission to store
if(isset($_POST['name'])){
	if($_POST['name'] != "" && $_POST['image_url'] != "" && $_POST['price'] != "" && $_POST['quantity'] != "" && $_POST['project_id'] != ""){
		if(isset($_POST['override'])){
			createItem($conn, $_POST['name'], $_POST['image_url'], $_POST['price'], $_POST['quantity'], $_POST['project_id'], $_POST['override']);
		}else{
			createItem($conn, $_POST['name'], $_POST['image_url'], $_POST['price'], $_POST['quantity'], $_POST['project_id']);
		}
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

function renderWalletConnection($page){
	echo '<ul>
	<div class="wallet-connect">
	<li class="role"><img class="icon" src="icons/wallet.png"/>
		<label for="wallets"><strong>Connect</strong>&nbsp;</label>
		<select onchange="javascript:connectWallet(this.options[this.selectedIndex].value);" name="wallets" id="wallets">
			<option value="none">Wallet</option>
		</select>
		<form id="addressForm" action="'.$page.'.php" method="post">
		  <input type="hidden" id="wallet" name="wallet" value="">	
		  <input type="hidden" id="address" name="address" value="">
		  <input type="hidden" id="stakeaddress" name="stakeaddress" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</li>
	</div>';
}

function renderCurrency($conn){
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
	<?php
}

function renderCrafting($conn, $page){
	?>
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
		echo "You do not have balances for all currency to craft. Purchase NFTs from every project in the Skulliance in order to craft \$DIAMOND.";
	}else{
		?>
		<form id="craftingForm" action="<?php echo $page; ?>.php" method="post">
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
	<?php
}

function renderItemSubmissionForm($creators, $page){
	// Check if user has permissions to submit store items
	if(in_array($_SESSION['userData']['discord_id'], $creators)){
	?>
	<div class="nft offering">
	<h2>Item Submission</h2>
	<form id="itemForm" action="<?php echo $page; ?>.php" method="post" class="nft-data">
	  <table>
		<tr>
	  <td><label for="name">NFT Name:</label></td>
	  <td><input type="text" id="name" name="name"></td>
	    </tr>
		<tr>
	  <td><label for="image_url">Image URL:</label></td>
	  <td><input type="text" id="image_url" name="image_url"></td>
	    </tr>
	    <tr>
	  <td><label for="price">Price:</label></td>
	  <td><input type="number" id="price" name="price"></td>
	    </tr>
	    <tr>
	  <td><label for="quantity">Quantity:</label></td>
	  <td><input type="number" id="quantity" name="quantity"></td>
	    </tr>
	    <tr>
	  <td><label for="project_id">Project:</label></td>
	  <td><select id="project_id" name="project_id">
			<option value="1" <?php echo ($_SESSION['userData']['discord_id'] == $creators["1"])?"selected":""; ?> >Galactico</option>
			<option value="2" <?php echo ($_SESSION['userData']['discord_id'] == $creators["2"])?"selected":""; ?>>Ohh Meed</option>
			<option value="3" <?php echo ($_SESSION['userData']['discord_id'] == $creators["3"])?"selected":""; ?>>H.Y.P.E.</option>
			<option value="4" <?php echo ($_SESSION['userData']['discord_id'] == $creators["4"])?"selected":""; ?>>Sinder Skullz</option>
			<option value="5" <?php echo ($_SESSION['userData']['discord_id'] == $creators["5"])?"selected":""; ?>>Kimosabe Art</option>
			<option value="6" <?php echo ($_SESSION['userData']['discord_id'] == $creators["6"])?"selected":""; ?>>Crypties</option>
			<option value="7" <?php echo ($_SESSION['userData']['discord_id'] == $creators["7"])?"selected":""; ?>>Skulliance</option>
	  </select></td>
	  </tr>
	  <?php
	  if($_SESSION['userData']['discord_id'] == "772831523899965440"){
	  ?>
	  <tr>
		  <td><label for="override">Override:</label></td>
		  <td><input type="checkbox" id="override" name="override" value="1"></td>
	  </tr>
	  <?php } ?>
	  <tr>	
	  <td>&nbsp;</td>
	  <td><input type="submit" value="Submit" class="small-button"><!--<button class="small-button">Clear</button>--></td>
	    </tr>
	  </table>
	</form>
	</div>
	<?php
	}
}

$filterby = "";
if(isset($_POST['filterby'])){
	$filterby = $_POST['filterby'];
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
			<option value="3">H.Y.P.E.</option>
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

function filterLeaderboard($page){
	$anchor = "";
	echo'
	<div id="filter-nfts">
		<label for="filterLeaderboard"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterLeaderboard(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="0">Project</option>
			<option value="0">All</option>
			<option value="1">Galactico</option>
			<option value="2">Ohh Meed</option>
			<option value="3">H.Y.P.E.</option>
			<option value="4">Sinder Skullz</option>
			<option value="5">Kimosabe Art</option>
			<option value="6">Crypties</option>
			<option value="7">Skulliance</option>
		</select>
		<form id="filterLeaderboardForm" action="'.$page.'.php'.$anchor.'" method="post">
		  <input type="hidden" id="filterby" name="filterby" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
}

?>