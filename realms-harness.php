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

/** Brace-match a function out of a source string. */
function extract_fn_db($src, $name) {
	$at = strpos($src, 'function ' . $name . '(');
	if ($at === false) return '';
	$open = strpos($src, '{', $at);
	$d = 0;
	for ($i = $open, $n = strlen($src); $i < $n; $i++) {
		if ($src[$i] === '{') $d++;
		elseif ($src[$i] === '}') { $d--; if ($d === 0) return substr($src, $at, $i - $at + 1); }
	}
	return '';
}

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

/* ---------- 4. the switcher cannot reach for an element that is not there -- */
/*
 * toggleSections() walks a list of panel ids and touches each one's
 * .style and its matching -icon, unguarded. Delete a panel -- as the raid
 * and faction stats panel just was -- and getElementById returns null,
 * .style throws, and the whole <script> block stops. That block is 1,500
 * lines and holds the quick menu, so the symptom is the entire page
 * rendering as one long dump. It has happened twice for other reasons; this
 * closes the third door.
 */
echo "\nthe panel switcher\n";
preg_match("/var sections = \[([^\]]*)\]/", $src, $sm);
ok(!empty($sm[1]), 'could not find the sections list in toggleSections()');
preg_match_all("/'([a-z]+)'/", $sm[1], $names);
$sections = $names[1];
printf("  sections: %s\n", implode(', ', $sections));
foreach ($sections as $sec) {
	ok(preg_match('/id="' . $sec . '"|id=\'' . $sec . '\'/', $src) === 1,
	   "toggleSections() lists '$sec' but nothing renders id=\"$sec\" -- "
	 . 'getElementById returns null, .style throws, and the whole script block dies');
	ok(preg_match('/id="' . $sec . '-icon"/', $src) === 1,
	   "no quick-menu icon with id=\"$sec-icon\" for section '$sec' -- same crash");
}
/* And the reverse: an icon wired to a section the list does not know about
   would silently do nothing. */
preg_match_all('/id="([a-z]+)-icon"/', $src, $im);
foreach (array_unique($im[1]) as $icon) {
	ok(in_array($icon, $sections, true),
	   "there is a '$icon-icon' in the quick menu that toggleSections() never "
	 . 'hides or selects');
}
/* Every other direct touch of a panel id outside that loop. */
preg_match_all("/getElementById\('([a-z-]+)'\)\.style/", $src, $gm);
$missing = array();
foreach (array_unique($gm[1]) as $id) {
	if (!preg_match('/id="' . preg_quote($id, '/') . '"|id=\'' . preg_quote($id, '/') . '\'/', $src)
	    && strpos($src, "id='" . $id . "'") === false) $missing[$id] = true;
}
/* back-to-top-button lives in header.php, not here. */
unset($missing['back-to-top-button']);
ok(!$missing, 'the script sets .style on ids this page never renders: '
            . implode(', ', array_keys($missing)));

/* ---------- 5. the loader ---------------------------------------------------- */
echo "\nthe loader\n";
$loaderAt  = strpos($src, 'id="rl-loader"');
$flushAt   = strpos($src, '@flush();');
$markupAt2 = strpos($src, '<div class="row" id="row0">');
ok($loaderAt !== false, 'the full-screen loader is gone');
ok($flushAt !== false && $loaderAt < $flushAt && $flushAt < $markupAt2,
   'the loader is not flushed before the page does its work -- it would arrive '
 . 'with everything else and show nothing');
ok(strpos($src, "getElementById('rl-loader')") !== false,
   'nothing dismisses the loader, so the page would stay behind it');

/* ---------- 6. the identity panel ------------------------------------------ */
/*
 * Swapping this block in is what swallowed the whole #raids row a moment
 * ago -- caught by the switcher check above, which is the third time that
 * check has earned itself. These pin the pieces the panel needs.
 */
echo "\nthe identity panel\n";
ok(strpos($src, "include __DIR__ . '/realms-identity.php'") !== false,
   'realms.php no longer includes the identity partial');
