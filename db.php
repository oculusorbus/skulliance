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
	$sql = "UPDATE users SET message='".$status."' WHERE discord_id='".$discord_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Update Discord Reaction Status
function updateDiscordReactionStatus($conn, $discord_id, $status){
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
 	  echo "<table cellspacing='0' id='transactions'>";
	  echo "<th align='center' width='55'>Icon</th><th width='55' align='center'>Project</th><th align='left' id='consumable-header'>Items</th><th align='left'>Cost</th><th align='left'>Reward</th><th align='left'>NFTs</th><th align='left'>Success</th><th align='left'>Duration</th><th align='left'>Time Left</th><th align='center'>Status</th>";
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
			$time_message = $days_remaining."d ".$hours_remaining."h ".$minutes_remaining."m";
			$completed = "In Progress";
		}else{
			$time_message = "0d 0h 0m";
			$completed = "Completed";
			//$completed = "<input type='button' class='small-button' value='Claim' onclick='completeMission(".$row["mission_id"].", ".$row["quest_id"].");this.style.display=\"none\";'/>";
			$completed_missions[$row["mission_id"]] = $row["quest_id"];
		}
		$consumables = getMissionConsumables($conn, $row["mission_id"]);
		$decimal = $days_remaining.".".(($hours_remaining<10)?"0".$hours_remaining:$hours_remaining).(($minutes_remaining<10)?"0".$minutes_remaining:$minutes_remaining).$row["mission_id"];
		$rows[$decimal] = "";
		$rows[$decimal] .= "<tr id='mission-row-".$row["mission_id"]."'>";
		  $rows[$decimal] .= "<td align='center'>";
		  $rows[$decimal] .= "<img class='icon' title='".$row["title"]."' src='images/missions/".strtolower(str_replace(" ", "-", $row["title"])).".png'/>";
		  $rows[$decimal] .= "</td>";
		  $rows[$decimal] .= "<td align='center'>";
		  $rows[$decimal] .= "<img class='icon' title='".$row["currency"]."' style='border:0px;' src='icons/".strtolower($row["currency"]).".png' />";
		  $rows[$decimal] .= "</td>";
  		  $rows[$decimal] .= "<td align='left' id='consumable-".$row["mission_id"]."'>";
		  if(is_array($consumables)){
		  	  foreach($consumables AS $consumable_id => $consumable_name){
				  $rows[$decimal] .= "<img title='".$consumable_name."' class='icon consumable' src='icons/".strtolower(str_replace("%", "", str_replace(" ", "-", $consumable_name))).".png'/>";
		  	  }
		  }else{
			  //echo "<img class='icon consumable' src='icons/nothing.png'/>";
		  }
		  $rows[$decimal] .= "</td>";
		  $rows[$decimal] .= "<td align='left'>";
		  $rows[$decimal] .= number_format($row["cost"])." ".$row["currency"];
		  $rows[$decimal] .= "</td>";
		  $rows[$decimal] .= "<td align='left' id='mission-reward-".$row["mission_id"]."'>";
		  $rows[$decimal] .= number_format($row["reward"])." <span id='currency-".$row["mission_id"]."'>".$row["currency"]."</span>";
		  $rows[$decimal] .= "</td>";
		  $rows[$decimal] .= "<td align='left'>";
		  $rows[$decimal] .= $row["total_nfts"];
		  $rows[$decimal] .= "</td>";
  		  $rows[$decimal] .= "<td align='left'>";
		  $rows[$decimal] .= $success_rate+$row["success_rate"]."%";
		  $rows[$decimal] .= "</td>";
		  $rows[$decimal] .= "<td align='left'>";
		  $rows[$decimal] .= $row["duration"]." ".(($row["duration"] == 1)?"Day":"Days");
		  $rows[$decimal] .= "</td>";
  		  $rows[$decimal] .= "<td align='left'>";
		  $rows[$decimal] .= $time_message;
		  $rows[$decimal] .= "</td>";
  		  $rows[$decimal] .= "<td align='center' id='mission-result-".$row["mission_id"]."'>";
		  if($completed == "In Progress"){
			  $rows[$decimal] .= "<input type='button' id='retreat-button-".$row["mission_id"]."' class='small-button' value='Retreat' onclick='retreat(\"".$row["mission_id"]."\", \"".$row["quest_id"]."\");'/>";
		  }else{
 			  $rows[$decimal] .= $completed;
		  }
		  $rows[$decimal] .= "</td>";
		$rows[$decimal] .= "</tr>";
		$rows[$decimal] .= "<tr id='mission-progress-".$row["mission_id"]."'>";
		$rows[$decimal] .= "<td colspan='10' style='padding:0px;'>";
		$rows[$decimal] .= "<div class='w3-border'>";
		if($completed == "Completed"){
			$percentage = 100;
		}else{
			$percentage = 100-((($days_remaining+($hours_remaining/24)+($minutes_remaining/1440)) / $row["duration"])*100);
		}
		// background-image:url("."images/missions/".strtolower(str_replace(" ", "-", $row["title"])).".png".");background-position:center;background-blend-mode:exclusion;
		$rows[$decimal] .= "<div class='w3-grey' style='width:".$percentage."%;opacity:0.3;'></div>";
		$rows[$decimal] .= "</div>";
		$rows[$decimal] .= "</td>";
		$rows[$decimal] .= "</tr>";
	  }
	  ksort($rows);
	  foreach($rows AS $duration => $output){
		  echo $output;
	  }
	  echo "</table><br>";
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
			echo renderIPFS($row["ipfs"], $row["collection_id"], getIPFS($row["ipfs"], $row["collection_id"]), true);
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
		
			$sql = "SELECT title, cost, project_id, currency FROM quests INNER JOIN projects ON projects.id = quests.project_id WHERE quests.id ='".$_SESSION['userData']['mission']['quest_id']."';";
			$result = $conn->query($sql);
		
			if ($result->num_rows > 0) {
			  // output data of each row
			  while($row = $result->fetch_assoc()) {
				  $title = $row["title"];
				  $project_id = $row["project_id"];
				  $cost = $row["cost"];
				  $currency = $row["currency"];
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
	$rate_flag = "false";
	
	// Get all level 1 quests
	$sql = "SELECT id, cost, project_id FROM quests WHERE level = '1'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
		// output data of each row
		while($row = $result->fetch_assoc()) {
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
	echo "<form style='display:".$display."' id='startFreeMissionsForm' action='missions.php' method='post'>
	<input type='hidden' id='start_all' name='start_all' value='true'>	
	<input type='submit' value='Start All Free' class='button'>
	</form><br>";
	return $projects;
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
	  	$sql = "SELECT currency, cost, project_id FROM quests INNER JOIN projects ON projects.id = quests.project_id WHERE quests.id = '".$quest_id."'";
	  	$result = $conn->query($sql);
	  	if ($result->num_rows > 0) {
	  	  // output data of each row
	  	  while($row = $result->fetch_assoc()) {
	  	    $cost = $row["cost"];
	  		$project_id = $row["project_id"];
	  		$currency = $row["currency"];
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
	  	$sql = "SELECT name, consumable_id FROM missions_consumables INNER JOIN consumables ON consumables.id = missions_consumables.consumable_id WHERE mission_id = '".$mission_id."'";
	  	$result = $conn->query($sql);
	  	if ($result->num_rows > 0) {
	  	  // output data of each row
	  	  while($row = $result->fetch_assoc()) {
	  		echo $row["name"]." Restored\r\n";
	  	    updateAmount($conn, $_SESSION['userData']['user_id'], $row["consumable_id"], 1);
	  	  }
	  	} else {
	  	  //echo "0 results";
	  	}
	
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
	$sql = "SELECT amount FROM amounts WHERE user_id = '".$user_id."' AND consumable_id = '".$consumable_id."'";
	$result = $conn->query($sql);
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	return $row["amount"];
	  }
	} else {
	  //echo "0 results";
	    return "false";
	}
}

// Update specific user amount for a specific consumable
function updateAmount($conn, $user_id, $consumable_id, $subtotal){
	$current_amount = getCurrentAmount($conn, $user_id, $consumable_id);
	if($current_amount != "false"){
		$total = $subtotal + $current_amount;
		$sql = "UPDATE amounts SET amount = '".$total."' WHERE user_id='".$user_id."' AND consumable_id ='".$consumable_id."'";
		if ($conn->query($sql) === TRUE) {
		  //echo "New record created successfully";
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}else{
		$sql = "INSERT INTO amounts (amount, user_id, consumable_id)
		VALUES ('".$subtotal."', '".$user_id."', '".$consumable_id."')";

		if ($conn->query($sql) === TRUE) {
		  //echo "New record created successfully";
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
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
	$sql = "INSERT INTO users (discord_id, avatar, username)
	VALUES ('".$_SESSION['userData']['discord_id']."', '".$_SESSION['userData']['avatar']."', '".$_SESSION['userData']['name']."')";

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
	$sql = "UPDATE users SET username='".$_SESSION['userData']['name']."', avatar='".$_SESSION['userData']['avatar']."' WHERE id='".$_SESSION['userData']['user_id']."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Create address for user
function createAddress($conn, $stake_address, $address) {
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
	$sql = "SELECT stake_address FROM wallets";
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
	$sql = "UPDATE nfts set	user_id = 0";
	
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
function getIPFS($ipfs, $collection_id){
	if(str_contains($ipfs, "data:image/svg+xml;base64")){
		return $ipfs;
	}else{
		$ipfs = str_replace("ipfs/", "", $ipfs);
		return "https://ipfs5.jpgstoreapis.com/ipfs/".$ipfs;
		if($collection_id == 4 || $collection_id == 23){
			return "https://image-optimizer.jpgstoreapis.com/".$ipfs;
		}else if($collection_id == 20 || $collection_id == 21 || $collection_id == 30 || $collection_id == 42){
			return "https://storage.googleapis.com/jpeg-optim-files/".$ipfs;
		}else{
			return "https://image-optimizer.jpgstoreapis.com/".$ipfs;
		}
	}
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
		return "<span class='nft-image'><img ".$class." onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
	}else if($collection_id == 20 || $collection_id == 21 || $collection_id == 30 || $collection_id == 42){
		return "<span class='nft-image'><img ".$class." onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
	}else if($collection_id == 260){
		return "<span class='nft-image'><img style='min-height:165px' ".$class." onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
	}else{
		return "<span class='nft-image'><img ".$class." onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
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
		echo renderIPFS($row["ipfs"], $row["collection_id"], getIPFS($row["ipfs"], $row["collection_id"]));
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
	$imageurl = getIPFS($nft_image, $collection_id);
	discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
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
function getNFTs($conn, $filterby="", $advanced_filter="", $diamond_skull=false, $diamond_skull_id="", $core_projects=false, $diamond_skull_totals=""){
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
		
		// Limit for Oculus Orbus' massive NFT collection
		$limit = "";
		if($_SESSION['userData']['user_id'] == 1){
			//$limit = " LIMIT 300";
		}
		$sql = "SELECT asset_id, asset_name, nfts.name AS nfts_name, ipfs, collection_id, nfts.id AS nfts_id, collections.rate AS rate, projects.currency AS currency, projects.id AS project_id, projects.name AS project_name, collections.name AS collection_name, users.username AS username FROM nfts INNER JOIN users ON users.id = nfts.user_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id WHERE ".$user_filter.$and.$filterby.$diamond_skull_filter.$core_where." ORDER BY FIELD(project_id,6,5,4,3,2,1,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30,31,32,33,34,35,36,37,38,39,40,41,42,43,44,45,46,47,48,49,50), collection_id".$limit;
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
		  // output data of each row
		  $nftcounter = 0;
		  while($row = $result->fetch_assoc()) {
			$nftcounter++;
			$reveal_prefix = "";
			$reveal_suffix = "";
			if($nftcounter > 16){
				$reveal_prefix = "<section class='reveal'>";
				$reveal_suffix = "</section>";
			}
			if($diamond_skull == true){
				echo $reveal_prefix."<div class='nft'><div class='diamond-skull-data'>";
			}else{
		    	echo $reveal_prefix."<div class='nft'><div class='nft-data'>";
			}
			echo "<span class='nft-name'>".$row["nfts_name"]."</span>";
			echo "<a href='https://pool.pm/".$row["asset_id"]."' target='_blank'>".renderIPFS($row["ipfs"], $row["collection_id"], getIPFS($row["ipfs"], $row["collection_id"]))."</a>";
			if($diamond_skull == false){
				echo "<span class='nft-level'><strong>Project</strong><br>".$row["project_name"]."</span>";
				echo "<span class='nft-level'><strong>Collection</strong><br>".$row["collection_name"]."</span>";
				echo "<span class='nft-level'><strong>Reward Rate</strong><br>".$row["rate"]." ".$row["currency"]."</span>";
			}else if($diamond_skull == true){
				echo "<span class='nft-level'><strong>Owner</strong><br>".$row["username"]."</span>";
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
			echo "</div></div>".$reveal_suffix;
		  }
		} else {
		  //echo "0 results";
		  echo "<p>You do not have any qualifying NFTs.</p>";
		}
	}
}

// Create item
function createItem($conn, $name, $image_url, $price, $quantity, $project_id, $override=0){
	$sql = "INSERT INTO items (name, image_url, price, quantity, project_id, override)
	VALUES ('".mysqli_real_escape_string($conn, $name)."', '".$image_url."', '".$price."', '".$quantity."', '".$project_id."', '".$override."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Update item
function updateItem($conn, $item_id, $name, $image_url, $price, $quantity, $project_id){
	$sql = "UPDATE items SET name='".mysqli_real_escape_string($conn, $name)."', image_url='".$image_url."', price='".$price."', quantity='".$quantity."' WHERE id='".$item_id."'";
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
		$filterby = "AND project_id = '".$filterby."' ";
	}else if($filterby == "exclusive"){
		$filterby = "AND featured = '1' ";
	}else{
		$filterby = "";
	}
	$sql = "SELECT items.id AS item_id, items.name AS item_name, image_url, price, quantity, project_id, secondary_project_id, projects.name AS project_name, projects.currency AS currency, divider, featured FROM items INNER JOIN projects ON projects.id = items.project_id WHERE quantity != 0 ".$filterby." ORDER BY featured DESC, projects.id, items.name ASC";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  $nftcounter = 0;
	  $uri = $_SERVER['REQUEST_URI'];
	  if(str_contains($uri, "store.php")){
		$class = "store-item";
	  }else{
		$class = "offering";
	  }
	  while($row = $result->fetch_assoc()) {
		$nftcounter++;
	    echo "<div class='nft ".$class."'><div class='nft-data'>";
		echo "<span class='nft-name'>".$row["item_name"]."</span>";
		echo "<span class='nft-image'><img onError='this.src=\"/staking/icons/skull.png\";' src='".$row["image_url"]."'/></span>";
		/* Listing price is redundant when buy buttons list price
		if($row["project_id"] != 7){
			echo "<span class='nft-level'><strong>Price</strong><br>".number_format($row["price"])." ".$row["currency"]."<br>or<br>".number_format($row["price"]/$row["divider"])." DIAMOND</span>";
		}else{
			echo "<span class='nft-level'><strong>Price</strong><br>".number_format($row["price"])." ".$row["currency"]."</span>";
		}*/
		echo "<span class='nft-level'><strong>Project</strong><br>".$row["project_name"]."</span>";
		echo "<span class='nft-level'><strong>Quantity</strong><br>".$row["quantity"]."</span>";
		renderBuyButton($row["item_id"], $row["project_id"], "BUY: ".number_format($row["price"])." ".$row["currency"], $row["project_id"], $page);
		if($row["secondary_project_id"] != 0){
			$project = getProjectInfo($conn, $row["secondary_project_id"]);
			renderBuyButton($row["item_id"], $row["secondary_project_id"], "BUY: ".number_format($row["price"])." ".$project["currency"], $row["project_id"], $page);
		}
		if($row["project_id"] != 7){
			renderBuyButton($row["item_id"], 7, "BUY: ".number_format($row["price"]/$row["divider"])." DIAMOND", $row["project_id"], $page);
		}
		echo "</div></div>";
	  }
	} else {
	  echo "<p>There are no items available.</p><p>Please contact the project to request more staking incentives.</p><p><img src='images/empty.gif'/></p>";
	}
}

// Render buy button for item
function renderBuyButton($id, $project_id, $verbiage, $primary_project_id, $page){
	global $conn;
	echo "
	<form onsubmit='return confirm(\"Do you really want to purchase this item?\");' action='".$page.".php#store' method='post'>
	  <input type='hidden' id='item_id' name='item_id' value='".$id."'>
	  <input type='hidden' id='project_id' name='project_id' value='".$project_id."'>
	  <input type='hidden' id='primary_project_id' name='primary_project_id' value='".$primary_project_id."'>
	  <input class='small-button' type='submit' value='".$verbiage."'>
	</form>";
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
	echo "<script type='text/javascript'>window.onload = function(event) {alert('".$message."');};</script>";
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
	if($current_balance != "false"){
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

function fireworks(){
	echo "<script type='text/javascript'>".
		 "var body = document.getElementsByTagName('body')[0];".
		 "body.style.backgroundImage = 'url(https://www.skulliance.io/staking/images/fireworks.gif)';".
		 "</script>";
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
			echo "<table id='transactions' cellspacing='0'>";
			echo "<th>Rank</th><th>Avatar</th><th align='left'>Username</th><th>NFTs</th><th>Points</th>";
		  	while($row = $result->fetch_assoc()) {
				// Filter out project owners
				if(($row["discord_id"] == $row["project_discord_id"] && $row["project_discord_id"] != "772831523899965440" && $row["project_discord_id"] != "578386308406181918") || ($project_id == '5' && $row["discord_id"] == "183841115286405121")){
					// Do not generate row for project owners unless Oculus Orbus and filter out Kimosabe
				}else{
					$leaderboardCounter++;
					$width = 40;
					$trophy = "";
					if($leaderboardCounter == 1){
						//$width = 50;
						$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
						if(isset($_SESSION['userData']['user_id'])){
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
						}
						}
					}else if($leaderboardCounter == 2){
						//$width = 45;
						if($last_total != $row["total"]){
							$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
						}else{
							$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
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
							$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
							$third_total = $row["total"];
						}else{
							$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
							$leaderboardCounter--;
						}
						if(isset($_SESSION['userData']['user_id'])){
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
						}
						}
					}else if($leaderboardCounter > 3 && $third_total == $row["total"]){
						$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
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
					$avatar = "";
					if($row["avatar"] != ""){
						$avatar = "<img style='width:".$width."px' onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon rounded-full'/>";
					}
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
					$username = "";
					if($row["visibility"] == "2"){
						$username = "<a href='showcase.php?username=".$row["username"]."'>".$row["username"]. "</a>";
					}else{
						$username = $row["username"];
					}
					echo "<tr class='".$highlight."'>";
			    	echo "<td align='center'><strong>".(($trophy == "")?(($leaderboardCounter<10)?"0":"").$leaderboardCounter.".":$trophy)."</strong></td><td align='center'>".$avatar."</td><td><strong style='font-size:20px'>".$username."</strong></td><td align='center'>".$row["total"].$delegated."</td><td align='center'>".(($project_id != 0)?" ".number_format($current_balance)." ".$row["currency"]."":number_format($current_balance)).$diamond_skull_count."</td>";
					echo "</tr>";
					$last_total = $row["total"];
				}
		  	}
			//echo "</ul>";
			echo "</table>";
			if($fireworks){
				fireworks();
			}
		}
	} else {
	  //echo "0 results";
	}
}

function getTotalMissions($conn){
	/*$month_sql = "SELECT (SELECT COUNT(success_missions.id) FROM missions AS success_missions INNER JOIN users AS success_users ON success_users.id = success_missions.user_id 
				  WHERE success_missions.status = '1' AND success_users.id = users.id AND DATE(success_missions.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS success, 
	             (SELECT COUNT(failed_missions.id) FROM missions AS failed_missions INNER JOIN users AS failed_users ON failed_users.id = failed_missions.user_id 
				  WHERE failed_missions.status = '2' AND failed_users.id = users.id AND DATE(failed_missions.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS failure, 
				 (SELECT COUNT(progress_missions.id) FROM missions AS progress_missions INNER JOIN users AS progress_users ON progress_users.id = progress_missions.user_id 
				  WHERE progress_missions.status = '0' AND progress_users.id = users.id AND DATE(progress_missions.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS progress, 
	        	  COUNT(missions.id) AS total, SUM(quests.duration) AS total_duration, users.id AS user_id 
		    	  FROM users INNER JOIN missions ON missions.user_id = users.id INNER JOIN quests ON quests.id = missions.quest_id WHERE users.id = '".$_SESSION['userData']['user_id']."' AND DATE(missions.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')";*/
				  
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
	GROUP BY users.id;";
	
	$month_result = $conn->query($month_sql);
	
	if ($month_result->num_rows > 0) {
		echo "<table id='transactions' cellspacing='0'>";
		echo "<th width='14%'>Timeframe</th><th width='14%'>Score</th><th width='14%'>Total Missions</th><th width='14%'>In Progress</th><th width='14%'>Success</th><th width='14%'>Failure</th><th width='14%'>Leaderboard</th>";
		while($month_row = $month_result->fetch_assoc()) {
			echo "<tr class='month-row'>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/calendar.png'/>";
			echo date('F');
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/score.png'/>";
			echo number_format(calculateScore($month_row["total_duration"], $month_row["success"], $month_row["failure"], $month_row["progress"]));
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/total.png'/>";
			echo $month_row["total"];
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/time.png'/>";
			echo $month_row["progress"];
			echo "</td>";
			echo "<td align='center'>";
			$success_percentage = 0;
			$over50 = "";
			if($month_row["total"]-$month_row["progress"] != 0){
				$success_percentage = $month_row["success"]/($month_row["total"]-$month_row["progress"])*100;
			}else{
				$success_percentage = 0;
			}
			if(round($success_percentage) > 50){
				$over50 = "over50";
			}
			?>
			<div class="progress-circle <?php echo $over50;?> p<?php echo round($success_percentage);?>">
			   <span><?php echo round($success_percentage, 2)."%";?></span>
			   <div class="left-half-clipper">
			      <div class="first50-bar success"></div>
			      <div class="value-bar success-bar"></div>
			   </div>
			</div>
			<?php
			echo "<span class='outcome-total'>".$month_row["success"]."</span>";
			echo "</td>";
			echo "<td align='center'>";
			$failure_percentage = 0;
			$over50 = "";
			if($month_row["total"]-$month_row["progress"] != 0){
				$failure_percentage = $month_row["failure"]/($month_row["total"]-$month_row["progress"])*100;
			}else{
				$failure_percentage = 0;
			}
			if(round($failure_percentage) > 50){
				$over50 = "over50";
			}
			?>
			<div class="progress-circle <?php echo $over50;?> p<?php echo round($failure_percentage);?>">
			   <span><?php echo round($failure_percentage, 2)."%";?></span>
			   <div class="left-half-clipper">
			      <div class="first50-bar failure"></div>
			      <div class="value-bar failure-bar"></div>
			   </div>
			</div>
			<?php
			echo "<span class='outcome-total'>".$month_row["failure"]."</span>";
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/trophy.png'/>";
			echo "<form action='leaderboards.php' method='post'><input type='hidden' name='filterby' id='filterby' value='monthly'/><input type='submit' class='small-button' value='".date("F")."'/></form>";
			echo "</td>";
			echo "</tr>";
		}
		/*$sql = "SELECT (SELECT COUNT(success_missions.id) FROM missions AS success_missions INNER JOIN users AS success_users ON success_users.id = success_missions.user_id WHERE success_missions.status = '1' AND success_users.id = users.id) AS success, 
		               (SELECT COUNT(failed_missions.id) FROM missions AS failed_missions INNER JOIN users AS failed_users ON failed_users.id = failed_missions.user_id  WHERE failed_missions.status = '2' AND failed_users.id = users.id) AS failure, 
					   (SELECT COUNT(progress_missions.id) FROM missions AS progress_missions INNER JOIN users AS progress_users ON progress_users.id = progress_missions.user_id  WHERE progress_missions.status = '0' AND progress_users.id = users.id) AS progress, 
		        COUNT(missions.id) AS total, SUM(quests.duration) AS total_duration, users.id AS user_id
			    FROM users INNER JOIN missions ON missions.user_id = users.id INNER JOIN quests ON quests.id = missions.quest_id WHERE users.id = '".$_SESSION['userData']['user_id']."'";*/
		
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
	
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				echo "<tr>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/infinity.png'/>";
				echo "All Time";
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/all-score.png'/>";
				echo number_format(calculateScore($row["total_duration"], $row["success"], $row["failure"], $row["progress"]));
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/grand-total.png'/>";
				echo number_format($row["total"]);
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/all-time.png'/>";
				echo $row["progress"];
				echo "</td>";
				echo "<td align='center'>";
				$success_percentage = 0;
				$over50 = "";
				if($row["total"]-$row["progress"] != 0){
					$success_percentage = $row["success"]/($row["total"]-$row["progress"])*100;
				}else{
					$success_percentage = 0;
				}
				if(round($success_percentage) > 50){
					$over50 = "over50";
				}
				?>
				<div class="progress-circle <?php echo $over50;?> p<?php echo round($success_percentage);?>">
				   <span><?php echo round($success_percentage, 2)."%";?></span>
				   <div class="left-half-clipper">
				      <div class="first50-bar success"></div>
				      <div class="value-bar success-bar"></div>
				   </div>
				</div>
				<?php
				echo "<span class='outcome-total'>".number_format($row["success"])."</span>";
				echo "</td>";
				echo "<td align='center'>";
				$failure_percentage = 0;
				$over50 = "";
				if($row["total"]-$row["progress"] != 0){
					$failure_percentage = $row["failure"]/($row["total"]-$row["progress"])*100;
				}else{
					$failure_percentage = 0;
				}
				if(round($failure_percentage) > 50){
					$over50 = "over50";
				}
				?>
				<div class="progress-circle <?php echo $over50;?> p<?php echo round($failure_percentage);?>">
				   <span><?php echo round($failure_percentage, 2)."%";?></span>
				   <div class="left-half-clipper">
				      <div class="first50-bar failure"></div>
				      <div class="value-bar failure-bar"></div>
				   </div>
				</div>
				<?php
				echo "<span class='outcome-total'>".number_format($row["failure"])."</span>";
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/crown.png'/>";
				echo "<form action='leaderboards.php' method='post'><input type='hidden' name='filterby' id='filterby' value='missions'/><input type='submit' class='small-button' value='All Time'/></form>";
				echo "</td>";
				echo "</tr>";
				}
			}
		echo "</table><br>";
		$consumables = array();
		$consumables = getCurrentAmounts($conn);
		if(!empty($consumables)){
			echo "<h3>Item Inventory</h3>";
			echo "<table id='transactions' cellspacing='0'>";
			foreach($consumables AS $id => $consumable){
				echo "<th align='center' width='14%'>";
				echo $consumable["name"];
				echo "</th>";
			}
			echo "<tr>";
			foreach($consumables AS $id => $consumable){
				echo "<td align='center'>";
				echo "<img class='icon' style='border:0px' src='icons/".strtolower(str_replace("%", "", str_replace(" ", "-", $consumable["name"]))).".png'/><br><br>";
				echo "<span class='amount'>".$consumable["amount"]."</span>";
				echo "</li>";
			}
			echo "</tr></table>";
		}
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
		echo "<table id='transactions' cellspacing='0'>";
		echo "<th>Rank</th><th>Avatar</th><th align='left'>Username</th><th>Score</th><th>Total Missions</th><th>Success</th><th>Failure</th><th>In Progress</th>";
		if($monthly){
			echo "<th>Projected Rewards</th>";
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
			$trophy = "";
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $row["score"]){
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
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
					$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
					$third_total = $row["score"];
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $row["score"]){
				$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
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
			echo "<tr class='".$highlight."'>";
			echo "<td align='center'>";
			echo "<strong>".(($trophy == "")?(($leaderboardCounter<10)?"0":"").$leaderboardCounter.".":$trophy)."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			$avatar = "<img style='width:".$width."px' onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon rounded-full'/>";
			echo $avatar;
			echo "</td>";
			echo "<td align='left'>";
			$username = "";
			if($row["visibility"] == "2"){
				$username = "<a href='showcase.php?username=".$row["username"]."'>".$row["username"]. "</a>";
			}else{
				$username = $row["username"];
			}
			echo "<strong style='font-size:20px'>".$username."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["score"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["total"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["success"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["failure"]);
			echo "</td>";
			echo "<td align='center'>";
			echo $row["progress"];
			echo "</td>";
			if($monthly){
				echo "<td align='center'>";
				echo number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND";
				echo "</td>";
			}
			echo "</tr>";
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
		echo "</table>";
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
		    FROM users INNER JOIN realms ON users.id = realms.user_id INNER JOIN projects ON projects.id = realms.project_id INNER JOIN raids ON raids.offense_id = realms.id ".$where." GROUP BY users.id ORDER BY total DESC";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
		echo "<table id='transactions' cellspacing='0'>";
		echo "<th>Rank</th><th>Avatar</th><th align='left'>Username</th><th>Faction</th><th>Score</th><th>Total Raids</th><th>Success</th><th>Failure</th><th>In Progress</th>";
		if($monthly){
			echo "<th>Projected Rewards</th>";
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
			$trophy = "";
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $row["score"]){
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
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
					$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
					$third_total = $row["score"];
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $row["score"]){
				$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
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
			echo "<tr class='".$highlight."'>";
			echo "<td align='center'>";
			echo "<strong>".(($trophy == "")?(($leaderboardCounter<10)?"0":"").$leaderboardCounter.".":$trophy)."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			$avatar = "<img style='width:".$width."px' onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon rounded-full'/>";
			echo $avatar;
			echo "</td>";
			echo "<td align='left'>";
			$username = "";
			if($row["visibility"] == "2"){
				$username = "<a href='showcase.php?username=".$row["username"]."'>".$row["username"]. "</a>";
			}else{
				$username = $row["username"];
			}
			echo "<strong style='font-size:20px'>".$username."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			$faction = "<img style='width:".$width."px' onError='this.src=\"/staking/icons/skull.png\";' src='/staking/icons/".strtolower($row["currency"]).".png' class='icon'/>";
			echo $faction;
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["score"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["total"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["success"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["failure"]);
			echo "</td>";
			echo "<td align='center'>";
			echo $row["progress"];
			echo "</td>";
			if($monthly){
				echo "<td align='center'>";
				echo number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND";
				echo "</td>";
			}
			echo "</tr>";
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
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
		}
		echo "</table>";
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
		echo "<table id='transactions' cellspacing='0'>";
		echo "<th>Rank</th><th>Avatar</th><th align='left'>Faction</th><th>Members</th><th>Score</th><th>Total Raids</th><th>Success</th><th>Failure</th><th>In Progress</th>";
		if($monthly){
			echo "<th>Rewards For Each Member</th>";
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
			$trophy = "";
			$realm = getRealmInfo($conn);
			$project_id = $realm["project_id"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
				if(isset($project_id)){
					if($project_id == $row["project_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $row["score"]){
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
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
					$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
					$third_total = $row["score"];
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
					$leaderboardCounter--;
				}
				if(isset($project_id)){
					if($project_id == $row["project_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $row["score"]){
				$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
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
			echo "<tr class='".$highlight."'>";
			echo "<td align='center'>";
			echo "<strong>".(($trophy == "")?(($leaderboardCounter<10)?"0":"").$leaderboardCounter.".":$trophy)."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			$avatar = "<img style='width:".$width."px' onError='this.src=\"/staking/icons/skull.png\";' src='/staking/icons/".strtolower($row["currency"]).".png' class='icon'/>";
			echo $avatar;
			echo "</td>";
			echo "<td align='left'>";
			$project_name = "";
			$project_name = $row["project_name"];
			echo "<strong style='font-size:20px'>".$project_name."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			echo count(getFactionUserIDs($conn, $row["project_id"]));
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["score"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["total"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["success"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["failure"]);
			echo "</td>";
			echo "<td align='center'>";
			echo $row["progress"];
			echo "</td>";
			if($monthly){
				echo "<td align='center'>";
				echo number_format(round($points/$leaderboardCounter))." ".$row["currency"];
				echo "</td>";
			}
			echo "</tr>";
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
			discordmsg($title, $description, $imageurl, "https://skulliance.io/staking");
		}
		echo "</table>";
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
		echo "<table id='transactions' cellspacing='0'>";
		echo "<th>Rank</th><th>Avatar</th><th align='left'>Username</th><th>Total Streaks Completed</th><th>Current Streak (Days)</th>";
		if($monthly){
			echo "<th>Projected Rewards</th>";
		}
		while($row = $result->fetch_assoc()) {
			$leaderboardCounter++;
			$counter++;
			$trophy = "";
			$score = $row["streak_total"].$row["streak"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $score){
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
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
					$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
					$third_total = $score;
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $score){
				$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
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
			echo "<tr class='".$highlight."'>";
			echo "<td align='center'>";
			echo "<strong>".(($trophy == "")?(($leaderboardCounter<10)?"0":"").$leaderboardCounter.".":$trophy)."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			$avatar = "<img style='width:".$width."px' onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon rounded-full'/>";
			echo $avatar;
			echo "</td>";
			echo "<td align='left'>";
			$username = "";
			if($row["visibility"] == "2"){
				$username = "<a href='showcase.php?username=".$row["username"]."'>".$row["username"]. "</a>";
			}else{
				$username = $row["username"];
			}
			echo "<strong style='font-size:20px'>".$username."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			echo $row["streak_total"];
			echo "</td>";
			echo "<td align='center'>";
			echo $row["streak"];
			echo "</td>";
			if($monthly){
				echo "<td align='center'>";
				echo number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND";
				echo "</td>";
			}
			echo "</tr>";
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
		echo "</table>";
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
		echo "<table id='transactions' cellspacing='0'>";
		echo "<th>Rank</th><th>Avatar</th><th align='left'>Username</th><th>".($weekly ? "High Score" : "Average High Score")."</th><th>Attempts</th>";
		if($weekly){
			echo "<th>Projected Rewards</th>";
		}
		while($row = $result->fetch_assoc()) {
			$leaderboardCounter++;
			$counter++;
			$trophy = "";
			$score = $row["max_score"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $score){
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
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
					$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
					$third_total = $score;
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $score){
				$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
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
			echo "<tr class='".$highlight."'>";
			echo "<td align='center'>";
			echo "<strong>".(($trophy == "")?(($leaderboardCounter<10)?"0":"").$leaderboardCounter.".":$trophy)."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			$avatar = "<img style='width:".$width."px' onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon rounded-full'/>";
			echo $avatar;
			echo "</td>";
			echo "<td align='left'>";
			$username = "";
			if($row["visibility"] == "2"){
				$username = "<a href='showcase.php?username=".$row["username"]."'>".$row["username"]. "</a>";
			}else{
				$username = $row["username"];
			}
			echo "<strong style='font-size:20px'>".$username."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["max_score"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["attempts"]);
			echo "</td>";
			if($weekly){
				echo "<td align='center'>";
				echo number_format(round($carbon/$leaderboardCounter))." CARBON = ".number_format(floor(round($carbon/$leaderboardCounter)/100))." DIAMOND";
				echo "</td>";
			}
			echo "</tr>";
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
		echo "</table>";
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
		echo "<table id='transactions' cellspacing='0'>";
		echo "<th>Rank</th><th>Avatar</th><th align='left'>Username</th><th>Damage Dealt</th><th>Damage Taken</th><th>Encounters</th>";
		if($weekly){
			echo "<th>Projected Rewards</th>";
		}
		while($row = $result->fetch_assoc()) {
			$leaderboardCounter++;
			$counter++;
			$trophy = "";
			$score = $row["damage_dealt_total"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $score){
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
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
					$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
					$third_total = $score;
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $score){
				$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
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
			echo "<tr class='".$highlight."'>";
			echo "<td align='center'>";
			echo "<strong>".(($trophy == "")?(($leaderboardCounter<10)?"0":"").$leaderboardCounter.".":$trophy)."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			$avatar = "<img style='width:".$width."px' onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon rounded-full'/>";
			echo $avatar;
			echo "</td>";
			echo "<td align='left'>";
			$username = "";
			if($row["visibility"] == "2"){
				$username = "<a href='showcase.php?username=".$row["username"]."'>".$row["username"]. "</a>";
			}else{
				$username = $row["username"];
			}
			echo "<strong style='font-size:20px'>".$username."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["damage_dealt_total"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["damage_taken_total"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["encounters_total"]);
			echo "</td>";
			if($weekly){
				echo "<td align='center'>";
				echo number_format(round($points/$leaderboardCounter))." CLAW/CARBON";
				echo "</td>";
			}
			echo "</tr>";
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
		echo "</table>";
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
		echo "<table id='transactions' cellspacing='0'>";
		echo "<th>Rank</th><th>Avatar</th><th align='left'>Username</th><th>".($monthly ? "Level" : "Average Level")."</th><th>".($monthly ? "Score" : "Average Score")."</th><th>Completions</th>";
		if($monthly){
			echo "<th>Projected Rewards</th>";
		}
		while($row = $result->fetch_assoc()) {
			$leaderboardCounter++;
			$counter++;
			$trophy = "";
			$level = $row["max_level"];
			$score = $row["max_score"];
			if($leaderboardCounter == 1){
				//$width = 50;
				$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter == 2){
				//$width = 45;
				if($last_total != $score){
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
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
					$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
					$third_total = $score;
				}else{
					$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
					$leaderboardCounter--;
				}
				if(isset($_SESSION['userData']['user_id'])){
					if($_SESSION['userData']['user_id'] == $row["user_id"]){
						$fireworks = true;
					}
				}
			}else if($leaderboardCounter > 3 && $third_total == $score){
				$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
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
			echo "<tr class='".$highlight."'>";
			echo "<td align='center'>";
			echo "<strong>".(($trophy == "")?(($leaderboardCounter<10)?"0":"").$leaderboardCounter.".":$trophy)."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			$avatar = "<img style='width:".$width."px' onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon rounded-full'/>";
			echo $avatar;
			echo "</td>";
			echo "<td align='left'>";
			$username = "";
			if($row["visibility"] == "2"){
				$username = "<a href='showcase.php?username=".$row["username"]."'>".$row["username"]. "</a>";
			}else{
				$username = $row["username"];
			}
			echo "<strong style='font-size:20px'>".$username."</strong>";
			echo "</td>";
			echo "<td align='center'>";
			echo round($row["max_level"]);
			echo "</td>";
			echo "<td align='center'>";
			echo round($row["max_score"]);
			echo "</td>";
			echo "<td align='center'>";
			echo number_format($row["completions"]);
			echo "</td>";
			if($monthly){
				echo "<td align='center'>";
				echo number_format(round($claw/$leaderboardCounter))." CLAW";
				echo "</td>";
			}
			echo "</tr>";
			$last_total = $score;
			if($rewards){
				updateBalance($conn, $row["user_id"], 36, round($claw/$leaderboardCounter));
				logCredit($conn, $row["user_id"], round($claw/$leaderboardCounter), 36);
				
				// Limit number of rows added to description to prevent going over Discord notification text length limit
				if($counter <= 45){
					$description .= "- ".(($leaderboardCounter<10)?"0":"").$leaderboardCounter." "."<@".$row["discord_id"]."> Level: ".$row["max_level"].", Score: ".$row["max_score"].", Attempts: ".$row["attempts"]."\r\n";
					$description .= "        ".number_format(round($claw/$leaderboardCounter))." CLAW\r\n";
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
		echo "</table>";
		if($fireworks){
			fireworks();
		}
	}else{
		echo "<p>No Skull Swaps have been completed yet for the week.</p>";
		echo '<form action="leaderboards.php" method="post"><input type="hidden" name="filterbyswaps" id="filterbyswaps" value="monstrocity"><input type="submit" class="small-button" value="View All Monstrocity Leaderboard"></form><br><br>';
		echo '<img style="width:100%;" src="images/todolist.png"/>';
	}
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
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}
}

function updateRealmTheme($conn, $realm_id, $theme_id){
	$sql = "UPDATE realms SET theme_id='".$theme_id."' WHERE id='".$realm_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

function updateRealmFaction($conn, $realm_id, $faction){
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
		$sql = "SELECT id FROM realms WHERE user_id='".$_SESSION['userData']['user_id']."'";
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
		$sql = "INSERT INTO upgrades (realm_id, location_id, duration)
		VALUES ('".$realm_id."', '".$location_id."', '".$duration."')";

		if ($conn->query($sql) === TRUE) {
		  //echo "New record created successfully";
		  updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, -$cost);
		  logDebit($conn, $_SESSION['userData']['user_id'], 0, $cost, $project_id, $crafting=0, $mission_id=0, $location_id);
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}
}

function getRealmLocationUpgrade($conn, $realm_id, $location_id){
	$sql = "SELECT id, location_id, duration, created_date FROM upgrades WHERE realm_id = '".$realm_id."' AND location_id = '".$location_id."'";
	$result = $conn->query($sql);
	
	$status = array();
	if ($result->num_rows > 0) {
		while($row = $result->fetch_assoc()) {
			$date = strtotime('+'.$row["duration"].' day', strtotime($row["created_date"]));
			$remaining = $date - time();
			$days_remaining = floor(($remaining / 86400));
			$hours_remaining = floor(($remaining % 86400) / 3600);
			$minutes_remaining = floor(($remaining % 3600) / 60);
			if($date > time()){
				$time_message = "<strong>Upgrade Level:</strong> ".$row["duration"]."<br>";
				$time_message .= "<strong>Duration:</strong> ".$row["duration"]." ".(($row["duration"]==1)?"Day":"Days")."<br>";
				$time_message .= "<strong>Remaining:</strong> ".$days_remaining."d ".$hours_remaining."h ".$minutes_remaining."m";
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
		$sql = "SELECT id, location_id, duration, created_date FROM upgrades WHERE realm_id = '".$realm_id."'";
		$result = $conn->query($sql);
		
		$status = array();
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$date = strtotime('+'.$row["duration"].' day', strtotime($row["created_date"]));
				$remaining = $date - time();
				$days_remaining = floor(($remaining / 86400));
				$hours_remaining = floor(($remaining % 86400) / 3600);
				$minutes_remaining = floor(($remaining % 3600) / 60);
				if($date > time()){
					$time_message = "<strong>Upgrade Level:</strong> ".$row["duration"]."<br>";
					$time_message .= "<strong>Duration:</strong> ".$row["duration"]." ".(($row["duration"]==1)?"Day":"Days")."<br>";
					$time_message .= "<strong>Remaining:</strong> ".$days_remaining."d ".$hours_remaining."h ".$minutes_remaining."m";
					$status[$row['location_id']] = $time_message;
				}else{
					upgradeRealmLocationLevel($conn, $realm_id, $row['location_id'], $row["duration"]);
					deleteRealmLocationUpgrade($conn, $realm_id, $row['location_id']);
					//$time_message = "0d 0h 0m";
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
		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				$raw_offense = calculateRawRaidOffense($conn, $offense_id);
				$raw_defense = calculateRawRaidDefense($conn, $row['realm_id']);
				$raw_defense_offense = calculateRawRaidOffense($conn, $row['realm_id']);
				$offense = calculateRaidOffense($conn, $offense_id);
				$defense = calculateRaidDefense($conn, $row['realm_id']);
				$total = $defense + $offense;
				$percentage = (100/$total);
				$defense_threshold = $percentage * $defense;
				$offense_threshold = $percentage * $offense;
				$duration = ceil($defense/$offense);
				$balances = getRealmBalances($conn, $row['user_id']);
				
				$key = "";
				if($sort == "random"){
					$key = $row['realm_id'];
				}else if($sort == "weakness" || $sort == "strength"){
					$key = $raw_defense.".".$raw_defense_offense.$row['realm_id'];
				}else if($sort == "wealth"){
					$key = array_sum($balances).".".$row['realm_id'];
				}
				$output[$key] = "";
				$output[$key] .= "<th>".ucfirst($row['realm_name'])."</th><th>Raid Details</th><th>Location Levels</th><th>Top Points Balances</th>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td width='25%' valign='top' align='left'>";
				$output[$key] .= "<table id='transactions' style='border-style:none'>";
				$output[$key] .= "<tr><td align='center'>";
				$output[$key] .= "<img src='images/themes/".$row["theme_id"].".jpg' style='width:100%;max-width:358px'/>";
				$output[$key] .= "</td></tr>";
				$output[$key] .= "</table>";
				if($duration <= 0){
					$duration = 1;
				}
				$output[$key] .= "</td>";
				$output[$key] .= "<td width='25%' valign='top' align='left'>";
				$output[$key] .= "<table id='transactions' style='border-style:none'>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td align='right' width='50%'>";
					if($row["avatar"] != ""){
						$output[$key] .= "<img style='width:50px' onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon'/>";
					}
				$output[$key] .= "</td>";
				$output[$key] .= "<td width='50%'>".$row["username"]."</td>";
				$output[$key] .= "</tr>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td align='right' width='50%'>";
					if($row["currency"] != ""){
						$output[$key] .= "<img style='width:50px' src='/staking/icons/".strtolower($row["currency"]).".png' class='icon'/>";
					}
				$output[$key] .= "</td>";
				$output[$key] .= "<td width='50%'>".$row["project_name"]."</td>";
				$output[$key] .= "</tr>";
				$output[$key] .= "</tr>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td align='right'>Level ".$offense."</td>";
				$output[$key] .= "<td>Your Offense</td>";
				$output[$key] .= "</tr>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td align='right'>Level ".$defense."</td>";
				$output[$key] .= "<td>Their Defense</td>";
				$output[$key] .= "</tr>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td align='right' width='50%'>".$duration." ".(($duration == 1)?"day":"days")."</td>";
				$output[$key] .= "<td width='50%'>Raid Duration</td>";
				$output[$key] .= "</tr>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td align='right'>".round($offense_threshold)."%"."</td>";
				$output[$key] .= "<td>Success Chance</td>";
				$output[$key] .= "</tr>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td align='right'>&nbsp;";
				$output[$key] .= "</td>";
				$output[$key] .= "<td>";
				$unset = false;
				if(checkRealmRaidStatus($conn, $row["realm_id"])){
					$value = "START RAID";
					$raiding = false;
					if(in_array($offense_id, getRecentRealmsRaiding($conn, $row["realm_id"]))){
						$raiding = true;
						$value = "GET REVENGE";
					}
					if($offense_id == $row["realm_id"] || $offense_faction == $row["project_id"]){
						if(!$raiding){
							if($offense_id == $row["realm_id"]){
								$value = "SELF DESTRUCT";
							}else{
								$value = "FRIENDLY FIRE";
							}
						}else{
							if($offense_id == $row["realm_id"]){
								$value = "SELF ANNIHILATE";
							}else{
								$value = "PUNISH TRAITOR";
							}
						}
					}
					// Prevents established realms from rading new realms, but allows for new realms to raid each other.
					if(($raw_defense == 0 && $raw_offense != 0) && !$raiding){
						$output[$key] .= "<strong>Establishing Realm</strong><br><br>";
						$unset = true;
					}else if((($offense-$defense) > 3) && !$raiding){
						$level_range = (($offense-$defense)-3);
						$output[$key] .= "<strong>".$level_range." ".(($level_range == 1)?"Level":"Levels")." Out of Range</strong><br><br>";
						$unset = true;
					}else if(!in_array($row['realm_id'], getRecentRaidedRealms($conn)) || $raiding){
						if(checkMaxRaids($conn, $offense_id)){
							$output[$key] .= "<input type='button' class='raid-button' value='".$value."' onclick='startRaid(this, ".$row['realm_id'].", ".$duration.");'><br><br>";
						}else{
							$output[$key] .= "<strong>Max Raids Reached</strong><br><br>";
						}
					}else{
						$output[$key] .= "<strong>Recovering from Raid</strong><br><br>";
						$unset = true;
					}
				}else{
					$output[$key] .= "<strong>Raid in Progress</strong><br><br>";
					$unset = true;
				}
				$output[$key] .= "</td>";
				$output[$key] .= "</tr>";
				$output[$key] .= "</table>";
				$output[$key] .= "</td>";
				$output[$key] .= "<td width='25%' valign='top' align='left'>";
				$output[$key] .= "<table id='transactions' style='border-style:none'>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td align='right' width='50%'><strong>#</strong></td>";
				$output[$key] .= "<td width='50%'><strong>Location</strong></td>";
				$output[$key] .= "</tr>";
				$levels = getRealmLocationNamesLevels($conn, $row['realm_id']);
				foreach($levels AS $location_name => $level){
					$output[$key] .= "<tr>";
					$output[$key] .= "<td align='right' width='50%'>".$level."</td>";
					$output[$key] .= "<td width='50%'>".ucfirst($location_name)."</td>";
					$output[$key] .= "</tr>";
				}
				$output[$key] .= "</table>";
				$output[$key] .= "</td>";
				$output[$key] .= "<td width='25%' valign='top' align='right'>";
				$output[$key] .= "<table id='transactions' style='border-style:none'>";
				$output[$key] .= "<tr>";
				$output[$key] .= "<td align='right' width='50%'>".number_format(array_sum($balances))."</td>";
				$output[$key] .= "<td width='50%'>TOTAL POINTS</td>";
				$output[$key] .= "</tr>";
				$balances = array_slice($balances, 0, 7, true);
				foreach($balances AS $currency => $balance){
					$output[$key] .= "<tr>";
					$output[$key] .= "<td align='right' width='50%'>".number_format($balance)."</td>";
					$output[$key] .= "<td width='50%'>".$currency."</td>";
					$output[$key] .= "</tr>";
				}
				$output[$key] .= "</table>";
				$output[$key] .= "</td>";
				$output[$key] .= "</tr>";
				if($unset && $group == "Eligible"){
					unset($output[$key]);
				}
			}
			
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
				echo "<table width='100%' id='transactions'>";
				foreach($output AS $key => $val){
					echo $val;
				}
				echo "</table>";
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
		$defense = ceil($defense/3);
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
		$offense = ceil($offense/3);
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

function startRaid($conn, $defense_id, $duration){
	if(isset($_SESSION['userData']['user_id'])){
		$offense_id = getRealmID($conn);
		$sql = "INSERT INTO raids (offense_id, defense_id, duration)
		VALUES ('".$offense_id."', '".$defense_id."', '".$duration."')";

		if ($conn->query($sql) === TRUE) {
		  echo "<strong>Raid Started</strong>";
		} else {
		  echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}
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
			$final_output .= "<h2 class='raid-title'>".ucfirst($type)." ".ucfirst($status)."&nbsp;<img style='padding-right:20px;cursor:pointer;' class='icon' id='".$arrow."' src='icons/".$arrow.".png' onclick='toggleRaids(this, \"".$type."\", \"".$status."\")'/></h2>";
			$final_output .= '<div class="content raids" id="'.$type."-".$status.'-raids-container" style="display:'.$display.'">';
			$status = "";
			$final_output .= "<table id='transactions'>";
			$final_output .=  "<th width='6%'>Icon</th><th width='20%' align='left'>Realm</th><th width='6%'>Avatar</th><th width='20%' align='left'>Username</th><th width='12%' align='left'>Time Left</th><th width='12%' align='left'>".$results1." Results</th></th><th width='12%' align='left'>".$results2." Results</th>";
			$rows = array();
			while($row = $result->fetch_assoc()) {
				$date = strtotime('+'.$row["duration"].' day', strtotime($row["created_date"]));
				$remaining = $date - time();
				$days_remaining = floor(($remaining / 86400));
				$hours_remaining = floor(($remaining % 86400) / 3600);
				$minutes_remaining = floor(($remaining % 3600) / 60);
				if($date > time()){
					$time_message = $days_remaining."d ".$hours_remaining."h ".$minutes_remaining."m";
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
	
					// Calculate thresholds for random number generation
					$defense_threshold = $percentage * $defense;
					$offense_threshold = $percentage * $offense;
					$defense_results = round($defense_threshold,2)."%";
					$offense_results = round($offense_threshold,2)."%";
				}else{
					$time_message = "0d 0h 0m";
					$status = "Completed";
					if($row["outcome"] == 0){
						$outcome = endRaid($conn, $row['raid_id']);
					}else{
						$outcome = $row["outcome"];
					}
					// Offense Success
					if($outcome == 1){
						$offense_results = "<strong>Success</strong><br>";
						$offense_results .= "<br>".getRaidProjectBalanceAmount($conn, $row['raid_id'], "offense");
						$defense_results = "<strong>Failure</strong><br>";
						$defense_results .= "<br>".getRaidProjectBalanceAmount($conn, $row['raid_id'], "defense");
						$defense_results .= getRaidLocationLevelAmount($conn, $row['raid_id'], "defense");
					}
					// Defense Success
					else if($outcome == 2){
						$offense_results = "<strong>Failure</strong><br>";
						$offense_results .= getRaidLocationLevelAmount($conn, $row['raid_id'], "offense");
						$defense_results = "<strong>Success</strong><br>";
						$defense_results .= getRaidLocationLevelAmount($conn, $row['raid_id'], "defense");
					}
				}
				if($status == "Completed"){
					$decimal = $row["created_date"].$row["raid_id"];
				}else{
					$decimal = $days_remaining.".".(($hours_remaining<10)?"0".$hours_remaining:$hours_remaining).(($minutes_remaining<10)?"0".$minutes_remaining:$minutes_remaining).$row["raid_id"];
				}
				$rows[$decimal] = "";
				$rows[$decimal] .= "<tr>";
				$rows[$decimal] .= "<td valign='top'>";
				$rows[$decimal] .= "<img style='width:50px;padding-top:10px;' onError='this.src=\"/staking/icons/skull.png\";' src='images/themes/".$row["theme_id"].".jpg' class='icon'/>";
				$rows[$decimal] .= "</td>";
				$rows[$decimal] .= "<td valign='top' align='left'><br>";
				$rows[$decimal] .= $row["realm_name"];
				$rows[$decimal] .= "</td>";
				$rows[$decimal] .= "<td valign='top'>";
				$rows[$decimal] .= "<img style='width:50px' onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon'/>";
				$rows[$decimal] .= "</td>";
				$rows[$decimal] .= "<td valign='top' align='left'><br>";
				$rows[$decimal] .= $row["username"];
				$rows[$decimal] .= "</td>";
				$rows[$decimal] .= "<td valign='top' align='left'><br>";
				$rows[$decimal] .= $time_message;
				$rows[$decimal] .= "</td>";
				$rows[$decimal] .= "<td valign='top' align='left'><br>";
				$rows[$decimal] .= $offense_results;
				$rows[$decimal] .= "<br></td>";
				$rows[$decimal] .= "<td valign='top' align='left'><br>";
				$rows[$decimal] .= $defense_results;
				$rows[$decimal] .= "<br><br></td>";
				$rows[$decimal] .= "</tr>";
				$rows[$decimal] .= "<tr id='raid-progress-".$row["raid_id"]."'>";
				$rows[$decimal] .= "<td colspan='7' style='padding:0px;'>";
				$rows[$decimal] .= "<div class='w3-border'>";
				if($status == "Completed"){
					$percentage = 100;
				}else{
					$percentage = 100-((($days_remaining+($hours_remaining/24)+($minutes_remaining/1440)) / $row["duration"])*100);
				}
				$rows[$decimal] .= "<div class='w3-grey' style='width:".$percentage."%;opacity:0.3;'></div>";
				$rows[$decimal] .= "</div>";
				$rows[$decimal] .= "</td>";
				$rows[$decimal] .= "</tr>";
			}
			ksort($rows);
			if(strtolower($status) == "completed"){
				$rows = array_reverse($rows);
			}
			foreach($rows AS $duration => $output){
			    $final_output .= $output;
			}
			$final_output .= "</table>";
			if(!$history && $status == "Completed"){
				$final_output .= "<a href='raids.php'>View ".ucfirst($type)." Raids History</a>";
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
	
  	echo "<h2 class='raid-title'>Raid Stats&nbsp;<img style='padding-right:20px;cursor:pointer;' class='icon' id='".$arrow."' src='icons/".$arrow.".png' onclick='toggleTotalRaids(this)'/></h2>";
  	echo '<a name="total-missions" id="total-missions"></a>';
    echo '<div class="content missions" id="total-missions-container" style="display:'.$display.'">';
	echo "<table id='transactions' cellspacing='0'>";
	echo "<th width='14%'>Timeframe</th><th width='14%'>Score</th><th width='14%'>Total Raids</th><th width='14%'>In Progress</th><th width='14%'>Success</th><th width='14%'>Failure</th><th width='14%'>Leaderboard</th>";
	if ($month_result->num_rows > 0) {
		while($month_row = $month_result->fetch_assoc()) {
			echo "<tr class='month-row'>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/calendar.png'/>";
			echo date('F');
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/score.png'/>";
			echo number_format(calculateScore($month_row["total_duration"], $month_row["success"], $month_row["failure"], $month_row["progress"]));
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/total.png'/>";
			echo $month_row["total"];
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/time.png'/>";
			echo $month_row["progress"];
			echo "</td>";
			echo "<td align='center'>";
			$success_percentage = 0;
			$over50 = "";
			if($month_row["total"]-$month_row["progress"] != 0){
				$success_percentage = $month_row["success"]/($month_row["total"]-$month_row["progress"])*100;
			}else{
				$success_percentage = 0;
			}
			if(round($success_percentage) > 50){
				$over50 = "over50";
			}
			?>
			<div class="progress-circle <?php echo $over50;?> p<?php echo round($success_percentage);?>">
			   <span><?php echo round($success_percentage, 2)."%";?></span>
			   <div class="left-half-clipper">
			      <div class="first50-bar success"></div>
			      <div class="value-bar success-bar"></div>
			   </div>
			</div>
			<?php
			echo "<span class='outcome-total'>".$month_row["success"]."</span>";
			echo "</td>";
			echo "<td align='center'>";
			$failure_percentage = 0;
			$over50 = "";
			if($month_row["total"]-$month_row["progress"] != 0){
				$failure_percentage = $month_row["failure"]/($month_row["total"]-$month_row["progress"])*100;
			}else{
				$failure_percentage = 0;
			}
			if(round($failure_percentage) > 50){
				$over50 = "over50";
			}
			?>
			<div class="progress-circle <?php echo $over50;?> p<?php echo round($failure_percentage);?>">
			   <span><?php echo round($failure_percentage, 2)."%";?></span>
			   <div class="left-half-clipper">
			      <div class="first50-bar failure"></div>
			      <div class="value-bar failure-bar"></div>
			   </div>
			</div>
			<?php
			echo "<span class='outcome-total'>".$month_row["failure"]."</span>";
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/trophy.png'/>";
			echo "<form action='leaderboards.php' method='post'><input type='hidden' name='filterby' id='filterby' value='monthly-raids'/><input type='submit' class='small-button' value='".date("F")."'/></form>";
			echo "</td>";
			echo "</tr>";
		}
	}
$sql = "SELECT (SELECT COUNT(success_raids.id) FROM raids AS success_raids INNER JOIN realms AS success_realms ON success_realms.id = success_raids.offense_id INNER JOIN users AS success_users ON success_users.id = success_realms.user_id WHERE success_raids.outcome = '1' AND success_users.id = users.id) AS success, 
               (SELECT COUNT(failed_raids.id) FROM raids AS failed_raids INNER JOIN realms AS failed_realms ON failed_realms.id = failed_raids.offense_id INNER JOIN users AS failed_users ON failed_users.id = failed_realms.user_id  WHERE failed_raids.outcome = '2' AND failed_users.id = users.id) AS failure, 
     		   (SELECT COUNT(progress_raids.id) FROM raids AS progress_raids INNER JOIN realms AS progress_realms ON progress_realms.id = progress_raids.offense_id INNER JOIN users AS progress_users ON progress_users.id = progress_realms.user_id  WHERE progress_raids.outcome = '0' AND progress_users.id = users.id) AS progress, 
		        COUNT(raids.id) AS total, SUM(raids.duration) AS total_duration, users.id AS user_id 
			    FROM users INNER JOIN realms ON users.id = realms.user_id INNER JOIN raids ON raids.offense_id = realms.id WHERE users.id = '".$_SESSION['userData']['user_id']."'";
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
			while($row = $result->fetch_assoc()) {
				echo "<tr>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/infinity.png'/>";
				echo "All Time";
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/all-score.png'/>";
				echo number_format(calculateScore($row["total_duration"], $row["success"], $row["failure"], $row["progress"]));
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/grand-total.png'/>";
				echo number_format($row["total"]);
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/all-time.png'/>";
				echo $row["progress"];
				echo "</td>";
				echo "<td align='center'>";
				$success_percentage = 0;
				$over50 = "";
				if($row["total"]-$row["progress"] != 0){
					$success_percentage = $row["success"]/($row["total"]-$row["progress"])*100;
				}else{
					$success_percentage = 0;
				}
				if(round($success_percentage) > 50){
					$over50 = "over50";
				}
				?>
				<div class="progress-circle <?php echo $over50;?> p<?php echo round($success_percentage);?>">
				   <span><?php echo round($success_percentage, 2)."%";?></span>
				   <div class="left-half-clipper">
				      <div class="first50-bar success"></div>
				      <div class="value-bar success-bar"></div>
				   </div>
				</div>
				<?php
				echo "<span class='outcome-total'>".number_format($row["success"])."</span>";
				echo "</td>";
				echo "<td align='center'>";
				$failure_percentage = 0;
				$over50 = "";
				if($row["total"]-$row["progress"] != 0){
					$failure_percentage = $row["failure"]/($row["total"]-$row["progress"])*100;
				}else{
					$failure_percentage = 0;
				}
				if(round($failure_percentage) > 50){
					$over50 = "over50";
				}
				?>
				<div class="progress-circle <?php echo $over50;?> p<?php echo round($failure_percentage);?>">
				   <span><?php echo round($failure_percentage, 2)."%";?></span>
				   <div class="left-half-clipper">
				      <div class="first50-bar failure"></div>
				      <div class="value-bar failure-bar"></div>
				   </div>
				</div>
				<?php
				echo "<span class='outcome-total'>".number_format($row["failure"])."</span>";
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/crown.png'/>";
				echo "<form action='leaderboards.php' method='post'><input type='hidden' name='filterby' id='filterby' value='raids'/><input type='submit' class='small-button' value='All Time'/></form>";
				echo "</td>";
				echo "</tr>";
			}
		}
		echo "</table><br>";
		echo "</div>";
}

function getTotalFactionRaids($conn){
	$realm_id = getRealmID($conn);
	$project_id = getRealmFaction($conn, $realm_id);
	/*$month_sql = "SELECT (SELECT COUNT(success_raids.id) FROM raids AS success_raids INNER JOIN realms AS success_realms ON success_realms.id = success_raids.offense_id INNER JOIN users AS success_users ON success_users.id = success_realms.user_id 
				  WHERE success_raids.outcome = '1' AND success_users.id = users.id AND DATE(success_raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS success, 
	             (SELECT COUNT(failed_raids.id) FROM raids AS failed_raids INNER JOIN realms AS  failed_realms ON failed_realms.id = failed_raids.offense_id INNER JOIN users AS failed_users ON failed_users.id = failed_realms.user_id 
				  WHERE failed_raids.outcome = '2' AND failed_users.id = users.id AND DATE(failed_raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS failure, 
				 (SELECT COUNT(progress_raids.id) FROM raids AS progress_raids INNER JOIN realms AS  progress_realms ON progress_realms.id = progress_raids.offense_id INNER JOIN users AS progress_users ON progress_users.id = progress_realms.user_id 
				  WHERE progress_raids.outcome = '0' AND progress_users.id = users.id AND DATE(progress_raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS progress, 
	        	  COUNT(raids.id) AS total, SUM(raids.duration) AS total_duration, users.id AS user_id 
		    	  FROM users INNER JOIN realms ON users.id = realms.user_id INNER JOIN raids ON raids.offense_id = realms.id WHERE users.id = '".$_SESSION['userData']['user_id']."' AND DATE(raids.created_date) >= DATE_FORMAT(CURDATE(),'%Y-%m-01')";*/
				  
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
  	echo "<h2 class='raid-title'>Faction Stats&nbsp;<img style='padding-right:20px;cursor:pointer;' class='icon' id='".$arrow."' src='icons/".$arrow.".png' onclick='toggleTotalFactions(this)'/></h2>";
  	echo '<a name="total-factions" id="total-factions"></a>';
    echo '<div class="content missions" id="total-factions-container" style="display:'.$display.'">';
	echo "<h3>".$project["name"]."</h3>";
	echo "<table id='transactions' cellspacing='0'>";
	echo "<th width='14%'>Timeframe</th><th width='14%'>Score</th><th width='14%'>Total Raids</th><th width='14%'>In Progress</th><th width='14%'>Success</th><th width='14%'>Failure</th><th width='14%'>Leaderboard</th>";
	if ($month_result->num_rows > 0) {
		while($month_row = $month_result->fetch_assoc()) {
			echo "<tr class='month-row'>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/calendar.png'/>";
			echo date('F');
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/score.png'/>";
			echo number_format(calculateScore($month_row["total_duration"], $month_row["success"], $month_row["failure"], $month_row["progress"]));
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/total.png'/>";
			echo $month_row["total"];
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/time.png'/>";
			echo $month_row["progress"];
			echo "</td>";
			echo "<td align='center'>";
			$success_percentage = 0;
			$over50 = "";
			if($month_row["total"]-$month_row["progress"] != 0){
				$success_percentage = $month_row["success"]/($month_row["total"]-$month_row["progress"])*100;
			}else{
				$success_percentage = 0;
			}
			if(round($success_percentage) > 50){
				$over50 = "over50";
			}
			?>
			<div class="progress-circle <?php echo $over50;?> p<?php echo round($success_percentage);?>">
			   <span><?php echo round($success_percentage, 2)."%";?></span>
			   <div class="left-half-clipper">
			      <div class="first50-bar success"></div>
			      <div class="value-bar success-bar"></div>
			   </div>
			</div>
			<?php
			echo "<span class='outcome-total'>".$month_row["success"]."</span>";
			echo "</td>";
			echo "<td align='center'>";
			$failure_percentage = 0;
			$over50 = "";
			if($month_row["total"]-$month_row["progress"] != 0){
				$failure_percentage = $month_row["failure"]/($month_row["total"]-$month_row["progress"])*100;
			}else{
				$failure_percentage = 0;
			}
			if(round($failure_percentage) > 50){
				$over50 = "over50";
			}
			?>
			<div class="progress-circle <?php echo $over50;?> p<?php echo round($failure_percentage);?>">
			   <span><?php echo round($failure_percentage, 2)."%";?></span>
			   <div class="left-half-clipper">
			      <div class="first50-bar failure"></div>
			      <div class="value-bar failure-bar"></div>
			   </div>
			</div>
			<?php
			echo "<span class='outcome-total'>".$month_row["failure"]."</span>";
			echo "</td>";
			echo "<td align='center'>";
			echo "<img class='missions-icon' src='icons/trophy.png'/>";
			echo "<form action='leaderboards.php' method='post'><input type='hidden' name='filterby' id='filterby' value='monthly-factions'/><input type='submit' class='small-button' value='".date("F")."'/></form>";
			echo "</td>";
			echo "</tr>";
		}
	}
/*$sql = "SELECT (SELECT COUNT(success_raids.id) FROM raids AS success_raids INNER JOIN realms AS success_realms ON success_realms.id = success_raids.offense_id INNER JOIN users AS success_users ON success_users.id = success_realms.user_id WHERE success_raids.outcome = '1' AND success_users.id = users.id) AS success, 
               (SELECT COUNT(failed_raids.id) FROM raids AS failed_raids INNER JOIN realms AS failed_realms ON failed_realms.id = failed_raids.offense_id INNER JOIN users AS failed_users ON failed_users.id = failed_realms.user_id  WHERE failed_raids.outcome = '2' AND failed_users.id = users.id) AS failure, 
     		   (SELECT COUNT(progress_raids.id) FROM raids AS progress_raids INNER JOIN realms AS progress_realms ON progress_realms.id = progress_raids.offense_id INNER JOIN users AS progress_users ON progress_users.id = progress_realms.user_id  WHERE progress_raids.outcome = '0' AND progress_users.id = users.id) AS progress, 
		        COUNT(raids.id) AS total, SUM(raids.duration) AS total_duration, users.id AS user_id 
			    FROM users INNER JOIN realms ON users.id = realms.user_id INNER JOIN raids ON raids.offense_id = realms.id WHERE users.id = '".$_SESSION['userData']['user_id']."'";*/
	
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
			while($row = $result->fetch_assoc()) {
				echo "<tr>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/infinity.png'/>";
				echo "All Time";
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/all-score.png'/>";
				echo number_format(calculateScore($row["total_duration"], $row["success"], $row["failure"], $row["progress"]));
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/grand-total.png'/>";
				echo number_format($row["total"]);
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/all-time.png'/>";
				echo $row["progress"];
				echo "</td>";
				echo "<td align='center'>";
				$success_percentage = 0;
				$over50 = "";
				if($row["total"]-$row["progress"] != 0){
					$success_percentage = $row["success"]/($row["total"]-$row["progress"])*100;
				}else{
					$success_percentage = 0;
				}
				if(round($success_percentage) > 50){
					$over50 = "over50";
				}
				?>
				<div class="progress-circle <?php echo $over50;?> p<?php echo round($success_percentage);?>">
				   <span><?php echo round($success_percentage, 2)."%";?></span>
				   <div class="left-half-clipper">
				      <div class="first50-bar success"></div>
				      <div class="value-bar success-bar"></div>
				   </div>
				</div>
				<?php
				echo "<span class='outcome-total'>".number_format($row["success"])."</span>";
				echo "</td>";
				echo "<td align='center'>";
				$failure_percentage = 0;
				$over50 = "";
				if($row["total"]-$row["progress"] != 0){
					$failure_percentage = $row["failure"]/($row["total"]-$row["progress"])*100;
				}else{
					$failure_percentage = 0;
				}
				if(round($failure_percentage) > 50){
					$over50 = "over50";
				}
				?>
				<div class="progress-circle <?php echo $over50;?> p<?php echo round($failure_percentage);?>">
				   <span><?php echo round($failure_percentage, 2)."%";?></span>
				   <div class="left-half-clipper">
				      <div class="first50-bar failure"></div>
				      <div class="value-bar failure-bar"></div>
				   </div>
				</div>
				<?php
				echo "<span class='outcome-total'>".number_format($row["failure"])."</span>";
				echo "</td>";
				echo "<td align='center'>";
				echo "<img class='missions-icon' src='icons/crown.png'/>";
				echo "<form action='leaderboards.php' method='post'><input type='hidden' name='filterby' id='filterby' value='factions'/><input type='submit' class='small-button' value='All Time'/></form>";
				echo "</td>";
				echo "</tr>";
			}
		}
		echo "</table><br>";
		echo "</div>";
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
	// Get raid faction realm ID
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
	
	// Failure = 2, Success = 1
	$outcome = rand(1, 100);
	
	// Determine faction winner based on threshold
	$winner = "";
	if($outcome < $defense_threshold){
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
	
	// Based on pure outcome of raid, determine location leveling and project rewards (if any), update cross reference tables, update balances, and record transactions accordingly
	if($outcome == 1){
		// If Defense Offense is above level 10, damage a random offense location to help maintain gamification balance and prevent runaway offenses
		$defense_offense = calculateRaidOffense($conn, $defense_id);
		if($defense_offense > 10){
			alterRealmLocationLevel($conn, $raid_id, "defense", selectRandomLocationID($conn, "offense"), 1, "debit");
		}else{
			// Damage random defense location for defense
			alterRealmLocationLevel($conn, $raid_id, "defense", selectRandomLocationID($conn, "defense"), 1, "debit");
		}
		// Reward random points to offense from defense, credit offense and debit defense the same project points
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
		// Divide balance by 100 and multiply by the absolute value of the difference between offense and defense level, this ensures that a weaker realm successfully raiding a stronger realm is rewarded for their risk taking by extracting the absolute value of the negative result
		$amount = round(($balance/100)*$difference);
		// Restrict percentage of loot awarded to no more than 500 points to prevent players from getting too many points taken away and discouraging them from participating
		if($amount > 500){
			$amount = 500;
		}
		assignRealmProjectRewards($conn, $raid_id, $project_id, $amount);
	}else if($outcome == 2){
		// Damage to random offense location for offense
		$offense_id = selectRandomLocationID($conn, "offense");
		alterRealmLocationLevel($conn, $raid_id, "offense", $offense_id, 1, "debit");
		// Improve same offense location for defense
		alterRealmLocationLevel($conn, $raid_id, "defense", $offense_id, 1, "credit");
		// 1 in 3 chance of damage to offense portal
		if(rand(1, 3) == 2){
			$portal_id = 1;
			alterRealmLocationLevel($conn, $raid_id, "offense", $portal_id, 1, "debit");
		}
	}
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
	if($type == "deactivate"){
		$sql = "UPDATE realms SET active = '0', created_date = '".date('Y-m-d H:i:s')."' WHERE id='".$realm_id."' AND user_id = '".$_SESSION['userData']['user_id']."'";
		if ($conn->query($sql) === TRUE) {
			echo "Your Realm has been deactivated.\r\n\r\nYou will not be able to reactivate it for 30 days.";
		} else {
			echo "Error: " . $sql . "<br>" . $conn->error;
		}
	}else if($type == "reactivate"){
		$sql = "UPDATE realms SET active = '1' WHERE id='".$realm_id."' AND user_id = '".$_SESSION['userData']['user_id']."'";
		if ($conn->query($sql) === TRUE) {
			echo "Your Realm has been reactivated.";
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
	$sql = 'SELECT users.username AS user_name, realms.id AS realm_id, concat("https://cdn.discordapp.com/avatars/",users.discord_id,"/",users.avatar,".jpg") AS user_image, realms.name AS realm_name, concat("https://skulliance.io/staking/images/themes/",realms.theme_id,".jpg") AS realm_image, projects.name AS faction_name FROM `realms` INNER JOIN projects ON projects.id = realms.project_id INNER JOIN users ON users.id = realms.user_id WHERE realms.active = 1 ORDER BY faction_name';
	
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	    echo "<script type='text/javascript'>";
	    echo "window.csvData = `";
	    echo '"user_name","user_image","realm_name","realm_image","faction_name"';
	    echo "\n";
    	
		$realms = array();
	    while ($row = $result->fetch_assoc()) {
			$level = array_sum(getRealmLocationNamesLevels($conn, $row['realm_id']));
			if($level != 0){
				$realms[$row['realm_id']] = array();
				$realms[$row['realm_id']]["user_name"] = $row['user_name'];
				$realms[$row['realm_id']]["user_image"] = $row['user_image'];
				$realms[$row['realm_id']]["realm_name"] = $row['realm_name'];
				$realms[$row['realm_id']]["realm_image"] = $row['realm_image'];
				$realms[$row['realm_id']]["faction_name"] = $row['faction_name'];
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
	        echo '"'.$realm['faction_name'].'"';
			if ($realm_count < $realm_total) {
				echo "\n"; // Only add newline if not the last realm
			}
		}
    
	    echo "`;";
	    echo "</script>";
	}
}

/* END REALMS */

/* SKULL SWAP */

function saveSwapScore($conn, $score){
	if(isset($_SESSION['userData']['user_id'])){
		// Check if unrewarded score exists.
		// If it exists, check if new score is higher than existing score. If it is, update unrewarded score.
		// If it doesn't exist, create a new score in the database
		$current_score = getSwapScore($conn);
		if(isset($current_score)){
			if($score > $current_score){
				$sql = "UPDATE scores SET score = '".$score."', attempts = attempts + 1 WHERE user_id='".$_SESSION['userData']['user_id']."' AND reward = '0' AND project_id = '0'";
				if ($conn->query($sql) === TRUE) {
					echo "high";
				} else {
					echo "Error: " . $sql . "<br>" . $conn->error;
				}
			}else{
				$sql = "UPDATE scores SET attempts = attempts + 1 WHERE user_id='".$_SESSION['userData']['user_id']."' AND reward = '0' AND project_id = '0'";
				if ($conn->query($sql) === TRUE) {
					echo "low";
				} else {
					echo "Error: " . $sql . "<br>" . $conn->error;
				}
			}
		}else{
			// Create new score
			$sql = "INSERT INTO scores (user_id, score, attempts, reward, project_id) 
			VALUES ('".$_SESSION['userData']['user_id']."', '".$score."', '1', '0', '0')";

			if ($conn->query($sql) === TRUE) {
				echo "new";
			} else {
				echo "Error: " . $sql . "<br>" . $conn->error;
			}
		}
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
?>