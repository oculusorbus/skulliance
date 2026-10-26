<?php
/* dhcarena-harness.php — CLI only. Runs whole battles with no browser and no
   database, which is the entire reason dhcarena-engine.php takes a plain array.
   Usage: php dhcarena-harness.php [battles]
   See dhcarena.md §4: the commons-vs-legendaries test is not optional. */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require __DIR__ . '/dhcarena-engine.php';
$rarity = require __DIR__ . '/dhcrarity.php';
$N = isset($argv[1]) ? (int)$argv[1] : 60;

function pick_traits($rarity, $tier, &$seed) {
	$t = array();
	foreach (array('torso','head','headgear','arms','weapon','background','companion','effects') as $c) {
		$pool = array();
		foreach ($rarity[$c] as $slug => $info) if ($tier === null || $info[0] === $tier) $pool[] = $slug;
		if (!$pool) foreach ($rarity[$c] as $slug => $info) $pool[] = $slug;
		$seed = ($seed * 1103515245 + 12345) & 0x7FFFFFFF;
		$t[$c] = $pool[$seed % count($pool)];
	}
	return $t;
}
function crew($rarity, $tier, &$seed) {
	$c = array(); for ($i=0;$i<3;$i++) $c[] = pick_traits($rarity, $tier, $seed); return $c;
}
$names = array('mine'=>array('A1','A2','A3'), 'foes'=>array('B1','B2','B3'));

function play(&$b) {
	$guard = 0;
	while ($b['over'] === null && $guard++ < 400) {
		$mv = dhca_ai_move($b);            // both sides played by the AI
		if (!$mv) { dhca_fill_board($b); $mv = dhca_ai_move($b); if (!$mv) break; }
		if (!dhca_play($b, $b['turn'], $mv[0], $mv[1])) break;
	}
	return $guard;
}

/* 0. THE STYLESHEET STILL PARSES.
   Not a rules test, but it lives here because it is the one check that would
   have caught an entire block of desktop CSS being silently discarded: the
   scoping pass once left ".arena-wrap @media", and a prefixed at-rule is
   invalid, so every rule inside it was dropped by the browser and by nothing
   else -- php -l passes, the harness passes, the page renders, and the enemies
   simply stop facing you. */
$page = @file_get_contents(__DIR__ . '/dhcarena.php');
if ($page !== false && strpos($page, '<style>') !== false) {
	$css = substr($page, strpos($page, '<style>') + 7);
	$css = substr($css, 0, strpos($css, '</style>'));
	$css = preg_replace('#/\*.*?\*/#s', '', $css);
	$bad = preg_match_all('/[^\s{}][^{}\n]*@(?:media|keyframes|supports)/', $css, $m);
	$open = substr_count($css, '{'); $close = substr_count($css, '}');
	printf("stylesheet: %d prefixed at-rules (want 0), braces %s\n",
		$bad, $open === $close ? 'balanced' : "UNBALANCED $open/$close");
	if ($bad) foreach (array_slice($m[0], 0, 3) as $x) echo "   " . trim($x) . "\n";
}

/* 0b. THE BATTLE MARKUP IS NESTED THE WAY THE GRID NEEDS IT.
   Tag BALANCE is not structure, and trusting balance is what let a broken
   layout ship: .boardwrap lost its closing tag, every following element became
   its child, and .teamwrap.foes fell out of the .arena grid entirely -- while
   the open and close counts still matched perfectly, because a close that
   belonged to one element was silently consumed by another.
   So this asserts PARENTAGE. .arena must hold all three grid children, and the
   end card must sit inside the board rather than beside it. */
if (!empty($page)) {
	/* The PHP goes first: a <div> written inside a comment or one branch of a
	   conditional is not markup, and counting it is how a checker lies to you.
	   Stripping the TAGS alone is not enough once a template branches, because
	   both halves of an if/else are left behind and concatenated -- two opening
	   <div class="body"> against one close, which reads as broken nesting on
	   markup that renders perfectly either way. So walk the conditionals and
	   keep only the first branch of each: whichever branch is chosen, a template
	   whose every branch is individually balanced stays balanced. */
	$parts = preg_split('#(<\?php[\s\S]*?\?>)#', $page, -1, PREG_SPLIT_DELIM_CAPTURE);
	$body = ''; $frames = array();
	foreach ($parts as $part) {
		if (strncmp($part, '<?php', 5) !== 0) {
			$live = true;
			foreach ($frames as $f) if (!$f) { $live = false; break; }
			if ($live) $body .= $part;
			continue;
		}
		$code = trim(substr($part, 5, -2));
		if (preg_match('/^if\s*\(/', $code) && preg_match('/:\s*$/', $code)) {
			$frames[] = true;                       // take the first branch
		} elseif (preg_match('/^(else\s*:|elseif\s*\()/', $code)) {
			if ($frames) $frames[count($frames)-1] = false;   // ...and drop the rest
		} elseif (preg_match('/^endif\s*;/', $code)) {
			array_pop($frames);
		}
	}
	if ($frames) echo "   note: ".count($frames)." unclosed if() in the template\n";
	$body = preg_replace('#<script[\s\S]*?</script>#', '', $body);
	$body = preg_replace('#<style[\s\S]*?</style>#', '', $body);
	$body = preg_replace('#<!--[\s\S]*?-->#', '', $body);
	$void = array('br'=>1,'img'=>1,'input'=>1,'meta'=>1,'link'=>1,'hr'=>1,'source'=>1,'col'=>1);
	$stack = array(); $parent = array();
	preg_match_all('#<(/?)([a-zA-Z][\w-]*)([^>]*?)(/?)>#', $body, $ms, PREG_SET_ORDER);
	foreach ($ms as $m) {
		$close = $m[1] !== ''; $tag = strtolower($m[2]);
		if (isset($void[$tag]) || $m[4] !== '') continue;
		if (!$close) {
			$name = '';
			if (preg_match('/class="([^"]*)"/', $m[3], $c)) $name = $c[1];
			if (preg_match('/id="([^"]*)"/', $m[3], $i2)) $name .= '#'.$i2[1];
			if ($name !== '') $parent[$name] = end($stack);
			$stack[] = $name !== '' ? $name : $tag;
		} elseif ($stack) { array_pop($stack); }
	}
	$want = array(
		'teamwrap mine'   => 'arena',
		'boardcol'        => 'arena',
		'teamwrap foes'   => 'arena',
		'boardwrap'       => 'boardcol',
		'endcard#endcard' => 'boardwrap',
		'grid#grid'       => 'boardwrap',
		/* And the battle view's own children, because the check above only ever
		   looked INSIDE .arena -- so when a stray close ended #arenaBattle early,
		   the battle log and the Fighter card fell out into .arena-wrap, which is
		   not hidden, and both turned up on the Crew select screen. Everything
		   here is display:none until #arenaBattle wears .on; out here it is just
		   visible. */
		'top'                    => '#arenaBattle',
		'arena'                  => '#arenaBattle',
		'reach mobonly#reachM'   => '#arenaBattle',
		'legend mobonly#legendM' => '#arenaBattle',
		'logbox'                 => '#arenaBattle',
		'fcard#fcard'            => '#arenaBattle',
		'#arenaBattle'           => 'arena-wrap',
		'a-panels#arenaSetup'    => 'arena-wrap',
	);
	$bad = array();
	foreach ($want as $child => $mother) {
		$got = isset($parent[$child]) ? $parent[$child] : '(absent)';
		if ($got !== $mother) $bad[] = "$child is in $got, wanted $mother";
	}
	printf("battle markup: %s\n", $bad ? 'BROKEN -- '.implode('; ', $bad) : 'nesting correct');
}

/* 1. does a battle finish, and how long does it take */
$seed = 20260924; $turns = array(); $bombs = 0; $blasts = 0; $stuck = 0;
for ($i = 0; $i < $N; $i++) {
	$b = dhca_new_battle(crew($rarity,null,$seed), crew($rarity,null,$seed), $names, $rarity, $seed+$i);
	$t = play($b);
	if ($b['over'] === null) $stuck++;
	$turns[] = $t; $bombs += $b['stats']['bombs']; $blasts += $b['stats']['blasts'];
}
sort($turns);
printf("%d battles\n", $N);
printf("  turns   avg %.1f  median %d  p90 %d  max %d\n",
	array_sum($turns)/count($turns), $turns[intdiv(count($turns),2)],
	$turns[(int)floor(count($turns)*0.9)], end($turns));
printf("  bombs   %.1f armed, %.1f detonated per battle\n", $bombs/$N, $blasts/$N);

/* 1b. SHIELD HAS A CEILING.
 *
 * It had none and nothing ever removed it, so a Crew that kept matching blue
 * banked shield for a whole battle -- and because a 5 anywhere in a cascade
 * grants another turn, a defending Crew could stack several moves in a row
 * doing it. Reported as an AI repeating the same match with a shield near 700
 * and no way through. Measured before the fix: median 0, p99 802, worst 8117.
 *
 * Driven, not read: the cap is applied per Fighter against that Fighter's own
 * maxHp, inside a branch that only runs on a blue match, and the amount it
 * announces to the client has to be the gain that LANDED. None of that is
 * visible in the shape of the source.
 */
$sh_fail = 0;
$shok = function ($cond, $what) use (&$sh_fail) {
	if (!$cond) { $sh_fail++; echo "  FAIL  $what\n"; }
};
/*
 * THE SEEDS ARE NOT ARBITRARY. The runaway is ~0.5% of battles, so a round
 * number of random ones does not contain it -- the first version of this
 * check ran 60 battles, passed, and went on passing with the fix removed.
 * These eight were found by sweeping 3000 battles against the PRE-FIX engine
 * and keeping the ones whose cascade reached the guard. Each is a live
 * reproduction; if the fix is removed, every one of them runs away again.
 */
$shRunaway = array(1219, 1757, 1825, 2252, 2348, 2476, 2500, 2701);
$shMax = 0.0; $shOver = 0; $shSamples = 0; $shChainMax = 0;
foreach ($shRunaway as $shSeed) {
	$b = dhca_new_battle(crew($rarity,null,$shSeed), crew($rarity,null,$shSeed),
	                     $names, $rarity, $shSeed);
	$g = 0;
	while ($b['over'] === null && $g++ < 400) {
		$mv = dhca_ai_move($b);
		if (!$mv) { dhca_fill_board($b); $mv = dhca_ai_move($b); if (!$mv) break; }
		if (!dhca_play($b, $b['turn'], $mv[0], $mv[1])) break;
		if ($b['stats']['best'] > $shChainMax) $shChainMax = $b['stats']['best'];
		foreach (array('mine','foes') as $sd) foreach ($b[$sd] as $f) {
			$shSamples++;
			$lim = $f['maxHp'] * DHCA_SHIELD_CAP;
			$ratio = $f['maxHp'] > 0 ? $f['shield'] / $f['maxHp'] : 0;
			if ($ratio > $shMax) $shMax = $ratio;
			/* +1 for the int rounding the cap itself does. */
			if ($f['shield'] > $lim + 1) $shOver++;
		}
	}
}
$shok($shOver === 0,
   $shOver . ' of ' . $shSamples . ' fighter-states carried more shield than '
 . (DHCA_SHIELD_CAP * 100) . '% of their own health -- shield is accumulating without a ceiling again');
