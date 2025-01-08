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
							<?php $cost = (($levels[$location_id]+1)*1000); ?>
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
							} ?>
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
					<p id="disclaimer">By creating your realm, you agree to being vulnerable to raids from other realm owners which may damage your realm and steal your points. You can also raid other realms but raid failures result in your troops dying and requiring resurrection. If you find that you are not dedicated to protecting your realm and raiding on a regular basis, it is your responsibility to deactivate your abandoned realm or you could be subject to complete devastation and constant looting. Choose wisely.</p>
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
		<h2><?php echo checkRealm($conn)?getRealmName($conn):"Realm"; ?></h2>
	    <div class="content realm">
			<img src="images/realm.jpg" width="100%"/>
	    </div>
		</div>
	  </div>
</div>
<div class="row" id="row1">	
	<div class="main">
	<div id="raids">
		<div class="content raids">
			<h2>Outgoing Raids</h2>
			<?php
			getRaids($conn, "outgoing");
			?>
		</div>
		<div class="content raids">
			<h2>Incoming Raids</h2>
			<?php
			getRaids($conn, "incoming");
			?>
		</div>
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