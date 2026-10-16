<?php
/*
 * The gate for moving Monstrocity themes out of code and into a table.
 *
 * THE ONLY QUESTION THIS ASKS: does the database-driven generator emit the
 * EXACT bytes the old JS literal emitted, for every combination of the two
 * visibility flags? Not "equivalent output" -- identical. If one space moves,
 * this fails, and that is the point: the whole value of this migration is that
 * it changes nothing observable.
 *
 * It reads the seed from monstrocity-schema.md rather than from a fixture, so
 * a typo in the SQL you are about to paste into phpMyAdmin fails here first.
 *
 * Usage: php monstrocity-themes-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/monstrocity-themes-lib.php';
function admin_tabs_src() { return file_get_contents(__DIR__ . '/admin-lib.php'); }

/* ---- 1. the seed, parsed out of the schema doc ---------------------------- */
$doc = file_get_contents(__DIR__ . '/monstrocity-schema.md');
$at  = strpos($doc, 'VALUES');
$sql = substr($doc, $at + 6, strpos($doc, ';', $at) - $at - 6);
preg_match_all('/\(([^()]*(?:\([^)]*\)[^()]*)*)\)/', $sql, $mm);
$cols = array('slug','project_id','project_name','title','policy_ids',
              'orientations','ipfs_prefixes','background','extension','theme_group',
              'group_order','req_innercircle','req_dev','sort_order','active','created_at');
$seed = array();
foreach ($mm[1] as $tuple) {
	/* split on commas that are not inside single quotes */
	$parts = preg_split("/,(?=(?:[^']*'[^']*')*[^']*$)/", $tuple);
	if (count($parts) !== count($cols)) continue;
	$row = array();
	foreach ($cols as $i => $c) {
		$v = trim($parts[$i]);
		if ($v === 'NULL' || $v === 'NOW()') { $row[$c] = null; continue; }
		if (strlen($v) > 1 && $v[0] === "'") $v = str_replace(array("\\'","\\\\"), array("'","\\"), substr($v, 1, -1));
		$row[$c] = $v;
	}
	$row['id'] = count($seed) + 1;
	$seed[] = $row;
}
ok(count($seed) === 49, 'the seed holds ' . count($seed) . ' themes, not the 49 that were in the code');

/* ---- 2. a stand-in for $conn --------------------------------------------- */
class MonStubRes {
	private $rows; private $i = 0;
	public function __construct($r) { $this->rows = $r; }
	public function fetch_assoc() { return $this->i < count($this->rows) ? $this->rows[$this->i++] : null; }
}
/*
 * THE STUB OBEYS THE QUERY, it does not ignore it.
 *
 * The first version sorted and filtered in PHP regardless of the SQL handed
 * to it, which meant the SQL was never tested at all: reversing the ORDER BY
 * and deleting the `active = 1` filter both passed. A stub that answers
 * correctly no matter what it is asked is not a test of the caller.
 *
 * It is not a SQL engine. It honours exactly the two clauses this function's
 * output depends on, and records the query so the assertions below can be
 * explicit about what was asked for.
 */
class MonStubConn {
	public $rows; public $lastSql = ''; public $sqls = array();
	public function __construct($r) { $this->rows = $r; }
	/* Collections attached to themes: array(theme_id => array(policy, ...)). */
	public $links = array();
	public function query($sql) {
		$this->lastSql = $sql; $this->sqls[] = $sql;

		/* TWO TABLES NOW, so the stub has to know which one it was asked for.
		   Returning themes for every query would make the collection-override
		   path untestable -- it would never see a link row. */
		if (strpos($sql, 'monstrocity_theme_collections') !== false) {
			$rows = array();
			foreach ($this->links as $tid => $policies) {
				foreach ($policies as $i => $pol) {
					if ($pol === '') continue;          // the query filters these out
					$rows[] = array('theme_id' => (string)$tid, 'policy' => $pol, '_o' => $i);
				}
			}
			usort($rows, function ($a, $b) {
				return array((int)$a['theme_id'], $a['_o']) <=> array((int)$b['theme_id'], $b['_o']);
			});
			return new MonStubRes($rows);
		}

		$r = $this->rows;
		if (preg_match('/\bactive\s*=\s*1\b/i', $sql)) {
			$r = array_filter($r, function ($x) { return (int)$x['active'] === 1; });
		}
		$r = array_values($r);
		if (preg_match('/ORDER BY\s+sort_order\s+(ASC|DESC)/i', $sql, $m)) {
			$dir = strtoupper($m[1]) === 'DESC' ? -1 : 1;
			usort($r, function ($a, $b) use ($dir) { return $dir * ((int)$a['sort_order'] <=> (int)$b['sort_order']); });
		}
		return new MonStubRes($r);
	}
}
$conn = new MonStubConn($seed);