$shok($shMax > 0.05,
   'no Fighter in these battles reached even 5% shield (peak ' . round($shMax * 100, 1) . '%), so this '
 . 'check is passing because shielding stopped happening, not because it is capped');
/* AND THE CASCADE CANNOT RUN AWAY.
 *
 * A wave that clears nothing leaves the board untouched, so the same match is
 * found again on the next pass and resolved again, to the guard at 40. The
 * signature on the live ladder was a BEST CHAIN of exactly x40 while every
 * other player sat between x5 and x9 -- chains ran 2..12 and then jumped
 * straight to the guard, because 40 was never a cascade.
 *
 * Asserted against the guard itself: anything at or above it is the loop
 * being stopped by its limit rather than finishing. */
$shok($shChainMax < 40,
   'a cascade reached ' . $shChainMax . ' links, which is the loop guard, not a cascade -- '
 . 'a wave that clears nothing is being re-resolved');
$shok($shChainMax >= 5,
   'no cascade in these battles got past ' . $shChainMax . ' links, so the guard check above '
 . 'is passing because cascades stopped happening');
printf("  shield  peak %.0f%% of maxHp over %d fighter-states (cap %.0f%%)\n",
	$shMax * 100, $shSamples, DHCA_SHIELD_CAP * 100);
printf("  chains  longest %d links (guard is 40)\n", $shChainMax);
echo ($sh_fail ? "\n$sh_fail shield/cascade check(s) FAILED\n" : "  shield cap and cascade guard: ok\n");
if ($sh_fail) exit(1);
printf("  never finished: %d\n", $stuck);

/* 2. §4 -- commons must not be hopeless against legendaries */
$seed = 777; $w = 0; $d = 0; $M = max(40, (int)($N/2));
for ($i = 0; $i < $M; $i++) {
	$b = dhca_new_battle(crew($rarity,'common',$seed), crew($rarity,'legendary',$seed),
	                     $names, $rarity, $seed+$i*7);
	play($b);
	if ($b['over'] === 'mine') $w++;
	if ($b['over'] === null) $d++;
}
printf("\n§4 balance: all-commons beat all-legendaries %.1f%% of %d  (target >=35%%)%s\n",
	$w/$M*100, $M, ($w/$M >= 0.35 ? "  PASS" : "  FAIL"));
printf("  undecided: %d\n", $d);

/* 2b. EVERY BAR-MOVING CHANGE ANNOUNCES ITSELF IN THE fx STREAM.
   The client mirrors these deltas so health drops on the hit that causes it
   rather than in one lump at the end of the turn, and then finish() hard-assigns
   the server's state over the top. So a change to hp, shield, surge or ko with
   NO fx event is not a missing effect -- it is a visible snap at the end of the
   turn, exactly the lag this replaced. Two already existed when this was
   written: Break stripping shield, and Charge accruing.
   Replays the stream against a pre-turn snapshot and demands it land on the
   post-turn truth, which is also what proves finish() never jumps. */
$div = 0; $checked = 0;
for ($g = 0; $g < 12; $g++) {
	$sd = $g * 7 + 3;
	$b = dhca_new_battle(crew($rarity,null,$sd), crew($rarity,null,$sd), $names, $rarity, 900+$g);
	$guard = 0;
	while ($b['over'] === null && $guard++ < 400) {
		$pre = array();
		foreach (array('mine','foes') as $sX) foreach ($b[$sX] as $i => $f)
			$pre[$sX][$i] = array($f['hp'], $f['shield'], $f['surge'], $f['ko']?1:0);
		$mv = dhca_ai_move($b);
		if (!$mv) { dhca_fill_board($b); $mv = dhca_ai_move($b); if (!$mv) break; }
		if (!dhca_play($b, $b['turn'], $mv[0], $mv[1])) break;
		$sim = $pre;
		foreach ($b['fx'] as $e) {
			$k = $e['k'];
			if ($k==='hit')      $sim[$e['side']][$e['i']][0] = max(0, $sim[$e['side']][$e['i']][0] - $e['v']);
			elseif ($k==='heal') $sim[$e['side']][$e['i']][0] += $e['v'];
			elseif ($k==='shielded'||$k==='sunder')
			                     $sim[$e['side']][$e['i']][1] = max(0, $sim[$e['side']][$e['i']][1] - $e['v']);
			elseif ($k==='shield') $sim[$e['side']][$e['i']][1] += $e['v'];
			elseif ($k==='charge') foreach ($e['s'] as $ci => $cv) $sim[$e['side']][$ci][2] = $cv;
			elseif ($k==='erupt')  $sim[$e['side']][$e['i']][2] = 0;
			elseif ($k==='ko')     $sim[$e['side']][$e['i']][3] = 1;
		}
		foreach (array('mine','foes') as $sX) foreach ($b[$sX] as $i => $f) {
			$checked++;
			if ($sim[$sX][$i][0] != $f['hp'] || $sim[$sX][$i][1] != $f['shield']
			 || $sim[$sX][$i][2] != $f['surge'] || $sim[$sX][$i][3] != ($f['ko']?1:0)) {
				if ($div < 3) printf("   %s#%d hp %d/%d shield %d/%d surge %d/%d ko %d/%d\n",
					$sX, $i, $sim[$sX][$i][0], $f['hp'], $sim[$sX][$i][1], $f['shield'],
					$sim[$sX][$i][2], $f['surge'], $sim[$sX][$i][3], $f['ko']?1:0);
				$div++;
			}
		}
	}
}
printf("fx covers every bar: %d divergences over %d fighter-turns (want 0)\n", $div, $checked);
/* IT PRINTED THE NUMBER AND CARRIED ON. A divergence means the client's bars
   and the server's numbers have parted company -- the player watches a shield
   that is not there -- and the run still exited 0, so a mutation that
   announced the uncapped shield gain instead of the gain that landed passed
   this check in silence. Same shape as the layout section that could not fail. */
if ($div > 0) { echo "\n$div fx divergence(s) -- the client's bars no longer match the server\n"; exit(1); }

/* 3. determinism -- the same seed and the same moves must give the same result */
$seed = 4242;
$mt = crew($rarity,null,$seed); $ft = crew($rarity,null,$seed);
$r1 = dhca_new_battle($mt,$ft,$names,$rarity,999); play($r1);
$r2 = dhca_new_battle($mt,$ft,$names,$rarity,999); play($r2);
$same = ($r1['over'] === $r2['over'] && $r1['round'] === $r2['round']
         && json_encode($r1['board']) === json_encode($r2['board']));
echo "\ndeterminism: same seed replays identically -- " . ($same ? "yes" : "NO") . "\n";

/* ---------------------------------------------------------------------------
 * THE RIVAL LIST ON A PHONE.
 *
 * It scrolled in BOTH directions, and the two causes are different:
 *
 *   Vertically, .a-foes is a 330px window with overflow:auto -- a short
 *   scroller inside a page that already scrolls, the same trap the DHC trait
 *   grid had.
 *
 *   Horizontally, each row is a flex line whose children refuse to shrink:
 *   .m and .paidtag are both white-space:nowrap, so the row's min-content
 *   width is the avatar plus the whole of "35 Fighters - best 2,176" plus the
 *   whole of "Beaten today - no trait". Over the column width, .n ellipsises
 *   away to NOTHING and overflow:auto turns the rest into a sideways scroll.
 *
 * Measured at 390px, before: scrollHeight > clientHeight, scrollWidth 358 vs
 * client 342, and four of eight usernames rendering 0px wide -- which is the
 * screenshot, rows with no name on them. After: neither axis scrolls and all
 * eight names are visible. Desktop keeps its 330px window.
 * ------------------------------------------------------------------------- */
$ar_src = file_get_contents(__DIR__ . '/dhcarena.php');
$ar_css = preg_replace('!/\*.*?\*/!s', '', $ar_src);

$ar_fail = 0;
$arok = function ($cond, $what) use (&$ar_fail) {
	if (!$cond) { $ar_fail++; echo "  FAIL  $what\n"; }
};

echo "\nthe rival list does not scroll inside itself on a phone\n";
$arok(preg_match('/@media \(max-width:700px\)\{[\s\S]{0,900}?\.arena-wrap \.a-foes\{max-height:none;overflow:visible\}/', $ar_css) === 1,
   'the rival list keeps its 330px window on a phone, so it is a short scroller inside a page that already scrolls');
/* Desktop must keep it: the panel sits beside the board there and an
   unbounded list would push everything else off the screen. */
$fd_at  = strpos($ar_css, '.arena-wrap .a-foes{');
$foes_d = $fd_at === false ? '' : substr($ar_css, $fd_at, strpos($ar_css, '}', $fd_at) - $fd_at);
/* The two properties that matter, not the whole declaration verbatim --
   pinning the exact string meant adding padding-top broke an assertion about
   the window, which is a different fact. */
/*
 * THE WINDOW IS VIEWPORT-RELATIVE NOW, not 330px. The fixed cap scrolled nine
 * rivals -- 445px of content -- while the column had hundreds of pixels of
 * nothing under it, and that was reported. But the cap itself is not wrong:
 * Enter the Arena and Practice sit BELOW this list and dhca_opponents()
 * returns up to 24 rivals, so an unbounded list buries the page's primary
 * action. calc(100vh - N) keeps both: it uses whatever the screen has.
 *
 * MEASURED at the reporter's own 1454x1046 viewport, which is the only reason
 * to believe the constant: 9 rivals -> 445px of content in a 706px window, no
 * scroll, button at y=650. 24 rivals -> scrolls, button still at y=911, on
 * screen. The old 330px scrolled at 9.
 *
 * Asserted as "viewport-relative and still bounded" rather than as the exact
 * expression, so tuning the subtrahend is not a test failure -- what must not
 * come back is a fixed pixel cap, and what must not go away is the cap.
 */
$arok($foes_d !== '' && strpos($foes_d, 'overflow:auto') !== false,
   'the rival list lost its desktop window entirely; Enter the Arena sits below it and '
 . 'a 24-rival list would push it off the screen');
$arok($foes_d !== '' && preg_match('/max-height:calc\(100vh\s*-\s*\d+px\)/', $foes_d) === 1,
   'the rival list is back on a FIXED max-height; it must size to the viewport, or it '
 . 'scrolls on a tall screen that had room to show the whole list');
