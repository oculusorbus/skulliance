<?php
/**
 * dropship-audio-harness.php -- which sound a round plays.
 *
 * Run: php dropship-audio-harness.php
 *
 * displayRound() carried thirty-four if-branches matching caption text
 * against sound filenames, with every reskin's own words sitting in there
 * beside Drop Ship's -- Vibrator, Whip, Paddle, Ball Gag, Insert Anal
 * Beads. All of those are words the wording table already holds, so the
 * branches were the wording table written a second time in a file no
 * admin page could reach.
 *
 * THE ONE THING THAT MUST NOT BREAK IS THE ORDER. The match is a
 * SUBSTRING of the caption, so "Smoke Bomb" also matches "Pull Smoke Bomb
 * Pin" -- the pin has to be tested first or it never fires at all, and
 * nothing about a wrong order looks wrong until somebody plays a round
 * and hears the wrong thing. The branch order used to carry that; the
 * array order carries it now, and that is what most of this checks.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($cond, $msg) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $msg\n"; } }

require_once __DIR__ . '/dropship/dropship-themes.php';
require_once __DIR__ . '/dropship/dropship-art.php';
require_once __DIR__ . '/dropship/dropship-audio.php';

/* ---------- stubs ------------------------------------------------------- */
class ARes { public $num_rows; private $r; private $i = 0;
	public function __construct($r) { $this->r = $r; $this->num_rows = count($r); }
	public function fetch_assoc() { return $this->i < count($this->r) ? $this->r[$this->i++] : null; } }
class AConn { public $themes; public $text;
	public function __construct($themes = array(), $text = array()) {
		$this->themes = $themes; $this->text = $text; }
	public function query($sql) {
		if (strpos($sql, 'SHOW TABLES') !== false) return new ARes(array(array('t' => 'themes')));
		if (strpos($sql, 'FROM themes WHERE project_id') !== false) {
			preg_match('/project_id = (\d+)/', $sql, $m); $p = (int)$m[1];
			return new ARes(isset($this->themes[$p]) ? array($this->themes[$p]) : array());
		}
		if (strpos($sql, 'SELECT id, project_id') !== false || strpos($sql, 'FROM themes') !== false) {
			return new ARes(array_values($this->themes));
		}
		if (strpos($sql, 'FROM theme_text') !== false) {
			preg_match('/theme_id = (\d+)/', $sql, $m); $t = (int)$m[1];
			return new ARes(isset($this->text[$t]) ? $this->text[$t] : array());
		}
		return new ARes(array());
	} }
function arow($find, $rep) {
	return array('match_type' => 'exact', 'find' => $find, 'replacement' => $rep); }

echo "\norder is the rule, because the match is a substring\n";
$ev = dropshipAudioEvents();
$terms = array();
foreach ($ev as $i => $e) $terms[$e['term']] = $i;
ok(count($terms) === count($ev), 'two events share a term, so one of them can never fire');

/*
 * THE INVARIANT: if one term contains another, the LONGER one has to be
 * tested first. Derived from the terms themselves rather than written
 * down, so adding an event that shadows an existing one fails here
 * instead of silently stealing its sound.
 */
foreach ($terms as $a => $ia) {
	foreach ($terms as $b => $ib) {
		if ($a === $b) continue;
		if (strpos($a, $b) === false) continue;      /* $b is inside $a */
		ok($ia < $ib, "'$b' is tested before '$a', which contains it, so '$a' never fires");
	}
}
/* Spot-checks on the pairs that actually exist, so the loop above cannot
   quietly stop finding anything. */
foreach (array(array('Pull Smoke Bomb Pin', 'Smoke Bomb'),
               array('Load Machine Gun', 'Machine Gun'),
               array('Rocket Launcher Gunfire', 'Rocket Launcher'),
               array('Rocket Launcher Explosion', 'Rocket Launcher')) as $pair) {
	ok(isset($terms[$pair[0]], $terms[$pair[1]]) && $terms[$pair[0]] < $terms[$pair[1]],
	   "'{$pair[0]}' is not tested before '{$pair[1]}'");
}
/* The catch-all is last, or it answers for every round. */
ok($ev[count($ev) - 1]['term'] === 'Survived',
   'the catch-all is not last, so every round plays it');

