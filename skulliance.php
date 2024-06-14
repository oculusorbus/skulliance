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

/* Logic for maintenance mode that allows restriction to specific discord ids for troubleshooting
if($_SESSION['userData']['discord_id'] != "772831523899965440"){
	echo "The staking platform is in maintenance mode. Thanks for your patience.";
	exit;
}*/

// Call initial DB functions
if(sizeof(getAddressesDiscord($conn)) != 0){
	checkUser($conn);
}

// Initiate variables
$member = false;
$elite = false;
$innercircle = false;
$roles = $_SESSION['userData']['roles'];
if(!empty($roles)){
	foreach ($roles as $key => $roleData) {
		switch ($roleData) {
		  case "949930195584954378":
			$member = true;
			break;
  		  case "949930360681140274":
  			$elite = true;
  			break;
		  case "949930529841635348";
		    $innercircle = true;
			break;
		  default:
			break;
		}
	}
	if(sizeof(getAddressesDiscord($conn)) != 0 && str_contains($_SERVER['REQUEST_URI'], "dashboard.php")){
		$status = array();
		$status = verifyMembershipNFTs($conn, $roles);
		// Member
		if(!$member){
			$member = member($status, true);
			if($member){
				$elite = elite($status, true);
			}
			if($elite){
				$innercircle = innercircle($status, true);
			}
		}else{ // Default to member check in case already a member but no longer own qualifying NFTs
			$member = member($status, false, $roles);
		}
		// Elite
		if($member && !$elite){
			$elite = elite($status, true);
			if($elite){
				$innercircle = innercircle($status, true);
			}
		}else{ // Default to elite check in case already an elite member but no longer own qualifying NFTs
			$elite = elite($status, false, $roles);
		}
		// Inner Circle
		if($member && $elite && !$innercircle){
			$innercircle = innercircle($status, true);
		}else{ // Default to inner circle check in case already an inner circle member but no longer own qualifying NFTs
			$innercircle = innercircle($status, false, $roles);
		}
		if(!$status["diamond"] && in_array("1097916579250978907", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "1097916579250978907", "delete");
		}
		if(!$status["crypties"] && in_array("944816668327166002", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "944816668327166002", "delete");
		}
		if(!$status["kimosabe"] && in_array("944817126705885234", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "944817126705885234", "delete");
		}
		if(!$status["sinder"] && in_array("944817421976490056", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "944817421976490056", "delete");
		}
		if(!$status["hype"] && in_array("952215678100852807", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "952215678100852807", "delete");
		}
		if(!$status["ohhmeed"] && in_array("944816868911370290", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "944816868911370290", "delete");
		}
		if(!$status["galactico"] && in_array("944817486124171324", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "944817486124171324", "delete");
		}
	}
}else{
	// Redirect non-members to the splash page for membership information
	header("Location: http://discord.gg/JqqBZBrph2");	
}

function member($status, $message, $roles=array()){
	if($status["crypties"] && $status["kimosabe"] && $status["sinder"]){
		if(!in_array("949930195584954378", $roles)){
			array_push($_SESSION['userData']['roles'], "949930195584954378");
			// Member Role
			assignRole($_SESSION['userData']['discord_id'], "949930195584954378");
		}
		if($message){
			$title = "Congratulations ".$_SESSION['userData']['name']."!";
			$description = "<@".$_SESSION['userData']['discord_id']."> just became an official member of the Skulliance!";
			$imageurl = "https://www.madballs.net/skulliance/gifs/meme3.gif";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "general");
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "member");
		}
		return true;
	}else{
		if(in_array("949930195584954378", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "949930195584954378", "delete");
		}
		return false;
	}
}

