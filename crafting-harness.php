<?php
/* crafting-harness.php — CLI only. Static. No database.
 *
 * CRAFTING IS THE MOST CONSEQUENTIAL PRESS ON THE PLATFORM.
 *
 * Convert spends six balances at once, shatter spends DIAMOND, burn
 * destroys CARBON. None of the three can be undone by the player, and
 * two of them are each other's only undo at a 1:1 rate. So the things
 * worth asserting are not about the animation:
 *
 *   the endpoint never trusts the number the browser sent;
 *   the reveal never decides an outcome, it only reports one;
 *   a refusal never animates a success;
 *   and the admin preview cannot craft, by construction.
 *
 * The sequences themselves are the question the preview page exists to
 * answer, and a harness that asserted how they look would be asserting
 * taste. What IS asserted about them is structural: that convert and
 * shatter are mirrors of one ring, and that nothing animates layout.
 */
$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$strip = function ($s) {
	$s = preg_replace('~/\*.*?\*/~s', '', $s);
	return preg_replace('~^\s*//.*$~m', '', $s);
};
$api   = (string) file_get_contents(__DIR__ . '/ajax/craft.php');
$modal = (string) file_get_contents(__DIR__ . '/crafting-modal.php');
$panel = (string) file_get_contents(__DIR__ . '/skulliance.php');
$adm   = (string) file_get_contents(__DIR__ . '/admin-crafting.php');
$apiCode   = $strip($api);
$panelCode = $strip($panel);
$admCode   = $strip($adm);
$modalCode = $strip($modal);
/* The script only, comments gone: the prose in this file describes the
   behaviour closely enough to satisfy a plain search on its own. */
$js = substr($modalCode, strpos($modalCode, '<script>'));
$js = substr($js, 0, strpos($js, '</script>'));
$css = substr($modalCode, strpos($modalCode, '<style>') + 7);
$css = substr($css, 0, strpos($css, '</style>'));

echo "the endpoint never trusts the browser\n";
ok(strpos($apiCode, "\$_SERVER['REQUEST_METHOD'] !== 'POST'") !== false,
   'ajax/craft.php accepts a GET, so an <img src> can craft');
ok(strpos($apiCode, "isset(\$_SESSION['userData']['user_id'])") !== false,
   'the endpoint does not require a session');
ok(preg_match("~\\\$amount\s*=\s*.*\(int\)\s*\\\$_POST\['amount'\]~", $apiCode) === 1,
   'the amount is not cast to an integer before it is used');
/*
 * EACH ACTION IS CLAMPED AGAINST A FIGURE RE-READ IN THIS REQUEST.
 * The sliders in the panel carry a max, and the max is markup: a posted
 * amount above it has to be refused here or the slider IS the security.
 */
$bounds = array(
	'convert' => 'getMinimumBalance($conn)',
	'shatter' => "\$cft_before['DIAMOND']",
	'burn'    => 'getBalance($conn, 15)',
);
foreach ($bounds as $act => $src) {
	$blk = strpos($apiCode, "'" . $act . "'");
	$blk = $blk === false ? '' : substr($apiCode, $blk, 700);
	ok(strpos($blk, $src) !== false, "$act does not re-read its own ceiling from the database");
	ok(preg_match('~if\s*\(\s*\$amount\s*>\s*\$~', $blk) === 1,
	   "$act does not refuse an amount above that ceiling");
}
/* 100 CARBON = 1 DIAMOND. A number that is not a multiple would mint a
   fraction of one. */
ok(preg_match('~floor\(\s*\$amount\s*/\s*100\s*\)\s*\*\s*100~', $apiCode) === 1,
   'burn does not floor to a whole hundred, so it can mint a fractional DIAMOND');
ok(strpos($apiCode, "if (\$amount < 100)") !== false,
   'burn does not refuse an amount that forms nothing');
/* The three writers are db.php's. This endpoint decides whether they may
   run; a second implementation here is a second thing to get wrong. */
