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
<div class="row" id="row1">	
	  <div class="side" id="realm">
		<div class="content realm">
			<?php
			if(checkRealm($conn)){
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
							<strong>Cost:</strong> <?php echo number_format((($levels[$location_id]+1)*1000))." ".$projects[$location_id]['currency']; ?><br>
							<strong>Duration:</strong> <?php echo ($levels[$location_id]+1); ?> <?php echo ($levels[$location_id]+1 == 1)?"Day":"Days"; ?><br>
							<input class='small-button' type='button' value='Upgrade to Level <?php echo ($levels[$location_id]+1); ?>' onclick='upgradeRealmLocation(<?php echo $realm_id;?>, <?php echo $location_id;?>)'>
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