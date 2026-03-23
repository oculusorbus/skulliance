<?php
include 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'credentials/hw_credentials.php';
include 'skulliance.php';
include 'header.php';
?>
		<a name="store" id="store"></a>
		<div class="row" id="row1">
    		<div class="main">
				<?php
				if($filterby != null && $filterby != 0 && $filterby != "exclusive"){
					$project = getProjectInfo($conn, $filterby);
					$title = $project["name"];
				}else if($filterby == "exclusive"){
					$title = "Exclusive Items";
				}else{
					$title = "All Projects";
					$filterby = 0;
				}
				echo "<h2>".$title."</h2>";?>
				<a name="store" id="store"></a>
				<div class="content" id="filtered-content">
					<?php
					filterItems("store");?>
					<div id="nfts" class="nfts store-nfts">
						<?php 
						if(!$member){
							echo "<p>You must become a member of Skulliance before you can claim items from the store.<br><br><a href='info.php'>View info on how to become a member of Skulliance.</a></p>";
						}
						if(str_contains($_SERVER["REQUEST_URI"], "staking")){	
							getItems($conn, "store", $filterby);
						}else{
							echo "<p>The store is disabled on the test server. Kick rocks.</p>";
							getItems($conn, "store", $filterby);
						}
						?>				
					</div>
				</div>
				<?php if(isset($_SESSION['userData']['user_id'])){ renderItemSubmissionForm($creators, "store"); } ?>
			</div>
		</div>
		<!-- Store image modal -->
		<div id="store-image-modal" onclick="closeStoreImageModal()" style="display:none;position:fixed;inset:0;background:rgba(0,0,0,0.85);z-index:1000;align-items:center;justify-content:center;flex-direction:column;gap:12px;cursor:zoom-out;">
			<img id="store-modal-img" src="" alt="" style="max-width:90vw;max-height:80vh;border-radius:8px;object-fit:contain;box-shadow:0 8px 40px rgba(0,0,0,0.7);">
			<p id="store-modal-name" style="color:#e8eef4;font-size:1rem;font-weight:bold;margin:0;"></p>
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
	echo "<script type='text/javascript'>document.getElementById('filterNFTs').value = '".$filterby."';</script>";
}?>
<script type="text/javascript" src="skulliance.js"></script>
<script type="text/javascript">
function openStoreImageModal(src, name){
	var modal = document.getElementById('store-image-modal');
	document.getElementById('store-modal-img').src = src;
	document.getElementById('store-modal-name').textContent = name || '';
	modal.style.display = 'flex';
}
function closeStoreImageModal(){
	document.getElementById('store-image-modal').style.display = 'none';
}
document.addEventListener('keydown', function(e){ if(e.key === 'Escape') closeStoreImageModal(); });
</script>
</html>