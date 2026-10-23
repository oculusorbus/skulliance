<?php
include 'db.php';
include 'webhooks.php';
include 'dropship.php';

/*
 * THE LINK BEING HIDDEN IS NOT THE GATE. header.php stops offering this
 * page to somebody the theme will not admit, which is convenience; the
 * URL still works, and anybody who has bookmarked it or switched themes
 * with the page open would walk straight in. So it is checked on
 * arrival too, exactly as admin-themes.php gates again after the nav
 * has already hidden its own link.
 *
 * BEFORE header.php, because a redirect after output is a notice and a
 * page that renders anyway. Sent to the dashboard rather than refused,
 * because the dashboard is where the Play gate explains what the theme
 * actually wants.
 */
if (function_exists('dropshipThemeUnlocked')
    && !dropshipThemeUnlocked($conn, $_SESSION['userData']['dropship_project_id'] ?? 0, $vip ?? 'false')) {
	header('Location: dashboard.php');
	exit;
}
include 'header.php';
?>
		<div class="row" id="row3">
			<div class="main">
				<h2>Achievements</h2>
			    <div class="content">
					<div id="achievements" class="achievements">
						<?php loadAchievements($conn, $heavy, $medium, $light, $base, $melee, $demolition, $extralife); ?>
					</div>
				</div>
			</div>
		</div>

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
<script type="text/javascript" src="<?php echo dropshipAsset('dropship.js'); ?>"></script>
<script type="text/javascript">
// Reorganize achivements with unlocked at top, locked at bottom
var unlocked = "";
var locked = "";
[].forEach.call(document.querySelectorAll('.unlocked'), function (el) {
  unlocked += el.outerHTML;
});
[].forEach.call(document.querySelectorAll('.locked'), function (el) {
  locked += el.outerHTML;
});
var achievements = document.getElementById('achievements');
achievements.innerHTML = "";
achievements.innerHTML = unlocked;
achievements.innerHTML += locked;
</script>
</html>