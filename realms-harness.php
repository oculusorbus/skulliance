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
/*
 * BUT NOTHING ELSE SHOULD DODGE IT. .rl-head used to reserve 64px on the
 * right for the same burger, from when the panel started at the top of the
 * screen. The nav owns that band now: the burger runs from the top inset
 * down 39px, .rl-nav covers 0 to inset+38 opaque at z-index 20, and the two
 * only ever render together (same $realm_status branch). Measured at three
 * scroll positions -- the Guide button shares the burger's column and never
 * its rows, passing up behind the nav instead. The padding was shifting the
 * button left to avoid a collision that cannot happen.
 */
ok(preg_match('/\.rl-head \{[^}]*padding-right/', $src) === 0,
   '.rl-head is dodging the burger again, which shifts the Guide button left '
 . 'for a collision the sticky nav already makes impossible');
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
/* ---------- 8c. the map is a real destination on a phone ------------------
 *
 * map.css opened with `#map { display: none !important }` below 768px, and
 * that was correct at the time: packFactions() sized itself to the VIEWPORT,
 * so at 390px it could not fit two faction blocks side by side and the map
 * came out 542 x 4652 -- a twelve-screen ribbon that overflowed sideways as
 * well (measured, 52 realms across 10 factions). It composes at a fixed
 * design width now and the CSS scales it: 330 x 601 at the same phone.
 *
 * THE min-width:0 IS LOAD-BEARING. #map is a .row (display:flex) and
 * #container is a flex item, so both default to min-width:auto and refuse to
 * shrink below the SVG's intrinsic width. Without it the other three rules
 * measure 1132px inside a 390px page -- they do literally nothing.
 */
/* ---------- 8d. every class the Manage modals render has a rule -----------
 *
 * I DELETED SIXTEEN RULES BY ACCIDENT and shipped it. Cutting the
 * #quick-menu blocks out of realms.php's sheet took the whole soldier-card
 * run with them -- they sat next to each other. Nothing threw, no harness
 * moved, and the PAGE looked perfect, because every one of those classes
 * only renders INSIDE a Manage modal. It surfaced as "managing locations
 * has blown out nft images, unusable on mobile": .soldier-nft-img had lost
 * width:64px, so a 1000px NFT rendered at 1000px, and .soldiers-grid had
 * lost display:grid, so the cards stopped being a grid and stacked one per
 * screen. The @media(max-width:500px) rule setting that grid to three
 * columns SURVIVED the cut and was overriding a grid that no longer
 * existed, which is why nothing looked obviously missing in the source.
 *
 * So: every realms-only class those endpoints emit must have a rule here.
 * This is the check that would have caught it.
 */
echo "\nthe manage modals have their styles\n";
$styleBlocks = array();
preg_match_all('/<style[^>]*>(.*?)<\/style>/s', $src, $sb);
$sheet = implode("\n", $sb[1]);
ok(count($sb[1]) >= 2, 'realms.php has fewer <style> blocks than expected; the '
 . 'audit below would be looking at the wrong one');
$emitted = array();
foreach (array_merge(glob(__DIR__ . '/ajax/get-*.php'), array(__DIR__ . '/realms.php')) as $f) {
	preg_match_all('/class=["\']([^"\']+)["\']/', file_get_contents($f), $cm);
	foreach ($cm[1] as $attr) {
		foreach (preg_split('/\s+/', $attr) as $c) {
			/* Only the families realms.php owns. .button, .icon and friends
			   come from flexbox.css and are not this page's to define. */
			if (preg_match('/^(soldier|soldiers|coffin|gear|tower)-/', $c)
			 || in_array($c, array('soldiers-grid', 'soldiers-table'), true)) {
				$emitted[$c] = true;
			}
		}
	}
}
ok(count($emitted) > 10, 'the class scan found almost nothing, so it is not '
 . 'actually scanning the modal endpoints any more');
/* A rule in EITHER sheet counts: .soldier-discharge-btn is a red variant of
   the platform's own .small-button and lives in flexbox.css, not here.
   .tower-pick has no rule anywhere by design -- it is the selector jQuery
   uses to find the cards. Named explicitly rather than excused by an "is it
   used in JS" test, so that deleting a real rule cannot slip through the
   same door. */
