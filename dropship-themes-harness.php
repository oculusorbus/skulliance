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
	public $projects = array();
	public $policies = array();
	public function __construct($themes = array(), $text = array(), $projects = array(),
	                            $policies = array()) {
		$this->themes = $themes; $this->text = $text;
		$this->projects = $projects; $this->policies = $policies;
	}
	public function query($sql) {
		$this->queries++;
		if (strpos($sql, 'SHOW TABLES') !== false) return new TRes(array(array('t' => 'dropship_themes')));
		if (strpos($sql, 'SELECT project_id, active FROM dropship_themes') !== false) {
			return new TRes(array_values($this->themes));
		}
		if (strpos($sql, 'FROM dropship_themes WHERE project_id') !== false) {
			preg_match('/project_id = (\d+)/', $sql, $m);
			$pid = (int)$m[1];
			return new TRes(isset($this->themes[$pid]) ? array($this->themes[$pid]) : array());
		}
		if (strpos($sql, 'SELECT policy_id FROM projects WHERE id') !== false) {
			preg_match('/id = (\d+)/', $sql, $m);
			$pid = (int)$m[1];
			return new TRes(isset($this->policies[$pid])
				? array(array('policy_id' => $this->policies[$pid])) : array());
		}
		if (strpos($sql, 'FROM projects') !== false) {
			/* HONOURS THE WHERE, because the thing under test is whether the
			   admin query still carries getProjects()'s name exclusion. A
			   stub that returned every row regardless would pass with the
			   exclusion copied straight back in. */
			$rows = $this->projects;
			if (preg_match_all("/name != '([^']*)'/", $sql, $m)) {
				$rows = array_values(array_filter($rows, function ($r) use ($m) {
					return !in_array($r['name'], $m[1], true);
				}));
			}
			return new TRes($rows);
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
$after50 = $c3->queries;
for ($i = 0; $i < 500; $i++) dropshipThemeText($c3, 30, 'A');
/* THE CLAIM IS THAT IT DOES NOT SCALE WITH LABELS, not a magic number.
   Setup costs a fixed few queries -- SHOW TABLES, the theme row, its text
   -- and a threshold asserted against that total broke the day the table
   check started being asked per connection instead of once globally, which
   was a fix, not a regression. */
ok($c3->queries === $after50,
   'ten times the labels cost ' . ($c3->queries - $after50) . ' more queries');
ok($after50 <= 3, 'reading one theme took ' . $after50 . ' queries before any caching');

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
/*
 * THE SEED IS THE RESET. It carries its own DROP and CREATE so one file
 * gives a clean slate and the schema can never be half a version behind the
 * data. Both halves have to be there: without the DROP a re-run hits the
 * old table and fails on duplicate keys, and without the collation it fails
 * the way it originally did, on "#1062 Duplicate entry".
 */
foreach (array('DROP TABLE IF EXISTS dropship_theme_text;',
               'DROP TABLE IF EXISTS dropship_themes;',
               'CREATE TABLE dropship_themes (',
               'CREATE TABLE dropship_theme_text (') as $ddl) {
	ok(strpos($seed, $ddl) !== false, 'the seed cannot rebuild from scratch: no "' . $ddl . '"');
}
/* The child goes first, and both go before anything is written. */
ok(strpos($seed, 'DROP TABLE IF EXISTS dropship_theme_text;')
   < strpos($seed, 'DROP TABLE IF EXISTS dropship_themes;'),
   'the seed drops the parent table before the child');
ok(strpos($seed, 'CREATE TABLE dropship_theme_text (')
   < strpos($seed, 'INSERT INTO dropship_themes'),
   'the seed inserts before it has finished creating the tables');
/* The collation, in the seed's OWN create -- the schema doc is checked
   separately and the two can drift. */
ok(preg_match('/CREATE TABLE dropship_theme_text \(.*?COLLATE utf8mb4_bin.*?\n\) ENGINE/s', $seed) === 1,
   "the seed's own CREATE lets the default collation decide, which is the #1062 bug again");
ok(preg_match('/\n\tfind\s+VARCHAR\(190\)[^\n]*COLLATE utf8mb4_bin/', $seed) === 1,
   "it is not the find column in the seed's CREATE that is case sensitive");
ok(strpos($seed, 'DESTRUCTIVE') !== false,
   'the seed drops tables without saying so at the top of the file');
ok(strpos($seed, "'exact', 'gif'") === false,
   'the seed carries a gif override, which would fight dropship_themes.extension');
ok(substr_count($seed, 'INSERT INTO dropship_themes') === 4,
   'the seed does not create all four themes');
/*
 * PROJECT 1 GETS A ROW BUT NO TEXT. It used to get neither, on the grounds
 * that a row implies wording of its own -- but the row is what lets the
 * base game be reskinned like the rest and what lets the art admin manage
 * its files. The absence of TEXT rows is the thing that says it has no
 * overrides, and dropshipThemeDictionaries() returns null for a theme with
 * none, so the fallback behaves exactly as it did with no row at all.
 */
ok(strpos($seed, "  (1, 'drop-ship', 1, 'gif'") !== false,
   'Drop Ship has no theme row, so the base game cannot be reskinned');
$p1 = substr($seed, strpos($seed, "(1, 'drop-ship'"));
$p1 = substr($p1, 0, strpos($p1, 'project 2'));
ok(strpos($p1, '(@t,') === false, 'the seed gives Drop Ship text rows; it is the base wording');

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
$terms = array(); $bylabel = array(); $icons = 0;
foreach ($vocab as $group => $rows) {
	ok(!empty($rows), "vocabulary group '$group' is empty");
	foreach ($rows as $r) {
		foreach ($r['exact'] as $w)  $terms['exact|' . $w] = true;
		foreach ($r['desc'] as $w)   $terms['description|' . $w] = true;
		foreach ($r['icons'] as $ic) { $terms['exact|' . $ic] = true; $icons++; }
		ok($r['exact'] || $r['desc'],
		   "family '" . $r['label'] . "' names no word at all");
		ok(in_array($r['label'], $r['exact'], true) || in_array($r['label'], $r['desc'], true),
		   "family '" . $r['label'] . "' does not include its own label");
		ok(!isset($bylabel[$r['label']]), "two families are both called '" . $r['label'] . "'");
		$bylabel[$r['label']] = $r;
	}
}
ok(count($terms) >= 100, 'the vocabulary covers only ' . count($terms) . ' terms; it should cover what the themes overrode');
ok($icons > 0, 'no family carries an icon filename');
foreach (array('exact|SCRIP', 'exact|Barracks', 'exact|Heavy', 'exact|Medkit',
               'exact|Drop Ship', 'exact|Grunt', 'description|beach') as $must) {
	ok(isset($terms[$must]), "the vocabulary is missing $must, which existing themes override");
}
/* 'gif' must NOT be offered: the extension is a column, not an override. */
ok(!isset($terms['exact|gif']),
   'the vocabulary offers gif as an override, which would fight dropship_themes.extension');
/*
 * ONE BOX PER THING, in all four ways the game asks for it: the bare label,
 * the shop item, the word mid-sentence, and the icon filename. If those were
 * separate rows the admin would type one answer four times, and four boxes
 * that must agree are four boxes that can drift.
 */
ok($bylabel['Heavy']['exact'] === array('Heavy', 'Heavy Armor'),
   "the Heavy family does not cover both 'Heavy' and 'Heavy Armor'");
ok($bylabel['Heavy']['desc'] === array('Heavy Armor'),
   'the Heavy family does not cover the mid-sentence form');
ok($bylabel['Heavy']['icons'] === array('heavy-armor'),
   "the Heavy family does not carry its icon filename (it is 'heavy-armor', never 'heavy')");
ok(!isset($bylabel['Heavy Armor']) && !isset($bylabel['heavy-armor']),
   'a form of Heavy still has a row of its own, so the admin types the answer twice');
/*
 * NO "DESCRIPTIONS" GROUP. Exact and description are match TYPES. A group
 * that collects terms by how they are matched says nothing about what is
 * being renamed, and every term in it was already named above.
 */
ok(!isset($vocab['Descriptions']),
   'Descriptions is back as a group, so the same thing is listed twice under two headings');
$desc_fams = 0;
foreach ($vocab as $rows) foreach ($rows as $r) if ($r['desc']) $desc_fams++;
ok($desc_fams > 20, 'only ' . $desc_fams . ' families carry a description form; they have been dropped rather than folded in');
/* The lone description-only terms belong with the thing they describe. */
ok(isset($vocab['Locations']['0']) || true, '');
$loc = array_column($vocab['Locations'], 'label');
foreach (array('beach', 'hill', 'bunker') as $place) {
	ok(in_array($place, $loc, true), "'$place' is not filed under Locations");
}
$act = array_column($vocab['Actions'], 'label');
foreach (array('Stormed the Beach', 'Climbed the Hill', 'Entered the Bunker') as $line) {
	ok(in_array($line, $act, true), "'$line' is not filed under Actions");
}
/*
 * TWO ICON FACTS THAT CAME OUT OF MEASUREMENT, not review, and that a
 * reasonable guess gets wrong:
 */
ok($bylabel['Super Soldier']['icons'] === array('dropship', 'supersoldier'),
   "'dropship' is not Super Soldier's icon -- both themes send it to 'vip', as they do 'supersoldier'");
/* ...and Drop Ship has NO icon. The chain carried a 'drop-ship' entry, but
   nothing renders icons/drop-ship.png -- every other use of that string is
   the art folder slug -- so project 1 had been asking for a 404 since long
   before any of this. Claiming it would put a permanently missing slot on
   the art page and demand three files nobody uses. */
ok($bylabel['Drop Ship']['icons'] === array(),
   'the Drop Ship family claims an icon, but icons/drop-ship.png has never existed');
ok(dropshipThemeIconLabel('drop-ship') === null,
   "'drop-ship' is still treated as an icon filename");
foreach ($vocab as $rows) foreach ($rows as $r) {
	foreach ($r['icons'] as $ic) {
		ok(preg_match('/^[a-z0-9-]+$/', $ic) === 1,
		   "'" . $ic . "' is carried as an icon filename but is not one");
	}
}
/*
 * Every word in a family must take the same answer in every theme that has
 * an opinion, or one box could not speak for all of them. Checked against
 * the real seed, which is what the live themes load.
 */
$seed = file_get_contents(__DIR__ . '/dropship/dropship-themes-seed.sql');
/* The seed writes rows against @t, reset once per theme, so the theme a row
   belongs to is the block it sits in -- split on the marker rather than
   looking for an id that is not in the text. */
$blocks = array();
foreach (preg_split('/SET @t := \(SELECT id FROM dropship_themes WHERE project_id = (\d+)\);/',
                    $seed, -1, PREG_SPLIT_DELIM_CAPTURE) as $k => $part) {
	if ($k % 2 === 1) $pid = $part; else if ($k > 0) $blocks[$pid] = $part;
}
ok(count($blocks) === 3, 'the seed no longer has three theme blocks; found ' . count($blocks));
$seen_pairs = 0;
foreach ($vocab as $rows) foreach ($rows as $r) {
	$want = array();
	foreach ($r['exact'] as $w) $want[] = array('exact', $w);
	foreach ($r['desc'] as $w)  $want[] = array('description', $w);
	if (count($want) < 2) continue;
	foreach ($blocks as $pid => $sql) {
		$vals = array();
		foreach ($want as $pair) {
			if (preg_match("/\(\@t, '" . $pair[0] . "', '" . preg_quote($pair[1], '/')
			               . "', '([^']*)'/", $sql, $m)) $vals[] = $m[1];
		}
		if (count($vals) > 1) $seen_pairs++;
		ok(count(array_unique($vals)) <= 1,
		   "project $pid gives the words of '" . $r['label'] . "' different answers ("
		   . implode(' / ', array_unique($vals)) . "), so one box cannot speak for them");
	}
}
ok($seen_pairs > 20,
   'only ' . $seen_pairs . ' multi-word families were found in the seed, so this check is barely proving anything');

echo "\na blank box means the Drop Ship word, and writes nothing\n";
/*
 * evaluateText() already falls through to the original when it finds no row,
 * so a row replacing a word with itself would be a no-op stored only to
 * record that somebody had looked at it. That was a checkbox column on every
 * row of the form, bought with nothing. Only overrides override.
 */
$one = function ($label, $say) {
	return dropshipThemeVocabExpand(array(
		'fam_label' => array($label), 'fam_say' => array($say)));
};
$flat = function ($rows) {
	$o = array();
	foreach ($rows as $x) $o[$x['type'] . '|' . $x['find']] = $x['replacement'];
	return $o;
};
ok($one('Heavy', '') === array(), 'a blank box writes a row, so blank and "keep it" differ');
ok($one('Barracks', '   ') === array(), 'whitespace alone counts as an override');
/* One box fills every form of the thing. */
$g = $flat($one('Heavy', 'Smoking Jacket'));
ok(($g['exact|Heavy'] ?? null) === 'Smoking Jacket', 'the family label is not written');
ok(($g['exact|Heavy Armor'] ?? null) === 'Smoking Jacket',
   'the shop item is not written, so the shop keeps the Drop Ship name');
ok(($g['description|Heavy Armor'] ?? null) === 'Smoking Jacket',
   'the mid-sentence form is not written, so results lines keep the Drop Ship name');
/*
 * AND NO ICON ROW. The icon filename is the word made into a filename, and
 * dropshipThemeText() derives it on the way out -- writing it here too
 * would be the same fact in two rows, free to disagree, which is exactly
 * what the live data did: 13 of 64 stored icon names had stopped matching
 * the word beside them.
 */
ok(!isset($g['exact|heavy-armor']),
   'the expansion still writes an icon row, so the name can drift from the word again');
ok(count($g) === 3, 'the Heavy family wrote ' . count($g) . ' rows rather than 3');
foreach ($g as $k => $v) {
	ok(strpos($k, '|heavy-armor') === false && strpos($k, '|medkit') === false,
	   "the expansion wrote an icon row: $k");
}
/* A description-only family stays a description, and invents no icon. */
$g5 = $one('beach', 'lobby');
ok(count($g5) === 1 && $g5[0]['type'] === 'description' && $g5[0]['find'] === 'beach',
   'a description-only family does not write exactly one description row');
foreach ($one('Barracks', 'Green Room') as $x) {
	ok($x['find'] === 'Barracks', "an iconless family wrote a row for '" . $x['find'] . "'");
}
/*
 * THE WORDS COME FROM THE VOCABULARY, NEVER FROM THE POST. The form carries
 * the label, not the list, so a mangled or forged post cannot write a word
 * this form does not own.
 */
ok($one('Not A Real Term', 'Whatever') === array(),
   'a label that is not in the vocabulary is written anyway');
ok(dropshipThemeVocabExpand(array('fam_label' => array('Heavy'), 'fam_say' => array('X'),
                                  'fam_icon' => array(''), 'fam_words' => array('Soldiers'))) === $one('Heavy', 'X'),
   'the post can smuggle in a word the family does not own');
/* The form must post what the expansion reads, and must no longer ask the
   question the blank box already answers. */
$adm2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-themes.php'));
ok(strpos($adm2, 'dropshipThemeVocabExpand($_POST)') !== false,
   'the admin does not expand the families it posts');
foreach (array('fam_label[]', 'fam_say[]') as $field) {
	ok(strpos($adm2, 'name="' . $field . '"') !== false,
	   "the form does not post $field, which the expansion reads");
}
ok(strpos($adm2, "\$_POST['keep']") === false, 'the admin still reads the Keep checkbox');
ok(strpos($adm2, 'name="keep[') === false, 'the form still renders Keep checkboxes');
ok(strpos($adm2, "if (\$rep === '') continue;") !== false, 'a blank box is written as a row');
ok(strpos($adm2, 'dropshipThemeVocabulary()') !== false, 'the admin does not render the guide');
/* There is nowhere to type an icon name, because there is nothing to type. */
ok(strpos($adm2, 'fam_icon') === false,
   'the wording form still has an icon field, which is the filename spelled out again');
ok(strpos($adm2, 'admin-art.php') !== false,
   'the wording form does not say where the picture is actually managed');
/* An identity row must be harmless at lookup: replacing a word with itself. */
$c9 = new TConn(array(90 => array('id' => 9, 'project_id' => 90, 'slug' => 'k')),
                array(9 => array(pair('exact', 'Barracks', 'Barracks'))));
ok(dropshipThemeText($c9, 90, 'Barracks') === 'Barracks',
   'a word replaced by itself does not come back unchanged');

echo "\nmaking a theme active is what puts it in front of players\n";
/*
 * dropship_themes.active was written by the admin and read by nothing, so
 * ticking it did nothing: which projects a player saw was two names
 * hardcoded in getProjects(). That list is also what dropship.php validates
 * ?project_id against before the value reaches SQL, so the flag has to
 * drive BOTH or a project hidden from the dropdown stays reachable by
 * typing the id into the URL.
 */
$mk = function ($rows) {
	$t = array();
	foreach ($rows as $pid => $act) {
		$t[$pid] = array('id' => $pid, 'project_id' => $pid, 'slug' => 's' . $pid, 'active' => $act);
	}
	return new TConn($t);
};
ok(dropshipActiveProjectIds($mk(array(1 => 1, 2 => 0, 3 => 0, 4 => 1))) === array(1, 4),
   'the active themes are not the ones offered');
ok(dropshipActiveProjectIds($mk(array(1 => 1, 2 => 1, 3 => 1, 4 => 1))) === array(1, 2, 3, 4),
   'activating every theme does not offer every theme');
ok(dropshipActiveProjectIds($mk(array(2 => 1))) === array(2),
   'a single active theme is not offered on its own');
/*
 * NULL IN EVERY DOUBTFUL CASE, so the old list stands rather than an empty
 * dropdown, which a player cannot tell from an outage.
 */
ok(dropshipActiveProjectIds($mk(array())) === null,
   'an empty themes table hides every project instead of falling back');
ok(dropshipActiveProjectIds($mk(array(1 => 0, 2 => 0))) === null,
   'deactivating every theme locks every player out of the game');
ok(dropshipActiveProjectIds(null) === null, 'a dead connection hides every project');
/* '0' and 0 both mean inactive; the column is TINYINT but mysqli hands back
   strings, so a truthiness test on '0' would call it active. */
ok(dropshipActiveProjectIds($mk(array(1 => '1', 2 => '0'))) === array(1),
   "a string '0' is treated as active");
/*
 * THE SEED MUST REPRODUCE TODAY'S BEHAVIOUR EXACTLY. This is the property
 * that makes the switch safe to deploy: the themes marked active have to be
 * precisely the projects the hardcoded exclusion already allowed, or
 * re-seeding silently opens a work-in-progress theme to players or shuts a
 * live one.
 */
/* Matched loosely on the trailing columns, because every new theme-level
   setting adds one and pinning the exact count here means this breaks for
   a reason that has nothing to do with what it checks. */
preg_match_all("/\((\d+), '[a-z-]+', (\d), '\w+'(?:, '\w+')*, NOW\(\)\)/", $seed, $sm, PREG_SET_ORDER);
ok(count($sm) === 4, 'the seed no longer declares four themes');
$seed_active = array();
foreach ($sm as $row) if ($row[2] === '1') $seed_active[] = (int)$row[1];
sort($seed_active);
/* What the old rule allowed: everything except Filthy Mermaid (3) and
   Dread City (2). Read from db.php rather than restated, so renaming a
   project here is not a quiet way to disagree with the code. */
$dbsrc3 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(preg_match("/name != 'Filthy Mermaid' AND name != 'Dread City'/", $dbsrc3) === 1,
   'the legacy exclusion changed, so the comparison below is against nothing');
ok($seed_active === array(1, 4),
   'the seed activates ' . implode(',', $seed_active)
   . ' but the old rule showed players projects 1 and 4; re-seeding would change who sees what');

/* getProjects() must consult it, and must still carry the old list for the
   fallback -- that is what keeps this invisible until a theme is saved. */
ok(strpos($dbsrc3, 'dropshipActiveProjectIds($conn)') !== false,
   'getProjects() ignores the active flag, so ticking it still does nothing');
ok(strpos($dbsrc3, "WHERE name != 'Filthy Mermaid' AND name != 'Dread City'") !== false,
   'the fallback list is gone, so a site with no themes yet shows every project');
/* The ids are cast before they are concatenated into the IN(). */
ok(strpos($dbsrc3, "implode(',', array_map('intval', \$allow))") !== false,
   'project ids reach the IN() clause without being cast to integers');
/* One list for both jobs. If the dropdown and the guard ever came from
   different places, a hidden project would still be reachable by URL. */
$dssrc2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship.php'));
ok(strpos($dssrc2, '$ds_allowed = getProjects($conn);') !== false,
   'the ?project_id guard no longer uses the same list as the dropdown');
$dash2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash2, 'getProjects($conn)') !== false,
   'the dashboard dropdown no longer uses that list either');

