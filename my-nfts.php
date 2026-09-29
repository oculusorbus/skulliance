<?php
/**
 * my-nfts.php — a staker's collection.
 *
 * FIRST PAGE MIGRATED OFF db.php's RENDERERS. It used to be 120 lines that
 * showed almost nothing you could edit: getNFTs() built the query AND
 * echoed the cards, so the design lived inside a 687KB data-access file.
 * It now calls getNFTsData(), which returns the same rows with the same
 * filters and no output, and owns its own markup and CSS below.
 *
 * getNFTs() is untouched. showcase.php and diamond-skulls.php still call
 * it and still render exactly as they did.
 *
 * EVERY JS HOOK THIS PAGE EVER EMITTED STILL EXISTS. Checked with
 * ui-selectors.php --check against a snapshot taken before the rewrite,
 * which flags any removed id or class that JavaScript anywhere in the repo
 * still reaches for -- including the ones built by concatenation, which a
 * grep cannot see. myModal, modal-header, modal-text, filtered-content,
 * nfts, row1, holdings, my-nfts and year are all still here, and the
 * .nft / .nft-data / .nft-name / .nft-level classes are kept because
 * skulliance.js's image auto-heal and upload affordance hang off them.
 */
include_once 'db.php';
include 'message.php';
// Verify includes Webhooks
include 'verify.php';
include 'skulliance.php';
include 'header.php';

$mn_user = isset($_SESSION['userData']['user_id']);
$mn_page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$mn_per  = 24;
$mn_total = $mn_user ? countNFTs($conn, $filterby) : 0;
$mn_pages = (int)ceil($mn_total / $mn_per);
if ($mn_page > $mn_pages && $mn_pages > 0) $mn_page = $mn_pages;
$mn_rows = $mn_user ? getNFTsData($conn, $filterby, "", $mn_page, $mn_per) : array();

/* The daily yield of what is on screen, which the page never said before.
   A collection view that cannot tell you what the collection earns is a
   list of pictures. */
$mn_rate = array();
foreach ($mn_rows as $r) {
	$c = $r['currency'];
	$mn_rate[$c] = (isset($mn_rate[$c]) ? $mn_rate[$c] : 0) + (float)$r['rate'];
}

/*
 * WHAT IS THE SAME ON EVERY CARD GETS SAID ONCE.
 *
 * The rate is per COLLECTION, not per NFT, and most people view this
 * filtered to one project -- so "2 CRYPT/night", "Project: Crypties",
 * "Collection: Crypties - Season 1" were repeating identically on all 24
 * cards. Four of five lines the same, twenty-four times: that is what made
 * the grid read as a database report instead of a collection. The art is
 * the only thing that differs, and it was the smallest part of the card.
 *
 * So each field is checked across the page. Uniform ones move to a single
 * context line under the title and come off the cards entirely; the cards
 * keep only what actually varies.
 */
$mn_same = array('project' => true, 'collection' => true, 'rate' => true);
if ($mn_rows) {
	$f = $mn_rows[0];
	foreach ($mn_rows as $r) {
		if ($r['project_name']    !== $f['project_name'])    $mn_same['project']    = false;
		if ($r['collection_name'] !== $f['collection_name']) $mn_same['collection'] = false;
		if ($r['rate'].$r['currency'] !== $f['rate'].$f['currency']) $mn_same['rate'] = false;
	}
	$mn_ctx = array();
	if ($mn_same['project'])    $mn_ctx[] = $f['project_name'];
	if ($mn_same['collection'] && $f['collection_name'] !== $f['project_name']) $mn_ctx[] = $f['collection_name'];
	if ($mn_same['rate'])       $mn_ctx[] = $f['rate'] . ' ' . $f['currency'] . ' per NFT, nightly';
} else {
	$mn_ctx = array();
}
?>

<!-- Modal -->
<div id="myModal" class="modal">
	<div class="modal-content">
	  <div class="modal-header">
	    <span class="close">&times;</span>
	    <h2 id="modal-header">IMPORTANT ANNOUNCEMENT</h2>
	  </div>
	  <div class="modal-body">
	    <p id="modal-text">
			Leaderboards have been updated to allow for stakers to view other staker's NFT collections by clicking on usernames from the leaderboards. You have a choice as to whether you want other stakers to view your NFT collection or not. Select 'Visible' to allow. Select 'Hidden' to reject. This setting can be changed at any time under the Wallets menu item. This message will continue to be displayed on this page until a selection is submitted.
			<?php renderVisibility("my-nfts"); ?>
		</p>
	  </div>
	  <div class="modal-footer"><h3></h3></div>
	</div>
</div>
<?php if (getVisibility($conn) == "0") { ?>
<script type="text/javascript">
 document.getElementById("myModal").style.display = "block";
 document.getElementById("visibility-button").className = "button";
</script>
<?php } ?>

