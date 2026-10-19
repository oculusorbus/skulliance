<?php
/* dhcarena-standouts-harness.php - CLI only. No database, no network.
 *
 * The three flags on a rival card: Arch nemesis, Tormentor, Sitting duck.
 *
 * WHAT GOES WRONG HERE IS A LABEL ON A PERSON. These words are attached
 * to real players, in public, by name, and two of them are judgements:
 * Tormentor says somebody beats you and Sitting duck says you beat them.
 * The arithmetic deciding who wears which has to be right, because the
 * failure is not a wrong number on a page -- it is calling somebody a
 * sitting duck when you are 5-6 against them.
 *
 * That exact trap has already been hit once on this platform: the rivalry
 * panel took "best against" as the max of (won - lost) without requiring
 * a winning record, and called a 5-6 pairing a best.
 *
 * dhca_standouts() is run against a fake mysqli, so this is the shipping
 * function rather than a copy of its reasoning.
 *
 * Usage: php dhcarena-standouts-harness.php
 */
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

$fail = 0;
function ok($cond, $what) { global $fail; if (!$cond) { $fail++; echo "  FAIL  $what\n"; } }

require_once __DIR__ . '/harness-strip.php';
require_once __DIR__ . '/dhcarena-lib.php';

class SoRes {
	private $rows; private $i = 0;
	public function __construct($rows) { $this->rows = $rows; $this->num_rows = count($rows); }
	public $num_rows = 0;
	public function fetch_assoc() { return $this->i < count($this->rows) ? $this->rows[$this->i++] : null; }
}
/* Answers the two queries dhca_standouts() makes, and counts them. */
class SoConn {
	public $pairs; public $crew; public $queries = 0; public $sql = array();
	public function __construct($pairs, $crew) { $this->pairs = $pairs; $this->crew = $crew; }
	public function query($sql) {
		$this->queries++; $this->sql[] = $sql;
		if (strpos($sql, 'dhc_arena_battles') !== false) {
			$out = array();
			foreach ($this->pairs as $p) {
				/* The HAVING the real query applies. */
				if ($p[1] < DHCA_NEMESIS_MIN) continue;
				$out[] = array('user_id' => $p[0], 'username' => 'u' . $p[0],
				               'discord_id' => '1', 'avatar' => 'a',
				               'battles' => $p[1], 'my_wins' => $p[2], 'their_wins' => $p[3],
				               'they_attacked' => 0, 'last_at' => '2026-10-01');
			}
			return new SoRes($out);
		}
		if (strpos($sql, 'dhc_fighters') !== false) {
			$out = array();
			foreach ($this->crew as $uid => $n) {
				$out[] = array('user_id' => $uid, 'fighters' => $n, 'best' => 100);
			}
			return new SoRes($out);
		}
		return false;
	}
}

/* pairs: user_id, battles, my_wins, their_wins */
function so($pairs, $crew = null) {
	if ($crew === null) {
		$crew = array();
		foreach ($pairs as $p) $crew[$p[0]] = DHCA_CREW_SIZE;
	}
	$c = new SoConn($pairs, $crew);
	$r = dhca_standouts($c, 7);
	$r['_conn'] = $c;
	return $r;
}
$who = function ($row) { return $row ? (int)$row['user_id'] : 0; };

echo "a word is only earned by a one-sided record\n";
{
	/*
	 * 6-5 IS NOT A TORMENTOR AND 5-6 IS NOT A SITTING DUCK. Taking the
	 * worst and best of a set regardless of sign is how the rivalry panel
	 * came to call a losing record somebody's best.
	 */
	$r = so(array(array(1, 11, 6, 5), array(2, 11, 5, 6)));
	ok($who($r['duck']) === 1, 'the only winning record (6-5) is not the sitting duck');
	ok($who($r['tormentor']) === 2, 'the only losing record (5-6) is not the tormentor');

	/* All winning: nobody is a tormentor, however narrow the worst is. */
	$r = so(array(array(1, 20, 18, 2), array(2, 20, 11, 9)));
	ok($r['tormentor'] === null,
	   'a player you are 11-9 UP on was called your tormentor because it was your worst record');
	ok($who($r['duck']) === 1, 'the best winning record is not the duck');

	/* All losing: nobody is a duck. */
	$r = so(array(array(1, 20, 2, 18), array(2, 20, 9, 11)));
	ok($r['duck'] === null,
	   'a player you are 9-11 DOWN to was called a sitting duck because it was your best record');
	ok($who($r['tormentor']) === 1, 'the worst losing record is not the tormentor');

	/* Dead even is neither. */
	$r = so(array(array(1, 10, 5, 5)));
	ok($r['tormentor'] === null && $r['duck'] === null, 'a 5-5 record earned a word');
	ok($who($r['nemesis']) === 1, 'the only opponent is not the nemesis');
}

