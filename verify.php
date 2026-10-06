<?php
include_once 'db.php';
include_once 'message.php';
include_once 'webhooks.php';
require_once 'Bech32.php';

use CardanoPhp\Bech32\Bech32;

if(isset($argv)){
	parse_str(implode('&', array_slice($argv, 1)), $_GET);
}
/*
 * IS verify.php THE PAGE BEING REQUESTED, OR DID SOMETHING INCLUDE IT?
 *
 * Every block below is gated on isset($_GET['verify']) and nothing else
 * -- no auth, no CLI check -- and the last of them ends in platform-wide
 * payouts. verify.php is included by store.php, realms.php, wallets.php,
 * my-nfts.php, auctions.php, cryptcrawl.php, cryptconquest.php,
 * dhcgallery.php, raffles-verify.php and wallet-ajax.php, so until now
 * `store.php?verify=1` ran the entire nightly job, payouts included, for
 * anyone who typed it.
 *
 * That contradicts a rule this codebase already states for itself: the
 * leaderboard snapshot cron is kept deliberately read-only and its
 * dispatch deliberately separate, because coupling it "would put payout
 * paths one edit away from an unattended cron" (MAINTENANCE.md). Ten
 * pages is rather more than one edit.
 *
 * SCRIPT_FILENAME is the entry point, not the included file, so this is
 * true exactly when verify.php is what was asked for -- which covers the
 * CLI runs (php verify.php verify=polygon) and a URL cron on
 * verify.php?verify=1 unchanged, and nothing else. Chosen over a shared
 * token as the FIRST fix precisely because it cannot break a cron that
 * already works: if the existing crontab points at verify.php, it keeps
 * running with no change at all.
 *
 * It is not sufficient on its own -- verify.php itself is still reachable
 * over HTTP by anyone. See VERIFY_JOB_TOKEN below for the other half,
 * which needs a crontab edit and is therefore opt-in.
 */
/* Both sides must RESOLVE. realpath() returns false for a path that does
   not exist, and false === false is true -- so comparing the raw results
   would hand a pass to any context where SCRIPT_FILENAME is not a real
   file (php -r, for one, where it is the empty string). */
$verify_self  = @realpath(__FILE__);
$verify_asked = isset($_SERVER['SCRIPT_FILENAME']) ? @realpath($_SERVER['SCRIPT_FILENAME']) : false;
$verify_entry = is_string($verify_self) && is_string($verify_asked) && $verify_self === $verify_asked;
if (!$verify_entry && isset($_GET['verify'])) {
	error_log('verify.php: ?verify= ignored, included by '
	        . (isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '?')
	        . ' rather than requested directly');
}

/*
 * THE OTHER HALF, AND IT IS OPT-IN BECAUSE IT NEEDS A CRONTAB EDIT.
 *
 * Define VERIFY_JOB_TOKEN in credentials/db_credentials.php and the job
 * refuses to run over HTTP without ?token=<it>. Leave it undefined and
 * behaviour is exactly as it was, so this ships without touching a
 * running cron. CLI never needs it -- a shell on the box is already past
 * any gate this could impose.
 *
 *   define('VERIFY_JOB_TOKEN', '<long random string>');
 *   crontab: .../verify.php?verify=1&token=<the same string>
 */
function verify_job_allowed() {
	if (PHP_SAPI === 'cli') return true;
	if (!defined('VERIFY_JOB_TOKEN') || VERIFY_JOB_TOKEN === '') return true;
	$given = isset($_GET['token']) ? (string)$_GET['token'] : '';
	/* Constant time: a timing oracle on a token that triggers payouts is
	   not a thing to leave lying around. */
	return hash_equals((string)VERIFY_JOB_TOKEN, $given);
}

/*
 * THE SOLANA PASS, BY HAND. Like the XRPL one below it, this is not a
 * scheduled job -- it runs inside the main verify block, for the reason
 * written out there.
 *
 *   php verify.php verify=solana                run it now
 *   php verify.php verify=solana dry=1          read and report, write nothing
 *   php verify.php verify=solana dry=1 addr=... ...using one address, before
 *                                               anybody has linked a wallet
 */
if($verify_entry && verify_job_allowed() && isset($_GET['verify']) && $_GET['verify'] === 'solana'){
	set_time_limit(0);
	require_once __DIR__ . '/verify-solana.php';

	$sol_collections = getCollectionIDs($conn, SOLANA_CHAIN_ID);

	/*
	 * addr=... uses ONE address instead of the wallets table, so the whole
	 * pass can be proven before anybody has linked anything. Dry runs only:
	 * staking an address nobody has proved they hold is the one thing this
	 * file must never make easy.
	 */
	if(!empty($_GET['addr'])){
		if(empty($_GET['dry'])){
			echo "addr= is for dry runs only. A real pass reads the wallets table,\n";
			echo "because that is the only place an address has been proved.\n";
			exit(1);
		}
		$sol_addresses = array(trim($_GET['addr']));
	}else{
		$sol_addresses = getAllAddresses($conn, SOLANA_CHAIN_ID);
	}

	if(!$sol_addresses || !$sol_collections){
		printf("solana: nothing to do — %d linked address(es), %d registered collection(s)\n",
			count($sol_addresses), count($sol_collections));
		if(!$sol_collections)
			echo "  Register one first:\n"
			   . "    php verify-solana-probe.php <collection|asset|wallet address>\n";
		if(!$sol_addresses)
			echo "  Or try a dry run against one address:\n"
			   . "    php verify.php verify=solana dry=1 addr=<your address>\n";
		exit(1);
	}

	/*
	 * A DRY RUN PRINTS WHAT IS NOT REGISTERED, not just what is.
	 *
	 * A collection address off by one character matches nothing, raises
	 * nothing, and looks exactly like a correct run against a wallet that
	 * holds none of it. The only way to tell those apart is to list what the
	 * chain says the wallet holds that we did NOT recognise.
	 */
	if(!empty($_GET['dry'])){
		$api = getChainSetting($conn, SOLANA_CHAIN_ID, 'api_base',
		                       'https://api.mainnet-beta.solana.com');
		$seen = array(); $bad = array(); $unknown = array();
		foreach($sol_addresses AS $addr){
			$got = sol_account_assets($api, $addr, 'sol_http');
			if(!$got['ok']){ $bad[] = $addr; continue; }
			foreach($got['list'] AS $a){
				if(isset($sol_collections[$a['collection']]))
					$seen[$a['collection']] = (isset($seen[$a['collection']]) ? $seen[$a['collection']] : 0) + 1;
				else
					$unknown[$a['collection']] = (isset($unknown[$a['collection']]) ? $unknown[$a['collection']] : 0) + 1;
			}
		}
		echo "solana DRY RUN — nothing was written\n";
		printf("  %d address(es), %d registered collection(s)\n",
			count($sol_addresses), count($sol_collections));
		if($bad) echo "  COULD NOT READ: " . implode(', ', $bad) . "\n";
		if($seen){
			echo "  would stake:\n";
			foreach($seen AS $policy => $n) printf("    %-44s %d\n", $policy, $n);
		}else{
			echo "  would stake: NOTHING\n";
		}
		if($unknown){
			echo "\n  held, but NOT registered:\n";
			foreach($unknown AS $policy => $n) printf("    %-44s %d\n", $policy, $n);
			echo "\n  If one of those is the collection you meant to add, the policy in\n";
			echo "  the collections table does not match what the chain says. That is\n";
			echo "  the failure this mode exists to show.\n";
			echo "    php verify-solana-probe.php <that address>\n";
		}
		exit(($bad || !$seen) ? 1 : 0);
	}

	echo sol_nightly($conn) . "\n";
	exit;
}

