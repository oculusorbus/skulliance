<?php
// Returns the current app version as plain text. Polled by the auto-refresh
// JS in header.php to detect when a new version has been deployed. Uses the
// VERSION file's mtime as the version marker (see that file's own comment) --
// its content is bumped in every commit, so any push trips this signal, not
// just ones that happen to touch header.php (the previous, narrower signal
// this replaced -- a game-logic-only push, for instance, never moved it).
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo @filemtime(__DIR__ . '/VERSION') ?: time();
