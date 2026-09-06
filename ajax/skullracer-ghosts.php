<?php
// ajax/skullracer-ghosts.php — returns the current weekly-leader and
// all-time-leader ghost traces for Skull Racer, so racing/index.html can
// render them alongside the player's own (100% client-side, never touches
// this endpoint) personal-best ghost. See skullRacerGetGhosts()'s own
// comment in db.php for how "current leader" is determined.
//
// Public data (same visibility as the leaderboard itself) -- no login
// required, a guest sees the same ghosts a logged-in player does.
include_once '../db.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0'); // leaders change as races finish -- never a stale cached copy

echo json_encode(skullRacerGetGhosts($conn));