/*
 * THE POLYGON PASS, BY HAND. Like the other two, this is not a scheduled
 * job -- it runs inside the main verify block, for the reason written
 * there.
 *
 *   php verify.php verify=polygon                run it now
 *   php verify.php verify=polygon dry=1          read and report, write nothing
 *   php verify.php verify=polygon dry=1 addr=0x. ...using one address, before
 *                                                anybody has linked a wallet
 */
if($verify_entry && verify_job_allowed() && isset($_GET['verify']) && $_GET['verify'] === 'polygon'){
	set_time_limit(0);
	require_once __DIR__ . '/verify-polygon.php';

	$poly_collections = getCollectionIDs($conn, POLYGON_CHAIN_ID);

	/* addr=... uses ONE address instead of the wallets table, so the pass
	   can be proven before anybody has linked anything. Dry runs only: a
	   real pass must read the wallets table or it would clear everybody
	   else's rows and rebuild only this one. */
	if(!empty($_GET['addr'])){
		if(empty($_GET['dry'])){
			echo "addr= is for dry runs only. A real pass reads the wallets table,\n";
			echo "and running it for one address would clear everybody else's rows.\n";
			exit(1);
		}
		$poly_addresses = array((string)$_GET['addr']);
	}else{
		$poly_addresses = getAllAddresses($conn, POLYGON_CHAIN_ID);
	}

	if(!$poly_collections){
		echo "polygon: no collections registered for chain " . POLYGON_CHAIN_ID . ".\n";
		echo "  Add one in the Collections admin panel, with the CONTRACT ADDRESS\n";
		echo "  as the policy, in lower case.\n";
		exit(1);
	}
	if(!$poly_addresses){
		echo "polygon: no wallets linked on chain " . POLYGON_CHAIN_ID . ".\n";
		echo "  Try a dry run against one address first:\n"
		   . "    php verify.php verify=polygon dry=1 addr=0xYourAddress\n";
		exit(1);
	}

	/*
	 * A DRY RUN PRINTS THE WHOLE OWNER MAP, not just our share.
	 *
	 * The failure this exists to catch is a contract address that is
	 * wrong by a character. On the per-wallet chains that shows up as
	 * "held, but NOT registered"; here it cannot, because an unregistered
	 * contract is never read at all. What it looks like instead is a
	 * collection whose map comes back EMPTY -- indistinguishable from a
	 * correct run against a collection nobody here holds, unless the
	 * supply is printed beside it.
	 */
	if(!empty($_GET['dry'])){
		$node = poly_pick_node(getChainSetting($conn, POLYGON_CHAIN_ID, 'api_base', ''));
		if($node === ''){
			echo "polygon DRY RUN - no node answered on chain 137.\n";
			exit(1);
		}
		echo "polygon DRY RUN - nothing was written\n";
		echo "  node: $node\n";
		printf("  %d address(es), %d registered collection(s)\n",
			count($poly_addresses), count($poly_collections));

		$mine = array();
		foreach($poly_addresses AS $a){
			$n = poly_norm_address($a);
			if($n === '') echo "  NOT AN ADDRESS: " . $a . "\n";
			else $mine[$n] = 1;
		}

		$total_ours = 0; $bad = 0;
		foreach($poly_collections AS $contract => $cid){
			$map = poly_owner_map($node, $contract, 'poly_http');
			if(!$map['ok']){
				$bad++;
				printf("  %-44s COULD NOT READ: %s\n", $contract, implode('; ', $map['failed']));
				echo "    A contract address that is wrong by one character looks exactly\n";
				echo "    like this. Check it against the chain before blaming the node.\n";
				continue;
			}
			$ours = 0;
			foreach($map['owners'] AS $owner) if(isset($mine[$owner])) $ours++;
			$total_ours += $ours;
			printf("  %-44s supply %d, %d owned, %d ours\n",
				$contract, $map['supply'], count($map['owners']), $ours);
			if(count($map['owners']) === 0){
				echo "    NOBODY owns any of it, which for a live collection means the\n";
				echo "    policy in the collections table is not this contract.\n";
			}
		}
		echo $total_ours ? "  would stake: $total_ours\n" : "  would stake: NOTHING\n";
		exit(($bad || !$total_ours) ? 1 : 0);
	}

	echo poly_nightly($conn) . "\n";
	exit;
}

/*
 * THE XRPL PASS, BY HAND. Not a scheduled job -- see the note in the main
 * verify block below for why it runs inside that one instead.
 *
 *   php verify.php verify=xrpl              run it now
 *   php verify.php verify=xrpl dry=1        read and report, write nothing
 *   php verify.php verify=xrpl dry=1 addr=r...   ...using one address, before
 *                                                anybody has linked a wallet
 */
