<?php
/* dhc-layerorder-harness.php — CLI only. Needs node on PATH.
 *
 * ONE QUESTION: does the canvas draw a Fighter in the same order the server
 * does?
 *
 * There are two implementations of the draw order and there have to be. PHP's
 * dhcf_layer_order() serves every server renderer -- the roster cards, the
 * Collection, the Discord embed, the Arena portrait, the full-size download --
 * and the assembler's layerOrder() in JS serves the canvas you actually build
 * on. They are fed the SAME constants (the page prints them with json_encode
 * out of dhcfighters-config.php), so what can drift is the logic, and it has:
 * armsBehindTorso() once carried an armless-variant escape hatch that PHP had
 * no equivalent of, and the same Fighter drew two different ways.
 *
 * So this extracts the real JS out of dhc-assembler.php -- brace-matched, not
 * reimplemented -- feeds it the real constants, and compares it against the
 * real PHP over a matrix of trait sets built to hit every exception.
 *
 * It also checks the two slot lists agree. $dhc_slots in the assembler and
 * dhcf_slots() in the config are separate declarations of the base order, and
 * every exception is expressed as a move relative to it.
 *
 * Usage: php dhc-layerorder-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

require_once __DIR__ . '/dhcfighters-lib.php';

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

$src = file_get_contents(__DIR__ . '/dhc-assembler.php');

/* ---------- 1. the two base orders are the same list ----------------------- */
preg_match('/\$dhc_slots = array\((.*?)\n\);/s', $src, $m);
preg_match_all("/^\t'([a-zA-Z0-9]+)'\s*=>/m", isset($m[1]) ? $m[1] : '', $sm);
$canvasSlots = $sm[1];
$phpSlots    = dhcf_slots();
printf("base order\n  canvas %s\n  php    %s\n",
	implode(',', $canvasSlots), implode(',', $phpSlots));
ok($canvasSlots === $phpSlots,
   'the canvas and the config disagree about the base draw order, so EVERY '
 . 'exception below is measured from a different starting point');

/* ---------- 2. pull the real JS out of the page ---------------------------- */
/** Brace-match a function out of the source, so this is the shipped code. */
function extract_fn($src, $name) {
	$at = strpos($src, 'function ' . $name . '(');
	if ($at === false) return '';
	$open = strpos($src, '{', $at);
	if ($open === false) return '';
	$d = 0;
	for ($i = $open, $n = strlen($src); $i < $n; $i++) {
		if ($src[$i] === '{') $d++;
		elseif ($src[$i] === '}') { $d--; if ($d === 0) return substr($src, $at, $i - $at + 1); }
	}
	return '';
}
$js = '';
/* companionTwoPart joins the list for the same reason the other two are on it:
   layerOrder() calls it, so lifting layerOrder() alone gives node a function
   that references something that does not exist. */
foreach (array('layerOrder', 'armsBehindTorso', 'effectBehindTorso',
               'companionTwoPart', 'companionBlocked', 'twoPartHandsAfter',
               'armsOverHeadgear') as $fn) {
	$body = extract_fn($src, $fn);
	ok($body !== '', "found $fn() in dhc-assembler.php — if this fails the page was "
	                . 'restructured and this harness is testing nothing');
	$js .= $body . "\n";
}
/* No PHP may survive the extraction: a <?php echo ?> inside one of these would
   mean the harness is running something the browser never sees. */
ok(strpos($js, '<?') === false, 'the extracted JS still contains PHP');

/* ---------- 3. the matrix -------------------------------------------------- */
/* Each case is named for the exception it exists to pin down. */
$cover  = DHCF_COVER_EFFECTS[0];
$cover2 = isset(DHCF_COVER_EFFECTS[1]) ? DHCF_COVER_EFFECTS[1] : $cover;
/*
 * BOTH LISTS ARE EMPTY NOW, and that is the interesting case to get right.
 *
 * These used to be DHCF_COMPANION_UNDER[0] and DHCF_COMPANION_OVER_COVER[0].
 * The moment Code Sea Predator left both lists, that read null -- so five
 * rows below quietly became "a Fighter with no companion", passed, and
 * proved nothing. A harness that reports a rule as tested when the rule has
 * no members is worse than one that does not test it, because the row is
 * still there and still green.
 *
 * So: with no members, the ROWS ARE DROPPED and the gap is announced, and
 * what gets asserted instead is that both code paths still EXIST in both
 * implementations. An empty list is a list waiting for a member, not a
 * reason to let the mechanism rot.
 */
$under  = DHCF_COMPANION_UNDER      ? DHCF_COMPANION_UNDER[0]      : '';
$over   = DHCF_COMPANION_OVER_COVER ? DHCF_COMPANION_OVER_COVER[0] : '';
if ($under === '' || $over === '') {
	printf("  NOTE: DHCF_COMPANION_UNDER has %d member(s) and DHCF_COMPANION_OVER_COVER %d.\n",
		count(DHCF_COMPANION_UNDER), count(DHCF_COMPANION_OVER_COVER));
	echo "        Rows needing one are SKIPPED below -- those rules are unexercised,
"
	   . "        not passing. The code paths are asserted to still exist instead.\n";
	/* The mechanism, in both implementations. Comments stripped: a rule that
	   survives only in prose is a rule that has been deleted. */
	$cfgCode = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '',
	                        file_get_contents(__DIR__ . '/dhcfighters-config.php'));
	$asmCode = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '',
	                        file_get_contents(__DIR__ . '/dhc-assembler.php'));
	ok(strpos($cfgCode, 'DHCF_COMPANION_UNDER, true)') !== false,
	   'dhcf_layer_order() no longer demotes a companion at all, so refilling '
	 . 'DHCF_COMPANION_UNDER would do nothing');
	ok(strpos($asmCode, 'COMPANION_UNDER.indexOf(') !== false,
	   'the canvas no longer demotes a companion, so it would disagree with PHP '
	 . 'the moment the list has a member again');
	ok(strpos($cfgCode, 'DHCF_COMPANION_OVER_COVER, true)') !== false,
	   'dhcf_layer_order() no longer moves a cover below a demoted companion');
	ok(strpos($asmCode, 'COMPANION_OVER_COVER.indexOf(') !== false,
	   'the canvas no longer moves a cover below a demoted companion');
} else if ($under === $over) {
	echo "  (only one demoted companion, so two rows below coincide)\n";
}
$behind = DHCF_EFFECTS_BEHIND_TORSO[0];
$backarm = DHCF_ARMS_BEHIND_TORSO[0];
$onearm  = array_keys(DHCF_ONE_ARM)[0];
$armsover = DHCF_ARMS_OVER_COMPANION[0];   // dh-vision-shoulder-cam
$plain = array('background' => 'bg', 'torso' => 'body', 'head' => 'face',
               'headgear' => 'hat', 'arms' => 'limbs', 'weapon' => 'axe-ish');

