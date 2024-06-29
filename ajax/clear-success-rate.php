<?php
include '../db.php';
include '../skulliance.php';

// Clear only success rate items
unset($_SESSION['userData']['mission']['consumables'][1]);
unset($_SESSION['userData']['mission']['consumables'][2]);
unset($_SESSION['userData']['mission']['consumables'][3]);
unset($_SESSION['userData']['mission']['consumables'][4]);
unset($_SESSION['userData']['mission']['nfts']);

// Close DB Connection
$conn->close();
?>