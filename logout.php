<?php
session_start();

// Clear server-side session data.
$_SESSION = array();

// Delete the PHPSESSID cookie itself (session_destroy alone leaves it).
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

// CRITICAL: also delete the 6-month "SessionCookie" that skulliance.php /
// db.php use to auto-restore a lapsed login. Without this, logout did nothing
// lasting - every staking page just rebuilt $_SESSION from this cookie and the
// user stayed logged in everywhere. It's set with PHP's default path (the
// /staking/ directory), so clear that; also clear root in case it was ever set
// there.
setcookie('SessionCookie', '', time() - 3600);             // default (/staking/)
setcookie('SessionCookie', '', time() - 3600, '/');        // root, just in case

header('Location: index.php');
exit();