if($verify_entry && verify_job_allowed() && isset($_GET['verify']) && $_GET['verify'] === 'xrpl'){
	set_time_limit(0);
	require_once __DIR__ . '/verify-xrpl.php';

	$xrpl_collections = getCollectionIDs($conn, XRPL_CHAIN_ID);

	/*
	 * addr=r... uses ONE address instead of the wallets table, so the whole
	 * pass can be checked before anybody has linked a wallet. Dry runs only:
	 * staking an address nobody has proved they hold is the one thing this
	 * subsystem exists to prevent, and a convenience flag is exactly how that
	 * kind of hole gets opened.
	 */
	if(!empty($_GET['addr'])){
		if(empty($_GET['dry'])){
			echo "addr= is for dry runs only. A real pass reads the wallets table,\n";
			echo "because an address only counts once somebody has proved they hold it.\n";
			exit(1);
		}
		$xrpl_addresses = array(trim($_GET['addr']));
	}else{
		$xrpl_addresses = getAllAddresses($conn, XRPL_CHAIN_ID);
	}

	if(!$xrpl_addresses || !$xrpl_collections){
		printf("xrpl: nothing to do — %d linked address(es), %d registered collection(s)\n",
			count($xrpl_addresses), count($xrpl_collections));
		if(!$xrpl_collections)
			echo "  No XRPL collection registered yet. See multichain-schema.md §3,\n"
			   . "  and verify-xrpl-probe.php to find a collection's issuer:taxon.\n";
		if(!$xrpl_addresses)
			echo "  No XRPL wallet linked yet. To check the pass before anybody links one:\n"
			   . "    php verify.php verify=xrpl dry=1 addr=rYourAddress\n";
		exit;
	}

	/*
	 * DRY RUN. Worth having because the likeliest first-run mistake is SILENT:
	 * an issuer:taxon off by a digit matches nothing, writes nothing and
	 * raises nothing, which looks exactly like a correct run against wallets
	 * that hold nothing. This prints what is on the ledger next to what is
	 * registered, so the two can be told apart.
	 */
	if(!empty($_GET['dry'])){
		$seen = array(); $unmatched = array(); $bad = array();
		foreach($xrpl_addresses AS $addr){
			$got = xrpl_account_nfts(
				getChainSetting($conn, XRPL_CHAIN_ID, 'api_base', 'https://xrplcluster.com'),
				$addr, 'xrpl_http');
			if(!$got['ok']){ $bad[] = $addr; continue; }
			foreach($got['list'] AS $n){
				if(isset($xrpl_collections[$n['policy']])) $seen[$n['policy']] = (isset($seen[$n['policy']]) ? $seen[$n['policy']] : 0) + 1;
				else $unmatched[$n['policy']] = (isset($unmatched[$n['policy']]) ? $unmatched[$n['policy']] : 0) + 1;
			}
		}
		echo "DRY RUN — nothing was written\n\n";
		printf("  %d address(es), %d registered collection(s)\n", count($xrpl_addresses), count($xrpl_collections));
		if($bad) printf("  UNREADABLE: %s\n", implode(', ', $bad));
		echo "\n  would stake:\n";
		if($seen) foreach($seen AS $k => $c) printf("    %-46s %d\n", $k, $c);
		else      echo "    (nothing)\n";
		if($unmatched){
			echo "\n  on the ledger but NOT registered:\n";
			foreach($unmatched AS $k => $c) printf("    %-46s %d\n", $k, $c);
			echo "\n  If one of those is the collection you meant to add, its policy in\n";
			echo "  the collections table does not match what the ledger says. That is\n";
			echo "  the failure this mode exists to show.\n";
		}
		exit(($bad || !$seen) ? 1 : 0);
	}

	echo xrpl_nightly($conn) . "\n";
	exit;
}

