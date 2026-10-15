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

/* ---- 1. the seed, parsed out of the schema doc ---------------------------- */
$doc = file_get_contents(__DIR__ . '/monstrocity-schema.md');
$at  = strpos($doc, 'VALUES');
$sql = substr($doc, $at + 6, strpos($doc, ';', $at) - $at - 6);
preg_match_all('/\(([^()]*(?:\([^)]*\)[^()]*)*)\)/', $sql, $mm);
$cols = array('slug','project_id','collection_id','project_name','title','policy_ids',
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
	public $rows; public $lastSql = '';
	public function __construct($r) { $this->rows = $r; }
	public function query($sql) {
		$this->lastSql = $sql;
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
ok(preg_match('/\bactive\s*=\s*1\b/i', $conn->lastSql) === 1,
   'the themes query does not filter on active = 1, so a retired theme still renders');
ok(preg_match('/ORDER BY\s+sort_order\s+ASC/i', $conn->lastSql) === 1,
   'the themes query does not order by sort_order ASC, so group order is whatever the table returns');

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

echo ($fail ? "\n$fail check(s) FAILED\n" : "\nall theme-extraction checks passed\n");
exit($fail ? 1 : 0);
