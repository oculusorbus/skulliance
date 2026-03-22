<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
$is_mobile = preg_match('/(android|iphone|ipad|ipod|mobile)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');
function strip_nft_images($html){
	return preg_replace("/<span class='nft-image'>.*?<\\/span>/s", '', $html);
}
include 'header.php';

$username="";
if(isset($_GET['username'])){
	$username = $_GET['username'];
}
?>

<a name="showcase" id="showcase"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
	<h2><?php echo ucfirst($username); ?> NFTs</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content" id="filtered-content">
		<?php filterNFTs("showcase", $username, "get"); ?>
		<div id="nfts" class="nfts">
			<?php
			if(isset($_SESSION['userData']['user_id'])){
				$visibility = getVisibilityByUsername($conn, $username);
				if($visibility == "2"){
					$page     = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
					$per_page = 24;
					$total_nfts  = countNFTs($conn, $filterby, $username);
					$total_pages = (int)ceil($total_nfts / $per_page);
					if($page > $total_pages && $total_pages > 0) $page = $total_pages;
					ob_start();
					getNFTs($conn, $filterby, $username, false, "", false, "", $page, $per_page);
					$html = ob_get_clean();
					echo $is_mobile ? strip_nft_images($html) : $html;
					if($total_pages > 1){
						$fb  = urlencode($filterby);
						$usr = urlencode($username);
						echo "<div class='nft-pagination'>";
						if($page > 1){
							echo "<a class='page-btn' href='showcase.php?username={$usr}&filterby={$fb}&page=".($page-1)."'>&#8592; Prev</a>";
						}else{
							echo "<span class='page-btn disabled'>&#8592; Prev</span>";
						}
						$prev = 0;
						for($p = 1; $p <= $total_pages; $p++){
							if($p == 1 || $p == $total_pages || ($p >= $page - 2 && $p <= $page + 2)){
								if($prev && $p - $prev > 1){
									echo "<span class='page-ellipsis'>&#8230;</span>";
								}
								if($p == $page){
									echo "<span class='page-btn active'>{$p}</span>";
								}else{
									echo "<a class='page-btn' href='showcase.php?username={$usr}&filterby={$fb}&page={$p}'>{$p}</a>";
								}
								$prev = $p;
							}
						}
						if($page < $total_pages){
							echo "<a class='page-btn' href='showcase.php?username={$usr}&filterby={$fb}&page=".($page+1)."'>Next &#8594;</a>";
						}else{
							echo "<span class='page-btn disabled'>Next &#8594;</span>";
						}
						echo "<div class='page-info'>Page {$page} of {$total_pages} &nbsp;({$total_nfts} NFTs)</div>";
						echo "</div>";
					}
				}else{
					echo "<p>This user does not allow visibility of their NFT collection";
				}
			}else{
				echo "<p>Please connect a Cardano wallet to view your qualifying NFTs.<br><br>Once you begin staking your NFTs, you will need to become a Skulliance member before you can claim items from the store.<br><br><a href='info.php'>View info on how to become a member of Skulliance.</a></p>";
			}
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
	echo "<script type='text/javascript'>document.getElementById('filterNFTs').value = '".$filterby."';</script>";
}?>
<script type="module" src="wallet.js?var=<?php echo rand(0,999); ?>"></script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
</html>