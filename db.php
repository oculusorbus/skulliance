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
?>