<?php
/*
 * missions-lib.php — the missions page's data layer and its one writer.
 *
 * WHY A LIB AND NOT MORE db.php. Everything missions needs today is spread
 * across six renderers in db.php that echo markup as they query
 * (getMissionsFilters, getMissions, getInventory, getCurrentMissions and
 * the three bulk-launch buttons). None of them is touched here. This file
 * adds data-returning siblings and one transactional writer beside them,
 * the same additive pattern getNFTsData() and getItemsData() used, so the
 * old page reverts in a line if anything about the new one is wrong.
 *
 * THE ONE BEHAVIOURAL CHANGE is mission_launch(). The old path spread a
 * mission's load-out across $_SESSION['userData']['mission'], mutated by
 * four separate ajax endpoints, and startMission() then wrote whatever it
 * found there. Two consequences, both real:
 *
 *   1. ajax/process-mission-nft.php writes whatever nft_id the CLIENT
 *      names into the session, and nothing downstream re-checks that the
 *      NFT is yours, is in this quest's project, or is not already out on
 *      another mission. ajax/process-mission-consumable.php is the same
 *      for items -- a +100% Success you do not own still gets attached.
 *   2. When the session and the page disagreed the user got a shipped
 *      error message telling them to hard-refresh the page.
 *
 * mission_launch() takes the whole load-out as arguments, re-derives every
 * fact from the database, and writes once. The client cannot name an NFT
 * it does not own because the server intersects the request against the
 * eligible set rather than trusting it.
 */

/* Included with require_once everywhere; db.php's own include_once habit is
   the precedent. No define-guard brace wrapper, which only hides a double
   include behind a parse error the day someone edits the bottom of the file. */

/* Success-rate items, by consumables.id. Mirrors the map that startMission(),
   getCurrentMissions() and skulliance.js each carry their own copy of. */
function mission_boost_map() { return array(1 => 100, 2 => 75, 3 => 50, 4 => 25); }
define('MISSION_ITEM_FAST_FORWARD', 5);
define('MISSION_ITEM_DOUBLE_REWARD', 6);

function mission_user_id() {
	return isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;
}

/* The artwork a quest uses, derived from its title the way every existing
   renderer derives it -- lowercased, spaces to hyphens, apostrophes dropped.
   Kept in ONE place because getMissions(), getInventory(), getCurrentMissions()
   and startMission() each build it inline and two of them forget the
   apostrophe, which is why a couple of missions show a broken image. */
function mission_art_slug($title) {
	return strtolower(str_replace("'", "", str_replace(" ", "-", $title)));
}

/*
 * THE PROJECT PICKER.
 *
 * The old filter was forty unlabelled currency icons with a hover tooltip,
 * which is unusable on a phone and unreadable to anyone who has not already
 * memorised which sigil is which. This returns what a picker actually needs
 * to be legible: the name, how far up the ladder you are, and whether you
 * have anything idle that could go out right now.
 *
 * Four queries total regardless of how many projects there are -- the old
 * one ran checkMissionInventory() once PER PROJECT inside its loop, which
 * is forty correlated subqueries on every page load.
 */
