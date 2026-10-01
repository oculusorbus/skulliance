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

/*
 * WHAT YOU HAVE ALREADY CLAIMED GOES TO THE BOTTOM.
 *
 * getItemsData() orders by featured, then project, then name -- which is
 * the right order for a catalogue and the wrong one for a shopper, because
 * it scatters the rows you can do nothing about through the ones you can.
 * A claimed card is dimmed and its buy buttons are disabled, so every one
 * of them sitting mid-list is a gap in the thing you are actually reading.
 *
 * A partition rather than a sort, so it does not depend on the sort being
 * stable. PHP's sorts ARE stable from 8.0 and a usort here would behave
 * identically -- measured -- but this platform has around twenty PHP
 * builds available and nothing pins which one serves the page, and on 7.x
 * an unstable sort would scatter the catalogue order inside each half.
 * Partitioning cannot do that: within each half the original order is
 * carried through untouched, so featured items still lead and projects
 * still group.
 *
 * Done here rather than in the SQL because 'owned' is not a column -- it
 * is resolved per viewer after the query, so ORDER BY cannot see it.
 */
if ($st_owned) {
	$st_open = array(); $st_done = array();
	foreach ($st_items as $it) {
		if (!empty($it['owned'])) $st_done[] = $it; else $st_open[] = $it;
	}
	$st_items = array_merge($st_open, $st_done);
	unset($st_open, $st_done);
}

/*
 * EDITING A LISTING AFTER IT IS SUBMITTED.
 *
 * A listing used to be frozen the moment it was created: a typo, a dead
 * image URL or a price agreed afterwards all meant opening phpMyAdmin.
 * This draws an Edit control on the cards the viewer may change and hands
 * it to ajax/item-edit.php.
 *
 * WHO SEES IT is storeItemEditRights()' answer, not this page's opinion --
 * user 1 on everything, a partner creator on the listings credited to a
 * project they own. Asking the same function the endpoint asks is what
 * stops the page drawing buttons the endpoint would refuse.
 *
 * NONE OF THIS DECIDES ANYTHING. It decides whether a button is drawn. The
 * endpoint re-checks the session itself, because a button nobody can see
 * is not a permission -- anyone can POST to the endpoint directly.
 */
$st_rights  = storeItemEditRights($conn);
$st_admin   = !empty($st_rights['super']);   // may edit every field
$st_mine    = $st_rights['projects'];        // project ids this viewer owns
$st_canedit = $st_admin || (bool)$st_mine;

/* A partner picks from THEIR projects only; user 1 picks from all. The
   endpoint enforces the same list -- this just keeps the form honest. */
