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

// Handle Drop Ship DREAD Rewards
if(isset($_POST['discord_id']) && isset($_POST['rank'])) {
	// Lookup user by discord id
	$user_id = getUserIdFromDiscordId($conn, $_POST['discord_id']);
	// If user exists, add DREAD reward based on rank in game to balance and log credit in transaction history
	if($user_id != false){
		$subtotal = 0;
		if($_POST['rank'] == 1){
			$subtotal = 1000;
		}else if($_POST['rank'] == 2){
			$subtotal = 500;
		}else if($_POST['rank'] == 3){
			$subtotal = 250;
		}
		updateBalance($conn, $user_id, 2, $subtotal);
		logCredit($conn, $user_id, $subtotal, 2);
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

// Get NFT Collection Leaderboard Visibility
function getVisibility($conn){
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
		return $project;
	}
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

// Get missions
function getMissions($conn, $quest_id) {
	$sql = "SELECT quests.id, title, description, image, cost, reward, project_id, duration, currency, name FROM quests INNER JOIN projects ON projects.id = quests.project_id ORDER BY CASE WHEN quests.id = '".$quest_id."' THEN 1 ELSE 2 END, quests.id";
	
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
    	$class = "";
		if($quest_id == $row["id"]){
			$class = " highlight";
		}
    	echo "<div class='nft'><div class='nft-data".$class."'>";
		echo "<span class='nft-name'>".$row["title"]."</span>";
		echo "<span class='nft-image'><img src='images/missions/".$row["image"]."'/></span>";
		echo "<span class='nft-level'><strong>Description</strong><br>".$row["description"]."</span>";
		echo "<span class='nft-level'><strong>Project</strong><br>".$row["name"]."</span>";
		echo "<span class='nft-level'><strong>Cost</strong><br>".$row["cost"]." ".$row["currency"]."</span>";
		echo "<span class='nft-level'><strong>Reward</strong><br>".$row["reward"]." ".$row["currency"]."</span>";
		echo "<span class='nft-level'><strong>Duration</strong><br>".$row["duration"]." Day(s)</span>";
		echo"
		<form action='missions.php#inventory' method='post'>
		  <input type='hidden' id='quest_id' name='quest_id' value='".$row["id"]."'>
		  <input type='hidden' id='project_id' name='project_id' value='".$row["project_id"]."'>
		  <input class='small-button' type='submit' value='Select Mission'>
		</form>";
		echo "</div></div>";
	  }
	} else {
	  //echo "0 results";
	}
}

// Get Current Missions for User
function getCurrentMissions($conn){
	$sql = "SELECT DISTINCT missions.id AS mission_id, quest_id title, projects.name AS project_name, cost, reward, currency, missions.created_date, duration, COUNT(nft_id) AS total_nfts, SUM(rate) AS success_rate 
	FROM missions INNER JOIN quests ON missions.quest_id = quests.id INNER JOIN projects ON projects.id = quests.project_id INNER JOIN missions_nfts ON missions.id = missions_nfts.mission_id INNER JOIN nfts ON nfts.id = missions_nfts.nft_id INNER JOIN collections ON collections.id = nfts.collection_id 
	WHERE status = 0 AND missions.user_id = '".$_SESSION['userData']['user_id']."' GROUP BY missions.id ORDER BY missions.id ASC";
	
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  echo "<h2>Current Missions</h2>";
	  echo '<a name="current-missions" id="current-missions"></a>';
	  echo '<div class="content missions">';
 	  echo "<table cellspacing='0' id='transactions'>";
	  echo "<th align='left'>Title</th><th align='left'>Project</th><th align='left'>Deployed</th><th align='left'>Cost</th><th align='left'>Reward</th><th align='left'>Success Rate</th><th align='left'>Time Left</th><th align='left'>Status</th>";
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
  		$date = strtotime('+'.$row["duration"].' day', strtotime($row["created_date"]));
  		$remaining = $date - time();
  		$hours_remaining = floor(($remaining % 86400) / 3600);
  		$minutes_remaining = floor(($remaining % 3600) / 60);
		if($hours_remaining > 0 && $minutes_remaining > 0){
			$time_message = $hours_remaining." Hours ".$minutes_remaining." Minutes";
			$completed = "Not Completed";
		}else{
			$time_message = "0 Hours and 0 Minutes";
			$completed = "<input type='button' class='small-button' value='Claim' onclick='completeMission(".$row["mission_id"].", ".$row["quest_id"].");'/>";
		}
		echo "<tr>";
		  echo "<td align='left'>";
		  echo $row["title"];
		  echo "</td>";
		  echo "<td align='left'>";
		  echo $row["project_name"];
		  echo "</td>";
		  echo "<td align='left'>";
		  echo $row["total_nfts"]." NFTs";
		  echo "</td>";
		  echo "<td align='left'>";
		  echo $row["cost"]." ".$row["currency"];
		  echo "</td>";
		  echo "<td align='left'>";
		  echo $row["reward"]." ".$row["currency"];
		  echo "</td>";
  		  echo "<td align='left'>";
		  echo $row["success_rate"]."%";
		  echo "</td>";
  		  echo "<td align='left'>";
		  echo $time_message;
		  echo "</td>";
  		  echo "<td align='left'>";
		  echo $completed;
		  echo "</td>";
		echo "</tr>";
	  }
	  echo "</table></div>";
	} else {
	  //echo "0 results";
	}
}

