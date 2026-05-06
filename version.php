<?php
// Returns the current app version as plain text. Polled by the auto-refresh
// JS in header.php to detect when a new version has been deployed. Uses
// header.php's mtime as the version marker — header.php gets updated nearly
// every deploy because most pages include it. If you want a stricter signal,
// switch this to filemtime() of a dedicated VERSION file you touch on deploy.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
echo @filemtime(__DIR__ . '/header.php') ?: time();
