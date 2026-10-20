<?php
/**
 * WHETHER A THEME IS ACTUALLY PLAYABLE -- answered, instead of guessed.
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

	require_once __DIR__ . '/../lib/koios.php';
	$left = koios_token_expires_in();
	$add('Koios token', $left !== null && $left > 0 ? 1 : 0,
	     $left === null ? 'Unreadable, so it is not sent.'
	       : ($left > 0 ? 'valid for ' . round($left / 86400) . ' more days'
	                    : 'EXPIRED ' . round(-$left / 86400) . ' days ago. Koios answers 403 and the reply is '
	                      . 'not JSON, so every lookup silently does nothing.'));

	$policy = function_exists('dropshipProjectPolicy')
	        ? dropshipProjectPolicy($conn, $project_id) : '';
	$add('Collection on the project (projects.policy_id)',
	     $policy === '' ? 0 : 1,
	     $policy === '' ? 'Not set. Nothing can match, on either source.' : $policy);
	if ($policy === '') return $out;

	$source = function_exists('dropshipThemeTraitSource')
	        ? dropshipThemeTraitSource($conn, $project_id) : 'koios';
	$add('Trait source this theme is set to', 1, $source . ' (both are checked below)');

	/*
	 * BOTH PATHS, ALWAYS, whichever one the theme is set to. The question
	 * being asked is "why does neither work", and reporting only the
	 * configured one answers half of it and invites a second round of
	 * switching the setting back and forth to see.
	 */
	$sk = function_exists('dropshipSkullianceDb') ? dropshipSkullianceDb() : null;
	if (!$sk) {
		$add('SKULLIANCE source: database', null, 'Could not connect.');
	} else {
		$pe = $sk->real_escape_string($policy);
		$de = $sk->real_escape_string((string)$discord_id);
		$one = function ($sql) use ($sk) {
			$r = $sk->query($sql);
			return ($r && ($row = $r->fetch_assoc())) ? (int)$row['n'] : 0;
		};
		$ncol = $one("SELECT COUNT(*) AS n FROM collections WHERE policy = '$pe'");
		$add('SKULLIANCE: collection with this policy', $ncol,
		     $ncol ? '' : 'Skulliance has no collections row for this policy, so no NFT can join to it and this source can never work for this theme.');
		if ($ncol) {
			$nall = $one("SELECT COUNT(*) AS n FROM nfts
			              INNER JOIN collections ON collections.id = nfts.collection_id
			              WHERE collections.policy = '$pe'");
			$add('SKULLIANCE: NFTs of it synced at all', $nall,
			     $nall ? '' : 'The collection is known but nothing has ever been synced into nfts for it.');
			$nmine = $one("SELECT COUNT(*) AS n FROM nfts
			               INNER JOIN users ON users.id = nfts.user_id
			               INNER JOIN collections ON collections.id = nfts.collection_id
			               WHERE collections.policy = '$pe' AND users.discord_id = '$de'");
			$add('SKULLIANCE: ...and held by you', $nmine,
			     $nmine ? '' : 'You hold none of this collection on your Skulliance account.');
		}
		$sk->close();
	}

	/*
	 * AN ACTIVE GAME, because without one the theme cannot be played at
	 * all and nothing else on this list matters. Only automate.php creates
	 * them, and it is weekly -- a theme switched on mid-week has none until
	 * somebody runs `automate.php automate=1 project_id=N`.
	 *
	 * Asked directly rather than through checkGame(), which writes to the
	 * session; this page is a read-only check and must not change which
	 * game the admin is considered to be in.
	 */
	$g = @$conn->query("SELECT COUNT(*) AS n FROM games WHERE active = 1 AND project_id = "
	                   . (int)$project_id);
	$ngame = ($g && ($row = $g->fetch_assoc())) ? (int)$row['n'] : 0;
	$add('An active game to play', $ngame,
	     $ngame ? '' : 'Nothing to play. Run: php automate.php automate=1 project_id=' . (int)$project_id);

	/*
	 * A DISCORD CHANNEL, so results and trait drops are announced
	 * somewhere. A missing webhook is filtered out rather than erroring,
	 * so this fails as silence -- the game plays, and nothing is ever
	 * posted. Never prints the URL, only whether there is one.
	 */
	if (function_exists('getWebhook')) {
		$hook = (string)@getWebhook($project_id);
		$add('A Discord channel for this project', $hook !== '' ? 1 : 0,
		     $hook !== '' ? 'configured'
		       : 'No webhook, so every result and trait drop for this theme is posted nowhere. '
		       . 'It fails as silence, not as an error.');
	} else {
		$add('A Discord channel for this project', null, 'getWebhook() is not loaded here.');
	}
	/*
	 * The Skulliance-side mirror is a SECOND, separate channel, found by
	 * deriving the accessor name from the theme slug -- the same rule
	 * discordmsg() uses, so this reports what will actually happen rather
	 * than a list that can drift from it.
	 */
	$cfg_m = function_exists('dropshipThemeConfig')
	       ? dropshipThemeConfig($conn, $project_id) : null;
	$m_slug = ($cfg_m && !empty($cfg_m['slug'])) ? $cfg_m['slug'] : '';
	$m_fn = $m_slug === '' ? '' : 'getSkulliance'
		. str_replace(' ', '', ucwords(str_replace('-', ' ', $m_slug))) . 'Webhook';
	$add('...mirrored into a Skulliance channel',
	     ($m_fn !== '' && function_exists($m_fn)) ? 1 : 0,
	     $m_fn === '' ? 'No slug, so no accessor name to look for.'
	       : (function_exists($m_fn) ? $m_fn . '()'
	          : 'No ' . $m_fn . '() defined. Its own channel still gets everything; this '
	            . 'mirror is extra, and adding it is just that function in the credentials file.'));

	/* KOIOS source, step for step as dashboard.php runs it. */
	$addresses = function_exists('dropshipConnectedStakeAddresses')
	           ? dropshipConnectedStakeAddresses($discord_id) : array();
	$add('KOIOS: wallets connected to your account', count($addresses),
	     $addresses ? '' : 'Koios reads your wallets, so with none connected it is skipped entirely and nothing happens.');
	if (!$addresses) return $out;

	/* One malformed address makes Koios 500 the WHOLE batch, so the count
	   that matters is how many are usable, not how many exist. */
	$usable = koios_filter_stake_addresses($addresses);
	$bad = array();
	foreach ($addresses as $a) if (!koios_valid_stake_address($a)) $bad[] = (trim((string)$a) === '' ? '(empty)' : substr(trim((string)$a), 0, 24));
	$add('KOIOS: ...that Koios can read', count($usable),
	     $bad ? count($bad) . ' rejected before sending (' . implode(', ', array_slice($bad, 0, 4))
	            . '). Left in, any one of these makes Koios answer 500 for every wallet at once.'
	          : '');
	if (!$usable) return $out;
	$addresses = $usable;

	$ch = curl_init("https://api.koios.rest/api/v1/account_utxos?select=asset_list&asset_list=not.is.null");
	/* THE SAME REQUEST THE DASHBOARD MAKES, headers and body. A probe that
	   asks a different question answers a different question: this one
	   first reported a 500 the real pipeline never saw, because it left off
	   the bearer and _extended. */
	curl_setopt($ch, CURLOPT_HTTPHEADER, koios_headers());
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS,
		json_encode(array('_stake_addresses' => array_values($addresses), '_extended' => true)));
	curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
	/* The probe may wait longer than a page render would: it is a
	   diagnostic somebody is watching, and "it timed out" is itself the
	   answer it exists to give. */
	koios_timeouts($ch, 20);
	$raw  = curl_exec($ch);
	$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
	$cerr = curl_error($ch);
	curl_close($ch);
	$resp = json_decode((string)$raw);
	if (!is_array($resp)) {
		$add('KOIOS: answered', null,
		     'HTTP ' . $code . ($cerr !== '' ? ' -- ' . $cerr : '')
		     . '. Reply was not a list, so no token could be read.');
		return $out;
	}
	$add('KOIOS: answered', 1, 'HTTP ' . $code . ', ' . count($resp) . ' utxo group(s)');

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
	$add('KOIOS: tokens in those wallets', $total,
	     $total ? '' : 'The wallets hold no native tokens at all.');
	arsort($policies);
	$top = array();
	foreach (array_slice($policies, 0, 6, true) as $p => $n) $top[] = substr($p, 0, 12) . '... x' . $n;
	$add('KOIOS: ...under this collection', $mine,
	     $mine ? '' : 'You hold none of this collection in a connected wallet. Policies actually held: '
	                . ($top ? implode(', ', $top) : 'none'));
	return $out;
}
