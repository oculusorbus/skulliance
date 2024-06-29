<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
		<a name="leaderboards" id="leaderboards"></a>
		<div class="row" id="row1">
			<div class="col1of3">
			    <?php
					if($filterby != null && $filterby != 0 && $filterby != "missions" && $filterby != "monthly" && $filterby != "streaks"){
						$project = getProjectInfo($conn, $filterby);
						$title = $project["name"];
					}else if($filterby == null || $filterby == 0){
						$title = "All Projects";
						$filterby = 0;
					}else if($filterby == "missions"){
						$title = "Missions";
						$filterby = "missions";
					}else if($filterby == "monthly"){
						$title = date("F")." Missions";
						$filterby = "monthly";
					}else if($filterby == "streaks"){
						$title = "Daily Reward Streak";
						$filterby = "streaks";
					}
					echo "<h2>".$title."</h2>";?>
			    <div class="content" id="filtered-content">
				    <?php
						filterLeaderboard("leaderboards");
						if($filterby != "missions" && $filterby != "monthly" && $filterby != "streaks"){
							getTotalNFTs($conn, $filterby);
							checkLeaderboard($conn, false, $filterby);
						}else if($filterby == "missions"){
							checkMissionsLeaderboard($conn);
						}else if($filterby == "monthly"){
							checkMissionsLeaderboard($conn, true);
						}else if($filterby == "streaks"){
							checkStreaksLeaderboard($conn);
						}
					?>
				</div>
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
	echo "<script type='text/javascript'>document.getElementById('filterLeaderboard').value = '".$filterby."';</script>";
}?>
<script type="text/javascript" src="skulliance.js"></script>
</html>