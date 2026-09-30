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

/**
 * Source with comments removed.
 *
 * THREE TIMES NOW a check in this codebase has matched the comment that
 * explains the very thing being checked -- 'discordmsg' in the daily-reward
 * harness, 'filterNFTsForm' in the get-realm check, 'toggleRaids' here. A
 * comment saying "X was removed" contains X. Strip before searching, or
 * search for a form only markup can have.
 */
function no_comments($src) {
	$out = '';
	foreach (token_get_all($src) as $t) {
		if (is_string($t)) { $out .= $t; continue; }
		if ($t[0] === T_COMMENT || $t[0] === T_DOC_COMMENT) {
			$out .= str_repeat("\n", substr_count($t[1], "\n"));
			continue;
		}
		$out .= $t[1];
	}
	return $out;
}

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
	ok(preg_match('/data-sec="' . $sec . '"/', $src) === 1,
	   "no nav link with data-sec=\"$sec\" for section '$sec' -- the section is "
	 . 'reachable only by hash, and rlMark() will never light anything for it');
}
/* And the reverse: a link pointing at a section the list does not know about
   would switch to a panel toggleSections() never hides again. */
preg_match_all('/data-sec="([a-z]+)"/', $src, $im);
foreach (array_unique($im[1]) as $link) {
	ok(in_array($link, $sections, true),
	   "there is a nav link for '$link' that toggleSections() never hides or selects");
}
/*
 * AND NOTHING MAY DEREFERENCE A '-icon' ID AGAIN. The bottom bar is gone;
 * the twenty unguarded getElementById('<sec>-icon') calls that opened this
 * block went with it. One left behind throws on the FIRST statement of a
 * 1,500-line <script> and the page comes back as a single stack of panels.
 */
$realmsJs = $src;
preg_match_all("/getElementById\((['\"])([a-z]+)-icon\\1\)/", no_comments($realmsJs), $icons);
ok(empty($icons[0]),
   'realms.php still reads a <section>-icon element; the quick menu that provided '
 . 'them is gone, so this is a null dereference at the top of the script block');
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
/* ---------- 7b. the attack panel is one copy too --------------------------
 *
 * THE THIRD TIME. get-locations.php, then get-realm.php, now get-realms.php
 * -- each carried its own stale copy of a panel, so the redesigned markup in
 * realms.php was swapped out for the old one by the first refresh. On a
 * desktop that refresh happens immediately, which is how a rebuilt panel
 * shipped that the player had literally never seen.
 */
echo "\nthe attack panel\n";
$attackPartial = __DIR__ . '/realms-attack.php';
ok(is_file($attackPartial), 'realms-attack.php is gone; the Attack panel header is '
 . 'back to being written out twice');
$gRealms = file_get_contents(__DIR__ . '/ajax/get-realms.php');
foreach (array('realms.php' => $src, 'ajax/get-realms.php' => $gRealms) as $who => $body) {
	ok(strpos($body, "realms-attack.php") !== false,
	   "$who does not include realms-attack.php -- it is rendering its own copy "
	 . 'of the Attack header, which is the drift that has now bitten three panels');
	/* The MARKUP, not the identifier: realms.php names #filterRealms twice in
	   its squaring block, which is not a second copy of the control. */
	ok(strpos(no_comments($body), 'name="filterRealms"') === false,
	   "$who still writes its own Sort By control");
}
/* no_comments(), because the partial's own header comment quotes the very
   markup being looked for. That is the FOURTH time a check in this file has
   matched the comment explaining it. */
$apSrc = no_comments(file_get_contents($attackPartial));
ok(strpos($apSrc, 'ra-head') !== false && strpos($apSrc, '<h2>Realms</h2>') !== false
   && strpos($apSrc, 'class="content realms"') !== false,
   'the Attack heading is outside the panel again -- with the sort control '
 . 'floated inside it, the two end up on different lines at opposite ends of '
 . 'a very wide panel');
/* The heading must be INSIDE .content, not before it. */
ok(strpos($apSrc, 'class="content realms"') < strpos($apSrc, '<h2>Realms</h2>'),
   'the <h2> is rendered before .content.realms opens, which is the layout '
 . 'that was reported');