foreach (array('craft(', 'shatter(', 'burn(') as $w) {
	ok(substr_count($apiCode, $w) === 1, "the endpoint calls $w more than once, or reimplements it");
}
ok(strpos($apiCode, 'UPDATE ') === false && strpos($apiCode, 'INSERT ') === false,
   'the endpoint writes SQL of its own instead of calling the three writers');

echo "\nthe no-JavaScript path still pays out\n";
/*
 * The three forms are still in the markup and a browser without
 * JavaScript still submits them. Deleting the handlers at the top of
 * skulliance.php would have made crafting silently do nothing for anyone
 * in that position.
 */
foreach (array('balance', 'diamond', 'carbon') as $f) {
	ok(strpos($panelCode, "isset(\$_POST['" . $f . "'])") !== false,
	   "the \$_POST['$f'] handler is gone, so crafting without JavaScript does nothing");
}

echo "\nthe reveal replaced the confirm box, and reports rather than decides\n";
ok(strpos($panelCode, 'confirmForm(') === false,
   'a crafting button still routes through the generic confirm box');
foreach (array('convert', 'shatter', 'burn') as $k) {
	ok(preg_match("~SK_CRAFT_OPEN\('" . $k . "'~", $panelCode) === 1,
	   "the $k button does not open the reveal");
}
ok(substr_count($panelCode, "include __DIR__ . '/crafting-modal.php'") === 1,
   'the reveal is included more than once, or not at all');
ok(strpos($js, 'window.SK_CRAFT_OPEN = function') !== false,
   'the partial never defines the hook it is called through');
/*
 * NOTHING IN THE DIALOG WORKS OUT AN OUTCOME. Every figure it prints
 * comes off the reply, so a craft the server clamped shows what the
 * server actually did and not what the slider asked for.
 */
/* INSIDE show(), not anywhere in the file: `r.spent` appears in three
   rows, so a plain search passed with one of them swapped for the amount
   the slider asked for. */
$showFn = substr($js, strpos($js, 'function show(r)'));
$showFn = substr($showFn, 0, strpos($showFn, "\n\t}"));
ok(strpos($showFn, 'r.spent') !== false && strpos($showFn, 'r.gained') !== false,
   'the result rows are not built from the reply');
ok(strpos($showFn, 'state.amount') === false && strpos($showFn, 'state.kind') === false,
   'the result rows read what was ASKED for rather than what the server did');
ok(preg_match('~show\(\s*\{[^}]*spent:\s*(amount|\d)~', $js) === 1,
   'the preview is not the only place that invents a result');
ok(strpos($js, 'reply.success') !== false, 'the dialog does not check whether the craft succeeded');

echo "\na refusal never animates a success\n";
/*
 * The request and the animation start together, but the RESULT waits for
 * both -- a reply that beats the animation would print the outcome over
 * the top of it. A failure does not wait, because there is nothing to
 * reveal.
 */
ok(strpos($js, 'if (settled || !ran || reply === null) return;') !== false,
   'the result can be written before the animation has finished, or before the reply arrives');
$finish = substr($js, strpos($js, 'var finish = function'));
$finish = substr($finish, 0, strpos($finish, "\n\t\t};"));
ok(strpos($finish, "veil.className = 'on done'") !== false
   || strpos($finish, "'on done k-'") !== false,
   'a successful craft never reaches the settled state');
ok(strpos($finish, "'on idle k-'") !== false,
   'a refused craft stays in the animating state instead of returning to the offer');
ok(strpos($js, 'ontimeout') !== false && strpos($js, 'onerror') !== false,
   'a dropped request leaves the dialog working forever');
ok(strpos($js, 'Nothing was crafted.') !== false,
   'a network failure does not say whether anything was spent');

echo "\nit only reloads when something actually moved\n";
/*
 * The panel behind the dialog is six server-rendered balances and three
 * sliders whose maximums are all now wrong, so a real craft reloads.
 * A refusal and a preview must not: nothing changed, and a reload would
 * throw away the error that explains why.
 */
ok(preg_match('~var moved = state && state\.done && !state\.preview~', $js) === 1,
   'the reload is not gated on a craft having actually happened');
