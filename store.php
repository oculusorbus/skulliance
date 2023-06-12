<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
		<a name="store" id="store"></a>
		<div class="row" id="row1">
			<div class="side">
				<div class="content" id="player-stats">
					<?php
					renderItemSubmissionForm($creators);
					?>
				</div>
			</div>
    		<div class="main">
		    	<h2>Store</h2>
				<a name="store" id="store"></a>
				<div class="content">
					<div id="nfts" class="nfts">
						<?php 
						getItems($conn);
						?>				
					</div>
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