/* array_merge, NOT the + union operator: + keeps the left-hand value for a key
   that already exists, so "arms behind torso" silently kept $plain's ordinary
   arms and tested nothing. */
function ms_case($plain, $extra) { return array_merge($plain, $extra); }
$twoPart = DHCF_COMPANION_TWO_PART ? DHCF_COMPANION_TWO_PART[0] : 'golden-isopunk';
$sgGear = array_keys(DHCF_ARMS_OVER_HEADGEAR)[0];
$sgArms = DHCF_ARMS_OVER_HEADGEAR[$sgGear][0];
$rk = array_keys(DHCF_COMPANION_EXCLUDED_BY_HEAD);
$refuseHead = $rk ? $rk[0] : 'beheaded-cyborg';
$cases = array(
	'nothing special'           => $plain,
	/* The ATTACHED companion. Its body goes behind the TORSO rather than
	   merely behind the arms, which is a different move from the demoted
	   case below and has to agree between PHP and the canvas. Its front half
	   is not a slot, so it cannot appear here -- dhcf_layers() is asserted
	   separately further down. */
	'attached companion'        => ms_case($plain, array('companion' => $twoPart)),
	'attached + cover'          => ms_case($plain, array('companion' => $twoPart,
	                                                     'effects1'  => $cover)),
	/* The five exceptions, each from the sandbox. The canvas and PHP have to
	   agree on every one of them or a Fighter looks different where it was
	   built from where it is shown. */
	'attached, wing torso'      => ms_case($plain, array('companion' => $twoPart,
	                                                     'torso' => DHCF_TWOPART_BODY_OVER_TORSO[0])),
	'attached, arms over claws' => ms_case($plain, array('companion' => $twoPart,
	                                                     'arms' => DHCF_TWOPART_HANDS_UNDER_ARMS[0])),
	'attached, demon over arms' => ms_case($plain, array('companion' => $twoPart,
	                                                     'arms' => DHCF_TWOPART_OVER_ARMS[0])),
	'attached, head refuses it' => ms_case($plain, array('companion' => $twoPart,
	                                                     'head' => $refuseHead)),
	/* THE SAMURAI PAIR, and the three combinations it could disturb. The pair
	   itself lifts the arms over the headgear; everything else that touches
	   either piece must be left exactly where it was. */
	'samurai pair'              => ms_case($plain, array('headgear' => $sgGear,
	                                                     'arms' => $sgArms)),
	'samurai gear, other arms'  => ms_case($plain, array('headgear' => $sgGear,
	                                                     'arms' => 'skeletal-stabber')),
	'samurai arms, other gear'  => ms_case($plain, array('headgear' => 'tank-helmet',
	                                                     'arms' => $sgArms)),
	'samurai pair + demon'      => ms_case($plain, array('headgear' => $sgGear,
	                                                     'arms' => $sgArms,
	                                                     'companion' => $twoPart)),
	'samurai pair + companion'  => ms_case($plain, array('headgear' => $sgGear,
	                                                     'arms' => $sgArms,
	                                                     'companion' => 'golden-isopunk')),
	'samurai pair + cover'      => ms_case($plain, array('headgear' => $sgGear,
	                                                     'arms' => $sgArms,
	                                                     'effects1' => $cover)),
	'samurai pair + cam'        => ms_case($plain, array('headgear' => $sgGear,
	                                                     'arms' => $sgArms,
	                                                     'companion' => 'dh-vision-shoulder-cam')),
	'ordinary companion'        => ms_case($plain, array('companion' => 'golden-isopunk')),
	'cover, ordinary companion' => ms_case($plain, array('companion' => 'golden-isopunk',
	                                                     'effects1'  => $cover)),
	/* THESE FOUR ONLY EVER USED A COMPANION AS SCENERY. They are about the
	   effects slots, the behind-torso arm and the one-sided arm; which
	   companion is on is irrelevant to every one of them, and pinning them to
	   a demoted one made four rules hostage to a list that is now empty. An
	   ordinary companion exercises them exactly as well. */
	'cover in slot 2 only'      => ms_case($plain, array('companion' => 'golden-isopunk',
	                                                     'effects2'  => $cover)),
	'behind-torso fx wins'      => ms_case($plain, array('companion' => 'golden-isopunk',
	                                                     'effects1'  => $behind)),
	'arms behind torso'         => ms_case($plain, array('companion' => 'golden-isopunk',
	                                                     'arms'      => $backarm,
	                                                     'effects1'  => $cover)),
	'one-sided arm'             => ms_case($plain, array('companion' => 'golden-isopunk',
	                                                     'arms'      => $onearm,
	                                                     'effects1'  => $cover)),
	/* THE SABER PIPE over the cam it is bolted beside, plus the two
	   neighbours that must NOT move. */
	'saber + cam'               => ms_case($plain, array('headgear' => 'saber-tech-headgear',
	                                                     'companion' => 'dh-vision-shoulder-cam')),
	'saber + ordinary pet'      => ms_case($plain, array('headgear' => 'saber-tech-headgear',
	                                                     'companion' => 'golden-isopunk')),
	'other gear + cam'          => ms_case($plain, array('headgear' => 'ww2-helmet',
	                                                     'companion' => 'dh-vision-shoulder-cam')),
	/* CODE SEA PREDATOR, which wants the same two things as the cam and one
	   more: above the head and headgear, under ordinary arms, but OVER the
	   turret limbs, which would otherwise swallow it. */
	/* The head draws under the torso, except for a head with no neck to put
	   into the collar -- a severed stump whose wires fall across the chest. */
	'head under torso'          => $plain,
	'headless stump over torso' => ms_case($plain, array('head' => DHCF_HEADS_OVER_TORSO[0])),
	'sea predator'              => ms_case($plain, array('companion' => 'code-sea-predator')),
	'sea predator + turret'     => ms_case($plain, array('companion' => 'code-sea-predator',
	                                                     'arms' => 'turret-blue-cyborg-limbs')),
	'cam + turret'              => ms_case($plain, array('companion' => 'dh-vision-shoulder-cam',
	                                                     'arms' => 'turret-blue-cyborg-limbs')),
	/* The shoulder cam: above head and headgear (so NOT demoted) but under
	   the arms, which is only expressible by moving the arms to the front. */
	'shoulder cam'              => ms_case($plain, array('companion' => $armsover)),
	'shoulder cam, no arms'     => array('background' => 'bg', 'torso' => 'body',
	                                     'head' => 'face', 'headgear' => 'hat',
	                                     'companion' => $armsover),
	'shoulder cam + rear arms'  => ms_case($plain, array('companion' => $armsover,
	                                                     'arms'      => $backarm)),
	'shoulder cam + cover'      => ms_case($plain, array('companion' => $armsover,
	                                                     'effects1'  => $cover)),
	'no companion at all'       => ms_case($plain, array('effects1'  => $cover)),
	'cover, no other traits'    => array('background' => 'bg', 'torso' => 'body',
	                                     'head' => 'face', 'effects1' => $cover),
);
/* THE DEMOTION ROWS, built only when the lists have a member. Appended rather
   than written inline so an empty list leaves no row at all -- a case built on
   a missing fixture is a Fighter with no companion, which passes and proves
   nothing. That is exactly what happened when Code Sea Predator left. */
