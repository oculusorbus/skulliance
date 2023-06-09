<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
		<a name="leaderboards" id="leaderboards"></a>
		<div class="row" id="row2">
			<div class="col1of2">
			    <div class="content">
				    <?php
						echo "<h2>Skulliance</h2>";
						checkLeaderboard($conn, false, 7);
					?>
				</div>
			</div>
			<div class="col1of2">
				<div class="content">
				    <?php
						echo "<h2>Galactico</h2>";
						checkLeaderboard($conn, false, 1);
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
?>
<script type="text/javascript" src="dropship.js"></script>
</html>