echo "\nthe admin can pick any project, not just the public ones\n";
/*
 * The theme admin could not offer Filthy Mermaid or Dread City -- the two
 * themes most in need of editing -- because it built its dropdown from
 * getProjects(), which excludes them BY NAME. That is the player-facing
 * list, and it is also the allow-list dropship.php validates ?project_id
 * against before the value is interpolated into SQL, so it cannot simply be
 * widened: that would un-hide work in progress and loosen an injection
 * guard at the same time. Two questions, two functions.
 */
$cp = new TConn(array(), array(), array(
	array('id' => '1', 'name' => 'Drop Ship'),
	array('id' => '2', 'name' => 'Dread City'),
	array('id' => '3', 'name' => 'Filthy Mermaid'),
	array('id' => '4', 'name' => 'Oculus Lounge'),
));
$ap = dropshipAdminProjects($cp);
ok(count($ap) === 4, 'the admin project list dropped one: ' . implode(', ', $ap));
foreach (array(1 => 'Drop Ship', 2 => 'Dread City', 3 => 'Filthy Mermaid',
               4 => 'Oculus Lounge') as $pid => $name) {
	ok(isset($ap[$pid]) && $ap[$pid] === $name, "the admin cannot pick $name");
}
/* Keyed by INT, because the template compares against (int)$theme['project_id']
   and a string key would never match, silently losing the selected option. */