if ($under !== '') {
	$cases['demoted companion']         = ms_case($plain, array('companion' => $under));
	$cases['cover + demoted companion'] = ms_case($plain, array('companion' => $under,
	                                                            'effects1'  => $cover));
}
if ($over !== '') {
	$cases['cover + over-cover comp'] = ms_case($plain, array('companion' => $over,
	                                                          'effects1'  => $cover));
	$cases['plain fx + over-cover']   = ms_case($plain, array('companion' => $over,
	                                                          'effects1'  => 'electricity'));
	$cases['two covers + over-cover'] = ms_case($plain, array('companion' => $over,
	                                                          'effects1'  => $cover,
	                                                          'effects2'  => $cover2));
}

/* ---------- 4. run the canvas's own code ----------------------------------- */
$prelude = "var SLOTS = " . json_encode(array_map(function ($k) { return array('key' => $k); },
                                                  $canvasSlots)) . ";\n"
         . "var customOrder = null;\n"
         . "var COMPANION_UNDER = "      . json_encode(DHCF_COMPANION_UNDER)      . ";\n"
         . "var ARMS_BEHIND_TORSO = "    . json_encode(DHCF_ARMS_BEHIND_TORSO)    . ";\n"
         . "var EFFECTS_BEHIND_TORSO = " . json_encode(DHCF_EFFECTS_BEHIND_TORSO) . ";\n"
         . "var ARMS_OVER_COMPANION = " . json_encode(DHCF_ARMS_OVER_COMPANION) . ";\n"
         . "var ARMS_UNDER_COMPANION = " . json_encode(DHCF_ARMS_UNDER_COMPANION) . ";\n"
         . "var HEADGEAR_OVER_COMPANION = " . json_encode(DHCF_HEADGEAR_OVER_COMPANION) . ";\n"
         . "var HEADS_OVER_TORSO = " . json_encode(DHCF_HEADS_OVER_TORSO) . ";\n"
         . "var COVER_EFFECTS = "        . json_encode(DHCF_COVER_EFFECTS)        . ";\n"
         . "var COMPANION_OVER_COVER = " . json_encode(DHCF_COMPANION_OVER_COVER) . ";\n"
         . "var COMPANION_TWO_PART = "   . json_encode(array_values(DHCF_COMPANION_TWO_PART)) . ";\n"
         . "var TP_BODY_OVER_TORSO = "   . json_encode(array_values(DHCF_TWOPART_BODY_OVER_TORSO)) . ";\n"
         . "var TP_HANDS_UNDER_ARMS = "  . json_encode(array_values(DHCF_TWOPART_HANDS_UNDER_ARMS)) . ";\n"
         . "var TP_OVER_ARMS = "         . json_encode(array_values(DHCF_TWOPART_OVER_ARMS)) . ";\n"
         . "var TP_HANDS_OVER_GEAR = "   . json_encode(array_values(DHCF_TWOPART_HANDS_OVER_HEADGEAR)) . ";\n"
         . "var COMPANION_BLOCKED = "    . json_encode(DHCF_COMPANION_EXCLUDED_BY_HEAD) . ";\n"
         . "var ARMS_OVER_GEAR = "       . json_encode(DHCF_ARMS_OVER_HEADGEAR) . ";\n"
         . "var sel = {};\n";
$driver = $prelude . $js . "
var CASES = " . json_encode($cases) . ";
var out = {};
for (var name in CASES) {
  sel = {};
  for (var k in CASES[name]) sel[k] = CASES[name][k];
  out[name] = layerOrder().map(function (s) { return s.key; })
                          .filter(function (k) { return !!sel[k]; });
}
var HANDS = {};
[TP_HANDS_OVER_GEAR[0], '666-demon-headgear'].forEach(function (g) {
  sel = { headgear: g };
  HANDS[g] = twoPartHandsAfter();
});
console.log(JSON.stringify({ order: out, handsAfter: HANDS }));
";
/*
 * EVERY CONSTANT THE REAL CODE USES HAS TO BE IN THE PRELUDE. Add a rule to
 * layerOrder() that reads a new constant and this harness dies with a bare
 * ReferenceError from node -- which is loud, but says nothing about what is
 * missing. Naming it here turns that into a sentence. (It has happened once:
 * ARMS_OVER_COMPANION.)
 */
/* COMMENTS FIRST. The scan looks for ALL-CAPS tokens, and prose is full of
   them -- a comment reading "goes behind the TORSO" was enough to make this
   demand a constant called TORSO and fail a working change. Only the code
   can name a constant the driver has to define. */
