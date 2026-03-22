<?php
include '../db.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }

$realm_status = checkRealm($conn);
$projects = getProjects($conn, "core");

$realm_id = $realm_status ? getRealmID($conn) : null;
?>
<a name="realm-image" id="realm-image"></a>
<h2><?php echo $realm_status ? "<span id='realmName'>".getRealmName($conn)."</span>&nbsp;<img style='max-width:25px;cursor: pointer;' src='icons/edit.png' class='icon' onclick='editRealmName(this);'/>" : "Realm"; ?></h2>
<div class="content realm">
<?php if($realm_status): ?>
	<?php
	$image = getRealmThemeID($conn, $realm_id);
	$theme_id = $image;
	$project_id = getRealmFaction($conn, $realm_id);
	$partner_projects = getProjects($conn, "partner");
	?>
	<img src="images/themes/<?php echo isset($image) ? $image : '7'; ?>.jpg" width="100%"/>
	<div id="filter-nfts" style="position:static;margin-top:10px;">
		<label for="filterNFTs"><strong>Theme:</strong></label>
		<select onchange="javascript:filterNFTs(this.options[this.selectedIndex].value);" name="filterNFTs" id="filterNFTs" class="dropdown">
			<optgroup label="Core Projects">
			<?php
			$projects_rev = array_reverse($projects, true);
			foreach($projects_rev AS $id => $project){
				$selected = ($theme_id == $id) ? "selected" : "";
				echo '<option '.$selected.' value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup><optgroup label="Partner Projects">';
			foreach($partner_projects AS $id => $project){
				$selected = ($theme_id == $id) ? "selected" : "";
				echo '<option '.$selected.' value="'.$id.'">'.$project["name"].'</option>';
			}
			echo '</optgroup>';
			if($_SESSION['userData']['discord_id'] == '772831523899965440'){
				echo '<optgroup label="Founder"><option value="0">Oculus Orbus</option></optgroup>';
			}
			?>
		</select>
		<form id="filterNFTsForm" action="realms.php#realm-image" method="post">
			<input type="hidden" id="filterby" name="filterby" value="">
			<input type="submit" value="Submit" style="display:none;">
		</form><br>
		<form id="factionsForm" action="realms.php#realm-image" method="post">
		<label for="faction"><strong>Faction:</strong></label>
		<select onchange="document.getElementById('factionsForm').submit();" class="dropdown" name="faction" id="faction">
		<optgroup label="Core Factions">
		<?php
		$core_projects = $projects;
		unset($core_projects[7]);
		foreach($core_projects AS $id => $project){
			$selected = ($project_id == $id) ? "selected" : "";
			echo '<option '.$selected.' value="'.$id.'">'.$project["name"].'</option>';
		}
		echo '</optgroup><optgroup label="Partner Factions">';
		foreach($partner_projects AS $id => $project){
			$selected = ($project_id == $id) ? "selected" : "";
			echo '<option '.$selected.' value="'.$id.'">'.$project["name"].'</option>';
		}
		echo '</optgroup>';
		?>
		</select>
		</form>
	</div>
<?php else: ?>
	<p>No realm found.</p>
<?php endif; ?>
</div>
<?php $conn->close(); ?>