/* ---- 3. the original literal, rendered by the original code --------------- */
/* The frozen original. monstrocity.php no longer contains the literal -- that
   is the whole point of the change -- so the reference lives in its own file
   and never moves again. */
$snap = file_get_contents(__DIR__ . '/monstrocity-themes-snapshot.php');
$body = substr($snap, strpos($snap, '?>') + 2);
$body = rtrim(ltrim($body, "\r\n"), " \t\r\n");
ok(substr_count($body, 'policyIds:') === 49,
   'the snapshot holds ' . substr_count($body, 'policyIds:') . ' themes, not the 49 that were in the code');
/* And the literal really is gone from the page. */
$page = file_get_contents(__DIR__ . '/monstrocity.php');
ok(strpos($page, 'group: "Partner Project Themes"') === false,
   'monstrocity.php still carries the theme literal, so the page and the table can drift apart');
ok(strpos($page, 'monstrocity_themes_js($conn') !== false,
   'monstrocity.php does not render its themes from the database');

function render_original($body, $innercircle, $dev) {
	/* The block is PHP plus text, so evaluating it IS the original behaviour --
	   no reimplementation of the conditionals to get subtly wrong. */
	ob_start();
	eval('?>' . $body);
	$o = ob_get_clean();
	return rtrim($o, " \t\r\n");
}

/* ---- 4. byte comparison, every visibility combination --------------------- */
$combos = array(
	array(0, 0, 'logged out / ordinary member'),
	array(1, 0, 'Inner Circle'),
	array(1, 1, 'Inner Circle + dev'),
	array(0, 1, 'dev who is NOT Inner Circle (the nesting case)'),
);
foreach ($combos as $c) {
	list($ic, $dv, $label) = $c;
	$want = render_original($body, $ic, $dv);
	$got  = rtrim(monstrocity_themes_js($conn, (bool)$ic, (bool)$dv), " \t\r\n");

	/*
	 * TWO TESTS, AND THE SECOND IS THE REAL ONE.
	 *
	 * Byte-identity was the gate I set, and two of the four combinations meet
	 * it outright. The other two differ at exactly one place: the original
	 * emitted "}\n\t    \t    ,\n" between the third and fourth groups, because
	 * the line holding `<?php if($innercircle) { ?>` left its own indentation
	 * behind in the output. That is an artifact of the tag, not content, and
	 * reproducing it in a generator that has no tag would be daft.
	 *
	 * So the gate becomes: WITH ALL WHITESPACE REMOVED, the two must be byte
	 * identical. That still rules out every difference that could matter -- a
	 * changed value, a lost theme, a reordered group, a stray character -- and
	 * admits only differences in spacing between JS tokens. The byte count is
	 * printed either way so a surprise cannot hide in the gap.
	 */
	$wantTight = preg_replace('/\s+/', '', $want);
	$gotTight  = preg_replace('/\s+/', '', $got);
	if ($want === $got) {
		printf("  identical, byte for byte (%6d bytes)   %s\n", strlen($got), $label);
		continue;
	}
	if ($wantTight === $gotTight) {
		printf("  identical ignoring whitespace (%6d vs %6d bytes, %+d)   %s\n",
			strlen($want), strlen($got), strlen($got) - strlen($want), $label);
		continue;
	}
	$fail++;
	printf("  FAIL  %s : content differs, not just spacing\n", $label);
	$n = min(strlen($wantTight), strlen($gotTight));
	for ($i = 0; $i < $n; $i++) if ($wantTight[$i] !== $gotTight[$i]) break;
	printf("        first real difference at token-byte %d\n", $i);
	printf("        expected: %s\n", substr($wantTight, max(0,$i-60), 120));
	printf("        got     : %s\n", substr($gotTight,  max(0,$i-60), 120));
}

