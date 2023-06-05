<?php
include 'credentials/db_credentials.php';
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

// Get user ID by stake address for cron job verification
function getUserId($conn, $address){
	$sql = "SELECT user_id FROM wallets WHERE address='".$address."'";
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

// Remove all NFT user ids in preparation for cron job verification
function removeUsers($conn){
	$sql = "UPDATE nfts set	user_id = 0";
	
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
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
function createAddress($conn, $address) {
	$sql = "INSERT INTO wallets (address, user_id)
	VALUES ('".$address."', '".$_SESSION['userData']['user_id']."')";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Check user's Cardano address
function checkAddress($conn, $address) {
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT address FROM wallets WHERE user_id='".$_SESSION['userData']['user_id']."' AND address='".$address."'";
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
		  // output data of each row
		} else {
		  //echo "0 results";
			createAddress($conn, $address);
		}
	}
}

// Get user wallet addresses
function getAddresses($conn) {
	$sql = "SELECT address FROM wallets WHERE user_id='".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	
    $addresses = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$addresses[] = $row["address"];
	  }
	} else {
	  //echo "0 results";
	}
	return $addresses;
}

// Get all addresses 
function getAllAddresses($conn){
	$sql = "SELECT address FROM wallets";
	$result = $conn->query($sql);
	
    $addresses = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
    	$addresses[] = $row["address"];
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
	VALUES ('".$asset_id."', '".$asset_name."', '".$name."', '".$ipfs."', '".$collection_id."', '".$user_id."')";
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

// Get NFTs
function getNFTs($conn){
	$sql = "SELECT asset_name, nfts.name AS nfts_name, ipfs, collection_id, nfts.id AS nfts_id, collections.rate AS rate, projects.currency AS currency FROM nfts INNER JOIN users ON users.id = nfts.user_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id WHERE user_id = '".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  $nftcounter = 0;
	  while($row = $result->fetch_assoc()) {
		$nftcounter++;
	    echo "<div class='nft'><div class='nft-data'>";
		echo "<span class='nft-name'>".substr($row["nfts_name"], 0, 19)."</span>";
		if($row["collection_id"] == 4){
			echo "<span class='nft-image'><img src='https://ipfs2.jpgstoreapis.com/".$row["ipfs"]."'/></span>";
		}else{
			echo "<span class='nft-image'><img src='https://image-optimizer.jpgstoreapis.com/".$row["ipfs"]."'/></span>";
		}
		echo "<span class='nft-level'><strong>Rate</strong><br>".$row["rate"]." $".$row["currency"]."</span>";
		echo "</div></div>";
	  }
	} else {
	  //echo "0 results";
	}
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
function updateBalances($conn){
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
    	foreach($subtotals[$row["user_id"]] AS $project_id => $subtotal){
			updateBalance($conn, $row["user_id"], $project_id, $subtotal);
		}
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
	}
}
/*
// Update specific user balance for a project
function updateBalance($conn, $user_id, $project_id, $subtotal){
	$current_balance = getCurrentBalance($conn, $user_id, $project_id);
	$total = $subtotal + $current_balance;
	$sql = "UPDATE balances SET balance = '".$total."' WHERE user_id='".$row["user_id"]."' AND project_id='".$row["project_id"]."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}*/
?>