$ident = file_get_contents(__DIR__ . '/realms-identity.php');
foreach (array('id="realmName"', 'id="filterNFTs"', 'id="faction"', 'id="ri-image"', 'id="ri-msg"')
         as $hook) {
	ok(strpos($ident, $hook) !== false, "the identity panel lost $hook");
}
ok(strpos($src, 'function setRealmIdentity') !== false,
   'setRealmIdentity() is gone, so both dropdowns would do nothing');
ok(strpos($ident, 'setRealmIdentity(') !== false,
   'the dropdowns are not wired to setRealmIdentity()');
/* editRealmName() lives in skulliance.js and writes into #realmName. */
ok(strpos(file_get_contents(__DIR__ . '/skulliance.js'), "getElementById('realmName')") !== false
   || strpos($ident, 'editRealmName') !== false,
   'nothing renames the realm any more');
/* The old full-page submit must not come back. */
ok(strpos($ident, 'factionsForm') === false && strpos($ident, '.submit()') === false,
   'the identity panel submits a form again -- that reloads the whole page to '
 . 'change one dropdown, which is what this replaced');

/* ---------- 6b. the refresh renders the same panel as the page ------------- */
/*
 * ajax/get-locations.php replaces the WHOLE locations panel, header and
 * all. Any flag it passes the partial differently from realms.php is a
 * control that exists on load and disappears the first time anything
 * refreshes the list -- which is what happened to the Guide button: set
 * false here on the mistaken theory that it sat outside the fragment, it
 * vanished on the first stock, equip or upgrade.
 */
echo "\nthe panel refresh\n";
$refresh = file_get_contents(__DIR__ . '/ajax/get-locations.php');
/* Only the flags the PARTIAL reads from its caller: anything it uses but
   never assigns itself. That keeps loop variables in either file -- the CSS
   generator's $rl_cid, the partial's own $rl_spare -- out of the comparison. */
$partialSrc = file_get_contents(__DIR__ . '/realms-locations.php');
preg_match_all('/\$rl_([a-z_]+)/', $partialSrc, $used);
preg_match_all('/\$rl_([a-z_]+)\s*=/', $partialSrc, $setInPartial);
/* `$rl_x = isset($rl_x) ? $rl_x : default;` is not the partial owning that
   variable -- it is the partial declaring that the CALLER supplies it and
   giving a fallback. Treating it as an assignment is how $rl_guide fell out
   of this comparison, which is the very flag that went wrong. */
preg_match_all('/\$rl_([a-z_]+)\s*=\s*isset\(\$rl_\1\)/', $partialSrc, $defaulted);
$owned = array_diff(array_unique($setInPartial[1]), array_unique($defaulted[1]));
$contract = array_values(array_diff(array_unique($used[1]), $owned));
printf("  the partial expects from its caller: %s\n", implode(', ', $contract));
ok(!empty($contract), 'the partial appears to take nothing from its caller, which cannot be right');

preg_match_all('/\$rl_([a-z_]+)\s*=\s*([^;]+);/', $src, $pageFlags, PREG_SET_ORDER);
preg_match_all('/\$rl_([a-z_]+)\s*=\s*([^;]+);/', $refresh, $refFlags, PREG_SET_ORDER);
$pf = array(); foreach ($pageFlags as $m) $pf[$m[1]] = trim($m[2]);
$rf = array(); foreach ($refFlags as $m) $rf[$m[1]] = trim($m[2]);
foreach ($pf as $k => $v) {
	if (!in_array($k, $contract, true)) continue;
	if ($k === 'panel') continue;                 // the data itself, built the same way
	if (!isset($rf[$k])) { ok(false, "realms.php sets \$rl_$k and the refresh does not"); continue; }
	printf("  \$rl_%-8s page=%-28s refresh=%s\n", $k, $v, $rf[$k]);
	ok($rf[$k] === $v,
	   "\$rl_$k is '$v' on the page and '{$rf[$k]}' on the refresh -- whatever that "
	 . 'controls will be there on load and gone the moment the panel reloads');
}

