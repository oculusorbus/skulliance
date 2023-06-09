<?php
include 'db.php';
include 'webhooks.php';
include 'dropship.php';
include 'header.php';
?>

		<?php if($hideLeaderboard == "false") { ?>
		<a name="leaderboards" id="leaderboards"></a>
		<div class="row" id="row3">
			<div class="col1of3">
			    <div class="content">
				    <?php
						echo "<h2>Skulliance</h2>";
						checkATHLeaderboard($conn, false, 7);
					?>
				</div>
			</div>
		</div>
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