<?php
include '../db.php';
include '../skulliance.php';
require_once __DIR__ . '/../realms-lib.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }

$realm_status = checkRealm($conn);
$projects = getProjects($conn, "core");
?>
<div class="content realm">
<?php
if($realm_status){
	if(checkRealmState($conn) == 1){
		/* THE SAME PARTIAL THE PAGE USES. This file used to carry its own
		   near-verbatim copy of the panel -- same strip, same rows, same
		   price ladder -- and the two had already drifted apart (the page
		   had a Guide button, this did not). No Guide here still, but for a
		   reason now: it lives outside the fragment this replaces. */
		$rl_panel = realm_location_panel($conn);
		$rl_guide = false;
		include __DIR__ . '/../realms-locations.php';
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
<a href="skullpaper.php?page=realms">Read about Realms, Locations, Raids, and Factions in the Skull Paper</a>
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