function elite($status, $message, $roles=array()){
	if($status["hype"] && $status["ohhmeed"] && $status["galactico"]){
		if(!in_array("949930360681140274", $roles)){
			array_push($_SESSION['userData']['roles'], "949930360681140274");
			// Elite Role
			assignRole($_SESSION['userData']['discord_id'], "949930360681140274");
		}
		if($message){
			$title = "Congratulations ".$_SESSION['userData']['name']."!";
			$description = "<@".$_SESSION['userData']['discord_id']."> just became an elite member of the Skulliance!";
			$imageurl = "https://www.madballs.net/skulliance/gifs/meme2.gif";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "general");
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "elite");
		}
		return true;
	}else{
		if(in_array("949930360681140274", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "949930360681140274", "delete");
		}
		return false;
	}
}

function innercircle($status, $message, $roles=array()){
	if($status["diamond"]){
		if(!in_array("949930529841635348", $roles)){
			array_push($_SESSION['userData']['roles'], "949930529841635348");
			// Inner Circle Role
			assignRole($_SESSION['userData']['discord_id'], "949930529841635348");
		}
		if($message){
			$title = "Congratulations ".$_SESSION['userData']['name']."!";
			$description = "<@".$_SESSION['userData']['discord_id']."> just became an official member of the Skulliance Inner Circle!";
			$imageurl = "https://www.madballs.net/skulliance/gifs/meme1.gif";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "general");
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "innercircle");
		}
		return true;
	}else{
		if(in_array("949930529841635348", $roles)){
			assignRole($_SESSION['userData']['discord_id'], "949930529841635348", "delete");
		}
		return false;
	}
}
/*
if(!$member){
	header("Location: https://skulliance.io/staking/info.php");
}else{
	// Call initial DB functions
	checkUser($conn);
}*/

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
					$description = $item["name"]." purchased for ".number_format($price)." $".$item["currency"]." by "."<@".getDiscordID($conn).">";
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
						$content = $item["name"]." purchased for ".$price." ".$item["currency"]." by "."<@".getDiscordID($conn).">". "\r\n ".$imageurl." \r\n Please send NFT to ".getAddress($conn);
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
		$description = $_POST['name']." listed for ".number_format($_POST['price'])." $".$project["currency"]." by "."<@".getDiscordID($conn).">"."\r\nQuantity: ".$_POST['quantity'];
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
	// Get all NFT asset IDs to determine whether to update DB records, saves on DB resources instead of individual DB calls to check NFT presence
	$asset_ids = array();
	$asset_ids = getNFTAssetIDs($conn);
	verifyNFTs($conn, $addresses, $policies, $asset_ids);
	assignRole($_SESSION['userData']['discord_id'], "1119732763956871199");
	alert("Your wallet with stake address: ".$_POST['stakeaddress']." has been successfully connected. The qualifying NFTs in your wallet have now been verified and will automatically begin accruing rewards nightly. You can connect additional wallets as well. They will not replace the wallet you just connected. You have also been assigned the Staker role in the Skulliance discord. Enjoy Skulliance staking!");
}

// Refresh User Wallets
if(isset($_POST['refresh'])){
	if(isset($_SESSION['userData']['user_id'])){ 
		// Get all NFT asset IDs to determine whether to update DB records, saves on DB resources instead of individual DB calls to check NFT presence
		$asset_ids = array();
		$asset_ids = getNFTAssetIDs($conn);
		// Verify all NFTs from wallets in the DB for a specific user
		verifyNFTs($conn, getAddresses($conn), getPolicies($conn), $asset_ids);
		alert("Your wallet(s) have been successfully refreshed. Any newly acquired qualifying NFTs have been accounted for in your wallet and will automatically begin accruing rewards nightly. You can connect additional wallets as well. They will not replace the wallets you have already connected. Enjoy Skulliance staking!");
	}
}

// Crafting
if(isset($_POST['balance'])){
	$minbalance = 0;
	$minbalance = getMinimumBalance($conn);
	// Double check submitted balance before crafting
	if($_POST['balance'] > 0 && $_POST['balance'] <= $minbalance){
		craft($conn, $_POST['balance']);
		alert("You have successfully crafted ".number_format($_POST['balance'])." DIAMOND. ".number_format($_POST['balance'])." of every other project currency has been deducted from your balances.");
	}
}

