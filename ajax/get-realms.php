<?php
include '../db.php';
include '../skulliance.php';

if(!isset($_SESSION['userData']['user_id'])){ exit; }
if(!checkRealm($conn) || checkRealmState($conn) != 1){ exit; }

$sort = isset($_GET['sort']) ? $_GET['sort'] : 'weakness';
$group = isset($_GET['group']) ? $_GET['group'] : 'Eligible';
?>
<div class="main">
	<a name="realms" id="realms-anchor"></a>
	<h2>Realms</h2>
	<div class="content realms" id="filtered-content">
		<div id="filter-nfts">
			<label for="filterRealms"><strong>Sort By:</strong></label>
			<select onchange="javascript:filterRealms(this.options[this.selectedIndex]);" name="filterRealms" id="filterRealms" class="dropdown">
				<optgroup label="Eligible">
					<option value="weakness" <?php echo $sort=='weakness'&&$group=='Eligible'?'selected':'';?>>Weakness</option>
					<option value="strength" <?php echo $sort=='strength'&&$group=='Eligible'?'selected':'';?>>Strength</option>
					<option value="wealth" <?php echo $sort=='wealth'&&$group=='Eligible'?'selected':'';?>>Wealth</option>
					<option value="random" <?php echo $sort=='random'&&$group=='Eligible'?'selected':'';?>>Random</option>
				</optgroup>
				<optgroup label="All">
					<option value="weakness" <?php echo $sort=='weakness'&&$group=='All'?'selected':'';?>>Weakness</option>
					<option value="strength" <?php echo $sort=='strength'&&$group=='All'?'selected':'';?>>Strength</option>
					<option value="wealth" <?php echo $sort=='wealth'&&$group=='All'?'selected':'';?>>Wealth</option>
					<option value="random" <?php echo $sort=='random'&&$group=='All'?'selected':'';?>>Random</option>
				</optgroup>
			</select>
		</div>
		<div id="realms-list">
			<?php getRealms($conn, $sort, $group); ?>
		</div>
	</div>
</div>
<?php $conn->close(); ?>
