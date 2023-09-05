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
if(!empty($roles)){
	foreach ($roles as $key => $roleData) {
		switch ($roleData) {
		  case "949930195584954378":
			$member = true;
			break;
		  default:
			break;
		}
	}
}else{
	// Redirect non-members to the splash page for membership information
	header("Location: info.php");	
}
/*
if(!$member){
	header("Location: https://skulliance.io/staking/info.php");
}else{
	// Call initial DB functions
	checkUser($conn);
}*/

// Call initial DB functions
if(sizeof(getAddressesDiscord($conn)) != 0){
	checkUser($conn);
}

$projects = array();
$projects = getProjects($conn);
$creators = array();
foreach($projects AS $id => $project){
	$creators[$id] = $project["discord_id"];
}

//Item purchases
if(isset($_POST['item_id'])) {
	if($member){
		if(!checkTransaction($conn, $_POST['item_id'])){
			$quantity = getItemQuantity($conn, $_POST['item_id']);
			if($quantity >= 1){
				$project = array();
				$project = getProjectInfo($conn, $_POST['primary_project_id']);
				$price = getItemPrice($conn, $_POST['item_id']);
				if($_POST['project_id'] == 7 && $_POST['primary_project_id'] != 7){
					$price = $price/$project["divider"];
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
	}else{
		// Redirect non-members to the splash page for membership information
		header("Location: info.php");
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
		$description = $_POST['name']." listed for ".number_format($_POST['price'])." $".$project["currency"]." by ".getUsername($conn)."\r\nQuantity: ".$_POST['quantity'];
		$imageurl = $_POST['image_url'];
		discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
	}else{
		alert("Please fill out all the fields in the item submission form.");
	}
}

// Handle wallet selection
if(isset($_POST['stakeaddress'])){
	checkUser($conn);
	checkAddress($conn, $_POST['stakeaddress'], $_POST['address']);
	$addresses = array();
	//$addresses = getAddresses($conn);
	$addresses[0] = $_POST['stakeaddress'];
	$policies = array();
	$policies = getPolicies($conn);
	verifyNFTs($conn, $addresses, $policies);
	assignRole($_SESSION['userData']['discord_id'], "1119732763956871199");
	alert("Your wallet with stake address: ".$_POST['stakeaddress']." has been successfully connected. The qualifying NFTs in your wallet have now been verified and will automatically begin accruing rewards nightly. You can connect additional wallets as well. They will not replace the wallet you just connected. You have also been assigned the Staker role in the Skulliance discord. Enjoy Skulliance staking!");
}

// Crafting
if(isset($_POST['balance'])){
	$minbalance = 0;
	$minbalance = getMinimumBalance($conn);
	// Double check submitted balance before crafting
	if($_POST['balance'] > 0 && $_POST['balance'] <= $minbalance){
		craft($conn, $_POST['balance']);
		alert("You have successfully crafted ".number_format($_POST['balance'])." \$DIAMOND. ".number_format($_POST['balance'])." of every other project currency has been deducted from your balances.");
	}
}

// Shattering
if(isset($_POST['diamond'])){
	$balances = array();
	$balances = getBalances($conn);
	$diamond = $balances["\$DIAMOND"];
	// Double check submitted balance before crafting
	if($_POST['diamond'] > 0 && $_POST['diamond'] <= $diamond){
		shatter($conn, $_POST['diamond']);
		alert("You have successfully shattered ".number_format($_POST['diamond'])." \$DIAMOND. ".number_format($_POST['diamond'])." of every other project currency has been added to your balances.");
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

function renderCurrency($conn, $skulliance=true){
	if($skulliance == true){
		$balances = getBalances($conn);
	}else{
		$balances = getBalances($conn, false);
	}
	if(isset($balances)){
	foreach($balances AS $currency => $balance){ 
		?>
		<li class="role"><img class="icon rounded-full" src="icons/<?php echo strtolower(str_replace("$", "", $currency));?>.webp"/>
			<?php
			echo number_format($balance)." ".$currency;
			?>
		</li>
	<?php }} ?>
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
	if(!empty($balances)){
		$diamond = $balances["\$DIAMOND"];
		unset($balances["\$DIAMOND"]);
		$zero = false;
		foreach($balances AS $currency => $balance){
			if($balance == 0){
				$zero = true;
			}
		}
		if($zero){
			echo "You do not have balances for all currency to craft.<br><br>Purchase NFTs from every project in the Skulliance in order to craft \$DIAMOND.";
		}else{
			?>
			<form onsubmit="return confirm('Do you really want to convert all currency to $DIAMOND?');" id="craftingForm" action="<?php echo $page; ?>.php" method="post">
			  Convert the following amount of core project currency to $DIAMOND:<br><br>
			  <img class="icon" src="icons/diamond.png">MAX&nbsp;
			  <input type="number" size="10" id="balance" name="balance" min="1" max="<?php echo min($balances);?>" value="<?php echo min($balances);?>">	
			  <input type="submit" value="Convert" class="small-button">
			</form>
			<?php
		}
		if($diamond > 0){
			?>
			</li>
			<li class="role">
			<form onsubmit="return confirm('Do you really want to shatter this $DIAMOND?');" id="diamondForm" action="<?php echo $page; ?>.php" method="post">
			  <br>Shatter the following amount of $DIAMOND to equal parts core currency:<br><br>
			  <img class="icon" src="icons/diamond.png">MAX&nbsp;
			  <input type="number" size="10" id="diamond" name="diamond" min="1" max="<?php echo $diamond;?>" value="<?php echo $diamond;?>">	
			  <input type="submit" value="Shatter" class="small-button">
			</form>
			<?php
		}
	}
	
	?>
	</li>
	</ul>
	<?php
}

function renderItemSubmissionForm($creators, $page){
	global $conn;
	$projects = getProjects($conn);
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
			<?php
	  		foreach($projects AS $id => $project){
				if($_SESSION['userData']['discord_id'] == $project["discord_id"]){
					echo '<option value="'.$id.'" selected>'.$project["name"].'</option>';
				}else{
					echo '<option value="'.$id.'">'.$project["name"].'</option>';
				}
			}
	  		?>
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
	global $conn;
	$projects = getProjects($conn);
	$anchor = "";
	if($page == "dashboard"){
		$anchor = "#holdings";
	}
	echo'
	<div id="filter-nfts">
		<label for="filterNFTs"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterNFTs(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="None">Project</option>
			<option value="None">All</option>';
			foreach($projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
		echo '
		</select>
		<form id="filterNFTsForm" action="'.$page.'.php'.$anchor.'" method="post">
		  <input type="hidden" id="filterby" name="filterby" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
}

function filterLeaderboard($page){
	global $conn;
	$projects = getProjects($conn);
	$anchor = "";
	echo'
	<div id="filter-nfts">
		<label for="filterLeaderboard"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterLeaderboard(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="0">Project</option>
			<option value="0">All</option>';
			foreach($projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
		echo '
		</select>
		<form id="filterLeaderboardForm" action="'.$page.'.php'.$anchor.'" method="post">
		  <input type="hidden" id="filterby" name="filterby" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
}

function filterPolicies($page){
	global $conn;
	$projects = getProjects($conn);
	$anchor = "";
	echo '
	<div id="filter-nfts">
		<label for="filterPolicies"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterPolicies(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="0">Project</option>
			<option value="0">All</option>';
			foreach($projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
		echo '
		</select>
		<form id="filterPoliciesForm" action="'.$page.'.php'.$anchor.'" method="post">
		  <input type="hidden" id="filterby" name="filterby" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
}

function filterItems($page){
	global $conn;
	$projects = getProjects($conn);
	$anchor = "";
	echo '
	<div id="filter-nfts">
		<label for="filterItems"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterItems(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="0">Project</option>
			<option value="0">All</option>';
			foreach($projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
		echo '
		</select>
		<form id="filterItemsForm" action="'.$page.'.php'.$anchor.'" method="post">
		  <input type="hidden" id="filterby" name="filterby" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
}

?>