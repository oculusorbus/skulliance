<?php
include 'db.php';
include 'message.php';
include 'verify.php';
include 'skulliance.php';
include 'header.php';

$raffles = getActiveRaffles($conn);
$all_projects_res = $conn->query("SELECT id, name, currency, divider FROM projects ORDER BY id ASC");
$all_projects = array();
if ($all_projects_res) { while ($r = $all_projects_res->fetch_assoc()) $all_projects[] = $r; }
$now_ts = time();
?>
<style>
.raffles-grid { display:grid; grid-template-columns: repeat(auto-fill, minmax(280px,1fr)); gap:18px; margin-top:16px; }
.raffle-card { background:#0d1f2d; border:1px solid rgba(0,200,160,0.15); border-radius:10px; overflow:hidden; display:flex; flex-direction:column; }
.raffle-card-img { width:100%; aspect-ratio:1/1; object-fit:cover; background:#111; }
.raffle-card-img-placeholder { width:100%; aspect-ratio:1/1; background:#0a1a26; display:flex; align-items:center; justify-content:center; color:rgba(255,255,255,0.2); font-size:2.5rem; }
.raffle-card-body { padding:14px; flex:1; display:flex; flex-direction:column; gap:8px; }
.raffle-card-title { font-weight:bold; font-size:0.95rem; color:#e8eef4; }
.raffle-card-desc { font-size:0.78rem; opacity:0.55; line-height:1.4; flex:1; }
.raffle-stat-row { display:flex; align-items:center; justify-content:space-between; font-size:0.82rem; }
.raffle-stat-label { opacity:0.5; }
.raffle-stat-value { font-weight:bold; color:#a040ff; }
.raffle-timer { font-size:0.75rem; opacity:0.5; }
.raffle-upcoming-badge { font-size:0.72rem; background:rgba(255,200,0,0.12); border:1px solid rgba(255,200,0,0.25); border-radius:4px; padding:2px 7px; color:#ffc800; display:inline-block; margin-bottom:4px; }
.raffle-card-footer { padding:10px 14px; border-top:1px solid rgba(255,255,255,0.05); }
.raffle-empty { opacity:0.5; text-align:center; padding:40px 0; }

.raffle-modal { display:none; position:fixed; inset:0; z-index:800; align-items:center; justify-content:center; }
.raffle-modal.open { display:flex; }
.raffle-modal-overlay { position:absolute; inset:0; background:rgba(0,0,0,0.75); }
.raffle-modal-box { position:relative; z-index:1; background:#0d1f2d; border:1px solid rgba(0,200,160,0.2); border-radius:12px; width:min(520px,95vw); max-height:90vh; overflow-y:auto; padding:24px; display:flex; flex-direction:column; gap:16px; }
.raffle-modal-title { font-size:1.1rem; font-weight:bold; color:#e8eef4; }
.raffle-modal-close { position:absolute; top:14px; right:16px; background:none; border:none; color:rgba(255,255,255,0.5); font-size:1.4rem; cursor:pointer; }
.raffle-modal-close:hover { color:#fff; }
.form-row { display:flex; flex-direction:column; gap:5px; }
.form-row label { font-size:0.78rem; opacity:0.6; }
.form-row input, .form-row textarea, .form-row select { background:#0a1520; border:1px solid rgba(255,255,255,0.1); border-radius:6px; color:#e8eef4; padding:8px 10px; font-size:0.85rem; width:100%; box-sizing:border-box; }
.form-row textarea { resize:vertical; min-height:70px; }
.form-row input:focus, .form-row textarea:focus, .form-row select:focus { outline:none; border-color:rgba(0,200,160,0.4); }
.form-section-label { font-size:0.72rem; text-transform:uppercase; letter-spacing:0.05em; opacity:0.4; margin-top:4px; }
.currency-rows { display:flex; flex-direction:column; gap:6px; }
.currency-row { display:flex; gap:8px; align-items:center; }
.currency-row select { flex:2; }
.currency-row input { flex:1; }
.currency-row .rm-btn { flex-shrink:0; background:rgba(200,50,50,0.15); border:1px solid rgba(200,50,50,0.3); border-radius:5px; color:#ff6b6b; cursor:pointer; padding:6px 10px; font-size:0.78rem; white-space:nowrap; }
.ticket-modal-box { width:min(420px,95vw); }

/* ── Flatpickr overrides ── */
.flatpickr-calendar { background:#0e1e2e !important; border:1px solid rgba(255,255,255,0.12) !important; box-shadow:0 8px 32px rgba(0,0,0,0.5) !important; }
.flatpickr-day { color:#c8d8e4 !important; }
.flatpickr-day:hover, .flatpickr-day.prevMonthDay:hover, .flatpickr-day.nextMonthDay:hover { background:rgba(160,64,255,0.15) !important; border-color:transparent !important; }
.flatpickr-day.selected, .flatpickr-day.selected:hover { background:#a040ff !important; border-color:#a040ff !important; color:#fff !important; }
.flatpickr-day.today { border-color:#a040ff !important; }
.flatpickr-months, .flatpickr-weekdays, .flatpickr-time { background:#0e1e2e !important; }
.flatpickr-current-month, .flatpickr-weekday, .numInputWrapper span { color:#c8d8e4 !important; }
.flatpickr-prev-month svg, .flatpickr-next-month svg { fill:#c8d8e4 !important; }
.flatpickr-time input, .flatpickr-time .flatpickr-am-pm { color:#c8d8e4 !important; background:#0a1520 !important; }
.flatpickr-time input:focus, .flatpickr-time .flatpickr-am-pm:focus { background:rgba(160,64,255,0.08) !important; }
.numInput { color:#c8d8e4 !important; background:#0a1520 !important; }
</style>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">

<div class="row" id="row1">
  <div class="main">
    <h2>Raffles</h2>
    <div class="content">
      <p style="font-size:0.85rem;opacity:0.6;margin-bottom:4px;">Buy tickets for a chance to win NFTs and prizes. The more tickets you hold, the better your odds.</p>

      <?php if ($member): ?>
      <div style="margin-bottom:16px;">
        <button class="small-button" onclick="openCreateRaffleModal()">+ Create Raffle</button>
      </div>
      <?php endif; ?>

      <?php if (empty($raffles)): ?>
      <div class="raffle-empty"><p>No active raffles right now. Check back later or create one!</p></div>
      <?php else: ?>
      <div class="raffles-grid">
        <?php foreach ($raffles as $r):
          $has_img  = !empty($r['image_path']) && file_exists($r['image_path']);
          $is_owner = (isset($_SESSION['userData']['user_id']) && intval($r['user_id']) === intval($_SESSION['userData']['user_id']));
          $upcoming = strtotime($r['start_date']) > $now_ts;
          $sold     = intval($r['total_tickets_sold']);
          $cheap    = $r['cheapest_ticket'];
        ?>
        <div class="raffle-card">
          <?php if ($has_img): ?>
          <img class="raffle-card-img" src="<?php echo htmlspecialchars($r['image_path']); ?>" alt="" />
          <?php else: ?>
          <div class="raffle-card-img-placeholder">&#x1F3AB;</div>
          <?php endif; ?>
          <div class="raffle-card-body">
            <?php if ($upcoming): ?>
            <div class="raffle-upcoming-badge">Upcoming</div>
            <?php endif; ?>
            <div class="raffle-card-title"><?php echo htmlspecialchars($r['title']); ?></div>
            <?php if ($cheap): ?>
            <div class="raffle-stat-row">
              <span class="raffle-stat-label">Ticket Price</span>
              <span class="raffle-stat-value" style="display:flex;align-items:center;gap:3px;"><?php
                $rcur_lc = strtolower($cheap['currency']);
                echo 'from ';
                echo '<img src="icons/' . $rcur_lc . '.png" style="width:14px;height:14px;vertical-align:middle;">';
                echo number_format($cheap['cost']) . ' ' . strtoupper($rcur_lc);
              ?></span>
            </div>
            <?php endif; ?>
            <div class="raffle-stat-row">
              <span class="raffle-stat-label">Tickets Sold</span>
              <span class="raffle-stat-value"><?php echo $sold; ?></span>
            </div>
            <div class="raffle-timer">
              <?php if ($upcoming): ?>
              Launches: <span class="countdown" data-deadline="<?php echo strtotime($r['start_date']); ?>"></span>
              <?php else: ?>
              Ends: <span class="countdown" data-deadline="<?php echo strtotime($r['end_date']); ?>"></span>
              <?php endif; ?>
            </div>
            <div style="font-size:0.72rem;opacity:0.35;display:flex;align-items:center;gap:5px;">By
              <?php if (!empty($r['creator_discord']) && !empty($r['creator_avatar'])): ?>
              <img src="https://cdn.discordapp.com/avatars/<?php echo $r['creator_discord']; ?>/<?php echo $r['creator_avatar']; ?>.png" style="width:14px;height:14px;border-radius:50%;vertical-align:middle;">
              <?php endif; ?>
              <a href="/staking/profile.php?username=<?php echo htmlspecialchars($r['creator_name']); ?>" style="color:inherit;text-decoration:underline;"><?php echo htmlspecialchars($r['creator_name']); ?></a>
            </div>
          </div>
          <div class="raffle-card-footer">
            <button class="small-button" onclick="openTicketModal(<?php echo $r['id']; ?>)" style="width:100%;">Buy Tickets</button>
            <?php if ($is_owner && !$r['completed'] && !$r['canceled']): ?>
            <button class="small-button small-button-danger" onclick="cancelRaffle(<?php echo $r['id']; ?>)" style="width:100%;margin-top:6px;">Cancel Raffle</button>
            <?php endif; ?>
            <?php if ($is_owner && !$r['completed'] && !$r['canceled']): ?>
            <button class="small-button" onclick="openEditRaffleModal(<?php echo $r['id']; ?>)" style="width:100%;margin-top:6px;">Edit Raffle</button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php endif; ?>
    </div>
  </div>
</div>

<!-- Create Raffle Modal -->
<div class="raffle-modal" id="create-raffle-modal">
  <div class="raffle-modal-overlay" onclick="closeCreateRaffleModal()"></div>
  <div class="raffle-modal-box">
    <button class="raffle-modal-close" onclick="closeCreateRaffleModal()">&times;</button>
    <div class="raffle-modal-title">Create Raffle</div>

    <div class="form-section-label">Prize Details</div>
    <div class="form-row"><label>Cardano Asset ID *</label><input type="text" id="r-asset-id" maxlength="44" placeholder="asset1..." /></div>
    <div id="r-img-status" style="font-size:0.75rem;margin-top:4px;display:none;"></div>
    <div id="r-img-preview" style="display:none;margin-top:6px;"><img id="r-img-preview-img" style="max-width:80px;max-height:80px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);object-fit:cover;" /></div>

    <div class="form-row" id="r-title-row" style="display:none;">
      <label>Title *</label>
      <input type="text" id="r-title" maxlength="255" placeholder="What are you raffling off?" />
    </div>

    <div class="form-row" id="r-image-row" style="display:none;">
      <label>Image Upload</label>
      <input type="file" id="r-image" accept="image/png,image/gif,image/jpeg,image/webp" />
      <input type="hidden" id="r-ipfs-url" />
    </div>

    <div class="form-row" id="r-qty-row" style="display:none;">
      <label>Quantity *</label>
      <input type="number" id="r-quantity" min="1" step="1" value="1" style="max-width:140px;" />
    </div>

    <div class="form-section-label" style="margin-top:8px;">Ticket Prices by Currency *</div>
    <div class="currency-rows" id="r-currency-rows"></div>
    <button type="button" onclick="addRaffleCurrencyRow()" style="background:rgba(160,64,255,0.08);border:1px solid rgba(160,64,255,0.2);border-radius:6px;color:#a040ff;padding:6px 14px;font-size:0.8rem;cursor:pointer;align-self:flex-start;">+ Add Currency</button>

    <div class="form-section-label" style="margin-top:8px;">Schedule</div>
    <div class="form-row"><label>Start Date (optional — leave blank to list immediately)</label><input type="text" id="r-start-date" /></div>
    <div class="form-row"><label>End Date *</label><input type="text" id="r-end-date" /></div>

    <div id="r-error" style="color:#ff6b6b;font-size:0.82rem;display:none;"></div>
    <button class="small-button" onclick="submitCreateRaffle()" style="margin-top:4px;">Create Raffle</button>
  </div>
</div>

<!-- Edit Raffle Modal -->
<div class="raffle-modal" id="edit-raffle-modal">
  <div class="raffle-modal-overlay" onclick="closeEditRaffleModal()"></div>
  <div class="raffle-modal-box">
    <button class="raffle-modal-close" onclick="closeEditRaffleModal()">&times;</button>
    <div class="raffle-modal-title">Edit Raffle</div>
    <input type="hidden" id="re-raffle-id" />

    <div class="form-section-label">Prize Details</div>
    <div class="form-row"><label>Cardano Asset ID *</label><input type="text" id="re-asset-id" maxlength="44" placeholder="asset1..." /></div>
    <div id="re-img-status" style="font-size:0.75rem;margin-top:4px;display:none;"></div>
    <div id="re-img-preview" style="display:none;margin-top:6px;"><img id="re-img-preview-img" style="max-width:80px;max-height:80px;border-radius:6px;border:1px solid rgba(255,255,255,0.12);object-fit:cover;" /></div>

    <div class="form-row" id="re-title-row">
      <label>Title *</label>
      <input type="text" id="re-title" maxlength="255" placeholder="What are you raffling off?" />
    </div>

    <div class="form-row" id="re-image-row">
      <label>Image Upload</label>
      <input type="file" id="re-image" accept="image/png,image/gif,image/jpeg,image/webp" />
      <input type="hidden" id="re-ipfs-url" />
    </div>

    <div class="form-row" id="re-qty-row" style="display:none;">
      <label>Quantity *</label>
      <input type="number" id="re-quantity" min="1" step="1" value="1" style="max-width:140px;" />
    </div>

    <div class="form-section-label" style="margin-top:8px;">Ticket Prices by Currency *</div>
    <div class="currency-rows" id="re-currency-rows"></div>
    <button type="button" onclick="addRaffleEditCurrencyRow()" style="background:rgba(160,64,255,0.08);border:1px solid rgba(160,64,255,0.2);border-radius:6px;color:#a040ff;padding:6px 14px;font-size:0.8rem;cursor:pointer;align-self:flex-start;">+ Add Currency</button>

    <div class="form-section-label" style="margin-top:8px;">Schedule</div>
    <div class="form-row"><label>Start Date (optional — leave blank to list immediately)</label><input type="text" id="re-start-date" /></div>
    <div class="form-row"><label>End Date *</label><input type="text" id="re-end-date" /></div>

    <div id="re-error" style="color:#ff6b6b;font-size:0.82rem;display:none;"></div>
    <button class="small-button" onclick="submitEditRaffle()" style="margin-top:4px;">Save Changes</button>
  </div>
</div>

<!-- Ticket Purchase Modal -->
<div class="raffle-modal" id="ticket-modal">
  <div class="raffle-modal-overlay" onclick="closeTicketModal()"></div>
  <div class="raffle-modal-box ticket-modal-box">
    <button class="raffle-modal-close" onclick="closeTicketModal()">&times;</button>
    <div id="ticket-modal-inner" style="display:flex;flex-direction:column;gap:12px;">
      <div style="opacity:0.5;font-size:0.85rem;">Loading…</div>
    </div>
  </div>
</div>

<!-- Footer -->
<div class="footer"><p>Skulliance<br>Copyright © <span id="year"></span></p></div>
</div></div>
<?php $conn->close(); ?>
<script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
<script type="text/javascript" src="skulliance.js"></script>
<script type="text/javascript">
// ── Project options ───────────────────────────────────────────────────────────
var allProjects = <?php echo json_encode(array_map(function($p){ return ['id'=>intval($p['id']),'name'=>$p['name'],'currency'=>strtoupper($p['currency']),'divider'=>(float)$p['divider']]; }, $all_projects)); ?>;
// Conversion rate to Diamond: Diamond(7)=1, Core(1-6)=6, Partner(8+,not 15)=12, CARBON(15)=10000
function getConversionRate(pid) {
  pid = parseInt(pid, 10);
  if (pid === 7)  return 1;
  if (pid === 15) return 100;
  if (pid >= 1 && pid <= 6) return 6;
  return 12;
}

// ── Countdown timers ──────────────────────────────────────────────────────────
function updateCountdowns() {
  var now = Math.floor(Date.now() / 1000);
  document.querySelectorAll('.countdown[data-deadline]').forEach(function(el) {
    var deadline = parseInt(el.dataset.deadline, 10);
    var diff = deadline - now;
    if (diff <= 0) { el.textContent = 'Ended'; return; }
    var d = Math.floor(diff / 86400);
    var h = Math.floor((diff % 86400) / 3600);
    var m = Math.floor((diff % 3600) / 60);
    var s = diff % 60;
    el.textContent = (d > 0 ? d + 'd ' : '') + (h > 0 ? h + 'h ' : '') + (m > 0 ? m + 'm ' : '') + s + 's';
  });
}
setInterval(updateCountdowns, 1000);
updateCountdowns();

// ── Create Raffle ─────────────────────────────────────────────────────────────
function openCreateRaffleModal() {
  var rows = document.getElementById('r-currency-rows');
  if (rows.children.length === 0) addRaffleCurrencyRow();
  document.getElementById('create-raffle-modal').classList.add('open');
}
function closeCreateRaffleModal() {
  document.getElementById('create-raffle-modal').classList.remove('open');
}

function buildProjectOptions(selectedId, excludeIds) {
  excludeIds = excludeIds || [];
  return allProjects.filter(function(p) {
    return p.id == selectedId || excludeIds.indexOf(p.id) === -1;
  }).map(function(p) {
    var sel = (p.id == selectedId) ? ' selected' : '';
    return '<option value="' + p.id + '"' + sel + '>' + p.name + ' (' + p.currency + ')</option>';
  }).join('');
}

function getUsedProjectIds(containerSel, selectClass) {
  var ids = [];
  document.querySelectorAll(containerSel + ' ' + selectClass).forEach(function(s) {
    var v = parseInt(s.value, 10);
    if (v) ids.push(v);
  });
  return ids;
}

function refreshCurrencySelects(containerSel, selectClass, skipEl) {
  document.querySelectorAll(containerSel + ' ' + selectClass).forEach(function(sel) {
    if (skipEl && sel === skipEl) return;
    var currentVal = parseInt(sel.value, 10) || '';
    var otherUsed = getUsedProjectIds(containerSel, selectClass).filter(function(id) { return id !== currentVal; });
    sel.innerHTML = buildProjectOptions(currentVal, otherUsed);
  });
}

// ── Currency conversion sync ──────────────────────────────────────────────────
function syncBids(sourceRow, containerSel, bidClass, selectClass) {
  var sourcePid = parseInt(sourceRow.querySelector(selectClass).value, 10);
  var sourceVal = parseFloat(sourceRow.querySelector(bidClass).value);
  if (!sourcePid || !sourceVal || sourceVal <= 0) return;
  var baseDiamonds = sourceVal / getConversionRate(sourcePid);
  document.querySelectorAll(containerSel + ' .currency-row').forEach(function(row) {
    if (row === sourceRow) return;
    var targetPid = parseInt(row.querySelector(selectClass).value, 10);
    if (!targetPid) return;
    var computed = Math.ceil(baseDiamonds * getConversionRate(targetPid));
    row.querySelector(bidClass).value = computed > 0 ? computed : '';
  });
}

function attachBidSync(row, containerSel, bidClass, selectClass) {
  row.querySelector(bidClass).addEventListener('input', function() {
    syncBids(row, containerSel, bidClass, selectClass);
  });
  row.querySelector(selectClass).addEventListener('change', function() {
    refreshCurrencySelects(containerSel, selectClass);
    var sourceRow = null;
    document.querySelectorAll(containerSel + ' .currency-row').forEach(function(r) {
      if (r === row) return;
      if (!sourceRow && parseFloat(r.querySelector(bidClass).value) > 0) sourceRow = r;
    });
    if (sourceRow) syncBids(sourceRow, containerSel, bidClass, selectClass);
  });
}

function addRaffleCurrencyRow(projectId, cost) {
  var rows    = document.getElementById('r-currency-rows');
  var usedIds = getUsedProjectIds('#r-currency-rows', '.r-proj-select');
  var avail   = allProjects.filter(function(p) { return usedIds.indexOf(p.id) === -1; });
  if (!projectId && avail.length === 0) return;
  var selId = projectId || (avail[0] ? avail[0].id : '');
  var div   = document.createElement('div');
  div.className = 'currency-row';
  div.innerHTML =
    '<select class="r-proj-select">' + buildProjectOptions(selId, usedIds) + '</select>' +
    '<input type="number" class="r-cost" min="1" step="1" placeholder="Cost per ticket" value="' + (cost || '') + '" />' +
    '<button type="button" class="rm-btn" onclick="removeRaffleCurrencyRow(this)">Remove</button>';
  rows.appendChild(div);
  refreshCurrencySelects('#r-currency-rows', '.r-proj-select', div.querySelector('.r-proj-select'));
  attachBidSync(div, '#r-currency-rows', '.r-cost', '.r-proj-select');
}

function removeRaffleCurrencyRow(btn) {
  btn.parentNode.remove();
  refreshCurrencySelects('#r-currency-rows', '.r-proj-select');
}

// ── Asset IPFS auto-lookup ────────────────────────────────────────────────────
function setupAssetLookup(assetInputId, fileInputId, statusId, previewId, previewImgId, ipfsHiddenId, titleInputId, quantityWrapperId, titleWrapperId, imageWrapperId) {
  var timer      = null;
  var assetInput = document.getElementById(assetInputId);
  var fileInput  = document.getElementById(fileInputId);
  var status     = document.getElementById(statusId);
  var preview    = document.getElementById(previewId);
  var previewImg = document.getElementById(previewImgId);
  var ipfsHidden = document.getElementById(ipfsHiddenId);
  var titleInput = titleInputId ? document.getElementById(titleInputId) : null;
  var qtyRow     = quantityWrapperId ? document.getElementById(quantityWrapperId) : null;
  var qtyInput   = qtyRow ? qtyRow.querySelector('input[type=number]') : null;
  var titleWrap  = titleWrapperId ? document.getElementById(titleWrapperId) : null;
  var imageWrap  = imageWrapperId ? document.getElementById(imageWrapperId) : null;

  function setQtyVisible(visible) {
    if (!qtyRow) return;
    qtyRow.style.display = visible ? '' : 'none';
    if (!visible && qtyInput) qtyInput.value = 1;
  }
  function setTitleVisible(v) { if (titleWrap) titleWrap.style.display = v ? '' : 'none'; }
  function setImageVisible(v) { if (imageWrap) imageWrap.style.display = v ? '' : 'none'; }

  assetInput.addEventListener('input', function() {
    clearTimeout(timer);
    var val = this.value.trim();
    ipfsHidden.value      = '';
    preview.style.display = 'none';
    status.style.display  = 'none';
    if (!/^asset1[a-z0-9]{38}$/.test(val)) {
      setQtyVisible(false);
      setTitleVisible(true);
      setImageVisible(true);
      return;
    }
    status.textContent   = 'Looking up asset…';
    status.style.color   = '#8899aa';
    status.style.display = 'block';
    timer = setTimeout(function() {
      $.getJSON('ajax/asset-lookup.php', { asset_id: val }, function(r) {
        if (r.success) {
          if (r.name) {
            if (titleInput && titleInput.value.trim() === '') titleInput.value = r.name;
            setTitleVisible(false);
          } else {
            setTitleVisible(true);
          }
          setQtyVisible(r.is_fungible);
          if (r.ipfs_raw) {
            ipfsHidden.value   = r.ipfs_raw;
            previewImg.src     = r.ipfs_url;
            previewImg.onload  = function() { preview.style.display = 'block'; };
            previewImg.onerror = function() { preview.style.display = 'none'; };
            status.textContent = 'Asset found — image and name pre-filled.';
            status.style.color = '#a040ff';
            setImageVisible(false);
          } else {
            status.textContent = 'Asset found — please upload an image manually.';
            status.style.color = '#8899aa';
            setImageVisible(true);
          }
        } else {
          setQtyVisible(true);
          setTitleVisible(true);
          setImageVisible(true);
          status.textContent = 'Asset not found — please fill in details manually.';
          status.style.color = '#8899aa';
        }
      }).fail(function() {
        setQtyVisible(true);
        setTitleVisible(true);
        setImageVisible(true);
        status.textContent = 'Lookup failed — please fill in details manually.';
        status.style.color = '#8899aa';
      });
    }, 600);
  });

  fileInput.addEventListener('change', function() {
    if (this.files.length > 0) {
      ipfsHidden.value      = '';
      preview.style.display = 'none';
      status.textContent    = 'Using uploaded file.';
      status.style.color    = '#8899aa';
      status.style.display  = 'block';
    }
  });
}

var _fpCfg = {
  enableTime:      true,
  altInput:        true,
  altFormat:       'M j, Y — h:i K',
  dateFormat:      'Y-m-d H:i',
  minuteIncrement: 15,
  minDate:         'today',
};

document.addEventListener('DOMContentLoaded', function() {
  setupAssetLookup('r-asset-id',  'r-image',  'r-img-status',  'r-img-preview',  'r-img-preview-img',  'r-ipfs-url',  'r-title',  'r-qty-row',  'r-title-row',  'r-image-row');
  setupAssetLookup('re-asset-id', 're-image', 're-img-status', 're-img-preview', 're-img-preview-img', 're-ipfs-url', 're-title', 're-qty-row', null, null);

  flatpickr('#r-start-date',  Object.assign({}, _fpCfg, { minDate: null }));
  flatpickr('#r-end-date',    _fpCfg);
  flatpickr('#re-start-date', Object.assign({}, _fpCfg, { minDate: null }));
  flatpickr('#re-end-date',   _fpCfg);
});

function submitCreateRaffle() {
  var err = document.getElementById('r-error');
  err.style.display = 'none';
  var title     = document.getElementById('r-title').value.trim();
  var assetId   = document.getElementById('r-asset-id').value.trim();
  var startDate = document.getElementById('r-start-date').value;
  var endDate   = document.getElementById('r-end-date').value;
  var imgFile   = document.getElementById('r-image').files[0];

  if (!title) { err.textContent = 'Title is required.'; err.style.display = 'block'; return; }
  if (!endDate) { err.textContent = 'End date is required.'; err.style.display = 'block'; return; }
  if (!assetId) { err.textContent = 'Cardano Asset ID is required.'; err.style.display = 'block'; return; }
  if (!/^asset1[a-z0-9]{38}$/.test(assetId)) { err.textContent = 'Asset ID must be in asset1... fingerprint format (e.g. asset16jt7ekn7...).'; err.style.display = 'block'; return; }

  var ticketOptions = [];
  document.querySelectorAll('#r-currency-rows .currency-row').forEach(function(row) {
    var pid  = parseInt(row.querySelector('.r-proj-select').value, 10);
    var cost = parseInt(row.querySelector('.r-cost').value, 10);
    if (pid > 0 && cost > 0) ticketOptions.push({ project_id: pid, cost: cost });
  });
  if (ticketOptions.length === 0) { err.textContent = 'Add at least one currency with a ticket price.'; err.style.display = 'block'; return; }

  var fd = new FormData();
  fd.append('title', title);
  fd.append('asset_id', assetId);
  fd.append('ticket_options', JSON.stringify(ticketOptions));
  fd.append('start_date', startDate);
  fd.append('end_date', endDate);
  var rQtyRow = document.getElementById('r-qty-row');
  fd.append('quantity', (rQtyRow && rQtyRow.style.display !== 'none') ? (parseInt(document.getElementById('r-quantity').value) || 1) : 1);
  if (imgFile) fd.append('image', imgFile);
  else { var ipfsUrl = document.getElementById('r-ipfs-url').value; if (ipfsUrl) fd.append('ipfs_url', ipfsUrl); }

  $.ajax({
    url: 'ajax/raffle-create.php',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    dataType: 'text',
    success: function(res) {
      try { var r = JSON.parse(res); } catch(e) { err.textContent = 'Unexpected error.'; err.style.display = 'block'; return; }
      if (r.success) { location.reload(); }
      else { err.textContent = r.message || 'Error creating raffle.'; err.style.display = 'block'; }
    },
    error: function() { err.textContent = 'Server error.'; err.style.display = 'block'; }
  });
}

// ── Ticket Modal ──────────────────────────────────────────────────────────────
function updateRaffleTicketUI() {
  var sel = document.getElementById('raffle-project-select');
  if (!sel) return;
  var opt  = sel.options[sel.selectedIndex];
  var cost = parseInt(opt.dataset.cost, 10) || 0;
  var qty  = parseInt(document.getElementById('ticket-qty').value, 10) || 1;
  var curLc = opt.dataset.currency || '';
  var curUc = curLc.toUpperCase();
  document.getElementById('raffle-cost-per').textContent = cost.toLocaleString();
  document.getElementById('ticket-total').textContent    = (cost * qty).toLocaleString();
  document.getElementById('raffle-cur-label').innerHTML  = curLc
    ? '<img src="icons/' + curLc + '.png" style="width:14px;height:14px;vertical-align:middle;margin-right:2px;">' + curUc
    : '';
}
function openTicketModal(raffle_id) {
  document.getElementById('ticket-modal').classList.add('open');
  $.get('ajax/raffle-detail.php', { id: raffle_id }, function(html) {
    document.getElementById('ticket-modal-inner').innerHTML = html;
    updateCountdowns();
    var sel = document.getElementById('raffle-project-select');
    var qty = document.getElementById('ticket-qty');
    if (sel) sel.addEventListener('change', updateRaffleTicketUI);
    if (qty) qty.addEventListener('input', updateRaffleTicketUI);
    updateRaffleTicketUI();
  });
}
function closeTicketModal() {
  document.getElementById('ticket-modal').classList.remove('open');
}

function submitBuyTickets(raffle_id) {
  var qty  = parseInt(document.getElementById('ticket-qty').value, 10);
  var sel  = document.getElementById('raffle-project-select');
  var pid  = sel ? parseInt(sel.value, 10) : 0;
  var err  = document.getElementById('ticket-error');
  err.style.display = 'none';
  if (!qty || qty < 1) { err.textContent = 'Enter a valid quantity.'; err.style.display = 'block'; return; }
  if (!pid) { err.textContent = 'Select a currency.'; err.style.display = 'block'; return; }
  $.post('ajax/raffle-buy.php', { raffle_id: raffle_id, project_id: pid, quantity: qty }, function(res) {
    var r; try { r = JSON.parse(res); } catch(e) { err.textContent = 'Server error: ' + String(res).replace(/<[^>]+>/g,'').trim().slice(0,200); err.style.display = 'block'; return; }
    if (r.success) { openNotify(r.message); setTimeout(function(){ openTicketModal(raffle_id); }, 800); }
    else { err.textContent = r.message || 'Purchase failed.'; err.style.display = 'block'; }
  }, 'text');
}

// ── Edit Raffle Modal ─────────────────────────────────────────────────────────
function openEditRaffleModal(id) {
  $.getJSON('ajax/raffle-edit-load.php', { id: id }, function(r) {
    if (!r.success) { openNotify(r.message || 'Could not load raffle data.'); return; }
    document.getElementById('re-raffle-id').value    = id;
    document.getElementById('re-title').value         = r.title || '';
    document.getElementById('re-asset-id').value      = r.asset_id || '';
    var fpStart = document.getElementById('re-start-date')._flatpickr;
    var fpEnd   = document.getElementById('re-end-date')._flatpickr;
    if (r.start_date) fpStart.setDate(r.start_date, false); else fpStart.clear();
    if (r.end_date)   fpEnd.setDate(r.end_date, false);     else fpEnd.clear();
    document.getElementById('re-image').value         = '';
    var rows = document.getElementById('re-currency-rows');
    rows.innerHTML = '';
    (r.ticket_options || []).forEach(function(o) { addRaffleEditCurrencyRow(o.project_id, o.cost); });
    if (rows.children.length === 0) addRaffleEditCurrencyRow();
    var reQr = document.getElementById('re-qty-row');
    var qty  = parseInt(r.quantity) || 1;
    if (reQr) { reQr.style.display = qty > 1 ? '' : 'none'; document.getElementById('re-quantity').value = qty > 1 ? qty : 1; }
    document.getElementById('re-error').style.display = 'none';
    document.getElementById('edit-raffle-modal').classList.add('open');
  });
}
function closeEditRaffleModal() {
  document.getElementById('edit-raffle-modal').classList.remove('open');
}

function addRaffleEditCurrencyRow(projectId, cost) {
  var rows    = document.getElementById('re-currency-rows');
  var usedIds = getUsedProjectIds('#re-currency-rows', '.re-proj-select');
  var avail   = allProjects.filter(function(p) { return usedIds.indexOf(p.id) === -1; });
  if (!projectId && avail.length === 0) return;
  var selId = projectId || (avail[0] ? avail[0].id : '');
  var div   = document.createElement('div');
  div.className = 'currency-row';
  div.innerHTML =
    '<select class="re-proj-select">' + buildProjectOptions(selId, usedIds) + '</select>' +
    '<input type="number" class="re-cost" min="1" step="1" placeholder="Cost per ticket" value="' + (cost || '') + '" />' +
    '<button type="button" class="rm-btn" onclick="removeRaffleEditCurrencyRow(this)">Remove</button>';
  rows.appendChild(div);
  refreshCurrencySelects('#re-currency-rows', '.re-proj-select', div.querySelector('.re-proj-select'));
  attachBidSync(div, '#re-currency-rows', '.re-cost', '.re-proj-select');
}

function removeRaffleEditCurrencyRow(btn) {
  btn.parentNode.remove();
  refreshCurrencySelects('#re-currency-rows', '.re-proj-select');
}

function submitEditRaffle() {
  var err = document.getElementById('re-error');
  err.style.display = 'none';
  var raffleId   = document.getElementById('re-raffle-id').value;
  var title      = document.getElementById('re-title').value.trim();
  var assetId    = document.getElementById('re-asset-id').value.trim();
  var startDate  = document.getElementById('re-start-date').value;
  var endDate    = document.getElementById('re-end-date').value;
  var imgFile    = document.getElementById('re-image').files[0];

  if (!title)   { err.textContent = 'Title is required.'; err.style.display = 'block'; return; }
  if (!endDate) { err.textContent = 'End date is required.'; err.style.display = 'block'; return; }
  if (!assetId) { err.textContent = 'Cardano Asset ID is required.'; err.style.display = 'block'; return; }
  if (!/^asset1[a-z0-9]{38}$/.test(assetId)) { err.textContent = 'Asset ID must be in asset1... fingerprint format.'; err.style.display = 'block'; return; }

  var ticketOptions = [];
  document.querySelectorAll('#re-currency-rows .currency-row').forEach(function(row) {
    var pid  = parseInt(row.querySelector('.re-proj-select').value, 10);
    var cost = parseInt(row.querySelector('.re-cost').value, 10);
    if (pid > 0 && cost > 0) ticketOptions.push({ project_id: pid, cost: cost });
  });
  if (ticketOptions.length === 0) { err.textContent = 'Add at least one currency with a ticket price.'; err.style.display = 'block'; return; }

  var fd = new FormData();
  fd.append('raffle_id', raffleId);
  fd.append('title', title);
  fd.append('asset_id', assetId);
  fd.append('ticket_options', JSON.stringify(ticketOptions));
  fd.append('start_date', startDate);
  fd.append('end_date', endDate);
  var reQtyRow = document.getElementById('re-qty-row');
  fd.append('quantity', (reQtyRow && reQtyRow.style.display !== 'none') ? (parseInt(document.getElementById('re-quantity').value) || 1) : 1);
  if (imgFile) fd.append('image', imgFile);
  else { var ipfsUrl = document.getElementById('re-ipfs-url').value; if (ipfsUrl) fd.append('ipfs_url', ipfsUrl); }

  $.ajax({
    url: 'ajax/raffle-edit.php',
    type: 'POST',
    data: fd,
    processData: false,
    contentType: false,
    dataType: 'text',
    success: function(res) {
      try { var r = JSON.parse(res); } catch(e) { err.textContent = 'Unexpected error.'; err.style.display = 'block'; return; }
      if (r.success) { location.reload(); }
      else { err.textContent = r.message || 'Error saving raffle.'; err.style.display = 'block'; }
    },
    error: function() { err.textContent = 'Server error.'; err.style.display = 'block'; }
  });
}

function cancelRaffle(raffle_id) {
  openConfirm('Cancel this raffle? All ticket buyers will be fully refunded.', function() {
    $.post('ajax/raffle-cancel.php', { raffle_id: raffle_id }, function(res) {
      var r; try { r = JSON.parse(res); } catch(e) { openNotify('Server error: ' + String(res).replace(/<[^>]+>/g,'').trim().slice(0,200)); return; }
      if (r.success) { location.reload(); }
      else { openNotify(r.message || 'Could not cancel raffle.'); }
    }, 'text');
  });
}
</script>
</html>
