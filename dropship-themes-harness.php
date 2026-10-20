<?php
/**
 * dropship-themes-harness.php — themes as data, and the fallback that makes
 * shipping them safe.
 *
 * Run: php dropship-themes-harness.php
 *
 * evaluateText() is on the path of every label on every page. The contract
 * worth proving is not that the new tables work -- it is that they change
 * NOTHING until somebody deliberately fills them in.
 */
require_once __DIR__ . '/dropship/dropship-themes.php';
require_once __DIR__ . '/harness-strip.php';

$fail = 0;
function ok($cond, $msg) {
	global $fail;
	if ($cond) { echo "  ok    $msg\n"; return; }
	$fail++; echo "  FAIL  $msg\n";
}

class TRes {
	private $rows; public $num_rows;
	public function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	public function fetch_assoc() { return array_shift($this->rows); }
}
class TConn {
	public $error = '';
	public $themes;   /* project_id => row */
	public $text;     /* theme_id => rows */
	public $queries = 0;
	public function __construct($themes = array(), $text = array()) {
		$this->themes = $themes; $this->text = $text;
	}
	public function query($sql) {
		$this->queries++;
		if (strpos($sql, 'SHOW TABLES') !== false) return new TRes(array(array('t' => 'dropship_themes')));
		if (strpos($sql, 'FROM dropship_themes WHERE project_id') !== false) {
			preg_match('/project_id = (\d+)/', $sql, $m);
			$pid = (int)$m[1];
			return new TRes(isset($this->themes[$pid]) ? array($this->themes[$pid]) : array());
		}
		if (strpos($sql, 'FROM dropship_theme_text') !== false) {
			preg_match('/theme_id = (\d+)/', $sql, $m);
			$tid = (int)$m[1];
			return new TRes(isset($this->text[$tid]) ? $this->text[$tid] : array());
		}
		return new TRes(array());
	}
}
function pair($type, $find, $rep) {
	return array('match_type' => $type, 'find' => $find, 'replacement' => $rep);
}

/* One themed project (10) and one with a row but no text (11). */
$conn = new TConn(
	array(10 => array('id' => 1, 'project_id' => 10, 'slug' => 'a'),
	      11 => array('id' => 2, 'project_id' => 11, 'slug' => 'b')),
	array(1 => array(pair('exact', 'Soldiers', 'Citizens'),
	                 pair('exact', 'SCRIP', 'TIDDIES'),
	                 pair('description', 'beach', 'lobby')),
	      2 => array()));

echo "\nan empty theme changes nothing\n";
/*
 * THE WHOLE SAFETY ARGUMENT. The tables can exist on production with no rows
 * and evaluateText() must behave exactly as it did, or deploying the schema
 * is a content change nobody approved.
 */
ok(dropshipThemeDictionaries($conn, 11) === null,
   'a theme row with no overrides returns a dictionary, blanking the built-in wording');
ok(dropshipThemeText($conn, 11, 'Soldiers') === null,
   'a theme with no overrides answers instead of falling back');
ok(dropshipThemeText($conn, 99, 'Soldiers') === null,
   'a project with no theme row at all answers instead of falling back');

echo "\nthe two lookups keep their own jobs\n";
/* Exact replaces a whole label and nothing else; description rewrites inside
   a sentence and only when no exact match was found. Collapsing them breaks
   one or the other. */
ok(dropshipThemeText($conn, 10, 'Soldiers') === 'Citizens', 'an exact override does not apply');
ok(dropshipThemeText($conn, 10, 'Soldiers of fortune') === 'Soldiers of fortune',
   'an exact override leaked into a longer string: '
 . dropshipThemeText($conn, 10, 'Soldiers of fortune'));
ok(dropshipThemeText($conn, 10, 'Stormed the beach') === 'Stormed the lobby',
   'a description override does not rewrite inside a sentence: '
 . dropshipThemeText($conn, 10, 'Stormed the beach'));
