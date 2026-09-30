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
foreach (array('layerOrder', 'armsBehindTorso', 'effectBehindTorso') as $fn) {
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
$under  = DHCF_COMPANION_UNDER[0];                        // dh-vision-shoulder-cam
$over   = DHCF_COMPANION_OVER_COVER[0];                   // code-sea-predator
$behind = DHCF_EFFECTS_BEHIND_TORSO[0];
$backarm = DHCF_ARMS_BEHIND_TORSO[0];
$onearm  = array_keys(DHCF_ONE_ARM)[0];
$plain = array('background' => 'bg', 'torso' => 'body', 'head' => 'face',
               'headgear' => 'hat', 'arms' => 'limbs', 'weapon' => 'axe-ish');

/* array_merge, NOT the + union operator: + keeps the left-hand value for a key
   that already exists, so "arms behind torso" silently kept $plain's ordinary
   arms and tested nothing. */
function ms_case($plain, $extra) { return array_merge($plain, $extra); }
$cases = array(
	'nothing special'           => $plain,
	'ordinary companion'        => ms_case($plain, array('companion' => 'golden-isopunk')),
	'cover, ordinary companion' => ms_case($plain, array('companion' => 'golden-isopunk',
	                                                     'effects1'  => $cover)),
	'demoted companion'         => ms_case($plain, array('companion' => $under)),
	'cover + demoted companion' => ms_case($plain, array('companion' => $under,
	                                                     'effects1'  => $cover)),
	'cover + over-cover comp'   => ms_case($plain, array('companion' => $over,
	                                                     'effects1'  => $cover)),
	'plain fx + over-cover'     => ms_case($plain, array('companion' => $over,
	                                                     'effects1'  => 'electricity')),
	'two covers + over-cover'   => ms_case($plain, array('companion' => $over,
	                                                     'effects1'  => $cover,
	                                                     'effects2'  => $cover2)),
	'cover in slot 2 only'      => ms_case($plain, array('companion' => $over,
	                                                     'effects2'  => $cover)),
	'behind-torso fx wins'      => ms_case($plain, array('companion' => $over,
	                                                     'effects1'  => $behind)),
	'arms behind torso'         => ms_case($plain, array('companion' => $over,
	                                                     'arms'      => $backarm,
	                                                     'effects1'  => $cover)),
	'one-sided arm'             => ms_case($plain, array('companion' => $over,
	                                                     'arms'      => $onearm,
	                                                     'effects1'  => $cover)),
	'no companion at all'       => ms_case($plain, array('effects1'  => $cover)),
	'cover, no other traits'    => array('background' => 'bg', 'torso' => 'body',
	                                     'head' => 'face', 'effects1' => $cover),
);

/* ---------- 4. run the canvas's own code ----------------------------------- */
$prelude = "var SLOTS = " . json_encode(array_map(function ($k) { return array('key' => $k); },
                                                  $canvasSlots)) . ";\n"
         . "var customOrder = null;\n"
         . "var COMPANION_UNDER = "      . json_encode(DHCF_COMPANION_UNDER)      . ";\n"
         . "var ARMS_BEHIND_TORSO = "    . json_encode(DHCF_ARMS_BEHIND_TORSO)    . ";\n"
         . "var EFFECTS_BEHIND_TORSO = " . json_encode(DHCF_EFFECTS_BEHIND_TORSO) . ";\n"
         . "var COVER_EFFECTS = "        . json_encode(DHCF_COVER_EFFECTS)        . ";\n"
         . "var COMPANION_OVER_COVER = " . json_encode(DHCF_COMPANION_OVER_COVER) . ";\n"
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
console.log(JSON.stringify(out));
";
$tmp = sys_get_temp_dir() . '/dhc-layerorder-' . getmypid() . '.js';
file_put_contents($tmp, $driver);
$raw = shell_exec('node ' . escapeshellarg($tmp) . ' 2>&1');
@unlink($tmp);
$canvas = json_decode((string)$raw, true);
if (!is_array($canvas)) {
	echo "  node produced nothing usable:\n" . substr((string)$raw, 0, 900) . "\n";
	ok(false, 'could not run the canvas layerOrder() — the comparison did NOT happen');
	echo "\nFAILED: $fail check(s)\n";
	exit(1);
}

/* ---------- 5. compare ------------------------------------------------------ */
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
