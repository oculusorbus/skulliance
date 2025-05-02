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
				switch (true) {
				    case ($filterby != null && $filterby != 0 && $filterby != "missions" && $filterby != "monthly" && 
				           $filterby != "streaks" && $filterby != "monthly-streaks" && $filterby != "raids" && 
				           $filterby != "monthly-raids" && $filterby != "factions" && $filterby != "monthly-factions" && 
				           $filterby != "swaps" && $filterby != "weekly-swaps" && $filterby != "bosses" && 
						   $filterby != "weekly-bosses") && $filterby != "monstrocity" && $filterby != "monthly-monstrocity"):
				        $project = getProjectInfo($conn, $filterby);
				        $title = $project["name"];
				        break;
				    case ($filterby == null || $filterby == 0):
				        $title = "All Projects";
				        $filterby = 0;
				        break;
				    case ($filterby == "missions"):
				        $title = "All Missions";
				        $filterby = "missions";
				        break;
				    case ($filterby == "monthly"):
				        $title = date("F") . " Missions";
				        $filterby = "monthly";
				        break;
				    case ($filterby == "streaks"):
				        $title = "Daily Rewards Streaks";
				        $filterby = "streaks";
				        break;
				    case ($filterby == "monthly-streaks"):
				        $title = date("F") . " Daily Rewards Streaks";
				        $filterby = "monthly-streaks";
				        break;
				    case ($filterby == "raids"):
				        $title = "All Raids";
				        $filterby = "raids";
				        break;
				    case ($filterby == "monthly-raids"):
				        $title = date("F") . " Raids";
				        $filterby = "monthly-raids";
				        break;
				    case ($filterby == "factions"):
				        $title = "All Factions";
				        $filterby = "factions";
				        break;
				    case ($filterby == "monthly-factions"):
				        $title = date("F") . " Factions";
				        $filterby = "monthly-factions";
				        break;
				    case ($filterby == "swaps"):
				        $title = "All Skull Swaps";
				        $filterby = "swaps";
				        break;
				    case ($filterby == "weekly-swaps"):
				        $title = "Weekly Skull Swaps";
				        $filterby = "weekly-swaps";
				        break;
				    case ($filterby == "bosses"):
				        $title = "All Boss Battles";
				        $filterby = "bosses";
				        break;
				    case ($filterby == "weekly-bosses"):
				        $title = "Weekly Boss Battles";
				        $filterby = "weekly-bosses";
				        break;
				    case ($filterby == "monstrocity"):
				        $title = "All Monstrocity";
				        $filterby = "monstrocity";
				        break;
				    case ($filterby == "monthly-monstrocity"):
				        $title = "Monthly Monstrocity";
				        $filterby = "monthly-monstrocity";
				        break;
				}
				echo "<h2>" . $title . "</h2>";
				?>

				<div class="content" id="filtered-content">
				    <?php
				    filterLeaderboard("leaderboards");
    
				    switch (true) {
				        case ($filterby != "missions" && $filterby != "monthly" && $filterby != "streaks" && 
				              $filterby != "monthly-streaks" && $filterby != "raids" && $filterby != "monthly-raids" && 
				              $filterby != "factions" && $filterby != "monthly-factions" && $filterby != "swaps" && 
				              $filterby != "weekly-swaps" && $filterby != "bosses" && $filterby != "weekly-bosses" && 
							  $filterby != "monstrocity" && $filterby != "monthly-monstrocity"):
				            getTotalNFTs($conn, $filterby);
				            checkLeaderboard($conn, false, $filterby);
				            break;
				        case ($filterby == "missions"):
				            checkMissionsLeaderboard($conn);
				            break;
				        case ($filterby == "monthly"):
				            checkMissionsLeaderboard($conn, true);
				            break;
				        case ($filterby == "streaks"):
				            checkStreaksLeaderboard($conn);
				            break;
				        case ($filterby == "monthly-streaks"):
				            checkStreaksLeaderboard($conn, true);
				            break;
				        case ($filterby == "raids"):
				            checkRaidsLeaderboard($conn);
				            break;
				        case ($filterby == "monthly-raids"):
				            checkRaidsLeaderboard($conn, true);
				            break;
				        case ($filterby == "factions"):
				            checkFactionsLeaderboard($conn);
				            break;
				        case ($filterby == "monthly-factions"):
				            checkFactionsLeaderboard($conn, true);
				            break;
				        case ($filterby == "swaps"):
				            checkSkullSwapsLeaderboard($conn);
				            break;
				        case ($filterby == "weekly-swaps"):
				            checkSkullSwapsLeaderboard($conn, true);
				            break;
				        case ($filterby == "bosses"):
				            checkBossBattlesLeaderboard($conn);
				            break;
				        case ($filterby == "weekly-bosses"):
				            checkBossBattlesLeaderboard($conn, true);
				            break;
				        case ($filterby == "monstrocity"):
				            checkMonstrocityLeaderboard($conn);
				            break;
				        case ($filterby == "monthly-monstrocity"):
				            checkMonstrocityLeaderboard($conn, true);
				            break;
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