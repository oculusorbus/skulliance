<?php
/* missions-chest-harness.php — CLI only. Static. No database.
 *
 * TWO FILES, ONE FEATURE, AND A CLEAN SPLIT BETWEEN THEM.
 *
 * missions-chest.php is the state of the daily reward in the missions
 * action column: closed while there is something to take, open and lit
 * once it has been. missions-claim-modal.php is the moment it is taken:
 * a 210px chest, a burst, and the day's real consumable and currency
 * icons flying out of its mouth into their own row.
 *
 * The split is the thing worth testing. The strip used to do the whole
 * performance at 96px, where the burst was all that read and the rewards
 * were a line of 20px icons beside it. If animation creeps back into the
 * strip, or the modal stops naming what was actually paid, the feature is
 * back to where it started.
 *
 * AND BOTH HOOKS ARE OPTIONAL BY CONSTRUCTION. dailyReward() must check
 * for SK_CHEST_OPEN and SK_CLAIM_SHOW before calling them, exactly as it
 * does for DHC_SHOW_DROP, or every page that claims without these
 * partials throws instead of claiming.
 */
$fail = 0;
function ok($c, $w) { global $fail; if (!$c) { $fail++; echo "  FAIL  $w\n"; } }

$strip = function ($s) {
	$s = preg_replace('~/\*.*?\*/~s', '', $s);
	return preg_replace('~^\s*//.*$~m', '', $s);
};
$chest = (string) file_get_contents(__DIR__ . '/missions-chest.php');
$modal = (string) file_get_contents(__DIR__ . '/missions-claim-modal.php');
$daily = (string) file_get_contents(__DIR__ . '/missions-daily.php');
$adm   = (string) file_get_contents(__DIR__ . '/admin-chest.php');
$lib   = (string) file_get_contents(__DIR__ . '/missions-lib.php');
$js    = (string) file_get_contents(__DIR__ . '/skulliance.js');

/* EVERY assertion reads the comment-stripped text. The prose in these
   files describes the behaviour closely enough to satisfy a plain search
   on its own, which is how an earlier version of this harness passed
   with the code deleted and the comment left behind. */
$chestCode = $strip($chest);
$modalCode = $strip($modal);
$admCode   = $strip($adm);
$jsCode    = $strip($js);
$libCode   = $strip($lib);
$dailyCode = $strip($daily);

echo "the button still does the claiming\n";
ok(strpos($chestCode, 'dailyReward(') === false,
   'the chest calls dailyReward() itself, so there are two claim paths');
ok(strpos($chestCode, "getElementById('claimRewardButton')") !== false,
   'the chest does not find the real button, so it cannot forward to it');
ok(strpos($chestCode, 'btn.click()') !== false,
   'the chest does not forward its click to the button');
ok(strpos($chestCode, 'if (btn.disabled) return;') !== false,
   'the chest forwards a click to a disabled button, which is the second-claim guard');
ok(strpos($chestCode, 'aria-hidden="true"') !== false,
   'the chest is not aria-hidden, so a screen reader meets a second nameless control');
ok(strpos($dailyCode, 'claimRewardButton') !== false,
   'the claim button has gone from the strip, leaving only the picture');
ok(strpos($modalCode, 'dailyReward(') === false,
   'the reveal can reach dailyReward(), so showing it could claim');

echo "\nthe strip icon is state, not animation\n";
/*
 * THE WHOLE POINT OF THE SPLIT. The strip is a status light. A status
 * light that breathes, bursts and throws sparks at 96px is asking for
 * attention it does not need, and it was where the reveal used to live.
 * Transitions are allowed -- the open state crossfading in is the state
 * changing, and the hover scale is feedback on a pointer event.
 */
ok(strpos($chestCode, '@keyframes') === false,
   'the strip icon has keyframes again, so it animates on its own');
ok(preg_match('~(^|[;{\s])animation\s*:~', $chestCode) !== 1,
   'the strip icon sets an animation property, so it is not purely state');
foreach (array('ms-chest-spark', 'ms-chest-flash', 'is-bursting', '--mcrise') as $gone) {
	ok(strpos($chestCode, $gone) === false,
	   "the strip icon still carries $gone, which belongs to the reveal");
}
/* The two states themselves must still be there. */
ok(preg_match('~\.ms-chest\.is-open \.ms-chest-open\s*\{[^}]*opacity:\s*1~s', $chestCode) === 1,
   'the open state never shows the open chest');
ok(preg_match('~\.ms-chest\.is-open \.ms-chest-glow\s*\{[^}]*opacity:\s*1~s', $chestCode) === 1,
   'the open state is not lit, so a claimed chest looks the same as an empty one');
ok(preg_match('~\.ms-chest-open,\s*\.ms-chest-glow\s*\{[^}]*opacity:\s*0~s', $chestCode) === 1,
   'the open chest and the glow are not hidden to start, so the closed state shows all three');

echo "\nboth hooks are optional, like the one beside them\n";
ok(strpos($jsCode, 'if (window.SK_CHEST_OPEN) SK_CHEST_OPEN();') !== false,
   'dailyReward() calls the strip hook without checking it exists');
ok(strpos($jsCode, 'if (window.SK_CLAIM_SHOW) SK_CLAIM_SHOW(obj);') !== false,
   'dailyReward() calls the reveal without checking it exists');
ok(strpos($chestCode, 'window.SK_CHEST_OPEN = function') !== false,
   'the strip partial never defines the hook it is called through');
ok(strpos($modalCode, 'window.SK_CLAIM_SHOW = function') !== false,
   'the reveal partial never defines the hook it is called through');
$atChest = strpos($jsCode, 'SK_CHEST_OPEN');
$atShow  = strpos($jsCode, 'SK_CLAIM_SHOW');
$rew     = strpos($jsCode, "getElementById('reward').style.opacity");
ok($atChest !== false && $rew !== false && $atChest < $rew,
   'the strip switches after the reward line is written, so the art lags the numbers');
ok($atShow !== false && $rew !== false && $atShow < $rew,
   'the reveal opens after the reward line is written, so it lags the numbers');
/* The already-claimed reply returns null and must not open anything. */
$nullBranch = substr($jsCode, strpos($jsCode, 'if(obj == null)'));
$nullBranch = substr($nullBranch, 0, strpos($nullBranch, '}else{'));
ok(strpos($nullBranch, 'SK_CHEST_OPEN') === false,
   'an already-claimed reply still switches the strip chest');
ok(strpos($nullBranch, 'SK_CLAIM_SHOW') === false,
   'an already-claimed reply still opens the reveal, with nothing to reveal');
/* INSIDE THE HOOK, not anywhere in the file: the same test appears in the
   click binding, so a plain search passes with the guard deleted. */
$hook = substr($chestCode, strpos($chestCode, 'window.SK_CHEST_OPEN = function'));
$hook = substr($hook, 0, strpos($hook, '};'));
ok(strpos($hook, "el.classList.contains('is-open')") !== false,
   'the strip hook does not guard against switching twice');
ok(strpos($hook, "add('is-open')") !== false, 'the strip hook never opens the chest');

echo "\nthe reveal names what was actually paid\n";
/*
 * THE REWARD ICONS ARE THE REVEAL. Not generic sparkles: the real
 * icons/*.png for the day's consumable and the currency that was drawn,
 * which are the same two pictures the reward line has always shown. A
 * modal that shows light and a number is a receipt.
 */