$arok($foes_d !== '' && strpos($foes_d, 'max-height:330px') === false,
   'the 330px window is back -- it scrolled nine rivals, which is what was reported');

/*
 * SELECTING THE NEMESIS LOOKS LIKE SELECTING ANYONE ELSE.
 *
 * .a-foe.nem is declared after .a-foe.sel with equal specificity, so it won
 * on background and border-color and a selected nemesis stayed crimson while
 * every other rival turned ochre. Reported as "it throws me off". The crimson
 * belongs to the row's IDENTITY (its resting border and the ARCH NEMESIS
 * badge); what you did to it is interaction feedback, and that has to read
 * the same everywhere.
 *
 * Checked as "a .nem.sel rule exists and names the accent", not as the exact
 * declaration, because the point is that the override is present at all --
 * without it the later .nem rule silently wins again.
 */
$arok(preg_match('/\.arena-wrap \.a-foe\.nem\.sel\{[^}]*var\(--ochre\)/', $ar_css) === 1,
   'selecting the arch nemesis does not use the same highlight as selecting any other '
 . 'rival -- .a-foe.nem is declared later and wins on background and border');
$arok(preg_match('/\.arena-wrap \.a-foe\.nem:hover\{[^}]*var\(--ochre\)/', $ar_css) === 1,
   'hovering the arch nemesis still goes crimson while selecting it goes ochre, which is '
 . 'a bigger jump than the problem being fixed');

echo "\nand a row cannot be wider than the column\n";
$arok(preg_match('/@media \(max-width:700px\)\{[\s\S]{0,900}?\.arena-wrap \.a-foe\{display:grid/', $ar_css) === 1,
   'the rival row is still one flex line on a phone, so nowrap children force it wider than the screen');
foreach (array('.n', '.m', '.paidtag') as $part) {
	$arok(preg_match('/@media \(max-width:700px\)\{[\s\S]{0,900}?\.arena-wrap \.a-foe \\' . $part . '\{[^}]*white-space:normal/', $ar_css) === 1,
	   'the rival row\'s ' . $part . ' is still nowrap on a phone; it is what pushes the name out of view');
}
$arok(preg_match('/\.arena-wrap \.a-foe img\{grid-row:1 \/ span 2/', $ar_css) === 1,
   'the avatar no longer spans the stacked rows, so the text does not align beside it');

/* ---------------------------------------------------------------------------
 * THE ARCH NEMESIS
 *
 * The scoring rule is the feature, so it is driven rather than read. It is
 * NOT "who beats you most" -- that is a wall, and pointing a player at an
 * opponent who always wins is discouraging. A nemesis is the one nothing is
 * settled with:  score = battles - |my wins - their wins|.
 * ------------------------------------------------------------------------- */
$nem_src = file_get_contents(__DIR__ . '/dhcarena-lib.php');
$nem_sql = substr($nem_src, strpos($nem_src, 'function dhca_nemesis'));
$nem_sql = substr($nem_sql, 0, strpos($nem_sql, "\n}\n"));

/* The ranking expression, evaluated the way MySQL would. */
$score = function ($battles, $mine, $theirs) { return $battles - abs($mine - $theirs); };
$arok($score(10, 5, 5)  >  $score(10, 10, 0),
   'a 10-0 rout outranks an even 5-5 rivalry');
$arok($score(10, 5, 5)  >  $score(4, 2, 2),
   'a shorter even history outranks a longer one');
$arok($score(10, 0, 10) === $score(10, 10, 0),
   'being farmed and farming score differently, so a wall can become a nemesis');
$arok($score(6, 4, 2)   >  $score(6, 6, 0),
   'a one-sided six outranks a close six');

$arok(strpos($nem_sql, 'SUM(t.n) - ABS(SUM(t.mine) - SUM(t.theirs))') !== false,
   'the ORDER BY is not volume-minus-imbalance');
/* BOTH DIRECTIONS. Half the history is battles they started, which the
   defender is never told about -- only the attacker is benched and only the
   attacker reaches the ladder. That half is the point of the feature. */
$arok(substr_count($nem_sql, 'UNION ALL') === 1,
   'the nemesis is computed from one side of the ledger only');
/* The WHOLE clause of each branch, not just its first condition. A loose
   check passed a mutation that prefixed the second branch with WHERE 0 --
   the strings were all still there and the branch returned nothing. */
$arok(strpos($nem_sql, 'WHERE attacker_id = $uid AND defender_id <> $uid AND outcome <> 0') !== false,
   'the attacking branch no longer selects exactly the battles I started');
$arok(strpos($nem_sql, 'WHERE defender_id = $uid AND attacker_id <> $uid AND outcome <> 0') !== false,
   'the defending branch no longer selects exactly the battles they started');
/* outcome 1 = the attacker won, so which column is "my win" flips with the
   side of the row. Getting this backwards silently inverts every record. */
/* The columns are SELECTed before the WHERE that identifies the branch, so
   the match runs from the column to its WHERE, not the other way round. */
$arok(preg_match('/\(outcome = 1\) AS mine.*?WHERE attacker_id = \$uid/s', $nem_sql) === 1,
   'on a battle I started, outcome 1 is not counted as my win');
$arok(preg_match('/\(outcome = 2\) AS mine.*?WHERE defender_id = \$uid/s', $nem_sql) === 1,
   'on a battle they started, outcome 2 is not counted as my win');
/* ONCE PER BRANCH. Checking it appears at all passed a mutation that
   stripped it from the attacking half only -- the other half still had it. */
$arok(substr_count($nem_sql, 'outcome <> 0') === 2,
   'a branch counts unresolved battles');
$arok(strpos($nem_sql, 'HAVING battles >= $min') !== false,
   'a single battle can make somebody an arch nemesis');
/* The ladder resets monthly; a grudge that resets with it is not a grudge. */
$arok(strpos($nem_sql, 'season') === false,
   'the nemesis is scoped to a season');
/* Fewer Fighters than a Crew and they cannot be fielded -- the same bar
   dhca_opponents() applies, so pinning them would offer an unplayable fight. */
$arok(strpos($nem_sql, "< DHCA_CREW_SIZE) return null") !== false,
   'a nemesis who can no longer field a Crew is still pinned');

/* ADDED, NOT JUST STYLED. dhca_opponents() is ORDER BY best DESC LIMIT 24 --
   the same two dozen for everyone -- so a flagged rival is often absent, and a
   highlight on a card that never renders is the feature failing silently.
   The resulting ORDER is dhcarena-standouts-harness.php's job: it runs this
   block for real. Here, only the two moves it is built out of. */
$arok(strpos($ar_src, 'array_splice($foesList, $at, 0, array($st[\'row\']));') !== false,
   'a flagged rival missing from the top 24 is never added to the list');
$arok(preg_match('/\$o\[.user_id.\] !== \$sid\) continue;.*?\$foesList\[\$i\] = \$st\[.row.\];/s', $ar_src) === 1,
   'a flagged rival already in the list keeps the row that has no record on it');
$arok(strpos($ar_src, "\$nem = isset(\$o['battles']);") !== false,
   'the card cannot tell the nemesis row from an ordinary rival');
/*
 * EVERY NUMBER CARRIES ITS SUBJECT, and it took two goes. "21-10" reads as a
 * scoreline with no clue whose side is whose; "Won 21 · Lost 10" names the
 * outcome but still not the PERSON. Both were reported off the live card.
 * The test is that each figure is preceded by who it belongs to.
 */
$arok(strpos($ar_src, "You won <b><?php echo (int)\$o['my_wins']; ?></b>") !== false,
   'the player\'s wins are not labelled as theirs');
$arok(strpos($ar_src, "They won <b><?php echo (int)\$o['their_wins']; ?></b>") !== false,
   'the rival\'s wins are not labelled as theirs, or are still phrased as the player\'s losses');
/* A SHARE, NOT A COUNT. "They started 20" against a 21-11 record forces the
   reader to add the two up and compare, every time. The question the stat
   exists to answer is who comes after whom, and a percentage answers it with
   no arithmetic. */
$arok(strpos($ar_src, 'They started <b><?php echo $pct; ?>%</b>') !== false,
   'the battles they initiated are shown as a raw count again, or lost their label');
/* $battles is dhca_nemesis()'s own row count. my_wins+their_wins happens to
   equal it only because there are no draws -- one is the authority. */
$arok(strpos($ar_src, "max(1, (int)\$o['battles'])") !== false,
   'the percentage is not taken over the authoritative battle count, or can divide by zero');
/* 0% is itself the answer -- you started every one of them -- so the stat is
   no longer hidden when they have never attacked. */
$arok(strpos($ar_src, "if ((int)\$o['they_attacked'] > 0)") === false,
   'the share is hidden at 0%, which is a fact worth stating');
/* The exact figures survive on the title, so nothing is lost. */
$arok(strpos($ar_src, 'of <?php echo (int)$o[\'battles\']; ?> battles"') !== false,
   'the raw counts are gone entirely rather than moved to the title');
$arok(strpos($ar_src, 'their doing') === false && strpos($ar_src, 'nemcame') === false,
   'the awkward "N of them were their doing" line is still there');
/* outcome is only ever 1 or 2, so there are no draws and the battle count is
   always wins plus losses -- printing it restates the two numbers beside it. */
$arok(strpos($ar_src, 'battles</span>') === false,
   'the card restates the total, which is only wins plus losses');
/* Three labelled stats do not fit one line on a phone, and clipping a number
   off the end is worse than wrapping. */
$nem_rec = '';
$nr_at = strpos($ar_css, '.arena-wrap .a-foe .nemrec{');
if ($nr_at !== false) $nem_rec = substr($ar_css, $nr_at, strpos($ar_css, '}', $nr_at) - $nr_at);
$arok($nem_rec !== '' && strpos($nem_rec, 'nowrap') === false,
   'the record is nowrap, so on a narrow card a figure is clipped rather than wrapped');
/* Under 700px the row stops being a flex line and becomes a 28px/1fr grid in
   which EVERY child is assigned column 2 by hand. A child that is not gets
   auto-placed into the avatar column and crushed -- so the nemesis line has
   to be placed there with the rest. */
$ph_at = strpos($ar_css, '@media (max-width:700px)');
$phone = $ph_at === false ? '' : substr($ar_css, $ph_at, 900);
$arok($phone !== '' && strpos($phone, '.arena-wrap .a-foe .nemrec{grid-column:2') !== false,
   'the nemesis record is not placed in the phone grid, so it lands in the 28px avatar column');
/* .top is the ochre reward band and means something unrelated -- what the
   fight pays, not who it is against. A row that is both must read as both. */
/* INSIDE the rule, not anywhere in the file. Searching the whole stylesheet
   passed a mutation that recoloured the row to the ochre reward band,
   because the :hover rule below it still mentioned the red. */