$jsCode = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '', $js);
preg_match_all('/\b([A-Z][A-Z0-9_]{3,})\b/', $jsCode, $cm);
foreach (array_unique($cm[1]) as $name) {
	ok(strpos($prelude, 'var ' . $name . ' =') !== false,
	   "the extracted JS reads $name and the harness prelude does not define it -- "
	 . 'add it from the matching DHCF_ constant, or the comparison cannot run');
}

$tmp = sys_get_temp_dir() . '/dhc-layerorder-' . getmypid() . '.js';
file_put_contents($tmp, $driver);
$raw = shell_exec('node ' . escapeshellarg($tmp) . ' 2>&1');
@unlink($tmp);
$node   = json_decode((string)$raw, true);
$canvas = is_array($node) && isset($node['order']) ? $node['order'] : null;
$handsAfter = is_array($node) && isset($node['handsAfter']) ? $node['handsAfter'] : array();
if (!is_array($canvas)) {
	echo "  node produced nothing usable:\n" . substr((string)$raw, 0, 900) . "\n";
	ok(false, 'could not run the canvas layerOrder() — the comparison did NOT happen');
	echo "\nFAILED: $fail check(s)\n";
	exit(1);
}

/* ---------- 5. compare ------------------------------------------------------ */
/* ---------------------------------------------------------------------------
 * THE ATTACHED COMPANION'S SECOND LAYER
 *
 * layerOrder() deals in SLOTS, and the front half is not one -- it is derived
 * by dhcf_layers(), the same way the single-sided-arm restore is. So it has
 * to be asserted on the layer list itself.
 * ------------------------------------------------------------------------- */