$flexAll  = file_get_contents(__DIR__ . '/dist/flexbox.css');
/* THE SQUARING BLOCK IS NOT A STYLE for this purpose. It lists most of these
   classes by name to zero their corners, so a class whose ONLY remaining
   mention is in there would read as styled while having lost everything that
   positions it -- .soldiers-stat did exactly that and passed. Drop any rule
   whose whole body is a border-radius before auditing. */
$radiusOnly = '/[^{}]+\{\s*border-radius:[^;}]+;?\s*\}/';
$sheet   = preg_replace($radiusOnly, '', $sheet);
$flexAll = preg_replace($radiusOnly, '', $flexAll);
$jsOnly   = array('tower-pick' => true);
$unstyled = array();
foreach (array_keys($emitted) as $c) {
	if (isset($jsOnly[$c])) continue;
	$sel = '/\.' . preg_quote($c, '/') . '[\s.,{:]/';
	if (!preg_match($sel, $sheet) && !preg_match($sel, $flexAll)) $unstyled[] = $c;
}
sort($unstyled);
printf("  modal classes rendered: %d; unstyled: %s\n",
	count($emitted), $unstyled ? implode(', ', $unstyled) : 'none');
ok(empty($unstyled),
   'these classes are rendered by a Manage modal and have no rule in '
 . 'realms.php: ' . implode(', ', $unstyled) . ' -- a modal is the one place '
 . 'on this page where losing a rule is invisible until someone opens it');
/* The two that actually caused the report, named so a future cut cannot
   quietly take them again. */
ok(preg_match('/\.soldiers-grid\s*\{[^}]*display:\s*grid/', $sheet) === 1,
   '.soldiers-grid lost display:grid -- the soldier cards stack one per row '
 . 'and the modal becomes endless on a phone');
ok(preg_match('/\.soldier-nft-img\s*\{[^}]*width:\s*64px/', $sheet) === 1,
   '.soldier-nft-img lost its width -- NFT art renders at its natural size, '
 . 'which is what "blown out nft images" was');

echo "\nthe map on a phone\n";
$mapJs  = file_get_contents(__DIR__ . '/map.js');
$mapCss = file_get_contents(__DIR__ . '/dist/map.css');
ok(strpos($mapCss, '#map { display: none !important; }') === false,
   'map.css hides #map on a phone again, so the Map tab leads to an empty panel');
preg_match('/@media \(max-width: 768px\) \{(.*?)\n\}/s', $mapCss, $mq);
$mobileCss = isset($mq[1]) ? $mq[1] : '';
ok($mobileCss !== '', 'map.css has no <=768px block at all');
ok(preg_match('/min-width:\s*0/', $mobileCss) === 1,
   'the mobile map block does not free the flex chain with min-width:0 -- a '
 . 'flex item defaults to min-width:auto, so #container will not shrink below '
 . 'the SVG and every other rule in this block does nothing');
/*
 * THE BOX HAS TO SCALE WITH THE DRAWING. map.js gives #container an explicit
 * inline width and height -- it has always been sized TO the map rather than
 * BY it -- so scaling the SVG in CSS was never enough on its own: the picture
 * came down to 330 x 601 and its box stayed 1132 x 2062, leaving 1471px of
 * empty background under the map on a 390px phone. That is what was reported
 * as the backdrop running on for multiples of the map's length. One scale
 * factor, applied to both.
 */
ok(preg_match('/container\.style\.width\s*=\s*`\$\{fitW\}px`/', $mapJs) === 1
   && preg_match('/container\.style\.height\s*=\s*`\$\{fitH\}px`/', $mapJs) === 1,
   '#container is sized from the raw svgW/svgH again, so on a phone the box '
 . 'stays full size while the map inside it shrinks -- dead background for '
 . 'multiples of the map\'s height');
ok(preg_match('/svg\.setAttribute\(\x27width\x27,\s*fitW\)/', $mapJs) === 1,
   'the SVG is not being fitted, so the map overflows the phone sideways');