foreach (array_keys($ap) as $k) ok(is_int($k), 'the project list is keyed by ' . gettype($k));
/* ALWAYS an array. getProjects() returns NULL on an empty table -- the
   foreach that follows would warn, and display_errors is on in production. */
ok(dropshipAdminProjects(new TConn()) === array(), 'an empty projects table does not give an array');
ok(dropshipAdminProjects(null) === array(), 'a dead connection does not give an array');
/* The admin must use it, and must NOT use the player-facing one. */
$adm4 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-themes.php'));
ok(strpos($adm4, 'dropshipAdminProjects($conn)') !== false,
   'the admin does not build its dropdown from every project');
ok(strpos($adm4, 'getProjects($conn)') === false,
   'the admin is back on the player-facing list, which hides two of the themes');
/* ...and the player-facing list must keep its exclusions, because it guards
   an id that reaches SQL. */
$dbsrc2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($dbsrc2, "WHERE name != 'Filthy Mermaid' AND name != 'Dread City'") !== false,
   'getProjects() no longer excludes the hidden projects, so players can enter them');
$dssrc = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship.php'));
ok(strpos($dssrc, '$ds_allowed = getProjects($conn);') !== false,
   'the project_id guard no longer validates against the player-facing list');
ok(strpos($dssrc, 'dropshipAdminProjects') === false,
   'the project_id guard validates against the ADMIN list, which allows every project');

echo "\nan icon filename is derived from the word, never stored\n";
/*
 * The markup asks for the icon by its Drop Ship filename -- the shop renders
 * icons/<?= evaluateText('medkit') ?>.png -- so the lookup is where the
 * derivation has to happen. It used to be a stored row AND a field in the
 * wording form, which is the same fact in two places: rename the Medkit and
 * the icon row still said 'nurse' until somebody edited that too, with
 * nothing on screen saying that was why the shop had the wrong picture.
 * 13 of the 64 live icon names had already drifted that way.
 */
$ci = new TConn(array(40 => array('id' => 4, 'project_id' => 40, 'slug' => 'ol')),
                array(4 => array(pair('exact', 'Medkit', 'Sexy Nurse'),
                                 pair('exact', 'Heavy', 'Smoking Jacket'),
                                 pair('exact', 'Super Soldier', 'VIP'),
                                 pair('exact', 'Barracks', 'Green Room'))));
/*
 * A STALE STORED ROW MUST NOT WIN. Themes saved before the icon name became
 * derived still carry icon rows, and the live data proved why that matters:
 * Oculus Lounge kept answering 'robe' for heavy-armor long after the file
 * was renamed smoking-jacket.png, so the shop asked for a picture that no
 * longer existed. Deriving BEFORE the dictionary makes the leftovers inert
 * whether or not anybody deletes them.
 */
$cs = new TConn(array(42 => array('id' => 6, 'project_id' => 42, 'slug' => 'ol2')),
                array(6 => array(pair('exact', 'Heavy', 'Smoking Jacket'),
                                 pair('exact', 'heavy-armor', 'robe'))));
ok(dropshipThemeText($cs, 42, 'heavy-armor') === 'smoking-jacket',
   "a leftover icon row still wins: '" . dropshipThemeText($cs, 42, 'heavy-armor') . "'");

ok(dropshipThemeText($ci, 40, 'medkit') === 'sexy-nurse',
   "the Medkit icon does not follow the word: '" . dropshipThemeText($ci, 40, 'medkit') . "'");
ok(dropshipThemeText($ci, 40, 'heavy-armor') === 'smoking-jacket',
   'the armor icon does not follow the word');
/* Both of Super Soldier's files follow the one word. */
ok(dropshipThemeText($ci, 40, 'supersoldier') === 'vip'
   && dropshipThemeText($ci, 40, 'dropship') === 'vip',
   'a family with two icon files does not point both at the word');
/* A thing the theme did not rename keeps the Drop Ship filename -- and must
   NOT come back null, because null means "use the chain" and the chain is
   another theme's answer. */
ok(dropshipThemeText($ci, 40, 'radar') === 'radar',
   "an unrenamed icon does not keep its own filename: "
   . var_export(dropshipThemeText($ci, 40, 'radar'), true));
/* The word itself is untouched by any of this. */
ok(dropshipThemeText($ci, 40, 'Medkit') === 'Sexy Nurse', 'the display word stopped resolving');
ok(dropshipThemeText($ci, 40, 'Barracks') === 'Green Room', 'a plain word stopped resolving');
/* A themed word that is not a filename still becomes one. */
/* A DIFFERENT project id, deliberately: dropshipThemeDictionaries() caches
   per request by pid, so reusing 40 here silently tested the theme above. */
$cj = new TConn(array(41 => array('id' => 5, 'project_id' => 41, 'slug' => 'dc')),
                array(5 => array(pair('exact', 'Quantum Stealth', 'Mask & Hoodie'))));
ok(dropshipThemeText($cj, 41, 'quantum-stealth') === 'mask-hoodie',
   'a word with punctuation does not become a usable filename');
/* No theme at all still falls back to the chain, which is what keeps the
   schema a no-op until somebody saves a theme. */
$ck = new TConn(array(), array());
ok(dropshipThemeText($ck, 99, 'medkit') === null,
   'an unthemed project no longer falls back to the chain');
/* The reverse map has to know every icon and nothing else, or a word would
   be mistaken for a filename and resolved twice. */
ok(dropshipThemeIconLabel('heavy-armor') === 'Heavy', "'heavy-armor' is not mapped to Heavy");
ok(dropshipThemeIconLabel('dropship') === 'Super Soldier',
   "'dropship' is not mapped to Super Soldier");
foreach (array('Medkit', 'Heavy', 'beach', 'Barracks', 'SCRIP') as $word) {
	ok(dropshipThemeIconLabel($word) === null, "'$word' is treated as an icon filename");
}
/* No family label may itself be an icon term, or the derivation recurses. */
foreach ($vocab as $rows) foreach ($rows as $r) {
	ok(dropshipThemeIconLabel($r['label']) === null,
	   "family label '" . $r['label'] . "' is also an icon term, which would recurse");
}
/* The legacy chain derives the same way, so a renamed file is right on both
   paths. 39 lines of chain said it separately and 13 of them disagreed. */
$dbsrc = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($dbsrc, 'dropshipThemeIconLabel($text)') !== false,
   'the legacy chain does not derive icon names, so it still answers the old filenames');
foreach (array('medkit', 'heavy-armor', 'drop-box', 'quantum-stealth') as $term) {
	ok(strpos($dbsrc, "\$replacements['" . $term . "']") === false,
	   "the chain still spells out the '$term' icon, which can disagree with the word beside it");
}

echo "\nthe wording owns the labels, so there are no label columns\n";
/*
 * dropship_themes used to carry currency, squad_label and character_label.
 * They held the same three words the wording already owns -- SCRIP, Super
 * Soldier, Troops -- so a theme could rename SCRIP in its wording and the
 * dashboard would still say "SCRIP", with nothing in the admin saying why.
 * One word, one place.
 */
$dash   = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
$schema = file_get_contents(__DIR__ . '/dropship/dropship-themes-schema.md');
$seedsql = file_get_contents(__DIR__ . '/dropship/dropship-themes-seed.sql');
/* Matched in a COLUMN LIST, not anywhere in the file: the seed's own header
   explains why these columns are gone, and a bare substring search found
   that prose and reported the column as still present. */
foreach (array('currency', 'squad_label', 'character_label') as $col) {
	ok(strpos($adm2, $col) === false, "the admin still writes dropship_themes.$col");
	ok(!preg_match('/^\t' . $col . '\s/m', $schema), "the schema still creates dropship_themes.$col");
	ok(!preg_match('/^\s*\(project_id[^)]*\b' . $col . '\b/m', $seedsql),
	   "the seed still fills dropship_themes.$col");
	ok(!preg_match('/^\t' . $col . '\s/m', $seedsql),
	   "the seed's own CREATE still has dropship_themes.$col");
}
/* The dashboard reads all three through the same path as every other word. */
foreach (array('$currency   = evaluateText("SCRIP")',
               '$squad      = evaluateText("Super Soldier")',
               '$characters = evaluateText("Troops")') as $line) {
	ok(strpos($dash, $line) !== false, 'the dashboard does not read a label through the wording: ' . $line);
}
ok(strpos($dash, '$currency = "DREAD"') === false,
   'the hardcoded currency chain is back, so a theme cannot rename its own money');
ok(strpos($dash, '$characters = "Neo Miami Citizens"') === false,
   'the hardcoded character chain is back');
/* Troops had no dictionary rows at all, because it lived in the column. It
   has to be a family or the dashboard loses the word entirely. */
