<?php
/**
 * dhc-ranks.php -- where a Fighter would place, on the four axes the
 * Collection already ranks by.
 *
 * WHY IT EXISTS. The assembler lets you try a piece and see the picture
 * change, but nothing told you what the change DID until you saved -- and
 * saving spends the traits. This answers the question while the answer can
 * still change your mind: put a trait on and see the four ranks move.
 *
 * ONE DEFINITION OF EACH AXIS, which is the whole reason this is a file and
 * not four lines in the assembler. dhcgallery.php's sort buttons named these
 * first, so its meanings are the canonical ones and it now reads them from
 * here:
 *
 *   Rarest         rarity_score -- dhcf_score() plus the originality bonus,
 *                  the same number stored on the row and ranked by the
 *                  Fighters leaderboard.
 *   Deadliest      health x power. Not a stat the engine carries: it is the
 *                  Collection's own shorthand for "what this thing is worth
 *                  bringing", because a wall that cannot hit and a glass
 *                  cannon are both easy to beat.
 *   Toughest       health, which the torso sets.
 *   Hardest hitting  power, which the weapon sets.
 *
 * Health and power come out of dhca_build_fighter() -- the Arena's own
 * builder, pure and cheap -- and NOT from a copy of its arithmetic. A second
 * opinion about what a torso is worth is the same class of bug as a second
 * opinion about draw order, and that one has already cost this codebase twice.
 *
 * THE POOL IS EVERY SAVED FIGHTER, and it has to be built by reading them all:
 * health and power are derived from traits at read time, not stored, so there
 * is no ORDER BY that can answer "how many are tougher than this". That is the
 * same O(n) pass dhcgallery.php already makes on every public page view, so it
 * is affordable -- but the assembler asks per keystroke rather than per page
 * view, so the pool is cached on disk and rebuilt only when the collection
 * actually changes.
 */

require_once __DIR__ . '/dhcfighters-lib.php';
require_once __DIR__ . '/dhcarena-engine.php';

/* dhcf_rank_axes() lives in dhcfighters-config.php, NOT here. The assembler
   needs the four labels to render the strip and already requires config; it
   must not have to pull in this file, which drags in the library and the
   Arena engine with it -- a caller that stubs dhcf_rarity() (the assembler's
   own harness does) then gets a fatal redeclare. Config is pure defines. */

/**
 * The four numbers for one trait set.
 *
 * $rarity_score is the stored score when there is one. For a build that has
 * never been saved there is not, so it is computed the way the save would --
 * base points plus the originality bonus, which now applies to every Fighter.
 *
 * @return array('rarest','might','tough','power') plus 'crit','resist','assist','charge','kit'
 */
function dhcf_rank_values($traits, $rarity_score = null, $built = null) {
	/* $built lets a caller that already has one -- dhcgallery.php builds every
	   row for its kit and role readout -- avoid a second pass over the same
	   traits. It must be dhca_build_fighter()'s output and nothing else. */
	if (!is_array($built)) $built = dhca_build_fighter($traits, '', 'r', dhcf_rarity());
	$hp    = (int)$built['maxHp'];
	$pow   = (int)$built['power'];
	if ($rarity_score === null) {
		$base = dhcf_score($traits);
		$rarity_score = $base + (int)round($base * DHCF_ORIGINALITY_BONUS);
	}
	return array(
		'rarest' => (int)$rarity_score,
		'might'  => $hp * $pow,
		'tough'  => $hp,
		'power'  => $pow,
		'crit'   => (float)$built['critC'],
		'resist' => (float)$built['resist'],
		'assist' => (float)$built['assist'],
		'charge' => (float)$built['charge'],
		'kit'    => $built['kit'],
	);
}

/**
 * Every saved Fighter's four numbers, each axis sorted DESCENDING.
 *
 * Cached as JSON under dhcrenders/ (already writable, already gitignored)
 * against a stamp of COUNT(*) and MAX(updated_at): a Fighter saved, edited or
 * disassembled moves one of those, and nothing else can change the pool. An
 * unwritable cache directory is not an error -- it just means rebuilding every
 * time, which is what the Collection does anyway.
 *
 * @return array axis => sorted int[] (descending), plus '_n' => count
 */