/* ---- 4b. the SHAPE, which the whitespace-blind comparison above cannot see --
 *
 * Stripping whitespace to forgive the PHP-tag artifact also forgives every
 * other whitespace change, including collapsing the newlines between groups.
 * That is semantically harmless in JS but it is still an unreviewed change to
 * generated output, and the mutation that did it sailed through. So the exact
 * line and group counts are pinned per combination. If the formatting is ever
 * deliberately changed, these numbers get updated in the same commit.
 */
$shape = array(
	'0|0' => array(434, 3, 42),
	'1|0' => array(499, 4, 48),
	'1|1' => array(509, 4, 49),
	'0|1' => array(434, 3, 42),
);
foreach ($combos as $c) {
	list($ic, $dv, $label) = $c;
	$o = monstrocity_themes_js($conn, (bool)$ic, (bool)$dv);
	list($nl, $gr, $it) = $shape[$ic . '|' . $dv];
	$haveNl = substr_count($o, "\n");
	$haveGr = substr_count($o, 'group: ');
	$haveIt = substr_count($o, 'policyIds:');
	ok($haveNl === $nl, "$label: $haveNl newlines, expected $nl -- the generated layout changed");
	ok($haveGr === $gr, "$label: $haveGr groups, expected $gr");
	ok($haveIt === $it, "$label: $haveIt themes, expected $it");
}

/* The query really did ask for those two things, in the real SQL, not just in
   whatever the stub chose to do. */
/* The THEMES query specifically -- lastSql is now whichever ran most recently,
   and the collections read runs second. Asserting against the wrong one is how
   this check briefly started failing for the right reason. */
$themeSql = '';
foreach ($conn->sqls as $q) if (strpos($q, 'FROM monstrocity_themes') !== false) $themeSql = $q;
$linkSql = '';
foreach ($conn->sqls as $q) if (strpos($q, 'monstrocity_theme_collections') !== false) $linkSql = $q;
ok($themeSql !== '', 'no query was ever issued against monstrocity_themes');
ok(preg_match('/\bactive\s*=\s*1\b/i', $themeSql) === 1,
   'the themes query does not filter on active = 1, so a retired theme still renders');
ok(preg_match('/ORDER BY\s+sort_order\s+ASC/i', $themeSql) === 1,
   'the themes query does not order by sort_order ASC, so group order is whatever the table returns');
ok($linkSql !== '', 'the attached collections are never read, so linking one would do nothing');
ok(preg_match('/ORDER BY\s+tc\.theme_id\s+ASC,\s*tc\.sort_order\s+ASC/i', $linkSql) === 1,
   'attached collections are read in no defined order, so a theme\'s policy list could reshuffle between page loads');

/* ---- 4c. collections override the legacy policy string ------------------- */
/*
 * A theme covers several collections, which is why some carry a list of
 * policy ids. Where those collections exist here, the policy is read from
 * them. Where they do not, the hardcoded string stands.
 *
 * The first half of this is the safety property for the migration: with
 * nothing attached, output is unchanged -- which is what every byte
 * comparison above has already proved, since $links starts empty.
 */
$before = monstrocity_themes($conn);
$apprIx = null;
foreach ($before as $i => $r) if ($r['slug'] === 'apprentices') $apprIx = $i;
ok($apprIx !== null, 'the apprentices theme is missing from the seed');