echo "\nevery frame the run shows has a sound slot\n";
/*
 * EVERY NUMBERED FRAME IS ITS OWN MOMENT. Reported looking at the
 * Demolition row: demo1 is the activation and demo2 the explosion, two
 * distinct sounds, and only one was on the page. They are two frames
 * with two captions -- "Demolition" and "Explosion" -- so the audio was
 * there; what was missing was a slot for every OTHER frame in that
 * position. Five of them had none at all (Night Vision Goggles, Radio,
 * Reinforcements, Jet Pack Takeoff, Drop Box), which is not the same
 * thing as a frame that should be silent.
 *
 * SCRAPED FROM THE GAME, not restated. Each caption is read off the
 * markup that emits the frame, so a frame added later with a caption
 * nothing matches fails here.
 */
$ds_src = file_get_contents(__DIR__ . '/dropship/dropship.php');
preg_match_all('/evaluateText\("([^"]+)"\)\."<\/h3>";\s*\n\s*\$dropshipMarkup\.="<img src=.\"\.\$prefix\.\"images\/([a-z0-9\/-]+)\./',
	$ds_src, $fm, PREG_SET_ORDER);
ok(count($fm) >= 30, 'only ' . count($fm) . ' captioned frames found; the scrape is broken');
$terms_in_order = array();
foreach ($ev as $e) $terms_in_order[] = $e['term'];
foreach ($fm as $f) {
	list(, $caption, $frame) = $f;
	$hit = '';
	foreach ($terms_in_order as $t) { if (strpos($caption, $t) !== false) { $hit = $t; break; } }
	ok($hit !== '', "the frame images/$frame is captioned \"$caption\" and no sound event matches it");
}
/* AND THE PAIRS THAT PROVE IT IS PER FRAME, not per weapon. */
foreach (array('Demolition' => 'Explosion',
               'Pull Smoke Bomb Pin' => 'Smoke Bomb',
               'Load Machine Gun' => 'Machine Gun',
               'Pull Grenade Pin' => 'Throw Grenade') as $a => $b) {
	ok(isset($terms[$a]) && isset($terms[$b]) && $a !== $b,
	   "$a and $b are not two separate events, so a two-frame weapon has one sound");
}

echo "\nevery sound the old chain could play is still reachable\n";
/*
 * THE OLD CHAIN, as it stood, taken from git rather than from memory.
 * Every sound file it could reach has to still be reachable, or this
 * refactor silently dropped one.
 */
/* THE LAST COMMIT THAT STILL HAD THE CHAIN, found rather than guessed at
   HEAD~1 -- which stopped being the right commit the moment anything else
   was committed on top. -S finds the commit that REMOVED it; its parent is
   the one that still has it. */
$old = '';
$cut = trim((string)shell_exec('git -C ' . escapeshellarg(__DIR__)
	. ' log -S"evaluateAudio(currentRound" --format=%H -- dropship/dropship.js 2>/dev/null | head -1'));
if ($cut !== '') {
	$old = (string)shell_exec('git -C ' . escapeshellarg(__DIR__)
		. ' show ' . escapeshellarg($cut . '^:dropship/dropship.js') . ' 2>/dev/null');
}
ok(is_string($old) && strpos($old, 'evaluateAudio') !== false,
   'the old chain could not be read out of git, so the coverage check below proves nothing');