<a name="my-nfts" id="my-nfts"></a>
<div class="row" id="row1">
  <div class="main">

    <?php /* A masthead, not an <h2>. The page opens with what the
             collection IS and what it earns, so the first thing a staker
             sees is a number rather than a heading. */ ?>
    <div class="mn-head">
      <div class="mn-head-left">
        <span class="mn-kick">Your collection</span>
        <h2 class="mn-title">Staked NFTs</h2>
        <?php if ($mn_ctx): ?>
        <?php /* Everything identical across the page, said once. */ ?>
        <p class="mn-ctx"><?php echo htmlspecialchars(implode(' · ', $mn_ctx)); ?></p>
        <?php endif; ?>
      </div>
      <div class="mn-head-right">
        <?php /* THE FILTER LIVES IN THE MASTHEAD NOW. #filter-nfts carries
                 `position:relative; top:-35px` so it could ride up beside
                 the old bare <h2>; against a taller masthead that -35px
                 dropped it straight on top of the figures and clipped
                 them. Neutralised in this page's CSS and placed on
                 purpose instead. */ ?>
        <?php filterNFTs("my-nfts", "", "get"); ?>
        <?php if ($mn_user && $mn_total > 0): ?>
        <div class="mn-figures">
          <div class="mn-fig"><b><?php echo number_format($mn_total); ?></b><span>Staking</span></div>
          <?php foreach (array_slice($mn_rate, 0, 2, true) as $cur => $amt): ?>
          <div class="mn-fig">
            <b><?php echo number_format($amt, ($amt < 10 ? 2 : 0)); ?></b>
            <span><?php echo htmlspecialchars($cur); ?> / night<?php echo $mn_pages > 1 ? ' (page)' : ''; ?></span>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <a name="holdings" id="holdings"></a>
    <div class="content" id="filtered-content">

      <div id="nfts" class="nfts">
      <?php if (!$mn_user): ?>
        <div class="mn-empty">
          <h3>Nothing staked yet</h3>
          <p>Connect a Cardano or XRPL wallet and your qualifying NFTs start earning the same night.
             Nothing leaves your wallet and there is no transaction to sign.</p>
          <p><a class="mn-btn" href="wallets.php">Connect a wallet</a>
             <a class="mn-btn ghost" href="info.php">How membership works</a></p>
        </div>
      <?php elseif (!$mn_rows): ?>
        <div class="mn-empty">
          <h3>No qualifying NFTs here</h3>
          <p>Nothing in your connected wallets matches a registered collection<?php
             echo ($filterby !== "" && $filterby !== "None") ? ' with this filter applied' : ''; ?>.</p>
          <p><a class="mn-btn ghost" href="collections.php">See what qualifies</a></p>
        </div>
      <?php else: foreach ($mn_rows as $row):
            /* .nft / .nft-data / .nft-name / .nft-level are KEPT, not
               restyled away: skulliance.js hangs the image auto-heal and
               the upload affordance off them, and renderIPFS() emits into
               them. The card is rebuilt around those hooks, not without
               them. */ ?>
        <div class="nft">
          <div class="nft-data">
            <a class="nft-art" href="<?php echo nftExplorerUrl($conn, $row["asset_id"], isset($row["blockchain_id"]) ? $row["blockchain_id"] : 1); ?>"
               target="_blank" rel="noopener"><?php
              echo renderIPFS($row["ipfs"], $row["collection_id"],
                   getIPFS($row["ipfs"], $row["collection_id"], $row["project_id"]), false, $row["nfts_id"]);
            ?></a>
            <span class="nft-name"><?php echo htmlspecialchars($row["nfts_name"]); ?></span>
            <?php /* Only what differs. When the whole page shares a rate,
                     a project or a collection, it is in the context line
                     above and repeating it here is 24 copies of one fact. */ ?>
            <?php if (!$mn_same['rate']): ?>
            <span class="nft-rate"><b><?php echo $row["rate"]; ?></b> <?php echo htmlspecialchars($row["currency"]); ?><i>/night</i></span>
            <?php endif; ?>
            <?php if (!$mn_same['project']): ?>
            <span class="nft-level"><?php echo htmlspecialchars($row["project_name"]); ?></span>
            <?php endif; ?>
            <?php if (!$mn_same['collection'] && $row["collection_name"] !== $row["project_name"]): ?>
            <span class="nft-level"><?php echo htmlspecialchars($row["collection_name"]); ?></span>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; endif; ?>
      </div>

      <?php if ($mn_pages > 1): $fb = urlencode($filterby); ?>
      <div class="nft-pagination">
        <?php if ($mn_page > 1): ?>
          <a class="page-btn" href="my-nfts.php?filterby=<?php echo $fb; ?>&page=<?php echo $mn_page-1; ?>">&#8592; Prev</a>
        <?php else: ?><span class="page-btn disabled">&#8592; Prev</span><?php endif; ?>
        <?php $prev = 0;
          for ($p = 1; $p <= $mn_pages; $p++):
            if ($p == 1 || $p == $mn_pages || ($p >= $mn_page - 2 && $p <= $mn_page + 2)):
              if ($prev && $p - $prev > 1) echo "<span class='page-ellipsis'>&#8230;</span>";
              if ($p == $mn_page): ?><span class="page-btn active"><?php echo $p; ?></span><?php
              else: ?><a class="page-btn" href="my-nfts.php?filterby=<?php echo $fb; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif;
              $prev = $p;
            endif;
          endfor; ?>
        <?php if ($mn_page < $mn_pages): ?>
          <a class="page-btn" href="my-nfts.php?filterby=<?php echo $fb; ?>&page=<?php echo $mn_page+1; ?>">Next &#8594;</a>
        <?php else: ?><span class="page-btn disabled">Next &#8594;</span><?php endif; ?>
        <div class="page-info">Page <?php echo $mn_page; ?> of <?php echo $mn_pages; ?> &nbsp;(<?php echo number_format($mn_total); ?> NFTs)</div>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<style>