// Shattering
if(isset($_POST['diamond'])){
	$balances = array();
	$balances = getBalances($conn);
	$diamond = $balances["DIAMOND"];
	// Double check submitted balance before crafting
	if($_POST['diamond'] > 0 && $_POST['diamond'] <= $diamond){
		shatter($conn, $_POST['diamond']);
		alert("You have successfully shattered ".number_format($_POST['diamond'])." DIAMOND. ".number_format($_POST['diamond'])." of every other project currency has been added to your balances.");
	}
}

// Burning
if(isset($_POST['carbon'])){
	$carbon_balance = getBalance($conn, 15);
	if($_POST['carbon'] <= $carbon_balance){
		burn($conn, $_POST['carbon'], 15);
		alert("You have successfully burned ".number_format($_POST['carbon'])." CARBON and formed ".(number_format($_POST['carbon']/100))." DIAMOND.");
	}
}

function renderVisibility($page){
	global $conn;
	
	if(isset($_SESSION['userData']['user_id'])){
		$visibility = 0;
		if(isset($_POST['visibility'])){
			if($_POST['visibility'] == "hidden"){
				$visibility = 1;
			}else if($_POST['visibility'] == "visible"){
				$visibility = 2;
			}
			updateVisibility($conn, $visibility);
		}
	
		$visibility = 0;
		$hidden = "";
		$visible = "";
		$visibility = getVisibility($conn);
		if($visibility == "2"){
			$visible = "checked";
		}else if($visibility == "1"){
			$hidden = "checked";
		}
	
		echo '
		<div class="visibility">
		<li class="role">
			<strong>NFT Collection Visibility from Leaderboards</strong>
		</li>
		<li class="role">
			<form id="privacyForm" action="'.$page.'.php" method="post">
			  <br>	
			  <input type="radio" id="visible" name="visibility" value="visible" '.$visible.'>
			  <label for="visible">Visible</label><br>
			  <input type="radio" id="hidden" name="visibility" value="hidden" '.$hidden.'>
			  <label for="hidden">Hidden</label><br>
			  <br>
			  <input type="submit" value="Submit" class="small-button" id="visibility-button">
			  <br><br>
			</form>
		</li>
		</div>';
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
		</form>';
		// Check if already a user before allowing wallet refreshes
		if(isset($_SESSION['userData']['user_id'])){ 
			echo '&nbsp;<form id="refreshWallet" action="'.$page.'.php" method="post">
			  <input type="hidden" id="refresh" name="refresh" value="refresh">
			  <input type="submit" value="Refresh" class="small-button">
			</form>';
		}
	echo '</li>
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
		<li class="role"><img class="icon" src="icons/<?php echo strtolower(str_replace("$", "", $currency));?>.png"/>
			<?php
			echo number_format($balance)." ".$currency;
			?>
		</li>
	<?php }} ?>
		</ul>
	<?php
}

function renderDailyRewards($rewards){
	$count = 0;
	// Existing rewards
	if(is_array($rewards)){
		foreach($rewards AS $index => $reward){
			renderDailyReward($reward["day"], $reward["currency"], $reward["amount"]);
		}
		$count = count($rewards);
	}else{
		$count = 0;
	}
	// Potential rewards
	$reward_tiers = getRewardTiers();
	$count++;
	if($count <= 7){
	  renderDailyReward($count, "RANDOM".$count, $reward_tiers[$count], true);
	}
	$count++;
	for ($count; $count <= 7; $count++) {
      renderDailyReward($count, "RANDOM".$count, $reward_tiers[$count]);
	}	
}

function renderDailyReward($day, $currency, $amount, $reward=false) {
	$greyed_out = "";
	if(str_contains($currency, "RANDOM")){
		$greyed_out = " greyed-out";
	}
	if($reward){
		echo "<li class='role".$greyed_out."' id='reward'>";
	}else{
		echo "<li class='role".$greyed_out."'>";
	}
	echo "<strong>Day ".$day.":</strong> &nbsp;&nbsp;<img class='icon' src='icons/".strtolower($currency).".png'/> +".$amount." ".preg_replace('/[0-9]+/', '', $currency);
	echo "</li>";
}