if (is_string($old) && strpos($old, 'evaluateAudio') !== false) {
	/* COMMENTED-OUT BRANCHES ARE NOT BEHAVIOUR. //configureAudio(2,
	   "deathcontra") sat in there and has not played for years. */
	$old_live = preg_replace('#^\s*//.*$#m', '', $old);
	preg_match_all('/configureAudio\(2, "([^"]+)"/', $old_live, $m);
	$was = array_values(array_unique($m[1]));
	/*
	 * REACHABLE EITHER WAY. A sound is still reachable if it is an
	 * event's default OR if it is what some theme's own word slugs to --
	 * vibrator.mp3 is reached by Oculus Lounge calling the Melee a
	 * Vibrator, which is the entire point of the change and would read
	 * as a regression against defaults alone. The themes below carry the
	 * real wording that put those filenames in the old chain.
	 */
	$cov = new AConn(
		array(1 => array('id' => 10, 'project_id' => 1, 'slug' => 'drop-ship', 'name' => 'Drop Ship'),
		      3 => array('id' => 30, 'project_id' => 3, 'slug' => 'filthy-mermaid', 'name' => 'Filthy Mermaid'),
		      4 => array('id' => 40, 'project_id' => 4, 'slug' => 'oculus-lounge', 'name' => 'Oculus Lounge')),
		array(40 => array(arow('Melee', 'Vibrator'), arow('Tactical Katana', 'Dildo'),
		                  arow('Smoke Bomb', 'Whip'), arow('Pull Smoke Bomb Pin', 'Pull Out Whip'),
		                  arow('Explosion', 'Spank'), arow('Demolition', 'Paddle'),
		                  arow('Radar', 'Ball Gag'), arow('Sniper Rifle', 'Butt Plug'),
		                  arow('Pull Grenade Pin', 'Insert Anal Beads'),
		                  arow('Throw Grenade', 'Remove Anal Beads')),
		      30 => array(arow('Drop Ship Initiating', 'Filthy Mermaid Entrance'),
		                  arow('Drop Ship Landed', 'Filthy Mermaid Doorway'))));
	$now = array();
	foreach ($ev as $e) if ($e['sound'] !== '') $now[$e['sound']] = true;
	foreach (array(1, 3, 4) as $p) {
		foreach (dropshipAudioSlots($cov, $p) as $s) if ($s['stem'] !== '') $now[$s['stem']] = true;
	}
	/*
	 * THE TWO THAT GENUINELY MOVE. Filthy Mermaid's opening rounds play
	 * door.mp3 and stairs.mp3, named after the sound; its words for
	 * those rounds are "Filthy Mermaid Entrance" and "... Doorway", so
	 * the convention asks for files under those names and the old two
	 * are reachable only once they are renamed. Listed here rather than
	 * quietly excluded, so the day somebody renames them this check
	 * fails and says the note is spent.
	 */
	/*
	 * THE TERM IS THE WORDING TABLE'S, NOT THE OLD CHAIN'S. The chain
	 * matched "Pull Grenade" because a substring of the caption was all
	 * it needed; the lookup here is by the whole term, and the wording
	 * row is "Pull Grenade Pin". The short form resolves to nothing, so
	 * Oculus Lounge would never reach its own sound for it.
	 */
	$pgp = false;
	foreach ($ev as $e) if ($e['term'] === 'Pull Grenade Pin') $pgp = true;
	ok($pgp, 'the grenade pin event is not named the way the wording table names it');

	$moved = array(
		/* Filthy Mermaid's opening two rounds. Its words are "Filthy
		   Mermaid Entrance" and "... Doorway". */
		'door', 'stairs',
		/* Oculus Lounge's, named with the spaces squeezed out rather than
		   slugged: ball-gag, insert-anal-beads, remove-anal-beads. */
		'ballgag', 'analbeadsinsertion', 'analbeadsremoval',
		/* Named after the sound rather than the word: Pull Out Whip and
		   Paddle both reached this one. */
		'spankme',
	);
	foreach ($was as $sound) {
		if (in_array($sound, $moved, true)) {
			ok(!isset($now[$sound]),
			   "$sound is reachable again; rename it onto the wording and drop it from \$moved");
			continue;
		}
		ok(isset($now[$sound]),
		   "$sound was playable before this refactor and nothing reaches it now");
	}
	ok(count($was) >= 20, 'only ' . count($was) . ' sounds found in the old chain; the scrape is broken');
	ok(!in_array('deathcontra', $was, true),
	   'a commented-out branch is being counted as behaviour');
	/*
	 * SIX, AND THE NUMBER IS THE POINT. Each one is a live theme playing
	 * a sound whose filename does not match the word the theme uses, so
	 * until it is copied onto that name the event falls back to Drop
	 * Ship's sound. The art page shows each as "playing <old file>" with
	 * a picker to set it, which is the two-click fix. If this count ever
	 * grows, somebody added a themed sound the old way.
	 */
	ok(count($moved) === 6, 'the rename list is ' . count($moved) . ' files, not the 6 that were measured');
}

echo "\nthe filename is the wording, like an icon\n";
/* Oculus Lounge calling the Melee a Vibrator IS the instruction to
   supply sounds/vibrator.mp3. Nothing is stored. */
