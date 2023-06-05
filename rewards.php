<?php
include_once 'db.php';

if(isset($_SERVER['argv']['deploy'])){
	set_time_limit(0);
	updateBalances($conn);
}
?>