$nem_rule = '';
$nem_at = strpos($ar_css, '.arena-wrap .a-foe.nem{');
if ($nem_at !== false) $nem_rule = substr($ar_css, $nem_at, strpos($ar_css, '}', $nem_at) - $nem_at);
$arok($nem_rule !== '' && strpos($nem_rule, 'rgba(224,70,107') !== false,
   'the nemesis row has no colour of its own');
$arok($nem_rule !== '' && strpos($nem_rule, '232,177,76') === false,
   'the nemesis row reuses the ochre reward band, which means what the fight PAYS');

/*
 * CONTRAST, MEASURED RATHER THAN EYEBALLED -- reported from the live card.
 * On this ground (#0d1e2e under a 6% red wash) the bright #e0466b is 4.12:1,
 * under the 4.5 AA floor, so the figures were the least readable thing on a
 * row that exists to show them. And white on that same red is 3.99:1, so the
 * badge cannot simply be inverted -- it needs a darker fill.
 */
$arok(strpos($ar_css, '.arena-wrap .a-foe .nemrec b{color:#ff6b8a') !== false,
   'the figures are back on a colour that measures under 4.5:1 against the card');
$nt_at = strpos($ar_css, '.arena-wrap .a-foe .nemtag{');
$nemtag = $nt_at === false ? '' : substr($ar_css, $nt_at, strpos($ar_css, '}', $nt_at) - $nt_at);
$arok($nemtag !== '' && strpos($nemtag, 'color:#fff') !== false
   && strpos($nemtag, 'background:#a31f42') !== false,
   'the Arch nemesis badge is not white on the darker fill, so it is under 5:1');
$arok($nemtag !== '' && strpos($nemtag, 'font-size:8.5px') === false,
   'the badge is back to 8.5px, which no contrast ratio rescues');
/*
 * Right-aligned: on the left it sat directly over the avatar.
 *
 * POSITIONED ON THE ROW, NOT THE CHIP, since a card can wear two chips --
 * whoever you fight most is usually whoever you have the most record
 * against. .nemtags is the positioned strip and .nemtag is static inside
 * it; two absolutely-positioned chips would land on top of each other.
 */
$nts_at  = strpos($ar_css, '.arena-wrap .a-foe .nemtags{');
$nemtags = $nts_at === false ? '' : substr($ar_css, $nts_at, strpos($ar_css, '}', $nts_at) - $nts_at);
$arok($nemtags !== '' && strpos($nemtags, 'right:9px') !== false
   && strpos($nemtags, 'left:9px') === false,
   'the rival chips are not right-aligned');
$arok($nemtags !== '' && strpos($nemtags, 'display:flex') !== false,
   'the chips are not laid out in a row, so a second one has nowhere to go');
$arok($nemtag !== '' && strpos($nemtag, 'position:static') !== false,
   'a chip is absolutely positioned, so two of them stack on the same spot');
/* The tab hangs at top:-7px, OUTSIDE its row, and .a-foes is a scrollport
   with overflow:auto -- which clips to its padding box. With no padding-top
   the tab on the first row was sliced in half, and the nemesis is always the
   first row. */
$arok($foes_d !== '' && strpos($foes_d, 'padding-top:9px') !== false,
   'the rival scroller has no padding-top, so the Arch nemesis tab is clipped by it');
$arok($nemtags !== '' && strpos($nemtags, 'top:-7px') !== false,
   'the tabs no longer hang above the row, so the scroller padding is now dead space');
/* Each stat is one nowrap unit, so the line breaks BETWEEN stats and never
   strands a number -- "They started" / "19" on two lines was reported. */
$arok(strpos($ar_css, '.arena-wrap .a-foe .nemrec em{font-style:normal;white-space:nowrap}') !== false,
   'a stat can be split across two lines, stranding its number');

/* ---------------------------------------------------------------------------
 * PIECES THAT MUST NOT FALL
 *
 * The death sequence drops each layer out of the composite. That is wrong
 * for art drawn as SUSPENDED FROM THE TOP OF THE FRAME -- it is cut off at
 * that edge on purpose, so translating it down exposes the cut and the frame
 * edge shows through, which is how bio-circuit-head-port was reported.
 * ------------------------------------------------------------------------- */
require_once __DIR__ . '/dhcfighters-config.php';

$arok(defined('DHCF_DEATH_ANCHORED') && in_array('bio-circuit-head-port', DHCF_DEATH_ANCHORED, true),
   'bio-circuit-head-port is not anchored, so it falls and shows the frame edge');
/* ONE list. The Arena reads the PHP constant rather than keeping a copy --
   the platform has lost five lists to hand-kept duplicates already. */
$arok(strpos($ar_src, 'var DEATH_ANCHORED = <?php echo json_encode(array_values(DHCF_DEATH_ANCHORED)); ?>;') !== false,
   'the Arena does not take its anchored list from DHCF_DEATH_ANCHORED');
/* The check needs the SLUG, and the layer tags only carried the slot. */
$arok(strpos($ar_src, 'data-l="\'+k+\'" data-s="\'+t[k]+\'"') !== false,
   'layer images no longer carry their trait slug, so nothing can be anchored');
$arok(strpos($ar_src, "DEATH_ANCHORED.indexOf(img.getAttribute('data-s')) !== -1") !== false,
   'the anchored test does not look at the slug');
/* Both paths: the staggered fall AND the effects fade. */
$arok(strpos($ar_src, 'if (anchored(img)) return;') !== false,
   'an anchored piece is still given the falling animation');
$arok(strpos($ar_src, "if (img && !anchored(img)) img.className = 'snuff';") !== false,
   'an anchored effect is still snuffed');
/* The group it belonged to must still fall -- a head grouped with an
   anchored headgear has to drop OUT of it, which is the whole picture. */
$kill = substr($ar_src, strpos($ar_src, 'function killAnim'), 1200);
$arok(strpos($kill, 'if (anchored(img)) return;') < strpos($kill, 'found = true;'),
   'an anchored piece still counts toward the stagger, so its group stops falling with it');

/* THIS SECTION DID NOT AFFECT THE EXIT CODE. It printed its failures and
   returned 0 regardless, so every assertion above was advisory -- a mutation
   that reinstated the 330px window printed FAIL and the run still "passed".
   Found by mutation-testing the rival-list change; nothing else would have
   shown it, because a harness that cannot fail looks exactly like one that
   is passing. */
echo ($ar_fail ? "\n$ar_fail arena-layout check(s) FAILED\n" : "\nrival list layout: ok\n");
if ($ar_fail) exit(1);

/* ===========================================================================
 * THE LADDER, BOTH WINDOWS
 *
 * The all-time board is the monthly board without the season clause. Written
 * as a second function it would drift about what a win is, so dhca_ladder()
 * swaps one predicate -- and these checks EXECUTE it against a stub
 * connection rather than reading the file, so what is compared is the SQL the
 * live page actually sends.
 * ======================================================================== */
class DhcaLadderStubConn {
	public $sqls = array();
	public function real_escape_string($s) { return addslashes($s); }
	public function query($sql) { $this->sqls[] = $sql; return false; }
}
$ld_fail = 0;
$ldok = function ($cond, $what) use (&$ld_fail) {
	if (!$cond) { $ld_fail++; echo "  FAIL  $what\n"; }
};
require_once __DIR__ . '/dhcarena-lib.php';

$ldStub = new DhcaLadderStubConn();
dhca_ladder($ldStub, null, 10);
dhca_ladder($ldStub, DHCA_LADDER_ALL_TIME, 10);
$ldok(count($ldStub->sqls) === 2, 'dhca_ladder() did not issue one query per call');
list($ldMonth, $ldAll) = array($ldStub->sqls[0], $ldStub->sqls[1]);

$season = dhca_season();
$ldok(strpos($ldMonth, "b.season = '" . $season . "'") !== false,
   'the monthly ladder is no longer scoped to the current season');
$ldok(strpos($ldAll, 'season') === false,
   'the ALL-TIME ladder still filters on a season, so it is just another monthly board');

/* The two must differ ONLY by that clause. Anything else -- a column, the
   grouping, the order, the limit -- diverging means the two boards would
   disagree about who is top, which is the whole reason this is one function. */
$ldNormal = str_replace("b.season = '" . $season . "' AND ", '', $ldMonth);
$ldok(preg_replace('/\s+/', ' ', $ldNormal) === preg_replace('/\s+/', ' ', $ldAll),
   'the two ladders differ by more than the season clause, so they can disagree about '
 . 'what counts as a win');

/* Both windows count the same outcomes. `outcome IN (1,2)` rather than
   `<> 0` is deliberate: identical result, and an IN list is indexable, which
   the all-time branch needs because it has no season to narrow on. */
foreach (array('monthly' => $ldMonth, 'all-time' => $ldAll) as $ldName => $ldSql) {
	$ldok(strpos($ldSql, 'outcome IN (1,2)') !== false,
	   "the $ldName ladder does not count exactly the resolved battles");
	$ldok(strpos($ldSql, 'b.outcome = 1) AS wins') !== false
	   || strpos($ldSql, 'SUM(b.outcome = 1) AS wins') !== false,
	   "the $ldName ladder stopped counting wins as outcome 1");
	$ldok(strpos($ldSql, 'GROUP BY u.id') !== false, "the $ldName ladder is not grouped per player");
	$ldok(strpos($ldSql, 'LIMIT 10') !== false, "the $ldName ladder ignored its limit");
}

/* The sentinel must not be confusable with an ordinary season. '' and null
   both mean "this month" and always have; a board published under the wrong
   heading is worse than no board. */
$ldStub2 = new DhcaLadderStubConn();
dhca_ladder($ldStub2, '', 10);
$ldok(strpos($ldStub2->sqls[0], "b.season = '" . $season . "'") !== false,
   "an empty season no longer means the current one");

/* And the page renders BOTH, through one renderer. */
$ldPage = preg_replace(array('!/\*.*?\*/!s', '~(?<![:/])//.*$~m'), '',
                       file_get_contents(__DIR__ . '/dhcarena.php'));
$ldok(strpos($ldPage, 'dhca_ladder($conn, DHCA_LADDER_ALL_TIME') !== false,
   'dhcarena.php does not fetch the all-time ladder');
$ldok(substr_count($ldPage, 'dhca_ladder_html(') === 3,
   'the two ladder sections are not both rendered through the one renderer '
 . '(expected two calls plus its definition)');
/* ONCE, inside dhca_ladder_html(). Two occurrences means the markup was
   copied back into the page for one of the sections, which is how the two
   boards start differing by a column. */
$ldok(substr_count($ldPage, '<table class="a-lad">') === 1,
   'the ladder table markup appears ' . substr_count($ldPage, '<table class="a-lad">')
 . ' times, not once -- it belongs only in dhca_ladder_html()');