$st_projects = array();
if ($st_canedit) {
	$st_projects = getProjects($conn);
	if (!is_array($st_projects)) $st_projects = array();
	if (!$st_admin) $st_projects = array_intersect_key($st_projects, $st_mine);
}
?>
		<a name="store" id="store"></a>
		<div class="row" id="row1">
    		<div class="main">

				<div class="st-head">
					<div class="st-head-left">
						<span class="st-kick">Staking store</span>
						<h2 class="st-title"><?php echo htmlspecialchars($st_title); ?></h2>
						<p class="st-ctx">Spend the points your NFTs earn.</p>
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
								<?php /* User 1 on anything; a partner only on their own project's
								         listings. Same test the endpoint applies to the STORED
								         project_id, so no drawn button can fail authorisation. */
								$st_editable = $st_canedit && ($st_admin || isset($st_mine[$pid])); ?>
								<?php if ($st_editable): ?>
								<?php /* Every value the form needs is already in $row, so the
								         card carries it and the modal opens with no round
								         trip. The raw name is stored -- including the literal
								         <br> some listings use -- so an edit that does not
								         touch the name writes back exactly what was there. */ ?>
								<button type="button" class="st-edit" title="Edit this listing"
									data-id="<?php echo (int)$row['item_id']; ?>"
									data-name="<?php echo htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8'); ?>"
									data-image="<?php echo htmlspecialchars($row['image_url'], ENT_QUOTES, 'UTF-8'); ?>"
									data-price="<?php echo (float)$row['price']; ?>"
									data-quantity="<?php echo (int)$row['quantity']; ?>"
									data-project="<?php echo (int)$row['project_id']; ?>"
									data-secondary="<?php echo (int)$row['secondary_project_id']; ?>"
									data-featured="<?php echo !empty($row['featured']) ? 1 : 0; ?>"
									onclick="openItemEdit(this)">Edit</button>
								<?php endif; ?>
								<span class="nft-image"><img loading="lazy"
									onError="this.src='/staking/icons/skull.png';"
									src="<?php echo htmlspecialchars($row['image_url']); ?>"
									style="cursor:zoom-in;"
									onclick="openStoreImageModal(this.src, this.closest('.nft-data').querySelector('.nft-name').textContent)"></span>
								<?php
								/* ITEM NAMES CONTAIN A DELIBERATE <br>. Several are written
								   as "Galaxy of Sons<br>Claimer's Choice (DM Oculus Orbus)"
								   so the qualifier sits on its own line. The old code echoed
								   the name raw, which rendered it -- and also rendered
								   anything else anyone typed. htmlspecialchars() alone fixed
								   that and printed a literal "<br>" on the card.
								   So: escape everything, then put back the one tag that is
								   meant to be there. Stricter than the original and it keeps
								   the line break. */
								/* double_encode FALSE: a name already containing an
								   entity would otherwise become &amp;#39; on screen. */
								$st_name = str_replace(
									array('&lt;br&gt;', '&lt;br/&gt;', '&lt;br /&gt;'), '<br>',
									htmlspecialchars($row['item_name'], ENT_QUOTES, 'UTF-8', false));
								?>
								<span class="nft-name"><?php echo $st_name; ?></span>

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
<?php if ($st_canedit): ?>
		<!-- Listing editor. Rendered only for someone with something to edit; the endpoint checks again. -->
		<div id="st-edit-modal" class="st-modal" style="display:none;">
			<?php /* A REAL FORM, pointed at the endpoint rather than at this page.
			         store.php's own handler creates an item whenever it sees a
			         POSTed `name`, so a form that fell back to a normal submit
			         would silently duplicate the listing it was meant to fix.
			         Posting to ajax/item-edit.php means the no-JS path still
			         performs the edit -- it just shows JSON instead of a page. */ ?>
			<form id="st-edit-form" class="st-modal-box" action="ajax/item-edit.php" method="post"
				onsubmit="return submitItemEdit(event);">
				<h3>Edit listing</h3>
				<input type="hidden" name="item_id" id="st-e-id">
				<label>Name <i>&lt;br&gt; makes a line break</i>
					<textarea name="name" id="st-e-name" rows="2"></textarea></label>
				<label>Image URL
					<input type="text" name="image_url" id="st-e-image"></label>
				<div class="st-e-preview"><img id="st-e-img" src="" alt=""
					onerror="this.src='/staking/icons/skull.png';"></div>
				<div class="st-e-row">
					<label>Price
						<input type="number" name="price" id="st-e-price" step="any" min="0"></label>
					<label>Quantity <i>-1 unlimited, 0 delists</i>
						<input type="number" name="quantity" id="st-e-qty" step="1" min="-1"></label>
				</div>
				<div class="st-e-row">
					<label>Project
						<select name="project_id" id="st-e-project">
							<?php foreach ($st_projects as $pid2 => $pr): ?>
							<option value="<?php echo (int)$pid2; ?>"><?php
								echo htmlspecialchars($pr['name'] . ' (' . $pr['currency'] . ')'); ?></option>
							<?php endforeach; ?>
						</select></label>
					<?php if ($st_admin): ?>
					<?php /* ADMIN ONLY, and not rendered rather than disabled. A second
					         currency makes a listing cost ANOTHER project's points, and
					         Featured is merchandising -- neither is on the submission
					         form, so a partner never set them. The endpoint OMITS both
					         from the UPDATE for a partner, so they survive untouched
					         when their owner fixes a typo. */ ?>
					<label>Second currency
						<select name="secondary_project_id" id="st-e-secondary">
							<option value="0">None</option>
							<?php foreach ($st_projects as $pid2 => $pr): ?>
							<option value="<?php echo (int)$pid2; ?>"><?php
								echo htmlspecialchars($pr['name'] . ' (' . $pr['currency'] . ')'); ?></option>
							<?php endforeach; ?>
						</select></label>
					<?php endif; ?>
				</div>
				<?php if ($st_admin): ?>
				<label class="st-e-check"><input type="checkbox" name="featured" id="st-e-featured" value="1">
					Featured <i>shows under the Exclusive filter and sorts first</i></label>
				<?php endif; ?>
				<p class="st-e-msg" id="st-e-msg"></p>
				<div class="st-e-actions">
					<button type="button" class="small-button" onclick="closeItemEdit()">Cancel</button>
					<button type="submit" class="small-button" id="st-e-save">Save changes</button>
				</div>
			</form>
		</div>
