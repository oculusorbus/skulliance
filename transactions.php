<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
		<div class="row" id="row4">
			<div class="col1of3">
				<h2>Transaction History</h2>
					<div class="content" id="transactions-pane">
						<?php
						transactionHistory($conn);
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
<script type="text/javascript" src="skulliance.js"></script>
</html>