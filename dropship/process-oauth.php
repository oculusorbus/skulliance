<?php
// Dead since Drop Ship's own Discord OAuth app was retired in favor of
// requiring Skulliance's own login (see dropship/db.php's gate). This used
// to be a ~160-line token-exchange handler with its own copies of
// getUsersGuildsRoles()/addUserToGuild()/getUsersGuilds() -- all names
// Skulliance's own process-oauth.php also defines, so keeping the old body
// around (even unreachable) was a redeclare-collision risk waiting for the
// day something included it alongside Skulliance's own copy. Replaced
// outright rather than just neutered in place.
header('Location: ../index.php');
exit();