/*
 * AND THE BOX CONTAINS ITS OWN INK. svgW/svgH come from the packer -- the
 * union of the faction BLOCKS -- but the name pills are drawn at y-17,
 * above their territory and centred on its width, so they hang off the top
 * and (for a long name on a block at x=0) off the left. Measured on a real
 * map: 17 above, 25.3 left. map.css sets overflow:visible, so that was
 * never clipped, it just painted over whatever the layout had put there --
 * and once the section's top padding went, that was the sticky nav.
 *
 * Padding by a guess is the wrong shape: the left bleed depends on the
 * longest faction NAME and moves with the data. The viewBox is grown to
 * the measured getBBox() instead, which is why zero padding is correct.
 */
ok(preg_match('/setAttribute\(\x27viewBox\x27, `\$\{vx\} \$\{vy\} \$\{vw\} \$\{vh\}`\)/', $mapJs) === 1,
   'the viewBox is no longer grown to the drawing\'s real extent, so the '
 . 'faction name pills paint outside the box and land under the sticky nav');
/*
 * AND IT MUST NOT ASK THE LAYOUT. getBBox() returns all zeros for an
 * element inside a display:none subtree -- verified in Chrome -- and
 * renderMap() runs at page load, when #map is hidden. Measuring that way
 * silently reported nothing, the box came out with only the slack, and
 * the top row of labels stayed under the nav. The extents are tracked
 * from the same geometry the shapes are drawn from instead.
 */
ok(preg_match('/^\s*(?!\s*\*)[^\n]*svg\.getBBox\(\)/m', no_comments('<?php ' . $mapJs)) === 0,
   'map.js measures the drawing with getBBox() again -- it renders while '
 . '#map is display:none, where getBBox reports all zeros');
ok(preg_match('/const ink = \(x0, y0, x1, y1\)/', $mapJs) === 1,
   'the ink tracker is gone; nothing knows how far outside the packed '
 . 'blocks the map actually draws');
ok(substr_count($mapJs, 'ink(') >= 3,
   'something that draws outside the faction blocks is no longer being '
 . 'tracked -- the pills, the marker rings and the marker name labels all '
 . 'reach past them, and the widest of the three is a realm NAME');
ok(strpos($mapJs, 'measureTextWidth(realm.user_name') !== false,
   'the marker name labels are not measured, so a long username still '
 . 'hangs outside the box -- they are text-anchor:middle and reach further '
 . 'left than any ring');
ok(preg_match('/const k = Math\.min\(1, avail \/ vw\)/', $mapJs) === 1,
   'the phone fit is scaling from the packer box rather than the measured '
 . 'one, so the map will not match the space it is given');

ok(preg_match('/background-repeat:\s*no-repeat/', $mobileCss) === 1,
   'the map backdrop can tile again; with cover that only shows when the box '
 . 'is wrong, but it is the visible symptom and costs nothing to pin');
/*
 * AND THE POPUP HAS TO FOLLOW THE ZOOM. position:fixed measures against the
 * LAYOUT viewport, so a pinch-zoomed player tapping a marker got an overlay
 * laid out across the whole unzoomed page, mostly off-screen. There is no
 * reliable way to force a zoom-out on iOS, and doing so would throw away
 * their place on the map, so the overlay is pinned to visualViewport.
 */
/*
 * AND IT ZOOMS BACK OUT FIRST. Anchoring alone left the card opening over a
 * corner of a zoomed-in map, which reads as the page lurching. iOS has no
 * API for resetting zoom, so this is the meta-tag clamp: declare the page
 * unzoomable for a moment, which makes Safari clamp the scale, then put the
 * ORIGINAL tag straight back. The restore is the dangerous half -- leaving
 * user-scalable=0 behind disables pinch for the rest of the session on
 * every page sharing header.php's viewport, which is worse than the bug.
 */
ok(strpos($mapJs, 'function resetPageZoom()') !== false
   && preg_match('/resetPageZoom\(\);\s*\n\s*popupOverlay\.style\.display/', $mapJs) === 1,
   'the popup no longer zooms the page out before it opens');
