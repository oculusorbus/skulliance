<?php
/*
 * index.php -- the staking root, which is also where every invite lands.
 *
 * THE REFERRAL CODE IS CAPTURED HERE OR NOT AT ALL. An invite is
 * /staking/?r=<code>, and this file had no PHP in it whatsoever -- so
 * ref_capture() never ran, the cookie was never set, and by the time the
 * arrival came back from Discord's OAuth hop there was nothing left to say
 * who had invited them. The invite simply never paid, with no error
 * anywhere and the arrival signed in perfectly normally. db.php does the
 * capture for every other page on the platform; this page does not include
 * db.php and does not need to, so it does it itself.
 *
 * BEFORE ANY OUTPUT, because ref_capture() sets a cookie and setcookie()
 * after the first byte of HTML does nothing (it checks headers_sent() and
 * returns quietly, which is the same silent failure in a different place).
 *
 * The session is resumed only when there is already a session cookie --
 * starting one unconditionally would write an orphan session file for
 * every cold visitor and every crawler that ever hits the login page, the
 * same reason skullswap.php guards its own restore that way. ref_capture()
 * only reads the session to notice somebody clicking their own link, and
 * ref_credit() refuses a self-referral again anyway.
 */
require_once __DIR__ . '/lib/referral-lib.php';
if (session_status() !== PHP_SESSION_ACTIVE && isset($_COOKIE[session_name()])) @session_start();
ref_capture();
?>
<!doctype html>
<html>
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <link href="dist/output.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.1.1/css/all.min.css" integrity="sha512-KfkfwYDsLkIlwQp6LFnl8zNdLGxu9YAA1QvwINks4PhcElQSvqcyVLLD9aMhXd13uQjoXtEKNosOWaZqXgel0g==" crossorigin="anonymous" referrerpolicy="no-referrer" />
</head>
<body>
    <div class="flex items-center justify-center h-screen bg-discord-gray">
        <a href="https://discord.com/api/oauth2/authorize?client_id=1007909434141462588&permissions=268437504&redirect_uri=https%3A%2F%2Fskulliance.io%2Fstaking%2Fprocess-oauth.php&response_type=code&scope=identify%20guilds%20guilds.members.read" class="bg-discord-blue px-5 py-3 rounded-md hover:bg-gray-600 transition">
            <span class="text-white font-semibold text-xl"><i class="fa-brands fa-discord mr-2"></i> Login with Discord</span>
        </a>
    </div>

</body>
</html>