$ldok(strpos(substr($ldPage, strpos($ldPage, 'function dhca_ladder_html')), '<table class="a-lad">') !== false,
   'the one copy of the ladder markup is not inside dhca_ladder_html()');

echo ($ld_fail ? "\n$ld_fail ladder check(s) FAILED\n" : "ladder, both windows: ok\n");
if ($ld_fail) exit(1);

/* ---------------------------------------------------------------------------
 * THE BOARD DOES NOT RAISE TOOLTIPS WHILE YOU READ IT
 *
 * Every cell carried a native title=, so moving the cursor across the board --
 * which is how the game is played -- popped a tooltip about once a second,
 * under the cursor, covering the tiles being compared. Reported as distracting
 * while choosing a move, which is the only time it ever happened.
 *
 * The information was not lost, and that is what these checks protect:
 *   - an ordinary gem's tooltip only repeated the legend, which is on screen
 *     for the whole battle in a fixed place;
 *   - a bomb's said something the legend did not, so the bomb key joins the
 *     legend while a bomb is live.
 * ------------------------------------------------------------------------- */
$bt_fail = 0;
$btok = function ($cond, $what) use (&$bt_fail) {
	if (!$cond) { $bt_fail++; echo "  FAIL  $what\n"; }
};
$btSrc = file_get_contents(__DIR__ . '/dhcarena.php');
$btNo  = preg_replace(array('!/\*.*?\*/!s', '~(?<![:/])//[^\n]*~'), '', $btSrc);

/* The cell's opening tag, from `class="cell` to the `>` that ends it. Matching
   the whole file for 'title=' would pass on the legend's and the buttons', and
   those are fine -- it is specifically the forty-nine board cells that are not. */
$btOpen = strpos($btNo, '<div class="cell ');
$btKid  = $btOpen === false ? false : strpos($btNo, '<div class="g">', $btOpen);
if ($btOpen !== false && $btKid !== false && $btKid > $btOpen) {
	/* Exactly the opening tag and the bomb-glow wrapper -- everything emitted
	   before the cell's first child. An unbounded window here swallowed half
	   the file and matched the LEGEND's title=, so the check passed on a board
	   that still had tooltips. Bound it, and prove the bound. */
	$btTag = substr($btNo, $btOpen, $btKid - $btOpen);
	$btok(strlen($btTag) < 700,
	   'the cell-tag window is ' . strlen($btTag) . ' bytes, so it reaches past the '
	 . 'cell into markup whose title= is legitimate');
	$btok(strpos($btTag, 'title=') === false,
	   'a board cell carries a title attribute again, so reading the board raises '
	 . 'a tooltip under the cursor on every tile');
} else {
	$btok(false, 'the board cell markup no longer matches, so the tooltip check is '
	           . 'not testing anything -- re-point it at the real cell tag');
}

/* The bomb key must exist, or removing the cell tooltip silently deleted the
   only explanation of what a bomb does. */
$btok(strpos($btNo, 'function paintBombKey(') !== false,
   'there is no bomb key, so nothing on screen says what a bomb does');
$btok(strpos($btNo, 'clears its row and column') !== false
   && strpos($btNo, 'clears everything') !== false,
   'the bomb key does not distinguish the cross bomb from the board bomb');

/*
 * THE STALENESS TRAP, which is the part worth a test.
 *
 * A bomb is armed and spent inside a single cascade, and paintBoard() runs
 * several times during one WITHOUT paintChrome() following it (the land and
 * settle calls). A bomb key painted only from paintChrome() is therefore a
 * cascade behind: it would announce a bomb that just detonated, or miss one
 * that just armed. Asserted by COUNT, because the broken version calls
 * paintBombKey too -- just not from the function that knows.
 */
$btBoard = strpos($btNo, 'function paintBoard(');
$btEnd   = strpos($btNo, 'function ', $btBoard + 10);
$btok($btBoard !== false && $btEnd !== false
   && strpos(substr($btNo, $btBoard, $btEnd - $btBoard), 'paintBombKey()') !== false,
   'paintBoard() does not repaint the bomb key, so during a cascade it reports '
 . 'the bombs from the previous repaint');
$btok(substr_count($btNo, 'paintBombKey()') >= 2,
   'the bomb key is painted from only one place; it needs both the board repaint '
 . 'and the chrome repaint, which run independently');

/* The legend is the fallback the cell tooltip was removed in favour of. If it
   ever stops rendering, the removal above becomes a real loss. */
$btok(strpos($btNo, "\$('legend').innerHTML") !== false,
   'the gem legend no longer renders, which was the reason the per-cell tooltip '
 . 'could be removed at all');

echo ($bt_fail ? "\n$bt_fail board-tooltip check(s) FAILED\n" : "board tooltips: ok\n");
if ($bt_fail) exit(1);

/* ---------------------------------------------------------------------------
 * AN OPEN BATTLE IS RESUMED OR FORFEITED BY A PERSON
 *
 * A battle untouched for six hours used to be resolved as a defeat. That rule
 * was doing almost no work: the board lives in dhc_arena_state, so coming
 * back a week later puts you on the same damaged Crew in the same losing
 * position, and ONE BATTLE AT A TIME is what actually prevents free
 * abandonment. What the timer uniquely did was decide for the player,
 * silently, and because the sweep only ran when that same player next opened
 * the Arena they discovered a loss they had no say in.
 * ------------------------------------------------------------------------- */
$ff_fail = 0;
$ffok = function ($cond, $what) use (&$ff_fail) {
	if (!$cond) { $ff_fail++; echo "  FAIL  $what\n"; }
};
$ffSrc = file_get_contents(__DIR__ . '/dhcarena-lib.php');
$ffNo  = preg_replace(array('!^[ \t]*/\*.*?\*/!ms', '~(?<![:/])//[^\n]*~'), '', $ffSrc);

/* The clock is gone, not merely lengthened. */
$ffok(preg_match('/define\(\s*\x27DHCA_STALE_H\x27/', $ffNo) !== 1,
   'DHCA_STALE_H is still defined, so something can still forfeit on a timer');
$ffok(strpos($ffNo, "age'] < DHCA_STALE_H") === false && strpos($ffNo, 'STALE_H * 3600') === false,
   'the sweep still compares a battle age against a deadline');

/* The sweep must leave a resumable battle alone. That is the whole change:
   previously it loaded the state and finished it as a defeat. */
$swAt = strpos($ffNo, 'function dhca_sweep_stale(');
$swEnd = strpos($ffNo, "\n}", $swAt);
$sw = $swAt === false ? '' : substr($ffNo, $swAt, $swEnd - $swAt);
$ffok($sw !== '', 'dhca_sweep_stale() is gone entirely; the orphaned-row case still needs it');
$ffok(strpos($sw, 'if ($b) return false;') !== false,
   'the sweep still acts on a battle it was able to load, so an abandoned battle is still '
 . 'forfeited without the player being asked');
$ffok(strpos($sw, 'dhca_finish(') === false,
   'the sweep still finishes a battle, which is the automatic defeat being removed');
/* But an unloadable row must still be closed, or one lost state locks the
   player out of the Arena for good. */
$ffok(strpos($sw, 'outcome = 2') !== false,
   'a ledger row with no state behind it is left open, which locks that player out permanently');

/* The deliberate act exists, and marks the whole Crew as fallen. */
$ffok(strpos($ffNo, 'function dhca_forfeit(') !== false, 'there is no way to forfeit deliberately');
$fAt = strpos($ffNo, 'function dhca_forfeit(');
$fEnd = strpos($ffNo, "\n}", $fAt);
$fn = $fAt === false ? '' : substr($ffNo, $fAt, $fEnd - $fAt);
/*
 * THE PART THAT MATTERS FOR THE PER-FIGHTER RECORD. The old automatic forfeit
 * ran the normal finish, so any Fighter still standing banked a SURVIVAL in a
 * battle the code itself called a defeat -- and the survival record depends on
 * "a defeat means every Fighter fell" to be worth anything.
 */
$ffok(preg_match('/foreach \(\$b\[\x27mine\x27\] as \$i => \$f\) \$b\[\x27mine\x27\]\[\$i\]\[\x27ko\x27\] = true;/', $fn) === 1,
   'forfeiting does not mark the Crew as fallen, so walking away still counts as surviving');
$koAt = strpos($fn, "['ko'] = true");
$fiAt = strpos($fn, 'dhca_finish(');
$ffok($koAt !== false && $fiAt !== false && $koAt < $fiAt,
   'the Crew is marked fallen AFTER dhca_finish() has already read the ko flags');
$ffok(strpos($fn, "\$b['over']    = 'foes';") !== false, 'a forfeit does not resolve as a defeat');
$ffok(strpos($fn, "\$b['forfeit'] = true;") !== false,
   'a forfeit is announced to Discord; nobody played it, and the call is on a page-load path');

/* The endpoint exposes it, and the page offers the choice. */
$ffAct = file_get_contents(__DIR__ . '/ajax/dhcarena-action.php');
$ffok(strpos($ffAct, "\$do === 'forfeit'") !== false, 'the action endpoint has no forfeit');
$ffok(strpos($ffAct, 'dhca_forfeit($conn, $user_id)') !== false,
   'the endpoint does not call dhca_forfeit()');
$ffPage = file_get_contents(__DIR__ . '/dhcarena.php');
$ffok(strpos($ffPage, 'id="aForfeit"') !== false, 'the page offers no way to forfeit');
$ffok(strpos($ffPage, 'id="aResumeBlocked"') !== false,
   'the page offers no way to resume from the block, which is the option that should be taken');
/* THE GUARD, not the word. `if (false && !confirm(...))` keeps every string
   a presence check looks for while letting the forfeit straight through. */
$ffok(strpos($ffPage, "if (!confirm('Forfeit this battle?") !== false,
   'forfeiting is not guarded by a bare `if (!confirm(...))`, and it is the only control here '
 . 'that records a defeat without a move being played');
$cfAt = strpos($ffPage, "if (!confirm('Forfeit this battle?");
$pfAt = strpos($ffPage, "post({ do: 'forfeit' }");
$ffok($cfAt !== false && $pfAt !== false && $cfAt < $pfAt,
   'the forfeit is posted before the confirmation is asked for');
/* And the refusal must describe both options rather than just saying no. */
$ffok(strpos($ffNo, 'Finish it or forfeit it') !== false,
   'the entry block still tells the player only that they are stuck');

echo ($ff_fail ? "\n$ff_fail forfeit check(s) FAILED\n" : "resume or forfeit: ok\n");
if ($ff_fail) exit(1);

