<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';

$realm_status = checkRealm($conn);

if($realm_status){ ?>
<div class="row">	
	<div class="main">
		<?php
			echo '<div id="raids">';
			$outgoing_raids = getRaids($conn, "outgoing", "pending", true); 
			if(isset($outgoing_raids)){
				echo '<div class="content raids">';
				echo $outgoing_raids;
				echo '</div>';
			}	
			$outgoing_completed = getRaids($conn, "outgoing", "completed", true); 
			if(isset($outgoing_completed)){
				echo '<div class="content raids">';
				echo $outgoing_completed;
				echo '</div>';
			}	
			$incoming_raids = getRaids($conn, "incoming", "pending", true); 
			if(isset($incoming_raids)){
				echo '<div class="content raids">';
				echo $incoming_raids;
				echo '</div>';
			}	
			$incoming_completed = getRaids($conn, "incoming", "completed", true); 
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
?>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
</html>