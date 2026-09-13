<?php
/**
 * DHC FIGHTERS
 *
 * The trait game. Earn traits by playing everything else on the platform,
 * assemble them into Fighters, save them, compete on rarity.
 *
 * Unlike dhcsandbox.php -- which is public and deliberately includes nothing --
 * this is a platform page: it needs the session to know whose traits to show,
 * so it takes db.php, skulliance.php and header.php like every other game.
 *
 * The assembler is the same dhc-assembler.php the sandbox uses, handed an
 * ownership filter instead of the full trait list.
 */
include 'db.php';
include 'skulliance.php';
require_once __DIR__ . '/dhcfighters-lib.php';

$dhcf_user = isset($_SESSION['userData']['user_id']) ? (int)$_SESSION['userData']['user_id'] : 0;

$dhcf_avail    = $dhcf_user ? dhcf_available($conn, $dhcf_user) : array();
$dhcf_roster   = $dhcf_user ? dhcf_fighters($conn, $dhcf_user)  : array();
$dhcf_next     = dhcf_default_name(dhcf_next_serial($conn));
$dhcf_lb_ath   = dhcf_leaderboard($conn, 'ath', 10);
$dhcf_lb_month = dhcf_leaderboard($conn, 'monthly', 10);

// totals for the header strip
$dhcf_owned_n = 0; $dhcf_free_n = 0;
foreach ($dhcf_avail as $cat => $traits) {
	foreach ($traits as $slug => $t) { $dhcf_owned_n += $t['copies']; $dhcf_free_n += $t['free']; }
}
$dhcf_best = 0;
foreach ($dhcf_roster as $f) if ((int)$f['rarity_score'] > $dhcf_best) $dhcf_best = (int)$f['rarity_score'];

// Usernames for the leaderboards, resolved in one pass rather than per row.
$dhcf_names = array();
$dhcf_ids = array();
foreach (array_merge($dhcf_lb_ath, $dhcf_lb_month) as $r) $dhcf_ids[(int)$r['user_id']] = true;
if ($dhcf_ids) {
	$in = implode(',', array_map('intval', array_keys($dhcf_ids)));
	$res = $conn->query("SELECT id, username FROM users WHERE id IN ($in)");
	if ($res) while ($row = $res->fetch_assoc()) $dhcf_names[(int)$row['id']] = $row['username'];
}
function dhcf_user_label($id) {
	global $dhcf_names;
	return isset($dhcf_names[$id]) ? htmlspecialchars($dhcf_names[$id]) : 'player ' . (int)$id;
}

include 'header.php';

// Hand the assembler the player's holdings. Everything the picker offers is
// something they actually hold a free copy of.
$dhca_mode  = 'fighters';
$dhca_owned = $dhcf_avail;
?>

<style>
/* Page chrome only -- the assembler brings its own styles. */
.dhcf-wrap{max-width:1500px;margin:0 auto;padding:14px}
.dhcf-head{display:flex;flex-wrap:wrap;align-items:baseline;gap:12px;margin:0 0 4px}
.dhcf-head h1{margin:0;font-size:22px;letter-spacing:.02em}
.dhcf-head .sub{font-size:12px;opacity:.7}
.dhcf-note{font-size:11.5px;opacity:.65;line-height:1.6;margin:0 0 14px;max-width:70ch}
.dhcf-stats{display:flex;flex-wrap:wrap;gap:8px;margin:0 0 14px}
.dhcf-stat{border:1px solid rgba(255,255,255,.14);border-radius:3px;padding:7px 12px;min-width:96px}
.dhcf-stat b{display:block;font-size:17px;font-variant-numeric:tabular-nums}
.dhcf-stat span{font-size:9.5px;letter-spacing:.12em;text-transform:uppercase;opacity:.6}
.dhcf-empty{border:1px dashed rgba(255,255,255,.2);border-radius:3px;padding:22px;
  font-size:13px;line-height:1.75;margin:0 0 16px}
.dhcf-empty b{font-size:15px}
.dhcf-panels{display:grid;grid-template-columns:repeat(auto-fit,minmax(320px,1fr));gap:14px;margin-top:18px}
.dhcf-panel{border:1px solid rgba(255,255,255,.14);border-radius:3px;overflow:hidden}
.dhcf-panel h2{margin:0;padding:9px 12px;font-size:10px;letter-spacing:.16em;text-transform:uppercase;
  opacity:.65;border-bottom:1px solid rgba(255,255,255,.12)}