if (DHCF_COMPANION_TWO_PART) {
	$tp   = DHCF_COMPANION_TWO_PART[0];
	$root = __DIR__ . '/dhc/web';

	$ls = dhcf_layers(array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02',
	                        'headgear' => '666-demon-headgear', 'arms' => 'connected-protector-limbs',
	                        'companion' => $tp), $root);
	$cats = array_map(function ($l) { return $l['cat']; }, $ls);
	$i = function ($c) use ($cats) { return array_search($c, $cats, true); };

	/* WHICHEVER TORSO LAYER EXISTS. With arms equipped the torso is drawn from
	   torso-noarms, so looking for 'torso' alone returns false and the
	   comparison silently becomes "0 < false". Find the first layer of either
	   kind instead. */
	$torsoAt = null;
	foreach ($cats as $ix => $c) {
		if ($c === 'torso' || $c === 'torso-noarms') { $torsoAt = $ix; break; }
	}
	ok($torsoAt !== null, 'the test build has no torso layer at all');
	ok($i('companion') !== false && $torsoAt !== null && $i('companion') < $torsoAt,
	   'the attached companion body is not behind the torso');
	ok($i('companion-front') !== false,
	   'the attached companion never gets its front half');
	ok($i('companion-front') > $i('head'),
	   'the claws draw under the head, where the grip does not read at all');
	ok($i('companion-front') < $i('headgear'),
	   'the claws draw over the headgear instead of under it');
	/* Same slug, two directories -- the pairing torso-noarms already uses. */
	foreach ($ls as $l) if ($l['cat'] === 'companion-front') {
		ok($l['slug'] === $tp, 'the front half does not pair with its companion by slug');
	}

	/* THE FIVE HEADGEARS THE CLAWS GO OVER. Established in the sandbox: the
	   claws reach around the skull, and on these five helmets they read as
	   gripping the helmet rather than as buried under it. Every one of them,
	   not just the first -- the list is the rule. */
	foreach (DHCF_TWOPART_HANDS_OVER_HEADGEAR as $gear) {
		$lg = dhcf_layers(array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02',
		                        'headgear' => $gear, 'companion' => $tp), $root);
		$gc = array_map(function ($l) { return $l['cat']; }, $lg);
		$gi = function ($c) use ($gc) { return array_search($c, $gc, true); };
		ok($gi('companion-front') !== false && $gi('headgear') !== false
		   && $gi('companion-front') > $gi('headgear'),
		   "the claws draw UNDER $gear, which is on the list of headgear they grip");
	}
	/* And the anchor the canvas picks has to be the same one, for both kinds
	   of headgear -- that is the whole of the rule the two share. */
	foreach (array(DHCF_TWOPART_HANDS_OVER_HEADGEAR[0] => 'headgear',
	/* 'torso', not 'head': the head draws UNDER the torso now, so anchoring the
	   claws on it would put them behind the chest. The five helmets on
	   DHCF_TWOPART_HANDS_OVER_HEADGEAR are unchanged -- those still want the
	   claws over the helmet, which is later again. */
	               '666-demon-headgear'                => 'torso') as $gear => $want) {
		ok(dhcf_twopart_hands_after(array('headgear' => $gear)) === $want,
		   "php anchors the claws on '" . dhcf_twopart_hands_after(array('headgear' => $gear))
		 . "' with $gear, not '$want'");
		ok(isset($handsAfter[$gear]) && $handsAfter[$gear] === $want,
		   "the canvas anchors the claws on '"
		 . (isset($handsAfter[$gear]) ? $handsAfter[$gear] : '?')
		 . "' with $gear, so it draws them in a different place from the server");
	}

	/* A HEAD THAT REFUSES IT LOSES BOTH HALVES. The claws are a derived layer,
	   so dropping only the slot leaves a pair of hands gripping nothing -- and
	   dropping only the claws leaves a demon with no grip. Neither may appear. */
	foreach (DHCF_COMPANION_EXCLUDED_BY_HEAD as $rh => $rcomps) {
		foreach ($rcomps as $rc) {
			$lr = dhcf_layers(array('torso' => 'amplified-dreadnought', 'head' => $rh,
			                        'companion' => $rc), $root);
			$rcat = array_map(function ($l) { return $l['cat']; }, $lr);
			ok(!in_array('companion', $rcat, true),
			   "$rh still gets the body of $rc, which it has no room for");
			ok(!in_array('companion-front', $rcat, true),
			   "$rh still gets the claws of $rc, gripping a head that is not there");
		}
	}

	/* MEMBERSHIP, NOT JUST SHAPE. Every list above is walked by a loop, so a
	   list someone trims is a rule that silently stops being tested. These
	   entries were each established by eye in the sandbox and are the reason
	   the lists exist, so name them. */
	foreach (array('merged', 'ww2-helmet', 'cyber-insekt-helmet', 'xlon-implant',
	               'dh-samurai-helmet') as $gear) {
		ok(in_array($gear, DHCF_TWOPART_HANDS_OVER_HEADGEAR, true),
		   "$gear left DHCF_TWOPART_HANDS_OVER_HEADGEAR, so the claws go under it again");
	}
	foreach (array('inferno-wasp-guardian', 'red-cyborg') as $t) {
		ok(in_array($t, DHCF_TWOPART_BODY_OVER_TORSO, true),
		   "$t left DHCF_TWOPART_BODY_OVER_TORSO, so the demon hides behind its wings");
	}
	foreach (array('turret-blue-cyborg-limbs', 'robo-crusher-arms') as $a) {
		ok(in_array($a, DHCF_TWOPART_OVER_ARMS, true),
		   "$a left DHCF_TWOPART_OVER_ARMS");
	}
	foreach (array('skeletal-stabber', 'cyber-666-knight-armor', 'connected-protector-limbs',
	               'deflektor', 'dh-samurai-augmented-arms') as $a) {
		ok(in_array($a, DHCF_TWOPART_HANDS_UNDER_ARMS, true),
		   "$a left DHCF_TWOPART_HANDS_UNDER_ARMS");
	}
	foreach (array('beheaded-cyborg', 'cyborg-stryker-02') as $h) {
		ok(isset(DHCF_COMPANION_EXCLUDED_BY_HEAD[$h])
		   && in_array('demon-companion', DHCF_COMPANION_EXCLUDED_BY_HEAD[$h], true),
		   "$h no longer refuses the demon, which has nothing to grip on it");
	}

	/* ---------------------------------------------------------------------
	 * THE SAMURAI PAIR, AND WHAT IT MUST NOT DISTURB.
	 *
	 * The diff against the canvas further down only proves the two agree; it
	 * would pass just as happily if both were wrong. These say what the order
	 * is supposed to BE.
	 * ------------------------------------------------------------------- */
	$ix = function ($traits) {
		$o = dhcf_layer_order($traits);
		return function ($k) use ($o) { return array_search($k, $o, true); };
	};
	$sgG = array_keys(DHCF_ARMS_OVER_HEADGEAR)[0];
	$sgA = DHCF_ARMS_OVER_HEADGEAR[$sgG][0];

	$f = $ix(array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02',
	               'headgear' => $sgG, 'arms' => $sgA));
	ok($f('arms') > $f('headgear'),
	   "$sgA does not draw in front of $sgG, which is the whole of this rule");

	/* THE PAIR, NOT THE PIECES. Generalising either way was rendered and
	   rejected -- skeletal-stabber swallows the helmet, and the broad flat
	   helmets end up behind the shoulder spikes. */
	$f = $ix(array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02',
	               'headgear' => $sgG, 'arms' => 'skeletal-stabber'));
	ok($f('arms') < $f('headgear'),
	   "$sgG now draws behind OTHER arms too; the rule is the pair, not the helmet");
	$f = $ix(array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02',
	               'headgear' => 'tank-helmet', 'arms' => $sgA));
	ok($f('arms') < $f('headgear'),
	   "$sgA now draws over OTHER headgear too; the rule is the pair, not the arms");

	/* AND IT MUST NOT PULL THE ARMS BACKWARDS. The shoulder cam has already
	   pushed them past the companion for its own rule; moving them to
	   just-after-headgear from there would undo it. This shipped broken once
	   and this is the check that found it. */
	$cam = DHCF_ARMS_OVER_COMPANION[0];
	$f = $ix(array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02',
	               'headgear' => $sgG, 'arms' => $sgA, 'companion' => $cam));
	ok($f('arms') > $f('companion'),
	   "the samurai rule pulled the arms back behind $cam, undoing DHCF_ARMS_OVER_COMPANION");
	ok($f('arms') > $f('headgear'),
	   'the samurai pair lost its own rule when the shoulder cam was added');

	/* WITH THE DEMON, three rules apply at once and only one order satisfies
	   all of them: headgear < claws < arms. Asserted on the real layer list,
	   because the claws are not a slot and dhcf_layer_order() cannot show
	   them. */
	$ls2 = dhcf_layers(array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02',
	                         'headgear' => $sgG, 'arms' => $sgA, 'companion' => $tp), $root);
	$c2 = array_map(function ($l) { return $l['cat']; }, $ls2);
	$j = function ($c) use ($c2) { return array_search($c, $c2, true); };
	ok($j('companion-front') !== false && $j('headgear') !== false
	   && $j('companion-front') > $j('headgear'),
	   'with the samurai pair the claws fell back under the helmet');
	ok($j('arms') !== false && $j('companion-front') !== false
	   && $j('arms') > $j('companion-front'),
	   'with the samurai pair the claws landed ON TOP of the arm meant to cover them');

	/* ---------------------------------------------------------------------
	 * CODE SEA PREDATOR: over the headgear, under the arms, over the turret.
	 *
	 * It was DEMOTED below Arms, which is also below the head and headgear,
	 * so a big piece buried it -- five Fighters in the Collection showed the
	 * creature's head gone under an Alien Predator or Alien Skull. It takes
	 * the shoulder cam's solution instead: stay last and move the ARMS.
	 * ------------------------------------------------------------------- */
	$sp = 'code-sea-predator';
	/* A REAL ARM, and picked rather than typed. 'robo-arms' was used here
	   first and does not exist -- dhcf_layer_order() does not care (it reads
	   the trait array, not the disk) so the assertions still passed, but the
	   render built from the same fixture silently drew no arms at all, which
	   is how it was noticed. Anything that is not behind the torso, not the
	   turret and has art. */
	$ordinaryArm = '';
	foreach (array_keys(dhcf_rarity()['arms']) as $a) {
		if (in_array($a, DHCF_ARMS_BEHIND_TORSO, true)) continue;
		if (dhcf_arms_under_companion('code-sea-predator', $a)) continue;
		if (!is_file($root . '/1000/arms/' . $a . '.png')) continue;
		$ordinaryArm = $a; break;
	}
	ok($ordinaryArm !== '', 'no ordinary arm with art to test against');
	$ix2 = function ($traits) {
		$o = dhcf_layer_order($traits);
		return function ($k) use ($o) { return array_search($k, $o, true); };
	};
	$base2 = array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02',
	               'headgear' => 'alien-predator');

	$f2 = $ix2($base2 + array('companion' => $sp));
	ok($f2('companion') > $f2('headgear'),
	   "$sp still draws under the headgear -- this is the reported bug");
	ok($f2('companion') > $f2('head'), "$sp still draws under the head");

	$f2 = $ix2($base2 + array('companion' => $sp, 'arms' => $ordinaryArm));
	ok($f2('arms') > $f2('companion'),
	   "an ordinary arm no longer passes in FRONT of $sp");
	ok($f2('companion') > $f2('headgear'),
	   "$sp lost the headgear fix as soon as an arm was added");

	$f2 = $ix2($base2 + array('companion' => $sp, 'arms' => 'turret-blue-cyborg-limbs'));
	ok($f2('companion') > $f2('arms'),
	   'the turret limbs draw over ' . $sp . ', which is what they swallow it with');

	/* The exception is keyed to the COMPANION. The cam must be unaffected by
	   it, or one companion's fix has silently become the other's. */
	$f2 = $ix2($base2 + array('companion' => 'dh-vision-shoulder-cam',
	                          'arms' => 'turret-blue-cyborg-limbs'));
	ok($f2('arms') > $f2('companion'),
	   "the turret exception leaked onto the shoulder cam, which wants the arms in front");

	/* And an ordinary companion gains nothing from any of it. */
	$f2 = $ix2($base2 + array('companion' => 'golden-isopunk', 'arms' => $ordinaryArm));
	ok($f2('companion') > $f2('arms'),
	   'an ordinary companion had its arms hoisted over it');

	/* ---------------------------------------------------------------------
	 * THE SABER PIPE OVER THE SHOULDER CAM.
	 *
	 * The pipe comes down off the helmet past a cam that is bolted to the
	 * shoulder. Behind the mount it reads as passing through the character.
	 * A pair rule: the cam still covers every other headgear and this helmet
	 * still goes under every other companion.
	 * ------------------------------------------------------------------- */
	$sgr = array_keys(DHCF_HEADGEAR_OVER_COMPANION)[0];
	$scm = DHCF_HEADGEAR_OVER_COMPANION[$sgr][0];
	$ix3 = function ($traits) {
		$o = dhcf_layer_order($traits);
		return function ($k) use ($o) { return array_search($k, $o, true); };
	};
	$base3 = array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02');

	$f3 = $ix3($base3 + array('headgear' => $sgr, 'companion' => $scm));
	ok($f3('headgear') > $f3('companion'),
	   "$sgr still draws under $scm, so its pipe runs behind the mount");

	$f3 = $ix3($base3 + array('headgear' => $sgr, 'companion' => 'golden-isopunk'));
	ok($f3('headgear') < $f3('companion'),
	   "$sgr now draws over EVERY companion; the rule is the pair, not the helmet");

	$f3 = $ix3($base3 + array('headgear' => 'ww2-helmet', 'companion' => $scm));
	ok($f3('headgear') < $f3('companion'),
	   "$scm now draws under EVERY headgear; the rule is the pair, not the cam");

	/* AND THE CAM KEEPS ITS OWN RULE. DHCF_ARMS_OVER_COMPANION puts the arms
	   in front of the cam; moving the headgear past the companion must not
	   land it in front of the arms as a side effect. */
	$f3 = $ix3($base3 + array('headgear' => $sgr, 'companion' => $scm,
	                          'arms' => 'connected-protector-limbs'));
	ok($f3('arms') > $f3('companion'),
	   'the saber rule cost the shoulder cam its arms-in-front rule');
	ok($f3('arms') > $f3('headgear'),
	   'the headgear ended up over the arms, which the cam pairing does not want');
	ok($f3('headgear') > $f3('companion'),
	   'the saber rule stopped applying once arms were added');

	/* ---------------------------------------------------------------------
	 * THE NECK FILL, under the torso.
	 *
	 * Closes the gap where a head does not reach the edge of the socket --
	 * without it the background shows through a hole in the character's own
	 * outline. One shared shape, safe because it is black only where black can
	 * never be wrong for any torso/arms/head combination.
	 * ------------------------------------------------------------------- */
	if (is_file($root . '/1000/neckfill/neckfill.png')) {
		$nf = function ($traits) use ($root) {
			return array_map(function ($l) { return $l['cat']; }, dhcf_layers($traits, $root));
		};
		$c4 = $nf(array('background' => 'black', 'torso' => 'golden-cyborg', 'head' => 'mk100'));
		$i4 = array_search('neckfill', $c4, true);
		ok($i4 !== false, 'the neck fill is not emitted at all');
		ok($i4 !== false && $i4 < array_search('torso', $c4, true),
		   'the neck fill draws OVER the torso, which would black out the chest');
		ok($i4 !== false && $i4 > array_search('background', $c4, true),
		   'the neck fill draws under the background, where nothing can see it');

		/* NO HEAD, NO FILL. The generator leaves the no-head case out of its
		   safety set -- with no head the socket is open sky, not a hole, so
		   the shape is not safe there and a black plate would just show. */
		ok(!in_array('neckfill', $nf(array('background' => 'black', 'torso' => 'golden-cyborg')), true),
		   'the neck fill is drawn with no head on, where its shape is not safe');
		ok(!in_array('neckfill', $nf(array('background' => 'black', 'head' => 'mk100')), true),
		   'the neck fill is drawn with no torso, which has no socket to fill');

		/* It must sit under the ARMLESS torso too -- that is the variant the
		   renderer actually draws once arms are on. */
		$c5 = $nf(array('background' => 'black', 'torso' => 'golden-cyborg',
		                'head' => 'mk100', 'arms' => 'skeletal-stabber'));
		$i5 = array_search('neckfill', $c5, true);
		ok($i5 !== false && $i5 < array_search('torso-noarms', $c5, true),
		   'the neck fill is not under the armless torso, which is what gets drawn with arms on');

		/* BOTH SIZES ON DISK. A missing 250 means every thumbnail on the
		   Collection keeps the bleed while the full size is fixed. */
		foreach (array(1000, 250) as $sz) {
			ok(is_file($root . '/' . $sz . '/neckfill/neckfill.png'),
			   "neckfill/neckfill.png is missing at {$sz}px");
		}
		/*
		 * THE TWO SIZES MUST BE THE SAME DRAWING.
		 *
		 * The 1000px block is authored by hand; the 250px one is resampled
		 * from it by dhc-neckfill.php. If they are edited independently they
		 * drift, and the thumbnail keeps a bleed the full size does not --
		 * which is invisible until somebody looks at a card. Compared by the
		 * fraction of the frame each covers and where it sits, not pixel for
		 * pixel, because resampling is lossy by design.
		 *
		 * (There is no fingerprint file any more. The old generator derived
		 * the shape from every torso, arm and head, so one new head could
		 * invalidate it and staleness had to be detectable. An authored block
		 * is not derived from anything, so there is nothing to go stale
		 * against and a manifest would only claim a guarantee that is gone.)
		 */
		$nfBox = function ($png) {
			$im = @imagecreatefrompng($png);
			if (!$im) return null;
			if (!imageistruecolor($im)) imagepalettetotruecolor($im);
			$w = imagesx($im); $h = imagesy($im);
			$n = 0; $x0 = $w; $x1 = -1; $y0 = $h; $y1 = -1;
			for ($y = 0; $y < $h; $y++) for ($x = 0; $x < $w; $x++) {
				if (((imagecolorat($im, $x, $y) >> 24) & 0x7F) >= 64) continue;
				$n++;
				if ($x < $x0) $x0 = $x; if ($x > $x1) $x1 = $x;
				if ($y < $y0) $y0 = $y; if ($y > $y1) $y1 = $y;
			}
			if ($x1 < 0) return null;
			return array('frac' => $n / ($w * $h), 'x0' => $x0 / $w, 'x1' => $x1 / $w,
			             'y0' => $y0 / $h, 'y1' => $y1 / $h);
		};
		$bigBox   = $nfBox($root . '/1000/neckfill/neckfill.png');
		$smallBox = $nfBox($root . '/250/neckfill/neckfill.png');
		ok($bigBox !== null, 'the 1000px neck fill is empty or unreadable');
		ok($smallBox !== null, 'the 250px neck fill is empty or unreadable');
		if ($bigBox && $smallBox) {
			ok(abs($bigBox['frac'] - $smallBox['frac']) < 0.002,
			   sprintf('the two neck fills cover different amounts of the frame (%.2f%% vs %.2f%%) '
			         . '-- run: php dhc-neckfill.php',
			           $bigBox['frac']*100, $smallBox['frac']*100));
			foreach (array('x0','x1','y0','y1') as $edge) {
				ok(abs($bigBox[$edge] - $smallBox[$edge]) < 0.01,
				   "the two neck fills do not sit in the same place ($edge differs) -- "
				 . 'run: php dhc-neckfill.php');
			}
			/* A block, not a full-body silhouette. The first version of this
			   covered 21.7% of the frame and was wrong for it: it only has to
			   cover the neckline, and everything else it claimed was hidden
			   under a torso doing nothing. */
			ok($bigBox['frac'] < 0.05,
			   sprintf('the neck fill covers %.1f%% of the frame -- it is meant to be a block at '
			         . 'the neckline, not a body-sized shape', $bigBox['frac']*100));
		}

		/* And the canvas draws it, in the same place. */
		$asm4 = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '',
		                     file_get_contents(__DIR__ . '/dhc-assembler.php'));
		ok(strpos($asm4, "url('neckfill', 'neckfill', 1000)") !== false,
		   'the canvas does not build the neck fill path, so the assembler keeps the bleed');
		/* Under BOTH: it rides whichever of head and torso the order puts first. */
		ok(preg_match("/if \\(s\\.key === nfAnchor\\) \\{\\s*var nf = document\\.getElementById\\('L-neckfill'\\);/", $asm4) === 1,
		   'the canvas pins the neck fill to one slot instead of riding whichever of head and torso comes first');
		ok(strpos($asm4, "if (!sel.torso || !sel.head) {") !== false,
		   'the canvas draws the neck fill without a head or without a torso');
	} else {
		echo "  neck fill: SKIPPED (not generated in this checkout)\n";
	}

	/* ---------------------------------------------------------------------
	 * THE HEAD UNDER THE TORSO, AND THE ONE HEAD THAT IS NOT.
	 * ------------------------------------------------------------------- */
	$ixH = function ($traits) {
		$o = dhcf_layer_order($traits);
		return function ($k) use ($o) { return array_search($k, $o, true); };
	};
	$fh = $ixH(array('torso' => 'golden-cyborg', 'head' => 'mk100'));
	ok($fh('head') < $fh('torso'),
	   'the head draws over the torso again, so a neck lies on the collar instead of '
	 . 'going into it');
	$stump = DHCF_HEADS_OVER_TORSO[0];
	$fh = $ixH(array('torso' => 'golden-cyborg', 'head' => $stump));
	ok($fh('head') > $fh('torso'),
	   "$stump draws under the torso, which loses the wires it is drawn to spill "
	 . 'across the chest');

	/* THE FILL GOES UNDER BOTH, whichever order they are in. Anchored to the
	   head alone it surfaces above the body on the stump. */
	foreach (array('mk100', $stump) as $hh) {
		$cats = array_map(function ($l) { return $l['cat']; },
		                  dhcf_layers(array('background' => 'black', 'torso' => 'golden-cyborg',
		                                    'head' => $hh), $root));
		$nf = array_search('neckfill', $cats, true);
		ok($nf !== false, "the neck fill is not emitted with head $hh");
		if ($nf !== false) {
			ok($nf < array_search('head', $cats, true),
			   "the neck fill draws over the head with $hh");
			ok($nf < array_search('torso', $cats, true),
			   "the neck fill draws over the torso with $hh -- it must be under both");
		}
	}

	/* A headless Fighter is a real saved state, and still wears the claws. */
	$lsNoHead = dhcf_layers(array('torso' => 'amplified-dreadnought', 'companion' => $tp), $root);
	ok(in_array('companion-front', array_map(function ($l) { return $l['cat']; }, $lsNoHead), true),
	   'a Fighter with no head loses the claws entirely');

	/* An ordinary companion must gain nothing from any of this. */
	$lsPlain = dhcf_layers(array('torso' => 'amplified-dreadnought', 'head' => 'alien-zombie-02',
	                             'companion' => 'golden-isopunk'), $root);
	$pc = array_map(function ($l) { return $l['cat']; }, $lsPlain);
	ok(!in_array('companion-front', $pc, true),
	   'an ordinary companion is given a front half it has no art for');
	ok(array_search('companion', $pc, true) > array_search('torso', $pc, true),
	   'an ordinary companion was moved behind the torso');

	/* And the art has to actually be there, or every renderer silently skips it. */
	foreach (DHCF_COMPANION_TWO_PART as $slug) {
		foreach (array(1000, 250) as $sz) {
			ok(is_file($root . '/' . $sz . '/companion-front/' . $slug . '.png'),
			   "companion-front/$slug.png is missing at {$sz}px");
			ok(is_file($root . '/' . $sz . '/companion/' . $slug . '.png'),
			   "companion/$slug.png is missing at {$sz}px");
		}
	}
	/* All three renderers ask the same question. A renderer that stops asking
	   draws a different Fighter from the other two, which this codebase has
	   already paid for twice. */
	/* EACH RENDERER'S REAL PATH, not the words "companion-front" anywhere in
	   the file. Checking the bare string passed a mutation that renamed the
	   directory in the GD renderer, because the COMMENT above it still said
	   companion-front -- the same way the ALL-CAPS scan above was fooled by
	   prose. Comments are stripped and the actual expression is named. */
	/* TWO renderers now, not three. The GD renderer stopped rebuilding the
	   stack and paints dhcf_layers() instead, which is checked separately
	   below -- it cannot drift from the Collection because it IS the
	   Collection's stack. What remains is the canvas, which has to stay a
	   second implementation because it runs in the browser. */
	$wants = array(
		'dhcfighters-lib.php'     => "'cat' => 'companion-front'",
		'dhc-assembler.php'       => "url('companion-front'",
	);
	foreach ($wants as $f => $needle) {
		$src2 = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '',
		                     file_get_contents(__DIR__ . '/' . $f));
		ok(strpos($src2, $needle) !== false,
		   "$f does not build the companion-front path, so it will draw this Fighter differently");
		ok(strpos($src2, 'dhcf_companion_two_part') !== false
		   || strpos($src2, 'companionTwoPart') !== false,
		   "$f does not ask whether the companion is the attached kind");
		/* The two rules that moved AFTER the Demon shipped. A renderer that
		   anchors the claws on a hardcoded 'head' puts them under the five
		   headgears they are meant to cover, and one that never asks about a
		   refusing head draws a Demon gripping a severed neck. Both are
		   silent -- nothing errors, the picture is just wrong in one place. */
		ok(strpos($src2, 'dhcf_twopart_hands_after') !== false
		   || strpos($src2, 'twoPartHandsAfter') !== false,
		   "$f hardcodes where the claws go, so headgear they should cover will cover them");
		ok(strpos($src2, 'dhcf_companion_blocked') !== false
		   || strpos($src2, 'companionBlocked') !== false,
		   "$f does not ask whether the head refuses the companion");
	}
	/* And the GD renderer paints that stack rather than deriving one. If it
	   ever walks dhcf_layer_order() again it has re-acquired its own opinion
	   about the armless swap, the nudge and the claws, and Discord can differ
	   from the Collection without anything erroring. */
	$gd = preg_replace(array('#/\*.*?\*/#s', '#//[^\n]*#'), '',
	                   file_get_contents(__DIR__ . '/dhcfighters-notify.php'));
	ok(strpos($gd, 'dhcf_layers($traits') !== false,
	   'the GD renderer no longer paints dhcf_layers(), so Discord derives its own stack');
	ok(strpos($gd, 'dhcf_layer_order(') === false,
	   'the GD renderer builds its own layer order again, which is the drift this collapsed');
}

