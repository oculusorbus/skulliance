<?php
// Drop Ship's own Discord OAuth app/login is retired -- signing in to
// Skulliance is the prerequisite now (dropship/db.php's gate enforces this
// on every real page already; this is just the direct entry point's own
// version of the same redirect, for anyone who still has this URL saved).
header('Location: ../index.php');
exit();