function renderDailyRewardsSection(){
	global $conn;
	echo "<ul>";
	echo "<li class='role no-border-style'><strong>Daily Random Rewards Streak</strong></li>";
	echo "<li class='role'>Total Streaks Completed: ".getStreaksTotal($conn)."</li>";
	if(getDailyRewardEligibility($conn)) { 
		// Reset daily reward streak if yesterday's daily reward wasn't claimed
		if(!verifyYesterdaysRewards($conn)){
			resetDailyRewardStreak($conn);
		}
	}
	$days = getStreakRewards($conn);
	renderDailyRewards($days);
	if(getDailyRewardEligibility($conn)) { 
		echo '<li class="role no-border-style" id="claimed" style="display:none;">';
		echo '<strong>Daily Reward Claimed</strong>';
		echo '</li>';
		echo '<li class="role" id="remaining" style="display:none;">';
		echo '</li><br>';
		echo '<input id="claimRewardButton" type="button" value="Claim Daily Reward" class="button" onclick="javascript:dailyReward();">';
	} else { 
			// Display 7 day completed rewards despite streak being reset
			/*
			$current_streak = getCurrentDailyRewardStreak($conn);
			if($current_streak == 0){
				$rewards = getCompletedRewards($conn);
				renderDailyRewards($rewards);
			}*/
		echo '<li class="role no-border-style">';
		echo '<strong>Daily Reward Already Claimed</strong>';
		echo '</li>';
		echo '<li class="role">';
		echo getRewardTimeRemaining($conn);
		echo '</li>';
 	} 
	echo '</ul>';
}

function getRewardTimeRemaining($conn){
	$maxdate = getMaxDateCreated($conn);
	if(isset($maxdate)){
		$date = strtotime('+1 day', strtotime(date('Y-m-d 00:00:00', strtotime($maxdate))));
		$remaining = $date - time();
		$hours_remaining = floor(($remaining % 86400) / 3600);
		$minutes_remaining = floor(($remaining % 3600) / 60);
		return $hours_remaining." hours and ".$minutes_remaining." minutes until next reward";
	}else{
		return "";
	}
}