/* Scoped to this page. Loaded after flexbox.css, so it refines the .nft
   card rather than replacing it -- the class stays, the look changes, and
   showcase.php's copy of the same markup is unaffected because these
   rules only exist on this page.

   SQUARE, NOT ROUNDED. DHC Fighters and the Arena have no rounded corners
   and they are the pages that read as modern; the 2.5rem radius on .nft
   and the 10px on .nft-data are the old platform's. Hard edges here, in
   one place, so the two halves of the site stop disagreeing. */

/* .main sets `text-align: center` for the whole column, which is why the
   kicker floated centred over a left-aligned title -- the h2 overrode it
   and the span did not. Alignment is stated here rather than inherited. */
.mn-head, .mn-head * { text-align: left; }
.mn-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 24px; flex-wrap: wrap; margin: 0 0 20px;
  border-bottom: 1px solid rgba(0,200,160,.14); padding-bottom: 16px;
}
.mn-head-left { min-width: 0; }
.mn-head-right { display: flex; align-items: flex-end; gap: 14px; flex-wrap: wrap; }
.mn-kick { display: block; font-size: .7rem; letter-spacing: .16em; text-transform: uppercase; color: #00c8a0; }
/* The platform styles every h2 uppercase and teal, which stacked two teal
   uppercase lines once the kicker was added above it. The kicker keeps the
   accent; the title takes the size and the plain colour, which is the same
   hierarchy the homepage layers use. */
.mn-title {
  margin: 2px 0 0; line-height: 1.1;
  font-size: clamp(1.5rem, 3vw, 2.1rem);
  text-transform: none; color: #e8eaed; letter-spacing: -.01em;
}
/* Everything identical across the page, stated once. */
.mn-ctx { margin: 8px 0 0; font-size: .82rem; color: #7a9eb0; }

.mn-figures { display: flex; gap: 8px; flex-wrap: wrap; }
.mn-fig {
  background: #0a1929; border: 1px solid rgba(0,200,160,.14);
  border-radius: 0; padding: 9px 15px; min-width: 96px; text-align: left;
}
.mn-fig b { display: block; font-size: 1.25rem; color: #00c8a0; line-height: 1.1; }
.mn-fig span { font-size: .64rem; color: #7a9eb0; letter-spacing: .05em; text-transform: uppercase; }

/*
 * NEGATIVE-OFFSET HACKS, NEUTRALISED. The platform claws back space with
 * `position: relative; top: -Npx` in at least eight places -- here
 * #filter-nfts is -35px and #filtered-content is -40px, both tuned to
 * close the gap under a bare <h2>. Give a page a taller masthead and
 * those offsets stop closing a gap and start landing content ON TOP of
 * something: the filter sat over the figures and clipped them, and the
 * content grid covered the context line.
 *
 * THIS IS THE FIRST THING TO CHECK ON EVERY REMAINING PAGE. Grep
 * `top: -` in flexbox.css before changing any page header; a layout that
 * depends on the header being exactly one line tall will break silently
 * and only at certain widths.
 */
/* .content carries border-radius:10px, which is the rounded panel still
   showing around the whole thing. Squared with everything else. */
#filtered-content { position: static; top: 0; border-radius: 0; }
/* Anything left over inside the page tree -- selects, the pagination, the
   platform's own panels. Scoped to this page, so nothing else moves. */
.main .content, .main .content *, .mn-head * { border-radius: 0; }
#filter-nfts { position: static; top: 0; text-align: left; font-size: 1rem; margin: 0; }
#filter-nfts label { display: block; font-size: .64rem; letter-spacing: .05em;
  text-transform: uppercase; color: #7a9eb0; margin-bottom: 4px; }
#filter-nfts label strong { font-weight: inherit; }
#filterNFTs { border-radius: 0; height: 38px; }

/*
 * THE CARD GRID. The platform gives .nft `width: 25%; float: left` -- a
 * hard four-column float layout from before there was a phone audience --
 * and .nft-data `text-align: right`, which is why every label hugged the
 * right edge. Both are overridden here rather than in flexbox.css,
 * because showcase.php renders the identical markup through getNFTs() and
 * must keep behaving exactly as it does.
 */
#filtered-content .nfts {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(190px, 1fr));
  gap: 12px; align-items: stretch;
}
#filtered-content .nft { width: auto; float: none; font-size: 12px; border-radius: 0; }
#filtered-content .nft-data {
  margin: 0; min-height: 0; height: 100%;
  display: flex; flex-direction: column; text-align: left;
  border-radius: 0; padding: 0; overflow: hidden;
  background: #0a1929; border: 1px solid rgba(0,200,160,.14);
  transition: border-color .15s;
}
#filtered-content .nft-data:hover { border-color: rgba(0,200,160,.5); }

/*
 * THE ART IS THE PRODUCT, so it leads and fills the card edge to edge --
 * it was a thumbnail under five lines of text.
 *
 * AND NOTHING IS CROPPED. The platform wraps every image in
 * `.nft-image { min-height:170px; max-height:170px; overflow-y:hidden }`
 * with `.nft img { max-height:165px }` -- a fixed 170px window that hard
 * -clips anything taller. Square art survived it; a tall piece lost its
 * top and bottom, and once the art became the point of the card that went
 * from a quirk to the whole problem.
 *
 * A SQUARE BOX WITH object-fit: contain, not natural heights. At 1,483
 * NFTs a ragged grid of mismatched card heights is hard to scan, and
 * `cover` would crop exactly what we are trying to stop cropping. contain
 * letterboxes a tall piece against the card and shows ALL of it. Same
 * call, for the same reason, as the callout screenshots on dhcgame.php.
 */
#filtered-content .nft-art { display: block; line-height: 0; }
#filtered-content .nft-image {
  display: block; float: none; margin: 0;
  min-height: 0; max-height: none; overflow: visible;
  aspect-ratio: 1 / 1; background: #07111d;
}
#filtered-content .nft-data img {
  width: 100%; height: 100%; max-width: none; max-height: none;
  object-fit: contain; display: block; border-radius: 0;
}
#filtered-content .nft-name {
  padding: 10px 12px 0; font-size: .82rem; color: #e8eaed;
  white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
}
/* Pinned to the bottom so a long collection name cannot knock one card's
   figures out of line with its neighbours. */
