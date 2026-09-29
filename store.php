<?php
include 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'credentials/hw_credentials.php';
include 'skulliance.php';
include 'header.php';
?>
<?php
/*
 * The second page migrated off db.php's renderers, same pattern as
 * my-nfts.php: getItemsData() returns the rows and the arithmetic, this
 * page owns the markup, getItems() is untouched so a revert is one line.
 *
 * EVERYTHING INHERITED WAS CHECKED FIRST this time rather than found in
 * review -- .main's `text-align: center`, .content's `border-radius: 10px`,
 * #filtered-content's `top: -40px`, #filter-nfts' `top: -35px`, and
 * .nft-image's `max-height: 170px; overflow-y: hidden`, which crops any
 * item photo that is not square. All neutralised in the CSS below.
 */
if ($filterby != null && $filterby != 0 && $filterby != "exclusive") {
	$project = getProjectInfo($conn, $filterby);
	$st_title = $project["name"];
} else if ($filterby == "exclusive") {
	$st_title = "Exclusive Items";
} else {
	$st_title = "All Projects";
	$filterby = 0;
}
$st_live  = str_contains($_SERVER["REQUEST_URI"], "staking");
$st_items = getItemsData($conn, $filterby);

/* What a visitor actually wants to know at a glance: how much is here,
   and how much of it they can afford right now. The old page said
   neither -- you had to read every card to find out. */
$st_afford = 0; $st_owned = 0;
foreach ($st_items as $it) {
	if (!empty($it['owned'])) $st_owned++;
	elseif (!empty($it['affordable'])) $st_afford++;
}
?>
		<a name="store" id="store"></a>
		<div class="row" id="row1">
    		<div class="main">

				<div class="st-head">
					<div class="st-head-left">
						<span class="st-kick">Staking store</span>
						<h2 class="st-title"><?php echo htmlspecialchars($st_title); ?></h2>
						<p class="st-ctx">Spend the points your NFTs earn. Nothing here is for sale in any other currency.</p>
					</div>
					<div class="st-head-right">
						<?php /* The filter sits in the masthead deliberately -- its
						         -35px offset would otherwise land it on the tiles. */ ?>
						<?php filterItems("store"); ?>
						<?php if ($st_items): ?>
						<div class="st-figures">
							<div class="st-fig"><b><?php echo number_format(count($st_items)); ?></b><span>Items</span></div>
							<?php if (isset($_SESSION['userData']['user_id'])): ?>
							<div class="st-fig"><b><?php echo number_format($st_afford); ?></b><span>You can afford</span></div>
							<?php if ($st_owned): ?>
							<div class="st-fig"><b><?php echo number_format($st_owned); ?></b><span>Claimed</span></div>
							<?php endif; ?>
							<?php endif; ?>
						</div>
						<?php endif; ?>
					</div>
				</div>

				<?php if (!$member): ?>
				<div class="st-notice">
					<strong>Membership unlocks claiming.</strong>
					Staking works without it, but items are claimed by members.
					<a href="info.php">How membership works &rarr;</a>
				</div>
				<?php endif; ?>
				<?php if (!$st_live): ?>
				<div class="st-notice st-warn"><strong>Test server.</strong> The store is disabled here.</div>
				<?php endif; ?>

				<div class="content" id="filtered-content">
					<div id="nfts" class="nfts store-nfts">
					<?php if (!$st_items): ?>
						<div class="st-empty">
							<h3>Nothing in the store right now</h3>
							<p>No items are listed<?php echo $filterby ? ' for this filter' : ''; ?>.
							   Projects add incentives as they go, so this fills up again.</p>
						</div>
					<?php else: foreach ($st_items as $row):
						$pid = (int)$row['project_id']; ?>
						<div class="nft store-item<?php echo !empty($row['owned']) ? ' st-owned' : ''; ?>">
							<div class="nft-data">
								<span class="nft-image"><img loading="lazy"
									onError="this.src='/staking/icons/skull.png';"
									src="<?php echo htmlspecialchars($row['image_url']); ?>"
									style="cursor:zoom-in;"
									onclick="openStoreImageModal(this.src, this.closest('.nft-data').querySelector('.nft-name').textContent)"></span>
								<span class="nft-name"><?php echo htmlspecialchars($row['item_name']); ?></span>

								<?php /* Project and stock on one line instead of two
								         stacked label/value pairs -- at a glance you
								         want who made it and whether it is nearly gone. */ ?>
								<span class="st-meta">
									<?php echo htmlspecialchars($row['project_name']); ?>
									<i<?php echo ((int)$row['quantity'] > 0 && (int)$row['quantity'] <= 3) ? ' class="st-low"' : ''; ?>>
										<?php echo (int)$row['quantity'] < 0 ? 'unlimited' : (int)$row['quantity'] . ' left'; ?>
									</i>
								</span>

								<div class="st-buy">
								<?php if (!empty($row['owned'])): ?>
									<span class="st-claimed">&#10003; Claimed</span>
								<?php endif; ?>
								<?php foreach ($row['options'] as $opt):
									$verbiage = "BUY: " . number_format($opt['price']) . " " . $opt['currency'];
									renderBuyButton($row["item_id"], $opt['project_id'], $verbiage, $pid, "store",
										!$opt['can_afford'] || !empty($row['owned']));
								endforeach; ?>
								<?php if (!empty($row['logged_in'])): ?>
								<?php /* Balances are the reason a button is greyed out,
								         so they belong beside the buttons, not above
								         the picture in a separate stack. */ ?>
								<span class="st-bal">
								<?php foreach ($row['options'] as $opt): ?>
									<i><img src="icons/<?php echo strtolower($opt['currency']); ?>.png" alt=""><?php
										echo number_format($opt['balance']) . ' ' . htmlspecialchars($opt['currency']); ?></i>
								<?php endforeach; ?>
								</span>
								<?php endif; ?>
								</div>
							</div>
						</div>
					<?php endforeach; endif; ?>
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

