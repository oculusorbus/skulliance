<?php
include 'db.php';
include 'dropship.php';
include 'webhooks.php';           // $bot_token -- must precede role.php's use of it
require_once 'role.php';          // dropshipMemberHasRole(), for the VIP recheck below
require_once 'vip-links.php';     // oculusVipLinks(), for the Play gate below
include 'header.php';

/*
 * THE ROLES SNAPSHOT GOES STALE, AND THIS ONE GATES A GAME.
 *
 * $vip comes from $_SESSION['userData']['roles'], which process-oauth.php
 * fills ONCE at login from four sequential Discord calls whose failures
 * getUsersGuildsRoles() silently ignores. A role granted after sign-in --
 * or lost to a single rate-limited call -- does not exist to this page.
 * Confirmed from a real account: holding the VIP role and the token,
 * refused by the gate, and logging out and back in fixed it.
 *
 * So when the snapshot says no, ask Discord. Only then: a player the
 * snapshot already approves never costs an API call. Once every five
 * minutes at most, because this runs on every dashboard load.
 *
 * IT ONLY EVER GRANTS. dropshipMemberHasRole() returns null when Discord
 * could not be asked, which is not the same as "no", and even a definite
 * false is ignored here -- an outage must never lock out someone who was
 * already playing.
 */
if ($vip !== 'true'
    && isset($_SESSION['userData']['dropship_project_id'])
    && $_SESSION['userData']['dropship_project_id'] == 4
    && !empty($_SESSION['userData']['discord_id'])) {

	$ds_now  = time();
	$ds_last = isset($_SESSION['userData']['vip_recheck_at'])
	         ? (int)$_SESSION['userData']['vip_recheck_at'] : 0;
	if ($ds_now - $ds_last >= 300) {
		$_SESSION['userData']['vip_recheck_at'] = $ds_now;
		if (dropshipMemberHasRole($_SESSION['userData']['discord_id'], '966399108011163678') === true) {
			$_SESSION['userData']['VIP'] = true;
		}
	}
	if (!empty($_SESSION['userData']['VIP'])) $vip = 'true';
}

// Handle post actions at the top of the page to ensure data is accurate before rendering HTML
// Soldier allocation
if(isset($_POST['soldier_id'])) {
	if($_POST['deploy'] == 1){
		deploySoldier($conn, $_POST['soldier_id'], 1);
	}else{
		deploySoldier($conn, $_POST['soldier_id'], 0);
	}
}
/*
 * SAVED SQUADS. Up here with the other post actions, and for the same
 * reason the comment above gives: every one of these changes what the
 * barracks is about to render, so it has to happen before a card is drawn.
 *
 * ONE MESSAGE, SHOWN WHERE THE SQUADS ARE. Each action returns (ok,
 * message) rather than echoing -- these run before <html>, so anything
 * printed here lands above the doctype.
 */
$ds_squad_msg = null;
/*
 * SAVE, DISPERSE AND REMOVE ARE GONE. Squads are built by
 * dropshipSquadsAutoSync() from what the player owns, so there is nothing
 * to assemble and nothing to take apart -- and a disperse would simply be
 * undone by the engine on the next load. What is left is the two things
 * that are still the player's: field a squad, and clear the field.
 */
if (isset($_POST['squad_deploy'])) {
	$ds_squad_msg = dropshipSquadDeploy($conn, $_POST['squad_deploy']);
} elseif (isset($_POST['squad_clear'])) {
	$ds_squad_msg = dropshipWithdrawAll($conn);
}

//Item purchases
if(isset($_POST['item_id'])) {
	// Disable purchasing of items if the game has already been played. This is a fallback error in case someone tried to hack the HTML.
	if(!isset($_SESSION['userData']['current_score']) || checkSquadCount($conn) > 0){
		// Buy item and pass false flag since it's not a Drop Box transaction
		buyItem($conn, $_POST['item_id'], false);
	}else{
		?><script type="text/javascript">alert("You've already played Drop Ship. You cannot purchase items until a new game is live.");</script><?php
	}
} 
// Activate / Deactivate games
if($_SESSION['userData']['discord_id'] == "772831523899965440") {
	if(isset($_POST['deactivate']) && isset($_SESSION['userData']['game_id'])) {			
		$title = "Game Over";
		/* Same capture, same reason: anything PHP prints while the buffer
		   is open would be posted into Discord with the leaderboard. */
		$description = dropshipCapture(function () use ($conn) { checkLeaderboard($conn, "true"); });
		$imageurl = dropshipThemeImageUrl($conn, $_SESSION['userData']['dropship_project_id'], 'images/dropship.jpg');
		discordmsg($title, evaluateText($description), $imageurl, $_SESSION['userData']['dropship_project_id']);
		deactivateGame($conn);
	}
	if(isset($_POST['newgame']) && !isset($_SESSION['userData']['game_id'])) {
		createGame($conn, $_POST['name'], $_POST['prizes']);
		$title = "New Game ".$_POST['name'];
		$description = "A new game of ".getProjectName($conn)." has been created.";
		$imageurl = dropshipThemeImageUrl($conn, $_SESSION['userData']['dropship_project_id'], 'images/dropship.jpg');
		discordmsg($title, $description, $imageurl, $_SESSION['userData']['dropship_project_id']);
	}
}

function renderNFT($nft_data, $ipfs){
	echo "<div class='nft'><div class='nft-data'>
	<span class='nft-name'>".$nft_data->name."</span>";
//	echo "<span class='nft-image'><img src='https://cloudflare-ipfs.com/ipfs/".$ipfs."'/></span>";
	echo "<span class='nft-image'><img src='https://image-optimizer.jpgstoreapis.com/".$ipfs."'/></span>";
	/*
	<span class='nft-rank'><strong>Rank: </strong>".$nft_data->Rank."</span>
	<span class='nft-armor'><strong>Armor: </strong>".$nft_data->Armor."</span>
	<span class='nft-gear'><strong>Gear: </strong>".$nft_data->Gear."</span>
	<span class='nft-level'><strong>Level: </strong>".$nft_data->Level."</span><br><br>*/
	echo "</div></div>";
}

function storeRank($ranks){
	if(isset($ranks)){
		if(isset($ranks["VIP"])){
			$_SESSION['userData']['rank'] = "VIP";
		}else if(isset($ranks["Mayor"])){
			$_SESSION['userData']['rank'] = "Mayor";
		}else if(isset($ranks["Don"])){
			$_SESSION['userData']['rank'] = "Don";
		}else if(isset($ranks["Capo"])){
			$_SESSION['userData']['rank'] = "Capo";
		}else if(isset($ranks["VIP"])){
			$_SESSION['userData']['rank'] = "VIP";
		}else if(isset($ranks["Patron"])){
			$_SESSION['userData']['rank'] = "Patron";
		}else if(isset($ranks["Henchmen"])){
			$_SESSION['userData']['rank'] = "Henchmen";
		}else if(isset($ranks["Command Sergeant Major"])){
			$_SESSION['userData']['rank'] = "Command Sergeant Major";
		}else if(isset($ranks["Sergeant Major"])){
			$_SESSION['userData']['rank'] = "Sergeant Major";
		}else if(isset($ranks["First Sergeant"])){
			$_SESSION['userData']['rank'] = "First Sergeant";
		}else if(isset($ranks["Master Sergeant"])){
			$_SESSION['userData']['rank'] = "Master Sergeant";
		}else if(isset($ranks["Sergeant First Class"])){
			$_SESSION['userData']['rank'] = "Sergeant First Class";
		}else if(isset($ranks["Staff Sergeant"])){
			$_SESSION['userData']['rank'] = "Staff Sergeant";
		}else if(isset($ranks["Sergeant"])){
			$_SESSION['userData']['rank'] = "Sergeant";
		}else if(isset($ranks["Corporal"])){
			$_SESSION['userData']['rank'] = "Corporal";
		}else if(isset($ranks["Specialist"])){
			$_SESSION['userData']['rank'] = "Specialist";
		}else if(isset($ranks["Private"])){
			$_SESSION['userData']['rank'] = "Private";
		}
	}
}

$policy_id = getProjectPolicyId($conn);
$ranks = array();

// Wallet connection is Skulliance's job now, not Drop Ship's -- pulls every
// stake address already connected to this player's Skulliance account
// instead of running its own separate browser-extension connect flow (see
// dropshipConnectedStakeAddresses()'s own comment in db.php). Keyed by a
// joined string, not the raw array, so the "did anything actually change"
// check below is a simple string compare -- same shape the old single-
// address flow used, just covering a settable now instead of one value.
$addresses = dropshipConnectedStakeAddresses($_SESSION['userData']['discord_id']);
$addresses_key = implode(',', $addresses);