printf("  attack panel: one partial, header inside the panel\n");
/* THE SWEEP TOOK THE CARDS AND LEFT THE PILLS, which is most of what is
   actually on screen in the Attack list. Every one of these carries a radius
   in dist/flexbox.css. */
foreach (array('rtc-loc-pill', 'rtc-loc-cat-boost', 'rtc-balance-pill',
               'rtc-garrison-slot') as $pill) {
	ok(preg_match('/\.' . $pill . '\b[^{]*\{[^}]*\}|\.' . $pill . '\b[^{]*,/', $src) === 1
	   || strpos($src, '.' . $pill . ',') !== false,
	   "the squaring block does not name .$pill, and flexbox.css rounds it");
}

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
/*
 * THE BAR IS GONE. It was position:fixed at the bottom, which on an iOS PWA
 * meant fighting the home indicator, and it moved between panels because it
 * reacted to the height of the page: no content, bar high; content, bar
 * dropped. Reported four times in four different words. The nav is a sticky
 * strip at the TOP now, the same control missions.php uses, and top:0 has
 * none of those problems -- nothing below the fold, no indicator to dodge,
 * no fixed positioning to lose.
 */
ok(preg_match('/\.rl-nav\s*\{[^}]*position:\s*sticky/', $src) === 1,
   'the section nav is not position:sticky -- it scrolls away on a long page, '
 . 'which is the whole reason it moved to the top');
/*
 * STICKY NEEDS A CONTAINING BLOCK TALLER THAN ONE SCREEN, and flexbox.css
 * gives .container height:100%. The nav is a direct child of it, so the
 * first cut of this pinned for exactly one viewport and then scrolled away
 * -- reported as the menu disappearing halfway down a section. Every part of
 * that is invisible in the nav's own rules, which is why it is checked here.
 */
ok(preg_match('/^\s*\.container\s*\{[^}]*height:\s*auto/m', $src) === 1,
   'realms.php no longer overrides .container height -- flexbox.css pins it to '
 . '100% of the viewport, and a position:sticky child cannot stick outside its '
 . 'containing block, so the nav will unpin one screenful down');
ok(preg_match('/height:\s*100%/', $flexContainer = (function(){
		$f = file_get_contents(__DIR__ . '/dist/flexbox.css');
		return preg_match('/\.container\s*\{([^}]*)\}/', $f, $m) ? $m[1] : '';
	})()) === 1,
   'flexbox.css no longer sets .container height:100%, so the realms override '
 . 'above is now dead weight -- delete it rather than leaving a comment '
 . 'explaining a problem that no longer exists');
/*
 * THE INSET IS PADDING, NOT `top`. body carries padding-top:env(inset) and
 * that padding SCROLLS AWAY; pinning the bar at top:env(inset) leaves the
 * band above it transparent, and page content rides up through it into the
 * clock and the battery. Paying the inset as the bar's own padding makes its
 * background own that band at every scroll position.
 */
ok(preg_match('/\.rl-nav\s*\{[^}]*top:\s*0\s*;/', $src) === 1,
   'the sticky nav does not pin at top:0, so whatever offset it uses leaves a '
 . 'transparent band above it that page content scrolls through');
ok(preg_match('/\.rl-nav\s*\{[^}]*padding:\s*calc\(\s*[0-9]+px\s*\+\s*env\(safe-area-inset-top/', $src) === 1,
   'the nav does not pay the top safe-area inset as its own padding -- on an '
 . 'iOS PWA its links sit in the strip the status bar owns');
/*
 * ORDER IS ACTION FIRST. Locations and Attack are the two places you do
 * something; Raids, Realm and the Map are things you read or set once. They
 * also have to be ADJACENT, because on a phone the swipe walks this list and
 * the two action panels being one swipe apart is the point.
 */
preg_match_all('/<a href="#[a-z]+" data-sec="([a-z]+)"/', $src, $ord);
printf("  nav order: %s\n", implode(' > ', $ord[1]));
ok(isset($ord[1][0]) && $ord[1][0] === 'locations' && isset($ord[1][1]) && $ord[1][1] === 'realms',
   'the nav no longer leads with the two action sections (Locations then '
 . 'Attack) -- reference panels are in front of them and they are no longer '
 . 'one swipe apart');