<style>
/* Scoped to store.php. Same approach as my-nfts.php: override what the
   platform imposes, change nothing in flexbox.css, so every other page
   that uses .nft / .nft-data / .nft-image is untouched.

   NEUTRALISED HERE, all found by checking before building rather than in
   review: .main's text-align:center, .content's border-radius:10px,
   #filtered-content's top:-40px, #filter-nfts' top:-35px, and
   .nft-image's max-height:170px + overflow-y:hidden, which crops any
   item photo that is not square. */
.st-head, .st-head * { text-align: left; }
.st-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 24px; flex-wrap: wrap; margin: 0 0 18px;
  border-bottom: 1px solid rgba(0,200,160,.14); padding-bottom: 16px;
}
.st-head-right { display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap; }
.st-kick { display: block; font-size: .7rem; letter-spacing: .16em; text-transform: uppercase; color: #00c8a0; }
.st-title { margin: 2px 0 0; line-height: 1.1; font-size: clamp(1.5rem, 3vw, 2.1rem);
  text-transform: none; color: #e8eaed; letter-spacing: -.01em; }
.st-ctx { margin: 8px 0 0; font-size: .82rem; color: #7a9eb0; }
.st-figures { display: flex; gap: 8px; flex-wrap: wrap; }
.st-fig { background: #0a1929; border: 1px solid rgba(0,200,160,.14);
  border-radius: 0; padding: 9px 15px; min-width: 96px; }
.st-fig b { display: block; font-size: 1.25rem; color: #00c8a0; line-height: 1.1; }
.st-fig span { font-size: .64rem; color: #7a9eb0; letter-spacing: .05em; text-transform: uppercase; }

#filter-items, #filter-nfts { position: static; top: 0; text-align: left; font-size: 1rem; margin: 0; }
#filter-items label, #filter-nfts label { display: block; font-size: .64rem; letter-spacing: .05em;
  text-transform: uppercase; color: #7a9eb0; margin-bottom: 4px; }
#filter-items select, #filter-nfts select, #filterNFTs { border-radius: 0; height: 38px; }

.st-notice {
  background: #0a1929; border: 1px solid rgba(0,200,160,.2); border-left: 3px solid #00c8a0;
  padding: 12px 16px; margin: 0 0 16px; font-size: .9rem; color: #b9c7d4; text-align: left;
}
.st-notice strong { color: #e8eaed; }
.st-notice.st-warn { border-color: rgba(245,166,35,.35); border-left-color: #f5a623; }

#filtered-content { position: static; top: 0; border-radius: 0; }
.main .content, .main .content *, .st-head * { border-radius: 0; }

#filtered-content .nfts {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(232px, 1fr));
  gap: 12px; align-items: stretch;
}
#filtered-content .nft { width: auto; float: none; font-size: 12px; }
#filtered-content .nft-data {
  margin: 0; min-height: 0; height: 100%; padding: 0; overflow: hidden;
  display: flex; flex-direction: column; text-align: left;
  background: #0a1929; border: 1px solid rgba(0,200,160,.14);
  transition: border-color .15s;
}
#filtered-content .nft-data:hover { border-color: rgba(0,200,160,.5); }

/* NOTHING IS CROPPED. Store photos are product shots at whatever aspect
   the creator uploaded, and .nft-image's fixed 170px window with
   overflow-y:hidden clipped every one that was not square. A 1/1 box with
   object-fit:contain keeps the grid even and shows the whole photo. */
#filtered-content .nft-image {
  display: block; float: none; margin: 0;
  min-height: 0; max-height: none; overflow: visible;
  aspect-ratio: 1 / 1; background: #07111d;
}
#filtered-content .nft-image img {
  width: 100%; height: 100%; max-width: none; max-height: none;
  object-fit: contain; display: block; border-radius: 0;
}
#filtered-content .nft-name {
  padding: 10px 12px 0; font-size: .86rem; color: #e8eaed; font-weight: bold;
}
/* SPECIFICITY, not just the property. `.nft span { display: block }` is
   0-1-1 and beat a plain `.st-meta` at 0-1-0, so these silently rendered
   as blocks and the project name ran straight into the quantity --
   "Sinder Skullz2 left". Any new span inside a .nft needs to out-specify
   that rule, which the #filtered-content prefix does. */
#filtered-content .st-meta {
  display: flex; justify-content: space-between; gap: 8px;
  padding: 6px 12px 0; font-size: .74rem; color: #7a9eb0;
}
#filtered-content .st-meta i { font-style: normal; opacity: .8; }
#filtered-content .st-meta i.st-low { color: #f5a623; opacity: 1; font-weight: bold; }

/* Buttons pinned to the bottom so every card's action sits on one line
   across a row, whatever the item name did to the height above it. */
.st-buy { margin-top: auto; padding: 10px 12px 12px; display: flex; flex-direction: column; gap: 6px; }
.st-buy .small-button { border-radius: 0; width: 100%; }
#filtered-content .st-claimed { color: #00c8a0; font-weight: bold; font-size: .78rem; }
#filtered-content .st-bal { display: flex; flex-wrap: wrap; gap: 4px 10px; margin-top: 2px; }
#filtered-content .st-bal i {
  font-style: normal; font-size: .7rem; color: #7a9eb0;
  display: inline-flex; align-items: center; gap: 3px;
}
#filtered-content .st-bal i img { height: 12px; width: auto; }

.st-owned .nft-data { border-color: rgba(0,200,160,.4); }
.st-owned .nft-image { opacity: .55; }

.st-empty {
  grid-column: 1 / -1; text-align: center; padding: 48px 20px;
  background: #0a1929; border: 1px solid rgba(0,200,160,.12);
}
.st-empty h3 { color: #e8eaed; margin: 0 0 8px; }
.st-empty p { color: #b9c7d4; max-width: 520px; margin: 0 auto; font-size: .94rem; }
@media (max-width: 720px) { .st-head { align-items: flex-start; } .st-head-right { width: 100%; } }
</style>

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