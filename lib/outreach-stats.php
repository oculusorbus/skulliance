<?php
/**
 * lib/outreach-stats.php — what one project's community has actually done here.
 *
 * FOR THE OUTREACH POST (admin-outreach.php), which goes into somebody
 * else's Discord with their project's name on it. That raises the bar on
 * every number: a wrong one is not a wrong number on an admin page, it is a
 * wrong claim made in public to the people best placed to know it is wrong.
 *
 * SO NOTHING HERE IS INVENTED. Every query is the shape the platform
 * already uses to count the same thing somewhere else:
 *
 *   stakers / staked   nfts.user_id is 0 when unstaked and the holder's id
 *                      when staked -- see the unstake path in db.php, which
 *                      is `UPDATE nfts SET user_id = 0`.
 *   distributed        transactions, type 'credit', written by logCredit().
 *   missions           missions JOIN quests, status '1' is a success --
 *                      getTotalMissions()'s own encoding.
 *   raids              raids JOIN realms ON raids.offense_id = realms.id,
 *                      grouped by realms.project_id, outcome '1' is a win --
 *                      checkFactionsLeaderboard()'s own join. A realm's
 *                      project IS its faction; there is no separate table.
 *
 * NULL IS NOT ZERO, AND THAT IS THE WHOLE POINT OF THE SHAPE BELOW. A read
 * that fails comes back null and the post leaves the line out; a read that
 * succeeds and finds nothing comes back 0 and the post ALSO leaves it out,
 * because "0 raids won" is an argument against turning up. The difference
 * still matters on the page itself, which says "could not read" rather than
 * quietly showing a nought -- this platform has been bitten twice by a
 * fallback that looks like a legitimate empty state.
 */

if (!function_exists('outreach_num')) {

/** One scalar, or null if the read failed. Never 0 for a failure. */
function outreach_num($conn, $sql, $col) {
	$r = @$conn->query($sql);
	if (!$r) return null;
	$row = $r->fetch_assoc();
	if (!$row || !array_key_exists($col, $row)) return null;
	/* A SUM over no rows is SQL NULL, which IS a real zero here: the read
	   worked and found nothing. Only a failed read stays null.
	   0.0 AND NOT 0, so the return is float-or-null and never three types --
	   a caller comparing with === had to know which branch produced it. */
	return $row[$col] === null ? 0.0 : (float)$row[$col];
}

/**
 * Everything the post can say about one project, in display order.
 *
 * Returns key => array(label, value, kind). `value` null means the read
 * failed. `kind` is 'count' or 'points' and only decides formatting.
 */
function outreach_stats($conn, $project_id) {
	$id = (int)$project_id;
	$out = array();

	/* HOLDERS AND NFTS IN ONE READ. Two COUNTs over the same join is one
	   scan, and the two numbers are always from the same instant -- asked
	   separately they can disagree across a stake landing between them. */
	$sql = "SELECT COUNT(DISTINCT n.user_id) AS holders, COUNT(*) AS staked
	          FROM nfts n INNER JOIN collections c ON c.id = n.collection_id
	         WHERE c.project_id = $id AND n.user_id <> 0";
	$r = @$conn->query($sql);
	$row = $r ? $r->fetch_assoc() : null;
	$out['holders'] = array('label' => 'Holders staking',
		'value' => $row ? (float)$row['holders'] : null, 'kind' => 'count');
	$out['staked']  = array('label' => 'NFTs staked',
		'value' => $row ? (float)$row['staked'] : null, 'kind' => 'count');

	$out['distributed'] = array('label' => 'Points distributed',
		'value' => outreach_num($conn,
			"SELECT SUM(amount) AS n FROM transactions
			  WHERE type = 'credit' AND project_id = $id", 'n'),
		'kind' => 'points');

	$out['missions'] = array('label' => 'Missions completed',
		'value' => outreach_num($conn,
			"SELECT SUM(m.status = '1') AS n
			   FROM missions m INNER JOIN quests q ON q.id = m.quest_id
			  WHERE q.project_id = $id", 'n'),
		'kind' => 'count');

	/* THE FACTION IS THE PROJECT. realms.project_id is what the factions
	   board groups on, and a raid belongs to the faction that LAUNCHED it
	   (offense_id), which is the number worth boasting about. */
	$out['raids'] = array('label' => 'Raids won by the faction',
		'value' => outreach_num($conn,
			"SELECT SUM(r.outcome = '1') AS n
			   FROM raids r INNER JOIN realms rl ON rl.id = r.offense_id
			  WHERE rl.project_id = $id", 'n'),
		'kind' => 'count');

	$out['realms'] = array('label' => 'Realms flying the faction',
		'value' => outreach_num($conn,
			"SELECT COUNT(*) AS n FROM realms WHERE project_id = $id", 'n'),
		'kind' => 'count');

	return $out;
}

/**
 * The lines the post should actually carry.
 *
 * A NOUGHT IS LEFT OUT, not printed. "0 raids won by the faction" is an
 * argument against turning up, and a project with no Realms never had a
 * faction to begin with -- the absence is not a result. A FAILED read is
 * left out too, for the opposite reason: it is not known to be true.
 */
function outreach_lines($stats, $currency, $only = null) {
	$on = function ($k) use ($stats, $only) {
		return (($only === null) || in_array($k, $only, true))
		    && isset($stats[$k]) && $stats[$k]['value'] !== null && $stats[$k]['value'] > 0;
	};
	$n = function ($k) use ($stats) { return number_format($stats[$k]['value']); };

	$lines = array();
	/* HOLDERS AND NFTS AS ONE SENTENCE. "128 holders staking" and "1,904
	   NFTs staked" on two bullets reads as two unrelated facts; together
	   they are the one number a project actually recognises about itself.
	   Either alone still stands if the other is zero or unreadable. */
	if ($on('holders') && $on('staked'))
		$lines[] = $n('holders') . ' holders staking ' . $n('staked') . ' NFTs';
	elseif ($on('holders')) $lines[] = $n('holders') . ' holders staking';
	elseif ($on('staked'))  $lines[] = $n('staked') . ' NFTs staked';

	if ($on('distributed')) $lines[] = $n('distributed') . ' ' . $currency . ' distributed';
	if ($on('missions'))    $lines[] = $n('missions') . ' missions completed';
	if ($on('raids'))       $lines[] = $n('raids') . ' raids won by the faction';
	if ($on('realms'))      $lines[] = $n('realms') . ' Realms flying the faction';
	return $lines;
}

}