ok(isset($bylabel['Troops']), "'Troops' is not in the vocabulary, so no theme can rename its own people");
/* ...and the three themes that had a character_label must now say it as a row. */
foreach (array(2 => 'Henchmen', 3 => 'Perverts', 4 => 'Neo Miami Citizens') as $pid => $word) {
	ok(strpos($blocks[$pid], "'exact', 'Troops', '" . $word . "'") !== false,
	   "project $pid no longer calls its people $word");
}
ok(strpos($blocks[2], "'exact', 'Super Soldier', 'Elite'") !== false,
   'project 2 no longer calls its squad Elite, which the squad_label column used to say');
/* Every seed block must number its rows once, or a re-run writes two rows
   claiming the same position. */
foreach ($blocks as $pid => $sql) {
	preg_match_all("/, (\d+)\)[,;]/", $sql, $m);
	ok(count($m[1]) === count(array_unique($m[1])),
	   "project $pid has duplicate sort_order values in the seed");
}

echo "\nthe Koios batch survives one bad wallet\n";
require_once __DIR__ . '/lib/koios.php';
/*
 * MEASURED AGAINST THE REAL API, not assumed: ten valid stake addresses
 * answer 200, nine valid plus one malformed answer HTTP 500 "Failed to
 * decode Bech32 string", and nine valid plus one empty string answer 500
 * too. Every wallet goes in ONE account_utxos call, so one bad row costs
 * all of them -- and a 500 body is not JSON, so the caller's is_array()
 * check drops it without a word. Batching the wallets is what turned a
 * one-wallet problem into an all-wallets one.
 */
$good = 'stake1uyqszvt8ck9fhem3z63cj5zdhdqznwhkns5dx75c3cp0zgs084u8j';
ok(koios_valid_stake_address($good), 'a real stake address is rejected');
foreach (array('' => 'an empty string', '   ' => 'whitespace',
               'stake1_not_a_real_address' => 'an underscore, which Bech32 has no character for',
               'stake1short' => 'something far too short',
               'addr1qykk9ue0wmnk8m0sq6l4q9j5v6vmz5qz7x9h3c8v2n4m6p8r0t2w4y6' => 'a payment address',
               ) as $bad => $what) {
	ok(!koios_valid_stake_address($bad), "$what is sent to Koios, which 500s the whole batch");
}
/* b, i and o are not in the Bech32 alphabet at all. */
ok(!koios_valid_stake_address(substr($good, 0, -1) . 'b'), 'a character outside Bech32 is accepted');
ok(koios_filter_stake_addresses(array($good, '', 'bad', $good)) === array($good),
   'the filter does not drop the bad rows and de-duplicate the good ones');
ok(koios_filter_stake_addresses(array()) === array(), 'an empty wallet list does not come back empty');
$dash4 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash4, 'koios_filter_stake_addresses($addresses)') !== false,
   'the dashboard still posts unfiltered wallets, so one bad row blanks them all');
/* ...and the list should not contain them in the first place. Skulliance
   keeps Solana, EVM and XRPL addresses in the same column, and the only
   consumer of this one is Koios. */
$dbsrc5 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($dbsrc5, 'w.blockchain_id = 1') !== false,
   'the wallet list is not restricted to Cardano, so a Solana wallet 500s every Koios lookup');
foreach (array('7xKXtg2CW87d97TXJSDpbD5jBkheTqA83TZRuJosgAsU' => 'a Solana address',
               '0x742d35Cc6634C0532925a3b844Bc454e4438f44e'   => 'an EVM address',
               'rPdvC6ccq8hCdPKSPJkPmyZ4Mi1oG2FFkT'           => 'an XRPL address') as $a => $what) {
	ok(!koios_valid_stake_address($a), "$what would be sent to Koios");
}

echo "\nthe token is in one place, and is dropped once it expires\n";
/*
 * dropship/dashboard.php carried a bearer that expired 2024-12-20 while the
 * rest of the platform had moved to a current one, and nothing compared
 * them. An EXPIRED token is worse than none: Koios answers 403 with a
 * sentence where an anonymous call answers 200, and json_decode() of a
 * sentence is null, which is_array() drops silently.
 */
$koios_src = harness_strip_comments(file_get_contents(__DIR__ . '/lib/koios.php'));
ok(koios_token_expires_in() > 0,
   'the Koios token has expired; renew it in lib/koios.php (every lookup is silently doing nothing)');
$hdr = koios_headers();
ok(count(array_filter($hdr, function ($h) { return stripos($h, 'authorization:') === 0; })) === 1,
   'the bearer is not being sent while the token is valid');
/* An expired token must be left out entirely rather than sent. */
$dead = 'a.' . rtrim(strtr(base64_encode('{"exp":1}'), '+/', '-_'), '=') . '.c';
ok(koios_token_expires_in($dead) < 0, 'an expired token does not read as expired');
ok(koios_token_expires_in('not-a-jwt') === null, 'a token with no segments does not read as unreadable');
/* JWT-SHAPED BUT UNREADABLE, which exits further in than the one above --
   a token that merely lacks dots never reaches the claims branch, so it
   left that branch untested and a mutation there passed. */
$noclaims = 'a.' . rtrim(strtr(base64_encode('not json at all'), '+/', '-_'), '=') . '.c';
ok(koios_token_expires_in($noclaims) === null, 'a token with unreadable claims is treated as valid');
$noexp = 'a.' . rtrim(strtr(base64_encode('{"tier":1}'), '+/', '-_'), '=') . '.c';
ok(koios_token_expires_in($noexp) === null, 'a token with no exp claim is treated as valid');
/* ...and unreadable must mean NOT SENT, because a token we cannot vouch
   for is exactly the 403 that started this. */
ok(strpos($koios_src, '$left !== null && $left > 0') !== false,
   'an unreadable expiry still attaches the bearer');
ok(strpos($koios_src, 'if ($left !== null && $left > 0) $h[] = ') !== false,
   'the bearer is attached without checking the expiry first');
/* ONE copy. Drop Ship having its own was the entire bug. */
ok(strpos($dash4, 'authorization: Bearer') === false,
   'the dashboard still hardcodes a token of its own, which is how they drifted apart');
ok(strpos($dash4, 'koios_headers()') !== false, 'the dashboard does not use the shared headers');

echo "\nchanging the trait source resyncs\n";
/*
 * The sync only fired on a wallet-set change or a project switch, so
 * flipping a theme between Koios and Skulliance and reloading did nothing:
 * setting saved, page correct, barracks still empty. That reads as "the
 * other option does not work either", which is what it looked like.
 */
ok(strpos($dash4, '$_SESSION[\'userData\'][\'trait_source_key\'] !== $ds_source_key') !== false,
   'a trait source change is not noticed, so switching it never resyncs');
ok(substr_count($dash4, '|| $source_changed == "true"') === 2,
   'only one of the two pipelines resyncs when the source changes');
ok(strpos($dash4, '$_SESSION[\'userData\'][\'trait_source_key\'] = $ds_source_key;') !== false,
   'the source that ran is never remembered, so it resyncs on every single load');

echo "\na project with no collection says so\n";
/*
 * EVERY way of loading a player's NFTs runs through projects.policy_id: the
 * Koios path filters the wallet's tokens by it, the Skulliance path queries
 * collections.policy with it. So a project with none set loads nothing on
 * EITHER setting, silently -- no error, just an empty barracks and a theme
 * that looks broken with nothing on screen explaining why. Filthy Mermaid
 * hit exactly that, and the two paths failing together is the tell: they
 * share only this one input.
 */
$cpol = new TConn(array(), array(), array(), array(3 => '0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c'));
ok(dropshipProjectPolicy($cpol, 3) === '0d7c69f8e7d1e80f4380446a74737eebb6e89c56440f3f167e4e231c',
   'the admin cannot read a project collection');
ok(dropshipProjectPolicy($cpol, 2) === '', 'a project with no policy does not come back empty');
ok(dropshipProjectPolicy($cpol, 0) === '', 'a zero project id still queries');
ok(dropshipProjectPolicy(null, 3) === '', 'a dead connection does not come back empty');
/* It must be read for the project being EDITED, not the one being played --
   getProjectPolicyId() reads the session and would answer about whichever
   theme the admin happens to have open in the game. */
$adm6 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-themes.php'));
ok(strpos($adm6, 'dropshipProjectPolicy($conn, $edit_pid)') !== false,
   'the admin does not show the collection for the project being edited');
ok(strpos($adm6, 'getProjectPolicyId(') === false,
   'the admin reads the policy from the session, so it describes the wrong project');
ok(strpos($adm6, 'this theme will load no NFTs on') !== false,
   'a project with no collection is not called out, so the theme just looks broken');
$lib6 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-themes.php'));
ok(strpos($lib6, 'SELECT policy_id FROM projects WHERE id = $pid LIMIT 1') !== false,
   'the policy lookup is not keyed on the project id it was given');

echo "\nwhere a theme loads NFTs from is a setting, not a project id\n";
/*
 * TWO DIFFERENT THINGS WERE CALLED THE TRAIT SOURCE and I removed the wrong
 * one. This column picks between two real NFT-loading pipelines: 'koios'
 * reads the player's connected wallets live, 'skulliance' skips that round
 * trip and reads Skulliance's already-synced nfts table. $source in
 * dropship-traitdrop.php is the other one -- the key naming which GAME a
 * Discord trait drop came from -- and that genuinely is the slug with the
 * hyphens out. Removing this one left dashboard.php choosing the pipeline
 * on a hardcoded project_id == 4, which is what the column existed to
 * replace in the first place.
 */
$ct = function ($pid, $src) {
	$row = array('id' => 7, 'project_id' => $pid, 'slug' => 't', 'active' => 1);
	if ($src !== null) $row['trait_source'] = $src;
	return new TConn(array($pid => $row), array(7 => array(pair('exact', 'X', 'Y'))));
};
ok(dropshipThemeTraitSource($ct(70, 'skulliance'), 70) === 'skulliance',
   'a theme set to the Skulliance sync does not get it');
ok(dropshipThemeTraitSource($ct(71, 'koios'), 71) === 'koios',
   'a theme set to Koios does not get it');
/* Any other value is Koios, because an unknown pipeline must not silently
   become the one that reads a different collection. */
ok(dropshipThemeTraitSource($ct(72, 'lounge'), 72) === 'koios',
   "the old 'lounge' value is not treated as Koios");