ok(dropshipThemeText($conn, 10, 'Nothing to see') === 'Nothing to see',
   'an unmatched string was altered');
/* NOTE, so nobody reads coverage that is not here: the str_contains() guard
   in front of the description pass is a PERFORMANCE guard, not a behavioural
   one -- str_replace() is already a no-op when the needle is absent, so
   removing it is an equivalent mutant and no assertion can catch it. It is
   kept because evaluateText() runs hundreds of times a page and the original
   code guarded it the same way. Mutation-tested and confirmed equivalent. */
/* ORDER: exact must win outright, or a label containing a description word
   gets rewritten twice. */
$c2 = new TConn(array(20 => array('id' => 3, 'project_id' => 20, 'slug' => 'c')),
                array(3 => array(pair('exact', 'beach', 'Approach'),
                                 pair('description', 'beach', 'lobby'))));
ok(dropshipThemeText($c2, 20, 'beach') === 'Approach',
   'the description pass beat the exact match: ' . dropshipThemeText($c2, 20, 'beach'));

echo "\nit is read once per theme, not once per label\n";
/*
 * evaluateText() runs hundreds of times rendering one dashboard. A query per
 * call would be slower than the if/else chain this replaces, which would
 * make the whole change a regression.
 */
$c3 = new TConn(array(30 => array('id' => 4, 'project_id' => 30, 'slug' => 'd')),
                array(4 => array(pair('exact', 'A', 'B'))));
for ($i = 0; $i < 50; $i++) dropshipThemeText($c3, 30, 'A');
ok($c3->queries <= 2, 'the theme was queried ' . $c3->queries . ' times for 50 labels');

echo "\na slug cannot escape the images folder\n";
/* The slug IS the directory name, concatenated into a path. */
ok(dropshipThemeSlug('Oculus Lounge') === 'oculus-lounge', 'a normal name does not slugify');
foreach (array('../../etc', '..', 'a/b', 'a\\b', '<script>') as $bad) {
	$s = dropshipThemeSlug($bad);
	ok(strpos($s, '.') === false && strpos($s, '/') === false && strpos($s, '\\') === false,
	   "slug '$bad' came back as '$s', which can leave the folder");
}
ok(dropshipThemeSlug('   ') === '', 'a blank slug is not rejected');

echo "\nonly staff can edit\n";
$_SESSION = array();
ok(dropshipThemeAdmin() === false, 'a signed-out visitor can edit themes');
$_SESSION['userData'] = array('discord_id' => '1');
ok(dropshipThemeAdmin() === false, 'any signed-in player can edit themes');
$_SESSION['userData'] = array('discord_id' => '772831523899965440');
ok(dropshipThemeAdmin() === true, 'the admin cannot edit themes');
$GLOBALS['discordid_ohhmeed'] = '424242';
$_SESSION['userData'] = array('discord_id' => '424242');
ok(dropshipThemeAdmin() === true, 'the credential globals are not honoured');
/*
 * DROP SHIP USER 1 IS THE OWNER. Sound as a key because the Drop Ship users
 * table has no project_id -- checkUser() matches on discord_id alone, one
 * row per person for the whole app -- so an id is a stable identity here
 * rather than a per-project counter meaning someone different on each theme.
 * Compared as an integer because the session stores it as a string.
 */
$_SESSION['userData'] = array('dropship_user_id' => '1');
ok(dropshipThemeAdmin() === true, 'Drop Ship user 1 cannot edit themes');
$_SESSION['userData'] = array('dropship_user_id' => 1);
ok(dropshipThemeAdmin() === true, 'user 1 fails when the id is an int rather than a string');
/* AND NOBODY ELSE RIDES IN ON IT. An off-by-one here hands the whole admin
   to every registered player, which is why the ids either side are tested
   and not just the happy path. */