echo "\nlayer order, canvas vs php\n";
foreach ($cases as $name => $traits) {
	$php = array();
	foreach (dhcf_layer_order($traits) as $slot) if (!empty($traits[$slot])) $php[] = $slot;
	$js_ = isset($canvas[$name]) ? $canvas[$name] : array();
	$same = $php === $js_;
	printf("  %-26s %s\n", $name, implode(' > ', $php));
	if (!$same) printf("  %-26s canvas: %s\n", '', implode(' > ', $js_));
	ok($same, "$name: the canvas and the server draw this Fighter differently");
}

/* ---------- 6. the invariants the rules rely on ----------------------------- */
echo "\ninvariants\n";
foreach (DHCF_COMPANION_OVER_COVER as $c) {
	ok(in_array($c, DHCF_COMPANION_UNDER, true),
	   "$c is in DHCF_COMPANION_OVER_COVER but not DHCF_COMPANION_UNDER — it "
	 . 'already draws last, so pushing the cover below it would put the frame '
	 . 'over the head instead');
}
/* The cover list is a family that will grow. Check it against the art rather
   than trusting whoever adds cover 4 to remember this file. */
$base = '';
foreach (array('web', 'dhc', 'dhc/web', 'traits') as $c) {
	if (is_dir(__DIR__ . '/' . $c . '/1000')) { $base = $c; break; }
}
if ($base === '') {
	echo "  (no trait art here, so DHCF_COVER_EFFECTS was not checked against disk)\n";
} else {
	$onDisk = array();
	foreach (glob(__DIR__ . '/' . $base . '/1000/effects/*comic-cover*.png') as $f) {
		$onDisk[] = basename($f, '.png');
	}
	sort($onDisk);
	$listed = DHCF_COVER_EFFECTS; sort($listed);
	printf("  covers on disk: %s\n", implode(', ', $onDisk));
	ok($onDisk === $listed,
	   'DHCF_COVER_EFFECTS does not match the comic covers in the art folder ('
	 . implode(',', array_merge(array_diff($onDisk, $listed), array_diff($listed, $onDisk)))
	 . ') — a cover missing from the list silently skips the companion rule');
}

echo "\n" . ($fail ? "FAILED: $fail check(s)\n" : "all layer-order checks passed\n");
exit($fail ? 1 : 0);
