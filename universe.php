<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
<style>
body {
	background:url('images/space.jpg');
}	
</style>
		<div class="row" id="row1">
			<div class="col1of3">
			    <div class="content">

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