/* ---------------------------------------------------------------------------
 * THE PARTICIPATION LEDGER
 *
 * Six rows per battle saying who was in it and what happened to them. Nothing
 * recorded Crew composition before this, which is why one corrupted forfeit
 * could not be repaired: there was no record of whose record it was.
 *
 * Driven against an in-memory stand-in, because the whole value of these
 * numbers is in one SQL expression -- `b.outcome = p.side` meaning "this
 * Fighter's Crew won" -- and reading that is not testing it.
 * ------------------------------------------------------------------------- */
$pl_fail = 0;
$plok = function ($cond, $what) use (&$pl_fail) {
	if (!$cond) { $pl_fail++; echo "  FAIL  $what\n"; }
};

/* A stand-in that answers the two real queries from arrays, applying the same
   rules the SQL does. It is not a SQL engine; it implements exactly the
   grouping and the outcome-vs-side comparison under test. */
class DhcaLedgerStub {
	public $rows = array();      // battle_id, fighter_id, side, rank, fell, dealt
	public $battles = array();   // id => array(outcome, defender_id)
	public $sqls = array();
	public function real_escape_string($s) { return $s; }
	public function query($sql) {
		$this->sqls[] = $sql;
		if (strpos($sql, 'INSERT IGNORE INTO dhc_arena_battle_fighters') !== false) {
			preg_match_all('/\((\d+),(\d+),(\d+),(\d+),(\d+),(\d+)\)/', $sql, $m, PREG_SET_ORDER);
			foreach ($m as $v) {
				foreach ($this->rows as $ex)      // INSERT IGNORE: the key wins
					if ($ex['battle_id'] == $v[1] && $ex['fighter_id'] == $v[2]) continue 2;
				$this->rows[] = array('battle_id'=>(int)$v[1], 'fighter_id'=>(int)$v[2],
					'side'=>(int)$v[3], 'rank'=>(int)$v[4], 'fell'=>(int)$v[5], 'dealt'=>(int)$v[6]);
			}
			return true;
		}
		if (strpos($sql, 'FROM dhc_arena_battle_fighters p') !== false) {
			preg_match('/fighter_id = (\d+)/', $sql, $fm);
			$fid = (int)$fm[1];
			/*
			 * THE STUB OBEYS THE SQL for the expressions under test.
			 *
			 * Reimplementing them in PHP meant mutating the query changed
			 * nothing: inverting `b.outcome = p.side` to `<>` and making
			 * survived_wins count the FALLEN both passed. A stub that answers
			 * correctly however it is asked is not testing the caller.
			 *
			 * Not a SQL engine -- it reads the comparison out of the three
			 * expressions whose correctness is the whole point.
			 */
			$winOp = preg_match('/SUM\(b\.outcome\s*(=|<>)\s*p\.side\)\s*AS wins/', $sql, $wo)
			       ? $wo[1] : '?';
			$survFell = preg_match('/SUM\(b\.outcome\s*(=|<>)\s*p\.side AND p\.fell = (\d)\)\s*AS survived_wins/', $sql, $so)
			       ? array($so[1], (int)$so[2]) : array('?', -1);
			$acc = array();
			foreach ($this->rows as $r) {
				if ($r['fighter_id'] !== $fid) continue;
				$bt = isset($this->battles[$r['battle_id']]) ? $this->battles[$r['battle_id']] : null;
				if (!$bt || !in_array((int)$bt['outcome'], array(1,2), true)) continue;
				$s = $r['side'];
				if (!isset($acc[$s])) $acc[$s] = array('side'=>$s,'battles'=>0,'wins'=>0,'losses'=>0,
					'survived_wins'=>0,'fell_in_wins'=>0,'dealt'=>0);
				$same = ((int)$bt['outcome'] === $s);
				$acc[$s]['battles']++;
				$countsAsWin = ($winOp === '=') ? $same : (($winOp === '<>') ? !$same : false);
				$acc[$s][$countsAsWin ? 'wins' : 'losses']++;
				$survSide = ($survFell[0] === '=') ? $same : (($survFell[0] === '<>') ? !$same : false);
				if ($survSide && (int)$r['fell'] === $survFell[1]) $acc[$s]['survived_wins']++;
				if ($same && $r['fell']) $acc[$s]['fell_in_wins']++;
				$acc[$s]['dealt'] += $r['dealt'];
			}
			return new DhcaLedgerRes(array_values($acc));
		}
		if (strpos($sql, 'FROM dhc_arena_battles') !== false && strpos($sql, 'defender_id =') !== false) {
			preg_match('/defender_id = (\d+)/', $sql, $dm);
			$uid = (int)$dm[1]; $d = 0; $h = 0; $l = 0;
			/* BOTH expressions read from the query, not one derived from the
			   other: deriving lost as "not held" meant mutating the lost
			   expression changed nothing. */
			$heldVal = preg_match('/SUM\(outcome = (\d)\) AS held/', $sql, $hv) ? (int)$hv[1] : -1;
			$lostVal = preg_match('/SUM\(outcome = (\d)\) AS lost/', $sql, $lv) ? (int)$lv[1] : -1;
			foreach ($this->battles as $bt) {
				if ((int)$bt['defender_id'] !== $uid) continue;
				if (!in_array((int)$bt['outcome'], array(1,2), true)) continue;
				$d++;
				if ((int)$bt['outcome'] === $heldVal) $h++;
				if ((int)$bt['outcome'] === $lostVal) $l++;
			}
			return new DhcaLedgerRes(array(array('defences'=>$d,'held'=>$h,'lost'=>$l)));
		}
		return new DhcaLedgerRes(array());
	}
}
class DhcaLedgerRes {
	private $r; private $i = 0;
	public $num_rows;
	public function __construct($rows) { $this->r = $rows; $this->num_rows = count($rows); }
	public function fetch_assoc() { return $this->i < count($this->r) ? $this->r[$this->i++] : null; }
}

$L = new DhcaLedgerStub();
/* battle 1: our Crew attacked and WON; fighter 7 fell doing it, 8 and 9 stood. */
$L->battles[1] = array('outcome' => 1, 'defender_id' => 99);
dhca_log_participants($L, 1,
	array('mine' => array(array('rank'=>0,'ko'=>true,'dealt'=>120),
	                      array('rank'=>1,'ko'=>false,'dealt'=>300),
	                      array('rank'=>2,'ko'=>false,'dealt'=>80)),
	      'foes' => array(array('rank'=>0,'ko'=>true,'dealt'=>40),
	                      array('rank'=>1,'ko'=>true,'dealt'=>50),
	                      array('rank'=>2,'ko'=>true,'dealt'=>60))),
	array('mineIds' => array(7, 8, 9), 'foeIds' => array(71, 72, 73)));
$plok(count($L->rows) === 6, 'a finished battle wrote ' . count($L->rows) . ' rows, not six');

/* battle 2: our Crew attacked and LOST, so every one of ours fell. */
$L->battles[2] = array('outcome' => 2, 'defender_id' => 99);
dhca_log_participants($L, 2,
	array('mine' => array(array('rank'=>0,'ko'=>true,'dealt'=>10),
	                      array('rank'=>1,'ko'=>true,'dealt'=>20),
	                      array('rank'=>2,'ko'=>true,'dealt'=>30)),
	      'foes' => array(array('rank'=>0,'ko'=>false,'dealt'=>200),
	                      array('rank'=>1,'ko'=>true,'dealt'=>10),
	                      array('rank'=>2,'ko'=>false,'dealt'=>90))),
	array('mineIds' => array(7, 8, 9), 'foeIds' => array(71, 72, 73)));

/* battle 3: fighter 7 DEFENDED, and the defence HELD. */
$L->battles[3] = array('outcome' => 2, 'defender_id' => 5);
dhca_log_participants($L, 3,
	array('mine' => array(array('rank'=>0,'ko'=>true,'dealt'=>5),
	                      array('rank'=>1,'ko'=>true,'dealt'=>5),
	                      array('rank'=>2,'ko'=>true,'dealt'=>5)),
	      'foes' => array(array('rank'=>0,'ko'=>false,'dealt'=>400),
	                      array('rank'=>1,'ko'=>true,'dealt'=>10),
	                      array('rank'=>2,'ko'=>false,'dealt'=>20))),
	array('mineIds' => array(31, 32, 33), 'foeIds' => array(7, 8, 9)));

$st = dhca_fighter_stats($L, 7);
/* Fighter 7: attacked twice (won 1, lost 1), defended once (held). */
$plok($st['attack']['battles'] === 2, "attack battles {$st['attack']['battles']}, expected 2");
$plok($st['attack']['wins'] === 1 && $st['attack']['losses'] === 1,
   "attack record {$st['attack']['wins']}W/{$st['attack']['losses']}L, expected 1W/1L");
$plok($st['defend']['battles'] === 1 && $st['defend']['wins'] === 1,
   'the defended battle was not counted as a defensive win');
$plok($st['all']['battles'] === 3 && $st['all']['wins'] === 2,
   "overall {$st['all']['wins']}W of {$st['all']['battles']}, expected 2W of 3");

/*
 * THE NUMBER THIS WAS ALL FOR. Fighter 7 fell during the attacking win and
 * stood through the defensive one: 1 of 2 wins survived.
 */
$plok($st['attack']['survived_wins'] === 0 && $st['attack']['fell_in_wins'] === 1,
   'fighter 7 fell during an attacking win and it was not recorded as such');
$plok($st['defend']['survived_wins'] === 1, 'surviving a held defence was not recorded');
$plok($st['all']['survived_wins'] === 1 && $st['all']['fell_in_wins'] === 1,
   'the overall survived/fell split across wins is wrong');
$plok(dhca_survivability($st['all']) === 50.0,
   'overall win survivability came out ' . var_export(dhca_survivability($st['all']), true) . ', expected 50.0');
$plok(dhca_survivability($st['attack']) === 0.0, 'attacking win survivability should be 0');
$plok(dhca_survivability($st['defend']) === 100.0, 'defending win survivability should be 100');

/* A LOSS CARRIES NO SURVIVAL INFORMATION, because a Crew only loses when every
   Fighter has fallen. Nothing may ever be counted as surviving a defeat. */
$surviveLoss = 0;
foreach ($L->rows as $r) {
	$bt = $L->battles[$r['battle_id']];
	if ((int)$bt['outcome'] !== $r['side'] && !$r['fell']) $surviveLoss++;
}
$plok($surviveLoss === 0,
   $surviveLoss . ' Fighters are recorded as standing at the end of a battle their Crew lost');

/* Damage follows the Fighter, not the side. */
$plok($st['attack']['dealt'] === 130 && $st['defend']['dealt'] === 400,
   "dealt came out {$st['attack']['dealt']} attacking / {$st['defend']['dealt']} defending, "
 . 'expected 130 / 400');

/* A Fighter nobody has fielded answers honestly rather than dividing by zero. */
$none = dhca_fighter_stats($L, 4242);
$plok($none['all']['battles'] === 0, 'an unfought Fighter reports battles');
$plok(dhca_survivability($none['all']) === null, 'an unfought Fighter got a survivability rate');

