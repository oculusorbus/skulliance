<?php
/**
 * missions-media-probe.php — can this server convert video for missions?
 *
 *   php missions-media-probe.php                     (the CLI answer)
 *   https://skulliance.io/staking/missions-media-probe.php   (the one that matters)
 *
 * RUN IT BOTH WAYS, AND BELIEVE THE SECOND. The shell php is a different
 * build from the one Apache runs -- this platform has already paid for
 * that lesson twice, with ext/sodium and with mysqlnd -- and cPanel
 * commonly leaves exec() enabled for the shell user while disabling it
 * for the web user. A CLI "yes" proves nothing about an upload.
 *
 * WHAT IT DECIDES. A mission that is a video needs TWO files: the .mp4
 * the player watches and a .gif used as the poster and as the still on
 * every tile. Both are currently made by hand. With ffmpeg reachable
 * from the web SAPI, an upload of a .mov or an .mp4 could produce both
 * on its own; without it, .mov stays refused, because a QuickTime file
 * carrying HEVC -- which is what an iPhone records by default -- plays
 * for whoever uploaded it and shows a black frame in Chrome and Firefox.
 *
 * READ-ONLY. It runs `ffmpeg -version` and nothing else, writes nothing,
 * and converts nothing.
 */

$cli = (PHP_SAPI === 'cli');
if (!$cli) {
	/* Over the web this reports server configuration, so it is admin
	   only -- and 404, not 403, like the rest of the panel. */
	include __DIR__ . '/db.php';
	include __DIR__ . '/skulliance.php';
	require_once __DIR__ . '/admin-lib.php';
	if (!adminIsSuper()) { http_response_code(404); header('Content-Type: text/plain'); echo "Not found.\n"; exit; }
	header('Content-Type: text/plain; charset=utf-8');
}

function say($s = '') { echo $s . "\n"; }

say('SAPI                : ' . PHP_SAPI . ($cli ? '   <- not the one that matters' : '   <- this is the one that matters'));
say('PHP                 : ' . PHP_VERSION);
say();

$disabled = array_filter(array_map('trim', explode(',', (string)ini_get('disable_functions'))));
$can_exec = function_exists('exec') && !in_array('exec', $disabled, true);
say('exec()              : ' . ($can_exec ? 'available' : 'NOT AVAILABLE'));
say('disable_functions   : ' . ($disabled ? implode(', ', $disabled) : '(none)'));
say();

/* which(1) only sees PATH, and the web user's PATH is usually shorter
   than a shell login's. Look in the places a cPanel box actually keeps
   things before concluding it is absent. */
$candidates = array('ffmpeg', '/usr/bin/ffmpeg', '/usr/local/bin/ffmpeg', '/usr/local/cpanel/3rdparty/bin/ffmpeg',
                    '/opt/cpanel/ffmpeg/bin/ffmpeg', '/opt/ffmpeg/bin/ffmpeg', '/home/jeremiah/bin/ffmpeg');
say('looking for ffmpeg:');
$found = '';
foreach ($candidates as $c) {
	$isfile = ($c[0] === '/') ? is_file($c) : false;
	$mark   = $isfile ? 'present' : (($c[0] === '/') ? '-' : 'on PATH?');
	if ($c[0] === '/') say(sprintf('  %-44s %s', $c, $mark));
	if ($isfile && $found === '') $found = $c;
}

/* The only answer that counts: does running it work from HERE. */
$version = '';
if ($can_exec) {
	foreach (array_merge($found ? array($found) : array(), array('ffmpeg')) as $bin) {
		$out = array(); $code = 1;
		@exec(escapeshellcmd($bin) . ' -version 2>&1', $out, $code);
		if ($code === 0 && $out) { $found = $bin; $version = $out[0]; break; }
	}
}
say();
say('ffmpeg runnable here: ' . ($version !== '' ? 'YES  (' . $found . ')' : 'no'));
if ($version !== '') say('  ' . $version);
say();

if ($version !== '') {
	say('SO: an upload can produce both files on its own.');
	say('  .mov or .mp4 in  ->  H.264 .mp4 transcode  +  a .gif poster from the first seconds');
	say('  Nothing is converted by hand, and .mov becomes safe to accept because it stops');
	say('  being what the browser is handed.');
} else {
	say('SO: .mov stays refused, and video missions keep needing both files made by hand.');
	say('  Accepting .mov as-is would trade a visible refusal for an invisible break: an');
	say('  iPhone .mov is HEVC, which plays in Safari and shows a black frame in Chrome.');
	if (!$can_exec) say('  The blocker here is exec(), not ffmpeg -- see disable_functions above.');
	else            say('  The blocker here is ffmpeg itself. cPanel boxes rarely ship it; a static');
	say('  build dropped in ~/bin and named above would be enough.');
}
