<?php
include_once 'db.php';
include 'webhooks.php';
include 'message.php';
include 'role.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';
?>

<a name="diamond-skulls" id="diamond-skulls"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
	<h2>Diamond Skulls</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content">
		<?php filterDiamondSkulls("diamond-skulls"); ?>
		<div id="nfts" class="nfts">
			<?php 
			if(isset($_SESSION['userData']['user_id'])){ 
				if($filterby == "MY" || $filterby == ""){
					$all = false;
				}else if($filterby == "ALL"){
					$all = true;
				}
				getNFTs($conn, 7, $all, true, $diamond_skull_id); 
			}else{
				echo "<p>You do not own a Diamond Skull NFT.<br><br>Please connect a Cardano wallet with a Diamond Skull NFT.</p>";
			} 
			?>
		</div>
		<div id="nfts" class="nfts">
			<div class='nft'><div class='nft-data'></div></div>
			<div class='nft'><div class='nft-data'></div></div>
			<div class='nft'><div class='nft-data'></div></div>
			<div class='nft'><div class='nft-data'></div></div>
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
	echo "<script type='text/javascript'>document.getElementById('filterDiamondSkulls').value = '".$filterby."';</script>";
}?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
</html>