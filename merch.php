<?php
include_once 'db.php';
include_once 'webhooks.php';
include 'skulliance.php';

$user_id = intval($_SESSION['userData']['user_id']);

// ── Flash messages ───────────────────────────────────────────
if (!isset($_SESSION['merch_flash'])) $_SESSION['merch_flash'] = [];
function merchFlash($msg, $type = 'info') {
    $_SESSION['merch_flash'][] = ['msg' => $msg, 'type' => $type];
}

// ── POST handling (disconnect only — connect is now OAuth) ───
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'disconnect_printful') {
        $conn->query("DELETE FROM merch_accounts WHERE user_id = $user_id LIMIT 1");
        merchFlash('Printful account disconnected.', 'info');
        header('Location: merch.php');
        exit;
    }

    header('Location: merch.php');
    exit;
}

// ── Build OAuth authorization URL ───────────────────────────
$state_token = bin2hex(random_bytes(16));
$state       = $state_token . '.' . $user_id;
$_SESSION['merch_oauth_state'] = $state_token;
$oauth_url = 'https://www.printful.com/oauth/authorize?'
    . http_build_query([
        'client_id'     => PRINTFUL_CLIENT_ID,
        'redirect_url'  => PRINTFUL_REDIRECT_URI,
        'scope'         => 'orders products',
        'state'         => $state,
        'response_type' => 'code',
    ]);

header('X-Accel-Buffering: no');
include 'header.php';

// ── Load state ───────────────────────────────────────────────
$acct_res  = $conn->query("SELECT * FROM merch_accounts WHERE user_id = $user_id LIMIT 1");
$merch_acct = ($acct_res && $acct_res->num_rows) ? $acct_res->fetch_assoc() : null;

$active_tab    = $_GET['tab'] ?? 'nfts';
$filter_proj   = intval($_GET['project_id'] ?? 0);
$filter_coll   = intval($_GET['collection_id'] ?? 0);
$page          = max(1, intval($_GET['page'] ?? 1));
$per_page      = 24;
$offset        = ($page - 1) * $per_page;

