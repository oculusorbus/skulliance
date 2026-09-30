<?php
/* dropdowns-harness.php — CLI only. Static. No database.
 *
 * EVERY DROPDOWN ON THE PLATFORM IS SQUARE. The site squared its panels,
 * buttons and cards page by page and left the <select>s rounded behind
 * them, so on most screens the one round thing left was a dropdown.
 *
 * This sweeps dist/flexbox.css and every page's own <style> for a rule
 * that targets a select and gives it a radius. It is a SWEEP rather than a
 * list of known rules on purpose: the point is that a new page cannot
 * quietly introduce a rounded one.
 *
 * WHAT COUNTS AS TARGETING A SELECT: the bare tag, .dropdown, or a
 * class/id whose name ends in -select or starts with #filter. Buttons and
 * containers that merely have "select" in the NAME -- #theme-select-button,
 * #character-select-container, #select-boss-button -- are not dropdowns and
 * are left alone; they are listed explicitly so the exclusion is a decision
 * rather than a regex accident.
 *
 * Usage: php dropdowns-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

/* Named, not pattern-matched: each is a button or a panel, not a dropdown. */
$NOT_DROPDOWNS = array(
	'#theme-select-button', '#select-boss-button', '#theme-select-container',
	'#character-select-container', '#boss-select-container', '#rg-track',
	'.podium-section', '#theme-select',   /* dead rule: no such element exists */
);

function targets_a_select($sel) {
	global $NOT_DROPDOWNS;
	foreach (explode(',', $sel) as $one) {
		$one = trim(preg_replace('/\s+/', ' ', $one));
		if ($one === '') continue;
		$skip = false;
		foreach ($NOT_DROPDOWNS as $n) { if (strpos($one, $n) !== false) { $skip = true; break; } }
		if ($skip) continue;
		if (preg_match('/(^|[\s>+~])select\b/', $one)) return $one;
		if (preg_match('/\.dropdown\b/', $one))        return $one;
		if (preg_match('/[.#][A-Za-z0-9_-]*-select\b/', $one)) return $one;
		if (preg_match('/#filter[A-Za-z]*\b/', $one))  return $one;
	}
	return '';
}

$sheets = array(array('dist/flexbox.css', file_get_contents(__DIR__ . '/dist/flexbox.css')));
foreach (glob(__DIR__ . '/*.php') as $f) {
	$src = file_get_contents($f);
	if (preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $src, $m)) {
		foreach ($m[1] as $block) $sheets[] = array(basename($f), $block);
	}
}

echo "sweeping for rounded dropdowns\n";
printf("  %d stylesheets / inline blocks\n", count($sheets));
$checked = 0; $round = array();
foreach ($sheets as $sheet) {
	list($name, $css) = $sheet;
	$css = preg_replace('~/\*.*?\*/~s', '', $css);           /* a comment is not a rule */
	if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $rules, PREG_SET_ORDER)) continue;
	foreach ($rules as $r) {
		if (strpos($r[2], 'border-radius') === false) continue;
		$hit = targets_a_select($r[1]);
		if ($hit === '') continue;
		$checked++;
		preg_match('/border-radius:\s*([^;}]+)/', $r[2], $v);
		$val = trim(str_replace('!important', '', $v[1]));
		if ($val !== '0' && $val !== '0px') $round[] = "$name: $hit -> $val";
	}
}
printf("  %d rules target a dropdown and set a radius\n", $checked);
ok($checked > 5, 'the sweep found almost nothing, so it is not actually reading the sheets');
foreach ($round as $r) ok(false, "a dropdown is still rounded -- $r");
ok(empty($round), count($round) . ' rounded dropdown rule(s) above');

/* And the baseline, for a select with no rule of its own. */
$flex = file_get_contents(__DIR__ . '/dist/flexbox.css');
ok(preg_match('/^select \{ border-radius: 0; \}/m', $flex) === 1,
   'flexbox.css has no bare `select` baseline, so a dropdown on a page that '
 . 'never styled one goes back to the browser default');

echo "\n";
if ($fail) { echo "$fail check(s) FAILED\n"; exit(1); }
echo "every dropdown is square\n";