function mission_projects($conn) {
	$uid = mission_user_id();
	$out = array();

	$res = $conn->query(
		"SELECT DISTINCT p.id, p.name, p.currency
		 FROM quests q INNER JOIN projects p ON p.id = q.project_id
		 ORDER BY p.name ASC");
	if (!$res) return $out;

	$tops = array();
	$tr = $conn->query("SELECT project_id, MAX(level) AS top, COUNT(*) AS total FROM quests GROUP BY project_id");
	if ($tr) while ($r = $tr->fetch_assoc())
		$tops[(int)$r['project_id']] = array('top' => (int)$r['top'], 'total' => (int)$r['total']);

	/* Idle holdings per project, in ONE query rather than one per project. */
	$idle = array();
	$deployed = array();
	$balances = array();
	if ($uid > 0) {
		$ir = $conn->query(
			"SELECT c.project_id, COUNT(*) AS n, COALESCE(SUM(c.rate),0) AS rate
			 FROM nfts n INNER JOIN collections c ON c.id = n.collection_id
			 WHERE n.user_id = '$uid' AND n.id NOT IN (
			   SELECT mn.nft_id FROM missions_nfts mn
			   INNER JOIN missions m ON m.id = mn.mission_id
			   WHERE m.status = '0' AND m.user_id = '$uid')
			 GROUP BY c.project_id");
		if ($ir) while ($r = $ir->fetch_assoc())
			$idle[(int)$r['project_id']] = array('n' => (int)$r['n'], 'rate' => (float)$r['rate']);

		$dr = $conn->query(
			"SELECT q.project_id, COUNT(*) AS n FROM missions m
			 INNER JOIN quests q ON q.id = m.quest_id
			 WHERE m.status = '0' AND m.user_id = '$uid' GROUP BY q.project_id");
		if ($dr) while ($r = $dr->fetch_assoc()) $deployed[(int)$r['project_id']] = (int)$r['n'];

		$br = $conn->query("SELECT project_id, balance FROM balances WHERE user_id = '$uid'");
		if ($br) while ($r = $br->fetch_assoc()) $balances[(int)$r['project_id']] = (float)$r['balance'];
	}
	$cleared = $uid > 0 ? getMissionLevels($conn) : array();

	while ($row = $res->fetch_assoc()) {
		$pid   = (int)$row['id'];
		$top   = isset($tops[$pid]) ? $tops[$pid]['top'] : 0;
		$done  = isset($cleared[$pid]) ? (int)$cleared[$pid] : 0;
		/* Clearing a level unlocks the next, capped at the top. Same rule
		   autoMissions() uses for $has_locked -- they must not disagree or
		   the page offers a mission the launcher will refuse. */
		$open  = $top > 0 ? min($done + 1, $top) : 0;

		$out[] = array(
			'project_id'  => $pid,
			'name'        => $row['name'],
			'currency'    => $row['currency'],
			'icon'        => 'icons/' . strtolower($row['currency']) . '.png',
			'levels_open' => $open,
			'levels_top'  => $top,
			'complete'    => ($top > 0 && $open >= $top),
			'idle_nfts'   => isset($idle[$pid]) ? $idle[$pid]['n'] : 0,
			'idle_rate'   => isset($idle[$pid]) ? $idle[$pid]['rate'] : 0,
			'deployed'    => isset($deployed[$pid]) ? $deployed[$pid] : 0,
			'balance'     => isset($balances[$pid]) ? $balances[$pid] : 0,
			'eligible'    => (isset($idle[$pid]) && $idle[$pid]['n'] > 0),
		);
	}
	return $out;
}

/*
 * ONE PROJECT'S LADDER.
 *
 * Returns every quest for a project with its state worked out, including
 * the locked ones. The old renderer scrambled a locked mission's title into
 * "?????? ####" and swapped its art for a padlock, which to a newcomer
 * makes most of the grid look like noise rather than like progress. These
 * rows keep the real title and say WHY a mission is not available, so the
 * page can show a ladder you are climbing instead of a wall of gibberish.
 */
function mission_quests($conn, $project_id) {
	$uid = mission_user_id();
	$pid = (int)$project_id;
	$out = array();

	$res = $conn->query(
		"SELECT q.id, q.title, q.description, q.cost, q.reward, q.duration, q.level,
		        q.extension, q.project_id, p.name AS project_name, p.currency
		 FROM quests q INNER JOIN projects p ON p.id = q.project_id
		 WHERE p.id = '$pid' ORDER BY q.level ASC");
	if (!$res) return $out;

	$cleared = $uid > 0 ? getMissionLevels($conn) : array();
	$done    = isset($cleared[$pid]) ? (int)$cleared[$pid] : 0;

	$balance = 0; $idle = 0; $running = array();
	if ($uid > 0) {
		$br = $conn->query("SELECT balance FROM balances WHERE user_id = '$uid' AND project_id = '$pid' LIMIT 1");
		if ($br && $br->num_rows) $balance = (float)$br->fetch_assoc()['balance'];

		$ir = $conn->query(
			"SELECT COUNT(*) AS n FROM nfts n INNER JOIN collections c ON c.id = n.collection_id
			 WHERE n.user_id = '$uid' AND c.project_id = '$pid' AND n.id NOT IN (
			   SELECT mn.nft_id FROM missions_nfts mn
			   INNER JOIN missions m ON m.id = mn.mission_id
			   WHERE m.status = '0' AND m.user_id = '$uid')");
		if ($ir && $ir->num_rows) $idle = (int)$ir->fetch_assoc()['n'];

		$rr = $conn->query(
			"SELECT quest_id, COUNT(*) AS n FROM missions
			 WHERE status = '0' AND user_id = '$uid' GROUP BY quest_id");
		if ($rr) while ($r = $rr->fetch_assoc()) $running[(int)$r['quest_id']] = (int)$r['n'];
	}

	while ($row = $res->fetch_assoc()) {
		$level  = (int)$row['level'];
		$cost   = (float)$row['cost'];
		$locked = ($level > $done + 1);
		$slug   = mission_art_slug($row['title']);
		/* mp4 quests keep a .gif alongside for the still. */
		$ext    = ($row['extension'] === 'mp4') ? 'gif' : $row['extension'];

		$out[] = array(
			'quest_id'    => (int)$row['id'],
			'title'       => $row['title'],
			'description' => $row['description'],
			'project_id'  => (int)$row['project_id'],
			'project'     => $row['project_name'],
			'currency'    => $row['currency'],
			'cost'        => $cost,
			'reward'      => (float)$row['reward'],
			'duration'    => (int)$row['duration'],
			'level'       => $level,
			'image'       => 'images/missions/' . $slug . '.' . $ext,
			'video'       => ($row['extension'] === 'mp4') ? 'images/missions/' . $slug . '.mp4' : '',
			'locked'      => $locked,
			/* WHY, not just whether. Each of these is a different thing for
			   the player to do next, and the old page said only "disabled". */
			'unlock_at'   => $locked ? $level - 1 : 0,
			'affordable'  => ($cost <= 0 || $balance >= $cost),
			'shortfall'   => ($cost > $balance) ? $cost - $balance : 0,
			'has_squad'   => ($idle > 0),
			'running'     => isset($running[(int)$row['id']]) ? $running[(int)$row['id']] : 0,
			'net_per_day' => ((int)$row['duration'] > 0)
			                 ? ((float)$row['reward'] - $cost) / (int)$row['duration'] : 0,
		);
	}
	return $out;
}

/*
 * EVERYTHING THE LAUNCH DRAWER NEEDS, IN ONE CALL.
 *
 * The old flow needed a full page POST to get here -- each mission card was
 * its own <form action='missions.php#inventory'>, so choosing a mission
 * reloaded the page, re-ran verify.php, and needed a nine-second fake
 * progress bar to cover the wait. This returns the same facts as data so
 * the drawer opens without a round trip to anywhere but here.
 *
 * It also returns the numbers the page needs to do MAXIMISE and BALANCE in
 * the browser. Those were two more full page POSTs (renderInventoryButton()
 * posts back to missions.php), for an operation that is "walk the list in
 * rate order and stop at a threshold" -- arithmetic the client already has
 * every input for.
 */
function mission_loadout($conn, $quest_id) {
	$uid = mission_user_id();
	$qid = (int)$quest_id;
	if ($uid <= 0 || $qid <= 0) return null;

	$qr = $conn->query(
		"SELECT q.id, q.title, q.description, q.cost, q.reward, q.duration, q.level,
		        q.extension, q.project_id, p.name AS project_name, p.currency
		 FROM quests q INNER JOIN projects p ON p.id = q.project_id
		 WHERE q.id = '$qid' LIMIT 1");
	if (!$qr || !$qr->num_rows) return null;
	$q   = $qr->fetch_assoc();
	$pid = (int)$q['project_id'];

	/* Locked is decided here, not by the page. The drawer can be opened by
	   anything that knows a quest id. */
	$cleared = getMissionLevels($conn);
	$done    = isset($cleared[$pid]) ? (int)$cleared[$pid] : 0;
	$locked  = ((int)$q['level'] > $done + 1);

	$balance = 0;
	$br = $conn->query("SELECT balance FROM balances WHERE user_id = '$uid' AND project_id = '$pid' LIMIT 1");
	if ($br && $br->num_rows) $balance = (float)$br->fetch_assoc()['balance'];

	/* The idle roster for this project, in the order the old inventory used
	   so the default selection is the same one stakers are used to. */
	$squad = array();
	$sr = $conn->query(
		"SELECT n.id, n.asset_id, n.asset_name, n.ipfs, c.rate, n.collection_id
		 FROM nfts n
		 INNER JOIN collections c ON c.id = n.collection_id
		 WHERE c.project_id = '$pid' AND n.user_id = '$uid' AND n.id NOT IN (
		   SELECT mn.nft_id FROM missions_nfts mn
		   INNER JOIN missions m ON m.id = mn.mission_id
		   WHERE m.status = '0' AND m.user_id = '$uid')
		 ORDER BY n.collection_id ASC, c.rate DESC");
	$idle_rate = 0;
	if ($sr) while ($r = $sr->fetch_assoc()) {
		$idle_rate += (float)$r['rate'];
		$squad[] = array(
			'nft_id' => (int)$r['id'],
			'name'   => $r['asset_name'],
			'rate'   => (float)$r['rate'],
			'image'  => getIPFS($r['ipfs'], $r['collection_id'], $pid),
		);
	}

	/* Rate already committed to this project's running missions.
	   THE OLD QUERY WAS WRONG HERE and had been for a long time:
	     SELECT SUM(rate), nft_id AS total_mission_rates
	   aliases nft_id, not the sum, so $total_mission_rates was an arbitrary
	   NFT's primary key added to the whale-balancing total. A key of 40,000
	   made every threshold collapse to 100. */
	$out_rate = 0;
	$or = $conn->query(
		"SELECT COALESCE(SUM(c.rate),0) AS deployed_rate
		 FROM missions_nfts mn
		 INNER JOIN missions m ON m.id = mn.mission_id
		 INNER JOIN quests q2 ON q2.id = m.quest_id
		 INNER JOIN nfts n ON n.id = mn.nft_id
		 INNER JOIN collections c ON c.id = n.collection_id
		 WHERE m.status = '0' AND m.user_id = '$uid' AND q2.project_id = '$pid'");
	if ($or && $or->num_rows) $out_rate = (float)$or->fetch_assoc()['deployed_rate'];

	/* The whale-balancing threshold, same ladder as getInventory(): if the
	   roster can cover two or three simultaneous runs, default to a share of
	   it rather than dumping everything into one mission. */
	$total_rate = $idle_rate + $out_rate;
	$threshold  = 100;
	if ($total_rate > 100) {
		$double = ceil($total_rate / 2);
		$triple = ceil($total_rate / 3);
		if      ($double < 100) $threshold = $double;
		else if ($triple < 100) $threshold = $triple;
	}

	$items = array();
	$have  = getCurrentAmounts($conn);
	if (is_array($have)) foreach ($have as $id => $c) {
		if ((int)$c['amount'] <= 0) continue;
		$boost = mission_boost_map();
		$items[] = array(
			'consumable_id' => (int)$id,
			'name'          => $c['name'],
			'amount'        => (int)$c['amount'],
			'boost'         => isset($boost[(int)$id]) ? $boost[(int)$id] : 0,
			'icon'          => 'icons/' . strtolower(str_replace('%', '', str_replace(' ', '-', $c['name']))) . '.png',
		);
	}

	$slug = mission_art_slug($q['title']);
	return array(
		'quest_id'    => (int)$q['id'],
		'title'       => $q['title'],
		'description' => $q['description'],
		'project_id'  => $pid,
		'project'     => $q['project_name'],
		'currency'    => $q['currency'],
		'cost'        => (float)$q['cost'],
		'reward'      => (float)$q['reward'],
		'duration'    => (int)$q['duration'],
		'level'       => (int)$q['level'],
		'locked'      => $locked,
		'image'       => 'images/missions/' . $slug . '.' . (($q['extension'] === 'mp4') ? 'gif' : $q['extension']),
		'video'       => ($q['extension'] === 'mp4') ? 'images/missions/' . $slug . '.mp4' : '',
		'balance'     => $balance,
		'affordable'  => ((float)$q['cost'] <= 0 || $balance >= (float)$q['cost']),
		'squad'       => $squad,
		'items'       => $items,
		'idle_rate'   => $idle_rate,
		'out_rate'    => $out_rate,
		'threshold'   => $threshold,
		'net_per_day' => ((int)$q['duration'] > 0)
		                 ? ((float)$q['reward'] - (float)$q['cost']) / (int)$q['duration'] : 0,
	);
}

/*
 * WHAT IS IN THE FIELD.
 *
 * Same facts getCurrentMissions() renders, returned instead of echoed, plus
 * the two the old card made you work out: whether it is claimable NOW, and
 * what the reward will actually be after a Double Rewards item.
 */
function mission_active($conn) {
	$uid = mission_user_id();
	$out = array();
	if ($uid <= 0) return $out;

	$res = $conn->query(
		"SELECT m.id AS mission_id, m.quest_id, m.created_date, m.status,
		        q.title, q.cost, q.reward, q.duration, q.extension, q.level,
		        p.name AS project_name, p.currency,
		        COUNT(mn.nft_id) AS total_nfts, COALESCE(SUM(c.rate),0) AS squad_rate
		 FROM missions m
		 INNER JOIN quests q ON m.quest_id = q.id
		 INNER JOIN projects p ON p.id = q.project_id
		 LEFT JOIN missions_nfts mn ON m.id = mn.mission_id
		 LEFT JOIN nfts n ON n.id = mn.nft_id
		 LEFT JOIN collections c ON c.id = n.collection_id
		 WHERE m.status = '0' AND m.user_id = '$uid'
		 GROUP BY m.id ORDER BY m.created_date ASC");
	if (!$res) return $out;

	$boost_map = mission_boost_map();
	while ($row = $res->fetch_assoc()) {
		$mid   = (int)$row['mission_id'];
		$items = getMissionConsumables($conn, $mid);
		if (!is_array($items)) $items = array();

		$boost = 0; $reward = (float)$row['reward']; $fast = false;
		foreach ($items as $cid => $name) {
			$cid = (int)$cid;
			if (isset($boost_map[$cid]))                $boost += $boost_map[$cid];
			else if ($cid === MISSION_ITEM_DOUBLE_REWARD) $reward *= 2;
			else if ($cid === MISSION_ITEM_FAST_FORWARD)  $fast = true;
		}

		$started = strtotime($row['created_date']);
		if ($fast) $started = strtotime('-' . ceil((int)$row['duration'] / 2) . ' day', $started);
		$due       = strtotime('+' . (int)$row['duration'] . ' day', $started);
		$remaining = $due - time();
		$ready     = ($remaining <= 0);

		$elapsed = time() - $started;
		$span    = max(1, $due - $started);
		$pct     = $ready ? 100 : max(0, min(100, ($elapsed / $span) * 100));

		$icons = array();
		foreach ($items as $cid => $name)
			$icons[] = array('name' => $name,
				'icon' => 'icons/' . strtolower(str_replace('%', '', str_replace(' ', '-', $name))) . '.png');

		$out[] = array(
			'mission_id' => $mid,
			'quest_id'   => (int)$row['quest_id'],
			'title'      => $row['title'],
			'project'    => $row['project_name'],
			'currency'   => $row['currency'],
			'level'      => (int)$row['level'],
			'cost'       => (float)$row['cost'],
			'reward'     => $reward,
			'doubled'    => ($reward > (float)$row['reward']),
			'nfts'       => (int)$row['total_nfts'],
			'success'    => min(100, (float)$row['squad_rate'] + $boost),
			'duration'   => (int)$row['duration'],
			'fast'       => $fast,
			'due'        => $due,
			'ready'      => $ready,
			'percent'    => $pct,
			'items'      => $icons,
			'image'      => 'images/missions/' . mission_art_slug($row['title']) . '.png',
		);
	}
	/* Closest to done first: what you came to claim should be at the top. */
	usort($out, function($a, $b) { return $a['due'] - $b['due']; });
	return $out;
}

/*
 * THE HEADLINE NUMBERS. Cheap enough to run on every page load -- the
 * detailed month/all-time breakdown stays in getTotalMissions(), which the
 * page still loads lazily.
 */
function mission_overview($conn) {
	$uid = mission_user_id();
	$o = array('active' => 0, 'ready' => 0, 'success' => 0, 'total' => 0,
	           'levels_open' => 0, 'levels_top' => 0, 'ever' => false);
	if ($uid <= 0) return $o;

	$r = $conn->query(
		"SELECT SUM(status = '0') AS active, SUM(status = '1') AS success, COUNT(*) AS total
		 FROM missions WHERE user_id = '$uid'");
	if ($r && $r->num_rows) {
		$x = $r->fetch_assoc();
		$o['active']  = (int)$x['active'];
		$o['success'] = (int)$x['success'];
		$o['total']   = (int)$x['total'];
		$o['ever']    = ((int)$x['total'] > 0);
	}

	$t = $conn->query("SELECT project_id, MAX(level) AS top FROM quests GROUP BY project_id");
	$cleared = getMissionLevels($conn);
	if ($t) while ($x = $t->fetch_assoc()) {
		$pid = (int)$x['project_id']; $top = (int)$x['top'];
		if ($top <= 0) continue;
		$done = isset($cleared[$pid]) ? (int)$cleared[$pid] : 0;
		$o['levels_top']  += $top;
		$o['levels_open'] += min($done + 1, $top);
	}
	return $o;
}

/*
 * ================= THE WRITER =================
 *
 * ONE CALL, EVERYTHING RE-DERIVED, WRITTEN ONCE.
 *
 * The request says which quest, which NFTs and which items. None of it is
 * believed. The eligible squad and the owned item counts are read here and
 * the request is INTERSECTED against them, so an NFT that is not yours, is
 * in another project, or is already deployed simply is not in the set that
 * gets written -- which is the hole ajax/process-mission-nft.php left open,
 * since it wrote any nft_id the browser named straight into the session and
 * startMission() inserted it unchecked.
 *
 * Returns array('ok' => bool, 'message' => string, ...). Never echoes.
 */
function mission_launch($conn, $quest_id, $nft_ids, $item_ids) {
	$uid = mission_user_id();
	$qid = (int)$quest_id;
	if ($uid <= 0) return array('ok' => false, 'message' => 'Not signed in.');
	if ($qid <= 0) return array('ok' => false, 'message' => 'No mission chosen.');

	$qr = $conn->query(
		"SELECT q.id, q.title, q.cost, q.reward, q.duration, q.extension, q.level,
		        q.project_id, p.currency
		 FROM quests q INNER JOIN projects p ON p.id = q.project_id
		 WHERE q.id = '$qid' LIMIT 1");
	if (!$qr || !$qr->num_rows) return array('ok' => false, 'message' => 'That mission does not exist.');
	$q    = $qr->fetch_assoc();
	$pid  = (int)$q['project_id'];
	$cost = (float)$q['cost'];

	/* Locked is re-derived. The ladder is the game; a crafted request must
	   not be able to skip it. */
	$cleared = getMissionLevels($conn);
	$done    = isset($cleared[$pid]) ? (int)$cleared[$pid] : 0;
	if ((int)$q['level'] > $done + 1)
		return array('ok' => false, 'message' => 'That mission is still locked.');

	$balance = 0;
	$br = $conn->query("SELECT balance FROM balances WHERE user_id = '$uid' AND project_id = '$pid' LIMIT 1");
	if ($br && $br->num_rows) $balance = (float)$br->fetch_assoc()['balance'];
	if ($cost > 0 && $balance < $cost)
		return array('ok' => false, 'message' => 'You need ' . number_format($cost - $balance)
			. ' more ' . $q['currency'] . ' to start this mission.');

	/* ---- the squad: intersect the request with what is actually eligible --- */
	$eligible = array();
	$sr = $conn->query(
		"SELECT n.id, c.rate FROM nfts n
		 INNER JOIN collections c ON c.id = n.collection_id
		 WHERE c.project_id = '$pid' AND n.user_id = '$uid' AND n.id NOT IN (
		   SELECT mn.nft_id FROM missions_nfts mn
		   INNER JOIN missions m ON m.id = mn.mission_id
		   WHERE m.status = '0' AND m.user_id = '$uid')");
	if ($sr) while ($r = $sr->fetch_assoc()) $eligible[(int)$r['id']] = (float)$r['rate'];

	$squad = array(); $squad_rate = 0; $dropped = 0;
	foreach ((array)$nft_ids as $n) {
		$n = (int)$n;
		if ($n <= 0)          continue;
		if (isset($squad[$n])) continue;   /* a repeat is not a rejection */
		if (!isset($eligible[$n])) { $dropped++; continue; }
		$squad[$n] = $eligible[$n];
		$squad_rate += $eligible[$n];
	}

	/* ---- the items: same treatment against what you actually hold ------- */
	$owned = array();
	$have  = getCurrentAmounts($conn);
	if (is_array($have)) foreach ($have as $id => $c) if ((int)$c['amount'] > 0) $owned[(int)$id] = (int)$c['amount'];

	$boost_map = mission_boost_map();
	$items = array(); $boost = 0;
	foreach ((array)$item_ids as $i) {
		$i = (int)$i;
		if (!isset($owned[$i]) || isset($items[$i])) continue;
		$items[$i] = $i;
		if (isset($boost_map[$i])) $boost += $boost_map[$i];
	}

	/* The old rule, kept: a mission with no squad AND no success item is a
	   mission with a zero chance, which is a mistake rather than a gamble. */
	if (!$squad && $boost <= 0)
		return array('ok' => false, 'message' => $dropped
			? 'Those NFTs are no longer available -- they may already be on a mission. Reload and try again.'
			: 'Choose at least one NFT, or a success item, before launching.');

	/* ---- write ---------------------------------------------------------- */
	$tx = false;
	if (method_exists($conn, 'begin_transaction')) { $tx = @$conn->begin_transaction(); }

	if ($conn->query("INSERT INTO missions (quest_id, user_id) VALUES ('$qid', '$uid')") !== TRUE) {
		if ($tx) @$conn->rollback();
		return array('ok' => false, 'message' => 'Could not start the mission.');
	}
	/* insert_id, not MAX(id): the old code re-queried for the highest id for
	   this user and quest, which is the same row only as long as nothing else
	   inserts between the two statements. */
	$mission_id = (int)$conn->insert_id;
	if ($mission_id <= 0) {
		if ($tx) @$conn->rollback();
		return array('ok' => false, 'message' => 'Could not start the mission.');
	}

	if ($cost > 0) {
		updateBalance($conn, $uid, $pid, -$cost);
		logDebit($conn, $uid, 0, $cost, $pid, 0, $mission_id);
	}
	foreach ($squad as $nft_id => $rate)
		$conn->query("INSERT INTO missions_nfts (mission_id, nft_id) VALUES ('$mission_id', '" . (int)$nft_id . "')");
	foreach ($items as $cid) {
		if ($conn->query("INSERT INTO missions_consumables (mission_id, consumable_id) VALUES ('$mission_id', '" . (int)$cid . "')") === TRUE)
			updateAmount($conn, $uid, $cid, -1);
	}
	if ($tx) @$conn->commit();

	$success = min(100, $squad_rate + $boost);
	mission_announce($conn, $q, $mission_id, count($squad), $success, $boost, array_keys($items));

	return array('ok' => true, 'mission_id' => $mission_id, 'success' => $success,
		'nfts' => count($squad), 'dropped' => $dropped,
		'message' => $q['title'] . ' launched at ' . (int)$success . '% success.');
}

/* The Discord embed, lifted out of startMission() unchanged in substance so
   the channel reads the same whichever path launched the mission. */
function mission_announce($conn, $q, $mission_id, $nft_count, $success, $boost, $item_ids) {
	$name    = !empty($_SESSION['userData']['username']) ? $_SESSION['userData']['username']
	         : (!empty($_SESSION['userData']['name']) ? $_SESSION['userData']['name'] : 'Unknown');
	$discord = isset($_SESSION['userData']['discord_id']) ? $_SESSION['userData']['discord_id'] : '';
	$avatar  = isset($_SESSION['userData']['avatar']) ? $_SESSION['userData']['avatar'] : '';
	$avurl   = ($discord && $avatar) ? "https://cdn.discordapp.com/avatars/$discord/$avatar.png" : '';
	$profile = "https://skulliance.io/staking/profile.php?username=" . urlencode($name);
	$img     = "https://skulliance.io/staking/images/missions/" . mission_art_slug($q['title']) . "." . $q['extension'];

	$extras = array();
	$boost_map = mission_boost_map();
	foreach ($item_ids as $cid) {
		if (isset($boost_map[(int)$cid])) continue;
		$e = $conn->query("SELECT name FROM consumables WHERE id='" . (int)$cid . "'");
		if ($e && $e->num_rows) $extras[] = $e->fetch_assoc()['name'];
	}
	if ($boost > 0) array_unshift($extras, "+" . (int)$boost . "% Success");

	$mention = $discord ? "<@$discord>" : $name;
	$desc  = $mention . " has embarked on a mission!\n\n";
	$desc .= "📜 **Quest:** " . $q['title'] . "\n";
	$desc .= "💰 **Cost:** " . number_format($q['cost']) . " " . $q['currency']
	       . " → **Reward:** " . number_format($q['reward']) . " " . $q['currency'] . "\n";
	$desc .= "⏱️ **Duration:** " . $q['duration'] . ((int)$q['duration'] === 1 ? " day" : " days") . "\n";
	$desc .= "🎯 **Success Rate:** " . (int)$success . "%\n";
	$desc .= "🦴 **NFTs Deployed:** " . (int)$nft_count;
	if ($extras) $desc .= "\n🎒 **Items:** " . implode(", ", $extras);

	discordmsg("⚔️ Mission Embarked", $desc, $img, "https://skulliance.io/staking/missions.php",
		"missions", $avurl, "FF6B35", array("name" => $name, "icon_url" => $avurl, "url" => $profile));
}
