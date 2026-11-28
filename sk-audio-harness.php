<?php
/* sk-audio-harness.php — CLI only. Static. No database.
 *
 *     php sk-audio-harness.php
 *
 * THE ONE THING THIS PROTECTS. iOS silently ignores
 * HTMLMediaElement.volume -- no error, no event, always 1.0, by Apple's
 * design, and that includes the PWA installed to a home screen. So a
 * volume slider wired the obvious way moves and does nothing on the
 * device most of these games are played on. Every slider therefore has
 * to go through a Web Audio GainNode, which iOS does respect.
 *
 * cryptcrawl.php found this first and carries its own copy; sk-audio.php
 * is that logic on its own for everything since. What this pins is that
 * no NEW control quietly goes back to el.volume.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }
function src($f) { return (string) file_get_contents(__DIR__ . '/' . $f); }

$ska = src('sk-audio.php');

echo "the shared helper routes volume where iOS will hear it\n";
ok(strpos($ska, 'createGain()') !== false && strpos($ska, 'createMediaElementSource') !== false,
   'sk-audio.php does not use a GainNode, so every slider is dead on iOS');
ok(strpos($ska, 'g.gain.value = vol; el.volume = 1;') !== false,
   'the wrapped element does not pin its own volume to 1, so the two fight');
ok(strpos($ska, 'else   { el.volume = vol; }') !== false,
   'there is no fallback for a browser without Web Audio, so it would go silent');
ok(strpos($ska, 'function unlock()') !== false && strpos($ska, "state === 'suspended'") !== false,
   'the context is never resumed, so nothing plays until some other code happens to unlock it');
ok(strpos($ska, "if (defined('SK_AUDIO_DONE')) return;") !== false,
   'including it twice emits the helper twice and redefines window.SkAudio mid-page');

echo "\nevery game with audio controls has a volume slider\n";
/*
 * REQUESTED: "audio controls also have a volume slider anytime audio
 * controls are present." Derived from the files rather than listed, so a
 * game that grows a mute button tomorrow is caught by the same check.
 */
foreach (array('cryptcrawl.php', 'cryptconquest.php', 'guardians.php',
               'dhcarena.php', 'gauntlets-fight-ui.php') as $f) {
	$s = src($f);
	$hasControl = preg_match('~id="(?:[a-z-]*mute|[a-z-]*audio-toggle|rg-music)"~', $s) === 1;
	if (!$hasControl) continue;
	ok(preg_match('~<input[^>]+type="range"[^>]+(?:vol|Volume)~i', $s) === 1
	   || preg_match('~<input[^>]+(?:vol|Volume)[^>]+type="range"~i', $s) === 1,
	   "$f has audio controls but no volume slider");
}

echo "\nthe new controls do not set el.volume directly\n";
/* Crypt Crawl is the deliberate exception: it has its own copy of the
   same workaround, predating this file, and rewriting a working game's
   audio to share a helper is a bad trade. */
foreach (array('dhcarena.php', 'gauntlets-fight-ui.php') as $f) {
	$s = preg_replace('~/\*.*?\*/~s', '', src($f));
	ok(strpos($s, 'SkAudio.set(') !== false, "$f does not use the shared helper to set volume");
	/* Allowed only as the explicit fallback on the same line. */
	/* A bare el.volume is allowed ONLY as the else-branch of a SkAudio
	   test, which may sit on the line above. Anything else is a control
	   that will not work on an iPhone. */
	$lines = explode("\n", $s); $bare = 0;
	foreach ($lines as $i => $line) {
		if (!preg_match('~\.volume\s*=~', $line)) continue;
		$ctx = $line . ' ' . (isset($lines[$i - 1]) ? $lines[$i - 1] : '');
		if (strpos($ctx, 'SkAudio') === false) $bare++;
	}
	ok($bare === 0, "$f sets .volume on $bare line(s) with no SkAudio fallback guard");
}

echo "\nthe Gauntlet strip survives a round\n";
$gf    = src('gauntlets-fight-ui.php');
$shell = src('gauntlets.php');
$body  = src('gauntlets-body.php');
/* #g-area is replaced between rounds; controls inside it would be
   rebuilt mid-track, which is the whole reason the <audio> is outside.
   The strip renders UNDER the game, so it lives in the shell after
   #g-area rather than with the rest of the audio code. */
ok(strpos($shell, 'id="gf-audio"') !== false, 'Gauntlets has no audio strip');
ok(strpos($gf, 'id="gf-audio"') === false,
   'the strip is emitted with the audio code again, which puts it above the game');
ok(strpos($body, 'gf-audio') === false,
   'the Gauntlet audio strip is inside the swapped body, so a round change rebuilds it');
/* Below, not above: anything before #g-area pushes the fight down. */
ok((int)strpos($shell, 'id="g-area"') < (int)strpos($shell, 'id="gf-audio"'),
   'the audio strip renders above the game, where it pushes the fight down a phone screen');
ok(strpos($gf, "remember('gfVol'") !== false
   && strpos($gf, "localStorage.getItem('gfVol')") !== false,
   'the chosen volume is not remembered between visits');
ok(strpos($gf, 'if (!el || muted || paused) return;') !== false,
   'pausing does not stop the next turn from restarting the track');

/*
 * THE BUTTON HAS TO FOLLOW THE AUDIO, not the request. `playing` only
 * becomes true when play() RESOLVES -- after the gesture that unlocks
 * audio -- so without a repaint there the icon sat on play while the
 * music was audibly running. Reported as "why is there only a play
 * button, no pause icon".
 */
ok(strpos($gf, 'pr.then(function () { playing = true;  icons(); },') !== false,
   'the button is not repainted when play() resolves, so it never shows pause');
ok(strpos($gf, "el.addEventListener('ended', function () { playing = false; icons(); });") !== false,
   'the button stays on pause after a non-looping track finishes');
/* And the first tap has to actually start it. */
$unlockFn = substr($gf, (int)strpos($gf, 'function unlock() {'), 700);
$unlockFn = substr($unlockFn, 0, (int)strpos($unlockFn, "\n  }"));
ok(strpos($unlockFn, 'getElementById(AREA)') === false,
   'the first-tap unlock still requires a live fight, so music never starts on the deck screen');
ok(strpos($unlockFn, 'music(moodFrom());') !== false,
   'the first-tap unlock no longer starts the music');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all audio checks passed\n");
exit($fail ? 1 : 0);