ok(strpos($modalCode, "'icons/' + String(obj.currency).toLowerCase() + '.png'") !== false,
   'the reveal does not show the currency that was actually drawn');
ok(strpos($modalCode, 'DAYS[day]') !== false,
   'the reveal does not look up the day, so it cannot name the consumable');
ok(strpos($modalCode, 'info.icon') !== false && strpos($modalCode, 'info.name') !== false,
   'the reveal has the day but shows neither its icon nor its name');
/*
 * AND THE DAY MAP IS NOT A THIRD COPY. mission_daily_items() defines the
 * cycle; the modal is handed $ms_daily['days'], which is built from it.
 * A hardcoded list here would be a fourth place to forget to change.
 */
ok(strpos($modalCode, "\$ms_daily['days']") !== false,
   'the reveal does not read the cycle from the data it is given');
foreach (array('random-reward', 'fast-forward', '100-success') as $slugish) {
	ok(strpos($modalCode, $slugish) === false,
	   "the reveal hardcodes $slugish, so it is a second copy of the cycle");
}
ok(strpos($libCode, 'function mission_daily_items()') !== false,
   'the cycle has no single definition to read');
/* mission_daily() must USE it rather than keep its own inline copy. */
$md = substr($libCode, strpos($libCode, 'function mission_daily($conn)'));
$md = substr($md, 0, strpos($md, "\n}"));
ok(strpos($md, 'mission_daily_items()') !== false,
   'mission_daily() does not use the shared cycle, so the ladder and the reveal can disagree');
ok(strpos($md, "'random-reward'") === false,
   'mission_daily() still carries its own inline cycle beside the shared one');
/* A missing icon file must not leave a hole where a reward was. */
ok(strpos($modalCode, 'onerror=') !== false,
   'a missing icon leaves a blank where a reward was, with nothing naming it');

echo "\nthe rewards fly from the mouth to their row\n";
/*
 * Each item starts translated back to the chest's mouth and transitions
 * to transform:none, which is its own resting layout position. Two
 * things make that work and both are easy to lose:
 *
 *   the offsets are custom properties, read in JS and consumed in CSS;
 *   the rect is read BEFORE the .in class, because .in is the transition.
 *
 * The read is exact despite scale() already being applied, because
 * transform-origin is the centre and the centre is all that is read.
 */
ok(preg_match('~\.mcm-item\s*\{[^}]*transform:\s*translate\(var\(--mcmdx~s', $modalCode) === 1,
   'the items do not start at the mouth, so they fade in where they already are');
ok(preg_match('~\.mcm-item\.in\s*\{[^}]*transform:\s*none~s', $modalCode) === 1,
   'the items never return to their resting position, so they stay on the chest');
ok(preg_match('~\.mcm-item\.in\s*\{[^}]*transition:[^}]*transform~s', $modalCode) === 1,
   'the flight is not transitioned, so the items appear in place');
ok(strpos($modalCode, "setProperty('--mcmdx'") !== false
   && strpos($modalCode, "setProperty('--mcmdy'") !== false,
   'the offsets are never measured, so every item flies from the same nowhere');
ok(strpos($modalCode, 'getBoundingClientRect()') !== false,
   'the offsets are not measured off the real layout');
/* ORDER: measure, then fly. Reversed, the rect read includes the
   transition's own transform and every item flies from the wrong place. */
$show = substr($modalCode, strpos($modalCode, 'window.SK_CLAIM_SHOW = function'));
$mdx  = strpos($show, "setProperty('--mcmdx'");
$inCl = strpos($show, "classList.add('in')");
ok($mdx !== false && $inCl !== false && $mdx < $inCl,
   'the .in class is added before the offsets are measured, so the flight starts from nowhere');
ok(strpos($show, 'cr.height * 0.47') !== false,
   'the flight does not start at the mouth of the chest');

echo "\nthe burst leaves along the mouth, which is not horizontal\n";
/*
 * The chest is drawn in three-quarter view, so its front rim rises to
 * the right. Six sparks sharing one origin and fanning sideways read as
 * a horizontal line crossing a tilted object.
 */
preg_match_all('~\.mcm-spark\.s(\d)\s*\{[^}]*left:\s*([\d.]+)%[^}]*top:\s*([\d.]+)%~', $modalCode, $sp, PREG_SET_ORDER);
ok(count($sp) >= 6, 'the sparks do not each have their own origin (' . count($sp) . ' found)');
$lefts = array_map(function ($m) { return (float) $m[2]; }, $sp);
$tops  = array_map(function ($m) { return (float) $m[3]; }, $sp);
ok(count(array_unique($tops)) === count($tops),
   'two sparks start at the same height, so they are not spread along the rim');
$descending = true;
for ($i = 1; $i < count($sp); $i++) if ($tops[$i] >= $tops[$i-1]) $descending = false;
ok($descending,
   'the spark origins do not rise to the right, so the burst ignores the angle of the chest');
$ordered = true;
for ($i = 1; $i < count($lefts); $i++) if ($lefts[$i] <= $lefts[$i-1]) $ordered = false;
ok($ordered, 'the spark origins are not ordered across the rim');
ok(count(array_unique($lefts)) === count($lefts),
   'two sparks share a horizontal origin, so they are not spread along the rim');
ok(min($lefts) > 20 && max($lefts) < 80,
   'the sparks start outside the opening (' . min($lefts) . '% to ' . max($lefts) . '%)');
ok(max($lefts) - min($lefts) > 25,
   'the sparks only cover ' . (max($lefts) - min($lefts)) . '% of the opening, so they read as one point');
/* 56% was the middle of the skull. The mouth sits around 45-50%. */
ok(max($tops) < 52 && min($tops) > 40,
   'the sparks start off the opening, at ' . min($tops) . '% to ' . max($tops) . '% down');
ok(preg_match('~\.mcm-flash\s*\{[^}]*top:\s*4[0-9](\.\d+)?%~s', $modalCode) === 1,
   'the flash is not centred on the mouth of the chest');
ok(strpos($modalCode, 'var(--mcrise)') !== false,
   'the spark rise is hardcoded, so it does not scale with the chest');

echo "\nthe burst cannot grow the dialog a scrollbar\n";
/*
 * A transform that escapes a scrollable ancestor adds scrollable
 * overflow to it. The one thing that must not happen at the moment a
 * reward lands is the dialog scrolling, so the stage clips -- and it is
 * sized so that it does not have to. Measured here rather than asserted:
 * the numbers are the only reason the clip is invisible.
 */
ok(preg_match('~#mcm-stage\s*\{[^}]*overflow:\s*hidden~s', $modalCode) === 1,
   'the stage does not clip, so the burst adds scrollable overflow to the dialog');
/* THE PLINTH IS THE BOX THE CHEST LIVES IN now that the pane also holds
   the reward row and is sized by its content. The clip is still the
   pane's, but the geometry that has to fit inside it is the plinth's. */