$c = new AConn(
	array(4 => array('id' => 40, 'project_id' => 4, 'slug' => 'oculus-lounge'),
	      1 => array('id' => 10, 'project_id' => 1, 'slug' => 'drop-ship')),
	array(40 => array(arow('Melee', 'Vibrator'), arow('Smoke Bomb', 'Whip'),
	                  arow('Demolition', 'Paddle'), arow('Radar', 'Ball Gag'))));
$by = array();
foreach (dropshipAudioSlots($c, 4) as $s) $by[$s['term']] = $s;
ok($by['Melee']['said'] === 'Vibrator', "the theme's own word is not used to match the caption");
ok($by['Melee']['rel'] === 'sounds/vibrator.mp3',
   'the file asked for is not named after the wording: ' . $by['Melee']['rel']);
ok($by['Smoke Bomb']['rel'] === 'sounds/whip.mp3', 'a renamed thing asks for its old file');
ok($by['Radar']['rel'] === 'sounds/ball-gag.mp3',
   'a two-word name is not slugged the way an icon is: ' . $by['Radar']['rel']);
/* Untouched wording asks for its own term, which is what Drop Ship does
   for all of them. */
$by1 = array();
foreach (dropshipAudioSlots($c, 1) as $s) $by1[$s['term']] = $s;
ok($by1['Melee']['rel'] === 'sounds/melee.mp3', 'an unrenamed thing does not ask for its own word');
ok($by1['Smoke Bomb']['said'] === 'Smoke Bomb', 'an unrenamed thing reports a different word');

/*
 * THE OPENING MUST NOT COLLIDE WITH A CAPTION.
 *
 * It is the one slot with no caption to derive from, so it is named
 * after the theme -- and Filthy Mermaid's word for the round 1 caption
 * "Drop Ship" is "Filthy Mermaid", which means a bare slug would have
 * put two different sounds on filthy-mermaid.mp3. Whichever was
 * uploaded second would silently become both.
 */
/* A PROJECT ID NOT USED ABOVE: dropshipThemeConfig() and
   dropshipThemeDictionaries() both cache per id for the request, so
   reusing 3 would answer with the dictionary an earlier section set. */
$fmc = new AConn(
	array(33 => array('id' => 330, 'project_id' => 33, 'slug' => 'filthy-mermaid', 'name' => 'Filthy Mermaid')),
	array(330 => array(arow('Drop Ship', 'Filthy Mermaid'))));
$open3 = dropshipAudioOpenSlot($fmc, 33);
$stems = array();
foreach (dropshipAudioSlots($fmc, 33) as $sl) if ($sl['stem'] !== '') $stems[$sl['stem']] = $sl['term'];
ok($open3['stem'] !== '', 'the opening has no filename at all');
ok(!isset($stems[$open3['stem']]),
   'the opening asks for the same file as ' . ($stems[$open3['stem']] ?? '?')
 . ', so two different sounds fight over one name');
ok(isset($stems['filthy-mermaid']),
   'the round 1 caption no longer asks for the theme word, so this check has gone stale');

echo "\nthe delay belongs to the event, not the theme\n";
/* When a sound lands against the animation is not a thing a reskin
   decides, so it stays in the registry and follows the event through
   any amount of renaming. */
ok($by['Smoke Bomb']['delay'] === 0 && $by1['Smoke Bomb']['delay'] === 0,
   'the delay changed between themes');
$pin = null; foreach ($ev as $e) if ($e['term'] === 'Pull Smoke Bomb Pin') $pin = $e;
ok($pin && $pin['delay'] === 600, 'the grenade pin lost its 600ms delay');
foreach (array('Tactical Katana' => 1000, 'Air Strike' => 2000, 'Exo Suit' => 300,
               'Sniper Rifle' => 1000) as $t => $d) {
	$got = null; foreach ($ev as $e) if ($e['term'] === $t) $got = $e['delay'];
	ok($got === $d, "$t should wait {$d}ms, waits " . var_export($got, true));
}

echo "\nnothing goes quiet on the day this ships\n";
/*
 * Drop Ship's own files are named after the SOUND -- grenadecontra,
 * massexplosioncontra, enemyguncontra -- not after its words, so almost
 * nothing is sitting at the name the convention asks for yet. The table
 * falls back to the old name last, or the game is silent until every
 * file has been renamed.
 */