// Distinguish between a logged in user and verification cron job
if($verify_entry && verify_job_allowed() && isset($_GET['verify'])){
	set_time_limit(0);

	/*
	 * XRPL FIRST, IN THIS SAME JOB, AND NOT AS A SEPARATE CRON.
	 *
	 * The payout steps at the end of this block are platform-wide: they read
	 * whatever is staked, on either chain, and pay once. Scheduling XRPL as
	 * its own cron put a race between them -- the XRPL pass clears ownership
	 * before rebuilding it, so a payout landing mid-pass reads a half-rebuilt
	 * table, or an empty one, and underpays. Silently, and in money.
	 *
	 * Two crons with a generous gap only makes that unlikely, not impossible,
	 * and "unlikely" is the wrong guarantee for a payout. Running it here
	 * makes the ordering structural: the payouts below cannot start until this
	 * has finished.
	 *
	 * ISOLATION IS NOT THE SAME THING AS A SEPARATE JOB, which is what the
	 * earlier design confused. xrpl_nightly() never throws and is bounded by a
	 * wall-clock budget, so an XRPL problem costs XRPL holders a night and
	 * costs everybody else nothing -- which is all the isolation was ever for.
	 */
	require_once __DIR__ . '/verify-xrpl.php';
	echo xrpl_nightly($conn) . "\n";

	/*
	 * SOLANA, FOR THE SAME REASONS AND IN THE SAME PLACE. Everything the
	 * comment above says about the payout race applies identically: the
	 * payouts below are platform-wide and must not start until every chain
	 * has finished rebuilding ownership. sol_nightly() never throws and is
	 * bounded by its own wall-clock budget, so a Solana problem costs Solana
	 * holders a night and costs nobody else anything.
	 *
	 * Each chain clears only its OWN rows -- removeUsers($conn, <chain>) --
	 * which is what makes running them in one job safe. A pass that cleared
	 * another chain's rows would never restore them, and nothing would error.
	 */
	require_once __DIR__ . '/verify-solana.php';
	echo sol_nightly($conn) . "\n";

	/*
	 * POLYGON, SAME PLACE AND SAME CONTRACT. poly_nightly() never throws
	 * and is bounded by a wall-clock budget, and it clears only
	 * removeUsers($conn, POLYGON_CHAIN_ID).
	 *
	 * It is CHEAPER than the two above rather than more expensive, which
	 * is worth knowing before anyone reaches for a budget: this chain
	 * reads the collection once instead of once per address, so its cost
	 * is set by how big the collection is, not by how many holders have
	 * linked a wallet. 4,444 tokens is 18 calls, measured at ~3.2s on
	 * this server, and that figure does not move as the platform grows.
	 */
	require_once __DIR__ . '/verify-polygon.php';
	echo poly_nightly($conn) . "\n";

	$addresses = array();
	$addresses = getAllAddresses($conn);

	/*
	 * BELT AND BRACES ON THE CHAIN SCOPE.
	 *
	 * getAllAddresses() already filters to blockchain_id 1, so anything
	 * non-Cardano here means a row is MISLABELLED -- which happened: an XRPL
	 * r-address stored as Cardano, handed to Koios every night, answered with
	 *
	 *   Failed to decode Bech32 string: Parse(Char(InvalidChar('i')))
	 *
	 * four times and a Discord alert each time. checkAddress() now refuses
	 * those at the door, but rows written before that fix still exist and a
	 * future mislabelling must not cost the payout job four retries and the
	 * operator a false alarm.
	 *
	 * Logged rather than silently dropped: a skipped address is somebody not
	 * being paid, which is exactly the kind of thing that should be visible.
	 */
	$wrong_chain = array();
	foreach($addresses AS $i => $a){
		if(!preg_match('/^(stake|addr)(_test)?1[0-9a-z]{10,}$/i', trim((string)$a))){
			$wrong_chain[] = $a;
			unset($addresses[$i]);
		}
	}
	if($wrong_chain){
		$addresses = array_values($addresses);
		$note = "verify: skipped ".count($wrong_chain)." non-Cardano address(es) filed as Cardano: "
		      . implode(', ', array_slice($wrong_chain, 0, 5))
		      . ". Fix with: UPDATE wallets SET blockchain_id = 2 WHERE stake_address IN (...);";
		echo $note."\n";
		error_log($note);
	}

	$policies = array();
	$policies = getPolicies($conn);
	// Remove all user ids from NFTs before running cron job verification
	removeUsers($conn);
	// Get all NFT asset IDs to determine whether to update DB records, saves on DB resources instead of individual DB calls to check NFT presence
	$asset_ids = array();
	$asset_ids = getNFTAssetIDs($conn);
	// Verify all NFTs from wallets in the DB
	$nft_owners = verifyNFTs($conn, $addresses, $policies, $asset_ids);
	// Zero out protected NFTs (Diamond Skulls + delegated) whose owner's wallet was processed but the NFT wasn't found
	cleanupOrphanedProtectedNFTs($conn, $addresses, $nft_owners);
	// Deactivate soldiers whose NFT is no longer owned by the realm's user and return their gear to inventory
	verifyRealmSoldiers($conn);
	// Get project percentages for Diamond Skull delegations
	$percentages = array();
	$percentages = getProjectDelegationPercentages($conn);
	// Determine whether Diamond Skulls should get a bonus
	$diamond_skull_bonus = getDiamondSkullBonus($percentages);
	// Deploy rewards for all users of the platform
	updateBalances($conn, $diamond_skull_bonus);
	// Deploy rewards for Diamond Skull delegation
	deployDiamondSkullRewards($conn, $percentages);
	// Write nightly realm resource generation to realms_logs
	//define('REALMS_LOGS_INCLUDED', true);
	//include_once 'realms-logs.php';
}