$sizes = array();
preg_match('~#mcm-plinth\s*\{[^}]*height:\s*(\d+)px~s', $modalCode, $m) && $sizes['stage'] = (int) $m[1];
preg_match('~#mcm-plinth\s*\{[^}]*padding-bottom:\s*(\d+)px~s', $modalCode, $m) && $sizes['floor'] = (int) $m[1];
preg_match('~\.mcm-chest\s*\{[^}]*height:\s*(\d+)px~s', $modalCode, $m) && $sizes['chest'] = (int) $m[1];
preg_match('~\.mcm-chest\s*\{[^}]*--mcrise:\s*(-?\d+)px~s', $modalCode, $m) && $sizes['rise'] = (int) $m[1];
ok(count($sizes) === 4, 'the plinth, floor, chest and rise are not all declared in px, so nothing can be checked');
if (count($sizes) === 4) {
	/* Bottom-aligned chest standing on the floor: the mouth is 47% down
	   the chest, measured from the top of the plinth. */
	$mouth = $sizes['stage'] - $sizes['floor'] - $sizes['chest'] + $sizes['chest'] * 0.47;
	ok($mouth + $sizes['rise'] > 0,
	   'the sparks are clipped: they rise ' . abs($sizes['rise']) . 'px off a mouth '
	   . round($mouth) . 'px down a ' . $sizes['stage'] . 'px plinth');
	ok($sizes['chest'] + $sizes['floor'] <= $sizes['stage'],
	   'the chest plus its floor is taller than the plinth, so the clip cuts the art itself');
	/* And it must still be worth the modal: the strip renders it at 99px. */
	ok($sizes['chest'] >= 180,
	   'the reveal chest is only ' . $sizes['chest'] . 'px, which is the size the strip already was');
}
/* THE WHOLE @media BLOCK, by counting braces. `[^}]*` stops at the first
   nested rule's closing brace, which is why a correct declaration two
   rules further down read as missing. */
$block = function ($src, $at) {
	$open = strpos($src, '{', $at);
	if ($open === false) return '';
	$d = 0;
	for ($i = $open; $i < strlen($src); $i++) {
		if ($src[$i] === '{') $d++;
		elseif ($src[$i] === '}') { $d--; if (!$d) return substr($src, $open, $i - $open); }
	}
	return '';
};
$phone = strpos($modalCode, 'max-width: 480px');
ok($phone !== false && strpos($block($modalCode, $phone), '--mcrise') !== false,
   'the phone size does not scale the spark rise');

echo "\nonly transform and opacity animate\n";
/*
 * Anything else is a layout thrash. The block is extracted by counting
 * braces: a regex bounded with [^@]* runs straight past the closing
 * brace into whatever rule follows, which is how an earlier version of
 * this reported `left: 50%` on the flash as a keyframe property.
 */
$bad = array(); $pos = 0; $found = 0;
while (($k = strpos($modalCode, '@keyframes', $pos)) !== false) {
	$open = strpos($modalCode, '{', $k);
	$d = 0; $end = $open;
	for ($i = $open; $i < strlen($modalCode); $i++) {
		if ($modalCode[$i] === '{') $d++;
		elseif ($modalCode[$i] === '}') { $d--; if (!$d) { $end = $i; break; } }
	}
	$body = substr($modalCode, $open, $end - $open);
	$name = trim(substr($modalCode, $k + 10, $open - $k - 10));
	if (preg_match('~(^|[;{\s])(width|height|top|left|right|bottom|margin|padding)\s*:~', $body)) $bad[] = $name;
	$found++;
	$pos = $end + 1;
}
ok(count($bad) === 0,
   'these keyframes animate layout properties instead of transform/opacity: ' . implode(', ', $bad));
ok($found >= 3, 'only ' . $found . ' @keyframes were found, so the extraction is reading the wrong file');

echo "\none reveal at a time\n";
/*
 * dhc-dropmodal.php is included by header.php on EVERY page, and a full
 * streak pays a trait, so dailyReward() used to open its veil 900ms
 * after a claim -- straight on top of the claim modal, which is up by
 * then. The claim modal takes the drop and reveals it on close. The
 * timer stays as the fallback for a page without the modal.
 */
ok(strpos($jsCode, 'obj.dhc_drop && window.DHC_SHOW_DROP && !window.SK_CLAIM_SHOW') !== false,
   'the trait reveal still fires on a timer, so it opens on top of the claim modal');
/* IN close(), not in show(): handing it over on open is the collision. */
$closeFn = substr($modalCode, strpos($modalCode, 'function close()'));
$closeFn = substr($closeFn, 0, strpos($closeFn, "\n\t}"));
ok(strpos($closeFn, 'DHC_SHOW_DROP') !== false,
   'the trait is revealed somewhere other than on close, which is the collision this fixes');
ok(strpos($show, 'window.DHC_SHOW_DROP) ? obj.dhc_drop : null') !== false,
   'the reveal takes the drop without checking the trait modal is even there');

echo "\nit closes, and nothing leaks between claims\n";
ok(strpos($modalCode, "e.key === 'Escape'") !== false, 'Escape does not close the reveal');
ok(strpos($modalCode, 'if (e.target === veil) close()') !== false,
   'clicking the veil does not close the reveal');
ok(strpos($modalCode, "getElementById('mcm-close').addEventListener('click', close)") !== false,
   'the Collect button does not close the reveal');
/* reset() exists because the admin preview plays it repeatedly, and a
   second play with the first one's timers still pending opens a chest
   that is already open and leaves the old items in the row. */
ok(strpos($modalCode, 'function reset()') !== false,
   'a second reveal keeps the first one state, so the chest is already open');
ok(strpos($show, 'reset();') !== false, 'the reveal does not reset before it plays');
ok(substr_count($modalCode, 'timers.forEach(clearTimeout)') >= 2,
   'pending timers survive a close, so a burst fires into a closed dialog');

echo "\nreduced motion keeps the reveal and drops the travel\n";
ok(strpos($modalCode, 'prefers-reduced-motion') !== false,
   'the reveal ignores prefers-reduced-motion');
$rm = substr($modalCode, strpos($modalCode, 'prefers-reduced-motion'));
$rm = substr($rm, 0, strpos($rm, '</style>'));
ok(strpos($rm, '.mcm-flash, .mcm-spark { display: none') !== false,
   'reduced motion still fires the sparks');
ok(preg_match('~\.mcm-item\s*\{\s*transform:\s*none~', $rm) === 1,
   'reduced motion still flies the rewards across the dialog');
ok(preg_match('~\.mcm-chest\.is-open \.mcm-glow~', $rm) === 1,
   'reduced motion also removes the glow, so the chest opens dark');
ok(strpos($rm, '#mcm-box { animation: none') !== false,
   'the dialog still springs in under reduced motion');
/* The rewards must still ARRIVE, or reduced motion is a blank row. */
ok(strpos($rm, 'transform: none') !== false && strpos($rm, 'opacity: 0') === false,
   'reduced motion leaves the rewards invisible rather than simply still');

echo "\nincluded once each, in the right state\n";
ok(substr_count($dailyCode, "include __DIR__ . '/missions-chest.php'") === 1,
   'the strip chest is included more than once, or not at all');
ok(substr_count($dailyCode, "include __DIR__ . '/missions-claim-modal.php'") === 1,
   'the reveal is included more than once, or not at all');
/* COMMENT-STRIPPED, because this file's header comment names both partials
   above everything else: read raw, the first match for either name is the
   prose and both of these ordering checks are measuring the wrong thing. */
$incChest = strpos($dailyCode, 'missions-chest.php');
$br       = strpos($dailyCode, "if (\$ms_daily['eligible']): ?>", strpos($dailyCode, 'ms-daily-right'));
ok($incChest !== false && $br !== false && $incChest < $br,
   'the strip chest is inside the eligible branch, so a claimed day shows no chest');
