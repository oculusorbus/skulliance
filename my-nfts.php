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
      <div>
        <span class="mn-kick">Your collection</span>
        <h2 class="mn-title">Staked NFTs</h2>
      </div>
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

    <a name="holdings" id="holdings"></a>
    <div class="content" id="filtered-content">
      <?php filterNFTs("my-nfts", "", "get"); ?>

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
            <span class="nft-name"><?php echo htmlspecialchars($row["nfts_name"]); ?></span>
            <a href="<?php echo nftExplorerUrl($conn, $row["asset_id"], isset($row["blockchain_id"]) ? $row["blockchain_id"] : 1); ?>"
               target="_blank" rel="noopener"><?php
              echo renderIPFS($row["ipfs"], $row["collection_id"],
                   getIPFS($row["ipfs"], $row["collection_id"], $row["project_id"]), false, $row["nfts_id"]);
            ?></a>
            <?php /* The rate is the reason this page exists, so it is the
                     one fact given weight; project and collection are
                     context and are set below it. */ ?>
            <span class="nft-rate"><b><?php echo $row["rate"]; ?></b> <?php echo htmlspecialchars($row["currency"]); ?><i>/night</i></span>
            <span class="nft-level"><strong>Project</strong><br><?php echo htmlspecialchars($row["project_name"]); ?></span>
            <span class="nft-level"><strong>Collection</strong><br><?php echo htmlspecialchars($row["collection_name"]); ?></span>
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
   showcase.php's copy of the same class is unaffected because these rules
   are only on this page. */
.mn-head {
  display: flex; align-items: flex-end; justify-content: space-between;
  gap: 24px; flex-wrap: wrap; margin: 0 0 18px;
}
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
.mn-figures { display: flex; gap: 10px; flex-wrap: wrap; }
.mn-fig {
  background: #0a1929; border: 1px solid rgba(0,200,160,.14);
  border-radius: 10px; padding: 10px 16px; min-width: 104px; text-align: left;
}
.mn-fig b { display: block; font-size: 1.3rem; color: #00c8a0; line-height: 1.1; }
.mn-fig span { font-size: .68rem; color: #7a9eb0; letter-spacing: .04em; text-transform: uppercase; }

/*
 * THE CARD GRID. The platform gives .nft `width: 25%; float: left` -- a
 * hard four-column float layout from before this had a phone audience --
 * and .nft-data `text-align: right`, which is why every label hugs the
 * right edge. Both are overridden here rather than changed in
 * flexbox.css, because showcase.php renders the identical markup through
 * getNFTs() and must keep behaving exactly as it does. This CSS is inline
 * on this page, so it cannot reach showcase.php at all.
 */
#filtered-content .nfts {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(228px, 1fr));
  gap: 14px; align-items: stretch;
}
#filtered-content .nft { width: auto; float: none; font-size: 12px; }
#filtered-content .nft-data {
  margin: 0; min-height: 0; height: 100%;
  display: flex; flex-direction: column; text-align: left;
  border-radius: 14px; padding: 12px;
}
#filtered-content .nft-name { padding-top: 0; font-size: .9rem; }
#filtered-content .nft-data a { display: block; margin: 10px 0 0; }
#filtered-content .nft-data img { border-radius: 10px; width: 100%; height: auto; display: block; }
/* Pins the figures to the bottom of every card, so a long collection name
   cannot knock one card's numbers out of line with its neighbours. */
#filtered-content .nft-rate { margin-top: auto; }
#filtered-content .nft-level { color: #7a9eb0; margin-top: 6px; display: block; }
#filtered-content .nft-level strong { color: #9fb4c4; font-weight: 600; }
.nft-rate {
  display: block; margin: 10px 0 2px; font-size: .78rem; color: #7a9eb0;
}
.nft-rate b { color: #00c8a0; font-size: 1.05rem; }
.nft-rate i { font-style: normal; opacity: .7; }

.mn-empty {
  grid-column: 1 / -1; text-align: center; padding: 48px 20px;
  background: #0a1929; border: 1px solid rgba(0,200,160,.12); border-radius: 14px;
}
.mn-empty h3 { color: #e8eaed; margin: 0 0 8px; }
.mn-empty p { color: #b9c7d4; max-width: 520px; margin: 0 auto 14px; font-size: .94rem; }
.mn-btn {
  display: inline-block; margin: 4px 4px 0; padding: 10px 20px; border-radius: 999px;
  font-weight: 700; font-size: .9rem; text-decoration: none !important;
  background: linear-gradient(135deg,#00c8a0,#0596c4); color: #04121b !important;
}
.mn-btn.ghost { background: transparent; color: #00c8a0 !important; border: 1px solid rgba(0,200,160,.45); }
@media (max-width: 620px) { .mn-head { align-items: flex-start; } }
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
