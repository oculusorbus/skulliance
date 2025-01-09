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
	}
}

if(isset($_SESSION['userData']['user_id'])){ ?>
<!-- The flexible grid (content) -->

<div class="row" id="row0">	
	  <div class="side" id="realm">
		<div class="content realm">
			<?php
			if(checkRealm($conn)){
				$status = getRealmLocationsUpgrades($conn);
				$locations = getLocationInfo($conn);
				?>
				<h2>Locations</h2>
				<ul>
				<?php
				$realm_id = getRealmID($conn);
				$levels = getRealmLocationLevels($conn);
				$projects = getProjects($conn, "core");
				foreach($locations AS $location_id => $location){?>
						<li class="role">
							<table>
							<tr>
								<td width="40%">
							<img title="<?php echo $location['description'];?>" width="75%" src="icons/locations/<?php echo $location['name']; ?>.png"><br>
								</td>
								<td width="60%">
							<strong><?php echo strtoupper($location['name']); ?></strong><br>
							<strong>Level:</strong> <?php echo $levels[$location_id]; ?><br>
							<strong>Type:</strong> <?php echo ucfirst($location['type']); ?><br>
							<?php if($levels[$location_id] != 10){ ?>
								<?php $cost = (($levels[$location_id]+1)*100); ?>
								<strong>Cost:</strong> <?php echo number_format($cost)." ".$projects[$location_id]['currency']; ?><br>
								<?php $duration = $levels[$location_id]+1;?>
								<strong>Duration:</strong> <?php echo $duration; ?> <?php echo ($duration == 1)?"Day":"Days"; ?><br>
								<?php 
								if(!isset($status[$location_id])){  
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
				}
				?>
				</ul>
				<?php
			}else{
				?>
				<h2>Create Your Realm</h2>
				<ul>
				<li class="role">
				<form action="realms.php" method="post">
					<label for="realm">Realm Name</label><br>
					<input type="text" id="realm" name="realm" size="30"><br><br>
					<label for="disclaimer">Disclaimer</label><br>
					<p id="disclaimer">By creating your realm, you agree to being vulnerable to raids from other realm owners which may damage your realm and steal your points. You can also raid other realms but raid failures result in damage to your realm. If you find that you are not dedicated to protecting your realm and raiding on a regular basis, it is your responsibility to deactivate your abandoned realm or you could be subject to complete devastation and constant looting. Choose wisely.</p>
					<input class="button" type="submit" value="Create">
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
		<h2><?php echo checkRealm($conn)?getRealmName($conn):"Realm"; ?></h2>
	    <div class="content realm" id="filtered-content">
		<?php
		if(isset($_POST['filterby'])){
			$image = $_POST['filterby'];
			updateRealmTheme($conn, $realm_id, $_POST['filterby']);
		}else{
			$image = getRealmThemeID($conn, $realm_id);
		}
		echo '
		<div id="filter-nfts">
			<label for="filterNFTs"><strong>AI Themes Inspired By:</strong></label>
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
		?>
			<img src="images/<?php echo $image;?>.jpg" width="100%"/>
	    </div>
		</div>
	  </div>
</div>
<div class="row" id="row1">	
	<div class="main">
	<div id="raids">
		<?php 
		$outgoing_raids = getRaids($conn, "outgoing", "pending"); 
		if(isset($outgoing_raids)){
		?>
		<div class="content raids">
			<h2>Outgoing Raids</h2>
			<?php
				echo $outgoing_raids;
			?>
		</div>
		<?php
		}	
		$outgoing_completed = getRaids($conn, "outgoing", "completed"); 
		if(isset($outgoing_completed)){
		?>
		<div class="content raids">
			<h2>Outgoing Completed</h2>
			<?php
			echo $outgoing_completed;
			?>
		</div>
		<?php
		}	
		$incoming_raids = getRaids($conn, "incoming", "pending"); 
		if(isset($incoming_raids)){
		?>
		<div class="content raids">
			<h2>Incoming Raids</h2>
			<?php
			echo $incoming_raids;
			?>
		</div>
		<?php
		}	
		$incoming_completed = getRaids($conn, "incoming", "completed"); 
		if(isset($incoming_completed)){
		?>
		<div class="content raids">
			<h2>Incoming Completed</h2>
			<?php
			echo $incoming_raids;
			?>
		</div>
		<?php
		}	
		?>
	</div>
	</div>
</div>
<div class="row" id="row2">	
	<div class="main">
	<div id="realms">
		<div class="content realms">
			<h2>Realms</h2>
			<?php
			getRealms($conn);
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
}?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
</html>