ok(isset($ord[1][4]) && $ord[1][4] === 'map',
   'the Map is not last; it is purely reference and should trail the list');
ok(preg_match('/@media \(max-width: 700px\)[^}]*\{\s*[^}]*\.rl-nav\s*\{[^}]*padding-right/', $src) === 1
   || preg_match('/\.rl-nav\s*\{[^}]*padding-right:\s*56px/', $src) === 1,
   'the nav does not dodge the burger, which is fixed at the top right under '
 . '700px -- the last link ends up under it');
ok(strpos($src, 'id="quick-menu"') === false,
   'the bottom quick menu is back in realms.php; the sticky nav replaced it and '
 . 'having both means two controls disagreeing about which panel is open');
/* THE BAR MUST HUG THE BOTTOM IN STANDALONE, on the pages that still have
   one -- guardians and obscura do. flexbox.css used to lift it by the inset
   plus 10px and paint a body::after strip to mask the gap; that lift is
   invisible in desktop Chrome and was reported three times as the bar
   sitting high with dead space under it. */
$flex = file_get_contents(__DIR__ . '/dist/flexbox.css');
ok(!preg_match('/#quick-menu\s*\{[^}]*bottom:\s*calc\(env\(safe-area-inset-bottom[^}]*\+\s*10px/', $flex),
   'flexbox.css lifts #quick-menu off the bottom again in standalone -- that is the '
 . 'dead band under the bar, and it does not reproduce outside a PWA');
ok(strpos($flex, 'body:has(#quick-menu[style*="block"])::after') === false,
   'the body::after masking strip is back; it exists only to hide the gap under a '
 . 'lifted bar, and the bar is not lifted any more');
printf("  preload generated from realm_con_names(): %s; sticky nav: %s\n",
	$genPreload ? 'yes' : 'no',
	preg_match('/\.rl-nav\s*\{[^}]*position:\s*sticky/', $src) ? 'yes' : 'no');
/* The panels still need the bottom inset -- not to clear a bar any more, but
   so their last row is not under the home indicator. */
ok(substr_count($src, 'safe-area-inset-bottom') >= 1,
   'the panels lost the bottom inset, so their last row sits under the iOS home '
 . 'indicator');

/* ---------- 8b. swipe between sections on a phone ------------------------ */
/*
 * swipe-nav.js CLICKS THE NEXT NAV LINK rather than calling toggleSections()
 * itself. That is the whole design: realms swaps panels, missions jumps to a
 * hash, and neither page grew a second idea of what a section change is. A
 * swipe implementation that reached for toggleSections directly would work
 * on realms and be dead code on missions, and would drift the first time a
 * link learns to do anything else.
 */
echo "\nswipe between sections\n";
$swipe = file_get_contents(__DIR__ . '/swipe-nav.js');
$swipeC = no_comments($swipe);
ok(strpos($swipeC, '.click()') !== false,
   'swipe-nav.js no longer clicks the nav link -- if it calls the page\'s own '
 . 'switcher instead it is a second copy of the navigation rule');
ok(strpos($swipeC, 'touchstart') !== false && strpos($swipeC, 'mousedown') === false,
   'swipe-nav.js binds something other than touch; this is meant to be invisible '
 . 'to a mouse');
ok(strpos($swipeC, 'overflowX') !== false,
   'swipe-nav.js no longer gives way to sideways scrollers -- it will steal the '
 . 'drag from the nav strip itself, from wide tables and from the map');
ok(strpos($swipeC, 'show-menu') !== false,
   'swipe-nav.js no longer stands down while the burger is open, and the burger '
 . 'is a full-screen affordance on every page here');
ok(strpos($swipeC, 'offsetParent') !== false,
   'swipe-nav.js counts hidden links, so a swipe on a phone will land on the '
 . 'section realms deliberately dropped from the nav at that width');