$address_changed = "false";
if(!isset($_SESSION['userData']['addresses_key']) || $_SESSION['userData']['addresses_key'] !== $addresses_key){
	$address_changed = "true";
	$_SESSION['userData']['addresses_key'] = $addresses_key;
}
$_SESSION['userData']['addresses'] = $addresses;

// WHICH PIPELINE LOADS A PLAYER'S NFTs IS A THEME SETTING, not a project id
// written in here. 'skulliance' skips the wallet/Koios round trip entirely
// and reads holdings out of Skulliance's own already-synced `nfts` table
// (see dropshipSyncFromSkulliance()'s comment in db.php for why Oculus
// Lounge changed to it); 'koios' is the live wallet read every other theme
// uses. This was `== 4`, so a second theme wanting the same pipeline meant
// editing this line.
//
// Either way it stays gated on the same $address_changed/$project_id_changed
// trigger: a wallet-set change is still a reasonable signal to re-check,
// since Skulliance's nfts.user_id reflects whichever wallets are connected
// to that account -- the same wallets $addresses is built from.
/*
 * CHANGING THE TRAIT SOURCE HAS TO COUNT AS A CHANGE.
 *
 * The sync only ever fired on a wallet-set change or a project switch, so
 * flipping a theme between Koios and Skulliance in the admin and reloading
 * did nothing at all -- the setting was saved, the page looked right, and
 * the barracks stayed empty, which reads as "that option does not work
 * either". Remembering which source last ran for this project makes the
 * switch itself a trigger. Also fires once after deploy, which is harmless
 * and is exactly what a theme that has never synced needs.
 */
$ds_source = dropshipThemeTraitSource($conn, $_SESSION['userData']['dropship_project_id']);
$ds_source_key = $_SESSION['userData']['dropship_project_id'] . ':' . $ds_source;
$source_changed = (!isset($_SESSION['userData']['trait_source_key'])
                   || $_SESSION['userData']['trait_source_key'] !== $ds_source_key) ? "true" : "false";
$_SESSION['userData']['trait_source_key'] = $ds_source_key;

if($ds_source === 'skulliance'){
	if(($address_changed == "true") || $project_id_changed == "true" || $source_changed == "true"){
		dropshipSyncFromSkulliance($conn, $_SESSION['userData']['discord_id'], $policy_id);
		$ranks["VIP"] = true;
		$_SESSION['userData']['rank'] = "";
		storeRank($ranks);
	}
}elseif(($address_changed == "true") || $project_id_changed == "true" || $source_changed == "true"){
	if(!empty($addresses)){
		$ch = curl_init("https://api.koios.rest/api/v1/account_utxos?select=asset_list&asset_list=not.is.null");
		// koios_headers(): the token this file carried expired 2024-12-20 and
		// Koios answers an expired subscription with 403 and a sentence, which
		// json_decode() turns into null and is_array() then drops -- so this
		// whole block has quietly done nothing since. The rest of the platform
		// had already moved to a current token; Drop Ship kept the old one
		// through the migration. One copy now, in lib/koios.php.
		curl_setopt( $ch, CURLOPT_HTTPHEADER, koios_headers());
		curl_setopt( $ch, CURLOPT_POST, 1);
		// All connected wallets in one call -- Koios' account_utxos already
		// accepts multiple _stake_addresses, so this is the exact same
		// request shape as before, just with N addresses instead of 1. Every
		// asset from every wallet flows into the same $response the code
		// below already loops over per-account, so no downstream logic
		// (soldier creation, rank counting) needed to change at all.
		// FILTERED, because one malformed row kills the whole batch: Koios
		// answers the entire request with HTTP 500 ("Failed to decode Bech32
		// string") if any single address is bad, and the 500 body is not
		// JSON so is_array() below drops it without a word. Ten wallets, one
		// bad row, nothing loads. Batching every wallet into one call is
		// what turned a one-wallet problem into an all-wallets one.
		curl_setopt( $ch, CURLOPT_POSTFIELDS, json_encode(array("_stake_addresses" => koios_filter_stake_addresses($addresses), "_extended" => true)));
		curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt( $ch, CURLOPT_HEADER, 0);
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);
		// Bounded: an unbounded wallet read holds the whole page render open.
		koios_timeouts($ch);

		$response = curl_exec( $ch );
		// If you need to debug, or find out why you can't send message uncomment line below, and execute script.
		$response = json_decode($response);
		//print_r($response[0]->asset_list);
		//exit;
		curl_close( $ch );
		
		$_SESSION['userData']['nfts'] = array();
		if(is_array($response)){
	    if(isset($response[0])){
			$asset_names = array();
			$counter = 0;
			$asset_list = array();
			$asset_list["_asset_list"] = array();
			foreach($response AS $index => $list){
				foreach($list->asset_list AS $index => $token){
					if($token->policy_id == $policy_id){
						$asset_list["_asset_list"][$counter] = array();
						$asset_list["_asset_list"][$counter][0] = $token->policy_id;
						$asset_list["_asset_list"][$counter][1] = $token->asset_name;
						$counter++;
					
					} // End if
				} // End foreach
			}
			
			$tokench = curl_init("https://api.koios.rest/api/v1/asset_info");
			curl_setopt( $tokench, CURLOPT_HTTPHEADER, koios_headers());
			curl_setopt( $tokench, CURLOPT_POST, 1);
			curl_setopt( $tokench, CURLOPT_POSTFIELDS, json_encode($asset_list));
			curl_setopt( $tokench, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt( $tokench, CURLOPT_HEADER, 0);
			curl_setopt( $tokench, CURLOPT_RETURNTRANSFER, 1);
			koios_timeouts($tokench);
			
			$tokenresponse = curl_exec( $tokench );
			$tokenresponse = json_decode($tokenresponse);
			curl_close( $tokench );
			
			if(is_array($tokenresponse)){
				foreach($tokenresponse AS $index => $tokenresponsedata){		
				if(isset($tokenresponsedata->minting_tx_metadata)){
					foreach($tokenresponsedata->minting_tx_metadata AS $metadata){
						$counter++;
						$policy_id = $tokenresponsedata->policy_id;
						$asset_name = $tokenresponsedata->asset_name_ascii;
						$nfts = $metadata->$policy_id;
						foreach($nfts AS $nft){
							$nft_data = $nft;
							$ipfs = substr($nft_data->image, 7, strlen($nft_data->image));
							// Was: $ranks[$nft_data->Rank] = true -- the on-chain
							// Rank attribute (e.g. "Common"/"Legendary") never
							// matched any of storeRank()'s tier names, so this
							// never actually set a rank. Tier is by NFT COUNT
							// now, assigned below alongside project 2/4's own
							// count-based tiers, same pattern.
							//$armor[$nft_data->Armor] = true;
							//$gear[$nft_data->Gear] = true;
							// Account for NFT with NaN value for asset name
							if($asset_name == "NaN"){
								$nft_data->AssetName = "DROPSHIP012";
							}else{
								$nft_data->AssetName = $asset_name;
							}
							//renderNFT($nft_data, $ipfs);
							/* Removing to rely on database now
							if($_SESSION['userData']['dropship_project_id'] != 1){
								$_SESSION['userData']['nfts'][] = $nft_data;
							}*/
							$asset_names[] = $nft_data->AssetName;
							if(!checkSoldier($conn, $nft_data->AssetName)){
								// Soldier doesn't exist, create soldier
								if($_SESSION['userData']['dropship_project_id'] != 1){
									$armor_weight["Base"] = 0;
									$armor_weight["Light"] = 2;
									$armor_weight["Medium"] = 4;
									$armor_weight["Heavy"] = 6;
									$gear_weight["None"] = 1;
									$gear_weight["Melee"] = 2;
									$gear_weight["Demolition"] = 3;
									$gear_weight["Medkit"] = 4;
									$armor = array("Heavy", "Medium", "Light", "Base");
									$gear = array("None", "Melee", "Demolition", "Medkit");
									$armor_random = rand(0,3);
									$gear_random = rand(0,3);
									$armor_final = $armor[$armor_random];
									$gear_final = $gear[$gear_random];
									$level = $armor_weight[$armor_final] + $gear_weight[$gear_final];
									if(!isset($nft_data->summary)){
										$nft_data->summary = null;
									}
									/*
									 * ONE NEUTRAL RANK, THEMED LIKE EVERY OTHER WORD.
									 *
									 * This was a project-id ladder -- 2 and 3 got "Henchmen",
									 * 4 got "Neo Miami Citizen" -- which is the fourth of
									 * these we have pulled out, and the worst behaved: a
									 * project not on the list left $rank UNDEFINED, so a new
									 * theme got whatever the previous loop iteration held, or
									 * a warning printed into the page.
									 *
									 * Worse than untidy, it made one theme's vocabulary the
									 * base string every other theme had to override. Oculus
									 * Lounge's word is Oculus Lounge's; the canonical is the
									 * game's own noun, and a theme renames it in the admin
									 * exactly like SCRIP or Medkit.
									 */
									$rank = DROPSHIP_DEFAULT_RANK;
									createSoldier($conn, $nft_data->AssetName, $nft_data->name, $nft_data->summary, $rank, $armor_final, $gear_final, $level, $ipfs);
								}
							} // End if
						} // End foreach
					} // End foreach
				}// End if
				} // End foreach
				// Project 1's own rank tiers are computed unconditionally
				// further down (dropshipSoldierCount()), not here -- see
				// that function's own comment for why this Koios-gated
				// block is the wrong place for it.
				if($_SESSION['userData']['dropship_project_id'] == 2){
					if($counter >= 40){
						$ranks["Mayor"] = true;
					}else if($counter >= 30){
						$ranks["Don"] = true;
					}else if($counter >= 20){
						$ranks["Capo"] = true;
					}else if($counter >= 10){
						$ranks["VIP"] = true;
					}else if($counter >= 5){
						$ranks["Patron"] = true;
					}else if($counter >= 1){
						$ranks["Henchmen"] = true;
					}
				}else if($_SESSION['userData']['dropship_project_id'] == 4){
					$ranks["VIP"] = true;
				}

			} // End if
			updateSoldiers($conn, implode("', '", $asset_names));
			//getSoldiers($conn, 0);
		} // End if
		} // End Outer if
		$_SESSION['userData']['rank'] = "";
		storeRank($ranks);
	}
// If the connected wallet set didn't change, load NFT data from the
// database to reduce loading times
}else if(!empty($addresses) && $address_changed == "false"){
	//getSoldiers($conn, 0);
	// Old session stuff
	/*
	foreach($_SESSION['userData']['nfts'] AS $index => $nft_data){
		$ipfs = substr($nft_data->image, 7, strlen($nft_data->image));
		//renderNFT($nft_data, $ipfs);
	}*/
}