/* The reveal is the opposite: only a claimable day can produce a claim,
   so rendering it on a claimed day is markup nothing can reach. */
ok($br !== false && strpos($dailyCode, 'missions-claim-modal.php') > $br,
   'the reveal renders on a day with nothing to claim');
ok(strpos($chestCode, "defined('MS_CHEST_CSS')") !== false,
   'the strip stylesheet is not guarded, so a second include duplicates it');
ok(strpos($modalCode, "defined('MS_CLAIM_MODAL')") !== false,
   'the reveal is not guarded, so a second include duplicates its ids');
foreach (array('missions-chest.php' => $chestCode, 'missions-claim-modal.php' => $modalCode) as $f => $src) {
	ok(strpos($src, 'images/chest/') !== false && strpos($src, 'https://') === false,
	   "$f references the art absolutely; cookies here are host-only, so paths stay relative");
}

echo "\nthe admin preview shows the real thing and claims nothing\n";
/*
 * The reveal fires once a day for a player with a claimable day, so
 * without a preview nobody can look at it twice. Three things matter
 * about it: it must include the REAL partials, it must not be able to
 * claim, and it must not call mission_daily(), which resets a lapsed
 * streak as a side effect.
 */
ok(strpos($admCode, "include __DIR__ . '/missions-chest.php'") !== false,
   'the preview does not include the real strip partial, so it can drift from what ships');
ok(strpos($admCode, "include __DIR__ . '/missions-claim-modal.php'") !== false,
   'the preview does not include the real reveal, so it can drift from what ships');
ok(strpos($admCode, 'claimRewardButton') === false,
   'the preview carries a claim button, so looking at the reveal can spend a reward');
ok(strpos($admCode, 'dailyReward') === false,
   'the preview can reach dailyReward(), so it can claim');
ok(strpos($admCode, 'mission_daily(') === false,
   'the preview calls mission_daily(), which resets a lapsed streak as a side effect');
ok(strpos($admCode, 'mission_daily_items()') !== false,
   'the preview builds the cycle itself rather than reading the shared one');
ok(strpos($admCode, 'admin_require()') !== false, 'the preview is not behind admin_require()');
$req = strpos($admCode, 'admin_require()');
$hdr = strpos($admCode, "include 'header.php'");
ok($req !== false && $hdr !== false && $req < $hdr,
   'admin_require() runs after header.php, which is after output has begun');
ok(strpos($admCode, 'SK_CLAIM_SHOW({') !== false,
   'the preview never plays the reveal, so the button does nothing');
ok(strpos($admCode, 'SK_CHEST_OPEN()') !== false,
   'the preview cannot switch the strip icon');
ok(strpos($admCode, "classList.remove('is-open')") !== false,
   'the preview cannot reset the strip, so it can only be switched once per reload');
ok(strpos($admCode, "getElementById('adm-chest-day')") !== false,
   'the preview cannot pick a day, so six of the seven consumables are unreachable');
/* Day seven is the only one that pays a trait, and the handoff is the
   one sequencing problem in the feature. A preview that hands a drop
   over on every day previews a collision that cannot happen. */
ok(preg_match('~day === 7 \? drop : null~', $admCode) === 1,
   'the preview hands a trait over on days that do not pay one');
ok(strpos($admCode, 'dhcf_drop_payload(') !== false,
   'the preview invents a trait payload rather than building a real one');
ok(strpos($admCode, 'dhcf_award(') === false,
   'the preview awards a real trait, so previewing the reveal pays out');
/* The art arrives by FTP, so a fresh deploy has markup and no pictures. */
ok(strpos($admCode, "addEventListener('error'") !== false,
   'the preview shows broken frames rather than saying which files are missing');
echo "\nthe dialog has depth, and it borrows the platform's\n";
/*
 * THE SHORTHAND IS THE TRAP, AND IT IS A BAD ONE.
 *
 * polish.css declares --msp-lift and friends, it is ONE <link> in
 * header.php, and ?polish=0 turns it off without a deploy. This file
 * always loads. So `background: var(--msp-lift), #0a1929` here is not
 * the same rule it is in polish.css: with the token missing the whole
 * declaration is invalid at computed-value time, background falls back
 * to its initial value, and the initial value is transparent -- the
 * dialog would lose its surface, its button and its note box the moment
 * anybody added ?polish=0 to a URL.
 *
 * Split across background-color and background-image, a missing token
 * drops only the gradient and the colour underneath survives. Asserted
 * as "no shorthand anywhere in this file carries a token", because the
 * natural thing to do when adding the next panel is to copy the line
 * out of polish.css.
 */
$mcmShort = array();
if (preg_match_all('~(^|[;{\s])background:\s*[^;]*var\(--msp-[^;]*;~m', $modal, $bgm)) {
	foreach ($bgm[0] as $hit) $mcmShort[] = trim($hit);
}
ok(count($mcmShort) === 0,
   'a background shorthand carries a polish token (' . implode(' | ', $mcmShort) . '); '
 . 'with ?polish=0 that panel goes transparent instead of flat');
/* And the colour has to actually be there to survive to. */
foreach (array('#mcm-box' => '#0a1929', '#mcm-close' => '#00c8a0') as $sel => $colour) {
	ok(preg_match('~' . preg_quote($sel, '~') . ' \{[^}]*background-color: ' . preg_quote($colour, '~') . '~s', $modal) === 1,
	   $sel . ' has no background-color under its gradient, so losing the token loses the panel');
}
/*
 * IT BORROWS, IT DOES NOT INVENT. Every panel and button on the
 * platform wears these three; a dialog with its own private idea of
 * depth would be the one lit thing that is lit differently.
 */
foreach (array('--msp-lift', '--msp-lift-soft', '--msp-fill', '--msp-fill-in') as $tok) {
	ok(strpos($modal, 'var(' . $tok . ')') !== false,
	   'the dialog does not use ' . $tok . ', so it is lit by its own rules rather than the platform\'s');
}
/* The soft one is for the TINTED box specifically: a black bottom edge
   over a teal tint is brown, which is why polish.css has two. */
ok(preg_match('~#mcm-trait \{[^}]*var\(--msp-lift-soft\)~s', $modal) === 1,
   'the teal note takes the full lift, whose black bottom edge turns a teal tint brown');
/*
 * THE CHEST STANDS ON THE FLOOR, NOT UNDER IT. The contact shadow is a
 * ::after on the stage, which in tree order paints AFTER the chest --
 * so without a z-index the shadow lands on top of the thing casting it.
 */
ok(preg_match('~\.mcm-chest \{ position: relative; z-index: 1;~', $modal) === 1,
   'the chest is not lifted above the stage pseudo-elements, so its own shadow paints over it');
ok(preg_match('~#mcm-plinth::before \{[^}]*z-index: 0;~s', $modal) === 1,
   'the glow is not pinned below the chest');
ok(preg_match('~#mcm-plinth::before \{[^}]*pointer-events: none;~s', $modal) === 1,
   'the glow can take pointer events, and it lies across the chest');
/*
 * AND THERE IS NO DARK CONTACT SHADOW, which took three rounds of
 * "the chest is still floating" to arrive at.
 *
 * A contact shadow is how an object that BLOCKS light sits on a floor.
 * This chest is a light source -- open, lit from inside, throwing green
 * on everything near it -- so black underneath is not a shadow, it is a
 * hole. Measured down the centre column of the render: at .5 alpha
 * there were nineteen rows of exactly zero green straddling the base,
 * the chest's own glow above them and the pool below, and that dead
 * band WAS the gap. The sides never showed it, because a radial shadow
 * is darkest at its middle.
 */
