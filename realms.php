<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';

if(isset($_POST['realm'])){
	if(!checkRealm($conn)){
		createRealm($conn, $_POST['realm']);
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
	if(checkRealm($conn)){
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
			if(checkRealm($conn)){
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
										<?php if($levels[$location_id] != 10){ ?>
											<?php 
											if(!isset($status[$location_id])){  
												$cost = (($levels[$location_id]+1)*100); ?>
												<strong>Cost:</strong> <?php echo number_format($cost)." ".$projects[$location_id]['currency']; ?><br>
												<?php $duration = $levels[$location_id]+1;?>
												<strong>Duration:</strong> <?php echo $duration; ?> <?php echo ($duration == 1)?"Day":"Days"; ?><br>
											<?php 
												$balance = getBalance($conn, $location_id);
												if($balance >= $cost){ ?>
													<input class='small-button' type='button' value='Upgrade to Level <?php echo ($levels[$location_id]+1); ?>' onclick='upgradeRealmLocation(this, <?php echo $realm_id;?>, <?php echo $location_id;?>, <?php echo $duration;?>, <?php echo $cost;?>)'>
											<?php
												}else{
													echo "Need ".number_format($cost-$balance)." ".$projects[$location_id]['currency'];
												}
										    }else{ 
												echo $status[$location_id];
											}
										}else{
											echo "<br>Reached Max Level";
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
					<label for="realm">Realm Name</label><br>
					<input type="text" id="realm" name="realm" size="30" required><br><br>
					<input class="button" type="submit" value="Create Realm"><br><br>
					<label for="disclaimer">Disclaimer</label><br>
					<p id="disclaimer">By creating your realm, you agree to being vulnerable to raids from other realm owners who may attack your realm, damage your locations, and steal some of your points. You can raid other realms but raid failures also result in damage to your locations. If you anticipate that you are not going to be dedicated to protecting your realm and upgrading/raiding on a regular basis, don't feel obligated to create a realm. Abandoned realms are a prime target for plundering which can lead to complete poverty and devastation.
<br><br>
Realms require core project points as well as DIAMOND to upgrade offense, defense, and transport locations. The more core projects points you have, the stronger your realm will be. Without being able to upgrade all your locations, your ability to attack, defend, and travel may be impeded.
<br><br>
With that being said, Skulliance is offering a promotional incentive to participate in realms. Stakers establishing new realms will receive the following starter pack of core project points:
<ul>
	<li>1K STAR</li>
	<li>1K DREAD</li>
	<li>1K HYPE</li>
	<li>1K SINDER</li>
	<li>1K CYBER</li>
	<li>1K CRYPT</li>
	<li>1K DIAMOND</li>
</ul>
<br>
This allocation of core project points should provide you the opportunity to upgrade your locations to level 4. You can choose to deactivate your realm at any time and no longer participate in raids. But you will have to wait a month before being allowed to reactivate your realm. If you have not raided in over a month, your realm is subject to being automatically deactivated. You can reactivate an automatically deactivated realm at any time with no penalty.</p>
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
		echo '
		<div id="filter-nfts" style="top:25px">
			<label for="filterNFTs"><strong>Theme:</strong></label>
			<select onchange="javascript:filterNFTs(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs">
				<optgroup label="Core Projects">';
				$projects = array_reverse($projects, true);
				foreach($projects AS $id => $project){
					echo '<option value="'.$id.'">'.$project["name"].'</option>';
				}
				echo '</optgroup><optgroup label="Partner Projects">';
				$partner_projects = getProjects($conn, "partner");
				foreach($partner_projects AS $id => $project){
					echo '<option value="'.$id.'">'.$project["name"].'</option>';
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
			</form>
		</div>';
		}
		?>
	    </div>
		</div>
	  </div>
</div>
<div class="row" id="row1">	
	<div class="main">
	<div id="raids">
		<?php 
		if(checkRealm($conn)){
			getTotalRaids($conn);
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
		}
		?>
	</div>
	</div>
</div>
<div class="row" id="row2">	
	<div class="main">
	<div id="realms">
		<a name="realms" id="realms"></a>	
		<h2>Realms</h2>	
		<div class="content realms" id="filtered-content">
			<?php
			$filterByRealms = "";
			if(checkRealm($conn)){
				if(isset($_POST['filterByRealms'])){
					$filterByRealms = $_POST['filterByRealms'];
				}else{
					$filterByRealms = "random";
				}
				if(checkRealmState($conn) == 1){
					?>
					<?php echo '
					<div id="filter-nfts">
						<label for="filterRealms"><strong>Sort By:</strong></label>
						<select onchange="javascript:filterRealms(this.options[this.selectedIndex].value);" name="filterRealms" id="filterRealms">';
							echo '<option value="random">Random</option>';
							echo '<option value="weakness">Weakness</option>';
							echo '<option value="strength">Strength</option>';
							echo '<option value="wealth">Wealth</option>';
						echo '
						</select>
						<form id="filterRealmsForm" action="realms.php#realms" method="post">
						  <input type="hidden" id="filterByRealms" name="filterByRealms" value="">
						  <input type="submit" value="Submit" style="display:none;">
						</form>
					</div>';?>
					<?php
					$sort = $filterByRealms;
					getRealms($conn, $sort);
				}
			}
			?>
		</div>
	</div>
	</div>
</div>
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
<div id="quick-menu">
	<img id="locations-icon" src="icons/locations.png" onclick="toggleSections('locations');">
	<img id="realm-icon" src="icons/realm.png" onclick="toggleSections('realm');">
	<img id="raids-icon" src="icons/raids.png" onclick="toggleSections('raids');">
	<img id="realms-icon" src="icons/quests.png" onclick="toggleSections('realms');">
</div>
	<!-- Footer -->
	<div class="footer">
	  <p>Skulliance<br>Copyright © <span id="year"></span>
	</div>
</div>
</div>
</body>
<?php
// Close DB Connection
$conn->close();
if($filterby != ""){
	echo "<script type='text/javascript'>document.getElementById('filterNFTs').value = '".$filterby."';</script>";
}
if($filterByRealms != ""){
	echo "<script type='text/javascript'>document.getElementById('filterRealms').value = '".$filterByRealms."';</script>";
}
?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<script type='text/javascript'>
	if($(window).width() <= 700){
		document.getElementById('back-to-top-button').style.zIndex = "-1";
		document.getElementById('quick-menu').style.display = "block";
		document.getElementById('raids').style.position = "relative";
		document.getElementById('raids').style.top = '-55px';
		document.getElementById('realms').style.position = "relative";
		document.getElementById('realms').style.top = '-105px';
		document.getElementById('realm').style.position = "relative";
		document.getElementById('realm').style.top = '-25px';
		if(window.location.hash == "#realms"){
			document.getElementById('realms').style.display = "block";
			document.getElementById('realms-icon').classList.add("selected");
			document.getElementById('locations').style.display = "none";
			document.getElementById('locations-icon').classList.remove("selected");
			document.getElementById('realm').style.display = "none";
			document.getElementById('realm-icon').classList.remove("selected");
			document.getElementById('raids').style.display = "none";
			document.getElementById('raids-icon').classList.remove("selected");
		}else if(window.location.hash == "#realm-image" || window.location.hash == "#realm-name"){
			document.getElementById('realm').style.display = "block";
			document.getElementById('realm-icon').classList.add("selected");
			document.getElementById('locations').style.display = "none";
			document.getElementById('locations-icon').classList.remove("selected");
			document.getElementById('realm-icon').classList.add("selected");
			document.getElementById('raids').style.display = "none";
			document.getElementById('raids-icon').classList.remove("selected");
			document.getElementById('realms').style.display = "none";
			document.getElementById('realms-icon').classList.remove("selected");
		}else{
			document.getElementById('locations').style.display = "block";
			document.getElementById('locations-icon').classList.add("selected");
			document.getElementById('realm').style.display = "none";
			document.getElementById('realm-icon').classList.remove("selected");
			document.getElementById('raids').style.display = "none";
			document.getElementById('raids-icon').classList.remove("selected");
			document.getElementById('realms').style.display = "none";
			document.getElementById('realms-icon').classList.remove("selected");
		}
	}else{
		document.getElementById('quick-menu').style.display = "none";
		//document.getElementById('row1').style.position = "relative";
		//document.getElementById('row1').style.top = '-65px';
	}
	
	function toggleSections(selection){
		if($(window).width() <= 700){
			window.scrollTo(0, 0);
			document.getElementById('locations').style.display = "none";
			document.getElementById('locations-icon').classList.remove("selected");
			document.getElementById('realm').style.display = "none";
			document.getElementById('realm-icon').classList.remove("selected");
			document.getElementById('raids').style.display = "none";
			document.getElementById('raids-icon').classList.remove("selected");
			document.getElementById('realms').style.display = "none";
			document.getElementById('realms-icon').classList.remove("selected");
			if ($('#'+selection).length > 0) {
				document.getElementById(selection).style.display = "block";
				document.getElementById(selection+"-icon").classList.add("selected");
			}else{

			}
		}
	}
</script>
</html>