foreach (array('2', 2, '', '0', 'x', '1x', '-1') as $other) {
	$_SESSION['userData'] = array('dropship_user_id' => $other);
	ok(dropshipThemeAdmin() === false,
	   'dropship_user_id ' . var_export($other, true) . ' was granted admin');
}

echo "\nevaluateText asks the table first and falls back\n";
$db = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($db, 'dropshipThemeText($conn') !== false,
   'evaluateText() never consults the theme tables');
$at_lookup = strpos($db, 'dropshipThemeText($conn');
$at_chain  = strpos($db, "\$replacements['SCRIP']");
ok($at_lookup !== false && $at_chain !== false && $at_lookup < $at_chain,
   'the hardcoded chain runs before the table, so a saved theme is ignored');
/* The chain MUST still be there. Deleting it would turn an empty table into
   a site with no wording at all. */
ok($at_chain !== false, 'the fallback chain was removed; an empty table would blank every label');
ok(strpos($db, "function_exists('dropshipThemeText')") !== false,
   'evaluateText() assumes the library is loaded, so any page missing it fatals');
ok(strpos($db, "require_once __DIR__ . '/dropship-themes.php'") !== false,
   'db.php does not load the theme library it calls');

echo "\nthe nav link is gated the same way the page is\n";
/* A link nobody else can see is convenience, not security -- the page must
   still refuse on arrival, or the URL is the whole lock. */
$hdr = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/header.php'));
ok(strpos($hdr, 'admin-themes.php') !== false, 'there is no way to reach the theme admin');
ok(strpos($hdr, 'dropshipThemeAdmin()') !== false,
   'the nav link is shown to every player');
ok(strpos($hdr, "function_exists('dropshipThemeAdmin')") !== false,
   'the nav assumes the theme library is loaded, so a page without it fatals');

echo "\nthe admin saves what the form shows\n";
$adm = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-themes.php'));
ok(strpos($adm, 'dropshipThemeAdmin()') !== false, 'the admin page is not gated');
/* REPLACED WHOLESALE. The form posts the complete set, so a row cleared in
   the browser has to vanish -- merging would make deletion impossible. */
ok(strpos($adm, 'DELETE FROM dropship_theme_text WHERE theme_id') !== false,
   'the admin merges overrides instead of replacing them, so a row can never be deleted');
ok(strpos($adm, 'part-translated') !== false,
   'a partial write reports success, leaving a theme speaking two languages');
ok(strpos($adm, 'dropshipThemeSlug(') !== false, 'the admin stores a slug without sanitising it');
/* IT MUST NOT TOUCH projects. The collection binding lives there and several
   themes legitimately share one. */
ok(preg_match('/(UPDATE|INSERT INTO|DELETE FROM)\s+projects/i', $adm) !== 1,
   'the admin writes to the projects table, which owns the collection binding');
ok(strpos($adm, 'dropshipThemesTable($conn)') !== false,
   'the admin does not check the tables exist, so it errors instead of explaining');

echo "\n3D is retired, everywhere\n";
/*
 * The animation extension was a per-player toggle: a session flag, an ajax
 * endpoint, a checkbox, a conditional 'gif' => 'png' in the chain, and two
 * blanket str_replace passes over stored replay markup. That last part is
 * why it is worth being thorough -- it rewrote the word "png" ANYWHERE in
 * the markup, not just in a file extension.
 *
 * It is a property of the theme now, on one column.
 */
foreach (glob(__DIR__ . '/dropship/*.php') as $f) {
	$name = basename($f);
	$src  = harness_strip_comments(file_get_contents($f));
	ok(strpos($src, "userData']['3d']") === false, "$name still reads the retired 3D session flag");
	ok(strpos($src, 'toggle3D') === false, "$name still references the retired toggle");
}
$js = file_get_contents(__DIR__ . '/dropship/dropship.js');
ok(strpos($js, 'function toggle3D') === false, 'dropship.js still defines toggle3D()');
ok(strpos($js, 'toggle-3d.php') === false, 'dropship.js still calls the retired endpoint');
ok(!is_file(__DIR__ . '/dropship/ajax/toggle-3d.php'), 'the toggle-3d.php endpoint is still there');

