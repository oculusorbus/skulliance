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
					?>
					<li class="role">
						<table>
						<tr> 
							<td width="40%">
								<img src="images/realms-logo.png" width="80%" style="position:relative;top:10px"/>
							</td>
							<td width="60%" valign="bottom">
								<?php echo '<input class="small-button" type="button" value="Deactivate Realm" onclick="deactivateRealm('.$realm_id.');">';?>
								<br><br>
								<strong>Locations</strong>
							</td>
						</tr>
						</table>
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
							<table>
								<tr>	
									<td width="40%">
										<img style="opacity:0.85" title="<?php echo $location['description'];?>" width="75%" src="icons/locations/<?php echo $location['name']; ?>.png"><br>
									</td>
									<td width="60%">
										<strong><?php echo strtoupper($location['name']); ?></strong><br>
										<strong>Current Level:</strong> <?php echo $levels[$location_id]; ?><br>
										<?php 
										if(!isset($status[$location_id])){  
											if($levels[$location_id] > 10){
												// Safety precaution to prevent leveling above 10
												$duration = 10;
											}else if($levels[$location_id] == 10){
												// Maintain max level of 10 for all max uprgrades
												$duration = $levels[$location_id];
											}else{
												$duration = $levels[$location_id]+1;
											}
											$cost = $duration*100; ?>
											<strong>Cost:</strong> <?php echo number_format($cost)." ".$projects[$location_id]['currency']; ?><br>
											<strong>Duration:</strong> <?php echo $duration; ?> <?php echo ($duration == 1)?"Day":"Days"; ?><br>
										<?php 
											$balance = getBalance($conn, $location_id);
											if($balance >= $cost){ 
												$upgrade_verbiage = "";
												if($levels[$location_id] >= 10){
													$upgrade_verbiage = "Maintain";
												}else{
													$upgrade_verbiage = "Upgrade to";
												}
												?>
												
												<input id='upgrade-button-<?php echo $location_id; ?>' class='small-button' type='button' value='<?php echo $upgrade_verbiage; ?> Level <?php echo ($duration); ?>' onclick='upgradeRealmLocation(this, <?php echo $realm_id;?>, <?php echo $location_id;?>, <?php echo $duration;?>, <?php echo $cost;?>, <?php echo $location_id; ?>)'>
										<?php
											}else{
												echo "<span id='upgrade-message-".$location_id."'>Need ".number_format($cost-$balance)." ".$projects[$location_id]['currency']."</span>";
											?>
											<br>
											<input id="points-button-<?php echo $location_id; ?>" class="small-button" type="button" value="<?php echo $points_multiplier; ?>x Points Upgrade" onclick="togglePointsButtons('disable');pointsOption(this, <?php echo $realm_id;?>, <?php echo $location_id;?>, <?php echo $duration;?>, <?php echo $cost; ?>)">
											<?php
											}
									    }else{ 
											echo $status[$location_id];
										}
									?>
									</td>
								</tr>
								</table>
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
</script>
<?php } ?>
</html>