/* ---------- 6c. the realm panel has one copy too --------------------------- */
/*
 * ajax/get-realm.php re-renders the realm column, and toggleSections() swaps
 * it in every time the desktop layout shows Locations. It carried its own
 * copy of the OLD panel -- heading outside .content, "Theme:" labels, the
 * hidden form that reloaded the page -- so the rebuilt panel was replaced by
 * the old one on the first toggle and was never actually seen on a phone or
 * a desktop. The same drift as ajax/get-locations.php, found the same way:
 * by the owner screenshotting markup that no longer exists in the source.
 */
echo "\nthe realm panel refresh\n";
$grealm = file_get_contents(__DIR__ . '/ajax/get-realm.php');
ok(strpos($grealm, "realms-identity.php") !== false,
   'ajax/get-realm.php does not include the identity partial -- it is rendering '
 . 'its own copy of the realm panel, which will silently replace the real one');
/* MATCHED AS MARKUP, not as words. The first version of this check looked
   for the bare string 'filterNFTsForm' and found it in this file's own
   comment explaining what had been removed -- the same self-reference that
   caught out daily-reward-harness.php. */
foreach (array('<label for="filterNFTs"', 'id="filterNFTsForm"', 'id="factionsForm"')
         as $stale) {
	ok(strpos($grealm, $stale) === false,
	   "ajax/get-realm.php still renders '$stale' from the old panel");
}
printf("  get-realm.php is %d bytes and includes the partial: %s\n",
	strlen($grealm), strpos($grealm, 'realms-identity.php') !== false ? 'yes' : 'NO');

/* ---------- 7. the icons are fetched once each ----------------------------- */
/*
 * The seven item icons are drawn in the inventory strip and on all seven
 * locations. As <img> that was 56 requests for 7 files, and on a phone a
 * different one failed to arrive on every load. They are CSS backgrounds
 * now: one request each, and a miss leaves an empty slot rather than a
 * broken-image glyph.
 */
echo "\nitem icons\n";
$panel = file_get_contents(__DIR__ . '/realms-locations.php');
$imgs  = substr_count($panel, 'src="icons/');
printf("  <img src=\"icons/...\"> in the panel: %d\n", $imgs);
ok($imgs <= 1, "the panel renders $imgs icon <img> tags; the seven item icons should be "
             . 'CSS backgrounds so each file is fetched once, not once per slot');
preg_match_all('/\.rl-ico-(\d+)\s*\{\s*background-image/', $src, $icm);
printf("  .rl-ico-N background rules: %d\n", count($icm[1]));
ok(count($icm[1]) === 0 && strpos($src, '.rl-ico-<?php') !== false
   || count($icm[1]) >= 7,
   'the .rl-ico-N background rules are missing, so every slot would be blank');

/* ---------- 8. the icons are preloaded, and the bar clears the notch ------- */
/*
 * A background-image is fetched at LOWER priority than an in-viewport <img>,
 * and this page carries 162 images with 52 of them eager. Moving the seven
 * item icons to backgrounds cut 56 requests to 7 and then let them lose the
 * race anyway -- same missing-icon symptom, different cause. The preloads
 * are what put them back in front, so losing them silently reintroduces it.
 */
echo "\nicon priority and safe areas\n";
preg_match_all('/rel="preload" as="image"/', $src, $pl);
$genPreload = strpos($src, "foreach (realm_con_names() as") !== false
           && strpos($src, 'rel="preload" as="image"') !== false;
ok($genPreload, 'the item icons are no longer preloaded -- as CSS backgrounds they '
              . 'queue behind every eager <img> on a 162-image page, which is exactly '
              . 'the intermittent missing icon that was reported twice');
ok(strpos($src, '#quick-menu') !== false && strpos($src, 'safe-area-inset-bottom') !== false,
   'the fixed quick-menu has no bottom safe-area inset -- on an iOS PWA its lower '
 . 'edge sits under the home indicator');
/* THE BAR MUST HUG THE BOTTOM IN STANDALONE. flexbox.css used to lift it by
   the inset plus 10px and paint a body::after strip to mask the gap; that
   lift is invisible in desktop Chrome and was reported three times as the
   bar sitting high with dead space under it. */
