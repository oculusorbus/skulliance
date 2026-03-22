<?php
include '../db.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }
if(!checkRealm($conn)){ exit; }

getTotalFactionRaids($conn);
getTotalRaids($conn);

$conn->close();
?>