/*
 * SQUADS LOOK AFTER THEMSELVES FROM HERE.
 *
 * Placed after the holdings sync above and before anything is drawn,
 * because this is the first moment the soldiers table is current: run it
 * earlier and a freshly bought NFT would not be in a squad until the next
 * page load.
 *
 * ONLY THE GROUPING IS AUTOMATIC. Deploying, buying and running are still
 * the player's, and this touches none of them -- it writes squads and
 * squad_members and nothing else. soldiers.active is not read or written,
 * so a squad in the field stays in the field.
 *
 * IT USUALLY DOES NOTHING. The arrangement is current exactly when every
 * owned soldier sits in one squad, which is one SELECT to establish; the
 * rebuild only runs when an NFT has arrived or gone.
 */
if (function_exists('dropshipSquadsAutoSync')) dropshipSquadsAutoSync($conn);

// Assign default soldiers to players for Drop Ship
if($_SESSION['userData']['dropship_project_id'] == 1 && isset($_SESSION['userData']['dropship_user_id'])){
	if(!checkDefaultSoldiers($conn)){
		addDefaultSoldiers($conn);
	}
}

// Drop Ship's own rank tier (Private...Command Sergeant Major), by total
// NFTs held. Deliberately unconditional -- runs every page load, not just
// when dropshipConnectedStakeAddresses()'s address set changed. See
// dropshipSoldierCount()'s own comment in db.php: keying this off the
// Koios-gated $counter meant a session that had already cached
// "unchanged" addresses before this logic existed would never recompute
// it at all, leaving the badge stuck hidden/empty. A plain COUNT against
// the persisted soldiers table has no such staleness problem.
if($_SESSION['userData']['dropship_project_id'] == 1 && isset($_SESSION['userData']['dropship_user_id'])){
	$dropship_soldier_count = dropshipSoldierCount($conn);
	$dropship_ranks = array();
	if($dropship_soldier_count >= 55){
		$dropship_ranks["Command Sergeant Major"] = true;
	}else if($dropship_soldier_count >= 40){
		$dropship_ranks["Sergeant Major"] = true;
	}else if($dropship_soldier_count >= 30){
		$dropship_ranks["First Sergeant"] = true;
	}else if($dropship_soldier_count >= 23){
		$dropship_ranks["Master Sergeant"] = true;
	}else if($dropship_soldier_count >= 17){
		$dropship_ranks["Sergeant First Class"] = true;
	}else if($dropship_soldier_count >= 12){
		$dropship_ranks["Staff Sergeant"] = true;
	}else if($dropship_soldier_count >= 8){
		$dropship_ranks["Sergeant"] = true;
	}else if($dropship_soldier_count >= 5){
		$dropship_ranks["Corporal"] = true;
	}else if($dropship_soldier_count >= 3){
		$dropship_ranks["Specialist"] = true;
	}else if($dropship_soldier_count >= 1){
		$dropship_ranks["Private"] = true;
	}
	$_SESSION['userData']['rank'] = "";
	storeRank($dropship_ranks);
}

/*
 * THE SAME WORD MAPPING AS EVERY OTHER LABEL ON THE PAGE.
 *
 * These three were a hardcoded chain on project id, and themes
 * carried currency/squad_label/character_label columns that duplicated it
 * -- a theme that renamed SCRIP in its wording still read "SCRIP" here
 * unless somebody also filled in the column, and nothing in the admin said
 * so. Two places to change one word is one place too many, so the columns
 * are gone and these go through evaluateText() like everything else.
 * A project with no theme gets the Drop Ship word back, which is what the
 * chain's else branch did.
 */
