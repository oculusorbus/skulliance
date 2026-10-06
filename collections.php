<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
<style>
/* The chain badge in the Collections table. The logos are the same
   white-on-transparent marks the wallet modal uses, so they need no
   plate on this dark ground. 20px: big enough to tell four chains apart
   at a glance, small enough not to set the row height. */
.chain-badge { display: inline-flex; align-items: center; gap: 7px; white-space: nowrap; }
.chain-badge-i { width: 20px; height: 20px; object-fit: contain; flex: 0 0 20px; opacity: .9; }
.chain-badge-m { display: inline-flex; align-items: center; justify-content: center;
  background: #123049; color: #00c8a0; font-size: .58rem; font-weight: 700; letter-spacing: .02em; }
.chain-badge-n { font-size: .86em; }
#filter-nfts select { margin-right: 8px; }
/* Under ~560px the name doubles the column width for no information the
   logo has not already given. */
@media (max-width: 560px) { .chain-badge-n { display: none; } }
</style>
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
						getPoliciesListing($conn, $filterby, isset($filterchain) ? $filterchain : 0);
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