echo "\nthe same person can never be both\n";
{
	/* Mutually exclusive by construction: one needs wins > losses and the
	   other needs losses > wins. Checked anyway, because the day somebody
	   loosens either condition this is the first thing to break. */
	foreach (array(array(array(1, 9, 9, 0)),
	               array(array(1, 9, 0, 9)),
	               array(array(1, 20, 11, 9), array(2, 20, 9, 11))) as $set) {
		$r = so($set);
		ok(!($r['tormentor'] && $r['duck'] && $who($r['tormentor']) === $who($r['duck'])),
		   'one player is wearing Tormentor and Sitting duck at once');
	}
}

echo "\nthe nemesis can wear a second word, and usually does\n";
{
	/* Whoever you fight most is whoever you have the most record against,
	   so the card has to be able to carry two chips. */
	$r = so(array(array(1, 60, 25, 35), array(2, 6, 5, 1)));
	ok($who($r['nemesis']) === 1, 'the 60-battle pairing is not the nemesis');
	ok($who($r['tormentor']) === 1, 'the nemesis is not also recognised as the tormentor');
	ok($who($r['duck']) === 2, 'the 5-1 record is not the duck');
}

echo "\nnobody is flagged on too little evidence\n";
{
	/* DHCA_NEMESIS_MIN battles, the same bar the nemesis has always had. */
	$r = so(array(array(1, DHCA_NEMESIS_MIN - 1, 0, DHCA_NEMESIS_MIN - 1)));
	ok($r['nemesis'] === null && $r['tormentor'] === null && $r['duck'] === null,
	   'a pairing under ' . DHCA_NEMESIS_MIN . ' battles was flagged');
	$r = so(array(array(1, DHCA_NEMESIS_MIN, 0, DHCA_NEMESIS_MIN)));
	ok($who($r['tormentor']) === 1, 'exactly ' . DHCA_NEMESIS_MIN . ' battles is not enough');
	/* The bar above is applied by the FAKE connection, because a stub
	   cannot run SQL -- so the real query's HAVING has to be read out of
	   the source or dropping it would pass here. */
	$lib = file_get_contents(__DIR__ . '/dhcarena-lib.php');
	$fn  = substr($lib, strpos($lib, 'function dhca_standouts('));
	$fn  = substr($fn, 0, strpos($fn, "\n}"));
	ok(strpos($fn, 'HAVING battles >= $min') !== false,
	   'the aggregate no longer filters on a minimum number of battles');
	ok(strpos($fn, '$min = (int)DHCA_NEMESIS_MIN;') !== false,
	   'the minimum is not DHCA_NEMESIS_MIN, so it can drift from the nemesis bar');

	/* And nobody who cannot field a Crew, because the card is a button
	   that starts a battle -- the same bar dhca_opponents() applies. */
	$r = so(array(array(1, 20, 2, 18), array(2, 20, 18, 2)),
	        array(1 => DHCA_CREW_SIZE - 1, 2 => DHCA_CREW_SIZE));
	ok($r['tormentor'] === null,
	   'somebody with fewer than ' . DHCA_CREW_SIZE . ' Fighters was pinned, and they cannot be fielded');
	ok($who($r['duck']) === 2, 'the player who CAN be fielded was dropped too');
}

echo "\nthree flags, one query\n";
{
	$r = so(array(array(1, 20, 2, 18), array(2, 20, 18, 2), array(3, 40, 20, 20)));
	/* One aggregate plus one crew lookup. Three separate round trips to
	   decorate one list is what dhca_fighter_perf() exists to avoid. */
	ok($r['_conn']->queries === 2,
	   'dhca_standouts() made ' . $r['_conn']->queries . ' queries; it should make two');
	/* QUERIES that touch the table, not mentions of it: the aggregate is
	   a UNION ALL and names dhc_arena_battles twice inside one statement,
	   so counting the string said "two" for correct code. */
	$hits = 0;
	foreach ($r['_conn']->sql as $q) if (strpos($q, 'dhc_arena_battles') !== false) $hits++;
	ok($hits === 1, 'the battle aggregate runs ' . $hits . ' times; once is enough for all three');
}