$currency   = evaluateText("SCRIP");
$squad      = evaluateText("Super Soldier");
$characters = evaluateText("Troops");
?>
		<a name="dashboard" id="dashboard"></a>
		<!-- The flexible grid (content) -->
		<div class="row" id="row1">
		  <div class="main">
		    <div class="content">
				<!--<h1>Doc Oc & Ohh Meed's Drop Ship</h1>-->
			    <!-- Display results of Drop Ship run to user -->
				<?php 
				/*
				 * KEN BURNS ON STILLS ONLY. A gif animates itself and a pan
				 * laid over it just fights it; a png needs the movement.
				 *
				 * This keyed off project 4, which was right only by
				 * coincidence -- that is the theme that happens to use
				 * stills. It follows the theme's media extension now, so a
				 * new png theme gets the pan and a gif one never does.
				 */
				$image_wrap = "";
				if((isset($_POST['run']) || isset($_POST['instant_replay']))
				   && function_exists('dropshipMediaIsStill') && dropshipMediaIsStill()) {
					$image_wrap = "class='image-wrap'";
				}?>
				<div id="results" <?php echo $image_wrap;?>>
					<div id="results-image"></div>
					<?php if(isset($_SESSION['userData']['current_score']) && checkSquadCount($conn) < 1) { ?>
						<?php
						/*
						 * THE GAME OVER SCREEN FOLLOWS THE THEME, like every
						 * other frame. This was hardcoded images/gameover.gif,
						 * which is why Dread City -- a jpg theme -- showed an
						 * animated game over with animation switched off, and
						 * why the animation it showed was Drop Ship's: that
						 * file is byte-for-byte the one in dropship/images/.
						 *
						 * dropshipMediaExtension() is the same call the rest of
						 * the run makes, so the player's animation switch moves
						 * this with everything else. It falls back to the gif
						 * while a theme has no still, because right now none of
						 * them do; theme art lists the still as missing until
						 * one is uploaded.
						 */
						$ds_over = dropshipThemeArtFile($slug, 'images/gameover', dropshipMediaExtension());
						if ($ds_over !== ''): ?>
						<script>document.getElementById("results-image").style.backgroundImage = "url('<?php echo $prefix . $ds_over; ?>')";</script>
						<?php endif; ?>
						<!-- The Discord announcement used to live here; it is above now,
						     so that it fires on the run rather than on whatever request
						     happens to render this panel. -->
					<?php } else { ?>
						<?php if(isset($_POST['run'])) { ?>
							<script>document.getElementById("results-image").style.backgroundImage = "url('<?php echo $prefix; ?>images/reset/intro.jpg')";</script>
						<?php } else { ?>
							<script>document.getElementById("results-image").style.backgroundImage = "url('<?php echo $prefix; ?>images/dropship.jpg')";</script>
							<!--<img class="rounded" src='dropship.jpg'/>-->
						<?php } ?>
					<?php } ?>
				</div>
				
				<?php
				/*
				 * THE SOUND OVER THE TITLE CARD, which is a slot like any
				 * other now.
				 *
				 * This was a project_id ladder -- 1 and 2 loading, 3
				 * splash, 4 laughing -- autoplayed by audio2 as the
				 * results pane renders, which is exactly when
				 * images/reset/intro.jpg is on screen. Nothing in the
				 * audio registry knew about it, so that picture was the
				 * one card in THE DROP with no sound row while every
				 * other had one, and the page said so wrongly.
				 */
				$ds_intro = dropshipAudioSlotByArt($conn,
					$_SESSION["userData"]["dropship_project_id"], 'reset/intro');
				$audio2 = ($ds_intro && $ds_intro['plays'] !== '')
				        ? $ds_intro['plays'] : '';
				$muted = "";
				if(isset($_SESSION['userData']['audio']) && $_SESSION['userData']['audio'] == "false"){
					$muted = "muted";
				}
				// Set results display variable
				$view_results = '
				<br>
				<button id="viewResults" class="button" onclick="displayRound('.$_SESSION["userData"]["dropship_project_id"].');" type="button">Next Round</button><br><br>
				<div id="disableMessage" style="display:none">false</div>
				<div id="resultsText"></div>
				<br>
				<audio '.$muted.' id="audio1" controls onloadstart="this.volume=0.75" style="display:none">
				  <source id="audioSource1" src="sounds/static.mp3" type="audio/mpeg">
				Your browser does not support the audio element.
				</audio>
				<audio '.$muted.' id="audio2" controls autoplay style="display:none">
				  <source id="audioSource2" src="'.($audio2 !== '' ? dropshipAudioUrl($audio2) : '').'" type="audio/mpeg">
				Your browser does not support the audio element.
				</audio>';
				?>
				
				
				<?php 
				if(!isset($_POST['instant_replay'])){
					// Display button to play Drop Ship
					/*
					echo "Post Run: ".$_POST['run']."<br>";
					echo "Current Score: ".$_SESSION['userData']['current_score']."<br>";
					echo "Squad Count: ".checkSquadCount($conn)."<br>";
					echo "Game ID: ".$_SESSION['userData']['game_id']."<br>";
					echo "Battle ID: ".$_SESSION['userData']['battle_id']."<br>";*/
					// FALSE AND FALSE OR TRUE OR FALSE
					if(!isset($_POST['run']) && !isset($_SESSION['userData']['current_score']) || (checkSquadCount($conn) >= 1 && isset($_SESSION['userData']['game_id']) && !isset($_SESSION['userData']['battle_id'])) || (isset($_SESSION['userData']['battle_id']) && !isset($_POST['run'])) ) { ?>
						<br>
						<?php
						$battle = "";
						if(isset($_SESSION['userData']['battle_id'])){
							$battle = " Battle";
						}
						$play_button = '<form action="dashboard.php" method="post">
						  <input type="hidden" id="run" name="run" value="true">
						  <input class="button" type="submit" value="Play'.$battle.'">
						</form>';
						?>
						<?php if(checkSquadCount($conn) >= 1) { ?>
							<?php
							/*
							 * ONE QUALIFYING NFT IS THE WHOLE GATE, bar Oculus
							 * Lounge's Discord role.
							 *
							 * Filthy Mermaid was refused outright and the copy
							 * did not describe the code: holding the Patron role
							 * (5+ NFTs) got "Cannot Play Until Fully Developed"
							 * unless you were one of two hardcoded Discord ids,
							 * and everyone else got "You Must Have 5+ NFTs to
							 * Play" -- a number that would not have let anybody
							 * in either, because no branch reached the play
							 * button. Per the owner: the Shorty Verse NFTs are
							 * expensive, getting one is already the hard part,
							 * so anybody who has one plays.
							 *
							 * checkSquadCount() above IS that test. A soldier
							 * exists only because a qualifying NFT was synced
							 * off the wallet, so an assembled squad is proof of
							 * holding, and Filthy Mermaid now gates exactly the
							 * way Drop Ship and Dread City do.
							 */
							if($_SESSION['userData']['dropship_project_id'] == 4 && $vip == "true"){
								echo '<form action="dashboard.php" method="post">
								  <input type="hidden" id="run" name="run" value="true">
								  <input class="button" type="submit" value="Play XXX Game'.$battle.'">
								</form>';
							}else if($_SESSION['userData']['dropship_project_id'] == 4){
									// The refusal used to be one link to the marketplace, which
									// leaves out the step the gate actually tests: the Discord
									// role. Someone holding the token was refused here and had no
									// way to find out why. See dropship/vip-links.php.
									echo oculusVipLinks('<strong>You need VIP access to play.</strong>');
									echo "<p><a href='discoin.php'>Or buy a temporary VIP pass with DISCOIN</a></p>";
							}else{
								echo $play_button;
 							} ?>
						<?php 
						} else {
							echo "<p><strong>Assemble Squad of ".$characters." to Play</strong></p>";
							/*
							 * WHERE TO GET ONE, but only for somebody who has
							 * none. This refusal also catches a player holding a
							 * squad they simply have not deployed yet, and
							 * "here is where to buy NFTs" is the wrong answer to
							 * that -- their NFTs are three clicks away on this
							 * same page. dropshipSoldierCount() is holdings,
							 * checkSquadCount() is deployment.
							 */
							if(dropshipSoldierCount($conn) < 1){
								echo dropshipCollectionLink($conn, $_SESSION['userData']['dropship_project_id']);
							}
						}?>
					<?php
					// Display game status or Drop Ship Results
					} else if((!isset($_SESSION['userData']['current_score']) || checkSquadCount($conn) >= 1) || isset($_SESSION['userData']['battle_id'])) { 
						if(!isset($_SESSION['userData']['game_id']) && !isset($_SESSION['userData']['battle_id'])) {
							?>
							<script>document.getElementById("results-image").style.backgroundImage = "url('<?php echo $prefix; ?>images/dropship.jpg')";</script>
							<script>
							var videoContent = "";
							var video = "";
							if(Math.floor(Math.random() * 3) == 0){
								video = "easteregg";
							}else{
								video = "dropship";
							}
							videoContent ='<video <?php echo $muted; ?> id="dropshipPromoVideo" onloadstart="this.volume=0.15" autoplay controls><source src="videos/'+video+'.mp4" type="video/mp4">Your browser does not support the video tag.</video>';
							document.getElementById("results").innerHTML = videoContent;
							document.getElementById("dropshipPromoVideo").style.height = (document.getElementById("results").offsetHeight-10)+"px";
							</script>
							<?php
							echo "<h3>No active game.</h3>";
						} else { 
							$hideLeaderboard = "true"; 
							// Unset notification flag
							unset($_SESSION['userData']['notification']);
							echo $view_results;
							// If battle id is present, unset it since the player is now viewing their battle results and disable send results to discord so that it displays refresh
							if(isset($_SESSION['userData']['battle_id'])){
								unset($_SESSION['userData']['battle_id']);
								echo "<script type='text/javascript'>document.getElementById('disableMessage').innerHTML = 'true';</script>";
							}
						} 
					} 
				}
				?>
				
				<?php if(isset($_POST['instant_replay'])) { 
					?><script>document.getElementById("results-image").style.backgroundImage = "url('<?php echo $prefix; ?>images/reset/intro.jpg')";</script><?php
					// Do not display instant replay button if play button is rendered
					echo $view_results;
					echo "<script type='text/javascript'>document.getElementById('disableMessage').innerHTML = 'true';</script>";
					$dropshipMarkup = getReplay($conn, $_POST['result_id']);
					/* 3D RETIRED. A stored replay used to have its extension
					   rewritten here to match a per-player toggle, with a
					   blanket str_replace over the whole markup -- which also
					   caught the word anywhere else in it. The extension is a
					   property of the theme now (themes.extension),
					   so a replay is served exactly as it was recorded. */
				}  
				if(!isset($_POST['instant_replay']) && isset($_SESSION['userData']['current_score']) && isset($_SESSION['userData']['game_id']) && !isset($_POST['deactivate']) && !isset($_POST['newgame'])){
					// Old instant replay by user id only, disabled until or if this can be figured out with result id.
					echo "<br>";
					instantReplayButton(checkMaxScoreResultID($conn, $_SESSION['userData']['dropship_user_id'], $_SESSION['userData']['current_score']), false);
				}
				?>
				
				<!-- Hidden Drop Ship results that are manipulated with Javascript -->
		        <div id="hidden-results" style="display:none;">
					<?php echo $dropshipMarkup; ?>
				</div>
		    </div>
		  </div>
		  <?php 
			// Load game id and current score that would typically happen when leaderboards loaded before that was moved to another page.
			checkGame($conn);
			if(isset($_SESSION['userData']['game_id'])){
				checkScore($conn);
			}
		  ?>

		  <div class="side">
			<?php $data = array();
				if(isset($_SESSION['userData']['dropship_user_id'])){
					$data = checkXP($conn, $_SESSION['userData']['dropship_user_id']);
					if(!empty($data)){
						$_SESSION['userData']['level'] = $data['level'];
					}
				}
			?>
			<h2><?php echo getProjectName($conn); ?></h2>
			<div class="content" id="player-stats">
				<ul>
					<!-- No more "Connect a wallet" here -- Skulliance's own
					     connected wallet(s) are used automatically now, see
					     dropshipConnectedStakeAddresses() in db.php. -->
					<?php
					$projects = getProjects($conn);
					?>
					<?php
					/*
					 * SELECT GAME, not a dropdown.
					 *
					 * This was a <select> labelled "Select Project": two clicks
					 * to reach a list of four words, and a word is the worst way
					 * to tell somebody Dread City is not the game they just
					 * played. The button opens a picker showing each game's own
					 * art -- the same two clicks, for something you recognise
					 * rather than read.
					 *
					 * "Game" and not "Theme" deliberately. They share an engine,
					 * but they are four different games to a player, and a
					 * control called Change Theme invites the assumption that it
					 * is a reskin of the one they have already finished.
					 *
					 * Only when there is more than one to pick. The picker
					 * itself is at the foot of this page: it has to be a child
					 * of <body>, because this panel is inside .side and would
					 * clip a fixed overlay.
					 */
					if (count($projects) > 1): ?>
					<div class="wallet-connect select-project">
					<li class="role"><img class="icon" src="icons/cardano.png"/>
						<button type="button" class="small-button" id="dsgame-open"
						        aria-haspopup="dialog">Select Game</button>
					</li>
					</div>
					<?php endif; ?>
					<?php
					$checked = "checked";
					$audio = "on";
					if(isset($_SESSION['userData']['audio'])){
						if($_SESSION['userData']['audio'] == "true"){
							$checked = "checked";
							$audio = "on";
						}else{
							$checked = "";
							$audio = "off";
						}
					}else{
						$checked = "checked";
						$audio = "on";
					}
					?>
					<li class="role"><img class="icon" id="audio-icon" src="icons/audio-<?php echo $audio;?>.png"/>Audio
						&nbsp;
						<!-- Rounded switch -->
						<label class="switch">
						  <input onchange="javascript:toggleAudio(this.checked);" type="checkbox" <?php echo $checked; ?>>
						  <span class="slider rounded"></span>
						</label>
					</li>
					<?php