function verifyNFTs($conn, $addresses, $policies, $asset_ids, $nft_owners=array(), $attempts=0){
	global $blockfrost_project_id;
	
	// Havoc Worlds - Add smart contract stake address manually
	$addresses[] = 'stake1uxg4ucl2m0j4d6ycuychm0dzl2ed4rr33h2q5w8u4yhwtwg3jdp34';
	
	$collections = getCollectionIDs($conn);
	$failed_addresses = array();
	$attempts++;
	
	$offsets = array();
	$offsets[1] = "";
	$offsets[2] = "offset=1000";
	$offset_flag = false;
	$message = "";
	
	foreach($offsets AS $i => $offset){
		if($i == 2){
			$offset_flag = true;
		}
		foreach($addresses AS $index => $address){
			if(isset($_SESSION['userData']['user_id'])){
				$user_id = $_SESSION['userData']['user_id'];
			}else{
				$user_id = getUserId($conn, $address);
			}
			// Run verification if first pass OR if stake address for dhp157 aka Davi on second pass, accommodates an extra batch for more than 1,000 UTXOs in a single wallet
			if($offset_flag == false || $address == "stake1u9h47jzelq38mk7yvaxklducf9uw7lhmfhwk4fm44wfdszsgqdmmz"){
				// Koios' PostgREST backend intermittently returns a transient 504 /
				// PGRST003 ("Timed out acquiring connection from connection pool").
				// Without a retry, a single hiccup here drops into the "no response"
				// failure branch below and exit()s the whole nightly run. Retry
				// transient failures (cURL errors, HTTP 5xx, or any response that
				// doesn't decode to an array) a few times with backoff before giving
				// up. A legitimately empty wallet returns a 2xx [] which is a valid
				// array, so it succeeds immediately and is never retried.
				$max_attempts = 4;
				$response = false;
				$http_code = 0;
				for($attempt = 1; $attempt <= $max_attempts; $attempt++){
					$ch = curl_init("https://api.koios.rest/api/v1/account_utxos?select=asset_list,inline_datum&asset_list=not.is.null".$offset);
					curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'accept: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXhybHB1d2R4MjN4bGRhM3hkOG40NnR3cW0zano5Y3hkNGYyazJoaDhzNGUwMGN3ZmFnNHUiLCJleHAiOjE3OTc5NjAyODEsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.JWfVIQGU6SH0p7BpyzqV931Em8nz_eKkVbheIGzLShg'));
					curl_setopt( $ch, CURLOPT_POST, 1);
					curl_setopt( $ch, CURLOPT_POSTFIELDS, '{"_stake_addresses":["'.$address.'"],"_extended":true}');
					curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
					curl_setopt( $ch, CURLOPT_HEADER, 0);
					curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
					//curl_setopt( $ch, CURLOPT_VERBOSE, true);

					$response = curl_exec( $ch );
					$curl_err = ($response === false) ? curl_error($ch) : "";
					$curl_errno = ($response === false) ? curl_errno($ch) : 0;
					$http_code = ($response === false) ? 0 : curl_getinfo($ch, CURLINFO_HTTP_CODE);
					curl_close( $ch );

					$decoded = json_decode($response);

					// Success: a 2xx response that decodes to an array (an empty wallet's
					// [] counts and is not retried). Keep the decoded value and stop.
					if($response !== false && $http_code >= 200 && $http_code < 300 && is_array($decoded)){
						$response = $decoded;
						break;
					}

					// Transient failure — log this attempt. $message is only ever DM'd
					// from the failure/exit branches, so on eventual success these lines
					// are harmlessly discarded; on permanent failure they give the
					// operator the full retry history.
					if ($response === false) {
					    $message .= "cURL Error (attempt ".$attempt."/".$max_attempts."): " . $curl_err . "\n";
					    $message .= "cURL Error Number: " . $curl_errno . "\n";
					} else {
					    $message .= "HTTP Error (attempt ".$attempt."/".$max_attempts."): Status code " . $http_code . "\n";
					    $message .= "Response: " . $response . "\n";
					}
					$response = $decoded;
					// Back off before retrying (3s, 6s, 9s); skip the wait after the last attempt.
					if($attempt < $max_attempts){
						sleep($attempt * 3);
					}
				}

				//$_SESSION['userData']['nfts'] = array();
				if(is_array($response)){
			    if(isset($response[0])){
					$asset_names = array();
					$counter = 0;
					$asset_list = array();
					$asset_list["_asset_list"] = array();
					// Temporary counter, remove after testing
					$havoc_worlds_assets = array();
					foreach($response AS $index => $list){
						$process = false;
						
						// Havoc Worlds - Decode inline datum bytes to extract stake address of Havoc World assets in smart contract
						if($address == "stake1uxg4ucl2m0j4d6ycuychm0dzl2ed4rr33h2q5w8u4yhwtwg3jdp34"){
							if(isset($list->inline_datum->value->bytes)){
								// Your input hex string (28-byte stake key hash)
								$hex = $list->inline_datum->value->bytes;

								// Prepend header (0xe1 for mainnet stake address)
								$fullHex = "e1" . $hex;

								// Convert hex to 5-bit array
								$byteArray = Bech32::hexToByteArray($fullHex);

								// Encode as Bech32 with "stake" prefix (mainnet)
								$stakeAddress = Bech32::encode("stake", $byteArray);
								
								// Check if decoded stake address in UTXO exists within stake address in the database
								if(in_array($stakeAddress, $addresses)){
									$process = true;
								}
							}
						}else{
							$process = true;
						}
						
						if($process == true){
							foreach($list->asset_list AS $index => $token){
								if(in_array($token->policy_id, $policies)){
									$asset_list["_asset_list"][$counter] = array();
									$asset_list["_asset_list"][$counter][0] = $token->policy_id;
									$asset_list["_asset_list"][$counter][1] = $token->asset_name;
									$counter++;
									
									// Havoc Worlds - align asset names with actual stake addresses of staker
									if($address == "stake1uxg4ucl2m0j4d6ycuychm0dzl2ed4rr33h2q5w8u4yhwtwg3jdp34"){
										$havoc_worlds_assets[$token->asset_name] = $stakeAddress;
									}
					
								} // End if
							} // End foreach
						}
					}
			
					// Batch asset list into arrays of 35 items or less to allow for successful queries, had to reduce from 50 to 35 to remain under the free Koios plan limits.
					$batch_asset_lists = array();
					$final_asset_lists = array();
					$batch_index = 0;
					if(count($asset_list["_asset_list"]) < 35){
						$final_asset_lists[$batch_index] = array();
						$final_asset_lists[$batch_index]["_asset_list"] = $asset_list["_asset_list"];
					}else{
						$batch_asset_lists = array_chunk($asset_list["_asset_list"], 35);
						foreach($batch_asset_lists AS $index => $batch_asset_list){
							$final_asset_lists[$index] = array();
							$final_asset_lists[$index]["_asset_list"] = $batch_asset_list;
						}
					}

					foreach($final_asset_lists AS $final_asset_index => $final_asset_list){
						// Koios' PostgREST backend intermittently returns a transient 504 /
						// PGRST003 ("Timed out acquiring connection from connection pool").
						// That used to drop straight into the failure branch below and
						// exit(), killing the whole nightly verification run every few days.
						// Retry transient failures (cURL errors, HTTP 5xx, or any response
						// that doesn't decode to the expected array) a few times with
						// exponential-ish backoff before giving up.
						$max_attempts = 4;
						$tokenresponse = false;
						$http_code = 0;
						for($attempt = 1; $attempt <= $max_attempts; $attempt++){
							$tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
							curl_setopt( $tokench, CURLOPT_HTTPHEADER, array('Content-type: application/json', 'authorization: Bearer eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJhZGRyIjoic3Rha2UxdXhybHB1d2R4MjN4bGRhM3hkOG40NnR3cW0zano5Y3hkNGYyazJoaDhzNGUwMGN3ZmFnNHUiLCJleHAiOjE3OTc5NjAyODEsInRpZXIiOjEsInByb2pJRCI6InNrdWxsaWFuY2UifQ.JWfVIQGU6SH0p7BpyzqV931Em8nz_eKkVbheIGzLShg'));
							curl_setopt( $tokench, CURLOPT_POST, 1);
							curl_setopt( $tokench, CURLOPT_POSTFIELDS, json_encode($final_asset_list));
							curl_setopt( $tokench, CURLOPT_FOLLOWLOCATION, 1);
							curl_setopt( $tokench, CURLOPT_HEADER, 0);
							curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
							//curl_setopt( $tokench, CURLOPT_VERBOSE, true);

							//$tokench = curl_init("https://api.koios.rest/api/v0/asset_info?_asset_policy=".$token->policy_id."&_asset_name=".$token->asset_name);
							//curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
							$tokenresponse = curl_exec( $tokench );
							$curl_err = ($tokenresponse === false) ? curl_error($tokench) : "";
							$curl_errno = ($tokenresponse === false) ? curl_errno($tokench) : 0;
							$http_code = ($tokenresponse === false) ? 0 : curl_getinfo($tokench, CURLINFO_HTTP_CODE);
							curl_close( $tokench );

							$decoded = json_decode($tokenresponse);

							// Success: a 2xx response that decodes to the expected array of
							// asset records. Keep the decoded value and stop retrying.
							if($tokenresponse !== false && $http_code >= 200 && $http_code < 300 && is_array($decoded)){
								$tokenresponse = $decoded;
								break;
							}

							// Transient failure — log this attempt. $message is only ever DM'd
							// from the failure/exit branches, so on eventual success these
							// lines are harmlessly discarded; on permanent failure they give
							// the operator the full retry history.
							if ($tokenresponse === false) {
							    $message .= "cURL Error (attempt ".$attempt."/".$max_attempts."): " . $curl_err . "\n";
							    $message .= "cURL Error Number: " . $curl_errno . "\n";
							} else {
							    $message .= "HTTP Error (attempt ".$attempt."/".$max_attempts."): Status code " . $http_code . "\n";
							    $message .= "Response: " . $tokenresponse . "\n";
							}
							$tokenresponse = $decoded;
							// Back off before retrying (3s, 6s, 9s); skip the wait after the last attempt.
							if($attempt < $max_attempts){
								sleep($attempt * 3);
							}
						}
			
						if(is_array($tokenresponse)){
							foreach($tokenresponse AS $index => $tokenresponsedata){
								
								// Prevent double creation or update of the same NFT for a specific user
								//if(!checkNFTOwner($conn, $tokenresponsedata->fingerprint, $user_id)){
								if(!in_array($user_id."-".$tokenresponsedata->fingerprint, $nft_owners)){
									
									// Havoc Worlds - Set address to another variable for NFT processing so that $address is not overriden with Havoc Worlds stakers
									$nft_address = $address;
									// Havoc Worlds - Check if address is smart contract wallet and lookup address of staker before saving NFT data
									if($address == "stake1uxg4ucl2m0j4d6ycuychm0dzl2ed4rr33h2q5w8u4yhwtwg3jdp34"){
										// Override nft_address with Havoc Worlds staker address
										$nft_address = $havoc_worlds_assets[$tokenresponsedata->asset_name];
										// Lookup user for Havoc Worlds staker address since no user exists for Havoc Worlds smart contract wallet
										$user_id = getUserId($conn, $nft_address);
									}
									
									// Check whether NFT already exists in the db. If so, just update it and don't fuck with cycling through NFT metadata that tends to randomly fail
									if(in_array($tokenresponsedata->fingerprint, $asset_ids)){
										// Check to see if there is an NFT with no owner in the database
										if(checkAvailableNFT($conn, $tokenresponsedata->fingerprint)){
											// Limit update to 1 record and only for NFTs with no current owner
											updateNFT($conn, $tokenresponsedata->fingerprint, $user_id);
											$nft_owners[] = $user_id."-".$tokenresponsedata->fingerprint;
										// If someone already has ownership — Diamond Skull and delegated NFTs use
										// force-update (never zeroed by removeUsers to avoid circular dependency).
										// All other NFTs with existing owners are RFTs and get a new DB entry.
										}else{
											$fp_esc = $conn->real_escape_string($tokenresponsedata->fingerprint);
											$is_protected = (isset($collections[$tokenresponsedata->policy_id]) && $collections[$tokenresponsedata->policy_id] == 16)
												|| ($conn->query("SELECT 1 FROM nfts n JOIN diamond_skulls ds ON ds.nft_id = n.id WHERE n.asset_id = '$fp_esc' LIMIT 1")->num_rows > 0);
											if($is_protected){
												forceUpdateNFT($conn, $tokenresponsedata->fingerprint, $user_id);
												$nft_owners[] = $user_id."-".$tokenresponsedata->fingerprint;
											}else{
												$payload = processNFTMetadata($conn, $tokenresponsedata, $nft_address, $asset_ids, $nft_owners, $collections);
												$asset_ids = $payload["asset_ids"];
												$nft_owners = $payload["nft_owners"];
											}
										}
									}else{
										$payload = processNFTMetadata($conn, $tokenresponsedata, $nft_address, $asset_ids, $nft_owners, $collections);
										$asset_ids = $payload["asset_ids"];
										$nft_owners = $payload["nft_owners"];
									} // End if
								}
							} // End foreach
						}else{
							$message .= "Bulk asset info could not be retrieved after ".$max_attempts." attempts for stake address: https://pool.pm/".$address." \r\n";
							$failed_addresses[] = $address;
							echo $message;
							print_r($tokenresponse);
							sendDM("772831523899965440", $message);
							exit();
						}
					} // End foreach
					//updateNFTs($conn, implode("', '", $asset_names));
				}else{
					// Empty array response. Two cases:
					//   1) HTTP 2xx + [] — usually a legitimately emptied wallet
					//      (the request filters with asset_list=not.is.null).
					//   2) Non-2xx + [] — an API error whose body happened to
					//      parse as []. Never trust this as "empty".
					//
					// For case (1), one more guard: if Koios is lying (rare but
					// possible — indexing lag, internal consistency bug) and the
					// user owns protected NFTs (Diamond Skulls or delegated),
					// trusting the empty would cascade through
					// cleanupOrphanedProtectedNFTs: the skull gets zeroed, every
					// NFT delegated to it gets booted, and only a DB restore can
					// reverse it. So before silently skipping, check the DB —
					// if this address's user holds protected NFTs, alert and
					// exit so the operator can confirm the wallet really is
					// empty. Wallets without protected NFTs skip silently (the
					// common case that was triggering nightly false alarms).
					if($http_code >= 200 && $http_code < 300){
						$check_uid = getUserId($conn, $address);
						$protected_count = 0;
						if($check_uid > 0){
							$uid_esc = intval($check_uid);
							$res = $conn->query("
								SELECT COUNT(*) AS c FROM nfts
								WHERE user_id = $uid_esc
								  AND (collection_id = 16 OR id IN (SELECT nft_id FROM diamond_skulls))
							");
							if($res && $row = $res->fetch_assoc()){
								$protected_count = intval($row['c']);
							}
						}
						if($protected_count > 0){
							$message .= "Koios returned [] for stake address: https://pool.pm/".$address." (HTTP 2xx)\r\n";
							$message .= "User ".$check_uid." owns ".$protected_count." protected NFT(s) (Diamond Skulls or delegated). Aborting to avoid booting delegators — confirm whether the wallet is actually empty before rerunning.\r\n";
							$failed_addresses[] = $address;
							echo $message;
							sendDM("772831523899965440", $message);
							exit();
						}else{
							echo "No NFTs for stake address: https://pool.pm/".$address." (empty wallet, skipping) \r\n";
						}
					}else{
						$message .= "There was no response data for stake address: https://pool.pm/".$address." (HTTP ".$http_code.")\r\n";
						$failed_addresses[] = $address;
						echo $message;
						print_r($response);
						sendDM("772831523899965440", $message);
						exit();
					}
				}
				}else{
					$message .= "There was no response after ".$max_attempts." attempts for stake address: https://pool.pm/".$address." \r\n";
					$failed_addresses[] = $address;
					echo $message;
					print_r($response);
					sendDM("772831523899965440", $message);
					exit();
				}
			} // Offset End if
		} // End foreach
	} // End offset foreach
	// This is not working for some reason. It keeps having unverified assets that mess up Diamond Skull delegation.
	/*
	if(!empty($failed_addresses)){
		if($attempts <= 3){
			verifyNFTs($conn, $failed_addresses, $policies, $asset_ids, $nft_owners, $attempts);
			echo "Attempt: ".$attempts." \r\n";
		}else{
			$message = "There were 3 verification attempts yet the following addresses continued to fail: \r\n";
			$message .= print_r($failed_addresses, true);
			echo $message;
			sendDM("772831523899965440", $message);
			exit();
		}
	}*/
	return $nft_owners;
}

