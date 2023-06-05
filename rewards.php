<?php
include_once 'db.php';

if(isset($_GET['deploy'])){
	updateBalances($conn);
}
?>