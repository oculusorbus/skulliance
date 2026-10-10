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
$arok($foes_d !== '' && strpos($foes_d, 'max-height:330px') !== false
   && strpos($foes_d, 'overflow:auto') !== false,
   'the rival list lost its desktop window, which is what keeps the panel beside the board');

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

/* PINNED, NOT JUST STYLED. dhca_opponents() is ORDER BY best DESC LIMIT 24 --
   the same two dozen for everyone -- so the nemesis is usually absent, and a
   highlight on a card that never renders is the feature failing silently. */
$arok(strpos($ar_src, 'array_unshift($foesList, $nemesis);') !== false,
   'the nemesis is not inserted into the rival list');
$arok(preg_match('/if \(\(int\)\$o\[.user_id.\] === \$nid\) \{ unset\(\$foesList\[\$i\]\); break; \}/', $ar_src) === 1,
   'a nemesis already in the top 24 is listed twice');
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
$arok(strpos($ar_src, "They started <b><?php echo (int)\$o['they_attacked']; ?></b>") !== false,
   'the battles they initiated are not labelled, or use the old "their doing" phrasing');
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
/* Right-aligned: on the left it sat directly over the avatar. */
$arok($nemtag !== '' && strpos($nemtag, 'right:9px') !== false
   && strpos($nemtag, 'left:9px') === false,
   'the Arch nemesis badge is not right-aligned');
/* The tab hangs at top:-7px, OUTSIDE its row, and .a-foes is a scrollport
   with overflow:auto -- which clips to its padding box. With no padding-top
   the tab on the first row was sliced in half, and the nemesis is always the
   first row. */
$arok($foes_d !== '' && strpos($foes_d, 'padding-top:9px') !== false,
   'the rival scroller has no padding-top, so the Arch nemesis tab is clipped by it');
$arok($nemtag !== '' && strpos($nemtag, 'top:-7px') !== false,
   'the tab no longer hangs above the row, so the scroller padding is now dead space');
/* Each stat is one nowrap unit, so the line breaks BETWEEN stats and never
   strands a number -- "They started" / "19" on two lines was reported. */
$arok(strpos($ar_css, '.arena-wrap .a-foe .nemrec em{font-style:normal;white-space:nowrap}') !== false,
   'a stat can be split across two lines, stranding its number');

echo ($ar_fail ? "\n$ar_fail arena-layout check(s) FAILED\n" : "\nrival list layout: ok\n");