ok(strpos($modal, '#mcm-plinth::after') === false,
   'the dark contact shadow is back, and under a light source it reads as a hole rather than contact');
/*
 * AND ONE LIGHT, NOT TWO. #mcm-stage used to carry a teal radial of
 * its own -- always on, independent of the chest opening -- so the
 * pool was always being read on top of a second glow with a different
 * centre. Reported as "there's a base glow and then this appears on
 * top of it", and it made the pool impossible to judge. The shade
 * stays; that is the recess, not a light.
 */
$stageBg = preg_match('~#mcm-stage \{.*?\n  box-shadow~s', $modal, $sb) ? $sb[0] : '';
ok($stageBg !== '', 'the #mcm-stage rule is gone');
ok(strpos($stageBg, 'rgba(0,200,160') === false,
   'the stage carries a teal layer of its own again, so the pool is read on top of a second glow');
/*
 * AND THE POOL TOOK OVER WHAT THE STAGE'S RADIAL WAS FOR.
 *
 * That layer was the ambient: something lit under the chest before
 * anything happened. Deleting it without replacing it would have left
 * the floor black until the lid went, so the payoff would be the only
 * moment the light existed. The pool rests at HALF and the open
 * doubles it -- the same ambient job, at the position and angle the
 * owner set, on one light instead of two.
 *
 * Both numbers are read and compared: half is the point, not 0.5 as
 * such, and an open state that is not brighter than the rest state is
 * a reveal with nothing to reveal.
 */
$glowRest = preg_match('~#mcm-plinth::before \{[^}]*opacity: ([0-9.]+);~s', $modal, $gq) ? (float)$gq[1] : -1;
$glowOpen = preg_match('~#mcm-plinth:has\(\.mcm-chest\.is-open\)::before \{ opacity: ([0-9.]+); \}~', $modal, $gw)
          ? (float)$gw[1] : -1;
ok($glowRest > 0,
   'the floor is black until the chest opens, so the light only exists after the payoff');
ok($glowOpen > $glowRest,
   'the open state (' . $glowOpen . ') is no brighter than the rest state (' . $glowRest . '), '
 . 'so there is nothing for the lid to reveal');
ok($glowRest > 0 && abs($glowRest - $glowOpen / 2) < 0.001,
   'the resting glow is ' . $glowRest . ' against an open ' . $glowOpen . ', which is not half');
/* A transition, not an animation: it has to ramp on a browser without
   :has too, where the state simply never arrives. */
ok(preg_match('~#mcm-plinth::before \{[^}]*transition: opacity [0-9.]+s~s', $modal) === 1,
   'the glow snaps between its two levels instead of ramping');
/*
 * AND IT HAS A FLOOR TO CAST ONTO.
 *
 * open.webp's alpha box is 4px in from every edge of 480x497, so at
 * 210x217 the chest's base is under two pixels off the bottom of its
 * own box. Flush to the bottom of the stage there was nowhere for the
 * contact shadow to fall: it spread into the chest on one side and into
 * overflow:hidden on the other, cropped to a hard line exactly where it
 * was meant to be softest.
 *
 * BOTH SIZES, because the phone has its own stage height and dropping
 * the padding there puts the crop straight back on the screen where the
 * dialog is biggest relative to the page.
 */
$stageRule = preg_match('~#mcm-plinth \{[^}]*\}~s', $modal, $sgm) ? $sgm[0] : '';
ok(preg_match('~padding-bottom: (\d+)px~', $stageRule, $sp) === 1 && (int)$sp[1] >= 14,
   'the plinth has no floor under the chest, so its contact shadow is cropped to a hard line');
/*
 * AND THE SHADOW HAS TO BE WIDER THAN THE CHEST. The first one was
 * 196px under a 210px chest, and the chest is z-index 1 -- so the whole
 * shadow sat behind the art and not one pixel of it was ever visible.
 * All that showed was the gap between the base and the pool below it,
 * which read as the chest hovering over its own glow.
 */
/*
 * THE BUTTON WEARS ITS LIGHT, NOT A SECOND BORDER. The sheen inside
 * --msp-fill is the top edge; an inset white line on top of it, and a
 * focus ring offset OUTWARD in pale mint, both read as extra borders
 * drawn around the slab. The ring cannot simply go -- it is the only
 * thing telling a keyboard user where they are, and the dialog moves
 * focus here as it opens -- so it is inset and in the button's own ink.
 */
$closeRule = preg_match('~#mcm-close \{[^}]*\}~s', $modal, $cr) ? $cr[0] : '';
ok($closeRule !== '', 'the #mcm-close rule is gone');
ok(strpos($closeRule, 'inset 0 1px 0 rgba(255,255,255') === false,
   'the button has a white inset line over its own sheen, which reads as a second border');
ok(preg_match('~#mcm-close:focus-visible \{ outline: [^;]*; outline-offset: (-\d+)px~', $modal, $fo) === 1,
   'the focus ring is outside the button, where it reads as a highlight border around it');
ok(preg_match('~#mcm-close:focus-visible~', $modal) === 1,
   'the focus ring is gone, so a keyboard user cannot see where they are');

/*
 * THE GLOW LIES IN THE CHEST'S GROUND PLANE, and both halves of that
 * are measured off open.webp rather than chosen. For each column, the
 * lowest opaque row is where the chest meets the floor; a least-squares
 * line through that footprint gives slope -0.0826 (-4.7 degrees, rising
 * to the right) crossing the art's centre at 88.5% of its height.
 *
 * THE ANGLE. The chest is drawn in three-quarter view, so a level
 * ellipse under it is a floor at a different angle from the one it is
 * standing on -- which reads as hovering over a puddle rather than
 * standing in it. Reported in exactly those words.
 *
 * THE HEIGHT. 88.5% of a 217px chest is 192px, so the ground line sits
 * 25px ABOVE the bottom of the chest's own box. Every version centred
 * on the box bottom put the glow a clear 25px below the feet, which is
 * the gap -- and is why pushing the chest down onto the glow fixed it
 * by hand. A positive `bottom` is the glow sitting up inside the
 * plinth where the feet are; a negative one is the old mistake.
 */
$chestW = preg_match('~\.mcm-chest \{[^}]*width: (\d+)px~s', $modal, $cw) ? (int)$cw[1] : 0;
$poolW  = preg_match('~#mcm-plinth::before \{[^}]*width: (\d+)px~s', $modal, $pw) ? (int)$pw[1] : 0;
ok($poolW > 0 && $chestW > 0 && $poolW > $chestW,
   'the glow is ' . $poolW . 'px under a ' . $chestW . 'px chest, so it never reaches past the art');
/*
 * A RAKE, NOT A PARTICULAR ONE. -4.7 degrees is the chest's base
 * contour; the shipped -1.8 is what looks right dragged against the
 * art, and flatter is correct for a reason the contour cannot know --
 * the pool is light on a floor seen from slightly above, so it
 * foreshortens toward level. What must not come back is a LEVEL
 * ellipse under a chest drawn in three-quarter view, so the check is
 * the sign and a sane range rather than one number.
 */
