<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
		<a name="policies" id="policies"></a>
		<div class="row" id="row1">
			<div class="col1of3">
				<?php
					if($filterby != null && $filterby != 0){
						$project = getProjectInfo($conn, $filterby);
						$title = $project["name"];
					}else{
						$title = "All Projects";
						$filterby = 0;
					}
					echo "<h2>".$title."</h2>";
					?>
			    <div class="content" id="filtered-content">
				    <?php
						filterPolicies("collections");
						getPoliciesListing($conn, $filterby);
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
	echo "<script type='text/javascript'>document.getElementById('filterPolicies').value = '".$filterby."';</script>";
}?>
<script type="text/javascript" src="skulliance.js"></script>
</html>