<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';

$realm_status = checkRealm($conn);

if(isset($_POST['realm']) && isset($_POST['faction'])){
	if(!$realm_status){
		createRealm($conn, $_POST['realm'], $_POST['faction']);
		$realm_id = getRealmID($conn);
		$core_projects = getProjects($conn, "core");
		$partner_projects = getProjects($conn, "partner");
		$all_projects = $core_projects+$partner_projects;
		$theme_id = array_rand($all_projects, 1);
		updateRealmTheme($conn, $realm_id, $theme_id);
		$realm_name = ucfirst(getRealmName($conn));
		$title = $realm_name;
		$description = $realm_name." has been established by:\r\n\r\n".getUsername($conn)." <@".$_SESSION['userData']['discord_id'].">";
		$imageurl = "https://www.skulliance.io/staking/images/themes/".$theme_id.".jpg";
		$thumbnail = "https://cdn.discordapp.com/avatars/".$_SESSION['userData']["discord_id"]."/".$_SESSION['userData']["avatar"].".jpg";
		discordmsg($title, $description, $imageurl, "https://skulliance.io/staking", "", $thumbnail);
	}
}

if(isset($_POST['realmText'])){
	if($realm_status){
		$realm_id = getRealmID($conn);
		updateRealmName($conn, $realm_id, $_POST['realmText']);
	}
}