// Licensed projects and collections for filter dropdowns
$filter_projects    = [];
$filter_collections = [];
if ($merch_acct) {
    $fp_res = $conn->query("
        SELECT DISTINCT projects.id, projects.name
        FROM projects
        INNER JOIN collections ON collections.project_id = projects.id
        INNER JOIN nfts        ON nfts.collection_id     = collections.id
        WHERE nfts.user_id = $user_id AND collections.merch_licensed = 1
        ORDER BY projects.name ASC
    ");
    if ($fp_res) while ($row = $fp_res->fetch_assoc()) $filter_projects[] = $row;

    $fc_where = $filter_proj ? "AND collections.project_id = $filter_proj" : '';
    $fc_res = $conn->query("
        SELECT DISTINCT collections.id, collections.name
        FROM collections
        INNER JOIN nfts ON nfts.collection_id = collections.id
        WHERE nfts.user_id = $user_id AND collections.merch_licensed = 1 $fc_where
        ORDER BY collections.name ASC
    ");
    if ($fc_res) while ($row = $fc_res->fetch_assoc()) $filter_collections[] = $row;
}

// Total count for pagination
$total_nfts = 0;
$eligible_nfts = [];
if ($merch_acct) {
    $where_extra = '';
    if ($filter_proj) $where_extra .= " AND collections.project_id = $filter_proj";
    if ($filter_coll) $where_extra .= " AND nfts.collection_id = $filter_coll";

    $count_res = $conn->query("
        SELECT COUNT(*) AS c FROM nfts
        INNER JOIN collections ON collections.id = nfts.collection_id
        WHERE nfts.user_id = $user_id AND collections.merch_licensed = 1 $where_extra
    ");
    if ($count_res) $total_nfts = intval($count_res->fetch_assoc()['c']);
    $total_pages = max(1, ceil($total_nfts / $per_page));
    if ($page > $total_pages) $page = $total_pages;

    $nft_res = $conn->query("
        SELECT nfts.id, nfts.name AS nft_name, nfts.ipfs, nfts.collection_id,
               collections.name AS collection_name, collections.project_id,
               projects.currency
        FROM nfts
        INNER JOIN collections ON collections.id = nfts.collection_id
        INNER JOIN projects    ON projects.id    = collections.project_id
        WHERE nfts.user_id = $user_id AND collections.merch_licensed = 1 $where_extra
        ORDER BY collections.name ASC, nfts.name ASC
        LIMIT $per_page OFFSET $offset
    ");
    if ($nft_res) while ($row = $nft_res->fetch_assoc()) $eligible_nfts[] = $row;
} else {
    $total_pages = 1;
}

// Active listings for this user
$listings = [];
$listings_res = $conn->query("
    SELECT mp.id, mp.nft_id, mp.printful_product_id, mp.status, mp.created_at,
           nfts.name AS nft_name, nfts.ipfs, nfts.collection_id,
           collections.name AS collection_name, collections.project_id,
           mpt.name AS product_type_name,
           GROUP_CONCAT(mps.store_type ORDER BY mps.store_type SEPARATOR ', ') AS stores
    FROM merch_products mp
    INNER JOIN nfts        ON nfts.id        = mp.nft_id
    INNER JOIN collections ON collections.id = nfts.collection_id
    LEFT JOIN  merch_product_types mpt ON mpt.id = mp.product_type_id
    LEFT JOIN  merch_product_stores mps ON mps.merch_product_id = mp.id
    WHERE mp.user_id = $user_id
    GROUP BY mp.id
    ORDER BY mp.created_at DESC
");
if ($listings_res) {
    while ($row = $listings_res->fetch_assoc()) $listings[] = $row;
}

// Product types (for fee info in UI)
$product_types = [];
$pt_res = $conn->query("SELECT * FROM merch_product_types WHERE active = 1 ORDER BY name ASC");
if ($pt_res) {
    while ($row = $pt_res->fetch_assoc()) $product_types[] = $row;
}

// Determine state
$state = $merch_acct ? 'connected' : 'no_account';

// Flashes
$flashes = $_SESSION['merch_flash'];
$_SESSION['merch_flash'] = [];

// Connected stores for display
$connected_stores = $merch_acct ? json_decode($merch_acct['connected_stores'], true) : [];
?>
<style>
/* ── Merchandise page styles ─────────────────────────── */
.merch-section         { max-width:960px; margin:0 auto; padding:20px 10px; }
.merch-connect-box     { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:10px; padding:30px; max-width:480px; margin:40px auto; text-align:center; }
.merch-connect-box h2  { margin-bottom:6px; }
.merch-connect-box p   { color:rgba(255,255,255,.55); font-size:.88rem; margin-bottom:20px; }
.merch-input           { width:100%; padding:10px 12px; border-radius:6px; border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.06); color:#e8eaed; font-size:.92rem; box-sizing:border-box; margin-bottom:14px; }
.merch-input:focus     { outline:none; border-color:#00c8a0; }
.merch-tabs            { display:flex; gap:4px; margin-bottom:20px; border-bottom:1px solid rgba(255,255,255,.08); }
.merch-tab             { padding:10px 20px; cursor:pointer; font-size:.88rem; color:rgba(255,255,255,.5); border-radius:6px 6px 0 0; transition:background .15s,color .15s; }
.merch-tab:hover       { background:rgba(255,255,255,.05); color:#e8eaed; }
.merch-tab.active      { background:rgba(0,200,160,.12); color:#00c8a0; border-bottom:2px solid #00c8a0; }
.merch-nft-grid        { display:grid; grid-template-columns:repeat(auto-fill,minmax(160px,1fr)); gap:14px; }
.merch-nft-card        { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:8px; overflow:hidden; cursor:pointer; transition:border-color .18s,transform .18s; }
.merch-nft-card:hover  { border-color:#00c8a0; transform:translateY(-2px); }
.merch-nft-card img    { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.merch-nft-card .card-label { padding:8px 10px; font-size:.78rem; }
.merch-nft-card .card-label strong { display:block; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.merch-nft-card .card-label span   { color:rgba(255,255,255,.45); font-size:.72rem; }
.merch-stores          { display:flex; gap:8px; flex-wrap:wrap; margin-bottom:16px; }
.merch-store-badge     { background:rgba(0,200,160,.12); color:#00c8a0; border:1px solid rgba(0,200,160,.25); border-radius:20px; padding:4px 12px; font-size:.78rem; }
.merch-listing-row     { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:8px; padding:14px 16px; margin-bottom:10px; display:flex; align-items:center; gap:14px; }
.merch-listing-row img { width:56px; height:56px; object-fit:cover; border-radius:6px; flex-shrink:0; }
.merch-listing-info    { flex:1; min-width:0; }
.merch-listing-info strong { display:block; }
.merch-listing-info span   { font-size:.78rem; color:rgba(255,255,255,.45); }
.merch-status-badge    { font-size:.72rem; padding:3px 9px; border-radius:12px; font-weight:600; letter-spacing:.03em; }
.merch-status-active   { background:rgba(0,200,160,.15); color:#00c8a0; }
.merch-status-pending  { background:rgba(255,190,0,.12); color:#ffbe00; }
.merch-status-archived { background:rgba(255,255,255,.07); color:rgba(255,255,255,.35); }
.merch-flash           { padding:10px 16px; border-radius:6px; margin-bottom:14px; font-size:.88rem; }
.merch-flash.success   { background:rgba(0,200,160,.12); color:#00c8a0; border:1px solid rgba(0,200,160,.25); }
.merch-flash.error     { background:rgba(220,50,50,.12); color:#f87171; border:1px solid rgba(220,50,50,.25); }
.merch-flash.info      { background:rgba(255,255,255,.06); color:#e8eaed; border:1px solid rgba(255,255,255,.1); }
.merch-acct-bar        { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:8px; padding:12px 16px; margin-bottom:20px; display:flex; align-items:center; gap:12px; flex-wrap:wrap; }
.merch-acct-bar .connected-dot { width:8px; height:8px; border-radius:50%; background:#00c8a0; flex-shrink:0; }
.merch-acct-bar .acct-label    { flex:1; font-size:.86rem; color:rgba(255,255,255,.65); }
/* Mockup modal */
#merch-modal-overlay   { position:fixed; inset:0; background:rgba(0,0,0,.65); z-index:800; display:none; }
#merch-modal           { position:fixed; top:50%; left:50%; transform:translate(-50%,-50%); background:#0f1b26; border:1px solid rgba(255,255,255,.1); border-radius:12px; padding:24px; width:min(680px,95vw); max-height:88vh; overflow-y:auto; z-index:801; display:none; flex-direction:column; gap:16px; }
#merch-modal h3        { margin:0; font-size:1.05rem; }
.merch-modal-close     { position:absolute; top:14px; right:16px; background:none; border:none; color:rgba(255,255,255,.5); font-size:1.3rem; cursor:pointer; line-height:1; padding:0; }
.merch-modal-close:hover { color:#e8eaed; }
.mockup-grid           { display:grid; grid-template-columns:repeat(auto-fill,minmax(140px,1fr)); gap:12px; }
.mockup-card           { background:rgba(255,255,255,.04); border:1px solid rgba(255,255,255,.08); border-radius:8px; overflow:hidden; }
.mockup-card img       { width:100%; aspect-ratio:1; object-fit:cover; display:block; }
.mockup-card .mkp-label { padding:6px 8px; font-size:.75rem; color:rgba(255,255,255,.55); }
.mockup-spinner        { text-align:center; padding:40px; color:rgba(255,255,255,.4); font-size:.88rem; }
.fee-notice            { background:rgba(255,190,0,.08); border:1px solid rgba(255,190,0,.2); border-radius:6px; padding:10px 14px; font-size:.84rem; color:#ffbe00; }
.product-type-select   { display:flex; flex-wrap:wrap; gap:8px; margin:4px 0 12px; }
.product-type-btn      { padding:7px 14px; border-radius:6px; border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.05); color:#e8eaed; cursor:pointer; font-size:.82rem; transition:background .15s,border-color .15s; }
.product-type-btn.selected { background:rgba(0,200,160,.15); border-color:#00c8a0; color:#00c8a0; }
.merch-filters         { display:flex; gap:10px; flex-wrap:wrap; margin-bottom:16px; align-items:center; }
.merch-select          { padding:7px 10px; border-radius:6px; border:1px solid rgba(255,255,255,.15); background:rgba(255,255,255,.06); color:#e8eaed; font-size:.84rem; cursor:pointer; }
.merch-select:focus    { outline:none; border-color:#00c8a0; }
.merch-pagination      { display:flex; gap:6px; justify-content:center; align-items:center; margin-top:20px; flex-wrap:wrap; }
.merch-page-btn        { padding:6px 12px; border-radius:6px; border:1px solid rgba(255,255,255,.12); background:rgba(255,255,255,.05); color:#e8eaed; font-size:.82rem; cursor:pointer; text-decoration:none; transition:background .15s,border-color .15s; }
.merch-page-btn:hover  { background:rgba(255,255,255,.1); }
.merch-page-btn.active { background:rgba(0,200,160,.15); border-color:#00c8a0; color:#00c8a0; cursor:default; }
.merch-page-btn.disabled { opacity:.35; pointer-events:none; }
</style>

<div class="row">
<div class="main">
<div class="merch-section">
  <h1 style="margin-bottom:6px;">&#127758; Merchandise</h1>
  <p style="color:rgba(255,255,255,.45);font-size:.88rem;margin-bottom:20px;">
    Submit your NFT art as official merch through your Printful account — sales go directly to you.
  </p>

  <?php foreach ($flashes as $f): ?>
    <div class="merch-flash <?php echo htmlspecialchars($f['type']); ?>"><?php echo htmlspecialchars($f['msg']); ?></div>
  <?php endforeach; ?>

  <?php if ($state === 'no_account'): ?>
  <!-- ── No account: OAuth connect ──────────────────────── -->
  <div class="merch-connect-box">
    <div style="font-size:2.5rem;margin-bottom:10px;">&#128279;</div>
    <h2>Connect Printful</h2>
    <p>Authorize Skulliance to access your Printful account. You will be redirected to Printful to approve the connection. Skulliance will create products on your behalf — all revenue goes directly to your Etsy or TikTok Shop.</p>
    <a href="<?php echo htmlspecialchars($oauth_url); ?>" class="small-button" style="display:block;width:100%;box-sizing:border-box;text-align:center;text-decoration:none;margin-top:4px;">Connect with Printful</a>
    <p style="font-size:.76rem;margin-top:16px;color:rgba(255,255,255,.3);">
      You will be redirected to Printful to authorize access.
    </p>
  </div>

  <?php else: ?>
  <!-- ── Connected account bar ──────────────────────────── -->
  <div class="merch-acct-bar">
    <div class="connected-dot"></div>
    <div class="acct-label">
      <strong>Printful Connected</strong>
      <?php if (!empty($connected_stores)): ?>
        &mdash; <?php echo count($connected_stores); ?> store(s):
        <?php foreach ($connected_stores as $s): ?>
          <span class="merch-store-badge"><?php echo htmlspecialchars($s['type'] ?? $s['name'] ?? 'Store'); ?></span>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
    <form method="post" action="merch.php" style="margin:0;">
      <input type="hidden" name="action" value="disconnect_printful">
      <button type="submit" class="small-button" style="background:rgba(220,50,50,.15);color:#f87171;border-color:rgba(220,50,50,.3);font-size:.76rem;padding:5px 12px;"
        onclick="return confirm('Disconnect Printful account?')">Disconnect</button>
    </form>
  </div>

  <!-- ── Tabs ───────────────────────────────────────────── -->
  <div class="merch-tabs">
    <div class="merch-tab <?php echo $active_tab === 'nfts' ? 'active' : ''; ?>"
         onclick="switchTab('nfts')">My NFTs</div>
    <div class="merch-tab <?php echo $active_tab === 'listings' ? 'active' : ''; ?>"
         onclick="switchTab('listings')">My Listings
      <?php $active_count = count(array_filter($listings, fn($l) => $l['status'] === 'active')); ?>
      <?php if ($active_count > 0): ?><span style="margin-left:5px;background:rgba(0,200,160,.2);color:#00c8a0;border-radius:10px;padding:1px 7px;font-size:.72rem;"><?php echo $active_count; ?></span><?php endif; ?>
    </div>
  </div>

  <!-- ── NFTs tab ───────────────────────────────────────── -->
  <div id="tab-nfts" style="display:<?php echo $active_tab === 'nfts' ? 'block' : 'none'; ?>">

    <?php if (!empty($filter_projects)): ?>
    <!-- Filters -->
    <form method="get" action="merch.php" class="merch-filters" id="merch-filter-form">
      <input type="hidden" name="tab" value="nfts">
      <select name="project_id" class="merch-select" onchange="this.form.submit()">
        <option value="0">All Projects</option>
        <?php foreach ($filter_projects as $fp): ?>
          <option value="<?php echo intval($fp['id']); ?>" <?php echo $filter_proj === intval($fp['id']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($fp['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <select name="collection_id" class="merch-select" onchange="this.form.submit()">
        <option value="0">All Collections</option>
        <?php foreach ($filter_collections as $fc): ?>
          <option value="<?php echo intval($fc['id']); ?>" <?php echo $filter_coll === intval($fc['id']) ? 'selected' : ''; ?>>
            <?php echo htmlspecialchars($fc['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
      <span style="font-size:.8rem;color:rgba(255,255,255,.35);"><?php echo $total_nfts; ?> NFT<?php echo $total_nfts !== 1 ? 's' : ''; ?></span>
    </form>
    <?php endif; ?>

    <?php if (empty($eligible_nfts)): ?>
      <div style="text-align:center;padding:40px;color:rgba(255,255,255,.35);font-size:.88rem;">
        No eligible NFTs found. Only NFTs from licensed collections can be used for merch.
      </div>
    <?php else: ?>
      <div class="merch-nft-grid">
        <?php foreach ($eligible_nfts as $nft):
          $img_url = getIPFS($nft['ipfs'], $nft['collection_id'], $nft['project_id']);
          if (str_starts_with($img_url, '/')) $img_url = 'https://skulliance.io' . $img_url;
          $already_listed = false;
          foreach ($listings as $l) {
              if (intval($l['nft_id']) === intval($nft['id']) && $l['status'] === 'active') { $already_listed = true; break; }
          }
        ?>
        <div class="merch-nft-card" onclick="openMerchModal(<?php echo intval($nft['id']); ?>, <?php echo intval($nft['collection_id']); ?>, <?php echo intval($nft['project_id']); ?>, '<?php echo htmlspecialchars(addslashes($nft['nft_name'])); ?>', '<?php echo htmlspecialchars(addslashes($nft['collection_name'])); ?>', '<?php echo htmlspecialchars(addslashes($nft['currency'])); ?>')">
          <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($nft['nft_name']); ?>" loading="lazy">
          <div class="card-label">
            <strong><?php echo htmlspecialchars($nft['nft_name']); ?></strong>
            <span><?php echo htmlspecialchars($nft['collection_name']); ?></span>
            <?php if ($already_listed): ?>
              <span style="color:#00c8a0;display:block;margin-top:2px;font-size:.7rem;">&#10003; Listed</span>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <?php if ($total_pages > 1):
        $base_url = 'merch.php?tab=nfts' . ($filter_proj ? '&project_id=' . $filter_proj : '') . ($filter_coll ? '&collection_id=' . $filter_coll : '');
      ?>
      <div class="merch-pagination">
        <a href="<?php echo $base_url . '&page=' . max(1, $page - 1); ?>" class="merch-page-btn <?php echo $page <= 1 ? 'disabled' : ''; ?>">&lsaquo; Prev</a>
        <?php
        $start = max(1, $page - 2);
        $end   = min($total_pages, $page + 2);
        if ($start > 1) echo '<span class="merch-page-btn disabled">&hellip;</span>';
        for ($i = $start; $i <= $end; $i++):
        ?>
          <a href="<?php echo $base_url . '&page=' . $i; ?>" class="merch-page-btn <?php echo $i === $page ? 'active' : ''; ?>"><?php echo $i; ?></a>
        <?php endfor;
        if ($end < $total_pages) echo '<span class="merch-page-btn disabled">&hellip;</span>';
        ?>
        <a href="<?php echo $base_url . '&page=' . min($total_pages, $page + 1); ?>" class="merch-page-btn <?php echo $page >= $total_pages ? 'disabled' : ''; ?>">Next &rsaquo;</a>
      </div>
      <?php endif; ?>

    <?php endif; ?>
  </div>

  <!-- ── Listings tab ──────────────────────────────────── -->
  <div id="tab-listings" style="display:<?php echo $active_tab === 'listings' ? 'block' : 'none'; ?>">
    <?php if (empty($listings)): ?>
      <div style="text-align:center;padding:40px;color:rgba(255,255,255,.35);font-size:.88rem;">
        No listings yet. Select an NFT from the NFTs tab to create your first listing.
      </div>
    <?php else: ?>
      <?php foreach ($listings as $listing):
        $img_url = getIPFS($listing['ipfs'], $listing['collection_id'], $listing['project_id']);
        if (str_starts_with($img_url, '/')) $img_url = 'https://skulliance.io' . $img_url;
        $status_class = 'merch-status-' . $listing['status'];
      ?>
      <div class="merch-listing-row">
        <img src="<?php echo htmlspecialchars($img_url); ?>" alt="<?php echo htmlspecialchars($listing['nft_name']); ?>" loading="lazy">
        <div class="merch-listing-info">
          <strong><?php echo htmlspecialchars($listing['nft_name']); ?><?php if (!empty($listing['product_type_name'])): ?> <span style="font-weight:400;color:rgba(255,255,255,.45);font-size:.82em;">&mdash; <?php echo htmlspecialchars($listing['product_type_name']); ?></span><?php endif; ?></strong>
          <span><?php echo htmlspecialchars($listing['collection_name']); ?></span>
          <?php if (!empty($listing['stores'])): ?>
            <span style="display:block;margin-top:2px;">Stores: <?php echo htmlspecialchars($listing['stores']); ?></span>
          <?php endif; ?>
          <span>Listed <?php echo date('M j, Y', strtotime($listing['created_at'])); ?></span>
        </div>
        <span class="merch-status-badge <?php echo $status_class; ?>"><?php echo ucfirst($listing['status']); ?></span>
        <?php if ($listing['status'] === 'active'): ?>
          <button class="small-button" style="background:rgba(220,50,50,.12);color:#f87171;border-color:rgba(220,50,50,.25);font-size:.76rem;padding:5px 12px;"
            onclick="archiveListing(<?php echo intval($listing['id']); ?>, this)">Archive</button>
        <?php elseif ($listing['status'] === 'archived'): ?>
          <button class="small-button" style="background:rgba(0,200,160,.1);color:#00c8a0;border-color:rgba(0,200,160,.25);font-size:.76rem;padding:5px 12px;"
            onclick="relistListing(<?php echo intval($listing['id']); ?>, this)">Relist</button>
          <button class="small-button" style="background:rgba(220,50,50,.08);color:#f87171;border-color:rgba(220,50,50,.2);font-size:.76rem;padding:5px 12px;"
            onclick="deleteListing(<?php echo intval($listing['id']); ?>, this)">Delete</button>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    <?php endif; ?>
  </div>

  <?php endif; ?>
</div><!-- .merch-section -->
</div><!-- .main -->
</div><!-- .row -->

<!-- ── Merch Submit Modal ───────────────────────────────────── -->
<div id="merch-modal-overlay" onclick="closeMerchModal()"></div>
<div id="merch-modal" role="dialog" aria-modal="true">
  <button class="merch-modal-close" onclick="closeMerchModal()">&times;</button>
  <h3 id="merch-modal-title">Submit Listing</h3>
  <div id="merch-modal-body">
    <!-- dynamically populated -->
  </div>
</div>

<script>
// Product types from PHP
var productTypes = <?php echo json_encode($product_types); ?>;

function switchTab(tab) {
    document.querySelectorAll('.merch-tab').forEach(function(t,i){ t.classList.toggle('active', ['nfts','listings'][i] === tab); });
    document.getElementById('tab-nfts').style.display      = tab === 'nfts'     ? 'block' : 'none';
    document.getElementById('tab-listings').style.display  = tab === 'listings' ? 'block' : 'none';
    history.replaceState(null, '', 'merch.php?tab=' + tab);
}

function openMerchModal(nftId, collectionId, projectId, nftName, collectionName, currency) {
    var modal   = document.getElementById('merch-modal');
    var overlay = document.getElementById('merch-modal-overlay');
    var body    = document.getElementById('merch-modal-body');
    var title   = document.getElementById('merch-modal-title');

    title.textContent = nftName + ' — Submit Listing';
    body.innerHTML    = '<div class="mockup-spinner">&#8635; Generating mockups&hellip;</div>';
    modal.style.display   = 'flex';
    overlay.style.display = 'block';

    // Fetch mockups
    fetch('ajax/merch-mockups.php?nft_id=' + nftId)
        .then(function(r){ return r.json(); })
        .then(function(data) {
            if (!data.success) {
                body.innerHTML = '<div class="mockup-spinner" style="color:#f87171;">' + (data.error || 'Error loading mockups.') + '</div>';
                return;
            }
            renderMerchModal(body, data, nftId, collectionId, projectId, nftName, collectionName, currency);
        })
        .catch(function(e) {
            body.innerHTML = '<div class="mockup-spinner" style="color:#f87171;">Failed to load mockups. Please try again.</div>';
        });
}

function renderMerchModal(body, data, nftId, collectionId, projectId, nftName, collectionName, currency) {
    var html = '';

    // Mockup previews
    if (data.mockups && data.mockups.length > 0) {
        html += '<div>';
        html += '<strong style="font-size:.85rem;color:rgba(255,255,255,.55);">MOCKUP PREVIEWS</strong>';
        html += '<div class="mockup-grid" style="margin-top:8px;">';
        data.mockups.forEach(function(m) {
            html += '<div class="mockup-card">';
            html += '<img src="' + escHtml(m.url) + '" alt="' + escHtml(m.product_name) + '" loading="lazy">';
            html += '<div class="mkp-label">' + escHtml(m.product_name) + '</div>';
            html += '</div>';
        });
        html += '</div></div>';
    } else {
        html += '<p style="color:rgba(255,255,255,.4);font-size:.84rem;">Mockup generation is in progress — previews will be available shortly after submission.</p>';
    }

    // Product type selection
    html += '<div>';
    html += '<strong style="font-size:.85rem;color:rgba(255,255,255,.55);">SELECT PRODUCT TYPES</strong>';
    html += '<div class="product-type-select" id="product-type-select" style="margin-top:8px;">';
    if (productTypes.length > 0) {
        productTypes.forEach(function(pt) {
            html += '<button class="product-type-btn" data-id="' + pt.id + '" onclick="toggleProductType(this)">' + escHtml(pt.name) + ' ($' + parseFloat(pt.base_price).toFixed(2) + '+)</button>';
        });
    } else {
        html += '<span style="color:rgba(255,255,255,.35);font-size:.82rem;">No product types configured.</span>';
    }
    html += '</div></div>';

    // Fee notice
    html += '<div class="fee-notice" id="merch-fee-notice">';
    html += '&#9888; Listing fee: <strong id="merch-fee-amt">—</strong> ' + escHtml(currency) + '. Select products above to see total.';
    html += '</div>';

    // Stores
    if (data.stores && data.stores.length > 0) {
        html += '<div>';
        html += '<strong style="font-size:.85rem;color:rgba(255,255,255,.55);">PUBLISH TO STORES</strong>';
        html += '<div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;">';
        data.stores.forEach(function(s) {
            var storeId   = s.id;
            var storeType = (s.type || 'store').toLowerCase();
            var storeName = s.name || storeType;
            html += '<label style="display:flex;align-items:center;gap:6px;font-size:.84rem;cursor:pointer;">';
            html += '<input type="checkbox" class="merch-store-check" data-store-id="' + storeId + '" data-store-type="' + escHtml(storeType) + '" checked> ';
            html += escHtml(storeName);
            html += '</label>';
        });
        html += '</div></div>';
    }

    // Submit button
    html += '<button class="small-button" style="width:100%;margin-top:4px;" id="merch-submit-btn"';
    html += ' onclick="submitMerchListing(' + nftId + ', ' + projectId + ', \'' + escHtml(nftName) + '\', \'' + escHtml(collectionName) + '\', \'' + escHtml(currency) + '\')">';
    html += 'Submit Listing</button>';

    body.innerHTML = html;

    // Populate fee data for JS
    body._feeData = data.fees || {};
}

function toggleProductType(btn) {
    btn.classList.toggle('selected');
    updateFeeDisplay();
}

function updateFeeDisplay() {
    var selected = document.querySelectorAll('#product-type-select .product-type-btn.selected');
    var feeNotice = document.getElementById('merch-fee-amt');
    if (!feeNotice) return;
    var body = document.getElementById('merch-modal-body');
    var feeData = body._feeData || {};
    var total = 0;
    selected.forEach(function(b) {
        var id = b.getAttribute('data-id');
        total += (feeData[id] || 0);
    });
    feeNotice.textContent = selected.length > 0 ? total.toFixed(0) + ' per selected product' : '—';
}

function submitMerchListing(nftId, projectId, nftName, collectionName, currency) {
    var selectedTypes = [];
    document.querySelectorAll('#product-type-select .product-type-btn.selected').forEach(function(b) {
        selectedTypes.push(b.getAttribute('data-id'));
    });
    if (selectedTypes.length === 0) {
        openNotify('Please select at least one product type.');
        return;
    }

    var selectedStores = [];
    document.querySelectorAll('.merch-store-check:checked').forEach(function(c) {
        selectedStores.push({ store_id: c.getAttribute('data-store-id'), store_type: c.getAttribute('data-store-type') });
    });
    if (selectedStores.length === 0) {
        openNotify('Please select at least one store to publish to.');
        return;
    }

    var feeNotice = document.getElementById('merch-fee-amt');
    var feeText   = feeNotice ? feeNotice.textContent : '';
    openConfirm(
        'Submit ' + selectedTypes.length + ' product listing(s) for "' + nftName + '"?\n\nFee: ' + feeText + ' ' + currency + ' will be deducted from your balance.',
        function() {
            var btn = document.getElementById('merch-submit-btn');
            if (btn) { btn.disabled = true; btn.textContent = 'Submitting…'; }

            var body = new URLSearchParams();
            body.append('nft_id',     nftId);
            body.append('project_id', projectId);
            selectedTypes.forEach(function(t) { body.append('product_type_ids[]', t); });
            body.append('stores', JSON.stringify(selectedStores));

            fetch('ajax/merch-submit.php', { method:'POST', body: body })
                .then(function(r){ return r.json(); })
                .then(function(data) {
                    if (data.success) {
                        closeMerchModal();
                        openNotify('Listing submitted! Your products are being created on Printful. Check the Listings tab for status.');
                        setTimeout(function(){ location.reload(); }, 1800);
                    } else {
                        if (btn) { btn.disabled = false; btn.textContent = 'Submit Listing'; }
                        openNotify(data.error || 'Submission failed. Please try again.');
                    }
                })
                .catch(function() {
                    if (btn) { btn.disabled = false; btn.textContent = 'Submit Listing'; }
                    openNotify('Network error. Please try again.');
                });
        }
    );
}

function relistListing(listingId, btn) {
    openConfirm('Relist this product on Printful? No fee will be charged.', function() {
        btn.disabled    = true;
        btn.textContent = 'Relisting…';
        var body = new URLSearchParams();
        body.append('listing_id', listingId);
        fetch('ajax/merch-relist.php', { method:'POST', body: body })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.success) {
                    openNotify('Listing relisted successfully!');
                    setTimeout(function(){ location.reload(); }, 1200);
                } else {
                    btn.disabled    = false;
                    btn.textContent = 'Relist';
                    openNotify(data.error || 'Could not relist.');
                }
            })
            .catch(function() {
                btn.disabled    = false;
                btn.textContent = 'Relist';
                openNotify('Network error. Please try again.');
            });
    });
}

function deleteListing(listingId, btn) {
    openConfirm('Permanently delete this listing? This cannot be undone.', function() {
        btn.disabled    = true;
        btn.textContent = 'Deleting…';
        var body = new URLSearchParams();
        body.append('listing_id', listingId);
        fetch('ajax/merch-delete.php', { method:'POST', body: body })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.success) {
                    openNotify('Listing deleted.');
                    setTimeout(function(){ location.reload(); }, 1200);
                } else {
                    btn.disabled    = false;
                    btn.textContent = 'Delete';
                    openNotify(data.error || 'Could not delete listing.');
                }
            })
            .catch(function() {
                btn.disabled    = false;
                btn.textContent = 'Delete';
                openNotify('Network error. Please try again.');
            });
    });
}

function archiveListing(listingId, btn) {
    openConfirm('Archive this listing? The product will be removed from your Printful store.', function() {
        btn.disabled    = true;
        btn.textContent = 'Archiving…';
        var body = new URLSearchParams();
        body.append('listing_id', listingId);
        fetch('ajax/merch-archive.php', { method:'POST', body: body })
            .then(function(r){ return r.json(); })
            .then(function(data) {
                if (data.success) {
                    openNotify('Listing archived.');
                    setTimeout(function(){ location.reload(); }, 1200);
                } else {
                    btn.disabled    = false;
                    btn.textContent = 'Archive';
                    openNotify(data.error || 'Could not archive listing.');
                }
            })
            .catch(function() {
                btn.disabled    = false;
                btn.textContent = 'Archive';
                openNotify('Network error. Please try again.');
            });
    });
}

function closeMerchModal() {
    document.getElementById('merch-modal').style.display   = 'none';
    document.getElementById('merch-modal-overlay').style.display = 'none';
}

function escHtml(str) {
    if (!str) return '';
    return String(str)
        .replace(/&/g,'&amp;')
        .replace(/</g,'&lt;')
        .replace(/>/g,'&gt;')
        .replace(/"/g,'&quot;');
}
</script>

<div class="footer">
	<p>Skulliance<br>Copyright &copy; <span id="year"></span></p>
</div>
<script>document.getElementById('year') && (document.getElementById('year').textContent = new Date().getFullYear());</script>
</div>
</div>
</body>
<?php $conn->close(); ?>