/*
 * ANIMATION, for a still theme that ships gifs anyway.
 *
 * This is what the 3D toggle actually was. Retiring 3D was right -- it
 * was a second format calling itself a dimension, and it dragged two
 * blanket str_replace passes over stored replay markup with it -- but it
 * also took away the only way to see Oculus Lounge's animated set.
 *
 * SHOWN ONLY WHEN IT WOULD DO SOMETHING: the theme is a still AND the
 * gifs are on disk. A theme already set to gif has nothing to switch, and
 * a still theme with no gifs would switch to 404s.
 */
/* THE THEME'S extension, not the resolved one. Asking the resolved one
   meant the switch vanished the moment it was turned on -- it reports
   'gif' once animation is active -- with no way back to the stills. */
$ds_can_animate = function_exists('dropshipThemeHasGifArt')
	&& dropshipThemeMediaExtension() !== 'gif'
	&& dropshipThemeHasGifArt($slug);
if ($ds_can_animate):
	$anim_on = dropshipAnimationOn($slug);
?>
					<?php /* The 3D toggle's own icon, back where it was -- this
					         control is what that one actually did. */ ?>
					<li class="role"><img class="icon" id="animation-icon" src="icons/3d.png"/>Animation
						&nbsp;
						<label class="switch">
						  <input onchange="javascript:toggleAnimation(this.checked);" type="checkbox"
						         <?php echo $anim_on ? 'checked' : ''; ?>>
						  <span class="slider rounded"></span>
						</label>
					</li>
<?php endif; ?>
					<li class="role"><img class="icon" src="icons/level.png"/>Level <?php echo (!empty($data))?$data['level']:"0"; ?></li>
					<?php if($hideLeaderboard == "false") { 
						if(!empty($data)){
					?>
					<li class="role"><img class="icon" src="icons/xp.png"/><?php echo number_format($data['xp']); ?> / <?php echo number_format(ceil($data['ceiling'])); ?> XP</li>
					<?php 
						}
					} ?>
					<?php if($hideLeaderboard == "false") {
						if(!empty($data)){
					?>
					<li class="role">
					<img class="icon" src="icons/progress.png"/>
					<div class="w3-border">
					  <div class="w3-grey" style="width:<?php echo $data['percentage']; ?>%"></div>
					</div>
					</li>
					<?php 
						 }
					} ?>
					<li class="role"><img class="icon" src="icons/scoreboard.png"/>
						<?php echo "<strong>Games Played:</strong>&nbsp;".getScoreCount($conn); ?>
					</li>
					<?php if($hideLeaderboard == "false") { ?>					
					<li class="role"><img class="icon" src="icons/average.png"/>
						<?php echo "<strong>Average Score:</strong>&nbsp;".getAverageScore($conn); ?>
					</li>
					<?php } ?>
					<?php if($hideLeaderboard == "false") { ?>
					<li class="role"><img class="icon" src="icons/trophy.png"/>
						<?php echo "<strong>Top Score:</strong>&nbsp;".getTopScore($conn); ?>
					</li>
					<?php } ?>
					<?php
					// Scoped to Drop Ship itself (project 1) only, not the
					// other reskins -- Dread City/Filthy Mermaid (2/3) are
					// still a work in progress (not in the theme dropdown
					// yet), and Oculus Lounge (4) doesn't use this ladder at
					// all. Was briefly hard-disabled (`false &&`) while this
					// looked like a dead Discord-role dependency; turned out
					// to be Drop Ship's own military rank ladder that just
					// needed count-based tiers wired up (see the $counter
					// thresholds above) -- real data now, not hidden.
					if(isset($_SESSION["userData"]["rank"]) && $_SESSION["userData"]["rank"] !== "" && $_SESSION["userData"]["dropship_project_id"] == 1){?>
						<li class="role"><img class="icon" src="icons/rank.png"/>
							<?php echo "<strong>".$_SESSION["userData"]["rank"]."</strong>";?>
						</li>
					<?php } ?>
				    <?php echo "<li class='role' id='super-soldier'><img class='icon' src='icons/".evaluateText("supersoldier").".png'/><strong>".evaluateText("Super Soldier")."</strong></li>"; ?>
					<?php $scrip=number_format(checkBalance($conn)); ?>
					<li class="role"><img class="icon" src="icons/scrip.png"/>
						<?php echo $scrip; ?> <?php echo "$".$currency ?>
					</li>
				</ul>
			</div>
		  </div>
		
		</div>
    	
		<?php if($hideLeaderboard == "false") { ?>
		<div class="row">
			<div class="soldiers" id="soldiers-panel">
			<?php if(checkSquadCount($conn) > 0){?>
			    <div class="content" id="squad-panel">
				<h2 id="squad">Squad</h2>
				<?php
				/* WHAT THIS SQUAD IS ACTUALLY WORTH. Drop Ship decides the
				   whole run before you press Run, so this screen is where the
				   only real decision happens -- and it had no numbers on it.
				   The figure comes from dropshipThreshold(), the same
				   function the run rolls against. */
				echo dropshipOddsPanel($conn);
				?>
					<div id="nfts" class="nfts">
						<?php
						getSoldiers($conn, 1); 
						?>
					</div>
					<?php
					/*
					 * NOTHING TO SAVE ANY MORE. "Save this squad" lived here
					 * because this was where a hand-built squad existed.
					 * dropshipSquadsAutoSync() now arranges the whole roster
					 * into the best squads it can make whenever the holdings
					 * change, so a squad is never assembled by hand and there
					 * is nothing to name or keep.
					 *
					 * WITHDRAW ALL STAYS, because clearing the field is still
					 * the player's: it is how you take one squad off and put
					 * another on. It empties the field and touches no squad.
					 */
					if (checkSquadCount($conn) > 0): ?>
					<div class="dssq-save">
						<form action="dashboard.php#barracks" method="post">
							<input type="hidden" name="squad_clear" value="1">
							<input class="small-button secondary" type="submit" value="Withdraw all">
						</form>
					</div>
					<?php endif; ?>
				</div>
			<?php } ?>
				<a name="barracks" id="barracks"></a>
				<div class="content" id="barracks-panel">
				<h2><?php echo evaluateText("Barracks"); ?></h2>
					<?php
					/*
					 * SQUADS FIRST, LOOSE SOLDIERS AFTER, which is the owner's
					 * order and the right one: a saved squad is a decision
					 * already made and wants one press, while what is left over
					 * is the tail that never fitted a Super Soldier Squad and is
					 * rarely used. getSoldiers() leaves squad members out of the
					 * list below, so nobody appears twice.
					 */
					if (isset($ds_squad_msg)) {
						echo '<div class="msg ' . ($ds_squad_msg[0] ? 'good' : 'bad') . '">'
						   . htmlspecialchars($ds_squad_msg[1], ENT_QUOTES) . '</div>';
					}
					echo dropshipSquadsPanel($conn);
					/*
					 * THE LOOSE GRID AND ITS FILTER ARE GONE. EVERYTHING IS
					 * SQUAD BASED.
					 *
					 * The barracks used to be where a player picked soldiers
					 * one at a time and the filter was how they found the
					 * pieces of a Super Soldier Squad by hand. Both answered
					 * a question nobody is asked any more:
					 * dropshipSquadsAutoSync() arranges the entire roster,
					 * so every soldier is already in a squad above and there
					 * is nothing loose to list.
					 *
					 * A SINGLE SOLDIER IS STILL REACHABLE, which is the part
					 * worth not losing: the plan runs out of complete squads
					 * and keeps going, so the tail is squads of three, two
					 * and one. The hail-mary throw of whatever is left is a
					 * squad like any other and deploys in one press.
					 *
					 * getSoldiers($conn, 0, ...) is left alone rather than
					 * deleted -- $active 1 is the field and $active 2 is the
					 * public Registered Soldiers roll, and both still use it.
					 */
					?>
				</div>
			</div>
			<a name="armory" id="armory"></a>
			<div class="col1of3">
				<h2><?php echo evaluateText("Armory"); ?></h2>
 				 <div id="armory-icons">
					  <img onclick="javascript:toggleArmory(document.getElementById('inventory'), this)" class="armory-icon" id="inventory-icon" src="icons/inventory.png"/>
					  <img onclick="javascript:toggleArmory(document.getElementById('weapons'), this)" class="armory-icon icon-disabled" id="weapon-icon" src="icons/weapons.png"/>
					  <img onclick="javascript:toggleArmory(document.getElementById('armor'), this)" class="armory-icon icon-disabled" id="armor-icon" src="icons/shield.png"/>
					  <img onclick="javascript:toggleArmory(document.getElementById('equipment'), this)" class="armory-icon icon-disabled" id="equipment-icon" src="icons/equipment.png"/>
				</div>
				 <div class="content" id="inventory">
					<ul><li class="role"><img class="icon" src="icons/inventory.png"/><h3>Inventory</h3></li>
						<li class="role"><img class="icon" src="icons/scrip.png"/>
						<?php echo $scrip; ?> <?php echo "$".$currency ?>
						</li>
					</ul>
					<?php checkInventory($conn);?>
				 </div>
				 <div class="content" id="weapons" style="display:none">
					<ul><li class="role"><img class="icon" src="icons/weapons.png"/><h3>Weapons</h3></li>
						<li class="role"><img class="icon" src="icons/scrip.png"/>
						<?php echo $scrip; ?> <?php echo "$".$currency ?>
						</li>
					</ul>
					<?php loadItems($conn, "Weapon", $heavy, $medium, $light, $base, $melee, $demolition, $extralife, $scrip);?>
				 </div>
				 <div class="content" id="armor" style="display:none">
					<ul><li class="role"><img class="icon" src="icons/shield.png"/><h3>Armor</h3></li>
						<li class="role"><img class="icon" src="icons/scrip.png"/>
						<?php echo $scrip; ?> <?php echo "$".$currency ?>
						</li>
					</ul>
					<?php loadItems($conn, "Armor", $heavy, $medium, $light, $base, $melee, $demolition, $extralife, $scrip);?>
				 </div>
				 <div class="content" id="equipment" style="display:none">
					<ul><li class="role"><img class="icon" src="icons/equipment.png"/><h3>Equipment</h3></li>
						<li class="role"><img class="icon" src="icons/scrip.png"/>
						<?php echo $scrip; ?> <?php echo "$".$currency ?>
						</li>
					</ul>
					<?php loadItems($conn, "Equipment", $heavy, $medium, $light, $base, $melee, $demolition, $extralife, $scrip);
					?>
				 </div>
			</div>
		</div>
		<?php } ?>
		<!-- Footer -->
		<div class="footer">
		  <p>Drop Ship | Ohh Meed's Shorty Verse<br>Copyright © <span id="year"></span>
			<!-- Admin functions to deactivate current game or create a new game -->
			<?php if($_SESSION['userData']['discord_id'] == "772831523899965440") {
				if(isset($_SESSION['userData']['game_id'])) { ?>
					<form action="dashboard.php" method="post">
					  <input type="hidden" id="deactivate" name="deactivate" value="true">
					  <input class="button" type="submit" value="Deactivate Game">
					</form>
					<br>
				<?php 
				} else { ?>
					<form action="dashboard.php" method="post">
					  <p>Name: </p><input id="name" name="name" value="">
  					  <p>Number of Prizes: </p><input id="prizes" name="prizes" value=""><br><br>
					  <input type="hidden" id="newgame" name="newgame" value="true">
					  <input class="button" type="submit" value="New Game">
					</form>
					<br>
				<?php }
			 } ?>
		</div>
	</div>
  </div>

