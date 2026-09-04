<?php
// Login is unified now (Skulliance's own), so logout has to be too. A bare
// session_destroy() here would kill the shared session's DATA but not
// Skulliance's own cookies (SessionCookie in particular) -- db.php's own
// restore-from-SessionCookie logic would just log the player straight back
// in on their very next request, making this logout silently do nothing.
// Skulliance's own logout.php clears every cookie that matters; defer to it.
header("Location: ../logout.php");
exit();