<?php endif; ?>
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

/* THE STORE HAS ITS OWN LAYOUT SYSTEM, and it is !important.
   `.store-nfts { display: flex !important }` beat the grid outright, and
   `.store-item { width: 33% }` lost to this file's `width: auto` -- so
   the result was flex-wrap with content-sized cards, which is why rows
   held five, then five, then six. Grid has to be declared !important to
   win, and then width:auto is correct rather than harmful. */
#filtered-content .nfts,
#filtered-content .nfts.store-nfts {
  display: grid !important;
  grid-template-columns: repeat(auto-fill, minmax(216px, 1fr));
  gap: 12px; align-items: stretch;
}
#filtered-content .nft,
#filtered-content .nft.store-item { width: auto; float: none; font-size: 12px; }
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
#filtered-content .nft-image,
#filtered-content .store-item .nft-image {
  display: block; float: none; margin: 0;
  /* `.store-item .nft-image { height: 180px }` would otherwise fight the
     aspect-ratio box and win on the cascade for height. */
  height: auto; min-height: 0; max-height: none; overflow: visible;
  aspect-ratio: 1 / 1; background: #07111d; border-radius: 0;
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

/* ---- admin edit (user 1 only; nothing below renders for anyone else) ---- */
#filtered-content .nft-data { position: relative; }
#filtered-content .st-edit {
  position: absolute; top: 6px; right: 6px; z-index: 2;
  font-size: .62rem; letter-spacing: .08em; text-transform: uppercase;
  padding: 4px 9px; border-radius: 0; cursor: pointer;
  background: rgba(7,17,29,.86); color: #00c8a0; border: 1px solid rgba(0,200,160,.45);
  /* pointer-events with the opacity, not just the opacity: an invisible
     button that still swallows clicks turns the top-right corner of every
     card into a trap where the image zoom should be. */
  opacity: 0; pointer-events: none; transition: opacity .12s;
}
/* Visible on hover on a pointer device, always visible on touch -- a
   control you cannot hover is a control you cannot find. */
