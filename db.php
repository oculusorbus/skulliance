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
?>