ok(preg_match('~#mcm-plinth::before \{[^}]*rotate\((-[0-9.]+)deg\)~s', $modal, $gr) === 1
   && (float)$gr[1] <= -0.5 && (float)$gr[1] >= -10,
   'the glow is level, or raked past anything the art supports, under a three-quarter chest');
/*
 * THE CENTRE AND THE DEPTH, COMPUTED RATHER THAN EYEBALLED.
 *
 * `bottom > 0` was the first version of this check and it was a proxy,
 * not the property: it happened to hold while the ellipse was shallow
 * and broke the moment the ellipse got deep enough to do its job, even
 * though the geometry had just become MORE correct. So this works the
 * real numbers out of the declared ones.
 *
 * MIDPOINT 89.2%, DEPTH 18.3%, both off open.webp's alpha channel --
 * for each column, the lowest opaque row, over the chest's footprint.
 *
 * The glow has to be deeper than the footprint, not merely as deep: a
 * 3/4 view has a footprint rather than a contact line (40px of floor on
 * a 217px chest, front-left foot to back-right corner), and an ellipse
 * no deeper than that lights a stripe across the middle and leaves both
 * ends dark. That is what "not deep enough to cover the depth of the
 * box" was.
 */
$MID = 0.892; $DEPTH = 0.183;
$poolSizes = array('base' => $modal);
if (preg_match('~@media \(max-width: 480px\) \{.*?\n\}~s', $modal, $smm)) $poolSizes['phone'] = $smm[0];
ok(count($poolSizes) === 2, 'the small-screen block is gone, so only one size can be checked');
foreach ($poolSizes as $poolWhich => $poolSrc) {
	$g = array();
	preg_match('~#mcm-plinth \{[^}]*height: (\d+)px~s', $poolSrc, $m) && $g['plinth'] = (int)$m[1];
	preg_match('~#mcm-plinth \{[^}]*padding-bottom: (\d+)px~s', $poolSrc, $m) && $g['pad'] = (int)$m[1];
	preg_match('~\.mcm-chest \{[^}]*height: (\d+)px~s', $poolSrc, $m) && $g['chest'] = (int)$m[1];
	preg_match('~#mcm-plinth::before \{[^}]*bottom: (-?\d+)px~s', $poolSrc, $m) && $g['bottom'] = (int)$m[1];
	preg_match('~#mcm-plinth::before \{[^}]*height: (\d+)px~s', $poolSrc, $m) && $g['h'] = (int)$m[1];
	ok(count($g) === 5,
	   'the ' . $poolWhich . ' size does not declare all of plinth height, floor, chest height, '
	 . 'glow offset and glow depth, so none of it can be checked');
	if (count($g) !== 5) continue;
	/* Where the chest actually touches, and where the glow actually is. */
	$chestTop  = $g['plinth'] - $g['pad'] - $g['chest'];
	$footMid   = $chestTop + $g['chest'] * $MID;
	$glowMid   = $g['plinth'] - $g['bottom'] - $g['h'] / 2;
	/*
	 * A BAND, NOT A POINT, and the band is what the bug was.
	 *
	 * This used to require the glow's centre within 8px of the
	 * footprint's midpoint, because that is where the measurement said
	 * it belonged. Dragged against the real art it wants to be 22px
	 * HIGHER -- light under a lit object pushes back under it rather
	 * than pooling at the front edge -- and an assertion that fails on
	 * the look the owner chose is an assertion arguing with the owner.
	 *
	 * So it guards the two failures instead. Below ~92% is the original
	 * bug coming back: the glow sliding down to the bottom of the
	 * chest's BOX, which is 23px below where the chest touches at all.
	 * Above ~70% it has climbed off the floor into the chest's body.
	 * The shipped setting sits at 79%, in the middle of neither edge.
	 */
	$glowPct = ($glowMid - $chestTop) / $g['chest'];
	ok($glowPct >= 0.70 && $glowPct <= 0.92,
	   'the ' . $poolWhich . ' glow is centred ' . round($glowPct * 100) . '% down the chest; '
	 . 'below 70% it is up inside the body, above 92% it has slid back to the bottom of the box, '
	 . 'which is 23% below where the chest touches');
	ok($g['h'] >= $g['chest'] * $DEPTH * 1.8,
	   'the ' . $poolWhich . ' glow is ' . $g['h'] . 'px deep against a '
	 . round($g['chest'] * $DEPTH) . 'px footprint, so it lights a stripe across it and leaves the ends dark');
}
/*
 * AND IT MUST NOT BE inset:0 ON THE PLINTH.
 *
 * A background is clipped to its own element. As a full-bleed layer the
 * pool was a radial centred at 93% of the plinth with a vertical radius
 * of 17%, which runs to 110% -- so the bottom of the glow was cut at
 * the plinth's edge in a hard line. A sized box hanging below the
 * plinth has nothing to clip it; the only clip here is the pane's, and
 * it is far outside.
 */
ok(preg_match('~#mcm-plinth::before \{[^}]*inset: 0~s', $modal) !== 1,
   'the pool is a full-bleed layer again, so its own element crops the bottom of the glow');

$small = preg_match('~@media \(max-width: 480px\) \{.*?\n\}~s', $modal, $smm) ? $smm[0] : '';
ok($small !== '', 'the small-screen block is gone');
ok(preg_match('~#mcm-plinth \{[^}]*padding-bottom: (\d+)px~s', $small, $sp2) === 1 && (int)$sp2[1] >= 14,
   'the phone stage drops the floor, so the crop comes back at the size the dialog fills the screen');

echo "\nthe chest rattles before it opens, on one clock\n";
/*
 * THE BUILD-UP IS THE POINT. The dialog used to open the chest 240ms in
 * and that was the whole of it: a lid swap you could miss. Something
 * tries to get out first, and the rattle gets louder so the pop lands on
 * a pattern the eye has already started following.
 *
 * TWO NUMBERS THAT MUST AGREE. The length lives in the CSS animation and
 * in MCM_SHAKE, and nothing in a browser enforces the match: too short
 * and the chest opens mid-throw, too long and it sits still waiting. So
 * both are read out of the file and compared.
 */
$shakeCss = preg_match('~\.mcm-chest\.is-shaking \{ animation: mcmShake ([0-9.]+)s~', $modal, $sm)
          ? (float)$sm[1] * 1000 : -1;
/*
 * DERIVED, NOT TYPED, so this reads whatever the script actually works
 * out. MCM_SHAKE used to be the literal 660; it is now computed from the
 * wreath's measured peak, because the rattle builds to the sound rather
 * than to a number somebody liked. A check that still looked for a
 * literal reported -1 and failed on correct code.
 */
$sfxHit1 = preg_match('~MCM_SFX_HIT1\s*=\s*(\d+);~', $modal, $h1) ? (int)$h1[1] : -1;
$sfxHit2 = preg_match('~MCM_SFX_HIT2\s*=\s*(\d+);~', $modal, $h2) ? (int)$h2[1] : -1;
$shakeJs  = preg_match('~MCM_SHAKE\s*=\s*MCM_CALM \? 0 : \(MCM_SFX_HIT1 - MCM_SHAKE_AT - 40\);~', $modal)
          ? ($sfxHit1 - (preg_match('~MCM_SHAKE_AT\s*=\s*(\d+);~', $modal, $sa0) ? (int)$sa0[1] : 0) - 40)
          : (preg_match('~MCM_SHAKE\s*=\s*MCM_CALM \? 0 : (\d+);~', $modal, $sj) ? (int)$sj[1] : -1);
