<?php
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
$is_mobile = preg_match('/(android|iphone|ipad|ipod|mobile)/i', $_SERVER['HTTP_USER_AGENT'] ?? '');

// Static rarity ranking: skull number => rank
$skull_ranks = [
	22=>1,  81=>2,  90=>3,  24=>4,  85=>5,  88=>6,  4=>7,   47=>8,  64=>9,  71=>10,
	82=>11, 83=>12, 46=>13, 34=>14, 70=>15, 1=>16,  16=>17, 69=>18, 80=>19, 32=>20,
	77=>21, 89=>22, 45=>23, 38=>24, 57=>25, 2=>26,  6=>27,  3=>28,  20=>29, 25=>30,
	26=>31, 54=>32, 76=>33, 9=>34,  30=>35, 44=>36, 56=>37, 65=>38, 52=>39, 55=>40,
	12=>41, 27=>42, 62=>43, 87=>44, 5=>45,  14=>46, 58=>47, 86=>48, 8=>49,  18=>50,
	31=>51, 48=>52, 7=>53,  17=>54, 39=>55, 41=>56, 42=>57, 51=>58, 53=>59, 78=>60,
	40=>61, 60=>62, 67=>63, 29=>64, 63=>65, 68=>66, 74=>67, 75=>68, 21=>69, 59=>70,
	66=>71, 84=>72, 11=>73, 13=>74, 43=>75, 50=>76, 61=>77, 79=>78, 92=>79, 36=>80,
	37=>81, 72=>82, 73=>83, 93=>84, 94=>85, 15=>86, 33=>87, 35=>88, 91=>89, 95=>90,
	96=>91, 97=>92, 10=>93, 19=>94, 23=>95, 28=>96, 49=>97, 98=>98, 99=>99, 100=>100,
];
// Strip <span class='nft-image'>...</span> blocks so images are never sent to mobile
function strip_nft_images($html){
	return preg_replace("/<span class='nft-image'>.*?<\\/span>/s", '', $html);
}
function render($callable, $is_mobile){
	ob_start();
	$callable();
	$html = ob_get_clean();
	echo $is_mobile ? strip_nft_images($html) : $html;
}
include 'header.php';
?>

<a name="diamond-skulls" id="diamond-skulls"></a>
<!-- The flexible grid (content) -->
<div class="row" id="row1">
  <div class="main">
	<?php if($filterbydiamond == "MY"){ ?>
	<h2>My Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "DELEGATED"){ ?>
	<h2>My Delegated Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "ALL" || $filterbydiamond == ""){ ?>
	<h2>All Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "ALL DELEGATED"){ ?>
	<h2>All Delegated Diamond Skulls</h2>
	<?php }else if($filterbydiamond == "EMPTY"){ ?>
	<h2>Empty Diamond Skulls</h2>
	<?php } ?>
	<a name="diamond-skulls" id="diamond-skulls"></a>
    <div class="content" id="filtered-content">
		<?php filterDiamondSkulls("diamond-skulls"); ?>
		<div id="nfts" class="nfts">
			<?php
			if(isset($_SESSION['userData']['user_id'])){
				if($filterbydiamond == "MY"){
					$advanced_filter = "my";
				}else if($filterbydiamond == "ALL" || $filterbydiamond == ""){
					$advanced_filter = "all";
				}else if($filterbydiamond == "DELEGATED"){
					$advanced_filter = "delegated";
				}else if($filterbydiamond == "ALL DELEGATED"){
					$advanced_filter = "all delegated";
				}else if($filterbydiamond == "EMPTY"){
					$advanced_filter = "empty";
				}
				if($diamond_skull_id != ""){
					$_SESSION['userData']['diamond_skull_id'] = $diamond_skull_id;
				}
				if(!isset($_SESSION['userData']['diamond_skull_id'])){
					$_SESSION['userData']['diamond_skull_id'] = "";
				}
				$diamond_skull_totals = getDiamondSkullTotals($conn);
				render(function() use ($conn, $advanced_filter, $diamond_skull_totals){
					getNFTs($conn, 7, $advanced_filter, $diamond_skull=true, $_SESSION['userData']['diamond_skull_id'], false, $diamond_skull_totals);
				}, $is_mobile);
			}else{
				echo "<p>You do not own a Diamond Skull NFT.<br><br>Please connect a Cardano wallet with a Diamond Skull NFT.</p>";
			}
			?>
		</div>
    </div>
  </div>
</div>
<?php


if($_SESSION['userData']['diamond_skull_id'] != ""){ ?>
<a name="diamond-skull" id="diamond-skull"></a>
<h2>Diamond Skull Delegation<?php
$skull_name_row = $conn->query("SELECT name FROM nfts WHERE id='".intval($_SESSION['userData']['diamond_skull_id'])."' LIMIT 1")->fetch_assoc();
if($skull_name_row && preg_match('/#(\d+)/', $skull_name_row['name'], $skm)){
	$skn = (int)$skm[1];
	if(isset($skull_ranks[$skn])){
		echo " &mdash; Rarity Rank #".$skull_ranks[$skn]." of 100";
	}
}
?></h2>
<div class="diamond-container">
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php render(function() use ($conn, $projects, $project_names){ getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 5, $projects, $project_names); }, $is_mobile); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php render(function() use ($conn, $projects, $project_names){ getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 6, $projects, $project_names); }, $is_mobile); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php render(function() use ($conn, $projects, $project_names){ getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 4, $projects, $project_names); }, $is_mobile); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php render(function() use ($conn, $projects, $project_names){ getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 3, $projects, $project_names); }, $is_mobile); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php render(function() use ($conn, $projects, $project_names){ getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 2, $projects, $project_names); }, $is_mobile); ?>
		</div>
	</div>
	</div>
</div>
<div class="row" id="row1">
    <div class="main-diamond">
    <div class="content">
		<div id="nfts" class="nfts">
			<?php render(function() use ($conn, $projects, $project_names){ getDiamondSkullNFTs($conn, $_SESSION['userData']['diamond_skull_id'], 1, $projects, $project_names); }, $is_mobile); ?>
		</div>
	</div>
	</div>
</div>
</div>
<div class="row" id="row1">
  <div class="main">
	<h2>NFTs</h2>
	<a name="holdings" id="holdings"></a>
    <div class="content" id="filtered-content">
		<?php filterCoreNFTs("diamond-skulls"); ?>
		<div id="nfts" class="nfts">
			<?php
			if(isset($_SESSION['userData']['user_id'])){
				render(function() use ($conn){
					getNFTs($conn, $_SESSION['userData']['filterby'], $advanced_filter="", $diamond_skull=false, $diamond_skull_id="", $core_projects=true);
				}, $is_mobile);
			}else{
				echo "<p>You do not own any qualifying NFTs.<br><br>Please connect a Cardano wallet to view your NFTs.</p>";
			}
			?>
		</div>
    </div>
  </div>
</div>

<?php } ?>

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
<?php if($is_mobile): ?>
<script type="text/javascript">
if(typeof revealObserver !== 'undefined'){ revealObserver.disconnect(); }
document.querySelectorAll('section.reveal').forEach(function(el){ el.classList.add('active'); });
</script>
<?php endif; ?>
</html>