ok(preg_match('~if \(moved\) window\.location\.reload\(\)~', $js) === 1,
   'closing does not refresh the balances after a craft');

echo "\nthe preview cannot craft, by construction\n";
/*
 * THE BUG THIS EXISTS TO PREVENT, which the first version had: the
 * preview leaves the button reading "Done", and it cleared state.done so
 * that closing would not reload -- which left exactly one press between
 * the admin preview and a real, irreversible craft.
 */
$click = substr($js, strpos($js, "go.addEventListener('click'"));
$click = substr($click, 0, strpos($click, 'x.send('));
$pv = strpos($click, 'state.preview');
$rq = strpos($click, 'XMLHttpRequest');
ok($pv !== false && $rq !== false && $pv < $rq,
   'the Confirm handler reaches the request without checking for a preview first');
$prev = substr($js, strpos($js, 'window.SK_CRAFT_PREVIEW = function'));
$prev = substr($prev, 0, strpos($prev, "\n\t};"));
ok(strpos($prev, 'state.preview = true') !== false, 'the preview does not mark itself as one');
ok(strpos($prev, 'XMLHttpRequest') === false && strpos($prev, 'craft.php') === false,
   'the preview makes a request, so playing a sequence can spend a balance');
ok(strpos($prev, 'state.done = false') === false,
   'the preview clears state.done again, which is what let its Done button craft');

echo "\nthe admin page shows the real thing and crafts nothing\n";
ok(strpos($admCode, "include __DIR__ . '/crafting-modal.php'") !== false,
   'the preview page does not include the real partial, so it can drift from what ships');
ok(strpos($admCode, 'SK_CRAFT_PREVIEW(') !== false, 'the preview page never plays a sequence');
ok(strpos($admCode, 'SK_CRAFT_OPEN(') === false,
   'the preview page opens the real dialog, which can craft');
/*
 * NOT a search for the string: the page NAMES ajax/craft.php in its own
 * copy, explaining that it does not go near it, and a plain search read
 * that sentence as a call. What matters is that the page makes no
 * request at all.
 */
ok(strpos($admCode, 'XMLHttpRequest') === false && strpos($admCode, 'fetch(') === false,
   'the preview page makes a request, so playing a sequence could craft');
ok(preg_match("~(src|href|open)\s*[=(]\s*['\"][^'\"]*craft\.php~i", $admCode) !== 1,
   'the preview page addresses the endpoint directly');
ok(strpos($admCode, 'admin_require()') !== false, 'the preview page is not behind admin_require()');
$req = strpos($admCode, 'admin_require()');
$hdr = strpos($admCode, "include 'header.php'");
ok($req !== false && $hdr !== false && $req < $hdr,
   'admin_require() runs after header.php, which is after output has begun');
$tabs = (string) file_get_contents(__DIR__ . '/admin-lib.php');
ok(strpos($tabs, "'crafting' => array('admin-crafting.php'") !== false,
   'there is no admin tab, so the preview cannot be reached');

echo "\nconvert and shatter are the same ring, in opposite directions\n";
/*
 * The two operations are each other's undo at 1:1, and should look like
 * it. One radius, six points, evenly spaced -- and if the geometry is
 * generated rather than hand-placed, it cannot drift apart.
 */
/* The geometry is generated, so the two directions cannot drift apart.
   A separate check for hand-written positions could not fail on its own:
   anything that replaces the expressions also trips the circle test
   below, which reads them. */
ok(preg_match('~cos\(\$ang\)\s*\*\s*(\d+)~', $modal, $rx) === 1
   && preg_match('~sin\(\$ang\)\s*\*\s*(\d+)~', $modal, $ry) === 1
   && $rx[1] === $ry[1],
   'the ring is not a circle: the two axes use different radii');
ok(preg_match('~\$i\s*\*\s*60~', $modal) === 1,
   'the six points are not 60 degrees apart, so they are not evenly spaced');