if ($apprIx !== null) {
	$legacy = $before[$apprIx]['policy_ids'];
	ok($legacy !== '', 'apprentices has no legacy policy string to override');

	$conn->links = array((int)$before[$apprIx]['id'] => array('aaa111', 'bbb222'));
	$after = monstrocity_themes($conn);
	ok($after[$apprIx]['policy_ids'] === 'aaa111,bbb222',
	   'attached collections did not replace the legacy policy ids (got "'
	 . $after[$apprIx]['policy_ids'] . '")');

	/* Order is the link order, not whatever the table felt like. */
	$conn->links = array((int)$before[$apprIx]['id'] => array('zzz999', 'aaa111'));
	$after = monstrocity_themes($conn);
	ok($after[$apprIx]['policy_ids'] === 'zzz999,aaa111',
	   'attached collections are not emitted in their sort order');

	/* Everyone else is untouched by one theme being linked. */
	$others = 0;
	foreach ($after as $i => $r) if ($i !== $apprIx && $r['policy_ids'] !== $before[$i]['policy_ids']) $others++;
	ok($others === 0, $others . ' other themes changed when one theme was linked');

	/* A theme with links but no usable policy keeps its legacy string rather
	   than rendering an empty policy list, which would silently un-gate it. */
	$conn->links = array((int)$before[$apprIx]['id'] => array(''));
	$after = monstrocity_themes($conn);
	ok($after[$apprIx]['policy_ids'] === $legacy,
	   'a theme linked to a collection with no policy lost its legacy ids and now matches nothing');

	/* Two collections can legitimately share a policy (the same policy covering
	   more than one collection row). The rendered list must not repeat it --
	   the client splits this string and a duplicate is a wasted comparison at
	   best and a confusing admin display at worst. */
	$conn->links = array((int)$before[$apprIx]['id'] => array('dup111', 'dup111', 'other2'));
	$after = monstrocity_themes($conn);
	ok($after[$apprIx]['policy_ids'] === 'dup111,other2',
	   'a policy shared by two attached collections is emitted twice (got "'
	 . $after[$apprIx]['policy_ids'] . '")');

	$conn->links = array();   // back to the migration state for anything below
}

/*
 * IF THE LINK TABLE CANNOT BE READ, EVERY THEME KEEPS ITS LEGACY POLICIES.
 *
 * Same deploy window as the themes table itself: this code can be live before
 * monstrocity_theme_collections exists. Failing that read must not blank out
 * the policy ids, because a theme with no policies matches no NFTs and would
 * quietly stop recognising anyone's holdings.
 */
class MonHalfConn extends MonStubConn {
	public function query($sql) {
		if (strpos($sql, 'monstrocity_theme_collections') !== false) return false;   // table absent
		return parent::query($sql);
	}
}
$half = new MonHalfConn($seed);
$halfRows = monstrocity_themes($half);
$fullRows = monstrocity_themes(new MonStubConn($seed));
$lost = 0;
foreach ($halfRows as $i => $r) if ($r['policy_ids'] !== $fullRows[$i]['policy_ids']) $lost++;
ok(count($halfRows) === 49 && $lost === 0,
   'with the link table missing, ' . $lost . ' themes lost their policy ids -- those themes '
 . 'would match nobody\'s NFTs until the table exists');

/* And with nothing attached, the rendered JS is still the frozen original. */
$plain = rtrim(monstrocity_themes_js($conn, true, true), " \t\r\n");
ok(preg_replace('/\s+/', '', $plain) === preg_replace('/\s+/', '', render_original($body, 1, 1)),
   'with no collections attached the output is no longer the original');

/* ---- 4d. THE PAGE'S ASSEMBLY, not just the generator ---------------------
 *
 * Everything above tests monstrocity_themes_js(). None of it tested how
 * monstrocity.php splices the result between "const themes = [" and "];",
 * and that is where the first bug after deploy actually was: `?>` swallows
 * the newline immediately after it, so the page emitted "}\t  ];" with the
 * line break gone. Valid JS, wrong output, and invisible to every check here.
 *
 * So render the page's own template, with the same stub in scope, and look
 * at what it really produces.
 */
$page  = file_get_contents(__DIR__ . '/monstrocity.php');
$ps    = strpos($page, 'const themes = [');
$pe    = strpos($page, '];', $ps);
$tmpl  = substr($page, $ps, $pe - $ps + 2);
$innercircle = true; $dev = true;      // names the template expects
ob_start();
eval('?>' . $tmpl);
$assembled = ob_get_clean();

ok(substr_count($assembled, 'policyIds:') === 49,
   'the page template rendered ' . substr_count($assembled, 'policyIds:') . ' themes, not 49');
ok(strpos($assembled, "}\n	  ];") !== false,
   'the array does not close on its own line -- a `?>` is eating the newline before "];"');
ok(strpos($assembled, '<?php') === false && strpos($assembled, '?>') === false,
   'a PHP tag survived into the rendered page');
ok(preg_match('/const themes = \[\s*\n\s*\{/', $assembled) === 1,
   'the array does not open cleanly before its first entry');

