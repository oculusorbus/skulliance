<?php
// Drop Ship's own Discord OAuth app/login is retired -- signing in to
// Skulliance is the prerequisite now (dropship/db.php's gate enforces this
// on every real page already). This is the direct entry point, for anyone
// who still has the bare /staking/dropship/ URL saved.
//
// It used to send EVERYONE to ../index.php, the Skulliance login. That is
// right for a visitor with no session and wrong for a player who is already
// signed in -- they got bounced to a login page they were already past. The
// launchpad tile pointed at this directory, which is why it appeared broken
// while the nav link (straight to dashboard.php) worked.
//
// So: anyone carrying a session cookie is handed to dashboard.php, and the
// db.php there does the REAL restore and gate -- including the SessionCookie
// fallback for Mobile Safari/PWA, which is exactly why this file must not try
// to decide "logged in" for itself. The cookie's mere presence is enough to
// ROUTE; db.php decides whether it is valid, and sends an invalid one to
// error.php like every other Drop Ship page. session_name() does not require
// a started session, so nothing here opens one.
if (isset($_COOKIE[session_name()]) || isset($_COOKIE['SessionCookie'])) {
	header('Location: dashboard.php');
} else {
	header('Location: ../index.php');
}
exit();
