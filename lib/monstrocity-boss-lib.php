<?php
/**
 * Which collections a Boss Battles boss accepts fighters from.
 *
 * WHY THIS EXISTS: `bosses.collection_id` is a single column, so a boss drew
 * its roster from exactly one collection. That was fine while every project
 * had one collection on one chain. Projects now have collections on Cardano,
 * Solana, XRPL and Polygon, and a holder on the wrong chain simply could not
 * fight their own project's boss -- `canFight` tested one policy and the
 * roster fetch posted one policy.
 *
 * MANY COLLECTIONS, SO A JOIN TABLE, NOT A COLUMN -- the same shape and the
 * same reasoning as `monstrocity_theme_collections`, which solved this for
 * themes. One boss keeps ONE shared health pool and ONE bounty, which is the
 * point of a boss; the alternative was a duplicate boss per chain, each with
 * its own pool, the same art, and a separate leaderboard row.
 *
 * `bosses.collection_id` STAYS and still works. A boss with nothing attached
 * behaves exactly as it always did, so this is additive: no existing boss
 * changes until somebody ticks a box.
 */

/**
 * Does the link table exist?
 *
 * ASKED OUT LOUD for the same reason monstrocity_theme_links_table() asks:
 * NOTHING IN THE CODEBASE CREATES IT. It is a manual step out of
 * monstrocity-schema.md, and mysqli does not throw for a missing table -- the
 * query just returns false. Without this check the feature degrades to
 * silence, the admin's ticks vanish, and the form still says "Saved."
 */
function monstrocity_boss_links_table($conn) {
	static $have = null;
	if ($have === null) {
		$r = $conn->query("SHOW TABLES LIKE 'boss_collections'");
		$have = (bool)($r && $r->num_rows);
	}
	return $have;
}

/**
 * array(boss_id => array('policy', ...)); bosses with nothing attached are
 * simply absent, and the caller falls back to their own collection_id.
 *
 * Two plain reads rather than GROUP_CONCAT: that truncates at
 * group_concat_max_len without saying so, which here would mean a holder
 * quietly unable to field their NFTs. Same call made in
 * monstrocity_theme_policies().
 */
function monstrocity_boss_policies($conn) {
	$out = array();
	if (!monstrocity_boss_links_table($conn)) return $out;
	$res = $conn->query(
		/* IS NOT NULL as well as <> '': a NULL policy makes `c.policy <> ''`
		   evaluate to NULL, which is not TRUE, so the row drops out anyway --
		   but silently. Both stated. A collection with no policy id can never
		   match an NFT, so it must not count as an attachment. */
		'SELECT bc.boss_id, c.policy
		   FROM boss_collections bc
		   JOIN collections c ON c.id = bc.collection_id
		  WHERE c.policy IS NOT NULL AND c.policy <> \'\'
		  ORDER BY bc.boss_id ASC, bc.sort_order ASC, c.id ASC');
	if (!$res) return $out;
	while ($r = $res->fetch_assoc()) {
		$id = (int)$r['boss_id'];
		if (!isset($out[$id])) $out[$id] = array();
		if (!in_array($r['policy'], $out[$id], true)) $out[$id][] = $r['policy'];
	}
	return $out;
}

/**
 * Every policy one boss accepts, best-known first.
 *
 * THE BOSS'S OWN collection_id IS ALWAYS INCLUDED, whatever is attached. A
 * boss cannot be made unfightable by its own holders through a half-finished
 * edit in the admin, and an existing boss with no links keeps exactly the one
 * policy it has always had.
 */
function monstrocity_boss_policy_list($own_policy, $linked) {
	$out = array();
	$own_policy = trim((string)$own_policy);
	if ($own_policy !== '') $out[] = $own_policy;
	foreach ((array)$linked as $p) {
		$p = trim((string)$p);
		if ($p !== '' && !in_array($p, $out, true)) $out[] = $p;
	}
	return $out;
}

/**
 * Collection ids ticked for a boss, in order.
 */
function monstrocity_boss_links($conn, $boss_id) {
	$out = array();
	if (!monstrocity_boss_links_table($conn)) return $out;
	$r = $conn->query('SELECT collection_id FROM boss_collections
	                    WHERE boss_id = ' . (int)$boss_id . '
	                    ORDER BY sort_order ASC, collection_id ASC');
	if ($r) while ($x = $r->fetch_assoc()) $out[] = (int)$x['collection_id'];
	return $out;
}