ok($shakeCss > 0, 'the rattle has no CSS animation, so the chest just waits and then opens');
ok($shakeJs > 0,  'the script has no rattle duration, so nothing drives the delay');
ok((int)$shakeCss === $shakeJs,
   'the rattle is ' . $shakeCss . 'ms in the CSS and ' . $shakeJs . 'ms in the script, '
 . 'so the chest opens mid-throw or sits still waiting for it');
/* And the lid must not go while the chest is still moving. */
$shakeAt = preg_match('~MCM_SHAKE_AT\s*=\s*(\d+);~', $modal, $sa) ? (int)$sa[1] : -1;
$openExp = preg_match('~MCM_OPEN\s*=\s*MCM_SHAKE \? MCM_SHAKE_AT \+ MCM_SHAKE \+ (\d+) : (\d+);~', $modal, $so);
ok($shakeAt >= 0 && $openExp === 1,
   'the open is no longer expressed from the rattle, so the two can drift apart');
/*
 * IT HAS TO END ON `none`. The class comes off at the same instant the
 * lid swaps; a keyframe that finished mid-throw would snap the chest
 * back four pixels under the burst -- the same class of bug as the glow
 * pulse above, in the other direction.
 */
ok(preg_match('~@keyframes mcmShake \{.*?100%\s*\{ transform: none; \}~s', $modal) === 1,
   'the rattle does not settle to centre, so removing the class jerks the chest as it opens');
/*
 * ONE CLOCK. Everything after the open is written as an offset FROM it,
 * because the rattle moved that moment and three hardcoded timestamps
 * would each have had to move with it.
 */
$bareAt = array();
if (preg_match_all('~\bat\((\d+)[,\s]~', $modal, $bm)) $bareAt = $bm[1];
ok(count($bareAt) === 0,
   'there are hardcoded timings in the sequence (' . implode(', ', $bareAt) . 'ms); '
 . 'they drift the moment the rattle changes length');
/*
 * REDUCED MOTION TAKES THE DELAY, NOT JUST THE ANIMATION. Turning the
 * CSS animation off on its own would leave the dialog staring at a shut
 * chest for two thirds of a second, for exactly the players who asked
 * for less of this.
 */
ok(strpos($modal, "matchMedia('(prefers-reduced-motion: reduce)').matches") !== false,
   'the script does not read the motion preference, so the delay runs for everybody');
/* The block is taken to its own closing brace at column 0, not with
   [^}]* -- that window ends at the FIRST inner rule's brace, two rules
   short of the one being looked for. */
$calm = preg_match('~@media \(prefers-reduced-motion: reduce\) \{.*?\n\}~s', $modal, $cm) ? $cm[0] : '';
ok($calm !== '', 'the reduced-motion block is gone');
ok(strpos($calm, '.mcm-chest.is-shaking { animation: none; }') !== false,
   'the rattle still animates under reduced motion');
/* And a second claim must not open on a chest still wearing the class. */
ok(preg_match('~classList\.remove\(\x27is-shaking\x27, \x27is-open\x27~', $modal) === 1,
   'reset leaves is-shaking on, so the next claim starts on a chest mid-rattle');

echo "\nthe idle pulse joins the opening without a jump\n";
/*
 * THE STUTTER. The glow transitions to opacity 1 as the chest opens,
 * and .is-settled then starts mcmPulse with a 1.2s delay. There is no
 * fill-mode, so the light holds at 1 through that delay and the
 * animation's FIRST keyframe is what it snaps to. With 0% at .82 that
 * was a one-frame drop from the brightest thing on screen, a second and
 * a half after the chest opened.
 *
 * The invariant is not "0% is 1" -- it is "0% is whatever the state it
 * takes over from left behind". Both are read here, so moving one
 * without the other fails rather than reintroducing the jump quietly.
 */
$glowOpen = preg_match('~\.mcm-chest\.is-open \.mcm-glow \{[^}]*opacity:\s*([0-9.]+)~s', $modal, $gm)
          ? $gm[1] : '';
ok($glowOpen !== '', 'the opening no longer sets the glow opacity, so there is nothing to join to');
/* Bound by what FOLLOWS -- the line break after the block -- rather than
   by "no closing brace in between", which stops at the first inner one
   and cuts the 50% stop's own brace off the capture. */
$pulse = preg_match('~@keyframes mcmPulse \{(.*?)\}\n~s', $modal, $pm) ? $pm[1] : '';
ok($pulse !== '', 'the mcmPulse keyframes are gone');
ok(preg_match('~0%,\s*100% \{ opacity: ' . preg_quote($glowOpen, '~') . '; \}~', $pulse) === 1,
   'the pulse does not start on the opening\'s own opacity (' . $glowOpen . '), '
 . 'so it snaps the glow the moment the loop begins');
/* And it has to actually go somewhere: both ends equal with no dip is a
   loop that animates nothing. */
ok(preg_match('~50% \{ opacity: ([0-9.]+);~', $pulse, $hm) === 1 && (float)$hm[1] < (float)$glowOpen,
   'the middle of the pulse is not dimmer than its ends, so the light either does not breathe '
 . 'or breathes up from full and clips');
/* The delay is what makes the join visible at all; if it ever goes, the
   animation starts while the transition is still running and the above
   stops being the thing that matters. */
ok(preg_match('~animation: mcmPulse [0-9.]+s ease-in-out [0-9.]+s infinite~', $modal) === 1,
   'the pulse no longer waits out the opening, so it fights the transition that is still running');

echo "\nthe dialog looks the same wherever it is mounted\n";
/*
 * THE HOST PAGE RESTYLED THE DIALOG, TWICE.
 *
 * missions-daily.php includes this inside .ms-daily, and missions.php
 * carries `.ms-daily, .ms-daily * { text-align: left }`. That universal
 * descendant selector hits every element in here DIRECTLY, and a direct
 * rule always beats an inherited one -- so text-align:center on #mcm-box
 * centred nothing, and the kicker, the title, the two reward labels and
 * the countdown all shipped flush left while admin-chest.php, which
 * mounts the same markup outside .ms-daily, looked exactly as designed.
 *
 * The same thing ran the other way on the title: it is an <h4>, and
 * admin-css.php has `.adm h4 { text-transform: uppercase }`, so the
 * PREVIEW said CLAIMED and the page said Claimed.
 *
 * Both are the flexbox.css trap in miniature. A component that can be
 * mounted anywhere states its own alignment and its own case.
 */
$mcmBox = '';
if (preg_match('~#mcm-box, #mcm-box \* \{[^}]*\}~s', $modal, $mb)) $mcmBox = $mb[0];
ok(strpos($mcmBox, 'text-align: center') !== false,
   'only #mcm-box is centred, so .ms-daily * flushes every child of it left');
ok(preg_match('~#mcm-title \{[^}]*text-transform:\s*none~s', $modal) === 1,
   'the title takes its case from the host page, so the preview and the page disagree');
/* The host rule this defends against is real and still there; if it ever
   goes, this guard is why the dialog stopped depending on it. */
$msrc = (string) file_get_contents(__DIR__ . '/missions.php');
ok(strpos($msrc, '.ms-daily, .ms-daily * { text-align: left; }') !== false,
   'the .ms-daily left-align rule is gone, so this guard is documenting a trap that no longer exists');