function renderCrafting($conn, $page){
	?>
	<ul>
	<?php 
	$balances = array();
	$balances = getBalances($conn);
	if(!empty($balances)){
		$diamond = $balances["DIAMOND"];
		unset($balances["DIAMOND"]);
		$carbon = 0;
		// Double check to make sure CARBON exists before referencing it.
		if(isset($balances["CARBON"])){
			$carbon = $balances["CARBON"];
			unset($balances["CARBON"]);
		}
		$zero = false;
		foreach($balances AS $currency => $balance){
			if($balance == 0){
				$zero = true;
			}
		}
		if($zero){
			echo "You do not have balances for all core currency listed above to craft.<br><br>Purchase NFTs from every core project in the Skulliance in order to craft DIAMOND.";
		}else{
			?>
			<li class="role">
			<form onsubmit="return confirm('Do you really want to convert all currency to DIAMOND?');" id="craftingForm" action="<?php echo $page; ?>.php" method="post">
			  <strong>Convert Core Points to DIAMOND</strong><br><br>
			  <img class="icon" src="icons/diamond.png">MAX&nbsp;
			  <input type="number" size="10" id="balance" name="balance" min="1" max="<?php echo min($balances);?>" value="<?php echo min($balances);?>">	
			  <input type="submit" value="Convert" class="small-button">
			</form>
			</li>
			<?php
		}
		if($diamond > 0){
			?>
			<li class="role">
			<form onsubmit="return confirm('Do you really want to shatter this DIAMOND?');" id="diamondForm" action="<?php echo $page; ?>.php" method="post">
			  <br><strong>Shatter DIAMOND to Core Points</strong><br><br>
			  <img class="icon" src="icons/diamond.png">MAX&nbsp;
			  <input type="number" size="10" id="diamond" name="diamond" min="1" max="<?php echo $diamond;?>" value="<?php echo $diamond;?>">	
			  <input type="submit" value="Shatter" class="small-button">
			</form>
			</li>
			<?php
		}
		if($carbon >= 1000){
			$carbon_index = floor($carbon/1000);
			?>
			<li class="role">
			<form onsubmit="return confirm('Do you really want to convert CARBON to DIAMOND?');" id="carbonForm" action="<?php echo $page; ?>.php" method="post">
			  <br><strong>Burn CARBON to Form DIAMOND</strong><br><br>
			  <img class="icon" src="icons/carbon.png">
		      <select name="carbon" id="carbon">
			  <?php
			  for ($x = 1; $x <= $carbon_index; $x++) {?>
				    <option <?php if($carbon_index == $x){echo "selected";} ?> value="<?php echo $x*1000; ?>"><?php echo $x*1000; ?> CARBON to <?php echo $x*10; ?> DIAMOND</option>
			  <?php } ?>
			  </select>
			  <input type="submit" value="Burn" class="small-button">
			</form>
			</li>
			<?php
		}else{
			echo '<li class="role">';
			echo "You need at least 100 CARBON to form DIAMOND.<br><br>Delegate your core project NFTs to Diamond Skulls to earn CARBON.";
			echo '</li>';
		}
	}
	
	?>

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
if(!isset($_SESSION['userData']['filterby'])){
	$_SESSION['userData']['filterby'] = "core";
}
if(isset($_POST['filterby'])){
	$filterby = $_POST['filterby'];
	$_SESSION['userData']['filterby'] = $filterby;
}

$filterbydiamond = "";
if(isset($_POST['filterbydiamond'])){
	$filterbydiamond = $_POST['filterbydiamond'];
	$_SESSION['userData']['diamond_skull_id'] = "";
}

$diamond_skull_id = "";
if(isset($_POST['diamond_skull_id'])){
	$diamond_skull_id = $_POST['diamond_skull_id'];
}

$projects = array();
$projects[1] = 1;
$projects[2] = 2;
$projects[3] = 3;
$projects[4] = 4;
$projects[5] = 4;
$projects[6] = 5;

$project_names = array();
$project_names[1] = "Galactico";
$project_names[2] = "Ohh Meed";
$project_names[3] = "H.Y.P.E.";
$project_names[4] = "Sinder Skullz";
$project_names[5] = "Kimosabe Art";
$project_names[6] = "Crypties";

$nft_id = "";
if(isset($_POST['nft_id'])){
	if($member == true){
		$nft_id = $_POST['nft_id'];
		$project_id = getNFTProjectID($conn, $nft_id);
		$availability = checkDiamondSkullProjectAvailability($conn, $_SESSION['userData']['diamond_skull_id'], $project_id, $projects);
		if($availability == true){
			// Check whether NFT id is a valid core project that can delegate to prevent hacking
		
			// Check whether NFT has already been delegated
			$delegated = checkNFTDelegationStatus($conn, $nft_id);
			if($delegated == false){
				addDiamondSkullNFT($conn, $_SESSION['userData']['diamond_skull_id'], $nft_id);
			}else{
				alert("Your NFT was not delegated. This NFT has already been delegated to a Diamond Skull.");
			}
		}else{
			alert("All ".$project_names[$project_id]." slots have been taken for this Diamond Skull.");
		}
	}else{
		alert("You must be a member of Skulliance in order to participate in Diamond Skull delegation and earn CARBON rewards. Join Skulliance Discord to verify your wallet and request membership. You will need to logout and login to the staking platform in order for your membership to be recognized.");
	}
}

if(isset($_POST['remove_nft_id'])){
	$nft_id = $_POST['remove_nft_id'];
	// Verify user owns delegated NFT before removing
	// Function here
	
	// Remove Diamond Skull NFT delegation
	removeDiamondSkullNFT($conn, $_SESSION['userData']['diamond_skull_id'], $nft_id);
}

function filterNFTs($page, $username=""){
	global $conn;
	$core_projects = getProjects($conn, "core");
	$partner_projects = getProjects($conn, "partner");
	$anchor = "";
	if($page == "dashboard" || $page == "showcase"){
		$anchor = "#holdings";
	}
	if($username != ""){
		$username = "?username=".$username;
	}
	echo'
	<div id="filter-nfts">
		<label for="filterNFTs"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterNFTs(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="None">Project</option>
			<option value="None">All</option>
			<optgroup label="Core Projects">';
			foreach($core_projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup><optgroup label="Partner Projects">';
			foreach($partner_projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup>';
		echo '
		</select>
		<form id="filterNFTsForm" action="'.$page.'.php'.$username.$anchor.'" method="post">
		  <input type="hidden" id="filterby" name="filterby" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
}

function filterCoreNFTs($page){
	global $conn;
	$core_projects = getProjects($conn, "core");
	$anchor = "#holdings";
	echo'
	<div id="filter-nfts">
		<label for="filterNFTs"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterNFTs(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="None">Project</option>';
			foreach($core_projects AS $id => $project){
				if($id != 7){
					echo '<option value="'.$id.'">'.$project["name"].'</option>';
				}
			}
		echo '
		</select>
		<form id="filterNFTsForm" action="'.$page.'.php'.$anchor.'" method="post">
		  <input type="hidden" id="filterby" name="filterby" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
}

function filterDiamondSkulls($page){
	global $conn;
	$anchor = "#diamond-skulls";
	echo'
	<div id="filter-nfts">
		<label for="filterNFTs"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterDiamondSkulls(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="MY">Diamond Skulls</option>
			<option value="MY">My Diamond Skulls</option>
			<option value="DELEGATED">My Delegated Diamond Skulls</option>
			<option value="EMPTY">Empty Diamond Skulls</option>
			<option value="ALL DELEGATED">Delegated Diamond Skulls</option>
			<option value="ALL">All Diamond Skulls</option>';
		echo '
		</select>
		<form id="filterDiamondSkullsForm" action="'.$page.'.php'.$anchor.'" method="post">
		  <input type="hidden" id="filterbydiamond" name="filterbydiamond" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
}

function filterLeaderboard($page){
	global $conn;
	$core_projects = getProjects($conn, "core");
	$partner_projects = getProjects($conn, "partner");
	$anchor = "";
	echo'
	<div id="filter-nfts">
		<label for="filterLeaderboard"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterLeaderboard(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="0">Project</option>
			<option value="0">All</option>
			<option value="missions">All Missions</option>
			<option value="monthly">'.date('F').' Missions</option>
			<option value="15">Delegations</option>
			<optgroup label="Core Projects">';
			foreach($core_projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup><optgroup label="Partner Projects">';
			foreach($partner_projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup>';
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
	$core_projects = getProjects($conn, "core");
	$partner_projects = getProjects($conn, "partner");
	$anchor = "";
	echo '
	<div id="filter-nfts">
		<label for="filterPolicies"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterPolicies(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="0">Project</option>
			<option value="0">All</option>
			<optgroup label="Core Projects">';
			foreach($core_projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup><optgroup label="Partner Projects">';
			foreach($partner_projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup>';
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
	$core_projects = getProjects($conn, "core");
	$partner_projects = getProjects($conn, "partner");
	$anchor = "";
	echo '
	<div id="filter-nfts">
		<label for="filterItems"><strong>Filter By:</strong></label>
		<select onchange="javascript:filterItems(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
			<option value="0">Project</option>
			<option value="0">All</option>
			<option value="exclusive">Exclusive</option>
			<optgroup label="Core Projects">';
			foreach($core_projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup><optgroup label="Partner Projects">';
			foreach($partner_projects AS $id => $project){
				echo '<option value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup>';
		echo '
		</select>
		<form id="filterItemsForm" action="'.$page.'.php'.$anchor.'" method="post">
		  <input type="hidden" id="filterby" name="filterby" value="">
		  <input type="submit" value="Submit" style="display:none;">
		</form>
	</div>';
}

?>