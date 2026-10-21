<?php
/**
 * dropship/audio-migrate.php -- put the existing sounds on the names the
 * wording asks for. ONE-OFF. Delete it once the art page is clean.
 *
 *   php audio-migrate.php          what it WOULD do, and nothing else
 *   php audio-migrate.php --go     actually copy
 *
 * WHY THIS EXISTS. The sound a round plays is now named after the
 * theme's own word for the thing plus the frame number -- demo1 is the
 * activation and demo2 the explosion, so Drop Ship wants
 * demolition1.mp3 and demolition2.mp3 and a theme calling it a Paddle
 * wants paddle1.mp3 and paddle2.mp3. The files on the server are named
 * after the SOUND (grenadecontra, massexplosioncontra, enemyguncontra)
 * or after a word with its spaces squeezed out (ballgag,
 * analbeadsinsertion). This copies each one onto the name that asks for
 * it.
 *
 * IT READS THE LIVE WORDING, which is the point of running it here
 * rather than working from a list. Rename a thing in the theme admin and
 * the name it asks for changes with it; a list written yesterday does
 * not know that.
 *
 * IT NEVER DESTROYS ANYTHING. Copy, never move. It refuses to overwrite
 * a file that already exists, so running it twice is safe and running it
 * after doing some of the work by hand is safe. The old names are still
 * load-bearing while Drop Ship falls back to them, so nothing is deleted
 * here at all -- see the note it prints at the end.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit("cli only\n"); }

/*
 * THE CHAIN AS IT WAS, which is the only record of what a themed caption
 * used to play. displayRound() matched a substring of the rendered
 * caption against these, in this order, and the order matters: "Smoke
 * Bomb" is inside "Pull Smoke Bomb Pin". Kept here and nowhere else,
 * because this script is the last thing that needs to know.
 */
$CHAIN = array(
	'Melee' => 'melee', 'Vibrator' => 'vibrator', 'Tactical Katana' => 'melee',
	'Dildo' => 'melee', 'Extra Life' => 'extralife',
	'Pull Smoke Bomb Pin' => 'grenadepin', 'Pull Out Whip' => 'spankme',
	'Smoke Bomb' => 'grenadecontra', 'Whip' => 'spank',
	'Pull Grenade' => 'grenadepin', 'Throw Grenade' => 'grenadecontra',
	'Insert Anal Beads' => 'analbeadsinsertion', 'Remove Anal Beads' => 'analbeadsremoval',
	'Ball Gag' => 'ballgag', 'Butt Plug' => 'ballgag',
	'Load Machine Gun' => 'loading', 'Machine Gun' => 'gunfirecontra',
	'Flamethrower Ignition' => 'flamethrower1', 'Flamethrower Spray' => 'flamethrower2',
	'Flamethrower Flames' => 'flamethrower3', 'Flamethrower Fire' => 'flamethrower4',
	'Load Rocket Launcher' => 'helicopter', 'Rocket Launcher Gunfire' => 'gunfirecontra',
	'Rocket Launcher' => 'rocketlaunchercontra', 'Rocket Launcher Explosion' => 'massexplosioncontra',
	'Demolition' => 'demo', 'Paddle' => 'spankme', 'Explosion' => 'explosioncontra',
	'Spank' => 'spank', 'Sniper' => 'sniper', 'Pilot' => 'loading',
	'Air Strike' => 'massexplosioncontra', 'Exo' => 'exo', 'Secured' => 'success',
	'Survived' => 'enemyguncontra',
);
/* The per-theme opening, which never had a caption to match on. */
$OPEN = array(1 => 'alarm', 2 => '', 3 => 'crowd', 4 => 'flyingcar');

/* ---------------------------------------------------------------- plan - */
/**
 * Which file each name wants, read off the live wording.
 *
 * SEPARATE SO IT CAN BE DRIVEN. This script copies real files on a live
 * server; the part that decides WHAT to copy is checked in
 * dropship-audio-migrate-harness.php against a stub, because getting it
 * wrong writes somebody's audio onto the wrong name.
 */