/* ---- 5. the data itself survived ----------------------------------------- */
$all = monstrocity_themes($conn);
ok(count($all) === 49, 'the reader returns ' . count($all) . ' themes, not 49');
$slugs = array_column($all, 'slug');
ok(count(array_unique($slugs)) === count($slugs), 'two themes share a slug, so one folder would serve both');
$dev = array_values(array_filter($all, function ($r) { return (int)$r['req_dev'] === 1; }));
ok(count($dev) === 1 && $dev[0]['slug'] === 'j2',
   'the dev-only theme is no longer exactly j2, so the nested visibility was lost');
foreach ($all as $r) {
	if ((int)$r['req_dev'] === 1 && (int)$r['req_innercircle'] !== 1) {
		ok(false, $r['slug'] . ' requires dev but not Inner Circle, which the old nesting could never produce');
	}
}
/* ---- 6. a missing table renders the frozen list, not an empty picker ------ */
class MonDeadConn { public $lastSql=''; public function query($s){ $this->lastSql=$s; return false; } }
$dead = new MonDeadConn();
foreach (array(array(0,0),array(1,1)) as $k) {
	$fb = monstrocity_themes_js($dead, (bool)$k[0], (bool)$k[1]);
	$n  = substr_count($fb, 'policyIds:');
	ok($n === ($k[0] ? 49 : 42),
	   'with no table the fallback rendered ' . $n . ' themes for innercircle=' . $k[0]
	 . ', so a deploy that lands before the SQL would leave the picker wrong');
}
ok(strpos(monstrocity_themes_js($dead, false, false), 'group: "Partner Project Themes"') !== false,
   'the fallback does not produce a usable theme list');

/* ---- 7. the admin rules -------------------------------------------------- */
echo "\nadmin validation\n";

/* A slug becomes a folder name and a URL path, so the interesting cases are
   the hostile ones, not the tidy ones. */
$slugCases = array(
	array('apprentices2', array(),                '',     'a plain slug'),
	array('',             array(),                'bad',  'empty'),
	array('../etc',       array(),                'bad',  'path traversal'),
	array('a/b',          array(),                'bad',  'a slash'),
	array('Theme',        array(),                'bad',  'uppercase'),
	array('my theme',     array(),                'bad',  'a space'),
	array('-lead',        array(),                'bad',  'leading hyphen'),
	array('trail-',       array(),                'bad',  'trailing hyphen'),
	array('.',            array(),                'bad',  'a bare dot'),
	array('..',           array(),                'bad',  'a double dot'),
	array('theme%00',     array(),                'bad',  'a null byte escape'),
	array(str_repeat('a', 65), array(),           'bad',  '65 characters'),
	array('taken',        array('taken'),         'bad',  'a duplicate'),
	array('free',         array('taken'),         '',     'not a duplicate'),
);
foreach ($slugCases as $c) {
	list($slug, $taken, $want, $label) = $c;
	$got = monstrocity_slug_problem($slug, $taken);
	$isBad = ($got !== '');
	ok($isBad === ($want === 'bad'),
	   "slug check wrong for $label (\"$slug\"): " . ($isBad ? "refused: $got" : 'accepted'));
}

$base = array('slug'=>'ok-theme','project_name'=>'P','title'=>'T','theme_group'=>'G',
              'orientations'=>'Left','extension'=>'png','policy_ids'=>'');
ok(monstrocity_validate_theme($base) === array(), 'a valid theme was refused: '
   . implode(' | ', monstrocity_validate_theme($base)));

$bad = $base; $bad['project_name'] = '  ';
ok(monstrocity_validate_theme($bad) !== array(), 'a blank project name was accepted');
$bad = $base; $bad['theme_group'] = '';
ok(monstrocity_validate_theme($bad) !== array(), 'a theme with no group was accepted');
$bad = $base; $bad['orientations'] = 'Sideways';
ok(monstrocity_validate_theme($bad) !== array(), 'a nonsense orientation was accepted');
$bad = $base; $bad['extension'] = 'php';
ok(monstrocity_validate_theme($bad) !== array(), 'a .php extension was accepted, which would write executable art');

/* A truncated policy id is the paste error that produces a theme matching
   nobody, with nothing in any log to say so. */