#filtered-content .nft-data:hover .st-edit,
#filtered-content .st-edit:focus { opacity: 1; pointer-events: auto; }
@media (hover: none) { #filtered-content .st-edit { opacity: 1; pointer-events: auto; } }
#filtered-content .st-edit:hover { background: #00c8a0; color: #07111d; }

.st-modal {
  position: fixed; inset: 0; z-index: 1001; background: rgba(0,0,0,.85);
  align-items: flex-start; justify-content: center; overflow-y: auto; padding: 40px 16px;
}
.st-modal-box {
  background: #0a1929; border: 1px solid rgba(0,200,160,.3); border-radius: 0;
  padding: 22px; width: 100%; max-width: 520px; text-align: left;
  display: flex; flex-direction: column; gap: 12px;
}
.st-modal-box h3 { margin: 0; color: #e8eaed; text-transform: none; text-align: left; }
.st-modal-box label { display: block; font-size: .68rem; letter-spacing: .06em;
  text-transform: uppercase; color: #7a9eb0; text-align: left; }
.st-modal-box label i { font-style: normal; text-transform: none; letter-spacing: 0; opacity: .7; }
.st-modal-box input[type=text], .st-modal-box input[type=number],
.st-modal-box textarea, .st-modal-box select {
  display: block; width: 100%; box-sizing: border-box; margin-top: 4px;
  background: #07111d; color: #e8eaed; border: 1px solid rgba(0,200,160,.22);
  border-radius: 0; padding: 8px 10px; font-size: .86rem; font-family: inherit;
}
.st-e-row { display: flex; gap: 12px; flex-wrap: wrap; }
.st-e-row > label { flex: 1 1 180px; }
.st-e-check { display: flex !important; align-items: flex-start; gap: 8px; }
.st-e-check input { margin-top: 2px; }
.st-e-preview { background: #07111d; border: 1px solid rgba(0,200,160,.14);
  height: 150px; display: flex; align-items: center; justify-content: center; }
.st-e-preview img { max-width: 100%; max-height: 100%; object-fit: contain; }
.st-e-msg { margin: 0; font-size: .8rem; color: #f5a623; min-height: 1em; text-align: left; }
.st-e-msg.ok { color: #00c8a0; }
.st-e-actions { display: flex; gap: 10px; justify-content: flex-end; }
.st-e-actions .small-button { border-radius: 0; }
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
document.addEventListener('keydown', function(e){ if(e.key !== 'Escape') return;
	closeStoreImageModal(); if (typeof closeItemEdit === 'function') closeItemEdit(); });
</script>
<?php if ($st_canedit): ?>
<script type="text/javascript">
/* Listing editor. Every value comes off the card's own data-* set,
   so opening the form costs nothing and works with the page already
   rendered. The endpoint is the authority on whether any of it is allowed. */
function openItemEdit(btn){
	var d = btn.dataset;
	document.getElementById('st-e-id').value       = d.id;
	document.getElementById('st-e-name').value     = d.name;
	document.getElementById('st-e-image').value    = d.image;
	document.getElementById('st-e-img').src        = d.image;
	document.getElementById('st-e-price').value    = d.price;
	document.getElementById('st-e-qty').value      = d.quantity;
	document.getElementById('st-e-project').value  = d.project;
	/* Admin-only fields are not rendered for a partner, so guard rather than
	   assume. The endpoint leaves those columns alone for a partner, so
	   there is nothing here to populate and nothing to send. */
	var sec = document.getElementById('st-e-secondary');
	if (sec) sec.value = d.secondary;
	var feat = document.getElementById('st-e-featured');
	if (feat) feat.checked = d.featured === '1';
	setItemEditMsg('', false);
	document.getElementById('st-e-save').disabled = false;
	document.getElementById('st-edit-modal').style.display = 'flex';
}
function closeItemEdit(){
	var m = document.getElementById('st-edit-modal');
	if (m) m.style.display = 'none';
}
function setItemEditMsg(text, ok){
	var el = document.getElementById('st-e-msg');
	el.textContent = text;
	el.className = 'st-e-msg' + (ok ? ' ok' : '');
}
/* Live preview, so a pasted URL that 404s is obvious before saving
   rather than after it is on everyone's store page. */
document.getElementById('st-e-image').addEventListener('input', function(){
	document.getElementById('st-e-img').src = this.value || '/staking/icons/skull.png';
});
/* Clicking the backdrop closes; clicking the form must not. */
document.getElementById('st-edit-modal').addEventListener('click', function(e){
	if (e.target === this) closeItemEdit();
});
function submitItemEdit(e){
	e.preventDefault();
	var form = document.getElementById('st-edit-form');
	var save = document.getElementById('st-e-save');
	save.disabled = true;
	setItemEditMsg('Saving...', true);
	fetch('ajax/item-edit.php', { method: 'POST', body: new FormData(form),
			credentials: 'same-origin' })
		.then(function(r){ return r.json(); })
		.then(function(j){
			if (j && j.success) {
				setItemEditMsg('Saved. Reloading...', true);
				/* RELOAD RATHER THAN PATCH THE CARD. Price, currency options,
				   affordability and the featured-first ordering are all worked
				   out server-side, so repainting one card in place would leave
				   the rest of the page disagreeing with the database. */
				window.location.reload();
			} else {
				save.disabled = false;
				setItemEditMsg((j && j.message) || 'Save failed.', false);
			}
		})
		.catch(function(){
			save.disabled = false;
			setItemEditMsg('Could not reach the server.', false);
		});
	return false;
}
</script>
<?php endif; ?>
</html>