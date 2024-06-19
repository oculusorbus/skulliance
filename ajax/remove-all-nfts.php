<?php
include '../db.php';
include '../skulliance.php';

unset($_SESSION['userData']['mission']['nfts']);

// Close DB Connection
$conn->close();
?>