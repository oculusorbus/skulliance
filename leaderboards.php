<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
		<a name="leaderboards" id="leaderboards"></a>
		<div class="row" id="row1">
			    <div class="content">
 					<?php filterLeaderboard("leaderboard"); ?>
				    <?php
						echo "<h2>All Projects</h2>";
						checkLeaderboard($conn, false, $filterby);
					?>
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
// Close DB Connection
$conn->close();
if($filterby != ""){
	echo "<script type='text/javascript'>document.getElementById('filterNFTs').value = '".$filterby."';</script>";
}?>
?>
<script type="text/javascript" src="skulliance.js"></script>
</html>