// Get missions inventory
function getInventory($conn, $project_id, $quest_id) {
	$sql = "SELECT nfts.id, asset_name, ipfs, rate, collection_id FROM nfts INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id WHERE project_id = '".$project_id."' AND user_id = '".$_SESSION['userData']['user_id']."' AND nfts.id 
		NOT IN(
        SELECT nft_id
        FROM missions_nfts INNER JOIN missions ON missions.id = missions_nfts.mission_id WHERE missions.user_id = '".$_SESSION['userData']['user_id']."') 
		ORDER BY collection_id ASC, rate DESC";
	
	$result = $conn->query($sql);
	
	$rate_tally = 0;
	if ($result->num_rows > 0) {
		echo "<ul>";
		echo "<li class='role'><strong>Success Rate: </strong>&nbsp;<span id='success-rate'>Loading...</span>%</li>";
		echo "<input type='button' class='button' value='Start Mission' onclick='startMission();'/>";
		while($row = $result->fetch_assoc()) {
			echo "<li class='role'>";
			echo renderIPFS($row["ipfs"], $row["collection_id"], getIPFS($row["ipfs"], $row["collection_id"]), true);
			echo substr($row["asset_name"], 0, 12)." (Rate ".$row["rate"].")";
			if(($rate_tally+$row["rate"]) <= 100){
				$rate_tally += $row["rate"];
				$_SESSION['userData']['mission']['nfts'][$row["id"]] = $row["rate"];
				?>&nbsp;<input style='float:right' type='button' id='button-<?php echo $row["id"]; ?>' class='small-button' value='Deselect' onclick='processMissionNFT(this.value, <?php echo $row["id"]; ?>, <?php echo $row["rate"]; ?>);'/><?php
			}else{
				?>&nbsp;<input style='float:right' type='button' id='button-<?php echo $row["id"]; ?>' class='small-button' value='Select' onclick='processMissionNFT(this.value, <?php echo $row["id"]; ?>, <?php echo $row["rate"]; ?>);'/><?php
			}
			echo "</li>";
		}
		echo "</ul>";
		return $rate_tally;
	}
}

function startMission($conn){
	if(isset($_SESSION['userData']['mission']['quest_id']) && isset($_SESSION['userData']['user_id'])){
		
		$sql = "SELECT project_id, cost FROM quests WHERE id ='".$_SESSION['userData']['mission']['quest_id']."';";
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			  $project_id = $row["project_id"];
			  $cost = $row["cost"];
		  }
	    }
		
		$balance = getBalance($conn, $project_id);
		if($balance >= $cost){
			updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, -$cost);
			logDebit($conn, $_SESSION['userData']['user_id'], 0, $cost, $project_id, 0, 1);
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
			} else {
			  //echo "Error: " . $sql . "<br>" . $conn->error;
			}
			unset($_SESSION['userData']['mission']);
		}else{
			alert("You do not have enough points to start this mission.");
		}
	}else{
		echo "No Session";
	}
}

