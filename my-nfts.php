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
/*
 * 27 a page: three full rows of nine.
 *
 * 24 left a three-card hole in the last row on a wide screen, because the
 * grid is auto-fill at minmax(190px, 1fr) and lands on nine columns there --
 * 9 + 9 + 6. 27 divides by nine exactly.
 *
 * It only divides evenly at nine columns (and three), so a narrower window
 * still ends on a partial row; no single number fills every width when the
 * column count follows the viewport. This one is chosen for the wide case,
 * which is where the gap was conspicuous.
 *
 * It is also the ceiling on what the save-artwork button attempts in one
 * run, which is why the endpoint's rate-limit reply is treated as a pause
 * rather than a failure.
 */
$mn_per  = 27;
/*
 * ?collection=N -- narrowing WITHIN the chosen project.
 *
 * Only meaningful once a project is picked, which is also the only time the
 * control is offered: the list of collections for "all projects" would be
 * every collection on the platform, which is the thing paging already is.
 *
 * VALIDATED AGAINST WHAT THIS STAKER ACTUALLY HOLDS, not just against the
 * collections table. An id typed into the address bar for a collection they
 * own nothing in would otherwise produce an empty grid with a filter chip
 * naming somebody else's collection.
 */
$mn_coll  = isset($_GET['collection']) ? (int)$_GET['collection'] : 0;
$mn_colls = array();
$mn_proj  = ($filterby !== "" && $filterby !== "None" && $filterby !== "core") ? (int)$filterby : 0;
if ($mn_user && $mn_proj > 0) {
	$uid = (int)$_SESSION['userData']['user_id'];
	$res = $conn->query(
		"SELECT c.id, c.name, COUNT(*) AS n
		   FROM nfts n
		   INNER JOIN collections c ON c.id = n.collection_id
		  WHERE n.user_id = $uid AND c.project_id = $mn_proj
		  GROUP BY c.id, c.name
		  ORDER BY c.name");
	if ($res) while ($r = $res->fetch_assoc()) $mn_colls[(int)$r['id']] = $r;
}
/* Anything not in that list is dropped rather than obeyed. */
if ($mn_coll > 0 && !isset($mn_colls[$mn_coll])) $mn_coll = 0;

$mn_total = $mn_user ? countNFTs($conn, $filterby, "", $mn_coll) : 0;
$mn_pages = (int)ceil($mn_total / $mn_per);
if ($mn_page > $mn_pages && $mn_pages > 0) $mn_page = $mn_pages;
$mn_rows = $mn_user ? getNFTsData($conn, $filterby, "", $mn_page, $mn_per, $mn_coll) : array();

/*
 * WHICH OF THESE HAVE A LOCAL COPY, settled before anything is drawn.
 *
 * getIPFS() globs the cache directory and returns a /staking/images/nfts/...
 * path when there is a copy on disk and a gateway URL when there is not, so
 * it already answers the question -- the resolved URL is kept per row so the
 * render loop does not glob a second time.
 *
 * It is a PRE-PASS rather than something the loop accumulates because the
 * offer to fetch the missing ones belongs ABOVE the grid, and a count the
 * loop is still filling in cannot be printed before it.
 */
$mn_imgs     = array();
$mn_uncached = array();
foreach ($mn_rows as $r) {
	$id  = (int)$r["nfts_id"];
	$url = getIPFS($r["ipfs"], $r["collection_id"], $r["project_id"]);
	$mn_imgs[$id] = $url;
	if (strpos($url, '/staking/images/nfts/') === 0) continue;   // already local
	/* An inline SVG data URI IS the picture -- there is no file to fetch and
	   cacheNFTImage() skips it, so counting it would put a number on the
	   button that can never come down. */
	if (strpos((string)$r["ipfs"], 'data:image') !== false) continue;
	$mn_uncached[] = $id;
}

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

        <?php
          /*
           * SAVE THE ART THAT IS NOT SAVED YET.
           *
           * An NFT whose picture only exists on IPFS loads slowly and depends
           * on a gateway still serving it. The nightly pass takes a copy of
           * everything it can, and it does not get everything -- a gateway
           * timing out at 3am is simply missed. This offers the ones on this
           * page, and only appears when there are some.
           *
           * STRICTLY ONE AT A TIME, and that is the whole design. This
           * endpoint already had a caller: images that failed to load called
           * it automatically, and when a lot of them failed at once the
           * parallel requests saturated PHP-FPM and locked the site, so that
           * path was cut back to showing a skull (see healNFT in
           * skulliance.js). What makes this safe where that was not is that
           * it is bounded by the page size, serial, and something a person
           * asked for -- never a cascade that fires on its own.
           */
          if ($mn_uncached):
        ?>
        <p class="mn-cache-wrap">
          <button type="button" class="mn-btn ghost" id="mn-cache-go"
                  data-ids="<?php echo htmlspecialchars(implode(',', $mn_uncached)); ?>">
            Save <?php echo count($mn_uncached); ?> artwork<?php
              echo count($mn_uncached) === 1 ? '' : 's'; ?> to Skulliance
          </button>
          <span class="mn-cache-note" id="mn-cache-note"><?php
            echo count($mn_uncached); ?> of <?php echo count($mn_rows); ?> on this page
            load from IPFS. Saving keeps a copy here, so they load faster and
            survive a gateway going away.</span>
        </p>
        <?php endif; ?>
      </div>
      <div class="mn-head-right">
        <?php /* THE FILTER LIVES IN THE MASTHEAD NOW. #filter-nfts carries
                 `position:relative; top:-35px` so it could ride up beside
                 the old bare <h2>; against a taller masthead that -35px
                 dropped it straight on top of the figures and clipped
                 them. Neutralised in this page's CSS and placed on
                 purpose instead. */ ?>
        <?php /* $filterby passed so the control shows what the page is
                 actually filtered to, rather than always reading
                 "Project" beside a collection dropdown that does. */ ?>
        <?php filterNFTs("my-nfts", "", "get", $filterby); ?>
        <?php
          /*
           * DRILL INTO ONE COLLECTION, once a project is chosen.
           *
           * Offered only then: the list for "all projects" is every
           * collection on the platform, which is the thing paging already
           * is. And only when there is more than one to choose between --
           * a project where the staker holds a single collection has
           * nothing to narrow, and a select with one option is furniture.
           *
           * Its own form rather than the shared one in filterNFTs(): that
           * one posts a single `filterby` and is used by three pages, and
           * widening it to carry a second field would change all of them
           * to serve this one. A GET form keeps the result linkable.
           */
          if (count($mn_colls) > 1):
        ?>
        <div id="mn-collfilter">
          <label for="mn-coll"><strong>Collection:</strong></label>
          <form method="get" action="my-nfts.php#holdings" id="mn-collform">
            <input type="hidden" name="filterby" value="<?php echo htmlspecialchars($filterby); ?>">
            <select name="collection" id="mn-coll" onchange="this.form.submit()">
              <option value="0">All <?php echo count($mn_colls); ?> collections</option>
              <?php foreach ($mn_colls as $cid => $c): ?>
                <option value="<?php echo (int)$cid; ?>" <?php
                  echo $mn_coll === (int)$cid ? 'selected' : ''; ?>><?php
                  echo htmlspecialchars($c['name']) . ' (' . (int)$c['n'] . ')'; ?></option>
              <?php endforeach; ?>
            </select>
            <noscript><input type="submit" value="Go"></noscript>
          </form>
        </div>
        <?php endif; ?>
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
            $mn_img = $mn_imgs[(int)$row["nfts_id"]];
            /* .nft / .nft-data / .nft-name / .nft-level are KEPT, not
               restyled away: skulliance.js hangs the image auto-heal and
               the upload affordance off them, and renderIPFS() emits into
               them. The card is rebuilt around those hooks, not without
               them. */ ?>
        <div class="nft">
          <div class="nft-data">
            <?php /* STILL AN ANCHOR TO THE EXPLORER. The panel takes over the
                     click, but the href stays real so middle-click, "open in
                     new tab" and a visitor with JavaScript off all still get
                     somewhere -- and the panel carries the same link on it. */ ?>
            <a class="nft-art" data-nft-open="<?php echo (int)$row["nfts_id"]; ?>"
               href="<?php echo nftExplorerUrl($conn, $row["asset_id"], isset($row["blockchain_id"]) ? $row["blockchain_id"] : 1); ?>"
               target="_blank" rel="noopener"><?php
              echo renderIPFS($row["ipfs"], $row["collection_id"], $mn_img, false, $row["nfts_id"]);
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

      <?php if ($mn_pages > 1): $fb = urlencode($filterby);
            /* The collection rides along, or page 2 silently widens back
               out to the whole project. */
            $cq = $mn_coll > 0 ? '&collection=' . $mn_coll : ''; ?>
      <div class="nft-pagination">
        <?php if ($mn_page > 1): ?>
          <a class="page-btn" href="my-nfts.php?filterby=<?php echo $fb . $cq; ?>&page=<?php echo $mn_page-1; ?>">&#8592; Prev</a>
        <?php else: ?><span class="page-btn disabled">&#8592; Prev</span><?php endif; ?>
        <?php $prev = 0;
          for ($p = 1; $p <= $mn_pages; $p++):
            if ($p == 1 || $p == $mn_pages || ($p >= $mn_page - 2 && $p <= $mn_page + 2)):
              if ($prev && $p - $prev > 1) echo "<span class='page-ellipsis'>&#8230;</span>";
              if ($p == $mn_page): ?><span class="page-btn active"><?php echo $p; ?></span><?php
              else: ?><a class="page-btn" href="my-nfts.php?filterby=<?php echo $fb . $cq; ?>&page=<?php echo $p; ?>"><?php echo $p; ?></a><?php endif;
              $prev = $p;
            endif;
          endfor; ?>
        <?php if ($mn_page < $mn_pages): ?>
          <a class="page-btn" href="my-nfts.php?filterby=<?php echo $fb . $cq; ?>&page=<?php echo $mn_page+1; ?>">Next &#8594;</a>
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
 * The collection drill-down. It sits beside #filterNFTs and must not look
 * like a different control -- which it did: left to the browser's defaults
 * it came out white with a blue focus ring next to a dark one.
 *
 * flexbox.css already had this exact note on #filterChainSel, which is the
 * chain filter on the Collections page and copies #filterNFTs property for
 * property for the same reason. Copied again here rather than shared,
 * because those two live in the platform stylesheet and this page owns its
 * own; the alternative is editing a global rule to serve one page.
 *
 * Wider than the 190px of #filterNFTs: a project is "Dead Pop Hell" and a
 * collection is a project name plus a tier plus a number.
 */
#mn-collfilter { text-align: left; font-size: 1rem; margin: 0; }
#mn-collfilter label { display: block; font-size: .64rem; letter-spacing: .05em;
  text-transform: uppercase; color: #7a9eb0; margin-bottom: 4px; }
#mn-collfilter label strong { font-weight: inherit; }
#mn-collfilter form { margin: 0; }
#mn-coll {
  width: 240px;
  max-width: 100%;
  border-radius: 0;
  /* 38px, not the platform's 30px: this page overrides #filterNFTs to 38
     and a 30px control beside it reads as misaligned. */
  height: 38px;
  font-weight: bold;
  background-color: #0d1e2e;
  color: #D6DDDE;
  text-align: left;
  font-size: 20px;
  border: 1px solid rgba(0,200,160,0.18);
  font-family: Arial, sans-serif;
}
@media (max-width: 700px) { #mn-coll { font-size: 14px; width: auto; } }
/* (.mn-head-right already wraps -- see above. A second rule saying so is a
   duplicate that reads as a dependency and is not one.) */

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
<?php if ($mn_uncached): ?>
<?php
  /*
   * A MODAL OF ITS OWN, not #myModal. That one is the visibility
   * announcement: it carries renderVisibility() inside it and is opened by
   * its own condition. Borrowing it would mean emptying somebody's consent
   * prompt to show a progress report.
   */
?>
<div id="mn-cache-veil" class="mn-cache-veil" role="dialog" aria-modal="true"
     aria-labelledby="mn-cache-title" hidden>
  <div class="mn-cache-sheet">
    <button type="button" class="mn-cache-x" id="mn-cache-close" aria-label="Close">&times;</button>
    <h3 id="mn-cache-title">Saving artwork</h3>
    <?php /* Always available. A run that cannot be called off is a run
             somebody has to close the tab to escape. */ ?>
    <button type="button" class="mn-btn ghost mn-cache-stop" id="mn-cache-stop" hidden>Stop</button>
    <p class="mn-cache-prog" id="mn-cache-prog"></p>
    <div class="mn-cache-bar"><i id="mn-cache-fill"></i></div>
    <div id="mn-cache-out"></div>
  </div>
</div>
<style>
.mn-cache-wrap{display:flex;flex-wrap:wrap;align-items:center;gap:8px 12px;margin:10px 0 0}
.mn-cache-note{font-size:11px;opacity:.6;line-height:1.5;max-width:46ch}
.mn-cache-veil{position:fixed;inset:0;z-index:9996;background:rgba(3,8,14,.86);
  display:flex;align-items:flex-start;justify-content:center;overflow:auto;
  padding:calc(env(safe-area-inset-top,0px) + 6vh) 16px calc(env(safe-area-inset-bottom,0px) + 20px)}
.mn-cache-veil[hidden]{display:none!important}
.mn-cache-sheet{position:relative;width:min(620px,100%);background:#0a1929;
  border:1px solid #1b3346;border-radius:6px;padding:20px 22px 22px;color:#e8eaed;
  font:13px/1.55 ui-monospace,Menlo,monospace}
.mn-cache-sheet h3{margin:0 0 10px;font-size:16px}
.mn-cache-x{position:absolute;top:8px;right:10px;background:none;border:0;color:#7a9eb0;
  font-size:24px;line-height:1;cursor:pointer;padding:4px 8px}
.mn-cache-x:hover{color:#e8eaed}
.mn-cache-prog{margin:0 0 8px;font-size:12px;color:#7a9eb0}
.mn-cache-stop{position:absolute;top:16px;right:46px;font-size:11px;padding:4px 12px}
.mn-cache-bar{height:4px;background:#07111d;border-radius:2px;overflow:hidden;margin:0 0 14px}
.mn-cache-bar i{display:block;height:100%;width:0;background:#00c8a0;transition:width .2s ease}
.mn-cache-sum{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 12px}
.mn-cache-sum span{border:1px solid #1b3346;border-radius:3px;padding:4px 9px;font-size:11px}
.mn-cache-sum b{font-variant-numeric:tabular-nums}
.mn-cache-sum .ok b{color:#00c8a0}
.mn-cache-sum .bad b{color:#e0466b}
.mn-cache-list{margin:0;padding:0;list-style:none;max-height:40vh;overflow:auto}
.mn-cache-list li{border-top:1px solid #1b3346;padding:6px 0;font-size:11.5px;display:flex;gap:8px}
.mn-cache-list li i{font-style:normal;flex:0 0 auto;width:62px;letter-spacing:.06em;
  text-transform:uppercase;font-size:9px;padding-top:3px;opacity:.7}
.mn-cache-list li.ok i{color:#00c8a0} .mn-cache-list li.bad i{color:#e0466b}
.mn-cache-why{opacity:.75;word-break:break-word}
@media (prefers-reduced-motion:reduce){.mn-cache-bar i{transition:none}}
</style>
<script>
(function () {
  var btn   = document.getElementById('mn-cache-go');
  var veil  = document.getElementById('mn-cache-veil');
  var out   = document.getElementById('mn-cache-out');
  var prog  = document.getElementById('mn-cache-prog');
  var fill  = document.getElementById('mn-cache-fill');
  var close = document.getElementById('mn-cache-close');
  var stopBtn = document.getElementById('mn-cache-stop');
  if (!btn || !veil) return;

  function esc(t) {
    return String(t == null ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
  }
  function shut() { veil.hidden = true; }
  close.addEventListener('click', shut);
  veil.addEventListener('click', function (e) { if (e.target === veil) shut(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !veil.hidden) shut();
  });

  /*
   * ONE REQUEST IN FLIGHT, ALWAYS.
   *
   * The endpoint this calls used to be driven automatically by every image
   * that failed to load, and a page where many failed at once fired them
   * together and saturated PHP-FPM until the site stopped answering. The
   * fix then was to stop calling it at all.
   *
   * It is called again here, but the shape is different in the ways that
   * caused that: the list is bounded by the page, the next request is not
   * started until the previous one has answered, and a person pressed a
   * button. Nothing recurses and nothing fans out.
   *
   * The endpoint also rate-limits 30 requests a minute per address, which a
   * page of 24 fits inside -- but a second run straight after will not, so
   * that reply is reported rather than swallowed.
   */
  btn.addEventListener('click', function () {
    var ids = (btn.getAttribute('data-ids') || '').split(',').filter(Boolean);
    if (!ids.length) return;

    btn.disabled = true;
    veil.hidden = false;
    out.innerHTML = '';
    fill.style.width = '0%';

    var done = 0;
    var tally = { cached: 0, exists: 0, skipped: 0, error: 0 };
    var rows = [];
    var stopped = false;
    var streak = 0;        // consecutive failures
    var retried = {};      // ids given one second chance after a busy reply

    /* Stopping is always available. A run that cannot be called off is a run
       somebody has to close the tab to escape. */
    stopBtn.hidden = false;
    stopBtn.onclick = function () { stopped = true; stopBtn.hidden = true; };

    function halt(label, why) {
      stopped = true;
      rows.push({ cls: 'bad', label: label, why: why });
      tally.error++;
      finish(why);
    }

    function finish(halted) {
      btn.disabled = false;
      stopBtn.hidden = true;
      var saved = tally.cached;
      prog.textContent = (halted ? 'Stopped. ' : (stopped ? 'Stopped. ' : 'Finished. '))
        + saved + ' of ' + ids.length + ' saved'
        + (done < ids.length ? ', ' + (ids.length - done) + ' not attempted' : '') + '.';
      var sum = '<div class="mn-cache-sum">'
        + '<span class="ok"><b>' + tally.cached + '</b> saved</span>'
        + (tally.exists  ? '<span><b>' + tally.exists + '</b> already had a copy</span>' : '')
        + (tally.skipped ? '<span><b>' + tally.skipped + '</b> nothing to save</span>' : '')
        + (tally.error   ? '<span class="bad"><b>' + tally.error + '</b> failed</span>' : '')
        + '</div>';
      /* Only the ones that did not work get a line. A list of 24 successes
         is noise; the reason a particular one failed is the useful part. */
      var bad = rows.filter(function (r) { return r.cls === 'bad'; });
      var body = bad.length
        ? '<ul class="mn-cache-list">' + bad.map(function (r) {
            return '<li class="' + r.cls + '"><i>' + esc(r.label) + '</i>'
                 + '<span class="mn-cache-why">' + esc(r.why) + '</span></li>';
          }).join('') + '</ul>'
        : '<p class="mn-cache-why" style="margin:0">Every one that could be saved, was.</p>';
      var again = tally.error
        ? '<p class="mn-cache-why" style="margin:12px 0 0">A gateway that is slow now is '
          + 'often fine later, and the nightly pass will try these again. Pressing the '
          + 'button again is safe.</p>'
        : '';
      out.innerHTML = sum + body + again;
      if (saved) {
        out.innerHTML += '<p class="mn-cache-why" style="margin:12px 0 0">'
          + 'Saved artwork is showing now and will load from Skulliance from here on.</p>';
      }
    }

    function step() {
      if (stopped) { finish(); return; }
      if (done >= ids.length) { finish(); return; }
      /*
       * THREE FAILURES IN A ROW AND IT STOPS. When a gateway is having a bad
       * minute every remaining NFT will fail the same way, and grinding
       * through two dozen of them helps nobody and costs the server two
       * dozen long requests.
       */
      if (streak >= 3) {
        rows.push({ cls: 'bad', label: 'Stopped',
                    why: 'Three in a row failed, so the rest were not attempted. '
                       + 'That usually means the gateways are having a bad moment.' });
        finish();
        return;
      }
      var id = ids[done];
      prog.textContent = 'Saving ' + (done + 1) + ' of ' + ids.length + '\u2026';

      var xhr = new XMLHttpRequest();
      xhr.open('POST', 'ajax/cache-nft-image.php', true);
      xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
      /*
       * LONGER THAN THE SERVER'S OWN CAP, deliberately and by a margin.
       *
       * At 25s against a server that could run past it, the browser gave up
       * on live work and started more -- which is how a serial loop
       * saturated PHP-FPM. The client must be the LAST thing to give up, so
       * that in normal operation the server always answers first and the
       * worker is freed before the next request goes out. A timeout here now
       * means something is genuinely wrong, which is why it halts the run.
       */
      xhr.timeout = 40000;

      function record(status, label, why, url) {
        if (tally[status] === undefined) tally.error++; else tally[status]++;
        var cls = (status === 'cached' || status === 'exists') ? 'ok' : 'bad';
        streak = (cls === 'ok') ? 0 : streak + 1;
        rows.push({ cls: cls, label: label, why: why });
        if (url) {
          /* Swap the picture in immediately -- the point of the exercise,
             visible without a reload. */
          var img = document.querySelector('img[data-nft-id="' + id + '"]');
          if (img) { img.onerror = null; img.src = url; }
        }
        done++;
        fill.style.width = Math.round(done / ids.length * 100) + '%';
        step();
      }

      function nameFor(nid) {
        var img = document.querySelector('img[data-nft-id="' + nid + '"]');
        var card = img && img.closest ? img.closest('.nft-data, .nft') : null;
        var nm = card ? card.querySelector('.nft-name') : null;
        return nm && nm.textContent ? nm.textContent.trim() : ('NFT ' + nid);
      }

      xhr.onload = function () {
        var d = null;
        try { d = JSON.parse(xhr.responseText); } catch (e) {}
        if (!d) {
          record('error', nameFor(id), 'The server replied with something unreadable.');
          return;
        }
        if (d.success) {
          record(d.status === 'exists' ? 'exists' : 'cached', nameFor(id), '', d.url);
          return;
        }
        /*
         * BUSY IS BACK-PRESSURE, NOT A FAILURE. The server caps how many of
         * these run at once across the whole platform; being turned away
         * means somebody else is caching, not that this image cannot be
         * saved. Treating it as a failure would burn the retry and, worse,
         * trip the three-strikes breaker on a perfectly healthy queue.
         *
         * It is also the one refusal that is instant, so waiting and
         * retrying costs nothing. Once -- then it goes back in the queue as
         * untried rather than being hammered.
         */
        /*
         * BUSY and RATE-LIMITED are both the server saying "not now", not
         * "never". Counting either as a failure burns the attempt and trips
         * the three-strikes breaker on a queue that is simply moving faster
         * than the limits allow -- which a page of 48 makes easy.
         *
         * The rate limit is a 60-second window, so backing off for a couple
         * of seconds is not enough to clear it; it waits longer.
         */
        if ((d.status === 'busy' || d.status === 'ratelimited') && !retried[id]) {
          retried[id] = true;
          var wait = d.status === 'ratelimited' ? 20000 : 2500;
          prog.textContent = (d.status === 'ratelimited'
            ? 'Going too fast \u2014 pausing '
            : 'Waiting for a free slot ') + (wait / 1000) + 's\u2026';
          setTimeout(step, wait);
          return;
        }
        record(d.status === 'skipped' ? 'skipped' : 'error', nameFor(id),
               d.message || 'Could not be saved.');
      };
      /*
       * A REQUEST THAT DID NOT ANSWER STOPS THE RUN. It does not advance it.
       *
       * This is the bug that mattered. record() moves to the next NFT, and
       * calling it from ontimeout meant the browser gave up on a request the
       * SERVER WAS STILL WORKING ON and immediately started another. Thirteen
       * timeouts left thirteen requests running at once -- the exact pile-up
       * that took the site down when broken images used to call this endpoint
       * by themselves, arrived at through the back door of my own timeout.
       *
       * Abandoning a request tells us nothing about whether it is finished.
       * The only safe reading is that work is still in flight, so the run
       * halts and says so rather than adding to it.
       */
      xhr.onerror   = function () { halt(nameFor(id), 'The request did not reach the server.'); };
      xhr.ontimeout = function () {
        halt(nameFor(id), 'The server did not answer in ' + (xhr.timeout / 1000)
           + 's. It may still be working on this one, so the rest were not started.');
      };
      xhr.send('nft_id=' + encodeURIComponent(id));
    }

    step();
  });
})();
</script>
<?php endif; ?>
<?php
  /*
   * THE NFT PANEL. A card used to go straight out to a block explorer,
   * which answers "does this exist on chain" and nothing about the thing a
   * staker actually owns it for -- what it is earning, what it is on, where
   * it is standing. All of that lives in the platform's own tables and was
   * not visible anywhere.
   *
   * It is also the only place the artwork is bigger than a thumbnail.
   */
?>
<div id="nftd-veil" class="nftd-veil" role="dialog" aria-modal="true"
     aria-labelledby="nftd-name" hidden>
  <div class="nftd-sheet">
    <button type="button" class="nftd-x" id="nftd-close" aria-label="Close">&times;</button>
    <div id="nftd-body"><p class="nftd-load">Loading&hellip;</p></div>
  </div>
</div>
<style>
.nftd-veil{position:fixed;inset:0;z-index:9996;background:rgba(3,8,14,.88);
  display:flex;align-items:flex-start;justify-content:center;overflow:auto;
  padding:calc(env(safe-area-inset-top,0px) + 4vh) 16px calc(env(safe-area-inset-bottom,0px) + 20px)}
.nftd-veil[hidden]{display:none!important}
.nftd-sheet{position:relative;width:min(880px,100%);background:#0a1929;border:1px solid #1b3346;
  border-radius:6px;padding:20px 22px 24px;color:#e8eaed;font:13px/1.55 ui-monospace,Menlo,monospace}
.nftd-x{position:absolute;top:8px;right:10px;background:none;border:0;color:#7a9eb0;
  font-size:26px;line-height:1;cursor:pointer;padding:4px 8px;z-index:2}
.nftd-x:hover{color:#e8eaed}
.nftd-load{color:#7a9eb0;margin:0}
/* THE ART IS THE POINT OF OPENING IT, so it leads and it is big. Capped by
   viewport height as well as width, or a tall piece pushes everything else
   off the screen. */
.nftd-top{display:flex;flex-wrap:wrap;gap:20px}
.nftd-art{flex:1 1 320px;min-width:0;display:flex;align-items:flex-start;justify-content:center}
.nftd-art img{max-width:100%;max-height:58vh;width:auto;height:auto;display:block;
  border-radius:4px;background:#07111d}
.nftd-side{flex:1 1 300px;min-width:0}
.nftd-side h2{margin:0 0 2px;font-size:18px;word-break:break-word}
.nftd-sub{font-size:11px;color:#7a9eb0;margin:0 0 14px}
.nftd-stats{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
.nftd-stats div{border:1px solid #1b3346;border-radius:3px;padding:6px 10px;min-width:78px}
.nftd-stats b{display:block;font-size:15px;font-variant-numeric:tabular-nums;color:#00c8a0}
.nftd-stats span{font-size:8.5px;letter-spacing:.1em;text-transform:uppercase;opacity:.55}
.nftd-sect{font-size:9.5px;letter-spacing:.14em;text-transform:uppercase;opacity:.5;margin:16px 0 8px}
.nftd-list{margin:0;padding:0;list-style:none}
.nftd-list li{border-top:1px solid #1b3346;padding:7px 0;display:flex;gap:10px;
  align-items:baseline;flex-wrap:wrap}
.nftd-list li b{font-weight:600}
.nftd-list .when{margin-left:auto;font-size:10.5px;opacity:.55;white-space:nowrap}
.nftd-idle{font-size:11.5px;opacity:.6;margin:0}
.nftd-links{display:flex;flex-wrap:wrap;gap:8px;margin:16px 0 0;padding-top:14px;
  border-top:1px solid #1b3346}
.nftd-links a{font-size:10px;letter-spacing:.08em;text-transform:uppercase;text-decoration:none;
  color:#7a9eb0;border:1px solid #1b3346;padding:5px 10px;border-radius:3px;text-align:center}
.nftd-links a:hover{border-color:#00c8a0;color:#00c8a0}
.nftd-flag{display:inline-block;font-size:9px;letter-spacing:.1em;text-transform:uppercase;
  border:1px solid currentColor;border-radius:3px;padding:2px 6px;margin-left:6px;vertical-align:2px}
.nftd-flag.on{color:#00c8a0} .nftd-flag.off{color:#e0a800}
@media (max-width:620px){ .nftd-sheet{padding:16px 14px 18px} .nftd-art img{max-height:42vh} }
</style>
<script>
(function () {
  var veil = document.getElementById('nftd-veil');
  var body = document.getElementById('nftd-body');
  if (!veil || !body) return;

  function esc(t) {
    return String(t == null ? '' : t)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
  }
  function shut() { veil.hidden = true; body.innerHTML = '<p class="nftd-load">Loading\u2026</p>'; }
  document.getElementById('nftd-close').addEventListener('click', shut);
  veil.addEventListener('click', function (e) { if (e.target === veil) shut(); });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && !veil.hidden) shut();
  });

  function plural(n, w) { return n + ' ' + w + (n === 1 ? '' : 's'); }

  function render(d) {
    var n = d.nft;
    var h = '<div class="nftd-top">';

    h += '<div class="nftd-art">'
       + '<img src="' + esc(d.image) + '" alt="' + esc(n.name) + '"'
       + ' onerror="this.onerror=null;this.src=\'/staking/icons/skull.png\'">'
       + '</div>';

    h += '<div class="nftd-side">'
       + '<h2 id="nftd-name">' + esc(n.name) + '</h2>'
       + '<p class="nftd-sub">' + esc(n.collection_name)
       + (n.project_name ? ' \u00b7 ' + esc(n.project_name) : '')
       + (d.mine ? '' : (n.username ? ' \u00b7 held by ' + esc(n.username) : ''))
       + '</p>';

    /* Older payloads have no raids key; treat that as "never fought" rather
       than letting every read below throw. */
    var r = d.raids || {};
    r.total = r.total || 0;

    /* What it earns and what it has done. */
    h += '<div class="nftd-stats">'
       + '<div><b>' + esc(n.rate) + '</b><span>' + esc(n.currency || 'pts') + ' / night</span></div>'
       + '<div><b>' + d.missions.length + '</b><span>On mission</span></div>'
       + '<div><b>' + d.missionsDone + '</b><span>Missions done</span></div>'
       + '<div><b>' + d.gauntlets.length + '</b><span>Gauntlet runs</span></div>'
       /*
        * RAIDS FOUGHT, NOT RAIDS WON, as the headline number. A Fighter that
        * turned out thirty times and lost twenty has done more than one that
        * won its only raid, and the record is right underneath. Only shown
        * once it has fought: a "0 raids" tile on an NFT that has never been
        * in a realm is a column of zeroes rather than information.
        */
       + (r.total
          ? '<div><b>' + r.total + '</b><span>Raid' + (r.total === 1 ? '' : 's')
            + ' fought</span></div>'
          : '')
       + '</div>';

    /* The record, in words, because W/L needs saying which is which when the
       same NFT both attacks and defends. */
    if (r.total) {
      var bits = [];
      if (r.won)  bits.push(r.won + ' won');
      if (r.lost) bits.push(r.lost + ' lost');
      if (r.attack && r.defend) bits.push(r.attack + ' attacking, ' + r.defend + ' defending');
      else if (r.attack) bits.push('always attacking');
      else if (r.defend) bits.push('always defending');
      if (r.fell) bits.push('fell ' + r.fell + (r.fell === 1 ? ' time' : ' times'));
      if (bits.length) h += '<p class="nftd-idle">' + esc(bits.join(' \u00b7 ')) + '</p>';
    }

    /* The artwork's home, which is the thing the save button is about. */
    h += '<p class="nftd-idle">Artwork '
       + (d.cached
          ? 'is saved on Skulliance<span class="nftd-flag on">saved</span>'
          : 'loads from IPFS<span class="nftd-flag off">not saved</span>')
       + '</p>';
    h += '</div></div>';

    /* ---- what it is doing right now ---- */
    h += '<p class="nftd-sect">Right now</p>';
    var busy = [];

    if (d.missions.length) {
      busy.push('<ul class="nftd-list">' + d.missions.map(function (m) {
        return '<li><b>' + esc(m.title || 'A mission') + '</b>'
             + (m.project_name ? '<span style="opacity:.6">' + esc(m.project_name) + '</span>' : '')
             + (m.reward ? '<span style="opacity:.6">' + esc(m.reward) + ' '
                         + esc(m.currency || '') + '</span>' : '')
             + '<span class="when">started ' + esc((m.created_date || '').slice(0, 16)) + '</span></li>';
      }).join('') + '</ul>');
    }
    if (d.soldier && String(d.soldier.active) === '1' && !d.soldier.dead) {
      busy.push('<ul class="nftd-list"><li><b>Standing in '
        + esc(d.soldier.realm_name || 'a realm') + '</b>'
        + '<span style="opacity:.6">' + (String(d.soldier.trained) === '1' ? 'trained' : 'untrained')
        + '</span>'
        + '<span class="when">defends against raids</span></li></ul>');
    }
    if (r.now) {
      /* Committed to a raid that has not resolved. The soldier line above
         says where it stands; this says it is not standing there right now. */
      busy.push('<ul class="nftd-list"><li><b>In a raid</b>'
        + '<span style="opacity:.6">' + r.now + ' unresolved</span>'
        + '<span class="when">away until it ends</span></li></ul>');
    }
    if (d.diamond) {
      busy.push('<ul class="nftd-list"><li><b>Part of a Diamond Skull</b>'
        + '<span style="opacity:.6">' + esc(d.diamond) + '</span></li></ul>');
    }

    h += busy.length ? busy.join('')
       : '<p class="nftd-idle">Not on a mission, not in a realm. It is earning its nightly '
         + 'rate and otherwise idle.</p>';

    /* ---- out to the chain and the rest of the platform ---- */
    h += '<div class="nftd-links">';
    if (d.explorer) h += '<a href="' + esc(d.explorer) + '" target="_blank" rel="noopener">On chain &rsaquo;</a>';
    if (n.marketplace_slug) {
      h += '<a href="https://xrp.cafe/collection/' + encodeURIComponent(n.marketplace_slug)
         + '" target="_blank" rel="noopener">Marketplace &rsaquo;</a>';
    }
    h += '<a href="collections.php">The collection &rsaquo;</a>';
    if (d.missions.length || d.missionsDone) h += '<a href="missions.php">Missions &rsaquo;</a>';
    if (d.soldier) h += '<a href="realms.php">Realms &rsaquo;</a>';
    if (r.total) h += '<a href="raids.php">Raids &rsaquo;</a>';
    h += '</div>';

    body.innerHTML = h;
  }

  /* ONE DELEGATED HANDLER. The grid is re-rendered by pagination, and a
     listener per card would have to be rebound with it. */
  document.addEventListener('click', function (e) {
    var a = e.target && e.target.closest ? e.target.closest('[data-nft-open]') : null;
    if (!a) return;
    /* Leave the explorer link working for anyone deliberately opening it
       elsewhere -- modified clicks and middle-click are not ours to take. */
    if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || e.button !== 0) return;
    e.preventDefault();

    var id = a.getAttribute('data-nft-open');
    veil.hidden = false;
    body.innerHTML = '<p class="nftd-load">Loading\u2026</p>';

    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'ajax/nft-dossier.php?nft_id=' + encodeURIComponent(id), true);
    xhr.timeout = 15000;
    xhr.onload = function () {
      var d = null;
      try { d = JSON.parse(xhr.responseText); } catch (err) {}
      if (!d) { body.innerHTML = '<p class="nftd-load">That came back unreadable.</p>'; return; }
      if (!d.success) {
        body.innerHTML = '<p class="nftd-load">' + esc(d.message || 'Could not load it.') + '</p>';
        return;
      }
      render(d);
    };
    xhr.onerror   = function () { body.innerHTML = '<p class="nftd-load">That request did not arrive.</p>'; };
    xhr.ontimeout = function () { body.innerHTML = '<p class="nftd-load">That took too long.</p>'; };
    xhr.send();
  });
})();
</script>
<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php $conn->close(); ?>
</html>
