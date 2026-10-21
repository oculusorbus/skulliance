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

/*
 * THE GUARDS, which are half the chain's behaviour and the half a plain
 * substring table cannot express. A dozen branches were wrapped in
 * `project_id != 3 && project_id != 4` -- Filthy Mermaid and Oculus
 * Lounge were SILENT on the whole flamethrower, the machine gun, the
 * rocket launcher, the sniper and the air strike. Without this the
 * migration reads "no sound in the chain" as "give it Drop Ship's",
 * which is the exact leak the rework exists to stop.
 */
$GUARDED = array(
	'Pull Grenade', 'Throw Grenade', 'Load Machine Gun', 'Machine Gun',
	'Flamethrower Ignition', 'Flamethrower Spray', 'Flamethrower Flames',
	'Flamethrower Fire', 'Load Rocket Launcher', 'Rocket Launcher Gunfire',
	'Rocket Launcher', 'Rocket Launcher Explosion', 'Sniper', 'Pilot',
	'Air Strike', 'Exo',
	/* The catch-all was guarded too: `evaluateRoundAudio(currentRound,
	   lastRound-1, "!=")` sat inside the same project_id test, which is
	   why only Drop Ship and Dread City got a gunshot on every advance.
	   Every theme calls a surviving round "Survived", so all four ask
	   for survived.mp3 -- copying anything into it hands the gunshot to
	   all of them, which is the report that started this. */
	'Survived',
);
/*
 * ROUNDS 1 AND 2 were keyed on the round NUMBER, not the caption, so
 * they are not in the chain at all: project 1 and 2 got dropshipcontra
 * and gunfirecontra, Filthy Mermaid got door and stairs, and Oculus
 * Lounge got nothing.
 */
$ROUNDS = array(
	'drop/drop' => array(1 => 'dropshipcontra', 2 => 'dropshipcontra', 3 => 'door',   4 => ''),
	'drop/team' => array(1 => 'gunfirecontra',  2 => 'gunfirecontra',  3 => 'stairs', 4 => ''),
);

/* ---------------------------------------------------------------- plan - */
/**
 * Which file each name wants, read off the live wording.
 *
 * SEPARATE SO IT CAN BE DRIVEN. This script copies real files on a live
 * server; the part that decides WHAT to copy is checked in
 * dropship-audio-migrate-harness.php against a stub, because getting it
 * wrong writes somebody's audio onto the wrong name.
 */
function dsamPlan($conn, $CHAIN, $OPEN, $GUARDED = array(), $ROUNDS = array()) {
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
			if (isset($ROUNDS[$s['art']])) {
				$src = isset($ROUNDS[$s['art']][$pid]) ? $ROUNDS[$s['art']][$pid] : '';
			} else {
				foreach ($CHAIN as $term => $snd) {
					if (strpos($cap, $term) === false) continue;
					/* Guarded off for Filthy Mermaid and Oculus Lounge:
					   they were silent, and silent is the answer. */
					if (($pid === 3 || $pid === 4) && in_array($term, $GUARDED, true)) break;
					$src = $snd;
					break;
				}
			}
			/*
			 * THE OLD DROP SHIP NAME IS ONLY DROP SHIP'S. Falling back to
			 * it for any other theme is handing that theme Drop Ship's
			 * audio, which is the thing being fixed, not a convenience.
			 */
			if ($src === '' && $s['is_base'] && $s['legacy'] !== '') $src = $s['legacy'];
			if ($src . '.mp3' === $target) continue;
			if (!isset($want[$target])) {
				$want[$target] = array('src' => '', 'srcs' => array(), 'why' => array(), 'mute' => false);
			}
			if ($src === '') $want[$target]['mute'] = true;
			else { $want[$target]['srcs'][$src . '.mp3'] = true; $want[$target]['src'] = $src . '.mp3'; }
			$want[$target]['why'][$name . ': ' . $s['caption']] = true;
		}
		$open = dropshipAudioOpenSlot($conn, $pid);
		$leg  = isset($OPEN[$pid]) ? $OPEN[$pid] : '';
		if ($open['stem'] !== '' && $leg !== '') {
			$target = $open['stem'] . '.mp3';
			if ($leg . '.mp3' !== $target && !isset($want[$target])) {
				/* Same shape as every other row, or the agreement check
				   below trips over a half-built one. */
				$want[$target] = array(
					'src'  => $leg . '.mp3',
					'srcs' => array($leg . '.mp3' => true),
					'mute' => false,
					'why'  => array($name . ': Opening' => true),
				);
			}
		}
	}
	ksort($want);
	/*
	 * A SHARED NAME NEEDS ONE AGREED ANSWER.
	 *
	 * Several themes use the same word and so ask for the same file --
	 * every theme calls a surviving round "Survived", so all four want
	 * survived.mp3. If some of them were silent there, or they had
	 * different sounds, copying ANYTHING into that name gives it to all
	 * of them. Dropped with a reason rather than guessed at: the art
	 * page lists them as silent and they can be set one at a time.
	 */
	foreach ($want as $t => $w) {
		if ($w['src'] === '')                       { $want[$t]['skip'] = 'no source'; continue; }
		if ($w['mute'])                             { $want[$t]['skip'] = 'shared with a theme that was silent'; continue; }
		if (count($w['srcs']) > 1)                  { $want[$t]['skip'] = 'shared, and the themes had different sounds'; continue; }
	}
	ksort($want);
	return $want;
}

$GO = in_array('--go', $argv, true);
chdir(__DIR__);
include __DIR__ . '/db.php';
if (!isset($conn) || !is_object($conn)) exit("no database connection\n");
$dir = __DIR__ . '/sounds/';
if (!is_dir($dir)) exit("no sounds folder at $dir\n");
$want = dsamPlan($conn, $CHAIN, $OPEN, $GUARDED, $ROUNDS);
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
$made = $have = $stuck = $skip = 0;
echo ($GO ? "COPYING" : "DRY RUN -- nothing is being written. Add --go to apply.") . "\n\n";
foreach ($want as $target => $w) {
	$src = $w['src'];
	$who = implode(', ', array_keys($w['why']));
	if (is_file($dir . $target)) {
		$have++;
		printf("  have  %-28s\n", $target);
		continue;
	}
	if (!empty($w['skip'])) {
		$skip++;
		printf("  skip  %-28s %-24s %s\n", $target, $w['skip'], $who);
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

printf("\n%d to copy, %d already there, %d skipped, %d stuck, %d total\n",
	$made, $have, $skip, $stuck, count($want));

/*
 * ONE SOUND SPREAD THIN. Oculus Lounge's five rocket launcher frames all
 * take melee.mp3, because the old chain tested "Dildo" before it ever
 * reached the rocket launcher -- so that is honestly what plays today,
 * and copying it five times is faithful rather than wrong. It is also
 * obviously not what anybody wants, and it is invisible in a list of
 * sixty lines unless it is counted.
 */
$spread = array();
foreach ($want as $t => $w) {
	if (!empty($w['skip']) || $w['src'] === '' || is_file($dir . $t)) continue;
	$spread[$w['src']][] = $t;
}
$noisy = array();
foreach ($spread as $src => $ts) if (count($ts) >= 3) $noisy[$src] = $ts;
if ($noisy) {
	echo "\nWORTH A LOOK -- one sound is about to cover several frames:\n";
	foreach ($noisy as $src => $ts) {
		printf("  %-24s -> %s\n", $src, implode(', ', $ts));
	}
	echo "  That is what plays today, so copying is faithful. If you would rather\n"
	   . "  give those frames real sounds, leave them out and the art page will\n"
	   . "  list them as silent until you do.\n";
}
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
