<?php
/* realms-harness.php — CLI only. Static. No database.
 *
 * TWO FAILURES I SHIPPED IN ONE AFTERNOON, both from the same move: pulling
 * the locations panel out of realms.php into a partial. The panel was
 * assigning variables at global scope that the REST of the page read
 * hundreds of lines later, and taking it away took them.
 *
 *   $realm_id   the right column read it for the realm image, the Theme
 *               dropdown and the Faction select. Undefined, the theme fell
 *               through to a hardcoded default and looked like the saved
 *               theme had changed.
 *
 *   $levels     read at $levels[1] INSIDE the big <script> block. The
 *               warning printed into the middle of the JavaScript, which is
 *               a syntax error, which stops the WHOLE block -- 1,500 lines
 *               of it, including the quick menu and the panel switcher. The
 *               page rendered every panel at once.
 *
 * AND IT IS INVISIBLE FROM THE PAGE. A warning inside a <script> does not
 * appear in document.body.innerText, so the browser reported zero warnings
 * while the page was thoroughly broken. That is why this is a file and not
 * a habit.
 *
 * A third one in the same afternoon: reading getLocationInfo($conn) at the
 * point of use, which is ~800 lines after $conn->close(). Same result -- a
 * PHP error inside the script block.
 *
 * Usage: php realms-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$file = __DIR__ . '/realms.php';
$src  = file_get_contents($file);
$toks = token_get_all($src);

/*
 * Variables that arrive from somewhere else: db.php, skulliance.php,
 * header.php, the partials, and PHP itself. Everything NOT in here has to be
 * assigned in realms.php before it is read.
 */
$external = array(
	'this', 'GLOBALS', '_SESSION', '_POST', '_GET', '_SERVER', '_COOKIE', '_FILES', '_ENV',
	'conn',               // db.php
	'points_multiplier',  // skulliance.php
	'name', 'avatar_url', 'title', 'url', 'user', 'id', 'status', 'member', // header.php
	'rl_panel', 'rl_guide', 'rl_cons', 'rl_prev', 'rl_manage', 'rl_can_manage',
	'rl_e', 'r', 'q', 'ef', 'bits', 'n', 'cid', 'cname', 'qty', 'cls', 'tip',
	'act', 'on', 'manage', 'loc_id', 'loc',   // the partial's own locals
);

/* ---------- 1. nothing is read before it is written ------------------------ */
echo "variables the page reads\n";
$assigned = array();
$read     = array();
$n = count($toks);
for ($i = 0; $i < $n; $i++) {
	if (!is_array($toks[$i]) || $toks[$i][0] !== T_VARIABLE) continue;
	$var  = ltrim($toks[$i][1], '$');
	$line = $toks[$i][2];
	/* Assignment, foreach target, or a by-reference/compound write. */
	$j = $i + 1;
	while ($j < $n && is_array($toks[$j]) && $toks[$j][0] === T_WHITESPACE) $j++;
	$next = $j < $n ? (is_array($toks[$j]) ? $toks[$j][0] : $toks[$j]) : null;
	$isWrite = ($next === '=' || $next === T_PLUS_EQUAL || $next === T_CONCAT_EQUAL
	         || $next === T_MINUS_EQUAL);
	/* foreach (... as $k => $v) */
	$k = $i - 1;
	while ($k >= 0 && is_array($toks[$k]) && $toks[$k][0] === T_WHITESPACE) $k--;
	$prev = $k >= 0 ? (is_array($toks[$k]) ? $toks[$k][0] : $toks[$k]) : null;
	if ($prev === T_AS || $prev === T_DOUBLE_ARROW) $isWrite = true;

	if ($isWrite) { if (!isset($assigned[$var])) $assigned[$var] = $line; }
	else          { if (!isset($read[$var]))     $read[$var]     = $line; }
}
$orphans = array();
foreach ($read as $var => $line) {
	if (in_array($var, $external, true)) continue;
	if (!isset($assigned[$var])) $orphans[$var] = $line;
}
printf("  %d assigned, %d read, %d read but never assigned\n",
	count($assigned), count($read), count($orphans));
foreach ($orphans as $var => $line) echo "    \$$var first read at line $line\n";
ok(!$orphans,
   'realms.php reads ' . implode(', ', array_map(function ($v) { return '$' . $v; },
     array_keys($orphans)))
 . ' and never assigns it. If that read sits inside the <script> block the PHP '
 . 'warning becomes a JS syntax error and the entire block stops running.');

/* Read before written, in source order, is the same bug with a definition. */
$late = array();
foreach ($read as $var => $rline) {
	if (in_array($var, $external, true)) continue;
	if (isset($assigned[$var]) && $assigned[$var] > $rline) $late[$var] = "$rline before $assigned[$var]";
}
ok(!$late, 'read before it is assigned: ' . json_encode($late));

/* ---------- 2. nothing queries a closed connection ------------------------- */
echo "\nthe connection\n";
$closeAt = strpos($src, '$conn->close()');
ok($closeAt !== false, 'realms.php never closes its connection');
$closeLine = substr_count(substr($src, 0, $closeAt), "\n") + 1;
$after = substr($src, $closeAt + 14);
/* Any db.php helper takes $conn as its first argument, so that is the shape
   to look for rather than a list of function names that will go stale. */
preg_match_all('/\b([a-zA-Z_][a-zA-Z0-9_]*)\s*\(\s*\$conn\b/', $after, $um, PREG_OFFSET_CAPTURE);
$uses = array();
foreach ($um[1] as $u) {
	$line = $closeLine + substr_count(substr($after, 0, $u[1]), "\n");
	$uses[] = $u[0] . '() at line ~' . $line;
}
printf("  closes at line %d; %d call(s) use \$conn after that\n", $closeLine, count($uses));
foreach ($uses as $u) echo "    $u\n";
ok(!$uses,
   'realms.php uses $conn after closing it: ' . implode(', ', $uses)
 . '. The error prints wherever the call sits -- inside the <script> block that '
 . 'is a syntax error and the whole block dies.');

/* ---------- 3. the panel is included, not rebuilt -------------------------- */
echo "\nstructure\n";
ok(strpos($src, "include __DIR__ . '/realms-locations.php'") !== false,
   'realms.php no longer includes the shared panel partial');
$styleAt  = strpos($src, "\n<style>");
$markupAt = strpos($src, '<div class="row" id="row0">');
printf("  <style> at byte %d, first markup at byte %d\n", $styleAt, $markupAt);
ok($styleAt !== false && $markupAt !== false && $styleAt < $markupAt,
   'the page stylesheet is below the markup again -- the panel paints unstyled '
 . 'first and the item icons, which have no width of their own, fill the screen '
 . 'until it arrives');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all realms page checks passed\n");
exit($fail ? 1 : 0);