function processNFTMetadata($conn, $tokenresponsedata, $address, $asset_ids, $nft_owners, $collections){
	global $blockfrost_project_id;
	$payload = array();
	// Handle creation of NFTs by cycling through NFT metadata
	if(isset($tokenresponsedata->minting_tx_metadata)){
		foreach($tokenresponsedata->minting_tx_metadata AS $metadata){
			$policy_id = $tokenresponsedata->policy_id;
			if(isset($tokenresponsedata->asset_name_ascii)){
				$asset_name = $tokenresponsedata->asset_name_ascii;
				if(isset($metadata->$policy_id)){
					$nft = $metadata->$policy_id;
					if(isset($nft)){
						$nft_data = $nft->$asset_name;
						if(isset($nft_data)){
							// Account for NFT with NaN value for asset name
							if($asset_name == "NaN"){
								$nft_data->AssetName = "DROPSHIP012";
							}else{
								$nft_data->AssetName = $asset_name;
							}
							// When name is not present, but Name is present, make name Name
							if(!isset($nft_data->name)){
								if(isset($nft_data->Name)){
									$nft_data->name = $nft_data->Name;
								}
							}
							if(isset($nft_data->AssetName) && isset($nft_data->name) && isset($nft_data->image) && isset($tokenresponsedata->fingerprint)){
								$payload = processNFT($conn, $policy_id, $nft_data->AssetName, $nft_data->name, $nft_data->image, $tokenresponsedata->fingerprint, $address, $asset_ids, $nft_owners, $collections);
								$asset_ids = $payload["asset_ids"];
								$nft_owners = $payload["nft_owners"];
							}else{
								//echo "NFT is missing an asset name, name, image, or fingerprint.";
							}
						}else{
							// Handles cases where the NFT data is empty for whatever reason, but the NFT still exists in the database and ownership needs to be assigned
							/* This is no longer needed because we're checking for updates above before determining whether processing NFT metadata is necessary to add new NFTs
							echo $asset_name." was missing NFT data, but was still updated in the db. \r\n";
							if(isset($_SESSION['userData']['user_id'])){
								$user_id = $_SESSION['userData']['user_id'];
							}else{
								$user_id = getUserId($conn, $address);
							}
							if(in_array($tokenresponsedata->fingerprint, $asset_ids)){
								updateNFT($conn, $tokenresponsedata->fingerprint, $user_id);
							}*/
						}
					}
				}
			}
		} // End foreach
	// Use Koios CIP-68 metadata
	}else if(isset($tokenresponsedata->cip68_metadata)){
		$traits = array();
		$alternate = "key";
		$key = "";
		$value = "";
		foreach($tokenresponsedata->cip68_metadata AS $metadata){
			foreach($metadata AS $fields){
				if(is_array($fields)){
					foreach($fields AS $maps){
						foreach($maps AS $map){
							if(is_array($map)){
								foreach($map AS $pairings){
									foreach($pairings AS $pairing){
										if(isset($pairing->bytes)){
											if($alternate == "key"){
												$key = hex2str($pairing->bytes);
												$alternate = "value";
											}else{
												$value = hex2str($pairing->bytes);
												$alternate = "key";
											}
										}
									}
									if($key != "" && $value != ""){
										$traits[$key] = $value;
									}
								}
							}
						}
					}
				}
			}
			if(isset($traits["name"]) && isset($traits["image"]) && isset($tokenresponsedata->fingerprint)){
				$payload = processNFT($conn, $tokenresponsedata->policy_id, $traits["name"], $traits["name"], $traits["image"], $tokenresponsedata->fingerprint, $address, $asset_ids, $nft_owners, $collections);
				$asset_ids = $payload["asset_ids"];
				$nft_owners = $payload["nft_owners"];
			}
		}
	// Fallback to Blockfrost for CIP68
	}else{
		$blockfrostch = curl_init("https://cardano-mainnet.blockfrost.io/api/v0/assets/".$tokenresponsedata->policy_id.$tokenresponsedata->asset_name);
		curl_setopt( $blockfrostch, CURLOPT_HTTPHEADER, array('Content-type: application/json', "project_id: ".$blockfrost_project_id));
		curl_setopt( $blockfrostch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $blockfrostch, CURLOPT_HEADER, 0);
		curl_setopt( $blockfrostch, CURLOPT_RETURNTRANSFER, 1);
		$blockfrostresponse = curl_exec( $blockfrostch );
		$blockfrostresponse = json_decode($blockfrostresponse);

		curl_close( $blockfrostch );

		if(is_object($blockfrostresponse)){
				$metadata = $blockfrostresponse->onchain_metadata;
				// Convert CIP68 asset name from hex to str and strip out extra b.s.
				$asset_name = clean(hex2str($blockfrostresponse->asset_name));
				$payload = processNFT($conn, $blockfrostresponse->policy_id, $asset_name , $metadata->name, $metadata->image, $blockfrostresponse->fingerprint, $address, $asset_ids, $nft_owners, $collections);
				$asset_ids = $payload["asset_ids"];
				$nft_owners = $payload["nft_owners"];
		}
	} // End if
	$payload["asset_ids"] = $asset_ids;
	$payload["nft_owners"] = $nft_owners;
	return $payload;
}