<?php
/*
 * THE GAME PICKER.
 *
 * A CHILD OF <body>, not of the panel its button sits in. .side is a flex
 * column with its own background and the stats list lives inside .content;
 * a fixed overlay rendered in there is still laid out by that subtree, and
 * anything that establishes a containing block above it (a transform, a
 * filter, an overflow) crops the veil to the sidebar. Rendering it here is
 * the only placement that cannot be clipped by a panel.
 *
 * BUILT FROM getProjects(), which is the allow-list dropship.php validates
 * ?project_id against. A hand-written list of four would offer a game the
 * server then refuses, and would miss the fifth on the day there is one --
 * the same gate-written-as-a-list that cost Dread City and Filthy Mermaid
 * their Discord standings.
 *
 * LINKS, NOT A SCRIPTED FORM. ?project_id is accepted and validated exactly
 * like the POST was, so a tile is an ordinary anchor: it survives JavaScript
 * failing, it can be opened in a new tab, and the browser shows where it
 * goes. The old dropdown needed selectProject() to submit a hidden form.
 */
$ds_games = array();
foreach (getProjects($conn) as $ds_gid => $ds_gname) {
	/* The theme's OWN picture, and nothing if it has none -- the fallback
	   would be Drop Ship's, which is the one thing a picker exists to tell
	   apart. dropshipThemeImagePath() gives the path whether or not the file
	   is there, so existence is tested here, the same way
	   dropshipThemeImageUrl() does it for the Discord post. */
	$ds_rel = function_exists('dropshipThemeImagePath')
	        ? dropshipThemeImagePath($conn, $ds_gid, 'images/dropship.jpg') : '';
	$ds_games[] = array(
		'id'   => (int)$ds_gid,
		'name' => (string)$ds_gname,
		'src'  => ($ds_rel !== '' && is_file(__DIR__ . '/' . $ds_rel))
		          ? '/staking/dropship/' . $ds_rel : '',
	);
}
/* The picker's own order, not the query's -- see dropshipGameOrder(). */
$ds_games = dropshipGameOrderSort($ds_games);
$ds_now = isset($_SESSION['userData']['dropship_project_id'])
        ? (int)$_SESSION['userData']['dropship_project_id'] : 0;
/*
 * THE COUNT COMES OFF THE LIST, not out of a sentence somebody typed.
 *
 * The sub-heading named the number of games in words and had been wrong
 * since DHC2 Fighters made it five. The grid below it has always been built
 * from getProjects(), so the picker and the sentence introducing the picker
 * were drawn from two different sources, and only one of them got updated
 * when a theme was added. The sixth would have broken it again.
 *
 * Spelled out, because the sentence is prose and a digit there reads like a
 * stat line. Past twelve it falls back to digits, which reads fine and is
 * one less table to keep right.
 */
$ds_count = count($ds_games);
$ds_numbers = array(2 => 'Two', 3 => 'Three', 4 => 'Four', 5 => 'Five', 6 => 'Six',
                    7 => 'Seven', 8 => 'Eight', 9 => 'Nine', 10 => 'Ten',
                    11 => 'Eleven', 12 => 'Twelve');
$ds_count_word = isset($ds_numbers[$ds_count]) ? $ds_numbers[$ds_count] : (string)$ds_count;
if ($ds_count > 1):
?>
<style>
/*
 * UNSCOPED IDS, on purpose. These rules were nearly written as
 * `.side #dsgame`, which would have styled nothing: the element is a child
 * of <body>. A selector that names an ancestor the element does not have is
 * a stylesheet that silently does not apply, and the Theme Art lightbox
 * shipped exactly that bug once.
 */
#dsgame{position:fixed;inset:0;z-index:1000;display:flex;align-items:center;
  justify-content:center;padding:16px;background:rgba(4,10,18,.82)}