function completeMission($conn, $mission_id, $quest_id){
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT title, reward, project_id, currency FROM quests INNER JOIN projects ON projects.id = quests.project_id WHERE quests.id ='".$quest_id."';";
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			  $title = $row["title"];
			  $reward = $row["reward"];
			  $project_id = $row["project_id"];
			  $currency = $row["currency"];
		  }
	    }
		
		$sql = "SELECT SUM(rate) AS success_rate FROM missions_nfts INNER JOIN nfts ON nfts.id = missions_nfts.nft_id WHERE mission_id ='".$mission_id."';";
		$result = $conn->query($sql);
		
		if ($result->num_rows > 0) {
		  // output data of each row
		  while($row = $result->fetch_assoc()) {
			  $success_rate = $row["success_rate"];
		  }
	    }
		
		// Failure = 2, Succes = 1
		$success = 2;
		$chance = $success_rate;
		if(rand(1,100) <= (int)$chance){
			$success = 1;
		}
		
		$sql = "UPDATE missions SET status='".$success."' WHERE id='".$mission_id."' AND user_id = '".$_SESSION['userData']['user_id']."'";
		if ($conn->query($sql) === TRUE) {
		  //echo "New record created successfully";
		} else {
		  //echo "Error: " . $sql . "<br>" . $conn->error;
		}
		
		// If success, update balance and log credit transaction
		if($success == 1){
			updateBalance($conn, $_SESSION['userData']['user_id'], $project_id, $reward);
			logCredit($conn, $_SESSION['userData']['user_id'], $reward, $project_id, 0, 0, 1);
			alert($title." was successful! ".$reward." ".$currency." added to your balance and transaction history.");
		}else{
			alert($title." failed. 0 ".$currency." awarded.");
		}
	}else{
		echo "No Session";
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
		$sql = "SELECT stake_address FROM wallets WHERE user_id='".$_SESSION['userData']['user_id']."' AND stake_address='".$stake_address."'";
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

// Create NFT
function createNFT($conn, $asset_id, $asset_name, $name, $ipfs, $collection_id, $user_id){
	$sql = "INSERT INTO nfts (asset_id, asset_name, name, ipfs, collection_id, user_id)
	VALUES ('".$asset_id."', '".mysqli_real_escape_string($conn, $asset_name)."', '".mysqli_real_escape_string($conn, $name)."', '".$ipfs."', '".$collection_id."', '".$user_id."')";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Update NFT for user
function updateNFT($conn, $asset_id, $user_id) {
	$sql = "UPDATE nfts SET user_id='".$user_id."' WHERE asset_id='".$asset_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Check if NFT already exists
function checkNFT($conn, $asset_id){
	$sql = "SELECT ipfs FROM nfts WHERE asset_id='".$asset_id."'";
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

// Render IPFS
function getIPFS($ipfs, $collection_id){
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

// Render IPFS
function renderIPFS($ipfs, $collection_id, $ipfs_format, $icon=false){
	$class = "";
	if($icon){
		$class = "class='icon' ";
	}
	$ipfs = str_replace("ipfs/", "", $ipfs);
	if($collection_id == 4 || $collection_id == 23){
		// Resource intensive IPFS code, disabled to save server resources, swapped for fallback skull icon
		// onError='this.src=\"image.php?ipfs=".$ipfs."\";'
		return "<span class='nft-image'><img ".$class." onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
	}else if($collection_id == 20 || $collection_id == 21 || $collection_id == 30 || $collection_id == 42){
		return "<span class='nft-image'><img ".$class." onError='this.src=\"/staking/icons/skull.png\";' src='".$ipfs_format."'/></span>";
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
		$sql = "SELECT asset_id, asset_name, nfts.name AS nfts_name, ipfs, collection_id, nfts.id AS nfts_id, collections.rate AS rate, projects.currency AS currency, projects.id AS project_id, projects.name AS project_name, collections.name AS collection_name, users.username AS username FROM nfts INNER JOIN users ON users.id = nfts.user_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id WHERE ".$user_filter.$and.$filterby.$diamond_skull_filter.$core_where." ORDER BY FIELD(project_id,6,5,4,3,2,1,7,8,9,10,11,12,13,14,15,16,17,18,19,20,21,22,23,24,25,26,27,28,29,30), collection_id".$limit;
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
			echo "<span class='nft-name'>".substr($row["asset_name"], 0, 19)."</span>";
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
		renderBuyButton($row["item_id"], $row["project_id"], "BUY for ".number_format($row["price"])." ".$row["currency"], $row["project_id"], $page);
		if($row["secondary_project_id"] != 0){
			$project = getProjectInfo($conn, $row["secondary_project_id"]);
			renderBuyButton($row["item_id"], $row["secondary_project_id"], "BUY for ".number_format($row["price"])." ".$project["currency"], $row["project_id"], $page);
		}
		if($row["project_id"] != 7){
			renderBuyButton($row["item_id"], 7, "BUY for ".number_format($row["price"]/$row["divider"])." DIAMOND", $row["project_id"], $page);
		}
		echo "</div></div>";
	  }
	} else {
	  echo "<p>There are no items available.</p><p>Please contact the project to request more staking incentives.</p>";
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
	$sql = "SELECT balance FROM balances WHERE user_id = '".$user_id."' AND project_id = '".$project_id."'";
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
function logCredit($conn, $user_id, $amount, $project_id, $crafting=0, $bonus=0, $mission=0) {
	$sql = "INSERT INTO transactions (type, user_id, amount, project_id, crafting, bonus, mission)
	VALUES ('credit', '".$user_id."', '".$amount."', '".$project_id."', '".$crafting."', '".$bonus."', '".$mission."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Log a specific user debit for an item purchase
function logDebit($conn, $user_id, $item_id, $amount, $project_id, $crafting=0, $mission=0) {
	$sql = "INSERT INTO transactions (type, user_id, item_id, amount, project_id, crafting, mission)
	VALUES ('debit', '".$user_id."', '".$item_id."', '".$amount."', '".$project_id."', '".$crafting."', '".$mission."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Display transaction history for user
function transactionHistory($conn) {
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT transactions.type, amount, items.name, crafting, bonus, mission, transactions.date_created, projects.currency AS currency, projects.name AS project_name FROM transactions LEFT JOIN items ON transactions.item_id = items.id LEFT JOIN projects ON projects.id = transactions.project_id WHERE transactions.user_id='".$_SESSION['userData']['user_id']."' ORDER BY date_created DESC LIMIT 1000";
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
				if($row["mission"] != 0){
					echo "<td>Mission Cost</td>";
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
				if(($row["discord_id"] == $row["project_discord_id"] && $row["project_discord_id"] != "772831523899965440") || ($project_id == '5' && $row["discord_id"] == "183841115286405121")){
					// Do not generate row for project owners unless Oculus Orbus and filter out Kimosabe
				}else{
					$leaderboardCounter++;
					$width = 40;
					$trophy = "";
					if($leaderboardCounter == 1){
						//$width = 50;
						$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
						}
					}else if($leaderboardCounter == 2){
						//$width = 45;
						if($last_total != $row["total"]){
							$trophy = "<img style='width:".$width."px' src='/staking/icons/second.png' class='icon'/>";
						}else{
							$trophy = "<img style='width:".$width."px' src='/staking/icons/first.png' class='icon'/>";
							$leaderboardCounter--;
						}
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
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
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
						}
					}else if($leaderboardCounter > 3 && $third_total == $row["total"]){
						$trophy = "<img style='width:".$width."px' src='/staking/icons/third.png' class='icon'/>";
						$leaderboardCounter--;
						if($_SESSION['userData']['user_id'] == $row["user_id"]){
							$fireworks = true;
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
			    	echo "<td align='center'><strong>".(($trophy == "")?(($leaderboardCounter<10)?"0":"").$leaderboardCounter.".":$trophy)."</strong></td><td align='center'>".$avatar."</td><td><strong style='font-size:20px'>".$username."</strong></td><td align='center'>".$row["total"].$delegated."</td><td align='center'>".(($project_id != 0)?" ".number_format($current_balance)." ".$row["currency"]."":"").$diamond_skull_count."</td>";
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
?>