$radius = isset($rx[1]) ? (int) $rx[1] : 0;
preg_match('~#cft-stage\s*\{[^}]*width:\s*(\d+)px~s', $css, $sw);
preg_match('~\.cft-orb\s*\{[^}]*width:\s*(\d+)px~s', $css, $ow);
ok(!empty($sw) && !empty($ow), 'the stage or the orb has no pixel size this can check');
if (!empty($sw) && !empty($ow)) {
	/* The stage clips, so the ring plus half an orb has to fit inside it
	   or the six are cut off at their widest. */
	ok($radius + (int) $ow[1] / 2 <= (int) $sw[1] / 2,
	   'the ring is clipped: radius ' . $radius . ' plus half a ' . $ow[1]
	   . 'px orb does not fit a ' . $sw[1] . 'px stage');
}
ok(preg_match('~#cft-stage\s*\{[^}]*overflow:\s*hidden~s', $css) === 1,
   'the stage does not clip, so the flash adds scrollable overflow to the dialog');
/* Mirrors: one draws in, one bursts out, and both use the same points. */
ok(strpos($css, 'cftDrawIn') !== false && strpos($css, 'cftBurstOut') !== false,
   'convert and shatter do not have opposite travel');
ok(preg_match('~@keyframes cftDrawIn\s*\{[^@]*translate\(var\(--cx\), var\(--cy\)\)~s', $css) === 1,
   'convert does not start from the ring');
ok(preg_match('~@keyframes cftBurstOut\s*\{[^@]*translate\(var\(--cx\), var\(--cy\)\)~s', $css) === 1,
   'shatter does not end on the ring');

echo "\nonly transform, opacity and filter animate\n";
/*
 * Anything else is a layout thrash. Extracted by counting braces: a
 * regex bounded with [^@]* runs straight past the closing brace into
 * whatever rule follows.
 */
$bad = array(); $pos = 0; $found = 0;
while (($k = strpos($css, '@keyframes', $pos)) !== false) {
	$open = strpos($css, '{', $k);
	$d = 0; $end = $open;
	for ($i = $open; $i < strlen($css); $i++) {
		if ($css[$i] === '{') $d++;
		elseif ($css[$i] === '}') { $d--; if (!$d) { $end = $i; break; } }
	}
	$body = substr($css, $open, $end - $open);
	$name = trim(substr($css, $k + 10, $open - $k - 10));
	if (preg_match('~(^|[;{\s])(width|height|top|left|right|bottom|margin|padding)\s*:~', $body)) $bad[] = $name;
	$found++;
	$pos = $end + 1;
}
ok(count($bad) === 0, 'these keyframes animate layout: ' . implode(', ', $bad));
ok($found >= 6, 'only ' . $found . ' @keyframes were found, so the extraction is reading the wrong file');

echo "\nit closes, and reduced motion keeps the outcome\n";
ok(strpos($js, "e.key === 'Escape'") !== false, 'Escape does not close the dialog');
ok(strpos($js, 'if (e.target === veil) close()') !== false, 'clicking the veil does not close it');
ok(strpos($js, "cancel.addEventListener('click', close)") !== false, 'Cancel does not close it');
ok(substr_count($js, 'timers.forEach(clearTimeout)') >= 1,
   'pending timers survive a close, so a sequence fires into a closed dialog');
ok(strpos($css, 'prefers-reduced-motion') !== false, 'the reveal ignores prefers-reduced-motion');
$rm = substr($css, strpos($css, 'prefers-reduced-motion'));
ok(strpos($rm, 'animation: none') !== false, 'reduced motion does not stop the sequences');
/* The OUTCOME still has to be legible: the end state of each sequence is
   restated, or reduced motion is an empty stage. */
/*
 * EACH KIND'S END STATE, named. `.cft-orb` and `#cft-diamond` both
 * appear several times in this block, so a search for either passed with
 * the line that actually restates an outcome deleted.
 */
ok(preg_match('~k-convert #cft-diamond[^{]*\{[^}]*opacity:\s*1~s', $rm) === 1,
   'reduced motion ends convert on an empty stage instead of the DIAMOND it made');
ok(preg_match('~k-shatter \.cft-orb\s*\{[^}]*opacity:\s*1~s', $rm) === 1,
   'reduced motion ends shatter on an empty stage instead of the six it made');