#dsgame[hidden]{display:none}
/*
 * 780 NOW, AND THAT IS THE SAME HEIGHT ARGUMENT, NOT A REVERSAL OF IT.
 *
 * It was 560 because a square tile is as tall as its column is wide, so a
 * WIDER box meant TALLER rows. That held while the grid was two across: at
 * 560 four games were two rows of ~262px tiles, about 710px all in.
 *
 * The fifth game broke it. Two across made three rows -- roughly 1050px --
 * and the picker scrolled on a laptop, which is what it was widened to
 * avoid. Three across at 780 is two rows of ~240px tiles, about 690px: the
 * tiles get SMALLER as the box gets wider, because the column count went up
 * faster than the width did. Six games still fit in those two rows.
 */
#dsgame-box{position:relative;width:100%;max-width:780px;max-height:100%;
  overflow-y:auto;background:#0a1929;border:1px solid #163047;border-radius:10px;
  padding:18px;box-shadow:0 18px 50px rgba(0,0,0,.55);
  padding-top:calc(18px + env(safe-area-inset-top, 0px));
  padding-bottom:calc(18px + env(safe-area-inset-bottom, 0px))}
#dsgame-box h3{margin:0 34px 4px 0;font-weight:300;font-size:1.4rem;color:#00c8a0}
#dsgame-box p.sub{margin:0 0 14px;font-size:12px;color:#7a9eb0}
#dsgame-x{position:absolute;top:10px;right:10px;width:30px;height:30px;padding:0;
  line-height:28px;font-size:20px;background:none;border:1px solid #163047;
  border-radius:6px;color:#9fb6c4;cursor:pointer}
#dsgame-x:hover{color:#e8eaed;border-color:#00c8a0}
/*
 * FLEX, NOT GRID, AND ONLY FOR THE LAST ROW.
 *
 * Five games in three columns leaves two on the second row. CSS Grid has no
 * way to centre a partial final row -- the items are placed on tracks, and
 * the empty third track still takes its share -- so the orphans sit left
 * with a hole beside them. A centred flex wrap has no tracks: a full row of
 * three consumes the width exactly and shows no centring at all, and a
 * short row splits the leftover evenly. It is right for any number of
 * games rather than tuned to five, which matters because the count is now
 * whatever getProjects() returns.
 *
 * --cols IS CAPPED BY THE GAME COUNT, set inline from PHP. Without it two
 * games would each take a third of a 780px box and sit marooned in the
 * middle of it. The media queries below set --cols rather than --maxcols,
 * so they still win on a narrow screen: an inline style only raises the
 * ceiling, it does not pin the value.
 */
#dsgame-grid{display:flex;flex-wrap:wrap;justify-content:center;gap:12px;
  --maxcols:3;--cols:var(--maxcols)}
#dsgame-grid .dsgame-tile{flex:0 0 calc((100% - (var(--cols) - 1) * 12px) / var(--cols));
  max-width:calc((100% - (var(--cols) - 1) * 12px) / var(--cols))}