foreach (array('realms.php' => 'rl-nav', 'missions.php' => 'ms-nav') as $page => $navId) {
	$ps = file_get_contents(__DIR__ . '/' . $page);
	ok(strpos($ps, 'swipe-nav.js') !== false,
	   "$page does not load swipe-nav.js");
	ok(strpos(no_comments($ps), "SkullSwipe.init('$navId'") !== false,
	   "$page loads swipe-nav.js but never initialises it against #$navId");
	ok(strpos(no_comments($ps), 'blocked:') !== false,
	   "$page initialises the swipe with no blocked() guard, so a swipe over an "
	 . 'open dialog moves the page underneath it');
}

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
$gr = no_comments('<?php ' . extract_fn_db($dbsrc, 'getRaids'));
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
/*
 * THE CAP HAS TO ACTUALLY CAP. getRaids() marks rows past the fifth with
 * the `hidden` attribute, which is enforced by a UA rule that ANY author
 * `display` beats -- and .rr sets display:flex. So every row rendered and
 * "Show all 10" removed a button that did nothing. Reported as exactly
 * that. It needs a rule that outranks .rr, and 'hidden' being silently
 * ignored is the same shape of bug as classList.contains() on a renamed
 * class: nothing throws.
 */
ok(preg_match('/\.rr\[hidden\]\s*\{[^}]*display:\s*none/', $src) === 1,
   'nothing overrides .rr { display:flex } for [hidden] rows, so getRaids()\'s '
 . 'cap renders every row anyway and the Show-all button does nothing');
ok(strpos(no_comments($gr), 'rr-more') !== false && strpos($src, 'rr-more') !== false,
   'the capped rows and the Show-all handler disagree about the .rr-more marker');
/*
 * THE WHOLE ROW IS THE HIT AREA. .rr-main is flex:1 1 240px, so on a
 * pending row -- pills, Replay AND Retreat beside it -- it was squeezed to
 * the left quadrant and the rest of the row did nothing. Completed rows
 * carry less, which is why it read as random rather than as a layout rule.
 */
ok(preg_match('/\.rr-main::after\s*\{[^}]*position:\s*absolute[^}]*inset:\s*0/', $src) === 1,
   'the raid row has no stretched hit area, so only the part .rr-main happens '
 . 'to be flexed to is clickable -- narrowest on pending rows, which carry the '
 . 'most beside it');
ok(preg_match('/\.rr-tags,\s*\.rr-acts\s*\{[^}]*z-index:\s*1/', $src) === 1,
   'the action buttons are not lifted above the row-wide hit area, so Replay '
 . 'and Retreat now open the modal instead of firing');
ok(preg_match('/\.rr:hover\s*\{[^}]*background/', $src) === 1,
   'the raid row does not highlight on hover, so nothing signals that it opens '
 . 'anything');
/* The history link was built for one player who has left. */
ok(strpos($gr, 'raids.php') === false && strpos($gr, 'rc-history-link') === false,
   'the raid history link is back under the completed lists; it points at the '
 . 'same renderer with the LIMIT lifted, which Show-all already covers');
printf("  compact row + hidden detail + modal: wired; cap enforced; row-wide hit area\n");

/* NO DISCLOSURE ARROW. The sections were collapsible because a raid was a
   400px card; a raid is a row now, and an arrow between the player and the
   list is the step this whole change existed to remove. */
ok(strpos($gr, 'toggleRaids') === false,
   'the raid sections collapse again -- that press is the thing the compact '
 . 'rows were meant to make unnecessary');
ok(strpos($gr, 'raid-arrow-icon') === false, 'the disclosure arrow is back on the raid sections');
ok(strpos($gr, "rc-section-count") !== false, 'the section heading lost its count');
ok(strpos($gr, "style='display:") === false && strpos($gr, 'style="display:\'.$display') === false,
   'the raid container is rendered with an inline display again, so it can load hidden');
/* A cap instead, so a long list still has a lid but the first few are
   visible without pressing anything. */
ok(strpos($gr, 'rr-more') !== false && strpos($gr, 'showAllRaidRows') !== false,
   'long raid lists have no cap -- the history page has no LIMIT at all');
ok(strpos($src, 'function showAllRaidRows') !== false,
   'realms.php has no showAllRaidRows(), so the Show all button does nothing');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all realms page checks passed\n");
exit($fail ? 1 : 0);