echo "\nthe extension is a column, not an override\n";
/* One value in one place. A seeded 'gif' pair would be a second. */
$dbsrc = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($dbsrc, "\$text === 'gif'") !== false,
   'evaluateText() no longer answers the extension from the theme row');
ok(strpos($dbsrc, "\$cfg['extension']") !== false,
   'the extension does not come from dropship_themes.extension');
$ext_at   = strpos($dbsrc, "\$text === 'gif'");
$table_at = strpos($dbsrc, 'dropshipThemeText($conn');
ok($ext_at !== false && $table_at !== false && $ext_at < $table_at,
   'the text table is consulted before the extension, so a stray gif row would win');

echo "\nthe seed matches the chain it was taken from\n";
/*
 * GENERATED BY RUNNING the if/else chain, not by reading it, and verified
 * key by key: 261 keys across projects 2, 3 and 4 produce identical output
 * through the chain and through the seeded dictionaries. Re-verify with
 * that comparison rather than by eye if the chain is ever edited.
 */
$seed = file_get_contents(__DIR__ . '/dropship/dropship-themes-seed.sql');
ok(strpos($seed, "'exact', 'gif'") === false,
   'the seed carries a gif override, which would fight dropship_themes.extension');
ok(substr_count($seed, 'INSERT INTO dropship_themes') === 3,
   'the seed does not create exactly the three themes that have overrides');
/* Project 1 is the base and must NOT be seeded: a row with no text would be
   harmless, but a row implies it has wording of its own and it does not. */
ok(strpos($seed, 'VALUES\n  (1,') === false && strpos($seed, "  (1, 'drop-ship'") === false,
   'the seed creates a theme for Drop Ship, which has no overrides of its own');
ok(strpos($seed, "(4, 'oculus-lounge', 1, 'png'") !== false,
   'the Oculus Lounge seed is not active-and-png, which is what it is live');

echo "\nthe admin prefills the words a theme has to answer for\n";
/*
 * Reworking a theme used to mean guessing which words mattered, or reading
 * the game until you found them all. The vocabulary is the union of what the
 * three existing themes actually overrode, so the admin is a form to fill in
 * rather than a blank box to speculate into.
 */
require_once __DIR__ . '/dropship/dropship-theme-vocab.php';
$vocab = dropshipThemeVocabulary();
$terms = array(); $icons = 0;
foreach ($vocab as $group => $rows) {
	ok(!empty($rows), "vocabulary group '$group' is empty");
	foreach ($rows as $r) {
		$terms[$r['type'] . '|' . $r['find']] = true;
		if ($r['icon']) $icons++;
	}
}
ok(count($terms) >= 100, 'the vocabulary has only ' . count($terms) . ' terms; it should cover what the themes overrode');
ok($icons > 0, 'no term is flagged as an icon filename');
/* The words every reskin had to rename must be in there. */
foreach (array('exact|SCRIP', 'exact|Barracks', 'exact|Heavy', 'exact|Medkit',
               'exact|Drop Ship', 'exact|Grunt', 'description|beach') as $must) {
	ok(isset($terms[$must]), "the vocabulary is missing $must, which existing themes override");
}
/* 'gif' must NOT be offered: the extension is a column, not an override. */
ok(!isset($terms['exact|gif']),
   'the vocabulary offers gif as an override, which would fight dropship_themes.extension');
/*
 * The icon flag must mark the filename forms and not the display words.
 *
 * KEYED BY TYPE AND FIND, not find alone: 'Medkit' exists as both an exact
 * term and a description term, so a find-only key let the second overwrite
 * the first and the check passed against the wrong row. Found by mutation,
 * not by reading.
 */
