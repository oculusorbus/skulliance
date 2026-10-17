<?php
/**
 * lib/nft-dossier.php -- everything the platform currently knows about one NFT.
 *
 * My NFTs used to send a click straight out to a block explorer, which answers
 * "does this exist on chain" and nothing about the thing the staker actually
 * owns it for. An NFT here is on missions, in gauntlets, standing in a realm,
 * part of a diamond skull -- and none of that was visible anywhere.
 *
 * ONE READ PER SURFACE, EACH TOLERANT OF ITS TABLE BEING ABSENT. These are
 * the platform's older tables and not every deployment has every game; a
 * missing table returns false from query() and is reported as "nothing here"
 * rather than taking the panel down. The same reason dhca_crew_profiles()
 * guards its reads.
 *
 * READ-ONLY, and owner-checked by the caller. Nothing here writes, and
 * nothing here decides who may look -- ajax/nft-dossier.php does that, so the
 * rule lives in one place rather than being re-derived per query.
 */

/** Rows from a query, or an empty array if the table is not there. */
function _nftd_rows($conn, $sql) {
	$res = $conn->query($sql);
	if (!$res) return array();
	$out = array();
	while ($r = $res->fetch_assoc()) $out[] = $r;
	return $out;
}

/**
 * The NFT itself, plus where it currently is.
 *
 * Returns null when there is no such NFT. Everything else is always present,
 * empty rather than missing, so the renderer can loop without checking.
 */
function nft_dossier($conn, $nft_id) {
	$id = (int)$nft_id;
	if ($id <= 0) return null;

	$base = _nftd_rows($conn,
		"SELECT n.id, n.asset_id, n.asset_name, n.name, n.ipfs, n.user_id,
		        n.collection_id, n.blockchain_id,
		        c.name AS collection_name, c.rate, c.marketplace_slug,
		        c.project_id, p.name AS project_name, p.currency,
		        u.username
		   FROM nfts n
		   INNER JOIN collections c ON c.id = n.collection_id
		   INNER JOIN projects    p ON p.id = c.project_id
		   LEFT  JOIN users       u ON u.id = n.user_id
		  WHERE n.id = $id LIMIT 1");
	if (!$base) return null;

	$d = array('nft' => $base[0], 'missions' => array(), 'missions_done' => 0,
	           'gauntlets' => array(), 'diamond' => null, 'soldier' => null,
	           'raids' => array());

	/* ---- missions. status 0 is in progress; anything else has finished. ---- */
	$d['missions'] = _nftd_rows($conn,
		"SELECT m.id, m.created_date, q.title, q.duration, q.level,
		        q.reward, q.currency, pr.name AS project_name
		   FROM missions_nfts mn
		   INNER JOIN missions m ON m.id = mn.mission_id
		   INNER JOIN quests   q ON q.id = m.quest_id
		   LEFT  JOIN projects pr ON pr.id = q.project_id
		  WHERE mn.nft_id = $id AND m.status = '0'
		  ORDER BY m.created_date DESC");

	$done = _nftd_rows($conn,
		"SELECT COUNT(*) AS n FROM missions_nfts mn
		   INNER JOIN missions m ON m.id = mn.mission_id
		  WHERE mn.nft_id = $id AND m.status <> '0'");
	$d['missions_done'] = $done ? (int)$done[0]['n'] : 0;

	/* ---- gauntlets ---- */
	$d['gauntlets'] = _nftd_rows($conn,
		"SELECT grn.run_id FROM gauntlets_nfts grn
		  WHERE grn.nft_id = $id ORDER BY grn.run_id DESC LIMIT 10");

	/*
	 * ---- a diamond skull it is part of ----
	 *
	 * diamond_skulls.diamond_skull_id IS ITSELF AN nfts.id -- the Skull this
	 * one is delegated to is another NFT, which gallery.php already relies on.
	 * So the row carries everything needed to show it properly; returning the
	 * bare id, as this did, put a raw number on the panel where a name and a
	 * picture belong. The ipfs/collection/project columns come back raw and
	 * the CALLER resolves them, the same split the main image uses: getIPFS()
	 * lives in db.php and this library does not include it.
	 */
	$ds = _nftd_rows($conn,
		"SELECT ds.diamond_skull_id, dn.name, dn.asset_name, dn.ipfs,
		        dn.collection_id, dc.project_id
		   FROM diamond_skulls ds
		   INNER JOIN nfts        dn ON dn.id = ds.diamond_skull_id
		   LEFT  JOIN collections dc ON dc.id = dn.collection_id
		  WHERE ds.nft_id = $id LIMIT 1");
	if ($ds) $d['diamond'] = $ds[0];

	/*
	 * ---- RAIDS, REACHED THROUGH THE SOLDIER ----
	 *
	 * raids_soldiers keys on soldier_id, and a soldier keys on nft_id, so an
	 * NFT's raid history is one join away. Counted over EVERY soldier row for
	 * this NFT rather than the active one the panel shows below: a soldier can
	 * be retired and replaced, and the raids it fought still happened.
	 *
	 * A WIN DEPENDS ON WHICH SIDE IT WAS ON. raids.outcome is written from the
	 * ATTACKER's point of view -- '1' means the raid succeeded, '2' that it was
	 * repelled, '0' that it is still running. So outcome '1' is a win for
	 * offense and a loss for defense, and counting outcome='1' as a win
	 * outright would credit every defender for being overrun.
	 *
	 * Aggregated in PHP rather than with SUM(CASE...): the row count per NFT is
	 * tiny, and a GROUP BY here is one more thing ONLY_FULL_GROUP_BY can reject
	 * on a server whose mode we do not control.
	 */
	$d['raids'] = array('total' => 0, 'attack' => 0, 'defend' => 0,
	                    'won' => 0, 'lost' => 0, 'fell' => 0, 'now' => 0);
	foreach (_nftd_rows($conn,
		"SELECT rs.side, rs.dead, r.outcome
		   FROM raids_soldiers rs
		   INNER JOIN soldiers s ON s.id = rs.soldier_id
		   INNER JOIN raids    r ON r.id = rs.raid_id
		  WHERE s.nft_id = $id") as $r) {
		$off = ($r['side'] === 'offense');
		$out = (string)$r['outcome'];
		$d['raids']['total']++;
		$d['raids'][$off ? 'attack' : 'defend']++;
		if (!empty($r['dead'])) $d['raids']['fell']++;
		if      ($out === '0') $d['raids']['now']++;
		else if ($out === ($off ? '1' : '2')) $d['raids']['won']++;
		else if ($out === ($off ? '2' : '1')) $d['raids']['lost']++;
	}

	/* ---- standing in a realm, which is also how it reaches raids ---- */
	$sol = _nftd_rows($conn,
		/* theme_id IS the realm's picture: images/themes/{theme_id}.jpg, the
		   same file realms.php and the raid cards draw. */
		"SELECT s.realm_id, s.location, s.trained, s.dead, s.active,
		        r.name AS realm_name, r.user_id AS realm_user, r.theme_id
		   FROM soldiers s
		   LEFT JOIN realms r ON r.id = s.realm_id
		  WHERE s.nft_id = $id
		  ORDER BY s.active DESC, s.id DESC LIMIT 1");
	if ($sol) $d['soldier'] = $sol[0];

	return $d;
}