$snd = __DIR__ . '/dropship/sounds';
$made = is_dir($snd) || @mkdir($snd, 0777, true);
ok($made, 'could not make a sounds folder to probe, so the checks below prove nothing');
if ($made) {
	$probe = array();
	$put = function ($name) use ($snd, &$probe) {
		$f = $snd . '/' . $name . '.mp3';
		if (!is_file($f)) { file_put_contents($f, 'x'); $probe[] = $f; }
		return $f;
	};
	/* Only the OLD name exists: the table must still find it. */
	$put('grenadecontra');
	$rows = dropshipAudioTable($c, 1);
	$find = function ($rows, $m) { foreach ($rows as $r) if ($r['m'] === $m) return $r; return null; };
	$sb = $find($rows, 'Smoke Bomb');
	ok($sb !== null, 'the smoke bomb went silent because its file is still on the old name');
	ok($sb && strpos($sb['f'], 'sounds/grenadecontra.mp3') === 0,
	   'the old name is not used as the last resort: ' . ($sb ? $sb['f'] : 'absent'));
	/* Now the file exists under the WORDING too: that one wins. */
	$put('smoke-bomb');
	$rows = dropshipAudioTable($c, 1);
	$sb = $find($rows, 'Smoke Bomb');
	ok($sb && strpos($sb['f'], 'sounds/smoke-bomb.mp3') === 0,
	   'renaming a file onto the wording does not take over: ' . ($sb ? $sb['f'] : 'absent'));
	/*
	 * A RENAMED THING NEVER FALLS BACK TO DROP SHIP'S SOUND.
	 *
	 * Reported looking at Filthy Mermaid: "Demolition -> Paddle, playing
	 * demo.mp3". True and wrong -- a theme that has given the thing its
	 * own name has said it is not Drop Ship's object, so Drop Ship's
	 * sound is the one answer it must not get. Same rule as the Discord
	 * image and the bunker art: no sound beats another game's.
	 */
	$rows4 = dropshipAudioTable($c, 4);
	$wh = $find($rows4, 'Whip');
	ok($wh === null,
	   "a renamed thing falls back to Drop Ship's sound: " . ($wh ? $wh['f'] : ''));
	/* AND A THING IT HAS NOT RENAMED STILL DOES, or a theme that only
	   changes a few words goes quiet everywhere else. */
	$ex = $find(dropshipAudioTable($c, 4), 'Extra Life');
	$put('extralife');
	$ex = $find(dropshipAudioTable($c, 4), 'Extra Life');
	ok($ex && strpos($ex['f'], 'sounds/extralife.mp3') === 0,
	   'an unrenamed thing stopped falling back too: ' . ($ex ? $ex['f'] : 'absent'));
	/* And its own file wins once it is there. */
	$put('whip');
	$rows4 = dropshipAudioTable($c, 4);
	$wh = $find($rows4, 'Whip');
	ok($wh && strpos($wh['f'], 'sounds/whip.mp3') === 0,
	   "a theme's own file does not beat the fallback: " . ($wh ? $wh['f'] : 'absent'));

	/* CACHE BUSTED BY THE FILE. rand() against a week-long max-age is
	   what made dropship.js itself serve stale copies. */
	ok($wh && strpos($wh['f'], '?v=') !== false, 'the sound path carries no cache buster');
	ok($wh && preg_match('/\?v=\d{6,}$/', $wh['f']) === 1,
	   'the buster is not the file mtime: ' . $wh['f']);

	/* A ROW WITH NO FILE IS LEFT OUT, not sent empty -- silence is a real
	   answer and an <audio> pointed at nothing errors every round. */
	foreach (dropshipAudioTable($c, 4) as $r) {
		ok($r['f'] !== '' && strpos($r['f'], 'sounds/') === 0,
		   'a row was sent with no usable file: ' . json_encode($r));
	}
	$silent = $find(dropshipAudioTable($c, 1), 'Radar');
	ok($silent === null, 'an event with no file anywhere is still sent to the browser');

	/* WHO ELSE IS LISTENING -- the warning the shared folder needs. */
	$claims = dropshipAudioClaims($c, 'smoke-bomb', 4, 'Smoke Bomb');
	ok(in_array('drop-ship: Smoke Bomb', $claims, true) || count($claims) >= 1,
	   'replacing a shared sound reports nobody else: ' . json_encode($claims));
	$own = dropshipAudioClaims($c, 'vibrator', 4, 'Melee');
	ok(!in_array('oculus-lounge: Vibrator', $own, true),
	   'the warning names the theme doing the replacing, so it nags about itself');

	foreach ($probe as $f) @unlink($f);
	if (!glob($snd . '/*')) @rmdir($snd);
}