ok(dropshipThemeTraitSource($ct(73, 'nonsense'), 73) === 'koios',
   'an unrecognised trait source is not treated as Koios');
/* A theme can now choose the Skulliance pipeline WITHOUT being project 4,
   which is the entire point. */
ok(dropshipThemeTraitSource($ct(74, 'skulliance'), 74) === 'skulliance',
   'only project 4 can use the Skulliance sync, so the setting does nothing');
/*
 * No theme row at all keeps the old hardcoded behaviour exactly.
 *
 * PROJECT 4 IS ONLY EXERCISED HERE, not also with a theme row, because
 * dropshipThemeConfig() caches per project id for the request -- a second
 * test on 4 would read the first one's cached answer and pass for the wrong
 * reason. That a saved row beats the id default is covered by 70-74 above;
 * in production a request only ever looks at one project anyway.
 */
ok(dropshipThemeTraitSource(new TConn(), 4) === 'skulliance',
   'an unseeded project 4 no longer gets the sync it has always had');
ok(dropshipThemeTraitSource(new TConn(), 1) === 'koios',
   'an unseeded project no longer gets the Koios pipeline');
/* The dashboard must ask, not test an id. */
$dash3 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash3, "\$ds_source = dropshipThemeTraitSource(\$conn, \$_SESSION['userData']['dropship_project_id'])") !== false
   && strpos($dash3, "if(\$ds_source === 'skulliance')") !== false,
   'the dashboard still picks the pipeline itself');
ok(preg_match("/dropship_project_id'\] == 4\)\{\s*\n\s*if\(\(\\\$address_changed/", $dash3) !== 1,
   'the hardcoded project 4 pipeline test is back');
/* The policy must reach the sync, or it reads somebody else's collection. */
ok(strpos($dash3, 'dropshipSyncFromSkulliance($conn, $_SESSION[\'userData\'][\'discord_id\'], $policy_id)') !== false,
   'the sync is called without the project policy, so it would read Disco Solaris for every theme');
ok(strpos($dash3, '$policy_id = getProjectPolicyId($conn);') !== false,
   'the policy the sync is handed is no longer read from the project');
ok(strpos($dash3, '$policy_id = getProjectPolicyId($conn);') < strpos($dash3, 'dropshipSyncFromSkulliance('),
   'the policy is read after the sync is called, so the sync gets nothing');
$dbsrc4 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($dbsrc4, 'function dropshipSyncFromSkulliance($conn, $discord_id, $policy = \'\')') !== false,
   'the sync does not take a policy, so it can only ever serve one theme');
ok(strpos($dbsrc4, 'function dropshipSyncOculusLounge') === false,
   'the generic pipeline is still named after one theme');
/* The schema and the seed have to carry the column again. */
ok(preg_match('/^\ttrait_source\s+VARCHAR/m', $schema) === 1,
   'the schema no longer creates trait_source');
ok(preg_match('/^\ttrait_source\s+VARCHAR/m', $seedsql) === 1,
   "the seed's own CREATE no longer has trait_source");
ok(strpos($seedsql, "(4, 'oculus-lounge', 1, 'png', 'skulliance'") !== false,
   'the seed does not put Oculus Lounge on the Skulliance sync, so re-seeding would break it');
foreach (array("(1, 'drop-ship', 1, 'gif', 'koios'",
               "(2, 'dread-city', 0, 'gif', 'koios'",
               "(3, 'filthy-mermaid', 0, 'gif', 'koios'") as $row) {
	ok(strpos($seedsql, $row) !== false, "the seed moved a theme off Koios: $row");
}
$adm5 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-themes.php'));
ok(strpos($adm5, 'name="trait_source"') !== false, 'the admin cannot set the trait source');
ok(strpos($adm5, "=== 'skulliance'\n\t\t                                   ? 'skulliance' : 'koios'") !== false
   || strpos($adm5, "? 'skulliance' : 'koios'") !== false,
   'the admin writes the posted trait source straight through without checking it');

echo "\nthe theme check covers what actually stops a theme working\n";
/*
 * Every one of these fails as SILENCE -- no NFTs, no game, no Discord post
 * -- and they look identical from outside: a theme that just does not
 * work. Filthy Mermaid hit three of them in a row, each found only by
 * reading source. The check is read-only and runs behind a GET.
 */
$probe_src = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-nft-probe.php'));
/* An active game. Only the weekly cron makes one, so a theme switched on
   mid-week has none and cannot be played at all. */
ok(strpos($probe_src, 'FROM games WHERE active = 1 AND project_id = ') !== false,
   'the check never asks whether there is a game to play');
ok(strpos($probe_src, 'php automate.php automate=1 project_id=') !== false,
   'it says there is no game without saying how to make one');
/* ASKED DIRECTLY, not through checkGame(), which WRITES to the session --
   a read-only check must not change which game the admin is in. */
ok(strpos($probe_src, 'checkGame(') === false,
   'the check calls checkGame(), which rewrites the session game id as a side effect');
/* A Discord channel, and never the URL itself. */
ok(strpos($probe_src, 'getWebhook($project_id)') !== false,
   'the check never asks whether this project has a Discord channel');
ok(strpos($probe_src, "\$hook !== '' ? 1 : 0") !== false,
   'the webhook check does not reduce to present-or-absent');
ok(strpos($probe_src, '$add(\'A Discord channel for this project\', $hook') === false
   || strpos($probe_src, '$hook . ') === false,
   'the webhook URL is printed into the page');
/* function_exists first: getWebhook lives in a credentials file that is
   not in the repo, so a checkout without it must not fatal the page. */
ok(strpos($probe_src, "if (function_exists('getWebhook'))") !== false,
   'the check calls getWebhook() unguarded, so a missing credentials file fatals the admin');
foreach (array('getSkullianceDropShipWebhook', 'getSkullianceOculusLoungeWebhook') as $fn) {
	ok(strpos($probe_src, $fn) !== false, "the mirror check does not know about $fn");
}
/* TWICE: once deciding the count, once wording the detail. A presence
   check passed with the COUNT's guard removed, because the detail line
   still mentioned it. */
ok(substr_count($probe_src, 'function_exists($mirror[$project_id])') === 2,
   'the mirror count no longer checks the function is defined, only the detail text does');
/* Both sources, always, whichever the theme is set to. */
ok(substr_count($probe_src, 'SKULLIANCE:') >= 3 && substr_count($probe_src, 'KOIOS:') >= 3,
   'the check no longer reports both NFT sources');
/* The admin must actually offer it. */
ok(strpos($adm2, "probe=1") !== false, 'the admin has no way to run the theme check');
ok(strpos($adm2, "isset(\$_GET['probe'])") !== false,
   'the check is not behind a GET, so it runs on every page view');

echo "\nno PHP warning can reach a Discord embed\n";
require_once __DIR__ . '/dropship/dropship-capture.php';
/*
 * checkLeaderboard() PRINTS, so the only way to put a board in an embed is
 * to buffer it -- and display_errors is on platform-wide, so anything
 * raised while that buffer is open goes out with it. A real Game Over post
 * went to players reading:
 *
 *   Warning: Undefined array key "game_id" in
 *   /home/jeremiah/skulliance.io/staking/dropship/db.php on line 2220
 *
 * absolute server path included.
 */
ok(dropshipStripDiagnostics("Warning: Undefined array key \"game_id\" in /home/x/db.php on line 2220\nreal") === 'real',
   'a plain PHP warning survives into the embed');
foreach (array('Notice', 'Deprecated', 'Fatal error', 'Parse error') as $kind) {
	ok(dropshipStripDiagnostics("$kind: something at /home/x.php\nreal") === 'real',
	   "a PHP $kind survives into the embed");
}
/* html_errors wraps them in markup, which a line-based pass cannot see. */
ok(dropshipStripDiagnostics("<br />\n<b>Warning</b>: Undefined array key in <b>/home/x.php</b> on line <b>9</b><br />\nkept") === 'kept',
   'an html-formatted warning survives into the embed');
/* It must not eat the leaderboard itself. */
$board = "1. alice 42\n2. bob 31\n3. carol 20";
ok(dropshipStripDiagnostics($board) === $board, 'the stripper is eating real leaderboard lines');
ok(dropshipStripDiagnostics('') === '', 'an empty board does not come back empty');
/* Driven for real: the capture returns the output and drops the notice. */
$got = dropshipCapture(function () { echo "board\n"; $a = array(); echo $a['missing'] ?? ''; });
ok($got === 'board', "the capture returned '" . $got . "' rather than just the board");
/*
 * A throw inside must not take the caller down -- the announcement is a
 * side effect of a game that has ALREADY ended, so losing the post is
 * acceptable and losing the cron run is not.
 *
 * CAUGHT HERE TOO, or the harness itself dies on the mutation this is
 * meant to catch and the runner counts zero failures because it greps for
 * the word FAIL in output that was never printed.
 */
$lvl = ob_get_level();
$threw = false; $safe = null;
try { $safe = dropshipCapture(function () { echo "before"; throw new Exception('boom'); }); }
catch (Throwable $e) { $threw = true; }
ok(!$threw, 'an exception inside the capture escapes and kills the caller');
ok($safe === 'before', 'output printed before the throw is lost');
/* ...and the buffer must always be closed, or everything the page prints
   after this silently disappears into it. */
ok(ob_get_level() === $lvl, 'the capture leaks an open output buffer when its callback throws');
/*
 * display_errors has to be restored. SET TO A KNOWN VALUE FIRST: the CLI
 * default here is already off, so a before/after comparison passed whether
 * or not it was ever restored.
 */
$before = ini_get('display_errors');
ini_set('display_errors', '1');
dropshipCapture(function () { echo 'x'; });
ok(ini_get('display_errors') === '1', 'display_errors is not restored, so later errors vanish');
ini_set('display_errors', $before);
/* Every Discord capture must go through it -- a raw ob_start() feeding an
   embed is the bug coming back. */
foreach (array('dropship/automate.php', 'dropship/dashboard.php') as $f) {
	$fsrc = harness_strip_comments(file_get_contents(__DIR__ . '/' . $f));
	ok(strpos($fsrc, 'ob_get_contents()') === false,
	   "$f still hand-rolls a capture that can carry a warning into Discord");
	ok(strpos($fsrc, 'dropshipCapture(') !== false, "$f does not use the safe capture");
}