ok(preg_match('~k-burn #cft-diamond[^{]*\{[^}]*opacity:\s*1~s', $rm) === 1,
   'reduced motion ends burn on an empty stage instead of the DIAMOND it made');


echo "\nthe flame is a flame, not a row of dots\n";
/*
 * WHAT IT WAS. Seven <span>s with a linear-gradient, rising on a straight
 * line. Reported as "a row of orange dots", which is exactly what divs
 * can be: too few, never overlapping into a mass, no lick in the path.
 *
 * THREE THINGS CARRY IT, and each is one line away from being lost.
 */
ok(strpos($modal, '<canvas id="cft-flame"') !== false,
   'the flame is not a canvas; divs cannot be fire');
ok(strpos($modalCode, 'cft-flame') !== false && strpos($css, '.cft-flame.f1') === false,
   'the old div flames are back');
/* 1. ADDITIVE BLENDING. Without it a crowd of faint blobs stays a crowd
      of faint blobs instead of summing into a body with a hot core. */
ok(strpos($js, "globalCompositeOperation = 'lighter'") !== false,
   'the flame does not blend additively, so it is discrete dots again');
/* 2. A HOT CORE AND A COOL TIP, chosen by AGE. One flat colour is
      confetti; fire is a temperature gradient. */
ok(preg_match('~var RAMP = \[~', $js) === 1, 'the flame has no colour ramp');
preg_match('~var RAMP = \[(.*?)\];~s', $js, $rampM);
preg_match_all('~\[\s*(\d+),\s*(\d+),\s*(\d+)\s*\]~', isset($rampM[1]) ? $rampM[1] : '', $stops, PREG_SET_ORDER);
ok(count($stops) >= 6, 'the ramp has only ' . count($stops) . ' stops, which will band');
if (count($stops) >= 6) {
	$first = array_map('intval', array_slice($stops[0], 1));
	$last  = array_map('intval', array_slice($stops[count($stops) - 1], 1));
	ok(min($first) > 150, 'the flame is not near-white where it is born, so it has no hot core');
	ok(max($last) < 80, 'the flame does not cool as it rises, so it has no tip');
	/* And it is the BRAND green through the body, not orange. */
	$mid = array_map('intval', array_slice($stops[(int) (count($stops) / 2)], 1));
	ok($mid[1] > $mid[0] && $mid[1] > $mid[2],
	   'the body of the flame is not green (' . implode(',', $mid) . ')');
}
/* The ramp is pre-rendered once. Tinting per particle per frame is the
   thing that makes a particle system too slow to run. */
ok(strpos($js, 'sprites.push(') !== false && strpos($js, 'createRadialGradient') !== false,
   'the blobs are not pre-rendered soft sprites');
ok(strpos($js, 'arc(') === false,
   'the flame draws filled circles; a hard edge reads as a dot however many there are');
/* 3. TURBULENCE. Every reference implementation gives particles a
      velocity and lets them fly straight, which is why they spray. */
ok(preg_match('~Math\.sin\(p\.ph \+ p\.life \* p\.fr\)~', $js) === 1,
   'the particles travel in straight lines, so the flame sprays rather than licks');
ok(preg_match('~\* p\.amp \* t \* t~', $js) === 1,
   'the curl does not grow as a particle rises, so the tip does not whip');
ok(preg_match('~\(p\.ox - cx\) \* \(1 - [\d.]+ \* t\)~', $js) === 1,
   'the particles keep their birth offset, so the flame is a column with parallel sides');

echo "\nand the flame loop cannot outlive the dialog\n";
/*
 * It is a requestAnimationFrame chain. Left running it burns a core
 * against a hidden canvas for as long as the tab is open.
 */
ok(strpos($js, 'cancelAnimationFrame') !== false, 'the flame loop is never cancelled');
$closeFn = substr($js, strpos($js, 'function close()'));
$closeFn = substr($closeFn, 0, strpos($closeFn, "\n\t}"));
ok(strpos($closeFn, 'FIRE.stop()') !== false, 'closing the dialog leaves the flame running');
ok(substr_count($js, 'FIRE.stop()') >= 3,
   'the flame is not stopped on every exit (close, settle, and the preview)');