$flex = file_get_contents(__DIR__ . '/dist/flexbox.css');
ok(!preg_match('/#quick-menu\s*\{[^}]*bottom:\s*calc\(env\(safe-area-inset-bottom[^}]*\+\s*10px/', $flex),
   'flexbox.css lifts #quick-menu off the bottom again in standalone -- that is the '
 . 'dead band under the bar, and it does not reproduce outside a PWA');
ok(strpos($flex, 'body:has(#quick-menu[style*="block"])::after') === false,
   'the body::after masking strip is back; it exists only to hide the gap under a '
 . 'lifted bar, and the bar is not lifted any more');
ok(strpos($src, 'bottom: 0 !important') !== false,
   'realms no longer pins its own bar to the bottom');
ok(strpos($src, 'padding: 0 8px !important') !== false,
   'realms is not zeroing the page-level bar padding, so the global inset padding '
 . 'and this page\'s inset height will stack into a double gap');
$insets = substr_count($src, 'safe-area-inset-bottom');
printf("  safe-area-inset-bottom used %d time(s); preload generated from realm_con_names(): %s\n",
	$insets, $genPreload ? 'yes' : 'no');
ok($insets >= 2, 'the panels also need the inset, or their last row hides behind the bar');
/* The bar must not be able to change height as its art decodes -- 512x512
   source images sized only by CSS percentage made the bar reflow, which is
   how a position:fixed element appears to move between panels. */
preg_match_all('/<img width="\d+" height="\d+" id="[a-z]+-icon"/', $src, $qi);
printf("  quick-menu icons with explicit dimensions: %d of 5\n", count($qi[0]));
ok(count($qi[0]) === 5,
   'the quick-menu icons have no width/height attributes -- they are 512x512 '
 . 'source art sized by percentage, so the bar reflows as they decode and a '
 . 'fixed bar that changes height reads as a bar that moves');

/* ---------- 9. raid rows are compact, with the card in a modal ------------ */
/*
 * Each raid card was a progress bar, a realm name, a date, a header with
 * theme art, two full-bleed columns with their own backgrounds and avatars,
 * a stack of pills and an action row -- 400px+ per raid, which is why both
 * sections had to be collapsible to be usable. The list is one line per
 * raid now and the card opens over the page.
 *
 * THE CARD IS NOT RE-RENDERED ANYWHERE. It sits hidden beside its row and
 * the modal clones it, so there is no second copy of a 250-line renderer to
 * keep in step -- the failure mode this file has caught three times already
 * with get-locations and get-realm.
 */
echo "\nraid rows\n";
$dbsrc = file_get_contents(__DIR__ . '/db.php');
$gr = extract_fn_db($dbsrc, 'getRaids');
ok($gr !== '', 'getRaids() not found in db.php');
ok(strpos($gr, "class='rr'") !== false, 'getRaids() no longer emits a compact row');
ok(strpos($gr, "class='rc-detail'") !== false && strpos($gr, 'hidden') !== false,
   'the rich card is not parked hidden beside its row, so the list is long again');
ok(strpos($gr, 'openRaidDetail(') !== false, 'the compact row does not open the detail');
foreach (array('showRaidViewAnimation(', 'showRaidResultAnimation(', 'retreatRaid(') as $fn) {
	ok(strpos($gr, $fn) !== false, "getRaids() lost $fn -- Replay or Retreat is gone");
}
/* Replay has to be on the ROW, not only inside the modal: the battle
   animation is the thing worth reaching in one tap. */
$rrBlock = substr($gr, strpos($gr, "\$_compact  ="));
ok(strpos($gr, "\$_rr_acts") !== false && strpos($rrBlock, "rr-acts") !== false,
   'the action buttons are not on the compact row');
foreach (array('openRaidDetail', 'closeRaidDetail', 'raid-detail-body', 'raid-detail-overlay')
         as $hook) {
	ok(strpos($src, $hook) !== false, "realms.php is missing $hook for the raid modal");
}
ok(strpos($src, "removeAttribute('id')") !== false,
   'the modal clone keeps its ids, so every getElementById in the page can land '
 . 'in the copy instead of the original');
printf("  compact row + hidden detail + modal: wired\n");

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all realms page checks passed\n");
exit($fail ? 1 : 0);