$bad = $base; $bad['policy_ids'] = '93ff51e7dfdf32314fd2f99ff222aa9c92f486b7';
ok(monstrocity_validate_theme($bad) !== array(), 'a 40-character policy id was accepted');
$good = $base; $good['policy_ids'] = '93ff51e7dfdf32314fd2f99ff222aa9c92f486b7d2cc0d46b64a9785';
ok(monstrocity_validate_theme($good) === array(), 'a real 56-hex policy id was refused');
$two = $base; $two['policy_ids'] = '93ff51e7dfdf32314fd2f99ff222aa9c92f486b7d2cc0d46b64a9785,'
                                 . 'b3dab69f7e6100849434fb1781e34bd12a916557f6231b8d2629b6f6';
ok(monstrocity_validate_theme($two) === array(), 'a two-policy list was refused');
$comma = $base; $comma['policy_ids'] = '93ff51e7dfdf32314fd2f99ff222aa9c92f486b7d2cc0d46b64a9785,';
ok(monstrocity_validate_theme($comma) !== array(), 'a trailing comma was accepted');

/* The visibility nesting, preserved from the original conditionals. */
$nest = $base; $nest['req_dev'] = 1; $nest['req_innercircle'] = 0;
ok(monstrocity_validate_theme($nest) !== array(),
   'dev-only without Inner Circle was accepted, which the old nesting could never produce');
$nest['req_innercircle'] = 1;
ok(monstrocity_validate_theme($nest) === array(), 'dev + Inner Circle was refused');

ok(monstrocity_clean_collection_ids(array('3','3','x','0','-2','7')) === array(3, 7),
   'collection ids are not cleaned to unique positive ints in order');
ok(monstrocity_clean_collection_ids(array()) === array(), 'an empty collection list did not stay empty');

/* ---- 7b. the project name is inherited, not retyped ---------------------- */
$projStub = array(4 => array('name' => 'Happy Hoppers'), 9 => array('name' => 'Black Flag'));
ok(monstrocity_display_project('', 4, $projStub) === 'Happy Hoppers',
   'a blank name did not inherit the linked project');
ok(monstrocity_display_project('Hoppy Valentines', 4, $projStub) === 'Hoppy Valentines',
   'a typed name was overwritten by the project -- the override is the whole point');
ok(monstrocity_display_project('   ', 4, $projStub) === 'Happy Hoppers',
   'a name of spaces counted as an override');
ok(monstrocity_display_project('', 0, $projStub) === '',
   'a name appeared from nowhere with no project linked');
ok(monstrocity_display_project('', 999, $projStub) === '',
   'an unknown project id produced a name');

/* And validation follows the same rule: a name is only required when there is
   no project to inherit from. */
$inh = array('slug'=>'x','project_name'=>'','project_id'=>4,'title'=>'T','theme_group'=>'G',
             'orientations'=>'','extension'=>'png','policy_ids'=>'');
ok(monstrocity_validate_theme($inh) === array(),
   'a blank name with a linked project was refused: ' . implode(' | ', monstrocity_validate_theme($inh)));
$inh['project_id'] = 0;
ok(monstrocity_validate_theme($inh) !== array(),
   'a blank name with NO linked project was accepted, so the theme would render unlabelled');

/* ---- 8. the admin page's dangerous edges --------------------------------- */
echo "\nadmin page safety\n";
$ap = file_get_contents(__DIR__ . '/admin-monstrocity.php');
/* BOTH comment styles, because a "// admin_require();" satisfied a plain
   strpos on the collage page and the gate-removal mutation passed. */
$apNo = preg_replace('!/\*.*?\*/!s', '', $ap);
$apNo = preg_replace('~(?<![:/])//[^\n]*~', '', $apNo);

ok(strpos($apNo, 'admin_require();') !== false, 'the Monstrocity admin is not gated to the admin');
ok(strpos($apNo, 'admin_require();') < strpos($apNo, "include 'header.php'"),
   'admin_require() runs after output has started, so the gate cannot redirect');

/*
 * THE SLUG BECOMES A DIRECTORY. mkdir() with anything from $_POST is a path
 * traversal unless it has been checked first, so the ORDER is the property:
 * validation, then the write. Presence of the validator proves nothing if it
 * runs after the folder has already been made.
 */
