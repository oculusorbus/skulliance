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
						<div class="location-row" style="border-bottom:1px solid rgba(0,200,160,0.15);padding-bottom:14px;margin-bottom:6px;align-items:center;">
							<div style="flex-shrink:0;">
								<img src="images/realms-logo.png" style="width:100px;opacity:0.9;margin-right:0;"/>
							</div>
							<div style="flex:1;"></div>
							<div style="flex-shrink:0;text-align:right;">
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
								<button class="small-button" id="stock-all-btn" onclick="stockAllLocations()">Stock All Locations</button>
								<button class="small-button" onclick="openInventoryInfoModal()">Inventory Info</button>
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
								<?php 
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
		<div id="filter-nfts" style="top:25px">
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
	            <button class="popup-close" id="popup-close">X</button>
	            <img class="popup-image" id="popup-image" src="" alt="Realm Image">
	            <p class="popup-name" id="popup-name"></p>
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
				<input type="button" class="button" id="raid-con-modal-start-btn" value="Start Raid" onclick="startRaidFromModal()"/>
				<input type="button" class="small-button" value="Cancel" onclick="closeRaidConsumablesModal()"/>
			</div>
		</div>
	</div>

	<!-- Footer -->
	<div class="footer">
	  <p>Skulliance<br>Copyright © <span id="year"></span>
	</div>
</div>
</div>
</body>
<?php
getFactionsRealmsMapData($conn);
// Close DB Connection
$conn->close();
?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="map.js?var=<?php echo rand(0,999); ?>"></script>
<?php if($realm_status){ ?>
<script type='text/javascript'>
	//if($(window).width() <= 700){
		document.getElementById('back-to-top-button').style.zIndex = "-1";
		document.getElementById('quick-menu').style.display = "block";
		document.getElementById('map').style.position = "relative";
		document.getElementById('map').style.top = '-55px';
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
		//if($(window).width() <= 700){
			window.scrollTo(0, 0);
			document.getElementById('locations').style.display = "none";
			document.getElementById('locations-icon').classList.remove("selected");
			document.getElementById('map').style.display = "none";
			document.getElementById('map-icon').classList.remove("selected");
			document.getElementById('realm').style.display = "none";
			document.getElementById('realm-icon').classList.remove("selected");
			document.getElementById('stats').style.display = "none";
			document.getElementById('stats-icon').classList.remove("selected");
			document.getElementById('raids').style.display = "none";
			document.getElementById('raids-icon').classList.remove("selected");
			document.getElementById('realms').style.display = "none";
			document.getElementById('realms-icon').classList.remove("selected");
			if ($('#'+selection).length > 0) {
				document.getElementById(selection).style.display = "block";
				document.getElementById(selection+"-icon").classList.add("selected");
				if($(window).width() > 700){
					if(selection == 'locations'){
						document.getElementById('realm').style.display = "block";
					}
					if(selection == 'map'){
						document.body.style.backgroundImage = "url('images/darkwater.gif')";
					}else{
						document.body.style.backgroundImage = "none";
					}
				}else{
					if(selection == 'realm'){
						document.getElementById('map').style.display = "block";
					}
				}
			}else{

			}
			//}
	}
	_checkStockButtonStates();
</script>
<?php } ?>
</html>