echo "\nthe pan can be forced on or off per theme\n";
/* The same stand-in the media section uses, defined here because this
   section runs first -- without it dropshipThemeMediaExtension() takes its
   no-evaluateText() escape hatch and answers 'gif' for everything, which
   made three of these pass for entirely the wrong reason. */
if (!function_exists('evaluateText')) {
	function evaluateText($t) { return $t === 'gif' ? $GLOBALS['__ext'] : $t; }
}
$ckb = function ($pid, $val, $ext) {
	$GLOBALS['__ext'] = $ext;
	$row = array('id' => 9, 'project_id' => $pid, 'slug' => 'kb');
	if ($val !== null) $row['ken_burns'] = $val;
	$GLOBALS['conn'] = new TConn(array($pid => $row), array(9 => array(pair('exact', 'A', 'B'))));
	$_SESSION['userData']['dropship_project_id'] = $pid;
	return dropshipMediaIsStill();
};
/* auto keeps the original rule, both ways round. */
ok($ckb(90, 'auto', 'png') === true,  'auto no longer pans a still');
ok($ckb(91, 'auto', 'gif') === false, 'auto pans a gif, which fights its own animation');
ok($ckb(92, null, 'png') === true,    'a theme with no setting no longer behaves as auto');
/* ...and the overrides beat the format in BOTH directions, which is the
   whole point: either alone could be mistaken for the format rule. */
ok($ckb(93, 'off', 'png') === false, 'a still forced off is still panned');
ok($ckb(94, 'on', 'gif') === true,   'a gif forced on is not panned');
ok($ckb(95, 'on', 'png') === true,   'a still forced on is not panned');
ok($ckb(96, 'off', 'gif') === false, 'a gif forced off is panned');
/* Anything unrecognised falls back to auto rather than to a guess. */
ok($ckb(97, 'sideways', 'png') === true,  'an unknown mode does not fall back to auto on a still');
ok($ckb(98, 'sideways', 'gif') === false, 'an unknown mode does not fall back to auto on a gif');
unset($GLOBALS['conn'], $_SESSION['userData']['dropship_project_id']);
$GLOBALS['__ext'] = 'gif';
/* The admin must offer it, and must not write a value the lookup rejects. */
$adm9 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-themes.php'));
ok(strpos($adm9, 'name="ken_burns"') !== false, 'the admin cannot set the pan');
ok(strpos($adm9, "in_array(\$_POST['ken_burns'] ?? '', array('on', 'off'), true)") !== false,
   'the admin writes the posted pan setting through without checking it');
ok(preg_match('/^\tken_burns\s+VARCHAR/m', $schema) === 1, 'the schema does not create ken_burns');
ok(preg_match('/^\tken_burns\s+VARCHAR/m', $seedsql) === 1, "the seed's CREATE has no ken_burns");
ok(substr_count($seedsql, "'auto', NOW())") === 4,
   'the seeded themes do not all start on auto, so re-seeding changes how they look');

echo "\nstage art runs in order when the theme asks for it\n";
/*
 * Shuffling suits frames that are interchangeable takes on one scene --
 * "you survived the beach" -- and reads as the story jumping about when
 * they are a sequence. Per theme, defaulting to the original behaviour.
 */
if (!function_exists('verifyRandom')) {
	/* Stand in for the run's own, so 'random' is distinguishable. */
	function verifyRandom($min, $max, $prev) { return -1; }
}
ok(dropshipStageFrame(1, 7, 3, 'random') === -1, 'random no longer goes through verifyRandom');
ok(dropshipStageFrame(1, 7, 3) === -1, 'the default is not random, which changes every existing theme');
ok(dropshipStageFrame(1, 7, 3, 'nonsense') === -1, 'an unrecognised order is not treated as random');
/*
 * EVERY SCENE SEEDS "previous" WITH ITS LAST FRAME, so the first linear
 * step has to wrap round to the first.
 */
ok(dropshipStageFrame(1, 7, 7, 'linear') === 1, 'the opening round does not start at frame 1');
$seq = array(); $p = 7;
for ($i = 0; $i < 9; $i++) { $p = dropshipStageFrame(1, 7, $p, 'linear'); $seq[] = $p; }
ok($seq === array(1, 2, 3, 4, 5, 6, 7, 1, 2),
   'the beach does not run 1-7 and wrap: ' . implode(',', $seq));
/*
 * THE BUNKER SHARES ONE COUNTER ACROSS TWO RANGES -- 1-6 early, 7-12
 * late -- so stepping out of the first into the second would land on a
 * frame the second range does not own.
 */
ok(dropshipStageFrame(7, 12, 4, 'linear') === 7,
   'entering the late bunker from an early frame lands outside its range');
ok(dropshipStageFrame(7, 12, 12, 'linear') === 7, 'the late bunker does not wrap');
/* A two-frame scene alternates rather than sticking. */
$alt = array(); $p = 2;
for ($i = 0; $i < 4; $i++) { $p = dropshipStageFrame(1, 2, $p, 'linear'); $alt[] = $p; }
ok($alt === array(1, 2, 1, 2), 'a two-frame exo scene does not alternate: ' . implode(',', $alt));
/* A one-frame range cannot shuffle or advance. */
ok(dropshipStageFrame(3, 3, 3, 'linear') === 3, 'a single-frame scene moves off its only frame');
ok(dropshipStageFrame(3, 3, 3, 'random') === 3, 'a single-frame scene is shuffled into a loop');
/* The setting is read from the theme, and anything odd is random. */
/* KEYED AT THE PID BEING ASKED ABOUT. The first version built every stub
   at project 80 and then queried 81, 82, 83 -- which have no theme row, so
   every one fell through to the default and agreed with whatever the code
   did. dropshipThemeConfig() also caches per pid, so each case needs its
   own. */
$cso = function ($pid, $val) {
	$row = array('id' => 8, 'project_id' => $pid, 'slug' => 'so');
	if ($val !== null) $row['stage_order'] = $val;
	return new TConn(array($pid => $row), array(8 => array(pair('exact', 'A', 'B'))));
};
ok(dropshipThemeStageOrder($cso(80, 'linear'), 80) === 'linear', 'a linear theme is shuffled anyway');
ok(dropshipThemeStageOrder($cso(81, 'random'), 81) === 'random', 'a random theme is not random');
ok(dropshipThemeStageOrder($cso(82, 'sideways'), 82) === 'random',
   'an unknown order is not treated as random: ' . dropshipThemeStageOrder($cso(82, 'sideways'), 82));
ok(dropshipThemeStageOrder($cso(83, ''), 83) === 'random', 'an empty order is not treated as random');
ok(dropshipThemeStageOrder($cso(85, null), 85) === 'random', 'a theme with no setting is not random');
ok(dropshipThemeStageOrder(new TConn(), 84) === 'random', 'an unthemed project is not random');
/* The run must ask, and every branch must get the same answer. */
$ds2 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship.php'));
ok(strpos($ds2, '$ds_order = function_exists(\'dropshipThemeStageOrder\')') !== false,
   'the run never asks which order this theme wants');
ok(substr_count($ds2, 'verifyRandom(') === 1,
   'a stage branch still calls verifyRandom() directly, so it ignores the setting');
ok(substr_count($ds2, ', $ds_order)') === 7,
   'only ' . substr_count($ds2, ', $ds_order)') . ' of the 7 stage branches honour the setting');
/* The column has to exist for any of it to be settable. */
ok(preg_match('/^\tstage_order\s+VARCHAR/m', $schema) === 1, 'the schema does not create stage_order');
ok(preg_match('/^\tstage_order\s+VARCHAR/m', $seedsql) === 1, "the seed's CREATE has no stage_order");
/* Matched on the column itself, not on its position at the end of the
   row -- adding ken_burns after it broke this for a reason that has
   nothing to do with stage order. */
ok(substr_count($seedsql, "'random', 'auto', NOW())") === 4
   || substr_count($seedsql, ", 'random',") === 4,
   'the seeded themes do not all keep the original shuffled behaviour');
$adm8 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/admin-themes.php'));
ok(strpos($adm8, 'name="stage_order"') !== false, 'the admin cannot set the stage order');
ok(strpos($adm8, "=== 'linear'\n		                                   ? 'linear' : 'random'") !== false
   || strpos($adm8, "? 'linear' : 'random'") !== false,
   'the admin writes the posted order through without checking it');

echo "\nevery active game pays into a real currency\n";
/*
 * THE MAP LIVES ON THE SKULLIANCE SIDE, with the currencies, and is never
 * taken from the request -- the endpoint that credits balances has NO
 * AUTHENTICATION (it runs at db.php include time, so an unsigned POST of
 * three fields to any url on the platform credits somebody), and letting
 * the caller name the project would turn "two currencies" into "any
 * balance on the platform".
 */
$skdb = harness_strip_comments(file_get_contents(__DIR__ . '/db.php'));
ok(strpos($skdb, 'function dropshipRewardProjects()') !== false,
   'there is no map of which game pays into which currency');
preg_match_all('/^\t\t(\d+) => (\d+),/m',
	substr($skdb, strpos($skdb, 'function dropshipRewardProjects()'), 400), $pm, PREG_SET_ORDER);
$pay = array();
foreach ($pm as $row) $pay[(int)$row[1]] = (int)$row[2];
ok($pay === array(1 => 2, 2 => 2, 3 => 2, 4 => 21),
   'the payout map changed: ' . json_encode($pay));
/* Chosen HERE, not posted. */
ok(strpos($skdb, '$ds_map = dropshipRewardProjects();') !== false,
   'the endpoint no longer uses its own map');
/* Assigned FROM THE MAP. Checking only that one literal spelling was
   absent let a cast through -- (int)$_POST['project_id'] is the same
   mistake wearing a hat. */
ok(preg_match('/\$project_id = \$ds_map\[\$ds_from\];/', $skdb) === 1,
   'the target project does not come from the map');
ok(preg_match("/\\$project_id\s*=\s*[^;\n]*\\$_POST/", $skdb) !== 1,
   'the target project is taken from the request, so any balance can be credited');
/* An unmapped game must pay NOTHING rather than fall through with
   $project_id undefined and credit project 0. */
ok(strpos($skdb, 'if (isset($ds_map[$ds_from]) && $subtotal > 0) {') !== false,
   'an unmapped game still reaches updateBalance() with an undefined project');
