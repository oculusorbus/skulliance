<?php
/**
 * dropship-icons-harness.php — a renamed thing must not become a 404.
 *
 * Run: php dropship-icons-harness.php
 *
 * THE BUG. The achievement LABEL and the achievement ICON were themed by
 * the same call. evaluateText() turned "Stormed the Beach" into "Landed
 * the Eon Raider", which is right, and then the same translation was used
 * as the filename -- so the page asked for icons/eon-raider.png, which
 * nobody had drawn. Three broken images on DHC2 Fighters, and the trap
 * armed for every theme after it: naming a place in the admin silently
 * created a demand for a PNG.
 *
 * Driven against a REAL directory the test fills itself, because the
 * icons ship by FTP and are not in the repo -- a check against the live
 * folder would assert nothing on a developer's machine.
 */
require_once __DIR__ . '/harness-strip.php';

$fail = 0;
/* RETURNS THE VERDICT. It used to return void, and the lift loop below
   reads `if (!ok(...)) exit(1)` -- so the harness exited on its own first
   PASS and reported nothing else. */
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return true; }
	$fail++; echo "  FAIL  $msg\n"; return false;
}

/* Lifted by source: dropship/db.php connects at file scope. */
$src = file_get_contents(__DIR__ . '/dropship/db.php');
function lift($src, $sig) {
	$at = strpos($src, $sig);
	if ($at === false) return null;
	$depth = 0;
	for ($j = strpos($src, '{', $at); $j < strlen($src); $j++) {
		if ($src[$j] === '{') $depth++;
		elseif ($src[$j] === '}') { $depth--; if (!$depth) return substr($src, $at, $j - $at + 1); }
	}
	return null;
}
foreach (array('function dropshipIconSlug(', 'function dropshipIconSrc(',
               'function dropshipIconImg(') as $sig) {
	$fn = lift($src, $sig);
	if (!ok($fn !== null, rtrim($sig, '(') . '() is gone from dropship/db.php')) exit(1);
	eval($fn);
}

/* The theme's dictionary, as a stub. evaluateText() is the real one's
   name, so the lifted code binds to this exactly as it does in the app. */
$GLOBALS['theme_words'] = array();
function evaluateText($t) {
	$w = $GLOBALS['theme_words'];
	return isset($w[$t]) ? $w[$t] : $t;
}

/*
 * A FRESH DIRECTORY PER SCENARIO, not one folder rewritten between them.
 * dropshipIconSrc() caches is_file() per request, which is right in the
 * app -- the folder cannot change mid-render -- and wrong for a test that
 * deletes a file and asks again. The first version did exactly that and
 * the fallback case passed on a cached hit for a file that was gone.
 */
$dirs = array();
function scenario($files) {
	global $dirs;
	$d = sys_get_temp_dir() . '/dsicons-' . getmypid() . '-' . count($GLOBALS['dirs']);
	@mkdir($d, 0777, true);
	foreach ($files as $n) file_put_contents($d . '/' . $n . '.png', 'x');
	$dirs[] = $d;
	return $d;
}

echo "\nslugs\n";
ok(dropshipIconSlug('Heavy Armor') === 'heavy-armor', 'a two-word name does not slugify');
ok(dropshipIconSlug('Landed the Eon Raider!') === 'landed-the-eon-raider', 'punctuation survives into the filename');
ok(dropshipIconSlug('  Eon   Raider  ') === 'eon-raider', 'runs of spaces become runs of hyphens');
ok(dropshipIconSlug('') === '', 'an empty name is not empty');

echo "\nthe theme's own art wins when it exists\n";
$d = scenario(array('beach', 'eon-raider'));
$GLOBALS['theme_words'] = array('beach' => 'Eon Raider');
ok(dropshipIconSrc('beach', $d) === 'icons/eon-raider.png',
   'a theme that ships its own art is not getting it');

echo "\nand the base art is used when it does not\n";
$d = scenario(array('beach'));                 // only the original exists
$GLOBALS['theme_words'] = array('beach' => 'Eon Raider');
ok(dropshipIconSrc('beach', $d) === 'icons/beach.png',
   'THE LIVE BUG: a renamed achievement asks for art nobody drew');

echo "\neither spelling of the themed key resolves\n";
/* The two callers disagreed: scenes looked up 'beach', items looked up
   'heavy-armor' -- slugified BEFORE translating. A theme keyed on either
   has to keep working. */
$d = scenario(array('nano-patch'));
$GLOBALS['theme_words'] = array('heavy-armor' => 'Nano Patch');
ok(dropshipIconSrc('Heavy Armor', $d) === 'icons/nano-patch.png',
   'a dictionary keyed on the slug no longer resolves');
$d = scenario(array('nano-patch'));
$GLOBALS['theme_words'] = array('Heavy Armor' => 'Nano Patch');
ok(dropshipIconSrc('Heavy Armor', $d) === 'icons/nano-patch.png',
   'a dictionary keyed on the raw name no longer resolves');

echo "\nno art at all draws no image\n";
/* icons/extra-life.png has never existed. A broken image box with a
   filename under it is worse than nothing. */
$d = scenario(array());
$GLOBALS['theme_words'] = array();
ok(dropshipIconSrc('Extra Life', $d) === '', 'a name with no art still returns a src');
ok(dropshipIconImg('Extra Life', 'icon', $d) === '', 'a name with no art still emits an <img>');
$d = scenario(array('medkit'));
ok(dropshipIconImg('Medkit', 'icon', $d) === "<img class='icon' src='icons/medkit.png'/>",
   'the img tag changed shape');
/* dropship.php wants one with no class at all. */
ok(dropshipIconImg('Medkit', '', $d) === "<img src='icons/medkit.png'/>",
   'the classless form emits an empty class attribute');

echo "\nthe callers pass the base name, not a translation\n";
/*
 * This is what stops the bug being reintroduced one call site at a time.
 * loadAchievement() themes the icon itself now, so a caller that hands it
 * evaluateText(...) is asking for the translated string to be translated
 * again and then used as a filename, which is where this started.
 */
$clean = harness_strip_comments($src);
ok(strpos($clean, 'loadAchievement($status, evaluateText(') === false,
   'a caller is passing an already-translated icon name to loadAchievement()');
ok(preg_match('/loadAchievement\(\$status, "beach", /', $clean) === 1,
   'the beach achievement no longer passes its base icon name');
ok(strpos($clean, "src='icons/\".evaluateText(") === false,
   'the shop still builds an icon path out of a translation');
/*
 * ALL OF THEM, not just the shop. The first pass of this fix changed two
 * call sites and this sweep found five more -- the inventory list, two
 * more item tables, the Super Soldier badge and the random drop icon --
 * every one of them building icons/<translated>.png by hand.
 */
foreach (array('dropship/db.php', 'dropship/dashboard.php', 'dropship/dropship.php') as $f) {
	$c = harness_strip_comments(file_get_contents(__DIR__ . '/' . $f));
	ok(preg_match("~src='icons/\"\\.evaluateText\\(~", $c) !== 1,
	   "$f still builds an icon path out of a translation");
}
/* And the one place a path is still built by hand is the lock, which is
   platform art and deliberately not themed. */
ok(substr_count($clean, "src='icons/\".strtolower(\$status).\".png'") === 1,
   'the locked/unlocked icon changed shape or gained a sibling');

foreach ($dirs as $d2) { foreach (glob($d2 . '/*.png') as $f2) @unlink($f2); @rmdir($d2); }
echo "\n" . ($fail ? "$fail FAILED\n" : "all passed\n");
exit($fail ? 1 : 0);