function dsamPlan($conn, $CHAIN, $OPEN) {
	$want = array();   /* target file => array('src' => old name, 'why' => who wants it) */
	$themes = dropshipThemeAll($conn);
		if (!$themes) return array();

	foreach ($themes as $t) {
		$pid  = (int)$t['project_id'];
		$name = !empty($t['name']) ? $t['name'] : $t['slug'];
		foreach (dropshipAudioSlots($conn, $pid) as $s) {
			if ($s['stem'] === '') continue;
			$target = $s['stem'] . '.mp3';
			/*
			 * What the old chain played for THIS theme's caption, matched
			 * exactly as the browser used to match it.
			 *
			 * THE CAPTION IS LOOKED UP, NOT BUILT FROM THE FAMILY WORD.
			 * A first pass substituted the family's word into the
			 * caption -- Demolition -> Paddle -- which left "Explosion"
			 * untouched and turned "Pull Smoke Bomb Pin" into "Pull Whip
			 * Pin". Both wrong: Oculus Lounge has its own rows saying
			 * Explosion is "Spank" and the pin is "Pull Out Whip", and
			 * those are what the page renders, so those are what the
			 * chain saw.
			 */
			$cap = $s['caption'];
			if (function_exists('dropshipThemeText')) {
				$c = dropshipThemeText($conn, $pid, $s['caption']);
				if ($c !== null && $c !== '') $cap = $c;
			}
			$src = '';
			foreach ($CHAIN as $term => $snd) {
				if (strpos($cap, $term) !== false) { $src = $snd; break; }
			}
			if ($src === '' && $s['legacy'] !== '') $src = $s['legacy'];
			if ($src === '' || $src . '.mp3' === $target) continue;
			if (!isset($want[$target])) $want[$target] = array('src' => $src . '.mp3', 'why' => array());
			$want[$target]['why'][$name . ': ' . $s['caption']] = true;
		}
		$open = dropshipAudioOpenSlot($conn, $pid);
		$leg  = isset($OPEN[$pid]) ? $OPEN[$pid] : '';
		if ($open['stem'] !== '' && $leg !== '') {
			$target = $open['stem'] . '.mp3';
			if ($leg . '.mp3' !== $target && !isset($want[$target])) {
				$want[$target] = array('src' => $leg . '.mp3', 'why' => array($name . ': Opening' => true));
			}
		}
	}
	ksort($want);
	ksort($want);
	return $want;
}

$GO = in_array('--go', $argv, true);
chdir(__DIR__);
include __DIR__ . '/db.php';
if (!isset($conn) || !is_object($conn)) exit("no database connection\n");
$dir = __DIR__ . '/sounds/';
if (!is_dir($dir)) exit("no sounds folder at $dir\n");
$want = dsamPlan($conn, $CHAIN, $OPEN);
if (!$want) exit("no themes in the database, or nothing to do\n");

/*
 * A SOURCE THAT HAS ALREADY BEEN MOVED. Doing part of this by hand with
 * mv rather than cp leaves the old name gone -- spankme.mp3 became
 * paddle1.mp3 -- and everything else that wanted it then has no source.
 * Any target already created from the same original is the same audio,
 * so it stands in.
 */
$standin = array();
foreach ($want as $target => $w) {
	if (is_file($dir . $w['src'])) continue;
	if (is_file($dir . $target)) $standin[$w['src']] = $target;
}

/* ----------------------------------------------------------------- do - */
$made = $have = $stuck = 0;
echo ($GO ? "COPYING" : "DRY RUN -- nothing is being written. Add --go to apply.") . "\n\n";
foreach ($want as $target => $w) {
	$src = $w['src'];
	$who = implode(', ', array_keys($w['why']));
	if (is_file($dir . $target)) {
		$have++;
		printf("  have  %-28s\n", $target);
		continue;
	}
	if (!is_file($dir . $src) && isset($standin[$src])) $src = $standin[$src];
	if (!is_file($dir . $src)) {
		$stuck++;
		printf("  ????  %-28s no source (%s is gone) -- %s\n", $target, $w['src'], $who);
		continue;
	}
	if (!$GO) { $made++; printf("  copy  %-28s <- %-24s %s\n", $target, $src, $who); continue; }
	if (!@copy($dir . $src, $dir . $target)) {
		$stuck++;
		printf("  FAIL  %-28s <- %s\n", $target, $src);
		continue;
	}
	/* 0644: a copy inherits the source's mode, and a file the web server
	   cannot read is a silent 404. This bit us on the art. */
	@chmod($dir . $target, 0644);
	$standin[$w['src']] = $target;
	$made++;
	printf("  made  %-28s <- %-24s %s\n", $target, $src, $who);
}

printf("\n%d to copy, %d already there, %d with no source, %d total\n",
	$made, $have, $stuck, count($want));
if (!$GO) {
	echo "\nNothing was written. Run it again with --go to do it.\n";
} else {
	echo "\nDone. Check the Theme Art page: any row still reading \"playing <old name>\"\n"
	   . "is Drop Ship falling back, and any reading \"silent\" needs a sound that does\n"
	   . "not exist yet in any form.\n";
}
echo "\nNOTHING WAS DELETED. The old names are still load-bearing -- Drop Ship falls\n"
   . "back to them for anything not yet copied -- so leave them until no row on the\n"
   . "art page mentions one. Then they are free, and so is this script.\n";
$conn->close();
