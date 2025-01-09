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
					if($filterby != null && $filterby != 0 && $filterby != "missions" && $filterby != "monthly" && $filterby != "streaks" && $filterby != "monthly-streaks" && $filterby != "raids" && $filterby != "monthly-raids"){
						$project = getProjectInfo($conn, $filterby);
						$title = $project["name"];
					}else if($filterby == null || $filterby == 0){
						$title = "All Projects";
						$filterby = 0;
					}else if($filterby == "missions"){
						$title = "All Missions";
						$filterby = "missions";
					}else if($filterby == "monthly"){
						$title = date("F")." Missions";
						$filterby = "monthly";
					}else if($filterby == "streaks"){
						$title = "Daily Rewards Streaks";
						$filterby = "streaks";
					}else if($filterby == "monthly-streaks"){
						$title = date("F")." Daily Rewards Streaks";
						$filterby = "monthly-streaks";
					}else if($filterby == "raids"){
						$title = "All Raids";
						$filterby = "raids";
					}else if($filterby == "monthly-raids"){
						$title = date("F")." Raids";
						$filterby = "monthly-raids";
					}
					echo "<h2>".$title."</h2>";?>
			    <div class="content" id="filtered-content">
				    <?php
						filterLeaderboard("leaderboards");
						if($filterby != "missions" && $filterby != "monthly" && $filterby != "streaks" && $filterby != "monthly-streaks"){
							getTotalNFTs($conn, $filterby);
							checkLeaderboard($conn, false, $filterby);
						}else if($filterby == "missions"){
							checkMissionsLeaderboard($conn);
						}else if($filterby == "monthly"){
							checkMissionsLeaderboard($conn, true);
						}else if($filterby == "streaks"){
							checkStreaksLeaderboard($conn);
						}else if($filterby == "monthly-streaks"){
							checkStreaksLeaderboard($conn, true);
						}else if($filterby == "raids"){
							checkRaidsLeaderboard($conn);
						}else if($filterby == "monthly-raids"){
							checkRaidsLeaderboard($conn, true);
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