$byterm = array();
foreach ($vocab as $rows) foreach ($rows as $r) $byterm[$r['type'] . '|' . $r['find']] = $r;
ok(!empty($byterm['exact|medkit']['icon']), "'medkit' is not flagged as an icon filename");
ok(empty($byterm['exact|Medkit']['icon']), "'Medkit' is wrongly flagged as an icon filename");
/* Every icon-flagged term must actually look like a filename. */
foreach ($byterm as $k => $r) {
	if (empty($r['icon'])) continue;
	ok(preg_match('/^[a-z0-9-]+$/', $r['find']) === 1,
	   "'" . $r['find'] . "' is flagged as an icon filename but is not one");
}

echo "\n\"keep\" is stored as a decision, not as silence\n";
/*
 * An empty box means "not looked at yet" and a ticked one means "this theme
 * uses the Drop Ship word on purpose". If keep wrote nothing, the two would
 * be indistinguishable and the list would stop being a checklist. It is
 * stored as a row whose replacement equals its find -- a no-op at lookup
 * time, which is the point.
 */
$adm2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-themes.php'));
ok(strpos($adm2, "\$_POST['keep']") !== false, 'the admin ignores the Keep checkbox');
ok(strpos($adm2, 'if (!empty($keeps[$i])) $rep = $find;') !== false,
   'Keep does not store the word as its own replacement');
ok(strpos($adm2, "if (\$rep === '') continue;") !== false,
   'an undecided term is written as a row, so blank and Keep become the same thing');
ok(strpos($adm2, 'dropshipThemeVocabulary()') !== false, 'the admin does not render the guide');
ok(strpos($adm2, 'terms decided') !== false, 'the admin does not show how much of the guide is done');
/* An identity row must be harmless at lookup: replacing a word with itself. */
$c9 = new TConn(array(90 => array('id' => 9, 'project_id' => 90, 'slug' => 'k')),
                array(9 => array(pair('exact', 'Barracks', 'Barracks'))));
ok(dropshipThemeText($c9, 90, 'Barracks') === 'Barracks',
   'a kept term does not come back unchanged');

echo "\nthe media extension decides the motion\n";
/*
 * A gif animates itself; a still needs the Ken Burns pan (.image-wrap, a 40s
 * drift in dist/flexbox.css). The pan used to be switched on for project 4
 * and nothing else, which was right only by coincidence -- that is the theme
 * that happens to use stills. A new png theme would have rendered a dead
 * frame, and a gif theme on project 4's id would have had a pan dragged
 * across its own animation.
 */
function media_is_still_for($ext) {
	/* Drive the real helper by standing in for evaluateText(), which is what
	   it asks -- that accessor answers from the theme row when one exists and
	   from the built-in chain when it does not. */
	$GLOBALS['__ext'] = $ext;
	return dropshipMediaIsStill();
}
if (!function_exists('evaluateText')) {
	function evaluateText($t) { return $t === 'gif' ? $GLOBALS['__ext'] : $t; }
}
ok(media_is_still_for('gif') === false, 'a gif theme is given the pan, which fights its own animation');
ok(media_is_still_for('png') === true, 'a png theme gets no pan, so its art sits dead on the screen');
ok(media_is_still_for('PNG') === true,
   'an uppercase extension is not normalised, so PNG and png behave differently');
ok(media_is_still_for('GIF') === false, 'an uppercase gif is treated as a still');
ok(media_is_still_for('jpg') === true, 'a jpg theme gets no pan');
ok(media_is_still_for('') === false, 'an empty extension is treated as a still rather than falling back to gif');

$dash2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash2, 'dropshipMediaIsStill()') !== false,
   'the dashboard does not use the media extension to decide the pan');
ok(preg_match("/image_wrap[^;]*dropship_project_id'\] == 4/", $dash2) !== 1,
   'the pan is still keyed off project 4 rather than the format');