ok(preg_match('~if \(state\.kind === .burn.\) FIRE\.start\(\)~', $js) === 1,
   'the flame starts for crafts that are not burns, or not at all');
/* Reduced motion gets no loop at all -- the CSS already shows the end. */
/* THE LAST ONE, not the first: FIRE opens with a no-op fallback for a
   browser with no canvas -- `return { start: function () {}, ... }` --
   and anchoring on the first match extracted that empty body instead. */
$startAt = strrpos($js, 'start: function ()');
$startFn = $startAt === false ? '' : substr($js, $startAt);
$stopAt  = $startFn === '' ? false : strpos($startFn, 'stop: function');
$startFn = $stopAt === false ? $startFn : substr($startFn, 0, $stopAt);
ok(strlen($startFn) > 120, 'the flame start function could not be read, so these checks are blind');
ok(strpos($startFn, 'prefers-reduced-motion') !== false,
   'the flame runs under reduced motion, which is the one place it must not');
ok(strpos($startFn, 'if (running) return;') !== false,
   'a second start stacks a second animation loop on the first');


echo "\nthe fire is out before the diamond arrives\n";
/*
 * REPORTED: "the burn lingers too long. It should be done when the
 * Diamond reveals and pushes forward." It burned at full strength until
 * the whole sequence settled, so the diamond formed THROUGH a live fire
 * and then everything cut at once.
 *
 * TWO NUMBERS IN TWO LANGUAGES have to agree about this, which is
 * exactly the kind of pair that drifts: when the spawner stops plus how
 * long the stragglers take to die, in JavaScript, against the diamond's
 * animation-delay in CSS. Measured rather than asserted -- if either
 * side moves and this fails, that is the conversation.
 */
ok(preg_match('~var BURNOUT_AT = (\d+);~', $js, $bo) === 1,
   'the spawner has no burnout point, so the fire only ends when the dialog does');
ok(preg_match('~var FADE_FRAMES = (\d+);~', $js, $ff) === 1,
   'the stragglers have no fade, so the fire cuts off instead of starving');
ok(preg_match('~k-burn #cft-diamond \{ animation: cftForm [\d.]+s [^;]*?\s([\d.]+)s forwards~', $css, $dd) === 1,
   "the diamond's delay cannot be read, so this check is blind");
if (!empty($bo) && !empty($ff) && !empty($dd)) {
	$flameOut   = (int) $bo[1] + (int) $ff[1] * (1000 / 60);   /* fade runs at ~60fps */
	$diamondAt  = (float) $dd[1] * 1000;
	ok($flameOut <= $diamondAt + 1,
	   'the fire is still burning when the diamond starts: out at ' . round($flameOut)
	   . 'ms, diamond at ' . round($diamondAt) . 'ms');
	/* And it must not go out so early that the burn is over before it
	   has been watched. */
	ok($flameOut >= $diamondAt - 700,
	   'the fire dies ' . round($diamondAt - $flameOut) . 'ms before the diamond, which is a gap, not a handover');
	/* The lump cannot outlive the thing consuming it. */
	ok(preg_match('~k-burn #cft-carbon \{ animation: cftChar ([\d.]+)s~', $css, $cc) === 1
	   && (float) $cc[1] * 1000 <= $flameOut,
	   'the CARBON lump is still on screen after the fire has gone out');
}
/* Starving means NO NEW FUEL and the rest dimming -- not a stop() call,
   which is what close() and a refusal use and which clears instantly. */
ok(preg_match('~if \(starving\)~', $js) === 1, 'the fire does not starve, it is switched off');
ok(preg_match('~\} else \{[^}]*spawn\(Math\.max~s', $js) === 1,
   'the spawner keeps running past burnout, so the fire never starves');
ok(preg_match('~globalAlpha = [^;]*\* fade;~', $js) === 1,
   'the fade is computed but never applied to anything');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
