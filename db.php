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

// Get first user wallet address to send purchases to
function getAddress($conn){
	$sql = "SELECT address FROM wallets WHERE user_id='".$_SESSION['userData']['user_id']."'";
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
function getProjects($conn){
	$sql = "SELECT id, name, currency, discord_id FROM projects";
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

// Remove all NFT user ids in preparation for cron job verification
function removeUsers($conn){
	$sql = "UPDATE nfts set	user_id = 0";
	
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Get NFTs
function getNFTs($conn, $filterby=""){
	if($filterby != "None" && $filterby != ""){
		$filterby = "AND project_id = '".$filterby."' ";
	}else{
		$filterby = "";
	}
	$sql = "SELECT asset_name, nfts.name AS nfts_name, ipfs, collection_id, nfts.id AS nfts_id, collections.rate AS rate, projects.currency AS currency, projects.id AS project_id, projects.name AS project_name, collections.name AS collection_name FROM nfts INNER JOIN users ON users.id = nfts.user_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id WHERE user_id = '".$_SESSION['userData']['user_id']."'".$filterby." ORDER BY project_id, collection_id";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  $nftcounter = 0;
	  while($row = $result->fetch_assoc()) {
		$nftcounter++;
	    echo "<div class='nft'><div class='nft-data'>";
		echo "<span class='nft-name'>".substr($row["asset_name"], 0, 19)."</span>";
		if($row["collection_id"] == 4 || $row["collection_id"] == 23){
			echo "<span class='nft-image'><img onError='this.src=\"/staking/icons/skull.png\";' src='https://ipfs2.jpgstoreapis.com/".$row["ipfs"]."'/></span>";
		}else if($row["collection_id"] == 20 || $row["collection_id"] == 21 || $row["collection_id"] == 30){
			echo "<span class='nft-image'><img onError='this.src=\"/staking/icons/skull.png\";' src='https://storage.googleapis.com/jpeg-optim-files/".str_replace("ipfs/", "", $row["ipfs"])."'/></span>";
		}else{
			echo "<span class='nft-image'><img onError='this.src=\"/staking/icons/skull.png\";' src='https://image-optimizer.jpgstoreapis.com/".$row["ipfs"]."'/></span>";
		}
		echo "<span class='nft-level'><strong>Project</strong><br>".$row["project_name"]."</span>";
		echo "<span class='nft-level'><strong>Collection</strong><br>".$row["collection_name"]."</span>";
		echo "<span class='nft-level'><strong>Reward Rate</strong><br>".$row["rate"]." $".$row["currency"]."</span>";
		echo "</div></div>";
	  }
	} else {
	  //echo "0 results";
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
function getItems($conn, $page){
	$sql = "SELECT items.id AS item_id, items.name AS item_name, image_url, price, quantity, project_id, projects.name AS project_name, projects.currency AS currency, divider FROM items INNER JOIN projects ON projects.id = items.project_id WHERE quantity != 0 ORDER BY price DESC";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  $nftcounter = 0;
	  $uri = $_SERVER['REQUEST_URI'];
	  if(str_contains($uri, "/staking/store.php")){
		$class = "store-item";
	  }else{
		$class = "offering";
	  }
	  while($row = $result->fetch_assoc()) {
		$nftcounter++;
	    echo "<div class='nft ".$class."'><div class='nft-data'>";
		echo "<span class='nft-name'>".$row["item_name"]."</span>";
		echo "<span class='nft-image'><img onError='this.src=\"/staking/icons/skull.png\";' src='".$row["image_url"]."'/></span>";
		if($row["project_id"] != 7){
			echo "<span class='nft-level'><strong>Price</strong><br>".number_format($row["price"])." $".$row["currency"]."<br>or<br>".number_format($row["price"]/$row["divider"])." \$DIAMOND</span>";
		}else{
			echo "<span class='nft-level'><strong>Price</strong><br>".number_format($row["price"])." $".$row["currency"]."</span>";
		}
		echo "<span class='nft-level'><strong>Quantity</strong><br>".$row["quantity"]."</span>";
		echo "<span class='nft-level'><strong>Project</strong><br>".$row["project_name"]."</span>";
		renderBuyButton($row["item_id"], $row["project_id"], "BUY for ".number_format($row["price"])." $".$row["currency"], $row["project_id"], $page);
		if($row["project_id"] != 7){
			renderBuyButton($row["item_id"], 7, "BUY for ".number_format($row["price"]/$row["divider"])." \$DIAMOND", $row["project_id"], $page);
		}
		echo "</div></div>";
	  }
	} else {
	  //echo "0 results";
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
	$sql = "SELECT items.id AS item_id, projects.id AS project_id, items.name AS item_name, image_url, price, projects.name AS project_name, currency, override FROM items INNER JOIN projects ON projects.id = items.project_id WHERE items.id = '".$item_id."'";
	$result = $conn->query($sql);
	
	if ($result->num_rows > 0) {
	  // output data of each row
	  $item = array();
	  while($row = $result->fetch_assoc()) {  
		$item["name"] = $row["item_name"];
		$item["image_url"] = $row["image_url"];
		if($project_id == 7){
			$item["currency"] = "DIAMOND";
		}else{
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
	echo "<script type='text/javascript'>alert('".$message."');</script>";
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

// Update specific user balance for a project
function updateBalance($conn, $user_id, $project_id, $subtotal){
	$current_balance = getCurrentBalance($conn, $user_id, $project_id);
	$total = $subtotal + $current_balance;
	$sql = "UPDATE balances SET balance = '".$total."' WHERE user_id='".$user_id."' AND project_id='".$project_id."'";
	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Get user balances for all projects
function getBalances($conn){
	$sql = "SELECT balance, project_id, projects.currency AS currency FROM balances INNER JOIN projects ON balances.project_id = projects.id WHERE user_id = '".$_SESSION['userData']['user_id']."'";
	$result = $conn->query($sql);
	
	$balances = array();
	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
	    //echo "id: " . $row["id"]. " - Discord ID: " . $row["discord_id"]. " Username: " . $row["username"]. "<br>";
        $balances["$".$row["currency"]] = $row["balance"];
	  }
	} else {
	  //echo "0 results";
	}
	return $balances;
}

// Get minimum balance for crafting
function getMinimumBalance($conn){
	$sql = "SELECT balance, project_id FROM balances WHERE user_id = '".$_SESSION['userData']['user_id']."' AND project_id != '7' ORDER BY balance ASC LIMIT 1";
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

// Craft $DIAMOND
function craft($conn, $balance){
	$sql = "SELECT balance, project_id FROM balances INNER JOIN projects ON balances.project_id = projects.id WHERE user_id = '".$_SESSION['userData']['user_id']."' AND project_id != '7'";
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

// Log a specific user credit for nightly rewards
function logCredit($conn, $user_id, $amount, $project_id, $crafting=0) {
	$sql = "INSERT INTO transactions (type, user_id, amount, project_id, crafting)
	VALUES ('credit', '".$user_id."', '".$amount."', '".$project_id."', '".$crafting."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Log a specific user debit for an item purchase
function logDebit($conn, $user_id, $item_id, $amount, $project_id, $crafting=0) {
	$sql = "INSERT INTO transactions (type, user_id, item_id, amount, project_id, crafting)
	VALUES ('debit', '".$user_id."', '".$item_id."', '".$amount."', '".$project_id."', '".$crafting."')";

	if ($conn->query($sql) === TRUE) {
	  //echo "New record created successfully";
	} else {
	  //echo "Error: " . $sql . "<br>" . $conn->error;
	}
}

// Display transaction history for user
function transactionHistory($conn) {
	if(isset($_SESSION['userData']['user_id'])){
		$sql = "SELECT transactions.type, amount, items.name, crafting, transactions.date_created, projects.currency AS currency, projects.name AS project_name FROM transactions LEFT JOIN items ON transactions.item_id = items.id LEFT JOIN projects ON projects.id = transactions.project_id WHERE transactions.user_id='".$_SESSION['userData']['user_id']."' ORDER BY date_created DESC LIMIT 1000";
		$result = $conn->query($sql);
	
		echo "<table cellspacing='0' id='transactions'><tr><th>Date</th><th>Time</th><th align='center'>Type</th><th align='center'>Amount</th><th align='center'>Icon</th><th>Description</th></tr>";
		while($row = $result->fetch_assoc()) {
			$currency = "<img class='icon' src='icons/".strtolower($row["currency"]).".png'/>";
			$type = "<img class='icon' src='icons/".$row["type"].".png'/>";
			echo "<tr class='".$row["type"]."'>";
			$date = date("n-j-Y",strtotime("-1 hour", strtotime($row["date_created"])));
			$time = date("g:ia",strtotime("-1 hour", strtotime($row["date_created"])));
			if ($row["type"] == "credit"){
	    		echo "<td>".$date."</td><td>".$time."</td><td align='center'>".$type."</td><td align='center'>".$row["amount"]." $".$row["currency"]."</td><td align='center'>";
				echo $currency;
				echo "</td><td>";
				if($row["crafting"] == 0){
					echo "Staking Reward: ".$row["project_name"];
				}else{
					echo "Crafting";
				}
				echo "</td>";
			}else if ($row["type"] == "debit"){
				echo "<td>".$date."</td><td>".$time."</td><td align='center'>".$type."</td><td align='center'>".$row["amount"]." $".$row["currency"]."</td>";
				echo "<td align='center'><img class='icon' src='icons/".strtolower($row["currency"]).".png'/></td>";
				if($row["crafting"] == 0){
					echo "<td>NFT Purchase: ".$row["name"]."</td>";
				}else{
					echo "<td>Crafting</td>";
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

// Check leaderboard for discord and site display
function checkLeaderboard($conn, $clean, $project_id=0) {
	$where = "";
	if($project_id != 0){
		$where = "WHERE collections.project_id = '".$project_id."'";
	}
	$sql = "SELECT nfts.id, nfts.user_id, COUNT(nfts.id) as total, users.username, users.discord_id AS discord_id, avatar, projects.id AS project_id FROM nfts INNER JOIN users ON nfts.user_id=users.id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id ".$where." GROUP BY nfts.user_id ORDER BY total DESC";
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
			$leaderboardCounter = 0;
		  	echo "<ul id='leaderboard'>";
		  	while($row = $result->fetch_assoc()) {
				$leaderboardCounter++;
				//$level = floor($row["xp"]/100);
				$avatar = "";
				if($row["avatar"] != ""){
					$avatar = "<img onError='this.src=\"/staking/icons/skull.png\";' src='https://cdn.discordapp.com/avatars/".$row["discord_id"]."/".$row["avatar"].".jpg' class='icon rounded-full'/>";
				}
		    	echo "<li>".$leaderboardCounter.". ".$avatar." <strong>".$row["username"]. "</strong>: ".$row["total"]." NFTs</li>";
		  	}
			echo "</ul>";
		}
	} else {
	  //echo "0 results";
	}
}

// Get policy IDs
function getPoliciesListing($conn, $project_id=0) {
	$where = "";
	if($project_id != 0){
		$where = "WHERE collections.project_id = '".$project_id."'";
	}
	$sql = "SELECT collections.name AS collection_name, policy, rate, projects.name AS project_name, currency FROM collections INNER JOIN projects ON projects.id = collections.project_id ".$where." ORDER BY projects.id";
	$result = $conn->query($sql);
	
	echo "<table cellspacing='0' id='transactions'>";
	echo "<tr><th>Collection</th><th>Reward Rate</th><th>Project</th><th>Policy ID</th></tr>";
	if ($result->num_rows > 0) {
	  // output data of each row
	  	while($row = $result->fetch_assoc()) {
		  	echo "<tr>";
			echo "<td align='center'>"."<a target='_blank' href='https://www.jpg.store/collection/".$row["policy"]."'>".$row["collection_name"]."</a>"."</td>";
			echo "<td align='center'>".$row["rate"]." $".$row["currency"]."</td>";
			echo "<td align='center'>".$row["project_name"]."</td>";
			echo "<td align='center'>"."<a target='_blank' href='https://www.jpg.store/collection/".$row["policy"]."'>".$row["policy"]."</a>"."</td>";
			echo "</tr>";
	  	}
	} else {
	  //echo "0 results";
	}
	echo "</table>";
}
?>