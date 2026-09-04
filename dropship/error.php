<?php
// Drop Ship no longer runs its own login -- accessing it now requires
// signing in to Skulliance first (see dropship/db.php's session-gate
// comment). Every path that used to land here (dropship.php's own
// !logged_in check, db.php's own gate) now bounces one level further, to
// Skulliance's own "you need to be logged in" page -- the same one every
// other gated Skulliance page already uses, so a Drop Ship visitor sees the
// exact same login prompt, not a dead-ended Drop Ship-specific one.
header('Location: ../error.php');
exit();