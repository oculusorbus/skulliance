<?php
/**
 * WHY A THEME LOADS NO NFTs -- answered, instead of guessed.
 *
 * Both pipelines fail SILENTLY and in several different places: no wallet
 * connected, Koios unreachable, nothing held under the project's policy,
 * nothing synced into Skulliance's nfts table. Every one of those produces
 * the same symptom -- an empty barracks and no error anywhere -- so working
 * out which it was meant reading source and guessing. Filthy Mermaid cost
 * several rounds of exactly that.
 *
 * READ ONLY. It writes nothing, creates no soldiers and changes no session.
 * It asks the same questions dashboard.php asks and reports the count at
 * each step, so the first zero IS the answer.
 */

/**
 * Probe one project's NFT pipeline for one player.
 *
 * Returns an ordered list of array('step', 'count', 'detail'), where count
 * is null when the step could not run at all.
 */
function dropshipNftProbe($conn, $project_id, $discord_id) {
	$out = array();
	$add = function ($step, $count, $detail = '') use (&$out) {
		$out[] = array('step' => $step, 'count' => $count, 'detail' => $detail);
	};

	$policy = function_exists('dropshipProjectPolicy')
	        ? dropshipProjectPolicy($conn, $project_id) : '';
	$add('Collection on the project (projects.policy_id)',
	     $policy === '' ? 0 : 1,
	     $policy === '' ? 'Not set. Nothing can match, on either source.' : $policy);
	if ($policy === '') return $out;

	$source = function_exists('dropshipThemeTraitSource')
	        ? dropshipThemeTraitSource($conn, $project_id) : 'koios';
	$add('Trait source for this theme', 1, $source);

	if ($source === 'skulliance') {
		/* The same query dropshipSyncFromSkulliance() runs. */
		$sk = dropshipSkullianceDb();
		if (!$sk) { $add('Skulliance database', null, 'Could not connect.'); return $out; }
		$pe = $sk->real_escape_string($policy);
		$de = $sk->real_escape_string((string)$discord_id);
		$r  = $sk->query("SELECT COUNT(*) AS n FROM collections WHERE policy = '$pe'");
		$ncol = ($r && ($row = $r->fetch_assoc())) ? (int)$row['n'] : 0;
		$add('That collection exists in Skulliance', $ncol,
		     $ncol ? '' : 'No collections row with this policy, so no NFT can ever join to it.');
		$r = $sk->query("SELECT COUNT(*) AS n FROM nfts
		                 INNER JOIN collections ON collections.id = nfts.collection_id
		                 WHERE collections.policy = '$pe'");
		$nall = ($r && ($row = $r->fetch_assoc())) ? (int)$row['n'] : 0;
		$add('NFTs of that collection synced at all', $nall,
		     $nall ? '' : 'Skulliance has never synced this collection, so this source cannot work for it.');
		$r = $sk->query("SELECT COUNT(*) AS n FROM nfts
		                 INNER JOIN users ON users.id = nfts.user_id
		                 INNER JOIN collections ON collections.id = nfts.collection_id
		                 WHERE collections.policy = '$pe' AND users.discord_id = '$de'");
		$nmine = ($r && ($row = $r->fetch_assoc())) ? (int)$row['n'] : 0;
		$add('...and held by you', $nmine,
		     $nmine ? '' : 'You hold none of this collection on your Skulliance account.');
		$sk->close();
		return $out;
	}

	/* The Koios path, step for step as dashboard.php runs it. */
	$addresses = function_exists('dropshipConnectedStakeAddresses')
	           ? dropshipConnectedStakeAddresses($discord_id) : array();
	$add('Wallets connected to your Skulliance account', count($addresses),
	     $addresses ? '' : 'Koios reads your wallets, so with none connected it is skipped entirely and nothing happens.');
	if (!$addresses) return $out;

	$ch = curl_init("https://api.koios.rest/api/v1/account_utxos?select=asset_list&asset_list=not.is.null");
	curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'accept: application/json'));
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array('_stake_addresses' => array_values($addresses))));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
	curl_setopt($ch, CURLOPT_TIMEOUT, 20);
	$raw  = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$cerr = curl_error($ch);
	curl_close($ch);
	$resp = json_decode((string)$raw);
	if (!is_array($resp)) {
		$add('Koios answered', null,
		     'HTTP ' . $code . ($cerr !== '' ? ' -- ' . $cerr : '')
		     . '. Reply was not a list, so no token could be read.');
		return $out;
	}
	$add('Koios answered', 1, 'HTTP ' . $code . ', ' . count($resp) . ' utxo group(s)');

	$total = 0; $mine = 0; $policies = array();
	foreach ($resp as $list) {
		if (empty($list->asset_list)) continue;
		foreach ($list->asset_list as $token) {
			$total++;
			$p = isset($token->policy_id) ? $token->policy_id : '';
			$policies[$p] = isset($policies[$p]) ? $policies[$p] + 1 : 1;
			if ($p === $policy) $mine++;
		}
	}
	$add('Tokens in those wallets', $total,
	     $total ? '' : 'The wallets hold no native tokens at all.');
	arsort($policies);
	$top = array();
	foreach (array_slice($policies, 0, 6, true) as $p => $n) $top[] = substr($p, 0, 12) . '... x' . $n;
	$add('...under THIS collection', $mine,
	     $mine ? '' : 'You hold none of this collection in a connected wallet. Policies actually held: '
	                . ($top ? implode(', ', $top) : 'none'));
	return $out;
}