ok(preg_match('/setTimeout\(\(\) => meta\.setAttribute\(\x27content\x27, original\)/', $mapJs) === 1,
   'the viewport meta is clamped and never restored -- that disables '
 . 'pinch-zoom for the rest of the session, on every page, which is a worse '
 . 'bug than the one being fixed');
ok(preg_match('/vv\.scale <= 1\.01/', $mapJs) === 1,
   'the zoom reset fires even when the page is not zoomed, so an ordinary tap '
 . 'rewrites the shared viewport meta for nothing');
ok(preg_match('/if \(\/maximum-scale\/\.test\(original\)\) return false/', $mapJs) === 1,
   'nothing guards against re-entering while the restore is still pending -- '
 . 'a second tap would capture the CLAMPED tag as the original and make '
 . 'user-scalable=0 permanent');
/* The anchor stays as the fallback: the clamp is a trick, and Safari has
   changed its mind about it before. */
ok(strpos($mapJs, 'function anchorPopup()') !== false
   && preg_match('/popupOverlay\.style\.display = \x27flex\x27;\s*\n\s*anchorPopup\(\);/', $mapJs) === 1,
   'the realm popup is not anchored to the visual viewport when it opens, so '
 . 'it lands off-screen for anyone who has pinch-zoomed the map');
ok(preg_match('/visualViewport\.addEventListener\(\x27resize\x27/', $mapJs) === 1
   && preg_match('/visualViewport\.addEventListener\(\x27scroll\x27/', $mapJs) === 1,
   'the popup does not follow a zoom or pan that happens while it is open');
ok(strpos(no_comments('<?php ' . $mapJs), 'MOBILE_DESIGN_W') !== false
   && preg_match('/innerWidth\s*<=\s*MOBILE_BP\s*\?\s*MOBILE_DESIGN_W/', $mapJs) === 1,
   'packFactions() is packing to the phone\'s own width again, which turns the '
 . 'map into a single column -- 542 x 4652 on a 390px screen');
/* And the page has to stop treating the map as a passenger. */
ok(preg_match("/if \(rlWide\) \{ var hide = rlNavLink\('realm'\)/", $src) === 1,
   'realms.php still drops the Map link at phone width, so the map it can now '
 . 'render is unreachable');
ok(strpos(no_comments($src), "selection === 'realm'") === false,
   'the map is still stapled under the realm panel on a phone; it has its own '
 . 'tab now and would render twice');
/* The nudges every section used to need are gone with the headings. */
ok(preg_match("/getElementById\('(map|raids|realms|realm)'\)\.style\.top/", no_comments($src)) === 0,
   'a section is being nudged up by a negative top again -- those existed only '
 . 'to pull a panel over an <h2> that sat outside it, and no heading is '
 . 'outside a panel any more');
printf("  map composes at a design width and scales to the screen\n");

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
/*
 * PENDING FIRST, BOTH DIRECTIONS, THEN COMPLETED. The order was grouped by
 * direction -- outgoing-pending, outgoing-completed, incoming-pending,
 * incoming-completed -- which buried the incoming raids you can still act
 * on underneath a history list. And the whole thing was written out twice:
 * ajax/get-raids.php had its own copy, the FOURTH panel on this page to be
 * duplicated after the locations panel, the realm panel and the Attack
 * header.
 */
$raidsPartial = __DIR__ . '/realms-raids.php';
ok(is_file($raidsPartial), 'realms-raids.php is gone; the raid lists and their '
 . 'order are back to being written out twice');
$rp = no_comments(file_get_contents($raidsPartial));
preg_match_all('/\\$rr_(out|in)_(pending|done)/', $rp, $om, PREG_SET_ORDER);
$order = array();
foreach ($om as $mm) { $k = $mm[1] . '_' . $mm[2]; if (!in_array($k, $order, true)) $order[] = $k; }
printf("  raid list order: %s\n", implode(' > ', $order));
$echoAt = strpos($rp, 'foreach');
preg_match_all('/\\$rr_(out|in)_(pending|done)/', substr($rp, $echoAt), $em, PREG_SET_ORDER);
$rendered = array();
foreach ($em as $mm) $rendered[] = $mm[1] . '_' . $mm[2];
ok($rendered === array('out_pending', 'in_pending', 'out_done', 'in_done'),
   'the raid lists are not rendered pending-first: got '
 . implode(' > ', $rendered));
