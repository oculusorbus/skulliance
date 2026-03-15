<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
<style>
@keyframes podium-user-glow {
  0%, 100% { box-shadow: 0 0 4px 2px rgba(0,200,160,0.15); }
  50%       { box-shadow: 0 0 22px 7px rgba(0,200,160,0.75); }
}
@keyframes podium-rise {
  from { transform: scaleY(0); transform-origin: bottom; }
  to   { transform: scaleY(1); transform-origin: bottom; }
}
@keyframes podium-fade-in {
  from { opacity: 0; transform: translateY(12px); }
  to   { opacity: 1; transform: translateY(0); }
}
.podium-wrap {
  display: flex;
  justify-content: center;
  align-items: flex-end;
  gap: 6px;
  margin: 20px 0 0;
}
@media (max-width: 600px) {
  .podium-wrap { justify-content: flex-start; gap: 4px; overflow-x: auto; padding-bottom: 4px; }
  .podium-slot { width: 100px; }
  .podium-rank-1 .podium-avatar { width: 68px; height: 68px; }
  .podium-avatar { width: 56px; height: 56px; }
}
.podium-slot {
  display: flex;
  flex-direction: column;
  align-items: center;
  width: 130px;
}
.podium-medal { font-size: 1.6rem; margin-bottom: 4px; line-height: 1; }
.podium-avatar {
  width: 68px; height: 68px;
  border-radius: 50%;
  border: 3px solid #888;
  object-fit: cover;
  margin-bottom: 7px;
  transition: transform 0.2s;
}
.podium-avatar:hover { transform: scale(1.08); }
.podium-rank-1 .podium-avatar { width: 84px; height: 84px; border-color: #FFD700; box-shadow: 0 0 18px rgba(255,215,0,0.45); }
.podium-rank-2 .podium-avatar { border-color: #C0C0C0; box-shadow: 0 0 10px rgba(192,192,192,0.3); }
.podium-rank-3 .podium-avatar { border-color: #CD7F32; box-shadow: 0 0 10px rgba(205,127,50,0.3); }
.podium-name {
  font-size: 0.82rem; font-weight: bold;
  text-decoration: none;
  margin-bottom: 3px; text-align: center;
  max-width: 120px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  display: block;
}
.podium-rank-1 .podium-name { color: #FFD700; font-size: 0.92rem; }
.podium-rank-2 .podium-name { color: #C0C0C0; }
.podium-rank-3 .podium-name { color: #CD7F32; }
.podium-score { font-size: 0.72rem; color: rgba(255,255,255,0.5); margin-bottom: 8px; text-align: center; }
.podium-platform {
  width: 100%; border-radius: 4px 4px 0 0;
  display: flex; align-items: center; justify-content: center;
}
.podium-rank-1 .podium-platform { height: 100px; background: linear-gradient(180deg,rgba(255,215,0,0.22) 0%,rgba(255,215,0,0.06) 100%); border-top: 3px solid #FFD700; }
.podium-rank-2 .podium-platform { height: 70px;  background: linear-gradient(180deg,rgba(192,192,192,0.22) 0%,rgba(192,192,192,0.06) 100%); border-top: 3px solid #C0C0C0; }
.podium-rank-3 .podium-platform { height: 50px;  background: linear-gradient(180deg,rgba(205,127,50,0.22) 0%,rgba(205,127,50,0.06) 100%); border-top: 3px solid #CD7F32; }
.podium-rank-num { font-size: 1.3rem; font-weight: bold; opacity: 0.45; }
.podium-rank-1 .podium-rank-num { color: #FFD700; opacity: 0.6; }
.podium-rank-2 .podium-rank-num { color: #C0C0C0; }
.podium-rank-3 .podium-rank-num { color: #CD7F32; }
.podium-section {
  position: relative;
  padding: 16px 10px 0;
  margin-bottom: 20px;
  border-radius: 0;
  overflow: hidden;
  width: 100vw;
  left: 50%;
  transform: translateX(-50%);
  border-top: 1px solid #00c8a0;
  border-bottom: 1px solid #00c8a0;
}
.podium-section.has-theme {
  background-size: cover;
  background-position: center;
  background-attachment: fixed;
}
.podium-section.has-theme::before {
  content: '';
  position: absolute;
  inset: 0;
  background: rgba(0,0,0,0.65);
  pointer-events: none;
  z-index: 0;
}
.podium-section > * { position: relative; z-index: 1; }
.podium-slot {
  background: #1a1a1a;
  border-radius: 8px 8px 0 0;
  padding: 8px 4px 0;
}
</style>

<?php
function renderPodium($top3, $conn=null, $override_theme_id=null){
  if(!$top3 || count($top3) < 2) return;
  $medals  = ['🥇','🥈','🥉'];
  $ranks   = [1,2,3];
  // rise delays: gold first, silver second, bronze third
  $delays  = [0.1, 0.4, 0.7]; // indexed by $pos (1st=0, 2nd=1, 3rd=2)
  $me = isset($_SESSION['userData']['username']) ? $_SESSION['userData']['username'] : '';

  $is_faction = !empty($top3[0]['faction']);

  // Background: project override first, then faction project, then gold user's realm theme
  $gold_theme_id = $override_theme_id;
  if(!$gold_theme_id){
    if($is_faction){
      $gold_theme_id = $top3[0]['project_id'];
    } else if($conn && !empty($top3[0]['username'])){
      $gn = mysqli_real_escape_string($conn, $top3[0]['username']);
      $tr = $conn->query("SELECT theme_id FROM realms INNER JOIN users ON users.id = realms.user_id WHERE users.username = '".$gn."' AND realms.active = 1 LIMIT 1");
      if($tr && $tr->num_rows > 0) $gold_theme_id = $tr->fetch_assoc()['theme_id'];
    }
  }
  // Display order: 2nd (left), 1st (center), 3rd (right)
  $display = [1, 0, 2];
  $section_class = 'podium-section' . ($gold_theme_id ? ' has-theme' : '');
  $section_style = $gold_theme_id ? ' style="background-image:url(\'images/themes/'.intval($gold_theme_id).'.jpg\')"' : '';
  echo '<div class="'.$section_class.'"'.$section_style.'>';
  echo '<div class="podium-wrap">';
  foreach($display as $pos){
    if(!isset($top3[$pos])) continue;
    $u     = $top3[$pos];
    $rank  = $ranks[$pos];
    $delay = $delays[$pos];
    $fade_in     = 'podium-fade-in 0.5s ease '.($delay + 0.3).'s both';
    $above_style = 'animation: '.$fade_in.';';
    $platform_style = 'animation: podium-rise 0.6s cubic-bezier(0.34,1.56,0.64,1) '.$delay.'s both;';
    echo '<div class="podium-slot podium-rank-'.$rank.'">';
    echo   '<div class="podium-medal" style="'.$above_style.'">'.$medals[$pos].'</div>';
    if($is_faction){
      echo '<img class="podium-avatar" src="icons/'.strtolower(htmlspecialchars($u['currency'])).'.png" onerror="this.src=\'icons/skull.png\'" alt="" style="'.$above_style.';border-radius:0;border:none;box-shadow:none;background:none;">';
      echo '<span class="podium-name" style="'.$above_style.'">'.htmlspecialchars($u['project_name']).'</span>';
    } else {
      $prof = 'profile.php?username='.urlencode($u['username']);
      $av   = ($u['avatar'] && $u['discord_id'])
            ? 'https://cdn.discordapp.com/avatars/'.htmlspecialchars($u['discord_id']).'/'.htmlspecialchars($u['avatar']).'.png'
            : 'icons/skull.png';
      $is_me = ($me !== '' && $u['username'] === $me);
      $avatar_style = $is_me
        ? 'animation: '.$fade_in.', podium-user-glow 1.4s ease-in-out '.($delay + 0.9).'s infinite;'
        : $above_style;
      echo '<a href="'.$prof.'" style="'.$above_style.'">';
      echo   '<img class="podium-avatar" src="'.htmlspecialchars($av).'" onerror="this.src=\'icons/skull.png\'" alt="" style="'.$avatar_style.'">';
      echo '</a>';
      echo '<a href="'.$prof.'" class="podium-name" style="'.$above_style.'">'.htmlspecialchars($u['username']).'</a>';
    }
    echo   '<span class="podium-score" style="'.$above_style.'">'.htmlspecialchars($u['score']).'</span>';
    echo   '<div class="podium-platform" style="'.$platform_style.'"><span class="podium-rank-num">#'.$rank.'</span></div>';
    echo '</div>';
  }
  echo '</div>'; // podium-wrap
  echo '</div>'; // podium-section
}
?>

		<a name="leaderboards" id="leaderboards"></a>
		<div class="row" id="row1">
			<div class="col1of3" style="max-width:900px;margin:0 auto;flex:1 1 100%;">
				<?php
				switch (true) {
				    case ($filterby != null && $filterby != 0 && $filterby != "missions" && $filterby != "monthly" && 
				           $filterby != "streaks" && $filterby != "monthly-streaks" && $filterby != "raids" && 
				           $filterby != "monthly-raids" && $filterby != "factions" && $filterby != "monthly-factions" && 
				           $filterby != "swaps" && $filterby != "weekly-swaps" && $filterby != "bosses" && 
						   $filterby != "weekly-bosses" && $filterby != "monstrocity" && $filterby != "monthly-monstrocity"):
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
				    $leaderboard_top3 = [];
				    ob_start();
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
				    $table_html = ob_get_clean();
				    $project_theme_override = null;
				    if(is_numeric($filterby) && $filterby > 0){
				        $pt = intval($filterby);
				        if(file_exists('images/themes/'.$pt.'.jpg')) $project_theme_override = $pt;
				    }
				    renderPodium($leaderboard_top3, $conn, $project_theme_override);
				    echo $table_html;
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