if(isset($_SESSION['userData']['user_id'])){ ?>
<!-- The flexible grid (content) -->

<div class="row" id="row0">	
	  <div class="side" id="locations">
		<div class="content realm">
			<?php
			$projects = getProjects($conn, "core");
			if($realm_status){
				if(checkRealmState($conn) == 1){
					$status = getRealmLocationsUpgrades($conn);
					$locations = getLocationInfo($conn);
					?>
					<ul style='position:relative;top:-51px'>
					<?php
					$realm_id = getRealmID($conn);
					$levels = getRealmLocationLevels($conn);
					$loc_consumables = getRealmLocationConsumables($conn, $realm_id);
					$amounts_data = getCurrentAmounts($conn);
					$con_names = array(1=>'100% Success',2=>'75% Success',3=>'50% Success',4=>'25% Success',5=>'Fast Forward',6=>'Double Rewards',7=>'Random Reward');
					?>
					<li class="role" style="display:block;padding:8px 0 12px;">
						<div class="location-row realm-header-row" style="border-bottom:1px solid rgba(0,200,160,0.15);padding-bottom:14px;margin-bottom:6px;align-items:center;">
							<div class="realm-logo-wrap" style="flex-shrink:0;">
								<img src="images/realms-logo.png" style="width:100px;opacity:0.9;margin-right:0;margin-top:8px;"/>
							</div>
							<div class="realm-header-spacer" style="flex:1;"></div>
							<div class="realm-deactivate-wrap" style="flex-shrink:0;text-align:right;">
								<?php echo '<input class="small-button" type="button" value="Deactivate" onclick="deactivateRealm('.$realm_id.');">';?>
							</div>
						</div>
					</li>
					<li class="role" style="display:block;padding:4px 0 8px;">
						<strong class="loc-inventory-header">Inventory</strong>
						<div class="loc-inventory-strip">
							<?php foreach($con_names as $cid => $cname):
								$qty = isset($amounts_data[$cid]) ? intval($amounts_data[$cid]['amount']) : 0;
								$icon = strtolower(str_replace('%','',str_replace(' ','-',$cname))).'.png';
							?>
							<div class="loc-inv-slot <?php echo $qty > 0 ? 'available' : 'unavailable'; ?>"
							     id="inv-slot-<?php echo $cid; ?>"
							     title="<?php echo htmlspecialchars($cname); ?>">
								<img class="icon" src="icons/<?php echo $icon; ?>" onerror="this.src='icons/skull.png'"/>
								<span class="loc-con-badge" id="inv-qty-<?php echo $cid; ?>"><?php echo $qty; ?></span>
							</div>
							<?php endforeach; ?>
							<div class="loc-stock-row" style="margin-top:6px;gap:6px;">
								<button class="small-button" onclick="openInventoryInfoModal()">Inventory Info</button>
								<button class="small-button" id="stock-all-btn" onclick="stockAllLocations()">Stock All Locations</button>
							</div>
						</div>
					</li>
					<?php
					$previous_type = "";
					foreach($locations AS $location_id => $location){
						if($previous_type == ""){
							echo "<div class='location-wrapper ".$location['type']."'>".ucfirst($location['type']);
						}else if($previous_type != $location['type']){
							echo "</div>";
							echo "<div class='location-wrapper ".$location['type']."'>".ucfirst($location['type']);
						}
						?>
						<li class="role">
							<div class="location-row">
								<div class="location-icon-wrap">
									<img style="opacity:0.85;width:44px;" title="<?php echo $location['description'];?>" src="icons/locations/<?php echo $location['name']; ?>.png">
								</div>
								<div class="location-info">
									<strong><?php echo strtoupper($location['name']); ?></strong>
									<div class="location-meta">Level <?php echo $levels[$location_id]; ?>
									<?php 
									if(!isset($status[$location_id])){  
										if($levels[$location_id] > 10){ $duration = 10;
										}else if($levels[$location_id] == 10){ $duration = $levels[$location_id];
										}else{ $duration = $levels[$location_id]+1; }
										$cost = $duration*100; ?>
										&bull; <?php echo number_format($cost)." ".$projects[$location_id]['currency']; ?>
										&bull; <?php echo $duration." ".($duration == 1 ? "Day" : "Days"); ?>
									<?php } ?>
									</div>
									<?php
									$loc_eq = isset($loc_consumables[$location_id]) ? $loc_consumables[$location_id] : array();
									$s_boost = 0;
									if(isset($loc_eq[1])) $s_boost += 4;
									if(isset($loc_eq[2])) $s_boost += 3;
									if(isset($loc_eq[3])) $s_boost += 2;
									if(isset($loc_eq[4])) $s_boost += 1;
									$s_boost = min(10, $s_boost);
									$tags = array();
									if($s_boost > 0)      $tags[] = '+'.$s_boost.'% Success';
									if(isset($loc_eq[5])) $tags[] = 'Fast Forward';
									if(isset($loc_eq[6])) $tags[] = 'Shield';
									if(isset($loc_eq[7])) $tags[] = 'Random Reward';
									?>
									<div id="loc-status-<?php echo $location_id; ?>" class="loc-status-labels">
										<?php foreach($tags as $tag): ?><span class="loc-status-tag"><?php echo $tag; ?></span><?php endforeach; ?>
									</div>
								</div>
								<div class="location-action" id="loc-action-<?php echo $location_id; ?>">
								<div id="loc-upgrade-<?php echo $location_id; ?>"><?php
								if(!isset($status[$location_id])){
									$balance = getBalance($conn, $location_id);
									if($balance >= $cost){ 
										$upgrade_verbiage = ($levels[$location_id] >= 10) ? "Maintain" : "Upgrade";
										echo "<input id='upgrade-button-".$location_id."' class='small-button' type='button' value='".$upgrade_verbiage." Lv".$duration."' onclick='upgradeRealmLocation(this, ".$realm_id.", ".$location_id.", ".$duration.", ".$cost.", ".$location_id.")'>";
									}else{
										echo "<span id='upgrade-message-".$location_id."' class='location-meta'>Need ".number_format($cost-$balance)." ".$projects[$location_id]['currency']."</span><br>";
									echo "<input id='points-button-".$location_id."' class='small-button' type='button' value='".$points_multiplier."x Pts' onclick=\"togglePointsButtons('disable');pointsOption(this, ".$realm_id.", ".$location_id.", ".$duration.", ".$cost.")\"".">";
									}
								}else{ echo $status[$location_id]; }
								?></div><?php
							// Location-specific modal button
							$loc_modal_map = array(1=>'Portal',2=>'Armory',3=>'Tower',4=>'Barracks',5=>'Factory',6=>'Crypt',7=>'Mine');
							if(isset($loc_modal_map[$location_id])){
								echo "<br><input class='small-button loc-modal-btn' type='button' value='".$loc_modal_map[$location_id]."' onclick='openLocationModal(".$location_id.")' style='margin-top:4px;'>";
							}
								?>
								</div>
							</div>
							<!-- Consumable strip for this location -->
							<div class="loc-consumable-strip" id="loc-consumables-<?php echo $location_id; ?>">
								<?php foreach($con_names as $cid => $cname):
									$equipped = isset($loc_consumables[$location_id][$cid]);
									$qty = isset($amounts_data[$cid]) ? intval($amounts_data[$cid]['amount']) : 0;
									$icon = strtolower(str_replace('%','',str_replace(' ','-',$cname))).'.png';
									$slot_class = $equipped ? 'equipped' : ($qty > 0 ? 'available' : 'unavailable');
									$slot_title = htmlspecialchars($cname).($equipped?' (Equipped - click to unequip)':($qty>0?' (Click to equip)':' (None in inventory)'));
									$slot_onclick = $equipped ? 'removeLocationConsumable('.$location_id.','.$cid.')' : ($qty>0 ? 'applyLocationConsumable('.$location_id.','.$cid.')' : '');
								?>
								<div class="loc-con-slot <?php echo $slot_class; ?>"
								     id="loc-con-<?php echo $location_id.'-'.$cid; ?>"
								     title="<?php echo $slot_title; ?>"
								     onclick="<?php echo $slot_onclick; ?>">
									<img class="icon" src="icons/<?php echo $icon; ?>" onerror="this.src='icons/skull.png'"/>
									<?php if($equipped): ?>
									<span class="loc-con-badge equipped">&#10003;</span>
									<?php elseif($qty > 0): ?>
									<span class="loc-con-badge" id="loc-inv-<?php echo $location_id.'-'.$cid; ?>"><?php echo $qty; ?></span>
									<?php endif; ?>
								</div>
								<?php endforeach; ?>
								<div class="loc-stock-row">
									<button class="small-button" id="stock-btn-<?php echo $location_id; ?>" onclick="stockLocation(<?php echo $location_id; ?>)">Stock Location</button>
								</div>
							</div>
							</li>
					<?php
					$previous_type = $location['type'];
					}
					echo "</div>";
					?>
					</ul>
				<?php
				}else{
					$realm_id = getRealmID($conn);
					echo "<h2>Realm Status</h2>";
					$activation = checkRealmActivation($conn);
					if($activation == "true"){
						echo '<input class="button" type="button" value="Reactivate Realm" onclick="reactivateRealm('.$realm_id.');">';
					}else{
						echo '<p>Your Realm cannot be reactivated until '.date('F j, Y', strtotime($activation)).'</p>';
					}
				}
			}else{
				?>
				<h2>Create Your Realm</h2>
				<img src="images/realms-logo.png" width="100%"/>
				<ul>
				<li class="role">
				<form action="realms.php" method="post">
					<label for="realm">Realm Name:</label><br><br>
					<input type="text" id="realm" name="realm" size="30" required><br><br>
					<label for="faction"><strong>Faction:</strong></label><br><br>
					<select required class="dropdown" name="faction" id="faction">
					<?php
					$core_projects = getProjects($conn, "core");
					$partner_projects = getProjects($conn, "partner");
					?>
					<optgroup label="Core Factions">';
					<?php
					unset($core_projects[7]);
					foreach($core_projects AS $id => $project){
						echo '<option value="'.$id.'">'.$project["name"].'</option>';
					}
					echo '</optgroup><optgroup label="Partner Factions">';
					$partner_projects = getProjects($conn, "partner");
					foreach($partner_projects AS $id => $project){
						echo '<option value="'.$id.'">'.$project["name"].'</option>';
					}
					echo '</optgroup>';
					?>
					</select><br><br>
					<input class="button" type="submit" value="Create Realm"><br><br>
					<label for="disclaimer">Information</label><br>
					<p id="disclaimer">
Skulliance Realms is a unique and rewarding multiplayer experience for stakers that allows for competition between players.
<br><br>
<a href="https://skulliance.gitbook.io/skulliance/realms" target="_blank">Read about Realms, Locations, Raids, and Factions in the Skull Paper</a>
<br><br>
Skulliance is offering a promotional incentive to participate in realms. Stakers establishing new realms will receive the following starter pack of core project points:
<ul>
	<li>1K STAR</li>
	<li>1K DREAD</li>
	<li>1K HYPE</li>
	<li>1K SINDER</li>
	<li>1K CYBER</li>
	<li>1K CRYPT</li>
	<li>1K DIAMOND</li>
</ul>
</p>
				</form>
				</li>
				</ul>
				<?php
			}
			?>
		</div>
	  </div>
	  <div class="main">
		<div id="realm">
		<a name="realm-image" id="realm-image"></a>
		<h2><?php echo checkRealm($conn)?"<span id='realmName'>".getRealmName($conn)."</span>&nbsp;<img style='max-width:25px;cursor: pointer;' src='icons/edit.png' class='icon' onclick='editRealmName(this);'/>":"Realm"; ?></h2>
	    <div class="content realm">
		<?php
		if(isset($_POST['filterby'])){
			$image = $_POST['filterby'];
			$filterby = $_POST['filterby'];
			if(verifyRealmTheme($conn, $_POST['filterby']) || $_POST['filterby'] == 0){
				updateRealmTheme($conn, $realm_id, $_POST['filterby']);
			}else{
				$project_info = getProjectInfo($conn, $_POST['filterby']);
				//$image = getRealmThemeID($conn, $realm_id);
				//$filterby = $image;
				alert("You must own at least 1 NFT from ".$project_info["name"]." in order to save this theme. Purchase an NFT and refresh your wallet(s) to try again.");
			}
		}else{
			if(isset($realm_id)){
				$image = getRealmThemeID($conn, $realm_id);
			}
		}?>
		<img src="images/themes/<?php echo (isset($image)?$image:'7');?>.jpg" width="100%"/>
		<?php if(isset($image)){
		$selected = "";
		$theme_id = getRealmThemeID($conn, $realm_id);
		echo '
		<div id="filter-nfts" style="position:static;margin-top:10px;">
			<label for="filterNFTs"><strong>Theme:</strong></label>
			<select onchange="javascript:filterNFTs(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs" class="dropdown">
				<optgroup label="Core Projects">';
				$projects = array_reverse($projects, true);
				foreach($projects AS $id => $project){
					if($theme_id == $id){
						$selected = "selected";
					}else{
						$selected = "";
					}
					echo '<option '.$selected.' value="'.$id.'">'.$project["name"].'</option>';
				}
				echo '</optgroup><optgroup label="Partner Projects">';
				$partner_projects = getProjects($conn, "partner");
				foreach($partner_projects AS $id => $project){
					if($theme_id == $id){
						$selected = "selected";
					}else{
						$selected = "";
					}
					echo '<option '.$selected.' value="'.$id.'">'.$project["name"].'</option>';
				}
				echo '</optgroup>';
				if($_SESSION['userData']['discord_id'] == '772831523899965440'){
				echo '</optgroup><optgroup label="Founder">';
				echo '<option value="0">Oculus Orbus</option>';
				echo '</optgroup>';
				}
			echo '
			</select>
			<form id="filterNFTsForm" action="realms.php#realm-image" method="post">
			  <input type="hidden" id="filterby" name="filterby" value="">
			  <input type="submit" value="Submit" style="display:none;">
			</form><br>';
		}
		if(isset($_POST['faction'])){
			if(verifyRealmFaction($conn, $_POST['faction'])){
				updateRealmFaction($conn, $realm_id, $_POST['faction']);
			}else{
				$project_info = getProjectInfo($conn, $_POST['faction']);
				alert("You must own at least 1 NFT from ".$project_info["name"]." in order to join this Faction. Purchase an NFT and refresh your wallet(s) to try again.");
			}
			
		}
		?>
		<?php if($realm_status){ ?>
		<form id="factionsForm" action="realms.php#realm-image" method="post">
		<label for="faction"><strong>Faction:</strong></label>
		<select onchange="document.getElementById('factionsForm').submit();" class="dropdown" name="faction" id="faction">
		<?php
		$selected = "";
		$project_id = getRealmFaction($conn, $realm_id);
		$core_projects = getProjects($conn, "core");
		$partner_projects = getProjects($conn, "partner");
		?>
		<optgroup label="Core Factions">';
		<?php
		unset($core_projects[7]);
		foreach($core_projects AS $id => $project){
			if($project_id == $id){
				$selected = "selected";
			}else{
				$selected = "";
			}
			echo '<option '.$selected.' value="'.$id.'">'.$project["name"].'</option>';
		}
		echo '</optgroup><optgroup label="Partner Factions">';
		$partner_projects = getProjects($conn, "partner");
		foreach($partner_projects AS $id => $project){
			if($project_id == $id){
				$selected = "selected";
			}else{
				$selected = "";
			}
			echo '<option '.$selected.' value="'.$id.'">'.$project["name"].'</option>';
		}
		echo '</optgroup>';
		?>
		</select>
		</form>
		<?php } ?>
		</div>
	    </div>
		</div>
	  </div>
</div>
<?php if($realm_status){ ?>
<div class="row">	
	<div class="main">
		<?php
			echo '<div id="stats">';
			getTotalFactionRaids($conn);
			getTotalRaids($conn);
			echo '</div>';
			echo '<div id="raids">';
			$outgoing_raids = getRaids($conn, "outgoing", "pending"); 
			if(isset($outgoing_raids)){
				echo '<div class="content raids">';
				echo $outgoing_raids;
				echo '</div>';
			}	
			$outgoing_completed = getRaids($conn, "outgoing", "completed"); 
			if(isset($outgoing_completed)){
				echo '<div class="content raids">';
				echo $outgoing_completed;
				echo '</div>';
			}	
			$incoming_raids = getRaids($conn, "incoming", "pending"); 
			if(isset($incoming_raids)){
				echo '<div class="content raids">';
				echo $incoming_raids;
				echo '</div>';
			}	
			$incoming_completed = getRaids($conn, "incoming", "completed"); 
			if(isset($incoming_completed)){
				echo '<div class="content raids">';
				echo $incoming_completed;
				echo '</div>';
			}
			echo "</div>";	
		?>
	</div>
</div>
<?php } ?>
<?php if($realm_status){ ?>
<div class="row" id="map" style="display:none">	
	<div class="main">
	    <div id="container-wrapper">
	        <div id="container"></div>
	    </div>
	    <div class="popup-overlay" id="popup-overlay">
	        <div class="popup-content">
	            <button class="popup-close" id="popup-close">&#x2715;</button>
	            <img class="popup-image" id="popup-image" src="" alt="Realm Image">
	            <div class="popup-footer">
	                <img class="popup-avatar" id="popup-avatar" src="" alt="Avatar">
	                <div class="popup-info">
	                    <p class="popup-user" id="popup-user"></p>
	                    <p class="popup-realm" id="popup-realm"></p>
	                </div>
	            </div>
	            <div class="popup-stats">
	                <div class="popup-stat">
	                    <img class="popup-stat-icon" id="popup-faction-icon" src="" alt="">
	                    <span id="popup-faction-name"></span>
	                </div>
	                <div class="popup-stat">
	                    <span class="popup-stat-label">Avg Level</span>
	                    <span class="popup-stat-value" id="popup-avg-level"></span>
	                </div>
	                <div id="popup-raid-chips"></div>
	            </div>
	        </div>
	    </div>
	</div>
</div>
<div class="row" id="realms">	
	<div class="main">
		<a name="realms" id="realms"></a>	
		<h2>Realms</h2>	
		<div class="content realms" id="filtered-content">
			<?php
			if(checkRealm($conn)){
				if(checkRealmState($conn) == 1){
					?>
					<?php echo '
					<div id="filter-nfts">
						<label for="filterRealms"><strong>Sort By:</strong></label>
						<select onchange="javascript:filterRealms(this.options[this.selectedIndex]);" name="filterRealms" id="filterRealms">';
							echo '<optgroup label="Eligible">';
								echo '<option value="weakness">Weakness</option>';
								echo '<option value="strength">Strength</option>';
								echo '<option value="wealth">Wealth</option>';
								echo '<option value="random">Random</option>';
							echo '</optgroup>';
							echo '<optgroup label="All">';
								echo '<option value="weakness">Weakness</option>';
								echo '<option value="strength">Strength</option>';
								echo '<option value="wealth">Wealth</option>';
								echo '<option value="random">Random</option>';
							echo '</optgroup>';
						echo '
						</select>
					</div>';?>
					<?php
					$sort = "weakness";
					echo "<div id='realms-list'>";
					getRealms($conn, $sort, "Eligible");
					echo "</div>";
				}
			}
			?>
		</div>
	</div>
</div>
<?php } ?>
<?php
}else{
	echo "<div class='row'>";
	echo "<div class='side'>";
	echo "<h2>Connect Wallet</h2>";
	echo "<div class='content' id='player-stats'>";
	renderWalletConnection("missions");
	echo "</div>";
	echo "</div>";
	echo "<div class='main'>";
	echo "<h2>Welcome to Skulliance</h2>";
	echo "<p>Please connect a Cardano wallet to view realms.<br><br>Once you begin staking your NFTs, you will need to become a Skulliance member before you can claim items from the store.<br><br><a href='info.php'>View info on how to become a member of Skulliance.</a></p>";
	echo "</div>";
	echo "</div>";
} 
?>
<?php if($realm_status){ ?>
<div id="quick-menu">
	<img id="locations-icon" title="Locations" src="icons/locations.png" onclick="toggleSections('locations');">
	<img id="map-icon" title="Map" src="icons/map.png" onclick="toggleSections('map');">
	<img id="realm-icon" title="Realm" src="icons/realm.png" onclick="toggleSections('realm');">
	<img id="stats-icon" title="Stats" src="icons/stats.png" onclick="toggleSections('stats');">
	<img id="raids-icon" title="Raids" src="icons/raids.png" onclick="toggleSections('raids');">
	<img id="realms-icon" title="Realms" src="icons/quests.png" onclick="toggleSections('realms');">
</div>
<?php } ?>
	<!-- Inventory Info Modal -->
	<div id="inventory-info-modal" class="modal" style="display:none;">
		<div class="raid-modal-content" style="max-width:560px;max-height:80vh;overflow-y:auto;">
			<div class="raid-modal-header">
				<h2 style="margin:0;font-size:1rem;letter-spacing:0.04em;">Location Consumables Guide</h2>
				<button class="raid-modal-close" onclick="closeInventoryInfoModal()" aria-label="Close">&times;</button>
			</div>
			<p style="font-size:0.78rem;opacity:0.55;margin:0 0 14px;">Each location holds one of each consumable type. Offense locations: Armory, Barracks, Crypt, and Portal (Transport). Defense locations: Tower, Factory, and Mine.</p>
			<div class="inv-info-grid">
				<div class="inv-info-item">
					<img class="icon" src="icons/100-success.png" onerror="this.src='icons/skull.png'"/>
					<div>
						<strong>+4% Success</strong>
						<p>Adds 4% to the success chance for this location's type (offense/defense). Averaged across all locations in the group, up to 10% total. Survives until the location takes real damage.</p>
					</div>
				</div>
				<div class="inv-info-item">
					<img class="icon" src="icons/75-success.png" onerror="this.src='icons/skull.png'"/>
					<div>
						<strong>+3% Success</strong>
						<p>Adds 3% to the success chance for this location's type (offense/defense). Averaged across all locations in the group, up to 10% total. Survives until the location takes real damage.</p>
					</div>
				</div>
				<div class="inv-info-item">
					<img class="icon" src="icons/50-success.png" onerror="this.src='icons/skull.png'"/>
					<div>
						<strong>+2% Success</strong>
						<p>Adds 2% to the success chance for this location's type (offense/defense). Averaged across all locations in the group, up to 10% total. Survives until the location takes real damage.</p>
					</div>
				</div>
				<div class="inv-info-item">
					<img class="icon" src="icons/25-success.png" onerror="this.src='icons/skull.png'"/>
					<div>
						<strong>+1% Success</strong>
						<p>Adds 1% to the success chance for this location's type (offense/defense). Averaged across all locations in the group, up to 10% total. Survives until the location takes real damage.</p>
					</div>
				</div>
				<div class="inv-info-item">
					<img class="icon" src="icons/fast-forward.png" onerror="this.src='icons/skull.png'"/>
					<div>
						<strong>Fast Forward</strong>
						<p>Halves the upgrade duration for this location (rounded up, minimum 1 day). Burns when the upgrade completes or if the location takes real damage before it does.</p>
					</div>
				</div>
				<div class="inv-info-item">
					<img class="icon" src="icons/double-rewards.png" onerror="this.src='icons/skull.png'"/>
					<div>
						<strong>Double Rewards</strong>
						<p>Acts as a damage shield. When this location would take a hit, the shield absorbs it — only Double Rewards is consumed and all other consumables on this location survive intact. Burns on use.</p>
					</div>
				</div>
				<div class="inv-info-item">
					<img class="icon" src="icons/random-reward.png" onerror="this.src='icons/skull.png'"/>
					<div>
						<strong>Random Reward</strong>
						<p>When all locations of the same type (offense/defense) are stocked, a successful raid or defense triggers a free level credit to a random location. All Random Rewards in that group are consumed when triggered.</p>
					</div>
				</div>
			</div>
			<div class="raid-modal-footer">
				<input type="button" class="small-button" value="Close" onclick="closeInventoryInfoModal()"/>
			</div>
		</div>
	</div>

	<!-- Location Modal (Portal / Barracks / Crypt / Tower / Mine / Factory / Armory) -->
	<div id="location-modal-overlay" onclick="closeLocationModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;"></div>
	<div id="location-modal" class="modal" style="display:none;z-index:1001;" role="dialog" aria-modal="true">
		<div class="raid-modal-content" style="max-width:600px;max-height:85vh;overflow-y:auto;" id="location-modal-inner">
			<div class="raid-modal-header" id="location-modal-header">
				<h2 style="margin:0;font-size:1rem;letter-spacing:0.04em;" id="location-modal-title">Loading...</h2>
				<button class="raid-modal-close" onclick="closeLocationModal()" aria-label="Close">&times;</button>
			</div>
			<div id="location-modal-body" style="padding:4px 0 8px;">
				<div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div>
			</div>
			<div class="raid-modal-footer" id="location-modal-footer" style="display:none;">
				<input type="button" class="small-button" value="Close" onclick="closeLocationModal()"/>
			</div>
		</div>
	</div>

	<!-- Raid Soldier Selection Modal -->
	<div id="raid-soldiers-overlay" onclick="closeRaidSoldierModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:1004;"></div>
	<div id="raid-soldiers-modal" class="modal" style="display:none;z-index:1005;" role="dialog" aria-modal="true">
		<div class="raid-modal-content" style="max-width:560px;max-height:82vh;overflow-y:auto;">
			<div class="raid-modal-header">
				<h2 style="margin:0;font-size:1rem;">Select Soldiers for Raid</h2>
				<button class="raid-modal-close" onclick="closeRaidSoldierModal()" aria-label="Close">&times;</button>
			</div>
			<p style="font-size:0.78rem;opacity:0.55;margin:0 0 10px;">Select up to <?php echo intval($levels[1]); ?> trained soldiers from your Barracks (Portal level <?php echo intval($levels[1]); ?>). Their equipped weapon and armor will go on this raid.</p>
			<div id="raid-soldiers-grid"><div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div></div>
			<div class="raid-modal-footer">
				<span id="raid-soldiers-count" style="font-size:0.8rem;opacity:0.65;margin-right:auto;">0 / 10 selected</span>
				<button class="button" onclick="confirmRaidSoldierSelection()">Continue</button>
				<button class="small-button" onclick="skipRaidSoldierSelection()">Skip</button>
				<button class="small-button" onclick="closeRaidSoldierModal()">Cancel</button>
			</div>
		</div>
	</div>

	<!-- Enlist Picker Modal (child of barracks) -->
	<div id="enlist-modal-overlay" onclick="closeEnlistPicker()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1002;"></div>
	<div id="enlist-modal" class="modal" style="display:none;z-index:1003;" role="dialog" aria-modal="true">
		<div class="raid-modal-content" style="max-width:580px;max-height:80vh;overflow-y:auto;">
			<div class="raid-modal-header">
				<h2 style="margin:0;font-size:1rem;">Enlist Soldiers</h2>
				<button class="raid-modal-close" onclick="closeEnlistPicker()" aria-label="Close">&times;</button>
			</div>
			<p style="font-size:0.78rem;opacity:0.55;margin:0 0 12px;">Select NFTs to enlist. Partner NFTs cost 2 slots each.</p>
			<div id="enlist-picker-body"><div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div></div>
			<div class="raid-modal-footer">
				<span id="enlist-selected-count" style="font-size:0.8rem;opacity:0.65;margin-right:auto;">0 selected</span>
				<input type="button" class="button" value="Enlist Selected" onclick="confirmEnlist()"/>
				<input type="button" class="small-button" value="Cancel" onclick="closeEnlistPicker()"/>
			</div>
		</div>
	</div>

	<!-- Raid Consumables Modal -->
	<div id="raid-consumables-modal" class="modal" style="display:none;">
		<div class="raid-modal-content">
			<div class="raid-modal-header">
				<h2 style="margin:0;font-size:1rem;letter-spacing:0.04em;">Customize Raid Consumables</h2>
				<button class="raid-modal-close" onclick="closeRaidConsumablesModal()" aria-label="Close">&times;</button>
			</div>
			<p style="font-size:0.78rem;opacity:0.55;margin:0 0 14px;">Select which consumables to use. Checked items will be consumed on launch.</p>
			<div id="raid-con-modal-items" class="raid-con-item-grid"></div>
			<div id="raid-con-modal-summary" class="raid-con-summary"></div>
			<div class="raid-modal-footer">
				<label style="font-size:0.8rem;opacity:0.65;margin-right:auto;"><input type="checkbox" id="raid-con-save-config" checked> Save configuration</label>
				<input type="button" class="button" id="raid-con-modal-start-btn" value="Start Raid" onclick="startRaidFromModal()"/>
				<input type="button" class="small-button" value="Cancel" onclick="closeRaidConsumablesModal()"/>
			</div>
		</div>
	</div>