ok(strpos((string) file_get_contents(__DIR__ . '/admin-css.php'), 'h4{') !== false,
   'admin-css no longer styles h4, so the case guard is documenting a trap that no longer exists');

$tabs = (string) file_get_contents(__DIR__ . '/admin-lib.php');
ok(strpos($tabs, "'chest' => array('admin-chest.php'") !== false,
   'there is no admin tab, so the preview cannot be reached');

echo "\nthe claim has two sounds, on the two beats it already had\n";
/*
 * The wreath lands as the modal opens -- the reward is announced before
 * the chest does anything -- and the diamonds land ON TOP of it when the
 * lid goes. Layered, not sequenced.
 */
ok(strpos($modal, "'sounds/quest_award_wreath.ogg'") !== false,
   'the modal has no sound as it opens');
ok(strpos($modal, "'sounds/diamond_mine_treasurefind_diamonds.ogg'") !== false,
   'the lid opens in silence');
ok(strpos($modal, "mcmSfx('open');") !== false, 'the opening sound is never played');

/*
 * ON THE SAME CLOCK AS THE PICTURES. MCM_OPEN is the lid and it moves
 * whenever the rattle does -- a hardcoded delay for the second sound
 * would drift away from the thing it is meant to be hitting. The same
 * rule the rest of this file already enforces for the visuals.
 */
preg_match('~at\(MCM_OPEN, function \(\) \{(.*?)\n\t\t\}\);~s', $modal, $m_lid);
$lid = count($m_lid) === 2 ? $m_lid[1] : '';
ok($lid !== '', 'the lid beat could not be lifted');
ok($lid !== '' && strpos($lid, "mcmSfx('lid')") !== false,
   'the second sound is not fired on the lid beat, so it can drift away from the lid');
/* ON the beat, not a delay from it. Being inside the block is not
   enough -- a setTimeout in there is a second hardcoded timestamp of
   exactly the kind this file already refuses for the visuals, and it
   would drift the moment the rattle length changes. */
ok($lid !== '' && strpos($lid, 'setTimeout') === false,
   'the lid beat defers its sound, which is a hardcoded timestamp that drifts when the rattle moves');

/* REDUCED MOTION TAKES THE NOISE WITH IT. Somebody who asked for less of
   this did not ask for a quieter version of all of it. */
preg_match('~function mcmSfx\(key\) \{(.*?)\n\t\}~s', $modal, $m_sfx);
$sfxb = count($m_sfx) === 2 ? $m_sfx[1] : '';
ok($sfxb !== '', 'mcmSfx() could not be lifted');
ok($sfxb !== '' && strpos($sfxb, 'if (MCM_CALM) return;') !== false,
   'the sounds play under prefers-reduced-motion');
/*
 * AND IT IS ALLOWED TO FAIL. Autoplay policy, a missing file, a codec a
 * browser will not take -- none of those may stop a claim being shown.
 * The chest is the reveal; the sound is a bonus on top of it.
 */
ok($sfxb !== '' && strpos($sfxb, 'try {') !== false && strpos($sfxb, 'catch') !== false,
   'a sound that will not play can throw into the claim reveal');
ok($sfxb !== '' && strpos($sfxb, 'pr.catch') !== false,
   'the play() promise is unhandled, so a blocked autoplay logs an unhandled rejection every claim');

/*
 * THE PICTURES ARE CUT TO THE SOUND.
 *
 * quest_award_wreath.ogg is not a hit, it is a two-second swell that
 * lands and then a second smaller hit just after. Measured off the file
 * at 10ms resolution: the swell starts at 0.30s, peaks at 2.34s -- the
 * loudest frame in the whole file -- and hits again at 2.93s.
 *
 * So the rattle builds across the swell, the lid goes on the peak with
 * the diamonds layered on it, and the icons land on the second hit.
 * These are measurements, not preferences, and the arithmetic below is
 * what keeps them true when any one of them is edited.
 */
ok($sfxHit1 > 0 && $sfxHit2 > $sfxHit1,
   'the measured hits are missing or out of order, so nothing below is checking a sync');

/*
 * THE MEASUREMENT ITSELF, PINNED TO A BAND.
 *
 * Everything below derives from these two numbers, so arithmetic alone
 * cannot catch them being wrong -- MCM_KIDS_AT is HIT2 minus HIT1, which
 * makes "the icons land on the second hit" true by construction whatever
 * the hits say. Only the file can contradict them.
 *
 * Read from quest_award_wreath.ogg at 10ms resolution (mono, 8kHz, RMS
 * per frame, onsets by positive rise over a 60ms window): the swell
 * begins at 0.30s, the loudest frame in the file is 2.34s, and the
 * second onset is 2.93s at 43% of peak. The bands are +/- 40ms, which is
 * tighter than a frame at 24fps and looser than the measurement's own
 * resolution.
 *
 * IF THE SOUND IS REPLACED, RE-MEASURE -- do not widen these.
 */
ok($sfxHit1 >= 2300 && $sfxHit1 <= 2380,
   'MCM_SFX_HIT1 is ' . $sfxHit1 . 'ms; the wreath peaks at 2340ms, so the lid lands off the hit');
ok($sfxHit2 >= 2890 && $sfxHit2 <= 2970,
   'MCM_SFX_HIT2 is ' . $sfxHit2 . 'ms; the wreath hits again at 2930ms, so the icons land off it');
ok($sfxHit2 - $sfxHit1 >= 550 && $sfxHit2 - $sfxHit1 <= 630,
   'the gap between the two hits is ' . ($sfxHit2 - $sfxHit1) . 'ms, not the measured 590ms');

/* THE LID LANDS ON THE FIRST HIT. MCM_OPEN is built from the rattle, so
   this is the whole chain: start + length + the held beat == the peak. */
ok($shakeAt + $shakeJs + 40 === $sfxHit1,
   'the lid opens at ' . ($shakeAt + $shakeJs + 40) . 'ms and the wreath peaks at '
 . $sfxHit1 . 'ms, so the chest opens into silence or after it');

/* AND THE ICONS LAND ON THE SECOND. Expressed as a distance from the
   lid, because everything after the open already is. */
$kidsAt = preg_match('~MCM_KIDS_AT\s*=\s*MCM_CALM \? 190 : \(MCM_SFX_HIT2 - MCM_SFX_HIT1\);~', $modal)
        ? ($sfxHit2 - $sfxHit1) : -1;
ok($kidsAt > 0, 'the icon landing is not derived from the two hits, so it cannot stay in sync');
ok($sfxHit1 + $kidsAt === $sfxHit2,
   'the icons land at ' . ($sfxHit1 + $kidsAt) . 'ms and the second hit is at '
 . $sfxHit2 . 'ms');
ok(strpos($modal, 'at(MCM_OPEN + MCM_KIDS_AT,') !== false,
   'the icons fly on a hardcoded offset again rather than on the measured hit');

/*
 * REDUCED MOTION KEEPS THE OLD SHAPE, which is the right answer rather
 * than a convenient one: the sound does not play at all for those
 * players, so there is nothing to synchronise to, and making them wait
 * 2.3 seconds for a chest to open would be the opposite of what they
 * asked for.
 */
ok(strpos($modal, 'MCM_KIDS_AT  = MCM_CALM ? 190') !== false,
   'reduced motion inherits the sound-synced timing, so it waits on an animation it is not getting');

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