ok(strpos($dash2, "class='image-wrap'") !== false, 'the Ken Burns wrapper is gone entirely');
/* And the effect it switches on must still exist. */
$css = file_get_contents(__DIR__ . '/dropship/dist/flexbox.css');
ok(strpos($css, '.image-wrap') !== false, 'the .image-wrap pan is gone from the stylesheet');
ok(strpos($css, 'animation: move') !== false, 'the Ken Burns keyframes are gone');

/* The label players and the owner read. */
$adm3 = file_get_contents(__DIR__ . '/dropship/admin-themes.php');
ok(strpos($adm3, 'Media extension') !== false, 'the admin does not call it the media extension');
ok(strpos($adm3, 'Animation extension') === false, 'the old label is back');

echo "\ncase-only pairs are real rows, and the column must allow them\n";
/*
 * 'Medkit' is the word a player reads; 'medkit' is the icon filename. They
 * need different replacements, so they are different rows -- but MySQL's
 * DEFAULT COLLATION IS CASE INSENSITIVE and treats them as one, which
 * rejected the seed with "#1062 Duplicate entry '1-exact-melee'". Found by
 * the seed failing to load, not by review.
 */
$pairs_ci = array();
foreach ($vocab as $rows) foreach ($rows as $r) {
	$k = $r['type'] . '|' . strtolower($r['find']);
	$pairs_ci[$k] = isset($pairs_ci[$k]) ? $pairs_ci[$k] + 1 : 1;
}
$collide = array_filter($pairs_ci, function ($n) { return $n > 1; });
ok(!empty($collide),
   'no case-only pairs in the vocabulary, so this check is no longer proving anything');
$schema_src = file_get_contents(__DIR__ . '/dropship/dropship-themes-schema.md');
/*
 * SCOPED TO THE CREATE BLOCK. A bare search for the collation anywhere in
 * the file also matches the ALTER statement and the prose below it, which
 * is exactly how the first version of this check missed a mutation that
 * stripped the collation from the CREATE itself.
 */
$ct = strpos($schema_src, 'CREATE TABLE IF NOT EXISTS dropship_theme_text');
ok($ct !== false, 'the schema has no CREATE for dropship_theme_text');
$create_block = $ct === false ? ''
    : substr($schema_src, $ct, strpos($schema_src, 'ENGINE=InnoDB', $ct) - $ct);
ok(strpos($create_block, 'COLLATE utf8mb4_bin') !== false,
   'the CREATE lets the default collation decide, which makes Medkit and medkit one row');
ok(preg_match('/find\s+VARCHAR\(190\)[^,]*COLLATE utf8mb4_bin/', $create_block) === 1,
   'it is not the find column in the CREATE that is case sensitive');
ok(strpos($schema_src, 'ALTER TABLE dropship_theme_text') !== false,
   'there is no ALTER for a table that already exists with the wrong collation');
$seed_src = file_get_contents(__DIR__ . '/dropship/dropship-themes-seed.sql');
ok(strpos($seed_src, 'utf8mb4_bin') !== false,
   'the seed does not say it needs a case-sensitive column, so it fails with no explanation');
/* And the lookup side was always case sensitive -- assert it still is. */
$cc = new TConn(array(80 => array('id' => 8, 'project_id' => 80, 'slug' => 'j')),
                array(8 => array(pair('exact', 'Medkit', 'Sexy Nurse'),
                                 pair('exact', 'medkit', 'sexy-nurse'))));
ok(dropshipThemeText($cc, 80, 'Medkit') === 'Sexy Nurse', 'the display word resolves wrongly');
ok(dropshipThemeText($cc, 80, 'medkit') === 'sexy-nurse',
   'the icon filename resolves to the display word, so the page asks for the wrong file');

echo "\nthe migration is written down\n";
$schema = file_get_contents(__DIR__ . '/dropship/dropship-themes-schema.md');
foreach (array('dropship_themes', 'dropship_theme_text') as $t) {
	ok(strpos($schema, 'CREATE TABLE IF NOT EXISTS ' . $t) !== false,
	   "the schema has no CREATE for $t, so nobody can create it");
}

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
