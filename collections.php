<?php
include 'db.php';
include 'webhooks.php';
include 'skulliance.php';
include 'header.php';
?>
<style>
/* The chain badge in the Collections table. The logos are the same
   white-on-transparent marks the wallet modal uses, so they need no
   plate on this dark ground. 24px because the logo is now alone in the
   column -- the chain name is a tooltip, not a label. help cursor is
   the standing convention for "there is a title here". */
.chain-badge { display: inline-flex; align-items: center; cursor: help; }
.chain-badge-i { width: 24px; height: 24px; object-fit: contain; flex: 0 0 24px; opacity: .9;
  transition: opacity .15s; }
.chain-badge:hover .chain-badge-i { opacity: 1; }
.chain-badge-m { display: inline-flex; align-items: center; justify-content: center;
  background: #123049; color: #00c8a0; font-size: .6rem; font-weight: 700; letter-spacing: .02em; }
#filter-nfts select { margin-right: 8px; }
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