#filtered-content .nft-rate { margin-top: auto; padding: 8px 12px 0; }
#filtered-content .nft-rate b { color: #00c8a0; font-size: 1rem; }
#filtered-content .nft-rate i { font-style: normal; opacity: .7; }
#filtered-content .nft-level {
  color: #7a9eb0; display: block; padding: 4px 12px 0; font-size: .74rem;
}
#filtered-content .nft-data > :last-child { padding-bottom: 10px; }

.mn-empty {
  grid-column: 1 / -1; text-align: center; padding: 48px 20px;
  background: #0a1929; border: 1px solid rgba(0,200,160,.12); border-radius: 0;
}
.mn-empty h3 { color: #e8eaed; margin: 0 0 8px; }
.mn-empty p { color: #b9c7d4; max-width: 520px; margin: 0 auto 14px; font-size: .94rem; }
.mn-btn {
  display: inline-block; margin: 4px 4px 0; padding: 11px 22px; border-radius: 0;
  font-weight: 700; font-size: .9rem; text-decoration: none !important;
  background: linear-gradient(135deg,#00c8a0,#0596c4); color: #04121b !important;
}
.mn-btn.ghost { background: transparent; color: #00c8a0 !important; border: 1px solid rgba(0,200,160,.45); }

/* Pagination, squared to match. */
#filtered-content .page-btn { border-radius: 0; }
@media (max-width: 720px) {
  .mn-head { align-items: flex-start; }
  .mn-head-right { width: 100%; }
}
</style>

	<!-- Footer -->
	<div class="footer">
	  <p>Skulliance<br>Copyright © <span id="year"></span>
	</div>
</div>
</div>
</body>
<?php
if ($filterby != "") {
	echo "<script type='text/javascript'>document.getElementById('filterNFTs').value = '".$filterby."';</script>";
}
?>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php $conn->close(); ?>
</html>
