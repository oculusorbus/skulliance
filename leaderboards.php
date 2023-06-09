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
						echo "<h2>All Projects</h2>";
						checkLeaderboard($conn, false);
					?>
				</div>
			</div>
			<div class="col1of2">
				<div class="content">
				    <?php
						echo "<h2>Skulliance</h2>";
						checkLeaderboard($conn, false, 7);
					?>
				</div>
			</div>
		</div>
		<div class="row" id="row2">
			<div class="col1of2">
			    <div class="content">
				    <?php
						echo "<h2>Galactico</h2>";
						checkLeaderboard($conn, false, 1);
					?>
				</div>
			</div>
			<div class="col1of2">
				<div class="content">
				    <?php
						echo "<h2>Ohh Meed</h2>";
						checkLeaderboard($conn, false, 2);
					?>
				</div>
			</div>
		</div>
		<div class="row" id="row2">
			<div class="col1of2">
			    <div class="content">
				    <?php
						echo "<h2>H.Y.P.E.</h2>";
						checkLeaderboard($conn, false, 3);
					?>
				</div>
			</div>
			<div class="col1of2">
				<div class="content">
				    <?php
						echo "<h2>Sinder Skullz</h2>";
						checkLeaderboard($conn, false, 4);
					?>
				</div>
			</div>
		</div>
		<div class="row" id="row2">
			<div class="col1of2">
			    <div class="content">
				    <?php
						echo "<h2>Kimosabe Art</h2>";
						checkLeaderboard($conn, false, 5);
					?>
				</div>
			</div>
			<div class="col1of2">
				<div class="content">
				    <?php
						echo "<h2>Crypties</h2>";
						checkLeaderboard($conn, false, 6);
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