/* Two across once three would squeeze the tiles below a readable name. */
@media (max-width:700px){#dsgame-grid{--cols:min(2,var(--maxcols))}}
/* One column only where two would make the name unreadable, not at the
   usual phone width -- the multi-up shape is the point of the picker. */
@media (max-width:359px){#dsgame-grid{--cols:1}}
/* text-align RESTATED, not inherited. flexbox.css sets `a {text-align:center}`
   globally, so every anchor on this app is centred unless it says otherwise,
   and a caption under a picture wants its own edge. Same for the colour: the
   global rule paints links teal. */
.dsgame-tile{display:block;text-align:left;text-decoration:none;color:#e8eaed;
  background:#07111d;border:1px solid #163047;border-radius:8px;overflow:hidden}
.dsgame-tile:hover,.dsgame-tile:focus-visible{border-color:#00c8a0}
/*
 * THE WHOLE PICTURE, NOT A CROP OF IT.
 *
 * This was a 16/9 box with object-fit:cover, which is the right pairing for
 * art cut to a known shape and the wrong one here. MEASURED, because the
 * four are nothing alike: Drop Ship is 745x850, Dread City 1038x1080, Oculus
 * Lounge 891x891 and Filthy Mermaid 480x356. Three are portrait and one is
 * landscape, so a 16/9 crop took the middle band out of three of them and
 * threw away the faces -- on a picker whose entire job is telling four games
 * apart by looking at them.
 *
 * A SQUARE BOX AND contain. Square because three of the four are within 13%
 * of it, so the bars are small where they appear at all; contain because
 * with ratios this far apart nothing else shows every image whole. The box
 * is the tile's own ink, so a letterboxed edge reads as the tile rather than
 * as a grey slab -- which matters most for Filthy Mermaid, whose art is a
 * white logo on black and simply continues into it.
 */
.dsgame-tile .art{display:block;width:100%;aspect-ratio:1/1;background:var(--ds-ink,#07111d)}
.dsgame-tile .art img{display:block;width:100%;height:100%;object-fit:contain;max-width:100%}
.dsgame-tile b{display:block;padding:8px 10px 2px;font-size:13px}
.dsgame-tile em{display:block;padding:0 10px 9px;font-style:normal;font-size:10px;
  letter-spacing:.08em;text-transform:uppercase;color:#7a9eb0}
.dsgame-tile.on{border-color:#00c8a0}
.dsgame-tile.on em{color:#00c8a0}
</style>
<div id="dsgame" hidden>
  <div id="dsgame-box" role="dialog" aria-modal="true" aria-labelledby="dsgame-h" tabindex="-1">
    <button type="button" id="dsgame-x" aria-label="Close">&times;</button>
    <h3 id="dsgame-h">Select Game</h3>
    <p class="sub"><?php echo $ds_count_word; ?> games on one engine. Each keeps
      its own board, its own squad and its own prizes.</p>
    <div id="dsgame-grid" style="--maxcols:<?php echo min(3, $ds_count); ?>">
      <?php foreach ($ds_games as $g): $on = ($g['id'] === $ds_now); ?>
      <a class="dsgame-tile<?php echo $on ? ' on' : ''; ?>"
         href="dashboard.php?project_id=<?php echo $g['id']; ?>">
        <span class="art"><?php if ($g['src'] !== ''): ?><?php /* data-src, swapped
          when the picker is first opened: four hero jpgs is most of a megabyte
          and nobody should pay for it to sit behind a button they never press. */ ?>
          <img alt="" data-src="<?php echo htmlspecialchars($g['src'], ENT_QUOTES); ?>">
        <?php endif; ?></span>
        <b><?php echo htmlspecialchars($g['name'], ENT_QUOTES); ?></b>
        <em><?php echo $on ? 'Playing now' : 'Play'; ?></em>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
</div>
<script>
(function () {
	var veil = document.getElementById('dsgame');
	var open = document.getElementById('dsgame-open');
	var box  = document.getElementById('dsgame-box');
	if (!veil || !open || !box) return;
	var last = null, loaded = false;

	function show() {
		/* THE PICTURES ARE FETCHED ON FIRST OPEN. They are hero jpgs and
		   there are four of them; loading="lazy" does not help, because a
		   hidden image is loaded or not at the browser's discretion. */
		if (!loaded) {
			loaded = true;
			var imgs = veil.querySelectorAll('img[data-src]');
			for (var i = 0; i < imgs.length; i++) imgs[i].src = imgs[i].getAttribute('data-src');
		}
		last = document.activeElement;
		veil.hidden = false;
		box.focus();
	}
	function hide() {
		veil.hidden = true;
		if (last && last.focus) last.focus();
	}

	open.addEventListener('click', show);
	document.getElementById('dsgame-x').addEventListener('click', hide);
	/* The veil closes, the box does not -- a click inside must not fall
	   through to the backdrop and shut the thing being clicked. */
	veil.addEventListener('click', function (ev) { if (ev.target === veil) hide(); });
	document.addEventListener('keydown', function (ev) {
		if (!veil.hidden && (ev.key === 'Escape' || ev.key === 'Esc')) hide();
	});
	/* Switching game is a full page load that re-reads the squad, the board
	   and the art, so it is not instant -- the dropdown showed the loader for
	   the same reason. */
	veil.addEventListener('click', function (ev) {
		var a = ev.target.closest ? ev.target.closest('.dsgame-tile') : null;
		if (a && typeof dsShowLoader === 'function') dsShowLoader();
	});
}());
</script>
<?php endif; ?>
</body>
<?php
if($heavy == "true" && $medium == "true" && $light == "true" && $base == "true" && $extralife == "true" && $demolition == "true" && $melee == "true") { ?>
	<script type="text/javascript">
	document.getElementById('super-soldier').style.display = "flex";
	document.getElementById('squad').innerHTML = '<?php echo $squad; ?> Squad';
	</script>
<?php } ?>
<?php
if(isset($_SESSION["userData"]["wallet"])){
	echo "<script type='text/javascript'>function updateWallet(){document.getElementById('wallets').value = '".$_SESSION["userData"]["wallet"]."';}</script>";
}
/* The filter dropdown this pre-selected is gone with the loose grid, and
   getElementById() on a missing id returns null -- so this line threw and
   took every script after it on the page down with it. A stale form post
   can still set $filterby, so the guard is on the ELEMENT, not on the
   value. */
/* The dropdown that this selected into is gone -- the picker marks the
   current game on the tile itself, so there is no control to pre-set. Left
   as a note rather than deleted silently, because the line threw a null
   dereference the moment the <select> went and stopped the three scripts
   after it from running. */
/* The page background is set once in header.php, which every page
   includes -- these two copies only ran on the dashboard, and the Filthy
   Mermaid one was missing the /staking/dropship prefix every other path
   on the page carries, so it 404'd while the file sat there. */
?>
<!-- wallet.js no longer loaded -- its connectWallet()/sendAddress() drove the
     removed "Connect a wallet" UI, dead now that wallet connection is
     read from Skulliance's own account instead. File left in place,
     unreferenced, rather than deleted outright. -->
<?php
/*
 * WHICH SOUND EACH ROUND PLAYS, resolved here rather than in the browser.
 *
 * displayRound() carried thirty-four if-branches of caption text against
 * sound filenames, and every reskin's own words were in there beside Drop
 * Ship's -- Vibrator, Whip, Paddle, Ball Gag -- all of which the wording
 * table already holds. The wording lives on this side and so does the
 * filesystem, so the answer is worked out here and the browser walks a
 * list. See dropship/dropship-audio.php.
 *
 * ORDER MATTERS AND IS PRESERVED: the match is a substring of the
 * caption, so "Smoke Bomb" also matches "Pull Smoke Bomb Pin" and the pin
 * has to come first. json_encode keeps the array's order.
 */
?>
<script>window.dsAudio = <?php echo json_encode(dropshipAudioPayload($conn, $_SESSION['userData']['dropship_project_id']),
	JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
<script type="text/javascript" src="<?php echo dropshipAsset('dropship.js'); ?>"></script>
<?php
/*
 * ANNOUNCE THE RUN, AFTER THE PAGE IS ALREADY ON THE WIRE.
 *
 * This ran just above <div id="results">, which put two synchronous Discord
 * posts in front of everything below it -- including #hidden-results, where
 * the replay frames live. discordmsg() had NO TIMEOUT AT ALL, so a slow or
 * rate-limited webhook stalled the response part-way through: players got a
 * page with no animation and then an error, while their score had already
 * been written by logScore() earlier in the same request. Reported live on
 * mobile for both Drop Ship and Oculus Lounge, which is where a stalled
 * response shows up first.
 *
 * Down here every byte of the page has already been sent, so nothing a
 * player sees depends on this finishing. discordmsg() is capped as well now
 * (2s connect, 4s total): the cap stops the hang, the placement stops a
 * hang mattering.
 */

				/*
				 * ANNOUNCE EVERY RUN.
				 *
				 * This lived inside the game-over panel below, which only
				 * renders once `current_score` is set -- and checkScore()
				 * sets that at the BOTTOM of this page, so on the request
				 * that actually logs the run it still holds the previous
				 * value. The announcement therefore waited for the NEXT
				 * request, and the only thing that reliably produced one was
				 * the player stepping through every round of the replay and
				 * pressing a button labelled "Send Results to Discord". Close
				 * the tab early and the channel never heard about that run.
				 *
				 * logScore() now sets run_logged the moment a score is on the
				 * board, so a run announces exactly once whatever the player
				 * does next. The old game-over condition is kept as a second
				 * trigger so a run logged before this change still posts when
				 * its results are next opened.
				 *
				 * HERE AND NOT IN logScore() because discordmsg() is Drop
				 * Ship's own and webhooks.php is included AFTER dropship.php,
				 * so at the point the score is written it does not exist yet.
				 * function_exists() guards that ordering rather than trusting
				 * it.
				 */
				$ds_just_ran  = isset($_SESSION['userData']['run_logged']);
				$ds_game_over = isset($_SESSION['userData']['current_score']) && checkSquadCount($conn) < 1;
				unset($_SESSION['userData']['run_logged']);
				if(($ds_just_ran || $ds_game_over)
				   && !isset($_SESSION['userData']['notification'])
				   && isset($_SESSION['userData']['score'])
				   && function_exists('discordmsg')) {
							$title = "Dead on Round ".$_SESSION['userData']['score'];
							/* Captured without its warnings -- display_errors is on
							   platform-wide and anything raised inside the buffer
							   would be posted into Discord verbatim, server paths
							   and all. */
							$list = dropshipCapture(function () use ($conn) { checkPlayerItems($conn); });
							$description = $_SESSION['userData']['name']." died during Round ".$_SESSION['userData']['score']."\n".evaluateText($list);
							/*
							 * THE THEME'S OWN FORMAT. This was hardcoded .gif while
							 * the game itself asks for evaluateText("gif"), and Dread
							 * City is a jpg theme -- so a Dread City death posted
							 * dread-city/images/die/N.gif, which on the server is a
							 * byte-for-byte COPY of Drop Ship's animated death. That
							 * is the "I got a drop ship animated gif death" report:
							 * the path was the theme's, the picture inside it was
							 * not.
							 *
							 * AND NO FALLBACK TO .gif WHEN THE FILE IS MISSING. The
							 * first version of this fell back on the same frame,
							 * reasoning that a theme can ship both sets -- but the
							 * set it would fall back TO is the one full of Drop
							 * Ship's copies, so the fallback reproduces exactly the
							 * bug it sits under. No picture beats another theme's.
							 */
							$ds_die = rand(1,3);
							$ds_pid = $_SESSION['userData']['dropship_project_id'];
							$imageurl = dropshipThemeImageUrl($conn, $ds_pid, 'images/die/'.$ds_die.'.'.dropshipMediaExtension());
							discordmsg($title, $description, $imageurl, $_SESSION['userData']['dropship_project_id']);
							$_SESSION['userData']['notification'] = "true";

							/*
							 * Current leaderboard standings, posted automatically after
							 * every run -- replaces the old /leaderboard Discord bot
							 * command (no longer reliable). Same output-buffered
							 * checkLeaderboard() capture automate.php already uses for
							 * its own leaderboard posts, just fired per-run instead of
							 * on a cron cadence. discordmsg()'s own $project_id routing
							 * (see webhooks.php) sends it to the right channel.
							 *
							 * EVERY THEME, NOT A LIST OF PROJECT IDS. This read
							 * `project_id == 1 || project_id == 4` with a note that
							 * Dread City and Filthy Mermaid were a work in progress --
							 * which is "these two post no ladder" spelled as a hardcoded
							 * id check, so the work never got finished and nothing said
							 * so. It is the same gate dropship/db.php's payout carried
							 * until a theme got a weekly cycle and paid nobody;
							 * dropship-themes-harness.php forbids it there and now here.
							 *
							 * NOTHING IN HERE IS THEME-SPECIFIC, which is why the list
							 * was never needed: checkLeaderboard() already scopes itself
							 * to the session's own game_id and project_id,
							 * getProjectName() names the theme, and
							 * dropshipThemeImageUrl() resolves the theme's OWN picture
							 * and returns '' when it has none -- which discordmsg()
							 * omits -- rather than falling back to Drop Ship's. So the
							 * two real conditions are the ones stated: a game, for there
							 * to be a board at all, and something on it.
							 */
							if(isset($_SESSION['userData']['game_id'])){
								$leaderboard_list = dropshipCapture(function () use ($conn) { checkLeaderboard($conn, "true"); });
								if($leaderboard_list != ""){
									$leaderboard_imageurl = dropshipThemeImageUrl($conn, $_SESSION['userData']['dropship_project_id'], 'images/dropship.jpg');
									discordmsg("📊 ".getProjectName($conn)." Leaderboard", $leaderboard_list, $leaderboard_imageurl, $_SESSION['userData']['dropship_project_id']);
								}
							}
				}

/*
 * CLOSE LAST, AND NOTHING AFTER IT.
 *
 * This used to sit immediately after </body>, which was fine until the run
 * announcement moved down here: everything below it needs $conn --
 * checkSquadCount(), checkPlayerItems(), checkLeaderboard() and
 * getProjectName() -- and a closed mysqli is a FATAL on first use, not a
 * failed query. It fired for anybody with a score in session, which is every
 * player who had just finished a run: the score was written, then the page
 * died on the way to announcing it.
 */
$conn->close();
?>
</html>