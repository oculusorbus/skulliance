<?php
include '../db.php';
include '../skulliance.php';

unset($_SESSION['userData']['mission']['nfts']);
unset($_SESSION['userData']['mission']['consumables']);

// Close DB Connection
$conn->close();
?>