.dhcf-panel .body{padding:10px 12px}
.dhcf-roster{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:10px}
.dhcf-card{border:1px solid rgba(255,255,255,.14);border-radius:3px;overflow:hidden;position:relative}
.dhcf-card .art{position:relative;aspect-ratio:1;background:#16110f;overflow:hidden}
.dhcf-card .art img{position:absolute;inset:0;width:100%;height:100%;object-fit:contain}
.dhcf-card .meta{padding:6px 8px}
.dhcf-card .nm{font-size:11.5px;display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
.dhcf-card .sc{font-size:10px;opacity:.6;font-variant-numeric:tabular-nums}
.dhcf-card .acts{display:flex;gap:5px;padding:0 8px 8px}
.dhcf-card .acts button{flex:1;font:inherit;font-size:9px;letter-spacing:.08em;text-transform:uppercase;
  padding:4px;cursor:pointer;background:none;border:1px solid rgba(255,255,255,.18);border-radius:2px;color:inherit}
.dhcf-card .acts button:hover{border-color:#c8913c;color:#c8913c}
.dhcf-lb{width:100%;border-collapse:collapse;font-size:12px}
.dhcf-lb th{text-align:left;font-size:9px;letter-spacing:.12em;text-transform:uppercase;opacity:.55;padding:4px 6px}
.dhcf-lb td{padding:5px 6px;border-top:1px solid rgba(255,255,255,.08);font-variant-numeric:tabular-nums}
.dhcf-lb .r{width:26px;opacity:.6}
.dhcf-save{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:12px 0 0}
.dhcf-save input{flex:1;min-width:170px;font:inherit;font-size:12px;padding:7px 9px;border-radius:2px;
  background:rgba(0,0,0,.28);border:1px solid rgba(255,255,255,.18);color:inherit}
.dhcf-save button{font:inherit;font-size:11px;letter-spacing:.08em;text-transform:uppercase;padding:8px 16px;
  cursor:pointer;border-radius:2px;background:#8f2f27;border:1px solid #b8433a;color:#f2e9df}
.dhcf-save button:hover{background:#a8372d}
.dhcf-say{font-size:11.5px;min-height:16px;opacity:.85}
</style>

<div class="dhcf-wrap">

  <div class="dhcf-head">
    <h1>DHC Fighters</h1>
    <span class="sub">Digital Hell Citizens 2 &middot; art by Maxingo</span>
  </div>
  <p class="dhcf-note">
    Earn traits by playing across the platform, then assemble and save Fighters. Your best Fighter's
    rarity score sets your place on the board.
    <br>
    Assembled Fighters are a platform feature only &mdash; they are not NFTs, cannot be minted, and are
    not part of the official Digital Hell Citizens collection.
  </p>

  <div class="dhcf-stats">
    <div class="dhcf-stat"><b><?php echo (int)$dhcf_owned_n; ?></b><span>Traits held</span></div>
    <div class="dhcf-stat"><b><?php echo (int)$dhcf_free_n; ?></b><span>Unused</span></div>
    <div class="dhcf-stat"><b><?php echo count($dhcf_roster); ?></b><span>Fighters</span></div>
    <div class="dhcf-stat"><b><?php echo number_format($dhcf_best); ?></b><span>Best score</span></div>
    <div class="dhcf-stat"><b><?php echo htmlspecialchars($dhcf_next); ?></b><span>Next number</span></div>
  </div>

<?php if ($dhcf_owned_n === 0): ?>
  <div class="dhcf-empty">
    <b>You have no traits yet.</b><br>
    Traits drop while you play. Each game pays a different part of a Fighter, so a complete
    character takes a spread of them:
    <br><br>
    <?php
      $bits = array();
      foreach ($GLOBALS['DHCF_GAMES'] as $k => $g) {
          $cat = $g['category'] === 'wildcard' ? 'any trait' : $g['category'];
          $bits[] = '<b>' . htmlspecialchars($g['label']) . '</b> &rarr; ' . htmlspecialchars($cat)
                  . ' <span style="opacity:.6">(' . htmlspecialchars($g['trigger']) . ')</span>';
      }
      echo implode('<br>', $bits);
    ?>
  </div>
<?php endif; ?>

  <?php include __DIR__ . '/dhc-assembler.php'; ?>

  <div class="dhcf-save">
    <input type="text" id="dhcfName" maxlength="48"
           placeholder="Name this Fighter (optional &mdash; defaults to <?php echo htmlspecialchars($dhcf_next); ?>)">
    <button type="button" id="dhcfSave">Save Fighter</button>
    <span class="dhcf-say" id="dhcfSay"></span>
  </div>

  <div class="dhcf-panels">

    <div class="dhcf-panel">
      <h2>Your Fighters</h2>
      <div class="body">
        <?php if (!$dhcf_roster): ?>
          <p style="font-size:12px;opacity:.6;margin:2px 0">Nothing saved yet.</p>
        <?php else: ?>
        <div class="dhcf-roster">
          <?php foreach ($dhcf_roster as $f): ?>
            <div class="dhcf-card" data-id="<?php echo (int)$f['id']; ?>">
              <div class="art">
                <?php
                  // Same draw order the assembler uses, rendered small.
                  foreach (dhcf_slots() as $slot) {
                      if (empty($f['traits'][$slot])) continue;
                      $dir = dhcf_slot_category($slot);
                      echo '<img loading="lazy" alt="" src="' . htmlspecialchars($dhc_base . '/250/' . $dir . '/' . $f['traits'][$slot] . '.png') . '">';
                  }
                ?>
              </div>
              <div class="meta">
                <span class="nm"><?php echo htmlspecialchars($f['display']); ?></span>
                <span class="sc"><?php echo number_format((int)$f['rarity_score']); ?> pts</span>
              </div>
              <div class="acts">
                <button type="button" class="dhcf-rename">Rename</button>
                <button type="button" class="dhcf-scrap">Disassemble</button>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
    </div>

    <div class="dhcf-panel">
      <h2>All-time &mdash; best Fighter</h2>
      <div class="body">
        <?php echo dhcf_board_html($dhcf_lb_ath); ?>
      </div>
    </div>

    <div class="dhcf-panel">
      <h2>This month</h2>
      <div class="body">
        <?php echo dhcf_board_html($dhcf_lb_month); ?>
      </div>
    </div>

  </div>
</div>

<?php
function dhcf_board_html($rows) {
	if (!$rows) return '<p style="font-size:12px;opacity:.6;margin:2px 0">No Fighters saved yet.</p>';
	$h = '<table class="dhcf-lb"><tr><th class="r">#</th><th>Player</th><th>Best</th><th>Fighters</th></tr>';
	$i = 0;
	foreach ($rows as $r) {
		$i++;
		$h .= '<tr><td class="r">' . $i . '</td><td>' . dhcf_user_label((int)$r['user_id']) . '</td>'
		    . '<td>' . number_format((int)$r['best_score']) . '</td>'
		    . '<td>' . (int)$r['fighters'] . '</td></tr>';
	}
	return $h . '</table>';
}
?>

<script>
(function () {
  var say = document.getElementById('dhcfSay');
  function msg(t, good) { say.textContent = t; say.style.color = good ? '#4f9d84' : '#d0463a'; }

  /* The assembler exposes its current selection on window.DHC_SELECTION -- the
     save button reads it rather than the DOM, so what gets stored is exactly
     what the renderer was told to draw. */
  document.getElementById('dhcfSave').addEventListener('click', function () {
    var sel = (window.DHC_SELECTION && window.DHC_SELECTION()) || {};
    if (!Object.keys(sel).length) { msg('Pick some traits first.', false); return; }
    var btn = this; btn.disabled = true; msg('Saving...', true);
    var body = 'name=' + encodeURIComponent(document.getElementById('dhcfName').value) +
               '&traits=' + encodeURIComponent(JSON.stringify(sel));
    fetch('ajax/dhc-save-fighter.php', {
      method: 'POST', credentials: 'same-origin',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'}, body: body
    }).then(function (r) { return r.json(); })
      .then(function (d) {
        btn.disabled = false;
        if (d.ok) { msg('Saved as ' + d.display + ' — ' + d.score + ' pts', true); setTimeout(function(){location.reload();}, 900); }
        else msg(d.message || 'Could not save.', false);
      })
      .catch(function () { btn.disabled = false; msg('Network error.', false); });
  });

  document.querySelectorAll('.dhcf-rename').forEach(function (b) {
    b.addEventListener('click', function () {
      var card = b.closest('.dhcf-card');
      var cur  = card.querySelector('.nm').textContent;
      var name = prompt('Name for this Fighter (blank to use its number):', cur);
      if (name === null) return;
      fetch('ajax/dhc-rename-fighter.php', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + card.dataset.id + '&name=' + encodeURIComponent(name)
      }).then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) card.querySelector('.nm').textContent = d.display; });
    });
  });

  document.querySelectorAll('.dhcf-scrap').forEach(function (b) {
    b.addEventListener('click', function () {
      var card = b.closest('.dhcf-card');
      if (!confirm('Disassemble ' + card.querySelector('.nm').textContent +
                   '? Its traits return to your unused pile. The number is retired.')) return;
      fetch('ajax/dhc-delete-fighter.php', {
        method: 'POST', credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: 'id=' + card.dataset.id
      }).then(function (r) { return r.json(); })
        .then(function (d) { if (d.ok) location.reload(); });
    });
  });
})();
</script>

<script type="text/javascript" src="skulliance.js?var=<?php echo rand(0,999); ?>"></script>
<?php
$conn->close();
?>
</html>
