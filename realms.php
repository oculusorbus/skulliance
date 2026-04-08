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

if(isset($_SESSION['userData']['user_id'])){
// Run completed raid resolution first so endRaid updates consumables before locations panel renders
$outgoing_completed = getRaids($conn, "outgoing", "completed");
$incoming_completed = getRaids($conn, "incoming", "completed");
?>
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
							<div class="realm-deactivate-wrap" style="flex-shrink:0;text-align:right;display:flex;gap:6px;align-items:center;">
								<input class="small-button" type="button" value="Guide" onclick="openGuideModal()"/>
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
							$loc_modal_map = array(1=>'Manage Portal',2=>'Manage Armory',3=>'Manage Tower',4=>'Manage Barracks',5=>'Manage Factory',6=>'Manage Crypt',7=>'Manage Mine');
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
	<div id="inventory-info-overlay" onclick="closeInventoryInfoModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1006;"></div>
	<div id="inventory-info-modal" class="modal" style="display:none;z-index:1007;" onclick="closeInventoryInfoModal()">
		<div class="raid-modal-content" style="max-width:560px;max-height:80vh;overflow-y:auto;" onclick="event.stopPropagation()">
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

	<!-- Guide Modal -->
	<div id="guide-modal-overlay" onclick="closeGuideModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:1000;"></div>
	<div id="guide-modal" class="modal" style="display:none;z-index:1001;" onclick="closeGuideModal()">
		<div class="raid-modal-content" style="max-width:640px;max-height:85vh;overflow-y:auto;font-size:0.85rem;line-height:1.55;" onclick="event.stopPropagation()">
			<div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
				<h2 style="margin:0;font-size:1.1rem;letter-spacing:0.05em;color:#00c8a0;">Realm &amp; Raids Guide</h2>
				<button class="raid-modal-close" onclick="closeGuideModal()" aria-label="Close">&times;</button>
			</div>
			<div style="display:flex;flex-direction:column;gap:18px;">
				<div style="background:rgba(0,200,160,0.07);border:1px solid rgba(0,200,160,0.2);border-radius:8px;padding:14px 16px;">
					<div style="font-weight:600;color:#00c8a0;margin-bottom:6px;">&#127984; Your Realm</div>
					<p style="margin:0;opacity:0.8;">Your Realm is your base of operations. Build and upgrade locations to grow your power, defend against raiders, and earn rewards every night. Join a faction for your favorite project and earn even more rewards with the help of your fellow community members.</p>
				</div>
				<div style="background:rgba(74,144,217,0.07);border:1px solid rgba(74,144,217,0.2);border-radius:8px;padding:14px 16px;">
					<div style="font-weight:600;color:#4a90d9;margin-bottom:8px;">&#128205; Locations</div>
					<div style="display:grid;grid-template-columns:1fr 1fr;gap:8px;">
						<div><span style="opacity:0.55;font-size:0.78rem;">BARRACKS</span><br/>Enlist soldiers and train them for deployment. Higher level = faster training.</div>
						<div><span style="opacity:0.55;font-size:0.78rem;">ARMORY</span><br/>Generates gear drops nightly. Equip weapons &amp; armor to boost soldier power.</div>
						<div><span style="opacity:0.55;font-size:0.78rem;">TOWER</span><br/>Garrison trained soldiers here to defend your realm. Up to 10 defenders, +1% defense per soldier.</div>
						<div><span style="opacity:0.55;font-size:0.78rem;">PORTAL</span><br/>Launch raids against other realms. Higher level = more simultaneous raids &amp; larger squads.</div>
						<div><span style="opacity:0.55;font-size:0.78rem;">CRYPT</span><br/>Fallen soldiers rest here. Higher level = faster resurrection time.</div>
						<div><span style="opacity:0.55;font-size:0.78rem;">MINE &amp; FACTORY</span><br/>Passive resource generation. Upgrade to increase nightly yields of CARBON and consumables.</div>
					</div>
				</div>
				<div style="background:rgba(255,150,50,0.07);border:1px solid rgba(255,150,50,0.2);border-radius:8px;padding:14px 16px;">
					<div style="font-weight:600;color:#ff9632;margin-bottom:6px;">&#9876;&#65039; Raids</div>
					<p style="margin:0 0 8px;opacity:0.8;">Send soldiers through your Portal to raid other realms. Win to loot points from their realm. Lose and risk your soldiers' lives: and take a hit to your own locations.</p>
					<p style="margin:0;opacity:0.8;">Each raid pits your squad against the defender's Tower garrison. Gear levels and consumables tip the odds. Soldiers who die go to the Crypt and must wait to be resurrected before fighting again.</p>
				</div>
				<div style="background:rgba(180,100,255,0.07);border:1px solid rgba(180,100,255,0.2);border-radius:8px;padding:14px 16px;">
					<div style="font-weight:600;color:#b464ff;margin-bottom:8px;">&#129514; Consumables</div>
					<div style="display:flex;flex-direction:column;gap:6px;">
						<div style="display:flex;gap:10px;align-items:flex-start;"><img class="icon" src="icons/fast-forward.png" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"/><span><strong>Fast Forward</strong>: Halves location upgrades, training time (Barracks) or resurrection time (Crypt) while active.</span></div>
						<div style="display:flex;gap:10px;align-items:flex-start;"><img class="icon" src="icons/double-rewards.png" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"/><span><strong>Double Rewards</strong>: Acts as a shield. Absorbs one incoming raid hit and is consumed on use.</span></div>
						<div style="display:flex;gap:10px;align-items:flex-start;"><img class="icon" src="icons/random-reward.png" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"/><span><strong>Random Reward</strong>: When all locations of the same side (offense/defense) are stocked and a raid is won, grants a free level-up to a random location.</span></div>
						<div style="display:flex;gap:10px;align-items:flex-start;"><img class="icon" src="icons/100-success.png" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"/><span><strong>+4% Success</strong>: Adds 4% to the raid success chance for this location's side (offense/defense). Averaged across all locations in the group, up to +10% total.</span></div>
						<div style="display:flex;gap:10px;align-items:flex-start;"><img class="icon" src="icons/75-success.png" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"/><span><strong>+3% Success</strong>: Adds 3% to the raid success chance for this location's side.</span></div>
						<div style="display:flex;gap:10px;align-items:flex-start;"><img class="icon" src="icons/50-success.png" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"/><span><strong>+2% Success</strong>: Adds 2% to the raid success chance for this location's side.</span></div>
						<div style="display:flex;gap:10px;align-items:flex-start;"><img class="icon" src="icons/25-success.png" onerror="this.src='icons/skull.png'" style="width:20px;height:20px;flex-shrink:0;margin-top:2px;"/><span><strong>+1% Success</strong>: Adds 1% to the raid success chance for this location's side.</span></div>
					</div>
				</div>
				<div style="background:rgba(255,200,0,0.06);border:1px solid rgba(255,200,0,0.18);border-radius:8px;padding:14px 16px;">
					<div style="font-weight:600;color:#ffc800;margin-bottom:6px;">&#128161; Getting Started</div>
					<ol style="margin:0;padding-left:18px;opacity:0.8;display:flex;flex-direction:column;gap:4px;">
						<li>Activate your Realm and upgrade your Barracks to unlock soldier training.</li>
						<li>Enlist NFTs as soldiers and train them: trained soldiers can be deployed to the Tower garrison or sent on raids.</li>
						<li>Upgrade your Armory to generate gear (weapons/armor), then equip your soldiers for better odds in combat.</li>
						<li>Garrison your best soldiers in the Tower to protect your defensive locations from incoming raids.</li>
						<li>Once your Portal is upgraded, launch more raids to loot points from rival realms.</li>
					</ol>
				</div>
			</div>
			<div class="raid-modal-footer" style="margin-top:20px;">
				<button class="small-button" onclick="closeGuideModal()">Close</button>
			</div>
		</div>
	</div>

	<!-- Location Modal (Portal / Barracks / Crypt / Tower / Mine / Factory / Armory) -->
	<div id="location-modal-overlay" onclick="closeLocationModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1000;"></div>
	<div id="location-modal" class="modal" style="display:none;z-index:1001;" role="dialog" aria-modal="true" onclick="closeLocationModal()">
		<div class="raid-modal-content" style="max-width:600px;max-height:85vh;overflow-y:auto;" id="location-modal-inner" onclick="event.stopPropagation()">
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

	<!-- Deployment Configuration Modal -->
	<div id="deploy-config-overlay" onclick="closeDeployConfig()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:1004;"></div>
	<div id="deploy-config-modal" class="modal" style="display:none;z-index:1005;" role="dialog" aria-modal="true" onclick="closeDeployConfig()">
		<div class="raid-modal-content" style="max-width:480px;max-height:85vh;overflow-y:auto;" onclick="event.stopPropagation()">
			<div class="raid-modal-header">
				<h2 style="margin:0;font-size:1rem;letter-spacing:0.04em;">Deployment Configuration</h2>
				<button class="raid-modal-close" onclick="closeDeployConfig()" aria-label="Close">&times;</button>
			</div>
			<div style="display:flex;flex-direction:column;gap:14px;padding:4px 0 10px;">
				<div class="deploy-axis">
					<div class="deploy-axis-label">Force Size</div>
					<div class="deploy-tier-group">
						<button class="deploy-tier-btn" data-axis="amount" data-value="blitz" onclick="setDeployTier('amount','blitz')">Blitz</button>
						<button class="deploy-tier-btn" data-axis="amount" data-value="tactical" onclick="setDeployTier('amount','tactical')">Tactical</button>
						<button class="deploy-tier-btn" data-axis="amount" data-value="recon" onclick="setDeployTier('amount','recon')">Recon</button>
					</div>
				</div>
				<div class="deploy-axis">
					<div class="deploy-axis-label">Weapon Priority</div>
					<div class="deploy-tier-group">
						<button class="deploy-tier-btn" data-axis="weapon" data-value="aggressive" onclick="setDeployTier('weapon','aggressive')">Aggressive</button>
						<button class="deploy-tier-btn" data-axis="weapon" data-value="balanced" onclick="setDeployTier('weapon','balanced')">Balanced</button>
						<button class="deploy-tier-btn" data-axis="weapon" data-value="stealth" onclick="setDeployTier('weapon','stealth')">Stealth</button>
					</div>
				</div>
				<div class="deploy-axis">
					<div class="deploy-axis-label">Armor Priority</div>
					<div class="deploy-tier-group">
						<button class="deploy-tier-btn" data-axis="armor" data-value="heavy" onclick="setDeployTier('armor','heavy')">Heavy</button>
						<button class="deploy-tier-btn" data-axis="armor" data-value="medium" onclick="setDeployTier('armor','medium')">Medium</button>
						<button class="deploy-tier-btn" data-axis="armor" data-value="light" onclick="setDeployTier('armor','light')">Light</button>
					</div>
				</div>
				<div id="deploy-preview" style="font-size:0.82rem;opacity:0.65;text-align:center;min-height:1.2em;"></div>
			</div>
			<div class="raid-modal-footer">
				<label style="font-size:0.8rem;opacity:0.65;margin-right:auto;"><input type="checkbox" id="deploy-save-config" checked> Save configuration</label>
				<button class="button" onclick="confirmDeployConfig()">Continue</button>
				<button class="small-button" onclick="closeDeployConfig()">Cancel</button>
			</div>
			<div style="margin-top:16px;border-top:1px solid rgba(255,255,255,0.06);padding-top:14px;display:flex;flex-direction:column;gap:8px;">
				<div style="font-size:0.68rem;text-transform:uppercase;letter-spacing:0.08em;opacity:0.4;margin-bottom:2px;">Raid Mechanics</div>
				<div style="background:rgba(255,255,255,0.03);border-radius:8px;padding:10px 12px;">
					<div style="font-size:0.72rem;color:#00c8a0;margin-bottom:6px;font-weight:600;">⚔️ Offense Score</div>
					<div style="font-size:0.75rem;opacity:0.65;line-height:1.6;">Your <strong style="color:#c8dce8;">Armory</strong>, <strong style="color:#c8dce8;">Barracks</strong>, and <strong style="color:#c8dce8;">Crypt</strong> levels drive your raid power, weighted by how fully you've filled your Barracks with trained soldiers. Consumables can tip a close matchup.</div>
				</div>
				<div style="display:flex;gap:8px;">
					<div style="flex:1;background:rgba(255,255,255,0.03);border-radius:8px;padding:10px 12px;">
						<div style="font-size:0.72rem;color:#ff9944;margin-bottom:5px;font-weight:600;">💀 On Victory</div>
						<div style="font-size:0.75rem;opacity:0.65;line-height:1.5;">Raiders' combined weapon levels raise garrison death chance (<strong style="color:#c8dce8;">+3%</strong> per level). Garrison armor resists: <strong style="color:#c8dce8;">-4%</strong> per armor level.</div>
					</div>
					<div style="flex:1;background:rgba(255,255,255,0.03);border-radius:8px;padding:10px 12px;">
						<div style="font-size:0.72rem;color:#ff6b6b;margin-bottom:5px;font-weight:600;">🛡️ On Defeat</div>
						<div style="font-size:0.75rem;opacity:0.65;line-height:1.5;">Each raider rolls individually. <strong style="color:#c8dce8;">70%</strong> base death chance, reduced by <strong style="color:#c8dce8;">5%</strong> per armor level (min <strong style="color:#c8dce8;">5%</strong>).</div>
					</div>
				</div>
			</div>
		</div>
	</div>

	<!-- Raid Soldier Selection Modal -->
	<div id="raid-soldiers-overlay" onclick="closeRaidSoldierModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.75);z-index:1004;"></div>
	<div id="raid-soldiers-modal" class="modal" style="display:none;z-index:1005;" role="dialog" aria-modal="true" onclick="closeRaidSoldierModal()">
		<div class="raid-modal-content" style="max-width:560px;max-height:82vh;overflow-y:auto;" onclick="event.stopPropagation()">
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
	<div id="enlist-modal" class="modal" style="display:none;z-index:1003;" role="dialog" aria-modal="true" onclick="closeEnlistPicker()">
		<div class="raid-modal-content" style="max-width:580px;max-height:80vh;overflow-y:auto;" onclick="event.stopPropagation()">
			<div class="raid-modal-header">
				<h2 style="margin:0;font-size:1rem;">Enlist Soldiers</h2>
				<button class="raid-modal-close" onclick="closeEnlistPicker()" aria-label="Close">&times;</button>
			</div>
			<p style="font-size:0.78rem;opacity:0.55;margin:0 0 8px;">Select NFTs to enlist. Partner NFTs cost 2 slots each.</p>
			<select id="enlist-project-filter" style="display:none;width:100%;margin-bottom:6px;background:#1a1a1a;color:#fff;border:1px solid rgba(255,255,255,0.2);border-radius:6px;padding:5px 8px;font-size:0.82rem;" onchange="filterEnlistByProject(this.value)">
				<option value="">All Projects</option>
			</select>
			<select id="enlist-collection-filter" style="display:none;width:100%;margin-bottom:6px;background:#1a1a1a;color:#fff;border:1px solid rgba(255,255,255,0.2);border-radius:6px;padding:5px 8px;font-size:0.82rem;" onchange="filterEnlistByCollection(this.value)">
				<option value="">All Collections</option>
			</select>
			<div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
				<span id="enlist-selected-count" style="font-size:0.8rem;opacity:0.65;">0 of <?php echo intval($barracks_slots_open); ?> slots selected</span>
				<input type="button" id="enlist-select-all-btn" class="small-button" value="Select All" onclick="selectAllEligible()"/>
			</div>
			<div id="enlist-picker-body"><div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div></div>
			<div class="raid-modal-footer">
				<input type="button" class="button" value="Enlist Selected" onclick="confirmEnlist()"/>
				<input type="button" id="enlist-clear-all-btn" class="small-button" value="Clear All" onclick="clearAllEnlist()" style="display:none;"/>
				<input type="button" class="small-button" value="Cancel" onclick="closeEnlistPicker()"/>
			</div>
		</div>
	</div>

	<!-- Raid Consumables Modal -->
	<div id="raid-consumables-overlay" onclick="closeRaidConsumablesModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.7);z-index:1008;"></div>
	<div id="raid-consumables-modal" class="modal" style="display:none;z-index:1009;" onclick="closeRaidConsumablesModal()">
		<div class="raid-modal-content" onclick="event.stopPropagation()">
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

<!-- ── Raid Launch Animation Overlay ───────────────────────── -->
<div id="raid-anim-overlay" aria-hidden="true">
	<div id="raid-anim-field">
		<div id="raid-anim-loading" class="rla-loading">&#8635; Preparing raid&hellip;</div>
		<div id="rla-attacker" class="rla-side" style="display:none"></div>
		<div id="rla-defender" class="rla-side" style="display:none"></div>
	</div>
	<div id="raid-anim-status" class="rla-status"></div>
	<button class="rla-skip-btn" onclick="dismissRaidAnimation()">Skip</button>
</div>

</body>
<?php
getFactionsRealmsMapData($conn);
getActiveRaidsMapData($conn);
if($realm_status && isset($_SESSION['userData']['user_id'])){
	echo "<script>window.myRealmId = ".(int)getRealmID($conn).";</script>";
$_raidDeployConfig = isset($_SESSION['raidDeployConfig']) ? $_SESSION['raidDeployConfig'] : array('amount'=>'tactical','weapon'=>'balanced','armor'=>'medium');
}
$nft_project_tree    = getUserNFTProjectTree($conn);
$barracks_cap        = getDeploymentCap($conn, $realm_id);
$barracks_slots_open = max(0, $barracks_cap - getTotalSoldierSlotCost($conn, $realm_id));
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
.soldiers-grid { display:grid; grid-template-columns:repeat(5,1fr); gap:10px; margin-top:8px; }
.soldier-card { background:rgba(255,255,255,0.05); border:1px solid rgba(255,255,255,0.1); border-radius:8px; padding:8px; text-align:center; font-size:0.75rem; display:flex; flex-direction:column; align-items:center; gap:4px; }
.soldier-card.selected { border-color:#00c8a0; background:rgba(0,200,160,0.1); }
.soldier-card.soldier-ready { border-color:#00c8a0; }
.soldier-card.soldier-dead { opacity:0.7; }
.soldier-nft-img { width:64px; height:64px; object-fit:cover; border-radius:6px; }
.soldier-name { font-size:0.7rem; opacity:0.8; text-align:center; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:10ch; }
.soldier-status { font-size:0.68rem; padding:2px 6px; border-radius:4px; background:rgba(255,255,255,0.08); }
.soldier-status.status-ready { background:rgba(0,200,160,0.2); color:#00c8a0; }
.soldier-status.status-deployed { background:rgba(74,144,217,0.2); color:#4a90d9; }
.soldier-status.status-training { background:rgba(255,200,0,0.15); color:#ffc800; }
.soldier-status.status-dead { background:rgba(255,60,60,0.15); color:#ff6060; }
/* Crypt coffin cards */
#crypt-soldiers-grid .soldier-status { background:none; }
.coffin-wrapper {
    background: url('icons/coffin.png') center bottom / contain no-repeat;
}
.coffin-card {
    background: rgba(30, 10, 10, 0.7);
    border: 1px solid rgba(150, 50, 50, 0.4);
    border-radius: 8px;
    padding: 32.5px;
    text-align: center;
    font-size: 0.75rem;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 4px;
    margin-bottom: -4px;
}
#crypt-soldiers-grid { grid-template-columns:repeat(4,1fr); }
.coffin-card.soldier-ready {
    border-color:rgba(0,200,160,0.5);
    background:rgba(0,40,30,0.7);
    box-shadow:0 0 10px rgba(0,200,160,0.15);
}
@keyframes resurrect-ascend {
    0%   { transform:translateY(0) scale(1);   opacity:1; filter:brightness(1); }
    30%  { transform:translateY(-8px) scale(1.05); opacity:1; filter:brightness(1.8) drop-shadow(0 0 8px #00c8a0); }
    60%  { transform:translateY(-30px) scale(0.95); opacity:0.7; filter:brightness(2.5) drop-shadow(0 0 16px #ffffff); }
    100% { transform:translateY(-80px) scale(0.6); opacity:0; filter:brightness(4) drop-shadow(0 0 24px #ffffff); }
}
.coffin-wrapper.ascending {
    background-image: none;
    animation: resurrect-ascend 0.9s ease-in forwards;
    pointer-events:none;
}
.soldier-status.status-reserve { background:rgba(180,100,255,0.15); color:#b464ff; }
.soldier-badge { font-size:0.62rem; padding:1px 5px; border-radius:3px; background:rgba(255,150,0,0.2); color:#ffa040; }
.partner-badge { background:rgba(150,100,255,0.2); color:#b08aff; }
.soldier-gear-row { display:flex; flex-direction:column; align-items:center; gap:4px; margin-top:4px; }
.soldier-gear-slot { display:flex; flex-direction:column; align-items:center; justify-content:center; gap:2px; font-size:0.7rem; opacity:0.85; height:52px; }
.soldier-gear-slot img.icon { margin-right:0; }
.gear-label { font-size:0.65rem; opacity:0.7; }
.gear-empty { font-size:0.65rem; opacity:0.4; display:flex; align-items:flex-start; justify-content:center; height:52px; padding-top:2px; }
/* Compact gear row (armory/tower cards) */
.soldier-gear-compact { display:flex; flex-direction:row; gap:6px; justify-content:center; margin-top:4px; width:100%; }
.gear-compact-slot { display:flex; flex-direction:column; align-items:center; gap:2px; flex:1; }
.gear-compact-empty { font-size:0.65rem; opacity:0.3; }
/* Tower header and action rows */
.tower-garrison-header { display:flex; align-items:center; justify-content:space-between; flex-wrap:wrap; gap:6px; margin-bottom:8px; }
.tower-garrison-controls { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
.tower-deploy-row { margin-top:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; }
@media (max-width:500px) {
    .soldiers-grid { grid-template-columns:repeat(3,1fr); }
    #crypt-soldiers-grid { grid-template-columns:repeat(2,1fr); }
    .gear-inventory-row { flex-direction:column !important; }
    .soldiers-stat-row { display:grid; grid-template-columns:repeat(2,1fr); }
    .soldiers-stat { padding:7px 10px; min-width:0; }
    .soldiers-stat-label { font-size:0.66rem; }
    .soldiers-stat-value { font-size:0.95rem; }
    .tower-garrison-header { flex-direction:column; align-items:flex-start; }
    .tower-deploy-row { justify-content:flex-end; }
}
.soldier-gear-controls { display:flex; flex-direction:column; gap:4px; width:100%; margin-top:4px; }
.soldier-gear-controls .dropdown { font-size:0.7rem; padding:2px 4px; width:100%; }
.soldiers-table { border-collapse:collapse; }
.soldiers-table th, .soldiers-table td { padding:4px 10px; text-align:left; border-bottom:1px solid rgba(255,255,255,0.08); }
.soldiers-table th { font-size:0.72rem; opacity:0.55; font-weight:normal; text-transform:uppercase; }
.deploy-axis { display:flex; align-items:center; gap:12px; }
.deploy-axis-label { font-size:0.78rem; opacity:0.6; text-transform:uppercase; letter-spacing:0.05em; width:110px; flex-shrink:0; }
.deploy-tier-group { display:flex; gap:6px; flex:1; }
.deploy-tier-btn { flex:1; padding:6px 4px; font-size:0.78rem; background:rgba(255,255,255,0.06); border:1px solid rgba(255,255,255,0.15); border-radius:6px; color:#fff; cursor:pointer; transition:background 0.15s,border-color 0.15s; }
.deploy-tier-btn:hover { background:rgba(255,255,255,0.1); }
.deploy-tier-btn.active { background:rgba(0,200,160,0.18); border-color:#00c8a0; color:#00c8a0; font-weight:bold; }

/* ── Raid Launch Animation ───────────────────────────────────── */
#raid-anim-overlay {
    position:fixed; inset:0; background:rgba(7,17,29,.97); z-index:2000;
    display:none; flex-direction:column; align-items:center; justify-content:center;
    gap:16px; opacity:0; transition:opacity .3s ease;
}
#raid-anim-overlay.active { opacity:1; }
#raid-anim-field {
    display:flex; flex-direction:row; align-items:center; justify-content:center;
    gap:0; width:100%; max-width:900px; padding:0 10px; box-sizing:border-box;
}
.rla-loading { display:none; }
.rla-side {
    display:flex; flex-direction:row; align-items:center; justify-content:center;
    gap:6px; flex:1; opacity:0; transition:opacity .5s ease, transform .5s ease;
}
.rla-side.rla-atk { transform:translateX(-20px); }
.rla-side.rla-def { transform:translateX(20px); }
.rla-side.revealed { opacity:1; transform:translateX(0); }
.rla-realm-wrap {
    display:flex; flex-direction:column; align-items:center; gap:5px; flex-shrink:0;
}
.rla-realm-img {
    width:90px; height:90px; object-fit:cover; border-radius:8px;
    border:1px solid rgba(255,255,255,.12);
}
.rla-realm-name {
    font-size:.62rem; color:rgba(255,255,255,.45); text-align:center;
    max-width:90px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;
}
.rla-loc-col { display:flex; flex-direction:column; gap:5px; flex-shrink:0; }
.rla-loc-icon { width:26px; height:26px; border-radius:5px; overflow:hidden; flex-shrink:0; }
.rla-loc-icon img { width:100%; height:100%; object-fit:cover; display:block; }
.rla-loc-icon.rla-shielded { box-shadow:0 0 9px 2px rgba(0,200,160,.7); border-radius:6px; }
.rla-portal-icon {
    display:flex; flex-direction:column; align-items:center; gap:3px; flex-shrink:0;
}
.rla-portal-icon img { width:38px; height:38px; object-fit:contain; display:block; }
.rla-portal-icon.rla-shielded img { filter:drop-shadow(0 0 6px rgba(0,200,160,.9)); }
.rla-portal-label { font-size:.55rem; color:rgba(255,255,255,.3); letter-spacing:.05em; text-transform:uppercase; }
.rla-soldiers-col { display:grid; grid-template-columns:repeat(2, 28px); gap:2px; flex-shrink:0; align-content:start; }
.rla-soldier { width:28px; height:28px; border-radius:4px; overflow:hidden; flex-shrink:0; }
.rla-soldier img { width:100%; height:100%; object-fit:cover; display:block; }
.rla-def .rla-soldier img { opacity:.75; }
.rla-status {
    font-size:1rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase;
    color:#00c8a0; min-height:1.4em; text-align:center;
    animation:rla-status-pop .35s cubic-bezier(.18,.89,.32,1.28) both;
}
@keyframes rla-status-pop { from { opacity:0; transform:scale(.7); } to { opacity:1; transform:scale(1); } }
.rla-skip-btn {
    background:none; border:1px solid rgba(255,255,255,.15); color:rgba(255,255,255,.3);
    padding:5px 16px; border-radius:20px; font-size:.72rem; cursor:pointer;
    letter-spacing:.06em; text-transform:uppercase; transition:color .15s,border-color .15s;
}
.rla-skip-btn:hover { color:#e8eaed; border-color:rgba(255,255,255,.4); }
@media (max-width:600px) {
    .rla-loc-col, .rla-soldiers-col { display:none; }
    .rla-realm-img { width:70px; height:70px; }
    .rla-portal-icon img { width:28px; height:28px; }
}
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
	var _nftProjectTree           = <?php echo json_encode($nft_project_tree); ?>;
	var _barracksOpenSlots        = <?php echo intval($barracks_slots_open); ?>;
	var _raidSoldierSelectedIds   = [];
	var _raidSoldiersDefenseId    = null;
	var _raidSoldiersDuration     = null;
	var _raidSoldiersButton       = null;
	var _raidSoldiersConsumables  = null;

	/* ── DEPLOYMENT CONFIG ────────────────────────────────── */
	var _deployConfig   = <?php echo json_encode($_raidDeployConfig); ?>;
	var _deployRaiders  = [];

	function openDeployConfig(defenseId, duration, mode, raidButton) {
		_raidSoldiersDefenseId   = defenseId;
		_raidSoldiersDuration    = duration;
		_raidSoldiersConsumables = mode;
		_raidSoldiersButton      = raidButton || null;
		_applyDeployConfigUI();
		document.getElementById('deploy-config-overlay').style.display = 'block';
		document.getElementById('deploy-config-modal').style.display   = 'flex';
		$.getJSON('ajax/get-available-raiders.php', function(raiders) {
			_deployRaiders = raiders || [];
			_updateDeployPreview();
		});
	}

	function closeDeployConfig() {
		document.getElementById('deploy-config-overlay').style.display = 'none';
		document.getElementById('deploy-config-modal').style.display   = 'none';
		_deployRaiders = [];
	}

	function _applyDeployConfigUI() {
		document.querySelectorAll('.deploy-tier-btn').forEach(function(btn) {
			var axis = btn.dataset.axis, val = btn.dataset.value;
			btn.classList.toggle('active', _deployConfig[axis] === val);
		});
		_updateDeployPreview();
	}

	function setDeployTier(axis, val) {
		_deployConfig[axis] = val;
		document.querySelectorAll('.deploy-tier-btn[data-axis="' + axis + '"]').forEach(function(b) {
			b.classList.toggle('active', b.dataset.value === val);
		});
		_updateDeployPreview();
	}

	function _updateDeployPreview() {
		var selected = _autoSelectSoldiers(_deployRaiders, _deployConfig, _portalLevel);
		var withWeapon = selected.filter(function(id) {
			var s = _deployRaiders.find(function(r) { return r.soldier_id == id; });
			return s && s.weapon_id;
		}).length;
		var withArmor = selected.filter(function(id) {
			var s = _deployRaiders.find(function(r) { return r.soldier_id == id; });
			return s && s.armor_id;
		}).length;
		var total = _deployRaiders.length;
		var msg = total === 0
			? 'No trained soldiers available.'
			: 'Sending ' + selected.length + ' soldier' + (selected.length !== 1 ? 's' : '') +
			  ' &mdash; ' + withWeapon + ' armed, ' + withArmor + ' armored';
		document.getElementById('deploy-preview').innerHTML = msg;
	}

	function _autoSelectSoldiers(raiders, config, portalLevel) {
		if (!raiders || raiders.length === 0) return [];
		var count;
		if      (config.amount === 'blitz')    count = portalLevel;
		else if (config.amount === 'tactical') count = Math.ceil(portalLevel / 2);
		else                                   count = Math.ceil(portalLevel / 3);

		var wSum = 0, aSum = 0, wCount = 0, aCount = 0;
		raiders.forEach(function(s) {
			if (s.weapon_level) { wSum += parseFloat(s.weapon_level); wCount++; }
			if (s.armor_level)  { aSum += parseFloat(s.armor_level);  aCount++; }
		});
		var wMean = wCount > 0 ? wSum / wCount : 5;
		var aMean = aCount > 0 ? aSum / aCount : 5;

		function wScore(s) {
			var lv = s.weapon_level ? parseFloat(s.weapon_level) : null;
			if (config.weapon === 'aggressive') return lv !== null ? (10 - lv) : 999;
			if (config.weapon === 'balanced')   return lv !== null ? (Math.abs(lv - wMean) * 10 + (lv < wMean ? 0 : 1)) : 999;
			/* stealth */                        return lv !== null ? (lv - 1) : 999;
		}
		function aScore(s) {
			var lv = s.armor_level ? parseFloat(s.armor_level) : null;
			if (config.armor === 'heavy')  return lv !== null ? (10 - lv) : 999;
			if (config.armor === 'medium') return lv !== null ? (Math.abs(lv - aMean) * 10 + (lv < aMean ? 0 : 1)) : 999;
			/* light */                     return lv !== null ? (lv - 1) : 999;
		}

		var sorted = raiders.slice().sort(function(a, b) {
			var ws = wScore(a) - wScore(b);
			if (ws !== 0) return ws;
			var as_ = aScore(a) - aScore(b);
			if (as_ !== 0) return as_;
			return new Date(a.date_created) - new Date(b.date_created);
		});
		return sorted.slice(0, count).map(function(s) { return s.soldier_id; });
	}

	function confirmDeployConfig() {
		if (document.getElementById('deploy-save-config').checked) {
			$.post('ajax/save-raid-deploy-config.php', _deployConfig);
		}
		_raidSoldierSelectedIds = _autoSelectSoldiers(_deployRaiders, _deployConfig, _portalLevel);
		closeDeployConfig();
		_proceedAfterSoldierPick();
	}

	// Intercept direct startRaid (raid card with pre-set consumables)
	var _origStartRaid = typeof startRaid === 'function' ? startRaid : null;
	startRaid = function(raidButton, defenseID, duration) {
		var allEl = document.getElementById('raid-all-items-' + defenseID);
		var mode  = allEl ? (allEl.dataset.mode || 'default') : 'default';
		if (mode === 'saved') {
			// Both soldier and item configs are saved — skip modals, launch directly
			_raidSoldiersDefenseId   = defenseID;
			_raidSoldiersDuration    = duration;
			_raidSoldiersConsumables = 'direct';
			_raidSoldiersButton      = raidButton || null;
			$.getJSON('ajax/get-available-raiders.php', function(raiders) {
				_deployRaiders = raiders || [];
				if (_deployRaiders.length === 0) {
					openNotify('No trained soldiers available.<br>Enlist and train soldiers in your Barracks before raiding.');
					_deployRaiders = []; _raidSoldiersDefenseId = null;
					return;
				}
				_raidSoldierSelectedIds = _autoSelectSoldiers(_deployRaiders, _deployConfig, _portalLevel);
				_deployRaiders          = [];
				_proceedAfterSoldierPick();
			});
		} else {
			openDeployConfig(defenseID, duration, 'direct', raidButton);
		}
	};

	var _origOpenRaidConsumablesModal = typeof openRaidConsumablesModal === 'function' ? openRaidConsumablesModal : null;
	openRaidConsumablesModal = function(realmId, duration) {
		openDeployConfig(realmId, duration, 'modal', null);
	};

	function _renderRaidSoldierGrid(raiders) {
		var grid = document.getElementById('raid-soldiers-grid');
		if (!raiders || raiders.length === 0) {
			grid.innerHTML = '<p style="opacity:0.55;font-size:0.85rem;text-align:center;">No trained soldiers available. Enlist and train soldiers in Barracks first.</p>';
			return;
		}
		var html = '<div class="soldiers-grid">';
		raiders.forEach(function(s) {
			var weaponInfo = s.weapon_name ? ('Lv' + s.weapon_level + ' ' + s.weapon_name) : 'No Weapon';
			var armorInfo  = s.armor_name  ? ('Lv' + s.armor_level  + ' ' + s.armor_name)  : 'No Armor';
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
			var mode  = allEl ? (allEl.dataset.mode || 'default') : 'default';
			if (mode !== 'saved') {
				// No saved config — force consumables modal
				_raidSoldiersConsumables = 'modal';
				if (_origOpenRaidConsumablesModal) _origOpenRaidConsumablesModal(defId, dur);
				return;
			}
			var savedIds = allEl.dataset.savedIds ? allEl.dataset.savedIds.split(',').map(Number).filter(Boolean) : [];
			var consumablesParam = '';
			savedIds.forEach(function(id) { consumablesParam += '&consumables[]=' + id; });
			var capturedSoldierIds = _raidSoldierSelectedIds.slice();
			_raidSoldierSelectedIds = [];
			var xhttp = new XMLHttpRequest();
			xhttp.open('GET', 'ajax/start_raid.php?defense_id=' + defId + '&duration=' + dur + consumablesParam + soldiersParam, true);
			xhttp.send();
			showRaidAnimation(defId, capturedSoldierIds, function(data) {
				var conRow = document.getElementById('raid-con-row-' + defId);
				if (conRow) conRow.style.display = 'none';
				if (data != '' && btn) btn.outerHTML = data;
			});
			xhttp.onreadystatechange = function() {
				if (xhttp.readyState == XMLHttpRequest.DONE && xhttp.status == 200) {
					_raidAnimGotResponse(xhttp.responseText);
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
		var capturedSoldierIds = _raidSoldierSelectedIds.slice();
		closeRaidConsumablesModal();
		var raidButton = document.getElementById('raid-btn-' + defenseID);
		var xhttp = new XMLHttpRequest();
		xhttp.open('GET', 'ajax/start_raid.php?defense_id=' + defenseID + '&duration=' + duration + consumablesParam + saveParam + soldiersParam, true);
		xhttp.send();
		showRaidAnimation(defenseID, capturedSoldierIds, function(data) {
			var conRow = document.getElementById('raid-con-row-' + defenseID);
			if (conRow) conRow.style.display = 'none';
			if (data != '' && raidButton) raidButton.outerHTML = data;
		});
		xhttp.onreadystatechange = function() {
			if (xhttp.readyState == XMLHttpRequest.DONE && xhttp.status == 200) {
				_raidAnimGotResponse(xhttp.responseText);
			}
		};
		_raidSoldierSelectedIds = [];
	};

	/* ── LOCATION MODALS ─────────────────────────────────── */
	var _locModalTitles    = {1:'Portal',2:'Armory',3:'Tower',4:'Barracks',5:'Factory',6:'Crypt',7:'Mine'};
	var _locModalEndpoints = {1:'get-portal-report',2:'get-armory',3:'get-tower',4:'get-barracks',5:'get-factory',6:'get-crypt',7:'get-mine'};
	var _locModalIcons     = <?php
		$icon_map = array();
		foreach ($locations as $loc_id => $loc) { $icon_map[intval($loc_id)] = 'icons/locations/' . $loc['name'] . '.png'; }
		echo json_encode($icon_map);
	?>;
	var _barracksPage = 1;
	var _armoryPage   = 1;
	var _portalPage   = 1;

	function openLocationModal(loc_id) {
		var title    = _locModalTitles[loc_id]    || 'Location';
		var endpoint = _locModalEndpoints[loc_id] || null;
		if (!endpoint) return;
		var iconSrc  = _locModalIcons[loc_id];
		var iconHtml = iconSrc ? '<img src="' + iconSrc + '" style="width:22px;height:22px;object-fit:contain;vertical-align:middle;margin-right:8px;opacity:0.9;" onerror="this.style.display=\'none\'">' : '';
		document.getElementById('location-modal-title').innerHTML = iconHtml + title;
		var _soldierModals = [1, 2, 3, 4, 6];
		document.getElementById('location-modal-body').innerHTML = _soldierModals.indexOf(loc_id) !== -1 ? _skullLoaderHTML : '<div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div>';
		document.getElementById('location-modal-footer').style.display = 'flex';
		document.getElementById('location-modal-overlay').style.display = 'block';
		document.getElementById('location-modal').style.display          = 'flex';
		var params = '';
		if (loc_id === 1) params = '?page=' + _portalPage;
		if (loc_id === 4) params = '?page=' + _barracksPage;
		if (loc_id === 2) params = '?page=' + _armoryPage;
		if (loc_id === 3) { if (window._currentLocModal !== 3) { _towerPage = 1; _towerSelectedIds = []; } params = '?page=' + _towerPage; }
		$.ajax({
			url: 'ajax/' + endpoint + '.php' + params,
			cache: false,
			success: function(html) {
				document.getElementById('location-modal-body').innerHTML = html;
				if (loc_id === 3) {
					$('#tower-available-grid .tower-pick').each(function() {
						if (_towerSelectedIds.indexOf(parseInt($(this).data('soldier-id'))) !== -1)
							$(this).addClass('selected');
					});
					_updateTowerSelectCount();
				}
			},
			error: function() {
				document.getElementById('location-modal-body').innerHTML = '<p style="opacity:0.5;text-align:center;">Failed to load.</p>';
			}
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

	var _skullLoaderHTML = '<div class="modal-skull-loader"><div class="lsk">💀</div><div class="lbar-wrap"><div class="lbar"></div></div></div>';

	// gridSelector: optional CSS selector for just the soldiers grid — shows loader there only.
	// Omit for a full-body loader (used on initial modal open).
	function _reloadModalBody(endpoint, params, callback, gridSelector) {
		var body = document.getElementById('location-modal-body');
		if (gridSelector) {
			var grid = body.querySelector(gridSelector);
			if (grid) {
				grid.style.display         = 'flex';
				grid.style.alignItems      = 'center';
				grid.style.justifyContent  = 'center';
				grid.style.minHeight       = '160px';
				grid.innerHTML = _skullLoaderHTML;
			}
		} else {
			body.innerHTML = _skullLoaderHTML;
		}
		$.get('ajax/' + endpoint + '.php' + (params || ''), function(html) {
			body.innerHTML = html;
			if (callback) callback();
		}).fail(function() {
			body.innerHTML = '<p style="opacity:0.5;text-align:center;">Failed to load.</p>';
		});
	}

	function goBarracksPage(page) {
		_barracksPage = page;
		_reloadModalBody(_locModalEndpoints[4], '?page=' + page, null, '#barracks-soldiers-grid');
	}

	function goPortalPage(page) {
		_portalPage = page;
		_reloadModalBody(_locModalEndpoints[1], '?page=' + page, null, '#portal-soldiers-grid');
	}

	function goArmoryPage(page) {
		_armoryPage = page;
		_reloadModalBody(_locModalEndpoints[2], '?page=' + page, null, '#armory-soldiers-grid');
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

	var _dischargeMessages = {
		barracks: 'PERMANENTLY discharge this soldier from your army? Their gear will be returned to inventory and they will be removed from your forces entirely. This cannot be undone unless you re-enlist them.',
		tower:    'PERMANENTLY discharge this soldier from your army — not just the garrison. If you only want to move them back to reserve, use the Remove button instead. Their gear will be returned to inventory. This cannot be undone unless you re-enlist them.',
		portal:   'PERMANENTLY discharge this soldier mid-raid? They will be immediately pulled from the active raid and removed from your army entirely. Their gear will be returned to inventory. This cannot be undone unless you re-enlist them.'
	};
	var _dischargeAllMessages = {
		1: 'PERMANENTLY discharge ALL soldiers currently in reserve? This will not affect soldiers in the Tower or on active Raids. All gear will be returned to inventory. This cannot be undone.',
		2: 'PERMANENTLY discharge ALL garrison soldiers from your army — not just remove them from the Tower. To move them back to reserve instead, use Remove All. All gear will be returned to inventory. This cannot be undone.',
		3: 'PERMANENTLY discharge ALL soldiers currently on raids? They will be immediately pulled from active raids and removed from your army entirely. All gear will be returned to inventory. This cannot be undone.'
	};

	function dischargeSoldier(soldier_id, context) {
		var msg = _dischargeMessages[context] || _dischargeMessages['barracks'];
		openConfirm(msg, function() {
			$.post('ajax/discharge-soldier.php', {soldier_id: soldier_id}, function(resp) {
				try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
				if (r.success) { refreshLocationModal(); }
				else { openNotify('Could not discharge soldier.'); }
			});
		});
	}

	function dischargeAllSoldiers(location) {
		var msg = _dischargeAllMessages[location] || 'PERMANENTLY discharge ALL soldiers? All gear will be returned to inventory. This cannot be undone.';
		openConfirm(msg, function() {
			$.post('ajax/discharge-all-soldiers.php', {location: location}, function(resp) {
				try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
				if (r.success) {
					openNotify(r.discharged + ' soldier' + (r.discharged != 1 ? 's' : '') + ' discharged.');
					refreshLocationModal();
				} else { openNotify('Could not discharge soldiers.'); }
			});
		});
	}

	function autoFillBarracks() {
		$.getJSON('ajax/get-barracks-slots.php', function(s) {
			if (typeof s.open !== 'undefined') _barracksOpenSlots = s.open;
		}).always(function() {
		$.get('ajax/get-eligible-nfts.php', function(html) {
			var tmp = $('<div>').html(html);
			var ids = [];
			var remaining = _barracksOpenSlots;
			if (remaining <= 0) { openNotify('No open slots available.'); return; }
			tmp.find('.enlist-candidate').each(function() {
				var cost = parseInt($(this).data('slots')) || 1;
				if (remaining >= cost) {
					ids.push($(this).data('nft-id'));
					remaining -= cost;
				}
			});
			if (ids.length === 0) { openNotify('No eligible NFTs available to enlist.'); return; }
			$.post('ajax/enlist-soldier.php', {nft_ids: ids}, function(resp) {
				try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
				if (r.success) {
					openNotify(r.enlisted + ' soldier' + (r.enlisted != 1 ? 's' : '') + ' enlisted.');
					refreshLocationModal();
				}
			});
		});
		}); // always
	}

	var _enlistSelectedIds = [];
	var _enlistSlotMap     = {}; // nft_id -> slot cost (1 or 2)

	function _enlistUsedSlots() {
		return _enlistSelectedIds.reduce(function(sum, id) { return sum + (_enlistSlotMap[id] || 1); }, 0);
	}

	function openEnlistPicker() {
		_enlistSelectedIds = [];
		_enlistSlotMap     = {};
		document.getElementById('enlist-picker-body').innerHTML = '<p style="text-align:center;padding:20px;opacity:0.5;">Select a project above to view eligible NFTs.</p>';
		document.getElementById('enlist-project-filter').value  = '';
		document.getElementById('enlist-collection-filter').style.display = 'none';
		document.getElementById('enlist-collection-filter').value = '';
		document.getElementById('enlist-modal-overlay').style.display = 'block';
		document.getElementById('enlist-modal').style.display          = 'flex';
		_buildEnlistProjectFilter();
		$.getJSON('ajax/get-barracks-slots.php', function(r) {
			if (typeof r.open !== 'undefined') _barracksOpenSlots = r.open;
			_updateEnlistCount();
		}).fail(function() { _updateEnlistCount(); });
	}

	function closeEnlistPicker() {
		document.getElementById('enlist-modal-overlay').style.display = 'none';
		document.getElementById('enlist-modal').style.display          = 'none';
	}

	function _buildEnlistProjectFilter() {
		var sel = document.getElementById('enlist-project-filter');
		var coreHtml = '', partnerHtml = '';
		for (var pid in _nftProjectTree) {
			var opt = '<option value="' + pid + '">' + _nftProjectTree[pid].name + '</option>';
			if (_nftProjectTree[pid].group === 'core') coreHtml += opt;
			else partnerHtml += opt;
		}
		sel.innerHTML = '<option value="">All Projects</option>';
		if (coreHtml)    sel.innerHTML += '<optgroup label="Core Projects">'    + coreHtml    + '</optgroup>';
		if (partnerHtml) sel.innerHTML += '<optgroup label="Partner Projects">' + partnerHtml + '</optgroup>';
		sel.style.display = Object.keys(_nftProjectTree).length > 0 ? 'block' : 'none';
	}

	function filterEnlistByProject(pid) {
		var colSel = document.getElementById('enlist-collection-filter');
		colSel.innerHTML = '<option value="">All Collections</option>';
		colSel.value = '';
		if (pid && _nftProjectTree[pid]) {
			var cols = _nftProjectTree[pid].collections;
			cols.forEach(function(c) {
				colSel.innerHTML += '<option value="' + c.id + '">' + c.name + '</option>';
			});
			colSel.style.display = cols.length > 1 ? 'block' : 'none';
		} else {
			colSel.style.display = 'none';
		}
		_loadEligibleNFTs(pid, '');
	}

	function filterEnlistByCollection(cid) {
		var pid = document.getElementById('enlist-project-filter').value;
		_loadEligibleNFTs(pid, cid);
	}

	function _loadEligibleNFTs(pid, cid) {
		if (!pid) {
			document.getElementById('enlist-picker-body').innerHTML = '<p style="text-align:center;padding:20px;opacity:0.5;">Select a project above to view eligible NFTs.</p>';
			_updateEnlistCount();
			return;
		}
		document.getElementById('enlist-picker-body').innerHTML = '<div style="text-align:center;padding:20px;opacity:0.5;">Loading...</div>';
		$.get('ajax/get-eligible-nfts.php', {project_id: pid, collection_id: cid}, function(html) {
			document.getElementById('enlist-picker-body').innerHTML = html;
			// cache slot costs and re-apply existing selections
			$('#enlist-picker-body .enlist-candidate').each(function() {
				var id = parseInt($(this).data('nft-id'));
				_enlistSlotMap[id] = parseInt($(this).data('slots')) || 1;
				if (_enlistSelectedIds.indexOf(id) !== -1) $(this).addClass('selected');
			});
			_updateEnlistCount();
		});
	}

	function toggleEnlistSelect(el) {
		var id   = parseInt($(el).data('nft-id'));
		var cost = parseInt($(el).data('slots')) || 1;
		_enlistSlotMap[id] = cost;
		var idx  = _enlistSelectedIds.indexOf(id);
		if (idx === -1) {
			if (_enlistUsedSlots() + cost > _barracksOpenSlots) {
				openNotify('Not enough open slots. Deselect an NFT or reduce your selection.');
				return;
			}
			_enlistSelectedIds.push(id);
			$(el).addClass('selected');
		} else {
			_enlistSelectedIds.splice(idx, 1);
			$(el).removeClass('selected');
		}
		_updateEnlistCount();
	}

	function _updateEnlistCount() {
		document.getElementById('enlist-selected-count').textContent = _enlistUsedSlots() + ' of ' + _barracksOpenSlots + ' slots selected';
		// Select All / Deselect All scoped to current listing
		var visibleIds = [];
		$('#enlist-picker-body .enlist-candidate').each(function() { visibleIds.push(parseInt($(this).data('nft-id'))); });
		var anyVisibleSelected = visibleIds.some(function(id) { return _enlistSelectedIds.indexOf(id) !== -1; });
		var btn = document.getElementById('enlist-select-all-btn');
		if (btn) btn.value = anyVisibleSelected ? 'Deselect All' : 'Select All';
		// Clear All visible only when cross-collection selections exist
		var clearBtn = document.getElementById('enlist-clear-all-btn');
		if (clearBtn) clearBtn.style.display = _enlistSelectedIds.length > 0 ? 'inline-block' : 'none';
	}

	function selectAllEligible() {
		var candidates = $('#enlist-picker-body .enlist-candidate');
		var anySelected = candidates.filter('.selected').length > 0;
		if (anySelected) {
			// deselect only the current listing
			candidates.each(function() {
				var id  = parseInt($(this).data('nft-id'));
				var idx = _enlistSelectedIds.indexOf(id);
				if (idx !== -1) _enlistSelectedIds.splice(idx, 1);
				$(this).removeClass('selected');
			});
			_updateEnlistCount();
			return;
		}
		var remaining = _barracksOpenSlots - _enlistUsedSlots();
		if (remaining <= 0) return;
		candidates.each(function() {
			var id   = parseInt($(this).data('nft-id'));
			var cost = parseInt($(this).data('slots')) || 1;
			_enlistSlotMap[id] = cost;
			if (_enlistSelectedIds.indexOf(id) === -1 && remaining >= cost) {
				_enlistSelectedIds.push(id);
				$(this).addClass('selected');
				remaining -= cost;
			}
		});
		_updateEnlistCount();
	}

	function clearAllEnlist() {
		_enlistSelectedIds = [];
		$('#enlist-picker-body .enlist-candidate').removeClass('selected');
		_updateEnlistCount();
	}

	function confirmEnlist() {
		var ids = _enlistSelectedIds;
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
		var readyCards = document.querySelectorAll('#crypt-soldiers-grid .coffin-wrapper:has(.coffin-card.soldier-ready)');
		// Remove coffin background immediately then ascend
		readyCards.forEach(function(wrapper, i) {
			setTimeout(function() {
				wrapper.style.backgroundImage = 'none';
				wrapper.classList.add('ascending');
			}, i * 120);
		});
		var ascendEnd = readyCards.length > 0 ? (readyCards.length - 1) * 120 + 950 : 0;
		setTimeout(function() {
			$.post('ajax/resurrect-soldiers.php', {}, function(resp) {
				try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
				if (r.success) { openNotify('Soldiers have been resurrected and returned to Reserve.'); refreshLocationModal(); }
				else { openNotify('No soldiers ready for resurrection yet.'); }
			});
		}, ascendEnd);
	}

	/* ── TOWER ────────────────────────────────────────────── */
	var _towerSelectedIds = [];
	var _towerPage        = 1;

	function goTowerPage(page) {
		_towerPage = page;
		_reloadModalBody(_locModalEndpoints[3], '?page=' + page, function() {
			// re-apply cross-page selections to newly rendered cards
			$('#tower-available-grid .tower-pick').each(function() {
				if (_towerSelectedIds.indexOf(parseInt($(this).data('soldier-id'))) !== -1)
					$(this).addClass('selected');
			});
			_updateTowerSelectCount();
		}, '#tower-available-grid');
	}

	function toggleTowerSelect(el) {
		var sid  = parseInt($(el).data('soldier-id'));
		var grid = document.getElementById('tower-available-grid');
		var max  = grid ? parseInt(grid.dataset.max) : 10;
		var idx  = _towerSelectedIds.indexOf(sid);
		if (idx >= 0) {
			_towerSelectedIds.splice(idx, 1);
			$(el).removeClass('selected');
		} else {
			if (_towerSelectedIds.length >= max) {
				openNotify('Only ' + max + ' garrison slot' + (max !== 1 ? 's' : '') + ' remaining.');
				return;
			}
			_towerSelectedIds.push(sid);
			$(el).addClass('selected');
		}
		_updateTowerSelectCount();
	}

	function selectAllTower() {
		var grid = document.getElementById('tower-available-grid');
		if (!grid) return;
		var max       = parseInt(grid.dataset.max);
		var pagePicks = $(grid).find('.tower-pick');
		var anySelected = pagePicks.filter('.selected').length > 0;
		if (anySelected) {
			// deselect current page only
			pagePicks.each(function() {
				var sid = parseInt($(this).data('soldier-id'));
				var idx = _towerSelectedIds.indexOf(sid);
				if (idx !== -1) _towerSelectedIds.splice(idx, 1);
				$(this).removeClass('selected');
			});
		} else {
			pagePicks.each(function() {
				var sid = parseInt($(this).data('soldier-id'));
				if (_towerSelectedIds.indexOf(sid) === -1 && _towerSelectedIds.length < max) {
					_towerSelectedIds.push(sid);
					$(this).addClass('selected');
				}
			});
		}
		_updateTowerSelectCount();
	}

	function clearAllTower() {
		_towerSelectedIds = [];
		$('#tower-available-grid .tower-pick').removeClass('selected');
		_updateTowerSelectCount();
	}

	function _updateTowerSelectCount() {
		var grid = document.getElementById('tower-available-grid');
		var max  = grid ? parseInt(grid.dataset.max) : 10;
		var el   = document.getElementById('tower-select-count');
		if (el) el.textContent = _towerSelectedIds.length + ' of ' + max + ' slots selected';
		// Toggle Select All / Deselect All label
		var pagePicks = $('#tower-available-grid .tower-pick');
		var anyVisibleSelected = pagePicks.filter('.selected').length > 0;
		var btn = document.getElementById('tower-select-all-btn');
		if (btn) btn.textContent = anyVisibleSelected ? 'Deselect All' : 'Select All';
		// Show Clear All only when cross-page selections exist
		var clearBtn = document.getElementById('tower-clear-all-btn');
		if (clearBtn) clearBtn.style.display = _towerSelectedIds.length > 0 ? 'inline-block' : 'none';
	}

	function deployToTower() {
		if (_towerSelectedIds.length === 0) { openNotify('Select at least one soldier.'); return; }
		$.post('ajax/tower-action.php', {action:'assign_bulk', soldier_ids: _towerSelectedIds}, function(resp) {
			try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
			_towerSelectedIds = [];
			if (r.success) refreshLocationModal();
			else openNotify('Could not deploy soldiers. Tower may be full.');
		});
	}

	function removeFromTower(soldier_id) {
		$.post('ajax/tower-action.php', {action:'remove', soldier_id:soldier_id}, function() { refreshLocationModal(); });
	}

	function removeAllFromTower() {
		$.post('ajax/tower-action.php', {action:'remove_bulk'}, function() { refreshLocationModal(); });
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

	function autoEquipGear() {
		$.post('ajax/auto-equip.php', {}, function(resp) {
			try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
			if (r.success) {
				var msg = r.equipped + ' gear item' + (r.equipped != 1 ? 's' : '') + ' equipped!';
				if (r.stripped > 0) msg += '<br>' + r.stripped + ' gear item' + (r.stripped != 1 ? 's' : '') + ' returned to inventory!';
				openNotify(msg); refreshLocationModal();
			}
			else { openNotify('Nothing new to equip.'); }
		});
	}

	/* ── REALM LOG CLAIM ──────────────────────────────────── */
	/* ── RAID LAUNCH ANIMATION ───────────────────────────── */
	var _raidAnim = { done:false, html:null, applyFn:null, timers:[] };

	function showRaidAnimation(defId, soldierIds, applyFn) {
		_raidAnim = { done:false, html:null, applyFn:applyFn, timers:[] };

		var overlay = document.getElementById('raid-anim-overlay');
		var loading = document.getElementById('raid-anim-loading');
		var atk     = document.getElementById('rla-attacker');
		var def     = document.getElementById('rla-defender');
		var statusEl= document.getElementById('raid-anim-status');

		loading.style.display = 'flex';
		atk.style.display = 'none'; def.style.display = 'none';
		atk.innerHTML = ''; def.innerHTML = '';
		statusEl.textContent = '';
		overlay.style.display = 'flex';
		requestAnimationFrame(function(){ overlay.classList.add('active'); });

		var soldierParam = soldierIds.map(function(id){ return '&soldiers[]=' + id; }).join('');
		fetch('ajax/get-raid-preview.php?defense_id=' + defId + soldierParam)
			.then(function(r){ return r.json(); })
			.then(function(data){
				if (!data.success || !data.attacker || !data.defender) return;
				loading.style.display = 'none';
				_renderRaidAnimSides(data.attacker, data.defender);
				_runRaidAnimSequence(soldierIds.length);
			})
			.catch(function(){});

		var minTimer = setTimeout(function(){
			_raidAnim.done = true;
			_tryApplyRaidResult();
		}, 4800);
		_raidAnim.timers.push(minTimer);
	}

	function _escHtml(s) {
		return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
	}

	function _renderRaidAnimSides(attacker, defender) {
		function locIconHtml(loc) {
			var sh  = loc.has_shield ? ' rla-shielded' : '';
			var src = (_locModalIcons && _locModalIcons[loc.location_id])
				? _locModalIcons[loc.location_id]
				: 'icons/skull.png';
			return '<div class="rla-loc-icon' + sh + '" title="' + _escHtml(loc.name) + '">'
				+ '<img src="' + src + '" onerror="this.src=\'icons/skull.png\'">'
				+ '</div>';
		}
		function locColHtml(locs) {
			if (!locs || !locs.length) return '';
			return '<div class="rla-loc-col">' + locs.map(locIconHtml).join('') + '</div>';
		}
		function soldierColHtml(soldiers) {
			var html = '<div class="rla-soldiers-col">';
			if (soldiers && soldiers.length) {
				soldiers.forEach(function(s){
					html += '<div class="rla-soldier">'
						+ '<img src="' + _escHtml(s.img_url) + '" onerror="this.src=\'icons/skull.png\'" title="' + _escHtml(s.name) + '">'
						+ '</div>';
				});
			}
			return html + '</div>';
		}
		function portalHtml(loc) {
			var sh      = (loc && loc.has_shield) ? ' rla-shielded' : '';
			var portalSrc = (_locModalIcons && _locModalIcons[1]) ? _locModalIcons[1] : 'icons/skull.png';
			return '<div class="rla-portal-icon' + sh + '">'
				+ '<img src="' + portalSrc + '" onerror="this.src=\'icons/skull.png\'">'
				+ '<div class="rla-portal-label">Portal</div>'
				+ '</div>';
		}
		function realmWrapHtml(realm) {
			return '<div class="rla-realm-wrap">'
				+ '<img class="rla-realm-img" src="images/themes/' + _escHtml(realm.theme_id) + '.jpg" onerror="this.src=\'icons/skull.png\'">'
				+ '<div class="rla-realm-name">' + _escHtml(realm.name) + '</div>'
				+ '</div>';
		}

		var atkDef    = attacker.locations.filter(function(l){ return l.location_type==='defense'; });
		var atkOff    = attacker.locations.filter(function(l){ return l.location_type==='offense' && l.location_id!=1; });
		var atkPortal = attacker.locations.find(function(l){ return l.location_id==1; });
		var defDef    = defender.locations.filter(function(l){ return l.location_type==='defense'; });
		var defOff    = defender.locations.filter(function(l){ return l.location_type==='offense' && l.location_id!=1; });
		var defPortal = defender.locations.find(function(l){ return l.location_id==1; });

		var atkEl = document.getElementById('rla-attacker');
		var defEl = document.getElementById('rla-defender');

		atkEl.className = 'rla-side rla-atk';
		atkEl.innerHTML = locColHtml(atkDef)
			+ realmWrapHtml(attacker)
			+ locColHtml(atkOff)
			+ soldierColHtml(attacker.soldiers)
			+ portalHtml(atkPortal);

		defEl.className = 'rla-side rla-def';
		defEl.innerHTML = portalHtml(defPortal)
			+ soldierColHtml(defender.soldiers)
			+ locColHtml(defDef)
			+ realmWrapHtml(defender)
			+ locColHtml(defOff);

		atkEl.style.display = 'flex'; defEl.style.display = 'flex';
		requestAnimationFrame(function(){
			setTimeout(function(){
				atkEl.classList.add('revealed');
				defEl.classList.add('revealed');
			}, 30);
		});
	}

	function _runRaidAnimSequence(soldierCount) {
		var statusEl = document.getElementById('raid-anim-status');

		var t1 = setTimeout(function(){
			statusEl.style.animation = 'none';
			void statusEl.offsetWidth;
			statusEl.style.animation = '';
			statusEl.textContent = '\u2694\uFE0F Raid Launched!';
		}, 700);
		_raidAnim.timers.push(t1);

		var marchers  = document.querySelectorAll('#rla-attacker .rla-soldier');
		var portalEl  = document.querySelector('#rla-attacker .rla-portal-icon');
		marchers.forEach(function(el, i){
			var t = setTimeout(function(){
				var sr = el.getBoundingClientRect();
				var pr = portalEl ? portalEl.getBoundingClientRect() : null;
				if (pr) {
					var dx = Math.round((pr.left + pr.width  / 2) - (sr.left + sr.width  / 2));
					var dy = Math.round((pr.top  + pr.height / 2) - (sr.top  + sr.height / 2));
					el.style.transition = 'transform .6s ease-in, opacity .45s ease-in .18s';
					el.style.transform  = 'translate(' + dx + 'px,' + dy + 'px) scale(.15)';
					el.style.opacity    = '0';
				}
			}, 1200 + i * 320);
			_raidAnim.timers.push(t);
		});

		var afterMarch = 1200 + marchers.length * 320 + 700;
		var t2 = setTimeout(function(){
			statusEl.style.animation = 'none';
			void statusEl.offsetWidth;
			statusEl.style.animation = '';
			statusEl.textContent = '\uD83C\uDF00 Raiders are en route\u2026';
		}, afterMarch);
		_raidAnim.timers.push(t2);
	}

	function _raidAnimGotResponse(html) {
		_raidAnim.html = html;
		_tryApplyRaidResult();
	}

	function _tryApplyRaidResult() {
		if (_raidAnim.html !== null && _raidAnim.done) {
			var fn = _raidAnim.applyFn;
			var html = _raidAnim.html;
			dismissRaidAnimation();
			if (fn) fn(html);
		}
	}

	function dismissRaidAnimation() {
		var overlay = document.getElementById('raid-anim-overlay');
		overlay.classList.remove('active');
		var timers = _raidAnim.timers.slice();
		timers.forEach(clearTimeout);
		_raidAnim = { done:false, html:null, applyFn:null, timers:[] };
		setTimeout(function(){ overlay.style.display = 'none'; }, 350);
	}

	function claimRealmLogs(types) {
		$.ajax({
			url: 'ajax/claim-realm-logs.php',
			type: 'POST',
			data: {types: types},
			cache: false,
			success: function(resp) {
				try { var r = JSON.parse(resp); } catch(e) { var r = {success:false}; }
				if (r.success) {
					openNotify('Rewards claimed!');
					var loc = window._currentLocModal;
					if (loc) openLocationModal(loc);
				} else {
					openNotify('Nothing to claim.');
				}
			}
		});
	}
</script>
<?php } ?>
</html>