foreach (array('realms.php' => $src, 'ajax/get-raids.php' => file_get_contents(__DIR__ . '/ajax/get-raids.php')) as $who => $body) {
	ok(strpos($body, 'realms-raids.php') !== false,
	   "$who does not include realms-raids.php -- it is writing its own copy of "
	 . 'the raid lists, which is how three other panels on this page shipped a '
	 . 'redesign nobody saw');
	ok(substr_count(no_comments($body), "class=\"content raids\"") === 0
	   && substr_count(no_comments($body), "class='content raids'") === 0,
	   "$who still emits its own .content.raids blocks");
}
/*
 * AND THE COMPLETED LISTS STAY WHERE THEY ARE. getRaids(..., 'completed')
 * is what RESOLVES finished raids -- endRaid() updates consumables inside
 * it -- so realms.php runs it near the top, before the locations panel
 * prices anything. Moving those calls into the partial would render the
 * panel above from pre-raid state.
 */
ok(preg_match('/\$outgoing_completed = getRaids\(\$conn, "outgoing", "completed"\);/', $src) === 1
   && strpos($src, '$rr_out_done = $outgoing_completed;') !== false,
   'realms.php no longer resolves completed raids before the locations panel '
 . 'renders, or no longer hands that markup to the partial -- the panel will '
 . 'price locations from pre-raid state');

/*
 * THE MAP STARTS UNDER THE NAV. It is full-bleed art with its own backdrop
 * and was inheriting .main's 20px and #container-wrapper's 20px on top of
 * the nav's 14px margin: 54px of navy nothing before the picture, measured,
 * on every width. Hidden until now by the -100px nudge on #map, which I
 * removed as a leftover -- for this section it was not a leftover, it was
 * masking this.
 */
ok(preg_match('/#map > \.main \{[^}]*padding-top:\s*0/', $src) === 1
   && preg_match('/#map #container-wrapper \{[^}]*padding-top:\s*0/', $src) === 1,
   'the map section is inheriting the page gutter again -- 54px of empty '
 . 'background before the map starts');
/*
 * AND THE EMPTY WRAPPERS COME DOWN WITH THEIR PANELS. #realm and #raids
 * are not top-level: each sits inside a .main, and .main carries 20px of
 * padding. Hiding the panel alone left the wrapper as 40px of empty
 * gutter, twice -- 80px stacked above whatever section you were looking
 * at, which is the rest of the gap that was reported before the map after
 * the .main/#container-wrapper padding was already removed.
 */
foreach (array('realm', 'raids') as $wrapped) {
	ok(preg_match('/data-wrap="' . $wrapped . '"/', $src) === 1,
	   "the .main wrapping #$wrapped is not marked data-wrap, so hiding the "
	 . 'panel leaves 40px of empty gutter behind it');
}
ok(preg_match('/function rlPanel\(sec, on\)\{[^}]*data-wrap/s', $src) === 1,
   'rlPanel() no longer hides the wrapper alongside the panel');
ok(strpos(no_comments($src), "sections.forEach(function(s){ rlPanel(s, false); });") !== false,
   'toggleSections() is writing style.display straight onto each section '
 . 'again, which bypasses rlPanel() and leaves every wrapper standing');
/*
 * AND EVERY SHOW GOES THROUGH IT TOO. Routing only the HIDE path through
 * rlPanel is what broke Raids and Realm: the wrapper came down with the
 * panel, then the show path wrote container.style.display = 'block'
 * directly, so the panel was made visible inside a wrapper that was still
 * display:none. The section rendered into nothing, at every width. One
 * half of a pair is worse than neither half.
 */
$ts = no_comments(substr($src, strpos($src, 'function toggleSections(')));
$ts = substr($ts, 0, strpos($ts, "\n\t}") + 3);
preg_match_all('/\.style\.display\s*=/', $ts, $direct);
printf("  toggleSections direct style.display writes: %d\n", count($direct[0]));
ok(count($direct[0]) === 0,
   'toggleSections() writes .style.display directly on a section again; it '
 . 'has to go through rlPanel() or the section\'s wrapper is left behind '
 . '-- hidden on a show, standing on a hide');