/* Settling the same battle twice must not double-count it. */
$before = count($L->rows);
dhca_log_participants($L, 1,
	array('mine' => array(array('rank'=>0,'ko'=>true,'dealt'=>120)),
	      'foes' => array(array('rank'=>0,'ko'=>true,'dealt'=>40))),
	array('mineIds' => array(7), 'foeIds' => array(71)));
$plok(count($L->rows) === $before, 'a battle settled twice wrote its rows twice');

/*
 * And the player-level defensive record, which needed no new table at all.
 *
 * ASYMMETRIC ON PURPOSE. With one held and one lost, swapping which outcome
 * counts as "held" produces the identical pair of numbers and the mutation
 * that inverted it passed. Two held against one lost can tell them apart.
 */
$L->battles[4] = array('outcome' => 2, 'defender_id' => 99);   // another successful defence
$dr = dhca_defence_record($L, 99);
$plok($dr['defences'] === 3 && $dr['held'] === 2 && $dr['lost'] === 1,
   "defence record {$dr['held']} held / {$dr['lost']} lost of {$dr['defences']}, expected 2/1 of 3");
$plok(dhca_defence_record($L, 12345)['defences'] === 0, 'a player never attacked shows defences');

/* The writer must be called from the place that settles a battle. */
$plSrc = preg_replace(array('!^[ \t]*/\*.*?\*/!ms', '~(?<![:/])//[^\n]*~'), '',
                      file_get_contents(__DIR__ . '/dhcarena-lib.php'));
$fnAt = strpos($plSrc, 'function dhca_finish(');
$fnEnd = strpos($plSrc, "\n}", $fnAt);
$plok(strpos(substr($plSrc, $fnAt, $fnEnd - $fnAt), 'dhca_log_participants(') !== false,
   'dhca_finish() does not write the ledger, so nothing is ever recorded');
$plok(strpos($plSrc, 'INSERT IGNORE INTO dhc_arena_battle_fighters') !== false,
   'the ledger insert is not IGNORE, so a double settle can double-count');
/* `rank` is reserved from MySQL 8.0.2 (the window function), so every
   reference to it has to be quoted or the statement fails outright. */
$plok(strpos($plSrc, '`rank`') !== false && !preg_match('/\(battle_id, fighter_id, side, rank,/', $plSrc),
   'the ledger INSERT names rank unquoted, which is a reserved word from MySQL 8.0.2');
$plSchema = file_get_contents(__DIR__ . '/dhcarena-schema.md');
$ctAt = strpos($plSchema, 'CREATE TABLE IF NOT EXISTS dhc_arena_battle_fighters');
$ct = $ctAt === false ? '' : substr($plSchema, $ctAt, 700);
$plok($ct !== '' && strpos($ct, '`rank`') !== false,
   'the CREATE declares rank unquoted; it is reserved from MySQL 8.0.2 and the statement '
 . 'will not run');

/* ---- the at-a-glance profile ------------------------------------------- */
$mkSt = function ($b, $w, $sw, $d) {
	$x = array('battles'=>$b,'wins'=>$w,'losses'=>$b-$w,'survived_wins'=>$sw,
	           'fell_in_wins'=>$w-$sw,'dealt'=>$d);
	return array('all'=>$x, 'attack'=>$x, 'defend'=>$x);
};
/*
 * A RATE NEEDS A SAMPLE. Three battles is an anecdote -- one unlucky front
 * rank takes a Fighter from 100% to 50% -- so below the floor it says how
 * many it has fought, which is both honest and the useful instruction.
 */
$plok(dhca_fighter_profile($mkSt(0,0,0,0))['key'] === 'unproven', 'an unfought Fighter is not Unproven');
$plok(dhca_fighter_profile($mkSt(0,0,0,0))['fact'] === 'Not fought yet',
   'an unfought Fighter does not say so plainly');
$plok(dhca_fighter_profile($mkSt(4,4,4,900))['key'] === 'unproven',
   'four spotless battles already reads as Reliable, which a single bad one would undo');
$plok(dhca_fighter_profile($mkSt(10,8,7,0))['key'] === 'reliable', '7 of 8 wins survived is not Reliable');
$plok(dhca_fighter_profile($mkSt(10,8,2,0))['key'] === 'risky',    '2 of 8 wins survived is not Risky');
$plok(dhca_fighter_profile($mkSt(12,9,5,0))['key'] === 'even',     '5 of 9 wins survived is not Even');
/* Fought plenty, never won: survival cannot say anything, because a Crew only
   loses when every Fighter has fallen. */
$plok(dhca_fighter_profile($mkSt(8,0,0,900))['key'] === 'winless',
   'a Fighter that has never won is rated on a survival rate it cannot have');
$plok(dhca_fighter_profile($mkSt(8,0,0,900))['pct'] === null, 'a winless Fighter was given a percentage');

/* The position is SHOWN, never corrected for -- a match of 3 reaches the
   front, so the same rate means different things in different seats. */
$pfFront = dhca_fighter_profile($mkSt(10,8,7,0), 0);
$plok(strpos($pfFront['fact'], 'front') !== false, 'the position a Fighter plays is not in the fact');
/* A MIDDLING RATE, deliberately. At 87% every threshold agrees whatever the
   position, so a mutation that shifted the bar for back-rank Fighters changed
   nothing and passed. 50% sits where a shifted threshold would show. */
$pfMidRate = $mkSt(10, 8, 4, 0);          // 4 of 8 wins survived = 50%
$keys = array();
foreach (array(0, 1, 2) as $seat) $keys[] = dhca_fighter_profile($pfMidRate, $seat)['key'];
$plok(count(array_unique($keys)) === 1,
   'the label changes with position (' . implode(', ', $keys) . '), which silently invents a '
 . 'number nobody can check -- the position is shown so the player can judge it, not corrected for');
/* Damage per battle, not total: a veteran would otherwise always look better. */
$plok(dhca_fighter_profile($mkSt(10,8,7,4000))['dpb'] === 400, 'damage is not reported per battle');
$plok(dhca_fighter_profile($mkSt(0,0,0,0))['dpb'] === 0, 'an unfought Fighter divides by zero for damage');

/* The picker must not hide a Fighter the player has already chosen. */
$pfPage = file_get_contents(__DIR__ . '/dhcarena.php');
$plok(strpos($pfPage, "c.classList.contains('sel')") !== false,
   'the reliability filter does not exempt chosen Fighters, so narrowing the list would hide '
 . 'Crew members the player had already picked');
$plok(strpos($pfPage, 'data-pf="<?php echo htmlspecialchars(isset($f[\'profile\'])') !== false,
   'the picker cards carry no profile key, so nothing can filter on it');
$plok(strpos($pfPage, 'title="<?php echo htmlspecialchars($pf[\'fact\']') !== false,
   'the label has no fact behind it, so a player cannot check it');

echo ($pl_fail ? "\n$pl_fail ledger check(s) FAILED\n" : "participation ledger: ok\n");
if ($pl_fail) exit(1);

/*
 * EVERY CLASS THIS PAGE INVENTS IS ACTUALLY STYLED.
 *
 * The reliability row shipped with none. .a-pf-filter and .a-pf-note were in
 * the markup and nowhere in the stylesheet, so its buttons took the
 * browser's default -- light pills with blue text, under two rows of dark
 * ones. PHP was happy, the markup was valid, and nothing was looking at the
 * pairing.
 *
 * Only the a- prefixed classes are checked: those are this page's own
 * invention. Everything else belongs to flexbox.css or a shared include and
 * is not this file's to define.
 */
$cs_fail = 0;
$csok = function ($cond, $what) use (&$cs_fail) {
	if (!$cond) { $cs_fail++; echo "FAIL  $what\n"; }
};
$csSrc = file_get_contents(__DIR__ . '/dhcarena.php');
$csCss = '';
if (preg_match_all('/<style>(.*?)<\/style>/s', $csSrc, $sm)) $csCss = implode("\n", $sm[1]);
/*
 * WITHOUT ITS COMMENTS. The note above .a-pf-filter names the class while
 * explaining why it exists, and a plain strpos cannot tell prose from a
 * selector -- with every rule deleted, the comment alone still answered
 * "yes, that is styled". A comment describing a rule is the one thing
 * guaranteed to survive the rule's removal.
 */
$csCss = preg_replace('~/\*.*?\*/~s', '', $csCss);
$csok($csCss !== '', 'the page has a stylesheet to check against');
$csok(strpos($csCss, 'shipped with no CSS') === false, 'and its comments are stripped from it');

$csUsed = array();
/* From the markup. */
preg_match_all('/class=[\'"]([^\'"<]*)[\'"]/', $csSrc, $cm);
foreach ($cm[1] as $attr) {
	foreach (preg_split('/\s+/', $attr) as $c) {
		$c = trim($c);
		if ($c === '' || strpos($c, 'a-') !== 0) continue;
		$csUsed[$c] = true;
	}
}
/*
 * AND FROM THE SCRIPT, which is where the one that mattered came from.
 *
 * The reliability filter works by toggling pf-hid on a card. That class had
 * no rule, so it went on, came off, and the grid never changed -- the filter
 * looked inert because visually it was. It never appeared in a class
 * attribute, and it is not a- prefixed, so a check that read only markup and
 * only a- names walked straight past the live bug while reporting the page
 * clean.
 *
 * A class a script toggles is exactly the kind that has no markup to notice
 * it is missing from.
 */
preg_match_all('/classList\.(?:toggle|add)\(\s*[\'"]([a-z0-9_-]+)[\'"]/i', $csSrc, $jm);
foreach ($jm[1] as $c) $csUsed[$c] = true;
$csok(count($jm[1]) > 0, 'found classes the script toggles (' . count($jm[1]) . ')');
$csok(isset($csUsed['pf-hid']), "including pf-hid, the one that was missed");
$csok(count($csUsed) > 10, "classes this page is responsible for (" . count($csUsed) . ')');
$csUnstyled = array();
foreach (array_keys($csUsed) as $c) {
	if (strpos($csCss, '.' . $c) === false) $csUnstyled[] = $c;
}
$csok(!$csUnstyled, 'every class the page invents is styled'
   . ($csUnstyled ? ' -- these are not: ' . implode(', ', $csUnstyled) : ''));

/*
 * ONE ROW, THREE FILTERS, THREE HANDLERS THAT MUST NOT CLOBBER EACH OTHER.
 *
 * Sort, Fights like and Record share .a-tools now -- they narrow one list
 * and reading as two stacked tools was wrong. The cost of sharing a
 * container is that a handler doing querySelectorAll('button') reaches the
 * other two: pressing a Record chip would clear the active state off the
 * sort and the kit, leaving a sort still applied with nothing showing as
 * selected.
 *
 * Every handler must therefore qualify by its own data attribute.
 */