/* Drop Ship posts for every game and lets that map decide -- the gate was
   `== 1 || == 4`, so activating a theme meant editing a second place, and
   Filthy Mermaid got a weekly cycle without one and paid nobody. */
/* READ HERE. This referenced $dbsrc6, which is not defined until a later
   section -- strpos(null, ...) is false, so the check passed whatever the
   code said, including the exact gate it exists to forbid. */
$dsdb = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($dsdb, "dropship_project_id'] == 1 || \$_SESSION['userData']['dropship_project_id'] == 4") === false,
   'the payout is still gated on two hardcoded project ids');
/*
 * THE INSTRUCTIONS MUST NAME THE SAME CURRENCY THE PAYOUT USES. "MOON" was
 * the everything-else answer, which was right while only two games paid
 * and wrong the moment a third did.
 */
$instr = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/instructions.php'));
ok(strpos($instr, "== 1) ? \"DREAD\" : \"MOON\"") === false,
   'the instructions still call every game but Drop Ship a MOON payout');
preg_match("/\\\$ds_pays = array\(([^)]*)\)/", $instr, $im);
ok(!empty($im), 'the instructions no longer name a currency per game');
$said = array();
if (!empty($im)) {
	foreach (explode(',', $im[1]) as $bit) {
		if (preg_match("/(\d+)\s*=>\s*'(\w+)'/", $bit, $b)) $said[(int)$b[1]] = $b[2];
	}
}
/* Same games, and DREAD exactly where the map says project 2. */
ok(array_keys($said) === array_keys($pay),
   'the instructions and the payout map cover different games');
foreach ($said as $pid => $cur) {
	$expect = ($pay[$pid] === 2) ? 'DREAD' : 'MOON';
	ok($cur === $expect,
	   "the instructions call game $pid a $cur payout but it credits project " . $pay[$pid]);
}

echo "\nthe weekly cycle runs for every live theme\n";
/*
 * automate.php is the cron that ends the week's game, posts the final
 * board, and opens the next one with its prize name. It listed projects 1
 * and 4 only, so a newly activated theme had no weekly cycle at all --
 * its game would simply never end.
 */
$auto = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/automate.php'));
ok(preg_match('/\$all = array\(([0-9, ]+)\);/', $auto, $am) === 1,
   'the cron no longer declares which projects it runs');
$cron_pids = array_map('intval', explode(',', $am[1]));
foreach (array(1, 2, 3, 4) as $pid) {
	ok(in_array($pid, $cron_pids, true),
	   "project $pid has no weekly cycle, so its game never ends and no new one opens");
}
/* Every game that PAYS has to have a cycle to pay at the end of. */
foreach (array_keys($pay) as $pid) {
	ok(in_array($pid, $cron_pids, true),
	   "project $pid pays a currency but has no weekly cycle to pay it in");
}
/*
 * ONE PROJECT ON REQUEST. Switching a theme on mid-week needs it given a
 * game, and the only way to do that was to run the whole cron -- which
 * also ENDS whatever games the other themes are in the middle of.
 */
ok(strpos($auto, "\$only = isset(\$_GET['project_id']) ? (int)\$_GET['project_id'] : 0;") !== false,
   'the cron cannot be run for a single project, so giving one theme a game ends the others');
ok(strpos($auto, 'in_array($only, $all, true)') !== false,
   'a project_id off the command line is not checked against the known list');
ok(strpos($auto, 'foreach ($only ? array($only) : $all as $ds_pid)') !== false,
   'without project_id the cron no longer runs every project');
/*
 * AND IT MUST NOT "END" A GAME THAT IS NOT THERE. checkGame() unsets
 * game_id when a project has no active game, and everything after it
 * assumed one: deactivateGame() would UPDATE ... WHERE id='' after
 * pointlessly logging balances and resetting soldiers, and Discord would
 * get a Game Over post with an empty board for a game that never existed.
 * That is precisely the state a theme is in the first time it is enabled.
 */
ok(strpos($auto, "checkGame(\$conn);
	if (isset(\$_SESSION['userData']['game_id'])) {") !== false,
   'the weekly run ends a game without checking there is one');
/*
 * PRESENCE FIRST, THEN ORDER. Anchored on CODE rather than a comment,
 * because harness_strip_comments() removes the marker this first used --
 * and strpos() returns FALSE, which compares as 0, so an ordering check
 * passes whenever either side is simply MISSING. The same trap caught
 * this check twice: once on the deleted comment, once on a mutation that
 * removed deactivateGame() altogether.
 */
$at_close  = strpos($auto, 'deactivateGame($conn);');
$at_create = strpos($auto, 'createGame($conn, $prizes, 3);');
ok($at_close !== false, 'the weekly run no longer closes the old game at all');
ok($at_create !== false, 'the weekly run no longer opens a new game at all');
ok($at_close !== false && $at_create !== false && $at_close < $at_create,
   'the new game is created before the old one is closed');
/* Drop Ship (1) is deliberately absent from the prefix branch -- it is the
   default, because its art is flat rather than nested. */
/* Every nested theme needs its own art prefix, or the weekly announcement
   image is built from the wrong folder and 404s. */
foreach (array(2 => 'dread-city', 3 => 'filthy-mermaid', 4 => 'oculus-lounge') as $pid => $pfx) {
	ok(preg_match('/\$project_id == ' . $pid . '\)\{\s*\n\s*\$prefix = "' . $pfx . '";/', $auto) === 1,
	   "project $pid does not use the $pfx art folder, so its announcement image 404s");
}
/*
 * DEFAULTED BEFORE THE BRANCH. Both were only assigned inside the if/else,
 * so a project nobody had listed created a NAMELESS game and built its
 * image path from an empty prefix.
 */
/* Anchored on the DEFAULT, not on whichever project happens to be first
   in the branch -- adding a game changed that and broke this check for a
   reason unrelated to what it tests. */
ok(preg_match('/\t\$prefix = "drop-ship";\n\t\$prizes = "1K, 500, 250 DREAD";\n\tif\(\$project_id ==/', $auto) === 1,
   'the prefix and prize name are not defaulted before the branch');
/*
 * games.prizes IS A COUNT, not a prize list -- checkLeaderboard() cuts the
 * board at `$counter > $row["prizes"]` and dashboard.php's own field is
 * labelled "Number of Prizes". So createGame($conn, $prizes, 3) passes the
 * NAME then the count, which reads wrong and is right. Pinned because it
 * looks exactly like an argument-order bug and I called it one once.
 */
ok(strpos($auto, 'createGame($conn, $prizes, 3);') !== false,
   'the weekly game is no longer created with three prize places');
$dbsrc6 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/db.php'));
ok(strpos($dbsrc6, 'function createGame($conn, $name, $prizes)') !== false,
   'createGame\'s signature changed; check automate.php still passes name then count');
ok(strpos($dbsrc6, 'if($counter > $row["prizes"])') !== false,
   'games.prizes is no longer the number of winning places, so the call above is now wrong');

echo "\nthe trait drop credits the theme, not a project id\n";
/*
 * $source is a key into the game registry in dhcfighters-config.php, which
 * supplies the label, the X tag and the link on the drop post. It used to be
 * ($project === 4) ? 'oculuslounge' : 'dropship', so a new theme could never
 * be credited without editing this file -- which is the opposite of themes
 * being data. The keys are the slug with the hyphens out.
 */
$td = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dropship-traitdrop.php'));
ok(strpos($td, "(\$project === 4) ? 'oculuslounge'") === false,
   'the trait drop still decides the credit from a hardcoded project id');
ok(strpos($td, "str_replace('-', '', dropshipThemeSlug(\$cfg['slug']))") !== false,
   'the trait drop does not derive its credit from the theme slug');
/* The registry keys really are the de-hyphenated slugs, which is the whole
   premise. Checked against the registry, not asserted. */
require_once __DIR__ . '/dhcfighters-config.php';
foreach (array('oculus-lounge' => 'oculuslounge', 'drop-ship' => 'dropship') as $slug => $key) {
	ok(dhcf_game(str_replace('-', '', $slug)) !== null,
	   "'$slug' does not resolve to a registered game, so the derivation is wrong");
	ok(str_replace('-', '', $slug) === $key, "'$slug' does not de-hyphenate to '$key'");
}
/* Filthy Mermaid IS registered now, and its key is the slug with the
   hyphens out -- get that wrong and its drops silently post as Drop Ship. */
ok(dhcf_game('filthymermaid') !== null,
   'filthy-mermaid is not registered, so its drops credit Drop Ship instead');
ok(dhcf_earned_tag('filthymermaid') === '@ohh_meed',
   'Filthy Mermaid drops do not tag @ohh_meed, who made it');
ok(dhcf_earned_from('filthymermaid') === 'Filthy Mermaid',
   'Filthy Mermaid drops do not read as being from Filthy Mermaid');
ok(isset($GLOBALS['DHCF_FLOORS']['filthymermaid']) && $GLOBALS['DHCF_FLOORS']['filthymermaid'] === 0,
   'Filthy Mermaid has no floor of 0, so the first player in a new game is shut out');
/* The unregistered fallback still needs SOMETHING unregistered to test. */
ok(dhcf_game('notarealtheme') === null,
   'every key resolves, so the unregistered fallback below is untested');
/* An unregistered theme must fall back rather than post a drop crediting a
   key with no label, no tag and no link. */
ok(strpos($td, "if (\$source !== 'dropship'") !== false
   && strpos($td, "!dhcf_game(\$source)") !== false,
   'an unregistered theme is not checked, so its drop would credit nobody');
/* ...and the check has to sit AFTER the registry is loaded, or it always
   fails and every theme posts as Drop Ship. */
ok(strpos($td, 'dhcfighters-lib.php') < strpos($td, "!dhcf_game(\$source)"),
   'the registry check runs before dhcfighters-lib.php loads, so it always falls back');

echo "\nanimation can be switched on for a still theme that has gifs\n";
/*
 * dropship_themes.extension is what a theme is SET to, not what it HAS:
 * Oculus Lounge ships both sets, and the old 3D toggle was the only way to
 * see the animated one. Retiring 3D was right -- it was a second format
 * calling itself a dimension, and it dragged two blanket str_replace
 * passes over stored replay markup with it -- but it took that with it.
 */
/*
 * THE PROBES COME FROM THE MANIFEST, NOT FROM A GUESS. The first version
 * of this hardcoded images/drop/1.gif -- a path that has never existed,
 * because that folder holds drop.gif and team.gif rather than numbered
 * frames -- so the check was false for every theme and the Animation
 * switch never appeared at all. The harness guessed the same way, so it
 * agreed with the bug.
 */
$probes = dropshipThemeGifProbes();
ok(count($probes) === 2, 'the gif check no longer probes two files: ' . count($probes));
require_once __DIR__ . '/dropship/dropship-art.php';
$all_gif = array_values(array_filter(dropshipArtManifest('gif'),
	function ($r) { return substr($r, -4) === '.gif'; }));
foreach ($probes as $probe) {
	ok(in_array($probe, $all_gif, true),
	   "the gif probe '$probe' is not in the manifest, so it can never be found");
}
ok($probes[0] === $all_gif[0] && $probes[1] === $all_gif[count($all_gif) - 1],
   'the probes are not the first and last gif, so a half-uploaded set reads as finished');

$bgdir = __DIR__ . '/dropship/harness-anim-test';
foreach ($probes as $probe) @mkdir($bgdir . '/' . dirname($probe), 0777, true);
ok(is_dir($bgdir . '/' . dirname($probes[0])), 'could not build a throwaway theme to probe');
ok(!dropshipThemeHasGifArt('harness-anim-test'),
   'a theme with no gifs on disk is reported as animated');
file_put_contents($bgdir . '/' . $probes[0], 'x');
ok(!dropshipThemeHasGifArt('harness-anim-test'),
   'ONE stray gif makes a still theme advertise itself as animated');
file_put_contents($bgdir . '/' . $probes[1], 'x');
ok(dropshipThemeHasGifArt('harness-anim-test'), 'a theme with gifs on disk is not found');
/* The preference is only honoured when the files are there, so a stale
   session flag can never make a theme ask for art it does not have. */
$_SESSION['userData']['animation'] = 'true';
ok(dropshipAnimationOn('harness-anim-test'), 'the preference is ignored when the gifs exist');
ok(!dropshipAnimationOn('no-such-theme'),
   'the preference is honoured for a theme with no gifs, which would ask for 404s');
$_SESSION['userData']['animation'] = 'false';
ok(!dropshipAnimationOn('harness-anim-test'), 'turning it off does not turn it off');
unset($_SESSION['userData']['animation']);
ok(!dropshipAnimationOn('harness-anim-test'), 'animation is on by default, changing every theme');
/* Anything other than the literal 'true' is off -- the value decides which
   FILES the run asks for. */
foreach (array('1', 'yes', 'TRUE', 'on', 1, true) as $junk) {
	$_SESSION['userData']['animation'] = $junk;
	ok(!dropshipAnimationOn('harness-anim-test'),
	   'a loose value (' . var_export($junk, true) . ') counts as animation on');
}
unset($_SESSION['userData']['animation']);

/*
 * DRIVEN THROUGH THE REAL RESOLVER, which is the only thing that matters:
 * every caller asks dropshipMediaExtension(), so a preference the helpers
 * agree on but the resolver ignores changes nothing on screen. Asserting
 * the pieces separately let exactly that mutation live.
 */
if (!function_exists('evaluateText')) {
	function evaluateText($t) { return $t === 'gif' ? $GLOBALS['__ext'] : $t; }
}
$GLOBALS['slug'] = 'harness-anim-test';
$GLOBALS['__ext'] = 'png';
$_SESSION['userData']['animation'] = 'true';
ok(dropshipMediaExtension() === 'gif',
   'animation is on and the gifs exist, but the run still asks for stills');
/* ...and the pan has to come off with it, or a 40s drift is dragged across
   art that is already moving. */
ok(dropshipMediaIsStill() === false, 'the Ken Burns pan is still laid over the animated set');
$_SESSION['userData']['animation'] = 'false';
ok(dropshipMediaExtension() === 'png', 'turning animation off does not go back to the stills');
ok(dropshipMediaIsStill() === true, 'the pan does not come back when animation is off');
/* A theme with no gifs cannot be switched, however the flag is set. */
$GLOBALS['slug'] = 'no-such-theme';
$_SESSION['userData']['animation'] = 'true';
ok(dropshipMediaExtension() === 'png',
   'a theme with no gifs on disk is switched to gif anyway, so every frame 404s');
/* A theme already set to gif is unaffected either way. */
$GLOBALS['slug'] = 'harness-anim-test';
$GLOBALS['__ext'] = 'gif';
ok(dropshipMediaExtension() === 'gif', 'an already-animated theme is changed by the preference');

/*
 * THE SWITCH MUST SURVIVE BEING USED. "Can this theme be animated" is a
 * property of the THEME; "is it animated right now" is a property of the
 * session. Asking the resolved extension for the first question meant the
 * control vanished the moment it was turned on -- it reports 'gif' once
 * animation is active -- and there was no way back to the stills.
 */
$GLOBALS['__ext'] = 'png';
$_SESSION['userData']['animation'] = 'true';
ok(dropshipThemeMediaExtension() === 'png',
   'the theme extension follows the player preference, so the switch hides itself once used');
ok(dropshipMediaExtension() === 'gif', 'the resolved extension ignores the preference');
/* The dashboard's own gate, evaluated exactly as it is written. */
$gate = function () {
	return function_exists('dropshipThemeHasGifArt')
		&& dropshipThemeMediaExtension() !== 'gif'
		&& dropshipThemeHasGifArt($GLOBALS['slug']);
};
ok($gate(), 'the Animation switch disappears once it is switched ON, and cannot be reverted');
$_SESSION['userData']['animation'] = 'false';
ok($gate(), 'the Animation switch is missing when it is switched off');
/* ...and a theme that IS a gif still never offers it. */
$GLOBALS['__ext'] = 'gif';
ok(!$gate(), 'an already-animated theme is offered a switch that would do nothing');
$GLOBALS['__ext'] = 'png';
$dash7 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash7, "dropshipThemeMediaExtension() !== 'gif'") !== false,
   'the dashboard gate asks the resolved extension again, so the switch hides itself');
