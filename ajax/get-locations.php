<?php
include '../db.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }

$realm_status = checkRealm($conn);
$projects = getProjects($conn, "core");
?>
<div class="content realm">
<?php
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
		<optgroup label="Core Factions">
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
<?php $conn->close(); ?>