/* The headings were sized as CONTROLS -- flexbox.css gives them 1.6rem at
   weight 300, a pointer cursor and a hover fade, because they used to be
   the toggles that collapsed each list. They are labels now and should sit
   at the same size as this page's other panel headings. */
ok(preg_match('/\.rc-section-label \{[^}]*font-size:\s*1\.05rem/', $src) === 1,
   'the raid section headings are back to the 1.6rem they had when they were '
 . 'the collapse toggles, which shouts over every other heading on the page');
ok(preg_match('/\.rc-section-title:hover \{[^}]*opacity:\s*1/', $src) === 1,
   'the raid headings still fade on hover, which says clickable about '
 . 'something that no longer does anything');
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

/* ---------------------------------------------------------------------------
 * ARRIVING AT A SECTION.
 *
 * The nav links are #locations/#realms/#raids/#realm/#map, but only
 * #realm-image and #realm-name were honoured on load -- so an incoming link
 * to #raids opened Locations, and so did a refresh or a Back after clicking
 * a tab. DHC Fighters' trait-drop list pointed at the old standalone
 * raids.php instead, which renders the same lists with none of this page's
 * CSS: an unstyled wall of markup, reported from the installed PWA.
 * ------------------------------------------------------------------------- */
echo "\na section in the hash opens that section\n";
ok(strpos($src, "var rlSections = ['locations', 'realms', 'raids', 'realm', 'map'];") !== false,
   'the section whitelist is gone; a hash cannot open a section');
/* The comment between the branch and the call is two lines, not one -- the
   first version of this assumed one and failed against working code. Match
   across whatever is in between instead of counting lines. */
ok(preg_match('/\}else if\(rlSections\.indexOf\(rlHash\) !== -1\)\{[\s\S]{0,300}?rlNav\(rlHash\);/', $src) === 1,
   'an incoming section hash no longer opens that section on load');
/* Through rlNav(), the same path a tab click takes -- an arrival and a
   click must not end in different states. */
ok(strpos($src, 'rlNav(rlHash);') !== false,
   'the hash opens a panel by some other route than the one a click uses');
/* The hash is user input and rlPanel() takes it as a selector, so it has to
   be checked against the nav's own list rather than passed through. */
ok(strpos($src, 'rlPanel(rlHash') === false,
   'a raw hash is passed to rlPanel(); it must be whitelisted first');
/* Every section the nav offers must be in the whitelist, or that tab is
   linkable from the nav and not reachable by URL. */
preg_match_all('/data-sec="([a-z]+)"/', $src, $nm);
foreach (array_unique($nm[1]) as $sec) {
	ok(strpos($src, "'" . $sec . "'") !== false,
	   "the nav offers \"$sec\" but the hash whitelist does not list it");
}

echo "\nthe old standalone raids page sends people to the real one\n";
/* Comment-stripped: this file's own header explains what it used to do and
   names getRaids(), db.php and header.php while doing so. Checking the raw
   text reported the redirect as still rendering raids -- the sixth time a
   harness here has matched the prose instead of the code. */
$raids = preg_replace('!/\*.*?\*/!s', '', file_get_contents(__DIR__ . '/raids.php'));
ok(strpos($raids, "header('Location: realms.php#raids'") !== false,
   'raids.php no longer redirects; it renders the raid lists with none of this page CSS');
ok(strpos($raids, 'getRaids(') === false,
   'raids.php renders raids again instead of redirecting');
/* A redirect after output is not a redirect, and db.php runs with
   display_errors on. */
ok(strpos($raids, "include 'db.php'") === false && strpos($raids, "include 'header.php'") === false,
   'raids.php includes something that prints before the redirect header');
ok(strpos($raids, '302') !== false,
   'the redirect is permanent; 301 is cached forever and hard to undo if the page comes back');
$cfg = file_get_contents(__DIR__ . '/dhcfighters-config.php');
ok(strpos($cfg, "'url' => 'realms.php#raids'") !== false,
   'the DHC trait-drop list points at raids.php again');

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all realms page checks passed\n");
exit($fail ? 1 : 0);