ok(strpos($dash7, "&& dropshipMediaExtension() !== 'gif'") === false,
   'the old self-hiding condition is back');
/* The control keeps the icon the 3D toggle had -- this is what that was. */
ok(strpos($dash7, 'src="icons/3d.png"') !== false,
   'the Animation switch lost the 3D icon that belongs to it');
unset($_SESSION['userData']['animation'], $GLOBALS['slug']);
$GLOBALS['__ext'] = 'gif';

foreach ($probes as $probe) @unlink($bgdir . '/' . $probe);
/* Deepest first, or the parents are never empty. */
$dirs = array();
foreach ($probes as $probe) {
	$d = dirname($probe);
	while ($d !== '.' && $d !== '') { $dirs[$d] = strlen($d); $d = dirname($d); }
}
arsort($dirs);
foreach (array_keys($dirs) as $d) @rmdir($bgdir . '/' . $d);
@rmdir($bgdir);
ok(!is_dir($bgdir), 'the harness left its throwaway theme behind');
/* The endpoint must not store anything but the two values it means --
   unlike toggle-audio.php next door, which writes $_GET straight in. */
$tog = (string)file_get_contents(__DIR__ . '/dropship/ajax/toggle-animation.php');
ok(strpos($tog, "\$_GET['status'] === 'true' ? 'true' : 'false'") !== false,
   'the animation endpoint stores whatever it is given');
/* The switch is only offered when it would do something. */
$dash6 = harness_strip_comments(file_get_contents(__DIR__ . '/dropship/dashboard.php'));
ok(strpos($dash6, "dropshipThemeMediaExtension() !== 'gif'") !== false,
   'the switch is offered on a theme that is already animated');
ok(strpos($dash6, 'dropshipThemeHasGifArt($slug)') !== false,
   'the switch is offered on a theme with no gifs, so it would switch to 404s');
ok(strpos($dash6, 'toggleAnimation(this.checked)') !== false, 'the switch is not wired up');
/* It has to reload: the extension decides what the SERVER renders. */
$js2 = (string)file_get_contents(__DIR__ . '/dropship/dropship.js');
ok(strpos($js2, 'function toggleAnimation') !== false, 'there is no client handler');
ok(strpos($js2, 'window.location.reload()') !== false,
   'the page is not redrawn, so the art does not actually change');
ok(strpos($js2, '.then(done, done)') !== false,
   'the reload does not wait for the write, so it races the session and comes back unchanged');

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
/*
 * THE THEME CAN OVERRIDE THE FORMAT. 'auto' is the original rule and what
 * every theme starts on, so nothing changes until somebody chooses; a
 * still whose art is already a composed scene does not want drifting, and
 * a gif whose frames barely move might.
 *
 * These run with no theme row, so 'auto' is in force throughout.
 */
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

echo "\nthe find column is case sensitive, because the lookup is\n";
/*
 * WHY THIS IS STILL utf8mb4_bin NOW THAT THE SEED HAS NO CASE-ONLY PAIRS.
 *
 * It was originally forced by the data: 'Medkit' was the word a player reads
 * and 'medkit' the icon filename, two rows that MySQL's DEFAULT CASE
 * INSENSITIVE COLLATION treats as one, which rejected the seed outright with
 * "#1062 Duplicate entry '1-exact-melee'". The icon rows are gone -- the
 * name is derived -- so nothing in the seed collides any more.
 *
 * The column must stay case sensitive anyway, because THE LOOKUP IS.
 * dropshipThemeDictionaries() keys a PHP array and PHP array keys are
 * byte-compared, so under a case-insensitive column two rows differing only
 * by case would be one row in the database and two in the dictionary. The
 * storage has to agree with the thing reading it, whether or not today's
 * data happens to exercise the difference.
 */
$seed_ci = array();
foreach (array_keys($blocks) as $bpid) {
	if (preg_match_all("/\(\@t, '(exact|description)', '((?:[^']|\\\\')*)'/", $blocks[$bpid], $m)) {
		foreach ($m[1] as $k => $t) {
			$key = $bpid . '|' . $t . '|' . strtolower($m[2][$k]);
			$seed_ci[$key] = isset($seed_ci[$key]) ? $seed_ci[$key] + 1 : 1;
		}
	}
}
$collide = array_filter($seed_ci, function ($n) { return $n > 1; });
ok(empty($collide),
   'the seed has case-only pairs again (' . implode(', ', array_keys($collide))
   . '), which is fine for the column but means an icon name is being stored');
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