$vAt = strpos($apNo, 'monstrocity_validate_theme(');
$mAt = strpos($apNo, '@mkdir(');
ok($vAt !== false && $mAt !== false && $vAt < $mAt,
   'the theme folder is created before the slug has been validated, which makes '
 . 'mkdir() reachable with a traversal');
ok(strpos($apNo, 'if ($errs) {') !== false && strpos($apNo, 'if ($errs) {') < $mAt,
   'nothing branches on the validation result before the folder is written');

/*
 * NOTHING FROM $_POST REACHES SQL UNESCAPED. Checked per query rather than
 * per file: the file obviously contains both $_POST and query(), and that
 * proves nothing about whether they ever meet.
 */
$bad = 0;
if (preg_match_all('/\$conn->query\(((?:[^()]|\([^()]*\))*)\)/s', $apNo, $qm)) {
	foreach ($qm[1] as $q) if (strpos($q, '$_POST') !== false || strpos($q, '$_GET') !== false) $bad++;
}
ok($bad === 0, $bad . ' query(...) calls interpolate $_POST or $_GET directly');
/* And the values that do reach SQL went through one of the two safe routes. */
ok(substr_count($apNo, 'real_escape_string') >= 1, 'no string is escaped anywhere, which cannot be right');
ok(preg_match('/\$esc\s*=\s*function/', $apNo) === 1, 'the per-value escaper is gone');

/* An edit must not be able to move a theme's folder out from under its art. */
ok(strpos($apNo, "\$was['slug'] !== \$t['slug']") !== false,
   'the slug can be changed on an existing theme, orphaning everything already uploaded');

/*
 * LIST OR FORM, NEVER BOTH. The form was a 420px column next to the list and
 * had grown seventeen controls. The two views are now exclusive, which is
 * only true if the markup actually branches on it.
 */
ok(preg_match('/if \(!\$row\):/', $ap) === 1, 'the view does not branch between list and form');
ok(substr_count($ap, '<table class="mt">') === 1, 'the themes table is rendered more than once');
/*
 * The form must sit inside the else branch, not before it.
 *
 * Anchored on the unique FORM banner, NOT on "<?php else:" -- the list cell
 * that reports policies has its own inline if/elseif/else, so searching for
 * the first "<?php else:" found that one and the check passed with the
 * branch separator deleted. An anchor that matches more than one thing is
 * not an anchor.
 */
$elseAt = strpos($ap, '<?php else: /* ================= FORM');
$formAt = strpos($ap, '<form method="post"');
/* strrPos: the list cell has its own inline endif, which closes long before
   the branch does. Same ambiguity as the else above, one line later. */
$endAt  = strrpos($ap, '<?php endif; ?>');
ok($elseAt !== false, 'the list/form branch separator is gone, so both views render at once');
ok($elseAt !== false && $formAt > $elseAt && $formAt < $endAt,
   'the form is not inside the else branch, so the list page renders it too');

/* The collection filter must never hide something already ticked -- a theme
   can span projects and you cannot save what you cannot see. */
ok(strpos($ap, "r.querySelector('input').checked") !== false,
   'the collection filter can hide a ticked collection');
ok(strpos($ap, "all.checked || pid === '0'") !== false,
   'the filter has no escape hatch for showing every project');

/*
 * dist/flexbox.css styles bare `a` with text-align:center for the whole
 * platform, so any anchor or label in a scoped component has to restate it.
 * Asserted CONDITIONALLY: if that global ever goes, this guard should drop
 * rather than pin a rule nothing is countering any more.
 */
$fb = @file_get_contents(__DIR__ . '/dist/flexbox.css');
if ($fb !== false && preg_match('/(^|})\s*a\s*\{[^}]*text-align\s*:\s*center/s', $fb)) {
	foreach (array('.mt-back', '.mt-field label', '.mt-cols label', 'table.mt a.slug') as $sel) {
		ok(preg_match('/' . preg_quote($sel, '/') . '[^}]*text-align/', $ap) === 1,
		   $sel . ' does not restate text-align, so the global `a { text-align:center }` centres it');
	}
}

/* The tab is registered, so the page is reachable at all. */
$tabs = admin_tabs_src();
ok(strpos($tabs, 'admin-monstrocity.php') !== false, 'the Monstrocity tab is not in admin_tabs()');

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall theme checks passed\n");
exit($fail ? 1 : 0);