</div>
</div>
</body>
<?php
getFactionsRealmsMapData($conn);
getActiveRaidsMapData($conn);
if($realm_status && isset($_SESSION['userData']['user_id'])){
	echo "<script>window.myRealmId = ".(int)getRealmID($conn).";</script>";
}
// Close DB Connection
$conn->close();
?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="map.js?var=<?php echo rand(0,999); ?>"></script>
<?php if($realm_status){ ?>
<style>
@keyframes lp { 0%,100%{opacity:.3;transform:scale(.92)} 50%{opacity:1;transform:scale(1)} }
@keyframes lb { to { width:90%; } }
/* Padding so content can scroll clear of the fixed quick-menu (~120px tall) */
#locations, #realm, #stats, #raids, #realms { padding-bottom: 100px; }
#map #container-wrapper { padding-top: 35px; padding-bottom: 100px; }
/* Soldiers / Location Modals */
.soldiers-stat-row { display:flex; gap:12px; flex-wrap:wrap; margin-bottom:12px; }
.soldiers-stat { background:rgba(255,255,255,0.06); border-radius:8px; padding:10px 14px; flex:1; min-width:100px; }
.soldiers-stat-label { display:block; font-size:0.72rem; opacity:0.5; letter-spacing:0.04em; text-transform:uppercase; margin-bottom:3px; }
.soldiers-stat-value { display:block; font-size:1.1rem; font-weight:bold; color:#00c8a0; }
.soldiers-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(100px,1fr)); gap:10px; margin-top:8px; }
.soldier-card { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:8px; text-align:center; font-size:0.75rem; display:flex; flex-direction:column; align-items:center; gap:4px; }
.soldier-card.selected { border-color:#00c8a0; background:rgba(0,200,160,0.1); }
.soldier-card.soldier-ready { border-color:#00c8a0; }
.soldier-card.soldier-dead { opacity:0.7; }
.soldier-nft-img { width:64px; height:64px; object-fit:cover; border-radius:6px; }
.soldier-name { font-size:0.7rem; opacity:0.8; word-break:break-word; text-align:center; }
.soldier-status { font-size:0.68rem; padding:2px 6px; border-radius:4px; background:rgba(255,255,255,0.08); }
.soldier-status.status-ready { background:rgba(0,200,160,0.2); color:#00c8a0; }
.soldier-status.status-deployed { background:rgba(74,144,217,0.2); color:#4a90d9; }
.soldier-status.status-training { background:rgba(255,200,0,0.15); color:#ffc800; }
.soldier-status.status-dead { background:rgba(255,60,60,0.15); color:#ff6060; }
.soldier-badge { font-size:0.62rem; padding:1px 5px; border-radius:3px; background:rgba(255,150,0,0.2); color:#ffa040; }
.partner-badge { background:rgba(150,100,255,0.2); color:#b08aff; }
.soldier-gear-row { display:flex; gap:6px; justify-content:center; flex-wrap:wrap; margin-top:4px; }
.soldier-gear-slot { display:flex; flex-direction:column; align-items:center; gap:2px; font-size:0.7rem; opacity:0.85; }
.gear-label { font-size:0.65rem; opacity:0.7; }
.gear-empty { font-size:0.65rem; opacity:0.4; }
.soldier-gear-controls { display:flex; flex-direction:column; gap:4px; width:100%; margin-top:4px; }
.soldier-gear-controls .dropdown { font-size:0.7rem; padding:2px 4px; width:100%; }
.soldiers-table { border-collapse:collapse; }
.soldiers-table th, .soldiers-table td { padding:4px 10px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.08); }
.soldiers-table th { font-size:0.72rem; opacity:0.55; font-weight:normal; text-transform:uppercase; }
</style>
<script type='text/javascript'>
	//if($(window).width() <= 700){
		document.getElementById('back-to-top-button').style.zIndex = "-1";
		document.getElementById('quick-menu').style.display = "block";
		document.getElementById('map').style.position = "relative";
		document.getElementById('map').style.top = '-100px';
		document.getElementById('stats').style.position = "relative";
		document.getElementById('stats').style.top = '-55px';
		document.getElementById('raids').style.position = "relative";
		document.getElementById('raids').style.top = '-55px';
		document.getElementById('realms').style.position = "relative";
		document.getElementById('realms').style.top = '-95px';
		document.getElementById('realm').style.position = "relative";
		document.getElementById('realm').style.top = '-25px';
		if($(window).width() > 700){
			document.getElementById('realm-icon').style.display = "none";
		}else{
			document.getElementById('map-icon').style.display = "none";
		}
		if(window.location.hash == "#realm-image" || window.location.hash == "#realm-name"){
			if($(window).width() > 700){
				document.getElementById('locations').style.display = "block";
				document.getElementById('locations-icon').classList.add("selected");
				document.getElementById('realm').style.display = "block";
			}else{
				document.getElementById('realm').style.display = "block";
				document.getElementById('realm-icon').classList.add("selected");
				document.getElementById('locations').style.display = "none";
				document.getElementById('locations-icon').classList.remove("selected");
			}
			document.getElementById('map').style.display = "none";
			document.getElementById('map-icon').classList.remove("selected");
			document.getElementById('stats').style.display = "none";
			document.getElementById('stats-icon').classList.remove("selected");
			document.getElementById('raids').style.display = "none";
			document.getElementById('raids-icon').classList.remove("selected");
			document.getElementById('realms').style.display = "none";
			document.getElementById('realms-icon').classList.remove("selected");
		}else{
			document.getElementById('locations').style.display = "block";
			document.getElementById('locations-icon').classList.add("selected");
			if($(window).width() <= 700){
				document.getElementById('realm').style.display = "none";
				document.getElementById('realm-icon').classList.remove("selected");
			}
			document.getElementById('map').style.display = "none";
			document.getElementById('map-icon').classList.remove("selected");
			document.getElementById('stats').style.display = "none";
			document.getElementById('stats-icon').classList.remove("selected");
			document.getElementById('raids').style.display = "none";
			document.getElementById('raids-icon').classList.remove("selected");
			document.getElementById('realms').style.display = "none";
			document.getElementById('realms-icon').classList.remove("selected");
		}
	/*}else{
		document.getElementById('quick-menu').style.display = "none";
		//document.getElementById('row1').style.position = "relative";
		//document.getElementById('row1').style.top = '-65px';
	}*/
	
	function toggleSections(selection){
		window.scrollTo(0, 0);
		var sections = ['locations','map','realm','stats','raids','realms'];
		sections.forEach(function(s){
			document.getElementById(s).style.display = 'none';
			document.getElementById(s+'-icon').classList.remove('selected');
		});

		if(!document.getElementById(selection)) return;
		document.getElementById(selection+'-icon').classList.add('selected');

		// Map: just toggle visibility — reinitializing the SVG map via AJAX is not supported
		if(selection === 'map'){
			document.getElementById('map').style.display = 'block';
			return;
		}

		var loadingHtml = '<div style="display:flex;flex-direction:column;align-items:center;justify-content:center;gap:16px;min-height:70vh;">'
			+ '<div style="font-size:2.2rem;animation:lp 1.2s ease-in-out infinite;">&#x1F480;</div>'
			+ '<div style="width:180px;height:3px;background:rgba(255,255,255,.08);border-radius:2px;overflow:hidden;">'
			+   '<div style="height:100%;background:#00c8a0;width:0%;animation:lb 4s ease-out forwards;"></div>'
			+ '</div>'
			+ '<div style="font-size:.75rem;color:rgba(255,255,255,.35);letter-spacing:.1em;text-transform:uppercase;">Loading</div>'
			+ '</div>';

		var container = document.getElementById(selection);
		container.style.display = 'block';
		container.innerHTML = loadingHtml;

		$.get('ajax/get-' + selection + '.php', function(html){
			container.innerHTML = html;
			if(typeof _checkStockButtonStates === 'function') _checkStockButtonStates();
		}).fail(function(){
			container.innerHTML = '<p style="padding:20px;opacity:0.5;text-align:center;">Failed to load section.</p>';
		});

		// Desktop: locations shows realm panel alongside it
		if($(window).width() > 700 && selection === 'locations'){
			var realmEl = document.getElementById('realm');
			realmEl.style.display = 'block';
			realmEl.innerHTML = loadingHtml;
			$.get('ajax/get-realm.php', function(html){
				realmEl.innerHTML = html;
			});
		}
		// Mobile: realm shows map alongside it
		if($(window).width() <= 700 && selection === 'realm'){
			document.getElementById('map').style.display = 'block';
		}
	}
	_checkStockButtonStates();

	/* ── RAID SOLDIER SELECTION ──────────────────────────── */
	// Intercept openRaidConsumablesModal to show soldier picker first
	var _portalLevel              = <?php echo intval($levels[1]); ?>;
	var _raidSoldierSelectedIds   = [];
	var _raidSoldiersDefenseId    = null;
	var _raidSoldiersDuration     = null;
	var _raidSoldiersButton       = null;
	var _raidSoldiersConsumables  = null;

	// Intercept direct startRaid (raid card with pre-set consumables)
	var _origStartRaid = typeof startRaid === 'function' ? startRaid : null;
	startRaid = function(raidButton, defenseID, duration) {
		_raidSoldiersButton      = raidButton;
		_raidSoldiersDefenseId   = defenseID;
		_raidSoldiersDuration    = duration;
		_raidSoldiersConsumables = 'direct'; // signals this came from direct start
		_raidSoldierSelectedIds  = [];
		document.getElementById('raid-soldiers-grid').innerHTML = '<div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div>';
		document.getElementById('raid-soldiers-overlay').style.display = 'block';
		document.getElementById('raid-soldiers-modal').style.display   = 'flex';
		$.getJSON('ajax/get-available-raiders.php', function(raiders) {
			_renderRaidSoldierGrid(raiders);
		}).fail(function() {
			document.getElementById('raid-soldiers-overlay').style.display = 'none';
			document.getElementById('raid-soldiers-modal').style.display   = 'none';
			if (_origStartRaid) _origStartRaid(raidButton, defenseID, duration);
		});
	};

	var _origOpenRaidConsumablesModal = typeof openRaidConsumablesModal === 'function' ? openRaidConsumablesModal : null;
	openRaidConsumablesModal = function(realmId, duration) {
		_raidSoldiersConsumables = 'modal';
		_raidSoldiersDefenseId = realmId;
		_raidSoldiersDuration  = duration;
		_raidSoldierSelectedIds = [];
		document.getElementById('raid-soldiers-grid').innerHTML = '<div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div>';
		document.getElementById('raid-soldiers-overlay').style.display = 'block';
		document.getElementById('raid-soldiers-modal').style.display   = 'flex';
		$.getJSON('ajax/get-available-raiders.php', function(raiders) {
			_renderRaidSoldierGrid(raiders);
		}).fail(function() {
			// No soldiers or error — skip to consumables
			document.getElementById('raid-soldiers-overlay').style.display = 'none';
			document.getElementById('raid-soldiers-modal').style.display   = 'none';
			if (_origOpenRaidConsumablesModal) _origOpenRaidConsumablesModal(realmId, duration);
		});
	};

	function _renderRaidSoldierGrid(raiders) {
		var grid = document.getElementById('raid-soldiers-grid');
		if (!raiders || raiders.length === 0) {
			grid.innerHTML = '<p style="opacity:0.55;font-size:0.85rem;text-align:center;">No trained soldiers available. Enlist and train soldiers in Barracks first.</p>';
			return;
		}
		var html = '<div class="soldiers-grid">';
		raiders.forEach(function(s) {
			var weaponInfo = s.weapon_id ? ('Lv' + s.weapon_level + ' ' + s.weapon_name) : 'No Weapon';
			var armorInfo  = s.armor_id  ? ('Lv' + s.armor_level  + ' ' + s.armor_name)  : 'No Armor';
			html += '<div class="soldier-card raid-soldier-pick" data-soldier-id="' + s.soldier_id + '" onclick="toggleRaidSoldierSelect(this)">';
			html += '<img class="soldier-nft-img" src="' + (s.ipfs || 'icons/skull.png') + '" onerror="this.src=\'icons/skull.png\'" />';
			html += '<div class="soldier-name">' + s.nft_name + '</div>';
			html += '<div class="soldier-status" style="font-size:0.65rem;">' + weaponInfo + '</div>';
			html += '<div class="soldier-status" style="font-size:0.65rem;">' + armorInfo + '</div>';
			html += '</div>';
		});
		html += '</div>';
		grid.innerHTML = html;
		_updateRaidSoldierCount();
	}

	function toggleRaidSoldierSelect(el) {
		var sid = parseInt($(el).data('soldier-id'));
		var idx = _raidSoldierSelectedIds.indexOf(sid);
		if (idx >= 0) {
			_raidSoldierSelectedIds.splice(idx, 1);
			$(el).removeClass('selected');
		} else {
			if (_raidSoldierSelectedIds.length >= _portalLevel) {
				openNotify('Maximum ' + _portalLevel + ' soldiers per raid (Portal level ' + _portalLevel + ').');
				return;
			}
			_raidSoldierSelectedIds.push(sid);
			$(el).addClass('selected');
		}
		_updateRaidSoldierCount();
	}

	function _updateRaidSoldierCount() {
		document.getElementById('raid-soldiers-count').textContent = _raidSoldierSelectedIds.length + ' / ' + _portalLevel + ' selected';
	}

	function confirmRaidSoldierSelection() {
		document.getElementById('raid-soldiers-overlay').style.display = 'none';
		document.getElementById('raid-soldiers-modal').style.display   = 'none';
		_proceedAfterSoldierPick();
	}

	function skipRaidSoldierSelection() {
		_raidSoldierSelectedIds = [];
		document.getElementById('raid-soldiers-overlay').style.display = 'none';
		document.getElementById('raid-soldiers-modal').style.display   = 'none';
		_proceedAfterSoldierPick();
	}

	function closeRaidSoldierModal() {
		document.getElementById('raid-soldiers-overlay').style.display = 'none';
		document.getElementById('raid-soldiers-modal').style.display   = 'none';
		_raidSoldiersDefenseId  = null;
		_raidSoldiersDuration   = null;
		_raidSoldierSelectedIds = [];
		_raidSoldiersConsumables = null;
		_raidSoldiersButton     = null;
	}

	function _proceedAfterSoldierPick() {
		if (_raidSoldiersConsumables === 'direct') {
			// Direct startRaid path — build soldiers param and launch immediately
			var soldiersParam = '';
			_raidSoldierSelectedIds.forEach(function(sid) { soldiersParam += '&soldiers[]=' + sid; });
			var btn = _raidSoldiersButton;
			var defId = _raidSoldiersDefenseId;
			var dur   = _raidSoldiersDuration;
			var allEl = document.getElementById('raid-all-items-' + defId);
			var consumablesParam = '';
			if (allEl && allEl.checked) {
				var mode = allEl.dataset.mode || 'all';
				var savedIds = allEl.dataset.savedIds ? allEl.dataset.savedIds.split(',').map(Number).filter(Boolean) : [];
				var conItems = document.querySelectorAll('[id^="raid-con-item-"]');
				if (mode === 'saved') {
					savedIds.forEach(function(id) { consumablesParam += '&consumables[]=' + id; });
				} else {
					consumablesParam = '&consumables=all';
				}
			}
			_raidSoldierSelectedIds = [];
			var xhttp = new XMLHttpRequest();
			xhttp.open('GET', 'ajax/start_raid.php?defense_id=' + defId + '&duration=' + dur + consumablesParam + soldiersParam, true);
			xhttp.send();
			xhttp.onreadystatechange = function() {
				if (xhttp.readyState == XMLHttpRequest.DONE && xhttp.status == 200) {
					var data = xhttp.responseText;
					var conRow = document.getElementById('raid-con-row-' + defId);
					if (conRow) conRow.style.display = 'none';
					if (data != '' && btn) btn.outerHTML = data;
				}
			};
		} else {
			// Consumables modal path
			if (_origOpenRaidConsumablesModal && _raidSoldiersDefenseId) {
				_origOpenRaidConsumablesModal(_raidSoldiersDefenseId, _raidSoldiersDuration);
			}
		}
	}

	// Override startRaidFromModal to include soldiers param
	startRaidFromModal = function() {
		var defenseID = _raidModalDefenseId;
		var duration  = _raidModalDuration;
		if (!defenseID) return;
		var checks = document.querySelectorAll('#raid-con-modal-items .raid-con-check:checked');
		var consumablesParam = '';
		var savedCids = [];
		checks.forEach(function(ch) {
			consumablesParam += '&consumables[]=' + ch.getAttribute('data-id');
			savedCids.push(parseInt(ch.getAttribute('data-id')));
		});
		var saveEl  = document.getElementById('raid-con-save-config');
		var saveParam = (!saveEl || saveEl.checked) ? '&save_config=1' : '';
		if (saveParam) _updateAllRaidConfigCheckboxes(savedCids);
		var soldiersParam = '';
		_raidSoldierSelectedIds.forEach(function(sid) { soldiersParam += '&soldiers[]=' + sid; });
		closeRaidConsumablesModal();
		var raidButton = document.getElementById('raid-btn-' + defenseID);
		var xhttp = new XMLHttpRequest();
		xhttp.open('GET', 'ajax/start_raid.php?defense_id=' + defenseID + '&duration=' + duration + consumablesParam + saveParam + soldiersParam, true);
		xhttp.send();
		xhttp.onreadystatechange = function() {
			if (xhttp.readyState == XMLHttpRequest.DONE && xhttp.status == 200) {
				var data = xhttp.responseText;
				var conRow = document.getElementById('raid-con-row-' + defenseID);
				if (conRow) conRow.style.display = 'none';
				if (data != '' && raidButton) raidButton.outerHTML = data;
			}
		};
		_raidSoldierSelectedIds = [];
	};

	/* ── LOCATION MODALS ─────────────────────────────────── */
	var _locModalTitles = {1:'Portal Report',2:'Armory',3:'Tower Garrison',4:'Barracks',5:'Factory',6:'Crypt',7:'Mine'};
	var _locModalEndpoints = {1:'get-portal-report',2:'get-armory',3:'get-tower',4:'get-barracks',5:'get-factory',6:'get-crypt',7:'get-mine'};

	function openLocationModal(loc_id) {
		var title    = _locModalTitles[loc_id]    || 'Location';
		var endpoint = _locModalEndpoints[loc_id] || null;
		if (!endpoint) return;
		document.getElementById('location-modal-title').textContent = title;
		document.getElementById('location-modal-body').innerHTML    = '<div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div>';
		document.getElementById('location-modal-footer').style.display = 'flex';
		document.getElementById('location-modal-overlay').style.display = 'block';
		document.getElementById('location-modal').style.display          = 'flex';
		$.get('ajax/' + endpoint + '.php', function(html) {
			document.getElementById('location-modal-body').innerHTML = html;
		}).fail(function() {
			document.getElementById('location-modal-body').innerHTML = '<p style="opacity:0.5;text-align:center;">Failed to load.</p>';
		});
		window._currentLocModal = loc_id;
	}

	function closeLocationModal() {
		document.getElementById('location-modal-overlay').style.display = 'none';
		document.getElementById('location-modal').style.display          = 'none';
		window._currentLocModal = null;
	}

	function refreshLocationModal() {
		if (window._currentLocModal) openLocationModal(window._currentLocModal);
	}

	/* ── BARRACKS ─────────────────────────────────────────── */
	function removeSoldier(soldier_id) {
		openConfirm('Remove this soldier from the Barracks?', function() {
			$.post('ajax/remove-soldier.php', {soldier_id: soldier_id}, function(resp) {
				try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
				if (r.success) { refreshLocationModal(); }
				else { openNotify('Could not remove soldier. They may be deployed.'); }
			});
		});
	}

	function autoFillBarracks() {
		$.get('ajax/get-eligible-nfts.php', function(html) {
			// Collect all nft_ids and enlist them all
			var tmp = $('<div>').html(html);
			var ids = [];
			tmp.find('.enlist-candidate').each(function() { ids.push($(this).data('nft-id')); });
			if (ids.length === 0) { openNotify('No eligible NFTs available to enlist.'); return; }
			$.post('ajax/enlist-soldier.php', {nft_ids: ids}, function(resp) {
				try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
				if (r.success) {
					openNotify(r.enlisted + ' soldier' + (r.enlisted != 1 ? 's' : '') + ' enlisted.');
					refreshLocationModal();
				}
			});
		});
	}

	function openEnlistPicker() {
		document.getElementById('enlist-picker-body').innerHTML = '<div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div>';
		document.getElementById('enlist-modal-overlay').style.display = 'block';
		document.getElementById('enlist-modal').style.display          = 'flex';
		$.get('ajax/get-eligible-nfts.php', function(html) {
			document.getElementById('enlist-picker-body').innerHTML = html;
			_updateEnlistCount();
		});
	}

	function closeEnlistPicker() {
		document.getElementById('enlist-modal-overlay').style.display = 'none';
		document.getElementById('enlist-modal').style.display          = 'none';
	}

	function toggleEnlistSelect(el) {
		$(el).toggleClass('selected');
		_updateEnlistCount();
	}

	function _updateEnlistCount() {
		var count = $('#enlist-picker-body .enlist-candidate.selected').length;
		document.getElementById('enlist-selected-count').textContent = count + ' selected';
	}

	function confirmEnlist() {
		var ids = [];
		$('#enlist-picker-body .enlist-candidate.selected').each(function() { ids.push($(this).data('nft-id')); });
		if (ids.length === 0) { openNotify('Select at least one NFT to enlist.'); return; }
		$.post('ajax/enlist-soldier.php', {nft_ids: ids}, function(resp) {
			try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
			if (r.success) {
				closeEnlistPicker();
				openNotify(r.enlisted + ' soldier' + (r.enlisted != 1 ? 's' : '') + ' enlisted and now in training.');
				refreshLocationModal();
			}
		});
	}

	/* ── CRYPT ────────────────────────────────────────────── */
	function resurrectAllSoldiers() {
		$.post('ajax/resurrect-soldiers.php', {}, function(resp) {
			try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
			if (r.success) { openNotify('Soldiers have been resurrected and returned to Reserve.'); refreshLocationModal(); }
			else { openNotify('No soldiers ready for resurrection yet.'); }
		});
	}

	/* ── TOWER ────────────────────────────────────────────── */
	function assignToTower(soldier_id) {
		$.post('ajax/tower-action.php', {action:'assign', soldier_id:soldier_id}, function(resp) {
			try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
			if (r.success) refreshLocationModal();
			else openNotify('Could not assign to Tower. Tower may be full.');
		});
	}

	function removeFromTower(soldier_id) {
		$.post('ajax/tower-action.php', {action:'remove', soldier_id:soldier_id}, function() { refreshLocationModal(); });
	}

	function equipWeapon(soldier_id, weapon_id) {
		$.post('ajax/tower-action.php', {action:'weapon', soldier_id:soldier_id, value:weapon_id}, function() { refreshLocationModal(); });
	}

	function equipArmor(soldier_id, armor_id) {
		$.post('ajax/tower-action.php', {action:'armor', soldier_id:soldier_id, value:armor_id}, function() { refreshLocationModal(); });
	}

	/* ── GEAR EQUIP / UNEQUIP ─────────────────────────────── */
	function equipGearItem(type, item_id) {
		var sel = document.getElementById('equip-target-' + type + '-' + item_id);
		var soldier_id = sel ? sel.value : '';
		if (!soldier_id) { openNotify('Select a soldier first.'); return; }
		$.post('ajax/gear-action.php', {action:'equip', soldier_id:soldier_id, item_id:item_id, type:type}, function(resp) {
			try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
			if (r.success) { refreshLocationModal(); } else { openNotify('Could not equip gear.'); }
		});
	}

	function unequipGearItem(soldier_id, type) {
		$.post('ajax/gear-action.php', {action:'unequip', soldier_id:soldier_id, type:type}, function(resp) {
			try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
			if (r.success) { refreshLocationModal(); } else { openNotify('Could not unequip gear.'); }
		});
	}

	/* ── REALM LOG CLAIM ──────────────────────────────────── */
	function claimRealmLogs() {
		$.get('ajax/claim-realm-logs.php', function(resp) {
			try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
			if (r.success) { openNotify('Rewards claimed!'); refreshLocationModal(); }
			else { openNotify('Nothing to claim.'); }
		});
	}
</script>
<?php } ?>
</html>