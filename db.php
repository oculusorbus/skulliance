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
	if($collection_id == 4 || $collection_id == 23){
		return "https://image-optimizer.jpgstoreapis.com/".$ipfs;
	}else if($collection_id == 20 || $collection_id == 21 || $collection_id == 30 || $collection_id == 42){
		return "https://storage.googleapis.com/jpeg-optim-files/".$ipfs;
	}else{
		return "https://image-optimizer.jpgstoreapis.com/".$ipfs;
	}
}

// Render IPFS
function renderIPFS($ipfs, $collection_id, $ipfs_format){
	$ipfs = str_replace("ipfs/", "", $ipfs);
	if($collection_id == 4 || $collection_id == 23){
		return "<span class='nft-image'><img onError='this.src=\"image.php?ipfs=".$ipfs."\";' src='".$ipfs_format."'/></span>";
	}else if($collection_id == 20 || $collection_id == 21 || $collection_id == 30 || $collection_id == 42){
		return "<span class='nft-image'><img onError='this.src=\"image.php?ipfs=".$ipfs."\";' src='".$ipfs_format."'/></span>";
	}else{
		return "<span class='nft-image'><img onError='this.src=\"image.php?ipfs=".$ipfs."\";' src='".$ipfs_format."'/></span>";
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
		$imageurl = getIPFS($nft_image, $collection_id);
	}else{
		$imageurl = "https://www.skulliance.io/staking/image.php?ipfs=".str_replace("ipfs/", "", $nft_image);
	}
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

// Get NFTs
function getNFTs($conn, $filterby="", $advanced_filter="", $diamond_skull=false, $diamond_skull_id="", $core_projects=false, $diamond_skull_totals=""){
	global $projects, $project_names;
	if(isset($_SESSION['userData']['user_id'])){
		if($filterby != "None" && $filterby != ""){
			$filterby = "project_id = '".$filterby."' ";
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
			$user_filter = "AND nfts.id IN(SELECT nft_id FROM diamond_skulls)";
		}else if($advanced_filter == "empty"){
			$user_filter = "AND nfts.id NOT IN(SELECT nft_id FROM diamond_skulls)";
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
		$sql = "SELECT asset_name, nfts.name AS nfts_name, ipfs, collection_id, nfts.id AS nfts_id, collections.rate AS rate, projects.currency AS currency, projects.id AS project_id, projects.name AS project_name, collections.name AS collection_name, users.username AS username FROM nfts INNER JOIN users ON users.id = nfts.user_id INNER JOIN collections ON nfts.collection_id = collections.id INNER JOIN projects ON collections.project_id = projects.id WHERE ".$user_filter.$and.$filterby.$diamond_skull_filter.$core_where." ORDER BY project_id, collection_id";
		echo $sql;
		exit;
		$result = $conn->query($sql);

		if ($result->num_rows > 0) {
		  // output data of each row
		  $nftcounter = 0;
		  while($row = $result->fetch_assoc()) {
			$nftcounter++;
			if($diamond_skull == true){
				echo "<div class='nft'><div class='diamond-skull-data'>";
			}else{
		    	echo "<div class='nft'><div class='nft-data'>";
			}
			echo "<span class='nft-name'>".substr($row["asset_name"], 0, 19)."</span>";
			echo renderIPFS($row["ipfs"], $row["collection_id"], getIPFS($row["ipfs"], $row["collection_id"]));
			if($diamond_skull == false){
				echo "<span class='nft-level'><strong>Project</strong><br>".$row["project_name"]."</span>";
				echo "<span class='nft-level'><strong>Collection</strong><br>".$row["collection_name"]."</span>";
				echo "<span class='nft-level'><strong>Reward Rate</strong><br>".$row["rate"]." $".$row["currency"]."</span>";
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
					foreach($diamond_skull_totals[$row["nfts_id"]] AS $project_id => $total){
						$status = "";
						if($projects[$project_id] == $total){
							$status = "Full"; 
							$carbon_count++;
						}else{
							$status = "Open"; 
						}
						$project_rows = $project_rows."<tr><td align='left'>".$project_names[$project_id]."</td><td align='center'>".$total."</td><td align='right'>".$status."</td></tr>";
					}
					echo "<br><img class='carbon-icon' src='icons/carbon".$carbon_count.".png'/><br><br>";
					echo "<table><tr><th width='60%' align='left'>Project</th><th width='20%' align='left'>NFTs</th><th width='20%' align='left'>Status</th></tr>";
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
	if($filterby != "0"){
		$filterby = "AND project_id = '".$filterby."' ";
	}else{
		$filterby = "";
	}
	$sql = "SELECT items.id AS item_id, items.name AS item_name, image_url, price, quantity, project_id, secondary_project_id, projects.name AS project_name, projects.currency AS currency, divider FROM items INNER JOIN projects ON projects.id = items.project_id WHERE quantity != 0 ".$filterby." ORDER BY projects.id, items.name ASC";
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
		if($row["project_id"] != 7){
			echo "<span class='nft-level'><strong>Price</strong><br>".number_format($row["price"])." ".$row["currency"]."<br>or<br>".number_format($row["price"]/$row["divider"])." DIAMOND</span>";
		}else{
			echo "<span class='nft-level'><strong>Price</strong><br>".number_format($row["price"])." ".$row["currency"]."</span>";
		}
		echo "<span class='nft-level'><strong>Quantity</strong><br>".$row["quantity"]."</span>";
		echo "<span class='nft-level'><strong>Project</strong><br>".$row["project_name"]."</span>";
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

// Deploy and Verify Diamond Skull Rewards for Delegators and Owners
function deployDiamondSkullRewards($conn){
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
	$sql = "SELECT diamond_skull_id, nft_id, rate, user_id FROM diamond_skulls INNER JOIN nfts ON nfts.id = diamond_skulls.nft_id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id";
	$result = $conn->query($sql);
	
	$delegator_rewards = array();

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		  // If NFT has no owner, remove NFT delegation to Diamond Skull
		  if($row["user_id"] == 0){
			  removeDiamondSkullNFT($conn, $row["diamond_skull_id"], $row["nft_id"]);
		  }else{
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
	    		echo "<td>".$date."</td><td>".$time."</td><td align='center'>".$type."</td><td align='center'>".number_format($row["amount"])." ".$row["currency"]."</td><td align='center'>";
				echo $currency;
				echo "</td><td>";
				if($row["crafting"] == 0){
					echo "Staking Reward: ".$row["project_name"];
				}else{
					echo "Crafting";
				}
				echo "</td>";
			}else if ($row["type"] == "debit"){
				echo "<td>".$date."</td><td>".$time."</td><td align='center'>".$type."</td><td align='center'>".number_format($row["amount"])." ".$row["currency"]."</td>";
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

// Get total NFTs staked
function getTotalNFTs($conn, $project_id=0){
	$where = "";
	if($project_id != 0){
		$where = "WHERE collections.project_id = '".$project_id."'";
	}
	$sql = "SELECT COUNT(nfts.id) as total FROM nfts INNER JOIN users ON nfts.user_id=users.id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id ".$where." AND nfts.user_id != '0'";
	$result = $conn->query($sql);

	if ($result->num_rows > 0) {
	  // output data of each row
	  while($row = $result->fetch_assoc()) {
		echo "<ul class='leaderboard'><li><strong>Total Staked: </strong>".number_format($row["total"])." NFTs</li></ul>";
	  }
	}
}

// Check leaderboard for discord and site display
function checkLeaderboard($conn, $clean, $project_id=0) {
	$where = "";
	if($project_id != 0){
		$where = "WHERE collections.project_id = '".$project_id."'";
	}
	$sql = "SELECT nfts.id, nfts.user_id, COUNT(nfts.id) as total, users.username, users.discord_id AS discord_id, avatar, projects.id AS project_id, currency FROM nfts INNER JOIN users ON nfts.user_id=users.id INNER JOIN collections ON collections.id = nfts.collection_id INNER JOIN projects ON projects.id = collections.project_id ".$where." GROUP BY nfts.user_id ORDER BY total DESC";
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
		  	echo "<ul class='leaderboard'>";
		  	while($row = $result->fetch_assoc()) {
				$leaderboardCounter++;
				$width = 20;
				if($leaderboardCounter == 1){
					$width = 50;
				}else if($leaderboardCounter == 2){
					$width = 45;
				}else if($leaderboardCounter == 3){
					$width = 40;
				}else if($leaderboardCounter == 4){
					$width = 35;
				}else if($leaderboardCounter == 5){
					$width = 30;
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
		    	echo "<li class='".$highlight."'>".(($leaderboardCounter<10)?"0":"").$leaderboardCounter.". ".$avatar." <strong style='font-size:".$width."px'>".$row["username"]. "</strong>: ".$row["total"]." NFTs".(($project_id != 0)?" (".number_format($current_balance)." ".$row["currency"].")":"")."</li>";
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
	$sql = "SELECT collections.name AS collection_name, policy, rate, projects.name AS project_name, currency, COUNT(nfts.id) AS total FROM collections INNER JOIN nfts ON nfts.collection_id = collections.id INNER JOIN users ON users.id = nfts.user_id INNER JOIN projects ON projects.id = collections.project_id ".$where." AND users.id != '0' GROUP BY collections.id ORDER BY projects.id, collections.name ASC";
	$result = $conn->query($sql);
	
	echo "<table cellspacing='0' id='transactions'>";
	echo "<tr><th>Collection</th><th>Project</th><th>Reward Rate</th><th>Total Staked</th></tr>";
	if ($result->num_rows > 0) {
	  // output data of each row
	  	while($row = $result->fetch_assoc()) {
		  	echo "<tr>";
			echo "<td align='center'>"."<a target='_blank' href='https://www.jpg.store/collection/".$row["policy"]."'>".$row["collection_name"]."</a>"."</td>";
			echo "<td align='center'>".$row["project_name"]."</td>";
			echo "<td align='center'>".$row["rate"]." ".$row["currency"]."</td>";
			echo "<td align='center'>".$row["total"]."</td>";
			echo "</tr>";
	  	}
	} else {
	  //echo "0 results";
	}
	echo "</table>";
}
?>