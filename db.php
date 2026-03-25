<?php
include 'credentials/db_credentials.php';
include_once 'role.php';
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL ^ E_NOTICE);  


if(isset($_SESSION['userData']['discord_id'])){
	if($_SESSION['userData']['discord_id'] == $discordid_oculusorbus) {
		//$dbname = $dbbametest;
	}
}

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);
// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Handle Drop Ship DREAD AND Oculus Lounge MOON Rewards
if(isset($_POST['discord_id']) && isset($_POST['rank']) && isset($_POST['project_id'])) {
	// Lookup user by discord id
	$user_id = getUserIdFromDiscordId($conn, $_POST['discord_id']);
	// If user exists, add DREAD or MOON reward based on rank in game to balance and log credit in transaction history
	if($user_id != false){
		$subtotal = 0;
		if($_POST['rank'] == 1){
			$subtotal = 1000;
		}else if($_POST['rank'] == 2){
			$subtotal = 500;
		}else if($_POST['rank'] == 3){
			$subtotal = 250;
		}
		if($_POST['project_id'] == 1){
			$project_id = 2;
		}else if($_POST['project_id'] == 4){
			$project_id = 21;
		}
		updateBalance($conn, $user_id, $project_id, $subtotal);
		logCredit($conn, $user_id, $subtotal, $project_id);
	}
}

// Verify NFTs required for Membership
function verifyMembershipNFTs($conn, $roles){
	$sql = "SELECT DISTINCT projects.id AS project_id FROM nfts INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id WHERE nfts.user_id='".$_SESSION['userData']['user_id']."' AND project_id IN('7','6','5','4','3','2','1')";
	$result = $conn->query($sql);
	
	$status = array();
	$status["crypties"] = false;
	$status["kimosabe"] = false;
	$status["sinder"] = false;
	$status["hype"] = false;
	$status["ohhmeed"] = false;
	$status["galactico"] = false;
	$status["diamond"] = false;
	
	if ($result->num_rows > 0) {
  	  while($row = $result->fetch_assoc()) {
    	if($row["project_id"] == "7"){
    		$status["diamond"] = true;
			// Diamond Skull Role
			if(!in_array("1097916579250978907", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "1097916579250978907");
			}
    	}
      	if($row["project_id"] == "6"){
      		$status["crypties"] = true;
			// Crypties Role
			if(!in_array("944816668327166002", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "944816668327166002");
			}
      	}
      	if($row["project_id"] == "5"){
      		$status["kimosabe"] = true;
			// Kimosabe Role
			if(!in_array("944817126705885234", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "944817126705885234");
			}
      	}
      	if($row["project_id"] == "4"){
    		$status["sinder"] = true;
			// Sinder Role
			if(!in_array("944817421976490056", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "944817421976490056");
			}
      	}
      	if($row["project_id"] == "3"){
      		$status["hype"] = true;
			// HYPE Role
			if(!in_array("952215678100852807", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "952215678100852807");
			}
      	}
      	if($row["project_id"] == "2"){
      		$status["ohhmeed"] = true;
			// Ohh Meed Role
			if(!in_array("944816868911370290", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "944816868911370290");
			}
      	}
      	if($row["project_id"] == "1"){
    		$status["galactico"] = true;
			// Galactico Role
			if(!in_array("944817486124171324", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "944817486124171324");
			}
      	}
  	  }
	}
	return $status;
}

// Verify Ritual NFT roles
function verifyRitualNFTs($conn, $roles){
	$sql = "SELECT DISTINCT collections.name AS collection_name FROM nfts INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id WHERE nfts.user_id='".$_SESSION['userData']['user_id']."' AND project_id = '22'";
	$result = $conn->query($sql);
	
	$status = array();
	$status["BOGEYMAN"] = false;
	$status["RITUAL"] = false;
	$status["V/H/S"] = false;
	$status["JOHN DOE"] = false;
	$status["BEELZEBUB"] = false;
	$status["SKADA"] = false;
	$status["Nemonium x Ritual"] = false;
	
	if ($result->num_rows > 0) {
  	  while($row = $result->fetch_assoc()) {
    	if($row["collection_name"] == "BOGEYMAN"){
			$status["BOGEYMAN"] = true;
			if(!in_array("1258837960413937695", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "1258837960413937695", "", "1235869893664964608");
			}
		}
    	if($row["collection_name"] == "RITUAL"){
			$status["RITUAL"] = true;
			if(!in_array("1258838665304735785", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "1258838665304735785", "", "1235869893664964608");
			}
		}
    	if($row["collection_name"] == "V/H/S"){
			$status["V/H/S"] = true;
			if(!in_array("1258838325280641075", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "1258838325280641075", "", "1235869893664964608");
			}
		}
    	if($row["collection_name"] == "JOHN DOE"){
			$status["JOHN DOE"] = true;
			if(!in_array("1258838187929899018", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "1258838187929899018", "", "1235869893664964608");
			}
		}
    	if($row["collection_name"] == "BEELZEBUB"){
			$status["BEELZEBUB"] = true;
			if(!in_array("1258839170194079949", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "1258839170194079949", "", "1235869893664964608");
			}
		}
    	if($row["collection_name"] == "SKADA"){
			$status["SKADA"] = true;
			if(!in_array("1258848946248220702", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "1258848946248220702", "", "1235869893664964608");
			}
		}
    	if($row["collection_name"] == "Nemonium x Ritual"){
			$status["Nemonium x Ritual"] = true;
			if(!in_array("1259251384998690866", $roles)){
				assignRole($_SESSION['userData']['discord_id'], "1259251384998690866", "", "1235869893664964608");
			}
		}
	  }
    }
	return $status;
}

// Get NFT Collection Leaderboard Visibility
function getVisibility($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT visibility FROM users WHERE id = '".$_SESSION['userData']['user_id']."'";
		$result = $conn->query($sql);
	
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
		    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
	    	return $row["visibility"];
		  }
		} else {
		  //echo "0 results";
		}
	}
}

// Get NFT Collection Leaderboard Visibility by Username
function getVisibilityByUsername($conn, $username){
	$username = $conn->real_escape_string($username);
	$sql = "SELECT visibility FROM users WHERE username = '".$username."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	return $row["visibility"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Update NFT Collection Leaderboard Visibility
function updateVisibility($conn, $visibility){
	$sql = "UPDATE users SET visibility='".$visibility."' WHERE id='".$_SESSION['userData']['user_id']."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Update Discord Message Status
function updateDiscordMessageStatus($conn, $discord_id, $status){
	$discord_id = preg_replace('/[^0-9]/', '', $discord_id);
	$status = (int)$status;
	$sql = "UPDATE users SET message='".$status."' WHERE discord_id='".$discord_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Update Discord Reaction Status
function updateDiscordReactionStatus($conn, $discord_id, $status){
	$discord_id = preg_replace('/[^0-9]/', '', $discord_id);
	$status = (int)$status;
	$sql = "UPDATE users SET reaction='".$status."' WHERE discord_id='".$discord_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Reset Discord Message and Reaction Status
function resetDiscordStatus($conn){
	$sql = "UPDATE users SET message='0', reaction='0' WHERE id='".$_SESSION['userData']['user_id']."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Get Discord Message and Reaction Status
function getDiscordStatus($conn, $discord_id){
	$discord_id = preg_replace('/[^0-9]/', '', $discord_id);
	$sql = "SELECT message, reaction FROM users WHERE discord_id='".$discord_id."'";
	$result = $conn->query($sql);
	
	$status = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
    	$status['message'] = $row['message'];
		$status['reaction'] = $row['reaction'];
	  }
	} else {
	  //echo "0 results";
	}
	return $status;
}

// Get all users
function getUsers($conn){
	$sql = "SELECT * FROM users";
	$result = $conn->query($sql);
	
	$users = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$users[$row["id"]] = array();
		$users[$row["id"]]["discord_id"] = $row["discord_id"];
		$users[$row["id"]]["username"] = $row["username"];
	  }
	  return $users;
	} else {
	  //echo "0 results";
	}
	
}

// Get user ID by stake address for cron job verification
function getUserId($conn, $address){
	$address = $conn->real_escape_string($address);
	$sql = "SELECT user_id FROM wallets WHERE stake_address='".$address."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	return strval($row["user_id"]);
	  }
	} else {
	  //echo "0 results";
	}
}

// Get user ID from discord id for Drop Ship rewards
function getUserIdFromDiscordId($conn, $discord_id){
	$discord_id = preg_replace('/[^0-9]/', '', $discord_id);
	$sql = "SELECT id FROM users WHERE discord_id='".$discord_id."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	return strval($row["id"]);
	  }
	} else {
	  //echo "0 results";
	  return false;
	}
}

// Get first user wallet address to send purchases to
function getAddress($conn){
	$sql = "SELECT address, main FROM wallets WHERE user_id='".$_SESSION['userData']['user_id']."' ORDER BY main DESC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	return $row["address"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Get all wallets for user
function getWallets($conn){
	$sql = "SELECT id, stake_address, address, main FROM wallets WHERE user_id='".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	
    $wallets = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
	    $wallet = array();
	    $wallet["address"] = $row["address"];
		$wallet["main"] = $row["main"];
    	$wallets[$row["id"]] = $wallet;
	  }
	} else {
	  //echo "0 results";
	}
	return $wallets;
}

// Set primary wallet
function setPrimaryWallet($conn, $wallet_id){
	// Reset all wallets
	$sql = "UPDATE wallets SET main='0' WHERE user_id='".$_SESSION['userData']['user_id']."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
	// Set primary wallet
	$sql = "UPDATE wallets SET main='1' WHERE id='".$wallet_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Get project info
function getProjectInfo($conn, $project_id){
	$sql = "SELECT name, currency, discord_id, divider FROM projects WHERE id='".$project_id."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  $project = array();
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$project["name"] = $row["name"];
		$project["currency"] = $row["currency"];
		$project["discord_id"] = $row["discord_id"];
		$project["divider"] = $row["divider"];
		return $project;
	  }
	} else {
	  //echo "0 results";
	}
}

// Get projects
function getProjects($conn, $type=""){
	$where = " ";
	if($type == "core"){
		$where = " WHERE id <= 7";
	}
	if($type == "partner"){
		$where = " WHERE id > 7 && id != 15";
	}
	$sql = "SELECT id, name, currency, discord_id FROM projects".$where." ORDER by name ASC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  $projects = array();
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
	    $projects[$row["id"]] = array();
    	$projects[$row["id"]]["name"] = $row["name"];
		$projects[$row["id"]]["currency"] = $row["currency"];
		$projects[$row["id"]]["discord_id"] = $row["discord_id"];
	  }
	  return $projects;
	} else {
	  //echo "0 results";
	}
}

// Verify yesterday's rewards
function verifyYesterdaysRewards($conn){
	$sql = "SELECT id FROM transactions WHERE user_id='".$_SESSION['userData']['user_id']."' AND DATE(date_created) >= DATE_SUB(CURDATE(), INTERVAL 1 DAY) AND DATE(date_created) < CURDATE()";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	return true;
	  }
	} else {
	  //echo "0 results";
	  return false;
	}
}

// Get current daily reward streak
function getCurrentDailyRewardStreak($conn) {
	$sql = "SELECT streak FROM users WHERE id ='".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	
	$streak = 0;
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$streak = $row["streak"];
	  }
	} else {
	  //echo "0 results";
	}
	return $streak;
}

// Increment daily reward streak
function incrementDailyRewardStreak($conn) {
	$current_streak = getCurrentDailyRewardStreak($conn);
	$current_streak++;
	$streak = $current_streak;
	// If streak reaches 7 days (or more cuz something fucked up), reset to zero
	if($streak >= 7){
		$streak = 0;
	}
	$sql = "UPDATE users SET streak='".$streak."' WHERE id='".$_SESSION['userData']['user_id']."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
	return $current_streak;
}

// Reset daily reward streak
function resetDailyRewardStreak($conn) {
	$sql = "UPDATE users SET streak='0' WHERE id='".$_SESSION['userData']['user_id']."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Determine if eligible for daily reward
function getDailyRewardEligibility($conn){
	$eligibility = false;
	$date_created = getMaxDateCreated($conn);
	if(isset($date_created)){
		// If max date less than or equal to yesterday's date, eligible. If max date greater than yesterday's date, not elibible.
		if (strtotime(date('Y-m-d 00:00:00', strtotime($date_created))) <= strtotime(date('Y-m-d 00:00:00', strtotime('-1 day')))) {
		    $eligibility = true;
		}else{
			$eligibility = false;
		}
	}else{
		$eligibility = true;
	}
	return $eligibility;
}

// Get maximum date created for bonus transaction
function getMaxDateCreated($conn){
	$date_created = "";
	$sql = "SELECT id, MAX(date_created) AS date_created FROM transactions WHERE user_id='".$_SESSION['userData']['user_id']."' AND bonus = '1' GROUP BY id";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$date_created = $row["date_created"];
	  }
	  return $date_created;
	} else {
	  //echo "0 results";
      return null;
	}
}

// Get random daily reward for user
function getRandomReward($conn){
	$eligibility = getDailyRewardEligibility($conn);
	if($eligibility){
		$projects = array();
		$project = array();
		$projects = getProjects($conn, $type="");
		$project_id = rand(1, count($projects));
		$project = $projects[$project_id];
		$current_streak = incrementDailyRewardStreak($conn);
		$reward_tiers = getRewardTiers();
		$project["day"] = $current_streak;
		$project["amount"] = $reward_tiers[$current_streak];
		updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, $project["amount"]);
		logCredit($conn, $_SESSION['userData']['user_id'], $project["amount"], $project_id, $crafting=0, $bonus=1);
		getDailyConsumable($conn, $current_streak);
		// Webhook notification
		$day_consumables = array(1=>7, 2=>4, 3=>5, 4=>3, 5=>2, 6=>6, 7=>1);
		$con_id  = $day_consumables[$current_streak];
		$con_res = $conn->query("SELECT name FROM consumables WHERE id='".$con_id."'");
		$con_row = $con_res ? $con_res->fetch_assoc() : null;
		$con_name = $con_row ? $con_row['name'] : '';
		$total_res = $conn->query("SELECT COUNT(id) AS total FROM transactions WHERE user_id='".$_SESSION['userData']['user_id']."' AND bonus='1'");
		$total_row = $total_res ? $total_res->fetch_assoc() : null;
		$total_claims = $total_row ? (int)$total_row['total'] : 0;
		$dr_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
		$dr_discord_id = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
		$dr_avatar     = isset($_SESSION['userData']['avatar'])     ? $_SESSION['userData']['avatar']     : '';
		$dr_avatar_url = ($dr_discord_id && $dr_avatar) ? "https://cdn.discordapp.com/avatars/".$dr_discord_id."/".$dr_avatar.".png" : "";
		$dr_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($dr_username);
		$dr_mention    = $dr_discord_id ? "<@".$dr_discord_id.">" : $dr_username;
		$streak_bar    = str_repeat("🔥", $current_streak).str_repeat("⬜", 7 - $current_streak);
		$currency_icon = "https://skulliance.io/staking/icons/".strtolower(str_replace("$", "", $project['currency'])).".png";
		$dr_desc  = $dr_mention." claimed their [daily reward](".$dr_profile.")!\n\n";
		$dr_desc .= "📅 **Streak:** Day ".$current_streak." of 7　".$streak_bar."\n";
		$dr_desc .= "💰 **Reward:** ".$project['amount']." ".$project['currency']."\n";
		$dr_desc .= "🎁 **Bonus Item:** ".$con_name."\n";
		$dr_desc .= "🏆 **Total Claims:** ".$total_claims;
		$dr_author = array("name" => $dr_username." · Day ".$current_streak." of 7", "icon_url" => $dr_avatar_url, "url" => $dr_profile);
		discordmsg("🌟 Daily Reward Claimed", $dr_desc, $currency_icon, "https://skulliance.io/staking", "dailyrewards", $dr_avatar_url, "FFD700", $dr_author);
		return $project;
	}
}

// Get daily consumable reward for user
function getDailyConsumable($conn, $current_streak){
	$consumables = array();
	$consumables[1] = 7; // Random Reward
	$consumables[2] = 4; // 25% Success
	$consumables[3] = 5; // Fast Forward
	$consumables[4] = 3; // 50% Success
	$consumables[5] = 2; // 75% Success
	$consumables[6] = 6; // Double Rewards
	$consumables[7] = 1; // 100% Success
	$consumable_id = $consumables[$current_streak];
	updateAmount($conn, $_SESSION['userData']['user_id'], $consumable_id, 1);
}

// Reward tiers
function getRewardTiers(){
	$reward_tiers = array();
	$reward_tiers[1] = 1;
	$reward_tiers[2] = 3;
	$reward_tiers[3] = 5;
	$reward_tiers[4] = 10;
	$reward_tiers[5] = 15;
	$reward_tiers[6] = 20;
	$reward_tiers[7] = 30;
	return $reward_tiers;
}

// Get streak rewards
function getStreakRewards($conn) {
	$current_streak = getCurrentDailyRewardStreak($conn);
	$sql = "SELECT currency, amount FROM transactions INNER JOIN projects ON projects.id = transactions.project_id WHERE user_id ='".$_SESSION['userData']['user_id']."' AND bonus = '1' ORDER BY date_created DESC LIMIT ".$current_streak;
	$result = $conn->query($sql);
	
	$days = array();
	$index = $current_streak;
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$days[$index] = array();
		$days[$index]["day"] = $index;
		$days[$index]["currency"] = $row["currency"];
		$days[$index]["amount"] = $row["amount"];
		$index--;
	  }
	  ksort($days);
	} else {
	  //echo "0 results";
	}
	return $days;
}

// Get completed daily rewards streak
function getCompletedRewards($conn) {
	$current_streak = getCurrentDailyRewardStreak($conn);
	if($current_streak == 0){
		$current_streak = 7;
	}
	$sql = "SELECT currency, amount FROM transactions INNER JOIN projects ON projects.id = transactions.project_id WHERE user_id ='".$_SESSION['userData']['user_id']."' AND bonus = '1' ORDER BY date_created DESC LIMIT ".$current_streak;
	$result = $conn->query($sql);
	
	$rewards = array();
	$index = $current_streak;
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		$rewards[$index] = array();
	    $rewards[$index]["day"] = $index;
		$rewards[$index]["currency"] = $row["currency"];
		$rewards[$index]["amount"] = $row["amount"];
		$index--;
	  }
	  ksort($rewards);
	} else {
	  //echo "0 results";
	}
	return $rewards;
}

// Get number of streaks completed
function getStreaksTotal($conn) {
	$sql = "SELECT COUNT(id) AS streaks FROM transactions WHERE user_id ='".$_SESSION['userData']['user_id']."' AND bonus = '1' AND amount = '30'";
	
	$result = $conn->query($sql);
	
	$streaks = 0;
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		$streaks = $row["streaks"];
	  }
	} else {
	  //echo "0 results";
	  $streaks = 0;
	}
	return $streaks;
}

// Get number of streaks completed
/*
function getStreaksTotal($conn) {
	$sql = "SELECT streak FROM users WHERE id ='".$_SESSION['userData']['user_id']."'";
	
	$result = $conn->query($sql);
	
	$streaks = 0;
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		$streak = $row["streak"];
	  }
	} else {
	  //echo "0 results";
	  $streak = 0;
	}
	return $streak;
}*/

// Get mission info
function getMission($conn, $mission_id){
	$sql = "SELECT title, description, cost, reward, project_id, duration, currency, name FROM missions INNER JOIN quests ON quests.id = missions.quest_id INNER JOIN projects ON projects.id = quests.project_id WHERE missions.id = '".$mission_id."'";
	
	$result = $conn->query($sql);
	
	$mission = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  $mission["title"] = $row["title"]; 
		  $mission["description"] = $row["description"];
		  $mission["cost"] = $row["cost"];
		  $mission["reward"] = $row["reward"];
		  $mission["duration"] = $row["duration"];
		  $mission["currency"] = $row["currency"];
		  $mission["project"] = $row["name"];
	  }
	  return $mission;
	} else {
	  //echo "0 results";
	}
}

// Get max mission levels for successfully completed missions by project
function getMissionLevels($conn) {
	$sql = "SELECT level, project_id FROM missions INNER JOIN quests ON quests.id = missions.quest_id WHERE status = '1' AND user_id = '".$_SESSION['userData']['user_id']."'";
	
	$result = $conn->query($sql);
	
	$levels = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  if(!isset($levels[$row["project_id"]])){
		  	$levels[$row["project_id"]] = $row["level"];
	  	  }else{
			  if($row["level"] > $levels[$row["project_id"]]){
			  	$levels[$row["project_id"]] = $row["level"];
			  }
	  	  }
	  }
  	}else{
  		
  	}
	return $levels;
}


function getMissionsFilters($conn, $quest_id, $projects) {
	$eligible = "";
	$sql = "SELECT DISTINCT projects.id, projects.name, projects.currency AS currency FROM quests INNER JOIN projects ON projects.id = quests.project_id ORDER BY projects.id";
	
	$result = $conn->query($sql);
	
	echo "<div class='missions-filters'>";
	/*
	echo "<div class='missions-filter' onclick='toggleMissions(\"block\");hideLockedMissions();selectProjectFilter(0);toggleSections(\"quests\");'>All</div>";
	echo "<div class='missions-filter' onclick='toggleMissions(\"block\");hideLockedMissions();selectProjectFilter(0);hideIneligibleMissions();toggleSections(\"quests\");'>Eligible</div>";*/
	if($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			if(isset($projects[$row["id"]])){
				$eligible = " eligible";
			}else{
				$eligible = "";
			}
			//echo "<div class='missions-filter".$eligible."' onclick='getQuests(".$row["id"].");toggleMissions(\"none\");showMissions(".$row["id"].");selectProjectFilter(".$row["id"].");toggleSections(\"quests\");'>".$row["name"]."</div>";
			echo "<div class='missions-filter".$eligible."' onclick='getQuests(".$row["id"].");selectProjectFilter(".$row["id"].");toggleSections(\"quests\");'><img title='".$row["name"]."' src='icons/".strtolower($row["currency"]).".png'/></div>";
		}
	}
	echo "</div>";
}

// See if user has qualifying NFTs for a particular mission
function checkMissionInventory($conn, $project_id){
	$sql = "SELECT nfts.id FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id WHERE collections.project_id = '".$project_id."' AND user_id = '".$_SESSION['userData']['user_id']."'";
	
	$result = $conn->query($sql);
	
	if($result->num_rows > 0) {
		return true;
	}else{
		return false;
	}
}

// See if there is more than one mission available for a project
function getQuestProjectID($conn, $quest_id){
	$sql = "SELECT project_id FROM quests WHERE quest_id = '".$quest_id."'";
	
	$result = $conn->query($sql);
	
	if($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			return $row['project_id'];
		}
	}else{

	}
}

// Get missions
function getMissions($conn, $quest_id, $project_id) {
	$where = "";
	if(isset($project_id)){
		$_SESSION['userData']['project_id'] = $project_id;
		$where = "WHERE projects.id = '".$project_id."'";
	}else{
		$project_id = 1;
	}
	if(isset($_SESSION['userData']['project_id'])){
		$project_id = $_SESSION['userData']['project_id'];
	}
	//CASE WHEN quests.id = '".$quest_id."' THEN 1 ELSE 2 END
	$sql = "SELECT quests.id, title, description, cost, reward, project_id, duration, level, currency, name, extension FROM quests INNER JOIN projects ON projects.id = quests.project_id ".$where." ORDER BY projects.id, level ASC";
	
	$result = $conn->query($sql);
	
	$levels = getMissionLevels($conn);
	$quest_ids = array();
	$locked_quest_ids = array();
	$ineligible_quest_ids = array();
	echo "<div id='quests'>";
	echo "<div class='nfts'>";
	if($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	  	$max_level = 0;
    	$class = "";
		$title = $row["title"];
		$image = "images/missions/".strtolower(str_replace(" ", "-", $row["title"]));
		if($row["extension"] != "mp4"){
			$extension = $row["extension"];
		}else{
			$extension = "gif";
		}
		$style = "background-image: url(".$image.".$extension)";
		$quest_ids[$row["id"]] = $row["id"];
		if(!checkMissionInventory($conn, $row["project_id"])){
			$ineligible_quest_ids[$row["id"]] = $row["id"];
		}
		//$class = " highlight";
		if(isset($levels[$row["project_id"]])){
			$max_level = $levels[$row["project_id"]];
		}
		if(($max_level+1) >= $row["level"]){
			echo "<div class='nft project-".$row["project_id"].(($row["project_id"] == 37)?" aeoniumsky":"")."' id='quest-".$row["id"]."'>";
    		echo "<div class='nft-data".$class." mission-data' onclick='document.getElementById(\"submit-".$row["id"]."\").click()' style='".$style."'>";
		}else{
			$locked_quest_ids[$row["id"]] = $row["id"];
			// Removed display none from this div
			echo "<div class='nft project-".$row["project_id"].(($row["project_id"] == 37)?" aeoniumsky":"")."' id='quest-".$row["id"]."'>";
			if($_SESSION['userData']['discord_id'] != '772831523899965440'){
				echo "<div class='nft-data".$class." mission-data disabled'>";
				$title = preg_replace('/[0-9_-]/', '#', preg_replace('/[a-zA-Z_-]/', '?', $row["title"]));
				$image = "icons/padlock";
			}else{
	    		//echo "<div class='nft-data".$class." mission-data' onclick='document.getElementById(\"submit-".$row["id"]."\").click()' style='".$style."'>";
				
				echo "<div class='nft-data".$class." mission-data disabled' onclick='document.getElementById(\"submit-".$row["id"]."\").click()'>";
				//echo "<div class='nft-data".$class." mission-data disabled'>";
				$title = preg_replace('/[0-9_-]/', '#', preg_replace('/[a-zA-Z_-]/', '?', $row["title"]));
				$image = "icons/padlock";
			}
		}
		echo "<span class='nft-name'>".$title."</span>";
		echo "<span class='nft-image'><img class='mission-image' src='".$image.(($row["project_id"] == 37 && $image != "icons/padlock")?".gif":".png")."'/></span>";
		//echo "<span class='nft-level'><strong>Description</strong><br>".$row["description"]."</span>";
		echo "<span class='nft-level'><strong>Project</strong><br>".$row["name"]."</span>";
		echo "<span class='nft-level'><strong>Cost</strong><br>".number_format($row["cost"])." ".$row["currency"]."</span>";
		echo "<span class='nft-level'><strong>Reward</strong><br>".number_format($row["reward"])." ".$row["currency"]."</span>";
		echo "<span class='nft-level'><strong>Duration</strong><br>".$row["duration"]." Day(s)</span>";
		echo "<span class='nft-level'><strong>Level</strong><br>".$row["level"]."</span>";
		if(($max_level+1) >= $row["level"] || $_SESSION['userData']['discord_id'] == '772831523899965440'){
			echo"
			<form action='missions.php#inventory' method='post'>
			  <input type='hidden' id='quest_id' name='quest_id' value='".$row["id"]."'>
			  <input type='hidden' id='project_id' name='project_id' value='".$row["project_id"]."'>
			  <input style='display:none' id='submit-".$row["id"]."' class='small-button' type='submit' value='Select Mission'>
			</form>";
		}
		echo "</div></div>";
	  }
	} else {
	  //echo "0 results";
	}
	echo "</div></div>";
	/*
	echo "<script type='text/javascript'>";
	if(isset($_SESSION['userData']['project_id'])){
		echo "toggleMissions('none');";
		echo "showMissions(".$_SESSION['userData']['project_id'].");";
	}
	echo "function toggleMissions(visibility){";
	foreach($quest_ids AS $id => $quest_id){
		echo "document.getElementById('quest-".$quest_id."').style.display = visibility;";
	}
	echo "}";
	echo "function hideLockedMissions(){";
	foreach($locked_quest_ids AS $id => $quest_id){
		echo "document.getElementById('quest-".$quest_id."').style.display = 'none';";
	}
	echo "}";
	echo "function hideIneligibleMissions(){";
	foreach($ineligible_quest_ids AS $id => $quest_id){
		echo "document.getElementById('quest-".$quest_id."').style.display = 'none';";
	}
	echo "}";
	echo "function showMissions(project_id){";
	echo "var projects = document.getElementsByClassName('project-'+project_id);";
	echo "
	for (var i = 0; i < projects.length; i ++) {
	    projects[i].style.display = 'block';
	}";
	echo "}";
	echo "</script>";*/
}

// Get Current Missions for User
function getCurrentMissions($conn){
	$projects = array();
	/*
	$sql = "SELECT missions.id AS mission_id, quest_id, title, projects.name AS project_name, cost, reward, currency, level, missions.created_date, duration, COUNT(nft_id) AS total_nfts, SUM(rate) AS success_rate 
	FROM missions INNER JOIN quests ON missions.quest_id = quests.id INNER JOIN projects ON projects.id = quests.project_id LEFT JOIN missions_nfts ON missions.id = missions_nfts.mission_id LEFT JOIN nfts ON nfts.id = missions_nfts.nft_id LEFT JOIN collections ON collections.id = nfts.collection_id 
	WHERE status = 0 AND missions.user_id = '".$_SESSION['userData']['user_id']."' GROUP BY missions.id ORDER BY level ASC, missions.created_date ASC";*/
	
	// AI optimized query to speed things up as much as possible, original query with nft left joins that was problematic is above
	$sql = "SELECT
    m.id AS mission_id, 
    m.quest_id, 
    q.title, 
    p.name AS project_name, 
    q.cost, 
    q.reward, 
    p.currency, 
    m.created_date, 
    q.duration, 
    m.status,
	COUNT(mn.nft_id) AS total_nfts, 
	SUM(c.rate) AS success_rate 
	FROM missions m
	INNER JOIN quests q ON m.quest_id = q.id
	INNER JOIN projects p ON p.id = q.project_id
	LEFT JOIN missions_nfts mn ON m.id = mn.mission_id
	LEFT JOIN nfts n ON n.id = mn.nft_id
	LEFT JOIN collections c ON c.id = n.collection_id
	WHERE m.status = '0' AND m.user_id = '".$_SESSION['userData']['user_id']."'
	GROUP BY m.id 
	ORDER BY m.created_date ASC;";
	
	$result = $conn->query($sql);
	
	$completed_missions = array();

	if ($result->num_rows > 0) {
   	  $projects = renderStartAllFreeEligibleMissionsButton($conn);
	  renderStartAutoMissionsButton($conn);
	  echo "<div class='mc-list'>";
	  // output data of each row
	  $rows = array();
	  while($row = $result->fetch_assoc()) {
		// Handle consumables for each mission
	  	$consumables = array();
	  	$random_reward = false;
		$success_rate = 0;
		$fast_forward = false;
	  	$consumables_sql = "SELECT consumable_id FROM missions_consumables WHERE mission_id ='".$row["mission_id"]."';";
	  	$consumables_result = $conn->query($consumables_sql);
	
	  	if ($consumables_result->num_rows > 0) {
	  	  // output data of each row
	  	  while($consumables_row = $consumables_result->fetch_assoc()) {
	  		  $consumables[$consumables_row["consumable_id"]] = $consumables_row["consumable_id"];
	  	  }
	    }
	  	foreach($consumables AS $id => $consumable_id){
	  		if($consumable_id == 1){
	  			$success_rate += 100;
	  		}else if($consumable_id == 2){
	  			$success_rate += 75;
	  		}else if($consumable_id == 3){
	  			$success_rate += 50;
	  		}else if($consumable_id == 4){
	  			$success_rate += 25;
	  		}else if($consumable_id == 6){
	  			$row["reward"] = $row["reward"]*2;
	  		}else if($consumable_id == 5){
	  			$fast_forward = true;
	  		}
	  	}  
		
		$created_date = "";
		if($fast_forward == true){
			$created_date = strtotime('-'.ceil($row["duration"]/2).' day', strtotime($row["created_date"]));
		}else{
			$created_date = strtotime($row["created_date"]);
		}
  		$date = strtotime('+'.$row["duration"].' day', $created_date);
  		$remaining = $date - time();
		$days_remaining = floor(($remaining / 86400));
  		$hours_remaining = floor(($remaining % 86400) / 3600);
  		$minutes_remaining = floor(($remaining % 3600) / 60);
		if($date > time()){
			$time_message = "<span class='countdown' data-deadline='".$date."'>".$days_remaining."d ".$hours_remaining."h ".$minutes_remaining."m 0s</span>";
			$completed = "In Progress";
		}else{
			$time_message = "0d 0h 0m 0s";
			$completed = "Completed";
			//$completed = "<input type='button' class='small-button' value='Claim' onclick='completeMission(".$row["mission_id"].", ".$row["quest_id"].");this.style.display=\"none\";'/>";
			$completed_missions[$row["mission_id"]] = $row["quest_id"];
		}
		$consumables = getMissionConsumables($conn, $row["mission_id"]);
		$decimal = $days_remaining.".".(($hours_remaining<10)?"0".$hours_remaining:$hours_remaining).(($minutes_remaining<10)?"0".$minutes_remaining:$minutes_remaining).$row["mission_id"];
		// Calculate progress percentage
		if($completed == "Completed"){
			$percentage = 100;
		}else{
			$percentage = 100-((($days_remaining+($hours_remaining/24)+($minutes_remaining/1440)) / $row["duration"])*100);
		}
		$rows[$decimal] = "";
		// Card wrapper (id used by JS for success/failure class and hide on retreat)
		$rows[$decimal] .= "<div class='mc-card' id='mission-row-".$row["mission_id"]."'>";

		// Header row: icons | title + consumables | time + action
		$rows[$decimal] .= "<div class='mc-header'>";

		// Mission icon only
		$rows[$decimal] .= "<div class='mc-icons'>";
		$rows[$decimal] .= "<img class='mc-mission-icon' title='".$row["title"]."' src='images/missions/".strtolower(str_replace(" ", "-", $row["title"])).".png'/>";
		$rows[$decimal] .= "</div>";

		// Title only in body
		$rows[$decimal] .= "<div class='mc-body'>";
		$rows[$decimal] .= "<span class='mc-title'>".$row["title"]."</span>";
		$rows[$decimal] .= "</div>"; // mc-body

		// Time left + retreat/claim button
		$rows[$decimal] .= "<div class='mc-aside'>";
		$rows[$decimal] .= "<span class='mc-time-label'>".(($completed == "In Progress") ? "Time Left" : "Complete")."</span>";
		$rows[$decimal] .= "<span class='mc-time'>".$time_message."</span>";
		$rows[$decimal] .= "<div id='mission-result-".$row["mission_id"]."' class='mc-result'>";
		if($completed == "In Progress"){
			$rows[$decimal] .= "<input type='button' id='retreat-button-".$row["mission_id"]."'  class='small-button' value='Retreat' onclick='retreat(\"".$row["mission_id"]."\", \"".$row["quest_id"]."\");'/>"; 
		}else{
			$rows[$decimal] .= "<span class='mc-claim-label'>".$completed."</span>";
		}
		$rows[$decimal] .= "</div>"; // mc-result
		$rows[$decimal] .= "</div>"; // mc-aside
		$rows[$decimal] .= "</div>"; // mc-header

		// Stats strip
		$rows[$decimal] .= "<div class='mc-stats'>";
		$rows[$decimal] .= "<div class='mc-stat'><div class='mc-stat-label'>Cost</div><div class='mc-stat-val'>".number_format($row["cost"])."&nbsp;".$row["currency"]."</div></div>";
		$rows[$decimal] .= "<div class='mc-stat'><div class='mc-stat-label'>Reward</div><div id='mission-reward-".$row["mission_id"]."' class='mc-stat-val'>".number_format($row["reward"])." <span id='currency-".$row["mission_id"]."'>".$row["currency"]."</span></div></div>";
		$rows[$decimal] .= "<div class='mc-stat'><div class='mc-stat-label'>NFTs</div><div class='mc-stat-val'>".$row["total_nfts"]."</div></div>";
		$rows[$decimal] .= "<div class='mc-stat'><div class='mc-stat-label'>Success</div><div class='mc-stat-val'>".(($success_rate+$row["success_rate"]))."%</div></div>";
		$rows[$decimal] .= "<div class='mc-stat'><div class='mc-stat-label'>Duration</div><div class='mc-stat-val'>".$row["duration"]."&nbsp;".(($row["duration"] == 1) ? "Day" : "Days")."</div></div>";
		$rows[$decimal] .= "</div>"; // mc-stats

		// Items row: project/points icon first, then consumables (anchored above progress bar)
		// Project icon is outside consumable-{id} so JS innerHTML replacement on claim doesn't wipe it
		$rows[$decimal] .= "<div class='mc-items'>";
		$rows[$decimal] .= "<img class='mc-currency-icon icon' title='".$row["currency"]."' style='border:0px;' src='icons/".strtolower($row["currency"]).".png'/>";
		$rows[$decimal] .= "<div id='consumable-".$row["mission_id"]."' class='mc-consumables-inner'>";
		if(is_array($consumables)){
			foreach($consumables AS $consumable_id => $consumable_name){
				$rows[$decimal] .= "<img title='".$consumable_name."' class='icon consumable' src='icons/".strtolower(str_replace("%", "", str_replace(" ", "-", $consumable_name))).".png'/>";
			}
		}
		$rows[$decimal] .= "</div>";
		$rows[$decimal] .= "</div>";

		// Progress bar (separate ID for JS hide on retreat)
		$rows[$decimal] .= "<div class='mc-progress' id='mission-progress-".$row["mission_id"]."'>";
		$rows[$decimal] .= "<div class='mc-progress-fill' style='width:".$percentage."%;' ></div>";
		$rows[$decimal] .= "</div>";

		$rows[$decimal] .= "</div>"; // mc-card
	  }
	  ksort($rows);
	  foreach($rows AS $duration => $output){
		  echo $output;
	  }
	  echo "</div><br>";
	  //json_encode(
	  if(!empty($completed_missions)){
		  $mission_ids = "";
		  $quest_ids = "";
		  foreach($completed_missions AS $mission_id => $quest_id){
			  $mission_ids = $mission_ids.",".$mission_id;
			  $quest_ids = $quest_ids.",".$quest_id;
		  }
		  $mission_ids = substr($mission_ids, 1);
		  $quest_ids = substr($quest_ids, 1);
		  echo "<span id='claim-missions-button'><input type='button' class='button' value='Claim Missions' onclick='completeMissions(\"".$mission_ids."\", \"".$quest_ids."\");document.getElementById(\"claim-missions-button\").style.display=\"none\";'/><br><br></span>";
		  echo "<script type='text/javascript'>
			  document.getElementById('current-missions-container').insertBefore(document.getElementById('claim-missions-button'), document.getElementById('current-missions-container').firstChild);
		  	  </script>";
  	  }
	} else {
	  //echo "0 results";
	}
	return $projects;
}

// Get missions inventory
function getInventory($conn, $project_id, $quest_id) {
	$total_rates = 100;
	$threshold = 100;
	// Only evaluate project NFT holdings whether in missions or not when balancing for multiple runs
	if(!isset($_POST['maximize'])){
		// Check if there's existing missions deployed. If so, factor those into total rates calculation so that balancing is accurate
		$sql = "SELECT SUM(rate), nft_id AS total_mission_rates FROM missions_nfts INNER JOIN missions ON missions.id = missions_nfts.mission_id INNER JOIN quests ON quests.id = missions.quest_id 
			    INNER JOIN nfts ON nfts.id = missions_nfts.nft_id INNER JOIN collections ON collections.id = nfts.collection_id WHERE status = '0' AND missions.user_id = '".$_SESSION['userData']['user_id']."' AND quests.project_id = '".$project_id."'";
	
		$result = $conn->query($sql);
	
		$total_mission_rates = 0;
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$total_mission_rates = $row["total_mission_rates"];
			}
			//$threshold = 100;
		}else{
		
		}
	
		// If no missions deployed, check if total rates divided by 2 or 3 is greater than 100. If so, make threshold half or a third of total rates to try and balance out missions for whales
		$sql = "SELECT SUM(rate) AS total_rates FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id WHERE user_id = '".$_SESSION['userData']['user_id']."' AND collections.project_id = '".$project_id."';";

		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$total_rates = $row["total_rates"];
				$total_rates = $total_rates + $total_mission_rates;
				$double = ceil($total_rates/2);
				$triple = ceil($total_rates/3);
				if($total_rates > 100 && $double < 100){
					$threshold = $double;
				}else if($total_rates > 100 && $triple < 100){
					$threshold = $triple;
				}else{
					$threshold = 100;
				}
			}
		}
	}
	
	$sql = "SELECT nfts.id, asset_id, asset_name, ipfs, rate, collection_id FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id WHERE project_id = '".$project_id."' AND user_id = '".$_SESSION['userData']['user_id']."' AND asset_id 
		NOT IN(
        SELECT asset_id
        FROM missions_nfts INNER JOIN nfts ON nfts.id = missions_nfts.nft_id INNER JOIN missions ON missions.id = missions_nfts.mission_id WHERE status = '0' AND missions.user_id = '".$_SESSION['userData']['user_id']."') 
		ORDER BY collection_id ASC, rate DESC";
	
	$result = $conn->query($sql);
	
	$rate_tally = 0;
	$quest = getQuestInfo($conn, $quest_id);
	$balance = "";
	$balance = getBalance($conn, $project_id);
	echo "<h2>".$quest["title"]."</h2>";
	echo "<ul>";
	$extension = $quest["extension"];
	$filename = "images/missions/".strtolower(str_replace("'", "", str_replace(" ", "-", $quest["title"])));
	echo "<li class='role no-border-style'>";
	if($extension == "mp4"){
		echo "<video width='100%' height='100%' controls muted autoplay loop poster='".$filename.".gif'>
  		  		<source src='".$filename.".".$extension."' type='video/mp4'>
				Your browser does not support the video tag.	
			  </video>";
	}else{
		echo "<img class='mission-image' width='100%' src='".$filename.".".$extension."'/>";
	}
	echo "</li>";
	echo "<li class='role'>".$quest["description"]."</li>";
	echo "<li class='role'><strong>Project:</strong>&nbsp;".$quest["project"]."</li>";
	if($balance != ""){
		echo "<li class='role'><strong>Balance:</strong>&nbsp;".number_format($balance)." ".$quest["currency"]."</li>";
	}else{
		echo "<li class='role'><strong>Balance:</strong>&nbsp;NO ".$quest["currency"]."</li>";
	}
	echo "<li class='role'><strong>Cost:</strong>&nbsp;".number_format($quest["cost"])." ".$quest["currency"]."</li>";
	echo "<li class='role'><strong>Reward:</strong>&nbsp;".number_format($quest["reward"])." ".$quest["currency"]."</li>";
	if($quest["duration"] != 0){
		echo "<li class='role'><strong>Net Reward Per Day:</strong>&nbsp;".number_format(($quest["reward"]-$quest["cost"])/$quest["duration"])." ".$quest["currency"]."</li>";
	}
	echo "<li class='role'><strong>Duration:</strong>&nbsp;".$quest["duration"]." Day(s)</li>";
	echo "<li class='role'><strong>Level:</strong>&nbsp;".$quest["level"]."</li>";
	if ($result->num_rows > 0) {
		echo "<li class='role'><strong>Success Rate:</strong>&nbsp;<span id='success-rate'>Loading...</span>%</li>";
		echo "<li class='role no-border-style'>";
		if($balance == ""){
			updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, 0);
			$balance = 0;
		}
		if($balance >= $quest["cost"]){
			echo "<input type='button' class='button' value='Start Mission' onclick='startMission();'/>";
		}else{
			echo "You need ".number_format($quest["cost"]-$balance)." more ".$quest["currency"]." to start this mission.";
		}
		echo "</li>";
		echo "</ul>";
		echo "<h2>Inventory</strong></h2>";
		echo "<ul>";
		echo "<li class='role'><input style='padding:12px;' type='button' class='small-button' value='Clear All Selections' onclick='clearAllSelections();'/></li>";
		
		$consumables = array();
		$consumables = getCurrentAmounts($conn);
		foreach($consumables AS $id => $consumable){
			if($consumable["amount"] != 0){
				echo "<li class='role'>";
				echo "<input id='consumable-".$id."' type='button' class='small-button' value='Select' onclick='processConsumable(this.value, ".$id.");' />&nbsp;";
				echo "<img class='icon' src='icons/".strtolower(str_replace("%", "", str_replace(" ", "-", $consumable["name"]))).".png'/>";
				echo $consumable["name"].":&nbsp;<span id='amount-".$id."'>".$consumable["amount"]."</span>";
				echo "</li>";
			}
		}
		
		// Toggle Maximization and Balancing Inventory Selection Buttons
		if($total_rates >= 100){
			  if(!isset($_POST['maximize']) && !isset($_POST['balance'])){
				  renderInventoryButton("maximize", $quest_id, $project_id);
			  }else{
				  if(!isset($_POST['maximize'])){
					  renderInventoryButton("maximize", $quest_id, $project_id);
			  	  }
				  if(!isset($_POST['balance'])){
					  renderInventoryButton("balance", $quest_id, $project_id);
				  }
		  	  }
		}
		$nft_ids = array();
		while($row = $result->fetch_assoc()) {
			echo "<li class='role'>";
			$nft_ids[$row["id"]] = $row["id"];
			if(($rate_tally+$row["rate"]) <= $threshold){
				$rate_tally += $row["rate"];
				$_SESSION['userData']['mission']['nfts'][$row["id"]] = $row["rate"];
				?><input style='float:right' type='button' id='button-<?php echo $row["id"]; ?>' class='small-button activated' value='Remove' onclick='processMissionNFT(this.value, <?php echo $row["id"]; ?>, <?php echo $row["rate"]; ?>);'/>&nbsp;<?php
			}else{
				?><input style='float:right' type='button' id='button-<?php echo $row["id"]; ?>' class='small-button' value='Select' onclick='processMissionNFT(this.value, <?php echo $row["id"]; ?>, <?php echo $row["rate"]; ?>);'/>&nbsp;<?php
			}
			echo renderIPFS($row["ipfs"], $row["collection_id"], getIPFS($row["ipfs"], $row["collection_id"], $project_id), true);
			echo substr($row["asset_name"], 0, 12)." (+".$row["rate"]."%)";
			echo "</li>";
		}
		echo "</ul>";
		if(!empty($nft_ids)){
			echo "<script type='text/javascript'>";
			echo "function clearAllSelections(){";
				foreach($consumables AS $id => $consumable){
					if($consumable["amount"] != 0){
						echo "document.getElementById('consumable-".$id."').value = 'Select';";
						echo "document.getElementById('consumable-".$id."').classList.remove('activated');";
						echo "document.getElementById('amount-".$id."').innerHTML = '".$consumable["amount"]."';";
					}
				}
			
				foreach($nft_ids AS $id => $nft_id){
					echo "document.getElementById('button-".$nft_id."').value = 'Select';";
					echo "document.getElementById('button-".$nft_id."').classList.remove('activated');";
				}
				echo "document.getElementById('success-rate').innerHTML = 0;";
				echo "var xhttp = new XMLHttpRequest();";
				echo "xhttp.open('GET', 'ajax/clear-all-selections.php', true);";
				echo "xhttp.send();";
			echo "}";
			
			echo "function clearSuccessRate(){";
				foreach($consumables AS $id => $consumable){
					if($consumable["amount"] != 0){
						// Clear only success rate items
						if($id <= 4){
							echo "document.getElementById('consumable-".$id."').value = 'Select';";
							echo "document.getElementById('consumable-".$id."').classList.remove('activated');";
							echo "document.getElementById('amount-".$id."').innerHTML = '".$consumable["amount"]."';";
						}
					}
				}
				foreach($nft_ids AS $id => $nft_id){
					echo "document.getElementById('button-".$nft_id."').value = 'Select';";
					echo "document.getElementById('button-".$nft_id."').classList.remove('activated');";
				}
				echo "document.getElementById('success-rate').innerHTML = 0;";
				echo "var xhttp = new XMLHttpRequest();";
				echo "xhttp.open('GET', 'ajax/clear-success-rate.php', true);";
				echo "xhttp.send();";
			echo "}";
			echo "</script>";
		}
		return $rate_tally;
	}
}

// Render Inventory selection maximazation and balancing toggle buttons
function renderInventoryButton($selection, $quest_id, $project_id){
  echo "<li class='role no-border-style'>
  <form action='missions.php#inventory' method='post'>
		    <input type='hidden' id='quest_id' name='quest_id' value='".$quest_id."'>
    <input type='hidden' id='project_id' name='project_id' value='".$project_id."'>
	<input type='hidden' id='".$selection."' name='".$selection."' value='".$selection."'/>";
	if($selection == "balance"){
		echo "<input type='submit' class='small-button' style='padding:12px;' value='Balance Multiple Missions Inventory'/>";
	}else{
		echo "<input type='submit' class='small-button' style='padding:12px;' value='Maximize Single Mission Inventory'/>";
	}
  echo "
  </form>
  </li><br>";
}

function getQuestInfo($conn, $quest_id){
	$sql = "SELECT title, description, extension, cost, reward, duration, level, project_id, projects.name, currency FROM quests INNER JOIN projects ON projects.id = quests.project_id WHERE quests.id ='".$quest_id."';";
	$result = $conn->query($sql);
	
	$quest = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  $quest["title"] = $row["title"];
		  $quest["cost"] = $row["cost"];
		  $quest["reward"] = $row["reward"];
		  $quest["project"] = $row["name"];
		  $quest["currency"] = $row["currency"];
		  $quest["duration"] = $row["duration"];
		  $quest["level"] = $row["level"];
		  $quest["description"] = $row["description"];
		  $quest["extension"] = $row["extension"];
	  }
	  return $quest;
    }
}

function startMission($conn){
	if(!isset($_SESSION['userData']['mission']['nfts']) && 
	  (!isset($_SESSION['userData']['mission']['consumables'][1]) && !isset($_SESSION['userData']['mission']['consumables'][2]) && !isset($_SESSION['userData']['mission']['consumables'][3]) && !isset($_SESSION['userData']['mission']['consumables'][4]) )){
		echo 'Your selected consumable items were not saved properly. Your mission did not start and nothing has been deducted from your balances. Please hard refresh the webpage and try again.';
	}else{
		if(isset($_SESSION['userData']['mission']['quest_id']) && isset($_SESSION['userData']['user_id'])){
		
			$sql = "SELECT title, cost, reward, duration, extension, project_id, currency FROM quests INNER JOIN projects ON projects.id = quests.project_id WHERE quests.id ='".$_SESSION['userData']['mission']['quest_id']."';";
			$result = $conn->query($sql);

			$reward = 0; $duration = 0; $extension = 'png';
			if ($result->num_rows > 0) {
			  // output data of each row
			  while($row = $result->fetch_assoc()) {
				  $title = $row["title"];
				  $project_id = $row["project_id"];
				  $cost = $row["cost"];
				  $currency = $row["currency"];
				  $reward = $row["reward"];
				  $duration = $row["duration"];
				  $extension = $row["extension"];
			  }
		    }
		
			$balance = getBalance($conn, $project_id);
			if($balance >= $cost){
				$sql = "INSERT INTO missions (quest_id, user_id)
				VALUES ('".$_SESSION['userData']['mission']['quest_id']."', '".$_SESSION['userData']['user_id']."');";
		
				$mission_id = 0;
				if ($conn->query($sql) === TRUE) {
					//echo "New record created successfully";
					$sql = "SELECT MAX(id) AS mission_id FROM missions WHERE user_id ='".$_SESSION['userData']['user_id']."' AND quest_id = '".$_SESSION['userData']['mission']['quest_id']."'";
					$result = $conn->query($sql);
			
					if ($result->num_rows > 0) {
					  // output data of each row
					  while($row = $result->fetch_assoc()) {
						  $mission_id = $row["mission_id"];
					  }
				    }else{
		    	
				    }
					if($mission_id > 0){
						if($cost > 0){
							updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, -$cost);
							logDebit($conn, $_SESSION['userData']['user_id'], 0, $cost, $project_id, 0, $mission_id);
						}
						if(isset($_SESSION['userData']['mission']['nfts'])){
							foreach($_SESSION['userData']['mission']['nfts'] AS $nft_id => $rate){
								$sql = "INSERT INTO missions_nfts (mission_id, nft_id)
								VALUES ('".$mission_id."', '".$nft_id."')";

								if ($conn->query($sql) === TRUE) {
								  //echo "New record created successfully";
								} else {
								  //echo "Error: " . $sql . "<br>" . $conn->error;
								}
							}
						}
						if(isset($_SESSION['userData']['mission']['consumables'])){
							foreach($_SESSION['userData']['mission']['consumables'] AS $id => $consumable_id){
								$sql = "INSERT INTO missions_consumables (mission_id, consumable_id)
								VALUES ('".$mission_id."', '".$consumable_id."')";

								if ($conn->query($sql) === TRUE) {
								  //echo "New record created successfully";
								  updateAmount($conn, $_SESSION['userData']['user_id'], $consumable_id, -1);
								} else {
								  //echo "Error: " . $sql . "<br>" . $conn->error;
								}
							}
						}
						// Discord webhook — manual mission embark
						$m_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
						$m_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
						$m_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
						$m_avatar_url = ($m_discord && $m_avatar) ? "https://cdn.discordapp.com/avatars/".$m_discord."/".$m_avatar.".png" : "";
						$m_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($m_username);
						$m_slug       = strtolower(str_replace("'", "", str_replace(" ", "-", $title)));
						$m_image_url  = "https://skulliance.io/staking/images/missions/".$m_slug.".".$extension;
						$m_nft_rate   = 0;
						if(isset($_SESSION['userData']['mission']['nfts'])){
							foreach($_SESSION['userData']['mission']['nfts'] AS $_nid => $_rate){ $m_nft_rate += $_rate; }
						}
						$m_boost_map  = array(1 => 100, 2 => 75, 3 => 50, 4 => 25);
						$m_boost      = 0; $m_extras = array();
						if(isset($_SESSION['userData']['mission']['consumables'])){
							foreach($_SESSION['userData']['mission']['consumables'] AS $_slot => $_cid){
								if(isset($m_boost_map[$_cid])){ $m_boost += $m_boost_map[$_cid]; }
								else { $_en = $conn->query("SELECT name FROM consumables WHERE id='".$_cid."'"); if($_en && $_en->num_rows > 0) $m_extras[] = $_en->fetch_assoc()['name']; }
							}
						}
						if($m_boost > 0) array_unshift($m_extras, "+".$m_boost."% Success");
						$m_success    = min(100, $m_nft_rate + $m_boost);
						$m_nft_count  = isset($_SESSION['userData']['mission']['nfts']) ? count($_SESSION['userData']['mission']['nfts']) : 0;
						$m_mention    = $m_discord ? "<@".$m_discord.">" : $m_username;
						$m_desc       = $m_mention." has embarked on a mission!\n\n";
						$m_desc      .= "📜 **Quest:** ".$title."\n";
						$m_desc      .= "💰 **Cost:** ".number_format($cost)." ".$currency." → **Reward:** ".number_format($reward)." ".$currency."\n";
						$m_desc      .= "⏱️ **Duration:** ".$duration.($duration == 1 ? " day" : " days")."\n";
						$m_desc      .= "🎯 **Success Rate:** ".$m_success."%\n";
						$m_desc      .= "🦴 **NFTs Deployed:** ".$m_nft_count;
						if(!empty($m_extras)) $m_desc .= "\n🎒 **Items:** ".implode(", ", $m_extras);
						$m_author = array("name" => $m_username, "icon_url" => $m_avatar_url, "url" => $m_profile);
						discordmsg("⚔️ Mission Embarked", $m_desc, $m_image_url, "https://skulliance.io/staking/missions.php", "missions", $m_avatar_url, "FF6B35", $m_author);
					}
				} else {
				  //echo "Error: " . $sql . "<br>" . $conn->error;
				}
				unset($_SESSION['userData']['mission']);
				//echo $title." Mission successfully started!";
			}else{
				echo "You do not have enough ".$currency." to start the ".$title." Mission.\r\n".
					 "You have ".$balance." ".$currency.".\r\n".
					 "You need ".$cost." ".$currency.".";
			}
		}else{
			echo "No Session";
		}
	}
}

function startAllFreeEligibleMissions($conn){
	static $sf_depth = 0;
	static $sf_titles = array();
	$sf_depth++;

	$rate_flag = "false";
	
	// Get all level 1 quests
	$sql = "SELECT id, title, cost, project_id FROM quests WHERE level = '1'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
		// output data of each row
		while($row = $result->fetch_assoc()) {
			$sf_quest_title = $row['title'];
			// Get all user NFTs for a specific project that aren't currently deployed in missions
			$nft_sql = "SELECT nfts.id, asset_id, asset_name, ipfs, rate, collection_id FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id WHERE project_id = '".$row['project_id']."' AND user_id = '".$_SESSION['userData']['user_id']."' AND asset_id
				NOT IN(
			      SELECT asset_id
			      FROM missions_nfts INNER JOIN nfts ON nfts.id = missions_nfts.nft_id INNER JOIN missions ON missions.id = missions_nfts.mission_id WHERE status = '0' AND missions.user_id = '".$_SESSION['userData']['user_id']."') 
				ORDER BY collection_id ASC, rate DESC";

			$nft_result = $conn->query($nft_sql);
			if ($nft_result->num_rows > 0) {
				// Create a new mission
				$mission_sql = "INSERT INTO missions (quest_id, user_id)
				VALUES ('".$row['id']."', '".$_SESSION['userData']['user_id']."');";
	
				$mission_id = 0;
				if ($conn->query($mission_sql) === TRUE) {
					// Get newly created mission id
					$max_sql = "SELECT MAX(id) AS mission_id FROM missions WHERE user_id ='".$_SESSION['userData']['user_id']."' AND quest_id = '".$row['id']."'";
					$max_result = $conn->query($max_sql);

					if ($max_result->num_rows > 0) {
						// output data of each row
						while($max_row = $max_result->fetch_assoc()) {
						  $mission_id = $max_row["mission_id"];
						}
				    }else{
	
				    }
					if($mission_id > 0){
						$sf_titles[] = $sf_quest_title;
						$rate_tally = 0;
						while($nft_row = $nft_result->fetch_assoc()) {
							$rate_tally += $nft_row["rate"];
							if($rate_tally <= 100){
								// Associate NFT with mission
								$mission_nft_sql = "INSERT INTO missions_nfts (mission_id, nft_id)
								VALUES ('".$mission_id."', '".$nft_row['id']."')";

								if ($conn->query($mission_nft_sql) === TRUE) {
								  //echo "New record created successfully";
								} else {
								  //echo "Error: " . $sql . "<br>" . $conn->error;
								}
							}else{
								$rate_flag = "true";
							}
						} // End while
					} // End if
				} // End if
			} // End if
		} // End while
    } // End if
	if($rate_flag == "true"){
		startAllFreeEligibleMissions($conn);
	}
	$sf_depth--;
	if($sf_depth === 0 && !empty($sf_titles)){
		$sf_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
		$sf_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
		$sf_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
		$sf_avatar_url = ($sf_discord && $sf_avatar) ? "https://cdn.discordapp.com/avatars/".$sf_discord."/".$sf_avatar.".png" : "";
		$sf_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($sf_username);
		$sf_mention    = $sf_discord ? "<@".$sf_discord.">" : $sf_username;
		$sf_count      = count($sf_titles);
		$sf_desc       = $sf_mention." used **Start All Free** and launched **".$sf_count."** mission".($sf_count != 1 ? "s" : "")."!\n\n";
		$sf_budget     = 1800 - strlen($sf_desc);
		$sf_truncated  = false;
		foreach(array_count_values($sf_titles) as $sf_t => $sf_n){
			$sf_line = "• ".$sf_t.($sf_n > 1 ? " x".$sf_n : "")."\n";
			if(strlen($sf_line) > $sf_budget){ $sf_truncated = true; break; }
			$sf_desc .= $sf_line; $sf_budget -= strlen($sf_line);
		}
		if($sf_truncated) $sf_desc .= "*(and more...)*";
		$sf_author = array("name" => $sf_username, "icon_url" => $sf_avatar_url, "url" => $sf_profile);
		discordmsg("🚀 Start All Free", $sf_desc, "", "https://skulliance.io/staking/missions.php", "missions", $sf_avatar_url, "4A90D9", $sf_author);
		$sf_titles = array();
	}
}

function renderStartAllFreeEligibleMissionsButton($conn){
	$projects = array();
    $display = "none";
	$nft_ids = "";
	$sql = "SELECT asset_id, project_id
	          FROM missions_nfts INNER JOIN missions ON missions.id = missions_nfts.mission_id INNER JOIN nfts ON nfts.id = missions_nfts.nft_id INNER JOIN collections ON nfts.collection_id = collections.id WHERE status = '0' AND missions.user_id = '".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
		$results = $result->fetch_all();
		$pairings = array();
		foreach($results AS $index => $pairing){
			if(isset($pairings[$pairing[1]])){
				$pairings[$pairing[1]] .= "'".$pairing[0]."',";
			}else{
				$pairings[$pairing[1]] = "'".$pairing[0]."',";
			}
		}
  	}
	
	$sql = "SELECT id, project_id FROM quests WHERE level = '1'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		$asset_ids = "";
		if(isset($pairings[$row['project_id']])){
			$asset_ids = " AND asset_id NOT IN(".substr_replace($pairings[$row['project_id']], "", -1).")";
		}
	  	$nft_sql = "SELECT asset_id, collection_id FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id WHERE project_id = '".$row['project_id']."' AND user_id = '".$_SESSION['userData']['user_id']."'".$asset_ids;
	  	$nft_result = $conn->query($nft_sql);
		if ($nft_result->num_rows > 0) {
	  		$projects[$row['project_id']] = true;
		}
	  }
	  if(!empty($projects)){
		  $display = "block";
  	  }
	}
	echo "<span style='display:".$display."' id='startFreeMissionsForm'>
	<button type='button' class='button' onclick='startFreeMissionsAjax(this)'>Start All Free</button>
	</span><br>";
	return $projects;
}

// ── Auto Missions ─────────────────────────────────────────────────────────────

// Find the best NFT + item loadout targeting 100% combined success rate.
// Tries all subsets of available 25/50/75% items, packs NFTs greedily into the
// remaining budget, and picks the combo with the highest combined rate.
// Never produces an item-only loadout (always requires at least 1 NFT).
// Returns ['nfts' => [nft_id => rate, ...], 'items' => [consumable_id, ...], 'rate' => int]
function findBestMissionLoadout($available_nfts, $consumable_amounts) {
	// Success consumable IDs → their rate contribution (excludes 100% item id=1)
	$success_items = [4 => 25, 3 => 50, 2 => 75];

	// Only consider items the user actually has in stock
	$in_stock = [];
	foreach ($success_items as $id => $rate) {
		if (isset($consumable_amounts[$id]) && (int)$consumable_amounts[$id]['amount'] > 0) {
			$in_stock[$id] = $rate;
		}
	}

	// Generate every subset of in-stock success items (including empty = no items)
	$item_ids = array_keys($in_stock);
	$n        = count($item_ids);
	$combos   = [];
	for ($mask = 0; $mask < (1 << $n); $mask++) {
		$combo_ids  = [];
		$combo_rate = 0;
		for ($bit = 0; $bit < $n; $bit++) {
			if ($mask & (1 << $bit)) {
				$combo_ids[] = $item_ids[$bit];
				$combo_rate += $in_stock[$item_ids[$bit]];
			}
		}
		// Skip combos that hit 100% on their own — no room for NFTs
		if ($combo_rate < 100) {
			$combos[] = ['ids' => $combo_ids, 'rate' => $combo_rate];
		}
	}

	// Sort highest item coverage first so we try the most-filling combos first
	usort($combos, function($a, $b) { return $b['rate'] - $a['rate']; });

	$best = ['nfts' => [], 'items' => [], 'rate' => 0, 'nft_count' => 0];

	foreach ($combos as $combo) {
		$nft_budget = 100 - $combo['rate'];

		// Pack NFTs highest-rate-first up to the remaining budget
		$assigned = [];
		$nft_sum  = 0;
		foreach ($available_nfts as $nft) {
			if ($nft_sum + $nft['rate'] <= $nft_budget) {
				$assigned[$nft['id']] = $nft['rate'];
				$nft_sum += $nft['rate'];
			}
		}

		// Never send items without at least one NFT
		if (empty($assigned)) continue;

		$combined  = $nft_sum + $combo['rate'];
		$nft_count = count($assigned);

		// Prefer higher combined rate; on a tie prefer the loadout that uses more NFTs
		if ($combined > $best['rate'] ||
			($combined === $best['rate'] && $nft_count > $best['nft_count'])) {
			$best = [
				'nfts'      => $assigned,
				'items'     => $combo['ids'],
				'rate'      => $combined,
				'nft_count' => $nft_count,
			];
		}
	}

	return $best;
}

// Insert one mission row and link NFTs + consumables. Returns mission_id or 0.
function _launchAutoMission($conn, $user_id, $quest_id, $cost, $project_id, $loadout, $extra_items) {
	$sql = "INSERT INTO missions (quest_id, user_id) VALUES ('$quest_id', '$user_id')";
	if (!$conn->query($sql)) return 0;

	$id_result  = $conn->query("SELECT MAX(id) AS mid FROM missions WHERE user_id='$user_id' AND quest_id='$quest_id'");
	$mission_id = 0;
	if ($id_result && $id_result->num_rows > 0) {
		$mission_id = (int)$id_result->fetch_assoc()['mid'];
	}
	if (!$mission_id) return 0;

	if ($cost > 0) {
		updateBalance($conn, $user_id, $project_id, -$cost);
		logDebit($conn, $user_id, 0, $cost, $project_id, 0, $mission_id);
	}

	foreach ($loadout['nfts'] as $nft_id => $rate) {
		$conn->query("INSERT INTO missions_nfts (mission_id, nft_id) VALUES ('$mission_id', '$nft_id')");
	}

	$all_consumables = array_merge($loadout['items'], $extra_items);
	foreach ($all_consumables as $consumable_id) {
		if ($conn->query("INSERT INTO missions_consumables (mission_id, consumable_id) VALUES ('$mission_id', '$consumable_id')")) {
			updateAmount($conn, $user_id, $consumable_id, -1);
		}
	}

	return $mission_id;
}

// Main entry point: loops over every project, builds optimal loadouts, and
// launches missions until NFTs or balance run out.
function startAutoMissions($conn) {
	if (!isset($_SESSION['userData']['user_id'])) return;
	$user_id = $_SESSION['userData']['user_id'];
	$auto_launched = [];

	// Max quest level per project
	$max_quest_levels = [];
	$mlr = $conn->query("SELECT project_id, MAX(level) AS max_level FROM quests GROUP BY project_id");
	if ($mlr) {
		while ($row = $mlr->fetch_assoc()) {
			$max_quest_levels[(int)$row['project_id']] = (int)$row['max_level'];
		}
	}
	if (empty($max_quest_levels)) return;

	// User's max successfully completed level per project
	$completed_levels = getMissionLevels($conn);

	foreach (array_keys($max_quest_levels) as $project_id) {
		$max_completed      = isset($completed_levels[$project_id]) ? (int)$completed_levels[$project_id] : 0;
		$max_unlocked_level = $max_completed + 1;
		$max_quest_level    = $max_quest_levels[$project_id];
		$has_locked         = ($max_unlocked_level < $max_quest_level);

		while (true) {
			// Re-query available NFTs each iteration so newly-locked ones are excluded
			$nft_sql = "SELECT nfts.id, collections.rate AS rate
			            FROM nfts
			            INNER JOIN collections ON collections.id = nfts.collection_id
			            WHERE collections.project_id = '$project_id'
			              AND nfts.user_id = '$user_id'
			              AND nfts.asset_id NOT IN (
			                  SELECT n2.asset_id
			                  FROM missions_nfts
			                  INNER JOIN nfts n2 ON n2.id = missions_nfts.nft_id
			                  INNER JOIN missions m ON m.id = missions_nfts.mission_id
			                  WHERE m.status = '0' AND m.user_id = '$user_id'
			              )
			            ORDER BY collections.rate DESC";
			$nft_result     = $conn->query($nft_sql);
			$available_nfts = [];
			if ($nft_result && $nft_result->num_rows > 0) {
				while ($row = $nft_result->fetch_assoc()) {
					$available_nfts[] = ['id' => (int)$row['id'], 'rate' => (int)$row['rate']];
				}
			}
			if (empty($available_nfts)) break;

			$balance = (int)getBalance($conn, $project_id);

			// Highest affordable unlocked quest
			$quest_result = $conn->query(
				"SELECT id, cost, level, title FROM quests
				 WHERE project_id = '$project_id' AND level <= '$max_unlocked_level'
				 ORDER BY level DESC"
			);
			$target_quest = null;
			if ($quest_result) {
				while ($qrow = $quest_result->fetch_assoc()) {
					if ($balance >= (int)$qrow['cost']) {
						$target_quest = $qrow;
						break;
					}
				}
			}
			if (!$target_quest) break;

			$is_max_mission   = ((int)$target_quest['level'] === $max_quest_level);
			$consumable_amounts = getCurrentAmounts($conn);
			$loadout          = findBestMissionLoadout($available_nfts, $consumable_amounts);

			if (empty($loadout['nfts'])) break;

			$quest_id     = (int)$target_quest['id'];
			$quest_title  = $target_quest['title'];
			$cost         = (int)$target_quest['cost'];
			$use_fallback = false;

			if ($loadout['rate'] < 90) {
				// Fall back to level 1 — free gamble with remaining NFTs
				$l1r = $conn->query(
					"SELECT id, cost, title FROM quests WHERE project_id = '$project_id' AND level = '1' LIMIT 1"
				);
				if ($l1r && $l1r->num_rows > 0) {
					$l1row = $l1r->fetch_assoc();
					if ($balance >= (int)$l1row['cost']) {
						$quest_id     = (int)$l1row['id'];
						$quest_title  = $l1row['title'];
						$cost         = (int)$l1row['cost'];
						$use_fallback = true;
					} else {
						break;
					}
				} else {
					break;
				}
			}

			// Fast Forward + Double Reward only when missions remain to unlock,
			// not on the max quest, and not on a level-1 fallback
			$extra_items = [];
			if ($has_locked && !$is_max_mission && !$use_fallback) {
				if (isset($consumable_amounts[5]) && (int)$consumable_amounts[5]['amount'] > 0) {
					$extra_items[] = 5; // Fast Forward
				}
				if (isset($consumable_amounts[6]) && (int)$consumable_amounts[6]['amount'] > 0) {
					$extra_items[] = 6; // Double Reward
				}
			}

			$mission_id = _launchAutoMission($conn, $user_id, $quest_id, $cost, $project_id, $loadout, $extra_items);
			if (!$mission_id) break;
			$auto_launched[] = $quest_title;
		}
	}

	// Discord webhook — auto missions summary
	if(!empty($auto_launched)){
		$am_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
		$am_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
		$am_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
		$am_avatar_url = ($am_discord && $am_avatar) ? "https://cdn.discordapp.com/avatars/".$am_discord."/".$am_avatar.".png" : "";
		$am_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($am_username);
		$am_mention    = $am_discord ? "<@".$am_discord.">" : $am_username;
		$am_count      = count($auto_launched);
		$am_desc       = $am_mention." used **Start All Auto** and launched **".$am_count."** mission".($am_count != 1 ? "s" : "")."!\n\n";
		$am_budget     = 1800 - strlen($am_desc);
		$am_truncated  = false;
		foreach(array_count_values($auto_launched) as $am_t => $am_n){
			$am_line = "• ".$am_t.($am_n > 1 ? " x".$am_n : "")."\n";
			if(strlen($am_line) > $am_budget){ $am_truncated = true; break; }
			$am_desc .= $am_line; $am_budget -= strlen($am_line);
		}
		if($am_truncated) $am_desc .= "*(and more...)*";
		$am_author = array("name" => $am_username, "icon_url" => $am_avatar_url, "url" => $am_profile);
		discordmsg("🤖 Start All Auto", $am_desc, "", "https://skulliance.io/staking/missions.php", "missions", $am_avatar_url, "9B59B6", $am_author);
	}
}

// Render the Start All Auto button. Visible only when the user has available NFTs.
function renderStartAutoMissionsButton($conn) {
	if (!isset($_SESSION['userData']['user_id'])) return;
	$user_id = $_SESSION['userData']['user_id'];
	$display = 'none';

	$sql = "SELECT COUNT(*) AS cnt
	        FROM nfts
	        INNER JOIN collections ON collections.id = nfts.collection_id
	        INNER JOIN quests ON quests.project_id = collections.project_id
	        WHERE nfts.user_id = '$user_id'
	          AND nfts.asset_id NOT IN (
	              SELECT n2.asset_id
	              FROM missions_nfts
	              INNER JOIN nfts n2 ON n2.id = missions_nfts.nft_id
	              INNER JOIN missions m ON m.id = missions_nfts.mission_id
	              WHERE m.status = '0' AND m.user_id = '$user_id'
	          )";
	$result = $conn->query($sql);
	if ($result) {
		$row = $result->fetch_assoc();
		if ((int)$row['cnt'] > 0) $display = 'block';
	}

	echo "<span style='display:$display' id='startAutoMissionsForm'>
	<button type='button' class='button' onclick='startAutoMissionsAjax(this)'>Start All Auto</button>
	</span><br>";
}

function completeMission($conn, $mission_id, $quest_id){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT title, reward, project_id, currency FROM quests INNER JOIN projects ON projects.id = quests.project_id WHERE quests.id ='".$quest_id."';";
		$result = $conn->query($sql);
		
		$title = "";
		$reward = 0;
		$project_id = 0;
		$currency = "";
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			  $title = $row["title"];
			  $reward = $row["reward"];
			  $project_id = $row["project_id"];
			  $currency = $row["currency"];
		  }
	    }
		
		$sql = "SELECT SUM(rate) AS success_rate FROM missions_nfts INNER JOIN nfts ON nfts.id = missions_nfts.nft_id INNER JOIN collections ON collections.id = nfts.collection_id WHERE mission_id ='".$mission_id."';";
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			  $success_rate = $row["success_rate"];
		  }
	    }
		
		$consumables = array();
		$random_reward = false;
		$sql = "SELECT consumable_id FROM missions_consumables WHERE mission_id ='".$mission_id."';";
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			  $consumables[$row["consumable_id"]] = $row["consumable_id"];
		  }
	    }
		foreach($consumables AS $id => $consumable_id){
			if($consumable_id == 1){
				$success_rate += 100;
			}else if($consumable_id == 2){
				$success_rate += 75;
			}else if($consumable_id == 3){
				$success_rate += 50;
			}else if($consumable_id == 4){
				$success_rate += 25;
			}else if($consumable_id == 6){
				$reward = $reward*2;
			}else if($consumable_id == 7){
				$projects = array();
				$projects = getProjects($conn, $type="");
				$project_id = rand(1, count($projects));
			}
		}
		
		// Failure = 2, Success = 1
		$success = 2;
		$chance = $success_rate;
		if(rand(1,100) <= (int)$chance){
			$success = 1;
		}
		
		// Check to see if mission has failed 4 times in a row. If so, force a successful result for mission 5
		$sql = "SELECT SUM(status) AS status_total FROM (SELECT status FROM missions WHERE user_id ='".$_SESSION['userData']['user_id']."' AND quest_id = '".$quest_id."' ORDER BY id DESC LIMIT 5) AS subquery";
		$result = $conn->query($sql);
		
		$status_total = 0;
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			  $status_total = $row["status_total"];
		  }
		  if($status_total == 8){
			  $success = 1;
		  }
	    }
		
		$sql = "UPDATE missions SET status='".$success."' WHERE id='".$mission_id."' AND user_id = '".$_SESSION['userData']['user_id']."'";
		if ($conn->query($sql) === TRUE) {
		  //echo "New record created successfully";
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
		
		/* Still deciding on whether this is the best approach. Considering archiving mission nft and consumable data in a separate table for reference.
	  	// Delete Mission NFTs since they are unecessary after a mission is completed and take up a ton of table space which degrades query performance
    	$sql = "DELETE FROM missions_nfts WHERE mission_id = '".$mission_id."'";
    	if ($conn->query($sql) === TRUE) {
    	  //echo "Record deleted successfully";
    	} else {
    	  //echo "Error: " . $sql . "<br>" . $conn->error;
    	}
	
	  	// Delete Mission Consumables since they are unecessary after a mission is completed and take up a ton of table space which degrades query performance
    	$sql = "DELETE FROM missions_consumables WHERE mission_id = '".$mission_id."'";
    	if ($conn->query($sql) === TRUE) {
    	  //echo "Record deleted successfully";
    	} else {
    	  //echo "Error: " . $sql . "<br>" . $conn->error;
    	}
		*/
		
		$mission_result = array();
		$project = getProjectInfo($conn, $project_id);
		$mission_result["currency"] = $project["currency"];
		
		// If success, update balance and log credit transaction
		if($success == 1){
		    $random = 0;
		    $consumables = array();
		    $consumables = getConsumables($conn);
		    $consumable_ranges = array();
		    $consumable_ranges = getConsumableRanges($conn);
		    $random = rand(1, 100);
		    $consumable_id = 0;
		    foreach($consumable_ranges AS $id => $range){
		  	  foreach($range AS $start => $end){
		  		  if($random >= $start && $random <= $end){
		  			  $consumable_id = $id;
		  		  }
		  	  }
		    }
			updateAmount($conn, $_SESSION['userData']['user_id'], $consumable_id, 1);
			updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, $reward);
			logCredit($conn, $_SESSION['userData']['user_id'], $reward, $project_id, 0, 0, $mission_id);
			$mission_result["status"] = "SUCCESS";
			$mission_result["consumable"] = strtolower(str_replace("%", "", str_replace(" ", "-", $consumables[$consumable_id])));
		}else if($success == 2){
			$mission_result["status"] = "FAILURE";
			$mission_result["consumable"] = "";
		}
		return $mission_result;
	}else{
		echo "No Session";
	}
}

// Retreat from mission, refund cost, and restore all consumable items. Remove mission, mission NFTs, mission consumables, and mission transaction history.
function retreatMission($conn, $mission_id, $quest_id){
	$cost = 0;
	$project_id = 0;
	$currency = "";
	
	// Verify that the mission for current user is still in progress
	$sql = "SELECT * FROM missions WHERE id = '".$mission_id."' AND status = '0' AND user_id = '".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
		echo "Your Retreat was successful.\r\n\r\n";
		
	  	// output data of each row
	  	$sql = "DELETE FROM missions WHERE id = '".$mission_id."' AND user_id = '".$_SESSION['userData']['user_id']."'";
	  	if ($conn->query($sql) === TRUE) {
	  	  	//echo "Record deleted successfully";
	  	} else {
			// echo "Record was not deleted";
	  	}
	
	  	// Get quest cost, project id, and currency
	  	$sql = "SELECT title, extension, currency, cost, project_id FROM quests INNER JOIN projects ON projects.id = quests.project_id WHERE quests.id = '".$quest_id."'";
	  	$rm_title = ""; $rm_ext = "png";
	  	$result = $conn->query($sql);
	  	if ($result->num_rows > 0) {
	  	  // output data of each row
	  	  while($row = $result->fetch_assoc()) {
	  	    $cost = $row["cost"];
	  		$project_id = $row["project_id"];
	  		$currency = $row["currency"];
	  		$rm_title = $row["title"];
	  		$rm_ext   = $row["extension"];
	  	  }
	  	} else {
	  	  //echo "0 results";
	  	}
	
	  	// Restore mission cost if not zero
	  	if($cost != 0 && $project_id != 0){
	  		echo $cost." ".$currency." Refunded\r\n";
	  		updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, $cost);
	  	}
	
	  	// Restore consumable items
	  	$rm_restored_items = array();
	  	$sql = "SELECT name, consumable_id FROM missions_consumables INNER JOIN consumables ON consumables.id = missions_consumables.consumable_id WHERE mission_id = '".$mission_id."'";
	  	$result = $conn->query($sql);
	  	if ($result->num_rows > 0) {
	  	  // output data of each row
	  	  while($row = $result->fetch_assoc()) {
	  		echo $row["name"]." Restored\r\n";
	  		$rm_restored_items[] = $row["name"];
	  	    updateAmount($conn, $_SESSION['userData']['user_id'], $row["consumable_id"], 1);
	  	  }
	  	} else {
	  	  //echo "0 results";
	  	}

	  	// Discord webhook — mission retreat
	  	$rm_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
	  	$rm_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
	  	$rm_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
	  	$rm_avatar_url = ($rm_discord && $rm_avatar) ? "https://cdn.discordapp.com/avatars/".$rm_discord."/".$rm_avatar.".png" : "";
	  	$rm_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($rm_username);
	  	$rm_slug       = strtolower(str_replace("'", "", str_replace(" ", "-", $rm_title)));
	  	$rm_image_url  = $rm_title ? "https://skulliance.io/staking/images/missions/".$rm_slug.".".$rm_ext : "";
	  	$rm_mention    = $rm_discord ? "<@".$rm_discord.">" : $rm_username;
	  	$rm_desc       = $rm_mention." has retreated from a mission.\n\n";
	  	$rm_desc      .= "📜 **Quest:** ".$rm_title."\n";
	  	if($cost > 0) $rm_desc .= "💰 **Refunded:** ".$cost." ".$currency."\n";
	  	if(!empty($rm_restored_items)) $rm_desc .= "🎒 **Items Restored:** ".implode(", ", $rm_restored_items);
	  	$rm_author = array("name" => $rm_username, "icon_url" => $rm_avatar_url, "url" => $rm_profile);
	  	discordmsg("🏳️ Mission Retreated", $rm_desc, $rm_image_url, "https://skulliance.io/staking/missions.php", "missions", $rm_avatar_url, "888888", $rm_author);

	  	// Delete Mission NFTs
    	$sql = "DELETE FROM missions_nfts WHERE mission_id = '".$mission_id."'";
    	if ($conn->query($sql) === TRUE) {
    	  //echo "Record deleted successfully";
    	} else {
    	  //echo "Error: " . $sql . "<br>" . $conn->error;
    	}
	
	  	// Delete Mission Consumables
    	$sql = "DELETE FROM missions_consumables WHERE mission_id = '".$mission_id."'";
    	if ($conn->query($sql) === TRUE) {
    	  //echo "Record deleted successfully";
    	} else {
    	  //echo "Error: " . $sql . "<br>" . $conn->error;
    	}

	  	// Delete Mission Transaction History
    	$sql = "DELETE FROM transactions WHERE mission_id = '".$mission_id."'";
    	if ($conn->query($sql) === TRUE) {
    	  //echo "Record deleted successfully";
    	} else {
    	  //echo "Error: " . $sql . "<br>" . $conn->error;
    	}
	} else {
	    echo "Your Retreat was unsuccessful.\r\n\r\nPlease refresh the webpage and try again.";
	}
}

// Get user amounts for all consumables
function getCurrentAmounts($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT amount, name, consumables.id AS consumable_id FROM amounts INNER JOIN consumables ON amounts.consumable_id = consumables.id WHERE user_id = '".$_SESSION['userData']['user_id']."' ORDER BY consumables.id ASC";
		$result = $conn->query($sql);
	
		$consumables = array();
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
		    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
	        $consumables[$row["consumable_id"]] = array();
			$consumables[$row["consumable_id"]]["name"] = $row["name"];
			$consumables[$row["consumable_id"]]["amount"] = $row["amount"];
		  }
		} else {
		  //echo "0 results";
		}
		return $consumables;
	}
}

// Get current amount for user for a specific consumable
function getCurrentAmount($conn, $user_id, $consumable_id){
	$user_id       = intval($user_id);
	$consumable_id = intval($consumable_id);
	$result = $conn->query("SELECT COALESCE(SUM(amount), 0) AS total FROM amounts WHERE user_id = $user_id AND consumable_id = $consumable_id");
	if ($result) return intval($result->fetch_assoc()['total']);
	return 0;
}

// Update specific user amount for a specific consumable
function updateAmount($conn, $user_id, $consumable_id, $subtotal){
	$user_id       = intval($user_id);
	$consumable_id = intval($consumable_id);
	$subtotal      = intval($subtotal);
	// Collapse duplicate rows: sum all amounts into the lowest-id row, delete the rest
	$agg = $conn->query("SELECT MIN(id) AS keep_id, COALESCE(SUM(amount), 0) AS total FROM amounts WHERE user_id = $user_id AND consumable_id = $consumable_id");
	if ($agg && ($row = $agg->fetch_assoc()) && $row['keep_id'] !== null) {
		$new_total = max(0, intval($row['total']) + $subtotal);
		$keep_id   = intval($row['keep_id']);
		$conn->query("UPDATE amounts SET amount = $new_total WHERE id = $keep_id");
		$conn->query("DELETE FROM amounts WHERE user_id = $user_id AND consumable_id = $consumable_id AND id != $keep_id");
	} else if ($subtotal > 0) {
		$conn->query("INSERT INTO amounts (amount, user_id, consumable_id) VALUES ($subtotal, $user_id, $consumable_id)");
	}
}

// Get consumable ranges for probability of rewards
function getConsumableRanges($conn){
	$sql = "SELECT id, rate FROM consumables ORDER BY rate ASC";
	$result = $conn->query($sql);
	
	$consumables = array();
	$total = 0;
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  $consumables[$row["id"]] = array();
     	  $consumables[$row["id"]][$total+1] = $row["rate"]+$total;
		  $total += $row["rate"];
	  }
	  return $consumables;
    }
}

// Get consumables ids and names
function getConsumables($conn){
	$sql = "SELECT id, name FROM consumables";
	$result = $conn->query($sql);
	
	$consumables = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  $consumables[$row["id"]] = $row["name"];
	  }
	  return $consumables;
    }
}

function getMissionConsumables($conn, $mission_id){
	$sql = "SELECT consumables.id AS consumable_id, name FROM consumables INNER JOIN missions_consumables ON consumables.id = missions_consumables.consumable_id WHERE missions_consumables.mission_id = '".$mission_id."' ORDER BY consumables.id ASC";
	$result = $conn->query($sql);
	
	$consumables = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  $consumables[$row["consumable_id"]] = $row["name"];
	  }
	  return $consumables;
    }
}

// Check if user already exists, if not... create them.
function checkUser($conn) {
	if(isset($_SESSION['userData']['discord_id'])){
		$sql = "SELECT id, discord_id, username FROM users WHERE discord_id='".$_SESSION['userData']['discord_id']."'";
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
		    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
	    	$_SESSION['userData']['user_id'] = strval($row["id"]);
			updateUser($conn);
		  }
		} else {
		  //echo "0 results";
		  createUser ($conn, $_SESSION);
		}
	}
}

// Create a user that has visited the site for the first time.
function createUser($conn) {
	$cu_discord_id = preg_replace('/[^0-9]/', '', $_SESSION['userData']['discord_id']);
	$cu_avatar     = $conn->real_escape_string($_SESSION['userData']['avatar']);
	$cu_name       = $conn->real_escape_string($_SESSION['userData']['name']);
	$sql = "INSERT INTO users (discord_id, avatar, username)
	VALUES ('".$cu_discord_id."', '".$cu_avatar."', '".$cu_name."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
	// Immediately check user to set session variable and prevent first run errors
	checkUser($conn);
	initializeBalances($conn);
}

// Get username
function getUsername($conn) {
	$sql = "SELECT username FROM users WHERE id='".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
		return $row["username"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Get discord ID
function getDiscordID($conn) {
	$sql = "SELECT discord_id FROM users WHERE id='".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
		return $row["discord_id"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Update user to maintain current username
function updateUser($conn) {
	$uu_name   = $conn->real_escape_string($_SESSION['userData']['name']);
	$uu_avatar = $conn->real_escape_string($_SESSION['userData']['avatar']);
	$sql = "UPDATE users SET username='".$uu_name."', avatar='".$uu_avatar."' WHERE id='".$_SESSION['userData']['user_id']."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Create address for user
function createAddress($conn, $stake_address, $address) {
	$stake_address = $conn->real_escape_string($stake_address);
	$address       = $conn->real_escape_string($address);
	$sql = "INSERT INTO wallets (stake_address, address, user_id)
	VALUES ('".$stake_address."', '".$address."', '".$_SESSION['userData']['user_id']."')";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Check user's Cardano address
function checkAddress($conn, $stake_address, $address) {
	if(isset($_SESSION['userData']['user_id'])){
		$stake_address = $conn->real_escape_string($stake_address);
		$sql = "SELECT stake_address FROM wallets WHERE stake_address='".$stake_address."'";
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
		  // output data of each row
		} else {
		  //echo "0 results";
			createAddress($conn, $stake_address, $address);
		}
	}
}

// Get user wallet addresses
function getAddresses($conn) {
	$sql = "SELECT stake_address FROM wallets WHERE user_id='".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	
    $addresses = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$addresses[] = $row["stake_address"];
	  }
	} else {
	  //echo "0 results";
	}
	return $addresses;
}

// Get user wallet addresses based on discord ID
function getAddressesDiscord($conn) {
	$sql = "SELECT stake_address FROM wallets INNER JOIN users ON wallets.user_id = users.id WHERE users.discord_id='".$_SESSION['userData']['discord_id']."'";
	$result = $conn->query($sql);
	
    $addresses = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$addresses[] = $row["stake_address"];
	  }
	} else {
	  //echo "0 results";
	}
	return $addresses;
}

// Get all addresses 
function getAllAddresses($conn){
	$sql = "
		-- Active users: logged in within the last month
		SELECT DISTINCT w.stake_address FROM wallets w
		JOIN users u ON u.id = w.user_id
		WHERE u.last_login >= NOW() - INTERVAL 1 MONTH

		UNION

		-- Always include Diamond Skull owners (collection_id = 16) regardless of last login
		SELECT DISTINCT w.stake_address FROM wallets w
		JOIN nfts n ON n.user_id = w.user_id
		WHERE n.collection_id = 16

		UNION

		-- Always include owners of NFTs delegated to any Diamond Skull regardless of last login
		SELECT DISTINCT w.stake_address FROM wallets w
		JOIN nfts n ON n.user_id = w.user_id
		JOIN diamond_skulls ds ON ds.nft_id = n.id
	";
	$result = $conn->query($sql);

	$addresses = array();
	if ($result->num_rows > 0) {
	  while($row = $result->fetch_assoc()) {
    	$addresses[] = $row["stake_address"];
	  }
	}
	return $addresses;
}

// Get all collection policies
function getPolicies($conn){
	$sql = "SELECT policy FROM collections";
	$result = $conn->query($sql);
	
    $policies = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$policies[] = $row["policy"];
	  }
	} else {
	  //echo "0 results";
	}
	return $policies;
}

// Get collection id
function getCollectionId($conn, $policy){
	$policy = $conn->real_escape_string($policy);
	$sql = "SELECT id FROM collections WHERE policy='".$policy."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	return $row["id"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Get all collection policies and ids
function getCollectionIDs($conn){
	$sql = "SELECT id, policy FROM collections";
	$result = $conn->query($sql);
	
	$collections = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$collections[$row["policy"]] = $row["id"];
	  }
	} else {
	  //echo "0 results";
	}
	return $collections;
}

// Create NFT
function createNFT($conn, $asset_id, $asset_name, $name, $ipfs, $collection_id, $user_id){
	$sql = "INSERT INTO nfts (asset_id, asset_name, name, ipfs, collection_id, user_id)
	VALUES ('".$asset_id."', '".mysqli_real_escape_string($conn, $asset_name)."', '".mysqli_real_escape_string($conn, $name)."', '".$ipfs."', '".$collection_id."', '".$user_id."')";
	if ($conn->query($sql) === TRUE) {
  	  $last_id = $conn->insert_id;
  	  return $last_id;
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	  return false;
	}
}

// Update NFT for user
function updateNFT($conn, $asset_id, $user_id) {
	$sql = "UPDATE nfts SET user_id='".$user_id."' WHERE asset_id='".$asset_id."' AND user_id = '0' ORDER BY created_date LIMIT 1";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Check if NFT is already owned by user
function checkNFTOwner($conn, $asset_id, $user_id){
	$sql = "SELECT ipfs FROM nfts WHERE asset_id='".$asset_id."' AND user_id = '".$user_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  return true;
	} else {
	  //echo "0 results";
	  return false;
	}
}

// Generate array of concatenated user ids and asset ids to reduce db queries
function getNFTOwners($conn){
	$sql = "SELECT asset_id, user_id FROM nfts WHERE user_id != '0'";
	$result = $conn->query($sql);
	
    $nft_owners = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$nft_owners[] = $row["user_id"]."-".$row["asset_id"];
	  }
	} else {
	  //echo "0 results";
	}
	return $nft_owners;
}

// Check if available NFT
function checkAvailableNFT($conn, $asset_id){
	$sql = "SELECT user_id FROM nfts WHERE asset_id='".$asset_id."' AND user_id = '0' LIMIT 1";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  return true;
	} else {
	  //echo "0 results";
	  return false;
	}
}

// Remove all NFT user ids in preparation for cron job verification
function removeUsers($conn){
	// Only clear NFT ownership for users who are about to be re-verified:
	// active users (logged in within last month), Diamond Skull owners, and delegators.
	// Inactive users retain their NFT associations for leaderboard history.
	// Derived table wrapper required because legs 2+3 reference nfts, the table being updated.
	$sql = "
		UPDATE nfts SET user_id = 0
		WHERE user_id IN (
			SELECT user_id FROM (
				SELECT id AS user_id FROM users
				WHERE last_login >= NOW() - INTERVAL 1 MONTH

				UNION

				SELECT DISTINCT user_id FROM nfts
				WHERE collection_id = 16

				UNION

				SELECT DISTINCT n.user_id FROM nfts n
				JOIN diamond_skulls ds ON ds.nft_id = n.id
			) AS active_users
		)
	";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Remove specific user from their NFTs
function removeUser($conn, $user_id){
	$sql = "UPDATE nfts set	user_id = 0 WHERE user_id = '".$user_id."'";
	
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Render IPFS
function getIPFS($ipfs, $collection_id, $project_id = 0){
	if(str_contains($ipfs, "data:image/svg+xml;base64")){
		return $ipfs;
	}
	// Check for locally cached image first
	if($project_id > 0){
		$matches = glob(__DIR__ . '/images/nfts/' . $project_id . '/' . $collection_id . '/' . md5($ipfs) . '.*');
		if(!empty($matches)){
			$ext = pathinfo($matches[0], PATHINFO_EXTENSION);
			return '/staking/images/nfts/' . $project_id . '/' . $collection_id . '/' . md5($ipfs) . '.' . $ext;
		}
	}
	// Fall back to JPGStore
	$ipfs = str_replace("ipfs/", "", $ipfs);
	return "https://ipfs5.jpgstoreapis.com/ipfs/".$ipfs;
}

// Render IPFS
function renderIPFS($ipfs, $collection_id, $ipfs_format, $icon=false){
	$class = "";
	if($icon){
		$class = "class='icon' ";
	}
	if(!str_contains($ipfs, "data:image/svg+xml;base64")){
		$ipfs = str_replace("ipfs/", "", $ipfs);
	}
	if($collection_id == 4 || $collection_id == 23){
		// Resource intensive IPFS code, disabled to save server resources, swapped for fallback skull icon
		// onError='this.src=\"image.php?ipfs=".$ipfs."\";'
		return "<span class='nft-image'><img ".$class." loading='lazy' onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
	}else if($collection_id == 20 || $collection_id == 21 || $collection_id == 30 || $collection_id == 42){
		return "<span class='nft-image'><img ".$class." loading='lazy' onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
	}else if($collection_id == 260){
		return "<span class='nft-image'><img style='min-height:165px' ".$class." loading='lazy' onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
	}else{
		return "<span class='nft-image'><img ".$class." loading='lazy' onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
	}
}

// Get NFTs associated with a Diamond Skull
function getDiamondSkullNFTs($conn, $diamond_skull_id, $project_id, $projects, $project_names){
	$sql = "SELECT nfts.id AS nfts_id, asset_name, nfts.name AS nfts_name, ipfs, collections.id AS collection_id, projects.name AS project_name, nfts.user_id AS user_id, users.username AS username, rate FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.nft_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id JOIN users ON users.id = nfts.user_id WHERE diamond_skulls.diamond_skull_id = '".$diamond_skull_id."' AND collections.project_id = '".$project_id."'";
	$result = $conn->query($sql);
	
	$diamond_skull_owner = verifyDiamondSkullOwner($conn, $diamond_skull_id);
	
    $nftcounter = 0;
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
		$nftcounter++;
	    echo "<div class='diamond'><div class='diamond-data'>";
		echo "<span class='nft-name'>".substr($row["asset_name"], 0, 19)."</span>";
		echo renderIPFS($row["ipfs"], $row["collection_id"], getIPFS($row["ipfs"], $row["collection_id"], $project_id));
		echo "<span class='nft-level'><strong>".$row["username"]."</strong></span>";
		echo "<span class='nft-level'><br><strong>".$row["rate"]." CARBON</strong></span>";
		if($_SESSION['userData']['user_id'] == $row["user_id"] || $diamond_skull_owner == true){
		?>
			<form id="nftRemovalForm" action="diamond-skulls.php#diamond-skull" method="post">
			  <input type="hidden" id="remove_nft_id" name="remove_nft_id" value="<?php echo $row["nfts_id"];?>">
			  <input type="submit" value="Remove" class="small-button">
			</form>
		<?php
		}
		echo "</div></div>";
	  }
	} else {
	  //echo "0 results";
	}
	while($nftcounter < $projects[$project_id]){
	    echo "<div class='diamond'><div class='diamond-data'>";
		echo "<span class='nft-name'>".$project_names[$project_id]."<br><br>Delegation Available</span>";
		echo "</div></div>";
		$nftcounter++;
	}
}

// Verify Diamond Skull ownership
function verifyDiamondSkullOwner($conn, $diamond_skull_id){
	$sql = "SELECT user_id FROM nfts WHERE nfts.id ='".$diamond_skull_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  if($_SESSION['userData']['user_id'] == $row["user_id"]){
			  return true;
	  	  }else{
			  return false;
	  	  }
	  }
	} else {
	  //echo "0 results";
	}
}

// Get NFT project ID
function getNFTProjectID($conn, $nft_id){
	$sql = "SELECT projects.id AS project_id FROM nfts INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON projects.id = collections.project_id WHERE nfts.id ='".$nft_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  return $row["project_id"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Check whether NFT has already been delegated to a Diamond Skull
function checkNFTDelegationStatus($conn, $nft_id){
	$sql = "SELECT nft_id FROM diamond_skulls WHERE nft_id ='".$nft_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  return true;
	  }
	} else {
	  //echo "0 results";
	  return false;
	}
}

// Check Diamond Skull Project Availability
function checkDiamondSkullProjectAvailability($conn, $diamond_skull_id, $project_id, $projects){
	$sql = "SELECT COUNT(diamond_skulls.id) AS diamond_skull_index_total, nfts.id AS nft_id, projects.id AS project_id FROM diamond_skulls INNER JOIN nfts ON diamond_skulls.nft_id = nfts.id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id WHERE diamond_skull_id = '".$diamond_skull_id."' AND projects.id ='".$project_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    if($row["diamond_skull_index_total"] < $projects[$project_id]){
	    	return true;
	    }else{
	    	return false;
	    }
	  }
	} else {
	  //echo "0 results";
	  return true;
	}
}

// Add Diamond Skull NFT Association
function addDiamondSkullNFT($conn, $diamond_skull_id, $nft_id){
	$sql = "INSERT INTO diamond_skulls (diamond_skull_id, nft_id)
	VALUES ('".$diamond_skull_id."', '".$nft_id."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	  sendDiamondSkullNFTNotification($conn, $diamond_skull_id, $nft_id, $action="add");
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Remove Diamond Skull NFT Association
function removeDiamondSkullNFT($conn, $diamond_skull_id, $nft_id){
	$sql = "DELETE FROM diamond_skulls WHERE diamond_skull_id = '".$diamond_skull_id."' AND nft_id = '".$nft_id."'";

	if ($conn->query($sql) === TRUE) {
	  //echo "Record deleted successfully";
	  sendDiamondSkullNFTNotification($conn, $diamond_skull_id, $nft_id, $action="remove");
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Get NFT User ID 
function getNFTUserID($conn, $nft_id){
	$sql = "SELECT user_id FROM nfts WHERE nfts.id ='".$nft_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  return $row["user_id"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Send Discord Message regarding addition or removal of Diamond Skull delegation
function sendDiamondSkullNFTNotification($conn, $diamond_skull_id, $nft_id, $action){
	// Adjust DB query to handle user id of zero for unstaked NFTs
	if(getNFTUserID($conn, $diamond_skull_id) != 0){
		$sql = "SELECT nfts.name AS nft_name, ipfs, username, discord_id FROM nfts INNER JOIN users ON users.id = nfts.user_id WHERE nfts.id ='".$diamond_skull_id."'";
	}else{
		$sql = "SELECT nfts.name AS nft_name, ipfs FROM nfts WHERE nfts.id ='".$diamond_skull_id."'";
	}
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  $diamond_skull_name = $row["nft_name"];
		  $diamond_skull_image = $row["ipfs"];
		  if(isset($row["username"])){
		  	$diamond_skull_owner = $row["username"];
	  	  }else{
	  	  	$diamond_skull_owner = "Unknown Owner";
	  	  }
		  if(isset($row["discord_id"])){
		  	$diamond_skull_discord_id = "<@".$row["discord_id"].">";
	  	  }else{
	  	  	$diamond_skull_discord_id = "Unknown Owner";
	  	  }
	  }
	} else {
	  //echo "0 results";
	  $diamond_skull_name = "Unstaked Diamond Skull";
	  $diamond_skull_owner = "Unknown Owner";
	  $diamond_skull_discord_id = "Unknown Owner";
	}
	
	// Adjust DB query to handle user id of zero for unstaked NFTs
	if(getNFTUserID($conn, $nft_id) != 0){
		$sql = "SELECT nfts.name AS nft_name, ipfs, username, users.discord_id AS discord_id, collection_id, project_id FROM nfts INNER JOIN users ON users.id = nfts.user_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON projects.id = collections.project_id WHERE nfts.id ='".$nft_id."'";
	}else{
		$sql = "SELECT nfts.name AS nft_name, ipfs, collection_id, project_id FROM nfts INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON projects.id = collections.project_id WHERE nfts.id ='".$nft_id."'";
	}
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  $nft_name = $row["nft_name"];
		  $nft_image = $row["ipfs"];
		  if(isset($row["username"])){
			  $nft_owner = $row["username"];
		  }else{
		  	  $nft_owner = "Unknown Owner";
		  }
		  if(isset($row["discord_id"])){
		  	$nft_discord_id = "<@".$row["discord_id"].">";
	  	  }else{
	  	  	$nft_discord_id = "Unknown Owner";
	  	  }
		  $collection_id = $row["collection_id"];
		  $project_id = $row["project_id"];
	  }
	} else {
	  $nft_name = "Unstaked NFT";
	  $nft_image = "";
	  $nft_owner = "Unknown Owner";
	  $nft_discord_id = "Unknown Owner";
	  $project_id = 0;
	}
	
	$title_verbiage = "";
	$verbiage = "";
	if($action == "add"){
		$title_verbiage = "Delegation";
		$verbiage = "\r\n\r\ndelegated to\r\n\r\n";
	}else if($action == "remove"){
		$title_verbiage = "Removal";
		$verbiage = "\r\n\r\nremoved from\r\n\r\n";
	}
	$title = "Diamond Skull ".$title_verbiage;
	$description = $nft_discord_id.": ".$nft_name.$verbiage.$diamond_skull_discord_id.": ".$diamond_skull_name;
	if($project_id == 6){
		//$imageurl = getIPFS($nft_image, $collection_id);
	}else{
		// Too resource intensive
		//$imageurl = "https://www.skulliance.io/staking/image.php?ipfs=".str_replace("ipfs/", "", $nft_image);
	}
	// Defaulting to IPFS even though it doesn't work for animated GIFs
	$imageurl = getIPFS($nft_image, $collection_id, $project_id);
	discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "delegations");
}

// Get total delegations for Diamond Skulls
function getDiamondSkullTotals($conn){
	$sql = "SELECT diamond_skull_id, nft_id, project_id FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.nft_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON projects.id = collections.project_id";
	$result = $conn->query($sql);
	
	$diamond_skull_totals = array();
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  if(!isset($diamond_skull_totals[$row["diamond_skull_id"]])){
		  	$diamond_skull_totals[$row["diamond_skull_id"]] = array();
	  	  }
		  if(!isset($diamond_skull_totals[$row["diamond_skull_id"]][$row["project_id"]])){
		  	$diamond_skull_totals[$row["diamond_skull_id"]][$row["project_id"]] = 0;
		  }
		  $diamond_skull_totals[$row["diamond_skull_id"]][$row["project_id"]]++;
	  }
	  return $diamond_skull_totals;
	} else {
	  //echo "0 results";
	  return null;
	}
}

// Get total delegations for Project
function getProjectDelegationTotals($conn){
	$sql = "SELECT nft_id, project_id FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.nft_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON projects.id = collections.project_id";
	$result = $conn->query($sql);
	
	$project_delegation_totals = array();
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  if(!isset($project_delegation_totals[$row["project_id"]])){
		  	$project_delegation_totals[$row["project_id"]] = 0;
		  }
		  $project_delegation_totals[$row["project_id"]]++;
	  }
	  return $project_delegation_totals;
	} else {
	  //echo "0 results";
	  return null;
	}
}

// Get total delegators for Project
function getProjectDelegatorTotals($conn){
	$sql = "SELECT COUNT(DISTINCT user_id) AS user_total, project_id FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.nft_id INNER JOIN users ON nfts.user_id = users.id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON projects.id = collections.project_id GROUP BY project_id";
	$result = $conn->query($sql);
	
	$project_delegator_totals = array();
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  if(!isset($project_delegator_totals[$row["project_id"]])){
		  	$project_delegator_totals[$row["project_id"]] = 0;
		  }
		  $project_delegator_totals[$row["project_id"]] = $row["user_total"];
	  }
	  return $project_delegator_totals;
	} else {
	  //echo "0 results";
	  return null;
	}
}

// Get total delegators
function getDelegatorTotal($conn){
	$sql = "SELECT COUNT(DISTINCT user_id) AS user_total FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.nft_id INNER JOIN users ON nfts.user_id = users.id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON projects.id = collections.project_id";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  return $row["user_total"];
	  }
	} else {
	  //echo "0 results";
	  return null;
	}
}




// Get total rewards for Diamond Skulls delegation
function getDiamondSkullsDelegationRewards($conn){
	// Track Rewards by User ID for Delegators AND Diamond Skull Owners
	$sql = "SELECT diamond_skull_id, nft_id, rate FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.nft_id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id";
	$result = $conn->query($sql);

	$delegator_rewards = array();

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  // Delegator Rewards
		  if(!isset($delegator_rewards[$row["diamond_skull_id"]])){
		  	$delegator_rewards[$row["diamond_skull_id"]] = 0;
		  }
		  $delegator_rewards[$row["diamond_skull_id"]] = $row["rate"]+$delegator_rewards[$row["diamond_skull_id"]];
	  }
	} else {
	  //echo "0 results";
	}
	return $delegator_rewards;
}

// Get Diamond Skulls with NFTs Delegated by User
function getDelegatedDiamondSkulls($conn){
	$sql = "SELECT DISTINCT diamond_skull_id FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.nft_id WHERE nfts.user_id='".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	
	$diamond_skull_ids = "";
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  $diamond_skull_ids = $diamond_skull_ids.$row["diamond_skull_id"].",";
	  }
	  return rtrim($diamond_skull_ids, ",");
	} else {
	  //echo "0 results";
	  return $diamond_skull_ids;
	}
}

// Get NFT asset ids
function getNFTAssetIDs($conn){
	$sql = "SELECT id, asset_id FROM nfts";
	$result = $conn->query($sql);
	
	$asset_ids = array();
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  $asset_ids[$row["id"]] = $row["asset_id"];
	  }
	} else {
	  //echo "0 results";
	}
	return $asset_ids;
}

// Get NFTs
function getNFTs($conn, $filterby="", $advanced_filter="", $diamond_skull=false, $diamond_skull_id="", $core_projects=false, $diamond_skull_totals="", $page=1, $per_page=0){
	global $projects, $project_names;
	if(isset($_SESSION['userData']['user_id'])){
		if($filterby != "None" && $filterby != "" && $filterby != "core"){
			$filterby = "project_id = '".$filterby."' ";
		}else if($filterby == "core"){
			$filterby = "project_id IN(1,2,3,4,5,6) ";
		}else{
			$filterby = "";
		}
		$user_filter = "";
		if($advanced_filter == "all"){
			$user_filter = "";
		}else if($advanced_filter == "my" || $advanced_filter == ""){
			$user_filter = "user_id = '".$_SESSION['userData']['user_id']."'";
		}else if($advanced_filter == "delegated"){
			$diamond_skull_ids = getDelegatedDiamondSkulls($conn);
			if($diamond_skull_ids != ""){
				$user_filter = "nfts.id IN(".$diamond_skull_ids.")";
			}
		}else if($advanced_filter == "all delegated"){
			$user_filter = "nfts.id IN(SELECT diamond_skull_id FROM diamond_skulls)";
		}else if($advanced_filter == "empty"){
			$user_filter = "nfts.id NOT IN(SELECT diamond_skull_id FROM diamond_skulls)";
		}else if($advanced_filter != ""){
			$user_filter = "username = '".$advanced_filter."'";
		}
		$and = "";
		if(($filterby != "None" && $filterby != "") && $user_filter != ""){
			$and = " AND ";
		}
		$diamond_skull_filter = "";
		if($diamond_skull_id != ""){
			$diamond_skull_filter = " AND nfts.id = '".$diamond_skull_id."'";
			$user_filter = "";
			$and = "";
		}
		if($diamond_skull == true){
			$delegator_rewards = getDiamondSkullsDelegationRewards($conn);
		}
		
		$core_where = "";
		if($core_projects == true){
			$core_where = "AND nfts.id NOT IN(SELECT nft_id FROM diamond_skulls)";
		}
		
		$limit = "";
		if($per_page > 0){
			$offset = ($page - 1) * $per_page;
			$limit = " LIMIT " . $per_page . " OFFSET " . $offset;
		}
		$sql = "SELECT asset_id, asset_name, nfts.name AS nfts_name, ipfs, collection_id, nfts.id AS nfts_id, collections.rate AS rate, projects.currency AS currency, projects.id AS project_id, projects.name AS project_name, collections.name AS collection_name, users.username AS username FROM nfts INNER JOIN users ON users.id = nfts.user_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id WHERE ".$user_filter.$and.$filterby.$diamond_skull_filter.$core_where." ORDER BY FIELD(project_id,6,5,4,3,2,1,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50), collection_id".$limit;
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			if($diamond_skull == true){
				echo "<div class='nft'><div class='diamond-skull-data'>";
			}else{
		    	echo "<div class='nft'><div class='nft-data'>";
			}
			echo "<span class='nft-name'>".$row["nfts_name"]."</span>";
			echo "<a href='https://pool.pm/".$row["asset_id"]."' target='_blank'>".renderIPFS($row["ipfs"], $row["collection_id"], getIPFS($row["ipfs"], $row["collection_id"], $row["project_id"]))."</a>";
			if($diamond_skull == false){
				echo "<span class='nft-level'><strong>Project</strong><br>".$row["project_name"]."</span>";
				echo "<span class='nft-level'><strong>Collection</strong><br>".$row["collection_name"]."</span>";
				echo "<span class='nft-level'><strong>Reward Rate</strong><br>".$row["rate"]." ".$row["currency"]."</span>";
			}else if($diamond_skull == true){
				echo "<span class='nft-level'><strong>Owner</strong><br>".$row["username"]."</span>";
				global $skull_ranks;
				if(!empty($skull_ranks) && preg_match('/#(\d+)/', $row["nfts_name"], $skm)){
					$skn = (int)$skm[1];
					if(isset($skull_ranks[$skn])){
						echo "<span class='nft-level'><strong>Rarity Rank</strong><br>#".$skull_ranks[$skn]." of 100</span>";
					}
				}
				if($diamond_skull_id == ""){
				?>
				<form id="diamondSkullsForm" action="diamond-skulls.php" method="post">
				  <input type="hidden" id="diamond_skull_id" name="diamond_skull_id" value="<?php echo $row["nfts_id"];?>">
				  <input type="submit" value="Select" class="small-button">
				</form>
				<?php
				}
				if(isset($diamond_skull_totals[$row["nfts_id"]])){
					ksort($diamond_skull_totals[$row["nfts_id"]]);
					
					$carbon_count = 0;
					$project_rows = "";
					$previous_project_id = 0;
					foreach($diamond_skull_totals[$row["nfts_id"]] AS $project_id => $total){
						$status = "";
						if($projects[$project_id] == $total){
							$status = "Full"; 
							$carbon_count++;
						}else{
							$status = "Open"; 
						}
						if($previous_project_id != 0){
							if(($project_id - $previous_project_id) > 1){
								for ($x = 1; $x < ($project_id - $previous_project_id); $x++) {
									$project_rows = $project_rows."<tr><td align='left'>".$project_names[$project_id-$x]."</td><td align='right'>&nbsp;</td><td align='right'>Empty</td></tr>";
								}
							}
						}else{
							if($project_id > 1){
								for ($x = 1; $x < $project_id; $x++) {
									$project_rows = $project_rows."<tr><td align='left'>".$project_names[$x]."</td><td align='right'>&nbsp;</td><td align='right'>Empty</td></tr>";
								}
							}
						}
						$skulls = "";
						for ($x = 0; $x < $total; $x++) {
							$skulls = $skulls."💀";
						}
						$project_rows = $project_rows."<tr><td align='left'>".$project_names[$project_id]."</td><td align='right'>".$skulls."</td><td align='right'>".$status."</td></tr>";
						$previous_project_id = $project_id;
					}
					if($previous_project_id < 6){
						for ($x = ($previous_project_id+1); $x <= 6; $x++) {
							$project_rows = $project_rows."<tr><td align='left'>".$project_names[$x]."</td><td align='right'>&nbsp;</td><td align='right'>Empty</td></tr>";
						}
					}
					echo "<br><img class='carbon-icon' src='icons/carbon".$carbon_count.".png'/><br><br>";
					echo "<table><tr><th width='40%' align='left'>Project</th><th width='40%' align='right'>NFTs</th><th width='20%' align='right'>Status</th></tr>";
					echo $project_rows;
					echo "</table>";
					echo "<span class='nft-level'><br><strong>CARBON Rewards</strong>: ".$delegator_rewards[$row["nfts_id"]]." of 38</span>";
				}else{
					echo "<br><img class='carbon-icon' src='icons/carbon0.png'/>";
					echo "<span class='nft-level'><br><strong>All Slots Available</strong></span>";
					echo "<span class='nft-level'><br><strong>CARBON Rewards</strong>: 0 of 38</span>";
				}
			}
			if($core_projects == true){
				?>
				<form id="coreProjectsForm" action="diamond-skulls.php#diamond-skull" method="post">
				  <input type="hidden" id="nft_id" name="nft_id" value="<?php echo $row["nfts_id"];?>">
				  <input type="submit" value="Delegate" class="small-button">
				</form>
				<?php
			}
			echo "</div></div>";
		  }
		} else {
		  //echo "0 results";
		  echo "<p>You do not have any qualifying NFTs.</p>";
		}
	}
}

// Count NFTs for pagination (mirrors getNFTs WHERE logic)
function countNFTs($conn, $filterby="", $advanced_filter=""){
	if(!isset($_SESSION['userData']['user_id'])) return 0;
	if($filterby != "None" && $filterby != "" && $filterby != "core"){
		$filterby_sql = "project_id = '".$filterby."' ";
	}else if($filterby == "core"){
		$filterby_sql = "project_id IN(1,2,3,4,5,6) ";
	}else{
		$filterby_sql = "";
	}
	if($advanced_filter == "all"){
		$user_filter = "";
	}else if($advanced_filter != ""){
		$user_filter = "username = '".mysqli_real_escape_string($conn, $advanced_filter)."'";
	}else{
		$user_filter = "nfts.user_id = '".$_SESSION['userData']['user_id']."'";
	}
	$and = ($filterby_sql != "" && $user_filter != "") ? " AND " : "";
	$sql = "SELECT COUNT(*) AS total FROM nfts
		INNER JOIN users ON users.id = nfts.user_id
		INNER JOIN collections ON nfts.collection_id = collections.id
		INNER JOIN projects ON collections.project_id = projects.id
		WHERE ".$user_filter.$and.$filterby_sql;
	$result = $conn->query($sql);
	if($result && $row = $result->fetch_assoc()){
		return (int)$row['total'];
	}
	return 0;
}

// Create item
function createItem($conn, $name, $image_url, $price, $quantity, $project_id, $override=0){
	$sql = "INSERT INTO items (name, image_url, price, quantity, project_id, override)
	VALUES ('".mysqli_real_escape_string($conn, $name)."', '".mysqli_real_escape_string($conn, $image_url)."', '".$price."', '".$quantity."', '".$project_id."', '".$override."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Update item
function updateItem($conn, $item_id, $name, $image_url, $price, $quantity, $project_id){
	$sql = "UPDATE items SET name='".mysqli_real_escape_string($conn, $name)."', image_url='".mysqli_real_escape_string($conn, $image_url)."', price='".$price."', quantity='".$quantity."' WHERE id='".$item_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Get items for store
function getItems($conn, $page, $filterby=""){
	global $conn;
	if($filterby != "0" && $filterby != "exclusive"){
		$filterby = is_numeric($filterby) ? "AND project_id = '".(int)$filterby."' " : "";
	}else if($filterby == "exclusive"){
		$filterby = "AND featured = '1' ";
	}else{
		$filterby = "";
	}

	// Load all user balances keyed by project_id, and set of purchased item IDs
	$bal = [];
	$purchased = [];
	$logged_in = isset($_SESSION['userData']['user_id']);
	if($logged_in){
		$uid = (int)$_SESSION['userData']['user_id'];
		$br = $conn->query("SELECT project_id, balance FROM balances WHERE user_id = '$uid'");
		if($br) while($b = $br->fetch_assoc()) $bal[(int)$b['project_id']] = (float)$b['balance'];
		$pr = $conn->query("SELECT DISTINCT item_id FROM transactions WHERE user_id = '$uid' AND type = 'debit' AND item_id > 0");
		if($pr) while($p = $pr->fetch_assoc()) $purchased[(int)$p['item_id']] = true;
	}

	$sql = "SELECT items.id AS item_id, items.name AS item_name, image_url, price, quantity, project_id, secondary_project_id, projects.name AS project_name, projects.currency AS currency, divider, featured FROM items INNER JOIN projects ON projects.id = items.project_id WHERE quantity != 0 ".$filterby." ORDER BY featured DESC, projects.id, items.name ASC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  $nftcounter = 0;
	  $uri = $_SERVER['REQUEST_URI'];
	  $class = str_contains($uri, "store.php") ? "store-item" : "offering";
	  while($row = $result->fetch_assoc()) {
		$nftcounter++;
		$pid   = (int)$row['project_id'];
		$price = (float)$row['price'];
		$div   = (float)($row['divider'] ?: 1);
	    echo "<div class='nft ".$class."'><div class='nft-data'>";
		echo "<span class='nft-name'>".$row["item_name"]."</span>";
		echo "<span class='nft-image'><img loading='lazy' onError='this.src=\"/staking/icons/skull.png\";' src='".$row["image_url"]."' style='cursor:zoom-in;' onclick='openStoreImageModal(this.src, this.closest(\".nft-data\").querySelector(\".nft-name\").textContent)'/></span>";
		echo "<span class='nft-level'><strong>Project</strong><br>".$row["project_name"]."</span>";
		echo "<span class='nft-level'><strong>Quantity</strong><br>".$row["quantity"]."</span>";

		// Build payment options for this item
		$options = [];
		$options[] = ['project_id' => $pid, 'currency' => $row['currency'], 'price' => $price];
		if($row['secondary_project_id'] != 0){
			$sec = getProjectInfo($conn, $row['secondary_project_id']);
			$options[] = ['project_id' => (int)$row['secondary_project_id'], 'currency' => $sec['currency'], 'price' => $price];
		}
		if($pid != 7){
			$options[] = ['project_id' => 7, 'currency' => 'DIAMOND', 'price' => $price / $div];
		}

		// Show user's relevant balances
		if($logged_in){
			echo "<span class='nft-level'><strong>Your Balance</strong><br>";
			foreach($options as $opt){
				$user_bal = $bal[$opt['project_id']] ?? 0;
				$icon = strtolower($opt['currency']);
				echo "<img src='icons/{$icon}.png' style='height:14px;vertical-align:middle;margin-right:3px;'>";
				echo number_format($user_bal) . " " . $opt['currency'] . "<br>";
			}
			echo "</span>";
		}

		// Render buy buttons, disabled if user can't afford or already purchased
		$already_purchased = $logged_in && isset($purchased[(int)$row['item_id']]);
		if($already_purchased){
			echo "<span class='nft-level' style='color:#00c8a0;font-weight:bold;'>&#10003; Already Purchased</span>";
		}
		foreach($options as $opt){
			$user_bal   = $bal[$opt['project_id']] ?? 0;
			$can_afford = !$logged_in || $user_bal >= $opt['price'];
			$verbiage   = "BUY: " . number_format($opt['price']) . " " . $opt['currency'];
			renderBuyButton($row["item_id"], $opt['project_id'], $verbiage, $pid, $page, !$can_afford || $already_purchased);
		}

		echo "</div></div>";
	  }
	} else {
	  echo "<p>There are no items available.</p><p>Please contact the project to request more staking incentives.</p><p><img src='images/empty.gif'/></p>";
	}
}

// Render buy button for item
function renderBuyButton($id, $project_id, $verbiage, $primary_project_id, $page, $disabled=false){
	global $conn;
	if($disabled){
		echo "
	<form onsubmit='return false;' action='".$page.".php#store' method='post'>
	  <input type='hidden' id='item_id' name='item_id' value='".$id."'>
	  <input type='hidden' id='project_id' name='project_id' value='".$project_id."'>
	  <input type='hidden' id='primary_project_id' name='primary_project_id' value='".$primary_project_id."'>
	  <button type='button' class='small-button' disabled style='opacity:0.4;cursor:not-allowed;'>".htmlspecialchars($verbiage)."</button>
	</form>";
	}else{
		echo "
	<form onsubmit='return false;' action='".$page.".php#store' method='post'>
	  <input type='hidden' id='item_id' name='item_id' value='".$id."'>
	  <input type='hidden' id='project_id' name='project_id' value='".$project_id."'>
	  <input type='hidden' id='primary_project_id' name='primary_project_id' value='".$primary_project_id."'>
	  <button type='button' class='small-button' onclick='confirmForm(this.form, \"Purchase this item?\")'>".htmlspecialchars($verbiage)."</button>
	</form>";
	}
}

// Get item information
function getItemInfo($conn, $item_id, $project_id){
	global $conn;
	$sql = "SELECT items.id AS item_id, projects.id AS project_id, secondary_project_id, items.name AS item_name, image_url, price, projects.name AS project_name, currency, override FROM items INNER JOIN projects ON projects.id = items.project_id WHERE items.id = '".$item_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  $item = array();
	  while($row = $result->fetch_assoc()) {  
		$item["name"] = $row["item_name"];
		$item["image_url"] = $row["image_url"];
		if($project_id == 7){
			$item["currency"] = "DIAMOND";
		}else if($project_id == $row["secondary_project_id"]){
			$project = getProjectInfo($conn, $row["secondary_project_id"]);
			$item["currency"] = $project["currency"];
		}
		else{
			$item["currency"] = $row["currency"];
		}
		$item["project"] = $row["project_name"];
		$item["override"] = $row["override"];
		return $item;
	  }
	} else {
	  //echo "0 results";
	}
}

// Get item quantity
function getItemQuantity($conn, $item_id){
	$sql = "SELECT id, quantity FROM items WHERE id = '".$item_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {  
		return $row["quantity"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Update item quantity
function updateQuantity($conn, $item_id){
	$quantity = getItemQuantity($conn, $item_id);
	$quantity = $quantity - 1;
	$sql = "UPDATE items SET quantity = '".$quantity."' WHERE id='".$item_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Get item price
function getItemPrice($conn, $item_id){
	$sql = "SELECT id, price FROM items WHERE id = '".$item_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {  
		return $row["price"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Get user balance
function getBalance($conn, $project_id){
	$sql = "SELECT balance FROM balances WHERE user_id = '".$_SESSION['userData']['user_id']."' AND project_id = '".$project_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {  
		return $row["balance"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Render JS alert message
function alert($message){
	$safe = json_encode($message);
	echo "<script type='text/javascript'>window.onload = function(){ openNotify(".$safe."); };</script>";
}

// Zero out all currency upon user creation
function initializeBalances($conn){
	$sql = "SELECT id FROM projects";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
		$sql = "INSERT INTO balances (balance, user_id, project_id)
		VALUES ('0', '".$_SESSION['userData']['user_id']."', '".$row["id"]."')";

		if ($conn->query($sql) === TRUE) {
		  //echo "New record created successfully";
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
	  }
	} else {
	  //echo "0 results";
	}
}


// Deploy staking daily staking rewards
function updateBalances($conn, $diamond_skull_bonus=false){
	$sql = "SELECT user_id, collection_id, collections.rate AS rate, collections.project_id AS project_id FROM nfts INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id";
	$result = $conn->query($sql);
	
	$subtotals = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		if(!isset($subtotals[$row["user_id"]])){
			$subtotals[$row["user_id"]] = array();
		}
		if(!isset($subtotals[$row["user_id"]][$row["project_id"]])){
			$subtotals[$row["user_id"]][$row["project_id"]] = 0;
		}
		// Double Diamond Skull rewards if bonus is activated
		if($diamond_skull_bonus == true && $row["project_id"] == 7){
			$row["rate"] = $row["rate"]*2;
		}
		$current_rate = $subtotals[$row["user_id"]][$row["project_id"]];
		$subtotals[$row["user_id"]][$row["project_id"]] = strval($current_rate) + strval($row["rate"]);
	  }
	} else {
	  //echo "0 results";
	}
   	processSubtotals($conn, $subtotals);
}

// Cycle through user ids and submit subtotals for each project to current balances
function processSubtotals($conn, $subtotals){
	$sql = "SELECT id AS user_id FROM users";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
		if(isset($subtotals[$row["user_id"]])){
	    	foreach($subtotals[$row["user_id"]] AS $project_id => $subtotal){
				updateBalance($conn, $row["user_id"], $project_id, $subtotal);
				logCredit($conn, $row["user_id"], $subtotal, $project_id);
			}
		}
	  }
	} else {
	  //echo "0 results";
	}
}

// Deploy and Verify Diamond Skull Rewards for Delegators and Owners
function deployDiamondSkullRewards($conn, $percentages){
	// Populate Diamond Skull Owners
	$sql = "SELECT diamond_skull_id, user_id FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.diamond_skull_id";
	$result = $conn->query($sql);
	
	$diamond_skull_owners = array();

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()){
		  // If no owner, remove all NFTs delegated to the Diamond Skull
		  if($row["user_id"] == 0){
		  	removeDiamondSkullNFTs($conn, $row["diamond_skull_id"]);
	  	  }else{
	  	  	$diamond_skull_owners[$row["diamond_skull_id"]] = $row["user_id"];
	  	  }
	  }
	} else {
	  //echo "0 results";
	}
	
	// Track Rewards by User ID for Delegators AND Diamond Skull Owners
	$sql = "SELECT diamond_skull_id, nft_id, rate, user_id, project_id FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.nft_id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id";
	$result = $conn->query($sql);
	
	$delegator_rewards = array();

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  // If NFT has no owner, remove NFT delegation to Diamond Skull
		  if($row["user_id"] == 0){
			  removeDiamondSkullNFT($conn, $row["diamond_skull_id"], $row["nft_id"]);
		  }else{
			  // If project delegation is at 100%, double reward rates
			  if($percentages[$row["project_id"]] == 100){
			  	$row["rate"] = $row["rate"]+2;
			  }
			  // Delegator Rewards
			  if(!isset($delegator_rewards[$row["user_id"]])){
			  	$delegator_rewards[$row["user_id"]] = 0;
			  }
			  $delegator_rewards[$row["user_id"]] = $row["rate"]+$delegator_rewards[$row["user_id"]];
			  // Diamond Skull Rewards
			  if(!isset($delegator_rewards[$diamond_skull_owners[$row["diamond_skull_id"]]])){
			  	$delegator_rewards[$diamond_skull_owners[$row["diamond_skull_id"]]] = 0;
			  }
			  $delegator_rewards[$diamond_skull_owners[$row["diamond_skull_id"]]] = $row["rate"]+$delegator_rewards[$diamond_skull_owners[$row["diamond_skull_id"]]];
		  }
	  }
	} else {
	  //echo "0 results";
	}
	
	// Diamond Skull project ID for CARBON
	$project_id = 15;
	foreach($delegator_rewards AS $delegator_id => $subtotal){
		updateBalance($conn, $delegator_id, $project_id, $subtotal);
		logCredit($conn, $delegator_id, $subtotal, $project_id);
	}
}

// Remove all NFT Delegations from a Diamond Skull
function removeDiamondSkullNFTs($conn, $diamond_skull_id){
	$sql = "SELECT nft_id, diamond_skull_id FROM diamond_skulls WHERE diamond_skull_id = '".$diamond_skull_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()){
		  // Remove each NFT Delegation for this Diamond Skull
		  removeDiamondSkullNFT($conn, $row["diamond_skull_id"], $row["nft_id"]);
	  }
	} else {
	  //echo "0 results";
	}
}

// Get current balance for user for a specific project
function getCurrentBalance($conn, $user_id, $project_id){
	if($project_id == 0){
		$sql = "SELECT SUM(balance) AS balance FROM balances WHERE user_id = '".$user_id."'";
	}else{
		$sql = "SELECT balance FROM balances WHERE user_id = '".$user_id."' AND project_id = '".$project_id."'";
	}
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	return $row["balance"];
	  }
	} else {
	  //echo "0 results";
	    return "false";
	}
}

// Update specific user balance for a project
function updateBalance($conn, $user_id, $project_id, $subtotal){
	$current_balance = getCurrentBalance($conn, $user_id, $project_id);
	if($current_balance !== "false"){
		$total = $subtotal + $current_balance;
		$sql = "UPDATE balances SET balance = '".$total."' WHERE user_id='".$user_id."' AND project_id='".$project_id."'";
		if ($conn->query($sql) === TRUE) {
		  //echo "New record created successfully";
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}else{
		$sql = "INSERT INTO balances (balance, user_id, project_id)
		VALUES ('".$subtotal."', '".$user_id."', '".$project_id."')";

		if ($conn->query($sql) === TRUE) {
		  //echo "New record created successfully";
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}
}

// Get user balances for all projects
function getBalances($conn, $skulliance=true){
	if(isset($_SESSION['userData']['user_id'])){
		$project_filter = "";
		if($skulliance == true){
			$project_filter = " AND (project_id <= '7' OR project_id = '15')";
		}else{
			$project_filter = " AND project_id > '7' AND project_id != '15'";
		}
		$sql = "SELECT balance, project_id, projects.currency AS currency FROM balances INNER JOIN projects ON balances.project_id = projects.id WHERE user_id = '".$_SESSION['userData']['user_id']."' ".$project_filter;
		$result = $conn->query($sql);
	
		$balances = array();
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
		    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
	        $balances[$row["currency"]] = $row["balance"];
		  }
		} else {
		  //echo "0 results";
		}
		return $balances;
	}
}

// Get minimum balance for crafting
function getMinimumBalance($conn){
	$sql = "SELECT balance, project_id FROM balances WHERE user_id = '".$_SESSION['userData']['user_id']."' AND project_id < '7' ORDER BY balance ASC LIMIT 1";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	return $row["balance"];
	  }
	} else {
	  //echo "0 results";
	}
}

// Craft DIAMOND
function craft($conn, $balance){
	$sql = "SELECT balance, project_id FROM balances INNER JOIN projects ON balances.project_id = projects.id WHERE user_id = '".$_SESSION['userData']['user_id']."' AND project_id < '7'";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	updateBalance($conn, $_SESSION['userData']['user_id'], $row["project_id"], -$balance);
		logDebit($conn, $_SESSION['userData']['user_id'], 0, $balance, $row["project_id"], 1);
	  }
	} else {
	  //echo "0 results";
	}
	updateBalance($conn, $_SESSION['userData']['user_id'], 7, $balance);
	logCredit($conn, $_SESSION['userData']['user_id'], $balance, 7, 1);
}

// Shatter DIAMOND
function shatter($conn, $balance){
	$sql = "SELECT balance, project_id FROM balances INNER JOIN projects ON balances.project_id = projects.id WHERE user_id = '".$_SESSION['userData']['user_id']."' AND project_id < '7'";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	updateBalance($conn, $_SESSION['userData']['user_id'], $row["project_id"], $balance);
		logCredit($conn, $_SESSION['userData']['user_id'], $balance, $row["project_id"], 1);
	  }
	} else {
	  //echo "0 results";
	}
	updateBalance($conn, $_SESSION['userData']['user_id'], 7, -$balance);
	logDebit($conn, $_SESSION['userData']['user_id'], 0, $balance, 7, 1);
}

// Burn CARBON, Craft DIAMOND
function burn($conn, $balance, $project_id){
	// Update CARBON Balance and Log Debit
    updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, -$balance);
	logDebit($conn, $_SESSION['userData']['user_id'], 0, $balance, $project_id, 1);
	// Update DIAMOND Balance and Log Credit
	updateBalance($conn, $_SESSION['userData']['user_id'], 7, ($balance/100));
	logCredit($conn, $_SESSION['userData']['user_id'], ($balance/100), 7, 1);
}


// Log a specific user credit for nightly rewards
function logCredit($conn, $user_id, $amount, $project_id, $crafting=0, $bonus=0, $mission_id=0, $location_id=0, $raid_id=0) {
	$sql = "INSERT INTO transactions (type, user_id, amount, project_id, crafting, bonus, mission_id, location_id, raid_id)
	VALUES ('credit', '".$user_id."', '".$amount."', '".$project_id."', '".$crafting."', '".$bonus."', '".$mission_id."', '".$location_id."', '".$raid_id."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Log a specific user debit for an item purchase
function logDebit($conn, $user_id, $item_id, $amount, $project_id, $crafting=0, $mission_id=0, $location_id=0, $raid_id=0) {
	$sql = "INSERT INTO transactions (type, user_id, item_id, amount, project_id, crafting, mission_id, location_id, raid_id)
	VALUES ('debit', '".$user_id."', '".$item_id."', '".$amount."', '".$project_id."', '".$crafting."', '".$mission_id."', '".$location_id."', '".$raid_id."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Display transaction history for user
function transactionHistory($conn) {
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT transactions.type, amount, items.name, crafting, bonus, mission_id, location_id, raid_id, transactions.date_created, projects.currency AS currency, projects.name AS project_name, transactions.project_id AS project_id FROM transactions 
			    LEFT JOIN items ON transactions.item_id = items.id LEFT JOIN projects ON projects.id = transactions.project_id 
		        WHERE transactions.user_id='".$_SESSION['userData']['user_id']."' ORDER BY date_created DESC LIMIT 1000";
		$result = $conn->query($sql);
	
		echo "<table cellspacing='0' id='transactions'><tr><th align='left'>Date</th><th align='left'>Time</th><th align='center'>Type</th><th align='center'>Amount</th><th align='center'>Icon</th><th align='left'>Description</th></tr>";
		while($row = $result->fetch_assoc()) {
			$currency = "<img class='icon' src='icons/".strtolower($row["currency"]).".png'/>";
			$type = "<img class='icon' src='icons/".$row["type"].".png'/>";
			echo "<tr class='".$row["type"]."'>";
			$date = date("n-j-Y",strtotime("-1 hour", strtotime($row["date_created"])));
			$time = date("g:ia",strtotime("-1 hour", strtotime($row["date_created"])));
			if ($row["type"] == "credit"){
	    		echo "<td>".$date."</td><td>".$time."</td><td align='center'>".$type."</td><td align='center'>".number_format($row["amount"])." ".$row["currency"]."</td><td align='center'>";
				echo $currency;
				echo "</td><td>";
				if($row["crafting"] == 0){
					if($row["bonus"] == 1){
						echo "Daily Reward: ".$row["project_name"];
					}else if($row["mission_id"] != 0){
						$mission = getMission($conn, $row["mission_id"]);
						echo "Mission Reward: ".$mission["title"];
					}else if($row["raid_id"] != 0){
						echo "Raid Reward: Offense Success";
					}else{
						echo "Staking Reward: ".$row["project_name"];
					}
				}else{
					echo "Crafting";
				}
				echo "</td>";
			}else if ($row["type"] == "debit"){
				echo "<td>".$date."</td><td>".$time."</td><td align='center'>".$type."</td><td align='center'>".number_format($row["amount"])." ".$row["currency"]."</td>";
				echo "<td align='center'><img class='icon' src='icons/".strtolower($row["currency"]).".png'/></td>";
				if($row["crafting"] != 0){
					echo "<td>Crafting</td>";
				}
				if($row["name"] != ""){
					echo "<td>NFT Purchase: ".$row["name"]."</td>";
				}
				if($row["mission_id"] != 0){
					$mission = getMission($conn, $row["mission_id"]);
					echo "<td>Mission Cost: ".$mission["title"]."</td>";
				}
				if($row["location_id"] != 0){
					$locations = getLocationInfo($conn);
					echo "<td>Realm Upgrade: ".ucfirst($locations[$row["location_id"]]['name'])."</td>";
				}
				if($row["raid_id"] != 0){
					echo "<td>Raid Deduction: Defense Failure</td>";
				}
				
			}
			echo "</tr>";
	  	}
		echo "</table>";
	}
}

// Check transaction history for previous item purchase
function checkTransaction($conn, $item_id){
	$sql = "SELECT id FROM transactions WHERE user_id = '".$_SESSION['userData']['user_id']."' AND item_id='".$item_id."'";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
	  // output data of each row
	  return true;
	  //echo "0 results";
	}else{
	  return false;
	}
}

// Get total NFTs staked
function getTotalNFTs($conn, $project_id=0){
	$where = "";
	$inner_join = "";
	if($project_id != 0){
		if($project_id == "15"){
			$inner_join = "INNER JOIN diamond_skulls ON diamond_skulls.nft_id = nfts.id ";
		}else{
			$where = "WHERE collections.project_id = '".$project_id."'";
		}
	}
	$sql = "SELECT COUNT(nfts.id) as total FROM nfts INNER JOIN users ON nfts.user_id=users.id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id ".$inner_join.$where." AND nfts.user_id != '0'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		echo "<ul class='leaderboard'><li><strong>Total NFTs Staked: </strong>".number_format($row["total"])."<br>";
	  }
	}
	
	$sql = "SELECT COUNT(DISTINCT user_id) as total FROM nfts INNER JOIN users ON nfts.user_id=users.id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id ".$inner_join.$where." AND nfts.user_id != '0'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		echo "<strong>Total Stakers: </strong>".number_format($row["total"])."</li></ul>";
	  }
	}
}


// Get total NFTs staked count
function getTotalNFTCount($conn, $project_id=0){
	$where = "";
	$inner_join = "";
	if($project_id != 0){
		if($project_id == "15"){
			$inner_join = "INNER JOIN diamond_skulls ON diamond_skulls.nft_id = nfts.id ";
		}else{
			$where = "WHERE collections.project_id = '".$project_id."'";
		}
	}
	$sql = "SELECT COUNT(nfts.id) as total FROM nfts INNER JOIN users ON nfts.user_id=users.id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id ".$inner_join.$where." AND nfts.user_id != '0'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		echo number_format($row["total"]);
	  }
	}
}

// Get total Diamond Skulls staked
function getTotalDiamondSkulls($conn){
	$sql = "SELECT COUNT(nfts.id) as total FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id WHERE projects.id = '7' AND user_id != '0'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		return $row["total"];
	  }
	}
}

// Evaluate project delegation percentages and determine if Diamond Skulls get a bonus
function getDiamondSkullBonus($percentages){
	$bonus = true;
	foreach($percentages AS $project_id => $percentage){
		if($percentage < 100){
			$bonus = false;
		}
	}
	return $bonus;
}

// Calculate project delegation percentages based off of max/current delegations
function getProjectDelegationPercentages($conn){
	$diamond_skull_count = getTotalDiamondSkulls($conn);
	
	$max_delegations = array();
	$max_delegations[1] = $diamond_skull_count;
	$max_delegations[2] = $diamond_skull_count*2;
	$max_delegations[3] = $diamond_skull_count*3;
	$max_delegations[4] = $diamond_skull_count*4;
	$max_delegations[5] = $diamond_skull_count*4;
	$max_delegations[6] = $diamond_skull_count*5;
	
	$percentages = array();
	$project_delegations = getProjectDelegationTotals($conn);
	foreach($project_delegations AS $project_id => $total){
		$percentages[$project_id] = round($total/$max_delegations[$project_id]*100);
	}
	return $percentages;
}

function fireworks(){}

// Universal leaderboard renderer — called by all check*Leaderboard functions
function renderLeaderboardList($rows) {
    if(empty($rows)) return;
    echo "<div class='lb-list'>";
    foreach($rows as $row) {
        $hl = !empty($row['highlight']) ? ' lb-highlight' : '';
        echo "<div class='lb-row".$hl."'>";
        // Rank / trophy
        echo "<div class='lb-rank'>";
        if(!empty($row['trophy'])) {
            echo "<img class='lb-trophy' src='/staking/icons/".$row['trophy'].".png'/>";
        } else {
            $n = intval($row['rank']);
            echo "<span class='lb-rank-num'>".($n<10?'0':'').$n.".</span>";
        }
        echo "</div>";
        // Identity
        echo "<div class='lb-ident'>";
        $av_url = !empty($row['avatar_url']) ? htmlspecialchars($row['avatar_url']) : '/staking/icons/skull.png';
        echo "<img class='lb-avatar".(!empty($row['faction'])?' lb-faction-icon':'')."' src='".$av_url."' onerror=\"this.src='/staking/icons/skull.png'\" loading='lazy'/>";
        echo "<span class='lb-name'>".$row['name']."</span>";
        echo "</div>";
        // Stats chips
        echo "<div class='lb-stats'>";
        foreach($row['stats'] as $label => $value) {
            echo "<div class='lb-stat'><div class='lb-stat-label'>".htmlspecialchars($label)."</div><div class='lb-stat-val'>".$value."</div></div>";
        }
        if(!empty($row['reward'])) {
            echo "<div class='lb-stat lb-reward'><div class='lb-stat-label'>Reward</div><div class='lb-stat-val'>".$row['reward']."</div></div>";
        }
        echo "</div>"; // lb-stats
        echo "</div>"; // lb-row
    }
    echo "</div>"; // lb-list
}

// Check leaderboard for discord and site display
function checkLeaderboard($conn, $clean, $project_id=0) {
	$where = "";
	$inner_join = "";
	if($project_id != 0){
		if($project_id == '15'){
			$inner_join = "INNER JOIN diamond_skulls ON diamond_skulls.nft_id = nfts.id ";
		}else{
			$where = "WHERE collections.project_id = '".$project_id."'";
		}
	}
	$sql = "SELECT nfts.id, nfts.user_id, COUNT(nfts.id) as total, users.username, users.visibility, users.discord_id AS discord_id, avatar, projects.id AS project_id, projects.discord_id AS project_discord_id, currency FROM nfts INNER JOIN users ON nfts.user_id=users.id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id ".$inner_join.$where." GROUP BY nfts.user_id ORDER BY total DESC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
		// Clean output for discord leaderboard
		if($clean == "true") {
			$leaderboardCounter = 0;
			while($row = $result->fetch_assoc()) {
				$leaderboardCounter++;
				//$level = floor($row["total"]/100);
				echo $leaderboardCounter.". ".$row["username"].": ".$row["total"]." NFTs\n";
			}
		// Formatted output for website leaderboard
		} else {
			$fireworks = false;
			$leaderboardCounter = 0;
			$last_total = 0;
			$third_total = 0;
		  	//echo "<ul class='leaderboard'>";
			$lb_rows = [];
		  	while($row = $result->fetch_assoc()) {
				// Filter out project owners
				if(($row["discord_id"] == $row["project_discord_id"] && $row["project_discord_id"] != "772831523899965440" && $row["project_discord_id"] != "578386308406181918") || ($project_id == '5' && $row["discord_id"] == "183841115286405121")){
					// Do not generate row for project owners unless Oculus Orbus and filter out Kimosabe
				}else{
					$leaderboardCounter++;
					if($leaderboardCounter <= 3){ global $leaderboard_top3; $leaderboard_top3[] = ['username'=>$row['username'],'discord_id'=>$row['discord_id'],'avatar'=>$row['avatar'],'visibility'=>$row['visibility'],'score'=>number_format($row['total']).' NFTs']; }
					$width = 40;
					$trophy = "";
					if($leaderboardCounter == 1){
						//$width = 50;
						$trophy = "first";
						if(isset($_SESSION['userData']['user_id'])){
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
						}
						}
					}else if($leaderboardCounter == 2){
						//$width = 45;
						if($last_total != $row["total"]){
							$trophy = "second";
						}else{
							$trophy = "first";
							$leaderboardCounter--;
						}
						if(isset($_SESSION['userData']['user_id'])){
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
						}
						}
					}else if($leaderboardCounter == 3){
						//$width = 40;
						if($last_total != $row["total"]){
							$trophy = "third";
							$third_total = $row["total"];
						}else{
							$trophy = "second";
							$leaderboardCounter--;
						}
						if(isset($_SESSION['userData']['user_id'])){
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
						}
						}
					}else if($leaderboardCounter > 3 && $third_total == $row["total"]){
						$trophy = "third";
						$leaderboardCounter--;
						if(isset($_SESSION['userData']['user_id'])){
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
						}
						}
					}else if($leaderboardCounter > 3 && $last_total == $row["total"]){
						$leaderboardCounter--;
					}
					//$level = floor($row["xp"]/100);
					$highlight = "";
					if(isset($_SESSION['userData']['user_id'])){
						if($row["user_id"] == $_SESSION['userData']['user_id']){
							$highlight = "highlight";
						}
					}
					$current_balance = getCurrentBalance($conn, $row["user_id"], $project_id);
					if($current_balance == "false"){
						$current_balance = 0;
					}
					$delegated = "";
					$diamond_skull_count = "";
					if($project_id == "15"){
						$row["currency"] = "CARBON";
						$delegated = " Delegated";
						$diamond_skull_count = " - ".getDiamondSkullTotal($conn, $row["user_id"])." Diamond Skulls";
					}
					$avatar_url = ($row["avatar"] != "") ? "https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg" : '/staking/icons/skull.png';
					$name_html = "<a href='profile.php?username=".urlencode($row["username"])."'>".htmlspecialchars($row["username"])."</a>";
					$stats = ['NFTs' => $row["total"].$delegated.$diamond_skull_count, 'Points' => (($project_id != 0) ? number_format($current_balance)." ".$row["currency"] : number_format($current_balance))];
					$lb_rows[] = ['rank'=>$leaderboardCounter,'trophy'=>$trophy,'avatar_url'=>$avatar_url,'name'=>$name_html,'highlight'=>($highlight=="highlight"),'stats'=>$stats];
					$last_total = $row["total"];
				}
		  	}
			//echo "</ul>";
			renderLeaderboardList($lb_rows);
			if($fireworks){
				fireworks();
			}
		}
	} else {
	  //echo "0 results";
	}
}

function getTotalMissions($conn){
	$month_sql = "SELECT
    users.id AS user_id,
    SUM(IF(missions.status = '1', 1, 0)) AS success,
    SUM(IF(missions.status = '2', 1, 0)) AS failure,
    SUM(IF(missions.status = '0', 1, 0)) AS progress,
    COUNT(missions.id) AS total,
    SUM(quests.duration) AS total_duration
	FROM users
	INNER JOIN missions ON missions.user_id = users.id
	INNER JOIN quests ON quests.id = missions.quest_id
	WHERE users.id = '".$_SESSION['userData']['user_id']."'
	  AND DATE(missions.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')
	GROUP BY users.id";

	$month_result = $conn->query($month_sql);

	$sql = "SELECT
    users.id AS user_id,
    SUM(IF(missions.status = '1', 1, 0)) AS success,
    SUM(IF(missions.status = '2', 1, 0)) AS failure,
    SUM(IF(missions.status = '0', 1, 0)) AS progress,
    COUNT(missions.id) AS total,
    SUM(quests.duration) AS total_duration
	FROM users
	INNER JOIN missions ON missions.user_id = users.id
	INNER JOIN quests ON quests.id = missions.quest_id
	WHERE users.id = '".$_SESSION['userData']['user_id']."'
	GROUP BY users.id";

	$result = $conn->query($sql);

	// --- This Month card data ---
	$m_score = 0; $m_total = 0; $m_progress = 0; $m_success = 0; $m_failure = 0; $m_duration = 0;
	if ($month_result && $month_result->num_rows > 0) {
		$month_row = $month_result->fetch_assoc();
		$m_total    = intval($month_row["total"]);
		$m_progress = intval($month_row["progress"]);
		$m_success  = intval($month_row["success"]);
		$m_failure  = intval($month_row["failure"]);
		$m_duration = intval($month_row["total_duration"]);
		$m_score    = calculateScore($m_duration, $m_success, $m_failure, $m_progress);
	}
	$m_completed = $m_total - $m_progress;
	$m_spct = ($m_completed > 0) ? round($m_success / $m_completed * 100, 1) : 0;
	$m_fpct = ($m_completed > 0) ? round($m_failure / $m_completed * 100, 1) : 0;

	echo "<div class='rs-grid'>";

	// This Month card
	echo "<div class='rs-card'>";
	echo "<div class='rs-card-period'><img class='missions-icon' src='icons/calendar.png'/> ".date('F')."</div>";
	echo "<div class='rs-score-label'>Score</div>";
	echo "<div class='rs-score'>".number_format($m_score)."</div>";
	echo "<div class='rs-stat-row'>";
	echo "<div class='rs-stat'><div class='rs-stat-value'>".number_format($m_total)."</div><div class='rs-stat-label'>Total Missions</div></div>";
	echo "<div class='rs-stat'><div class='rs-stat-value'>".$m_progress."</div><div class='rs-stat-label'>In Progress</div></div>";
	echo "<div class='rs-stat rs-success'><div class='rs-stat-value'>".number_format($m_success)." <span class='rs-pct'>".$m_spct."%</span></div><div class='rs-stat-label'>Success</div></div>";
	echo "<div class='rs-stat rs-failure'><div class='rs-stat-value'>".number_format($m_failure)." <span class='rs-pct'>".$m_fpct."%</span></div><div class='rs-stat-label'>Failure</div></div>";
	echo "</div>"; // rs-stat-row
	echo "<div class='rs-lb-link'><form action='leaderboards.php' method='post'><input type='hidden' name='filterby' value='monthly'/><input type='submit' class='small-button' value='".date("F")." Leaderboard'/></form></div>";
	echo "</div>"; // rs-card

	// All Time card
	if ($result && $result->num_rows > 0) {
		$row = $result->fetch_assoc();
		$a_total    = intval($row["total"]);
		$a_progress = intval($row["progress"]);
		$a_success  = intval($row["success"]);
		$a_failure  = intval($row["failure"]);
		$a_duration = intval($row["total_duration"]);
		$a_score    = calculateScore($a_duration, $a_success, $a_failure, $a_progress);
		$a_completed = $a_total - $a_progress;
		$a_spct = ($a_completed > 0) ? round($a_success / $a_completed * 100, 1) : 0;
		$a_fpct = ($a_completed > 0) ? round($a_failure / $a_completed * 100, 1) : 0;
		echo "<div class='rs-card'>";
		echo "<div class='rs-card-period'><img class='missions-icon' src='icons/infinity.png'/> All Time</div>";
		echo "<div class='rs-score-label'>Score</div>";
		echo "<div class='rs-score'>".number_format($a_score)."</div>";
		echo "<div class='rs-stat-row'>";
		echo "<div class='rs-stat'><div class='rs-stat-value'>".number_format($a_total)."</div><div class='rs-stat-label'>Total Missions</div></div>";
		echo "<div class='rs-stat'><div class='rs-stat-value'>".$a_progress."</div><div class='rs-stat-label'>In Progress</div></div>";
		echo "<div class='rs-stat rs-success'><div class='rs-stat-value'>".number_format($a_success)." <span class='rs-pct'>".$a_spct."%</span></div><div class='rs-stat-label'>Success</div></div>";
		echo "<div class='rs-stat rs-failure'><div class='rs-stat-value'>".number_format($a_failure)." <span class='rs-pct'>".$a_fpct."%</span></div><div class='rs-stat-label'>Failure</div></div>";
		echo "</div>"; // rs-stat-row
		echo "<div class='rs-lb-link'><form action='leaderboards.php' method='post'><input type='hidden' name='filterby' value='missions'/><input type='submit' class='small-button' value='All Time Leaderboard'/></form></div>";
		echo "</div>"; // rs-card
	}

	echo "</div>"; // rs-grid

	// Item Inventory
	$consumables = getCurrentAmounts($conn);
	if (!empty($consumables)) {
		echo "<div class='ms-inv-header'>Item Inventory</div>";
		echo "<div class='ms-inventory'>";
		foreach ($consumables as $id => $consumable) {
			$icon_name = strtolower(str_replace("%", "", str_replace(" ", "-", $consumable["name"])));
			echo "<div class='ms-inv-pill'>";
			echo "<img class='icon' style='border:0px' src='icons/".$icon_name.".png'/>";
			echo "<span class='ms-inv-name'>".$consumable["name"]."</span>";
			echo "<span class='ms-inv-amount'>".$consumable["amount"]."</span>";
			echo "</div>";
		}
		echo "</div>";
	}
}

// Calculate score for mission stats and monthly and all time high leaderboards
function calculateScore($total_duration, $success, $failure, $progress){
	return round(((($total_duration+($success*2))-($failure/2))-$progress)+1);
}

function checkMissionsLeaderboard($conn, $monthly=false, $rewards=false){
	$carbon = 100000;
	$where = "";
	if($monthly){
		$where = "WHERE DATE(missions.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')";
	}
	if($rewards){
		$where = "WHERE DATE(missions.created_date) >= DATE_FORMAT((CURDATE() - INTERVAL 1 MONTH),'%Y-%m-01')";
	}
	$sql = "SELECT (SELECT COUNT(success_missions.id) FROM missions AS success_missions INNER JOIN users AS success_users ON success_users.id = success_missions.user_id 
					WHERE success_missions.status = '1' AND success_users.id = users.id ".str_replace("WHERE", "AND", str_replace("missions", "success_missions", $where)).") AS success, 
	               
				   (SELECT COUNT(failed_missions.id) FROM missions AS failed_missions INNER JOIN users AS failed_users ON failed_users.id = failed_missions.user_id 
				    WHERE failed_missions.status = '2' AND failed_users.id = users.id ".str_replace("WHERE", "AND", str_replace("missions", "failed_missions", $where)).") AS failure, 
				   
				   (SELECT COUNT(progress_missions.id) FROM missions AS progress_missions INNER JOIN users AS progress_users ON progress_users.id = progress_missions.user_id 
				    WHERE progress_missions.status = '0' AND progress_users.id = users.id ".str_replace("WHERE", "AND", str_replace("missions", "progress_missions", $where)).") AS progress, 
	        
			COUNT(missions.id) AS total, SUM(quests.duration) AS total_duration, users.id AS user_id, discord_id, username, avatar, discord_id, visibility 
		    FROM users INNER JOIN missions ON missions.user_id = users.id INNER JOIN quests ON quests.id = missions.quest_id ".$where." GROUP BY users.id ORDER BY total DESC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		$lb_rows = [];
		if($monthly){
			// monthly mode — reward column will be included in lb_rows
		}
		$fireworks = false;
		$leaderboardCounter = 0;
		$last_total = 0;
		$third_total = 0;
		$width = 40;
		$missions = array();
		$index = 0;
		$description = "";
		$counter = 0;
		while($row = $result->fetch_assoc()) {
			$missions[$index] = array();
			$missions[$index]["visibility"] = $row["visibility"];
			$missions[$index]["user_id"] = $row["user_id"];
			$missions[$index]["total"] = $row["total"];
			$missions[$index]["user_id"] = $row["user_id"];
			$missions[$index]["discord_id"] = $row["discord_id"];
			$missions[$index]["avatar"] = $row["avatar"];
			$missions[$index]["username"] = $row["username"];
			$missions[$index]["success"] = $row["success"];
			$missions[$index]["failure"] = $row["failure"];
			$missions[$index]["progress"] = $row["progress"];
			$missions[$index]["score"] = calculateScore($row["total_duration"], $row["success"], $row["failure"], $row["progress"]);
			$index++;
		}
		array_sort_by_column($missions, "score");
		foreach($missions AS $index => $row){
			$leaderboardCounter++;
			$counter++;
			if($leaderboardCounter <= 3){ global $leaderboard_top3; $leaderboard_top3[] = ['username'=>$row['username'],'discord_id'=>$row['discord_id'],'avatar'=>$row['avatar'],'visibility'=>$row['visibility'],'score'=>number_format($row['score']).' pts']; }
			$trophy = "";
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "first";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $row["score"]){
					$trophy = "second";
				}else{
					$trophy = "first";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 3){
				//$width = 40;
				if($last_total != $row["score"]){
					$trophy = "third";
					$third_total = $row["score"];
				}else{
					$trophy = "second";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $row["score"]){
				$trophy = "third";
				$leaderboardCounter--;
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $last_total == $row["score"]){
				$leaderboardCounter--;
			}
			$highlight = "";
			if(isset($_SESSION['userData']['user_id'])){
				if($row["user_id"] == $_SESSION['userData']['user_id']){
					$highlight = "highlight";
				}
			}
			$avatar_url = "https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg";
			$name_html = "<a href='profile.php?username=".urlencode($row["username"])."'>".htmlspecialchars($row["username"])."</a>";
			$stats = ['Score'=>number_format($row["score"]),'Missions'=>number_format($row["total"]),'Success'=>number_format($row["success"]),'Failure'=>number_format($row["failure"]),'In Progress'=>$row["progress"]];
			$reward_col = $monthly ? number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND" : '';
			$lb_rows[] = ['rank'=>$leaderboardCounter,'trophy'=>$trophy,'avatar_url'=>$avatar_url,'name'=>$name_html,'highlight'=>($highlight=="highlight"),'stats'=>$stats,'reward'=>$reward_col];
			$last_total = $row["score"];
			if($rewards){
				updateBalance($conn, $row["user_id"], 15, round($carbon/$leaderboardCounter));
				logCredit($conn, $row["user_id"], round($carbon/$leaderboardCounter), 15);
				
				// Limit number of rows added to description to prevent going over Discord notification text length limit
				if($counter <= 45){
					$description .= "- ".(($leaderboardCounter<10)?"0":"").$leaderboardCounter." "."<@".$row["discord_id"]."> - Score: ".$row["score"].", Total: ".$row["total"]."\r\n";
					//$description .= "        "."Success: ".$row["success"].", Failure: ".$row["failure"].", In Progress: ".$row["progress"]."\r\n";
					$description .= "        ".number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND\r\n";
				}
			}
		}
		if($rewards){
			$last_month = date('F', strtotime('last month'));
			$title = $last_month." Missions Leaderboard Results";
			$imageurl = "";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
		}
		renderLeaderboardList($lb_rows);
		if($fireworks){
			fireworks();
		}
	}
}

function checkRaidsLeaderboard($conn, $monthly=false, $rewards=false){
	$carbon = 1000000;
	$where = "";
	if($monthly){
		$where = "WHERE DATE(raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')";
	}
	if($rewards){
		$where = "WHERE DATE(raids.created_date) >= DATE_FORMAT((CURDATE() - INTERVAL 1 MONTH),'%Y-%m-01')";
	}
	$sql = "SELECT (SELECT COUNT(success_raids.id) FROM raids AS success_raids INNER JOIN realms AS success_realms ON success_realms.id = success_raids.offense_id INNER JOIN users AS success_users ON success_users.id = success_realms.user_id 
					WHERE success_raids.outcome = '1' AND success_users.id = users.id ".str_replace("WHERE", "AND", str_replace("raids", "success_raids", $where)).") AS success, 
	               
				   (SELECT COUNT(failed_raids.id) FROM raids AS failed_raids INNER JOIN realms AS failed_realms ON failed_realms.id = failed_raids.offense_id INNER JOIN users AS failed_users ON failed_users.id = failed_realms.user_id 
				    WHERE failed_raids.outcome = '2' AND failed_users.id = users.id ".str_replace("WHERE", "AND", str_replace("raids", "failed_raids", $where)).") AS failure, 
				   
				   (SELECT COUNT(progress_raids.id) FROM raids AS progress_raids INNER JOIN realms AS progress_realms ON progress_realms.id = progress_raids.offense_id INNER JOIN users AS progress_users ON progress_users.id = progress_realms.user_id 
				    WHERE progress_raids.outcome = '0' AND progress_users.id = users.id ".str_replace("WHERE", "AND", str_replace("raids", "progress_raids", $where)).") AS progress, 
	        
			COUNT(raids.id) AS total, SUM(raids.duration) AS total_duration, users.id AS user_id, users.discord_id AS discord_id, project_id, currency, username, avatar, visibility 
		    FROM users INNER JOIN realms ON users.id = realms.user_id INNER JOIN projects ON projects.id = realms.project_id INNER JOIN raids ON raids.offense_id = realms.id ".($where ? $where." AND realms.active = '1'" : "WHERE realms.active = '1'")." GROUP BY users.id ORDER BY total DESC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		$lb_rows = [];
		if($monthly){
			// monthly mode — reward column will be included in lb_rows
		}
		$fireworks = false;
		$leaderboardCounter = 0;
		$last_total = 0;
		$third_total = 0;
		$width = 40;
		$raids = array();
		$index = 0;
		$description = "";
		$counter = 0;
		while($row = $result->fetch_assoc()) {
			$raids[$index] = array();
			$raids[$index]["visibility"] = $row["visibility"];
			$raids[$index]["user_id"] = $row["user_id"];
			$raids[$index]["total"] = $row["total"];
			$raids[$index]["user_id"] = $row["user_id"];
			$raids[$index]["discord_id"] = $row["discord_id"];
			$raids[$index]["avatar"] = $row["avatar"];
			$raids[$index]["username"] = $row["username"];
			$raids[$index]["success"] = $row["success"];
			$raids[$index]["failure"] = $row["failure"];
			$raids[$index]["progress"] = $row["progress"];
			$raids[$index]["currency"] = $row["currency"];
			$raids[$index]["score"] = calculateScore($row["total_duration"], $row["success"], $row["failure"], $row["progress"]);
			$index++;
		}
		array_sort_by_column($raids, "score");
		foreach($raids AS $index => $row){
			$leaderboardCounter++;
			$counter++;
			if($leaderboardCounter <= 3){ global $leaderboard_top3; $leaderboard_top3[] = ['username'=>$row['username'],'discord_id'=>$row['discord_id'],'avatar'=>$row['avatar'],'visibility'=>$row['visibility'],'score'=>number_format($row['score']).' pts']; }
			$trophy = "";
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "first";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $row["score"]){
					$trophy = "second";
				}else{
					$trophy = "first";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 3){
				//$width = 40;
				if($last_total != $row["score"]){
					$trophy = "third";
					$third_total = $row["score"];
				}else{
					$trophy = "second";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $row["score"]){
				$trophy = "third";
				$leaderboardCounter--;
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $last_total == $row["score"]){
				$leaderboardCounter--;
			}
			$highlight = "";
			if(isset($_SESSION['userData']['user_id'])){
				if($row["user_id"] == $_SESSION['userData']['user_id']){
					$highlight = "highlight";
				}
			}
			$avatar_url = "https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg";
			$name_html = "<a href='profile.php?username=".urlencode($row["username"])."'>".htmlspecialchars($row["username"])."</a>";
			$stats = ['Faction'=>$row["currency"],'Score'=>number_format($row["score"]),'Raids'=>number_format($row["total"]),'Rewards'=>number_format($row["success"]),'Penalties'=>number_format($row["failure"]),'In Progress'=>$row["progress"]];
			$reward_col = $monthly ? number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND" : '';
			$lb_rows[] = ['rank'=>$leaderboardCounter,'trophy'=>$trophy,'avatar_url'=>$avatar_url,'name'=>$name_html,'highlight'=>($highlight=="highlight"),'stats'=>$stats,'reward'=>$reward_col];
			$last_total = $row["score"];
			if($rewards){
				updateBalance($conn, $row["user_id"], 15, round($carbon/$leaderboardCounter));
				logCredit($conn, $row["user_id"], round($carbon/$leaderboardCounter), 15);
				
				// Limit number of rows added to description to prevent going over Discord notification text length limit
				if($counter <= 45){
					$description .= "- ".(($leaderboardCounter<10)?"0":"").$leaderboardCounter." "."<@".$row["discord_id"]."> - Score: ".$row["score"].", Total: ".$row["total"]."\r\n";
					//$description .= "        "."Success: ".$row["success"].", Failure: ".$row["failure"].", In Progress: ".$row["progress"]."\r\n";
					$description .= "        ".number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND\r\n";
				}
			}
		}
		if($rewards){
			$last_month = date('F', strtotime('last month'));
			$title = $last_month." Raids Leaderboard Results";
			$imageurl = "";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "raids");
		}
		renderLeaderboardList($lb_rows);
		if($fireworks){
			fireworks();
		}
	}
}

function checkFactionsLeaderboard($conn, $monthly=false, $rewards=false){
	$points = 10000;
	$where = "";
	if($monthly){
		$where = "WHERE DATE(raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')";
	}
	if($rewards){
		$where = "WHERE DATE(raids.created_date) >= DATE_FORMAT((CURDATE() - INTERVAL 1 MONTH),'%Y-%m-01')";
	}
	
	$sql = "SELECT 
    realms.project_id AS project_id,
    projects.name AS project_name,
    projects.currency AS currency, -- Currency is from the projects table, no need for aggregation
    SUM(CASE WHEN raids.outcome = '1' THEN 1 ELSE 0 END) AS success,
    SUM(CASE WHEN raids.outcome = '2' THEN 1 ELSE 0 END) AS failure,
    SUM(CASE WHEN raids.outcome = '0' THEN 1 ELSE 0 END) AS progress,
    COUNT(raids.id) AS total,
    SUM(raids.duration) AS total_duration
	FROM 
    users 
    INNER JOIN realms ON users.id = realms.user_id 
    INNER JOIN projects ON projects.id = realms.project_id 
    INNER JOIN raids ON raids.offense_id = realms.id ".$where." 
    GROUP BY 
    realms.project_id
	ORDER BY 
    total DESC;";
	
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		$lb_rows = [];
		if($monthly){
			// monthly mode — reward column will be included in lb_rows
		}
		$fireworks = false;
		$leaderboardCounter = 0;
		$last_total = 0;
		$third_total = 0;
		$width = 40;
		$raids = array();
		$index = 0;
		$description = "";
		$counter = 0;
		while($row = $result->fetch_assoc()) {
			$raids[$index] = array();
			$raids[$index]["project_id"] = $row["project_id"];
			$raids[$index]["project_name"] = $row["project_name"];
			$raids[$index]["currency"] = $row["currency"];
			$raids[$index]["total"] = $row["total"];
			$raids[$index]["success"] = $row["success"];
			$raids[$index]["failure"] = $row["failure"];
			$raids[$index]["progress"] = $row["progress"];
			$raids[$index]["score"] = calculateScore($row["total_duration"], $row["success"], $row["failure"], $row["progress"]);
			$index++;
		}
		array_sort_by_column($raids, "score");
		foreach($raids AS $index => $row){
			$leaderboardCounter++;
			$counter++;
			if($leaderboardCounter <= 3){ global $leaderboard_top3; $leaderboard_top3[] = ['faction'=>true,'project_id'=>$row['project_id'],'project_name'=>$row['project_name'],'currency'=>$row['currency'],'score'=>number_format($row['score']).' pts']; }
			$trophy = "";
			$realm = getRealmInfo($conn);
			$project_id = $realm["project_id"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "first";
				if(isset($project_id)){
					if($project_id == $row["project_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $row["score"]){
					$trophy = "second";
				}else{
					$trophy = "first";
					$leaderboardCounter--;
				}
				if(isset($project_id)){
					if($project_id == $row["project_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 3){
				//$width = 40;
				if($last_total != $row["score"]){
					$trophy = "third";
					$third_total = $row["score"];
				}else{
					$trophy = "second";
					$leaderboardCounter--;
				}
				if(isset($project_id)){
					if($project_id == $row["project_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $row["score"]){
				$trophy = "third";
				$leaderboardCounter--;
				if(isset($project_id)){
					if($project_id == $row["project_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $last_total == $row["score"]){
				$leaderboardCounter--;
			}
			$highlight = "";
			if(isset($project_id)){
				if($row["project_id"] == $project_id){
					$highlight = "highlight";
				}
			}
			$avatar_url = '/staking/icons/'.strtolower($row["currency"]).'.png';
			$name_html = htmlspecialchars($row["project_name"]);
			$member_count = count(getFactionUserIDs($conn, $row["project_id"]));
			$stats = ['Members'=>$member_count,'Score'=>number_format($row["score"]),'Raids'=>number_format($row["total"]),'Rewards'=>number_format($row["success"]),'Penalties'=>number_format($row["failure"]),'In Progress'=>$row["progress"]];
			$reward_col = $monthly ? number_format(round($points/$leaderboardCounter))." ".$row["currency"] : '';
			$lb_rows[] = ['rank'=>$leaderboardCounter,'trophy'=>$trophy,'avatar_url'=>$avatar_url,'name'=>$name_html,'highlight'=>($highlight=="highlight"),'faction'=>true,'stats'=>$stats,'reward'=>$reward_col];
			$last_total = $row["score"];
			if($rewards){
				$user_ids = getFactionUserIDs($conn, $row["project_id"]);
				foreach($user_ids AS $id => $user_id){
					updateBalance($conn, $user_id, $row["project_id"], round($points/$leaderboardCounter));
					logCredit($conn, $user_id, round($points/$leaderboardCounter), $row["project_id"]);
				}
				
				// Limit number of rows added to description to prevent going over Discord notification text length limit
				if($counter <= 45){
					$description .= "- ".(($leaderboardCounter<10)?"0":"").$leaderboardCounter." "."".$row["project_name"]." - Score: ".$row["score"].", Total: ".$row["total"]."\r\n";
					//$description .= "        "."Success: ".$row["success"].", Failure: ".$row["failure"].", In Progress: ".$row["progress"]."\r\n";
					$description .= "        ".number_format(round($points/$leaderboardCounter))." ".$row["currency"]." rewarded to each member of this faction.\r\n";
				}
			}
		}
		if($rewards){
			$last_month = date('F', strtotime('last month'));
			$title = $last_month." Factions Leaderboard Results";
			$imageurl = "";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "realms");
		}
		renderLeaderboardList($lb_rows);
		if($fireworks){
			fireworks();
		}
	}
}



// Check Daily Rewards Streak Leaderboard
function checkStreaksLeaderboard($conn, $monthly=false, $rewards=false){
	$carbon = 10000;
	$where = "";
	if($monthly){
		$where = "DATE(transactions.date_created) >= DATE_FORMAT(CURDATE(),'%Y-%m-01') AND";
	}
	if($rewards){
		$where = "DATE(transactions.date_created) >= DATE_FORMAT((CURDATE() - INTERVAL 1 MONTH),'%Y-%m-01') AND";
	}
	$sql =" SELECT COUNT(transactions.id) AS streak_total, user_id, discord_id, avatar, visibility, username, streak FROM transactions INNER JOIN users ON users.id = transactions.user_id WHERE ".$where." bonus = '1' AND amount = '30' GROUP BY user_id ORDER BY streak_total DESC, streak DESC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		$fireworks = false;
		$leaderboardCounter = 0;
		$last_total = 0;
		$third_total = 0;
		$width = 40;
		$score = 0;
		$description = "";
		$counter = 0;
		$lb_rows = [];
		if($monthly){
			// monthly mode — reward column will be included in lb_rows
		}
		while($row = $result->fetch_assoc()) {
			$leaderboardCounter++;
			$counter++;
			if($leaderboardCounter <= 3){ global $leaderboard_top3; $leaderboard_top3[] = ['username'=>$row['username'],'discord_id'=>$row['discord_id'],'avatar'=>$row['avatar'],'visibility'=>$row['visibility'],'score'=>number_format($row['streak_total']).' streaks']; }
			$trophy = "";
			$score = $row["streak_total"].$row["streak"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "first";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $score){
					$trophy = "second";
				}else{
					$trophy = "first";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 3){
				//$width = 40;
				if($last_total != $score){
					$trophy = "third";
					$third_total = $score;
				}else{
					$trophy = "second";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $score){
				$trophy = "third";
				$leaderboardCounter--;
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $last_total == $score){
				$leaderboardCounter--;
			}
			$highlight = "";
			if(isset($_SESSION['userData']['user_id'])){
				if($row["user_id"] == $_SESSION['userData']['user_id']){
					$highlight = "highlight";
				}
			}
				$avatar_url = "https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg";
			$name_html = "<a href='profile.php?username=".urlencode($row["username"])."'>".htmlspecialchars($row["username"])."</a>";
			$stats = ['Streaks'=>number_format($row["streak_total"]),'Current Streak'=>$row["streak"]." days"];
			$reward_col = $monthly ? number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND" : '';
			$lb_rows[] = ['rank'=>$leaderboardCounter,'trophy'=>$trophy,'avatar_url'=>$avatar_url,'name'=>$name_html,'highlight'=>($highlight=="highlight"),'stats'=>$stats,'reward'=>$reward_col];
			$last_total = $score;
			if($rewards){
				updateBalance($conn, $row["user_id"], 15, round($carbon/$leaderboardCounter));
				logCredit($conn, $row["user_id"], round($carbon/$leaderboardCounter), 15);
				
				// Limit number of rows added to description to prevent going over Discord notification text length limit
				if($counter <= 45){
					$description .= "- ".(($leaderboardCounter<10)?"0":"").$leaderboardCounter." "."<@".$row["discord_id"]."> Total: ".$row["streak_total"].", Current Streak: ".$row["streak"]."\r\n";
					$description .= "        ".number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND\r\n";
				}
			}
		}
		if($rewards){
			$last_month = date('F', strtotime('last month'));
			$title = $last_month." Daily Rewards Streaks Leaderboard Results";
			$imageurl = "";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
		}
		renderLeaderboardList($lb_rows);
		if($fireworks){
			fireworks();
		}
	}else{
		echo "<p>No Streaks have been completed yet for the month of ".date("F").".</p>";
		echo '<form action="leaderboards.php" method="post"><input type="hidden" name="filterbystreak" id="filterbystreak" value="streaks"><input type="submit" class="small-button" value="View All Streaks Leaderboard"></form><br><br>';
		echo '<img style="width:100%;" src="images/todolist.png"/>';
	}
}

// Check Skull Swaps Leaderboard
function checkSkullSwapsLeaderboard($conn, $weekly=false, $rewards=false){
	$carbon = 25000;
	$where = "WHERE project_id = '0'";
	$attempts = "SUM(attempts) AS attempts";
	if($weekly || $rewards){
		$where = "WHERE reward = '0' AND project_id = '0'";
		$attempts = "attempts";
	}
	$sql =" SELECT ".($weekly ? "MAX" : "AVG")."(score) AS max_score, ".$attempts.", user_id, discord_id, avatar, visibility, username FROM scores INNER JOIN users ON users.id = scores.user_id ".$where." GROUP BY user_id ORDER BY max_score DESC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		$fireworks = false;
		$leaderboardCounter = 0;
		$last_total = 0;
		$third_total = 0;
		$width = 40;
		$score = 0;
		$description = "";
		$counter = 0;
		$lb_rows = [];
		if($weekly){
			// weekly mode — reward column will be included in lb_rows
		}
		while($row = $result->fetch_assoc()) {
			$leaderboardCounter++;
			$counter++;
			if($leaderboardCounter <= 3){ global $leaderboard_top3; $leaderboard_top3[] = ['username'=>$row['username'],'discord_id'=>$row['discord_id'],'avatar'=>$row['avatar'],'visibility'=>$row['visibility'],'score'=>number_format($row['max_score']).' pts']; }
			$trophy = "";
			$score = $row["max_score"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "first";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $score){
					$trophy = "second";
				}else{
					$trophy = "first";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 3){
				//$width = 40;
				if($last_total != $score){
					$trophy = "third";
					$third_total = $score;
				}else{
					$trophy = "second";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $score){
				$trophy = "third";
				$leaderboardCounter--;
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $last_total == $score){
				$leaderboardCounter--;
			}
			$highlight = "";
			if(isset($_SESSION['userData']['user_id'])){
				if($row["user_id"] == $_SESSION['userData']['user_id']){
					$highlight = "highlight";
				}
			}
			$avatar_url = "https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg";
			$name_html = "<a href='profile.php?username=".urlencode($row["username"])."'>".htmlspecialchars($row["username"])."</a>";
			$score_label = $weekly ? "High Score" : "Avg High Score";
			$stats = [$score_label=>number_format($row["max_score"]),'Attempts'=>number_format($row["attempts"])];
			$reward_col = $weekly ? number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND" : '';
			$lb_rows[] = ['rank'=>$leaderboardCounter,'trophy'=>$trophy,'avatar_url'=>$avatar_url,'name'=>$name_html,'highlight'=>($highlight=="highlight"),'stats'=>$stats,'reward'=>$reward_col];
			$last_total = $score;
			if($rewards){
				updateBalance($conn, $row["user_id"], 15, round($carbon/$leaderboardCounter));
				logCredit($conn, $row["user_id"], round($carbon/$leaderboardCounter), 15);
				
				// Limit number of rows added to description to prevent going over Discord notification text length limit
				if($counter <= 45){
					$description .= "- ".(($leaderboardCounter<10)?"0":"").$leaderboardCounter." "."<@".$row["discord_id"]."> Score: ".$row["max_score"].", Attempts: ".$row["attempts"]."\r\n";
					$description .= "        ".number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND\r\n";
				}
			}
		}
		if($rewards){
			// Mark all current scores as rewarded
			resetSwapScores($conn);
				
			$title = "Weekly Skull Swap Leaderboard Results";
			$imageurl = "";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
		}
		renderLeaderboardList($lb_rows);
		if($fireworks){
			fireworks();
		}
	}else{
		echo "<p>No Skull Swaps have been completed yet for the week.</p>";
		echo '<form action="leaderboards.php" method="post"><input type="hidden" name="filterbyswaps" id="filterbyswaps" value="swaps"><input type="submit" class="small-button" value="View All Swaps Leaderboard"></form><br><br>';
		echo '<img style="width:100%;" src="images/todolist.png"/>';
	}
}

// Check Boss Battles Leaderboard
function checkBossBattlesLeaderboard($conn, $weekly=false, $rewards=false){
	$points = 10000;
	$where = "";
	if($weekly || $rewards){
		$where = "WHERE reward = '0'";
	}
	$sql =" SELECT SUM(damage_dealt) AS damage_dealt_total, SUM(damage_taken) AS damage_taken_total, COUNT(encounters.id) AS encounters_total, user_id, discord_id, avatar, visibility, username FROM encounters INNER JOIN users ON users.id = encounters.user_id ".$where." GROUP BY user_id ORDER BY damage_dealt_total DESC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		$fireworks = false;
		$leaderboardCounter = 0;
		$last_total = 0;
		$third_total = 0;
		$width = 40;
		$score = 0;
		$description = "";
		$counter = 0;
		$lb_rows = [];
		if($weekly){
			// weekly mode — reward column will be included in lb_rows
		}
		while($row = $result->fetch_assoc()) {
			$leaderboardCounter++;
			$counter++;
			if($leaderboardCounter <= 3){ global $leaderboard_top3; $leaderboard_top3[] = ['username'=>$row['username'],'discord_id'=>$row['discord_id'],'avatar'=>$row['avatar'],'visibility'=>$row['visibility'],'score'=>number_format($row['damage_dealt_total']).' dmg']; }
			$trophy = "";
			$score = $row["damage_dealt_total"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "first";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $score){
					$trophy = "second";
				}else{
					$trophy = "first";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 3){
				//$width = 40;
				if($last_total != $score){
					$trophy = "third";
					$third_total = $score;
				}else{
					$trophy = "second";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $score){
				$trophy = "third";
				$leaderboardCounter--;
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $last_total == $score){
				$leaderboardCounter--;
			}
			$highlight = "";
			if(isset($_SESSION['userData']['user_id'])){
				if($row["user_id"] == $_SESSION['userData']['user_id']){
					$highlight = "highlight";
				}
			}
			$avatar_url = "https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg";
			$name_html = "<a href='profile.php?username=".urlencode($row["username"])."'>".htmlspecialchars($row["username"])."</a>";
			$stats = ['Dmg Dealt'=>number_format($row["damage_dealt_total"]),'Dmg Taken'=>number_format($row["damage_taken_total"]),'Encounters'=>number_format($row["encounters_total"])];
			$reward_col = $weekly ? number_format(round($points/$leaderboardCounter))." CLAW/CARBON" : '';
			$lb_rows[] = ['rank'=>$leaderboardCounter,'trophy'=>$trophy,'avatar_url'=>$avatar_url,'name'=>$name_html,'highlight'=>($highlight=="highlight"),'stats'=>$stats,'reward'=>$reward_col];
			$last_total = $score;
			if($rewards){
				updateBalance($conn, $row["user_id"], 15, round($points/$leaderboardCounter));
				logCredit($conn, $row["user_id"], round($points/$leaderboardCounter), 15);
				updateBalance($conn, $row["user_id"], 36, round($points/$leaderboardCounter));
				logCredit($conn, $row["user_id"], round($points/$leaderboardCounter), 36);
				
				// Limit number of rows added to description to prevent going over Discord notification text length limit
				if($counter <= 45){
					$description .= "- ".(($leaderboardCounter<10)?"0":"").$leaderboardCounter." "."<@".$row["discord_id"]."> Damage Dealt: ".$row["damage_dealt_total"].", Damage Taken: ".$row["damage_taken_total"].", Encounters: ".$row["encounters_total"]."\r\n";
					$description .= "        ".number_format(round($points/$leaderboardCounter))." CLAW/CARBON \r\n";
				}
			}
		}
		if($rewards){
			// Distribute all Bounties based on damage dealt
			// Mark all current encounters as rewarded
			// Reset Player Health to NULL
			// Reset Boss Health to Max Health
			resetBossBattles($conn);
				
			$title = "Weekly Boss Battle Leaderboard Results";
			$imageurl = "";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
		}
		renderLeaderboardList($lb_rows);
		if($fireworks){
			fireworks();
		}
	}else{
		echo "<p>No Boss Battles have been completed yet for the week.</p>";
		echo '<form action="leaderboards.php" method="post"><input type="hidden" name="filterbybosses" id="filterbybosses" value="bosses"><input type="submit" class="small-button" value="View All Boss Battles Leaderboard"></form><br><br>';
		echo '<img style="width:100%;" src="images/todolist.png"/>';
	}
}


// Check Monstrocity Leaderboard
function checkMonstrocityLeaderboard($conn, $monthly=false, $rewards=false){
	$claw = 30000;
	$carbon = 30000;
	$where = "WHERE project_id = '36'";
	$attempts = "SUM(attempts) AS completions";
	if($monthly || $rewards){
		$where = "WHERE reward = '0' AND project_id = '36'";
		$attempts = "attempts AS completions";
	}
	$sql =" SELECT ".($monthly ? "MAX" : "AVG")."(score) AS max_score, ".$attempts.", ".($monthly ? "MAX" : "AVG")."(level) AS max_level, user_id, discord_id, avatar, visibility, username FROM scores INNER JOIN users ON users.id = scores.user_id ".$where." GROUP BY user_id ORDER BY max_level DESC, max_score DESC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		$fireworks = false;
		$leaderboardCounter = 0;
		$last_total = 0;
		$third_total = 0;
		$width = 40;
		$score = 0;
		$description = "";
		$counter = 0;
		$lb_rows = [];
		if($monthly){
			// monthly mode — reward column will be included in lb_rows
		}
		while($row = $result->fetch_assoc()) {
			$leaderboardCounter++;
			$counter++;
			if($leaderboardCounter <= 3){ global $leaderboard_top3; $leaderboard_top3[] = ['username'=>$row['username'],'discord_id'=>$row['discord_id'],'avatar'=>$row['avatar'],'visibility'=>$row['visibility'],'score'=>'Lvl '.$row['max_level']]; }
			$trophy = "";
			$level = $row["max_level"];
			$score = $row["max_score"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "first";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $score){
					$trophy = "second";
				}else{
					$trophy = "first";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 3){
				//$width = 40;
				if($last_total != $score){
					$trophy = "third";
					$third_total = $score;
				}else{
					$trophy = "second";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $score){
				$trophy = "third";
				$leaderboardCounter--;
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $last_total == $score){
				$leaderboardCounter--;
			}
			$highlight = "";
			if(isset($_SESSION['userData']['user_id'])){
				if($row["user_id"] == $_SESSION['userData']['user_id']){
					$highlight = "highlight";
				}
			}
			$avatar_url = "https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg";
			$name_html = "<a href='profile.php?username=".urlencode($row["username"])."'>".htmlspecialchars($row["username"])."</a>";
			$level_label = $monthly ? "Level" : "Avg Level";
			$score_label = $monthly ? "Score" : "Avg Score";
			$stats = [$level_label=>$row["max_level"],$score_label=>number_format($row["max_score"]),'Completions'=>number_format($row["completions"])];
			$reward_col = $monthly ? number_format(round($claw/$leaderboardCounter))." CLAW + ".number_format(round($carbon/$leaderboardCounter))." CARBON" : '';
			$lb_rows[] = ['rank'=>$leaderboardCounter,'trophy'=>$trophy,'avatar_url'=>$avatar_url,'name'=>$name_html,'highlight'=>($highlight=="highlight"),'stats'=>$stats,'reward'=>$reward_col];
			$last_total = $score;
			if($rewards){
				updateBalance($conn, $row["user_id"], 36, round($claw/$leaderboardCounter));
				logCredit($conn, $row["user_id"], round($claw/$leaderboardCounter), 36);
				updateBalance($conn, $row["user_id"], 15, round($carbon/$leaderboardCounter));
				logCredit($conn, $row["user_id"], round($carbon/$leaderboardCounter), 15);

				// Limit number of rows added to description to prevent going over Discord notification text length limit
				if($counter <= 45){
					$description .= "- ".(($leaderboardCounter<10)?"0":"").$leaderboardCounter." "."<@".$row["discord_id"]."> Level: ".$row["max_level"].", Score: ".$row["max_score"].", Attempts: ".$row["attempts"]."\r\n";
					$description .= "        ".number_format(round($claw/$leaderboardCounter))." CLAW/CARBON\r\n";
				}
			}
		}
		if($rewards){
			// Mark all current scores as rewarded
			resetMonstrocityScores($conn);
			
			// Delete all Monstrocity progress
			deleteMonstrocityProgress($conn);
				
			$title = "Monthly Monstrocity Leaderboard Results";
			$imageurl = "";
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
		}
		renderLeaderboardList($lb_rows);
		if($fireworks){
			fireworks();
		}
	}else{
		echo "<p>No Skull Swaps have been completed yet for the week.</p>";
		echo '<form action="leaderboards.php" method="post"><input type="hidden" name="filterbyswaps" id="filterbyswaps" value="monstrocity"><input type="submit" class="small-button" value="View All Monstrocity Leaderboard"></form><br><br>';
		echo '<img style="width:100%;" src="images/todolist.png"/>';
	}
}

// Return top 3 users for podium display (separate slim query, does not touch existing render functions)
function getLeaderboardTop3($conn, $filterby){
	$rows = [];
	$score_suffix = '';

	if($filterby === 'factions' || $filterby === 'monthly-factions'){
		return null; // No per-user podium for factions
	} else if($filterby === 'missions' || $filterby === 'monthly'){
		$df = ($filterby === 'monthly') ? " AND DATE(missions.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')" : "";
		$sql = "SELECT COUNT(missions.id) AS total, users.id AS user_id, users.discord_id, users.username, users.avatar, users.visibility FROM users INNER JOIN missions ON missions.user_id = users.id INNER JOIN quests ON quests.id = missions.quest_id WHERE 1=1".$df." GROUP BY users.id ORDER BY total DESC LIMIT 3";
		$score_suffix = ' missions';
	} else if($filterby === 'streaks' || $filterby === 'monthly-streaks'){
		$df = ($filterby === 'monthly-streaks') ? " AND DATE(transactions.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')" : "";
		$sql = "SELECT COUNT(transactions.id) AS total, transactions.user_id, users.discord_id, users.avatar, users.visibility, users.username FROM transactions INNER JOIN users ON users.id = transactions.user_id WHERE bonus='1' AND amount='30'".$df." GROUP BY transactions.user_id ORDER BY total DESC LIMIT 3";
		$score_suffix = ' streaks';
	} else if($filterby === 'raids' || $filterby === 'monthly-raids'){
		$df = ($filterby === 'monthly-raids') ? " AND DATE(raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')" : "";
		$sql = "SELECT COUNT(raids.id) AS total, users.id AS user_id, users.discord_id, users.username, users.avatar, users.visibility FROM users INNER JOIN realms ON users.id = realms.user_id INNER JOIN raids ON raids.offense_id = realms.id WHERE realms.active = '1'".$df." GROUP BY users.id ORDER BY total DESC LIMIT 3";
		$score_suffix = ' raids';
	} else if($filterby === 'swaps' || $filterby === 'weekly-swaps'){
		$rf = ($filterby === 'weekly-swaps') ? " AND reward='0'" : "";
		$sql = "SELECT MAX(score) AS total, user_id, users.discord_id, users.avatar, users.visibility, users.username FROM scores INNER JOIN users ON users.id = scores.user_id WHERE project_id='0'".$rf." GROUP BY user_id ORDER BY total DESC LIMIT 3";
		$score_suffix = ' pts';
	} else if($filterby === 'bosses' || $filterby === 'weekly-bosses'){
		$rf = ($filterby === 'weekly-bosses') ? " WHERE reward='0'" : "";
		$sql = "SELECT SUM(damage_dealt) AS total, user_id, users.discord_id, users.avatar, users.visibility, users.username FROM encounters INNER JOIN users ON users.id = encounters.user_id".$rf." GROUP BY user_id ORDER BY total DESC LIMIT 3";
		$score_suffix = ' dmg';
	} else if($filterby === 'monstrocity' || $filterby === 'monthly-monstrocity'){
		$rf = ($filterby === 'monthly-monstrocity') ? " AND reward='0'" : "";
		$sql = "SELECT MAX(level) AS total, user_id, users.discord_id, users.avatar, users.visibility, users.username FROM scores INNER JOIN users ON users.id = scores.user_id WHERE project_id='36'".$rf." GROUP BY user_id ORDER BY total DESC LIMIT 3";
		$score_suffix = ' lvl';
	} else {
		// NFT count (all projects or specific project)
		$where = "nfts.user_id != 0";
		if($filterby && $filterby != 0){
			$where .= " AND collections.project_id = '".intval($filterby)."'";
		} else {
			$where .= " AND collections.project_id IN(1,2,3,4,5,6)";
		}
		$sql = "SELECT COUNT(nfts.id) AS total, nfts.user_id, users.discord_id, users.avatar, users.visibility, users.username FROM nfts INNER JOIN users ON nfts.user_id=users.id INNER JOIN collections ON collections.id=nfts.collection_id INNER JOIN projects ON projects.id=collections.project_id WHERE ".$where." GROUP BY nfts.user_id ORDER BY total DESC LIMIT 3";
		$score_suffix = ' NFTs';
	}

	$result = $conn->query($sql);
	if(!$result) return null;
	while($row = $result->fetch_assoc()){
		$rows[] = [
			'username'   => $row['username'],
			'discord_id' => $row['discord_id'],
			'avatar'     => $row['avatar'],
			'visibility' => $row['visibility'],
			'score'      => number_format((int)$row['total']).$score_suffix,
		];
	}
	return count($rows) >= 2 ? $rows : null;
}

function deleteMonstrocityProgress($conn){
	// Clear saved progress for all users
	$stmt = $conn->prepare("DELETE FROM progress WHERE project_id = 36");
	$stmt->execute();
	$stmt->close();
}

// Multidimensional array sorting
function array_sort_by_column(&$arr, $col, $dir = SORT_DESC) {
    $sort_col = array();
    foreach ($arr as $key => $row) {
        $sort_col[$key] = $row[$col];
    }

    array_multisort($sort_col, $dir, $arr);
}


// Get Diamond Skull total for specific user
function getDiamondSkullTotal($conn, $user_id=0){
	$sql = "SELECT COUNT(nfts.id) AS total FROM nfts INNER JOIN collections ON nfts.collection_id = collections.id WHERE collections.project_id='7' AND nfts.user_id = '".$user_id."'";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	  	return $row["total"];
	  }
	  //echo "0 results";
	}else{
	  return 0;
	}
}

// Get policy IDs
function getPoliciesListing($conn, $project_id=0) {
	$where = "";
	if($project_id != 0){
		$where = "WHERE collections.project_id = '".$project_id."'";
	}
	$sql = "SELECT collections.name AS collection_name, policy, rate, projects.name AS project_name, currency, COUNT(nfts.id) AS total FROM collections INNER JOIN nfts ON nfts.collection_id = collections.id INNER JOIN users ON users.id = nfts.user_id INNER JOIN projects ON projects.id = collections.project_id ".$where." AND users.id != '0' GROUP BY collections.id ORDER BY projects.id, collections.name ASC";
	$result = $conn->query($sql);
	
	echo "<table cellspacing='0' id='transactions'>";
	echo "<tr><th align='left'>Collection</th><th align='left'>Project</th><th align='left'>Reward Rate</th><th align='left'>Total Staked</th></tr>";
	if ($result->num_rows > 0) {
	  // output data of each row
	  	while($row = $result->fetch_assoc()) {
		  	echo "<tr>";
			echo "<td align='left'>"."<a target='_blank' href='https://www.jpg.store/collection/".$row["policy"]."'>".$row["collection_name"]."</a>"."</td>";
			echo "<td align='left'>".$row["project_name"]."</td>";
			echo "<td align='left'>".$row["rate"]." ".$row["currency"]."</td>";
			echo "<td align='left'>".$row["total"]."</td>";
			echo "</tr>";
	  	}
	} else {
	  //echo "0 results";
	}
	echo "</table>";
}

/* REALMS */

function checkRealm($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT id FROM realms WHERE user_id='".$_SESSION['userData']['user_id']."'";
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
			return true;
		} else {
			return false;
		}
	}
}

function createRealm($conn, $realm, $faction){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "INSERT INTO realms (name, user_id, project_id, theme_id)
		VALUES ('".mysqli_real_escape_string($conn, $realm)."', '".$_SESSION['userData']['user_id']."', '".$faction."', '7')";

		if ($conn->query($sql) === TRUE) {
		    //echo "New record created successfully";
		    $realm_id = getRealmID($conn);
			foreach(getLocationIDs($conn) AS $location_id){
				$sql = "INSERT INTO realms_locations (realm_id, location_id, level)
				VALUES ('".$realm_id."', '".$location_id."', '0')";

				if ($conn->query($sql) === TRUE) {
				  //echo "New record created successfully";
				} else {
				  //echo "Error: " . $sql . "<br>" . $conn->error;
				}
			}
			// Promotion to provide core project points to realm creators
			$projects = getProjects($conn, "core");
			foreach($projects AS $project_id => $project){
				// Update balance
				updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, 1000);
				// Log transactions
				logCredit($conn, $_SESSION['userData']['user_id'], 1000, $project_id);
			}
			$discord_mention = isset($_SESSION['userData']['discord_id']) ? "<@".$_SESSION['userData']['discord_id'].">" : "A member";
			discordmsg("New Realm Established", $discord_mention." has founded **".$realm."**", "", "https://skulliance.io/staking/realms.php", "realms");
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}
}

function updateRealmTheme($conn, $realm_id, $theme_id){
	$realm_id = (int)$realm_id; $theme_id = (int)$theme_id;
	$sql = "UPDATE realms SET theme_id='".$theme_id."' WHERE id='".$realm_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

function updateRealmFaction($conn, $realm_id, $faction){
	$realm_id = (int)$realm_id; $faction = (int)$faction;
	$sql = "UPDATE realms SET project_id='".$faction."' WHERE id='".$realm_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

function getRealmFaction($conn, $realm_id){
	$sql = "SELECT project_id FROM realms WHERE id='".$realm_id."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			return $row['project_id'];
		}
	} else {

	}
}

function verifyRealmFaction($conn, $faction){
	$sql = "SELECT DISTINCT collections.project_id AS project_id, nfts.user_id AS user_id FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id 
		    WHERE nfts.user_id='".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			if($row["project_id"] == $faction){
				return true;
			}
		}
	} else {
		return false;
	}
	return false;
}

function verifyRealmTheme($conn, $theme_id){
	$sql = "SELECT DISTINCT collections.project_id AS project_id, nfts.user_id AS user_id FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id 
		    WHERE nfts.user_id='".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			if($row["project_id"] == $theme_id){
				return true;
			}
		}
	} else {
		return false;
	}
	return false;
}

function getRealmThemeID($conn, $realm_id){
	$sql = "SELECT theme_id FROM realms WHERE id='".$realm_id."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			return $row['theme_id'];
		}
	} else {

	}
}

function updateRealmName($conn, $realm_id, $name){
	$sql = "UPDATE realms SET name='".mysqli_real_escape_string($conn, $name)."' WHERE id='".$realm_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

function getRealmInfo($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT * FROM realms WHERE user_id='".$_SESSION['userData']['user_id']."'";
		$result = $conn->query($sql);
		
		$realm = array();
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$realm["id"] = $row["id"];
				$realm["name"] = $row["name"];
				$realm["project_id"] = $row["project_id"];
				$realm["theme_id"] = $row["theme_id"];
				return $realm;
			}
		} else {

		}
	}
}

function getFactionUserIDs($conn, $faction){
	$faction = (int)$faction;
	$sql = "SELECT user_id FROM realms WHERE project_id = '".$faction."' AND active = '1'";
	$result = $conn->query($sql);
	
	$user_ids = array();
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$user_ids[$row["user_id"]] = $row["user_id"];
		}
	} else {

	}
	return $user_ids;
}

function getRealmID($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT id FROM realms WHERE user_id='".$_SESSION['userData']['user_id']."' AND active='1'";
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				return $row['id'];
			}
		} else {

		}
	}
}

function getRealmName($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT name FROM realms WHERE user_id='".$_SESSION['userData']['user_id']."'";
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				return $row['name'];
			}
		} else {

		}
	}
}

function getRealmUserID($conn, $realm_id){
	$sql = "SELECT realms.id AS realm_id, users.id AS user_id FROM realms INNER JOIN users ON users.id = realms.user_id WHERE realms.id = '".$realm_id."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			return $row["user_id"];
		}
	}else{
		
	}
}

function getLocationInfo($conn){
	$sql = "SELECT name, description, type, project_id FROM locations ORDER BY type DESC";
	$result = $conn->query($sql);
	
	$locations = array();
	$location = array();
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$location['name'] = $row['name'];
			$location['description'] = $row['description'];
			$location['type'] = $row['type'];
			$locations[$row['project_id']] = $location;
		}
	} else {

	}
	return $locations;
}

function getLocationIDs($conn){
	$sql = "SELECT id FROM locations";
	$result = $conn->query($sql);
	
	$locations = array();
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$locations[$row['id']] = $row['id'];
		}
	} else {

	}
	return $locations;
}

function getRealmLocationLevels($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$realm_id = getRealmID($conn);
		$sql = "SELECT location_id, level FROM realms_locations WHERE realm_id = '".$realm_id."'";
		$result = $conn->query($sql);
		
		$levels = array();
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$levels[$row['location_id']] = $row['level'];
			}
		} else {

		}
		return $levels;
	}
}

function getRealmLocationsWithShields($conn, $realm_id){
	$sql = "SELECT l.name, rl.location_id, rl.id AS rl_id, rl.level, l.type AS location_type FROM realms_locations rl INNER JOIN locations l ON l.id = rl.location_id WHERE rl.realm_id = '".$realm_id."' ORDER BY l.type DESC, l.id ASC";
	$result = $conn->query($sql);
	$_boost_map = array(1=>4, 2=>3, 3=>2, 4=>1);
	$locations = array();
	if($result) while($row = $result->fetch_assoc()){
		$shield_res = $conn->query("SELECT id FROM realms_locations_consumables WHERE realm_location_id='".$row['rl_id']."' AND consumable_id='6' AND raid_id='0' LIMIT 1");
		$boost_res  = $conn->query("SELECT consumable_id FROM realms_locations_consumables WHERE realm_location_id='".$row['rl_id']."' AND consumable_id IN (1,2,3,4) AND raid_id='0'");
		$_loc_boost = 0;
		if($boost_res && $boost_res->num_rows > 0){
			while($br = $boost_res->fetch_assoc()) $_loc_boost += $_boost_map[intval($br['consumable_id'])];
		}
		$locations[] = array(
			'name'          => $row['name'],
			'location_id'   => $row['location_id'],
			'level'         => $row['level'],
			'location_type' => $row['location_type'],
			'has_shield'    => ($shield_res && $shield_res->num_rows > 0),
			'success_boost' => min(10, $_loc_boost),
		);
	}
	return $locations;
}

function getRealmLocationNamesLevels($conn, $realm_id){
	$sql = "SELECT locations.name AS name, location_id, level, locations.type AS location_type FROM realms_locations INNER JOIN locations ON locations.id = realms_locations.location_id WHERE realm_id = '".$realm_id."' ORDER BY locations.type DESC, locations.id ASC";
	$result = $conn->query($sql);
	
	$levels = array();
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$levels[$row['name']] = $row['level'];
		}
	} else {

	}
	return $levels;
}

function upgradeRealmLocation($conn, $realm_id, $location_id, $duration, $cost, $project_id){
	if(isset($_SESSION['userData']['user_id'])){
		$realm_id    = intval($realm_id);
		$location_id = intval($location_id);
		$duration    = intval($duration);
		$cost        = intval($cost);
		$project_id  = intval($project_id);
		// level = target level (always the full requested duration).
		// duration = timer days: halved when Fast Forward is equipped, otherwise full.
		$ff_check = $conn->query("SELECT rlc.id FROM realms_locations_consumables rlc INNER JOIN realms_locations rl ON rl.id = rlc.realm_location_id WHERE rl.realm_id='".$realm_id."' AND rl.location_id='".$location_id."' AND rlc.consumable_id='5' AND rlc.raid_id=0");
		$timer = ($ff_check && $ff_check->num_rows > 0)
			? max(1, (int)ceil($duration / 2))
			: $duration;
		$sql = "INSERT INTO upgrades (realm_id, location_id, duration, level, created_date)
		VALUES ('".$realm_id."', '".$location_id."', '".$timer."', '".$duration."', NOW())";

		if ($conn->query($sql) === TRUE) {
		  //echo "New record created successfully";
		  updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, -$cost);
		  logDebit($conn, $_SESSION['userData']['user_id'], 0, $cost, $project_id, $crafting=0, $mission_id=0, $location_id);

		  // Discord webhook — Upgrade Initiated
		  $ug_res = $conn->query("SELECT r.name AS realm_name, r.theme_id, l.name AS loc_name, l.type AS loc_type, p.currency FROM realms r INNER JOIN locations l ON l.id='".$location_id."' INNER JOIN projects p ON p.id='".$project_id."' WHERE r.id='".$realm_id."'");
		  if ($ug_res && ($ug_row = $ug_res->fetch_assoc())) {
		    $ug_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
		    $ug_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
		    $ug_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
		    $ug_mention    = $ug_discord ? "<@".$ug_discord.">" : $ug_username;
		    $ug_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($ug_username);
		    $ug_loc_icon   = "https://skulliance.io/staking/icons/locations/".$ug_row['loc_name'].".png";
		    $ug_realm_img  = "https://skulliance.io/staking/images/themes/".$ug_row['theme_id'].".jpg";
		    $ug_cons_res   = $conn->query("SELECT c.name FROM consumables c INNER JOIN realms_locations_consumables rlc ON rlc.consumable_id=c.id INNER JOIN realms_locations rl ON rl.id=rlc.realm_location_id WHERE rl.realm_id='".$realm_id."' AND rl.location_id='".$location_id."' AND rlc.raid_id=0");
		    $ug_cons = [];
		    if ($ug_cons_res) { while ($cr = $ug_cons_res->fetch_assoc()) $ug_cons[] = $cr['name']; }
		    $ug_loc_name = ucwords($ug_row['loc_name']);
		    $ug_desc  = $ug_mention." initiated a **Level ".$duration."** upgrade on **".$ug_loc_name."** in **".$ug_row['realm_name']."**!\n\n";
		    $ug_desc .= "🏰 **Realm:** ".$ug_row['realm_name']."\n";
		    $ug_desc .= "📍 **Location:** ".$ug_loc_name." (".ucfirst($ug_row['loc_type']).")\n";
		    $ug_desc .= "⬆️ **Target Level:** ".$duration."\n";
		    $ug_desc .= "⏱️ **Duration:** ".$timer." ".($timer==1?"day":"days");
		    if ($timer < $duration) $ug_desc .= " *(Fast Forward applied)*";
		    $ug_desc .= "\n💰 **Cost:** ".number_format($cost)." ".$ug_row['currency'];
		    if (!empty($ug_cons)) $ug_desc .= "\n🎒 **Items:** ".implode(", ", $ug_cons);
		    $ug_author = array("name" => $ug_username, "icon_url" => $ug_loc_icon, "url" => $ug_profile);
		    discordmsg("🔨 Upgrade Started: ".$ug_loc_name, $ug_desc, $ug_realm_img, "https://skulliance.io/staking/realms.php", "realms", $ug_loc_icon, "FF9900", $ug_author);
		  }
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}
}

function getRealmLocationUpgrade($conn, $realm_id, $location_id){
	$sql = "SELECT id, location_id, duration, level, created_date FROM upgrades WHERE realm_id = '".$realm_id."' AND location_id = '".$location_id."'";
	$result = $conn->query($sql);

	$status = array();
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$target_level = ($row['level'] > 0) ? $row['level'] : $row['duration'];
			$date = strtotime('+'.$row["duration"].' day', strtotime($row["created_date"]));
			$remaining = $date - time();
			$days_remaining = floor(($remaining / 86400));
			$hours_remaining = floor(($remaining % 86400) / 3600);
			$minutes_remaining = floor(($remaining % 3600) / 60);
			if($date > time()){
				$time_message  = "<div class='location-meta' style='font-weight:normal;text-align:right;'>";
				$time_message .= "Lv".$target_level." upgrade";
				$time_message .= " &bull; ".$row['duration']." ".(($row['duration']==1)?"day":"days");
				$time_message .= "<br><span class='countdown' data-deadline='".$date."'>".$days_remaining."d ".$hours_remaining."h ".$minutes_remaining."m 0s</span>";
				$time_message .= "</div>";
				$status[$row['location_id']] = $time_message;
			}
		}
	}
	return $status;
}

function checkRealmLocationUpgrade($conn, $realm_id, $location_id){
	$sql = "SELECT id FROM upgrades WHERE realm_id = '".$realm_id."' AND location_id = '".$location_id."'";
	$result = $conn->query($sql);
	
	$status = array();
	if ($result->num_rows > 0) {
		return true;
	}else{
		return false;
	}
}

function getRealmLocationsUpgrades($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$realm_id = getRealmID($conn);
		$sql = "SELECT id, location_id, duration, level, created_date FROM upgrades WHERE realm_id = '".$realm_id."'";
		$result = $conn->query($sql);

		$status = array();
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$target_level = ($row['level'] > 0) ? $row['level'] : $row['duration'];
				$date = strtotime('+'.$row["duration"].' day', strtotime($row["created_date"]));
				$remaining = $date - time();
				$days_remaining = floor(($remaining / 86400));
				$hours_remaining = floor(($remaining % 86400) / 3600);
				$minutes_remaining = floor(($remaining % 3600) / 60);
				if($date > time()){
					$time_message  = "<div class='location-meta' style='font-weight:normal;text-align:right;'>";
					$time_message .= "Lv".$target_level." upgrade";
					$time_message .= " &bull; ".$row['duration']." ".(($row['duration']==1)?"day":"days");
					$time_message .= "<br><span class='countdown' data-deadline='".$date."'>".$days_remaining."d ".$hours_remaining."h ".$minutes_remaining."m 0s</span>";
					$time_message .= "</div>";
					$status[$row['location_id']] = $time_message;
				}else{
					upgradeRealmLocationLevel($conn, $realm_id, $row['location_id'], $target_level);
					deleteRealmLocationUpgrade($conn, $realm_id, $row['location_id']);
					//$time_message = "0d 0h 0m";
					// Burn Fast Forward consumable when upgrade completes
					$conn->query("DELETE rlc FROM realms_locations_consumables rlc INNER JOIN realms_locations rl ON rl.id = rlc.realm_location_id WHERE rl.realm_id='".$realm_id."' AND rl.location_id='".$row['location_id']."' AND rlc.consumable_id='5'");

					// Discord webhook — Upgrade Complete
					$uc_res = $conn->query("SELECT r.name AS realm_name, r.theme_id, l.name AS loc_name, l.type AS loc_type FROM realms r INNER JOIN locations l ON l.id='".$row['location_id']."' WHERE r.id='".$realm_id."'");
					if ($uc_res && ($uc_row = $uc_res->fetch_assoc())) {
					  $uc_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
					  $uc_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
					  $uc_mention    = $uc_discord ? "<@".$uc_discord.">" : $uc_username;
					  $uc_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($uc_username);
					  $uc_loc_icon   = "https://skulliance.io/staking/icons/locations/".$uc_row['loc_name'].".png";
					  $uc_realm_img  = "https://skulliance.io/staking/images/themes/".$uc_row['theme_id'].".jpg";
					  $uc_loc_name = ucwords($uc_row['loc_name']);
					  $uc_desc  = $uc_mention."'s **".$uc_loc_name."** in **".$uc_row['realm_name']."** has reached **Level ".$target_level."**!\n\n";
					  $uc_desc .= "🏰 **Realm:** ".$uc_row['realm_name']."\n";
					  $uc_desc .= "📍 **Location:** ".$uc_loc_name." (".ucfirst($uc_row['loc_type']).") \n";
					  $uc_desc .= "⬆️ **New Level:** ".$target_level."\n";
					  $uc_desc .= "⏱️ **Duration:** ".$row['duration']." ".($row['duration']==1?"day":"days");
					  $uc_author = array("name" => $uc_username, "icon_url" => $uc_loc_icon, "url" => $uc_profile);
					  discordmsg("✅ Upgrade Complete: ".$uc_loc_name, $uc_desc, $uc_realm_img, "https://skulliance.io/staking/realms.php", "realms", $uc_loc_icon, "00C8A0", $uc_author);
					}
				}
			}
		} else {

		}
		return $status;
	}
}

function getRealmLocationLevel($conn, $realm_id, $location_id){
	$sql = "SELECT level FROM realms_locations WHERE realm_id = '".$realm_id."' AND location_id = '".$location_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			return $row["level"];
		}
	}else{
		
	}
}

function upgradeRealmLocationLevel($conn, $realm_id, $location_id, $duration){
	$current_level = getRealmLocationLevel($conn, $realm_id, $location_id);
	// Check if current level is greater than upgrade duration. If so, sync upgrade to current level to avoid penalizing owner.
	if($current_level > $duration){
		$duration = $current_level;
	}
	// Safety precaution in case someone manages to level up a location past 10
	if($duration > 10){
		$duration = 10;
	}
	//$new_level = $current_level + 1;
	$sql = "UPDATE realms_locations SET level = '".$duration."' WHERE realm_id='".$realm_id."' AND location_id='".$location_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

function updateRealmLocationLevel($conn, $realm_id, $location_id, $amount, $type){
	$current_level = getRealmLocationLevel($conn, $realm_id, $location_id);
	if($type == "credit"){
		$new_level = $current_level + $amount;
	}else if($type == "debit"){
		if($current_level != 0){
			$new_level = $current_level - $amount;
		}else{
			$new_level = 0;
		}
	}
	$sql = "UPDATE realms_locations SET level = '".$new_level."' WHERE realm_id='".$realm_id."' AND location_id='".$location_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

function deleteRealmLocationUpgrade($conn, $realm_id, $location_id){
	$sql = "DELETE FROM upgrades WHERE realm_id = '".$realm_id."' AND location_id = '".$location_id."'";

	if ($conn->query($sql) === TRUE) {
	  //echo "Record deleted successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// ── Realm Location Consumables ─────────────────────────────────────────────
// Note: realms_locations_consumables.realm_location_id = realms_locations.id

// Look up the realms_locations.id for a given realm+location pair
function getRealmLocationID($conn, $realm_id, $location_id){
	$realm_id    = intval($realm_id);
	$location_id = intval($location_id);
	$result = $conn->query("SELECT id FROM realms_locations WHERE realm_id='".$realm_id."' AND location_id='".$location_id."' LIMIT 1");
	if($result && $result->num_rows > 0){ $row = $result->fetch_assoc(); return $row['id']; }
	return null;
}

// Returns array[location_id][consumable_id] = true for all equipped consumables on a realm
function getRealmLocationConsumables($conn, $realm_id){
	$realm_id = intval($realm_id);
	$sql = "SELECT rl.location_id, rlc.consumable_id
	        FROM realms_locations_consumables rlc
	        INNER JOIN realms_locations rl ON rl.id = rlc.realm_location_id
	        WHERE rl.realm_id = '".$realm_id."' AND rlc.raid_id = 0";
	$result = $conn->query($sql);
	$equipped = array();
	if($result && $result->num_rows > 0){
		while($row = $result->fetch_assoc()){
			$equipped[$row['location_id']][$row['consumable_id']] = true;
		}
	}
	return $equipped;
}

// Apply a consumable to a location (deducts from inventory, inserts row)
function applyLocationConsumable($conn, $realm_id, $location_id, $consumable_id){
	if(!isset($_SESSION['userData']['user_id'])) return array('error'=>'Not logged in');
	$user_id       = $_SESSION['userData']['user_id'];
	$realm_id      = intval($realm_id);
	$location_id   = intval($location_id);
	$consumable_id = intval($consumable_id);
	$qty = getCurrentAmount($conn, $user_id, $consumable_id);
	if($qty === "false" || $qty <= 0) return array('error'=>'No inventory');
	$rl_id = getRealmLocationID($conn, $realm_id, $location_id);
	if(!$rl_id) return array('error'=>'Location not found');
	$check = $conn->query("SELECT id FROM realms_locations_consumables WHERE realm_location_id='".$rl_id."' AND consumable_id='".$consumable_id."' AND raid_id=0");
	if($check && $check->num_rows > 0) return array('error'=>'Already equipped');
	updateAmount($conn, $user_id, $consumable_id, -1);
	$conn->query("INSERT INTO realms_locations_consumables (realm_location_id, consumable_id) VALUES ('".$rl_id."', '".$consumable_id."')");
	// FF applied to existing upgrade: halve remaining duration
	$upgrade_info = null;
	if($consumable_id == 5){
		$upg = $conn->query("SELECT duration, created_date FROM upgrades WHERE realm_id='".$realm_id."' AND location_id='".$location_id."'");
		if($upg && $upg->num_rows > 0){
			$upg_row    = $upg->fetch_assoc();
			$completion = strtotime('+'.$upg_row['duration'].' day', strtotime($upg_row['created_date']));
			if($completion > time()){
				$remaining_days = (int)ceil(($completion - time()) / 86400);
				$halve          = (int)ceil($remaining_days / 2);
				$new_duration   = max(0, $upg_row['duration'] - $halve);
				$conn->query("UPDATE upgrades SET duration='".$new_duration."' WHERE realm_id='".$realm_id."' AND location_id='".$location_id."'");
				// Re-query for accurate remaining seconds after update
				$upg2 = $conn->query("SELECT duration, created_date FROM upgrades WHERE realm_id='".$realm_id."' AND location_id='".$location_id."'");
				if($upg2 && $upg2->num_rows > 0){
					$row2 = $upg2->fetch_assoc();
					$comp2 = strtotime('+'.$row2['duration'].' day', strtotime($row2['created_date']));
					$upgrade_info = array('duration'=>intval($row2['duration']), 'remaining_seconds'=>max(0, $comp2 - time()));
				}
			}
		}
	}
	$new_qty = getCurrentAmount($conn, $user_id, $consumable_id);
	if($new_qty === "false") $new_qty = 0;
	$ret = array('success'=>true, 'qty'=>intval($new_qty));
	if($upgrade_info !== null) $ret['upgrade'] = $upgrade_info;
	return $ret;
}

// Remove a consumable from a location and refund to inventory (UI unequip)
function removeLocationConsumableRefund($conn, $realm_id, $location_id, $consumable_id){
	if(!isset($_SESSION['userData']['user_id'])) return array('error'=>'Not logged in');
	$user_id       = $_SESSION['userData']['user_id'];
	$consumable_id = intval($consumable_id);
	$rl_id = getRealmLocationID($conn, $realm_id, $location_id);
	if(!$rl_id) return array('error'=>'Location not found');
	$sql = "DELETE FROM realms_locations_consumables WHERE realm_location_id='".$rl_id."' AND consumable_id='".$consumable_id."' AND raid_id=0";
	if($conn->query($sql) === TRUE && $conn->affected_rows > 0){
		updateAmount($conn, $user_id, $consumable_id, 1);
		// FF removed mid-upgrade: restore remaining duration
		$upgrade_info = null;
		if($consumable_id == 5){
			$upg = $conn->query("SELECT duration, created_date FROM upgrades WHERE realm_id='".$realm_id."' AND location_id='".$location_id."'");
			if($upg && $upg->num_rows > 0){
				$upg_row    = $upg->fetch_assoc();
				$completion = strtotime('+'.$upg_row['duration'].' day', strtotime($upg_row['created_date']));
				if($completion > time()){
					$remaining_days = (int)ceil(($completion - time()) / 86400);
					$new_duration   = $upg_row['duration'] + $remaining_days;
					$conn->query("UPDATE upgrades SET duration='".$new_duration."' WHERE realm_id='".$realm_id."' AND location_id='".$location_id."'");
					// Re-query for accurate remaining seconds after update
					$upg2 = $conn->query("SELECT duration, created_date FROM upgrades WHERE realm_id='".$realm_id."' AND location_id='".$location_id."'");
					if($upg2 && $upg2->num_rows > 0){
						$row2 = $upg2->fetch_assoc();
						$comp2 = strtotime('+'.$row2['duration'].' day', strtotime($row2['created_date']));
						$upgrade_info = array('duration'=>intval($row2['duration']), 'remaining_seconds'=>max(0, $comp2 - time()));
					}
				}
			}
		}
		$new_qty = getCurrentAmount($conn, $user_id, $consumable_id);
		if($new_qty === "false") $new_qty = 1;
		$ret = array('success'=>true, 'qty'=>intval($new_qty));
		if($upgrade_info !== null) $ret['upgrade'] = $upgrade_info;
		return $ret;
	}
	return array('error'=>'Not equipped');
}

// Remove a single consumable from a location without refund (internal raid use)
function removeLocationConsumable($conn, $realm_id, $location_id, $consumable_id, $raid_id=0){
	$consumable_id = intval($consumable_id);
	$raid_id       = intval($raid_id);
	$rl_id = getRealmLocationID($conn, $realm_id, $location_id);
	if(!$rl_id) return;
	if($raid_id > 0){
		$conn->query("UPDATE realms_locations_consumables SET raid_id='".$raid_id."' WHERE realm_location_id='".$rl_id."' AND consumable_id='".$consumable_id."' AND raid_id=0");
	} else {
		$conn->query("DELETE FROM realms_locations_consumables WHERE realm_location_id='".$rl_id."' AND consumable_id='".$consumable_id."' AND raid_id=0");
	}
}

// Burn all consumables from a location when it takes real damage (no refund)
// If FF was equipped, restore the upgrade duration before burning
function burnLocationConsumables($conn, $realm_id, $location_id, $raid_id=0){
	$realm_id    = intval($realm_id);
	$location_id = intval($location_id);
	$raid_id     = intval($raid_id);
	$rl_id = getRealmLocationID($conn, $realm_id, $location_id);
	if(!$rl_id) return;
	// Check if FF is stocked — if so, un-accelerate the upgrade timer.
	// Restore duration to the target level and reset created_date so the full timer runs from now.
	$ff_check = $conn->query("SELECT id FROM realms_locations_consumables WHERE realm_location_id='".$rl_id."' AND consumable_id='5' AND raid_id=0");
	if($ff_check && $ff_check->num_rows > 0){
		$upg = $conn->query("SELECT id FROM upgrades WHERE realm_id='".$realm_id."' AND location_id='".$location_id."'");
		if($upg && $upg->num_rows > 0){
			$conn->query("UPDATE upgrades SET duration = level, created_date = NOW() WHERE realm_id='".$realm_id."' AND location_id='".$location_id."' AND level > 0");
		}
	}
	if($raid_id > 0){
		$conn->query("UPDATE realms_locations_consumables SET raid_id='".$raid_id."' WHERE realm_location_id='".$rl_id."' AND raid_id=0");
	} else {
		$conn->query("DELETE FROM realms_locations_consumables WHERE realm_location_id='".$rl_id."' AND raid_id=0");
	}
}

// Returns true if location has Double Rewards shield (consumable_id=6)
function hasDoubleRewardsShield($conn, $realm_id, $location_id){
	$rl_id = getRealmLocationID($conn, $realm_id, $location_id);
	if(!$rl_id) return false;
	$result = $conn->query("SELECT id FROM realms_locations_consumables WHERE realm_location_id='".$rl_id."' AND consumable_id='6' AND raid_id=0");
	return ($result && $result->num_rows > 0);
}

// Average success rate boost across locations of a type
// type 'defense' = locations 3,5,7 | type 'offense' = locations 1,2,4,6 (portal+offense)
function getLocationSuccessRateBoost($conn, $realm_id, $type){
	$realm_id     = intval($realm_id);
	$boost_map    = array(1=>4, 2=>3, 3=>2, 4=>1);
	$location_ids = ($type == 'defense') ? array(3,5,7) : array(1,2,4,6);
	$boosts = array();
	foreach($location_ids as $lid){
		$loc_boost = 0;
		$sql = "SELECT rlc.consumable_id
		        FROM realms_locations_consumables rlc
		        INNER JOIN realms_locations rl ON rl.id = rlc.realm_location_id
		        WHERE rl.realm_id='".$realm_id."' AND rl.location_id='".$lid."' AND rlc.consumable_id IN (1,2,3,4) AND rlc.raid_id=0";
		$result = $conn->query($sql);
		if($result && $result->num_rows > 0){
			while($row = $result->fetch_assoc()) $loc_boost += $boost_map[intval($row['consumable_id'])];
		}
		$boosts[] = min(10, $loc_boost);
	}
	if(empty($boosts)) return 0;
	return (int)ceil(array_sum($boosts) / count($boosts));
}

// Returns true if ALL locations of the given type have Random Reward (consumable_id=7)
function hasRandomReward($conn, $realm_id, $type){
	$realm_id     = intval($realm_id);
	$location_ids = ($type == 'defense') ? array(3,5,7) : array(1,2,4,6);
	foreach($location_ids as $lid){
		$rl_id = getRealmLocationID($conn, $realm_id, $lid);
		if(!$rl_id) return false;
		$result = $conn->query("SELECT id FROM realms_locations_consumables WHERE realm_location_id='".$rl_id."' AND consumable_id='7' AND raid_id=0");
		if(!$result || $result->num_rows == 0) return false;
	}
	return true;
}

// Consume Random Reward from all locations of a type (no refund — game mechanic)
function consumeRandomRewards($conn, $realm_id, $type, $raid_id=0){
	$realm_id     = intval($realm_id);
	$raid_id      = intval($raid_id);
	$location_ids = ($type == 'defense') ? array(3,5,7) : array(1,2,4,6);
	foreach($location_ids as $lid){
		$rl_id = getRealmLocationID($conn, $realm_id, $lid);
		if(!$rl_id) continue;
		if($raid_id > 0){
			$conn->query("UPDATE realms_locations_consumables SET raid_id='".$raid_id."' WHERE realm_location_id='".$rl_id."' AND consumable_id='7' AND raid_id=0");
		} else {
			$conn->query("DELETE FROM realms_locations_consumables WHERE realm_location_id='".$rl_id."' AND consumable_id='7' AND raid_id=0");
		}
	}
}

// Select a random location ID from all 7 locations
function selectRandomLocationIDAny(){
	return array_rand(array(1=>1, 2=>2, 3=>3, 4=>4, 5=>5, 6=>6, 7=>7), 1);
}

// Get array of consumable_ids used for a specific raid
function retreatRaid($conn, $raid_id){
	if(!isset($_SESSION['userData']['user_id'])) return array('error'=>'Not logged in');
	$user_id = $_SESSION['userData']['user_id'];
	$raid_id = intval($raid_id);
	// Verify user owns the offense realm and raid is still pending
	$check = $conn->query("SELECT r.id, r.duration, r.created_date FROM raids r INNER JOIN realms rl ON rl.id = r.offense_id WHERE r.id='".$raid_id."' AND r.outcome='0' AND rl.user_id='".$user_id."'");
	if(!$check || $check->num_rows == 0) return array('error'=>'Not authorized or raid not pending');
	$raid = $check->fetch_assoc();
	// Verify raid has not yet completed
	$completion = strtotime('+'.$raid['duration'].' day', strtotime($raid['created_date']));
	if($completion <= time()) return array('error'=>'Raid has already completed');
	// Refund raid consumables to attacker inventory
	$cons = $conn->query("SELECT consumable_id FROM raids_consumables WHERE raid_id='".$raid_id."'");
	if($cons){
		while($con = $cons->fetch_assoc()){
			updateAmount($conn, $user_id, intval($con['consumable_id']), 1);
		}
	}
	// Get realm/user info for webhook before deleting
	$retreat_info_res = $conn->query("SELECT off_r.name AS off_name, off_u.username AS off_username, off_u.discord_id AS off_discord, off_u.avatar AS off_avatar, def_r.name AS def_name, def_r.theme_id AS def_theme_id, def_u.username AS def_username, def_u.discord_id AS def_discord FROM raids ra INNER JOIN realms off_r ON off_r.id = ra.offense_id INNER JOIN users off_u ON off_u.id = off_r.user_id INNER JOIN realms def_r ON def_r.id = ra.defense_id INNER JOIN users def_u ON def_u.id = def_r.user_id WHERE ra.id='".$raid_id."'");
	$ri = $retreat_info_res ? $retreat_info_res->fetch_assoc() : null;
	$conn->query("DELETE FROM raids_consumables WHERE raid_id='".$raid_id."'");
	$conn->query("DELETE FROM raids WHERE id='".$raid_id."'");
	if($ri){
		$off_mention    = $ri['off_discord'] ? "<@".$ri['off_discord'].">" : $ri['off_username'];
		$def_mention    = $ri['def_discord'] ? "<@".$ri['def_discord'].">" : $ri['def_username'];
		$off_avatar_url = ($ri['off_discord'] && $ri['off_avatar']) ? "https://cdn.discordapp.com/avatars/".$ri['off_discord']."/".$ri['off_avatar'].".png" : "";
		$def_image_url  = $ri['def_theme_id'] ? "https://skulliance.io/staking/images/themes/".$ri['def_theme_id'].".jpg" : "";
		$retreat_desc   = $off_mention." has called off the attack.\n\n";
		$retreat_desc  .= "🏳️ **Retreating:** ".$ri['off_username']." — ".$ri['off_name']."\n";
		$retreat_desc  .= "🛡️ **Spared:** ".$def_mention." — ".$ri['def_name']."\n";
		$retreat_desc  .= "♻️ Consumables have been refunded.";
		$author = array("name" => $ri['off_username']." · ".$ri['off_name'], "icon_url" => $off_avatar_url, "url" => "https://skulliance.io/staking/profile.php?username=".urlencode($ri['off_username']));
		discordmsg("🏳️ Raid Retreated", $retreat_desc, $def_image_url, "https://skulliance.io/staking/realms.php", "raids", $off_avatar_url, "888888", $author);
	}
	return array('success'=>true);
}

function getRaidConsumablesList($conn, $raid_id){
	$raid_id = intval($raid_id);
	$result  = $conn->query("SELECT consumable_id FROM raids_consumables WHERE raid_id='".$raid_id."'");
	$list = array();
	if($result && $result->num_rows > 0){
		while($row = $result->fetch_assoc()) $list[] = intval($row['consumable_id']);
	}
	return $list;
}

// Get consumables burned/shielded on locations during a specific raid (raid_id > 0)
function getRaidBurnedLocationConsumables($conn, $raid_id){
	$raid_id = intval($raid_id);
	$sql = "SELECT rl.location_id, rlc.consumable_id
	        FROM realms_locations_consumables rlc
	        INNER JOIN realms_locations rl ON rl.id = rlc.realm_location_id
	        WHERE rlc.raid_id='".$raid_id."'";
	$result = $conn->query($sql);
	$burned = array();
	if($result && $result->num_rows > 0){
		while($row = $result->fetch_assoc()){
			$burned[$row['location_id']][] = intval($row['consumable_id']);
		}
	}
	return $burned;
}

// Get total success rate boost from raid consumables (items 1-4, cap 10)
function getRaidSuccessRateBoost($conn, $raid_id){
	$raid_id   = intval($raid_id);
	$boost_map = array(1=>4, 2=>3, 3=>2, 4=>1);
	$result    = $conn->query("SELECT consumable_id FROM raids_consumables WHERE raid_id='".$raid_id."' AND consumable_id IN (1,2,3,4)");
	$boost = 0;
	if($result && $result->num_rows > 0){
		while($row = $result->fetch_assoc()) $boost += $boost_map[intval($row['consumable_id'])];
	}
	return min(10, $boost);
}

function getRealms($conn, $sort, $group){
	if(isset($_SESSION['userData']['user_id'])){
		$realm = getRealmInfo($conn);
		$offense_id = $realm["id"];
		$offense_faction = $realm["project_id"];
		$sql = "SELECT DISTINCT realms.id AS realm_id, realms.name AS realm_name, theme_id, project_id, projects.name AS project_name, currency, users.id AS user_id, users.username AS username, users.avatar AS avatar, users.discord_id AS discord_id, realms.active AS active 
			    FROM realms INNER JOIN users ON users.id = realms.user_id INNER JOIN projects ON projects.id = realms.project_id WHERE realms.active = '1' ORDER BY rand()";
				/* WHERE users.id != '".$_SESSION['userData']['user_id']."' AND raids.offense_id != '".$offense_id."' AND raids.outcome != '0'"; */
		$result = $conn->query($sql);
	
		$last_realm_id = 0;
		$balances_display = "";
		$output = array();
		// Pre-compute attacker fixed values (constant across all raid targets)
		$attacker_loc_boost  = getLocationSuccessRateBoost($conn, $offense_id, 'offense');
		$amounts             = getCurrentAmounts($conn);
		$inv_boost_map       = array(1=>4, 2=>3, 3=>2, 4=>1);
		$all_avail_raid_boost = 0;
		foreach($inv_boost_map as $_cid => $_pts){
			if(isset($amounts[$_cid]) && intval($amounts[$_cid]['amount']) > 0) $all_avail_raid_boost += $_pts;
		}
		$all_avail_raid_boost = min(10, $all_avail_raid_boost);
		$all_avail_ff = (isset($amounts[5]) && intval($amounts[5]['amount']) > 0);
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$raw_offense = calculateRawRaidOffense($conn, $offense_id);
				$raw_defense = calculateRawRaidDefense($conn, $row['realm_id']);
				$raw_defense_offense = calculateRawRaidOffense($conn, $row['realm_id']);
				$offense = calculateRaidOffense($conn, $offense_id);
				$defense = calculateRaidDefense($conn, $row['realm_id']);
				$total = $defense + $offense;
				$percentage = (100/$total);
				$defense_threshold   = $percentage * $defense;
				$offense_threshold   = $percentage * $offense;
				$duration            = max(2, ceil($defense/$offense));
				$defender_loc_boost  = getLocationSuccessRateBoost($conn, $row['realm_id'], 'defense');
				$init_adj_win = $offense_threshold - $defender_loc_boost + $attacker_loc_boost + $all_avail_raid_boost;
				if($init_adj_win < 1)  $init_adj_win = 1;
				if($init_adj_win > 99) $init_adj_win = 99;
				$init_duration = $all_avail_ff ? max(1, (int)ceil($duration / 2)) : $duration;
				$balances = getRealmBalances($conn, $row['user_id']);
				
				$key = "";
				if($sort == "random"){
					$key = $row['realm_id'];
				}else if($sort == "weakness" || $sort == "strength"){
					$key = $raw_defense.".".$raw_defense_offense.$row['realm_id'];
				}else if($sort == "wealth"){
					$key = array_sum($balances).".".$row['realm_id'];
				}
				if($duration < 2) $duration = 2;
				$output[$key] = "";
				$output[$key] .= "<div class='raid-target-card'>";

				// Header: theme image + key info
				$output[$key] .= "<div class='rtc-header'>";
				$output[$key] .= "<img class='rtc-theme' src='images/themes/".$row["theme_id"].".jpg' loading='lazy' onerror='this.src=\"/staking/icons/skull.png\"'>";
				$output[$key] .= "<div class='rtc-info'>";
				$output[$key] .= "<div class='rtc-title-row'>";
				$output[$key] .= "<span class='rtc-realm-name'>".ucfirst($row['realm_name'])."</span>";
				$output[$key] .= "<a href='/staking/profile.php?username=".urlencode($row["username"])."' class='rtc-user'>";
				if($row["avatar"] != "") $output[$key] .= "<img src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='rtc-avatar' loading='lazy' onerror='this.src=\"/staking/icons/skull.png\"'>";
				$output[$key] .= "<span>".$row["username"]."</span></a>";
				$output[$key] .= "</div>";
				if($row["currency"] != "") $output[$key] .= "<div class='rtc-faction'><img src='/staking/icons/".strtolower($row["currency"]).".png' class='rtc-faction-icon' onerror='this.src=\"/staking/icons/skull.png\"'><span>".$row["project_name"]."</span></div>";
				$output[$key] .= "<div class='rtc-stats'>";
				$output[$key] .= "<div class='rtc-stat'><span class='rtc-stat-label'>Your Offense</span><span class='rtc-stat-value'>Lv ".$offense."</span></div>";
				$output[$key] .= "<div class='rtc-stat'><span class='rtc-stat-label'>Their Defense</span><span class='rtc-stat-value'>Lv ".$defense."</span></div>";
				$output[$key] .= "<div class='rtc-stat'><span class='rtc-stat-label'>Duration</span><span class='rtc-stat-value' id='raid-duration-".$row['realm_id']."' data-base-duration='".$duration."'>".$init_duration." ".($init_duration==1?"day":"days")."</span></div>";
				$output[$key] .= "<div class='rtc-stat rtc-stat-success'><span class='rtc-stat-label'>Success</span><span class='rtc-stat-value' id='raid-success-".$row['realm_id']."' data-offense-pct='".round($offense_threshold,4)."' data-defender-boost='".$defender_loc_boost."' data-attacker-loc-boost='".$attacker_loc_boost."'>".round($init_adj_win)."%</span></div>";
				$output[$key] .= "</div></div></div>";

				// Locations grouped by category with per-category avg success boost
				$_locs = getRealmLocationsWithShields($conn, $row['realm_id']);
				if(!empty($_locs)){
					// Group by location ID — matches getLocationSuccessRateBoost logic exactly
					// defense = 3,5,7 | offense = 1(portal),2,4,6
					$_defense_ids = array(3,5,7);
					$_by_type = array();
					foreach($_locs as $_loc){
						$_cat = in_array($_loc['location_id'], $_defense_ids) ? 'defense' : 'offense';
						$_by_type[$_cat][] = $_loc;
					}
					// Render defense first, then offense
					$_type_order = array('defense', 'offense');
					$output[$key] .= "<div class='rtc-locations'>";
					foreach($_type_order as $_type){
						if(empty($_by_type[$_type])) continue;
						$_group = $_by_type[$_type];
						// Compute average boost for this category (rounded)
						$_total_boost = array_sum(array_column($_group, 'success_boost'));
						$_avg_boost   = ($_total_boost > 0) ? (int)ceil($_total_boost / count($_group)) : 0;
						$_cat_label   = ucfirst($_type);
						$output[$key] .= "<div class='rtc-loc-category'>";
						$output[$key] .= "<div class='rtc-loc-cat-header'>";
						$output[$key] .= "<span class='rtc-loc-cat-name ".'rtc-cat-'.$_type."'>".$_cat_label."</span>";
						if($_avg_boost > 0) $output[$key] .= "<span class='rtc-loc-cat-boost' title='Avg success chance boost across ".$_cat_label." locations'>+".$_avg_boost."% avg</span>";
						$output[$key] .= "</div>";
						$output[$key] .= "<div class='rtc-loc-pills'>";
						foreach($_group as $_loc){
							$_sc = $_loc['has_shield'] ? ' rtc-loc-shielded' : '';
							$output[$key] .= "<div class='rtc-loc-pill".$_sc."'>";
							$output[$key] .= "<span class='rtc-loc-level'>".$_loc['level']."</span>";
							$output[$key] .= "<span class='rtc-loc-name'>".ucfirst($_loc['name'])."</span>";
							if($_loc['has_shield']) $output[$key] .= "<span class='rtc-shield-badge' title='Shielded'>&#x1F6E1;</span>";
							$output[$key] .= "</div>";
						}
						$output[$key] .= "</div>"; // rtc-loc-pills
						$output[$key] .= "</div>"; // rtc-loc-category
					}
					$output[$key] .= "</div>"; // rtc-locations
				}

				// Wealth
				$output[$key] .= "<div class='rtc-wealth'>";
				$output[$key] .= "<span class='rtc-total-pts'>".number_format(array_sum($balances))." pts</span>";
				$output[$key] .= "<div class='rtc-balances'>";
				foreach(array_slice($balances, 0, 7, true) as $_curr => $_bal){
					$output[$key] .= "<span class='rtc-balance-pill'>".number_format($_bal)." ".$_curr."</span>";
				}
				$output[$key] .= "</div></div>";

				// Action row
				$unset = false;
				$output[$key] .= "<div class='rtc-action'>";
				if(checkRealmRaidStatus($conn, $row["realm_id"])){
					$value = "START RAID";
					$raiding = false;
					if(in_array($offense_id, getRecentRealmsRaiding($conn, $row["realm_id"]))){
						$raiding = true;
						$value = "GET REVENGE";
					}
					if($offense_id == $row["realm_id"] || $offense_faction == $row["project_id"]){
						if(!$raiding){
							if($offense_id == $row["realm_id"]){ $value = "SELF DESTRUCT"; }
							else { $value = "FRIENDLY FIRE"; }
						}else{
							if($offense_id == $row["realm_id"]){ $value = "SELF ANNIHILATE"; }
							else { $value = "PUNISH TRAITOR"; }
						}
					}
					if(($raw_defense == 0 && $raw_offense != 0) && !$raiding){
						$output[$key] .= "<span class='rtc-status-msg'>Establishing Realm</span>";
						$unset = true;
					}else if((($offense-$defense) > 3) && !$raiding){
						$level_range = (($offense-$defense)-3);
						$output[$key] .= "<span class='rtc-status-msg'>".$level_range." ".($level_range==1?"Level":"Levels")." Out of Range</span>";
						$unset = true;
					}else if(!in_array($row['realm_id'], getRecentRaidedRealms($conn)) || $raiding){
						if(checkMaxRaids($conn, $offense_id)){
							$_saved_config = isset($_SESSION['raid_consumable_config']) && !empty($_SESSION['raid_consumable_config']) ? $_SESSION['raid_consumable_config'] : null;
							$_has_saved    = !empty($_saved_config);
							$_cb_mode      = $_has_saved ? 'saved' : 'default';
							$_cb_ids       = $_has_saved ? implode(',', array_map('intval', $_saved_config)) : '';
							$output[$key] .= "<div id='raid-con-row-".$row['realm_id']."' class='raid-con-row'>";
							$_cb_label = $_has_saved ? 'Saved Config' : 'Default Config';
							$output[$key] .= "<span id='raid-all-items-".$row['realm_id']."' class='raid-config-label' data-mode='".$_cb_mode."' data-saved-ids='".$_cb_ids."'>".$_cb_label."</span>";
							$output[$key] .= "<span class='raid-gear-icon' onclick='openRaidConsumablesModal(".$row['realm_id'].", ".$duration.")' title='Customize consumables'>&#9881;</span>";
							$output[$key] .= "</div>";
							$output[$key] .= "<input type='button' id='raid-btn-".$row['realm_id']."' class='raid-button' value='".$value."' onclick='startRaid(this, ".$row['realm_id'].", ".$duration.");'>";
						}else{
							$output[$key] .= "<span class='rtc-status-msg'>Max Raids Reached</span>";
						}
					}else{
						$output[$key] .= "<span class='rtc-status-msg'>Recovering from Raid</span>";
						$unset = true;
					}
				}else{
					$output[$key] .= "<span class='rtc-status-msg'>Raid in Progress</span>";
					$unset = true;
				}
				$output[$key] .= "</div>"; // rtc-action
				$output[$key] .= "</div>"; // raid-target-card
				if($unset && $group == "Eligible"){
					unset($output[$key]);
				}
			} // end while fetch_assoc
			if($sort == "random"){
				// Do nothing
			}else if($sort == "weakness"){
				ksort($output);
			}else if($sort == "strength"){
				ksort($output);
				$output = array_reverse($output);
			}else if($sort == "wealth"){
				ksort($output);
				$output = array_reverse($output);
			}
			
			if(!empty($output)){
				echo "<div class='raid-target-list'>";
				foreach($output AS $key => $val){
					echo $val;
				}
				echo "</div>";
			}else{
				echo "<p>There are no realms currently available for you to raid.<br><br>Please check back later as your location levels and those of your potential opponents can change as raids are completed.<br><br><img src='/staking/images/disappointed.gif'/></p>";
			}
			
		}else{
			
		}
	}
}

// Check if realm is already raiding another realm
function checkRealmRaidStatus($conn, $realm_id){
	if(isset($_SESSION['userData']['user_id'])){
		$offense_id = getRealmID($conn);
		$sql = "SELECT id FROM raids WHERE offense_id = '".$offense_id."' AND defense_id = '".$realm_id."' AND outcome ='0'";
		$result = $conn->query($sql);
	
		if ($result->num_rows > 0) {
			return false;
		}else{
			return true;
		}
	}
}

// Get recent successfully raided realms limited by current portal level
function getRecentRaidedRealms($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$offense_id = getRealmID($conn);
		$portal_level = getRealmLocationLevel($conn, $offense_id, 1);
		if($portal_level == 0){
			$portal_level = 1;
		}
		$sql = "SELECT defense_id, outcome FROM raids WHERE offense_id = '".$offense_id."' AND outcome != '0' ORDER BY id DESC LIMIT ".$portal_level;
		$result = $conn->query($sql);
		
		$recent_realms = array();
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				// Check successful outcome
				if($row['outcome'] == 1){
					$recent_realms[$row['defense_id']] = $row['defense_id'];
				}
			}
		}
		return $recent_realms;
	}
}

// Get recent realms raiding or raided limited by current portal level
function getRecentRealmsRaiding($conn, $realm_id){
	$portal_level = getRealmLocationLevel($conn, $realm_id, 1);
	if($portal_level == 0){
		$portal_level = 1;
	}
	$sql = "SELECT defense_id FROM raids WHERE offense_id = '".$realm_id."' ORDER BY id DESC LIMIT ".$portal_level;
	$result = $conn->query($sql);
	
	$recent_realms = array();
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$recent_realms[$row['defense_id']] = $row['defense_id'];
		}
	}
	return $recent_realms;
}

// Calculate Raw Raid Defense to better sort Realm results for strength and weakness
function calculateRawRaidDefense($conn, $realm_id){
	$sql = "SELECT locations.name AS name, location_id, level FROM realms_locations INNER JOIN locations ON locations.id = realms_locations.location_id WHERE realm_id = '".$realm_id."'";
	$result = $conn->query($sql);
	
	$defense = 0;
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			if($row['location_id'] == 3){
				$defense += $row["level"];
			}
			if($row['location_id'] == 5){
				$defense += $row["level"];
			}
			if($row['location_id'] == 7){
				$defense += $row["level"];
			}
		}
	}else{

	}
	return $defense;
}

// Calculate Raw Raid Offense to identify newly established or heavily damaged realms
function calculateRawRaidOffense($conn, $realm_id){
	$sql = "SELECT locations.name AS name, location_id, level FROM realms_locations INNER JOIN locations ON locations.id = realms_locations.location_id WHERE realm_id = '".$realm_id."'";
	$result = $conn->query($sql);
	
	$offense = 0;
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			if($row['location_id'] == 2){
				$offense += $row["level"];
			}
			if($row['location_id'] == 4){
				$offense += $row["level"];
			}
			if($row['location_id'] == 6){
				$offense += $row["level"];
			}
		}
	}else{

	}
	return $offense;
}

function calculateRaidDefense($conn, $realm_id){
	$sql = "SELECT locations.name AS name, location_id, level FROM realms_locations INNER JOIN locations ON locations.id = realms_locations.location_id WHERE realm_id = '".$realm_id."'";
	$result = $conn->query($sql);

	$defense = 0;
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			if($row['location_id'] == 3){
				$defense += $row["level"];
			}
			if($row['location_id'] == 5){
				$defense += $row["level"];
			}
			if($row['location_id'] == 7){
				$defense += $row["level"];
			}
		}
		// Tower garrison as 4th input (0-10 scale)
		$tower_score = getTowerScore($conn, $realm_id);
		$defense = ceil(($defense + $tower_score) / 4);
		if($defense == 0){
			$defense = 1;
		}
	}else{

	}
	return $defense;
}

function calculateRaidOffense($conn, $realm_id){
	$sql = "SELECT locations.name AS name, location_id, level FROM realms_locations INNER JOIN locations ON locations.id = realms_locations.location_id WHERE realm_id = '".$realm_id."'";
	$result = $conn->query($sql);

	$offense = 0;
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			if($row['location_id'] == 2){
				$offense += $row["level"];
			}
			if($row['location_id'] == 4){
				$offense += $row["level"];
			}
			if($row['location_id'] == 6){
				$offense += $row["level"];
			}
		}
		// Barracks training score as 4th input (0-10 scale)
		$barracks_score = getBarracksScore($conn, $realm_id);
		$offense = ceil(($offense + $barracks_score) / 4);
		if($offense == 0){
			$offense = 1;
		}
	}else{

	}
	return $offense;
}

// Get realm balances
function getRealmBalances($conn, $user_id){
	$sql = "SELECT balance, project_id, projects.currency AS currency FROM balances INNER JOIN projects ON balances.project_id = projects.id AND user_id = '".$user_id."' ORDER BY balance DESC";
	$result = $conn->query($sql);

	$balances = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
        $balances[$row["currency"]] = $row["balance"];
	  }
	} else {
	  //echo "0 results";
	}
	return $balances;
}

// Get location balances
function getLocationBalances($conn, $user_id){
	$sql = "SELECT balance, project_id, projects.currency AS currency FROM balances INNER JOIN projects ON balances.project_id = projects.id AND user_id = '".$user_id."' WHERE projects.id != '15' ORDER BY balance DESC";
	$result = $conn->query($sql);

	$balances = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    $balances[$row["project_id"]] = array();
		$balances[$row["project_id"]]["currency"] = $row["currency"];
        $balances[$row["project_id"]]["balance"] = $row["balance"];
	  }
	} else {
	  //echo "0 results";
	}
	return $balances;
}

function startRaid($conn, $defense_id, $duration, $consumables = array()){
	if(isset($_SESSION['userData']['user_id'])){
		$user_id    = $_SESSION['userData']['user_id'];
		$offense_id = getRealmID($conn);
		$defense_id = intval($defense_id);
		$duration   = max(2, intval($duration));

		// Resolve which consumable IDs to apply
		$consumable_ids = array();
		if($consumables === 'all'){
			$inventory = getCurrentAmounts($conn);
			foreach($inventory as $cid => $data){
				if($data['amount'] > 0) $consumable_ids[] = intval($cid);
			}
		} else if(is_array($consumables) && !empty($consumables)){
			foreach($consumables as $cid) $consumable_ids[] = intval($cid);
		}

		// Fast Forward (id=5): apply at inception, never stored in raids_consumables
		if(in_array(5, $consumable_ids)){
			$ff_qty = getCurrentAmount($conn, $user_id, 5);
			if($ff_qty !== "false" && $ff_qty > 0){
				$duration = max(1, (int)ceil($duration / 2));
				updateAmount($conn, $user_id, 5, -1);
			}
		}

		$sql = "INSERT INTO raids (offense_id, defense_id, duration)
		VALUES ('".$offense_id."', '".$defense_id."', '".$duration."')";

		if ($conn->query($sql) === TRUE) {
			$raid_id = $conn->insert_id;
			// Store remaining consumables (not FF) and deduct from inventory
			foreach($consumable_ids as $cid){
				if($cid == 5) continue; // FF already handled above
				$qty = getCurrentAmount($conn, $user_id, $cid);
				if($qty !== "false" && $qty > 0){
					updateAmount($conn, $user_id, $cid, -1);
					$conn->query("INSERT INTO raids_consumables (raid_id, consumable_id) VALUES ('".$raid_id."', '".$cid."')");
				}
			}
			$off_name     = getRealmName($conn);
			$off_username = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
			$off_discord  = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
			$off_avatar   = isset($_SESSION['userData']['avatar'])     ? $_SESSION['userData']['avatar']     : '';
			$off_mention  = $off_discord ? "<@".$off_discord.">" : $off_username;
			$def_info_res = $conn->query("SELECT r.name, r.theme_id, u.username, u.discord_id FROM realms r INNER JOIN users u ON u.id = r.user_id WHERE r.id='".$defense_id."'");
			$def_info     = $def_info_res ? $def_info_res->fetch_assoc() : null;
			$def_name     = $def_info ? $def_info['name']       : 'Unknown Realm';
			$def_username = $def_info ? $def_info['username']   : 'Unknown';
			$def_discord  = $def_info ? $def_info['discord_id'] : '';
			$def_theme_id = $def_info ? $def_info['theme_id']   : '';
			$def_mention  = $def_discord ? "<@".$def_discord.">" : $def_username;
			// Consumables used
			$con_names_res = $conn->query("SELECT id, name FROM consumables");
			$con_names = array();
			if($con_names_res) while($r = $con_names_res->fetch_assoc()) $con_names[$r['id']] = $r['name'];
			$success_boost_map = array(1 => 4, 2 => 3, 3 => 2, 4 => 1);
			$total_success_boost = 0;
			$items_used = array();
			foreach($consumable_ids as $cid){
				if(isset($success_boost_map[$cid])) $total_success_boost += $success_boost_map[$cid];
				elseif(isset($con_names[$cid])) $items_used[] = $con_names[$cid];
			}
			if($total_success_boost > 0) array_unshift($items_used, "+".$total_success_boost."% Success");
			$items_line = !empty($items_used) ? "\n🧪 **Items:** ".implode(" · ", $items_used) : "";
			// Build notification
			$dur_res = $conn->query("SELECT duration FROM raids WHERE id='".$raid_id."'");
			$dur_row = $dur_res ? $dur_res->fetch_assoc() : null;
			$display_duration = $dur_row ? $dur_row['duration'] : $duration;
			$off_avatar_url = ($off_discord && $off_avatar) ? "https://cdn.discordapp.com/avatars/".$off_discord."/".$off_avatar.".png" : "";
			$def_image_url  = $def_theme_id ? "https://skulliance.io/staking/images/themes/".$def_theme_id.".jpg" : "";
			$raid_author = array("name" => $off_username." · ".$off_name, "icon_url" => $off_avatar_url, "url" => "https://skulliance.io/staking/profile.php?username=".urlencode($off_username));
			$raid_desc  = $off_mention." has declared war!\n\n";
			$raid_desc .= "⚔️ **Attacker:** ".$off_username." — ".$off_name."\n";
			$raid_desc .= "🛡️ **Target:** ".$def_mention." — ".$def_name."\n";
			$raid_desc .= "⏱️ **Duration:** ".$display_duration." day(s)";
			$raid_desc .= $items_line;
			discordmsg("⚔️ Raid Launched", $raid_desc, $def_image_url, "https://skulliance.io/staking/realms.php", "raids", $off_avatar_url, "FF6B35", $raid_author);
			echo "<strong>Raid Started</strong>";
			return $raid_id;
		} else {
			echo "Error: " . $conn->error;
		}
	}
	return 0;
}

function checkMaxRaids($conn, $realm_id){
	$sql = "SELECT location_id, level FROM realms_locations INNER JOIN locations ON locations.id = realms_locations.location_id WHERE realm_id = '".$realm_id."' AND location_id = '1'";
	$result = $conn->query($sql);
	
	$max_raids = 1;
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			if($row['location_id'] == 1){
				if($row["level"] != 0){
					$max_raids = $row["level"];
				}
			}
		}
	}else{

	}
	
	$raid_count = 0;
	$sql = "SELECT COUNT(id) AS raid_count FROM raids WHERE offense_id = '".$realm_id."' AND outcome = '0'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$raid_count = $row["raid_count"];
		}
	}else{
		
	}
	
	if($raid_count < $max_raids){
		return true;
	}else{
		return false;
	}
}

function getRaids($conn, $type, $status="pending", $history=false){
	if(isset($_SESSION['userData']['user_id'])){
		$realm_id = getRealmID($conn);
		$id1 = "";
		$id2 = "";
		$results1 = "";
		$results2 = "";
		if($type == "outgoing"){
			$id1 = "defense_id";
			$id2 = "offense_id";
			$results1 = "Your";
			$results2 = "Their";
		}else if($type == "incoming"){
			$id1 = "offense_id";
			$id2 = "defense_id";
			$results1 = "Their";
			$results2 = "Your";
		}
		if($status == "pending"){
			$outcome_operator = " = '0'";
		}else if($status == "completed"){
			$outcome_operator = " != '0'";
		}
		if(!$history){
			$limit = "LIMIT 10";
		}else{
			$limit = "";
		}
		$sql = "SELECT raids.id AS raid_id, outcome, realms.name AS realm_name, theme_id, raids.duration AS duration, raids.created_date AS created_date, username, discord_id, avatar 
			    FROM raids INNER JOIN realms ON realms.id = raids.".$id1." INNER JOIN users ON users.id = realms.user_id WHERE ".$id2." = '".$realm_id."' AND outcome ".$outcome_operator." ORDER BY raids.id DESC ".$limit;
		$result = $conn->query($sql);
		
		// Handle Toggle Sessions
		$arrow = "down";
		$display = "block";
		if(isset($_SESSION['userData'][$type."-".$status."-raids"])){
			if($_SESSION['userData'][$type."-".$status."-raids"] == "show"){
				$arrow = "down";
				$display = "block";
			}else if($_SESSION['userData'][$type."-".$status."-raids"] == "hide"){
				$arrow = "up";
				$display = "none";
			}
		}
		
		$final_output = "";
		if ($result->num_rows > 0) {
			// output data of each row
			$final_output .= "<div class='rc-section-title' onclick='toggleRaids(this.querySelector(\".raid-arrow-icon\"), \"" . $type . "\", \"" . $status . "\")'>"
				. "<img class='raid-arrow-icon' id='" . $arrow . "' src='icons/" . $arrow . ".png'>"
				. "<span class='rc-section-label'>" . strtoupper($type) . " " . strtoupper($status) . "</span>"
				. "</div>";
			$final_output .= '<div class="content raids" id="'.$type."-".$status.'-raids-container" style="display:'.$display.'">';
			$status = "";
			$final_output .= "<div class='rc-list'>";
			$rows = array();
			while($row = $result->fetch_assoc()) {
				$date = strtotime('+'.$row["duration"].' day', strtotime($row["created_date"]));
				$remaining = $date - time();
				$days_remaining = floor(($remaining / 86400));
				$hours_remaining = floor(($remaining % 86400) / 3600);
				$minutes_remaining = floor(($remaining % 3600) / 60);
				if($date > time()){
					$time_message = "<span class='countdown' data-deadline='".$date."'>".$days_remaining."d ".$hours_remaining."h ".$minutes_remaining."m 0s</span>";
					$status = "In Progress";

					// Get raid faction realm ID
					$defense_id = getRaidRealmID($conn, $row['raid_id'], "defense");
					$offense_id = getRaidRealmID($conn, $row['raid_id'], "offense");
	
					// Calculate faction score based on locations
					$defense = calculateRaidDefense($conn, $defense_id);
					$offense = calculateRaidOffense($conn, $offense_id);
	
					// Total scores and calculate percentage
					$total = $defense + $offense;
					$percentage = (100/$total);
	
					// Calculate adjusted win chances including consumable and location boosts
					$defense_threshold  = $percentage * $defense;
					$defender_boost     = getLocationSuccessRateBoost($conn, $defense_id, 'defense');
					$attacker_loc_boost = getLocationSuccessRateBoost($conn, $offense_id, 'offense');
					$raid_boost         = getRaidSuccessRateBoost($conn, $row['raid_id']);
					$attacker_boost     = $attacker_loc_boost + $raid_boost;
					$adj_threshold      = $defense_threshold + $defender_boost - $attacker_boost;
					if($adj_threshold < 1)  $adj_threshold = 1;
					if($adj_threshold > 99) $adj_threshold = 99;
					$defense_results = round($adj_threshold, 2)."%";
					$offense_results = round(100 - $adj_threshold, 2)."%";
					// Build offense status tags from raid consumables
					$raid_cons = getRaidConsumablesList($conn, $row['raid_id']);
					$_boost_map = array(1=>4, 2=>3, 3=>2, 4=>1);
					$_off_s_boost = 0; $_off_tags = array();
					foreach($raid_cons as $cid){
						if(isset($_boost_map[$cid])) $_off_s_boost += $_boost_map[$cid];
						if($cid == 5) $_off_tags[] = 'Fast Forward';
						if($cid == 6) $_off_tags[] = 'Double Rewards';
						if($cid == 7) $_off_tags[] = 'Random Reward';
					}
					$_off_s_boost = min(10, $_off_s_boost);
					// FF is burned at inception and never stored in raids_consumables -- infer from duration
					$_expected_duration = max(2, (int)ceil($defense/$offense));
					if(intval($row['duration']) < $_expected_duration) array_unshift($_off_tags, 'Fast Forward');
					if($_off_s_boost > 0) array_unshift($_off_tags, '+'.$_off_s_boost.'% Success');
					if(!empty($_off_tags)){
						$offense_results .= "<div class='loc-status-labels' style='margin-top:4px;'>";
						foreach($_off_tags as $_t) $offense_results .= "<span class='loc-status-tag'>".$_t."</span>";
						$offense_results .= "</div>";
					}
					// Build defense status tags from defender's location boost
					$_def_tags = array();
					if($defender_boost > 0) $_def_tags[] = '+'.$defender_boost.'% Defense';
					if(!empty($_def_tags)){
						$defense_results .= "<div class='loc-status-labels' style='margin-top:4px;'>";
						foreach($_def_tags as $_t) $defense_results .= "<span class='loc-status-tag'>".$_t."</span>";
						$defense_results .= "</div>";
					}
				}else{
					$time_message = "0d 0h 0m 0s";
					$status = "Completed";
					if($row["outcome"] == 0){
						$outcome = endRaid($conn, $row['raid_id']);
					}else{
						$outcome = $row["outcome"];
					}
					$_raid_cons_comp = getRaidConsumablesList($conn, $row['raid_id']);
					$_has_dr  = in_array(6, $_raid_cons_comp);
					$_has_rr  = in_array(7, $_raid_cons_comp);
					// Offense Success
					if($outcome == 1){
						$offense_results = "<strong style='color:#00c8a0'>Success</strong><br>";
						$offense_results .= "<br>".getRaidProjectBalanceAmount($conn, $row['raid_id'], "offense");
						$_off_comp_tags = array();
						if($_has_dr) $_off_comp_tags[] = 'Double Rewards (1000 cap)';
						if($_has_rr) $_off_comp_tags[] = 'Random Reward';
						if(!empty($_off_comp_tags)){
							$offense_results .= "<div class='loc-status-labels' style='margin-top:4px;'>";
							foreach($_off_comp_tags as $_t) $offense_results .= "<span class='loc-status-tag'>".$_t."</span>";
							$offense_results .= "</div>";
						}
						$defense_results = "<strong style='color:#ff5c5c'>Failure</strong><br>";
						$defense_results .= "<br>".getRaidProjectBalanceAmount($conn, $row['raid_id'], "defense");
						$defense_results .= getRaidLocationLevelAmount($conn, $row['raid_id'], "defense");
					}
					// Defense Success
					else if($outcome == 2){
						$offense_results = "<strong style='color:#ff5c5c'>Failure</strong><br>";
						$offense_results .= getRaidLocationLevelAmount($conn, $row['raid_id'], "offense");
						$defense_results = "<strong style='color:#00c8a0'>Success</strong><br>";
						$defense_results .= getRaidLocationLevelAmount($conn, $row['raid_id'], "defense");
					}
				}
				if($status == "Completed"){
					$decimal = $row["created_date"].$row["raid_id"];
				}else{
					$decimal = $days_remaining.".".(($hours_remaining<10)?"0".$hours_remaining:$hours_remaining).(($minutes_remaining<10)?"0".$minutes_remaining:$minutes_remaining).$row["raid_id"];
				}
				// Compute progress percentage
				if($status == "Completed"){
					$_rc_pct = 100;
				}else{
					$_rc_pct = 100-((($days_remaining+($hours_remaining/24)+($minutes_remaining/1440)) / $row["duration"])*100);
				}
				// Outcome badge for completed cards
				$_rc_outcome_class = '';
				$_rc_outcome_badge = '';
				if($status == "Completed"){
					if($outcome == 1){
						$_rc_outcome_class = ($type == 'outgoing') ? 'rc-victory' : 'rc-defeat';
						$_rc_outcome_badge = ($type == 'outgoing') ? 'Victory' : 'Defeat';
					}else{
						$_rc_outcome_class = ($type == 'outgoing') ? 'rc-defeat' : 'rc-victory';
						$_rc_outcome_badge = ($type == 'outgoing') ? 'Defeat' : 'Victory';
					}
				}
				$rows[$decimal] = "";
				$rows[$decimal] .= "<div class='rc-card' id='raid-row-".$row['raid_id']."'>";
				// Progress bar
				$rows[$decimal] .= "<div class='rc-progress-bar'><div class='rc-progress-fill' style='width:".$_rc_pct."%'></div></div>";
				// Realm name — full-width centered row so it sits over the column divider
				$rows[$decimal] .= "<div class='rc-card-realm'>".ucfirst($row['realm_name'])."</div>";
				// Header: theme image, user info, countdown/badge
				$rows[$decimal] .= "<div class='rc-card-header'>";
				$rows[$decimal] .= "<img class='rc-theme-img' loading='lazy' onerror='this.src=\"/staking/icons/skull.png\";' src='images/themes/".$row["theme_id"].".jpg'>";
				$rows[$decimal] .= "<a href='/staking/profile.php?username=".urlencode($row['username'])."'  class='rc-user-row'>";
				if($row["avatar"] != "") $rows[$decimal] .= "<img class='rc-avatar' loading='lazy' onerror='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg'>";
				$rows[$decimal] .= "<span class='rc-username'>".$row['username']."</span></a>";
				if($status == "Completed"){
					$rows[$decimal] .= "<div class='rc-outcome-badge ".$_rc_outcome_class."'>".$_rc_outcome_badge."</div>";
				}else{
					$rows[$decimal] .= "<div class='rc-countdown-block'><span class='rc-countdown-label'>Time Left</span>".$time_message."</div>";
				}
				$rows[$decimal] .= "</div>"; // rc-card-header
				// Avatars per column: session user vs opponent, assigned by raid direction
				$_sess_avatar_url = (isset($_SESSION['userData']['discord_id'], $_SESSION['userData']['avatar']) && $_SESSION['userData']['avatar'] != '')
					? 'https://cdn.discordapp.com/avatars/'.$_SESSION['userData']['discord_id'].'/'.$_SESSION['userData']['avatar'].'.jpg'
					: '/staking/icons/skull.png';
				$_opp_avatar_url = ($row['avatar'] != '')
					? 'https://cdn.discordapp.com/avatars/'.$row['discord_id'].'/'.$row['avatar'].'.jpg'
					: '/staking/icons/skull.png';
				// outgoing: col1=attacker(session), col2=defender(opponent)
				// incoming: col1=attacker(opponent), col2=defender(session)
				$_col1_avatar = ($type == 'outgoing') ? $_sess_avatar_url : $_opp_avatar_url;
				$_col2_avatar = ($type == 'outgoing') ? $_opp_avatar_url : $_sess_avatar_url;
				// Body: two result columns
				$rows[$decimal] .= "<div class='rc-card-body'>";
				$rows[$decimal] .= "<div class='rc-col'>";
				$rows[$decimal] .= "<img class='rc-col-avatar' src='".$_col1_avatar."' loading='lazy' onerror='this.src=\"/staking/icons/skull.png\";'>";
				$rows[$decimal] .= "<span class='rc-col-label'>".$results1." Results</span>";
				$rows[$decimal] .= "<div class='rc-col-content'>".$offense_results."</div>";
				$rows[$decimal] .= "</div>";
				$rows[$decimal] .= "<div class='rc-col'>";
				$rows[$decimal] .= "<img class='rc-col-avatar' src='".$_col2_avatar."' loading='lazy' onerror='this.src=\"/staking/icons/skull.png\";'>";
				$rows[$decimal] .= "<span class='rc-col-label'>".$results2." Results</span>";
				$rows[$decimal] .= "<div class='rc-col-content'>".$defense_results."</div>";
				$rows[$decimal] .= "</div>";
				$rows[$decimal] .= "</div>"; // rc-card-body
				// Soldier breakdown from raids_logs (completed only)
				if($status == "Completed"){
					$rows[$decimal] .= getRaidLogsDisplay($conn, $row['raid_id']);
				}
				// Retreat button (outgoing pending only)
				if($type == 'outgoing' && $date > time()){
					$rows[$decimal] .= "<div class='rc-action-row'><input type='button' class='small-button' value='Retreat' onclick='retreatRaid(".$row['raid_id'].")'/></div>";
				}
				$rows[$decimal] .= "</div>"; // rc-card
			}
			ksort($rows);
			if(strtolower($status) == "completed"){
				$rows = array_reverse($rows);
			}
			foreach($rows AS $duration => $output){
			    $final_output .= $output;
			}
			$final_output .= "</div>"; // rc-list
			if(!$history && $status == "Completed"){
				$final_output .= "<div class='rc-history-link'><a href='raids.php'>View ".ucfirst($type)." Raid History</a></div>";
			}
			$final_output .= "</div>";
			return $final_output;
		} else {
		  //echo "0 results";
	    }
	}
}

function getTotalRaids($conn){
	$month_sql = "SELECT (SELECT COUNT(success_raids.id) FROM raids AS success_raids INNER JOIN realms AS success_realms ON success_realms.id = success_raids.offense_id INNER JOIN users AS success_users ON success_users.id = success_realms.user_id 
				  WHERE success_raids.outcome = '1' AND success_users.id = users.id AND DATE(success_raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS success, 
	             (SELECT COUNT(failed_raids.id) FROM raids AS failed_raids INNER JOIN realms AS  failed_realms ON failed_realms.id = failed_raids.offense_id INNER JOIN users AS failed_users ON failed_users.id = failed_realms.user_id 
				  WHERE failed_raids.outcome = '2' AND failed_users.id = users.id AND DATE(failed_raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS failure, 
				 (SELECT COUNT(progress_raids.id) FROM raids AS progress_raids INNER JOIN realms AS  progress_realms ON progress_realms.id = progress_raids.offense_id INNER JOIN users AS progress_users ON progress_users.id = progress_realms.user_id 
				  WHERE progress_raids.outcome = '0' AND progress_users.id = users.id AND DATE(progress_raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS progress, 
        	  COUNT(raids.id) AS total, SUM(raids.duration) AS total_duration, users.id AS user_id 
		    	  FROM users INNER JOIN realms ON users.id = realms.user_id INNER JOIN raids ON raids.offense_id = realms.id WHERE users.id = '".$_SESSION['userData']['user_id']."' AND DATE(raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')";
	$month_result = $conn->query($month_sql);

	$arrow = "down";
	$display = "block";
	if(isset($_SESSION['userData']['total_raids'])){
		if($_SESSION['userData']['total_raids'] == "show"){
			$arrow = "down";
			$display = "block";
		}else if($_SESSION['userData']['total_raids'] == "hide"){
			$arrow = "up";
			$display = "none";
		}
	}

	echo "<div class='rc-section-title' onclick='toggleTotalRaids(this.querySelector(\".raid-arrow-icon\"))'>"
	   . "<img class='raid-arrow-icon' id='".$arrow."' src='icons/".$arrow.".png'>"
	   . "<span class='rc-section-label'>RAID STATS</span>"
	   . "</div>";
	echo '<a name="total-missions" id="total-missions"></a>';
	echo '<div class="content" id="total-missions-container" style="display:'.$display.'">';

	// --- This Month card ---
	$m_score = 0; $m_total = 0; $m_progress = 0; $m_success = 0; $m_failure = 0; $m_duration = 0;
	if ($month_result->num_rows > 0) {
		$month_row = $month_result->fetch_assoc();
		$m_total    = intval($month_row["total"]);
		$m_progress = intval($month_row["progress"]);
		$m_success  = intval($month_row["success"]);
		$m_failure  = intval($month_row["failure"]);
		$m_duration = intval($month_row["total_duration"]);
		$m_score    = calculateScore($m_duration, $m_success, $m_failure, $m_progress);
	}
	$m_completed = $m_total - $m_progress;
	$m_spct = ($m_completed > 0) ? round($m_success / $m_completed * 100, 1) : 0;
	$m_fpct = ($m_completed > 0) ? round($m_failure / $m_completed * 100, 1) : 0;

	echo "<div class='rs-grid'>";
	echo "<div class='rs-card'>";
	echo "<div class='rs-card-period'><img class='missions-icon' src='icons/calendar.png'/> ".date('F')."</div>";
	echo "<div class='rs-score-label'>Score</div>";
	echo "<div class='rs-score'>".number_format($m_score)."</div>";
	echo "<div class='rs-stat-row'>";
	echo "<div class='rs-stat'><div class='rs-stat-value'>".number_format($m_total)."</div><div class='rs-stat-label'>Total Raids</div></div>";
	echo "<div class='rs-stat'><div class='rs-stat-value'>".$m_progress."</div><div class='rs-stat-label'>In Progress</div></div>";
	echo "<div class='rs-stat rs-success'><div class='rs-stat-value'>".number_format($m_success)." <span class='rs-pct'>".$m_spct."%</span></div><div class='rs-stat-label'>Success</div></div>";
	echo "<div class='rs-stat rs-failure'><div class='rs-stat-value'>".number_format($m_failure)." <span class='rs-pct'>".$m_fpct."%</span></div><div class='rs-stat-label'>Failure</div></div>";
	echo "</div>"; // rs-stat-row
	echo "<div class='rs-lb-link'><form action='leaderboards.php' method='post'><input type='hidden' name='filterby' value='monthly-raids'/><input type='submit' class='small-button' value='".date("F")." Leaderboard'/></form></div>";
	echo "</div>"; // rs-card

	// --- All Time card ---
	$sql = "SELECT (SELECT COUNT(success_raids.id) FROM raids AS success_raids INNER JOIN realms AS success_realms ON success_realms.id = success_raids.offense_id INNER JOIN users AS success_users ON success_users.id = success_realms.user_id WHERE success_raids.outcome = '1' AND success_users.id = users.id) AS success, 
               (SELECT COUNT(failed_raids.id) FROM raids AS failed_raids INNER JOIN realms AS failed_realms ON failed_realms.id = failed_raids.offense_id INNER JOIN users AS failed_users ON failed_users.id = failed_realms.user_id  WHERE failed_raids.outcome = '2' AND failed_users.id = users.id) AS failure, 
     		   (SELECT COUNT(progress_raids.id) FROM raids AS progress_raids INNER JOIN realms AS progress_realms ON progress_realms.id = progress_raids.offense_id INNER JOIN users AS progress_users ON progress_users.id = progress_realms.user_id  WHERE progress_raids.outcome = '0' AND progress_users.id = users.id) AS progress, 
		        COUNT(raids.id) AS total, SUM(raids.duration) AS total_duration, users.id AS user_id 
			    FROM users INNER JOIN realms ON users.id = realms.user_id INNER JOIN raids ON raids.offense_id = realms.id WHERE users.id = '".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
		$row = $result->fetch_assoc();
		$a_total    = intval($row["total"]);
		$a_progress = intval($row["progress"]);
		$a_success  = intval($row["success"]);
		$a_failure  = intval($row["failure"]);
		$a_duration = intval($row["total_duration"]);
		$a_score    = calculateScore($a_duration, $a_success, $a_failure, $a_progress);
		$a_completed = $a_total - $a_progress;
		$a_spct = ($a_completed > 0) ? round($a_success / $a_completed * 100, 1) : 0;
		$a_fpct = ($a_completed > 0) ? round($a_failure / $a_completed * 100, 1) : 0;
		echo "<div class='rs-card'>";
		echo "<div class='rs-card-period'><img class='missions-icon' src='icons/infinity.png'/> All Time</div>";
		echo "<div class='rs-score-label'>Score</div>";
		echo "<div class='rs-score'>".number_format($a_score)."</div>";
		echo "<div class='rs-stat-row'>";
		echo "<div class='rs-stat'><div class='rs-stat-value'>".number_format($a_total)."</div><div class='rs-stat-label'>Total Raids</div></div>";
		echo "<div class='rs-stat'><div class='rs-stat-value'>".$a_progress."</div><div class='rs-stat-label'>In Progress</div></div>";
		echo "<div class='rs-stat rs-success'><div class='rs-stat-value'>".number_format($a_success)." <span class='rs-pct'>".$a_spct."%</span></div><div class='rs-stat-label'>Success</div></div>";
		echo "<div class='rs-stat rs-failure'><div class='rs-stat-value'>".number_format($a_failure)." <span class='rs-pct'>".$a_fpct."%</span></div><div class='rs-stat-label'>Failure</div></div>";
		echo "</div>"; // rs-stat-row
		echo "<div class='rs-lb-link'><form action='leaderboards.php' method='post'><input type='hidden' name='filterby' value='raids'/><input type='submit' class='small-button' value='All Time Leaderboard'/></form></div>";
		echo "</div>"; // rs-card
	}
	echo "</div>"; // rs-grid
	echo "</div>"; // total-missions-container
}



function getTotalFactionRaids($conn){
	$realm_id = getRealmID($conn);
	$project_id = getRealmFaction($conn, $realm_id);

    $month_sql = "SELECT 
    realms.project_id AS project_id, 
    projects.name AS project_name, 
    projects.currency AS currency, 
    SUM(CASE WHEN raids.outcome = '1' THEN 1 ELSE 0 END) AS success,
    SUM(CASE WHEN raids.outcome = '2' THEN 1 ELSE 0 END) AS failure,
    SUM(CASE WHEN raids.outcome = '0' THEN 1 ELSE 0 END) AS progress,
    COUNT(raids.id) AS total,
    SUM(raids.duration) AS total_duration
	FROM 
    users 
    INNER JOIN realms ON users.id = realms.user_id 
    INNER JOIN projects ON projects.id = realms.project_id 
    INNER JOIN raids ON raids.offense_id = realms.id 
	WHERE 
	DATE(raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')  
	AND 
	realms.project_id = '".$project_id."'  
	GROUP BY realms.project_id 
	ORDER BY total DESC;";

	$month_result = $conn->query($month_sql);

	$arrow = "down";
	$display = "block";
	if(isset($_SESSION['userData']['total_factions'])){
		if($_SESSION['userData']['total_factions'] == "show"){
			$arrow = "down";
			$display = "block";
		}else if($_SESSION['userData']['total_factions'] == "hide"){
			$arrow = "up";
			$display = "none";
		}
	}

	$project = getProjectInfo($conn, $project_id);
	echo "<div class='rc-section-title' onclick='toggleTotalFactions(this.querySelector(\".raid-arrow-icon\"))'>"
	   . "<img class='raid-arrow-icon' id='".$arrow."' src='icons/".$arrow.".png'>"
	   . "<span class='rc-section-label'>FACTION STATS</span>"
	   . "</div>";
	echo '<a name="total-factions" id="total-factions"></a>';
	echo '<div class="content" id="total-factions-container" style="display:'.$display.'">';
	echo "<div class='rs-faction-header'>"
	   . "<img class='rs-faction-icon' src='icons/".strtolower($project['currency']).".png' onerror=\"this.src='icons/skull.png'\">"
	   . "<span>".$project["name"]."</span>"
	   . "</div>";

	// --- This Month card ---
	$m_score = 0; $m_total = 0; $m_progress = 0; $m_success = 0; $m_failure = 0; $m_duration = 0;
	if ($month_result->num_rows > 0) {
		$month_row = $month_result->fetch_assoc();
		$m_total    = intval($month_row["total"]);
		$m_progress = intval($month_row["progress"]);
		$m_success  = intval($month_row["success"]);
		$m_failure  = intval($month_row["failure"]);
		$m_duration = intval($month_row["total_duration"]);
		$m_score    = calculateScore($m_duration, $m_success, $m_failure, $m_progress);
	}
	$m_completed = $m_total - $m_progress;
	$m_spct = ($m_completed > 0) ? round($m_success / $m_completed * 100, 1) : 0;
	$m_fpct = ($m_completed > 0) ? round($m_failure / $m_completed * 100, 1) : 0;

	echo "<div class='rs-grid'>";
	echo "<div class='rs-card'>";
	echo "<div class='rs-card-period'><img class='missions-icon' src='icons/calendar.png'/> ".date('F')."</div>";
	echo "<div class='rs-score-label'>Score</div>";
	echo "<div class='rs-score'>".number_format($m_score)."</div>";
	echo "<div class='rs-stat-row'>";
	echo "<div class='rs-stat'><div class='rs-stat-value'>".number_format($m_total)."</div><div class='rs-stat-label'>Total Raids</div></div>";
	echo "<div class='rs-stat'><div class='rs-stat-value'>".$m_progress."</div><div class='rs-stat-label'>In Progress</div></div>";
	echo "<div class='rs-stat rs-success'><div class='rs-stat-value'>".number_format($m_success)." <span class='rs-pct'>".$m_spct."%</span></div><div class='rs-stat-label'>Success</div></div>";
	echo "<div class='rs-stat rs-failure'><div class='rs-stat-value'>".number_format($m_failure)." <span class='rs-pct'>".$m_fpct."%</span></div><div class='rs-stat-label'>Failure</div></div>";
	echo "</div>"; // rs-stat-row
	echo "<div class='rs-lb-link'><form action='leaderboards.php' method='post'><input type='hidden' name='filterby' value='monthly-factions'/><input type='submit' class='small-button' value='".date("F")." Leaderboard'/></form></div>";
	echo "</div>"; // rs-card

	// --- All Time card ---
	$sql = "SELECT 
    realms.project_id AS project_id, 
    projects.name AS project_name, 
    projects.currency AS currency, 
    SUM(CASE WHEN raids.outcome = '1' THEN 1 ELSE 0 END) AS success,
    SUM(CASE WHEN raids.outcome = '2' THEN 1 ELSE 0 END) AS failure,
    SUM(CASE WHEN raids.outcome = '0' THEN 1 ELSE 0 END) AS progress,
    COUNT(raids.id) AS total,
    SUM(raids.duration) AS total_duration
	FROM 
    users 
    INNER JOIN realms ON users.id = realms.user_id 
    INNER JOIN projects ON projects.id = realms.project_id 
    INNER JOIN raids ON raids.offense_id = realms.id 
	WHERE 
	realms.project_id = '".$project_id."' 
	GROUP BY realms.project_id 
	ORDER BY total DESC;";

	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
		$row = $result->fetch_assoc();
		$a_total    = intval($row["total"]);
		$a_progress = intval($row["progress"]);
		$a_success  = intval($row["success"]);
		$a_failure  = intval($row["failure"]);
		$a_duration = intval($row["total_duration"]);
		$a_score    = calculateScore($a_duration, $a_success, $a_failure, $a_progress);
		$a_completed = $a_total - $a_progress;
		$a_spct = ($a_completed > 0) ? round($a_success / $a_completed * 100, 1) : 0;
		$a_fpct = ($a_completed > 0) ? round($a_failure / $a_completed * 100, 1) : 0;
		echo "<div class='rs-card'>";
		echo "<div class='rs-card-period'><img class='missions-icon' src='icons/infinity.png'/> All Time</div>";
		echo "<div class='rs-score-label'>Score</div>";
		echo "<div class='rs-score'>".number_format($a_score)."</div>";
		echo "<div class='rs-stat-row'>";
		echo "<div class='rs-stat'><div class='rs-stat-value'>".number_format($a_total)."</div><div class='rs-stat-label'>Total Raids</div></div>";
		echo "<div class='rs-stat'><div class='rs-stat-value'>".$a_progress."</div><div class='rs-stat-label'>In Progress</div></div>";
		echo "<div class='rs-stat rs-success'><div class='rs-stat-value'>".number_format($a_success)." <span class='rs-pct'>".$a_spct."%</span></div><div class='rs-stat-label'>Success</div></div>";
		echo "<div class='rs-stat rs-failure'><div class='rs-stat-value'>".number_format($a_failure)." <span class='rs-pct'>".$a_fpct."%</span></div><div class='rs-stat-label'>Failure</div></div>";
		echo "</div>"; // rs-stat-row
		echo "<div class='rs-lb-link'><form action='leaderboards.php' method='post'><input type='hidden' name='filterby' value='factions'/><input type='submit' class='small-button' value='All Time Leaderboard'/></form></div>";
		echo "</div>"; // rs-card
	}
	echo "</div>"; // rs-grid
	echo "</div>"; // total-factions-container
}


function getRaidRealmID($conn, $raid_id, $faction){
	$sql = "SELECT ".$faction."_id FROM raids WHERE id = '".$raid_id."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			return $row[$faction."_id"];
		}
	}else{
		
	}
}

function getRaidLocationLevelAmount($conn, $raid_id, $faction){
	$sql = "SELECT amount, raids_locations.type AS type, locations.name AS location_name FROM raids_locations INNER JOIN locations ON locations.id = raids_locations.location_id WHERE raid_id = '".$raid_id."' AND faction = '".$faction."'";
	$result = $conn->query($sql);

	$location_results = "";
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$location_results .= "<br>".(($row["type"] == "debit")?"-":"+").$row["amount"]." ".ucfirst($row["location_name"]);
		}
	}else{
		
	}
	return $location_results."<br>";
}

function getRaidProjectBalanceAmount($conn, $raid_id, $faction){
	$sql = "SELECT amount, projects.currency AS project_currency FROM raids_projects INNER JOIN projects ON projects.id = raids_projects.project_id WHERE raid_id = '".$raid_id."'";
	$result = $conn->query($sql);

	$project_results = "";
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$project_results .= (($faction == "defense")?"-":"+").number_format($row["amount"])." ".($row["project_currency"]);
		}
	}else{
		
	}
	return $project_results;
}

function endRaid($conn, $raid_id){
	// Get raid faction realm IDs
	$defense_id = getRaidRealmID($conn, $raid_id, "defense");
	$offense_id = getRaidRealmID($conn, $raid_id, "offense");
	
	// Calculate faction score based on locations
	$defense = calculateRaidDefense($conn, $defense_id);
	$offense = calculateRaidOffense($conn, $offense_id);
	
	// Total scores and calculate percentage
	$total = $defense + $offense;
	$percentage = (100/$total);
	
	// Calculate thresholds for random number generation
	$defense_threshold = $percentage * $defense;
	$offense_threshold = $percentage * $offense;
	
	// Apply consumable success rate boosts
	$defender_boost     = getLocationSuccessRateBoost($conn, $defense_id, 'defense');
	$attacker_loc_boost = getLocationSuccessRateBoost($conn, $offense_id, 'offense');
	$raid_boost         = getRaidSuccessRateBoost($conn, $raid_id);
	$attacker_boost     = $attacker_loc_boost + $raid_boost; // 0-20
	
	// Adjusted threshold: defender boost raises it (harder for attacker), attacker boost lowers it
	$adjusted_threshold = $defense_threshold + $defender_boost - $attacker_boost;
	if($adjusted_threshold < 1)  $adjusted_threshold = 1;
	if($adjusted_threshold > 99) $adjusted_threshold = 99;
	
	// Failure = 2, Success = 1
	$outcome = rand(1, 100);
	
	// Determine faction winner based on adjusted threshold
	$winner = "";
	if($outcome < $adjusted_threshold){
		$winner = "defense";
	}else{
		$winner = "offense";
	}
	
	// Determine outcome based on winner
	if($winner == "offense"){
		$outcome = 1;
	}else if($winner == "defense"){
		$outcome = 2;
	}
	
	$sql = "UPDATE raids SET outcome = '".$outcome."' WHERE id='".$raid_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
	
	// Raid consumables for this raid
	$raid_consumables    = getRaidConsumablesList($conn, $raid_id);
	$raid_double_rewards = in_array(6, $raid_consumables);
	$raid_random_reward  = in_array(7, $raid_consumables);
	
	// Based on outcome, determine location leveling and project rewards
	if($outcome == 1){
		// If Defense Offense is above level 10, damage a random offense location to maintain gamification balance
		$defense_offense = calculateRaidOffense($conn, $defense_id);
		if($defense_offense > 10){
			$dmg_loc = selectRandomLocationID($conn, "offense");
		}else{
			$dmg_loc = selectRandomLocationID($conn, "defense");
		}
		// Check for Double Rewards shield on the damaged location (defense realm)
		$hit1_shielded = hasDoubleRewardsShield($conn, $defense_id, $dmg_loc);
		if($hit1_shielded){
			// Shield absorbs the hit — consume DR only, all other consumables survive
			removeLocationConsumable($conn, $defense_id, $dmg_loc, 6, $raid_id);
		}else{
			// Real damage: level down and burn all consumables on that location
			alterRealmLocationLevel($conn, $raid_id, "defense", $dmg_loc, 1, "debit");
			burnLocationConsumables($conn, $defense_id, $dmg_loc, $raid_id);
		}
		// Double Rewards: second hit
		if($raid_double_rewards){
			if($hit1_shielded){
				// Shield was stripped — hit the same location now unshielded
				$dmg_loc2 = $dmg_loc;
			}else{
				// First location already damaged — pick a different defense location
				// If hit 1 targeted an offense location (high-offense balance case), no exclusion needed
				$exclude = in_array($dmg_loc, array(3,5,7)) ? $dmg_loc : null;
				$dmg_loc2 = selectRandomLocationIDExcluding("defense", $exclude);
			}
			if(hasDoubleRewardsShield($conn, $defense_id, $dmg_loc2)){
				removeLocationConsumable($conn, $defense_id, $dmg_loc2, 6, $raid_id);
			}else{
				alterRealmLocationLevel($conn, $raid_id, "defense", $dmg_loc2, 1, "debit");
				burnLocationConsumables($conn, $defense_id, $dmg_loc2, $raid_id);
			}
		}
		// Reward random points to offense from defense
		$project = selectRandomProjectID($conn, $defense_id);
		$project_balance = 0;
		foreach($project AS $id => $balance){
			$project_balance = $balance;
			$project_id = $id;
		}
		$difference = abs($offense-$defense);
		if($difference == 0){
			$difference = 1;
		}
		// Divide balance by 100 and multiply by absolute value of difference
		$amount = round(($balance/100)*$difference);
		// Cap at base 500, then double if Double Rewards is active
		if($amount > 500) $amount = 500;
		if($raid_double_rewards) $amount = $amount * 2;
		// Random Reward: compare before assigning — replace with random project if it pays more
		if($raid_random_reward){
			$rr_project = selectRandomProjectID($conn, $defense_id);
			foreach($rr_project AS $rr_proj_id => $rr_bal){}
			$rr_amount = round(($rr_bal/100)*$difference);
			if($rr_amount > 500) $rr_amount = 500;
			if($raid_double_rewards) $rr_amount = $rr_amount * 2;
			if($rr_amount > $amount){
				$project_id = $rr_proj_id;
				$amount     = $rr_amount;
			}
		}
		assignRealmProjectRewards($conn, $raid_id, $project_id, $amount);
		// Location Random Reward: if offense has all offense/portal locations stocked with RR, credit a random location
		if(hasRandomReward($conn, $offense_id, 'offense')){
			$rr_loc = selectRandomLocationIDAny();
			alterRealmLocationLevel($conn, $raid_id, "offense", $rr_loc, 1, "credit");
			consumeRandomRewards($conn, $offense_id, 'offense', $raid_id);
		}
	}else if($outcome == 2){
		// Damage to random offense location for offense
		$offense_loc = selectRandomLocationID($conn, "offense");
		if(hasDoubleRewardsShield($conn, $offense_id, $offense_loc)){
			removeLocationConsumable($conn, $offense_id, $offense_loc, 6, $raid_id);
		}else{
			alterRealmLocationLevel($conn, $raid_id, "offense", $offense_loc, 1, "debit");
			burnLocationConsumables($conn, $offense_id, $offense_loc, $raid_id);
		}
		// Improve same offense location for defense
		alterRealmLocationLevel($conn, $raid_id, "defense", $offense_loc, 1, "credit");
		// 1 in 3 chance of damage to offense portal
		if(rand(1, 3) == 2){
			$portal_id = 1;
			if(hasDoubleRewardsShield($conn, $offense_id, $portal_id)){
				removeLocationConsumable($conn, $offense_id, $portal_id, 6, $raid_id);
			}else{
				alterRealmLocationLevel($conn, $raid_id, "offense", $portal_id, 1, "debit");
				burnLocationConsumables($conn, $offense_id, $portal_id, $raid_id);
			}
		}
		// Location Random Reward: if defense has all defense locations stocked with RR, credit a random location
		if(hasRandomReward($conn, $defense_id, 'defense')){
			$rr_loc = selectRandomLocationIDAny();
			alterRealmLocationLevel($conn, $raid_id, "defense", $rr_loc, 1, "credit");
			consumeRandomRewards($conn, $defense_id, 'defense', $raid_id);
		}
	}
	$off_info_res = $conn->query("SELECT r.name, r.theme_id, u.username, u.discord_id, u.avatar FROM realms r INNER JOIN users u ON u.id = r.user_id WHERE r.id='".$offense_id."'");
	$off_info     = $off_info_res ? $off_info_res->fetch_assoc() : null;
	$off_name     = $off_info ? $off_info['name']       : 'Unknown Realm';
	$off_username = $off_info ? $off_info['username']   : 'Unknown';
	$off_discord  = $off_info ? $off_info['discord_id'] : '';
	$off_avatar   = $off_info ? $off_info['avatar']     : '';
	$off_theme_id = $off_info ? $off_info['theme_id']   : '';
	$def_info_res = $conn->query("SELECT r.name, r.theme_id, u.username, u.discord_id, u.avatar FROM realms r INNER JOIN users u ON u.id = r.user_id WHERE r.id='".$defense_id."'");
	$def_info     = $def_info_res ? $def_info_res->fetch_assoc() : null;
	$def_name     = $def_info ? $def_info['name']       : 'Unknown Realm';
	$def_username = $def_info ? $def_info['username']   : 'Unknown';
	$def_discord  = $def_info ? $def_info['discord_id'] : '';
	$def_avatar   = $def_info ? $def_info['avatar']     : '';
	$def_theme_id = $def_info ? $def_info['theme_id']   : '';
	$off_mention  = $off_discord ? "<@".$off_discord.">" : $off_username;
	$def_mention  = $def_discord ? "<@".$def_discord.">" : $def_username;
	$off_avatar_url = ($off_discord && $off_avatar) ? "https://cdn.discordapp.com/avatars/".$off_discord."/".$off_avatar.".png" : "";
	$def_avatar_url = ($def_discord && $def_avatar) ? "https://cdn.discordapp.com/avatars/".$def_discord."/".$def_avatar.".png" : "";
	$off_image_url  = $off_theme_id ? "https://skulliance.io/staking/images/themes/".$off_theme_id.".jpg" : "";
	$def_image_url  = $def_theme_id ? "https://skulliance.io/staking/images/themes/".$def_theme_id.".jpg" : "";
	// Consumables used in this raid
	$con_used_res = $conn->query("SELECT rc.consumable_id, c.name FROM raids_consumables rc INNER JOIN consumables c ON c.id = rc.consumable_id WHERE rc.raid_id='".$raid_id."'");
	$success_boost_map = array(1 => 4, 2 => 3, 3 => 2, 4 => 1);
	$total_success_boost = 0;
	$con_used = array();
	if($con_used_res) while($r = $con_used_res->fetch_assoc()){
		if(isset($success_boost_map[$r['consumable_id']])) $total_success_boost += $success_boost_map[$r['consumable_id']];
		else $con_used[] = $r['name'];
	}
	if($total_success_boost > 0) array_unshift($con_used, "+".$total_success_boost."% Success");
	$items_line = !empty($con_used) ? "\n🧪 **Items used:** ".implode(" · ", $con_used) : "";
	if($outcome == 1){
		$loot_res = $conn->query("SELECT rp.amount, p.currency FROM raids_projects rp INNER JOIN projects p ON p.id = rp.project_id WHERE rp.raid_id='".$raid_id."' ORDER BY rp.id DESC LIMIT 1");
		$loot_row = $loot_res ? $loot_res->fetch_assoc() : null;
		$loot_line = $loot_row ? "\n💰 **Looted:** ".number_format($loot_row['amount'])." ".$loot_row['currency'] : "";
		$double_dmg_line = $raid_double_rewards ? "\n💥 **Double Damage** — 2 defense locations targeted" : "";
		$raid_desc  = "The battle is over — the attacker prevails!\n\n";
		$raid_desc .= "⚔️ **Attacker:** ".$off_mention." — ".$off_name."\n";
		$raid_desc .= "🛡️ **Defender:** ".$def_mention." — ".$def_name;
		$raid_desc .= $loot_line.$double_dmg_line.$items_line;
		$author = array("name" => $off_username." · ".$off_name, "icon_url" => $off_avatar_url, "url" => "https://skulliance.io/staking/profile.php?username=".urlencode($off_username));
		discordmsg("💀 Attacker Victory", $raid_desc, $def_image_url, "https://skulliance.io/staking/realms.php", "raids", $off_avatar_url, "00C8A0", $author);
	} else {
		$raid_desc  = "The battle is over — the defender holds their ground!\n\n";
		$raid_desc .= "⚔️ **Attacker:** ".$off_mention." — ".$off_name."\n";
		$raid_desc .= "🛡️ **Defender:** ".$def_mention." — ".$def_name."\n";
		$raid_desc .= "🏹 The attacker's forces were repelled and took damage.";
		$raid_desc .= $items_line;
		$author = array("name" => $def_username." · ".$def_name, "icon_url" => $def_avatar_url, "url" => "https://skulliance.io/staking/profile.php?username=".urlencode($def_username));
		discordmsg("🛡️ Defense Holds", $raid_desc, $def_image_url, "https://skulliance.io/staking/realms.php", "raids", $def_avatar_url, "4A90D9", $author);
	}
	// Soldier death rolls
	if ($outcome == 1) {
		// Offense wins: tower garrison defenders may die
		rollTowerSoldierDeaths($conn, $raid_id, $defense_id);
	} else {
		// Defense wins: attacking raiders may die
		rollRaidSoldierDeaths($conn, $raid_id);
	}
	// Release raid soldiers back to reserve (alive ones return, dead ones stay dead in location=1)
	releaseRaidSoldiers($conn, $raid_id);

	return $outcome;
}

function selectRandomLocationID($conn, $faction){
	if($faction == "offense"){
		$offense_id = array_rand(array(2=>2,4=>4,6=>6), 1);
		return $offense_id;
	}
	if($faction == "defense"){
		$defense_id = array_rand(array(3=>3,5=>5,7=>7), 1);
		return $defense_id;
	}
}

// Like selectRandomLocationID but excludes a specific location_id from the pool.
// Falls back to the excluded location if it's the only one available.
function selectRandomLocationIDExcluding($faction, $exclude_id = null){
	$pool = ($faction == "offense") ? array(2=>2,4=>4,6=>6) : array(3=>3,5=>5,7=>7);
	if($exclude_id !== null && isset($pool[$exclude_id])) unset($pool[$exclude_id]);
	if(empty($pool)) return $exclude_id;
	return array_rand($pool, 1);
}

function alterRealmLocationLevel($conn, $raid_id, $faction, $location_id, $amount, $type){
	$sql = "INSERT INTO raids_locations (raid_id, faction, location_id, amount, type)
	VALUES ('".$raid_id."', '".$faction."', '".$location_id."', '".$amount."', '".$type."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
	// Get Realm ID from raid based on faction
	$realm_id = getRaidRealmID($conn, $raid_id, $faction);
	// Use Realm ID to update location level
	updateRealmLocationLevel($conn, $realm_id, $location_id, $amount, $type);
}

function selectRandomProjectID($conn, $realm_id){
	$sql = "SELECT realms.id AS realm_id, balances.project_id AS project_id, balance FROM realms INNER JOIN users ON users.id = realms.user_id INNER JOIN balances ON balances.user_id = users.id WHERE realms.id = '".$realm_id."' AND balance >= 100";
	$result = $conn->query($sql);
	
	$project_ids = array();
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$project_ids[$row['project_id']] = $row["balance"];
		}
	}else{
		
	}
	$random_project_id = array_rand($project_ids, 1);
	$selected_project = array();
	$selected_project[$random_project_id] = $project_ids[$random_project_id];
	return $selected_project;
}

function assignRealmProjectRewards($conn, $raid_id, $project_id, $amount){
	$sql = "INSERT INTO raids_projects (raid_id, project_id, amount) 
	VALUES ('".$raid_id."', '".$project_id."', '".$amount."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
	
	// Get Realm IDs from raid based on factions
	$offense_id = getRaidRealmID($conn, $raid_id, "offense");
	$defense_id = getRaidRealmID($conn, $raid_id, "defense");
	
	// Get User IDs based on realms by faction
	$offense_user_id = getRealmUserID($conn, $offense_id);
	$defense_user_id = getRealmUserID($conn, $defense_id);
	
	// Update balances, credit offense user, debit defense user
	updateBalance($conn, $offense_user_id, $project_id, $amount);
	updateBalance($conn, $defense_user_id, $project_id, -$amount);
	
	// Log transactions
	logCredit($conn, $offense_user_id, $amount, $project_id, $crafting=0, $bonus=0, $mission_id=0, $location_id=0, $raid_id);
	logDebit($conn, $defense_user_id, $item_id=0, $amount, $project_id, $crafting=0, $mission_id=0, $location_id=0, $raid_id);
}

function toggleRealmState($conn, $realm_id, $type){
	$rs_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
	$rs_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
	$rs_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
	$rs_avatar_url = ($rs_discord && $rs_avatar) ? "https://cdn.discordapp.com/avatars/".$rs_discord."/".$rs_avatar.".png" : "";
	$rs_mention    = $rs_discord ? "<@".$rs_discord.">" : $rs_username;
	$rs_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($rs_username);
	$rs_res        = $conn->query("SELECT r.name AS realm_name, r.theme_id, p.name AS faction_name FROM realms r INNER JOIN projects p ON p.id = r.project_id WHERE r.id='".$realm_id."'");
	$rs_row        = ($rs_res && $rs_res->num_rows > 0) ? $rs_res->fetch_assoc() : null;
	$rs_realm_name = $rs_row ? $rs_row['realm_name'] : 'Unknown Realm';
	$rs_realm_img  = $rs_row ? "https://skulliance.io/staking/images/themes/".$rs_row['theme_id'].".jpg" : "";
	$rs_author     = array("name" => $rs_username, "icon_url" => $rs_avatar_url, "url" => $rs_profile);

	if($type == "deactivate"){
		$sql = "UPDATE realms SET active = '0', created_date = '".date('Y-m-d H:i:s')."' WHERE id='".$realm_id."' AND user_id = '".$_SESSION['userData']['user_id']."'";
		if ($conn->query($sql) === TRUE) {
			echo "Your Realm has been deactivated.\r\n\r\nYou will not be able to reactivate it for 30 days.";
			if ($rs_row) {
				$rs_desc  = $rs_mention." has **deactivated** their realm **".$rs_realm_name."**.\n\n";
				$rs_desc .= "🏰 **Realm:** ".$rs_realm_name."\n";
				$rs_desc .= "⚔️ **Faction:** ".$rs_row['faction_name']."\n";
				$rs_desc .= "⏳ Cannot be reactivated for 30 days.";
				discordmsg("💤 Realm Deactivated", $rs_desc, $rs_realm_img, "https://skulliance.io/staking/realms.php", "realms", $rs_avatar_url, "888888", $rs_author);
			}
		} else {
			echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}else if($type == "reactivate"){
		$sql = "UPDATE realms SET active = '1' WHERE id='".$realm_id."' AND user_id = '".$_SESSION['userData']['user_id']."'";
		if ($conn->query($sql) === TRUE) {
			echo "Your Realm has been reactivated.";
			if ($rs_row) {
				$rs_desc  = $rs_mention." has **reactivated** their realm **".$rs_realm_name."**!\n\n";
				$rs_desc .= "🏰 **Realm:** ".$rs_realm_name."\n";
				$rs_desc .= "⚔️ **Faction:** ".$rs_row['faction_name'];
				discordmsg("⚡ Realm Reactivated", $rs_desc, $rs_realm_img, "https://skulliance.io/staking/realms.php", "realms", $rs_avatar_url, "00C8A0", $rs_author);
			}
		} else {
			echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}
}

function checkRealmState($conn){
	$sql = "SELECT active FROM realms WHERE user_id = '".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			return $row['active'];
		}
	}else{
		
	}
}

function checkRealmActivation($conn){
	$sql = "SELECT id FROM realms WHERE user_id = '".$_SESSION['userData']['user_id']."' AND DATE(created_date) <= DATE_FORMAT((CURDATE() - INTERVAL 30 DAY),'%Y-%m-%d')";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
		return "true";
	}else{
		$sql = "SELECT created_date, DATE_ADD(DATE(created_date), INTERVAL 30 DAY) AS activation_date FROM realms WHERE user_id = '".$_SESSION['userData']['user_id']."'";
		$result = $conn->query($sql);
	
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				return $row["activation_date"];
			}
		}
	}
}

function getFactionsRealmsMapData($conn){
	$sql = 'SELECT users.username AS user_name, realms.id AS realm_id, concat("https://cdn.discordapp.com/avatars/",users.discord_id,"/",users.avatar,".jpg") AS user_image, realms.name AS realm_name, concat("https://skulliance.io/staking/images/themes/",realms.theme_id,".jpg") AS realm_image, projects.name AS faction_name, projects.currency AS faction_currency FROM `realms` INNER JOIN projects ON projects.id = realms.project_id INNER JOIN users ON users.id = realms.user_id WHERE realms.active = 1 ORDER BY faction_name';
	
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	    echo "<script type='text/javascript'>";
	    echo "window.csvData = `";
	    echo '"user_name","user_image","realm_name","realm_image","faction_name","faction_currency","realm_id","avg_level"';
	    echo "\n";
    	
		$realms = array();
	    while ($row = $result->fetch_assoc()) {
			$levels = getRealmLocationNamesLevels($conn, $row['realm_id']);
			$level = array_sum($levels);
			if($level != 0){
				$avg_level = round($level / count($levels));
				$realms[$row['realm_id']] = array();
				$realms[$row['realm_id']]["user_name"] = $row['user_name'];
				$realms[$row['realm_id']]["user_image"] = $row['user_image'];
				$realms[$row['realm_id']]["realm_name"] = $row['realm_name'];
				$realms[$row['realm_id']]["realm_image"] = $row['realm_image'];
				$realms[$row['realm_id']]["faction_name"] = $row['faction_name'];
				$realms[$row['realm_id']]["faction_currency"] = $row['faction_currency'];
				$realms[$row['realm_id']]["avg_level"] = $avg_level;
			}
	    }

		$realm_total = count($realms);
		$realm_count = 0;
		foreach($realms AS $realm_id => $realm){
			$realm_count++;
	        echo '"'.$realm['user_name'].'",';
	        echo '"'.$realm['user_image'].'",';
	        echo '"'.$realm['realm_name'].'",';
	        echo '"'.$realm['realm_image'].'",';
	        echo '"'.$realm['faction_name'].'",';
	        echo '"'.$realm['faction_currency'].'",';
	        echo '"'.$realm_id.'",';
	        echo '"'.$realm['avg_level'].'"';
			if ($realm_count < $realm_total) {
				echo "\n"; // Only add newline if not the last realm
			}
		}
    
	    echo "`;";
	    echo "</script>";
	}
}

function getActiveRaidsMapData($conn){
	$result = $conn->query("SELECT offense_id, defense_id FROM raids WHERE outcome='0' AND DATE(created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')");
	$pairs = [];
	if ($result && $result->num_rows > 0) {
		while ($row = $result->fetch_assoc()) {
			$pairs[] = [(int)$row['offense_id'], (int)$row['defense_id']];
		}
	}
	echo "<script type='text/javascript'>window.raidPairs=" . json_encode($pairs) . ";</script>";
}

/* END REALMS */

/* SKULL SWAP */

function saveSwapScore($conn, $score){
	if(isset($_SESSION['userData']['user_id'])){
		$ss_user_id = $_SESSION['userData']['user_id'];
		// Check if unrewarded score exists.
		// If it exists, check if new score is higher than existing score. If it is, update unrewarded score.
		// If it doesn't exist, create a new score in the database
		$current_score = getSwapScore($conn);
		$ss_is_high = false;
		if(isset($current_score)){
			if($score > $current_score){
				$ss_is_high = true;
				$sql = "UPDATE scores SET score = '".$score."', attempts = attempts + 1 WHERE user_id='".$ss_user_id."' AND reward = '0' AND project_id = '0'";
				if ($conn->query($sql) === TRUE) {
					echo "high";
				} else {
					echo "Error: " . $sql . "<br>" . $conn->error;
				}
			}else{
				$sql = "UPDATE scores SET attempts = attempts + 1 WHERE user_id='".$ss_user_id."' AND reward = '0' AND project_id = '0'";
				if ($conn->query($sql) === TRUE) {
					echo "low";
				} else {
					echo "Error: " . $sql . "<br>" . $conn->error;
				}
			}
		}else{
			$ss_is_high = true;
			// Create new score
			$sql = "INSERT INTO scores (user_id, score, attempts, reward, project_id) 
			VALUES ('".$ss_user_id."', '".$score."', '1', '0', '0')";

			if ($conn->query($sql) === TRUE) {
				echo "new";
			} else {
				echo "Error: " . $sql . "<br>" . $conn->error;
			}
		}
		// Discord webhook — Skull Swap round completed
		$ss_row_res = $conn->query("SELECT score, attempts FROM scores WHERE user_id='".$ss_user_id."' AND reward='0' AND project_id='0'");
		$ss_best = $score; $ss_attempts = 1;
		if($ss_row_res && $ss_row_res->num_rows > 0){ $ss_r = $ss_row_res->fetch_assoc(); $ss_best = $ss_r['score']; $ss_attempts = $ss_r['attempts']; }
		$ss_rank_res = $conn->query("SELECT COUNT(*) + 1 AS rank FROM (SELECT MAX(score) AS best FROM scores WHERE project_id='0' AND reward='0' AND user_id != '".$ss_user_id."' GROUP BY user_id) AS ranked WHERE ranked.best > '".$ss_best."'");
		$ss_rank = 1;
		if($ss_rank_res && $ss_rank_res->num_rows > 0){ $ss_rank = (int)$ss_rank_res->fetch_assoc()['rank']; }
		$ss_rank_sfx = ($ss_rank == 1 ? "st" : ($ss_rank == 2 ? "nd" : ($ss_rank == 3 ? "rd" : "th")));
		$ss_username   = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
		$ss_discord    = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
		$ss_avatar     = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
		$ss_avatar_url = ($ss_discord && $ss_avatar) ? "https://cdn.discordapp.com/avatars/".$ss_discord."/".$ss_avatar.".png" : "";
		$ss_profile    = "https://skulliance.io/staking/profile.php?username=".urlencode($ss_username);
		$ss_mention    = $ss_discord ? "<@".$ss_discord.">" : $ss_username;
		$ss_desc       = $ss_mention." completed a Skull Swap round!".($ss_is_high ? " 🆕 **New High Score!**" : "")."\n\n";
		$ss_desc      .= "🎴 **Round Score:** ".number_format($score)."\n";
		$ss_desc      .= "🏆 **Monthly Best:** ".number_format($ss_best)."\n";
		$ss_desc      .= "🔢 **Attempts This Month:** ".$ss_attempts."\n";
		$ss_desc      .= "📊 **Monthly Rank:** ".$ss_rank.$ss_rank_sfx;
		$ss_author = array("name" => $ss_username, "icon_url" => $ss_avatar_url, "url" => $ss_profile);
		discordmsg("🎴 Skull Swap", $ss_desc, "", "https://skulliance.io/staking/skullswap.php", "skullswap", $ss_avatar_url, "F39C12", $ss_author);
	}else{
		echo "User not logged in. Score cannot be saved.";
	}
}

function getSwapScore($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT score FROM scores WHERE user_id = '".$_SESSION['userData']['user_id']."' AND reward = '0' AND project_id = '0'";
		$result = $conn->query($sql);
	
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				return $row['score'];
			}
		}
	}
}

function resetSwapScores($conn){
	$sql = "UPDATE scores SET reward = '1' WHERE reward = '0' AND project_id = '0'";
	if ($conn->query($sql) === TRUE) {
		//echo "All scores marked as rewarded.";
	} else {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

function resetBossBattles($conn){
	// Distribute all Bounties based on damage dealt
	// Mark all current encounters as rewarded
	if (distributeBounties($conn)) {
	    echo "Bounties distributed successfully.";
	} else {
	    echo "Error distributing bounties.";
	}
	// Mark all encounters as rewarded, even those with no damage dealt that didn't receive a bounty
	$sql = "UPDATE encounters SET reward = '1' WHERE reward = '0'";
	if ($conn->query($sql) === TRUE) {
		//echo "All scores marked as rewarded.";
	} else {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
	// Reset Player Health to NULL
	$sql = "UPDATE health SET health = NULL";
	if ($conn->query($sql) === TRUE) {
		//echo "All scores marked as rewarded.";
	} else {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
	// Reset Boss Health to Max Health
	$sql = "UPDATE bosses SET health = max_health";
	if ($conn->query($sql) === TRUE) {
		//echo "All scores marked as rewarded.";
	} else {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

function distributeBounties($conn) {
    // Step 1: Fetch unrewarded encounters
    $sql = "SELECT e.id, e.user_id, e.boss_id, e.damage_dealt, e.date_created, 
                   b.health, b.max_health AS bounty, b.project_id 
            FROM encounters e 
            INNER JOIN bosses b ON b.id = e.boss_id 
            WHERE e.reward = '0'";
    $result = $conn->query($sql);

    if (!$result) {
        error_log("Query failed: " . $conn->error);
        return false;
    }

    if ($result->num_rows == 0) {
        error_log("No unrewarded encounters found.");
        return true;
    }

    // Step 2: Group encounters by boss_id
    $boss_encounters = [];
    while ($row = $result->fetch_assoc()) {
        $boss_id = $row['boss_id'];
        if (!isset($boss_encounters[$boss_id])) {
            $boss_encounters[$boss_id] = [
                'bounty' => $row['bounty'],
                'health' => $row['health'],
                'project_id' => $row['project_id'],
                'encounters' => []
            ];
        }
        $boss_encounters[$boss_id]['encounters'][] = [
            'id' => $row['id'],
            'user_id' => $row['user_id'],
            'damage_dealt' => $row['damage_dealt'],
            'date_created' => $row['date_created'] ?? '1970-01-01 00:00:00'
        ];
    }

    // Step 3: Process each boss and its encounters
    foreach ($boss_encounters as $boss_id => $data) {
        $max_health = $data['bounty'];
        $health = $data['health'];
        $project_id = $data['project_id'];
        $encounters = $data['encounters'];

        // Step 4: Update each encounter with reward = damage_dealt
        foreach ($encounters as $enc) {
            $encounter_id = $enc['id'];
            $user_id = $enc['user_id'];
            $damage_dealt = $enc['damage_dealt'];

            // Skip if no damage dealt
            if ($damage_dealt <= 0) {
                error_log("No damage dealt for encounter id: $encounter_id, boss_id: $boss_id, skipping");
                continue;
            }

            // Set reward to damage_dealt
            $reward = (string)$damage_dealt; // Treat reward as string
            $update_sql = "UPDATE encounters SET reward = '$reward' WHERE id = " . $conn->real_escape_string($encounter_id);
            if (!$conn->query($update_sql)) {
                error_log("Failed to update reward for encounter id: $encounter_id, boss_id: $boss_id: " . $conn->error);
                continue;
            }
            if ($conn->affected_rows == 0) {
                error_log("No rows updated for encounter id: $encounter_id (boss_id: $boss_id)");
                continue;
            }

            // Apply reward to user balance
            updateBalance($conn, $user_id, $project_id, $damage_dealt);
            logCredit($conn, $user_id, $damage_dealt, $project_id);
            error_log("Successfully distributed bounty for boss_id: $boss_id, encounter_id: $encounter_id, amount: $damage_dealt, user_id: $user_id");
        }
    }

    return true;
}

function resetMonstrocityScores($conn){
	$sql = "UPDATE scores SET reward = '1' WHERE reward = '0' AND project_id = '36'";
	if ($conn->query($sql) === TRUE) {
		//echo "All scores marked as rewarded.";
	} else {
		echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

/* END SKULL SWAP */

/* MONSTROCITY */

function getMonstrocityAssets($conn){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT nfts.id AS id, asset_id, asset_name, policy FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id WHERE user_id = '".$_SESSION['userData']['user_id']."' AND collections.name = 'Monstrocity'";
		$result = $conn->query($sql);
		
		$asset_list = array();
		$asset_list["_asset_list"] = array();
		if ($result->num_rows > 0) {
			$index = 0;
			while($row = $result->fetch_assoc()) {
				$asset_list["_asset_list"][$index] = array($row["policy"], bin2hex($row["asset_name"]));
				$index++;
			}
			return $asset_list;
		}else{
			return false;
		}
	}
}

function saveMonstrocityScore($conn, $user_id, $score, $level) {
  $score = intval($score);
  $level = intval($level);
  $project_id = 36;

  try {
    if (!$conn) throw new Exception("Database connection is null");

    // Check existing score
    $stmt = $conn->prepare("SELECT score, level, attempts FROM scores WHERE user_id = ? AND project_id = ? AND reward = 0");
    $stmt->bind_param("ii", $user_id, $project_id);
    $stmt->execute();
    $stmt->bind_result($stored_score, $stored_level, $stored_attempts);
    $has_result = $stmt->fetch();
    if (!$has_result) {
      $stored_score = 0;
      $stored_level = 0;
      $stored_attempts = 0;
    }
    $stmt->close();

    // Log the comparison for debugging
    error_log("saveMonstrocityScore: user_id=$user_id, level=$level, score=$score, stored_level=$stored_level, stored_score=$stored_score, stored_attempts=$stored_attempts");

    // Save if: (level is higher) OR (level is same AND score is higher)
    $should_save = ($level > $stored_level) || ($level == $stored_level && $score > $stored_score);

    if ($should_save) {
      // Reset attempts if level is less than 28 and was previously higher (indicating a restart)
      $new_attempts = $stored_level > $level && $level < 28 ? 0 : $stored_attempts;
      // Increment attempts only on level 28 completion
      if ($level == 28) $new_attempts += 1;

      // Log the decision
      error_log("saveMonstrocityScore: should_save=true, new_attempts=$new_attempts");

      // Update if exists, insert if not
      if ($has_result) {
        $stmt = $conn->prepare("UPDATE scores SET score = ?, level = ?, attempts = ? WHERE user_id = ? AND project_id = ? AND reward = 0");
        $stmt->bind_param("iiiii", $score, $level, $new_attempts, $user_id, $project_id);
      } else {
        $stmt = $conn->prepare("INSERT INTO scores (user_id, project_id, score, level, attempts, reward, date_created) VALUES (?, ?, ?, ?, ?, 0, NOW())");
        $stmt->bind_param("iiiii", $user_id, $project_id, $score, $level, $new_attempts);
      }
      $stmt->execute();
      $stmt->close();

      return [
        'status' => 'success',
        'message' => 'Score saved',
        'level' => $level,
        'score' => $score,
        'attempts' => $new_attempts
      ];
    }

    // Log the skip decision
    error_log("saveMonstrocityScore: should_save=false, no improvement in level or score");
    return [
      'status' => 'skipped',
      'message' => 'No improvement in level or score',
      'level' => $level,
      'score' => $score,
      'attempts' => $stored_attempts
    ];
  } catch (Exception $e) {
    error_log("saveMonstrocityScore: error=" . $e->getMessage());
    return [
      'status' => 'error',
      'message' => 'Database error: ' . $e->getMessage(),
      'level' => $level,
      'score' => $score,
      'attempts' => isset($stored_attempts) ? $stored_attempts : 0
    ];
  }
}

/* END MONSTROCITY */

/* ============================================================
   REALMS ENHANCEMENT — SOLDIERS, BARRACKS, TOWER, CRYPT,
   MINE, FACTORY, ARMORY
   ============================================================ */

// ── WEAPON & ARMOR LOOKUPS ─────────────────────────────────
function getAllWeapons($conn) {
	$result = $conn->query("SELECT id, name, level FROM weapons ORDER BY level ASC");
	$weapons = array();
	while ($row = $result->fetch_assoc()) $weapons[$row['id']] = $row;
	return $weapons;
}

function getAllArmor($conn) {
	$result = $conn->query("SELECT id, name, level FROM armor ORDER BY level ASC");
	$armor = array();
	while ($row = $result->fetch_assoc()) $armor[$row['id']] = $row;
	return $armor;
}

// ── SOLDIER SLOT HELPERS ───────────────────────────────────
// Deployment cap = barracks level × 10 (max 100)
function getDeploymentCap($conn, $realm_id) {
	$level = getRealmLocationLevel($conn, $realm_id, 4); // Barracks = location 4
	return min(100, intval($level) * 10);
}

// Count of alive soldiers for this realm (all locations, not dead)
function getTotalSoldierCount($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$result = $conn->query("SELECT COUNT(*) AS cnt FROM soldiers WHERE realm_id = $realm_id AND dead IS NULL AND active = 1");
	$row = $result->fetch_assoc();
	return intval($row['cnt']);
}

// Weighted slot cost across all alive soldiers (partner NFTs cost 2 slots, core cost 1)
function getTotalSoldierSlotCost($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$result = $conn->query("SELECT COALESCE(SUM(CASE WHEN collections.project_id > 7 AND collections.project_id != 15 THEN 2 ELSE 1 END), 0) AS slot_cost FROM soldiers INNER JOIN nfts ON nfts.id = soldiers.nft_id INNER JOIN collections ON collections.id = nfts.collection_id WHERE soldiers.realm_id = $realm_id AND soldiers.dead IS NULL AND soldiers.active = 1");
	$row = $result->fetch_assoc();
	return intval($row['slot_cost']);
}

// Count of deployed soldiers (tower + raid, alive)
function getDeployedCount($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$result = $conn->query("SELECT COUNT(*) AS cnt FROM soldiers WHERE realm_id = $realm_id AND location IN(2,3) AND dead IS NULL AND active = 1");
	$row = $result->fetch_assoc();
	return intval($row['cnt']);
}

// NFT slot cost: partner = 2, core = 1
function getSoldierSlotCost($conn, $nft_id) {
	$nft_id = intval($nft_id);
	$result = $conn->query("SELECT projects.id AS project_id FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id WHERE nfts.id = $nft_id");
	if ($result && $result->num_rows > 0) {
		$row = $result->fetch_assoc();
		$pid = intval($row['project_id']);
		return ($pid > 7 && $pid != 15) ? 2 : 1;
	}
	return 1;
}

// Total slot cost of all alive soldiers for realm
function getTotalSlotCost($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$result = $conn->query("SELECT soldiers.nft_id, projects.id AS project_id FROM soldiers INNER JOIN nfts ON nfts.id = soldiers.nft_id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id WHERE soldiers.realm_id = $realm_id AND soldiers.dead IS NULL AND soldiers.active = 1");
	$total = 0;
	while ($row = $result->fetch_assoc()) {
		$pid = intval($row['project_id']);
		$total += ($pid > 7 && $pid != 15) ? 2 : 1;
	}
	return $total;
}

// Returns soldier IDs that are over the deployment cap (reserved status)
// Priority: partner NFTs reserved first, then lowest gear score, then oldest
function getReservedSoldierIds($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$cap = getDeploymentCap($conn, $realm_id);
	$sql = "SELECT soldiers.id AS soldier_id,
	               projects.id AS project_id,
	               COALESCE(weapons.level,0) + COALESCE(armor.level,0) AS gear_score
	        FROM soldiers
	        INNER JOIN nfts ON nfts.id = soldiers.nft_id
	        INNER JOIN collections ON collections.id = nfts.collection_id
	        INNER JOIN projects ON projects.id = collections.project_id
	        LEFT JOIN weapons ON weapons.id = soldiers.weapon_id
	        LEFT JOIN armor ON armor.id = soldiers.armor_id
	        WHERE soldiers.realm_id = $realm_id
	          AND soldiers.dead IS NULL
	          AND soldiers.active = 1
	        ORDER BY (projects.id > 7 AND projects.id != 15) ASC,
	                 gear_score DESC,
	                 soldiers.date_created ASC";
	$result = $conn->query($sql);
	$reserved_ids = array();
	$used = 0;
	while ($row = $result->fetch_assoc()) {
		$is_partner = (intval($row['project_id']) > 7 && intval($row['project_id']) != 15);
		$cost = $is_partner ? 2 : 1;
		if ($used + $cost <= $cap) {
			$used += $cost;
		} else {
			$reserved_ids[] = intval($row['soldier_id']);
		}
	}
	return $reserved_ids;
}

// ── PORTAL ─────────────────────────────────────────────────
function getPortalReport($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$portal_level  = intval(getRealmLocationLevel($conn, $realm_id, 1));
	$deployed      = getDeployedCount($conn, $realm_id);
	$cap           = getDeploymentCap($conn, $realm_id);
	$result = $conn->query("SELECT COUNT(*) AS cnt FROM raids WHERE offense_id = $realm_id AND outcome = '0'");
	$row = $result->fetch_assoc();
	$active_raids  = intval($row['cnt']);
	return array(
		'portal_level'  => $portal_level,
		'raids_allowed' => $portal_level,
		'active_raids'  => $active_raids,
		'deployed'      => $deployed,
		'cap'           => $cap,
	);
}

// ── BARRACKS ───────────────────────────────────────────────
// Training duration: (11 - level) days. Level 0 = no training. Fast Forward halves it.
function getBarracksTrainingHours($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$level = intval(getRealmLocationLevel($conn, $realm_id, 4));
	if ($level == 0) return 0;
	$hours = (11 - $level) * 24;
	$ff = $conn->query("SELECT rlc.id FROM realms_locations_consumables rlc INNER JOIN realms_locations rl ON rl.id = rlc.realm_location_id WHERE rl.realm_id = $realm_id AND rl.location_id = 4 AND rlc.consumable_id = 5 AND rlc.raid_id = 0 LIMIT 1");
	if ($ff && $ff->num_rows > 0) $hours = (int)ceil($hours / 2);
	return $hours;
}

// Get all soldiers for a realm with NFT info and training status
function getBarracksSoldiers($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$training_hours = getBarracksTrainingHours($conn, $realm_id);
	$sql = "SELECT soldiers.id AS soldier_id, soldiers.nft_id, soldiers.location, soldiers.trained, soldiers.dead,
	               soldiers.weapon_id, soldiers.armor_id, soldiers.date_created,
	               nfts.name AS nft_name, nfts.ipfs, nfts.collection_id,
	               collections.rate, projects.id AS project_id,
	               weapons.name AS weapon_name, weapons.level AS weapon_level,
	               armor.name AS armor_name, armor.level AS armor_level,
	               DATE_ADD(soldiers.date_created, INTERVAL $training_hours HOUR) AS ready_at
	        FROM soldiers
	        INNER JOIN nfts ON nfts.id = soldiers.nft_id
	        INNER JOIN collections ON collections.id = nfts.collection_id
	        INNER JOIN projects ON projects.id = collections.project_id
	        LEFT JOIN weapons ON weapons.id = soldiers.weapon_id
	        LEFT JOIN armor ON armor.id = soldiers.armor_id
	        WHERE soldiers.realm_id = $realm_id AND soldiers.dead IS NULL AND soldiers.active = 1
	        ORDER BY COALESCE(weapons.level,0) + COALESCE(armor.level,0) DESC, soldiers.date_created ASC";
	$result = $conn->query($sql);
	$soldiers = array();
	while ($row = $result->fetch_assoc()) $soldiers[] = $row;
	return $soldiers;
}

// Enlist an NFT as a soldier
function enlistSoldier($conn, $realm_id, $nft_id) {
	if (!isset($_SESSION['userData']['user_id'])) return false;
	$realm_id = intval($realm_id);
	$nft_id   = intval($nft_id);
	$user_id  = intval($_SESSION['userData']['user_id']);

	// Verify the NFT belongs to this user
	$check = $conn->query("SELECT id FROM nfts WHERE id = $nft_id AND user_id = $user_id LIMIT 1");
	if (!$check || $check->num_rows == 0) return false;

	// Verify not already an active soldier
	$dup = $conn->query("SELECT id FROM soldiers WHERE nft_id = $nft_id AND dead IS NULL AND active = 1 LIMIT 1");
	if ($dup && $dup->num_rows > 0) return false;

	// Enforce deployment cap
	$cap        = getDeploymentCap($conn, $realm_id);
	$used       = getTotalSlotCost($conn, $realm_id);
	$nft_cost   = getSoldierSlotCost($conn, $nft_id);
	if ($used + $nft_cost > $cap) return false;

	// Reactivate a previously discharged soldier record if one exists
	$inactive = $conn->query("SELECT id FROM soldiers WHERE nft_id = $nft_id AND realm_id = $realm_id AND dead IS NULL AND active = 0 LIMIT 1");
	if ($inactive && $inactive->num_rows > 0) {
		$inactive_id = intval($inactive->fetch_assoc()['id']);
		$conn->query("UPDATE soldiers SET active = 1, location = 1 WHERE id = $inactive_id LIMIT 1");
		return true;
	}

	$sql = "INSERT INTO soldiers (nft_id, realm_id, location, armor_id, weapon_id, trained, date_created, active) VALUES ($nft_id, $realm_id, 1, 0, 0, 0, NOW(), 1)";
	return $conn->query($sql);
}

// Remove a soldier (must belong to this realm)
function removeSoldier($conn, $soldier_id, $realm_id) {
	$soldier_id = intval($soldier_id);
	$realm_id   = intval($realm_id);
	// Only allow removing reserve soldiers (location=1) that are trained and alive
	$conn->query("DELETE FROM soldiers WHERE id = $soldier_id AND realm_id = $realm_id AND location = 1 AND dead IS NULL");
}

// Discharge a soldier: deactivates them, returns gear to inventory, removes from raids_soldiers if on raid.
// Not allowed if dead (crypt).
function dischargeSoldier($conn, $soldier_id, $realm_id) {
	$soldier_id = intval($soldier_id);
	$realm_id   = intval($realm_id);
	$r = $conn->query("SELECT user_id FROM realms WHERE id = $realm_id LIMIT 1");
	if (!$r || $r->num_rows == 0) return false;
	$user_id = intval($r->fetch_assoc()['user_id']);
	// Must be alive and active
	$check = $conn->query("SELECT id, weapon_id, armor_id, location FROM soldiers WHERE id = $soldier_id AND realm_id = $realm_id AND dead IS NULL AND active = 1 LIMIT 1");
	if (!$check || $check->num_rows == 0) return false;
	$s = $check->fetch_assoc();
	// Return gear to inventory
	if (intval($s['weapon_id']) > 0) updateGear($conn, $user_id, 'weapon', intval($s['weapon_id']), 1);
	if (intval($s['armor_id'])  > 0) updateGear($conn, $user_id, 'armor',  intval($s['armor_id']),  1);
	// Pull from raids_soldiers if currently on a raid
	if (intval($s['location']) == 3) $conn->query("DELETE FROM raids_soldiers WHERE soldier_id = $soldier_id");
	// Deactivate: clear gear, reset to reserve, mark inactive
	$conn->query("UPDATE soldiers SET active = 0, location = 1, weapon_id = 0, armor_id = 0 WHERE id = $soldier_id AND realm_id = $realm_id LIMIT 1");
	return true;
}

function dischargeAllSoldiers($conn, $realm_id, $location = null) {
	$realm_id = intval($realm_id);
	$r = $conn->query("SELECT user_id FROM realms WHERE id = $realm_id LIMIT 1");
	if (!$r || $r->num_rows == 0) return 0;
	$user_id = intval($r->fetch_assoc()['user_id']);
	$loc_filter = ($location !== null) ? " AND location = " . intval($location) : "";
	$result = $conn->query("SELECT id, weapon_id, armor_id, location FROM soldiers WHERE realm_id = $realm_id AND dead IS NULL AND active = 1" . $loc_filter);
	$count = 0;
	while ($s = $result->fetch_assoc()) {
		$sid = intval($s['id']);
		if (intval($s['weapon_id']) > 0) updateGear($conn, $user_id, 'weapon', intval($s['weapon_id']), 1);
		if (intval($s['armor_id'])  > 0) updateGear($conn, $user_id, 'armor',  intval($s['armor_id']),  1);
		if (intval($s['location']) == 3) $conn->query("DELETE FROM raids_soldiers WHERE soldier_id = $sid");
		$conn->query("UPDATE soldiers SET active = 0, location = 1, weapon_id = 0, armor_id = 0 WHERE id = $sid AND realm_id = $realm_id LIMIT 1");
		$count++;
	}
	return $count;
}

// Get distinct projects and collections the user owns NFTs in, for dropdown population
function getUserNFTProjectTree($conn) {
	if (!isset($_SESSION['userData']['user_id'])) return array();
	$user_id = intval($_SESSION['userData']['user_id']);
	$sql = "SELECT DISTINCT projects.id AS project_id, projects.name AS project_name,
	               collections.id AS collection_id, collections.name AS collection_name
	        FROM nfts
	        INNER JOIN collections ON collections.id = nfts.collection_id
	        INNER JOIN projects ON projects.id = collections.project_id
	        WHERE nfts.user_id = $user_id
	          AND projects.id != 15
	        ORDER BY projects.id ASC, collections.name ASC";
	$result = $conn->query($sql);
	$core = array();
	$partner = array();
	while ($row = $result->fetch_assoc()) {
		$pid     = $row['project_id'];
		$is_core = ($pid >= 1 && $pid <= 7);
		if ($is_core) {
			if (!isset($core[$pid])) $core[$pid] = array('name' => $row['project_name'], 'group' => 'core', 'collections' => array());
			$core[$pid]['collections'][] = array('id' => $row['collection_id'], 'name' => $row['collection_name']);
		} else {
			if (!isset($partner[$pid])) $partner[$pid] = array('name' => $row['project_name'], 'group' => 'partner', 'collections' => array());
			$partner[$pid]['collections'][] = array('id' => $row['collection_id'], 'name' => $row['collection_name']);
		}
	}
	uasort($partner, function($a, $b) { return strcmp($a['name'], $b['name']); });
	return $core + $partner;
}

// Get eligible NFTs for enlistment: owned by user, not already a soldier, not on mission
function getEligibleEnlistNFTs($conn, $realm_id, $project_id=0, $collection_id=0) {
	if (!isset($_SESSION['userData']['user_id'])) return array();
	$user_id       = intval($_SESSION['userData']['user_id']);
	$project_id    = intval($project_id);
	$collection_id = intval($collection_id);
	$where = "nfts.user_id = $user_id
	          AND nfts.id NOT IN (SELECT nft_id FROM soldiers WHERE dead IS NULL AND active = 1)";
	if ($project_id)    $where .= " AND projects.id = $project_id";
	if ($collection_id) $where .= " AND nfts.collection_id = $collection_id";
	$sql = "SELECT nfts.id AS nft_id, nfts.name AS nft_name, nfts.ipfs, nfts.collection_id,
	               collections.name AS collection_name,
	               projects.id AS project_id, projects.name AS project_name
	        FROM nfts
	        INNER JOIN collections ON collections.id = nfts.collection_id
	        INNER JOIN projects ON projects.id = collections.project_id
	        WHERE $where
	        ORDER BY collections.name ASC, nfts.name ASC";
	$result = $conn->query($sql);
	$nfts = array();
	while ($row = $result->fetch_assoc()) $nfts[] = $row;
	return $nfts;
}

/// Lazy training: mark trained=1 for soldiers in one realm that have completed training (called on modal view)
function updateSoldierTraining($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$hours = getBarracksTrainingHours($conn, $realm_id);
	if ($hours == 0) return; // level 0, training not possible
	$conn->query("UPDATE soldiers SET trained = 1 WHERE realm_id = $realm_id AND trained = 0 AND dead IS NULL AND DATE_ADD(date_created, INTERVAL $hours HOUR) <= NOW()");
}

// Deactivate soldiers whose NFT is no longer owned by the realm's user (called after verifyNFTs).
// Returns equipped gear to the realm owner's inventory, then soft-deletes the soldier (active=0).
// The soldier record is preserved so the original owner can re-enlist the same NFT if it returns.
function verifyRealmSoldiers($conn) {
	$sql = "SELECT soldiers.id, soldiers.weapon_id, soldiers.armor_id, realms.user_id AS realm_user_id
	        FROM soldiers
	        INNER JOIN realms ON realms.id = soldiers.realm_id
	        INNER JOIN nfts ON nfts.id = soldiers.nft_id
	        WHERE nfts.user_id != realms.user_id AND soldiers.active = 1";
	$result = $conn->query($sql);
	while ($row = $result->fetch_assoc()) {
		$sid     = intval($row['id']);
		$user_id = intval($row['realm_user_id']);
		if (intval($row['weapon_id']) > 0) updateGear($conn, $user_id, 'weapon', intval($row['weapon_id']), 1);
		if (intval($row['armor_id'])  > 0) updateGear($conn, $user_id, 'armor',  intval($row['armor_id']),  1);
		$conn->query("UPDATE soldiers SET active = 0, location = 1, weapon_id = 0, armor_id = 0 WHERE id = $sid LIMIT 1");
	}
}

// ── CRYPT ──────────────────────────────────────────────────
function getCryptResurrectionDays($conn, $realm_id) {
	$realm_id    = intval($realm_id);
	$crypt_level = intval(getRealmLocationLevel($conn, $realm_id, 6));
	$res_days    = max(1, 11 - $crypt_level);
	$ff = $conn->query("SELECT rlc.id FROM realms_locations_consumables rlc INNER JOIN realms_locations rl ON rl.id = rlc.realm_location_id WHERE rl.realm_id = $realm_id AND rl.location_id = 6 AND rlc.consumable_id = 5 AND rlc.raid_id = 0 LIMIT 1");
	if ($ff && $ff->num_rows > 0) $res_days = max(1, (int)ceil($res_days / 2));
	return $res_days;
}

function getCryptSoldiers($conn, $realm_id) {
	$realm_id     = intval($realm_id);
	$res_days     = getCryptResurrectionDays($conn, $realm_id);
	$sql = "SELECT soldiers.id AS soldier_id, soldiers.nft_id, soldiers.dead, soldiers.weapon_id, soldiers.armor_id,
	               nfts.name AS nft_name, nfts.ipfs, nfts.collection_id, projects.id AS project_id,
	               DATE_ADD(soldiers.dead, INTERVAL $res_days DAY) AS ready_at,
	               (DATE_ADD(soldiers.dead, INTERVAL $res_days DAY) <= NOW()) AS eligible,
	               weapons.name AS weapon_name, weapons.level AS weapon_level,
	               armor.name AS armor_name, armor.level AS armor_level
	        FROM soldiers
	        INNER JOIN nfts ON nfts.id = soldiers.nft_id
	        INNER JOIN collections ON collections.id = nfts.collection_id
	        INNER JOIN projects ON projects.id = collections.project_id
	        LEFT JOIN weapons ON weapons.id = soldiers.weapon_id
	        LEFT JOIN armor ON armor.id = soldiers.armor_id
	        WHERE soldiers.realm_id = $realm_id AND soldiers.dead IS NOT NULL AND soldiers.active = 1
	        ORDER BY eligible DESC, COALESCE(weapons.level,0) + COALESCE(armor.level,0) DESC, soldiers.dead ASC";
	$result = $conn->query($sql);
	$soldiers = array();
	while ($row = $result->fetch_assoc()) $soldiers[] = $row;
	return $soldiers;
}

// Resurrect all eligible soldiers (dead timer expired)
function resurrectSoldiers($conn, $realm_id) {
	$realm_id    = intval($realm_id);
	$res_days    = getCryptResurrectionDays($conn, $realm_id);
	// Set location=1 (reserve), trained=1 (already trained), clear dead timestamp
	$conn->query("UPDATE soldiers SET dead = NULL, location = 1, trained = 1 WHERE realm_id = $realm_id AND dead IS NOT NULL AND active = 1 AND DATE_ADD(dead, INTERVAL $res_days DAY) <= NOW()");
}

// ── TOWER ──────────────────────────────────────────────────
function getTowerGarrison($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$sql = "SELECT soldiers.id AS soldier_id, soldiers.nft_id, soldiers.weapon_id, soldiers.armor_id,
	               nfts.name AS nft_name, nfts.ipfs, nfts.collection_id, projects.id AS project_id,
	               weapons.name AS weapon_name, weapons.level AS weapon_level,
	               armor.name AS armor_name, armor.level AS armor_level
	        FROM soldiers
	        INNER JOIN nfts ON nfts.id = soldiers.nft_id
	        INNER JOIN collections ON collections.id = nfts.collection_id
	        INNER JOIN projects ON projects.id = collections.project_id
	        LEFT JOIN weapons ON weapons.id = soldiers.weapon_id
	        LEFT JOIN armor ON armor.id = soldiers.armor_id
	        WHERE soldiers.realm_id = $realm_id AND soldiers.location = 2 AND soldiers.dead IS NULL AND soldiers.active = 1
	        ORDER BY COALESCE(weapons.level,0) + COALESCE(armor.level,0) DESC, soldiers.id ASC";
	$result = $conn->query($sql);
	$garrison = array();
	while ($row = $result->fetch_assoc()) $garrison[] = $row;
	return $garrison;
}

function assignToTower($conn, $soldier_id, $realm_id) {
	$soldier_id = intval($soldier_id);
	$realm_id   = intval($realm_id);
	// Max 10 in tower
	if (count(getTowerGarrison($conn, $realm_id)) >= 10) return false;
	// Reserved soldiers may not support tower
	$reserved = getReservedSoldierIds($conn, $realm_id);
	if (in_array($soldier_id, $reserved)) return false;
	// Must be trained, in reserve, and alive
	$conn->query("UPDATE soldiers SET location = 2 WHERE id = $soldier_id AND realm_id = $realm_id AND location = 1 AND trained = 1 AND dead IS NULL LIMIT 1");
	return true;
}

function removeFromTower($conn, $soldier_id, $realm_id) {
	$soldier_id = intval($soldier_id);
	$realm_id   = intval($realm_id);
	$conn->query("UPDATE soldiers SET location = 1 WHERE id = $soldier_id AND realm_id = $realm_id AND location = 2 AND dead IS NULL LIMIT 1");
}

function equipSoldierWeapon($conn, $soldier_id, $realm_id, $weapon_id) {
	$soldier_id = intval($soldier_id);
	$realm_id   = intval($realm_id);
	$weapon_id  = intval($weapon_id);
	$conn->query("UPDATE soldiers SET weapon_id = $weapon_id WHERE id = $soldier_id AND realm_id = $realm_id AND dead IS NULL LIMIT 1");
}

function equipSoldierArmor($conn, $soldier_id, $realm_id, $armor_id) {
	$soldier_id = intval($soldier_id);
	$realm_id   = intval($realm_id);
	$armor_id   = intval($armor_id);
	$conn->query("UPDATE soldiers SET armor_id = $armor_id WHERE id = $soldier_id AND realm_id = $realm_id AND dead IS NULL LIMIT 1");
}

// ── MINE ───────────────────────────────────────────────────
// Mine rate: mine_level × 100 CARBON per day
function getMineInfo($conn, $realm_id) {
	$realm_id   = intval($realm_id);
	$mine_level = intval(getRealmLocationLevel($conn, $realm_id, 7));
	$nightly    = $mine_level * 100;
	$r = $conn->query("SELECT user_id FROM realms WHERE id = $realm_id LIMIT 1");
	$carbon = 0;
	if ($r && $r->num_rows > 0) {
		$carbon = intval(getCurrentBalance($conn, $r->fetch_assoc()['user_id'], 15));
	}
	return array('mine_level' => $mine_level, 'nightly' => $nightly, 'carbon' => $carbon);
}

// ── FACTORY ────────────────────────────────────────────────
function getFactoryInfo($conn, $realm_id) {
	$realm_id      = intval($realm_id);
	$factory_level = intval(getRealmLocationLevel($conn, $realm_id, 5));
	return array('factory_level' => $factory_level, 'drops_per_night' => $factory_level);
}

// ── ARMORY ─────────────────────────────────────────────────
// Gear drops_per_night = armory_level, capped at 10
function getArmoryInfo($conn, $realm_id) {
	$realm_id     = intval($realm_id);
	$armory_level = intval(getRealmLocationLevel($conn, $realm_id, 2));
	$drops        = min(10, $armory_level);
	$all_soldiers = getBarracksSoldiers($conn, $realm_id);
	$soldiers     = array_values(array_filter($all_soldiers, function($s) { return intval($s['trained']) == 1 && intval($s['location']) == 1; }));
	$r = $conn->query("SELECT user_id FROM realms WHERE id = $realm_id LIMIT 1");
	$inventory    = array();
	if ($r && $r->num_rows > 0) $inventory = getGearInventory($conn, intval($r->fetch_assoc()['user_id']));
	return array('armory_level' => $armory_level, 'drops_per_night' => $drops, 'soldiers' => $soldiers, 'inventory' => $inventory);
}

// Roll a weapon/armor tier (1-10) based on armory level
function rollArmoryTier($level) {
	$roll = rand(1, 100);
	if ($level <= 2) {
		if ($roll <= 50) return 1;
		if ($roll <= 85) return 2;
		return 3;
	} elseif ($level <= 4) {
		if ($roll <= 35) return 1;
		if ($roll <= 60) return 2;
		if ($roll <= 80) return 3;
		if ($roll <= 92) return 4;
		return 5;
	} elseif ($level <= 6) {
		if ($roll <= 20) return 1;
		if ($roll <= 38) return 2;
		if ($roll <= 55) return 3;
		if ($roll <= 68) return 4;
		if ($roll <= 79) return 5;
		if ($roll <= 88) return 6;
		if ($roll <= 95) return 7;
		return 8;
	} elseif ($level <= 8) {
		if ($roll <= 12) return 1;
		if ($roll <= 26) return 2;
		if ($roll <= 40) return 3;
		if ($roll <= 53) return 4;
		if ($roll <= 64) return 5;
		if ($roll <= 74) return 6;
		if ($roll <= 83) return 7;
		if ($roll <= 91) return 8;
		if ($roll <= 96) return 9;
		return 10;
	} else { // level 9-10
		if ($roll <= 8)  return 1;
		if ($roll <= 18) return 2;
		if ($roll <= 30) return 3;
		if ($roll <= 42) return 4;
		if ($roll <= 54) return 5;
		if ($roll <= 65) return 6;
		if ($roll <= 75) return 7;
		if ($roll <= 84) return 8;
		if ($roll <= 93) return 9;
		return 10;
	}
}

// ── GEAR INVENTORY ─────────────────────────────────────────
function getCurrentGear($conn, $user_id, $type, $item_id) {
	$user_id = intval($user_id);
	$item_id = intval($item_id);
	$type    = mysqli_real_escape_string($conn, $type);
	$result  = $conn->query("SELECT COALESCE(SUM(quantity), 0) AS total FROM gear WHERE user_id = $user_id AND type = '$type' AND item_id = $item_id");
	if ($result) return intval($result->fetch_assoc()['total']);
	return 0;
}

function updateGear($conn, $user_id, $type, $item_id, $delta) {
	$user_id = intval($user_id);
	$item_id = intval($item_id);
	$delta   = intval($delta);
	$type    = mysqli_real_escape_string($conn, $type);
	// Collapse duplicate rows: sum all quantities into the lowest-id row, delete the rest
	$agg = $conn->query("SELECT MIN(id) AS keep_id, COALESCE(SUM(quantity), 0) AS total FROM gear WHERE user_id = $user_id AND type = '$type' AND item_id = $item_id");
	if ($agg && ($row = $agg->fetch_assoc()) && $row['keep_id'] !== null) {
		$new_qty = max(0, intval($row['total']) + $delta);
		$keep_id = intval($row['keep_id']);
		$conn->query("UPDATE gear SET quantity = $new_qty WHERE id = $keep_id");
		$conn->query("DELETE FROM gear WHERE user_id = $user_id AND type = '$type' AND item_id = $item_id AND id != $keep_id");
	} else if ($delta > 0) {
		$conn->query("INSERT INTO gear (user_id, type, item_id, quantity) VALUES ($user_id, '$type', $item_id, $delta)");
	}
}

function getGearInventory($conn, $user_id) {
	$user_id = intval($user_id);
	$result  = $conn->query("
		SELECT g.type, g.item_id, g.quantity,
		       w.name AS weapon_name, w.level AS weapon_level,
		       a.name AS armor_name, a.level AS armor_level
		FROM gear g
		LEFT JOIN weapons w ON g.type = 'weapon' AND w.id = g.item_id
		LEFT JOIN armor a ON g.type = 'armor' AND a.id = g.item_id
		WHERE g.user_id = $user_id AND g.quantity > 0
		ORDER BY g.type ASC, COALESCE(w.level, a.level) DESC
	");
	$inventory = array();
	if ($result) while ($row = $result->fetch_assoc()) $inventory[] = $row;
	return $inventory;
}

// Equip a gear item from inventory to a soldier; returns current gear to inventory
// Equip gear from inventory to a reserve (location=1) soldier; returns displaced gear to inventory
function equipGear($conn, $soldier_id, $realm_id, $item_id, $is_weapon) {
	$soldier_id = intval($soldier_id);
	$realm_id   = intval($realm_id);
	$item_id    = intval($item_id);
	$r = $conn->query("SELECT user_id FROM realms WHERE id = $realm_id LIMIT 1");
	if (!$r || $r->num_rows == 0) return false;
	$user_id = intval($r->fetch_assoc()['user_id']);
	$type    = $is_weapon ? 'weapon' : 'armor';
	$col     = $is_weapon ? 'weapon_id' : 'armor_id';
	if (getCurrentGear($conn, $user_id, $type, $item_id) < 1) return false;
	// Only allow equipping to reserve (barracks) soldiers
	$s = $conn->query("SELECT $col FROM soldiers WHERE id = $soldier_id AND realm_id = $realm_id AND location = 1 AND dead IS NULL AND active = 1 LIMIT 1");
	if (!$s || $s->num_rows == 0) return false;
	$current_gear_id = intval($s->fetch_assoc()[$col]);
	if ($current_gear_id > 0) updateGear($conn, $user_id, $type, $current_gear_id, 1);
	updateGear($conn, $user_id, $type, $item_id, -1);
	$conn->query("UPDATE soldiers SET $col = $item_id WHERE id = $soldier_id AND realm_id = $realm_id LIMIT 1");
	return true;
}

// Unequip gear from a reserve (location=1) soldier and return it to inventory
function unequipGear($conn, $soldier_id, $realm_id, $is_weapon) {
	$soldier_id = intval($soldier_id);
	$realm_id   = intval($realm_id);
	$r = $conn->query("SELECT user_id FROM realms WHERE id = $realm_id LIMIT 1");
	if (!$r || $r->num_rows == 0) return false;
	$user_id = intval($r->fetch_assoc()['user_id']);
	$type    = $is_weapon ? 'weapon' : 'armor';
	$col     = $is_weapon ? 'weapon_id' : 'armor_id';
	$s = $conn->query("SELECT $col FROM soldiers WHERE id = $soldier_id AND realm_id = $realm_id AND location = 1 AND dead IS NULL AND active = 1 LIMIT 1");
	if (!$s || $s->num_rows == 0) return false;
	$gear_id = intval($s->fetch_assoc()[$col]);
	if ($gear_id == 0) return false;
	updateGear($conn, $user_id, $type, $gear_id, 1);
	$conn->query("UPDATE soldiers SET $col = NULL WHERE id = $soldier_id AND realm_id = $realm_id LIMIT 1");
	return true;
}

// Auto-equip inventory gear to reserve soldiers, best gear first, upgrading weakest slots
function autoEquipReserve($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$r = $conn->query("SELECT user_id FROM realms WHERE id = $realm_id LIMIT 1");
	if (!$r || $r->num_rows == 0) return 0;
	$user_id = intval($r->fetch_assoc()['user_id']);
	$equipped = 0;
	$stripped = 0;
	foreach (array('weapon', 'armor') as $type) {
		$col   = $type === 'weapon' ? 'weapon_id' : 'armor_id';
		$table = $type === 'weapon' ? 'weapons' : 'armor';
		// Snapshot inventory best-to-worst
		$inv_result = $conn->query("SELECT g.item_id, $table.level FROM gear g INNER JOIN $table ON $table.id = g.item_id WHERE g.user_id = $user_id AND g.type = '$type' AND g.quantity > 0 ORDER BY $table.level DESC");
		if (!$inv_result) continue;
		$inv_items = array();
		while ($row = $inv_result->fetch_assoc()) $inv_items[] = $row;
		foreach ($inv_items as $gear) {
			$gear_id    = intval($gear['item_id']);
			$gear_level = intval($gear['level']);
			// Equip as many copies as available to trained soldiers that would benefit
			while (getCurrentGear($conn, $user_id, $type, $gear_id) > 0) {
				$target = $conn->query("SELECT soldiers.id AS soldier_id, COALESCE($table.level, 0) AS current_level FROM soldiers LEFT JOIN $table ON $table.id = soldiers.$col WHERE soldiers.realm_id = $realm_id AND soldiers.location = 1 AND soldiers.trained = 1 AND soldiers.dead IS NULL AND soldiers.active = 1 AND COALESCE($table.level, 0) < $gear_level ORDER BY COALESCE($table.level, 0) ASC, soldiers.date_created ASC LIMIT 1");
				if (!$target || $target->num_rows == 0) break;
				$trow = $target->fetch_assoc();
				if (equipGear($conn, intval($trow['soldier_id']), $realm_id, $gear_id, $type === 'weapon')) {
					$equipped++;
					if (intval($trow['current_level']) > 0) $stripped++;
				}
			}
		}
	}
	return array('equipped' => $equipped, 'stripped' => $stripped);
}

// ── REALM LOG ──────────────────────────────────────────────
// Nightly: write CARBON generation to realms_logs for all realms
function processMineRewards($conn) {
	$result = $conn->query("SELECT id FROM realms");
	while ($row = $result->fetch_assoc()) {
		$realm_id   = intval($row['id']);
		$mine_level = intval(getRealmLocationLevel($conn, $realm_id, 7));
		if ($mine_level == 0) continue;
		$amount = $mine_level * 100;
		$conn->query("INSERT INTO realms_logs (realm_id, type, item_id, quantity, claimed) VALUES ($realm_id, 'carbon', 0, $amount, 0)");
	}
}

// Factory drop odds scale linearly from DB baseline (level 1) toward level 10 targets.
// Rare items gain pp; Random Reward (worst item) absorbs all reductions.
// Total always sums to 100.
function getFactoryOdds($factory_level) {
	$level = max(1, min(10, intval($factory_level)));
	$t     = ($level - 1) / 9.0; // 0.0 at level 1, 1.0 at level 10
	$id1 = (int) round(5  + $t * 10); // 5%  → 15%
	$id6 = (int) round(5  + $t * 10); // 5%  → 15%
	$id2 = (int) round(10 + $t * 5);  // 10% → 15%
	$id4 = (int) round(20 - $t * 5);  // 20% → 15%
	$id5 = (int) round(20 - $t * 5);  // 20% → 15%
	$id3 = 15;
	$id7 = 100 - ($id1 + $id6 + $id2 + $id4 + $id5 + $id3); // 25% → 10%, absorbs rounding
	return array(7=>$id7, 4=>$id4, 5=>$id5, 3=>$id3, 2=>$id2, 1=>$id1, 6=>$id6);
}

// Nightly: write consumable drops to realms_logs for all realms using level-based rarity rates
function processFactoryDrops($conn) {
	$result = $conn->query("SELECT id FROM realms");
	while ($row = $result->fetch_assoc()) {
		$realm_id      = intval($row['id']);
		$factory_level = intval(getRealmLocationLevel($conn, $realm_id, 5));
		if ($factory_level == 0) continue;
		$odds   = getFactoryOdds($factory_level);
		$ranges = array();
		$cursor = 1;
		foreach ($odds as $cid => $pct) {
			$ranges[$cid] = array($cursor, $cursor + $pct - 1);
			$cursor += $pct;
		}
		$drops = array();
		for ($i = 0; $i < $factory_level; $i++) {
			$random = rand(1, 100);
			foreach ($ranges as $cid => $range) {
				if ($random >= $range[0] && $random <= $range[1]) {
					$drops[$cid] = ($drops[$cid] ?? 0) + 1;
					break;
				}
			}
		}
		foreach ($drops as $consumable_id => $qty) {
			$cid = intval($consumable_id);
			$conn->query("INSERT INTO realms_logs (realm_id, type, item_id, quantity, claimed) VALUES ($realm_id, 'consumable', $cid, $qty, 0)");
		}
	}
}

// Nightly: write gear drops to realms_logs for all realms
function processArmoryDrops($conn) {
	$result = $conn->query("SELECT id FROM realms");
	while ($row = $result->fetch_assoc()) {
		$realm_id     = intval($row['id']);
		$armory_level = intval(getRealmLocationLevel($conn, $realm_id, 2));
		if ($armory_level == 0) continue;
		$drops_per_night = min(10, $armory_level);
		for ($i = 0; $i < $drops_per_night; $i++) {
			$is_weapon = ($i % 2 == 0);
			$tier = rollArmoryTier($armory_level);
			$table = $is_weapon ? 'weapons' : 'armor';
			$type  = $is_weapon ? 'weapon' : 'armor';
			$r = $conn->query("SELECT id FROM $table WHERE level = $tier LIMIT 1");
			if (!$r || $r->num_rows == 0) continue;
			$gear_id = intval($r->fetch_assoc()['id']);
			$conn->query("INSERT INTO realms_logs (realm_id, type, item_id, quantity, claimed) VALUES ($realm_id, '$type', $gear_id, 1, 0)");
		}
	}
}

// Get all unclaimed log entries for a realm with item names/levels joined
function getUnclaimedRealmLogs($conn, $realm_id, $types = array()) {
	$realm_id    = intval($realm_id);
	$type_filter = '';
	if (!empty($types)) {
		$escaped     = array_map(function($t) use ($conn) { return "'" . $conn->real_escape_string($t) . "'"; }, $types);
		$type_filter = " AND rl.type IN (" . implode(',', $escaped) . ")";
	}
	$result = $conn->query("
		SELECT rl.id, rl.type, rl.item_id, rl.quantity, rl.created_date,
		       w.name AS weapon_name, w.level AS weapon_level,
		       a.name AS armor_name, a.level AS armor_level,
		       c.name AS consumable_name
		FROM realms_logs rl
		LEFT JOIN weapons w ON rl.type = 'weapon' AND w.id = rl.item_id
		LEFT JOIN armor a ON rl.type = 'armor' AND a.id = rl.item_id
		LEFT JOIN consumables c ON rl.type = 'consumable' AND c.id = rl.item_id
		WHERE rl.realm_id = $realm_id AND rl.claimed = 0$type_filter
		ORDER BY rl.created_date ASC
	");
	$logs = array();
	if ($result) while ($row = $result->fetch_assoc()) $logs[] = $row;
	return $logs;
}

// Claim unclaimed log entries for a realm, optionally scoped to specific types
function claimRealmLogs($conn, $realm_id, $types = array()) {
	$realm_id = intval($realm_id);
	$r = $conn->query("SELECT user_id FROM realms WHERE id = $realm_id LIMIT 1");
	if (!$r || $r->num_rows == 0) return false;
	$user_id = intval($r->fetch_assoc()['user_id']);
	$logs = getUnclaimedRealmLogs($conn, $realm_id, $types);
	if (empty($logs)) return false;
	foreach ($logs as $log) {
		$item_id = intval($log['item_id']);
		$qty     = intval($log['quantity']);
		switch ($log['type']) {
			case 'carbon':
				updateBalance($conn, $user_id, 15, $qty);
				logCredit($conn, $user_id, $qty, 15);
				break;
			case 'consumable':
				updateAmount($conn, $user_id, $item_id, $qty);
				break;
			case 'weapon':
				updateGear($conn, $user_id, 'weapon', $item_id, $qty);
				break;
			case 'armor':
				updateGear($conn, $user_id, 'armor', $item_id, $qty);
				break;
		}
	}
	$type_filter = '';
	if (!empty($types)) {
		$escaped     = array_map(function($t) use ($conn) { return "'" . $conn->real_escape_string($t) . "'"; }, $types);
		$type_filter = " AND type IN (" . implode(',', $escaped) . ")";
	}
	$conn->query("UPDATE realms_logs SET claimed = 1 WHERE realm_id = $realm_id AND claimed = 0$type_filter");
	return true;
}

// ── RAID SOLDIERS ──────────────────────────────────────────
// Get trained, alive, reserve soldiers available for a new raid
function getAvailableRaiders($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$reserved = getReservedSoldierIds($conn, $realm_id);
	$exclude  = !empty($reserved) ? 'AND soldiers.id NOT IN (' . implode(',', $reserved) . ')' : '';
	$sql = "SELECT soldiers.id AS soldier_id, soldiers.nft_id, soldiers.weapon_id, soldiers.armor_id,
	               soldiers.date_created,
	               nfts.name AS nft_name, nfts.ipfs, nfts.collection_id, projects.id AS project_id,
	               weapons.name AS weapon_name, weapons.level AS weapon_level,
	               armor.name AS armor_name, armor.level AS armor_level
	        FROM soldiers
	        INNER JOIN nfts ON nfts.id = soldiers.nft_id
	        INNER JOIN collections ON collections.id = nfts.collection_id
	        INNER JOIN projects ON projects.id = collections.project_id
	        LEFT JOIN weapons ON weapons.id = soldiers.weapon_id
	        LEFT JOIN armor ON armor.id = soldiers.armor_id
	        WHERE soldiers.realm_id = $realm_id
	          AND soldiers.location = 1
	          AND soldiers.trained = 1
	          AND soldiers.dead IS NULL
	          $exclude
	        ORDER BY COALESCE(weapons.level,0) + COALESCE(armor.level,0) DESC";
	$result = $conn->query($sql);
	$raiders = array();
	while ($row = $result->fetch_assoc()) $raiders[] = $row;
	return $raiders;
}

// Commit soldiers to a raid (set location=3, insert raids_soldiers rows)
function commitRaidSoldiers($conn, $raid_id, $soldier_ids) {
	$raid_id = intval($raid_id);
	foreach ($soldier_ids as $sid) {
		$sid = intval($sid);
		// Verify soldier is in reserve (location=1), trained, alive
		$check = $conn->query("SELECT id, realm_id, weapon_id, armor_id FROM soldiers WHERE id = $sid AND location = 1 AND trained = 1 AND dead IS NULL AND active = 1 LIMIT 1");
		if (!$check || $check->num_rows == 0) continue;
		$srow      = $check->fetch_assoc();
		$weapon_id = intval($srow['weapon_id']);
		$armor_id  = intval($srow['armor_id']);
		$conn->query("INSERT INTO raids_soldiers (raid_id, soldier_id) VALUES ($raid_id, $sid)");
		$conn->query("UPDATE soldiers SET location = 3 WHERE id = $sid LIMIT 1");
		$conn->query("INSERT INTO raids_logs (raid_id, side, soldier_id, weapon_id, armor_id, dead) VALUES ($raid_id, 'offense', $sid, $weapon_id, $armor_id, 0)");
	}
}

// Release raid soldiers back to reserve (called when raid resolves)
function releaseRaidSoldiers($conn, $raid_id) {
	$raid_id = intval($raid_id);
	$result  = $conn->query("SELECT soldier_id FROM raids_soldiers WHERE raid_id = $raid_id");
	while ($row = $result->fetch_assoc()) {
		$sid = intval($row['soldier_id']);
		$conn->query("UPDATE soldiers SET location = 1 WHERE id = $sid AND location = 3 LIMIT 1");
	}
}

// Kill raid soldiers that fail their death roll (called from endRaid on loss)
// $armor_reduction_per_level = 5 (each armor level reduces death chance by 5%, min 5% floor)
function rollRaidSoldierDeaths($conn, $raid_id, $armor_reduction_per_level = 5) {
	$raid_id = intval($raid_id);
	$result  = $conn->query("SELECT raids_soldiers.soldier_id, soldiers.armor_id, COALESCE(armor.level, 0) AS armor_level FROM raids_soldiers INNER JOIN soldiers ON soldiers.id = raids_soldiers.soldier_id LEFT JOIN armor ON armor.id = soldiers.armor_id WHERE raids_soldiers.raid_id = $raid_id");
	while ($row = $result->fetch_assoc()) {
		$sid           = intval($row['soldier_id']);
		$armor_level   = intval($row['armor_level']);
		$death_chance  = max(5, 70 - ($armor_level * $armor_reduction_per_level));
		if (rand(1, 100) <= $death_chance) {
			$conn->query("UPDATE soldiers SET dead = NOW(), location = 1 WHERE id = $sid LIMIT 1");
			$conn->query("UPDATE raids_logs SET dead = 1 WHERE raid_id = $raid_id AND soldier_id = $sid AND side = 'offense' LIMIT 1");
		} else {
			$conn->query("UPDATE raids_logs SET dead = 0 WHERE raid_id = $raid_id AND soldier_id = $sid AND side = 'offense' LIMIT 1");
		}
	}
}

// Kill tower garrison soldiers when offense wins
// Attacker weapon bonus: each weapon level reduces garrison survival by flat amount
function rollTowerSoldierDeaths($conn, $raid_id, $defense_realm_id, $weapon_bonus_per_level = 3) {
	$raid_id         = intval($raid_id);
	$defense_realm_id = intval($defense_realm_id);
	// Sum of weapon levels across all raids_soldiers for this raid
	$wresult = $conn->query("SELECT COALESCE(SUM(COALESCE(weapons.level,0)),0) AS total_weapon_level FROM raids_soldiers INNER JOIN soldiers ON soldiers.id = raids_soldiers.soldier_id LEFT JOIN weapons ON weapons.id = soldiers.weapon_id WHERE raids_soldiers.raid_id = $raid_id");
	$wrow = $wresult->fetch_assoc();
	$total_weapon = intval($wrow['total_weapon_level']);

	// Each tower soldier rolls
	$garrison = $conn->query("SELECT soldiers.id AS soldier_id, soldiers.weapon_id, soldiers.armor_id, COALESCE(armor.level,0) AS armor_level FROM soldiers LEFT JOIN armor ON armor.id = soldiers.armor_id WHERE soldiers.realm_id = $defense_realm_id AND soldiers.location = 2 AND soldiers.dead IS NULL AND soldiers.active = 1");
	while ($row = $garrison->fetch_assoc()) {
		$sid          = intval($row['soldier_id']);
		$weapon_id    = intval($row['weapon_id']);
		$armor_id     = intval($row['armor_id']);
		$armor_level  = intval($row['armor_level']);
		// Attacker weapons increase death chance; defender armor reduces it
		$death_chance = max(5, 40 + ($total_weapon * $weapon_bonus_per_level) - ($armor_level * 4));
		$death_chance = min(95, $death_chance);
		$dead         = (rand(1, 100) <= $death_chance) ? 1 : 0;
		if ($dead) {
			$conn->query("UPDATE soldiers SET dead = NOW(), location = 1 WHERE id = $sid LIMIT 1");
		}
		$conn->query("INSERT INTO raids_logs (raid_id, side, soldier_id, weapon_id, armor_id, dead) VALUES ($raid_id, 'defense', $sid, $weapon_id, $armor_id, $dead)");
	}
}

// Render soldier breakdown from raids_logs for a completed raid card
function getRaidLogsDisplay($conn, $raid_id) {
	$raid_id = intval($raid_id);
	$result  = $conn->query("SELECT raids_logs.side, raids_logs.dead, raids_logs.weapon_id, raids_logs.armor_id, nfts.name AS nft_name, weapons.name AS weapon_name, armor.name AS armor_name FROM raids_logs INNER JOIN soldiers ON soldiers.id = raids_logs.soldier_id INNER JOIN nfts ON nfts.id = soldiers.nft_id LEFT JOIN weapons ON weapons.id = raids_logs.weapon_id AND raids_logs.weapon_id > 0 LEFT JOIN armor ON armor.id = raids_logs.armor_id AND raids_logs.armor_id > 0 WHERE raids_logs.raid_id = $raid_id ORDER BY raids_logs.side, raids_logs.dead DESC");
	if (!$result || $result->num_rows == 0) return '';
	$offense = array(); $defense = array();
	while ($row = $result->fetch_assoc()) {
		if ($row['side'] === 'offense') $offense[] = $row;
		else $defense[] = $row;
	}
	if (empty($offense) && empty($defense)) return '';
	$html = "<div class='rc-logs-panel'>";
	foreach (array('Offense' => $offense, 'Defense' => $defense) as $label => $soldiers) {
		if (empty($soldiers)) continue;
		$html .= "<div class='rc-logs-col'>";
		$html .= "<div class='rc-logs-label'>" . $label . "</div>";
		foreach ($soldiers as $s) {
			$dead_class = ($s['dead'] == 1) ? ' rc-log-dead' : '';
			$html .= "<div class='rc-log-row" . $dead_class . "'>";
			$html .= "<span class='rc-log-name'>" . htmlspecialchars($s['nft_name']) . "</span>";
			$gear_icons = '';
			if (!empty($s['weapon_name'])) {
				$wfile = strtolower(str_replace(' ', '-', $s['weapon_name'])) . '.png';
				$gear_icons .= "<img class='icon' src='icons/" . $wfile . "' onerror=\"this.src='icons/skull.png'\" style='width:14px;height:14px;opacity:0.7;' title='" . htmlspecialchars($s['weapon_name']) . "'>";
			}
			if (!empty($s['armor_name'])) {
				$afile = strtolower(str_replace(' ', '-', $s['armor_name'])) . '.png';
				$gear_icons .= "<img class='icon' src='icons/" . $afile . "' onerror=\"this.src='icons/skull.png'\" style='width:14px;height:14px;opacity:0.7;' title='" . htmlspecialchars($s['armor_name']) . "'>";
			}
			if ($gear_icons) $html .= "<span class='rc-log-gear'>" . $gear_icons . "</span>";
			$html .= "</div>";
		}
		$html .= "</div>";
	}
	$html .= "</div>";
	return $html;
}

// ── BARRACKS & TOWER SCORE FOR RAID CALCULATIONS ───────────
// Barracks score: (filled_trained_slots / deployment_cap) × 10  →  0–10
function getBarracksScore($conn, $realm_id) {
	$realm_id = intval($realm_id);
	$cap = getDeploymentCap($conn, $realm_id);
	if ($cap == 0) return 0;
	$result = $conn->query("SELECT COUNT(*) AS cnt FROM soldiers WHERE realm_id = $realm_id AND trained = 1 AND dead IS NULL AND active = 1");
	$row    = $result->fetch_assoc();
	$filled = intval($row['cnt']);
	return min(10, ($filled / $cap) * 10);
}

// Tower score: (garrison_count / 10) × 10  →  0–10
function getTowerScore($conn, $realm_id) {
	$count = count(getTowerGarrison($conn, $realm_id));
	return ($count / 10) * 10;
}

/* END REALMS ENHANCEMENT */
?>