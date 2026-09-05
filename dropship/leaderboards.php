<?php
include 'db.php';
include 'webhooks.php';
include 'dropship.php';
include 'header.php';
?>

		<?php if($hideLeaderboard == "false") { ?>
		<a name="leaderboards" id="leaderboards"></a>
		<!-- Mobile-only view switch -- on desktop all three columns already
		     show side by side (no switching needed, hidden via CSS). On
		     mobile they used to just stack, meaning a long scroll past two
		     full tables to reach the third; this shows one at a time
		     instead, same icon-tab spirit as Missions/Realms' #quick-menu
		     but scoped to this page's own three views, not site navigation. -->
		<div class="lb-view-switch">
			<img src="icons/dropship.png" class="selected" onclick="lbSwitchView('current', this)" title="Current Game"/>
			<img src="icons/trophy.png" onclick="lbSwitchView('ath', this)" title="All Time High"/>
			<img src="icons/xp.png" onclick="lbSwitchView('xp', this)" title="Levels / XP"/>
		</div>
		<div class="row" id="row3">
			<div class="col1of3 lb-pane" id="lb-current">
			    <div class="content">
					<?php if(isset($_SESSION['userData']['game_id']) && $hideLeaderboard == "false") {
						echo "<h2>Current Game</h2>";
						checkLeaderboard($conn, false);
					} else {
						echo "<h2>No Active Game</h2>";
					}?>
				</div>
			</div>
			<div class="col1of3 lb-pane lb-hidden" id="lb-ath">
			    <div class="content">
					<?php if($hideLeaderboard == "false") {
						echo "<h2>All Time High</h2>";
						checkATHLeaderboard($conn, false);
					}?>
				</div>
			</div>
			<div class="col1of3 lb-pane lb-hidden" id="lb-xp">
				<div class="content">
					<?php if($hideLeaderboard == "false") {
						echo "<h2>Levels / XP</h2>";
						checkXPLeaderboard($conn, false);
					}?>
				</div>
			</div>
		</div>
		<script>
		function lbSwitchView(view, el){
			document.querySelectorAll('.lb-view-switch img').forEach(function(i){ i.classList.remove('selected'); });
			el.classList.add('selected');
			document.getElementById('lb-current').classList.toggle('lb-hidden', view !== 'current');
			document.getElementById('lb-ath').classList.toggle('lb-hidden', view !== 'ath');
			document.getElementById('lb-xp').classList.toggle('lb-hidden', view !== 'xp');
		}
		</script>
		<?php } ?>
		<!-- Footer -->
		<div class="footer">
		  <p>Drop Ship | Ohh Meed's Shorty Verse<br>Copyright © <span id="year"></span>
		</div>
	</div>
  </div>
</body>
<?php
// Close DB Connection
$conn->close();
?>
<script type="text/javascript" src="dropship.js"></script>
</html>