echo "\nthe page shows every flag, in its own colour\n";
{
	$p = file_get_contents(__DIR__ . '/dhcarena.php');
	ok(strpos($p, 'dhca_standouts($conn, $user_id)') !== false, 'the page does not ask for the flags');
	foreach (array('nemesis' => 'Arch nemesis', 'tormentor' => 'Tormentor',
	               'duck' => 'Sitting duck') as $k => $label) {
		ok(strpos($p, "'" . $k . "'    => '" . $label . "'") !== false
		   || strpos($p, "'" . $k . "' => '" . $label . "'") !== false,
		   'the ' . $k . ' chip is not labelled "' . $label . '"');
		ok(preg_match('/\.nemtag\.t-' . $k . '\{[^}]*background:/', $p) === 1,
		   'the ' . $k . ' chip has no colour of its own');
		ok(preg_match('/\.a-foe\.lead-' . $k . '\{/', $p) === 1,
		   'a card led by ' . $k . ' has no styling');
	}
	/* NOT BLENDED. Two chips, each keeping its colour: a blend of purple
	   and red is maroon, which means neither of them. */
	ok(strpos($p, 'class="nemtags"') !== false, 'there is nowhere for a second chip to go');
	ok(strpos($p, 'foreach ($tags as $t)') !== false, 'only one chip is ever drawn');

	/* The card follows the RECORD label over the nemesis: the record is
	   what should change what you do next. */
	/* The ORDER of the tests, not merely their presence: nesting the
	   nemesis check in front leaves this exact string intact while
	   reversing what it does. */
	$leadExpr = substr($p, strpos($p, '$lead = in_array'));
	$leadExpr = substr($leadExpr, 0, strpos($leadExpr, '?>'));
	$atT = strpos($leadExpr, "'tormentor'");
	$atD = strpos($leadExpr, "'duck'");
	$atN = strpos($leadExpr, "'nemesis'");
	ok($atT !== false && $atN !== false && $atT < $atN,
	   'the card takes the nemesis colour ahead of Tormentor, so a warning loses to a volume fact');
	ok($atD !== false && $atD < $atN,
	   'the card takes the nemesis colour ahead of Sitting duck');

	/* The record once per card, not once per chip -- both chips describe
	   the same opponent and the numbers are identical. */
	ok(strpos($p, '$lastTag = $tags[count($tags) - 1];') !== false,
	   'the record is printed on every chip, so a two-chip card says it twice');
}

echo "\nevery chip is readable\n";
{
	/* White on #e0b500 is 1.95:1, which is why the duck chip is the only
	   one with dark text. Checked rather than eyeballed. */
	$lum = function ($hex) {
		$hex = ltrim($hex, '#'); $v = array();
		foreach (array(0, 2, 4) as $i) {
			$c = hexdec(substr($hex, $i, 2)) / 255;
			$v[] = $c <= 0.03928 ? $c / 12.92 : pow(($c + 0.055) / 1.055, 2.4);
		}
		return 0.2126 * $v[0] + 0.7152 * $v[1] + 0.0722 * $v[2];
	};
	$cr = function ($a, $b) use ($lum) {
		$l1 = $lum($a); $l2 = $lum($b);
		if ($l1 < $l2) { $t = $l1; $l1 = $l2; $l2 = $t; }
		return ($l1 + 0.05) / ($l2 + 0.05);
	};
	$p = file_get_contents(__DIR__ . '/dhcarena.php');
	foreach (array('nemesis', 'tormentor', 'duck') as $k) {
		preg_match('/\.nemtag\.t-' . $k . '\{([^}]*)\}/', $p, $m);
		ok($m, 'no rule for the ' . $k . ' chip');
		if (!$m) continue;
		preg_match('/background:(#[0-9a-f]{6})/i', $m[1], $bg);
		preg_match('/color:(#[0-9a-f]{6})/i', $m[1], $fg);
		$back = $bg ? $bg[1] : '#000000';
		$fore = $fg ? $fg[1] : '#ffffff';          /* the base .nemtag colour */
		$ratio = $cr($back, $fore);
		ok($ratio >= 4.5,
		   sprintf('the %s chip is %s on %s, %.2f:1 -- under the 4.5:1 small text needs',
		           $k, $fore, $back, $ratio));
	}
}

echo "\n" . ($fail ? "$fail FAILED\n" : "all good\n");
exit($fail ? 1 : 0);