function dhcf_rank_pool($conn, $use_cache = true) {
	$stamp = '0-0';
	$r = $conn->query("SELECT COUNT(*) AS n, COALESCE(MAX(updated_at), '0') AS m
	                   FROM dhc_fighters WHERE disassembled_at IS NULL");
	if ($r && $row = $r->fetch_assoc()) $stamp = $row['n'] . '-' . $row['m'];

	$file = __DIR__ . '/dhcrenders/rankpool-' . md5($stamp) . '.json';
	if ($use_cache && is_file($file)) {
		$hit = json_decode((string)file_get_contents($file), true);
		if (is_array($hit) && isset($hit['_n'])) return $hit;
	}

	$pool = array('rarest' => array(), 'might' => array(),
	              'tough'  => array(), 'power' => array(), '_n' => 0);
	$res = $conn->query("SELECT traits, rarity_score FROM dhc_fighters
	                     WHERE disassembled_at IS NULL");
	if ($res) {
		while ($row = $res->fetch_assoc()) {
			$t = json_decode($row['traits'], true);
			if (!is_array($t) || !$t) continue;
			/* The STORED score, not a recomputed one. A Fighter saved under
			   older rules keeps the number it is actually ranked by, and this
			   has to agree with the leaderboard or the preview lies. */
			$v = dhcf_rank_values($t, (int)$row['rarity_score']);
			foreach (array('rarest', 'might', 'tough', 'power') as $k) $pool[$k][] = $v[$k];
			$pool['_n']++;
		}
	}
	foreach (array('rarest', 'might', 'tough', 'power') as $k) rsort($pool[$k]);

	if ($use_cache && is_dir(dirname($file)) && is_writable(dirname($file))) {
		/* Temp then rename: two players can be assembling at the same moment
		   and a half-written cache read back as JSON is a silent empty pool. */
		$tmp = $file . '.' . getmypid() . '.tmp';
		if (@file_put_contents($tmp, json_encode($pool)) !== false) {
			if (!@rename($tmp, $file)) @unlink($tmp);
		}
		/* Sweep the pools this one replaced. Left alone they accumulate one
		   file per save, forever. */
		foreach ((array)glob(__DIR__ . '/dhcrenders/rankpool-*.json') as $old) {
			if ($old !== $file && @filemtime($old) < time() - 3600) @unlink($old);
		}
	}
	return $pool;
}

/**
 * Where $value would place in a descending list -- 1 is the top.
 *
 * TIES SHARE A PLACE, and the place is the best one: three Fighters on 900
 * are all 1st and the next is 4th. Competition ranking, because "you would be
 * 3rd" when two others have exactly your number would read as a loss.
 *
 * Binary search, so this stays cheap however big the collection gets.
 */
function dhcf_rank_of($sorted, $value) {
	$lo = 0; $hi = count($sorted);
	while ($lo < $hi) {
		$mid = ($lo + $hi) >> 1;
		if ($sorted[$mid] > $value) $lo = $mid + 1; else $hi = $mid;
	}
	return $lo + 1;
}

/**
 * The whole answer for one build: value, rank and pool size per axis.
 *
 * $exclude_score lets an EDIT rank against the collection without its own
 * saved row -- otherwise the Fighter competes with itself and the preview
 * says "2nd" for a build that is about to be 1st.
 */
function dhcf_rank_build($conn, $traits, $exclude_score = null) {
	$v    = dhcf_rank_values($traits);
	$pool = dhcf_rank_pool($conn);
	$out  = array('n' => (int)$pool['_n'], 'axes' => array());

	$drop = null;
	if ($exclude_score !== null && is_array($exclude_score)) $drop = $exclude_score;

	foreach (dhcf_rank_axes() as $k => $label) {
		$list = $pool[$k];
		if ($drop !== null && isset($drop[$k])) {
			/* Remove exactly one copy of the Fighter being edited. */
			$at = array_search($drop[$k], $list, true);
			if ($at !== false) array_splice($list, $at, 1);
		}
		$out['axes'][$k] = array(
			'label' => $label,
			'value' => $v[$k],
			'rank'  => dhcf_rank_of($list, $v[$k]),
			'of'    => count($list) + 1,     // this build would be in the pool too
		);
	}
	return $out;
}
