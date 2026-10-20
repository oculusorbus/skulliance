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

echo "\nthe migration is written down\n";
$schema = file_get_contents(__DIR__ . '/dropship/dropship-themes-schema.md');
foreach (array('dropship_themes', 'dropship_theme_text') as $t) {
	ok(strpos($schema, 'CREATE TABLE IF NOT EXISTS ' . $t) !== false,
	   "the schema has no CREATE for $t, so nobody can create it");
}

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall good\n");
exit($fail ? 1 : 0);