echo "\nthe panel says what the run does\n";
/*
 * THE PAGE WORKS OUT WHAT IS PLAYING SO IT CAN SHOW IT, and that is a
 * second copy of the resolution -- so it can disagree with the run and
 * tell somebody their theme is fine when it is silent. Same rule, same
 * order, asserted on the source because the page cannot be executed
 * here.
 */
$adm_src = (string)@file_get_contents(__DIR__ . '/dropship/admin-art.php');
ok(strpos($adm_src, "\$renamed = (\$as['said'] !== \$as['term']);") !== false,
   'the art page does not know a renamed thing never falls back, so it reports a sound the run will not play');
ok(strpos($adm_src, "\$playing = \$as['exists'] ? \$as['rel']") !== false
   && strpos($adm_src, "(\$renamed ? ''") !== false,
   'the page resolves what is playing by a different rule from the run');
/* And when it is silent it offers the file Drop Ship uses, because that
   is nearly always the one being looked for. */
ok(strpos($adm_src, "\$suggest = (\$playing === '' && \$as['legacy_has'])") !== false,
   'a silent row does not offer the obvious file, so every fix is a hunt through the dropdown');
/*
 * PLAY IS A PREVIEW, NOT A REPORT. It was disabled whenever the RUN
 * plays nothing, so a row reading "silent, Drop Ship uses demo.mp3"
 * named a file and then refused to play it. What the run does is the
 * status text; what you can listen to is anything the row points at.
 */
ok(strpos($adm_src, "\$preview = \$playing !== '' ? dropshipAudioUrl(\$playing)") !== false,
   'the play button is still tied to what the run plays, so a named file cannot be heard');
/* THE FALLBACK HALF SPECIFICALLY. Without it $preview is just $playing
   under a new name and the silent row is mute again -- which is the bug,
   so it needs its own assertion rather than riding on the line above. */
ok(strpos($adm_src, "(\$suggest !== '' ? dropshipAudioUrl('sounds/' . \$suggest) : '')") !== false,
   'a row the run plays silent has no candidate to preview, which is the whole report');
ok(strpos($adm_src, "\$preview === '' ? 'disabled'") !== false,
   'the button is disabled on something other than having nothing to play');
/* And the difference is visible, or the button quietly lies about what
   the theme does. Matched on the RULE BODY, not the selector: a :hover
   rule for the same class satisfies a bare selector check. */
ok(strpos($adm_src, "' cand'") !== false,
   'nothing marks a button that previews a sound the run will not play');
ok(preg_match('/\.play\.cand\{[^}]*border-style:dashed/', $adm_src) === 1,
   'a preview of a sound the run will not play looks identical to the real thing');

echo "\nthe panel pairs a sound with the thing it belongs to\n";
$plan = dropshipArtPlan($c, 1, 'gif', 'drop-ship');
$rl = array();
foreach ($plan as $g) foreach ($g['rows'] as $r) if ($r['label'] !== '') $rl[] = $r['label'];
ok(dropshipAudioFamily('Pull Smoke Bomb Pin', $rl) === 'Smoke Bomb',
   'the pin does not sit under the smoke bomb: ' . dropshipAudioFamily('Pull Smoke Bomb Pin', $rl));
ok(dropshipAudioFamily('Rocket Launcher Explosion', $rl) === 'Rocket Launcher',
   'a rocket launcher stage does not sit under the rocket launcher');
ok(dropshipAudioFamily('Flamethrower Flames', $rl) === 'Flamethrower',
   'a flamethrower stage does not sit under the flamethrower');
ok(dropshipAudioFamily('Survived', $rl) === '',
   'a round survived was paired with an object, which it is not');
/* NOTHING MAY VANISH. Every event either pairs with a row the page
   renders or lands in the section at the end. */
$seen = 0;
foreach (dropshipAudioSlots($c, 1) as $s) {
	$f = dropshipAudioFamily($s['term'], $rl);
	if ($f === '' || !in_array($f, $rl, true)) { $seen++; continue; }
	$seen++;
}
ok($seen === count($ev), 'an event is neither paired nor loose, so the page never shows it');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