$fs_fail = 0;
$fsok = function ($cond, $what) use (&$fs_fail) {
	if (!$cond) { $fs_fail++; echo "FAIL  $what\n"; }
};
$fsSrc = file_get_contents(__DIR__ . '/dhcarena.php');
$fsok(substr_count($fsSrc, 'class="a-pf-filter"') === 0,
   'the separate reliability row is gone');
$fsok(substr_count($fsSrc, 'class="a-pf-note"') === 0, 'and so is its own note');
$fsok(strpos($fsSrc, '<div class="a-tools" id="aPfFilter">') !== false,
   'Record lives in the same row as Sort and Fights like');
$fsok(substr_count($fsSrc, '<span class="lbl">') >= 3,
   'and the row is labelled in three sections (' . substr_count($fsSrc, '<span class="lbl">') . ')');
/* No handler may select buttons without qualifying them. */
preg_match_all('/querySelectorAll\(\s*[\'"]([^\'"]*button[^\'"]*)[\'"]/', $fsSrc, $qm);
$fsok(count($qm[1]) > 0, 'found the button selectors (' . count($qm[1]) . ')');
$loose = array();
foreach ($qm[1] as $sel) {
	if (strpos($sel, '[data-') === false) $loose[] = $sel;
}
$fsok(!$loose, 'every one qualifies by a data attribute, so no handler clears another\'s state'
   . ($loose ? ' -- these do not: ' . implode(' | ', $loose) : ''));
/* And the explanation is one sentence above them, not one per row. */
/* The MARKUP, not the string. The comment left where those rules used to
   live names the class while explaining its absence -- the third time today
   a check has matched my own prose instead of the code. */
$fsok(substr_count($fsSrc, 'class="a-pf-note"') === 0, 'the second explanation is gone');
$fsok(strpos($fsSrc, 'Sort the list, narrow it to one fighting') !== false,
   'replaced by one sentence covering all three');
$fsok(strpos($fsSrc, '$pfShow') !== false,
   'and the Record half of it only appears when Record does');

echo ($fs_fail ? "\n$fs_fail filter-row check(s) FAILED\n" : "one filter row: ok\n");

echo ($cs_fail ? "\n$cs_fail styling check(s) FAILED\n" : "every invented class is styled: ok\n");

/* ------------------------------------------------------------------ */
/*
 * THE ODDS ARE A COLUMN, so they have to be in the same place on every
 * row.
 *
 * .nemrec is flex:1 0 100%, which breaks the line -- so whatever
 * follows it in the markup goes down with it. The odds did, landing on
 * a third line at the LEFT while every row without a rivalry kept
 * theirs at the right of the first. Reported as exactly that: the eye
 * should not have to scan sideways to compare a column just because a
 * row is highlighted.
 */
$ar_fail = 0;
function ar_ok($cond, $msg) { global $ar_fail; if (!$cond) { $ar_fail++; echo "  FAIL  $msg\n"; } }
$ar = (string)@file_get_contents(__DIR__ . '/dhcarena.php');

/*
 * IT PARSES. Trivial, and the one thing that catches an unbalanced
 * if/endif -- which is the shape of mistake hand-editing this template
 * produces, and the shape a source-reading harness is blind to: every
 * span is still present and still in the right order, the file just
 * does not run. The page is 3,000 lines of interleaved PHP and markup
 * and nothing else here would notice.
 */
$lint = (string)shell_exec('php -l ' . escapeshellarg(__DIR__ . '/dhcarena.php') . ' 2>&1');
ar_ok(strpos($lint, 'No syntax errors') !== false, 'dhcarena.php does not parse: ' . trim($lint));

$i_odds = strpos($ar, '<span class="odds">');
$i_rec  = strpos($ar, '<span class="nemrec">');
ar_ok($i_odds !== false && $i_rec !== false, 'the rival row lost one of its two spans');
ar_ok($i_odds !== false && $i_rec !== false && $i_odds < $i_rec,
   'the odds are written after the history line, which breaks the row and takes them down with it');

/*
 * AND WHICH SIDE OF THE GUARD EACH ONE IS ON.
 *
 * Reordering them by hand put .odds INSIDE `if ($nem)` and left .nemrec
 * outside it -- so every row without a rivalry lost its percentages
 * entirely, and every such row then rendered the history block against
 * keys that are not there. Four "Undefined array key" warnings per row,
 * printed into the page, because display_errors is on.
 *
 * Checking the ORDER of the two spans did not catch that: the order was
 * right. So this walks the row counting if/endif depth and asks where
 * each span actually sits.
 */
$row_at  = strpos($ar, '<div class="a-foe');
$row_end = strpos($ar, '<?php endforeach; ?>', $row_at);
$row = ($row_at === false || $row_end === false) ? '' : substr($ar, $row_at, $row_end - $row_at);
ar_ok($row !== '', 'the foe row could not be isolated; the checks below prove nothing');
/* Walk the PHP control structures in order and record the open guards
   at the point each span is emitted. */
preg_match_all('/<\?php if \((\$[a-z_]+)\):|<\?php endif;|<span class="(odds|nemrec)"/',
	$row, $tok, PREG_SET_ORDER);
$stack = array();
$under = array();
foreach ($tok as $t) {
	if (!empty($t[2])) { $under[$t[2]] = $stack; continue; }
	if (strpos($t[0], 'endif') !== false) { array_pop($stack); continue; }
	$stack[] = $t[1];
}
ar_ok(isset($under['odds']) && !in_array('$nem', $under['odds'], true),
   'the odds are inside the rivalry guard, so a row without a rivalry has none at all');
ar_ok(isset($under['nemrec']) && in_array('$nem', $under['nemrec'], true),
   'the history line is outside the rivalry guard, so every ordinary row renders it '
 . 'against keys that do not exist');

/* AND THE HISTORY STILL TAKES ITS OWN LINE, or it would sit inline and
   squeeze the name. */
ar_ok(preg_match('/\.a-foe \.nemrec\{[^}]*flex:1 0 100%/', $ar) === 1,
   'the history line no longer spans the row');

/*
 * AND ON A NARROW SCREEN, where the row becomes a grid, EVERY child
 * needs an explicit column. One that does not gets auto-placed into the
 * 28px avatar track and rendered as a crushed stack of single
 * characters -- the comment in that media query says so about .nemrec,
 * and .odds was the child it forgot.
 */
$mq_at = strpos($ar, '@media (max-width:700px){');
ar_ok($mq_at !== false, 'the narrow-screen block moved; the checks below prove nothing');
$mq = $mq_at === false ? '' : substr($ar, $mq_at, strpos($ar, "\n}\n", $mq_at) - $mq_at);
ar_ok(strpos($mq, 'display:grid') !== false, 'the narrow row is no longer a grid');
foreach (array('.n', '.m', '.odds', '.nemrec', '.paidtag') as $child) {
	ar_ok(preg_match('/\.a-foe ' . preg_quote($child, '/') . '\{[^}]*grid-column:2/', $mq) === 1,
	   "$child has no column in the narrow grid, so it lands in the avatar track");
}

echo ($ar_fail ? "\n$ar_fail rival-row check(s) FAILED\n" : "the odds line up on every row: ok\n");
if ($cs_fail || $fs_fail || $ar_fail) exit(1);

/* ---------------------------------------------------------------- *
 * NO PHP LEAKS INTO THE PAGE AS TEXT
 * ---------------------------------------------------------------- *
 * A refactor replaced a `foreach` line in the Crews feed and left the
 * block that followed it -- a comment and five assignments -- stranded
 * OUTSIDE any <?php tag. The live page printed its own source into the
 * feed and then threw "Undefined variable" for every one of them.
 *
 * php -l CANNOT SEE THIS: the file is still valid PHP and the orphaned
 * code is just HTML to the parser. So the test is on the OUTPUT shape --
 * strip every <?php ... ?> span and what is left must look like markup.
 */
$pl_fail = 0;
function pl_ok($c, $what) { global $pl_fail; if (!$c) { $pl_fail++; echo "  FAIL  $what\n"; } }
$pg2    = file_get_contents(__DIR__ . '/dhcarena.php');
$bodyAt = strpos($pg2, '<div class="arena-wrap">');
pl_ok($bodyAt !== false, 'the page body could not be located');
if ($bodyAt !== false) {
	$body = substr($pg2, $bodyAt);
	pl_ok(substr_count($body, '<?php') === substr_count($body, '?>'),
	   'the body has ' . substr_count($body, '<?php') . ' opening PHP tags and '
	 . substr_count($body, '?>') . ' closing ones, so a block is stranded and will print');
	$html = preg_replace('~<\?php.*?\?>~s', '', $body);
	/* script and style carry their own $ and comment syntax, and neither is PHP. */
	$html = preg_replace('~(?s)<script\b.*?</script>~', '', $html);
	$html = preg_replace('~(?s)<style\b.*?</style>~', '', $html);
	pl_ok(preg_match('~\$[a-zA-Z_]\w*\s*=\s~', $html, $lm) === 0,
	   'a PHP assignment sits outside a PHP tag, so the page prints its own source: '
	 . (isset($lm[0]) ? trim($lm[0]) : ''));
	pl_ok(strpos($html, '<?php') === false,
	   'an unclosed <?php survives the strip, so the rest of the page is inside it');
}
echo ($pl_fail ? "\n$pl_fail source-leak check(s) FAILED\n" : "the page never prints its own source: ok\n");

/* ---------------------------------------------------------------- *
 * THE SHARE POST, EXECUTED
 * ---------------------------------------------------------------- */
/*
 * WHAT THE PLAYER ACTUALLY POSTS, which no check in this file could answer.
 *
 * Every loss ever played shared the same sentence -- "My Crew went down to
 * X's Crew in the DHC Arena. Going again." -- describing no battle and
 * asserting an intention the game has no way to know. Nothing was wrong
 * with the source text; the source text was the bug, and a grep for a
 * phrase cannot tell a good sentence from a useless one.
 *
 * So shareLink() is lifted out of the page and RUN, and the post is read
 * back off the href. Reported here as one assertion, the same arrangement
 * nft-dossier-harness.php uses for its panel render.
 */
$sh_out = array(); $sh_rc = 0;
exec('node ' . escapeshellarg(__DIR__ . '/dhcarena-share-harness.js') . ' 2>&1', $sh_out, $sh_rc);
if ($sh_rc !== 0) {
	echo "  --- dhcarena-share-harness.js ---\n";
	foreach ($sh_out as $line) if (strpos($line, 'FAIL') !== false) echo "  $line\n";
	echo "\nthe share post check FAILED\n";
} else {
	echo "the share post says what the battle did: ok\n";
}