/*
 * $blockchain_id is LAST and defaults to 1 so every Cardano caller is
 * untouched -- but it has to exist. createNFT() also defaults it to 1, so
 * omitting it here wrote every XRPL NFT as Cardano: a row that looks perfect,
 * errors nowhere, and is wrong in three compounding ways.
 *
 *   1. getNFTAssetIDs($conn, 2) never sees it, so the next XRPL pass thinks
 *      the NFT is new and creates ANOTHER row. Every run. Forever.
 *   2. removeUser($conn, $user, 2) never clears it, so ownership never resets
 *      when the holder sells.
 *   3. removeUsers($conn, 1) DOES zero it -- the Cardano pass clears it and
 *      the Cardano pass cannot put it back, because Koios has never heard of
 *      it. The holder's NFTs disappear on the first nightly run after linking.
 *
 * Found reading the code after the first XRPL tester reported nothing showed
 * up. It is not that bug -- nothing had been written for them at all -- but
 * it would have been the next one, and #3 is silent and total.
 */
function processNFT($conn, $policy_id, $asset_name, $name, $image, $fingerprint, $address, $asset_ids, $nft_owners, $collections, $blockchain_id = 1){
	if(isset($image)){
		// Dank Bit Fix
		if(is_array($image)){
			//$image = $image[0].$image[1];
			$image = implode("", $image);
		}
		// On-chain check for Digi Monks
		if(str_contains($image, "data:image/svg+xml;base64")){
			$ipfs = $image;
		/*
		 * AN ABSOLUTE URL IS STORED WHOLE, not chopped.
		 *
		 * The substr(7) below strips "ipfs://" and is right for every Cardano
		 * and XRPL image this platform has ever written, because nfts.ipfs
		 * held a BARE CID and the image cache built its URL as
		 * gateway . value. Solana's OMEN serves its art from
		 * https://omenati.com, and seven characters off the front of that is
		 * "//omenati.com/art/01998.jpg", fetched as
		 * "https://ipfs.io/ipfs///omenati.com/art/01998.jpg" -- a poisoned
		 * column that looks like data and fails inside a nightly worker.
		 *
		 * This branch is strictly ADDITIVE: no input that reached substr(7)
		 * before and worked takes a different path now. An image that was
		 * already an absolute https URL was being mangled, so the only
		 * behaviour that changes is behaviour that was broken.
		 *
		 * The two readers were taught the same distinction: getIPFS() in
		 * db.php and _doCacheFetch() in lib/image-cache-lib.php. Those three
		 * are the only places that consume this column.
		 */
		}else if(preg_match('~^https?://~i', (string)$image)){
			$ipfs = $image;
		}else{
			$ipfs = substr($image, 7, strlen($image));
		}
	}else{
		$ipfs = "";
	}
	if(isset($_SESSION['userData']['user_id'])){
		$user_id = $_SESSION['userData']['user_id'];
	}else{
		$user_id = getUserId($conn, $address);
	}
	$last_id = 0;
	if(isset($name)){
		// Check if NFT already exists in the database or has been added during verification
		if(in_array($fingerprint, $asset_ids)){
			// Check to see if there is an NFT with no owner in the database
			if(checkAvailableNFT($conn, $fingerprint)){
				// Limit update to 1 record and only for NFTs with no current owner
				updateNFT($conn, $fingerprint, $user_id);
				$nft_owners[] = $user_id."-".$fingerprint;
			// If someone already has ownership — Diamond Skull and delegated NFTs use
			// force-update (never zeroed by removeUsers to avoid circular dependency).
			// All other NFTs with existing owners are RFTs and get a new DB entry.
			}else{
				$fp_esc = $conn->real_escape_string($fingerprint);
				$is_protected = (isset($collections[$policy_id]) && $collections[$policy_id] == 16)
					|| ($conn->query("SELECT 1 FROM nfts n JOIN diamond_skulls ds ON ds.nft_id = n.id WHERE n.asset_id = '$fp_esc' LIMIT 1")->num_rows > 0);
				if($is_protected){
					forceUpdateNFT($conn, $fingerprint, $user_id);
					$nft_owners[] = $user_id."-".$fingerprint;
				}else{
					//$collection_id = getCollectionId($conn, $policy_id);
					$last_id = createNFT($conn, $fingerprint, $asset_name, $name, $ipfs, $collections[$policy_id], $user_id, $blockchain_id);
					$asset_ids[$last_id] = $fingerprint;
					$nft_owners[] = $user_id."-".$fingerprint;
				}
			}
		}else{
			//$collection_id = getCollectionId($conn, $policy_id);
			$last_id = createNFT($conn, $fingerprint, $asset_name, $name, $ipfs, $collections[$policy_id], $user_id, $blockchain_id);
			$asset_ids[$last_id] = $fingerprint;
			$nft_owners[] = $user_id."-".$fingerprint;
		}
	}
	// Return altered asset ids to ensure new NFTs created are included in the array
	$payload = array();
	$payload["asset_ids"] = $asset_ids;
	$payload["nft_owners"] = $nft_owners;
	return $payload;
}

function hex2str($hex) {
    $str = '';
    for($i=0;$i<strlen($hex);$i+=2) $str .= chr(hexdec(substr($hex,$i,2)));
    return $str;
}

function clean($string) {
   $string = preg_replace('/[^A-Za-z0-9. -]/', '', $string); // Removes special chars.

   return $string;
}
?>