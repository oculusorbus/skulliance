<?php
/* ui-selectors.php — CLI only. Does a redesign orphan a JavaScript hook?
 *
 *     php ui-selectors.php --snapshot my-nfts.php > /tmp/before.json
 *     ...edit the page...
 *     php ui-selectors.php --check /tmp/before.json my-nfts.php
 *
 * WHY THIS AND NOT A RENDER TEST. The platform has 111 pages, 1,226 DOM
 * lookups inside PHP files and 771 distinct selectors, and no UI test
 * coverage at all. Rendering a page needs the database, so a render test
 * cannot run here and cannot run in a hook. What CAN be checked statically
 * is the one failure a redesign actually causes: an id or class that JS
 * reaches for stops being emitted, and nothing says so until a visitor
 * clicks the thing.
 *
 * DIFF-BASED, DELIBERATELY. Checking a page against every selector in the
 * repo drowns in noise -- skulliance.js is global and reaches for hooks
 * belonging to forty other pages. Comparing a page against ITSELF before
 * the edit has no false positives: it reports exactly what this change
 * removed, and only where something still reaches for it.
 *
 * THE 140 CONCATENATED SELECTORS ARE THE POINT. getElementById('p' + n +
 * '-image') never appears in the source as a literal, so a plain grep
 * cannot protect it. Those are collected as PREFIXES and a removed id is
 * flagged when it starts with one.
 *
 * It reads files. It writes nothing, touches no database, and is safe to
 * run on anything.
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

/** Every id and class this file EMITS as markup, including from echoes. */
function ui_defined($path) {
	$src = @file_get_contents($path);
	if ($src === false) return array('ids' => array(), 'classes' => array());
	$ids = $classes = array();
	/* id="x" and id='x', in plain markup and inside a PHP echo alike. */
	if (preg_match_all('/\bid\s*=\s*[\'"]([^\'"$<>]+)[\'"]/i', $src, $m))
		foreach ($m[1] as $v) if (trim($v) !== '') $ids[trim($v)] = 1;
	if (preg_match_all('/\bclass\s*=\s*[\'"]([^\'"$<>]+)[\'"]/i', $src, $m))
		foreach ($m[1] as $v) foreach (preg_split('/\s+/', trim($v)) as $c)
			if ($c !== '') $classes[$c] = 1;
	/* classList.add('x') counts as emitting it: JS elsewhere may select it. */
	if (preg_match_all('/classList\.(?:add|toggle)\(\s*[\'"]([^\'"]+)[\'"]/', $src, $m))
		foreach ($m[1] as $v) $classes[$v] = 1;
	ksort($ids); ksort($classes);
	return array('ids' => array_keys($ids), 'classes' => array_keys($classes));
}

/** Every selector any JS in the repo reaches for, plus concatenation prefixes. */
function ui_referenced() {
	static $out = null;
	if ($out !== null) return $out;
	$ids = $classes = $prefixes = array();
	$files = array_merge(glob(__DIR__ . '/*.php'), glob(__DIR__ . '/*.js'),
	                     glob(__DIR__ . '/js/*.js'), glob(__DIR__ . '/ajax/*.php'));
	foreach ($files as $f) {
		$src = @file_get_contents($f);
		if ($src === false) continue;
		if (preg_match_all('/getElementById\(\s*[\'"]([^\'"]+)[\'"]/', $src, $m))
			foreach ($m[1] as $v) $ids[$v] = 1;
		/* The concatenated form: getElementById('consumable-' + id) */
		if (preg_match_all('/getElementById\(\s*[\'"]([^\'"]+)[\'"]\s*\+/', $src, $m))
			foreach ($m[1] as $v) if (strlen($v) >= 2) $prefixes[$v] = 1;
		if (preg_match_all('/getElementsByClassName\(\s*[\'"]([^\'"]+)[\'"]/', $src, $m))
			foreach ($m[1] as $v) $classes[$v] = 1;
		if (preg_match_all('/querySelector(?:All)?\(\s*[\'"]([^\'"]+)[\'"]/', $src, $m))
			foreach ($m[1] as $sel) {
				if (preg_match_all('/#([A-Za-z0-9_-]+)/', $sel, $i)) foreach ($i[1] as $v) $ids[$v] = 1;
				if (preg_match_all('/\.([A-Za-z0-9_-]+)/', $sel, $c)) foreach ($c[1] as $v) $classes[$v] = 1;
			}
	}
	$out = array('ids' => $ids, 'classes' => $classes, 'prefixes' => array_keys($prefixes));
	return $out;
}

$mode = isset($argv[1]) ? $argv[1] : '';

if ($mode === '--snapshot' && isset($argv[2])) {
	$snap = array();
	foreach (array_slice($argv, 2) as $f) $snap[$f] = ui_defined($f);
	echo json_encode($snap, JSON_PRETTY_PRINT), "\n";
	exit(0);
}

if ($mode === '--check' && isset($argv[2])) {
	$before = json_decode(@file_get_contents($argv[2]), true);
	if (!is_array($before)) { echo "Cannot read snapshot {$argv[2]}\n"; exit(1); }
	$ref = ui_referenced();
	$problems = 0; $removed_total = 0;

	foreach ($before as $file => $was) {
		$now = ui_defined($file);
		foreach (array('ids', 'classes') as $kind) {
			$gone = array_diff($was[$kind], $now[$kind]);
			foreach ($gone as $name) {
				$removed_total++;
				$hit = isset($ref[$kind][$name]);
				$why = $hit ? 'referenced by JS' : '';
				if (!$hit && $kind === 'ids') {
					foreach ($ref['prefixes'] as $p) {
						if (strncmp($name, $p, strlen($p)) === 0) {
							$hit = true; $why = "matches concatenated prefix '{$p}…'"; break;
						}
					}
				}
				if ($hit) {
					$problems++;
					printf("  BROKEN  %s removed %s \"%s\" -- %s\n", $file, rtrim($kind, 's'), $name, $why);
				}
			}
		}
	}
	printf("\n%d selector(s) removed, %d of them still reached for by JavaScript.\n",
		$removed_total, $problems);
	echo $problems ? "FAIL\n" : "OK -- nothing orphaned.\n";
	exit($problems ? 1 : 0);
}

echo "usage:\n";
echo "  php ui-selectors.php --snapshot <file...> > before.json\n";
echo "  php ui-selectors.php --check before.json\n";
exit(1);
